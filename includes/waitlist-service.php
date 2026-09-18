<?php
/**
 * Waitlist service.
 *
 * Single implementation of the waitlist life cycle, used by the member page,
 * the admin pages, the cancellation handlers and the expiry script.
 *
 *   waiting  (waitlist_status NULL)
 *      | lsc_waitlist_offer_slot()      a court in that slot became free
 *   pending  (offer sent, placeholder booking holds the court)
 *      | lsc_waitlist_confirm()         member accepts (credit deducted) or admin confirms
 *   confirmed
 *      | lsc_waitlist_decline()         member/guest/admin declines, or offer times out
 *   declined / expired                  placeholder cancelled, next waiting entry offered
 *
 * Members and guests share one queue ordered by created_at.
 *  - Member offers: the member (or an admin) confirms; the court fee plus guest fees is
 *    deducted from the member's credit.
 *  - Guest offers: ONLY an admin can confirm, choosing cash or bank transfer, because guests
 *    have no credit balance. The guest is told by SMS that staff will contact them; the club
 *    is told by LINE. The guest may decline their own offer.
 *
 * Rules:
 *  - An offer is only made when the slot starts more than LSC_WAITLIST_OFFER_MIN_LEAD seconds from now.
 *  - An offer must be confirmed within LSC_WAITLIST_OFFER_TTL seconds of being sent.
 *  - Credit is always deducted from the waitlisted member, never from the actor.
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

const LSC_WAITLIST_OFFER_TTL      = 2 * 3600; // seconds to confirm an offer
const LSC_WAITLIST_OFFER_MIN_LEAD = 2 * 3600; // slot must start at least this far in the future
const LSC_COURT_FEE_DAY           = 160;
const LSC_COURT_FEE_EVENING       = 280;
const LSC_GUEST_FEE               = 200;   // per extra player
const LSC_COACH_FEE               = 750;   // assistant coach, guest bookings only
const LSC_DAILY_FEES              = ['individual' => 500, 'couple' => 800, 'family' => 950, 'junior' => 350, '1 adult 1 child' => 650];
const LSC_SENTINEL_IDS            = [0, 90001, 90002];
const LSC_ADMIN_URL               = 'https://booking.lesmashclub.com';

/* ------------------------------------------------------------------ clock & notifier (overridable in tests) */

function lsc_waitlist_now(?DateTimeInterface $set = null, bool $reset = false): DateTimeImmutable
{
    static $fixed = null;
    if ($reset) {
        $fixed = null;
    } elseif ($set !== null) {
        $fixed = DateTimeImmutable::createFromInterface($set);
    }
    return $fixed ?? new DateTimeImmutable('now');
}

/** Test hook. $fn receives (string $channel 'sms'|'line', string $to, string $message). */
function lsc_waitlist_set_notifier(?callable $fn): void
{
    $GLOBALS['__lsc_waitlist_notifier'] = $fn;
}

function lsc_waitlist_notify(string $channel, string $to, string $message): void
{
    $fn = $GLOBALS['__lsc_waitlist_notifier'] ?? null;
    if ($fn) {
        $fn($channel, $to, $message);
        return;
    }
    if ($channel === 'line') {
        lsc_send_line_broadcast($message, LINE_BROADCAST_TOKEN);
        return;
    }
    if ($to === '') {
        lsc_log('Waitlist SMS skipped', 'No phone number. Message: ' . $message);
        return;
    }
    lsc_send_sms_notification($to, $message, SMSMKT_API_KEY, SMSMKT_SECRET_KEY, SMSMKT_SENDER);
}

/* ------------------------------------------------------------------ pricing & helpers */

function lsc_waitlist_slot_start(string $date, string $timeslot): ?DateTimeImmutable
{
    $hours = [
        '6-7am' => 6, '7-8am' => 7, '8-9am' => 8, '9-10am' => 9, '10-11am' => 10, '11am-12pm' => 11,
        '12-1pm' => 12, '1-2pm' => 13, '2-3pm' => 14, '3-4pm' => 15, '4-5pm' => 16, '5-6pm' => 17,
        '6-7pm' => 18, '7-8pm' => 19, '8-9pm' => 20, '9-10pm' => 21,
    ];
    if (!isset($hours[$timeslot])) {
        return null;
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', sprintf('%s %02d:00:00', $date, $hours[$timeslot]));
    return $dt ?: null;
}

function lsc_waitlist_slot_price(string $timeslot): int
{
    return in_array($timeslot, ['6-7pm', '7-8pm', '8-9pm', '9-10pm'], true) ? LSC_COURT_FEE_EVENING : LSC_COURT_FEE_DAY;
}

/** waitlist_note is a CSV written by the booking forms: name,email,phone,daily_member_type,coach,extra_players */
function lsc_waitlist_parse_note(?string $note): array
{
    $parts = array_map('trim', explode(',', (string) $note));
    return [
        'name'              => $parts[0] ?? '',
        'email'             => $parts[1] ?? '',
        'phone'             => $parts[2] ?? '',
        'daily_member_type' => $parts[3] ?? '',
        'coach'             => $parts[4] ?? '',
        'extra_players'     => max(0, (int) ($parts[5] ?? 0)),
    ];
}

function lsc_waitlist_is_guest_entry(array $w): bool
{
    return strtolower(trim((string) $w['member_type'])) === 'non-member';
}

/** Full price for the offer: member = court + extras; guest = court + daily fee + coach + extras. */
function lsc_waitlist_total_price(string $timeslot, ?string $note, bool $isGuest = false): int
{
    $n = lsc_waitlist_parse_note($note);
    $price = lsc_waitlist_slot_price($timeslot) + $n['extra_players'] * LSC_GUEST_FEE;
    if ($isGuest) {
        $price += LSC_DAILY_FEES[strtolower($n['daily_member_type'])] ?? LSC_DAILY_FEES['individual'];
        if (stripos($n['coach'], 'coach') !== false) {
            $price += LSC_COACH_FEE;
        }
    }
    return $price;
}

function lsc_waitlist_is_real_member_id($id): bool
{
    return $id !== null && !in_array((int) $id, LSC_SENTINEL_IDS, true);
}

function lsc_waitlist_readable_date(string $date): string
{
    $d = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    return $d ? $d->format('d F, Y') : $date;
}

/* ------------------------------------------------------------------ queries */

function lsc_waitlist_get(PDO $pdo, int $waitlistId, bool $forUpdate = false): ?array
{
    $st = $pdo->prepare('SELECT * FROM wait_list WHERE wait_list_id = ?' . ($forUpdate ? ' FOR UPDATE' : ''));
    $st->execute([$waitlistId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r === false ? null : $r;
}

function lsc_waitlist_court_is_free(PDO $pdo, int $court, string $date, string $timeslot): bool
{
    $st = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE court = ? AND date = ? AND timeslot = ? AND booking_status <> 'cancelled'");
    $st->execute([$court, $date, $timeslot]);
    return (int) $st->fetchColumn() === 0;
}

/**
 * Resolve the person behind a waitlist row.
 * Returns ['kind' => 'member'|'guest', 'id' => int, 'name' => string, 'phone' => string, 'member_type' => string] or null.
 */
function lsc_waitlist_resolve_person(PDO $pdo, array $w): ?array
{
    if (lsc_waitlist_is_guest_entry($w)) {
        if (!lsc_waitlist_is_real_member_id($w['non_member_id'])) {
            return null;
        }
        $st = $pdo->prepare('SELECT id, guest_name, member_phone, member_email FROM non_members WHERE id = ?');
        $st->execute([(int) $w['non_member_id']]);
        $g = $st->fetch(PDO::FETCH_ASSOC);
        if (!$g) {
            return null;
        }
        $note = lsc_waitlist_parse_note($w['waitlist_note']);
        return ['kind' => 'guest', 'id' => (int) $g['id'], 'name' => (string) $g['guest_name'],
                'phone' => (string) ($g['member_phone'] ?: $note['phone']), 'email' => (string) ($g['member_email'] ?: $note['email']), 'member_type' => ''];
    }
    if (!lsc_waitlist_is_real_member_id($w['member_id'])) {
        return null;
    }
    $st = $pdo->prepare('SELECT id, first_name, last_name, member_number, member_phone, member_type FROM members WHERE id = ?');
    $st->execute([(int) $w['member_id']]);
    $m = $st->fetch(PDO::FETCH_ASSOC);
    if (!$m) {
        return null;
    }
    return ['kind' => 'member', 'id' => (int) $m['id'], 'name' => trim($m['first_name'] . ' ' . $m['last_name']),
            'phone' => (string) $m['member_phone'], 'email' => '', 'member_type' => (string) $m['member_type'], 'member_number' => $m['member_number']];
}

/** Oldest still-waiting entry (member or guest) for the slot whose person can be resolved. */
function lsc_waitlist_next_waiting(PDO $pdo, string $date, string $timeslot): ?array
{
    $st = $pdo->prepare("SELECT * FROM wait_list WHERE date = ? AND timeslot = ? AND waitlist_status IS NULL ORDER BY created_at ASC, wait_list_id ASC FOR UPDATE");
    $st->execute([$date, $timeslot]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $w) {
        $person = lsc_waitlist_resolve_person($pdo, $w);
        if ($person !== null) {
            $w['person'] = $person;
            return $w;
        }
    }
    return null;
}

/** Pending offers, newest first, with the person resolved. For admin screens. */
function lsc_waitlist_pending_offers(PDO $pdo): array
{
    $st = $pdo->query("SELECT * FROM wait_list WHERE waitlist_status = 'pending' ORDER BY updated_at DESC");
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $w) {
        $w['person'] = lsc_waitlist_resolve_person($pdo, $w);
        $isGuest = lsc_waitlist_is_guest_entry($w);
        $w['is_guest'] = $isGuest;
        $w['price'] = lsc_waitlist_total_price($w['timeslot'], $w['waitlist_note'], $isGuest);
        $w['expires_at'] = (new DateTimeImmutable($w['updated_at']))->modify('+' . LSC_WAITLIST_OFFER_TTL . ' seconds')->format('Y-m-d H:i:s');
        $out[] = $w;
    }
    return $out;
}

function lsc_waitlist_pending_guest_count(PDO $pdo): int
{
    return (int) $pdo->query("SELECT COUNT(*) FROM wait_list WHERE waitlist_status = 'pending' AND LOWER(member_type) = 'non-member'")->fetchColumn();
}

/* ------------------------------------------------------------------ offer */

/**
 * A court has become free. Offer it to the next waiting entry.
 * Returns ['waitlist_id','kind','member_id'|'non_member_id','booking_id','phone','price'] or null when nobody was offered.
 */
function lsc_waitlist_offer_slot(PDO $pdo, int $court, string $date, string $timeslot): ?array
{
    $start = lsc_waitlist_slot_start($date, $timeslot);
    if ($start === null) {
        return null;
    }
    $now = lsc_waitlist_now();
    if ($start->getTimestamp() - $now->getTimestamp() <= LSC_WAITLIST_OFFER_MIN_LEAD) {
        return null; // too close to start (or already past)
    }

    $own = !$pdo->inTransaction();
    if ($own) {
        $pdo->beginTransaction();
    }
    try {
        if (!lsc_waitlist_court_is_free($pdo, $court, $date, $timeslot)) {
            if ($own) { $pdo->commit(); }
            return null;
        }
        $w = lsc_waitlist_next_waiting($pdo, $date, $timeslot);
        if ($w === null) {
            if ($own) { $pdo->commit(); }
            return null;
        }
        $p        = $w['person'];
        $isGuest  = $p['kind'] === 'guest';
        $note     = lsc_waitlist_parse_note($w['waitlist_note']);
        $price    = lsc_waitlist_total_price($timeslot, $w['waitlist_note'], $isGuest);
        $readable = lsc_waitlist_readable_date($date);

        $memberId    = $isGuest ? 90002 : $p['id'];
        $nonMemberId = $isGuest ? $p['id'] : 90002;
        $guestInfo   = $isGuest ? implode(',', [$p['name'], $p['email'], $p['phone']]) : '';
        $txTitle     = $isGuest ? 'Booking_' . $p['id'] : 'Booking_' . $p['id'];

        // Ledger rows exist from the start so the booking screens can show an amount,
        // but they are typed 'waitlist offer' until confirmed.
        $st = $pdo->prepare("INSERT INTO transactions (transaction_title, member_id, non_member_id, non_member_info, transaction_amount, transaction_type, payment_type, slip_url, transaction_note)
                             VALUES (?, ?, ?, ?, ?, 'waitlist offer', ?, '', 'Waitlist offer, awaiting confirmation')");
        $st->execute([$txTitle, $memberId, $nonMemberId, $guestInfo, $price, $isGuest ? 'cash' : 'credit']);
        $parentTx = (int) $pdo->lastInsertId();

        $st = $pdo->prepare("INSERT INTO transactions (transaction_title, assoc_transaction_id, member_id, non_member_id, non_member_info, transaction_amount, transaction_type, payment_type, slip_url, transaction_note)
                             VALUES (?, ?, ?, ?, ?, ?, 'waitlist offer', ?, '', 'Waitlist offer, awaiting confirmation')");
        $st->execute(["Booking for date $readable, court $court at $timeslot", $parentTx, $memberId, $isGuest ? $nonMemberId : 90001, $guestInfo, $price, $isGuest ? 'cash' : 'credit']);
        $childTx = (int) $pdo->lastInsertId();

        $st = $pdo->prepare("INSERT INTO bookings (court, date, timeslot, member_id, non_member_id, booking_status, booking_type, daily_member_type, payment, transaction_id, payment_remark, coach, coach_extra_player, booking_note, non_member_info)
                             VALUES (?, ?, ?, ?, ?, 'approved', ?, ?, 'cash', ?, 'Not paid yet', ?, ?, 'waitlist-reserved', ?)");
        $st->execute([$court, $date, $timeslot, $memberId, $nonMemberId, $isGuest ? 'non-member' : 'member booking',
                      $note['daily_member_type'] ?: ($isGuest ? 'Individual' : $p['member_type']), $childTx,
                      $isGuest ? ($note['coach'] ?: null) : null, $note['extra_players'], $guestInfo ?: null]);
        $bookingId = (int) $pdo->lastInsertId();

        $st = $pdo->prepare("UPDATE wait_list SET waitlist_status = 'pending', free_court = ?, waitlist_booking_id = ?, updated_at = ? WHERE wait_list_id = ?");
        $st->execute([$court, $bookingId, $now->format('Y-m-d H:i:s'), $w['wait_list_id']]);

        if ($own) { $pdo->commit(); }
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }

    if ($isGuest) {
        lsc_waitlist_notify('sms', $p['phone'],
            "Our court $court on $readable at $timeslot from your waitlist is available now and has been reserved for you. "
          . "Our staff will contact you within 2 hours to confirm and arrange payment ($price THB). You can also contact the reception.");
        lsc_waitlist_notify('line', '',
            "[Waitlist - Guest] ต้องการการยืนยันจากแอดมิน\nGuest: {$p['name']} ({$p['phone']})\nวันที่: $readable\nCourt: $court เวลา: $timeslot\nราคา: $price THB\nยืนยันที่ " . LSC_ADMIN_URL . "/admin-waitlist.php");
    } else {
        lsc_waitlist_notify('sms', $p['phone'],
            "Our court $court on $readable at $timeslot from your waitlist is available now. "
          . "Please login to your account within 2 hours to confirm the booking ($price THB will be deducted from your credit).");
    }
    lsc_log('Waitlist Offer', "Offered court $court, $date $timeslot to {$p['kind']} ID {$p['id']} ({$p['name']}; waitlist {$w['wait_list_id']}, booking $bookingId, $price THB)" . ($isGuest ? ' - admin confirmation required.' : '.'), 'System');

    return [
        'waitlist_id'   => (int) $w['wait_list_id'],
        'kind'          => $p['kind'],
        'member_id'     => $isGuest ? null : $p['id'],
        'non_member_id' => $isGuest ? $p['id'] : null,
        'name'          => $p['name'],
        'booking_id'    => $bookingId,
        'phone'         => $p['phone'],
        'price'         => $price,
    ];
}

/* ------------------------------------------------------------------ confirm */

function lsc_waitlist_actor_owns(array $actor, array $w): bool
{
    if (lsc_waitlist_is_guest_entry($w)) {
        return (int) ($actor['non_member_id'] ?? 0) === (int) $w['non_member_id'] && lsc_waitlist_is_real_member_id($w['non_member_id']);
    }
    return (int) ($actor['member_id'] ?? 0) === (int) $w['member_id'] && lsc_waitlist_is_real_member_id($w['member_id']);
}

/**
 * Accept a pending offer.
 * $actor = ['member_id' => int, 'non_member_id' => int, 'is_admin' => bool, 'payment' => 'cash'|'qr' (guest offers, admin only)].
 * Returns ['ok' => true, 'amount' => int, 'booking_id' => int, 'kind' => 'member'|'guest']
 *      or ['ok' => false, 'reason' => 'not_found'|'not_pending'|'forbidden'|'admin_only'|'expired'|'insufficient_credit', ...]
 */
function lsc_waitlist_confirm(PDO $pdo, int $waitlistId, array $actor): array
{
    $isAdmin = !empty($actor['is_admin']);

    $pdo->beginTransaction();
    try {
        $w = lsc_waitlist_get($pdo, $waitlistId, true);
        if ($w === null) {
            $pdo->rollBack();
            return ['ok' => false, 'reason' => 'not_found'];
        }
        if ($w['waitlist_status'] !== 'pending') {
            $pdo->rollBack();
            return ['ok' => false, 'reason' => 'not_pending', 'status' => $w['waitlist_status']];
        }
        $isGuest = lsc_waitlist_is_guest_entry($w);
        if ($isGuest && !$isAdmin) {
            $pdo->rollBack();
            return ['ok' => false, 'reason' => 'admin_only'];
        }
        if (!$isAdmin && !lsc_waitlist_actor_owns($actor, $w)) {
            $pdo->rollBack();
            return ['ok' => false, 'reason' => 'forbidden'];
        }

        $now   = lsc_waitlist_now();
        $start = lsc_waitlist_slot_start($w['date'], $w['timeslot']);
        $sent  = new DateTimeImmutable($w['updated_at']);
        $tooOld   = ($now->getTimestamp() - $sent->getTimestamp()) > LSC_WAITLIST_OFFER_TTL;
        $tooClose = $start === null || ($start->getTimestamp() - $now->getTimestamp()) <= LSC_WAITLIST_OFFER_MIN_LEAD;
        if ($tooOld || $tooClose) {
            $pdo->commit();
            lsc_waitlist_decline($pdo, $waitlistId, ['is_admin' => true], 'expired');
            return ['ok' => false, 'reason' => 'expired'];
        }

        $price     = lsc_waitlist_total_price($w['timeslot'], $w['waitlist_note'], $isGuest);
        $bookingId = (int) $w['waitlist_booking_id'];
        $memberId  = (int) $w['member_id'];

        if ($isGuest) {
            $payment = in_array($actor['payment'] ?? '', ['cash', 'qr'], true) ? $actor['payment'] : 'cash';
            $txType  = 'booking (non member)';
            $noteTxt = 'Waitlist booking confirmed by admin (' . $payment . ')';
        } else {
            $st = $pdo->prepare('SELECT credit FROM members WHERE id = ? FOR UPDATE');
            $st->execute([$memberId]);
            $credit = $st->fetchColumn();
            if ($credit === false || (float) $credit < $price) {
                $pdo->rollBack();
                return ['ok' => false, 'reason' => 'insufficient_credit', 'required' => $price, 'credit' => (float) $credit];
            }
            $payment = 'credit';
            $txType  = 'booking (member)';
            $noteTxt = 'Waitlist booking confirmed' . ($isAdmin ? ' by admin' : ' by member');
        }

        $st = $pdo->prepare("UPDATE bookings SET payment = ?, payment_remark = NULL, booking_note = 'waitlist booking paid', booking_status = 'approved' WHERE id = ?");
        $st->execute([$payment, $bookingId]);

        $st = $pdo->prepare('SELECT t.transaction_id, t.assoc_transaction_id FROM bookings b JOIN transactions t ON t.transaction_id = b.transaction_id WHERE b.id = ?');
        $st->execute([$bookingId]);
        if ($tx = $st->fetch(PDO::FETCH_ASSOC)) {
            $ids = array_filter([(int) $tx['transaction_id'], (int) $tx['assoc_transaction_id']]);
            $in  = implode(',', array_fill(0, count($ids), '?'));
            $st = $pdo->prepare("UPDATE transactions SET transaction_type = ?, transaction_amount = ?, payment_type = ?, transaction_note = ? WHERE transaction_id IN ($in)");
            $st->execute(array_merge([$txType, $price, $payment, $noteTxt], array_values($ids)));
        }

        if (!$isGuest) {
            $st = $pdo->prepare('UPDATE members SET credit = credit - ? WHERE id = ?');
            $st->execute([$price, $memberId]);
        }

        $st = $pdo->prepare("UPDATE wait_list SET waitlist_status = 'confirmed' WHERE wait_list_id = ?");
        $st->execute([$waitlistId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }

    $who = $isGuest ? "Guest ID {$w['non_member_id']} confirmed by admin, $price THB by $payment" : "Member ID $memberId charged $price THB";
    lsc_log('Waitlist Confirm', "Waitlist $waitlistId confirmed" . ($isAdmin ? ' by admin' : '') . ". $who for booking $bookingId ({$w['date']} {$w['timeslot']} court {$w['free_court']}).");
    return ['ok' => true, 'amount' => $price, 'booking_id' => $bookingId, 'kind' => $isGuest ? 'guest' : 'member',
            'member_id' => $isGuest ? null : $memberId, 'non_member_id' => $isGuest ? (int) $w['non_member_id'] : null, 'payment' => $payment];
}

/* ------------------------------------------------------------------ decline / expire */

/**
 * Decline or expire a pending offer, release the placeholder booking and offer the court to the next entry.
 * $reason: 'declined' (explicit) or 'expired' (timeout). Returns ['ok'=>bool, 'next'=>?array].
 */
function lsc_waitlist_decline(PDO $pdo, int $waitlistId, array $actor, string $reason = 'declined'): array
{
    $isAdmin = !empty($actor['is_admin']);
    $status  = $reason === 'expired' ? 'expired' : 'declined';

    $pdo->beginTransaction();
    try {
        $w = lsc_waitlist_get($pdo, $waitlistId, true);
        if ($w === null) {
            $pdo->rollBack();
            return ['ok' => false, 'reason' => 'not_found', 'next' => null];
        }
        if ($w['waitlist_status'] !== 'pending') {
            $pdo->rollBack();
            return ['ok' => false, 'reason' => 'not_pending', 'status' => $w['waitlist_status'], 'next' => null];
        }
        if (!$isAdmin && $reason !== 'expired' && !lsc_waitlist_actor_owns($actor, $w)) {
            $pdo->rollBack();
            return ['ok' => false, 'reason' => 'forbidden', 'next' => null];
        }

        $bookingId = (int) $w['waitlist_booking_id'];
        $note = $status === 'expired' ? 'waitlist-expired' : ($isAdmin ? 'waitlist-declined-by-admin' : 'waitlist-declined');

        $st = $pdo->prepare("UPDATE bookings SET booking_status = 'cancelled', booking_note = ? WHERE id = ? AND booking_note IN ('waitlist-reserved')");
        $st->execute([$note, $bookingId]);

        $st = $pdo->prepare("SELECT t.transaction_id, t.assoc_transaction_id FROM bookings b JOIN transactions t ON t.transaction_id = b.transaction_id WHERE b.id = ? AND t.transaction_type = 'waitlist offer'");
        $st->execute([$bookingId]);
        if ($tx = $st->fetch(PDO::FETCH_ASSOC)) {
            $ids = array_filter([(int) $tx['transaction_id'], (int) $tx['assoc_transaction_id']]);
            $in  = implode(',', array_fill(0, count($ids), '?'));
            $st = $pdo->prepare("UPDATE transactions SET transaction_type = 'cancelled', transaction_amount = 0, transaction_note = ? WHERE transaction_id IN ($in)");
            $st->execute(array_merge(['Waitlist offer ' . $status . ', no charge'], array_values($ids)));
        }

        $st = $pdo->prepare('UPDATE wait_list SET waitlist_status = ? WHERE wait_list_id = ?');
        $st->execute([$status, $waitlistId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }

    lsc_log('Waitlist ' . ucfirst($status), "Waitlist $waitlistId $status" . ($isAdmin ? ' by admin' : '') . ". Released booking $bookingId ({$w['date']} {$w['timeslot']} court {$w['free_court']}).");

    $next = null;
    if (!empty($w['free_court'])) {
        $next = lsc_waitlist_offer_slot($pdo, (int) $w['free_court'], $w['date'], $w['timeslot']);
    }
    return ['ok' => true, 'status' => $status, 'booking_id' => $bookingId, 'next' => $next];
}

/**
 * Expire every pending offer that is older than the TTL or whose slot is now too close.
 * Each expiry offers the court to the next waiting entry. Returns the number expired.
 */
function lsc_waitlist_expire_stale(PDO $pdo): int
{
    $now = lsc_waitlist_now();
    $st = $pdo->prepare("SELECT wait_list_id, date, timeslot, updated_at FROM wait_list WHERE waitlist_status = 'pending'");
    $st->execute();
    $count = 0;
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $w) {
        $start = lsc_waitlist_slot_start($w['date'], $w['timeslot']);
        $sent  = new DateTimeImmutable($w['updated_at']);
        $tooOld   = ($now->getTimestamp() - $sent->getTimestamp()) > LSC_WAITLIST_OFFER_TTL;
        $tooClose = $start === null || ($start->getTimestamp() - $now->getTimestamp()) <= LSC_WAITLIST_OFFER_MIN_LEAD;
        if ($tooOld || $tooClose) {
            $r = lsc_waitlist_decline($pdo, (int) $w['wait_list_id'], ['is_admin' => true], 'expired');
            if ($r['ok']) {
                $count++;
            }
        }
    }
    return $count;
}

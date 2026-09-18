<?php
/**
 * Waitlist service.
 *
 * Single implementation of the waitlist life cycle, used by the member page,
 * the admin page, the cancellation handlers and the expiry script.
 *
 *   waiting  (waitlist_status NULL)
 *      | lsc_waitlist_offer_slot()      a court in that slot became free
 *   pending  (offer sent, placeholder booking holds the court)
 *      | lsc_waitlist_confirm()         member or admin accepts -> credit deducted
 *   confirmed
 *      | lsc_waitlist_decline()         member/admin declines, or offer times out
 *   declined / expired                  placeholder cancelled, next waiting member offered
 *
 * Rules:
 *  - An offer is only made when the slot starts more than LSC_WAITLIST_OFFER_MIN_LEAD seconds from now.
 *  - An offer must be accepted within LSC_WAITLIST_OFFER_TTL seconds of being sent.
 *  - Price = court fee for the timeslot + LSC_GUEST_FEE per extra player recorded on the waitlist entry.
 *  - Credit is always deducted from the waitlisted member, never from the actor.
 *  - Only member-type entries are offered; guest entries have no credit and are skipped.
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

const LSC_WAITLIST_OFFER_TTL      = 2 * 3600; // seconds a member has to accept
const LSC_WAITLIST_OFFER_MIN_LEAD = 2 * 3600; // slot must start at least this far in the future
const LSC_COURT_FEE_DAY           = 160;
const LSC_COURT_FEE_EVENING       = 280;
const LSC_GUEST_FEE               = 200;
const LSC_SENTINEL_IDS            = [0, 90001, 90002];

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

function lsc_waitlist_set_notifier(?callable $fn): void
{
    $GLOBALS['__lsc_waitlist_notifier'] = $fn;
}

function lsc_waitlist_notify(string $phone, string $message): void
{
    $fn = $GLOBALS['__lsc_waitlist_notifier'] ?? null;
    if ($fn) {
        $fn($phone, $message);
        return;
    }
    if ($phone === '') {
        lsc_log('Waitlist SMS skipped', 'No phone number. Message: ' . $message);
        return;
    }
    lsc_send_sms_notification($phone, $message, SMSMKT_API_KEY, SMSMKT_SECRET_KEY, SMSMKT_SENDER);
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
        'daily_member_type' => $parts[3] ?? '',
        'coach'             => $parts[4] ?? '',
        'extra_players'     => max(0, (int) ($parts[5] ?? 0)),
    ];
}

function lsc_waitlist_total_price(string $timeslot, ?string $note): int
{
    $n = lsc_waitlist_parse_note($note);
    return lsc_waitlist_slot_price($timeslot) + $n['extra_players'] * LSC_GUEST_FEE;
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

/** Oldest still-waiting member entry for the slot, joined with the member row. */
function lsc_waitlist_next_waiting(PDO $pdo, string $date, string $timeslot): ?array
{
    $st = $pdo->prepare("
        SELECT w.*, m.id AS m_id, m.first_name, m.last_name, m.member_number, m.member_phone, m.credit, m.member_type AS m_member_type
        FROM wait_list w
        JOIN members m ON m.id = w.member_id
        WHERE w.date = ? AND w.timeslot = ?
          AND w.waitlist_status IS NULL
          AND w.member_type = 'member booking'
        ORDER BY w.created_at ASC, w.wait_list_id ASC
        LIMIT 1
        FOR UPDATE
    ");
    $st->execute([$date, $timeslot]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r === false ? null : $r;
}

/* ------------------------------------------------------------------ offer */

/**
 * A court has become free. Offer it to the next waiting member.
 * Returns ['waitlist_id','member_id','booking_id','phone','price'] or null when nobody was offered.
 * Safe to call from inside or outside a transaction.
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

        $memberId = (int) $w['m_id'];
        $price    = lsc_waitlist_total_price($timeslot, $w['waitlist_note']);
        $note     = lsc_waitlist_parse_note($w['waitlist_note']);
        $readable = lsc_waitlist_readable_date($date);

        // Ledger rows exist from the start so the booking screens can show an amount,
        // but they are typed 'waitlist offer' until the member confirms.
        $st = $pdo->prepare("INSERT INTO transactions (transaction_title, member_id, non_member_id, non_member_info, transaction_amount, transaction_type, payment_type, slip_url, transaction_note)
                             VALUES (?, ?, 90002, '', ?, 'waitlist offer', 'credit', '', ?)");
        $st->execute(['Booking_' . $memberId, $memberId, $price, 'Waitlist offer, awaiting confirmation']);
        $parentTx = (int) $pdo->lastInsertId();

        $st = $pdo->prepare("INSERT INTO transactions (transaction_title, assoc_transaction_id, member_id, non_member_id, non_member_info, transaction_amount, transaction_type, payment_type, slip_url, transaction_note)
                             VALUES (?, ?, ?, 90001, '', ?, 'waitlist offer', 'credit', '', ?)");
        $st->execute(["Booking for date $readable, court $court at $timeslot", $parentTx, $memberId, $price, 'Waitlist offer, awaiting confirmation']);
        $childTx = (int) $pdo->lastInsertId();

        $st = $pdo->prepare("INSERT INTO bookings (court, date, timeslot, member_id, non_member_id, booking_status, booking_type, daily_member_type, payment, transaction_id, payment_remark, coach_extra_player, booking_note)
                             VALUES (?, ?, ?, ?, 90002, 'approved', 'member booking', ?, 'cash', ?, 'Not paid yet', ?, 'waitlist-reserved')");
        $st->execute([$court, $date, $timeslot, $memberId, $note['daily_member_type'] ?: $w['m_member_type'], $childTx, $note['extra_players']]);
        $bookingId = (int) $pdo->lastInsertId();

        $st = $pdo->prepare("UPDATE wait_list SET waitlist_status = 'pending', free_court = ?, waitlist_booking_id = ?, updated_at = ? WHERE wait_list_id = ?");
        $st->execute([$court, $bookingId, $now->format('Y-m-d H:i:s'), $w['wait_list_id']]);

        if ($own) { $pdo->commit(); }
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }

    $msg = "Our court $court on $readable at $timeslot from your waitlist is available now. "
         . "Please login to your account within 2 hours to confirm the booking ($price THB will be deducted from your credit).";
    lsc_waitlist_notify((string) $w['member_phone'], $msg);
    lsc_log('Waitlist Offer', "Offered court $court, $date $timeslot to member ID $memberId (waitlist {$w['wait_list_id']}, booking $bookingId, $price THB).", 'System');

    return [
        'waitlist_id' => (int) $w['wait_list_id'],
        'member_id'   => $memberId,
        'booking_id'  => $bookingId,
        'phone'       => (string) $w['member_phone'],
        'price'       => $price,
    ];
}

/* ------------------------------------------------------------------ confirm */

/**
 * Accept a pending offer. $actor = ['member_id' => int, 'is_admin' => bool].
 * Returns ['ok' => true, 'amount' => int, 'booking_id' => int]
 *      or ['ok' => false, 'reason' => 'not_found'|'not_pending'|'forbidden'|'expired'|'insufficient_credit', ...]
 */
function lsc_waitlist_confirm(PDO $pdo, int $waitlistId, array $actor): array
{
    $isAdmin = !empty($actor['is_admin']);
    $actorId = (int) ($actor['member_id'] ?? 0);

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
        $memberId = (int) $w['member_id'];
        if (!$isAdmin && $actorId !== $memberId) {
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
            lsc_waitlist_decline($pdo, $waitlistId, $actor, 'expired');
            return ['ok' => false, 'reason' => 'expired'];
        }

        $price = lsc_waitlist_total_price($w['timeslot'], $w['waitlist_note']);

        $st = $pdo->prepare('SELECT credit FROM members WHERE id = ? FOR UPDATE');
        $st->execute([$memberId]);
        $credit = $st->fetchColumn();
        if ($credit === false || (float) $credit < $price) {
            $pdo->rollBack();
            return ['ok' => false, 'reason' => 'insufficient_credit', 'required' => $price, 'credit' => (float) $credit];
        }

        $bookingId = (int) $w['waitlist_booking_id'];
        $st = $pdo->prepare("UPDATE bookings SET payment = 'credit', payment_remark = NULL, booking_note = 'waitlist booking paid', booking_status = 'approved' WHERE id = ?");
        $st->execute([$bookingId]);

        // Promote the offer ledger rows to real booking rows.
        $st = $pdo->prepare('SELECT t.transaction_id, t.assoc_transaction_id FROM bookings b JOIN transactions t ON t.transaction_id = b.transaction_id WHERE b.id = ?');
        $st->execute([$bookingId]);
        $tx = $st->fetch(PDO::FETCH_ASSOC);
        if ($tx) {
            $ids = array_filter([(int) $tx['transaction_id'], (int) $tx['assoc_transaction_id']]);
            $in  = implode(',', array_fill(0, count($ids), '?'));
            $st = $pdo->prepare("UPDATE transactions SET transaction_type = 'booking (member)', transaction_amount = ?, transaction_note = ? WHERE transaction_id IN ($in)");
            $st->execute(array_merge([$price, 'Waitlist booking confirmed' . ($isAdmin ? ' by admin' : ' by member')], array_values($ids)));
        }

        $st = $pdo->prepare('UPDATE members SET credit = credit - ? WHERE id = ?');
        $st->execute([$price, $memberId]);

        $st = $pdo->prepare("UPDATE wait_list SET waitlist_status = 'confirmed' WHERE wait_list_id = ?");
        $st->execute([$waitlistId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }

    lsc_log('Waitlist Confirm', "Waitlist $waitlistId confirmed" . ($isAdmin ? " by admin" : "") . ". Member ID $memberId charged $price THB for booking $bookingId ({$w['date']} {$w['timeslot']} court {$w['free_court']}).");
    return ['ok' => true, 'amount' => $price, 'booking_id' => $bookingId, 'member_id' => $memberId];
}

/* ------------------------------------------------------------------ decline / expire */

/**
 * Decline or expire a pending offer, release the placeholder booking and offer the court to the next member.
 * $reason: 'declined' (explicit) or 'expired' (timeout). Returns ['ok'=>bool, 'next'=>?array].
 */
function lsc_waitlist_decline(PDO $pdo, int $waitlistId, array $actor, string $reason = 'declined'): array
{
    $isAdmin = !empty($actor['is_admin']);
    $actorId = (int) ($actor['member_id'] ?? 0);
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
        if (!$isAdmin && $reason !== 'expired' && $actorId !== (int) $w['member_id']) {
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
 * Each expiry offers the court to the next waiting member. Returns the number expired.
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

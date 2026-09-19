<?php
/**
 * Member credit: the only place a balance is allowed to change.
 *
 * Every movement writes two things together, inside the caller's transaction:
 *   1. members.credit  is adjusted by $delta
 *   2. the matching transactions row records credit_delta plus who did it
 *
 * `credit_delta` is the signed effect on the balance. Rows that are recorded
 * for information but never moved money keep 0, which is what makes a member's
 * statement add up: SUM(credit_delta) for a member should equal members.credit.
 * Informational rows include the per-court child rows of a booking and the
 * separate "Guest transaction" row, whose amount is already inside the parent.
 *
 * Actor is taken from the session, never from a form field.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';   // lsc_log()

const LSC_ACTOR_MEMBER = 'member';
const LSC_ACTOR_ADMIN  = 'admin';
const LSC_ACTOR_SYSTEM = 'system';

/**
 * Who is performing the current action.
 * Falls back to system for CLI scripts (cron, migrations) and unauthenticated contexts.
 */
function lsc_actor(): array
{
    if (PHP_SAPI === 'cli') {
        return ['type' => LSC_ACTOR_SYSTEM, 'id' => null, 'name' => 'System'];
    }
    $me = lsc_current_user();
    if ($me === null) {
        return ['type' => LSC_ACTOR_SYSTEM, 'id' => null, 'name' => 'System'];
    }
    return [
        'type' => $me['is_admin'] ? LSC_ACTOR_ADMIN : LSC_ACTOR_MEMBER,
        'id'   => $me['id'],
        'name' => $me['name'] !== '' ? $me['name'] : ('User ' . $me['id']),
    ];
}

/** Force the actor for the rest of the request. Used by automated flows (waitlist expiry). */
function lsc_actor_force_system(?bool $on = null): bool
{
    static $forced = false;
    if ($on !== null) {
        $forced = $on;
    }
    return $forced;
}

function lsc_actor_effective(): array
{
    return lsc_actor_force_system() ? ['type' => LSC_ACTOR_SYSTEM, 'id' => null, 'name' => 'System'] : lsc_actor();
}

/** Stamp a transaction row with the acting user, without touching any balance. */
function lsc_credit_stamp_actor(PDO $pdo, int $transactionId): void
{
    if ($transactionId <= 0) {
        return;
    }
    $a = lsc_actor_effective();
    $st = $pdo->prepare('UPDATE transactions SET actor_type = ?, actor_id = ?, actor_name = ? WHERE transaction_id = ?');
    $st->execute([$a['type'], $a['id'], $a['name'], $transactionId]);
}

/**
 * Move a member's balance and record the movement on $transactionId.
 *
 * $delta is signed: positive adds credit, negative deducts it.
 * Call inside an open database transaction; this does not commit.
 * Returns the member's new balance.
 */
function lsc_credit_move(PDO $pdo, int $memberId, int $delta, int $transactionId): float
{
    if ($memberId <= 0) {
        throw new InvalidArgumentException('lsc_credit_move: member id required');
    }

    if ($delta !== 0) {
        $st = $pdo->prepare('UPDATE members SET credit = credit + ? WHERE id = ?');
        $st->execute([$delta, $memberId]);
    }

    if ($transactionId > 0) {
        $a = lsc_actor_effective();
        $st = $pdo->prepare('UPDATE transactions SET credit_delta = ?, actor_type = ?, actor_id = ?, actor_name = ? WHERE transaction_id = ?');
        $st->execute([$delta, $a['type'], $a['id'], $a['name'], $transactionId]);
    }

    $st = $pdo->prepare('SELECT credit FROM members WHERE id = ?');
    $st->execute([$memberId]);
    return (float) $st->fetchColumn();
}

/**
 * Admin adjustment with a written reason: creates the ledger row and moves the balance.
 * $delta signed. Returns ['transaction_id' => int, 'balance' => float].
 */
function lsc_credit_adjust(PDO $pdo, int $memberId, int $delta, string $reason): array
{
    $reason = trim($reason);
    if ($reason === '') {
        throw new InvalidArgumentException('A reason is required for a credit adjustment.');
    }
    if ($delta === 0) {
        throw new InvalidArgumentException('The adjustment amount cannot be zero.');
    }

    $own = !$pdo->inTransaction();
    if ($own) {
        $pdo->beginTransaction();
    }
    try {
        $st = $pdo->prepare('SELECT credit FROM members WHERE id = ? FOR UPDATE');
        $st->execute([$memberId]);
        if ($st->fetchColumn() === false) {
            throw new RuntimeException('Member not found.');
        }

        $title = $delta > 0 ? 'Admin credit add' : 'Admin credit deduction';
        $type  = $delta > 0 ? 'Admin credit add' : 'Admin credit deduction';

        $st = $pdo->prepare("INSERT INTO transactions (transaction_title, member_id, non_member_id, non_member_info, transaction_amount, transaction_type, payment_type, slip_url, transaction_note)
                             VALUES (?, ?, 90002, 'N/A', ?, ?, 'credit', '', ?)");
        $st->execute([$title, $memberId, abs($delta), $type, $reason]);
        $txId = (int) $pdo->lastInsertId();

        $balance = lsc_credit_move($pdo, $memberId, $delta, $txId);

        if ($own) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    $a = lsc_actor_effective();
    lsc_log('Credit Adjustment', sprintf('Member ID %d: %s%d THB by %s. Reason: %s. New balance: %s THB.',
        $memberId, $delta > 0 ? '+' : '', $delta, $a['name'], $reason, number_format($balance, 2)));

    return ['transaction_id' => $txId, 'balance' => $balance];
}

/** Sum of recorded movements for a member. Should equal members.credit. */
function lsc_credit_ledger_total(PDO $pdo, int $memberId): float
{
    $st = $pdo->prepare('SELECT COALESCE(SUM(credit_delta), 0) FROM transactions WHERE member_id = ?');
    $st->execute([$memberId]);
    return (float) $st->fetchColumn();
}

/** Human label for the actor recorded on a row. */
function lsc_actor_label(?string $actorType, ?string $actorName): string
{
    switch ($actorType) {
        case LSC_ACTOR_ADMIN:  return 'Admin' . ($actorName ? ' (' . $actorName . ')' : '');
        case LSC_ACTOR_MEMBER: return 'Member' . ($actorName ? ' (' . $actorName . ')' : '');
        case LSC_ACTOR_SYSTEM: return 'System';
        default:               return 'Unknown';
    }
}

/* ---------------------------------------------------------------- statement helpers */

/**
  * Signed effect on the balance, as recorded when the movement happened.
  * Rows written for information only carry 0 and never appear in the statement.
  */
function lsc_credit_transaction_effect(array $transaction): int
{
    return (int) ($transaction['credit_delta'] ?? 0);
}

function lsc_credit_transaction_where(): string
{
    return "member_id = ? AND credit_delta <> 0";
}

/** What the member's credit was spent on or received for, in plain words. */
function lsc_credit_description(array $row, array $children): string
{
    $type  = (string) $row['transaction_type'];
    $title = trim((string) $row['transaction_title']);
    $note  = trim((string) ($row['transaction_note'] ?? ''));

    if (in_array($type, ['booking (member)', 'booking'], true)) {
        $parts = [];
        foreach ($children as $c) {
            $t = trim((string) $c['transaction_title']);
            // "Booking for date 20 September, 2026, court 3 at 5-6pm" -> "Court 3, 5-6pm on 20 September, 2026"
            if (preg_match('/date (.+?), court (\d+) at (.+)$/i', $t, $m)) {
                $parts[] = 'Court ' . $m[2] . ', ' . $m[3] . ' on ' . $m[1];
            } elseif ($t !== '') {
                $parts[] = $t;
            }
        }
        if ($parts) {
            return 'Court booking: ' . implode('; ', $parts);
        }
        return 'Court booking';
    }

    if ($type === 'Admin credit add' || $type === 'Admin credit deduction') {
        return ($type === 'Admin credit add' ? 'Credit added by admin' : 'Credit deducted by admin')
             . ($note !== '' ? ' - ' . $note : '');
    }
    if ($type === 'Credit refill - Approved') {
        return 'Credit refill approved';
    }
    if ($type === 'Opening balance') {
        return 'Opening balance';
    }
    if (str_starts_with($type, 'cancelled')) {
        return ($title !== '' ? $title : 'Cancelled booking') . ($note !== '' ? ' (' . $note . ')' : '');
    }
    return $title !== '' ? $title : $type;
}

function lsc_member_transaction_badge($transaction_type)
{
    switch ($transaction_type) {
        case 'cancelled booking':
        case 'cancelled':
            return '<span class="badge text-bg-danger">Cancelled booking</span>';
        case 'cancelled-rain-half':
            return '<span class="badge text-bg-danger">Cancelled booking<br>(Rain/Pollution)<br>- Half refunded</span>';
        case 'cancelled-rain':
            return '<span class="badge text-bg-danger">Cancelled booking<br>(Rain/Pollution)<br>- Fully refunded</span>';
        case 'booking (member)':
            return '<span class="badge text-bg-success">Member booking</span>';
        case 'booking (non member)':
            return '<span class="badge text-bg-success">Non Member booking</span>';
        case 'Credit refill':
            return '<span class="badge text-bg-primary">Credit refill</span>';
        case 'Credit refill - Approved':
            return '<span class="badge text-bg-primary">Credit refill (approved)</span>';
        case 'Admin credit add':
            return '<span class="badge text-bg-info">Admin credit add</span>';
        case 'Membership renewal':
            return '<span class="badge text-bg-warning">Membership renewal</span>';
        default:
            return '<span class="badge text-bg-secondary">' . htmlspecialchars($transaction_type) . '</span>';
    }
}

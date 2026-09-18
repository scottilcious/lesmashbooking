<?php
/**
 * Booking write helpers shared by the book-*.php handlers.
 *
 * lsc_booking_transaction($pdo, $date, fn) runs fn inside ONE database
 * transaction while holding a per-date named lock, so two requests for the
 * same day are serialised: the second one waits, then sees the first one's
 * rows when it re-checks the slot. Everything fn writes (ledger rows,
 * bookings, credit) is committed together or not at all.
 *
 * Inside fn, throw LscBookingConflict with a user-facing message to abort
 * cleanly (slot taken, not enough credit); the handler renders it.
 */

declare(strict_types=1);

class LscBookingConflict extends RuntimeException
{
    public string $backUrl;

    public function __construct(string $message, string $backUrl = 'index.php')
    {
        parent::__construct($message);
        $this->backUrl = $backUrl;
    }
}

const LSC_BOOKING_LOCK_TIMEOUT = 5; // seconds to wait for another request on the same date

function lsc_booking_lock_name(string $date): string
{
    return 'lsc_booking_' . preg_replace('/[^0-9-]/', '', $date);
}

/** Take the per-date lock and open a transaction. Pair with lsc_booking_commit() or lsc_booking_abort(). */
function lsc_booking_begin(PDO $pdo, string $date): void
{
    $st = $pdo->prepare('SELECT GET_LOCK(?, ?)');
    $st->execute([lsc_booking_lock_name($date), LSC_BOOKING_LOCK_TIMEOUT]);
    if ((int) $st->fetchColumn() !== 1) {
        throw new LscBookingConflict('The booking system is busy right now. Please try again in a moment.');
    }
    $pdo->beginTransaction();
}

function lsc_booking_commit(PDO $pdo, string $date): void
{
    try {
        $pdo->commit();
    } finally {
        $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([lsc_booking_lock_name($date)]);
    }
}

function lsc_booking_abort(PDO $pdo, string $date): void
{
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([lsc_booking_lock_name($date)]);
}

/**
 * Closure form of begin/commit/abort.
 * @param callable(PDO):mixed $fn
 * @return mixed whatever $fn returns
 * @throws LscBookingConflict|Throwable
 */
function lsc_booking_transaction(PDO $pdo, string $date, callable $fn)
{
    lsc_booking_begin($pdo, $date);
    try {
        $result = $fn($pdo);
    } catch (Throwable $e) {
        lsc_booking_abort($pdo, $date);
        throw $e;
    }
    lsc_booking_commit($pdo, $date);
    return $result;
}

/** True when any non-cancelled booking already occupies the court at that date/time. */
function lsc_booking_slot_taken(PDO $pdo, $court, string $date, string $timeslot): bool
{
    $st = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE court = ? AND date = ? AND timeslot = ? AND booking_status <> 'cancelled'");
    $st->execute([$court, $date, $timeslot]);
    return (int) $st->fetchColumn() > 0;
}

/**
 * Re-check every requested court/time pair. Waitlist rows ('waitlist' as court) are skipped.
 * @throws LscBookingConflict on the first taken slot
 */
function lsc_booking_assert_slots_free(PDO $pdo, array $courts, array $times, string $date, string $backUrl = 'index.php'): void
{
    foreach ($courts as $i => $court) {
        $court = trim((string) $court);
        $time  = trim((string) ($times[$i] ?? ''));
        if ($court === '' || $court === 'waitlist' || $time === '') {
            continue;
        }
        if (lsc_booking_slot_taken($pdo, $court, $date, $time)) {
            throw new LscBookingConflict("Looks like another member has just booked court $court at $time. Please select another court/time.", $backUrl);
        }
    }
}

/**
 * Lock the member row and make sure the balance covers the amount.
 * Call inside lsc_booking_transaction so the deduction that follows cannot race another booking.
 * @throws LscBookingConflict
 */
function lsc_booking_assert_credit(PDO $pdo, $memberId, float $amount, string $backUrl = 'add-credit.php'): float
{
    $st = $pdo->prepare('SELECT credit FROM members WHERE id = ? FOR UPDATE');
    $st->execute([$memberId]);
    $credit = $st->fetchColumn();
    if ($credit === false) {
        throw new LscBookingConflict('Member account not found.', $backUrl);
    }
    if ((float) $credit < $amount) {
        throw new LscBookingConflict('Not enough credit for this booking (' . number_format($amount) . ' THB needed, ' . number_format((float) $credit) . ' THB available). Please refill credit before booking.', $backUrl);
    }
    return (float) $credit;
}

/** Standard error fragment for the AJAX booking flow. */
function lsc_booking_render_conflict(LscBookingConflict $e): void
{
    http_response_code(409);
    ?>
    <div class="error-message text-center text-danger">
        <span class="fs-2">
            <i class="ri-error-warning-fill"></i>
        </span>
        <br>
        <p><?= nl2br(htmlspecialchars($e->getMessage())) ?></p>
        <hr>
        <a href="<?= htmlspecialchars($e->backUrl) ?>" class="btn btn-primary">Back</a>
    </div>
    <?php
}

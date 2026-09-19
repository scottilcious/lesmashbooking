<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/booking-service.php';

test('booking transaction commits all writes together', function () {
    $m = t_member(['credit' => 500]);
    $r = lsc_booking_transaction(t_pdo(), '2026-10-07', function (PDO $pdo) use ($m) {
        $pdo->prepare("INSERT INTO bookings (court, date, timeslot, member_id, non_member_id, booking_status, transaction_id) VALUES (1, '2026-10-07', '7-8am', ?, 90002, 'approved', 0)")->execute([$m]);
        $pdo->prepare('UPDATE members SET credit = credit - 160 WHERE id = ?')->execute([$m]);
        return 'done';
    });
    assert_same('done', $r);
    assert_eq(1, count(t_rows('bookings')));
    assert_eq(340, t_credit($m));
});

test('booking transaction rolls back everything when the callback throws', function () {
    $m = t_member(['credit' => 500]);
    try {
        lsc_booking_transaction(t_pdo(), '2026-10-07', function (PDO $pdo) use ($m) {
            $pdo->prepare("INSERT INTO transactions (transaction_title, member_id, transaction_amount, transaction_type) VALUES ('x', ?, 160, 'booking (member)')")->execute([$m]);
            $pdo->prepare('UPDATE members SET credit = credit - 160 WHERE id = ?')->execute([$m]);
            throw new LscBookingConflict('slot gone');
        });
        throw new TestFailure('expected exception');
    } catch (LscBookingConflict $e) {
        assert_same('slot gone', $e->getMessage());
    }
    assert_eq(0, count(t_rows('transactions', "transaction_type <> 'Opening balance'")), 'ledger row rolled back');
    assert_eq(500, t_credit($m), 'credit rolled back');
    assert_same(false, t_pdo()->inTransaction());
});

test('slot check sees non-cancelled bookings only and skips waitlist entries', function () {
    $m = t_member();
    t_booking(['court' => 3, 'date' => '2026-10-07', 'timeslot' => '7-8am', 'member_id' => $m, 'booking_status' => 'cancelled']);
    lsc_booking_assert_slots_free(t_pdo(), ['3', 'waitlist'], ['7-8am', '8-9am'], '2026-10-07'); // no throw
    t_booking(['court' => 3, 'date' => '2026-10-07', 'timeslot' => '7-8am', 'member_id' => $m]);
    try {
        lsc_booking_assert_slots_free(t_pdo(), ['1', '3'], ['7-8am', '7-8am'], '2026-10-07');
        throw new TestFailure('expected conflict');
    } catch (LscBookingConflict $e) {
        assert_true(str_contains($e->getMessage(), 'court 3 at 7-8am'));
    }
});

test('credit check locks the row and refuses an insufficient balance', function () {
    $m = t_member(['credit' => 100]);
    lsc_booking_transaction(t_pdo(), '2026-10-07', function (PDO $pdo) use ($m) {
        assert_eq(100, lsc_booking_assert_credit($pdo, $m, 100.0));
        try {
            lsc_booking_assert_credit($pdo, $m, 160.0);
            throw new TestFailure('expected conflict');
        } catch (LscBookingConflict $e) {
            assert_true(str_contains($e->getMessage(), '160 THB needed'));
        }
    });
});

test('two connections booking the same date are serialised by the lock', function () {
    // Connection B holds the date lock in a transaction; connection A must wait and then see B's booking.
    $m1 = t_member(['credit' => 500]);
    $m2 = t_member(['credit' => 500]);
    $pdoB = lsc_create_pdo(DB_TEST_DATABASE);
    $pdoB->prepare('SELECT GET_LOCK(?, 5)')->execute([lsc_booking_lock_name('2026-10-07')]);
    $pdoB->prepare("INSERT INTO bookings (court, date, timeslot, member_id, non_member_id, booking_status, transaction_id) VALUES (4, '2026-10-07', '9-10am', ?, 90002, 'approved', 0)")->execute([$m2]);

    // Release B's lock shortly after A starts waiting (A blocks for up to LSC_BOOKING_LOCK_TIMEOUT seconds).
    $start = microtime(true);
    $released = false;
    $pdoA = t_pdo();
    // We cannot run B concurrently in one PHP process, so shorten the wait: release right away and assert A observed B's row.
    $pdoB->prepare('SELECT RELEASE_LOCK(?)')->execute([lsc_booking_lock_name('2026-10-07')]);
    try {
        lsc_booking_transaction($pdoA, '2026-10-07', function (PDO $pdo) {
            lsc_booking_assert_slots_free($pdo, ['4'], ['9-10am'], '2026-10-07');
        });
        throw new TestFailure('A should have seen B\'s booking');
    } catch (LscBookingConflict $e) {
        assert_true(str_contains($e->getMessage(), 'court 4'));
    }

    // And while B holds the lock, A cannot get it within the timeout.
    $pdoB->prepare('SELECT GET_LOCK(?, 5)')->execute([lsc_booking_lock_name('2026-10-08')]);
    $t0 = microtime(true);
    try {
        lsc_booking_transaction($pdoA, '2026-10-08', fn() => null);
        throw new TestFailure('A should not obtain a lock held by B');
    } catch (LscBookingConflict $e) {
        assert_true(str_contains($e->getMessage(), 'busy'), $e->getMessage());
        assert_true(microtime(true) - $t0 >= LSC_BOOKING_LOCK_TIMEOUT - 0.5, 'A waited for the timeout');
    }
    $pdoB->prepare('SELECT RELEASE_LOCK(?)')->execute([lsc_booking_lock_name('2026-10-08')]);
});

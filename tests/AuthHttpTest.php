<?php
/**
 * Ownership and role checks, exercised through the real pages over HTTP
 * against the test database.
 */
declare(strict_types=1);

require_once __DIR__ . '/http.php';
require_once __DIR__ . '/../includes/password.php';

function t_auth_fixture(): array
{
    // Bookings 3 days ahead so cancellations are inside the refund window and the advance window.
    $date = date('Y-m-d', strtotime('+3 days'));
    $a = t_member(['first_name' => 'Alice', 'member_number' => 5001, 'member_password' => 'pwA', 'credit' => 1000, 'member_phone' => '0810000001']);
    $b = t_member(['first_name' => 'Bob',   'member_number' => 5002, 'member_password' => 'pwB', 'credit' => 1000, 'member_phone' => '0810000002']);
    $admin = t_member(['first_name' => 'Root', 'member_number' => 5003, 'member_password' => 'pwAdm', 'member_type' => 'admin', 'credit' => 0]);
    t_pdo()->exec("INSERT INTO system_settings (setting_key, setting_value) VALUES ('allow_midnight_booking', '1')");
    // Bob's booking, paid by credit (160)
    t_pdo()->prepare("INSERT INTO transactions (transaction_title, member_id, non_member_id, transaction_amount, transaction_type, payment_type) VALUES ('Booking_b', ?, 90001, 160, 'booking (member)', 'credit')")->execute([$b]);
    $tx = (int) t_pdo()->lastInsertId();
    $bk = t_booking(['court' => 1, 'date' => $date, 'timeslot' => '9-10am', 'member_id' => $b, 'transaction_id' => $tx, 'payment' => 'credit']);
    return compact('date', 'a', 'b', 'admin', 'bk');
}

test('http: not logged in cannot cancel a booking', function () {
    $f = t_auth_fixture();
    $r = t_http_post('cancel_booking.php', ['booking_id' => $f['bk']]);
    assert_eq(401, $r['status']);
    assert_eq('approved', t_row('bookings', 'id = ?', [$f['bk']])['booking_status']);
});

test('http: a member cannot cancel another member\'s booking, whatever the form says', function () {
    $f = t_auth_fixture();
    $jar = t_http_login('5001', 'pwA');
    $r = t_http_post('cancel_booking.php', ['booking_id' => $f['bk'], 'bookingType' => 'member booking', 'userId' => $f['b']], $jar);
    assert_eq(403, $r['status']);
    assert_eq('approved', t_row('bookings', 'id = ?', [$f['bk']])['booking_status'], 'Bob still has his court');
    assert_eq(1000, t_credit($f['a']), 'Alice got no refund');
    assert_eq(1000, t_credit($f['b']));
    assert_eq(0, count(t_rows('transactions', "transaction_type = 'cancelled'")), 'no cancellation ledger row');
});

test('http: owner can cancel, is refunded once, and cannot be refunded twice', function () {
    $f = t_auth_fixture();
    $jar = t_http_login('5002', 'pwB');
    $r = t_http_post('cancel_booking.php', ['booking_id' => $f['bk']], $jar);
    assert_eq(200, $r['status']);
    assert_true(str_contains($r['body'], 'cancelled successfully'), 'success fragment');
    assert_eq('cancelled', t_row('bookings', 'id = ?', [$f['bk']])['booking_status']);
    assert_eq(1160, t_credit($f['b']), 'refunded 160');
    $r2 = t_http_post('cancel_booking.php', ['booking_id' => $f['bk']], $jar);
    assert_eq(403, $r2['status'], 'second cancel rejected');
    assert_eq(1160, t_credit($f['b']), 'not refunded again');
});

test('http: a member gets 403 on admin handlers; admin gets through', function () {
    $f = t_auth_fixture();
    $jar = t_http_login('5001', 'pwA');
    $r = t_http_post('admin-save-member.php', ['member_id' => $f['b'], 'member_number' => '5002', 'first_name' => 'Hacked', 'last_name' => '', 'member_type' => 'individual',
        'member_status' => 'active', 'member_phone' => '', 'member_email' => '', 'member_since' => '2025-01-01', 'member_length' => '1 year',
        'member_expiration' => '2030-01-01', 'last_renewed' => '2025-01-01', 'credit' => 999999, 'member_note' => '', 'member_password' => ''], $jar);
    assert_eq(403, $r['status']);
    $bob = t_row('members', 'id = ?', [$f['b']]);
    assert_eq('Bob', $bob['first_name']);
    assert_eq(1000, (float) $bob['credit']);
    foreach (['admin-cancel-booking.php', 'admin-delete-transaction.php', 'admin-approve-credit.php', 'book-admin-member.php', 'admin-search-member.php'] as $h) {
        assert_eq(403, t_http_post($h, ['x' => 1], $jar)['status'], "$h blocked for member");
    }
    foreach (['admin-members.php', 'admin-transactions.php', 'admin-view-member.php?member_id=' . $f['b']] as $p) {
        $r = t_http_get($p, $jar);
        assert_eq(302, $r['status'], "$p redirects a member");
    }
    $adm = t_http_login('5003', 'pwAdm');
    assert_eq(200, t_http_get('admin-members.php', $adm)['status'], 'admin can open members page');
});

test('http: update_phone only changes the caller\'s own phone', function () {
    $f = t_auth_fixture();
    $jar = t_http_login('5001', 'pwA');
    $r = t_http_post('booking_functions/update_phone.php', ['user_id' => $f['b'], 'member_phone' => '0899999999'], $jar);
    assert_eq(200, $r['status']);
    assert_eq('0899999999', t_row('members', 'id = ?', [$f['a']])['member_phone'], 'Alice changed');
    assert_eq('0810000002', t_row('members', 'id = ?', [$f['b']])['member_phone'], 'Bob untouched');
});

test('http: booking with a foreign member_id in the form books for the session member', function () {
    $f = t_auth_fixture();
    $jar = t_http_login('5001', 'pwA');
    $r = t_http_post('book-member.php', [
        'booking_type' => 'member booking', 'date' => $f['date'], 'member_id' => $f['b'], 'daily_member_type' => 'individual',
        'all_selected_courts' => '3', 'all_selected_times' => '9-10am', 'transaction_amount' => 160, 'payment_type' => 'credit', 'extra_player' => 0,
    ], $jar);
    assert_eq(200, $r['status']);
    assert_true(str_contains($r['body'], 'successfully'), 'booked: ' . substr(strip_tags($r['body']), 0, 200));
    $bk = t_row('bookings', 'court = 3 AND date = ? AND timeslot = ?', [$f['date'], '9-10am']);
    assert_eq($f['a'], $bk['member_id'], 'booked for Alice, not Bob');
    assert_eq(840, t_credit($f['a']), 'Alice paid');
    assert_eq(1000, t_credit($f['b']), 'Bob did not pay');
});

test('http: change-password cannot target another member', function () {
    $f = t_auth_fixture();
    $jar = t_http_login('5001', 'pwA');
    $r = t_http_post('change-password.php', ['pass_member_id' => $f['b'], 'password' => 'hacked', 'confirm_password' => 'hacked'], $jar);
    assert_eq(302, $r['status'], 'sent back to login (no pending password change in session)');
    assert_true(lsc_password_verify('pwB', t_row('members', 'id = ?', [$f['b']])['member_password']), 'Bob\'s password unchanged');
    assert_true(lsc_password_verify('pwA', t_row('members', 'id = ?', [$f['a']])['member_password']), 'Alice\'s password unchanged');
});

test('http: first login with the default password forces a change for that account only', function () {
    t_auth_fixture();
    $c = t_member(['member_number' => 5004, 'member_password' => 'lesmashclubmember']);
    $jar = t_http_cookie_jar();
    $r = t_http_post('login.php', ['login_type' => 'member', 'member_number' => '5004', 'password' => 'lesmashclubmember'], $jar);
    assert_eq(302, $r['status']);
    assert_true(str_contains((string) $r['location'], 'change-password.php'));
    $r = t_http_post('change-password.php', ['pass_member_id' => 1, 'password' => 'brandnew', 'confirm_password' => 'brandnew'], $jar);
    assert_eq(302, $r['status']);
    assert_true(lsc_password_verify('brandnew', t_row('members', 'id = ?', [$c])['member_password']));
    t_http_login('5004', 'brandnew');
});

test('http: guest handlers reject members and vice versa', function () {
    $f = t_auth_fixture();
    $jar = t_http_login('5001', 'pwA');
    assert_eq(403, t_http_post('book-non-member.php', ['date' => $f['date']], $jar)['status'], 'member cannot use the guest booking handler');
    $g = t_http_login_guest('Guest One', 'g1@example.com', '0870000001');
    assert_eq(403, t_http_post('book-member.php', ['date' => $f['date']], $g)['status'], 'guest cannot use the member booking handler');
    assert_eq(403, t_http_post('refill-credit.php', ['credit_amount' => 100], $g)['status'], 'guest cannot refill credit');
    assert_eq(403, t_http_post('admin-cancel-booking.php', ['booking_id' => $f['bk']], $g)['status']);
});

<?php
/**
 * Booking handlers over HTTP: taken slots are refused and nothing is written.
 */
declare(strict_types=1);

require_once __DIR__ . '/http.php';

function t_bk_fixture(): array
{
    $date = date('Y-m-d', strtotime('+2 days'));
    $a = t_member(['first_name' => 'Ann', 'member_number' => 7001, 'member_password' => 'pwA', 'credit' => 1000]);
    $b = t_member(['first_name' => 'Ben', 'member_number' => 7002, 'member_password' => 'pwB', 'credit' => 1000]);
    $admin = t_member(['first_name' => 'Root', 'member_number' => 7003, 'member_password' => 'pwAdm', 'member_type' => 'admin', 'member_number' => 7003]);
    t_pdo()->exec("INSERT INTO system_settings (setting_key, setting_value) VALUES ('allow_midnight_booking', '1')");
    return compact('date', 'a', 'b', 'admin');
}

function t_member_booking_payload(array $f, string $courts, string $times, int $amount): array
{
    return ['booking_type' => 'member booking', 'date' => $f['date'], 'daily_member_type' => 'individual',
        'all_selected_courts' => $courts, 'all_selected_times' => $times, 'transaction_amount' => $amount, 'payment_type' => 'credit', 'extra_player' => 0];
}

test('http: member booking succeeds and writes ledger, booking and credit together', function () {
    $f = t_bk_fixture();
    $jar = t_http_login('7001', 'pwA');
    $r = t_http_post('book-member.php', t_member_booking_payload($f, '1,2', '9-10am,10-11am', 320), $jar);
    assert_eq(200, $r['status'], strip_tags($r['body']));
    assert_eq(2, count(t_rows('bookings', 'member_id = ? AND booking_status = ?', [$f['a'], 'approved'])));
    assert_eq(3, count(t_rows('transactions', 'member_id = ?', [$f['a']])), 'parent + 2 children');
    assert_eq(680, t_credit($f['a']));
});

test('http: member booking a slot someone holds is refused with nothing written', function () {
    $f = t_bk_fixture();
    t_booking(['court' => 1, 'date' => $f['date'], 'timeslot' => '9-10am', 'member_id' => $f['b']]);
    $jar = t_http_login('7001', 'pwA');
    $r = t_http_post('book-member.php', t_member_booking_payload($f, '1,2', '9-10am,9-10am', 320), $jar);
    assert_true(str_contains($r['body'], 'just booked'), 'conflict message: ' . substr(strip_tags($r['body']), 0, 160));
    assert_eq(0, count(t_rows('bookings', 'member_id = ?', [$f['a']])), 'no booking for Ann, not even court 2');
    assert_eq(0, count(t_rows('transactions', 'member_id = ?', [$f['a']])), 'no ledger rows');
    assert_eq(1000, t_credit($f['a']), 'no charge');
});

test('http: academy block cannot overwrite an existing member booking', function () {
    $f = t_bk_fixture();
    t_booking(['court' => 3, 'date' => $f['date'], 'timeslot' => '4-5pm', 'member_id' => $f['b']]);
    $adm = t_http_login('7003', 'pwAdm');
    $r = t_http_post('book-academy.php', ['booking_type' => 'junior_academy', 'date' => $f['date'], 'member_id' => $f['admin'],
        'all_selected_courts' => '3,4', 'all_selected_times' => '4-5pm,4-5pm'], $adm);
    assert_eq(409, $r['status']);
    assert_eq(0, count(t_rows('bookings', "booking_type = 'junior_academy'")), 'neither court blocked');
    $r = t_http_post('book-academy.php', ['booking_type' => 'junior_academy', 'date' => $f['date'], 'member_id' => $f['admin'],
        'all_selected_courts' => '4,5', 'all_selected_times' => '4-5pm,4-5pm'], $adm);
    assert_eq(200, $r['status'], strip_tags($r['body']));
    assert_eq(2, count(t_rows('bookings', "booking_type = 'junior_academy'")));
});

test('http: admin booking for a member on a taken court is refused; free court succeeds and charges the member', function () {
    $f = t_bk_fixture();
    t_booking(['court' => 6, 'date' => $f['date'], 'timeslot' => '7-8am', 'member_id' => $f['b']]);
    $adm = t_http_login('7003', 'pwAdm');
    $base = ['booking_type' => 'member booking', 'date' => $f['date'], 'member_id' => $f['a'], 'daily_member_type' => 'individual',
        'transaction_amount' => 160, 'payment_type' => 'credit', 'extra_player' => 0, 'not_paid_yet' => ''];
    $r = t_http_post('book-admin-member.php', $base + ['all_selected_courts' => '6', 'all_selected_times' => '7-8am'], $adm);
    assert_true(str_contains($r['body'], 'just booked'), substr(strip_tags($r['body']), 0, 160));
    assert_eq(1000, t_credit($f['a']));
    $r = t_http_post('book-admin-member.php', $base + ['all_selected_courts' => '7', 'all_selected_times' => '7-8am'], $adm);
    assert_eq(200, $r['status'], strip_tags($r['body']));
    assert_eq(840, t_credit($f['a']));
    assert_eq(1, count(t_rows('bookings', 'member_id = ? AND court = 7', [$f['a']])));
});

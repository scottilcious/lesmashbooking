<?php
/**
 * Admin credit approval and booking edits, through the real handlers over HTTP.
 */
declare(strict_types=1);

require_once __DIR__ . '/http.php';

function t_money_fixture(): array
{
    $date = date('Y-m-d', strtotime('+3 days'));
    $m = t_member(['first_name' => 'Mia', 'member_number' => 6001, 'member_password' => 'pwM', 'credit' => 200]);
    $admin = t_member(['first_name' => 'Root', 'member_number' => 6003, 'member_password' => 'pwAdm', 'member_type' => 'admin', 'credit' => 0]);
    t_pdo()->prepare("INSERT INTO transactions (transaction_title, member_id, transaction_amount, transaction_type, payment_type, slip_url) VALUES ('Member credit refill', ?, 1000, 'Credit refill', 'qr', 'slip.jpg')")->execute([$m]);
    $refill = (int) t_pdo()->lastInsertId();
    return compact('date', 'm', 'admin', 'refill');
}

test('http: approving a refill ADDS the amount to the current balance, even if the member booked meanwhile', function () {
    $f = t_money_fixture();
    $adm = t_http_login('6003', 'pwAdm');
    // Admin opened the approval page when Mia had 200; then Mia spends 160 before the admin clicks approve.
    t_pdo()->prepare("UPDATE members SET credit = credit - 160 WHERE id = ?")->execute([$f['m']]);
    $r = t_http_post('admin-approve-credit.php', ['add_credit_transaction_id' => $f['refill'], 'total_credit' => 1200], $adm);
    assert_eq(200, $r['status'], strip_tags($r['body']));
    assert_eq(1040, t_credit($f['m']), '40 + 1000, not the stale 1200');
    $tx = t_row('transactions', 'transaction_id = ?', [$f['refill']]);
    assert_eq('Credit refill - Approved', $tx['transaction_type']);
    assert_eq(1000, $tx['transaction_amount']);
});

test('http: a refill cannot be approved twice', function () {
    $f = t_money_fixture();
    $adm = t_http_login('6003', 'pwAdm');
    assert_eq(200, t_http_post('admin-approve-credit.php', ['add_credit_transaction_id' => $f['refill']], $adm)['status']);
    $r = t_http_post('admin-approve-credit.php', ['add_credit_transaction_id' => $f['refill']], $adm);
    assert_eq(409, $r['status']);
    assert_true(str_contains($r['body'], 'already been approved'));
    assert_eq(1200, t_credit($f['m']), 'added once');
});

test('http: admin can adjust the approved amount; zero and unknown ids are refused', function () {
    $f = t_money_fixture();
    $adm = t_http_login('6003', 'pwAdm');
    assert_eq(409, t_http_post('admin-approve-credit.php', ['add_credit_transaction_id' => $f['refill'], 'approved_amount' => 0], $adm)['status']);
    assert_eq(409, t_http_post('admin-approve-credit.php', ['add_credit_transaction_id' => 999999], $adm)['status']);
    assert_eq(200, t_credit($f['m']));
    $r = t_http_post('admin-approve-credit.php', ['add_credit_transaction_id' => $f['refill'], 'approved_amount' => 950], $adm);
    assert_eq(200, $r['status']);
    assert_eq(1150, t_credit($f['m']));
    $tx = t_row('transactions', 'transaction_id = ?', [$f['refill']]);
    assert_eq(950, $tx['transaction_amount'], 'ledger matches what was approved');
    assert_true(str_contains((string) $tx['transaction_note'], 'adjusted from 1000'));
});

function t_save_payload(array $bk, array $over = []): array
{
    return array_merge([
        'booking_id' => $bk['id'], 'court' => $bk['court'], 'date' => $bk['date'], 'timeslot' => $bk['timeslot'],
        'booking_status' => $bk['booking_status'], 'payment' => $bk['payment'], 'member_id' => $bk['member_id'],
        'coach' => '', 'coach_name' => 'Coach K', 'coach_extra_player' => 0, 'booking_note' => 'edited', 'credit' => 160, 'credit_refund' => 'false',
    ], $over);
}

test('http: save booking cannot cancel silently and never touches credit', function () {
    $f = t_money_fixture();
    $adm = t_http_login('6003', 'pwAdm');
    $id = t_booking(['court' => 2, 'date' => $f['date'], 'timeslot' => '9-10am', 'member_id' => $f['m']]);
    $bk = t_row('bookings', 'id = ?', [$id]);
    $r = t_http_post('admin-save-booking.php', t_save_payload($bk, ['booking_status' => 'cancelled']), $adm);
    assert_eq(409, $r['status']);
    assert_eq('approved', t_row('bookings', 'id = ?', [$id])['booking_status']);
    assert_eq(200, t_credit($f['m']), 'no phantom refund');
    // ordinary edit still works
    $r = t_http_post('admin-save-booking.php', t_save_payload($bk), $adm);
    assert_eq(200, $r['status'], strip_tags($r['body']));
    assert_eq('Coach K', t_row('bookings', 'id = ?', [$id])['coach_name']);
    assert_eq(200, t_credit($f['m']));
});

test('http: moving a booking onto an occupied court is refused, onto a free one allowed', function () {
    $f = t_money_fixture();
    $adm = t_http_login('6003', 'pwAdm');
    $other = t_member();
    t_booking(['court' => 5, 'date' => $f['date'], 'timeslot' => '9-10am', 'member_id' => $other]);
    $id = t_booking(['court' => 2, 'date' => $f['date'], 'timeslot' => '9-10am', 'member_id' => $f['m']]);
    $bk = t_row('bookings', 'id = ?', [$id]);
    $r = t_http_post('admin-save-booking.php', t_save_payload($bk, ['court' => 5]), $adm);
    assert_eq(409, $r['status']);
    assert_true(str_contains($r['body'], 'already booked'));
    assert_eq(2, t_row('bookings', 'id = ?', [$id])['court'], 'not moved');
    $r = t_http_post('admin-save-booking.php', t_save_payload($bk, ['court' => 6]), $adm);
    assert_eq(200, $r['status']);
    assert_eq(6, t_row('bookings', 'id = ?', [$id])['court'], 'moved to the free court');
});

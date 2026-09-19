<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/credit.php';
require_once __DIR__ . '/http.php';

function t_tx(int $memberId, int $amount, string $type = 'Admin credit add', string $title = 'x'): int
{
    t_pdo()->prepare("INSERT INTO transactions (transaction_title, member_id, non_member_id, transaction_amount, transaction_type, payment_type) VALUES (?, ?, 90002, ?, ?, 'credit')")
        ->execute([$title, $memberId, $amount, $type]);
    return (int) t_pdo()->lastInsertId();
}

test('credit_move changes the balance and records the movement on the row', function () {
    $m = t_member(['credit' => 500]);
    $tx = t_tx($m, 300);
    $balance = lsc_credit_move(t_pdo(), $m, 300, $tx);
    assert_eq(800, $balance);
    assert_eq(800, t_credit($m));
    $row = t_row('transactions', 'transaction_id = ?', [$tx]);
    assert_eq(300, $row['credit_delta']);
    assert_eq('system', $row['actor_type'], 'CLI context records system');
});

test('credit_move handles deductions and leaves informational rows at zero', function () {
    $m = t_member(['credit' => 500]);
    $charge = t_tx($m, 160, 'booking (member)', 'Booking_x');
    $info   = t_tx($m, 200, 'booking (member)', 'Guest transaction');
    lsc_credit_move(t_pdo(), $m, -160, $charge);
    assert_eq(340, t_credit($m));
    assert_eq(-160, t_row('transactions', 'transaction_id = ?', [$charge])['credit_delta']);
    assert_eq(0, t_row('transactions', 'transaction_id = ?', [$info])['credit_delta'], 'never stamped, never counted');
    assert_eq(340, lsc_credit_ledger_total(t_pdo(), $m), 'ledger equals balance');
});

test('adjust requires an amount and a reason', function () {
    $m = t_member(['credit' => 100]);
    foreach ([[0, 'some reason'], [50, ''], [50, '   ']] as [$delta, $reason]) {
        try {
            lsc_credit_adjust(t_pdo(), $m, $delta, $reason);
            throw new TestFailure("expected rejection for delta=$delta reason='$reason'");
        } catch (InvalidArgumentException $e) {
            // expected
        }
    }
    assert_eq(100, t_credit($m), 'nothing changed');
    assert_eq(0, count(t_rows('transactions', "transaction_type <> 'Opening balance'")));
});

test('adjust records the reason, moves the balance and keeps the ledger equal', function () {
    $m = t_member(['credit' => 100]);
    $up = lsc_credit_adjust(t_pdo(), $m, 250, 'Cash top up at reception');
    assert_eq(350, $up['balance']);
    $row = t_row('transactions', 'transaction_id = ?', [$up['transaction_id']]);
    assert_eq('Admin credit add', $row['transaction_type']);
    assert_eq(250, $row['transaction_amount'], 'amount is stored positive');
    assert_eq(250, $row['credit_delta']);
    assert_eq('Cash top up at reception', $row['transaction_note']);

    $down = lsc_credit_adjust(t_pdo(), $m, -100, 'Correction for double charge');
    assert_eq(250, $down['balance']);
    $row2 = t_row('transactions', 'transaction_id = ?', [$down['transaction_id']]);
    assert_eq('Admin credit deduction', $row2['transaction_type']);
    assert_eq(100, $row2['transaction_amount']);
    assert_eq(-100, $row2['credit_delta']);

    assert_eq(250, lsc_credit_ledger_total(t_pdo(), $m), 'ledger reconciles with the balance');
});

test('a failed adjustment leaves no trace', function () {
    $m = t_member(['credit' => 100]);
    try {
        lsc_credit_adjust(t_pdo(), 999999, 50, 'nobody');
        throw new TestFailure('expected failure for a missing member');
    } catch (RuntimeException $e) {
        assert_true(str_contains($e->getMessage(), 'not found'));
    }
    assert_eq(0, count(t_rows('transactions', "transaction_type <> 'Opening balance'")), 'ledger row rolled back');
    assert_eq(100, t_credit($m));
});

test('actor labels', function () {
    assert_eq('Admin (Root)', lsc_actor_label('admin', 'Root'));
    assert_eq('Member (Ann)', lsc_actor_label('member', 'Ann'));
    assert_eq('System', lsc_actor_label('system', null));
    assert_eq('Unknown', lsc_actor_label(null, null), 'history we cannot attribute');
});

/* ---------------------------------------------------------------- over HTTP */

function t_credit_fixture(): array
{
    $m = t_member(['first_name' => 'Mia', 'member_number' => 8001, 'member_password' => 'pwM', 'credit' => 500]);
    $admin = t_member(['first_name' => 'Root', 'member_number' => 8003, 'member_password' => 'pwAdm', 'member_type' => 'admin']);
    return compact('m', 'admin');
}

test('http: only an admin can adjust credit, and the acting admin is recorded', function () {
    $f = t_credit_fixture();
    assert_eq(401, t_http_post('admin-adjust-credit.php', ['member_id' => $f['m'], 'amount' => 100, 'reason' => 'x'])['status']);
    $member = t_http_login('8001', 'pwM');
    assert_eq(403, t_http_post('admin-adjust-credit.php', ['member_id' => $f['m'], 'amount' => 100, 'reason' => 'x'], $member)['status']);
    assert_eq(500, t_credit($f['m']));

    $adm = t_http_login('8003', 'pwAdm');
    $r = t_http_post('admin-adjust-credit.php', ['member_id' => $f['m'], 'direction' => 'add', 'amount' => 250, 'reason' => 'Cash at reception'], $adm);
    assert_eq(200, $r['status'], strip_tags($r['body']));
    assert_eq(750, t_credit($f['m']));
    $row = t_row('transactions', 'member_id = ? AND transaction_type = ?', [$f['m'], 'Admin credit add']);
    assert_eq('admin', $row['actor_type']);
    assert_eq($f['admin'], (int) $row['actor_id'], 'the acting admin, not the member');
    assert_eq('Root Member', $row['actor_name']);
});

test('http: adjustment without a reason is refused', function () {
    $f = t_credit_fixture();
    $adm = t_http_login('8003', 'pwAdm');
    $r = t_http_post('admin-adjust-credit.php', ['member_id' => $f['m'], 'amount' => 100, 'reason' => ''], $adm);
    assert_eq(409, $r['status']);
    assert_true(str_contains($r['body'], 'reason is required'));
    assert_eq(500, t_credit($f['m']));
    assert_eq(0, count(t_rows('transactions', "transaction_type <> 'Opening balance'")));
});

test('http: deduction direction works and cannot be zero or negative', function () {
    $f = t_credit_fixture();
    $adm = t_http_login('8003', 'pwAdm');
    assert_eq(409, t_http_post('admin-adjust-credit.php', ['member_id' => $f['m'], 'amount' => 0, 'reason' => 'r'], $adm)['status']);
    assert_eq(409, t_http_post('admin-adjust-credit.php', ['member_id' => $f['m'], 'amount' => -50, 'reason' => 'r'], $adm)['status']);
    $r = t_http_post('admin-adjust-credit.php', ['member_id' => $f['m'], 'direction' => 'deduct', 'amount' => 200, 'reason' => 'Refund to card'], $adm);
    assert_eq(200, $r['status']);
    assert_eq(300, t_credit($f['m']));
    assert_eq(-200, t_row('transactions', 'member_id = ? AND transaction_type = ?', [$f['m'], 'Admin credit deduction'])['credit_delta']);
});

test('http: saving the member form can no longer change the balance', function () {
    $f = t_credit_fixture();
    $adm = t_http_login('8003', 'pwAdm');
    $r = t_http_post('admin-save-member.php', [
        'member_id' => $f['m'], 'member_number' => '8001', 'first_name' => 'Mia', 'last_name' => 'Renamed',
        'member_type' => 'individual', 'member_status' => 'active', 'member_phone' => '', 'member_email' => '',
        'member_since' => '2025-01-01', 'member_length' => '1 year', 'member_expiration' => '2030-01-01',
        'last_renewed' => '2025-01-01', 'credit' => 999999, 'member_note' => '', 'member_password' => '',
    ], $adm);
    assert_eq(200, $r['status'], strip_tags($r['body']));
    $row = t_row('members', 'id = ?', [$f['m']]);
    assert_eq('Renamed', $row['last_name'], 'other fields still save');
    assert_eq(500, (float) $row['credit'], 'credit ignored even though it was posted');
    assert_eq(0, count(t_rows('transactions', "transaction_type <> 'Opening balance'")), 'no silent ledger row either');
});

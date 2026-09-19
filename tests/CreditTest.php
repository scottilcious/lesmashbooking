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

test('http: a member booking records the member as the actor', function () {
    $f = t_credit_fixture();
    t_pdo()->exec("INSERT INTO system_settings (setting_key, setting_value) VALUES ('allow_midnight_booking', '1')");
    $date = date('Y-m-d', strtotime('+2 days'));
    $jar = t_http_login('8001', 'pwM');
    $r = t_http_post('book-member.php', ['booking_type' => 'member booking', 'date' => $date, 'daily_member_type' => 'individual',
        'all_selected_courts' => '1', 'all_selected_times' => '9-10am', 'transaction_amount' => 160, 'payment_type' => 'credit', 'extra_player' => 0], $jar);
    assert_eq(200, $r['status'], strip_tags($r['body']));

    $moved = t_rows('transactions', "member_id = ? AND credit_delta <> 0 AND transaction_type <> 'Opening balance'", [$f['m']]);
    assert_eq(1, count($moved), 'exactly one row moved the balance');
    assert_eq(-160, (int) $moved[0]['credit_delta']);
    assert_eq('member', $moved[0]['actor_type']);
    assert_eq($f['m'], (int) $moved[0]['actor_id']);
    assert_eq(340, t_credit($f['m']));
    assert_eq(340, lsc_credit_ledger_total(t_pdo(), $f['m']), 'statement reconciles');
});

test('http: an admin booking on a member\'s behalf records the admin, and the member pays', function () {
    $f = t_credit_fixture();
    t_pdo()->exec("INSERT INTO system_settings (setting_key, setting_value) VALUES ('allow_midnight_booking', '1')");
    $date = date('Y-m-d', strtotime('+2 days'));
    $adm = t_http_login('8003', 'pwAdm');
    $r = t_http_post('book-admin-member.php', ['booking_type' => 'member booking', 'date' => $date, 'member_id' => $f['m'],
        'daily_member_type' => 'individual', 'all_selected_courts' => '2', 'all_selected_times' => '9-10am',
        'transaction_amount' => 160, 'payment_type' => 'credit', 'extra_player' => 0, 'not_paid_yet' => ''], $adm);
    assert_eq(200, $r['status'], strip_tags($r['body']));

    $moved = t_rows('transactions', "member_id = ? AND credit_delta <> 0 AND transaction_type <> 'Opening balance'", [$f['m']]);
    assert_eq(1, count($moved));
    assert_eq('admin', $moved[0]['actor_type'], 'the admin did it');
    assert_eq($f['admin'], (int) $moved[0]['actor_id']);
    assert_eq(340, t_credit($f['m']), 'but the member paid');
    assert_eq(340, lsc_credit_ledger_total(t_pdo(), $f['m']));
});

test('http: a member cancelling within the refund window records the member and reconciles', function () {
    $f = t_credit_fixture();
    $date = date('Y-m-d', strtotime('+3 days'));
    t_pdo()->prepare("INSERT INTO transactions (transaction_title, member_id, non_member_id, transaction_amount, transaction_type, payment_type, credit_delta, actor_type) VALUES ('Booking_x', ?, 90001, 160, 'booking (member)', 'credit', -160, 'member')")->execute([$f['m']]);
    $tx = (int) t_pdo()->lastInsertId();
    t_pdo()->prepare("UPDATE members SET credit = credit - 160 WHERE id = ?")->execute([$f['m']]);
    $bk = t_booking(['court' => 1, 'date' => $date, 'timeslot' => '9-10am', 'member_id' => $f['m'], 'transaction_id' => $tx, 'payment' => 'credit']);

    $jar = t_http_login('8001', 'pwM');
    $r = t_http_post('cancel_booking.php', ['booking_id' => $bk], $jar);
    assert_eq(200, $r['status']);
    assert_eq(500, t_credit($f['m']), 'refunded back to the starting balance');

    $refund = t_row('transactions', "member_id = ? AND credit_delta > 0 AND transaction_type = 'cancelled'", [$f['m']]);
    assert_eq(160, (int) $refund['credit_delta']);
    assert_eq('member', $refund['actor_type']);
    assert_eq(500, lsc_credit_ledger_total(t_pdo(), $f['m']), 'statement reconciles after a refund');
});

test('http: the credit activity page is admin only and shows movements with the actor', function () {
    $f = t_credit_fixture();
    assert_eq(302, t_http_get('admin-member-credit.php?member_id=' . $f['m'])['status'], 'not logged in');
    $member = t_http_login('8001', 'pwM');
    assert_eq(302, t_http_get('admin-member-credit.php?member_id=' . $f['m'], $member)['status'], 'members redirected');

    $adm = t_http_login('8003', 'pwAdm');
    lsc_credit_adjust(t_pdo(), $f['m'], 250, 'Cash top up at reception');
    $body = t_http_get('admin-member-credit.php?member_id=' . $f['m'], $adm)['body'];
    assert_true(str_contains($body, 'Credit added by admin - Cash top up at reception'), 'reason is shown');
    assert_true(str_contains($body, 'Balance after'), 'running balance column');
    assert_true(str_contains($body, 'add up to the current balance'), 'reconciliation confirmed');
    assert_true(str_contains($body, 'Opening balance'), 'the opening balance is part of the story');
});

test('http: the activity page warns when the ledger does not add up', function () {
    $f = t_credit_fixture();
    // Simulate a balance changed outside the ledger, which is what the old member form did.
    t_pdo()->prepare('UPDATE members SET credit = credit + 777 WHERE id = ?')->execute([$f['m']]);
    $adm = t_http_login('8003', 'pwAdm');
    $body = t_http_get('admin-member-credit.php?member_id=' . $f['m'], $adm)['body'];
    assert_true(str_contains($body, 'was never recorded'), 'drift is called out');
    assert_true(str_contains($body, '777'), 'and quantified');
});

test('http: the member page links to the activity page and no longer has a credit tab', function () {
    $f = t_credit_fixture();
    $adm = t_http_login('8003', 'pwAdm');
    $body = t_http_get('admin-view-member.php?member_id=' . $f['m'], $adm)['body'];
    assert_true(str_contains($body, 'admin-member-credit.php?member_id=' . $f['m']), 'button links to the statement');
    assert_true(str_contains($body, 'id="openAdjustCredit"'), 'adjust button sits beside the balance');
    assert_true(str_contains($body, 'readonly'), 'balance is read-only');
});

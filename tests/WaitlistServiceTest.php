<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/waitlist-service.php';

/* Fixed clock: Monday 2026-10-05 08:00 Bangkok. Slot under test: 2026-10-07 7-8am (day rate). */
const T_NOW  = '2026-10-05 08:00:00';
const T_DATE = '2026-10-07';

function t_clock(string $ts): void { lsc_waitlist_now(new DateTimeImmutable($ts)); }

$smsLog = [];
lsc_waitlist_set_notifier(function ($phone, $msg) use (&$smsLog) { $smsLog[] = [$phone, $msg]; });

function t_sms(): array { global $smsLog; $s = $smsLog; $smsLog = []; return $s; }

/* ---------------- pricing ---------------- */

test('slot price: day 160, evening 280', function () {
    assert_same(160, lsc_waitlist_slot_price('7-8am'));
    assert_same(160, lsc_waitlist_slot_price('5-6pm'));
    assert_same(280, lsc_waitlist_slot_price('6-7pm'));
    assert_same(280, lsc_waitlist_slot_price('9-10pm'));
});

test('total price includes guests from the waitlist note', function () {
    assert_same(160, lsc_waitlist_total_price('7-8am', ',,,individual,,0'));
    assert_same(560, lsc_waitlist_total_price('7-8am', ',,,individual,,2'));
    assert_same(480, lsc_waitlist_total_price('8-9pm', ',,,couple,,1'));
    assert_same(160, lsc_waitlist_total_price('7-8am', null));
});

/* ---------------- offer ---------------- */

test('offer: first waiting member gets placeholder booking, SMS and pending status', function () {
    t_clock(T_NOW); t_sms();
    $a = t_member(['first_name' => 'Alice', 'member_phone' => '0811111111', 'credit' => 500]);
    $b = t_member(['first_name' => 'Bob', 'member_phone' => '0822222222']);
    $wa = t_waitlist(['member_id' => $a, 'date' => T_DATE]);
    $wb = t_waitlist(['member_id' => $b, 'date' => T_DATE]);

    $offer = lsc_waitlist_offer_slot(t_pdo(), 3, T_DATE, '7-8am');

    assert_true($offer !== null, 'expected an offer');
    assert_eq($a, $offer['member_id'], 'oldest entry wins');
    assert_eq(160, $offer['price']);
    $bk = t_row('bookings', 'id = ?', [$offer['booking_id']]);
    assert_eq('approved', $bk['booking_status']);
    assert_eq('Not paid yet', $bk['payment_remark']);
    assert_eq('waitlist-reserved', $bk['booking_note']);
    assert_eq(3, $bk['court']);
    $w = t_row('wait_list', 'wait_list_id = ?', [$wa]);
    assert_eq('pending', $w['waitlist_status']);
    assert_eq(3, $w['free_court']);
    assert_eq($offer['booking_id'], $w['waitlist_booking_id']);
    assert_null(t_row('wait_list', 'wait_list_id = ?', [$wb])['waitlist_status'], 'second member still waiting');
    $sms = t_sms();
    assert_eq(1, count($sms));
    assert_eq('0811111111', $sms[0][0]);
    assert_true(str_contains($sms[0][1], 'court 3'), 'SMS names the court');
    // ledger rows exist but are not yet counted as bookings
    assert_eq(2, count(t_rows('transactions', "member_id = ? AND transaction_type = 'waitlist offer'", [$a])));
    assert_eq(500, t_credit($a), 'no credit taken at offer time');
});

test('offer: nothing happens when slot starts within 2 hours or has passed', function () {
    t_clock('2026-10-07 05:30:00'); t_sms();
    $a = t_member();
    t_waitlist(['member_id' => $a, 'date' => T_DATE]);
    assert_null(lsc_waitlist_offer_slot(t_pdo(), 1, T_DATE, '7-8am'));
    t_clock('2026-10-07 09:00:00');
    assert_null(lsc_waitlist_offer_slot(t_pdo(), 1, T_DATE, '7-8am'));
    assert_eq(0, count(t_rows('bookings')));
    assert_eq(0, count(t_sms()));
});

test('offer: skipped when the court is not actually free', function () {
    t_clock(T_NOW);
    $a = t_member();
    t_waitlist(['member_id' => $a, 'date' => T_DATE]);
    t_booking(['court' => 1, 'date' => T_DATE, 'timeslot' => '7-8am', 'member_id' => t_member()]);
    assert_null(lsc_waitlist_offer_slot(t_pdo(), 1, T_DATE, '7-8am'));
    assert_null(t_row('wait_list', '1')['waitlist_status']);
});

test('offer: guest (non-member) waitlist entries are skipped', function () {
    t_clock(T_NOW);
    t_waitlist(['member_id' => 90002, 'non_member_id' => 55, 'member_type' => 'non-member', 'date' => T_DATE]);
    assert_null(lsc_waitlist_offer_slot(t_pdo(), 1, T_DATE, '7-8am'));
});

/* ---------------- confirm ---------------- */

test('confirm by member deducts the price from the member', function () {
    t_clock(T_NOW);
    $a = t_member(['credit' => 500]);
    $wa = t_waitlist(['member_id' => $a, 'date' => T_DATE]);
    $offer = lsc_waitlist_offer_slot(t_pdo(), 2, T_DATE, '7-8am');

    $r = lsc_waitlist_confirm(t_pdo(), $wa, ['member_id' => $a, 'is_admin' => false]);

    assert_true($r['ok'], 'confirm ok');
    assert_eq(160, $r['amount']);
    assert_eq(340, t_credit($a), 'credit reduced by 160');
    $bk = t_row('bookings', 'id = ?', [$offer['booking_id']]);
    assert_eq('credit', $bk['payment']);
    assert_null($bk['payment_remark']);
    assert_eq('waitlist booking paid', $bk['booking_note']);
    $w = t_row('wait_list', 'wait_list_id = ?', [$wa]);
    assert_eq('confirmed', $w['waitlist_status']);
    assert_eq($offer['booking_id'], $w['waitlist_booking_id'], 'booking link is kept');
    assert_eq(0, count(t_rows('transactions', "transaction_type = 'waitlist offer'")), 'offer rows promoted');
    assert_eq(2, count(t_rows('transactions', "transaction_type = 'booking (member)' AND transaction_amount = 160")));
});

test('confirm by admin deducts from the waitlisted member, not the admin', function () {
    t_clock(T_NOW);
    $admin = t_member(['member_type' => 'admin', 'credit' => 0]);
    $a = t_member(['credit' => 1000]);
    $wa = t_waitlist(['member_id' => $a, 'date' => T_DATE, 'timeslot' => '8-9pm', 'waitlist_note' => ',,,individual,,1']);
    lsc_waitlist_offer_slot(t_pdo(), 5, T_DATE, '8-9pm');

    $r = lsc_waitlist_confirm(t_pdo(), $wa, ['member_id' => $admin, 'is_admin' => true]);

    assert_true($r['ok']);
    assert_eq(480, $r['amount'], 'evening 280 + one guest 200');
    assert_eq(520, t_credit($a), 'member charged');
    assert_eq(0, t_credit($admin), 'admin untouched');
});

test('confirm refused when credit is insufficient, nothing changes', function () {
    t_clock(T_NOW);
    $a = t_member(['credit' => 100]);
    $wa = t_waitlist(['member_id' => $a, 'date' => T_DATE]);
    $offer = lsc_waitlist_offer_slot(t_pdo(), 1, T_DATE, '7-8am');

    $r = lsc_waitlist_confirm(t_pdo(), $wa, ['member_id' => $a]);

    assert_same(false, $r['ok']);
    assert_eq('insufficient_credit', $r['reason']);
    assert_eq(160, $r['required']);
    assert_eq(100, t_credit($a));
    assert_eq('pending', t_row('wait_list', 'wait_list_id = ?', [$wa])['waitlist_status'], 'offer still open');
    assert_eq('Not paid yet', t_row('bookings', 'id = ?', [$offer['booking_id']])['payment_remark']);
});

test('confirm refused for another member (forbidden)', function () {
    t_clock(T_NOW);
    $a = t_member(); $x = t_member();
    $wa = t_waitlist(['member_id' => $a, 'date' => T_DATE]);
    lsc_waitlist_offer_slot(t_pdo(), 1, T_DATE, '7-8am');
    $r = lsc_waitlist_confirm(t_pdo(), $wa, ['member_id' => $x]);
    assert_eq('forbidden', $r['reason']);
    assert_eq(1000, t_credit($a));
});

test('confirm after 2h TTL expires the offer, frees the court and offers the next member', function () {
    t_clock(T_NOW); t_sms();
    $a = t_member(['credit' => 500, 'member_phone' => '0811111111']);
    $b = t_member(['credit' => 500, 'member_phone' => '0822222222']);
    $wa = t_waitlist(['member_id' => $a, 'date' => T_DATE]);
    $wb = t_waitlist(['member_id' => $b, 'date' => T_DATE]);
    $offer = lsc_waitlist_offer_slot(t_pdo(), 4, T_DATE, '7-8am');
    t_sms();

    t_clock('2026-10-05 10:30:00'); // 2.5h later
    $r = lsc_waitlist_confirm(t_pdo(), $wa, ['member_id' => $a]);

    assert_eq('expired', $r['reason']);
    assert_eq(500, t_credit($a), 'no charge');
    assert_eq('expired', t_row('wait_list', 'wait_list_id = ?', [$wa])['waitlist_status']);
    assert_eq('cancelled', t_row('bookings', 'id = ?', [$offer['booking_id']])['booking_status'], 'placeholder released');
    $wbRow = t_row('wait_list', 'wait_list_id = ?', [$wb]);
    assert_eq('pending', $wbRow['waitlist_status'], 'next member offered');
    assert_eq(4, $wbRow['free_court']);
    $sms = t_sms();
    assert_eq(1, count($sms));
    assert_eq('0822222222', $sms[0][0]);
    // exactly one live booking on court 4
    assert_eq(1, count(t_rows('bookings', "court = 4 AND booking_status <> 'cancelled'")));
});

test('confirm refused when the slot is now within 2 hours', function () {
    t_clock(T_NOW);
    $a = t_member();
    $wa = t_waitlist(['member_id' => $a, 'date' => T_DATE]);
    lsc_waitlist_offer_slot(t_pdo(), 1, T_DATE, '7-8am');
    t_clock('2026-10-07 05:30:00'); // TTL long gone anyway, but also < 2h to slot
    $r = lsc_waitlist_confirm(t_pdo(), $wa, ['member_id' => $a]);
    assert_eq('expired', $r['reason']);
    assert_eq(1000, t_credit($a));
});

test('confirm twice is rejected the second time', function () {
    t_clock(T_NOW);
    $a = t_member(['credit' => 500]);
    $wa = t_waitlist(['member_id' => $a, 'date' => T_DATE]);
    lsc_waitlist_offer_slot(t_pdo(), 1, T_DATE, '7-8am');
    assert_true(lsc_waitlist_confirm(t_pdo(), $wa, ['member_id' => $a])['ok']);
    $r = lsc_waitlist_confirm(t_pdo(), $wa, ['member_id' => $a]);
    assert_eq('not_pending', $r['reason']);
    assert_eq(340, t_credit($a), 'charged once only');
});

/* ---------------- decline ---------------- */

test('decline by member: no charge, placeholder cancelled, next member offered', function () {
    t_clock(T_NOW); t_sms();
    $a = t_member(['credit' => 500]);
    $b = t_member(['credit' => 500, 'member_phone' => '0822222222']);
    $wa = t_waitlist(['member_id' => $a, 'date' => T_DATE]);
    $wb = t_waitlist(['member_id' => $b, 'date' => T_DATE]);
    $offer = lsc_waitlist_offer_slot(t_pdo(), 6, T_DATE, '7-8am');
    t_sms();

    $r = lsc_waitlist_decline(t_pdo(), $wa, ['member_id' => $a]);

    assert_true($r['ok']);
    assert_eq('declined', t_row('wait_list', 'wait_list_id = ?', [$wa])['waitlist_status']);
    assert_eq(500, t_credit($a));
    assert_eq('cancelled', t_row('bookings', 'id = ?', [$offer['booking_id']])['booking_status']);
    assert_eq(0, count(t_rows('transactions', "member_id = ? AND transaction_amount > 0", [$a])), 'ledger shows no charge');
    assert_true($r['next'] !== null && $r['next']['member_id'] == $b, 'next member offered');
    assert_eq('0822222222', t_sms()[0][0]);
});

test('decline by admin works for any member', function () {
    t_clock(T_NOW);
    $admin = t_member(['member_type' => 'admin']);
    $a = t_member();
    $wa = t_waitlist(['member_id' => $a, 'date' => T_DATE]);
    lsc_waitlist_offer_slot(t_pdo(), 1, T_DATE, '7-8am');
    $r = lsc_waitlist_decline(t_pdo(), $wa, ['member_id' => $admin, 'is_admin' => true]);
    assert_true($r['ok']);
    assert_eq('declined', t_row('wait_list', 'wait_list_id = ?', [$wa])['waitlist_status']);
});

test('decline by another member is forbidden', function () {
    t_clock(T_NOW);
    $a = t_member(); $x = t_member();
    $wa = t_waitlist(['member_id' => $a, 'date' => T_DATE]);
    lsc_waitlist_offer_slot(t_pdo(), 1, T_DATE, '7-8am');
    $r = lsc_waitlist_decline(t_pdo(), $wa, ['member_id' => $x]);
    assert_eq('forbidden', $r['reason']);
    assert_eq('pending', t_row('wait_list', 'wait_list_id = ?', [$wa])['waitlist_status']);
});

/* ---------------- expiry sweep ---------------- */

test('expire_stale: expires old offers, leaves fresh ones, promotes next', function () {
    t_clock(T_NOW); t_sms();
    $a = t_member(['member_phone' => '0811111111']);
    $b = t_member(['member_phone' => '0822222222']);
    $c = t_member(['member_phone' => '0833333333']);
    $wa = t_waitlist(['member_id' => $a, 'date' => T_DATE, 'timeslot' => '7-8am']);
    $wb = t_waitlist(['member_id' => $b, 'date' => T_DATE, 'timeslot' => '7-8am']);
    $wc = t_waitlist(['member_id' => $c, 'date' => T_DATE, 'timeslot' => '5-6pm']);
    lsc_waitlist_offer_slot(t_pdo(), 1, T_DATE, '7-8am');   // to a, at 08:00
    t_clock('2026-10-05 09:30:00');
    lsc_waitlist_offer_slot(t_pdo(), 2, T_DATE, '5-6pm');   // to c, at 09:30
    t_sms();

    t_clock('2026-10-05 10:15:00'); // a's offer is 2h15 old, c's is 45 min old
    $n = lsc_waitlist_expire_stale(t_pdo());

    assert_eq(1, $n);
    assert_eq('expired', t_row('wait_list', 'wait_list_id = ?', [$wa])['waitlist_status']);
    assert_eq('pending', t_row('wait_list', 'wait_list_id = ?', [$wb])['waitlist_status'], 'b offered court 1');
    assert_eq('pending', t_row('wait_list', 'wait_list_id = ?', [$wc])['waitlist_status'], 'c untouched');
    assert_eq('0822222222', t_sms()[0][0]);
});

test('expire_stale: no next member leaves the court simply free', function () {
    t_clock(T_NOW);
    $a = t_member();
    $wa = t_waitlist(['member_id' => $a, 'date' => T_DATE]);
    $offer = lsc_waitlist_offer_slot(t_pdo(), 1, T_DATE, '7-8am');
    t_clock('2026-10-05 11:00:00');
    assert_eq(1, lsc_waitlist_expire_stale(t_pdo()));
    assert_eq('cancelled', t_row('bookings', 'id = ?', [$offer['booking_id']])['booking_status']);
    assert_true(lsc_waitlist_court_is_free(t_pdo(), 1, T_DATE, '7-8am'));
});

<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/pricing.php';

test('court price switches to the evening rate at 6pm', function () {
    assert_same(LSC_COURT_FEE_DAY, lsc_price_court('6-7am'));
    assert_same(LSC_COURT_FEE_DAY, lsc_price_court('12-1pm'));
    assert_same(LSC_COURT_FEE_DAY, lsc_price_court('5-6pm'), 'last day-rate slot');
    assert_same(LSC_COURT_FEE_EVENING, lsc_price_court('6-7pm'), 'first evening slot');
    assert_same(LSC_COURT_FEE_EVENING, lsc_price_court('9-10pm'));
    assert_same(LSC_COURT_FEE_DAY, lsc_price_court('nonsense'), 'unknown slots fall back to the day rate');
});

test('timeslot hour lookup', function () {
    assert_same(6, lsc_timeslot_hour('6-7am'));
    assert_same(11, lsc_timeslot_hour('11am-12pm'));
    assert_same(12, lsc_timeslot_hour('12-1pm'));
    assert_same(21, lsc_timeslot_hour('9-10pm'));
    assert_null(lsc_timeslot_hour('5-6am'));
});

test('guest, coach and daily fees', function () {
    assert_same(0, lsc_price_guests(0));
    assert_same(LSC_GUEST_FEE * 3, lsc_price_guests(3));
    assert_same(0, lsc_price_guests(-2), 'negative counts are ignored');
    assert_same(LSC_COACH_FEE, lsc_price_coach('Assistant coach'));
    assert_same(0, lsc_price_coach(''));
    assert_same(0, lsc_price_coach(null));
    assert_same(LSC_DAILY_FEES['couple'], lsc_price_daily_fee('Couple'), 'case insensitive');
    assert_same(LSC_DAILY_FEES['family'], lsc_price_daily_fee(' family '));
    assert_same(LSC_DAILY_FEES['individual'], lsc_price_daily_fee('unknown type'), 'unknown falls back to individual');
    assert_same(LSC_DAILY_FEES['individual'], lsc_price_daily_fee(null));
});

test('the config published to the browser matches the PHP constants', function () {
    $c = json_decode(lsc_pricing_json(), true);
    assert_same(LSC_COURT_FEE_DAY, $c['courtDay']);
    assert_same(LSC_COURT_FEE_EVENING, $c['courtEvening']);
    assert_same(LSC_GUEST_FEE, $c['guestFee']);
    assert_same(LSC_COACH_FEE, $c['coachFee']);
    assert_eq(LSC_DAILY_FEES, $c['dailyFees']);
    assert_eq(['6-7pm', '7-8pm', '8-9pm', '9-10pm'], $c['eveningSlots'], 'browser gets the same evening slots');
    foreach ($c['eveningSlots'] as $slot) {
        assert_same(LSC_COURT_FEE_EVENING, lsc_price_court($slot), "$slot priced as evening on the server too");
    }
});

test('the waitlist service prices through the shared module', function () {
    require_once __DIR__ . '/../includes/waitlist-service.php';
    assert_same(lsc_price_court('7-8am'), lsc_waitlist_slot_price('7-8am'));
    assert_same(lsc_price_court('8-9pm'), lsc_waitlist_slot_price('8-9pm'));
    // member: court + guests
    assert_same(LSC_COURT_FEE_DAY + 2 * LSC_GUEST_FEE, lsc_waitlist_total_price('7-8am', ',,,individual,,2'));
    // guest: court + daily + coach + guests
    assert_same(LSC_COURT_FEE_EVENING + LSC_DAILY_FEES['family'] + LSC_COACH_FEE + LSC_GUEST_FEE,
        lsc_waitlist_total_price('8-9pm', 'k,k@x.com,06,Family,Assistant coach,1', true));
});

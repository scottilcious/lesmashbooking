<?php
/**
 * Prices. Single source of truth for PHP and JavaScript.
 *
 * To change a price, edit the constants below and nothing else. `menu.php` emits
 * them to the browser as `window.LSC_PRICING`, which `app.js` and the inline
 * script in `check_availability.php` read, so the summary card and the server
 * always agree.
 *
 * Evening rate applies from LSC_EVENING_FROM_HOUR onwards.
 */

declare(strict_types=1);

const LSC_COURT_FEE_DAY     = 160;  // per hour, 06:00-18:00
const LSC_COURT_FEE_EVENING = 280;  // per hour, 18:00-22:00
const LSC_GUEST_FEE         = 200;  // per extra player
const LSC_COACH_FEE         = 750;  // assistant coach, guest bookings
const LSC_EVENING_FROM_HOUR = 18;

/** Daily membership fee paid by guests, by the daily type they pick. Keys are lowercase. */
const LSC_DAILY_FEES = [
    'individual'      => 500,
    'couple'          => 800,
    'family'          => 950,
    'junior'          => 350,
    '1 adult 1 child' => 650,
];

/** Start hour of a timeslot string, or null if it is not a known slot. */
function lsc_timeslot_hour(string $timeslot): ?int
{
    static $hours = [
        '6-7am' => 6, '7-8am' => 7, '8-9am' => 8, '9-10am' => 9, '10-11am' => 10, '11am-12pm' => 11,
        '12-1pm' => 12, '1-2pm' => 13, '2-3pm' => 14, '3-4pm' => 15, '4-5pm' => 16, '5-6pm' => 17,
        '6-7pm' => 18, '7-8pm' => 19, '8-9pm' => 20, '9-10pm' => 21,
    ];
    return $hours[trim($timeslot)] ?? null;
}

function lsc_is_evening_slot(string $timeslot): bool
{
    $h = lsc_timeslot_hour($timeslot);
    return $h !== null && $h >= LSC_EVENING_FROM_HOUR;
}

/** Court fee for one hour. */
function lsc_price_court(string $timeslot): int
{
    return lsc_is_evening_slot($timeslot) ? LSC_COURT_FEE_EVENING : LSC_COURT_FEE_DAY;
}

function lsc_price_guests(int $extraPlayers): int
{
    return max(0, $extraPlayers) * LSC_GUEST_FEE;
}

/** Daily membership fee for a guest booking; unknown types fall back to individual. */
function lsc_price_daily_fee(?string $dailyMemberType): int
{
    $key = strtolower(trim((string) $dailyMemberType));
    return LSC_DAILY_FEES[$key] ?? LSC_DAILY_FEES['individual'];
}

function lsc_price_coach(?string $coachOption): int
{
    return stripos((string) $coachOption, 'coach') !== false ? LSC_COACH_FEE : 0;
}

/** The same numbers, for the browser. */
function lsc_pricing_config(): array
{
    return [
        'courtDay'        => LSC_COURT_FEE_DAY,
        'courtEvening'    => LSC_COURT_FEE_EVENING,
        'guestFee'        => LSC_GUEST_FEE,
        'coachFee'        => LSC_COACH_FEE,
        'eveningFromHour' => LSC_EVENING_FROM_HOUR,
        'dailyFees'       => LSC_DAILY_FEES,
        'eveningSlots'    => array_values(array_filter(
            ['6-7am','7-8am','8-9am','9-10am','10-11am','11am-12pm','12-1pm','1-2pm','2-3pm','3-4pm','4-5pm','5-6pm','6-7pm','7-8pm','8-9pm','9-10pm'],
            'lsc_is_evening_slot'
        )),
    ];
}

function lsc_pricing_json(): string
{
    return json_encode(lsc_pricing_config(), JSON_UNESCAPED_UNICODE);
}

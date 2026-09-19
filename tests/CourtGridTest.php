<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/court-grid.php';
require_once __DIR__ . '/http.php';

/** Render the rows for a date and return the HTML. */
function t_grid(string $date, array $ctx = []): string
{
    ob_start();
    lsc_court_grid_rows(t_pdo(), $date, $ctx + ['memberType' => 'individual']);
    return (string) ob_get_clean();
}

test('the grid draws every court and timeslot once, plus a waitlist column', function () {
    $html = t_grid('2026-10-07');
    assert_eq(count(LSC_GRID_TIMES), substr_count($html, '<tr>'), 'one row per timeslot');
    foreach (LSC_GRID_TIMES as $t) {
        assert_eq(1, substr_count($html, 'id="waitlist_' . $t . '"'), "one waitlist box for $t");
        for ($c = 1; $c <= LSC_GRID_COURTS; $c++) {
            assert_eq(1, substr_count($html, 'id="select-court-' . $c . '-' . $t . '"'), "one box for court $c at $t");
        }
    }
});

test('a booked slot shows the member number instead of a checkbox', function () {
    $m = t_member(['member_number' => 4242, 'first_name' => 'Nina']);
    t_booking(['court' => 3, 'date' => '2026-10-07', 'timeslot' => '7-8am', 'member_id' => $m]);
    $html = t_grid('2026-10-07');
    assert_true(!str_contains($html, 'id="select-court-3-7-8am"'), 'no checkbox on the taken slot');
    assert_true(str_contains($html, '4242'), 'member number shown');
    assert_true(str_contains($html, 'booked status-approved'), 'marked as booked');
    assert_true(str_contains($html, 'id="select-court-4-7-8am"'), 'other courts still bookable');
});

test('cancelled bookings free the slot again', function () {
    $m = t_member();
    t_booking(['court' => 2, 'date' => '2026-10-07', 'timeslot' => '8-9am', 'member_id' => $m, 'booking_status' => 'cancelled']);
    assert_true(str_contains(t_grid('2026-10-07'), 'id="select-court-2-8-9am"'), 'slot is selectable again');
});

test('admins see the member name and the unpaid marker; members do not', function () {
    $m = t_member(['member_number' => 5150, 'first_name' => 'Owen']);
    t_booking(['court' => 1, 'date' => '2026-10-07', 'timeslot' => '9-10am', 'member_id' => $m,
               'payment' => 'cash', 'payment_remark' => 'Not paid yet']);
    $admin = t_grid('2026-10-07', ['memberType' => 'admin']);
    assert_true(str_contains($admin, 'Owen'), 'admin sees the name');
    assert_true(str_contains($admin, 'not_paid'), 'admin sees the unpaid class the CSS colours');
    assert_true(str_contains($admin, 'Not paid yet'), 'and the wording');

    $member = t_grid('2026-10-07', ['memberType' => 'individual']);
    assert_true(!str_contains($member, 'Owen'), 'members do not see other members by name');
    assert_true(str_contains($member, '5150'), 'only the member number');
    assert_true(!str_contains($member, 'Not paid yet'), 'members do not see payment state');
});

test('academy blocks show their label rather than a member', function () {
    $m = t_member();
    t_booking(['court' => 5, 'date' => '2026-10-07', 'timeslot' => '4-5pm', 'member_id' => $m, 'booking_type' => 'junior_academy']);
    $html = t_grid('2026-10-07', ['memberType' => 'admin']);
    assert_true(str_contains($html, 'Junior Academy'), 'label shown');
    assert_true(str_contains($html, 'booked-academy'), 'styled as a block');
});

test('the waitlist box carries the quota and disables itself once reached', function () {
    $m = t_member(['member_type' => 'individual']);   // 1 court per timeslot
    $html = t_grid('2026-10-07', ['limitMemberType' => 'individual', 'selectedMemberId' => $m]);
    assert_true(str_contains($html, 'data-max-per-timeslot="1"'));
    assert_true(!str_contains($html, 'data-quota-disabled'), 'nothing booked yet');

    t_booking(['court' => 1, 'date' => '2026-10-07', 'timeslot' => '7-8am', 'member_id' => $m]);
    $html = t_grid('2026-10-07', ['limitMemberType' => 'individual', 'selectedMemberId' => $m]);
    assert_true(str_contains($html, 'Waitlist Unavailable - quota reached'), 'quota reached for that slot');
    assert_eq(1, substr_count($html, 'data-quota-disabled'), 'only the affected timeslot');
});

test('non-members are blocked from peak slots more than 48 hours ahead', function () {
    $far = date('Y-m-d', strtotime('+6 days'));
    $guest = t_grid($far, ['memberType' => 'non-member']);
    assert_true(str_contains($guest, 'Peak Time'), 'peak slots are blocked');
    assert_true(!str_contains($guest, 'id="select-court-1-7-8am"'), '7-8am is peak on any day');
    assert_true(str_contains($guest, 'id="select-court-1-12-1pm"'), 'off-peak is still open');

    $member = t_grid($far, ['memberType' => 'individual']);
    assert_true(!str_contains($member, 'Peak Time'), 'members are not restricted');
    assert_true(str_contains($member, 'id="select-court-1-7-8am"'));
});

test('holiday closures: courts 1-4 only, then all courts', function () {
    $partial = t_grid('2025-12-24');
    assert_true(str_contains($partial, 'Court Closed'), 'closure shown');
    assert_eq(4 * count(LSC_GRID_TIMES), substr_count($partial, 'disabled>'), 'courts 1-4 disabled all day');
    assert_true(str_contains($partial, 'id="select-court-5-7-8am"'), 'court 5 still open');

    $full = t_grid('2025-12-31');
    assert_eq(LSC_GRID_COURTS * count(LSC_GRID_TIMES), substr_count($full, 'disabled>'), 'every court disabled');
});

test('http: the availability endpoint returns rows only, with no script', function () {
    $m = t_member(['member_number' => 4400, 'member_password' => 'pw4', 'credit' => 500]);
    t_pdo()->exec("INSERT INTO system_settings (setting_key, setting_value) VALUES ('allow_midnight_booking', '1')");
    $jar = t_http_login('4400', 'pw4');
    $date = date('Y-m-d', strtotime('+2 days'));
    $r = t_http_post('check_availability.php', ['date' => $date], $jar);
    assert_eq(200, $r['status']);
    assert_true(!str_contains($r['body'], '<script'), 'the page owns the JavaScript, not the fragment');
    assert_true(!str_contains($r['body'], 'Fatal error'), 'no PHP error');
    assert_eq(count(LSC_GRID_TIMES), substr_count($r['body'], '<tr>'), 'a row per timeslot');
});

test('http: the availability endpoint needs a session and refuses dates outside the window', function () {
    t_pdo()->exec("INSERT INTO system_settings (setting_key, setting_value) VALUES ('allow_midnight_booking', '1')");
    assert_eq(401, t_http_post('check_availability.php', ['date' => date('Y-m-d')])['status']);
    t_member(['member_number' => 4401, 'member_password' => 'pw4']);
    $jar = t_http_login('4401', 'pw4');
    $far = t_http_post('check_availability.php', ['date' => date('Y-m-d', strtotime('+30 days'))], $jar);
    assert_true(str_contains($far['body'], '7 days in advance'), 'advance limit enforced');
    assert_true(!str_contains($far['body'], 'select-court-'), 'and no grid is drawn');
});

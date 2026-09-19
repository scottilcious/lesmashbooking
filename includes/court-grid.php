<?php
/**
 * The court booking grid: 7 courts by 16 hourly slots, plus a waitlist column.
 *
 * One implementation, used by both places that draw it:
 *   modules/time-table.php   the initial render inside index.php
 *   check_availability.php   the AJAX re-render when the date changes
 *
 * Those two used to carry their own copies of the same nine helpers and the
 * same 200 lines of cell markup, which had already drifted apart.
 *
 * `lsc_court_grid_rows()` echoes the <tr> elements only. The surrounding table
 * and its header live in modules/time-table.php, because the AJAX response
 * replaces the table body alone.
 */

declare(strict_types=1);

require_once __DIR__ . '/booking-functions.php';

const LSC_GRID_COURTS = 7;
const LSC_GRID_TIMES  = [
    '6-7am', '7-8am', '8-9am', '9-10am', '10-11am', '11am-12pm', '12-1pm',
    '1-2pm', '2-3pm', '3-4pm', '4-5pm', '5-6pm', '6-7pm', '7-8pm', '8-9pm', '9-10pm',
];

/** Courts 1 to 4 were unavailable over the 2025 holidays; 5 to 7 stayed open. */
const LSC_GRID_PARTIAL_CLOSURE_DATES = ['2025-12-21', '2025-12-22', '2025-12-23', '2025-12-24', '2025-12-25', '2025-12-26', '2025-12-27', '2025-12-28'];
const LSC_GRID_FULL_CLOSURE_DATES    = ['2025-12-29', '2025-12-30', '2025-12-31', '2026-01-01'];
const LSC_GRID_CLOSED_STYLE          = 'style="background: #f8f8f8; color: #000; border: 1px solid #f2f2f2;"';

/* ------------------------------------------------------------------ peak time */

function lsc_grid_is_weekend(string $dayOfWeek): bool
{
    return in_array($dayOfWeek, ['Saturday', 'Sunday'], true);
}

/** Peak slots, which non-members may not book far in advance. */
function lsc_grid_peak_times(string $dayOfWeek): array
{
    $weekday = ['6-7am', '7-8am', '8-9am', '4-5pm', '5-6pm', '6-7pm', '7-8pm', '8-9pm', '9-10pm'];
    $weekend = array_merge(['9-10am', '10-11am', '11-12am'], $weekday);
    return lsc_grid_is_weekend($dayOfWeek) ? $weekend : $weekday;
}

function lsc_grid_is_peak(string $dayOfWeek, string $time): bool
{
    return in_array($time, lsc_grid_peak_times($dayOfWeek), true);
}

/** Hours from now until midday-equivalent on $date, used for the non-member peak rule. */
function lsc_grid_hours_until(string $date): float
{
    $now  = new DateTime();
    $then = new DateTime($date . ' ' . $now->format('H:i:s'));
    $diff = $now->diff($then);
    return round(($diff->days * 24) + $diff->h + ($diff->i / 60), 2);
}

/* ------------------------------------------------------------------ labels */

function lsc_grid_booking_type_label(string $bookingType): string
{
    return [
        'court'          => 'Court booked',
        'academy'        => 'Academy',
        'junior_academy' => 'Junior Academy',
        'adult_clinic'   => 'Adult Clinic',
        'tennis_camp'    => 'Tennis Camp',
        'tournament'     => 'Tournament',
    ][$bookingType] ?? '';
}

function lsc_grid_daily_type_label(string $dailyMemberType): string
{
    return [
        'Individual'      => 'Indiv.',
        'Couple'          => 'Couple',
        'Family'          => 'Family',
        'Junior'          => 'Junior',
        '1 adult 1 child' => '1 adult + child',
    ][$dailyMemberType] ?? '';
}

/* ------------------------------------------------------------------ one occupied cell */

/**
 * What to show in a cell that already has a booking.
 * The purple "not paid" highlight comes from .booked.not_paid in style.css.
 */
function lsc_grid_render_booked_cell(array $booking, string $viewerType, PDO $pdo): void
{
    $isAdmin  = $viewerType === 'admin';
    $notPaid  = $isAdmin && !empty($booking['payment_remark']);
    $classes  = 'time_table_page check_availability_page booked status-' . $booking['booking_status'] . ' ' . ($notPaid ? 'not_paid' : 'already_paid');
    $type     = $booking['booking_type'];

    if ($type !== 'member booking' && $type !== 'non-member') {
        echo '<div class="booked-academy">' . htmlspecialchars(lsc_grid_booking_type_label((string) $type)) . '</div>';
        return;
    }

    if ($type === 'member booking') {
        $st = $pdo->prepare('SELECT member_number, first_name FROM members WHERE id = ?');
        $st->execute([$booking['member_id']]);
        $member = $st->fetch(PDO::FETCH_ASSOC) ?: ['member_number' => '', 'first_name' => ''];
        $who    = ($isAdmin ? $member['first_name'] . ' - ' : '') . $member['member_number'];
        $tip    = 'Member Booking';
    } else {
        $guest = explode(',', (string) $booking['non_member_info']);
        $who   = '[Non-Member] ' . ($guest[0] ?? '');
        $tip   = 'Non Member Booking';
    }
    ?>
    <div class="<?= $classes ?>"<?php if ($isAdmin): ?> data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?= $tip ?>"<?php endif; ?>>
        <?php if ($isAdmin): ?>
            <span class="badge rounded-pill text-bg-light"><?= htmlspecialchars(lsc_grid_daily_type_label((string) $booking['daily_member_type'])) ?></span><br>
        <?php endif; ?>
        <?= htmlspecialchars($who) ?>
        <?php if (!empty($booking['coach_name'])): ?>
            <br>Coach: <?= htmlspecialchars((string) $booking['coach_name']) ?>
        <?php endif; ?>
        <?php if ($notPaid): ?>
            <br><small>(<?= $booking['booking_note'] === 'waitlist-reserved' ? 'Waitlist Reserved' : 'Not paid yet' ?>)</small>
        <?php endif; ?>
    </div>
    <?php
}

/* ------------------------------------------------------------------ the grid */

/**
 * Echo the <tr> rows for one date.
 *
 * $ctx:
 *   memberType        the viewer's type; drives what is shown and the peak rule
 *   limitMemberType   whose booking rules to apply (admins may act for a member)
 *   selectedMemberId  whose existing bookings count toward the waitlist quota
 */
function lsc_court_grid_rows(PDO $pdo, string $date, array $ctx): void
{
    $memberType      = (string) ($ctx['memberType'] ?? '');
    $limitMemberType = (string) ($ctx['limitMemberType'] ?? $memberType);
    $selectedMember  = $ctx['selectedMemberId'] ?? null;

    $partialClosure = in_array($date, LSC_GRID_PARTIAL_CLOSURE_DATES, true);
    $fullClosure    = in_array($date, LSC_GRID_FULL_CLOSURE_DATES, true);
    $closedStyle    = ($partialClosure || $fullClosure) ? LSC_GRID_CLOSED_STYLE : '';
    $disabledAttr   = ($partialClosure || $fullClosure) ? 'disabled' : '';
    $availableText  = $fullClosure || $partialClosure ? 'Court Closed' : 'Available';

    $dayOfWeek = date('l', strtotime($date));
    $hoursAway = lsc_grid_hours_until($date);

    // Everything booked on the day, keyed by "timeslot_court".
    $st = $pdo->prepare("SELECT * FROM bookings WHERE date = ? AND booking_status <> 'cancelled'");
    $st->execute([$date]);
    $booked = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $booked[$row['timeslot'] . '_' . $row['court']] = $row;
    }

    $rules  = lsc_get_booking_rules_for_member_type($limitMemberType);
    $counts = lsc_get_member_timeslot_booking_counts($pdo, $date, $selectedMember);

    foreach (LSC_GRID_TIMES as $time) {
        // Non-members cannot take a peak slot more than 48 hours ahead.
        $peakBlocked = $memberType === 'non-member' && $hoursAway > 48 && lsc_grid_is_peak($dayOfWeek, $time);

        $existing       = $counts[$time] ?? 0;
        $quotaReached   = $existing >= $rules['maxPerTimeslot'];
        $waitlistLabel  = $quotaReached ? 'Waitlist Unavailable - quota reached' : ($fullClosure ? 'Court Closed' : 'Waitlist Available');
        ?>
        <tr>
            <td class="timeslot-column fw-bold"><?= $time ?></td>
            <?php for ($court = 1; $court <= LSC_GRID_COURTS; $court++):
                // The holiday closure applied to courts 1 to 4 only.
                $cellStyle    = ($court <= 4 || $fullClosure) ? $closedStyle : '';
                $cellDisabled = ($court <= 4 || $fullClosure) ? $disabledAttr : '';
                $cellLabel    = ($court <= 4 || $fullClosure) ? $availableText : ($fullClosure ? 'Court Closed' : 'Available');
                $key          = $time . '_' . $court;
                ?>
                <td class="time_<?= $time ?>_court_<?= $court ?>">
                    <?php if (isset($booked[$key])): ?>
                        <?php lsc_grid_render_booked_cell($booked[$key], $memberType, $pdo); ?>
                    <?php elseif ($peakBlocked): ?>
                        <div class="peak-time-available" <?= $cellStyle ?>>Peak Time</div>
                    <?php else: ?>
                        <label class="available-slot" for="select-court-<?= $court ?>-<?= $time ?>" <?= $cellStyle ?>>
                            <input type="checkbox" value="<?= $court ?>/<?= $time ?>" id="select-court-<?= $court ?>-<?= $time ?>" name="court_timeslot[]" <?= $cellDisabled ?>>
                            <?= $cellLabel ?>
                        </label>
                    <?php endif; ?>
                </td>
            <?php endfor; ?>
            <td>
                <label class="available-slot waitlist-slot" for="waitlist_<?= $time ?>" <?= $fullClosure ? LSC_GRID_CLOSED_STYLE : '' ?>>
                    <input type="checkbox" value="waitlist/<?= $time ?>" id="waitlist_<?= $time ?>" name="court_timeslot[]"
                           data-existing-member-bookings="<?= (int) $existing ?>"
                           data-max-per-timeslot="<?= (int) $rules['maxPerTimeslot'] ?>"
                           <?= $quotaReached ? 'disabled data-quota-disabled="true"' : '' ?>>
                    <span class="waitlist-label-text"><?= htmlspecialchars($waitlistLabel) ?></span>
                </label>
            </td>
        </tr>
        <?php
    }
}

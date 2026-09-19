<?php
/**
 * AJAX: re-render the booking grid rows for a chosen date.
 * Returns <tr> elements only; app.js drops them into #largeTimeTable.
 * No JavaScript is emitted here: app.js is already loaded on the page and
 * owns the selection rules and fee calculation.
 */
require_once __DIR__ . '/includes/auth.php';
$lsc_me = lsc_require_login();
require 'config.php';
require_once 'includes/court-grid.php';
require_once 'includes/booking-functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST only.');
}

$passed_date = (string) ($_POST['date'] ?? '');
$memberType  = $lsc_me['type'];

if (!lsc_is_booking_window_open_for_member($memberType)) {
    ?>
    <tr><td colspan="9"><div class="alert alert-warning mb-0">
        Booking is closed between 12:00 am and 6:00 am. Please come back after 6:00 am.
    </div></td></tr>
    <?php
    exit;
}

if (!lsc_is_booking_date_within_advance_window($passed_date, $memberType)) {
    ?>
    <tr><td colspan="9"><div class="alert alert-warning mb-0">
        Bookings are limited to today through 7 days in advance.
    </div></td></tr>
    <?php
    exit;
}

// Only an admin may draw the grid against another member's rules and quota.
lsc_court_grid_rows($pdo, $passed_date, [
    'memberType'       => $memberType,
    'limitMemberType'  => $lsc_me['is_admin'] ? (string) ($_POST['limit_member_type'] ?? $memberType) : $memberType,
    'selectedMemberId' => $lsc_me['is_admin'] ? ($_POST['member_id'] ?? null) : $lsc_me['id'],
]);

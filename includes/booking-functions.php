<?php 
date_default_timezone_set('Asia/Bangkok');

function lsc_is_booking_window_open_for_member(?string $memberType = null): bool
{
    $memberType = strtolower(trim((string) $memberType));

    if ($memberType === 'admin') {
        return true;
    }

    if (lsc_is_midnight_booking_enabled()) {
        return true;
    }

    $currentTime = new DateTime('now');
    $currentHour = (int) $currentTime->format('G');

    return !($currentHour >= 0 && $currentHour < 6);
}

function lsc_render_booking_window_closed_message(string $returnUrl = 'index.php'): void
{
    ?>
    <div class="error-message text-center text-danger">
        <span class="fs-2">
            <i class="ri-error-warning-fill"></i>
        </span>
        <br>
        <p>Booking is closed between 12:00 am and 6:00 am.<br>Please come back after 6:00 am.</p>
        <hr>
        <a href="<?= $returnUrl ?>" class="btn btn-primary">Back</a>
    </div>
    <?php
}

function lsc_ensure_system_settings_table(): void
{
    global $pdo;

    static $tableReady = false;
    if ($tableReady || !isset($pdo)) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS system_settings (
            setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
            setting_value TEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    $tableReady = true;
}

function lsc_get_system_setting(string $key, $default = null)
{
    global $pdo;

    static $settingsCache = [];

    if (array_key_exists($key, $settingsCache)) {
        return $settingsCache[$key];
    }

    if (!isset($pdo)) {
        return $default;
    }

    try {
        lsc_ensure_system_settings_table();
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
    } catch (PDOException $e) {
        return $default;
    }

    if ($value === false) {
        $settingsCache[$key] = $default;
        return $default;
    }

    $settingsCache[$key] = $value;
    return $value;
}

function lsc_set_system_setting(string $key, string $value): bool
{
    global $pdo;

    static $settingsCache = [];

    if (!isset($pdo)) {
        return false;
    }

    try {
        lsc_ensure_system_settings_table();
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $result = $stmt->execute([$key, $value]);
        if ($result) {
            $settingsCache[$key] = $value;
        }
        return $result;
    } catch (PDOException $e) {
        return false;
    }
}

function lsc_is_midnight_booking_enabled(): bool
{
    $settingValue = lsc_get_system_setting('allow_midnight_booking', '0');
    return in_array((string) $settingValue, ['1', 'true', 'on', 'yes'], true);
}

function lsc_set_midnight_booking_enabled(bool $enabled): bool
{
    return lsc_set_system_setting('allow_midnight_booking', $enabled ? '1' : '0');
}

function lsc_get_booking_advance_limit_days(?string $memberType = null): ?int
{
    $memberType = strtolower(trim((string) $memberType));

    if ($memberType === 'admin') {
        return null;
    }

    return 7;
}

function lsc_is_booking_date_within_advance_window(string $date, ?string $memberType = null): bool
{
    $advanceLimitDays = lsc_get_booking_advance_limit_days($memberType);
    if ($advanceLimitDays === null) {
        return true;
    }

    $bookingDate = DateTime::createFromFormat('Y-m-d', $date);
    if (!$bookingDate) {
        return false;
    }

    $bookingDate->setTime(0, 0, 0);
    $today = new DateTime('today');
    $maxBookingDate = (clone $today)->modify('+' . $advanceLimitDays . ' days');

    return $bookingDate >= $today && $bookingDate <= $maxBookingDate;
}

function lsc_render_booking_advance_limit_message(string $returnUrl = 'index.php', int $days = 7): void
{
    ?>
    <div class="error-message text-center text-danger">
        <span class="fs-2">
            <i class="ri-error-warning-fill"></i>
        </span>
        <br>
        <p>You can only make a booking up to <?= (int) $days ?> days in advance.</p>
        <hr>
        <a href="<?= htmlspecialchars($returnUrl) ?>" class="btn btn-primary">Back</a>
    </div>
    <?php
}

function lsc_get_booking_rules_for_member_type($member_type): array
{
    $member_type = strtolower(trim((string) $member_type));

    if ($member_type === 'individual' || $member_type === 'junior') {
        return ['totalLimit' => 2, 'maxPerTimeslot' => 1];
    }

    if ($member_type === 'couple' || $member_type === '1 adult 1 child') {
        return ['totalLimit' => 3, 'maxPerTimeslot' => 2];
    }

    if ($member_type === 'family') {
        return ['totalLimit' => 4, 'maxPerTimeslot' => 2];
    }

    if ($member_type === 'admin') {
        return ['totalLimit' => 240, 'maxPerTimeslot' => 12];
    }

    return ['totalLimit' => 2, 'maxPerTimeslot' => 1];
}

function lsc_get_member_timeslot_booking_counts(PDO $pdo, string $date, $member_id): array
{
    if (empty($member_id)) {
        return [];
    }

    $stmt = $pdo->prepare("SELECT timeslot, COUNT(*) AS booking_count FROM bookings WHERE date = ? AND member_id = ? AND NOT booking_status = ? GROUP BY timeslot");
    $stmt->execute([$date, $member_id, 'cancelled']);

    $counts = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $counts[$row['timeslot']] = (int) $row['booking_count'];
    }

    return $counts;
}


function format_date_to_readable_text($input_date){
        $input_date = DateTime::createFromFormat("Y-m-d", $input_date);
        $formattedDate = $input_date->format("d F, Y");
    return $formattedDate;
}

function get_time_from_timeslot_booking($timeslot){

    switch ($timeslot) {
        case '6-7am':
            $time = '6:00:00';
            break;
        
        case '7-8am':
            $time = '7:00:00';
            break;

        case '8-9am':
            $time = '8:00:00';
            break;
        
        case '9-10am':
            $time = '9:00:00';
            break;

        case '10-11am':
            $time = '10:00:00';
            break;
        
        case '11am-12pm':
            $time = '11:00:00';
            break;

        case '12-1pm':
            $time = '12:00:00';
            break;
        
        case '1-2pm':
            $time = '13:00:00';
            break;
        
        case '2-3pm':
            $time = '14:00:00';
            break;

        case '3-4pm':
            $time = '15:00:00';
            break;
        
        case '4-5pm':
            $time = '16:00:00';
            break;

        case '5-6pm':
            $time = '17:00:00';
            break;

        case '6-7pm':
            $time = '18:00:00';
            break;
        
        case '7-8pm':
            $time = '19:00:00';
            break;

        case '8-9pm':
            $time = '20:00:00';
            break;
        
        case '9-10pm':
            $time = '21:00:00';
            break;

        default:
            # code...
            break;
    }

    return $time;

}

function message_credit_refund_policy(){
    ?>
    <div class="container mt-5">
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <strong>Conditions for credit payment:</strong>
            <ul>
                <li>Refundable if cancelled at least 48 hours before the booked session</li>
                <li>Non-refundable if cancelled less than 48 hours before the booked session</li>
                <li>Refundable if booking cancelled due to rain</li>
                <li>Refundable if booking cancelled due to pollution AQI over 150</li>
                <li>coaching fees are due in full if cancellation on the same day</li>
            </ul>
                
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    <?php 
}


function get_booking_status_badge($booking_status){

    $badge_html  = "";
    switch ($booking_status) {
        case 'approved':
            $badge_html = '<span class="badge text-bg-success">Approved</span>';
            break;

        case 'pending':
            $badge_html = '<span class="badge text-bg-warning">Pending</span>';
            break;
        
        case 'cancelled':
            $badge_html = '<span class="badge text-bg-danger">Cancelled</span>';
            break;
        
        default:
            # code...
            break;
    }
    
    return $badge_html;
}

function get_booking_payment_info($booking_payment, $transaction_amount, $slip_url){


    if(  $booking_payment == 'credit' ){
    ?>
        <span>Paid with account credit</span>
        <span class="badge rounded-pill text-bg-dark"><?= $transaction_amount ?></span>
    <?php 
    }

    if( $booking_payment == 'qr'){
    ?>
        <span>Paid by direct bank transfer</span>
        <a href="<?= $slip_url ?>" class="btn btn-outline-primary" target="_blank">View Slip</a>
    <?php 
    }

    if( $booking_payment == 'cash'){
    ?>
    <span>Paid with Cash</span>
    <?php 
    }

}


// Legacy helper kept for screens that still need the "booking has passed" flag.
// Do not use index [2] for refund decisions; use lsc_is_booking_refund_eligible() instead.
function get_booking_date_compare($date, $timeslot){
    $currentTime = new DateTime();
    $converted_time = get_time_from_timeslot_booking($timeslot);
    $booking_date_time_converted = create_booking_date_time($date, $converted_time);

    $date_passed = false;
    if( $booking_date_time_converted < $currentTime){
        $date_passed = true;
    }else{
        $date_passed = false;
    }

    //Check the time differences
    $diff = $currentTime->diff($booking_date_time_converted);
    $hoursDifference = ($diff->days * 24) + $diff->h + ($diff->i / 60);

    // Legacy 48-hour flag. No current refund code should depend on this array value.
    $lessthan_24hr = false;
    if( $hoursDifference <= 48 ){
        $lessthan_24hr = true;
    }else{
        $lessthan_24hr = false;
    }

    $booking_datetime_compare = [$hoursDifference, $date_passed, $lessthan_24hr];

    return $booking_datetime_compare;
}

function lsc_is_booking_refund_eligible(string $date, string $timeslot, int $hours = 48): bool
{
    $converted_time = get_time_from_timeslot_booking($timeslot);
    if (empty($converted_time)) {
        return false;
    }

    $booking_date_time = create_booking_date_time($date, $converted_time);
    $current_time = new DateTime();
    $seconds_until_booking = $booking_date_time->getTimestamp() - $current_time->getTimestamp();

    return $seconds_until_booking >= ($hours * 3600);
}


?>

<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function lsc_admin_member_booking_rules($member_type)
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

    return ['totalLimit' => 2, 'maxPerTimeslot' => 1];
}

function lsc_render_admin_member_booking_limit_error($message)
{
    ?>
    <div class="error-message text-center text-danger">
        <span class="fs-2">
            <i class="ri-error-warning-fill"></i>
        </span>
        <br>
        <p><?= htmlspecialchars($message) ?></p>
        <hr>
        <a href="index.php" class="btn btn-primary">Try again</a>
    </div>
    <?php
}

function lsc_enforce_admin_member_booking_limits(PDO $pdo, $date, $member_id, $member_type, array $selected_times): void
{
    $rules = lsc_admin_member_booking_rules($member_type);
    $selected_times = array_values(array_filter(array_map('trim', $selected_times), static function ($time) {
        return $time !== '';
    }));

    $selected_total = count($selected_times);
    $selected_by_timeslot = array_count_values($selected_times);

    $stmt_total = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE date = ? AND member_id = ? AND NOT booking_status = ?");
    $stmt_total->execute([$date, $member_id, 'cancelled']);
    $existing_total = (int) $stmt_total->fetchColumn();

    if (($existing_total + $selected_total) > $rules['totalLimit']) {
        lsc_render_admin_member_booking_limit_error('This member has reached the maximum booking of ' . $rules['totalLimit'] . ' sessions per day.');
        exit();
    }

    $stmt_timeslot = $pdo->prepare("SELECT timeslot, COUNT(*) AS booking_count FROM bookings WHERE date = ? AND member_id = ? AND NOT booking_status = ? GROUP BY timeslot");
    $stmt_timeslot->execute([$date, $member_id, 'cancelled']);
    $existing_by_timeslot = [];
    foreach ($stmt_timeslot->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing_by_timeslot[$row['timeslot']] = (int) $row['booking_count'];
    }

    foreach ($selected_by_timeslot as $timeslot => $selected_count) {
        $existing_count = $existing_by_timeslot[$timeslot] ?? 0;
        if (($existing_count + $selected_count) > $rules['maxPerTimeslot']) {
            $message = $rules['maxPerTimeslot'] === 1
                ? 'This member cannot book more than one court at the same time (' . $timeslot . ').'
                : 'This member can book at most ' . $rules['maxPerTimeslot'] . ' courts at the same time (' . $timeslot . ').';
            lsc_render_admin_member_booking_limit_error($message);
            exit();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require 'config.php';
    require_once 'includes/functions.php';
    include 'includes/booking-functions.php';

    $booking_type           = $_POST['booking_type'];
    $date                   = $_POST['date'];

    $member_id              = $_POST['member_id'];
    $daily_member_type      = $_POST['daily_member_type'];
    $daily_member_type_lwr  = strtolower($daily_member_type);
    $coach_option           = $_POST['coach_option'] ?? '';
    $extra_player           = $_POST['extra_player'] ?? 0;
    $coach_name             = $_POST['coach_name'] ?? '';
    
    $all_selected_courts    = $_POST['all_selected_courts'];
    $all_selected_times     = $_POST['all_selected_times'];
    /*
    $all_selected_courts    = '1,2';
    $all_selected_times     = '6-7am,6-7am';
    */
    $courts                 = explode(",", $all_selected_courts);
    $times                  = explode(",", $all_selected_times);
    $transaction_amount     = $_POST['transaction_amount'];
    $payment_type           = $_POST['payment_type'];
    $not_paid_yet           = $_POST['not_paid_yet'] ?? '';
    if( $not_paid_yet == '' || $not_paid_yet == 'undefined' ){
        $not_paid_yet = '';
    }
    $file                   = $_FILES['slip_upload'] ?? null;
    $transaction_id         = null;
    $slip_url               = '';
    $booking_note           = '';
    $booking_status         = 'approved';
    
    $non_member_info        = "";
    $waitlist_note          = ",,,".$daily_member_type . ",".$coach_option. "," .$extra_player;
    $current_admin_member_number = $_SESSION['member_number'] ?? '';
    $admin_is_limit_exempt = ((string) $current_admin_member_number === '1');

    /*
    echo "Booking type: " . $booking_type; echo '<br>';
    echo "Date: " . $date; echo '<br>';
    echo "member id: " . $member_id; echo '<br>';
    echo "daily member type : " . $daily_member_type; echo '<br>';
    echo "coach option : " . $coach_option; echo '<br>';
    echo "extra_player : " . $extra_player; echo '<br>';
    echo "coach_name : " . $coach_name; echo '<br>';
    echo "all_selected_courts : " . $all_selected_courts; echo '<br>';
    echo "all_selected_times : " . $all_selected_times; echo '<br>';
    echo "transaction_amount : " . $transaction_amount; echo '<br>';
    echo "payment_type : " . $payment_type; echo '<br>';
    echo "credit : " . $credit; echo '<br>';
    echo "file : " . $file; echo '<br>';
    echo "not_paid_yet : " . $not_paid_yet; echo '<br>';
    echo "booking_status : " . $booking_status; echo '<br>';
    echo "waitlist_note : " . $waitlist_note; echo '<br>';
    echo "booking_note : " . $booking_note; echo '<br>';
    */

    if (!$admin_is_limit_exempt) {
        lsc_enforce_admin_member_booking_limits($pdo, $date, $member_id, $daily_member_type, $times);
    }

    //Check duplicated
    $check_duplicated = 0;
    foreach ($courts as $key => $court_value) {
        //$all_bookings = $all_booking_query->fetchAll(PDO::FETCH_ASSOC);
        $court_numb         = $court_value;
        $time_val           = $times[$key];

        //Check duplicate
        $query_booking_status = 'cancelled';
        $all_booking_query = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE court = ? AND timeslot = ? AND date = ? AND NOT booking_status = ?");
        $all_booking_query->execute([$court_numb, $time_val, $date,$query_booking_status]);
        $count_booking = $all_booking_query->fetchColumn();
        $check_duplicated += $count_booking;

    }

if( $check_duplicated > 0  ){
?>
    <div class="error-message text-center text-danger">
        <span class="fs-2">
            <i class="ri-error-warning-fill"></i>
        </span>
        <br>
        <p>Looks like another member has just booked the court/time you selected.<br><br>Please select another court/time.</p>
        <hr>
        <a href="index.php" class="btn btn-primary">Try again</a>
    </div>

    <?php 
    exit();
}

    if ($payment_type === 'credit' && (float) $transaction_amount > 0) {
        $stmt_member_credit = $pdo->prepare("SELECT credit FROM members WHERE id = ? LIMIT 1");
        $stmt_member_credit->execute([$member_id]);
        $member_credit_balance = (float) $stmt_member_credit->fetchColumn();

        if ($member_credit_balance < (float) $transaction_amount) {
            ?>
            <div class="error-message text-center text-danger">
                <span class="fs-2">
                    <i class="ri-error-warning-fill"></i>
                </span>
                <br>
                <p>This member does not have enough credit to make this booking.<br>Please refill credit before booking.</p>
                <hr>
                <a href="admin-add-credit.php" class="btn btn-outline-primary">Add credit</a>
                <a href="index.php" class="btn btn-primary">Back</a>
            </div>
            <?php
            exit();
        }
    }


    // UPLOAD FILE IF TRANSACTION AMOUNT IS NOT 0 
    if( $transaction_amount > 0 &&  $file ){

        $uploadDir          = 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true); // Create the directory if it doesn't exist
        }

        //Prepare slip 
        $fileTmpPath        = $file['tmp_name'];
        $fileName           = $file['name'];
        $fileExtension      = pathinfo($fileName, PATHINFO_EXTENSION);
        $uniqueFileName     = date('Y_m_d').'_'.uniqid('slip_010_', true) . '.' . $fileExtension;
        $fileDestination    = $uploadDir . $uniqueFileName;
        $file_move_result   = move_uploaded_file($fileTmpPath, $fileDestination);
        $slip_url           = $uniqueFileName;
        
    }

    
    // CREATE TRANSACTION 
    if( $transaction_amount > 0 ){

        try {

            $transaction_title = 'Booking_' . $member_id;
            $transaction_type = "booking (member)";

            $stmt = $pdo->prepare("INSERT INTO transactions (transaction_title, member_id, non_member_id, non_member_info, transaction_amount, transaction_type, payment_type, slip_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$transaction_title, $member_id, "90001", $non_member_info, $transaction_amount, $transaction_type, $payment_type, $slip_url]);
            
            $transaction_id = $pdo->lastInsertId();

            
            //If there's extra player, create a transaction
            if($extra_player >= 1){

                $guest_transaction_title = 'Guest transaction';
                $guest_transaction_amount = $extra_player*200;
                $guest_transaction_note = 'Guest transaction';

                $stmt_guest = $pdo->prepare("INSERT INTO transactions (transaction_title, member_id, non_member_id, non_member_info, transaction_amount, transaction_type, payment_type, slip_url, transaction_note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_guest->execute([$guest_transaction_title, $member_id, "90002", $non_member_info, $guest_transaction_amount, $transaction_type, $payment_type, $slip_url, $guest_transaction_note]);

            }
            

        }catch (PDOException $e) {
            echo $e->getMessage();
        }

    }
    $transaction_message = "success";    
    

    // MAKE BOOKING

    try {
        foreach ($courts as $key => $court_value) {

            $court_numb         = $court_value;
            $time_val           = $times[$key];

            if( $time_val == '6-7pm' || $time_val == '7-8pm' || $time_val == '8-9pm' || $time_val == '9-10pm' ){
                $this_transaction_price = 280;
            }else{
                $this_transaction_price = 160;
            }


            //If waitlist
            if( $court_numb == 'waitlist'){

                $stmt = $pdo->prepare("INSERT INTO wait_list (member_id, non_member_id,  date, timeslot, member_type, waitlist_note) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$member_id, "90001", $date, $time_val, $booking_type, $waitlist_note] );

            }else{

                //Create a transaction for each booking 
                $this_transaction_title = 'Booking for date ' . format_date_to_readable_text($date) . ', court ' . $court_numb . ' at ' . $time_val;
                $transaction_type = "booking (member)";

                $stmt_ts = $pdo->prepare("INSERT INTO transactions (transaction_title, assoc_transaction_id, member_id, non_member_id, non_member_info, transaction_amount, transaction_type, payment_type, slip_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_ts->execute([$this_transaction_title, $transaction_id, $member_id, "90001", $non_member_info, $this_transaction_price, $transaction_type, $payment_type, $slip_url]);
                
                $this_transaction_id = $pdo->lastInsertId();


                $stmt = $pdo->prepare("INSERT INTO bookings (court, date, timeslot, member_id, non_member_id, booking_status, booking_type, daily_member_type, coach, coach_name, coach_extra_player, booking_note, payment, transaction_id, payment_remark, slip) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
                $stmt->execute([$court_numb, $date, $time_val, $member_id, "90001", $booking_status, $booking_type, $daily_member_type, $coach_option, $coach_name, $extra_player, $booking_note, $payment_type,  $this_transaction_id, $not_paid_yet, $slip_url]);
            }

        }

        $booking_message = "success";

    }catch (PDOException $e) {
        echo $e->getMessage();
    }

    // REDUCE CREDIT
    if( $payment_type == 'credit' && (float) $transaction_amount > 0 ){
        $stmt = $pdo->prepare("UPDATE members SET credit = credit - ? WHERE id = ? ");
        $result = $stmt->execute([$transaction_amount, $member_id]);
    }

    // SHOW MESSAGE
    if(  $booking_message == 'success'){
        lsc_log('Admin Booking Member', "Admin created booking(s) for Member ID: $member_id, Date: $date, Courts: $all_selected_courts, Timeslots: $all_selected_times. Payment: $payment_type, Amount: $transaction_amount THB.");
    ?>

    <div class="success-message text-center text-success">
        <span class="fs-2">
            <i class="ri-checkbox-circle-line"></i>
        </span>
        <br>
        <p>The booking has been made successfully</p>
        <hr>
        <a href="index.php" class="btn btn-outline-primary">Book again</a>
        <a href="admin-bookings.php" class="btn btn-primary">View all bookings</a>
    </div>

    <?php 
    }else{ ?>

    <div class="error-message text-center text-danger">
        <span class="fs-2">
            <i class="ri-error-warning-fill"></i>
        </span>
        <br>
        <p>There's something wrong with the booking. Please try again.</p>
        <hr>
        <a href="index.php" class="btn btn-primary">Try again</a>
    </div>


    <?php 
    }


}
error_reporting(-1);
?>

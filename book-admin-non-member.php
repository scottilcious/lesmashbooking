<?php 
require_once __DIR__ . '/includes/auth.php';
$lsc_me = lsc_require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require 'config.php';
    require_once 'includes/functions.php';
    include_once 'includes/booking-functions.php';

    $booking_type           = $_POST['booking_type'];
    $date                   = $_POST['date'];

    $non_member_name        = $_POST['non_member_name'];
    $daily_member_type      = $_POST['daily_member_type'];
    $coach_option           = $_POST['coach_option'];
    $extra_player           = $_POST['extra_player'];
    $coach_name             = $_POST['coach_name'];
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
    $not_paid_yet           = $_POST['not_paid_yet'];
    if( $not_paid_yet == '' || $not_paid_yet == 'undefined' ){
        $not_paid_yet = '';
    }
    $file                   = $_FILES['slip_upload'] ?? null;
    $booking_status         = 'approved';
    //echo $not_paid_yet;

    $waitlist_note          = $non_member_name. ",,,". $daily_member_type . ",".$coach_option. "," .$extra_player;
    $non_member_info        = $non_member_name;

    //Check duplicated
    $check_duplicated = 0;
    foreach ($courts as $key => $court_value) {
        //$all_bookings = $all_booking_query->fetchAll(PDO::FETCH_ASSOC);
        $court_numb         = $court_value;
        $time_val           = $times[$key];

        //Check duplicate
        $query_booking_status = 'cancelled';
        $all_booking_query = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE court = ? AND timeslot = ? AND date = ? AND NOT booking_status = ?");
        $all_booking_query->execute([$court_numb, $time_val, $date, $query_booking_status]);
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
        <a href="index.php?param_booking_type=non-member" class="btn btn-primary">Try again</a>
    </div>

    <?php 
    exit();
}


    // CREATE NON MEMBER
if( $non_member_name  != ''){

    try {
        $member_note = "Admin created";

        $stmt = $pdo->prepare("INSERT INTO non_members (guest_name, member_note) VALUES (?, ?)");
        $stmt->execute([$non_member_name, $member_note]);
        
        $member_id = $pdo->lastInsertId();

    }catch (PDOException $e) {
        echo 'created member: ';
        echo $e->getMessage();
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


    // ---- All writes below run in ONE transaction under a per-date lock (includes/booking-service.php).
    require_once 'includes/booking-service.php';
    require_once 'includes/functions.php';
    try {
        lsc_booking_begin($pdo, $date);
        // Re-check inside the lock: another request may have taken the slot since the pre-check above.
        lsc_booking_assert_slots_free($pdo, $courts, $times, $date, 'index.php?param_booking_type=non-member');
    // CREATE TRANSACTION
    if( $transaction_amount > 0 ){

        try {

            $transaction_title = 'Booking_' . $member_id;
            $transaction_type = "booking (non member)";

            $stmt = $pdo->prepare("INSERT INTO transactions (transaction_title, member_id, non_member_id, non_member_info, transaction_amount, transaction_type, payment_type, slip_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$transaction_title, "90002", $member_id, $non_member_info, $transaction_amount, $transaction_type, $payment_type, $slip_url]);
            
            $transaction_id = $pdo->lastInsertId();

            

        }catch (PDOException $e) {
            throw $e; // rolls back the whole booking
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

                $stmt = $pdo->prepare("INSERT INTO wait_list (member_id, non_member_id, date, timeslot, member_type, waitlist_note) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute(["90001", $member_id, $date, $time_val, $booking_type, $waitlist_note] );

            }else{

                //Create a transaction for each booking 
                $this_transaction_title = 'Booking for date ' . format_date_to_readable_text($date) . ', court ' . $court_numb . ' at ' . $time_val;
                $transaction_type = "booking (non member)";

                $stmt_ts = $pdo->prepare("INSERT INTO transactions (transaction_title, assoc_transaction_id, member_id, non_member_id, non_member_info, transaction_amount, transaction_type, payment_type, slip_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_ts->execute([$this_transaction_title, $transaction_id, "90002", $member_id, $non_member_info, $this_transaction_price, $transaction_type, $payment_type, $slip_url]);
                
                $this_transaction_id = $pdo->lastInsertId();

                $stmt = $pdo->prepare("INSERT INTO bookings (court, date, timeslot, member_id, non_member_id, booking_status, booking_type, daily_member_type, coach, coach_name, coach_extra_player, booking_note, payment, transaction_id, payment_remark, slip, non_member_info) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
                $stmt->execute([$court_numb, $date, $time_val, "90001", $member_id, $booking_status, $booking_type, $daily_member_type, $coach_option, $coach_name, $extra_player, $booking_note, $payment_type,  $this_transaction_id, $not_paid_yet, $slip_url, $non_member_info]);
            }

        }

        $booking_message = "success";

    }catch (PDOException $e) {
            throw $e; // rolls back the whole booking
        }

        lsc_booking_commit($pdo, $date);
    } catch (LscBookingConflict $e) {
        lsc_booking_abort($pdo, $date);
        lsc_booking_render_conflict($e);
        exit;
    } catch (Throwable $e) {
        lsc_booking_abort($pdo, $date);
        lsc_log('Booking Failed', basename('book-admin-non-member.php') . ': ' . get_class($e) . ': ' . $e->getMessage());
        lsc_booking_render_conflict(new LscBookingConflict("There's something wrong with the booking. Nothing was charged. Please try again.", 'index.php?param_booking_type=non-member'));
        exit;
    }

    // SHOW MESSAGE
    if(  $booking_message == 'success'){
        lsc_log('Admin Booking Non-Member', "Admin created booking(s) for Non-Member Guest Name: $non_member_name, Date: $date, Courts: $all_selected_courts, Timeslots: $all_selected_times. Payment: $payment_type, Amount: $transaction_amount THB.");
        ?>
    
        <div class="success-message text-center text-success">
            <span class="fs-2">
                <i class="ri-checkbox-circle-line"></i>
            </span>
            <br>
            <p>The booking has been made successfully</p>
            <hr>
            <a href="index.php?param_booking_type=non-member" class="btn btn-outline-primary">Book again</a>
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
            <a href="index.php?param_booking_type=non-member" class="btn btn-primary">Try again</a>
        </div>
    
    
        <?php 
        }
    }else{
        echo 'Please specify non member name';
    }

}
?>
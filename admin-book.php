<?php
require_once __DIR__ . '/includes/auth.php';
$lsc_me = lsc_require_admin();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require 'config.php';

    $booking_type = $_POST['booking_type'];
    /*
    $court_1 = $_POST['court_1'];
    $court_2 = $_POST['court_2'];
    $time_1 = $_POST['time_1'];
    $time_2 = $_POST['time_2'];
    */
    $all_selected_courts = $_POST['all_selected_courts'];
    $all_selected_times = $_POST['all_selected_times'];
    // treat court 
    $courts = explode(",", $all_selected_courts);
    // treat time
    $times = explode(",", $all_selected_times);

    $date = $_POST['date'];
    $member_id = $_POST['member_id'];
    $booking_status = $_POST['booking_status'];
    $coach_option = $_POST['coach_option'];
    $coach_name = $_POST['coach_name'];
    $booking_note = $_POST['booking_note'];
    if( $coach_option == 'undefined'){
        $coach_option = null;
    }

    //Default values
    $daily_member_type = 'admin';
    $payment_type = 'admin';
    $transaction_amount = 0;
    $transaction_id = 0;


//Make a booking
try {
    foreach ($courts as $key => $court_value) {

        $court_numb = $court_value;
        $time_val = $times[$key];

        if( $court_numb == 'waitlist'){

            $stmt = $pdo->prepare("INSERT INTO wait_list (member_id,date, timeslot, member_type) VALUES (?, ?, ?, ?)");
            $stmt->execute([$member_id, $date, $time_val, $booking_type] );

        }else{

            $stmt = $pdo->prepare("INSERT INTO bookings (court, date, timeslot, member_id, booking_status, booking_type, daily_member_type, coach, coach_name, booking_note, payment, transaction_id, slip) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
            $stmt->execute([$court_numb, $date, $time_val, $member_id, $booking_status, $booking_type, $daily_member_type, $coach_option, $coach_name, $booking_note, $payment_type,  $transaction_id, $slip_url ?? null]);

        }

        
    }
    /*
        $stmt = $pdo->prepare("INSERT INTO bookings (court, date, timeslot, member_id, booking_status, booking_type, daily_member_type, coach, coach_name, booking_note, payment, transaction_id, slip) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$court_1, $date, $time_1, $member_id, $booking_status, $booking_type, $daily_member_type, $coach_option, $coach_name, $booking_note, $payment_type,  $transaction_id, $slip_url ?? null]);

    if( $court_2 != '' && $time_2 != ''):
        $stmt_2 = $pdo->prepare("INSERT INTO bookings (court, date, timeslot, member_id, booking_status, booking_type, daily_member_type, coach, coach_name, booking_note, payment, transaction_id, slip) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_2->execute([$court_2, $date, $time_2, $member_id, $booking_status, $booking_type, $daily_member_type, $coach_option, $coach_name, $booking_note, $payment_type, $transaction_id, $slip_url ?? null]);
    endif;
    */

    $booking_message = "success";
} catch (PDOException $e) {
    echo $e->getMessage();
}


if(  $booking_message == 'success'){
?>
    <div class="success-message text-center text-success">
        <span class="fs-2">
            <i class="ri-checkbox-circle-line"></i>
        </span>
        <br>
        <p>Your booking has been made successfully</p>
        <hr>
        <a href="index.php" class="btn btn-outline-primary">Book again</a>
        <a href="admin-bookings.php" class="btn btn-primary">View all bookings</a>
    </div>
<?php 
}else{ 
?>

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
?>
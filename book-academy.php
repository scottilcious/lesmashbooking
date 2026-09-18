<?php 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require 'config.php';
    include_once 'includes/booking-functions.php';

    $booking_type           = $_POST['booking_type'];
    $allowed_academy_booking_types = ['junior_academy', 'adult_clinic', 'tennis_camp', 'tournament'];
    if (!in_array($booking_type, $allowed_academy_booking_types, true)) {
        die("Invalid academy booking type.");
    }

    $date                   = $_POST['date'];

    $member_id              = $_POST['member_id'];
    $non_member_info        = $_POST['non_member_info'] ?? '';
    $daily_member_type      = 'Academy';
    $all_selected_courts    = $_POST['all_selected_courts'];
    $all_selected_times     = $_POST['all_selected_times'];
    $transaction_amount     = 0;
    $coach_option           = "none";
    $extra_player           = 0;
    $payment_type           = "None";
    $booking_status         = 'approved';

    $waitlist_note          = 'academy';
    $booking_note           = 'academy booking';
    $transaction_id         = "90001";
    $slip_url               = '';
    $coach_name             = '';
    $non_member_info        = '';

    /* MAKE BOOKING */
    $courts             = explode(",", $all_selected_courts);
    $times              = explode(",", $all_selected_times);

    try {
        foreach ($courts as $key => $court_value) {

            $court_numb         = $court_value;
            $time_val           = $times[$key];

            //If waitlist
            if( $court_numb == 'waitlist'){

                $stmt = $pdo->prepare("INSERT INTO wait_list (member_id, non_member_id, date, timeslot, member_type, waitlist_note) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$member_id, "90001", $date, $time_val, $booking_type, $waitlist_note] );

            }else{

                $stmt = $pdo->prepare("INSERT INTO bookings (court, date, timeslot, member_id, non_member_id, booking_status, booking_type, daily_member_type, coach, coach_name, coach_extra_player, booking_note, payment, transaction_id, slip, non_member_info) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
                $stmt->execute([$court_numb, $date, $time_val, $member_id, "90001", $booking_status, $booking_type, $daily_member_type, $coach_option, $coach_name, $extra_player, $booking_note, $payment_type,  $transaction_id, $slip_url, $non_member_info]);
            }

        }

        $booking_message = "success";

    }catch (PDOException $e) {
        echo $e->getMessage();
    }


    /* SHOW MESSAGE */
    if( $booking_message == 'success'){
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
?>

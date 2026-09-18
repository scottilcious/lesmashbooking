<?php
session_start();
require 'config.php';
require_once 'includes/functions.php';
require_once 'includes/member-functions.php';
require_once 'includes/booking-functions.php';

if (!isset($_SESSION["user_id"])) {
    die("Unauthorized access.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["booking_id"])) {

    include 'booking_functions/dynamic-waitlist.php';

    $booking_id = $_POST["booking_id"];
    $bookingType = $_POST["bookingType"];
    $user_id = $_POST["userId"];

    $isNonMember = ($bookingType === 'non-member' || $bookingType === 'non_member');

    //Get this booking info 
    $bk_stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
    $bk_stmt->execute([$booking_id]);
    $booking = $bk_stmt->fetchAll(PDO::FETCH_ASSOC);
    $this_booking_transaction_id = $booking[0]['transaction_id'];
    $this_booking_id = $booking[0]['id'];
    $this_booking_date = $booking[0]['date'];
    $this_booking_court = $booking[0]['court'];
    $this_booking_timeslot = $booking[0]['timeslot'];
    $this_booking_extra_player = $booking[0]['coach_extra_player'];
    $formatted_date = date_create($this_booking_date);
    $readable_date = date_format($formatted_date,"d F, Y");
    $is_refund_eligible = lsc_is_booking_refund_eligible($this_booking_date, $this_booking_timeslot);

    //Get the transaction amount 
    $transaction_id = $booking[0]['transaction_id'];                                
    $ts_stmt = $pdo->prepare("SELECT * FROM transactions WHERE transaction_id = ?");
    $ts_stmt->execute([$this_booking_transaction_id]);
    $transaction = $ts_stmt->fetchAll(PDO::FETCH_ASSOC);
    $transaction_amount = !empty($transaction) ? $transaction[0]['transaction_amount'] : 0;
    $transaction_note = "cancelled by member";
    $guest_refund_amount = max(0, (int) $this_booking_extra_player) * 200;

    //Cancel booking
    if ($isNonMember) {
        $stmt = $pdo->prepare("UPDATE bookings SET booking_status = 'cancelled', booking_note = ? WHERE id = ? AND non_member_id = ?");
        $result = $stmt->execute([$transaction_note, $booking_id, $user_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE bookings SET booking_status = 'cancelled', booking_note = ? WHERE id = ? AND member_id = ?");
        $result = $stmt->execute([$transaction_note, $booking_id, $user_id]);
    }

    //Create cancellation transaction record 
    $cancelled_transaction_title = 'Cancelled by member for booking ' . $readable_date . ', Court: ' . $this_booking_court . ', Time: ' . $this_booking_timeslot;
    
    // Determine refund eligibility and transaction values
    $this_booking_payment = isset($booking[0]['payment']) ? strtolower(trim($booking[0]['payment'])) : '';
    $eligible_payment = ($this_booking_payment === 'credit' || $this_booking_payment === 'qr');
    
    if ($is_refund_eligible && !$isNonMember) {
        if ($eligible_payment) {
            $transaction_type = 'cancelled';
            $transaction_note = 'cancelled by member (refunded)';
            $this_transaction_price = $transaction_amount;
        } else {
            $transaction_type = 'cancelled';
            $transaction_note = 'cancelled by member';
            $this_transaction_price = 0;
        }
    } elseif ($is_refund_eligible && $isNonMember) {
        $transaction_type = 'cancelled';
        $transaction_note = 'cancelled by non-member (contact admin for refund)';
        $this_transaction_price = 0;
    } else {
        $transaction_type = 'cancelled';
        $transaction_note = $isNonMember ? 'cancelled by non-member' : 'cancelled by member';
        $this_transaction_price = 0;
    }
    
    $non_member_info = !empty($booking[0]['non_member_info']) ? $booking[0]['non_member_info'] : 'N/A';
    $payment_type = 'credit';
    $slip_url = '';

    if ($isNonMember) {
        $stmt_ts = $pdo->prepare("INSERT INTO transactions (transaction_title, member_id, non_member_id, non_member_info, transaction_amount, transaction_type, payment_type, slip_url, transaction_note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_ts->execute([$cancelled_transaction_title, "90002", $user_id, $non_member_info, $this_transaction_price, $transaction_type, $payment_type, $slip_url, $transaction_note]);
    } else {
        $stmt_ts = $pdo->prepare("INSERT INTO transactions (transaction_title, member_id, non_member_id, non_member_info, transaction_amount, transaction_type, payment_type, slip_url, transaction_note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_ts->execute([$cancelled_transaction_title, $user_id, "90002", $non_member_info, $this_transaction_price, $transaction_type, $payment_type, $slip_url, $transaction_note]);
    }


    //Prepare values for waitlist
    $notify_court    = $this_booking_court;
    $notify_date     = $this_booking_date;
    $notify_timeslot = $this_booking_timeslot;
    $formattedNotifyDate = date('F j, Y', strtotime($notify_date));

    //Check if past grace period 
    $formatted_time = get_timeslot_for_waitlist($notify_timeslot);
    $diffSeconds = getTimeDifferenceFromNow($notify_date, $formatted_time);

    //Finding waitlist with member phone number
    $latest_waitlist = getLatestWaitlistEntryWithPhone($pdo, $notify_date, $notify_timeslot);
    if ($latest_waitlist && $diffSeconds > 2 * 3600) {
        $queried_waitlist_id = $latest_waitlist['wait_list_id'];
        $queried_waitlist_type = $latest_waitlist['member_type'];
        $queried_waitlist_member_id = $latest_waitlist['member_db_id'];
        $queried_waitlist_member_credit = $latest_waitlist['member_credit'];
        $queried_waitlist_member_phone = $latest_waitlist['member_phone'];

        //Prep variables for a booking
        $waitlist_id = $queried_waitlist_id;
        $passed_date = $notify_date;
        $passed_timeslot = $notify_timeslot;
        $free_court = $notify_court;

        //Check if the booking already exists then update waitlist status
        if( waitlistBookingExists($pdo, $free_court, $passed_date, $passed_timeslot)){
            $updatedWaitlistStatus = 'expired';
            $booking_id = NULL;
            updateWaitlistStatus($pdo, $waitlist_id, $updatedWaitlistStatus, $free_court, $booking_id);
        }

        //Create transaction
        $created_transaction_id = create_waitlist_booking_transaction($pdo, $queried_waitlist_member_id, $free_court, $passed_timeslot, $passed_date);

        //Create assoc transaction
        $created_assoc_transaction_id = create_assoc_transaction($pdo, $created_transaction_id, $queried_waitlist_member_id, $free_court, $passed_timeslot, $passed_date);

        //Create a booking
        $daily_member_type = waitlist_get_member_type($pdo, $queried_waitlist_member_id);
        $booking_fee = get_pricing_rule_by_timeslot($passed_timeslot);

        $payment_type = 'cash';
        $payment_remark = 'Not paid yet';

        $bookingData = [
            'court'              => $free_court,
            'date'               => $passed_date,
            'timeslot'           => $passed_timeslot,
            'member_id'          => $queried_waitlist_member_id,
            'non_member_id'      => '90002',
            'booking_status'     => 'approved',
            'booking_type'       => 'member booking',
            'daily_member_type'  => $daily_member_type,
            'payment'            => $payment_type,
            'transaction_id'     => $created_assoc_transaction_id,
            'payment_remark'     => $payment_remark,
            'slip'               => null,
            'coach'              => null,                 // 1/0 or coach id depending on schema
            'coach_extra_player' => null,
            'coach_name'         => null,
            'booking_note'       => 'waitlist-reserved',
            'non_member_info'    => null,              // or JSON/text if walk-in
        ];

        $bookingId = waitlistCreateBooking($pdo, $bookingData);

        //Update waitlist status after making a booking 
        if( $bookingId){
            $waitlist_status = 'pending';
            updateWaitlistStatus($pdo, $waitlist_id, $waitlist_status, $free_court, $bookingId);

            //Send SMS
            $sms_msg = "Our court " . $notify_court . " on " . $formattedNotifyDate . " at " . $notify_timeslot . " from your waitlist is available now. Please login to your account to confirm the booking." ;

            //echo $sms_msg;

            send_sms_mkt($queried_waitlist_member_phone, $sms_msg);
        }

    
    } 

    //Refund Credit 
    $total_refund_amount = 0;
    $refunded_to_credit = false;
    if ($is_refund_eligible && !$isNonMember && $eligible_payment) {
        $total_refund_amount = $transaction_amount + $guest_refund_amount;
        $credit_stmt = $pdo->prepare("UPDATE members SET credit = credit + ? WHERE id = ?");
        $credit_stmt->execute([$total_refund_amount, $user_id]);
        $refunded_to_credit = true;

        //Guest extra player
        if ($guest_refund_amount > 0) {
            $guest_transaction_title = 'Refunded guest transaction';
            $guest_transaction_amount = $guest_refund_amount;
            $guest_transaction_note = 'Refunded guest transaction';

            $stmt_guest = $pdo->prepare("INSERT INTO transactions (transaction_title, member_id, non_member_id, non_member_info, transaction_amount, transaction_type, payment_type, slip_url, transaction_note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_guest->execute([$guest_transaction_title, $user_id, "90002", $non_member_info, $guest_transaction_amount, $transaction_type, $payment_type, $slip_url, $guest_transaction_note]);
        }
    }

    //Send LINE 
    if ($result) {
        $log_desc = "Booking ID: $booking_id, Court: $this_booking_court, Time: $this_booking_timeslot, Date: $readable_date. Payment: $this_booking_payment.";
        if ($refunded_to_credit) {
            $log_desc .= " Refunded $total_refund_amount THB to member credit.";
        }
        lsc_log('Cancel Booking', $log_desc);

        $prefix = $isNonMember ? "[Non-Member]" : "[Member]";
        $booking_line_message = $prefix . " มีการยกเลิกจอง Booking\n วันที่: " . $readable_date . "\nCourt: " . $this_booking_court . " เวลา: " . $this_booking_timeslot . "\nดูที่ https://booking.lesmashclub.com/admin-view-booking.php?booking_id=" . $this_booking_id ;
        
        $accessToken = LINE_BROADCAST_TOKEN;
        lsc_send_line_broadcast($booking_line_message, $accessToken);

        ?>

        <div class="success-message text-center text-success">
            <span class="fs-2">
                <i class="ri-checkbox-circle-line"></i>
            </span>
            <br>
            <p>This booking has been cancelled successfully</p>
            <?php if ($refunded_to_credit): ?>
                <p class="text-muted fs-6">A refund of <?= number_format($total_refund_amount, 0) ?> THB has been added to your credit.</p>
            <?php elseif ($isNonMember && $is_refund_eligible): ?>
                <p class="text-muted fs-6">Since you are not a member, your refund cannot be automatically credited. Please contact the admin to arrange your refund.</p>
            <?php endif; ?>

            <button type="button" class="btn btn-primary" onclick="location.reload()">Done</button>
        </div>

        <?php 

    }else{
        ?>

        <div class="error-message text-center text-danger">
            <span class="fs-2">
                <i class="ri-error-warning-fill"></i>
            </span>
            <br>
            <p>There's something wrong with the cancellation. Please try again.</p>

            <button type="button" class="btn btn-primary" onclick="location.reload()">Try again</button>
        </div>

        <?php 
    }


}
?>

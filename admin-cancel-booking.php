<?php
require_once __DIR__ . '/includes/auth.php';
$lsc_me = lsc_require_admin();
require 'config.php';
require_once 'includes/functions.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_once 'includes/waitlist-service.php';

    $booking_id = $_POST["booking_id"];
    $credit_refund = $_POST["credit_refund"];
    $cancel_special = $_POST["cancel_special"];
    $cancel_half = $_POST["cancel_half"];
    $transaction_id = $_POST["transaction_id"];
    $member_id = $_POST["member_id"];
    $non_member_id = $_POST["non_member_id"];

    //Get Transaction info to get transaction amount
    $stmt_ts = $pdo->prepare("SELECT * FROM transactions WHERE transaction_id = ?");
    $stmt_ts->execute([$transaction_id]);
    $transaction_data = $stmt_ts->fetchAll(PDO::FETCH_ASSOC);
    $transaction_amount = $transaction_data[0]['transaction_amount'];

    //Get booking information 
    $stmt_bk = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
    $stmt_bk->execute([$booking_id]);
    $booking_data = $stmt_bk->fetchAll(PDO::FETCH_ASSOC);
    $booking_date = $booking_data[0]['date'];
    $booking_date_create = date_create($booking_date);
    $booking_date_format = date_format($booking_date_create,"d F, Y");
    $booking_court = $booking_data[0]['court'];
    $booking_timeslot = $booking_data[0]['timeslot'];
    $booking_extra_player = $booking_data[0]['coach_extra_player'];
    $booking_payment_remark = $booking_data[0]['payment_remark'];
    $guest_refund_amount = max(0, (int) $booking_extra_player) * 200;

    //Booking cancellation note
    if( $cancel_special == 'true' && $cancel_half == 'false' ){
        $cancellation_note = 'admin-cancelled-rain';
    }elseif( $cancel_special == 'true' && $cancel_half == 'true'  ){
        $cancellation_note = 'admin-cancelled-rain';
    }else{
        $cancellation_note = 'admin-cancelled';
    }

    //Cancel booking 
    try {

        $stmt = $pdo->prepare("UPDATE bookings SET booking_status = 'cancelled', booking_note = ? WHERE id = ?");
        $result = $stmt->execute([$cancellation_note, $booking_id]);

    }catch (PDOException $e) {
        echo $e->getMessage();
    }


    //Create new transaction with updated information and note if cancel due to rain 
    if( $cancel_special == 'true' && $cancel_half == 'false' ){

        $this_transaction_title = 'Cancelled booking for ' . $booking_date_format . ', Court: ' . $booking_court . ', Time: ' . $booking_timeslot;
        $transaction_type = 'cancelled-rain';
        $transaction_note = 'cancelled by admin - due to rain/pollution';
        $non_member_info = 'N/A';
        $this_transaction_price = $transaction_amount;
        $payment_type = 'credit';
        $slip_url = '';

        $stmt_ts = $pdo->prepare("INSERT INTO transactions (transaction_title, member_id, non_member_id, non_member_info, transaction_amount, transaction_type, payment_type, slip_url, transaction_note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt_ts->execute([$this_transaction_title, $member_id, $non_member_id, $non_member_info, $this_transaction_price, $transaction_type, $payment_type, $slip_url, $transaction_note]);

        //Guest extra player
        if($booking_extra_player >= 1){

            $guest_transaction_title = 'Refunded guest transaction';
            $guest_transaction_amount = $booking_extra_player*200;
            $guest_transaction_note = 'Refunded guest transaction';

            $stmt_guest = $pdo->prepare("INSERT INTO transactions (transaction_title, member_id, non_member_id, non_member_info, transaction_amount, transaction_type, payment_type, slip_url, transaction_note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_guest->execute([$guest_transaction_title, $member_id, $non_member_id, $non_member_info, $guest_transaction_amount, $transaction_type, $payment_type, $slip_url, $guest_transaction_note]);

        }

    }elseif( $cancel_special == 'true' && $cancel_half == 'true'  ){

        $this_transaction_title = 'Cancelled booking for ' . $booking_date_format . ', Court: ' . $booking_court . ', Time: ' . $booking_timeslot . ' (Half refunded)';
        $transaction_type = 'cancelled-rain-half';
        $transaction_note = 'cancelled by admin - due to rain/pollution (half refunded)';
        $non_member_info = 'N/A';
        $this_transaction_price = $transaction_amount/2;
        $payment_type = 'credit';
        $slip_url = '';

        $stmt_ts = $pdo->prepare("INSERT INTO transactions (transaction_title, member_id, non_member_id, non_member_info, transaction_amount, transaction_type, payment_type, slip_url, transaction_note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt_ts->execute([$this_transaction_title, $member_id, $non_member_id, $non_member_info, $this_transaction_price, $transaction_type, $payment_type, $slip_url, $transaction_note]);


        //Guest extra player
        if($booking_extra_player >= 1){

            $guest_transaction_title = 'Refunded guest transaction';
            $guest_transaction_amount = ($booking_extra_player*200)/2;
            $guest_transaction_note = 'Refunded guest transaction';

            $stmt_guest = $pdo->prepare("INSERT INTO transactions (transaction_title, member_id, non_member_id, non_member_info, transaction_amount, transaction_type, payment_type, slip_url, transaction_note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_guest->execute([$guest_transaction_title, $member_id, $non_member_id, $non_member_info, $guest_transaction_amount, $transaction_type, $payment_type, $slip_url, $guest_transaction_note]);

        }

    }else{
        $this_transaction_title = 'Cancelled booking for ' . $booking_date_format . ', Court: ' . $booking_court . ', Time: ' . $booking_timeslot . ' (No refund)';
        $transaction_type = 'cancelled';
        $transaction_note = 'cancelled by admin';
        $non_member_info = 'N/A';
        $this_transaction_price = 0;
        $payment_type = 'credit';
        $slip_url = '';

        $stmt_ts = $pdo->prepare("INSERT INTO transactions (transaction_title, member_id, non_member_id, non_member_info, transaction_amount, transaction_type, payment_type, slip_url, transaction_note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt_ts->execute([$this_transaction_title, $member_id, $non_member_id, $non_member_info, $this_transaction_price, $transaction_type, $payment_type, $slip_url, $transaction_note]);
    
    }

    //Offer the freed court to the first member on the waitlist. Rain/pollution cancellations do not free a playable court.
    if ($result && $cancellation_note == 'admin-cancelled') {
        lsc_waitlist_offer_slot($pdo, (int) $booking_court, $booking_date, $booking_timeslot);
    }

    //Refund Credit
    if( ( $credit_refund == 'true' || $cancel_special == 'true' ) && !empty($member_id) && $member_id != "0" && $member_id != "90001" && $member_id != "90002" && $booking_payment_remark != 'Not paid yet' ){

        //Create transaction records
        if( $cancel_half == 'true'){
            $transaction_amount = $transaction_amount/2;
            $guest_refund_amount = $guest_refund_amount/2;
        }

        $total_refund_amount = $transaction_amount + $guest_refund_amount;

        try {

            $stmt_mb = $pdo->prepare("UPDATE members SET credit= credit+? WHERE id = ?");
            $result_mb = $stmt_mb->execute([$total_refund_amount, $member_id]);

        }catch (PDOException $e) {
            echo $e->getMessage();
        }

    }




 
?>
    <?php if( $result ): 
        $log_desc = "Booking ID: $booking_id, Court: $booking_court, Time: $booking_timeslot, Date: $booking_date_format. Note: $cancellation_note.";
        if (isset($total_refund_amount)) {
            $log_desc .= " Refunded {$total_refund_amount} THB to member credit.";
        }
        lsc_log('Admin Cancel Booking', $log_desc);
    ?>

        <div class="success-message text-center text-success">
            <span class="fs-2">
                <i class="ri-checkbox-circle-line"></i>
            </span>
            <br>
            <p>Booking has been cancelled successfully</p>
            <?php if( ( $credit_refund == 'true' || $cancel_special == 'true' ) && $member_id != "90001" && $member_id != "90002" ){ ?>
                <p>Credit has already been refunded to the member.</p>
            <?php } ?>

            <hr>
            <a href="admin-bookings.php" class="btn btn-outline-primary">View all bookings</a>
            <a href="admin-view-booking.php?booking_id=<?php echo $booking_id; ?>" class="btn btn-primary">View edited booking</a>
            
        </div>

    <?php else: ?>

        <div class="error-message text-center text-danger">
            <span class="fs-2">
                <i class="ri-error-warning-fill"></i>
            </span>
            <br>
            <p>There's something wrong with cancelling booking. Please try again.</p>
            <hr>
            <a href="admin-view-booking.php?booking_id=<?php echo $booking_id; ?>" class="btn btn-primary">Try again</a>
        </div>

    <?php endif; ?>

<?php 
}
?>

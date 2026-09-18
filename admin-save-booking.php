<?php
require_once __DIR__ . '/includes/auth.php';
$lsc_me = lsc_require_admin();
require 'config.php';
require_once 'includes/functions.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $booking_id = $_POST["booking_id"];
    $court = $_POST["court"];
    $date = $_POST["date"];
    $timeslot = $_POST["timeslot"];
    $booking_status = $_POST["booking_status"];
    $payment = $_POST['payment'];
    $member_id = $_POST['member_id'];
    $coach = $_POST['coach'];
    $coach_name = $_POST['coach_name'];
    $coach_extra_player = $_POST['coach_extra_player'];
    $booking_note = $_POST['booking_note'];
    $credit = $_POST['credit'];
    $credit_refund = $_POST['credit_refund'];

     // Ensure the booking belongs to the logged-in user
     $stmt = $pdo->prepare("UPDATE bookings SET 
     court= ?, date = ?, timeslot = ?, booking_status = ?, coach = ?, coach_name = ?, coach_extra_player = ?, booking_note = ?
     WHERE id = ?");
     $result = $stmt->execute([$court, $date, $timeslot, $booking_status, $coach, $coach_name, $coach_extra_player, $booking_note, $booking_id]);

     if( $booking_status == 'cancelled' && $credit_refund == 'false' ){
        $stmt = $pdo->prepare("UPDATE members SET credit= credit+? WHERE id = ?");
        $result = $stmt->execute([$credit, $member_id]);
     }
 
?>
    <?php if( $result ): 
        lsc_log('Admin Save Booking', "Updated booking ID: $booking_id, Court: $court, Date: $date, Timeslot: $timeslot, Status: $booking_status, Coach: $coach_name, Notes: $booking_note.");
    ?>

        <div class="success-message text-center text-success">
            <span class="fs-2">
                <i class="ri-checkbox-circle-line"></i>
            </span>
            <br>
            <p>Booking has been saved successfully</p>
            <a href="admin-bookings.php" class="btn btn-outline-primary">View all bookings</a>
            <a href="admin-view-booking.php?booking_id=<?php echo $booking_id; ?>" class="btn btn-primary">View edited booking</a>
            
        </div>

    <?php else: ?>

        <div class="error-message text-center text-danger">
            <span class="fs-2">
                <i class="ri-error-warning-fill"></i>
            </span>
            <br>
            <p>There's something wrong with the booking. Please try again.</p>
            <hr>
            <a href="admin-view-booking.php?booking_id=<?php echo $booking_id; ?>" class="btn btn-primary">Try again</a>
        </div>

    <?php endif; ?>

<?php 
}
?>
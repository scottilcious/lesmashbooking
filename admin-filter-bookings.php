<?php 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require 'config.php';
    $date = $_POST['date'];

    $times = [
        "6-7am", "7-8am", "8-9am", "9-10am", "10-11am", "11am-12pm", "12-1pm",
        "1-2pm", "2-3pm", "3-4pm", "4-5pm", "5-6pm", "6-7pm", "7-8pm", "8-9pm", "9-10pm"
    ];

    foreach ($times as $key => $time) {

        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE date = ? AND timeslot = ?");
        $stmt->execute([$date, $time]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        $member_id = $booking['member_id'];
        $booking_type = ($booking['booking_type'] == 'academy') ? "academy" : "normal";

        $member_stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
        $member_stmt->execute([$member_id]);

        $this_member = $member_stmt->fetch(PDO::FETCH_ASSOC);
        $member_number = $this_member['member_number'];

?>
<tr>
    <td class="timeslot-column table-dark fw-bold"><?= $time ?></td>
    <td class="time-slot-court <?= ($booking && $booking['court'] == 1 ) ? 'court-time-booked'.' '.$booking_type  : 'available-court-time'; ?>">
        <div class="court-time-info court-1-<?= $time ?>" id="court1Time<?= $time ?>">
            <?php if ($booking && $booking['court'] == "1" ): ?>
                <?= ($booking['booking_type'] == 'academy' )? "Academy booking" : '<a class="color-white" href="admin-view-booking.php?booking_id='.$booking['id'].'" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="View booking">'. $member_number . '</a>' ?>
            <?php else: ?> 
                Available
            <?php endif; ?>
        </div>
    </td>
    <td class="time-slot-court <?= ($booking && $booking['court'] == 2 ) ? 'court-time-booked'.' '.$booking_type  : 'available-court-time'; ?>">
        <div class="court-time-info court-2-<?= $time ?>" id="court1Time<?= $time ?>">
            <?php if ($booking && $booking['court'] == "2" ): ?>
                <?= ($booking['booking_type'] == 'academy' )? "Academy booking" : '<a class="color-white" href="admin-view-booking.php?booking_id='.$booking['id'].'" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="View booking">'. $member_number . '</a>' ?>
            <?php else: ?> 
                Available
            <?php endif; ?>
        </div>
    </td>
    <td class="time-slot-court <?= ($booking && $booking['court'] == 3 ) ? 'court-time-booked'.' '.$booking_type  : 'available-court-time'; ?>">
        <div class="court-time-info court-3-<?= $time ?>" id="court1Time<?= $time ?>">
            <?php if ($booking && $booking['court'] == "3" ): ?>
                <?= ($booking['booking_type'] == 'academy' )? "Academy booking" : '<a class="color-white" href="admin-view-booking.php?booking_id='.$booking['id'].'" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="View booking">'. $member_number . '</a>' ?>
            <?php else: ?> 
                Available
            <?php endif; ?>
        </div>
    </td>
    <td class="time-slot-court <?= ($booking && $booking['court'] == 4 ) ? 'court-time-booked'.' '.$booking_type  : 'available-court-time'; ?>">
        <div class="court-time-info court-4-<?= $time ?>" id="court1Time<?= $time ?>">
            <?php if ($booking && $booking['court'] == "4" ): ?>
                <?= ($booking['booking_type'] == 'academy' )? "Academy booking" : '<a class="color-white" href="admin-view-booking.php?booking_id='.$booking['id'].'" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="View booking">'. $member_number . '</a>' ?>
            <?php else: ?> 
                Available
            <?php endif; ?>
        </div>
    </td>
    <td class="time-slot-court <?= ($booking && $booking['court'] == 5 ) ? 'court-time-booked'.' '.$booking_type  : 'available-court-time'; ?>">
        <div class="court-time-info court-5-<?= $time ?>" id="court1Time<?= $time ?>">
            <?php if ($booking && $booking['court'] == "5" ): ?>
                <?= ($booking['booking_type'] == 'academy' )? "Academy booking" : '<a class="color-white" href="admin-view-booking.php?booking_id='.$booking['id'].'" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="View booking">'. $member_number . '</a>' ?>
            <?php else: ?> 
                Available
            <?php endif; ?>
        </div>
    </td>
    <td class="time-slot-court <?= ($booking && $booking['court'] == 6 ) ? 'court-time-booked'.' '.$booking_type  : 'available-court-time'; ?>">
        <div class="court-time-info court-6-<?= $time ?>" id="court1Time<?= $time ?>">
            <?php if ($booking && $booking['court'] == "6" ): ?>
                <?= ($booking['booking_type'] == 'academy' )? "Academy booking" : '<a class="color-white" href="admin-view-booking.php?booking_id='.$booking['id'].'" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="View booking">'. $member_number . '</a>' ?>
            <?php else: ?> 
                Available
            <?php endif; ?>
        </div>
    </td>
    <td class="time-slot-court <?= ($booking && $booking['court'] == 7 ) ? 'court-time-booked'.' '.$booking_type  : 'available-court-time'; ?>">
        <div class="court-time-info court-7-<?= $time ?>" id="court1Time<?= $time ?>">
            <?php if ($booking && $booking['court'] == "7" ): ?>
                <?= ($booking['booking_type'] == 'academy' )? "Academy booking" : '<a class="color-white" href="admin-view-booking.php?booking_id='.$booking['id'].'" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="View booking">'. $member_number . '</a>' ?>
            <?php else: ?> 
                Available
            <?php endif; ?>
        </div>
    </td>
</tr>

<?php 
    }

}
?>
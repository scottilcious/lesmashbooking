<?php
require_once __DIR__ . '/includes/auth.php';
$lsc_me = lsc_require_admin();
require 'config.php';
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $booking_id         = (int) ($_POST["booking_id"] ?? 0);
    $court              = (int) ($_POST["court"] ?? 0);
    $date               = (string) ($_POST["date"] ?? '');
    $timeslot           = (string) ($_POST["timeslot"] ?? '');
    $booking_status     = (string) ($_POST["booking_status"] ?? '');
    $coach              = $_POST['coach'] ?? null;
    $coach_name         = $_POST['coach_name'] ?? null;
    $coach_extra_player = (int) ($_POST['coach_extra_player'] ?? 0);
    $booking_note       = $_POST['booking_note'] ?? null;

    $error = '';

    $st = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
    $st->execute([$booking_id]);
    $current = $st->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        $error = 'Booking not found.';
    } elseif ($booking_status === 'cancelled' && $current['booking_status'] !== 'cancelled') {
        // Cancelling has money and waitlist consequences; only the Cancel buttons handle those.
        $error = 'To cancel a booking use the "Cancel this booking" or "Cancel due to Rain/Pollution" buttons, so refunds and the waitlist are handled.';
    } elseif (!in_array($booking_status, ['pending', 'approved', 'cancelled', 'not_paid'], true)) {
        $error = 'Unknown booking status.';
    } elseif (!DateTime::createFromFormat('Y-m-d', $date) || $court < 1 || $court > 7 || $timeslot === '') {
        $error = 'Court, date or timeslot is invalid.';
    } else {
        $moved = ($court != $current['court'] || $date != $current['date'] || $timeslot != $current['timeslot']);
        if ($moved && $booking_status !== 'cancelled') {
            $chk = $pdo->prepare("SELECT id FROM bookings WHERE court = ? AND date = ? AND timeslot = ? AND booking_status <> 'cancelled' AND id <> ? LIMIT 1");
            $chk->execute([$court, $date, $timeslot, $booking_id]);
            if ($chk->fetch()) {
                $error = "Court $court at $timeslot on $date is already booked.";
            }
        }
    }

    $result = false;
    if ($error === '') {
        $stmt = $pdo->prepare("UPDATE bookings SET court = ?, date = ?, timeslot = ?, booking_status = ?, coach = ?, coach_name = ?, coach_extra_player = ?, booking_note = ? WHERE id = ?");
        $result = $stmt->execute([$court, $date, $timeslot, $booking_status, $coach, $coach_name, $coach_extra_player, $booking_note, $booking_id]);
    }

    if ($result) {
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
        <?php
    } else {
        http_response_code(409);
        ?>
        <div class="error-message text-center text-danger">
            <span class="fs-2">
                <i class="ri-error-warning-fill"></i>
            </span>
            <br>
            <p><?= htmlspecialchars($error ?: 'There is something wrong with saving this booking. Please try again.') ?></p>
            <hr>
            <a href="admin-view-booking.php?booking_id=<?php echo $booking_id; ?>" class="btn btn-primary">Back to booking</a>
        </div>
        <?php
    }
}

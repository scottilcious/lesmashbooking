<?php
ob_start(); // allows redirects after the header has been rendered
// Database connection
require 'config.php';
?>

<!-- index.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waitlist - Court Booking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css"rel="stylesheet" />
    <link rel="stylesheet" href="style.css">
    <link href="bs-overwrite.css" rel="stylesheet">
</head>
<body class="page-id-2">
    <?php include "menu.php"; 
    include_once 'includes/member-functions.php';
    include_once 'includes/booking-functions.php';


    // Fetch user waitlist
    if( $memberType == 'non-member'){
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM wait_list WHERE non_member_id = ? ORDER BY wait_list_id DESC");
            $stmt->execute([$userId]);
            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }catch (PDOException $e) {
            echo $e->getMessage();
        }

    }else{

        try {
            $stmt = $pdo->prepare("SELECT * FROM wait_list WHERE member_id = ? ORDER BY wait_list_id DESC");
            $stmt->execute([$userId]);
            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }catch (PDOException $e) {
            echo $e->getMessage();
        }
    }

    
    
    ?>
    <div class="banner">
        <div class="container">
            <h1>Waitlist</h1>
        </div>
    </div>

    <?php
    require_once 'includes/waitlist-service.php';

    // Lazy sweep: expire stale offers (and offer the court to the next member) before showing the list.
    lsc_waitlist_expire_stale($pdo);

    $waitlist_result  = $_GET['waitlist_result'] ?? '';
    $waitlist_action  = $_GET['waitlist_action'] ?? '';   // confirm | decline
    $waitlist_id      = (int) ($_GET['waitlist_id'] ?? 0);
    $waitlist_notice  = null; // ['type' => bootstrap alert type, 'title' => ..., 'body' => html, 'links' => [[label, href, class]]]
    $is_guest_user    = ($memberType == 'non-member');
    $actor            = $is_guest_user
        ? ['non_member_id' => (int) $userId, 'is_admin' => false]
        : ['member_id' => (int) $userId, 'is_admin' => false];

    if ($waitlist_action === 'confirm' && $waitlist_id > 0) {
        if (!lsc_is_booking_window_open_for_member($memberType)) {
            $waitlist_notice = ['type' => 'warning', 'title' => 'Booking is closed right now',
                'body' => 'Please come back after 6:00 am to confirm this waitlist booking.', 'links' => []];
        } else {
            $r = lsc_waitlist_confirm($pdo, $waitlist_id, $actor);
            if ($r['ok']) {
                ob_end_clean();
                header('Location: waitlist.php?waitlist_result=confirmed&amount=' . (int) $r['amount']);
                exit;
            }
            switch ($r['reason']) {
                case 'insufficient_credit':
                    $waitlist_notice = ['type' => 'warning', 'title' => 'Not enough credit to confirm this booking',
                        'body' => 'This booking costs <b>' . number_format($r['required']) . ' THB</b> and your credit is <b>' . number_format($r['credit']) . ' THB</b>.<br>Please refill credit, then confirm again. The offer stays open for 2 hours after the SMS was sent.',
                        'links' => [['Add Credit', 'add-credit.php', 'btn-primary']]];
                    break;
                case 'expired':
                    $waitlist_notice = ['type' => 'danger', 'title' => 'This offer has expired',
                        'body' => 'The 2 hour confirmation window has passed or the session is too close to start. No credit has been deducted.<br>The court has been offered to the next member on the waitlist.',
                        'links' => [['Make a booking', 'index.php', 'btn-primary']]];
                    break;
                case 'forbidden':
                    $waitlist_notice = ['type' => 'danger', 'title' => 'This waitlist entry does not belong to your account', 'body' => '', 'links' => []];
                    break;
                case 'admin_only':
                    $waitlist_notice = ['type' => 'info', 'title' => 'Our staff will confirm this booking for you',
                        'body' => 'Guest waitlist bookings are confirmed by the club after payment. Please contact the reception if you have not heard from us.', 'links' => []];
                    break;
                default:
                    $waitlist_notice = ['type' => 'danger', 'title' => 'This offer is no longer open',
                        'body' => 'Its current status is <b>' . htmlspecialchars((string) ($r['status'] ?? $r['reason'])) . '</b>.', 'links' => []];
            }
        }
    } elseif ($waitlist_action === 'decline' && $waitlist_id > 0) {
        $r = lsc_waitlist_decline($pdo, $waitlist_id, $actor, 'declined');
        if ($r['ok']) {
            ob_end_clean();
            header('Location: waitlist.php?waitlist_result=declined');
            exit;
        }
        $waitlist_notice = ['type' => 'danger', 'title' => 'Could not decline this offer',
            'body' => 'Its current status is <b>' . htmlspecialchars((string) ($r['status'] ?? $r['reason'])) . '</b>.', 'links' => []];
    }

    // Re-read the list after the sweep / action
    $id_column = ($memberType == 'non-member') ? 'non_member_id' : 'member_id';
    $stmt = $pdo->prepare("SELECT * FROM wait_list WHERE $id_column = ? ORDER BY wait_list_id DESC");
    $stmt->execute([$userId]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <?php if ($waitlist_notice): ?>
        <div class="container mt-3">
            <div class="alert alert-<?= $waitlist_notice['type'] ?>" role="alert">
                <h2><?= $waitlist_notice['title'] ?></h2>
                <?= $waitlist_notice['body'] ?>
                <?php if ($waitlist_notice['links']): ?><hr>
                    <?php foreach ($waitlist_notice['links'] as [$label, $href, $cls]): ?>
                        <a href="<?= $href ?>" class="btn <?= $cls ?>"><?= $label ?></a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if( $waitlist_result == 'declined' ): ?>
        <div class="container mt-3">
            <div class="alert alert-secondary text-center" role="alert">
                <p class="mb-2">You declined this waitlist offer. No credit has been deducted and the court has been offered to the next member.</p>
                <a href="index.php" class="btn btn-outline-primary">Make a booking</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if( $waitlist_result == 'confirmed' ): ?>
        <div class="container mt-3">
            <div class="success-message text-center text-success alert alert-success" role="alert">
                <span class="fs-2">
                    <i class="ri-checkbox-circle-line"></i>
                </span>
                <br>
                <p>Your booking has been confirmed and <b><?= number_format((int) ($_GET['amount'] ?? 0)) ?> THB</b> has been deducted from your credit.</p>
                <hr>
                <a href="bookings.php" class="btn btn-primary">View all bookings</a>
            </div>
        </div>
    <?php endif; ?>


    <!-- booking navigation -->
    <div class="container mt-5">
        <a class="btn btn-outline-primary" href="bookings.php" role="button">All bookings</a>
        <a class="btn btn-primary" href="waitlist.php" role="button">View my waitlist</a>
    </div>

    <div class="container mt-3 mb-5">
        <div class="table-responsive">
        <table class="table table-bordered text-center">
            <thead class="table-dark">
                <tr>
                    <th>Date</th>
                    <th>Requested Time</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody  id="allBookingTableBody">
                <?php if (count($bookings) > 0): ?>
                    <?php foreach ($bookings as $booking): ?>
                        <tr id="booking-<?= $booking['wait_list_id'] ?>">
                            <td><?= lsc_format_date('db_to_readable',$booking['date']) ?></td>
                            <td><?= htmlspecialchars($booking['timeslot']) ?></td>
                            <td>
                                <?php 
                                    if( $booking['waitlist_status'] == 'pending' && $is_guest_user ){
                                        echo '<span class="badge text-bg-warning">Reserved - staff will confirm</span>';
                                    }else if( $booking['waitlist_status'] == 'pending'){
                                        echo '<span class="badge text-bg-warning">Pending Confirmation</span>';
                                    }else if( $booking['waitlist_status'] == 'confirmed' ){
                                        echo '<span class="badge text-bg-success">Confirmed</span>';
                                    }else if( $booking['waitlist_status'] == 'expired' ){
                                        echo '<span class="badge text-bg-secondary">Expired</span>';
                                    }else if( $booking['waitlist_status'] == 'declined' ){
                                        echo '<span class="badge text-bg-secondary">Declined</span>';
                                    }else{
                                        echo '<span class="badge text-bg-dark">Waiting...</span>';
                                    }
                                ?>
                            </td>
                            <td>
                                <?php if( $booking['waitlist_status'] == 'pending' && $is_guest_user ){ ?>
                                    <p class="mb-1">Court <?= htmlspecialchars((string) $booking['free_court']) ?> is reserved for you.<br>
                                    <b><?= number_format(lsc_waitlist_total_price($booking['timeslot'], $booking['waitlist_note'], true)) ?> THB</b> - our staff will contact you to confirm and take payment.</p>
                                    <a href="waitlist.php?waitlist_action=decline&waitlist_id=<?= $booking['wait_list_id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Decline this offer? The court will go to the next person on the waitlist.')">I no longer want this slot</a>
                                <?php }else if( $booking['waitlist_status'] == 'pending'){ ?>
                                    <a href="waitlist.php?waitlist_action=confirm&waitlist_id=<?= $booking['wait_list_id'] ?>" class="btn btn-success">Confirm Booking</a><br>
                                    <span class="text-primary">Court <?= htmlspecialchars((string) $booking['free_court']) ?> &middot; <?= number_format(lsc_waitlist_total_price($booking['timeslot'], $booking['waitlist_note'])) ?> THB will be deducted from your credit</span>
                                    <hr>

                                    <a href="waitlist.php?waitlist_action=decline&waitlist_id=<?= $booking['wait_list_id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Decline this offer? The court will go to the next member on the waitlist.')">Decline Offer</a><br>
                                    <span class="text-primary">Your credit WILL NOT be deducted</span>
                                    
                                <?php } ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">No Waitlist found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    


    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</body>
</html>

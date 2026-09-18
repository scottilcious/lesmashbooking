<?php
ob_start(); // allows redirects after the header has been rendered
// Database connection
require 'config.php';
include_once 'includes/member-functions.php';

$booking_id = $_GET['booking_id'];
$mark_as_paid = $_GET['mark_as_paid'];
$delete_booking_action = $_GET['delete_action'];
?>

<!-- index.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View/Edit booking - Court Booking System</title>
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
<body>
    <?php include "menu.php"; 
    // legacy booking_functions/dynamic-waitlist.php no longer needed here; waitlist logic lives in includes/waitlist-service.php

function getWaitlistByBookingId(PDO $pdo, int $waitlist_booking_id)
{
    if ($waitlist_booking_id <= 0) {
        return false;
    }

    $sql = "
        SELECT *
        FROM wait_list
        WHERE waitlist_booking_id = :id
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $waitlist_booking_id]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
}

function getMemberCreditById(PDO $pdo, int $member_id)
{
    if ($member_id <= 0) {
        return false;
    }

    $sql = "
        SELECT credit
        FROM members
        WHERE id = :id
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $member_id]);

    $credit = $stmt->fetchColumn();

    return $credit !== false ? $credit : false;
}



function get_admin_time_from_timeslot_booking($timeslot){

    switch ($timeslot) {
        case '6-7am':
            $time = '6:00:00';
            break;
        
        case '7-8am':
            $time = '7:00:00';
            break;

        case '8-9am':
            $time = '8:00:00';
            break;
        
        case '9-10am':
            $time = '9:00:00';
            break;

        case '10-11am':
            $time = '10:00:00';
            break;
        
        case '11am-12pm':
            $time = '11:00:00';
            break;

        case '12-1pm':
            $time = '12:00:00';
            break;
        
        case '1-2pm':
            $time = '13:00:00';
            break;
        
        case '2-3pm':
            $time = '14:00:00';
            break;

        case '3-4pm':
            $time = '15:00:00';
            break;
        
        case '4-5pm':
            $time = '16:00:00';
            break;

        case '5-6pm':
            $time = '17:00:00';
            break;

        case '6-7pm':
            $time = '18:00:00';
            break;
        
        case '7-8pm':
            $time = '19:00:00';
            break;

        case '8-9pm':
            $time = '20:00:00';
            break;
        
        case '9-10pm':
            $time = '21:00:00';
            break;

        default:
            # code...
            break;
    }

    return $time;

}

function get_admin_booking_date_compare($date, $timeslot){
    $currentTime = new DateTime();
    $converted_time = get_admin_time_from_timeslot_booking($timeslot);
    $booking_date_time_converted = create_booking_date_time($date, $converted_time);

    $date_passed = false;
    if( $booking_date_time_converted < $currentTime){
        $date_passed = true;
    }else{
        $date_passed = false;
    }

    //Check the time differences
    $diff = $currentTime->diff($booking_date_time_converted);
    $hoursDifference = ($diff->days * 24) + $diff->h + ($diff->i / 60);

    $lessthan_24hr = false;
    if( $hoursDifference <= 48 ){
        $lessthan_24hr = true;
    }else{
        $lessthan_24hr = false;
    }

    $booking_datetime_compare = [$hoursDifference, $date_passed, $lessthan_24hr];

    return $booking_datetime_compare;
}

    // Fetch user bookings
    //$stmt = $pdo->prepare("SELECT * FROM bookings WHERE member_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
    $stmt->execute([$booking_id]);
    //$stmt->execute([$userId, $limit, $offset]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $times = [
        "6-7am", "7-8am", "8-9am", "9-10am", "10-11am", "11am-12pm", "12-1pm",
        "1-2pm", "2-3pm", "3-4pm", "4-5pm", "5-6pm", "6-7pm", "7-8pm", "8-9pm", "9-10pm"
    ];

    
    ?>
    <div class="banner">
        <div class="container">
            <div class="row justify-content-between">
                <div class="col-12 col-md-8">
                    <h1>View/Edit Booking</h1>
                </div>
                <div class="col-12 col-md-auto">
                    <a href="admin-view-booking.php?booking_id=<?= $booking_id ?>&delete_action=true" 
                    id="DeleteThisBooking" class="btn btn-danger">Delete Booking</a>
                </div>
            </div>
            
        </div>
    </div>

    <?php
    require_once 'includes/waitlist-service.php';

    // Waitlist offer actions taken by the admin on behalf of the member
    $waitlist_action = $_GET['waitlist_action'] ?? '';   // confirm | decline
    $waitlist_id     = (int) ($_GET['waitlist_id'] ?? 0);
    $waitlist_notice = $_SESSION['lsc_flash'] ?? null;
    unset($_SESSION['lsc_flash']);

    if ($memberType === 'admin' && $waitlist_id > 0 && in_array($waitlist_action, ['confirm', 'decline'], true)) {
        $actor = ['member_id' => (int) $userId, 'is_admin' => true, 'payment' => $_GET['payment'] ?? 'cash'];

        if ($waitlist_action === 'confirm') {
            $r = lsc_waitlist_confirm($pdo, $waitlist_id, $actor);
            if ($r['ok'] && $r['kind'] === 'guest') {
                $waitlist_notice = ['success', 'Guest booking confirmed as paid by <b>' . ($r['payment'] === 'qr' ? 'bank transfer' : 'cash') . '</b>, <b>' . number_format($r['amount']) . ' THB</b>.'];
            } elseif ($r['ok']) {
                $waitlist_notice = ['success', 'This booking has been confirmed. <b>' . number_format($r['amount']) . ' THB</b> was deducted from member ID ' . (int) $r['member_id'] . '.'];
            } else {
                switch ($r['reason']) {
                    case 'insufficient_credit':
                        $waitlist_notice = ['warning', 'Not enough credit: this booking costs <b>' . number_format($r['required']) . ' THB</b> but the member has <b>' . number_format($r['credit']) . ' THB</b>. <a href="admin-add-credit.php" class="alert-link">Add credit</a> and confirm again.'];
                        break;
                    case 'expired':
                        $waitlist_notice = ['danger', 'This offer has expired (2 hour window passed or the session is too close). The reservation was released and the court offered to the next member on the waitlist.'];
                        break;
                    default:
                        $waitlist_notice = ['danger', 'This offer is no longer open (status: ' . htmlspecialchars((string) ($r['status'] ?? $r['reason'])) . ').'];
                }
            }
        } else {
            $r = lsc_waitlist_decline($pdo, $waitlist_id, $actor, 'declined');
            if ($r['ok']) {
                $next = $r['next'] ? ' The court has been offered to ' . $r['next']['kind'] . ' ' . htmlspecialchars($r['next']['name']) . '.' : ' Nobody else is waiting for this slot; the court is now free.';
                $waitlist_notice = ['secondary', 'Offer declined on behalf of the member. No credit was deducted.' . $next];
            } else {
                $waitlist_notice = ['danger', 'Could not decline this offer (status: ' . htmlspecialchars((string) ($r['status'] ?? $r['reason'])) . ').'];
            }
        }

        // Redirect so a refresh does not repeat the action and the header badge is current
        $_SESSION['lsc_flash'] = $waitlist_notice;
        ob_end_clean();
        header('Location: admin-view-booking.php?booking_id=' . (int) $booking_id);
        exit;
    }

    if ($waitlist_notice): ?>
        <div class="container mt-3">
            <div class="alert alert-<?= $waitlist_notice[0] ?>" role="alert"><?= $waitlist_notice[1] ?></div>
        </div>
    <?php endif;
    ?>

    <?php 
    //Delete booking 
    if( $delete_booking_action == 'true'){
        $stmt_dlt_bk = $pdo->prepare("DELETE FROM bookings
         WHERE id = ?");
        $result_dlt_bk = $stmt_dlt_bk->execute([$booking_id]);

         if( $result_dlt_bk ){
        ?>
        <div class="container mt-3">
            <div class="alert alert-success" role="alert">
            This booking has been <b>deleted</b> sucessfully.
            <br>
            <a href="index.php" class="alert-link">Done</a>
            </div>
        </div>
        <?php 

            exit();

         }
    }

    ?>

    <?php 
    //If mark as paid
    if( $mark_as_paid == 'true' && $bookings[0]['booking_note'] == 'waitlist-reserved' ){
    ?>
        <div class="container mt-3">
        <div class="alert alert-warning" role="alert">
         This is a waitlist reservation. Use <b>Confirm Booking</b> below so the member's credit is deducted, or <b>Decline Offer</b> to release the court.
        </div>
        </div>
    <?php
    }elseif( $mark_as_paid == 'true'){
        $payment_remark = '';
        $stmt_bk = $pdo->prepare("UPDATE bookings SET payment_remark = ?
         WHERE id = ?");
        $result_bk = $stmt_bk->execute([$payment_remark, $booking_id]);

        if( $result_bk ){
    ?>
        <div class="container mt-3">
        <div class="alert alert-success" role="alert">
         This booking has been marked as <b>"Paid"</b>
        </div>
        </div>
    <?php 
        }
    }
    ?>

    <div class="container mt-5">
        <div class="row justify-content-end">
            <div class="col-12 col-md-12 text-end">
                <?php 

                    //Check if refunded within 24 hours
                    $booking_date = $bookings[0]['date'];
                    $booking_time = $bookings[0]['timeslot'];
                    $converted_time = get_time_from_timeslot($booking_time);
                    $booking_date_time_converted = create_booking_date_time($booking_date, $converted_time);
                    $currentTime = new DateTime();

                    //Check if it is already passed
                    if( $booking_date_time_converted < $currentTime){
                        $date_passed = true;
                    }else{
                        $date_passed = false;
                    }

                    $diff = $currentTime->diff($booking_date_time_converted);
                    $hoursDifference = ($diff->days * 24) + $diff->h + ($diff->i / 60);

                    if( $hoursDifference <= 48 ){
                        $lessthan_24Hr = true;
                        $notice_class = 'd-inline-block';
                        $refund_credit = 'false';
                    }else{
                        $lessthan_24Hr = false;
                        $notice_class = 'd-none';
                        $refund_credit = 'true';
                    }

                    $get_admin_booking_date_compare = get_admin_booking_date_compare($booking_date, $booking_time);
                    

                ?>

                <?php /* if( $get_admin_booking_date_compare[1] == true){ ?>
                    <div class="alert alert-warning <?= $notice_class ?>" role="alert">
                        This booking has already passed</b>
                    </div>
                <?php }elseif( $get_admin_booking_date_compare[2] == true && $bookings[0]['booking_status'] != 'cancelled' ){ ?>
                    <div class="alert alert-warning <?= $notice_class ?>" role="alert">
                        Cancelling in less than 48 hours, <b>the credit won't be refunded.</b>
                    </div>

                    <button class="btn btn-danger btn-sm cancel-btn" 
                        data-id="<?= $bookings[0]['id'] ?>"  
                        data-booking-type="<?= $bookings[0]['booking_type'] ?>"
                        data-user-id="<?= $bookings[0]['member_id'] ?>"
                        data-passed-24="<?= ($get_admin_booking_date_compare[2] == true)? "true": "false" ?>"
                        >
                        Cancel
                    </button>
                <?php }else{ ?>

                <?php } */ ?>

                <!-- cancel buttons -->
                <?php if( $bookings[0]['booking_status'] == 'cancelled' ): ?>
                    <span class="badge rounded-pill text-bg-warning">Cancelled</span>
                <?php else: ?>
                <div class="alert alert-warning <?= $notice_class ?>" role="alert">
                    Cancelling in less than 48 hours, <b>the credit won't be refunded.</b>
                </div>
                <button type="button" class="btn btn-cancel" id="cancelThisBooking"
                data-booking-id="<?php echo $bookings[0]['id']; ?>" 
                data-refund-credit="<?= $refund_credit ?>"
                data-cancel-special="false"
                data-cancel-half="false"
                data-transaction-id="<?= $bookings[0]['transaction_id'] ?>"
                data-member-id="<?= $bookings[0]['member_id'] ?>"
                data-non-member-id="<?= $bookings[0]['non_member_id'] ?>"
                >
                    Cancel this booking
                </button>
                <br>
                <button type="button" class="btn btn-outline-danger btn-outline-cancel" id="cancelThisBookingSpecial"
                        data-booking-id="<?php echo $bookings[0]['id']; ?>" 
                        data-refund-credit="true"
                        data-cancel-special="true"
                        data-cancel-half="false"
                        data-transaction-id="<?= $bookings[0]['transaction_id'] ?>"
                        data-member-id="<?= $bookings[0]['member_id'] ?>"
                        data-non-member-id="<?= $bookings[0]['non_member_id'] ?>"
                        >
                    Cancel due to Rain/Pollution
                </button>
                <button type="button" class="btn btn-outline-danger btn-outline-cancel" id="cancelThisBookingHalf"
                        data-booking-id="<?php echo $bookings[0]['id']; ?>" 
                        data-refund-credit="true"
                        data-cancel-special="true"
                        data-cancel-half="true"
                        data-transaction-id="<?= $bookings[0]['transaction_id'] ?>"
                        data-member-id="<?= $bookings[0]['member_id'] ?>"
                        data-non-member-id="<?= $bookings[0]['non_member_id'] ?>"
                        >
                    Cancel due to Rain/Pollution (Half)
                </button>
                <!-- cancel buttons -->
                <?php endif; ?>

            </div>
        </div>
    </div>

    <div class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8">
                <div class="card">

                    <?php if($bookings[0]['booking_note'] == 'waitlist-reserved'): 

                        $waitlist = getWaitlistByBookingId($pdo, $booking_id);                        
                        ?>
                    <div class="alert alert-warning rounded-bottom-0" role="alert">
                        <div class="row justify-content-between">
                            <div class="col-auto">
                                <?php $wl_is_guest = lsc_waitlist_is_guest_entry($waitlist); $wl_price = lsc_waitlist_total_price($waitlist['timeslot'], $waitlist['waitlist_note'], $wl_is_guest); ?>
                                <h4>This booking is a reservation for a waitlist<?= $wl_is_guest ? ' (guest)' : '' ?>.</h4>
                                <?php if ($wl_is_guest): ?>
                                    <b>Only an admin can confirm a guest offer.</b> Collect <?= number_format($wl_price) ?> THB from the guest, then confirm with the payment method used.
                                <?php else: ?>
                                    Member has not confirmed this booking yet.
                                <?php endif; ?>
                                Offer sent <?= htmlspecialchars((string) $waitlist['updated_at']) ?>; it expires 2 hours later.
                            </div>
                            <div class="col-auto">
                                <a class="btn btn-outline-dark" id="reserveMarkExpiredBtn" href="admin-view-booking.php?booking_id=<?= $booking_id ?>&waitlist_action=decline&waitlist_id=<?= $waitlist['wait_list_id'] ?>" onclick="return confirm('Decline this offer? The court will go to the next person on the waitlist.')">Decline Offer</a>
                                <?php if ($wl_is_guest): ?>
                                    <a class="btn btn-success" id="reserveConfirmCashBtn" href="admin-view-booking.php?booking_id=<?= $booking_id ?>&waitlist_action=confirm&payment=cash&waitlist_id=<?= $waitlist['wait_list_id'] ?>" onclick="return confirm('Confirm this guest booking as PAID IN CASH (<?= number_format($wl_price) ?> THB)?')">Confirm - paid cash (<?= number_format($wl_price) ?> THB)</a>
                                    <a class="btn btn-outline-success" id="reserveConfirmQrBtn" href="admin-view-booking.php?booking_id=<?= $booking_id ?>&waitlist_action=confirm&payment=qr&waitlist_id=<?= $waitlist['wait_list_id'] ?>" onclick="return confirm('Confirm this guest booking as PAID BY BANK TRANSFER (<?= number_format($wl_price) ?> THB)?')">Confirm - paid by transfer</a>
                                <?php else: ?>
                                    <a class="btn btn-primary" id="reserveConfirmBookingBtn" href="admin-view-booking.php?booking_id=<?= $booking_id ?>&waitlist_action=confirm&waitlist_id=<?= $waitlist['wait_list_id'] ?>" onclick="return confirm('Confirm this booking and deduct <?= number_format($wl_price) ?> THB from the member\'s credit?')">Confirm Booking (<?= number_format($wl_price) ?> THB)</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                    </div>
                    <?php
                    
                     endif; ?>

                    <div class="card-body">

                        <p class="mb-0">Booking type</p>
                        <span class="badge rounded-pill text-bg-primary">
                            <?php 
                           $booking_type = $bookings[0]['booking_type'];
                           switch ($booking_type) {
                            case 'member booking':
                                echo 'Member booking';
                                break;
                            case 'non-member':
                                echo 'Non-member booking';
                                break;
                            case 'academy':
                                echo 'Academy';
                                break;
                            case 'junior_academy':
                                echo 'Junior Academy';
                                break;
                            case 'adult_clinic':
                                echo 'Adult clinic';
                                break;
                            case 'tennis_camp':
                                echo 'Tennis camp';
                                break;
                            case 'tournament':
                                echo 'Tournament';
                                break;
                            
                            default:
                                # code...
                                break;
                           }
                            
                            ?>
                        </span>
                        <hr>

                        <input type="hidden" name="booking_id" id="booking_id" value="<?php echo $bookings[0]['id']; ?>">
                        <div class="card">
                            <div class="card-body">

                        <div class="mt-3">
                            <label for="date">Date</label>
                            <input class="form-control form-control-lg admin-booking-date" type="text" name="date" id="date" value="<?php echo $bookings[0]['date'];?>" />

                            <hr>
                            <button type="button" id="adminCheckAvailability" class="btn btn-outline-primary">Change court and time</button>
                        </div>

                        <div class="mt-3">
                            <label for="court">Court</label>
                            <input type="text" class="form-control form-control-lg" name="court" id="court" value="<?= $bookings[0]['court'] ?>" disabled>
                        </div>

                        <div class="mt-3">
                            <p>Timeslot</p>
                            <input type="text" class="form-control form-control-lg" name="timeslot" id="timeslot" value="<?= $bookings[0]['timeslot'] ?>" disabled>
                        </div>

                            </div>
                        </div> <!-- card wrapper -->

                        <div class="mt-3">
                            <label for="booking_status">Booking Status</label>
                            <select class="form-select form-select-lg" name="booking_status" id="booking_status">
                                <option value="pending" <?php echo ($bookings[0]['booking_status'] == 'pending') ? "selected" : ""; ?>>Pending</option>
                                <option value="approved" <?php echo ($bookings[0]['booking_status'] == 'approved') ? "selected" : ""; ?>>Approved</option>
                                <option value="cancelled" <?php echo ($bookings[0]['booking_status'] == 'cancelled') ? "selected" : ""; ?>>Cancelled</option>
                                <option value="not_paid" <?php echo ($bookings[0]['booking_status'] == 'not_paid') ? "selected" : ""; ?>>Not Paid</option>
                            </select>
                        </div>


                        <?php if( $bookings[0]['payment'] == 'qr'){ ?>
                        <div class="mt-3">
                            <h4>Payment</h4>
                            <img src="uploads/<?php echo $bookings[0]['slip']; ?>" alt="" width="300"><br>
                            <a href="uploads/<?php echo $bookings[0]['slip']; ?>" class="btn btn-outline-primary">
                            <i class="ri-receipt-fill"></i>View full size slip</a>
                        </div>
                        <?php }else if( $bookings[0]['payment'] == 'credit' ){ 
                            
                            $transaction_id = $bookings[0]['transaction_id'];
                                    
                            $ts_stmt = $pdo->prepare("SELECT * FROM transactions WHERE transaction_id = ?");
                            $ts_stmt->execute([$transaction_id]);
                            $transaction = $ts_stmt->fetchAll(PDO::FETCH_ASSOC);
                            ?>
                            <p class="mt-3 mb-0">Payment</p>
                            <div class="card mt-1">
                                <div class="card-body">
                                    Paid with credit: <br> 
                                    <span class="badge text-bg-dark"><?= $transaction[0]['transaction_amount'] ?></span>
                                    <input type="hidden" name="credit" id="credit" value="<?= $transaction[0]['transaction_amount'] ?>">
                                </div>
                            </div>
                            
                        <?php }else{ ?>
                            <p class="mt-3 mb-0">Payment</p>
                            <?= $bookings[0]['payment'] ?> <?= ( $bookings[0]['payment_remark'] ) ? '- ' . $bookings[0]['payment_remark'] : '' ?>
                            <?php if( $bookings[0]['payment_remark'] ): ?>
                            <br>
                            <a class="btn btn-outline-primary" href="admin-view-booking.php?booking_id=<?= $booking_id ?>&mark_as_paid=true">Mark as Paid</a>
                            <?php endif; ?>
                        <?php } ?>

                        <div class="mt-3">
                            <label for="coach_name">Coach Name</label>
                            <input type="text" name="coach_name" id="coach_name" class="form-control form-control-lg" value="<?= $bookings[0]['coach_name'] ?>">
                        </div>
                        
                        <div class="mt-3">
                            <label for="coach_name">Extra player</label>
                            <input type="number" name="coach_extra_player" id="coach_extra_player" class="form-control form-control-lg" value="<?= $bookings[0]['coach_extra_player'] ?>">
                        </div>



                        <div class="mt-3 mb-5 border-top">
                            <label for="booking_note">Booking note</label>
                            <textarea name="booking_note" id="booking_note" class="form-control form-control-lg"><?= $bookings[0]['booking_note'] ?></textarea>
                        </div>


                        <div class="mt-3">
                            <div class="alert alert-light" role="alert">
                                <small>Updated on</small>
                                <p><?php echo $bookings[0]['created_at']; ?></p>
                            </div>
                        </div>

                        <div class="cta-wrapper border-top mt-5 pt-3 mb-5">
                            <input type="hidden" name="booking_type" id="booking_type" value="<?php echo $bookings[0]['booking_type']; ?>">
                            <input type="hidden" name="payment" id="payment" value="<?php echo $bookings[0]['payment']; ?>">
                            <input type="hidden" name="member_id" id="member_id" value="<?php echo $bookings[0]['member_id']; ?>">
                            
                            <button type="button" class="btn btn-primary" data-booking-id="<?php echo $bookings[0]['id']; ?>" id="saveBooking"
                            <?= ($bookings[0]['booking_status'] == 'cancelled') ? 'disabled' : '' ?>
                            >Save changes</button>
                        </div>




                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card card-member">
                    <div class="card-body">
                        <?php if( $bookings[0]['member_id'] && ( $bookings[0]['non_member_id'] == '90002' || $bookings[0]['non_member_id'] == '90001') ){ ?>
                        <h4 class="fw-bold <?= $bookings[0]['member_id'] ?>">Booking member details:</h4>
                        <?php 
                            $stmt_member = $pdo->prepare("SELECT * FROM members WHERE id = ?");
                            $stmt_member->execute([$bookings[0]['member_id']]);
                            //$stmt->execute([$userId, $limit, $offset]);
                            $member_info = $stmt_member->fetchAll(PDO::FETCH_ASSOC);
                        ?>

                        <span class="text-body-tertiary">Member Name</span>
                        <p class="fw-bold"><?= $member_info[0]['first_name'] . " " . $member_info[0]['last_name'] ?> </p>

                        <span class="text-body-tertiary">Member Number</span>
                        <p class="fw-bold"><?= $member_info[0]['member_number'] ?> </p>

                        <span class="text-body-tertiary">Member Type</span>
                        <p class="fw-bold"><?= $member_info[0]['member_type'] ?> </p>

                        <span class="text-body-tertiary">Member Status</span>
                        <p class="fw-bold"><?= $member_info[0]['member_status'] ?> </p>

                        <span class="text-body-tertiary">Member Phone</span>
                        <p class="fw-bold"><a href="<?= $member_info[0]['member_phone'] ?>"><?= $member_info[0]['member_phone'] ?></a></p>
                        <span class="text-body-tertiary">Member Email</span>
                        <p class="fw-bold"><a href="<?= $member_info[0]['member_email'] ?>"><?= $member_info[0]['member_email'] ?></a></p>

                        <hr>
                        <a href="admin-view-member.php?member_id=<?= $member_info[0]['id'] ?>" class="btn btn-primary">View this member details</a>
                    <?php }elseif( ($bookings[0]['member_id'] == '90002' || $bookings[0]['member_id'] == '90001' ) && $bookings[0]['non_member_id']){ ?>
                        <h4 class="fw-bold">Booking non-member details:</h4>
                        <?php 
                            $stmt_non_member = $pdo->prepare("SELECT * FROM non_members WHERE id = ?");
                            $stmt_non_member->execute([$bookings[0]['non_member_id']]);
                            //$stmt->execute([$userId, $limit, $offset]);
                            $non_member_info = $stmt_non_member->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        <span class="text-body-tertiary">Name</span>
                        <p class="fw-bold"><?= $non_member_info[0]['guest_name'] ?> </p>

                        <span class="text-body-tertiary">Email</span>
                        <p class="fw-bold"><?= $non_member_info[0]['member_email'] ?> </p>

                        <span class="text-body-tertiary">Phone</span>
                        <p class="fw-bold"><?= $non_member_info[0]['member_phone'] ?> </p>

                    <?php }else{

                    } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Check available -->
    <div class="overlay-court-time-admin" id="overlayCourtTimeAdmin">
        <div class="container">
            <div class="card-court-time-wrapper">
                
                <div class="card mt-1">
                    <div class="card-header">
                        <div class="row justify-content-between">
                            <div class="col-auto">
                                <h2>
                                    Available court and time
                                </h2>
                            </div>
                            <div class="col-auto">
                                <button type="button" id="closeAdminCourtTime" class="btn btn-outline-secondary" onclick="close_admin_court_time_check()">
                                    <i class="ri-close-line"></i>
                                </button>
                            </div>
                        </div>
                        
                    </div>
                    <div class="card-body">

                        <!-- <div class="mb-3">
                            <label for="admin_check_date">Date</label>
                            <input class="form-control form-control-lg admin-booking-date" type="text" name="admin_check_date" id="admin_check_date">
                        </div> -->

                        <div id="adminCourtTimeResultContent">

                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>

    <!-- Loading -->
    <div class="loader-overlay pt-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-6 col-md-4">
                    <div class="inner-loader bg-white text-center">
                        <img src="images/ball_loading.gif" alt="" width="100">
                        <p>Please wait...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .overlay-court-time-admin{
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 999;
        }
        .overlay-court-time-admin.active{
            display: block;
        }

        .card-court-time-wrapper .table-court-time-selection{
            height: 80vh;
            overflow: auto;
        }

        #selectAdminCourtTime td{
            padding: 0 0;
        }

        #selectAdminCourtTime td label{
            display: block;
            padding: 1em;
            background-color: #ddf6c7;
            color: #2c671d;
            border: 1px solid #58bb60;
        }
        #selectAdminCourtTime td label:has(input:disabled){
            cursor: not-allowed;
            background-color: #eb564f;
            color: white;
            border: 1px solid #eb564f;
            opacity: 0.8;
        }

    </style>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>

        function close_admin_court_time_check(){
            document.getElementById("overlayCourtTimeAdmin").classList.remove("active");
        }

        /*
        document.addEventListener("DOMContentLoaded", function () {
            flatpickr("#date", {
                dateFormat: "Y-m-d", // Format: YYYY-MM-DD
                defaultDate: "<?php echo $bookings[0]['date'];?>",
                //minDate: "today",   // Disable past dates
                altInput: true,     // Show formatted date
                altFormat: "j F, Y", // Format: Full month name, day, year
                });
        });
        */
        $(document).ready(function () {

            let enquired_booking_id = $("#booking_id").val();

            //Select Date 
            flatpickr(".admin-booking-date", {
                dateFormat: "Y-m-d", // Format: YYYY-MM-DD
                defaultDate: "<?php echo $bookings[0]['date'];?>",
                altInput: true,     // Show formatted date
                altFormat: "F j, Y", // Format: Full month name, day, year
                onChange: function(selectedDates, dateStr, instance) {

                    $("#overlayCourtTimeAdmin").addClass("active");

                    $.post("admin-check-availability.php", { date: dateStr, booking_id: enquired_booking_id }, function (data) {
                        $("#adminCourtTimeResultContent").html(data);
                    });
                }
            });

            $("#adminCheckAvailability").click(function(){

                let this_booking_date = $('#date').val();
                let enquired_booking_id = $("#booking_id").val();
                //console.log(this_booking_date);
                $("#overlayCourtTimeAdmin").addClass("active");

                $.post("admin-check-availability.php", { date: this_booking_date, booking_id: enquired_booking_id }, function (data) {
                    $("#adminCourtTimeResultContent").html(data);
                });

            });

            $("#saveBooking").click(function () {
                let bookingId = $(this).data("booking-id");
                let court = $('#court').val();
                let date = $('#date').val();
                let payment = $('#payment').val();
                let member_id = $("#member_id").val();
                //let timeslot = $("input[name='timeslot']:checked").val();
                let timeslot = $('#timeslot').val();
                let booking_status = $('#booking_status').val();
                let coach = $('#coach').val();
                let coach_name = $('#coach_name').val();
                let coach_extra_player = $('#coach_extra_player').val();
                let booking_note = $('#booking_note').val();
                let credit = $("#credit").val();
                if ( credit == undefined || credit == null || credit == ''){
                    credit = '';
                }


                if (confirm("Are you sure you want to save changes to this booking?")) {

                    $(".loader-overlay").addClass("display");

                    $.ajax({
                        url: "admin-save-booking.php",
                        type: "POST",
                        data: { 
                            booking_id: bookingId,
                            court: court,
                            date: date,
                            timeslot: timeslot,
                            payment: payment,
                            member_id: member_id,
                            booking_status: booking_status,
                            coach:coach,
                            coach_name:coach_name,
                            coach_extra_player: coach_extra_player,
                            booking_note:booking_note,
                            credit: credit
                        },
                        success: function (response) {
                            $(".loader-overlay .inner-loader").html(response);
                        },
                        error: function(xhr) {
                            $(".loader-overlay .inner-loader").html("Error: " + xhr.statusText);
                        }
                    });
                }
            });

            
            $("#cancelThisBooking").click(function(){

                let bookingId = $(this).data("booking-id");
                let credit_refund = $(this).data("refund-credit");
                let cancel_special = $(this).data("cancel-special");
                let cancel_half = $(this).data("cancel-half");
                let transaction_id = $(this).data("transaction-id");
                let member_id = $(this).data("member-id");
                let non_member_id = $(this).data("non-member-id");


                if (confirm("Are you sure you want to cancel this booking?")) {

                    $(".loader-overlay").addClass("display");

                    $.ajax({
                        url: "admin-cancel-booking.php",
                        type: "POST",
                        data: { 
                            booking_id: bookingId,
                            credit_refund: credit_refund,
                            cancel_special: cancel_special,
                            cancel_half: cancel_half,
                            transaction_id: transaction_id,
                            member_id: member_id,
                            non_member_id: non_member_id
                        },
                        success: function (response) {
                            $(".loader-overlay .inner-loader").html(response);
                        },
                        error: function(xhr) {
                            $(".loader-overlay .inner-loader").html("Error: " + xhr.statusText);
                        }
                    });
                }
            });

            $("#cancelThisBookingSpecial").click(function(){

                let bookingId = $(this).data("booking-id");
                let credit_refund = $(this).data("refund-credit");
                let cancel_special = $(this).data("cancel-special");
                let cancel_half = $(this).data("cancel-half");
                let transaction_id = $(this).data("transaction-id");
                let member_id = $(this).data("member-id");
                let non_member_id = $(this).data("non-member-id");
                

                if (confirm("Are you sure you want to cancel this booking?")) {

                    $(".loader-overlay").addClass("display");

                    $.ajax({
                        url: "admin-cancel-booking.php",
                        type: "POST",
                        data: { 
                            booking_id: bookingId,
                            credit_refund: credit_refund,
                            cancel_special: cancel_special,
                            cancel_half: cancel_half,
                            transaction_id: transaction_id,
                            member_id: member_id,
                            non_member_id: non_member_id
                        },
                        success: function (response) {
                            $(".loader-overlay .inner-loader").html(response);
                        },
                        error: function(xhr) {
                            $(".loader-overlay .inner-loader").html("Error: " + xhr.statusText);
                        }
                    });
                    
                }
            });

            $("#cancelThisBookingHalf").click(function(){

                let bookingId = $(this).data("booking-id");
                let credit_refund = $(this).data("refund-credit");
                let cancel_special = $(this).data("cancel-special");
                let cancel_half = $(this).data("cancel-half");
                let transaction_id = $(this).data("transaction-id");
                let member_id = $(this).data("member-id");
                let non_member_id = $(this).data("non-member-id");
                

                if (confirm("Are you sure you want to cancel this booking?")) {

                    $(".loader-overlay").addClass("display");

                    $.ajax({
                        url: "admin-cancel-booking.php",
                        type: "POST",
                        data: { 
                            booking_id: bookingId,
                            credit_refund: credit_refund,
                            cancel_special: cancel_special,
                            cancel_half: cancel_half,
                            transaction_id: transaction_id,
                            member_id: member_id,
                            non_member_id: non_member_id
                        },
                        success: function (response) {
                            $(".loader-overlay .inner-loader").html(response);
                        },
                        error: function(xhr) {
                            $(".loader-overlay .inner-loader").html("Error: " + xhr.statusText);
                        }
                    });
                    
                }
            });

            

            $("#DeleteThisBooking").click(function(){

                if (confirm("Are you sure you want to delete this booking?")) {
                    return true;
                }else{
                    return false;
                }
            });


        });
    </script>
</body>
</html>


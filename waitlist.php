<?php
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
    include 'includes/member-functions.php';
    include 'includes/booking-functions.php';
    include 'booking_functions/dynamic-waitlist.php';

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
    //Check and update expired waitlist



    $waitlist_result = $_GET['waitlist_result'] ?? '';

    //Confirm booking 
    $waitlist_confirm = $_GET['waitlist_confirm'] ?? '';
    $waitlist_id = $_GET['waitlist_id'] ?? '';
    $passed_date = $_GET['date'] ?? '';
    $passed_timeslot = $_GET['timeslot'] ?? '';
    $free_court = $_GET['free_court'] ?? '';
    $book_id = $_GET['book_id'] ?? '';

    
    
    if( $waitlist_confirm == 'true' && $waitlist_id && $free_court && $passed_date &&  $passed_timeslot){
        if (!lsc_is_booking_window_open_for_member($memberType)) {
            ?>
            <div class="container mt-3">
                <div class="alert alert-warning" role="alert">
                    <h2>Booking is closed right now</h2>
                    Please come back after 6:00 am to confirm this waitlist booking.
                </div>
            </div>
            <?php
            return false;
        }

        $waitlist_status = 'confirmed';
        $required_credit = getTransactionPrice($passed_timeslot);

        //Check credit
        if($memberCredit < $required_credit){
        ?>
            <div class="container mt-3">
            <div class="alert alert-warning" role="alert">
                <h2>Not enough credit to confirm a booking</h2>
                You do not have enough credit to make a booking.<br>
                Please refill credit before confirming the booking.
                <hr>
                <a href="add-credit.php" class="btn btn-primary">Add Credit</a>
            </div>
            </div>
        <?php 
            
            return false;
        }

        //Check if past grace period 
        $formatted_time = get_timeslot_for_waitlist($passed_timeslot);
        $diffSeconds = getTimeDifferenceFromNow($passed_date, $formatted_time);

        // Check if it's less than 2 hours
        if ($diffSeconds < 2 * 3600) {

            //Update Waitlist to Expired
            $waitlist_status = 'expired';
            updateWaitlistStatus($pdo, $waitlist_id, $waitlist_status, $free_court);

            $transactionId = getTransactionIdByBookingId($pdo, $book_id);
            $assocId = getAssocTransactionId($pdo, $transactionId);

            //Create cancelled transaction
            $transactionData = [
                'transaction_title'    => 'Cancelled from waitlist expiration',
                'assoc_transaction_id' => null,       // or another txn ID if linked
                'member_id'            => $userId,
                'non_member_id'        => '90002',
                'transaction_amount'   => 0,
                'transaction_type'     => 'cancelled',  // or refund, credit_topup, etc.
                'payment_type'         => 'credit',       // cash, qr, credit, etc.
                'slip_url'             => null,
                'transaction_note'     => 'cancelled by member',
            ];

            $txnId = createTransaction($pdo, $transactionData);

            //Update booking status 
            $newStatus = 'cancelled';
            $updateBooking = updateBookingStatus($pdo, $book_id, $newStatus);

            if( $txnId && $updateBooking ){
                ?>
                <div class="container mt-3">
                    <div class="error-message text-center text-danger alert alert-danger" role="alert">
                        <span class="fs-2">
                            <i class="ri-error-warning-fill"></i>
                        </span>
                        <br>
                        <p>Unfortunately, the booking has been expired.<br>However, you can still make another booking.</p>
                        <hr>
                        <a href="index.php" class="btn btn-primary">Make a booking</a>
                    </div>
                </div>
                <?php 
            }else{
                ?>
                <div class="container mt-3">
                    <div class="error-message text-center text-danger alert alert-danger" role="alert">
                        <span class="fs-2">
                            <i class="ri-error-warning-fill"></i>
                        </span>
                        <br>
                        <p>There's something wrong with the booking. Please try again.</p>
                        <hr>
                        <a href="waitlist.php" class="btn btn-primary">Try again</a>
                    </div>
                </div>
                <?php 
            }

            
        } else {
            //Confirm booking
            $payment = 'credit';
            $payment_remark = NULL;
            $booking_note = 'waitlist booking paid';

            try {
                $pdo->beginTransaction();

                $payment_updated = updateBookingPayment($pdo, (int) $book_id, $payment, $payment_remark, $booking_note);
                $waitlist_updated = $payment_updated
                    ? updateWaitlistStatus($pdo, (int) $waitlist_id, 'confirmed', $free_court)
                    : false;

                $credit_updated = false;
                if ($payment_updated && $waitlist_updated) {
                    $stmt_mb = $pdo->prepare("UPDATE members SET credit = credit - ? WHERE id = ? ");
                    $credit_updated = $stmt_mb->execute([$required_credit, $userId]);
                }

                if ($payment_updated && $waitlist_updated && $credit_updated) {
                    $pdo->commit();
                    echo '<script>window.location.href="waitlist.php?waitlist_result=confirmed";</script>';
                    exit;
                }

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            }
            ?>
            <div class="container mt-3">
                <div class="error-message text-center text-danger alert alert-danger" role="alert">
                    <span class="fs-2">
                        <i class="ri-error-warning-fill"></i>
                    </span>
                    <br>
                    <p>There's something wrong with the booking. Please try again.</p>
                    <hr>
                    <a href="waitlist.php" class="btn btn-primary">Try again</a>
                </div>
            </div>
            <?php
        }
        

    }else if($waitlist_confirm == 'false' && $waitlist_id && $free_court && $passed_date &&  $passed_timeslot){
        $waitlist_status = 'confirmed';
        //Cancel Booking Confirmation

        //Update Waitlist to Expired
            $waitlist_status = 'expired';
            updateWaitlistStatus($pdo, $waitlist_id, $waitlist_status, $free_court);

            //Create cancelled transaction
            $transactionData = [
                'transaction_title'    => 'Cancelled from waitlist expiration',
                'assoc_transaction_id' => null,       // or another txn ID if linked
                'member_id'            => $userId,
                'non_member_id'        => '90002',
                'transaction_amount'   => 0,
                'transaction_type'     => 'cancelled',  // or refund, credit_topup, etc.
                'payment_type'         => 'credit',       // cash, qr, credit, etc.
                'slip_url'             => null,
                'transaction_note'     => 'cancelled by member',
            ];

            $txnId = createTransaction($pdo, $transactionData);

            //Update booking status 
            $newStatus = 'cancelled';
            $updateBooking = updateBookingStatus($pdo, $book_id, $newStatus);

            if( $txnId && $updateBooking ){
                ?>
                <div class="container mt-3">
                    <div class="error-message text-center text-danger alert alert-danger" role="alert">
                        <span class="fs-2">
                            <i class="ri-error-warning-fill"></i>
                        </span>
                        <br>
                        <p>Booking confirmation cancelled successfully</p>
                        <hr>
                        <a href="index.php" class="btn btn-outline-primary">Make a booking</a>
                        <a href="waitlist.php" class="btn btn-primary">View my waitlist</a>
                    </div>
                </div>
                <?php 
            }else{
                ?>
                <div class="container mt-3">
                    <div class="error-message text-center text-danger alert alert-danger" role="alert">
                        <span class="fs-2">
                            <i class="ri-error-warning-fill"></i>
                        </span>
                        <br>
                        <p>There's something wrong with the booking. Please try again.</p>
                        <hr>
                        <a href="waitlist.php" class="btn btn-primary">Try again</a>
                    </div>
                </div>
                <?php 
            }


    }else{}

    ?>

    <?php if( $waitlist_result == 'confirmed' ): ?>
        <div class="container mt-3">
            <div class="success-message text-center text-success alert alert-success" role="alert">
                <span class="fs-2">
                    <i class="ri-checkbox-circle-line"></i>
                </span>
                <br>
                <p>Your booking has been confirmed and your credit has been updated.</p>
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
                        <tr id="booking-<?= $booking['id'] ?>">
                            <td><?= lsc_format_date('db_to_readable',$booking['date']) ?></td>
                            <td><?= htmlspecialchars($booking['timeslot']) ?></td>
                            <td>
                                <?php 
                                    if( $booking['waitlist_status'] == 'pending'){
                                        echo '<span class="badge text-bg-warning">Pending Confirmation</span>';
                                    }else if( $booking['waitlist_status'] == 'confirmed' ){
                                        echo '<span class="badge text-bg-success">Confirmed</span>';
                                    }else if( $booking['waitlist_status'] == 'expired' ){
                                        echo '<span class="badge text-bg-secondary">Expired</span>';
                                    }else{
                                        echo '<span class="badge text-bg-dark">Waiting...</span>';
                                    }
                                ?>
                            </td>
                            <td>
                                <?php if( $booking['waitlist_status'] == 'pending'){ ?>
                                    <a href="waitlist.php?waitlist_confirm=true&waitlist_id=<?= $booking['wait_list_id'] ?>&free_court=<?= $booking['free_court'] ?>&date=<?= $booking['date'] ?>&timeslot=<?= $booking['timeslot'] ?>&book_id=<?= $booking['waitlist_booking_id'] ?>" class="btn btn btn-success">Confirm Booking</a><br>
                                    <span class="text-primary">Your credit will be deducted</span>
                                    <hr>

                                    <a href="waitlist.php?waitlist_confirm=false&waitlist_id=<?= $booking['wait_list_id'] ?>&free_court=<?= $booking['free_court'] ?>&date=<?= $booking['date'] ?>&timeslot=<?= $booking['timeslot'] ?>&book_id=<?= $booking['waitlist_booking_id'] ?>" class="btn btn-outline-danger">Cancel Booking Confirmation</a><br>
                                    <span class="text-primary">Your credit WILL NOT be duducted</span>
                                    
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

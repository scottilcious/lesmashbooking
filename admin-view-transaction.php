<?php
// Database connection
require 'config.php';
include_once 'includes/member-functions.php';

$transaction_id = $_GET['transaction_id'];
?>

<!-- index.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View transaction - Court Booking System</title>
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

    // Fetch user transaction
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE transaction_id = ?");
    $stmt->execute([$transaction_id]);
    $transaction_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $transaction_id = $transaction_data[0]['transaction_id'];
    $transaction_member_id = $transaction_data[0]['member_id'];
    
    ?>
    <div class="banner">
        <div class="container">
            <h1>View Transaction</h1>
        </div>
    </div>

    <div class="container mt-5">
        <div class="row justify-content-end">
            <div class="col-12 col-md-8 text-end">
                <a href="#" class="btn btn-secondary">Approve Credit</a>

            </div>
        </div>
    </div>

    <div class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h2>Transaction Details</h2>
                        <div class="mt-5">
                            <span>Transaction title</span>
                            <p class="fw-bold border-bottom pb-2"><?= $transaction_data[0]['transaction_title'] ?></p>

                            <span>Transaction Amount</span>
                            <p class="fw-bold border-bottom pb-2 fs-4"><?= $transaction_data[0]['transaction_amount'] ?></p>

                            <span>Transaction Type</span>
                            <p class="fw-bold border-bottom pb-2"><?= $transaction_data[0]['transaction_type'] ?></p>

                            <span>Slip/Credit</span>
                            <p class="fw-bold border-bottom pb-2">
                                <?= $transaction_data[0]['slip_url'] ?>
                                <?= $transaction['payment_type'] ?>
                                <?php if( ($transaction_data[0]['slip_url'] == '' || $transaction_data[0]['slip_url'] == NULL) && $transaction['payment_type'] == 'credit'  ): ?>
                                    Credit transferred:<br>
                                    <span class="badge text-bg-dark"><?=  number_format($transaction_data[0]['transaction_amount'], "0", "", "") ?></span>

                                <?php elseif( ($transaction_data[0]['slip_url'] == '' || $transaction_data[0]['slip_url'] == NULL) && $transaction['payment_type'] == 'cash'  ): ?>
                                    Cash (Admin)
                                <?php else: ?>
                                <a class="btn btn-outline-primary btn-sm view-btn" href="uploads/<?= $transaction_data[0]['slip_url'] ?>" target="_blank">View Slip</a>
                                <?php endif; ?>

                            </p>
                        </div>
                    </div>    
                </div>
            </div>
            <div class="col-12 col-md-4">

                <?php if($transaction_data[0]['assoc_transaction_id'] != ''){ ?>
                <div class="card">
                    <div class="card-body">
                        <h2>Booking Details</h2>
                        <?php 
                        $stmt_booking = $pdo->prepare("SELECT * FROM bookings WHERE transaction_id = ?");
                        $stmt_booking->execute( [$transaction_id] );
                        $booking_info = $stmt_booking->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        <span>Booking Date</span>
                        <p class="fw-bold border-bottom pb-2"><?= format_date_to_readable($booking_info[0]['date']) ?></p>
                        <span>Booking Court</span>
                        <p class="fw-bold border-bottom pb-2"><?= $booking_info[0]['court'] ?></p>
                        <span>Booking Time</span>
                        <p class="fw-bold border-bottom pb-2"><?= $booking_info[0]['timeslot'] ?></p>

                        <div class="mt-2">
                            <a href="admin-view-booking.php?booking_id=<?= $booking_info[0]['id'] ?>" class="btn btn-outline-primary">View Booking</a>
                        </div>
                    </div>    
                </div>
                <?php } ?>
                
                <?php if($transaction_data[0]['member_id'] != ''){ ?>
                <div class="card mt-3">
                    <div class="card-body">
                        <h2>Member Details</h2>
                        <?php 
                        $stmt_member = $pdo->prepare("SELECT * FROM members WHERE id = ?");
                        $stmt_member->execute( [$transaction_member_id] );
                        $transaction_member_info = $stmt_member->fetchAll(PDO::FETCH_ASSOC);
                        ?>

                        <span>Member Name</span>
                        <p class="fw-bold border-bottom pb-2">
                            <?= $transaction_member_info[0]['first_name'] ?> <?= $transaction_member_info[0]['last_name'] ?>
                        </p>
                        <span>Member Number</span>
                        <p class="fw-bold border-bottom pb-2">
                            <?= $transaction_member_info[0]['member_number'] ?>
                        </p>

                        <div class="mt-2">
                            <a href="admin-view-member.php?member_id=<?= $transaction_member_info[0]['id'] ?>" class="btn btn-outline-primary">View Member</a>
                        </div>
                    </div>    
                </div>
                <?php } ?>

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

    

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
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
        $(document).ready(function () {

            $("#saveBooking").click(function () {
                let bookingId = $(this).data("booking-id");
                let court = $('#court').val();
                let date = $('#date').val();
                let payment = $('#payment').val();
                let member_id = $("#member_id").val();
                let timeslot = $("input[name='timeslot']:checked").val();
                let booking_status = $('#booking_status').val();
                let coach = $('#coach').val();
                let coach_name = $('#coach_name').val();
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
                let member_id = $("#member_id").val();
                let credit = $("#credit").val();

                if (confirm("Are you sure you want to cancel this booking?")) {

                    $(".loader-overlay").addClass("display");

                    $.ajax({
                        url: "admin-cancel-booking.php",
                        type: "POST",
                        data: { 
                            booking_id: bookingId,
                            credit_refund: credit_refund,
                            member_id: member_id,
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

            $("#cancelThisBookingSpecial").click(function(){

                let bookingId = $(this).data("booking-id");
                let credit_refund = $(this).data("refund-credit");
                let member_id = $("#member_id").val();
                let credit = $("#credit").val();

                if (confirm("Are you sure you want to cancel this booking?")) {

                    $(".loader-overlay").addClass("display");

                    $.ajax({
                        url: "admin-cancel-booking.php",
                        type: "POST",
                        data: { 
                            booking_id: bookingId,
                            credit_refund: credit_refund,
                            member_id: member_id,
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


        });
        */
    </script>
</body>
</html>


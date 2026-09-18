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
    <title>My bookings - Court Booking System</title>
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


    // Fetch user bookings
    if( $memberType == 'non-member'){
        
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE non_member_id = ? ORDER BY date DESC");
        $stmt->execute([$userId]);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    }else{

        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE member_id = ? ORDER BY date DESC");
        $stmt->execute([$userId]);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    
    ?>
    <div class="banner">
        <div class="container">
            <h1>My Bookings</h1>
        </div>
    </div>

    <!-- Credit refund policy -->
    <?php if( $memberType != 'non-member'):
        message_credit_refund_policy();
    endif; ?>

    <!-- booking navigation -->
    <div class="container mt-5">
        <a class="btn btn-primary" href="bookings.php" role="button">All bookings</a>
        <a class="btn btn-outline-primary" href="waitlist.php" role="button">View my waitlist</a>
    </div>

    <!-- booking date filter -->
    <!-- 
    <div class="container mt-3">
        <p class="mb-0">View by date</p>
        <form action="admin-bookings.php" method="post">
            <div class="input-group date-input-group">
                <input type="date" id="all_booking_date" name="all_booking_date" class="form-control" required>
                <div class="date-icon"><i class="ri-calendar-2-line"></i></div>
            </div>
        </form>
    </div> -->

    <div class="container mt-3 mb-5">
        <div class="table-responsive">
        <table class="table table-bordered text-center">
            <thead class="table-dark">
                <tr>
                    <th>Court</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th width="20%">Action</th>
                </tr>
            </thead>
            <tbody  id="allBookingTableBody">
                <?php if (count($bookings) > 0): ?>
                    <?php foreach ($bookings as $booking): ?>
                        <tr id="booking-<?= $booking['id'] ?>">
                            <td><?= htmlspecialchars($booking['court']) ?></td>
                            <td><?= lsc_format_date('db_to_readable',$booking['date']) ?></td>
                            <td><?= htmlspecialchars($booking['timeslot']) ?></td>
                            <td>
                                <?php 
                                    $booking_status = $booking['booking_status'];
                                    echo get_booking_status_badge($booking_status);
                                ?>
  
                            </td>
                            <td>
                                <?php 
                                $transaction_type = $booking['payment'];
                                $transaction_id = $booking['transaction_id'];

                                $ts_stmt = $pdo->prepare("SELECT * FROM transactions WHERE transaction_id = ?");
                                $ts_stmt->execute([$transaction_id]);
                                $transaction = $ts_stmt->fetchAll(PDO::FETCH_ASSOC);

                                $transaction_amount = $transaction[0]['transaction_amount'];
                                $slip_url = $transaction[0]['slip_url'];

                                echo '<span class="fw-bold">'. $transaction_amount . ' THB</span><br>';

                                get_booking_payment_info($transaction_type, $transaction_amount, $slip_url);
                                ?>
                            </td>
                            <td width="20%">
                                <?php 
                                    $booking_date = $booking['date'];
                                    $booking_time = $booking['timeslot'];

                                    // get_booking_date_compare() is legacy here and only used for the "already passed" message.
                                    // Refund eligibility must come from lsc_is_booking_refund_eligible().
                                    $get_booking_date_compare = get_booking_date_compare($booking_date, $booking_time);
                                    $refund_window_open = lsc_is_booking_refund_eligible($booking_date, $booking_time);

                                    if( $get_booking_date_compare[1] == true){
                                        ?>
                                        <div class="alert alert-warning" role="alert">
                                        This booking has already passed booking date
                                        </div>
                                        <?php 
                                    }else{

                                        if(!$refund_window_open && $booking['booking_status'] != 'cancelled' ){
                                        ?>
                                        <div class="alert alert-warning" role="alert">
                                        If you cancel this booking, <span class="fw-bold">YOU WILL NOT BE REFUNDED</span> because it is less than 48 hours before the booked session.
                                        </div>
                                        <?php
                                        }
                                        ?>
                                        

                                        <button class="btn btn-danger btn-sm cancel-btn" 
                                            data-id="<?= $booking['id'] ?>"  
                                            data-booking-type="<?= $booking['booking_type'] ?>"
                                            data-user-id="<?php echo $userId; ?>"
                                            <?= ($booking['booking_status'] == 'cancelled')? 'disabled' : 'normal-cancel' ?>>

                                            <?= ($booking['booking_status'] == 'cancelled')? 'Cancelled' : 'Cancel' ?>

                                        </button>

                                        <?php 

                                    }

                                    ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">No bookings found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    
    <!-- Loading -->
    <div class="loader-overlay pt-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-10 col-md-6">
                    <div class="inner-loader bg-white text-center" id="loaderHtml">
                        <img src="images/ball_loading.gif" alt="" width="100">
                        <p>Please wait...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        
        $(document).ready(function () {
            $(".cancel-btn").click(function () {
                let bookingId = $(this).data("id");
                let bookingType = $(this).data("booking-type");
                let userId = $(this).data("user-id");

                if (confirm("Are you sure you want to cancel this booking?")) {
                    $(".loader-overlay").addClass("display");
                    $.ajax({
                        url: "cancel_booking.php",
                        type: "POST",
                        data: { booking_id: bookingId, bookingType: bookingType, userId: userId },
                        success: function (response) {

                            $("#loaderHtml").html(response);

                            window.setTimeout(function() {
                                window.location.href = 'bookings.php';
                            }, 1000);

                            /*
                            if (response === "success") {

                                window.setTimeout(function() {
                                    window.location.href = 'bookings.php';
                                }, 1000);
                                
                            } else {
                                alert("Error: " + response);
                            }
                            */
                            
                        }
                    });
                }
            });
        });
        
    </script>
</body>
</html>

<?php
require_once __DIR__ . '/includes/auth.php';
$lsc_me = lsc_require_admin('redirect');
// Database connection
require 'config.php';
require_once 'includes/booking-functions.php';


//Param court booking type 
$param_booking_type = $_GET['param_booking_type'] ?? 'junior_academy';
$allowed_academy_booking_types = ['junior_academy', 'adult_clinic', 'tennis_camp', 'tournament'];
if (!in_array($param_booking_type, $allowed_academy_booking_types, true)) {
    $param_booking_type = 'junior_academy';
}
?>

<!-- index.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make a booking - Court Booking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css"rel="stylesheet" />
    <link rel="stylesheet" href="style.css?v=<?= date('Ymd') ?>">
    <link href="bs-overwrite.css" rel="stylesheet">
</head>
<body class="page-id-1">
    <?php include "menu.php"; ?>
    <div class="banner">
        <div class="container">
            <h1>Make a Booking</h1>
        </div>
    </div>

    <div class="container"><form id="bookingForm" enctype="multipart/form-data">

        <input type="hidden" name="member_type" id="member_type" value="admin">
        <input type="hidden" name="member_id" id="member_id" value="<?php echo $userId; ?>">
        <input type="hidden" name="booking_type" id="booking_type" value="<?= ($param_booking_type) ? $param_booking_type : 'member booking' ?>">

        <div class="row mt-5">
            <div class="col-12 col-md-9">
                <?php if ( $memberType == 'admin') : ?>
                <!-- Admin Menu -->
                <section class="adminBookingMenu">
                    <?php
                     include "modules/admin-booking-menu.php"; 
                    ?>
                </section>
                    
                <?php endif; ?>
                <!-- date --> 
                <section class="selectTimeCourt-section mb-3">
                    <?php
                     include "modules/select-date.php"; 
                    ?>
                </section>
                <!-- time table --> 
                 <section class="selectTimeCourt-section">
                    <?php
                     include "modules/time-table.php"; 
                    ?>
                 </section>

                 <!-- 
                 <section class="selectCoachOption">
                    <?php
                    /*
                     include "modules/select-coach-option.php"; 
                     */
                    ?>
                 </section>
                --> 
            </div>
            <div class="col-12 col-md-3">
                <div class="card-summary-wrapper sticky-top mb-5">
                    <div class="card shadow-lg border" style="--bs-border-opacity: .5;">
                        <div class="card-body">
                            <h2 class="fs-5 fw-bold text-primary-emphasis">Booking summary</h2>

                            <div class="booking-summary-body mt-2">
                                <div class="booking-summary-list">
                                    <small class="text-body-tertiary">Date</small>
                                    <p class="summary-text fw-bold" id="SummaryDate">
                                        <?= date("F j, Y") ?>
                                    </p>
                                </div>
                                <div class="booking-summary-list">
                                    <small class="text-body-tertiary">Time</small>
                                    <p class="summary-text fw-bold" id="SummaryTime">
                                        <em class="text-body-tertiary">Select time</em>
                                    </p>
                                </div>
                                <div class="booking-summary-list">
                                    <small class="text-body-tertiary">Court</small>
                                    <p class="summary-text fw-bold" id="SummaryCourt">
                                        <em class="text-body-tertiary">Select court</em>
                                    </p>
                                </div>

                                <div class="make-booking-cta-wrapper pt-5">
                                    <input type="hidden" name="court_1" id="court_1" value="">
                                    <input type="hidden" name="court_2" id="court_2" value="">
                                    <input type="hidden" name="time_1" id="time_1" value="">
                                    <input type="hidden" name="time_2" id="time_2" value="">
                                    
                                    <input type="hidden" name="all_selected_courts" id="all_selected_courts" value="">
                                    <input type="hidden" name="all_selected_times" id="all_selected_times" value="">
                                    <input type="hidden" name="session_fees" id="session_fees" value="0">
                                    
                                    <input type="hidden" name="total_fee" id="total_fee"  value="0">
                                    
                                    <input type="hidden" name="admin_view" id="admin_view" value="<?= ($memberType == 'admin')? 'true': 'false' ?>">

                                    <button type="button" class="btn btn-primary btn-lg w-100" id="adminBookButton">
                                        <i class="ri-calendar-2-line"></i> Make a booking
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Bank Transfer Modal --> 
        <?php
            include "modules/modal-bank-transfer.php"; 
        ?>

        <!-- Credit payment conditions --> 
        <?php
            include "modules/modal-credit-condition.php"; 
        ?>

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

    </form></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script src="app.js?v=<?= filemtime(__DIR__ . '/app.js') ?>"></script>

</body>
</html>

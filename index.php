<?php
date_default_timezone_set('Asia/Bangkok');
// Database connection
require 'config.php';
require_once 'includes/booking-functions.php';

$change_password = $_GET['change_password'];
//Param court booking type 
$param_booking_type = $_GET['param_booking_type'];

//If not admin then process booking_type

?>

<!-- index.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Court Booking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css"rel="stylesheet" />
    <link rel="stylesheet" href="style.css?v=<?= date('Y-m-d') ?>">
    <link href="bs-overwrite.css" rel="stylesheet">
</head>
<body class="page-id-1">
    <?php include "menu.php"; 
    if( $memberType == 'non-member' ){
        $param_booking_type = 'non-member';
    }

    $booking_window_open = lsc_is_booking_window_open_for_member($memberType);
    
    ?>
    <div class="banner">
        <div class="container">
            <h1>Make a Booking</h1>
        </div>
    </div>

    <?php /* if( $operating_indic == 'not_booking_time' ) : 
        //Booking time announcement
        ?>

        <div class="container mt-5">
            <div class="alert alert-warning" role="alert">
                <h4 class="alert-heading">Outside of booking time</h4>
                <p>Unfortunately, our booking has not been opened for booking yet.</p>
                <hr>
                <h3 class="fw-bold">Please come back to make a booking at 7:00 am</h3>
                <p>Thank you for understanding.</p>
            </div>
        </div>


    <?php endif; */ ?>


    <?php 
    //Check if current member is expired 
    if( $memberType != 'non-member'){
        $memberDataExpiry = $memberData['member_expiration'];
        $currentMemberTime = date("H:i:s");
        $convertedMemberDateTime = $memberDataExpiry . " " .$currentMemberTime;
        $converted_date_time = new DateTime($convertedMemberDateTime);
        $currentTime = new DateTime();

        //Check if it is already passed
        if( $converted_date_time < $currentTime){
            $member_expiry_date_passed = true;
        }else{
            $member_expiry_date_passed = false;
        }
    }

    ?>

<?php 
if( $member_expiry_date_passed == true){
?>
<div class="container mt-5">
    <div class="alert alert-warning" role="alert">
    <h4 class="alert-heading">Your membership has expired.</h4>
    <p>To renew your membership please contact the reception.<br>Thank you in advance</p>
    <!-- <hr>
    <p class="mb-0">Here are how you can extend your membership</p>
    <ol>
        <li>Contact our staff at the reception to extend your membership</li>
        <li>
            Extend your membership online <br>
            <a href="extend-membership.php" class="btn btn-dark">Extend membership online now</a>
        </li>
    </ol> -->
    </div>
</div>

<?php }else if( !$booking_window_open ){ ?>
<div class="container mt-5">
    <div class="alert alert-warning" role="alert">
        <h4 class="alert-heading">Outside of booking time</h4>
        <p>Booking is closed between 12:00 am and 6:00 am.</p>
        <hr>
        <h3 class="fw-bold">Please come back after 6:00 am</h3>
    </div>
</div>

<?php }else{ ?>



    <div class="container"><form id="bookingForm" enctype="multipart/form-data">

        <?php if( $memberType == 'non-member'): ?>
        <input type="hidden" name="member_name" id="member_name" value="<?= $_SESSION["member_fullname"] ?>">
        <input type="hidden" name="non_member_email" id="non_member_email" value="<?= $_SESSION["member_email"] ?>">
        <input type="hidden" name="non_member_phone" id="non_member_phone" value="<?= $_SESSION["member_phone"] ?>">
        <?php endif; ?>

        <input type="hidden" name="member_type" id="member_type" value="<?= $memberType ?>">
        <input type="hidden" name="member_id" id="member_id" value="<?php echo $userId; ?>">
        <input type="hidden" name="booking_type" id="booking_type" value="<?= ($param_booking_type == 'non-member')? 'non-member': 'member booking' ?>">

        <div class="row mt-5">
            <div class="col-12 col-md-9">

                <?php if( $memberType == 'admin') : ?>
                <!-- Admin Menu -->
                <section class="adminBookingMenu">
                    <?php
                     include "modules/admin-booking-menu.php"; 
                    ?>
                </section>
                <?php endif; ?>

                <?php if( $param_booking_type == 'non-member'): ?>
                <section class="selectMemberType">
                    <?php
                     include "modules/select-member-type.php"; 
                    ?>
                </section>
                <?php else: ?>
                    <input type="hidden" name="daily_member_type_input" id="daily_member_type_input">
                <?php endif; ?>
                
                <?php if ( $memberType == 'admin' && $param_booking_type == '') : ?>
                <!-- Admin Tool bar -->
                <section class="adminToolBar">
                    <?php
                     include "modules/admin-tool-bar.php"; 
                    ?>
                </section>
                <?php endif; ?>
                
                <?php if( $param_booking_type == 'non-member' && $memberType == 'admin' ): ?>
                <!-- Admin Non Member bar -->
                <section class="adminToolBar">
                    <?php
                     include "modules/admin-non-member-bar.php"; 
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

                
                

                <?php if( $memberType == 'admin'): ?>
                    <input type="hidden" name="admin_select_member" id="admin_select_member">
                <?php endif; ?>

                 <section class="selectCoachOption">
                    <?php
                     include "modules/select-coach-option.php"; 
                    ?>
                 </section>
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
                                <div class="booking-summary-fee">
                                    <small class="text-body-tertiary">Total booking fee</small><br>
                                    <span class="fs-4 fw-bold text-primary" id="SummaryBookingFee">
                                        <?php echo ( $memberType == 'non-member' || $param_booking_type == 'non-member')? number_format(lsc_price_daily_fee('individual'), 2) : '0'; ?>
                                    </span> <span class="fs-6 text-body-tertiary">THB</span>
                                    <input type="hidden" name="firstBookingFee" id="firstBookingFee" value="0">
                                    <input type="hidden" name="secondBookingFee" id="secondBookingFee" value="0">
                                </div>

                                <h3 class="fs-5 fw-bold text-primary-emphasis mt-3">Payment option</h3>
                                <div class="payment-option-wrapper">
                                    <?php if ( $memberType == 'admin') : ?>
                                        <div class="paymention-option payment-cash">
                                        <div class="paymention-accordion-title" id="paymentCashTitle" data-accordion="paymentCash">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="payment_option" id="payment_option_cash" value="cash" data-accordion="paymentCash" checked>
                                                <label class="form-check-label" for="payment_option_cash">
                                                    Cash
                                                </label>
                                            </div>
                                            
                                        </div>
                                        <div class="payment-accordion-content always-active" id="paymentCash" data-accordion="paymentCash">
                                            <input type="checkbox" name="not_paid_yet[]" id="not_paid_yet" value="Not paid yet">
                                            <label for="not_paid_yet">Not paid yet</label>

                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if(  $memberType != 'non-member'  ): ?>
                                    <!-- Payment as credit -->
                                    <div class="paymention-option payment-credit <?= $memberType ?> <?= $param_booking_type ?>">
                                        <div class="paymention-accordion-title active"  id="paymentCreditTitle" data-accordion="paymentCredit">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="payment_option" id="payment_option_credit" value="credit" data-accordion="paymentCredit"
                                                <?= ($memberCredit>0 && $memberType != 'admin' )? 'checked' : 'disabled' ?>>
                                                <label class="form-check-label" for="payment_option_credit">
                                                    Credit <br>
                                                    <span class="remaining-credit-badge">
                                                        <span class="title-text">Remaining credit</span>
                                                        <span class="remaining-credit-amount fw-bold" id="remaining_credit_payment_type"><?php echo credit_number_sanitize($memberCredit); ?></span>
                                                    </span>

                                                </label>
                                            </div>
                                            
                                            <div class="mt-2">
                                                <button type="button" class="btn btn-link" id="viewCreditConditions">
                                                    <i class="ri-information-fill"></i>Read conditions for credit payment
                                                </button>
                                            </div>

                                            <!-- notice if credit is 0 -->
                                            <input type="hidden" name="member_current_credit" id="member_current_credit" value="<?= $memberCredit ?>">
                                            <div class="credit-warning <?= ($memberCredit>0)? 'd-none' : 'd-block' ?>" id="creditWarning">
                                                <div class="alert alert-warning mt-2" role="alert">
                                                    <b>You have run out of credit/not enough credit</b><br>
                                                    You can refill credit in order to use it to make a booking<br>
                                                    <a href="add-credit.php" class="btn btn-outline-primary">Refill credit</a>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="payment-accordion-content active" id="paymentCredit"  data-accordion="paymentCredit">
                                            <span class="credit-amount" id="creditAmount">0</span> credits will be deducted from your account
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if( $memberType == 'non-member'): ?>
                                    <!-- Payment bank transfer -->
                                    <div class="paymention-option payment-transfer">
                                        <div class="paymention-accordion-title <?= ( $memberType == 'non-member')? 'active' : '' ?>" id="paymentTransferTitle" data-accordion="paymentTransfer">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="payment_option" id="payment_option_qr" value="qr" data-accordion="paymentTransfer"
                                                <?= ( $memberType == 'non-member')? 'checked' : '' ?>>
                                                <label class="form-check-label" for="payment_option_qr">
                                                    Direct bank transfer
                                                </label>
                                            </div>
                                        </div>

                                        <?php // if( $memberType == 'non-member'): ?>
                                        <div class="ps-2 pe-2">
                                            <div class="alert alert-warning mt-3 text-center" role="alert">
                                                If you don't have a thai bank account, please contact the reception or contact us via line account<br>
                                                <a href="https://line.me/ti/p/lesmashclub" target="_blank"><img src="images/line_icon.png" alt="Line Account" width="24"></a>&nbsp;
                                                <a href="https://line.me/ti/p/lesmashclub" target="_blank">lesmashclub</a>
                                            </div>
                                        </div>
                                        <?php // endif; ?>
                                       
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if( $memberType != 'admin'): ?>
                                    <!-- Payment later -->
                                    <div class="paymention-option payment-later hide" id="paymentLaterOptionWrapper">
                                        <div class="paymention-accordion-title" id="paymenLaterTitle" data-accordion="paymenLater">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="payment_option" id="payment_option_later" value="Pay later" data-accordion="paymenLater" >
                                                <label class="form-check-label" for="payment_option_later">
                                                    Pay at confirmation (Waitlist only)
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    
                                </div>

                                <div class="make-booking-cta-wrapper pt-5">
                                    <input type="hidden" name="current_credit" id="current_credit" value="<?= ($memberCredit)? $memberCredit : 0 ?>">
                                    <input type="hidden" name="all_selected_courts" id="all_selected_courts" value="">
                                    <input type="hidden" name="all_selected_times" id="all_selected_times" value="">
                                    <input type="hidden" name="all_booking_fees" id="all_booking_fees" value="">
                                    
                                    <input type="hidden" name="total_fee" id="total_fee"  value="0">
                                    
                                    <input type="hidden" name="admin_view" id="admin_view" value="<?= ($memberType == 'admin')? 'true': 'false' ?>">
                                    <input type="hidden" name="current_admin_member_number" id="current_admin_member_number" value="<?= ($memberType == 'admin') ? htmlspecialchars((string) $memberNumber) : '' ?>">
                                    
                                    <button type="button" class="btn btn-primary btn-lg w-100" id="bookButton">
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
                    <div class="col-10 col-md-6">
                        <div class="inner-loader bg-white text-center">
                            <img src="images/ball_loading.gif" alt="" width="100">
                            <p>Please wait...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </form></div>

    <!-- Hidden field to carry the phone number forward with the form if needed -->
    <input type="hidden" name="member_phone_confirmed" id="member_phone_confirmed" value="<?= htmlspecialchars($memberPhone, ENT_QUOTES) ?>">

    <!-- Waitlist Modal -->
    <div class="modal fade" id="waitlistPhoneModal" tabindex="-1" aria-labelledby="waitlistPhoneModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-3">
        <div class="modal-header">
            <h5 class="modal-title" id="waitlistPhoneModalLabel">Confirm Your Phone Number</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
            

            <!-- Phone number -->
            <div id="modalWaitlistPhoneConfirmWrapper">
                <p>Please confirm or update your phone number for the waitlist:</p>
                <div class="alert alert-info" role="alert">
                    We'll notify you via SMS as soon as a timeslot becomes available for your waitlist. Make sure this phone number is accurate.
                </div>
                <div class="mb-3">
                <label for="waitlistPhoneInput" class="form-label">Phone number</label>
                <input
                    type="tel"
                    class="form-control"
                    id="waitlistPhoneInput"
                    value="<?= htmlspecialchars($memberPhone, ENT_QUOTES) ?>"
                    autocomplete="tel"
                    placeholder="e.g. 08xxxxxxxx"
                >
                <div class="form-text">You can edit this number before confirming.</div>
                </div>
                <input type="hidden" id="waitlistUserId" value="<?= $userId ?>">
            </div>

            <!-- Phone number -->

            <!-- Credit -->
            <div class="alert alert-warning d-flex align-items-center d-none" role="alert" id="modalWaitlistCreditLowAlert">
                <div>
                    <?php if( $memberType != 'admin'){
                        $credit_add_link = 'add-credit.php';
                        $credit_refill_note_title = 'Your current credit is:';
                    }else{
                        $credit_add_link = 'admin-add-credit.php';
                        $credit_refill_note_title = 'Member current credit is:';
                    }?>

                    <?= $credit_refill_note_title ?>
                    <h4><?= $memberCredit ?></h4>
                    <hr>
                    <b>Please make sure that the credit is enough.</b> <br>
                    As soon as the waitlist is available, the booking will be made and your credit will be deducted at the confirmation.
                    
                </div>
            </div>
            <!-- Credit -->
        </div>

        <div class="modal-footer" id="modalWaitlistConfirmCTAs">
            <button type="button" class="btn btn-outline-secondary" id="waitlistCancelBtn" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" id="waitlistConfirmBtn">Confirm Phone Number</button>
            <button type="button" class="btn btn-success" id="waitlistSaveConfirmBtn">Save &amp; Confirm</button>
        </div>
        </div>
    </div>
    </div>
    <!-- Waitlist Modal -->

    <!-- Hidden field to carry the phone number forward with the form if needed -->
    <input type="hidden" name="current_member_credit_chk" id="current_member_credit_chk" value="<?= $memberCredit ?>">

    <!-- Warning Credit Low -->
    <div class="modal fade" id="lowCreditModal" tabindex="-1" aria-labelledby="lowCreditModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-3">
        <div class="modal-header">
            <h5 class="modal-title" id="lowCreditModalLabel">Your credit is low</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
            <div class="alert alert-warning d-flex align-items-center" role="alert">
                <div>
                    Your current credit is:
                    <h4><?= $memberCredit ?></h4>
                    <hr>
                    <b>Please make sure that your credit is enough.</b> <br>
                    As soon as your waitlist is available, the booking will be made and your credit will be deducted at the confirmation.
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" id="lowCreditModalLabelCancelBtn" data-bs-dismiss="modal">Cancel</button>
            <a href="add-credit.php" class="btn btn-primary" id="addCreditWarningBtn">Add credit</a>
        </div>
        </div>
    </div>
    </div>
    <!-- Warning Credit Low -->

    <!-- System Maintenance Notification -->
    <!-- <div class="modal fade" id="systemMaintenanceModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

          <div class="modal-header">
            <h5 class="modal-title">📢 Scheduled System Maintenance</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body">

            <p class="mb-2">
              We are currently updating our systems to serve you better. During this time, you may experience intermittent errors while booking.<br>
              If you encounter any issues or need immediate assistance with bookings, please reach out to our team:

            </p>

            <div class="row">
                <div class="col-auto">
                     Whatsapp:<br>
                    <img src="images/whatsapp.jpg" alt="" width="150">
                </div>
                <div class="col-auto">LINE ID: <a href="https://line.me/ti/p/~lesmashclub" target="_blank">lesmashclub</a><br>
                    <img src="images/line.png" alt="" width="150">
                </div>
            </div>

          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
          </div>

        </div>
      </div>
    </div> -->


    <!-- Pricing Notification Modal -->
    <!-- <div class="modal fade" id="pricingNoticeModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

          <div class="modal-header">
            <h5 class="modal-title">📢 New Pricing Notice</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body">

            <p class="mb-2">
              Notification of new pricing for court booking as of 
              <strong>1st January 2026</strong>:
            </p>

            <ul class="mb-0">
              <li><strong>Off Peak time (6AM - 6PM):</strong> <?= LSC_COURT_FEE_DAY ?> THB</li>
              <li><strong>Peak time (6PM - 10PM):</strong> <?= LSC_COURT_FEE_EVENING ?> THB</li>
              <li><strong>Assistant coach fee:</strong> <?= LSC_COACH_FEE ?> THB</li>
            </ul>

          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
          </div>

        </div>
      </div>
    </div> -->

<?php 

} 
//End Check member expiration?>

<?php 
//Function to convert timeslot to readable 
    function timeslot_convert_time_home(string $timeslot): ?string
    {
        // Expect formats like "6-7am", "7-8pm", "10-11am" (case-insensitive)
        $pattern = '/^\s*(\d{1,2})\s*-\s*\d{1,2}\s*(am|pm)\s*$/i';

        if (!preg_match($pattern, $timeslot, $matches)) {
            return null;
        }

        $hour = (int)$matches[1];        // first hour
        $ampm = strtolower($matches[2]); // am or pm

        if ($ampm === 'pm' && $hour < 12) {
            $hour += 12;
        } elseif ($ampm === 'am' && $hour === 12) {
            // 12am is 00:00 in 24h
            $hour = 0;
        }

        return sprintf('%02d:00:00', $hour);
    }
    //Check all waitlist for today and update status
    $todayDateForWaitList = date('Y-m-d');


    // Waitlist Check SQL
    $waitlist_chk_sql = "
        SELECT *
        FROM wait_list
        WHERE date = :date 
        AND waitlist_status = :status
        ORDER BY created_at ASC
    ";
    $waitlist_chk_params = [
            ':date'   => $todayDateForWaitList,
            ':status' => 'pending'
        ];
    $stmt_waitlist_chk = $pdo->prepare($waitlist_chk_sql);
    $stmt_waitlist_chk->execute($waitlist_chk_params);

    $todayWaitlistRows = $stmt_waitlist_chk->fetchAll(PDO::FETCH_ASSOC);

    
    foreach ($todayWaitlistRows as $waitlist) {
        $waitlistTimeslot = $waitlist['timeslot'];
        $homeWaitlistId = $waitlist['wait_list_id'];

        // Build full datetime: booking_date + " " + timeslot
        $waitlist_time_chk = new DateTime($todayDateForWaitList . " " . timeslot_convert_time_home($waitlistTimeslot));
        $waitlist_chk_now  = new DateTime();

        // Calculate difference: future = positive, past = negative
        $waitlist_chk_time_diff = $waitlist_time_chk->getTimestamp() - $waitlist_chk_now->getTimestamp();
        
        if ($waitlist_chk_time_diff < 2 * 3600) {

            
            // Prepare and execute update query
            $waitlist_update_home_sql = "UPDATE wait_list 
                    SET waitlist_status = :status
                    WHERE wait_list_id = :id";

            $waitlist_update_home_stmt = $pdo->prepare($waitlist_update_home_sql);
            $exec_waitlist_home_update = $waitlist_update_home_stmt->execute([
                ':status' => 'expired',
                ':id'     => $homeWaitlistId
            ]);

        }
    }

    //Check waitlist 
  if( $userId && $memberType != 'admin'){

    $waitlist_sql = "
        SELECT *
        FROM wait_list
        WHERE waitlist_status = 'pending'
        AND member_id = :member_id;
    ";

    $waitlit_stmt = $pdo->prepare($waitlist_sql);
    $waitlit_stmt->execute([':member_id' => $userId]);
    $waitlist_rows = $waitlit_stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($waitlist_rows) { 
    
    ?>

    <!-- Waitlist notification Modal -->
    <div class="modal fade" id="waitlistNotificationModal" tabindex="-1" aria-labelledby="waitlistNotificationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-3">
        <div class="modal-header">
            <h5 class="modal-title" id="waitlistNotificationModalLabel">You have available slot(s) in your waitlist</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
            <p>You have available slot(s) in your waitlist:</p>
            <table class="table table-bordered text-center">
                <thead class="table-dark">
                    <tr>
                        <th>Date</th>
                        <th>Requested Time</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($waitlist_rows as $waitlist_row): ?>
                    <tr>
                        <td>
                            <?php 
                            $waitlistNotiFormatted = (new DateTime($waitlist_row['date']))->format("d F, Y");
                            echo $waitlistNotiFormatted;
                            ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($waitlist_row['timeslot']) ?>
                        </td>
                        <td>
                            <?php 
                                if( $waitlist_row['waitlist_status'] == 'pending'){
                                    echo '<span class="badge text-bg-warning">Pending Confirmation</span>';
                                }else if( $waitlist_row['waitlist_status'] == 'confirmed' ){
                                    echo '<span class="badge text-bg-success">Confirmed</span>';
                                }else if( $waitlist_row['waitlist_status'] == 'expired' ){
                                    echo '<span class="badge text-bg-secondary">Expired</span>';
                                }else{
                                    echo '<span class="badge text-bg-dark">Waiting...</span>';
                                }
                            ?>
                        </td>
                        <td>
                            <?php if( $waitlist_row['waitlist_status'] == 'pending'){ ?>
                                <a href="waitlist.php?waitlist_confirm=true&waitlist_id=<?= $waitlist_row['wait_list_id'] ?>&free_court=<?= $waitlist_row['free_court'] ?>&date=<?= $waitlist_row['date'] ?>&timeslot=<?= $waitlist_row['timeslot'] ?>&book_id=<?= $waitlist_row['waitlist_booking_id'] ?>" class="btn btn btn-success">Confirm Booking</a><br>
                                <span class="text-primary">Your credit will be deducted</span>
                                <hr>

                                <a href="waitlist.php?waitlist_confirm=false&waitlist_id=<?= $waitlist_row['wait_list_id'] ?>&free_court=<?= $waitlist_row['free_court'] ?>&date=<?= $waitlist_row['date'] ?>&timeslot=<?= $waitlist_row['timeslot'] ?>&book_id=<?= $waitlist_row['waitlist_booking_id'] ?>" class="btn btn-outline-danger">Cancel Booking Confirmation</a><br>
                                <span class="text-primary">Your credit WILL NOT be duducted</span>
                                
                            <?php } ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- <p>
                You can view your waitlist and confirm these slots to secure the booking.
            </p> -->

        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" id="waitlistNotificationCancelBtn" data-bs-dismiss="modal">Cancel</button>
            <a href="waitlist.php" class="btn btn-success" id="waitlistViewBtn">View Waitlist</a>
        </div>
        </div>
    </div>
    </div>
    <!-- Waitlist notification Modal -->
     <input type="hidden" id="waitlist_check" name="waitlist_check" value="waitlist_true">
    

    <?php 
    //if waitlist row exist 
    }else{
        //echo 'no waitlist';
        ?>
        <input type="hidden" id="waitlist_check" name="waitlist_check" value="waitlist_false">
        <?php 
    }
    
    

  }
?>

    <!-- large court imag modal -->
    <div class="modal fade" id="courtLayoutModal" tabindex="-1" aria-labelledby="courtLayoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
        <div class="modal-header">
            <h1 class="modal-title fs-5" id="courtLayoutModalLabel">Court Layout</h1>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <img src="images/court_layout.png" alt="" width="100%">
        </div>
        </div>
    </div>
    </div>
    <!-- large court imag modal -->


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script src="app.js?v=<?= filemtime(__DIR__ . '/app.js') ?>"></script>
    <script src="booking_functions/bookings.js?v=<?= filemtime(__DIR__ . '/booking_functions/bookings.js') ?>"></script>
    <?php if( $change_password == 'true'){ ?>
        <script>
            alert("Your password has been successfully changed.");
        </script>
    <?php } ?>

    <script>
        //Loading price notification popup 
        if( document.getElementById("member_type").value != 'admin' ){
            // document.addEventListener('DOMContentLoaded', function() {
            //     var pricingNoticeModal = new bootstrap.Modal(document.getElementById('pricingNoticeModal'));
            //     pricingNoticeModal.show();
            // });

            document.addEventListener('DOMContentLoaded', function() {
                var systemMaintenanceModal = new bootstrap.Modal(document.getElementById('systemMaintenanceModal'));
                systemMaintenanceModal.show();
            });
        }
    </script>

</body>
</html>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require 'config.php';
    require_once 'includes/functions.php';
    include 'includes/booking-functions.php';

    if (!lsc_is_booking_window_open_for_member('member')) {
        lsc_render_booking_window_closed_message('index.php');
        exit;
    }

    if (!lsc_is_booking_date_within_advance_window($_POST['date'], 'member')) {
        lsc_render_booking_advance_limit_message('index.php', 7);
        exit;
    }

    $booking_type           = $_POST['booking_type'];
    $date                   = $_POST['date'];

    $member_id              = $_POST['member_id'];
    $daily_member_type      = $_POST['daily_member_type'];
    $daily_member_type_lwr  = strtolower($daily_member_type);
    $coach_option           = $_POST['coach_option'] ?? '';
    $extra_player           = $_POST['extra_player'] ?? 0;
    $coach_name             = $_POST['coach_name'] ?? '';

    $all_selected_courts    = $_POST['all_selected_courts'];
    $all_selected_times     = $_POST['all_selected_times'];
    
    $courts                 = explode(",", $all_selected_courts);
    $times                  = explode(",", $all_selected_times);
    
    $transaction_amount     = $_POST['transaction_amount'];
    $payment_type           = $_POST['payment_type'];
    $credit                 = $_POST['credit'] ?? 0;
    $file                   = $_FILES['slip_upload'] ?? null;
    $transaction_id         = null;
    $slip_url               = '';
    $booking_note           = '';
    if( $payment_type == 'qr'){
        $booking_status     = 'pending';
    }else{
        $booking_status     = 'approved';
    }
    
    $non_member_info        = "";
    $waitlist_note          = ",,,".$daily_member_type . ",".$coach_option. "," .$extra_player;

    //Check duplicated and Check if member has booked more than 2 sessions today
    $check_duplicated = 0;
    $check_more_than_2hours = 0;
    foreach ($courts as $key => $court_value) {
        //$all_bookings = $all_booking_query->fetchAll(PDO::FETCH_ASSOC);
        $court_numb         = $court_value;
        $time_val           = $times[$key];

        //Check if more than 2 hours
        $query_booking_status = 'cancelled';
        $by_date_booking_query = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE date = ? AND member_id = ? AND NOT booking_status = ?");
        $by_date_booking_query->execute([$date, $member_id, $query_booking_status]);
        $count_2hours_booking = $by_date_booking_query->fetchColumn();
        $check_more_than_2hours += $count_2hours_booking;

        //Check duplicate
        $all_booking_query = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE court = ? AND timeslot = ? AND date = ? AND NOT booking_status = ?");
        $all_booking_query->execute([$court_numb, $time_val, $date, $query_booking_status]);
        $count_booking = $all_booking_query->fetchColumn();
        $check_duplicated += $count_booking;


    }

if( $check_duplicated > 0  ){
?>
    <div class="error-message text-center text-danger">
        <span class="fs-2">
            <i class="ri-error-warning-fill"></i>
        </span>
        <br>
        <p>Looks like another member has just booked the court/time you selected.<br><br>Please select another court/time.</p>
        <hr>
        <a href="index.php" class="btn btn-primary">Try again</a>
    </div>

    <?php 
    exit();
}
/*
if( $check_duplicated > 0 ){
    ?>
    <div class="error-message text-center text-danger">
        <span class="fs-2">
            <i class="ri-error-warning-fill"></i>
        </span>
        <br>
        <p>Looks like another member has just booked the court/time you selected.<br><br>Please select another court/time.</p>
        <hr>
        <a href="index.php" class="btn btn-primary">Try again</a>
    </div>

    <?php 
}elseif( $check_more_than_2hours >= 2 ){
*/
//CHECK Max booking hours per member type
    $max_hour_compare = 2;
    if( $daily_member_type_lwr == 'individual' || $daily_member_type_lwr == 'junior' ){
        $max_hour_compare = 2;
    }else if( $daily_member_type_lwr == 'couple' || $daily_member_type_lwr == '1 adult 1 child' ){
        $max_hour_compare = 3;
    }else if( $daily_member_type_lwr == 'family' ){
        $max_hour_compare = 4;
    }else{
        $max_hour_compare = 240;
    }

if( $check_more_than_2hours > $max_hour_compare ){
    ?>

    <div class="error-message text-center text-danger">
        <span class="fs-2">
            <i class="ri-error-warning-fill"></i>
        </span>
        <br>
        <p>You have reached the maximum booking of <?= $max_hour_compare ?> sessions per day.<br>You can make a booking for another date.</p>
        <hr>
        <a href="index.php" class="btn btn-primary">Try again</a>
    </div>

    <?php 
}else{

    if ($payment_type === 'credit' && (float) $transaction_amount > 0) {
        $stmt_member_credit = $pdo->prepare("SELECT credit FROM members WHERE id = ? LIMIT 1");
        $stmt_member_credit->execute([$member_id]);
        $member_credit_balance = (float) $stmt_member_credit->fetchColumn();

        if ($member_credit_balance < (float) $transaction_amount) {
            ?>
            <div class="error-message text-center text-danger">
                <span class="fs-2">
                    <i class="ri-error-warning-fill"></i>
                </span>
                <br>
                <p>You do not have enough credit to make this booking.<br>Please refill credit before booking.</p>
                <hr>
                <a href="add-credit.php" class="btn btn-outline-primary">Refill credit</a>
                <a href="index.php" class="btn btn-primary">Back</a>
            </div>
            <?php
            exit();
        }
    }


    // UPLOAD FILE IF TRANSACTION AMOUNT IS NOT 0
    if( $transaction_amount > 0 &&  $file ){

        /* $uploadDir          = 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true); // Create the directory if it doesn't exist
        }

        //Prepare slip 
        $fileTmpPath        = $file['tmp_name'];
        $fileName           = $file['name'];
        $fileExtension      = pathinfo($fileName, PATHINFO_EXTENSION);
        $uniqueFileName     = date('Y_m_d').'_'.uniqid('slip_010_', true) . '.' . $fileExtension;
        $fileDestination    = $uploadDir . $uniqueFileName;
        $file_move_result   = move_uploaded_file($fileTmpPath, $fileDestination);
        $slip_url           = $uniqueFileName;
        */

        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true); // Create the directory if it doesn't exist
        }

        // Prepare slip
        $fileTmpPath   = $file['tmp_name'];
        $fileName      = $file['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $uniqueFileName = date('Y_m_d') . '_' . uniqid('slip_010_', true) . '.' . $fileExtension;
        $fileDestination = $uploadDir . $uniqueFileName;

        // Resize before saving
        list($width, $height) = getimagesize($fileTmpPath);
        $maxWidth = 1000;

        if ($width > $maxWidth) {
            $ratio = $height / $width;
            $newWidth = $maxWidth;
            $newHeight = $maxWidth * $ratio;

            // Create a new image from original
            switch ($fileExtension) {
                case 'jpg':
                case 'jpeg':
                    $src = imagecreatefromjpeg($fileTmpPath);
                    break;
                case 'png':
                    $src = imagecreatefrompng($fileTmpPath);
                    break;
                case 'gif':
                    $src = imagecreatefromgif($fileTmpPath);
                    break;
                default:
                    die("Unsupported image format.");
            }

            // Create new resized image
            $dst = imagecreatetruecolor($newWidth, $newHeight);

            // Preserve transparency for PNG/GIF
            if ($fileExtension == 'png' || $fileExtension == 'gif') {
                imagecolortransparent($dst, imagecolorallocatealpha($dst, 0, 0, 0, 127));
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
            }

            imagecopyresampled($dst, $src, 0, 0, 0, 0, 
                $newWidth, $newHeight, $width, $height);

            // Save resized image
            switch ($fileExtension) {
                case 'jpg':
                case 'jpeg':
                    imagejpeg($dst, $fileDestination, 90); // 90% quality
                    break;
                case 'png':
                    imagepng($dst, $fileDestination, 6);
                    break;
                case 'gif':
                    imagegif($dst, $fileDestination);
                    break;
            }

            imagedestroy($src);
            imagedestroy($dst);
        } else {
            // If image is already <= 800px, just move it
            move_uploaded_file($fileTmpPath, $fileDestination);
        }

        $slip_url = $uniqueFileName;
        
    }

    // CREATE TRANSACTION
    if( $transaction_amount > 0 ){

        try {

            $transaction_title = 'Booking_' . $member_id;
            $transaction_type = "booking (member)";

            $stmt = $pdo->prepare("INSERT INTO transactions (transaction_title, member_id, non_member_id, non_member_info, transaction_amount, transaction_type, payment_type, slip_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$transaction_title, $member_id, "90002", $non_member_info, $transaction_amount, $transaction_type, $payment_type, $slip_url]);

            $transaction_id = $pdo->lastInsertId();

            
            //If there's extra player, create a transaction
            if($extra_player >= 1){

                $guest_transaction_title = 'Guest transaction';
                $guest_transaction_amount = $extra_player*200;
                $guest_transaction_note = 'Guest transaction';

                $stmt_guest = $pdo->prepare("INSERT INTO transactions (transaction_title, member_id, non_member_id, non_member_info, transaction_amount, transaction_type, payment_type, slip_url, transaction_note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_guest->execute([$guest_transaction_title, $member_id, "90002", $non_member_info, $guest_transaction_amount, $transaction_type, $payment_type, $slip_url, $guest_transaction_note]);

            }
            
            

        }catch (PDOException $e) {
            echo $e->getMessage();
        }

    }
    $transaction_message = "success";

    // MAKE BOOKING

    try {
        foreach ($courts as $key => $court_value) {

            $court_numb         = $court_value;
            $time_val           = $times[$key];

            if( $time_val == '6-7pm' || $time_val == '7-8pm' || $time_val == '8-9pm' || $time_val == '9-10pm' ){
                $this_transaction_price = 280;
            }else{
                $this_transaction_price = 160;
            }


            //If waitlist
            if( $court_numb == 'waitlist'){

                $stmt = $pdo->prepare("INSERT INTO wait_list (member_id, non_member_id,  date, timeslot, member_type, waitlist_note) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$member_id, "90002", $date, $time_val, $booking_type, $waitlist_note] );

                $booking_line_message = "[Waitlist] มีรายการจอง Waitlist ใหม่ \n วันที่: " . format_date_to_readable_text($date) . " เวลา: " . $time_val . "\n ดูที่ https://booking.lesmashclub.com/admin-bookings.php";

            }else{

                //Create a transaction for each booking 
                $this_transaction_title = 'Booking for date ' . format_date_to_readable_text($date) . ', court ' . $court_numb . ' at ' . $time_val;
                $transaction_type = "booking (member)";

                $stmt_ts = $pdo->prepare("INSERT INTO transactions (transaction_title, assoc_transaction_id, member_id, non_member_id, non_member_info, transaction_amount, transaction_type, payment_type, slip_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_ts->execute([$this_transaction_title, $transaction_id, $member_id, "90001", $non_member_info, $this_transaction_price, $transaction_type, $payment_type, $slip_url]);
                
                $this_transaction_id = $pdo->lastInsertId();

                $stmt = $pdo->prepare("INSERT INTO bookings (court, date, timeslot, member_id, non_member_id, booking_status, booking_type, daily_member_type, coach_name, coach_extra_player, booking_note, payment, transaction_id, slip) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
                $stmt->execute([$court_numb, $date, $time_val, $member_id, "90002", $booking_status, $booking_type, $daily_member_type, $coach_name, $extra_player, $booking_note, $payment_type, $this_transaction_id, $slip_url]);

                $this_booking_id = $pdo->lastInsertId();

                $booking_line_message = "[Member] มีรายการจองใหม่ \n วันที่: " . format_date_to_readable_text($date) . "\nCourt: " . $court_numb . " เวลา: " . $time_val . "\nดูที่ https://booking.lesmashclub.com/admin-view-booking.php?booking_id=" . $this_booking_id ;
            }

        }

        $booking_message = "success";

    }catch (PDOException $e) {
        echo $e->getMessage();
    }

    // REDUCE CREDIT 
    if( $payment_type == 'credit' && (float) $transaction_amount > 0 ){
        $stmt = $pdo->prepare("UPDATE members SET credit = credit - ? WHERE id = ? ");
        $result = $stmt->execute([$transaction_amount, $member_id]);
    }

    // SHOW MESSAGE
    if(  $booking_message == 'success'){
        lsc_log('Member Booking', "Created booking(s) for Date: $date, Courts: $all_selected_courts, Timeslots: $all_selected_times. Payment: $payment_type, Amount: $transaction_amount THB.");
        
        $accessToken = LINE_BROADCAST_TOKEN;
        lsc_send_line_broadcast($booking_line_message, $accessToken);
        
   

    ?>

    <div class="success-message text-center text-success">
        <span class="fs-2">
            <i class="ri-checkbox-circle-line"></i>
        </span>
        <br>
        <p>Your booking has been made successfully</p>
        <hr>
        <a href="index.php" class="btn btn-outline-primary">Book again</a>
        <a href="bookings.php" class="btn btn-primary">View all bookings</a>
    </div>

    <?php 
    }else{ ?>

    <div class="error-message text-center text-danger">
        <span class="fs-2">
            <i class="ri-error-warning-fill"></i>
        </span>
        <br>
        <p>There's something wrong with the booking. Please try again.</p>
        <hr>
        <a href="index.php" class="btn btn-primary">Try again</a>
    </div>


    <?php 
    }
} // Check duplicated

}
?>

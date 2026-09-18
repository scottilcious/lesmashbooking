<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require 'config.php';

    $booking_type = $_POST['booking_type'];
    if( $booking_type == ''){
        $booking_type = 'member booking';
    }
    $all_selected_courts = $_POST['all_selected_courts'];
    $all_selected_times = $_POST['all_selected_times'];
    // treat court 
    $courts = explode(",", $all_selected_courts);
    // treat time
    $times = explode(",", $all_selected_times);
    
    $date = $_POST['date'];
    $member_id = $_POST['member_id'];
    $payment_type = $_POST['payment_type'];
    $booking_status = $_POST['booking_status'];
    $daily_member_type = $_POST['daily_member_type'];
    $coach_option = $_POST['coach_option'];
    $coach_name = $_POST['coach_name'];
    $booking_note = $_POST['booking_note'];
    if( $coach_option == 'undefined'){
        $coach_option = null;
    }
    $transaction_amount = $_POST['transaction_amount'];
    $file = $_FILES['slip_upload'] ?? null;

    $admin_view = $_POST['admin_view'] ?? '';
    
    if( $booking_status == ''){
        $booking_status = 'pending';
    }

    if( $payment_type == 'credit' ){
        $booking_status = 'approved';
    }
    
    $transaction_type = 'booking';

    //Get member details
    
    try {
        if( $booking_type == 'non-member'){
            $mb_stmt = $pdo->prepare("SELECT * FROM non_members WHERE id = ?");
        }else{
            $mb_stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
        }
        $mb_stmt->execute([$member_id]);
        $members = $mb_stmt->fetchAll(PDO::FETCH_ASSOC);
        $member_name = $members[0]['first_name']. ' ' .$members[0]['last_name'];
        $member_number = $members[0]['member_number'];

    } catch (PDOException $e) {
        echo $e->getMessage();
    }
    
    $transaction_message = "";
    $booking_message = "";

    //echo $court_1 . "<br>" . $time_1 . "<br><br>" . $court_2 . "<br>" . $time_2;


 
//Upload file
if ($file) {
    $uploadDir = 'uploads/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true); // Create the directory if it doesn't exist
    }
    //Prepare slip 
    $fileTmpPath = $file['tmp_name'];
    $fileName = $file['name'];

    $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
    $uniqueFileName = uniqid('slip_010_', true) . '.' . $fileExtension;

    $fileDestination = $uploadDir . $uniqueFileName;

    $file_move_result = move_uploaded_file($fileTmpPath, $fileDestination);

    $slip_url = $uniqueFileName;

    
}


//Create transaction
try {
    if( $transaction_amount > 0){
    $stmt = $pdo->prepare("INSERT INTO transactions (member_id, transaction_amount, transaction_type, slip_url) VALUES (?, ?, ?, ?)");

    $stmt->execute([$member_id, $transaction_amount, $transaction_type, $slip_url]);

    $transaction_id = $pdo->lastInsertId();
    }

    $transaction_message = "success";
} catch (PDOException $e) {
    $transaction_message = $e->getMessage();
    //echo $e->getMessage();
}

//Make a booking
try {
    foreach ($courts as $key => $court_value) {

        $court_numb = $court_value;
        $time_val = $times[$key];

        if( $court_numb == 'waitlist'){

            $stmt = $pdo->prepare("INSERT INTO wait_list (member_id,date, timeslot, member_type) VALUES (?, ?, ?, ?)");
            $stmt->execute([$member_id, $date, $time_val, $booking_type] );

            
            if( $booking_type == 'non-member'){
                $userMessage = "[Non Member] \nมีรายการจอง Waitlist ใหม่ \nวันที่: " . $date . " เวลา: " .$time_val . "\nสมาชิก: " . $member_name . " (" .  $member_number . ") ดูรายละเอียด : https://booking.lesmashclub.com/admin-waitlist.php";
            }else{
                $userMessage = "[Club Member] \nมีรายการจอง Waitlist ใหม่ \nวันที่: " . $date . " เวลา: " .$time_val . "\nสมาชิก: " . $member_name . " (" .  $member_number . ") ดูรายละเอียด : https://booking.lesmashclub.com/admin-waitlist.php";
            }
            

            

        }else{

            $stmt = $pdo->prepare("INSERT INTO bookings (court, date, timeslot, member_id, booking_status, booking_type, daily_member_type, coach, coach_name, booking_note, payment, transaction_id, slip) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
            $stmt->execute([$court_numb, $date, $time_val, $member_id, $booking_status, $booking_type, $daily_member_type, $coach_option, $coach_name, $booking_note, $payment_type,  $transaction_id, $slip_url ?? null]);

            
            if( $booking_type == 'non-member'){
                $userMessage = "[Non Member] \nมีรายการจองใหม่ \nวันที่: " . $date . " เวลา: " .$time_val . "\nสมาชิก: " . $member_name . " (" .  $member_number . ") \n ดูรายละเอียด : https://booking.lesmashclub.com/admin-bookings.php" ;
            }else{
                $userMessage = "[Club Member] \nมีรายการจองใหม่ \nวันที่: " . $date . " เวลา: " .$time_val . "\nสมาชิก: " . $member_name . " (" .  $member_number . ") \n ดูรายละเอียด : https://booking.lesmashclub.com/admin-bookings.php";
            }
            
        }

        //Send line notification if is not admin 
        if( $admin_view == 'false'){

            //$userMessage = 'มีรายการจองใหม่';

            $accessToken = LINE_PUSH_TOKEN;

            $url = 'https://api.line.me/v2/bot/message/push';

            $data = [
                'to' => LINE_ADMIN_USER_ID,
                'messages' => [[
                    'type' => 'text',
                    'text' => $userMessage
                ]]
            ];

            $postData = json_encode($data);

            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

            $result = curl_exec($ch);
            curl_close($ch);
            
        }

        
    }
    /*
        $stmt = $pdo->prepare("INSERT INTO bookings (court, date, timeslot, member_id, booking_status, booking_type, daily_member_type, coach, coach_name, booking_note, payment, transaction_id, slip) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$court_1, $date, $time_1, $member_id, $booking_status, $booking_type, $daily_member_type, $coach_option, $coach_name, $booking_note, $payment_type,  $transaction_id, $slip_url ?? null]);

    if( $court_2 != '' && $time_2 != ''):
        $stmt_2 = $pdo->prepare("INSERT INTO bookings (court, date, timeslot, member_id, booking_status, booking_type, daily_member_type, coach, coach_name, booking_note, payment, transaction_id, slip) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_2->execute([$court_2, $date, $time_2, $member_id, $booking_status, $booking_type, $daily_member_type, $coach_option, $coach_name, $booking_note, $payment_type, $transaction_id, $slip_url ?? null]);
    endif;
    */

    $booking_message = "success";
} catch (PDOException $e) {
    echo $e->getMessage();
}





//Update credit if pay with credit 
if( $payment_type == 'credit'){

    $stmt = $pdo->prepare("UPDATE members SET credit = credit - ? WHERE id = ? ");
    $result = $stmt->execute([$transaction_amount, $member_id]);

}

if(  $transaction_message == 'success' && $booking_message == 'success'){
?>
    <div class="success-message text-center text-success">
        <span class="fs-2">
            <i class="ri-checkbox-circle-line"></i>
        </span>
        <br>
        <p>Your booking has been made successfully</p>
        <hr>
        <a href="index.php" class="btn btn-outline-primary">Book again</a>
        <a href="<?= ($admin_view == 'true') ? 'admin-bookings.php' : 'bookings.php' ?>" class="btn btn-primary">View all bookings</a>
    </div>
<?php 
}else{ 
?>

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

}
?>
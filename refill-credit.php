<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require 'config.php';
    require_once 'includes/functions.php';

    $credit_amount = $_POST['credit_amount'];
    $member_id = $_POST['member_id'];
    $payment_type = $_POST['payment_type'];
    $file = $_FILES['slip_upload'] ?? null;
    
    $transaction_type = "Credit refill";
    $payment_type = 'qr';

    $transaction_message = "";
    $member_message = "";


    //Upload file
    if ($file) {
        /*
        $uploadDir = 'uploads/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true); // Create the directory if it doesn't exist
        }
        //Prepare slip 
        $fileTmpPath = $file['tmp_name'];
        $fileName = $file['name'];

        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        $uniqueFileName = date('Y_m_d').'_'.uniqid('slip_', true) . '.' . $fileExtension;

        $fileDestination = $uploadDir . $uniqueFileName;

        $file_move_result = move_uploaded_file($fileTmpPath, $fileDestination);

        $slip_url = $uniqueFileName;
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

    $transaction_title = 'Member credit refill';
    //Create transaction
    try {
        $stmt = $pdo->prepare("INSERT INTO transactions (transaction_title, member_id, transaction_amount, transaction_type, payment_type, slip_url) VALUES (?, ?, ?, ?, ?, ?)");
    
        $stmt->execute([$transaction_title, $member_id, $credit_amount, $transaction_type, $payment_type, $slip_url]);
    
        $transaction_id = $pdo->lastInsertId();
    
        $transaction_message = "success";
    } catch (PDOException $e) {
        $transaction_message = $e->getMessage();
    }


    //Get Member Info 
    $stmt_member = $pdo->prepare("SELECT * FROM members WHERE id = ?");
    $stmt_member->execute( [$member_id] );
    $transaction_member_info = $stmt_member->fetchAll(PDO::FETCH_ASSOC);

    $ts_member_full_name = $transaction_member_info[0]['first_name'] . ' ' . $transaction_member_info[0]['last_name'];
    $ts_member_number = $transaction_member_info[0]['member_number'];


    
    //SEND to LINE
    if( $payment_type == 'extend_membership'){
        $booking_line_message = "มีรายการต่อสมาชิกใหม่ ดูที่ https://booking.lesmashclub.com/admin-transactions.php";
    }else{
        $booking_line_message = "มีรายการเพิ่ม Credit ใหม่\nจำนวน: " .$credit_amount. "\nจากสมาชิก: " . $ts_member_full_name . "\n" . "หมายเลขสมาชิก: " .$ts_member_number . "\nดู Slip ที่ https://booking.lesmashclub.com/uploads/" . $slip_url ."\n\nดูที่ https://booking.lesmashclub.com/admin-transactions.php";
    }


    //Send Line Message
        $accessToken = LINE_BROADCAST_TOKEN;
        lsc_send_line_broadcast($booking_line_message, $accessToken);

    



    if(  $transaction_message == 'success'){
    ?>
        <div class="success-message text-center text-success">
            <span class="fs-2">
                <i class="ri-checkbox-circle-line"></i>
            </span>
            <br>
            <p>Payment has been made successfully</p>
            <hr>
            <a href="index.php" class="btn btn-outline-primary">Done</a>
        </div>
        <script>
            //window.setTimeout(function() {
                //window.location.href = 'bookings.php';
            //}, 5000);
            
        </script>
    <?php 
    }else{ 
    ?>

        <div class="error-message text-center text-danger">
            <span class="fs-2">
                <i class="ri-error-warning-fill"></i>
            </span>
            <br>
            <p>There's something wrong with the refilling the credit. Please try again.</p>
            <hr>
            <?php if( $payment_type == 'extend_membership'){ ?>
                <a href="extend-membership.php" class="btn btn-primary">Try again</a>
            <?php }else{ ?>
                <a href="add-credit.php" class="btn btn-primary">Try again</a>
            <?php } ?>
        </div>

        <script>
            //window.setTimeout(function() {
                //window.location.href = 'index.php';
            //}, 5000);
        </script>

    <?php 
    }
}
?>
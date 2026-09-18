<?php 
// Load config (defines DB_* constants and $pdo) if the caller has not already done so
if (!defined('DB_HOSTNAME')) {
    require_once __DIR__ . '/../config.php';
}

function lsc_format_date($type, $input_date){
    if( $type == 'db_to_readable'){

        $input_date = DateTime::createFromFormat("Y-m-d", $input_date);
        $formattedDate = $input_date->format("D d F, Y");

    }elseif( $type == 'to_db' ){

        $input_date = DateTime::createFromFormat("d-m-Y", $input_date);
        $formattedDate = $input_date->format("Y-m-d");

    }else{
        $input_date = DateTime::createFromFormat("Y-m-d", $input_date);
        $formattedDate = $input_date->format("D d F, Y");
    }

    return $formattedDate;
}

function get_member_info($member_id, $member_type){
    // Create connection
    $conn = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);

    if( $member_type  == 'non-member'){
        $query = "SELECT * FROM non_members WHERE id = '$member_id'";
    }else{
        $query = "SELECT * FROM members WHERE id = '$member_id'";
    }
    

    $results = $conn->query($query);

    $member_data = array();

    if ($results->num_rows > 0) {

        while($row = $results->fetch_assoc()) {

            $member_data['message'] = 'Member found';
            $member_data['id'] = $row['id'];
            $member_data['member_number'] = $row['member_number'];
            $member_data['first_name'] = $row['first_name'];
            $member_data['last_name'] = $row['last_name'];
            $member_data['member_type'] = $row['member_type'];
            $member_data['member_status'] = $row['member_status'];
            $member_data['member_phone'] = $row['member_phone'];
            $member_data['member_email'] = $row['member_email'];
            $member_data['member_password'] = $row['member_password'];
            $member_data['member_since'] = $row['member_since'];
            $member_data['member_length'] = $row['member_length'];
            $member_data['last_renewed'] = $row['last_renewed'];
            $member_data['new_price'] = $row['new_price'];
            $member_data['credit'] = $row['credit'];
            $member_data['member_expiration'] = $row['member_expiration'];

        }

    }else{
        $member_data['message'] = 'No member found';
    }

    return $member_data;

}


function credit_number_sanitize($credit){
    $converted_credit = number_format($credit,0,".",",");

    return $converted_credit;
}

function lsc_log($action, $description, $username = null) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if ($username === null) {
        if (isset($_SESSION['user_id'])) {
            $username = 'User ID: ' . $_SESSION['user_id'];
            if (isset($_SESSION['member_fullname'])) {
                $username .= ' (' . $_SESSION['member_fullname'] . ')';
            } elseif (isset($_SESSION['member_type'])) {
                $username .= ' (' . $_SESSION['member_type'] . ')';
            }
        } else {
            $username = 'System/Guest';
        }
    }
    
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . '/app.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = json_encode([
        'timestamp' => $timestamp,
        'username' => $username,
        'action' => $action,
        'description' => $description
    ], JSON_UNESCAPED_UNICODE) . "\n";
    
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

function lsc_send_line_broadcast($message, $accessToken) {
    if (defined('DISABLE_NOTIFICATIONS') && DISABLE_NOTIFICATIONS) {
        lsc_log('Line Broadcast (Suppressed)', "Message: $message");
        return true;
    }
    $messageData = [
        'messages' => [
            [
                'type' => 'text',
                'text' => $message
            ]
        ]
    ];

    $ch = curl_init('https://api.line.me/v2/bot/message/broadcast');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($messageData));

    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

function lsc_send_sms_notification($phone, $message, $apiKey, $secretKey, $sender) {
    if (defined('DISABLE_NOTIFICATIONS') && DISABLE_NOTIFICATIONS) {
        lsc_log('SMS Notification (Suppressed)', "To: $phone, Message: $message");
        return ['success' => true, 'message' => 'Suppressed in demo mode'];
    }
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://portal-otp.smsmkt.com/api/send-message',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => array(
            "Content-Type: application/json",
            "api_key:" . $apiKey,
            "secret_key:" . $secretKey,
        ),
        CURLOPT_POSTFIELDS => json_encode(array(
            "message" => $message,
            "phone" => $phone,
            "sender" => $sender,
        )),
    ));
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        return ['success' => false, 'error' => $err];
    }
    
    $result = json_decode($response, true);
    return $result ?: ['success' => true, 'response' => $response];
}

?>
<?php
    session_start();
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../includes/functions.php';

    if (!isset($_SESSION["user_id"])) {
        die("Unauthorized access.");
    }

    function get_timeslot_for_waitlist($timeslot){

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

function getTransactionPrice(string $time_val): int
{
    // Evening rate timeslots
    $eveningSlots = [
        '6-7pm',
        '7-8pm',
        '8-9pm',
        '9-10pm'
    ];

    // If the time is in evening slots → 260
    if (in_array($time_val, $eveningSlots, true)) {
        return 280;
    }

    // Otherwise → 140
    return 160;
}


function getTimeDifferenceFromNow(string $booking_date, string $timeslot): int
{
    // Build full datetime: booking_date + " " + timeslot
    $time = new DateTime($booking_date . " " . $timeslot);

    $now  = new DateTime();

    // Calculate difference: future = positive, past = negative
    return $time->getTimestamp() - $now->getTimestamp();
}

    function expireOldPendingWaitlists(PDO $pdo, int $hours = 2): int
    {
        $sql = "
            UPDATE wait_list
            SET waitlist_status = 'expired',
                updated_at      = NOW()
            WHERE waitlist_status = 'pending'
            AND updated_at <= DATE_SUB(NOW(), INTERVAL :hours HOUR)
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':hours' => $hours]);

        return $stmt->rowCount();
    }

    //Readable Date for Waitlist
    function waitlist_format_date_to_readable_text($input_date){
        $input_date = DateTime::createFromFormat("Y-m-d", $input_date);
        $formattedDate = $input_date->format("d F, Y");
    return $formattedDate;
    }

    //Get Member Info for Waitlist
    function waitlist_get_member_type($pdo, $member_id){

        $sql = "SELECT member_type 
            FROM members 
            WHERE id = :id 
            LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $member_id]);

        $member_type = $stmt->fetchColumn();

        return $member_type ?: false; // Return false if not found
    
    }

    //Query waitlist to find if exist 
    function getWaitlistBySlot(PDO $pdo, string $notify_date, string $notify_timeslot): array
    {
        $sql = "
            SELECT *
            FROM wait_list
            WHERE date = :date
            AND timeslot = :timeslot
            ORDER BY created_at ASC
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':date'      => $notify_date,
            ':timeslot'  => $notify_timeslot,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    }

    //Find waitlist and get phone number 
    function getLatestWaitlistEntryWithPhone(PDO $pdo, string $notify_date, string $notify_timeslot)
    {
        // ensure old pending entries are expired first
        expireOldPendingWaitlists($pdo, 2);

        // 1️⃣ Fetch the latest waitlist entry where status is NULL
        $sql = "
            SELECT wait_list_id, member_id, non_member_id, member_type, date, timeslot, created_at
            FROM wait_list
            WHERE date = :date
            AND timeslot = :timeslot
            AND waitlist_status IS NULL
            ORDER BY created_at ASC
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':date'      => $notify_date,
            ':timeslot'  => $notify_timeslot,
        ]);
        $waitlist = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$waitlist) {
            return false; // No waitlist entry found
        }

        $memberPhone  = null;
        $memberId     = null;
        $memberCredit = null;

        // 2️⃣ Determine where to look for phone number (and now ID + credit)
        if ($waitlist['member_type'] === 'member booking' && !empty($waitlist['member_id'])) {
            // Query from "members" table
            $stmt = $pdo->prepare("
                SELECT id, member_phone, credit
                FROM members
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $waitlist['member_id']]);
            $member = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($member) {
                $memberPhone  = $member['member_phone'];
                $memberId     = $member['id'];
                $memberCredit = $member['credit'];
            }
        } elseif ($waitlist['member_type'] === 'non-member' && !empty($waitlist['non_member_id'])) {
            // Query from "non_members" table
            $stmt = $pdo->prepare("
                SELECT member_phone
                FROM non_members
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $waitlist['non_member_id']]);
            $memberPhone = $stmt->fetchColumn();
        }

        // 3️⃣ Append results to the waitlist array
        $waitlist['member_phone']  = $memberPhone ?? null;
        $waitlist['member_db_id']  = $memberId ?? null;       // member’s ID from members table
        $waitlist['member_credit'] = $memberCredit ?? null;   // credit from members table

        return $waitlist;
    }


    //Send SMS with SMSMKT
    function send_sms_mkt(string $phone, string $message): array
    {
        $apiKey    = SMSMKT_API_KEY;
        $secretKey = SMSMKT_SECRET_KEY;
        $sender    = SMSMKT_SENDER; // must be an approved sender name in SMSMKT

        return lsc_send_sms_notification($phone, $message, $apiKey, $secretKey, $sender);
    }


    //Send SMS
    function send_sms(string $to, string $body): void {
        lsc_send_sms_notification($to, $body, SMSMKT_API_KEY, SMSMKT_SECRET_KEY, SMSMKT_SENDER);
    }


    //Update waitlist status 
    function updateWaitlistStatus(PDO $pdo, int $wait_list_id, string $status, $free_court, $booking_id = null): bool
    {
        // Validate input
        if ($wait_list_id <= 0 || empty($status)) {
            return false;
        }

        // Prepare and execute update query
        $sql = "UPDATE wait_list 
                SET waitlist_status = :status,
                free_court = :free_court,
                waitlist_booking_id = :waitlist_booking_id
                WHERE wait_list_id = :id";

        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':status' => $status,
            ':free_court' => $free_court,
            ':waitlist_booking_id' => $booking_id,
            ':id'     => $wait_list_id
        ]);
    }

    //Check pricing rule by 
    function get_pricing_rule_by_timeslot($timeslot){
            return getTransactionPrice($timeslot);
    }

    //Waitlist Create Transaction 
    function create_waitlist_booking_transaction($pdo, $member_id, $court, $timeslot, $date){

        $transaction_title = 'Booking_' . $member_id;
        $transaction_type = "booking (member)";

        $transaction_amount = get_pricing_rule_by_timeslot($timeslot);
        $non_member_info = '90002';
        $payment_type = 'credit';
        $slip_url = '';

        $stmt = $pdo->prepare("INSERT INTO transactions (transaction_title, member_id, non_member_id, non_member_info, transaction_amount, transaction_type, payment_type, slip_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$transaction_title, $member_id, "90002", $non_member_info, $transaction_amount, $transaction_type, $payment_type, $slip_url]);

        $transaction_id = $pdo->lastInsertId();

        return $transaction_id;

    }

    //Waitlist Create Booking 
    function create_waitlist_booking($pdo, $transaction_id, $member_id, $court, $timeslot, $date ){

        $this_transaction_title = 'Booking for date ' . waitlist_format_date_to_readable_text($date) . ', court ' . $court . ' at ' . $timeslot;
        $transaction_type = "booking (member)";
        $non_member_info = '';
        $this_transaction_price = get_pricing_rule_by_timeslot($timeslot);
        $payment_type = 'credit';
        $slip_url = '';

        $daily_member_type = waitlist_get_member_type($pdo, $member_id);
        $coach_name = '';
        $extra_player = '';
        $booking_note = '';

        //Create transaction for this booking
        $stmt_ts = $pdo->prepare("INSERT INTO transactions (transaction_title, assoc_transaction_id, member_id, non_member_id, non_member_info, transaction_amount, transaction_type, payment_type, slip_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_ts->execute([$this_transaction_title, $transaction_id, $member_id, "90002", $non_member_info, $this_transaction_price, $transaction_type, $payment_type, $slip_url]);
        
        $this_transaction_id = $pdo->lastInsertId();

        //Make a booking
        $stmt = $pdo->prepare("INSERT INTO bookings (court, date, timeslot, member_id, non_member_id, booking_status, booking_type, daily_member_type, coach_name, coach_extra_player, booking_note, payment, transaction_id, slip) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([$court, $date, $timeslot, $member_id, "90002", "approved", "member booking", $daily_member_type, $coach_name, $extra_player, $booking_note, "credit", $this_transaction_id, $slip_url]);

        $this_booking_id = $pdo->lastInsertId();

        return $this_booking_id;

    }

    //Create transaction assoc
    function create_assoc_transaction($pdo, $transaction_id, $member_id, $court, $timeslot, $date ){

        $this_transaction_title = 'Booking for date ' . waitlist_format_date_to_readable_text($date) . ', court ' . $court . ' at ' . $timeslot;
        $transaction_type = "booking (member)";
        $non_member_info = '';
        $this_transaction_price = get_pricing_rule_by_timeslot($timeslot);
        $payment_type = 'credit';
        $slip_url = '';

        //Create transaction for this booking
        $stmt_ts = $pdo->prepare("INSERT INTO transactions (transaction_title, assoc_transaction_id, member_id, non_member_id, non_member_info, transaction_amount, transaction_type, payment_type, slip_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_ts->execute([$this_transaction_title, $transaction_id, $member_id, "90002", $non_member_info, $this_transaction_price, $transaction_type, $payment_type, $slip_url]);
        
        $this_transaction_id = $pdo->lastInsertId();

        return $this_transaction_id;

    }

    //Create a booking for waitlist
    function waitlistCreateBooking(PDO $pdo, array $data)
    {
        $sql = "
            INSERT INTO bookings (
                court,
                date,
                timeslot,
                member_id,
                non_member_id,
                booking_status,
                booking_type,
                daily_member_type,
                payment,
                transaction_id,
                payment_remark,
                slip,
                coach,
                coach_extra_player,
                coach_name,
                booking_note,
                non_member_info,
                created_at
            ) VALUES (
                :court,
                :date,
                :timeslot,
                :member_id,
                :non_member_id,
                :booking_status,
                :booking_type,
                :daily_member_type,
                :payment,
                :transaction_id,
                :payment_remark,
                :slip,
                :coach,
                :coach_extra_player,
                :coach_name,
                :booking_note,
                :non_member_info,
                NOW()
            )
        ";

        $stmt = $pdo->prepare($sql);

        $ok = $stmt->execute([
            ':court'              => $data['court']              ?? null,
            ':date'               => $data['date']               ?? null,
            ':timeslot'           => $data['timeslot']           ?? null,
            ':member_id'          => $data['member_id']          ?? null,
            ':non_member_id'      => $data['non_member_id']      ?? null,
            ':booking_status'     => $data['booking_status']     ?? 'pending',   // default example
            ':booking_type'       => $data['booking_type']       ?? null,
            ':daily_member_type'  => $data['daily_member_type']  ?? null,
            ':payment'            => $data['payment']            ?? null,
            ':transaction_id'     => $data['transaction_id']     ?? null,
            ':payment_remark'     => $data['payment_remark']     ?? null,
            ':slip'               => $data['slip']               ?? null,
            ':coach'              => $data['coach']              ?? null,
            ':coach_extra_player' => $data['coach_extra_player'] ?? null,
            ':coach_name'         => $data['coach_name']         ?? null,
            ':booking_note'       => $data['booking_note']       ?? null,
            ':non_member_info'    => $data['non_member_info']    ?? null,
        ]);

        if (!$ok) {
            return false;
        }

        return (int)$pdo->lastInsertId();
    }

    //Waitlist check booking exists
    function waitlistBookingExists(PDO $pdo, string $court, string $date, string $timeslot): bool
{
    $sql = "
        SELECT COUNT(*) 
        FROM bookings
        WHERE court = :court
          AND date = :date
          AND timeslot = :timeslot
          AND booking_status IN ('pending', 'approved')
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':court'    => $court,
        ':date'     => $date,
        ':timeslot' => $timeslot,
    ]);

    $count = $stmt->fetchColumn();

    return $count > 0;
}


function updateBookingPayment(PDO $pdo, int $book_id, $payment, $payment_remark, $booking_note)
{
    if ($book_id <= 0) {
        return false; // invalid id
    }

    $sql = "
        UPDATE bookings
        SET 
            payment         = :payment,
            payment_remark  = :payment_remark,
            booking_note    = :booking_note,
            created_at      = NOW()      -- optional: update timestamp
        WHERE id = :id
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);

    return $stmt->execute([
        ':payment'        => $payment,
        ':payment_remark' => $payment_remark,
        ':booking_note'   => $booking_note,
        ':id'             => $book_id,
    ]);
}

function getTransactionIdByBookingId(PDO $pdo, int $booking_id)
{
    if ($booking_id <= 0) {
        return false; // invalid ID
    }

    $sql = "SELECT transaction_id 
            FROM bookings 
            WHERE id = :id 
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $booking_id]);

    $transactionId = $stmt->fetchColumn();

    return $transactionId !== false ? $transactionId : false;
}

function getAssocTransactionId(PDO $pdo, string $transaction_id)
{
    if (empty($transaction_id)) {
        return false;
    }

    $sql = "
        SELECT assoc_transaction_id
        FROM transactions
        WHERE transaction_id = :transaction_id
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':transaction_id' => $transaction_id]);

    $assocId = $stmt->fetchColumn();

    return $assocId !== false ? $assocId : false;
}

function updateBookingStatus(PDO $pdo, int $booking_id, string $status): bool
{
    if ($booking_id <= 0 || empty($status)) {
        return false; // invalid input
    }

    $sql = "
        UPDATE bookings
        SET booking_status = :status
        WHERE id = :id
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);

    return $stmt->execute([
        ':status' => $status,
        ':id'     => $booking_id
    ]);
}


function createTransaction(PDO $pdo, array $data)
{
    $sql = "
        INSERT INTO transactions (
            transaction_title,
            assoc_transaction_id,
            member_id,
            non_member_id,
            transaction_amount,
            transaction_type,
            payment_type,
            slip_url,
            transaction_note,
            created_at
        ) VALUES (
            :transaction_title,
            :assoc_transaction_id,
            :member_id,
            :non_member_id,
            :transaction_amount,
            :transaction_type,
            :payment_type,
            :slip_url,
            :transaction_note,
            NOW()
        )
    ";

    $stmt = $pdo->prepare($sql);

    $success = $stmt->execute([
        ':transaction_title'   => $data['transaction_title']   ?? null,
        ':assoc_transaction_id'=> $data['assoc_transaction_id']?? null,
        ':member_id'           => $data['member_id']           ?? null,
        ':non_member_id'       => $data['non_member_id']       ?? null,
        ':transaction_amount'  => $data['transaction_amount']  ?? 0,
        ':transaction_type'    => $data['transaction_type']    ?? null,
        ':payment_type'        => $data['payment_type']        ?? null,
        ':slip_url'            => $data['slip_url']            ?? null,
        ':transaction_note'    => $data['transaction_note']    ?? null,
    ]);

    if (!$success) {
        return false;
    }

    return (int)$pdo->lastInsertId();
}


?>  

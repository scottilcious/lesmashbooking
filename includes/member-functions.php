<?php 
date_default_timezone_set('Asia/Bangkok');
function get_time_from_timeslot($timeslot){

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

function create_booking_date_time($booking_date, $booking_time_converted){

    $concat_date_time = $booking_date . " " . $booking_time_converted;

    $converted_date_time = new DateTime($concat_date_time);

    return $converted_date_time;
}


function format_date_to_readable($input_date){
        $input_date = DateTime::createFromFormat("Y-m-d", $input_date);
        $formattedDate = $input_date->format("d F, Y");
    return $formattedDate;
}



function check_member_expired($expired_date){
    $currentTime = new DateTime();
    $booking_date_time_converted = create_booking_date_time($expired_date, "00:00:00");

    $date_passed = "false";
    if( $booking_date_time_converted < $currentTime){
        $date_passed = "true";
    }else{
        $date_passed = "false";
    }

    return $date_passed;
}


function get_member_info_with_passed_id($member_id){
    // Create connection
    $conn = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, LSC_ACTIVE_DATABASE, DB_PORT);

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


function get_booking_detail_from_transaction($transaction_id){
    $query = "SELECT * FROM bookings WHERE transaction_id = '$transaction_id'";

    $results = $conn->query($query);

    $booking_data = array();

    if ($results->num_rows > 0) {

        while($row = $results->fetch_assoc()) {

            $booking_data['message'] = 'Booking found';
            $booking_data['id'] = $row['id'];
            $booking_data['court'] = $row['court'];
            $booking_data['date'] = $row['date'];
            $booking_data['timeslot'] = $row['timeslot'];
            $booking_data['member_id'] = $row['member_id'];
            $booking_data['non_member_id'] = $row['non_member_id'];
            $booking_data['booking_status'] = $row['booking_status'];
            $booking_data['booking_type'] = $row['booking_type'];
            $booking_data['payment'] = $row['payment'];
            $booking_data['booking_note'] = $row['booking_note'];
            $booking_data['booking_date'] = format_date_to_readable($booking_data['date']);
            

        }

    }else{
        $booking_data['message'] = 'No booking found';
    }

    return $booking_data;
}

?>
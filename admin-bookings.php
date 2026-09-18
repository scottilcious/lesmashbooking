<?php
require_once __DIR__ . '/includes/auth.php';
$lsc_me = lsc_require_admin('redirect');
// Database connection
require 'config.php';
?>

<!-- index.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All bookings - Admin Le Smash Club</title>
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
    if ($memberType == 'admin' && !empty($lsc_pending_guest_offers)): ?>
        <div class="container mt-3">
            <div class="alert alert-warning d-flex justify-content-between align-items-center" role="alert">
                <div><b><?= (int) $lsc_pending_guest_offers ?></b> guest waitlist offer<?= $lsc_pending_guest_offers > 1 ? 's are' : ' is' ?> waiting for admin confirmation.</div>
                <a href="admin-waitlist.php" class="btn btn-warning btn-sm">Open waitlist</a>
            </div>
        </div>
    <?php endif;
    
    $times = [
        "6-7am", "7-8am", "8-9am", "9-10am", "10-11am", "11am-12pm", "12-1pm",
        "1-2pm", "2-3pm", "3-4pm", "4-5pm", "5-6pm", "6-7pm", "7-8pm", "8-9pm", "9-10pm"
    ];


    function get_daily_member_type_text($daily_member_type){
        $dmt_text = "";
        switch ($daily_member_type) {
            case 'Individual':
                $dmt_text = "Indiv.";
                break;
            
            case 'Couple':
                $dmt_text = "Couple";
                break;

            case 'Family':
                $dmt_text = "Family";
                break;

            case 'Junior':
                $dmt_text = "Junior";
                break;

            case '1 adult 1 child':
                $dmt_text = "1 adult + child";
                break;
            
                
            default:
                # code...
                break;
        }

        return $dmt_text;
    }

    function get_booking_table_content($booking_object, $current_member_type, $pdo){
        if( $booking_object['booking_type'] == 'member booking'){ 
            
            $member_stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
            $member_stmt->execute([$booking_object['member_id']]);
            $this_member = $member_stmt->fetch(PDO::FETCH_ASSOC);
            $member_number = $this_member['member_number'];
            $this_member_name = $this_member['first_name'];
            $booking_note = $booking_object['booking_note'];

            if($booking_object['payment_remark'] == 'Not paid yet'){
                $class_not_paid = 'not_paid';
            }elseif( $booking_note == 'admin-cancelled-rain' ){
                $class_not_paid = 'already_paid cancelled-rain-booking';
            }else{
                $class_not_paid = 'already_paid';
            }
            ?>
            <div class="booked status-<?= $booking_object['booking_status'] ?> <?= $class_not_paid ?>" <?php if( $current_member_type == 'admin'){ ?> data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="Member Booking" <?php } ?> data-extra-guest="<?= $booking_object['coach_extra_player'] ?>">
                <a href="admin-view-booking.php?booking_id=<?= $booking_object['id'] ?>">
                <?php if( $current_member_type == 'admin'){
                    echo '<span class="badge rounded-pill text-bg-light">'. get_daily_member_type_text( $booking_object['daily_member_type'] ) .'</span>';
                    echo '<br>';
                    echo $this_member_name . ' - ';
                } ?>

                <?php echo $member_number ?>

                <?php 
                if( $booking_object['coach_name'] ){
                    echo '<br>';
                    echo $booking_object['coach_name'];
                }

                if( $booking_object['payment_remark'] ){
                    echo '<br>';
                    if( $booking_object['booking_note']=='waitlist-reserved' ){
                        echo '<small>(Waitlist Reserved)</small>';
                    }else{
                        echo '<small>(Not paid yet)</small>';
                    }
                }
                if( $booking_object['coach_extra_player'] && $booking_object['coach_extra_player'] == 1 ){
                    echo '<br>';
                    echo '<b>+' . $booking_object['coach_extra_player'] . ' guest</b>';
                }

                if( $booking_object['coach_extra_player'] && $booking_object['coach_extra_player'] > 1 ){
                    echo '<br>';
                    echo '<b>+' . $booking_object['coach_extra_player'] . ' guests</b>';
                }
                ?>
                </a>
            </div>

        <?php 
        }elseif(  $booking_object['booking_type'] == 'non-member' ){
            $non_member_info = $booking_object['non_member_info'];
            $non_member_exploded = explode(",", $non_member_info);
            $booking_note = $booking_object['booking_note'];

            if( $booking_object['payment_remark'] == 'Not paid yet'){
                $class_not_paid = 'not_paid';
            }elseif( $booking_note == 'admin-cancelled-rain' ){
                $class_not_paid = 'already_paid cancelled-rain-booking';
            }else{
                $class_not_paid = 'already_paid';
            }
            
            ?>
            <div class="booked status-<?= $booking_object['booking_status'] ?> <?= $class_not_paid ?>" <?php if( $current_member_type == 'admin'){ ?> data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="Non Member Booking" <?php } ?>>
                <a href="admin-view-booking.php?booking_id=<?= $booking_object['id'] ?>">
                <?php if( $current_member_type == 'admin'){
                    echo '<span class="badge rounded-pill text-bg-light">'. get_daily_member_type_text( $booking_object['daily_member_type'] ) .'</span>';
                    echo '<br>';
                } ?>

                [Non-Member] <?= $non_member_exploded[0]; ?>
                
                <?php 
                if( $booking_object['coach_name'] ){
                    echo '<br>';
                    echo $booking_object['coach_name'];
                }

                if( $booking_object['payment_remark'] ){
                    echo '<br>';
                    if( $booking_object['booking_note']=='waitlist-reserved' ){
                        echo '<small>(Waitlist Reserved)</small>';
                    }else{
                        echo '<small>(Not paid yet)</small>';
                    }
                }
                ?>
                </a>
            </div>
        <?php 
        }else{ ?>
            <div class="booked-academy">
                <a href="admin-view-booking.php?booking_id=<?= $booking_object['id'] ?>">
                <?= get_admin_booking_type( $booking_object['booking_type'] ) ?>
                </a>
            </div>
        <?php 
        }
        
    }

    function get_admin_booking_type($booking_type){
        $booking_type_text = "";

        switch ($booking_type) {
            case 'court':
                $booking_type_text = "Court booked";
                break;
            
            case 'academy':
                $booking_type_text = "Academy";
                break;

            case 'junior_academy':
                $booking_type_text = "Junior Academy";
                break;
            
            case 'adult_clinic':
                $booking_type_text = "Adult Clinic";
                break;

            case 'tennis_camp':
                $booking_type_text = "Tennis Camp";
                break;

            case 'tournament':
                $booking_type_text = "Tournament";
                break;
            
            default:
                # code...
                break;
        }

        return $booking_type_text;
    }

    function get_waitlist($date, $time, $pdo){
        $waitlist_stmt = $pdo->prepare("SELECT * FROM wait_list WHERE date = ? AND timeslot = ? ");
        $waitlist_stmt->execute([ $date, $time]);
        $this_waitlist = $waitlist_stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this_waitlist;
    }
    function get_this_member_info($id, $pdo){
        $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
        $stmt->execute([ $id ]);
        $this_member = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $member_info = array();
        $member_info['name'] =  $this_member['first_name'] . ' ' . $this_member['last_name'];
        $member_info['member_number'] =  $this_member['member_number'];
        $member_info['member_phone'] =  $this_member['member_phone'];
        $member_info['member_email'] =  $this_member['member_email'];

        return $member_info;
    }
    

    function get_non_member_info($id, $pdo){
        $stmt = $pdo->prepare("SELECT * FROM non_members WHERE id = ?");
        $stmt->execute([ $id ]);
        $this_member = $stmt->fetch(PDO::FETCH_ASSOC);

        $member_info = array();
        $member_info['name'] =  $this_member['guest_name'];
        $member_info['member_phone'] =  $this_member['member_phone'];
        $member_info['member_email'] =  $this_member['member_email'];

        return $member_info;
    }
    
    
    ?>
    <div class="banner">
        <div class="container">
            <h1>All Bookings</h1>
            <input type="hidden" name="member_type" id="member_type" value="<?= $memberType ?>">
        </div>
    </div>

    <div class="all-booking-list container">
        <div class="card mt-3 mb-3 bg-light">
            <div class="card-body">
                <form action="admin-bookings.php" method="post">
                <div class="input-group">
                    <span class="input-group-text">Search bookings by date</span>
                    <input type="date" id="admin-booking-date" name="admin-booking-date" class="form-control" required>
                    </form>
                </div>
            </div>
        </div>

        <div class="table-responsive table-time-table mb-5">
            <table class="table table-bordered table-court-timeslot admin-view-all-bookings-table">
                <thead>
                    <tr>
                        <th></th>
                        <th>Court 1</th>
                        <th>Court 2</th>
                        <th>Court 3</th>
                        <th>Court 4</th>
                        <th>Court 5</th>
                        <th>Court 6</th>
                        <th>Court 7</th>
                        <th>Waitlist</th>
                    </tr>
                </thead>
                <tbody id="adminBookingTimeTable">
                    <?php 

                        $todayDate = date("Y-m-d");

                        $sql = "
                            SELECT * FROM bookings
                            WHERE (
                                booking_status IN ('pending', 'approved')
                                OR (booking_status = 'cancelled' AND booking_note = 'admin-cancelled-rain')
                            )
                            AND date = :date
                        ";

                        $stmt = $pdo->prepare($sql);
                        $stmt->execute(['date' => $todayDate]);
                        $all_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        /*
                        //$query_booking_status = 'cancelled';
                        //$all_booking_query = $pdo->prepare("SELECT * FROM bookings WHERE date = ? AND NOT booking_status = ?");
                        $all_booking_query = $pdo->prepare("SELECT * FROM bookings WHERE (
                            booking_status IN ('pending', 'approved') OR (booking_status = 'cancelled' AND booking_note = 'admin-cancelled-rain') AND date = ?");
                        //$all_booking_query->execute([$todayDate, $query_booking_status]);
                        $all_booking_query->execute([$todayDate]);
                        */
                        //$all_bookings = $all_booking_query->fetchAll(PDO::FETCH_ASSOC);
                        

                        $booking_object_array = array();

                        foreach ($all_bookings as $key => $value) {
                            $booking_id = $value['id'];
                            $date = $value['date'];
                            $court_num = $value['court'];
                            $timeslot_val = $value['timeslot'];
                            $member_id = $value['member_id'];
                            $non_member_id = $value['non_member_id'];
                            $non_member_info = $value['non_member_info'];
                            $booking_type = $value['booking_type'];
                            $booking_status = $value['booking_status'];
                            $booking_note = $value['booking_note'];
                            $daily_member_type = $value['daily_member_type'];
                            $coach_name = $value['coach_name'];
                            $coach_extra_player = $value['coach_extra_player'];
                            $payment_remark = $value['payment_remark'];

                            $booking_object_array[$timeslot_val."_".$court_num]['id'] = $booking_id;
                            $booking_object_array[$timeslot_val."_".$court_num]['date'] = $date;
                            $booking_object_array[$timeslot_val."_".$court_num]['court'] = $court_num;
                            $booking_object_array[$timeslot_val."_".$court_num]['timeslot'] = $timeslot_val;
                            $booking_object_array[$timeslot_val."_".$court_num]['member_id'] = $member_id;
                            $booking_object_array[$timeslot_val."_".$court_num]['non_member_id'] = $non_member_id;
                            $booking_object_array[$timeslot_val."_".$court_num]['non_member_info'] = $non_member_info;
                            $booking_object_array[$timeslot_val."_".$court_num]['booking_type'] = $booking_type;
                            $booking_object_array[$timeslot_val."_".$court_num]['booking_status'] = $booking_status;
                            $booking_object_array[$timeslot_val."_".$court_num]['booking_note'] = $booking_note;
                            $booking_object_array[$timeslot_val."_".$court_num]['daily_member_type'] = $daily_member_type;
                            $booking_object_array[$timeslot_val."_".$court_num]['coach_name'] = $coach_name;
                            $booking_object_array[$timeslot_val."_".$court_num]['coach_extra_player'] = $coach_extra_player;
                            $booking_object_array[$timeslot_val."_".$court_num]['payment_remark'] = $payment_remark;
                        }
  

                        $admin_booking_statuses = ['court', 'academy', 'junior_academy', 'adult_clinic', 'tennis_camp', 'tournament'];

                    ?>
                    <?php foreach ($times as $key => $time) { ?>
                    <tr>
                        <td class="timeslot-column fw-bold"><?= $time ?></td>
                        <!-- Court 1 -->
                        <td class="time_<?= $time ?>_court_1">
                            <?php if( $booking_object_array[$time.'_1']){ 
                                    get_booking_table_content($booking_object_array[$time.'_1'], $memberType,  $pdo);
                                }else{ ?>
                                    <div class="available-slot">
                                    Available
                                    </div>
                            <?php } ?>
                        </td>
                        <!-- Court 1 -->
                        <!-- Court 2 -->
                        <td class="time_<?= $time ?>_court_2">
                            <?php if( $booking_object_array[$time.'_2']){ 
                                    get_booking_table_content($booking_object_array[$time.'_2'], $memberType,  $pdo);
                                }else{ ?>
                                    <div class="available-slot">
                                    Available
                                    </div>
                            <?php } ?>
                        </td>
                        <!-- Court 2 -->
                        <!-- Court 3 -->
                        <td class="time_<?= $time ?>_court_3">
                            <?php if( $booking_object_array[$time.'_3']){ 
                                    get_booking_table_content($booking_object_array[$time.'_3'], $memberType,  $pdo);
                                }else{ ?>
                                    <div class="available-slot">
                                    Available
                                    </div>
                            <?php } ?>
                        </td>
                        <!-- Court 3 -->
                        <!-- Court 4 -->
                        <td class="time_<?= $time ?>_court_4">
                            <?php if( $booking_object_array[$time.'_4']){ 
                                    get_booking_table_content($booking_object_array[$time.'_4'], $memberType,  $pdo);
                                }else{ ?>
                                    <div class="available-slot">
                                    Available
                                    </div>
                            <?php } ?>
                        </td>
                        <!-- Court 4 -->
                        <!-- Court 5 -->
                        <td class="time_<?= $time ?>_court_5">
                            <?php if( $booking_object_array[$time.'_5']){ 
                                    get_booking_table_content($booking_object_array[$time.'_5'], $memberType,  $pdo);
                                }else{ ?>
                                    <div class="available-slot">
                                    Available
                                    </div>
                            <?php } ?>
                        </td>
                        <!-- Court 5 -->
                        <!-- Court 6 -->
                        <td class="time_<?= $time ?>_court_6">
                            <?php if( $booking_object_array[$time.'_6']){ 
                                    get_booking_table_content($booking_object_array[$time.'_6'], $memberType,  $pdo);
                                }else{ ?>
                                    <div class="available-slot">
                                    Available
                                    </div>
                            <?php } ?>
                        </td>
                        <!-- Court 6 -->
                        <!-- Court 7 -->
                        <td class="time_<?= $time ?>_court_7">
                            <?php if( $booking_object_array[$time.'_7']){ 
                                    get_booking_table_content($booking_object_array[$time.'_7'], $memberType,  $pdo);
                                }else{ ?>
                                    <div class="available-slot">
                                    Available
                                    </div>
                            <?php } ?>
                        </td>
                        <!-- Court 7 -->
                        <!-- Waitlist -->
                        <td>
                            <div class="waitlist-content p-1" id="waitlist_<?= $time ?>">
                                <?php 
                                    $all_waitlist = get_waitlist($todayDate, $time, $pdo);

                                    if ($all_waitlist ){
                                        $count_waitlist = 1;
                                        foreach ($all_waitlist as $key => $value) {

                                            switch ($value['waitlist_status']) {
                                                case 'pending':
                                                    $waitlist_status = '<span class="badge rounded-pill text-bg-warning">Pending</span>';
                                                    break;

                                                case 'confirmed':
                                                    $waitlist_status = '<span class="badge rounded-pill text-bg-success">Confirmed</span>';
                                                    break;

                                                case 'expired':
                                                    $waitlist_status = '<span class="badge rounded-pill text-bg-secondary">Expired</span>';
                                                    break;
                                                
                                                default:
                                                    # code...
                                                    break;
                                            }

                                            if( $value['member_type'] == 'non-member' ){
                                                $get_membe_info = get_non_member_info($value['non_member_id'], $pdo);
                                                $member_info =  '<a href="#" class="waitlist-link" data-bs-toggle="popover" data-bs-trigger="focus" data-bs-html="true" data-bs-title="'.$get_membe_info['name'].'" data-bs-content="'.$get_membe_info['name'].'<br>Phone: '.$get_membe_info['member_phone'].'<br>Email: '.$get_membe_info['member_email'].'">'. $get_membe_info['name'] . ' <small>(Non Member)</small></a>';
                                            }else{
                                                $get_membe_info = get_this_member_info($value['member_id'], $pdo);
                                                $member_info =  '<a href="#" class="waitlist-link" data-bs-toggle="popover" data-bs-trigger="focus" data-bs-html="true" data-bs-title="'. $get_membe_info['member_number'] . '" data-bs-content="'.$get_membe_info['name'].'('.$get_membe_info['member_number'].')<br>Phone: '.$get_membe_info['member_phone'].'<br>Email: '.$get_membe_info['member_email'].'">'. $get_membe_info['member_number'] . ' <small>(Member)</small></a>';
                                            }

                                            if( $count_waitlist <= 3){
                                                echo $count_waitlist . ") " . $member_info;
                                                echo '<br>';

                                            }
                                            $count_waitlist++;
                                        }
                                        echo '<div class="text-center border-top">';
                                        echo '<a href="#" class="btn btn-link waitlist-link" data-bs-toggle="modal" data-bs-target="#waitlist'.$time.'">View All</a>';
                                        echo '</div>';
                                    ?>
                                    <div class="modal fade" id="waitlist<?= $time ?>" tabindex="-1" aria-labelledby="waitlist<?= $time ?>Label" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                            <div class="modal-header">
                                                <h1 class="modal-title fs-5" id="waitlist<?= $time ?>Label">Waitlist</h1>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <?php 
                                                    $count_waitlist = 1;
                                                    foreach ($all_waitlist as $key => $value) {

                                                        if( $value['member_type'] == 'non-member' ){
                                                            $get_membe_info = get_non_member_info($value['non_member_id'], $pdo);
                                                            $member_info =  $get_membe_info['name'] . ' <small>(Non Member)</small>';
                                                        }else{
                                                            $get_membe_info = get_this_member_info($value['member_id'], $pdo);
                                                            $member_info =  $get_membe_info['name'] . ' <span class="badge text-bg-dark"> '.  $get_membe_info['member_number'] .'</span>' . ' <small>(Member)</small>';
                                                        }

                                                        echo $count_waitlist . ") " . $member_info;
                                                        echo '<br>'. $waitlist_status;
                                                        echo '<br>';
                                                        echo '<i class="ri-smartphone-line"></i> Phone:' . $get_membe_info['member_phone'];
                                                        echo '<br>';
                                                        echo '<i class="ri-mail-line"></i> Email:' . $get_membe_info['member_email'];
                                                        echo '<hr>';
                                                    
                                                        $count_waitlist++;
                                                    }
                                                ?>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php 
                                    }
                                ?>
                            </div>
                        </td>
                        <!-- Waitlist -->

                    </tr>

                    <?php }  ?>
                </tbody>
            </table>
        </div> 
    </div>
    

    

    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="app.js?v=<?= filemtime(__DIR__ . '/app.js') ?>"></script>
    <script>
        /*
        document.addEventListener("DOMContentLoaded", function () {

            
            flatpickr("#date", {
                dateFormat: "Y-m-d", // Format: YYYY-MM-DD
                <?php if($pass_date): ?>
                defaultDate: "<?= $pass_date ?>",
                <?php else: ?>
                defaultDate: "today",
                <?php endif; ?>
                altInput: true,     // Show formatted date
                altFormat: "j F, Y", // Format: Full month name, day, year
                });

        });

        $(document).ready(function () {
            function formatDate(inputDate) {
                let dateObj = new Date(inputDate);
                let options = { year: 'numeric', month: 'long', day: 'numeric' };
                return dateObj.toLocaleDateString('en-US', options);
            }

            $("#filterBookings").click(function () {
                let date = $("#date").val();

                //Convert date 
                let formattedDate = formatDate(date);

                if (!date) {
                    alert("Please select date.");
                    return;
                }
                
                $.post("admin-filter-bookings.php", { date: date }, function (data) {
                    $("#largeTimeTable").html(data);
                    //$(".payment-options-wrapper").addClass("display");
                });
            });

        });
        */
       
    </script>
</body>
</html>

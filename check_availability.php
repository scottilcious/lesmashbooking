<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require 'config.php';
    include 'includes/member-functions.php';
    include 'includes/booking-functions.php';

    //Passed params
    //$court = $_POST['court'];
    $passed_date = $_POST['date'];
    $memberType = $_POST['member_type'];
    $admin_view = $_POST['admin_view'];
    $selected_member_id = $_POST['member_id'] ?? null;
    $limit_member_type = $_POST['limit_member_type'] ?? $memberType;

    if (!lsc_is_booking_window_open_for_member($memberType)) {
        ?>
        <tr>
            <td colspan="8">
                <div class="alert alert-warning mb-0">
                    Booking is closed between 12:00 am and 6:00 am. Please come back after 6:00 am.
                </div>
            </td>
        </tr>
        <?php
        exit;
    }

    if (!lsc_is_booking_date_within_advance_window($passed_date, $memberType)) {
        ?>
        <tr>
            <td colspan="8">
                <div class="alert alert-warning mb-0">
                    Bookings are limited to today through 7 days in advance.
                </div>
            </td>
        </tr>
        <?php
        exit;
    }

    $disabled_court_special = 'special-court';
    $court_exempt_dates = array('2025-12-21','2025-12-22','2025-12-23','2025-12-24','2025-12-25','2025-12-26','2025-12-27','2025-12-28');

    $court_closed_dates = array('2025-12-29','2025-12-30','2025-12-31','2026-01-01');

    if (in_array($passed_date, $court_exempt_dates)) {
        $disabled_court_special = 'disabled';
        $wrapper_style = 'style="background: #f8f8f8; color: #000; border: 1px solid #f2f2f2;"';
        $text_available_special = 'Court Closed';
        $text_waitlist_available_special = 'Court Closed';
    }else{
        $text_available_special = 'Available';
        $text_waitlist_available_special = 'Waitlist Available';
    }

    if (in_array($passed_date, $court_closed_dates)) {
        $disabled_court_special = 'disabled';
        $closed_court_wrapper_style = 'style="background: #f8f8f8; color: #000; border: 1px solid #f2f2f2;"';
        $text_closed_court_special = 'Court Closed';
        $text_closed_waitlist = 'Court Closed';
        $text_available_special = '';
        $text_waitlist_available_special = '';
    }
    

    date_default_timezone_set('Asia/Bangkok');
    //Check if it's 48 hours prior
    function check_48_hr_prior($date){
        $currentTime = new DateTime();
        $passedTime = new DateTime($date);
    
        $diff = $currentTime->diff($passedTime);
        $hoursDifference = ($diff->days * 24) + $diff->h + ($diff->i / 60); 
    
        return $hoursDifference <= 48;
    }

    function getHourDifference($datetimeString) {
        $currentDateTime = new DateTime(); // current time
        $passedDateTime = new DateTime($datetimeString); // passed time
    
        $interval = $currentDateTime->diff($passedDateTime);
    
        // Convert to total hours (includes days converted to hours)
        $hours = ($interval->days * 24) + $interval->h + ($interval->i / 60);
    
        // Optional: if you want to round to 2 decimal places
        return round($hours, 2);
    }

    function getHourDifferenceFromDateOnly($dateOnlyString, $defaultTime) {
        $currentTime = new DateTime();
        $defaultTime = $currentTime->format('H:i:s');
        $dateTimeString = $dateOnlyString . ' ' . $defaultTime;
        return getHourDifference($dateTimeString);
    }


    function check_peak_time_day_of_week($dayofweek, $time){

        $weekdays = [
            "Monday", "Tuesday", "Wednesday", "Thursday", "Friday"
        ];
    
        $weekends = [
            "Saturday", "Sunday"
        ];

        $peak_time_weekday = [
            "6-7am", "7-8am", "8-9am", "4-5pm", "5-6pm", "6-7pm", "7-8pm", "8-9pm", "9-10pm"
        ];
        $peak_time_weekend = [
            "6-7am", "7-8am", "8-9am", "9-10am", "10-11am","11-12am",  "4-5pm", "5-6pm", "6-7pm", "7-8pm", "8-9pm", "9-10pm"
        ];

        $type_day_of_week = "";
        if( in_array($dayofweek, $weekdays) ){
            $type_day_of_week = "weekday";
        }

        if( in_array($dayofweek, $weekends) ){
            $type_day_of_week = "weekend";
        }

        //Now check peak time 
        $result_peak_time = false;
        if( $type_day_of_week == "weekday" &&  in_array($time, $peak_time_weekday) ){
            $result_peak_time = true;
        }

        if( $type_day_of_week == "weekend" &&  in_array($time, $peak_time_weekend) ){
            $result_peak_time = true;
        }

        return $result_peak_time;


    }

    //Check if weekdays or weekend
    function check_weekday_weekend($dayofweek){
        $weekdays = [
            "Monday", "Tuesday", "Wednesday", "Thursday", "Friday"
        ];
    
        $weekends = [
            "Saturday", "Sunday"
        ];

        $type_day_of_week = "";
        if( in_array($dayofweek, $weekdays) ){
            $type_day_of_week = "weekday";
        }

        if( in_array($dayofweek, $weekends) ){
            $type_day_of_week = "weekend";
        }

        return $type_day_of_week;
    }

    //Check peak time 
    function check_peak_time($type_day_of_week){
        $peak_time_weekday = [
            "6-7am", "7-8am", "8-9am", "4-5pm", "5-6pm", "6-7pm", "7-8pm", "8-9pm", "9-10pm"
        ];
        $peak_time_weekend = [
            "6-7am", "7-8am", "8-9am", "9-10am", "10-11am","11-12am",  "4-5pm", "5-6pm", "6-7pm", "7-8pm", "8-9pm", "9-10pm"
        ];
        
        if( $type_day_of_week == 'weekday'){
            $peak_time = $peak_time_weekday;
        }

        if( $type_day_of_week == 'weekend'){
            $peak_time = $peak_time_weekend;
        }

        return $peak_time;
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
            $member_name = $this_member['first_name'];

            if( $current_member_type == 'admin' && $booking_object['payment_remark']){
                $class_not_paid = 'not_paid';
            }else{
                $class_not_paid = 'already_paid';
            }
            ?>
            <div class="check_availability_page booked status-<?= $booking_object['booking_status'] ?> <?= $class_not_paid ?>" <?php if( $current_member_type == 'admin'){ ?> data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="Member Booking" <?php } ?>>
                <?php if( $current_member_type == 'admin'){
                    echo '<span class="badge rounded-pill text-bg-light">'. get_daily_member_type_text( $booking_object['daily_member_type'] ) .'</span>';
                    echo '<br>';
                    echo $member_name . ' - ';
                } ?>

                <?php echo $member_number ?>

                <?php 
                if( $booking_object['coach_name'] ){
                    echo '<br>Coach: ';
                    echo $booking_object['coach_name'];
                }

                if( $booking_object['payment_remark'] && $current_member_type == 'admin' ){
                    echo '<br>';
                    if( $booking_object['booking_note']=='waitlist-reserved' ){
                        echo '<small>(Waitlist Reserved)</small>';
                    }else{
                        echo '<small>(Not paid yet)</small>';
                    }
                }
                ?>
            </div>

        <?php 
        }elseif(  $booking_object['booking_type'] == 'non-member' ){
            $non_member_info = $booking_object['non_member_info'];
            $non_member_exploded = explode(",", $non_member_info);

            if( $current_member_type == 'admin' && $booking_object['payment_remark']){
                $class_not_paid = 'not_paid';
            }else{
                $class_not_paid = 'already_paid';
            }
            
            ?>
            <div class="check_availability_page booked status-<?= $booking_object['booking_status'] ?> <?= $class_not_paid ?>" <?php if( $current_member_type == 'admin'){ ?> data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="Non Member Booking" <?php } ?>>
                <?php if( $current_member_type == 'admin'){
                    echo '<span class="badge rounded-pill text-bg-light">'. get_daily_member_type_text( $booking_object['daily_member_type'] ) .'</span>';
                    echo '<br>';
                } ?>

                [Non-Member] <?= $non_member_exploded[0]; ?>
                
                <?php 
                if( $booking_object['coach_name'] ){
                    echo '<br>Coach: ';
                    echo $booking_object['coach_name'];
                }

                if( $booking_object['payment_remark'] && $current_member_type == 'admin' ){
                    echo '<br>';
                    if( $booking_object['booking_note']=='waitlist-reserved' ){
                        echo '<small>(Waitlist Reserved)</small>';
                    }else{
                        echo '<small>(Not paid yet)</small>';
                    }
                }
                ?>
            </div>
        <?php 
        }else{ ?>
            <div class="booked-academy">
                <?= get_admin_booking_type( $booking_object['booking_type'] ) ?>
            </div>
        <?php 
        }
        
    }
    

    $new_time = strtotime($passed_date);
    $converted_date = date('Y-m-d',$new_time);

    // Use date() to get the day of the week
    $dayOfWeek = date('l', strtotime($converted_date));

    $check_day_of_week = check_weekday_weekend($dayOfWeek);
    $peak_time = check_peak_time($check_day_of_week);
    $check_48_hr = check_48_hr_prior($passed_date);
    
    $times = [
        "6-7am", "7-8am", "8-9am", "9-10am", "10-11am", "11am-12pm", "12-1pm",
        "1-2pm", "2-3pm", "3-4pm", "4-5pm", "5-6pm", "6-7pm", "7-8pm", "8-9pm", "9-10pm"
    ];
    
?>

<?php 

    $query_booking_status = 'cancelled';
    $all_booking_query = $pdo->prepare("SELECT * FROM bookings WHERE date = ? AND NOT booking_status = ?");
    $all_booking_query->execute([$passed_date, $query_booking_status]);
    $all_bookings = $all_booking_query->fetchAll(PDO::FETCH_ASSOC);

    $booking_object_array = array();

    foreach ($all_bookings as $key => $value) {
        $date = $value['date'];
        $court_num = $value['court'];
        $timeslot_val = $value['timeslot'];
        $member_id = $value['member_id'];
        $non_member_id = $value['non_member_id'];
        $non_member_info = $value['non_member_info'];
        $booking_type = $value['booking_type'];
        $booking_status = $value['booking_status'];
        $daily_member_type = $value['daily_member_type'];
        $coach_name = $value['coach_name'];
        $payment_remark = $value['payment_remark'];
        $booking_note = $value['booking_note'];

        $booking_object_array[$timeslot_val."_".$court_num]['date'] = $date;
        $booking_object_array[$timeslot_val."_".$court_num]['court'] = $court_num;
        $booking_object_array[$timeslot_val."_".$court_num]['timeslot'] = $timeslot_val;
        $booking_object_array[$timeslot_val."_".$court_num]['member_id'] = $member_id;
        $booking_object_array[$timeslot_val."_".$court_num]['non_member_id'] = $non_member_id;
        $booking_object_array[$timeslot_val."_".$court_num]['non_member_info'] = $non_member_info;
        $booking_object_array[$timeslot_val."_".$court_num]['booking_type'] = $booking_type;
        $booking_object_array[$timeslot_val."_".$court_num]['booking_status'] = $booking_status;
        $booking_object_array[$timeslot_val."_".$court_num]['daily_member_type'] = $daily_member_type;
        $booking_object_array[$timeslot_val."_".$court_num]['coach_name'] = $coach_name;
        $booking_object_array[$timeslot_val."_".$court_num]['payment_remark'] = $payment_remark;
        $booking_object_array[$timeslot_val."_".$court_num]['booking_note'] = $booking_note;
    }

        $admin_booking_statuses = ['court', 'academy', 'junior_academy', 'adult_clinic', 'tennis_camp', 'tournament'];
        $booking_rules = lsc_get_booking_rules_for_member_type($limit_member_type);
        $member_timeslot_booking_counts = lsc_get_member_timeslot_booking_counts($pdo, $passed_date, $selected_member_id);
?>

<?php foreach ($times as $key => $time) { 
    $hourDateFromPassedDifference = getHourDifferenceFromDateOnly($passed_date, "12:00:00");
    //Check if in peak time or not 
    if( check_peak_time_day_of_week($dayOfWeek, $time) == true && $hourDateFromPassedDifference > 48 &&  $memberType == 'non-member' ){
        $peak_time_checked = 'true';
    }else{
        $peak_time_checked = 'false';
    }
    //if( in_array($time, $peak_time) && $check_48_hr <= 48 && $member_type == 'non-member' ){
        //if( in_array($time, $peak_time) && $hourDateFromPassedDifference > 48 && $memberType == 'non-member' ){
        if( in_array($time, $peak_time) && $hourDateFromPassedDifference > 48 && $memberType == 'non-member' ){
        $peak_time_chk = 'true';
    }else{
        $peak_time_chk = 'false';
    }
    $existing_member_timeslot_count = $member_timeslot_booking_counts[$time] ?? 0;
    $waitlist_quota_disabled = $existing_member_timeslot_count >= $booking_rules['maxPerTimeslot'];
    $waitlist_disabled_attr = $waitlist_quota_disabled ? 'disabled data-quota-disabled="true"' : '';
    $waitlist_label_text = $waitlist_quota_disabled ? 'Waitlist Unavailable - quota reached' : (($text_closed_court_special == 'Court Closed') ? $text_closed_court_special : 'Waitlist Available');
    ?>

<tr data-date="<?= $passed_date ?>" <?= $memberType ?> data-hour-different-from-pass-date="<?= $hourDateFromPassedDifference ?>">
    <td class="timeslot-column fw-bold"><?= $time ?></td>
    <!-- Court 1 -->
    <td class="time_<?= $time ?>_court_1 peak-time-<?= $peak_time_chk ?>" <?= $check_48_hr ?>>
        <?php if( $booking_object_array[$time.'_1']){ 
                get_booking_table_content($booking_object_array[$time.'_1'], $memberType,  $pdo);
                }else{ ?>
                <?php if( $peak_time_checked == 'true'){ ?>
                <div class="peak-time-available" <?= $wrapper_style ?> <?= $closed_court_wrapper_style ?> >
                    Peak Time
                </div>
                <?php }else{ ?>
                <label class="available-slot" for="select-court-1-<?= $time ?>" <?= $wrapper_style ?> <?= $closed_court_wrapper_style ?> >
                <input type="checkbox" value="1/<?= $time ?>" id="select-court-1-<?= $time ?>" name="court_timeslot[]" <?= $disabled_court_special ?> > <?= $text_available_special ?> <?= $text_closed_court_special ?>
                </label>
                <?php } ?>
        <?php } ?>
    </td>
    <!-- Court 1 -->
    <!-- Court 2 -->
    <td class="time_<?= $time ?>_court_2 peak-time-<?= $peak_time_chk ?>">
        <?php if( $booking_object_array[$time.'_2']){ 
            get_booking_table_content($booking_object_array[$time.'_2'], $memberType, $pdo);
            }else{ ?>
            <?php if( $peak_time_checked == 'true'){ ?>
            <div class="peak-time-available" <?= $wrapper_style ?> <?= $closed_court_wrapper_style ?> >
                Peak Time
            </div>
            <?php }else{ ?>
            <label class="available-slot" for="select-court-2-<?= $time ?>" <?= $wrapper_style ?> <?= $closed_court_wrapper_style ?> >
            <input type="checkbox" value="2/<?= $time ?>" id="select-court-2-<?= $time ?>" name="court_timeslot[]" <?= $disabled_court_special ?>> <?= $text_available_special ?> <?= $text_closed_court_special ?>
            </label>
            <?php } ?>
        <?php } ?>
    </td>
    <!-- Court 2 -->
    <!-- Court 3 -->
    <td class="time_<?= $time ?>_court_3 peak-time-<?= $peak_time_chk ?>">
        <?php if( $booking_object_array[$time.'_3']){ 
            get_booking_table_content($booking_object_array[$time.'_3'], $memberType,$pdo);
            }else{ ?>
            <?php if( $peak_time_checked == 'true'){ ?>
            <div class="peak-time-available" <?= $wrapper_style ?> <?= $closed_court_wrapper_style ?> >
                Peak Time
            </div>
            <?php }else{ ?>
            <label class="available-slot" for="select-court-3-<?= $time ?>" <?= $wrapper_style ?> <?= $closed_court_wrapper_style ?> >
            <input type="checkbox" value="3/<?= $time ?>" id="select-court-3-<?= $time ?>" name="court_timeslot[]" <?= $disabled_court_special ?>> <?= $text_available_special ?> <?= $text_closed_court_special ?>
            </label>
            <?php } ?>
        <?php } ?>
    </td>
    <!-- Court 3 -->
    <!-- Court 4 -->
    <td class="time_<?= $time ?>_court_4 peak-time-<?= $peak_time_chk ?>">
        <?php if( $booking_object_array[$time.'_4']){ 
            get_booking_table_content($booking_object_array[$time.'_4'], $memberType, $pdo);
            }else{ ?>
            <?php if( $peak_time_checked == 'true'){ ?>
            <div class="peak-time-available" <?= $wrapper_style ?> <?= $closed_court_wrapper_style ?> >
                Peak Time
            </div>
            <?php }else{ ?>
            <label class="available-slot" for="select-court-4-<?= $time ?>" <?= $wrapper_style ?> <?= $closed_court_wrapper_style ?> >
            <input type="checkbox" value="4/<?= $time ?>" id="select-court-4-<?= $time ?>" name="court_timeslot[]" <?= $disabled_court_special ?>> <?= $text_available_special ?> <?= $text_closed_court_special ?>
            </label>
            <?php } ?>
        <?php } ?>
    </td>
    <!-- Court 4 -->
    <!-- Court 5 -->
    <td class="time_<?= $time ?>_court_5 peak-time-<?= $peak_time_chk ?>">
        <?php if( $booking_object_array[$time.'_5']){ 
            get_booking_table_content($booking_object_array[$time.'_5'], $memberType, $pdo);
            }else{ ?>
            <?php if( $peak_time_checked == 'true'){ ?>
            <div class="peak-time-available" <?= $closed_court_wrapper_style ?> >
                Peak Time
            </div>
            <?php }else{ ?>
            <label class="available-slot" for="select-court-5-<?= $time ?>" <?= $closed_court_wrapper_style ?> >
            <input type="checkbox" value="5/<?= $time ?>" id="select-court-5-<?= $time ?>" name="court_timeslot[]">  <?= ($text_closed_court_special == 'Court Closed')? $text_closed_court_special : 'Available' ?>
            </label>
            <?php } ?>
        <?php } ?>
    </td>
    <!-- Court 5 -->
    <!-- Court 6 -->
    <td class="time_<?= $time ?>_court_6 peak-time-<?= $peak_time_chk ?>">
        <?php if( $booking_object_array[$time.'_6']){ 
            get_booking_table_content($booking_object_array[$time.'_6'], $memberType, $pdo);
            }else{ ?>
            <?php if( $peak_time_checked == 'true'){ ?>
            <div class="peak-time-available" <?= $closed_court_wrapper_style ?> >
                Peak Time
            </div>
            <?php }else{ ?>
            <label class="available-slot" for="select-court-6-<?= $time ?>" <?= $closed_court_wrapper_style ?> >
            <input type="checkbox" value="6/<?= $time ?>" id="select-court-6-<?= $time ?>" name="court_timeslot[]">  <?= ($text_closed_court_special == 'Court Closed')? $text_closed_court_special : 'Available' ?>
            </label>
            <?php } ?>
        <?php } ?>
    </td>
    <!-- Court 6 -->
    <!-- Court 7 -->
    <td class="time_<?= $time ?>_court_7 peak-time-<?= $peak_time_chk ?>">
        <?php if( $booking_object_array[$time.'_7']){ 
            get_booking_table_content($booking_object_array[$time.'_7'], $memberType,$pdo);
            }else{ ?>
            <?php if( $peak_time_checked == 'true'){ ?>
            <div class="peak-time-available" <?= $closed_court_wrapper_style ?> >
                Peak Time
            </div>
            <?php }else{ ?>
            <label class="available-slot" for="select-court-7-<?= $time ?>" <?= $closed_court_wrapper_style ?> >
            <input type="checkbox" value="7/<?= $time ?>" id="select-court-7-<?= $time ?>" name="court_timeslot[]">  <?= ($text_closed_court_special == 'Court Closed')? $text_closed_court_special : 'Available' ?>
            </label>
            <?php } ?>
        <?php } ?>
    </td>
    <!-- Court 7 -->
    <!-- Waitlist -->
    <td>
        <label class="available-slot waitlist-slot" for="waitlist_<?= $time ?>" <?= $closed_court_wrapper_style ?> >
            <input type="checkbox" value="waitlist/<?= $time ?>" id="waitlist_<?= $time ?>" name="court_timeslot[]" data-existing-member-bookings="<?= $existing_member_timeslot_count ?>" data-max-per-timeslot="<?= $booking_rules['maxPerTimeslot'] ?>" <?= $waitlist_disabled_attr ?>> <span class="waitlist-label-text"><?= $waitlist_label_text ?></span>
        </label>
    </td>
    <!-- Waitlist -->

</tr>
<?php } ?>

<?php 
}
?>

<script>
/*const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]')
const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))*/

function formatNumber(number){
    number = number.toFixed(2) + '';
    x = number.split('.');
    x1 = x[0];
    x2 = x.length > 1 ? '.' + x[1] : '';
    var rgx = /(\d+)(\d{3})/;
    while (rgx.test(x1)) {
        x1 = x1.replace(rgx, '$1' + ',' + '$2');
    }
    return x1 + x2;
    
}



/*
function checkboxlimit(checkgroup, limit){
    var showalert = false;
    for (var i=0; i<checkgroup.length; i++){
        checkgroup[i].onclick=function(){
        var checkedcount=0;
        for (var i=0; i<checkgroup.length; i++)
            checkedcount+=(checkgroup[i].checked)? 1 : 0
        if (checkedcount>limit){
            //alert("You can only book a maximum of "+limit+" hours per day.");
            showalert = true;
            this.checked=false;
            alert("You can only book a maximum of "+limit+" hours per day.");
            }
        }
    }
}
*/
//BOOKING TIME RULES
function setupBookingRules(groupSelector, member_type) {
  // derive limits from member type
  const rules = (() => {
    const t = (member_type || '').toLowerCase();
    if (t === 'individual' || t === 'junior') {
      return { totalLimit: 2, maxPerTimeslot: 1 };
    }
    if (t === 'couple' || t === '1 adult 1 child') {
      return { totalLimit: 3, maxPerTimeslot: 2 };
    }
    if (t === 'family') {
      return { totalLimit: 4, maxPerTimeslot: 2 };
    }
    if (t === 'admin') {
      return { totalLimit: 240, maxPerTimeslot: 12 };
    }
    // default safest rule if unknown
    return { totalLimit: 2, maxPerTimeslot: 1 };
  })();

  const checkboxes = Array.from(document.querySelectorAll(groupSelector));

  // Guard: if nothing found, do nothing
  if (!checkboxes.length) return;

  function parseValue(val) {
    // Expect "court/timeslot"
    const [court, timeslot] = String(val).split('/');
    return { court: court?.trim(), timeslot: timeslot?.trim() };
  }

  function validate(currentChanged) {
    const checked = checkboxes.filter(cb => cb.checked);

    // 1) Total limit
    if (checked.length > rules.totalLimit) {
      alert(`You can only book a maximum of ${rules.totalLimit} hour(s) per day for your membership.`);
      return false;
    }

    // 2) Per-timeslot (parallel) limit
    const perTimeslot = new Map(); // timeslot -> count
    for (const cb of checked) {
      const { timeslot } = parseValue(cb.value);
      if (!timeslot) continue; // skip malformed
      perTimeslot.set(timeslot, (perTimeslot.get(timeslot) || 0) + 1);
      if (perTimeslot.get(timeslot) > rules.maxPerTimeslot) {
        const msg =
          rules.maxPerTimeslot === 1
            ? `You cannot book more than one court at the same time (${timeslot}).`
            : `You can book at most ${rules.maxPerTimeslot} court(s) at the same time (${timeslot}).`;
        alert(msg);
        return false;
      }
    }

    return true;
  }

  // Change handler (uses event delegation per checkbox)
  function onChange(e) {
    const cb = e.target;
    if (cb.type !== 'checkbox') return;

    // Only validate on checking (uncheck is always allowed)
    if (cb.checked) {
      const ok = validate(cb);
      if (!ok) {
        cb.checked = false; // revert the change
      }
    }
  }

  // Bind once per checkbox (or you can delegate from a container)
  checkboxes.forEach(cb => {
    cb.removeEventListener('change', onChange); // avoid duplicates if re-initialized
    cb.addEventListener('change', onChange);
  });
}
//BOOKING TIME RULES



if( document.getElementById("bookingForm") ){
    //If admin, allow more than 2 
    let member_type = document.getElementById("member_type").value;
    let admin_view = document.getElementById("admin_view").value;
    let limit_numb = 0;
    if( admin_view == "true"){
        limit_numb = 100;
    }else{
        limit_numb = 2;
    }
    // If admin, allow more than 2 
    //console.log("admin:" + admin_view);
    setupBookingRules('input[name="court_timeslot[]"]', member_type);
    /*
    if( admin_view == "true"){
        checkboxlimit(document.forms.bookingForm['court_timeslot[]'], 240);
    }else{
        checkboxlimit(document.forms.bookingForm['court_timeslot[]'], 2);
    }
    */
    
}   

//CALCULATE COURT BOOKING FEE BASED ON SELECTED TIME
function calc_court_booking_fee(selected_time){
    let check_today_date_jan1 = new Date('<?= $passed_date ?>');
    let jan1_2026 = new Date('2026-01-01');

    let current_booking_type = document.getElementById("booking_type").value;
    let court_booking_fee = 160;
    /*
    if (check_today_date_jan1 >= jan1_2026) {
        let court_booking_fee = 160;
    }else{
        let court_booking_fee = 140;
    }
    */

    console.log("calc_" + current_booking_type);

    if( current_booking_type == 'member booking' || current_booking_type == 'non-member'){
        
        if( selected_time == '6-7pm' || selected_time == '7-8pm' || selected_time == '8-9pm' || selected_time == '9-10pm' ){
            court_booking_fee = 280;
            /*
            if (check_today_date_jan1 >= jan1_2026) {
                court_booking_fee = 280;
            }else{
                court_booking_fee = 260;
            }
            */
            
        }else{
            court_booking_fee = 160;
            /*
            if (check_today_date_jan1 >= jan1_2026) {
                court_booking_fee = 160;
            }else{
               court_booking_fee = 140; 
            }
            */
            
        }

    }else{
        court_booking_fee = 0;
    }

    console.log("calc_court_booking_fee" + court_booking_fee);

    return court_booking_fee;

}




//PROCESS AND POPULATE COURT/TIME DATA 
function process_court_time_data(){

    //1. Get all selected court and time checkboxes 
    let all_court_time = document.querySelectorAll('input[name="court_timeslot[]"]:checked');

    //2. Loop through checked checkboxes
    // THEN save value inside all_checked_val 
    // THEN split string with "/" and save [0] in all_court_val and [1] in all_time_val
    let selected_date = document.getElementById('date').value;
    console.log('selected date'+ selected_date);
    let all_checked_val = [];
    let all_court_val = [];
    let all_time_val = [];
    let all_waitlist = [];
    let count_checkboxes = 0;
    all_court_time.forEach( checkbox => {
        

        all_checked_val.push(checkbox.value);
        //process value data 
        let splitval = checkbox.value.split("/");
        all_court_val[count_checkboxes] = splitval[0];
        all_time_val[count_checkboxes] = splitval[1];

        

        if( splitval[0] == 'waitlist'){
            all_waitlist.push( splitval[0] );
        }

        count_checkboxes++;
        
    });

    //console.log(all_waitlist);

    //3. Populate court and time in DOMs
    let all_selected_courts = document.getElementById("all_selected_courts");
    let all_selected_times = document.getElementById("all_selected_times");

    //Populate courts
    for (let index = 0; index < all_court_val.length; index++) {
        if( index == 0){
            if( document.getElementById("SummaryCourt") ){
                document.getElementById("SummaryCourt").innerHTML = all_court_val[index];
            }

            all_selected_courts.value = all_court_val[index];
            
        }
        if( index > 0){
            if( document.getElementById("SummaryCourt") ){
                document.getElementById("SummaryCourt").innerHTML += ", " + all_court_val[index];
            }

            all_selected_courts.value += ","+all_court_val[index];
        }
    }

    //Popualte times 
    for (let index_t = 0; index_t < all_time_val.length; index_t++) {
        if( index_t == 0){
            if( document.getElementById("SummaryTime") ){
                document.getElementById("SummaryTime").innerHTML = all_time_val[index_t];
            }

            all_selected_times.value = all_time_val[index_t];
        }
        if( index_t > 0 ){
            if( document.getElementById("SummaryTime") ){
                document.getElementById("SummaryTime").innerHTML += ", " + all_time_val[index_t];
            }

            all_selected_times.value += ","+all_time_val[index_t];
        }

    }

    //Compare all checkboxe amount with the legnth of waitlist array. If they are identical, change paymention option to Paylater and disable other paymention option (Credit and Bank Transfer)
    let current_credit = document.getElementById("current_credit").value;
    let admin_view = document.getElementById("admin_view").value;
    //console.log( all_waitlist.length );

    if( ( all_court_time.length > 0 && all_waitlist.length > 0 ) && ( all_waitlist.length ==  all_court_time.length ) &&  admin_view == 'false' ){
        //console.log("all waitlist");
        document.getElementById("paymentLaterOptionWrapper").classList.remove("hide");
        document.getElementById("payment_option_later").checked = true;

        if( document.getElementById("payment_option_qr") != null ){
            document.getElementById("payment_option_qr").checked = false;
        }
        if( document.getElementById("member_current_credit") != null ){
            document.getElementById("member_current_credit").checked = false;
        }

    }else if( ( all_court_time.length > 0 && all_waitlist.length > 0 ) && ( all_waitlist.length ==  all_court_time.length ) &&  admin_view == 'true' ){

        document.getElementById("payment_option_cash").checked = true;

    }else if( ( all_court_time.length > 0 || all_waitlist.length > 0 ) &&  admin_view == 'true' ){

        document.getElementById("payment_option_cash").checked = true;
        
    }else{
        if( document.getElementById("paymentLaterOptionWrapper") ){
            document.getElementById("paymentLaterOptionWrapper").classList.add("hide");
            document.getElementById("payment_option_later").checked = false;
        }   
        

        if( current_credit > 0){
            document.getElementById("payment_option_credit").checked = true;
        }else{
            document.getElementById("payment_option_qr").checked = true;
        }
        
    }
    
    

}


//CHECK IF ALL IN ARRAY IS WAITLIST
function check_waitlist_array(waitlist_array){
    let result_check_waitlist_array = true;
    for( let waitlist_index = 0; waitlist_index < waitlist_array.length; waitlist_index++ ){
        if( waitlist_array[waitlist_index] != 'waitlist' ){
            result_check_waitlist_array = false;
        }
    }

    return result_check_waitlist_array;
}

//CHECK TIME AND PRICE 
function get_price_from_timeslot(court, timeslot){
    let check_today_date_jan1 = new Date('<?= $passed_date ?>');
    let jan1_2026 = new Date('2026-01-01');
    let timeslot_price = 160;
    /*
    if (check_today_date_jan1 >= jan1_2026) {
        let timeslot_price = 160;
    }else{
       let timeslot_price = 140; 
    }
    */
    
    if( court != 'waitlist' ){
        if( (timeslot == '6-7pm' || timeslot == '7-8pm' || timeslot == '8-9pm' || timeslot == '9-10pm' ) ){
            timeslot_price = 280;
            /*
            if (check_today_date_jan1 >= jan1_2026) {
                timeslot_price = 280;
            }else{
                timeslot_price = 260;
            }*/
            
        }else{
            timeslot_price = 160;
            /*
            if (check_today_date_jan1 >= jan1_2026) {
                timeslot_price = 160;
            }else{
               timeslot_price = 140; 
            }*/
            
        }
    }else{
        timeslot_price = 0;
    }

    //console.log(timeslot_price);

    return timeslot_price;
}


//CALCULATE AND POPULATE ALL BOOKING FEES
function calculate_all_booking_fees(){
    let booking_type = document.getElementById("booking_type").value;
    
    let all_checked_court_time = [...document.querySelectorAll('input[name="court_timeslot[]"]:checked')].map(e => e.value);
    let all_checked_court_time_length = all_checked_court_time.length;
    let acccum_daily_member_type_fees = 0;
    let acccum_coach_option_fees = 0;
    let acccum_extra_player_fees = 0;
    let accum_court_booking_fees = 0;
    let selected_extra_player = document.querySelector('#extra_player:checked');
    let extra_player_amount = document.getElementById("extra_player_qty").value;

    //1. Calculate checked daily member type
    let selected_daily_member_type = document.querySelector('input[name="daily_member_type"]:checked');
    if( selected_daily_member_type != null){
        let daily_member_type_price = document.querySelector('input[name="daily_member_type"]:checked').getAttribute("data-type-price");
        //accum_court_booking_fees += parseInt(daily_member_type_price);
        acccum_daily_member_type_fees = parseInt(daily_member_type_price);
    }else{
        acccum_daily_member_type_fees = 0;
    }

    //console.log("Daily member type: " + acccum_daily_member_type_fees);

    //2. Calculate coach option 
    let selected_coach_option = document.querySelector('#coach_option:checked');
    if( selected_coach_option != null){
        let coach_option_price = document.querySelector('#coach_option:checked').getAttribute("data-coaching-price");
        //accum_court_booking_fees += parseInt(coach_option_price);
        if( booking_type == 'non-member'){
            acccum_coach_option_fees = parseInt(coach_option_price*all_checked_court_time_length);
        }else{
            acccum_coach_option_fees = parseInt(coach_option_price);
        }
    }else{
        acccum_coach_option_fees = 0;
    }

    //console.log("Coach option: " + acccum_coach_option_fees);

    //3. Calculate coaching extra player 
    
    if( extra_player_amount != null){
        
        let calc_extra_player = parseInt(extra_player_amount) * parseInt(200);

        if( booking_type == 'non-member'){
            acccum_extra_player_fees = parseInt(calc_extra_player*all_checked_court_time_length);
        }else{
            acccum_extra_player_fees = parseInt(calc_extra_player);
        }
    }else{
        acccum_extra_player_fees = 0;
    }


    //console.log("added extra player: " + acccum_extra_player_fees);

    //4. Calculate checked times
    //let all_checked_court_time = [...document.querySelectorAll('input[name="court_timeslot[]"]:checked')].map(e => e.value);
    let count_calc = 0;

    let count_waitlist = 0;
    for (let index = 0; index < all_checked_court_time.length; index++) {
        count_calc++; 
        let temp_court_time = all_checked_court_time[index].split("/");
        let temp_court = temp_court_time[0];
        let temp_time = temp_court_time[1];
        let temp_price = 0;
        
        
        if( temp_court == 'waitlist'){
            temp_price = parseInt(0);
            count_waitlist++;
        }else{
            temp_price = get_price_from_timeslot(temp_court, temp_time);
        }
        
        accum_court_booking_fees += parseInt(temp_price);
        
    }


    let all_calc_fees = 0;
    if( all_checked_court_time.length == count_waitlist ){
        all_calc_fees = 0;
    }else{
        all_calc_fees = parseInt(acccum_daily_member_type_fees) + parseInt(acccum_coach_option_fees) + parseInt(acccum_extra_player_fees) + parseInt(accum_court_booking_fees);
    }

    

    //console.log("All calculate fees" + all_calc_fees);

    document.getElementById("total_fee").value = all_calc_fees;
    document.getElementById("SummaryBookingFee").innerHTML = formatNumber(all_calc_fees);
    if( document.getElementById("creditAmount") != null ){
        document.getElementById("creditAmount").innerHTML = formatNumber(all_calc_fees);
    }
    
}



//WHEN SELECT COURT TIME 
document.querySelectorAll('input[name="court_timeslot[]"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {

        //1. Process court time data
        process_court_time_data();

        //2. calculate booking fees
        calculate_all_booking_fees();

        

    });
});


//WHEN SELECT DAILY MEMBER TYPE
document.querySelectorAll('input[name="daily_member_type"]').forEach(radio => {
    radio.addEventListener('change', function() {
        //let selected_member_type = this.value;

         //1. Process court time data
        process_court_time_data();

        //2. calculate booking fees
        calculate_all_booking_fees();
    });
});


//WHEN SELECT COACHING OPTION
document.querySelectorAll('input[name="coach[]"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        let selected_coach = this.value;
        let member_type = document.getElementById("member_type").value;

         //1. Process court time data
        process_court_time_data();

        //2. calculate booking fees
        calculate_all_booking_fees();

        //3. Show Extra Player option
        if( selected_coach != '' && member_type == 'non-member' ){
            document.getElementById("extraPlayer").classList.add("active");
        }else{
            document.getElementById("extraPlayer").classList.remove("active");
        }

    });
});

//WHEN SELECT EXTRA PLAYER
document.querySelectorAll('input[name="extra_player[]"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        let selected_extra_player = this.value;
        let extra_player_qty = document.getElementById("extra_player_qty").value;

        //1. Check if extra_player_qty is more than 0, if yes, set value to 1 
        if( selected_extra_player != ''){
            document.getElementById("extra_player_qty").value = 1;
        }else{
            document.getElementById("extra_player_qty").value = 0;
        }

        //2. calculate booking fees
        calculate_all_booking_fees();

    });
});


//WHEN ADD OR REMOVE EXTRA PLAYER

function minus_extra_player(Id){
    let currentVal = document.getElementById(Id).value;
    if( currentVal == 0){
        document.getElementById(Id).value = 0;
    }else{
        document.getElementById(Id).value = parseInt(currentVal) - 1;
    }
    calculate_all_booking_fees();
}

function plus_extra_player(Id){
    let currentVal = document.getElementById(Id).value;
    
    document.getElementById(Id).value = parseInt(currentVal) + 1;

    calculate_all_booking_fees();
}


//WHEN SELECT PAYMENT OPTION
document.querySelectorAll('input[name="payment_option"]').forEach(radio => {
    radio.addEventListener('change', function() {
        let selected_payment_option = radio.value;
        let member_current_credit = document.getElementById("member_current_credit").value;
        let total_fee = document.getElementById("total_fee").value;

        if( member_current_credit < total_fee){

        }
    });
});

</script>

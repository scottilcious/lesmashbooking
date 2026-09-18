<?php 
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
                $inline_style_not_paid = 'background-color: #68009f !important; border: 1px solid #68009f !important;';
            }else{
                $class_not_paid = 'already_paid';
                $inline_style_not_paid = '';
            }
            ?>
            <div class="time_table_page booked status-<?= $booking_object['booking_status'] ?> <?= $class_not_paid ?>" <?php if( $current_member_type == 'admin'){ ?> data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="Member Booking" <?php } ?> style="<?= $inline_style_not_paid; ?>">
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
                $inline_style_not_paid = 'background-color: #68009f !important; border: 1px solid #68009f !important;';
            }else{
                $class_not_paid = 'already_paid';
                $inline_style_not_paid = '';
            }

            $new_payment_remark = str_replace(" ", "_", $booking_object['payment_remark']);
            if( $new_payment_remark == 'Not_paid_yet'){
                $inline_style_not_paid = 'background-color: #68009f !important; border: 1px solid #68009f !important;';
            }
            
            ?>
            <div class="time_table_page booked status-<?= $booking_object['booking_status'] ?> <?= $class_not_paid ?>" <?php if( $current_member_type == 'admin'){ ?> data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="Non Member Booking" <?php } ?> style="<?= $inline_style_not_paid; ?>">
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

                if( $booking_object['payment_remark']   && $current_member_type == 'admin'  ){
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


    $times = [
        "6-7am", "7-8am", "8-9am", "9-10am", "10-11am", "11am-12pm", "12-1pm",
        "1-2pm", "2-3pm", "3-4pm", "4-5pm", "5-6pm", "6-7pm", "7-8pm", "8-9pm", "9-10pm"
    ];

    /*
    if( $memberType == 'admin'){
        $todayDate = date("Y-m-d");
    }else{
        $todayDate = "2025-05-12";
    }
    */

    $todayDate = date("Y-m-d");
    
    $dayOfWeek = date('l', strtotime($todayDate));

    $check_day_of_week = check_weekday_weekend($dayOfWeek);
    $peak_time = check_peak_time($check_day_of_week);
    $check_48_hr = check_48_hr_prior($todayDate);

    $disabled_court_special = 'special-court';
    $court_exempt_dates = array('2025-12-21','2025-12-22','2025-12-23','2025-12-24','2025-12-25','2025-12-26','2025-12-27','2025-12-28');

    $court_closed_dates = array('2025-12-29','2025-12-30','2025-12-31','2026-01-01');

    if (in_array($todayDate, $court_exempt_dates)) {
        $disabled_court_special = 'disabled';
        $wrapper_style = 'style="background: #f8f8f8; color: #000; border: 1px solid #f2f2f2;"';
        $text_available_special = 'Court Closed';
        $text_waitlist_available_special = 'Court Closed';
    }else{
        $text_available_special = 'Available';
        $text_waitlist_available_special = 'Waitlist Available';
    }

    if (in_array($todayDate, $court_closed_dates)) {
        $disabled_court_special = 'disabled';
        $closed_court_wrapper_style = 'style="background: #f8f8f8; color: #000; border: 1px solid #f2f2f2;"';
        $text_closed_court_special = 'Court Closed';
        $text_closed_waitlist = 'Court Closed';
        $text_available_special = '';
        $text_waitlist_available_special = '';
    }
    /*else{
        $text_closed_court_special = 'Available';
        $text_closed_waitlist = 'Waitlist Available';
        $text_available_special = '';
        $text_waitlist_available_special = '';
    }*/


    

    


?>
<p class="fs-4 mb-0">Select Court and Time</p>
<p>(6:00am - 6:00pm: 160THB/hr , 6:00pm - 10:00pm 280THB/hr)</p>
<?php if( $memberType != 'admin') { ?>
<div class="card court-layout text-center mb-3">
    <div class="card-body">
        <img src="images/court_layout.png" alt="" class="rounded" width="300">
        <button type="button" class="btn btn-outline-dark" data-bs-toggle="modal" data-bs-target="#courtLayoutModal">View large image</button>
    </div>
</div>
<?php } ?>

<div class="table-responsive table-time-table" data-day-of-week="<?= $check_day_of_week  ?> <?= $dayOfWeek ?>" >
    <table class="table table-bordered table-court-timeslot align-middle member-type-view-<?= $memberType ?>">
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
        <tbody id="largeTimeTable">
            <?php 

                $query_booking_status = 'cancelled';
                $all_booking_query = $pdo->prepare("SELECT * FROM bookings WHERE date = ? AND NOT booking_status = ?");
                $all_booking_query->execute([$todayDate, $query_booking_status]);
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
                $limit_member_id = ($memberType == 'admin') ? null : $userId;
                $limit_member_type = $memberType;
                $booking_rules = lsc_get_booking_rules_for_member_type($limit_member_type);
                $member_timeslot_booking_counts = lsc_get_member_timeslot_booking_counts($pdo, $todayDate, $limit_member_id);
                          
            ?>
            
            <?php foreach ($times as $key => $time) { 
                $hourDateFromPassedDifference = getHourDifferenceFromDateOnly($todayDate, "12:00:00");
                //Check if in peak time or not 

                if( check_peak_time_day_of_week($dayOfWeek, $time) == true && $hourDateFromPassedDifference > 48 &&  $memberType == 'non-member' ){
                    $peak_time_checked = 'true';
                }else{
                    $peak_time_checked = 'false';
                }

                //if( in_array($time, $peak_time) && $check_48_hr <= 48 && $memberType == 'non-member'){
                if( in_array($time, $peak_time) && $memberType == 'non-member'){
                    $peak_time_chk = 'true';
                }else{
                    $peak_time_chk = 'false';
                }
                $existing_member_timeslot_count = $member_timeslot_booking_counts[$time] ?? 0;
                $waitlist_quota_disabled = $existing_member_timeslot_count >= $booking_rules['maxPerTimeslot'];
                $waitlist_disabled_attr = $waitlist_quota_disabled ? 'disabled data-quota-disabled="true"' : '';
                $waitlist_label_text = $waitlist_quota_disabled ? 'Waitlist Unavailable - quota reached' : (($text_closed_court_special == 'Court Closed') ? $text_closed_court_special : 'Waitlist Available');
                
                ?>
            <tr data-date-pass-peak="<?= $hourDateFromPassedDifference ?>">
                <td class="timeslot-column fw-bold"><?= $time ?></td>
                <!-- Court 1 -->
                <td class="time_<?= $time ?>_court_1" data-peak-time="<?= $peak_time_checked ?>">
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
                            
                            <!-- court max booking hour -->
                            <!-- <div class="total-title border-top mt-2">Booking Hours</div>
                            <div class="total-court-hour d-flex">
                                <button type="button" class="btn btn-light" id="minus-court-1-hour" onclick="minuscourttime('court-1-hour')">-</button>
                                <input type="number" value="0" min="0" name="court-1-hour" id="court-1-hour">
                                <button type="button" class="btn btn-light" id="plus-court-1-hour" onclick="pluscourttime('court-1-hour')">
                                    +</button>
                            </div> -->
                            <!-- court max booking hour -->

                            </label>
                            
                            
                            <?php } ?>
                    <?php } ?>
                </td>
                <!-- Court 1 -->
                <!-- Court 2 -->
                <td class="time_<?= $time ?>_court_2" data-peak-time="<?= $peak_time_checked ?>">
                    <?php if( $booking_object_array[$time.'_2']){ 
                        get_booking_table_content($booking_object_array[$time.'_2'], $memberType, $pdo);
                     }else{ ?>
                        <?php if( $peak_time_checked == 'true'){ ?>
                            <div class="peak-time-available" <?= $wrapper_style ?> <?= $closed_court_wrapper_style ?> >
                                Peak Time
                            </div>
                            <?php }else{ ?>
                        <label class="available-slot" for="select-court-2-<?= $time ?>" <?= $wrapper_style ?> <?= $closed_court_wrapper_style ?> >
                        <input type="checkbox" value="2/<?= $time ?>" id="select-court-2-<?= $time ?>" name="court_timeslot[]" <?= $disabled_court_special ?> > <?= $text_available_special ?> <?= $text_closed_court_special ?>
                        </label>
                        <?php  } ?>
                    <?php } ?>
                </td>
                <!-- Court 2 -->
                <!-- Court 3 -->
                <td class="time_<?= $time ?>_court_3" data-peak-time="<?= $peak_time_checked ?>">
                    <?php if( $booking_object_array[$time.'_3']){ 
                        get_booking_table_content($booking_object_array[$time.'_3'], $memberType,$pdo);
                     }else{ ?>
                        <?php if( $peak_time_checked == 'true'){ ?>
                            <div class="peak-time-available" <?= $wrapper_style ?> <?= $closed_court_wrapper_style ?> >
                                Peak Time
                            </div>
                            <?php }else{ ?>
                        <label class="available-slot" for="select-court-3-<?= $time ?>" <?= $wrapper_style ?> <?= $closed_court_wrapper_style ?> >
                        <input type="checkbox" value="3/<?= $time ?>" id="select-court-3-<?= $time ?>" name="court_timeslot[]" <?= $disabled_court_special ?> > <?= $text_available_special ?> <?= $text_closed_court_special ?>
                        </label>
                        <?php } ?>
                    <?php } ?>
                </td>
                <!-- Court 3 -->
                <!-- Court 4 -->
                <td class="time_<?= $time ?>_court_4" data-peak-time="<?= $peak_time_checked ?>">
                    <?php if( $booking_object_array[$time.'_4']){ 
                        get_booking_table_content($booking_object_array[$time.'_4'], $memberType, $pdo);
                     }else{ ?>
                        <?php  if( $peak_time_checked == 'true'){ ?>
                            <div class="peak-time-available" <?= $wrapper_style ?> <?= $closed_court_wrapper_style ?> >
                                Peak Time
                            </div>
                            <?php }else{ ?>
                        <label class="available-slot" for="select-court-4-<?= $time ?>" <?= $wrapper_style ?> <?= $closed_court_wrapper_style ?> >
                        <input type="checkbox" value="4/<?= $time ?>" id="select-court-4-<?= $time ?>" name="court_timeslot[]" <?= $disabled_court_special ?> > 
                         <?= $text_available_special ?> <?= $text_closed_court_special ?>
                        </label>
                        <?php } ?>
                    <?php } ?>
                </td>
                <!-- Court 4 -->
                <!-- Court 5 -->
                <td class="time_<?= $time ?>_court_5" data-peak-time="<?= $peak_time_checked ?>">
                    <?php if( $booking_object_array[$time.'_5']){ 
                        get_booking_table_content($booking_object_array[$time.'_5'], $memberType, $pdo);
                     }else{ ?>
                        <?php if( $peak_time_checked == 'true'){ ?>
                            <div class="peak-time-available" <?= $closed_court_wrapper_style ?> >
                                Peak Time
                            </div>
                            <?php }else{ ?>
                        <label class="available-slot" for="select-court-5-<?= $time ?>" <?= $closed_court_wrapper_style ?> >
                        <input type="checkbox" value="5/<?= $time ?>" id="select-court-5-<?= $time ?>" name="court_timeslot[]"> <?= ($text_closed_court_special == 'Court Closed')? $text_closed_court_special : 'Available' ?>
                        </label>
                        <?php } ?>
                    <?php } ?>
                </td>
                <!-- Court 5 -->
                <!-- Court 6 -->
                <td class="time_<?= $time ?>_court_6" data-peak-time="<?= $peak_time_checked ?>">
                    <?php if( $booking_object_array[$time.'_6']){ 
                        get_booking_table_content($booking_object_array[$time.'_6'], $memberType, $pdo);
                     }else{ ?>
                        <?php  if( $peak_time_checked == 'true'){ ?>
                            <div class="peak-time-available" <?= $closed_court_wrapper_style ?> >
                                Peak Time
                            </div>
                            <?php }else{  ?>
                        <label class="available-slot" for="select-court-6-<?= $time ?>" <?= $closed_court_wrapper_style ?> >
                        <input type="checkbox" value="6/<?= $time ?>" id="select-court-6-<?= $time ?>" name="court_timeslot[]"> 
                        <?= ($text_closed_court_special == 'Court Closed')? $text_closed_court_special : 'Available' ?>
                        </label>
                        <?php } ?>
                    <?php } ?>
                </td>
                <!-- Court 6 -->
                <!-- Court 7 -->
                <td class="time_<?= $time ?>_court_7" data-peak-time="<?= $peak_time_checked ?>">
                    <?php if( $booking_object_array[$time.'_7']){ 
                        get_booking_table_content($booking_object_array[$time.'_7'], $memberType,$pdo);
                     }else{ ?>
                        <?php if( $peak_time_checked == 'true'){ ?>
                            <div class="peak-time-available" <?= $closed_court_wrapper_style ?> >
                                Peak Time
                            </div>
                            <?php }else{ ?>
                        <label class="available-slot" for="select-court-7-<?= $time ?>" <?= $closed_court_wrapper_style ?> >
                        <input type="checkbox" value="7/<?= $time ?>" id="select-court-7-<?= $time ?>" name="court_timeslot[]">
                        <?= ($text_closed_court_special == 'Court Closed')? $text_closed_court_special : 'Available' ?>
                        </label>
                        <?php } ?>
                    <?php } ?>
                </td>
                <!-- Court 7 -->
                <!-- Waitlist -->
                <td>
                    <label class="available-slot waitlist-slot" for="waitlist_<?= $time ?>" <?= $closed_court_wrapper_style ?> >
                        <input type="checkbox" value="waitlist/<?= $time ?>" id="waitlist_<?= $time ?>" name="court_timeslot[]" data-existing-member-bookings="<?= $existing_member_timeslot_count ?>" data-max-per-timeslot="<?= $booking_rules['maxPerTimeslot'] ?>" <?= $waitlist_disabled_attr ?>>
                        <span class="waitlist-label-text"><?= $waitlist_label_text ?></span>
                    </label>
                </td>
                <!-- Waitlist -->

            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

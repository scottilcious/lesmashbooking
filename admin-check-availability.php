<?php
require_once __DIR__ . '/includes/auth.php';
$lsc_me = lsc_require_admin();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include_once 'includes/booking-functions.php';

    $date = $_POST['date'];
    $booking_id = $_POST['booking_id'];
    /*
    $court = $_POST['court'];
    $timeslot = $_POST['timeslot'];
    */
    /*
    $query_booking_status = 'cancelled';
    $all_booking_query = $pdo->prepare("SELECT * FROM bookings WHERE date = ? AND NOT booking_status = ?");
    $all_booking_query->execute([$date]);
    $all_bookings = $all_booking_query->fetchAll(PDO::FETCH_ASSOC);
    */
    $sql = "
        SELECT * FROM bookings
        WHERE date = :date
        AND booking_status != 'cancelled'
        AND id != :id
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'date' => $date,
        'id' => $booking_id
    ]);

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $all_booking_queries = array();
    $count_queried_booking = 0;
    foreach ($results as $row) {
        //echo "Booking ID: " . $row['id'] . "<br>";
        /*
        $all_booking_queries[$count_queried_booking]['id'] = $row['id'];
        $all_booking_queries[$count_queried_booking]['court'] = $row['court'];
        $all_booking_queries[$count_queried_booking]['timeslot'] = $row['timeslot'];
        */
        $all_booking_queries[$row['court'] .'_'. $row['timeslot']]['id'] = $row['id'];
        $count_queried_booking++;
    }

    //print_r($all_booking_queries);

    //$available_text = 'Available';
    $times = [
        "6-7am", "7-8am", "8-9am", "9-10am", "10-11am", "11am-12pm", "12-1pm",
        "1-2pm", "2-3pm", "3-4pm", "4-5pm", "5-6pm", "6-7pm", "7-8pm", "8-9pm", "9-10pm"
    ];

    ?>
    <div class="table-court-time-selection">
    <div class="table-responsive">
        <table class="table table-bordered" id="selectAdminCourtTime">
            <thead>
                <th></th>
                <th>Court 1</th>
                <th>Court 2</th>
                <th>Court 3</th>
                <th>Court 4</th>
                <th>Court 5</th>
                <th>Court 6</th>
                <th>Court 7</th>
            </thead>

            <tbody>
                <?php foreach ($times as $key => $time) {  
                    
                    $index_court_1 = "1_" . $time;
                    $index_court_2 = "2_" . $time;
                    $index_court_3 = "3_" . $time;
                    $index_court_4 = "4_" . $time;
                    $index_court_5 = "5_" . $time;
                    $index_court_6 = "6_" . $time;
                    $index_court_7 = "7_" . $time;
                    
                    ?>
                <tr>
                    <td class="text-center"><b><?= $time ?></b></td>
                    <td>
                        <label for="admin_court_time_1_<?= $time ?>" class="label_admin_court_time">
                            <input type="radio" name="admin_court_time" id="admin_court_time_1_<?= $time ?>"
                            data-court="1" data-timeslot="<?= $time ?>" value="1/<?= $time ?>"
                            <?= ( $all_booking_queries[$index_court_1])? 'disabled' : '' ?>
                            > <?= ( $all_booking_queries[$index_court_1])? 'Booked' : 'Available' ?>
                        </label>
                    </td>
                    <td>
                        <label for="admin_court_time_2_<?= $time ?>" class="label_admin_court_time">
                            <input type="radio" name="admin_court_time" id="admin_court_time_2_<?= $time ?>"
                            data-court="2" data-timeslot="<?= $time ?>" value="2/<?= $time ?>"
                            <?= ( $all_booking_queries[$index_court_2])? 'disabled' : '' ?>> 
                             <?= ( $all_booking_queries[$index_court_2])? 'Booked' : 'Available' ?>
                        </label>
                    </td>
                    <td>
                        <label for="admin_court_time_3_<?= $time ?>" class="label_admin_court_time">
                            <input type="radio" name="admin_court_time" id="admin_court_time_3_<?= $time ?>"
                            data-court="3" data-timeslot="<?= $time ?>" value="3/<?= $time ?>"
                            <?= ( $all_booking_queries[$index_court_3])? 'disabled' : '' ?>> 
                             <?= ( $all_booking_queries[$index_court_3])? 'Booked' : 'Available' ?>
                        </label>
                    </td>
                    <td>
                        <label for="admin_court_time_4_<?= $time ?>" class="label_admin_court_time">
                            <input type="radio" name="admin_court_time" id="admin_court_time_4_<?= $time ?>"
                            data-court="4" data-timeslot="<?= $time ?>" value="4/<?= $time ?>"
                            <?= ( $all_booking_queries[$index_court_4])? 'disabled' : '' ?>> 
                             <?= ( $all_booking_queries[$index_court_4])? 'Booked' : 'Available' ?>
                        </label>
                    </td>
                    <td>
                        <label for="admin_court_time_5_<?= $time ?>" class="label_admin_court_time">
                            <input type="radio" name="admin_court_time" id="admin_court_time_5_<?= $time ?>"
                            data-court="5" data-timeslot="<?= $time ?>" value="5/<?= $time ?>"
                            <?= ( $all_booking_queries[$index_court_5])? 'disabled' : '' ?>> 
                             <?= ( $all_booking_queries[$index_court_5])? 'Booked' : 'Available' ?>
                        </label>
                    </td>
                    <td>
                        <label for="admin_court_time_6_<?= $time ?>" class="label_admin_court_time">
                            <input type="radio" name="admin_court_time" id="admin_court_time_6_<?= $time ?>"
                            data-court="6" data-timeslot="<?= $time ?>" value="6/<?= $time ?>"
                            <?= ( $all_booking_queries[$index_court_6])? 'disabled' : '' ?>> 
                             <?= ( $all_booking_queries[$index_court_6])? 'Booked' : 'Available' ?>
                        </label>
                    </td>
                    <td>
                        <label for="admin_court_time_7_<?= $time ?>" class="label_admin_court_time">
                            <input type="radio" name="admin_court_time" id="admin_court_time_7_<?= $time ?>"
                            data-court="7" data-timeslot="<?= $time ?>" value="7/<?= $time ?>"
                            <?= ( $all_booking_queries[$index_court_7])? 'disabled' : '' ?>> 
                            <?= ( $all_booking_queries[$index_court_7])? 'Booked' : 'Available' ?>
                        </label>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div></div>

    <div class="row mt-3 text-center justify-content-center">
        <div class="col-12 col-md-3">
            <button type="button" id="selectAdminCourtTimeToEdit" class="btn btn-primary">Select Court and Time</button>
        </div>
    </div>

    <script>
        $(document).ready(function(){
            $('#selectAdminCourtTimeToEdit').click(function(){
                let selected_admin_court_val = $("input[name='admin_court_time']:checked").data('court');
                let selected_admin_timeslot_val = $("input[name='admin_court_time']:checked").data('timeslot');

                //$('#court').val() = selected_admin_court_val;
                //$('#timeslot').val() = selected_admin_timeslot_val;
                document.getElementById("court").value = selected_admin_court_val;
                document.getElementById("timeslot").value = selected_admin_timeslot_val;

                $('#overlayCourtTimeAdmin').removeClass('active');

            });
        });
    </script>

    <?php 
    
    

    /*
    if( $all_bookings ){
       ?>
        <div class="error-message text-center text-danger">
            <span class="fs-2">
                <i class="ri-error-warning-fill"></i>
            </span>
            <br>
            <p>Looks like there's another member just made a booking at the court or time you just selected.<br>Please choose another one.</p>
            <hr>
            <a href="index.php" class="btn btn-primary">Choose another court or time</a>
        </div>
        <?php 
    }else{
        echo 'all good';
    }
    */

}
?>
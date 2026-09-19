<?php
require_once __DIR__ . '/../includes/court-grid.php';
require_once __DIR__ . '/../includes/pricing.php';

$todayDate = date("Y-m-d");
?>
<p class="fs-4 mb-0">Select Court and Time</p>
<p>(6:00am - 6:00pm: <?= LSC_COURT_FEE_DAY ?>THB/hr , 6:00pm - 10:00pm <?= LSC_COURT_FEE_EVENING ?>THB/hr)</p>
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
                lsc_court_grid_rows($pdo, $todayDate, [
                    'memberType'       => $memberType,
                    'limitMemberType'  => $memberType,
                    'selectedMemberId' => ($memberType == 'admin') ? null : $userId,
                ]);
            ?>
        </tbody>
    </table>
</div>

<?php
// Database connection
require 'config.php';
?>

<!-- index.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waitlist - Admin Le Smash Club</title>
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
<body  class="page-id-6">
    <?php include "menu.php"; 
    include 'includes/member-functions.php';

    $member_type = $_GET['member_type'];
    $waitlist_date = $_GET['waitlist_date'];
    $today_date = date('Y-m-d');

    if( $waitlist_date){

        $stmt = $pdo->prepare("SELECT * FROM wait_list WHERE date = ? ORDER BY created_at DESC");
        $stmt->execute([$waitlist_date]);

    }else{

        $stmt = $pdo->prepare("SELECT * FROM wait_list WHERE date = ? ORDER BY created_at DESC");
        $stmt->execute([$today_date]);

    }
    
    
    // Fetch user bookings
    //$stmt = $pdo->prepare("SELECT * FROM bookings WHERE member_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
    
    //$stmt->execute([$userId, $limit, $offset]);
    $waitlist = $stmt->fetchAll(PDO::FETCH_ASSOC);

    
    ?>
    <div class="banner">
        <div class="container">
            <h1>Wait list</h1>
        </div>
    </div>

    <!-- 
    <div class="container mt-5">
        <div class="btn-group btn-group-lg mt-3 mb-3" role="group" aria-label="Large button group">
            <a type="button" class="btn <?= ( $member_type == 'member')? 'btn-primary' : 'btn-outline-primary' ?> " href="login.php">
                View wait</a>
            <a type="button" class="btn  <?= ( $member_type == 'non-member')? 'btn-primary' : 'btn-outline-primary' ?> " href="login.php?member_type=non-member">
                Waitlist from non</a>
        </div>
    </div>
-->

    <div class="container mt-5">
        <form action="admin-waitlist.php">
            <label for="date" class="fs-4">View by date</label>
            <div class="input-group">
                <input type="text" class="form-control" type="date" id="waitlist_date" name="waitlist_date"  aria-describedby="checkAvailability" required>
                <button class="btn btn-outline-primary" type="submit" id="checkAvailability">View</button>
            </div>
        </form>
    </div>

    <div class="container mt-3 mb-5">
        <table class="table table-bordered table-responsive text-center">
            <thead class="table-dark">
                <tr>
                    <th>Requested Date</th>
                    <th>Requested Time</th> 
                    <th>Member Number/Guest Number</th>
                    <th>Member Name</th>
                    <th>Member Type</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($waitlist) > 0): ?>
                    <?php 
                        $count_waitlist = 1;
                        foreach ($waitlist as $waitlist_data): 
                        
                            $member_id = $waitlist_data['member_id'];

                            if( $waitlist_data['member_type'] == 'non-member'){
                                $mb_stmt = $pdo->prepare("SELECT * FROM  non_members WHERE id = ?");
                                $mb_stmt->execute([$member_id]);
                                $member_data = $mb_stmt->fetchAll(PDO::FETCH_ASSOC);

                                $member_number = $member_data[0]['member_number'];
                                $member_name = $member_data[0]['first_name'] . ' ' . $member_data[0]['last_name'];
                                $member_type = $member_data[0]['member_type'];
                            }else{
                                $mb_stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
                                $mb_stmt->execute([$member_id]);
                                $member_data = $mb_stmt->fetchAll(PDO::FETCH_ASSOC);

                                $member_number = $member_data[0]['member_number'];
                                $member_name = $member_data[0]['first_name'] . ' ' . $member_data[0]['last_name'];
                                $member_type = $member_data[0]['member_type'];
                            }
                            
                            
                        ?>
                        <tr>
                            <td><?= format_date_to_readable($waitlist_data['date']) ?></td>
                            <td><?= $waitlist_data['timeslot'] ?></td>
                            <td><?= $member_number ?></td>
                            <td><?= $member_name ?></td>
                            <td><?= $member_type ?></td>
                            <td>
                            <a class="btn btn-primary btn-sm view-btn" href="admin-view-member.php?member_id=<?= $member_data[0]['id'] ?>">View Member</a>
                            </td>
                        </tr>

                    <?php 
                    $count_waitlist++;
                    endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">No waitlist found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        flatpickr("#waitlist_date", {
            dateFormat: "Y-m-d", // Format: YYYY-MM-DD
            <?php if( $waitlist_date ) { ?>
            defaultDate: "<?= $waitlist_date ?>",
            <?php }else{ ?>
            defaultDate: "today",
            <?php } ?>
            altInput: true,     // Show formatted date
            altFormat: "j F, Y", // Format: Full month name, day, year
        });
    </script>
</body>
</html>


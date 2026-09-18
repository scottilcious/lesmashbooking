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
    <title>All members - Admin Le Smash Club</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css"rel="stylesheet" />
    <link rel="stylesheet" href="style.css?v=3">
</head>
<body class="page-id-3">
    <?php include "menu.php"; 
    include_once 'includes/member-functions.php';

    //search and filter 
    $search_member = $_GET['search_member'];
    $search_by = $_GET['search_by'];
    $sort = $_GET['sort'];
    $order = $_GET['order'];
    $order_param_text = 'DESC';

    

    if( $search_member && $search_by ){

        if( $search_by == 'member_name'){

            $sql = "SELECT * FROM members WHERE first_name LIKE '$search_member' ORDER BY member_number ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        }

        if( $search_by == 'member_number'){
            $sql = "SELECT * FROM members WHERE member_number = '$search_member'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        }

    }elseif( $sort && $order ){

        if( $sort  == 'member_number' && $order == 'ASC'){
            $sql = "SELECT * FROM members ORDER BY member_number ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();

            $order_param_text = 'DESC';
        }elseif( $sort  == 'member_number' && $order == 'DESC'){
            $sql = "SELECT * FROM members ORDER BY member_number DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();

            $order_param_text = 'ASC';
        }elseif( $sort  == 'member_expiration' && $order == 'ASC'){
            $sql = "SELECT * FROM members ORDER BY member_expiration ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();

            $order_param_text = 'DESC';
        }elseif( $sort  == 'member_expiration' && $order == 'DESC'){
            $sql = "SELECT * FROM members ORDER BY member_expiration DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();

            $order_param_text = 'ASC';
        }

        

    }else{
        
        $sql = "SELECT * FROM members ORDER BY member_number ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    }
    


    
    //$stmt->execute([$userId, $limit, $offset]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    
    ?>
    <div class="banner">
        <div class="container">
            <h1>All Members</h1>
        </div>
    </div>

    <!-- search and filter -->
    <div class="container mt-5">
        <div class="row">
            <div class="col-12">
                <form action="admin-members.php" method="get">
                    <h4>Search members</h4>
                    <div class="input-group mb-3">
                        <select class="form-select" name="search_by" id="search_by" aria-label="Search by">
                            <option value="member_name" <?= ($search_by == 'member_name')? 'selected' : '' ?>>
                                Search by name</option>
                            <option value="member_number" <?= ($search_by == 'member_number')? 'selected' : '' ?>>
                                Search by member number</option>
                        </select>
                        <input type="text" class="form-control" name="search_member" id="search_member" 
                        placeholder="Enter keywords"
                        value="<?= ($search_member)? $search_member: '' ?>">
                        <button class="btn btn-outline-secondary" type="submit">Search <i class="ri-search-line"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="container mt-2 mb-5">
        <table class="table table-bordered table-responsive text-center" id="allMemberTable">
            <thead class="table-dark">
                <tr>
                    <th>
                        <a href="admin-members.php?sort=member_number&order=<?= $order_param_text ?>">
                            Member Number <i class="ri-expand-up-down-fill"></i>
                        </a>
                    </th>
                    <th>Member Name</th>
                    <th>Member Status</th>
                    <th>Member Type</th>
                    <th>Member Phone & Email</th>
                    <!-- <th>Member Since</th> -->
                    <th>Member Length</th>
                    <th>
                        <a href="admin-members.php?sort=member_expiration&order=<?= $order_param_text ?>">
                            Member Expiration <i class="ri-expand-up-down-fill"></i>
                        </a>
                    </th>
                    <th>Last renew</th>
                    <th>Credit</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($members) > 0): ?>
                    <?php 
                        $count_member = 1;
                        foreach ($members as $member): ?>

                        <?php if( $member['member_type'] != 'admin'): ?>
                            <?php $checked_member_expiration = check_member_expired($member['member_expiration']); ?>
                        <tr id="booking-<?= $member['id'] ?>" class="<?= "member-expire-".$checked_member_expiration ?>">
                            <td><?= $member['member_number'] ?></td>
                            <td><?= $member['first_name'] ?> <?= $member['last_name'] ?></td>
                            <td>
                                <?php if( $member['member_status'] == 'active' && $checked_member_expiration == 'false' ): ?>
                                    <span class="badge text-bg-success">Active</span>
                                <?php endif; ?>
                                <?php if( $member['member_status'] == 'inactive' && $checked_member_expiration == 'false'): ?>
                                    <span class="badge text-bg-light">Inactive</span>
                                <?php endif; ?>
                                <?php if( $member['member_status'] == 'expired' || $checked_member_expiration == 'true' ): ?>
                                    <span class="badge text-bg-warning">Expired</span>
                                <?php endif; ?>
                                
                            </td>
                            <td>
                                <span class="badge text-bg-dark"><?= $member['member_type'] ?></span>
                            </td>
                            <td>
                                <?= ($member['member_phone'])? $member['member_phone'] : '' ?><br>
                                <?= ($member['member_email'])? $member['member_email'] : '' ?>
                            </td>
                            <!-- <td><?= ($member['member_since'])? format_date_to_readable($member['member_since'] ) : '' ?></td> -->
                            <td><?= ($member['member_length'])? $member['member_length'] : '' ?></td>
                            <td><?= ($member['member_expiration']) ? format_date_to_readable($member['member_expiration']) : '' ?></td>
                            <td><?= ($member['last_renewed']) ? format_date_to_readable($member['last_renewed']) : '' ?></td>
                            <td><?= $member['credit'] ?></td>
                            <td>
                                <a class="btn btn-primary btn-sm view-btn" href="admin-view-member.php?member_id=<?= $member['id'] ?>">View/Edit</a>
                            </td>
                        </tr>

                        <?php endif; ?>

                    <?php 
                    $count_member++;
                    endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="12">No members found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
</body>
</html>


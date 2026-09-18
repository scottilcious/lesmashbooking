<?php
session_start();
// Database connection
require 'config.php';
//$member_id = $_GET['member_id'];

if (!isset($_SESSION["user_id"])) {
    die("Unauthorized access.");
}
?>

<!-- index.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css"rel="stylesheet" />
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include "menu.php"; ?>

    <div class="banner">
        <div class="container">
            <h1>Edit Profile</h1>
        </div>
    </div>

    <div class="page-container mt-5 mb-5">
        <div class="container">

        <?php 
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $first_name = $_POST["first_name"];
            $last_name = $_POST["last_name"];
            $member_phone = $_POST["member_phone"];
            $member_email = $_POST["member_email"];
            $member_password = $_POST["member_password"];

            $stmt = $pdo->prepare("UPDATE members SET 
         first_name= ?, last_name = ?, member_phone = ?, member_email = ?, member_password =?
         WHERE id = ?");
         $result = $stmt->execute([$first_name, $last_name, $member_phone, $member_email, $member_password, $userId]);

        }

        if( $result ):
        ?>
        <div class="card mb-5">
            <div class="card-body">
        <div class="success-message text-center text-success">
            <span class="fs-2">
                <i class="ri-checkbox-circle-line"></i>
            </span>
            <br>
            <p>Your information has been saved successfully</p>
            <a href="index.php" class="btn btn-outline-primary">Done</a>
        </div>
        </div>
        </div>
        
        <?php 
        endif;

        $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
        $stmt->execute([$userId]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>

        <div class="row justify-content-center">
            <div class="col-12 col-md-8">
                <div class="card">
                    <div class="card-body">

                        <!-- Inputs -->
                        <form action="user-edit.php" method="post">
                        <div class="row mt-3">
                            <div class="col-12 col-md-6">
                                <label for="first_name">First name</label>
                                <input class="form-control form-control-lg" type="text" name="first_name" id="first_name" value="<?php echo $members[0]['first_name'];?>" />
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="last_name">Last name</label>
                                <input class="form-control form-control-lg" type="text" name="last_name" id="last_name" value="<?php echo $members[0]['last_name'];?>" />
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-12 col-md-6">
                                <label for="member_phone">Phone number</label>
                                <input class="form-control form-control-lg" type="text" name="member_phone" id="member_phone" value="<?php echo $members[0]['member_phone'];?>" />
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="member_email">Email</label>
                                <input class="form-control form-control-lg" type="text" name="member_email" id="member_email" value="<?php echo $members[0]['member_email'];?>" />
                            </div>
                        </div>

                        <hr>

                        <div class="mt-3">
                            <label for="member_password">Password</label>
                            <input class="form-control form-control-lg" type="text" name="member_password" id="member_password" value="<?php echo $members[0]['member_password'];?>" />
                        </div>

                        <div class="cta-wrapper border-top mt-5 pt-3 mb-5">
                            <button type="submit" class="btn btn-primary" id="saveMember">Save changes</button>
                        </div>
                        </form>

                        <!-- Inputs -->

                    </div>
                </div>
            </div>
        </div>

        </div>
    </div>


</body>
</html>
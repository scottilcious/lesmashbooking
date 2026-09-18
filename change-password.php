<?php
session_start();
require 'config.php';
$member_id = $_GET['member_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];
    $pass_member_id = $_POST["pass_member_id"];

    if (empty($password) || empty($confirm_password)) {
        $error = "Please enter password.";

    }elseif( $password != $confirm_password ){
        $error = "Please make sure password and confirm password match.";
    }else{

        $chng_pwd_stmt = $pdo->prepare("UPDATE members SET member_password = ? WHERE id = ?");
        $chng_pwd_user = $chng_pwd_stmt->execute([$password, $pass_member_id]);


        if( $chng_pwd_user ){

            $stmt = $pdo->prepare("SELECT id, member_number, member_password FROM members WHERE member_number = ? AND member_password = ?");
            $stmt->execute([$pass_member_id, $password]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $_SESSION["user_id"] = $user["id"];
            $_SESSION["member_number"] = $user["member_number"];
            header("Location: login.php?change_password=true");
            exit;

        }else{
            $error = "There is problem changing your password. Please try again";
        }
        

    }


}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Change Password</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css"rel="stylesheet" />
    <link rel="stylesheet" href="style.css">
    <link href="bs-overwrite.css" rel="stylesheet">
</head>
<body class="d-flex justify-content-center align-items-center vh-100">

<div class="container-fluid">
        <div class="row vh-100">
            <!-- Left Side: Image -->
            <div class="col-lg-6 col-md-6 image-side order-sm-0">
                <div class="image-title text-center">
                    <img src="images/logo.png" alt="Le Smash Club" width="150">
                    <h1>Booking System</h1>
                </div>
            </div>

            <!-- Right Side: Login Form -->
            <div class="col-lg-6 col-md-6 form-side order-sm-1">
                <div class="form-container">
                    <div class="card p-4 shadow-lg">
                        <h1>Change password</h1>
                        <div class="alert alert-warning" role="alert">
                            You have logged in the first time.<br>
                            You are required to change password. Please choose the password that you can memorize.
                        </div>
                        <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
                        <form action="change-password.php" method="POST">
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                            <input type="hidden" name="pass_member_id" id="pass_member_id" value="<?= $member_id ?>">

                            <button type="submit" class="btn btn-primary w-100">Change password</button>
                        </form>
                        <p class="text-center mt-3">Don't have an account? <a href="register.php">Register here</a></p>
                        <hr>
                        <p class="text-center mt-3">Have a different account? <a href="login.php">Login</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
</body>
</html>
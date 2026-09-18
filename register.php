<?php
require 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    //Get latest member number
    //$member_type_non_member = "non-member";
    $stmt = $pdo->prepare("SELECT member_number FROM non_members");
    $stmt->execute();
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $member_number_queried = array();
    foreach ($members as $key => $value) {
        array_push($member_number_queried, $value['member_number']);
    }

    sort($member_number_queried);
    $latest_member_number =  end($member_number_queried);
    $treat_member_number = (int)$latest_member_number[1] + 1;
    //$latest_member_number = $latest_member_number+1;
    $non_member_number = $treat_member_number;

    $member_first_name = trim($_POST["member_first_name"]);
    $member_last_name = trim($_POST["member_last_name"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $member_number = "2000".$non_member_number;
    $member_type = "non-member";
    $member_status = "active";
    $member_since = date("Y-m-d");
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];

    if (empty($member_first_name) || empty($member_last_name) || empty($email) || empty($phone) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("INSERT INTO non_members (first_name, last_name, member_number, member_status, member_phone, member_email, member_password, member_since ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$member_first_name, $member_last_name, $member_number, $member_status, $phone,   $email, $password, $member_since]);
            $success = "Registration successful! <br> Your login is as followed: <br><b>Member number: ".$member_number."</b><br><b>Password: ".$password."</b><hr><a href='login.php' class='alert-link'>Login here</a>";

            //Send email 
            $to = $email;
            $subject = "Member registration confirmation - Le Smash Club";

            $message = "
            <h3>Hi $member_first_name,</h3>
            <p>Thank you for registering on our Booking System.</p>
            <p>Your login details:</p>
            <ul>
                <li><strong>Member number:</strong> $member_number</li>
                <li><strong>Password:</strong> $password</li>
            </ul>
            <p><a href='http://booking.lesmashclub.com/login.php'>Login Here</a></p>
            <p>Best regards,<br>Le Smash Club Team</p>
            ";

            // Always set content-type when sending HTML email
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

            // More headers
            $headers .= 'From: <admin@lesmashclub.com>' . "\r\n";

            if (!defined('DISABLE_NOTIFICATIONS') || !DISABLE_NOTIFICATIONS) {
                mail($to,$subject,$message,$headers);
            } else {
                require_once 'includes/functions.php';
                lsc_log('Email (Suppressed)', "To: $to, Subject: $subject");
            }




        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css"rel="stylesheet" />
    <link rel="stylesheet" href="style.css">
</head>
<body class="d-flex justify-content-center align-items-center">

<div class="container-fluid container-register">
        <div class="row vh-100">
            <!-- Left Side: Image -->
            <div class="col-lg-6 col-md-6 image-side d-flex align-items-stretch order-sm-0">
                <div class="image-title text-center">
                    <img src="images/logo.png" alt="Le Smash Club" width="150">
                    <h1>Booking System</h1>
                </div>
            </div>

            <!-- Right Side: Login Form -->
            <div class="col-lg-6 col-md-6 form-side order-sm-1">
                <div class="form-container">
                    <div class="card p-4 shadow-lg">
                    <h1>Register</h1>

                    <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
                    <?php if (isset($success)) echo "<div class='alert alert-success'>$success</div>"; ?>
                    <form action="register.php" method="POST">
                        <div class="row">
                            <div class="col-12 col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">First name</label>
                                    <input type="text" name="member_first_name" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Last name</label>
                                    <input type="text" name="member_last_name" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone number</label>
                            <input type="phone" name="phone" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Register</button>
                    </form>
                    <p class="text-center mt-3">Already have an account? <a href="login.php">Login here</a></p>


                    </div>
                </div>
            </div>
        </div>
    </div>

    
</body>
</html>

<?php
session_start();
require 'config.php';
require_once 'includes/functions.php';
require_once 'includes/password.php';

$member_type = $_GET["member_type"] ?? '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $member_number = trim((string) ($_POST["member_number"] ?? ''));
    $password = (string) ($_POST["password"] ?? '');

    $guest_fullname = trim((string) ($_POST["guest_fullname"] ?? ''));
    $guest_email = trim((string) ($_POST["guest_email"] ?? ''));
    $guest_phone_number = trim((string) ($_POST["guest_phone_number"] ?? ''));

    $login_type = $_POST["login_type"] ?? 'member';

    //MEMBER LOGIN
    if( $login_type == 'member'){

        if( ($member_number && $password )){

            $stmt = $pdo->prepare("SELECT id, member_number, member_password, member_type, first_name, last_name FROM members WHERE member_number = ?");
            $stmt->execute([$member_number]);
            $user = false;
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $candidate) {
                if (lsc_password_verify($password, $candidate['member_password'])) {
                    $user = $candidate;
                    break;
                }
            }

            if ($user && $password == 'lesmashclubmember'){

                $_SESSION['pw_change_member_id'] = (int) $user['id'];
                header("Location: change-password.php");
                exit;
            
            }elseif( $user && $password != 'lesmashclubmember' ){

                $_SESSION["logged_in"] = 'true';
                $_SESSION["user_id"] = $user["id"];
                $_SESSION["member_number"] = $user["member_number"];
                $_SESSION["member_type"] = $user["member_type"];
                $_SESSION["member_fullname"] = $user["first_name"]. ' ' . $user["last_name"];
                lsc_log('Login', "Member logged in. Name: " . $_SESSION["member_fullname"] . ", Type: " . $_SESSION["member_type"] . ", Member Number: " . $_SESSION["member_number"]);
                header("Location: index.php");
                exit;

            } else {

                $error = "Invalid username or password.";

            }
        
        }else{
            $error = "All fields are required.";
        }

    //NON MEMBER LOGIN
    }else{

        if(  $guest_fullname && $guest_email &&  $guest_phone_number ){

            $stmt = $pdo->prepare("SELECT * FROM non_members WHERE guest_name = ? AND member_phone = ? AND member_email = ?");
            $stmt->execute([$guest_fullname, $guest_phone_number, $guest_email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if( $user ){

                $_SESSION["logged_in"] = 'true';
                $_SESSION["user_id"] = $user["id"];
                $_SESSION["member_type"] = 'non-member';
                $_SESSION["member_fullname"] = $user["guest_name"];
                $_SESSION["member_email"] = $user["member_email"];
                $_SESSION["member_phone"] = $user["member_phone"];
                lsc_log('Login', "Non-Member logged in. Name: " . $_SESSION["member_fullname"]);
                header("Location: index.php");
                exit;

            }else{

                $stmt = $pdo->prepare("INSERT INTO non_members (guest_name, member_phone, member_email ) VALUES (?, ?, ?)");
                $non_member_register = $stmt->execute([$guest_fullname, $guest_phone_number, $guest_email]);

                $non_member_id = $pdo->lastInsertId();

                if( $non_member_register){

                    $_SESSION["logged_in"] = 'true';
                    $_SESSION["user_id"] = $non_member_id;
                    $_SESSION["member_type"] = 'non-member';
                    $_SESSION["member_fullname"] = $guest_fullname;
                    $_SESSION["member_email"] = $guest_email;
                    $_SESSION["member_phone"] = $guest_phone_number;
                    lsc_log('Login', "New Non-Member registered and logged in. Name: " . $_SESSION["member_fullname"]);
                    header("Location: index.php");
                    exit;

                }
               
            }

        }else{
            $error = "All fields are required.";
        }

    }

}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
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
            <div class="col-lg-6 col-md-6 col-sm-12 image-side order-sm-0">
                <div class="image-title text-center">
                    <img src="images/logo.png" alt="Le Smash Club" width="150">
                    <h1>Booking System</h1>
                </div>
            </div>

            <!-- Right Side: Login Form -->
            <div class="col-lg-6 col-md-6 col-sm-12 form-side order-sm-1">
                <div class="form-container">
                    <div class="card p-4 shadow-lg">
                        <h1>Login</h1>

                        <div class="alert alert-primary mb-3 mt-3" role="alert">
                            If you are NOT a club member, you can make a booking in <b>non-member booking area</b>
                        </div>

                        <div class="btn-group btn-group-lg mt-3 mb-3" role="group" aria-label="Large button group">
                            <a type="button" class="btn <?= ( $member_type == '')? 'btn-primary' : 'btn-outline-primary' ?> " href="login.php">
                                Member Login</a>
                            <a type="button" class="btn  <?= ( $member_type == 'non-member')? 'btn-primary' : 'btn-outline-primary' ?> " href="login.php?member_type=non-member">
                                Non Member Login</a>
                        </div>
                        
                            <?php if( $member_type == 'non-member'): ?>
                                <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
                                <form action="login.php" method="POST">

                                    <div class="mb-3">
                                        <label class="form-label" for="guest_fullname">Name</label>
                                        <input type="text" class="form-control" placeholder="Enter your name" name="guest_fullname" id="guest_fullname">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label" for="guest_email">Email</label>
                                        <input type="email" class="form-control" placeholder="Enter your email" name="guest_email" id="guest_email">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label" for="guest_phone_number">Phone Number</label>
                                        <input type="phone" class="form-control" placeholder="Enter your phone number" name="guest_phone_number" id="guest_phone_number">
                                    </div>

                                   

                                    <input type="hidden" name="login_type" id="login_type" value="non-member">
                                    <button type="submit" class="btn btn-primary w-100">Login</button>
                                </form>
                            <?php else: ?>

                                <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
                                <form action="login.php" method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Member Number</label>
                                        <input type="text" name="member_number" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Password</label>
                                        <input type="password" name="password" class="form-control" required>
                                    </div>
                                    <input type="hidden" name="login_type" id="login_type" value="member">
                                    <button type="submit" class="btn btn-primary w-100">Login</button>
                                </form>
                                <p class="text-center mt-3">Don't have an account? Please contact our staff at the reception</p>
                                
                            <?php endif; ?>   
                                
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>    
</body>
</html>

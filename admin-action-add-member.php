<?php
session_start();
require 'config.php';
require_once 'includes/password.php';

if (!isset($_SESSION["user_id"])) {
    die("Unauthorized access.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $member_number = $_POST["member_number"];
    $first_name = $_POST["first_name"];
    $last_name = $_POST["last_name"];
    $member_type = $_POST["member_type"];
    $member_status = $_POST["member_status"];
    $member_phone = $_POST["member_phone"];
    $member_email = $_POST["member_email"];
    $member_since = $_POST["member_since"];
    $member_length = $_POST["member_length"];
    $member_expiration = $_POST["member_expiration"];
    $last_renewed = $_POST["last_renewed"];
    /*$new_price = $_POST["new_price"];
    $discount = $_POST["discount"]; */
    $credit = $_POST["credit"];
    $member_note = $_POST["member_note"];
    $member_password = lsc_password_for_storage((string) $_POST["member_password"], null);

    $result_message = "";
    try {
     
        $stmt = $pdo->prepare("INSERT INTO members (member_number, first_name, last_name, member_type, member_status, member_phone, member_email, member_since, member_length, member_expiration, last_renewed, credit, member_password, member_note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $result = $stmt->execute( [$member_number, $first_name, $last_name, $member_type, $member_status, $member_phone, $member_email, $member_since, $member_length, $member_expiration, $last_renewed, $credit, $member_password, $member_note] );

        $member_id = $pdo->lastInsertId();

        $result_message = "success";

    } catch (PDOException $e) {
        $result_message = $e->getMessage();
    }
 
?>
    <?php if( $result_message == "success" ): ?>

        <div class="success-message text-center text-success">
            <span class="fs-2">
                <i class="ri-checkbox-circle-line"></i>
            </span>
            <br>
            <p>Member has been added successfully</p>
            <a href="admin-members.php" class="btn btn-outline-primary">View all members</a>
        </div>

    <?php else: ?>

        <div class="error-message text-center text-danger">
            <span class="fs-2">
                <i class="ri-error-warning-fill"></i>
            </span>
            <br>
            <p>There's something wrong with the member adding. Please try again.</p>
            <p><?php echo  $result_message; ?></p>
            <hr>
            <a href="admin-members.php" class="btn btn-primary">Try again</a>
        </div>

    <?php endif; ?>

<?php 
}
?>
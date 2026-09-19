<?php
require_once __DIR__ . '/includes/auth.php';
$lsc_me = lsc_require_admin();
require 'config.php';
require_once 'includes/password.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $member_id = $_POST["member_id"];

    if( $_POST["action"] ){
        $action = $_POST["action"];
    }else{
        $action = '';
    }

    if( $action == 'delete'){

        $stmt = $pdo->prepare("DELETE FROM members WHERE id = ?");
        $result = $stmt->execute([$member_id]);

    }else{

        
        $member_number = $_POST['member_number'];
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
        $member_note = $_POST["member_note"];
        $existing = $pdo->prepare("SELECT member_password FROM members WHERE id = ?");
        $existing->execute([$member_id]);
        $member_password = lsc_password_for_storage((string) $_POST["member_password"], $existing->fetchColumn() ?: null);


         // Ensure the booking belongs to the logged-in user
         // credit is deliberately NOT updated here: it changes only through
         // admin-adjust-credit.php, so every movement has a reason and a ledger row.
         $stmt = $pdo->prepare("UPDATE members SET member_number = ?,
         first_name= ?, last_name = ?, member_type = ?, member_status = ?, member_phone = ?, member_email = ?, member_since = ?, member_length = ?, member_expiration = ?, last_renewed = ?, member_password =?, member_note =?
         WHERE id = ?");
         $result = $stmt->execute([$member_number, $first_name, $last_name, $member_type, $member_status, $member_phone, $member_email, $member_since, $member_length, $member_expiration, $last_renewed, $member_password,  $member_note, $member_id]);

     }
 
?>
    <?php if( $result && $action == '' ): ?>

        <div class="success-message text-center text-success">
            <span class="fs-2">
                <i class="ri-checkbox-circle-line"></i>
            </span>
            <br>
            <p>Member has been saved successfully</p>
            <a href="admin-view-member.php?member_id=<?php echo $member_id; ?>" class="btn btn-outline-primary">View member</a>
            <a href="admin-members.php" class="btn btn-primary">View all members</a>
        </div>

    <?php elseif( $result && $action == 'delete' ): ?>

        <div class="success-message text-center text-success">
            <span class="fs-2">
                <i class="ri-checkbox-circle-line"></i>
            </span>
            <br>
            <p>Member deleted successfully</p>
            <a href="admin-members.php" class="btn btn-primary">View all members</a>
        </div>

    <?php else: ?>

        <div class="error-message text-center text-danger">
            <span class="fs-2">
                <i class="ri-error-warning-fill"></i>
            </span>
            <br>
            <p>There's something wrong with the booking. Please try again.</p>
            <hr>
            <a href="admin-view-member.php?member_id=<?php echo $member_id; ?>" class="btn btn-primary">Try again</a>
        </div>

    <?php endif; ?>

<?php 
}
?>
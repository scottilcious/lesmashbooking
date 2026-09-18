<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require 'config.php';
    require_once 'includes/functions.php';

    $total_credit = $_POST['total_credit'];
    $add_credit_member_id = $_POST['add_credit_member_id'];
    $add_credit_transaction_id = $_POST['add_credit_transaction_id'];

    //Update Member Credit
    $stmt_mb = $pdo->prepare("UPDATE members SET credit = ? WHERE id = ?");
    $result_member = $stmt_mb->execute([$total_credit, $add_credit_member_id]);


    //Update Transaction 
    $stmt_ts = $pdo->prepare("UPDATE transactions SET transaction_type = 'Credit refill - Approved' WHERE transaction_id = ?");
    $result_transaction = $stmt_ts->execute([$add_credit_transaction_id]);
    
    if( $result_member && $result_transaction ){ 
        lsc_log('Approve Credit Refill', "Approved credit refill of transaction ID: {$add_credit_transaction_id} for Member ID: {$add_credit_member_id}. New total credit: {$total_credit} THB.");
        ?>

    <div class="success-message text-center text-success">
        <span class="fs-2">
            <i class="ri-checkbox-circle-line"></i>
        </span>
        <br>
        <p>Credit has been approved successfully</p>
    </div>


    <?php 
    }else{
    ?>

    <div class="error-message text-center text-danger">
        <span class="fs-2">
            <i class="ri-error-warning-fill"></i>
        </span>
        <br>
        <p>There's something wrong with the booking. Please try again.</p>
    </div>

    <?php 
    }


}
?>
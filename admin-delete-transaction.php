<?php
require_once __DIR__ . '/includes/auth.php';
$lsc_me = lsc_require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require 'config.php';
    require_once 'includes/functions.php';

    $transaction_id = $_POST['transaction_id'];

    $stmt_ts = $pdo->prepare("DELETE FROM transactions WHERE transaction_id = ?");
    $result =  $stmt_ts->execute([$transaction_id]);
    //$transaction_info = $stmt_ts->fetchAll(PDO::FETCH_ASSOC);

    if( $result ){
        lsc_log('Delete Transaction', "Admin deleted transaction ID: {$transaction_id}.");
?>

<div class="success-message text-center text-success">
    <span class="fs-2">
        <i class="ri-checkbox-circle-line"></i>
    </span>
    <br>
    <p>Transaction has been deleted successfully</p>
</div>

<?php 
    }else{ 
?>

<div class="error-message text-center text-danger">
    <span class="fs-2">
        <i class="ri-error-warning-fill"></i>
    </span>
    <br>
    <p>There's something wrong with deleting this transaction. Please try again.</p>
</div>

<?php 
    }
}
?>
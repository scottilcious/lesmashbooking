<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require 'config.php';

    $transaction_id = $_POST['transaction_id'];

    $stmt_ts = $pdo->prepare("SELECT * FROM transactions WHERE transaction_id = ?");
    $stmt_ts->execute([$transaction_id]);
    $transaction_info = $stmt_ts->fetchAll(PDO::FETCH_ASSOC);


    //Process transaction preinfo 
    $transaction_type = $transaction_info[0]['transaction_type'];
    switch ($transaction_type) {
        case 'cancelled booking':
            $transaction_title = $transaction_info[0]['transaction_title'];
            $transaction_type_class = 'cancelled';
            $transaction_badge = '<span class="badge text-bg-danger">Cancelled booking</span>';
            $amount_type_symbol = '';
            $slip_credit_text = 'Credit refunded';
        
            break;

        case 'cancelled':
            $transaction_title = $transaction_info[0]['transaction_title'];
            $transaction_type_class = 'cancelled';
            $transaction_badge = '<span class="badge text-bg-danger">Cancelled booking</span>';
            $amount_type_symbol = '';
            $slip_credit_text = 'Credit refunded';
            break;

        case 'cancelled-rain-half':
            $transaction_title = $transaction_info[0]['transaction_title'];
            $transaction_type_class = 'cancelled';
            $transaction_badge = '<span class="badge text-bg-danger">Cancelled booking<br>(Rain/Pollution)<br>- Half refunded</span>';
            $amount_type_symbol = '-';
            $slip_credit_text = 'Credit refunded';
            break;

        case 'cancelled-rain':
            $transaction_title = $transaction_info[0]['transaction_title'];
            $transaction_type_class = 'cancelled';
            $transaction_badge = '<span class="badge text-bg-danger">Cancelled booking<br>(Rain/Pollution)<br>- Fully refunded</span>';
            $amount_type_symbol = '-';
            $slip_credit_text = 'Credit refunded';
            break;

        case 'booking (member)':
            $transaction_title = 'Member booking';
            $transaction_type_class = 'booking';
            $transaction_badge = '<span class="badge text-bg-success">Member booking</span>';
            $amount_type_symbol = '+';
            $slip_credit_text = '';
            break;

        case 'booking (non member)':
            $transaction_title = 'Non member booking';
            $transaction_type_class = 'booking';
            $transaction_badge = '<span class="badge text-bg-success">Non Member booking</span>';
            $amount_type_symbol = '+';
            break;

        case 'Credit refill':
            $transaction_title = $transaction_info[0]['transaction_title'];
            $transaction_type_class = 'credit-refill';
            $transaction_badge = '<span class="badge text-bg-primary">Credit refill</span>';
            $amount_type_symbol = '+';
            $slip_credit_text = 'Credit refilled';
            break;

        case 'Credit refill - Approved':
            $transaction_title = $transaction_info[0]['transaction_title'];
            $transaction_type_class = 'credit-refill-approved';
            $transaction_badge = '<span class="badge text-bg-primary">Credit refill (approved)</span>';
            $amount_type_symbol = '+';
            $slip_credit_text = 'Credit refilled';
            break;
        
        default:
            # code...
            break;
    }

    //Get booking info
    if($transaction_info[0]['assoc_transaction_id'] == ''){

        $stmt_assoc_ts = $pdo->prepare("SELECT * FROM transactions WHERE assoc_transaction_id = ?");
        $stmt_assoc_ts->execute([$transaction_id]);
        $get_transaction_data = $stmt_assoc_ts->fetchAll(PDO::FETCH_ASSOC);

    }

    

?>

<h2>Transaction Info</h2>
<div class="table-responsive">
    <table class="table table-bordered">
        <tbody>
            <tr>
                <td>Transaction title</td>
                <td><?= $transaction_title ?></td>
            </tr>
            <?php if( $transaction_info[0]['assoc_transaction_id'] != '' ): ?>
            <tr>
                <td>Member info</td>
                <td>
                    <?php 

                    //&&  ( $transaction_info[0]['non_member_id'] == '90001' || $transaction_info[0]['non_member_id'] == '90002' 
                    if( $transaction_info[0]['member_id'] && $transaction_info[0]['member_id'] != '90002' && $transaction_info[0]['member_id'] != '90001' ){
                        $stmt_mb = $pdo->prepare("SELECT * FROM members WHERE id = ?");
                        $stmt_mb->execute([$transaction_info[0]['member_id']]);
                        $member_info = $stmt_mb->fetchAll(PDO::FETCH_ASSOC);

                        echo '<span>Member name</span><br>';
                        echo '<p class="fw-bold">'. $member_info[0]['first_name'] . ' ' . $member_info[0]['last_name'] . '</p>';
                        echo '<span>Member number</span><br>';
                        echo '<p class="fw-bold">'. $member_info[0]['member_number'] . '</p>';
                        echo '<span>Member email</span><br>';
                        echo '<p class="fw-bold">'. $member_info[0]['member_email'] . '</p>';
                        echo '<span>Member phone</span><br>';
                        echo '<p class="fw-bold">'. $member_info[0]['member_phone'] . '</p>';

                    }else{
                        $stmt_mb = $pdo->prepare("SELECT * FROM non_members WHERE id = ?");
                        $stmt_mb->execute([$transaction_info[0]['non_member_id']]);
                        $member_info = $stmt_mb->fetchAll(PDO::FETCH_ASSOC);

                        echo '<span>Member name</span><br>';
                        echo '<p class="fw-bold">'. $member_info[0]['guest_name'] . '</p>';
                        echo '<span>Member email</span><br>';
                        echo '<p class="fw-bold">'. $member_info[0]['member_email'] . '</p>';
                        echo '<span>Member phone</span><br>';
                        echo '<p class="fw-bold">'. $member_info[0]['member_phone'] . '</p>';
                        
                    }


                    
                    ?>
                </td>
            </tr>
            <?php endif; ?>
            
            <tr>
                <td>Transaction amount</td>
                <td><span class="fw-bold fs-3"><?= $transaction_info[0]['transaction_amount'] ?></span></td>
            </tr>
            <?php 
                if( $transaction['assoc_transaction_id'] == '' ){
                ?>
            <tr>
                <td>Transaction for bookings</td>
                <td>
                    <?php
                    foreach ($get_transaction_data as $key => $value) {
                        $this_derived_transaction_id = $value['transaction_id'];

                        echo '<div class="card mt-2"><div class="card-body p-1">';
                        echo '<small>Transaction #' . $this_derived_transaction_id . '</small>';
                        echo '<br>';
                        echo $value['transaction_amount'] . ' THB';
                        echo '</div></div>';
                    }
                    ?>
                </td>
            </tr>
                <?php } ?>
            <tr>
                <td>Transaction type</td>
                <td>
                    <?= $transaction_badge ?> 
                </td>
            </tr>
            <!-- <tr>
                <td>Payment type</td>
                <td>
                    <?= $transaction_info[0]['payment_type'] ?>
                    
                </td>
            </tr> -->
            <tr>
                <td>Slip/Credit</td>
                <td>
                    

                    <?php if( ($transaction_info[0]['slip_url'] == '' || $transaction_info[0]['slip_url'] == NULL) && $transaction_info[0]['payment_type'] == 'credit'  ): ?>
                        <?= $slip_credit_text ?><br>
                        <span class="badge text-bg-dark"><?= $amount_type_symbol ?> <?=  number_format($transaction_info[0]['transaction_amount'], "0", "", "") ?></span>

                    <?php elseif( ($transaction_info[0]['slip_url'] == '' || $transaction_info[0]['slip_url'] == NULL) && $transaction_info[0]['payment_type'] == 'cash'  ): ?>
                        Cash (Admin)
                    <?php else: ?>
                        <img src="uploads/<?= $transaction_info[0]['slip_url'] ?>" alt="" with="640" style="max-width: 80%;">
                        <br>
                        <a class="btn btn-outline-primary btn-sm view-btn" href="uploads/<?= $transaction_info[0]['slip_url'] ?>" target="_blank">View Full Slip</a>
                    <?php endif;  ?>
                </td>
            </tr>
        </tbody>
    </table>

    
</div>


<?php 
}
?>
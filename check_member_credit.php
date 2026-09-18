<?php
require_once __DIR__ . '/includes/auth.php';
$lsc_me = lsc_require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require 'config.php';

    $transaction_id = $_POST['transaction_id'];
    $transaction_type = $_POST['transaction_type'];
    $member_id = $_POST['member_id'];

    //Get Member Current Credit
    $stmt_mb = $pdo->prepare("SELECT * FROM members WHERE id = ?");
    $stmt_mb->execute([$member_id]);
    $member_info = $stmt_mb->fetchAll(PDO::FETCH_ASSOC);

    $stmt_ts = $pdo->prepare("SELECT * FROM transactions WHERE transaction_id = ?");
    $stmt_ts->execute([$transaction_id]);
    $transaction_info = $stmt_ts->fetchAll(PDO::FETCH_ASSOC);

    ?>

<div class="card">
    <div class="card-body">
        <h5>Member Info</h5>

        <div class="row">
            <div class="col-12 col-md-4">
                <span>Member Name</span>
                <p class="fw-bold"><?= $member_info[0]['first_name'] ?> <?= $member_info[0]['last_name'] ?></p>
            </div>
            <div class="col-12 col-md-4">
                <span>Member Number</span>
                <p class="fw-bold"><?= $member_info[0]['member_number'] ?></p>
            </div>
            <div class="col-12 col-auto">
                <a href="admin-view-member?member_id=<?= $member_info[0]['id'] ?>" class="btn btn-outline-primary">View this member</a>
            </div>
        </div>

    </div>
</div>

<div class="card border-success mt-3">
    <div class="card-body">
        <h5>Credit refill info</h5>

        <div class="row">
            <div class="col-12 col-md-8">
                <span>Credit refill status</span>
                <p>
                    <?php if( $transaction_info[0]['transaction_type'] == 'Credit refill'){ ?>
                        <span class="badge rounded-pill text-bg-light">Pending</span>
                    <?php }else{ ?>
                        <span class="badge rounded-pill text-bg-success">Approved</span>
                    <?php } ?>
                </p>

                <span>Credit refilled</span>
                <p class="fw-bold text-success border-bottom"><?= number_format($transaction_info[0]['transaction_amount'],2,'.', ',' ) ?></p>

                <span>Transaction date & time</span>
                <p class="fw-bold border-bottom">
                    <?php   
                    $transaction_date = date_create($transaction_info[0]['created_at']);
                    echo date_format($transaction_date,"d F, Y H:i:s");
                    ?>
                </p>

                <span>Slip</span>
                <div class="slip-wrapper mb-3">
                    <img src="uploads/<?= $transaction_info[0]['slip_url'] ?>" alt="" with="640" style="max-width: 100%;">
                </div>



            </div>
            <div class="col-12 col-md-4">
                <span>Current member credit</span>
                <p class="border-bottom mb-2"><?= number_format($member_info[0]['credit'], 2, '.', ',') ?></p>
                <span>Credit refilled</span>
                <p class="fw-bold border-bottom border-2 text-success mb-2"><?= number_format($transaction_info[0]['transaction_amount'], 2, '.', ',') ?></p>
                <span>Total final member credit</span>
                <h4 class="fw-bold fs-3">
                    <?php 
                    $total_credit = $member_info[0]['credit'] + $transaction_info[0]['transaction_amount'];
                    ?>
                    <input type="number" id="total_credit" name="total_credit"
                    value="<?= $total_credit ?>">
                    <input type="hidden" name="add_credit_member_id" id="add_credit_member_id" value="<?= $member_id ?>">
                    <input type="hidden" name="add_credit_transaction_id" id="add_credit_transaction_id" value="<?= $transaction_id ?>">
                </h4>
                <?php if( $transaction_info[0]['transaction_type'] == 'Credit refill'){ ?>
                    <span class="badge rounded-pill text-bg-light">Pending</span>
                <button type="button" id="approveThisCredit" class="btn btn-secondary btn-lg">Approve Credit</button>
                <?php }else{  ?>
                    <span class="badge rounded-pill text-bg-success">Approved</span>
                <?php } ?>
            </div>
        </div>

        

        

        
    </div>
</div>


          
<?php 
}
?>

<script>
    $(document).ready(function () {

        $('#approveThisCredit').click(function(){

            let total_credit = $("#total_credit").val();
            let add_credit_member_id = $("#add_credit_member_id").val();
            let add_credit_transaction_id = $("#add_credit_transaction_id").val();

            $.post("admin-approve-credit.php", { 
                    total_credit: total_credit, 
                    add_credit_member_id: add_credit_member_id, 
                    add_credit_transaction_id: add_credit_transaction_id }, 
            function (data) {
                $('#transactionContent').html(data);
            });

        });

    });
</script>
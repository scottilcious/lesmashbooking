<?php
require_once __DIR__ . '/includes/auth.php';
$lsc_me = lsc_require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require 'config.php';
    require_once 'includes/functions.php';

    $transaction_id  = (int) ($_POST['add_credit_transaction_id'] ?? 0);
    $approved_amount = isset($_POST['approved_amount']) && $_POST['approved_amount'] !== '' ? (int) $_POST['approved_amount'] : null;

    $error = '';
    $new_balance = null;
    try {
        $pdo->beginTransaction();

        $st = $pdo->prepare("SELECT transaction_id, member_id, transaction_amount, transaction_type FROM transactions WHERE transaction_id = ? FOR UPDATE");
        $st->execute([$transaction_id]);
        $tx = $st->fetch(PDO::FETCH_ASSOC);

        if (!$tx) {
            $error = 'Transaction not found.';
        } elseif ($tx['transaction_type'] !== 'Credit refill') {
            $error = 'This refill has already been approved (or is not a credit refill).';
        } else {
            $amount = $approved_amount ?? (int) $tx['transaction_amount'];
            if ($amount <= 0) {
                $error = 'Approved amount must be greater than zero.';
            } else {
                // Add the amount to whatever the balance is NOW (the member may have booked since the page was opened).
                $up = $pdo->prepare("UPDATE transactions SET transaction_type = 'Credit refill - Approved', transaction_amount = ?, transaction_note = CONCAT(COALESCE(transaction_note, ''), ?) WHERE transaction_id = ? AND transaction_type = 'Credit refill'");
                $up->execute([$amount, $amount != (int) $tx['transaction_amount'] ? " [approved amount adjusted from {$tx['transaction_amount']} by admin]" : '', $transaction_id]);
                if ($up->rowCount() !== 1) {
                    $error = 'This refill was approved by someone else a moment ago.';
                } else {
                    $mb = $pdo->prepare("UPDATE members SET credit = credit + ? WHERE id = ?");
                    $mb->execute([$amount, $tx['member_id']]);
                    $bal = $pdo->prepare("SELECT credit FROM members WHERE id = ?");
                    $bal->execute([$tx['member_id']]);
                    $new_balance = (float) $bal->fetchColumn();
                }
            }
        }

        if ($error === '') {
            $pdo->commit();
            lsc_log('Approve Credit Refill', "Approved credit refill transaction ID: {$transaction_id} for Member ID: {$tx['member_id']}. Added {$amount} THB. New balance: {$new_balance} THB.");
        } else {
            $pdo->rollBack();
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'Database error while approving credit.';
        lsc_log('Approve Credit Refill Failed', "Transaction ID: {$transaction_id}. " . $e->getMessage());
    }

    if ($error === '') {
        ?>
    <div class="success-message text-center text-success">
        <span class="fs-2">
            <i class="ri-checkbox-circle-line"></i>
        </span>
        <br>
        <p>Credit has been approved successfully.<br><b><?= number_format($amount) ?> THB</b> added. New balance: <b><?= number_format($new_balance, 2) ?> THB</b></p>
    </div>
        <?php
    } else {
        http_response_code(409);
        ?>
    <div class="error-message text-center text-danger">
        <span class="fs-2">
            <i class="ri-error-warning-fill"></i>
        </span>
        <br>
        <p><?= htmlspecialchars($error) ?></p>
    </div>
        <?php
    }
}

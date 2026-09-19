<?php
/**
 * Admin credit adjustment. The only way a balance is edited by hand.
 * Returns an HTML fragment; 409 on a refusal so the caller can show the reason.
 */
require_once __DIR__ . '/includes/auth.php';
$lsc_me = lsc_require_admin();
require 'config.php';
require_once 'includes/credit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST only.');
}

$member_id  = (int) ($_POST['member_id'] ?? 0);
$direction  = $_POST['direction'] ?? 'add';          // add | deduct
$amount     = (int) round((float) ($_POST['amount'] ?? 0));
$reason     = trim((string) ($_POST['reason'] ?? ''));

$error = '';
if ($member_id <= 0) {
    $error = 'No member selected.';
} elseif ($amount <= 0) {
    $error = 'Enter an amount greater than zero.';
} elseif ($reason === '') {
    $error = 'A reason is required so the adjustment can be explained later.';
} elseif (mb_strlen($reason) > 200) {
    $error = 'Please keep the reason under 200 characters.';
}

if ($error === '') {
    $delta = $direction === 'deduct' ? -$amount : $amount;
    try {
        $result = lsc_credit_adjust($pdo, $member_id, $delta, $reason);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($error !== '') {
    http_response_code(409);
    ?>
    <div class="alert alert-danger mb-0" role="alert">
        <i class="ri-error-warning-fill"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php
    exit;
}
?>
<div class="alert alert-success mb-0" role="alert">
    <i class="ri-checkbox-circle-line"></i>
    <b><?= $delta > 0 ? '+' : '' ?><?= number_format($delta) ?> THB</b> recorded.
    New balance: <b><?= number_format($result['balance'], 2) ?> THB</b>.
    <div class="mt-2"><button type="button" class="btn btn-sm btn-primary" onclick="location.reload()">Done</button></div>
</div>

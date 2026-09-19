<?php
/**
 * Adds the credit-ledger columns to `transactions` and backfills history.
 *
 *   php database/migrate-add-credit-ledger.php            (dry run: report only)
 *   php database/migrate-add-credit-ledger.php --apply    (alter + backfill)
 *
 * Idempotent: the ALTERs are skipped when the columns exist, and the backfill
 * only touches rows that have not been stamped yet (credit_delta = 0 and
 * actor_type IS NULL), so re-running it will not double anything.
 *
 * credit_delta is the signed effect on members.credit. Rows recorded for
 * information only keep 0: the per-court child rows of a booking, and the
 * "Guest transaction" row whose amount is already inside its parent.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require_once __DIR__ . '/../config.php';

$apply = in_array('--apply', $argv, true);

function col_exists(PDO $pdo, string $table, string $col): bool
{
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $st->execute([$table, $col]);
    return (int) $st->fetchColumn() > 0;
}

$columns = [
    'actor_type'   => "ALTER TABLE transactions ADD COLUMN actor_type VARCHAR(20) NULL AFTER transaction_note",
    'actor_id'     => "ALTER TABLE transactions ADD COLUMN actor_id INT NULL AFTER actor_type",
    'actor_name'   => "ALTER TABLE transactions ADD COLUMN actor_name VARCHAR(255) NULL AFTER actor_id",
    'credit_delta' => "ALTER TABLE transactions ADD COLUMN credit_delta INT NOT NULL DEFAULT 0 AFTER actor_name",
];

$missing = [];
foreach ($columns as $col => $sql) {
    if (!col_exists($pdo, 'transactions', $col)) {
        $missing[$col] = $sql;
    }
}

echo $missing ? 'Columns to add: ' . implode(', ', array_keys($missing)) . "\n" : "All columns already present.\n";

if ($apply && $missing) {
    foreach ($missing as $col => $sql) {
        $pdo->exec($sql);
        echo "  added $col\n";
    }
    $idx = $pdo->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'transactions' AND index_name = 'member_credit'");
    $idx->execute();
    if ((int) $idx->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE transactions ADD INDEX member_credit (member_id, credit_delta)");
        echo "  added index member_credit\n";
    }
}

if (!$apply && $missing) {
    exit("\nDry run: pass --apply to add the columns and backfill.\n");
}

/* ---------------------------------------------------------------- backfill */

$unstamped = "credit_delta = 0 AND actor_type IS NULL";

$rules = [
    // label                        => [where, credit_delta expression]
    'refill approved (in)'          => ["transaction_type = 'Credit refill - Approved'", 'transaction_amount'],
    'admin credit add (in)'         => ["transaction_type = 'Admin credit add'", 'transaction_amount'],
    'refund on cancellation (in)'   => ["transaction_type IN ('cancelled','cancelled booking','cancelled-rain','cancelled-rain-half') AND transaction_amount > 0", 'transaction_amount'],
    'booking paid by credit (out)'  => ["transaction_type IN ('booking (member)','booking') AND payment_type = 'credit' AND (assoc_transaction_id IS NULL OR assoc_transaction_id = '') AND transaction_title <> 'Guest transaction'", '-transaction_amount'],
    'membership renewal (out)'      => ["transaction_type = 'Membership renewal' AND payment_type = 'credit' AND (assoc_transaction_id IS NULL OR assoc_transaction_id = '')", '-transaction_amount'],
];

echo "\nBackfilling credit_delta:\n";
foreach ($rules as $label => [$where, $expr]) {
    $count = $pdo->query("SELECT COUNT(*) FROM transactions WHERE $unstamped AND ($where)")->fetchColumn();
    printf("  %-32s %7d rows\n", $label, $count);
    if ($apply && $count > 0) {
        $pdo->exec("UPDATE transactions SET credit_delta = $expr WHERE $unstamped AND ($where)");
    }
}

/* Actor, only where the stored note or type makes it certain. */
$actors = [
    // Most specific first: waitlist expiry rows carry a "cancelled by member" note
    // but were written by the automation, not by the member.
    'system' => "transaction_title = 'Cancelled from waitlist expiration' OR transaction_note LIKE '%waitlist offer expired%'",
    'admin'  => "transaction_type IN ('Admin credit add','Credit refill - Approved') OR transaction_note LIKE 'cancelled by admin%' OR transaction_note LIKE 'admin-cancelled%' OR transaction_note LIKE '%by admin%'",
    'member' => "transaction_type = 'Credit refill' OR transaction_note LIKE 'cancelled by member%'",
];

echo "\nBackfilling actor_type:\n";
foreach ($actors as $type => $where) {
    $count = $pdo->query("SELECT COUNT(*) FROM transactions WHERE actor_type IS NULL AND ($where)")->fetchColumn();
    printf("  %-32s %7d rows\n", $type, $count);
    if ($apply && $count > 0) {
        $pdo->exec("UPDATE transactions SET actor_type = '$type' WHERE actor_type IS NULL AND ($where)");
    }
}

$unknown = $pdo->query("SELECT COUNT(*) FROM transactions WHERE actor_type IS NULL")->fetchColumn();
printf("  %-32s %7d rows (shown as \"Unknown\")\n", 'not determinable', $unknown);

if (!$apply) {
    exit("\nDry run. Pass --apply to write.\n");
}

/* ---------------------------------------------------------------- report */

$drift = $pdo->query("
    SELECT COUNT(*) FROM members m
    WHERE ABS(m.credit - COALESCE((SELECT SUM(t.credit_delta) FROM transactions t WHERE t.member_id = m.id), 0)) >= 0.01
")->fetchColumn();

echo "\nDone.\n";
printf("%d member(s) whose balance does not match the sum of their ledger.\n", $drift);
echo "That difference is opening balances and past direct edits, which were never recorded.\n";
echo "Run database/backfill-opening-balances.php to record it as an opening balance per member.\n";

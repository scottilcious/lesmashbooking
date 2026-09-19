<?php
/**
 * Records each member's unexplained balance as an "Opening balance" ledger row,
 * so that SUM(credit_delta) equals members.credit for everyone and the credit
 * statement on the member page always reconciles.
 *
 *   php database/backfill-opening-balances.php            (dry run)
 *   php database/backfill-opening-balances.php --apply
 *
 * The difference being recorded is real money that was never logged: opening
 * balances set when the member was created, and later direct edits to the
 * credit field on the member form. Run this AFTER
 * database/migrate-add-credit-ledger.php --apply.
 *
 * Idempotent: a member who already has an Opening balance row is skipped, and
 * running it again finds no drift because the first run removed it.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require_once __DIR__ . '/../config.php';

$apply = in_array('--apply', $argv, true);

$rows = $pdo->query("
    SELECT m.id, m.member_number, m.first_name, m.last_name, m.credit,
           COALESCE((SELECT SUM(t.credit_delta) FROM transactions t WHERE t.member_id = m.id), 0) AS ledger,
           COALESCE(m.member_since, DATE(m.created_at), '2025-01-01') AS since,
           (SELECT COUNT(*) FROM transactions t2 WHERE t2.member_id = m.id AND t2.transaction_type = 'Opening balance') AS has_opening
    FROM members m
")->fetchAll(PDO::FETCH_ASSOC);

$todo = [];
foreach ($rows as $r) {
    if ((int) $r['has_opening'] > 0) {
        continue;
    }
    $diff = (int) round((float) $r['credit'] - (float) $r['ledger']);
    if ($diff !== 0) {
        $r['diff'] = $diff;
        $todo[] = $r;
    }
}

printf("%d member(s) need an opening balance row%s\n", count($todo), $apply ? '' : ' (dry run, pass --apply to write)');
$pos = array_sum(array_map(static fn($r) => $r['diff'] > 0 ? $r['diff'] : 0, $todo));
$neg = array_sum(array_map(static fn($r) => $r['diff'] < 0 ? $r['diff'] : 0, $todo));
printf("  positive total %s THB, negative total %s THB\n", number_format($pos), number_format($neg));

foreach (array_slice($todo, 0, 10) as $r) {
    printf("  #%-6s %-28s balance %9s  ledger %9s  opening %+d\n",
        $r['member_number'], trim($r['first_name'] . ' ' . $r['last_name']),
        number_format((float) $r['credit'], 2), number_format((float) $r['ledger'], 2), $r['diff']);
}
if (count($todo) > 10) {
    printf("  ... and %d more\n", count($todo) - 10);
}

if (!$apply || !$todo) {
    exit(0);
}

$pdo->beginTransaction();
$st = $pdo->prepare("INSERT INTO transactions
    (transaction_title, member_id, non_member_id, non_member_info, transaction_amount, transaction_type, payment_type, slip_url, transaction_note, actor_type, actor_name, credit_delta, created_at)
    VALUES ('Opening balance', ?, 90002, 'N/A', ?, 'Opening balance', 'credit', '', ?, 'system', 'System', ?, ?)");
foreach ($todo as $r) {
    $st->execute([
        $r['id'],
        abs($r['diff']),
        'Balance recorded when the credit ledger was introduced; covers the opening balance and any earlier direct edits.',
        $r['diff'],
        $r['since'] . ' 00:00:00',
    ]);
}
$pdo->commit();

$drift = $pdo->query("
    SELECT COUNT(*) FROM members m
    WHERE ABS(m.credit - COALESCE((SELECT SUM(t.credit_delta) FROM transactions t WHERE t.member_id = m.id), 0)) >= 0.01
")->fetchColumn();

printf("Written %d row(s). Members still not reconciling: %d\n", count($todo), $drift);

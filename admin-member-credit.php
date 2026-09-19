<?php
/**
 * Credit activity for one member: every movement, what it was for and who made it.
 * Opened from the "Credit activity" button beside the balance on the member page.
 */
require_once __DIR__ . '/includes/auth.php';
$lsc_me = lsc_require_admin('redirect');
require 'config.php';
require_once 'includes/credit.php';

$member_id = isset($_GET['member_id']) ? (int) $_GET['member_id'] : 0;
$page      = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$per_page  = 30;

$stmt = $pdo->prepare('SELECT * FROM members WHERE id = ?');
$stmt->execute([$member_id]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credit activity - Admin Le Smash Club</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet" />
    <link rel="stylesheet" href="style.css?v=<?= date('Ymd') ?>">
    <link href="bs-overwrite.css" rel="stylesheet">
    <style>@media print { header, .no-print { display: none !important; } }</style>
</head>
<body class="page-id-3">
<?php include "menu.php"; ?>

<div class="banner">
    <div class="container">
        <h1>Credit Activity</h1>
    </div>
</div>

<div class="container mt-4 mb-5">
<?php if (!$member): ?>
    <div class="alert alert-danger">Member not found.</div>
<?php else:
    $balance = (float) $member['credit'];
    $ledger_total = lsc_credit_ledger_total($pdo, $member_id);
    $reconciles = abs($ledger_total - $balance) < 0.01;

    $count_stmt = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE member_id = ? AND credit_delta <> 0');
    $count_stmt->execute([$member_id]);
    $total_rows = (int) $count_stmt->fetchColumn();
    $total_pages = max(1, (int) ceil($total_rows / $per_page));
    $page = min($page, $total_pages);
    $offset = ($page - 1) * $per_page;

    // Every movement, newest first, so the running balance can be walked back from today.
    $all_stmt = $pdo->prepare('SELECT * FROM transactions WHERE member_id = ? AND credit_delta <> 0 ORDER BY created_at DESC, transaction_id DESC');
    $all_stmt->execute([$member_id]);
    $all = $all_stmt->fetchAll(PDO::FETCH_ASSOC);

    $running = $balance;
    $balance_after = [];
    foreach ($all as $row) {
        $balance_after[(int) $row['transaction_id']] = $running;
        $running -= (int) $row['credit_delta'];
    }
    $rows = array_slice($all, $offset, $per_page);

    // The per-court detail lines for the bookings on this page, in one query.
    $children = [];
    $ids = array_map(static fn($r) => (int) $r['transaction_id'], $rows);
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $ch = $pdo->prepare("SELECT * FROM transactions WHERE assoc_transaction_id IN ($in) ORDER BY transaction_id");
        $ch->execute($ids);
        foreach ($ch->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $children[(int) $c['assoc_transaction_id']][] = $c;
        }
    }

    $in_total  = array_sum(array_map(static fn($r) => max(0, (int) $r['credit_delta']), $all));
    $out_total = array_sum(array_map(static fn($r) => min(0, (int) $r['credit_delta']), $all));
?>
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3 no-print">
        <div>
            <h2 class="fs-3 mb-1">
                <?= htmlspecialchars(trim($member['first_name'] . ' ' . $member['last_name'])) ?>
                <span class="badge text-bg-light">#<?= htmlspecialchars((string) $member['member_number']) ?></span>
                <span class="badge rounded-pill text-bg-primary"><?= htmlspecialchars((string) $member['member_type']) ?></span>
            </h2>
            <a href="admin-view-member.php?member_id=<?= (int) $member_id ?>">&larr; Back to member</a>
        </div>
        <div>
            <button class="btn btn-outline-secondary" onclick="window.print()"><i class="ri-printer-line"></i> Print</button>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card h-100"><div class="card-body">
                <small class="text-body-tertiary">Current balance</small>
                <div class="fs-3 fw-bold"><?= number_format($balance, 2) ?></div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100"><div class="card-body">
                <small class="text-body-tertiary">Total credit in</small>
                <div class="fs-3 fw-bold text-success">+<?= number_format($in_total) ?></div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100"><div class="card-body">
                <small class="text-body-tertiary">Total credit out</small>
                <div class="fs-3 fw-bold text-danger"><?= number_format($out_total) ?></div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100"><div class="card-body">
                <small class="text-body-tertiary">Movements</small>
                <div class="fs-3 fw-bold"><?= number_format($total_rows) ?></div>
            </div></div>
        </div>
    </div>

    <?php if ($reconciles): ?>
        <div class="alert alert-success"><i class="ri-checkbox-circle-line"></i>
            Every movement is recorded and they add up to the current balance.</div>
    <?php else: ?>
        <div class="alert alert-warning"><i class="ri-error-warning-line"></i>
            These movements add up to <b><?= number_format($ledger_total, 2) ?> THB</b> but the balance is
            <b><?= number_format($balance, 2) ?> THB</b>. The difference of
            <b><?= number_format($balance - $ledger_total, 2) ?> THB</b> was never recorded.
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
                <tr>
                    <th style="width:15%">Date &amp; time</th>
                    <th>What for</th>
                    <th style="width:16%">By who</th>
                    <th class="text-end" style="width:10%">In</th>
                    <th class="text-end" style="width:10%">Out</th>
                    <th class="text-end" style="width:12%">Balance after</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="6" class="text-center py-4 text-muted">No credit activity for this member yet.</td></tr>
            <?php else: foreach ($rows as $row):
                $delta = (int) $row['credit_delta'];
                $kids  = $children[(int) $row['transaction_id']] ?? [];
                $actor_type = $row['actor_type'] ?? null;
                $actor_badge = ['admin' => 'text-bg-primary', 'member' => 'text-bg-success', 'system' => 'text-bg-secondary'][$actor_type] ?? 'text-bg-light';
            ?>
                <tr>
                    <td><?= date('d M Y', strtotime($row['created_at'])) ?><br><small class="text-body-tertiary"><?= date('H:i', strtotime($row['created_at'])) ?></small></td>
                    <td>
                        <?= htmlspecialchars(lsc_credit_description($row, $kids)) ?>
                        <div><?= lsc_member_transaction_badge((string) $row['transaction_type']) ?></div>
                    </td>
                    <td><span class="badge <?= $actor_badge ?>"><?= htmlspecialchars(lsc_actor_label($actor_type, $row['actor_name'] ?? null)) ?></span></td>
                    <td class="text-end fw-bold text-success"><?= $delta > 0 ? '+' . number_format($delta) : '-' ?></td>
                    <td class="text-end fw-bold text-danger"><?= $delta < 0 ? number_format(abs($delta)) : '-' ?></td>
                    <td class="text-end fw-bold"><?= number_format($balance_after[(int) $row['transaction_id']] ?? 0, 2) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
        <nav class="no-print"><ul class="pagination justify-content-center">
            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="admin-member-credit.php?member_id=<?= (int) $member_id ?>&page=<?= $p ?>"><?= $p ?></a>
                </li>
            <?php endfor; ?>
        </ul></nav>
    <?php endif; ?>
<?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>

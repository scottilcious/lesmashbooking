<?php
require_once __DIR__ . '/includes/auth.php';
$lsc_me = lsc_require_admin('redirect');
require 'config.php';
require_once 'includes/logging.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - Admin Le Smash Club</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet" />
    <link rel="stylesheet" href="style.css?v=<?= date('Ymd') ?>">
    <link href="bs-overwrite.css" rel="stylesheet">
</head>
<body class="page-id-8">
<?php
include "menu.php";

$search     = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date   = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$month      = isset($_GET['month']) ? trim($_GET['month']) : '';
$page       = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;

$result   = lsc_log_query(compact('search', 'start_date', 'end_date', 'month') + ['page' => $page, 'limit' => 50]);
$entries  = $result['entries'];
$months   = $result['months'];
$selected = $month !== '' ? $month : ($start_date === '' && $end_date === '' ? ($months[0] ?? '') : '');

function lsc_log_query_string(array $over = []): string
{
    $params = array_merge([
        'search'     => $_GET['search'] ?? '',
        'start_date' => $_GET['start_date'] ?? '',
        'end_date'   => $_GET['end_date'] ?? '',
        'month'      => $_GET['month'] ?? '',
    ], $over);
    return '?' . http_build_query(array_filter($params, static fn($v) => $v !== '' && $v !== null));
}

function lsc_log_pagination(int $totalPages, int $currentPage): array
{
    if ($totalPages <= 1) {
        return [];
    }
    $out = [1];
    $from = max(2, $currentPage - 4);
    $to   = min($totalPages - 1, $currentPage + 4);
    if ($from > 2) { $out[] = '...'; }
    for ($i = $from; $i <= $to; $i++) { $out[] = $i; }
    if ($to < $totalPages - 1) { $out[] = '...'; }
    $out[] = $totalPages;
    return $out;
}

function lsc_log_month_label(string $ym): string
{
    if ($ym === 'legacy') {
        return 'Before rotation (app.log)';
    }
    $d = DateTimeImmutable::createFromFormat('Y-m-d', $ym . '-01');
    return $d ? $d->format('F Y') : $ym;
}
?>

<div class="banner">
    <div class="container">
        <h1>Admin Audit Logs</h1>
    </div>
</div>

<div class="container mt-5 mb-5">
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h4 class="card-title mb-3">Filters</h4>
            <form method="GET" action="admin-logs.php" class="row g-3">
                <div class="col-md-3">
                    <label for="search" class="form-label">Search Keyword</label>
                    <input type="text" class="form-control" id="search" name="search" placeholder="Search actor, action or details..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <label for="month" class="form-label">Month</label>
                    <select class="form-select" id="month" name="month">
                        <?php foreach ($months as $ym): ?>
                            <option value="<?= htmlspecialchars($ym) ?>" <?= $selected === $ym ? 'selected' : '' ?>><?= htmlspecialchars(lsc_log_month_label($ym)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">A date range below searches across months.</div>
                </div>
                <div class="col-md-2">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="text" class="form-control datepicker" id="start_date" name="start_date" placeholder="YYYY-MM-DD" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div class="col-md-2">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="text" class="form-control datepicker" id="end_date" name="end_date" placeholder="YYYY-MM-DD" value="<?= htmlspecialchars($end_date) ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="ri-search-line"></i> Filter</button>
                    <a href="admin-logs.php" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <p class="text-body-secondary">
        <?= number_format($result['total']) ?> entr<?= $result['total'] === 1 ? 'y' : 'ies' ?>
        <?php if ($start_date !== '' || $end_date !== ''): ?>
            in the selected date range
        <?php elseif ($selected !== ''): ?>
            in <?= htmlspecialchars(lsc_log_month_label($selected)) ?>
        <?php endif; ?>
    </p>

    <div class="table-responsive">
        <table class="table table-striped table-bordered text-center align-middle">
            <thead class="table-dark">
                <tr>
                    <th style="width: 15%">Timestamp</th>
                    <th style="width: 20%">User / Actor</th>
                    <th style="width: 20%">Action</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($entries) > 0): ?>
                    <?php foreach ($entries as $entry):
                        $action = htmlspecialchars($entry['action'] ?? '');
                        $badge_class = 'bg-secondary';
                        if (stripos($action, 'booking') !== false) {
                            $badge_class = 'bg-success';
                        } elseif (stripos($action, 'cancel') !== false || stripos($action, 'declin') !== false || stripos($action, 'expire') !== false) {
                            $badge_class = 'bg-danger';
                        } elseif (stripos($action, 'credit') !== false || stripos($action, 'refill') !== false) {
                            $badge_class = 'bg-primary';
                        } elseif (stripos($action, 'login') !== false) {
                            $badge_class = 'bg-info text-dark';
                        }
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($entry['timestamp'] ?? 'N/A') ?></td>
                            <td><strong><?= htmlspecialchars($entry['username'] ?? 'N/A') ?></strong></td>
                            <td><span class="badge <?= $badge_class ?> fs-6"><?= $action ?></span></td>
                            <td class="text-start"><?= htmlspecialchars($entry['description'] ?? 'N/A') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center py-4 text-muted">No log entries found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($result['pages'] > 1): ?>
        <div class="text-center mt-4">
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <?php foreach (lsc_log_pagination($result['pages'], $result['page']) as $pg): ?>
                        <?php if ($pg === '...'): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php elseif ($pg == $result['page']): ?>
                            <li class="page-item active"><span class="page-link"><?= $pg ?></span></li>
                        <?php else: ?>
                            <li class="page-item"><a class="page-link" href="<?= htmlspecialchars(lsc_log_query_string(['page' => $pg])) ?>"><?= $pg ?></a></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    $(document).ready(function() {
        flatpickr(".datepicker", { dateFormat: "Y-m-d", allowInput: true });
    });
</script>
</body>
</html>

<?php
require_once __DIR__ . '/includes/auth.php';
$lsc_me = lsc_require_admin('redirect');
require 'config.php';
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

if (!isset($_SESSION["logged_in"]) || $_SESSION['member_type'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$log_file = __DIR__ . '/logs/app.log';
$log_entries = [];

if (file_exists($log_file)) {
    $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if ($data) {
                $log_entries[] = $data;
            }
        }
    }
}

// Reverse chronological order
$log_entries = array_reverse($log_entries);

// Filters
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

$filtered_entries = [];
foreach ($log_entries as $entry) {
    // Search filter
    if ($search_query !== '') {
        $matches_search = (
            stripos($entry['action'] ?? '', $search_query) !== false ||
            stripos($entry['username'] ?? '', $search_query) !== false ||
            stripos($entry['description'] ?? '', $search_query) !== false
        );
        if (!$matches_search) {
            continue;
        }
    }

    // Date filter
    if (isset($entry['timestamp'])) {
        $entry_date = substr($entry['timestamp'], 0, 10); // Y-m-d
        if ($start_date !== '' && $entry_date < $start_date) {
            continue;
        }
        if ($end_date !== '' && $entry_date > $end_date) {
            continue;
        }
    }

    $filtered_entries[] = $entry;
}

// Pagination setup
$limit = 50;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$total_rows = count($filtered_entries);
$total_pages = ceil($total_rows / $limit);
$paginated_entries = array_slice($filtered_entries, $offset, $limit);

function paginate_logs($totalPages, $currentPage) {
    $maxPagesToShow = 12;
    $pagination = [];
    if ($totalPages <= 1) return $pagination;

    $pagination[] = 1;
    $start = max(2, $currentPage - 4);
    $end = min($totalPages - 1, $currentPage + 4);

    if ($start > 2) {
        $pagination[] = "...";
    }
    for ($i = $start; $i <= $end; $i++) {
        $pagination[] = $i;
    }
    if ($end < $totalPages - 1) {
        $pagination[] = "...";
    }
    $pagination[] = $totalPages;
    return $pagination;
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
                <div class="col-md-4">
                    <label for="search" class="form-label">Search Keyword</label>
                    <input type="text" class="form-control" id="search" name="search" placeholder="Search actor, action or details..." value="<?= htmlspecialchars($search_query) ?>">
                </div>
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="text" class="form-control datepicker" id="start_date" name="start_date" placeholder="YYYY-MM-DD" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div class="col-md-3">
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
                <?php if (count($paginated_entries) > 0): ?>
                    <?php foreach ($paginated_entries as $entry): 
                        // Determine badge class for action types
                        $action = htmlspecialchars($entry['action'] ?? '');
                        $badge_class = 'bg-secondary';
                        if (stripos($action, 'booking') !== false) {
                            $badge_class = 'bg-success';
                        } elseif (stripos($action, 'cancel') !== false) {
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

    <!-- pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="text-center mt-4">
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <?php
                    $pages = paginate_logs($total_pages, $page);
                    foreach ($pages as $pg) {
                        $queryString = '?page=' . $pg;
                        if ($search_query !== '') {
                            $queryString .= '&search=' . urlencode($search_query);
                        }
                        if ($start_date !== '') {
                            $queryString .= '&start_date=' . urlencode($start_date);
                        }
                        if ($end_date !== '') {
                            $queryString .= '&end_date=' . urlencode($end_date);
                        }

                        if ($pg === "...") {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        } elseif ($pg == $page) {
                            echo '<li class="page-item active"><span class="page-link">' . $pg . '</span></li>';
                        } else {
                            echo '<li class="page-item"><a class="page-link" href="' . $queryString . '">' . $pg . '</a></li>';
                        }
                    }
                    ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    $(document).ready(function() {
        flatpickr(".datepicker", {
            dateFormat: "Y-m-d",
            allowInput: true
        });
    });
</script>
</body>
</html>

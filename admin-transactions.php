<?php
// Database connection
require 'config.php';
?>

<!-- index.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions - Admin Le Smash Club</title>
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
<body class="page-id-5">
<?php
include "menu.php";
include 'includes/member-functions.php';

function paginate($totalPages, $currentPage) {
    $maxPagesToShow = 12;
    $pagination = [];

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

    if ($totalPages > 1) {
        $pagination[] = $totalPages;
    }

    if (count($pagination) > $maxPagesToShow) {
        $pagination = array_slice($pagination, 0, $maxPagesToShow - 1);
        $pagination[] = $totalPages;
    }

    return $pagination;
}

function get_booking_info_by_transaction_id($transaction_id, $pdo){
    $stmt_bk = $pdo->prepare("SELECT * FROM bookings WHERE transaction_id = ?");
    $stmt_bk->execute([$transaction_id]);
    $get_booking_data = $stmt_bk->fetchAll(PDO::FETCH_ASSOC);

    return $get_booking_data;
}

/* ---------------------------------------------------
   FILTER SETUP
--------------------------------------------------- */
$transaction_filter = isset($_GET['transaction_filter']) ? trim($_GET['transaction_filter']) : '';

$allowed_filters = [
    '',
    'booking',
    'cancelled',
    'Credit refill',
    'Membership renewal'
];

/* Safety guard */
if (!in_array($transaction_filter, $allowed_filters, true)) {
    $transaction_filter = '';
}

/* ---------------------------------------------------
   PAGINATION VARIABLES
--------------------------------------------------- */
$limit = 25;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

/* ---------------------------------------------------
   BUILD WHERE CLAUSE FOR FILTER
--------------------------------------------------- */
$whereClause = "";
$params = [];

if ($transaction_filter === 'booking') {
    $whereClause = " WHERE transaction_type IN ('booking (member)', 'booking (non member)')";
} elseif ($transaction_filter === 'cancelled') {
    $whereClause = " WHERE transaction_type IN ('cancelled', 'cancelled booking', 'cancelled-rain-half', 'cancelled-rain')";
} elseif ($transaction_filter === 'Credit refill') {
    $whereClause = " WHERE transaction_type IN ('Credit refill', 'Credit refill - Approved')";
} elseif ($transaction_filter === 'Membership renewal') {
    $whereClause = " WHERE transaction_type = 'Membership renewal'";
}

/* ---------------------------------------------------
   GET TOTAL NUMBER OF ROWS
--------------------------------------------------- */
$totalSql = "SELECT COUNT(*) FROM transactions" . $whereClause;
$totalStmt = $pdo->prepare($totalSql);
$totalStmt->execute();
$totalRows = $totalStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

/* ---------------------------------------------------
   GET TRANSACTIONS
--------------------------------------------------- */
$sql = "SELECT * FROM transactions" . $whereClause . " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="banner">
    <div class="container">
        <h1>All Transactions</h1>
    </div>
</div>

<?php
$approve_credit = $_GET["approve_credit"] ?? null;
$member_id = $_GET["member_id"] ?? null;
$credit_amount = $_GET["credit_amount"] ?? null;
$transaction_id = $_GET["transaction_id"] ?? null;

if ($approve_credit == 'true' && $member_id && $credit_amount && $transaction_id):
    try {
        $transaction_type = 'Credit refill - Approved';
        $stmt_ts = $pdo->prepare("UPDATE transactions SET transaction_type = ? WHERE transaction_id = ?");
        $result_ts = $stmt_ts->execute([$transaction_type, $transaction_id]);

        $stmt = $pdo->prepare("UPDATE members SET credit = credit + ? WHERE id = ?");
        $result = $stmt->execute([$credit_amount, $member_id]);

        $added_credit = "true";
        $add_credit_message = "Credit added to the member successfully";

    } catch (PDOException $e) {
        $added_credit = "false";
        $add_credit_message = "There is a problem adding credit. Please try again<br>" . $e->getMessage();
    }
endif;
?>

<?php if (($added_credit ?? '') == "true") { ?>
    <div class="container mt-5">
        <div class="alert alert-success" role="alert">
            <?= $add_credit_message ?>
        </div>
    </div>
    <script>
        window.setTimeout(function(){
            window.location.href = 'https://booking.lesmashclub.com/admin-transactions.php';
        }, 1000);
    </script>
<?php } elseif (($added_credit ?? '') == "false") { ?>
    <div class="container mt-5">
        <div class="alert alert-danger" role="alert">
            <?= $add_credit_message ?>
        </div>
    </div>

    <script>
        window.setTimeout(function(){
            window.location.href = 'https://booking.lesmashclub.com/admin-transactions.php';
        }, 1000);
    </script>
<?php } ?>

<div class="container mt-5 mb-3">
    <ul class="nav nav-pills flex-wrap gap-2">
        <li class="nav-item">
            <a class="nav-link <?= $transaction_filter === '' ? 'active' : '' ?>" href="admin-transactions.php">
                All transactions
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $transaction_filter === 'booking' ? 'active' : '' ?>" href="admin-transactions.php?transaction_filter=booking">
                Booking
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $transaction_filter === 'cancelled' ? 'active' : '' ?>" href="admin-transactions.php?transaction_filter=cancelled">
                Cancelled
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $transaction_filter === 'Credit refill' ? 'active' : '' ?>" href="admin-transactions.php?transaction_filter=Credit+refill">
                Credit refill
            </a>
        </li>
        <!-- <li class="nav-item">
            <a class="nav-link <?= $transaction_filter === 'Membership renewal' ? 'active' : '' ?>" href="admin-transactions.php?transaction_filter=Membership+renewal">
                Membership renewal
            </a>
        </li> -->
    </ul>
</div>

<div class="container mt-3 mb-5">
    <div class="table-responsive">
        <table class="table table-bordered table-responsive text-center">
            <thead class="table-dark">
                <tr>
                    <th class="transaction-type-td">Transaction Type</th>
                    <th>Transaction Title</th>
                    <th>Transaction Date & Time</th>
                    <th>Transaction Amount</th>
                    <th>Slip/Credit</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>

            <?php if (count($transactions) > 0): ?>
                <?php
                $count_transaction = 1;
                foreach ($transactions as $transaction):

                    // Get member info
                    $member_transaction_id = $transaction['member_id'];
                    $member_derived_fullname = '';
                    $member_derived_number = '';

                    if ($member_transaction_id) {
                        $member_derived_info = get_member_info_with_passed_id($member_transaction_id);
                        $member_derived_fullname = $member_derived_info['first_name'] . ' ' . $member_derived_info['last_name'];
                        $member_derived_number = $member_derived_info['member_number'];
                    }

                    // Process transaction preinfo
                    $transaction_type = $transaction['transaction_type'];
                    $transaction_title = $transaction['transaction_title'];
                    $transaction_type_class = '';
                    $transaction_badge = '';
                    $amount_type_symbol = '';
                    $slip_credit_text = '';

                    switch ($transaction_type) {
                        case 'cancelled booking':
                            $transaction_title = $transaction['transaction_title'];
                            $transaction_type_class = 'cancelled';
                            $transaction_badge = '<span class="badge text-bg-danger">Cancelled booking</span>';
                            $amount_type_symbol = '-';
                            $slip_credit_text = 'Credit';
                            break;

                        case 'cancelled':
                            $transaction_title = $transaction['transaction_title'];
                            $transaction_type_class = 'cancelled';
                            $transaction_badge = '<span class="badge text-bg-danger">Cancelled booking</span>';
                            $amount_type_symbol = '-';
                            $slip_credit_text = 'Credit';
                            break;

                        case 'cancelled-rain-half':
                            $transaction_title = $transaction['transaction_title'];
                            $transaction_type_class = 'cancelled';
                            $transaction_badge = '<span class="badge text-bg-danger">Cancelled booking<br>(Rain/Pollution)<br>- Half refunded</span>';
                            $amount_type_symbol = '-';
                            $slip_credit_text = 'Credit refunded';
                            break;

                        case 'cancelled-rain':
                            $transaction_title = $transaction['transaction_title'];
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
                            $slip_credit_text = '';
                            break;

                        case 'Credit refill':
                            $transaction_title = $transaction['transaction_title'];
                            $transaction_type_class = 'credit-refill';
                            $transaction_badge = '<span class="badge text-bg-primary">Credit refill</span>';
                            $amount_type_symbol = '+';
                            $slip_credit_text = 'Credit refilled';
                            break;

                        case 'Credit refill - Approved':
                            $transaction_title = $transaction['transaction_title'];
                            $transaction_type_class = 'credit-refill-approved';
                            $transaction_badge = '<span class="badge text-bg-primary">Credit refill (approved)</span>';
                            $amount_type_symbol = '+';
                            $slip_credit_text = 'Credit refilled';
                            break;

                        case 'Membership renewal':
                            $transaction_title = $transaction['transaction_title'] ?: 'Membership renewal';
                            $transaction_type_class = 'membership-renewal';
                            $transaction_badge = '<span class="badge text-bg-warning">Membership renewal</span>';
                            $amount_type_symbol = '+';
                            $slip_credit_text = 'Membership renewed';
                            break;

                        default:
                            $transaction_title = $transaction['transaction_title'];
                            $transaction_type_class = 'other';
                            $transaction_badge = '<span class="badge text-bg-secondary">' . htmlspecialchars($transaction_type) . '</span>';
                            $amount_type_symbol = '+';
                            $slip_credit_text = '';
                            break;
                    }

                    // Get associated transaction info
                    $get_transaction_data = [];
                    if ($transaction['assoc_transaction_id'] == '') {
                        $stmt_assoc_ts = $pdo->prepare("SELECT * FROM transactions WHERE assoc_transaction_id = ?");
                        $stmt_assoc_ts->execute([$transaction['transaction_id']]);
                        $get_transaction_data = $stmt_assoc_ts->fetchAll(PDO::FETCH_ASSOC);
                    }

                    // Don't show if record of booking child transaction
                    if ($transaction['assoc_transaction_id'] == ''):
                ?>

                <tr id="booking-<?= $transaction['transaction_id'] ?>" class="transaction_row transaction-type-<?= $transaction_type_class ?>">
                    <td class="transaction-type-td">
                        <?= $transaction_badge ?>
                        <?php if ($transaction_type == 'booking (member)' || $transaction_type == 'cancelled booking' || $transaction_type == 'cancelled' || $transaction_type == 'cancelled-rain-half' || $transaction_type == 'cancelled-rain') { ?>
                            <br>
                            <span class="fw-bold">Name: <?= $member_derived_fullname ?></span>
                            <br>
                            <span class="fw-bold">Member Number : <?= $member_derived_number ?></span>
                            <br>
                            <a href="admin-view-member.php?member_id=<?= $member_transaction_id ?>" class="btn btn-outline-primary">View This Member</a>
                        <?php } ?>
                    </td>

                    <td data-transaction-type="<?= $transaction['transaction_type'] ?>">
                        <?= $transaction_title ?>

                        <?php if ($member_transaction_id && ($transaction_type == 'Credit refill' || $transaction_type == 'Credit refill - Approved' || $transaction_type == 'Membership renewal')) { ?>
                            <div class="card">
                                <div class="card-body">
                                    <span>Member details:</span><br>
                                    <span class="fw-bold">Name: <?= $member_derived_fullname ?></span> -
                                    <span class="fw-bold">Member Number : <?= $member_derived_number ?></span>
                                    <br>
                                    <a href="admin-view-member.php?member_id=<?= $member_transaction_id ?>" class="btn btn-outline-primary">View This Member</a>
                                </div>
                            </div>
                        <?php } ?>

                        <?php
                        if ($transaction['assoc_transaction_id'] == '') {
                            foreach ($get_transaction_data as $key => $value) {
                                $this_derived_transaction_id = $value['transaction_id'];

                                echo '<div class="card mt-2"><div class="card-body p-1">';
                                echo '<b>Transaction #' . $this_derived_transaction_id . '</b>';
                                echo '<br>';
                                echo $value['transaction_title'];
                                echo '</div></div>';
                            }
                        }
                        ?>
                    </td>

                    <td>
                        <?php
                        $transaction_at = $transaction['created_at'];
                        $transaction_date_create = date_create($transaction_at);
                        $transaction_date_format = date_format($transaction_date_create, "d F, Y H:i:s");
                        echo $transaction_date_format;
                        ?>
                    </td>

                    <td class="fw-bold">
                        <?= $amount_type_symbol ?> <?= number_format($transaction['transaction_amount'], 0, ".", ",") ?> THB

                        <?php
                        if ($transaction['assoc_transaction_id'] == '') {
                            foreach ($get_transaction_data as $key => $value) {
                                $this_derived_transaction_id = $value['transaction_id'];

                                echo '<div class="card mt-2"><div class="card-body p-1">';
                                echo '<small>Transaction #' . $this_derived_transaction_id . '</small>';
                                echo '<br>';
                                echo $value['transaction_amount'] . ' THB';
                                echo '</div></div>';
                            }
                        }
                        ?>
                    </td>

                    <td>
                        <?php if (($transaction['slip_url'] == '' || $transaction['slip_url'] == NULL) && $transaction['payment_type'] == 'credit'): ?>
                            <?= $slip_credit_text ?><br>
                            <span class="badge text-bg-dark"><?= $amount_type_symbol ?> <?= number_format($transaction['transaction_amount'], 0, "", "") ?></span>

                        <?php elseif (($transaction['slip_url'] == '' || $transaction['slip_url'] == NULL) && $transaction['payment_type'] == 'cash'): ?>
                            Cash (Admin)

                        <?php else: ?>
                            <a class="btn btn-outline-primary btn-sm view-btn" href="uploads/<?= $transaction['slip_url'] ?>" target="_blank">View Slip</a>
                        <?php endif; ?>
                    </td>

                    <td>
                        <?php if ($transaction['transaction_type'] == 'Credit refill') { ?>
                            <button type="button" class="btn btn-secondary transaction-approve-button" data-transaction-id="<?= $transaction['transaction_id'] ?>" data-transaction-type="<?= $transaction['transaction_type'] ?>" data-member-id="<?= $transaction['member_id'] ?>" id="transactionApprove<?= $transaction['transaction_id'] ?>">
                                Approve Credit
                            </button>
                        <?php } else { ?>
                            <button type="button" class="btn btn-outline-primary transaction-view-button" data-transaction-id="<?= $transaction['transaction_id'] ?>" id="transactionView<?= $transaction['transaction_id'] ?>">
                                View Transaction
                            </button>
                        <?php } ?>

                        <button type="button" class="btn btn-outline-danger transaction-delete-button" data-transaction-id="<?= $transaction['transaction_id'] ?>" id="transactionDelete<?= $transaction['transaction_id'] ?>">
                            Delete
                        </button>
                    </td>
                </tr>

                <?php
                    if ($transaction['assoc_transaction_id'] == '') {
                        $count_transaction++;
                    }
                    endif;
                endforeach;
                ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">No transactions found.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- pagination -->
<div class="container mb-5 text-center">
    <nav aria-label="Page navigation example">
        <ul class="pagination">
            <?php
            $currentPage = $page;
            $pages = paginate($totalPages, $currentPage);

            foreach ($pages as $pg) {
                $class_nav = "pagination-numeric";

                $queryString = '?page=' . $pg;
                if ($transaction_filter !== '') {
                    $queryString .= '&transaction_filter=' . urlencode($transaction_filter);
                }

                if ($pg === "...") {
                    echo '<li class="page-item '.$class_nav.'">';
                    echo '...';
                    echo "</li>";
                } elseif ($pg == $currentPage) {
                    $class_nav = "pagination-numeric active";
                    echo '<li class="page-item '.$class_nav.'">';
                    echo '<a class="page-link" href="'.$queryString.'">';
                    echo $pg;
                    echo "</a></li>";
                } else {
                    echo '<li class="page-item '.$class_nav.'">';
                    echo '<a class="page-link" href="'.$queryString.'">';
                    echo $pg;
                    echo "</a></li>";
                }
            }
            ?>
        </ul>
    </nav>
</div>

<!-- Booking Modal -->
<div class="modal fade" id="transactionInfo" tabindex="-1" aria-labelledby="transactionInfoLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="transactionInfoLabel">Transaction Details</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="transactionContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal" id="closeTransactionModal" onclick="reload_page()">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    function reload_page(){
        location.reload();
    }

    $(document).ready(function () {

        $(".transaction-approve-button").click(function() {
            let transaction_id = $(this).data('transaction-id');
            let transaction_type = $(this).data('transaction-type');
            let member_id = $(this).data('member-id');

            $('#transactionInfo').modal('show');

            $.ajax({
                url: "check_member_credit.php",
                type: "POST",
                data: {
                    transaction_id: transaction_id,
                    transaction_type: transaction_type,
                    member_id: member_id
                },
                success: function (response) {
                    $('#transactionContent').html(response);
                }
            });
        });

        $('.transaction-view-button').click(function() {
            let transaction_id = $(this).data('transaction-id');
            $('#transactionInfo').modal('show');

            $.ajax({
                url: "popup_view_transaction.php",
                type: "POST",
                data: {
                    transaction_id: transaction_id
                },
                success: function (response) {
                    $('#transactionContent').html(response);
                }
            });
        });

        $('.transaction-delete-button').click(function() {
            let transaction_id = $(this).data('transaction-id');
            if (confirm("Are you sure you want to delete this transaction?") == true) {
                $('#transactionInfo').modal('show');

                $.ajax({
                    url: "admin-delete-transaction.php",
                    type: "POST",
                    data: {
                        transaction_id: transaction_id
                    },
                    success: function (response) {
                        $('#transactionContent').html(response);
                    }
                });
            } else {
                return false;
            }
        });

    });
</script>
</body>
</html>
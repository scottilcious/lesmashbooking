<?php
require_once __DIR__ . '/includes/auth.php';
$lsc_me = lsc_require_admin('redirect');
// Database connection
require 'config.php';
require_once 'includes/password.php';
require_once 'includes/credit.php';
$member_id = isset($_GET['member_id']) ? (int) $_GET['member_id'] : 0;
$transaction_filter = $_GET['transaction_filter'] ?? 'all';
$allowed_transaction_filters = ['all', 'bookings', 'cancellation'];
if (!in_array($transaction_filter, $allowed_transaction_filters, true)) {
    $transaction_filter = 'all';
}
$transaction_page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$transactions_per_page = 20;

?>

<!-- index.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View/Edit member - Admin Le Smash Club</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css"rel="stylesheet" />
    <link rel="stylesheet" href="style.css">
    <link href="bs-overwrite.css" rel="stylesheet">
</head>
<body>
    <?php include "menu.php"; 
    include_once 'includes/member-functions.php';

    // Fetch user bookings
    //$stmt = $pdo->prepare("SELECT * FROM bookings WHERE member_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
    $stmt->execute([$member_id]);
    //$stmt->execute([$userId, $limit, $offset]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    ?>
    <div class="banner">
        <div class="container">
            <h1>View/Edit Member</h1>
        </div>
    </div>

    <div class="container mt-5">
        <div class="row justify-content-end">
            <div class="col-12 col-md-auto">
            <button type="button" class="btn btn-danger" data-member-id="<?php echo $members[0]['id']; ?>" id="deleteMember">Delete this member</button>
            </div>
        </div>
    </div>

    <?php $checked_member_expiration = check_member_expired($members[0]['member_expiration']); ?>
    <div class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8">
                <div class="card">
                    <div class="card-body">

                        <?php if( $checked_member_expiration == "true"): ?>
                        <div class="memember-status">
                            <span class="badge rounded-pill text-bg-warning">Expired</span>
                        </div>
                        <?php endif; ?>

                        <input type="hidden" name="member_id" id="member_id" value="<?php echo $members[0]['id']; ?>">

                        <div class="mt-3">
                            <label for="member_number"><?= ($members[0]['member_type'] == 'non-member')? "Guest number" : "Member Number" ?></label>
                            <input class="form-control form-control-lg disabled" type="text" name="member_number" id="member_number" value="<?php echo $members[0]['member_number'];?>" />
                        </div>

                        <div class="row mt-3">
                            <div class="col-12 col-md-6">
                                <label for="first_name">First name</label>
                                <input class="form-control form-control-lg" type="text" name="first_name" id="first_name" value="<?php echo $members[0]['first_name'];?>" />
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="last_name">Last name</label>
                                <input class="form-control form-control-lg" type="text" name="last_name" id="last_name" value="<?php echo $members[0]['last_name'];?>" />
                            </div>
                        </div>

                        <div class="mt-3">
                            <label for="member_type">Member type</label>
                            <select class="form-select form-select-lg" name="member_type" id="member_type">
                                <option value="individual" <?php echo ($members[0]['member_type'] == 'individual') ? "selected" : ""; ?>>Individual</option>
                                <option value="couple" <?php echo ($members[0]['member_type'] == 'couple') ? "selected" : ""; ?>>Couple</option>
                                <option value="family" <?php echo ($members[0]['member_type'] == 'family') ? "selected" : ""; ?>>Family</option>
                                <option value="junior" <?php echo ($members[0]['member_type'] == 'junior') ? "selected" : ""; ?>>Junior</option>
                                <option value="1 adult 1 child" <?php echo ($members[0]['member_type'] == '1 adult 1 child') ? "selected" : ""; ?>>1 Adult 1 Child</option>
                                <option value="corporate" <?php echo ($members[0]['member_type'] == 'corporate') ? "selected" : ""; ?>>Corporate</option>
                                <option value="non-member" <?php echo ($members[0]['member_type'] == 'non-member') ? "selected" : ""; ?>>Non Member</option>
                            </select>
                        </div>

                        <div class="mt-3">
                            <label for="member_status">Member status</label>
                            <select class="form-select form-select-lg" name="member_status" id="member_status">
                                <option value="active" <?= ($members[0]['member_status'] == 'active' && $checked_member_expiration == "false") ? "selected" : ""; ?>>Active</option>
                                <option value="inactive" <?= ($members[0]['member_status'] == 'inactive' && $checked_member_expiration == "false") ? "selected" : ""; ?>>Inactive</option>
                                <option value="expired" <?= ($members[0]['member_status'] == 'expired' || $checked_member_expiration == "true") ? "selected" : ""; ?>>Expired</option>
                            </select>
                        </div>

                        <div class="row mt-3">
                            <div class="col-12 col-md-6">
                                <label for="member_phone">Member phone</label>
                                <input class="form-control form-control-lg" type="text" name="member_phone" id="member_phone" value="<?php echo $members[0]['member_phone'];?>" />
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="member_email">Member email</label>
                                <input class="form-control form-control-lg" type="text" name="member_email" id="member_email" value="<?php echo $members[0]['member_email'];?>" />
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-12 col-md-6">
                                <label for="member_since">Member since</label>
                                <input class="form-control form-control-lg" type="text" name="member_since" id="member_since" value="<?php echo $members[0]['member_since'];?>" />
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="member_length">Member length</label>
                                <select class="form-select form-select-lg" name="member_length" id="member_length">
                                    <option value="1 year" <?php echo ($members[0]['member_length'] == '1 year') ? "selected" : ""; ?>>1 Year</option>
                                    <option value="6 months" <?php echo ($members[0]['member_length'] == '6 months') ? "selected" : ""; ?>>6 months</option>
                                    <option value="1 month" <?php echo ($members[0]['member_length'] == '1 month') ? "selected" : ""; ?>>1 month</option>
                            </select>
                            </div>
                            <div class="col-12 col-md-6 mt-3">
                                <label for="member_expiration">Member expiration</label>
                                <input class="form-control form-control-lg" type="text" name="member_expiration" id="member_expiration" value="<?php echo $members[0]['member_expiration'];?>" />
                            </div>
                            <div class="col-12 col-md-6 mt-3">
                                <label for="last_renewed">Last renewed</label>
                                <input class="form-control form-control-lg" type="text" name="last_renewed" id="last_renewed" value="<?php echo $members[0]['last_renewed'];?>" />
                            </div>
                        </div>

                        <!-- <div class="row mt-3">
                            <div class="col-12 col-md-6">
                                <label for="new_price">New price</label>
                                <input class="form-control form-control-lg" type="number" name="new_price" id="new_price" value="<?php echo $members[0]['new_price'];?>" />
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="discount">Discount</label>
                                <input class="form-control form-control-lg" type="number" name="discount" id="discount" value="<?php echo $members[0]['discount'];?>" />
                            </div>
                        </div> -->

                        <div class="mt-3">
                            <label for="credit">Credit</label>
                            <div class="input-group input-group-lg">
                                <input class="form-control form-control-lg bg-body-secondary" type="text" id="credit" value="<?= number_format((float) $members[0]['credit'], 2) ?>" readonly>
                                <span class="input-group-text">THB</span>
                                <button type="button" class="btn btn-primary" id="openAdjustCredit" data-bs-toggle="modal" data-bs-target="#adjustCreditModal">
                                    <i class="ri-add-circle-line"></i> Adjust credit
                                </button>
                                <a class="btn btn-outline-primary" href="admin-member-credit.php?member_id=<?= urlencode((string) $member_id) ?>">
                                    <i class="ri-file-list-3-line"></i> Credit activity
                                </a>
                            </div>
                            <div class="form-text">
                                The balance can only be changed through <b>Adjust credit</b>, so every change is recorded with a reason and shows in the credit activity.
                            </div>
                        </div>

                        <hr>

                        <div class="mt-3">
                            <label for="member_password">Password</label>
                            <input class="form-control form-control-lg" type="text" name="member_password" id="member_password" value="<?php echo htmlspecialchars((string) lsc_password_decrypt($members[0]['member_password'])); ?>" />
                        </div>

                        <div class="mt-3">
                            <label for="member_note">Note</label>
                            <input class="form-control form-control-lg" type="text" name="member_note" id="member_note" value="<?php echo $members[0]['member_note']; ?>" />
                        </div>
                       
                        <div class="mt-3">
                            <div class="alert alert-light" role="alert">
                                <small>Created at</small>
                                <p><?php echo $members[0]['created_at']; ?></p>
                            </div>
                        </div>
                        

                        <div class="cta-wrapper border-top mt-5 pt-3 mb-5">
                            <button type="button" class="btn btn-primary" data-member-id="<?php echo $members[0]['id']; ?>" id="saveMember">Save changes</button>
                        </div>




                    </div>
                </div>
            </div>
            
        </div>
    </div>

    <!-- transaction -->
    <div class="container mb-5">
        <div class="row justify-content-center">
            <div class="col-12 col-md-10">
                <h4>Member Transaction History</h4>

                <?php
                    $base_url = 'admin-view-member.php?member_id=' . urlencode((string) $member_id);
                    $filter_links = [
                        'all' => 'All Transactions',
                        'bookings' => 'Bookings',
                        'cancellation' => 'Cancellation',
                    ];

                    $where_sql = "member_id = ? AND (assoc_transaction_id IS NULL OR assoc_transaction_id = '')";
                    $query_params = [$member_id];

                    if ($transaction_filter === 'bookings') {
                        $where_sql .= " AND transaction_type IN ('booking (member)', 'booking (non member)')";
                    } elseif ($transaction_filter === 'cancellation') {
                        $where_sql .= " AND transaction_type IN ('cancelled', 'cancelled booking', 'cancelled-rain-half', 'cancelled-rain')";
                    }

                    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE $where_sql");
                    $stmt_count->execute($query_params);
                    $total_transactions = (int) $stmt_count->fetchColumn();
                    $total_pages = max(1, (int) ceil($total_transactions / $transactions_per_page));
                    $transaction_page = min($transaction_page, $total_pages);
                    $transaction_offset = ($transaction_page - 1) * $transactions_per_page;

                    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE $where_sql ORDER BY created_at DESC, transaction_id DESC LIMIT $transactions_per_page OFFSET $transaction_offset");
                    $stmt->execute($query_params);
                    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

                ?>

                <div class="btn-group flex-wrap mb-3" role="group" aria-label="Transaction filters">
                    <?php foreach ($filter_links as $filter_key => $filter_label): ?>
                        <a class="btn btn-outline-primary <?= $transaction_filter === $filter_key ? 'active' : '' ?>" href="<?= $base_url ?>&transaction_filter=<?= urlencode($filter_key) ?>">
                            <?= $filter_label ?>
                        </a>
                    <?php endforeach; ?>
                </div>


                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Transaction title</th>
                                <th>Transaction type</th>
                                    <th>Transaction amount</th>
                                    <th>Slip/Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($transactions) > 0): ?>
                                <?php foreach ($transactions as $value): ?>
                                    <?php
                                        $transaction_type = $value['transaction_type'];
                                        $transaction_title = $value['transaction_title'] ?: $transaction_type;
                                        if ($transaction_type === 'booking (member)') {
                                            $transaction_title = $value['transaction_title'] ?: 'Member booking';
                                        } elseif ($transaction_type === 'booking (non member)') {
                                            $transaction_title = $value['transaction_title'] ?: 'Non member booking';
                                        }

                                        $transaction_badge = lsc_member_transaction_badge($transaction_type);
                                        $formatted_date = date_create($value['created_at']);
                                        $readable_date = date_format($formatted_date, "d F, Y H:i:s");

                                        $stmt_assoc_ts = $pdo->prepare("SELECT * FROM transactions WHERE assoc_transaction_id = ?");
                                        $stmt_assoc_ts->execute([$value['transaction_id']]);
                                        $get_transaction_data = $stmt_assoc_ts->fetchAll(PDO::FETCH_ASSOC);

                                                    ?>
                                    <tr>
                                        <td><?= $readable_date ?></td>
                                        <td>
                                            <?= htmlspecialchars($transaction_title) ?>
                                            <?php foreach ($get_transaction_data as $ts_value): ?>
                                                <div class="card mt-2"><div class="card-body p-1">
                                                    <b>Transaction #<?= $ts_value['transaction_id'] ?></b><br>
                                                    <?= htmlspecialchars($ts_value['transaction_title']) ?>
                                                </div></div>
                                            <?php endforeach; ?>
                                        </td>
                                        <td><?= $transaction_badge ?></td>

                                            <td>
                                                <span class="fw-bold fs-3"><?= number_format((int) $value['transaction_amount']) ?></span>
                                                <?php foreach ($get_transaction_data as $ts_value): ?>
                                                    <div class="card mt-2"><div class="card-body p-1">
                                                        <small>Transaction #<?= $ts_value['transaction_id'] ?></small><br>
                                                        <?= number_format((int) $ts_value['transaction_amount']) ?> THB
                                                    </div></div>
                                                <?php endforeach; ?>
                                            </td>
                                            <td>
                                                <?php if (($value['slip_url'] == '' || $value['slip_url'] == NULL) && $value['payment_type'] == 'credit'): ?>
                                                    Credit<br>
                                                    <span class="badge text-bg-dark"><?= number_format((int) $value['transaction_amount']) ?></span>
                                                <?php elseif (($value['slip_url'] == '' || $value['slip_url'] == NULL) && $value['payment_type'] == 'cash'): ?>
                                                    Cash (Admin)
                                                <?php elseif ($value['slip_url']): ?>
                                                    <a class="btn btn-outline-primary btn-sm view-btn" href="uploads/<?= htmlspecialchars($value['slip_url']) ?>" target="_blank">View Slip</a>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5">No transactions found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Transaction pages">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= $transaction_page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $base_url ?>&transaction_filter=<?= urlencode($transaction_filter) ?>&page=<?= max(1, $transaction_page - 1) ?>">Previous</a>
                            </li>
                            <?php for ($page_number = 1; $page_number <= $total_pages; $page_number++): ?>
                                <li class="page-item <?= $transaction_page === $page_number ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= $base_url ?>&transaction_filter=<?= urlencode($transaction_filter) ?>&page=<?= $page_number ?>"><?= $page_number ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $transaction_page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $base_url ?>&transaction_filter=<?= urlencode($transaction_filter) ?>&page=<?= min($total_pages, $transaction_page + 1) ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>

            </div>
        </div>
    </div>


   
    <!-- Loading -->
    <div class="loader-overlay pt-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-10 col-md-6">
                    <div class="inner-loader bg-white text-center">
                        <img src="images/ball_loading.gif" alt="" width="100">
                        <p>Please wait...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            flatpickr("#member_since", {
                dateFormat: "Y-m-d", // Format: YYYY-MM-DD
                defaultDate: "<?php echo $members[0]['member_since'];?>",
                altInput: true,     // Show formatted date
                altFormat: "j F, Y", // Format: Full month name, day, year
                });
            
            flatpickr("#member_expiration", {
                dateFormat: "Y-m-d", // Format: YYYY-MM-DD
                defaultDate: "<?php echo $members[0]['member_expiration'];?>",
                altInput: true,     // Show formatted date
                altFormat: "j F, Y", // Format: Full month name, day, year
                });
            
            flatpickr("#last_renewed", {
                dateFormat: "Y-m-d", // Format: YYYY-MM-DD
                defaultDate: "<?php echo $members[0]['last_renewed'];?>",
                altInput: true,     // Show formatted date
                altFormat: "j F, Y", // Format: Full month name, day, year
                });
        });
        $(document).ready(function () {

    
        $("#submitAdjustCredit").click(function () {
            const $btn = $(this);
            $btn.prop("disabled", true);
            $.ajax({
                url: "admin-adjust-credit.php",
                type: "POST",
                data: {
                    member_id: <?= (int) $member_id ?>,
                    direction: $("input[name=adjust_direction]:checked").val(),
                    amount: $("#adjust_amount").val(),
                    reason: $("#adjust_reason").val()
                },
                complete: function (xhr) {
                    $("#adjustCreditResult").html(xhr.responseText);
                    if (xhr.status === 200) {
                        $("#adjustCreditForm").hide();
                        $btn.hide();
                    } else {
                        $btn.prop("disabled", false);
                    }
                }
            });
        });

        $("#saveMember").click(function () {
                let memberId = $(this).data("member-id");
                let member_number = $('#member_number').val();
                let first_name = $('#first_name').val();
                let last_name = $('#last_name').val();
                let member_type = $('#member_type').val();
                let member_status = $('#member_status').val();
                let member_phone = $('#member_phone').val();
                let member_email = $('#member_email').val();
                let member_since = $('#member_since').val();
                let member_length = $('#member_length').val();
                let member_expiration = $('#member_expiration').val();
                let last_renewed = $('#last_renewed').val();
                /*let new_price = $('#new_price').val();
                let discount = $('#discount').val();*/
                let member_note = $('#member_note').val();
                let member_password = $('#member_password').val();
                


                if (confirm("Are you sure you want to save changes to this member?")) {

                    $(".loader-overlay").addClass("display");

                    $.ajax({
                        url: "admin-save-member.php",
                        type: "POST",
                        data: { 
                            member_id: memberId,
                            member_number: member_number,
                            first_name: first_name,
                            last_name: last_name,
                            member_type: member_type,
                            member_status: member_status,
                            member_phone: member_phone,
                            member_email: member_email,
                            member_since: member_since,
                            member_length: member_length,
                            member_expiration: member_expiration,
                            last_renewed: last_renewed,
                            member_note: member_note,
                            member_password: member_password
                        },
                        success: function (response) {
                            $(".loader-overlay .inner-loader").html(response);
                        },
                        error: function(xhr) {
                            $(".loader-overlay .inner-loader").html("Error: " + xhr.statusText);
                        }
                    });
                }
            });

            //Delete member 

            $("#deleteMember").click(function () {
                let memberId = $(this).data("member-id");


                if (confirm("Are you sure you want to delete this member?")) {

                    $(".loader-overlay").addClass("display");

                    $.ajax({
                        url: "admin-save-member.php",
                        type: "POST",
                        data: { 
                            member_id: memberId,
                            action:'delete'
                        },
                        success: function (response) {
                            $(".loader-overlay .inner-loader").html(response);
                        },
                        error: function(xhr) {
                            $(".loader-overlay .inner-loader").html("Error: " + xhr.statusText);
                        }
                    });
                }
            });


            

        });
    </script>

<!-- Adjust credit -->
<div class="modal fade" id="adjustCreditModal" tabindex="-1" aria-labelledby="adjustCreditLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title fs-5" id="adjustCreditLabel">Adjust credit</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="text-body-secondary">
          <?= htmlspecialchars(trim(($members[0]['first_name'] ?? '') . ' ' . ($members[0]['last_name'] ?? ''))) ?>
          &middot; current balance <b><?= number_format((float) $members[0]['credit'], 2) ?> THB</b>
        </p>
        <div id="adjustCreditResult"></div>
        <div id="adjustCreditForm">
          <div class="mb-3">
            <label class="form-label">Direction</label>
            <div class="btn-group w-100" role="group">
              <input type="radio" class="btn-check" name="adjust_direction" id="adjust_add" value="add" checked>
              <label class="btn btn-outline-success" for="adjust_add">Add credit</label>
              <input type="radio" class="btn-check" name="adjust_direction" id="adjust_deduct" value="deduct">
              <label class="btn btn-outline-danger" for="adjust_deduct">Deduct credit</label>
            </div>
          </div>
          <div class="mb-3">
            <label for="adjust_amount" class="form-label">Amount (THB)</label>
            <input type="number" min="1" step="1" class="form-control form-control-lg" id="adjust_amount" placeholder="0">
          </div>
          <div class="mb-3">
            <label for="adjust_reason" class="form-label">Reason <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="adjust_reason" maxlength="200" placeholder="e.g. Cash top up at reception, correction for double charge">
            <div class="form-text">Shown in the member's credit activity, so write something the next person will understand.</div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="submitAdjustCredit">Record adjustment</button>
      </div>
    </div>
  </div>
</div>

</body>
</html>

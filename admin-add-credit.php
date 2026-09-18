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
    <title>Add credit - Admin Le Smash Club</title>
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
<body  class="page-id-7">
    <?php include "menu.php";  ?>
    <div class="banner">
        <div class="container">
            <h1>Add credit for a member</h1>
        </div>
    </div>

    <?php 
    $added_credit = "";
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $member_id = $_POST["member_id"];
        $credit_amount = $_POST["credit_amount"];
    
        if( $member_id  && $credit_amount ):
    
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("UPDATE members SET credit= credit+? WHERE id = ?");
                $result = $stmt->execute([$credit_amount, $member_id]);

                $transaction_title = 'Admin credit add';
                $transaction_type = 'Admin credit add';
                $payment_type = 'credit';
                $slip_url = '';
                $non_member_id = '90002';
                $non_member_info = 'N/A';
                $transaction_note = 'Manual credit add from admin page';

                $stmt_ts = $pdo->prepare("INSERT INTO transactions (transaction_title, member_id, non_member_id, non_member_info, transaction_amount, transaction_type, payment_type, slip_url, transaction_note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_ts->execute([$transaction_title, $member_id, $non_member_id, $non_member_info, $credit_amount, $transaction_type, $payment_type, $slip_url, $transaction_note]);

                $pdo->commit();
    
                $added_credit = "true";
                $add_credit_message = "Credit added to this member successfully";
                lsc_log('Add Member Credit', "Added {$credit_amount} THB credit to Member ID: {$member_id}.");
    
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $added_credit = "false";
                $add_credit_message = "There is a problem adding credit. Please try again<br>" . $e->getMessage();
            }
    
        endif;
    
    
    if( $added_credit == "true"){ ?>
        <div class="container mt-5">
            <div class="alert alert-success" role="alert">
            <?= $add_credit_message ?>
            </div>
        </div>
    <?php }elseif( $added_credit == "false"){ ?>

        <div class="container mt-5">
            <div class="alert alert-danger" role="alert">
            <?= $add_credit_message ?>
            </div>
        </div>

    <?php }else{} 
    }
    ?>

    <div class="container mt-5 mb-5">

        <form action="admin-add-credit.php" method="post" id="formAddCredit">

        <div class="mb-3">
            <div class="alert alert-light search-member-panel display" id="searchMemberPanel">
                <label for="member_detail" class="fs-3">Search member</label>
                <div class="input-group mb-3">
                    <input type="text" class="form-control" name="member_detail" id="member_detail" placeholder="Enter keyword">
                    <select class="form-select" name="search_type" id="search_type">
                        <option value="member_name" selected>Search by name</option>
                        <option value="member_number">Search by member number</option>
                    </select>
                    <button class="btn btn-outline-secondary" type="button" id="searchMemberCredit" data-bs-toggle="modal" data-bs-target="#memberSearchResults">
                        Search member</button>
                </div>
            </div>

            <div class="search-member-result hide-panel bg-success-subtle p-2 rounded" id="memberSearchDetail">
                <h4>Selected member</h4>
                <small>Member name</small><br>
                <p id="memberName"></p>

                <small>Member number</small><br>
                <p id="memberNumber"></p>
                <input type="hidden" id="member_id" name="member_id">
            </div>
        </div>

        <!-- credit info -->
         <div class="credit-wrapper mt-3">
            <div class="card">
                <div class="card-body">
                    <div class="form-wrapper mt-3">
                        <label for="credit_amount">Credit amount</label>
                        <input class="form-control form-control-lg" name="credit_amount" id="credit_amount" type="number" placeholder="Enter credit amount">
                    </div>
                    <div class="form-wrapper mt-3">
                        <button type="button" class="btn btn-primary btn-lg" id="triggerAddCredit" onclick="add_credit_trigger()">Add Credit</button>
                    </div>
                </div>
            </div>
         </div>

        </form>

        <!-- Member modal -->
        <div class="modal fade" id="memberSearchResults" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="memberSearchResultsLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="memberSearchResultsLabel">Member search results</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="member-result-list" id="memberResultList">
                    <div class="loader-member-list text-center">
                        <img src="images/ball_loading.gif" alt="" width="100">
                        <p>Searching...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
            </div>
        </div>
        </div>
        
    </div>

    

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script src="app.js?v=<?= filemtime(__DIR__ . '/app.js') ?>"></script>
    <script>
        function add_credit_trigger(){
            let member_id = document.getElementById("member_id").value;
            let credit_amount = document.getElementById("credit_amount").value;

            if( member_id == '' ){
                alert("Please select a member");

                return false;
            }

            if( credit_amount == '' || parseInt(credit_amount) == 0 ){
                alert("Please enter credit amount");

                return false;
            }

            if( member_id != '' && credit_amount != '' ) {
                document.getElementById("formAddCredit").submit();
            }
        }
    </script>
</body>
</html>

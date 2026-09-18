<?php
// Database connection
require 'config.php';
include 'includes/member-functions.php';
$member_id = $_GET['member_id'];
?>

<!-- index.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add a member - Admin Le Smash Club</title>
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
<body  class="page-id-4">
    <?php include "menu.php"; 
    
    ?>
    <div class="banner">
        <div class="container">
            <h1>Add Member</h1>
        </div>
    </div>

    <div class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8">
                <div class="card">
                    <div class="card-body">
                    <?php 
                        try {
     
                            $stmt = $pdo->prepare("SELECT member_number FROM members");
                            $stmt->execute();
                            $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                        } catch (PDOException $e) {
                            echo  $e->getMessage();
                        }
                        
                        $member_number_queried = array();
                        foreach ($members as $key => $value) {
                            if( $value['member_number'] == '00001' || $value['member_number'] == '00002' || $value['member_number'] == '0003' || $value['member_number'] == '20250311461'  || $value['member_number'] == '20250302664'   || $value['member_number'] == '20250401806' ){
                            }else{
                                array_push($member_number_queried, $value['member_number']);
                            }
                        }

                        sort($member_number_queried);
                        $latest_member_number =  end($member_number_queried);
                        $latest_member_number = $latest_member_number+1;


                    ?>

                        <div class="mt-3">
                            <label for="member_number">Member number</label>
                            <input class="form-control form-control-lg disabled" type="text" name="member_number" id="member_number" value="<?php echo $latest_member_number;?>" />
                        </div>

                        <div class="row mt-3">
                            <div class="col-12 col-md-6">
                                <label for="first_name">First name</label>
                                <input class="form-control form-control-lg" type="text" name="first_name" id="first_name" value="" />
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="last_name">Last name</label>
                                <input class="form-control form-control-lg" type="text" name="last_name" id="last_name" value="" />
                            </div>
                        </div>

                        <div class="mt-3">
                            <label for="member_type">Member type</label>
                            <select class="form-select form-select-lg" name="member_type" id="member_type">
                                <option value="individual">Individual</option>
                                <option value="couple">Couple</option>
                                <option value="family">Family</option>
                                <option value="junior">Junior</option>
                                <option value="1 adult 1 child">1 Adult 1 Child</option>
                                <option value="non-member">Non Member</option>
                            </select>
                        </div>

                        <div class="mt-3">
                            <label for="member_status">Member status</label>
                            <select class="form-select form-select-lg" name="member_status" id="member_status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="expired">Expired</option>
                            </select>
                        </div>

                        <div class="row mt-3">
                            <div class="col-12 col-md-6">
                                <label for="member_phone">Member phone</label>
                                <input class="form-control form-control-lg" type="text" name="member_phone" id="member_phone" value="" />
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="member_email">Member email</label>
                                <input class="form-control form-control-lg" type="text" name="member_email" id="member_email" value="" />
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-12 col-md-6">
                                <label for="member_since">Member since</label>
                                <input class="form-control form-control-lg" type="text" name="member_since" id="member_since" value="" />
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="member_length">Member length</label>
                                <select class="form-select form-select-lg" name="member_length" id="member_length">
                                    <option value="1 year">1 Year</option>
                                    <option value="6 months">6 months</option>
                                    <option value="1 month">1 month</option>
                            </select>
                            </div>
                            <div class="col-12 col-md-6 mt-3">
                                <label for="member_expiration">Member expiration</label>
                                <input class="form-control form-control-lg" type="text" name="member_expiration" id="member_expiration" value="" />
                            </div>
                            <div class="col-12 col-md-6 mt-3">
                                <label for="last_renewed">Last renewed</label>
                                <input class="form-control form-control-lg" type="text" name="last_renewed" id="last_renewed" value="" />
                            </div>
                        </div>

                        <!-- <div class="row mt-3">
                            <div class="col-12 col-md-6">
                                <label for="new_price">New price</label>
                                <input class="form-control form-control-lg" type="number" name="new_price" id="new_price" placeholder="0" />
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="discount">Discount</label>
                                <input class="form-control form-control-lg" type="number" name="discount" id="discount" placeholder="0" />
                            </div>
                        </div> -->

                        <div class="mt-3">
                            <label for="credit">Credit</label>
                            <input class="form-control form-control-lg" type="number" name="credit" id="credit" placeholder="0" />
                        </div>

                        <hr>

                        <div class="mt-3">
                            <label for="member_password">Password</label>
                            <input class="form-control form-control-lg" type="text" name="member_password" id="member_password" value="" autocomplete="off" />
                        </div>

                        <div class="mt-3">
                            <label for="member_note">Note</label>
                            <input class="form-control form-control-lg" type="text" name="member_note" id="member_note" value="" />
                        </div>
                       
                        

                        <div class="cta-wrapper border-top mt-5 pt-3 mb-5">
                            <button type="button" class="btn btn-primary" id="addMember">Add member</button>
                        </div>




                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading -->
    <div class="loader-overlay pt-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-6 col-md-4">
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
                defaultDate: "today",
                altInput: true,     // Show formatted date
                altFormat: "j F, Y", // Format: Full month name, day, year
                });
            
            flatpickr("#member_expiration", {
                dateFormat: "Y-m-d", // Format: YYYY-MM-DD
                defaultDate: "today",
                altInput: true,     // Show formatted date
                altFormat: "j F, Y", // Format: Full month name, day, year
                });
            
            flatpickr("#last_renewed", {
                dateFormat: "Y-m-d", // Format: YYYY-MM-DD
                defaultDate: "today",
                altInput: true,     // Show formatted date
                altFormat: "j F, Y", // Format: Full month name, day, year
                });
        });
        $(document).ready(function () {

            $("#addMember").click(function () {
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
                let new_price = $('#new_price').val();
                let discount = $('#discount').val();
                let credit = $('#credit').val();
                let member_note = $('#member_note').val();
                let member_password = $('#member_password').val();
                
                $(".loader-overlay").addClass("display");

                $.ajax({
                    url: "admin-action-add-member.php",
                    type: "POST",
                    data: { 
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
                        credit: credit,
                        member_note: member_note,
                        member_password: member_password
                    },
                    success: function (response) {
                        $(".loader-overlay .inner-loader").html(response);
                    }
                    /*,
                    error: function(xhr) {
                        $(".loader-overlay .inner-loader").html("<br>Error: " + xhr.statusText);
                    }
                    */
                });

            });
        });
    </script>
</body>
</html>


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
    <title>Extend membership - Court Booking System</title>
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
    
    ?>
    <div class="banner">
        <div class="container">
            <h1>Extend membership</h1>
        </div>
    </div>

    <div class="page-container mt-5 mb-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12 col-md-6">
                    <div class="card">
                        <div class="card-body">

                            <form id="addCredit" enctype="multipart/form-data">
                                <input type="hidden" name="member_id" id="member_id" value="<?php echo $userId; ?>">
                                <label for="amount">Membership cost</label>
                                <!-- <input class="form-control form-control-lg" type="number" name="credit_amount" id="credit_amount" placeholder="0" /> -->
                                <select class="form-control form-control-lg" name="credit_amount" id="credit_amount">
                                    <option value="">Select membership type and length</option>
                                    <option value="11000" <?= ($memberType == 'Individual')? 'selected': '' ?>>Individual - 1 Year (11,000 THB)</option>
                                    <option value="2200">Individual - 1 Month (2,200 THB)</option>
                                    <option value="19500" <?= ($memberType == 'Couple')? 'selected': '' ?>>Couple - 1 Year (19,500 THB)</option>
                                    <option value="3800">Couple - 1 Month (3,800 THB)</option>
                                    <option value="25000" <?= ($memberType == 'Family')? 'selected': '' ?>>Family - 1 Year (25,000 THB)</option>
                                    <option value="5000">Family - 1 Month (5,000 THB)</option>
                                    <option value="8000" <?= ($memberType == 'Junior')? 'selected': '' ?>>Junior (3-17 years) - 1 Year (8,000 THB)</option>
                                    <option value="2000">Junior (3-17 years) - 1 Month (2,000 THB)</option>
                                    <option value="17500" <?= ($memberType == '1 Adult - 1 Child')? 'selected': '' ?>>1 Adult, 1 Child - 1 Year (17,500 THB)</option>
                                    <option value="3500">1 Adult, 1 Child - 1 Month (3,500 THB)</option>
                                </select>

                                <div class="payments mt-3">
                                    <h3>Payment</h3>
                                    <p>Bank account details:</p>
                                    <div class="row">
                                        <div class="col-12 col-md-4">
                                            <small class="text-body-tertiary">Bank account details:</small>
                                            <div class="bank-detail mb-2">
                                                <div class="list-bank-detail pt-1 pb-1">
                                                    Account name: <span class="fw-bold">MPM Asia</span>
                                                </div>
                                                <div class="list-bank-detail pt-1 pb-1">
                                                  Account number: <span class="fw-bold">057 286 4863</span>
                                                </div>
                                                <div class="list-bank-detail pt-1 pb-1">
                                                Bank: <span class="fw-bold">Kasikorn Bank</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-8">
                                            <small class="text-body-tertiary">Or scan QR code below to pay via Promptpay</small><br>
                                            <img src="images/lesmash_qr.png" alt="" class="w-100">
                                        </div>
                                        <div class="pt-2">
                                            <label for="slip_upload" class="form-label">Upload slip</label>
                                            <div class="alert alert-primary" role="alert">
                                                <i class="ri-questionnaire-fill"></i> If you pay with bank transfer or Promptpay QR, please upload slip.
                                            </div>  
                                            <input class="form-control form-control-lg" id="slip_upload" id="slip_upload" type="file">
                                        </div>
                                    </div>
                                </div>

                                <hr>
                                <div class="submit-wrapper text-end">
                                    <butto type="button" class="btn btn-primary btn-lg" id="creditRefillButton">Extend Membership</button>
                                </div>
                                
                            </form>

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

    <script>
        $(document).ready(function(){
            //Make booking 
            $("#creditRefillButton").click(function(){
                let credit_amount = $("#credit_amount").val();
                let member_id = $("#member_id").val();
                let slip_upload = $("#slip_upload")[0].files[0];

                if( credit_amount == '' && slip_upload == ''){
                    alert("Please specify credit amount and upload slip");

                    return false;
                }

                $(".loader-overlay").addClass("display");

                //Prepare form data
                let formData = new FormData();

                formData.append("credit_amount", credit_amount);
                formData.append("member_id", member_id);
                formData.append("payment_type", "extend_membership");
                if (slip_upload) {
                    formData.append("slip_upload", slip_upload);
                }

                // Make a booking 
                $.ajax({
                    url: "refill-credit.php",
                    type: "POST",
                    data: formData,
                    processData: false, // Prevent jQuery from processing data
                    contentType: false, // Prevent jQuery from setting content type
                    success: function(response) {
                        //alert(response);
                        $(".loader-overlay .inner-loader").html(response);
                    },
                    error: function(xhr) {
                        $(".loader-overlay .inner-loader").html("Error: " + xhr.statusText);
                    }
                });


            });
        });
    </script>

</body>
</html>
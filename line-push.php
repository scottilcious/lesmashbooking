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
    <title>Line PUSH - Court Booking System</title>
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
<body class="page-id-1">
    <div class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-4">

                <div class="card">
                    <div class="card-body">
                        <div class="form-wrapper mb-2">
                            <label for="userId">User ID</label>
                            <input type="text" class="form-control" name="userId" id="userId" value="<?= LINE_ADMIN_USER_ID ?>">
                        </div>
                        <div class="form-wrapper mb-2">
                            <label for="userMessage">Message</label>
                            <textarea name="userMessage" id="userMessage" class="form-control"></textarea>
                        </div>
                        

                        <button type="button" id="buttonSendLineAPI" class="btn btn-primary">Send Message</button>

                        <div class="message-result mt-2"></div>
                    </div>
                </div>


            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    $(document).ready(function () {

        $("#buttonSendLineAPI").click(function(){

            let userId = $("#userId").val();
            let userMessage = $("#userMessage").val();
            console.log(userId);
            console.log(userMessage);

            $.ajax({
                url: "line-api.php",
                type: "POST",
                data: {
                    userId: userId,
                    userMessage: userMessage
                },
                success: function (response) {
                    $(".message-result").html(response);
                }
            });

        });

        

    });
</script>
</body>
</html>
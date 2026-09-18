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
    <title>Memberhip Renewal  - Court Booking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css"rel="stylesheet" />
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include "menu.php"; ?>

    <div class="banner">
        <div class="container">
            <h1>Memberhip Renewal</h1>
        </div>
    </div>

    <div class="page-container mt-5 mb-5">
        <div class="container">

            <div class="container py-5">
                <div class="row justify-content-center">
                    <div class="col-md-8 text-center">
                        <h1 class="display-5 fw-bold mb-3">Membership Renewal</h1>
                        <p class="lead mb-5 text-secondary">Ready to stay in the game? Renew your membership through any of the following channels.</p>

                        <div class="row g-4">
                            <div class="col-12">
                                <div class="card border-0 shadow-sm bg-light p-4">
                                    <div class="card-body">
                                        <h3 class="h5 fw-bold">Visit the Club Counter</h3>
                                        <p class="mb-0">Speak with our staff directly at the front desk for immediate renewal and payment.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card h-100 border-0 shadow-sm p-4">
                                    <div class="card-body text-center">
                                        <h4 class="h5 fw-bold mb-3">WhatsApp</h4>
                                        <div class="bg-light border rounded mb-3 mx-auto d-flex align-items-center justify-content-center" style="width: 150px; height: 150px;">
                                            <img src="images/whatsapp.jpg" alt="" width="100%">
                                        </div>
                                        <p class="small text-muted mb-3">Scan to message our team</p>
                                        <a href="https://wa.me/YOUR_NUMBER" class="btn btn-outline-success w-100">Message on WhatsApp</a>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card h-100 border-0 shadow-sm p-4">
                                    <div class="card-body text-center">
                                        <h4 class="h5 fw-bold mb-3">LINE</h4>
                                        <div class="bg-light border rounded mb-3 mx-auto d-flex align-items-center justify-content-center" style="width: 150px; height: 150px;">
                                            <img src="images/line.png" alt="" width="100%">
                                        </div>
                                        <p class="small text-muted mb-3">Add us: <strong>lesmashclub</strong></p>
                                        <a href="https://line.me/ti/p/~lesmashclub" class="btn btn-success w-100" style="background-color: #06C755; border-color: #06C755;">Open LINE App</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p class="mt-5 text-muted small">
                            Having trouble? Please email us or call the club directly.
                        </p>
                    </div>
                </div>
            </div>
            
        </div>
    </div>

    

</body>
</html>
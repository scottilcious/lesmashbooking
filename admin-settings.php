<?php
require_once __DIR__ . '/includes/auth.php';
$lsc_me = lsc_require_admin('redirect');
require 'config.php';
include_once 'includes/booking-functions.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Admin Le Smash Club</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet" />
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
    <link href="bs-overwrite.css" rel="stylesheet">
</head>
<body class="page-id-9">
    <?php include "menu.php"; ?>

    <div class="banner">
        <div class="container">
            <h1>Settings</h1>
        </div>
    </div>

    <div class="container mt-5 mb-5">
        <?php if ($memberType !== 'admin'): ?>
            <div class="alert alert-danger" role="alert">
                This page is for admin only.
            </div>
        <?php else: ?>
            <?php
            $settings_message = '';
            $settings_message_type = '';
            $allow_midnight_booking = lsc_is_midnight_booking_enabled();

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $allow_midnight_booking = isset($_POST['allow_midnight_booking']) && $_POST['allow_midnight_booking'] === '1';
                $saved = lsc_set_midnight_booking_enabled($allow_midnight_booking);

                if ($saved) {
                    $settings_message = $allow_midnight_booking
                        ? 'Members and non-members can now book between 12:00 am and 6:00 am.'
                        : 'Members and non-members can no longer book between 12:00 am and 6:00 am.';
                    $settings_message_type = 'success';
                    lsc_log(
                        'Admin Settings',
                        $allow_midnight_booking
                            ? 'Enabled member/non-member midnight to 6:00 am booking window.'
                            : 'Disabled member/non-member midnight to 6:00 am booking window.'
                    );
                } else {
                    $settings_message = 'Unable to save this setting. Please try again.';
                    $settings_message_type = 'danger';
                }
            }
            ?>

            <?php if ($settings_message !== ''): ?>
                <div class="alert alert-<?= htmlspecialchars($settings_message_type) ?>" role="alert">
                    <?= htmlspecialchars($settings_message) ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <h2 class="fs-3">Booking Hours</h2>
                    <p class="text-body-secondary mb-4">
                        Control whether members and non-members can make bookings between midnight and 6:00 am.
                        Admin bookings remain available.
                    </p>

                    <form action="admin-settings.php" method="post">
                        <div class="form-check form-switch mb-3">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                role="switch"
                                id="allow_midnight_booking"
                                name="allow_midnight_booking"
                                value="1"
                                <?= $allow_midnight_booking ? 'checked' : '' ?>
                            >
                            <label class="form-check-label" for="allow_midnight_booking">
                                Allow member and non-member bookings from 12:00 am to 6:00 am
                            </label>
                        </div>

                        <div class="alert alert-light border">
                            Current status:
                            <strong><?= $allow_midnight_booking ? 'Enabled' : 'Disabled' ?></strong>
                        </div>

                        <button type="submit" class="btn btn-primary">Save settings</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>

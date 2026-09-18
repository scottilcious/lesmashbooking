<?php
require_once 'includes/functions.php';
// Start the session
session_start();

if (isset($_SESSION['user_id'])) {
    lsc_log('Logout', "User logged out.");
}

// Unset all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Redirect to the login page (or any desired page)
header("Location: login.php");
exit();
?>
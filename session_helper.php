<?php
// Start the session
session_start();

function saveLoginStatusToSession($userId) {
    // Ensure the session is active
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Save user data into session
    $_SESSION['is_logged_in'] = true;        // Login status
    $_SESSION['user_id'] = $userId;         // User ID
    /*
    $_SESSION['username'] = $username;      // Username
    $_SESSION['user_type'] = $userType;     // User type (default: user)
    */
    $_SESSION['login_time'] = time();       // Timestamp of login

}


?>

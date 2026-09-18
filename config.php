<?php
// All times in this application are club-local time.
date_default_timezone_set('Asia/Bangkok');

// Secrets and environment settings live in config.local.php (gitignored). See config.example.php.
$lsc_local_config = __DIR__ . '/config.local.php';
if (!file_exists($lsc_local_config)) {
    die("Missing config.local.php. Copy config.example.php to config.local.php and fill in the values.");
}
require_once $lsc_local_config;

// Notification switch: set to true on demo/testing environments to suppress LINE, SMS and email.
if (!defined('DISABLE_NOTIFICATIONS')) {
    define('DISABLE_NOTIFICATIONS', false);
}
if (!defined('DB_PORT')) {
    define('DB_PORT', 3306);
}
if (!defined('PASSWORD_ENCRYPTION_KEY') || strlen((string) PASSWORD_ENCRYPTION_KEY) !== 64) {
    die("PASSWORD_ENCRYPTION_KEY is missing or not 64 hex characters in config.local.php. See config.example.php.");
}

// Legacy variables still referenced by some files
$host = DB_HOSTNAME;
$dbname = DB_DATABASE;
$user = DB_USERNAME;
$pass = DB_PASSWORD;

if (!function_exists('lsc_create_pdo')) {
    function lsc_create_pdo(string $database): PDO
    {
        $dsn = "mysql:host=" . DB_HOSTNAME . ";port=" . DB_PORT . ";dbname=" . $database . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }
}

try {
    $pdo = lsc_create_pdo(DB_DATABASE);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>

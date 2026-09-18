<?php
// Notification Settings (Set to true on demo/testing environments to suppress Line, SMS, and Email notifications)
define('DISABLE_NOTIFICATIONS', false);

// Secrets live in config.local.php (gitignored). See config.example.php.
$lsc_local_config = __DIR__ . '/config.local.php';
if (!file_exists($lsc_local_config)) {
    die("Missing config.local.php. Copy config.example.php to config.local.php and fill in the values.");
}
require_once $lsc_local_config;

// Legacy variables still referenced by some files
$host = DB_HOSTNAME;
$dbname = DB_DATABASE;
$user = DB_USERNAME;
$pass = DB_PASSWORD;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>

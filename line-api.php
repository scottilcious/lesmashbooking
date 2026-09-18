<?php 
require_once __DIR__ . '/config.php';
$accessToken = LINE_BROADCAST_TOKEN;
$userId = LINE_TEST_USER_ID; // test user ID

$ch = curl_init("https://api.line.me/v2/bot/profile/$userId");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $accessToken
]);

$response = curl_exec($ch);
curl_close($ch);

echo $response;
?>
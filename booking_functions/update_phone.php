<?php
// update_phone.php
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php'; // define $pdo (PDO connection) or inline it below

// Basic validation
$userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$memberPhone = isset($_POST['member_phone']) ? trim($_POST['member_phone']) : '';

if ($userId <= 0 || $memberPhone === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    exit;
}

try {
    // Example PDO connect (or include db_config.php with $pdo defined)
    // $pdo = new PDO('mysql:host=localhost;dbname=your_db;charset=utf8mb4','user','pass', [
    //   PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    //   PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    // ]);

    $stmt = $pdo->prepare('UPDATE members SET member_phone = :phone WHERE id = :id LIMIT 1');
    $stmt->execute([':phone' => $memberPhone, ':id' => $userId]);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}

<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['subtotal' => 0, 'count' => 0]);
    exit;
}

include '../config.php';

$user_id = (int)$_SESSION['user_id'];

// Get cart totals
$stmt = $conn->prepare("SELECT SUM(quantity * price) as subtotal, SUM(quantity) as count FROM cart WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

$subtotal = $row['subtotal'] ?? 0;
$count = $row['count'] ?? 0;

echo json_encode([
    'subtotal' => (float)$subtotal,
    'count' => (int)$count
]);


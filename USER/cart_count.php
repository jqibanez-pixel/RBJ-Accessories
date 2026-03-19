<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
    exit();
}

include '../config.php';
$user_id = (int)$_SESSION['user_id'];
$count = 0;

$stmt = $conn->prepare("SELECT COALESCE(SUM(quantity), 0) as total FROM cart WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$count = (int)($row['total'] ?? 0);
$stmt->close();
$conn->close();

echo json_encode(['count' => $count]);

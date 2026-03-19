<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../config.php';
require_once __DIR__ . '/shapi_catalog_helper.php';
rbj_ensure_cart_choice_key_column($conn);

$user_id = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? '';

$posted_token = trim((string)($_POST['csrf_token'] ?? ''));
if ($posted_token === '') {
    $posted_token = trim((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($posted_token === '' || !hash_equals((string)$_SESSION['csrf_token'], $posted_token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid request token']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_quantity') {
    $cart_id = (int)($_POST['cart_id'] ?? 0);
    $template_id = (int)($_POST['template_id'] ?? 0);
    $quantity = max(1, min(99, (int)($_POST['quantity'] ?? 1)));
    $customizations = trim($_POST['customizations'] ?? 'Standard package');
    $choice_key = trim($_POST['choice_key'] ?? '');

    $item = null;
    if ($cart_id > 0) {
        $stmt = $conn->prepare("SELECT id, template_id, customizations, COALESCE(choice_key, '') AS choice_key FROM cart WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->bind_param("ii", $cart_id, $user_id);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } elseif ($template_id > 0) {
        $stmt = $conn->prepare("SELECT id, template_id, customizations, COALESCE(choice_key, '') AS choice_key FROM cart WHERE user_id = ? AND template_id = ? AND customizations = ? AND COALESCE(choice_key, '') = ? LIMIT 1");
        $stmt->bind_param("iiss", $user_id, $template_id, $customizations, $choice_key);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if (!$item) {
        echo json_encode(['success' => false, 'message' => 'Item not found in cart']);
        exit;
    }

    $stockInfo = rbj_resolve_item_stock(
        $conn,
        (int)$item['template_id'],
        (string)($item['customizations'] ?? 'Standard package'),
        (string)($item['choice_key'] ?? '')
    );
    $available = max(0, (int)($stockInfo['available'] ?? 0));
    if ($available <= 0) {
        $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $item['id'], $user_id);
        $stmt->execute();
        $stmt->close();

        echo json_encode([
            'success' => false,
            'message' => 'Selected design is out of stock and was removed from cart',
            'removed' => true
        ]);
        exit;
    }

    $finalQuantity = min($quantity, $available);
    $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
    $stmt->bind_param("ii", $finalQuantity, $item['id']);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'quantity' => $finalQuantity,
        'adjusted' => $finalQuantity !== $quantity,
        'available_stock' => $available,
        'message' => $finalQuantity !== $quantity
            ? 'Quantity adjusted to available stock (' . $available . ')'
            : 'Quantity updated'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'remove_item') {
    $cart_id = (int)($_POST['cart_id'] ?? 0);
    $template_id = (int)($_POST['template_id'] ?? 0);
    $customizations = trim($_POST['customizations'] ?? 'Standard package');
    $choice_key = trim($_POST['choice_key'] ?? '');

    if ($cart_id > 0) {
        $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $cart_id, $user_id);
        $stmt->execute();
        $stmt->close();
    } else {
        if ($template_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid product']);
            exit;
        }
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND template_id = ? AND customizations = ? AND COALESCE(choice_key, '') = ?");
        $stmt->bind_param("iiss", $user_id, $template_id, $customizations, $choice_key);
        $stmt->execute();
        $stmt->close();
    }
    
    echo json_encode(['success' => true]);
    exit;
}

// Default response
echo json_encode(['success' => false, 'message' => 'Invalid request']);


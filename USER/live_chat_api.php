<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? '') !== 'user')) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../config.php';

$user_id = (int)$_SESSION['user_id'];

$create_sql = "
CREATE TABLE IF NOT EXISTS live_chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    sender_role ENUM('user','admin') NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    delivered_at TIMESTAMP NULL DEFAULT NULL,
    seen_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_live_chat_user_id (user_id),
    INDEX idx_live_chat_user_read (user_id, sender_role, is_read),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
$conn->query($create_sql);
$conn->query("
CREATE TABLE IF NOT EXISTS live_chat_threads (
    user_id INT PRIMARY KEY,
    assigned_admin_id INT DEFAULT NULL,
    status ENUM('open','pending','resolved','closed') NOT NULL DEFAULT 'open',
    priority ENUM('normal','high','urgent') NOT NULL DEFAULT 'normal',
    branch_tag VARCHAR(80) DEFAULT '',
    escalated TINYINT(1) NOT NULL DEFAULT 0,
    escalation_reason VARCHAR(255) DEFAULT '',
    internal_note TEXT DEFAULT NULL,
    last_user_message_at TIMESTAMP NULL DEFAULT NULL,
    last_admin_reply_at TIMESTAMP NULL DEFAULT NULL,
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_live_chat_status (status),
    INDEX idx_live_chat_priority (priority),
    INDEX idx_live_chat_assigned_admin (assigned_admin_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_admin_id) REFERENCES users(id) ON DELETE SET NULL
)");
$conn->query("
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notifications_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

function has_column(mysqli $conn, string $table, string $column): bool
{
    $safe_table = str_replace('`', '``', $table);
    $safe_column = str_replace('`', '``', $column);
    $result = $conn->query("SHOW COLUMNS FROM `{$safe_table}` LIKE '{$safe_column}'");
    return $result && $result->num_rows > 0;
}

if (!has_column($conn, 'live_chat_messages', 'delivered_at')) {
    $conn->query("ALTER TABLE live_chat_messages ADD COLUMN delivered_at TIMESTAMP NULL DEFAULT NULL AFTER is_read");
}
if (!has_column($conn, 'live_chat_messages', 'seen_at')) {
    $conn->query("ALTER TABLE live_chat_messages ADD COLUMN seen_at TIMESTAMP NULL DEFAULT NULL AFTER delivered_at");
}
$conn->query("UPDATE live_chat_messages SET delivered_at = COALESCE(delivered_at, created_at), seen_at = COALESCE(seen_at, created_at) WHERE is_read = 1");

function json_out(array $payload): void
{
    echo json_encode($payload);
    exit();
}

function delivery_status(array $row): string
{
    if (!empty($row['seen_at']) || (int)($row['is_read'] ?? 0) === 1) {
        return 'seen';
    }
    if (!empty($row['delivered_at'])) {
        return 'delivered';
    }
    return 'sent';
}

function ensure_thread(mysqli $conn, int $user_id): void
{
    if ($user_id <= 0) {
        return;
    }
    $stmt = $conn->prepare("INSERT IGNORE INTO live_chat_threads (user_id) VALUES (?)");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'fetch';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array((string)$action, ['send', 'mark_read'], true)) {
    $posted_token = trim((string)($_POST['csrf_token'] ?? ''));
    if ($posted_token === '') {
        $posted_token = trim((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    }
    if ($posted_token === '' || !hash_equals((string)$_SESSION['csrf_token'], $posted_token)) {
        http_response_code(403);
        json_out(['ok' => false, 'error' => 'Invalid request token']);
    }
}

if ($action === 'send') {
    $message = trim((string)($_POST['message'] ?? ''));
    if ($message === '') {
        json_out(['ok' => false, 'error' => 'Message is empty']);
    }
    if (mb_strlen($message) > 1000) {
        json_out(['ok' => false, 'error' => 'Message is too long']);
    }

    ensure_thread($conn, $user_id);

    $stmt = $conn->prepare("INSERT INTO live_chat_messages (user_id, sender_role, message, is_read, delivered_at, seen_at) VALUES (?, 'user', ?, 0, NULL, NULL)");
    $stmt->bind_param("is", $user_id, $message);
    $ok = $stmt->execute();
    $new_id = $stmt->insert_id;
    $stmt->close();

    if ($ok) {
        $stmt = $conn->prepare("
            UPDATE live_chat_threads
            SET last_user_message_at = NOW(),
                status = 'open',
                resolved_at = NULL,
                updated_at = NOW()
            WHERE user_id = ?
        ");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
        }

        $username = (string)($_SESSION['username'] ?? 'Buyer');
        $name_stmt = $conn->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
        if ($name_stmt) {
            $name_stmt->bind_param("i", $user_id);
            $name_stmt->execute();
            $name_row = $name_stmt->get_result()->fetch_assoc();
            if ($name_row && !empty($name_row['username'])) {
                $username = (string)$name_row['username'];
            }
            $name_stmt->close();
        }

        $notif_msg = 'New buyer message from ' . $username . ' in Live Chat.';
        $notify_stmt = $conn->prepare("
            INSERT INTO notifications (user_id, message, is_read)
            SELECT id, ?, 0
            FROM users
            WHERE role IN ('admin', 'superadmin')
        ");
        if ($notify_stmt) {
            $notify_stmt->bind_param("s", $notif_msg);
            $notify_stmt->execute();
            $notify_stmt->close();
        }
    }

    json_out(['ok' => $ok, 'id' => (int)$new_id]);
}

if ($action === 'mark_read') {
    $stmt = $conn->prepare("UPDATE live_chat_messages SET is_read = 1, delivered_at = COALESCE(delivered_at, NOW()), seen_at = COALESCE(seen_at, NOW()) WHERE user_id = ? AND sender_role = 'admin' AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    json_out(['ok' => true, 'updated' => (int)$affected]);
}

if ($action === 'unread_count') {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM live_chat_messages WHERE user_id = ? AND sender_role = 'admin' AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    json_out(['ok' => true, 'count' => (int)($row['total'] ?? 0)]);
}

$since_id = (int)($_GET['since_id'] ?? 0);
$client_last_id = (int)($_GET['client_last_id'] ?? $since_id);

$stmt = $conn->prepare("UPDATE live_chat_messages SET delivered_at = COALESCE(delivered_at, NOW()) WHERE user_id = ? AND sender_role = 'admin' AND delivered_at IS NULL");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->close();

$messages = [];
$stmt = $conn->prepare("
    SELECT id, sender_role, message, is_read, delivered_at, seen_at, created_at
    FROM live_chat_messages
    WHERE user_id = ? AND id > ?
    ORDER BY id ASC
    LIMIT 200
");
$stmt->bind_param("ii", $user_id, $since_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $messages[] = [
        'id' => (int)$row['id'],
        'role' => $row['sender_role'],
        'text' => $row['message'],
        'is_read' => (int)$row['is_read'],
        'delivered_at' => $row['delivered_at'],
        'seen_at' => $row['seen_at'],
        'delivery_status' => delivery_status($row),
        'created_at' => $row['created_at'],
        'created_time' => date('g:i A', strtotime((string)$row['created_at'])),
    ];
}
$stmt->close();

$status_updates = [];
if ($client_last_id > 0) {
    $stmt = $conn->prepare("
        SELECT id, is_read, delivered_at, seen_at
        FROM live_chat_messages
        WHERE user_id = ? AND sender_role = 'user' AND id <= ?
        ORDER BY id DESC
        LIMIT 250
    ");
    $stmt->bind_param("ii", $user_id, $client_last_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $status_updates[] = [
            'id' => (int)$row['id'],
            'delivered_at' => $row['delivered_at'],
            'seen_at' => $row['seen_at'],
            'delivery_status' => delivery_status($row),
        ];
    }
    $stmt->close();
}

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM live_chat_messages WHERE user_id = ? AND sender_role = 'admin' AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

json_out([
    'ok' => true,
    'messages' => $messages,
    'status_updates' => $status_updates,
    'unread_count' => (int)($row['total'] ?? 0),
]);

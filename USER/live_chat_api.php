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

$conn->query("
CREATE TABLE IF NOT EXISTS live_chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    sender_role ENUM('user','admin') NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    delivered_at TIMESTAMP NULL DEFAULT NULL,
    seen_at TIMESTAMP NULL DEFAULT NULL,
    attachment_path VARCHAR(255) DEFAULT NULL,
    attachment_name VARCHAR(255) DEFAULT NULL,
    attachment_mime VARCHAR(120) DEFAULT NULL,
    attachment_size INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_live_chat_user_id (user_id),
    INDEX idx_live_chat_user_read (user_id, sender_role, is_read),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

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
    close_reason VARCHAR(255) DEFAULT '',
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

$conn->query("
CREATE TABLE IF NOT EXISTS live_chat_presence (
    user_id INT NOT NULL,
    actor_key VARCHAR(40) NOT NULL,
    actor_role ENUM('user','admin') NOT NULL,
    actor_user_id INT DEFAULT NULL,
    is_typing TINYINT(1) NOT NULL DEFAULT 0,
    is_viewing TINYINT(1) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, actor_key),
    INDEX idx_live_chat_presence_user (user_id, updated_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

function has_column(mysqli $conn, string $table, string $column): bool
{
    $safe_table = str_replace('`', '``', $table);
    $safe_column = str_replace('`', '``', $column);
    $result = $conn->query("SHOW COLUMNS FROM `{$safe_table}` LIKE '{$safe_column}'");
    return $result && $result->num_rows > 0;
}

foreach ([
    'delivered_at' => "ALTER TABLE live_chat_messages ADD COLUMN delivered_at TIMESTAMP NULL DEFAULT NULL AFTER is_read",
    'seen_at' => "ALTER TABLE live_chat_messages ADD COLUMN seen_at TIMESTAMP NULL DEFAULT NULL AFTER delivered_at",
    'attachment_path' => "ALTER TABLE live_chat_messages ADD COLUMN attachment_path VARCHAR(255) DEFAULT NULL AFTER seen_at",
    'attachment_name' => "ALTER TABLE live_chat_messages ADD COLUMN attachment_name VARCHAR(255) DEFAULT NULL AFTER attachment_path",
    'attachment_mime' => "ALTER TABLE live_chat_messages ADD COLUMN attachment_mime VARCHAR(120) DEFAULT NULL AFTER attachment_name",
    'attachment_size' => "ALTER TABLE live_chat_messages ADD COLUMN attachment_size INT DEFAULT NULL AFTER attachment_mime",
] as $column => $sql) {
    if (!has_column($conn, 'live_chat_messages', $column)) {
        $conn->query($sql);
    }
}

if (!has_column($conn, 'live_chat_threads', 'close_reason')) {
    $conn->query("ALTER TABLE live_chat_threads ADD COLUMN close_reason VARCHAR(255) DEFAULT '' AFTER internal_note");
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

function actor_key(string $role, int $actor_user_id): string
{
    return $role . ':' . $actor_user_id;
}

function map_attachment(array $row): ?array
{
    $path = trim((string)($row['attachment_path'] ?? ''));
    if ($path === '') {
        return null;
    }

    return [
        'url' => '/rbjsystem/' . ltrim(str_replace('\\', '/', $path), '/'),
        'name' => (string)($row['attachment_name'] ?? basename($path)),
        'mime' => (string)($row['attachment_mime'] ?? 'application/octet-stream'),
        'size' => (int)($row['attachment_size'] ?? 0),
    ];
}

function store_chat_attachment(array $file): ?array
{
    if (!isset($file['error']) || (int)$file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Attachment upload failed.');
    }
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Invalid attachment upload.');
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 5 * 1024 * 1024) {
        throw new RuntimeException('Attachment must be 5MB or smaller.');
    }

    $original_name = trim((string)($file['name'] ?? 'attachment'));
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $allowed = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
        'txt' => 'text/plain',
    ];
    if (!isset($allowed[$extension])) {
        throw new RuntimeException('Unsupported attachment type.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($file['tmp_name']);
    $mime_matches = [
        'jpg' => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'txt' => ['text/plain', 'application/octet-stream'],
    ];
    if (!in_array($mime, $mime_matches[$extension], true)) {
        throw new RuntimeException('Attachment format could not be verified.');
    }

    $upload_dir = dirname(__DIR__) . '/uploads/live_chat';
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0775, true) && !is_dir($upload_dir)) {
        throw new RuntimeException('Unable to create attachment directory.');
    }

    $safe_name = preg_replace('/[^A-Za-z0-9._-]/', '-', pathinfo($original_name, PATHINFO_FILENAME)) ?: 'chat-file';
    $file_name = sprintf('%s_%s.%s', $safe_name, bin2hex(random_bytes(8)), $extension);
    $target_path = $upload_dir . '/' . $file_name;

    if (!move_uploaded_file($file['tmp_name'], $target_path)) {
        throw new RuntimeException('Unable to save attachment.');
    }

    return [
        'path' => 'uploads/live_chat/' . $file_name,
        'name' => $original_name,
        'mime' => $mime,
        'size' => $size,
    ];
}

function fetch_presence_summary(mysqli $conn, int $user_id): array
{
    $summary = [
        'buyer_typing' => false,
        'buyer_viewing' => false,
        'admin_typing' => false,
        'admin_viewing' => false,
        'active_admins' => [],
    ];

    $stmt = $conn->prepare("
        SELECT p.actor_role, p.actor_user_id, p.is_typing, p.is_viewing, COALESCE(u.username, '') AS username
        FROM live_chat_presence p
        LEFT JOIN users u ON u.id = p.actor_user_id
        WHERE p.user_id = ?
          AND p.updated_at >= (NOW() - INTERVAL 20 SECOND)
          AND (p.is_typing = 1 OR p.is_viewing = 1)
    ");
    if (!$stmt) {
        return $summary;
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if ((string)$row['actor_role'] === 'user') {
            $summary['buyer_typing'] = $summary['buyer_typing'] || (int)$row['is_typing'] === 1;
            $summary['buyer_viewing'] = $summary['buyer_viewing'] || (int)$row['is_viewing'] === 1;
            continue;
        }
        $summary['admin_typing'] = $summary['admin_typing'] || (int)$row['is_typing'] === 1;
        $summary['admin_viewing'] = $summary['admin_viewing'] || (int)$row['is_viewing'] === 1;
        $summary['active_admins'][] = [
            'id' => (int)($row['actor_user_id'] ?? 0),
            'name' => (string)($row['username'] ?: 'Admin'),
            'is_typing' => (int)$row['is_typing'] === 1,
            'is_viewing' => (int)$row['is_viewing'] === 1,
        ];
    }
    $stmt->close();

    return $summary;
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'fetch';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array((string)$action, ['send', 'mark_read', 'update_presence'], true)) {
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
    if (mb_strlen($message) > 1000) {
        json_out(['ok' => false, 'error' => 'Message is too long']);
    }

    try {
        $attachment = store_chat_attachment($_FILES['attachment'] ?? []);
    } catch (RuntimeException $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()]);
    }

    if ($message === '' && $attachment === null) {
        json_out(['ok' => false, 'error' => 'Message or attachment is required']);
    }

    ensure_thread($conn, $user_id);

    $attachment_path = $attachment['path'] ?? null;
    $attachment_name = $attachment['name'] ?? null;
    $attachment_mime = $attachment['mime'] ?? null;
    $attachment_size = $attachment['size'] ?? null;

    $stmt = $conn->prepare("
        INSERT INTO live_chat_messages (
            user_id, sender_role, message, is_read, delivered_at, seen_at,
            attachment_path, attachment_name, attachment_mime, attachment_size
        ) VALUES (?, 'user', ?, 0, NULL, NULL, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        json_out(['ok' => false, 'error' => 'Unable to send message']);
    }
    $stmt->bind_param("issssi", $user_id, $message, $attachment_path, $attachment_name, $attachment_mime, $attachment_size);
    $ok = $stmt->execute();
    $new_id = (int)$stmt->insert_id;
    $stmt->close();

    if ($ok) {
        $stmt = $conn->prepare("
            UPDATE live_chat_threads
            SET last_user_message_at = NOW(),
                status = 'open',
                close_reason = '',
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

    json_out([
        'ok' => $ok,
        'id' => $new_id,
        'attachment' => $attachment ? map_attachment([
            'attachment_path' => $attachment_path,
            'attachment_name' => $attachment_name,
            'attachment_mime' => $attachment_mime,
            'attachment_size' => $attachment_size,
        ]) : null,
    ]);
}

if ($action === 'mark_read') {
    $stmt = $conn->prepare("
        UPDATE live_chat_messages
        SET is_read = 1,
            delivered_at = COALESCE(delivered_at, NOW()),
            seen_at = COALESCE(seen_at, NOW())
        WHERE user_id = ? AND sender_role = 'admin' AND is_read = 0
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $affected = (int)$stmt->affected_rows;
    $stmt->close();
    json_out(['ok' => true, 'updated' => $affected]);
}

if ($action === 'update_presence') {
    $is_typing = isset($_POST['is_typing']) && (int)$_POST['is_typing'] === 1 ? 1 : 0;
    $is_viewing = isset($_POST['is_viewing']) && (int)$_POST['is_viewing'] === 1 ? 1 : 0;
    $key = actor_key('user', $user_id);

    ensure_thread($conn, $user_id);

    $stmt = $conn->prepare("
        INSERT INTO live_chat_presence (user_id, actor_key, actor_role, actor_user_id, is_typing, is_viewing, updated_at)
        VALUES (?, ?, 'user', ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            actor_user_id = VALUES(actor_user_id),
            is_typing = VALUES(is_typing),
            is_viewing = VALUES(is_viewing),
            updated_at = NOW()
    ");
    if (!$stmt) {
        json_out(['ok' => false, 'error' => 'Unable to update presence']);
    }
    $stmt->bind_param("isiii", $user_id, $key, $user_id, $is_typing, $is_viewing);
    $ok = $stmt->execute();
    $stmt->close();

    json_out(['ok' => $ok, 'presence' => fetch_presence_summary($conn, $user_id)]);
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
    SELECT id, sender_role, message, is_read, delivered_at, seen_at, created_at,
           attachment_path, attachment_name, attachment_mime, attachment_size
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
        'role' => (string)$row['sender_role'],
        'text' => (string)$row['message'],
        'is_read' => (int)$row['is_read'],
        'delivered_at' => $row['delivered_at'],
        'seen_at' => $row['seen_at'],
        'delivery_status' => delivery_status($row),
        'created_at' => $row['created_at'],
        'created_time' => date('g:i A', strtotime((string)$row['created_at'])),
        'attachment' => map_attachment($row),
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

$thread = null;
$stmt = $conn->prepare("
    SELECT status, priority, branch_tag, escalated, escalation_reason, close_reason, assigned_admin_id, last_admin_reply_at, resolved_at
    FROM live_chat_threads
    WHERE user_id = ?
    LIMIT 1
");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $thread = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

json_out([
    'ok' => true,
    'messages' => $messages,
    'status_updates' => $status_updates,
    'unread_count' => (int)($row['total'] ?? 0),
    'presence' => fetch_presence_summary($conn, $user_id),
    'thread' => $thread,
]);

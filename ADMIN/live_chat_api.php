<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['admin', 'superadmin'], true)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../config.php';
require_once __DIR__ . '/admin_audit.php';

$admin_id = (int)$_SESSION['user_id'];
$admin_name = (string)($_SESSION['username'] ?? 'Admin');

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
CREATE TABLE IF NOT EXISTS live_chat_canned_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(120) NOT NULL,
    body TEXT NOT NULL,
    category VARCHAR(60) DEFAULT 'general',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("
CREATE TABLE IF NOT EXISTS live_chat_internal_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    admin_id INT NOT NULL,
    note TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_live_chat_note_user (user_id, created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
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

$conn->query("
INSERT IGNORE INTO live_chat_canned_replies (id, title, body, category, sort_order, is_active)
VALUES
 (1, 'Acknowledge', 'Thanks for your message. We are checking this now.', 'general', 10, 1),
 (2, 'Ask Order Number', 'Can you send your order number so we can verify quickly?', 'orders', 20, 1),
 (3, 'Payment Update', 'Payment received. We will update your order status shortly.', 'payments', 30, 1),
 (4, 'Follow Up', 'Noted. We will follow up within today.', 'general', 40, 1),
 (5, 'Customization Check', 'We are checking your customization request and we will update you shortly.', 'customization', 50, 1),
 (6, 'Shipping Follow Up', 'Your shipping concern has been noted. We are confirming the latest delivery status now.', 'shipping', 60, 1)
");

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

function normalize_status(string $value): string
{
    $value = strtolower(trim($value));
    return in_array($value, ['open', 'pending', 'resolved', 'closed'], true) ? $value : 'open';
}

function normalize_priority(string $value): string
{
    $value = strtolower(trim($value));
    return in_array($value, ['normal', 'high', 'urgent'], true) ? $value : 'normal';
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

function fetch_note_history(mysqli $conn, int $user_id): array
{
    $notes = [];
    $stmt = $conn->prepare("
        SELECT n.id, n.note, n.created_at, COALESCE(u.username, 'Admin') AS admin_name
        FROM live_chat_internal_notes n
        LEFT JOIN users u ON u.id = n.admin_id
        WHERE n.user_id = ?
        ORDER BY n.id DESC
        LIMIT 20
    ");
    if (!$stmt) {
        return $notes;
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $notes[] = [
            'id' => (int)$row['id'],
            'note' => (string)$row['note'],
            'admin_name' => (string)$row['admin_name'],
            'created_at' => (string)$row['created_at'],
        ];
    }
    $stmt->close();
    return $notes;
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

function fetch_customer_context(mysqli $conn, int $user_id): array
{
    $context = [
        'username' => '',
        'email' => '',
        'contact_number' => '',
        'chat_message_count' => 0,
        'last_activity_at' => null,
        'first_chat_at' => null,
        'order_count' => 0,
        'latest_order' => null,
    ];

    $stmt = $conn->prepare("SELECT username, email, contact_number FROM users WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            $context['username'] = (string)($row['username'] ?? '');
            $context['email'] = (string)($row['email'] ?? '');
            $context['contact_number'] = (string)($row['contact_number'] ?? '');
        }
        $stmt->close();
    }

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total_messages, MAX(created_at) AS last_activity_at, MIN(created_at) AS first_chat_at
        FROM live_chat_messages
        WHERE user_id = ?
    ");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            $context['chat_message_count'] = (int)($row['total_messages'] ?? 0);
            $context['last_activity_at'] = $row['last_activity_at'] ?? null;
            $context['first_chat_at'] = $row['first_chat_at'] ?? null;
        }
        $stmt->close();
    }

    $stmt = $conn->prepare("SELECT COUNT(*) AS total_orders FROM orders WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $context['order_count'] = (int)($row['total_orders'] ?? 0);
        $stmt->close();
    }

    $stmt = $conn->prepare("
        SELECT id, status, created_at, customization
        FROM orders
        WHERE user_id = ?
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            $context['latest_order'] = [
                'id' => (int)$row['id'],
                'status' => (string)($row['status'] ?? ''),
                'created_at' => (string)($row['created_at'] ?? ''),
                'customization' => (string)($row['customization'] ?? ''),
            ];
        }
        $stmt->close();
    }

    return $context;
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'threads';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array((string)$action, ['send', 'mark_read', 'update_thread_meta', 'update_presence'], true)) {
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
    $user_id = (int)($_POST['user_id'] ?? 0);
    $message = trim((string)($_POST['message'] ?? ''));
    if ($user_id <= 0) {
        json_out(['ok' => false, 'error' => 'Invalid user']);
    }
    if (mb_strlen($message) > 1000) {
        json_out(['ok' => false, 'error' => 'Message is too long']);
    }

    try {
        $attachment = store_chat_attachment($_FILES['attachment'] ?? []);
    } catch (RuntimeException $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()]);
    }

    if ($message === '' && $attachment === null) {
        json_out(['ok' => false, 'error' => 'Reply or attachment is required']);
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
        ) VALUES (?, 'admin', ?, 0, NULL, NULL, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        json_out(['ok' => false, 'error' => 'Unable to send reply']);
    }
    $stmt->bind_param("issssi", $user_id, $message, $attachment_path, $attachment_name, $attachment_mime, $attachment_size);
    $ok = $stmt->execute();
    $new_id = (int)$stmt->insert_id;
    $stmt->close();

    if ($ok) {
        $stmt = $conn->prepare("
            UPDATE live_chat_threads
            SET assigned_admin_id = COALESCE(assigned_admin_id, ?),
                last_admin_reply_at = NOW(),
                status = 'open',
                close_reason = '',
                resolved_at = NULL,
                updated_at = NOW()
            WHERE user_id = ?
        ");
        if ($stmt) {
            $stmt->bind_param("ii", $admin_id, $user_id);
            $stmt->execute();
            $stmt->close();
        }

        $notif_message = 'RBJ support replied to your live chat conversation.';
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, 0)");
        if ($stmt) {
            $stmt->bind_param("is", $user_id, $notif_message);
            $stmt->execute();
            $stmt->close();
        }

        rbj_admin_log(
            $conn,
            $admin_id,
            'send_live_chat_reply',
            'live_chat_thread',
            $user_id,
            [
                'message_id' => $new_id,
                'has_attachment' => $attachment !== null,
            ]
        );
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
    $user_id = (int)($_POST['user_id'] ?? 0);
    if ($user_id <= 0) {
        json_out(['ok' => false, 'error' => 'Invalid user']);
    }

    ensure_thread($conn, $user_id);

    $stmt = $conn->prepare("
        UPDATE live_chat_messages
        SET is_read = 1,
            delivered_at = COALESCE(delivered_at, NOW()),
            seen_at = COALESCE(seen_at, NOW())
        WHERE user_id = ? AND sender_role = 'user' AND is_read = 0
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $affected = (int)$stmt->affected_rows;
    $stmt->close();

    json_out(['ok' => true, 'updated' => $affected]);
}

if ($action === 'update_presence') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    if ($user_id <= 0) {
        json_out(['ok' => false, 'error' => 'Invalid user']);
    }

    ensure_thread($conn, $user_id);

    $is_typing = isset($_POST['is_typing']) && (int)$_POST['is_typing'] === 1 ? 1 : 0;
    $is_viewing = isset($_POST['is_viewing']) && (int)$_POST['is_viewing'] === 1 ? 1 : 0;
    $key = actor_key('admin', $admin_id);

    $stmt = $conn->prepare("
        INSERT INTO live_chat_presence (user_id, actor_key, actor_role, actor_user_id, is_typing, is_viewing, updated_at)
        VALUES (?, ?, 'admin', ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            actor_user_id = VALUES(actor_user_id),
            is_typing = VALUES(is_typing),
            is_viewing = VALUES(is_viewing),
            updated_at = NOW()
    ");
    if (!$stmt) {
        json_out(['ok' => false, 'error' => 'Unable to update presence']);
    }
    $stmt->bind_param("isiii", $user_id, $key, $admin_id, $is_typing, $is_viewing);
    $ok = $stmt->execute();
    $stmt->close();

    json_out(['ok' => $ok, 'presence' => fetch_presence_summary($conn, $user_id)]);
}

if ($action === 'update_thread_meta') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    if ($user_id <= 0) {
        json_out(['ok' => false, 'error' => 'Invalid user']);
    }

    ensure_thread($conn, $user_id);

    $assigned_admin_id = (int)($_POST['assigned_admin_id'] ?? 0);
    $status = normalize_status((string)($_POST['status'] ?? 'open'));
    $priority = normalize_priority((string)($_POST['priority'] ?? 'normal'));
    $branch_tag = trim((string)($_POST['branch_tag'] ?? ''));
    $internal_note = trim((string)($_POST['internal_note'] ?? ''));
    $escalated = isset($_POST['escalated']) && (int)$_POST['escalated'] === 1 ? 1 : 0;
    $escalation_reason = trim((string)($_POST['escalation_reason'] ?? ''));
    $close_reason = trim((string)($_POST['close_reason'] ?? ''));

    if (mb_strlen($branch_tag) > 80) {
        $branch_tag = mb_substr($branch_tag, 0, 80);
    }
    if (mb_strlen($escalation_reason) > 255) {
        $escalation_reason = mb_substr($escalation_reason, 0, 255);
    }
    if (mb_strlen($close_reason) > 255) {
        $close_reason = mb_substr($close_reason, 0, 255);
    }
    if (mb_strlen($internal_note) > 5000) {
        $internal_note = mb_substr($internal_note, 0, 5000);
    }

    if ($escalated === 1 && $escalation_reason === '') {
        json_out(['ok' => false, 'error' => 'Escalation reason is required']);
    }
    if (in_array($status, ['resolved', 'closed'], true) && $close_reason === '') {
        json_out(['ok' => false, 'error' => 'Close reason is required']);
    }
    if (!in_array($status, ['resolved', 'closed'], true)) {
        $close_reason = '';
    }

    $previous_note = '';
    $stmt = $conn->prepare("SELECT COALESCE(internal_note, '') AS internal_note FROM live_chat_threads WHERE user_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $previous_note = (string)($row['internal_note'] ?? '');
        $stmt->close();
    }

    $assigned_admin_param = $assigned_admin_id > 0 ? $assigned_admin_id : null;
    $resolved_at = in_array($status, ['resolved', 'closed'], true) ? date('Y-m-d H:i:s') : null;

    $stmt = $conn->prepare("
        UPDATE live_chat_threads
        SET assigned_admin_id = ?,
            status = ?,
            priority = ?,
            branch_tag = ?,
            escalated = ?,
            escalation_reason = ?,
            internal_note = ?,
            close_reason = ?,
            resolved_at = ?,
            updated_at = NOW()
        WHERE user_id = ?
    ");
    if (!$stmt) {
        json_out(['ok' => false, 'error' => 'Unable to update thread']);
    }
    $stmt->bind_param("isssissssi", $assigned_admin_param, $status, $priority, $branch_tag, $escalated, $escalation_reason, $internal_note, $close_reason, $resolved_at, $user_id);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok && $internal_note !== '' && $internal_note !== $previous_note) {
        $stmt = $conn->prepare("INSERT INTO live_chat_internal_notes (user_id, admin_id, note) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iis", $user_id, $admin_id, $internal_note);
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($ok) {
        rbj_admin_log(
            $conn,
            $admin_id,
            'update_live_chat_thread',
            'live_chat_thread',
            $user_id,
            [
                'assigned_admin_id' => $assigned_admin_id,
                'status' => $status,
                'priority' => $priority,
                'branch_tag' => $branch_tag,
                'escalated' => $escalated,
                'close_reason' => $close_reason,
            ]
        );
    }

    json_out(['ok' => $ok, 'notes' => fetch_note_history($conn, $user_id)]);
}

if ($action === 'fetch') {
    $user_id = (int)($_GET['user_id'] ?? 0);
    $since_id = (int)($_GET['since_id'] ?? 0);
    $client_last_id = (int)($_GET['client_last_id'] ?? $since_id);
    if ($user_id <= 0) {
        json_out(['ok' => false, 'error' => 'Invalid user']);
    }

    ensure_thread($conn, $user_id);

    $stmt = $conn->prepare("
        UPDATE live_chat_messages
        SET delivered_at = COALESCE(delivered_at, NOW())
        WHERE user_id = ? AND sender_role = 'user' AND delivered_at IS NULL
    ");
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
        LIMIT 300
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
            WHERE user_id = ? AND sender_role = 'admin' AND id <= ?
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

    $thread_meta = null;
    $stmt = $conn->prepare("
        SELECT t.user_id, t.assigned_admin_id, COALESCE(a.username, '') AS assigned_admin_name,
               t.status, t.priority, t.branch_tag, t.escalated, t.escalation_reason,
               t.internal_note, t.close_reason, t.last_user_message_at, t.last_admin_reply_at, t.resolved_at
        FROM live_chat_threads t
        LEFT JOIN users a ON a.id = t.assigned_admin_id
        WHERE t.user_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $thread_meta = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    json_out([
        'ok' => true,
        'messages' => $messages,
        'status_updates' => $status_updates,
        'thread' => $thread_meta,
        'notes' => fetch_note_history($conn, $user_id),
        'context' => fetch_customer_context($conn, $user_id),
        'presence' => fetch_presence_summary($conn, $user_id),
        'admin_id' => $admin_id,
        'admin_name' => $admin_name,
    ]);
}

if ($action === 'canned_replies') {
    $replies = [];
    $res = $conn->query("
        SELECT id, title, body, category
        FROM live_chat_canned_replies
        WHERE is_active = 1
        ORDER BY category ASC, sort_order ASC, id ASC
    ");
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $replies[] = $row;
        }
        $res->free();
    }
    json_out(['ok' => true, 'replies' => $replies]);
}

if ($action === 'thread_stats') {
    $stats = [
        'open' => 0,
        'pending' => 0,
        'resolved' => 0,
        'closed' => 0,
        'escalated' => 0,
        'unassigned' => 0,
    ];
    $res = $conn->query("
        SELECT
            SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_count,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved_count,
            SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) AS closed_count,
            SUM(CASE WHEN escalated = 1 THEN 1 ELSE 0 END) AS escalated_count,
            SUM(CASE WHEN assigned_admin_id IS NULL THEN 1 ELSE 0 END) AS unassigned_count
        FROM live_chat_threads
    ");
    if ($res instanceof mysqli_result) {
        $row = $res->fetch_assoc();
        $res->free();
        $stats['open'] = (int)($row['open_count'] ?? 0);
        $stats['pending'] = (int)($row['pending_count'] ?? 0);
        $stats['resolved'] = (int)($row['resolved_count'] ?? 0);
        $stats['closed'] = (int)($row['closed_count'] ?? 0);
        $stats['escalated'] = (int)($row['escalated_count'] ?? 0);
        $stats['unassigned'] = (int)($row['unassigned_count'] ?? 0);
    }
    json_out(['ok' => true, 'stats' => $stats]);
}

$thread_query = strtolower(trim((string)($_GET['q'] ?? '')));
$thread_status = normalize_status((string)($_GET['status'] ?? 'open'));
$thread_status_raw = strtolower(trim((string)($_GET['status'] ?? '')));
$thread_assigned = strtolower(trim((string)($_GET['assigned'] ?? '')));
$thread_unread_only = isset($_GET['unread_only']) && (int)$_GET['unread_only'] === 1;

if ($action === 'admins') {
    $admins = [];
    $res = $conn->query("
        SELECT id, username, role
        FROM users
        WHERE role IN ('admin', 'superadmin')
        ORDER BY role DESC, username ASC
    ");
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $admins[] = [
                'id' => (int)$row['id'],
                'username' => (string)$row['username'],
                'role' => (string)$row['role'],
            ];
        }
        $res->free();
    }
    json_out(['ok' => true, 'admins' => $admins, 'admin_id' => $admin_id]);
}

$threads = [];
$sql = "
SELECT
    u.id AS user_id,
    u.username,
    MAX(m.created_at) AS last_at,
    COALESCE(SUM(CASE WHEN m.sender_role = 'user' AND m.is_read = 0 THEN 1 ELSE 0 END), 0) AS unread_count,
    (
      SELECT lm.message
      FROM live_chat_messages lm
      WHERE lm.user_id = u.id
      ORDER BY lm.id DESC
      LIMIT 1
    ) AS last_message,
    (
      SELECT lm.attachment_name
      FROM live_chat_messages lm
      WHERE lm.user_id = u.id AND lm.attachment_path IS NOT NULL
      ORDER BY lm.id DESC
      LIMIT 1
    ) AS last_attachment_name,
    t.assigned_admin_id,
    COALESCE(a.username, '') AS assigned_admin_name,
    COALESCE(t.status, 'open') AS status,
    COALESCE(t.priority, 'normal') AS priority,
    COALESCE(t.branch_tag, '') AS branch_tag,
    COALESCE(t.escalated, 0) AS escalated,
    COALESCE(t.escalation_reason, '') AS escalation_reason,
    COALESCE(t.internal_note, '') AS internal_note,
    COALESCE(t.close_reason, '') AS close_reason,
    COALESCE(t.last_user_message_at, NULL) AS last_user_message_at,
    COALESCE(t.last_admin_reply_at, NULL) AS last_admin_reply_at,
    COALESCE(t.resolved_at, NULL) AS resolved_at
FROM users u
JOIN live_chat_messages m ON m.user_id = u.id
LEFT JOIN live_chat_threads t ON t.user_id = u.id
LEFT JOIN users a ON a.id = t.assigned_admin_id
GROUP BY
    u.id, u.username, t.assigned_admin_id, a.username, t.status, t.priority, t.branch_tag, t.escalated,
    t.escalation_reason, t.internal_note, t.close_reason, t.last_user_message_at, t.last_admin_reply_at, t.resolved_at
ORDER BY last_at DESC
";
$result = $conn->query($sql);
if ($result instanceof mysqli_result) {
    while ($row = $result->fetch_assoc()) {
        $last_message = (string)($row['last_message'] ?? '');
        if ($last_message === '' && !empty($row['last_attachment_name'])) {
            $last_message = '[Attachment] ' . (string)$row['last_attachment_name'];
        }
        $threads[] = [
            'user_id' => (int)$row['user_id'],
            'username' => (string)$row['username'],
            'last_at' => $row['last_at'],
            'unread_count' => (int)$row['unread_count'],
            'last_message' => $last_message,
            'assigned_admin_id' => $row['assigned_admin_id'] !== null ? (int)$row['assigned_admin_id'] : null,
            'assigned_admin_name' => (string)($row['assigned_admin_name'] ?? ''),
            'status' => (string)$row['status'],
            'priority' => (string)$row['priority'],
            'branch_tag' => (string)($row['branch_tag'] ?? ''),
            'escalated' => (int)($row['escalated'] ?? 0),
            'escalation_reason' => (string)($row['escalation_reason'] ?? ''),
            'internal_note' => (string)($row['internal_note'] ?? ''),
            'close_reason' => (string)($row['close_reason'] ?? ''),
            'last_user_message_at' => $row['last_user_message_at'],
            'last_admin_reply_at' => $row['last_admin_reply_at'],
            'resolved_at' => $row['resolved_at'],
        ];
    }
    $result->free();
}

if ($thread_query !== '') {
    $threads = array_values(array_filter($threads, static function (array $t) use ($thread_query): bool {
        $haystacks = [
            strtolower((string)($t['username'] ?? '')),
            strtolower((string)($t['last_message'] ?? '')),
            strtolower((string)($t['branch_tag'] ?? '')),
            strtolower((string)($t['close_reason'] ?? '')),
        ];
        foreach ($haystacks as $value) {
            if (strpos($value, $thread_query) !== false) {
                return true;
            }
        }
        return false;
    }));
}

if ($thread_unread_only) {
    $threads = array_values(array_filter($threads, static function (array $t): bool {
        return (int)($t['unread_count'] ?? 0) > 0;
    }));
}

if ($thread_status_raw !== '') {
    $threads = array_values(array_filter($threads, static function (array $t) use ($thread_status): bool {
        return (string)($t['status'] ?? 'open') === $thread_status;
    }));
}

if ($thread_assigned === 'mine') {
    $threads = array_values(array_filter($threads, static function (array $t) use ($admin_id): bool {
        return (int)($t['assigned_admin_id'] ?? 0) === $admin_id;
    }));
} elseif ($thread_assigned === 'unassigned') {
    $threads = array_values(array_filter($threads, static function (array $t): bool {
        return empty($t['assigned_admin_id']);
    }));
}

json_out(['ok' => true, 'threads' => $threads, 'admin_id' => $admin_id]);

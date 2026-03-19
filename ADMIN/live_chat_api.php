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

$conn->query("
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
INSERT IGNORE INTO live_chat_canned_replies (id, title, body, category, sort_order, is_active)
VALUES
 (1, 'Acknowledge', 'Thanks for your message. We are checking this now.', 'general', 10, 1),
 (2, 'Ask Order Number', 'Can you send your order number so we can verify quickly?', 'orders', 20, 1),
 (3, 'Payment Update', 'Payment received. We will update your order status shortly.', 'payments', 30, 1),
 (4, 'Follow Up', 'Noted. We will follow up within today.', 'general', 40, 1)
");

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

$action = $_POST['action'] ?? $_GET['action'] ?? 'threads';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array((string)$action, ['send', 'mark_read', 'update_thread_meta'], true)) {
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
    if ($user_id <= 0 || $message === '') {
        json_out(['ok' => false, 'error' => 'Invalid payload']);
    }
    if (mb_strlen($message) > 1000) {
        json_out(['ok' => false, 'error' => 'Message is too long']);
    }

    ensure_thread($conn, $user_id);

    $stmt = $conn->prepare("INSERT INTO live_chat_messages (user_id, sender_role, message, is_read, delivered_at, seen_at) VALUES (?, 'admin', ?, 0, NULL, NULL)");
    $stmt->bind_param("is", $user_id, $message);
    $ok = $stmt->execute();
    $new_id = (int)$stmt->insert_id;
    $stmt->close();

    if ($ok) {
        $stmt = $conn->prepare("
            UPDATE live_chat_threads
            SET last_admin_reply_at = NOW(),
                status = CASE WHEN status = 'closed' THEN 'open' ELSE status END,
                updated_at = NOW()
            WHERE user_id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        rbj_admin_log($conn, $admin_id, 'send_live_chat_reply', 'live_chat_thread', $user_id, ['message_id' => $new_id]);
    }

    json_out(['ok' => $ok, 'id' => $new_id]);
}

if ($action === 'mark_read') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    if ($user_id <= 0) {
        json_out(['ok' => false, 'error' => 'Invalid user']);
    }

    ensure_thread($conn, $user_id);

    $stmt = $conn->prepare("UPDATE live_chat_messages SET is_read = 1, delivered_at = COALESCE(delivered_at, NOW()), seen_at = COALESCE(seen_at, NOW()) WHERE user_id = ? AND sender_role = 'user' AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $affected = (int)$stmt->affected_rows;
    $stmt->close();

    json_out(['ok' => true, 'updated' => $affected]);
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

    if (mb_strlen($branch_tag) > 80) {
        $branch_tag = mb_substr($branch_tag, 0, 80);
    }
    if (mb_strlen($escalation_reason) > 255) {
        $escalation_reason = mb_substr($escalation_reason, 0, 255);
    }
    if (mb_strlen($internal_note) > 5000) {
        $internal_note = mb_substr($internal_note, 0, 5000);
    }

    $assigned_admin_param = $assigned_admin_id > 0 ? $assigned_admin_id : null;
    $resolved_at = ($status === 'resolved' || $status === 'closed') ? date('Y-m-d H:i:s') : null;

    $stmt = $conn->prepare("
        UPDATE live_chat_threads
        SET assigned_admin_id = ?,
            status = ?,
            priority = ?,
            branch_tag = ?,
            escalated = ?,
            escalation_reason = ?,
            internal_note = ?,
            resolved_at = ?,
            updated_at = NOW()
        WHERE user_id = ?
    ");
    if (!$stmt) {
        json_out(['ok' => false, 'error' => 'Unable to update thread']);
    }
    $stmt->bind_param("isssisssi", $assigned_admin_param, $status, $priority, $branch_tag, $escalated, $escalation_reason, $internal_note, $resolved_at, $user_id);
    $ok = $stmt->execute();
    $stmt->close();

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
                'escalated' => $escalated
            ]
        );
    }

    json_out(['ok' => $ok]);
}

if ($action === 'fetch') {
    $user_id = (int)($_GET['user_id'] ?? 0);
    $since_id = (int)($_GET['since_id'] ?? 0);
    $client_last_id = (int)($_GET['client_last_id'] ?? $since_id);
    if ($user_id <= 0) {
        json_out(['ok' => false, 'error' => 'Invalid user']);
    }

    ensure_thread($conn, $user_id);

    $stmt = $conn->prepare("UPDATE live_chat_messages SET delivered_at = COALESCE(delivered_at, NOW()) WHERE user_id = ? AND sender_role = 'user' AND delivered_at IS NULL");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    $messages = [];
    $stmt = $conn->prepare("
        SELECT id, sender_role, message, is_read, delivered_at, seen_at, created_at
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
               t.status, t.priority, t.branch_tag, t.escalated, t.escalation_reason, t.internal_note,
               t.last_user_message_at, t.last_admin_reply_at, t.resolved_at
        FROM live_chat_threads t
        LEFT JOIN users a ON a.id = t.assigned_admin_id
        WHERE t.user_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $thread_meta = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    json_out(['ok' => true, 'messages' => $messages, 'status_updates' => $status_updates, 'thread' => $thread_meta]);
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
        'unassigned' => 0
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
    t.assigned_admin_id,
    COALESCE(a.username, '') AS assigned_admin_name,
    COALESCE(t.status, 'open') AS status,
    COALESCE(t.priority, 'normal') AS priority,
    COALESCE(t.branch_tag, '') AS branch_tag,
    COALESCE(t.escalated, 0) AS escalated,
    COALESCE(t.escalation_reason, '') AS escalation_reason,
    COALESCE(t.internal_note, '') AS internal_note,
    COALESCE(t.last_user_message_at, NULL) AS last_user_message_at,
    COALESCE(t.last_admin_reply_at, NULL) AS last_admin_reply_at,
    COALESCE(t.resolved_at, NULL) AS resolved_at
FROM users u
JOIN live_chat_messages m ON m.user_id = u.id
LEFT JOIN live_chat_threads t ON t.user_id = u.id
LEFT JOIN users a ON a.id = t.assigned_admin_id
GROUP BY
    u.id, u.username, t.assigned_admin_id, a.username, t.status, t.priority, t.branch_tag, t.escalated,
    t.escalation_reason, t.internal_note, t.last_user_message_at, t.last_admin_reply_at, t.resolved_at
ORDER BY last_at DESC
";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $threads[] = [
            'user_id' => (int)$row['user_id'],
            'username' => $row['username'],
            'last_at' => $row['last_at'],
            'unread_count' => (int)$row['unread_count'],
            'last_message' => $row['last_message'] ?? '',
            'assigned_admin_id' => $row['assigned_admin_id'] !== null ? (int)$row['assigned_admin_id'] : null,
            'assigned_admin_name' => (string)($row['assigned_admin_name'] ?? ''),
            'status' => (string)$row['status'],
            'priority' => (string)$row['priority'],
            'branch_tag' => (string)($row['branch_tag'] ?? ''),
            'escalated' => (int)($row['escalated'] ?? 0),
            'escalation_reason' => (string)($row['escalation_reason'] ?? ''),
            'internal_note' => (string)($row['internal_note'] ?? ''),
            'last_user_message_at' => $row['last_user_message_at'],
            'last_admin_reply_at' => $row['last_admin_reply_at'],
            'resolved_at' => $row['resolved_at']
        ];
    }
}

if ($thread_query !== '') {
    $threads = array_values(array_filter($threads, static function (array $t) use ($thread_query): bool {
        $username = strtolower((string)($t['username'] ?? ''));
        $last_message = strtolower((string)($t['last_message'] ?? ''));
        $branch_tag = strtolower((string)($t['branch_tag'] ?? ''));
        return strpos($username, $thread_query) !== false
            || strpos($last_message, $thread_query) !== false
            || strpos($branch_tag, $thread_query) !== false;
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

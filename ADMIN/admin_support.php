<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['admin', 'superadmin'], true)) {
    header("Location: ../login.php");
    exit();
}
$current_admin_name = (string)($_SESSION['username'] ?? 'Admin');
$current_admin_role = (string)($_SESSION['role'] ?? 'admin');

include '../config.php';
require_once __DIR__ . '/admin_audit.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
$messages = [];

// Handle support message updates
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $posted_token = (string)($_POST['csrf_token'] ?? '');
    $allowed_statuses = ['open', 'in_progress', 'resolved', 'closed'];
    $message_id = (int)($_POST['message_id'] ?? 0);
    $status = (string)($_POST['status'] ?? '');
    $response = trim((string)($_POST['admin_response'] ?? ''));

    if (!hash_equals($csrf_token, $posted_token)) {
        $error = "Invalid request token. Please refresh and try again.";
    } elseif ($message_id <= 0) {
        $error = "Invalid support message id.";
    } elseif (!in_array($status, $allowed_statuses, true)) {
        $error = "Invalid status value.";
    } else {
        $stmt = $conn->prepare("UPDATE support_messages SET status = ?, admin_response = ?, updated_at = NOW() WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("ssi", $status, $response, $message_id);
            if ($stmt->execute()) {
                $success = "Support message updated successfully!";
                rbj_admin_log(
                    $conn,
                    (int)$_SESSION['user_id'],
                    'update_status',
                    'support_message',
                    $message_id,
                    ['status' => $status, 'has_response' => $response !== '']
                );
            } else {
                $error = "Failed to update support message.";
            }
            $stmt->close();
        } else {
            $error = "Unable to prepare support update query.";
        }
    }
}

// Fetch all support messages
$stmt = $conn->prepare("
    SELECT sm.*, u.username, u.email
    FROM support_messages sm
    JOIN users u ON sm.user_id = u.id
    ORDER BY
        CASE
            WHEN sm.status = 'open' THEN 1
            WHEN sm.status = 'in_progress' THEN 2
            WHEN sm.status = 'resolved' THEN 3
            WHEN sm.status = 'closed' THEN 4
        END,
        sm.created_at DESC
");
$stmt->execute();
$support_messages = $stmt->get_result();

// Get statistics
$stats = [
    'total' => 0,
    'open' => 0,
    'in_progress' => 0,
    'resolved' => 0,
    'closed' => 0
];

while ($msg = $support_messages->fetch_assoc()) {
    $stats['total']++;
    $stats[$msg['status']]++;
    $messages[] = $msg;
}

$support_messages->data_seek(0); // Reset pointer

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Support Management - RBJ Accessories</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link href="assets/admin-enhancements.css" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, sans-serif; }
body { background: #f4f6f8; }
.admin-container { display: flex; height: 100vh; }
/* Content */
.content { flex: 1; padding: 30px; overflow-y: auto; }
.content h1 { margin-bottom: 20px; }
/* Stats */
.stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
.stat-card { background: white; padding: 20px; border-radius: 10px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.stat-card h3 { margin: 0 0 10px 0; color: #2c3e50; }
.stat-card .number { font-size: 2em; font-weight: bold; color: #3498db; }
.stat-card.open .number { color: #e74c3c; }
.stat-card.in_progress .number { color: #f39c12; }
.stat-card.resolved .number { color: #27ae60; }
.stat-card.closed .number { color: #95a5a6; }
/* Messages Container */
.messages-container { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
.messages-header { background: #f8f9fa; padding: 15px 20px; border-bottom: 1px solid #dee2e6; }
.messages-header h2 { margin: 0; color: #2c3e50; }
.message-item { border-bottom: 1px solid #dee2e6; padding: 20px; }
.message-item:last-child { border-bottom: none; }
.message-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.message-user { font-weight: bold; color: #2c3e50; }
.message-meta { color: #6c757d; font-size: 0.9em; }
.message-status { padding: 4px 8px; border-radius: 12px; font-size: 0.8em; font-weight: bold; }
.message-status.open { background: #fee; color: #e74c3c; }
.message-status.in_progress { background: #fff3cd; color: #f39c12; }
.message-status.resolved { background: #d4edda; color: #27ae60; }
.message-status.closed { background: #f8f9fa; color: #6c757d; }
.message-content { margin: 10px 0; }
.message-priority { display: inline-block; padding: 2px 6px; border-radius: 8px; font-size: 0.8em; font-weight: bold; margin-left: 10px; }
.message-priority.low { background: #27ae60; color: white; }
.message-priority.medium { background: #f39c12; color: white; }
.message-priority.high { background: #e67e22; color: white; }
.message-priority.urgent { background: #e74c3c; color: white; }
.admin-response { background: #f8f9fa; padding: 15px; border-radius: 6px; margin-top: 15px; border-left: 4px solid #3498db; }
.admin-response h4 { margin: 0 0 10px 0; color: #2c3e50; }
.response-form { margin-top: 15px; }
.response-form textarea { width: 100%; padding: 10px; border: 1px solid #dee2e6; border-radius: 4px; resize: vertical; }
.response-form select { margin: 10px 0; padding: 8px; border: 1px solid #dee2e6; border-radius: 4px; }
.response-form button { background: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; }
.response-form button:hover { background: #2980b9; }
.success { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
@media (max-width: 900px) { .stats { grid-template-columns: repeat(2, 1fr); } }
</style>
</head>
<body>
<div class="admin-container">
  <aside class="sidebar">
    <div class="logo">
      <a class="admin-logo-link" href="/rbjsystem/ADMIN/dashboard_admin.php">
        <img src="/rbjsystem/rbjlogo.png" alt="RBJ Logo">
      </a>
    </div>
    <div class="admin-identity-card">
      <div class="admin-identity-label">Logged In As</div>
      <div class="admin-identity-name"><?php echo htmlspecialchars($current_admin_name, ENT_QUOTES, 'UTF-8'); ?></div>
      <div class="admin-identity-role"><?php echo htmlspecialchars($current_admin_role, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
    <nav>
      <a href="/rbjsystem/ADMIN/dashboard_admin.php">Dashboard</a>
      <a href="/rbjsystem/ADMIN/users_admin.php">Users</a>
      <a href="/rbjsystem/ADMIN/orders_admin.php">Orders</a>
      <a href="/rbjsystem/ADMIN/products_admin.php">Products</a>
      <a href="/rbjsystem/ADMIN/vouchers_admin.php">Vouchers</a>
      <a href="/rbjsystem/ADMIN/feedback_admin.php">Feedbacks</a>
      <a href="/rbjsystem/ADMIN/admin_support.php" class="active">Support</a>
      <a href="/rbjsystem/ADMIN/activity_logs_admin.php">Activity Logs</a>
      <a href="/rbjsystem/ADMIN/live_chat.php">Live Chat</a>
      <a href="/rbjsystem/logout.php">Logout</a>
    </nav>
  </aside>

  <main class="content">
    <div class="header">
        <h1>Support Management</h1>
    </div>

    <?php if (isset($success)): ?>
        <div class="success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="success" style="background:#f8d7da;color:#721c24;"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card total">
            <h3>Total Messages</h3>
            <div class="number"><?php echo $stats['total']; ?></div>
        </div>
        <div class="stat-card open">
            <h3>Open</h3>
            <div class="number"><?php echo $stats['open']; ?></div>
        </div>
        <div class="stat-card in_progress">
            <h3>In Progress</h3>
            <div class="number"><?php echo $stats['in_progress']; ?></div>
        </div>
        <div class="stat-card resolved">
            <h3>Resolved</h3>
            <div class="number"><?php echo $stats['resolved']; ?></div>
        </div>
    </div>

    <div class="messages-container">
        <div class="messages-header">
            <h2>Support Messages</h2>
        </div>

        <?php if ($support_messages->num_rows > 0): ?>
            <?php while ($message = $support_messages->fetch_assoc()): ?>
                <div class="message-item">
                    <div class="message-header">
                        <div>
                            <span class="message-user"><?php echo htmlspecialchars($message['username']); ?> (<?php echo htmlspecialchars($message['email']); ?>)</span>
                            <span class="message-meta"><?php echo date('M j, Y g:i A', strtotime($message['created_at'])); ?></span>
                        </div>
                        <div>
                            <span class="message-status <?php echo $message['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $message['status'])); ?></span>
                            <span class="message-priority <?php echo $message['priority']; ?>"><?php echo ucfirst($message['priority']); ?></span>
                        </div>
                    </div>

                    <div class="message-content">
                        <strong>Subject:</strong> <?php echo htmlspecialchars($message['subject']); ?><br>
                        <strong>Message:</strong> <?php echo htmlspecialchars($message['message']); ?>
                    </div>

                    <?php if (!empty($message['admin_response'])): ?>
                        <div class="admin-response">
                            <h4>Admin Response</h4>
                            <?php echo htmlspecialchars($message['admin_response']); ?>
                            <div style="font-size: 0.9em; color: #6c757d; margin-top: 5px;">
                                Responded: <?php echo date('M j, Y g:i A', strtotime($message['updated_at'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="admin_support.php" class="response-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                        <select name="status" required>
                            <option value="open" <?php echo $message['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                            <option value="in_progress" <?php echo $message['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="resolved" <?php echo $message['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                            <option value="closed" <?php echo $message['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                        </select>
                        <textarea name="admin_response" rows="3" placeholder="Enter your response..."><?php echo htmlspecialchars($message['admin_response']); ?></textarea>
                        <button type="submit" name="update_status">Update Status & Respond</button>
                    </form>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="padding: 40px; text-align: center; color: #6c757d;">
                <i class='bx bx-message-square-x' style="font-size: 48px; margin-bottom: 20px; display: block;"></i>
                No support messages yet.
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>






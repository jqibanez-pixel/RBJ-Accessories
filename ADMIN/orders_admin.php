<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['admin', 'superadmin'], true)) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
require_once __DIR__ . '/admin_audit.php';
if (!function_exists('rbj_ensure_payment_proofs_table')) {
    function rbj_ensure_payment_proofs_table(mysqli $conn): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS payment_proofs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                payment_id INT DEFAULT NULL,
                user_id INT NOT NULL,
                payment_channel VARCHAR(40) NOT NULL,
                reference_number VARCHAR(120) DEFAULT NULL,
                proof_path VARCHAR(255) DEFAULT NULL,
                status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
                admin_notes VARCHAR(255) DEFAULT NULL,
                verified_by INT DEFAULT NULL,
                verified_at TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_payment_proofs_order (order_id),
                INDEX idx_payment_proofs_user (user_id),
                INDEX idx_payment_proofs_status (status),
                CONSTRAINT fk_payment_proofs_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
                CONSTRAINT fk_payment_proofs_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL,
                CONSTRAINT fk_payment_proofs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ";
        $conn->query($sql);
    }
}
rbj_ensure_payment_proofs_table($conn);
$conn->query("UPDATE orders SET status = 'cancelled' WHERE status = 'return_refund'");
$conn->query("
    ALTER TABLE orders
    MODIFY COLUMN status ENUM(
        'pending',
        'to_pay',
        'to_ship',
        'to_receive',
        'in_progress',
        'completed',
        'cancelled'
    ) DEFAULT 'pending'
");
$conn->query("UPDATE orders SET status = 'pending' WHERE status = '' OR status IS NULL");
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
    )
");
$has_payment_proofs = false;
$proofs_table_check = $conn->query("SHOW TABLES LIKE 'payment_proofs'");
if ($proofs_table_check instanceof mysqli_result) {
    $has_payment_proofs = $proofs_table_check->num_rows > 0;
    $proofs_table_check->free();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$status_groups = [
    'to_pay' => ['pending', 'to_pay'],
    'to_ship' => ['in_progress', 'to_ship'],
    'to_receive' => ['to_receive'],
    'completed' => ['completed'],
    'cancelled' => ['cancelled']
];

function rbj_admin_resolve_status(string $status): array
{
    $map = [
        'pending' => ['label' => 'To Pay', 'class' => 'to_pay', 'group' => 'to_pay'],
        'to_pay' => ['label' => 'To Pay', 'class' => 'to_pay', 'group' => 'to_pay'],
        'in_progress' => ['label' => 'To Ship', 'class' => 'to_ship', 'group' => 'to_ship'],
        'to_ship' => ['label' => 'To Ship', 'class' => 'to_ship', 'group' => 'to_ship'],
        'to_receive' => ['label' => 'To Receive', 'class' => 'to_receive', 'group' => 'to_receive'],
        'completed' => ['label' => 'Complete', 'class' => 'completed', 'group' => 'completed'],
        'cancelled' => ['label' => 'Cancelled', 'class' => 'cancelled', 'group' => 'cancelled']
    ];

    if (isset($map[$status])) {
        return $map[$status];
    }

    return [
        'label' => ucfirst(str_replace('_', ' ', $status)),
        'class' => 'neutral',
        'group' => 'neutral'
    ];
}

// Handle order status updates and payment verification
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Invalid request token. Please refresh and try again.";
    } else {
        $admin_id = (int)$_SESSION['user_id'];
        $order_id = (int)$_POST['order_id'];
        $proof_id = (int)($_POST['proof_id'] ?? 0);
        $action = (string)$_POST['action'];

        $status_map = [
            'mark_to_pay' => 'to_pay',
            'mark_to_ship' => 'to_ship',
            'mark_to_receive' => 'to_receive',
            'mark_completed' => 'completed',
            'mark_cancelled' => 'cancelled'
        ];

        if ($action === 'send_reply') {
            $buyer_user_id = (int)($_POST['buyer_user_id'] ?? 0);
            $reply_message = trim((string)($_POST['reply_message'] ?? ''));
            if ($buyer_user_id <= 0 || $reply_message === '') {
                $error = "Reply message and buyer user are required.";
            } elseif (mb_strlen($reply_message) > 1000) {
                $error = "Reply message is too long.";
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO live_chat_messages (user_id, sender_role, message, is_read, delivered_at, seen_at)
                    VALUES (?, 'admin', ?, 0, NULL, NULL)
                ");
                if ($stmt) {
                    $stmt->bind_param("is", $buyer_user_id, $reply_message);
                    if ($stmt->execute()) {
                        $message = "Reply sent to buyer.";
                        rbj_admin_log(
                            $conn,
                            $admin_id,
                            'send_order_reply',
                            'order',
                            $order_id,
                            ['buyer_user_id' => $buyer_user_id]
                        );
                    } else {
                        $error = "Failed to send reply.";
                    }
                    $stmt->close();
                } else {
                    $error = "Unable to prepare reply message.";
                }
            }
        } elseif (($action === 'verify_payment' || $action === 'reject_payment') && !$has_payment_proofs) {
            $error = "Payment proof table is not available yet. Please run database update first.";
        } elseif ($action === 'verify_payment' && $proof_id > 0) {
            try {
                $conn->begin_transaction();

                $stmt = $conn->prepare("
                    SELECT pp.id, pp.order_id, pp.payment_id, pp.user_id, pp.status, pp.payment_channel, pp.reference_number
                    FROM payment_proofs pp
                    WHERE pp.id = ? AND pp.order_id = ?
                    LIMIT 1
                ");
                $stmt->bind_param("ii", $proof_id, $order_id);
                $stmt->execute();
                $proof_row = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$proof_row) {
                    throw new RuntimeException('Payment proof record not found.');
                }
                $proof_ref = strtoupper(trim((string)($proof_row['reference_number'] ?? '')));
                $proof_channel = strtoupper(trim((string)($proof_row['payment_channel'] ?? '')));
                if ($proof_ref === '') {
                    throw new RuntimeException('Reference number is required before verification.');
                }
                if (!preg_match('/^[A-Z0-9][A-Z0-9\-]{5,39}$/', $proof_ref)) {
                    throw new RuntimeException('Invalid reference number format.');
                }

                $stmt = $conn->prepare("
                    SELECT id
                    FROM payment_proofs
                    WHERE payment_channel = ? AND reference_number = ? AND id <> ?
                    LIMIT 1
                ");
                $stmt->bind_param("ssi", $proof_channel, $proof_ref, $proof_id);
                $stmt->execute();
                $duplicate_ref = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($duplicate_ref) {
                    throw new RuntimeException('Duplicate reference number detected.');
                }

                $stmt = $conn->prepare("
                    UPDATE payment_proofs
                    SET status = 'verified', verified_by = ?, verified_at = NOW(), admin_notes = NULL
                    WHERE id = ?
                ");
                $stmt->bind_param("ii", $admin_id, $proof_id);
                $stmt->execute();
                $stmt->close();

                $payment_id = (int)($proof_row['payment_id'] ?? 0);
                if ($payment_id > 0) {
                    $stmt = $conn->prepare("UPDATE payments SET status = 'completed' WHERE id = ?");
                    $stmt->bind_param("i", $payment_id);
                    $stmt->execute();
                    $stmt->close();
                }

                $stmt = $conn->prepare("UPDATE orders SET status = 'to_ship' WHERE id = ?");
                $stmt->bind_param("i", $order_id);
                $stmt->execute();
                $stmt->close();

                $buyer_id = (int)$proof_row['user_id'];
                $notify_message = 'Payment verified for order #' . $order_id . '. Your order is now queued for shipping.';
                $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, 0)");
                $stmt->bind_param("is", $buyer_id, $notify_message);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                $message = "Payment for order #" . $order_id . " verified. Order moved to to ship.";
                rbj_admin_log(
                    $conn,
                    $admin_id,
                    'verify_payment_proof',
                    'order',
                    $order_id,
                    ['proof_id' => $proof_id]
                );
            } catch (Throwable $e) {
                $conn->rollback();
                $error = "Failed to verify payment proof: " . $e->getMessage();
            }
        } elseif ($action === 'reject_payment' && $proof_id > 0) {
            $stmt = $conn->prepare("
                UPDATE payment_proofs
                SET status = 'rejected', verified_by = ?, verified_at = NOW(), admin_notes = 'Proof rejected by admin'
                WHERE id = ? AND order_id = ?
            ");
            $stmt->bind_param("iii", $admin_id, $proof_id, $order_id);
            if ($stmt->execute()) {
                $message = "Payment proof for order #" . $order_id . " marked as rejected.";
                rbj_admin_log(
                    $conn,
                    $admin_id,
                    'reject_payment_proof',
                    'order',
                    $order_id,
                    ['proof_id' => $proof_id]
                );
            } else {
                $error = "Failed to reject payment proof.";
            }
            $stmt->close();
        } elseif (array_key_exists($action, $status_map)) {
            $new_status = $status_map[$action];

            $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $new_status, $order_id);

            if ($stmt->execute()) {
                $message = "Order #" . $order_id . " status updated to " . str_replace('_', ' ', $new_status) . "!";
                rbj_admin_log(
                    $conn,
                    $admin_id,
                    'update_order_status',
                    'order',
                    $order_id,
                    ['status' => $new_status]
                );
            } else {
                $error = "Failed to update order status.";
            }

            $stmt->close();
        }
    }
} elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['bulk_update'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Invalid request token. Please refresh and try again.";
    } else {
        $order_ids = isset($_POST['selected_orders']) ? array_map('intval', $_POST['selected_orders']) : [];
        $order_ids = array_values(array_unique(array_filter($order_ids, function ($id) {
            return $id > 0;
        })));
        $new_status = $_POST['bulk_status'];

        if (!empty($order_ids) && in_array($new_status, ['pending', 'to_pay', 'to_ship', 'in_progress', 'to_receive', 'completed', 'cancelled'], true)) {
            $placeholders = str_repeat('?,', count($order_ids) - 1) . '?';
            $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id IN ($placeholders)");
            $stmt->bind_param('s' . str_repeat('i', count($order_ids)), $new_status, ...$order_ids);

            if ($stmt->execute()) {
                $message = count($order_ids) . " order(s) updated to " . str_replace('_', ' ', $new_status) . "!";
                rbj_admin_log(
                    $conn,
                    (int)$_SESSION['user_id'],
                    'bulk_update_order_status',
                    'order',
                    null,
                    ['status' => $new_status, 'count' => count($order_ids), 'ids' => $order_ids]
                );
            } else {
                $error = "Failed to update selected orders.";
            }
            $stmt->close();
        }
    }
}

// Get filter status from URL parameter
$status_filter = isset($_GET['status']) ? (string)$_GET['status'] : 'all';
$filter_statuses = [];
if ($status_filter !== 'all') {
    if (isset($status_groups[$status_filter])) {
        $filter_statuses = $status_groups[$status_filter];
    } elseif (in_array($status_filter, ['pending', 'to_pay', 'to_ship', 'in_progress', 'to_receive', 'completed', 'cancelled'], true)) {
        $filter_statuses = [$status_filter];
    }
}

$base_select = "
    SELECT
        o.id,
        o.customization,
        o.status,
        o.created_at,
        u.id AS buyer_user_id,
        u.username,
        u.email,
        p.id AS payment_id,
        p.payment_method,
        p.status AS payment_status,
        " . ($has_payment_proofs ? "pp.id AS proof_id,
        pp.payment_channel,
        pp.reference_number,
        pp.proof_path,
        pp.status AS proof_status" : "NULL AS proof_id,
        NULL AS payment_channel,
        NULL AS reference_number,
        NULL AS proof_path,
        NULL AS proof_status") . "
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN payments p ON p.order_id = o.id
    " . ($has_payment_proofs ? "LEFT JOIN payment_proofs pp ON pp.order_id = o.id" : "") . "
";

if (!empty($filter_statuses)) {
    $placeholders = implode(',', array_fill(0, count($filter_statuses), '?'));
    $stmt = $conn->prepare($base_select . " WHERE o.status IN ($placeholders) ORDER BY o.created_at DESC");
    if ($stmt) {
        $stmt->bind_param(str_repeat('s', count($filter_statuses)), ...$filter_statuses);
    }
} else {
    $stmt = $conn->prepare($base_select . " ORDER BY o.created_at DESC");
}

$orders = [];
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
} else {
    $error = "Unable to load orders list. SQL prepare failed.";
}
$conn->close();

$admin_tabs = [
    'all' => 'All',
    'to_pay' => 'To Pay',
    'to_ship' => 'To Ship',
    'to_receive' => 'To Receive',
    'completed' => 'Complete',
    'cancelled' => 'Cancelled'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Orders Management - RBJ Accessories</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link href="assets/admin-enhancements.css" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, sans-serif; }
body { background: #f4f6f8; }
.admin-container { display: flex; height: 100vh; }
.sidebar { width: 220px; background: #111; color: white; padding: 20px; }
.sidebar h2 { margin-bottom: 30px; }
.sidebar nav a { display: block; color: white; text-decoration: none; padding: 10px; margin-bottom: 5px; }
.sidebar nav a:hover, .sidebar nav a.active { background: #444; border-radius: 5px; }
.content { flex: 1; padding: 30px; overflow-y: auto; }
table { width: 100%; background: white; border-collapse: collapse; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
table th, table td { padding: 12px; border-bottom: 1px solid #e8ebef; text-align: left; vertical-align: top; }
table th { background: #f8f9fa; font-weight: 700; font-size: 12px; text-transform: uppercase; letter-spacing: .03em; color: #4a5563; }
.table-wrap { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); overflow: hidden; }
.table-wrap table { border-radius: 0; box-shadow: none; }
thead th { position: sticky; top: 0; z-index: 2; }
.row-main { background: #fff; }
.row-details { background: #fbfcfe; }
.row-details td { padding: 0; border-bottom: 1px solid #e8ebef; }
.detail-panel {
  display: none;
  padding: 16px;
  border-top: 1px solid #e8ebef;
  background: #fbfcfe;
}
.detail-panel.show { display: block; }
.detail-grid {
  display: grid;
  grid-template-columns: 1.2fr 1fr 1fr;
  gap: 16px;
}
.detail-card {
  background: #fff;
  border: 1px solid #e6ebf2;
  border-radius: 10px;
  padding: 12px;
}
.detail-card h4 {
  font-size: 12px;
  letter-spacing: .04em;
  text-transform: uppercase;
  color: #6b7785;
  margin-bottom: 8px;
}
.detail-card p, .detail-card small { font-size: 12px; color: #2f3b45; line-height: 1.4; }
.status {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 4px 8px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.2px;
  line-height: 1;
  min-height: 0;
}
.status.pending, .status.to_pay { background: rgba(243,156,18,0.2); color: #b9770e; }
.status.in_progress, .status.to_ship { background: rgba(52,152,219,0.2); color: #1f5f9a; }
.status.to_receive { background: rgba(142,68,173,0.18); color: #6c3483; }
.status.completed { background: rgba(39,174,96,0.2); color: #1e8449; }
.status.cancelled { background: rgba(231,76,60,0.18); color: #922b21; }
.status.neutral { background: rgba(108,117,125,0.15); color: #47525d; }
.status.verified { background: #2ecc71; color: #fff; }
.status.rejected { background: #e74c3c; color: #fff; }
.action-btn {
  padding: 6px 10px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  margin-right: 0;
  font-size: 12px;
  white-space: nowrap;
}
.btn-pay { background: #f39c12; color: #fff; }
.btn-ship { background: #3498db; color: #fff; }
.btn-receive { background: #8e44ad; color: #fff; }
.btn-completed { background: #27ae60; color: white; }
.btn-cancelled { background: #c0392b; color: #fff; }
.btn-verify { background: #2ecc71; color: #fff; }
.btn-reject { background: #c0392b; color: #fff; }
.btn-pay:hover, .btn-ship:hover, .btn-receive:hover, .btn-completed:hover, .btn-cancelled:hover { opacity: 0.85; }
.btn-verify:hover, .btn-reject:hover { opacity: 0.85; }
.customization-text { max-width: 300px; color: #28323c; line-height: 1.35; font-size: 13px; }
.payment-cell small { color: #666; display: block; margin-top: 3px; font-size: 12px; }
.proof-link { color: #2980b9; text-decoration: underline; font-size: 12px; }
.buyer-msg {
  max-width: 300px;
  font-size: 12px;
  color: #2f3b45;
  white-space: pre-wrap;
  background: #f7f9fc;
  border: 1px solid #e3e8ef;
  border-radius: 8px;
  padding: 8px 10px;
}
.reply-input {
  width: 100%;
  min-width: 200px;
  margin-top: 6px;
  margin-bottom: 6px;
  border: 1px solid #ccc;
  border-radius: 8px;
  padding: 7px 10px;
  font-size: 12px;
}
.order-id-chip {
  display: inline-flex;
  align-items: center;
  padding: 4px 8px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  background: #f1f4f8;
  color: #2f3b45;
}
.customer-name { font-weight: 700; color: #1f2933; }
.subtle { color: #6b7785; font-size: 12px; }
.action-stack { display:flex; flex-direction:column; gap:10px; min-width: 240px; }
.action-row { display:flex; flex-wrap: wrap; gap:8px; }
.action-stack form { display: block; }
.action-stack.compact { min-width: 0; gap: 8px; }
.action-row.center { align-items: center; }
.action-cluster {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}
.action-divider {
  height: 1px;
  background: #e3e8ef;
  border-radius: 999px;
  width: 100%;
}
.link-btn {
  display: inline-block;
  padding: 6px 10px;
  border-radius: 7px;
  border: 1px solid #cfd7e3;
  background: #fff;
  color: #1f5f9a;
  text-decoration: none;
  font-size: 12px;
  font-weight: 600;
}
.ghost-btn {
  border: 1px solid #cfd7e3;
  background: #fff;
  color: #1f2933;
  font-size: 12px;
  border-radius: 7px;
  padding: 6px 10px;
  cursor: pointer;
}
.ghost-btn.active { background: #eef3f8; }
.muted { color: #6b7785; font-size: 12px; }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
.alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
.alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.status-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin: 16px 0 20px;
}
.status-tab {
  display: inline-flex;
  align-items: center;
  padding: 8px 14px;
  border-radius: 999px;
  border: 1px solid #d9e2ec;
  background: #fff;
  color: #2f3b45;
  text-decoration: none;
  font-size: 13px;
  font-weight: 700;
}
.status-tab.active {
  background: linear-gradient(120deg, #a9352c, #c9473c);
  color: #fff;
  border-color: transparent;
  box-shadow: 0 10px 20px rgba(201,71,60,0.2);
}
</style>
</head>
<body>
<div class="admin-container">
  <aside class="sidebar">
    <div class="logo" style="text-align: center; margin-bottom: 20px;">
      <a href="/rbjsystem/ADMIN/dashboard_admin.php">
        <img src="/rbjsystem/rbjlogo.png" alt="RBJ Logo" style="height: 100px; width: auto; display: block; margin: 0 auto;">
      </a>
    </div>
    <nav>
      <a href="/rbjsystem/ADMIN/dashboard_admin.php">Dashboard</a>
      <a href="/rbjsystem/ADMIN/users_admin.php">Users</a>
      <a href="/rbjsystem/ADMIN/orders_admin.php" class="active">Orders</a>
      <a href="/rbjsystem/ADMIN/products_admin.php">Products</a>
      <a href="/rbjsystem/ADMIN/vouchers_admin.php">Vouchers</a>
      <a href="/rbjsystem/ADMIN/feedback_admin.php">Feedbacks</a>
      <a href="/rbjsystem/ADMIN/admin_support.php">Support</a>
      <a href="/rbjsystem/ADMIN/activity_logs_admin.php">Activity Logs</a>
      <a href="/rbjsystem/ADMIN/live_chat.php">Live Chat</a>
      <a href="/rbjsystem/logout.php">Logout</a>
    </nav>
  </aside>

  <main class="content">
    <h1>Orders Management</h1>

    <?php if (isset($message)): ?>
      <div class="alert success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
      <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="status-tabs">
      <?php foreach ($admin_tabs as $tab_key => $label): ?>
        <a class="status-tab <?php echo $status_filter === $tab_key ? 'active' : ''; ?>" href="orders_admin.php?status=<?php echo urlencode($tab_key); ?>">
          <?php echo htmlspecialchars($label); ?>
        </a>
      <?php endforeach; ?>
    </div>


    <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Order ID</th>
          <th>Customer</th>
          <th>Status</th>
          <th>Payment</th>
          <th>Order Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($orders)): ?>
          <tr>
            <td colspan="6" style="text-align: center; padding: 40px;">No orders found.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($orders as $order): ?>
            <?php $row_id = (int)$order['id']; ?>
            <tr>
              <td><span class="order-id-chip">#<?php echo $order['id']; ?></span></td>
              <td>
                <div class="customer-name"><?php echo htmlspecialchars($order['username']); ?></div>
                <div class="subtle"><?php echo htmlspecialchars($order['email']); ?></div>
              </td>
              <td>
                <?php $status_view = rbj_admin_resolve_status((string)$order['status']); ?>
                <span class="status <?php echo htmlspecialchars($status_view['class']); ?>">
                  <?php echo htmlspecialchars($status_view['label']); ?>
                </span>
              </td>
              <td class="payment-cell">
                <?php $method_label = (string)($order['payment_method'] ?? ''); ?>
                <?php
                  if ($method_label === '') {
                    $customization_text = (string)($order['customization'] ?? '');
                    if (stripos($customization_text, 'Payment: COD') !== false) {
                      $method_label = 'cash_on_delivery';
                    } elseif (stripos($customization_text, 'Payment: GCASH') !== false || stripos($customization_text, 'Payment: GOTIME') !== false) {
                      $method_label = 'bank_transfer';
                    }
                  }
                ?>
                <?php if ($method_label === 'bank_transfer'): ?>
                  <?php $method_label = !empty($order['payment_channel']) ? (string)$order['payment_channel'] : 'QR Payment'; ?>
                <?php elseif ($method_label === 'cash_on_delivery'): ?>
                  <?php $method_label = 'Cash on Delivery (COD)'; ?>
                <?php endif; ?>
                <div><strong><?php echo $method_label !== '' ? htmlspecialchars(strtoupper($method_label)) : 'N/A'; ?></strong></div>
                <small>Status: <?php echo htmlspecialchars((string)($order['payment_status'] ?? 'pending')); ?></small>
              </td>
              <td><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></td>
              <td>
                <div class="action-stack compact">
                  <div class="action-row center">
                    <button type="button" class="ghost-btn js-toggle-detail" data-target="detail-<?php echo $row_id; ?>">View Details</button>
                    <a class="link-btn" href="live_chat.php?user_id=<?php echo (int)$order['buyer_user_id']; ?>">Open Chat</a>
                  </div>
                </div>
              </td>
            </tr>
            <tr class="row-details">
              <td colspan="6">
                <div id="detail-<?php echo $row_id; ?>" class="detail-panel">
                  <div class="detail-grid">
                    <div class="detail-card">
                      <h4>Customization</h4>
                      <?php
                        $customization_preview = trim((string)$order['customization']);
                        if ($customization_preview === '') {
                          $customization_preview = 'No customization details.';
                        }
                      ?>
                      <p><?php echo nl2br(htmlspecialchars($customization_preview)); ?></p>
                    </div>
                    <div class="detail-card">
                      <h4>Voucher & Message</h4>
                      <?php
                        $voucher_value = 'none';
                        $shipping_voucher_value = 'none';
                        $discount_value = 0.0;
                        $shipping_discount_value = 0.0;
                        $customization_text = (string)($order['customization'] ?? '');
                        if (preg_match('/\|\s*Shop Voucher:\s*([^|]+)/i', $customization_text, $mVoucher)) {
                          $voucher_value = trim((string)$mVoucher[1]);
                        } elseif (preg_match('/\|\s*Voucher:\s*([^|]+)/i', $customization_text, $mVoucherLegacy)) {
                          $voucher_value = trim((string)$mVoucherLegacy[1]);
                        }
                        if (preg_match('/\|\s*Shipping Voucher:\s*([^|]+)/i', $customization_text, $mShipVoucher)) {
                          $shipping_voucher_value = trim((string)$mShipVoucher[1]);
                        }
                        if (preg_match('/\|\s*Discount:\s*PHP\s*([0-9]+(?:\.[0-9]+)?)/i', $customization_text, $mDiscount)) {
                          $discount_value = (float)$mDiscount[1];
                        }
                        if (preg_match('/\|\s*Shipping Discount:\s*PHP\s*([0-9]+(?:\.[0-9]+)?)/i', $customization_text, $mShipDiscount)) {
                          $shipping_discount_value = (float)$mShipDiscount[1];
                        }
                        $buyer_message = '';
                        if (preg_match('/\|\s*Message:\s*(.+)$/i', $customization_text, $mMsg)) {
                          $buyer_message = trim((string)$mMsg[1]);
                        }
                      ?>
                      <div class="muted">Shop Voucher: <strong><?php echo htmlspecialchars(strtoupper($voucher_value)); ?></strong></div>
                      <div class="muted">Shipping Voucher: <strong><?php echo htmlspecialchars(strtoupper($shipping_voucher_value)); ?></strong></div>
                      <?php if ($discount_value > 0): ?>
                        <div class="muted">Item Discount: PHP <?php echo number_format($discount_value, 0); ?></div>
                      <?php endif; ?>
                      <?php if ($shipping_discount_value > 0): ?>
                        <div class="muted">Shipping Discount: PHP <?php echo number_format($shipping_discount_value, 0); ?></div>
                      <?php endif; ?>
                      <div style="margin-top:8px;">
                        <?php if ($buyer_message !== '' && strtolower($buyer_message) !== 'none'): ?>
                          <div class="buyer-msg"><?php echo htmlspecialchars($buyer_message); ?></div>
                        <?php else: ?>
                          <small class="muted">No buyer message.</small>
                        <?php endif; ?>
                      </div>
                    </div>
                    <div class="detail-card">
                      <h4>Proof & Actions</h4>
                      <?php $proof_status = (string)($order['proof_status'] ?? ''); ?>
                      <?php $is_cod_order = stripos((string)($method_label ?? ''), 'COD') !== false; ?>
                      <?php if ($is_cod_order): ?>
                        <small class="muted">No proof required for COD.</small>
                      <?php else: ?>
                        <?php if (!empty($order['reference_number'])): ?>
                          <div class="muted">Ref: <span class="mono"><?php echo htmlspecialchars((string)$order['reference_number']); ?></span></div>
                        <?php endif; ?>
                        <?php if (!empty($order['proof_path'])): ?>
                          <div><a class="proof-link" href="<?php echo htmlspecialchars((string)$order['proof_path']); ?>" target="_blank" rel="noopener">View Screenshot</a></div>
                        <?php else: ?>
                          <small class="muted">No screenshot</small>
                        <?php endif; ?>
                        <?php if ($proof_status !== ''): ?>
                          <div style="margin-top:6px;">
                            <span class="status <?php echo htmlspecialchars($proof_status); ?>"><?php echo ucfirst(htmlspecialchars($proof_status)); ?></span>
                          </div>
                        <?php endif; ?>
                      <?php endif; ?>
                      <div class="action-divider" style="margin: 10px 0;"></div>
                      <form method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                        <input type="hidden" name="buyer_user_id" value="<?php echo (int)$order['buyer_user_id']; ?>">
                        <input class="reply-input" type="text" name="reply_message" placeholder="Reply to buyer..." maxlength="1000">
                        <div class="action-cluster">
                          <button type="submit" name="action" value="send_reply" class="action-btn btn-ship">Send Reply</button>
                        </div>
                      </form>
                      <div class="action-divider" style="margin: 10px 0;"></div>
                      <form method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                        <div class="action-row">
                        <?php if (!empty($order['proof_id']) && ($order['proof_status'] ?? '') === 'pending'): ?>
                          <input type="hidden" name="proof_id" value="<?php echo (int)$order['proof_id']; ?>">
                          <button type="submit" name="action" value="verify_payment" class="action-btn btn-verify">Verify Payment</button>
                          <button type="submit" name="action" value="reject_payment" class="action-btn btn-reject">Reject Proof</button>
                        <?php endif; ?>
                        <?php $status_group = $status_view['group']; ?>
                        <?php if ($status_group !== 'to_pay'): ?>
                          <button type="submit" name="action" value="mark_to_pay" class="action-btn btn-pay">Set To Pay</button>
                        <?php endif; ?>
                        <?php if ($status_group !== 'to_ship'): ?>
                          <button type="submit" name="action" value="mark_to_ship" class="action-btn btn-ship">Set To Ship</button>
                        <?php endif; ?>
                        <?php if ($status_group !== 'to_receive'): ?>
                          <button type="submit" name="action" value="mark_to_receive" class="action-btn btn-receive">Set To Receive</button>
                        <?php endif; ?>
                        <?php if ($status_group !== 'completed'): ?>
                          <button type="submit" name="action" value="mark_completed" class="action-btn btn-completed">Set Complete</button>
                        <?php endif; ?>
                        <?php if ($status_group !== 'cancelled'): ?>
                          <button type="submit" name="action" value="mark_cancelled" class="action-btn btn-cancelled">Set Cancelled</button>
                        <?php endif; ?>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
    </div>
  </main>
</div>
<script>
const csrfToken = '<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, "UTF-8"); ?>';

function toggleSelectAll() {
  const selectAll = document.getElementById('selectAll');
  const checkboxes = document.querySelectorAll('.order-checkbox');
  checkboxes.forEach(checkbox => {
    checkbox.checked = selectAll.checked;
  });
}

function bulkUpdate() {
  const selected = document.querySelectorAll('.order-checkbox:checked');
  const newStatus = document.getElementById('bulkStatus').value;

  if (selected.length === 0) {
    alert('Please select orders to update.');
    return;
  }

  if (!newStatus) {
    alert('Please select a status to apply.');
    return;
  }

  const ids = Array.from(selected).map(cb => cb.value);
  const confirmFn = window.adminConfirm || function (msg, ok) { if (confirm(msg)) { ok(); } };
  confirmFn(`Are you sure you want to update ${selected.length} order(s) to "${newStatus.replace('_', ' ')}"?`, function () {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'orders_admin.php';

    ids.forEach(id => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'selected_orders[]';
      input.value = id;
      form.appendChild(input);
    });

    const statusInput = document.createElement('input');
    statusInput.type = 'hidden';
    statusInput.name = 'bulk_status';
    statusInput.value = newStatus;
    form.appendChild(statusInput);

    const bulkInput = document.createElement('input');
    bulkInput.type = 'hidden';
    bulkInput.name = 'bulk_update';
    bulkInput.value = '1';
    form.appendChild(bulkInput);

    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = csrfToken;
    form.appendChild(csrfInput);

    document.body.appendChild(form);
    form.submit();
  });
}

document.querySelectorAll('.js-toggle-detail').forEach(btn => {
  btn.addEventListener('click', () => {
    const targetId = btn.getAttribute('data-target');
    const panel = document.getElementById(targetId);
    if (!panel) return;
    const isOpen = panel.classList.toggle('show');
    btn.classList.toggle('active', isOpen);
    btn.textContent = isOpen ? 'Hide Details' : 'View Details';
  });
});
</script>
<script src="assets/admin-enhancements.js"></script>
</body>
</html>






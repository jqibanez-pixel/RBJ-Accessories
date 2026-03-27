<?php
session_start();

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

if (!function_exists('rbj_ensure_shop_vouchers_table')) {
    function rbj_ensure_shop_vouchers_table(mysqli $conn): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS shop_vouchers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(50) NOT NULL UNIQUE,
                name VARCHAR(120) NOT NULL,
                voucher_type ENUM('free_shipping', 'fixed_discount') NOT NULL,
                amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                min_spend DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                start_at DATETIME NULL DEFAULT NULL,
                end_at DATETIME NULL DEFAULT NULL,
                usage_limit INT NULL DEFAULT NULL,
                used_count INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ";
        $conn->query($sql);

        $seed = "
            INSERT IGNORE INTO shop_vouchers (code, name, voucher_type, amount, min_spend, is_active)
            VALUES
            ('rbj_freeship', 'RBJ Free Shipping', 'free_shipping', 0.00, 0.00, 1),
            ('rbj_discount_100', 'RBJ Discount 100', 'fixed_discount', 100.00, 0.00, 1)
        ";
        $conn->query($seed);
    }
}
rbj_ensure_shop_vouchers_table($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Invalid request token.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'create') {
            $code = strtolower(trim((string)($_POST['code'] ?? '')));
            $name = trim((string)($_POST['name'] ?? ''));
            $voucher_type = (string)($_POST['voucher_type'] ?? 'fixed_discount');
            $amount = max(0.0, (float)($_POST['amount'] ?? 0));
            $min_spend = max(0.0, (float)($_POST['min_spend'] ?? 0));
            $usage_limit_raw = trim((string)($_POST['usage_limit'] ?? ''));
            $usage_limit = $usage_limit_raw === '' ? null : max(1, (int)$usage_limit_raw);
            $start_at = trim((string)($_POST['start_at'] ?? ''));
            $end_at = trim((string)($_POST['end_at'] ?? ''));
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if (!preg_match('/^[a-z0-9_]{3,40}$/', $code)) {
                $error = 'Voucher code must be 3-40 chars (lowercase letters, numbers, underscore).';
            } elseif ($name === '') {
                $error = 'Voucher name is required.';
            } elseif (!in_array($voucher_type, ['free_shipping', 'fixed_discount'], true)) {
                $error = 'Invalid voucher type.';
            } else {
                $start_val = $start_at !== '' ? date('Y-m-d H:i:s', strtotime($start_at)) : null;
                $end_val = $end_at !== '' ? date('Y-m-d H:i:s', strtotime($end_at)) : null;
                $stmt = $conn->prepare("
                    INSERT INTO shop_vouchers (code, name, voucher_type, amount, min_spend, start_at, end_at, usage_limit, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                if ($stmt) {
                    $stmt->bind_param('sssddssii', $code, $name, $voucher_type, $amount, $min_spend, $start_val, $end_val, $usage_limit, $is_active);
                    if ($stmt->execute()) {
                        $message = 'Voucher created successfully.';
                        rbj_admin_log(
                            $conn,
                            (int)$_SESSION['user_id'],
                            'create_voucher',
                            'voucher',
                            (int)$conn->insert_id,
                            ['code' => $code, 'voucher_type' => $voucher_type, 'amount' => $amount]
                        );
                    } else {
                        $error = 'Failed to create voucher. Code may already exist.';
                    }
                    $stmt->close();
                } else {
                    $error = 'Unable to prepare create query.';
                }
            }
        } elseif ($action === 'toggle') {
            $voucher_id = (int)($_POST['voucher_id'] ?? 0);
            $stmt = $conn->prepare("UPDATE shop_vouchers SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $voucher_id);
                if ($stmt->execute()) {
                    $message = 'Voucher status updated.';
                    rbj_admin_log(
                        $conn,
                        (int)$_SESSION['user_id'],
                        'toggle_voucher',
                        'voucher',
                        $voucher_id
                    );
                } else {
                    $error = 'Failed to update voucher status.';
                }
                $stmt->close();
            }
        }
    }
}

$vouchers = [];
$res = $conn->query("SELECT * FROM shop_vouchers ORDER BY created_at DESC");
if ($res instanceof mysqli_result) {
    while ($row = $res->fetch_assoc()) {
        $vouchers[] = $row;
    }
    $res->free();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Voucher Management - RBJ Accessories</title>
<link href="assets/admin-enhancements.css" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, sans-serif; }
body { background: #f4f6f8; }
.admin-container { display: flex; min-height: 100vh; }
.content { flex:1; padding:30px; }
.card { background:#fff; border-radius:10px; padding:20px; box-shadow:0 2px 10px rgba(0,0,0,0.08); margin-bottom:20px; }
.grid { display:grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap:12px; }
.field label { display:block; margin-bottom:6px; font-size:13px; color:#555; }
.field input, .field select { width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; }
.btn { padding:10px 14px; border:none; border-radius:8px; cursor:pointer; font-weight:600; }
.btn-primary { background:#111; color:#fff; }
.btn-secondary { background:#3498db; color:#fff; }
.alert { padding:12px; border-radius:8px; margin-bottom:12px; }
.success { background:#d4edda; color:#155724; }
.error { background:#f8d7da; color:#721c24; }
table { width:100%; border-collapse:collapse; background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,0.08); }
th, td { padding:12px; border-bottom:1px solid #eee; text-align:left; font-size:14px; }
th { background:#f8f9fa; }
.badge { padding:4px 8px; border-radius:999px; font-size:12px; font-weight:600; }
.badge.on { background:#2ecc71; color:#fff; }
.badge.off { background:#95a5a6; color:#fff; }
@media (max-width: 1000px) { .grid { grid-template-columns: 1fr; } }
</style>
</head>
<body>
<div class="admin-container">
  <aside class="sidebar">
    <div class="logo">
      <a class="admin-logo-link" href="/rbjsystem/ADMIN/dashboard_admin.php"><img src="/rbjsystem/rbjlogo.png" alt="RBJ Logo"></a>
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
      <a href="/rbjsystem/ADMIN/vouchers_admin.php" class="active">Vouchers</a>
      <a href="/rbjsystem/ADMIN/feedback_admin.php">Feedbacks</a>
      <a href="/rbjsystem/ADMIN/admin_support.php">Support</a>
      <a href="/rbjsystem/ADMIN/activity_logs_admin.php">Activity Logs</a>
      <a href="/rbjsystem/ADMIN/live_chat.php">Live Chat</a>
      <a href="/rbjsystem/logout.php">Logout</a>
    </nav>
  </aside>

  <main class="content">
    <h1 style="margin-bottom:16px;">Voucher Management</h1>
    <?php if (isset($message)): ?><div class="alert success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if (isset($error)): ?><div class="alert error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <section class="card">
      <h2 style="margin-bottom:12px;">Create Voucher</h2>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <input type="hidden" name="action" value="create">
        <div class="grid">
          <div class="field"><label>Code</label><input type="text" name="code" placeholder="rbj_discount_200" required></div>
          <div class="field"><label>Name</label><input type="text" name="name" placeholder="RBJ Discount 200" required></div>
          <div class="field">
            <label>Type</label>
            <select name="voucher_type">
              <option value="fixed_discount">Fixed Discount</option>
              <option value="free_shipping">Free Shipping</option>
            </select>
          </div>
          <div class="field"><label>Amount</label><input type="number" step="0.01" name="amount" value="0"></div>
          <div class="field"><label>Minimum Spend</label><input type="number" step="0.01" name="min_spend" value="0"></div>
          <div class="field"><label>Usage Limit (blank = unlimited)</label><input type="number" name="usage_limit"></div>
          <div class="field"><label>Start Date</label><input type="datetime-local" name="start_at"></div>
          <div class="field"><label>End Date</label><input type="datetime-local" name="end_at"></div>
          <div class="field"><label><input type="checkbox" name="is_active" checked> Active</label></div>
        </div>
        <div style="margin-top:12px;"><button class="btn btn-primary" type="submit">Create Voucher</button></div>
      </form>
    </section>

    <table>
      <thead>
        <tr>
          <th>Code</th>
          <th>Name</th>
          <th>Type</th>
          <th>Amount</th>
          <th>Min Spend</th>
          <th>Usage</th>
          <th>Schedule</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($vouchers)): ?>
          <tr><td colspan="9" style="text-align:center;">No vouchers found.</td></tr>
        <?php else: ?>
          <?php foreach ($vouchers as $v): ?>
            <tr>
              <td><?php echo htmlspecialchars((string)$v['code']); ?></td>
              <td><?php echo htmlspecialchars((string)$v['name']); ?></td>
              <td><?php echo htmlspecialchars(str_replace('_', ' ', (string)$v['voucher_type'])); ?></td>
              <td>PHP <?php echo number_format((float)$v['amount'], 2); ?></td>
              <td>PHP <?php echo number_format((float)$v['min_spend'], 2); ?></td>
              <td><?php echo (int)$v['used_count']; ?><?php echo $v['usage_limit'] !== null ? ' / ' . (int)$v['usage_limit'] : ' / unlimited'; ?></td>
              <td>
                <?php echo $v['start_at'] ? htmlspecialchars((string)$v['start_at']) : 'Any'; ?><br>
                <?php echo $v['end_at'] ? htmlspecialchars((string)$v['end_at']) : 'No end'; ?>
              </td>
              <td><span class="badge <?php echo ((int)$v['is_active'] === 1) ? 'on' : 'off'; ?>"><?php echo ((int)$v['is_active'] === 1) ? 'Active' : 'Inactive'; ?></span></td>
              <td>
                <form method="POST">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="voucher_id" value="<?php echo (int)$v['id']; ?>">
                  <button class="btn btn-secondary" type="submit"><?php echo ((int)$v['is_active'] === 1) ? 'Deactivate' : 'Activate'; ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </main>
</div>
</body>
</html>





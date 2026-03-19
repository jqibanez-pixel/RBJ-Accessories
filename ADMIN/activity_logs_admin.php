<?php
session_start();

if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['admin', 'superadmin'], true)) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
require_once __DIR__ . '/admin_audit.php';
rbj_ensure_admin_activity_logs_table($conn);

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 30;
$offset = ($page - 1) * $per_page;

$filter_admin = max(0, (int)($_GET['admin_id'] ?? 0));
$filter_action = trim((string)($_GET['action'] ?? ''));
$filter_entity = trim((string)($_GET['entity_type'] ?? ''));
$filter_date_from = trim((string)($_GET['date_from'] ?? ''));
$filter_date_to = trim((string)($_GET['date_to'] ?? ''));
$filter_q = trim((string)($_GET['q'] ?? ''));

$where = [];
if ($filter_admin > 0) {
    $where[] = 'l.admin_id = ' . $filter_admin;
}
if ($filter_action !== '') {
    $where[] = "l.action = '" . $conn->real_escape_string($filter_action) . "'";
}
if ($filter_entity !== '') {
    $where[] = "l.entity_type = '" . $conn->real_escape_string($filter_entity) . "'";
}
if ($filter_date_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date_from)) {
    $where[] = "DATE(l.created_at) >= '" . $conn->real_escape_string($filter_date_from) . "'";
}
if ($filter_date_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date_to)) {
    $where[] = "DATE(l.created_at) <= '" . $conn->real_escape_string($filter_date_to) . "'";
}
if ($filter_q !== '') {
    $q = $conn->real_escape_string('%' . $filter_q . '%');
    $where[] = "(u.username LIKE '{$q}' OR l.action LIKE '{$q}' OR l.entity_type LIKE '{$q}' OR COALESCE(l.details_json, '') LIKE '{$q}')";
}

$where_sql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

$admins = [];
$admin_res = $conn->query("
    SELECT DISTINCT u.id, u.username
    FROM admin_activity_logs l
    JOIN users u ON u.id = l.admin_id
    ORDER BY u.username ASC
");
if ($admin_res instanceof mysqli_result) {
    while ($row = $admin_res->fetch_assoc()) {
        $admins[] = $row;
    }
    $admin_res->free();
}

$actions = [];
$action_res = $conn->query("SELECT DISTINCT action FROM admin_activity_logs ORDER BY action ASC");
if ($action_res instanceof mysqli_result) {
    while ($row = $action_res->fetch_assoc()) {
        $actions[] = (string)$row['action'];
    }
    $action_res->free();
}

$entities = [];
$entity_res = $conn->query("SELECT DISTINCT entity_type FROM admin_activity_logs ORDER BY entity_type ASC");
if ($entity_res instanceof mysqli_result) {
    while ($row = $entity_res->fetch_assoc()) {
        $entities[] = (string)$row['entity_type'];
    }
    $entity_res->free();
}

$total = 0;
$count_sql = "
    SELECT COUNT(*) AS total
    FROM admin_activity_logs l
    JOIN users u ON u.id = l.admin_id
    {$where_sql}
";
$count_res = $conn->query($count_sql);
if ($count_res instanceof mysqli_result) {
    $total = (int)($count_res->fetch_assoc()['total'] ?? 0);
    $count_res->free();
}
$total_pages = max(1, (int)ceil($total / $per_page));
if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $per_page;
}

$logs = [];
$logs_sql = "
    SELECT
        l.id, l.admin_id, l.action, l.entity_type, l.entity_id, l.details_json,
        l.ip_address, l.user_agent, l.created_at, u.username
    FROM admin_activity_logs l
    JOIN users u ON u.id = l.admin_id
    {$where_sql}
    ORDER BY l.created_at DESC, l.id DESC
    LIMIT {$per_page} OFFSET {$offset}
";
$logs_res = $conn->query($logs_sql);
if ($logs_res instanceof mysqli_result) {
    while ($row = $logs_res->fetch_assoc()) {
        $logs[] = $row;
    }
    $logs_res->free();
}

$conn->close();

function h($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function url_with_page(int $target_page): string
{
    $query = $_GET;
    $query['page'] = $target_page;
    return 'activity_logs_admin.php?' . http_build_query($query);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Activity Logs - RBJ Accessories</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link href="assets/admin-enhancements.css" rel="stylesheet">
<style>
* { box-sizing: border-box; font-family: Arial, sans-serif; }
body { margin: 0; background: #f4f6f8; color: #1f2933; }
.admin-container { display: flex; min-height: 100vh; }
.sidebar { width: 220px; background: #111; color: #fff; padding: 20px; }
.sidebar a { display: block; color: #fff; text-decoration: none; padding: 10px; margin-bottom: 5px; }
.sidebar a:hover, .sidebar a.active { background: #444; border-radius: 5px; }
.content { flex: 1; padding: 24px; overflow-x: auto; }
.panel { background: #fff; border: 1px solid #dfe6ee; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
.panel-head { padding: 14px 16px; border-bottom: 1px solid #edf1f5; font-weight: 700; color: #c9473c; }
.filters { padding: 14px 16px; display: grid; grid-template-columns: repeat(6, minmax(120px, 1fr)); gap: 10px; }
.filters input, .filters select { width: 100%; border: 1px solid #c8d3df; border-radius: 8px; padding: 8px 10px; font-size: 13px; }
.filters .actions { display: flex; gap: 8px; align-items: end; }
.btn { border: 1px solid #c8d3df; border-radius: 8px; padding: 8px 12px; background: #fff; color: #1f2933; text-decoration: none; cursor: pointer; font-size: 13px; }
.btn.primary { background: #111; border-color: #111; color: #fff; }
.summary { padding: 0 16px 14px; color: #607080; font-size: 13px; }
table { width: 100%; border-collapse: collapse; }
th, td { border-bottom: 1px solid #edf1f5; padding: 10px 8px; text-align: left; vertical-align: top; font-size: 13px; }
th { background: #fafbfd; color: #607080; text-transform: uppercase; font-size: 11px; letter-spacing: .04em; }
.mono { font-family: Consolas, monospace; font-size: 12px; white-space: pre-wrap; }
.subtle { color: #7a8796; font-size: 12px; }
.pager { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; }
@media (max-width: 1100px) {
  .filters { grid-template-columns: repeat(2, minmax(120px, 1fr)); }
  .sidebar { display: none; }
}
</style>
</head>
<body>
<div class="admin-container">
  <aside class="sidebar">
    <div class="logo" style="text-align:center;margin-bottom:20px;">
      <a href="/rbjsystem/ADMIN/dashboard_admin.php">
        <img src="/rbjsystem/rbjlogo.png" alt="RBJ Logo" style="height:100px;width:auto;display:block;margin:0 auto;">
      </a>
    </div>
    <a href="/rbjsystem/ADMIN/dashboard_admin.php">Dashboard</a>
    <a href="/rbjsystem/ADMIN/users_admin.php">Users</a>
    <a href="/rbjsystem/ADMIN/orders_admin.php">Orders</a>
    <a href="/rbjsystem/ADMIN/products_admin.php">Products</a>
    <a href="/rbjsystem/ADMIN/vouchers_admin.php">Vouchers</a>
    <a href="/rbjsystem/ADMIN/feedback_admin.php">Feedbacks</a>
    <a href="/rbjsystem/ADMIN/admin_support.php">Support</a>
    <a href="/rbjsystem/ADMIN/activity_logs_admin.php" class="active">Activity Logs</a>
    <a href="/rbjsystem/ADMIN/live_chat.php">Live Chat</a>
    <a href="/rbjsystem/logout.php">Logout</a>
  </aside>

  <main class="content">
    <div class="panel">
      <div class="panel-head">Admin Activity Logs</div>
      <form method="GET" class="filters">
        <div>
          <label class="subtle">Admin</label>
          <select name="admin_id">
            <option value="0">All admins</option>
            <?php foreach ($admins as $a): ?>
              <option value="<?php echo (int)$a['id']; ?>" <?php echo $filter_admin === (int)$a['id'] ? 'selected' : ''; ?>>
                <?php echo h($a['username']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="subtle">Action</label>
          <select name="action">
            <option value="">All actions</option>
            <?php foreach ($actions as $action): ?>
              <option value="<?php echo h($action); ?>" <?php echo $filter_action === $action ? 'selected' : ''; ?>>
                <?php echo h($action); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="subtle">Entity</label>
          <select name="entity_type">
            <option value="">All entities</option>
            <?php foreach ($entities as $entity): ?>
              <option value="<?php echo h($entity); ?>" <?php echo $filter_entity === $entity ? 'selected' : ''; ?>>
                <?php echo h($entity); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="subtle">Date From</label>
          <input type="date" name="date_from" value="<?php echo h($filter_date_from); ?>">
        </div>
        <div>
          <label class="subtle">Date To</label>
          <input type="date" name="date_to" value="<?php echo h($filter_date_to); ?>">
        </div>
        <div>
          <label class="subtle">Search</label>
          <input type="text" name="q" value="<?php echo h($filter_q); ?>" placeholder="username/action/details">
        </div>
        <div class="actions">
          <button class="btn primary" type="submit">Apply</button>
          <a class="btn" href="activity_logs_admin.php">Reset</a>
        </div>
      </form>

      <div class="summary">
        Showing <?php echo count($logs); ?> of <?php echo (int)$total; ?> log entries.
      </div>

      <table>
        <thead>
          <tr>
            <th>When</th>
            <th>Admin</th>
            <th>Action</th>
            <th>Entity</th>
            <th>Details</th>
            <th>Client</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($logs)): ?>
            <tr>
              <td colspan="6" style="padding:20px;text-align:center;color:#7a8796;">No activity logs found for this filter.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($logs as $log): ?>
              <tr>
                <td>
                  <?php echo h(date('M j, Y g:i A', strtotime((string)$log['created_at']))); ?><br>
                  <span class="subtle">#<?php echo (int)$log['id']; ?></span>
                </td>
                <td>
                  <?php echo h($log['username']); ?><br>
                  <span class="subtle">ID <?php echo (int)$log['admin_id']; ?></span>
                </td>
                <td><?php echo h($log['action']); ?></td>
                <td>
                  <?php echo h($log['entity_type']); ?>
                  <?php if ($log['entity_id'] !== null): ?>
                    <span class="subtle">#<?php echo (int)$log['entity_id']; ?></span>
                  <?php endif; ?>
                </td>
                <td class="mono"><?php echo h($log['details_json'] ?? ''); ?></td>
                <td>
                  <span class="subtle">IP: <?php echo h($log['ip_address'] ?? '-'); ?></span><br>
                  <span class="subtle"><?php echo h($log['user_agent'] ?? '-'); ?></span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>

      <div class="pager">
        <div class="subtle">Page <?php echo (int)$page; ?> of <?php echo (int)$total_pages; ?></div>
        <div style="display:flex;gap:8px;">
          <?php if ($page > 1): ?>
            <a class="btn" href="<?php echo h(url_with_page($page - 1)); ?>">Previous</a>
          <?php endif; ?>
          <?php if ($page < $total_pages): ?>
            <a class="btn" href="<?php echo h(url_with_page($page + 1)); ?>">Next</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</div>
</body>
</html>


<?php
session_start();

// Check if admin/superadmin is logged in
if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['admin', 'superadmin'], true)) {
    header("Location: ../login.php");
    exit();
}
$current_admin_name = (string)($_SESSION['username'] ?? 'Admin');
$current_admin_role = (string)($_SESSION['role'] ?? 'admin');

include '../config.php';
require_once __DIR__ . '/admin_audit.php';

// Handle user operations
$message = '';
$current_admin_id = (int)$_SESSION['user_id'];
$is_superadmin = (($_SESSION['role'] ?? '') === 'superadmin');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = "Invalid request token. Please refresh and try again.";
    } elseif (isset($_POST['update_user'])) {
        $id = (int)$_POST['user_id'];
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $requested_role = trim((string)($_POST['role'] ?? 'user'));
        $role = in_array($requested_role, ['user', 'admin', 'superadmin'], true) ? $requested_role : 'user';

        $target_stmt = $conn->prepare("SELECT username, role FROM users WHERE id = ?");
        $target_stmt->bind_param("i", $id);
        $target_stmt->execute();
        $target_user = $target_stmt->get_result()->fetch_assoc();
        $target_stmt->close();

        if (!$target_user) {
            $message = "User not found.";
        } elseif (($target_user['role'] ?? '') === 'superadmin' && !$is_superadmin) {
            $message = "Only superadmin can modify the superadmin account.";
        } elseif ($id === $current_admin_id && ($target_user['role'] ?? '') === 'superadmin' && $role !== 'superadmin') {
            $message = "You cannot demote your own superadmin account.";
        } elseif (!$is_superadmin && in_array(($target_user['role'] ?? ''), ['admin', 'superadmin'], true) && $id !== $current_admin_id) {
            $message = "Only superadmin can modify other admin accounts.";
        } elseif (!$is_superadmin && $role !== $target_user['role']) {
            $message = "Only superadmin can change user roles.";
        } elseif (!empty($username) && !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
            $stmt->bind_param("sssi", $username, $email, $role, $id);

            if ($stmt->execute()) {
                $message = "User updated successfully!";
                rbj_admin_log(
                    $conn,
                    $current_admin_id,
                    'update_user',
                    'user',
                    $id,
                    ['role' => $role, 'username' => $username]
                );
            } else {
                $message = "Failed to update user.";
            }
            $stmt->close();
        } else {
            $message = "Please provide valid username and email.";
        }
    } elseif (isset($_POST['delete_user'])) {
        $id = (int)$_POST['user_id'];
        $target_stmt = $conn->prepare("SELECT username, role FROM users WHERE id = ?");
        $target_stmt->bind_param("i", $id);
        $target_stmt->execute();
        $target_user = $target_stmt->get_result()->fetch_assoc();
        $target_stmt->close();

        if (!$target_user) {
            $message = "User not found.";
        } elseif ($id === $current_admin_id) {
            $message = "You cannot delete your own account.";
        } elseif (($target_user['role'] ?? '') === 'superadmin') {
            $message = "Superadmin account cannot be deleted.";
        } elseif (!$is_superadmin && in_array(($target_user['role'] ?? ''), ['admin', 'superadmin'], true)) {
            $message = "Only superadmin can delete admin accounts.";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                $message = "User deleted successfully!";
                rbj_admin_log(
                    $conn,
                    $current_admin_id,
                    'delete_user',
                    'user',
                    $id,
                    ['username' => (string)($target_user['username'] ?? '')]
                );
            } else {
                $message = "Failed to delete user.";
            }
            $stmt->close();
        }
    } elseif (isset($_POST['bulk_delete'])) {
        $ids = isset($_POST['selected_users']) ? array_map('intval', $_POST['selected_users']) : [];
        $ids = array_values(array_unique(array_filter($ids, function ($id) {
            return $id > 0;
        })));

        if (!empty($ids)) {
            if (in_array($current_admin_id, $ids, true)) {
                $message = "You cannot delete your own account.";
            } else {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $types = str_repeat('i', count($ids));

                $check_stmt = $conn->prepare("SELECT username, role FROM users WHERE id IN ($placeholders)");
                $check_stmt->bind_param($types, ...$ids);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();

                $has_superadmin = false;
                $has_admin = false;
                while ($row = $check_result->fetch_assoc()) {
                    if (($row['role'] ?? '') === 'superadmin') {
                        $has_superadmin = true;
                    }
                    if (in_array(($row['role'] ?? ''), ['admin', 'superadmin'], true)) {
                        $has_admin = true;
                    }
                }
                $check_stmt->close();

                if ($has_superadmin) {
                    $message = "Superadmin account cannot be deleted.";
                } elseif (!$is_superadmin && $has_admin) {
                    $message = "Only superadmin can delete admin accounts.";
                } else {
                    $stmt = $conn->prepare("DELETE FROM users WHERE id IN ($placeholders)");
                    $stmt->bind_param($types, ...$ids);

                    if ($stmt->execute()) {
                        $message = count($ids) . " user(s) deleted successfully!";
                        rbj_admin_log(
                            $conn,
                            $current_admin_id,
                            'bulk_delete_users',
                            'user',
                            null,
                            ['count' => count($ids), 'ids' => $ids]
                        );
                    } else {
                        $message = "Failed to delete selected users.";
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// Fetch all users
$stmt = $conn->prepare("SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC");
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Users Management - RBJ Accessories</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link href="assets/admin-enhancements.css" rel="stylesheet">
<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  font-family: Arial, sans-serif;
}

body {
  background: #f4f6f8;
  color: #2c3e50;
}

.admin-container {
  display: flex;
  height: 100vh;
}

.content {
  flex: 1;
  padding: 30px;
  overflow-y: auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.page-header h1 {
  margin: 0;
}

.page-subtitle {
  margin-top: 6px;
  color: #6c757d;
}

.message {
  background: #d4edda;
  color: #155724;
  border: 1px solid #c3e6cb;
  padding: 12px;
  border-radius: 4px;
  margin-bottom: 20px;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
  margin-bottom: 30px;
}

.stat-card {
  background: white;
  padding: 20px;
  border-radius: 10px;
  text-align: center;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.stat-label {
  color: #777;
  margin-bottom: 6px;
}

.stat-value {
  font-size: 32px;
  font-weight: 700;
  color: #333;
}

.users-panel {
  background: white;
  border-radius: 10px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.panel-head {
  background: #f8f9fa;
  padding: 15px 20px;
  border-bottom: 1px solid #dee2e6;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}

.panel-head h2 {
  font-size: 24px;
  color: #2c3e50;
}

.toolbar {
  padding: 14px 20px;
  border-bottom: 1px solid #dee2e6;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.toolbar-left,
.toolbar-right {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.toolbar input[type="search"],
.toolbar select {
  border: 1px solid #dee2e6;
  border-radius: 4px;
  padding: 9px 11px;
  font-size: 14px;
  min-width: 170px;
}

.toolbar label {
  color: #495057;
}

.table-wrap {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
}

table th,
table td {
  padding: 12px 15px;
  border-bottom: 1px solid #dee2e6;
  text-align: left;
  vertical-align: middle;
  white-space: nowrap;
}

table th {
  background: #f8f9fa;
  font-weight: 600;
  color: #2c3e50;
}

tbody tr:hover {
  background: #f8f9fa;
}

.role-badge {
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
}

.role-badge.admin {
  background: #fff3cd;
  color: #856404;
}

.role-badge.superadmin {
  background: #f8d7da;
  color: #721c24;
}

.role-badge.user {
  background: #d4edda;
  color: #155724;
}

.btn {
  border: none;
  border-radius: 4px;
  padding: 8px 12px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.btn-edit {
  background: #ffc107;
  color: #212529;
  margin-right: 6px;
}

.btn-delete {
  background: #dc3545;
  color: white;
}

.btn-ghost {
  background: #6c757d;
  color: white;
}

.selected-count {
  font-size: 13px;
  color: #6c757d;
}

.empty-row td {
  text-align: center;
  color: #6c757d;
  padding: 34px 16px;
}

.modal {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 1000;
  padding: 18px;
}

.modal-content {
  width: min(520px, 100%);
  margin: 5% auto 0;
  background: #fff;
  border-radius: 8px;
  border: 1px solid #dee2e6;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
  padding: 20px;
}

.modal-content h2 {
  margin-bottom: 14px;
}

.close {
  float: right;
  font-size: 24px;
  line-height: 1;
  cursor: pointer;
  color: #6c757d;
}

.form-group {
  margin-bottom: 12px;
}

.form-group label {
  display: block;
  margin-bottom: 6px;
  font-weight: 600;
}

.form-group input,
.form-group select {
  width: 100%;
  border: 1px solid #dee2e6;
  border-radius: 4px;
  padding: 10px 12px;
  font-size: 14px;
}

.add-btn {
  width: 100%;
  border: none;
  background: #3498db;
  color: #fff;
  border-radius: 4px;
  padding: 11px 12px;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}

@media (max-width: 1024px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .toolbar {
    flex-direction: column;
    align-items: flex-start;
  }

  .toolbar-right {
    width: 100%;
  }
}

@media (max-width: 900px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }
}
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
      <a href="/rbjsystem/ADMIN/users_admin.php" class="active">Users</a>
      <a href="/rbjsystem/ADMIN/orders_admin.php">Orders</a>
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
    <div class="page-header">
      <div>
        <h1>Users Management</h1>
        <p class="page-subtitle">Manage accounts, roles, and access quickly from one dashboard.</p>
      </div>
    </div>

    <?php if (!empty($message)): ?>
      <div class="message"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">Total Users</div>
        <div class="stat-value"><?php echo count($users); ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Administrators</div>
        <div class="stat-value"><?php echo count(array_filter($users, function($u) { return in_array(($u['role'] ?? ''), ['admin', 'superadmin'], true); })); ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Regular Users</div>
        <div class="stat-value"><?php echo count(array_filter($users, function($u) { return $u['role'] === 'user'; })); ?></div>
      </div>
    </div>

    <div class="users-panel">
      <div class="panel-head">
        <h2>All Users</h2>
        <div class="selected-count">
          Visible: <strong id="visibleCount"><?php echo count($users); ?></strong> |
          Selected: <strong id="selectedCount">0</strong>
        </div>
      </div>
      <div class="toolbar">
        <div class="toolbar-left">
          <input type="search" id="userSearch" placeholder="Search by username or email">
          <select id="roleFilter">
            <option value="">All Roles</option>
            <option value="admin">Admin</option>
            <option value="superadmin">Superadmin</option>
            <option value="user">User</option>
          </select>
          <button class="btn btn-ghost" type="button" onclick="clearFilters()">Clear Filters</button>
        </div>
        <div class="toolbar-right">
          <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this.checked)">
          <label for="selectAll">Select All Visible</label>
          <button id="bulkDeleteBtn" class="btn btn-delete" type="button" onclick="bulkDelete()" disabled>Delete Selected</button>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th><input type="checkbox" id="headerCheckbox" onchange="toggleSelectAll(this.checked)"></th>
              <th>ID</th>
              <th>Username</th>
              <th>Email</th>
              <th>Role</th>
              <th>Registration Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="usersTbody">
            <?php if (empty($users)): ?>
              <tr>
                <td colspan="7" class="empty-row">No users found.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($users as $user): ?>
                <tr class="user-row" data-role="<?php echo htmlspecialchars($user['role']); ?>">
                  <td><input type="checkbox" class="user-checkbox" value="<?php echo $user['id']; ?>" onchange="updateSelectionState()"></td>
                  <td><?php echo $user['id']; ?></td>
                  <td class="username-cell"><?php echo htmlspecialchars($user['username']); ?></td>
                  <td class="email-cell"><?php echo htmlspecialchars($user['email']); ?></td>
                  <td>
                    <span class="role-badge <?php echo $user['role']; ?>">
                      <?php echo ucfirst($user['role']); ?>
                    </span>
                  </td>
                  <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                  <td class="actions">
                    <button class="btn btn-edit" type="button" onclick='editUser(<?php echo (int)$user['id']; ?>, <?php echo json_encode($user['username']); ?>, <?php echo json_encode($user['email']); ?>, <?php echo json_encode($user['role']); ?>)'>Edit</button>
                    <button class="btn btn-delete" type="button" onclick='deleteUser(<?php echo (int)$user['id']; ?>, <?php echo json_encode($user['username']); ?>)'>Delete</button>
                  </td>
                </tr>
              <?php endforeach; ?>
              <tr id="filterEmptyRow" class="empty-row" style="display: none;">
                <td colspan="7">No users match your current filters.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>

  <!-- Edit User Modal -->
  <div id="userModal" class="modal">
    <div class="modal-content">
      <span class="close" onclick="closeModal()">&times;</span>
      <h2>Edit User</h2>
      <form method="POST" action="users_admin.php">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <input type="hidden" name="user_id" id="userId">
        <div class="form-group">
          <label for="username">Username *</label>
          <input type="text" id="username" name="username" required>
        </div>
        <div class="form-group">
          <label for="email">Email *</label>
          <input type="email" id="email" name="email" required>
        </div>
        <div class="form-group">
          <label for="role">Role *</label>
          <select id="role" name="role" required>
            <option value="user">User</option>
            <option value="admin">Admin</option>
            <option value="superadmin">Superadmin</option>
          </select>
        </div>
        <button type="submit" name="update_user" class="add-btn">Update User</button>
      </form>
    </div>
  </div>
</div>

<script>
const csrfToken = '<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, "UTF-8"); ?>';

function getVisibleRows() {
  return Array.from(document.querySelectorAll('.user-row')).filter(row => row.style.display !== 'none');
}

function updateSelectionState() {
  const visibleRows = getVisibleRows();
  const visibleCheckboxes = visibleRows.map(row => row.querySelector('.user-checkbox'));
  const selectedCheckboxes = Array.from(document.querySelectorAll('.user-checkbox:checked'));
  const selectedVisible = visibleCheckboxes.filter(cb => cb.checked);

  const allVisibleChecked = visibleCheckboxes.length > 0 && selectedVisible.length === visibleCheckboxes.length;
  document.getElementById('selectAll').checked = allVisibleChecked;
  document.getElementById('headerCheckbox').checked = allVisibleChecked;
  document.getElementById('selectedCount').textContent = selectedCheckboxes.length;

  const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
  if (bulkDeleteBtn) {
    bulkDeleteBtn.disabled = selectedCheckboxes.length === 0;
  }
}

function applyFilters() {
  const query = document.getElementById('userSearch').value.trim().toLowerCase();
  const role = document.getElementById('roleFilter').value;
  const rows = document.querySelectorAll('.user-row');
  const filterEmptyRow = document.getElementById('filterEmptyRow');
  let visible = 0;

  rows.forEach(row => {
    const username = row.querySelector('.username-cell').textContent.toLowerCase();
    const email = row.querySelector('.email-cell').textContent.toLowerCase();
    const rowRole = row.getAttribute('data-role');
    const searchMatch = query === '' || username.includes(query) || email.includes(query);
    const roleMatch = role === '' || rowRole === role;
    const isVisible = searchMatch && roleMatch;

    row.style.display = isVisible ? '' : 'none';
    if (isVisible) {
      visible += 1;
    } else {
      row.querySelector('.user-checkbox').checked = false;
    }
  });

  const visibleCount = document.getElementById('visibleCount');
  if (visibleCount) {
    visibleCount.textContent = visible;
  }

  if (filterEmptyRow) {
    filterEmptyRow.style.display = visible === 0 ? '' : 'none';
  }

  updateSelectionState();
}

function clearFilters() {
  document.getElementById('userSearch').value = '';
  document.getElementById('roleFilter').value = '';
  applyFilters();
}

function toggleSelectAll(checked) {
  const rows = getVisibleRows();
  rows.forEach(row => {
    const checkbox = row.querySelector('.user-checkbox');
    checkbox.checked = checked;
  });
  updateSelectionState();
}

function bulkDelete() {
  const selected = document.querySelectorAll('.user-checkbox:checked');
  if (selected.length === 0) {
    alert('Please select users to delete.');
    return;
  }

  const ids = Array.from(selected).map(cb => cb.value);
  const confirmFn = window.adminConfirm || function (msg, ok) { if (confirm(msg)) { ok(); } };
  confirmFn(`Are you sure you want to delete ${selected.length} user(s)? This action cannot be undone.`, function () {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'users_admin.php';

    ids.forEach(id => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'selected_users[]';
      input.value = id;
      form.appendChild(input);
    });

    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'bulk_delete';
    actionInput.value = '1';
    form.appendChild(actionInput);

    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = csrfToken;
    form.appendChild(csrfInput);

    document.body.appendChild(form);
    form.submit();
  });
}

function editUser(id, username, email, role) {
  document.getElementById('userId').value = id;
  document.getElementById('username').value = username;
  document.getElementById('email').value = email;
  document.getElementById('role').value = role;
  document.getElementById('userModal').style.display = 'block';
}

function deleteUser(id, username) {
  const confirmFn = window.adminConfirm || function (msg, ok) { if (confirm(msg)) { ok(); } };
  confirmFn(`Are you sure you want to delete "${username}"? This action cannot be undone.`, function () {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'users_admin.php';

    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'user_id';
    idInput.value = id;

    const deleteInput = document.createElement('input');
    deleteInput.type = 'hidden';
    deleteInput.name = 'delete_user';
    deleteInput.value = '1';

    form.appendChild(idInput);
    form.appendChild(deleteInput);

    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = csrfToken;
    form.appendChild(csrfInput);
    document.body.appendChild(form);
    form.submit();
  });
}

function closeModal() {
  document.getElementById('userModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
  const modal = document.getElementById('userModal');
  if (event.target == modal) {
    modal.style.display = 'none';
  }
}

document.addEventListener('DOMContentLoaded', function () {
  const searchInput = document.getElementById('userSearch');
  const roleFilter = document.getElementById('roleFilter');

  if (searchInput) {
    searchInput.addEventListener('input', applyFilters);
  }
  if (roleFilter) {
    roleFilter.addEventListener('change', applyFilters);
  }

  applyFilters();
});
</script>

</body>
</html>








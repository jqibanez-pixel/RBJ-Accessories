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

if (!function_exists('rbj_ensure_product_reviews_table')) {
    function rbj_ensure_product_reviews_table(mysqli $conn): void
    {
        $conn->query("
            CREATE TABLE IF NOT EXISTS product_reviews (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                product_id INT NOT NULL,
                rating TINYINT NOT NULL,
                comment TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_product_review (user_id, product_id),
                INDEX idx_product_reviews_product (product_id),
                INDEX idx_product_reviews_user (user_id),
                CONSTRAINT fk_product_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_product_reviews_product FOREIGN KEY (product_id) REFERENCES customization_templates(id) ON DELETE CASCADE
            )
        ");
    }
}
rbj_ensure_product_reviews_table($conn);

// Handle feedback approval/rejection
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $posted_token = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrf_token, $posted_token)) {
        $error = "Invalid request token. Please refresh and try again.";
    } else {
        $feedback_id = (int)$_POST['feedback_id'];
        $action = $_POST['action'];

        if ($action === 'approve') {
            $status = 'approved';
        } elseif ($action === 'reject') {
            $status = 'rejected';
        } else {
            $status = 'submitted'; // fallback
        }

        // First, get the user_id and feedback details for notification
        $stmt = $conn->prepare("SELECT user_id, feedback FROM feedback WHERE id = ?");
        $stmt->bind_param("i", $feedback_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $feedback_data = $result->fetch_assoc();
        $stmt->close();

        if ($feedback_data) {
            $user_id = $feedback_data['user_id'];
            $feedback_text = substr($feedback_data['feedback'], 0, 50) . (strlen($feedback_data['feedback']) > 50 ? '...' : '');

            // Update feedback status
            $stmt = $conn->prepare("UPDATE feedback SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $feedback_id);

            if ($stmt->execute()) {
                $message = "Feedback " . ($status === 'approved' ? 'approved' : 'rejected') . " successfully!";
                rbj_admin_log(
                    $conn,
                    (int)$_SESSION['user_id'],
                    $status === 'approved' ? 'approve_feedback' : 'reject_feedback',
                    'feedback',
                    $feedback_id,
                    ['status' => $status]
                );

                // Create notification for the user
                $notification_message = "Your feedback \"" . $feedback_text . "\" has been " . $status . ".";
                $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, 0)");
                $stmt->bind_param("is", $user_id, $notification_message);
                $stmt->execute();
                $stmt->close();
            } else {
                $error = "Failed to update feedback status.";
            }
        } else {
            $error = "Feedback not found.";
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_product_review'])) {
    $posted_token = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrf_token, $posted_token)) {
        $error = "Invalid request token. Please refresh and try again.";
    } else {
        $review_id = (int)$_POST['review_id'];
        if ($review_id > 0) {
            $stmt = $conn->prepare("DELETE FROM product_reviews WHERE id = ?");
            $stmt->bind_param("i", $review_id);
            if ($stmt->execute()) {
                $message = "Product review deleted successfully.";
                rbj_admin_log(
                    $conn,
                    (int)$_SESSION['user_id'],
                    'delete_product_review',
                    'product_review',
                    $review_id
                );
            } else {
                $error = "Failed to delete product review.";
            }
            $stmt->close();
        }
    }
}

// Fetch all submitted feedbacks
$feedbacks = [];
$stmt = $conn->prepare("SELECT f.id, f.feedback, f.rating, f.status, f.created_at, u.username FROM feedback f JOIN users u ON f.user_id = u.id ORDER BY f.created_at DESC");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $feedbacks = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}

$product_reviews = [];
$stmt = $conn->prepare("
    SELECT pr.id, pr.rating, pr.comment, pr.created_at,
           u.username, t.name AS product_name
    FROM product_reviews pr
    JOIN users u ON pr.user_id = u.id
    JOIN customization_templates t ON pr.product_id = t.id
    ORDER BY pr.created_at DESC
");
if ($stmt) {
    $stmt->execute();
    $product_reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Feedback Review - RBJ Accessories</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link href="assets/admin-enhancements.css" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, sans-serif; }
body { background: #f4f6f8; }
.admin-container { display: flex; height: 100vh; }
.content { flex: 1; padding: 30px; overflow-y: auto; font-size: 16px; }
.content h1 { margin-bottom: 20px; font-size: 32px; font-weight: 700; }
table { width: 100%; background: white; border-collapse: collapse; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
table th, table td { padding: 15px; border-bottom: 1px solid #ddd; text-align: left; font-size: 15px; }
table th { background: #f8f9fa; font-weight: 600; }
button.approve { background: #27ae60; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; margin-right: 5px; }
button.reject { background: #c0392b; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; }
button.approve:hover, button.reject:hover { opacity: 0.85; }
.status-badge { padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; }
.status.submitted { background: #bdc3c7; color: #2c3e50; }
.status.approved { background: #2ecc71; color: white; }
.status.rejected { background: #e74c3c; color: white; }
.alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
.alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.feedback-text { max-width: 300px; word-wrap: break-word; }
.rating-stars { color: #f39c12; }
.section-title { margin: 30px 0 14px; font-size: 22px; font-weight: 700; }
.review-text { max-width: 360px; word-wrap: break-word; }
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
      <a href="/rbjsystem/ADMIN/feedback_admin.php" class="active">Feedbacks</a>
      <a href="/rbjsystem/ADMIN/admin_support.php">Support</a>
      <a href="/rbjsystem/ADMIN/activity_logs_admin.php">Activity Logs</a>
      <a href="/rbjsystem/ADMIN/live_chat.php">Live Chat</a>
      <a href="/rbjsystem/logout.php">Logout</a>
    </nav>
  </aside>

  <main class="content">
    <h1>Feedback Management</h1>

    <?php if (isset($message)): ?>
      <div class="alert success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
      <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <table>
      <thead>
        <tr>
          <th>User</th>
          <th>Feedback</th>
          <th>Rating</th>
          <th>Status</th>
          <th>Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($feedbacks)): ?>
          <tr>
            <td colspan="6" style="text-align: center; padding: 40px;">No feedback submissions yet.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($feedbacks as $feedback): ?>
            <tr>
              <td><?php echo htmlspecialchars($feedback['username']); ?></td>
              <td class="feedback-text"><?php echo htmlspecialchars($feedback['feedback']); ?></td>
              <td>
                <span class="rating-stars">
                  <?php echo str_repeat('★', $feedback['rating']) . str_repeat('☆', 5 - $feedback['rating']); ?>
                </span>
                (<?php echo $feedback['rating']; ?>/5)
              </td>
              <td>
                <span class="status-badge status <?php echo $feedback['status']; ?>">
                  <?php echo ucfirst($feedback['status']); ?>
                </span>
              </td>
              <td><?php echo date('M d, Y', strtotime($feedback['created_at'])); ?></td>
              <td>
                <?php if ($feedback['status'] === 'submitted'): ?>
                  <form method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="feedback_id" value="<?php echo $feedback['id']; ?>">
                    <button type="submit" name="action" value="approve" class="approve">Approve</button>
                    <button type="submit" name="action" value="reject" class="reject">Reject</button>
                  </form>
                <?php else: ?>
                  <span style="color: #666; font-style: italic;">
                    <?php echo ucfirst($feedback['status']); ?>
                  </span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <h2 class="section-title">Product Reviews</h2>
    <table>
      <thead>
        <tr>
          <th>Product</th>
          <th>User</th>
          <th>Rating</th>
          <th>Comment</th>
          <th>Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($product_reviews)): ?>
          <tr>
            <td colspan="6" style="text-align: center; padding: 40px;">No product reviews yet.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($product_reviews as $review): ?>
            <tr>
              <td><strong><?php echo htmlspecialchars($review['product_name']); ?></strong></td>
              <td><?php echo htmlspecialchars($review['username']); ?></td>
              <td>
                <span class="rating-stars">
                  <?php echo str_repeat('★', (int)$review['rating']) . str_repeat('☆', 5 - (int)$review['rating']); ?>
                </span>
                (<?php echo (int)$review['rating']; ?>/5)
              </td>
              <td class="review-text"><?php echo htmlspecialchars($review['comment']); ?></td>
              <td><?php echo date('M d, Y', strtotime($review['created_at'])); ?></td>
              <td>
                <form method="POST" style="display: inline;">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                  <input type="hidden" name="review_id" value="<?php echo (int)$review['id']; ?>">
                  <button type="submit" name="delete_product_review" class="reject">Delete</button>
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






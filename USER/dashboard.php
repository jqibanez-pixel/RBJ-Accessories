<?php
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
$is_ajax_feedback = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';

$user_id = (int)$_SESSION['user_id'];
$dashboard_username = $_SESSION['username'] ?? 'Rider';
$feedback_errors = [];
$feedback_success = '';

// Ensure username reflects the currently authenticated user id.
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!empty($user_row['username'])) {
        $dashboard_username = $user_row['username'];
        $_SESSION['username'] = $dashboard_username;
    }
}

// Handle dashboard feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_dashboard_feedback'])) {
    $posted_token = $_POST['csrf_token'] ?? '';
    $feedback = trim($_POST['feedback'] ?? '');
    $rating = (int)($_POST['rating'] ?? 0);

    if ($feedback === '') {
        $feedback_errors[] = 'Please enter your feedback.';
    }

    if ($rating < 1 || $rating > 5) {
        $feedback_errors[] = 'Please select a valid rating.';
    }

    if (!hash_equals($csrf_token, $posted_token)) {
        $feedback_errors[] = 'Invalid request token.';
    }

    if (empty($feedback_errors)) {
        $stmt = $conn->prepare("INSERT INTO feedback (user_id, feedback, rating, status) VALUES (?, ?, ?, 'submitted')");
        if ($stmt) {
            $stmt->bind_param("isi", $user_id, $feedback, $rating);
            if ($stmt->execute()) {
                $feedback_success = 'Thanks for your feedback. Our team will review it before publishing.';
            } else {
                $feedback_errors[] = 'Failed to submit feedback. Please try again.';
            }
            $stmt->close();
        } else {
            $feedback_errors[] = 'Failed to prepare feedback submission.';
        }
    }

    if ($is_ajax_feedback) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'ok' => empty($feedback_errors),
            'message' => empty($feedback_errors) ? $feedback_success : implode(' ', $feedback_errors),
            'errors' => $feedback_errors
        ]);
        $conn->close();
        exit();
    }
}

// Get user statistics
$user_stats = [];

$stmt = $conn->prepare("SELECT COUNT(*) as total_orders FROM orders WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_stats['total_orders'] = (int)$stmt->get_result()->fetch_assoc()['total_orders'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as completed_orders FROM orders WHERE user_id = ? AND status = 'completed'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_stats['completed_orders'] = (int)$stmt->get_result()->fetch_assoc()['completed_orders'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as pending_orders FROM orders WHERE user_id = ? AND status = 'pending'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_stats['pending_orders'] = (int)$stmt->get_result()->fetch_assoc()['pending_orders'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as in_progress_orders FROM orders WHERE user_id = ? AND status = 'in_progress'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_stats['in_progress_orders'] = (int)$stmt->get_result()->fetch_assoc()['in_progress_orders'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total_reviews FROM reviews WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_stats['total_reviews'] = (int)$stmt->get_result()->fetch_assoc()['total_reviews'];
$stmt->close();

$stmt = $conn->prepare("SELECT AVG(rating) as avg_rating FROM reviews WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$user_stats['avg_rating'] = $result['avg_rating'] ? round((float)$result['avg_rating'], 1) : 0;
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total_favorites FROM favorites WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_stats['total_favorites'] = (int)$stmt->get_result()->fetch_assoc()['total_favorites'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as unread_notifications FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_stats['unread_notifications'] = (int)$stmt->get_result()->fetch_assoc()['unread_notifications'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total_support FROM support_messages WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_stats['total_support'] = (int)$stmt->get_result()->fetch_assoc()['total_support'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS submitted_feedback FROM feedback WHERE user_id = ? AND status = 'submitted'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_stats['submitted_feedback'] = (int)$stmt->get_result()->fetch_assoc()['submitted_feedback'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS approved_feedback FROM feedback WHERE user_id = ? AND status = 'approved'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_stats['approved_feedback'] = (int)$stmt->get_result()->fetch_assoc()['approved_feedback'];
$stmt->close();

// Recent activity
$recent_activity = [];
$stmt = $conn->prepare("
    (SELECT 'order' as type, id, customization as description, created_at, status as extra_info FROM orders WHERE user_id = ?)
    UNION ALL
    (SELECT 'review' as type, r.id, CONCAT('Reviewed order #', r.order_id) as description, r.created_at, CONCAT(r.rating, '/5') as extra_info FROM reviews r WHERE r.user_id = ?)
    UNION ALL
    (SELECT 'favorite' as type, f.id, CONCAT('Added to favorites: ', f.customization_name) as description, f.created_at, '' as extra_info FROM favorites f WHERE f.user_id = ?)
    ORDER BY created_at DESC LIMIT 10
");
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$activity_result = $stmt->get_result();
while ($activity = $activity_result->fetch_assoc()) {
    $recent_activity[] = $activity;
}
$stmt->close();

// Latest approved feedback for a quick preview
$latest_reviews = [];
$stmt = $conn->prepare("SELECT feedback, rating, created_at FROM feedback WHERE user_id = ? AND status = 'approved' ORDER BY created_at DESC LIMIT 2");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$reviews_result = $stmt->get_result();
while ($review = $reviews_result->fetch_assoc()) {
    $latest_reviews[] = $review;
}
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - RBJ Accessories</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<style>
body {
  min-height: 100vh;
  background: var(--rbj-bg, linear-gradient(135deg,#1b1b1b,#111));
  color: var(--rbj-text, #fff);
  padding-top: 100px;
  font-family: "Montserrat", sans-serif;
}

.navbar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 50px;
  background: rgba(0,0,0,0.8);
  z-index: 999;
}

.navbar .logo {
  display: flex;
  align-items: center;
  gap: 10px;
  color: inherit;
  text-decoration: none;
  font-size: 22px;
  font-weight: 700;
}

.navbar .logo img { height: 60px; width:auto; }
.navbar .nav-links { display:flex; align-items:center; gap:15px; }
.navbar .nav-links a { color: inherit; text-decoration:none; font-weight:500; margin-left:15px; }
.navbar .nav-links a:hover { text-decoration:underline; }

.account-dropdown { display:flex; align-items:center; margin-left:15px; position: relative; }
.account-icon {
  width:40px;
  height:40px;
  background: linear-gradient(135deg, var(--rbj-accent, #d90429), var(--rbj-accent-strong, #ef233c));
  border-radius:50%;
  display:flex;
  justify-content:center;
  align-items:center;
  font-weight:bold;
  margin-right:5px;
}

.account-username { font-weight:600; color:inherit; }
.account-trigger { background:none; border:none; color:inherit; display:flex; align-items:center; gap:8px; cursor:pointer; }
.account-menu {
  position:absolute;
  top:110%;
  right:0;
  background:var(--rbj-menu-bg, #1e1e1e);
  border-radius:10px;
  min-width:200px;
  padding:8px 0;
  display:none;
  box-shadow:0 10px 30px rgba(0,0,0,0.4);
  z-index:999;
}

.account-menu a { display:flex; align-items:center; gap:10px; padding:10px 15px; color:inherit; text-decoration:none; font-size:14px; }
.account-menu a:hover { background:rgba(255,255,255,0.08); }
.account-dropdown.active .account-menu { display:block; }

.wrapper {
  max-width: 1400px;
  margin: auto;
  padding: 20px;
}

.wrapper h1 {
  text-align: center;
  margin-bottom: 30px;
  font-size: 36px;
  color: var(--rbj-accent-strong, #ef233c);
}

.stats-grid {
  display:grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap:20px;
  margin-bottom:40px;
}

.stat-card {
  background: var(--rbj-surface, rgba(0,0,0,0.6));
  border-radius: 15px;
  padding: 25px;
  text-align: center;
  border: 1px solid var(--rbj-border, rgba(255,255,255,0.1));
  transition: transform 0.3s ease, box-shadow 0.3s ease;
  text-decoration: none;
  color: inherit;
  display: block;
}

.stat-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 14px 28px rgba(0,0,0,0.18);
  cursor: pointer;
}

.stat-card .icon { font-size: 46px; margin-bottom: 12px; opacity: 0.9; }
.stat-card .icon.orders { color: #3498db; }
.stat-card .icon.completed { color: #2ecc71; }
.stat-card .icon.pending { color: #f39c12; }
.stat-card .icon.reviews { color: #ef233c; }
.stat-card .icon.favorites { color: #9b59b6; }
.stat-card .icon.notifications { color: #e67e22; }
.stat-card .icon.support { color: #1abc9c; }
.stat-card .icon.feedback { color: #ff7f50; }

.stat-card h3 {
  margin: 0 0 10px;
  font-size: 13px;
  opacity: 0.9;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.stat-card .number { font-size: 34px; font-weight: 700; margin-bottom: 5px; }
.stat-card .label { font-size: 12px; opacity: 0.75; }

.dashboard-content {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 30px;
}

.recent-activity,
.quick-actions,
.feedback-card,
.welcome-message {
  background: var(--rbj-surface, rgba(0,0,0,0.6));
  border-radius: 15px;
  padding: 25px;
  border: 1px solid var(--rbj-border, rgba(255,255,255,0.1));
}

.recent-activity h2,
.quick-actions h2,
.feedback-card h2,
.welcome-message h2 {
  color: var(--rbj-accent-strong, #ef233c);
  margin-bottom: 16px;
  font-size: 24px;
}

.welcome-message {
  margin-bottom: 30px;
  text-align: center;
  background: linear-gradient(120deg, rgba(217,4,41,0.13), rgba(217,4,41,0.1));
}

.welcome-message p {
  color: var(--rbj-muted, rgba(255,255,255,0.82));
}

.activity-item {
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 15px 0;
  border-bottom: 1px solid var(--rbj-border, rgba(255,255,255,0.1));
}

.activity-item:last-child { border-bottom: none; }

.activity-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
}

.activity-icon.order { background: rgba(52, 152, 219, 0.2); color: #3498db; }
.activity-icon.review { background: rgba(231, 76, 60, 0.2); color: #ef233c; }
.activity-icon.favorite { background: rgba(155, 89, 182, 0.2); color: #9b59b6; }

.activity-content { flex: 1; }
.activity-content .title { font-weight: 600; margin-bottom: 5px; }
.activity-content .description { font-size: 14px; opacity: 0.82; }
.activity-content .time { font-size: 12px; opacity: 0.62; }
.activity-extra { font-size: 12px; color: var(--rbj-accent-strong, #ef233c); font-weight: 600; }

.quick-actions {
  height: fit-content;
}

.feedback-card {
  margin-top: 18px;
}

.feedback-meta {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
  margin-bottom: 14px;
}

.meta-box {
  border: 1px solid var(--rbj-border, rgba(255,255,255,0.2));
  border-radius: 10px;
  padding: 10px;
  text-align: center;
}

.meta-box strong {
  display: block;
  font-size: 20px;
  color: var(--rbj-accent-strong, #ef233c);
}

.meta-box span {
  font-size: 12px;
  opacity: 0.8;
}

.feedback-form {
  display: grid;
  gap: 10px;
}

.feedback-form textarea,
.feedback-form select {
  width: 100%;
  border-radius: 10px;
  border: 1px solid var(--rbj-border, rgba(255,255,255,0.2));
  background: rgba(255,255,255,0.04);
  color: inherit;
  padding: 12px;
  font-family: inherit;
  font-size: 14px;
}

.feedback-form textarea {
  min-height: 100px;
  resize: vertical;
}

.feedback-form textarea::placeholder {
  color: var(--rbj-muted, rgba(255,255,255,0.7));
}

.feedback-form button,
.action-btn {
  display: block;
  width: 100%;
  padding: 13px;
  margin-bottom: 10px;
  background: linear-gradient(45deg, var(--rbj-accent, #d90429), var(--rbj-accent-strong, #ef233c));
  color: #fff;
  border: none;
  border-radius: 10px;
  text-decoration: none;
  text-align: center;
  font-weight: 600;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  cursor: pointer;
}

.feedback-form button:hover,
.action-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(217,4,41,0.35);
}

.feedback-form button:disabled {
  opacity: 0.7;
  cursor: not-allowed;
  transform: none;
}

.action-btn.secondary {
  background: rgba(255,255,255,0.08);
  color: inherit;
  border: 1px solid var(--rbj-border, rgba(255,255,255,0.35));
}

.action-btn.secondary:hover {
  background: rgba(255,255,255,0.16);
}

.feedback-note {
  margin: 6px 0 12px;
  font-size: 12px;
  color: var(--rbj-muted, rgba(255,255,255,0.75));
}

.feedback-list {
  margin-top: 14px;
  display: grid;
  gap: 10px;
}

.feedback-item {
  border: 1px solid var(--rbj-border, rgba(255,255,255,0.2));
  border-radius: 10px;
  padding: 10px;
  background: rgba(255,255,255,0.03);
}

.feedback-item p {
  font-size: 13px;
  margin-bottom: 6px;
}

.feedback-item small {
  opacity: 0.78;
}

.feedback-response {
  margin-bottom: 10px;
}

.alert {
  padding: 10px 12px;
  border-radius: 8px;
  font-size: 13px;
}

.alert.error {
  background: rgba(192,57,43,0.18);
  border: 1px solid rgba(231,76,60,0.35);
}

.alert.success {
  background: rgba(39,174,96,0.16);
  border: 1px solid rgba(39,174,96,0.35);
}

.side-column {
  display: flex;
  flex-direction: column;
}

html[data-theme="light"] .feedback-form textarea,
html[data-theme="light"] .feedback-form select {
  background: #fff;
}

html[data-theme="light"] .feedback-item {
  background: rgba(255,255,255,0.75);
}

@media (max-width: 992px) {
  .dashboard-content { grid-template-columns: 1fr; }
  .side-column { order: 2; }
}

@media (max-width: 768px) {
  .stats-grid { grid-template-columns: repeat(2,1fr); }
  .navbar { padding: 10px 20px; }
  .wrapper h1 { font-size: 30px; }
}

@media (max-width: 520px) {
  .stats-grid { grid-template-columns: 1fr; }
  .feedback-meta { grid-template-columns: 1fr; }
}
</style>
<?php include __DIR__ . '/partials/user_navbar_theme.php'; ?>
</head>
<body>

<nav class="navbar">
  <a href="index.php" class="logo">
    <img src="../rbjlogo.png" alt="RBJ Accessories Logo">
    <span>RBJ Accessories</span>
  </a>
  <div class="nav-links">
    <a href="index.php">Home</a>
    <a href="catalog.php">Shop</a>
    <a href="customize.php">Customize</a>
    <a href="cart.php" class="nav-cart-link" title="Cart" aria-label="Cart"><i class='bx bx-cart'></i></a>
    <?php include __DIR__ . '/partials/account_menu.php'; ?>
  </div>
</nav>

<div class="wrapper">
  <h1>My Dashboard</h1>

  <div class="welcome-message">
    <h2>Welcome back, <?php echo htmlspecialchars($dashboard_username); ?>!</h2>
    <p>Track your RBJ orders, favorites, feedback, and support updates in one place.</p>
  </div>

  <div class="stats-grid">
    <a href="orders.php" class="stat-card">
      <div class="icon orders"><i class='bx bx-receipt'></i></div>
      <h3>Total Orders</h3>
      <div class="number"><?php echo $user_stats['total_orders']; ?></div>
      <div class="label">All time</div>
    </a>

    <a href="orders.php?status=completed" class="stat-card">
      <div class="icon completed"><i class='bx bx-check-circle'></i></div>
      <h3>Completed</h3>
      <div class="number"><?php echo $user_stats['completed_orders']; ?></div>
      <div class="label">Orders finished</div>
    </a>

    <a href="orders.php?status=in_progress" class="stat-card">
      <div class="icon pending"><i class='bx bx-time'></i></div>
      <h3>In Progress</h3>
      <div class="number"><?php echo $user_stats['in_progress_orders']; ?></div>
      <div class="label">Being worked on</div>
    </a>

    <a href="reviews.php" class="stat-card">
      <div class="icon reviews"><i class='bx bx-star'></i></div>
      <h3>My Reviews</h3>
      <div class="number"><?php echo $user_stats['total_reviews']; ?></div>
      <div class="label">Average: <?php echo $user_stats['avg_rating']; ?>/5</div>
    </a>

    <a href="favorites.php" class="stat-card">
      <div class="icon favorites"><i class='bx bx-heart'></i></div>
      <h3>Favorites</h3>
      <div class="number"><?php echo $user_stats['total_favorites']; ?></div>
      <div class="label">Saved items</div>
    </a>

    <a href="notifications.php" class="stat-card">
      <div class="icon notifications"><i class='bx bx-bell'></i></div>
      <h3>Notifications</h3>
      <div class="number"><?php echo $user_stats['unread_notifications']; ?></div>
      <div class="label">Unread messages</div>
    </a>

    <a href="support.php" class="stat-card">
      <div class="icon support"><i class='bx bx-support'></i></div>
      <h3>Support</h3>
      <div class="number"><?php echo $user_stats['total_support']; ?></div>
      <div class="label">Messages sent</div>
    </a>

    <a href="feedback_history.php" class="stat-card">
      <div class="icon feedback"><i class='bx bx-message-square-dots'></i></div>
      <h3>Feedback</h3>
      <div class="number"><?php echo $user_stats['approved_feedback']; ?></div>
      <div class="label">Approved reviews</div>
    </a>
  </div>

  <div class="dashboard-content">
    <div class="recent-activity">
      <h2>Recent Activity</h2>
      <?php if (empty($recent_activity)): ?>
        <p style="text-align: center; opacity: 0.7; padding: 40px;">No recent activity yet. Start customizing your next RBJ build.</p>
      <?php else: ?>
        <?php foreach ($recent_activity as $activity): ?>
          <div class="activity-item">
            <div class="activity-icon <?php echo htmlspecialchars($activity['type']); ?>">
              <?php
              switch ($activity['type']) {
                case 'order':
                    echo '<i class="bx bx-receipt"></i>';
                    break;
                case 'review':
                    echo '<i class="bx bx-star"></i>';
                    break;
                case 'favorite':
                    echo '<i class="bx bx-heart"></i>';
                    break;
                default:
                    echo '<i class="bx bx-info-circle"></i>';
              }
              ?>
            </div>
            <div class="activity-content">
              <div class="title"><?php echo ucfirst($activity['type']); ?> #<?php echo (int)$activity['id']; ?></div>
              <div class="description"><?php echo htmlspecialchars($activity['description']); ?></div>
              <div class="time"><?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?></div>
            </div>
            <?php if (!empty($activity['extra_info'])): ?>
              <div class="activity-extra"><?php echo htmlspecialchars($activity['extra_info']); ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="side-column">
      <div class="quick-actions">
        <h2>Quick Actions</h2>
        <a href="customize.php" class="action-btn">Start Customizing</a>
        <a href="catalog.php" class="action-btn">Browse Shop</a>
        <a href="cart.php" class="action-btn secondary">View Cart</a>
        <a href="orders.php" class="action-btn secondary">Track Orders</a>
        <a href="support.php" class="action-btn secondary">Get Support</a>
        <a href="account_info.php" class="action-btn secondary">Update Profile</a>
      </div>

      <div class="feedback-card" id="dashboardFeedback">
        <h2>Feedback Center</h2>

        <div class="feedback-meta">
          <div class="meta-box">
            <strong><?php echo $user_stats['submitted_feedback']; ?></strong>
            <span>Under Review</span>
          </div>
          <div class="meta-box">
            <strong><?php echo $user_stats['approved_feedback']; ?></strong>
            <span>Approved</span>
          </div>
        </div>

        <div id="dashboardFeedbackResponse" class="feedback-response">
          <?php if (!empty($feedback_errors)): ?>
            <div class="alert error"><?php echo htmlspecialchars(implode(' ', $feedback_errors)); ?></div>
          <?php elseif ($feedback_success !== ''): ?>
            <div class="alert success"><?php echo htmlspecialchars($feedback_success); ?></div>
          <?php endif; ?>
        </div>

        <form method="POST" action="dashboard.php" class="feedback-form" id="dashboardFeedbackForm">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
          <input type="hidden" name="submit_dashboard_feedback" value="1">
          <textarea name="feedback" placeholder="Tell us about your RBJ experience..." required></textarea>
          <select name="rating" required>
            <option value="">Rate your experience</option>
            <option value="5">★★★★★ Excellent (5 stars)</option>
            <option value="4">★★★★☆ Very Good (4 stars)</option>
            <option value="3">★★★☆☆ Good (3 stars)</option>
            <option value="2">★★☆☆☆ Fair (2 stars)</option>
            <option value="1">★☆☆☆☆ Poor (1 star)</option>
          </select>
          <button type="submit">Submit Feedback</button>
        </form>

        <p class="feedback-note">Your feedback is reviewed by RBJ staff before public posting.</p>

        <?php if (!empty($latest_reviews)): ?>
          <div class="feedback-list">
            <?php foreach ($latest_reviews as $review): ?>
              <div class="feedback-item">
                <p>"<?php echo htmlspecialchars($review['feedback']); ?>"</p>
                <small><?php echo str_repeat('★', (int)$review['rating']) . str_repeat('☆', 5 - (int)$review['rating']); ?> • <?php echo date('M j, Y', strtotime($review['created_at'])); ?></small>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <a href="feedback_history.php" class="action-btn secondary" style="margin-top:12px;">View Feedback History</a>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var form = document.getElementById('dashboardFeedbackForm');
  var responseWrap = document.getElementById('dashboardFeedbackResponse');

  function setResponse(type, message) {
    if (!responseWrap) return;
    if (!message) {
      responseWrap.innerHTML = '';
      return;
    }

    var div = document.createElement('div');
    div.className = 'alert ' + (type === 'success' ? 'success' : 'error');
    div.textContent = message;
    responseWrap.innerHTML = '';
    responseWrap.appendChild(div);
  }

  if (!form) return;

  form.addEventListener('submit', async function (e) {
    e.preventDefault();

    var submitBtn = form.querySelector('button[type="submit"]');
    var originalText = submitBtn ? submitBtn.textContent : '';
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Submitting...';
    }

    try {
      var formData = new FormData(form);
      var res = await fetch('dashboard.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      if (!res.ok) throw new Error('Request failed');
      var data = await res.json();

      if (data.ok) {
        setResponse('success', data.message || 'Feedback submitted successfully.');
        form.reset();
      } else {
        setResponse('error', data.message || 'Unable to submit feedback right now.');
      }
    } catch (err) {
      setResponse('error', 'Could not submit feedback right now. Please try again.');
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
      }
    }
  });
})();
</script>

<?php include __DIR__ . '/partials/user_footer.php'; ?>

</body>
</html>




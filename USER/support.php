<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle support message submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_support'])) {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf_token, $posted_token)) {
        http_response_code(400);
        die("Invalid request token.");
    }
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    $priority = $_POST['priority'];

    if (!empty($subject) && !empty($message)) {
        $stmt = $conn->prepare("INSERT INTO support_messages (user_id, subject, message, priority) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $subject, $message, $priority);

        if ($stmt->execute()) {
            $success = "Support request submitted successfully! We'll get back to you soon.";
        } else {
            $error = "Failed to submit support request. Please try again.";
        }
        $stmt->close();
    } else {
        $error = "Please fill in all required fields.";
    }
}

// Fetch user's support messages
$stmt = $conn->prepare("SELECT * FROM support_messages WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$support_messages = $stmt->get_result();
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Support - RBJ Accessories</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<style>
* { box-sizing: border-box; }
body { min-height:100vh; background: linear-gradient(135deg,#1b1b1b,#111); color:white; padding-top:100px; font-family:"Montserrat",sans-serif; }
.navbar { position:fixed; top:0; left:0; right:0; display:flex; justify-content:space-between; align-items:center; padding:10px 50px; background: rgba(0,0,0,0.8); z-index:999; }
.navbar .logo { display:flex; align-items:center; gap:10px; color:white; text-decoration:none; font-size:22px; font-weight:700; }
.navbar .logo img { height: 60px; width:auto; }
.navbar .nav-links { display:flex; align-items:center; gap:15px; }
.navbar .nav-links a { color:white; text-decoration:none; font-weight:500; margin-left:15px; }
.navbar .nav-links a:hover { text-decoration:underline; }

.account-dropdown { display:flex; align-items:center; margin-left:15px; }
.account-icon { width:40px; height:40px; background:#27ae60; border-radius:50%; display:flex; justify-content:center; align-items:center; font-weight:bold; margin-right:5px; }
.account-username { font-weight:600; color:white; }

.account-dropdown { position: relative; display: flex; align-items: center; }
.account-trigger { background: none; border: none; color: white; display: flex; align-items: center; gap: 8px; cursor: pointer; }
.account-menu { position: absolute; top: 110%; right: 0; background: #1e1e1e; border-radius: 10px; min-width: 200px; padding: 8px 0; display: none; box-shadow: 0 10px 30px rgba(0,0,0,0.4); z-index: 999; }
.account-menu a { display: flex; align-items: center; gap: 10px; padding: 10px 15px; color: white; text-decoration: none; font-size: 14px; }
.account-menu a:hover { background: rgba(255,255,255,0.08); }
.account-dropdown.active .account-menu { display: block; }

.wrapper { max-width:1080px; margin:auto; padding:24px 20px 34px; }
.wrapper h1 { text-align:center; margin-bottom:10px; font-size:34px; letter-spacing:0.2px; }
.support-subtitle { text-align:center; margin-bottom:24px; color: rgba(255,255,255,0.75); font-size:14px; }

.support-form {
  background: rgba(0,0,0,0.6);
  padding: 24px 22px;
  border-radius: 12px;
  margin-bottom: 28px;
  border: 1px solid rgba(255,255,255,0.12);
}
.support-form h2 { text-align: center; margin-bottom: 8px; color: #fff; font-size: 24px; }
.support-form .hint { text-align: center; margin: 0 0 16px; font-size: 13px; color: rgba(255,255,255,0.72); }
.support-form form { max-width: 760px; margin: 0 auto; }
.input-box { position: relative; width: 100%; margin: 14px 0; }
.input-box input,
.input-box select,
.input-box textarea {
  display: block;
  width: 100%;
  max-width: 100%;
  background: transparent;
  border: 2px solid rgba(255,255,255,0.45);
  font-size: 15px;
  color: white;
}
.input-box input,
.input-box select {
  height: 50px;
  border-radius: 14px;
  padding: 0 14px;
}
.input-box select {
  appearance: none;
  background-image: linear-gradient(45deg, transparent 50%, rgba(255,255,255,0.72) 50%), linear-gradient(135deg, rgba(255,255,255,0.72) 50%, transparent 50%);
  background-position: calc(100% - 20px) calc(50% - 3px), calc(100% - 14px) calc(50% - 3px);
  background-size: 6px 6px, 6px 6px;
  background-repeat: no-repeat;
  padding-right: 36px;
}
.input-box textarea {
  min-height: 140px;
  border-radius: 12px;
  padding: 12px 14px;
  line-height: 1.55;
  resize: vertical;
}
.input-box input::placeholder,
.input-box textarea::placeholder { color: rgba(255,255,255,0.7); }
.btn {
  width: 100%;
  height: 46px;
  background: linear-gradient(45deg, #d90429, #ef233c);
  color: #fff;
  border: none;
  border-radius: 999px;
  font-weight: 600;
  cursor: pointer;
  margin-top: 8px;
}
.btn:hover { filter: brightness(1.05); }
.success { color: #27ae60; text-align: center; margin-bottom: 20px; }
.error { color: #ef233c; text-align: center; margin-bottom: 20px; }

.messages-list { display: grid; gap: 20px; }
.message-card { background: rgba(0,0,0,0.6); border-radius: 12px; padding: 18px; border: 1px solid rgba(255,255,255,0.1); }
.message-card h3 { margin-bottom: 10px; color: #ef233c; line-height: 1.3; }
.message-card .status { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-bottom: 10px; }
.message-card .status.open { background: #ef233c; color: white; }
.message-card .status.in_progress { background: #f39c12; color: white; }
.message-card .status.resolved { background: #27ae60; color: white; }
.message-card .status.closed { background: #95a5a6; color: white; }
.message-card .priority { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; margin-left: 10px; }
.message-card .priority.low { background: #27ae60; color: white; }
.message-card .priority.medium { background: #f39c12; color: white; }
.message-card .priority.high { background: #e67e22; color: white; }
.message-card .priority.urgent { background: #ef233c; color: white; }
.message-card p { margin: 8px 0; font-size: 14px; }
.message-card .date { color: rgba(255,255,255,0.7); font-size: 12px; }
.message-card .admin-response { background: rgba(39,174,96,0.1); border-left: 3px solid #27ae60; padding: 15px; margin-top: 15px; }

.empty-messages { text-align: center; padding: 50px; color: rgba(255,255,255,0.7); }
.empty-messages i { font-size: 48px; margin-bottom: 20px; display: block; }

html[data-theme="light"] .support-subtitle,
html[data-theme="light"] .support-form .hint,
html[data-theme="light"] .empty-messages,
html[data-theme="light"] .message-card .date {
  color: var(--rbj-muted, #9f4b43);
}

html[data-theme="light"] .support-form,
html[data-theme="light"] .message-card {
  background: var(--rbj-surface, rgba(255,255,255,0.92));
  border-color: var(--rbj-border, rgba(217,4,41,0.22));
  color: var(--rbj-text, #7a211b);
}

html[data-theme="light"] .support-form h2,
html[data-theme="light"] .message-card h3 {
  color: var(--rbj-accent-strong, #ef233c);
}

html[data-theme="light"] .input-box input,
html[data-theme="light"] .input-box select,
html[data-theme="light"] .input-box textarea {
  background: #fff;
  color: var(--rbj-text, #7a211b);
  border-color: var(--rbj-border, rgba(217,4,41,0.22));
}

html[data-theme="light"] .input-box input::placeholder,
html[data-theme="light"] .input-box textarea::placeholder {
  color: #ad6a65;
}

html[data-theme="light"] .input-box select {
  background-image: linear-gradient(45deg, transparent 50%, #a14a43 50%), linear-gradient(135deg, #a14a43 50%, transparent 50%);
}

html[data-theme="light"] .message-card .admin-response {
  background: #fff3f0;
  border-left-color: #ef233c;
}

@media(max-width:768px){
  .messages-list{grid-template-columns:1fr;}
  .navbar{padding:10px 20px;}
  .wrapper { padding: 20px 12px 28px; }
  .support-form { padding: 18px 14px; }
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
  <h1>Support Center</h1>
  <p class="support-subtitle">Need help with your order or customization? Send us a clear request and we'll respond as soon as possible.</p>

  <!-- Support Form -->
  <div class="support-form">
    <h2>Submit a Support Request</h2>
    <p class="hint">Provide a clear subject, set your priority, and explain the concern in detail.</p>

    <?php if (isset($success)): ?>
      <p class="success"><?php echo $success; ?></p>
    <?php endif; ?>

    <?php if (isset($error)): ?>
      <p class="error"><?php echo $error; ?></p>
    <?php endif; ?>

    <form method="POST" action="support.php">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
      <div class="input-box">
        <input type="text" name="subject" placeholder="Subject" required>
      </div>

      <div class="input-box">
        <select name="priority" required>
          <option value="low">Low Priority</option>
          <option value="medium" selected>Medium Priority</option>
          <option value="high">High Priority</option>
          <option value="urgent">Urgent</option>
        </select>
      </div>

      <div class="input-box">
        <textarea name="message" placeholder="Describe your issue or question in detail..." required></textarea>
      </div>

      <button type="submit" name="submit_support" class="btn">Submit Request</button>
    </form>
  </div>

  <!-- Support Messages -->
  <h2>My Support Requests</h2>
  <?php if ($support_messages->num_rows > 0): ?>
    <div class="messages-list">
      <?php while ($message = $support_messages->fetch_assoc()): ?>
        <div class="message-card">
          <h3><?php echo htmlspecialchars($message['subject']); ?></h3>
          <span class="status <?php echo $message['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $message['status'])); ?></span>
          <span class="priority <?php echo $message['priority']; ?>"><?php echo ucfirst($message['priority']); ?></span>
          <p><strong>Your Message:</strong></p>
          <p><?php echo htmlspecialchars($message['message']); ?></p>
          <p class="date">Submitted: <?php echo date('M j, Y g:i A', strtotime($message['created_at'])); ?></p>

          <?php if (!empty($message['admin_response'])): ?>
            <div class="admin-response">
              <p><strong>Admin Response:</strong></p>
              <p><?php echo htmlspecialchars($message['admin_response']); ?></p>
              <p class="date">Responded: <?php echo date('M j, Y g:i A', strtotime($message['updated_at'])); ?></p>
            </div>
          <?php endif; ?>
        </div>
      <?php endwhile; ?>
    </div>
  <?php else: ?>
    <div class="empty-messages">
      <i class='bx bx-support'></i>
      <p>No support requests yet. Submit a request above if you need assistance!</p>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/partials/user_footer.php'; ?>

</body>
</html>












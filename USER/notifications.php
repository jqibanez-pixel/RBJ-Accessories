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

// Get user's notifications
$stmt = $conn->prepare("SELECT id, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications_result = $stmt->get_result();

$notifications = [];
while ($row = $notifications_result->fetch_assoc()) {
    $notifications[] = $row;
}

$stmt->close();

// Mark notifications as read after fetching so unread state can still be shown on first load.
$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->close();

function getNotificationRedirect(string $message): string
{
    $messageLower = strtolower($message);
    $orderId = null;

    if (preg_match('/order\s*#\s*(\d+)/i', $message, $match)) {
        $orderId = (int)$match[1];
    }

    if ($orderId !== null) {
        if (
            strpos($messageLower, 'completed') !== false ||
            strpos($messageLower, 'in progress') !== false ||
            strpos($messageLower, 'pending') !== false ||
            strpos($messageLower, 'pickup') !== false ||
            strpos($messageLower, 'estimated completion') !== false
        ) {
            return 'order_tracking.php?order_id=' . $orderId;
        }

        return 'orders.php?order_id=' . $orderId;
    }

    if (strpos($messageLower, 'feedback') !== false) {
        return 'feedback_history.php';
    }

    if (strpos($messageLower, 'review') !== false) {
        return 'reviews.php';
    }

    if (strpos($messageLower, 'welcome') !== false || strpos($messageLower, 'customiz') !== false) {
        return 'customize.php';
    }

    return 'dashboard.php';
}

function getNotificationTypeMeta(string $message): array
{
    $messageLower = strtolower($message);

    if (preg_match('/order\s*#\s*\d+/i', $message)) {
        return ['type' => 'order', 'icon' => 'bx-package', 'label' => 'Order Update'];
    }

    if (strpos($messageLower, 'feedback') !== false) {
        return ['type' => 'feedback', 'icon' => 'bx-message-dots', 'label' => 'Feedback'];
    }

    if (strpos($messageLower, 'review') !== false) {
        return ['type' => 'review', 'icon' => 'bx-star', 'label' => 'Review'];
    }

    if (strpos($messageLower, 'welcome') !== false || strpos($messageLower, 'customiz') !== false) {
        return ['type' => 'welcome', 'icon' => 'bx-party', 'label' => 'Welcome'];
    }

    return ['type' => 'general', 'icon' => 'bx-bell', 'label' => 'Notification'];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications - RBJ Accessories</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<style>
body { min-height:100vh; background: linear-gradient(135deg,#1b1b1b,#111); color:white; padding-top:100px; font-family:"Montserrat",sans-serif; }
.navbar { position:fixed; top:0; left:0; right:0; display:flex; justify-content:space-between; align-items:center; padding:10px 50px; background: rgba(0,0,0,0.8); z-index:999; }
.navbar .logo { display:flex; align-items:center; gap:10px; color:white; text-decoration:none; font-size:22px; font-weight:700; }
.navbar .logo img { height: 60px; width:auto; }
.navbar .nav-links { display:flex; align-items:center; gap:15px; }
.navbar .nav-links a { color:white; text-decoration:none; font-weight:500; margin-left:15px; }
.navbar .nav-links a:hover { text-decoration:underline; }

/* Account icon in navbar */
.account-dropdown { display:flex; align-items:center; margin-left:15px; }
.account-icon { width:40px; height:40px; background:#27ae60; border-radius:50%; display:flex; justify-content:center; align-items:center; font-weight:bold; margin-right:5px; }
.account-username { font-weight:600; color:white; }

/* ===== ACCOUNT DROPDOWN (FIX) ===== */
.account-dropdown {
  position: relative;
  display: flex;
  align-items: center;
}

.account-trigger {
  background: none;
  border: none;
  color: white;
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}

.account-icon {
  width: 40px;
  height: 40px;
  background: #27ae60;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
}

.account-menu {
  position: absolute;
  top: 110%;
  right: 0;
  background: #1e1e1e;
  border-radius: 10px;
  min-width: 200px;
  padding: 8px 0;
  display: none;
  box-shadow: 0 10px 30px rgba(0,0,0,0.4);
  z-index: 999;
}

.account-menu a {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 15px;
  color: white;
  text-decoration: none;
  font-size: 14px;
}

.account-menu a:hover {
  background: rgba(255,255,255,0.08);
}

/* SHOW MENU */
.account-dropdown.active .account-menu {
  display: block;
}

/* Wrapper */
.wrapper { max-width:800px; margin:auto; padding:20px; }
.wrapper h1 { text-align:center; margin-bottom:20px; font-size:32px; }
.notifications ul { list-style:none; }
.notifications li { background: rgba(255,255,255,0.05); margin-bottom:10px; border-radius:10px; font-size:16px; position:relative; overflow:hidden; }
.notifications li.unread { background: rgba(217,4,41, 0.14); border-left:4px solid #ef233c; }
.notifications .notification-link {
  display:flex;
  align-items:flex-start;
  gap:12px;
  padding:12px 15px;
  color:inherit;
  text-decoration:none;
  transition: background 0.2s ease;
}
.notifications .notification-icon {
  width:36px;
  height:36px;
  border-radius:50%;
  display:flex;
  align-items:center;
  justify-content:center;
  flex-shrink:0;
  font-size:18px;
  background: rgba(255,255,255,0.12);
  color:#fff;
}
.notifications .notification-content {
  min-width:0;
}
.notifications .notification-type {
  display:inline-block;
  font-size:11px;
  font-weight:700;
  letter-spacing:.4px;
  text-transform:uppercase;
  color:#ffb3ab;
  margin-bottom:4px;
}
.notifications .notification-link:hover {
  background: rgba(255,255,255,0.06);
}
.notifications .notification-message {
  display:block;
  line-height:1.45;
}
.notifications .date { font-size:12px; color:#888; margin-top:5px; }
.notifications li.type-order .notification-icon { background: rgba(52,152,219,0.25); }
.notifications li.type-feedback .notification-icon { background: rgba(230,126,34,0.25); }
.notifications li.type-review .notification-icon { background: rgba(241,196,15,0.25); }
.notifications li.type-welcome .notification-icon { background: rgba(155,89,182,0.28); }
.notifications li.type-general .notification-icon { background: rgba(255,255,255,0.12); }
.notifications-empty { text-align:center; color:#888; font-size:18px; }

html[data-theme="light"] .notifications li {
  background: var(--rbj-surface, rgba(255,255,255,0.92));
  border: 1px solid var(--rbj-border, rgba(217,4,41, 0.22));
}

html[data-theme="light"] .notifications li.unread {
  background: #fff0ec;
  border-left: 4px solid var(--rbj-accent-strong, #ef233c);
}

html[data-theme="light"] .notifications .notification-link:hover {
  background: #ffeae5;
}

html[data-theme="light"] .notifications .notification-icon {
  color: #fff;
}

html[data-theme="light"] .notifications .notification-message {
  color: var(--rbj-text, #7a211b);
}

html[data-theme="light"] .notifications .notification-type {
  color: var(--rbj-accent-strong, #ef233c);
}

html[data-theme="light"] .notifications .date,
html[data-theme="light"] .notifications-empty {
  color: var(--rbj-muted, #9f4b43);
}

@media(max-width:500px){ .navbar{padding:10px 20px;} .navbar .nav-links a{margin-left:10px;font-size:14px;} }
</style>
<?php include __DIR__ . '/partials/user_navbar_theme.php'; ?>
</head>
<body>

<!-- NAVBAR -->
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
  <h1>Notifications</h1>
  <div class="notifications">
    <?php if (empty($notifications)): ?>
      <p class="notifications-empty">No notifications yet.</p>
    <?php else: ?>
      <ul>
        <?php foreach ($notifications as $notification): ?>
        <?php $targetUrl = getNotificationRedirect((string)$notification['message']); ?>
        <?php $typeMeta = getNotificationTypeMeta((string)$notification['message']); ?>
        <li class="type-<?php echo htmlspecialchars($typeMeta['type']); ?> <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
          <a class="notification-link" href="<?php echo htmlspecialchars($targetUrl); ?>">
            <span class="notification-icon" aria-hidden="true"><i class='bx <?php echo htmlspecialchars($typeMeta['icon']); ?>'></i></span>
            <span class="notification-content">
              <span class="notification-type"><?php echo htmlspecialchars($typeMeta['label']); ?></span>
              <span class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></span>
              <div class="date"><?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?></div>
            </span>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/partials/user_footer.php'; ?>

</body>
</html>












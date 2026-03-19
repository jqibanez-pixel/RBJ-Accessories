<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';

$user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';
$placed_order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$show_placed_message = isset($_GET['placed']) && $_GET['placed'] === '1' && $placed_order_id > 0;

$tab_labels = [
    'all' => 'All',
    'to_pay' => 'To Pay',
    'to_ship' => 'To Ship',
    'to_receive' => 'To Receive',
    'completed' => 'Complete',
    'cancelled' => 'Cancelled'
];

$status_groups = [
    'to_pay' => ['pending', 'to_pay'],
    'to_ship' => ['in_progress', 'to_ship'],
    'to_receive' => ['to_receive'],
    'completed' => ['completed'],
    'cancelled' => ['cancelled']
];

$tab = isset($_GET['tab']) && isset($tab_labels[$_GET['tab']]) ? $_GET['tab'] : 'all';
$filter_statuses = $tab === 'all' ? [] : ($status_groups[$tab] ?? []);

function rbj_resolve_order_status(string $status): array
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

$sql = "
    SELECT o.*,
           COUNT(r.id) as review_count,
           COALESCE(AVG(r.rating), 0) as avg_rating
    FROM orders o
    LEFT JOIN reviews r ON o.id = r.order_id
    WHERE o.user_id = ?
";

$types = "i";
$params = [$user_id];

if (!empty($filter_statuses)) {
    $placeholders = implode(',', array_fill(0, count($filter_statuses), '?'));
    $sql .= " AND o.status IN ($placeholders)";
    $types .= str_repeat("s", count($filter_statuses));
    $params = array_merge($params, $filter_statuses);
}

$sql .= " GROUP BY o.id ORDER BY o.created_at DESC";

$orders = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Orders - MotoFit</title>
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
.wrapper { max-width:1200px; margin:auto; padding:20px; }
.wrapper h1 { text-align:center; margin-bottom:18px; font-size:34px; }

.order-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  justify-content: center;
  margin-bottom: 18px;
}
.order-tab {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 16px;
  border-radius: 999px;
  border: 1px solid rgba(255,255,255,0.2);
  background: rgba(255,255,255,0.06);
  color: rgba(255,255,255,0.9);
  text-decoration: none;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.2px;
}
.order-tab.active {
  background: linear-gradient(45deg, #d90429, #ef233c);
  border-color: transparent;
  color: #fff;
  box-shadow: 0 12px 22px rgba(217,4,41,0.28);
}

.orders-list { display: grid; gap: 18px; }
.order-card {
  background: rgba(0,0,0,0.6);
  border-radius: 14px;
  padding: 18px;
  border: 1px solid rgba(255,255,255,0.08);
  display: grid;
  gap: 12px;
  transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
}
.order-card:hover { transform: translateY(-2px); border-color: rgba(217,4,41,0.35); box-shadow: 0 18px 36px rgba(0,0,0,0.35); }
.order-header { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
.order-id { font-size: 16px; font-weight: 700; letter-spacing: 0.2px; }
.order-date { color: rgba(255,255,255,0.7); font-size: 13px; }
.order-status {
  display: inline-flex;
  align-items: center;
  padding: 6px 12px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.2px;
}
.order-status.to_pay { background: rgba(243,156,18,0.2); color: #f5c65a; }
.order-status.to_ship { background: rgba(52,152,219,0.2); color: #7fc4ff; }
.order-status.to_receive { background: rgba(142,68,173,0.18); color: #d0a3f3; }
.order-status.completed { background: rgba(39,174,96,0.2); color: #53e697; }
.order-status.cancelled { background: rgba(231,76,60,0.18); color: #ff7a8a; }
.order-status.neutral { background: rgba(255,255,255,0.12); color: #fff; }

.order-body { color: rgba(255,255,255,0.82); line-height: 1.6; }
.order-rating { color: #f5c65a; font-size: 13px; }
.order-actions { display: flex; flex-wrap: wrap; gap: 10px; }
.btn { padding: 9px 16px; border-radius: 999px; border: none; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
.btn-primary { background: linear-gradient(45deg, #d90429, #ef233c); color: #fff; }
.btn-secondary { background: rgba(255,255,255,0.1); color: #fff; border: 1px solid rgba(255,255,255,0.25); }
.btn-secondary:hover { background: rgba(255,255,255,0.2); }

/* No orders message */
.no-orders { text-align:center; padding:40px; color:#888; font-size:18px; }
.success-banner { background: rgba(39,174,96,0.15); border: 1px solid rgba(39,174,96,0.45); color: #2ecc71; border-radius: 12px; padding: 12px 15px; margin-bottom: 16px; text-align: center; }

html[data-theme="light"] .order-tab {
  background: #fff;
  color: var(--rbj-text, #7a211b);
  border-color: rgba(217,4,41,0.2);
}
html[data-theme="light"] .order-card {
  background: #fff;
  border-color: rgba(217,4,41,0.18);
  box-shadow: 0 18px 30px rgba(217,4,41,0.12);
}
html[data-theme="light"] .order-date,
html[data-theme="light"] .order-body {
  color: var(--rbj-muted, #9f4b43);
}
html[data-theme="light"] .order-status.neutral {
  background: rgba(217,4,41,0.08);
  color: var(--rbj-text, #7a211b);
}

@media(max-width:768px){
  .navbar{padding:10px 20px;}
  .order-header{flex-direction:column; align-items:flex-start;}
  .order-tabs{justify-content:flex-start;}
}
</style>
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
  <h1>My Orders</h1>
  <div class="order-tabs">
    <?php foreach ($tab_labels as $tab_key => $tab_label): ?>
      <?php
        $tab_query = 'orders.php?tab=' . urlencode($tab_key);
        if ($placed_order_id > 0) {
            $tab_query .= '&order_id=' . $placed_order_id;
        }
        if ($show_placed_message) {
            $tab_query .= '&placed=1';
        }
      ?>
      <a href="<?php echo $tab_query; ?>" class="order-tab <?php echo $tab === $tab_key ? 'active' : ''; ?>">
        <?php echo htmlspecialchars($tab_label); ?>
      </a>
    <?php endforeach; ?>
  </div>
  <?php if ($show_placed_message): ?>
    <div class="success-banner">Order #<?php echo $placed_order_id; ?> placed successfully.</div>
  <?php endif; ?>
  <?php if (empty($orders)): ?>
    <div class="no-orders">
      <p>You haven't placed any orders yet.</p>
      <p><a href="customize.php" style="color:#27ae60;">Start customizing your motorcycle seat!</a></p>
    </div>
  <?php else: ?>
  <div class="orders-list">
    <?php foreach ($orders as $order): ?>
      <?php
        $status = rbj_resolve_order_status((string)$order['status']);
        $preview = trim((string)$order['customization']);
        if (mb_strlen($preview) > 120) {
            $preview = mb_substr($preview, 0, 120) . '...';
        }
      ?>
      <div class="order-card">
        <div class="order-header">
          <div>
            <div class="order-id">Order #<?php echo (int)$order['id']; ?></div>
            <div class="order-date"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></div>
          </div>
          <span class="order-status <?php echo htmlspecialchars($status['class']); ?>">
            <?php echo htmlspecialchars($status['label']); ?>
          </span>
        </div>

        <div class="order-body">
          <?php echo htmlspecialchars($preview); ?>
        </div>

        <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
          <?php if ((float)$order['avg_rating'] > 0): ?>
            <div class="order-rating">
              <i class='bx bx-star'></i>
              <?php echo number_format((float)$order['avg_rating'], 1); ?>
              (<?php echo (int)$order['review_count']; ?> reviews)
            </div>
          <?php endif; ?>
        </div>

        <div class="order-actions">
          <?php if ($status['group'] === 'completed' && (int)$order['review_count'] === 0): ?>
            <a href="reviews.php" class="btn btn-primary"><i class='bx bx-edit'></i> Write Review</a>
          <?php endif; ?>
          <a href="support.php" class="btn btn-secondary"><i class='bx bx-support'></i> Contact Support</a>
          <a href="order_tracking.php?tab=<?php echo urlencode($status['group'] === 'neutral' ? 'all' : $status['group']); ?>&order_id=<?php echo (int)$order['id']; ?>" class="btn btn-secondary"><i class='bx bx-map'></i> Track</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const accountDropdown = document.querySelector('.account-dropdown');
  const accountTrigger = document.querySelector('.account-trigger');
  const accountMenu = document.querySelector('.account-menu');

  if (!accountDropdown || !accountTrigger || !accountMenu) return;

  accountTrigger.addEventListener('click', function (e) {
    e.stopPropagation();
    accountDropdown.classList.toggle('active');
  });

  accountMenu.addEventListener('click', function (e) {
    e.stopPropagation();
  });

  document.addEventListener('click', function () {
    accountDropdown.classList.remove('active');
  });
});
</script>


<?php include __DIR__ . '/partials/user_footer.php'; ?>

</body>
</html>












<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';

$user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';
$user_initial = strtoupper(substr($username, 0, 1));

$feedback_items = [];
$summary = [
    'submitted' => 0,
    'approved' => 0,
    'rejected' => 0
];

$stmt = $conn->prepare("
    SELECT id, feedback, rating, status, created_at
    FROM feedback
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $feedback_items[] = $row;
    $status_key = $row['status'];
    if (isset($summary[$status_key])) {
        $summary[$status_key]++;
    }
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Feedback History - RBJ Accessories</title>
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
.account-dropdown { position: relative; display: flex; align-items: center; margin-left:15px; }
.account-trigger { background: none; border: none; color: white; display: flex; align-items: center; gap: 8px; cursor: pointer; }
.account-icon { width: 40px; height: 40px; background: #27ae60; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }
.account-menu { position: absolute; top: 110%; right: 0; background: #1e1e1e; border-radius: 10px; min-width: 210px; padding: 8px 0; display: none; box-shadow: 0 10px 30px rgba(0,0,0,0.4); z-index: 999; }
.account-menu a { display: flex; align-items: center; gap: 10px; padding: 10px 15px; color: white; text-decoration: none; font-size: 14px; }
.account-menu a:hover { background: rgba(255,255,255,0.08); }
.account-dropdown.active .account-menu { display: block; }
.wrapper { max-width: 1240px; margin: auto; padding: 24px 22px 34px; }
.wrapper h1 { margin-bottom: 8px; text-align: center; font-size: 36px; letter-spacing: 0.2px; }
.page-subtitle { text-align: center; margin: 0 0 22px; color: rgba(255,255,255,0.74); font-size: 14px; }
.summary-grid { display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:16px; margin-bottom:22px; }
.summary-card {
  background: rgba(255,255,255,0.07);
  border:1px solid rgba(255,255,255,0.14);
  border-radius:14px;
  padding:20px 18px;
  min-height: 120px;
  display: flex;
  flex-direction: column;
  justify-content: center;
}
.summary-card h3 { font-size: 14px; color: #d2d2d2; margin-bottom: 10px; font-weight: 600; letter-spacing: .2px; }
.summary-card .num { font-size: 38px; font-weight: 700; line-height: 1; }
.summary-card.submitted .num { color: #f1c40f; }
.summary-card.approved .num { color: #2ecc71; }
.summary-card.rejected .num { color: #ef233c; }
.feedback-list { display:grid; gap:14px; }
.feedback-item {
  background: rgba(0,0,0,0.55);
  border:1px solid rgba(255,255,255,0.1);
  border-radius:14px;
  padding:18px 18px 16px;
}
.feedback-top { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:10px; }
.rating { color:#f1c40f; font-size: 18px; letter-spacing: 1px; line-height: 1; }
.status-pill { padding:6px 11px; border-radius:999px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; }
.status-pill.submitted { background:#f1c40f; color:#2d2d2d; }
.status-pill.approved { background:#2ecc71; color:white; }
.status-pill.rejected { background:#ef233c; color:white; }
.feedback-text { color:#e8e8e8; line-height:1.72; margin: 4px 0 0; font-size: 15px; }
.feedback-date { color:#b8b8b8; font-size:12px; margin-top:12px; }
.empty {
  padding:54px 26px;
  text-align:center;
  color:#cfcfcf;
  background: rgba(255,255,255,0.04);
  border-radius:14px;
  border:1px solid rgba(255,255,255,0.1);
  max-width: 860px;
  margin: 0 auto;
}

html[data-theme="light"] .page-subtitle,
html[data-theme="light"] .summary-card h3,
html[data-theme="light"] .feedback-date,
html[data-theme="light"] .empty {
  color: var(--rbj-muted, #9f4b43);
}

html[data-theme="light"] .summary-card,
html[data-theme="light"] .feedback-item,
html[data-theme="light"] .empty {
  background: var(--rbj-surface, rgba(255,255,255,0.92));
  color: var(--rbj-text, #7a211b);
  border-color: var(--rbj-border, rgba(217,4,41,0.22));
}

html[data-theme="light"] .feedback-text {
  color: var(--rbj-text, #7a211b);
}

html[data-theme="light"] .rating {
  color: #d68612;
}

@media(max-width:980px){
  .summary-grid{grid-template-columns:1fr;}
}

@media(max-width:800px){
  .navbar{padding:10px 20px;}
  .navbar .nav-links a{margin-left:10px;font-size:14px;}
  .wrapper { padding: 20px 12px 28px; }
  .wrapper h1 { font-size: 30px; }
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
  <h1>My Feedback History</h1>
  <p class="page-subtitle">Track all your submitted feedback and monitor review status updates from RBJ.</p>

  <div class="summary-grid">
    <div class="summary-card submitted">
      <h3>Pending Review</h3>
      <div class="num"><?php echo (int)$summary['submitted']; ?></div>
    </div>
    <div class="summary-card approved">
      <h3>Approved</h3>
      <div class="num"><?php echo (int)$summary['approved']; ?></div>
    </div>
    <div class="summary-card rejected">
      <h3>Rejected</h3>
      <div class="num"><?php echo (int)$summary['rejected']; ?></div>
    </div>
  </div>

  <?php if (!empty($feedback_items)): ?>
    <div class="feedback-list">
      <?php foreach ($feedback_items as $item): ?>
        <?php
          $rating = max(1, min(5, (int)$item['rating']));
          $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
          $status = $item['status'];
          $status_label = $status === 'submitted' ? 'Pending' : ucfirst($status);
        ?>
        <article class="feedback-item">
          <div class="feedback-top">
            <div class="rating"><?php echo htmlspecialchars($stars); ?></div>
            <span class="status-pill <?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($status_label); ?></span>
          </div>
          <p class="feedback-text"><?php echo nl2br(htmlspecialchars($item['feedback'])); ?></p>
          <p class="feedback-date">Submitted on <?php echo date('M j, Y g:i A', strtotime($item['created_at'])); ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty">
      You have not submitted feedback yet.
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/partials/user_footer.php'; ?>

</body>
</html>






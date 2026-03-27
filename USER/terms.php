<?php
session_start();
include '../config.php';
$is_logged_in = isset($_SESSION['user_id']);
$cart_count = 0;
if ($is_logged_in && isset($conn) && $conn instanceof mysqli) {
    $user_id = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT COALESCE(SUM(quantity), 0) AS total FROM cart WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $cart_count = (int)($row['total'] ?? 0);
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Terms & Conditions - RBJ Accessories</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<style>
* { box-sizing: border-box; }
body { margin: 0; font-family: "Montserrat", sans-serif; background: linear-gradient(135deg,#1b1b1b,#111); color: #fff; padding-top: 100px; }
.navbar { position: fixed; top: 0; left: 0; right: 0; display: flex; justify-content: space-between; align-items: center; padding: 10px 50px; background: rgba(0,0,0,0.88); z-index: 999; }
.logo { display: flex; align-items: center; gap: 10px; color: #fff; text-decoration: none; font-size: 22px; font-weight: 700; }
.logo img { height: 60px; width: auto; }
.nav-links { display: flex; align-items: center; gap: 15px; }
.nav-links a { color: #fff; text-decoration: none; font-weight: 500; margin-left: 15px; }
.nav-links a:hover, .nav-links a.active { text-decoration: underline; }
.page-shell { max-width: 1040px; margin: 0 auto; padding: 28px 20px 42px; }
.hero, .term-card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 18px; box-shadow: 0 18px 42px rgba(0,0,0,0.24); }
.hero { padding: 28px; margin-bottom: 18px; }
.eyebrow { display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 999px; background: rgba(217,4,41,0.16); color: #ffd6db; font-size: 12px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; }
.hero h1 { margin: 14px 0 10px; font-size: 38px; line-height: 1.1; }
.hero p { margin: 0; color: rgba(255,255,255,0.78); line-height: 1.75; }
.hero .meta { margin-top: 14px; color: rgba(255,255,255,0.62); font-size: 13px; }
.term-stack { display: grid; gap: 16px; }
.term-card { padding: 22px; }
.term-card h2 { margin: 0 0 12px; font-size: 22px; }
.term-card p, .term-card li { color: rgba(255,255,255,0.78); line-height: 1.75; font-size: 14px; }
.term-card ul { margin: 0; padding-left: 18px; }
html[data-theme="light"] body { background: linear-gradient(145deg, #fff9f8, #fff2ef, #fffdfd) !important; color: #2a1715 !important; }
html[data-theme="light"] .hero, html[data-theme="light"] .term-card { background: rgba(255,255,255,0.95) !important; border-color: rgba(217,4,41,0.18) !important; box-shadow: 0 16px 32px rgba(217,4,41,0.10); }
html[data-theme="light"] .hero p, html[data-theme="light"] .hero .meta, html[data-theme="light"] .term-card p, html[data-theme="light"] .term-card li { color: #7a4a45 !important; }
@media (max-width: 768px) { .navbar { padding: 10px 20px; } .page-shell { padding: 22px 14px 34px; } .hero, .term-card { padding: 18px; } .hero h1 { font-size: 31px; } }
</style>
<?php include __DIR__ . '/partials/user_navbar_theme.php'; ?>
</head>
<body>
<nav class="navbar">
  <a href="index.php" class="logo"><img src="../rbjlogo.png" alt="RBJ Accessories Logo"><span>RBJ Accessories</span></a>
  <div class="nav-links">
    <a href="index.php">Home</a>
    <a href="catalog.php">Shop</a>
    <a href="customize.php">Customize</a>
    <a href="terms.php" class="active">Terms</a>
    <?php if ($is_logged_in): ?>
    <a href="cart.php" class="nav-cart-link" title="Cart" aria-label="Cart"><i class='bx bx-cart'></i><span class="cart-count" data-cart-count style="<?php echo $cart_count > 0 ? '' : 'display:none;'; ?>"><?php echo (int)$cart_count; ?></span></a>
    <?php endif; ?>
    <?php if ($is_logged_in): ?>
    <?php include __DIR__ . '/partials/account_menu.php'; ?>
    <?php else: ?>
    <a href="../login.php">Login</a>
    <?php endif; ?>
  </div>
</nav>
<main class="page-shell">
  <section class="hero">
    <span class="eyebrow"><i class='bx bx-file'></i> Terms & Conditions</span>
    <h1>Guidelines for Using the RBJ Accessories Website and Services.</h1>
    <p>These Terms and Conditions govern the use of the RBJ Accessories website, including browsing, account registration, ordering, customization features, support tools, and related customer services available through the platform.</p>
    <div class="meta">Last updated: March 20, 2026</div>
  </section>
  <section class="term-stack">
    <article class="term-card"><h2>Website Use</h2><p>By using the website, you agree to access and use it responsibly. You are expected to provide accurate information where required, maintain the security of your account credentials, and avoid actions that may disrupt or misuse the platform.</p></article>
    <article class="term-card"><h2>Accounts and Orders</h2><p>Certain services require an account, including order tracking, support access, and parts of the customization workflow. Users are responsible for ensuring that submitted account, shipping, and order information is accurate and current.</p></article>
    <article class="term-card"><h2>Products and Customization</h2><p>Product listings, visual customization tools, and seat design previews are intended to help customers understand available options. Final product outcomes may vary slightly based on materials, production factors, and order-specific details.</p><ul><li>Availability may change without prior notice.</li><li>Customization selections should be reviewed carefully before final order confirmation.</li><li>Images and previews are for presentation and reference purposes.</li></ul></article>
    <article class="term-card"><h2>Pricing and Platform Updates</h2><p>RBJ Accessories may update product information, pricing, service features, and platform content when needed for operational, technical, or business reasons. Such updates are part of maintaining an active online store and customer service system.</p></article>
    <article class="term-card"><h2>Support and Communication</h2><p>Users may contact RBJ Accessories through site-based support features when account assistance, order help, or product-related clarification is needed. Support communication should remain respectful, relevant, and free from abusive or disruptive content.</p></article>
    <article class="term-card"><h2>Limitation and Policy Reference</h2><p>Use of the website is subject to availability and operational limitations. Additional rules related to data handling, delivery expectations, and returns may also be described in related pages such as the Privacy Policy and Shipping & Returns page.</p></article>
  </section>
</main>
<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
include __DIR__ . '/partials/user_footer.php';
?>
</body>
</html>

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
<title>Privacy Policy - RBJ Accessories</title>
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
.hero, .policy-card, .toc { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 18px; box-shadow: 0 18px 42px rgba(0,0,0,0.24); }
.hero { padding: 28px; margin-bottom: 18px; }
.eyebrow { display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 999px; background: rgba(217,4,41,0.16); color: #ffd6db; font-size: 12px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; }
.hero h1 { margin: 14px 0 10px; font-size: 38px; line-height: 1.1; }
.hero p { margin: 0; color: rgba(255,255,255,0.78); line-height: 1.75; }
.hero .meta { margin-top: 14px; color: rgba(255,255,255,0.62); font-size: 13px; }
.toc { padding: 18px 20px; margin-bottom: 18px; }
.toc h2 { margin: 0 0 12px; font-size: 20px; }
.toc ul { margin: 0; padding-left: 18px; display: grid; gap: 8px; }
.toc a { color: #fff; text-decoration: none; }
.policy-stack { display: grid; gap: 16px; }
.policy-card { padding: 22px; }
.policy-card h2 { margin: 0 0 12px; font-size: 22px; }
.policy-card p, .policy-card li { color: rgba(255,255,255,0.78); line-height: 1.75; font-size: 14px; }
.policy-card ul { margin: 0; padding-left: 18px; }
html[data-theme="light"] body { background: linear-gradient(145deg, #fff9f8, #fff2ef, #fffdfd) !important; color: #2a1715 !important; }
html[data-theme="light"] .hero, html[data-theme="light"] .toc, html[data-theme="light"] .policy-card { background: rgba(255,255,255,0.95) !important; border-color: rgba(217,4,41,0.18) !important; box-shadow: 0 16px 32px rgba(217,4,41,0.10); }
html[data-theme="light"] .hero p, html[data-theme="light"] .hero .meta, html[data-theme="light"] .policy-card p, html[data-theme="light"] .policy-card li { color: #7a4a45 !important; }
html[data-theme="light"] .toc a { color: #7a211b; }
@media (max-width: 768px) { .navbar { padding: 10px 20px; } .page-shell { padding: 22px 14px 34px; } .hero, .toc, .policy-card { padding: 18px; } .hero h1 { font-size: 31px; } }
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
    <a href="privacy.php" class="active">Privacy</a>
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
    <span class="eyebrow"><i class='bx bx-lock-alt'></i> Privacy Policy</span>
    <h1>How RBJ Accessories Collects, Uses, and Protects Your Information.</h1>
    <p>This Privacy Policy explains how information may be collected and used when you browse the RBJ Accessories website, create an account, place orders, submit support requests, or use site features such as live chat and customization tools.</p>
    <div class="meta">Last updated: March 20, 2026</div>
  </section>
  <section class="toc">
    <h2>Quick Navigation</h2>
    <ul>
      <li><a href="#collect">Information We Collect</a></li>
      <li><a href="#use">How We Use Information</a></li>
      <li><a href="#share">When Information May Be Shared</a></li>
      <li><a href="#security">Data Protection</a></li>
      <li><a href="#rights">Your Choices</a></li>
    </ul>
  </section>
  <section class="policy-stack">
    <article class="policy-card" id="collect"><h2>Information We Collect</h2><p>Depending on how you use the website, RBJ Accessories may collect account details, contact details, order information, support messages, delivery-related information, and usage data needed to keep the website functional and customer requests organized.</p><ul><li>Account information such as username, email address, and profile details.</li><li>Order and cart information connected to your purchases and customization selections.</li><li>Messages sent through support tools or live chat.</li><li>Technical or usage information that helps maintain website performance and security.</li></ul></article>
    <article class="policy-card" id="use"><h2>How We Use Information</h2><p>Information is used to operate the website, process orders, provide customer support, improve service quality, and maintain account-related features such as notifications, order tracking, and chat history.</p><ul><li>To process and manage orders and customer requests.</li><li>To communicate updates related to support, orders, or account activity.</li><li>To improve product, website, and support experience.</li><li>To protect the website against misuse, unauthorized access, or suspicious activity.</li></ul></article>
    <article class="policy-card" id="share"><h2>When Information May Be Shared</h2><p>RBJ Accessories does not treat customer information as public content. Information may only be shared when needed for legitimate business operations, order fulfillment, legal compliance, or service support connected to your use of the platform.</p><ul><li>With internal staff handling orders, support, and platform operations.</li><li>When required to comply with legal or regulatory obligations.</li><li>With service providers directly supporting order, hosting, or communication workflows, when applicable.</li></ul></article>
    <article class="policy-card" id="security"><h2>Data Protection</h2><p>We take reasonable steps to maintain the integrity and security of customer information within the website environment. While no online platform can guarantee absolute security, RBJ Accessories uses platform controls, account access controls, and operational safeguards to reduce risk.</p></article>
    <article class="policy-card" id="rights"><h2>Your Choices</h2><p>You may review and update certain account information through your profile area. If you have concerns about account data, support records, or privacy-related requests, please contact RBJ Accessories through the support tools available on the website.</p></article>
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

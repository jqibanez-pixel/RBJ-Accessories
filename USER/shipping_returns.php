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
<title>Shipping & Returns - RBJ Accessories</title>
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
.page-shell { max-width: 1120px; margin: 0 auto; padding: 28px 20px 42px; }
.hero, .policy-card, .tip-card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 18px; box-shadow: 0 18px 42px rgba(0,0,0,0.24); }
.hero { padding: 28px; margin-bottom: 18px; }
.eyebrow { display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 999px; background: rgba(217,4,41,0.16); color: #ffd6db; font-size: 12px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; }
.hero h1 { margin: 14px 0 10px; font-size: 38px; line-height: 1.1; }
.hero p { margin: 0; color: rgba(255,255,255,0.78); line-height: 1.75; }
.hero .meta { margin-top: 14px; color: rgba(255,255,255,0.62); font-size: 13px; }
.grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
.policy-card, .tip-card { padding: 22px; }
.policy-card h2, .tip-card h2 { margin: 0 0 12px; font-size: 22px; }
.policy-card p, .policy-card li, .tip-card p, .tip-card li { color: rgba(255,255,255,0.78); line-height: 1.75; font-size: 14px; }
.policy-card ul, .tip-card ul { margin: 0; padding-left: 18px; }
.tip-card { margin-top: 18px; }
html[data-theme="light"] body { background: linear-gradient(145deg, #fff9f8, #fff2ef, #fffdfd) !important; color: #2a1715 !important; }
html[data-theme="light"] .hero, html[data-theme="light"] .policy-card, html[data-theme="light"] .tip-card { background: rgba(255,255,255,0.95) !important; border-color: rgba(217,4,41,0.18) !important; box-shadow: 0 16px 32px rgba(217,4,41,0.10); }
html[data-theme="light"] .hero p, html[data-theme="light"] .hero .meta, html[data-theme="light"] .policy-card p, html[data-theme="light"] .policy-card li, html[data-theme="light"] .tip-card p, html[data-theme="light"] .tip-card li { color: #7a4a45 !important; }
@media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
@media (max-width: 768px) { .navbar { padding: 10px 20px; } .page-shell { padding: 22px 14px 34px; } .hero, .policy-card, .tip-card { padding: 18px; } .hero h1 { font-size: 31px; } }
</style>
<?php include __DIR__ . '/partials/user_main_nav_base.php'; ?>
<?php include __DIR__ . '/partials/user_navbar_theme.php'; ?>
</head>
<body>
<?php include __DIR__ . '/partials/user_main_nav.php'; ?>
<main class="page-shell">
  <section class="hero">
    <span class="eyebrow"><i class='bx bx-package'></i> Shipping & Returns</span>
    <h1>Important Information About Delivery, Fulfillment, and Return Requests.</h1>
    <p>This page outlines general shipping and return expectations for RBJ Accessories website orders. Delivery timelines and return handling may vary depending on item type, customization level, order condition, and review outcome.</p>
    <div class="meta">Last updated: March 27, 2026</div>
  </section>
  <section class="grid">
    <article class="policy-card"><h2>Shipping Overview</h2><p>Orders are processed based on product availability, order confirmation status, and fulfillment readiness. Customized items may require additional preparation compared with standard ready-to-order items.</p><ul><li>Processing timelines may vary depending on order complexity.</li><li>Shipping progress can be followed through order-related pages where available.</li><li>Customers should provide accurate delivery details to avoid delays.</li></ul></article>
    <article class="policy-card"><h2>Delivery Expectations</h2><p>Estimated delivery timing depends on destination, courier performance, operational volume, and order readiness. RBJ Accessories works to keep fulfillment updates clear, but transit timelines can still vary after dispatch.</p><ul><li>Delivery schedules are not guaranteed to be identical for all locations.</li><li>High-volume periods may affect processing and transit time.</li><li>Customers are encouraged to monitor notifications and tracking updates.</li></ul></article>
    <article class="policy-card"><h2>Returns and Review Requests</h2><p>Return concerns are assessed based on the condition of the item, order history, and the nature of the issue raised. Customers should contact support promptly when a delivery-related or product-related concern needs review.</p><ul><li>Requests should be submitted with clear details about the issue.</li><li>Items may need to be reviewed before a return outcome is confirmed.</li><li>Support communication helps determine the correct next step.</li></ul></article>
    <article class="policy-card"><h2>Customized Orders</h2><p>Because custom products are prepared according to customer selections, they may require closer review when changes or return-related concerns are raised. Customers should carefully confirm customization choices before finalizing an order.</p></article>
  </section>
  <section class="tip-card">
    <h2>Need Help With A Shipping or Return Concern?</h2>
    <p>If you already have an account, the best next step is to contact RBJ Accessories through the Support page so your request is connected to your profile and order context. This makes it easier to review concerns accurately and respond with the most helpful resolution path.</p>
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

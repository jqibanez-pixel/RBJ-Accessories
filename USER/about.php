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
<title>About RBJ Accessories</title>
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
.page-shell { max-width: 1180px; margin: 0 auto; padding: 28px 20px 42px; }
.hero { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 18px; align-items: stretch; margin-bottom: 18px; }
.hero-card, .section-card, .metric-card, .timeline-card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 18px; box-shadow: 0 18px 42px rgba(0,0,0,0.24); }
.hero-card { padding: 28px; }
.eyebrow { display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 999px; background: rgba(217,4,41,0.16); color: #ffd6db; font-size: 12px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; }
.hero h1 { margin: 16px 0 12px; font-size: 40px; line-height: 1.1; }
.hero p { margin: 0; color: rgba(255,255,255,0.78); line-height: 1.75; font-size: 15px; }
.hero-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 20px; }
.hero-actions a { display: inline-flex; align-items: center; gap: 8px; padding: 12px 16px; border-radius: 999px; text-decoration: none; font-weight: 700; }
.hero-actions .primary { background: linear-gradient(45deg, #d90429, #ef233c); color: #fff; }
.hero-actions .secondary { background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.16); color: #fff; }
.hero-panel { padding: 22px; display: grid; gap: 14px; }
.hero-panel h2 { margin: 0; font-size: 22px; }
.hero-panel p { margin: 0; color: rgba(255,255,255,0.75); line-height: 1.7; font-size: 14px; }
.metric-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 18px; }
.metric-card { padding: 18px; }
.metric-label { color: rgba(255,255,255,0.72); font-size: 12px; text-transform: uppercase; letter-spacing: 0.08em; }
.metric-value { margin-top: 8px; font-size: 28px; font-weight: 800; }
.section-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
.section-card { padding: 22px; }
.section-card h2 { margin: 0 0 12px; font-size: 22px; }
.section-card p, .section-card li { color: rgba(255,255,255,0.78); line-height: 1.75; font-size: 14px; }
.section-card ul { margin: 0; padding-left: 18px; }
.timeline { display: grid; gap: 12px; }
.timeline-card { padding: 16px 18px; }
.timeline-year { font-size: 13px; font-weight: 800; letter-spacing: 0.08em; color: #ffb3bd; text-transform: uppercase; }
.timeline-title { margin-top: 6px; font-size: 18px; font-weight: 700; }
.timeline-copy { margin-top: 6px; color: rgba(255,255,255,0.76); line-height: 1.7; font-size: 14px; }
html[data-theme="light"] body { background: linear-gradient(145deg, #fff9f8, #fff2ef, #fffdfd) !important; color: #2a1715 !important; }
html[data-theme="light"] .hero-card, html[data-theme="light"] .section-card, html[data-theme="light"] .metric-card, html[data-theme="light"] .timeline-card { background: rgba(255,255,255,0.95) !important; border-color: rgba(217,4,41,0.18) !important; box-shadow: 0 16px 32px rgba(217,4,41,0.10); }
html[data-theme="light"] .hero p, html[data-theme="light"] .hero-panel p, html[data-theme="light"] .section-card p, html[data-theme="light"] .section-card li, html[data-theme="light"] .timeline-copy, html[data-theme="light"] .metric-label { color: #7a4a45 !important; }
html[data-theme="light"] .hero-actions .secondary { color: #7a211b; background: #fff3f0; border-color: rgba(217,4,41,0.20); }
html[data-theme="light"] .timeline-year { color: #b83a47; }
@media (max-width: 900px) { .hero, .section-grid, .metric-grid { grid-template-columns: 1fr; } }
@media (max-width: 768px) { .navbar { padding: 10px 20px; } .page-shell { padding: 22px 14px 34px; } .hero-card, .hero-panel, .section-card, .metric-card, .timeline-card { padding: 18px; } .hero h1 { font-size: 31px; } }
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
    <a href="about.php" class="active">About</a>
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
    <article class="hero-card">
      <span class="eyebrow"><i class='bx bx-shield-quarter'></i> About RBJ Accessories</span>
      <h1>Built Around Riders, Daily Use, and Clean Craftsmanship.</h1>
      <p>RBJ Accessories focuses on motorcycle seat covers and rider-focused upgrades that blend comfort, fit, and lasting quality. We design our work around practical use on the road, visual customization, and reliable materials that riders can trust day after day.</p>
      <div class="hero-actions">
        <a href="catalog.php" class="primary"><i class='bx bx-store-alt'></i> Explore Products</a>
        <a href="customize.php" class="secondary"><i class='bx bx-palette'></i> Start Customizing</a>
      </div>
    </article>
    <aside class="hero-card hero-panel">
      <h2>What We Stand For</h2>
      <p>We believe motorcycle accessories should feel personal, not generic. Every rider has a preferred look, fit, and riding experience, so our approach centers on customization, dependable workmanship, and clear customer support from inquiry to delivery.</p>
      <p>From everyday builds to more detailed custom seat projects, our goal is simple: deliver products that look right, fit right, and hold up in real riding conditions.</p>
    </aside>
  </section>
  <section class="metric-grid">
    <article class="metric-card"><div class="metric-label">Core Focus</div><div class="metric-value">Seat Covers</div></article>
    <article class="metric-card"><div class="metric-label">Service Style</div><div class="metric-value">Custom First</div></article>
    <article class="metric-card"><div class="metric-label">Support Approach</div><div class="metric-value">Guided & Clear</div></article>
    <article class="metric-card"><div class="metric-label">Customer Goal</div><div class="metric-value">Ride With Confidence</div></article>
  </section>
  <section class="section-grid">
    <article class="section-card">
      <h2>Our Mission</h2>
      <p>Our mission is to make motorcycle customization more approachable, practical, and premium for everyday riders. We aim to provide accessories that elevate both style and comfort while staying grounded in durability, usability, and honest service.</p>
    </article>
    <article class="section-card">
      <h2>Why Riders Choose RBJ</h2>
      <ul>
        <li>Customization options that support a more personal build.</li>
        <li>Materials and finishes selected with daily riding in mind.</li>
        <li>Clear support for orders, updates, and product questions.</li>
        <li>A consistent focus on fit, presentation, and overall rider satisfaction.</li>
      </ul>
    </article>
    <article class="section-card">
      <h2>What We Prioritize</h2>
      <ul>
        <li>Comfort that supports long or repeated rides.</li>
        <li>Visual details that give each seat a cleaner, more intentional look.</li>
        <li>Reliable production and finishing standards.</li>
        <li>A smoother buying experience from browsing to support.</li>
      </ul>
    </article>
    <article class="section-card">
      <h2>How We Serve Customers</h2>
      <p>We support customers through product browsing, customization tools, order tracking, and direct support options inside the website. Whether you are choosing a ready-made design or planning a customized setup, the site is structured to help you move from idea to order with less friction.</p>
    </article>
  </section>
  <section class="section-card" style="margin-top:18px;">
    <h2>Our Growth Story</h2>
    <div class="timeline">
      <article class="timeline-card"><div class="timeline-year">Foundation</div><div class="timeline-title">Starting With Rider Needs</div><div class="timeline-copy">RBJ Accessories began with a clear focus on motorcycle seat enhancement and practical rider upgrades, shaped by real customer demand for better comfort and better-looking builds.</div></article>
      <article class="timeline-card"><div class="timeline-year">Expansion</div><div class="timeline-title">Moving Into Customization</div><div class="timeline-copy">As customer preferences became more detailed, customization became a stronger part of our service model, allowing riders to request more personalized design combinations and styling choices.</div></article>
      <article class="timeline-card"><div class="timeline-year">Today</div><div class="timeline-title">A More Connected Website Experience</div><div class="timeline-copy">Today, RBJ Accessories combines catalog browsing, customization tools, support channels, and order visibility in one online system to provide a more complete customer experience.</div></article>
    </div>
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

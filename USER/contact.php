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
<title>Contact RBJ Accessories</title>
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
.intro, .contact-card, .branch-card, .help-card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 18px; box-shadow: 0 18px 42px rgba(0,0,0,0.24); }
.intro { padding: 28px; margin-bottom: 18px; }
.intro h1 { margin: 14px 0 10px; font-size: 38px; line-height: 1.1; }
.intro p { margin: 0; color: rgba(255,255,255,0.78); line-height: 1.75; max-width: 780px; }
.eyebrow { display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 999px; background: rgba(217,4,41,0.16); color: #ffd6db; font-size: 12px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; }
.contact-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin-bottom: 18px; }
.contact-card { padding: 20px; }
.contact-card i { font-size: 26px; color: #ff9da9; }
.contact-card h2 { margin: 12px 0 8px; font-size: 21px; }
.contact-card p { margin: 0; color: rgba(255,255,255,0.76); line-height: 1.7; font-size: 14px; }
.contact-card a { display: inline-flex; align-items: center; gap: 8px; margin-top: 14px; color: #fff; text-decoration: none; font-weight: 700; }
.section-title { margin: 0 0 14px; font-size: 24px; }
.branch-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
.branch-card { padding: 20px; }
.branch-card h3 { margin: 0 0 10px; font-size: 19px; }
.branch-card p { margin: 0 0 12px; color: rgba(255,255,255,0.76); line-height: 1.7; font-size: 14px; }
.branch-card .meta { display: inline-flex; align-items: center; gap: 8px; padding: 8px 10px; border-radius: 999px; background: rgba(255,255,255,0.06); font-size: 12px; color: rgba(255,255,255,0.82); }
.help-card { margin-top: 18px; padding: 22px; }
.help-card p { margin: 0; color: rgba(255,255,255,0.76); line-height: 1.75; }
html[data-theme="light"] body { background: linear-gradient(145deg, #fff9f8, #fff2ef, #fffdfd) !important; color: #2a1715 !important; }
html[data-theme="light"] .intro, html[data-theme="light"] .contact-card, html[data-theme="light"] .branch-card, html[data-theme="light"] .help-card { background: rgba(255,255,255,0.95) !important; border-color: rgba(217,4,41,0.18) !important; box-shadow: 0 16px 32px rgba(217,4,41,0.10); }
html[data-theme="light"] .intro p, html[data-theme="light"] .contact-card p, html[data-theme="light"] .branch-card p, html[data-theme="light"] .help-card p, html[data-theme="light"] .branch-card .meta { color: #7a4a45 !important; }
html[data-theme="light"] .contact-card a { color: #7a211b; }
@media (max-width: 900px) { .contact-grid, .branch-grid { grid-template-columns: 1fr; } }
@media (max-width: 768px) { .navbar { padding: 10px 20px; } .page-shell { padding: 22px 14px 34px; } .intro, .contact-card, .branch-card, .help-card { padding: 18px; } .intro h1 { font-size: 31px; } }
</style>
<?php include __DIR__ . '/partials/user_main_nav_base.php'; ?>
<?php include __DIR__ . '/partials/user_navbar_theme.php'; ?>
</head>
<body>
<?php include __DIR__ . '/partials/user_main_nav.php'; ?>
<main class="page-shell">
  <section class="intro">
    <span class="eyebrow"><i class='bx bx-headphone'></i> Contact & Support</span>
    <h1>We’re Here to Help With Orders, Customization, and Product Questions.</h1>
    <p>If you need assistance with product selection, order concerns, support follow-ups, or branch visits, RBJ Accessories offers multiple ways to connect through the website. For logged-in customers, the fastest route is usually the Support page or the live chat widget inside the site.</p>
  </section>
  <section class="contact-grid">
    <article class="contact-card"><i class='bx bx-support'></i><h2>Support Center</h2><p>Submit support requests through your account and keep a record of conversations, updates, and admin replies in one place.</p><a href="<?php echo $is_logged_in ? 'support.php' : '../login.php'; ?>"><i class='bx bx-right-arrow-alt'></i> Open Support</a></article>
    <article class="contact-card"><i class='bx bx-message-dots'></i><h2>Live Chat</h2><p>Use the built-in live chat on supported pages for quicker assistance when you need help while browsing or checking your account.</p><a href="<?php echo $is_logged_in ? 'dashboard.php' : '../login.php'; ?>"><i class='bx bx-right-arrow-alt'></i> Go To Account Area</a></article>
    <article class="contact-card"><i class='bx bx-store-alt'></i><h2>Visit A Branch</h2><p>Prefer an in-person visit? You can review branch locations below and use the branch map tools available on the website homepage.</p><a href="index.php#locate"><i class='bx bx-right-arrow-alt'></i> View Branch Locator</a></article>
  </section>
  <h2 class="section-title">Branch Locations</h2>
  <section class="branch-grid">
    <article class="branch-card"><h3>RBJ Accessories - Calamba Branch</h3><p>49 Burgos St, Calamba, 4027 Laguna</p><span class="meta"><i class='bx bx-map'></i> Available in branch locator</span></article>
    <article class="branch-card"><h3>RBJ Accessories - Tanauan Branch</h3><p>Tanauan City, Batangas</p><span class="meta"><i class='bx bx-map'></i> Available in branch locator</span></article>
    <article class="branch-card"><h3>RBJ Accessories - Cavite Branch</h3><p>Area K, 125 Governor's Dr, General Mariano Alvarez, Cavite</p><span class="meta"><i class='bx bx-map'></i> Available in branch locator</span></article>
    <article class="branch-card"><h3>RBJ Accessories - Pasig Branch</h3><p>306 Eulogio Amang Rodriguez Ave, Manggahan, Pasig, 1611 Metro Manila</p><span class="meta"><i class='bx bx-map'></i> Available in branch locator</span></article>
  </section>
  <section class="help-card">
    <h2 class="section-title">Best Way To Reach Us</h2>
    <p>For order-specific assistance, account-related concerns, or website support, using the support tools inside your account is the best option because it keeps your messages tied to your profile and order context. This makes it easier to review concerns accurately and respond with the most helpful resolution path.</p>
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

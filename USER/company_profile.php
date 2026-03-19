<?php
session_start();
$username = $_SESSION['username'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Business Profile - RBJ Accessories</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<style>
* { box-sizing: border-box; }
body {
  margin: 0;
  font-family: "Montserrat", sans-serif;
  background: linear-gradient(135deg,#1b1b1b,#111);
  color: #fff;
  padding-top: 100px;
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
  background: rgba(0,0,0,0.9);
  z-index: 999;
}
.logo {
  display: flex;
  align-items: center;
  gap: 10px;
  color: #fff;
  text-decoration: none;
  font-size: 22px;
  font-weight: 700;
}
.logo img { height: 60px; width: auto; }
.nav-links { display: flex; align-items: center; gap: 15px; }
.nav-links a { color: #fff; text-decoration: none; font-weight: 500; margin-left: 15px; }
.nav-links a:hover { text-decoration: underline; }
.company-dropdown,
.account-dropdown { position: relative; display: flex; align-items: center; margin-left: 15px; }
.account-trigger {
  background: none;
  border: none;
  color: #fff;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}
.account-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #27ae60;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  flex: 0 0 40px;
  overflow: hidden;
}
.account-username { font-weight: 600; color: #fff; }
.account-menu {
  position: absolute;
  top: 110%;
  right: 0;
  min-width: 220px;
  background: #1e1e1e;
  border-radius: 12px;
  border: 1px solid rgba(255,255,255,0.12);
  box-shadow: 0 10px 30px rgba(0,0,0,0.4);
  padding: 8px 0;
  z-index: 1001;
  display: none;
}
.account-menu a {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 15px;
  color: #fff;
  text-decoration: none;
  font-size: 14px;
  margin-left: 0;
}
.account-menu a:hover { background: rgba(255,255,255,0.08); text-decoration: none; }
.account-dropdown.active .account-menu { display: block; }

.page-content { padding: 34px 20px 10px; }
.wrap { max-width: 1100px; margin: 0 auto; }

.profile-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 14px;
}
.profile-head h1 {
  margin: 0;
  font-size: 34px;
}
.verified-by {
  margin-top: 6px;
  color: #c9c9c9;
  font-size: 14px;
}
.download-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  border: 1px solid rgba(255,255,255,0.2);
  background: rgba(255,255,255,0.08);
  color: #fff;
  text-decoration: none;
  padding: 10px 14px;
  border-radius: 999px;
  font-weight: 600;
}
.download-btn:hover {
  background: rgba(217,4,41,0.3);
  border-color: rgba(217,4,41,0.75);
}

.profile-grid {
  display: grid;
  gap: 16px;
  grid-template-columns: 1fr;
}

.section-card {
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 14px;
  padding: 18px;
}
.section-card h2 {
  margin: 0 0 14px;
  font-size: 20px;
}
.data-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px 14px;
}
.data-item {
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 10px;
  padding: 12px;
}
.data-label {
  display: block;
  color: #c8c8c8;
  font-size: 13px;
  margin-bottom: 6px;
}
.data-value {
  font-size: 15px;
  font-weight: 700;
}

/* Light-mode polish for readability and structure */
html[data-theme="light"] body {
  background:
    radial-gradient(circle at 14% 12%, rgba(217,4,41,0.14), transparent 30%),
    radial-gradient(circle at 88% 88%, rgba(217,4,41,0.10), transparent 28%),
    linear-gradient(145deg, #fff9f8 0%, #fff2ef 52%, #fffdfd 100%) !important;
  color: #2e1a18 !important;
}

html[data-theme="light"] .profile-head {
  border-bottom: 1px solid rgba(217,4,41,0.24);
  padding-bottom: 12px;
  margin-bottom: 18px;
}

html[data-theme="light"] .verified-by {
  color: #8d4a45 !important;
}

html[data-theme="light"] .download-btn {
  background: #fff6f4 !important;
  color: #7d231e !important;
  border-color: rgba(217,4,41,0.32) !important;
}

html[data-theme="light"] .download-btn:hover {
  background: #ffe9e4 !important;
  border-color: rgba(217,4,41,0.56) !important;
}

html[data-theme="light"] .section-card {
  background: linear-gradient(145deg, #fffefe 0%, #fff7f5 100%) !important;
  border: 1px solid rgba(217,4,41,0.22) !important;
  box-shadow: 0 10px 24px rgba(217,4,41,0.12);
}

html[data-theme="light"] .section-card h2 {
  color: #8d2720 !important;
  padding-bottom: 8px;
  margin-bottom: 12px;
  border-bottom: 1px solid rgba(217,4,41,0.18);
}

html[data-theme="light"] .data-item {
  background: #fff4f1 !important;
  border: 1px solid rgba(217,4,41,0.16) !important;
}

html[data-theme="light"] .data-label {
  color: #8c5955 !important;
}

html[data-theme="light"] .data-value {
  color: #2f1b1a !important;
}

@media (max-width: 768px) {
  .navbar { padding: 10px 20px; }
  .nav-links a { margin-left: 10px; font-size: 14px; }
  .profile-head h1 { font-size: 28px; }
  .data-grid { grid-template-columns: 1fr; }
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
      <?php if ($username): ?>
      <?php include __DIR__ . '/partials/account_menu.php'; ?>
      <?php else: ?>
      <a href="../login.php">Login</a>
      <?php endif; ?>
    </div>
  </nav>

  <main class="page-content">
    <div class="wrap">
      <div class="profile-head">
        <div>
          <h1>Business Profile</h1>
          <div class="verified-by">Verified profile by RBJ Accessories Quality Team</div>
        </div>
        <a href="#" class="download-btn" onclick="alert('Report download will be available soon.'); return false;">
          <i class='bx bx-download'></i> Download Report
        </a>
      </div>

      <div class="profile-grid">
        <section class="section-card">
          <h2>Overview</h2>
          <div class="data-grid">
            <div class="data-item"><span class="data-label">Company registration date</span><span class="data-value">2011-10-17</span></div>
            <div class="data-item"><span class="data-label">Floor space (㎡)</span><span class="data-value">2500</span></div>
            <div class="data-item"><span class="data-label">Accepted languages</span><span class="data-value">English, Filipino</span></div>
            <div class="data-item"><span class="data-label">Years exporting</span><span class="data-value">14</span></div>
            <div class="data-item"><span class="data-label">Years in industry</span><span class="data-value">14</span></div>
          </div>
        </section>

        <section class="section-card">
          <h2>Production Capabilities</h2>
          <div class="data-grid">
            <div class="data-item"><span class="data-label">Production lines</span><span class="data-value">5</span></div>
            <div class="data-item"><span class="data-label">Production machines</span><span class="data-value">56</span></div>
          </div>
        </section>

        <section class="section-card">
          <h2>Quality Control</h2>
          <div class="data-grid">
            <div class="data-item"><span class="data-label">Product support traceability of raw materials</span><span class="data-value">Yes</span></div>
            <div class="data-item"><span class="data-label">Product inspection method</span><span class="data-value">Inspection of all products, random inspection, and client-required checks</span></div>
            <div class="data-item"><span class="data-label">Quality control on all production lines</span><span class="data-value">Yes</span></div>
            <div class="data-item"><span class="data-label">QA/QC inspectors</span><span class="data-value">5</span></div>
          </div>
        </section>

        <section class="section-card">
          <h2>Trade Background</h2>
          <div class="data-grid">
            <div class="data-item"><span class="data-label">Main markets</span><span class="data-value">Eastern Asia (40%), Western Europe (30%), North America (20%), Domestic Market (10%)</span></div>
            <div class="data-item"><span class="data-label">Main client types</span><span class="data-value">Manufacturer, Wholesaler, Retailer, Rider Community, Brand Business</span></div>
          </div>
        </section>

        <section class="section-card">
          <h2>R&amp;D Capabilities</h2>
          <div class="data-grid">
            <div class="data-item"><span class="data-label">Customization options</span><span class="data-value">Sample processing, graphic processing, and made-to-order seat cover builds</span></div>
            <div class="data-item"><span class="data-label">New products launched in last year</span><span class="data-value">30</span></div>
            <div class="data-item"><span class="data-label">R&amp;D engineers</span><span class="data-value">2</span></div>
            <div class="data-item"><span class="data-label">R&amp;D engineer education levels</span><span class="data-value">1 Technical School, 1 Junior College</span></div>
          </div>
        </section>
      </div>
    </div>
  </main>

  <?php include __DIR__ . '/partials/user_footer.php'; ?>
</body>
</html>




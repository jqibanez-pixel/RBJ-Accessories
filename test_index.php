<?php
$sampleProducts = [
  ['name' => 'Helmet', 'price' => 1999, 'stock' => 15],
  ['name' => 'Motor Oil', 'price' => 450, 'stock' => 40],
  ['name' => 'Brake Pads', 'price' => 780, 'stock' => 22],
  ['name' => 'Gloves', 'price' => 650, 'stock' => 18],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Responsive Test Page</title>
  <style>
    :root {
      --bg: #f4f7fb;
      --surface: #ffffff;
      --primary: #0b63f6;
      --text: #16213d;
      --muted: #5c6783;
      --line: #d9dfec;
      --ok: #1f9d55;
      --warn: #b45309;
      --radius: 14px;
      --shadow: 0 10px 25px rgba(12, 31, 67, 0.08);
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      font-family: "Segoe UI", Tahoma, sans-serif;
      color: var(--text);
      background: linear-gradient(170deg, #edf3ff, #f9fbff 45%, #eef2f7);
      min-height: 100vh;
    }

    .container {
      width: min(1100px, 92%);
      margin: 0 auto;
    }

    .topbar {
      position: sticky;
      top: 0;
      z-index: 20;
      backdrop-filter: blur(8px);
      background: rgba(255, 255, 255, 0.88);
      border-bottom: 1px solid var(--line);
    }

    .topbar-inner {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 12px 0;
      position: relative;
    }

    .brand {
      font-weight: 700;
      letter-spacing: 0.4px;
      font-size: 1rem;
    }

    .nav {
      display: flex;
      gap: 14px;
      flex-wrap: wrap;
    }

    .nav a {
      text-decoration: none;
      color: var(--muted);
      font-size: 0.92rem;
      padding: 6px 10px;
      border-radius: 8px;
    }

    .nav a:hover {
      background: #ecf2ff;
      color: var(--primary);
    }

    .menu-toggle {
      display: none;
      border: 1px solid #cfd8ea;
      background: #fff;
      color: #24365d;
      width: auto;
      padding: 8px 10px;
      border-radius: 10px;
      font-size: 1.1rem;
      line-height: 1;
    }

    .hero {
      margin: 22px 0;
      background: var(--surface);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: clamp(16px, 4vw, 28px);
    }

    .hero h1 {
      margin: 0 0 8px;
      font-size: clamp(1.25rem, 4vw, 2rem);
      line-height: 1.2;
    }

    .hero p {
      margin: 0;
      color: var(--muted);
      max-width: 70ch;
      font-size: clamp(0.92rem, 2.6vw, 1rem);
    }

    .grid {
      display: grid;
      gap: 14px;
      grid-template-columns: repeat(12, 1fr);
      margin-bottom: 18px;
    }

    .card {
      grid-column: span 12;
      background: var(--surface);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 16px;
    }

    .card h3 {
      margin: 0 0 10px;
      font-size: 1.02rem;
    }

    .kpi {
      font-size: 1.65rem;
      font-weight: 700;
      margin: 6px 0;
    }

    .muted {
      color: var(--muted);
      font-size: 0.9rem;
    }

    .badge {
      display: inline-block;
      padding: 4px 9px;
      border-radius: 999px;
      font-size: 0.78rem;
      font-weight: 600;
    }

    .badge.ok { background: #e8f8ef; color: var(--ok); }
    .badge.warn { background: #fff4e6; color: var(--warn); }

    .section-title {
      margin: 22px 0 10px;
      font-size: 1.05rem;
    }

    .table-wrap {
      overflow-x: auto;
      border: 1px solid var(--line);
      border-radius: var(--radius);
      background: var(--surface);
      box-shadow: var(--shadow);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      min-width: 520px;
    }

    th, td {
      text-align: left;
      padding: 11px 12px;
      border-bottom: 1px solid #edf1f8;
      font-size: 0.93rem;
    }

    th {
      background: #f8faff;
      color: #314267;
      font-weight: 600;
    }

    .form-grid {
      display: grid;
      gap: 10px;
      grid-template-columns: 1fr;
      margin-top: 14px;
    }

    label {
      display: block;
      font-size: 0.84rem;
      font-weight: 600;
      margin-bottom: 5px;
      color: #2c3f66;
    }

    input, select, textarea, button {
      width: 100%;
      border: 1px solid #cfd8ea;
      border-radius: 10px;
      padding: 10px 12px;
      font: inherit;
    }

    textarea { min-height: 96px; resize: vertical; }

    button {
      background: var(--primary);
      color: #fff;
      border: none;
      font-weight: 600;
      cursor: pointer;
      transition: opacity 0.15s ease;
    }

    button:hover { opacity: 0.92; }

    @media (min-width: 640px) {
      .card.half { grid-column: span 6; }
      .form-grid { grid-template-columns: 1fr 1fr; }
      .form-grid .full { grid-column: 1 / -1; }
    }

    @media (max-width: 760px) {
      .menu-toggle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
      }

      .nav {
        display: none;
        position: absolute;
        top: 100%;
        right: 0;
        left: 0;
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 12px;
        padding: 10px;
        box-shadow: var(--shadow);
        margin-top: 8px;
      }

      .nav.is-open {
        display: flex;
        flex-direction: column;
        gap: 4px;
      }

      .nav a {
        padding: 10px;
      }
    }

    @media (min-width: 960px) {
      .card.third { grid-column: span 4; }
      .card.two-thirds { grid-column: span 8; }
    }
  </style>
</head>
<body>
  <header class="topbar">
    <div class="container topbar-inner">
      <div class="brand">RBJ System Responsive Demo</div>
      <button class="menu-toggle" id="menuToggle" type="button" aria-expanded="false" aria-controls="mobileNav" aria-label="Open navigation menu">
        &#9776;
      </button>
      <nav class="nav" id="mobileNav">
        <a href="#">Dashboard</a>
        <a href="#">Products</a>
        <a href="#">Orders</a>
        <a href="#">Profile</a>
      </nav>
    </div>
  </header>

  <main class="container">
    <section class="hero">
      <h1>Mobile Responsive Test Page</h1>
      <p>
        This page is designed to adapt on phones, tablets, and desktop screens. Resize the browser or open on your mobile phone to verify behavior.
      </p>
    </section>

    <section class="grid">
      <article class="card half">
        <h3>Today's Orders</h3>
        <div class="kpi">24</div>
        <span class="badge ok">+8% vs yesterday</span>
      </article>

      <article class="card half">
        <h3>Low Stock Alerts</h3>
        <div class="kpi">3</div>
        <span class="badge warn">Needs restock</span>
      </article>

      <article class="card third">
        <h3>Active Users</h3>
        <div class="kpi">57</div>
        <p class="muted">Users currently browsing</p>
      </article>

      <article class="card two-thirds">
        <h3>Create Quick Note</h3>
        <form class="form-grid" action="#" method="post">
          <div>
            <label for="name">Name</label>
            <input id="name" name="name" type="text" placeholder="Juan Dela Cruz">
          </div>

          <div>
            <label for="type">Category</label>
            <select id="type" name="type">
              <option>General</option>
              <option>Inventory</option>
              <option>Order</option>
            </select>
          </div>

          <div class="full">
            <label for="note">Message</label>
            <textarea id="note" name="note" placeholder="Type a quick message..."></textarea>
          </div>

          <div class="full">
            <button type="submit">Save Note</button>
          </div>
        </form>
      </article>
    </section>

    <h2 class="section-title">Sample Product Table (Scrollable on small screens)</h2>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Product</th>
            <th>Price</th>
            <th>Stock</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($sampleProducts as $item): ?>
            <tr>
              <td><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td>PHP <?php echo number_format($item['price'], 2); ?></td>
              <td><?php echo (int) $item['stock']; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <p class="muted" style="margin: 14px 0 28px;">
      Tip: Test in browser DevTools (iPhone/Android view) and real phone browser for accurate results.
    </p>
  </main>
  <script>
    (function () {
      var toggle = document.getElementById('menuToggle');
      var nav = document.getElementById('mobileNav');
      if (!toggle || !nav) return;

      toggle.addEventListener('click', function () {
        var isOpen = nav.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      });
    })();
  </script>
</body>
</html>

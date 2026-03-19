<?php
session_start();
include '../config.php';

$is_logged_in = isset($_SESSION['user_id']);
$username = $is_logged_in ? ($_SESSION['username'] ?? 'User') : '';

$q = trim((string)($_GET['q'] ?? ''));
$category = trim((string)($_GET['category'] ?? ''));
$sort = trim((string)($_GET['sort'] ?? 'relevance'));
$rating = max(0, min(5, (int)($_GET['rating'] ?? 0)));

$allowed_sort = ['relevance', 'price_low', 'price_high', 'latest'];
if (!in_array($sort, $allowed_sort, true)) {
    $sort = 'relevance';
}

$where = ['t.is_active = 1'];
$types = '';
$params = [];

if ($q !== '') {
    $where[] = '(t.name LIKE ? OR t.description LIKE ?)';
    $types .= 'ss';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}
if ($category !== '') {
    $where[] = 't.category = ?';
    $types .= 's';
    $params[] = $category;
}
if ($rating > 0) {
    $where[] = 'COALESCE(r.avg_rating, 0) >= ?';
    $types .= 'i';
    $params[] = $rating;
}

$orderBy = 't.created_at DESC';
if ($sort === 'price_low') {
    $orderBy = 't.base_price ASC';
} elseif ($sort === 'price_high') {
    $orderBy = 't.base_price DESC';
} elseif ($sort === 'latest') {
    $orderBy = 't.created_at DESC';
}

$sql = "
    SELECT
      t.id,
      t.name,
      t.description,
      t.category,
      t.base_price,
      t.image_path,
      COALESCE(r.avg_rating, 0) AS avg_rating,
      COALESCE(r.review_count, 0) AS review_count
    FROM customization_templates t
    LEFT JOIN (
      SELECT oi.template_id, AVG(pr.rating) AS avg_rating, COUNT(*) AS review_count
      FROM product_reviews pr
      JOIN order_items oi ON oi.template_id = pr.product_id
      GROUP BY oi.template_id
    ) r ON r.template_id = t.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY $orderBy
";

$products = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$categories = [];
$catRes = $conn->query("SELECT DISTINCT category FROM customization_templates WHERE is_active = 1 AND category IS NOT NULL AND category <> '' ORDER BY category");
if ($catRes) {
    while ($row = $catRes->fetch_assoc()) {
        $categories[] = $row['category'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Advanced Search - RBJ Accessories</title>
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <style>
    body { margin:0; font-family: "Montserrat", sans-serif; background:#121212; color:#fff; padding-top:90px; }
    .navbar { position:fixed; top:0; left:0; right:0; display:flex; justify-content:space-between; align-items:center; padding:10px 50px; background:rgba(0,0,0,.85); z-index:999; }
    .logo { display:flex; align-items:center; gap:10px; color:#fff; text-decoration:none; font-weight:700; font-size:22px; }
    .logo img { height:60px; width:auto; }
    .nav-links { display:flex; align-items:center; gap:15px; }
    .nav-links a { color:#fff; text-decoration:none; margin-left:12px; }
    .search-wrap { max-width:1100px; margin:0 auto; padding:24px 16px; }
    .filters { background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.1); border-radius:14px; padding:14px; display:grid; grid-template-columns:2fr 1fr 1fr 1fr; gap:10px; }
    .filters input, .filters select, .filters button { padding:10px; border-radius:10px; border:1px solid rgba(255,255,255,.2); background:rgba(255,255,255,.07); color:#fff; }
    .filters button { background:#d90429; border-color:#d90429; cursor:pointer; font-weight:700; }
    .results { display:grid; grid-template-columns:repeat(auto-fill,minmax(230px,1fr)); gap:14px; margin-top:18px; }
    .card { background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.1); border-radius:14px; overflow:hidden; }
    .card img { width:100%; height:170px; object-fit:cover; display:block; background:#222; }
    .card .content { padding:12px; }
    .card h3 { margin:0 0 8px; font-size:16px; color:#f3c1bc; }
    .meta { font-size:13px; color:#ddd; margin-bottom:6px; }
    .price { font-weight:700; color:#ffd7d2; margin:8px 0; }
    .btn-row { display:flex; gap:8px; }
    .btn-row a { flex:1; text-align:center; text-decoration:none; color:#fff; background:#d90429; border-radius:8px; padding:8px; font-size:13px; }
  </style>
  <?php include __DIR__ . '/partials/user_navbar_theme.php'; ?>
</head>
<body>
  <nav class="navbar">
    <a href="index.php" class="logo"><img src="../rbjlogo.png" alt="RBJ"> <span>RBJ Accessories</span></a>
    <div class="nav-links">
      <a href="index.php">Home</a>
      <a href="catalog.php">Shop</a>
      <a href="customize.php">Customize</a>
      <?php if ($is_logged_in): ?>
        <a href="cart.php" class="nav-cart-link" title="Cart"><i class='bx bx-cart'></i></a>
        <?php include __DIR__ . '/partials/account_menu.php'; ?>
      <?php else: ?>
        <a href="../login.php">Login</a>
      <?php endif; ?>
    </div>
  </nav>

  <div class="search-wrap">
    <form id="searchForm" class="filters" method="GET">
      <input type="text" name="q" placeholder="Search products..." value="<?php echo htmlspecialchars($q); ?>">
      <select name="category">
        <option value="">All Categories</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $cat))); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <select name="sort" onchange="changeSort(this.value)">
        <option value="relevance" <?php echo $sort === 'relevance' ? 'selected' : ''; ?>>Relevance</option>
        <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price Low-High</option>
        <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price High-Low</option>
        <option value="latest" <?php echo $sort === 'latest' ? 'selected' : ''; ?>>Latest</option>
      </select>
      <div id="ratingFilter" style="display:flex; align-items:center; gap:6px; justify-content:center;">
        <input id="ratingInput" type="hidden" name="rating" value="<?php echo (int)$rating; ?>">
        <?php for ($i = 0; $i < 5; $i++): ?><span class="star" style="cursor:pointer;">★</span><?php endfor; ?>
      </div>
      <button type="submit">Search</button>
    </form>

    <div class="results">
      <?php if ($products): foreach ($products as $p): ?>
        <div class="card">
          <img src="<?php echo htmlspecialchars($p['image_path'] ?: '../rbjlogo.png'); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>">
          <div class="content">
            <h3><?php echo htmlspecialchars($p['name']); ?></h3>
            <div class="meta"><?php echo htmlspecialchars((string)$p['category']); ?> • <?php echo number_format((float)$p['avg_rating'], 1); ?>★ (<?php echo (int)$p['review_count']; ?>)</div>
            <div class="price">PHP <?php echo number_format((float)$p['base_price'], 2); ?></div>
            <div class="btn-row">
              <a href="product.php?id=<?php echo (int)$p['id']; ?>">View</a>
              <a href="#" onclick="addToCart(<?php echo (int)$p['id']; ?>); return false;">Add</a>
            </div>
          </div>
        </div>
      <?php endforeach; else: ?>
        <p>No products found.</p>
      <?php endif; ?>
    </div>
  </div>

  <script src="assets/user-advanced-search.js"></script>
  <?php include __DIR__ . '/partials/user_footer.php'; ?>
</body>
</html>



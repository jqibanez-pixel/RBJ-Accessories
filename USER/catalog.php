<?php
session_start();
include '../config.php';
require_once __DIR__ . '/shapi_catalog_helper.php';
rbj_ensure_cart_choice_key_column($conn);
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$toast_type = '';
$toast_message = '';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_to_cart'])) {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf_token, $posted_token)) {
        $_SESSION['catalog_toast'] = [
            'type' => 'error',
            'message' => "Invalid request token."
        ];
    } elseif (!isset($_SESSION['user_id'])) {
        $_SESSION['catalog_toast'] = [
            'type' => 'error',
            'message' => "You need to login to add items to cart."
        ];
    } else {
        $user_id = (int)$_SESSION['user_id'];
        $template_id = (int)($_POST['template_id'] ?? 0);
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));
        $customizations = trim($_POST['customizations'] ?? 'Standard package');
        $choice_key = trim((string)($_POST['choice_key'] ?? ''));

        $stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND template_id = ? AND customizations = ? AND COALESCE(choice_key, '') = ?");
        $stmt->bind_param("iiss", $user_id, $template_id, $customizations, $choice_key);
        $stmt->execute();
        $existing_item = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare("SELECT name, base_price FROM customization_templates WHERE id = ? AND is_active = 1");
        $stmt->bind_param("i", $template_id);
        $stmt->execute();
        $template = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$template) {
            $_SESSION['catalog_toast'] = [
                'type' => 'error',
                'message' => "Selected product is no longer available."
            ];
        } else {
            $stockInfo = rbj_resolve_item_stock($conn, $template_id, $customizations, $choice_key);
            $available = max(0, (int)($stockInfo['available'] ?? 0));
            $existingQty = (int)($existing_item['quantity'] ?? 0);
            $newQuantity = $existingQty + $quantity;
            if ($available <= 0) {
                $_SESSION['catalog_toast'] = [
                    'type' => 'error',
                    'message' => "Selected design is out of stock."
                ];
            } elseif ($newQuantity > $available) {
                $_SESSION['catalog_toast'] = [
                    'type' => 'error',
                    'message' => "Only {$available} stock left for this design."
                ];
            } else {
                if ($existing_item) {
                    $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
                    $stmt->bind_param("ii", $newQuantity, $existing_item['id']);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $price = (float)$template['base_price'];
                    $stmt = $conn->prepare("INSERT INTO cart (user_id, template_id, customizations, choice_key, quantity, price) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("iissid", $user_id, $template_id, $customizations, $choice_key, $quantity, $price);
                    $stmt->execute();
                    $stmt->close();
                }

                $_SESSION['catalog_toast'] = [
                    'type' => 'success',
                    'message' => '"' . $template['name'] . '" added to cart.'
                ];
            }
        }
    }

    header("Location: catalog.php");
    exit();
}

if (!empty($_SESSION['catalog_toast'])) {
    $toast_type = $_SESSION['catalog_toast']['type'] ?? '';
    $toast_message = $_SESSION['catalog_toast']['message'] ?? '';
    unset($_SESSION['catalog_toast']);
}

// Get filter parameters
$seat_categories = [
    'universal seat',
    'racing seat',
    'indo seat',
    'camel back seat',
    'flat seat',
    'jdm seat'
];
$category = isset($_GET['category']) ? strtolower(trim($_GET['category'])) : '';
if (!in_array($category, $seat_categories, true)) {
    $category = '';
}
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 0;
$rating = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 25;
$offset = ($page - 1) * $limit;

// Build query
$query = "SELECT t.* FROM customization_templates t WHERE t.is_active = 1";
$params = [];
$types = "";

if (!empty($category)) {
    $seat_type_keywords = [
        'universal seat' => ['universal'],
        'racing seat' => ['racing', 'race', 'sport'],
        'indo seat' => ['indo'],
        'camel back seat' => ['camel back', 'camelback'],
        'flat seat' => ['flat'],
        'jdm seat' => ['jdm']
    ];
    $keywords = $seat_type_keywords[$category] ?? [];
    if (!empty($keywords)) {
        $query .= " AND (";
        $categoryParts = [];
        foreach ($keywords as $keyword) {
            $categoryParts[] = "(LOWER(t.name) LIKE ? OR LOWER(t.description) LIKE ? OR LOWER(t.category) LIKE ?)";
            $kw = '%' . strtolower($keyword) . '%';
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
            $types .= "sss";
        }
        $query .= implode(" OR ", $categoryParts) . ")";
    }
}

if (!empty($search)) {
    $query .= " AND (t.name LIKE ? OR t.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "ss";
}

if ($min_price > 0) {
    $query .= " AND t.base_price >= ?";
    $params[] = $min_price;
    $types .= "d";
}
if ($max_price > 0) {
    $query .= " AND t.base_price <= ?";
    $params[] = $max_price;
    $types .= "d";
}

if ($rating > 0) {
    $query .= " AND t.id IN (
        SELECT r.order_id FROM reviews r
        JOIN orders o ON r.order_id = o.id
        WHERE o.customization LIKE CONCAT('%', t.name, '%')
        GROUP BY r.order_id
        HAVING AVG(r.rating) >= ?
    )";
    $params[] = $rating;
    $types .= "i";
}

// Add sorting
switch ($sort) {
    case 'price_low':
        $query .= " ORDER BY t.base_price ASC";
        break;
    case 'price_high':
        $query .= " ORDER BY t.base_price DESC";
        break;
    case 'newest':
        $query .= " ORDER BY t.created_at DESC";
        break;
    default:
        $query .= " ORDER BY LOWER(t.name) ASC";
}

// Get total count for pagination
$countQuery = str_replace("SELECT t.*", "SELECT COUNT(*) as total", $query);
$countStmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalProducts = 0;
if ($row = $countResult->fetch_assoc()) {
    $totalProducts = (int)$row['total'];
}
$countStmt->close();

// Calculate pagination
$totalPages = ceil($totalProducts / $limit);
$currentPage = $page;

// Add LIMIT and OFFSET to main query
$query .= " LIMIT $limit OFFSET $offset";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result();
$products_count = (int)$products->num_rows;

$stock_column = null;
$stock_candidates = ['stock', 'stock_quantity', 'quantity', 'available_stock', 'inventory_stock'];
$stock_columns_result = $conn->query("SHOW COLUMNS FROM customization_templates");
if ($stock_columns_result instanceof mysqli_result) {
    while ($stock_col = $stock_columns_result->fetch_assoc()) {
        $field = strtolower((string)($stock_col['Field'] ?? ''));
        if (in_array($field, $stock_candidates, true)) {
            $stock_column = $field;
            break;
        }
    }
}

$top_sales = [];
$topSalesSql = "
    SELECT
        t.id,
        t.name,
        t.base_price,
        t.image_path,
        COALESCE(SUM(oi.quantity), 0) AS sold_qty,
        MAX(o.created_at) AS latest_order_at
    FROM order_items oi
    INNER JOIN orders o ON o.id = oi.order_id
    INNER JOIN customization_templates t ON t.id = oi.template_id
    WHERE t.is_active = 1
      AND o.status IN ('pending', 'in_progress', 'completed')
    GROUP BY t.id, t.name, t.base_price, t.image_path
    ORDER BY sold_qty DESC, latest_order_at DESC
    LIMIT 6
";
$topStmt = $conn->prepare($topSalesSql);
if ($topStmt) {
    $topStmt->execute();
    $topResult = $topStmt->get_result();
    while ($row = $topResult->fetch_assoc()) {
        $top_sales[] = $row;
    }
    $topStmt->close();
}

// Get cart count if user is logged in
$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $cart_count = $result['total'] ?? 0;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Shop - MotoFit</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<style>
body { min-height:100vh; background: var(--rbj-bg, linear-gradient(135deg,#1b1b1b,#111)); color: var(--rbj-text, #fff); padding-top:100px; font-family:"Montserrat",sans-serif; }
.navbar { position:fixed; top:0; left:0; right:0; display:flex; justify-content:space-between; align-items:center; padding:10px 50px; background: var(--rbj-navbar-bg, rgba(0,0,0,0.8)); z-index:999; }
.navbar .logo { display:flex; align-items:center; gap:10px; color: var(--rbj-text, #fff); text-decoration:none; font-size:22px; font-weight:700; }
.navbar .logo img { height: 60px; width:auto; }
.navbar .nav-links { display:flex; align-items:center; gap:15px; }
.navbar .nav-links a { color: var(--rbj-text, #fff); text-decoration:none; font-weight:500; margin-left:15px; }
.navbar .nav-links a:hover { text-decoration:underline; }

.account-dropdown { display:flex; align-items:center; margin-left:15px; }
.account-icon { width:40px; height:40px; background:#27ae60; border-radius:50%; display:flex; justify-content:center; align-items:center; font-weight:bold; margin-right:5px; }
.account-username { font-weight:600; color: var(--rbj-text, #fff); }

.account-dropdown { position: relative; display: flex; align-items: center; }
.account-trigger { background: none; border: none; color: var(--rbj-text, #fff); display: flex; align-items: center; gap: 8px; cursor: pointer; }
.account-menu { position: absolute; top: 110%; right: 0; background: var(--rbj-menu-bg, #1e1e1e); border-radius: 10px; min-width: 200px; padding: 8px 0; display: none; box-shadow: 0 10px 30px rgba(0,0,0,0.4); z-index: 999; }
.account-menu a { display: flex; align-items: center; gap: 10px; padding: 10px 15px; color: var(--rbj-text, #fff); text-decoration: none; font-size: 14px; }
.account-menu a:hover { background: rgba(255,255,255,0.08); }
.account-dropdown.active .account-menu { display: block; }

.wrapper { max-width:1520px; margin:auto; padding:20px; }
.wrapper h1 { text-align:center; margin-bottom:16px; font-size:36px; background: linear-gradient(45deg, var(--rbj-accent, #e50914), var(--rbj-accent-strong, #ff1f2d)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }

.catalog-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
  color: var(--rbj-muted, rgba(255,255,255,0.84));
  font-size: 14px;
}
.catalog-meta .count { font-weight: 700; color: var(--rbj-text, #f4f4f4); }
.catalog-meta .clear-link {
  color: var(--rbj-text, rgba(255,255,255,0.9));
  text-decoration: none;
  border: 1px solid var(--rbj-border, rgba(255,255,255,0.2));
  padding: 7px 11px;
  border-radius: 10px;
}
.catalog-meta .clear-link:hover { background: rgba(255,255,255,0.08); }

.top-sales {
  margin-bottom: 18px;
  background: linear-gradient(145deg, rgba(238,77,45,0.18), rgba(0,0,0,0.68));
  border: 1px solid rgba(238,77,45,0.36);
  border-radius: 14px;
  padding: 14px;
}
.top-sales-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 10px;
}
.top-sales-title {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  font-weight: 800;
  color: #ffd8d0;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  font-size: 13px;
}
.top-sales-sub { color: rgba(255,255,255,0.76); font-size: 12px; }
.top-sales-grid {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 10px;
}
.top-sales-item {
  display: block;
  text-decoration: none;
  color: inherit;
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.11);
  border-radius: 10px;
  overflow: hidden;
}
.top-sales-item:hover { border-color: rgba(238,77,45,0.6); }
.top-sales-image {
  aspect-ratio: 1 / 1;
  background: #101010;
}
.top-sales-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
}
.top-sales-body {
  padding: 8px;
}
.top-sales-name {
  font-size: 12px;
  font-weight: 700;
  line-height: 1.25;
  height: 30px;
  overflow: hidden;
}
.top-sales-meta {
  margin-top: 5px;
  font-size: 11px;
  color: #ffb39f;
}

.filters {
  background: var(--rbj-surface-strong, rgba(0,0,0,0.6));
  border: 1px solid var(--rbj-border, rgba(255,255,255,0.08));
  padding: 16px;
  border-radius: 15px;
  margin-bottom: 22px;
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
}
.search-box { flex: 1; min-width: 200px; }
.search-box input { width: 100%; padding: 12px; border: 2px solid var(--rbj-border, rgba(255,255,255,0.5)); border-radius: 25px; background: transparent; color: var(--rbj-text, #fff); font-size: 16px; }
.search-box input::placeholder { color: var(--rbj-muted, rgba(255,255,255,0.7)); }
.filter-select { padding: 10px 15px; border: 2px solid var(--rbj-border, rgba(255,255,255,0.5)); border-radius: 20px; background: rgba(0,0,0,0.8); color: var(--rbj-text, #fff); }
.filter-select option { background: #2c3e50; color: #fff; }
.toggle-advanced { padding: 10px 14px; border: 1px solid var(--rbj-border, rgba(255,255,255,0.35)); border-radius: 20px; background: var(--rbj-surface-soft, rgba(255,255,255,0.08)); color: var(--rbj-text, #fff); cursor: pointer; font-weight: 600; }
.toggle-advanced:hover { background: rgba(255,255,255,0.14); }
.advanced-filters { display: none; width: 100%; padding-top: 6px; }
.advanced-filters.show { display: block; }
.price-range { display: flex; gap: 10px; }
.price-range input { width: 130px; padding: 10px; border: 2px solid var(--rbj-border, rgba(255,255,255,0.5)); border-radius: 12px; background: rgba(0,0,0,0.8); color: var(--rbj-text, #fff); }
.rating-stars { display: flex; gap: 8px; font-size: 24px; }
.rating-stars .star { cursor: pointer; color: rgba(255,255,255,0.35); transition: color 0.2s ease; }
.rating-stars .star.active { color: #f39c12; }

.products-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 14px; }
.product-card {
  background: var(--rbj-surface-strong, rgba(0,0,0,0.58));
  border: 1px solid var(--rbj-border, rgba(255,255,255,0.08));
  border-radius: 14px;
  overflow: hidden;
  transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
  position: relative;
}
.product-link {
  color: inherit;
  text-decoration: none;
  display: block;
}
.product-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 10px 20px rgba(0,0,0,0.34);
  border-color: rgba(217,4,41,0.35);
}
.product-image {
  aspect-ratio: 1 / 1;
  background: linear-gradient(145deg, #1b1b1b, #111);
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
}
.product-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
  display: block;
  transition: transform 0.3s ease;
}
.product-card:hover .product-image img {
  transform: scale(1.04);
}
.product-badge {
  position: absolute;
  top: 10px;
  left: 10px;
  padding: 6px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.2px;
  background: rgba(255,255,255,0.1);
  border: 1px solid var(--rbj-border, rgba(255,255,255,0.2));
  color: var(--rbj-text, #fff);
  backdrop-filter: blur(6px);
}
.product-content { padding: 12px; }
.product-content h3 {
  margin: 0 0 8px 0;
  color: var(--rbj-text, #f2f2f2);
  font-size: 14px;
  line-height: 1.3;
  min-height: 36px;
}
.product-price { font-size: 17px; font-weight: 700; color: var(--rbj-accent-strong, #ff1f2d); margin-bottom: 10px; }
.product-stock { font-size: 12px; color: var(--rbj-muted, #ffcfb8); }
.product-actions {
  margin-top: 10px;
  display: flex;
  gap: 8px;
}
.product-actions .btn-secondary {
  flex: 1;
}
.btn { padding: 9px 10px; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; text-decoration: none; display: inline-block; text-align: center; font-size: 13px; }
.btn-primary { background: linear-gradient(45deg, var(--rbj-accent, #e50914), var(--rbj-accent-strong, #ff1f2d)); color: white; }
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(229,9,20,0.35); }
.btn-secondary { background: var(--rbj-surface-soft, rgba(255,255,255,0.1)); color: var(--rbj-text, #fff); border: 1px solid var(--rbj-border, rgba(255,255,255,0.3)); }
.btn-secondary:hover { background: rgba(255,255,255,0.2); }

.sticky-customize-cta {
  position: fixed;
  right: 20px;
  bottom: 20px;
  z-index: 1100;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 16px;
  border-radius: 999px;
  border: 1px solid rgba(255,255,255,0.2);
  background: linear-gradient(45deg, var(--rbj-accent, #e50914), var(--rbj-accent-strong, #ff1f2d));
  color: #fff;
  font-weight: 700;
  text-decoration: none;
  box-shadow: 0 8px 20px rgba(0,0,0,0.35);
}
.sticky-customize-cta:hover {
  transform: translateY(-1px);
  box-shadow: 0 10px 24px rgba(0,0,0,0.4);
}

.empty-state { text-align: center; padding: 80px 20px; color: var(--rbj-muted, rgba(255,255,255,0.7)); }
.empty-state i { font-size: 64px; margin-bottom: 20px; display: block; }
.empty-state h2 { margin-bottom: 10px; }

.cart-count { background: #ef233c; color: white; border-radius: 50%; width: 20px; height: 20px; font-size: 12px; display: flex; align-items: center; justify-content: center; margin-left: 5px; }
.toast {
  position: fixed;
  top: 86px;
  right: 20px;
  min-width: 260px;
  max-width: 420px;
  padding: 12px 14px;
  border-radius: 10px;
  border: 1px solid transparent;
  color: #fff;
  z-index: 1200;
  opacity: 0;
  transform: translateY(-10px);
  transition: opacity 0.25s ease, transform 0.25s ease;
  box-shadow: 0 8px 24px rgba(0,0,0,0.35);
}
.toast.show { opacity: 1; transform: translateY(0); }
.toast.success { background: rgba(39,174,96,0.95); border-color: rgba(39,174,96,1); }
.toast.error { background: rgba(192,57,43,0.95); border-color: rgba(192,57,43,1); }

@media(max-width:768px){
  .filters{flex-direction:column; align-items:stretch;}
  .top-sales-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .products-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
  .navbar{padding:10px 20px;}
  .price-range input { width: 100%; }
  .catalog-meta { flex-direction: column; align-items: flex-start; }
  .sticky-customize-cta {
    right: 14px;
    bottom: 14px;
    padding: 11px 14px;
    font-size: 13px;
  }
}

@media (max-width: 1320px) {
  .products-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}

@media (max-width: 1040px) {
  .products-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  .top-sales-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}

/* Pagination Styles */
.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 8px;
  margin-top: 30px;
  padding: 20px 0;
  flex-wrap: wrap;
}
.pagination-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 40px;
  height: 40px;
  padding: 0 12px;
  border: 1px solid var(--rbj-border, rgba(255,255,255,0.2));
  border-radius: 8px;
  background: var(--rbj-surface-strong, rgba(0,0,0,0.5));
  color: var(--rbj-text, #fff);
  text-decoration: none;
  font-weight: 600;
  font-size: 14px;
  transition: all 0.2s ease;
}
.pagination-btn:hover:not(.active):not(:disabled) {
  background: rgba(229,9,20,0.14);
  border-color: rgba(229,9,20,0.45);
}
.pagination-btn.active {
  background: linear-gradient(45deg, var(--rbj-accent, #e50914), var(--rbj-accent-strong, #ff1f2d));
  border-color: var(--rbj-accent, #e50914);
  color: #fff;
}
.pagination-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.pagination-ellipsis {
  color: var(--rbj-muted, rgba(255,255,255,0.6));
  padding: 0 8px;
}
.pagination-info {
  text-align: center;
  color: var(--rbj-muted, rgba(255,255,255,0.7));
  font-size: 13px;
  margin-top: 15px;
}

html[data-theme="light"] .top-sales {
  background: linear-gradient(145deg, rgba(217,4,41,0.12), rgba(255,255,255,0.92));
  border-color: rgba(217,4,41,0.25);
}

html[data-theme="light"] .top-sales-title {
  color: var(--rbj-text);
}

html[data-theme="light"] .top-sales-sub,
html[data-theme="light"] .top-sales-meta {
  color: var(--rbj-muted);
}

html[data-theme="light"] .top-sales-item {
  background: #fff;
  border-color: rgba(217,4,41,0.18);
  box-shadow: 0 10px 20px rgba(217,4,41,0.08);
}

html[data-theme="light"] .top-sales-image {
  background: #f8f1f0;
}

html[data-theme="light"] .catalog-meta .clear-link {
  background: rgba(217,4,41,0.08);
}

html[data-theme="light"] .filters {
  box-shadow: 0 16px 30px rgba(217,4,41,0.08);
}

html[data-theme="light"] .filter-select,
html[data-theme="light"] .price-range input {
  background: #fff;
  color: var(--rbj-text);
  border-color: rgba(217,4,41,0.25);
}

html[data-theme="light"] .filter-select option {
  background: #fff;
  color: var(--rbj-text);
}

html[data-theme="light"] .rating-stars .star {
  color: rgba(217,4,41,0.25);
}

html[data-theme="light"] .product-card {
  background: #fff;
  box-shadow: 0 18px 30px rgba(217,4,41,0.12);
}

html[data-theme="light"] .product-image {
  background: linear-gradient(145deg, #f9f1f0, #fff);
}

html[data-theme="light"] .product-badge {
  background: rgba(217,4,41,0.1);
  color: var(--rbj-text);
  border-color: rgba(217,4,41,0.25);
}

html[data-theme="light"] .btn-secondary {
  background: rgba(217,4,41,0.08);
  color: var(--rbj-text);
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
    <a href="catalog.php" class="active">Shop</a>
    <a href="customize.php">Customize</a>
    <?php if (isset($_SESSION['user_id'])): ?>
      <a href="cart.php"><i class='bx bx-cart'></i><span class="cart-count" data-cart-count style="<?php echo $cart_count > 0 ? '' : 'display:none;'; ?>"><?php echo (int)$cart_count; ?></span></a>
      <?php include __DIR__ . '/partials/account_menu.php'; ?>
    <?php else: ?>
      <a href="../login.php">Login</a>
      <a href="../register.php">Register</a>
    <?php endif; ?>
  </div>
</nav>

<div class="wrapper">
  <h1>Shop</h1>
  <div class="catalog-meta">
    <span class="count">
    <?php if ($totalProducts > 0): ?>
        Showing <?php echo ($offset + 1); ?>-<?php echo min($offset + $limit, $totalProducts); ?> of <?php echo (int)$totalProducts; ?> products
    <?php else: ?>
        0 products found
    <?php endif; ?>
    </span>
    <a class="clear-link" href="catalog.php">Reset Filters</a>
  </div>
  <?php if (!empty($top_sales)): ?>
    <section class="top-sales" data-reveal>
      <div class="top-sales-head">
        <div class="top-sales-title"><i class='bx bxs-hot'></i> Latest Top Sales</div>
        <div class="top-sales-sub">Based on highest sold items</div>
      </div>
      <div class="top-sales-grid">
        <?php foreach ($top_sales as $top): ?>
          <?php
            $topImageUrl = rbj_template_image_url($top['image_path'] ?? '');
            if ($topImageUrl === '') {
                $topChoiceItems = rbj_find_shapi_choices((string)$top['name']);
                if (!empty($topChoiceItems)) {
                    $topImageUrl = (string)$topChoiceItems[0]['image_url'];
                }
            }
          ?>
          <a class="top-sales-item" href="product.php?id=<?php echo (int)$top['id']; ?>">
            <div class="top-sales-image">
              <?php if ($topImageUrl !== ''): ?>
                <img src="<?php echo htmlspecialchars($topImageUrl); ?>" alt="<?php echo htmlspecialchars((string)$top['name']); ?>">
              <?php else: ?>
                <i class='bx bx-image' style="font-size: 34px; color: rgba(255,255,255,0.5); display:flex; align-items:center; justify-content:center; width:100%; height:100%;"></i>
              <?php endif; ?>
            </div>
            <div class="top-sales-body">
              <div class="top-sales-name"><?php echo htmlspecialchars((string)$top['name']); ?></div>
              <div class="top-sales-meta"><?php echo (int)$top['sold_qty']; ?> sold</div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if (!empty($success)): ?>
    <div style="background: rgba(39,174,96,0.15); color:#2ecc71; border:1px solid rgba(46,204,113,0.5); padding:10px 14px; border-radius:10px; margin-bottom:20px;">
      <?php echo $success; ?>
    </div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div style="background: rgba(231,76,60,0.15); color:#ff8b8b; border:1px solid rgba(231,76,60,0.5); padding:10px 14px; border-radius:10px; margin-bottom:20px;">
      <?php echo htmlspecialchars($error); ?>
    </div>
  <?php endif; ?>

  <!-- Filters -->
  <div class="filters" data-reveal>
    <button type="button" class="toggle-advanced" id="toggleAdvanced">Show Advanced Filters</button>
    <div class="search-box">
      <form method="GET" action="catalog.php" id="filterForm" style="display: flex; gap: 10px;">
        <input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
        <select name="category" class="filter-select">
          <option value="">All Seat Types</option>
          <?php foreach ($seat_categories as $seat_type): ?>
            <option value="<?php echo htmlspecialchars($seat_type); ?>" <?php echo $category === $seat_type ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars(ucwords($seat_type)); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <select name="sort" class="filter-select">
          <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Name A-Z</option>
          <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
          <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
          <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
        </select>
        <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">Search</button>
      </form>
    </div>
    <div class="advanced-filters" id="advancedFilters">
      <div style="display: flex; gap: 20px; flex-wrap: wrap;">
        <div>
          <label style="display: block; margin-bottom: 5px; font-weight: 600;">Price Range</label>
          <div class="price-range">
            <input type="number" name="min_price" placeholder="Min PHP" step="0.01" value="<?php echo $min_price > 0 ? $min_price : ''; ?>" form="filterForm">
            <input type="number" name="max_price" placeholder="Max PHP" step="0.01" value="<?php echo $max_price > 0 ? $max_price : ''; ?>" form="filterForm">
          </div>
        </div>
        <div>
          <label style="display: block; margin-bottom: 5px; font-weight: 600;">Minimum Rating</label>
          <div class="rating-stars" id="ratingFilter">
            <i class='bx bx-star star' data-rating="1"></i>
            <i class='bx bx-star star' data-rating="2"></i>
            <i class='bx bx-star star' data-rating="3"></i>
            <i class='bx bx-star star' data-rating="4"></i>
            <i class='bx bx-star star' data-rating="5"></i>
          </div>
          <input type="hidden" name="rating" id="ratingInput" value="<?php echo $rating; ?>" form="filterForm">
        </div>
      </div>
    </div>
  </div>

  <!-- Products Grid -->
  <?php if ($products->num_rows > 0): ?>
    <div class="products-grid" data-reveal>
      <?php while ($product = $products->fetch_assoc()): ?>
        <div class="product-card">
          <a class="product-link" href="product.php?id=<?php echo (int)$product['id']; ?>">
            <div class="product-image">
              <?php
                $cardImageUrl = rbj_template_image_url($product['image_path'] ?? '');
                $choiceItems = [];
                if ($cardImageUrl === '') {
                    $choiceItems = rbj_find_shapi_choices((string)$product['name']);
                    if (!empty($choiceItems)) {
                        $cardImageUrl = (string)$choiceItems[0]['image_url'];
                    }
                }
                $availableStock = 0;
                if ($stock_column !== null && isset($product[$stock_column]) && is_numeric($product[$stock_column])) {
                    $availableStock = max(0, (int)$product[$stock_column]);
                } else {
                    $availableStock = 1;
                }
              $badgeText = $availableStock <= 0 ? 'Out of stock' : ($availableStock <= 5 ? 'Limited' : 'In stock');
                ?>
              <span class="product-badge"><?php echo $badgeText; ?></span>
              <?php if ($cardImageUrl !== ''): ?>
                <img src="<?php echo htmlspecialchars($cardImageUrl); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
              <?php else: ?>
                <i class='bx bx-image' style="font-size: 48px; color: rgba(255,255,255,0.5);"></i>
              <?php endif; ?>
            </div>
          </a>
          <div class="product-content">
            <h3><a class="product-link" href="product.php?id=<?php echo (int)$product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?></a></h3>
            <div class="product-price">PHP <?php echo number_format($product['base_price'], 2); ?></div>
            <div class="product-stock"><?php echo (int)$availableStock; ?> available</div>
            <div class="product-actions">
              <a class="btn btn-secondary" href="product.php?id=<?php echo (int)$product['id']; ?>">View</a>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
  <?php else: ?>
    <div class="empty-state" data-reveal>
      <i class='bx bx-search-alt'></i>
      <h2>No products found</h2>
      <p>Try adjusting your search criteria or browse all products.</p>
      <a href="catalog.php" class="btn btn-primary" style="margin-top: 20px;">Clear Filters</a>
    </div>
  <?php endif; ?>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
    <div class="pagination">
      <?php
      // Build query string for pagination links (preserve all filters)
      function buildPaginationUrl($pageNum) {
        $params = $_GET;
        $params['page'] = $pageNum;
        return '?' . http_build_query($params);
      }
      
      $visiblePages = 5; // Number of page buttons to show
      $startPage = max(1, $currentPage - floor($visiblePages / 2));
      $endPage = min($totalPages, $startPage + $visiblePages - 1);
      
      // Adjust start if we're near the end
      if ($endPage - $startPage + 1 < $visiblePages) {
        $startPage = max(1, $endPage - $visiblePages + 1);
      }
      ?>
      
      <!-- Previous button -->
      <?php if ($currentPage > 1): ?>
        <a href="<?php echo buildPaginationUrl($currentPage - 1); ?>" class="pagination-btn">
          <i class='bx bx-chevron-left'></i>
        </a>
      <?php else: ?>
        <span class="pagination-btn" style="disabled"><i class='bx bx-chevron-left'></i></span>
      <?php endif; ?>

      <!-- First page + ellipsis -->
      <?php if ($startPage > 1): ?>
        <a href="<?php echo buildPaginationUrl(1); ?>" class="pagination-btn">1</a>
        <?php if ($startPage > 2): ?>
          <span class="pagination-ellipsis">...</span>
        <?php endif; ?>
      <?php endif; ?>

      <!-- Page numbers -->
      <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
        <?php if ($i == $currentPage): ?>
          <span class="pagination-btn active"><?php echo $i; ?></span>
        <?php else: ?>
          <a href="<?php echo buildPaginationUrl($i); ?>" class="pagination-btn"><?php echo $i; ?></a>
        <?php endif; ?>
      <?php endfor; ?>

      <!-- Last page + ellipsis -->
      <?php if ($endPage < $totalPages): ?>
        <?php if ($endPage < $totalPages - 1): ?>
          <span class="pagination-ellipsis">...</span>
        <?php endif; ?>
        <a href="<?php echo buildPaginationUrl($totalPages); ?>" class="pagination-btn"><?php echo $totalPages; ?></a>
      <?php endif; ?>

      <!-- Next button -->
      <?php if ($currentPage < $totalPages): ?>
        <a href="<?php echo buildPaginationUrl($currentPage + 1); ?>" class="pagination-btn">
          <i class='bx bx-chevron-right'></i>
        </a>
      <?php else: ?>
        <span class="pagination-btn"><i class='bx bx-chevron-right'></i></span>
      <?php endif; ?>
    </div>
    <div class="pagination-info">
      Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?>
    </div>
  <?php endif; ?>
</div>

<a class="sticky-customize-cta" href="customize.php">
  <i class='bx bx-palette'></i>
  Customize Now
</a>

<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>
<script>
window.RBJ_CATALOG_CONFIG = {
  toastMessage: <?php echo json_encode($toast_message); ?>,
  toastType: <?php echo json_encode($toast_type); ?>
};
</script>
<script src="assets/user-catalog.js"></script>
<?php include __DIR__ . '/partials/user_footer.php'; ?>

</body>
</html>


















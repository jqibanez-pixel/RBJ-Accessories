<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
require_once __DIR__ . '/shapi_catalog_helper.php';
rbj_ensure_cart_choice_key_column($conn);

$user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
$toast_type = '';
$toast_message = '';
$checkout_message_for_seller = trim((string)($_POST['message_for_seller'] ?? ''));

if (!empty($_SESSION['cart_toast'])) {
    $toast_type = $_SESSION['cart_toast']['type'] ?? '';
    $toast_message = $_SESSION['cart_toast']['message'] ?? '';
    unset($_SESSION['cart_toast']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['update_quantity']) || isset($_POST['remove_item']) || isset($_POST['update_variant']) || isset($_POST['checkout']))) {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf_token, $posted_token)) {
        http_response_code(400);
        die('Invalid request token.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $template_id = (int)($_POST['template_id'] ?? 0);
    $customizations = trim($_POST['customizations'] ?? 'Standard package');
    $choice_key = trim((string)($_POST['choice_key'] ?? ''));
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));

    $stmt = $conn->prepare('SELECT id, quantity FROM cart WHERE user_id = ? AND template_id = ? AND customizations = ? AND COALESCE(choice_key, \'\') = ?');
    $stmt->bind_param('iiss', $user_id, $template_id, $customizations, $choice_key);
    $stmt->execute();
    $existing_item = $stmt->get_result()->fetch_assoc();

    $stmt = $conn->prepare('SELECT base_price, name FROM customization_templates WHERE id = ? AND is_active = 1');
    $stmt->bind_param('i', $template_id);
    $stmt->execute();
    $template = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$template) {
        $_SESSION['cart_toast'] = [
            'type' => 'error',
            'message' => 'Selected product is no longer available.'
        ];
    } else {
        $stockInfo = rbj_resolve_item_stock($conn, $template_id, $customizations, $choice_key);
        $available = max(0, (int)($stockInfo['available'] ?? 0));
        $existingQty = (int)($existing_item['quantity'] ?? 0);
        $newQuantity = $existingQty + $quantity;

        if ($available <= 0) {
            $_SESSION['cart_toast'] = [
                'type' => 'error',
                'message' => 'Selected design is out of stock.'
            ];
        } elseif ($newQuantity > $available) {
            $_SESSION['cart_toast'] = [
                'type' => 'error',
                'message' => 'Only ' . $available . ' stock left for this design.'
            ];
        } else {
            if ($existing_item) {
                $stmt = $conn->prepare('UPDATE cart SET quantity = ? WHERE id = ?');
                $stmt->bind_param('ii', $newQuantity, $existing_item['id']);
                $stmt->execute();
                $stmt->close();
            } else {
                $price = (float)($template['base_price'] ?? 0);
                $stmt = $conn->prepare('INSERT INTO cart (user_id, template_id, customizations, choice_key, quantity, price) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('iissid', $user_id, $template_id, $customizations, $choice_key, $quantity, $price);
                $stmt->execute();
                $stmt->close();
            }

            $_SESSION['cart_toast'] = [
                'type' => 'success',
                'message' => 'Item added to cart!'
            ];
        }
    }

    header('Location: cart.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_quantity'])) {
    $cart_id = (int)($_POST['cart_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 1);

    if ($quantity > 0) {
        $stmt = $conn->prepare('SELECT template_id, customizations, COALESCE(choice_key, \'\') AS choice_key FROM cart WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->bind_param('ii', $cart_id, $user_id);
        $stmt->execute();
        $cartRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($cartRow) {
            $stockInfo = rbj_resolve_item_stock(
                $conn,
                (int)$cartRow['template_id'],
                (string)($cartRow['customizations'] ?? 'Standard package'),
                (string)($cartRow['choice_key'] ?? '')
            );
            $available = max(0, (int)($stockInfo['available'] ?? 0));
            if ($available <= 0) {
                $stmt = $conn->prepare('DELETE FROM cart WHERE id = ? AND user_id = ?');
                $stmt->bind_param('ii', $cart_id, $user_id);
                $stmt->execute();
                $stmt->close();
                $_SESSION['cart_toast'] = [
                    'type' => 'error',
                    'message' => 'Selected design is out of stock and was removed from cart.'
                ];
                header('Location: cart.php');
                exit();
            }
            if ($quantity > $available) {
                $quantity = $available;
                $_SESSION['cart_toast'] = [
                    'type' => 'error',
                    'message' => 'Quantity adjusted to available stock (' . $available . ').'
                ];
            }
        }

        $stmt = $conn->prepare('UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?');
        $stmt->bind_param('iii', $quantity, $cart_id, $user_id);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare('DELETE FROM cart WHERE id = ? AND user_id = ?');
        $stmt->bind_param('ii', $cart_id, $user_id);
        $stmt->execute();
        $stmt->close();
    }

    if (empty($_SESSION['cart_toast'])) {
        $_SESSION['cart_toast'] = [
            'type' => 'success',
            'message' => $quantity > 0 ? 'Cart updated.' : 'Item removed from cart.'
        ];
    }
    header('Location: cart.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_item'])) {
    $cart_id = (int)($_POST['cart_id'] ?? 0);
    $stmt = $conn->prepare('DELETE FROM cart WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $cart_id, $user_id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['cart_toast'] = [
        'type' => 'success',
        'message' => 'Item removed from cart.'
    ];
    header('Location: cart.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_variant'])) {
    $cart_id = (int)($_POST['cart_id'] ?? 0);
    $template_id = (int)($_POST['template_id'] ?? 0);
    $new_customizations = trim($_POST['customizations'] ?? 'Standard package');
    $new_choice_key = trim((string)($_POST['choice_key'] ?? ''));
    $choice_image_url = trim((string)($_POST['choice_image_url'] ?? ''));

    // Get current cart item
    $stmt = $conn->prepare('SELECT quantity FROM cart WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->bind_param('ii', $cart_id, $user_id);
    $stmt->execute();
    $cartRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($cartRow) {
        $currentQty = (int)$cartRow['quantity'];
        
        // Check stock for new variant
        $stockInfo = rbj_resolve_item_stock($conn, $template_id, $new_customizations, $new_choice_key);
        $available = max(0, (int)($stockInfo['available'] ?? 0));

        if ($available <= 0) {
            $_SESSION['cart_toast'] = [
                'type' => 'error',
                'message' => 'Selected variant is out of stock.'
            ];
        } else {
            // Update the cart item with new variant
            $newQty = min($currentQty, $available);
            
            $stmt = $conn->prepare('UPDATE cart SET customizations = ?, choice_key = ? WHERE id = ? AND user_id = ?');
            $stmt->bind_param('ssii', $new_customizations, $new_choice_key, $cart_id, $user_id);
            $stmt->execute();
            $stmt->close();
            
            // Update the image URL in product_images if provided
            if ($choice_image_url !== '') {
                // The display image will be updated when loading the cart
            }

            if ($newQty < $currentQty) {
                $_SESSION['cart_toast'] = [
                    'type' => 'warning',
                    'message' => 'Quantity adjusted to ' . $newQty . ' due to available stock.'
                ];
            } else {
                $_SESSION['cart_toast'] = [
                    'type' => 'success',
                    'message' => 'Variant updated successfully!'
                ];
            }
        }
    }
    
    header('Location: cart.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $redirect = 'Location: buy_now.php?source=cart';
    if ($checkout_message_for_seller !== '') {
        $redirect .= '&message_for_seller=' . urlencode($checkout_message_for_seller);
    }
    header($redirect);
    exit();
}

$stmt = $conn->prepare('
    SELECT c.*, COALESCE(c.choice_key, \'\') AS choice_key, t.name, t.image_path, t.base_price
    FROM cart c
    JOIN customization_templates t ON c.template_id = t.id
    WHERE c.user_id = ?
    ORDER BY c.added_at DESC
');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$cart_items = $stmt->get_result();

$total_items = 0;
$total_price = 0;
$cart_data = [];
while ($item = $cart_items->fetch_assoc()) {
    $imageUrl = rbj_template_image_url((string)($item['image_path'] ?? ''));
    $customizationLabel = trim((string)($item['customizations'] ?? ''));
    if ($customizationLabel !== '' && strcasecmp($customizationLabel, 'Standard package') !== 0) {
        $choiceItems = rbj_find_shapi_choices((string)($item['name'] ?? ''));
        $wanted = rbj_shapi_normalize($customizationLabel);
        foreach ($choiceItems as $choice) {
            $choiceLabel = rbj_shapi_normalize((string)($choice['label'] ?? ''));
            if ($choiceLabel === $wanted || strpos($choiceLabel, $wanted) !== false || strpos($wanted, $choiceLabel) !== false) {
                $imageUrl = (string)($choice['image_url'] ?? $imageUrl);
                break;
            }
        }
    }
    $item['display_image_url'] = $imageUrl;
    
    // Get stock info for this cart item (Shopee-style slot check)
    $stockInfo = rbj_resolve_item_stock(
        $conn,
        (int)$item['template_id'],
        (string)($item['customizations'] ?? 'Standard package'),
        (string)($item['choice_key'] ?? '')
    );
    $item['available_stock'] = max(0, (int)($stockInfo['available'] ?? 0));
    
    $cart_data[] = $item;
    $total_items += (int)$item['quantity'];
    $total_price += (float)$item['price'] * (int)$item['quantity'];
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Shopping Cart - MotoFit</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<style>
body { min-height:100vh; background: var(--rbj-bg, linear-gradient(135deg,#1b1b1b,#111)); color: var(--rbj-text, #fff); padding-top:100px; font-family:"Montserrat",sans-serif; }
.navbar { position:fixed; top:0; left:0; right:0; display:flex; justify-content:space-between; align-items:center; padding:10px 50px; background: rgba(0,0,0,0.8); z-index:999; }
.navbar .logo { display:flex; align-items:center; gap:10px; color:inherit; text-decoration:none; font-size:22px; font-weight:700; }
.navbar .logo img { height: 60px; width:auto; }
.navbar .nav-links { display:flex; align-items:center; gap:15px; }
.navbar .nav-links a { color:inherit; text-decoration:none; font-weight:500; margin-left:15px; }
.navbar .nav-links a:hover { text-decoration:underline; }
.navbar, .navbar .nav-links { overflow: visible; }

.account-dropdown { display:flex; align-items:center; margin-left:15px; position: relative; }
.account-trigger { background: none; border: none; color: white; display: flex; align-items: center; gap: 8px; cursor: pointer; }
.account-icon {
  width: 40px;
  height: 40px;
  min-width: 40px;
  min-height: 40px;
  border-radius: 50%;
  background: #27ae60;
  color: #fff;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}
.account-username { color: #fff; font-weight: 600; }
.account-menu { position: absolute; top: 110%; right: 0; background: #1e1e1e; border-radius: 10px; min-width: 200px; padding: 8px 0; display: none; box-shadow: 0 10px 30px rgba(0,0,0,0.4); z-index: 999; }
.account-menu a { display: flex; align-items: center; gap: 10px; padding: 10px 15px; color: white; text-decoration: none; font-size: 14px; }
.account-menu a:hover { background: rgba(255,255,255,0.08); }
.account-dropdown.active .account-menu { display: block; }
.cart-count { background: #ef233c; color: white; border-radius: 50%; width: 20px; height: 20px; font-size: 12px; display: flex; align-items: center; justify-content: center; margin-left: 5px; }

.wrapper { max-width:1200px; margin:auto; padding:20px; }
.wrapper h1 { text-align:center; margin-bottom:30px; font-size:32px; }

.cart-container { display:grid; grid-template-columns: 2fr 1fr; gap:30px; }
.cart-items { background: rgba(0,0,0,0.6); border-radius: 15px; padding: 30px; overflow: visible; }
.cart-item { display:flex; align-items:center; gap:20px; padding:20px 0; border-bottom:1px solid rgba(255,255,255,0.1); }
.cart-item-checkbox { width: 22px; height: 22px; cursor: pointer; accent-color: #27ae60; }
.cart-item:last-child { border-bottom:none; }
.cart-item img { width:80px; height:80px; border-radius:10px; object-fit:cover; }
.cart-item-details { flex:1; position: relative; overflow: visible; }
.cart-item h3 { margin-bottom:5px; color:#27ae60; }
.cart-item p { margin:2px 0; font-size:14px; color:rgba(255,255,255,0.8); }
.slot-indicator { display:inline-flex; align-items:center; gap:4px; font-size:12px; margin-top:4px; padding:3px 8px; border-radius:4px; font-weight:600; }
.slot-indicator.stock-good { background:rgba(39,174,96,0.15); color:#2ecc71; }
.slot-indicator.stock-low { background:rgba(243,156,18,0.15); color:#f39c12; }
.slot-indicator.stock-critical { background:rgba(231,76,60,0.15); color:#ef233c; }
.slot-indicator i { font-size:14px; }
.variant-selector { position: relative; }
.variant-btn { display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; color: white; cursor: pointer; font-size: 12px; transition: all 0.2s; }
.variant-btn:hover { background: rgba(255,255,255,0.12); border-color: rgba(255,255,255,0.3); }
.variant-btn i { transition: transform 0.2s; }
.variant-btn.active i { transform: rotate(180deg); }
.variant-dropdown { display: none; position: absolute; top: 100%; left: 0; z-index: 1000; min-width: 220px; max-height: 250px; overflow-y: auto; background: #1e1e1e; border: 1px solid rgba(255,255,255,0.15); border-radius: 10px; box-shadow: 0 8px 24px rgba(0,0,0,0.4); margin-top: 4px; }
.variant-dropdown.show { display: block; }
.variant-option { display: flex; align-items: center; justify-content: space-between; padding: 10px 12px; cursor: pointer !important; transition: background 0.15s; border-bottom: 1px solid rgba(255,255,255,0.05); }
.variant-option:last-child { border-bottom: none; }
.variant-option:hover:not(.disabled) { background: rgba(255,255,255,0.08); }
.variant-option.selected { background: rgba(39,174,96,0.15); }
.variant-option.disabled { opacity: 0.5; cursor: not-allowed; pointer-events: none; }
.variant-info { display: flex; flex-direction: column; gap: 2px; }
.variant-label { font-size: 13px; color: white; }
.variant-stock { font-size: 11px; color: #2ecc71; }
.variant-stock.low { color: #f39c12; }
.variant-stock.out { color: #ef233c; }
.variant-option i.bx-check { color: #2ecc71; font-size: 18px; }
.quantity-controls { display:flex; align-items:center; gap:10px; }
.quantity-controls button { background:#27ae60; border:none; color:white; width:30px; height:30px; border-radius:50%; cursor:pointer; }
.quantity-controls input { width:50px; text-align:center; background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.3); border-radius:5px; color:white; }
.remove-btn { background:#ef233c; color:white; border:none; padding:5px 10px; border-radius:5px; cursor:pointer; }
.remove-btn:hover { background:#b80721; }

.cart-summary { background: rgba(0,0,0,0.6); border-radius: 15px; padding: 30px; height:fit-content; }
.cart-summary h2 { color:#27ae60; margin-bottom:20px; }
.summary-card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 16px; margin-bottom: 15px; }
.summary-row-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.summary-row-flex:last-child { margin-bottom: 0; }
.summary-label { color: rgba(255,255,255,0.7); font-size: 14px; }
.summary-value { color: #fff; font-weight: 600; font-size: 15px; }
.shipping-row { flex-direction: column; align-items: flex-start; gap: 10px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.1); margin-top: 5px; }
.shipping-info { display: flex; align-items: center; gap: 8px; color: rgba(255,255,255,0.7); font-size: 14px; }
.shipping-info i { font-size: 18px; color: #27ae60; }
.shipping-options { display: flex; flex-direction: column; gap: 6px; width: 100%; }
.shipping-option { display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; font-size: 13px; color: rgba(255,255,255,0.8); }
.shipping-option:hover { background: rgba(39,174,96,0.1); border-color: rgba(39,174,96,0.3); }
.summary-total-row { display: flex; justify-content: space-between; align-items: center; padding: 15px 0; border-top: 2px solid rgba(255,255,255,0.2); margin-bottom: 15px; }
.total-label { color: rgba(255,255,255,0.9); font-size: 16px; font-weight: 600; }
.total-value { color: #27ae60; font-size: 22px; font-weight: 700; }
.summary-field { margin-top: 12px; }
.summary-field label { display:block; margin-bottom:6px; font-size:13px; color:rgba(255,255,255,0.82); }
.summary-field textarea {
  width: 100%;
  min-height: 78px;
  border: 1px solid rgba(255,255,255,0.28);
  border-radius: 8px;
  background: rgba(255,255,255,0.06);
  color: #fff;
  padding: 8px 10px;
  resize: vertical;
}
.checkout-btn { width:100%; background:linear-gradient(45deg,#27ae60,#2ecc71); color:white; border:none; padding:15px; border-radius:10px; font-weight:600; cursor:pointer; margin-top:20px; }
.checkout-btn:hover { transform:translateY(-2px); box-shadow:0 5px 15px rgba(39,174,96,0.4); }

.empty-cart { text-align:center; padding:80px 20px; color:rgba(255,255,255,0.7); }
.empty-cart i { font-size:64px; margin-bottom:20px; display:block; }
.empty-cart h2 { margin-bottom:10px; }
.empty-cart p { margin-bottom:20px; }
.shop-btn { display:inline-block; background:#27ae60; color:white; padding:12px 24px; border-radius:25px; text-decoration:none; }
.shop-btn:hover { background:#2ecc71; }

.toast {
  position: fixed;
  top: 86px;
  right: 20px;
  min-width: 260px;
  max-width: 420px;
  padding: 12px 14px;
  border-radius: 10px;
  border: 1px solid transparent;
  color: white;
  z-index: 1200;
  opacity: 0;
  transform: translateY(-10px);
  transition: opacity 0.25s ease, transform 0.25s ease;
  box-shadow: 0 8px 24px rgba(0,0,0,0.35);
}
.toast.show { opacity: 1; transform: translateY(0); }
.toast.success { background: rgba(39,174,96,0.95); border-color: rgba(39,174,96,1); }
.toast.error { background: rgba(192,57,43,0.95); border-color: rgba(192,57,43,1); }
.toast.warning { background: rgba(243,156,18,0.95); border-color: rgba(243,156,18,1); }

html[data-theme="light"] .cart-items,
html[data-theme="light"] .cart-summary {
  background: rgba(255,255,255,0.96);
  border: 1px solid rgba(217,4,41,0.2);
  color: #7a211b;
}

html[data-theme="light"] .cart-item {
  border-bottom-color: rgba(217,4,41,0.16);
}

html[data-theme="light"] .cart-item h3,
html[data-theme="light"] .cart-summary h2,
html[data-theme="light"] .total-value {
  color: #d90429;
}

html[data-theme="light"] .cart-item p,
html[data-theme="light"] .summary-label,
html[data-theme="light"] .shipping-info,
html[data-theme="light"] .empty-cart,
html[data-theme="light"] .summary-field label {
  color: #9f4b43;
}

html[data-theme="light"] .summary-value,
html[data-theme="light"] .total-label {
  color: #7a211b;
}

html[data-theme="light"] .summary-card {
  background: rgba(255,255,255,0.88);
  border-color: rgba(217,4,41,0.18);
}

html[data-theme="light"] .shipping-row,
html[data-theme="light"] .summary-total-row {
  border-top-color: rgba(217,4,41,0.2);
}

html[data-theme="light"] .shipping-option {
  background: #fff;
  border-color: rgba(217,4,41,0.24);
  color: #7a211b;
}

html[data-theme="light"] .shipping-option:hover {
  background: #fff5f3;
  border-color: rgba(217,4,41,0.36);
}

html[data-theme="light"] .quantity-controls input,
html[data-theme="light"] .summary-field textarea {
  background: #fff;
  color: #7a211b;
  border-color: rgba(217,4,41,0.28);
}

html[data-theme="light"] .variant-btn {
  background: #fff;
  border-color: rgba(217,4,41,0.28);
  color: #7a211b;
}

html[data-theme="light"] .variant-btn:hover {
  background: #fff5f3;
  border-color: rgba(217,4,41,0.4);
}

html[data-theme="light"] .variant-dropdown {
  background: #fff;
  border-color: rgba(217,4,41,0.24);
}

html[data-theme="light"] .variant-option {
  border-bottom-color: rgba(217,4,41,0.12);
}

html[data-theme="light"] .variant-option:hover:not(.disabled) {
  background: rgba(217,4,41,0.1);
}

html[data-theme="light"] .variant-label {
  color: #7a211b;
}

html[data-theme="light"] .cart-items > div:first-child {
  border-bottom-color: rgba(217,4,41,0.2) !important;
}

html[data-theme="light"] #selected-count {
  color: #9f4b43 !important;
}

@media(max-width:768px){ .cart-container{grid-template-columns:1fr;} .cart-item{flex-direction:column; text-align:center;} .navbar{padding:10px 20px;} }
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
    <a href="cart.php" class="active"><i class='bx bx-cart'></i><span class="cart-count" data-cart-count style="<?php echo $total_items > 0 ? '' : 'display:none;'; ?>"><?php echo (int)$total_items; ?></span></a>
    <?php include __DIR__ . '/partials/account_menu.php'; ?>
  </div>
</nav>

<div class="wrapper">
  <h1>Shopping Cart</h1>

  <?php if (empty($cart_data)): ?>
    <div class="empty-cart">
      <i class='bx bx-cart'></i>
      <h2>Your cart is empty</h2>
      <p>Browse the shop and add items to your cart.</p>
      <a href="catalog.php" class="shop-btn">Browse Shop</a>
    </div>
  <?php else: ?>
    <div class="cart-container">
<div class="cart-items">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:15px;padding-bottom:10px;border-bottom:1px solid rgba(255,255,255,0.1);">
          <input type="checkbox" id="select-all" class="cart-item-checkbox" checked onchange="toggleAllItems(this)">
          <label for="select-all" style="cursor:pointer;font-weight:600;">Select All</label>
          <span id="selected-count" style="margin-left:auto;color:rgba(255,255,255,0.7);font-size:14px;"></span>
        </div>
        <h2>Cart Items</h2>
<?php foreach ($cart_data as $item): ?>
          <div class="cart-item">
<input type="checkbox" name="selected_items[]" value="<?php echo (int)$item['id']; ?>" class="cart-item-checkbox" checked onchange="updateSelectedCount()">
            <?php if (!empty($item['display_image_url'])): ?>
              <img src="<?php echo htmlspecialchars((string)$item['display_image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
            <?php else: ?>
              <div style="width:80px;height:80px;border-radius:10px;background:rgba(255,255,255,0.08);display:flex;align-items:center;justify-content:center;">
                <i class='bx bx-image' style="font-size:28px;color:rgba(255,255,255,0.55);"></i>
              </div>
            <?php endif; ?>
<div class="cart-item-details">
              <h3><?php echo htmlspecialchars($item['name']); ?></h3>
              <?php 
                // Get available choices for this product
                $choiceItems = rbj_find_shapi_choices((string)($item['name'] ?? ''));
                $currentChoiceKey = trim((string)($item['choice_key'] ?? ''));
                $currentCustomization = trim((string)($item['customizations'] ?? ''));
                
                // Get stock for each choice
                $availableChoices = [];
                if (!empty($choiceItems)) {
                    foreach ($choiceItems as $choice) {
                        $choiceLabel = (string)($choice['label'] ?? '');
                        $choiceKey = rbj_choice_key_from_label($choiceLabel, (string)($choice['image_url'] ?? ''));
                        $choiceStockInfo = rbj_resolve_item_stock($conn, (int)$item['template_id'], $choiceLabel, $choiceKey);
                        $choiceStock = max(0, (int)($choiceStockInfo['available'] ?? 0));
                        $availableChoices[] = [
                            'label' => $choiceLabel,
                            'key' => $choiceKey,
                            'stock' => $choiceStock,
                            'image_url' => (string)($choice['image_url'] ?? '')
                        ];
                    }
                }
                
                $availableStock = (int)($item['available_stock'] ?? 0);
                $slotClass = 'stock-good';
                $slotIcon = 'bx-check-circle';
                $slotText = $availableStock . ' slot(s) available';
                if ($availableStock <= 0) {
                  $slotClass = 'stock-critical';
                  $slotIcon = 'bx-x-circle';
                  $slotText = 'Out of stock';
                } elseif ($availableStock <= 5) {
                  $slotClass = 'stock-critical';
                  $slotIcon = 'bx-error';
                  $slotText = 'Only ' . $availableStock . ' slot(s) left!';
                } elseif ($availableStock <= 10) {
                  $slotClass = 'stock-low';
                  $slotIcon = 'bx-warning';
                  $slotText = $availableStock . ' slot(s) left';
                }
              ?>
              <p><?php echo htmlspecialchars($item['customizations']); ?></p>
              <p>&#8369;<?php echo number_format((float)$item['price'], 2); ?> each</p>
              <div class="slot-indicator <?php echo $slotClass; ?>">
                <i class='bx <?php echo $slotIcon; ?>'></i>
                <span><?php echo $slotText; ?></span>
              </div>
              
<?php if (!empty($availableChoices) && count($availableChoices) > 1): ?>
              <div class="variant-selector" style="margin-top:8px;">
                <button type="button" class="variant-btn" onclick="toggleVariantDropdown(<?php echo (int)$item['id']; ?>)">
                  <span><?php echo htmlspecialchars($currentCustomization); ?></span>
                  <i class='bx bx-chevron-down'></i>
                </button>
                <div class="variant-dropdown" id="variant-dropdown-<?php echo (int)$item['id']; ?>">
                  <?php foreach ($availableChoices as $choice): ?>
                    <?php 
                      $isSelected = (rbj_shapi_normalize($choice['label']) === rbj_shapi_normalize($currentCustomization));
                      $stockStatus = $choice['stock'];
                      $disabled = $stockStatus <= 0;
                    ?>
                    <div class="variant-option <?php echo $isSelected ? 'selected' : ''; ?> <?php echo $disabled ? 'disabled' : ''; ?>" 
                         data-cart-id="<?php echo (int)$item['id']; ?>"
                         data-template-id="<?php echo (int)$item['template_id']; ?>"
                         data-choice-label="<?php echo htmlspecialchars($choice['label']); ?>"
                         data-choice-key="<?php echo htmlspecialchars($choice['key']); ?>"
                         data-choice-image="<?php echo htmlspecialchars($choice['image_url']); ?>"
                         <?php if (!$disabled): ?>
                         onclick="selectVariant(<?php echo (int)$item['id']; ?>, <?php echo (int)$item['template_id']; ?>, '<?php echo addslashes($choice['label']); ?>', '<?php echo addslashes($choice['key']); ?>', '<?php echo addslashes($choice['image_url']); ?>')"
                         <?php endif; ?>>
                      <div class="variant-info">
                        <span class="variant-label"><?php echo htmlspecialchars($choice['label']); ?></span>
                        <?php if ($disabled): ?>
                          <span class="variant-stock out">Out of stock</span>
                        <?php elseif ($stockStatus <= 5): ?>
                          <span class="variant-stock low">Only <?php echo $stockStatus; ?> left</span>
                        <?php else: ?>
                          <span class="variant-stock"><?php echo $stockStatus; ?> available</span>
                        <?php endif; ?>
                      </div>
                      <?php if ($isSelected): ?>
                        <i class='bx bx-check'></i>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php endif; ?>
            </div>
            <div class="quantity-controls">
              <button type="button" onclick="changeQuantity(<?php echo (int)$item['id']; ?>, <?php echo (int)$item['quantity'] - 1; ?>)">-</button>
              <input type="number" value="<?php echo (int)$item['quantity']; ?>" min="1" onchange="updateQuantity(<?php echo (int)$item['id']; ?>, this.value)">
              <button type="button" onclick="changeQuantity(<?php echo (int)$item['id']; ?>, <?php echo (int)$item['quantity'] + 1; ?>)">+</button>
            </div>
            <div>
              <p style="font-weight:bold; color:#27ae60;">&#8369;<?php echo number_format((float)$item['price'] * (int)$item['quantity'], 2); ?></p>
              <button class="remove-btn" onclick="removeItem(<?php echo (int)$item['id']; ?>)">Remove</button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="cart-summary">
        <h2>Order Summary</h2>
        <div class="summary-card">
          <div class="summary-row-flex">
            <span class="summary-label">Items (<?php echo (int)$total_items; ?>):</span>
            <span class="summary-value" id="summary-items-value">&#8369;<?php echo number_format($total_price, 2); ?></span>
          </div>
          <div class="summary-row-flex shipping-row">
            <div class="shipping-info">
              <i class='bx bx-truck'></i>
              <span>Shipping</span>
            </div>
            <div class="shipping-options">
              <span class="shipping-option">J&T Express: &#8369;95.00</span>
              <span class="shipping-option">SPX Express: &#8369;120.00</span>
            </div>
          </div>
        </div>
        <div class="summary-total-row">
          <span class="total-label">Total:</span>
          <span class="total-value" id="summary-total-value">&#8369;<?php echo number_format($total_price, 2); ?></span>
        </div>
        
        <!-- Checkout form with selected items only -->
        <div id="checkout-form-container">
          <div class="summary-field">
            <label for="message_for_seller">Message for Seller</label>
            <textarea id="message_for_seller" name="message_for_seller" placeholder="Add request, preferred details, or reminder for seller..."><?php echo htmlspecialchars($checkout_message_for_seller); ?></textarea>
          </div>
          <button type="button" class="checkout-btn" onclick="proceedToCheckout()">Proceed to Checkout</button>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>
<script>
window.RBJ_CART_CONFIG = {
  toastMessage: <?php echo json_encode($toast_message); ?>,
  toastType: <?php echo json_encode($toast_type); ?>,
  csrfToken: <?php echo json_encode($csrf_token); ?>
};
</script>
<script src="assets/user-cart.js"></script>
<?php include __DIR__ . '/partials/user_footer.php'; ?>

</body>
</html>



<?php
$umn_home_href = $umn_home_href ?? 'index.php';
$umn_shop_href = $umn_shop_href ?? 'catalog.php';
$umn_customize_href = $umn_customize_href ?? 'customize.php';
$umn_login_href = $umn_login_href ?? '../login.php';
$umn_register_href = $umn_register_href ?? '../register.php';
$umn_cart_href = $umn_cart_href ?? 'cart.php';
$umn_is_logged_in = !empty($is_logged_in);
$umn_cart_count = isset($cart_count) ? (int)$cart_count : 0;
?>
<nav class="navbar">
  <a href="<?php echo htmlspecialchars($umn_home_href, ENT_QUOTES, 'UTF-8'); ?>" class="logo">
    <img src="../rbjlogo.png" alt="RBJ Accessories Logo">
    <span>RBJ Accessories</span>
  </a>
  <div class="nav-links">
    <a href="<?php echo htmlspecialchars($umn_home_href, ENT_QUOTES, 'UTF-8'); ?>">Home</a>
    <a href="<?php echo htmlspecialchars($umn_shop_href, ENT_QUOTES, 'UTF-8'); ?>">Shop</a>
    <a href="<?php echo htmlspecialchars($umn_customize_href, ENT_QUOTES, 'UTF-8'); ?>">Customize</a>
    <?php if ($umn_is_logged_in): ?>
      <a href="<?php echo htmlspecialchars($umn_cart_href, ENT_QUOTES, 'UTF-8'); ?>" class="nav-cart-link" title="Cart" aria-label="Cart">
        <i class='bx bx-cart'></i>
        <span class="cart-count" data-cart-count style="<?php echo $umn_cart_count > 0 ? '' : 'display:none;'; ?>">
          <?php echo $umn_cart_count; ?>
        </span>
      </a>
      <?php include __DIR__ . '/account_menu.php'; ?>
    <?php else: ?>
      <a href="<?php echo htmlspecialchars($umn_login_href, ENT_QUOTES, 'UTF-8'); ?>">Login</a>
      <a href="<?php echo htmlspecialchars($umn_register_href, ENT_QUOTES, 'UTF-8'); ?>">Register</a>
    <?php endif; ?>
  </div>
</nav>

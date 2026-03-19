<?php include_once __DIR__ . '/user_theme.php'; ?>
<script>
(function () {
  var root = document.documentElement;
  root.classList.add('rbj-theme-booting');
  try {
    var saved = localStorage.getItem('rbj_theme');
    if (saved === 'light' || saved === 'dark') {
      root.setAttribute('data-theme', saved);
      root.classList.remove('rbj-theme-booting');
      root.classList.add('rbj-theme-ready');
      return;
    }
  } catch (e) {}

  if (!root.getAttribute('data-theme')) {
    root.setAttribute('data-theme', 'dark');
  }
  root.classList.remove('rbj-theme-booting');
  root.classList.add('rbj-theme-ready');
})();
</script>
<style id="rbj-user-navbar-theme">
html.rbj-theme-booting .navbar {
  visibility: hidden;
}

html.rbj-theme-ready .navbar {
  visibility: visible;
}

.navbar {
  background: rgba(0,0,0,0.86) !important;
  border-bottom: 1px solid rgba(255,255,255,0.06);
  backdrop-filter: blur(8px);
  box-shadow: 0 10px 30px rgba(0,0,0,0.35);
}

.navbar .logo {
  letter-spacing: 0.2px;
  font-family: "Playfair Display", "Times New Roman", serif;
  font-weight: 600;
}

.navbar .nav-links {
  gap: 14px !important;
}

.navbar .nav-links a {
  transition: color 160ms ease, opacity 160ms ease;
  text-transform: uppercase;
  font-size: 12px;
  letter-spacing: 0.8px;
}

.nav-cart-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.navbar .nav-links a:hover {
  opacity: 1;
}

.cart-count {
  box-shadow: 0 0 0 2px rgba(0,0,0,0.45);
}

.account-trigger {
  min-height: 40px;
}

@media (max-width: 768px) {
  .navbar {
    padding: 10px 16px !important;
  }

  .navbar .logo {
    font-size: 18px !important;
  }

  .navbar .logo img {
    height: 48px !important;
  }

  .navbar .nav-links {
    gap: 8px !important;
  }

  .navbar .nav-links a {
    margin-left: 8px !important;
    font-size: 13px;
  }
}
</style>

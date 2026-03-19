<?php
$am_current_page = basename($_SERVER['PHP_SELF'] ?? '');
$am_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$am_username = $_SESSION['username'] ?? 'User';
$am_user_initial = strtoupper(substr($am_username, 0, 1));
$am_user_email = $_SESSION['email'] ?? '';
$am_profile_picture = $_SESSION['profile_picture'] ?? '';

$am_notification_count = 0;
$am_pending_feedback_count = 0;
$am_pending_orders_count = 0;

$am_conn = null;
$am_owns_conn = false;
$am_prev_conn = $conn ?? null;

if ($am_user_id > 0) {
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            if ($conn->ping()) {
                $am_conn = $conn;
            }
        } catch (Throwable $e) {
            $am_conn = null;
        }
    }

    if (!$am_conn) {
        try {
            include __DIR__ . '/../../config.php';
            if (isset($conn) && $conn instanceof mysqli) {
                $am_conn = $conn;
                $am_owns_conn = true;
            }
        } catch (Throwable $e) {
            $am_conn = null;
        }
    }
}

if ($am_conn instanceof mysqli && $am_user_id > 0) {
    try {
        if ($am_user_email === '') {
        $stmt = $am_conn->prepare("SELECT email, profile_picture FROM users WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $am_user_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!empty($row['email'])) {
                $am_user_email = $row['email'];
            }
            if (!empty($row['profile_picture'])) {
                $am_profile_picture = $row['profile_picture'];
                $_SESSION['profile_picture'] = $am_profile_picture;
            }
        }
        }
    } catch (Throwable $e) {
        // Keep menu functional even if user lookup fails.
    }

    try {
        $stmt = $am_conn->prepare("SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0");
        if ($stmt) {
            $stmt->bind_param("i", $am_user_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $am_notification_count = (int)($row['total'] ?? 0);
        }
    } catch (Throwable $e) {
        $am_notification_count = 0;
    }

    try {
        $stmt = $am_conn->prepare("SELECT COUNT(*) AS total FROM feedback WHERE user_id = ? AND status = 'submitted'");
        if ($stmt) {
            $stmt->bind_param("i", $am_user_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $am_pending_feedback_count = (int)($row['total'] ?? 0);
        }
    } catch (Throwable $e) {
        $am_pending_feedback_count = 0;
    }

    try {
        $stmt = $am_conn->prepare("SELECT COUNT(*) AS total FROM orders WHERE user_id = ? AND status IN ('pending', 'in_progress')");
        if ($stmt) {
            $stmt->bind_param("i", $am_user_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $am_pending_orders_count = (int)($row['total'] ?? 0);
        }
    } catch (Throwable $e) {
        $am_pending_orders_count = 0;
    }
}

if ($am_owns_conn && $am_conn instanceof mysqli) {
    $am_conn->close();
}

if ($am_prev_conn instanceof mysqli) {
    $conn = $am_prev_conn;
} elseif ($am_owns_conn) {
    unset($conn);
}

$am_is_active = static function (array $pages) use ($am_current_page): bool {
    return in_array($am_current_page, $pages, true);
};
$am_combined_alert_count = $am_notification_count + $am_pending_feedback_count + $am_pending_orders_count;
?>
<style>
.account-icon {
  position: relative;
}

.company-dropdown {
  position: relative;
}

.company-trigger {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: transparent;
  border: 0;
  color: inherit;
  font: inherit;
  font-weight: 500;
  margin-left: 15px;
  line-height: 1.2;
  cursor: pointer;
  padding: 0;
  transition: color 180ms ease, transform 180ms ease;
}

.company-trigger:hover {
  color: var(--rbj-accent-strong, #ff1f2d);
  transform: translateY(-1px);
}

.company-menu {
  display: block;
  position: absolute;
  top: 44px;
  right: 0;
  min-width: 188px;
  width: max-content;
  max-width: 240px;
  background: #1e1e1e;
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 12px;
  box-shadow: 0 12px 28px rgba(0,0,0,0.38);
  backdrop-filter: blur(12px);
  padding: 6px;
  z-index: 1001;
  opacity: 0;
  transform: translateY(8px) scale(0.98);
  pointer-events: none;
  transition: opacity 160ms ease, transform 160ms ease;
}

.company-menu a {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  border-radius: 8px;
  text-decoration: none;
  color: #fff;
  font-size: 13px;
  line-height: 1.25;
  white-space: nowrap;
}

.company-menu a:hover,
.company-menu a.active {
  background: var(--rbj-accent-soft, rgba(217,4,41,0.20));
}

.company-dropdown.active .company-menu {
  opacity: 1;
  transform: translateY(0) scale(1);
  pointer-events: auto;
}

.account-icon .avatar-badge {
  position: absolute;
  top: -6px;
  right: -7px;
  min-width: 18px;
  height: 18px;
  border-radius: 999px;
  padding: 0 5px;
  line-height: 18px;
  text-align: center;
  font-size: 11px;
  font-weight: 700;
  color: #fff;
  background: #ef233c;
  box-shadow: 0 0 0 2px rgba(0,0,0,0.65);
}

.account-menu-summary {
  padding: 10px 15px;
  border-bottom: 1px solid rgba(255,255,255,0.08);
  margin-bottom: 4px;
}

.account-menu-summary .name {
  font-weight: 700;
  font-size: 13px;
  margin: 0;
  color: #fff;
}

.account-menu-summary .email {
  font-size: 12px;
  margin: 2px 0 0;
  color: var(--rbj-muted, #b8b8b8);
  word-break: break-word;
}

.account-menu .menu-divider {
  height: 1px;
  margin: 6px 10px;
  background: rgba(255,255,255,0.08);
}

.account-menu a.active {
  background: var(--rbj-accent-soft, rgba(217,4,41,0.20));
}

.account-dropdown:focus-within .account-menu {
  opacity: 1;
  transform: translateY(0) scale(1);
  pointer-events: auto;
}

.account-menu .menu-badge {
  margin-left: auto;
  font-size: 11px;
  font-weight: 700;
  background: #ef233c;
  color: #fff;
  border-radius: 999px;
  min-width: 18px;
  height: 18px;
  line-height: 18px;
  text-align: center;
  padding: 0 6px;
}

.account-avatar-img {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  object-fit: cover;
  display: block;
}

.account-trigger {
  transition: color 180ms ease, transform 180ms ease;
}

.account-trigger:hover {
  color: var(--rbj-accent-strong, #ff1f2d);
  transform: translateY(-1px);
}

.account-menu {
  display: block;
  opacity: 0;
  transform: translateY(8px) scale(0.98);
  pointer-events: none;
  transition: opacity 160ms ease, transform 160ms ease;
  backdrop-filter: blur(12px);
}

.account-dropdown.active .account-menu {
  opacity: 1;
  transform: translateY(0) scale(1);
  pointer-events: auto;
}

@media (max-width: 768px) {
  .account-menu {
    right: 0;
    width: min(92vw, 320px);
    max-height: 70vh;
    overflow-y: auto;
  }

  .company-trigger {
    margin-left: 8px;
    font-size: 13px;
  }
}
</style>

<div class="company-dropdown">
  <button class="company-trigger" type="button" aria-haspopup="true" aria-expanded="false" aria-label="Open business profile menu">
    <span>Business Profile</span>
    <i class='bx bx-chevron-down company-trigger-caret' aria-hidden="true"></i>
  </button>
  <div class="company-menu" role="menu">
    <a href="company_profile.php" class="<?php echo $am_is_active(['company_profile.php']) ? 'active' : ''; ?>">
      <i class='bx bx-id-card'></i> Company Overview
    </a>
    <a href="index.php#feedback" class="<?php echo $am_is_active(['index.php']) ? 'active' : ''; ?>">
      <i class='bx bx-star'></i> Ratings &amp; Reviews
    </a>
  </div>
</div>

<div class="account-dropdown">
  <button class="account-trigger" type="button" aria-haspopup="true" aria-expanded="false" aria-label="Open account menu">
    <div class="account-icon">
      <?php if (!empty($am_profile_picture)): ?>
        <img class="account-avatar-img" src="<?php echo '../uploads/' . htmlspecialchars($am_profile_picture); ?>" alt="Profile Avatar">
      <?php else: ?>
        <?php echo htmlspecialchars($am_user_initial); ?>
      <?php endif; ?>
      <?php if ($am_combined_alert_count > 0): ?>
        <span class="avatar-badge"><?php echo $am_combined_alert_count > 99 ? '99+' : $am_combined_alert_count; ?></span>
      <?php endif; ?>
    </div>
    <span class="account-username"><?php echo htmlspecialchars($am_username); ?></span>
    <i class='bx bx-chevron-down account-trigger-caret' aria-hidden="true"></i>
  </button>

  <div class="account-menu" role="menu">
    <div class="account-menu-summary">
      <p class="name"><?php echo htmlspecialchars($am_username); ?></p>
      <?php if ($am_user_email !== ''): ?>
        <p class="email"><?php echo htmlspecialchars($am_user_email); ?></p>
      <?php endif; ?>
    </div>

    <a href="dashboard.php" class="<?php echo $am_is_active(['dashboard.php']) ? 'active' : ''; ?>"><i class='bx bx-dashboard'></i> Dashboard</a>
    <a href="orders.php" class="<?php echo $am_is_active(['orders.php']) ? 'active' : ''; ?>">
      <i class='bx bx-receipt'></i> My Orders
      <?php if ($am_pending_orders_count > 0): ?>
        <span class="menu-badge"><?php echo $am_pending_orders_count > 9 ? '9+' : $am_pending_orders_count; ?></span>
      <?php endif; ?>
    </a>
    <a href="order_tracking.php" class="<?php echo $am_is_active(['order_tracking.php']) ? 'active' : ''; ?>"><i class='bx bx-map'></i> Order Tracking</a>
    <a href="account_info.php" class="<?php echo $am_is_active(['account_info.php', 'account.php']) ? 'active' : ''; ?>"><i class='bx bx-user'></i> Account Info</a>
    <a href="favorites.php" class="<?php echo $am_is_active(['favorites.php']) ? 'active' : ''; ?>"><i class='bx bx-heart'></i> Favorites</a>
    <a href="reviews.php" class="<?php echo $am_is_active(['reviews.php']) ? 'active' : ''; ?>"><i class='bx bx-star'></i> My Reviews</a>
    <a href="feedback_history.php" class="<?php echo $am_is_active(['feedback_history.php']) ? 'active' : ''; ?>">
      <i class='bx bx-message-square-dots'></i> Feedback History
      <?php if ($am_pending_feedback_count > 0): ?>
        <span class="menu-badge"><?php echo $am_pending_feedback_count > 9 ? '9+' : $am_pending_feedback_count; ?></span>
      <?php endif; ?>
    </a>
    <a href="notifications.php" class="<?php echo $am_is_active(['notifications.php']) ? 'active' : ''; ?>">
      <i class='bx bx-bell'></i> Notifications
      <?php if ($am_notification_count > 0): ?>
        <span class="menu-badge"><?php echo $am_notification_count > 9 ? '9+' : $am_notification_count; ?></span>
      <?php endif; ?>
    </a>
    <a href="support.php" class="<?php echo $am_is_active(['support.php']) ? 'active' : ''; ?>"><i class='bx bx-support'></i> Support</a>
    <div class="menu-divider" aria-hidden="true"></div>
    <a href="../logout.php" class="logout-link" onclick="return confirm('Log out now?');"><i class='bx bx-log-out'></i> Logout</a>
  </div>
</div>

<script>
(function () {
  function placeCompanyDropdownNearHome() {
    var navLinks = document.querySelector('.navbar .nav-links');
    var companyDropdown = document.querySelector('.company-dropdown');
    if (!navLinks || !companyDropdown) return;

    // Always place Business Profile between Home and Shop by inserting it before Shop.
    var shopLink = navLinks.querySelector('a[href="catalog.php"], a[href="./catalog.php"]');
    if (shopLink && shopLink.parentNode === navLinks) {
      navLinks.insertBefore(companyDropdown, shopLink);
      return;
    }

    // Fallback: place after Home if Shop link is not present.
    var homeLink = navLinks.querySelector('a[href="#hero"], a[href="index.php"], a[href="./index.php"]');
    if (homeLink && homeLink.parentNode === navLinks) {
      var afterHome = homeLink.nextElementSibling;
      if (afterHome) {
        navLinks.insertBefore(companyDropdown, afterHome);
      } else {
        navLinks.appendChild(companyDropdown);
      }
    }
  }

  function initCompanyMenu() {
    var companyDropdown = document.querySelector('.company-dropdown');
    var companyTrigger = document.querySelector('.company-trigger');
    var companyMenu = document.querySelector('.company-menu');
    var companyCaret = companyTrigger ? companyTrigger.querySelector('.company-trigger-caret') : null;
    if (!companyDropdown || !companyTrigger || !companyMenu) return;
    if (companyDropdown.dataset.menuInit === '1') return;
    companyDropdown.dataset.menuInit = '1';

    function syncExpanded() {
      var isOpen = companyDropdown.classList.contains('active');
      companyTrigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      if (companyCaret) {
        companyCaret.className = isOpen ? 'bx bx-chevron-up company-trigger-caret' : 'bx bx-chevron-down company-trigger-caret';
      }
    }

    syncExpanded();

    companyTrigger.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      companyDropdown.classList.toggle('active');
      syncExpanded();
    });

    companyMenu.addEventListener('click', function (e) {
      e.stopPropagation();
    });

    document.addEventListener('click', function () {
      companyDropdown.classList.remove('active');
      syncExpanded();
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        companyDropdown.classList.remove('active');
        syncExpanded();
      }
    });
  }

  function initAccountMenu() {
    var accountDropdown = document.querySelector('.account-dropdown');
    var accountTrigger = document.querySelector('.account-trigger');
    var accountMenu = document.querySelector('.account-menu');
    var accountCaret = accountTrigger ? accountTrigger.querySelector('.account-trigger-caret') : null;
    if (!accountDropdown || !accountTrigger || !accountMenu) return;
    if (accountDropdown.dataset.menuInit === '1') return;
    accountDropdown.dataset.menuInit = '1';

    function syncExpanded() {
      var isOpen = accountDropdown.classList.contains('active');
      accountTrigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      if (accountCaret) {
        accountCaret.className = isOpen ? 'bx bx-chevron-up account-trigger-caret' : 'bx bx-chevron-down account-trigger-caret';
      }
    }

    syncExpanded();

    accountTrigger.addEventListener('click', function (e) {
      e.stopPropagation();
      accountDropdown.classList.toggle('active');
      syncExpanded();
      if (!accountDropdown.classList.contains('active')) {
        accountTrigger.blur();
      }
    });

    accountMenu.addEventListener('click', function (e) {
      e.stopPropagation();
    });

    document.addEventListener('click', function () {
      accountDropdown.classList.remove('active');
      syncExpanded();
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        accountDropdown.classList.remove('active');
        syncExpanded();
      }
    });

    accountDropdown.addEventListener('focusout', function () {
      setTimeout(function () {
        if (!accountDropdown.contains(document.activeElement)) {
          accountDropdown.classList.remove('active');
          syncExpanded();
        }
      }, 0);
    });

    accountMenu.addEventListener('keydown', function (e) {
      var items = Array.prototype.slice.call(accountMenu.querySelectorAll('a'));
      if (!items.length) return;
      var idx = items.indexOf(document.activeElement);
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (idx < 0) idx = 0;
        else idx = (idx + 1) % items.length;
        items[idx].focus();
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (idx < 0) idx = items.length - 1;
        else idx = (idx - 1 + items.length) % items.length;
        items[idx].focus();
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      placeCompanyDropdownNearHome();
      initCompanyMenu();
      initAccountMenu();
    });
  } else {
    placeCompanyDropdownNearHome();
    initCompanyMenu();
    initAccountMenu();
  }
})();
</script>


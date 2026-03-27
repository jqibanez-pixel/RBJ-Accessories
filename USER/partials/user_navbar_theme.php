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
  body.rbj-mobile-nav-ready {
    padding-top: var(--rbj-navbar-offset, 84px) !important;
  }

  body.rbj-mobile-nav-open::before {
    content: "";
    position: fixed;
    inset: 0;
    background: rgba(4, 7, 12, 0.42);
    backdrop-filter: blur(3px);
    z-index: 998;
  }

  .navbar {
    padding: 10px 16px !important;
    gap: 10px;
    align-items: center;
    z-index: 1000;
  }

  .navbar .logo {
    font-size: 18px !important;
    min-width: 0;
    flex: 1 1 auto;
    max-width: calc(100% - 168px);
  }

  .navbar .logo img {
    height: 48px !important;
  }

  .rbj-mobile-actions {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex: 0 0 auto;
    margin-left: auto;
    position: relative;
    z-index: 1002;
  }

  .rbj-mobile-nav-toggle {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    width: 46px;
    height: 46px;
    min-width: 46px;
    min-height: 46px;
    padding: 0;
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.18);
    background: rgba(255,255,255,0.07);
    color: inherit;
    cursor: pointer;
    flex: 0 0 auto;
    position: relative;
    z-index: 1003;
    touch-action: manipulation;
    -webkit-tap-highlight-color: transparent;
  }

  .rbj-mobile-nav-toggle i {
    font-size: 22px;
  }

  .rbj-mobile-actions .nav-theme-toggle {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    width: 46px;
    height: 46px;
    min-width: 46px;
    min-height: 46px;
    margin: 0 !important;
    padding: 0;
    border-radius: 12px !important;
    border: 1px solid rgba(255,255,255,0.18);
    background: rgba(255,255,255,0.07);
    box-shadow: none;
  }

  .rbj-mobile-actions .nav-theme-toggle i {
    font-size: 20px;
  }

  .navbar .nav-links {
    position: absolute;
    top: calc(100% + 8px);
    left: 10px;
    right: 10px;
    display: none;
    flex-direction: column;
    align-items: stretch;
    gap: 4px !important;
    padding: 10px;
    border-radius: 16px;
    background: rgba(10, 13, 20, 0.985);
    border: 1px solid rgba(255,255,255,0.1);
    box-shadow: 0 18px 32px rgba(0,0,0,0.34);
    max-height: calc(100vh - var(--rbj-navbar-offset, 84px) - 24px);
    overflow-y: auto;
    z-index: 1001;
  }

  body.rbj-mobile-nav-open .navbar .nav-links {
    display: flex;
  }

  .navbar .nav-links {
    gap: 8px !important;
  }

  .navbar .nav-links a {
    margin-left: 0 !important;
    font-size: 13px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 40px;
    padding: 8px 10px;
    border-radius: 9px;
    width: 100%;
    line-height: 1.2;
    background: transparent;
    border: 1px solid transparent;
  }

  .navbar .nav-links .nav-cart-link {
    justify-content: flex-start;
  }

  .navbar .nav-links > a:hover,
  .navbar .nav-links > a:focus-visible,
  .navbar .company-trigger:hover,
  .navbar .company-trigger:focus-visible,
  .navbar .account-trigger:hover,
  .navbar .account-trigger:focus-visible {
    background: rgba(255,255,255,0.05);
    border-color: rgba(255,255,255,0.08);
    text-decoration: none;
  }

  .navbar .nav-links > a,
  .navbar .nav-links > .company-dropdown,
  .navbar .nav-links > .account-dropdown {
    margin: 0 !important;
  }

  .navbar .account-dropdown {
    margin-left: 0 !important;
    width: auto;
    flex: 0 1 auto;
    max-width: 146px;
  }

  .navbar .account-trigger {
    width: auto;
    max-width: 100%;
    justify-content: flex-start;
    min-height: 46px;
    padding: 4px 10px 4px 4px;
    border-radius: 12px;
    background: linear-gradient(135deg, rgba(255,255,255,0.065), rgba(255,255,255,0.03));
    border: 1px solid rgba(255,255,255,0.08);
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.04);
  }

  .navbar .account-trigger .account-icon {
    width: 34px;
    height: 34px;
    flex: 0 0 34px;
  }

  .navbar .account-menu,
  .navbar .dropdown-menu,
  .navbar .company-menu {
    position: absolute !important;
    top: calc(100% + 8px) !important;
    right: 0 !important;
    left: auto !important;
    width: min(300px, calc(100vw - 24px));
    min-width: 0;
    margin-top: 0;
  }

  .navbar .account-username {
    flex: 1 1 auto;
    min-width: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: 13px;
  }

  .navbar .logo span {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
}

@media (min-width: 769px) {
  .rbj-mobile-actions {
    display: none !important;
  }

  .rbj-mobile-nav-toggle {
    display: none !important;
  }
}
</style>
<script>
(function () {
  function setupNavbar() {
    var navbar = document.querySelector('.navbar');
    if (!navbar || navbar.dataset.rbjMobileReady === '1') {
      return;
    }

    var navLinks = navbar.querySelector('.nav-links');
    if (!navLinks) {
      return;
    }

    navbar.dataset.rbjMobileReady = '1';
    document.body.classList.add('rbj-mobile-nav-ready');

    var mobileActions = document.createElement('div');
    mobileActions.className = 'rbj-mobile-actions';
    navbar.insertBefore(mobileActions, navLinks);

    var toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'rbj-mobile-nav-toggle';
    toggle.setAttribute('aria-label', 'Toggle navigation');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.innerHTML = '<i class="bx bx-menu"></i>';
    mobileActions.appendChild(toggle);

    var accountDropdown = navbar.querySelector('.account-dropdown');
    var accountMarker = null;
    if (accountDropdown && accountDropdown.parentNode) {
      accountMarker = document.createComment('rbj-account-marker');
      accountDropdown.parentNode.insertBefore(accountMarker, accountDropdown);
    }

    var themeMarker = document.createComment('rbj-theme-marker');
    navLinks.appendChild(themeMarker);

    function syncOffset() {
      document.documentElement.style.setProperty('--rbj-navbar-offset', navbar.offsetHeight + 'px');
    }

    function syncMobileActions() {
      var isMobile = window.matchMedia('(max-width: 768px)').matches;
      var themeToggle = document.getElementById('navThemeToggleBtn');

      if (themeToggle && !themeMarker.parentNode) {
        navLinks.appendChild(themeMarker);
      }

      if (isMobile) {
        if (accountDropdown && !mobileActions.contains(accountDropdown)) {
          mobileActions.appendChild(accountDropdown);
        }
        if (themeToggle && !mobileActions.contains(themeToggle)) {
          if (themeToggle.parentNode) {
            themeToggle.parentNode.insertBefore(themeMarker, themeToggle);
          }
          mobileActions.appendChild(themeToggle);
        }
      } else {
        if (accountDropdown && accountMarker && accountMarker.parentNode && accountMarker.nextSibling !== accountDropdown) {
          accountMarker.parentNode.insertBefore(accountDropdown, accountMarker.nextSibling);
        }
        if (themeToggle && themeMarker.parentNode && themeMarker.nextSibling !== themeToggle) {
          themeMarker.parentNode.insertBefore(themeToggle, themeMarker.nextSibling);
        }
      }
    }

    function setOpen(open) {
      var shouldOpen = !!open && window.matchMedia('(max-width: 768px)').matches;
      document.body.classList.toggle('rbj-mobile-nav-open', shouldOpen);
      toggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
      toggle.innerHTML = shouldOpen ? '<i class="bx bx-x"></i>' : '<i class="bx bx-menu"></i>';
      document.body.style.overflow = shouldOpen ? 'hidden' : '';
    }

    toggle.addEventListener('click', function () {
      setOpen(!document.body.classList.contains('rbj-mobile-nav-open'));
    });

    navLinks.addEventListener('click', function (event) {
      var target = event.target;
      if (!(target instanceof Element)) {
        return;
      }
      if (target.closest('a')) {
        setOpen(false);
      }
    });

    document.addEventListener('click', function (event) {
      if (!window.matchMedia('(max-width: 768px)').matches) {
        return;
      }
      var target = event.target;
      if (!(target instanceof Element)) {
        return;
      }
      if (!navbar.contains(target)) {
        setOpen(false);
      }
    });

    window.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        setOpen(false);
      }
    });

    window.addEventListener('resize', function () {
      syncMobileActions();
      syncOffset();
      if (!window.matchMedia('(max-width: 768px)').matches) {
        setOpen(false);
      }
    }, { passive: true });

    var navObserver = new MutationObserver(function () {
      syncMobileActions();
      syncOffset();
    });
    navObserver.observe(navLinks, { childList: true, subtree: false });

    syncMobileActions();
    syncOffset();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupNavbar);
  } else {
    setupNavbar();
  }
})();
</script>

<style id="rbj-user-theme">
@import url('https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&family=Playfair+Display:wght@500;600;700&display=swap');
:root {
  --rbj-accent: #e50914;
  --rbj-accent-strong: #ff1f2d;
  --rbj-accent-soft: rgba(229, 9, 20, 0.2);
  --rbj-accent-glow: rgba(229, 9, 20, 0.3);
  --rbj-bg: linear-gradient(145deg, #0a0a0a 0%, #111111 45%, #0f0f0f 100%);
  --rbj-surface: rgba(0,0,0,0.6);
  --rbj-text: #ffffff;
  --rbj-muted: rgba(255,255,255,0.78);
  --rbj-border: rgba(255,255,255,0.15);
  --rbj-navbar-bg: rgba(0,0,0,0.86);
  --rbj-menu-bg: #1e1e1e;
  --rbj-font-sans: "Manrope", "Segoe UI", sans-serif;
  --rbj-font-serif: "Playfair Display", "Times New Roman", serif;
  --rbj-surface-strong: rgba(17, 17, 17, 0.86);
  --rbj-surface-soft: rgba(255, 255, 255, 0.05);
  --rbj-shadow-lg: 0 20px 50px rgba(0,0,0,0.45);
  --rbj-shadow-md: 0 12px 28px rgba(0,0,0,0.32);
  --rbj-shadow-soft: 0 10px 24px rgba(0,0,0,0.18);
  --rbj-outline: rgba(217,4,41, 0.35);
  --rbj-radius-lg: 18px;
  --rbj-radius-md: 12px;
  --rbj-radius-sm: 8px;
}

* {
  box-sizing: border-box;
}

body {
  font-family: var(--rbj-font-sans) !important;
  letter-spacing: 0.1px;
}

h1, h2, h3, h4 {
  font-family: var(--rbj-font-serif);
  letter-spacing: 0.3px;
}

a {
  transition: color 180ms ease, opacity 180ms ease;
}

html,
body,
.navbar,
.panel,
.card,
.cart-items,
.cart-summary,
.summary-card,
.stat-card,
.user-footer,
.btn,
button,
input,
select,
textarea {
  transition: background-color 220ms ease, color 220ms ease, border-color 220ms ease, box-shadow 220ms ease;
}

body::before,
body::after {
  content: "";
  position: fixed;
  inset: -20%;
  pointer-events: none;
  z-index: 0;
}

body::before {
  background: radial-gradient(circle at 15% 20%, rgba(229,9,20,0.2), transparent 45%),
              radial-gradient(circle at 85% 15%, rgba(255,31,45,0.12), transparent 40%),
              radial-gradient(circle at 50% 85%, rgba(255, 190, 120, 0.12), transparent 50%);
  opacity: 0.9;
}

body::after {
  background-image: linear-gradient(120deg, rgba(255,255,255,0.02) 0%, rgba(255,255,255,0.02) 100%);
  mix-blend-mode: screen;
  opacity: 0.3;
}

body > * {
  position: relative;
  z-index: 1;
}

@keyframes rbj-fade-up {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

.rbj-reveal {
  opacity: 0;
  transform: translateY(22px);
  transition: opacity 480ms ease, transform 480ms ease;
}

.rbj-reveal.is-visible {
  opacity: 1;
  transform: translateY(0);
}

.wrapper,
.page-title,
.panel,
.card,
.stat-card,
.cart-items,
.cart-summary {
  animation: rbj-fade-up 420ms ease both;
}

/* Premium surfaces */
.panel,
.cart-items,
.cart-summary,
.summary-card,
.stat-card,
.card,
.message-card,
.review-card,
.orders-table,
.orders-container,
.profile-card,
.address-card,
.support-card,
.notification-card {
  background: var(--rbj-surface-strong) !important;
  border: 1px solid var(--rbj-border) !important;
  border-radius: var(--rbj-radius-lg) !important;
  box-shadow: var(--rbj-shadow-md);
  backdrop-filter: blur(10px);
}

.card,
.stat-card,
.panel,
.summary-card,
.review-card,
.message-card {
  transition: transform 220ms ease, box-shadow 220ms ease, border-color 220ms ease;
}

.card:hover,
.stat-card:hover,
.panel:hover,
.summary-card:hover,
.review-card:hover,
.message-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--rbj-shadow-lg);
  border-color: rgba(217,4,41,0.4) !important;
}

/* Buttons */
button,
.btn,
.btn-primary,
.btn-secondary,
.checkout-btn,
.shop-btn,
.action-btn,
.request-btn,
.remove-btn {
  font-family: var(--rbj-font-sans) !important;
  border-radius: 999px !important;
  letter-spacing: 0.2px;
}

.btn-primary,
.checkout-btn,
.shop-btn,
.action-btn,
.request-btn {
  box-shadow: 0 10px 24px rgba(217,4,41,0.28);
}

.btn-secondary,
.action-btn.secondary {
  background: rgba(255,255,255,0.08) !important;
  border: 1px solid rgba(255,255,255,0.22) !important;
  color: var(--rbj-text) !important;
}

button:hover,
.btn:hover,
.checkout-btn:hover,
.shop-btn:hover,
.action-btn:hover,
.request-btn:hover {
  transform: translateY(-1px) scale(1.01);
  box-shadow: 0 10px 26px rgba(229,9,20,0.28);
}

html[data-theme="dark"] button:hover,
html[data-theme="dark"] .btn:hover,
html[data-theme="dark"] .checkout-btn:hover,
html[data-theme="dark"] .shop-btn:hover,
html[data-theme="dark"] .action-btn:hover,
html[data-theme="dark"] .request-btn:hover {
  box-shadow: 0 0 0 1px rgba(229,9,20,0.45), 0 14px 32px rgba(229,9,20,0.35);
}

/* Inputs */
input,
select,
textarea {
  font-family: var(--rbj-font-sans) !important;
  border-radius: var(--rbj-radius-md) !important;
  border: 1px solid rgba(255,255,255,0.2) !important;
  background: rgba(255,255,255,0.06) !important;
  color: var(--rbj-text) !important;
}

label {
  letter-spacing: 0.2px;
}

input:focus,
select:focus,
textarea:focus {
  outline: none !important;
  border-color: rgba(217,4,41,0.65) !important;
  box-shadow: 0 0 0 3px rgba(217,4,41,0.25);
}

/* Tables */
table {
  border-radius: var(--rbj-radius-lg);
  overflow: hidden;
}

th {
  letter-spacing: 0.2px;
  text-transform: uppercase;
  font-size: 12px;
}

/* Cards and badges */
.badge,
.status,
.slot-indicator,
.order-id-chip {
  border-radius: 999px !important;
  letter-spacing: 0.2px;
}

/* Navbar and account controls */
.navbar .nav-links a:hover,
.nav-links a:hover {
  color: var(--rbj-accent-strong) !important;
}

.account-icon {
  background: linear-gradient(135deg, var(--rbj-accent), var(--rbj-accent-strong)) !important;
  box-shadow: 0 8px 20px var(--rbj-accent-glow);
}

.account-menu a.active,
.account-menu a:hover,
.dropdown-menu a:hover,
.account-menu .logout-link:hover {
  background: var(--rbj-accent-soft) !important;
}

.account-menu .menu-badge,
.cart-count {
  background: var(--rbj-accent-strong) !important;
}

.nav-theme-toggle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 999px;
  border: 1px solid rgba(255,255,255,0.22);
  background: rgba(0,0,0,0.42);
  color: #fff;
  cursor: pointer;
  margin-left: 12px;
}

.nav-theme-toggle:hover {
  background: rgba(217,4,41,0.28);
  border-color: rgba(217,4,41,0.55);
}

/* Cart badge: pin to top-right of cart icon */
.nav-links a {
  position: relative;
}

.nav-links a.nav-cart-link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.cart-count {
  position: absolute !important;
  top: -8px;
  right: -10px;
  margin-left: 0 !important;
  min-width: 18px;
  height: 18px;
  line-height: 18px;
  padding: 0 5px;
  border-radius: 999px;
  text-align: center;
  font-size: 11px;
  font-weight: 700;
  box-shadow: 0 0 0 2px rgba(0,0,0,0.45);
}

/* Replace common green accents with red-complement accents */
.product-content h3,
.product-price,
.cart-summary h2,
.cart-item h3,
.order-id,
.message-card h3,
.review-card h3,
.filters h3,
.profile-picture img {
  color: var(--rbj-accent-strong) !important;
  border-color: var(--rbj-accent-strong) !important;
}

.wrapper h1 {
  color: var(--rbj-accent-strong) !important;
  background: none !important;
  -webkit-text-fill-color: currentColor !important;
}

.page-title,
.wrapper h1,
.section h2 {
  position: relative;
}

.page-title::after,
.wrapper h1::after,
.section h2::after {
  content: "";
  display: block;
  width: 48px;
  height: 3px;
  margin-top: 8px;
  border-radius: 999px;
  background: linear-gradient(90deg, var(--rbj-accent), rgba(217,4,41,0.2));
}

.product-image,
.btn-primary,
.product-actions .btn-primary,
.checkout-btn,
.shop-btn,
.action-btn,
.quantity-controls button,
.profile-picture .upload-btn {
  background: linear-gradient(45deg, var(--rbj-accent), var(--rbj-accent-strong)) !important;
  border-color: transparent !important;
}

.profile-picture .upload-btn,
.profile-picture .upload-btn i {
  color: #fff !important;
}

.toast.success {
  background: rgba(217,4,41, 0.92) !important;
  border-color: rgba(217,4,41, 1) !important;
}

.stat-card .icon.completed,
.recent-activity h2,
.activity-extra,
.quick-actions h2,
.welcome-message h2,
.avatar-success,
.success {
  color: var(--rbj-accent-strong) !important;
}

.action-btn.secondary {
  border-color: rgba(217,4,41, 0.45) !important;
}

.profile-picture img {
  box-shadow: 0 0 0 2px var(--rbj-accent-strong) inset;
}

/* Global theme surfaces */
html[data-theme="dark"] body {
  background: var(--rbj-bg) !important;
  color: var(--rbj-text) !important;
}

html[data-theme="light"] {
  --rbj-bg: linear-gradient(145deg, #ffffff 0%, #f8f9fa 50%, #ffffff 100%);
  --rbj-surface: rgba(255,255,255,0.92);
  --rbj-text: #1f2328;
  --rbj-muted: #4b5563;
  --rbj-border: rgba(217, 4, 41, 0.2);
  --rbj-navbar-bg: rgba(255,255,255,0.88);
  --rbj-menu-bg: #ffffff;
  --rbj-accent: #d90429;
  --rbj-accent-strong: #ef233c;
  --rbj-accent-soft: rgba(217, 4, 41, 0.12);
  --rbj-accent-glow: rgba(217, 4, 41, 0.22);
  --rbj-surface-strong: rgba(255, 255, 255, 0.94);
  --rbj-surface-soft: rgba(255, 255, 255, 0.75);
  --rbj-shadow-lg: 0 24px 50px rgba(217,4,41,0.14);
  --rbj-shadow-md: 0 16px 32px rgba(217,4,41,0.12);
  --rbj-shadow-soft: 0 10px 22px rgba(217,4,41,0.1);
  --rbj-outline: rgba(217,4,41,0.3);
}

html[data-theme="light"] body {
  background: var(--rbj-bg) !important;
  color: var(--rbj-text) !important;
}

html[data-theme="light"] .navbar {
  background: var(--rbj-navbar-bg) !important;
  border-bottom: 1px solid var(--rbj-border) !important;
}

html[data-theme="light"] .navbar .logo,
html[data-theme="light"] .navbar .nav-links a,
html[data-theme="light"] .account-username,
html[data-theme="light"] .account-trigger {
  color: var(--rbj-text) !important;
}

html[data-theme="light"] .nav-theme-toggle {
  background: #fff;
  color: #8d2720;
  border-color: rgba(217,4,41,0.3);
}

html[data-theme="light"] .account-menu,
html[data-theme="light"] .dropdown-menu,
html[data-theme="light"] .company-menu {
  background: var(--rbj-menu-bg) !important;
  border: 1px solid var(--rbj-border);
}

html[data-theme="light"] .account-menu a,
html[data-theme="light"] .dropdown-menu a,
html[data-theme="light"] .company-menu a {
  color: var(--rbj-text) !important;
}

html[data-theme="light"] input,
html[data-theme="light"] select,
html[data-theme="light"] textarea {
  background: #fff !important;
  color: var(--rbj-text) !important;
  border-color: rgba(217,4,41,0.28) !important;
}

html[data-theme="light"] body::before {
  background: radial-gradient(circle at 12% 18%, rgba(217,4,41,0.12), transparent 48%),
              radial-gradient(circle at 90% 10%, rgba(255, 190, 120, 0.12), transparent 44%),
              radial-gradient(circle at 50% 86%, rgba(217,4,41, 0.08), transparent 52%);
  opacity: 0.85;
}

html[data-theme="light"] body::after {
  background-image: linear-gradient(120deg, rgba(217,4,41,0.02) 0%, rgba(217,4,41,0.02) 100%);
  mix-blend-mode: normal;
  opacity: 0.35;
}

html[data-theme="light"] .panel,
html[data-theme="light"] .cart-items,
html[data-theme="light"] .cart-summary,
html[data-theme="light"] .summary-card,
html[data-theme="light"] .stat-card,
html[data-theme="light"] .card,
html[data-theme="light"] .message-card,
html[data-theme="light"] .review-card,
html[data-theme="light"] .orders-table,
html[data-theme="light"] .orders-container,
html[data-theme="light"] .profile-card,
html[data-theme="light"] .address-card,
html[data-theme="light"] .support-card,
html[data-theme="light"] .notification-card {
  background: #fff !important;
  border-color: var(--rbj-border) !important;
  box-shadow: var(--rbj-shadow-md);
}

html[data-theme="light"] .card:hover,
html[data-theme="light"] .stat-card:hover,
html[data-theme="light"] .panel:hover,
html[data-theme="light"] .summary-card:hover,
html[data-theme="light"] .review-card:hover,
html[data-theme="light"] .message-card:hover {
  box-shadow: var(--rbj-shadow-lg);
  border-color: rgba(217,4,41,0.38) !important;
}

html[data-theme="light"] .btn-secondary,
html[data-theme="light"] .action-btn.secondary {
  background: rgba(217,4,41,0.08) !important;
  border-color: rgba(217,4,41,0.26) !important;
  color: var(--rbj-text) !important;
  box-shadow: var(--rbj-shadow-soft);
}

html[data-theme="light"] .btn-secondary:hover,
html[data-theme="light"] .action-btn.secondary:hover {
  background: rgba(217,4,41,0.14) !important;
}

html[data-theme="light"] input:focus,
html[data-theme="light"] select:focus,
html[data-theme="light"] textarea:focus {
  border-color: rgba(217,4,41,0.5) !important;
  box-shadow: 0 0 0 3px rgba(217,4,41,0.18);
}

html[data-theme="light"] .nav-links a:hover,
html[data-theme="light"] .navbar .nav-links a:hover {
  color: var(--rbj-accent-strong) !important;
}

html[data-theme="light"] .account-menu a.active,
html[data-theme="light"] .account-menu a:hover,
html[data-theme="light"] .dropdown-menu a:hover,
html[data-theme="light"] .account-menu .logout-link:hover {
  background: rgba(217,4,41,0.12) !important;
}

html[data-theme="light"] .cart-count {
  box-shadow: 0 0 0 2px rgba(217,4,41,0.18);
}

html[data-theme="light"] .toast.success {
  background: rgba(217,4,41, 0.92) !important;
  border-color: rgba(217,4,41, 1) !important;
  color: #fff !important;
}

html[data-theme="light"] table {
  background: #fff;
}

html[data-theme="light"] th {
  color: var(--rbj-text);
}

html[data-theme="light"] .badge,
html[data-theme="light"] .status,
html[data-theme="light"] .slot-indicator,
html[data-theme="light"] .order-id-chip {
  background: rgba(217,4,41,0.1);
  color: var(--rbj-text);
  border: 1px solid rgba(217,4,41,0.2);
}
</style>


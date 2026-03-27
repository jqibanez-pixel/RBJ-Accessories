<?php include_once __DIR__ . '/user_theme.php'; ?>
<?php
$rbj_live_chat_page = basename($_SERVER['PHP_SELF'] ?? '');
$rbj_live_chat_hidden_pages = ['support.php', 'privacy.php', 'terms.php', 'shipping_returns.php'];
$rbj_is_user_session = isset($_SESSION['user_id']) && (($_SESSION['role'] ?? '') === 'user');
$rbj_show_live_chat = $rbj_is_user_session && !in_array($rbj_live_chat_page, $rbj_live_chat_hidden_pages, true);
?>
<style>
html, body {
  min-height: 100%;
}

body {
  min-height: 100svh !important;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
}

.user-footer {
  margin-top: auto;
  padding: 24px 16px;
  background: linear-gradient(135deg, rgba(10,10,10,0.96), rgba(17,17,17,0.98));
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  text-align: center;
  backdrop-filter: blur(8px);
}

.user-footer-links {
  display: flex;
  justify-content: center;
  gap: 18px;
  flex-wrap: wrap;
  margin-bottom: 8px;
}

.user-footer-links a {
  color: #d7d7d7;
  text-decoration: none;
  font-family: "Manrope", "Segoe UI", sans-serif;
  font-size: 16px;
  font-weight: 600;
  letter-spacing: 0.2px;
}

.user-footer-links a:hover {
  color: var(--rbj-accent-strong, #ff1f2d);
  text-decoration: none;
}

.user-footer-note {
  margin: 0;
  color: #9aa0a6;
  font-family: "Montserrat", "Segoe UI", sans-serif;
  font-size: 13px;
  font-weight: 500;
}

html[data-theme="light"] .user-footer {
  background: linear-gradient(135deg, rgba(255,255,255,0.97), rgba(248,249,250,0.98));
  border-top: 1px solid rgba(217, 4, 41, 0.2);
}

html[data-theme="light"] .user-footer-links a {
  color: #1f2328;
}

html[data-theme="light"] .user-footer-links a:hover {
  color: #d90429;
}

html[data-theme="light"] .user-footer-note {
  color: #4b5563;
}

.rbj-chat-launcher {
  position: fixed;
  right: 18px;
  bottom: 18px;
  width: 56px;
  height: 56px;
  border-radius: 999px;
  border: 0;
  background: linear-gradient(145deg, #d90429 0%, #ef233c 100%);
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 14px 28px rgba(217,4,41, 0.42);
  z-index: 1200;
}

.rbj-chat-launcher i {
  font-size: 25px;
}

.rbj-chat-launcher .rbj-chat-badge {
  position: absolute;
  top: -5px;
  right: -5px;
  min-width: 18px;
  height: 18px;
  line-height: 18px;
  border-radius: 999px;
  text-align: center;
  font-size: 11px;
  font-weight: 700;
  background: #ffffff;
  color: #9d2c24;
  box-shadow: 0 0 0 2px rgba(217,4,41,0.45);
  padding: 0 5px;
  display: none;
}

.rbj-chat-panel {
  position: fixed;
  right: 18px;
  bottom: 86px;
  width: min(92vw, 360px);
  height: min(66vh, 520px);
  display: none;
  flex-direction: column;
  border-radius: 14px;
  overflow: hidden;
  border: 1px solid rgba(255,255,255,0.1);
  background: rgba(14,14,14,0.97);
  box-shadow: 0 18px 42px rgba(0,0,0,0.45);
  z-index: 1201;
}

@media (max-width: 768px) {
  .rbj-chat-launcher {
    right: 12px;
    bottom: 12px;
  }

  .rbj-chat-panel {
    right: 12px;
    bottom: 78px;
  }
}

.rbj-chat-panel.show {
  display: flex;
}

.rbj-chat-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 10px 12px;
  border-bottom: 1px solid rgba(255,255,255,0.1);
  background: linear-gradient(145deg, rgba(217,4,41,0.18), rgba(217,4,41,0.14));
}

.rbj-chat-head .title-wrap strong {
  display: block;
  font-size: 14px;
}

.rbj-chat-head .title-wrap span {
  font-size: 11px;
  color: rgba(255,255,255,0.72);
}
.rbj-chat-presence {
  min-height: 22px;
  padding: 0 12px 8px;
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.rbj-chat-presence span {
  display: inline-flex;
  align-items: center;
  padding: 4px 9px;
  border-radius: 999px;
  background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.12);
  color: rgba(255,255,255,0.88);
  font-size: 11px;
}

.rbj-chat-head-actions {
  display: inline-flex;
  gap: 6px;
}

.rbj-chat-head-actions button,
.rbj-chat-head-actions a {
  width: 30px;
  height: 30px;
  border-radius: 999px;
  border: 1px solid rgba(255,255,255,0.22);
  background: transparent;
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  text-decoration: none;
}

.rbj-chat-head-actions i {
  font-size: 18px;
}

.rbj-chat-feed {
  flex: 1;
  overflow-y: auto;
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.rbj-chat-msg {
  max-width: 88%;
  display: flex;
  flex-direction: column;
  gap: 4px;
  align-self: flex-start;
}

.rbj-chat-bubble {
  width: fit-content;
  max-width: 100%;
  padding: 9px 11px;
  border-radius: 11px;
  line-height: 1.45;
  font-size: 13px;
  word-wrap: break-word;
}

.rbj-chat-msg-text {
  white-space: pre-wrap;
}
.rbj-chat-attachment {
  display: grid;
  gap: 6px;
  margin-top: 6px;
}
.rbj-chat-attachment a {
  color: inherit;
  font-weight: 700;
  text-decoration: underline;
}
.rbj-chat-attachment img {
  max-width: 210px;
  max-height: 170px;
  border-radius: 10px;
  border: 1px solid rgba(255,255,255,0.14);
  object-fit: cover;
  cursor: zoom-in;
}

.rbj-chat-meta {
  margin-top: 4px;
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 6px;
  font-size: 10px;
  opacity: 0.86;
  color: rgba(255,255,255,0.92);
}

.rbj-chat-meta .status {
  font-weight: 700;
  letter-spacing: 0.1px;
}

.rbj-chat-msg.bot {
  align-self: flex-start;
}

.rbj-chat-msg.bot .rbj-chat-bubble {
  background: rgba(255,255,255,0.09);
  border: 1px solid rgba(255,255,255,0.1);
}

.rbj-chat-msg.user {
  align-self: flex-end;
}

.rbj-chat-msg.user .rbj-chat-bubble {
  align-self: flex-end;
  background: linear-gradient(145deg, #d90429, #ef233c);
  color: #fff;
}

.rbj-chat-msg.user .rbj-chat-meta {
  justify-content: flex-end;
}
.rbj-chat-msg.typing {
  max-width: 92px;
}
.rbj-chat-msg.typing .rbj-chat-bubble {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  min-width: 54px;
}
.rbj-typing-dot {
  width: 7px;
  height: 7px;
  border-radius: 999px;
  background: rgba(255,255,255,0.82);
  animation: rbjTypingPulse 1.1s ease-in-out infinite;
}
.rbj-chat-msg.user.typing .rbj-typing-dot {
  background: rgba(255,255,255,0.86);
}
.rbj-typing-dot:nth-child(2) {
  animation-delay: 0.18s;
}
.rbj-typing-dot:nth-child(3) {
  animation-delay: 0.36s;
}
@keyframes rbjTypingPulse {
  0%, 80%, 100% {
    transform: translateY(0);
    opacity: 0.45;
  }
  40% {
    transform: translateY(-3px);
    opacity: 1;
  }
}

.rbj-chat-form {
  display: grid;
  gap: 8px;
  padding: 10px;
  border-top: 1px solid rgba(255,255,255,0.1);
}

.rbj-chat-form input {
  flex: 1;
  min-width: 0;
  border-radius: 10px;
  border: 1px solid rgba(255,255,255,0.2);
  background: rgba(255,255,255,0.08);
  color: #fff;
  padding: 10px 11px;
  font-size: 13px;
}

.rbj-chat-form input::placeholder {
  color: rgba(255,255,255,0.64);
}

.rbj-chat-form-row {
  display: flex;
  gap: 8px;
}

.rbj-chat-tools {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  font-size: 11px;
  color: rgba(255,255,255,0.78);
}

.rbj-chat-attach {
  border: 1px solid rgba(255,255,255,0.18);
  border-radius: 999px;
  padding: 6px 10px;
  cursor: pointer;
  font-weight: 700;
}

.rbj-chat-attachment-card {
  display: none;
  align-items: center;
  gap: 10px;
  padding: 9px 10px;
  border-radius: 12px;
  background: rgba(255,255,255,0.07);
  border: 1px solid rgba(255,255,255,0.12);
}

.rbj-chat-attachment-card.is-visible {
  display: flex;
}

.rbj-chat-attachment-thumb {
  width: 58px;
  height: 58px;
  border-radius: 10px;
  overflow: hidden;
  border: 1px solid rgba(255,255,255,0.12);
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  background: rgba(255,255,255,0.08);
}

.rbj-chat-attachment-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.rbj-chat-attachment-thumb i {
  font-size: 26px;
}

.rbj-chat-attachment-meta {
  min-width: 0;
  display: grid;
  gap: 4px;
}

.rbj-chat-attachment-name {
  font-size: 12px;
  font-weight: 700;
  color: #fff;
  word-break: break-word;
}

.rbj-chat-attachment-type {
  font-size: 11px;
  color: rgba(255,255,255,0.72);
}

.rbj-chat-attach input {
  display: none;
}

.rbj-chat-attach-clear {
  border: none;
  background: transparent;
  color: #ff9d9d;
  font-size: 11px;
  cursor: pointer;
  padding: 0;
}

.rbj-chat-form button {
  border: 0;
  border-radius: 10px;
  padding: 0 12px;
  background: linear-gradient(145deg, #d90429, #ef233c);
  color: #fff;
  cursor: pointer;
}

html[data-theme="light"] .rbj-chat-panel {
  background: #fffdfd;
  border-color: var(--rbj-border, rgba(217,4,41,0.22));
  color: var(--rbj-text, #7a211b);
}

html[data-theme="light"] .rbj-chat-feed {
  background: #fff7f5;
}

html[data-theme="light"] .rbj-chat-head {
  background: linear-gradient(145deg, #fff3f0 0%, #ffe9e4 100%);
  border-bottom-color: var(--rbj-border, rgba(217,4,41,0.22));
}

html[data-theme="light"] .rbj-chat-head .title-wrap span {
  color: var(--rbj-muted, #9f4b43);
}

html[data-theme="light"] .rbj-chat-head-actions button,
html[data-theme="light"] .rbj-chat-head-actions a {
  border-color: var(--rbj-border, rgba(217,4,41,0.3));
  color: var(--rbj-text, #7a211b);
}

html[data-theme="light"] .rbj-chat-msg.bot .rbj-chat-bubble {
  background: #fff4f1;
  border-color: var(--rbj-border, rgba(217,4,41,0.22));
  color: var(--rbj-text, #7a211b);
}

html[data-theme="light"] .rbj-chat-meta {
  color: #9f4b43;
}

html[data-theme="light"] .rbj-chat-msg.user .rbj-chat-bubble {
  background: linear-gradient(145deg, #d90429, #ef233c);
  color: #fff;
}

html[data-theme="light"] .rbj-chat-msg.user .rbj-chat-meta {
  color: #8e3c35;
  opacity: 1;
}

html[data-theme="light"] .rbj-chat-msg.bot .rbj-chat-meta {
  color: #9f4b43;
}

html[data-theme="light"] .rbj-chat-form {
  border-top-color: var(--rbj-border, rgba(217,4,41,0.22));
}

html[data-theme="light"] .rbj-chat-form input {
  background: #fff;
  color: var(--rbj-text, #7a211b);
  border-color: var(--rbj-border, rgba(217,4,41,0.28));
}

html[data-theme="light"] .rbj-chat-form input::placeholder {
  color: #ad6a65;
}

html[data-theme="light"] .rbj-chat-form button {
  background: linear-gradient(145deg, #d90429, #ef233c);
  color: #fff;
}

html[data-theme="light"] .rbj-chat-attachment-card {
  background: #fff3f0;
  border-color: rgba(217,4,41,0.14);
}

html[data-theme="light"] .rbj-chat-attachment-thumb {
  background: #fff;
  border-color: rgba(217,4,41,0.18);
}

html[data-theme="light"] .rbj-chat-attachment-name {
  color: #7a211b;
}

html[data-theme="light"] .rbj-chat-attachment-type {
  color: #9f4b43;
}

html[data-theme="light"] .rbj-chat-launcher .rbj-chat-badge {
  background: #fff;
  color: #8d2720;
  box-shadow: 0 0 0 2px rgba(217,4,41,0.3);
}
.rbj-chat-lightbox {
  position: fixed;
  inset: 0;
  z-index: 1250;
  display: none;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(10, 10, 10, 0.82);
}
.rbj-chat-lightbox.show {
  display: flex;
}
.rbj-chat-lightbox-inner {
  position: relative;
  max-width: min(94vw, 960px);
  max-height: 88vh;
  display: grid;
  gap: 10px;
  justify-items: center;
}
.rbj-chat-lightbox img {
  max-width: 100%;
  max-height: calc(88vh - 48px);
  border-radius: 14px;
  box-shadow: 0 16px 38px rgba(0,0,0,0.35);
  background: #fff;
}
.rbj-chat-lightbox-caption {
  color: #fff;
  font-size: 12px;
  text-align: center;
  word-break: break-word;
}
.rbj-chat-lightbox-close {
  position: absolute;
  top: -8px;
  right: -8px;
  width: 36px;
  height: 36px;
  border-radius: 999px;
  border: 1px solid rgba(255,255,255,0.24);
  background: rgba(10,10,10,0.78);
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  cursor: pointer;
}
</style>

<footer class="user-footer">
  <div class="user-footer-links">
    <a href="about.php">About</a>
    <a href="contact.php">Contact</a>
    <a href="support.php">Support</a>
    <a href="privacy.php">Privacy</a>
    <a href="terms.php">Terms</a>
    <a href="shipping_returns.php">Shipping & Returns</a>
  </div>
  <p class="user-footer-note">&copy; <?php echo date('Y'); ?> RBJ Accessories. All rights reserved.</p>
</footer>
<script>
(function () {
  function wrapResponsiveTables() {
    var tables = document.querySelectorAll('table');
    tables.forEach(function (table) {
      if (table.closest('.rbj-table-scroll')) {
        return;
      }
      var wrapper = document.createElement('div');
      wrapper.className = 'rbj-table-scroll';
      table.parentNode.insertBefore(wrapper, table);
      wrapper.appendChild(table);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', wrapResponsiveTables, { once: true });
  } else {
    wrapResponsiveTables();
  }
})();
</script>
<?php if ($rbj_show_live_chat): ?>
<button id="rbjChatLauncher" class="rbj-chat-launcher" type="button" aria-label="Open live chat">
  <i class='bx bx-message-dots'></i>
  <span id="rbjChatBadge" class="rbj-chat-badge" aria-hidden="true">0</span>
</button>

<section id="rbjChatPanel" class="rbj-chat-panel" aria-label="RBJ Live Chat">
  <div class="rbj-chat-head">
    <div class="title-wrap">
      <strong>RBJ Live Chat</strong>
      <span>Connected with RBJ support team</span>
    </div>
    <div class="rbj-chat-head-actions">
      <a href="support.php" title="Open full support page" aria-label="Open support page">
        <i class='bx bx-link-external'></i>
      </a>
      <button id="rbjChatClose" type="button" title="Close chat" aria-label="Close chat">
        <i class='bx bx-x'></i>
      </button>
    </div>
  </div>
  <div id="rbjChatPresence" class="rbj-chat-presence"></div>
  <div id="rbjChatFeed" class="rbj-chat-feed"></div>
  <form id="rbjChatForm" class="rbj-chat-form">
    <div class="rbj-chat-form-row">
      <input id="rbjChatInput" type="text" placeholder="Type your message..." maxlength="1000" autocomplete="off">
      <button type="submit" aria-label="Send chat message"><i class='bx bx-send'></i></button>
    </div>
    <div class="rbj-chat-tools">
      <label class="rbj-chat-attach">Attach file
        <input id="rbjChatAttachment" type="file" accept="image/*,.pdf,.txt">
      </label>
      <span id="rbjChatAttachmentPreview">Send image or file</span>
      <button type="button" id="rbjChatAttachmentClear" class="rbj-chat-attach-clear" hidden>Clear</button>
    </div>
    <div id="rbjChatAttachmentCard" class="rbj-chat-attachment-card" aria-live="polite">
      <div id="rbjChatAttachmentThumb" class="rbj-chat-attachment-thumb"><i class='bx bx-image-alt'></i></div>
      <div class="rbj-chat-attachment-meta">
        <div id="rbjChatAttachmentName" class="rbj-chat-attachment-name">No attachment selected</div>
        <div id="rbjChatAttachmentType" class="rbj-chat-attachment-type">Images, PDF, or TXT up to 5MB</div>
      </div>
    </div>
  </form>
</section>
<div id="rbjChatLightbox" class="rbj-chat-lightbox" aria-hidden="true">
  <div class="rbj-chat-lightbox-inner">
    <button id="rbjChatLightboxClose" type="button" class="rbj-chat-lightbox-close" aria-label="Close image preview">&times;</button>
    <img id="rbjChatLightboxImage" src="" alt="Chat attachment preview">
    <div id="rbjChatLightboxCaption" class="rbj-chat-lightbox-caption"></div>
  </div>
</div>
<?php endif; ?>

<script src="assets/user-enhancements.js"></script>
<?php if ($rbj_show_live_chat): ?>
<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$rbj_chat_csrf_token = (string)$_SESSION['csrf_token'];
?>
<script>
(function () {
  var apiUrl = 'live_chat_api.php';
  var csrfToken = <?php echo json_encode($rbj_chat_csrf_token); ?>;
  var openStateKey = 'rbj_live_chat_open_v1';
  var launcher = document.getElementById('rbjChatLauncher');
  var badge = document.getElementById('rbjChatBadge');
  var panel = document.getElementById('rbjChatPanel');
  var closeBtn = document.getElementById('rbjChatClose');
  var presence = document.getElementById('rbjChatPresence');
  var feed = document.getElementById('rbjChatFeed');
  var form = document.getElementById('rbjChatForm');
  var input = document.getElementById('rbjChatInput');
  var attachmentInput = document.getElementById('rbjChatAttachment');
  var attachmentPreview = document.getElementById('rbjChatAttachmentPreview');
  var attachmentCard = document.getElementById('rbjChatAttachmentCard');
  var attachmentThumb = document.getElementById('rbjChatAttachmentThumb');
  var attachmentName = document.getElementById('rbjChatAttachmentName');
  var attachmentType = document.getElementById('rbjChatAttachmentType');
  var attachmentClear = document.getElementById('rbjChatAttachmentClear');
  var lightbox = document.getElementById('rbjChatLightbox');
  var lightboxImage = document.getElementById('rbjChatLightboxImage');
  var lightboxCaption = document.getElementById('rbjChatLightboxCaption');
  var lightboxClose = document.getElementById('rbjChatLightboxClose');
  if (!launcher || !badge || !panel || !closeBtn || !feed || !form || !input || !presence || !attachmentInput || !attachmentPreview || !attachmentClear) return;

  var state = {
    open: false,
    lastId: 0,
    unreadCount: 0,
    typingTimer: null,
    presence: null
  };
  var attachmentPreviewUrl = '';

  try {
    state.open = localStorage.getItem(openStateKey) === '1';
  } catch (e) {}

  function isElementActive(el) {
    if (!el) return false;
    var rect = el.getBoundingClientRect();
    if (rect.width <= 0 || rect.height <= 0) return false;
    var style = window.getComputedStyle(el);
    if (style.display === 'none' || style.visibility === 'hidden' || Number(style.opacity) === 0) return false;
    if (el.id === 'scrollTopBtn' || el.classList.contains('scroll-top-btn')) {
      if (!el.classList.contains('show')) return false;
    }
    var isRightSide = rect.right > window.innerWidth * 0.55;
    var nearBottom = (window.innerHeight - rect.top) < 260;
    return isRightSide && nearBottom;
  }

  function applyDynamicPosition() {
    var base = window.matchMedia('(max-width: 768px)').matches ? 12 : 18;
    var candidates = [
      document.getElementById('scrollTopBtn'),
      document.querySelector('.scroll-top-btn'),
      document.querySelector('.sticky-customize-cta')
    ];

    var extra = 0;
    candidates.forEach(function (el) {
      if (!isElementActive(el)) return;
      var rect = el.getBoundingClientRect();
      extra = Math.max(extra, rect.height + 10);
    });

    var launcherBottom = base + extra;
    launcher.style.bottom = launcherBottom + 'px';
    panel.style.bottom = (launcherBottom + launcher.offsetHeight + 10) + 'px';
  }

  function parseDateTime(raw) {
    if (!raw) return null;
    var iso = String(raw).replace(' ', 'T');
    var dt = new Date(iso);
    if (!isNaN(dt.getTime())) return dt;
    dt = new Date(String(raw));
    return isNaN(dt.getTime()) ? null : dt;
  }

  function formatTime(raw) {
    var dt = parseDateTime(raw);
    if (!dt) {
      var s = String(raw || '');
      var m = s.match(/\b(\d{2}):(\d{2})(?::\d{2})?\b/);
      if (!m) return '';
      var hh = Number(m[1]);
      var mm = m[2];
      var ap = hh >= 12 ? 'PM' : 'AM';
      hh = hh % 12 || 12;
      return hh + ':' + mm + ' ' + ap;
    }
    return dt.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
  }

  function formatStatus(status) {
    if (status === 'seen') return 'Seen';
    if (status === 'delivered') return 'Delivered';
    return 'Sent';
  }

  function formatFileSize(bytes) {
    var size = Number(bytes || 0);
    if (!size) return '';
    if (size >= 1024 * 1024) return (size / (1024 * 1024)).toFixed(1) + ' MB';
    if (size >= 1024) return Math.round(size / 1024) + ' KB';
    return size + ' B';
  }

  function isImageFile(file) {
    return !!(file && String(file.type || '').indexOf('image/') === 0);
  }

  function openLightbox(src, caption) {
    if (!lightbox || !lightboxImage || !src) return;
    lightboxImage.src = src;
    if (lightboxCaption) lightboxCaption.textContent = caption || 'Chat attachment preview';
    lightbox.classList.add('show');
    lightbox.setAttribute('aria-hidden', 'false');
  }

  function closeLightbox() {
    if (!lightbox || !lightboxImage) return;
    lightbox.classList.remove('show');
    lightbox.setAttribute('aria-hidden', 'true');
    lightboxImage.src = '';
    if (lightboxCaption) lightboxCaption.textContent = '';
  }

  function resetAttachmentCard() {
    if (attachmentPreviewUrl) {
      URL.revokeObjectURL(attachmentPreviewUrl);
      attachmentPreviewUrl = '';
    }
    if (!attachmentCard || !attachmentThumb || !attachmentName || !attachmentType) return;
    attachmentCard.classList.remove('is-visible');
    attachmentThumb.innerHTML = "<i class='bx bx-image-alt'></i>";
    attachmentName.textContent = 'No attachment selected';
    attachmentType.textContent = 'Images, PDF, or TXT up to 5MB';
  }

  function setAttachmentFile(file) {
    if (!attachmentInput) return;
    if (!file) {
      attachmentInput.value = '';
      updateAttachmentPreview();
      return;
    }
    try {
      var dt = new DataTransfer();
      dt.items.add(file);
      attachmentInput.files = dt.files;
    } catch (e) {
      return;
    }
    updateAttachmentPreview();
  }

  function updateAttachmentPreview() {
    var file = attachmentInput.files && attachmentInput.files[0] ? attachmentInput.files[0] : null;
    if (!file) {
      attachmentPreview.textContent = 'Send image or file';
      attachmentClear.hidden = true;
      resetAttachmentCard();
      return null;
    }
    attachmentPreview.textContent = file.name + ' (' + formatFileSize(file.size) + ')';
    attachmentClear.hidden = false;
    if (attachmentCard && attachmentThumb && attachmentName && attachmentType) {
      attachmentCard.classList.add('is-visible');
      attachmentName.textContent = file.name;
      attachmentType.textContent = (file.type ? file.type : 'File') + ' - ' + formatFileSize(file.size);
      if (attachmentPreviewUrl) {
        URL.revokeObjectURL(attachmentPreviewUrl);
        attachmentPreviewUrl = '';
      }
      if (isImageFile(file)) {
        attachmentPreviewUrl = URL.createObjectURL(file);
        attachmentThumb.innerHTML = '<img src="' + attachmentPreviewUrl + '" alt="Selected image preview">';
      } else {
        attachmentThumb.innerHTML = "<i class='bx bx-file'></i>";
      }
    }
    return file;
  }

  function renderPresence(summary) {
    state.presence = summary || null;
    var chips = [];
    if (summary && summary.admin_typing) chips.push('<span>RBJ support is typing...</span>');
    if (summary && summary.admin_viewing) chips.push('<span>RBJ support is viewing your chat</span>');
    presence.innerHTML = chips.join('');
    syncTypingBubble(summary || null);
  }

  function ensureTypingBubble(role) {
    var existing = feed.querySelector('.rbj-chat-msg.typing.' + role);
    if (existing) return existing;
    var div = document.createElement('div');
    div.className = 'rbj-chat-msg typing ' + role;
    var bubble = document.createElement('div');
    bubble.className = 'rbj-chat-bubble';
    bubble.innerHTML = '<span class="rbj-typing-dot"></span><span class="rbj-typing-dot"></span><span class="rbj-typing-dot"></span>';
    div.appendChild(bubble);
    feed.appendChild(div);
    feed.scrollTop = feed.scrollHeight;
    return div;
  }

  function syncTypingBubble(summary) {
    var resolved = typeof summary === 'undefined' ? state.presence : summary;
    var adminTyping = !!(resolved && resolved.admin_typing && state.open);
    var adminNode = feed.querySelector('.rbj-chat-msg.typing.bot');
    if (adminTyping) {
      ensureTypingBubble('bot');
    } else if (adminNode) {
      adminNode.remove();
    }
  }

  function renderAttachment(container, attachment) {
    if (!attachment || !attachment.url) return;
    var wrap = document.createElement('div');
    wrap.className = 'rbj-chat-attachment';
    var isImage = String(attachment.mime || '').indexOf('image/') === 0;
    if (isImage) {
      var link = document.createElement('a');
      link.href = attachment.url;
      link.target = '_blank';
      link.rel = 'noopener';
      var img = document.createElement('img');
      img.src = attachment.url;
      img.alt = attachment.name || 'Attachment';
      link.appendChild(img);
      wrap.appendChild(link);
    }
    var fileLink = document.createElement('a');
    fileLink.href = attachment.url;
    fileLink.target = '_blank';
    fileLink.rel = 'noopener';
    fileLink.textContent = attachment.name || 'Open attachment';
    wrap.appendChild(fileLink);
    container.appendChild(wrap);
  }

  function renderMessage(msg) {
    syncTypingBubble();
    var div = document.createElement('div');
    div.className = 'rbj-chat-msg ' + (msg.role === 'user' ? 'user' : 'bot');
    div.dataset.messageId = String(Number(msg.id) || 0);

    var bubble = document.createElement('div');
    bubble.className = 'rbj-chat-bubble';

    var text = document.createElement('div');
    text.className = 'rbj-chat-msg-text';
    if (msg.text) {
      text.textContent = msg.text || '';
      bubble.appendChild(text);
    }
    renderAttachment(bubble, msg.attachment || null);
    div.appendChild(bubble);

    var meta = document.createElement('div');
    meta.className = 'rbj-chat-meta';

    var timeEl = document.createElement('span');
    timeEl.className = 'time';
    var t = msg.created_time || formatTime(msg.created_at);
    timeEl.textContent = t || '--:--';
    meta.appendChild(timeEl);

    if (msg.role === 'user') {
      var statusEl = document.createElement('span');
      statusEl.className = 'status';
      statusEl.textContent = formatStatus(msg.delivery_status);
      meta.appendChild(statusEl);
    }

    div.appendChild(meta);
    feed.appendChild(div);
    syncTypingBubble();
  }

  function updateMessageStatus(update) {
    var id = Number(update && update.id);
    if (!id) return;
    var node = feed.querySelector('.rbj-chat-msg.user[data-message-id="' + id + '"]');
    if (!node) return;
    var statusEl = node.querySelector('.rbj-chat-meta .status');
    if (!statusEl) return;
    statusEl.textContent = formatStatus(update.delivery_status);
  }

  function renderVisibility() {
    panel.classList.toggle('show', !!state.open);
    launcher.style.display = state.open ? 'none' : 'inline-flex';
    applyDynamicPosition();
  }

  function setUnread(count) {
    state.unreadCount = Math.max(0, Number(count) || 0);
    if (state.unreadCount > 0 && !state.open) {
      badge.style.display = 'inline-block';
      badge.textContent = state.unreadCount > 99 ? '99+' : String(state.unreadCount);
    } else {
      badge.style.display = 'none';
      badge.textContent = '0';
    }
  }

  async function markRead() {
    try {
      var body = new URLSearchParams();
      body.set('action', 'mark_read');
      body.set('csrf_token', csrfToken);
      await fetch(apiUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body: body.toString()
      });
      setUnread(0);
    } catch (e) {}
  }

  async function updatePresenceState(isTyping, isViewing) {
    try {
      var body = new URLSearchParams();
      body.set('action', 'update_presence');
      body.set('is_typing', isTyping ? '1' : '0');
      body.set('is_viewing', isViewing ? '1' : '0');
      body.set('csrf_token', csrfToken);
      var res = await fetch(apiUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body: body.toString()
      });
      if (!res.ok) return;
      var data = await res.json();
      if (!data || !data.ok) return;
      renderPresence(data.presence || null);
    } catch (e) {}
  }

  async function fetchChat(reset) {
    if (reset) {
      state.lastId = 0;
      feed.innerHTML = '';
    }
    try {
      var qs = '?action=fetch&since_id=' + encodeURIComponent(state.lastId) + '&client_last_id=' + encodeURIComponent(state.lastId);
      var res = await fetch(apiUrl + qs, { credentials: 'same-origin' });
      if (!res.ok) return;
      var data = await res.json();
      if (!data || !data.ok) return;

      var items = Array.isArray(data.messages) ? data.messages : [];
      items.forEach(function (msg) {
        renderMessage(msg);
        state.lastId = Math.max(state.lastId, Number(msg.id) || 0);
      });
      var updates = Array.isArray(data.status_updates) ? data.status_updates : [];
      updates.forEach(updateMessageStatus);
      renderPresence(data.presence || null);
      if (items.length) {
        feed.scrollTop = feed.scrollHeight;
      }
      if (state.open) {
        await markRead();
      } else {
        setUnread(data.unread_count || 0);
      }
    } catch (e) {}
  }

  async function sendMessage(text) {
    try {
      var body = new FormData();
      body.set('action', 'send');
      body.set('message', text);
      body.set('csrf_token', csrfToken);
      var attachment = attachmentInput.files && attachmentInput.files[0] ? attachmentInput.files[0] : null;
      if (attachment) body.set('attachment', attachment);
      var res = await fetch(apiUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: body
      });
      if (!res.ok) return false;
      var data = await res.json();
      return !!(data && data.ok);
    } catch (e) {
      return false;
    }
  }

  async function fetchUnreadCount() {
    try {
      var res = await fetch(apiUrl + '?action=unread_count', { credentials: 'same-origin' });
      if (!res.ok) return;
      var data = await res.json();
      if (!data || !data.ok) return;
      if (!state.open) {
        setUnread(data.count || 0);
      }
    } catch (e) {}
  }

  launcher.addEventListener('click', async function () {
    state.open = true;
    try {
      localStorage.setItem(openStateKey, '1');
    } catch (e) {}
    renderVisibility();
    await fetchChat(false);
    await markRead();
    updatePresenceState(false, true);
    input.focus();
  });

  closeBtn.addEventListener('click', function () {
    state.open = false;
    try {
      localStorage.setItem(openStateKey, '0');
    } catch (e) {}
    renderVisibility();
    updatePresenceState(false, false);
  });

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    var text = input.value.trim();
    var attachment = attachmentInput.files && attachmentInput.files[0] ? attachmentInput.files[0] : null;
    if (!text && !attachment) return;
    var ok = await sendMessage(text);
    if (ok) {
      input.value = '';
      attachmentInput.value = '';
      updateAttachmentPreview();
      updatePresenceState(false, state.open);
      await fetchChat(false);
      return;
    }
    alert('Unable to send message right now. Please try again.');
  });

  input.addEventListener('input', function () {
    updatePresenceState(true, state.open);
    if (state.typingTimer) clearTimeout(state.typingTimer);
    state.typingTimer = setTimeout(function () {
      updatePresenceState(false, state.open);
    }, 1800);
  });
  input.addEventListener('paste', function (e) {
    if (!e.clipboardData || !e.clipboardData.items) return;
    for (var i = 0; i < e.clipboardData.items.length; i += 1) {
      var item = e.clipboardData.items[i];
      if (!item || String(item.type || '').indexOf('image/') !== 0) continue;
      var blob = item.getAsFile();
      if (!blob) continue;
      e.preventDefault();
      var ext = (blob.type || 'image/png').split('/')[1] || 'png';
      var file = new File([blob], 'pasted-image-' + Date.now() + '.' + ext, { type: blob.type || 'image/png' });
      setAttachmentFile(file);
      break;
    }
  });

  attachmentInput.addEventListener('change', updateAttachmentPreview);
  attachmentClear.addEventListener('click', function () {
    attachmentInput.value = '';
    updateAttachmentPreview();
  });
  feed.addEventListener('click', function (e) {
    var image = e.target.closest('.rbj-chat-attachment img');
    if (!image) return;
    e.preventDefault();
    openLightbox(image.getAttribute('src') || '', image.getAttribute('alt') || 'Chat attachment');
  });
  if (lightboxClose) {
    lightboxClose.addEventListener('click', closeLightbox);
  }
  if (lightbox) {
    lightbox.addEventListener('click', function (e) {
      if (e.target === lightbox) closeLightbox();
    });
  }

  document.addEventListener('visibilitychange', function () {
    updatePresenceState(false, state.open && document.visibilityState === 'visible');
  });
  window.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && lightbox && lightbox.classList.contains('show')) {
      closeLightbox();
    }
  });

  window.addEventListener('resize', applyDynamicPosition, { passive: true });
  window.addEventListener('scroll', applyDynamicPosition, { passive: true });
  setInterval(applyDynamicPosition, 1200);
  setInterval(function () {
    fetchChat(false);
    fetchUnreadCount();
    updatePresenceState(false, state.open && document.visibilityState === 'visible');
  }, 3000);

  renderVisibility();
  updateAttachmentPreview();
  fetchChat(true);
  fetchUnreadCount();
  if (state.open) {
    markRead();
    updatePresenceState(false, true);
  }
})();
</script>
<?php endif; ?>


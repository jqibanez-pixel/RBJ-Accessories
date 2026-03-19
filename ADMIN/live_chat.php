<?php
session_start();

if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['admin', 'superadmin'], true)) {
    header("Location: ../login.php");
    exit();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = (string)$_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Live Chat - Admin</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link href="assets/admin-enhancements.css" rel="stylesheet">
<style>
* { box-sizing: border-box; }
body { margin: 0; font-family: Arial, sans-serif; background: #f4f6f8; color: #1e2a35; }
.admin-container { display: flex; min-height: 100vh; }
.sidebar {
  width: 220px;
  background: #111;
  color: #fff;
  padding: 20px;
}
.sidebar .logo {
  text-align: center;
  margin-bottom: 20px;
}
.sidebar a {
  display: block;
  color: #fff;
  text-decoration: none;
  padding: 10px;
  margin-bottom: 5px;
}
.sidebar a:hover, .sidebar a.active { background: #444; border-radius: 5px; }
.main {
  flex: 1;
  display: grid;
  grid-template-columns: 340px 1fr;
  gap: 18px;
  padding: 24px;
}
.panel {
  background: #fff;
  border: 1px solid #dfe6ee;
  border-radius: 10px;
  overflow: hidden;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.panel-head {
  padding: 13px 14px;
  border-bottom: 1px solid #dfe6ee;
  font-weight: 700;
  color: #c9473c;
  background: #fff;
}
.thread-tools {
  display: grid;
  grid-template-columns: 1fr auto auto auto;
  gap: 8px;
  padding: 10px 12px;
  border-bottom: 1px solid #edf1f5;
  background: #fff;
}
.thread-tools input {
  flex: 1;
  min-width: 0;
  border: 1px solid #c9d5e2;
  border-radius: 8px;
  padding: 8px 10px;
  font-size: 13px;
}
.thread-tools label {
  display: inline-flex;
  gap: 4px;
  align-items: center;
  font-size: 12px;
  color: #5f6c7b;
  white-space: nowrap;
}
.thread-tools select {
  border: 1px solid #c9d5e2;
  border-radius: 8px;
  padding: 8px 10px;
  font-size: 12px;
  background: #fff;
}
.stats-row {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  padding: 10px 12px;
  border-bottom: 1px solid #edf1f5;
  background: #fff;
}
.stat-chip {
  border: 1px solid #d8e0e8;
  border-radius: 999px;
  padding: 4px 10px;
  font-size: 11px;
  font-weight: 700;
  color: #344552;
  background: #f7fafc;
}
.thread-list {
  max-height: calc(100vh - 130px);
  overflow-y: auto;
}
.thread-item {
  padding: 12px 14px;
  border-bottom: 1px solid #edf1f5;
  cursor: pointer;
}
.thread-item:hover, .thread-item.active { background: #f6f9fc; }
.thread-item .top {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 5px;
}
.thread-item .name { font-weight: 700; font-size: 14px; }
.thread-item .time { font-size: 11px; color: #7a8796; }
.thread-item .msg {
  font-size: 12px;
  color: #5f6c7b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.badge {
  background: #c9473c;
  color: #fff;
  border-radius: 999px;
  min-width: 18px;
  height: 18px;
  line-height: 18px;
  text-align: center;
  padding: 0 6px;
  font-size: 11px;
  font-weight: 700;
}
.chat-wrap {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 48px);
}
.chat-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}
.chat-sub {
  color: #7a8796;
  font-size: 12px;
  font-weight: 600;
}
.thread-meta {
  border-bottom: 1px solid #edf1f5;
  padding: 10px 12px;
  background: #fff;
  display: grid;
  gap: 8px;
}
.meta-grid {
  display: grid;
  gap: 8px;
  grid-template-columns: repeat(4, minmax(120px, 1fr));
}
.meta-grid label,
.meta-check {
  font-size: 11px;
  color: #607182;
  display: grid;
  gap: 4px;
}
.meta-grid select,
.meta-grid input,
.thread-meta textarea {
  width: 100%;
  border: 1px solid #c9d5e2;
  border-radius: 8px;
  padding: 7px 9px;
  font-size: 12px;
  background: #fff;
}
.meta-inline {
  display: grid;
  gap: 8px;
  grid-template-columns: 1fr auto;
}
.meta-save {
  border: none;
  border-radius: 8px;
  background: #1f7a56;
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  padding: 0 12px;
  cursor: pointer;
}
.meta-save[disabled] {
  opacity: 0.6;
  cursor: not-allowed;
}
.sla-indicator {
  font-size: 11px;
  font-weight: 700;
  color: #445666;
}
.thread-tags {
  display: flex;
  gap: 5px;
  flex-wrap: wrap;
}
.tag {
  font-size: 10px;
  border-radius: 999px;
  padding: 2px 8px;
  border: 1px solid #d8e0e8;
  color: #4a5d6c;
  background: #fff;
}
.tag.p-high { border-color: #efcf95; background: #fff8ea; color: #94622c; }
.tag.p-urgent { border-color: #f3b0b0; background: #fff0f0; color: #983737; }
.tag.st-pending { border-color: #bed8f8; background: #eef6ff; color: #2f5f93; }
.tag.st-resolved { border-color: #b9e1c8; background: #effaf3; color: #2f6f45; }
.tag.st-closed { border-color: #d6dbe1; background: #f4f6f8; color: #5f6872; }
.chat-feed {
  flex: 1;
  overflow-y: auto;
  padding: 14px;
  background: #f9fbfd;
  display: flex;
  flex-direction: column;
  gap: 9px;
}
.msg {
  margin-bottom: 0;
  max-width: 78%;
  display: flex;
  flex-direction: column;
  gap: 4px;
  align-self: flex-start;
}
.msg-bubble {
  width: fit-content;
  max-width: 100%;
  padding: 9px 11px;
  border-radius: 10px;
  font-size: 13px;
  line-height: 1.45;
}
.msg.user { align-self: flex-start; }
.msg.user .msg-bubble { background: #ffffff; border: 1px solid #d9e2ec; }
.msg.admin { align-self: flex-end; }
.msg.admin .msg-bubble { background: #c9473c; color: #fff; }
.msg-text {
  white-space: pre-wrap;
}
.msg-meta {
  margin-top: 4px;
  display: flex;
  gap: 6px;
  align-items: center;
  justify-content: flex-start;
  font-size: 10px;
  opacity: 0.86;
}
.msg-meta .status {
  font-weight: 700;
}
.msg.admin .msg-meta {
  justify-content: flex-end;
}
.composer {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 8px;
  padding: 10px;
  border-top: 1px solid #dfe6ee;
  background: #fff;
}
.composer textarea {
  flex: 1;
  min-width: 0;
  border: 1px solid #c9d5e2;
  border-radius: 9px;
  padding: 10px;
  min-height: 42px;
  max-height: 120px;
  resize: vertical;
  font: inherit;
}
.composer button {
  border: none;
  border-radius: 9px;
  background: #c9473c;
  color: #fff;
  padding: 0 14px;
  cursor: pointer;
}
.composer button[disabled] {
  opacity: 0.6;
  cursor: not-allowed;
}
.quick-replies {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  padding: 8px 10px 0;
  border-top: 1px solid #edf1f5;
  background: #fff;
}
.quick-btn {
  border: 1px solid #d8e0e8;
  border-radius: 999px;
  background: #fff;
  color: #32424f;
  font-size: 11px;
  padding: 5px 9px;
  cursor: pointer;
}
.composer-meta {
  grid-column: 1 / -1;
  display: flex;
  justify-content: space-between;
  align-items: center;
  color: #6f7f8f;
  font-size: 11px;
}
.empty {
  text-align: center;
  color: #697787;
  padding: 22px;
}
@media (max-width: 980px) {
  .sidebar { display: none; }
  .main { grid-template-columns: 1fr; }
  .thread-list { max-height: 280px; }
  .chat-wrap { height: 62vh; }
  .thread-tools { grid-template-columns: 1fr 1fr; }
  .meta-grid { grid-template-columns: 1fr 1fr; }
  .meta-inline { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<div class="admin-container">
  <aside class="sidebar">
    <div class="logo">
      <a href="/rbjsystem/ADMIN/dashboard_admin.php">
        <img src="/rbjsystem/rbjlogo.png" alt="RBJ Logo" style="height: 100px; width: auto; display: block; margin: 0 auto;">
      </a>
    </div>
    <a href="/rbjsystem/ADMIN/dashboard_admin.php">Dashboard</a>
    <a href="/rbjsystem/ADMIN/users_admin.php">Users</a>
    <a href="/rbjsystem/ADMIN/orders_admin.php">Orders</a>
    <a href="/rbjsystem/ADMIN/products_admin.php">Products</a>
      <a href="/rbjsystem/ADMIN/vouchers_admin.php">Vouchers</a>
    <a href="/rbjsystem/ADMIN/feedback_admin.php">Feedbacks</a>
    <a href="/rbjsystem/ADMIN/admin_support.php">Support</a>
      <a href="/rbjsystem/ADMIN/activity_logs_admin.php">Activity Logs</a>
      <a href="/rbjsystem/ADMIN/live_chat.php" class="active">Live Chat</a>
    <a href="/rbjsystem/logout.php">Logout</a>
  </aside>

  <main class="main">
    <section class="panel">
      <div class="panel-head">Live Chat Threads</div>
      <div class="stats-row">
        <span class="stat-chip" id="statOpen">Open: 0</span>
        <span class="stat-chip" id="statPending">Pending: 0</span>
        <span class="stat-chip" id="statResolved">Resolved: 0</span>
        <span class="stat-chip" id="statEscalated">Escalated: 0</span>
        <span class="stat-chip" id="statUnassigned">Unassigned: 0</span>
      </div>
      <div class="thread-tools">
        <input id="threadSearch" type="search" placeholder="Search buyer..." autocomplete="off">
        <select id="statusFilter" title="Filter by status">
          <option value="">All Status</option>
          <option value="open">Open</option>
          <option value="pending">Pending</option>
          <option value="resolved">Resolved</option>
          <option value="closed">Closed</option>
        </select>
        <select id="assignedFilter" title="Filter by assignment">
          <option value="">All Threads</option>
          <option value="mine">Assigned to Me</option>
          <option value="unassigned">Unassigned</option>
        </select>
        <label><input type="checkbox" id="unreadOnly"> Unread</label>
      </div>
      <div id="threadList" class="thread-list"></div>
    </section>

    <section class="panel chat-wrap">
      <div class="panel-head chat-head">
        <span id="chatHead">Select a user thread</span>
        <span id="chatSub" class="chat-sub">No active thread</span>
      </div>
      <div class="thread-meta">
        <div class="meta-grid">
          <label>Assigned Admin
            <select id="assignedAdmin">
              <option value="0">Unassigned</option>
            </select>
          </label>
          <label>Status
            <select id="threadStatus">
              <option value="open">Open</option>
              <option value="pending">Pending</option>
              <option value="resolved">Resolved</option>
              <option value="closed">Closed</option>
            </select>
          </label>
          <label>Priority
            <select id="threadPriority">
              <option value="normal">Normal</option>
              <option value="high">High</option>
              <option value="urgent">Urgent</option>
            </select>
          </label>
          <label>Branch Tag
            <input id="branchTag" type="text" maxlength="80" placeholder="e.g. Branch 1">
          </label>
        </div>
        <div class="meta-grid">
          <label class="meta-check">Escalated
            <input id="escalatedFlag" type="checkbox">
          </label>
          <label>Escalation Reason
            <input id="escalationReason" type="text" maxlength="255" placeholder="Required if escalated">
          </label>
          <span class="sla-indicator" id="slaIndicator">SLA: --</span>
        </div>
        <div class="meta-inline">
          <textarea id="internalNote" rows="2" maxlength="5000" placeholder="Internal note (for admins only)"></textarea>
          <button type="button" id="saveMetaBtn" class="meta-save" disabled>Save Thread</button>
        </div>
      </div>
      <div id="chatFeed" class="chat-feed"></div>
      <div class="quick-replies" id="quickReplies"></div>
      <form id="chatForm" class="composer">
        <textarea id="chatInput" placeholder="Reply to selected user..." maxlength="1000" autocomplete="off"></textarea>
        <button id="sendBtn" type="submit" disabled><i class='bx bx-send'></i></button>
        <div class="composer-meta">
          <span>Enter to send, Shift+Enter for new line</span>
          <span id="charCount">0/1000</span>
        </div>
      </form>
    </section>
  </main>
</div>

<script>
(function () {
  var api = 'live_chat_api.php';
  var csrfToken = <?php echo json_encode($csrf_token); ?>;
  var threadList = document.getElementById('threadList');
  var chatHead = document.getElementById('chatHead');
  var chatSub = document.getElementById('chatSub');
  var chatFeed = document.getElementById('chatFeed');
  var chatForm = document.getElementById('chatForm');
  var chatInput = document.getElementById('chatInput');
  var sendBtn = document.getElementById('sendBtn');
  var charCount = document.getElementById('charCount');
  var threadSearch = document.getElementById('threadSearch');
  var unreadOnly = document.getElementById('unreadOnly');
  var statusFilter = document.getElementById('statusFilter');
  var assignedFilter = document.getElementById('assignedFilter');
  var statOpen = document.getElementById('statOpen');
  var statPending = document.getElementById('statPending');
  var statResolved = document.getElementById('statResolved');
  var statEscalated = document.getElementById('statEscalated');
  var statUnassigned = document.getElementById('statUnassigned');
  var assignedAdmin = document.getElementById('assignedAdmin');
  var threadStatus = document.getElementById('threadStatus');
  var threadPriority = document.getElementById('threadPriority');
  var branchTag = document.getElementById('branchTag');
  var escalatedFlag = document.getElementById('escalatedFlag');
  var escalationReason = document.getElementById('escalationReason');
  var internalNote = document.getElementById('internalNote');
  var saveMetaBtn = document.getElementById('saveMetaBtn');
  var slaIndicator = document.getElementById('slaIndicator');
  var quickReplies = document.getElementById('quickReplies');

  var activeUserId = 0;
  var initialUserId = Number(new URLSearchParams(window.location.search).get('user_id')) || 0;
  var lastMessageId = 0;
  var threads = [];
  var admins = [];
  var threadFetchInFlight = false;
  var messageFetchInFlight = false;
  var metaSaveInFlight = false;
  var activeThreadName = '';
  var activeThreadMeta = null;
  var threadSearchDebounce = null;

  function escapeHtml(str) {
    return (str || '').replace(/[&<>"']/g, function (m) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[m];
    });
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
    if (!dt) return '';
    return dt.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
  }

  function formatThreadTime(raw) {
    var dt = parseDateTime(raw);
    if (!dt) return '';
    var now = new Date();
    var sameDay = now.toDateString() === dt.toDateString();
    if (sameDay) return formatTime(raw);
    return dt.toLocaleDateString([], { month: 'short', day: 'numeric' });
  }

  function formatStatus(status) {
    if (status === 'seen') return 'Seen';
    if (status === 'delivered') return 'Delivered';
    return 'Sent';
  }

  function readableStatus(st) {
    if (st === 'pending') return 'Pending';
    if (st === 'resolved') return 'Resolved';
    if (st === 'closed') return 'Closed';
    return 'Open';
  }

  function readablePriority(pr) {
    if (pr === 'urgent') return 'Urgent';
    if (pr === 'high') return 'High';
    return 'Normal';
  }

  function buildThreadTags(t) {
    var tags = [];
    var status = String(t.status || 'open');
    var priority = String(t.priority || 'normal');
    tags.push('<span class="tag st-' + escapeHtml(status) + '">' + escapeHtml(readableStatus(status)) + '</span>');
    tags.push('<span class="tag p-' + escapeHtml(priority) + '">' + escapeHtml(readablePriority(priority)) + '</span>');
    if (Number(t.escalated || 0) === 1) tags.push('<span class="tag">Escalated</span>');
    if (t.branch_tag) tags.push('<span class="tag">' + escapeHtml(t.branch_tag) + '</span>');
    if (t.assigned_admin_name) tags.push('<span class="tag">Assigned: ' + escapeHtml(t.assigned_admin_name) + '</span>');
    return '<div class="thread-tags">' + tags.join('') + '</div>';
  }

  function renderThreads() {
    if (!threads.length) {
      threadList.innerHTML = '<div class="empty">No chat threads yet.</div>';
      return;
    }

    threadList.innerHTML = threads.map(function (t) {
      var cls = 'thread-item' + (t.user_id === activeUserId ? ' active' : '');
      var badge = t.unread_count > 0 ? '<span class="badge">' + (t.unread_count > 99 ? '99+' : t.unread_count) + '</span>' : '';
      return (
        '<div class="' + cls + '" data-user-id="' + t.user_id + '">' +
          '<div class="top">' +
            '<span class="name">' + escapeHtml(t.username) + '</span>' +
            '<span class="time">' + escapeHtml(formatThreadTime(t.last_at || '')) + '</span>' +
          '</div>' +
          '<div class="top">' +
            '<span class="msg">' + escapeHtml(t.last_message || '') + '</span>' +
            badge +
          '</div>' +
          buildThreadTags(t) +
        '</div>'
      );
    }).join('');
  }

  function setStats(stats) {
    var s = stats || {};
    statOpen.textContent = 'Open: ' + Number(s.open || 0);
    statPending.textContent = 'Pending: ' + Number(s.pending || 0);
    statResolved.textContent = 'Resolved: ' + Number(s.resolved || 0);
    statEscalated.textContent = 'Escalated: ' + Number(s.escalated || 0);
    statUnassigned.textContent = 'Unassigned: ' + Number(s.unassigned || 0);
  }

  function formatAge(raw) {
    var dt = parseDateTime(raw);
    if (!dt) return '--';
    var diffMin = Math.max(0, Math.floor((Date.now() - dt.getTime()) / 60000));
    if (diffMin < 1) return 'just now';
    if (diffMin < 60) return diffMin + 'm';
    var hr = Math.floor(diffMin / 60);
    var rem = diffMin % 60;
    return hr + 'h ' + rem + 'm';
  }

  function updateSla(thread) {
    if (!thread) {
      slaIndicator.textContent = 'SLA: --';
      return;
    }
    var lastUserAt = thread.last_user_message_at || '';
    var unread = Number(thread.unread_count || 0);
    if (!lastUserAt) {
      slaIndicator.textContent = 'SLA: No user message yet';
      return;
    }
    if (unread > 0) {
      slaIndicator.textContent = 'SLA Wait: ' + formatAge(lastUserAt) + ' since last buyer message';
      return;
    }
    slaIndicator.textContent = 'SLA: Replied';
  }

  function updateChatHead() {
    if (!activeUserId) {
      chatHead.textContent = 'Select a user thread';
      chatSub.textContent = 'No active thread';
      updateSla(null);
      return;
    }
    chatHead.textContent = 'Chat with ' + activeThreadName;
    var activeThread = threads.find(function (t) { return t.user_id === activeUserId; });
    var unread = Number(activeThread && activeThread.unread_count ? activeThread.unread_count : 0);
    var status = activeThread && activeThread.status ? String(activeThread.status) : 'open';
    chatSub.textContent = unread > 0
      ? (unread + ' unread message' + (unread > 1 ? 's' : '') + ' - ' + readableStatus(status))
      : ('Active conversation - ' + readableStatus(status));
    updateSla(activeThread);
  }

  function setMetaEnabled(enabled) {
    assignedAdmin.disabled = !enabled;
    threadStatus.disabled = !enabled;
    threadPriority.disabled = !enabled;
    branchTag.disabled = !enabled;
    escalatedFlag.disabled = !enabled;
    escalationReason.disabled = !enabled;
    internalNote.disabled = !enabled;
    saveMetaBtn.disabled = !enabled || metaSaveInFlight;
  }

  function applyThreadMeta(meta) {
    activeThreadMeta = meta || null;
    if (!meta) {
      assignedAdmin.value = '0';
      threadStatus.value = 'open';
      threadPriority.value = 'normal';
      branchTag.value = '';
      escalatedFlag.checked = false;
      escalationReason.value = '';
      internalNote.value = '';
      setMetaEnabled(false);
      return;
    }
    assignedAdmin.value = String(Number(meta.assigned_admin_id || 0));
    threadStatus.value = String(meta.status || 'open');
    threadPriority.value = String(meta.priority || 'normal');
    branchTag.value = String(meta.branch_tag || '');
    escalatedFlag.checked = Number(meta.escalated || 0) === 1;
    escalationReason.value = String(meta.escalation_reason || '');
    internalNote.value = String(meta.internal_note || '');
    setMetaEnabled(true);
  }

  function updateComposerState() {
    var len = (chatInput.value || '').length;
    if (charCount) {
      charCount.textContent = len + '/1000';
    }
    var enabled = !!activeUserId && chatInput.value.trim() !== '';
    sendBtn.disabled = !enabled;
    chatInput.disabled = !activeUserId;
    if (!activeUserId) {
      setMetaEnabled(false);
    }
  }

  function appendMessage(msg) {
    var nearBottom = chatFeed.scrollHeight - chatFeed.scrollTop - chatFeed.clientHeight < 80;
    var div = document.createElement('div');
    div.className = 'msg ' + (msg.role === 'admin' ? 'admin' : 'user');
    div.dataset.messageId = String(Number(msg.id) || 0);

    var bubble = document.createElement('div');
    bubble.className = 'msg-bubble';

    var text = document.createElement('div');
    text.className = 'msg-text';
    text.textContent = msg.text || '';
    bubble.appendChild(text);
    div.appendChild(bubble);

    var meta = document.createElement('div');
    meta.className = 'msg-meta';

    var timeEl = document.createElement('span');
    timeEl.className = 'time';
    timeEl.textContent = msg.created_time || formatTime(msg.created_at) || '--:--';
    meta.appendChild(timeEl);

    if (msg.role === 'admin') {
      var statusEl = document.createElement('span');
      statusEl.className = 'status';
      statusEl.textContent = formatStatus(msg.delivery_status);
      meta.appendChild(statusEl);
    }

    div.appendChild(meta);
    chatFeed.appendChild(div);
    if (nearBottom || msg.role === 'admin') {
      chatFeed.scrollTop = chatFeed.scrollHeight;
    }
  }

  function updateMessageStatus(update) {
    var id = Number(update && update.id);
    if (!id) return;
    var node = chatFeed.querySelector('.msg.admin[data-message-id="' + id + '"]');
    if (!node) return;
    var statusEl = node.querySelector('.msg-meta .status');
    if (!statusEl) return;
    statusEl.textContent = formatStatus(update.delivery_status);
  }

  function buildQuery() {
    var qs = new URLSearchParams();
    qs.set('action', 'threads');
    var q = (threadSearch.value || '').trim();
    if (q) qs.set('q', q);
    if (statusFilter.value) qs.set('status', statusFilter.value);
    if (assignedFilter.value) qs.set('assigned', assignedFilter.value);
    if (unreadOnly.checked) qs.set('unread_only', '1');
    return qs.toString();
  }

  async function fetchThreadStats() {
    try {
      var res = await fetch(api + '?action=thread_stats', { credentials: 'same-origin' });
      if (!res.ok) return;
      var data = await res.json();
      if (!data.ok) return;
      setStats(data.stats || {});
    } catch (e) {
    }
  }

  async function fetchAdmins() {
    try {
      var res = await fetch(api + '?action=admins', { credentials: 'same-origin' });
      if (!res.ok) return;
      var data = await res.json();
      if (!data.ok) return;
      admins = Array.isArray(data.admins) ? data.admins : [];
      var current = assignedAdmin.value;
      var options = ['<option value="0">Unassigned</option>'];
      admins.forEach(function (a) {
        var label = String(a.username || 'Admin') + ' (' + String(a.role || 'admin') + ')';
        options.push('<option value="' + Number(a.id || 0) + '">' + escapeHtml(label) + '</option>');
      });
      assignedAdmin.innerHTML = options.join('');
      assignedAdmin.value = current || '0';
    } catch (e) {
    }
  }

  async function fetchCannedReplies() {
    try {
      var res = await fetch(api + '?action=canned_replies', { credentials: 'same-origin' });
      if (!res.ok) return;
      var data = await res.json();
      if (!data.ok) return;
      var replies = Array.isArray(data.replies) ? data.replies : [];
      if (!replies.length) {
        quickReplies.innerHTML = '';
        return;
      }
      quickReplies.innerHTML = replies.map(function (r) {
        return '<button type="button" class="quick-btn" data-reply="' + escapeHtml(String(r.body || '')) + '">' + escapeHtml(String(r.title || 'Quick Reply')) + '</button>';
      }).join('');
    } catch (e) {
    }
  }

  async function fetchThreads() {
    if (threadFetchInFlight) return;
    threadFetchInFlight = true;
    try {
      var res = await fetch(api + '?' + buildQuery(), { credentials: 'same-origin' });
      if (!res.ok) return;
      var data = await res.json();
      if (!data.ok) return;
      threads = data.threads || [];
      if (initialUserId > 0 && !activeUserId) {
        var matched = threads.find(function (t) { return t.user_id === initialUserId; });
        if (matched) {
          selectThread(initialUserId, matched.username);
        }
      }
      if (!activeUserId && threads.length > 0) {
        var firstUnread = threads.find(function (t) { return Number(t.unread_count || 0) > 0; });
        var firstThread = firstUnread || threads[0];
        selectThread(firstThread.user_id, firstThread.username);
      } else if (activeUserId) {
        var activeThread = threads.find(function (t) { return t.user_id === activeUserId; });
        if (activeThread) {
          activeThreadName = activeThread.username || activeThreadName;
          applyThreadMeta(activeThread);
        }
      }
      updateChatHead();
      renderThreads();
    } catch (e) {
    } finally {
      threadFetchInFlight = false;
    }
  }

  async function fetchMessages(reset) {
    if (!activeUserId) return;
    if (messageFetchInFlight) return;
    messageFetchInFlight = true;
    if (reset) {
      lastMessageId = 0;
      chatFeed.innerHTML = '';
    }
    try {
      var res = await fetch(api + '?action=fetch&user_id=' + encodeURIComponent(activeUserId) + '&since_id=' + encodeURIComponent(lastMessageId) + '&client_last_id=' + encodeURIComponent(lastMessageId), { credentials: 'same-origin' });
      if (!res.ok) return;
      var data = await res.json();
      if (!data.ok) return;
      var messages = data.messages || [];
      messages.forEach(function (msg) {
        appendMessage(msg);
        lastMessageId = Math.max(lastMessageId, Number(msg.id) || 0);
      });
      var updates = Array.isArray(data.status_updates) ? data.status_updates : [];
      updates.forEach(updateMessageStatus);
      if (data.thread) {
        applyThreadMeta(data.thread);
      }
      await markRead();
      await fetchThreads();
      await fetchThreadStats();
      updateChatHead();
    } catch (e) {
    } finally {
      messageFetchInFlight = false;
    }
  }

  function selectThread(userId, username) {
    activeUserId = Number(userId) || 0;
    activeThreadName = username || '';
    var thread = threads.find(function (t) { return t.user_id === activeUserId; });
    applyThreadMeta(thread || null);
    renderThreads();
    updateChatHead();
    updateComposerState();
    fetchMessages(true);
  }

  async function markRead() {
    if (!activeUserId) return;
    try {
      var body = new URLSearchParams();
      body.set('action', 'mark_read');
      body.set('user_id', String(activeUserId));
      body.set('csrf_token', csrfToken);
      await fetch(api, {
        method: 'POST',
        body: body,
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }
      });
    } catch (e) {
    }
  }

  threadList.addEventListener('click', function (e) {
    var item = e.target.closest('.thread-item[data-user-id]');
    if (!item) return;
    var nextUserId = Number(item.getAttribute('data-user-id')) || 0;
    var thread = threads.find(function (t) { return t.user_id === activeUserId; });
    if (!thread || thread.user_id !== nextUserId) {
      thread = threads.find(function (t) { return t.user_id === nextUserId; });
    }
    selectThread(nextUserId, thread ? thread.username : '');
  });

  async function saveThreadMeta() {
    if (!activeUserId || metaSaveInFlight) return;
    if (escalatedFlag.checked && escalationReason.value.trim() === '') {
      escalationReason.focus();
      return;
    }
    metaSaveInFlight = true;
    setMetaEnabled(true);
    try {
      var body = new URLSearchParams();
      body.set('action', 'update_thread_meta');
      body.set('user_id', String(activeUserId));
      body.set('assigned_admin_id', String(Number(assignedAdmin.value || 0)));
      body.set('status', threadStatus.value || 'open');
      body.set('priority', threadPriority.value || 'normal');
      body.set('branch_tag', branchTag.value || '');
      body.set('escalated', escalatedFlag.checked ? '1' : '0');
      body.set('escalation_reason', escalationReason.value || '');
      body.set('internal_note', internalNote.value || '');
      body.set('csrf_token', csrfToken);
      var res = await fetch(api, {
        method: 'POST',
        body: body,
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }
      });
      if (!res.ok) return;
      var data = await res.json();
      if (!data.ok) return;
      await fetchThreads();
      await fetchThreadStats();
      await fetchMessages(false);
    } catch (e) {
    } finally {
      metaSaveInFlight = false;
      setMetaEnabled(!!activeUserId);
    }
  }

  chatForm.addEventListener('submit', async function (e) {
    e.preventDefault();
    if (!activeUserId) return;
    var text = chatInput.value.trim();
    if (!text) return;
    try {
      var body = new URLSearchParams();
      body.set('action', 'send');
      body.set('user_id', String(activeUserId));
      body.set('message', text);
      body.set('csrf_token', csrfToken);
      var res = await fetch(api, {
        method: 'POST',
        body: body,
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }
      });
      if (!res.ok) return;
      var data = await res.json();
      if (!data.ok) return;
      chatInput.value = '';
      updateComposerState();
      fetchMessages(false);
    } catch (e) {
    }
  });

  chatInput.addEventListener('input', updateComposerState);
  chatInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      if (!sendBtn.disabled) {
        chatForm.requestSubmit();
      }
    }
  });

  if (threadSearch) {
    threadSearch.addEventListener('input', function () {
      if (threadSearchDebounce) window.clearTimeout(threadSearchDebounce);
      threadSearchDebounce = window.setTimeout(fetchThreads, 220);
    });
  }
  if (unreadOnly) {
    unreadOnly.addEventListener('change', fetchThreads);
  }
  if (statusFilter) {
    statusFilter.addEventListener('change', fetchThreads);
  }
  if (assignedFilter) {
    assignedFilter.addEventListener('change', fetchThreads);
  }

  if (quickReplies) {
    quickReplies.addEventListener('click', function (e) {
      var btn = e.target.closest('.quick-btn[data-reply]');
      if (!btn || !activeUserId) return;
      var text = String(btn.getAttribute('data-reply') || '').trim();
      if (!text) return;
      if (chatInput.value.trim() !== '') {
        chatInput.value = chatInput.value.trim() + '\n' + text;
      } else {
        chatInput.value = text;
      }
      updateComposerState();
      chatInput.focus();
    });
  }

  saveMetaBtn.addEventListener('click', saveThreadMeta);
  [assignedAdmin, threadStatus, threadPriority, branchTag, escalatedFlag, escalationReason, internalNote].forEach(function (el) {
    if (!el) return;
    el.addEventListener('change', function () { setMetaEnabled(!!activeUserId); });
    el.addEventListener('input', function () { setMetaEnabled(!!activeUserId); });
  });

  fetchAdmins();
  fetchCannedReplies();
  fetchThreadStats();
  updateComposerState();
  applyThreadMeta(null);
  fetchThreads();
  setInterval(fetchThreadStats, 8000);
  setInterval(fetchThreads, 4000);
  setInterval(function () { fetchMessages(false); }, 2200);
})();
</script>
</body>
</html>






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
$current_admin_name = (string)($_SESSION['username'] ?? 'Admin');
$current_admin_role = (string)($_SESSION['role'] ?? 'admin');
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
.main {
  flex: 1;
  display: grid;
  grid-template-columns: 340px 1fr;
  gap: 18px;
  padding: 24px;
  min-width: 0;
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
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto;
  grid-template-areas:
    "search search search"
    "status assigned unread";
  gap: 8px;
  padding: 10px 12px;
  border-bottom: 1px solid #edf1f5;
  background: #fff;
  align-items: center;
}
.thread-tools .thread-search {
  grid-area: search;
  width: 100%;
  min-width: 0;
  border: 1px solid #c9d5e2;
  border-radius: 8px;
  padding: 8px 10px;
  font-size: 13px;
}
.thread-tools .thread-unread {
  grid-area: unread;
  display: inline-flex;
  gap: 4px;
  align-items: center;
  font-size: 12px;
  color: #5f6c7b;
  white-space: nowrap;
  justify-self: start;
  min-width: 76px;
}
.thread-tools select {
  width: 100%;
  min-width: 0;
  border: 1px solid #c9d5e2;
  border-radius: 8px;
  padding: 8px 10px;
  font-size: 12px;
  background: #fff;
}
.thread-tools .thread-status-filter {
  grid-area: status;
}
.thread-tools .thread-assigned-filter {
  grid-area: assigned;
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
  min-height: 0;
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
  min-width: 0;
}
.chat-shell {
  flex: 1;
  min-height: 0;
  position: relative;
}
.drawer-backdrop {
  position: absolute;
  inset: 0;
  background: rgba(15, 23, 42, 0.42);
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.22s ease;
  z-index: 5;
}
.chat-shell.drawer-open .drawer-backdrop {
  opacity: 1;
  pointer-events: auto;
}
.chat-main {
  height: 100%;
  display: flex;
  flex-direction: column;
}
.admin-drawer {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  width: 312px;
  background: #fff;
  border-left: 1px solid #edf1f5;
  box-shadow: -10px 0 24px rgba(15, 23, 42, 0.08);
  transform: translateX(100%);
  opacity: 0;
  pointer-events: none;
  transition: transform 0.22s ease, opacity 0.22s ease;
  z-index: 6;
}
.chat-shell.drawer-open .admin-drawer {
  transform: translateX(0);
  opacity: 1;
  pointer-events: auto;
}
.admin-drawer-inner {
  height: 100%;
  overflow-y: auto;
}
.drawer-head {
  position: sticky;
  top: 0;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 14px 14px 10px;
  background: rgba(255,255,255,0.96);
  border-bottom: 1px solid #edf1f5;
  backdrop-filter: blur(8px);
}
.drawer-head-main {
  display: grid;
  gap: 2px;
}
.drawer-head-title {
  font-size: 13px;
  font-weight: 700;
  color: #243543;
}
.drawer-head-subtitle {
  font-size: 11px;
  color: #607182;
}
.drawer-close-btn {
  border: 1px solid #d8e0e8;
  border-radius: 999px;
  width: 32px;
  height: 32px;
  background: #fff;
  color: #344552;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 18px;
}
.drawer-tabs {
  position: sticky;
  top: 59px;
  z-index: 2;
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 6px;
  padding: 10px 14px;
  background: rgba(255,255,255,0.96);
  border-bottom: 1px solid #edf1f5;
  backdrop-filter: blur(8px);
}
.drawer-tab {
  border: 1px solid #d8e0e8;
  border-radius: 999px;
  background: #fff;
  color: #4a5d6c;
  font-size: 11px;
  font-weight: 700;
  padding: 7px 8px;
  cursor: pointer;
}
.drawer-tab.is-active {
  border-color: #c9473c;
  background: #fff1ef;
  color: #a0352d;
}
.drawer-panel {
  display: none;
  padding-bottom: 10px;
}
.drawer-panel.is-active {
  display: block;
}
.drawer-summary {
  position: sticky;
  top: 112px;
  z-index: 1;
  display: grid;
  gap: 6px;
  padding: 10px 14px 12px;
  background: rgba(255,255,255,0.96);
  border-bottom: 1px solid #edf1f5;
  backdrop-filter: blur(8px);
}
.drawer-summary-title {
  font-size: 12px;
  font-weight: 700;
  color: #243543;
}
.drawer-summary-sub {
  font-size: 11px;
  color: #607182;
}
.drawer-summary-chips {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}
.chat-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.chat-sub {
  color: #7a8796;
  font-size: 12px;
  font-weight: 600;
}
.chat-head-main {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}
.chat-head-title {
  font-weight: 700;
  color: #c9473c;
}
.chat-head-side {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  justify-content: flex-end;
  min-width: 0;
}
.session-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 9px;
  border: 1px solid #d8e0e8;
  border-radius: 999px;
  background: #f7fafc;
  color: #344552;
  font-size: 11px;
  font-weight: 700;
}
.session-role {
  text-transform: capitalize;
  color: #607182;
}
.notify-btn {
  border: 1px solid #d8e0e8;
  border-radius: 999px;
  background: #fff;
  color: #344552;
  font-size: 11px;
  font-weight: 700;
  padding: 6px 10px;
  cursor: pointer;
}
.drawer-toggle-btn {
  border: 1px solid #d8e0e8;
  border-radius: 999px;
  background: #fff;
  color: #344552;
  font-size: 11px;
  font-weight: 700;
  padding: 6px 10px;
  cursor: pointer;
}
.notify-btn.is-on {
  border-color: #c9473c;
  background: #fff4f2;
  color: #a0352d;
}
.notify-btn.is-blocked {
  border-color: #d6b35f;
  background: #fff8e7;
  color: #8c6820;
}
.alert-status {
  min-height: 18px;
  padding: 0 12px 8px;
  font-size: 11px;
  color: #607182;
  background: #fff;
  border-bottom: 1px solid #edf1f5;
}
.alert-status.is-blocked {
  color: #8c6820;
  background: #fffaf0;
}
.alert-status a {
  color: inherit;
  font-weight: 700;
  text-decoration: underline;
}
.presence-bar {
  min-height: 28px;
  padding: 8px 12px;
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  background: #fff;
  border-bottom: 1px solid #edf1f5;
}
.chat-jumpbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  flex-wrap: wrap;
  padding: 8px 12px;
  background: #fff;
  border-bottom: 1px solid #edf1f5;
}
.chat-jump-actions {
  display: inline-flex;
  gap: 6px;
  flex-wrap: wrap;
}
.jump-btn {
  border: 1px solid #d8e0e8;
  border-radius: 999px;
  background: #fff;
  color: #344552;
  font-size: 11px;
  font-weight: 700;
  padding: 6px 10px;
  cursor: pointer;
}
.jump-btn[disabled] {
  opacity: 0.58;
  cursor: not-allowed;
}
.chat-jump-hint {
  font-size: 11px;
  color: #607182;
}
.presence-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 9px;
  border-radius: 999px;
  background: #f5f8fb;
  border: 1px solid #d8e0e8;
  font-size: 11px;
  color: #466073;
}
.thread-meta-card {
  border-bottom: 1px solid #edf1f5;
  background: #fff;
}
.thread-meta-card summary {
  list-style: none;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 12px;
  cursor: pointer;
}
.thread-meta-card summary::-webkit-details-marker {
  display: none;
}
.thread-meta-heading {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.thread-meta-title {
  font-size: 12px;
  font-weight: 700;
  color: #263543;
}
.thread-meta-summary {
  font-size: 11px;
  color: #607182;
}
.thread-meta-toggle {
  font-size: 11px;
  font-weight: 700;
  color: #c9473c;
  white-space: nowrap;
}
.thread-meta-card[open] .thread-meta-toggle::after {
  content: 'Hide';
}
.thread-meta-card:not([open]) .thread-meta-toggle::after {
  content: 'Show';
}
.thread-meta {
  padding: 12px 14px 14px;
  display: grid;
  gap: 8px;
}
.meta-grid {
  display: grid;
  gap: 8px;
  grid-template-columns: repeat(4, minmax(120px, 1fr));
}
.admin-drawer .meta-grid {
  grid-template-columns: 1fr;
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
.admin-drawer .meta-inline {
  grid-template-columns: 1fr;
}
.thread-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.admin-drawer .thread-actions {
  flex-direction: column;
  align-items: stretch;
}
.meta-quick-btn {
  border: 1px solid #d8e0e8;
  border-radius: 8px;
  background: #fff;
  color: #344552;
  font-size: 12px;
  font-weight: 700;
  padding: 8px 10px;
  cursor: pointer;
}
.meta-quick-btn[disabled] {
  opacity: 0.6;
  cursor: not-allowed;
}
.meta-help {
  font-size: 11px;
  color: #607182;
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
.tag.context { border-color: #d6dde6; background: #f7fafc; color: #4d6173; }
.thread-side-panels {
  display: grid;
  gap: 10px;
  grid-template-columns: 1.2fr 1fr;
  padding: 14px;
  background: #fff;
}
.admin-drawer .thread-side-panels {
  grid-template-columns: 1fr;
}
.thread-side-card {
  border-bottom: 1px solid #edf1f5;
  background: #fff;
}
.thread-side-card summary {
  list-style: none;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 12px;
  cursor: pointer;
}
.thread-side-card summary::-webkit-details-marker {
  display: none;
}
.thread-side-title {
  font-size: 12px;
  font-weight: 700;
  color: #263543;
}
.thread-side-summary {
  font-size: 11px;
  color: #607182;
}
.thread-side-toggle {
  font-size: 11px;
  font-weight: 700;
  color: #c9473c;
  white-space: nowrap;
}
.thread-side-card[open] .thread-side-toggle::after {
  content: 'Hide';
}
.thread-side-card:not([open]) .thread-side-toggle::after {
  content: 'Show';
}
.side-card {
  border: 1px solid #e0e7ef;
  border-radius: 10px;
  background: #f9fbfd;
  padding: 10px;
  display: grid;
  gap: 8px;
  min-height: 120px;
}
.side-card h3 {
  margin: 0;
  font-size: 12px;
  color: #263543;
}
.context-grid {
  display: grid;
  gap: 8px;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}
.admin-drawer .context-grid {
  grid-template-columns: 1fr;
}
.context-item {
  display: grid;
  gap: 3px;
  font-size: 11px;
}
.context-item span {
  color: #607182;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.context-item strong,
.context-item a {
  font-size: 12px;
  color: #243543;
  text-decoration: none;
  word-break: break-word;
}
.note-history {
  display: grid;
  gap: 8px;
  max-height: none;
}
.note-entry {
  border: 1px solid #e1e7ee;
  border-radius: 9px;
  background: #fff;
  padding: 8px 9px;
  display: grid;
  gap: 4px;
}
.note-entry strong {
  font-size: 12px;
  color: #243543;
}
.note-entry span {
  font-size: 10px;
  color: #6a7b8c;
}
.note-entry p {
  margin: 0;
  font-size: 12px;
  color: #354757;
  white-space: pre-wrap;
}
.chat-feed {
  flex: 1;
  overflow-y: auto;
  padding: 14px;
  background: #f9fbfd;
  display: flex;
  flex-direction: column;
  gap: 9px;
}
.chat-feed.is-empty {
  justify-content: center;
}
.chat-empty-state {
  margin: auto;
  max-width: 380px;
  padding: 18px 20px;
  border: 1px dashed #d8e0e8;
  border-radius: 14px;
  background: #fff;
  text-align: center;
  color: #5f6c7b;
  display: grid;
  gap: 7px;
}
.chat-empty-state strong {
  color: #243543;
  font-size: 14px;
}
.chat-empty-state span {
  font-size: 12px;
  line-height: 1.5;
}
.msg {
  margin-bottom: 0;
  max-width: 78%;
  display: flex;
  flex-direction: column;
  gap: 4px;
  align-self: flex-start;
  min-width: 0;
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
.msg.grouped {
  margin-top: -4px;
}
.msg.grouped.user .msg-bubble {
  border-top-left-radius: 7px;
}
.msg.grouped.admin .msg-bubble {
  border-top-right-radius: 7px;
}
.msg.grouped .msg-meta {
  margin-top: 1px;
  opacity: 0.72;
}
.msg[data-unread="1"] .msg-bubble {
  box-shadow: 0 0 0 2px rgba(190, 216, 248, 0.45);
}
.msg.typing {
  max-width: 110px;
}
.msg.typing .msg-bubble {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  min-width: 56px;
  background: #ffffff;
  border: 1px solid #d9e2ec;
}
.msg.typing.admin {
  align-self: flex-end;
}
.msg.typing.admin .msg-bubble {
  background: #c9473c;
  border-color: #c9473c;
}
.typing-dot {
  width: 7px;
  height: 7px;
  border-radius: 999px;
  background: rgba(74, 93, 108, 0.55);
  animation: typingPulse 1.1s ease-in-out infinite;
}
.msg.typing.admin .typing-dot {
  background: rgba(255,255,255,0.82);
}
.typing-dot:nth-child(2) {
  animation-delay: 0.18s;
}
.typing-dot:nth-child(3) {
  animation-delay: 0.36s;
}
@keyframes typingPulse {
  0%, 80%, 100% {
    transform: translateY(0);
    opacity: 0.45;
  }
  40% {
    transform: translateY(-3px);
    opacity: 1;
  }
}
.msg-text {
  white-space: pre-wrap;
}
.msg-attachment {
  display: grid;
  gap: 6px;
  margin-top: 6px;
}
.msg-attachment a {
  color: inherit;
  font-weight: 700;
  text-decoration: underline;
}
.msg-attachment img {
  max-width: 220px;
  max-height: 180px;
  border-radius: 10px;
  border: 1px solid rgba(0,0,0,0.08);
  object-fit: cover;
  background: #fff;
  cursor: zoom-in;
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
  align-items: end;
}
.composer.is-dragover {
  background: #fff6f4;
  box-shadow: inset 0 0 0 2px rgba(201, 71, 60, 0.18);
}
.composer.is-sending {
  opacity: 0.9;
}
.composer textarea {
  min-width: 0;
  border: 1px solid #c9d5e2;
  border-radius: 9px;
  padding: 10px;
  min-height: 42px;
  max-height: 120px;
  resize: vertical;
  font: inherit;
  overflow-y: hidden;
}
.composer button {
  border: none;
  border-radius: 9px;
  background: #c9473c;
  color: #fff;
  padding: 0 14px;
  cursor: pointer;
  min-height: 42px;
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
.composer-tools {
  grid-column: 1 / -1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}
.composer-left-tools {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  min-width: 0;
}
.attach-label,
.canned-select {
  border: 1px solid #d8e0e8;
  border-radius: 8px;
  background: #fff;
  color: #32424f;
  font-size: 11px;
  padding: 7px 10px;
}
.attach-label {
  cursor: pointer;
  font-weight: 700;
}
.attach-label input {
  display: none;
}
.attachment-preview {
  font-size: 11px;
  color: #5c6e7f;
}
.attachment-preview-card {
  grid-column: 1 / -1;
  display: none;
  align-items: center;
  gap: 10px;
  padding: 9px 10px;
  border: 1px solid #e0e7ef;
  border-radius: 10px;
  background: #f8fbfd;
}
.attachment-preview-card.is-visible {
  display: flex;
}
.attachment-preview-card.is-sending {
  border-color: #efcf95;
  background: #fff8ea;
}
.attachment-thumb {
  width: 64px;
  height: 64px;
  border-radius: 10px;
  overflow: hidden;
  background: #fff;
  border: 1px solid #d8e0e8;
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
}
.attachment-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.attachment-thumb i {
  font-size: 28px;
  color: #607182;
}
.attachment-meta {
  min-width: 0;
  display: grid;
  gap: 4px;
  flex: 1;
}
.attachment-name {
  font-size: 12px;
  font-weight: 700;
  color: #243543;
  word-break: break-word;
}
.attachment-type {
  font-size: 11px;
  color: #607182;
}
.attachment-clear {
  border: none;
  background: transparent;
  color: #9a3d35;
  font-size: 11px;
  cursor: pointer;
  padding: 0;
}
.composer-meta {
  grid-column: 1 / -1;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  color: #6f7f8f;
  font-size: 11px;
}
.empty {
  text-align: center;
  color: #697787;
  padding: 22px;
}
.toast-stack {
  position: fixed;
  right: 24px;
  bottom: 24px;
  z-index: 1300;
  display: grid;
  gap: 10px;
  pointer-events: none;
}
.toast {
  min-width: 220px;
  max-width: min(92vw, 340px);
  padding: 12px 14px;
  border-radius: 12px;
  background: rgba(36, 53, 67, 0.96);
  color: #fff;
  box-shadow: 0 14px 32px rgba(15, 23, 42, 0.18);
  font-size: 12px;
  line-height: 1.45;
}
.toast.success {
  background: rgba(31, 122, 86, 0.96);
}
.toast.warn {
  background: rgba(140, 104, 32, 0.96);
}
.toast.error {
  background: rgba(160, 53, 45, 0.97);
}
.lightbox {
  position: fixed;
  inset: 0;
  display: none;
  align-items: center;
  justify-content: center;
  padding: 28px;
  background: rgba(15, 23, 42, 0.82);
  z-index: 1400;
}
.lightbox.is-open {
  display: flex;
}
.lightbox-inner {
  position: relative;
  max-width: min(96vw, 1100px);
  max-height: 90vh;
  display: grid;
  gap: 10px;
  justify-items: center;
}
.lightbox img {
  max-width: 100%;
  max-height: calc(90vh - 52px);
  border-radius: 14px;
  box-shadow: 0 18px 42px rgba(0,0,0,0.32);
  background: #fff;
}
.lightbox-caption {
  color: #f4f7fb;
  font-size: 12px;
  text-align: center;
  word-break: break-word;
}
.lightbox-close {
  position: absolute;
  top: -10px;
  right: -10px;
  width: 38px;
  height: 38px;
  border-radius: 999px;
  border: 1px solid rgba(255,255,255,0.32);
  background: rgba(15, 23, 42, 0.78);
  color: #fff;
  font-size: 22px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
@media (max-width: 1240px) {
  .main {
    grid-template-columns: 300px 1fr;
    gap: 14px;
    padding: 18px;
  }
  .chat-wrap {
    height: calc(100vh - 36px);
  }
  .thread-list {
    max-height: calc(100vh - 118px);
  }
  .admin-drawer {
    width: 288px;
  }
  .drawer-summary {
    top: 112px;
  }
}
@media (max-width: 980px) {
  .sidebar { display: none; }
  .admin-container {
    min-height: 100svh;
  }
  .main {
    grid-template-columns: 1fr;
    gap: 14px;
    padding: 14px;
  }
  .thread-list {
    max-height: min(34vh, 320px);
  }
  .chat-wrap {
    height: min(72vh, calc(100svh - 120px));
  }
  .admin-drawer {
    left: 0;
    width: auto;
    border-left: 0;
    border-top: 1px solid #edf1f5;
    box-shadow: 0 -10px 24px rgba(15, 23, 42, 0.08);
    transform: translateY(100%);
    transition: transform 0.22s ease, opacity 0.22s ease;
  }
  .drawer-tabs {
    top: 63px;
  }
  .chat-shell.drawer-open .admin-drawer {
    transform: translateY(0);
  }
  .chat-head {
    align-items: flex-start;
  }
  .chat-head-side {
    width: 100%;
    justify-content: flex-start;
  }
  .session-badge {
    max-width: 100%;
  }
  .thread-tools {
    grid-template-columns: 1fr 1fr;
    grid-template-areas:
      "search search"
      "status assigned"
      "unread unread";
  }
  .meta-grid { grid-template-columns: 1fr 1fr; }
  .meta-inline { grid-template-columns: 1fr; }
  .thread-side-panels { grid-template-columns: 1fr; }
  .context-grid { grid-template-columns: 1fr; }
  .composer {
    grid-template-columns: 1fr auto;
  }
  .drawer-summary {
    top: 116px;
  }
}
@media (max-width: 760px) {
  .main {
    gap: 12px;
    padding: 12px;
  }
  .panel-head {
    padding: 12px;
  }
  .stats-row {
    gap: 6px;
  }
  .thread-list {
    max-height: min(32vh, 280px);
  }
  .chat-wrap {
    height: min(76vh, calc(100svh - 108px));
  }
  .chat-feed {
    padding: 12px 10px;
  }
  .msg {
    max-width: 90%;
  }
  .msg-attachment img {
    max-width: min(220px, 100%);
    max-height: 160px;
  }
  .composer {
    grid-template-columns: 1fr;
    gap: 10px;
  }
  .composer button[type="submit"] {
    width: 100%;
  }
  .composer-tools {
    align-items: flex-start;
  }
  .composer-left-tools {
    width: 100%;
  }
  .canned-select {
    max-width: 100%;
  }
  .attachment-preview-card {
    align-items: flex-start;
  }
  .composer-meta {
    justify-content: flex-start;
  }
  .drawer-head,
  .drawer-tabs,
  .drawer-summary {
    padding-left: 12px;
    padding-right: 12px;
  }
  .drawer-tabs {
    gap: 5px;
  }
  .drawer-tab {
    padding: 7px 6px;
    font-size: 10px;
  }
  .thread-meta,
  .thread-side-panels {
    padding-left: 12px;
    padding-right: 12px;
  }
  .meta-grid {
    grid-template-columns: 1fr;
  }
  .attachment-thumb {
    width: 56px;
    height: 56px;
  }
  .chat-jumpbar {
    padding-left: 10px;
    padding-right: 10px;
  }
  .toast-stack {
    right: 12px;
    left: 12px;
    bottom: 12px;
  }
  .toast {
    max-width: 100%;
  }
  .lightbox {
    padding: 16px;
  }
  .lightbox-close {
    top: -6px;
    right: -6px;
  }
}
@media (max-width: 560px) {
  .main {
    padding: 10px;
  }
  .thread-tools {
    grid-template-columns: 1fr;
    grid-template-areas:
      "search"
      "status"
      "assigned"
      "unread";
  }
  .chat-head-side > * {
    width: 100%;
  }
  .notify-btn,
  .drawer-toggle-btn,
  .session-badge {
    justify-content: center;
  }
  .alert-status,
  .presence-bar {
    padding-left: 10px;
    padding-right: 10px;
  }
  .quick-replies {
    padding: 8px 10px 0;
  }
  .composer {
    padding: 10px 8px;
  }
  .composer-left-tools {
    gap: 6px;
  }
  .attach-label,
  .canned-select {
    width: 100%;
  }
  .attachment-preview-card {
    flex-direction: column;
    padding: 10px;
  }
  .attachment-meta {
    width: 100%;
  }
  .admin-drawer {
    max-height: 84svh;
  }
  .drawer-head {
    top: 0;
  }
  .drawer-tabs {
    top: 61px;
  }
  .drawer-summary {
    top: 112px;
  }
  .chat-jump-actions,
  .chat-jump-hint {
    width: 100%;
  }
  .lightbox-caption {
    font-size: 11px;
  }
}
</style>
</head>
<body>
<div class="admin-container">
  <aside class="sidebar">
    <div class="logo">
      <a class="admin-logo-link" href="/rbjsystem/ADMIN/dashboard_admin.php">
        <img src="/rbjsystem/rbjlogo.png" alt="RBJ Logo">
      </a>
    </div>
    <div class="admin-identity-card">
      <div class="admin-identity-label">Logged In As</div>
      <div class="admin-identity-name"><?php echo htmlspecialchars($current_admin_name, ENT_QUOTES, 'UTF-8'); ?></div>
      <div class="admin-identity-role"><?php echo htmlspecialchars($current_admin_role, ENT_QUOTES, 'UTF-8'); ?></div>
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
        <input id="threadSearch" class="thread-search" type="search" placeholder="Search buyer..." autocomplete="off">
        <select id="statusFilter" class="thread-status-filter" title="Filter by status">
          <option value="">All Status</option>
          <option value="open">Open</option>
          <option value="pending">Pending</option>
          <option value="resolved">Resolved</option>
          <option value="closed">Closed</option>
        </select>
        <select id="assignedFilter" class="thread-assigned-filter" title="Filter by assignment">
          <option value="">All Threads</option>
          <option value="mine">Assigned to Me</option>
          <option value="unassigned">Unassigned</option>
        </select>
        <label class="thread-unread"><input type="checkbox" id="unreadOnly"> Unread</label>
      </div>
      <div id="threadList" class="thread-list"></div>
    </section>

    <section class="panel chat-wrap">
      <div class="panel-head chat-head">
        <div class="chat-head-main">
          <span id="chatHead" class="chat-head-title">Select a user thread</span>
          <span id="chatSub" class="chat-sub">No active thread</span>
        </div>
        <div class="chat-head-side">
          <button type="button" id="drawerToggleBtn" class="drawer-toggle-btn">Hide Admin Tools</button>
          <button type="button" id="notifyBtn" class="notify-btn">Enable Alerts</button>
          <span class="session-badge">
            <span>Logged in as <?php echo htmlspecialchars($current_admin_name, ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="session-role"><?php echo htmlspecialchars($current_admin_role, ENT_QUOTES, 'UTF-8'); ?></span>
          </span>
        </div>
      </div>
      <div id="alertStatus" class="alert-status">Alerts are off. Enable browser notifications to get new message alerts. <a href="https://support.google.com/chrome/answer/3220216" target="_blank" rel="noopener">How to enable notifications</a></div>
      <div id="chatShell" class="chat-shell">
        <button type="button" id="drawerBackdrop" class="drawer-backdrop" aria-label="Close admin tools"></button>
        <div class="chat-main">
          <div id="presenceBar" class="presence-bar"></div>
          <div class="chat-jumpbar">
            <div class="chat-jump-actions">
              <button type="button" id="jumpUnreadBtn" class="jump-btn" disabled>First Unread</button>
              <button type="button" id="jumpLatestBtn" class="jump-btn" disabled>Latest</button>
            </div>
            <span id="jumpHint" class="chat-jump-hint">Select a thread to jump between unread and latest messages.</span>
          </div>
          <div id="chatFeed" class="chat-feed"></div>
          <div class="quick-replies" id="quickReplies"></div>
          <form id="chatForm" class="composer">
            <textarea id="chatInput" placeholder="Reply to selected user..." maxlength="1000" autocomplete="off"></textarea>
            <button id="sendBtn" type="submit" disabled><i class='bx bx-send'></i></button>
            <div class="composer-tools">
              <div class="composer-left-tools">
                <label class="attach-label">Attach file
                  <input id="chatAttachment" type="file" accept="image/*,.pdf,.txt">
                </label>
                <select id="cannedReplySelect" class="canned-select">
                  <option value="">Insert canned reply...</option>
                </select>
                <span id="attachmentPreview" class="attachment-preview">Send image or file</span>
                <button type="button" id="clearAttachmentBtn" class="attachment-clear" hidden>Clear</button>
              </div>
            </div>
            <div id="attachmentPreviewCard" class="attachment-preview-card" aria-live="polite">
              <div id="attachmentThumb" class="attachment-thumb"><i class='bx bx-image-alt'></i></div>
              <div class="attachment-meta">
                <div id="attachmentName" class="attachment-name">No attachment selected</div>
                <div id="attachmentType" class="attachment-type">Images, PDF, or TXT up to 5MB</div>
              </div>
            </div>
            <div class="composer-meta">
              <span>Enter to send, Shift+Enter for new line, send image or file up to 5MB</span>
              <span id="charCount">0/1000</span>
            </div>
          </form>
        </div>
        <aside id="adminDrawer" class="admin-drawer">
          <div class="admin-drawer-inner">
            <div class="drawer-head">
              <div class="drawer-head-main">
                <span class="drawer-head-title">Admin Tools</span>
                <span class="drawer-head-subtitle">Details, buyer context, and internal notes.</span>
              </div>
              <button type="button" id="drawerCloseBtn" class="drawer-close-btn" aria-label="Close admin tools">&times;</button>
            </div>
            <div class="drawer-tabs" role="tablist" aria-label="Admin tools sections">
              <button type="button" class="drawer-tab is-active" id="tabBtnDetails" data-drawer-tab="details" role="tab" aria-selected="true">Details</button>
              <button type="button" class="drawer-tab" id="tabBtnContext" data-drawer-tab="context" role="tab" aria-selected="false">Context</button>
              <button type="button" class="drawer-tab" id="tabBtnNotes" data-drawer-tab="notes" role="tab" aria-selected="false">Notes</button>
            </div>
            <div class="drawer-summary">
              <span id="drawerSummaryTitle" class="drawer-summary-title">No thread selected</span>
              <span id="drawerSummarySub" class="drawer-summary-sub">Open a conversation to view admin controls, buyer context, and note history.</span>
              <div id="drawerSummaryChips" class="drawer-summary-chips"></div>
            </div>
            <section class="drawer-panel is-active" id="drawerPanelDetails" data-drawer-panel="details" role="tabpanel" aria-labelledby="tabBtnDetails">
              <div class="thread-meta-card" id="threadMetaCard">
                <div class="thread-meta">
                  <div class="thread-meta-heading">
                    <span class="thread-meta-title">Thread details</span>
                    <span class="thread-meta-summary" id="threadMetaSummary">Select a thread to view admin controls.</span>
                  </div>
                  <span class="thread-meta-toggle" aria-hidden="true"></span>
                </div>
              </div>
              <div class="thread-meta">
                <div class="thread-actions">
                  <button type="button" id="assignMeBtn" class="meta-quick-btn" disabled>Assign to Me</button>
                  <button type="button" id="unassignBtn" class="meta-quick-btn" disabled>Unassign</button>
                  <span id="threadActionHint" class="meta-help">Select a thread to manage assignment and status.</span>
                </div>
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
                  <label>Close Reason
                    <input id="closeReason" type="text" maxlength="255" placeholder="Required if resolved or closed">
                  </label>
                  <span class="sla-indicator" id="slaIndicator">SLA: --</span>
                </div>
                <div class="meta-inline">
                  <textarea id="internalNote" rows="2" maxlength="5000" placeholder="Internal note (for admins only)"></textarea>
                  <button type="button" id="saveMetaBtn" class="meta-save" disabled>Save Thread</button>
                </div>
              </div>
            </section>
            <section class="drawer-panel" id="drawerPanelContext" data-drawer-panel="context" role="tabpanel" aria-labelledby="tabBtnContext">
              <div class="thread-side-panels">
                <section class="side-card">
                  <h3>Customer Context</h3>
                  <div id="contextGrid" class="context-grid">
                    <div class="context-item"><span>Buyer</span><strong>--</strong></div>
                    <div class="context-item"><span>Latest Order</span><strong>--</strong></div>
                    <div class="context-item"><span>Order Status</span><strong>--</strong></div>
                    <div class="context-item"><span>Chat Messages</span><strong>--</strong></div>
                    <div class="context-item"><span>Contact</span><strong>--</strong></div>
                    <div class="context-item"><span>Last Activity</span><strong>--</strong></div>
                  </div>
                </section>
              </div>
            </section>
            <section class="drawer-panel" id="drawerPanelNotes" data-drawer-panel="notes" role="tabpanel" aria-labelledby="tabBtnNotes">
              <div class="thread-side-panels">
                <section class="side-card">
                  <h3>Internal Note Timeline</h3>
                  <div id="noteHistory" class="note-history">
                    <div class="empty">No internal notes yet.</div>
                  </div>
                </section>
              </div>
            </section>
          </div>
        </aside>
      </div>
    </section>
  </main>
</div>
<div id="toastStack" class="toast-stack" aria-live="polite" aria-atomic="true"></div>
<div id="imageLightbox" class="lightbox" aria-hidden="true">
  <div class="lightbox-inner">
    <button type="button" id="lightboxCloseBtn" class="lightbox-close" aria-label="Close image preview">&times;</button>
    <img id="lightboxImage" src="" alt="Attachment preview">
    <div id="lightboxCaption" class="lightbox-caption"></div>
  </div>
</div>

<script>
(function () {
  var api = 'live_chat_api.php';
  var csrfToken = <?php echo json_encode($csrf_token); ?>;
  var threadList = document.getElementById('threadList');
  var chatHead = document.getElementById('chatHead');
  var chatSub = document.getElementById('chatSub');
  var chatShell = document.getElementById('chatShell');
  var adminDrawer = document.getElementById('adminDrawer');
  var drawerBackdrop = document.getElementById('drawerBackdrop');
  var drawerToggleBtn = document.getElementById('drawerToggleBtn');
  var drawerCloseBtn = document.getElementById('drawerCloseBtn');
  var presenceBar = document.getElementById('presenceBar');
  var chatFeed = document.getElementById('chatFeed');
  var jumpUnreadBtn = document.getElementById('jumpUnreadBtn');
  var jumpLatestBtn = document.getElementById('jumpLatestBtn');
  var jumpHint = document.getElementById('jumpHint');
  var chatForm = document.getElementById('chatForm');
  var chatInput = document.getElementById('chatInput');
  var chatAttachment = document.getElementById('chatAttachment');
  var attachmentPreview = document.getElementById('attachmentPreview');
  var attachmentPreviewCard = document.getElementById('attachmentPreviewCard');
  var attachmentThumb = document.getElementById('attachmentThumb');
  var attachmentName = document.getElementById('attachmentName');
  var attachmentType = document.getElementById('attachmentType');
  var clearAttachmentBtn = document.getElementById('clearAttachmentBtn');
  var cannedReplySelect = document.getElementById('cannedReplySelect');
  var sendBtn = document.getElementById('sendBtn');
  var charCount = document.getElementById('charCount');
  var notifyBtn = document.getElementById('notifyBtn');
  var alertStatus = document.getElementById('alertStatus');
  var threadSearch = document.getElementById('threadSearch');
  var unreadOnly = document.getElementById('unreadOnly');
  var statusFilter = document.getElementById('statusFilter');
  var assignedFilter = document.getElementById('assignedFilter');
  var statOpen = document.getElementById('statOpen');
  var statPending = document.getElementById('statPending');
  var statResolved = document.getElementById('statResolved');
  var statEscalated = document.getElementById('statEscalated');
  var statUnassigned = document.getElementById('statUnassigned');
  var threadMetaSummary = document.getElementById('threadMetaSummary');
  var assignedAdmin = document.getElementById('assignedAdmin');
  var threadStatus = document.getElementById('threadStatus');
  var threadPriority = document.getElementById('threadPriority');
  var branchTag = document.getElementById('branchTag');
  var escalatedFlag = document.getElementById('escalatedFlag');
  var escalationReason = document.getElementById('escalationReason');
  var closeReason = document.getElementById('closeReason');
  var internalNote = document.getElementById('internalNote');
  var saveMetaBtn = document.getElementById('saveMetaBtn');
  var assignMeBtn = document.getElementById('assignMeBtn');
  var unassignBtn = document.getElementById('unassignBtn');
  var threadActionHint = document.getElementById('threadActionHint');
  var slaIndicator = document.getElementById('slaIndicator');
  var quickReplies = document.getElementById('quickReplies');
  var contextGrid = document.getElementById('contextGrid');
  var noteHistory = document.getElementById('noteHistory');
  var drawerSummaryTitle = document.getElementById('drawerSummaryTitle');
  var drawerSummarySub = document.getElementById('drawerSummarySub');
  var drawerSummaryChips = document.getElementById('drawerSummaryChips');
  var drawerTabs = Array.prototype.slice.call(document.querySelectorAll('[data-drawer-tab]'));
  var drawerPanels = Array.prototype.slice.call(document.querySelectorAll('[data-drawer-panel]'));
  var toastStack = document.getElementById('toastStack');
  var imageLightbox = document.getElementById('imageLightbox');
  var lightboxImage = document.getElementById('lightboxImage');
  var lightboxCaption = document.getElementById('lightboxCaption');
  var lightboxCloseBtn = document.getElementById('lightboxCloseBtn');

  var activeUserId = 0;
  var initialUserId = Number(new URLSearchParams(window.location.search).get('user_id')) || 0;
  var lastMessageId = 0;
  var threads = [];
  var admins = [];
  var threadFetchInFlight = false;
  var messageFetchInFlight = false;
  var metaSaveInFlight = false;
  var metaDirty = false;
  var activeThreadName = '';
  var activeThreadMeta = null;
  var activeContext = null;
  var activePresence = null;
  var selectedAttachment = null;
  var threadSearchDebounce = null;
  var typingTimer = null;
  var presenceHeartbeat = null;
  var notificationReady = false;
  var unreadSnapshot = {};
  var hasUnreadSnapshot = false;
  var attachmentPreviewUrl = '';
  var sendInFlight = false;
  var dragDepth = 0;

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

  function formatDateTime(raw) {
    var dt = parseDateTime(raw);
    if (!dt) return '--';
    return dt.toLocaleString([], { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
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

  function showToast(message, type) {
    if (!toastStack || !message) return;
    var toast = document.createElement('div');
    toast.className = 'toast' + (type ? ' ' + type : '');
    toast.textContent = String(message);
    toastStack.appendChild(toast);
    window.setTimeout(function () {
      if (toast.parentNode) toast.remove();
    }, 2800);
  }

  function openLightbox(src, caption) {
    if (!imageLightbox || !lightboxImage || !lightboxCaption || !src) return;
    lightboxImage.src = src;
    lightboxCaption.textContent = caption || 'Attachment preview';
    imageLightbox.classList.add('is-open');
    imageLightbox.setAttribute('aria-hidden', 'false');
  }

  function closeLightbox() {
    if (!imageLightbox || !lightboxImage || !lightboxCaption) return;
    imageLightbox.classList.remove('is-open');
    imageLightbox.setAttribute('aria-hidden', 'true');
    lightboxImage.src = '';
    lightboxCaption.textContent = '';
  }

  function renderChatEmptyState(title, message) {
    if (!chatFeed) return;
    chatFeed.classList.add('is-empty');
    chatFeed.innerHTML = '<div class="chat-empty-state"><strong>' + escapeHtml(title) + '</strong><span>' + escapeHtml(message) + '</span></div>';
  }

  function isGroupedMessage(prevMsg, nextMsg) {
    if (!prevMsg || !nextMsg) return false;
    if (String(prevMsg.role || '') !== String(nextMsg.role || '')) return false;
    var prevTime = parseDateTime(prevMsg.created_at);
    var nextTime = parseDateTime(nextMsg.created_at);
    if (!prevTime || !nextTime) return false;
    return Math.abs(nextTime.getTime() - prevTime.getTime()) <= 5 * 60 * 1000;
  }

  function autoresizeComposer() {
    if (!chatInput) return;
    chatInput.style.height = 'auto';
    var next = Math.min(Math.max(chatInput.scrollHeight, 42), 132);
    chatInput.style.height = next + 'px';
  }

  function setSendingState(isSending) {
    sendInFlight = !!isSending;
    if (chatForm) chatForm.classList.toggle('is-sending', sendInFlight);
    if (sendBtn) {
      sendBtn.disabled = sendInFlight || (!activeUserId || (!chatInput.value.trim() && !selectedAttachment));
      sendBtn.innerHTML = sendInFlight ? "<i class='bx bx-loader-alt bx-spin'></i>" : "<i class='bx bx-send'></i>";
    }
    if (attachmentPreviewCard) {
      attachmentPreviewCard.classList.toggle('is-sending', sendInFlight && !!selectedAttachment);
    }
    if (attachmentType && selectedAttachment) {
      attachmentType.textContent = sendInFlight
        ? 'Uploading attachment...'
        : ((selectedAttachment.type ? selectedAttachment.type : 'File') + ' - ' + formatFileSize(selectedAttachment.size));
    }
  }

  function updateJumpControls() {
    if (!jumpUnreadBtn || !jumpLatestBtn || !jumpHint) return;
    if (!activeUserId) {
      jumpUnreadBtn.disabled = true;
      jumpLatestBtn.disabled = true;
      jumpHint.textContent = 'Select a thread to jump between unread and latest messages.';
      return;
    }
    var firstUnread = chatFeed.querySelector('.msg.user[data-unread="1"]');
    var hasMessages = !!chatFeed.querySelector('.msg:not(.typing)');
    jumpUnreadBtn.disabled = !firstUnread;
    jumpLatestBtn.disabled = !hasMessages;
    jumpHint.textContent = firstUnread
      ? 'Unread buyer messages are highlighted for quick review.'
      : (hasMessages ? 'You are caught up in this thread.' : 'No messages in this thread yet.');
  }

  function scrollToMessage(selector) {
    if (!chatFeed) return;
    var node = chatFeed.querySelector(selector);
    if (!node) return;
    node.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  function updateDrawerSummary(meta, context) {
    if (!drawerSummaryTitle || !drawerSummarySub || !drawerSummaryChips) return;
    if (!activeUserId) {
      drawerSummaryTitle.textContent = 'No thread selected';
      drawerSummarySub.textContent = 'Open a conversation to view admin controls, buyer context, and note history.';
      drawerSummaryChips.innerHTML = '';
      return;
    }
    drawerSummaryTitle.textContent = activeThreadName ? ('Chat with ' + activeThreadName) : ('Thread #' + activeUserId);
    var detail = [];
    if (meta && meta.assigned_admin_name) {
      detail.push('Assigned to ' + meta.assigned_admin_name);
    } else {
      detail.push('Unassigned thread');
    }
    if (context && context.latest_order && context.latest_order.id) {
      detail.push('Latest order #' + Number(context.latest_order.id || 0));
    }
    drawerSummarySub.textContent = detail.join(' | ') || 'Admin thread tools';
    var chips = [];
    if (meta) {
      chips.push('<span class="tag st-' + escapeHtml(String(meta.status || 'open')) + '">' + escapeHtml(readableStatus(String(meta.status || 'open'))) + '</span>');
      chips.push('<span class="tag p-' + escapeHtml(String(meta.priority || 'normal')) + '">' + escapeHtml(readablePriority(String(meta.priority || 'normal'))) + '</span>');
      if (meta.branch_tag) chips.push('<span class="tag context">' + escapeHtml(String(meta.branch_tag)) + '</span>');
      if (Number(meta.escalated || 0) === 1) chips.push('<span class="tag">Escalated</span>');
    }
    if (context && context.chat_message_count) {
      chips.push('<span class="tag context">' + Number(context.chat_message_count) + ' messages</span>');
    }
    drawerSummaryChips.innerHTML = chips.join('');
  }

  function confirmDiscardMetaChanges(targetLabel) {
    if (!metaDirty) return true;
    return window.confirm('You have unsaved thread detail changes. Continue to ' + targetLabel + ' and discard them?');
  }

  function resetAttachmentPreviewCard() {
    if (attachmentPreviewUrl) {
      URL.revokeObjectURL(attachmentPreviewUrl);
      attachmentPreviewUrl = '';
    }
    if (!attachmentPreviewCard || !attachmentThumb || !attachmentName || !attachmentType) return;
    attachmentPreviewCard.classList.remove('is-visible');
    attachmentPreviewCard.classList.remove('is-sending');
    attachmentThumb.innerHTML = "<i class='bx bx-image-alt'></i>";
    attachmentName.textContent = 'No attachment selected';
    attachmentType.textContent = 'Images, PDF, or TXT up to 5MB';
  }

  function setAttachmentFile(file) {
    if (!chatAttachment) return;
    if (!file) {
      chatAttachment.value = '';
      updateAttachmentPreview();
      updateComposerState();
      return;
    }
    try {
      var dt = new DataTransfer();
      dt.items.add(file);
      chatAttachment.files = dt.files;
    } catch (e) {
      return;
    }
    updateAttachmentPreview();
    updateComposerState();
  }

  function renderPresence(presence) {
    activePresence = presence || null;
    if (!presenceBar) return;
    var chips = [];
    if (!activeUserId) {
      presenceBar.innerHTML = '';
      return;
    }
    if (presence && presence.buyer_typing) chips.push('<span class="presence-chip">Buyer is typing...</span>');
    if (presence && presence.buyer_viewing) chips.push('<span class="presence-chip">Buyer is viewing this thread</span>');
    if (presence && Array.isArray(presence.active_admins)) {
      presence.active_admins.forEach(function (admin) {
        if (Number(admin.id || 0) === Number(<?php echo json_encode((int)$_SESSION['user_id']); ?>)) return;
        if (admin.is_typing || admin.is_viewing) {
          chips.push('<span class="presence-chip">' + escapeHtml(String(admin.name || 'Admin')) + (admin.is_typing ? ' is typing' : ' is viewing') + '</span>');
        }
      });
    }
    presenceBar.innerHTML = chips.join('');
    syncTypingBubble();
  }

  function ensureTypingBubble(role) {
    var existing = chatFeed.querySelector('.msg.typing.' + role);
    if (existing) return existing;
    var div = document.createElement('div');
    div.className = 'msg typing ' + role;
    var bubble = document.createElement('div');
    bubble.className = 'msg-bubble';
    bubble.innerHTML = '<span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span>';
    div.appendChild(bubble);
    chatFeed.appendChild(div);
    chatFeed.scrollTop = chatFeed.scrollHeight;
    return div;
  }

  function syncTypingBubble() {
    if (!chatFeed) return;
    var buyerTyping = !!(activePresence && activePresence.buyer_typing && activeUserId);

    var buyerNode = chatFeed.querySelector('.msg.typing.user');
    if (buyerTyping) {
      ensureTypingBubble('user');
    } else if (buyerNode) {
      buyerNode.remove();
    }
  }

  function renderContext(context) {
    activeContext = context || null;
    if (!contextGrid) return;
    if (!context) {
      contextGrid.innerHTML = '<div class="context-item"><span>Buyer</span><strong>--</strong></div>';
      updateDrawerSummary(activeThreadMeta, null);
      return;
    }
    var latestOrder = context.latest_order || null;
    var orderLink = latestOrder
      ? '<a href="orders_admin.php" title="Open orders list">#' + Number(latestOrder.id || 0) + '</a>'
      : '<strong>None</strong>';
    contextGrid.innerHTML = [
      '<div class="context-item"><span>Buyer</span><strong>' + escapeHtml(String(context.username || '--')) + '</strong></div>',
      '<div class="context-item"><span>Latest Order</span>' + orderLink + '</div>',
      '<div class="context-item"><span>Order Status</span><strong>' + escapeHtml(String(latestOrder && latestOrder.status ? latestOrder.status : '--')) + '</strong></div>',
      '<div class="context-item"><span>Chat Messages</span><strong>' + Number(context.chat_message_count || 0) + '</strong></div>',
      '<div class="context-item"><span>Contact</span><strong>' + escapeHtml(String(context.contact_number || context.email || '--')) + '</strong></div>',
      '<div class="context-item"><span>Last Activity</span><strong>' + escapeHtml(formatDateTime(context.last_activity_at)) + '</strong></div>'
    ].join('');
    updateDrawerSummary(activeThreadMeta, context);
  }

  function renderNotes(notes) {
    if (!noteHistory) return;
    if (!Array.isArray(notes) || !notes.length) {
      noteHistory.innerHTML = '<div class="empty">No internal notes yet.</div>';
      return;
    }
    noteHistory.innerHTML = notes.map(function (note) {
      return '<article class="note-entry">' +
        '<strong>' + escapeHtml(String(note.admin_name || 'Admin')) + '</strong>' +
        '<span>' + escapeHtml(formatDateTime(note.created_at)) + '</span>' +
        '<p>' + escapeHtml(String(note.note || '')) + '</p>' +
      '</article>';
    }).join('');
  }

  function setThreadActionHint(thread) {
    if (!threadActionHint) return;
    if (!thread) {
      threadActionHint.textContent = 'Select a thread to manage assignment and status.';
      return;
    }
    if (Number(thread.assigned_admin_id || 0) === Number(<?php echo json_encode((int)$_SESSION['user_id']); ?>)) {
      threadActionHint.textContent = 'This thread is currently assigned to you.';
    } else if (Number(thread.assigned_admin_id || 0) > 0) {
      threadActionHint.textContent = 'Assigned to ' + String(thread.assigned_admin_name || 'another admin') + '.';
    } else {
      threadActionHint.textContent = 'This thread is currently unassigned.';
    }
  }

  function updateAttachmentPreview() {
    selectedAttachment = chatAttachment && chatAttachment.files && chatAttachment.files[0] ? chatAttachment.files[0] : null;
    if (!attachmentPreview || !clearAttachmentBtn) return;
    if (!selectedAttachment) {
      attachmentPreview.textContent = 'Send image or file';
      clearAttachmentBtn.hidden = true;
      resetAttachmentPreviewCard();
      return;
    }
    attachmentPreview.textContent = selectedAttachment.name + ' (' + formatFileSize(selectedAttachment.size) + ')';
    clearAttachmentBtn.hidden = false;
    if (attachmentPreviewCard && attachmentThumb && attachmentName && attachmentType) {
      attachmentPreviewCard.classList.add('is-visible');
      attachmentName.textContent = selectedAttachment.name;
      attachmentType.textContent = (selectedAttachment.type ? selectedAttachment.type : 'File') + ' - ' + formatFileSize(selectedAttachment.size);
      if (attachmentPreviewUrl) {
        URL.revokeObjectURL(attachmentPreviewUrl);
        attachmentPreviewUrl = '';
      }
      if (isImageFile(selectedAttachment)) {
        attachmentPreviewUrl = URL.createObjectURL(selectedAttachment);
        attachmentThumb.innerHTML = '<img src="' + attachmentPreviewUrl + '" alt="Selected image preview">';
      } else {
        attachmentThumb.innerHTML = "<i class='bx bx-file'></i>";
      }
    }
  }

  function renderAttachment(container, attachment) {
    if (!attachment || !attachment.url) return;
    var wrap = document.createElement('div');
    wrap.className = 'msg-attachment';
    var isImage = String(attachment.mime || '').indexOf('image/') === 0;
    if (isImage) {
      var link = document.createElement('a');
      link.href = attachment.url;
      link.target = '_blank';
      link.rel = 'noopener';
      var img = document.createElement('img');
      img.src = attachment.url;
      img.alt = attachment.name || 'Chat attachment';
      link.appendChild(img);
      wrap.appendChild(link);
    }
    var fileLink = document.createElement('a');
    fileLink.href = attachment.url;
    fileLink.target = '_blank';
    fileLink.rel = 'noopener';
    fileLink.textContent = attachment.name || 'Open attachment';
    wrap.appendChild(fileLink);
    if (attachment.size) {
      var size = document.createElement('span');
      size.textContent = formatFileSize(attachment.size);
      wrap.appendChild(size);
    }
    container.appendChild(wrap);
  }

  function buildMetaSummary(meta) {
    if (!meta) return 'Select a thread to view admin controls.';
    var parts = [];
    var assignedName = String(meta.assigned_admin_name || '').trim();
    parts.push(assignedName ? ('Assigned to ' + assignedName) : 'Unassigned');
    parts.push('Status: ' + readableStatus(String(meta.status || 'open')));
    parts.push('Priority: ' + readablePriority(String(meta.priority || 'normal')));
    if (meta.branch_tag) parts.push('Branch: ' + String(meta.branch_tag));
    if (Number(meta.escalated || 0) === 1) parts.push('Escalated');
    if (meta.close_reason) parts.push('Close reason: ' + String(meta.close_reason));
    return parts.join(' | ');
  }

  function buildMetaPayloadFromForm() {
    return {
      assigned_admin_id: Number(assignedAdmin.value || 0),
      assigned_admin_name: (function () {
        var opt = assignedAdmin.options[assignedAdmin.selectedIndex];
        if (!opt || String(opt.value || '0') === '0') return '';
        var label = String(opt.text || '').trim();
        return label.replace(/\s*\((admin|superadmin)\)\s*$/i, '');
      })(),
      status: String(threadStatus.value || 'open'),
      priority: String(threadPriority.value || 'normal'),
      branch_tag: String(branchTag.value || ''),
      escalated: escalatedFlag.checked ? 1 : 0,
      escalation_reason: String(escalationReason.value || ''),
      close_reason: String(closeReason.value || ''),
      internal_note: String(internalNote.value || '')
    };
  }

  function updateThreadMetaSummary(meta) {
    if (!threadMetaSummary) return;
    threadMetaSummary.textContent = buildMetaSummary(meta);
    updateDrawerSummary(meta, activeContext);
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
    if (t.close_reason) tags.push('<span class="tag context">' + escapeHtml(t.close_reason) + '</span>');
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

  function playAlertTone() {
    try {
      var AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (!AudioCtx) return;
      var ctx = new AudioCtx();
      var oscillator = ctx.createOscillator();
      var gain = ctx.createGain();
      oscillator.type = 'sine';
      oscillator.frequency.value = 880;
      gain.gain.value = 0.02;
      oscillator.connect(gain);
      gain.connect(ctx.destination);
      oscillator.start();
      window.setTimeout(function () {
        oscillator.stop();
        ctx.close();
      }, 140);
    } catch (e) {
    }
  }

  function updateNotifyButton() {
    if (!notifyBtn) return;
    var supported = 'Notification' in window;
    notificationReady = supported && Notification.permission === 'granted';
    notifyBtn.disabled = !supported;
    notifyBtn.classList.toggle('is-on', notificationReady);
    notifyBtn.classList.toggle('is-blocked', supported && Notification.permission === 'denied');
    if (!supported) {
      notifyBtn.textContent = 'Alerts Unsupported';
      if (alertStatus) {
        alertStatus.innerHTML = 'This browser does not support notifications.';
        alertStatus.classList.remove('is-blocked');
      }
      return;
    }
    if (Notification.permission === 'granted') {
      notifyBtn.textContent = 'Alerts On';
      if (alertStatus) {
        alertStatus.innerHTML = 'Browser alerts are enabled for new live chat messages.';
        alertStatus.classList.remove('is-blocked');
      }
      return;
    }
    if (Notification.permission === 'denied') {
      notifyBtn.textContent = 'Alerts Blocked';
      if (alertStatus) {
        alertStatus.innerHTML = 'Browser notifications are blocked for this site. Allow notifications in your browser site settings to enable alerts. <a href="https://support.google.com/chrome/answer/3220216" target="_blank" rel="noopener">How to enable notifications</a>';
        alertStatus.classList.add('is-blocked');
      }
      return;
    }
    notifyBtn.textContent = 'Enable Alerts';
    if (alertStatus) {
      alertStatus.innerHTML = 'Alerts are off. Enable browser notifications to get new message alerts. <a href="https://support.google.com/chrome/answer/3220216" target="_blank" rel="noopener">How to enable notifications</a>';
      alertStatus.classList.remove('is-blocked');
    }
  }

  function setDrawerOpen(isOpen) {
    if (!chatShell || !drawerToggleBtn || !adminDrawer) return;
    chatShell.classList.toggle('drawer-open', !!isOpen);
    adminDrawer.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    drawerToggleBtn.textContent = isOpen ? 'Hide Admin Tools' : 'Open Admin Tools';
  }

  function setDrawerTab(name) {
    drawerTabs.forEach(function (tab) {
      var active = tab.getAttribute('data-drawer-tab') === name;
      tab.classList.toggle('is-active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    drawerPanels.forEach(function (panel) {
      panel.classList.toggle('is-active', panel.getAttribute('data-drawer-panel') === name);
    });
  }

  function maybeNotifyUnread(nextThreads) {
    var nextSnapshot = {};
    var triggered = [];
    (nextThreads || []).forEach(function (thread) {
      var count = Number(thread.unread_count || 0);
      nextSnapshot[String(thread.user_id)] = count;
      if (hasUnreadSnapshot && count > Number(unreadSnapshot[String(thread.user_id)] || 0)) {
        triggered.push(thread);
      }
    });
    unreadSnapshot = nextSnapshot;
    if (!hasUnreadSnapshot) {
      hasUnreadSnapshot = true;
      return;
    }
    if (!triggered.length) return;
    playAlertTone();
    if (notificationReady && document.hidden) {
      var first = triggered[0];
      try {
        new Notification('New Live Chat Message', {
          body: String(first.username || 'Buyer') + ': ' + String(first.last_message || 'sent a new message'),
        });
      } catch (e) {
      }
    }
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
      updateDrawerSummary(null, null);
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
    updateDrawerSummary(activeThreadMeta || activeThread || null, activeContext);
  }

  function setMetaEnabled(enabled) {
    assignedAdmin.disabled = !enabled;
    threadStatus.disabled = !enabled;
    threadPriority.disabled = !enabled;
    branchTag.disabled = !enabled;
    escalatedFlag.disabled = !enabled;
    escalationReason.disabled = !enabled;
    closeReason.disabled = !enabled;
    internalNote.disabled = !enabled;
    saveMetaBtn.disabled = !enabled || metaSaveInFlight;
    assignMeBtn.disabled = !enabled;
    unassignBtn.disabled = !enabled;
  }

  function applyThreadMeta(meta, force) {
    if (typeof force === 'undefined') force = false;
    if (metaDirty && !force) {
      activeThreadMeta = meta || activeThreadMeta;
      updateDrawerSummary(activeThreadMeta, activeContext);
      return;
    }
    activeThreadMeta = meta || null;
    metaDirty = false;
    updateThreadMetaSummary(meta);
    if (!meta) {
      assignedAdmin.value = '0';
      threadStatus.value = 'open';
      threadPriority.value = 'normal';
      branchTag.value = '';
      escalatedFlag.checked = false;
      escalationReason.value = '';
      closeReason.value = '';
      internalNote.value = '';
      setMetaEnabled(false);
      setThreadActionHint(null);
      updateDrawerSummary(null, null);
      return;
    }
    assignedAdmin.value = String(Number(meta.assigned_admin_id || 0));
    threadStatus.value = String(meta.status || 'open');
    threadPriority.value = String(meta.priority || 'normal');
    branchTag.value = String(meta.branch_tag || '');
    escalatedFlag.checked = Number(meta.escalated || 0) === 1;
    escalationReason.value = String(meta.escalation_reason || '');
    closeReason.value = String(meta.close_reason || '');
    internalNote.value = String(meta.internal_note || '');
    setMetaEnabled(true);
    setThreadActionHint(meta);
    updateDrawerSummary(meta, activeContext);
  }

  function updateComposerState() {
    var len = (chatInput.value || '').length;
    if (charCount) {
      charCount.textContent = len + '/1000';
    }
    var enabled = !!activeUserId && chatInput.value.trim() !== '';
    sendBtn.disabled = sendInFlight || (!enabled && !selectedAttachment);
    chatInput.disabled = !activeUserId;
    if (chatAttachment) chatAttachment.disabled = !activeUserId;
    if (cannedReplySelect) cannedReplySelect.disabled = !activeUserId;
    if (!activeUserId) {
      setMetaEnabled(false);
      chatInput.placeholder = 'Select a thread first to reply.';
    } else {
      chatInput.placeholder = 'Reply to selected user...';
    }
    autoresizeComposer();
  }

  function appendMessage(msg) {
    syncTypingBubble();
    chatFeed.classList.remove('is-empty');
    var nearBottom = chatFeed.scrollHeight - chatFeed.scrollTop - chatFeed.clientHeight < 80;
    var previousNode = Array.prototype.slice.call(chatFeed.querySelectorAll('.msg:not(.typing)')).pop();
    var previousMsg = previousNode ? {
      role: previousNode.classList.contains('admin') ? 'admin' : 'user',
      created_at: previousNode.dataset.createdAt || ''
    } : null;
    var div = document.createElement('div');
    div.className = 'msg ' + (msg.role === 'admin' ? 'admin' : 'user');
    div.dataset.messageId = String(Number(msg.id) || 0);
    div.dataset.createdAt = String(msg.created_at || '');
    if (isGroupedMessage(previousMsg, msg)) {
      div.classList.add('grouped');
    }
    if (msg.role === 'user' && Number(msg.is_read || 0) === 0) {
      div.setAttribute('data-unread', '1');
    }

    var bubble = document.createElement('div');
    bubble.className = 'msg-bubble';

    var text = document.createElement('div');
    text.className = 'msg-text';
    text.textContent = msg.text || '';
    if (msg.text) {
      bubble.appendChild(text);
    }
    renderAttachment(bubble, msg.attachment || null);
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
    syncTypingBubble();
    updateJumpControls();
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

  async function updatePresence(options) {
    if (!activeUserId) return;
    try {
      var body = new URLSearchParams();
      body.set('action', 'update_presence');
      body.set('user_id', String(activeUserId));
      body.set('is_typing', options && options.is_typing ? '1' : '0');
      body.set('is_viewing', options && options.is_viewing ? '1' : '0');
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
      renderPresence(data.presence || null);
    } catch (e) {
    }
  }

  function scheduleTypingPresence() {
    if (!activeUserId) return;
    updatePresence({ is_typing: true, is_viewing: document.visibilityState === 'visible' });
    if (typingTimer) window.clearTimeout(typingTimer);
    typingTimer = window.setTimeout(function () {
      updatePresence({ is_typing: false, is_viewing: document.visibilityState === 'visible' });
    }, 1800);
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
        cannedReplySelect.innerHTML = '<option value="">Insert canned reply...</option>';
        return;
      }
      cannedReplySelect.innerHTML = ['<option value="">Insert canned reply...</option>'].concat(replies.map(function (r) {
        return '<option value="' + escapeHtml(String(r.body || '')) + '">' + escapeHtml(String((r.category || 'general') + ' - ' + (r.title || 'Reply')) ) + '</option>';
      })).join('');
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
      maybeNotifyUnread(threads);
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
          setThreadActionHint(activeThread);
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
      renderChatEmptyState('Loading conversation...', 'Pulling the latest messages and admin context for this thread.');
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
      renderNotes(data.notes || []);
      renderContext(data.context || null);
      renderPresence(data.presence || null);
      if (!messages.length && !chatFeed.querySelector('.msg:not(.typing)')) {
        renderChatEmptyState('No messages yet', 'This thread is ready for support, but there are no chat messages in it yet.');
      }
      await markRead();
      await fetchThreads();
      await fetchThreadStats();
      updateChatHead();
      updateJumpControls();
    } catch (e) {
    } finally {
      messageFetchInFlight = false;
    }
  }

  function selectThread(userId, username) {
    activeUserId = Number(userId) || 0;
    activeThreadName = username || '';
    var thread = threads.find(function (t) { return t.user_id === activeUserId; });
    applyThreadMeta(thread || null, true);
    renderContext(null);
    renderNotes([]);
    renderPresence(null);
    syncTypingBubble();
    renderThreads();
    updateChatHead();
    updateComposerState();
    updateJumpControls();
    updatePresence({ is_typing: false, is_viewing: document.visibilityState === 'visible' });
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
    if (nextUserId !== activeUserId && !confirmDiscardMetaChanges('switch threads')) return;
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
      showToast('Escalation reason is required before saving.', 'warn');
      return;
    }
    if ((threadStatus.value === 'resolved' || threadStatus.value === 'closed') && closeReason.value.trim() === '') {
      closeReason.focus();
      showToast('Close reason is required for resolved or closed threads.', 'warn');
      return;
    }
    var nextMeta = buildMetaPayloadFromForm();
    metaSaveInFlight = true;
    setMetaEnabled(true);
    try {
      var body = new URLSearchParams();
      body.set('action', 'update_thread_meta');
      body.set('user_id', String(activeUserId));
      body.set('assigned_admin_id', String(nextMeta.assigned_admin_id));
      body.set('status', nextMeta.status);
      body.set('priority', nextMeta.priority);
      body.set('branch_tag', nextMeta.branch_tag);
      body.set('escalated', nextMeta.escalated ? '1' : '0');
      body.set('escalation_reason', nextMeta.escalation_reason);
      body.set('close_reason', nextMeta.close_reason);
      body.set('internal_note', nextMeta.internal_note);
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
      metaDirty = false;
      activeThreadMeta = Object.assign({}, activeThreadMeta || {}, nextMeta);
      updateThreadMetaSummary(activeThreadMeta);
      renderNotes(data.notes || []);
      showToast('Thread details saved.', 'success');
      await fetchThreads();
      await fetchThreadStats();
      await fetchMessages(false);
    } catch (e) {
      showToast('Unable to save thread details right now.', 'error');
    } finally {
      metaSaveInFlight = false;
      setMetaEnabled(!!activeUserId);
    }
  }

  chatForm.addEventListener('submit', async function (e) {
    e.preventDefault();
    if (!activeUserId || sendInFlight) return;
    var text = chatInput.value.trim();
    if (!text && !selectedAttachment) return;
    var hadAttachment = !!selectedAttachment;
    try {
      setSendingState(true);
      var body = new FormData();
      body.set('action', 'send');
      body.set('user_id', String(activeUserId));
      body.set('message', text);
      body.set('csrf_token', csrfToken);
      if (selectedAttachment) {
        body.set('attachment', selectedAttachment);
      }
      var res = await fetch(api, {
        method: 'POST',
        body: body,
        credentials: 'same-origin'
      });
      if (!res.ok) return;
      var data = await res.json();
      if (!data.ok) return;
      chatInput.value = '';
      if (chatAttachment) chatAttachment.value = '';
      updateAttachmentPreview();
      updateComposerState();
      showToast(hadAttachment ? 'Reply and attachment sent.' : 'Reply sent.', 'success');
      fetchMessages(false);
    } catch (e) {
      showToast('Unable to send reply right now.', 'error');
    } finally {
      setSendingState(false);
    }
  });

  chatInput.addEventListener('input', updateComposerState);
  chatInput.addEventListener('paste', function (e) {
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
      showToast('Pasted image ready to send.', 'success');
      break;
    }
  });
  chatInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      if (!sendBtn.disabled) {
        chatForm.requestSubmit();
      }
    }
  });
  chatInput.addEventListener('input', scheduleTypingPresence);

  if (chatAttachment) {
    chatAttachment.addEventListener('change', function () {
      updateAttachmentPreview();
      updateComposerState();
      if (selectedAttachment) showToast('Attachment ready to send.', 'success');
    });
  }
  if (clearAttachmentBtn) {
    clearAttachmentBtn.addEventListener('click', function () {
      if (chatAttachment) chatAttachment.value = '';
      updateAttachmentPreview();
      updateComposerState();
    });
  }
  if (cannedReplySelect) {
    cannedReplySelect.addEventListener('change', function () {
      var text = String(cannedReplySelect.value || '').trim();
      if (!text) return;
      chatInput.value = chatInput.value.trim() ? (chatInput.value.trim() + '\n' + text) : text;
      cannedReplySelect.value = '';
      updateComposerState();
      chatInput.focus();
    });
  }

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

  if (assignMeBtn) {
    assignMeBtn.addEventListener('click', function () {
      if (!activeUserId) return;
      assignedAdmin.value = String(<?php echo json_encode((int)$_SESSION['user_id']); ?>);
      metaDirty = true;
      updateThreadMetaSummary(buildMetaPayloadFromForm());
      setThreadActionHint(buildMetaPayloadFromForm());
      showToast('Thread assigned to you.', 'success');
      saveThreadMeta();
    });
  }
  if (unassignBtn) {
    unassignBtn.addEventListener('click', function () {
      if (!activeUserId) return;
      assignedAdmin.value = '0';
      metaDirty = true;
      updateThreadMetaSummary(buildMetaPayloadFromForm());
      setThreadActionHint(buildMetaPayloadFromForm());
      showToast('Thread unassigned.', 'warn');
      saveThreadMeta();
    });
  }

  if (notifyBtn) {
    notifyBtn.addEventListener('click', async function () {
      if (!('Notification' in window)) return;
      if (Notification.permission === 'granted') {
        updateNotifyButton();
        return;
      }
      try {
        await Notification.requestPermission();
      } catch (e) {
      }
      updateNotifyButton();
    });
  }
  if (drawerToggleBtn) {
    drawerToggleBtn.addEventListener('click', function () {
      if (chatShell.classList.contains('drawer-open') && !confirmDiscardMetaChanges('close admin tools')) return;
      setDrawerOpen(!chatShell.classList.contains('drawer-open'));
    });
  }
  if (drawerCloseBtn) {
    drawerCloseBtn.addEventListener('click', function () {
      if (!confirmDiscardMetaChanges('close admin tools')) return;
      setDrawerOpen(false);
    });
  }
  if (drawerBackdrop) {
    drawerBackdrop.addEventListener('click', function () {
      if (!confirmDiscardMetaChanges('close admin tools')) return;
      setDrawerOpen(false);
    });
  }
  if (jumpUnreadBtn) {
    jumpUnreadBtn.addEventListener('click', function () {
      scrollToMessage('.msg.user[data-unread="1"]');
    });
  }
  if (jumpLatestBtn) {
    jumpLatestBtn.addEventListener('click', function () {
      chatFeed.scrollTo({ top: chatFeed.scrollHeight, behavior: 'smooth' });
    });
  }
  if (chatForm) {
    ['dragenter', 'dragover'].forEach(function (eventName) {
      chatForm.addEventListener(eventName, function (e) {
        e.preventDefault();
        if (!activeUserId) return;
        dragDepth += 1;
        chatForm.classList.add('is-dragover');
      });
    });
    ['dragleave', 'dragend', 'drop'].forEach(function (eventName) {
      chatForm.addEventListener(eventName, function (e) {
        e.preventDefault();
        dragDepth = Math.max(0, dragDepth - 1);
        if (eventName === 'drop') dragDepth = 0;
        if (dragDepth === 0) chatForm.classList.remove('is-dragover');
      });
    });
    chatForm.addEventListener('drop', function (e) {
      if (!activeUserId || !e.dataTransfer || !e.dataTransfer.files || !e.dataTransfer.files.length) return;
      var file = e.dataTransfer.files[0];
      setAttachmentFile(file);
      showToast('Dropped attachment ready to send.', 'success');
    });
  }
  if (chatFeed) {
    chatFeed.addEventListener('click', function (e) {
      var image = e.target.closest('.msg-attachment img');
      if (!image) return;
      e.preventDefault();
      openLightbox(image.getAttribute('src') || '', image.getAttribute('alt') || 'Chat attachment');
    });
  }
  if (lightboxCloseBtn) {
    lightboxCloseBtn.addEventListener('click', closeLightbox);
  }
  if (imageLightbox) {
    imageLightbox.addEventListener('click', function (e) {
      if (e.target === imageLightbox) closeLightbox();
    });
  }
  drawerTabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      setDrawerTab(tab.getAttribute('data-drawer-tab') || 'details');
    });
  });

  saveMetaBtn.addEventListener('click', saveThreadMeta);
  [assignedAdmin, threadStatus, threadPriority, branchTag, escalatedFlag, escalationReason, closeReason, internalNote].forEach(function (el) {
    if (!el) return;
    el.addEventListener('change', function () {
      metaDirty = !!activeUserId;
      setMetaEnabled(!!activeUserId);
      if (metaDirty) updateThreadMetaSummary(buildMetaPayloadFromForm());
    });
    el.addEventListener('input', function () {
      metaDirty = !!activeUserId;
      setMetaEnabled(!!activeUserId);
      if (metaDirty) updateThreadMetaSummary(buildMetaPayloadFromForm());
    });
  });

  document.addEventListener('visibilitychange', function () {
    if (!activeUserId) return;
    updatePresence({ is_typing: false, is_viewing: document.visibilityState === 'visible' });
  });
  window.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && imageLightbox && imageLightbox.classList.contains('is-open')) {
      closeLightbox();
    }
  });
  window.addEventListener('beforeunload', function (e) {
    if (!metaDirty) return;
    e.preventDefault();
    e.returnValue = '';
  });

  setDrawerOpen(false);
  setDrawerTab('details');
  updateNotifyButton();
  updateAttachmentPreview();
  renderChatEmptyState('Select a conversation', 'Choose a live chat thread from the left to start reading messages, replying, or managing admin details.');
  updateJumpControls();
  fetchAdmins();
  fetchCannedReplies();
  fetchThreadStats();
  updateComposerState();
  applyThreadMeta(null, true);
  fetchThreads();
  setInterval(fetchThreadStats, 8000);
  setInterval(fetchThreads, 4000);
  setInterval(function () { fetchMessages(false); }, 2200);
  presenceHeartbeat = setInterval(function () {
    updatePresence({ is_typing: false, is_viewing: document.visibilityState === 'visible' && !!activeUserId });
  }, 10000);
})();
</script>
</body>
</html>






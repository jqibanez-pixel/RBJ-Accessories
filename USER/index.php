<?php
session_start();
if (isset($_SESSION['user_id']) && in_array(($_SESSION['role'] ?? ''), ['admin', 'superadmin'], true)) {
    header("Location: ../ADMIN/dashboard_admin.php");
    exit();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
include '../config.php';
$is_logged_in = isset($_SESSION['user_id']);
$username = $is_logged_in ? ($_SESSION['username'] ?? 'User') : '';
$user_initial = $is_logged_in ? strtoupper(substr($username, 0, 1)) : '';
$is_ajax_feedback = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Get unread notification count
$unread_count = 0;
$cart_count = 0;
if ($is_logged_in) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $unread_count = $row['unread_count'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT COALESCE(SUM(quantity), 0) as total FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart_row = $stmt->get_result()->fetch_assoc();
    $cart_count = (int)($cart_row['total'] ?? 0);
    $stmt->close();
}

// Handle feedback submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_feedback'])) {
    $posted_token = $_POST['csrf_token'] ?? '';

    $feedback = trim($_POST['feedback']);
    $rating = (int)$_POST['rating'];

    $errors = [];

    // Validate input
    if (empty($feedback)) {
        $errors[] = "Please enter your feedback";
    }

    if ($rating < 1 || $rating > 5) {
        $errors[] = "Please select a valid rating";
    }

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        $errors[] = "You must be logged in to submit feedback";
    }
    if (!hash_equals($csrf_token, $posted_token)) {
        $errors[] = "Invalid request token";
    }

    if (empty($errors)) {
        $user_id = $_SESSION['user_id'];

        // Insert feedback into database with 'submitted' status
        $stmt = $conn->prepare("INSERT INTO feedback (user_id, feedback, rating, status) VALUES (?, ?, ?, 'submitted')");
        $stmt->bind_param("isi", $user_id, $feedback, $rating);

        if ($stmt->execute()) {
            $success = "Thank you for your feedback! It will be reviewed by our team before being published.";
        } else {
            $errors[] = "Failed to submit feedback. Please try again.";
        }

        $stmt->close();
    }

    if ($is_ajax_feedback) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'ok' => empty($errors),
            'message' => empty($errors)
                ? $success
                : implode(' ', $errors),
            'errors' => $errors
        ]);
        $conn->close();
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>RBJ Accessories - Home</title>
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <style>
    /* ======= RESET & GENERAL ======= */
    html {
      scroll-behavior: smooth;
      scroll-padding-top: 110px;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: "Montserrat", sans-serif; }
    body { font-family: "Montserrat", sans-serif; min-height: 100vh; background: var(--rbj-bg, linear-gradient(145deg,#0a0a0a,#111111)); color: var(--rbj-text, #fff); padding-top:100px; }
    body.home { display:flex; flex-direction:column; min-height:100svh; padding:0; margin:0; overflow-x:hidden; }

    /* ======= NAVBAR ======= */
    .navbar {
      position: fixed; top:0; left:0; right:0;
      display:flex; justify-content:space-between; align-items:center;
      padding:10px 50px; background: rgba(0,0,0,0.8); z-index:999;
    }
    .navbar .logo { display:flex; align-items:center; gap:10px; color:white; text-decoration:none; font-size:22px; font-weight:700; }
    .navbar .logo img { height:60px; width:auto; background: transparent; }
    .navbar .nav-links { display:flex; align-items:center; gap:15px; }
    .navbar .nav-links a { color:white; text-decoration:none; font-weight:500; margin-left:15px; }
    .navbar .nav-links a:hover { text-decoration:underline; }
    .navbar .nav-links a.active-section {
      color: #ff9f97;
      text-decoration: underline;
      text-underline-offset: 4px;
    }

    /* ======= ACCOUNT DROPDOWN ======= */
    .account-dropdown { position:relative; display:flex; align-items:center; cursor:pointer; margin-left:15px; }
    .account-icon { width:40px; height:40px; background:#27ae60; border-radius:50%; display:flex; justify-content:center; align-items:center; font-weight:bold; }
    .account-username { font-weight:600; margin-left:5px; color:white; }
    .dropdown-menu { display:none; position:absolute; top:55px; right:0; background:#222; border-radius:10px; min-width:180px; box-shadow:0 5px 15px rgba(0,0,0,0.5); z-index:1000; }
    .dropdown-menu a { display:block; padding:12px 20px; text-decoration:none; color:white; font-weight:500; }
    .dropdown-menu a:hover { background:#27ae60; color:white; }
/* Account Dropdown */
.account-dropdown {
  position: relative;
}

.account-trigger {
  background: none;
  border: none;
  color: white;
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}

.account-trigger i {
  font-size: 18px;
}

.account-menu {
  position: absolute;
  top: 110%;
  right: 0;
  background: #1e1e1e;
  border-radius: 10px;
  min-width: 200px;
  padding: 8px 0;
  display: none;
  box-shadow: 0 10px 30px rgba(0,0,0,0.4);
  z-index: 999;
}

.account-menu a {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 15px;
  color: white;
  text-decoration: none;
  font-size: 14px;
}

.account-menu a:hover {
  background: rgba(255,255,255,0.08);
}

.account-menu i {
  font-size: 18px;
}

/* Notification Badge */
.notification-badge {
  position: absolute;
  top: -5px;
  right: -5px;
  background: #ef233c;
  color: white;
  border-radius: 50%;
  width: 18px;
  height: 18px;
  font-size: 11px;
  font-weight: bold;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 10;
}

.cart-count {
  background: #ef233c;
  color: white;
  border-radius: 50%;
  width: 20px;
  height: 20px;
  font-size: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-left: 5px;
}

/* Show menu */
.account-dropdown.active .account-menu {
  display: block;
}
/* === FIX: allow dropdown links to be clickable === */
.account-menu {
  pointer-events: auto;
  z-index: 9999;
}

.account-trigger {
  pointer-events: auto;
}

    /* ======= LANDING ======= */
    .hero {
      position: relative;
      display: grid;
      grid-template-columns: 1.1fr 0.9fr;
      gap: 28px;
      align-items: center;
      padding: 130px 50px 70px;
      background:
        radial-gradient(circle at 12% 18%, rgba(229,9,20,0.2), transparent 36%),
        radial-gradient(circle at 84% 12%, rgba(255,31,45,0.14), transparent 30%),
        linear-gradient(160deg, #131313 0%, #090909 100%);
      border-bottom: 1px solid rgba(255,255,255,0.08);
      overflow: hidden;
    }
    .hero::before {
      content: "";
      position: absolute;
      inset: auto -140px -140px auto;
      width: 360px;
      height: 360px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(229,9,20,0.26), transparent 70%);
      pointer-events: none;
    }
    .hero-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(255,255,255,0.08);
      border: 1px solid rgba(255,255,255,0.14);
      color: #f3f3f3;
      padding: 8px 12px;
      border-radius: 999px;
      font-size: 12px;
      letter-spacing: 0.3px;
      margin-bottom: 14px;
    }
    .hero-text h1 {
      font-size: clamp(34px, 5vw, 56px);
      line-height: 1.04;
      margin-bottom: 12px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .hero-text h2 {
      font-size: clamp(17px, 2.2vw, 22px);
      color: #d7d7d7;
      margin-bottom: 14px;
      font-weight: 500;
    }
    .hero-text p {
      font-size: 17px;
      color: #c8c8c8;
      margin-bottom: 24px;
      max-width: 640px;
      line-height: 1.65;
    }
    .hero-cta {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
    }
    .btn-primary, .btn-secondary {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
      font-weight: 700;
      border-radius: 999px;
      padding: 12px 22px;
      transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
    }
    .btn-primary {
      background: linear-gradient(135deg, #e50914, #b80610);
      color: #fff;
      box-shadow: 0 10px 30px rgba(229,9,20,0.3);
    }
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 14px 34px rgba(229,9,20,0.38);
    }
    .btn-secondary {
      color: #fff;
      border: 1px solid rgba(255,255,255,0.24);
      background: rgba(255,255,255,0.04);
    }
    .btn-secondary:hover {
      transform: translateY(-2px);
      background: rgba(255,255,255,0.09);
    }
    .hero-image {
      min-height: 380px;
      border-radius: 20px;
      border: 1px solid rgba(255,255,255,0.15);
      background:
        linear-gradient(180deg, rgba(255,255,255,0.1), rgba(255,255,255,0.02)),
        #171717;
      box-shadow: 0 16px 36px rgba(0,0,0,0.45);
      display:flex;
      justify-content:center;
      align-items:center;
      padding: 16px;
    }
    .hero-image img {
      width: 100%;
      max-height: 340px;
      object-fit: cover;
      border-radius: 14px;
    }
    .trust-strip {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 14px;
      padding: 20px 50px;
      background: #111;
      border-top: 1px solid rgba(255,255,255,0.06);
      border-bottom: 1px solid rgba(255,255,255,0.06);
    }
    .trust-item {
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 12px;
      padding: 14px 16px;
      display: flex;
      gap: 12px;
      align-items: center;
    }
    .trust-item i {
      font-size: 24px;
      color: #e50914;
    }
    .trust-item h3 {
      font-size: 14px;
      margin-bottom: 2px;
    }
    .trust-item p {
      font-size: 12px;
      color: #bdbdbd;
    }

    /* ======= SHOWCASE ======= */
    .past-covers { padding:52px 20px; background:#0f0f0f; }
    .section-title { margin: 0 auto 18px; max-width: 1200px; padding: 0 12px; }
    .section-title h2 { font-size: 30px; margin-bottom: 6px; }
    .section-title p { color: #c2c2c2; }
    .cover-carousel {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 12px;
      position: relative;
      perspective: 1000px;
    }
    .cover-frame {
      position: relative;
      height: 430px;
      border-radius: 20px;
      overflow: hidden;
      background:
        radial-gradient(circle at 25% 18%, rgba(217,4,41,0.22), transparent 45%),
        radial-gradient(circle at 82% 18%, rgba(255,255,255,0.07), transparent 38%),
        linear-gradient(180deg, #151515 0%, #0f0f0f 100%);
      border: 1px solid rgba(255,255,255,0.1);
      box-shadow: inset 0 1px 0 rgba(255,255,255,0.08), 0 24px 48px rgba(0,0,0,0.45);
    }
    .cover-track {
      position: relative;
      width: 100%;
      height: 100%;
    }
    .cover-controls {
      position: absolute;
      top: 14px;
      right: 14px;
      display: flex;
      gap: 8px;
      z-index: 12;
    }
    .cover-controls button {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      border: 1px solid rgba(255,255,255,0.2);
      color: #fff;
      background: rgba(0,0,0,0.5);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      transition: transform 0.2s ease, background 0.2s ease, border-color 0.2s ease;
    }
    .cover-controls button:hover {
      transform: translateY(-2px);
      background: rgba(217,4,41,0.76);
      border-color: rgba(217,4,41,0.9);
    }
    .cover-card {
      position: absolute;
      top: 50%;
      left: 50%;
      width: min(420px, 72vw);
      transform-origin: center center;
      background:rgba(255,255,255,0.05);
      padding:10px;
      border-radius:16px;
      border: 1px solid rgba(255,255,255,0.08);
      text-align:center;
      transition: transform 0.55s cubic-bezier(.23,.84,.32,1), opacity 0.45s ease, box-shadow 0.35s ease, border-color 0.35s ease, filter 0.35s ease;
      overflow: hidden;
      cursor: pointer;
      opacity: 0;
      pointer-events: none;
      box-shadow: 0 16px 34px rgba(0,0,0,0.38);
    }
    .cover-card img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center;
      border-radius:12px;
      transition: transform 0.5s ease;
    }
    .cover-media {
      position: relative;
      height: 270px;
      border-radius: 12px;
      overflow: hidden;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #151515;
    }
    .cover-media img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center;
      border-radius: 12px;
      transition: transform 0.5s ease;
    }
    .cover-card .cover-caption {
      margin-top: 10px;
      display: block;
      font-size: 13px;
      color: #d6d6d6;
      letter-spacing: .2px;
    }
    .cover-card.is-center {
      opacity: 1;
      pointer-events: auto;
      transform: translate(-50%, -50%) scale(1.08) rotateY(0deg);
      z-index: 8;
      border-color: rgba(217,4,41,0.86);
      box-shadow: 0 0 0 2px rgba(217,4,41,0.22), 0 24px 40px rgba(0,0,0,0.5);
      filter: saturate(1.06);
    }
    .cover-card.is-center img {
      transform: scale(1.08);
    }
    .cover-card.is-left,
    .cover-card.is-right {
      opacity: 0.74;
      pointer-events: auto;
      z-index: 4;
      filter: saturate(0.82) brightness(0.88);
    }
    .cover-card.is-left {
      transform: translate(calc(-50% - 270px), -50%) scale(0.84) rotateY(17deg);
    }
    .cover-card.is-right {
      transform: translate(calc(-50% + 270px), -50%) scale(0.84) rotateY(-17deg);
    }
    .cover-card.is-hidden {
      opacity: 0;
      transform: translate(-50%, -50%) scale(0.7);
      z-index: 1;
      pointer-events: none;
    }
    .cover-card.is-left:hover,
    .cover-card.is-right:hover {
      opacity: 0.9;
      border-color: rgba(217,4,41,0.62);
    }
    .cover-lightbox {
      position: fixed;
      inset: 0;
      z-index: 1600;
      display: none;
      align-items: center;
      justify-content: center;
      background: rgba(15, 15, 15, 0.56);
      backdrop-filter: blur(3px);
      padding: 24px;
    }
    .cover-lightbox.show {
      display: flex;
    }
    .cover-lightbox-inner {
      width: auto;
      max-width: min(1000px, 95vw);
      max-height: 92vh;
      border-radius: 14px;
      overflow: visible;
      border: none;
      box-shadow: none;
      background: transparent;
      position: relative;
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    .cover-lightbox img {
      width: auto;
      height: auto;
      max-width: min(1000px, 95vw);
      max-height: 82vh;
      object-fit: contain;
      display: block;
      background: transparent;
      border-radius: 14px;
      border: 1px solid rgba(255,255,255,0.16);
      box-shadow: 0 24px 48px rgba(0,0,0,0.42);
    }
    .cover-lightbox-caption {
      width: min(1000px, 95vw);
      padding: 10px 14px;
      font-size: 13px;
      color: #d9d9d9;
      text-align: center;
    }
    .cover-lightbox-close {
      position: absolute;
      top: 10px;
      right: 10px;
      width: 34px;
      height: 34px;
      border-radius: 999px;
      border: 1px solid rgba(255,255,255,0.25);
      background: rgba(0,0,0,0.5);
      color: #fff;
      font-size: 22px;
      line-height: 30px;
      cursor: pointer;
    }
    .cover-status {
      position: absolute;
      left: 14px;
      top: 14px;
      z-index: 12;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 12px;
      color: #dfdfdf;
      background: rgba(0,0,0,0.44);
      border: 1px solid rgba(255,255,255,0.16);
      border-radius: 999px;
      padding: 8px 12px;
    }
    .cover-status i { color: #ff9f97; font-size: 15px; }
    .cover-dots {
      display: flex;
      justify-content: center;
      gap: 8px;
      margin-top: 16px;
      min-height: 16px;
    }
    .cover-dots button {
      width: 8px;
      height: 8px;
      border-radius: 999px;
      border: none;
      background: rgba(255,255,255,0.32);
      cursor: pointer;
      transition: width 0.25s ease, background 0.25s ease;
      padding: 0;
    }
    .cover-dots button.active {
      width: 24px;
      background: #ef233c;
    }

    /* ======= STORE LOCATOR ======= */
    .locator-section {
      padding: 50px 20px;
      background:
        radial-gradient(circle at 86% 18%, rgba(217,4,41,0.16), transparent 34%),
        #121212;
      border-top: 1px solid rgba(255,255,255,0.06);
      border-bottom: 1px solid rgba(255,255,255,0.06);
    }
    .locator-grid {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 12px;
      display: grid;
      grid-template-columns: 1.1fr 0.9fr;
      gap: 20px;
    }
    .locator-map-wrap,
    .locator-list {
      background: rgba(255,255,255,0.05);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 14px;
      overflow: hidden;
    }
    .locator-map-wrap {
      position: relative;
    }
    .locator-map-actions {
      position: absolute;
      top: 10px;
      right: 10px;
      z-index: 7;
    }
    .locator-map-expand-btn {
      border: 1px solid rgba(255,255,255,0.24);
      background: rgba(0,0,0,0.52);
      color: #fff;
      border-radius: 999px;
      padding: 8px 12px;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    .locator-map-expand-btn:hover {
      border-color: rgba(217,4,41,0.8);
      background: rgba(217,4,41,0.35);
    }
    .locator-map-wrap iframe {
      width: 100%;
      height: 360px;
      border: 0;
      display: block;
      pointer-events: auto;
    }
    .locator-map-link-blocker {
      position: absolute;
      left: 0;
      bottom: 0;
      width: 190px;
      height: 40px;
      z-index: 8;
      background: transparent;
      display: block;
    }
    .locator-list {
      padding: 18px;
    }
    .locator-list h3 {
      margin-bottom: 8px;
      font-size: 20px;
    }
    .locator-list > p {
      color: #c2c2c2;
      margin-bottom: 14px;
      font-size: 14px;
    }
    .branch-list {
      display: grid;
      gap: 10px;
    }
    .branch-item {
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 10px;
      padding: 12px;
      display: flex;
      gap: 10px;
      align-items: flex-start;
      justify-content: space-between;
      cursor: pointer;
      transition: border-color 0.2s ease, background 0.2s ease, transform 0.2s ease;
    }
    .branch-item:hover {
      border-color: rgba(217,4,41,0.65);
      background: rgba(255,255,255,0.07);
      transform: translateY(-1px);
    }
    .branch-item.is-active {
      border-color: rgba(217,4,41,0.9);
      background: rgba(217,4,41,0.14);
      box-shadow: 0 0 0 1px rgba(217,4,41,0.36) inset;
    }
    .branch-item i {
      color: #ef233c;
      font-size: 18px;
      margin-top: 1px;
    }
    .branch-item strong {
      display: block;
      font-size: 14px;
      margin-bottom: 2px;
    }
    .branch-item span {
      display: block;
      color: #bbbbbb;
      font-size: 13px;
    }
    .branch-main {
      display: flex;
      gap: 10px;
      align-items: flex-start;
      flex: 1;
      min-width: 0;
    }
    .branch-actions {
      display: flex;
      align-items: center;
    }
    .branch-direction-btn {
      border: 1px solid rgba(255,255,255,0.22);
      background: rgba(255,255,255,0.08);
      color: #f3f3f3;
      border-radius: 999px;
      padding: 7px 12px;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      white-space: nowrap;
      transition: background 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
    }
    .branch-direction-btn:hover {
      background: rgba(217,4,41,0.26);
      border-color: rgba(217,4,41,0.75);
      transform: translateY(-1px);
    }
    .branch-direction-btn:disabled {
      opacity: 0.65;
      cursor: wait;
      transform: none;
    }
    .locator-map-modal {
      position: fixed;
      inset: 0;
      z-index: 1750;
      display: none;
      align-items: center;
      justify-content: center;
      background: rgba(8,8,8,0.7);
      backdrop-filter: blur(3px);
      padding: 16px;
    }
    .locator-map-modal.show {
      display: flex;
    }
    .locator-map-modal-inner {
      width: min(1200px, 98vw);
      height: min(780px, 92vh);
      border-radius: 16px;
      border: 1px solid rgba(255,255,255,0.18);
      overflow: hidden;
      background: #101010;
      position: relative;
      box-shadow: 0 24px 48px rgba(0,0,0,0.46);
    }
    .locator-map-modal iframe {
      width: 100%;
      height: 100%;
      border: 0;
      display: block;
      pointer-events: auto;
    }
    .locator-map-modal-close {
      position: absolute;
      top: 10px;
      right: 10px;
      z-index: 10;
      width: 36px;
      height: 36px;
      border-radius: 999px;
      border: 1px solid rgba(255,255,255,0.25);
      background: rgba(0,0,0,0.58);
      color: #fff;
      font-size: 22px;
      line-height: 1;
      cursor: pointer;
    }

    /* ======= FEEDBACK PREVIEW ======= */
    .feedback-section {
      padding:50px 20px;
      background:
        radial-gradient(circle at 10% 10%, rgba(39,174,96,0.14), transparent 35%),
        #121212;
      border-top:1px solid rgba(255,255,255,0.06);
      border-bottom:1px solid rgba(255,255,255,0.06);
    }
    .feedback-grid {
      max-width: 1200px;
      margin: 0 auto;
      display: grid;
      gap: 20px;
      grid-template-columns: 0.95fr 1.05fr;
      padding: 0 12px;
    }
    .panel {
      background: rgba(255,255,255,0.05);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 14px;
      padding: 18px;
    }
    .panel h3 {
      margin-bottom: 12px;
    }
    .feedback-form textarea, .feedback-form select {
      width:100%;
      padding:12px;
      margin:10px 0;
      border-radius:10px;
      border:none;
      background:rgba(255,255,255,0.05);
      color:white;
    }
    .feedback-form textarea::placeholder { color:rgba(255,255,255,0.7); }
    .feedback-form select { cursor:pointer; }
    .feedback-form select option {
      color: #111;
      background: #fff;
    }
    .feedback-form button { width:100%; padding:12px; margin-top:10px; background:#fff; color:#333; border:none; border-radius:25px; font-weight:bold; cursor:pointer; transition: background 0.3s; }
    .feedback-form button:hover { background:#e6e6e6; }
    .reviews { margin-top:6px; }
    .review-card {
      background:rgba(255,255,255,0.08);
      border: 1px solid rgba(255,255,255,0.1);
      padding:14px;
      border-radius:12px;
      margin-bottom:10px;
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap: 10px;
    }
    .review-card .status { padding:4px 10px; border-radius:15px; font-size:12px; font-weight:600; }
    .status.submitted { background:#bdc3c7; color:#2c3e50; }
    .status.approved { background:#2ecc71; color:white; }
    .status.rejected { background:#ef233c; color:white; }
    .status-note { color: #b8b8b8; font-size: 13px; margin-top: 10px; }

    /* Alert messages */
    .alert { padding:10px; border-radius:10px; margin-bottom:20px; }
    .alert.error { background:#ef233c; color:white; }
    .alert.success { background:#2ecc71; color:white; }
    .global-toast-wrap {
      position: fixed;
      top: 90px;
      right: 20px;
      z-index: 3000;
      display: grid;
      gap: 10px;
      pointer-events: none;
    }
    .global-toast {
      min-width: 260px;
      max-width: 360px;
      border-radius: 12px;
      padding: 12px 14px;
      color: #fff;
      font-weight: 600;
      box-shadow: 0 12px 28px rgba(0,0,0,0.38);
      border: 1px solid rgba(255,255,255,0.16);
      transform: translateY(-6px);
      opacity: 0;
      animation: toastIn 180ms ease forwards;
      pointer-events: auto;
    }
    .global-toast.success { background: linear-gradient(135deg, #2ecc71, #27ae60); }
    .global-toast.error { background: linear-gradient(135deg, #ef233c, #b80721); }
    .global-toast.fade-out {
      animation: toastOut 220ms ease forwards;
    }
    @keyframes toastIn {
      to { opacity: 1; transform: translateY(0); }
    }
    @keyframes toastOut {
      to { opacity: 0; transform: translateY(-6px); }
    }

    /* ======= FAQ SECTION ======= */
    .faq-section {
      padding:50px 20px;
      background:#0f0f0f;
    }
    .faq-wrap {
      max-width: 1100px;
      margin: 0 auto;
      padding: 0 12px;
    }
    .faq-head {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 16px;
      gap: 10px;
    }
    .faq-head a {
      color: #27ae60;
      text-decoration: none;
      font-weight: 600;
    }
    .faq-head a:hover { text-decoration: underline; }
    .faq-item { margin-bottom:10px; }
    .faq-question { width:100%; text-align:left; background:#333; color:white; padding:12px; border:none; border-radius:10px; cursor:pointer; font-weight:600; transition: background 0.3s; }
    .faq-question:hover { background:#444; }
    .faq-answer { display:none; padding:10px 15px; background:#222; border-radius:8px; margin-top:5px; font-size:13px; }
    .final-cta {
      max-width: 1200px;
      margin: 0 auto 40px;
      padding: 24px;
      background: linear-gradient(135deg, rgba(39,174,96,0.18), rgba(241,196,15,0.14));
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 16px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 14px;
    }
    .final-cta p {
      color: #e8e8e8;
      margin-top: 6px;
    }

    /* ======= LIGHT THEME OVERRIDES ======= */
    html[data-theme="light"] body {
      background: var(--rbj-bg, #fff);
      color: var(--rbj-text, #7a211b);
    }
    html[data-theme="light"] .navbar {
      background: var(--rbj-navbar-bg, rgba(255,255,255,0.92));
      border-bottom: 1px solid var(--rbj-border, rgba(217,4,41,0.22));
    }
    html[data-theme="light"] .navbar .logo,
    html[data-theme="light"] .navbar .nav-links a,
    html[data-theme="light"] .account-username,
    html[data-theme="light"] .account-trigger {
      color: var(--rbj-text, #7a211b);
    }
    html[data-theme="light"] .hero {
      background: linear-gradient(150deg, #fff8f7 0%, #ffffff 100%);
      border-bottom: 1px solid var(--rbj-border, rgba(217,4,41,0.22));
    }
    html[data-theme="light"] .hero::before {
      background: radial-gradient(circle, rgba(217,4,41,0.18), transparent 70%);
    }
    html[data-theme="light"] .hero-badge {
      background: rgba(217,4,41,0.08);
      border-color: rgba(217,4,41,0.2);
      color: var(--rbj-text, #7a211b);
    }
    html[data-theme="light"] .hero-text h2,
    html[data-theme="light"] .hero-text p {
      color: var(--rbj-muted, #9f4b43);
    }
    html[data-theme="light"] .btn-secondary {
      background: rgba(217,4,41,0.08);
      border-color: var(--rbj-border, rgba(217,4,41,0.22));
      color: var(--rbj-text, #7a211b);
    }
    html[data-theme="light"] .hero-image {
      background: #fff;
      border-color: var(--rbj-border, rgba(217,4,41,0.22));
      box-shadow: 0 16px 36px rgba(217,4,41,0.12);
    }
    html[data-theme="light"] .trust-strip {
      background: #fff6f4;
      border-top: 1px solid var(--rbj-border, rgba(217,4,41,0.18));
      border-bottom: 1px solid var(--rbj-border, rgba(217,4,41,0.18));
    }
    html[data-theme="light"] .trust-item {
      background: #fff;
      border-color: rgba(217,4,41,0.18);
    }
    html[data-theme="light"] .trust-item p {
      color: var(--rbj-muted, #9f4b43);
    }
    html[data-theme="light"] .trust-item i {
      color: var(--rbj-accent, #d90429);
    }
    html[data-theme="light"] .past-covers {
      background: #fff;
    }
    html[data-theme="light"] .section-title p {
      color: var(--rbj-muted, #9f4b43);
    }
    html[data-theme="light"] .cover-frame {
      background: linear-gradient(180deg, #fff7f5 0%, #fff 100%);
      border-color: rgba(217,4,41,0.18);
      box-shadow: inset 0 1px 0 rgba(255,255,255,0.8), 0 24px 48px rgba(217,4,41,0.12);
    }
    html[data-theme="light"] .cover-card {
      background: #fff;
      border-color: rgba(217,4,41,0.18);
      box-shadow: 0 16px 30px rgba(217,4,41,0.12);
    }
    html[data-theme="light"] .cover-card .cover-caption,
    html[data-theme="light"] .cover-status,
    html[data-theme="light"] .cover-lightbox-caption {
      color: var(--rbj-text, #7a211b);
    }
    html[data-theme="light"] .cover-status {
      background: rgba(217,4,41,0.08);
      border-color: rgba(217,4,41,0.2);
    }
    html[data-theme="light"] .cover-controls button,
    html[data-theme="light"] .cover-lightbox-close {
      background: #fff;
      color: var(--rbj-text, #7a211b);
      border-color: rgba(217,4,41,0.22);
    }
    html[data-theme="light"] .locator-section {
      background: #fff7f5;
      border-top: 1px solid var(--rbj-border, rgba(217,4,41,0.18));
      border-bottom: 1px solid var(--rbj-border, rgba(217,4,41,0.18));
    }
    html[data-theme="light"] .locator-map-wrap,
    html[data-theme="light"] .locator-list {
      background: #fff;
      border-color: rgba(217,4,41,0.18);
    }
    html[data-theme="light"] .locator-list > p {
      color: var(--rbj-muted, #9f4b43);
    }
    html[data-theme="light"] .locator-map-expand-btn {
      background: #fff;
      color: var(--rbj-text, #7a211b);
      border-color: rgba(217,4,41,0.22);
    }
    html[data-theme="light"] .feedback-section,
    html[data-theme="light"] .faq-section {
      background: #fff;
    }
    html[data-theme="light"] .panel {
      background: #fff;
      border-color: rgba(217,4,41,0.18);
      box-shadow: 0 18px 30px rgba(217,4,41,0.12);
    }
    html[data-theme="light"] .feedback-form textarea,
    html[data-theme="light"] .feedback-form select {
      background: #fff;
      color: var(--rbj-text, #7a211b);
      border-color: rgba(217,4,41,0.28);
    }
    html[data-theme="light"] .feedback-form textarea::placeholder {
      color: #ad6a65;
    }
    html[data-theme="light"] .review-card {
      background: #fff7f5;
      border-color: rgba(217,4,41,0.18);
    }
    html[data-theme="light"] .faq-head a {
      color: var(--rbj-text, #7a211b);
    }
    html[data-theme="light"] .faq-question {
      background: #fff7f5;
      color: var(--rbj-text, #7a211b);
      border: 1px solid rgba(217,4,41,0.18);
    }
    html[data-theme="light"] .faq-question:hover {
      background: #fff0ec;
    }
    html[data-theme="light"] .faq-answer {
      background: #fff;
      border: 1px solid rgba(217,4,41,0.18);
      color: var(--rbj-muted, #9f4b43);
    }
    html[data-theme="light"] .final-cta {
      background: #fff7f5;
      border-color: rgba(217,4,41,0.18);
    }

    /* ======= RESPONSIVE ======= */
    @media (max-width:1100px) {
      .hero { grid-template-columns: 1fr; }
      .feedback-grid { grid-template-columns: 1fr; }
      .locator-grid { grid-template-columns: 1fr; }
      .trust-strip { grid-template-columns: 1fr; }
      .cover-frame { height: 390px; }
      .cover-card.is-left { transform: translate(calc(-50% - 220px), -50%) scale(0.8) rotateY(16deg); }
      .cover-card.is-right { transform: translate(calc(-50% + 220px), -50%) scale(0.8) rotateY(-16deg); }
    }
    @media (max-width:700px) {
      .navbar { padding:10px 20px; }
      .navbar .nav-links a { margin-left:10px; font-size:14px; }
      .hero { padding: 115px 20px 46px; }
      .cover-frame { height: 340px; }
      .cover-card { width: min(320px, 80vw); padding: 8px; }
      .cover-media { height: 200px; }
      .cover-card .cover-caption { font-size: 12px; }
      .locator-map-wrap iframe { height: 300px; }
      .locator-map-expand-btn { font-size: 11px; padding: 7px 10px; }
      .cover-card.is-left { transform: translate(calc(-50% - 110px), -50%) scale(0.74) rotateY(14deg); }
      .cover-card.is-right { transform: translate(calc(-50% + 110px), -50%) scale(0.74) rotateY(-14deg); }
      .final-cta { flex-direction: column; align-items: flex-start; }
    }

    .scroll-top-btn {
      position: fixed;
      right: 18px;
      bottom: 18px;
      width: 42px;
      height: 42px;
      border-radius: 999px;
      border: 1px solid rgba(255,255,255,0.28);
      background: rgba(0,0,0,0.56);
      color: #fff;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      z-index: 2900;
      opacity: 0;
      transform: translateY(10px);
      pointer-events: none;
      transition: opacity 180ms ease, transform 180ms ease, background 180ms ease, border-color 180ms ease;
      backdrop-filter: blur(2px);
    }
    .scroll-top-btn.show {
      opacity: 1;
      transform: translateY(0);
      pointer-events: auto;
    }
    .scroll-top-btn:hover {
      background: rgba(217,4,41,0.8);
      border-color: rgba(217,4,41,0.95);
    }

    @media (prefers-reduced-motion: reduce) {
      html { scroll-behavior: auto; }
    }
  </style>
  <?php include __DIR__ . '/partials/user_navbar_theme.php'; ?>
</head>
<body class="home">

  <!-- NAVBAR -->
  <nav class="navbar">
    <a href="index.php" class="logo">
      <img src="../rbjlogo.png" alt="RBJ Accessories Logo">
      <span>RBJ Accessories</span>
    </a>
    <div class="nav-links">
      <a href="#hero">Home</a>
      <a href="catalog.php">Shop</a>
      <a href="customize.php">Customize</a>
      <?php if ($is_logged_in): ?>
      <a href="cart.php"><i class='bx bx-cart'></i><span class="cart-count" data-cart-count style="<?php echo $cart_count > 0 ? '' : 'display:none;'; ?>"><?php echo (int)$cart_count; ?></span></a>
      <?php endif; ?>
      
      <?php if ($is_logged_in): ?>
      <?php include __DIR__ . '/partials/account_menu.php'; ?>
      <?php else: ?>
      <a href="../login.php">Login</a>
      <?php endif; ?>
    </div>
  </nav>

  <!-- HERO SECTION -->
  <section id="hero" class="hero" data-reveal>
    <div class="hero-text">
      <span class="hero-badge"><i class='bx bxs-bolt-circle'></i> Built for Real Riders</span>
      <h1>Design Your Dream Motorcycle Seat</h1>
      <h2>Premium Seat Covers and Accessories</h2>
      <p>Customize your build with quality materials and rider-focused design.</p>
      <div class="hero-cta">
        <a href="customize.php" class="btn-primary"><i class='bx bx-palette'></i> Customize Now</a>
        <a href="catalog.php" class="btn-secondary"><i class='bx bx-grid-alt'></i> Shop Now</a>
      </div>
    </div>
    <div class="hero-image">
      <img src="hero-seat.png" alt="Custom Motorcycle Seat">
    </div>
  </section>

  <section class="trust-strip" id="highlights" data-reveal>
    <article class="trust-item">
      <i class='bx bx-shield-quarter'></i>
      <div>
        <h3>Premium Materials</h3>
        <p>Durable components built for daily and long rides.</p>
      </div>
    </article>
    <article class="trust-item">
      <i class='bx bx-wrench'></i>
      <div>
        <h3>Custom Fit Build</h3>
        <p>Designed around your preferred setup and comfort.</p>
      </div>
    </article>
    <article class="trust-item">
      <i class='bx bx-support'></i>
      <div>
        <h3>Reliable Support</h3>
        <p>Fast assistance from inquiry up to post-order.</p>
      </div>
    </article>
  </section>

  <!-- PAST SEAT COVERS -->
  <section class="past-covers" id="featured" data-reveal>
    <div class="section-title">
      <h2>Featured Builds</h2>
      <p>Sample custom seat projects from RBJ Accessories.</p>
    </div>
    <div class="cover-carousel" id="coverCarousel" data-interval="2800">
      <div class="cover-frame">
        <div class="cover-status">
          <i class='bx bx-refresh'></i>
          <span id="coverStageCounter">1 / 6</span>
        </div>
        <div class="cover-controls">
          <button type="button" id="coverPrevBtn" aria-label="Previous build"><i class='bx bx-chevron-left'></i></button>
          <button type="button" id="coverNextBtn" aria-label="Next build"><i class='bx bx-chevron-right'></i></button>
        </div>
        <div class="cover-track">
          <div class="cover-card">
            <div class="cover-media" style="--cover-bg: url('../cover1.jpg');">
              <img src="../cover1.jpg" alt="Custom Seat Cover 1">
            </div>
            <span class="cover-caption">Custom Seat Cover 1</span>
          </div>
          <div class="cover-card">
            <div class="cover-media" style="--cover-bg: url('../cover2.jpg');">
              <img src="../cover2.jpg" alt="Custom Seat Cover 2">
            </div>
            <span class="cover-caption">Custom Seat Cover 2</span>
          </div>
          <div class="cover-card">
            <div class="cover-media" style="--cover-bg: url('../cover3.jpg');">
              <img src="../cover3.jpg" alt="Custom Seat Cover 3">
            </div>
            <span class="cover-caption">Custom Seat Cover 3</span>
          </div>
          <div class="cover-card">
            <div class="cover-media" style="--cover-bg: url('../cover4.jpg');">
              <img src="../cover4.jpg" alt="Custom Seat Cover 4">
            </div>
            <span class="cover-caption">Custom Seat Cover 4</span>
          </div>
          <div class="cover-card">
            <div class="cover-media" style="--cover-bg: url('../cover5.jpg');">
              <img src="../cover5.jpg" alt="Custom Seat Cover 5">
            </div>
            <span class="cover-caption">Custom Seat Cover 5</span>
          </div>
          <div class="cover-card">
            <div class="cover-media" style="--cover-bg: url('../cover6.jpg');">
              <img src="../cover6.jpg" alt="Custom Seat Cover 6">
            </div>
            <span class="cover-caption">Custom Seat Cover 6</span>
          </div>
        </div>
      </div>
      <div class="cover-dots" id="coverDots" aria-label="Featured builds indicators"></div>
    </div>
  </section>

  <div class="cover-lightbox" id="coverLightbox" aria-hidden="true" role="dialog" aria-label="Featured build preview">
    <div class="cover-lightbox-inner">
      <button type="button" class="cover-lightbox-close" id="coverLightboxClose" aria-label="Close preview">&times;</button>
      <img id="coverLightboxImage" src="" alt="">
      <div class="cover-lightbox-caption" id="coverLightboxCaption"></div>
    </div>
  </div>

  <!-- STORE LOCATOR SECTION -->
  <section class="locator-section" id="locator" data-reveal>
    <div class="section-title">
      <h2>Store Locator</h2>
      <p>Find and navigate to the nearest RBJ branch quickly.</p>
    </div>
    <div class="locator-grid">
      <div class="locator-map-wrap">
        <div class="locator-map-actions">
          <button type="button" id="locatorExpandBtn" class="locator-map-expand-btn" aria-label="View full map">
            <i class='bx bx-expand-alt'></i> View Full Map
          </button>
        </div>
        <iframe
          id="locatorMapFrame"
          src="https://www.google.com/maps?q=6566%2B6M%20Calamba%2C%20Laguna&output=embed"
          loading="lazy"
          referrerpolicy="no-referrer-when-downgrade"
          title="RBJ Branches Map Preview">
        </iframe>
        <span class="locator-map-link-blocker" aria-hidden="true"></span>
      </div>
      <aside class="locator-list" aria-label="Branch list preview">
        <h3>RBJ Branches</h3>
        <p>Click a branch or Get Directions. The route and location will load in this page map view.</p>
        <div class="branch-list">
          <div class="branch-item" tabindex="0" role="link" data-map-query="Rbj Accessories Calamba, 49 Burgos St, Calamba, 4027 Laguna">
            <div class="branch-main">
              <i class='bx bxs-map'></i>
              <div>
                <strong>Rbj Accessories - Calamba Branch</strong>
                <span>49 Burgos St, Calamba, 4027 Laguna (6566+6M Calamba, Laguna)</span>
              </div>
            </div>
            <div class="branch-actions">
              <button type="button" class="branch-direction-btn" data-destination="RBJ Accessories - Calamba Branch, 49 Burgos St, Calamba, 4027 Laguna">Get Directions</button>
            </div>
          </div>
          <div class="branch-item" tabindex="0" role="link" data-map-query="RBJ ACCESSORIES-Tanauan Branch, 3553+PMH, Tanauan City, Batangas">
            <div class="branch-main">
              <i class='bx bxs-map'></i>
              <div>
                <strong>RBJ Accessories - Tanauan Branch</strong>
                <span>3553+PMH, Tanauan City, Batangas</span>
              </div>
            </div>
            <div class="branch-actions">
              <button type="button" class="branch-direction-btn" data-destination="RBJ Accessories - Tanauan Branch, Tanauan City, Batangas">Get Directions</button>
            </div>
          </div>
          <div class="branch-item" tabindex="0" role="link" data-map-query="RBJ Accessories-GMA BRANCH, Area K, 125 Governor's Dr, Cavite, 4117 Cavite">
            <div class="branch-main">
              <i class='bx bxs-map'></i>
              <div>
                <strong>RBJ Accessories - Cavite Branch</strong>
                <span>Area K, 125 Governor's Dr, Cavite, 4117 Cavite (72M2+57 General Mariano Alvarez, Cavite)</span>
              </div>
            </div>
            <div class="branch-actions">
              <button type="button" class="branch-direction-btn" data-destination="RBJ Accessories - Cavite Branch, Area K, 125 Governor's Dr, General Mariano Alvarez, Cavite">Get Directions</button>
            </div>
          </div>
          <div class="branch-item" tabindex="0" role="link" data-map-query="RBJ ACCESSORIES-Pasig Branch, 306 Eulogio Amang Rodriguez Ave, Manggahan, Pasig, 1611 Metro Manila">
            <div class="branch-main">
              <i class='bx bxs-map'></i>
              <div>
                <strong>RBJ Accessories - Pasig Branch</strong>
                <span>306 Eulogio Amang Rodriguez Ave, Manggahan, Pasig, 1611 Metro Manila (J34R+HW Pasig, Metro Manila)</span>
              </div>
            </div>
            <div class="branch-actions">
              <button type="button" class="branch-direction-btn" data-destination="RBJ Accessories - Pasig Branch, 306 Eulogio Amang Rodriguez Ave, Manggahan, Pasig, 1611 Metro Manila">Get Directions</button>
            </div>
          </div>
        </div>
      </aside>
    </div>
  </section>
  <div class="locator-map-modal" id="locatorMapModal" aria-hidden="true" role="dialog" aria-label="Expanded store locator map">
    <div class="locator-map-modal-inner">
      <button type="button" class="locator-map-modal-close" id="locatorMapModalClose" aria-label="Close expanded map">&times;</button>
      <iframe
        id="locatorMapModalFrame"
        src=""
        loading="lazy"
        referrerpolicy="no-referrer-when-downgrade"
        title="Expanded RBJ Branches Map">
      </iframe>
      <span class="locator-map-link-blocker" aria-hidden="true"></span>
    </div>
  </div>

  <!-- FEEDBACK SECTION -->
  <section class="feedback-section" id="feedback" data-reveal>
    <div class="feedback-grid">
      <div class="panel">
        <h3>Share Your Feedback</h3>
        <div id="feedbackResponse">
        <?php if (!empty($errors)): ?>
          <div class="alert error">
            <?php foreach ($errors as $error): ?>
              <p><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
          <div class="alert success">
            <p><?php echo htmlspecialchars($success); ?></p>
          </div>
        <?php endif; ?>
        </div>

        <?php if ($is_logged_in): ?>
        <form class="feedback-form" method="POST" action="index.php" id="feedbackForm">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
          <input type="hidden" name="submit_feedback" value="1">
          <textarea name="feedback" placeholder="Tell us how your RBJ experience went..." required></textarea>
          <select name="rating" required>
            <option value="">Rate us</option>
            <option value="5">★★★★★ Excellent (5 stars)</option>
            <option value="4">★★★★☆ Very Good (4 stars)</option>
            <option value="3">★★★☆☆ Good (3 stars)</option>
            <option value="2">★★☆☆☆ Fair (2 stars)</option>
            <option value="1">★☆☆☆☆ Poor (1 star)</option>
          </select>
          <button type="submit">Submit Feedback</button>
        </form>
        <?php else: ?>
          <p class="status-note">Login to submit your feedback and rating.</p>
          <a href="../login.php" class="btn-secondary">Login to Continue</a>
        <?php endif; ?>
        <p class="status-note">Feedback posted here is reviewed first before publishing.</p>
      </div>

      <div class="panel">
        <h3>What Riders Say</h3>
        <div class="reviews">
          <?php
          $stmt = $conn->prepare("SELECT f.feedback, f.rating, u.username FROM feedback f JOIN users u ON f.user_id = u.id WHERE f.status = 'approved' ORDER BY f.created_at DESC LIMIT 3");
          $stmt->execute();
          $result = $stmt->get_result();

          if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
              $stars = str_repeat('★', (int)$row['rating']) . str_repeat('☆', 5 - (int)$row['rating']);
              echo '<div class="review-card">';
              echo '<div>';
              echo '<p>"' . htmlspecialchars($row['feedback']) . '"</p>';
              echo '<small style="color:#bdc3c7;">- ' . htmlspecialchars($row['username']) . ' (' . $stars . ')</small>';
              echo '</div>';
              echo '<span class="status approved">Approved</span>';
              echo '</div>';
            }
          } else {
            echo '<div class="review-card">';
            echo '<p>"Premium fit and very comfortable for long rides."</p>';
            echo '<span class="status approved">Approved</span>';
            echo '</div>';
            echo '<div class="review-card">';
            echo '<p>"Customization process was smooth and support was responsive."</p>';
            echo '<span class="status approved">Approved</span>';
            echo '</div>';
          }
          $stmt->close();
          ?>
        </div>
        <a href="feedback_history.php" class="btn-secondary">Feedback History</a>
      </div>
    </div>
  </section>

  <!-- FAQ SECTION -->
  <section class="faq-section" id="faq" data-reveal>
    <div class="faq-wrap">
      <div class="faq-head">
        <h2>Quick Answers</h2>
        <a href="support.php">Need More Help?</a>
      </div>
      <div class="faq-item">
        <button class="faq-question">How long does delivery take?</button>
        <div class="faq-answer">Typical delivery is 7-14 days depending on customization details and queue volume.</div>
      </div>
      <div class="faq-item">
        <button class="faq-question">Can I choose colors and materials?</button>
        <div class="faq-answer">Yes, you can pick seat color, material, and design options during customization.</div>
      </div>
      <div class="faq-item">
        <button class="faq-question">Can I ask for order updates?</button>
        <div class="faq-answer">Yes, use your order tracking page or contact support for status updates.</div>
      </div>

      <div class="final-cta">
        <div>
          <h3>Ready to Build Your Seat Setup?</h3>
          <p>Start your customization now and bring your preferred design to life.</p>
        </div>
        <a href="customize.php" class="btn-primary">Start Customizing</a>
      </div>
    </div>
  </section>

  <div id="globalToastWrap" class="global-toast-wrap" aria-live="polite" aria-atomic="true"></div>
  <button id="scrollTopBtn" class="scroll-top-btn" type="button" aria-label="Scroll to top">
    <i class='bx bx-chevron-up'></i>
  </button>

  <?php $conn->close(); ?>

  <script src="assets/user-index.js"></script>
<?php include __DIR__ . '/partials/user_footer.php'; ?>

</body>
</html>






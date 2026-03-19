<?php
session_start();
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
  <title>RBJ Accessories - Home (Test)</title>
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <style>
    :root {
      --test-accent: #ef233c;
      --test-accent-2: #e06248;
      --test-surface: rgba(255,255,255,0.05);
      --test-border: rgba(255,255,255,0.12);
    }
    /* ======= RESET & GENERAL ======= */
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: "Montserrat", sans-serif; }
    body { font-family: "Montserrat", sans-serif; min-height: 100vh; background: linear-gradient(135deg,#1b1b1b,#111); color:white; padding-top:100px; }
    body.home { display:flex; flex-direction:column; min-height:100svh; padding:0; margin:0; overflow-x:hidden; position: relative; }
    .page-bg-canvas {
      position: fixed;
      inset: 0;
      z-index: 0;
      width: 100%;
      height: 100%;
      pointer-events: none;
      display: block;
      background: #090909;
    }
    body.home::before {
      content: "";
      position: fixed;
      inset: 0;
      z-index: 1;
      pointer-events: none;
      background:
        radial-gradient(circle at 12% 12%, rgba(217,4,41,0.14), transparent 34%),
        radial-gradient(circle at 88% 15%, rgba(255,255,255,0.09), transparent 30%),
        linear-gradient(180deg, rgba(7,7,7,0.50) 0%, rgba(8,8,8,0.45) 45%, rgba(7,7,7,0.55) 100%);
    }
    .navbar,
    main,
    section,
    footer {
      position: relative;
      z-index: 2;
    }

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
      background: rgba(12,12,12,0.30);
      border-bottom: 1px solid rgba(255,255,255,0.08);
      overflow: hidden;
      isolation: isolate;
    }
    .hero::selection { background: rgba(217,4,41,0.3); }
    .hero > * { position: relative; z-index: 3; }
    .hero::after {
      content: "";
      position: absolute;
      inset: 0;
      z-index: 2;
      background:
        radial-gradient(circle at 12% 18%, rgba(217,4,41,0.2), transparent 36%),
        radial-gradient(circle at 84% 12%, rgba(255,255,255,0.08), transparent 30%),
        linear-gradient(100deg, rgba(5,5,5,0.78) 0%, rgba(10,10,10,0.62) 52%, rgba(5,5,5,0.75) 100%);
      pointer-events: none;
    }
    .hero::before {
      content: "";
      position: absolute;
      inset: auto -140px -140px auto;
      width: 360px;
      height: 360px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(39,174,96,0.26), transparent 70%);
      z-index: 2;
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
      animation: badgePulse 2.8s ease-in-out infinite;
    }
    .hero-text h1 {
      font-size: clamp(34px, 5vw, 56px);
      line-height: 1.04;
      margin-bottom: 12px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      text-shadow: 0 10px 30px rgba(0,0,0,0.35);
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
      background: linear-gradient(135deg, var(--test-accent), var(--test-accent-2));
      color: #fff;
      box-shadow: 0 10px 30px rgba(217,4,41,0.34);
    }
    .btn-primary:hover {
      transform: translateY(-3px) scale(1.01);
      box-shadow: 0 14px 36px rgba(217,4,41,0.45);
    }
    .btn-secondary {
      color: #fff;
      border: 1px solid rgba(255,255,255,0.24);
      background: rgba(255,255,255,0.04);
    }
    .btn-secondary:hover {
      transform: translateY(-2px) scale(1.01);
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
      transition: transform 280ms ease, box-shadow 280ms ease;
    }
    .hero-image img {
      width: 100%;
      max-height: 340px;
      object-fit: cover;
      border-radius: 14px;
      transition: transform 320ms ease;
    }
    .hero:hover .hero-image {
      transform: translateY(-4px);
      box-shadow: 0 24px 50px rgba(0,0,0,0.48);
    }
    .hero:hover .hero-image img {
      transform: scale(1.02);
    }
    .trust-strip {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 14px;
      padding: 20px 50px;
      background: rgba(15,15,15,0.34);
      border-top: 1px solid rgba(255,255,255,0.06);
      border-bottom: 1px solid rgba(255,255,255,0.06);
    }
    .trust-item {
      background: var(--test-surface);
      border: 1px solid var(--test-border);
      border-radius: 12px;
      padding: 14px 16px;
      display: flex;
      gap: 12px;
      align-items: center;
      transition: transform 240ms ease, box-shadow 240ms ease, border-color 240ms ease;
    }
    .trust-item i {
      font-size: 24px;
      color: var(--test-accent-2);
    }
    .trust-item:hover {
      transform: translateY(-4px);
      border-color: rgba(217,4,41,0.55);
      box-shadow: 0 16px 28px rgba(0,0,0,0.35);
    }
    .trust-item h3 {
      font-size: 14px;
      margin-bottom: 2px;
    }
    .trust-item p {
      font-size: 12px;
      color: #bdbdbd;
    }

    /* ======= FOOTER QUICK LINKS ======= */
    .home-footer {
      margin-top: auto;
      padding: 28px 20px;
      background: rgba(12,12,12,0.55);
      border-top: 1px solid rgba(255,255,255,0.08);
      text-align: center;
    }
    .footer-links {
      display: flex;
      justify-content: center;
      gap: 22px;
      flex-wrap: wrap;
      margin-bottom: 10px;
    }
    .footer-links a {
      color: #d7d7d7;
      text-decoration: none;
      font-weight: 500;
    }
    .footer-links a:hover { color: #fff; text-decoration: underline; }
    .home-footer p {
      margin: 0;
      color: #999;
      font-size: 13px;
    }

    /* ======= SHOWCASE ======= */
    .past-covers { padding:52px 20px; background:rgba(10,10,10,0.26); }
    .section-title { margin: 0 auto 18px; max-width: 1200px; padding: 0 12px; }
    .section-title h2 { font-size: 30px; margin-bottom: 6px; }
    .section-title p { color: #c2c2c2; }
    .showroom-shell {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 12px;
      position: relative;
      perspective: 1300px;
    }
    .showroom-stage {
      height: 500px;
      border-radius: 22px;
      position: relative;
      overflow: hidden;
      background:
        radial-gradient(circle at 22% 18%, rgba(217,4,41,0.16), transparent 38%),
        radial-gradient(circle at 78% 16%, rgba(255,255,255,0.07), transparent 34%),
        linear-gradient(180deg, rgba(18,18,18,0.84) 0%, rgba(12,12,12,0.8) 100%);
      border: 1px solid rgba(255,255,255,0.14);
      box-shadow: inset 0 1px 0 rgba(255,255,255,0.08), 0 24px 44px rgba(0,0,0,0.45);
    }
    .showroom-card {
      position: absolute;
      left: 50%;
      top: 50%;
      width: min(430px, 76vw);
      transform-origin: center center;
      border-radius: 16px;
      overflow: hidden;
      border: 1px solid rgba(255,255,255,0.12);
      box-shadow: 0 20px 40px rgba(0,0,0,0.42);
      background: rgba(20,20,20,0.9);
      opacity: 0;
      pointer-events: none;
      transition: transform 0.65s cubic-bezier(.21,.84,.29,1), opacity 0.4s ease, filter 0.4s ease, border-color 0.3s ease;
      will-change: transform, opacity;
    }
    .showroom-card img {
      width: 100%;
      height: 280px;
      object-fit: cover;
      display: block;
      transition: transform 0.45s ease;
    }
    .showroom-card .card-meta {
      padding: 11px 12px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      font-size: 12px;
      color: #ddd;
      background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(0,0,0,0.24));
    }
    .showroom-card .tag {
      font-size: 10px;
      text-transform: uppercase;
      letter-spacing: .45px;
      font-weight: 700;
      color: #ffd2cc;
    }
    .showroom-card.is-active {
      opacity: 1;
      pointer-events: auto;
      transform: translate(-50%, -50%) scale(1.08) rotateY(0deg);
      z-index: 7;
      border-color: rgba(217,4,41,0.8);
      filter: saturate(1.06);
    }
    .showroom-card.is-active img {
      transform: scale(1.02);
    }
    .showroom-card.is-prev,
    .showroom-card.is-next {
      opacity: 0.82;
      pointer-events: auto;
      z-index: 4;
      filter: saturate(0.86) brightness(0.9);
    }
    .showroom-card.is-prev {
      transform: translate(calc(-50% - 300px), -50%) scale(0.84) rotateY(16deg);
    }
    .showroom-card.is-next {
      transform: translate(calc(-50% + 300px), -50%) scale(0.84) rotateY(-16deg);
    }
    .showroom-card.is-hidden {
      opacity: 0;
      transform: translate(-50%, -50%) scale(0.72);
      z-index: 1;
      pointer-events: none;
    }
    .showroom-card.is-active:hover {
      border-color: rgba(217,4,41,0.95);
      box-shadow: 0 28px 50px rgba(0,0,0,0.5), 0 0 0 2px rgba(217,4,41,0.15);
    }
    .showroom-controls {
      position: absolute;
      top: 14px;
      right: 14px;
      z-index: 12;
      display: flex;
      gap: 8px;
    }
    .showroom-controls button {
      width: 36px;
      height: 36px;
      border: 1px solid rgba(255,255,255,0.2);
      border-radius: 50%;
      background: rgba(0,0,0,0.45);
      color: #fff;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      transition: transform 0.2s ease, background 0.2s ease, border-color 0.2s ease;
    }
    .showroom-controls button:hover {
      transform: translateY(-2px);
      background: rgba(217,4,41,0.78);
      border-color: rgba(217,4,41,0.92);
    }
    .showroom-footer {
      margin-top: 14px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
    }
    .showroom-status {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 12px;
      color: #ececec;
      background: rgba(0,0,0,0.36);
      border: 1px solid rgba(255,255,255,0.18);
      border-radius: 999px;
      padding: 9px 13px;
    }
    .showroom-status i {
      color: #ff9f97;
      font-size: 15px;
    }
    .showroom-title {
      font-size: clamp(20px, 3vw, 30px);
      font-weight: 700;
      text-shadow: 0 10px 24px rgba(0,0,0,0.35);
    }
    .showroom-dots {
      display: flex;
      align-items: center;
      gap: 8px;
      min-height: 12px;
    }
    .showroom-dots button {
      width: 8px;
      height: 8px;
      border: none;
      border-radius: 999px;
      background: rgba(255,255,255,0.35);
      cursor: pointer;
      transition: width 0.25s ease, background 0.25s ease;
      padding: 0;
    }
    .showroom-dots button.active {
      width: 24px;
      background: #ef233c;
    }

    /* ======= FEEDBACK PREVIEW ======= */
    .feedback-section {
      padding:50px 20px;
      background:
        radial-gradient(circle at 10% 10%, rgba(39,174,96,0.14), transparent 35%),
        rgba(11,11,11,0.28);
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
      background: var(--test-surface);
      border: 1px solid var(--test-border);
      border-radius: 14px;
      padding: 18px;
      transition: transform 240ms ease, box-shadow 240ms ease, border-color 240ms ease;
    }
    .panel:hover {
      transform: translateY(-4px);
      border-color: rgba(217,4,41,0.42);
      box-shadow: 0 16px 30px rgba(0,0,0,0.35);
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
      background:rgba(11,11,11,0.25);
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
      position: relative;
      overflow: hidden;
    }
    .final-cta::before {
      content: "";
      position: absolute;
      inset: -40% auto auto -10%;
      width: 220px;
      height: 220px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(217,4,41,0.26), transparent 68%);
      pointer-events: none;
    }
    .final-cta p {
      color: #e8e8e8;
      margin-top: 6px;
    }

    /* ======= RESPONSIVE ======= */
    @media (max-width:1100px) {
      .hero { grid-template-columns: 1fr; }
      .feedback-grid { grid-template-columns: 1fr; }
      .trust-strip { grid-template-columns: 1fr; }
      .showroom-stage { height: 440px; }
      .showroom-card.is-prev { transform: translate(calc(-50% - 230px), -50%) scale(0.8) rotateY(14deg); }
      .showroom-card.is-next { transform: translate(calc(-50% + 230px), -50%) scale(0.8) rotateY(-14deg); }
    }
    @media (max-width:700px) {
      .navbar { padding:10px 20px; }
      .navbar .nav-links a { margin-left:10px; font-size:14px; }
      .hero { padding: 115px 20px 46px; }
      .showroom-stage { height: 360px; }
      .showroom-card { width: min(330px, 82vw); }
      .showroom-card img { height: 210px; }
      .showroom-card .card-meta { font-size: 11px; padding: 9px 10px; }
      .showroom-card.is-prev { transform: translate(calc(-50% - 110px), -50%) scale(0.72) rotateY(12deg); }
      .showroom-card.is-next { transform: translate(calc(-50% + 110px), -50%) scale(0.72) rotateY(-12deg); }
      .final-cta { flex-direction: column; align-items: flex-start; }
    }

    /* ======= TEST POLISH MOTION ======= */
    .reveal-up {
      opacity: 0;
      transform: translateY(24px);
      transition: opacity 520ms ease, transform 520ms ease;
      will-change: opacity, transform;
    }
    .reveal-up.is-visible {
      opacity: 1;
      transform: translateY(0);
    }
    @keyframes badgePulse {
      0%, 100% { box-shadow: 0 0 0 0 rgba(217,4,41,0.0); }
      50% { box-shadow: 0 0 0 6px rgba(217,4,41,0.14); }
    }
  </style>
</head>
<body class="home">
  <canvas class="page-bg-canvas" id="pageBgCanvas" aria-hidden="true"></canvas>

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
  <section id="hero" class="hero">
    <div class="hero-text reveal-up">
      <span class="hero-badge"><i class='bx bxs-bolt-circle'></i> Built for Real Riders</span>
      <h1>Design Your Dream Motorcycle Seat</h1>
      <h2>Premium Seat Covers and Accessories</h2>
      <p>Customize your build with quality materials and rider-focused design.</p>
      <div class="hero-cta">
        <a href="customize.php" class="btn-primary"><i class='bx bx-palette'></i> Customize Now</a>
        <a href="catalog.php" class="btn-secondary"><i class='bx bx-grid-alt'></i> Shop Now</a>
      </div>
    </div>
    <div class="hero-image reveal-up">
      <img src="hero-seat.png" alt="Custom Motorcycle Seat">
    </div>
  </section>

  <section class="trust-strip">
    <article class="trust-item reveal-up">
      <i class='bx bx-shield-quarter'></i>
      <div>
        <h3>Premium Materials</h3>
        <p>Durable components built for daily and long rides.</p>
      </div>
    </article>
    <article class="trust-item reveal-up">
      <i class='bx bx-wrench'></i>
      <div>
        <h3>Custom Fit Build</h3>
        <p>Designed around your preferred setup and comfort.</p>
      </div>
    </article>
    <article class="trust-item reveal-up">
      <i class='bx bx-support'></i>
      <div>
        <h3>Reliable Support</h3>
        <p>Fast assistance from inquiry up to post-order.</p>
      </div>
    </article>
  </section>

  <!-- PAST SEAT COVERS -->
  <section class="past-covers">
    <div class="section-title reveal-up">
      <h2>Featured Builds</h2>
      <p>Sample custom seat projects from RBJ Accessories.</p>
    </div>
    <div class="showroom-shell reveal-up" id="seatShowroom" data-interval="2500">
      <div class="showroom-stage">
        <div class="showroom-controls">
          <button type="button" id="showroomPrevBtn" aria-label="Previous build"><i class='bx bx-chevron-left'></i></button>
          <button type="button" id="showroomNextBtn" aria-label="Next build"><i class='bx bx-chevron-right'></i></button>
        </div>
        <article class="showroom-card">
          <img src="../cover1.jpg" alt="Custom Seat Cover 1">
          <div class="card-meta"><span class="tag">Premium Stitch</span><span>Build 1</span></div>
        </article>
        <article class="showroom-card">
          <img src="../cover2.jpg" alt="Custom Seat Cover 2">
          <div class="card-meta"><span class="tag">Sport Fit</span><span>Build 2</span></div>
        </article>
        <article class="showroom-card">
          <img src="../cover3.jpg" alt="Custom Seat Cover 3">
          <div class="card-meta"><span class="tag">Street Glide</span><span>Build 3</span></div>
        </article>
        <article class="showroom-card">
          <img src="../cover4.jpg" alt="Custom Seat Cover 4">
          <div class="card-meta"><span class="tag">Comfort Ride</span><span>Build 4</span></div>
        </article>
        <article class="showroom-card">
          <img src="../cover5.jpg" alt="Custom Seat Cover 5">
          <div class="card-meta"><span class="tag">Night Racer</span><span>Build 5</span></div>
        </article>
        <article class="showroom-card">
          <img src="../cover6.jpg" alt="Custom Seat Cover 6">
          <div class="card-meta"><span class="tag">Classic Touring</span><span>Build 6</span></div>
        </article>
      </div>
      <div class="showroom-footer">
        <div class="showroom-status">
          <i class='bx bx-refresh'></i>
          <span id="showroomCounter">1 / 6</span>
        </div>
        <div class="showroom-title" id="showroomTitle">Custom Seat Cover 1</div>
        <div class="showroom-dots" id="showroomDots" aria-label="Showroom indicator"></div>
      </div>
    </div>
  </section>

  <!-- FEEDBACK SECTION -->
  <section class="feedback-section">
    <div class="feedback-grid">
      <div class="panel reveal-up">
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
        <form class="feedback-form" method="POST" action="test_index.php" id="feedbackForm">
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

      <div class="panel reveal-up">
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
  <section class="faq-section">
    <div class="faq-wrap">
      <div class="faq-head reveal-up">
        <h2>Quick Answers</h2>
        <a href="support.php">Need More Help?</a>
      </div>
      <div class="faq-item reveal-up">
        <button class="faq-question">How long does delivery take?</button>
        <div class="faq-answer">Typical delivery is 7-14 days depending on customization details and queue volume.</div>
      </div>
      <div class="faq-item reveal-up">
        <button class="faq-question">Can I choose colors and materials?</button>
        <div class="faq-answer">Yes, you can pick seat color, material, and design options during customization.</div>
      </div>
      <div class="faq-item reveal-up">
        <button class="faq-question">Can I ask for order updates?</button>
        <div class="faq-answer">Yes, use your order tracking page or contact support for status updates.</div>
      </div>

      <div class="final-cta reveal-up">
        <div>
          <h3>Ready to Build Your Seat Setup?</h3>
          <p>Start your customization now and bring your preferred design to life.</p>
        </div>
        <a href="customize.php" class="btn-primary">Start Customizing</a>
      </div>
    </div>
  </section>

  <footer class="home-footer">
    <div class="footer-links">
      <a href="about.php">About</a>
      <a href="contact.php">Contact</a>
      <a href="support.php">Support</a>
      <a href="privacy.php">Privacy</a>
      <a href="terms.php">Terms</a>
      <a href="shipping_returns.php">Shipping & Returns</a>
    </div>
    <p>&copy; <?php echo date('Y'); ?> RBJ Accessories. All rights reserved.</p>
  </footer>

  <div id="globalToastWrap" class="global-toast-wrap" aria-live="polite" aria-atomic="true"></div>

  <?php $conn->close(); ?>

  <script>
    // FAQ toggle
    const faqQuestions = document.querySelectorAll('.faq-question');
    faqQuestions.forEach(q => {
      q.addEventListener('click', () => {
        const answer = q.nextElementSibling;
        answer.style.display = answer.style.display === 'block' ? 'none' : 'block';
      });
    });

    const revealItems = document.querySelectorAll('.reveal-up');
    if ('IntersectionObserver' in window && revealItems.length) {
      const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            revealObserver.unobserve(entry.target);
          }
        });
      }, { threshold: 0.12, rootMargin: '0px 0px -30px 0px' });

      revealItems.forEach((item, idx) => {
        item.style.transitionDelay = Math.min(idx * 45, 280) + 'ms';
        revealObserver.observe(item);
      });
    } else {
      revealItems.forEach(item => item.classList.add('is-visible'));
    }

  </script>
  
<script>
    const accountDropdown = document.querySelector('.account-dropdown');
  const accountTrigger = document.querySelector('.account-trigger');
  const accountMenu = document.querySelector('.account-menu');
  const heroEl = document.querySelector('.hero');
  const heroImage = document.querySelector('.hero-image');
  const heroText = document.querySelector('.hero-text');

  if (accountDropdown && accountTrigger && accountMenu) {
    accountTrigger.addEventListener('click', function (e) {
      e.stopPropagation();
      accountDropdown.classList.toggle('active');
    });

    accountMenu.addEventListener('click', function (e) {
      e.stopPropagation();
    });

    document.addEventListener('click', function () {
      accountDropdown.classList.remove('active');
    });
  }

  if (heroEl && heroImage && heroText) {
    heroEl.addEventListener('mousemove', (e) => {
      const rect = heroEl.getBoundingClientRect();
      const px = ((e.clientX - rect.left) / rect.width) - 0.5;
      const py = ((e.clientY - rect.top) / rect.height) - 0.5;
      heroImage.style.transform = `translate(${px * 10}px, ${py * 8}px)`;
      heroText.style.transform = `translate(${px * -8}px, ${py * -6}px)`;
    });
    heroEl.addEventListener('mouseleave', () => {
      heroImage.style.transform = '';
      heroText.style.transform = '';
    });
  }

  const pageBgCanvas = document.getElementById('pageBgCanvas');
  if (pageBgCanvas) {
    const ctx = pageBgCanvas.getContext('2d');
    let canvasW = 0;
    let canvasH = 0;
    const startedAt = performance.now();

    function resizeCanvas() {
      const dpr = Math.min(window.devicePixelRatio || 1, 2);
      canvasW = Math.max(1, Math.floor(window.innerWidth * dpr));
      canvasH = Math.max(1, Math.floor(window.innerHeight * dpr));
      pageBgCanvas.width = canvasW;
      pageBgCanvas.height = canvasH;
      pageBgCanvas.style.width = window.innerWidth + 'px';
      pageBgCanvas.style.height = window.innerHeight + 'px';
      ctx.setTransform(1, 0, 0, 1, 0, 0);
    }

    function drawCinematicGradient(now) {
      const t = (now - startedAt) * 0.00018;
      const w = canvasW;
      const h = canvasH;

      ctx.clearRect(0, 0, w, h);

      const baseGrad = ctx.createLinearGradient(0, 0, 0, h);
      baseGrad.addColorStop(0, '#0d0d0f');
      baseGrad.addColorStop(0.55, '#101013');
      baseGrad.addColorStop(1, '#09090a');
      ctx.fillStyle = baseGrad;
      ctx.fillRect(0, 0, w, h);

      const x1 = (0.2 + 0.1 * Math.sin(t * 1.2)) * w;
      const y1 = (0.2 + 0.1 * Math.cos(t * 0.9)) * h;
      const r1 = Math.max(w, h) * 0.55;
      const g1 = ctx.createRadialGradient(x1, y1, 0, x1, y1, r1);
      g1.addColorStop(0, 'rgba(217,4,41,0.22)');
      g1.addColorStop(1, 'rgba(217,4,41,0)');
      ctx.fillStyle = g1;
      ctx.fillRect(0, 0, w, h);

      const x2 = (0.78 + 0.09 * Math.cos(t * 0.8)) * w;
      const y2 = (0.18 + 0.08 * Math.sin(t * 1.1)) * h;
      const r2 = Math.max(w, h) * 0.46;
      const g2 = ctx.createRadialGradient(x2, y2, 0, x2, y2, r2);
      g2.addColorStop(0, 'rgba(255,255,255,0.08)');
      g2.addColorStop(1, 'rgba(255,255,255,0)');
      ctx.fillStyle = g2;
      ctx.fillRect(0, 0, w, h);

      const x3 = (0.5 + 0.14 * Math.sin(t * 0.7)) * w;
      const y3 = (0.82 + 0.1 * Math.cos(t * 0.6)) * h;
      const r3 = Math.max(w, h) * 0.5;
      const g3 = ctx.createRadialGradient(x3, y3, 0, x3, y3, r3);
      g3.addColorStop(0, 'rgba(90,90,110,0.12)');
      g3.addColorStop(1, 'rgba(90,90,110,0)');
      ctx.fillStyle = g3;
      ctx.fillRect(0, 0, w, h);
    }

    function animate(now) {
      drawCinematicGradient(now);
      requestAnimationFrame(animate);
    }

    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);
    requestAnimationFrame(animate);
  }

  const seatShowroom = document.getElementById('seatShowroom');
  if (seatShowroom) {
    const showroomCards = Array.from(seatShowroom.querySelectorAll('.showroom-card'));
    const showroomTitle = document.getElementById('showroomTitle');
    const showroomCounter = document.getElementById('showroomCounter');
    const showroomDotsWrap = document.getElementById('showroomDots');
    const showroomPrevBtn = document.getElementById('showroomPrevBtn');
    const showroomNextBtn = document.getElementById('showroomNextBtn');
    const rotateInterval = Math.max(1800, Number(seatShowroom.dataset.interval) || 2500);
    const dots = [];
    let activeIndex = 0;
    let timer = null;

    function circularOffset(i, center) {
      const len = showroomCards.length;
      let diff = i - center;
      if (diff > len / 2) diff -= len;
      if (diff < -len / 2) diff += len;
      return diff;
    }

    function setShowroom(nextIndex, manual) {
      if (!showroomCards.length) return;
      activeIndex = (nextIndex + showroomCards.length) % showroomCards.length;

      showroomCards.forEach((card, idx) => {
        const offset = circularOffset(idx, activeIndex);
        card.classList.remove('is-prev', 'is-active', 'is-next', 'is-hidden');
        card.style.removeProperty('--rx');
        card.style.removeProperty('--ry');

        if (offset === 0) card.classList.add('is-active');
        else if (offset === -1) card.classList.add('is-prev');
        else if (offset === 1) card.classList.add('is-next');
        else card.classList.add('is-hidden');
      });

      const activeCard = showroomCards[activeIndex];
      const activeImg = activeCard ? activeCard.querySelector('img') : null;
      if (showroomTitle) showroomTitle.textContent = activeImg ? (activeImg.alt || ('Build ' + (activeIndex + 1))) : ('Build ' + (activeIndex + 1));
      if (showroomCounter) showroomCounter.textContent = (activeIndex + 1) + ' / ' + showroomCards.length;

      dots.forEach((dot, idx) => dot.classList.toggle('active', idx === activeIndex));

      if (manual) restartAuto();
    }

    function startAuto() {
      if (timer || !showroomCards.length) return;
      timer = setInterval(() => setShowroom(activeIndex + 1, false), rotateInterval);
    }
    function stopAuto() {
      if (!timer) return;
      clearInterval(timer);
      timer = null;
    }
    function restartAuto() {
      stopAuto();
      startAuto();
    }

    if (showroomDotsWrap) {
      showroomCards.forEach((_, idx) => {
        const dot = document.createElement('button');
        dot.type = 'button';
        dot.setAttribute('aria-label', 'Show build ' + (idx + 1));
        dot.addEventListener('click', () => setShowroom(idx, true));
        showroomDotsWrap.appendChild(dot);
        dots.push(dot);
      });
    }

    showroomCards.forEach((card, idx) => {
      card.addEventListener('click', () => setShowroom(idx, true));

      card.addEventListener('mousemove', (e) => {
        if (!card.classList.contains('is-active')) return;
        const rect = card.getBoundingClientRect();
        const px = ((e.clientX - rect.left) / rect.width) - 0.5;
        const py = ((e.clientY - rect.top) / rect.height) - 0.5;
        const ry = px * 8;
        const rx = py * -6;
        card.style.transform = `translate(-50%, -50%) scale(1.08) rotateX(${rx}deg) rotateY(${ry}deg)`;
      });
      card.addEventListener('mouseleave', () => {
        if (card.classList.contains('is-active')) {
          card.style.transform = 'translate(-50%, -50%) scale(1.08) rotateY(0deg)';
        }
      });
    });

    if (showroomPrevBtn) showroomPrevBtn.addEventListener('click', () => setShowroom(activeIndex - 1, true));
    if (showroomNextBtn) showroomNextBtn.addEventListener('click', () => setShowroom(activeIndex + 1, true));

    setShowroom(0, false);
    startAuto();
  }

  const feedbackForm = document.getElementById('feedbackForm');
  const feedbackResponse = document.getElementById('feedbackResponse');
  const globalToastWrap = document.getElementById('globalToastWrap');

  function showGlobalToast(type, message) {
    if (!globalToastWrap || !message) return;
    const toast = document.createElement('div');
    toast.className = 'global-toast ' + (type === 'success' ? 'success' : 'error');
    toast.textContent = message;
    globalToastWrap.appendChild(toast);

    setTimeout(() => {
      toast.classList.add('fade-out');
      setTimeout(() => {
        if (toast.parentNode) toast.parentNode.removeChild(toast);
      }, 240);
    }, 2600);
  }

  if (feedbackForm) {
    feedbackForm.addEventListener('submit', async function (e) {
      e.preventDefault();

      const submitBtn = feedbackForm.querySelector('button[type="submit"]');
      const originalBtnText = submitBtn ? submitBtn.textContent : '';
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';
      }

      try {
        const formData = new FormData(feedbackForm);
        const res = await fetch('test_index.php', {
          method: 'POST',
          body: formData,
          credentials: 'same-origin',
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        });

        if (!res.ok) throw new Error('Request failed');
        const data = await res.json();

        if (data.ok) {
          showGlobalToast('success', data.message || 'Feedback submitted successfully.');
          feedbackForm.reset();
          if (feedbackResponse) feedbackResponse.innerHTML = '';
        } else {
          showGlobalToast('error', data.message || 'Failed to submit feedback.');
        }
      } catch (err) {
        showGlobalToast('error', 'Could not submit feedback right now. Please try again.');
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = originalBtnText;
        }
      }
    });
  }
</script>

</body>
</html>




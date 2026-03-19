<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password - RBJ Accessories</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

<style>
/* ================= RESET ================= */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  font-family: "Montserrat", sans-serif;
}

/* ================= BODY ================= */
body {
  min-height: 100vh;
  background: linear-gradient(135deg, #1b1b1b, #111);
  display: flex;
  justify-content: center;
  align-items: flex-start;
  padding-top: 100px;
  color: white;
}

/* ================= NAVBAR ================= */
.navbar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: 70px;
  background: rgba(0,0,0,0.85);
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0 50px;
  z-index: 999;
}

.logo {
  display: flex;
  align-items: center;
  gap: 10px;
  color: white;
  text-decoration: none;
  font-size: 22px;
  font-weight: 700;
}

.logo img {
  height: 60px;
  width: auto;
  background: transparent;
}

.nav-links a {
  margin-left: 20px;
  color: white;
  text-decoration: none;
  font-weight: 500;
}

.nav-links a:hover {
  text-decoration: underline;
}

/* ================= FORM WRAPPER ================= */
.wrapper {
  width: 420px;
  background: rgba(0,0,0,0.6);
  border-radius: 10px;
  padding: 30px 40px;
  backdrop-filter: blur(5px);
  text-align: center;
}

.wrapper h1 {
  font-size: 36px;
  margin-bottom: 20px;
}

/* Input box */
.input-box {
  position: relative;
  width: 100%;
  height: 50px;
  margin: 20px 0;
}

.input-box input {
  width: 100%;
  height: 100%;
  background: transparent;
  border: 2px solid rgba(255,255,255,0.5);
  border-radius: 40px;
  padding: 0 20px;
  font-size: 16px;
  color: white;
}

.input-box input::placeholder {
  color: rgba(255,255,255,0.7);
}

.input-box i {
  position: absolute;
  right: 20px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 20px;
  color: white;
}

/* Button */
.btn {
  width: 100%;
  height: 45px;
  background: #fff;
  color: #333;
  border: none;
  border-radius: 40px;
  font-weight: 600;
  cursor: pointer;
  margin-top: 10px;
  box-shadow: 0 0 10px rgba(0,0,0,0.4);
}

.btn:hover {
  background: #e6e6e6;
}

/* Back to Login link */
.back-login {
  margin-top: 15px;
  font-size: 14px;
}

.back-login a {
  color: white;
  font-weight: 600;
  text-decoration: none;
}

.back-login a:hover {
  text-decoration: underline;
}

/* ================= RESPONSIVE ================= */
@media (max-width: 500px) {
  .navbar {
    padding: 0 20px;
  }

  .wrapper {
    width: 90%;
  }
}
</style>
</head>

<body>

<nav class="navbar">
  <a href="index.php" class="logo">
    <img src="rbjlogo.png" alt="RBJ Accessories Logo">
    <span>RBJ Accessories</span>
  </a>
</nav>

<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pending_user_id = (int)($_SESSION['pending_password_reset_user_id'] ?? 0);
$pending_phone = trim((string)($_SESSION['pending_password_reset_phone'] ?? ''));
$pending_resend_at = (int)($_SESSION['pending_password_reset_resend_at'] ?? 0);
$notice = trim((string)($_SESSION['pending_password_reset_notice'] ?? ''));

unset($_SESSION['pending_password_reset_notice']);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    include 'config.php';
    require_once __DIR__ . '/verification_helper.php';
    require_once __DIR__ . '/sms_helper.php';

    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $posted_token)) {
        $error = "Invalid request. Please refresh and try again.";
    } else {
        $action = $_POST['action'] ?? 'reset';
        if ($pending_user_id <= 0 || $pending_phone === '') {
            $error = "No pending password reset was found. Please request a new code.";
        } elseif ($action === 'resend') {
            if ($pending_resend_at > time()) {
                $seconds_left = max(1, $pending_resend_at - time());
                $error = "Please wait {$seconds_left} seconds before requesting another code.";
            } else {
                $sms_result = rbj_send_sms_otp($pending_phone);
                if (!empty($sms_result['ok'])) {
                    $pending_resend_at = time() + 60;
                    $_SESSION['pending_password_reset_resend_at'] = $pending_resend_at;
                    $success = "A new password reset code was sent to " . rbj_mask_phone_number($pending_phone) . ".";
                } else {
                    $error = "Could not send SMS right now. Please try again later.";
                }
            }
        } else {
            $otp = trim((string)($_POST['otp'] ?? ''));
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if ($otp === '' || empty($password) || empty($confirm_password)) {
                $error = "All fields are required";
            } elseif (!preg_match('/^\d{6}$/', $otp)) {
                $error = "Enter the 6-digit reset code.";
            } elseif ($password !== $confirm_password) {
                $error = "Passwords do not match";
            } elseif (strlen($password) < 8) {
                $error = "Password must be at least 8 characters";
            } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password) || !preg_match('/[^a-zA-Z\d]/', $password)) {
                $error = "Password must include uppercase, lowercase, number, and special character";
            } else {
                $verify_result = rbj_verify_sms_otp($pending_phone, $otp);
                if (empty($verify_result['ok'])) {
                    $error = trim((string)($verify_result['message'] ?? 'Invalid or expired reset code.'));
                } else {
                    rbj_ensure_verification_schema($conn);
                
                    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND contact_number = ? LIMIT 1");
                    $stmt->bind_param("is", $pending_user_id, $pending_phone);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user_exists = $result->num_rows > 0;
                    $stmt->close();

                    if (!$user_exists) {
                        $error = "This password reset request is no longer valid. Please try again.";
                    } else {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmt->bind_param("si", $hashed_password, $pending_user_id);

                        if ($stmt->execute()) {
                            unset(
                                $_SESSION['pending_password_reset_user_id'],
                                $_SESSION['pending_password_reset_phone'],
                                $_SESSION['pending_password_reset_resend_at']
                            );
                            $success = "Password reset successful! Please login with your new password.";
                        } else {
                            $error = "Failed to reset password";
                        }
                        $stmt->close();
                    }
                }
            }
        }
    }

    $conn->close();
}
?>

<div class="wrapper">
  <form method="POST" action="reset_password.php">
    <h1>Reset Password</h1>

    <?php if ($notice !== ''): ?>
      <p style="color: #70e59f; text-align: center; margin-bottom: 20px;"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <?php if (isset($error)): ?>
      <p style="color: red; text-align: center; margin-bottom: 20px;"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <?php if (isset($success)): ?>
      <p style="color: green; text-align: center; margin-bottom: 20px;"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

    <div class="input-box">
      <input type="text" name="otp" placeholder="6-digit reset code" inputmode="numeric" maxlength="6">
      <i class='bx bxs-key'></i>
    </div>

    <div class="input-box">
      <input type="password" name="password" placeholder="New Password" required>
      <i class='bx bxs-lock-alt'></i>
    </div>

    <div class="input-box">
      <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
      <i class='bx bxs-lock'></i>
    </div>

    <button type="submit" name="action" value="reset" class="btn">Reset Password</button>
    <button type="submit" name="action" value="resend" class="btn" style="margin-top:12px;background:transparent;color:white;border:1px solid rgba(255,255,255,0.5);">Resend Code</button>

    <div class="back-login">
      <p>Remembered your password? <a href="login.php">Login</a></p>
    </div>
  </form>
</div>

</body>
</html>

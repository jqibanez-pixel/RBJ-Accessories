<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify Mobile Number - RBJ Accessories</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  font-family: "Montserrat", sans-serif;
}

body {
  min-height: 100vh;
  background: linear-gradient(135deg, #1b1b1b, #111);
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 30px 16px;
  color: white;
}

.wrapper {
  width: min(94vw, 620px);
  background: rgba(0,0,0,0.65);
  border-radius: 10px;
  padding: 30px 34px;
  text-align: center;
}

.wrapper h1 {
  font-size: 34px;
  margin-bottom: 12px;
}

.wrapper p {
  line-height: 1.55;
  margin-bottom: 18px;
}

.status {
  margin-bottom: 14px;
  font-size: 14px;
}

.status.error {
  color: #ff8f8f;
}

.status.success {
  color: #70e59f;
}

.input-box {
  position: relative;
  width: 100%;
  height: 52px;
  margin: 20px 0;
}

.input-box input {
  width: 100%;
  height: 100%;
  background: transparent;
  border: 2px solid rgba(255,255,255,0.45);
  border-radius: 40px;
  padding: 0 20px;
  font-size: 16px;
  color: white;
  letter-spacing: 0.18em;
  text-align: center;
}

.input-box input::placeholder {
  color: rgba(255,255,255,0.65);
  letter-spacing: 0.08em;
}

.actions {
  display: flex;
  gap: 12px;
  justify-content: center;
  flex-wrap: wrap;
}

.btn {
  min-width: 170px;
  height: 45px;
  padding: 0 20px;
  background: #fff;
  color: #222;
  border: none;
  border-radius: 40px;
  font-weight: 700;
  cursor: pointer;
}

.btn.secondary {
  background: transparent;
  color: white;
  border: 1px solid rgba(255,255,255,0.4);
}

.links {
  margin-top: 18px;
}

.links a {
  color: white;
  text-decoration: none;
  margin: 0 8px;
}

.links a:hover {
  text-decoration: underline;
}

</style>
</head>
<body>
<?php
session_start();
include 'config.php';
require_once __DIR__ . '/verification_helper.php';
require_once __DIR__ . '/sms_helper.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';
$notice = trim((string)($_SESSION['pending_sms_verification_notice'] ?? ''));
$sms_error = trim((string)($_SESSION['pending_sms_verification_error'] ?? ''));
$pending_user_id = (int)($_SESSION['pending_sms_verification_user_id'] ?? 0);
$pending_phone = trim((string)($_SESSION['pending_sms_verification_phone'] ?? ''));
$pending_resend_at = (int)($_SESSION['pending_sms_resend_available_at'] ?? 0);

unset($_SESSION['pending_sms_verification_notice'], $_SESSION['pending_sms_verification_error']);

rbj_ensure_verification_schema($conn);

if ($pending_user_id <= 0 || $pending_phone === '') {
    $error = 'No pending SMS verification was found. Please register again.';
} else {
    $stmt = $conn->prepare("
        SELECT id, username, email, contact_number, sms_verified_at, is_verified
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $pending_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc() ?: null;
    $stmt->close();

    if (!$user) {
        $error = 'The pending account was not found. Please register again.';
    } elseif (rbj_user_is_verified($user)) {
        unset(
            $_SESSION['pending_sms_verification_user_id'],
            $_SESSION['pending_sms_verification_phone'],
            $_SESSION['pending_sms_resend_available_at']
        );
        $success = 'Your account is already verified. You can log in now.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $posted_token = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'], $posted_token)) {
            $error = 'Invalid request. Please refresh and try again.';
        } else {
            $action = $_POST['action'] ?? 'verify';
            if ($action === 'resend') {
                if ($pending_resend_at > time()) {
                    $seconds_left = max(1, $pending_resend_at - time());
                    $error = 'Please wait ' . $seconds_left . ' seconds before requesting another code.';
                } else {
                    $sms_result = rbj_send_sms_otp($pending_phone);
                    if (!empty($sms_result['ok'])) {
                        $pending_resend_at = time() + 60;
                        $_SESSION['pending_sms_resend_available_at'] = $pending_resend_at;
                        $success = 'A new verification code was sent to ' . rbj_mask_phone_number($pending_phone) . '.';
                        $sms_error = '';
                    } else {
                        $error = 'Could not send SMS right now. Please try again later.';
                        $sms_error = trim((string)($sms_result['message'] ?? 'SMS sending failed.'));
                    }
                }
            } else {
                $input_code = trim((string)($_POST['verification_code'] ?? ''));
                if (!preg_match('/^\d{6}$/', $input_code)) {
                    $error = 'Enter the 6-digit verification code.';
                } else {
                    $verify_result = rbj_verify_sms_otp($pending_phone, $input_code);
                    if (empty($verify_result['ok'])) {
                        $error = trim((string)($verify_result['message'] ?? 'Invalid or expired verification code.'));
                    } else {
                        $stmt = $conn->prepare("UPDATE users SET sms_verified_at = NOW(), is_verified = 1 WHERE id = ?");
                        $stmt->bind_param("i", $pending_user_id);
                        if ($stmt->execute()) {
                            $stmt->close();
                            unset(
                                $_SESSION['pending_sms_verification_user_id'],
                                $_SESSION['pending_sms_verification_phone'],
                                $_SESSION['pending_sms_resend_available_at']
                            );
                            $success = 'Mobile number verified successfully. You can log in now.';
                        } else {
                            $stmt->close();
                            $error = 'Could not complete verification. Please try again.';
                        }
                    }
                }
            }
        }
    }
}

$conn->close();
?>

<div class="wrapper">
  <h1>Verify Mobile</h1>
  <p>
    Enter the 6-digit code sent to
    <strong><?php echo htmlspecialchars(rbj_mask_phone_number($pending_phone), ENT_QUOTES, 'UTF-8'); ?></strong>.
  </p>

  <?php if ($notice !== ''): ?>
    <p class="status success"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>

  <?php if ($success !== ''): ?>
    <p class="status success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>

  <?php if ($error !== ''): ?>
    <p class="status error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>

  <?php if ($sms_error !== ''): ?>
    <p class="status error"><?php echo htmlspecialchars($sms_error, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>

  <?php if ($success === ''): ?>
    <form method="POST" action="verify_sms.php">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

      <div class="input-box">
        <input type="text" name="verification_code" placeholder="123456" inputmode="numeric" maxlength="6" pattern="\d{6}" required>
      </div>

      <div class="actions">
        <button type="submit" name="action" value="verify" class="btn">Verify Code</button>
        <button type="submit" name="action" value="resend" class="btn secondary">Resend Code</button>
      </div>
    </form>
  <?php endif; ?>

  <div class="links">
    <a href="login.php">Login</a>
    <a href="register.php">Register</a>
  </div>
</div>
</body>
</html>

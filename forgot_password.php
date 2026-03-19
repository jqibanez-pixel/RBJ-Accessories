<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password</title>
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
  align-items: center;
  padding: 30px 16px;
  color: white;
}

/* ================= BRAND HEADER ================= */
.auth-stack {
  width: min(94vw, 620px);
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.brand-panel {
  width: 100%;
  background: rgba(0,0,0,0.82);
  border: 1px solid rgba(255,255,255,0.14);
  box-shadow: 0 10px 24px rgba(0,0,0,0.35);
  border-radius: 10px;
  padding: 16px 22px;
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: center;
  gap: 14px;
}

.brand-panel::after {
  content: "";
  width: 72px;
}

.brand-panel img {
  height: 62px;
  width: auto;
  flex-shrink: 0;
}

.brand-title {
  font-size: 30px;
  font-weight: 800;
  letter-spacing: 0.12em;
  line-height: 1.2;
  text-transform: uppercase;
  text-shadow: 0 2px 8px rgba(0,0,0,0.35);
  text-align: center;
  justify-self: center;
}

/* ================= FORM WRAPPER ================= */
.wrapper {
  width: 100%;
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
  .auth-stack {
    width: min(95vw, 620px);
  }

  .brand-panel {
    padding: 14px 16px;
    gap: 10px;
  }

  .brand-panel::after {
    width: 52px;
  }

  .brand-panel img {
    height: 52px;
  }

  .brand-title {
    font-size: 20px;
    letter-spacing: 0.06em;
  }

  .wrapper {
    padding: 26px 24px;
  }
}
</style>
</head>

<body>

<div class="auth-stack">
  <div class="brand-panel" role="banner" aria-label="RBJ Header">
    <img src="rbjlogo.png" alt="RBJ Accessories Logo">
    <span class="brand-title">RBJ Accessories</span>
  </div>

<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    include 'config.php';
    require_once __DIR__ . '/verification_helper.php';
    require_once __DIR__ . '/sms_helper.php';

    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $posted_token)) {
        $error = "Invalid request. Please refresh and try again.";
    } else {
        $contact_number = trim($_POST['contact_number'] ?? '');
        $normalized_contact_number = rbj_normalize_phone_number($contact_number);

        if ($contact_number === '') {
            $error = "Mobile number is required";
        } elseif ($normalized_contact_number === null) {
            $error = "Please enter a valid Philippine mobile number";
        } else {
            rbj_ensure_verification_schema($conn);
            $success = "If your mobile number exists in our system, a password reset code has been sent.";

            $stmt = $conn->prepare("SELECT id, contact_number FROM users WHERE contact_number = ? LIMIT 1");
            $stmt->bind_param("s", $normalized_contact_number);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $sms_result = rbj_send_sms_otp((string)$user['contact_number']);
                if (!empty($sms_result['ok'])) {
                    $_SESSION['pending_password_reset_user_id'] = (int)$user['id'];
                    $_SESSION['pending_password_reset_phone'] = (string)$user['contact_number'];
                    $_SESSION['pending_password_reset_resend_at'] = time() + 60;
                    $_SESSION['pending_password_reset_notice'] = "Enter the password reset code sent to " . rbj_mask_phone_number((string)$user['contact_number']) . ".";
                    header("Location: reset_password.php");
                    exit();
                } else {
                    $error = "Could not send SMS right now. Please try again later.";
                    unset($success);
                }
            }

            $stmt->close();
        }
    }

    $conn->close();
}
?>

<div class="wrapper">
  <form method="POST" action="forgot_password.php">
    <h1>Forgot Password</h1>

    <?php if (isset($error)): ?>
      <p style="color: red; text-align: center; margin-bottom: 20px;"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <?php if (isset($success)): ?>
      <p style="color: green; text-align: center; margin-bottom: 20px;"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

    <div class="input-box">
      <input type="text" name="contact_number" placeholder="Enter your mobile number" required>
      <i class='bx bxs-phone'></i>
    </div>

    <button type="submit" class="btn">Reset Password</button>

    <div class="back-login">
      <p>Remembered your password? <a href="login.php">Login</a></p>
    </div>
  </form>
</div>
</div>

</body>
</html>

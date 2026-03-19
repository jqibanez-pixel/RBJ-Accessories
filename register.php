<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register - RBJ Accessories</title>
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

/* Brand Header */
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
  line-height: 1.2;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  text-shadow: 0 2px 8px rgba(0,0,0,0.35);
  text-align: center;
  justify-self: center;
}

/* Form */
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

.input-box > i {
  position: absolute;
  right: 20px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 20px;
}

.password-box input {
  padding-right: 52px;
}

.password-toggle {
  position: absolute;
  right: 20px;
  top: 50%;
  transform: translateY(-50%);
  background: transparent;
  border: none;
  color: rgba(255,255,255,0.9);
  cursor: pointer;
  font-size: 20px;
  line-height: 1;
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0;
}

.password-toggle i {
  position: static;
  transform: none;
  font-size: 20px;
  line-height: 1;
  pointer-events: none;
}

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
}

.btn:hover {
  background: #e6e6e6;
}

.register-link {
  margin-top: 15px;
  font-size: 14px;
}

.register-link a {
  color: white;
  font-weight: 600;
  text-decoration: none;
}

.register-link a:hover {
  text-decoration: underline;
}

.toast {
  position: fixed;
  top: 22px;
  right: 22px;
  z-index: 2000;
  min-width: 280px;
  max-width: 420px;
  padding: 12px 14px;
  border-radius: 10px;
  color: #fff;
  font-size: 14px;
  box-shadow: 0 10px 24px rgba(0,0,0,0.35);
  border: 1px solid rgba(255,255,255,0.18);
}

.toast.success { background: rgba(26, 130, 66, 0.92); }
.toast.warning { background: rgba(178, 112, 18, 0.92); }
.toast.error { background: rgba(156, 38, 38, 0.92); }

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

$error = '';
$form_username = '';
$form_contact_number = '';

function rbj_build_internal_email(string $normalized_contact_number): string {
    $phone_digits = preg_replace('/\D/', '', $normalized_contact_number) ?? '';
    return 'smsuser.' . $phone_digits . '@rbj.local';
}

function rbj_register_rate_limited(): bool {
    $window_seconds = 15 * 60;
    $max_attempts = 6;
    $now = time();

    if (!isset($_SESSION['register_rate_limit'])) {
        $_SESSION['register_rate_limit'] = ['count' => 0, 'window_start' => $now];
    }

    $rate = $_SESSION['register_rate_limit'];
    if (($now - (int)$rate['window_start']) > $window_seconds) {
        $rate = ['count' => 0, 'window_start' => $now];
    }

    if ((int)$rate['count'] >= $max_attempts) {
        $_SESSION['register_rate_limit'] = $rate;
        return true;
    }

    $rate['count'] = (int)$rate['count'] + 1;
    $_SESSION['register_rate_limit'] = $rate;
    return false;
}

function rbj_register_rate_reset(): void {
    $_SESSION['register_rate_limit'] = ['count' => 0, 'window_start' => time()];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    include 'config.php';
    require_once __DIR__ . '/verification_helper.php';
    require_once __DIR__ . '/sms_helper.php';
    $stmt = null;

    $form_username = trim($_POST['username'] ?? '');
    $form_contact_number = trim($_POST['contact_number'] ?? '');

    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $posted_token)) {
        $error = "Invalid request. Please refresh and try again.";
    } elseif (rbj_register_rate_limited()) {
        $error = "Too many registration attempts. Please wait 15 minutes and try again.";
    } else {
        $username = $form_username;
        $contact_number = $form_contact_number;
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $normalized_contact_number = rbj_normalize_phone_number($contact_number);
        $email = $normalized_contact_number !== null ? rbj_build_internal_email($normalized_contact_number) : '';

        // Validate input
        if (empty($username) || empty($contact_number) || empty($password) || empty($confirm_password)) {
            $error = "All fields are required";
        } elseif ($normalized_contact_number === null) {
            $error = "Please enter a valid Philippine mobile number";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match";
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters";
        } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password) || !preg_match('/[^a-zA-Z\d]/', $password)) {
            $error = "Password must include uppercase, lowercase, number, and special character";
        } else {
            rbj_ensure_verification_schema($conn);

            // Check if username, internal email, or contact number already exists.
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ? OR contact_number = ?");
            $stmt->bind_param("sss", $username, $email, $normalized_contact_number);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $error = "Username or mobile number already exists";
            } else {
                $role = 'user';

                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare("
                        INSERT INTO users (
                            username,
                            email,
                            password,
                            role,
                            contact_number,
                            sms_verified_at,
                            is_verified
                        ) VALUES (?, ?, ?, ?, ?, NULL, 0)
                    ");
                    $stmt->bind_param("sssss", $username, $email, $hashed_password, $role, $normalized_contact_number);
                    if (!$stmt->execute()) {
                        throw new RuntimeException('Failed to create user.');
                    }
                    $user_id = (int)$stmt->insert_id;

                    $conn->commit();

                    $sms_result = rbj_send_sms_otp($normalized_contact_number);
                    $sms_sent = !empty($sms_result['ok']);

                    $_SESSION['pending_sms_verification_user_id'] = $user_id;
                    $_SESSION['pending_sms_verification_phone'] = $normalized_contact_number;
                    $_SESSION['pending_sms_resend_available_at'] = $sms_sent ? (time() + 60) : time();
                    $_SESSION['pending_sms_verification_notice'] = $sms_sent
                        ? "Account created. Enter the code sent to " . rbj_mask_phone_number($normalized_contact_number) . "."
                        : "Account created, but SMS sending failed. You can resend the code from the verification page.";
                    $_SESSION['pending_sms_verification_error'] = $sms_sent ? '' : trim((string)($sms_result['message'] ?? 'SMS sending failed.'));

                    rbj_register_rate_reset();
                    $form_username = '';
                    $form_contact_number = '';
                    header("Location: verify_sms.php");
                    exit();
                } catch (Throwable $e) {
                    $conn->rollback();
                    $error = "Registration failed. Please try again.";
                }
            }

            if ($stmt instanceof mysqli_stmt) {
                $stmt->close();
            }
        }
    }

    $conn->close();
}
?>

<div class="wrapper">
  <form method="POST" action="register.php">
    <h1>Register</h1>

    <?php if (!empty($error)): ?>
      <p style="color: red; text-align: center;"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

    <div class="input-box">
      <input type="text" name="username" placeholder="Username" value="<?php echo htmlspecialchars($form_username, ENT_QUOTES, 'UTF-8'); ?>" required>
      <i class='bx bxs-user'></i>
    </div>

    <div class="input-box">
      <input type="text" name="contact_number" placeholder="Mobile Number (09xxxxxxxxx)" value="<?php echo htmlspecialchars($form_contact_number, ENT_QUOTES, 'UTF-8'); ?>" required>
      <i class='bx bxs-phone'></i>
    </div>

    <div class="input-box password-box">
      <input type="password" id="registerPassword" name="password" placeholder="Password" autocomplete="new-password" required>
      <button type="button" class="password-toggle" data-toggle-password="registerPassword" aria-label="Show password">
        <i class='bx bx-show'></i>
      </button>
    </div>

    <div class="input-box password-box">
      <input type="password" id="registerConfirmPassword" name="confirm_password" placeholder="Confirm Password" autocomplete="new-password" required>
      <button type="button" class="password-toggle" data-toggle-password="registerConfirmPassword" aria-label="Show password">
        <i class='bx bx-show'></i>
      </button>
    </div>

    <button type="submit" class="btn">Register</button>

    <div class="register-link">
      <p>Already have an account? <a href="login.php">Login</a></p>
    </div>
  </form>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const toggles = document.querySelectorAll('.password-toggle[data-toggle-password]');
  toggles.forEach(function (toggleBtn) {
    const targetId = toggleBtn.getAttribute('data-toggle-password');
    const input = document.getElementById(targetId);
    const icon = toggleBtn.querySelector('i');
    if (!input || !icon) return;

    toggleBtn.addEventListener('click', function () {
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      icon.className = show ? 'bx bx-hide' : 'bx bx-show';
      toggleBtn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
  });

});
</script>

</body>
</html>

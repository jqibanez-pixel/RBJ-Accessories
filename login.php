<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - RBJ Accessories</title>
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
  letter-spacing: 0.12em;
  text-transform: uppercase;
  text-shadow: 0 2px 8px rgba(0,0,0,0.35);
  text-align: center;
  justify-self: center;
}

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

.remember-forgot {
  display: flex;
  justify-content: space-between;
  font-size: 14px;
  margin-bottom: 15px;
}

.remember-forgot a {
  color: white;
  text-decoration: none;
}

.remember-forgot a:hover {
  text-decoration: underline;
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
$show_verify_sms_link = false;

function rbj_login_rate_limited(): bool {
    $window_seconds = 15 * 60;
    $max_attempts = 8;
    $now = time();

    if (!isset($_SESSION['login_rate_limit'])) {
        $_SESSION['login_rate_limit'] = ['count' => 0, 'window_start' => $now];
    }

    $rate = $_SESSION['login_rate_limit'];
    if (($now - (int)$rate['window_start']) > $window_seconds) {
        $rate = ['count' => 0, 'window_start' => $now];
    }

    if ((int)$rate['count'] >= $max_attempts) {
        $_SESSION['login_rate_limit'] = $rate;
        return true;
    }

    $rate['count'] = (int)$rate['count'] + 1;
    $_SESSION['login_rate_limit'] = $rate;
    return false;
}

function rbj_login_rate_reset(): void {
    $_SESSION['login_rate_limit'] = ['count' => 0, 'window_start' => time()];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    include 'config.php';
    require_once __DIR__ . '/verification_helper.php';

    $form_username = trim($_POST['username'] ?? '');
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $posted_token)) {
        $error = "Invalid request. Please refresh and try again.";
    } elseif (rbj_login_rate_limited()) {
        $error = "Too many login attempts. Please wait 15 minutes and try again.";
    } else {
        $username = $form_username;
        $password = $_POST['password'] ?? '';

        // Validate input
        if (empty($username) || empty($password)) {
            $error = "All fields are required";
        } else {
            rbj_ensure_verification_schema($conn);

            // Check if user exists
            $stmt = $conn->prepare("SELECT id, username, email, contact_number, password, role, sms_verified_at, is_verified FROM users WHERE username = ? OR email = ?");
            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    $is_verified = rbj_user_is_verified($user);
                    if (!in_array(($user['role'] ?? 'user'), ['admin', 'superadmin'], true) && !$is_verified) {
                        $contact_number = trim((string)($user['contact_number'] ?? ''));
                        if ($contact_number === '') {
                            $error = "This account is not verified and does not have a mobile number on file. Please contact admin or register again.";
                        } else {
                            $_SESSION['pending_sms_verification_user_id'] = (int)$user['id'];
                            $_SESSION['pending_sms_verification_phone'] = $contact_number;
                            $error = "Please verify your mobile number first before logging in.";
                            $show_verify_sms_link = true;
                        }
                    } else {
                    // Login successful
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    rbj_login_rate_reset();

                    if (in_array(($user['role'] ?? 'user'), ['admin', 'superadmin'], true)) {
                        $_SESSION['admin'] = true;
                        header("Location: ADMIN/dashboard_admin.php");
                    } else {
                        $_SESSION['user'] = true;
                        header("Location: USER/index.php");
                    }
                    exit();
                    }
                } else {
                    $error = "Invalid username/email or password";
                }
            } else {
                $error = "Invalid username/email or password";
            }

            $stmt->close();
        }
    }

    $conn->close();
}
?>

    <div class="wrapper">
  <form method="POST" action="login.php">
    <h1>Login</h1>

    <?php if (!empty($error)): ?>
      <p style="color: red; text-align: center; margin-bottom: 20px;"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php if ($show_verify_sms_link): ?>
        <p style="text-align: center; margin-bottom: 20px;"><a href="verify_sms.php" style="color: white; text-decoration: underline;">Open SMS verification page</a></p>
      <?php endif; ?>
    <?php endif; ?>
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

    <div class="input-box">
      <input type="text" name="username" placeholder="Username or Email" value="<?php echo htmlspecialchars($form_username, ENT_QUOTES, 'UTF-8'); ?>" required>
      <i class='bx bxs-user'></i>
    </div>

    <div class="input-box password-box">
      <input type="password" id="loginPassword" name="password" placeholder="Password" autocomplete="current-password" required>
      <button type="button" class="password-toggle" data-toggle-password="loginPassword" aria-label="Show password">
        <i class='bx bx-show'></i>
      </button>
    </div>

    <div class="remember-forgot">
      <span></span>
      <a href="forgot_password.php">Forgot Password?</a>
    </div>

    <button type="submit" class="btn">Login</button>

    <div class="register-link">
      <p>Don't have an account? <a href="register.php">Register</a></p>
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

<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle adding to favorites
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_favorite'])) {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf_token, $posted_token)) {
        http_response_code(400);
        die("Invalid request token.");
    }
    $customization_name = trim($_POST['customization_name']);
    $customization_details = trim($_POST['customization_details']);

    if (!empty($customization_name)) {
        $stmt = $conn->prepare("INSERT INTO favorites (user_id, customization_name, customization_details) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $customization_name, $customization_details);
        $stmt->execute();
        $stmt->close();
        $success = "Added to favorites!";
    }
}

// Handle removing from favorites
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['remove_favorite'])) {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf_token, $posted_token)) {
        http_response_code(400);
        die("Invalid request token.");
    }
    $favorite_id = (int)($_POST['favorite_id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM favorites WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $favorite_id, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: favorites.php");
    exit();
}

// Fetch user's favorites
$stmt = $conn->prepare("SELECT * FROM favorites WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$favorites = $stmt->get_result();
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Favorites - RBJ Accessories</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<style>
* { box-sizing: border-box; }
body { min-height:100vh; background: linear-gradient(135deg,#1b1b1b,#111); color:white; padding-top:100px; font-family:"Montserrat",sans-serif; }
.navbar { position:fixed; top:0; left:0; right:0; display:flex; justify-content:space-between; align-items:center; padding:10px 50px; background: rgba(0,0,0,0.8); z-index:999; }
.navbar .logo { display:flex; align-items:center; gap:10px; color:white; text-decoration:none; font-size:22px; font-weight:700; }
.navbar .logo img { height: 60px; width:auto; }
.navbar .nav-links { display:flex; align-items:center; gap:15px; }
.navbar .nav-links a { color:white; text-decoration:none; font-weight:500; margin-left:15px; }
.navbar .nav-links a:hover { text-decoration:underline; }

.account-dropdown { display:flex; align-items:center; margin-left:15px; }
.account-icon { width:40px; height:40px; background:#27ae60; border-radius:50%; display:flex; justify-content:center; align-items:center; font-weight:bold; margin-right:5px; }
.account-username { font-weight:600; color:white; }

.account-dropdown { position: relative; display: flex; align-items: center; }
.account-trigger { background: none; border: none; color: white; display: flex; align-items: center; gap: 8px; cursor: pointer; }
.account-menu { position: absolute; top: 110%; right: 0; background: #1e1e1e; border-radius: 10px; min-width: 200px; padding: 8px 0; display: none; box-shadow: 0 10px 30px rgba(0,0,0,0.4); z-index: 999; }
.account-menu a { display: flex; align-items: center; gap: 10px; padding: 10px 15px; color: white; text-decoration: none; font-size: 14px; }
.account-menu a:hover { background: rgba(255,255,255,0.08); }
.account-dropdown.active .account-menu { display: block; }

.wrapper { max-width:1120px; margin:auto; padding:24px 20px 34px; }
.wrapper h1 { text-align:center; margin-bottom:10px; font-size:34px; letter-spacing:0.2px; }
.favorites-subtitle { text-align:center; color: rgba(255,255,255,0.75); margin-bottom: 24px; font-size: 14px; }

.favorites-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:18px; margin-top:24px; }
.favorite-card {
  background: rgba(0,0,0,0.6);
  border-radius: 12px;
  padding: 18px;
  border: 1px solid rgba(255,255,255,0.1);
  display: flex;
  flex-direction: column;
  min-height: 220px;
}
.favorite-card h3 { margin-bottom: 10px; color: #ef233c; font-size: 18px; line-height: 1.25; }
.favorite-card p { margin: 5px 0; font-size: 14px; line-height: 1.5; color: rgba(255,255,255,0.92); }
.favorite-card .date { color: rgba(255,255,255,0.7); font-size: 12px; }
.favorite-card .remove-btn { background: #ef233c; color: white; border: none; padding: 8px 12px; border-radius: 7px; cursor: pointer; margin-top: auto; align-self: flex-start; }
.favorite-card .remove-btn:hover { background: #b80721; }

.add-favorite {
  background: rgba(0,0,0,0.6);
  padding: 26px 22px;
  border-radius: 12px;
  margin: 0 auto 28px;
  max-width: 760px;
  border: 1px solid rgba(255,255,255,0.1);
}
.add-favorite h2 { text-align: center; margin-bottom: 6px; color: #fff; font-size: 24px; }
.add-favorite .hint { text-align: center; margin: 0 0 16px; font-size: 13px; color: rgba(255,255,255,0.72); }
.add-favorite form {
  max-width: 700px;
  margin: 0 auto 0 0;
}
.input-box { position: relative; width: 100%; margin: 16px 0; }
.input-box input,
.input-box textarea {
  display: block;
  width: 100%;
  max-width: 100%;
  background: transparent;
  border: 2px solid rgba(255,255,255,0.5);
  padding: 13px 16px;
  font-size: 16px;
  color: white;
}
.input-box input {
  height: 48px;
  border-radius: 28px;
}
.input-box input::placeholder, .input-box textarea::placeholder { color: rgba(255,255,255,0.7); }
.input-box textarea { min-height: 110px; border-radius: 10px; resize: vertical; }
.btn { width: 100%; height: 45px; background: #fff; color: #333; border: none; border-radius: 40px; font-weight: 600; cursor: pointer; margin-top: 10px; }
.btn:hover { background: #e6e6e6; }
.success { color: #27ae60; text-align: center; margin-bottom: 20px; }

.empty-favorites {
  text-align: center;
  padding: 44px 26px;
  color: rgba(255,255,255,0.7);
  background: rgba(0,0,0,0.5);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 12px;
  max-width: 760px;
  margin: 10px auto 0;
}
.empty-favorites i { font-size: 48px; margin-bottom: 20px; display: block; }

html[data-theme="light"] .add-favorite,
html[data-theme="light"] .favorite-card {
  background: var(--rbj-surface, rgba(255,255,255,0.92));
  border: 1px solid var(--rbj-border, rgba(217,4,41,0.22));
  color: var(--rbj-text, #7a211b);
}

html[data-theme="light"] .add-favorite h2,
html[data-theme="light"] .favorite-card h3,
html[data-theme="light"] .success {
  color: var(--rbj-accent-strong, #ef233c);
}

html[data-theme="light"] .favorite-card p,
html[data-theme="light"] .favorite-card .date,
html[data-theme="light"] .empty-favorites,
html[data-theme="light"] .favorites-subtitle,
html[data-theme="light"] .add-favorite .hint {
  color: var(--rbj-muted, #9f4b43);
}

html[data-theme="light"] .input-box input,
html[data-theme="light"] .input-box textarea {
  background: #fff;
  color: var(--rbj-text, #7a211b);
  border-color: var(--rbj-border, rgba(217,4,41,0.22));
}

html[data-theme="light"] .input-box input::placeholder,
html[data-theme="light"] .input-box textarea::placeholder {
  color: #ad6a65;
}

html[data-theme="light"] .empty-favorites {
  background: var(--rbj-surface, rgba(255,255,255,0.92));
  border-color: var(--rbj-border, rgba(217,4,41,0.22));
}

@media(max-width:700px){
  .navbar{padding:10px 20px;}
  .favorites-grid{grid-template-columns:1fr;}
  .wrapper { padding: 20px 12px 28px; }
  .add-favorite { padding: 20px 14px; }
  .wrapper h1 { font-size: 30px; }
}
</style>
<?php include __DIR__ . '/partials/user_navbar_theme.php'; ?>
</head>
<body>

<nav class="navbar">
  <a href="index.php" class="logo">
    <img src="../rbjlogo.png" alt="RBJ Accessories Logo">
    <span>RBJ Accessories</span>
  </a>
  <div class="nav-links">
    <a href="index.php">Home</a>
    <a href="catalog.php">Shop</a>
    <a href="customize.php">Customize</a>
    <a href="cart.php" class="nav-cart-link" title="Cart" aria-label="Cart"><i class='bx bx-cart'></i></a>
    <?php include __DIR__ . '/partials/account_menu.php'; ?>
  </div>
</nav>

<div class="wrapper">
  <h1>My Favorites</h1>
  <p class="favorites-subtitle">Save your preferred RBJ builds and keep them ready for your next order.</p>

  <!-- Add Favorite Form -->
  <div class="add-favorite">
    <h2>Add New Favorite</h2>
    <p class="hint">Add a title and optional details so your saved setup is easier to revisit.</p>

    <?php if (isset($success)): ?>
      <p class="success"><?php echo $success; ?></p>
    <?php endif; ?>

    <form method="POST" action="favorites.php">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
      <div class="input-box">
        <input type="text" name="customization_name" placeholder="Customization Name" required>
      </div>

      <div class="input-box">
        <textarea name="customization_details" placeholder="Customization Details (optional)"></textarea>
      </div>

      <button type="submit" name="add_favorite" class="btn">Add to Favorites</button>
    </form>
  </div>

  <!-- Favorites Grid -->
  <?php if ($favorites->num_rows > 0): ?>
    <div class="favorites-grid">
      <?php while ($favorite = $favorites->fetch_assoc()): ?>
        <div class="favorite-card">
          <h3><?php echo htmlspecialchars($favorite['customization_name']); ?></h3>
          <?php if (!empty($favorite['customization_details'])): ?>
            <p><?php echo htmlspecialchars($favorite['customization_details']); ?></p>
          <?php else: ?>
            <p>No extra details saved for this favorite.</p>
          <?php endif; ?>
          <p class="date">Added: <?php echo date('M j, Y', strtotime($favorite['created_at'])); ?></p>
          <form method="POST" action="favorites.php" onsubmit="return confirm('Are you sure you want to remove this from favorites?');">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="favorite_id" value="<?php echo (int)$favorite['id']; ?>">
            <button class="remove-btn" type="submit" name="remove_favorite">Remove</button>
          </form>
        </div>
      <?php endwhile; ?>
    </div>
  <?php else: ?>
    <div class="empty-favorites">
      <i class='bx bx-heart'></i>
      <p>No favorites yet. Start customizing and save your favorite designs!</p>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/partials/user_footer.php'; ?>

</body>
</html>












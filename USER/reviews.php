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

// Handle review submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_review'])) {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf_token, $posted_token)) {
        http_response_code(400);
        die("Invalid request token.");
    }
    $order_id = (int)$_POST['order_id'];
    $rating = (int)$_POST['rating'];
    $review_text = trim($_POST['review_text']);

    // Verify the order belongs to the user and is completed
    $stmt = $conn->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ? AND status = 'completed'");
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $order_result = $stmt->get_result();

    if ($order_result->num_rows > 0 && $rating >= 1 && $rating <= 5) {
        // Check if review already exists
        $stmt = $conn->prepare("SELECT id FROM reviews WHERE user_id = ? AND order_id = ?");
        $stmt->bind_param("ii", $user_id, $order_id);
        $stmt->execute();
        $existing_review = $stmt->get_result();

        if ($existing_review->num_rows == 0) {
            // Insert new review
            $stmt = $conn->prepare("INSERT INTO reviews (user_id, order_id, rating, review_text) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiis", $user_id, $order_id, $rating, $review_text);
            $stmt->execute();
            $success = "Review submitted successfully!";
        } else {
            $error = "You have already reviewed this order.";
        }
        $stmt->close();
    } else {
        $error = "Invalid order or rating.";
    }
}

// Fetch completed orders that can be reviewed
$stmt = $conn->prepare("
    SELECT o.id, o.customization, o.created_at,
           r.rating, r.review_text, r.created_at as review_date
    FROM orders o
    LEFT JOIN reviews r ON o.id = r.order_id AND r.user_id = ?
    WHERE o.user_id = ? AND o.status = 'completed'
    ORDER BY o.created_at DESC
");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$completed_orders = $stmt->get_result();
$stmt->close();

// Fetch user's reviews
$stmt = $conn->prepare("
    SELECT r.*, o.customization
    FROM reviews r
    JOIN orders o ON r.order_id = o.id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_reviews = $stmt->get_result();
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Reviews - RBJ Accessories</title>
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

.wrapper { max-width:1200px; margin:auto; padding:24px 20px 34px; }
.wrapper h1 { text-align:center; margin-bottom:10px; font-size:34px; letter-spacing:0.2px; }
.reviews-subtitle { text-align:center; margin-bottom:26px; color: rgba(255,255,255,0.74); font-size: 14px; }

.reviews-section { display:grid; grid-template-columns:1.25fr .75fr; gap:22px; margin-top:26px; align-items: start; }
.review-card {
  background: rgba(0,0,0,0.6);
  border-radius: 12px;
  padding: 18px;
  border: 1px solid rgba(255,255,255,0.1);
  margin-bottom: 14px;
}
.review-card h3 { margin-bottom: 10px; color: #ef233c; line-height: 1.3; }
.review-card .rating { color: #f39c12; margin-bottom: 10px; }
.review-card .stars { display: flex; gap: 2px; }
.review-card .star { font-size: 16px; }
.review-card .star.filled { color: #f39c12; }
.review-card .star.empty { color: rgba(255,255,255,0.3); }
.review-card p { margin: 5px 0; font-size: 14px; }
.review-card .date { color: rgba(255,255,255,0.7); font-size: 12px; }

.review-form {
  background: rgba(0,0,0,0.6);
  padding: 22px 20px;
  border-radius: 12px;
  margin-bottom: 16px;
  border: 1px solid rgba(255,255,255,0.1);
}
.review-form h3 {
  margin: 0 0 8px;
  color: #fff;
  font-size: 17px;
  line-height: 1.3;
}
.order-date {
  font-size: 12px;
  color: rgba(255,255,255,0.68);
  margin-bottom: 10px;
}
.rating-label {
  text-align: center;
  font-size: 13px;
  color: rgba(255,255,255,0.78);
  margin-bottom: 6px;
}
.rating-input { display: flex; justify-content: center; gap: 8px; margin: 10px 0 14px; }
.rating-input .star { font-size: 30px; color: rgba(255,255,255,0.3); cursor: pointer; transition: color 0.2s; }
.rating-input .star:hover, .rating-input .star.active { color: #f39c12; }
.input-box { position: relative; width: 100%; margin: 12px 0; }
.input-box textarea {
  display: block;
  width: 100%;
  min-height: 115px;
  background: transparent;
  border: 2px solid rgba(255,255,255,0.5);
  border-radius: 10px;
  padding: 14px 16px;
  font-size: 15px;
  line-height: 1.5;
  color: white;
  resize: vertical;
}
.input-box textarea::placeholder { color: rgba(255,255,255,0.7); }
.btn {
  width: 100%;
  height: 44px;
  background: linear-gradient(45deg, #d90429, #ef233c);
  color: #fff;
  border: none;
  border-radius: 999px;
  font-weight: 600;
  cursor: pointer;
  margin-top: 6px;
}
.btn:hover { filter: brightness(1.05); }
.success { color: #27ae60; text-align: center; margin-bottom: 20px; }
.error { color: #ef233c; text-align: center; margin-bottom: 20px; }

.pending-reviews { margin-bottom: 30px; }
.pending-reviews h2 { color: #fff; margin-bottom: 14px; }
.section-title { margin: 0 0 10px; color: #fff; }
.empty-message { text-align: center; color: rgba(255,255,255,0.74); padding: 30px; }

html[data-theme="light"] .reviews-subtitle,
html[data-theme="light"] .order-date,
html[data-theme="light"] .rating-label,
html[data-theme="light"] .review-card .date,
html[data-theme="light"] .empty-message {
  color: var(--rbj-muted, #9f4b43);
}

html[data-theme="light"] .review-form,
html[data-theme="light"] .review-card {
  background: var(--rbj-surface, rgba(255,255,255,0.92));
  color: var(--rbj-text, #7a211b);
  border-color: var(--rbj-border, rgba(217,4,41, 0.22));
}

html[data-theme="light"] .pending-reviews h2,
html[data-theme="light"] .section-title,
html[data-theme="light"] .review-card h3 {
  color: var(--rbj-accent-strong, #ef233c);
}

html[data-theme="light"] .input-box textarea {
  background: #fff;
  color: var(--rbj-text, #7a211b);
  border-color: var(--rbj-border, rgba(217,4,41, 0.22));
}

html[data-theme="light"] .input-box textarea::placeholder {
  color: #ad6a65;
}

html[data-theme="light"] .review-card .star.empty {
  color: rgba(122, 33, 27, 0.28);
}

@media(max-width:900px){
  .reviews-section{grid-template-columns:1fr;}
}

@media(max-width:768px){
  .navbar{padding:10px 20px;}
  .wrapper { padding: 20px 12px 28px; }
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
  <h1>My Reviews</h1>
  <p class="reviews-subtitle">Rate your completed RBJ orders and track your review history in one place.</p>

  <!-- Pending Reviews -->
  <?php if ($completed_orders->num_rows > 0): ?>
    <div class="pending-reviews">
      <h2>Rate Your Completed Orders</h2>
      <?php while ($order = $completed_orders->fetch_assoc()): ?>
        <?php if (is_null($order['rating'])): ?>
          <div class="review-form">
            <h3>Order #<?php echo $order['id']; ?> - <?php echo htmlspecialchars(substr($order['customization'], 0, 60)); ?></h3>
            <p class="order-date">Completed order date: <?php echo date('M j, Y', strtotime($order['created_at'])); ?></p>
            <form method="POST" action="reviews.php" class="rating-form" data-order-id="<?php echo $order['id']; ?>">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
              <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
              <p class="rating-label">Tap a star to rate this order</p>
              <div class="rating-input">
                <i class='bx bx-star star' data-rating="1"></i>
                <i class='bx bx-star star' data-rating="2"></i>
                <i class='bx bx-star star' data-rating="3"></i>
                <i class='bx bx-star star' data-rating="4"></i>
                <i class='bx bx-star star' data-rating="5"></i>
                <input type="hidden" name="rating" value="0" required>
              </div>
              <div class="input-box">
                <textarea name="review_text" placeholder="Share your experience with this customization..."></textarea>
              </div>
              <button type="submit" name="submit_review" class="btn">Submit Review</button>
            </form>
          </div>
        <?php endif; ?>
      <?php endwhile; ?>
    </div>
  <?php endif; ?>

  <!-- My Reviews -->
  <div class="reviews-section">
    <div>
      <h2 class="section-title">My Reviews</h2>
      <?php if ($user_reviews->num_rows > 0): ?>
        <?php while ($review = $user_reviews->fetch_assoc()): ?>
          <div class="review-card">
            <h3><?php echo htmlspecialchars(substr($review['customization'], 0, 60)); ?></h3>
            <div class="rating">
              <div class="stars">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <i class='bx bx-star star <?php echo $i <= $review['rating'] ? 'filled' : 'empty'; ?>'></i>
                <?php endfor; ?>
              </div>
            </div>
            <?php if (!empty($review['review_text'])): ?>
              <p><?php echo htmlspecialchars($review['review_text']); ?></p>
            <?php endif; ?>
            <p class="date">Reviewed on <?php echo date('M j, Y', strtotime($review['created_at'])); ?></p>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <p class="empty-message">No reviews yet. Complete an order to leave a review.</p>
      <?php endif; ?>
    </div>

    <div>
      <h2 class="section-title">Review Statistics</h2>
      <div class="review-card">
        <?php
        // Calculate statistics
        $total_reviews = $user_reviews->num_rows;
        $avg_rating = 0;
        if ($total_reviews > 0) {
          mysqli_data_seek($user_reviews, 0);
          $sum = 0;
          while ($review = $user_reviews->fetch_assoc()) {
            $sum += $review['rating'];
          }
          $avg_rating = round($sum / $total_reviews, 1);
        }
        ?>
        <p><strong>Total Reviews:</strong> <?php echo $total_reviews; ?></p>
        <p><strong>Average Rating:</strong>
          <div class="stars" style="display: inline-block; margin-left: 10px;">
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <i class='bx bx-star star <?php echo $i <= $avg_rating ? 'filled' : 'empty'; ?>'></i>
            <?php endfor; ?>
          </div>
          (<?php echo $avg_rating; ?>/5)
        </p>
        <p><strong>Completed Orders:</strong> <?php echo $completed_orders->num_rows; ?></p>
      </div>
    </div>
  </div>

  <?php if (isset($success)): ?>
    <div class="success" style="margin-top: 20px;"><?php echo $success; ?></div>
  <?php endif; ?>

  <?php if (isset($error)): ?>
    <div class="error" style="margin-top: 20px;"><?php echo $error; ?></div>
  <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // Rating system
  document.querySelectorAll('.rating-form').forEach(function(form) {
    const ratingInput = form.querySelector('.rating-input');
    if (!ratingInput) return;
    const stars = ratingInput.querySelectorAll('.star');
    const ratingHidden = ratingInput.querySelector('input[type="hidden"]');

    stars.forEach(function(star, index) {
      star.addEventListener('click', function() {
        const rating = index + 1;
        ratingHidden.value = rating;

        // Update star display
        stars.forEach(function(s, i) {
          if (i < rating) {
            s.classList.add('active');
          } else {
            s.classList.remove('active');
          }
        });
      });
    });

    form.addEventListener('submit', function (e) {
      const current = Number(ratingHidden ? ratingHidden.value : 0);
      if (current < 1 || current > 5) {
        e.preventDefault();
        alert('Please select a star rating before submitting your review.');
      }
    });
  });
});
</script>

<?php include __DIR__ . '/partials/user_footer.php'; ?>

</body>
</html>













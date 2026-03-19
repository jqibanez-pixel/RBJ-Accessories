<?php
session_start();
include '../config.php';
require_once __DIR__ . '/shapi_catalog_helper.php';
rbj_ensure_cart_choice_key_column($conn);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$template_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$toast_type = '';
$toast_message = '';
$product = null;

if (!function_exists('rbj_ensure_product_reviews_table')) {
    function rbj_ensure_product_reviews_table(mysqli $conn): void
    {
        $conn->query("
            CREATE TABLE IF NOT EXISTS product_reviews (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                product_id INT NOT NULL,
                rating TINYINT NOT NULL,
                comment TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_product_review (user_id, product_id),
                INDEX idx_product_reviews_product (product_id),
                INDEX idx_product_reviews_user (user_id),
                CONSTRAINT fk_product_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_product_reviews_product FOREIGN KEY (product_id) REFERENCES customization_templates(id) ON DELETE CASCADE
            )
        ");
    }
}
rbj_ensure_product_reviews_table($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $posted_token = $_POST['csrf_token'] ?? '';
    $template_id = (int)($_POST['template_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim((string)($_POST['comment'] ?? ''));

    if (!hash_equals($csrf_token, $posted_token)) {
        $_SESSION['product_toast'] = [
            'type' => 'error',
            'message' => 'Invalid request token.'
        ];
    } elseif (!isset($_SESSION['user_id'])) {
        $_SESSION['product_toast'] = [
            'type' => 'error',
            'message' => 'Please login first to submit a review.'
        ];
    } elseif ($template_id <= 0) {
        $_SESSION['product_toast'] = [
            'type' => 'error',
            'message' => 'Invalid product.'
        ];
    } elseif ($rating < 1 || $rating > 5) {
        $_SESSION['product_toast'] = [
            'type' => 'error',
            'message' => 'Please select a rating between 1 and 5.'
        ];
    } elseif ($comment === '') {
        $_SESSION['product_toast'] = [
            'type' => 'error',
            'message' => 'Review comment cannot be empty.'
        ];
    } else {
        $user_id = (int)$_SESSION['user_id'];
        $order_check_stmt = $conn->prepare("
            SELECT oi.order_id
            FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            WHERE o.user_id = ? AND oi.template_id = ?
            LIMIT 1
        ");
        $has_purchased = false;
        if ($order_check_stmt) {
            $order_check_stmt->bind_param("ii", $user_id, $template_id);
            $order_check_stmt->execute();
            $has_purchased = (bool)$order_check_stmt->get_result()->fetch_assoc();
            $order_check_stmt->close();
        }

        if (!$has_purchased) {
            $_SESSION['product_toast'] = [
                'type' => 'error',
                'message' => 'You can only review products you have purchased.'
            ];
            header('Location: product.php?id=' . $template_id);
            exit();
        }

        $stmt = $conn->prepare("SELECT id FROM product_reviews WHERE user_id = ? AND product_id = ? LIMIT 1");
        $stmt->bind_param("ii", $user_id, $template_id);
        $stmt->execute();
        $existing_review = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing_review) {
            $_SESSION['product_toast'] = [
                'type' => 'error',
                'message' => 'You already reviewed this product.'
            ];
        } else {
            $stmt = $conn->prepare("INSERT INTO product_reviews (user_id, product_id, rating, comment) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiis", $user_id, $template_id, $rating, $comment);
            if ($stmt->execute()) {
                $_SESSION['product_toast'] = [
                    'type' => 'success',
                    'message' => 'Review submitted successfully.'
                ];
            } else {
                $_SESSION['product_toast'] = [
                    'type' => 'error',
                    'message' => 'Unable to submit review. Please try again.'
                ];
            }
            $stmt->close();
        }
    }

    header('Location: product.php?id=' . $template_id);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_now'])) {
    $posted_token = $_POST['csrf_token'] ?? '';
    $template_id = (int)($_POST['template_id'] ?? 0);
    $quantity = max(1, min(99, (int)($_POST['quantity'] ?? 1)));
    $customizations = trim((string)($_POST['customizations'] ?? 'Standard package'));
    $choice_key = trim((string)($_POST['choice_key'] ?? ''));
    if ($customizations === '') {
        $customizations = 'Standard package';
    }
    if (!hash_equals($csrf_token, $posted_token)) {
        $_SESSION['product_toast'] = [
            'type' => 'error',
            'message' => 'Invalid request token.'
        ];
        header('Location: product.php?id=' . $template_id);
        exit();
    }
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['product_toast'] = [
            'type' => 'error',
            'message' => 'Please login first before proceeding to checkout.'
        ];
        header('Location: ../login.php');
        exit();
    }
    $stockInfo = rbj_resolve_item_stock($conn, $template_id, $customizations, $choice_key);
    $available = max(0, (int)($stockInfo['available'] ?? 0));
    if ($available <= 0 || $quantity > $available) {
        $_SESSION['product_toast'] = [
            'type' => 'error',
            'message' => $available <= 0
                ? 'Selected design is out of stock.'
                : 'Only ' . $available . ' stock left for this design.'
        ];
        header('Location: product.php?id=' . $template_id);
        exit();
    }

    header('Location: buy_now.php?source=product&template_id=' . $template_id . '&quantity=' . $quantity . '&customizations=' . urlencode($customizations) . '&choice_key=' . urlencode($choice_key));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf_token, $posted_token)) {
        $_SESSION['product_toast'] = [
            'type' => 'error',
            'message' => 'Invalid request token.'
        ];
    } elseif (!isset($_SESSION['user_id'])) {
        $_SESSION['product_toast'] = [
            'type' => 'error',
            'message' => 'Please login first to add items to cart.'
        ];
    } else {
        $user_id = (int)$_SESSION['user_id'];
        $template_id = (int)($_POST['template_id'] ?? 0);
        $quantity = max(1, min(99, (int)($_POST['quantity'] ?? 1)));
        $customizations = trim($_POST['customizations'] ?? 'Standard package');
        $choice_key = trim((string)($_POST['choice_key'] ?? ''));

        $stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND template_id = ? AND customizations = ? AND COALESCE(choice_key, '') = ?");
        $stmt->bind_param("iiss", $user_id, $template_id, $customizations, $choice_key);
        $stmt->execute();
        $existing_item = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare("SELECT id, name, base_price FROM customization_templates WHERE id = ? AND is_active = 1");
        $stmt->bind_param("i", $template_id);
        $stmt->execute();
        $template = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$template) {
            $_SESSION['product_toast'] = [
                'type' => 'error',
                'message' => 'Selected product is no longer available.'
            ];
        } else {
            $stockInfo = rbj_resolve_item_stock($conn, $template_id, $customizations, $choice_key);
            $available = max(0, (int)($stockInfo['available'] ?? 0));
            $existingQty = (int)($existing_item['quantity'] ?? 0);
            $new_quantity = $existingQty + $quantity;
            if ($available <= 0) {
                $_SESSION['product_toast'] = [
                    'type' => 'error',
                    'message' => 'Selected design is out of stock.'
                ];
            } elseif ($new_quantity > $available) {
                $_SESSION['product_toast'] = [
                    'type' => 'error',
                    'message' => 'Only ' . $available . ' stock left for this design.'
                ];
            } else {
                if ($existing_item) {
                    $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
                    $stmt->bind_param("ii", $new_quantity, $existing_item['id']);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $price = (float)$template['base_price'];
                    $stmt = $conn->prepare("INSERT INTO cart (user_id, template_id, customizations, choice_key, quantity, price) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("iissid", $user_id, $template_id, $customizations, $choice_key, $quantity, $price);
                    $stmt->execute();
                    $stmt->close();
                }

                $_SESSION['product_toast'] = [
                    'type' => 'success',
                    'message' => '"' . $template['name'] . '" added to cart.'
                ];
            }
        }
    }

    header('Location: product.php?id=' . $template_id);
    exit();
}

if (!empty($_SESSION['product_toast'])) {
    $toast_type = $_SESSION['product_toast']['type'] ?? '';
    $toast_message = $_SESSION['product_toast']['message'] ?? '';
    unset($_SESSION['product_toast']);
}

if ($template_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM customization_templates WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmt->bind_param("i", $template_id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$product_choices = [];
$main_image_url = '';
$display_description = 'Built with premium synthetic leather, high-density foam, and reinforced stitching. Designed for daily riding, this item is durable and made to withstand heat, rain, and regular use.';
if ($product) {
    $templateId = (int)$product['id'];
    $buildChoiceKey = static function (string $label, string $imageUrl = ''): string {
        $choiceKey = rbj_shapi_normalize($label);
        $choiceKey = preg_replace('/\s+/', '_', trim((string)$choiceKey)) ?? '';
        if ($choiceKey === '') {
            $choiceKey = 'choice_' . substr(md5($imageUrl !== '' ? $imageUrl : $label), 0, 12);
        }
        return $choiceKey;
    };
    $normalizeImageKey = static function (string $imageUrl): string {
        return strtolower(trim(str_replace('\\', '/', urldecode($imageUrl))));
    };

    $choiceStockMap = [];
    $choiceStockTableExists = false;
    $choiceTableCheck = $conn->query("SHOW TABLES LIKE 'customization_choice_stock'");
    if ($choiceTableCheck instanceof mysqli_result && $choiceTableCheck->num_rows > 0) {
        $choiceStockTableExists = true;
    }
    if ($choiceStockTableExists) {
        $choiceStockStmt = $conn->prepare("SELECT choice_key, choice_label, image_url, stock_quantity FROM customization_choice_stock WHERE template_id = ?");
        if ($choiceStockStmt) {
            $choiceStockStmt->bind_param("i", $templateId);
            $choiceStockStmt->execute();
            $choiceStockRes = $choiceStockStmt->get_result();
            while ($cs = $choiceStockRes->fetch_assoc()) {
                $choiceStockMap[(string)$cs['choice_key']] = [
                    'label' => (string)($cs['choice_label'] ?? ''),
                    'image_url' => (string)($cs['image_url'] ?? ''),
                    'stock_quantity' => (int)($cs['stock_quantity'] ?? 0)
                ];
            }
            $choiceStockStmt->close();
        }
    }

    $imageStockColumnExists = false;
    $imgColCheck = $conn->query("SHOW COLUMNS FROM product_images LIKE 'stock_quantity'");
    if ($imgColCheck instanceof mysqli_result && $imgColCheck->num_rows > 0) {
        $imageStockColumnExists = true;
    }

    $imgSql = $imageStockColumnExists
        ? "SELECT id, image_path, alt_text, is_primary, stock_quantity FROM product_images WHERE template_id = ? ORDER BY is_primary DESC, id ASC"
        : "SELECT id, image_path, alt_text, is_primary FROM product_images WHERE template_id = ? ORDER BY is_primary DESC, id ASC";
    $imgStmt = $conn->prepare($imgSql);
    if ($imgStmt) {
        $imgStmt->bind_param("i", $templateId);
        $imgStmt->execute();
        $imgResult = $imgStmt->get_result();
        while ($img = $imgResult->fetch_assoc()) {
            $rawPath = trim((string)($img['image_path'] ?? ''));
            $imageUrl = rbj_template_image_url($rawPath);
            if ($imageUrl === '') {
                continue;
            }

            $label = trim((string)($img['alt_text'] ?? ''));
            if ($label === '') {
                $label = rbj_shapi_humanize(pathinfo($rawPath, PATHINFO_FILENAME));
            }

            $product_choices[] = [
                'label' => $label,
                'image_url' => $imageUrl,
                'file_name' => basename($rawPath),
                'choice_key' => $buildChoiceKey($label, $imageUrl),
                'stock_quantity' => $imageStockColumnExists
                    ? max(0, (int)($img['stock_quantity'] ?? 0))
                    : max(1, (int)($product['stock_quantity'] ?? 1))
            ];

            if ($main_image_url === '' || (int)$img['is_primary'] === 1) {
                $main_image_url = $imageUrl;
            }
        }
        $imgStmt->close();
    }

    $existingImageKeys = [];
    foreach ($product_choices as $existingChoice) {
        $existingImageUrl = trim((string)($existingChoice['image_url'] ?? ''));
        if ($existingImageUrl !== '') {
            $existingImageKeys[$normalizeImageKey($existingImageUrl)] = true;
        }
    }

    $shapiChoices = rbj_find_shapi_choices((string)$product['name']);
    foreach ($shapiChoices as $choice) {
        $choiceImageUrl = trim((string)($choice['image_url'] ?? ''));
        if ($choiceImageUrl === '') {
            continue;
        }
        $choiceImageKey = $normalizeImageKey($choiceImageUrl);
        if (isset($existingImageKeys[$choiceImageKey])) {
            continue;
        }

        $choiceLabelForKey = trim((string)($choice['label'] ?? 'Item'));
        $choiceKey = $buildChoiceKey($choiceLabelForKey, $choiceImageUrl);
        $pathFromUrl = (string)(parse_url($choiceImageUrl, PHP_URL_PATH) ?? '');
        $product_choices[] = [
            'label' => $choiceLabelForKey !== '' ? $choiceLabelForKey : 'Item',
            'image_url' => $choiceImageUrl,
            'file_name' => basename($pathFromUrl),
            'choice_key' => $choiceKey,
            'stock_quantity' => (int)($choiceStockMap[$choiceKey]['stock_quantity'] ?? 0)
        ];
        $existingImageKeys[$choiceImageKey] = true;
        if ($main_image_url === '') {
            $main_image_url = $choiceImageUrl;
        }
    }

    // Include persisted choice rows so admin-managed design stocks always appear.
    foreach ($choiceStockMap as $savedChoiceKey => $savedChoice) {
        $savedImageUrl = trim((string)($savedChoice['image_url'] ?? ''));
        $savedLabel = trim((string)($savedChoice['label'] ?? ''));
        if ($savedImageUrl === '' && $savedLabel === '') {
            continue;
        }
        $savedImageKey = $savedImageUrl !== '' ? $normalizeImageKey($savedImageUrl) : '';
        if ($savedImageKey !== '' && isset($existingImageKeys[$savedImageKey])) {
            continue;
        }

        $product_choices[] = [
            'label' => $savedLabel !== '' ? $savedLabel : 'Item',
            'image_url' => $savedImageUrl,
            'file_name' => $savedImageUrl !== '' ? basename((string)(parse_url($savedImageUrl, PHP_URL_PATH) ?? '')) : '',
            'choice_key' => (string)$savedChoiceKey,
            'stock_quantity' => (int)($savedChoice['stock_quantity'] ?? 0)
        ];
        if ($savedImageKey !== '') {
            $existingImageKeys[$savedImageKey] = true;
        }
        if ($main_image_url === '' && $savedImageUrl !== '') {
            $main_image_url = $savedImageUrl;
        }
    }

    if (!empty($product_choices) && $main_image_url === '') {
        $main_image_url = (string)$product_choices[0]['image_url'];
    }

    if ($main_image_url === '') {
        $main_image_url = rbj_template_image_url($product['image_path'] ?? '');
    }

    $productNameForChoices = trim((string)($product['name'] ?? ''));
    foreach ($product_choices as &$choice) {
        $rawLabel = trim((string)($choice['label'] ?? ''));
        $displayLabel = $rawLabel;
        if ($productNameForChoices !== '' && $rawLabel !== '') {
            $pattern = '/^' . preg_quote($productNameForChoices, '/') . '\s*[-:|]\s*/i';
            $displayLabel = preg_replace($pattern, '', $rawLabel) ?? $rawLabel;
            $displayLabel = trim($displayLabel);
            if ($displayLabel === '') {
                $displayLabel = $rawLabel;
            }
        }
        $choice['display_label'] = $displayLabel !== '' ? $displayLabel : 'Standard package';
        if (!isset($choice['choice_key']) || trim((string)$choice['choice_key']) === '') {
            $choice['choice_key'] = $buildChoiceKey($rawLabel !== '' ? $rawLabel : $choice['display_label'], (string)($choice['image_url'] ?? ''));
        }
        if (!isset($choice['stock_quantity'])) {
            $choiceLabelForKey = $rawLabel !== '' ? $rawLabel : $choice['display_label'];
            $choiceKey = rbj_shapi_normalize((string)$choiceLabelForKey);
            $choiceKey = preg_replace('/\s+/', '_', $choiceKey ?? '') ?? '';
            if ($choiceKey === '') {
                $choiceKey = 'choice_' . substr(md5((string)($choice['image_url'] ?? $choiceLabelForKey)), 0, 12);
            }
            $choice['stock_quantity'] = (int)($choiceStockMap[$choiceKey]['stock_quantity'] ?? 0);
        }
    }
    unset($choice);

    $materialMap = [
        'genuine leather' => 'genuine leather',
        'real leather' => 'genuine leather',
        'pu leather' => 'PU leather',
        'synthetic leather' => 'synthetic leather',
        'microfiber' => 'microfiber leather',
        'suede' => 'suede',
        'alcantara' => 'alcantara-style fabric',
        'mesh' => 'breathable mesh fabric',
        'fabric' => 'woven fabric',
        'vinyl' => 'vinyl covering',
        'carbon' => 'carbon-pattern panel',
        'neoprene' => 'neoprene layer',
        'rubber' => 'anti-slip rubber support',
        'foam' => 'high-density foam',
        'stitch' => 'reinforced stitching',
        'stitched' => 'reinforced stitching'
    ];

    $materialSources = [];
    $materialSources[] = strtolower((string)($product['name'] ?? ''));
    $materialSources[] = strtolower((string)($product['description'] ?? ''));
    $materialSources[] = strtolower((string)($product['image_path'] ?? ''));
    foreach ($product_choices as $choice) {
        $materialSources[] = strtolower((string)($choice['label'] ?? ''));
        $materialSources[] = strtolower((string)($choice['file_name'] ?? ''));
    }

    $materials = [];
    foreach ($materialSources as $sourceText) {
        foreach ($materialMap as $needle => $materialLabel) {
            if (strpos($sourceText, $needle) !== false) {
                $materials[$materialLabel] = true;
            }
        }
    }

    $materialList = array_keys($materials);
    if (empty($materialList)) {
        $materialList = ['premium synthetic leather', 'high-density foam', 'reinforced stitching'];
    }

    if (count($materialList) === 1) {
        $materialsText = $materialList[0];
    } elseif (count($materialList) === 2) {
        $materialsText = $materialList[0] . ' and ' . $materialList[1];
    } else {
        $last = array_pop($materialList);
        $materialsText = implode(', ', $materialList) . ', and ' . $last;
    }

    $display_description = 'Built with ' . $materialsText . '. Designed for daily riding, this item is durable and made to withstand heat, rain, and regular use.';
}

$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT SUM(quantity) AS total FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $cart_count = (int)($result['total'] ?? 0);
}

$product_reviews = [];
$review_summary = [
    'avg' => 0,
    'count' => 0
];
$user_has_reviewed = false;
$user_can_review = false;
if ($product) {
    $product_id = (int)$product['id'];
    $stmt = $conn->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS total_reviews FROM product_reviews WHERE product_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $summary_row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $review_summary['avg'] = (float)($summary_row['avg_rating'] ?? 0);
        $review_summary['count'] = (int)($summary_row['total_reviews'] ?? 0);
    }

    $stmt = $conn->prepare("
        SELECT pr.rating, pr.comment, pr.created_at, u.username
        FROM product_reviews pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.product_id = ?
        ORDER BY pr.created_at DESC
    ");
    if ($stmt) {
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $product_reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    if (isset($_SESSION['user_id'])) {
        $user_id = (int)$_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT id FROM product_reviews WHERE user_id = ? AND product_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("ii", $user_id, $product_id);
            $stmt->execute();
            $user_has_reviewed = (bool)$stmt->get_result()->fetch_assoc();
            $stmt->close();
        }

        $order_check_stmt = $conn->prepare("
            SELECT oi.order_id
            FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            WHERE o.user_id = ? AND oi.template_id = ?
            LIMIT 1
        ");
        if ($order_check_stmt) {
            $order_check_stmt->bind_param("ii", $user_id, $product_id);
            $order_check_stmt->execute();
            $user_can_review = (bool)$order_check_stmt->get_result()->fetch_assoc();
            $order_check_stmt->close();
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $product ? htmlspecialchars($product['name']) . ' - RBJ Accessories' : 'Product - RBJ Accessories'; ?></title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<style>
body { min-height:100vh; background: linear-gradient(135deg,#1b1b1b,#111); color:white; padding-top:100px; font-family:"Montserrat",sans-serif; }
.navbar { position:fixed; top:0; left:0; right:0; display:flex; justify-content:space-between; align-items:center; padding:10px 50px; background: rgba(0,0,0,0.8); z-index:999; }
.navbar .logo { display:flex; align-items:center; gap:10px; color:white; text-decoration:none; font-size:22px; font-weight:700; }
.navbar .logo img { height: 60px; width:auto; }
.navbar .nav-links { display:flex; align-items:center; gap:15px; }
.navbar .nav-links a { color:white; text-decoration:none; font-weight:500; margin-left:15px; }
.navbar .nav-links a:hover { text-decoration:underline; }

.account-dropdown { display:flex; align-items:center; margin-left:15px; position: relative; }
.account-icon {
  width:40px;
  height:40px;
  min-width:40px;
  min-height:40px;
  border-radius:50%;
  display:flex;
  justify-content:center;
  align-items:center;
  font-weight:bold;
  overflow: hidden;
}
.account-username { font-weight:600; color:white; }
.account-trigger {
  background: none;
  border: none;
  color: white;
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
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
.account-menu a:hover { background: rgba(255,255,255,0.08); }
.account-dropdown.active .account-menu { display: block; }

.page-wrap { max-width: 1420px; margin: 0 auto; padding: 18px; }
.back-link { color: rgba(255,255,255,0.88); text-decoration: none; display: inline-flex; gap: 6px; align-items: center; margin-bottom: 14px; }
.back-link:hover { color: #fff; text-decoration: underline; }

.product-layout {
  display: grid;
  grid-template-columns: 1.1fr 1.4fr;
  gap: 20px;
  align-items: stretch;
}

.media-panel, .info-panel {
  background: rgba(0,0,0,0.58);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 16px;
  padding: 16px;
  min-height: 740px;
}
.info-panel {
  display: flex;
  flex-direction: column;
}
.info-panel form {
  display: flex;
  flex-direction: column;
  flex: 1;
}

.main-image {
  width: 100%;
  border-radius: 14px;
  background: linear-gradient(145deg, #1a1a1a, #101010);
  height: 430px;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}
.main-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
}

.thumbs {
  margin-top: 10px;
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 8px;
  min-height: 164px;
  max-height: 164px;
  overflow-y: auto;
  padding-right: 2px;
}
.thumb {
  border: 1px solid rgba(255,255,255,0.14);
  border-radius: 10px;
  background: rgba(255,255,255,0.04);
  height: 78px;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}
.thumb img { width: 100%; height: 100%; object-fit: cover; object-position: center; opacity: 0.92; }
.thumb.is-active { border-color: rgba(39,174,96,0.95); }

.product-title {
  margin: 0 0 6px 0;
  font-size: 30px;
  line-height: 1.25;
  min-height: 74px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.product-submeta {
  color: rgba(255,255,255,0.78);
  font-size: 14px;
  margin-bottom: 14px;
}
.price-box {
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 12px;
  padding: 12px 14px;
  margin-bottom: 14px;
}
.price-box .price {
  font-size: 36px;
  font-weight: 800;
  color: #27ae60;
}

.section-title {
  font-size: 13px;
  color: rgba(255,255,255,0.75);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin-bottom: 8px;
}
.chips { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }
.chip {
  padding: 8px 12px;
  border-radius: 10px;
  border: 1px solid rgba(255,255,255,0.18);
  background: rgba(255,255,255,0.06);
  font-size: 13px;
}
.chip.active {
  border-color: rgba(217,4,41,0.65);
  background: rgba(217,4,41,0.14);
}

.desc {
  margin: 0 0 16px 0;
  color: rgba(255,255,255,0.86);
  line-height: 1.55;
  font-size: 14px;
  min-height: 88px;
  max-height: 88px;
  overflow-y: auto;
}

.choice-options {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 14px;
  min-height: 94px;
  max-height: 94px;
  overflow-y: auto;
  align-content: flex-start;
  padding-right: 2px;
}
.choice-option {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  border: 1px solid rgba(255,255,255,0.22);
  border-radius: 4px;
  background: rgba(255,255,255,0.04);
  color: #fff;
  padding: 6px 10px 6px 6px;
  min-height: 36px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 600;
  line-height: 1.3;
  transition: border-color 0.2s ease, background-color 0.2s ease, color 0.2s ease;
}
.choice-option .choice-thumb {
  width: 28px;
  height: 28px;
  border-radius: 4px;
  border: 1px solid rgba(255,255,255,0.18);
  object-fit: cover;
  flex-shrink: 0;
  background: rgba(0,0,0,0.2);
}
.choice-option .choice-label {
  display: inline-block;
  max-width: 160px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.choice-option .choice-stock {
  margin-left: 4px;
  font-size: 11px;
  color: rgba(255,255,255,0.68);
}
.choice-option:hover {
  border-color: rgba(238,77,45,0.72);
  color: #ffe8e2;
}
.choice-option.is-active {
  border-color: #ee4d2d;
  color: #ffb39f;
  background: rgba(238,77,45,0.12);
  box-shadow: inset 0 0 0 1px rgba(238,77,45,0.3);
}
.choice-option.is-disabled {
  opacity: 0.58;
  cursor: not-allowed;
}
.choice-option.is-disabled .choice-stock {
  color: #ff8f8f;
}

.qty-row {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 14px;
}
.qty-control {
  display: inline-flex;
  align-items: center;
  border: 1px solid rgba(255,255,255,0.2);
  border-radius: 10px;
  overflow: hidden;
}
.qty-btn {
  width: 36px;
  height: 36px;
  border: none;
  background: rgba(255,255,255,0.08);
  color: #fff;
  cursor: pointer;
  font-size: 18px;
}
.qty-input {
  width: 52px;
  height: 36px;
  border: none;
  outline: none;
  text-align: center;
  background: rgba(0,0,0,0.35);
  color: #fff;
}

.action-row {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
  margin-top: auto;
}
.btn {
  border: none;
  border-radius: 10px;
  padding: 12px 14px;
  font-weight: 700;
  cursor: pointer;
  text-align: center;
  text-decoration: none;
}
.btn-add {
  background: rgba(255,255,255,0.1);
  border: 1px solid rgba(255,255,255,0.24);
  color: #fff;
}
.btn-buy {
  background: linear-gradient(45deg, #27ae60, #2ecc71);
  color: #fff;
}
.btn-buy-now {
  background: linear-gradient(45deg, #e67e22, #f39c12);
  color: #fff;
}

.toast {
  position: fixed;
  top: 86px;
  right: 20px;
  min-width: 260px;
  max-width: 420px;
  padding: 12px 14px;
  border-radius: 10px;
  border: 1px solid transparent;
  color: white;
  z-index: 1200;
  opacity: 0;
  transform: translateY(-10px);
  transition: opacity 0.25s ease, transform 0.25s ease;
  box-shadow: 0 8px 24px rgba(0,0,0,0.35);
}
.toast.show { opacity: 1; transform: translateY(0); }
.toast.success { background: rgba(39,174,96,0.95); border-color: rgba(39,174,96,1); }
.toast.error { background: rgba(192,57,43,0.95); border-color: rgba(192,57,43,1); }

.not-found {
  background: rgba(0,0,0,0.58);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 16px;
  padding: 28px;
  text-align: center;
}

.reviews-wrap {
  margin-top: 22px;
  background: rgba(0,0,0,0.58);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 16px;
  padding: 18px;
}
.reviews-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
  margin-bottom: 12px;
}
.reviews-title {
  font-size: 18px;
  font-weight: 700;
}
.rating-summary {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: rgba(255,255,255,0.78);
}
.rating-stars {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.rating-stars i {
  color: #f5c65a;
  font-size: 16px;
}
.review-form {
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 12px;
  padding: 14px;
  margin-bottom: 16px;
}
.review-form textarea {
  width: 100%;
  min-height: 110px;
  border-radius: 10px;
  border: 1px solid rgba(255,255,255,0.2);
  background: rgba(0,0,0,0.35);
  color: #fff;
  padding: 10px;
  font-size: 14px;
  resize: vertical;
}
.review-form .form-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 10px;
}
.star-input {
  display: inline-flex;
  gap: 6px;
  flex-direction: row-reverse;
}
.star-input input { display: none; }
.star-input label {
  font-size: 20px;
  color: rgba(255,255,255,0.35);
  cursor: pointer;
  transition: color 0.2s ease;
}
.star-input input:checked ~ label,
.star-input label:hover,
.star-input label:hover ~ label {
  color: #f5c65a;
}
.review-list { display: grid; gap: 12px; }
.review-card {
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 12px;
  padding: 12px;
  background: rgba(255,255,255,0.03);
}
.review-meta {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
  font-size: 13px;
  color: rgba(255,255,255,0.7);
  margin-bottom: 8px;
}
.review-meta strong { color: #fff; }
.review-comment {
  color: rgba(255,255,255,0.85);
  line-height: 1.6;
  font-size: 14px;
}
.review-empty {
  text-align: center;
  color: rgba(255,255,255,0.7);
  padding: 20px;
}

@media (max-width: 980px) {
  .product-layout { grid-template-columns: 1fr; }
  .main-image { height: 320px; }
  .media-panel, .info-panel { min-height: 0; }
  .action-row { margin-top: 0; }
}

@media (max-width: 640px) {
  .action-row { grid-template-columns: 1fr; }
  .product-title { font-size: 24px; }
  .navbar { padding:10px 20px; }
  .navbar .nav-links a { margin-left: 10px; font-size: 14px; }
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
    <a href="catalog.php" class="active">Shop</a>
    <a href="customize.php">Customize</a>
    <?php if (isset($_SESSION['user_id'])): ?>
      <a href="cart.php" class="nav-cart-link"><i class='bx bx-cart'></i><span class="cart-count" data-cart-count style="<?php echo $cart_count > 0 ? '' : 'display:none;'; ?>"><?php echo (int)$cart_count; ?></span></a>
      <?php include __DIR__ . '/partials/account_menu.php'; ?>
    <?php else: ?>
      <a href="../login.php">Login</a>
      <a href="../register.php">Register</a>
    <?php endif; ?>
  </div>
</nav>

<div class="page-wrap">
  <a class="back-link" href="catalog.php"><i class='bx bx-left-arrow-alt'></i> Back to Shop</a>

  <?php if (!$product): ?>
    <div class="not-found">
      <h2>Product not found</h2>
      <p>The item might be unavailable or removed.</p>
      <a class="btn btn-buy" href="catalog.php">Return to Catalog</a>
    </div>
  <?php else: ?>
    <div class="product-layout">
      <section class="media-panel">
        <div class="main-image">
          <?php if ($main_image_url !== ''): ?>
            <img id="mainProductImage" src="<?php echo htmlspecialchars($main_image_url); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
          <?php else: ?>
            <i class='bx bx-image' style="font-size:64px; color: rgba(255,255,255,0.55);"></i>
          <?php endif; ?>
        </div>
        <div class="thumbs">
          <?php if (!empty($product_choices)): ?>
            <?php foreach ($product_choices as $idx => $choice): ?>
              <div class="thumb<?php echo $idx === 0 ? ' is-active' : ''; ?>" data-choice-index="<?php echo (int)$idx; ?>" data-image-url="<?php echo htmlspecialchars((string)$choice['image_url']); ?>">
                <img src="<?php echo htmlspecialchars((string)$choice['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name'] . ' - ' . (string)$choice['label']); ?>">
              </div>
            <?php endforeach; ?>
          <?php elseif ($main_image_url !== ''): ?>
            <?php for ($i = 0; $i < 4; $i++): ?>
              <div class="thumb<?php echo $i === 0 ? ' is-active' : ''; ?>" data-choice-index="<?php echo (int)$i; ?>" data-image-url="<?php echo htmlspecialchars($main_image_url); ?>">
                <img src="<?php echo htmlspecialchars($main_image_url); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
              </div>
            <?php endfor; ?>
          <?php else: ?>
            <?php for ($i = 0; $i < 4; $i++): ?>
              <div class="thumb"><i class='bx bx-image' style="font-size:28px; color: rgba(255,255,255,0.45);"></i></div>
            <?php endfor; ?>
          <?php endif; ?>
        </div>
      </section>

      <section class="info-panel">
        <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
        <p class="product-submeta">Seat Cover • Custom Build • RBJ Accessories</p>

        <div class="price-box">
          <div class="price">₱<?php echo number_format((float)$product['base_price'], 2); ?></div>
        </div>

        <div class="section-title">Seat Type</div>
        <div class="chips">
          <span class="chip active"><?php echo htmlspecialchars(ucwords($product['category'])); ?></span>
        </div>

        <div class="section-title">Product Description</div>
        <p class="desc"><?php echo nl2br(htmlspecialchars($display_description)); ?></p>

        <form method="POST" action="product.php?id=<?php echo (int)$product['id']; ?>">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
          <input type="hidden" name="template_id" value="<?php echo (int)$product['id']; ?>">
          <?php
            $defaultChoiceIndex = 0;
            if (!empty($product_choices)) {
                foreach ($product_choices as $idx => $choice) {
                    if ((int)($choice['stock_quantity'] ?? 0) > 0) {
                        $defaultChoiceIndex = (int)$idx;
                        break;
                    }
                }
            }
          ?>
          <div class="section-title">Design Choices</div>
          <div class="choice-options" id="choiceOptions">
            <?php if (!empty($product_choices)): ?>
              <?php foreach ($product_choices as $idx => $choice): ?>
                <?php $choiceLabel = (string)($choice['display_label'] ?? $choice['label']); ?>
                <?php $choiceStock = max(0, (int)($choice['stock_quantity'] ?? 0)); ?>
                <button
                  class="choice-option<?php echo $idx === $defaultChoiceIndex ? ' is-active' : ''; ?><?php echo $choiceStock <= 0 ? ' is-disabled' : ''; ?>"
                  type="button"
                  data-choice-index="<?php echo (int)$idx; ?>"
                  data-image-url="<?php echo htmlspecialchars((string)$choice['image_url']); ?>"
                  data-choice-label="<?php echo htmlspecialchars($choiceLabel); ?>"
                  data-choice-key="<?php echo htmlspecialchars((string)($choice['choice_key'] ?? rbj_choice_key_from_label($choiceLabel, (string)$choice['image_url']))); ?>"
                  data-choice-stock="<?php echo (int)$choiceStock; ?>"
                  <?php echo $choiceStock <= 0 ? 'disabled' : ''; ?>
                >
                  <img class="choice-thumb" src="<?php echo htmlspecialchars((string)$choice['image_url']); ?>" alt="<?php echo htmlspecialchars($choiceLabel); ?>">
                  <span class="choice-label"><?php echo htmlspecialchars($choiceLabel); ?></span>
                  <span class="choice-stock"><?php echo (int)$choiceStock; ?> available</span>
                </button>
              <?php endforeach; ?>
            <?php else: ?>
              <button class="choice-option is-disabled is-active" type="button" disabled>
                <span class="choice-label">Standard package</span>
              </button>
            <?php endif; ?>
          </div>
          <?php
            $defaultChoiceLabel = !empty($product_choices)
                ? (string)($product_choices[$defaultChoiceIndex]['display_label'] ?? $product_choices[$defaultChoiceIndex]['label'] ?? 'Standard package')
                : 'Standard package';
            $defaultChoiceImage = !empty($product_choices)
                ? (string)($product_choices[$defaultChoiceIndex]['image_url'] ?? '')
                : '';
            $defaultChoiceKey = !empty($product_choices)
                ? (string)($product_choices[$defaultChoiceIndex]['choice_key'] ?? rbj_choice_key_from_label($defaultChoiceLabel, $defaultChoiceImage))
                : '';
            $defaultChoiceStock = !empty($product_choices)
                ? max(0, (int)($product_choices[$defaultChoiceIndex]['stock_quantity'] ?? 0))
                : 99;
            $defaultQtyMax = $defaultChoiceStock > 0 ? min(99, $defaultChoiceStock) : 1;
          ?>
          <input type="hidden" id="choiceInput" name="customizations" value="<?php echo htmlspecialchars($defaultChoiceLabel); ?>">
          <input type="hidden" id="choiceKeyInput" name="choice_key" value="<?php echo htmlspecialchars($defaultChoiceKey); ?>">

          <div class="section-title">Quantity</div>
          <div class="qty-row">
            <div class="qty-control">
              <button class="qty-btn" type="button" id="qtyMinus">-</button>
              <input class="qty-input" id="qtyInput" type="number" name="quantity" value="1" min="1" max="<?php echo (int)$defaultQtyMax; ?>">
              <button class="qty-btn" type="button" id="qtyPlus">+</button>
            </div>
          </div>

          <div class="action-row">
            <button class="btn btn-add" type="submit" name="add_to_cart"><i class='bx bx-cart-add'></i> Add to Cart</button>
            <button class="btn btn-buy-now" type="submit" name="buy_now"><i class='bx bx-credit-card-front'></i> Buy Now</button>
            <a class="btn btn-buy" href="customize.php?template=<?php echo (int)$product['id']; ?>">Customize Now</a>
          </div>
        </form>
      </section>
    </div>
  <?php endif; ?>

  <?php if ($product): ?>
    <section class="reviews-wrap" id="productReviews">
      <div class="reviews-header">
        <div class="reviews-title">Product Reviews</div>
        <div class="rating-summary">
          <div class="rating-stars">
            <?php
              $avg_rating = $review_summary['avg'];
              $full_stars = (int)floor($avg_rating);
              $has_half = ($avg_rating - $full_stars) >= 0.5;
              for ($i = 1; $i <= 5; $i++) {
                  if ($i <= $full_stars) {
                      echo "<i class='bx bxs-star'></i>";
                  } elseif ($has_half && $i === $full_stars + 1) {
                      echo "<i class='bx bxs-star-half'></i>";
                  } else {
                      echo "<i class='bx bx-star'></i>";
                  }
              }
            ?>
          </div>
          <span><?php echo number_format($review_summary['avg'], 1); ?> / 5.0</span>
          <span>(<?php echo (int)$review_summary['count']; ?> reviews)</span>
        </div>
      </div>

      <?php if (isset($_SESSION['user_id'])): ?>
        <?php if (!$user_can_review): ?>
          <div class="review-form">
            <strong>You can only review products you have purchased.</strong>
          </div>
        <?php elseif ($user_has_reviewed): ?>
          <div class="review-form">
            <strong>You already reviewed this product.</strong>
          </div>
        <?php else: ?>
          <form class="review-form" method="POST" action="product.php?id=<?php echo (int)$product['id']; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="template_id" value="<?php echo (int)$product['id']; ?>">
            <div class="form-row">
              <div class="section-title" style="margin-bottom:0;">Your Rating</div>
              <div class="star-input">
                <input type="radio" id="rate5" name="rating" value="5" required>
                <label for="rate5"><i class='bx bxs-star'></i></label>
                <input type="radio" id="rate4" name="rating" value="4">
                <label for="rate4"><i class='bx bxs-star'></i></label>
                <input type="radio" id="rate3" name="rating" value="3">
                <label for="rate3"><i class='bx bxs-star'></i></label>
                <input type="radio" id="rate2" name="rating" value="2">
                <label for="rate2"><i class='bx bxs-star'></i></label>
                <input type="radio" id="rate1" name="rating" value="1">
                <label for="rate1"><i class='bx bxs-star'></i></label>
              </div>
            </div>
            <textarea name="comment" placeholder="Share your thoughts about the product..." required></textarea>
            <div style="margin-top:10px;">
              <button type="submit" name="submit_review" class="btn btn-buy-now"><i class='bx bx-send'></i> Submit Review</button>
            </div>
          </form>
        <?php endif; ?>
      <?php else: ?>
        <div class="review-form">
          <strong>Please login to leave a review.</strong>
        </div>
      <?php endif; ?>

      <div class="review-list">
        <?php if (!empty($product_reviews)): ?>
          <?php foreach ($product_reviews as $review): ?>
            <div class="review-card">
              <div class="review-meta">
                <strong><?php echo htmlspecialchars($review['username']); ?></strong>
                <span>
                  <?php
                    $rating = (int)$review['rating'];
                    for ($i = 1; $i <= 5; $i++) {
                        echo $i <= $rating ? "<i class='bx bxs-star'></i>" : "<i class='bx bx-star'></i>";
                    }
                  ?>
                </span>
                <span><?php echo date('M j, Y', strtotime($review['created_at'])); ?></span>
              </div>
              <div class="review-comment"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="review-empty">No reviews yet. Be the first to review this product.</div>
        <?php endif; ?>
      </div>
    </section>
  <?php endif; ?>
</div>

<script>
window.RBJ_PRODUCT_CONFIG = {
  toastMessage: <?php echo json_encode($toast_message); ?>,
  toastType: <?php echo json_encode($toast_type); ?>
};
</script>
<script src="assets/user-product.js"></script>
<?php include __DIR__ . '/partials/user_footer.php'; ?>

</body>
</html>




<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['admin', 'superadmin'], true)) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
require_once __DIR__ . '/admin_audit.php';
require_once __DIR__ . '/../USER/shapi_catalog_helper.php';

$build_choice_key = static function (string $label, string $imageUrl = ''): string {
    $key = rbj_shapi_normalize($label);
    $key = preg_replace('/\s+/', '_', trim((string)$key)) ?? '';
    if ($key === '') {
        $key = 'choice_' . substr(md5($imageUrl !== '' ? $imageUrl : $label), 0, 12);
    }
    return $key;
};

// Ensure stock column exists for admin-managed inventory
$stock_column = 'stock_quantity';
$stock_exists = false;
$columnCheck = $conn->query("SHOW COLUMNS FROM customization_templates LIKE '{$stock_column}'");
if ($columnCheck instanceof mysqli_result && $columnCheck->num_rows > 0) {
    $stock_exists = true;
} else {
    $alterSql = "ALTER TABLE customization_templates ADD COLUMN stock_quantity INT NOT NULL DEFAULT 0";
    if ($conn->query($alterSql)) {
        $stock_exists = true;
    }
}

// Ensure per-item stock column exists on product_images
$image_stock_column = 'stock_quantity';
$imageStockCheck = $conn->query("SHOW COLUMNS FROM product_images LIKE '{$image_stock_column}'");
if (!($imageStockCheck instanceof mysqli_result && $imageStockCheck->num_rows > 0)) {
    $conn->query("ALTER TABLE product_images ADD COLUMN stock_quantity INT NOT NULL DEFAULT 0");
}

// Store stock values for non-DB (Shapi-derived) choices
$conn->query("
    CREATE TABLE IF NOT EXISTS customization_choice_stock (
        id INT AUTO_INCREMENT PRIMARY KEY,
        template_id INT NOT NULL,
        choice_key VARCHAR(191) NOT NULL,
        choice_label VARCHAR(255) NOT NULL,
        image_url VARCHAR(255) DEFAULT '',
        stock_quantity INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_template_choice (template_id, choice_key),
        KEY idx_template_id (template_id)
    )
");

// Handle product operations
$message = '';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
$apply_item_stock_updates = static function (mysqli $conn, int $product_id, $variant_stock, $choice_stock, ?int $product_stock_quantity = null): int {
    $updated = 0;
    $variant_stock = is_array($variant_stock) ? $variant_stock : [];
    $choice_stock = is_array($choice_stock) ? $choice_stock : [];

    $updateStmt = $conn->prepare("UPDATE product_images SET stock_quantity = ? WHERE id = ? AND template_id = ?");
    if ($updateStmt) {
        foreach ($variant_stock as $image_id => $stock_val) {
            $image_id = (int)$image_id;
            if ($image_id <= 0) {
                continue;
            }
            $stock_val = max(0, (int)$stock_val);
            $updateStmt->bind_param("iii", $stock_val, $image_id, $product_id);
            $updateStmt->execute();
            $updated++;
        }
        $updateStmt->close();
    }

    if (!empty($choice_stock)) {
        $upsertChoiceStmt = $conn->prepare("
            INSERT INTO customization_choice_stock (template_id, choice_key, choice_label, image_url, stock_quantity)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                choice_label = VALUES(choice_label),
                image_url = VALUES(image_url),
                stock_quantity = VALUES(stock_quantity)
        ");
        if ($upsertChoiceStmt) {
            foreach ($choice_stock as $choice_key => $choiceData) {
                if (!is_array($choiceData)) {
                    continue;
                }
                $choice_key = trim((string)$choice_key);
                if ($choice_key === '') {
                    continue;
                }
                $choice_label = trim((string)($choiceData['label'] ?? 'Item'));
                $image_url = trim((string)($choiceData['image_url'] ?? ''));
                $stock_val = max(0, (int)($choiceData['stock'] ?? 0));
                $upsertChoiceStmt->bind_param("isssi", $product_id, $choice_key, $choice_label, $image_url, $stock_val);
                $upsertChoiceStmt->execute();
                $updated++;
            }
            $upsertChoiceStmt->close();
        }
    }

    $sumStmt = $conn->prepare("SELECT COALESCE(SUM(stock_quantity), 0) AS total_stock FROM product_images WHERE template_id = ?");
    $total_stock_db = 0;
    if ($sumStmt) {
        $sumStmt->bind_param("i", $product_id);
        $sumStmt->execute();
        $sumRow = $sumStmt->get_result()->fetch_assoc();
        $sumStmt->close();
        $total_stock_db = (int)($sumRow['total_stock'] ?? 0);
    }
    $sumChoiceStmt = $conn->prepare("SELECT COALESCE(SUM(stock_quantity), 0) AS total_stock FROM customization_choice_stock WHERE template_id = ?");
    $total_stock_choice = 0;
    if ($sumChoiceStmt) {
        $sumChoiceStmt->bind_param("i", $product_id);
        $sumChoiceStmt->execute();
        $sumChoiceRow = $sumChoiceStmt->get_result()->fetch_assoc();
        $sumChoiceStmt->close();
        $total_stock_choice = (int)($sumChoiceRow['total_stock'] ?? 0);
    }
    $computed_total_stock = $total_stock_db + $total_stock_choice;
    $total_stock = $product_stock_quantity !== null ? $product_stock_quantity : $computed_total_stock;
    $syncStmt = $conn->prepare("UPDATE customization_templates SET stock_quantity = ? WHERE id = ?");
    if ($syncStmt) {
        $syncStmt->bind_param("ii", $total_stock, $product_id);
        $syncStmt->execute();
        $syncStmt->close();
    }

    return $updated;
};

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!hash_equals($csrf_token, (string)($_POST['csrf_token'] ?? ''))) {
        $message = "Invalid request token. Please refresh and try again.";
    } elseif (isset($_POST['add_product'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $category = trim($_POST['category']);
        $base_price = (float)$_POST['base_price'];
        $stock_quantity = max(0, (int)($_POST['stock_quantity'] ?? 0));
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $uploadedImagePath = '';
        $fileError = '';

        if (!isset($_FILES['product_image']) || !is_array($_FILES['product_image']) || (int)($_FILES['product_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $fileError = 'Product image is required.';
        } else {
            $file = $_FILES['product_image'];
            $tmpFile = (string)($file['tmp_name'] ?? '');
            $originalName = (string)($file['name'] ?? '');
            $ext = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
            $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
            if (!in_array($ext, $allowedExt, true)) {
                $fileError = 'Invalid image format. Allowed: JPG, PNG, WEBP.';
            } elseif (!is_uploaded_file($tmpFile)) {
                $fileError = 'Invalid uploaded image file.';
            } else {
                $uploadDir = __DIR__ . '/../uploads/products';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0755, true);
                }
                if (!is_dir($uploadDir)) {
                    $fileError = 'Unable to create upload directory.';
                } else {
                    $newFileName = 'product_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                    $targetPath = $uploadDir . '/' . $newFileName;
                    if (!move_uploaded_file($tmpFile, $targetPath)) {
                        $fileError = 'Failed to upload product image.';
                    } else {
                        $uploadedImagePath = 'uploads/products/' . $newFileName;
                    }
                }
            }
        }

        if ($fileError !== '') {
            $message = $fileError;
        } elseif (!empty($name) && !empty($category) && $base_price > 0) {
            try {
                $conn->begin_transaction();

                $stmt = $conn->prepare("INSERT INTO customization_templates (name, description, category, base_price, stock_quantity, is_active, image_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssdiis", $name, $description, $category, $base_price, $stock_quantity, $is_active, $uploadedImagePath);
                $ok = $stmt->execute();
                $templateId = (int)$conn->insert_id;
                $stmt->close();

                if (!$ok || $templateId <= 0) {
                    throw new RuntimeException('Failed to add product.');
                }

                $altText = $name . ' - Primary';
                $isPrimary = 1;
                $imgStock = max(0, $stock_quantity);
                $imgStmt = $conn->prepare("INSERT INTO product_images (template_id, image_path, is_primary, alt_text, stock_quantity) VALUES (?, ?, ?, ?, ?)");
                if (!$imgStmt) {
                    throw new RuntimeException('Failed to prepare product image insert.');
                }
                $imgStmt->bind_param("isisi", $templateId, $uploadedImagePath, $isPrimary, $altText, $imgStock);
                if (!$imgStmt->execute()) {
                    $imgStmt->close();
                    throw new RuntimeException('Failed to save product image.');
                }
                $imgStmt->close();

                $conn->commit();
                $message = "Product added successfully!";
                rbj_admin_log(
                    $conn,
                    (int)$_SESSION['user_id'],
                    'add_product',
                    'product',
                    $templateId,
                    ['name' => $name, 'category' => $category, 'stock_quantity' => $stock_quantity]
                );
            } catch (Throwable $e) {
                $conn->rollback();
                if ($uploadedImagePath !== '') {
                    $absUploaded = __DIR__ . '/../' . $uploadedImagePath;
                    if (is_file($absUploaded)) {
                        @unlink($absUploaded);
                    }
                }
                $message = "Failed to add product.";
            }
        } else {
            $message = "Please fill in all required fields.";
        }
    } elseif (isset($_POST['update_product'])) {
        $id = (int)$_POST['product_id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $category = trim($_POST['category']);
        $base_price = (float)$_POST['base_price'];
        $stock_quantity = max(0, (int)($_POST['stock_quantity'] ?? 0));
        $variant_stock = $_POST['variant_stock'] ?? [];
        $choice_stock = $_POST['choice_stock'] ?? [];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (!empty($name) && !empty($category) && $base_price > 0) {
            $stmt = $conn->prepare("UPDATE customization_templates SET name = ?, description = ?, category = ?, base_price = ?, stock_quantity = ?, is_active = ? WHERE id = ?");
            $stmt->bind_param("sssdiii", $name, $description, $category, $base_price, $stock_quantity, $is_active, $id);

            if ($stmt->execute()) {
                $updated_items = $apply_item_stock_updates($conn, $id, $variant_stock, $choice_stock, $stock_quantity);
                $message = $updated_items > 0
                    ? "Product and item stocks updated successfully!"
                    : "Product updated successfully!";
                rbj_admin_log(
                    $conn,
                    (int)$_SESSION['user_id'],
                    'update_product',
                    'product',
                    $id,
                    ['name' => $name, 'category' => $category, 'stock_quantity' => $stock_quantity, 'updated_items' => $updated_items]
                );
            } else {
                $message = "Failed to update product.";
            }
            $stmt->close();
        } else {
            $message = "Please fill in all required fields.";
        }
    } elseif (isset($_POST['delete_product'])) {
        $id = (int)$_POST['product_id'];

        $stmt = $conn->prepare("DELETE FROM customization_templates WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $message = "Product deleted successfully!";
            rbj_admin_log(
                $conn,
                (int)$_SESSION['user_id'],
                'delete_product',
                'product',
                $id
            );
        } else {
            $message = "Failed to delete product.";
        }
        $stmt->close();
    } elseif (isset($_POST['update_variant_stock'])) {
        $product_id = (int)($_POST['product_id'] ?? 0);
        $variant_stock = $_POST['variant_stock'] ?? [];
        $choice_stock = $_POST['choice_stock'] ?? [];
        $posted_product_stock = trim((string)($_POST['product_stock_quantity'] ?? ''));
        $product_stock_quantity = $posted_product_stock === '' ? null : max(0, (int)$posted_product_stock);
        if ($product_id > 0 && (is_array($variant_stock) || is_array($choice_stock))) {
            $updated = $apply_item_stock_updates($conn, $product_id, $variant_stock, $choice_stock, $product_stock_quantity);
            $message = $updated > 0 ? "Variant stock updated successfully!" : "No variant stock changes applied.";
            if ($updated > 0) {
                rbj_admin_log(
                    $conn,
                    (int)$_SESSION['user_id'],
                    'update_variant_stock',
                    'product',
                    $product_id,
                    ['updated_items' => $updated]
                );
            }
        } else {
            $message = "Invalid product variant stock request.";
        }
    } elseif (isset($_POST['bulk_action'])) {
        $action = trim((string)($_POST['bulk_action'] ?? ''));
        $selected_ids = $_POST['selected_products'] ?? [];
        $selected_ids = is_array($selected_ids) ? array_values(array_unique(array_map('intval', $selected_ids))) : [];
        $selected_ids = array_values(array_filter($selected_ids, static function (int $id): bool {
            return $id > 0;
        }));

        if (empty($selected_ids)) {
            $message = "Please select at least one product.";
        } else {
            $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
            $types = str_repeat('i', count($selected_ids));

            if ($action === 'delete') {
                $sql = "DELETE FROM customization_templates WHERE id IN ({$placeholders})";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param($types, ...$selected_ids);
                    $ok = $stmt->execute();
                    $affected = $stmt->affected_rows;
                    $stmt->close();
                    $message = $ok ? "{$affected} product(s) deleted successfully!" : "Failed to delete selected products.";
                    if ($ok) {
                        rbj_admin_log(
                            $conn,
                            (int)$_SESSION['user_id'],
                            'bulk_delete_products',
                            'product',
                            null,
                            ['count' => (int)$affected, 'ids' => $selected_ids]
                        );
                    }
                } else {
                    $message = "Failed to prepare delete statement.";
                }
            } elseif ($action === 'activate' || $action === 'deactivate') {
                $new_status = $action === 'activate' ? 1 : 0;
                $sql = "UPDATE customization_templates SET is_active = ? WHERE id IN ({$placeholders})";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $bindTypes = 'i' . $types;
                    $bindValues = array_merge([$new_status], $selected_ids);
                    $stmt->bind_param($bindTypes, ...$bindValues);
                    $ok = $stmt->execute();
                    $affected = $stmt->affected_rows;
                    $stmt->close();
                    $message = $ok
                        ? "{$affected} product(s) " . ($new_status === 1 ? "activated" : "deactivated") . " successfully!"
                        : "Failed to update selected products.";
                    if ($ok) {
                        rbj_admin_log(
                            $conn,
                            (int)$_SESSION['user_id'],
                            $new_status === 1 ? 'bulk_activate_products' : 'bulk_deactivate_products',
                            'product',
                            null,
                            ['count' => (int)$affected, 'ids' => $selected_ids]
                        );
                    }
                } else {
                    $message = "Failed to prepare status update statement.";
                }
            } else {
                $message = "Invalid bulk action request.";
            }
        }
    }
}

// Get all products
$stmt = $conn->prepare("
    SELECT
        t.*,
        (
            SELECT p.image_path
            FROM product_images p
            WHERE p.template_id = t.id
            ORDER BY p.is_primary DESC, p.id ASC
            LIMIT 1
        ) AS preview_image_path,
        (
            SELECT COUNT(*)
            FROM product_images p
            WHERE p.template_id = t.id
        ) AS variant_count
    FROM customization_templates t
    ORDER BY t.created_at DESC
");
$stmt->execute();
$products = $stmt->get_result();

$variant_map = [];
$variantStmt = $conn->prepare("
    SELECT id, template_id, image_path, alt_text, is_primary, stock_quantity
    FROM product_images
    ORDER BY template_id ASC, is_primary DESC, id ASC
");
if ($variantStmt) {
    $variantStmt->execute();
    $variantRes = $variantStmt->get_result();
    while ($vr = $variantRes->fetch_assoc()) {
        $tid = (int)$vr['template_id'];
        if (!isset($variant_map[$tid])) {
            $variant_map[$tid] = [];
        }
        $variant_map[$tid][] = [
            'source' => 'db',
            'id' => (int)$vr['id'],
            'choice_key' => '',
            'label' => trim((string)$vr['alt_text']) !== '' ? (string)$vr['alt_text'] : rbj_shapi_humanize(pathinfo((string)$vr['image_path'], PATHINFO_FILENAME)),
            'image_url' => rbj_template_image_url((string)$vr['image_path']),
            'is_primary' => (int)$vr['is_primary'],
            'stock_quantity' => (int)$vr['stock_quantity']
        ];
    }
    $variantStmt->close();
}

$choice_stock_map = [];
$choiceStockStmt = $conn->prepare("SELECT template_id, choice_key, choice_label, image_url, stock_quantity FROM customization_choice_stock");
if ($choiceStockStmt) {
    $choiceStockStmt->execute();
    $choiceStockRes = $choiceStockStmt->get_result();
    while ($cs = $choiceStockRes->fetch_assoc()) {
        $tid = (int)$cs['template_id'];
        if (!isset($choice_stock_map[$tid])) {
            $choice_stock_map[$tid] = [];
        }
        $choice_stock_map[$tid][(string)$cs['choice_key']] = [
            'label' => (string)$cs['choice_label'],
            'image_url' => (string)$cs['image_url'],
            'stock_quantity' => (int)$cs['stock_quantity']
        ];
    }
    $choiceStockStmt->close();
}

// Always include persisted choice stocks so admin can edit even if Shapi matching changes.
foreach ($choice_stock_map as $tid => $choicesByKey) {
    if (!isset($variant_map[$tid])) {
        $variant_map[$tid] = [];
    }
    $existingChoiceKeys = [];
    $existingUrls = [];
    foreach ($variant_map[$tid] as $existingVariant) {
        $existingKey = trim((string)($existingVariant['choice_key'] ?? ''));
        if ($existingKey !== '') {
            $existingChoiceKeys[$existingKey] = true;
        }
        $existingUrl = trim((string)($existingVariant['image_url'] ?? ''));
        if ($existingUrl !== '') {
            $existingUrls[$existingUrl] = true;
        }
    }
    foreach ($choicesByKey as $choiceKey => $savedChoice) {
        $savedImageUrl = trim((string)($savedChoice['image_url'] ?? ''));
        if (isset($existingChoiceKeys[$choiceKey])) {
            continue;
        }
        if ($savedImageUrl !== '' && isset($existingUrls[$savedImageUrl])) {
            continue;
        }
        $variant_map[$tid][] = [
            'source' => 'choice',
            'id' => 0,
            'choice_key' => (string)$choiceKey,
            'label' => trim((string)($savedChoice['label'] ?? '')) !== '' ? (string)$savedChoice['label'] : 'Item',
            'image_url' => $savedImageUrl,
            'is_primary' => 0,
            'stock_quantity' => (int)($savedChoice['stock_quantity'] ?? 0)
        ];
        $existingChoiceKeys[$choiceKey] = true;
        if ($savedImageUrl !== '') {
            $existingUrls[$savedImageUrl] = true;
        }
    }
}

$templateChoicesStmt = $conn->prepare("SELECT id, name FROM customization_templates");
if ($templateChoicesStmt) {
    $templateChoicesStmt->execute();
    $templateChoicesRes = $templateChoicesStmt->get_result();
    while ($tp = $templateChoicesRes->fetch_assoc()) {
        $tid = (int)$tp['id'];
        $choices = rbj_find_shapi_choices((string)$tp['name']);
        if (empty($choices)) {
            continue;
        }
        if (!isset($variant_map[$tid])) {
            $variant_map[$tid] = [];
        }
        $existingUrls = [];
        $existingChoiceKeys = [];
        foreach ($variant_map[$tid] as $existingVariant) {
            $existingUrl = trim((string)($existingVariant['image_url'] ?? ''));
            if ($existingUrl !== '') {
                $existingUrls[$existingUrl] = true;
            }
            $existingChoiceKey = trim((string)($existingVariant['choice_key'] ?? ''));
            if ($existingChoiceKey !== '') {
                $existingChoiceKeys[$existingChoiceKey] = true;
            }
        }
        foreach ($choices as $choice) {
            $imageUrl = trim((string)($choice['image_url'] ?? ''));
            $choiceLabel = trim((string)($choice['label'] ?? 'Item'));
            $choiceKey = $build_choice_key($choiceLabel, $imageUrl);
            if (isset($existingChoiceKeys[$choiceKey])) {
                continue;
            }
            if ($imageUrl !== '' && isset($existingUrls[$imageUrl])) {
                continue;
            }
            $saved = $choice_stock_map[$tid][$choiceKey] ?? null;
            $variant_map[$tid][] = [
                'source' => 'choice',
                'id' => 0,
                'choice_key' => $choiceKey,
                'label' => $saved['label'] ?? $choiceLabel,
                'image_url' => $saved['image_url'] ?? $imageUrl,
                'is_primary' => 0,
                'stock_quantity' => (int)($saved['stock_quantity'] ?? 0)
            ];
            $existingChoiceKeys[$choiceKey] = true;
            if ($imageUrl !== '') {
                $existingUrls[$imageUrl] = true;
            }
        }
    }
    $templateChoicesStmt->close();
}

// Get categories for filter
$categories_query = "SELECT DISTINCT category FROM customization_templates ORDER BY category";
$categories = $conn->query($categories_query);
$category_options = [];
if ($categories instanceof mysqli_result) {
    while ($catRow = $categories->fetch_assoc()) {
        $catValue = trim((string)($catRow['category'] ?? ''));
        if ($catValue !== '') {
            $category_options[] = $catValue;
        }
    }
}
if (empty($category_options)) {
    $category_options = ['seats', 'backrests', 'grips', 'lighting', 'other'];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Product Management - RBJ Accessories</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link href="assets/admin-enhancements.css" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, sans-serif; }
body { background: #f4f6f8; }
.admin-container { display: flex; height: 100vh; }
/* Sidebar */
.sidebar { width: 220px; background: #111; color: white; padding: 20px; }
.sidebar h2 { margin-bottom: 30px; }
.sidebar nav a { display: block; color: white; text-decoration: none; padding: 10px; margin-bottom: 5px; }
.sidebar nav a:hover, .sidebar nav a.active { background: #444; border-radius: 5px; }
/* Content */
.content { flex: 1; padding: 30px; overflow-y: auto; }
.content h1 { margin-bottom: 20px; }
/* Header */
.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
.add-btn { background: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; }
.add-btn:hover { background: #2980b9; }
/* Message */
.message { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
/* Products Table */
.products-table { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
.table-header { background: #f8f9fa; padding: 15px 20px; border-bottom: 1px solid #dee2e6; }
.table-header h2 { margin: 0; color: #2c3e50; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #dee2e6; }
th { background: #f8f9fa; font-weight: 600; color: #2c3e50; }
tr:hover { background: #f8f9fa; }
.status { padding: 4px 8px; border-radius: 12px; font-size: 0.8em; font-weight: bold; }
.status.active { background: #d4edda; color: #155724; }
.status.inactive { background: #f8d7da; color: #721c24; }
.actions { display: flex; gap: 5px; }
.btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9em; }
.btn-edit { background: #ffc107; color: #212529; }
.btn-edit:hover { background: #e0a800; }
.btn-delete { background: #dc3545; color: white; }
.btn-delete:hover { background: #c82333; }
.btn-variant { background: #17a2b8; color: #fff; }
.btn-variant:hover { background: #138496; }
.product-preview {
  width: 52px;
  height: 52px;
  border-radius: 8px;
  object-fit: cover;
  border: 1px solid #dee2e6;
  background: #f1f3f5;
}
.variant-count-badge {
  display: inline-block;
  margin-left: 8px;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  background: #eaf6ff;
  color: #0b6aa2;
}
.variant-stock-list {
  max-height: 360px;
  overflow: auto;
  border: 1px solid #e1e5ea;
  border-radius: 8px;
}
.variant-row {
  display: grid;
  grid-template-columns: 56px 1fr 120px;
  gap: 10px;
  align-items: center;
  padding: 10px;
  border-bottom: 1px solid #eef1f4;
}
.variant-row:last-child { border-bottom: 0; }
.variant-row img {
  width: 48px;
  height: 48px;
  border-radius: 6px;
  object-fit: cover;
  border: 1px solid #dde3ea;
}
.variant-label {
  font-size: 13px;
  color: #2c3e50;
}
.variant-primary {
  font-size: 11px;
  color: #0b6aa2;
  font-weight: 700;
}
.variant-stock-input {
  width: 100%;
  padding: 8px;
  border: 1px solid #d5dbe3;
  border-radius: 6px;
}
.product-open-trigger {
  border: 0;
  background: transparent;
  padding: 0;
  margin: 0;
  cursor: pointer;
  text-align: left;
}
.product-open-trigger .name-link {
  color: #0b6aa2;
  text-decoration: underline;
}
.preview-gallery-main {
  width: 100%;
  height: 360px;
  border-radius: 10px;
  border: 1px solid #dfe5ec;
  background: #f5f7fa;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}
.preview-gallery-main img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}
.preview-gallery-thumbs {
  margin-top: 10px;
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 8px;
  max-height: 180px;
  overflow: auto;
}
.preview-gallery-thumb {
  border: 1px solid #d7dde5;
  border-radius: 8px;
  padding: 4px;
  background: #fff;
  cursor: pointer;
}
.preview-gallery-thumb.active {
  border-color: #0b6aa2;
  box-shadow: inset 0 0 0 1px rgba(11,106,162,0.22);
}
.preview-gallery-thumb img {
  width: 100%;
  height: 62px;
  object-fit: cover;
  border-radius: 6px;
}
.preview-gallery-meta {
  margin-top: 3px;
  font-size: 11px;
  color: #435161;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
/* Modal */
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
.modal-content { background: white; margin: 5% auto; padding: 20px; border-radius: 8px; width: 90%; max-width: 500px; }
#productModal .modal-content {
  max-height: 88vh;
  overflow-y: auto;
}
.close { float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
.close:hover { color: #aaa; }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #dee2e6; border-radius: 4px; }
.form-group textarea { resize: vertical; min-height: 80px; }
.checkbox-group { display: flex; align-items: center; }
.checkbox-group input { width: auto; margin-right: 8px; }
@media (max-width: 900px) { .header { flex-direction: column; gap: 20px; } }
</style>
</head>
<body>
<div class="admin-container">
  <aside class="sidebar">
    <div class="logo" style="text-align: center; margin-bottom: 20px;">
      <a href="/rbjsystem/ADMIN/dashboard_admin.php">
        <img src="/rbjsystem/rbjlogo.png" alt="RBJ Logo" style="height: 100px; width: auto; display: block; margin: 0 auto;">
      </a>
    </div>
    <nav>
      <a href="/rbjsystem/ADMIN/dashboard_admin.php">Dashboard</a>
      <a href="/rbjsystem/ADMIN/users_admin.php">Users</a>
      <a href="/rbjsystem/ADMIN/orders_admin.php">Orders</a>
      <a href="/rbjsystem/ADMIN/products_admin.php" class="active">Products</a>
      <a href="/rbjsystem/ADMIN/vouchers_admin.php">Vouchers</a>
      <a href="/rbjsystem/ADMIN/feedback_admin.php">Feedbacks</a>
      <a href="/rbjsystem/ADMIN/admin_support.php">Support</a>
      <a href="/rbjsystem/ADMIN/activity_logs_admin.php">Activity Logs</a>
      <a href="/rbjsystem/ADMIN/live_chat.php">Live Chat</a>
      <a href="/rbjsystem/logout.php">Logout</a>
    </nav>
  </aside>

  <main class="content">
    <div class="header">
        <h1>Product Management</h1>
        <button class="add-btn" onclick="openModal('add')">Add New Product</button>
    </div>

    <?php if (!empty($message)): ?>
        <div class="message"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="products-table">
        <div class="table-header">
            <h2>All Products</h2>
            <div class="bulk-actions" style="margin-top: 10px;">
                <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this.checked)">
                <label for="selectAll" style="margin-left: 5px; font-weight: normal;">Select All</label>
                <button class="btn btn-delete" onclick="bulkDelete()" style="margin-left: 10px;">Delete Selected</button>
                <button class="btn btn-edit" onclick="bulkActivate()" style="margin-left: 5px;">Activate Selected</button>
                <button class="btn btn-edit" onclick="bulkDeactivate()" style="margin-left: 5px;">Deactivate Selected</button>
            </div>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th><input type="checkbox" id="headerCheckbox" onchange="toggleSelectAll(this.checked)"></th>
                        <th>ID</th>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Price (₱)</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($product = $products->fetch_assoc()): ?>
                        <tr>
                            <td><input type="checkbox" class="product-checkbox" value="<?php echo $product['id']; ?>"></td>
                            <td><?php echo $product['id']; ?></td>
                            <td>
                                <?php $previewUrl = rbj_template_image_url((string)($product['preview_image_path'] ?? $product['image_path'] ?? '')); ?>
                                <?php if ($previewUrl !== ''): ?>
                                    <button type="button" class="product-open-trigger" onclick="openProductPreviewModal(<?php echo (int)$product['id']; ?>, <?php echo json_encode($product['name']); ?>)">
                                        <img class="product-preview" src="<?php echo htmlspecialchars($previewUrl); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="product-open-trigger" onclick="openProductPreviewModal(<?php echo (int)$product['id']; ?>, <?php echo json_encode($product['name']); ?>)">
                                        <div class="product-preview" style="display:flex;align-items:center;justify-content:center;color:#9aa4af;"><i class='bx bx-image'></i></div>
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                    $variantCount = isset($variant_map[(int)$product['id']]) ? count($variant_map[(int)$product['id']]) : (int)($product['variant_count'] ?? 0);
                                ?>
                                <button type="button" class="product-open-trigger" onclick="openProductPreviewModal(<?php echo (int)$product['id']; ?>, <?php echo json_encode($product['name']); ?>)">
                                    <span class="name-link"><?php echo htmlspecialchars($product['name']); ?></span>
                                </button>
                                <span class="variant-count-badge"><?php echo (int)$variantCount; ?> items</span>
                            </td>
                            <td><?php echo htmlspecialchars($product['category']); ?></td>
                            <td>₱<?php echo number_format($product['base_price'], 2); ?></td>
                            <td><?php echo (int)($product['stock_quantity'] ?? 0); ?></td>
                            <td><span class="status <?php echo $product['is_active'] ? 'active' : 'inactive'; ?>"><?php echo $product['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                            <td><?php echo date('M j, Y', strtotime($product['created_at'])); ?></td>
                            <td class="actions">
                                <button class="btn btn-edit" onclick="editProduct(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>', '<?php echo addslashes($product['description']); ?>', '<?php echo addslashes($product['category']); ?>', <?php echo $product['base_price']; ?>, <?php echo (int)($product['stock_quantity'] ?? 0); ?>, <?php echo $product['is_active']; ?>)">Edit</button>
                                <button class="btn btn-variant" onclick="openVariantStockModal(<?php echo (int)$product['id']; ?>, <?php echo json_encode($product['name']); ?>, <?php echo (int)($product['stock_quantity'] ?? 0); ?>)">Item Stocks</button>
                                <button class="btn btn-delete" onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>')">Delete</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Product Modal -->
<div id="productModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h2 id="modalTitle">Add New Product</h2>
        <form method="POST" action="products_admin.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="product_id" id="productId">

            <div class="form-group">
                <label for="name">Product Name *</label>
                <input type="text" id="name" name="name" required>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description"></textarea>
            </div>

            <div class="form-group">
                <label for="category">Category *</label>
                <select id="category" name="category" required>
                    <option value="">Select Category</option>
                    <?php foreach ($category_options as $catOption): ?>
                        <option value="<?php echo htmlspecialchars($catOption); ?>"><?php echo htmlspecialchars(ucwords($catOption)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="base_price">Base Price *</label>
                <input type="number" id="base_price" name="base_price" step="0.01" min="0" required>
            </div>
            <div class="form-group" id="productImageGroup">
                <label for="product_image">Product Image *</label>
                <input type="file" id="product_image" name="product_image" accept=".jpg,.jpeg,.png,.webp" required>
                <div id="productImageHint" style="margin-top:6px;font-size:12px;color:#6c757d;">Required when adding a new product.</div>
            </div>
            <div class="form-group">
                <label for="stock_quantity">Available Stock *</label>
                <input type="number" id="stock_quantity" name="stock_quantity" min="0" value="0" required>
            </div>
            <div class="form-group" id="editChoiceStockSection" style="display:none;">
                <label>Per-Choice Item Stocks</label>
                <div class="variant-stock-list" id="editChoiceStockList"></div>
                <div style="margin-top:8px;">
                    <button type="button" class="btn btn-variant" onclick="useComputedItemTotalFrom('editChoiceStockList', 'stock_quantity')">Use Item Total</button>
                </div>
                <div style="margin-top:6px;font-size:12px;color:#6c757d;">
                    Edit each color/design stock here. This saves with Update Product.
                </div>
            </div>

            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" id="is_active" name="is_active" checked>
                    <label for="is_active">Active (visible to customers)</label>
                </div>
            </div>

            <button type="submit" name="add_product" id="submitBtn" class="add-btn">Add Product</button>
            <button type="submit" name="update_product" id="updateBtn" class="add-btn" style="display: none;">Update Product</button>
        </form>
    </div>
</div>

<!-- Product Preview Modal -->
<div id="productPreviewModal" class="modal">
    <div class="modal-content" style="max-width: 920px;">
        <span class="close" onclick="closeProductPreviewModal()">&times;</span>
        <h2 id="productPreviewTitle">Product Preview</h2>
        <div class="preview-gallery-main" id="previewGalleryMain">
            <div style="color:#8a95a3;">No image available</div>
        </div>
        <div class="preview-gallery-thumbs" id="previewGalleryThumbs"></div>
    </div>
</div>

<!-- Variant Stock Modal -->
<div id="variantStockModal" class="modal">
    <div class="modal-content" style="max-width: 760px;">
        <span class="close" onclick="closeVariantStockModal()">&times;</span>
        <h2 id="variantModalTitle">Variant Stocks</h2>
        <form method="POST" action="products_admin.php" id="variantStockForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="product_id" id="variantProductId">
            <input type="hidden" name="update_variant_stock" value="1">
            <div style="display:flex;gap:10px;align-items:end;margin-bottom:12px;flex-wrap:wrap;">
                <div style="min-width:220px;">
                    <label for="productStockQuantity" style="display:block;margin-bottom:6px;font-weight:600;">Product Total Stock</label>
                    <input id="productStockQuantity" type="number" min="0" name="product_stock_quantity" class="variant-stock-input" value="0">
                </div>
                <button type="button" class="btn btn-variant" onclick="useComputedItemTotal()">Use Item Total</button>
            </div>
            <div id="variantStockList" class="variant-stock-list"></div>
            <div style="margin-top: 14px; display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn" onclick="closeVariantStockModal()">Cancel</button>
                <button type="submit" class="add-btn">Save Item Stocks</button>
            </div>
        </form>
    </div>
</div>

<script>
const variantStockMap = <?php echo json_encode($variant_map, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const csrfToken = <?php echo json_encode($csrf_token, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
function escapeHtml(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function getSelectedProductIds() {
    return Array.from(document.querySelectorAll('.product-checkbox:checked')).map(cb => cb.value);
}

function syncSelectAllCheckboxes() {
    const rowCheckboxes = Array.from(document.querySelectorAll('.product-checkbox'));
    const allChecked = rowCheckboxes.length > 0 && rowCheckboxes.every(cb => cb.checked);
    const anyChecked = rowCheckboxes.some(cb => cb.checked);
    const selectAll = document.getElementById('selectAll');
    const headerCheckbox = document.getElementById('headerCheckbox');
    if (selectAll) {
        selectAll.checked = allChecked;
        selectAll.indeterminate = !allChecked && anyChecked;
    }
    if (headerCheckbox) {
        headerCheckbox.checked = allChecked;
        headerCheckbox.indeterminate = !allChecked && anyChecked;
    }
}

function toggleSelectAll(forceChecked = null) {
    const selectAll = document.getElementById('selectAll');
    const headerCheckbox = document.getElementById('headerCheckbox');
    const rowCheckboxes = document.querySelectorAll('.product-checkbox');
    const targetChecked = typeof forceChecked === 'boolean'
        ? forceChecked
        : ((selectAll && selectAll.checked) || (headerCheckbox && headerCheckbox.checked));
    rowCheckboxes.forEach(cb => cb.checked = targetChecked);
    if (selectAll) selectAll.checked = targetChecked;
    if (headerCheckbox) headerCheckbox.checked = targetChecked;
}

function submitBulkAction(action) {
    const selected = getSelectedProductIds();
    if (selected.length === 0) {
        alert('Please select at least one product.');
        return;
    }

    const actionLabel = action === 'delete'
        ? 'delete'
        : (action === 'activate' ? 'activate' : 'deactivate');
    if (!confirm(`Are you sure you want to ${actionLabel} ${selected.length} selected product(s)?`)) {
        return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'products_admin.php';

    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'bulk_action';
    actionInput.value = action;
    form.appendChild(actionInput);

    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = csrfToken;
    form.appendChild(csrfInput);

    selected.forEach((id) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'selected_products[]';
        input.value = id;
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
}

function bulkDelete() {
    submitBulkAction('delete');
}

function bulkActivate() {
    submitBulkAction('activate');
}

function bulkDeactivate() {
    submitBulkAction('deactivate');
}

function openModal(mode, productData = null) {
    const modal = document.getElementById('productModal');
    const modalTitle = document.getElementById('modalTitle');
    const submitBtn = document.getElementById('submitBtn');
    const updateBtn = document.getElementById('updateBtn');
    const editChoiceSection = document.getElementById('editChoiceStockSection');
    const editChoiceList = document.getElementById('editChoiceStockList');
    const imageInput = document.getElementById('product_image');
    const imageHint = document.getElementById('productImageHint');
    const form = modal.querySelector('form');

    if (mode === 'add') {
        modalTitle.textContent = 'Add New Product';
        submitBtn.style.display = 'block';
        updateBtn.style.display = 'none';
        form.reset();
        document.getElementById('productId').value = '';
        if (editChoiceSection) {
            editChoiceSection.style.display = 'none';
        }
        if (editChoiceList) {
            editChoiceList.innerHTML = '';
        }
        if (imageInput) {
            imageInput.required = true;
            imageInput.value = '';
        }
        if (imageHint) {
            imageHint.textContent = 'Required when adding a new product.';
        }
    }

    modal.style.display = 'block';
}

function editProduct(id, name, description, category, price, stockQuantity, isActive) {
    const modal = document.getElementById('productModal');
    const modalTitle = document.getElementById('modalTitle');
    const submitBtn = document.getElementById('submitBtn');
    const updateBtn = document.getElementById('updateBtn');

    modalTitle.textContent = 'Edit Product';
    submitBtn.style.display = 'none';
    updateBtn.style.display = 'block';

    document.getElementById('productId').value = id;
    document.getElementById('name').value = name;
    document.getElementById('description').value = description;
    document.getElementById('category').value = category;
    document.getElementById('base_price').value = price;
    document.getElementById('stock_quantity').value = stockQuantity;
    document.getElementById('is_active').checked = isActive;
    const imageInput = document.getElementById('product_image');
    const imageHint = document.getElementById('productImageHint');
    if (imageInput) {
        imageInput.required = false;
        imageInput.value = '';
    }
    if (imageHint) {
        imageHint.textContent = 'Optional while editing. Use Item Stocks below for per-choice images and stock.';
    }
    renderChoiceStocksForEdit(id);

    modal.style.display = 'block';
}

function deleteProduct(id, name) {
    if (confirm(`Are you sure you want to delete "${name}"? This action cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'products_admin.php';

        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'product_id';
        idInput.value = id;

        const deleteInput = document.createElement('input');
        deleteInput.type = 'hidden';
        deleteInput.name = 'delete_product';
        deleteInput.value = '1';

        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = csrfToken;

        form.appendChild(idInput);
        form.appendChild(deleteInput);
        form.appendChild(csrfInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function closeModal() {
    document.getElementById('productModal').style.display = 'none';
}

function openProductPreviewModal(productId, productName) {
    const modal = document.getElementById('productPreviewModal');
    const title = document.getElementById('productPreviewTitle');
    const main = document.getElementById('previewGalleryMain');
    const thumbs = document.getElementById('previewGalleryThumbs');
    const variants = variantStockMap[String(productId)] || variantStockMap[productId] || [];
    title.textContent = `Product Images - ${productName}`;

    if (!variants.length) {
        main.innerHTML = '<div style="color:#8a95a3;">No image available</div>';
        thumbs.innerHTML = '';
        modal.style.display = 'block';
        return;
    }

    const displayImage = function (imageUrl, label) {
        if (!imageUrl) {
            main.innerHTML = '<div style="color:#8a95a3;">No image available</div>';
            return;
        }
        main.innerHTML = `<img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(label || productName)}">`;
    };

    const firstWithImage = variants.find(v => (v.image_url || '').trim() !== '') || variants[0];
    displayImage(firstWithImage.image_url || '', firstWithImage.label || productName);

    thumbs.innerHTML = variants.map((v, i) => {
        const safeLabel = escapeHtml(v.label || `Item ${i + 1}`);
        const safeImageUrl = escapeHtml(v.image_url || '');
        const activeClass = (v === firstWithImage) ? ' active' : '';
        return `
            <button type="button" class="preview-gallery-thumb${activeClass}" data-thumb-index="${i}">
                ${safeImageUrl ? `<img src="${safeImageUrl}" alt="${safeLabel}">` : `<div style="height:62px;display:flex;align-items:center;justify-content:center;color:#8a95a3;"><i class='bx bx-image'></i></div>`}
                <div class="preview-gallery-meta">${safeLabel}</div>
            </button>
        `;
    }).join('');

    Array.from(thumbs.querySelectorAll('.preview-gallery-thumb')).forEach((btn) => {
        btn.addEventListener('click', function () {
            const idx = Number(btn.getAttribute('data-thumb-index')) || 0;
            const item = variants[idx] || variants[0];
            displayImage(item.image_url || '', item.label || productName);
            thumbs.querySelectorAll('.preview-gallery-thumb').forEach(el => el.classList.remove('active'));
            btn.classList.add('active');
        });
    });

    modal.style.display = 'block';
}

function closeProductPreviewModal() {
    document.getElementById('productPreviewModal').style.display = 'none';
}

function openVariantStockModal(productId, productName, currentProductStock) {
    const modal = document.getElementById('variantStockModal');
    const list = document.getElementById('variantStockList');
    const title = document.getElementById('variantModalTitle');
    const productIdInput = document.getElementById('variantProductId');
    const totalStockInput = document.getElementById('productStockQuantity');
    productIdInput.value = productId;
    title.textContent = `Item Stocks - ${productName}`;
    totalStockInput.value = Number(currentProductStock) || 0;

    list.innerHTML = renderVariantStockRows(productId);

    modal.style.display = 'block';
}

function closeVariantStockModal() {
    document.getElementById('variantStockModal').style.display = 'none';
}

function useComputedItemTotal() {
    useComputedItemTotalFrom('variantStockList', 'productStockQuantity');
}

function useComputedItemTotalFrom(listId, inputId) {
    const list = document.getElementById(listId);
    const totalStockInput = document.getElementById(inputId);
    if (!list || !totalStockInput) return;
    const inputs = Array.from(list.querySelectorAll('input.variant-stock-input[type="number"]'));
    const sum = inputs.reduce((acc, input) => acc + (Math.max(0, parseInt(input.value, 10) || 0)), 0);
    totalStockInput.value = sum;
}

function renderVariantStockRows(productId) {
    const variants = variantStockMap[String(productId)] || variantStockMap[productId] || [];
    if (!variants.length) {
        return '<div style="padding:14px;color:#6c757d;">No per-item images found for this product yet.</div>';
    }
    return variants.map((v) => {
        const primaryBadge = Number(v.is_primary) === 1 ? '<div class="variant-primary">PRIMARY</div>' : '';
        const safeLabel = escapeHtml(v.label || 'Item');
        const imageHtml = v.image_url
            ? `<img src="${v.image_url}" alt="${safeLabel}">`
            : `<div style="width:48px;height:48px;border-radius:6px;background:#f1f3f5;display:flex;align-items:center;justify-content:center;color:#9aa4af;"><i class='bx bx-image'></i></div>`;
        const inputName = (v.source === 'choice' && v.choice_key)
            ? `choice_stock[${escapeHtml(v.choice_key)}][stock]`
            : `variant_stock[${Number(v.id)}]`;
        const hiddenFields = (v.source === 'choice' && v.choice_key)
            ? `
                <input type="hidden" name="choice_stock[${escapeHtml(v.choice_key)}][label]" value="${safeLabel}">
                <input type="hidden" name="choice_stock[${escapeHtml(v.choice_key)}][image_url]" value="${escapeHtml(v.image_url || '')}">
              `
            : '';
        return `
            <div class="variant-row">
                <div>${imageHtml}</div>
                <div class="variant-label">${safeLabel}${primaryBadge}</div>
                <div>
                    ${hiddenFields}
                    <input class="variant-stock-input" type="number" min="0" name="${inputName}" value="${Number(v.stock_quantity) || 0}">
                </div>
            </div>
        `;
    }).join('');
}

function renderChoiceStocksForEdit(productId) {
    const section = document.getElementById('editChoiceStockSection');
    const list = document.getElementById('editChoiceStockList');
    if (!section || !list) return;
    section.style.display = 'block';
    list.innerHTML = renderVariantStockRows(productId);
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('productModal');
    const previewModal = document.getElementById('productPreviewModal');
    const variantModal = document.getElementById('variantStockModal');
    if (event.target == modal) modal.style.display = 'none';
    if (event.target == previewModal) previewModal.style.display = 'none';
    if (event.target == variantModal) variantModal.style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function () {
    const rowCheckboxes = document.querySelectorAll('.product-checkbox');
    rowCheckboxes.forEach((cb) => {
        cb.addEventListener('change', syncSelectAllCheckboxes);
    });
    syncSelectAllCheckboxes();
});
</script>

</body>
</html>






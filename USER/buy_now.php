<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

include '../config.php';
require_once __DIR__ . '/shapi_catalog_helper.php';
rbj_ensure_cart_choice_key_column($conn);
if (!function_exists('rbj_ensure_payment_proofs_table')) {
    function rbj_ensure_payment_proofs_table(mysqli $conn): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS payment_proofs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                payment_id INT DEFAULT NULL,
                user_id INT NOT NULL,
                payment_channel VARCHAR(40) NOT NULL,
                reference_number VARCHAR(120) DEFAULT NULL,
                proof_path VARCHAR(255) DEFAULT NULL,
                status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
                admin_notes VARCHAR(255) DEFAULT NULL,
                verified_by INT DEFAULT NULL,
                verified_at TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_payment_proofs_order (order_id),
                INDEX idx_payment_proofs_user (user_id),
                INDEX idx_payment_proofs_status (status),
                CONSTRAINT fk_payment_proofs_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
                CONSTRAINT fk_payment_proofs_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL,
                CONSTRAINT fk_payment_proofs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ";
        $conn->query($sql);
    }
}
rbj_ensure_payment_proofs_table($conn);
if (!function_exists('rbj_ensure_shop_vouchers_table')) {
    function rbj_ensure_shop_vouchers_table(mysqli $conn): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS shop_vouchers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(50) NOT NULL UNIQUE,
                name VARCHAR(120) NOT NULL,
                voucher_type ENUM('free_shipping', 'fixed_discount') NOT NULL,
                amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                min_spend DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                start_at DATETIME NULL DEFAULT NULL,
                end_at DATETIME NULL DEFAULT NULL,
                usage_limit INT NULL DEFAULT NULL,
                used_count INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ";
        $conn->query($sql);

        $seed = "
            INSERT IGNORE INTO shop_vouchers (code, name, voucher_type, amount, min_spend, is_active)
            VALUES
            ('rbj_freeship', 'RBJ Free Shipping', 'free_shipping', 0.00, 0.00, 1),
            ('rbj_discount_100', 'RBJ Discount 100', 'fixed_discount', 100.00, 0.00, 1)
        ";
        $conn->query($seed);
    }
}
rbj_ensure_shop_vouchers_table($conn);
if (!function_exists('rbj_ensure_user_addresses_table')) {
    function rbj_ensure_user_addresses_table(mysqli $conn): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS user_addresses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                label VARCHAR(50) DEFAULT 'Home',
                receiver_name VARCHAR(100) NOT NULL,
                contact_number VARCHAR(20) NOT NULL,
                province VARCHAR(100) NOT NULL,
                city VARCHAR(100) NOT NULL,
                barangay VARCHAR(150) NOT NULL,
                home_address TEXT NOT NULL,
                is_default TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_user_addresses_user (user_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ";
        if ($conn->query($sql) === false) {
            // Fallback without FK for older schemas/engines.
            $fallback = "
                CREATE TABLE IF NOT EXISTS user_addresses (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    label VARCHAR(50) DEFAULT 'Home',
                    receiver_name VARCHAR(100) NOT NULL,
                    contact_number VARCHAR(20) NOT NULL,
                    province VARCHAR(100) NOT NULL,
                    city VARCHAR(100) NOT NULL,
                    barangay VARCHAR(150) NOT NULL,
                    home_address TEXT NOT NULL,
                    is_default TINYINT(1) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_user_addresses_user (user_id)
                )
            ";
            $conn->query($fallback);
        }
    }
}
rbj_ensure_user_addresses_table($conn);
if (!function_exists('rbj_ensure_live_chat_messages_table')) {
    function rbj_ensure_live_chat_messages_table(mysqli $conn): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS live_chat_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                sender_role ENUM('user','admin') NOT NULL,
                message TEXT NOT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                delivered_at TIMESTAMP NULL DEFAULT NULL,
                seen_at TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_live_chat_user_id (user_id),
                INDEX idx_live_chat_user_read (user_id, sender_role, is_read),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ";
        $conn->query($sql);
    }
}
rbj_ensure_live_chat_messages_table($conn);

$user_id = (int)$_SESSION['user_id'];
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$toast_type = '';
$toast_message = '';
$source = strtolower(trim($_REQUEST['source'] ?? 'cart'));
if (!in_array($source, ['cart', 'product'], true)) {
    $source = 'cart';
}

$selected_payment = $_POST['payment_method'] ?? 'cod';
if (!in_array($selected_payment, ['cod', 'cash', 'gcash', 'gotime'], true)) {
    $selected_payment = 'cod';
}
if ($selected_payment === 'cash') {
    // Backward compatibility for previous "cash" option value.
    $selected_payment = 'cod';
}

$gcash_qr_web_path = '';
$gotime_qr_web_path = '';
$qr_dir_fs = __DIR__ . '/../gcash_gotime_qr';
if (is_dir($qr_dir_fs)) {
    $entries = scandir($qr_dir_fs);
    $image_files = [];
    if (is_array($entries)) {
        foreach ($entries as $entry) {
            $entry = (string)$entry;
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full_path = $qr_dir_fs . '/' . $entry;
            if (!is_file($full_path)) {
                continue;
            }
            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
                continue;
            }
            $image_files[] = $entry;
        }
    }

    foreach ($image_files as $image_file) {
        if ($gcash_qr_web_path === '' && stripos($image_file, 'gcash') !== false) {
            $gcash_qr_web_path = '../gcash_gotime_qr/' . rawurlencode($image_file);
        }
        if (
            $gotime_qr_web_path === '' &&
            (stripos($image_file, 'gotime') !== false || stripos($image_file, 'go time') !== false)
        ) {
            $gotime_qr_web_path = '../gcash_gotime_qr/' . rawurlencode($image_file);
        }
    }

    if ($gcash_qr_web_path === '' && !empty($image_files)) {
        $gcash_qr_web_path = '../gcash_gotime_qr/' . rawurlencode($image_files[0]);
    }
    if ($gotime_qr_web_path === '') {
        if (count($image_files) > 1) {
            $gotime_qr_web_path = '../gcash_gotime_qr/' . rawurlencode($image_files[1]);
        } elseif (!empty($image_files)) {
            $gotime_qr_web_path = '../gcash_gotime_qr/' . rawurlencode($image_files[0]);
        }
    }
}
$all_vouchers = [];
$voucher_sql = "
    SELECT id, code, name, voucher_type, amount, min_spend, usage_limit, used_count
    FROM shop_vouchers
    WHERE is_active = 1
      AND (start_at IS NULL OR start_at <= NOW())
      AND (end_at IS NULL OR end_at >= NOW())
    ORDER BY id DESC
";
$voucher_res = $conn->query($voucher_sql);
if ($voucher_res instanceof mysqli_result) {
    while ($v = $voucher_res->fetch_assoc()) {
        $code = trim((string)($v['code'] ?? ''));
        if ($code === '') {
            continue;
        }
        $usage_limit = isset($v['usage_limit']) ? (int)$v['usage_limit'] : 0;
        $used_count = (int)($v['used_count'] ?? 0);
        $usage_left = $usage_limit > 0 ? max(0, $usage_limit - $used_count) : null;
        if ($usage_left === 0) {
            continue;
        }
        $all_vouchers[$code] = [
            'id' => (int)($v['id'] ?? 0),
            'code' => $code,
            'label' => (string)($v['name'] ?? $code),
            'type' => (string)($v['voucher_type'] ?? 'none'),
            'amount' => (float)($v['amount'] ?? 0),
            'min_spend' => (float)($v['min_spend'] ?? 0),
            'usage_left' => $usage_left
        ];
    }
    $voucher_res->free();
}
$shop_voucher_options = [
    'none' => ['id' => 0, 'code' => 'none', 'label' => 'No shop voucher', 'type' => 'none', 'amount' => 0.00, 'min_spend' => 0.00, 'usage_left' => null],
];
$shipping_voucher_options = [
    'none' => ['id' => 0, 'code' => 'none', 'label' => 'No shipping voucher', 'type' => 'none', 'amount' => 0.00, 'min_spend' => 0.00, 'usage_left' => null],
];
foreach ($all_vouchers as $code => $v) {
    if (($v['type'] ?? '') === 'fixed_discount') {
        $shop_voucher_options[$code] = $v;
    } elseif (($v['type'] ?? '') === 'free_shipping') {
        $shipping_voucher_options[$code] = $v;
    }
}
$selected_shop_voucher = trim((string)($_POST['shop_voucher'] ?? 'none'));
if (!array_key_exists($selected_shop_voucher, $shop_voucher_options)) {
    $selected_shop_voucher = 'none';
}
$selected_shipping_voucher = trim((string)($_POST['shipping_voucher'] ?? 'none'));
if (!array_key_exists($selected_shipping_voucher, $shipping_voucher_options)) {
    $selected_shipping_voucher = 'none';
}
$courier_options = [
    'jnt' => ['label' => 'J&T Express', 'fee' => 95.00, 'eta' => '2-4 days'],
    'spx' => ['label' => 'SPX Express', 'fee' => 120.00, 'eta' => '1-3 days'],
];
$selected_courier = strtolower(trim((string)($_POST['shipping_courier'] ?? 'jnt')));
if (!array_key_exists($selected_courier, $courier_options)) {
    $selected_courier = 'jnt';
}
$selected_courier_label = (string)$courier_options[$selected_courier]['label'];
$shipping_subtotal = (float)$courier_options[$selected_courier]['fee'];
$message_for_seller = trim((string)($_REQUEST['message_for_seller'] ?? ''));
$payment_reference = strtoupper(trim((string)($_POST['payment_reference'] ?? '')));
$payment_proof_file = $_FILES['payment_proof'] ?? null;
$has_payment_proof_upload = is_array($payment_proof_file)
    && (int)($payment_proof_file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

// Selected cart items from cart page (only checkout selected items)
$selected_cart_ids = [];
$selected_ids_param = trim((string)($_REQUEST['selected_ids'] ?? ''));
if ($selected_ids_param !== '') {
    $selected_ids_arr = explode(',', $selected_ids_param);
    foreach ($selected_ids_arr as $id) {
        $id = (int)trim($id);
        if ($id > 0) {
            $selected_cart_ids[] = $id;
        }
    }
}
$selected_cart_ids = array_values(array_unique($selected_cart_ids));

$template_id = (int)($_REQUEST['template_id'] ?? 0);
$quantity = max(1, min(99, (int)($_REQUEST['quantity'] ?? 1)));
$requested_customizations = trim((string)($_REQUEST['customizations'] ?? 'Standard package'));
$requested_choice_key = trim((string)($_REQUEST['choice_key'] ?? ''));
if ($requested_customizations === '') {
    $requested_customizations = 'Standard package';
}
$selected_address_id = (int)($_REQUEST['address_id'] ?? 0);

$stmt = $conn->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

$addresses = [];
$stmt = $conn->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $addr_result = $stmt->get_result();
    while ($row = $addr_result->fetch_assoc()) {
        $addresses[] = $row;
    }
    $stmt->close();
}

$selected_address = null;
if (!empty($addresses)) {
    if ($selected_address_id > 0) {
        foreach ($addresses as $addr) {
            if ((int)$addr['id'] === $selected_address_id) {
                $selected_address = $addr;
                break;
            }
        }
    }
    if (!$selected_address) {
        $selected_address = $addresses[0];
        $selected_address_id = (int)($selected_address['id'] ?? 0);
    }
}

$buyer_name_parts = [];
foreach (['first_name', 'middle_name', 'last_name', 'suffix_name'] as $name_key) {
    $value = trim((string)($user[$name_key] ?? ''));
    if ($value !== '') {
        $buyer_name_parts[] = $value;
    }
}
$buyer_name = !empty($buyer_name_parts) ? implode(' ', $buyer_name_parts) : ($_SESSION['username'] ?? 'Buyer');
$contact_number = trim((string)($user['contact_number'] ?? ''));
$first_name = trim((string)($user['first_name'] ?? ''));
$last_name = trim((string)($user['last_name'] ?? ''));
$home_address = trim((string)($user['home_address'] ?? ''));
$barangay = trim((string)($user['barangay'] ?? ''));
$city = trim((string)($user['city'] ?? ''));
$province = trim((string)($user['province'] ?? ''));
$address_label = '';

$address_missing_fields = [];
if ($selected_address) {
    $address_label = trim((string)($selected_address['label'] ?? ''));
    $buyer_name = trim((string)($selected_address['receiver_name'] ?? $buyer_name));
    $contact_number = trim((string)($selected_address['contact_number'] ?? $contact_number));
    $home_address = trim((string)($selected_address['home_address'] ?? $home_address));
    $barangay = trim((string)($selected_address['barangay'] ?? $barangay));
    $city = trim((string)($selected_address['city'] ?? $city));
    $province = trim((string)($selected_address['province'] ?? $province));

    if ($buyer_name === '') {
        $address_missing_fields[] = 'Receiver Name';
    }
    if ($contact_number === '') {
        $address_missing_fields[] = 'Contact Number';
    }
    if ($home_address === '' || $barangay === '' || $city === '' || $province === '') {
        $address_missing_fields[] = 'Full Address (House/Street, Barangay, City, Province)';
    }
}
$address_parts = [];
foreach ([$home_address, $barangay, $city, $province] as $value) {
    $value = trim((string)$value);
    if ($value !== '') {
        $address_parts[] = $value;
    }
}
$buyer_address = !empty($address_parts) ? implode(', ', $address_parts) : 'No complete address found. Please update Account Info first.';
$has_full_name = $first_name !== '' && $last_name !== '';
$has_contact_number = $contact_number !== '';
$has_complete_address = $home_address !== '' && $barangay !== '' && $city !== '' && $province !== '';
$has_checkout_profile = $has_full_name && $has_contact_number && $has_complete_address;
$missing_checkout_fields = [];
if (!$has_full_name) {
    $missing_checkout_fields[] = 'Full Name';
}
if (!$has_contact_number) {
    $missing_checkout_fields[] = 'Contact Number';
}
if (!$has_complete_address) {
    $missing_checkout_fields[] = 'Full Address (House/Street, Barangay, City, Province)';
}
if ($selected_address) {
    $buyer_address = $home_address !== '' || $barangay !== '' || $city !== '' || $province !== ''
        ? implode(', ', array_filter([$home_address, $barangay, $city, $province]))
        : 'No complete address found. Please update Address book first.';
    $has_checkout_profile = empty($address_missing_fields);
    $missing_checkout_fields = $address_missing_fields;
}

$items = [];
if ($source === 'product' && $template_id > 0) {
    $stmt = $conn->prepare('SELECT id, name, image_path, base_price FROM customization_templates WHERE id = ? AND is_active = 1 LIMIT 1');
    $stmt->bind_param('i', $template_id);
    $stmt->execute();
    $template = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($template) {
        $effectiveStock = rbj_resolve_item_stock($conn, (int)$template['id'], $requested_customizations, $requested_choice_key);
        $items[] = [
            'template_id' => (int)$template['id'],
            'name' => $template['name'],
            'image_path' => $template['image_path'],
            'customizations' => $requested_customizations,
            'choice_key' => $requested_choice_key,
            'quantity' => $quantity,
            'price' => (float)$template['base_price'],
            'available_stock' => (int)($effectiveStock['available'] ?? 0)
        ];
    }
} else {
    // Check if specific cart items are selected (already parsed at top)
    if (!empty($selected_cart_ids)) {
        $placeholders = implode(',', array_fill(0, count($selected_cart_ids), '?'));
        $sql = "
            SELECT c.id as cart_id, c.template_id, c.customizations, COALESCE(c.choice_key, '') AS choice_key, c.quantity, c.price, t.name, t.image_path
            FROM cart c
            JOIN customization_templates t ON c.template_id = t.id
            WHERE c.user_id = ? AND c.id IN ($placeholders)
            ORDER BY c.added_at DESC
        ";
        $stmt = $conn->prepare($sql);
        $types = 'i' . str_repeat('i', count($selected_cart_ids));
        $params = array_merge([$user_id], $selected_cart_ids);
        $stmt->bind_param($types, ...$params);
    } else {
        // Load all cart items (default behavior)
        $stmt = $conn->prepare('
            SELECT c.id as cart_id, c.template_id, c.customizations, COALESCE(c.choice_key, \'\') AS choice_key, c.quantity, c.price, t.name, t.image_path
            FROM cart c
            JOIN customization_templates t ON c.template_id = t.id
            WHERE c.user_id = ?
            ORDER BY c.added_at DESC
        ');
        $stmt->bind_param('i', $user_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'cart_id' => (int)$row['cart_id'],
            'template_id' => (int)$row['template_id'],
            'name' => $row['name'],
            'image_path' => $row['image_path'],
            'customizations' => $row['customizations'] ?: 'Standard package',
            'choice_key' => (string)($row['choice_key'] ?? ''),
            'quantity' => (int)$row['quantity'],
            'price' => (float)$row['price'],
            'available_stock' => 0
        ];
    }
    $stmt->close();
}

foreach ($items as $idx => $item) {
    $resolved = rbj_resolve_item_stock(
        $conn,
        (int)$item['template_id'],
        (string)($item['customizations'] ?? 'Standard package'),
        (string)($item['choice_key'] ?? '')
    );
    $items[$idx]['available_stock'] = (int)($resolved['available'] ?? 0);
}

$merchandise_subtotal = 0.0;
$total_qty = 0;
foreach ($items as $item) {
    $merchandise_subtotal += (float)$item['price'] * (int)$item['quantity'];
    $total_qty += (int)$item['quantity'];
}
$use_fallback_sample = $merchandise_subtotal <= 0;
if ($use_fallback_sample) {
    $merchandise_subtotal = 3800.00;
}
$voucher_discount = 0.0;
$shipping_discount = 0.0;
$selected_shop_voucher_info = $shop_voucher_options[$selected_shop_voucher] ?? $shop_voucher_options['none'];
$selected_shipping_voucher_info = $shipping_voucher_options[$selected_shipping_voucher] ?? $shipping_voucher_options['none'];

$shop_voucher_amount = (float)($selected_shop_voucher_info['amount'] ?? 0.0);
$shop_voucher_min_spend = (float)($selected_shop_voucher_info['min_spend'] ?? 0.0);
$shop_voucher_usage_left = $selected_shop_voucher_info['usage_left'] ?? null;
$shop_voucher_is_eligible = $selected_shop_voucher === 'none'
    || ($merchandise_subtotal >= $shop_voucher_min_spend && ($shop_voucher_usage_left === null || (int)$shop_voucher_usage_left > 0));

$shipping_voucher_min_spend = (float)($selected_shipping_voucher_info['min_spend'] ?? 0.0);
$shipping_voucher_usage_left = $selected_shipping_voucher_info['usage_left'] ?? null;
$shipping_voucher_is_eligible = $selected_shipping_voucher === 'none'
    || ($merchandise_subtotal >= $shipping_voucher_min_spend && ($shipping_voucher_usage_left === null || (int)$shipping_voucher_usage_left > 0));

if ($shop_voucher_is_eligible && $selected_shop_voucher !== 'none') {
    $voucher_discount = min($shop_voucher_amount, $merchandise_subtotal);
}
if ($shipping_voucher_is_eligible && $selected_shipping_voucher !== 'none') {
    $shipping_discount = $shipping_subtotal;
}
$total_discount = $voucher_discount + $shipping_discount;
$total_payment = max(0.0, ($merchandise_subtotal + $shipping_subtotal) - $total_discount);

// Handle updated quantities from Buy Now page
if ($source === 'product' && !empty($_POST['item_quantity'])) {
    $new_quantities = $_POST['item_quantity'] ?? [];
    $new_template_ids = $_POST['item_template_id'] ?? [];
    $new_customizations = $_POST['item_customizations'] ?? [];
    $new_choice_keys = $_POST['item_choice_key'] ?? [];
    
    if (!empty($new_quantities) && !empty($items)) {
        $updated_items = [];
        
        foreach ($items as $idx => $item) {
            $new_qty = isset($new_quantities[$idx]) ? max(1, min(99, (int)$new_quantities[$idx])) : (int)$item['quantity'];
            
            // Validate stock with new quantity
            $resolved = rbj_resolve_item_stock(
                $conn,
                (int)$item['template_id'],
                (string)($item['customizations'] ?? 'Standard package'),
                (string)($item['choice_key'] ?? '')
            );
            $available_stock = (int)($resolved['available'] ?? 0);
            
            // Cap quantity at available stock
            if ($available_stock > 0 && $new_qty > $available_stock) {
                $new_qty = $available_stock;
            }
            
            $updated_items[] = [
                'template_id' => (int)$item['template_id'],
                'name' => $item['name'],
                'image_path' => $item['image_path'],
                'customizations' => $item['customizations'],
                'choice_key' => $item['choice_key'],
                'quantity' => $new_qty,
                'price' => (float)$item['price'],
                'available_stock' => $available_stock
            ];
        }
        
        $items = $updated_items;
        
        // Recalculate totals with new quantities
        $merchandise_subtotal = 0.0;
        $total_qty = 0;
        foreach ($items as $item) {
            $merchandise_subtotal += (float)$item['price'] * (int)$item['quantity'];
            $total_qty += (int)$item['quantity'];
        }
        
        $selected_shop_voucher_info = $shop_voucher_options[$selected_shop_voucher] ?? $shop_voucher_options['none'];
        $selected_shipping_voucher_info = $shipping_voucher_options[$selected_shipping_voucher] ?? $shipping_voucher_options['none'];
        
        $shop_voucher_amount = (float)($selected_shop_voucher_info['amount'] ?? 0.0);
        $shop_voucher_min_spend = (float)($selected_shop_voucher_info['min_spend'] ?? 0.0);
        $shop_voucher_usage_left = $selected_shop_voucher_info['usage_left'] ?? null;
        $shop_voucher_is_eligible = $selected_shop_voucher === 'none'
            || ($merchandise_subtotal >= $shop_voucher_min_spend && ($shop_voucher_usage_left === null || (int)$shop_voucher_usage_left > 0));
        
        $shipping_voucher_min_spend = (float)($selected_shipping_voucher_info['min_spend'] ?? 0.0);
        $shipping_voucher_usage_left = $selected_shipping_voucher_info['usage_left'] ?? null;
        $shipping_voucher_is_eligible = $selected_shipping_voucher === 'none'
            || ($merchandise_subtotal >= $shipping_voucher_min_spend && ($shipping_voucher_usage_left === null || (int)$shipping_voucher_usage_left > 0));
        
        $voucher_discount = 0.0;
        $shipping_discount = 0.0;
        
        if ($shop_voucher_is_eligible && $selected_shop_voucher !== 'none') {
            $voucher_discount = min($shop_voucher_amount, $merchandise_subtotal);
        }
        if ($shipping_voucher_is_eligible && $selected_shipping_voucher !== 'none') {
            $shipping_discount = $shipping_subtotal;
        }
        
        $total_discount = $voucher_discount + $shipping_discount;
        $total_payment = max(0.0, ($merchandise_subtotal + $shipping_subtotal) - $total_discount);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf_token, $posted_token)) {
        $toast_type = 'error';
        $toast_message = 'Invalid request token.';
    } elseif (!$has_checkout_profile) {
        $toast_type = 'error';
        $toast_message = 'Please complete your full name, contact number, and full delivery address in Account Info first.';
    } elseif (empty($items)) {
        $toast_type = 'error';
        $toast_message = 'No products found for checkout.';
    } else {
        $stockErrors = [];
        foreach ($items as $item) {
            $availableStock = max(0, (int)($item['available_stock'] ?? 0));
            $requestedQty = max(1, (int)($item['quantity'] ?? 1));
            if ($availableStock <= 0) {
                $stockErrors[] = (string)$item['name'] . ' (' . (string)$item['customizations'] . ') is out of stock.';
            } elseif ($requestedQty > $availableStock) {
                $stockErrors[] = (string)$item['name'] . ' (' . (string)$item['customizations'] . ') only has ' . $availableStock . ' left.';
            }
        }
        if (!empty($stockErrors)) {
            $toast_type = 'error';
            $toast_message = implode(' ', $stockErrors);
        } else {
        $is_digital_payment = in_array($selected_payment, ['gcash', 'gotime'], true);
        $db_payment_method = $is_digital_payment ? 'bank_transfer' : 'cash_on_delivery';
        $transaction_prefix = strtoupper($selected_payment === 'cod' ? 'COD' : $selected_payment);

        if (!$shop_voucher_is_eligible) {
            $toast_type = 'error';
            $toast_message = 'Selected shop voucher is not eligible for this order. Please choose another voucher.';
        } elseif (!$shipping_voucher_is_eligible) {
            $toast_type = 'error';
            $toast_message = 'Selected shipping voucher is not eligible for this order. Please choose another voucher.';
        } elseif ($is_digital_payment && $payment_reference === '' && !$has_payment_proof_upload) {
            $toast_type = 'error';
            $toast_message = 'For GCash/GoTyme payment, please provide a reference number or upload a payment screenshot.';
        } else {
        if ($payment_reference !== '' && !preg_match('/^[A-Z0-9][A-Z0-9\-]{5,39}$/', $payment_reference)) {
            $toast_type = 'error';
            $toast_message = 'Invalid reference number format. Use letters, numbers, and dash only (6-40 chars).';
        }

        if ($toast_type !== 'error' && $payment_reference !== '') {
            $channel = strtoupper($selected_payment);
            $dup_stmt = $conn->prepare('
                SELECT id
                FROM payment_proofs
                WHERE payment_channel = ? AND reference_number = ?
                LIMIT 1
            ');
            if ($dup_stmt) {
                $dup_stmt->bind_param('ss', $channel, $payment_reference);
                $dup_stmt->execute();
                $dup_found = $dup_stmt->get_result()->fetch_assoc();
                $dup_stmt->close();
                if ($dup_found) {
                    $toast_type = 'error';
                    $toast_message = 'Reference number already exists. Please double-check your transaction reference.';
                }
            }
        }

        $proof_tmp_path = '';
        $proof_extension = '';
        if ($has_payment_proof_upload) {
            $upload_error = (int)($payment_proof_file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($upload_error !== UPLOAD_ERR_OK) {
                $toast_type = 'error';
                $toast_message = 'Payment proof upload failed. Please try again.';
            } else {
                $proof_size = (int)($payment_proof_file['size'] ?? 0);
                if ($proof_size <= 0 || $proof_size > (5 * 1024 * 1024)) {
                    $toast_type = 'error';
                    $toast_message = 'Payment proof file must be up to 5MB.';
                } else {
                    $proof_tmp_path = (string)($payment_proof_file['tmp_name'] ?? '');
                    $original_name = (string)($payment_proof_file['name'] ?? '');
                    $proof_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                    if (!in_array($proof_extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                        $toast_type = 'error';
                        $toast_message = 'Allowed proof file types: JPG, PNG, WEBP.';
                    } else {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime = $finfo ? (string)finfo_file($finfo, $proof_tmp_path) : '';
                        if ($finfo) {
                            finfo_close($finfo);
                        }
                        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                            $toast_type = 'error';
                            $toast_message = 'Uploaded payment proof is not a valid image.';
                        }
                    }
                }
            }
        }

        if ($toast_type !== 'error') {

        $address_note = trim(($address_label !== '' ? $address_label . ' - ' : '') . $buyer_name . ' | ' . $contact_number . ' | ' . $buyer_address);

        $order_note = sprintf(
            'Buy Now | Source: %s | Items: %d | Merchandise: PHP %.2f | Shipping: PHP %.2f | Courier: %s | Shop Voucher: %s | Shipping Voucher: %s | Discount: PHP %.2f | Shipping Discount: PHP %.2f | Total: PHP %.2f | Payment: %s | Ship To: %s | Message: %s',
            $source,
            $total_qty,
            $merchandise_subtotal,
            $shipping_subtotal,
            $selected_courier_label,
            $selected_shop_voucher,
            $selected_shipping_voucher,
            $voucher_discount,
            $shipping_discount,
            $total_payment,
            strtoupper($selected_payment),
            $address_note !== '' ? $address_note : 'None',
            $message_for_seller !== '' ? $message_for_seller : 'None'
        );

        try {
            $conn->begin_transaction();

            $stmt = $conn->prepare("INSERT INTO orders (user_id, customization, status) VALUES (?, ?, 'pending')");
            $stmt->bind_param('is', $user_id, $order_note);
            $stmt->execute();
            $order_id = (int)$conn->insert_id;
            $stmt->close();

            $stmt = $conn->prepare('INSERT INTO order_items (order_id, template_id, customizations, quantity, price) VALUES (?, ?, ?, ?, ?)');
            foreach ($items as $item) {
                $item_template_id = (int)$item['template_id'];
                $item_customizations = (string)$item['customizations'];
                $item_qty = (int)$item['quantity'];
                $item_price = (float)$item['price'];
                $stmt->bind_param('iisid', $order_id, $item_template_id, $item_customizations, $item_qty, $item_price);
                $stmt->execute();
            }
            $stmt->close();

            $hasProductImageStock = rbj_db_column_exists($conn, 'product_images', 'stock_quantity');
            $hasChoiceStockTable = rbj_db_table_exists($conn, 'customization_choice_stock');
            $hasTemplateStock = rbj_db_column_exists($conn, 'customization_templates', 'stock_quantity');
            foreach ($items as $item) {
                $itemTemplateId = (int)$item['template_id'];
                $itemQty = max(1, (int)$item['quantity']);
                $itemCustomization = trim((string)$item['customizations']);
                $itemChoiceKey = trim((string)($item['choice_key'] ?? ''));
                $deducted = false;

                if ($hasChoiceStockTable && $itemChoiceKey !== '') {
                    $deductStmt = $conn->prepare("
                        UPDATE customization_choice_stock
                        SET stock_quantity = GREATEST(stock_quantity - ?, 0)
                        WHERE template_id = ? AND choice_key = ?
                    ");
                    if ($deductStmt) {
                        $deductStmt->bind_param('iis', $itemQty, $itemTemplateId, $itemChoiceKey);
                        $deductStmt->execute();
                        $deducted = $deductStmt->affected_rows > 0;
                        $deductStmt->close();
                    }
                }

                if (!$deducted && $hasProductImageStock) {
                    $imageId = 0;
                    $imgStmt = $conn->prepare('SELECT id, image_path, alt_text FROM product_images WHERE template_id = ?');
                    if ($imgStmt) {
                        $imgStmt->bind_param('i', $itemTemplateId);
                        $imgStmt->execute();
                        $imgRes = $imgStmt->get_result();
                        $wantedNorm = rbj_shapi_normalize($itemCustomization);
                        while ($imgRow = $imgRes->fetch_assoc()) {
                            $candidateKey = rbj_choice_key_from_label((string)($imgRow['alt_text'] ?? ''), (string)($imgRow['image_path'] ?? ''));
                            $candidateNorm = rbj_shapi_normalize((string)($imgRow['alt_text'] ?? ''));
                            if (($itemChoiceKey !== '' && $candidateKey === $itemChoiceKey)
                                || ($wantedNorm !== '' && ($candidateNorm === $wantedNorm || strpos($candidateNorm, $wantedNorm) !== false || strpos($wantedNorm, $candidateNorm) !== false))) {
                                $imageId = (int)$imgRow['id'];
                                break;
                            }
                        }
                        $imgStmt->close();
                    }
                    if ($imageId > 0) {
                        $deductImgStmt = $conn->prepare('UPDATE product_images SET stock_quantity = GREATEST(stock_quantity - ?, 0) WHERE id = ?');
                        if ($deductImgStmt) {
                            $deductImgStmt->bind_param('ii', $itemQty, $imageId);
                            $deductImgStmt->execute();
                            $deducted = $deductImgStmt->affected_rows > 0;
                            $deductImgStmt->close();
                        }
                    }
                }

                if (!$deducted && $hasTemplateStock) {
                    $templateStmt = $conn->prepare('UPDATE customization_templates SET stock_quantity = GREATEST(stock_quantity - ?, 0) WHERE id = ?');
                    if ($templateStmt) {
                        $templateStmt->bind_param('ii', $itemQty, $itemTemplateId);
                        $templateStmt->execute();
                        $templateStmt->close();
                    }
                }
            }

            $transaction_id = $transaction_prefix . '-' . date('YmdHis') . '-' . $order_id;
            $payment_status = 'pending';
            $stmt = $conn->prepare('INSERT INTO payments (order_id, user_id, amount, payment_method, transaction_id, status) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('iidsss', $order_id, $user_id, $total_payment, $db_payment_method, $transaction_id, $payment_status);
            $stmt->execute();
            $payment_id = (int)$conn->insert_id;
            $stmt->close();

            if ($is_digital_payment) {
                $proof_web_path = null;
                if ($proof_tmp_path !== '') {
                    $proof_dir = dirname(__DIR__) . '/uploads/payment_proofs';
                    if (!is_dir($proof_dir) && !mkdir($proof_dir, 0775, true) && !is_dir($proof_dir)) {
                        throw new RuntimeException('Unable to create payment proof directory.');
                    }
                    $proof_file_name = 'order_' . $order_id . '_user_' . $user_id . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $proof_extension;
                    $proof_target_path = $proof_dir . '/' . $proof_file_name;
                    if (!move_uploaded_file($proof_tmp_path, $proof_target_path)) {
                        throw new RuntimeException('Unable to save uploaded payment proof.');
                    }
                    $proof_web_path = '../uploads/payment_proofs/' . $proof_file_name;
                }

                $channel = strtoupper($selected_payment);
                $ref_value = $payment_reference !== '' ? $payment_reference : null;
                $stmt = $conn->prepare('
                    INSERT INTO payment_proofs (order_id, payment_id, user_id, payment_channel, reference_number, proof_path, status)
                    VALUES (?, ?, ?, ?, ?, ?, "pending")
                ');
                $stmt->bind_param('iiisss', $order_id, $payment_id, $user_id, $channel, $ref_value, $proof_web_path);
                $stmt->execute();
                $stmt->close();
            }

            $voucher_ids_to_consume = [];
            $selected_shop_voucher_id = (int)($selected_shop_voucher_info['id'] ?? 0);
            if ($selected_shop_voucher !== 'none' && $selected_shop_voucher_id > 0 && $shop_voucher_is_eligible) {
                $voucher_ids_to_consume[] = $selected_shop_voucher_id;
            }
            $selected_shipping_voucher_id = (int)($selected_shipping_voucher_info['id'] ?? 0);
            if ($selected_shipping_voucher !== 'none' && $selected_shipping_voucher_id > 0 && $shipping_voucher_is_eligible) {
                $voucher_ids_to_consume[] = $selected_shipping_voucher_id;
            }
            $voucher_ids_to_consume = array_values(array_unique($voucher_ids_to_consume));
            foreach ($voucher_ids_to_consume as $voucher_id) {
                $stmt = $conn->prepare("
                    UPDATE shop_vouchers
                    SET used_count = used_count + 1
                    WHERE id = ?
                      AND is_active = 1
                      AND (usage_limit IS NULL OR used_count < usage_limit)
                      AND (start_at IS NULL OR start_at <= NOW())
                      AND (end_at IS NULL OR end_at >= NOW())
                ");
                if ($stmt) {
                    $stmt->bind_param('i', $voucher_id);
                    $stmt->execute();
                    if ($stmt->affected_rows <= 0) {
                        $stmt->close();
                        throw new RuntimeException('Voucher is no longer available.');
                    }
                    $stmt->close();
                }
            }

            $notification_msg = 'Thank you for your order! Order #' . $order_id . ' was received and is now pending confirmation.';
            $stmt = $conn->prepare('INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, 0)');
            $stmt->bind_param('is', $user_id, $notification_msg);
            $stmt->execute();
            $stmt->close();

            $auto_reply_msg = "Thank you for ordering with RBJ Accessories! Your order #{$order_id} is now in our queue. If you have clarifications or special requests, reply here and we will assist you.";
            $stmt = $conn->prepare("INSERT INTO live_chat_messages (user_id, sender_role, message, is_read, delivered_at, seen_at) VALUES (?, 'admin', ?, 0, NULL, NULL)");
            if ($stmt) {
                $stmt->bind_param('is', $user_id, $auto_reply_msg);
                $stmt->execute();
                $stmt->close();
            }

            if ($source === 'cart') {
                // Remove only selected cart items when provided
                if (!empty($selected_cart_ids)) {
                    $placeholders = implode(',', array_fill(0, count($selected_cart_ids), '?'));
                    $sql = "DELETE FROM cart WHERE user_id = ? AND id IN ($placeholders)";
                    $stmt = $conn->prepare($sql);
                    $types = 'i' . str_repeat('i', count($selected_cart_ids));
                    $params = array_merge([$user_id], $selected_cart_ids);
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $stmt = $conn->prepare('DELETE FROM cart WHERE user_id = ?');
                    $stmt->bind_param('i', $user_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            $conn->commit();
            header('Location: orders.php?placed=1&order_id=' . $order_id);
            exit();
        } catch (Throwable $e) {
            $conn->rollback();
            $toast_type = 'error';
            $toast_message = 'Unable to place order right now. Please try again.';
        }
        }
        }
    }
}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Buy Now - MotoFit</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<style>
body { min-height:100vh; background: linear-gradient(135deg,#1b1b1b,#111); color:white; padding-top:100px; font-family:"Montserrat",sans-serif; }
.navbar { position:fixed; top:0; left:0; right:0; display:flex; justify-content:space-between; align-items:center; padding:10px 50px; background: rgba(0,0,0,0.8); z-index:999; }
.navbar .logo { display:flex; align-items:center; gap:10px; color:white; text-decoration:none; font-size:22px; font-weight:700; }
.navbar .logo img { height: 60px; width:auto; }
.navbar .nav-links { display:flex; align-items:center; gap:15px; }
.navbar .nav-links a { color:white; text-decoration:none; font-weight:500; margin-left:15px; }
.navbar .nav-links a:hover { text-decoration: underline; }
.navbar, .navbar .nav-links { overflow: visible; }

.account-dropdown { position: relative; display: flex; align-items: center; margin-left: 15px; }
.account-trigger {
  background: none;
  border: none;
  color: white;
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}
.account-icon {
  width: 40px;
  height: 40px;
  min-width: 40px;
  min-height: 40px;
  border-radius: 50%;
  background: #27ae60;
  color: #fff;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}
.account-username { color: #fff; font-weight: 600; }
.account-menu {
  position: absolute;
  top: 110%;
  right: 0;
  background: #1e1e1e;
  border-radius: 10px;
  min-width: 220px;
  padding: 8px 0;
  display: none;
  box-shadow: 0 10px 30px rgba(0,0,0,0.4);
  z-index: 2200;
}
.account-dropdown.active .account-menu { display: block; }
.account-menu .logout-link {
  color: #ffb3ab !important;
  border-top: 1px solid rgba(231,76,60,0.28);
  margin-top: 4px;
}
.account-menu .logout-link:hover {
  background: rgba(231,76,60,0.18) !important;
}

.wrapper { max-width:1250px; margin:auto; padding:20px; }
.page-title { text-align:center; margin-bottom:18px; font-size:34px; }
.layout { display:grid; grid-template-columns: 1.7fr 1fr; gap:20px; }
.panel { background: rgba(0,0,0,0.6); border: 1px solid rgba(255,255,255,0.10); border-radius: 14px; padding: 18px; margin-bottom: 14px; }
.panel h2 { margin: 0 0 12px 0; font-size: 18px; color: #2ecc71; }
.address-line { color: rgba(255,255,255,0.9); line-height: 1.5; }
.address-warning { margin-top: 10px; font-size: 14px; color: #ffb3b3; }
.inline-link { color: #7ee2a8; text-decoration: underline; }
.address-picker { margin-top: 12px; display: flex; flex-direction: column; gap: 10px; }
.address-option {
  display: flex;
  gap: 12px;
  align-items: flex-start;
  padding: 12px;
  border: 1px solid rgba(255,255,255,0.18);
  border-radius: 12px;
  background: rgba(255,255,255,0.04);
  cursor: pointer;
}
.address-option input { margin-top: 4px; }
.address-option.selected { border-color: rgba(39,174,96,0.7); box-shadow: 0 0 0 1px rgba(39,174,96,0.4) inset; }
.address-option-title { display: flex; align-items: center; gap: 8px; font-weight: 700; }
.address-badge {
  font-size: 11px;
  padding: 4px 8px;
  border-radius: 999px;
  background: rgba(39,174,96,0.2);
  color: #8be6b6;
  border: 1px solid rgba(39,174,96,0.35);
}
.address-option-meta { font-size: 13px; color: rgba(255,255,255,0.78); margin-top: 4px; }
.address-option-text { font-size: 13px; color: rgba(255,255,255,0.7); margin-top: 4px; }
.address-actions-row { margin-top: 10px; display: flex; gap: 10px; }

.items-table { width:100%; border-collapse: collapse; }
.items-table th, .items-table td { padding: 10px 8px; border-bottom: 1px solid rgba(255,255,255,0.1); vertical-align: middle; text-align: left; }
.items-table th { color: rgba(255,255,255,0.8); font-size: 13px; }
.product-cell { display:flex; align-items:center; gap:10px; min-width: 220px; }
.product-cell img { width:58px; height:58px; border-radius:8px; object-fit:cover; background: rgba(255,255,255,0.05); }
.product-meta { font-size: 13px; color: rgba(255,255,255,0.75); }

.field { margin-bottom: 10px; }
.field label { display:block; font-size: 13px; color: rgba(255,255,255,0.75); margin-bottom: 6px; }
.field select, .field textarea {
  width:100%;
  border:1px solid rgba(255,255,255,0.25);
  border-radius:10px;
  background: rgba(255,255,255,0.06);
  color:white;
  padding: 10px;
}
.field select option { color: #111; }
.field textarea { min-height: 80px; resize: vertical; }
.field input[type="text"],
.field input[type="file"] {
  width:100%;
  border:1px solid rgba(255,255,255,0.25);
  border-radius:10px;
  background: rgba(255,255,255,0.06);
  color:white;
  padding: 10px;
}

.pay-row { display:flex; gap:16px; flex-wrap: wrap; }
.pay-opt { display:flex; align-items:center; gap:8px; border:1px solid rgba(255,255,255,0.25); border-radius:10px; padding:8px 10px; }
.ship-row { display:flex; flex-direction:column; gap:10px; }
.ship-opt {
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:10px;
  border:1px solid rgba(255,255,255,0.25);
  border-radius:10px;
  padding:10px 12px;
}
.ship-opt-main { display:flex; align-items:center; gap:8px; }
.ship-meta { font-size:12px; color: rgba(255,255,255,0.72); }
.ship-meta-value { white-space: nowrap; }
.qr-panel {
  margin-top: 12px;
  border: 1px dashed rgba(255,255,255,0.25);
  border-radius: 12px;
  padding: 12px;
  display: none;
}
.qr-title { font-size: 13px; color: rgba(255,255,255,0.8); margin-bottom: 8px; }
.qr-image-wrap { display: flex; justify-content: center; }
.qr-image {
  width: 220px;
  max-width: 100%;
  height: auto;
  border-radius: 10px;
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.2);
}
.qr-empty { font-size: 13px; color: #ffb3b3; }
.payment-proof-fields { margin-top: 12px; display: none; }
.field-help { font-size: 12px; color: rgba(255,255,255,0.7); margin-top: 5px; }

.invoice-row { display:flex; justify-content:space-between; align-items:center; gap: 10px; flex-wrap: wrap; }
.request-btn { border:none; border-radius:8px; background: rgba(39,174,96,0.2); color:#82e9b0; padding:9px 12px; font-weight:600; cursor:pointer; }

.summary-row { display:flex; justify-content:space-between; margin-bottom:10px; color: rgba(255,255,255,0.9); }
.summary-row.total { border-top:1px solid rgba(255,255,255,0.2); padding-top:12px; margin-top:12px; font-size: 18px; font-weight: 700; color: #fff; }
.place-btn {
  width:100%;
  border:none;
  border-radius:12px;
  padding: 13px;
  font-weight:700;
  background: linear-gradient(45deg,#27ae60,#2ecc71);
  color: #fff;
  cursor:pointer;
  margin-top: 14px;
}

.empty-state { text-align:center; color: rgba(255,255,255,0.8); padding: 20px 0; }

.qty-control { display: inline-flex; align-items: center; border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; overflow: hidden; }
.qty-control .qty-btn { width: 28px; height: 28px; border: none; background: rgba(255,255,255,0.1); color: #fff; cursor: pointer; font-size: 16px; display: flex; align-items: center; justify-content: center; }
.qty-control .qty-btn:hover { background: rgba(255,255,255,0.2); }
.qty-control .qty-input { width: 45px; height: 28px; border: none; border-left: 1px solid rgba(255,255,255,0.2); border-right: 1px solid rgba(255,255,255,0.2); outline: none; text-align: center; background: rgba(0,0,0,0.3); color: #fff; font-size: 14px; }
.qty-control .qty-input::-webkit-inner-spin-button, .qty-control .qty-input::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
.qty-control .qty-input[type=number] { -moz-appearance: textfield; }
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

html[data-theme="light"] body {
  background: linear-gradient(135deg, #fff5f2, #ffe9e2);
  color: #5a241f;
}
html[data-theme="light"] .navbar {
  background: rgba(255, 243, 238, 0.95) !important;
  border-bottom: 1px solid rgba(217,4,41, 0.22);
}
html[data-theme="light"] .navbar .logo,
html[data-theme="light"] .navbar .nav-links a,
html[data-theme="light"] .account-trigger,
html[data-theme="light"] .account-username {
  color: #7a211b;
}
html[data-theme="light"] .panel {
  background: rgba(255,255,255,0.88);
  border-color: rgba(217,4,41, 0.2);
  color: #6a211a;
}
html[data-theme="light"] .panel h2 { color: #9f2f26; }
html[data-theme="light"] .address-line,
html[data-theme="light"] .summary-row { color: #6a211a; }
html[data-theme="light"] .items-table th,
html[data-theme="light"] .items-table td {
  border-bottom-color: rgba(217,4,41, 0.18);
  color: #6a211a;
}
html[data-theme="light"] .product-meta { color: #8a443d; }
html[data-theme="light"] .field label { color: #8f4a43; }
html[data-theme="light"] .field select,
html[data-theme="light"] .field textarea {
  background: #fff;
  color: #5b241f;
  border-color: rgba(217,4,41, 0.28);
}
html[data-theme="light"] .field input[type="text"],
html[data-theme="light"] .field input[type="file"] {
  background: #fff;
  color: #5b241f;
  border-color: rgba(217,4,41, 0.28);
}
html[data-theme="light"] .pay-opt {
  border-color: rgba(217,4,41, 0.28);
  color: #6a211a;
  background: #fff;
}
html[data-theme="light"] .ship-opt {
  border-color: rgba(217,4,41, 0.28);
  color: #6a211a;
  background: #fff;
}
html[data-theme="light"] .ship-meta { color: #8f4a43; }
html[data-theme="light"] .qr-panel {
  border-color: rgba(217,4,41, 0.28);
  background: #fff;
}
html[data-theme="light"] .qr-title { color: #8f4a43; }
html[data-theme="light"] .qr-image { border-color: rgba(217,4,41, 0.24); }
html[data-theme="light"] .qr-empty { color: #a9443b; }
html[data-theme="light"] .field-help { color: #8f4a43; }
html[data-theme="light"] .request-btn {
  background: rgba(159, 47, 38, 0.12);
  color: #9f2f26;
}
html[data-theme="light"] .summary-row.total {
  border-top-color: rgba(217,4,41, 0.24);
  color: #5a1f19;
}
html[data-theme="light"] .inline-link { color: #9f2f26; }
html[data-theme="light"] .address-option { background: #fff; border-color: rgba(217,4,41,0.22); }
html[data-theme="light"] .address-option.selected { border-color: rgba(217,4,41,0.6); box-shadow: 0 0 0 1px rgba(217,4,41,0.2) inset; }
html[data-theme="light"] .address-badge { background: rgba(217,4,41,0.12); color: #9f2f26; border-color: rgba(217,4,41,0.24); }
html[data-theme="light"] .account-menu {
  background: #fff8f5;
  border: 1px solid rgba(217,4,41, 0.2);
}
html[data-theme="light"] .account-menu-summary .name,
html[data-theme="light"] .account-menu a,
html[data-theme="light"] .account-menu-summary .email {
  color: #6a211a;
}
html[data-theme="light"] .account-menu .menu-divider {
  background: rgba(217,4,41, 0.18);
}
@media(max-width:950px){
  .layout { grid-template-columns: 1fr; }
  .navbar{padding:10px 20px;}
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
    <a href="cart.php"><i class='bx bx-cart'></i></a>
    <?php include __DIR__ . '/partials/account_menu.php'; ?>
  </div>
</nav>

<div class="wrapper">
  <h1 class="page-title">Buy Now</h1>

  <form method="POST" action="buy_now.php" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
    <input type="hidden" name="source" value="<?php echo htmlspecialchars($source); ?>">
    <?php if ($source === 'cart' && !empty($selected_cart_ids)): ?>
      <input type="hidden" name="selected_ids" value="<?php echo htmlspecialchars(implode(',', $selected_cart_ids)); ?>">
    <?php endif; ?>
    <?php if ($source === 'product'): ?>
      <input type="hidden" name="template_id" value="<?php echo (int)$template_id; ?>">
      <input type="hidden" name="quantity" value="<?php echo (int)$quantity; ?>">
      <input type="hidden" name="choice_key" value="<?php echo htmlspecialchars($requested_choice_key); ?>">
    <?php endif; ?>

    <div class="layout">
      <div>
        <section class="panel">
          <h2>Delivery Address</h2>
          <div class="address-line" id="buyerNameLine">
            <strong id="buyerNameText"><?php echo htmlspecialchars($buyer_name); ?></strong>
            <span class="address-badge" id="buyerLabelBadge" <?php echo $address_label !== '' ? '' : 'style="display:none;"'; ?>>
              <?php echo htmlspecialchars($address_label); ?>
            </span>
          </div>
          <div class="address-line" id="buyerContactLine"><?php echo $contact_number !== '' ? htmlspecialchars($contact_number) : 'No contact number'; ?></div>
          <div class="address-line" id="buyerAddressLine"><?php echo htmlspecialchars($buyer_address); ?></div>
          <?php if (!$has_checkout_profile): ?>
            <div class="address-warning">
              Complete required details in <a class="inline-link" href="<?php echo !empty($addresses) ? 'manage_addresses.php' : 'account_info.php'; ?>"><?php echo !empty($addresses) ? 'Address Book' : 'Account Info'; ?></a> before placing an order:
              <?php echo htmlspecialchars(implode(', ', $missing_checkout_fields)); ?>.
            </div>
          <?php endif; ?>
          <?php if (!empty($addresses)): ?>
            <div class="address-picker" id="addressPicker">
              <?php foreach ($addresses as $addr): ?>
                <?php
                  $addr_id = (int)($addr['id'] ?? 0);
                  $addr_label = trim((string)($addr['label'] ?? ''));
                  $addr_name = trim((string)($addr['receiver_name'] ?? ''));
                  $addr_contact = trim((string)($addr['contact_number'] ?? ''));
                  $addr_full = implode(', ', array_filter([
                      (string)($addr['home_address'] ?? ''),
                      (string)($addr['barangay'] ?? ''),
                      (string)($addr['city'] ?? ''),
                      (string)($addr['province'] ?? '')
                  ]));
                  $is_selected = $addr_id > 0 && $addr_id === (int)$selected_address_id;
                ?>
                <label class="address-option <?php echo $is_selected ? 'selected' : ''; ?>"
                       data-name="<?php echo htmlspecialchars($addr_name); ?>"
                       data-contact="<?php echo htmlspecialchars($addr_contact); ?>"
                       data-address="<?php echo htmlspecialchars($addr_full); ?>"
                       data-label="<?php echo htmlspecialchars($addr_label); ?>">
                  <input type="radio" name="address_id" value="<?php echo $addr_id; ?>" <?php echo $is_selected ? 'checked' : ''; ?>>
                  <div>
                    <div class="address-option-title">
                      <span><?php echo htmlspecialchars($addr_label !== '' ? $addr_label : 'Address'); ?></span>
                      <?php if (!empty($addr['is_default'])): ?>
                        <span class="address-badge">Default</span>
                      <?php endif; ?>
                    </div>
                    <div class="address-option-meta"><?php echo htmlspecialchars($addr_name); ?> • <?php echo htmlspecialchars($addr_contact); ?></div>
                    <div class="address-option-text"><?php echo htmlspecialchars($addr_full); ?></div>
                  </div>
                </label>
              <?php endforeach; ?>
            </div>
            <div class="address-actions-row">
              <a class="inline-link" href="manage_addresses.php">Manage Addresses</a>
            </div>
          <?php else: ?>
            <div class="address-actions-row">
              <a class="inline-link" href="manage_addresses.php">Add Address</a>
            </div>
          <?php endif; ?>
        </section>

        <section class="panel">
          <h2>Products Ordered</h2>
          <?php if (empty($items)): ?>
            <div class="empty-state">No product selected. Go back to <a class="inline-link" href="catalog.php">Shop Catalog</a>.</div>
          <?php else: ?>
            <table class="items-table">
              <thead>
                <tr>
                  <th>Product</th>
                  <th>Unit Price</th>
                  <th>Quantity</th>
                  <th>Item Subtotal</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $idx => $item): ?>
                  <?php 
                    $item_subtotal = (float)$item['price'] * (int)$item['quantity'];
                    $available_stock = max(0, (int)($item['available_stock'] ?? 0));
                    $max_qty = $available_stock > 0 ? min(99, $available_stock) : 99;
                  ?>
                  <tr class="order-item" data-item-index="<?php echo (int)$idx; ?>" data-price="<?php echo htmlspecialchars((string)$item['price']); ?>" data-available-stock="<?php echo (int)$available_stock; ?>">
                    <td>
                      <div class="product-cell">
                        <?php $item_image_url = rbj_template_image_url($item['image_path'] ?? ''); ?>
                        <?php if ($item_image_url !== ''): ?>
                          <img src="<?php echo htmlspecialchars($item_image_url); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                        <?php endif; ?>
                        <div>
                          <div><?php echo htmlspecialchars($item['name']); ?></div>
                          <div class="product-meta"><?php echo htmlspecialchars($item['customizations']); ?></div>
                          <?php if ($available_stock > 0 && $available_stock < 10): ?>
                            <div class="product-meta" style="color: #ffb3b3;">Only <?php echo (int)$available_stock; ?> left in stock</div>
                          <?php elseif ($available_stock <= 0): ?>
                            <div class="product-meta" style="color: #ff6b6b;">Out of stock</div>
                          <?php endif; ?>
                        </div>
                      </div>
                    </td>
                    <td class="item-price">&#8369;<?php echo number_format((float)$item['price'], 2); ?></td>
                    <td>
                      <?php if ($source === 'product'): ?>
                        <div class="qty-control">
                          <button type="button" class="qty-btn" onclick="updateItemQuantity(<?php echo (int)$idx; ?>, -1)">-</button>
                          <input type="number" class="qty-input" name="item_quantity[]" value="<?php echo (int)$item['quantity']; ?>" min="1" max="<?php echo (int)$max_qty; ?>" data-original-qty="<?php echo (int)$item['quantity']; ?>" onchange="validateItemQuantity(this, <?php echo (int)$idx; ?>)">
                          <button type="button" class="qty-btn" onclick="updateItemQuantity(<?php echo (int)$idx; ?>, 1)">+</button>
                        </div>
                        <input type="hidden" name="item_template_id[]" value="<?php echo (int)$item['template_id']; ?>">
                        <input type="hidden" name="item_customizations[]" value="<?php echo htmlspecialchars($item['customizations']); ?>">
                        <input type="hidden" name="item_choice_key[]" value="<?php echo htmlspecialchars($item['choice_key'] ?? ''); ?>">
                      <?php else: ?>
                        <?php echo (int)$item['quantity']; ?>
                      <?php endif; ?>
                    </td>
                    <td class="item-subtotal">&#8369;<?php echo number_format($item_subtotal, 2); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </section>

        <section class="panel">
          <h2>E-Invoice</h2>
          <div class="invoice-row">
            <div>Get the true receipt of your order electronically.</div>
            <button type="button" class="request-btn" id="requestInvoiceBtn">Request Now</button>
          </div>
        </section>

        <section class="panel">
          <h2>Shipping Option</h2>
          <div class="ship-row">
            <?php foreach ($courier_options as $courier_key => $courier): ?>
              <label class="ship-opt">
                <span class="ship-opt-main">
                  <input type="radio" name="shipping_courier" value="<?php echo htmlspecialchars($courier_key); ?>" <?php echo $selected_courier === $courier_key ? 'checked' : ''; ?>>
                  <span><?php echo htmlspecialchars((string)$courier['label']); ?></span>
                </span>
                <span class="ship-meta ship-meta-value" data-fee="<?php echo htmlspecialchars((string)$courier['fee']); ?>" data-label="<?php echo htmlspecialchars((string)$courier['label']); ?>">&#8369;<?php echo number_format((float)$courier['fee'], 0); ?> | ETA <?php echo htmlspecialchars((string)$courier['eta']); ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="panel">
          <h2>Vouchers</h2>
          <div class="field">
            <label for="shop_voucher">Shop Voucher (Item Discount)</label>
            <select id="shop_voucher" name="shop_voucher">
              <?php foreach ($shop_voucher_options as $voucher_key => $voucher): ?>
                <option
                  value="<?php echo htmlspecialchars($voucher_key); ?>"
                  data-type="<?php echo htmlspecialchars((string)$voucher['type']); ?>"
                  data-amount="<?php echo htmlspecialchars((string)$voucher['amount']); ?>"
                  data-min-spend="<?php echo htmlspecialchars((string)($voucher['min_spend'] ?? 0)); ?>"
                  <?php echo $selected_shop_voucher === $voucher_key ? 'selected' : ''; ?>
                >
                  <?php echo htmlspecialchars((string)$voucher['label']); ?><?php echo isset($voucher['usage_left']) && $voucher['usage_left'] !== null ? ' (left: ' . (int)$voucher['usage_left'] . ')' : ''; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="shipping_voucher">Shipping Voucher</label>
            <select id="shipping_voucher" name="shipping_voucher">
              <?php foreach ($shipping_voucher_options as $voucher_key => $voucher): ?>
                <option
                  value="<?php echo htmlspecialchars($voucher_key); ?>"
                  data-type="<?php echo htmlspecialchars((string)$voucher['type']); ?>"
                  data-amount="<?php echo htmlspecialchars((string)$voucher['amount']); ?>"
                  data-min-spend="<?php echo htmlspecialchars((string)($voucher['min_spend'] ?? 0)); ?>"
                  <?php echo $selected_shipping_voucher === $voucher_key ? 'selected' : ''; ?>
                >
                  <?php echo htmlspecialchars((string)$voucher['label']); ?><?php echo isset($voucher['usage_left']) && $voucher['usage_left'] !== null ? ' (left: ' . (int)$voucher['usage_left'] . ')' : ''; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </section>

        <section class="panel">
          <h2>Message for Seller</h2>
          <div class="field">
            <label for="message_for_seller">Optional note about your order</label>
            <textarea id="message_for_seller" name="message_for_seller" placeholder="Enter your message for seller..."><?php echo htmlspecialchars($message_for_seller); ?></textarea>
          </div>
        </section>
      </div>

      <div>
        <section class="panel">
          <h2>Payment Method</h2>
          <div class="pay-row">
            <label class="pay-opt"><input type="radio" name="payment_method" value="cod" <?php echo $selected_payment === 'cod' ? 'checked' : ''; ?>> Cash on Delivery (COD)</label>
            <label class="pay-opt"><input type="radio" name="payment_method" value="gcash" <?php echo $selected_payment === 'gcash' ? 'checked' : ''; ?>> GCash</label>
            <label class="pay-opt"><input type="radio" name="payment_method" value="gotime" <?php echo $selected_payment === 'gotime' ? 'checked' : ''; ?>> GoTyme</label>
          </div>
          <div class="qr-panel" id="qrPaymentPanel">
            <div class="qr-title" id="qrTitle">Scan QR to pay</div>
            <div class="qr-image-wrap">
              <img
                id="qrImage"
                class="qr-image"
                src=""
                alt="Payment QR"
                data-gcash-src="<?php echo htmlspecialchars($gcash_qr_web_path); ?>"
                data-gotime-src="<?php echo htmlspecialchars($gotime_qr_web_path); ?>"
              >
            </div>
            <div class="qr-empty" id="qrEmptyMsg" style="display:none;">QR code image not found in <code>gcash_gotime_qr</code> folder.</div>
          </div>
          <div class="payment-proof-fields" id="digitalPaymentFields">
            <div class="field">
              <label for="payment_reference">Reference Number (optional if screenshot uploaded)</label>
              <input type="text" id="payment_reference" name="payment_reference" value="<?php echo htmlspecialchars($payment_reference); ?>" placeholder="Enter transaction/reference number">
            </div>
            <div class="field">
              <label for="payment_proof">Upload Payment Screenshot (optional if reference provided)</label>
              <input type="file" id="payment_proof" name="payment_proof" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
              <div class="field-help">Provide reference number or screenshot. Max file size: 5MB. Note: admin verification requires a valid reference number.</div>
            </div>
          </div>
        </section>

        <section class="panel">
          <h2>Payment Summary</h2>
          <div class="summary-row">
            <span>Merchandise Subtotal</span>
            <span id="merchandiseSubtotal">&#8369;<?php echo number_format($merchandise_subtotal, 0); ?></span>
          </div>
          <div class="summary-row">
            <span id="shippingLabel">Shipping Subtotal (<?php echo htmlspecialchars($selected_courier_label); ?>)</span>
            <span id="shippingAmount" data-shipping="<?php echo htmlspecialchars((string)$shipping_subtotal); ?>">&#8369;<?php echo number_format($shipping_subtotal, 0); ?></span>
          </div>
          <div class="summary-row" id="voucherDiscountRow" <?php echo $voucher_discount > 0 ? '' : 'style="display:none;"'; ?>>
            <span>Voucher Discount</span>
            <span id="voucherDiscountAmount" data-voucher-discount="<?php echo htmlspecialchars((string)$voucher_discount); ?>">-&#8369;<?php echo number_format($voucher_discount, 0); ?></span>
          </div>
          <div class="summary-row" id="shippingDiscountRow" <?php echo $shipping_discount > 0 ? '' : 'style="display:none;"'; ?>>
            <span>Shipping Discount</span>
            <span id="shippingDiscountAmount" data-shipping-discount="<?php echo htmlspecialchars((string)$shipping_discount); ?>">-&#8369;<?php echo number_format($shipping_discount, 0); ?></span>
          </div>
          <div class="summary-row total">
            <span>Total Payment:</span>
            <span id="totalAmount" data-merchandise="<?php echo htmlspecialchars((string)$merchandise_subtotal); ?>">&#8369;<?php echo number_format($total_payment, 0); ?></span>
          </div>

          <button type="submit" name="place_order" class="place-btn" <?php echo (empty($items) || !$has_checkout_profile) ? 'disabled' : ''; ?>>Place Order</button>
        </section>
      </div>
    </div>
  </form>
</div>

<script>
const toastMessage = <?php echo json_encode($toast_message); ?>;
const toastType = <?php echo json_encode($toast_type); ?>;
if (toastMessage) {
  const toast = document.createElement('div');
  toast.className = 'toast ' + (toastType === 'error' ? 'error' : 'success');
  toast.textContent = toastMessage;
  document.body.appendChild(toast);
  requestAnimationFrame(() => toast.classList.add('show'));
  setTimeout(() => {
    toast.classList.remove('show');
    setTimeout(() => toast.remove(), 260);
  }, 2600);
}

// Quantity management for Buy Now
function updateItemQuantity(index, change) {
  const itemRow = document.querySelector(`.order-item[data-item-index="${index}"]`);
  if (!itemRow) return;
  
  const qtyInput = itemRow.querySelector('.qty-input');
  const availableStock = parseInt(itemRow.dataset.availableStock) || 99;
  const currentQty = parseInt(qtyInput.value) || 1;
  const maxQty = Math.min(99, availableStock);
  
  let newQty = currentQty + change;
  if (newQty < 1) newQty = 1;
  if (newQty > maxQty) newQty = maxQty;
  
  qtyInput.value = newQty;
  updateItemSubtotal(index);
}

function validateItemQuantity(input, index) {
  const itemRow = document.querySelector(`.order-item[data-item-index="${index}"]`);
  if (!itemRow) return;
  
  const availableStock = parseInt(itemRow.dataset.availableStock) || 99;
  const maxQty = Math.min(99, availableStock);
  let qty = parseInt(input.value) || 1;
  
  if (qty < 1) qty = 1;
  if (qty > maxQty) qty = maxQty;
  
  input.value = qty;
  updateItemSubtotal(index);
}

function updateItemSubtotal(index) {
  const itemRow = document.querySelector(`.order-item[data-item-index="${index}"]`);
  if (!itemRow) return;
  
  const price = parseFloat(itemRow.dataset.price) || 0;
  const qtyInput = itemRow.querySelector('.qty-input');
  const qty = parseInt(qtyInput.value) || 1;
  const subtotalCell = itemRow.querySelector('.item-subtotal');
  
  if (subtotalCell) {
    subtotalCell.innerHTML = '&#8369;' + (price * qty).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  }
  
  updateOrderSummary();
}

function updateOrderSummary() {
  const itemRows = document.querySelectorAll('.order-item');
  let merchandiseSubtotal = 0;
  
  itemRows.forEach((row, index) => {
    const price = parseFloat(row.dataset.price) || 0;
    const qtyInput = row.querySelector('.qty-input');
    const qty = parseInt(qtyInput?.value) || 1;
    merchandiseSubtotal += price * qty;
  });
  
  // Update merchandise subtotal display
  const merchSubtotalEl = document.getElementById('merchandiseSubtotal');
  if (merchSubtotalEl) {
    merchSubtotalEl.innerHTML = '&#8369;' + Math.round(merchandiseSubtotal).toLocaleString('en-PH');
  }
  
  // Update total amount (recalculate with shipping and discounts)
  recalculateTotal(merchandiseSubtotal);
}

function recalculateTotal(merchandiseSubtotal) {
  const shippingAmountEl = document.getElementById('shippingAmount');
  const totalAmountEl = document.getElementById('totalAmount');
  const shopVoucherSelect = document.getElementById('shop_voucher');
  const shippingVoucherSelect = document.getElementById('shipping_voucher');
  
  if (!shippingAmountEl || !totalAmountEl) return;
  
  const shippingFee = parseFloat(shippingAmountEl.dataset.shipping) || 0;
  
  let voucherDiscount = 0;
  let shippingDiscount = 0;
  
  if (shopVoucherSelect) {
    const selectedOpt = shopVoucherSelect.options[shopVoucherSelect.selectedIndex];
    if (selectedOpt && selectedOpt.dataset.type === 'fixed_discount') {
      const minSpend = parseFloat(selectedOpt.dataset.minSpend) || 0;
      if (merchandiseSubtotal >= minSpend) {
        voucherDiscount = parseFloat(selectedOpt.dataset.amount) || 0;
        voucherDiscount = Math.min(voucherDiscount, merchandiseSubtotal);
      }
    }
  }
  
  if (shippingVoucherSelect) {
    const selectedOpt = shippingVoucherSelect.options[shippingVoucherSelect.selectedIndex];
    if (selectedOpt && selectedOpt.dataset.type === 'free_shipping') {
      const minSpend = parseFloat(selectedOpt.dataset.minSpend) || 0;
      if (merchandiseSubtotal >= minSpend) {
        shippingDiscount = shippingFee;
      }
    }
  }
  
  const totalDiscount = voucherDiscount + shippingDiscount;
  const totalPayment = Math.max(0, (merchandiseSubtotal + shippingFee) - totalDiscount);
  
  totalAmountEl.textContent = '&#8369;' + Math.round(totalPayment).toLocaleString('en-PH');
  totalAmountEl.dataset.merchandise = merchandiseSubtotal;
  
  // Update discount displays
  const voucherDiscountRow = document.getElementById('voucherDiscountRow');
  const voucherDiscountAmount = document.getElementById('voucherDiscountAmount');
  if (voucherDiscountRow && voucherDiscountAmount) {
    if (voucherDiscount > 0) {
      voucherDiscountRow.style.display = '';
      voucherDiscountAmount.textContent = '-&#8369;' + Math.round(voucherDiscount).toLocaleString('en-PH');
    } else {
      voucherDiscountRow.style.display = 'none';
    }
  }
  
  const shippingDiscountRow = document.getElementById('shippingDiscountRow');
  const shippingDiscountAmount = document.getElementById('shippingDiscountAmount');
  if (shippingDiscountRow && shippingDiscountAmount) {
    if (shippingDiscount > 0) {
      shippingDiscountRow.style.display = '';
      shippingDiscountAmount.textContent = '-&#8369;' + Math.round(shippingDiscount).toLocaleString('en-PH');
    } else {
      shippingDiscountRow.style.display = 'none';
    }
  }
}

const requestInvoiceBtn = document.getElementById('requestInvoiceBtn');
if (requestInvoiceBtn) {
  requestInvoiceBtn.addEventListener('click', function () {
    const toast = document.createElement('div');
    toast.className = 'toast success';
    toast.textContent = 'E-Invoice request submitted. We will attach it to your order records.';
    document.body.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('show'));
    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 260);
    }, 2300);
  });
}

function formatPeso(amount) {
  const n = Number(amount);
  if (!Number.isFinite(n)) {
    return '0';
  }
  return Math.round(n).toLocaleString('en-PH');
}

function syncCourierSummary() {
  const checkedCourier = document.querySelector('input[name="shipping_courier"]:checked');
  const shopVoucherSelect = document.getElementById('shop_voucher');
  const shippingVoucherSelect = document.getElementById('shipping_voucher');
  const shippingLabel = document.getElementById('shippingLabel');
  const shippingAmount = document.getElementById('shippingAmount');
  const totalAmount = document.getElementById('totalAmount');
  const voucherDiscountRow = document.getElementById('voucherDiscountRow');
  const voucherDiscountAmount = document.getElementById('voucherDiscountAmount');
  const shippingDiscountRow = document.getElementById('shippingDiscountRow');
  const shippingDiscountAmount = document.getElementById('shippingDiscountAmount');
  if (!checkedCourier || !shopVoucherSelect || !shippingVoucherSelect || !shippingLabel || !shippingAmount || !totalAmount) {
    return;
  }

  const shipMeta = checkedCourier.closest('.ship-opt')?.querySelector('.ship-meta-value');
  if (!shipMeta) {
    return;
  }

  const courierLabel = shipMeta.dataset.label || 'Courier';
  const shippingFee = Number(shipMeta.dataset.fee || '0');
  const merchandise = Number(totalAmount.dataset.merchandise || '0');
  const selectedShopVoucherOpt = shopVoucherSelect.options[shopVoucherSelect.selectedIndex];
  const selectedShippingVoucherOpt = shippingVoucherSelect.options[shippingVoucherSelect.selectedIndex];
  const shopVoucherAmount = Number(selectedShopVoucherOpt?.dataset.amount || '0');
  const shopVoucherMinSpend = Number(selectedShopVoucherOpt?.dataset.minSpend || '0');
  const shippingVoucherMinSpend = Number(selectedShippingVoucherOpt?.dataset.minSpend || '0');
  const shopVoucherEligible = merchandise >= shopVoucherMinSpend;
  const shippingVoucherEligible = merchandise >= shippingVoucherMinSpend;

  let voucherDiscount = 0;
  let shippingDiscount = 0;
  if (shopVoucherEligible && (selectedShopVoucherOpt?.dataset.type || 'none') === 'fixed_discount') {
    voucherDiscount = Math.min(shopVoucherAmount, merchandise);
  }
  if (shippingVoucherEligible && (selectedShippingVoucherOpt?.dataset.type || 'none') === 'free_shipping') {
    shippingDiscount = shippingFee;
  }
  const totalDiscount = voucherDiscount + shippingDiscount;
  const total = Math.max(0, (merchandise + shippingFee) - totalDiscount);

  shippingLabel.textContent = 'Shipping Subtotal (' + courierLabel + ')';
  shippingAmount.textContent = 'PHP ' + formatPeso(shippingFee);
  totalAmount.textContent = 'PHP ' + formatPeso(total);

  if (voucherDiscountRow && voucherDiscountAmount) {
    if (voucherDiscount > 0) {
      voucherDiscountRow.style.display = '';
      voucherDiscountAmount.textContent = '-PHP ' + formatPeso(voucherDiscount);
    } else {
      voucherDiscountRow.style.display = 'none';
    }
  }

  if (shippingDiscountRow && shippingDiscountAmount) {
    if (shippingDiscount > 0) {
      shippingDiscountRow.style.display = '';
      shippingDiscountAmount.textContent = '-PHP ' + formatPeso(shippingDiscount);
    } else {
      shippingDiscountRow.style.display = 'none';
    }
  }
}

document.querySelectorAll('input[name="shipping_courier"]').forEach(function (input) {
  input.addEventListener('change', syncCourierSummary);
});
const shopVoucherSelectEl = document.getElementById('shop_voucher');
if (shopVoucherSelectEl) {
  shopVoucherSelectEl.addEventListener('change', syncCourierSummary);
}
const shippingVoucherSelectEl = document.getElementById('shipping_voucher');
if (shippingVoucherSelectEl) {
  shippingVoucherSelectEl.addEventListener('change', syncCourierSummary);
}
syncCourierSummary();

function syncQrPaymentPanel() {
  const checkedPayment = document.querySelector('input[name="payment_method"]:checked');
  const qrPanel = document.getElementById('qrPaymentPanel');
  const qrImage = document.getElementById('qrImage');
  const qrTitle = document.getElementById('qrTitle');
  const qrEmptyMsg = document.getElementById('qrEmptyMsg');
  const digitalFields = document.getElementById('digitalPaymentFields');
  if (!checkedPayment || !qrPanel || !qrImage || !qrTitle || !qrEmptyMsg) {
    return;
  }

  const method = checkedPayment.value;
  if (method !== 'gcash' && method !== 'gotime') {
    qrPanel.style.display = 'none';
    if (digitalFields) {
      digitalFields.style.display = 'none';
    }
    return;
  }

  const qrSrc = method === 'gcash' ? qrImage.dataset.gcashSrc : qrImage.dataset.gotimeSrc;
  qrTitle.textContent = method === 'gcash' ? 'Scan this GCash QR to pay' : 'Scan this GoTyme QR to pay';
  qrPanel.style.display = 'block';

  if (qrSrc) {
    qrImage.src = qrSrc;
    qrImage.style.display = 'block';
    qrEmptyMsg.style.display = 'none';
  } else {
    qrImage.removeAttribute('src');
    qrImage.style.display = 'none';
    qrEmptyMsg.style.display = 'block';
  }

  if (digitalFields) {
    digitalFields.style.display = 'block';
  }
}

document.querySelectorAll('input[name="payment_method"]').forEach(function (input) {
  input.addEventListener('change', syncQrPaymentPanel);
});
syncQrPaymentPanel();

// Address picker sync
(function () {
  const picker = document.getElementById('addressPicker');
  if (!picker) return;
  const nameEl = document.getElementById('buyerNameText');
  const contactEl = document.getElementById('buyerContactLine');
  const addressEl = document.getElementById('buyerAddressLine');
  const badgeEl = document.getElementById('buyerLabelBadge');

  picker.querySelectorAll('input[type="radio"][name="address_id"]').forEach(function (input) {
    input.addEventListener('change', function () {
      picker.querySelectorAll('.address-option').forEach(function (opt) {
        opt.classList.remove('selected');
      });
      const option = input.closest('.address-option');
      if (!option) return;
      option.classList.add('selected');
      if (nameEl) nameEl.textContent = option.dataset.name || 'Receiver';
      if (contactEl) contactEl.textContent = option.dataset.contact || 'No contact number';
      if (addressEl) addressEl.textContent = option.dataset.address || 'No complete address found.';
      if (badgeEl) {
        const label = option.dataset.label || '';
        badgeEl.textContent = label;
        badgeEl.style.display = label !== '' ? 'inline-flex' : 'none';
      }
    });
  });
})();

// Fallback menu initializer for pages where shared partial JS does not bind.
(function () {
  function initFallbackMenus() {
    const accountDropdown = document.querySelector('.account-dropdown');
    const accountTrigger = document.querySelector('.account-trigger');
    const accountMenu = document.querySelector('.account-menu');
    if (
      accountDropdown &&
      accountTrigger &&
      accountMenu &&
      accountDropdown.dataset.menuInit !== '1' &&
      accountDropdown.dataset.fallbackInit !== '1'
    ) {
      accountDropdown.dataset.fallbackInit = '1';

      const syncAccountState = function () {
        const open = accountDropdown.classList.contains('active');
        accountTrigger.setAttribute('aria-expanded', open ? 'true' : 'false');
      };

      accountTrigger.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        accountDropdown.classList.toggle('active');
        syncAccountState();
      });

      accountMenu.addEventListener('click', function (e) {
        e.stopPropagation();
      });

      document.addEventListener('click', function () {
        accountDropdown.classList.remove('active');
        syncAccountState();
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
          accountDropdown.classList.remove('active');
          syncAccountState();
        }
      });
    }

    const companyDropdown = document.querySelector('.company-dropdown');
    const companyTrigger = document.querySelector('.company-trigger');
    const companyMenu = document.querySelector('.company-menu');
    if (
      companyDropdown &&
      companyTrigger &&
      companyMenu &&
      companyDropdown.dataset.menuInit !== '1' &&
      companyDropdown.dataset.fallbackInit !== '1'
    ) {
      companyDropdown.dataset.fallbackInit = '1';

      const syncCompanyState = function () {
        const open = companyDropdown.classList.contains('active');
        companyTrigger.setAttribute('aria-expanded', open ? 'true' : 'false');
      };

      companyTrigger.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        companyDropdown.classList.toggle('active');
        syncCompanyState();
      });

      companyMenu.addEventListener('click', function (e) {
        e.stopPropagation();
      });

      document.addEventListener('click', function () {
        companyDropdown.classList.remove('active');
        syncCompanyState();
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      setTimeout(initFallbackMenus, 0);
    });
  } else {
    setTimeout(initFallbackMenus, 0);
  }
})();

</script>

<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
include __DIR__ . '/partials/user_footer.php';
?>

</body>
</html>



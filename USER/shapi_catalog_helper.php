<?php

function rbj_shapi_normalize(string $value): string
{
    $value = strtolower($value);
    $value = str_replace(['&', '@', '+'], ' ', $value);
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';
    $value = preg_replace('/\s+/', ' ', trim($value)) ?? '';
    return $value;
}

function rbj_shapi_tokens(string $value): array
{
    $normalized = rbj_shapi_normalize($value);
    if ($normalized === '') {
        return [];
    }
    $tokens = explode(' ', $normalized);
    $ignore = [
        'seat', 'cover', 'universal', 'rbj', 'accessories', 'motor', 'new',
        'with', 'and', 'version', 'design', 'concept'
    ];
    $filtered = [];
    foreach ($tokens as $token) {
        if ($token === '' || in_array($token, $ignore, true)) {
            continue;
        }
        $filtered[] = $token;
    }
    return array_values(array_unique($filtered));
}

function rbj_shapi_humanize(string $value): string
{
    $value = str_replace(['_', '-'], ' ', $value);
    $value = preg_replace('/\s+/', ' ', trim($value)) ?? '';
    return ucwords($value);
}

function rbj_shapi_alias_map(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }

    $map = [
        rbj_shapi_normalize('BRIDE DARK EDITION SEAT COVER | UNIVERSAL') => 'bride_dark_edition_universal_seat_cover',
        rbj_shapi_normalize('BRIDE UNIVERSAL SEAT COVER') => 'bride_universal_seat_cover',
        rbj_shapi_normalize('INDO CONCEPT SEATS | NEW DESIGN') => 'indoseat_newdesign',
    ];

    return $map;
}

function rbj_shapi_extract_folder_from_path(?string $path): string
{
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }

    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#^\.\./#', '', $path) ?? $path;
    if (!preg_match('#(?:^|/)shapi/([^/]+)/#i', $path, $matches)) {
        return '';
    }

    return urldecode((string)($matches[1] ?? ''));
}

function rbj_template_image_url(?string $imagePath): string
{
    $imagePath = trim((string)$imagePath);
    if ($imagePath === '') {
        return '';
    }
    if (preg_match('/^https?:\/\//i', $imagePath)) {
        return $imagePath;
    }
    $imagePath = str_replace('\\', '/', $imagePath);
    if (strpos($imagePath, 'shapi/') === 0 || strpos($imagePath, 'templates/') === 0 || strpos($imagePath, 'uploads/') === 0) {
        return '../' . $imagePath;
    }
    return '../templates/' . $imagePath;
}

function rbj_is_placeholder_catalog_image(?string $imagePath): bool
{
    $imagePath = trim((string)$imagePath);
    if ($imagePath === '') {
        return false;
    }

    $imagePath = str_replace('\\', '/', $imagePath);
    $baseName = strtolower(basename($imagePath));
    if ($baseName === 'rbjlogo.png') {
        return true;
    }

    return (bool)preg_match('/^cover\d+\.(jpg|jpeg|png|webp|gif)$/i', $baseName);
}

function rbj_shapi_catalog_index(): array
{
    static $index = null;
    if ($index !== null) {
        return $index;
    }

    $index = [];
    $shapiRoot = realpath(__DIR__ . '/../shapi');
    if ($shapiRoot === false || !is_dir($shapiRoot)) {
        return $index;
    }

    $folders = scandir($shapiRoot);
    if ($folders === false) {
        return $index;
    }

    foreach ($folders as $folder) {
        if ($folder === '.' || $folder === '..') {
            continue;
        }
        $folderPath = $shapiRoot . DIRECTORY_SEPARATOR . $folder;
        if (!is_dir($folderPath)) {
            continue;
        }

        $files = scandir($folderPath);
        if ($files === false) {
            continue;
        }

        $items = [];
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $fullPath = $folderPath . DIRECTORY_SEPARATOR . $file;
            if (!is_file($fullPath)) {
                continue;
            }

            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                continue;
            }

            $nameOnly = pathinfo($file, PATHINFO_FILENAME);
            $url = '../shapi/' . rawurlencode($folder) . '/' . rawurlencode($file);
            $items[] = [
                'label' => rbj_shapi_humanize($nameOnly),
                'image_url' => $url,
                'file_name' => $file
            ];
        }

        if (empty($items)) {
            continue;
        }

        usort($items, static function (array $a, array $b): int {
            return strnatcasecmp($a['file_name'], $b['file_name']);
        });

        $folderKey = rbj_shapi_normalize($folder);
        $index[] = [
            'folder' => $folder,
            'folder_key' => $folderKey,
            'tokens' => rbj_shapi_tokens($folder),
            'items' => $items
        ];
    }

    return $index;
}

function rbj_shapi_find_folder_items(string $folderName): array
{
    $index = rbj_shapi_catalog_index();
    if (empty($index)) {
        return [];
    }

    $wanted = rbj_shapi_normalize($folderName);
    if ($wanted === '') {
        return [];
    }

    foreach ($index as $folderData) {
        if (($folderData['folder_key'] ?? '') === $wanted) {
            return (array)($folderData['items'] ?? []);
        }
    }

    return [];
}

function rbj_shapi_infer_folder_from_template(mysqli $conn, int $templateId): string
{
    static $cache = [];

    $templateId = (int)$templateId;
    if ($templateId <= 0) {
        return '';
    }

    if (array_key_exists($templateId, $cache)) {
        return $cache[$templateId];
    }

    $folder = '';

    if (rbj_db_table_exists($conn, 'product_images')) {
        $stmt = $conn->prepare('SELECT image_path FROM product_images WHERE template_id = ? ORDER BY is_primary DESC, id ASC');
        if ($stmt) {
            $stmt->bind_param('i', $templateId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $candidate = rbj_shapi_extract_folder_from_path((string)($row['image_path'] ?? ''));
                if ($candidate !== '') {
                    $folder = $candidate;
                    break;
                }
            }
            $stmt->close();
        }
    }

    if ($folder === '' && rbj_db_table_exists($conn, 'customization_templates')) {
        $stmt = $conn->prepare('SELECT image_path FROM customization_templates WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $templateId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $folder = rbj_shapi_extract_folder_from_path((string)($row['image_path'] ?? ''));
        }
    }

    $cache[$templateId] = $folder;
    return $folder;
}

function rbj_find_shapi_choices(string $productName, ?mysqli $conn = null, int $templateId = 0): array
{
    $index = rbj_shapi_catalog_index();
    if (empty($index)) {
        return [];
    }

    if ($conn instanceof mysqli && $templateId > 0) {
        $folderFromTemplate = rbj_shapi_infer_folder_from_template($conn, $templateId);
        if ($folderFromTemplate !== '') {
            $items = rbj_shapi_find_folder_items($folderFromTemplate);
            if (!empty($items)) {
                return $items;
            }
        }
    }

    $productKey = rbj_shapi_normalize($productName);
    if ($productKey === '') {
        return [];
    }

    $aliasFolder = rbj_shapi_alias_map()[$productKey] ?? '';
    if ($aliasFolder !== '') {
        $items = rbj_shapi_find_folder_items($aliasFolder);
        if (!empty($items)) {
            return $items;
        }
    }

    $productTokens = rbj_shapi_tokens($productName);

    $bestScore = 0.0;
    $bestItems = [];
    $bestCommonCount = 0;
    $bestHasDirectNameMatch = false;
    foreach ($index as $folderData) {
        $folderKey = $folderData['folder_key'];
        $score = 0.0;
        $hasDirectNameMatch = false;

        if ($productKey === $folderKey) {
            $score += 100.0;
            $hasDirectNameMatch = true;
        }
        if (strpos($folderKey, $productKey) !== false || strpos($productKey, $folderKey) !== false) {
            $score += 20.0;
            $hasDirectNameMatch = true;
        }

        $folderTokens = $folderData['tokens'];
        $commonCount = 0;
        if (!empty($productTokens) && !empty($folderTokens)) {
            $common = array_intersect($productTokens, $folderTokens);
            $commonCount = count($common);
            if ($commonCount > 0) {
                $score += ($commonCount * 10.0);
                $score += ($commonCount / max(count($productTokens), count($folderTokens))) * 10.0;
            }
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $bestItems = $folderData['items'];
            $bestCommonCount = $commonCount;
            $bestHasDirectNameMatch = $hasDirectNameMatch;
        }
    }

    if ($bestScore < 10.0) {
        return [];
    }

    if (!$bestHasDirectNameMatch && $bestCommonCount < 2) {
        return [];
    }

    return $bestItems;
}

function rbj_db_table_exists(mysqli $conn, string $tableName): bool
{
    static $cache = [];
    $key = strtolower(trim($tableName));
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $safe = $conn->real_escape_string($tableName);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    $exists = $res instanceof mysqli_result && $res->num_rows > 0;
    $cache[$key] = $exists;
    return $exists;
}

function rbj_db_column_exists(mysqli $conn, string $tableName, string $columnName): bool
{
    static $cache = [];
    $key = strtolower(trim($tableName)) . '.' . strtolower(trim($columnName));
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $safeTable = $conn->real_escape_string($tableName);
    $safeCol = $conn->real_escape_string($columnName);
    $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeCol}'");
    $exists = $res instanceof mysqli_result && $res->num_rows > 0;
    $cache[$key] = $exists;
    return $exists;
}

function rbj_ensure_cart_choice_key_column(mysqli $conn): bool
{
    if (!rbj_db_table_exists($conn, 'cart')) {
        return false;
    }
    if (rbj_db_column_exists($conn, 'cart', 'choice_key')) {
        return true;
    }
    $conn->query("ALTER TABLE cart ADD COLUMN choice_key VARCHAR(191) NULL AFTER customizations");
    $verify = $conn->query("SHOW COLUMNS FROM cart LIKE 'choice_key'");
    return $verify instanceof mysqli_result && $verify->num_rows > 0;
}

function rbj_choice_key_from_label(string $label, string $imageUrl = ''): string
{
    $key = rbj_shapi_normalize($label);
    $key = preg_replace('/\s+/', '_', trim((string)$key)) ?? '';
    if ($key === '') {
        $key = 'choice_' . substr(md5($imageUrl !== '' ? $imageUrl : $label), 0, 12);
    }
    return $key;
}

function rbj_is_standard_customization(string $customization): bool
{
    $normalized = rbj_shapi_normalize($customization);
    return $normalized === '' || $normalized === 'standard package' || $normalized === 'standard';
}

function rbj_resolve_item_stock(mysqli $conn, int $templateId, string $customization = 'Standard package', string $choiceKey = ''): array
{
    $templateId = (int)$templateId;
    $customization = trim((string)$customization);
    $choiceKey = trim((string)$choiceKey);
    $normalizedCustomization = rbj_shapi_normalize($customization);

    $templateStock = 0;
    if (rbj_db_column_exists($conn, 'customization_templates', 'stock_quantity')) {
        $stmt = $conn->prepare("SELECT stock_quantity FROM customization_templates WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $templateId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $templateStock = max(0, (int)($row['stock_quantity'] ?? 0));
        }
    }

    $best = [
        'available' => $templateStock,
        'source' => 'template',
        'choice_key' => '',
        'label' => ''
    ];

    if (rbj_is_standard_customization($customization)) {
        return $best;
    }

    // If choice_key is provided, try to match it first (case-insensitive)
    if ($choiceKey !== '') {
        // Try matching in customization_choice_stock table
        if (rbj_db_table_exists($conn, 'customization_choice_stock')) {
            $stmt = $conn->prepare("SELECT choice_key, choice_label, stock_quantity FROM customization_choice_stock WHERE template_id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $templateId);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $rowKey = trim((string)($row['choice_key'] ?? ''));
                    $rowLabel = trim((string)($row['choice_label'] ?? ''));
                    $rowStock = max(0, (int)($row['stock_quantity'] ?? 0));
                    
                    // Case-insensitive comparison for choice_key
                    if (strcasecmp($rowKey, $choiceKey) === 0) {
                        $stmt->close();
                        return [
                            'available' => $rowStock,
                            'source' => 'choice',
                            'choice_key' => $rowKey,
                            'label' => $rowLabel
                        ];
                    }
                }
                $stmt->close();
            }
        }

        // Try matching in product_images table
        if (rbj_db_column_exists($conn, 'product_images', 'stock_quantity')) {
            $stmt = $conn->prepare("SELECT image_path, alt_text, stock_quantity FROM product_images WHERE template_id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $templateId);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $altText = trim((string)($row['alt_text'] ?? ''));
                    $imagePath = trim((string)($row['image_path'] ?? ''));
                    $rowKey = rbj_choice_key_from_label($altText, $imagePath);
                    $rowStock = max(0, (int)($row['stock_quantity'] ?? 0));
                    
                    // Case-insensitive comparison for choice_key
                    if (strcasecmp($rowKey, $choiceKey) === 0) {
                        $stmt->close();
                        return [
                            'available' => $rowStock,
                            'source' => 'product_images',
                            'choice_key' => $rowKey,
                            'label' => $altText
                        ];
                    }
                }
                $stmt->close();
            }
        }
    }

    // Fall back to label-based matching if choice_key didn't match
    if (rbj_db_table_exists($conn, 'customization_choice_stock')) {
        $stmt = $conn->prepare("SELECT choice_key, choice_label, stock_quantity FROM customization_choice_stock WHERE template_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $templateId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $rowKey = trim((string)($row['choice_key'] ?? ''));
                $rowLabel = trim((string)($row['choice_label'] ?? ''));
                $rowStock = max(0, (int)($row['stock_quantity'] ?? 0));
                $rowNorm = rbj_shapi_normalize($rowLabel);
                $match = false;
                if ($normalizedCustomization !== '' && $rowNorm !== '') {
                    $match = $rowNorm === $normalizedCustomization
                        || strpos($rowNorm, $normalizedCustomization) !== false
                        || strpos($normalizedCustomization, $rowNorm) !== false;
                }
                if ($match) {
                    $stmt->close();
                    return [
                        'available' => $rowStock,
                        'source' => 'choice',
                        'choice_key' => $rowKey,
                        'label' => $rowLabel
                    ];
                }
            }
            $stmt->close();
        }
    }

    if (rbj_db_column_exists($conn, 'product_images', 'stock_quantity')) {
        $stmt = $conn->prepare("SELECT image_path, alt_text, stock_quantity FROM product_images WHERE template_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $templateId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $altText = trim((string)($row['alt_text'] ?? ''));
                $imagePath = trim((string)($row['image_path'] ?? ''));
                $rowKey = rbj_choice_key_from_label($altText, $imagePath);
                $rowNorm = rbj_shapi_normalize($altText);
                $rowStock = max(0, (int)($row['stock_quantity'] ?? 0));
                $match = false;
                if ($normalizedCustomization !== '' && $rowNorm !== '') {
                    $match = $rowNorm === $normalizedCustomization
                        || strpos($rowNorm, $normalizedCustomization) !== false
                        || strpos($normalizedCustomization, $rowNorm) !== false;
                }
                if ($match) {
                    $stmt->close();
                    return [
                        'available' => $rowStock,
                        'source' => 'product_images',
                        'choice_key' => $rowKey,
                        'label' => $altText
                    ];
                }
            }
            $stmt->close();
        }
    }

    return $best;
}

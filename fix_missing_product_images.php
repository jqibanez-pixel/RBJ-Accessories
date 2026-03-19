<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/USER/shapi_catalog_helper.php';

$fallbackImage = 'cover1.jpg';
if (!is_file(__DIR__ . DIRECTORY_SEPARATOR . $fallbackImage)) {
    $fallbackImage = 'rbjlogo.png';
}

$templates = [];
$result = $conn->query("
    SELECT t.id, t.name, t.image_path, COUNT(p.id) AS image_count
    FROM customization_templates t
    LEFT JOIN product_images p ON p.template_id = t.id
    WHERE t.is_active = 1
    GROUP BY t.id, t.name, t.image_path
");
if ($result instanceof mysqli_result) {
    while ($row = $result->fetch_assoc()) {
        $templates[] = $row;
    }
    $result->free();
}

$updateStmt = $conn->prepare('UPDATE customization_templates SET image_path = ? WHERE id = ?');
$insertImageStmt = $conn->prepare('INSERT INTO product_images (template_id, image_path, is_primary, alt_text) VALUES (?, ?, ?, ?)');
$existsImageStmt = $conn->prepare('SELECT id FROM product_images WHERE template_id = ? AND image_path = ? LIMIT 1');

if (!$updateStmt || !$insertImageStmt || !$existsImageStmt) {
    echo "Failed to prepare statements.\n";
    exit(1);
}

$updatedTemplatePath = 0;
$insertedImages = 0;
$fixedProducts = 0;

foreach ($templates as $template) {
    $id = (int)$template['id'];
    $name = (string)$template['name'];
    $imagePath = trim((string)($template['image_path'] ?? ''));
    $imageCount = (int)$template['image_count'];

    $needsPath = $imagePath === '';
    $needsImageRows = $imageCount <= 0;
    if (!$needsPath && !$needsImageRows) {
        continue;
    }

    $selectedPath = $imagePath;
    if ($selectedPath === '') {
        $choices = rbj_find_shapi_choices($name);
        if (!empty($choices)) {
            $url = (string)$choices[0]['image_url']; // ../shapi/folder/file.png
            $selectedPath = ltrim(str_replace('\\', '/', preg_replace('#^\.\./#', '', $url) ?? $url), '/');
        }
    }
    if ($selectedPath === '') {
        $selectedPath = $fallbackImage;
    }

    if ($needsPath) {
        $updateStmt->bind_param('si', $selectedPath, $id);
        $updateStmt->execute();
        $updatedTemplatePath++;
    }

    if ($needsImageRows) {
        $existsImageStmt->bind_param('is', $id, $selectedPath);
        $existsImageStmt->execute();
        $exists = $existsImageStmt->get_result()->fetch_assoc();
        if (!$exists) {
            $altText = $name . ' image';
            $isPrimary = 1;
            $insertImageStmt->bind_param('isis', $id, $selectedPath, $isPrimary, $altText);
            $insertImageStmt->execute();
            $insertedImages++;
        }
    }

    $fixedProducts++;
}

$updateStmt->close();
$insertImageStmt->close();
$existsImageStmt->close();
$conn->close();

echo "Done.\n";
echo "Products fixed: {$fixedProducts}\n";
echo "Updated template image_path: {$updatedTemplatePath}\n";
echo "Inserted product_images: {$insertedImages}\n";

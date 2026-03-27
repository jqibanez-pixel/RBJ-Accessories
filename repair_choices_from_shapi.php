<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/USER/shapi_catalog_helper.php';

function to_db_path_from_url(string $url): string
{
    $url = trim($url);
    $url = preg_replace('#^\.\./#', '', $url) ?? $url;
    return ltrim(str_replace('\\', '/', $url), '/');
}

$templates = [];
$result = $conn->query("
    SELECT id, name, image_path, description
    FROM customization_templates
    WHERE is_active = 1
    ORDER BY id ASC
");
if ($result instanceof mysqli_result) {
    while ($row = $result->fetch_assoc()) {
        $templates[] = $row;
    }
    $result->free();
}

$selectImagesStmt = $conn->prepare('SELECT id, image_path, is_primary FROM product_images WHERE template_id = ? ORDER BY is_primary DESC, id ASC');
$existsImageStmt = $conn->prepare('SELECT id FROM product_images WHERE template_id = ? AND image_path = ? LIMIT 1');
$insertImageStmt = $conn->prepare('INSERT INTO product_images (template_id, image_path, is_primary, alt_text) VALUES (?, ?, ?, ?)');
$updateTemplateImageStmt = $conn->prepare('UPDATE customization_templates SET image_path = ? WHERE id = ?');

if (!$selectImagesStmt || !$existsImageStmt || !$insertImageStmt || !$updateTemplateImageStmt) {
    echo "Failed to prepare statements.\n";
    exit(1);
}

$checked = 0;
$updatedProducts = 0;
$insertedChoices = 0;
$switchedPrimary = 0;

foreach ($templates as $template) {
    $checked++;
    $templateId = (int)$template['id'];
    $templateName = (string)$template['name'];
    $currentTemplatePath = trim((string)($template['image_path'] ?? ''));

    $choices = rbj_find_shapi_choices($templateName, $conn, $templateId);
    if (count($choices) < 2) {
        continue;
    }

    $selectImagesStmt->bind_param('i', $templateId);
    $selectImagesStmt->execute();
    $imgResult = $selectImagesStmt->get_result();
    $existing = [];
    while ($row = $imgResult->fetch_assoc()) {
        $path = trim((string)$row['image_path']);
        $existing[$path] = [
            'id' => (int)$row['id'],
            'is_primary' => (int)$row['is_primary']
        ];
    }

    $nonGenericCount = 0;
    foreach (array_keys($existing) as $path) {
        $normalized = strtolower(str_replace('\\', '/', $path));
        if (!preg_match('/^cover[0-9]+\.(jpg|jpeg|png|webp)$/', basename($normalized))) {
            $nonGenericCount++;
        }
    }

    if ($nonGenericCount >= 2) {
        continue;
    }

    $firstPath = '';
    $productTouched = false;

    foreach ($choices as $index => $choice) {
        $dbPath = to_db_path_from_url((string)$choice['image_url']);
        if ($dbPath === '') {
            continue;
        }
        if ($firstPath === '') {
            $firstPath = $dbPath;
        }

        $isPrimary = $index === 0 ? 1 : 0;
        $altText = $templateName . ' - ' . (string)$choice['label'];

        if (isset($existing[$dbPath])) {
            if ($isPrimary === 1 && (int)$existing[$dbPath]['is_primary'] !== 1) {
                $conn->query('UPDATE product_images SET is_primary = 0 WHERE template_id = ' . $templateId);
                $conn->query('UPDATE product_images SET is_primary = 1 WHERE id = ' . (int)$existing[$dbPath]['id']);
                $productTouched = true;
                $switchedPrimary++;
            }
            continue;
        }

        if ($isPrimary === 1) {
            $conn->query('UPDATE product_images SET is_primary = 0 WHERE template_id = ' . $templateId);
        }

        $insertImageStmt->bind_param('isis', $templateId, $dbPath, $isPrimary, $altText);
        $insertImageStmt->execute();
        $insertedChoices++;
        $productTouched = true;
    }

    if ($firstPath !== '') {
        $currentNorm = strtolower(str_replace('\\', '/', $currentTemplatePath));
        $firstNorm = strtolower(str_replace('\\', '/', $firstPath));
        $shouldUpdateTemplateImage = (
            $currentTemplatePath === '' ||
            preg_match('/^cover[0-9]+\.(jpg|jpeg|png|webp)$/', basename($currentNorm)) ||
            $currentNorm !== $firstNorm
        );
        if ($shouldUpdateTemplateImage) {
            $updateTemplateImageStmt->bind_param('si', $firstPath, $templateId);
            $updateTemplateImageStmt->execute();
            $productTouched = true;
        }
    }

    if ($productTouched) {
        $updatedProducts++;
    }
}

$selectImagesStmt->close();
$existsImageStmt->close();
$insertImageStmt->close();
$updateTemplateImageStmt->close();
$conn->close();

echo "Done.\n";
echo "Checked products: {$checked}\n";
echo "Updated products: {$updatedProducts}\n";
echo "Inserted choices: {$insertedChoices}\n";
echo "Primary switched: {$switchedPrimary}\n";

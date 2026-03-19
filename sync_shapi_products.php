<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/USER/shapi_catalog_helper.php';

function shapi_title_from_folder(string $folder): string
{
    return rbj_shapi_humanize($folder);
}

function shapi_marker(string $folder): string
{
    return '[SHAPI_FOLDER:' . $folder . ']';
}

$shapiRoot = realpath(__DIR__ . '/shapi');
if ($shapiRoot === false || !is_dir($shapiRoot)) {
    echo "shapi folder not found.\n";
    exit(1);
}

$folders = scandir($shapiRoot);
if ($folders === false) {
    echo "Unable to read shapi folder.\n";
    exit(1);
}

$existingTemplates = [];
$result = $conn->query('SELECT id, name, description, image_path FROM customization_templates');
if ($result instanceof mysqli_result) {
    while ($row = $result->fetch_assoc()) {
        $existingTemplates[] = $row;
    }
    $result->free();
}

$byMarker = [];
$byNameKey = [];
foreach ($existingTemplates as $tpl) {
    $description = (string)($tpl['description'] ?? '');
    if (preg_match('/\[SHAPI_FOLDER:(.+?)\]/', $description, $matches)) {
        $byMarker[$matches[1]] = (int)$tpl['id'];
    }
    $nameKey = rbj_shapi_normalize((string)$tpl['name']);
    if ($nameKey !== '' && !isset($byNameKey[$nameKey])) {
        $byNameKey[$nameKey] = (int)$tpl['id'];
    }
}

$insertTemplateStmt = $conn->prepare(
    'INSERT INTO customization_templates (name, description, category, base_price, image_path, is_active, created_at)
     VALUES (?, ?, ?, ?, ?, 1, NOW())'
);
$updateTemplateStmt = $conn->prepare(
    'UPDATE customization_templates SET name = ?, description = ?, category = ?, image_path = ?, is_active = 1 WHERE id = ?'
);
$insertImageStmt = $conn->prepare(
    'INSERT INTO product_images (template_id, image_path, is_primary, alt_text) VALUES (?, ?, ?, ?)'
);

if (!$insertTemplateStmt || !$updateTemplateStmt || !$insertImageStmt) {
    echo "Failed to prepare statements.\n";
    exit(1);
}

$createdProducts = 0;
$updatedProducts = 0;
$addedImages = 0;
$processedFolders = 0;

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

    $imageFiles = [];
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
        $imageFiles[] = $file;
    }

    if (empty($imageFiles)) {
        continue;
    }

    natcasesort($imageFiles);
    $imageFiles = array_values($imageFiles);

    $folderRel = str_replace('\\', '/', $folder);
    $firstImagePath = 'shapi/' . $folderRel . '/' . $imageFiles[0];

    $productName = shapi_title_from_folder($folder);
    $marker = shapi_marker($folder);
    $description = 'Imported from shapi catalog folder. ' . $marker;
    $category = 'seat_covers';
    $defaultPrice = 850.00;

    $nameKey = rbj_shapi_normalize($productName);
    $templateId = 0;

    if (isset($byMarker[$folder])) {
        $templateId = (int)$byMarker[$folder];
    } elseif ($nameKey !== '' && isset($byNameKey[$nameKey])) {
        $templateId = (int)$byNameKey[$nameKey];
    }

    if ($templateId > 0) {
        $updateTemplateStmt->bind_param('ssssi', $productName, $description, $category, $firstImagePath, $templateId);
        $updateTemplateStmt->execute();
        $updatedProducts++;
    } else {
        $insertTemplateStmt->bind_param('sssds', $productName, $description, $category, $defaultPrice, $firstImagePath);
        $insertTemplateStmt->execute();
        $templateId = (int)$conn->insert_id;
        if ($templateId <= 0) {
            continue;
        }
        $byMarker[$folder] = $templateId;
        if ($nameKey !== '') {
            $byNameKey[$nameKey] = $templateId;
        }
        $createdProducts++;
    }

    $existingImagePaths = [];
    $imgStmt = $conn->prepare('SELECT image_path FROM product_images WHERE template_id = ?');
    if ($imgStmt) {
        $imgStmt->bind_param('i', $templateId);
        $imgStmt->execute();
        $imgResult = $imgStmt->get_result();
        while ($imgRow = $imgResult->fetch_assoc()) {
            $existingImagePaths[(string)$imgRow['image_path']] = true;
        }
        $imgStmt->close();
    }

    foreach ($imageFiles as $index => $file) {
        $path = 'shapi/' . $folderRel . '/' . $file;
        if (isset($existingImagePaths[$path])) {
            continue;
        }
        $isPrimary = $index === 0 ? 1 : 0;
        $altText = $productName . ' - ' . rbj_shapi_humanize(pathinfo($file, PATHINFO_FILENAME));
        $insertImageStmt->bind_param('isis', $templateId, $path, $isPrimary, $altText);
        $insertImageStmt->execute();
        $addedImages++;
    }

    $processedFolders++;
}

$insertTemplateStmt->close();
$updateTemplateStmt->close();
$insertImageStmt->close();
$conn->close();

echo "Done.\n";
echo "Processed folders: {$processedFolders}\n";
echo "Created products: {$createdProducts}\n";
echo "Updated products: {$updatedProducts}\n";
echo "Added product_images: {$addedImages}\n";

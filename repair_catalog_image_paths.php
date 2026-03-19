<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/USER/shapi_catalog_helper.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function rbj_norm(string $s): string
{
    $s = strtolower($s);
    $s = str_replace(['&', '@', '+', '%20'], ' ', $s);
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s) ?? '';
    return trim(preg_replace('/\s+/', ' ', $s) ?? '');
}

function rbj_tokens(string $s): array
{
    $s = rbj_norm($s);
    if ($s === '') {
        return [];
    }
    $parts = explode(' ', $s);
    $ignore = ['seat', 'cover', 'universal', 'rbj', 'accessories', 'new', 'with', 'and', 'for', 'the'];
    $out = [];
    foreach ($parts as $p) {
        if ($p === '' || in_array($p, $ignore, true)) {
            continue;
        }
        $out[] = $p;
    }
    return array_values(array_unique($out));
}

function rbj_filename_score(string $productName, string $fileNameNoExt): float
{
    $pNorm = rbj_norm($productName);
    $fNorm = rbj_norm($fileNameNoExt);
    $pTokens = rbj_tokens($productName);
    $fTokens = rbj_tokens($fileNameNoExt);

    $score = 0.0;
    if ($fNorm !== '' && strpos($pNorm, $fNorm) !== false) {
        $score += 120.0; // full filename phrase appears in product name
    }
    if ($fNorm !== '' && strpos($fNorm, $pNorm) !== false && strlen($pNorm) >= 8) {
        $score += 75.0;
    }

    if (!empty($pTokens) && !empty($fTokens)) {
        $common = array_intersect($pTokens, $fTokens);
        $commonCount = count($common);
        if ($commonCount > 0) {
            $score += $commonCount * 16.0;
            $union = array_unique(array_merge($pTokens, $fTokens));
            $score += ($commonCount / max(1, count($union))) * 35.0;
        }
    }
    return $score;
}

function rbj_path_to_folder(string $path): string
{
    $path = str_replace('\\', '/', trim($path));
    if (stripos($path, 'shapi/') !== 0) {
        return '';
    }
    $parts = explode('/', $path);
    return $parts[1] ?? '';
}

function rbj_build_shapi_folder_map(string $shapiRoot): array
{
    $map = [];
    if (!is_dir($shapiRoot)) {
        return $map;
    }
    $folders = scandir($shapiRoot);
    if (!is_array($folders)) {
        return $map;
    }
    foreach ($folders as $folder) {
        if ($folder === '.' || $folder === '..') {
            continue;
        }
        $dir = $shapiRoot . DIRECTORY_SEPARATOR . $folder;
        if (!is_dir($dir)) {
            continue;
        }
        $files = scandir($dir);
        if (!is_array($files)) {
            continue;
        }
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $full = $dir . DIRECTORY_SEPARATOR . $file;
            if (!is_file($full)) {
                continue;
            }
            $ext = strtolower((string)pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                continue;
            }
            $nameNoExt = (string)pathinfo($file, PATHINFO_FILENAME);
            $map[$folder][] = [
                'file' => $file,
                'name_no_ext' => $nameNoExt,
                'path' => 'shapi/' . $folder . '/' . $file,
            ];
        }
    }
    return $map;
}

$shapiRoot = realpath(__DIR__ . '/shapi');
if ($shapiRoot === false) {
    echo "shapi folder not found.\n";
    exit(1);
}
$folderMap = rbj_build_shapi_folder_map($shapiRoot);
if (empty($folderMap)) {
    echo "No shapi images found.\n";
    exit(1);
}

$res = $conn->query("SELECT id, name, image_path FROM customization_templates WHERE is_active = 1 ORDER BY id ASC");
$templates = [];
while ($row = $res->fetch_assoc()) {
    $templates[] = [
        'id' => (int)$row['id'],
        'name' => (string)$row['name'],
        'image_path' => trim((string)($row['image_path'] ?? '')),
    ];
}
$res->free();

if (empty($templates)) {
    echo "No active templates found.\n";
    $conn->close();
    exit(0);
}

// Usage map to discourage duplicates inside the same run.
$usage = [];
foreach ($templates as $tpl) {
    $usage[$tpl['image_path']] = ($usage[$tpl['image_path']] ?? 0) + 1;
}

$updates = [];
foreach ($templates as $tpl) {
    $currentPath = $tpl['image_path'];
    $currentFolder = rbj_path_to_folder($currentPath);

    // Preferred folder: from shapi helper match, fallback to current folder.
    $preferredFolder = '';
    $choices = rbj_find_shapi_choices($tpl['name']);
    if (!empty($choices)) {
        $choicePath = str_replace('\\', '/', preg_replace('#^\.\./#', '', (string)($choices[0]['image_url'] ?? '')) ?? '');
        $preferredFolder = rbj_path_to_folder($choicePath);
    }
    if ($preferredFolder === '') {
        $preferredFolder = $currentFolder;
    }
    if ($preferredFolder === '' || empty($folderMap[$preferredFolder])) {
        continue;
    }

    $candidates = $folderMap[$preferredFolder];
    $bestPath = $currentPath;
    $bestEffective = -INF;
    foreach ($candidates as $cand) {
        $score = rbj_filename_score($tpl['name'], (string)$cand['name_no_ext']);
        $candPath = (string)$cand['path'];

        // Keep very strong preference for current exact path if already good.
        if ($candPath === $currentPath) {
            $score += 5.0;
        }

        $dupPenalty = ((int)($usage[$candPath] ?? 0)) * 3.5;
        $effective = $score - $dupPenalty;
        if ($effective > $bestEffective) {
            $bestEffective = $effective;
            $bestPath = $candPath;
        }
    }

    // Avoid noisy remaps with very weak confidence.
    $currentScore = -INF;
    foreach ($candidates as $cand) {
        $candPath = (string)$cand['path'];
        if ($candPath === $currentPath) {
            $currentScore = rbj_filename_score($tpl['name'], (string)$cand['name_no_ext']);
            break;
        }
    }
    if ($bestPath !== $currentPath && $bestEffective >= 10.0 && ($bestEffective - $currentScore) >= 4.0) {
        if ($currentPath !== '') {
            $usage[$currentPath] = max(0, (int)($usage[$currentPath] ?? 1) - 1);
        }
        $usage[$bestPath] = (int)($usage[$bestPath] ?? 0) + 1;
        $updates[] = [
            'id' => $tpl['id'],
            'name' => $tpl['name'],
            'from' => $currentPath,
            'to' => $bestPath,
        ];
    }
}

if (empty($updates)) {
    echo "No updates required.\n";
    $conn->close();
    exit(0);
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("UPDATE customization_templates SET image_path = ? WHERE id = ?");
    foreach ($updates as $u) {
        $stmt->bind_param('si', $u['to'], $u['id']);
        $stmt->execute();
    }
    $stmt->close();
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    echo "Failed: " . $e->getMessage() . "\n";
    $conn->close();
    exit(1);
}

echo "Updated " . count($updates) . " template image paths (full filename + preferred folder matching).\n";
foreach (array_slice($updates, 0, 30) as $u) {
    echo "#{$u['id']} {$u['name']}\n";
    echo "  from: " . ($u['from'] !== '' ? $u['from'] : '[empty]') . "\n";
    echo "  to:   {$u['to']}\n";
}
if (count($updates) > 30) {
    echo "... and " . (count($updates) - 30) . " more.\n";
}

$conn->close();


<?php
header('Content-Type: application/json; charset=utf-8');

$type = strtolower(trim($_GET['type'] ?? ''));
$region_code = trim($_GET['region_code'] ?? '');
$province_code = trim($_GET['province_code'] ?? '');
$city_code = trim($_GET['city_code'] ?? '');

$base_urls = [
    'https://psgc.cloud/api/v1',
    'https://psgc.cloud/api'
];

$path = '';
if ($type === 'provinces' && $region_code !== '') {
    $path = '/provinces?region_code=' . rawurlencode($region_code) . '&per_page=2000';
} elseif ($type === 'cities' && $province_code !== '') {
    $path = '/provinces/' . rawurlencode($province_code) . '/cities-municipalities?per_page=2000';
} elseif ($type === 'barangays' && $city_code !== '') {
    $path = '/cities-municipalities/' . rawurlencode($city_code) . '/barangays?per_page=2000';
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters']);
    exit;
}

$resp = null;
foreach ($base_urls as $base) {
    $url = $base . $path;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status >= 200 && $status < 300 && $body) {
        $resp = $body;
        break;
    }
}

if ($resp === null) {
    http_response_code(502);
    echo json_encode(['error' => 'Upstream unavailable']);
    exit;
}

echo $resp;

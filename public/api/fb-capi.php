<?php
// Facebook Conversions API relay. Pixel ID + access token are injected into
// fb-capi-config.php at deploy time (GitHub Actions) — never committed to git.
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://mclair.com.br');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$configPath = __DIR__ . '/fb-capi-config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Not configured']);
    exit;
}
require $configPath; // defines FB_PIXEL_ID and FB_ACCESS_TOKEN

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['event_name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing event_name']);
    exit;
}

$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
if (strpos($clientIp, ',') !== false) {
    $clientIp = trim(explode(',', $clientIp)[0]);
}

$userData = [
    'client_ip_address' => $clientIp,
    'client_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
];
if (!empty($input['fbp'])) $userData['fbp'] = $input['fbp'];
if (!empty($input['fbc'])) $userData['fbc'] = $input['fbc'];

$payload = [
    'data' => [[
        'event_name' => $input['event_name'],
        'event_time' => time(),
        'event_id' => $input['event_id'] ?? uniqid('evt_', true),
        'event_source_url' => $input['event_source_url'] ?? '',
        'action_source' => 'website',
        'user_data' => $userData,
    ]],
];

$ch = curl_init('https://graph.facebook.com/v20.0/' . FB_PIXEL_ID . '/events?access_token=' . urlencode(FB_ACCESS_TOKEN));
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($httpCode >= 200 && $httpCode < 300 ? 200 : 502);
echo $response ?: json_encode(['error' => 'No response from Facebook']);

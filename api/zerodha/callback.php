<?php
// ============================================
// FILE: api/upstox/login-url.php
// ============================================
header('Content-Type: application/json');
session_start();
require_once '../config.php';

$config = $BROKER_CONFIG['upstox'];
$UPSTOX_API_KEY = $config['api_key'];
$REDIRECT_URI = $config['redirect_uri'];

if (empty($UPSTOX_API_KEY) || $UPSTOX_API_KEY === 'your_upstox_api_key_here') {
    echo json_encode([
        'success' => false,
        'message' => 'Upstox API key not configured'
    ]);
    exit;
}

$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

$loginUrl = "https://api.upstox.com/v2/login/authorization/dialog?" . http_build_query([
    'client_id' => $UPSTOX_API_KEY,
    'redirect_uri' => $REDIRECT_URI,
    'response_type' => 'code',
    'state' => $state
]);

echo json_encode([
    'success' => true,
    'loginUrl' => $loginUrl
]);
?>

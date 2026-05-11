<?php
// ============================================
// FILE: api/upstox/callback.php
// ============================================
session_start();
require_once '../db.php';
require_once '../config.php';

$config = $BROKER_CONFIG['upstox'];
$UPSTOX_API_KEY = $config['api_key'];
$UPSTOX_API_SECRET = $config['api_secret'];
$REDIRECT_URI = $config['redirect_uri'];

$code = $_GET['code'] ?? null;
$state = $_GET['state'] ?? null;

if (!$code || $state !== $_SESSION['oauth_state']) {
    header('Location: ../../home.html?error=auth_failed');
    exit;
}

$url = 'https://api.upstox.com/v2/login/authorization/token';
$data = [
    'code' => $code,
    'client_id' => $UPSTOX_API_KEY,
    'client_secret' => $UPSTOX_API_SECRET,
    'redirect_uri' => $REDIRECT_URI,
    'grant_type' => 'authorization_code'
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/x-www-form-urlencoded'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($httpCode === 200 && isset($result['access_token'])) {
    $accessToken = $result['access_token'];
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO broker_connections (user_id, broker_name, access_token, created_at)
            VALUES (?, 'upstox', ?, NOW())
            ON DUPLICATE KEY UPDATE 
                access_token = VALUES(access_token),
                updated_at = NOW()
        ");
        
        $stmt->execute([$_SESSION['user_id'], $accessToken]);
        
        header('Location: ../../home.html?broker=upstox&status=connected');
    } catch (Exception $e) {
        error_log('Database error: ' . $e->getMessage());
        header('Location: ../../home.html?error=database_error');
    }
} else {
    error_log('Upstox token exchange failed: ' . $response);
    header('Location: ../../home.html?error=token_exchange_failed');
}
exit;
?>
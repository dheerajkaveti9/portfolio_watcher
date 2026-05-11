<?php
// ============================================
// FILE: api/zerodha/callback.php
// ============================================
session_start();
require_once '../db.php';
require_once '../config.php';

$config = $BROKER_CONFIG['zerodha'];
$KITE_API_KEY = $config['api_key'];
$KITE_API_SECRET = $config['api_secret'];

$request_token = $_GET['request_token'] ?? null;
$status = $_GET['status'] ?? null;

if (!$request_token || $status !== 'success') {
    header('Location: ../../home.html?error=auth_failed');
    exit;
}

$checksum = hash('sha256', $KITE_API_KEY . $request_token . $KITE_API_SECRET);

$url = 'https://api.kite.trade/session/token';
$data = [
    'api_key' => $KITE_API_KEY,
    'request_token' => $request_token,
    'checksum' => $checksum
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($httpCode === 200 && isset($result['data']['access_token'])) {
    $accessToken = $result['data']['access_token'];
    $userId = $result['data']['user_id'];
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO broker_connections (user_id, broker_name, access_token, broker_user_id, created_at)
            VALUES (?, 'zerodha', ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                access_token = VALUES(access_token),
                broker_user_id = VALUES(broker_user_id),
                updated_at = NOW()
        ");
        
        $stmt->execute([$_SESSION['user_id'], $accessToken, $userId]);
        
        header('Location: ../../home.html?broker=zerodha&status=connected');
    } catch (Exception $e) {
        error_log('Database error: ' . $e->getMessage());
        header('Location: ../../home.html?error=database_error');
    }
} else {
    error_log('Zerodha token exchange failed: ' . $response);
    header('Location: ../../home.html?error=token_exchange_failed');
}
exit;
?>

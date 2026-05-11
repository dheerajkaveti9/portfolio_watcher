<?php
// ============================================
// FILE: api/angelone/callback.php
// Angel One Smart API OAuth Callback Handler
// Location: C:\xampp\htdocs\portfolio_watcher\api\angelone-callback.php
// ============================================

session_start();

// Include database connection
require_once '../db.php';
require_once '../config.php';

// Turn off error display
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

// Set header
header('Content-Type: text/html; charset=utf-8');

// Get configuration
$config = $BROKER_CONFIG['angelone'];
$ANGELONE_API_KEY = $config['api_key'];

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['logged_in'])) {
        // Redirect to login if not authenticated
        header('Location: ../../login.html?redirect=home.html');
        exit;
    }
    
    // Get data from POST request
    $input = json_decode(file_get_contents('php://input'), true);
    
    $clientCode = $input['clientCode'] ?? $_POST['clientCode'] ?? null;
    $password = $input['password'] ?? $_POST['password'] ?? null;
    $totp = $input['totp'] ?? $_POST['totp'] ?? null;
    
    if (!$clientCode || !$password || !$totp) {
        header('Location: ../../home.html?error=missing_credentials');
        exit;
    }
    
    // Call AngelOne Login API
    $url = 'https://apiconnect.angelbroking.com/rest/auth/angelbroking/user/v1/loginByPassword';
    $loginData = [
        'clientcode' => $clientCode,
        'password' => $password,
        'totp' => $totp
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-UserType: USER',
        'X-SourceID: WEB',
        'X-ClientLocalIP: 127.0.0.1',
        'X-ClientPublicIP: 127.0.0.1',
        'X-MACAddress: 00:00:00:00:00:00',
        'X-PrivateKey: ' . $ANGELONE_API_KEY
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($httpCode === 200 && isset($result['data']['jwtToken'])) {
        $jwtToken = $result['data']['jwtToken'];
        $refreshToken = $result['data']['refreshToken'] ?? '';
        
        // Store in database
        $stmt = $pdo->prepare("
            INSERT INTO broker_connections (user_id, broker_name, access_token, refresh_token, broker_user_id, created_at)
            VALUES (?, 'angelone', ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                access_token = VALUES(access_token),
                refresh_token = VALUES(refresh_token),
                broker_user_id = VALUES(broker_user_id),
                updated_at = NOW()
        ");
        
        $userId = $_SESSION['user_id'] ?? $_SESSION['logged_in'];
        $stmt->execute([$userId, $jwtToken, $refreshToken, $clientCode]);
        
        header('Location: ../../home.html?broker=angelone&status=connected');
    } else {
        error_log('AngelOne login failed: ' . $response);
        header('Location: ../../home.html?error=login_failed');
    }
    
} catch (Exception $e) {
    error_log('AngelOne callback error: ' . $e->getMessage());
    header('Location: ../../home.html?error=database_error');
}

exit;
?>
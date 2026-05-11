<?php
// Connect Trading App Handler
// Location: C:\xampp\htdocs\portfolio_watcher\api\connect-trading-app.php

session_start();

require_once '../config/database.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get POST data
$platformType = trim($_POST['platform_type'] ?? '');
$platformName = trim($_POST['platform_name'] ?? '');
$apiKey = trim($_POST['api_key'] ?? '');
$apiSecret = trim($_POST['api_secret'] ?? '');

// Validation
if (empty($platformType) || empty($platformName)) {
    echo json_encode(['success' => false, 'message' => 'Platform type and name are required']);
    exit;
}

// Validate platform type
$validPlatforms = ['kite', 'angelone', 'upstox', 'coin', 'manual'];
if (!in_array($platformType, $validPlatforms)) {
    echo json_encode(['success' => false, 'message' => 'Invalid platform type']);
    exit;
}

try {
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];
    
    // Check if user already has this connection
    $stmt = $conn->prepare("SELECT id FROM trading_connections WHERE user_id = ? AND platform_type = ? AND is_active = 1");
    $stmt->bind_param("is", $userId, $platformType);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'This trading app is already connected']);
        $stmt->close();
        $conn->close();
        exit;
    }
    $stmt->close();
    
    // Insert new connection
    $stmt = $conn->prepare("INSERT INTO trading_connections (user_id, platform_name, platform_type, api_key, api_secret, connected_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("issss", $userId, $platformName, $platformType, $apiKey, $apiSecret);
    
    if ($stmt->execute()) {
        $connectionId = $stmt->insert_id;
        
        // Log the connection in audit log
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $description = "Connected trading app: " . $platformName;
        $action = "TRADING_APP_CONNECTED";
        
        $stmtLog = $conn->prepare("INSERT INTO audit_log (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
        $stmtLog->bind_param("isss", $userId, $action, $description, $ip);
        $stmtLog->execute();
        $stmtLog->close();
        
        echo json_encode([
            'success' => true,
            'message' => $platformName . ' connected successfully!',
            'connection_id' => $connectionId
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to connect trading app']);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
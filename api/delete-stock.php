<?php
// Delete Stock from Portfolio
// Location: C:\xampp\htdocs\portfolio_watcher\api\delete-stock.php

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

ob_start();

session_start();

require_once '../config/database.php';

ob_clean();
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$userId = $_SESSION['user_id'];
$symbol = trim($_POST['symbol'] ?? '');

// Validation
if (empty($symbol)) {
    echo json_encode(['success' => false, 'message' => 'Symbol is required']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Get stock name for logging
    $stmt = $conn->prepare("SELECT name FROM user_stocks WHERE user_id = ? AND symbol = ?");
    $stmt->bind_param("is", $userId, $symbol);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Stock not found in portfolio']);
        exit;
    }
    
    $stock = $result->fetch_assoc();
    $stockName = $stock['name'];
    $stmt->close();
    
    // Delete the stock
    $stmt = $conn->prepare("DELETE FROM user_stocks WHERE user_id = ? AND symbol = ?");
    $stmt->bind_param("is", $userId, $symbol);
    $stmt->execute();
    $stmt->close();
    
    // Log the action
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $description = "Stock removed from portfolio: " . $stockName . " (" . $symbol . ")";
    $action = "STOCK_REMOVED";
    
    $stmtLog = $conn->prepare("INSERT INTO audit_log (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
    $stmtLog->bind_param("isss", $userId, $action, $description, $ip);
    $stmtLog->execute();
    $stmtLog->close();
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Stock removed from portfolio successfully'
    ]);
    
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
} catch (Error $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>








<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Include database configuration
require_once __DIR__ . '/../config/database.php';

// Get POST data
$symbol = $_POST['symbol'] ?? '';
$name = $_POST['name'] ?? '';
$exchange = $_POST['exchange'] ?? '';
$price = $_POST['price'] ?? 0;
$previousClose = $_POST['previous_close'] ?? 0;
$change = $_POST['change'] ?? 0;
$changePercent = $_POST['change_percent'] ?? 0;

// Validate required fields
if (empty($symbol) || empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];
    
    // Check if stock already exists for this user
    $checkStmt = $conn->prepare("SELECT id FROM user_stocks WHERE user_id = ? AND symbol = ?");
    $checkStmt->bind_param("is", $userId, $symbol);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $checkStmt->close();
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Stock already exists in your portfolio']);
        exit;
    }
    $checkStmt->close();
    
    // Insert new stock
    $stmt = $conn->prepare("
        INSERT INTO user_stocks 
        (user_id, symbol, name, exchange, price, previous_close, `change`, change_percent, last_updated) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->bind_param(
        "isssdddd",
        $userId,
        $symbol,
        $name,
        $exchange,
        $price,
        $previousClose,
        $change,
        $changePercent
    );
    
    if ($stmt->execute()) {
        $stockId = $stmt->insert_id;
        $stmt->close();
        $conn->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Stock added successfully',
            'stock_id' => $stockId
        ]);
    } else {
        $error = $stmt->error;
        $stmt->close();
        $conn->close();
        
        echo json_encode([
            'success' => false,
            'message' => 'Failed to add stock: ' . $error
        ]);
    }
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->close();
    }
    error_log("Save stock error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
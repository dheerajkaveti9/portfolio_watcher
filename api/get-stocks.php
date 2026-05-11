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

try {
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];
    
    // Fetch all stocks for the logged-in user
    $stmt = $conn->prepare("
        SELECT 
            id,
            symbol,
            name,
            exchange,
            price,
            previous_close,
            `change`,
            change_percent,
            last_updated
        FROM user_stocks 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $stocks = [];
    while ($row = $result->fetch_assoc()) {
        $stocks[] = [
            'id' => $row['id'],
            'symbol' => $row['symbol'],
            'name' => $row['name'],
            'exchange' => $row['exchange'],
            'price' => (float)$row['price'],
            'previous_close' => (float)$row['previous_close'],
            'change' => (float)$row['change'],
            'change_percent' => (float)$row['change_percent'],
            'last_updated' => $row['last_updated']
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'stocks' => $stocks,
        'count' => count($stocks)
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->close();
    }
    error_log("Get stocks error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>

<?php
// ============================================
// FILE: api/get-connected-brokers.php
// ============================================
header('Content-Type: application/json');
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT broker_name, created_at, broker_user_id
        FROM broker_connections 
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $connections = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    echo json_encode([
        'success' => true,
        'brokers' => $connections
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching connections: ' . $e->getMessage()
    ]);
}
?>

<?php
// User Logout Handler
// Location: C:\xampp\htdocs\portfolio_watcher\api\logout.php

// Start session
session_start();

// Include database configuration
require_once '../config/database.php';

// Set header for JSON response
header('Content-Type: application/json');

// Allow both GET and POST for logout
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    // Check if user is logged in
    if (isset($_SESSION['user_id']) && isset($_SESSION['session_token'])) {
        $userId = $_SESSION['user_id'];
        $sessionToken = $_SESSION['session_token'];
        
        // Get database connection
        $conn = getDBConnection();
        
        // Invalidate session in database
        $stmt = $conn->prepare("UPDATE user_sessions SET is_active = FALSE WHERE session_token = ?");
        $stmt->bind_param("s", $sessionToken);
        $stmt->execute();
        $stmt->close();
        
        // Log logout action
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $description = "User logged out";
        $action = "USER_LOGOUT";
        
        $stmtLog = $conn->prepare("INSERT INTO audit_log (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
        $stmtLog->bind_param("isss", $userId, $action, $description, $ip);
        $stmtLog->execute();
        $stmtLog->close();
        
        $conn->close();
    }
    
    // Clear all session data
    $_SESSION = array();
    
    // Destroy the session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time()-3600, '/');
    }
    
    // Destroy the session
    session_destroy();
    
    echo json_encode([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Logout error: ' . $e->getMessage()
    ]);
}
?>
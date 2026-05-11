<?php
// Get User Data Handler - Fixed Session
// Location: C:\xampp\htdocs\portfolio_watcher\api\get-user.php

// Configure session settings BEFORE starting session
ini_set('session.gc_maxlifetime', 86400);
ini_set('session.cookie_lifetime', 86400);

// Start session
session_start();

// Include database configuration
require_once '../config/database.php';

// Set header for JSON response
header('Content-Type: application/json');

// Debug: Log session data
error_log("Session Data: " . print_r($_SESSION, true));
error_log("Session ID: " . session_id());

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode([
        'success' => false,
        'message' => 'Not authenticated',
        'debug' => [
            'session_id' => session_id(),
            'has_user_id' => isset($_SESSION['user_id']),
            'has_logged_in' => isset($_SESSION['logged_in']),
            'logged_in_value' => $_SESSION['logged_in'] ?? 'not set'
        ]
    ]);
    exit;
}

try {
    $userId = $_SESSION['user_id'];
    
    // Get database connection
    $conn = getDBConnection();
    
    // Fetch user data
    $stmt = $conn->prepare("SELECT id, name, email, email_verified, created_at FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
        $stmt->close();
        $conn->close();
        exit;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // Return user data
    echo json_encode([
        'success' => true,
        'user' => [
            'user_id' => intval($user['id']),
            'full_name' => strval($user['name']),
            'email' => strval($user['email']),
            'email_verified' => boolval($user['email_verified']),
            'created_at' => $user['created_at']
        ]
    ]);
    
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
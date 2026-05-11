<?php
// User Login Handler - FIXED Session Persistence
// Location: C:\xampp\htdocs\portfolio_watcher\api\login.php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CRITICAL: Configure session BEFORE session_start()
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_lifetime', 86400); // 24 hours
ini_set('session.gc_maxlifetime', 86400);

// Start session
session_start();

// Include database configuration
require_once '../config/database.php';

// Set header for JSON response
header('Content-Type: application/json');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get POST data
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Validation
$errors = [];

// Validate username (can be email)
if (empty($username)) {
    $errors[] = 'Username is required';
}

// Validate password
if (empty($password)) {
    $errors[] = 'Password is required';
}

// If there are validation errors, return them
if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

try {
    // Get database connection
    $conn = getDBConnection();
    
    // Check if user exists (search by email)
    $stmt = $conn->prepare("SELECT id, name, email, password, email_verified FROM users WHERE email = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Log failed login attempt
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $description = "Failed login attempt for: " . $username;
        $action = "LOGIN_FAILED";
        
        $stmtLog = $conn->prepare("INSERT INTO audit_log (user_id, action, description, ip_address) VALUES (NULL, ?, ?, ?)");
        $stmtLog->bind_param("sss", $action, $description, $ip);
        $stmtLog->execute();
        $stmtLog->close();
        
        echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
        $stmt->close();
        $conn->close();
        exit;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        // Log failed login attempt
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $description = "Failed login attempt for: " . $username;
        $action = "LOGIN_FAILED";
        
        $stmtLog = $conn->prepare("INSERT INTO audit_log (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
        $stmtLog->bind_param("isss", $user['id'], $action, $description, $ip);
        $stmtLog->execute();
        $stmtLog->close();
        
        echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
        $conn->close();
        exit;
    }
    
    // Note: last_login column doesn't exist in the database, so we skip updating it
    
    // Generate session token
    $sessionToken = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    // Create session in database
    $stmtSession = $conn->prepare("INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)");
    $stmtSession->bind_param("issss", $user['id'], $sessionToken, $ip, $userAgent, $expiresAt);
    $stmtSession->execute();
    $stmtSession->close();
    
    // Log successful login
    $description = "User logged in successfully: " . $user['email'];
    $action = "USER_LOGIN";
    
    $stmtLog = $conn->prepare("INSERT INTO audit_log (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
    $stmtLog->bind_param("isss", $user['id'], $action, $description, $ip);
    $stmtLog->execute();
    $stmtLog->close();
    
    // CRITICAL FIX: Regenerate session ID to prevent session fixation
    session_regenerate_id(true);
    
    // CRITICAL FIX: Clear any existing session data first
    $_SESSION = array();
    
    // Store session data with explicit type casting
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['full_name'] = (string)$user['name'];
    $_SESSION['email'] = (string)$user['email'];
    $_SESSION['session_token'] = (string)$sessionToken;
    $_SESSION['logged_in'] = true; // Boolean true, not string
    $_SESSION['email_verified'] = (bool)$user['email_verified'];
    $_SESSION['login_time'] = time();
    
    // CRITICAL FIX: Force session write and close
    session_write_close();
    
    // CRITICAL FIX: Restart session to verify it was saved
    session_start();
    
    // Verify session was saved correctly
    $sessionSaved = isset($_SESSION['user_id']) && isset($_SESSION['logged_in']);
    
    // Prepare response
    $response = [
        'success' => true,
        'message' => 'Login successful!',
        'user' => [
            'user_id' => (int)$user['id'],
            'full_name' => (string)$user['name'],
            'email' => (string)$user['email'],
            'email_verified' => (bool)$user['email_verified']
        ],
        'redirect' => 'home.html',
        'debug' => [
            'session_id' => session_id(),
            'session_saved' => $sessionSaved,
            'user_id_set' => isset($_SESSION['user_id']),
            'logged_in_set' => isset($_SESSION['logged_in']),
            'logged_in_value' => $_SESSION['logged_in'] ?? 'not set'
        ]
    ];
    
    // Return success response
    echo json_encode($response);
    
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
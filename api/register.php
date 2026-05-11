<?php
// User Registration Handler
// Location: C:\xampp\htdocs\portfolio_watcher\api\register.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get POST data
$fullName = trim($_POST['fullname'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm-password'] ?? '';
$terms = isset($_POST['terms']);

// Validation
$errors = [];

if (empty($fullName)) {
    $errors[] = 'Full name is required';
} elseif (strlen($fullName) < 2) {
    $errors[] = 'Full name must be at least 2 characters';
} elseif (strlen($fullName) > 100) {
    $errors[] = 'Full name must be less than 100 characters';
}

if (empty($email)) {
    $errors[] = 'Email is required';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email format';
} elseif (strlen($email) > 255) {
    $errors[] = 'Email must be less than 255 characters';
}

if (empty($password)) {
    $errors[] = 'Password is required';
} elseif (strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters';
} elseif (!preg_match('/[A-Z]/', $password)) {
    $errors[] = 'Password must contain at least one uppercase letter';
} elseif (!preg_match('/[a-z]/', $password)) {
    $errors[] = 'Password must contain at least one lowercase letter';
} elseif (!preg_match('/[0-9]/', $password)) {
    $errors[] = 'Password must contain at least one number';
}

if ($password !== $confirmPassword) {
    $errors[] = 'Passwords do not match';
}

if (!$terms) {
    $errors[] = 'You must agree to the Terms and Conditions';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Check if email already exists - FIXED: Changed user_id to id
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        $stmt->close();
        $conn->close();
        exit;
    }
    $stmt->close();
    
    // Hash the password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new user - FIXED: Changed to use 'name' column and 'password' column
    $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $fullName, $email, $passwordHash);
    
    if ($stmt->execute()) {
        $userId = $stmt->insert_id;
        
        // Create default user preferences
        $stmtPref = $conn->prepare("INSERT INTO user_preferences (user_id, theme, notifications_enabled, currency, language) VALUES (?, 'dark', TRUE, 'USD', 'en')");
        $stmtPref->bind_param("i", $userId);
        $stmtPref->execute();
        $stmtPref->close();
        
        // Log registration in audit log
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $description = "New user registered: " . $email;
        $action = "USER_REGISTERED";
        
        $stmtLog = $conn->prepare("INSERT INTO audit_log (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
        $stmtLog->bind_param("isss", $userId, $action, $description, $ip);
        $stmtLog->execute();
        $stmtLog->close();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Registration successful! You can now login.',
            'user_id' => $userId
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
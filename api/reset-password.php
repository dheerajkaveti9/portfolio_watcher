<?php
// Reset Password
// Location: C:\xampp\htdocs\portfolio_watcher\api\reset-password.php

// Turn off error display to prevent HTML output before JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start output buffering to catch any accidental output
ob_start();

session_start();

require_once '../config/database.php';

// Clear any output before sending JSON
ob_clean();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    ob_end_flush();
    exit;
}

$email = trim($_POST['email'] ?? '');
$code = trim($_POST['code'] ?? '');
$newPassword = $_POST['newPassword'] ?? '';

// Validation
if (empty($email) || empty($code) || empty($newPassword)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Email, code, and new password are required']);
    ob_end_flush();
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    ob_end_flush();
    exit;
}

if (strlen($code) !== 6 || !ctype_digit($code)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Code must be 6 digits']);
    ob_end_flush();
    exit;
}

if (strlen($newPassword) < 8) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long']);
    ob_end_flush();
    exit;
}

try {
    $conn = getDBConnection();
    
    // Verify reset code is valid and verified
    // Note: MySQL stores BOOLEAN as TINYINT(1), so verified = 1 means TRUE
    $stmt = $conn->prepare("SELECT * FROM password_reset_codes WHERE email = ? AND reset_code = ? AND verified = 1 ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("ss", $email, $code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        ob_clean();
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid or unverified reset request. Please verify the code first.'
        ]);
        ob_end_flush();
        exit;
    }
    
    $resetData = $result->fetch_assoc();
    $stmt->close();
    
    // Check if expired
    if (strtotime($resetData['expires_at']) < time()) {
        // Delete expired code
        $stmtDelete = $conn->prepare("DELETE FROM password_reset_codes WHERE id = ?");
        $stmtDelete->bind_param("i", $resetData['id']);
        $stmtDelete->execute();
        $stmtDelete->close();
        
        $conn->close();
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Reset code has expired']);
        ob_end_flush();
        exit;
    }
    
    // Hash new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update user password
    $stmtUpdate = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmtUpdate->bind_param("ss", $hashedPassword, $email);
    $stmtUpdate->execute();
    
    if ($stmtUpdate->affected_rows === 0) {
        $stmtUpdate->close();
        $conn->close();
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to update password. User may not exist.']);
        ob_end_flush();
        exit;
    }
    $stmtUpdate->close();
    
    // Delete reset code
    $stmtDelete = $conn->prepare("DELETE FROM password_reset_codes WHERE id = ?");
    $stmtDelete->bind_param("i", $resetData['id']);
    $stmtDelete->execute();
    $stmtDelete->close();
    
    // Log the action (non-blocking - don't fail if logging fails)
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $description = "Password reset successfully for: " . $email;
        $action = "PASSWORD_RESET_COMPLETED";
        
        $stmtLog = $conn->prepare("INSERT INTO audit_log (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
        $stmtLog->bind_param("isss", $resetData['user_id'], $action, $description, $ip);
        $stmtLog->execute();
        $stmtLog->close();
    } catch (Exception $logError) {
        // Log error but don't fail the password reset
        error_log("Failed to log password reset: " . $logError->getMessage());
    }
    
    $conn->close();
    
    // End output buffering and send response
    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Password reset successfully! Redirecting to login...'
    ]);
    ob_end_flush();
    
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    ob_end_flush();
} catch (Error $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    ob_end_flush();
}
?>



<?php
// Verify Email Code
// Location: C:\xampp\htdocs\portfolio_watcher\api\verify-email.php

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
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$email = trim($_POST['email'] ?? '');
$code = trim($_POST['code'] ?? '');

// Validation
if (empty($email) || empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Email and verification code are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

if (strlen($code) !== 6 || !ctype_digit($code)) {
    echo json_encode(['success' => false, 'message' => 'Verification code must be 6 digits']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Check if user exists
    $stmt = $conn->prepare("SELECT id, email_verified FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'No account found with this email']);
        $stmt->close();
        $conn->close();
        exit;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // Check if already verified
    if ($user['email_verified']) {
        echo json_encode(['success' => false, 'message' => 'Email is already verified']);
        $conn->close();
        exit;
    }
    
    // Get verification code
    $stmtCode = $conn->prepare("SELECT id, verification_code, expires_at, attempts FROM email_verifications WHERE email = ? ORDER BY created_at DESC LIMIT 1");
    $stmtCode->bind_param("s", $email);
    $stmtCode->execute();
    $resultCode = $stmtCode->get_result();
    
    if ($resultCode->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'No verification code found. Please request a new one.']);
        $stmtCode->close();
        $conn->close();
        exit;
    }
    
    $verification = $resultCode->fetch_assoc();
    $stmtCode->close();
    
    // Check if code has expired
    if (strtotime($verification['expires_at']) < time()) {
        // Delete expired code
        $stmtDelete = $conn->prepare("DELETE FROM email_verifications WHERE id = ?");
        $stmtDelete->bind_param("i", $verification['id']);
        $stmtDelete->execute();
        $stmtDelete->close();
        
        echo json_encode(['success' => false, 'message' => 'Verification code has expired. Please request a new one.']);
        $conn->close();
        exit;
    }
    
    // Check attempts (max 5)
    if ($verification['attempts'] >= 5) {
        // Delete code after too many attempts
        $stmtDelete = $conn->prepare("DELETE FROM email_verifications WHERE id = ?");
        $stmtDelete->bind_param("i", $verification['id']);
        $stmtDelete->execute();
        $stmtDelete->close();
        
        echo json_encode(['success' => false, 'message' => 'Too many incorrect attempts. Please request a new code.']);
        $conn->close();
        exit;
    }
    
    // Verify code
    if ($verification['verification_code'] !== $code) {
        // Increment attempts
        $newAttempts = $verification['attempts'] + 1;
        $stmtUpdate = $conn->prepare("UPDATE email_verifications SET attempts = ? WHERE id = ?");
        $stmtUpdate->bind_param("ii", $newAttempts, $verification['id']);
        $stmtUpdate->execute();
        $stmtUpdate->close();
        
        $remaining = 5 - $newAttempts;
        echo json_encode([
            'success' => false, 
            'message' => "Incorrect verification code. $remaining attempts remaining."
        ]);
        $conn->close();
        exit;
    }
    
    // Code is correct - update user as verified
    $stmtVerify = $conn->prepare("UPDATE users SET email_verified = TRUE WHERE id = ?");
    $stmtVerify->bind_param("i", $user['id']);
    $stmtVerify->execute();
    $stmtVerify->close();
    
    // Delete verification code
    $stmtDelete = $conn->prepare("DELETE FROM email_verifications WHERE id = ?");
    $stmtDelete->bind_param("i", $verification['id']);
    $stmtDelete->execute();
    $stmtDelete->close();
    
    // Log verification success
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $description = "Email verified successfully: " . $email;
    $action = "EMAIL_VERIFIED";
    
    $stmtLog = $conn->prepare("INSERT INTO audit_log (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
    $stmtLog->bind_param("isss", $user['id'], $action, $description, $ip);
    $stmtLog->execute();
    $stmtLog->close();
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Email verified successfully! Redirecting to login...'
    ]);
    
} catch (Exception $e) {
    // Make sure we output clean JSON
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
} catch (Error $e) {
    // Catch fatal errors too
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
<?php
// Verify Password Reset Code
// Location: C:\xampp\htdocs\portfolio_watcher\api\verify-reset-code.php

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

// Validation
if (empty($email) || empty($code)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Email and code are required']);
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

try {
    $conn = getDBConnection();
    
    // Get reset code by email only (to check attempts even if code is wrong)
    $stmt = $conn->prepare("SELECT * FROM password_reset_codes WHERE email = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'No reset code found. Please request a new one.']);
        ob_end_flush();
        exit;
    }
    
    $resetData = $result->fetch_assoc();
    $stmt->close();
    
    // Check attempts first (before checking expiration or code)
    if ($resetData['attempts'] >= 5) {
        // Delete code after too many attempts
        $stmtDelete = $conn->prepare("DELETE FROM password_reset_codes WHERE id = ?");
        $stmtDelete->bind_param("i", $resetData['id']);
        $stmtDelete->execute();
        $stmtDelete->close();
        
        $conn->close();
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Too many incorrect attempts. Please request a new code.']);
        ob_end_flush();
        exit;
    }
    
    // Check if expired
    if (strtotime($resetData['expires_at']) < time()) {
        // Delete expired code
        $stmtDelete = $conn->prepare("DELETE FROM password_reset_codes WHERE id = ?");
        $stmtDelete->bind_param("i", $resetData['id']);
        $stmtDelete->execute();
        $stmtDelete->close();
        
        $conn->close();
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Reset code has expired. Please request a new one.']);
        ob_end_flush();
        exit;
    }
    
    // Check if code matches
    if ($resetData['reset_code'] !== $code) {
        // Increment attempts for wrong code
        $newAttempts = $resetData['attempts'] + 1;
        $stmtUpdate = $conn->prepare("UPDATE password_reset_codes SET attempts = ? WHERE id = ?");
        $stmtUpdate->bind_param("ii", $newAttempts, $resetData['id']);
        $stmtUpdate->execute();
        $stmtUpdate->close();
        
        $remaining = 5 - $newAttempts;
        $conn->close();
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => "Incorrect code. $remaining attempts remaining."
        ]);
        ob_end_flush();
        exit;
    }
    
    // Code is correct - mark as verified and reset attempts
    // Note: MySQL stores BOOLEAN as TINYINT(1), so use 1 for TRUE
    $stmtVerify = $conn->prepare("UPDATE password_reset_codes SET verified = 1, attempts = 0 WHERE id = ?");
    $stmtVerify->bind_param("i", $resetData['id']);
    $stmtVerify->execute();
    
    // Check if update was successful
    if ($stmtVerify->affected_rows === 0) {
        $stmtVerify->close();
        $conn->close();
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to verify code. Please try again.']);
        ob_end_flush();
        exit;
    }
    
    $stmtVerify->close();
    $conn->close();
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Code verified successfully! You can now create a new password.'
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


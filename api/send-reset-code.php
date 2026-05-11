<?php
// Send Password Reset Code
// Location: C:\xampp\htdocs\portfolio_watcher\api\send-reset-code.php

// Turn off error display to prevent HTML output before JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start output buffering to catch any accidental output
ob_start();

session_start();

require_once '../config/database.php';
require_once '../config/email_helper.php';

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

// Validation
if (empty($email)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Email is required']);
    ob_end_flush();
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    ob_end_flush();
    exit;
}

try {
    $conn = getDBConnection();
    
    // Check if user exists
    $stmt = $conn->prepare("SELECT id, email, name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Always return success (don't reveal if email exists)
    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'If an account exists with this email, you will receive a reset code.'
        ]);
        ob_end_flush();
        exit;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // Delete any existing reset codes for this email
    $stmtDelete = $conn->prepare("DELETE FROM password_reset_codes WHERE email = ?");
    $stmtDelete->bind_param("s", $email);
    $stmtDelete->execute();
    $stmtDelete->close();
    
    // Generate 6-digit reset code
    $resetCode = sprintf("%06d", mt_rand(0, 999999));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    
    // Insert new reset code
    // Note: MySQL stores BOOLEAN as TINYINT(1), so use 0 for FALSE
    $stmtInsert = $conn->prepare("INSERT INTO password_reset_codes (user_id, email, reset_code, expires_at, verified, attempts) VALUES (?, ?, ?, ?, 0, 0)");
    $stmtInsert->bind_param("isss", $user['id'], $email, $resetCode, $expiresAt);
    $stmtInsert->execute();
    $stmtInsert->close();
    
    // Send email
    $subject = "Password Reset Code - Portfolio Watcher";
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #1173d4 0%, #0d5aa7 100%); 
                     color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .code-box { background: white; padding: 20px; text-align: center; 
                       margin: 20px 0; border-radius: 8px; border: 2px dashed #1173d4; }
            .code { font-size: 36px; font-weight: bold; letter-spacing: 8px; 
                   color: #1173d4; font-family: 'Courier New', monospace; }
            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
            .warning { background: #fff3cd; border-left: 4px solid #ffc107; 
                      padding: 15px; margin: 20px 0; border-radius: 4px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🔒 Password Reset Request</h1>
            </div>
            <div class='content'>
                <p>Hello " . htmlspecialchars($user['name']) . ",</p>
                <p>We received a request to reset your password for your Portfolio Watcher account.</p>
                <p>Use the following code to complete your password reset:</p>
                
                <div class='code-box'>
                    <div class='code'>" . $resetCode . "</div>
                </div>
                
                <div class='warning'>
                    <strong>⚠️ Security Notice:</strong>
                    <ul style='margin: 10px 0; padding-left: 20px;'>
                        <li>This code will expire in <strong>15 minutes</strong></li>
                        <li>Never share this code with anyone</li>
                        <li>If you didn't request this, please ignore this email</li>
                    </ul>
                </div>
                
                <p>Best regards,<br><strong>Portfolio Watcher Team</strong></p>
            </div>
            <div class='footer'>
                <p>© 2025 Portfolio Watcher. All rights reserved.</p>
                <p>This is an automated email. Please do not reply.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Send email using the email helper
    $emailResult = sendEmail($email, $subject, $message, $user['name']);
    $mailSent = $emailResult['success'];
    
    // Log the action
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $description = "Password reset code requested for: " . $email;
    $action = "PASSWORD_RESET_REQUESTED";
    
    $stmtLog = $conn->prepare("INSERT INTO audit_log (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
    $stmtLog->bind_param("isss", $user['id'], $action, $description, $ip);
    $stmtLog->execute();
    $stmtLog->close();
    
    $conn->close();
    
    ob_clean();
    if (!$mailSent) {
        $errorMsg = $emailResult['error'] ?? 'Unknown error';
        // Log the error for debugging
        error_log("Email send failed for $email: " . $errorMsg);
        
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send email. Please try again later.',
            'debug' => [
                'code' => $resetCode, // For testing - remove in production
                'email_error' => $errorMsg
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Reset code sent to your email. Please check your inbox.',
            'debug' => [
                'code' => $resetCode // For testing - remove in production
            ]
        ]);
    }
    ob_end_flush();
    
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    error_log("Send reset code error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    ob_end_flush();
} catch (Error $e) {
    ob_clean();
    http_response_code(500);
    error_log("Send reset code error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    ob_end_flush();
}
?>



<?php
// Send Email Verification Code
// Location: C:\xampp\htdocs\portfolio_watcher\api\send-verification.php

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
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$email = trim($_POST['email'] ?? '');

// Validation
if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email is required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Check if user exists
    $stmt = $conn->prepare("SELECT id, name, email_verified FROM users WHERE email = ?");
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
    
    // Generate 6-digit verification code
    $verificationCode = sprintf("%06d", mt_rand(0, 999999));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    
    // Delete any existing verification codes for this email
    $stmtDelete = $conn->prepare("DELETE FROM email_verifications WHERE email = ?");
    $stmtDelete->bind_param("s", $email);
    $stmtDelete->execute();
    $stmtDelete->close();
    
    // Insert new verification code
    $stmtInsert = $conn->prepare("INSERT INTO email_verifications (user_id, email, verification_code, expires_at) VALUES (?, ?, ?, ?)");
    $stmtInsert->bind_param("isss", $user['id'], $email, $verificationCode, $expiresAt);
    $stmtInsert->execute();
    $stmtInsert->close();
    
    // Send email (using mail() function - configure SMTP in production)
    $subject = "Email Verification - Portfolio Watcher";
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
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>✉️ Email Verification</h1>
            </div>
            <div class='content'>
                <p>Hello " . htmlspecialchars($user['name']) . ",</p>
                <p>Thank you for registering with Portfolio Watcher!</p>
                <p>Please use the following code to verify your email address:</p>
                
                <div class='code-box'>
                    <div class='code'>" . $verificationCode . "</div>
                </div>
                
                <p><strong>⚠️ Important:</strong></p>
                <ul>
                    <li>This code will expire in <strong>15 minutes</strong></li>
                    <li>Never share this code with anyone</li>
                    <li>If you didn't create an account, please ignore this email</li>
                </ul>
                
                <p>Best regards,<br><strong>Portfolio Watcher Team</strong></p>
            </div>
            <div class='footer'>
                <p>© 2025 Portfolio Watcher. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Send email using the email helper (PHPMailer if available)
    $emailResult = sendEmail($email, $subject, $message, $user['name']);
    $mailSent = $emailResult['success'];
    
    // Log the action
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $description = "Verification code sent to: " . $email;
    $action = "VERIFICATION_CODE_SENT";
    
    $stmtLog = $conn->prepare("INSERT INTO audit_log (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
    $stmtLog->bind_param("isss", $user['id'], $action, $description, $ip);
    $stmtLog->execute();
    $stmtLog->close();
    
    $conn->close();
    
    // Build response message
    if (!$mailSent) {
        // Email failed to send - show error to user
        $errorMsg = $emailResult['error'] ?? 'Unknown error occurred';
        $responseMessage = 'Failed to send email: ' . $errorMsg;
        
        // Log the error
        error_log('Email sending failed: ' . $errorMsg);
        
        echo json_encode([
            'success' => false,
            'message' => $responseMessage,
            'debug' => [
                'mail_sent' => false,
                'code' => $verificationCode, // Still show code in console for testing
                'email_error' => $errorMsg
            ]
        ]);
    } else {
        // Email sent successfully
        echo json_encode([
            'success' => true,
            'message' => 'Verification code sent to your email. Please check your inbox (and spam folder).',
            'debug' => [
                'mail_sent' => true,
                'code' => $verificationCode, // Remove this in production!
                'email_error' => null
            ]
        ]);
    }
    
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
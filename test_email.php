<?php
// Email Test Script
// Run this to test your email configuration

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/email.php';
require_once 'config/email_helper.php';

$emailConfig = require 'config/email.php';

echo "<h2>Email Configuration Test</h2>";
echo "<hr>";

// Check if credentials are set
echo "<h3>1. Configuration Check:</h3>";
if (empty($emailConfig['smtp_username'])) {
    echo "❌ <strong>smtp_username is empty</strong><br>";
    echo "   Please set your Gmail address in config/email.php<br><br>";
} else {
    echo "✅ smtp_username: " . htmlspecialchars($emailConfig['smtp_username']) . "<br>";
}

if (empty($emailConfig['smtp_password'])) {
    echo "❌ <strong>smtp_password is empty</strong><br>";
    echo "   Please set your Gmail App Password in config/email.php<br>";
    echo "   Get it from: <a href='https://myaccount.google.com/apppasswords' target='_blank'>https://myaccount.google.com/apppasswords</a><br><br>";
} else {
    echo "✅ smtp_password: " . str_repeat('*', strlen($emailConfig['smtp_password'])) . " (" . strlen($emailConfig['smtp_password']) . " chars)<br>";
}

echo "<hr>";

// Check PHPMailer
echo "<h3>2. PHPMailer Check:</h3>";
$phpmailerPath = __DIR__ . '/vendor/phpmailer/src/PHPMailer.php';
if (file_exists($phpmailerPath)) {
    echo "✅ PHPMailer is installed<br>";
} else {
    echo "❌ PHPMailer is NOT installed<br>";
    echo "   Location: " . htmlspecialchars($phpmailerPath) . "<br><br>";
}

echo "<hr>";

// Test email sending
echo "<h3>3. Test Email Sending:</h3>";

if (empty($emailConfig['smtp_username']) || empty($emailConfig['smtp_password'])) {
    echo "⚠️ Cannot test - email credentials not configured<br>";
} else {
    $testEmail = $emailConfig['smtp_username']; // Send to yourself
    $subject = "Test Email - Portfolio Watcher";
    $message = "<h3>Test Email</h3><p>If you received this, your email configuration is working!</p>";
    
    echo "Sending test email to: <strong>" . htmlspecialchars($testEmail) . "</strong><br>";
    echo "Please wait...<br><br>";
    
    $result = sendEmail($testEmail, $subject, $message, 'Test User');
    
    if ($result['success']) {
        echo "✅ <strong style='color: green;'>Email sent successfully!</strong><br>";
        echo "   Check your inbox at: " . htmlspecialchars($testEmail) . "<br>";
        echo "   Also check: Spam, Promotions, and Social tabs<br>";
    } else {
        echo "❌ <strong style='color: red;'>Email failed to send</strong><br>";
        echo "   Error: " . htmlspecialchars($result['error'] ?? 'Unknown error') . "<br>";
        echo "<br>";
        echo "<strong>Common issues:</strong><br>";
        echo "1. Gmail App Password not set correctly<br>";
        echo "2. 2-Step Verification not enabled on Gmail<br>";
        echo "3. 'Less secure app access' needs to be enabled (old accounts)<br>";
        echo "4. Wrong SMTP settings<br>";
    }
}

echo "<hr>";
echo "<p><a href='verification.html'>← Back to Verification Page</a></p>";
?>








<?php
// Angel One Smart API OAuth Callback Handler
// Location: C:\xampp\htdocs\portfolio_watcher\api\angelone-callback.php

session_start();

require_once '../config/database.php';

// Turn off error display
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

// This is the URL that Angel One will redirect to after user authorization
// You need to register this URL in Smart API: http://localhost/portfolio_watcher/api/angelone-callback.php

header('Content-Type: text/html; charset=utf-8');

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
        // Redirect to login if not authenticated
        header('Location: ../login.html?redirect=home.html');
        exit;
    }

    // Check for authorization code
    if (!isset($_GET['code'])) {
        // No authorization code - might be an error
        $error = $_GET['error'] ?? 'Unknown error';
        $errorDescription = $_GET['error_description'] ?? '';
        
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Angel One Connection Failed</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
                .error-box { background: white; padding: 30px; border-radius: 8px; max-width: 500px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .error { color: #e74c3c; font-size: 24px; margin-bottom: 10px; }
                .message { color: #666; margin-bottom: 20px; }
                .button { background: #1173d4; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; }
            </style>
        </head>
        <body>
            <div class='error-box'>
                <div class='error'>❌ Connection Failed</div>
                <div class='message'>$error</div>
                " . (!empty($errorDescription) ? "<div class='message' style='font-size: 14px;'>$errorDescription</div>" : "") . "
                <a href='../home.html' class='button'>Go Back to Home</a>
            </div>
        </body>
        </html>";
        exit;
    }

    $authorizationCode = $_GET['code'];
    $state = $_GET['state'] ?? '';

    // Get database connection
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];

    // Exchange authorization code for access token
    // You'll need to implement this based on Angel One Smart API documentation
    // For now, we'll store the authorization code and redirect
    
    // Store the authorization code temporarily
    $_SESSION['angelone_auth_code'] = $authorizationCode;
    $_SESSION['angelone_state'] = $state;

    // Close output buffer before redirect
    ob_clean();

    // Redirect to a page that will handle token exchange
    header('Location: ../home.html?angelone=processing');
    exit;

} catch (Exception $e) {
    ob_clean();
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Error</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
            .error-box { background: white; padding: 30px; border-radius: 8px; max-width: 500px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .error { color: #e74c3c; }
        </style>
    </head>
    <body>
        <div class='error-box'>
            <div class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</div>
            <a href='../home.html'>Go Back</a>
        </div>
    </body>
    </html>";
}
?>








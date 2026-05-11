<?php
// Test Session Configuration
// Location: C:\xampp\htdocs\portfolio_watcher\api\test-session.php

session_start();

header('Content-Type: application/json');

// Set a test value
$_SESSION['test'] = 'Session is working!';
$_SESSION['timestamp'] = time();

// Get session save path
$savePath = session_save_path();
if (empty($savePath)) {
    $savePath = sys_get_temp_dir();
}

// Check if directory is writable
$isWritable = is_writable($savePath);

echo json_encode([
    'php_version' => PHP_VERSION,
    'session_id' => session_id(),
    'session_status' => session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive',
    'session_save_path' => $savePath,
    'save_path_writable' => $isWritable,
    'save_path_exists' => file_exists($savePath),
    'session_data' => $_SESSION,
    'cookies' => $_COOKIE,
    'session_name' => session_name(),
    'session_module' => ini_get('session.save_handler')
], JSON_PRETTY_PRINT);
?>
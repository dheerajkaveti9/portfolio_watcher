<?php
// Session Debug Tool
// Location: C:\xampp\htdocs\portfolio_watcher\api\check-session.php

session_start();

header('Content-Type: application/json');

echo json_encode([
    'session_id' => session_id(),
    'session_data' => $_SESSION,
    'cookies' => $_COOKIE,
    'session_status' => session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive'
], JSON_PRETTY_PRINT);
?>
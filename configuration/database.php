<?php
// Database Configuration File
// Location: C:\xampp\htdocs\portfolio_watcher\config\database.php

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');  // Default XAMPP password is empty, change if you set a password
define('DB_NAME', 'portfolio_watcher');

// Create connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        // Don't output directly - throw exception instead
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    // Set charset to utf8mb4 for proper character support
    $conn->set_charset("utf8mb4");
    
    return $conn;
}

// Test connection function
function testConnection() {
    $conn = getDBConnection();
    if ($conn) {
        echo "Database connected successfully!";
        $conn->close();
        return true;
    }
    return false;
}
?>
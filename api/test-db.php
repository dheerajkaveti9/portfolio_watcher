<?php
require_once 'config.php';

echo "<h2>Database Connection Test</h2>";

$host = env('DB_HOST', 'localhost');
$dbname = env('DB_NAME', 'portfolio_watcher');
$username = env('DB_USER', 'root');
$password = env('DB_PASSWORD', '');

echo "Host: $host<br>";
echo "Database: $dbname<br>";
echo "Username: $username<br>";
echo "Password: " . (empty($password) ? '(empty)' : '***') . "<br><br>";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );
    echo "✅ <strong style='color:green'>Database connected successfully!</strong>";
} catch (PDOException $e) {
    echo "❌ <strong style='color:red'>Connection failed: " . $e->getMessage() . "</strong>";
}
?>
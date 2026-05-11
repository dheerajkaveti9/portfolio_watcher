<?php
// ============================================
// FILE: api/test_env.php
// Test if .env file is loading correctly
// Location: C:\xampp\htdocs\portfolio_watcher\api\test_env.php
// ============================================

require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>Environment Variables Test</h2>";
echo "<hr>";

echo "<h3>Current Directory:</h3>";
echo "<p>" . __DIR__ . "</p>";

echo "<h3>.env File Locations Checked:</h3>";
echo "<ul>";
echo "<li>" . __DIR__ . '/../project_backend/.env' . " - " . (file_exists(__DIR__ . '/../project_backend/.env') ? "✅ EXISTS" : "❌ NOT FOUND") . "</li>";
echo "<li>" . __DIR__ . '/../.env' . " - " . (file_exists(__DIR__ . '/../.env') ? "✅ EXISTS" : "❌ NOT FOUND") . "</li>";
echo "</ul>";

echo "<h3>Environment Variables:</h3>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr><th>Variable</th><th>Value</th></tr>";

$keys = [
    'DB_HOST',
    'DB_USER',
    'DB_NAME',
    'STOCK_API_KEY',
    'BASE_URL',
    'ZERODHA_API_KEY',
    'ANGELONE_API_KEY',
    'UPSTOX_API_KEY',
    'EMAIL_USER'
];

foreach ($keys as $key) {
    $value = env($key);
    $displayValue = $value ? substr($value, 0, 20) . (strlen($value) > 20 ? '...' : '') : '<span style="color: red;">NOT SET</span>';
    echo "<tr><td><strong>$key</strong></td><td>$displayValue</td></tr>";
}

echo "</table>";

echo "<h3>Broker Config Test:</h3>";
echo "<pre>";
print_r($BROKER_CONFIG);
echo "</pre>";

echo "<hr>";
echo "<p><a href='../home.html'>Back to Home</a></p>";
?>
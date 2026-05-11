<?php
// Simple test file
echo "<h1>AngelOne Test</h1>";
echo "<p>If you can see this, the file path is working!</p>";
echo "<p>Current file: " . __FILE__ . "</p>";
echo "<p>Directory: " . __DIR__ . "</p>";

echo "<h2>Config File Check:</h2>";
$configPath = __DIR__ . '/../config.php';
echo "<p>Looking for config at: " . $configPath . "</p>";
echo "<p>Config exists: " . (file_exists($configPath) ? "YES ✅" : "NO ❌") . "</p>";

if (file_exists($configPath)) {
    try {
        require_once $configPath;
        echo "<p>Config loaded successfully! ✅</p>";
        echo "<p>AngelOne API Key: " . (env('ANGELONE_API_KEY') ?: 'NOT SET') . "</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>Config error: " . $e->getMessage() . " ❌</p>";
    }
} else {
    echo "<p style='color: red;'>Config file not found! ❌</p>";
}
?>
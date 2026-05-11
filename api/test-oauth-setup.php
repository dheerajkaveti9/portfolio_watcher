<?php
// ============================================
// FILE: api/test-oauth-setup.php
// Diagnostic tool to check OAuth setup
// ============================================

session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>OAuth Setup Diagnostic</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        h2 {
            color: #333;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
        }
        h3 {
            color: #555;
            margin-top: 30px;
        }
        .pass {
            color: #4CAF50;
            font-weight: bold;
        }
        .fail {
            color: #f44336;
            font-weight: bold;
        }
        .info {
            background: #e3f2fd;
            padding: 15px;
            border-left: 4px solid #2196F3;
            margin: 10px 0;
        }
        .section {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        td:first-child {
            font-weight: bold;
            width: 40%;
        }
    </style>
</head>
<body>
    <h2>🔍 OAuth Setup Diagnostic Tool</h2>

<?php
// Test 1: Config File
echo '<div class="section">';
echo '<h3>1. Configuration File Test</h3>';

$configExists = file_exists(__DIR__ . '/config.php');
if ($configExists) {
    echo '<span class="pass">✅ config.php exists</span><br>';
    require_once 'config.php';
    echo '<span class="pass">✅ config.php loaded successfully</span><br>';
} else {
    echo '<span class="fail">❌ config.php NOT FOUND</span><br>';
    echo '<div class="info">Create api/config.php from the "PHP Environment Config Loader" artifact</div>';
}
echo '</div>';

// Test 2: Environment Variables
if ($configExists) {
    echo '<div class="section">';
    echo '<h3>2. Environment Variables (.env)</h3>';
    echo '<table>';
    
    $envVars = [
        'BASE_URL' => env('BASE_URL'),
        'ZERODHA_API_KEY' => env('ZERODHA_API_KEY'),
        'ZERODHA_API_SECRET' => env('ZERODHA_API_SECRET'),
        'UPSTOX_API_KEY' => env('UPSTOX_API_KEY'),
        'UPSTOX_API_SECRET' => env('UPSTOX_API_SECRET'),
        'ANGELONE_API_KEY' => env('ANGELONE_API_KEY')
    ];
    
    foreach ($envVars as $key => $value) {
        echo '<tr><td>' . $key . '</td><td>';
        if (empty($value) || strpos($value, 'your_') !== false) {
            echo '<span class="fail">❌ NOT SET or placeholder</span>';
        } else {
            // Mask the value for security
            $masked = substr($value, 0, 4) . '****' . substr($value, -4);
            echo '<span class="pass">✅ SET (' . $masked . ')</span>';
        }
        echo '</td></tr>';
    }
    
    echo '</table>';
    echo '<div class="info">⚠️ If any show "NOT SET", update your .env file with real API credentials</div>';
    echo '</div>';
}

// Test 3: Database Connection
echo '<div class="section">';
echo '<h3>3. Database Connection</h3>';

$dbExists = file_exists(__DIR__ . '/db.php');
if ($dbExists) {
    echo '<span class="pass">✅ db.php exists</span><br>';
    
    try {
        require_once 'db.php';
        echo '<span class="pass">✅ Database connection successful</span><br>';
        
        // Check if broker_connections table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'broker_connections'");
        if ($stmt->rowCount() > 0) {
            echo '<span class="pass">✅ broker_connections table exists</span><br>';
            
            // Check table structure
            $stmt = $pdo->query("DESCRIBE broker_connections");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo '<span class="pass">✅ Table has ' . count($columns) . ' columns</span><br>';
        } else {
            echo '<span class="fail">❌ broker_connections table NOT FOUND</span><br>';
            echo '<div class="info">Run the SQL schema from "Complete Database Schema" artifact in phpMyAdmin</div>';
        }
    } catch (Exception $e) {
        echo '<span class="fail">❌ Database error: ' . $e->getMessage() . '</span><br>';
    }
} else {
    echo '<span class="fail">❌ db.php NOT FOUND</span><br>';
}
echo '</div>';

// Test 4: Session / Authentication
echo '<div class="section">';
echo '<h3>4. Session & Authentication</h3>';

if (isset($_SESSION['user_id'])) {
    echo '<span class="pass">✅ User logged in (ID: ' . $_SESSION['user_id'] . ')</span><br>';
} else {
    echo '<span class="fail">❌ No active session</span><br>';
    echo '<div class="info">You need to login to your app before testing OAuth. Go to your login page and login first.</div>';
}
echo '</div>';

// Test 5: File Structure
echo '<div class="section">';
echo '<h3>5. Broker API Files</h3>';

$requiredFiles = [
    'zerodha/login-url.php',
    'zerodha/callback.php',
    'upstox/login-url.php',
    'upstox/callback.php',
    'angelone/login-url.php',
    'angelone/callback.php',
    'get-connected-brokers.php'
];

echo '<table>';
foreach ($requiredFiles as $file) {
    echo '<tr><td>' . $file . '</td><td>';
    if (file_exists(__DIR__ . '/' . $file)) {
        echo '<span class="pass">✅ Exists</span>';
    } else {
        echo '<span class="fail">❌ MISSING</span>';
    }
    echo '</td></tr>';
}
echo '</table>';
echo '</div>';

// Test 6: Test API Endpoint
if ($configExists && file_exists(__DIR__ . '/zerodha/login-url.php')) {
    echo '<div class="section">';
    echo '<h3>6. API Endpoint Test</h3>';
    
    // Simulate a request to login-url.php
    $testUrl = env('BASE_URL') . '/api/zerodha/login-url.php';
    echo 'Testing: <code>' . $testUrl . '</code><br><br>';
    
    $ch = curl_init($testUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo '<span class="pass">✅ Endpoint accessible (HTTP 200)</span><br>';
        $json = json_decode($response, true);
        if ($json && isset($json['success'])) {
            if ($json['success']) {
                echo '<span class="pass">✅ Returns valid JSON response</span><br>';
                echo '<span class="pass">✅ Login URL generated successfully</span><br>';
            } else {
                echo '<span class="fail">❌ API returned error: ' . ($json['message'] ?? 'Unknown') . '</span><br>';
            }
        }
    } else {
        echo '<span class="fail">❌ Endpoint not accessible (HTTP ' . $httpCode . ')</span><br>';
    }
    echo '</div>';
}

// Final Summary
echo '<div class="section">';
echo '<h3>📊 Summary</h3>';

$issues = [];

if (!$configExists) $issues[] = 'config.php missing';
if (empty(env('ZERODHA_API_KEY')) || strpos(env('ZERODHA_API_KEY'), 'your_') !== false) {
    $issues[] = 'API keys not configured';
}
if (!isset($pdo)) $issues[] = 'Database connection failed';
if (isset($pdo)) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'broker_connections'");
        if ($stmt->rowCount() == 0) $issues[] = 'broker_connections table missing';
    } catch (Exception $e) {}
}
if (!isset($_SESSION['user_id'])) $issues[] = 'Not logged in';

if (empty($issues)) {
    echo '<h2 style="color: #4CAF50;">🎉 All checks passed! OAuth flow is ready to test!</h2>';
    echo '<div class="info">';
    echo '<strong>Next Steps:</strong><br>';
    echo '1. Make sure redirect URLs are registered in broker portals<br>';
    echo '2. Go to home.html and click a broker card<br>';
    echo '3. Complete OAuth flow on broker\'s site<br>';
    echo '4. You should be redirected back with "Connected" status';
    echo '</div>';
} else {
    echo '<h2 style="color: #f44336;">⚠️ Issues Found (' . count($issues) . '):</h2>';
    echo '<ul>';
    foreach ($issues as $issue) {
        echo '<li style="color: #f44336;">' . $issue . '</li>';
    }
    echo '</ul>';
    echo '<div class="info">';
    echo '<strong>Fix these issues first, then re-run this diagnostic.</strong>';
    echo '</div>';
}
echo '</div>';

?>

</body>
</html>
<?php
// Cookie Test Page
// Location: C:\xampp\htdocs\portfolio_watcher\cookie-test.php

// Set cookie parameters
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

// Set test data if not exists
if (!isset($_SESSION['test_counter'])) {
    $_SESSION['test_counter'] = 0;
}
$_SESSION['test_counter']++;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cookie Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .test-box {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        h1 { color: #333; }
        .success { color: #4caf50; font-weight: bold; }
        .error { color: #f44336; font-weight: bold; }
        .info { background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #1173d4; color: white; }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #1173d4;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px;
        }
        .btn:hover { background: #0d5aa7; }
    </style>
</head>
<body>
    <div class="test-box">
        <h1>🍪 Cookie & Session Test</h1>
        
        <div class="info">
            <strong>Test Purpose:</strong> Verify that cookies work across different pages
        </div>

        <h2>Test Results:</h2>
        
        <table>
            <tr>
                <th>Test</th>
                <th>Status</th>
                <th>Value</th>
            </tr>
            <tr>
                <td>Session ID</td>
                <td class="<?php echo session_id() ? 'success' : 'error'; ?>">
                    <?php echo session_id() ? '✅ Found' : '❌ Not Found'; ?>
                </td>
                <td><?php echo session_id(); ?></td>
            </tr>
            <tr>
                <td>PHPSESSID Cookie</td>
                <td class="<?php echo isset($_COOKIE['PHPSESSID']) ? 'success' : 'error'; ?>">
                    <?php echo isset($_COOKIE['PHPSESSID']) ? '✅ Found' : '❌ Not Found'; ?>
                </td>
                <td><?php echo $_COOKIE['PHPSESSID'] ?? 'No cookie'; ?></td>
            </tr>
            <tr>
                <td>Session Counter</td>
                <td class="success">✅ Active</td>
                <td><?php echo $_SESSION['test_counter']; ?> (refreshes increment this)</td>
            </tr>
            <tr>
                <td>Cookie Path</td>
                <td class="success">✅ Set</td>
                <td><?php echo session_get_cookie_params()['path']; ?></td>
            </tr>
        </table>

        <h2>All Cookies:</h2>
        <table>
            <tr><th>Cookie Name</th><th>Cookie Value</th></tr>
            <?php if (empty($_COOKIE)): ?>
                <tr><td colspan="2" style="text-align: center; color: #999;">No cookies found</td></tr>
            <?php else: ?>
                <?php foreach ($_COOKIE as $name => $value): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($name); ?></td>
                        <td><?php echo htmlspecialchars($value); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>

        <h2>Session Data:</h2>
        <table>
            <tr><th>Session Key</th><th>Session Value</th></tr>
            <?php if (empty($_SESSION)): ?>
                <tr><td colspan="2" style="text-align: center; color: #999;">No session data</td></tr>
            <?php else: ?>
                <?php foreach ($_SESSION as $key => $value): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($key); ?></td>
                        <td><?php echo htmlspecialchars(is_array($value) ? json_encode($value) : $value); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>

        <div style="margin-top: 30px;">
            <a href="?" class="btn">🔄 Refresh (increment counter)</a>
            <a href="login.html" class="btn">← Back to Login</a>
        </div>

        <div class="info" style="margin-top: 20px;">
            <strong>Instructions:</strong>
            <ol>
                <li>Click "Refresh" - the counter should increment (proves sessions work)</li>
                <li>If counter increments each time, sessions are working properly</li>
                <li>Now try logging in and check if home.html loads properly</li>
            </ol>
        </div>
    </div>
</body>
</html>
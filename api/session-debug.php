<?php
// Session Debug Tool - Complete Diagnostics
// Location: C:\xampp\htdocs\portfolio_watcher\session-debug.php

// Configure session
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_lifetime', 86400);
ini_set('session.gc_maxlifetime', 86400);

session_start();

// Get session save path
$savePath = session_save_path();
if (empty($savePath)) {
    $savePath = sys_get_temp_dir();
}

// Set a test value
$_SESSION['test_value'] = 'Session is working!';
$_SESSION['timestamp'] = date('Y-m-d H:i:s');

// Try to find session file
$sessionFile = $savePath . '/sess_' . session_id();
$sessionFileExists = file_exists($sessionFile);
$sessionFileContent = $sessionFileExists ? file_get_contents($sessionFile) : 'File not found';

// Prepare diagnostics
$diagnostics = [
    'PHP Version' => PHP_VERSION,
    'Session ID' => session_id(),
    'Session Status' => session_status() === PHP_SESSION_ACTIVE ? '✅ Active' : '❌ Inactive',
    'Session Name' => session_name(),
    'Session Save Handler' => ini_get('session.save_handler'),
    'Session Save Path' => $savePath,
    'Save Path Exists' => file_exists($savePath) ? '✅ Yes' : '❌ No',
    'Save Path Writable' => is_writable($savePath) ? '✅ Yes' : '❌ No',
    'Save Path Permissions' => file_exists($savePath) ? substr(sprintf('%o', fileperms($savePath)), -4) : 'N/A',
    'Session File Path' => $sessionFile,
    'Session File Exists' => $sessionFileExists ? '✅ Yes' : '❌ No',
    'Session File Writable' => $sessionFileExists && is_writable($sessionFile) ? '✅ Yes' : ($sessionFileExists ? '❌ No' : 'N/A'),
    'Cookie Lifetime' => ini_get('session.cookie_lifetime'),
    'GC Maxlifetime' => ini_get('session.gc_maxlifetime'),
    'Use Cookies' => ini_get('session.use_cookies') ? '✅ Yes' : '❌ No',
    'Use Only Cookies' => ini_get('session.use_only_cookies') ? '✅ Yes' : '❌ No',
    'Cookie HTTPOnly' => ini_get('session.cookie_httponly') ? '✅ Yes' : '❌ No',
    'Cookie Secure' => ini_get('session.cookie_secure') ? '✅ Yes' : '❌ No',
    'Cookie SameSite' => ini_get('session.cookie_samesite') ?: 'Not set',
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Debug - Portfolio Watcher</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9ff;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }
        .section h2 {
            color: #333;
            margin-bottom: 15px;
            font-size: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        th {
            background: #667eea;
            color: white;
            font-weight: 600;
        }
        tr:hover {
            background: #f0f0f0;
        }
        .status-ok { color: #4caf50; font-weight: bold; }
        .status-error { color: #f44336; font-weight: bold; }
        .code-block {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 20px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #5568d3;
        }
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            color: #856404;
        }
        .success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            color: #155724;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Session Debug Tool</h1>
        <p class="subtitle">Portfolio Watcher - Comprehensive Session Diagnostics</p>

        <?php if (!$sessionFileExists): ?>
            <div class="warning">
                <strong>⚠️ Warning:</strong> Session file does not exist! This means sessions are not being saved to disk.
            </div>
        <?php else: ?>
            <div class="success">
                <strong>✅ Good:</strong> Session file exists and is being written.
            </div>
        <?php endif; ?>

        <?php if (!is_writable($savePath)): ?>
            <div class="warning">
                <strong>⚠️ Critical:</strong> Session save path is not writable! Sessions cannot be saved.
                <br><strong>Fix:</strong> Run as administrator: <code>icacls "<?php echo $savePath; ?>" /grant Users:F</code>
            </div>
        <?php endif; ?>

        <div class="section">
            <h2>📊 Session Configuration</h2>
            <table>
                <?php foreach ($diagnostics as $key => $value): ?>
                    <tr>
                        <th><?php echo htmlspecialchars($key); ?></th>
                        <td><?php echo htmlspecialchars($value); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="section">
            <h2>💾 Session Data</h2>
            <table>
                <tr><th>Key</th><th>Value</th></tr>
                <?php if (empty($_SESSION)): ?>
                    <tr><td colspan="2" style="text-align: center; color: #999;">No session data</td></tr>
                <?php else: ?>
                    <?php foreach ($_SESSION as $key => $value): ?>
                        <tr>
                            <th><?php echo htmlspecialchars($key); ?></th>
                            <td><?php echo htmlspecialchars(is_array($value) ? json_encode($value) : $value); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </table>
        </div>

        <div class="section">
            <h2>📁 Session File Content (Raw)</h2>
            <div class="code-block"><?php echo htmlspecialchars($sessionFileContent); ?></div>
        </div>

        <div class="section">
            <h2>🍪 Cookies Sent to Browser</h2>
            <table>
                <?php if (empty($_COOKIE)): ?>
                    <tr><td colspan="2" style="text-align: center; color: #999;">No cookies</td></tr>
                <?php else: ?>
                    <?php foreach ($_COOKIE as $key => $value): ?>
                        <tr>
                            <th><?php echo htmlspecialchars($key); ?></th>
                            <td><?php echo htmlspecialchars($value); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </table>
        </div>

        <a href="login.html" class="btn">← Back to Login</a>
        <a href="?" class="btn" onclick="location.reload(); return false;">🔄 Refresh</a>
    </div>
</body>
</html>
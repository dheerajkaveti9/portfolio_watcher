<?php
// ============================================
// FILE: api/config.php
// Load environment variables from .env file
// ============================================

class Config {
    private static $env = [];
    
    public static function load($path = '../.env') {
        // Normalize path separators
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        
        if (!file_exists($path)) {
            throw new Exception('.env file not found at: ' . realpath(dirname($path)) . DIRECTORY_SEPARATOR . basename($path));
        }
        
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parse KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                $value = trim($value, '"\'');
                
                self::$env[$key] = $value;
                
                // Also set as environment variable
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
    
    public static function get($key, $default = null) {
        return self::$env[$key] ?? getenv($key) ?? $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }
}

// Load .env file automatically
// Normalize paths to use forward slashes for cross-platform compatibility
$baseDir = str_replace('\\', '/', __DIR__);
$envPath1 = $baseDir . '/../project_backend/.env';
$envPath2 = $baseDir . '/../.env';

// Convert back to system-appropriate path separators
$envPath1 = str_replace('/', DIRECTORY_SEPARATOR, $envPath1);
$envPath2 = str_replace('/', DIRECTORY_SEPARATOR, $envPath2);

if (file_exists($envPath1)) {
    Config::load($envPath1);
} elseif (file_exists($envPath2)) {
    Config::load($envPath2);
} else {
    // Debug output
    $debugInfo = "Config.php: .env file not found!\n";
    $debugInfo .= "Current directory: " . __DIR__ . "\n";
    $debugInfo .= "Checked paths:\n";
    $debugInfo .= "  1. " . $envPath1 . " - " . (file_exists($envPath1) ? "EXISTS" : "NOT FOUND") . "\n";
    $debugInfo .= "  2. " . $envPath2 . " - " . (file_exists($envPath2) ? "EXISTS" : "NOT FOUND") . "\n";
    
    error_log($debugInfo);
    throw new Exception('.env file not found. Check error log for details.');
}

// Helper function for easy access
function env($key, $default = null) {
    return Config::get($key, $default);
}

// Broker configuration using environment variables
$BROKER_CONFIG = [
    'zerodha' => [
        'api_key' => env('ZERODHA_API_KEY'),
        'api_secret' => env('ZERODHA_API_SECRET'),
        'redirect_uri' => env('BASE_URL') . '/api/zerodha/callback.php',
        'login_url' => 'https://kite.zerodha.com/connect/login',
        'token_url' => 'https://api.kite.trade/session/token'
    ],
    
    'angelone' => [
        'api_key' => env('ANGELONE_API_KEY'),
        'client_code' => env('ANGELONE_CLIENT_CODE'),
        'password' => env('ANGELONE_PASSWORD'),
        'totp_secret' => env('ANGELONE_TOTP_SECRET'),
        'redirect_uri' => env('BASE_URL') . '/api/angelone/callback.php',
        'login_url' => 'https://smartapi.angelone.in/publisher-login',
        'token_url' => 'https://apiconnect.angelbroking.com/rest/auth/angelbroking/user/v1/loginByPassword'
    ],
    
    'upstox' => [
        'api_key' => env('UPSTOX_API_KEY'),
        'api_secret' => env('UPSTOX_API_SECRET'),
        'redirect_uri' => env('BASE_URL') . '/api/upstox/callback.php',
        'login_url' => 'https://api.upstox.com/v2/login/authorization/dialog',
        'token_url' => 'https://api.upstox.com/v2/login/authorization/token'
    ],
    
    'coin' => [
        'api_key' => env('ZERODHA_API_KEY'),
        'api_secret' => env('ZERODHA_API_SECRET'),
        'redirect_uri' => env('BASE_URL') . '/api/coin/callback.php'
    ]
];
?>
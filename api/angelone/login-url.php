<?php
// ============================================
// FILE: api/angelone/login-url.php
// ============================================
header('Content-Type: application/json');
session_start();
require_once '../config.php';

$config = $BROKER_CONFIG['angelone'];
$ANGELONE_API_KEY = $config['api_key'];

if (empty($ANGELONE_API_KEY) || $ANGELONE_API_KEY === 'your_angelone_api_key_here') {
    echo json_encode([
        'success' => false,
        'message' => 'AngelOne API key not configured'
    ]);
    exit;
}

// AngelOne uses direct credential login
echo json_encode([
    'success' => true,
    'loginUrl' => 'https://smartapi.angelone.in/publisher-login?api_key=' . $ANGELONE_API_KEY,
    'note' => 'AngelOne uses credential-based authentication'
]);
?>
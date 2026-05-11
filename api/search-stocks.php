<?php
// Stock Search using Twelve Data API
// Location: C:\xampp\htdocs\portfolio_watcher\api\search-stocks.php

session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Load Twelve Data API configuration
require_once __DIR__ . '/../config/twelve_data.php';
$twelveDataConfig = require __DIR__ . '/../config/twelve_data.php';

// Get search query
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($query) < 1) {
    echo json_encode(['success' => false, 'message' => 'Query too short']);
    exit;
}

// Check if API key is configured
if (empty($twelveDataConfig['api_key']) || $twelveDataConfig['api_key'] === 'YOUR_TWELVE_DATA_API_KEY_HERE') {
    echo json_encode([
        'success' => false,
        'message' => 'Twelve Data API key not configured. Please add your API key in config/twelve_data.php'
    ]);
    exit;
}

$stocks = [];

try {
    // Build Twelve Data API URL
    $baseUrl = $twelveDataConfig['base_url'];
    $endpoint = $twelveDataConfig['endpoints']['symbol_search'];
    $apiKey = $twelveDataConfig['api_key'];
    
    $apiUrl = $baseUrl . $endpoint . '?symbol=' . urlencode($query) . '&apikey=' . urlencode($apiKey);
    
    // Create context for HTTP request
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: PortfolioWatcher/1.0\r\n" .
                       "Accept: application/json\r\n",
            'timeout' => 15
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true
        ]
    ]);
    
    // Make API request
    $response = @file_get_contents($apiUrl, false, $context);
    
    if ($response === false) {
        throw new Exception('Failed to connect to Twelve Data API');
    }
    
    $data = json_decode($response, true);
    
    // Check for API errors
    if (isset($data['status']) && $data['status'] === 'error') {
        throw new Exception($data['message'] ?? 'API error occurred');
    }
    
    // Process results
    if (isset($data['data']) && is_array($data['data'])) {
        foreach ($data['data'] as $item) {
            // Twelve Data returns: symbol, instrument_name, exchange, country, currency, type
            $symbol = $item['symbol'] ?? '';
            $name = $item['instrument_name'] ?? $item['name'] ?? $symbol;
            $exchange = $item['exchange'] ?? 'UNKNOWN';
            $type = $item['type'] ?? 'EQUITY';
            
            // Skip if no symbol
            if (empty($symbol)) {
                continue;
            }
            
            // Format exchange name (remove common suffixes)
            $exchange = strtoupper($exchange);
            if (strpos($exchange, 'NASDAQ') !== false) {
                $exchange = 'NASDAQ';
            } elseif (strpos($exchange, 'NYSE') !== false) {
                $exchange = 'NYSE';
            } elseif (strpos($exchange, 'NSE') !== false || strpos($exchange, 'NATIONAL STOCK EXCHANGE') !== false) {
                $exchange = 'NSE';
            } elseif (strpos($exchange, 'BSE') !== false || strpos($exchange, 'BOMBAY STOCK EXCHANGE') !== false) {
                $exchange = 'BSE';
            }
            
            // Add to results
            $stocks[] = [
                'symbol' => $symbol,
                'name' => $name,
                'exchange' => $exchange,
                'type' => $type
            ];
        }
    } elseif (isset($data['symbol']) && isset($data['instrument_name'])) {
        // Single result format
        $stocks[] = [
            'symbol' => $data['symbol'],
            'name' => $data['instrument_name'] ?? $data['name'] ?? $data['symbol'],
            'exchange' => strtoupper($data['exchange'] ?? 'UNKNOWN'),
            'type' => $data['type'] ?? 'EQUITY'
        ];
    }
    
    // Limit results to 20
    $stocks = array_slice($stocks, 0, 20);
    
    echo json_encode([
        'success' => true,
        'stocks' => $stocks,
        'count' => count($stocks)
    ]);
    
} catch (Exception $e) {
    error_log("Stock search error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Search failed: ' . $e->getMessage(),
        'stocks' => []
    ]);
}
?>

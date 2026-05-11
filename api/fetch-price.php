<?php
// Fetch Stock Price using Twelve Data API
// Location: C:\xampp\htdocs\portfolio_watcher\api\fetch-price.php

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

// Get symbol from query parameter
$symbol = isset($_GET['symbol']) ? trim($_GET['symbol']) : '';

if (empty($symbol)) {
    echo json_encode(['success' => false, 'message' => 'Symbol is required']);
    exit;
}

// Get exchange from query parameter if provided (for Indian stocks)
$exchange = isset($_GET['exchange']) ? trim($_GET['exchange']) : '';

// Format symbol for Twelve Data API
// For Indian stocks (NSE/BSE), format is SYMBOL:EXCHANGE (e.g., ASIANPAINT:NSE)
$cleanSymbol = $symbol;

// If exchange is provided and it's NSE or BSE, format accordingly
if (!empty($exchange)) {
    $exchangeUpper = strtoupper($exchange);
    if ($exchangeUpper === 'NSE' || $exchangeUpper === 'BSE') {
        // Remove any existing exchange suffix
        $baseSymbol = str_replace([':NSE', ':BSE', '.NS', '.BO', '.NSE', '.BSE'], '', $cleanSymbol);
        $cleanSymbol = $baseSymbol . ':' . $exchangeUpper;
    }
} else {
    // Try to detect exchange from symbol suffix
    if (preg_match('/\.(NS|BO|NSE|BSE)$/i', $cleanSymbol, $matches)) {
        $suffix = strtoupper($matches[1]);
        $baseSymbol = preg_replace('/\.(NS|BO|NSE|BSE)$/i', '', $cleanSymbol);
        if ($suffix === 'NS' || $suffix === 'NSE') {
            $cleanSymbol = $baseSymbol . ':NSE';
        } elseif ($suffix === 'BO' || $suffix === 'BSE') {
            $cleanSymbol = $baseSymbol . ':BSE';
        }
    }
}

// Check if API key is configured
if (empty($twelveDataConfig['api_key']) || $twelveDataConfig['api_key'] === 'YOUR_TWELVE_DATA_API_KEY_HERE') {
    echo json_encode([
        'success' => false,
        'message' => 'Twelve Data API key not configured. Please add your API key in config/twelve_data.php'
    ]);
    exit;
}

try {
    // Build Twelve Data API URL - try /price endpoint first (simpler, faster)
    $baseUrl = $twelveDataConfig['base_url'];
    $apiKey = $twelveDataConfig['api_key'];
    
    // Try price endpoint first (simpler response)
    // For Indian stocks, try with and without exchange suffix
    $priceUrl = $baseUrl . '/price?symbol=' . urlencode($cleanSymbol) . '&apikey=' . urlencode($apiKey);
    
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
    
    // Make API request for price
    $priceResponse = @file_get_contents($priceUrl, false, $context);
    
    if ($priceResponse === false) {
        throw new Exception('Failed to connect to Twelve Data API');
    }
    
    $priceData = json_decode($priceResponse, true);
    
    // Check for API errors
    if (isset($priceData['status']) && $priceData['status'] === 'error') {
        // Log the error for debugging
        error_log("Twelve Data API error for symbol '$cleanSymbol': " . ($priceData['message'] ?? 'Unknown error'));
        
        // Try without exchange suffix as fallback
        $baseSymbolOnly = preg_replace('/:(NSE|BSE)$/i', '', $cleanSymbol);
        if ($baseSymbolOnly !== $cleanSymbol) {
            $altPriceUrl = $baseUrl . '/price?symbol=' . urlencode($baseSymbolOnly) . '&apikey=' . urlencode($apiKey);
            $altPriceResponse = @file_get_contents($altPriceUrl, false, $context);
            
            if ($altPriceResponse !== false) {
                $altPriceData = json_decode($altPriceResponse, true);
                if (!isset($altPriceData['status']) || $altPriceData['status'] !== 'error') {
                    $priceData = $altPriceData;
                    $cleanSymbol = $baseSymbolOnly; // Update symbol for quote request
                }
            }
        }
        
        // If still error, throw exception with more details
        if (isset($priceData['status']) && $priceData['status'] === 'error') {
            $errorMsg = $priceData['message'] ?? 'API error occurred';
            throw new Exception("Twelve Data API error: $errorMsg (Symbol: $cleanSymbol)");
        }
    }
    
    // Extract price from /price endpoint response
    // Response format: {"price": "123.45"}
    $price = isset($priceData['price']) ? floatval($priceData['price']) : 0;
    
    if ($price <= 0) {
        throw new Exception('Invalid price data received: ' . json_encode($priceData));
    }
    
    // Now get quote data for additional info (previous close, change, etc.)
    $quoteUrl = $baseUrl . '/quote?symbol=' . urlencode($cleanSymbol) . '&apikey=' . urlencode($apiKey);
    $quoteResponse = @file_get_contents($quoteUrl, false, $context);
    
    $previousClose = $price;
    $change = 0;
    $changePercent = 0;
    $currency = 'USD';
    
    if ($quoteResponse !== false) {
        $quoteData = json_decode($quoteResponse, true);
        
        if (!isset($quoteData['status']) || $quoteData['status'] !== 'error') {
            // Twelve Data quote endpoint returns: symbol, name, exchange, currency, datetime, timestamp, open, high, low, close, volume, previous_close, change, percent_change
            $previousClose = isset($quoteData['previous_close']) ? floatval($quoteData['previous_close']) : $price;
            $change = isset($quoteData['change']) ? floatval($quoteData['change']) : ($price - $previousClose);
            $changePercent = isset($quoteData['percent_change']) ? floatval($quoteData['percent_change']) : 
                             ($previousClose > 0 ? (($change / $previousClose) * 100) : 0);
            $currency = isset($quoteData['currency']) ? $quoteData['currency'] : 'USD';
        }
    }
    
    // Convert currency symbol for display
    // For Indian stocks (NSE/BSE), default to INR
    $currencySymbol = '₹'; // Default to INR
    if (strpos($cleanSymbol, ':NSE') !== false || strpos($cleanSymbol, ':BSE') !== false || 
        strtoupper($exchange) === 'NSE' || strtoupper($exchange) === 'BSE') {
        $currency = 'INR';
        $currencySymbol = '₹';
    } elseif ($currency === 'USD') {
        $currencySymbol = '$';
    } elseif ($currency === 'EUR') {
        $currencySymbol = '€';
    } elseif ($currency === 'GBP') {
        $currencySymbol = '£';
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'price' => $price,
            'previousClose' => $previousClose,
            'change' => $change,
            'changePercent' => $changePercent,
            'currency' => $currency,
            'currencySymbol' => $currencySymbol,
            'symbol' => $symbol
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Twelve Data API failed for symbol '$symbol': " . $e->getMessage());
    
    // Fallback to Yahoo Finance for price fetching
    try {
        // Format symbol for Yahoo Finance
        $yahooSymbol = $symbol;
        
        // Remove any Twelve Data format suffixes first
        $yahooSymbol = preg_replace('/:(NSE|BSE)$/i', '', $yahooSymbol);
        
        // If it's an Indian stock with :NSE or :BSE, convert to .NS or .BO format
        if (preg_match('/^(.+):(NSE|BSE)$/i', $symbol, $matches)) {
            $baseSym = $matches[1];
            $exch = strtoupper($matches[2]);
            $yahooSymbol = $baseSym . ($exch === 'NSE' ? '.NS' : '.BO');
        } elseif (!empty($exchange)) {
            // If exchange is provided separately, format accordingly
            $exchUpper = strtoupper($exchange);
            // Remove any existing suffixes
            $yahooSymbol = preg_replace('/\.(NS|BO|NSE|BSE)$/i', '', $yahooSymbol);
            
            if ($exchUpper === 'NSE') {
                $yahooSymbol = $yahooSymbol . '.NS';
            } elseif ($exchUpper === 'BSE') {
                $yahooSymbol = $yahooSymbol . '.BO';
            }
        } elseif (preg_match('/\.(NS|BO|NSE|BSE)$/i', $yahooSymbol, $suffixMatches)) {
            // Already has Yahoo format, normalize it
            $suffix = strtoupper($suffixMatches[1]);
            if ($suffix === 'NSE') {
                $yahooSymbol = preg_replace('/\.NSE$/i', '.NS', $yahooSymbol);
            } elseif ($suffix === 'BSE') {
                $yahooSymbol = preg_replace('/\.BSE$/i', '.BO', $yahooSymbol);
            }
        }
        
        // Try Yahoo Finance API as fallback
        $yahooUrl = "https://query1.finance.yahoo.com/v8/finance/chart/{$yahooSymbol}?interval=1d";
        
        $yahooContext = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'timeout' => 10
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);
        
        $yahooResponse = @file_get_contents($yahooUrl, false, $yahooContext);
        
        if ($yahooResponse === false) {
            throw new Exception('Failed to connect to Yahoo Finance API');
        }
        
        $yahooData = json_decode($yahooResponse, true);
        
        // Check if we got valid data
        if ($yahooData && isset($yahooData['chart']['result']) && is_array($yahooData['chart']['result']) && count($yahooData['chart']['result']) > 0) {
            if (isset($yahooData['chart']['result'][0]['meta'])) {
                $meta = $yahooData['chart']['result'][0]['meta'];
                
                $price = $meta['regularMarketPrice'] ?? 0;
                $previousClose = $meta['chartPreviousClose'] ?? $price;
                $change = $price - $previousClose;
                $changePercent = $previousClose > 0 ? (($change / $previousClose) * 100) : 0;
                $currency = $meta['currency'] ?? 'INR';
                
                // Determine currency symbol
                $currencySymbol = '₹';
                if ($currency === 'USD') {
                    $currencySymbol = '$';
                } elseif ($currency === 'EUR') {
                    $currencySymbol = '€';
                } elseif ($currency === 'GBP') {
                    $currencySymbol = '£';
                }
                
                error_log("Successfully fetched price for '$symbol' using Yahoo Finance fallback");
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'price' => $price,
                        'previousClose' => $previousClose,
                        'change' => $change,
                        'changePercent' => $changePercent,
                        'currency' => $currency,
                        'currencySymbol' => $currencySymbol,
                        'symbol' => $symbol,
                        'source' => 'yahoo_finance' // Indicate fallback was used
                    ]
                ]);
                return;
            } else {
                error_log("Yahoo Finance: No meta data in response for symbol '$yahooSymbol'");
            }
        } else {
            error_log("Yahoo Finance: Invalid response structure for symbol '$yahooSymbol'. Response: " . substr(json_encode($yahooData), 0, 200));
        }
    } catch (Exception $fallbackError) {
        error_log("Yahoo Finance fallback also failed for symbol '$symbol' (Yahoo format: '$yahooSymbol'): " . $fallbackError->getMessage());
    }
    
    // If both fail, return error
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch price from both Twelve Data and Yahoo Finance',
        'symbol' => $symbol,
        'twelve_data_error' => $e->getMessage()
    ]);
}
?>

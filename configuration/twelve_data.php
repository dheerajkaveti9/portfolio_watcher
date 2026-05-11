<?php
// Twelve Data API Configuration
// Location: C:\xampp\htdocs\portfolio_watcher\config\twelve_data.php

return [
    // Twelve Data API Key
    // Get your API key from: https://twelvedata.com/apikey
    'api_key' => '051435154a9b434dad79ed78747201b8ive',  // Replace with your actual API key
    
    // API Base URL
    'base_url' => 'https://api.twelvedata.com',
    
    // API Endpoints
    'endpoints' => [
        'symbol_search' => '/symbol_search',
        'price' => '/price',
        'quote' => '/quote',
        'time_series' => '/time_series'
    ]
];


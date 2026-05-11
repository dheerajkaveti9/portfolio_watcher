<?php
// Email Configuration
// Location: C:\xampp\htdocs\portfolio_watcher\config\email.php

return [
    // SMTP Configuration
    'smtp_host' => 'smtp.gmail.com',        // Gmail SMTP server
    'smtp_port' => 587,                      // TLS port (465 for SSL)
    'smtp_secure' => 'tls',                  // 'tls' or 'ssl'
    'smtp_auth' => true,
    'smtp_username' => 'prafulkaveti9@gmail.com',  // Your Gmail address
    'smtp_password' => 'hobm zcic figa fezf',                  // Your Gmail App Password (NOT regular password) - Get from https://myaccount.google.com/apppasswords
    
    // Email Settings
    // Note: Gmail requires from_email to match smtp_username
    'from_email' => 'prafulkaveti9@gmail.com',
    'from_name' => 'Portfolio Watcher',
    
    // Gmail Setup Instructions:
    // 1. Enable 2-Factor Authentication on your Google account
    // 2. Go to: https://myaccount.google.com/apppasswords
    // 3. Create an App Password for "Mail"
    // 4. Use that 16-character password in smtp_password above
];

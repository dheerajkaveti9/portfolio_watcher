-- Create user_stocks table for portfolio tracking
-- Run this SQL in your database if the table doesn't exist

CREATE TABLE IF NOT EXISTS user_stocks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    symbol VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    exchange VARCHAR(10) DEFAULT 'NSE',
    price DECIMAL(15, 2) NOT NULL,
    previous_close DECIMAL(15, 2) NOT NULL,
    change_amount DECIMAL(15, 2) DEFAULT 0,
    change_percent DECIMAL(10, 4) DEFAULT 0,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_stock (user_id, symbol),
    INDEX idx_user_id (user_id),
    INDEX idx_symbol (symbol)
);








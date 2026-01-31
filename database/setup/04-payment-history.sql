CREATE TABLE IF NOT EXISTS payment_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'DKK',
    status ENUM('succeeded', 'pending', 'failed', 'refunded') DEFAULT 'pending',
    description VARCHAR(255),
    stripe_payment_id VARCHAR(255),
    invoice_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_payment_account (account_id),
    INDEX idx_payment_status (status)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS account_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session_account (account_id),
    INDEX idx_session_token (session_token)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

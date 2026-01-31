CREATE TABLE IF NOT EXISTS event_owners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    account_id INT NOT NULL,
    role VARCHAR(50) DEFAULT 'owner',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_eo_event (event_id),
    INDEX idx_eo_account (account_id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS partner_inquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    partner_id INT NOT NULL,
    account_id INT NULL,
    event_id INT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    event_date DATE,
    guest_count INT,
    message TEXT NOT NULL,
    status ENUM('new', 'read', 'replied', 'closed') DEFAULT 'new',
    partner_reply TEXT,
    replied_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_inquiry_partner (partner_id),
    INDEX idx_inquiry_status (status)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

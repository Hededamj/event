CREATE TABLE IF NOT EXISTS partner_gallery (
    id INT AUTO_INCREMENT PRIMARY KEY,
    partner_id INT NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    caption VARCHAR(255),
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_gallery_partner (partner_id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

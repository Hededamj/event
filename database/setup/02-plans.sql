CREATE TABLE IF NOT EXISTS plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE,
    description TEXT,
    price_monthly INT DEFAULT 0,
    max_guests INT DEFAULT 50,
    max_events INT DEFAULT 1,
    features TEXT,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO plans (name, slug, price_monthly, max_guests, max_events, sort_order) VALUES
('Gratis', 'gratis', 0, 25, 1, 1),
('Basis', 'basis', 99, 50, 3, 2),
('Pro', 'pro', 199, 150, 10, 3),
('Business', 'business', 499, 500, 999, 4);

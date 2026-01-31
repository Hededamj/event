CREATE TABLE IF NOT EXISTS partner_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO partner_categories (name, slug, icon, sort_order) VALUES
('Teltudlejning', 'telt', 'tent', 1),
('Catering og Service', 'catering', 'food', 2),
('Festlokaler', 'lokaler', 'building', 3),
('DJ og Musik', 'musik', 'music', 4),
('Fotograf', 'fotograf', 'camera', 5),
('Blomster og Dekoration', 'dekoration', 'flower', 6),
('Kage og Dessert', 'kage', 'cake', 7),
('Underholdning', 'underholdning', 'theater', 8);

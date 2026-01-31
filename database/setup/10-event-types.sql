CREATE TABLE IF NOT EXISTS event_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE,
    icon VARCHAR(50),
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO event_types (name, slug, icon, sort_order) VALUES
('Konfirmation', 'konfirmation', 'church', 1),
('Bryllup', 'bryllup', 'wedding', 2),
('Foedselsdag', 'foedselsdag', 'cake', 3),
('Jubilaeum', 'jubilaeum', 'party', 4),
('Andet', 'andet', 'balloon', 5);

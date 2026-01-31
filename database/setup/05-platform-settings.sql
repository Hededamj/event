CREATE TABLE IF NOT EXISTS platform_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO platform_settings (setting_key, setting_value, description) VALUES
('platform_name', 'PartyParart', 'Platform display name'),
('support_email', 'mail@hededam.dk', 'Support contact email'),
('partner_approval_required', '1', 'Require admin approval for new partners'),
('commission_percentage', '10', 'Platform commission percentage'),
('trial_days', '14', 'Number of trial days for new accounts');

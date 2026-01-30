-- Migration 003: Partner Marketplace
-- Creates tables for partner/vendor marketplace functionality

-- Add is_platform_admin column to accounts
ALTER TABLE accounts ADD COLUMN is_platform_admin TINYINT(1) DEFAULT 0;

-- Partner categories
CREATE TABLE IF NOT EXISTS partner_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed default categories
INSERT INTO partner_categories (name, slug, icon, sort_order) VALUES
('Teltudlejning', 'telt', '⛺', 1),
('Catering & Service', 'catering', '🍽️', 2),
('Festlokaler', 'lokaler', '🏛️', 3),
('DJ & Musik', 'musik', '🎵', 4),
('Fotograf', 'fotograf', '📷', 5),
('Blomster & Dekoration', 'dekoration', '💐', 6),
('Kage & Dessert', 'kage', '🎂', 7),
('Underholdning', 'underholdning', '🎭', 8)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Partners/Vendors
CREATE TABLE IF NOT EXISTS partners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT,
    category_id INT NOT NULL,

    -- Company info
    company_name VARCHAR(255) NOT NULL,
    description TEXT,
    short_description VARCHAR(500),

    -- Contact
    contact_name VARCHAR(255),
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    website VARCHAR(255),

    -- Address
    address VARCHAR(255),
    city VARCHAR(100),
    postal_code VARCHAR(20),

    -- Pricing
    price_from INT,
    price_description VARCHAR(255),

    -- Images
    logo_url VARCHAR(500),
    cover_image_url VARCHAR(500),

    -- Status
    status ENUM('pending', 'approved', 'rejected', 'suspended') DEFAULT 'pending',
    is_featured TINYINT(1) DEFAULT 0,
    rejection_reason TEXT,

    -- Service area
    service_areas TEXT,
    nationwide TINYINT(1) DEFAULT 0,

    -- Stats
    view_count INT DEFAULT 0,
    inquiry_count INT DEFAULT 0,

    -- Timestamps
    approved_at TIMESTAMP NULL,
    approved_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (category_id) REFERENCES partner_categories(id),
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES accounts(id) ON DELETE SET NULL,
    INDEX idx_partner_status (status),
    INDEX idx_partner_category (category_id),
    INDEX idx_partner_featured (is_featured)
);

-- Partner gallery images
CREATE TABLE IF NOT EXISTS partner_gallery (
    id INT AUTO_INCREMENT PRIMARY KEY,
    partner_id INT NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    caption VARCHAR(255),
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE CASCADE
);

-- Inquiries from users to partners
CREATE TABLE IF NOT EXISTS partner_inquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    partner_id INT NOT NULL,
    account_id INT,
    event_id INT,

    -- Contact info
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50),

    -- Inquiry details
    event_date DATE,
    guest_count INT,
    message TEXT NOT NULL,

    -- Status
    status ENUM('new', 'read', 'replied', 'closed') DEFAULT 'new',
    partner_reply TEXT,
    replied_at TIMESTAMP NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL,
    INDEX idx_inquiry_partner (partner_id),
    INDEX idx_inquiry_status (status)
);

-- Platform settings table
CREATE TABLE IF NOT EXISTS platform_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO platform_settings (setting_key, setting_value, description) VALUES
('platform_name', 'EventPlatform', 'Platform display name'),
('support_email', 'mail@hededam.dk', 'Support contact email'),
('partner_approval_required', '1', 'Require admin approval for new partners'),
('commission_percentage', '10', 'Platform commission percentage'),
('trial_days', '14', 'Number of trial days for new accounts')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Set platform admin
UPDATE accounts SET is_platform_admin = 1 WHERE email = 'mail@hededam.dk';

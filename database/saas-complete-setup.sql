-- KOMPLET SaaS DATABASE SETUP
-- Kør denne fil i phpMyAdmin på hededam_dk_db_event
-- Dette opretter/opdaterer alle tabeller som SaaS-platformen kræver

-- ============================================
-- 1. ACCOUNTS TABEL
-- ============================================
CREATE TABLE IF NOT EXISTS accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255),
    name VARCHAR(255),
    phone VARCHAR(50),
    company_name VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    is_platform_admin TINYINT(1) DEFAULT 0,
    last_login_at TIMESTAMP NULL,
    login_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tilføj manglende kolonner hvis tabellen allerede eksisterer
ALTER TABLE accounts ADD COLUMN IF NOT EXISTS phone VARCHAR(50) NULL;
ALTER TABLE accounts ADD COLUMN IF NOT EXISTS company_name VARCHAR(255) NULL;
ALTER TABLE accounts ADD COLUMN IF NOT EXISTS is_platform_admin TINYINT(1) DEFAULT 0;
ALTER TABLE accounts ADD COLUMN IF NOT EXISTS last_login_at TIMESTAMP NULL;
ALTER TABLE accounts ADD COLUMN IF NOT EXISTS login_count INT DEFAULT 0;

-- ============================================
-- 2. PLANS TABEL (Abonnementsplaner)
-- ============================================
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

-- Tilføj manglende kolonner
ALTER TABLE plans ADD COLUMN IF NOT EXISTS slug VARCHAR(100) UNIQUE AFTER name;
ALTER TABLE plans ADD COLUMN IF NOT EXISTS max_guests INT DEFAULT 50;
ALTER TABLE plans ADD COLUMN IF NOT EXISTS max_events INT DEFAULT 1;
ALTER TABLE plans ADD COLUMN IF NOT EXISTS features TEXT;
ALTER TABLE plans ADD COLUMN IF NOT EXISTS sort_order INT DEFAULT 0;

-- Indsæt standard planer hvis tom
INSERT IGNORE INTO plans (name, slug, price_monthly, max_guests, max_events, sort_order) VALUES
('Gratis', 'gratis', 0, 25, 1, 1),
('Basis', 'basis', 99, 50, 3, 2),
('Pro', 'pro', 199, 150, 10, 3),
('Business', 'business', 499, 500, 999, 4);

-- ============================================
-- 3. SUBSCRIPTIONS TABEL (Abonnementer)
-- ============================================
CREATE TABLE IF NOT EXISTS subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    plan_id INT NOT NULL,
    status ENUM('active', 'cancelled', 'past_due', 'trialing', 'paused') DEFAULT 'active',
    stripe_subscription_id VARCHAR(255),
    stripe_customer_id VARCHAR(255),
    current_period_start TIMESTAMP NULL,
    current_period_end TIMESTAMP NULL,
    cancel_at_period_end TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sub_account (account_id),
    INDEX idx_sub_status (status)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ============================================
-- 4. PAYMENT_HISTORY TABEL (Betalingshistorik)
-- ============================================
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

-- ============================================
-- 5. PLATFORM_SETTINGS TABEL
-- ============================================
CREATE TABLE IF NOT EXISTS platform_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Indsæt standard indstillinger
INSERT IGNORE INTO platform_settings (setting_key, setting_value, description) VALUES
('platform_name', 'PartyParart', 'Platform display name'),
('support_email', 'mail@hededam.dk', 'Support contact email'),
('partner_approval_required', '1', 'Require admin approval for new partners'),
('commission_percentage', '10', 'Platform commission percentage'),
('trial_days', '14', 'Number of trial days for new accounts');

-- ============================================
-- 6. PARTNER_CATEGORIES TABEL
-- ============================================
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

-- Konverter til utf8mb4 for emoji-support
ALTER TABLE partner_categories CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Indsæt kategorier
INSERT IGNORE INTO partner_categories (name, slug, icon, sort_order) VALUES
('Teltudlejning', 'telt', '⛺', 1),
('Catering & Service', 'catering', '🍽️', 2),
('Festlokaler', 'lokaler', '🏛️', 3),
('DJ & Musik', 'musik', '🎵', 4),
('Fotograf', 'fotograf', '📷', 5),
('Blomster & Dekoration', 'dekoration', '💐', 6),
('Kage & Dessert', 'kage', '🎂', 7),
('Underholdning', 'underholdning', '🎭', 8);

-- ============================================
-- 7. PARTNERS TABEL (Leverandører)
-- ============================================
CREATE TABLE IF NOT EXISTS partners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NULL,
    category_id INT NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    description TEXT,
    short_description VARCHAR(500),
    contact_name VARCHAR(255),
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    website VARCHAR(255),
    address VARCHAR(255),
    city VARCHAR(100),
    postal_code VARCHAR(20),
    price_from INT,
    price_description VARCHAR(255),
    logo_url VARCHAR(500),
    cover_image_url VARCHAR(500),
    status ENUM('pending', 'approved', 'rejected', 'suspended') DEFAULT 'pending',
    is_featured TINYINT(1) DEFAULT 0,
    rejection_reason TEXT,
    service_areas TEXT,
    nationwide TINYINT(1) DEFAULT 0,
    view_count INT DEFAULT 0,
    inquiry_count INT DEFAULT 0,
    approved_at TIMESTAMP NULL,
    approved_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_partner_status (status),
    INDEX idx_partner_category (category_id),
    INDEX idx_partner_featured (is_featured)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ============================================
-- 8. PARTNER_GALLERY TABEL
-- ============================================
CREATE TABLE IF NOT EXISTS partner_gallery (
    id INT AUTO_INCREMENT PRIMARY KEY,
    partner_id INT NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    caption VARCHAR(255),
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_gallery_partner (partner_id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ============================================
-- 9. PARTNER_INQUIRIES TABEL (Forespørgsler)
-- ============================================
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

-- ============================================
-- 10. EVENT_TYPES TABEL
-- ============================================
CREATE TABLE IF NOT EXISTS event_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE,
    icon VARCHAR(50),
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Indsæt event typer
INSERT IGNORE INTO event_types (name, slug, icon, sort_order) VALUES
('Konfirmation', 'konfirmation', '⛪', 1),
('Bryllup', 'bryllup', '💒', 2),
('Fødselsdag', 'foedselsdag', '🎂', 3),
('Jubilæum', 'jubilaeum', '🎉', 4),
('Andet', 'andet', '🎈', 5);

-- ============================================
-- 11. EVENT_OWNERS TABEL
-- ============================================
CREATE TABLE IF NOT EXISTS event_owners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    account_id INT NOT NULL,
    role VARCHAR(50) DEFAULT 'owner',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_eo_event (event_id),
    INDEX idx_eo_account (account_id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ============================================
-- 12. ACCOUNT_SESSIONS TABEL
-- ============================================
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

-- ============================================
-- 13. EVENTS TABEL - Tilføj manglende kolonner
-- ============================================
ALTER TABLE events ADD COLUMN IF NOT EXISTS event_type_id INT NULL;
ALTER TABLE events ADD COLUMN IF NOT EXISTS is_legacy TINYINT(1) DEFAULT 0;
ALTER TABLE events ADD COLUMN IF NOT EXISTS status VARCHAR(50) DEFAULT 'active';

-- ============================================
-- 14. SÆT PLATFORM ADMIN
-- ============================================
UPDATE accounts SET is_platform_admin = 1 WHERE email = 'mail@hededam.dk';

-- ============================================
-- FÆRDIG!
-- ============================================
SELECT 'Database setup complete!' as status;

-- Migration 001: Account System for SaaS Platform
-- Creates multi-tenant architecture with accounts, subscriptions, and event types

-- Plans table (subscription tiers)
CREATE TABLE IF NOT EXISTS plans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    price_monthly DECIMAL(10,2) NOT NULL DEFAULT 0,
    price_yearly DECIMAL(10,2) NOT NULL DEFAULT 0,
    max_guests INT NOT NULL DEFAULT 30,
    max_events INT NOT NULL DEFAULT 1,
    features JSON,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default plans
INSERT INTO plans (name, slug, price_monthly, price_yearly, max_guests, max_events, features, sort_order) VALUES
('Gratis', 'free', 0, 0, 30, 1, '{"seating": false, "toastmaster": false, "budget": false, "checklist": false, "custom_domain": false}', 1),
('Basis', 'basic', 99, 990, 100, 3, '{"seating": true, "toastmaster": false, "budget": false, "checklist": true, "custom_domain": false}', 2),
('Premium', 'premium', 199, 1990, 300, 10, '{"seating": true, "toastmaster": true, "budget": true, "checklist": true, "custom_domain": false}', 3),
('Pro', 'pro', 499, 4990, 1000, 999, '{"seating": true, "toastmaster": true, "budget": true, "checklist": true, "custom_domain": true}', 4);

-- Accounts table (global platform users/owners)
CREATE TABLE IF NOT EXISTS accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    company VARCHAR(100),
    avatar_url VARCHAR(255),
    email_verified_at TIMESTAMP NULL,
    email_verification_token VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    last_login_at TIMESTAMP NULL,
    login_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Subscriptions table
CREATE TABLE IF NOT EXISTS subscriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL,
    plan_id INT NOT NULL,
    status ENUM('active', 'cancelled', 'past_due', 'trialing', 'paused') DEFAULT 'active',
    stripe_customer_id VARCHAR(100),
    stripe_subscription_id VARCHAR(100),
    current_period_start TIMESTAMP NULL,
    current_period_end TIMESTAMP NULL,
    cancel_at_period_end BOOLEAN DEFAULT FALSE,
    cancelled_at TIMESTAMP NULL,
    trial_ends_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES plans(id)
);

-- Event types table
CREATE TABLE IF NOT EXISTS event_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    icon VARCHAR(50),
    description TEXT,
    default_theme VARCHAR(50) DEFAULT 'elegant',
    has_secondary_person BOOLEAN DEFAULT FALSE,
    person_label VARCHAR(50) DEFAULT 'Hovedperson',
    secondary_person_label VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default event types
INSERT INTO event_types (name, slug, icon, description, has_secondary_person, person_label, secondary_person_label, sort_order) VALUES
('Konfirmation', 'confirmation', 'cross', 'Fejr en konfirmation med familie og venner', FALSE, 'Konfirmand', NULL, 1),
('Bryllup', 'wedding', 'rings', 'Planlæg jeres bryllup fra A til Z', TRUE, 'Brud', 'Gom', 2),
('Fødselsdag', 'birthday', 'cake', 'Fejr en rund fødselsdag', FALSE, 'Fødselar', NULL, 3),
('Barnedåb', 'baptism', 'baby', 'Velkommen til en ny verdensborger', FALSE, 'Barn', NULL, 4),
('Jubilæum', 'anniversary', 'star', 'Markér et jubilæum', TRUE, 'Person 1', 'Person 2', 5),
('Firmafest', 'corporate', 'briefcase', 'Planlæg firmafester og events', FALSE, 'Virksomhed', NULL, 6),
('Andet', 'other', 'calendar', 'Alle andre typer arrangementer', FALSE, 'Hovedperson', NULL, 99);

-- Event owners table (many-to-many between accounts and events)
CREATE TABLE IF NOT EXISTS event_owners (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL,
    event_id INT NOT NULL,
    role ENUM('owner', 'admin', 'editor', 'viewer') DEFAULT 'owner',
    invited_by_account_id INT,
    accepted_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_account_event (account_id, event_id),
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (invited_by_account_id) REFERENCES accounts(id) ON DELETE SET NULL
);

-- Payment history table
CREATE TABLE IF NOT EXISTS payment_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL,
    subscription_id INT,
    stripe_payment_intent_id VARCHAR(100),
    stripe_invoice_id VARCHAR(100),
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'DKK',
    status ENUM('succeeded', 'pending', 'failed', 'refunded') DEFAULT 'pending',
    description VARCHAR(255),
    invoice_url VARCHAR(255),
    receipt_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL
);

-- Password resets table
CREATE TABLE IF NOT EXISTS password_resets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(100) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_email (email)
);

-- Add new columns to events table
ALTER TABLE events
    ADD COLUMN IF NOT EXISTS account_id INT AFTER id,
    ADD COLUMN IF NOT EXISTS event_type_id INT AFTER account_id,
    ADD COLUMN IF NOT EXISTS slug VARCHAR(100) AFTER event_type_id,
    ADD COLUMN IF NOT EXISTS status ENUM('draft', 'active', 'completed', 'archived') DEFAULT 'active' AFTER slug,
    ADD COLUMN IF NOT EXISTS main_person_name VARCHAR(100) AFTER status,
    ADD COLUMN IF NOT EXISTS secondary_person_name VARCHAR(100) AFTER main_person_name,
    ADD COLUMN IF NOT EXISTS is_legacy BOOLEAN DEFAULT FALSE AFTER secondary_person_name;

-- Add unique index for slug
ALTER TABLE events ADD UNIQUE INDEX IF NOT EXISTS idx_slug (slug);

-- Add foreign keys to events (if not already exists)
-- Note: These might fail if events table already has data without matching account_id
-- ALTER TABLE events ADD FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL;
-- ALTER TABLE events ADD FOREIGN KEY (event_type_id) REFERENCES event_types(id) ON DELETE SET NULL;

-- Account sessions table (for "remember me" functionality)
CREATE TABLE IF NOT EXISTS account_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    user_agent VARCHAR(255),
    ip_address VARCHAR(45),
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    INDEX idx_token (token_hash)
);

-- Create index on account_id for events table
ALTER TABLE events ADD INDEX IF NOT EXISTS idx_account_id (account_id);

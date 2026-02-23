-- Migration: 017_consolidate_schema.sql
-- Consolidates all DDL that was previously scattered across page files.
-- These statements were run on every request; moving them here makes them
-- one-time migration operations. All statements are idempotent.
--
-- Sources:
--   admin/schedule.php
--   admin/settings.php
--   admin/guests.php       (SHOW COLUMNS + ALTER for max_guests, guest_names)
--   admin/bordplan.php     (CREATE seating_tables/seating_assignments, ALTER is_high_table)
--   admin/toastmaster.php  (CREATE toastmaster_items/toastmaster_access, ALTER columns)
--   guest/indslag.php      (CREATE toastmaster_items/toastmaster_messages)
--   guest/schedule.php     (SHOW COLUMNS for item_date)
--   e/pages/indslag.php    (CREATE toastmaster_items/toastmaster_messages)
--   toastmaster/index.php  (CREATE toastmaster_messages)
--   includes/gdpr.php      (CREATE consent_records, ALTER guests GDPR columns)
--   includes/rate-limiter.php (CREATE rate_limits)
--   includes/audit.php     (CREATE audit_log)
--   includes/auth-account.php (CREATE login_attempts)

-- ============================================
-- SCHEDULE ITEMS: item_date column
-- Source: admin/schedule.php, guest/schedule.php
-- ============================================

ALTER TABLE schedule_items ADD COLUMN IF NOT EXISTS item_date DATE NULL AFTER time;

-- ============================================
-- EVENTS TABLE: Visibility toggles + wishlist URL
-- Source: admin/settings.php
-- ============================================

ALTER TABLE events ADD COLUMN IF NOT EXISTS show_wishlist BOOLEAN DEFAULT TRUE;
ALTER TABLE events ADD COLUMN IF NOT EXISTS show_menu BOOLEAN DEFAULT TRUE;
ALTER TABLE events ADD COLUMN IF NOT EXISTS show_schedule BOOLEAN DEFAULT TRUE;
ALTER TABLE events ADD COLUMN IF NOT EXISTS show_photos BOOLEAN DEFAULT TRUE;
ALTER TABLE events ADD COLUMN IF NOT EXISTS oenskesky_url VARCHAR(500) NULL;

-- ============================================
-- GUESTS TABLE: max_guests, guest_names
-- Source: admin/guests.php
-- Already added without IF NOT EXISTS in 010_app_features.sql;
-- repeating here with IF NOT EXISTS for safety on older DBs.
-- ============================================

ALTER TABLE guests ADD COLUMN IF NOT EXISTS max_guests INT DEFAULT 1;
ALTER TABLE guests ADD COLUMN IF NOT EXISTS guest_names TEXT NULL;

-- ============================================
-- GUESTS TABLE: GDPR consent columns
-- Source: includes/gdpr.php
-- Already in 007_gdpr_compliance.sql (without IF NOT EXISTS);
-- repeating with IF NOT EXISTS for idempotency on partially-migrated DBs.
-- ============================================

ALTER TABLE guests ADD COLUMN IF NOT EXISTS privacy_consent TINYINT(1) DEFAULT 0;
ALTER TABLE guests ADD COLUMN IF NOT EXISTS privacy_consent_at TIMESTAMP NULL;
ALTER TABLE guests ADD COLUMN IF NOT EXISTS privacy_consent_version VARCHAR(20) DEFAULT NULL;
ALTER TABLE guests ADD COLUMN IF NOT EXISTS marketing_consent TINYINT(1) DEFAULT 0;
ALTER TABLE guests ADD COLUMN IF NOT EXISTS marketing_consent_at TIMESTAMP NULL;

-- ============================================
-- SEATING TABLES
-- Source: admin/bordplan.php
-- Already in 010_app_features.sql; repeated here with IF NOT EXISTS for completeness.
-- ============================================

CREATE TABLE IF NOT EXISTS seating_tables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    table_type ENUM('round', 'rectangle', 'square', 'ushape') DEFAULT 'round',
    capacity INT DEFAULT 8,
    position_x INT DEFAULT 0,
    position_y INT DEFAULT 0,
    sort_order INT DEFAULT 0,
    is_high_table TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_seating_event (event_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE seating_tables ADD COLUMN IF NOT EXISTS is_high_table TINYINT(1) DEFAULT 0;

CREATE TABLE IF NOT EXISTS seating_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    table_id INT NOT NULL,
    guest_name VARCHAR(255) NOT NULL,
    seat_number INT DEFAULT NULL,
    guest_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_assignment_event (event_id),
    INDEX idx_assignment_table (table_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (table_id) REFERENCES seating_tables(id) ON DELETE CASCADE,
    FOREIGN KEY (guest_id) REFERENCES guests(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TOASTMASTER ITEMS
-- Source: admin/toastmaster.php, guest/indslag.php, e/pages/indslag.php
-- Already in 010_app_features.sql; repeated here with IF NOT EXISTS.
-- ============================================

CREATE TABLE IF NOT EXISTS toastmaster_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    guest_name VARCHAR(255) NOT NULL,
    item_type ENUM('tale', 'sang', 'sketch', 'quiz', 'leg', 'musik', 'andet') DEFAULT 'tale',
    title VARCHAR(255) DEFAULT NULL,
    description TEXT,
    duration_minutes INT DEFAULT 5,
    is_secret TINYINT(1) DEFAULT 0,
    status ENUM('pending', 'approved', 'completed') DEFAULT 'pending',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_toast_event (event_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TOASTMASTER ACCESS
-- Source: admin/toastmaster.php, admin/guests.php
-- Already in 010_app_features.sql; repeated here with IF NOT EXISTS.
-- ============================================

CREATE TABLE IF NOT EXISTS toastmaster_access (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    access_code VARCHAR(20) NOT NULL,
    name VARCHAR(255) DEFAULT 'Toastmaster',
    email VARCHAR(255) DEFAULT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    guest_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_event_code (event_id, access_code),
    INDEX idx_access_event (event_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE toastmaster_access ADD COLUMN IF NOT EXISTS email VARCHAR(255) DEFAULT NULL AFTER name;
ALTER TABLE toastmaster_access ADD COLUMN IF NOT EXISTS is_primary TINYINT(1) DEFAULT 0 AFTER email;
ALTER TABLE toastmaster_access ADD COLUMN IF NOT EXISTS guest_id INT DEFAULT NULL AFTER is_primary;

-- ============================================
-- TOASTMASTER MESSAGES
-- Source: guest/indslag.php, e/pages/indslag.php, toastmaster/index.php
-- NEW - not present in any previous migration.
-- ============================================

CREATE TABLE IF NOT EXISTS toastmaster_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    guest_id INT DEFAULT NULL,
    guest_name VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_from_toastmaster TINYINT(1) DEFAULT 0,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_messages_event (event_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- CONSENT RECORDS
-- Source: includes/gdpr.php
-- Already in 007_gdpr_compliance.sql; repeated with IF NOT EXISTS for safety.
-- ============================================

CREATE TABLE IF NOT EXISTS consent_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT DEFAULT NULL,
    guest_id INT DEFAULT NULL,
    partner_id INT DEFAULT NULL,
    consent_type ENUM('privacy_policy', 'terms', 'marketing', 'data_processing', 'cookies') NOT NULL,
    consent_version VARCHAR(20) NOT NULL DEFAULT '1.0',
    is_granted TINYINT(1) NOT NULL DEFAULT 1,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    withdrawn_at TIMESTAMP NULL,
    INDEX idx_consent_account (account_id),
    INDEX idx_consent_guest (guest_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- RATE LIMITS
-- Source: includes/rate-limiter.php
-- Already in 008_upgrade_guest_codes.sql; repeated with IF NOT EXISTS for safety.
-- ============================================

CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    action VARCHAR(50) NOT NULL,
    attempts INT DEFAULT 1,
    first_attempt_at INT NOT NULL,
    last_attempt_at INT NOT NULL,
    blocked_until INT DEFAULT NULL,
    INDEX idx_ip_action (ip_address, action),
    INDEX idx_blocked (blocked_until),
    INDEX idx_cleanup (last_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- AUDIT LOG
-- Source: includes/audit.php
-- Already in 009_audit_log.sql; repeated with IF NOT EXISTS for safety.
-- ============================================

CREATE TABLE IF NOT EXISTS audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    account_id INT DEFAULT NULL,
    user_id INT DEFAULT NULL,
    guest_id INT DEFAULT NULL,
    event_id INT DEFAULT NULL,
    action VARCHAR(50) NOT NULL,
    resource_type VARCHAR(50) DEFAULT NULL,
    resource_id INT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    details JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_event_id (event_id),
    INDEX idx_audit_action (action),
    INDEX idx_audit_created_at (created_at),
    INDEX idx_audit_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- LOGIN ATTEMPTS
-- Source: includes/auth-account.php
-- NEW - not present in any previous migration.
-- ============================================

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    email VARCHAR(255) NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip (ip_address),
    INDEX idx_email (email),
    INDEX idx_attempted (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

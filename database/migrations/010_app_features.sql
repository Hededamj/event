-- App Features Migration
-- Migration: 010_app_features.sql
-- Adds all missing columns and tables for full feature parity with /admin/

-- ============================================
-- GUESTS TABLE ENHANCEMENTS
-- ============================================

-- Max guests per invitation
ALTER TABLE guests ADD COLUMN IF NOT EXISTS max_guests INT DEFAULT 1;

-- Guest names for seating (JSON array like ["Mormor", "Morfar"])
ALTER TABLE guests ADD COLUMN IF NOT EXISTS guest_names TEXT NULL;

-- Dietary restrictions/allergies
ALTER TABLE guests ADD COLUMN IF NOT EXISTS dietary_notes TEXT NULL;

-- Internal notes (admin only)
ALTER TABLE guests ADD COLUMN IF NOT EXISTS internal_notes TEXT NULL;

-- Invitation tracking
ALTER TABLE guests ADD COLUMN IF NOT EXISTS invitation_sent TINYINT(1) DEFAULT 0;
ALTER TABLE guests ADD COLUMN IF NOT EXISTS invitation_sent_at TIMESTAMP NULL;

-- ============================================
-- SEATING TABLES
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
    INDEX idx_seating_event (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seating_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    table_id INT NOT NULL,
    guest_name VARCHAR(255) NOT NULL,
    seat_number INT DEFAULT NULL,
    guest_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_assignment_event (event_id),
    INDEX idx_assignment_table (table_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- EVENTS TABLE - TOASTMASTER REFERENCE
-- ============================================

ALTER TABLE events ADD COLUMN IF NOT EXISTS toastmaster_guest_id INT DEFAULT NULL;
ALTER TABLE events ADD INDEX IF NOT EXISTS idx_toastmaster_guest (toastmaster_guest_id);

-- ============================================
-- TOASTMASTER TABLES
-- ============================================

CREATE TABLE IF NOT EXISTS toastmaster_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    guest_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    item_type ENUM('tale', 'sang', 'sketch', 'quiz', 'leg', 'musik', 'andet') DEFAULT 'tale',
    title VARCHAR(255) DEFAULT NULL,
    description TEXT,
    duration_minutes INT DEFAULT 5,
    is_secret TINYINT(1) DEFAULT 0,
    status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_toast_event (event_id),
    INDEX idx_toast_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    INDEX idx_access_event (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- BUDGET TABLE (if not exists)
-- ============================================

CREATE TABLE IF NOT EXISTS budget_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    category ENUM('lokale', 'mad', 'pynt', 'toj', 'gaver', 'underholdning', 'foto', 'andet') DEFAULT 'andet',
    title VARCHAR(255) NOT NULL,
    description TEXT,
    estimated_cost DECIMAL(10,2) DEFAULT 0,
    actual_cost DECIMAL(10,2) DEFAULT NULL,
    is_paid TINYINT(1) DEFAULT 0,
    paid_at DATE DEFAULT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_budget_event (event_id),
    INDEX idx_budget_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- UPDATE PLAN FEATURES
-- ============================================

UPDATE plans SET features = JSON_SET(
    COALESCE(features, '{}'),
    '$.csv_import', true,
    '$.bulk_add', true,
    '$.invitation_export', true
) WHERE slug = 'free' OR slug = 'gratis';

UPDATE plans SET features = JSON_SET(
    COALESCE(features, '{}'),
    '$.csv_import', true,
    '$.bulk_add', true,
    '$.invitation_export', true,
    '$.checklist', true,
    '$.seating', true,
    '$.budget', true
) WHERE slug = 'basis' OR slug = 'standard';

UPDATE plans SET features = JSON_SET(
    COALESCE(features, '{}'),
    '$.csv_import', true,
    '$.bulk_add', true,
    '$.invitation_export', true,
    '$.checklist', true,
    '$.seating', true,
    '$.budget', true,
    '$.toastmaster', true
) WHERE slug = 'pro' OR slug = 'premium';

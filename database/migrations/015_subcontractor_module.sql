-- ============================================================
-- Migration 015: Subcontractor / Vendor Marketplace Module
-- ============================================================
-- Creates the full schema for a vendor marketplace where
-- subcontractors (tent rentals, caterers, DJs, photographers,
-- etc.) can register, list services, receive bookings from
-- event organizers, and get paid via Stripe Connect.
--
-- Tables created:
--   1.  vendor_categories      - Service category taxonomy
--   2.  vendors                - Vendor/subcontractor accounts
--   3.  vendor_category_links  - Many-to-many vendors <-> categories
--   4.  vendor_services        - Individual service offerings
--   5.  vendor_gallery         - Portfolio / gallery images
--   6.  bookings               - Booking requests & lifecycle
--   7.  booking_messages       - In-booking messaging thread
--   8.  vendor_reviews         - Post-completion reviews & ratings
--   9.  manual_vendors         - Manually added vendors (not on platform)
--  10.  refund_requests        - Refund request workflow
--
-- Seed data:
--   10 default vendor_categories (Danish labels)
--
-- References existing tables: events(id), accounts(id)
-- ============================================================

-- ============================================
-- 1. VENDOR CATEGORIES
-- ============================================

CREATE TABLE IF NOT EXISTS vendor_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    icon VARCHAR(10),
    description VARCHAR(255),
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default categories
INSERT INTO vendor_categories (name, slug, icon, description, sort_order) VALUES
('Teltudlejning',  'teltudlejning',  '⛺', 'Udlejning af telte, pavilloner og tilbehør',            1),
('Catering',       'catering',       '🍽️', 'Mad, drikke og servering til dit event',               2),
('Festlokaler',    'festlokaler',    '🏛️', 'Lokaler og venues til fester og arrangementer',        3),
('DJ & Musik',     'dj-musik',       '🎵', 'DJs, bands og musikunderholdning',                      4),
('Fotograf',       'fotograf',       '📷', 'Fotografer og videografer',                              5),
('Blomster',       'blomster',       '💐', 'Blomsterdekorationer og buketter',                       6),
('Kage & Dessert', 'kage-dessert',   '🎂', 'Kager, desserter og søde sager',                        7),
('Underholdning',  'underholdning',  '🎭', 'Shows, aktiviteter og underholdning',                    8),
('Transport',      'transport',      '🚐', 'Transport, limousiner og kørsel',                       9),
('Andet',          'andet',          '📌', 'Øvrige leverandørtjenester',                            10)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ============================================
-- 2. VENDORS
-- ============================================

CREATE TABLE IF NOT EXISTS vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,

    -- Company info
    company_name VARCHAR(255) NOT NULL,
    contact_name VARCHAR(255),
    phone VARCHAR(50),
    website VARCHAR(255),
    description TEXT,
    short_description VARCHAR(500),

    -- Address / location
    address VARCHAR(255),
    city VARCHAR(100),
    postal_code VARCHAR(20),
    service_areas TEXT COMMENT 'JSON array of postal code ranges or region names',
    nationwide TINYINT(1) DEFAULT 0,

    -- Media
    logo_filename VARCHAR(255),
    cover_filename VARCHAR(255),

    -- Stripe Connect
    stripe_account_id VARCHAR(255),
    stripe_onboarding_complete TINYINT(1) DEFAULT 0,

    -- Status
    status ENUM('pending','approved','rejected','suspended') DEFAULT 'pending',
    rejection_reason TEXT,
    approved_at TIMESTAMP NULL,
    approved_by INT NULL,

    -- Aggregated stats (denormalized for performance)
    avg_rating DECIMAL(2,1) DEFAULT 0,
    review_count INT DEFAULT 0,
    booking_count INT DEFAULT 0,
    view_count INT DEFAULT 0,

    -- Auth
    last_login_at TIMESTAMP NULL,
    is_active TINYINT(1) DEFAULT 1,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_vendor_status (status),
    INDEX idx_vendor_city (city),
    INDEX idx_vendor_avg_rating (avg_rating DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. VENDOR CATEGORY LINKS (many-to-many)
-- ============================================

CREATE TABLE IF NOT EXISTS vendor_category_links (
    vendor_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (vendor_id, category_id),
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES vendor_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. VENDOR SERVICES
-- ============================================

CREATE TABLE IF NOT EXISTS vendor_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    price_from DECIMAL(10,2) NOT NULL,
    price_to DECIMAL(10,2) NULL,
    price_unit ENUM('fixed','per_person','per_hour') DEFAULT 'fixed',
    duration_hours DECIMAL(4,1) NULL,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    INDEX idx_vendor_service_vendor (vendor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. VENDOR GALLERY
-- ============================================

CREATE TABLE IF NOT EXISTS vendor_gallery (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255),
    caption VARCHAR(255),
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    INDEX idx_vendor_gallery_vendor (vendor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. BOOKINGS
-- ============================================

CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    vendor_id INT NOT NULL,
    vendor_service_id INT NULL,
    account_id INT NOT NULL,

    -- Status lifecycle
    status ENUM(
        'requested','quoted','accepted','deposited',
        'confirmed','completed','reviewed',
        'cancelled','refunded','disputed'
    ) DEFAULT 'requested',

    -- Pricing
    quoted_price DECIMAL(10,2) NULL,
    depositum_amount DECIMAL(10,2) NULL,
    commission_amount DECIMAL(10,2) NULL,
    vendor_payout DECIMAL(10,2) NULL,

    -- Stripe references
    stripe_payment_intent_id VARCHAR(255) NULL,
    stripe_transfer_id VARCHAR(255) NULL,
    stripe_refund_id VARCHAR(255) NULL,

    -- Event details (snapshot at booking time)
    event_date DATE NOT NULL,
    guest_count INT NULL,

    -- Messages
    organizer_message TEXT,
    vendor_message TEXT,

    -- Lifecycle timestamps
    quoted_at TIMESTAMP NULL,
    accepted_at TIMESTAMP NULL,
    paid_at TIMESTAMP NULL,
    confirmed_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    cancelled_at TIMESTAMP NULL,

    -- Cancellation
    cancelled_by ENUM('organizer','vendor','platform') NULL,
    cancel_reason TEXT,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE RESTRICT,
    FOREIGN KEY (vendor_service_id) REFERENCES vendor_services(id) ON DELETE SET NULL,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE RESTRICT,
    INDEX idx_booking_event (event_id),
    INDEX idx_booking_vendor (vendor_id),
    INDEX idx_booking_status (status),
    INDEX idx_booking_event_date (event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. BOOKING MESSAGES
-- ============================================

CREATE TABLE IF NOT EXISTS booking_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    sender_type ENUM('organizer','vendor','platform') NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    INDEX idx_booking_msg_booking (booking_id),
    INDEX idx_booking_msg_unread (booking_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 8. VENDOR REVIEWS
-- ============================================

CREATE TABLE IF NOT EXISTS vendor_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL UNIQUE,
    vendor_id INT NOT NULL,
    account_id INT NOT NULL,
    rating TINYINT NOT NULL,
    title VARCHAR(255),
    review_text TEXT,
    vendor_response TEXT NULL,
    vendor_responded_at TIMESTAMP NULL,
    is_visible TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accounts(id),
    INDEX idx_review_vendor (vendor_id),
    INDEX idx_review_vendor_rating (vendor_id, rating)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 9. MANUAL VENDORS (not on platform)
-- ============================================

CREATE TABLE IF NOT EXISTS manual_vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    category VARCHAR(50),
    company_name VARCHAR(255) NOT NULL,
    contact_name VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(50),
    website VARCHAR(255),
    agreed_price DECIMAL(10,2),
    is_paid TINYINT(1) DEFAULT 0,
    paid_at DATE NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_manual_vendor_event (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 10. REFUND REQUESTS
-- ============================================

CREATE TABLE IF NOT EXISTS refund_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    requested_by INT NOT NULL COMMENT 'account_id of requester',
    reason TEXT NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    admin_notes TEXT,
    resolved_by INT NULL,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES accounts(id),
    INDEX idx_refund_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Partner Commission System
-- Migration: 004_partner_commission.sql
-- Tracks bookings, calculates commission, and manages payouts

-- ============================================
-- PARTNER BOOKINGS
-- Tracks bookings made through the platform
-- ============================================
CREATE TABLE IF NOT EXISTS partner_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    partner_id INT NOT NULL,
    inquiry_id INT DEFAULT NULL COMMENT 'Link to original inquiry if applicable',
    event_id INT DEFAULT NULL COMMENT 'Link to event if booked by organizer',
    account_id INT DEFAULT NULL COMMENT 'Account that made the booking',

    -- Booking details
    booking_reference VARCHAR(20) NOT NULL UNIQUE COMMENT 'Public reference number',
    service_description VARCHAR(500) NOT NULL COMMENT 'What was booked',
    event_date DATE NOT NULL,
    guest_count INT DEFAULT NULL,

    -- Financial
    total_amount DECIMAL(10,2) NOT NULL COMMENT 'Total booking value in DKK',
    commission_rate DECIMAL(5,2) NOT NULL DEFAULT 10.00 COMMENT 'Commission percentage',
    commission_amount DECIMAL(10,2) NOT NULL COMMENT 'Platform commission in DKK',
    partner_amount DECIMAL(10,2) NOT NULL COMMENT 'Amount to partner after commission',

    -- Status tracking
    status ENUM('pending', 'confirmed', 'completed', 'cancelled', 'refunded') NOT NULL DEFAULT 'pending',
    payment_status ENUM('unpaid', 'partial', 'paid') NOT NULL DEFAULT 'unpaid',
    commission_status ENUM('pending', 'invoiced', 'paid') NOT NULL DEFAULT 'pending',

    -- Customer info (may differ from account)
    customer_name VARCHAR(255) NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(50) DEFAULT NULL,

    -- Notes
    notes TEXT DEFAULT NULL COMMENT 'Internal notes',
    partner_notes TEXT DEFAULT NULL COMMENT 'Notes from partner',
    cancellation_reason TEXT DEFAULT NULL,

    -- Timestamps
    booked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confirmed_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    cancelled_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign keys
    FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE RESTRICT,
    FOREIGN KEY (inquiry_id) REFERENCES partner_inquiries(id) ON DELETE SET NULL,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL,

    -- Indexes
    INDEX idx_booking_partner (partner_id),
    INDEX idx_booking_status (status),
    INDEX idx_booking_commission_status (commission_status),
    INDEX idx_booking_event_date (event_date),
    INDEX idx_booking_reference (booking_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PARTNER PAYOUTS
-- Tracks commission payments from partners to platform
-- ============================================
CREATE TABLE IF NOT EXISTS partner_payouts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    partner_id INT NOT NULL,

    -- Payout details
    payout_reference VARCHAR(20) NOT NULL UNIQUE COMMENT 'Invoice/payout reference',
    period_start DATE NOT NULL COMMENT 'Commission period start',
    period_end DATE NOT NULL COMMENT 'Commission period end',

    -- Financial
    total_bookings INT NOT NULL DEFAULT 0 COMMENT 'Number of bookings in period',
    total_booking_value DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Sum of all booking values',
    commission_amount DECIMAL(10,2) NOT NULL COMMENT 'Total commission owed',
    adjustment_amount DECIMAL(10,2) DEFAULT 0 COMMENT 'Any adjustments (+/-)',
    adjustment_reason VARCHAR(255) DEFAULT NULL,
    final_amount DECIMAL(10,2) NOT NULL COMMENT 'Final amount to pay',

    -- Status
    status ENUM('draft', 'sent', 'paid', 'overdue', 'cancelled') NOT NULL DEFAULT 'draft',

    -- Payment tracking
    due_date DATE NOT NULL,
    paid_at TIMESTAMP NULL,
    payment_method VARCHAR(50) DEFAULT NULL COMMENT 'bank_transfer, card, etc.',
    payment_reference VARCHAR(100) DEFAULT NULL COMMENT 'Transaction ID',

    -- Admin
    created_by INT DEFAULT NULL COMMENT 'Admin who created payout',
    notes TEXT DEFAULT NULL,

    -- Timestamps
    sent_at TIMESTAMP NULL COMMENT 'When invoice was sent',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign keys
    FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES accounts(id) ON DELETE SET NULL,

    -- Indexes
    INDEX idx_payout_partner (partner_id),
    INDEX idx_payout_status (status),
    INDEX idx_payout_period (period_start, period_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PAYOUT LINE ITEMS
-- Links bookings to payouts
-- ============================================
CREATE TABLE IF NOT EXISTS partner_payout_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payout_id INT NOT NULL,
    booking_id INT NOT NULL,
    commission_amount DECIMAL(10,2) NOT NULL,

    FOREIGN KEY (payout_id) REFERENCES partner_payouts(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES partner_bookings(id) ON DELETE RESTRICT,
    UNIQUE KEY unique_booking_payout (payout_id, booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PARTNER COMMISSION SETTINGS
-- Per-partner commission configuration
-- ============================================
CREATE TABLE IF NOT EXISTS partner_commission_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    partner_id INT NOT NULL UNIQUE,

    -- Commission rates
    commission_rate DECIMAL(5,2) NOT NULL DEFAULT 10.00 COMMENT 'Default commission %',
    minimum_commission DECIMAL(10,2) DEFAULT NULL COMMENT 'Minimum commission per booking',

    -- Payout settings
    payout_frequency ENUM('monthly', 'biweekly', 'weekly') DEFAULT 'monthly',
    payout_threshold DECIMAL(10,2) DEFAULT 500.00 COMMENT 'Minimum amount before payout',

    -- Bank details for refunds/payouts TO partner
    bank_name VARCHAR(100) DEFAULT NULL,
    bank_account VARCHAR(50) DEFAULT NULL,
    bank_reg_number VARCHAR(10) DEFAULT NULL,

    -- Special terms
    special_terms TEXT DEFAULT NULL,
    terms_agreed_at TIMESTAMP NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PLATFORM DEFAULT SETTINGS
-- ============================================
INSERT INTO platform_settings (setting_key, setting_value, description) VALUES
('default_commission_rate', '10.00', 'Default partner commission percentage'),
('commission_payout_day', '1', 'Day of month for payout generation (1-28)'),
('commission_payment_terms', '14', 'Days until payment is due'),
('minimum_payout_amount', '500', 'Minimum DKK before payout is generated')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- ============================================
-- HELPER VIEW: Partner Commission Summary
-- ============================================
CREATE OR REPLACE VIEW v_partner_commission_summary AS
SELECT
    p.id as partner_id,
    p.company_name,
    COALESCE(pcs.commission_rate, 10.00) as commission_rate,

    -- Pending commission (completed bookings not yet paid)
    COALESCE(SUM(CASE
        WHEN pb.status = 'completed' AND pb.commission_status = 'pending'
        THEN pb.commission_amount
        ELSE 0
    END), 0) as pending_commission,

    -- Total bookings
    COUNT(DISTINCT pb.id) as total_bookings,
    COUNT(DISTINCT CASE WHEN pb.status = 'completed' THEN pb.id END) as completed_bookings,

    -- Total revenue through platform
    COALESCE(SUM(CASE WHEN pb.status = 'completed' THEN pb.total_amount ELSE 0 END), 0) as total_revenue,

    -- Total commission paid
    COALESCE(SUM(CASE WHEN pb.commission_status = 'paid' THEN pb.commission_amount ELSE 0 END), 0) as total_commission_paid

FROM partners p
LEFT JOIN partner_commission_settings pcs ON p.id = pcs.partner_id
LEFT JOIN partner_bookings pb ON p.id = pb.partner_id
WHERE p.status = 'approved'
GROUP BY p.id, p.company_name, pcs.commission_rate;

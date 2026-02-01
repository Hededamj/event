-- GDPR Compliance
-- Migration: 007_gdpr_compliance.sql
-- Adds consent tracking, data export logs, and deletion requests

-- ============================================
-- CONSENT RECORDS
-- Tracks user consent for data processing
-- ============================================
CREATE TABLE IF NOT EXISTS consent_records (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Who gave consent (one of these will be set)
    account_id INT DEFAULT NULL,
    guest_id INT DEFAULT NULL,
    partner_id INT DEFAULT NULL,

    -- What they consented to
    consent_type ENUM('privacy_policy', 'terms', 'marketing', 'data_processing', 'cookies') NOT NULL,
    consent_version VARCHAR(20) NOT NULL DEFAULT '1.0' COMMENT 'Version of policy agreed to',

    -- Consent details
    is_granted TINYINT(1) NOT NULL DEFAULT 1,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,

    -- Timestamps
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    withdrawn_at TIMESTAMP NULL,

    -- Foreign keys
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (guest_id) REFERENCES guests(id) ON DELETE CASCADE,

    -- Indexes
    INDEX idx_consent_account (account_id),
    INDEX idx_consent_guest (guest_id),
    INDEX idx_consent_type (consent_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- DATA EXPORT REQUESTS
-- Logs when users request their data
-- ============================================
CREATE TABLE IF NOT EXISTS data_export_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,

    -- Request details
    request_type ENUM('export', 'view') NOT NULL DEFAULT 'export',
    format ENUM('json', 'pdf', 'html') NOT NULL DEFAULT 'json',
    status ENUM('pending', 'processing', 'completed', 'failed', 'expired') NOT NULL DEFAULT 'pending',

    -- File info (when completed)
    file_path VARCHAR(500) DEFAULT NULL,
    file_size INT DEFAULT NULL,
    expires_at TIMESTAMP NULL COMMENT 'When download link expires',

    -- Processing
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    error_message TEXT DEFAULT NULL,

    -- Timestamps
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    downloaded_at TIMESTAMP NULL,

    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    INDEX idx_export_account (account_id),
    INDEX idx_export_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ACCOUNT DELETION REQUESTS
-- Handles "Right to be Forgotten" with grace period
-- ============================================
CREATE TABLE IF NOT EXISTS account_deletion_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,

    -- Request details
    reason TEXT DEFAULT NULL COMMENT 'Optional reason for leaving',

    -- Status tracking
    status ENUM('pending', 'cancelled', 'completed') NOT NULL DEFAULT 'pending',
    scheduled_deletion_date DATE NOT NULL COMMENT '30 days from request',

    -- Confirmation
    confirmation_token VARCHAR(64) NOT NULL,
    confirmed_at TIMESTAMP NULL,

    -- Completion
    deleted_at TIMESTAMP NULL,
    deleted_by ENUM('user', 'admin', 'system') DEFAULT NULL,

    -- Timestamps
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    cancelled_at TIMESTAMP NULL,

    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    UNIQUE KEY unique_pending_request (account_id, status),
    INDEX idx_deletion_status (status),
    INDEX idx_deletion_scheduled (scheduled_deletion_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- DATA RETENTION SETTINGS
-- Configurable retention periods per data type
-- ============================================
CREATE TABLE IF NOT EXISTS data_retention_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data_type VARCHAR(50) NOT NULL UNIQUE,
    retention_days INT NOT NULL COMMENT 'Days to keep data (0 = forever)',
    description VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    last_cleanup_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default retention settings
INSERT INTO data_retention_settings (data_type, retention_days, description) VALUES
('completed_events', 365, 'Events that have passed (1 year)'),
('guest_data', 365, 'Guest information after event (1 year)'),
('audit_logs', 730, 'Security and audit logs (2 years)'),
('rate_limits', 7, 'Rate limiting records (1 week)'),
('session_data', 30, 'Expired sessions (30 days)'),
('export_files', 7, 'Data export files (1 week)'),
('deleted_accounts', 30, 'Soft-deleted account data (30 days grace period)')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ============================================
-- Add consent columns to guests table
-- ============================================
ALTER TABLE guests
ADD COLUMN IF NOT EXISTS privacy_consent TINYINT(1) DEFAULT 0 COMMENT 'Agreed to privacy policy',
ADD COLUMN IF NOT EXISTS privacy_consent_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS privacy_consent_version VARCHAR(20) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS marketing_consent TINYINT(1) DEFAULT 0 COMMENT 'Agreed to marketing',
ADD COLUMN IF NOT EXISTS marketing_consent_at TIMESTAMP NULL;

-- ============================================
-- Add deletion tracking to accounts
-- ============================================
ALTER TABLE accounts
ADD COLUMN IF NOT EXISTS deletion_requested_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS deletion_scheduled_for DATE NULL,
ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) DEFAULT 0;

-- ============================================
-- Privacy policy version tracking
-- ============================================
INSERT INTO platform_settings (setting_key, setting_value, description) VALUES
('privacy_policy_version', '1.0', 'Current privacy policy version'),
('terms_version', '1.0', 'Current terms of service version'),
('gdpr_contact_email', 'gdpr@example.com', 'Email for GDPR requests'),
('data_retention_enabled', 'true', 'Enable automatic data cleanup')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

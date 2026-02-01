-- Migration: Upgrade guest codes from 6-digit to 8-character alphanumeric
-- Run this migration after deploying the new code

-- Expand unique_code column to accommodate 8-character alphanumeric codes
-- Existing 6-digit codes will continue to work
ALTER TABLE guests MODIFY COLUMN unique_code VARCHAR(8) NOT NULL;

-- Create rate_limits table for brute force protection
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Note: Existing 6-digit codes will continue to work.
-- New guests will receive 8-character alphanumeric codes.
-- Old guests can be migrated by running:
-- UPDATE guests SET unique_code = ... WHERE LENGTH(unique_code) = 6;

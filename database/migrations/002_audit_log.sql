-- Audit Log Table
-- Tracks authentication events, data changes, and admin actions
-- Migration: 002_audit_log.sql

CREATE TABLE IF NOT EXISTS audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    account_id INT DEFAULT NULL,
    user_id INT DEFAULT NULL,
    guest_id INT DEFAULT NULL,
    event_id INT DEFAULT NULL,
    action VARCHAR(50) NOT NULL COMMENT 'auth.login, auth.logout, data.create, etc.',
    resource_type VARCHAR(50) DEFAULT NULL COMMENT 'Table/entity type affected',
    resource_id INT DEFAULT NULL COMMENT 'ID of affected record',
    ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IPv4 or IPv6',
    user_agent VARCHAR(500) DEFAULT NULL,
    details JSON DEFAULT NULL COMMENT 'Additional context',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes for common queries
    INDEX idx_audit_event_id (event_id),
    INDEX idx_audit_action (action),
    INDEX idx_audit_created_at (created_at),
    INDEX idx_audit_user_id (user_id),
    INDEX idx_audit_resource (resource_type, resource_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: Add foreign keys if strict referential integrity is needed
-- Note: These are commented out as audit logs should survive if referenced records are deleted

-- ALTER TABLE audit_log ADD CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;
-- ALTER TABLE audit_log ADD CONSTRAINT fk_audit_guest FOREIGN KEY (guest_id) REFERENCES guests(id) ON DELETE SET NULL;
-- ALTER TABLE audit_log ADD CONSTRAINT fk_audit_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL;

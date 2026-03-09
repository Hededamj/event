-- Referral/Agent Program: agents, referral links, and booking provisions
-- Migration 022

-- 1. Referral agents table
CREATE TABLE IF NOT EXISTS referral_agents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    referral_code VARCHAR(20) NOT NULL,
    provision_rate DECIMAL(4,2) NULL COMMENT 'Override rate; NULL = use global default',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_referral_agents_account (account_id),
    UNIQUE KEY uq_referral_agents_code (referral_code),
    INDEX idx_referral_agents_code (referral_code),
    CONSTRAINT fk_referral_agents_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Referral links table (agent <-> vendor relationship)
CREATE TABLE IF NOT EXISTS referral_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    vendor_id INT NOT NULL,
    provision_rate DECIMAL(4,2) NOT NULL COMMENT 'Locked rate at onboarding time',
    provision_until DATE NOT NULL COMMENT '12 months from vendor registration',
    total_earned DECIMAL(10,2) NOT NULL DEFAULT 0,
    last_paid_at DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_referral_links_vendor (vendor_id),
    INDEX idx_referral_links_agent (agent_id),
    INDEX idx_referral_links_provision_until (provision_until),
    CONSTRAINT fk_referral_links_agent FOREIGN KEY (agent_id) REFERENCES referral_agents(id) ON DELETE CASCADE,
    CONSTRAINT fk_referral_links_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Add agent provision columns to bookings
ALTER TABLE bookings
    ADD COLUMN agent_provision_amount DECIMAL(10,2) NULL,
    ADD COLUMN referral_link_id INT NULL,
    ADD CONSTRAINT fk_bookings_referral_link FOREIGN KEY (referral_link_id) REFERENCES referral_links(id) ON DELETE SET NULL;

-- 4. Add per-vendor commission rate override to vendors
ALTER TABLE vendors
    ADD COLUMN commission_rate DECIMAL(4,2) NULL COMMENT 'Override commission rate; NULL = use global default';

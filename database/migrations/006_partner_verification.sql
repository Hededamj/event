-- Partner Verification System
-- Migration: 006_partner_verification.sql
-- Allows partners to upload documents for verification

-- ============================================
-- PARTNER VERIFICATION DOCUMENTS
-- Stores uploaded verification documents
-- ============================================
CREATE TABLE IF NOT EXISTS partner_verification_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    partner_id INT NOT NULL,

    -- Document details
    document_type ENUM('cvr', 'insurance', 'license', 'portfolio', 'reference', 'other') NOT NULL,
    document_name VARCHAR(255) NOT NULL COMMENT 'Original filename',
    file_path VARCHAR(500) NOT NULL COMMENT 'Path to stored file',
    file_size INT NOT NULL COMMENT 'File size in bytes',
    mime_type VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,

    -- Verification status
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    reviewed_by INT DEFAULT NULL,
    reviewed_at TIMESTAMP NULL,
    review_note TEXT DEFAULT NULL,

    -- Timestamps
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Foreign keys
    FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES accounts(id) ON DELETE SET NULL,

    -- Indexes
    INDEX idx_doc_partner (partner_id),
    INDEX idx_doc_status (status),
    INDEX idx_doc_type (document_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Add verification columns to partners table
-- ============================================
ALTER TABLE partners ADD COLUMN is_verified TINYINT(1) DEFAULT 0 COMMENT 'Has completed verification';
ALTER TABLE partners ADD COLUMN verified_at TIMESTAMP NULL COMMENT 'When verification was completed';
ALTER TABLE partners ADD COLUMN verification_level ENUM('none', 'basic', 'full') DEFAULT 'none' COMMENT 'Verification tier';
ALTER TABLE partners ADD COLUMN cvr_number VARCHAR(20) DEFAULT NULL COMMENT 'Danish CVR number';

-- ============================================
-- VERIFICATION REQUIREMENTS
-- Defines what documents are required per level
-- ============================================
CREATE TABLE IF NOT EXISTS partner_verification_requirements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    verification_level ENUM('basic', 'full') NOT NULL,
    document_type VARCHAR(50) NOT NULL,
    is_required TINYINT(1) DEFAULT 1,
    description VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,

    UNIQUE KEY unique_level_type (verification_level, document_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default requirements
INSERT INTO partner_verification_requirements (verification_level, document_type, is_required, description, sort_order) VALUES
('basic', 'cvr', 1, 'CVR-registreringsbevis', 1),
('basic', 'insurance', 0, 'Erhvervsansvarsforsikring', 2),
('full', 'cvr', 1, 'CVR-registreringsbevis', 1),
('full', 'insurance', 1, 'Erhvervsansvarsforsikring', 2),
('full', 'license', 0, 'Relevante tilladelser eller certifikater', 3),
('full', 'reference', 0, 'Kundereference eller anbefalingsbrev', 4)
ON DUPLICATE KEY UPDATE description = VALUES(description);

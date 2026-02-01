-- Partner Reviews & Ratings System
-- Migration: 005_partner_reviews.sql

-- ============================================
-- PARTNER REVIEWS
-- Stores reviews from customers
-- ============================================
CREATE TABLE IF NOT EXISTS partner_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    partner_id INT NOT NULL,
    booking_id INT DEFAULT NULL COMMENT 'Link to booking if verified purchase',
    account_id INT DEFAULT NULL COMMENT 'Reviewer account if logged in',

    -- Reviewer info
    reviewer_name VARCHAR(255) NOT NULL,
    reviewer_email VARCHAR(255) NOT NULL,

    -- Rating (1-5 stars)
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),

    -- Review content
    title VARCHAR(255) DEFAULT NULL,
    review_text TEXT NOT NULL,

    -- Detailed ratings (optional)
    rating_quality TINYINT DEFAULT NULL CHECK (rating_quality BETWEEN 1 AND 5),
    rating_communication TINYINT DEFAULT NULL CHECK (rating_communication BETWEEN 1 AND 5),
    rating_value TINYINT DEFAULT NULL CHECK (rating_value BETWEEN 1 AND 5),

    -- Event details
    event_type VARCHAR(100) DEFAULT NULL COMMENT 'Wedding, Birthday, etc.',
    event_date DATE DEFAULT NULL,

    -- Moderation
    status ENUM('pending', 'approved', 'rejected', 'flagged') NOT NULL DEFAULT 'pending',
    is_verified TINYINT(1) DEFAULT 0 COMMENT 'Verified purchase/booking',
    moderation_note TEXT DEFAULT NULL,
    moderated_by INT DEFAULT NULL,
    moderated_at TIMESTAMP NULL,

    -- Partner response
    partner_response TEXT DEFAULT NULL,
    partner_responded_at TIMESTAMP NULL,

    -- Helpful votes
    helpful_count INT DEFAULT 0,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign keys
    FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES partner_bookings(id) ON DELETE SET NULL,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (moderated_by) REFERENCES accounts(id) ON DELETE SET NULL,

    -- Indexes
    INDEX idx_review_partner (partner_id),
    INDEX idx_review_status (status),
    INDEX idx_review_rating (rating),
    INDEX idx_review_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- REVIEW HELPFUL VOTES
-- Track who found reviews helpful
-- ============================================
CREATE TABLE IF NOT EXISTS partner_review_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    review_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    account_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (review_id) REFERENCES partner_reviews(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL,
    UNIQUE KEY unique_vote (review_id, ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Add rating columns to partners table
-- ============================================
ALTER TABLE partners ADD COLUMN average_rating DECIMAL(3,2) DEFAULT NULL COMMENT 'Cached average rating';
ALTER TABLE partners ADD COLUMN review_count INT DEFAULT 0 COMMENT 'Cached review count';

-- ============================================
-- VIEW: Partner Rating Summary
-- ============================================
CREATE OR REPLACE VIEW v_partner_ratings AS
SELECT
    p.id as partner_id,
    p.company_name,
    COUNT(pr.id) as total_reviews,
    AVG(pr.rating) as average_rating,
    SUM(CASE WHEN pr.rating = 5 THEN 1 ELSE 0 END) as five_star,
    SUM(CASE WHEN pr.rating = 4 THEN 1 ELSE 0 END) as four_star,
    SUM(CASE WHEN pr.rating = 3 THEN 1 ELSE 0 END) as three_star,
    SUM(CASE WHEN pr.rating = 2 THEN 1 ELSE 0 END) as two_star,
    SUM(CASE WHEN pr.rating = 1 THEN 1 ELSE 0 END) as one_star,
    AVG(pr.rating_quality) as avg_quality,
    AVG(pr.rating_communication) as avg_communication,
    AVG(pr.rating_value) as avg_value
FROM partners p
LEFT JOIN partner_reviews pr ON p.id = pr.partner_id AND pr.status = 'approved'
GROUP BY p.id, p.company_name;

-- ============================================
-- Trigger to update partner rating cache
-- ============================================
DROP TRIGGER IF EXISTS update_partner_rating_after_insert;
DROP TRIGGER IF EXISTS update_partner_rating_after_update;

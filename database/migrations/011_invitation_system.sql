-- Invitation System Migration
-- Migration: 011_invitation_system.sql
-- Adds invitation templates, configs, images, and email tracking

-- ============================================
-- INVITATION TEMPLATES (Seed Data)
-- ============================================

CREATE TABLE IF NOT EXISTS invitation_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    preview_image VARCHAR(255),
    layout_style ENUM('split', 'centered', 'fullscreen', 'minimal', 'classic') DEFAULT 'split',
    font_style ENUM('elegant', 'modern', 'playful', 'traditional', 'minimal') DEFAULT 'elegant',
    color_scheme JSON NOT NULL,
    sections_config JSON NOT NULL,
    category VARCHAR(50) DEFAULT 'general',
    is_blank TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_template_active (is_active),
    INDEX idx_template_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TEMPLATE EVENT TYPE RECOMMENDATIONS
-- ============================================

CREATE TABLE IF NOT EXISTS template_event_types (
    template_id INT NOT NULL,
    event_type_id INT NOT NULL,
    is_recommended TINYINT(1) DEFAULT 0,
    PRIMARY KEY (template_id, event_type_id),
    FOREIGN KEY (template_id) REFERENCES invitation_templates(id) ON DELETE CASCADE,
    INDEX idx_event_type_recommended (event_type_id, is_recommended)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- EVENT INVITATION CONFIGURATION
-- ============================================

CREATE TABLE IF NOT EXISTS invitation_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL UNIQUE,
    template_id INT DEFAULT NULL,
    layout_style ENUM('split', 'centered', 'fullscreen', 'minimal', 'classic') DEFAULT 'split',
    font_style ENUM('elegant', 'modern', 'playful', 'traditional', 'minimal') DEFAULT 'elegant',
    color_primary VARCHAR(7) DEFAULT '#1A1A1A',
    color_secondary VARCHAR(7) DEFAULT '#8FA583',
    color_accent VARCHAR(7) DEFAULT '#B8923D',
    color_text VARCHAR(7) DEFAULT '#1A1A1A',
    color_background VARCHAR(7) DEFAULT '#FAF9F7',
    greeting_template VARCHAR(255) DEFAULT 'Kære {guest_name}',
    headline_text VARCHAR(255) DEFAULT NULL,
    invitation_message TEXT,
    closing_text VARCHAR(255) DEFAULT NULL,
    show_countdown TINYINT(1) DEFAULT 1,
    show_map TINYINT(1) DEFAULT 0,
    show_schedule TINYINT(1) DEFAULT 1,
    show_rsvp TINYINT(1) DEFAULT 1,
    sections_order JSON DEFAULT NULL,
    custom_css TEXT DEFAULT NULL,
    is_published TINYINT(1) DEFAULT 0,
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES invitation_templates(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INVITATION IMAGES (Gallery)
-- ============================================

CREATE TABLE IF NOT EXISTS invitation_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255),
    file_size INT DEFAULT 0,
    mime_type VARCHAR(100),
    image_role ENUM('hero', 'gallery', 'background') DEFAULT 'gallery',
    position INT DEFAULT 0,
    alt_text VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_image_event (event_id),
    INDEX idx_image_role (event_id, image_role),
    INDEX idx_image_position (event_id, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- EMAIL SENDING LOG
-- ============================================

CREATE TABLE IF NOT EXISTS invitation_emails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    guest_id INT NOT NULL,
    email_address VARCHAR(255) NOT NULL,
    email_type ENUM('invitation', 'reminder', 'update') DEFAULT 'invitation',
    status ENUM('pending', 'queued', 'sent', 'delivered', 'opened', 'clicked', 'bounced', 'failed') DEFAULT 'pending',
    external_id VARCHAR(255) DEFAULT NULL,
    subject VARCHAR(255),
    sent_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    opened_at TIMESTAMP NULL,
    clicked_at TIMESTAMP NULL,
    error_message TEXT,
    metadata JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (guest_id) REFERENCES guests(id) ON DELETE CASCADE,
    INDEX idx_email_event (event_id),
    INDEX idx_email_guest (guest_id),
    INDEX idx_email_status (status),
    INDEX idx_email_external (external_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- NEW EVENT TYPES
-- ============================================

INSERT IGNORE INTO event_types (name, slug, icon, has_secondary_person, person_label) VALUES
('Begravelse/Mindehøjtid', 'funeral', 'candle', 0, 'Afdøde'),
('Studenterfest', 'graduation', 'graduation-cap', 0, 'Student'),
('Polterabend', 'bachelor-party', 'champagne', 0, 'Hovedperson'),
('Reception', 'reception', 'building', 1, 'Vært');

-- ============================================
-- UPDATE PLAN FEATURES FOR INVITATIONS
-- ============================================

UPDATE plans SET features = JSON_SET(
    COALESCE(features, '{}'),
    '$.invitations', true,
    '$.invitation_emails', 10
) WHERE slug = 'free' OR slug = 'gratis';

UPDATE plans SET features = JSON_SET(
    COALESCE(features, '{}'),
    '$.invitations', true,
    '$.invitation_emails', 100,
    '$.invitation_templates', true
) WHERE slug = 'basis' OR slug = 'standard';

UPDATE plans SET features = JSON_SET(
    COALESCE(features, '{}'),
    '$.invitations', true,
    '$.invitation_emails', -1,
    '$.invitation_templates', true,
    '$.invitation_custom_css', true
) WHERE slug = 'pro' OR slug = 'premium';

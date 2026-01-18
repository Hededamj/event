-- ========================================
-- EVENT PLATFORM DATABASE SCHEMA
-- ========================================

-- Create database (run this separately if needed)
-- CREATE DATABASE events_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE events_platform;

-- ========================================
-- EVENTS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL COMMENT 'Event name, e.g., "Sofies Konfirmation"',
    event_date DATE NOT NULL,
    event_time TIME DEFAULT '12:00:00',
    location VARCHAR(255),
    theme ENUM('girl', 'boy') DEFAULT 'girl',
    welcome_text TEXT COMMENT 'Welcome message shown to guests',
    confirmand_name VARCHAR(100) COMMENT 'Name of the person being celebrated',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_event_date (event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- USERS TABLE (Organizers)
-- ========================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'organizer', 'confirmand') DEFAULT 'organizer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    UNIQUE KEY unique_email_event (email, event_id),
    INDEX idx_user_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- GUESTS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS guests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(20),
    unique_code CHAR(6) NOT NULL COMMENT '6-digit access code',
    rsvp_status ENUM('pending', 'yes', 'no') DEFAULT 'pending',
    rsvp_date TIMESTAMP NULL,
    adults_count INT DEFAULT 1,
    children_count INT DEFAULT 0,
    dietary_notes TEXT COMMENT 'Allergies, dietary restrictions',
    notes TEXT COMMENT 'Internal notes from organizer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    UNIQUE KEY unique_code_event (unique_code, event_id),
    INDEX idx_guest_event (event_id),
    INDEX idx_guest_code (unique_code),
    INDEX idx_guest_rsvp (rsvp_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- WISHLIST ITEMS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS wishlist_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) COMMENT 'Approximate price',
    link VARCHAR(500) COMMENT 'Link to product',
    image_url VARCHAR(500),
    reserved_by_guest_id INT NULL,
    reserved_at TIMESTAMP NULL,
    purchased BOOLEAN DEFAULT FALSE,
    priority INT DEFAULT 0 COMMENT 'For sorting, higher = more wanted',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (reserved_by_guest_id) REFERENCES guests(id) ON DELETE SET NULL,
    INDEX idx_wishlist_event (event_id),
    INDEX idx_wishlist_reserved (reserved_by_guest_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- CHECKLIST ITEMS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS checklist_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    category VARCHAR(50) DEFAULT 'general' COMMENT 'e.g., mad, pynt, praktisk',
    task VARCHAR(255) NOT NULL,
    due_date DATE,
    completed BOOLEAN DEFAULT FALSE,
    completed_at TIMESTAMP NULL,
    assigned_to VARCHAR(100) COMMENT 'Name of person responsible',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_checklist_event (event_id),
    INDEX idx_checklist_completed (completed),
    INDEX idx_checklist_due (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- MENU ITEMS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    course ENUM('starter', 'main', 'dessert', 'drink', 'snack', 'other') NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_menu_event (event_id),
    INDEX idx_menu_course (course)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- SCHEDULE ITEMS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS schedule_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    time TIME NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_schedule_event (event_id),
    INDEX idx_schedule_time (time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- PHOTOS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    uploaded_by_guest_id INT,
    uploaded_by_user_id INT,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255),
    caption TEXT,
    approved BOOLEAN DEFAULT TRUE COMMENT 'For moderation if needed',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by_guest_id) REFERENCES guests(id) ON DELETE SET NULL,
    FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_photos_event (event_id),
    INDEX idx_photos_uploaded (uploaded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- BUDGET ITEMS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS budget_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    category VARCHAR(50) NOT NULL COMMENT 'e.g., mad, pynt, lokale',
    description VARCHAR(255) NOT NULL,
    estimated DECIMAL(10,2) DEFAULT 0 COMMENT 'Estimated cost',
    actual DECIMAL(10,2) DEFAULT 0 COMMENT 'Actual cost',
    paid BOOLEAN DEFAULT FALSE,
    paid_date DATE,
    notes TEXT,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_budget_event (event_id),
    INDEX idx_budget_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

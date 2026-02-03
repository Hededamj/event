-- Migration 012: Timeline Memories
-- Minde-tidslinje feature: Gæster kan uploade billeder med årstal til en tidslinje

-- Add timeline columns to events table
ALTER TABLE events ADD COLUMN timeline_enabled TINYINT(1) DEFAULT 0;
ALTER TABLE events ADD COLUMN timeline_start_date DATE NULL;
ALTER TABLE events ADD COLUMN timeline_label VARCHAR(100) NULL;

-- Create timeline_memories table
CREATE TABLE IF NOT EXISTS timeline_memories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    uploaded_by_guest_id INT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NULL,
    caption TEXT,
    memory_year INT NOT NULL,
    memory_description VARCHAR(255),
    approved TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by_guest_id) REFERENCES guests(id) ON DELETE SET NULL,
    INDEX idx_memories_event_year (event_id, memory_year),
    INDEX idx_memories_event_approved (event_id, approved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

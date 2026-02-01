-- ========================================
-- ADD EMAIL TO TOASTMASTER ACCESS
-- ========================================

-- Add email field to toastmaster_access table
ALTER TABLE toastmaster_access
ADD COLUMN IF NOT EXISTS email VARCHAR(255) DEFAULT NULL
AFTER name;

-- Add index for email lookup
ALTER TABLE toastmaster_access ADD INDEX IF NOT EXISTS idx_tm_email (email);

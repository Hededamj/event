-- Add QR token for public access to photos/memories without guest login
ALTER TABLE events ADD COLUMN qr_token VARCHAR(24) NULL AFTER slug;

-- Generate tokens for existing events
UPDATE events SET qr_token = SUBSTRING(MD5(CONCAT(id, slug, RAND(), NOW())), 1, 16) WHERE qr_token IS NULL;

-- Make it non-nullable and unique after populating
ALTER TABLE events MODIFY COLUMN qr_token VARCHAR(24) NOT NULL;
CREATE UNIQUE INDEX idx_events_qr_token ON events (qr_token);

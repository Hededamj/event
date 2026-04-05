-- Registration mode: invite (default, personal links) or open (self-registration)
ALTER TABLE events ADD COLUMN registration_mode ENUM('invite', 'open') DEFAULT 'invite' AFTER status;

-- Track self-registered guests
ALTER TABLE guests ADD COLUMN self_registered TINYINT(1) NOT NULL DEFAULT 0 AFTER notes;

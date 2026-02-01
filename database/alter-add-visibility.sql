-- Add visibility settings to events table
-- Run this on existing database to add the new columns

ALTER TABLE events
ADD COLUMN show_wishlist BOOLEAN DEFAULT TRUE COMMENT 'Show wishlist to guests' AFTER confirmand_name,
ADD COLUMN show_menu BOOLEAN DEFAULT TRUE COMMENT 'Show menu to guests' AFTER show_wishlist,
ADD COLUMN show_schedule BOOLEAN DEFAULT TRUE COMMENT 'Show schedule/program to guests' AFTER show_menu,
ADD COLUMN show_photos BOOLEAN DEFAULT TRUE COMMENT 'Show photo gallery to guests' AFTER show_schedule;

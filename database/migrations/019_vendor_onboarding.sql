-- Migration 019: Vendor Onboarding
-- Adds onboarding_completed flag to vendors table.
-- Existing vendors with categories assigned are marked as onboarded.

ALTER TABLE vendors ADD COLUMN onboarding_completed TINYINT(1) NOT NULL DEFAULT 0;

-- Backfill: mark existing vendors who already have categories as onboarded
UPDATE vendors v
SET v.onboarding_completed = 1
WHERE EXISTS (
    SELECT 1 FROM vendor_category_links vcl WHERE vcl.vendor_id = v.id
);

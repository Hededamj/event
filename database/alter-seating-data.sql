-- ========================================
-- ADD SEATING PLAN DATA TO GUESTS TABLE
-- ========================================

-- Add relation type for grouping (works for weddings, confirmations, etc.)
-- family_a = Bride's family / Confirmand's mom's family
-- family_b = Groom's family / Confirmand's dad's family
-- friends = Friends
-- colleagues = Colleagues/classmates
-- other = Other guests
ALTER TABLE guests
ADD COLUMN IF NOT EXISTS relation_type ENUM('family_a', 'family_b', 'friends', 'colleagues', 'other') DEFAULT 'other'
AFTER dietary_notes;

-- Add VIP flag for high table placement
ALTER TABLE guests
ADD COLUMN IF NOT EXISTS is_vip BOOLEAN DEFAULT FALSE
AFTER relation_type;

-- Add couple_group to link partners (guests in same couple_group will be kept together or separated)
ALTER TABLE guests
ADD COLUMN IF NOT EXISTS couple_group INT DEFAULT NULL
AFTER is_vip;

-- guest_names JSON structure is extended to support:
-- Old format: ["John", "Jane"]
-- New format: [{"name": "John", "gender": "male"}, {"name": "Jane", "gender": "female"}]
-- The code will handle both formats for backwards compatibility

-- Add index for seating queries
ALTER TABLE guests ADD INDEX IF NOT EXISTS idx_guest_relation (relation_type);
ALTER TABLE guests ADD INDEX IF NOT EXISTS idx_guest_vip (is_vip);

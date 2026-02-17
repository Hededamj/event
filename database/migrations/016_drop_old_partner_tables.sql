-- ============================================================
-- Migration 016: Remove old partner marketplace tables
-- ============================================================
-- These tables are replaced by the new vendor/subcontractor
-- module (migration 015). Safe to drop after data migration
-- or on fresh installs.
-- ============================================================

DROP TABLE IF EXISTS partner_inquiries;
DROP TABLE IF EXISTS partner_commissions;
DROP TABLE IF EXISTS partner_reviews;
DROP TABLE IF EXISTS partner_verification;
DROP TABLE IF EXISTS partner_gallery;
DROP TABLE IF EXISTS partners;
DROP TABLE IF EXISTS partner_categories;

-- ============================================================
-- Migration 016: Remove old partner marketplace tables
-- ============================================================
-- These tables are replaced by the new vendor/subcontractor
-- module (migration 015). Safe to drop after data migration
-- or on fresh installs.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS partner_verification_documents;
DROP TABLE IF EXISTS partner_commission_settings;
DROP TABLE IF EXISTS partner_payouts;
DROP TABLE IF EXISTS partner_payout_items;
DROP TABLE IF EXISTS partner_review_votes;
DROP TABLE IF EXISTS partner_bookings;
DROP TABLE IF EXISTS partner_inquiries;
DROP TABLE IF EXISTS partner_commissions;
DROP TABLE IF EXISTS partner_reviews;
DROP TABLE IF EXISTS partner_verification;
DROP TABLE IF EXISTS partner_gallery;
DROP TABLE IF EXISTS partners;
DROP TABLE IF EXISTS partner_categories;
SET FOREIGN_KEY_CHECKS = 1;

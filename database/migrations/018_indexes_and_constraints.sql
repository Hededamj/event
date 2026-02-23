-- ============================================
-- Migration 018: Database Indexes & Constraints
-- Adds missing foreign keys, performance indexes,
-- and CHECK constraints for data integrity.
-- ============================================

-- ============================================
-- FOREIGN KEYS (missing relationships)
-- Using IF NOT EXISTS pattern via procedure
-- ============================================

-- audit_log → accounts
ALTER TABLE audit_log
ADD CONSTRAINT fk_audit_account
FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL;

-- audit_log → events
ALTER TABLE audit_log
ADD CONSTRAINT fk_audit_event
FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL;

-- seating_assignments → guests (optional link)
ALTER TABLE seating_assignments
ADD CONSTRAINT fk_seating_guest
FOREIGN KEY (guest_id) REFERENCES guests(id) ON DELETE SET NULL;

-- ============================================
-- PERFORMANCE INDEXES
-- ============================================

-- Payment history: Revenue dashboard (eliminates N+1 loop)
CREATE INDEX idx_payment_history_status_created
ON payment_history(status, created_at);

-- Vendor services: Marketplace category pricing (eliminates correlated subquery)
CREATE INDEX idx_vendor_services_vendor_active
ON vendor_services(vendor_id, is_active, price_from);

-- Vendor category links: Category browsing JOIN
CREATE INDEX idx_vendor_catlinks_category
ON vendor_category_links(category_id, vendor_id);

-- Vendors: Status filtering on marketplace
CREATE INDEX idx_vendors_status_active
ON vendors(status, is_active);

-- Bookings: Event and vendor lookup
CREATE INDEX idx_bookings_event_status
ON bookings(event_id, status);

CREATE INDEX idx_bookings_vendor_status
ON bookings(vendor_id, status);

-- Booking messages: Unread message queries
CREATE INDEX idx_booking_messages_read
ON booking_messages(booking_id, is_read);

-- Audit log: Account and event lookup
CREATE INDEX idx_audit_log_account
ON audit_log(account_id, created_at);

CREATE INDEX idx_audit_log_event
ON audit_log(event_id, action);

-- Guests: RSVP status filtering
CREATE INDEX idx_guests_event_rsvp
ON guests(event_id, rsvp_status);

-- Toastmaster items: Event + status filtering
CREATE INDEX idx_toastmaster_event_status
ON toastmaster_items(event_id, status);

-- Schedule items: Time ordering
CREATE INDEX idx_schedule_event_time
ON schedule_items(event_id, `time`);

-- ============================================
-- CHECK CONSTRAINTS (MySQL 8.0.16+)
-- Silently ignored on older versions
-- ============================================

-- Review ratings must be 1-5
ALTER TABLE vendor_reviews
ADD CONSTRAINT check_review_rating
CHECK (rating BETWEEN 1 AND 5);

-- Service prices must be non-negative
ALTER TABLE vendor_services
ADD CONSTRAINT check_service_prices
CHECK (price_from >= 0 AND (price_to IS NULL OR price_to >= price_from));

-- Booking amounts must be non-negative
ALTER TABLE bookings
ADD CONSTRAINT check_booking_amounts
CHECK (
    (quoted_price IS NULL OR quoted_price >= 0)
    AND (depositum_amount IS NULL OR depositum_amount >= 0)
    AND (commission_amount IS NULL OR commission_amount >= 0)
    AND (vendor_payout IS NULL OR vendor_payout >= 0)
);

-- Payment amounts must be non-negative
ALTER TABLE payment_history
ADD CONSTRAINT check_payment_amount
CHECK (amount >= 0);

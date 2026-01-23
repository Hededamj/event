-- Migration 002: Add Stripe customer ID to accounts
-- Allows linking accounts directly to Stripe customers

ALTER TABLE accounts
    ADD COLUMN IF NOT EXISTS stripe_customer_id VARCHAR(100) AFTER avatar_url;

-- Add index for faster lookups
ALTER TABLE accounts ADD INDEX IF NOT EXISTS idx_stripe_customer_id (stripe_customer_id);

-- Update payment_history table to use 'paid' status instead of 'succeeded'
ALTER TABLE payment_history
    MODIFY COLUMN status ENUM('paid', 'pending', 'failed', 'refunded', 'succeeded') DEFAULT 'pending';

-- Add paid_at column to payment_history
ALTER TABLE payment_history
    ADD COLUMN IF NOT EXISTS paid_at TIMESTAMP NULL AFTER receipt_url;

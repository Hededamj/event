<?php
/**
 * Stripe Configuration
 *
 * Set these environment variables in production:
 * - STRIPE_SECRET_KEY
 * - STRIPE_PUBLISHABLE_KEY
 * - STRIPE_WEBHOOK_SECRET
 */

// Stripe API Keys
define('STRIPE_SECRET_KEY', getenv('STRIPE_SECRET_KEY') ?: 'sk_test_XXXXXXXX');
define('STRIPE_PUBLISHABLE_KEY', getenv('STRIPE_PUBLISHABLE_KEY') ?: 'pk_test_XXXXXXXX');
define('STRIPE_WEBHOOK_SECRET', getenv('STRIPE_WEBHOOK_SECRET') ?: 'whsec_XXXXXXXX');

// Currency
define('STRIPE_CURRENCY', 'dkk');

// Stripe Price IDs for each plan (set these after creating products in Stripe Dashboard)
// Format: 'plan_slug' => 'price_id'
define('STRIPE_PRICE_IDS', [
    'basis' => getenv('STRIPE_PRICE_BASIS') ?: 'price_basis_monthly',
    'premium' => getenv('STRIPE_PRICE_PREMIUM') ?: 'price_premium_monthly',
    'pro' => getenv('STRIPE_PRICE_PRO') ?: 'price_pro_monthly'
]);

// Plan prices in DKK (for display, should match Stripe prices)
define('PLAN_PRICES', [
    'gratis' => 0,
    'basis' => 9900,    // 99.00 DKK (stored in smallest unit)
    'premium' => 19900, // 199.00 DKK
    'pro' => 49900      // 499.00 DKK
]);

// Success and Cancel URLs for Stripe Checkout
define('STRIPE_SUCCESS_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/app/account/subscription.php?success=1');
define('STRIPE_CANCEL_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/app/account/subscription.php?cancelled=1');

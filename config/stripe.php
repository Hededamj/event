<?php
/**
 * Stripe Configuration
 *
 * Credentials loaded from .env file via env() helper.
 */

require_once __DIR__ . '/../includes/env.php';

// Stripe API Keys
define('STRIPE_SECRET_KEY', env('STRIPE_SECRET_KEY', ''));
define('STRIPE_PUBLISHABLE_KEY', env('STRIPE_PUBLISHABLE_KEY', ''));
define('STRIPE_WEBHOOK_SECRET', env('STRIPE_WEBHOOK_SECRET', ''));
define('STRIPE_CONNECT_WEBHOOK_SECRET', env('STRIPE_CONNECT_WEBHOOK_SECRET', ''));

// Currency
define('STRIPE_CURRENCY', 'dkk');

// Stripe Price IDs for each plan
define('STRIPE_PRICE_IDS', [
    'basis' => env('STRIPE_PRICE_BASIS', 'price_basis_monthly'),
    'premium' => env('STRIPE_PRICE_PREMIUM', 'price_premium_monthly'),
    'pro' => env('STRIPE_PRICE_PRO', 'price_pro_monthly')
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

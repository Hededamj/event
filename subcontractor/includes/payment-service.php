<?php
/**
 * Payment Service (Stripe Connect)
 * Handles Stripe Connect payment functions for vendor/subcontractor payments.
 * Uses the platform's stripeRequest() helper for all Stripe API calls.
 */

require_once __DIR__ . '/../../config/stripe.php';
require_once __DIR__ . '/../../includes/stripe.php';

// ============================================================
// 1. createConnectAccount
// ============================================================

/**
 * Create a Stripe Connect Express account for a vendor.
 *
 * @param string $email       Vendor's email address
 * @param string $companyName Vendor's company/business name
 * @return string|null        Stripe account ID (acct_xxx) or null on failure
 */
function createConnectAccount(string $email, string $companyName): ?string {
    $result = stripeRequest('/accounts', 'POST', [
        'type' => 'express',
        'email' => $email,
        'business_profile' => [
            'name' => $companyName
        ],
        'capabilities' => [
            'card_payments' => ['requested' => 'true'],
            'transfers' => ['requested' => 'true']
        ]
    ]);

    if (!$result || empty($result['id'])) {
        error_log("Failed to create Connect account for: $email");
        return null;
    }

    return $result['id'];
}

// ============================================================
// 2. createOnboardingLink
// ============================================================

/**
 * Create a Stripe Connect onboarding link for a vendor to complete setup.
 *
 * @param string $accountId  Stripe Connect account ID
 * @param string $returnUrl  URL to redirect to after onboarding completes
 * @param string $refreshUrl URL to redirect to if the link expires
 * @return string|null       Onboarding URL or null on failure
 */
function createOnboardingLink(string $accountId, string $returnUrl, string $refreshUrl): ?string {
    $result = stripeRequest('/account_links', 'POST', [
        'account' => $accountId,
        'type' => 'account_onboarding',
        'return_url' => $returnUrl,
        'refresh_url' => $refreshUrl
    ]);

    if (!$result || empty($result['url'])) {
        error_log("Failed to create onboarding link for account: $accountId");
        return null;
    }

    return $result['url'];
}

// ============================================================
// 3. isOnboardingComplete
// ============================================================

/**
 * Check whether a Stripe Connect account has completed onboarding.
 *
 * @param string $accountId Stripe Connect account ID
 * @return bool             True if charges and payouts are both enabled
 */
function isOnboardingComplete(string $accountId): bool {
    $result = stripeRequest('/accounts/' . $accountId);

    if (!$result) {
        error_log("Failed to retrieve account status for: $accountId");
        return false;
    }

    return !empty($result['charges_enabled']) && !empty($result['payouts_enabled']);
}

// ============================================================
// 4. createDepositumCheckout
// ============================================================

/**
 * Create a Stripe Checkout Session for a booking depositum payment.
 * Uses Stripe Connect destination charges to split payment between platform and vendor.
 *
 * @param array  $booking    Booking data with keys: id, depositum_amount, commission_amount,
 *                           vendor_company_name, vendor_stripe_account_id
 * @param string $successUrl URL to redirect to on successful payment
 * @param string $cancelUrl  URL to redirect to if payment is cancelled
 * @return array|null        ['session_id' => ..., 'url' => ...] or null on failure
 */
function createDepositumCheckout(array $booking, string $successUrl, string $cancelUrl): ?array {
    $result = stripeRequest('/checkout/sessions', 'POST', [
        'mode' => 'payment',
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => 'dkk',
                'unit_amount' => (int) ($booking['depositum_amount'] * 100),
                'product_data' => [
                    'name' => 'Depositum: ' . $booking['vendor_company_name']
                ]
            ],
            'quantity' => 1
        ]],
        'payment_intent_data' => [
            'application_fee_amount' => (int) ($booking['commission_amount'] * 100),
            'transfer_data' => [
                'destination' => $booking['vendor_stripe_account_id']
            ]
        ],
        'success_url' => $successUrl,
        'cancel_url' => $cancelUrl,
        'metadata' => [
            'booking_id' => $booking['id']
        ]
    ]);

    if (!$result || empty($result['id'])) {
        error_log("Failed to create depositum checkout for booking: " . ($booking['id'] ?? 'unknown'));
        return null;
    }

    return [
        'session_id' => $result['id'],
        'url' => $result['url']
    ];
}

// ============================================================
// 5. refundPayment
// ============================================================

/**
 * Issue a full refund for a payment intent.
 *
 * @param string $paymentIntentId Stripe payment intent ID
 * @return array|null             Refund data or null on failure
 */
function refundPayment(string $paymentIntentId): ?array {
    $result = stripeRequest('/refunds', 'POST', [
        'payment_intent' => $paymentIntentId
    ]);

    if (!$result || empty($result['id'])) {
        error_log("Failed to refund payment intent: $paymentIntentId");
        return null;
    }

    return $result;
}

// ============================================================
// 6. reverseTransfer
// ============================================================

/**
 * Reverse a transfer to a connected account (claws back vendor payment).
 *
 * @param string $transferId Stripe transfer ID
 * @return array|null        Reversal data or null on failure
 */
function reverseTransfer(string $transferId): ?array {
    $result = stripeRequest('/transfers/' . $transferId . '/reversals', 'POST');

    if (!$result || empty($result['id'])) {
        error_log("Failed to reverse transfer: $transferId");
        return null;
    }

    return $result;
}

// ============================================================
// 7. getPaymentIntent
// ============================================================

/**
 * Retrieve a payment intent from Stripe.
 *
 * @param string $paymentIntentId Stripe payment intent ID
 * @return array|null             Payment intent data or null on failure
 */
function getPaymentIntent(string $paymentIntentId): ?array {
    $result = stripeRequest('/payment_intents/' . $paymentIntentId);

    if (!$result || empty($result['id'])) {
        error_log("Failed to retrieve payment intent: $paymentIntentId");
        return null;
    }

    return $result;
}

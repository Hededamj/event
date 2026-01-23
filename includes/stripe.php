<?php
/**
 * Stripe Helper Functions
 *
 * Handles Stripe API interactions for subscriptions and payments.
 * Uses Stripe's REST API directly without requiring the PHP SDK.
 */

require_once __DIR__ . '/../config/stripe.php';

/**
 * Make a request to Stripe API
 *
 * @param string $endpoint API endpoint (e.g., '/customers')
 * @param string $method HTTP method
 * @param array $data Request data
 * @return array|null Response data or null on error
 */
function stripeRequest($endpoint, $method = 'GET', $data = []) {
    $url = 'https://api.stripe.com/v1' . $endpoint;

    $headers = [
        'Authorization: Bearer ' . STRIPE_SECRET_KEY,
        'Content-Type: application/x-www-form-urlencoded'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        error_log('Stripe API request failed: ' . curl_error($ch));
        return null;
    }

    $result = json_decode($response, true);

    if ($httpCode >= 400) {
        error_log('Stripe API error: ' . json_encode($result));
        return null;
    }

    return $result;
}

/**
 * Create or get Stripe customer for account
 *
 * @param PDO $db Database connection
 * @param int $accountId Account ID
 * @return string|null Stripe customer ID
 */
function getOrCreateStripeCustomer($db, $accountId) {
    // Check if account already has a Stripe customer ID
    $stmt = $db->prepare("SELECT stripe_customer_id, email, name FROM accounts WHERE id = ?");
    $stmt->execute([$accountId]);
    $account = $stmt->fetch();

    if (!$account) {
        return null;
    }

    if (!empty($account['stripe_customer_id'])) {
        return $account['stripe_customer_id'];
    }

    // Create new Stripe customer
    $customer = stripeRequest('/customers', 'POST', [
        'email' => $account['email'],
        'name' => $account['name'],
        'metadata' => [
            'account_id' => $accountId
        ]
    ]);

    if (!$customer || empty($customer['id'])) {
        return null;
    }

    // Save customer ID to account
    $stmt = $db->prepare("UPDATE accounts SET stripe_customer_id = ? WHERE id = ?");
    $stmt->execute([$customer['id'], $accountId]);

    return $customer['id'];
}

/**
 * Create Stripe Checkout Session for subscription
 *
 * @param PDO $db Database connection
 * @param int $accountId Account ID
 * @param string $planSlug Plan to subscribe to
 * @return array|null Checkout session data with URL
 */
function createCheckoutSession($db, $accountId, $planSlug) {
    $priceIds = STRIPE_PRICE_IDS;

    if (!isset($priceIds[$planSlug])) {
        error_log("Invalid plan slug: $planSlug");
        return null;
    }

    $customerId = getOrCreateStripeCustomer($db, $accountId);
    if (!$customerId) {
        return null;
    }

    $session = stripeRequest('/checkout/sessions', 'POST', [
        'customer' => $customerId,
        'mode' => 'subscription',
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price' => $priceIds[$planSlug],
            'quantity' => 1
        ]],
        'success_url' => STRIPE_SUCCESS_URL . '&session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => STRIPE_CANCEL_URL,
        'metadata' => [
            'account_id' => $accountId,
            'plan_slug' => $planSlug
        ],
        'subscription_data' => [
            'metadata' => [
                'account_id' => $accountId,
                'plan_slug' => $planSlug
            ]
        ]
    ]);

    return $session;
}

/**
 * Create Stripe Customer Portal session
 *
 * @param PDO $db Database connection
 * @param int $accountId Account ID
 * @param string $returnUrl URL to return to after portal
 * @return array|null Portal session data with URL
 */
function createPortalSession($db, $accountId, $returnUrl) {
    $stmt = $db->prepare("SELECT stripe_customer_id FROM accounts WHERE id = ?");
    $stmt->execute([$accountId]);
    $account = $stmt->fetch();

    if (!$account || empty($account['stripe_customer_id'])) {
        return null;
    }

    $session = stripeRequest('/billing_portal/sessions', 'POST', [
        'customer' => $account['stripe_customer_id'],
        'return_url' => $returnUrl
    ]);

    return $session;
}

/**
 * Get subscription details from Stripe
 *
 * @param string $subscriptionId Stripe subscription ID
 * @return array|null Subscription data
 */
function getStripeSubscription($subscriptionId) {
    return stripeRequest('/subscriptions/' . $subscriptionId);
}

/**
 * Cancel subscription at period end
 *
 * @param string $subscriptionId Stripe subscription ID
 * @return bool Success
 */
function cancelSubscription($subscriptionId) {
    $result = stripeRequest('/subscriptions/' . $subscriptionId, 'POST', [
        'cancel_at_period_end' => 'true'
    ]);

    return $result !== null;
}

/**
 * Reactivate a cancelled subscription
 *
 * @param string $subscriptionId Stripe subscription ID
 * @return bool Success
 */
function reactivateSubscription($subscriptionId) {
    $result = stripeRequest('/subscriptions/' . $subscriptionId, 'POST', [
        'cancel_at_period_end' => 'false'
    ]);

    return $result !== null;
}

/**
 * Get upcoming invoice for subscription
 *
 * @param string $customerId Stripe customer ID
 * @return array|null Invoice data
 */
function getUpcomingInvoice($customerId) {
    return stripeRequest('/invoices/upcoming?customer=' . urlencode($customerId));
}

/**
 * Get invoice history for customer
 *
 * @param string $customerId Stripe customer ID
 * @param int $limit Number of invoices to retrieve
 * @return array List of invoices
 */
function getInvoiceHistory($customerId, $limit = 10) {
    $result = stripeRequest('/invoices?customer=' . urlencode($customerId) . '&limit=' . $limit);
    return $result['data'] ?? [];
}

/**
 * Verify Stripe webhook signature
 *
 * @param string $payload Raw request body
 * @param string $signature Stripe-Signature header
 * @return bool Valid signature
 */
function verifyWebhookSignature($payload, $signature) {
    if (empty($signature)) {
        return false;
    }

    $sigParts = [];
    foreach (explode(',', $signature) as $part) {
        $pair = explode('=', $part, 2);
        if (count($pair) === 2) {
            $sigParts[$pair[0]] = $pair[1];
        }
    }

    if (!isset($sigParts['t']) || !isset($sigParts['v1'])) {
        return false;
    }

    $timestamp = $sigParts['t'];
    $expectedSig = $sigParts['v1'];

    // Check timestamp tolerance (5 minutes)
    if (abs(time() - $timestamp) > 300) {
        return false;
    }

    $signedPayload = $timestamp . '.' . $payload;
    $computedSig = hash_hmac('sha256', $signedPayload, STRIPE_WEBHOOK_SECRET);

    return hash_equals($computedSig, $expectedSig);
}

/**
 * Handle successful subscription creation/update
 *
 * @param PDO $db Database connection
 * @param array $subscription Stripe subscription object
 * @return bool Success
 */
function handleSubscriptionUpdate($db, $subscription) {
    $accountId = $subscription['metadata']['account_id'] ?? null;
    $planSlug = $subscription['metadata']['plan_slug'] ?? null;

    if (!$accountId) {
        // Try to find account by customer ID
        $stmt = $db->prepare("SELECT id FROM accounts WHERE stripe_customer_id = ?");
        $stmt->execute([$subscription['customer']]);
        $account = $stmt->fetch();
        $accountId = $account['id'] ?? null;
    }

    if (!$accountId) {
        error_log("Cannot find account for subscription: " . $subscription['id']);
        return false;
    }

    // Find plan by slug or determine from price
    $stmt = $db->prepare("SELECT id FROM plans WHERE slug = ?");
    $stmt->execute([$planSlug ?: 'basis']);
    $plan = $stmt->fetch();

    if (!$plan) {
        error_log("Cannot find plan: $planSlug");
        return false;
    }

    // Map Stripe status to our status
    $statusMap = [
        'active' => 'active',
        'trialing' => 'active',
        'past_due' => 'past_due',
        'canceled' => 'cancelled',
        'unpaid' => 'past_due',
        'incomplete' => 'pending',
        'incomplete_expired' => 'cancelled'
    ];
    $status = $statusMap[$subscription['status']] ?? 'pending';

    // Check for existing subscription
    $stmt = $db->prepare("SELECT id FROM subscriptions WHERE account_id = ?");
    $stmt->execute([$accountId]);
    $existingSub = $stmt->fetch();

    if ($existingSub) {
        // Update existing subscription
        $stmt = $db->prepare("
            UPDATE subscriptions SET
                plan_id = ?,
                stripe_subscription_id = ?,
                status = ?,
                current_period_start = ?,
                current_period_end = ?,
                cancel_at_period_end = ?,
                updated_at = NOW()
            WHERE account_id = ?
        ");
        $stmt->execute([
            $plan['id'],
            $subscription['id'],
            $status,
            date('Y-m-d H:i:s', $subscription['current_period_start']),
            date('Y-m-d H:i:s', $subscription['current_period_end']),
            $subscription['cancel_at_period_end'] ? 1 : 0,
            $accountId
        ]);
    } else {
        // Create new subscription
        $stmt = $db->prepare("
            INSERT INTO subscriptions (account_id, plan_id, stripe_subscription_id, status, current_period_start, current_period_end, cancel_at_period_end)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $accountId,
            $plan['id'],
            $subscription['id'],
            $status,
            date('Y-m-d H:i:s', $subscription['current_period_start']),
            date('Y-m-d H:i:s', $subscription['current_period_end']),
            $subscription['cancel_at_period_end'] ? 1 : 0
        ]);
    }

    return true;
}

/**
 * Handle subscription cancellation
 *
 * @param PDO $db Database connection
 * @param string $subscriptionId Stripe subscription ID
 * @return bool Success
 */
function handleSubscriptionCancelled($db, $subscriptionId) {
    $stmt = $db->prepare("
        UPDATE subscriptions
        SET status = 'cancelled', updated_at = NOW()
        WHERE stripe_subscription_id = ?
    ");
    return $stmt->execute([$subscriptionId]);
}

/**
 * Log payment to history
 *
 * @param PDO $db Database connection
 * @param array $invoice Stripe invoice object
 * @return bool Success
 */
function logPayment($db, $invoice) {
    $stmt = $db->prepare("SELECT id FROM accounts WHERE stripe_customer_id = ?");
    $stmt->execute([$invoice['customer']]);
    $account = $stmt->fetch();

    if (!$account) {
        return false;
    }

    $stmt = $db->prepare("
        INSERT INTO payment_history (account_id, stripe_invoice_id, amount, currency, status, description, paid_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE status = VALUES(status), paid_at = VALUES(paid_at)
    ");

    return $stmt->execute([
        $account['id'],
        $invoice['id'],
        $invoice['amount_paid'],
        $invoice['currency'],
        $invoice['paid'] ? 'paid' : 'failed',
        $invoice['lines']['data'][0]['description'] ?? 'Subscription',
        $invoice['paid'] ? date('Y-m-d H:i:s', $invoice['status_transitions']['paid_at'] ?? time()) : null
    ]);
}

/**
 * Format amount for display
 *
 * @param int $amount Amount in smallest currency unit (cents/øre)
 * @param string $currency Currency code
 * @return string Formatted amount
 */
function formatStripeAmount($amount, $currency = 'dkk') {
    return number_format($amount / 100, 2, ',', '.') . ' ' . strtoupper($currency);
}

<?php
/**
 * Stripe Webhook Handler
 *
 * Receives and processes webhook events from Stripe for subscription management.
 *
 * Configure webhook endpoint in Stripe Dashboard:
 * URL: https://yourdomain.com/app/webhooks/stripe.php
 *
 * Events to listen for:
 * - customer.subscription.created
 * - customer.subscription.updated
 * - customer.subscription.deleted
 * - invoice.paid
 * - invoice.payment_failed
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/stripe.php';

// Get raw POST body
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// Verify webhook signature
if (!verifyWebhookSignature($payload, $signature)) {
    http_response_code(400);
    error_log('Stripe webhook: Invalid signature');
    exit('Invalid signature');
}

// Parse event
$event = json_decode($payload, true);

if (!$event || !isset($event['type'])) {
    http_response_code(400);
    exit('Invalid payload');
}

$db = getDB();

// Log webhook event (for debugging)
error_log('Stripe webhook received: ' . $event['type'] . ' - ' . ($event['id'] ?? 'no-id'));

try {
    switch ($event['type']) {
        // Subscription created or updated
        case 'customer.subscription.created':
        case 'customer.subscription.updated':
            $subscription = $event['data']['object'];
            handleSubscriptionUpdate($db, $subscription);
            break;

        // Subscription cancelled/deleted
        case 'customer.subscription.deleted':
            $subscription = $event['data']['object'];
            handleSubscriptionCancelled($db, $subscription['id']);
            break;

        // Invoice paid successfully
        case 'invoice.paid':
            $invoice = $event['data']['object'];
            logPayment($db, $invoice);

            // Also update subscription if this is a subscription invoice
            if (!empty($invoice['subscription'])) {
                $subscription = getStripeSubscription($invoice['subscription']);
                if ($subscription) {
                    handleSubscriptionUpdate($db, $subscription);
                }
            }
            break;

        // Invoice payment failed
        case 'invoice.payment_failed':
            $invoice = $event['data']['object'];

            // Mark subscription as past_due if exists
            if (!empty($invoice['subscription'])) {
                $stmt = $db->prepare("
                    UPDATE subscriptions
                    SET status = 'past_due', updated_at = NOW()
                    WHERE stripe_subscription_id = ?
                ");
                $stmt->execute([$invoice['subscription']]);
            }

            // Log the failed payment
            $stmt = $db->prepare("SELECT id FROM accounts WHERE stripe_customer_id = ?");
            $stmt->execute([$invoice['customer']]);
            $account = $stmt->fetch();

            if ($account) {
                $stmt = $db->prepare("
                    INSERT INTO payment_history (account_id, stripe_invoice_id, amount, currency, status, description)
                    VALUES (?, ?, ?, ?, 'failed', ?)
                ");
                $stmt->execute([
                    $account['id'],
                    $invoice['id'],
                    $invoice['amount_due'],
                    $invoice['currency'],
                    'Betaling fejlet - ' . ($invoice['lines']['data'][0]['description'] ?? 'Abonnement')
                ]);
            }
            break;

        // Checkout session completed (initial subscription)
        case 'checkout.session.completed':
            $session = $event['data']['object'];

            // If it's a subscription checkout, the subscription.created event will handle the rest
            // But we can update the account with any metadata here
            if ($session['mode'] === 'subscription' && !empty($session['subscription'])) {
                error_log('Checkout completed for subscription: ' . $session['subscription']);
            }
            break;

        // Customer created/updated (sync customer data if needed)
        case 'customer.created':
        case 'customer.updated':
            $customer = $event['data']['object'];
            $accountId = $customer['metadata']['account_id'] ?? null;

            if ($accountId) {
                $stmt = $db->prepare("UPDATE accounts SET stripe_customer_id = ? WHERE id = ?");
                $stmt->execute([$customer['id'], $accountId]);
            }
            break;

        default:
            // Unhandled event type - just acknowledge receipt
            error_log('Stripe webhook: Unhandled event type: ' . $event['type']);
            break;
    }

    // Return success
    http_response_code(200);
    echo json_encode(['received' => true]);

} catch (Exception $e) {
    error_log('Stripe webhook error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

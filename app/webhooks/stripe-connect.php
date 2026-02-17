<?php
/**
 * Stripe Connect Webhook Handler
 *
 * Receives and processes webhook events from Stripe Connect for
 * booking payments, refunds, and vendor account updates.
 *
 * Configure webhook endpoint in Stripe Dashboard:
 * URL: https://yourdomain.com/app/webhooks/stripe-connect.php
 *
 * Events to listen for:
 * - checkout.session.completed (booking depositum paid)
 * - charge.refunded (payment refunded)
 * - account.updated (vendor onboarding status change)
 */

require_once __DIR__ . '/../../config/stripe.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../subcontractor/includes/booking-service.php';
require_once __DIR__ . '/../../subcontractor/includes/notification-service.php';

// ============================================================
// Read raw POST body and verify signature
// ============================================================

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (!verifyConnectWebhookSignature($payload, $signature)) {
    http_response_code(400);
    error_log('Stripe Connect webhook: Invalid signature');
    exit('Invalid signature');
}

// ============================================================
// Parse event
// ============================================================

$event = json_decode($payload, true);

if (!$event || !isset($event['type'])) {
    http_response_code(400);
    exit('Invalid payload');
}

$db = getDB();

// Log webhook event (for debugging)
error_log('Stripe Connect webhook received: ' . $event['type'] . ' - ' . ($event['id'] ?? 'no-id'));

try {
    switch ($event['type']) {

        // ============================================================
        // Checkout session completed — booking depositum paid
        // ============================================================
        case 'checkout.session.completed':
            $session = $event['data']['object'];
            $bookingId = $session['metadata']['booking_id'] ?? null;

            if (!$bookingId) {
                error_log('Stripe Connect webhook: checkout.session.completed missing booking_id in metadata');
                break;
            }

            $bookingId = (int) $bookingId;

            // Confirm the booking (transition from 'accepted' to 'deposited')
            $confirmed = confirmBooking($bookingId);

            if ($confirmed) {
                error_log("Stripe Connect webhook: Booking $bookingId confirmed (deposited)");

                // Update stripe_payment_intent_id on the booking
                $paymentIntentId = $session['payment_intent'] ?? null;
                if ($paymentIntentId) {
                    $stmt = $db->prepare("
                        UPDATE bookings
                        SET stripe_payment_intent_id = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$paymentIntentId, $bookingId]);
                }

                // Send payment confirmation notifications
                $booking = getBooking($bookingId);
                if ($booking) {
                    $vendor = [
                        'email' => $booking['vendor_email'],
                        'company_name' => $booking['vendor_company_name']
                    ];
                    $organizer = [
                        'email' => $booking['account_email'],
                        'name' => $booking['account_name']
                    ];
                    notifyBothPaymentConfirmed($booking, $vendor, $organizer);
                }
            } else {
                error_log("Stripe Connect webhook: Failed to confirm booking $bookingId (may already be confirmed or not in 'accepted' status)");
            }
            break;

        // ============================================================
        // Charge refunded — update booking status
        // ============================================================
        case 'charge.refunded':
            $charge = $event['data']['object'];
            $paymentIntentId = $charge['payment_intent'] ?? null;

            if (!$paymentIntentId) {
                error_log('Stripe Connect webhook: charge.refunded missing payment_intent');
                break;
            }

            // Find booking by payment_intent
            $stmt = $db->prepare("
                SELECT id FROM bookings
                WHERE stripe_payment_intent_id = ?
                LIMIT 1
            ");
            $stmt->execute([$paymentIntentId]);
            $booking = $stmt->fetch();

            if ($booking) {
                $stmt = $db->prepare("
                    UPDATE bookings
                    SET status = 'refunded', cancelled_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$booking['id']]);
                error_log("Stripe Connect webhook: Booking {$booking['id']} refunded");

                // Send refund notifications
                $fullBooking = getBooking($booking['id']);
                if ($fullBooking) {
                    $vendor = [
                        'email' => $fullBooking['vendor_email'],
                        'company_name' => $fullBooking['vendor_company_name']
                    ];
                    $organizer = [
                        'email' => $fullBooking['account_email'],
                        'name' => $fullBooking['account_name']
                    ];
                    notifyBothRefund($fullBooking, $vendor, $organizer);
                }
            } else {
                error_log("Stripe Connect webhook: No booking found for payment_intent: $paymentIntentId");
            }
            break;

        // ============================================================
        // Account updated — vendor onboarding status
        // ============================================================
        case 'account.updated':
            $account = $event['data']['object'];
            $stripeAccountId = $account['id'] ?? null;

            if (!$stripeAccountId) {
                error_log('Stripe Connect webhook: account.updated missing account id');
                break;
            }

            $chargesEnabled = !empty($account['charges_enabled']);
            $payoutsEnabled = !empty($account['payouts_enabled']);
            $onboardingComplete = ($chargesEnabled && $payoutsEnabled) ? 1 : 0;

            $stmt = $db->prepare("
                UPDATE vendors
                SET stripe_onboarding_complete = ?
                WHERE stripe_account_id = ?
            ");
            $stmt->execute([$onboardingComplete, $stripeAccountId]);

            if ($stmt->rowCount() > 0) {
                error_log("Stripe Connect webhook: Vendor with account $stripeAccountId onboarding_complete=$onboardingComplete");
            } else {
                error_log("Stripe Connect webhook: No vendor found for stripe_account_id: $stripeAccountId");
            }
            break;

        // ============================================================
        // Default: unhandled event type
        // ============================================================
        default:
            error_log('Stripe Connect webhook: Unhandled event type: ' . $event['type']);
            break;
    }

    // Return success
    http_response_code(200);
    echo json_encode(['received' => true]);

} catch (Exception $e) {
    error_log('Stripe Connect webhook error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

// ============================================================
// Helper: Verify Connect webhook signature
// ============================================================

/**
 * Verify Stripe Connect webhook signature using STRIPE_CONNECT_WEBHOOK_SECRET.
 * Same algorithm as verifyWebhookSignature() in includes/stripe.php but uses
 * the Connect-specific secret.
 *
 * @param string $payload   Raw request body
 * @param string $signature Stripe-Signature header
 * @return bool True if signature is valid
 */
function verifyConnectWebhookSignature(string $payload, string $signature): bool {
    if (empty($signature) || empty(STRIPE_CONNECT_WEBHOOK_SECRET)) {
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
    if (abs(time() - (int) $timestamp) > 300) {
        return false;
    }

    $signedPayload = $timestamp . '.' . $payload;
    $computedSig = hash_hmac('sha256', $signedPayload, STRIPE_CONNECT_WEBHOOK_SECRET);

    return hash_equals($computedSig, $expectedSig);
}

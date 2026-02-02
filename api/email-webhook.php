<?php
/**
 * Email Webhook Handler
 * Receives webhook events from SendGrid for email status updates
 */

require_once __DIR__ . '/../config/saas.php';
require_once __DIR__ . '/../includes/email-service.php';

// SendGrid sends events as JSON
$input = file_get_contents('php://input');
$events = json_decode($input, true);

// Validate input
if (!is_array($events)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

// Optional: Verify webhook signature
// SendGrid doesn't require signature verification for basic events,
// but you can implement it using the X-Twilio-Email-Event-Webhook-Signature header

$db = getDB();
$emailService = new EmailService($db);

try {
    $emailService->processWebhook($events);
    http_response_code(200);
    echo json_encode(['success' => true, 'processed' => count($events)]);
} catch (Exception $e) {
    error_log('Email webhook error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Processing failed']);
}

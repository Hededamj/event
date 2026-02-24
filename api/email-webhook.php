<?php
/**
 * Email Webhook Handler
 * Receives webhook events from Resend for email status updates
 */

require_once __DIR__ . '/../config/saas.php';
require_once __DIR__ . '/../includes/email-service.php';

$input = file_get_contents('php://input');
$payload = json_decode($input, true);

if (!is_array($payload) || empty($payload['type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$db = getDB();
$emailService = new EmailService($db);

try {
    // Resend sends individual events with type and data fields
    $emailService->processWebhook([$payload]);
    http_response_code(200);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('Email webhook error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Processing failed']);
}

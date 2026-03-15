<?php
/**
 * Invitation Autosave API
 * Accepts partial invitation config updates via JSON POST and saves to database
 */

require_once __DIR__ . '/../config/saas.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth-account.php';
require_once __DIR__ . '/../includes/invitation-functions.php';

header('Content-Type: application/json; charset=UTF-8');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Kun POST-metode er tilladt']);
    exit;
}

// Require authentication
if (!isAccountLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Ikke logget ind']);
    exit;
}

$accountId = getCurrentAccountId();
$db = getDB();

try {
    // Parse JSON body
    $input = json_decode(file_get_contents('php://input'), true);

    // Require event_id
    $eventId = isset($input['event_id']) ? (int)$input['event_id'] : 0;

    if (!$eventId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Manglende event_id']);
        exit;
    }

    // Check access to event
    $stmt = $db->prepare("
        SELECT e.id
        FROM events e
        JOIN event_owners eo ON e.id = eo.event_id AND eo.account_id = ?
        WHERE e.id = ?
    ");
    $stmt->execute([$accountId, $eventId]);
    $event = $stmt->fetch();

    if (!$event) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Ingen adgang til dette arrangement']);
        exit;
    }

    // Allowed fields
    $allowedFields = [
        'layout_style',
        'font_style',
        'color_primary',
        'color_secondary',
        'color_accent',
        'color_text',
        'color_background',
        'greeting_template',
        'headline_text',
        'invitation_message',
        'closing_text',
        'show_countdown',
        'show_map',
        'show_schedule',
        'show_rsvp',
        'sections_order',
        'template_id',
    ];

    // Boolean fields to cast to int
    $booleanFields = ['show_countdown', 'show_map', 'show_schedule', 'show_rsvp'];

    // Filter incoming fields to allowed only
    $incoming = array_intersect_key($input, array_flip($allowedFields));

    // Merge with existing config
    $existingConfig = getInvitationConfig($db, $eventId);
    $data = array_merge($existingConfig, $incoming);

    // Cast boolean fields to int
    foreach ($booleanFields as $field) {
        if (isset($data[$field])) {
            $data[$field] = (int)(bool)$data[$field];
        }
    }

    // Save
    saveInvitationConfig($db, $eventId, $data);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Serverfejl: ' . $e->getMessage()]);
}

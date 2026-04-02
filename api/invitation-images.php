<?php
/**
 * Invitation Images API
 * Handles upload, delete, reorder, and role changes for invitation images
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/saas.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth-account.php';
require_once __DIR__ . '/../includes/invitation-functions.php';

// Require authentication
if (!isAccountLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Ikke logget ind']);
    exit;
}

$accountId = getCurrentAccountId();
$db = getDB();

// Get event ID and verify access
$eventId = isset($_REQUEST['event_id']) ? (int)$_REQUEST['event_id'] : 0;

if (!$eventId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Manglende event_id']);
    exit;
}

// Check access to event
$stmt = $db->prepare("
    SELECT e.id FROM events e
    JOIN event_owners eo ON e.id = eo.event_id AND eo.account_id = ?
    WHERE e.id = ?
");
$stmt->execute([$accountId, $eventId]);
if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Ingen adgang til dette arrangement']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'upload':
        handleUpload($db, $eventId);
        break;

    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Kun POST-metode er tilladt']);
            break;
        }
        handleDelete($db, $eventId);
        break;

    case 'reorder':
        handleReorder($db, $eventId);
        break;

    case 'set-role':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Kun POST-metode er tilladt']);
            break;
        }
        handleSetRole($db, $eventId);
        break;

    case 'list':
        handleList($db, $eventId);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ukendt handling']);
}

/**
 * Handle image upload
 */
function handleUpload(PDO $db, int $eventId): void {
    if (empty($_FILES['image'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ingen fil uploadet']);
        return;
    }

    $role = $_POST['role'] ?? 'gallery';
    if (!in_array($role, ['hero', 'gallery', 'background'])) {
        $role = 'gallery';
    }

    $file = $_FILES['image'];

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'Filen er for stor (server limit)',
            UPLOAD_ERR_FORM_SIZE => 'Filen er for stor',
            UPLOAD_ERR_PARTIAL => 'Filen blev kun delvist uploadet',
            UPLOAD_ERR_NO_FILE => 'Ingen fil uploadet',
            UPLOAD_ERR_NO_TMP_DIR => 'Manglende temp mappe',
            UPLOAD_ERR_CANT_WRITE => 'Kunne ikke skrive filen',
            UPLOAD_ERR_EXTENSION => 'Upload stoppet af extension'
        ];
        $error = $errorMessages[$file['error']] ?? 'Ukendt uploadfejl';
        echo json_encode(['success' => false, 'error' => $error]);
        return;
    }

    $result = uploadInvitationImage($db, $eventId, $file, $role);
    echo json_encode($result);
}

/**
 * Handle image deletion
 */
function handleDelete(PDO $db, int $eventId): void {
    $imageId = isset($_REQUEST['image_id']) ? (int)$_REQUEST['image_id'] : 0;

    if (!$imageId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Manglende image_id']);
        return;
    }

    $success = deleteInvitationImage($db, $imageId, $eventId);
    echo json_encode(['success' => $success]);
}

/**
 * Handle image reordering
 */
function handleReorder(PDO $db, int $eventId): void {
    $input = json_decode(file_get_contents('php://input'), true);
    $imageIds = $input['image_ids'] ?? [];

    if (empty($imageIds) || !is_array($imageIds)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Manglende image_ids']);
        return;
    }

    $success = reorderInvitationImages($db, $eventId, $imageIds);
    echo json_encode(['success' => $success]);
}

/**
 * Handle setting image role
 */
function handleSetRole(PDO $db, int $eventId): void {
    $imageId = isset($_REQUEST['image_id']) ? (int)$_REQUEST['image_id'] : 0;
    $role = $_REQUEST['role'] ?? '';

    if (!$imageId || !in_array($role, ['hero', 'gallery', 'background'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ugyldige parametre']);
        return;
    }

    $success = setImageRole($db, $imageId, $eventId, $role);
    echo json_encode(['success' => $success]);
}

/**
 * List all images for an event
 */
function handleList(PDO $db, int $eventId): void {
    $role = $_GET['role'] ?? null;
    $images = getInvitationImages($db, $eventId, $role);

    // Add URLs to images
    foreach ($images as &$image) {
        $image['url'] = '/uploads/invitations/' . $image['filename'];
    }

    echo json_encode(['success' => true, 'images' => $images]);
}

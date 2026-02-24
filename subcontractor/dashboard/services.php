<?php
/**
 * Vendor Services Management
 * CRUD for vendor services/pricing with per-service image galleries.
 */

$pageTitle = 'Ydelser';

require_once __DIR__ . '/../includes/vendor-header.php';
require_once __DIR__ . '/../includes/vendor-validation.php';

$db = getDB();
$errors = [];
$maxImages = 8;

// ============================================================
// Handle POST actions
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyVendorCsrfToken($csrfToken)) {
        setFlash('error', 'Ugyldig anmodning. Prøv igen.');
        redirect('/subcontractor/dashboard/services.php');
    }

    // --- ADD ---
    if ($action === 'add') {
        $errors = validateService($_POST);

        if (empty($errors)) {
            try {
                $sortStmt = $db->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM vendor_services WHERE vendor_id = ?");
                $sortStmt->execute([$vendorId]);
                $nextSort = (int) $sortStmt->fetchColumn();

                $stmt = $db->prepare("
                    INSERT INTO vendor_services (vendor_id, title, description, price_from, price_to, price_unit, duration_hours, is_active, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $vendorId,
                    sanitizeString($_POST['title'] ?? ''),
                    !empty($_POST['description']) ? trim($_POST['description']) : null,
                    (float) ($_POST['price_from'] ?? 0),
                    (!empty($_POST['price_to']) && $_POST['price_to'] !== '') ? (float) $_POST['price_to'] : null,
                    $_POST['price_unit'] ?? 'fixed',
                    (!empty($_POST['duration_hours']) && $_POST['duration_hours'] !== '') ? (float) $_POST['duration_hours'] : null,
                    isset($_POST['is_active']) ? 1 : 0,
                    $nextSort,
                ]);

                setFlash('success', 'Ydelse tilføjet.');
                redirect('/subcontractor/dashboard/services.php');
            } catch (Exception $e) {
                error_log("Failed to add vendor service: " . $e->getMessage());
                setFlash('error', 'Der opstod en fejl. Prøv igen.');
                redirect('/subcontractor/dashboard/services.php');
            }
        }
    }

    // --- EDIT ---
    if ($action === 'edit') {
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $errors = validateService($_POST);

        if (empty($errors)) {
            try {
                $stmt = $db->prepare("
                    UPDATE vendor_services
                    SET title = ?, description = ?, price_from = ?, price_to = ?, price_unit = ?, duration_hours = ?, is_active = ?
                    WHERE id = ? AND vendor_id = ?
                ");
                $stmt->execute([
                    sanitizeString($_POST['title'] ?? ''),
                    !empty($_POST['description']) ? trim($_POST['description']) : null,
                    (float) ($_POST['price_from'] ?? 0),
                    (!empty($_POST['price_to']) && $_POST['price_to'] !== '') ? (float) $_POST['price_to'] : null,
                    $_POST['price_unit'] ?? 'fixed',
                    (!empty($_POST['duration_hours']) && $_POST['duration_hours'] !== '') ? (float) $_POST['duration_hours'] : null,
                    isset($_POST['is_active']) ? 1 : 0,
                    $serviceId,
                    $vendorId,
                ]);

                setFlash('success', 'Ydelse opdateret.');
                redirect('/subcontractor/dashboard/services.php');
            } catch (Exception $e) {
                error_log("Failed to update vendor service: " . $e->getMessage());
                setFlash('error', 'Der opstod en fejl. Prøv igen.');
                redirect('/subcontractor/dashboard/services.php');
            }
        }
    }

    // --- DELETE ---
    if ($action === 'delete') {
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        try {
            // Clean up image files before DB cascade delete
            $imgStmt = $db->prepare("SELECT filename FROM vendor_service_images WHERE service_id = ? AND vendor_id = ?");
            $imgStmt->execute([$serviceId, $vendorId]);
            $imgDir = __DIR__ . '/../../uploads/vendors/services';
            foreach ($imgStmt->fetchAll(PDO::FETCH_COLUMN) as $imgFile) {
                $path = $imgDir . '/' . $imgFile;
                if (file_exists($path)) {
                    @unlink($path);
                }
            }

            $stmt = $db->prepare("DELETE FROM vendor_services WHERE id = ? AND vendor_id = ?");
            $stmt->execute([$serviceId, $vendorId]);
            setFlash('success', 'Ydelse slettet.');
        } catch (Exception $e) {
            error_log("Failed to delete vendor service: " . $e->getMessage());
            setFlash('error', 'Der opstod en fejl. Prøv igen.');
        }
        redirect('/subcontractor/dashboard/services.php');
    }

    // --- TOGGLE ---
    if ($action === 'toggle') {
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        try {
            $stmt = $db->prepare("UPDATE vendor_services SET is_active = NOT is_active WHERE id = ? AND vendor_id = ?");
            $stmt->execute([$serviceId, $vendorId]);
            setFlash('success', 'Status opdateret.');
        } catch (Exception $e) {
            error_log("Failed to toggle vendor service: " . $e->getMessage());
            setFlash('error', 'Der opstod en fejl. Prøv igen.');
        }
        redirect('/subcontractor/dashboard/services.php');
    }

    // --- UPLOAD IMAGES ---
    if ($action === 'upload_images') {
        $serviceId = (int) ($_POST['service_id'] ?? 0);

        $checkStmt = $db->prepare("SELECT id FROM vendor_services WHERE id = ? AND vendor_id = ?");
        $checkStmt->execute([$serviceId, $vendorId]);
        if (!$checkStmt->fetch()) {
            setFlash('error', 'Ydelse ikke fundet.');
            redirect('/subcontractor/dashboard/services.php');
        }

        $countStmt = $db->prepare("SELECT COUNT(*) FROM vendor_service_images WHERE service_id = ?");
        $countStmt->execute([$serviceId]);
        $currentCount = (int) $countStmt->fetchColumn();

        if ($currentCount >= $maxImages) {
            setFlash('error', 'Maks ' . $maxImages . ' billeder pr. ydelse.');
            redirect('/subcontractor/dashboard/services.php');
        }

        $files = $_FILES['service_images'] ?? null;
        if (!$files || !is_array($files['name']) || empty($files['name'][0])) {
            setFlash('error', 'Ingen billeder valgt.');
            redirect('/subcontractor/dashboard/services.php');
        }

        $remaining = $maxImages - $currentCount;
        $fileCount = min(count($files['name']), $remaining);

        $uploadDir = __DIR__ . '/../../uploads/vendors/services';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $sortStmt = $db->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM vendor_service_images WHERE service_id = ?");
        $sortStmt->execute([$serviceId]);
        $nextSort = (int) $sortStmt->fetchColumn();

        $insertStmt = $db->prepare("
            INSERT INTO vendor_service_images (service_id, vendor_id, filename, original_name, is_primary, sort_order)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $timestamp = time();
        $uploaded = 0;

        for ($i = 0; $i < $fileCount; $i++) {
            $file = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i],
            ];

            if ($file['error'] === UPLOAD_ERR_NO_FILE) continue;

            $fileErrors = validateVendorImageUpload($file);
            if (!empty($fileErrors)) continue;

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = 'svc_' . $serviceId . '_' . $timestamp . '_' . $i . '.' . $ext;

            if (move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
                $nextSort++;
                $isPrimary = ($currentCount === 0 && $uploaded === 0) ? 1 : 0;
                try {
                    $insertStmt->execute([$serviceId, $vendorId, $filename, $file['name'], $isPrimary, $nextSort]);
                    $uploaded++;
                } catch (Exception $e) {
                    error_log("Failed to insert service image: " . $e->getMessage());
                    @unlink($uploadDir . '/' . $filename);
                }
            }
        }

        if ($uploaded > 0) {
            setFlash('success', $uploaded . ' billede(r) uploadet.');
        } else {
            setFlash('error', 'Ingen billeder blev uploadet. Tjek filformat og størrelse.');
        }
        redirect('/subcontractor/dashboard/services.php');
    }

    // --- DELETE IMAGE ---
    if ($action === 'delete_image') {
        $imageId = (int) ($_POST['image_id'] ?? 0);
        $uploadDir = __DIR__ . '/../../uploads/vendors/services';

        try {
            $stmt = $db->prepare("SELECT id, service_id, filename, is_primary FROM vendor_service_images WHERE id = ? AND vendor_id = ?");
            $stmt->execute([$imageId, $vendorId]);
            $image = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($image) {
                $db->prepare("DELETE FROM vendor_service_images WHERE id = ?")->execute([$imageId]);

                $filePath = $uploadDir . '/' . $image['filename'];
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }

                // Promote next image to primary if needed
                if ($image['is_primary']) {
                    $nextStmt = $db->prepare("SELECT id FROM vendor_service_images WHERE service_id = ? ORDER BY sort_order ASC LIMIT 1");
                    $nextStmt->execute([$image['service_id']]);
                    $nextImage = $nextStmt->fetch();
                    if ($nextImage) {
                        $db->prepare("UPDATE vendor_service_images SET is_primary = 1 WHERE id = ?")->execute([$nextImage['id']]);
                    }
                }

                setFlash('success', 'Billede slettet.');
            }
        } catch (Exception $e) {
            error_log("Failed to delete service image: " . $e->getMessage());
            setFlash('error', 'Kunne ikke slette billede.');
        }
        redirect('/subcontractor/dashboard/services.php');
    }

    // --- SET PRIMARY IMAGE ---
    if ($action === 'set_primary') {
        $imageId = (int) ($_POST['image_id'] ?? 0);

        try {
            $stmt = $db->prepare("SELECT service_id FROM vendor_service_images WHERE id = ? AND vendor_id = ?");
            $stmt->execute([$imageId, $vendorId]);
            $image = $stmt->fetch();

            if ($image) {
                $db->prepare("UPDATE vendor_service_images SET is_primary = 0 WHERE service_id = ?")->execute([$image['service_id']]);
                $db->prepare("UPDATE vendor_service_images SET is_primary = 1 WHERE id = ?")->execute([$imageId]);
                setFlash('success', 'Primært billede opdateret.');
            }
        } catch (Exception $e) {
            error_log("Failed to set primary image: " . $e->getMessage());
            setFlash('error', 'Kunne ikke opdatere primært billede.');
        }
        redirect('/subcontractor/dashboard/services.php');
    }
}

// ============================================================
// Modal state for validation errors
// ============================================================
$showModal = false;
$modalAction = 'add';
$modalData = [
    'service_id' => '',
    'title' => '',
    'description' => '',
    'price_from' => '',
    'price_to' => '',
    'price_unit' => 'fixed',
    'duration_hours' => '',
    'is_active' => 1,
];

if (!empty($errors)) {
    $showModal = true;
    $modalAction = $_POST['action'] ?? 'add';
    $modalData = [
        'service_id' => $_POST['service_id'] ?? '',
        'title' => $_POST['title'] ?? '',
        'description' => $_POST['description'] ?? '',
        'price_from' => $_POST['price_from'] ?? '',
        'price_to' => $_POST['price_to'] ?? '',
        'price_unit' => $_POST['price_unit'] ?? 'fixed',
        'duration_hours' => $_POST['duration_hours'] ?? '',
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];
}

// ============================================================
// Load services + images
// ============================================================
try {
    $stmt = $db->prepare("
        SELECT * FROM vendor_services
        WHERE vendor_id = ?
        ORDER BY sort_order ASC, is_active DESC
    ");
    $stmt->execute([$vendorId]);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to load vendor services: " . $e->getMessage());
    $services = [];
}

// Load all images grouped by service
$serviceImages = [];
if (!empty($services)) {
    try {
        $serviceIds = array_column($services, 'id');
        $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
        $imgStmt = $db->prepare("
            SELECT * FROM vendor_service_images
            WHERE service_id IN ($placeholders) AND vendor_id = ?
            ORDER BY is_primary DESC, sort_order ASC
        ");
        $imgStmt->execute(array_merge($serviceIds, [$vendorId]));
        foreach ($imgStmt->fetchAll(PDO::FETCH_ASSOC) as $img) {
            $serviceImages[$img['service_id']][] = $img;
        }
    } catch (Exception $e) {
        error_log("Failed to load service images: " . $e->getMessage());
    }
}

$priceUnitLabels = [
    'fixed' => 'fast pris',
    'per_person' => 'pr. person',
    'per_hour' => 'pr. time',
];
?>

<style>
    /* ── Service Cards ─────────────────────────────────────── */
    .svc-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 20px;
    }
    @media (max-width: 500px) {
        .svc-grid {
            grid-template-columns: 1fr;
        }
    }

    .svc-card {
        background: var(--surface-card);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 4px 12px rgba(0,0,0,0.03);
        transition: box-shadow 0.3s var(--ease-out), transform 0.3s var(--ease-out);
        display: flex;
        flex-direction: column;
    }
    .svc-card:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.06), 0 8px 24px rgba(0,0,0,0.06);
        transform: translateY(-2px);
    }
    .svc-card.inactive {
        opacity: 0.65;
    }

    /* Thumbnail area */
    .svc-thumb {
        position: relative;
        width: 100%;
        aspect-ratio: 16 / 10;
        background: var(--border);
        overflow: hidden;
        cursor: pointer;
    }
    .svc-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.4s var(--ease-out);
    }
    .svc-card:hover .svc-thumb img {
        transform: scale(1.03);
    }
    .svc-thumb-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: var(--text-secondary);
        gap: 8px;
    }
    .svc-thumb-empty svg {
        width: 32px;
        height: 32px;
        opacity: 0.35;
    }
    .svc-thumb-empty span {
        font-size: 13px;
        opacity: 0.6;
    }

    /* Image count badge on thumbnail */
    .svc-img-count {
        position: absolute;
        bottom: 8px;
        right: 8px;
        background: rgba(0,0,0,0.6);
        color: #fff;
        font-size: 12px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 20px;
        display: flex;
        align-items: center;
        gap: 5px;
        backdrop-filter: blur(4px);
    }
    .svc-img-count svg {
        width: 14px;
        height: 14px;
    }

    /* Status badge overlay */
    .svc-status-badge {
        position: absolute;
        top: 8px;
        left: 8px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .svc-status-badge.active {
        background: rgba(93, 168, 122, 0.9);
        color: #fff;
    }
    .svc-status-badge.inactive {
        background: rgba(0,0,0,0.55);
        color: #fff;
    }

    /* Card body */
    .svc-body {
        padding: 16px 20px 20px;
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    .svc-title {
        font-family: var(--font-display);
        font-size: 20px;
        font-weight: 500;
        color: var(--text);
        margin-bottom: 4px;
        line-height: 1.3;
    }
    .svc-desc {
        font-size: 13px;
        color: var(--text-secondary);
        line-height: 1.5;
        margin-bottom: 12px;
        flex: 1;
    }

    /* Price row */
    .svc-price-row {
        display: flex;
        align-items: baseline;
        gap: 8px;
        margin-bottom: 16px;
    }
    .svc-price {
        font-size: 18px;
        font-weight: 700;
        color: var(--text);
        letter-spacing: -0.3px;
    }
    .svc-price-unit {
        font-size: 13px;
        color: var(--text-secondary);
    }
    .svc-duration {
        margin-left: auto;
        font-size: 13px;
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .svc-duration svg {
        width: 14px;
        height: 14px;
    }

    /* Action row */
    .svc-actions {
        display: flex;
        gap: 6px;
        border-top: 1px solid var(--border);
        padding-top: 12px;
    }
    .svc-action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 8px 12px;
        font-family: var(--font-body);
        font-size: 13px;
        font-weight: 500;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: var(--surface-card);
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.2s var(--ease-out);
        text-decoration: none;
    }
    .svc-action-btn:hover {
        border-color: var(--accent);
        color: var(--text);
        background: var(--surface);
    }
    .svc-action-btn svg {
        width: 15px;
        height: 15px;
    }
    .svc-action-btn.danger:hover {
        border-color: var(--error);
        color: var(--error);
    }
    .svc-action-btn.images-btn {
        margin-left: auto;
        border-color: var(--accent-light);
        color: var(--accent-dark);
        font-weight: 600;
    }
    .svc-action-btn.images-btn:hover {
        background: rgba(168, 181, 160, 0.1);
        border-color: var(--accent);
    }

    /* ── Image Management Modal ────────────────────────────── */
    .img-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.55);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 16px;
        backdrop-filter: blur(2px);
    }
    .img-modal-overlay.active {
        display: flex;
    }
    .img-modal {
        background: var(--surface-card);
        border-radius: 20px;
        width: 100%;
        max-width: 640px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 24px 80px rgba(0,0,0,0.2);
        animation: modalSlideIn 0.35s var(--ease-out);
    }
    @keyframes modalSlideIn {
        from { opacity: 0; transform: translateY(20px) scale(0.97); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }
    .img-modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 20px 24px;
        border-bottom: 1px solid var(--border);
    }
    .img-modal-header h3 {
        font-family: var(--font-display);
        font-size: 22px;
        font-weight: 500;
        color: var(--text);
    }
    .img-modal-close {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: none;
        background: var(--surface);
        border-radius: 10px;
        cursor: pointer;
        color: var(--text-secondary);
        transition: all 0.2s;
    }
    .img-modal-close:hover {
        background: var(--border);
        color: var(--text);
    }
    .img-modal-close svg {
        width: 18px;
        height: 18px;
    }
    .img-modal-body {
        padding: 24px;
    }

    /* Upload zone inside modal */
    .img-upload-zone {
        border: 2px dashed var(--border);
        border-radius: 14px;
        padding: 24px 16px;
        text-align: center;
        cursor: pointer;
        transition: all 0.25s var(--ease-out);
        margin-bottom: 20px;
        background: var(--surface);
    }
    .img-upload-zone:hover,
    .img-upload-zone.dragover {
        border-color: var(--accent);
        background: rgba(168, 181, 160, 0.06);
    }
    .img-upload-zone svg {
        width: 28px;
        height: 28px;
        color: var(--accent-dark);
        margin-bottom: 8px;
    }
    .img-upload-zone p {
        font-size: 14px;
        color: var(--text-secondary);
        margin: 0;
    }
    .img-upload-zone .link {
        color: var(--accent-dark);
        font-weight: 600;
        text-decoration: underline;
    }
    .img-upload-zone .hint {
        font-size: 12px;
        opacity: 0.7;
        margin-top: 4px;
    }
    .img-upload-zone input[type="file"] {
        display: none;
    }

    /* Image grid inside modal */
    .img-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
        gap: 12px;
    }
    .img-grid-item {
        position: relative;
        aspect-ratio: 1;
        border-radius: 12px;
        overflow: hidden;
        border: 2px solid transparent;
        transition: border-color 0.2s;
    }
    .img-grid-item.is-primary {
        border-color: var(--accent);
    }
    .img-grid-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .img-grid-item .img-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(to top, rgba(0,0,0,0.5) 0%, transparent 50%);
        opacity: 0;
        transition: opacity 0.2s;
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        padding: 8px;
    }
    .img-grid-item:hover .img-overlay {
        opacity: 1;
    }
    .img-grid-item .img-overlay button {
        width: 30px;
        height: 30px;
        border: none;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.15s;
    }
    .img-overlay .star-btn {
        background: rgba(255,255,255,0.9);
        color: var(--text-secondary);
    }
    .img-overlay .star-btn:hover,
    .img-overlay .star-btn.active {
        background: var(--accent);
        color: #fff;
    }
    .img-overlay .del-btn {
        background: rgba(255,255,255,0.9);
        color: var(--error);
    }
    .img-overlay .del-btn:hover {
        background: var(--error);
        color: #fff;
    }
    .img-overlay button svg {
        width: 14px;
        height: 14px;
    }
    .img-primary-label {
        position: absolute;
        top: 6px;
        left: 6px;
        background: var(--accent);
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .img-count-label {
        font-size: 13px;
        color: var(--text-secondary);
        margin-bottom: 12px;
        font-weight: 500;
    }
    .img-empty {
        text-align: center;
        padding: 20px;
        color: var(--text-secondary);
        font-size: 14px;
    }
</style>

<!-- Page Header -->
<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Ydelser</h1>
        <p class="page-subtitle">Administrer dine ydelser, priser og billeder</p>
    </div>
    <div class="page-header-actions">
        <button type="button" class="btn btn-primary" onclick="openAddModal()">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Tilføj ydelse
        </button>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="flash-message flash-error" style="margin-bottom: 24px;">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
        <div>
            <?php foreach ($errors as $err): ?>
                <div><?= htmlspecialchars($err) ?></div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php if (empty($services)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                </svg>
            </div>
            <h3>Ingen ydelser endnu</h3>
            <p>Tilføj dine ydelser med billeder, så arrangører kan se hvad du tilbyder.</p>
            <button type="button" class="btn btn-primary" onclick="openAddModal()">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Tilføj din første ydelse
            </button>
        </div>
    </div>
<?php else: ?>
    <div class="svc-grid">
        <?php foreach ($services as $service):
            $images = $serviceImages[$service['id']] ?? [];
            $primaryImg = null;
            foreach ($images as $img) {
                if ($img['is_primary']) { $primaryImg = $img; break; }
            }
            if (!$primaryImg && !empty($images)) $primaryImg = $images[0];
            $imgCount = count($images);
        ?>
            <div class="svc-card <?= !$service['is_active'] ? 'inactive' : '' ?>">
                <!-- Thumbnail -->
                <div class="svc-thumb" onclick="openImageModal(<?= (int)$service['id'] ?>)">
                    <?php if ($primaryImg): ?>
                        <img src="/uploads/vendors/services/<?= htmlspecialchars($primaryImg['filename']) ?>" alt="<?= htmlspecialchars($service['title']) ?>" loading="lazy">
                    <?php else: ?>
                        <div class="svc-thumb-empty">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            <span>Klik for at tilføje billeder</span>
                        </div>
                    <?php endif; ?>

                    <?php if ($imgCount > 0): ?>
                        <div class="svc-img-count">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            <?= $imgCount ?>
                        </div>
                    <?php endif; ?>

                    <div class="svc-status-badge <?= $service['is_active'] ? 'active' : 'inactive' ?>">
                        <?= $service['is_active'] ? 'Aktiv' : 'Inaktiv' ?>
                    </div>
                </div>

                <!-- Body -->
                <div class="svc-body">
                    <div class="svc-title"><?= htmlspecialchars($service['title']) ?></div>
                    <?php if (!empty($service['description'])): ?>
                        <div class="svc-desc"><?= htmlspecialchars(mb_strimwidth($service['description'], 0, 100, '...')) ?></div>
                    <?php else: ?>
                        <div class="svc-desc" style="opacity:0.4;">Ingen beskrivelse</div>
                    <?php endif; ?>

                    <div class="svc-price-row">
                        <span class="svc-price">
                            <?php if (!empty($service['price_to']) && (float)$service['price_to'] > 0): ?>
                                <?= number_format((float)$service['price_from'], 0, ',', '.') ?>-<?= number_format((float)$service['price_to'], 0, ',', '.') ?> kr
                            <?php else: ?>
                                <?= number_format((float)$service['price_from'], 0, ',', '.') ?> kr
                            <?php endif; ?>
                        </span>
                        <span class="svc-price-unit"><?= htmlspecialchars($priceUnitLabels[$service['price_unit']] ?? 'fast pris') ?></span>
                        <?php if (!empty($service['duration_hours']) && (float)$service['duration_hours'] > 0): ?>
                            <span class="svc-duration">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <?= rtrim(rtrim(number_format((float)$service['duration_hours'], 1, ',', '.'), '0'), ',') ?>t
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="svc-actions">
                        <button type="button" class="svc-action-btn" title="Rediger"
                            onclick="openEditModal(<?= htmlspecialchars(json_encode($service), ENT_QUOTES, 'UTF-8') ?>)">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            Rediger
                        </button>

                        <form method="POST" style="display:inline;">
                            <?= vendorCsrfField() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="service_id" value="<?= (int)$service['id'] ?>">
                            <button type="submit" class="svc-action-btn" title="<?= $service['is_active'] ? 'Deaktiver' : 'Aktiver' ?>">
                                <?php if ($service['is_active']): ?>
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878l4.242 4.242M21 21l-3.121-3.121"></path></svg>
                                <?php else: ?>
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                <?php endif; ?>
                            </button>
                        </form>

                        <form method="POST" style="display:inline;" onsubmit="return confirm('Slet ydelse og alle billeder?');">
                            <?= vendorCsrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="service_id" value="<?= (int)$service['id'] ?>">
                            <button type="submit" class="svc-action-btn danger" title="Slet">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </form>

                        <button type="button" class="svc-action-btn images-btn" onclick="openImageModal(<?= (int)$service['id'] ?>)">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            Billeder (<?= $imgCount ?>)
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- ============================================================ -->
<!-- Add/Edit Service Modal -->
<!-- ============================================================ -->
<div class="modal-overlay <?= $showModal ? 'active' : '' ?>" id="serviceModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title" id="modalTitle"><?= $modalAction === 'edit' ? 'Rediger ydelse' : 'Tilføj ydelse' ?></h2>
            <button type="button" class="modal-close" onclick="closeModal()">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form method="POST" id="serviceForm">
            <?= vendorCsrfField() ?>
            <input type="hidden" name="action" id="formAction" value="<?= htmlspecialchars($modalAction) ?>">
            <input type="hidden" name="service_id" id="formServiceId" value="<?= htmlspecialchars($modalData['service_id']) ?>">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="title">Titel <span class="required">*</span></label>
                    <input type="text" id="title" name="title" class="form-input" placeholder="F.eks. Partytelt 6x12m" value="<?= htmlspecialchars($modalData['title']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Beskrivelse</label>
                    <textarea id="description" name="description" class="form-textarea" placeholder="Beskriv ydelsen i detaljer..." rows="3"><?= htmlspecialchars($modalData['description']) ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="price_from">Pris fra <span class="required">*</span></label>
                        <input type="number" id="price_from" name="price_from" class="form-input" placeholder="0" step="0.01" min="0" value="<?= htmlspecialchars($modalData['price_from']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="price_to">Pris til <span style="font-weight:400; color:var(--text-secondary); font-size:13px;">(valgfrit)</span></label>
                        <input type="number" id="price_to" name="price_to" class="form-input" placeholder="0" step="0.01" min="0" value="<?= htmlspecialchars($modalData['price_to']) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="price_unit">Prisenhed</label>
                        <select id="price_unit" name="price_unit" class="form-select">
                            <option value="fixed" <?= ($modalData['price_unit'] === 'fixed') ? 'selected' : '' ?>>Fast pris</option>
                            <option value="per_person" <?= ($modalData['price_unit'] === 'per_person') ? 'selected' : '' ?>>Pr. person</option>
                            <option value="per_hour" <?= ($modalData['price_unit'] === 'per_hour') ? 'selected' : '' ?>>Pr. time</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="duration_hours">Varighed <span style="font-weight:400; color:var(--text-secondary); font-size:13px;">(valgfrit)</span></label>
                        <input type="number" id="duration_hours" name="duration_hours" class="form-input" placeholder="Timer" step="0.5" min="0" value="<?= htmlspecialchars($modalData['duration_hours']) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <div class="form-checkbox-group">
                        <input type="checkbox" id="is_active" name="is_active" class="form-checkbox" value="1" <?= $modalData['is_active'] ? 'checked' : '' ?>>
                        <label class="form-checkbox-label" for="is_active">Aktiv (synlig for arrangører)</label>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal()">Annuller</button>
                <button type="submit" class="btn btn-primary" id="modalSubmitBtn">
                    <?= $modalAction === 'edit' ? 'Gem ændringer' : 'Tilføj ydelse' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================ -->
<!-- Image Management Modal -->
<!-- ============================================================ -->
<div class="img-modal-overlay" id="imageModal">
    <div class="img-modal">
        <div class="img-modal-header">
            <h3 id="imgModalTitle">Billeder</h3>
            <button class="img-modal-close" onclick="closeImageModal()">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <div class="img-modal-body">
            <!-- Upload -->
            <form method="POST" enctype="multipart/form-data" id="imgUploadForm">
                <?= vendorCsrfField() ?>
                <input type="hidden" name="action" value="upload_images">
                <input type="hidden" name="service_id" id="imgUploadServiceId" value="">
                <div class="img-upload-zone" id="imgUploadZone">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4"></path></svg>
                    <p><span class="link">Klik for at uploade</span> eller træk billeder hertil</p>
                    <p class="hint">JPEG, PNG, GIF, WebP. Maks 5MB. Op til <?= $maxImages ?> billeder pr. ydelse.</p>
                    <input type="file" id="imgFileInput" name="service_images[]" multiple accept="image/jpeg,image/png,image/gif,image/webp">
                </div>
            </form>

            <!-- Current images -->
            <div id="imgGridContainer">
                <!-- Populated by JS -->
            </div>
        </div>
    </div>
</div>

<!-- Hidden forms for image actions -->
<form method="POST" id="deleteImageForm" style="display:none;">
    <?= vendorCsrfField() ?>
    <input type="hidden" name="action" value="delete_image">
    <input type="hidden" name="image_id" id="deleteImageId" value="">
</form>
<form method="POST" id="setPrimaryForm" style="display:none;">
    <?= vendorCsrfField() ?>
    <input type="hidden" name="action" value="set_primary">
    <input type="hidden" name="image_id" id="setPrimaryImageId" value="">
</form>

<!-- Embed image data as JSON for JS -->
<script>
    var serviceImagesData = <?= json_encode($serviceImages, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var maxImagesPerService = <?= $maxImages ?>;

    // ── Service Modal ─────────────────────────────────────────
    function openAddModal() {
        document.getElementById('modalTitle').textContent = 'Tilføj ydelse';
        document.getElementById('formAction').value = 'add';
        document.getElementById('formServiceId').value = '';
        document.getElementById('title').value = '';
        document.getElementById('description').value = '';
        document.getElementById('price_from').value = '';
        document.getElementById('price_to').value = '';
        document.getElementById('price_unit').value = 'fixed';
        document.getElementById('duration_hours').value = '';
        document.getElementById('is_active').checked = true;
        document.getElementById('modalSubmitBtn').textContent = 'Tilføj ydelse';
        document.getElementById('serviceModal').classList.add('active');
    }

    function openEditModal(service) {
        document.getElementById('modalTitle').textContent = 'Rediger ydelse';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('formServiceId').value = service.id;
        document.getElementById('title').value = service.title || '';
        document.getElementById('description').value = service.description || '';
        document.getElementById('price_from').value = service.price_from || '';
        document.getElementById('price_to').value = service.price_to || '';
        document.getElementById('price_unit').value = service.price_unit || 'fixed';
        document.getElementById('duration_hours').value = service.duration_hours || '';
        document.getElementById('is_active').checked = !!parseInt(service.is_active);
        document.getElementById('modalSubmitBtn').textContent = 'Gem ændringer';
        document.getElementById('serviceModal').classList.add('active');
    }

    function closeModal() {
        document.getElementById('serviceModal').classList.remove('active');
    }

    document.getElementById('serviceModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    // ── Image Modal ───────────────────────────────────────────
    function openImageModal(serviceId) {
        document.getElementById('imgUploadServiceId').value = serviceId;
        var images = serviceImagesData[serviceId] || [];
        var count = images.length;

        document.getElementById('imgModalTitle').textContent = 'Billeder (' + count + '/' + maxImagesPerService + ')';

        var container = document.getElementById('imgGridContainer');

        if (count === 0) {
            container.innerHTML = '<div class="img-empty">Ingen billeder endnu. Upload ovenfor.</div>';
        } else {
            var html = '<div class="img-count-label">' + count + ' af ' + maxImagesPerService + ' billeder</div><div class="img-grid">';
            for (var i = 0; i < images.length; i++) {
                var img = images[i];
                var isPrimary = parseInt(img.is_primary);
                html += '<div class="img-grid-item' + (isPrimary ? ' is-primary' : '') + '">';
                html += '<img src="/uploads/vendors/services/' + encodeURI(img.filename) + '" alt="" loading="lazy">';
                if (isPrimary) {
                    html += '<div class="img-primary-label">Primær</div>';
                }
                html += '<div class="img-overlay">';
                html += '<button type="button" class="star-btn' + (isPrimary ? ' active' : '') + '" onclick="setPrimary(' + img.id + ')" title="Sæt som primær">';
                html += '<svg fill="' + (isPrimary ? 'currentColor' : 'none') + '" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path></svg>';
                html += '</button>';
                html += '<button type="button" class="del-btn" onclick="deleteImage(' + img.id + ')" title="Slet billede">';
                html += '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>';
                html += '</button>';
                html += '</div>';
                html += '</div>';
            }
            html += '</div>';
            container.innerHTML = html;
        }

        // Show/hide upload zone based on count
        document.getElementById('imgUploadZone').style.display = count >= maxImagesPerService ? 'none' : '';

        document.getElementById('imageModal').classList.add('active');
    }

    function closeImageModal() {
        document.getElementById('imageModal').classList.remove('active');
    }

    document.getElementById('imageModal').addEventListener('click', function(e) {
        if (e.target === this) closeImageModal();
    });

    function deleteImage(imageId) {
        if (!confirm('Slet dette billede?')) return;
        document.getElementById('deleteImageId').value = imageId;
        document.getElementById('deleteImageForm').submit();
    }

    function setPrimary(imageId) {
        document.getElementById('setPrimaryImageId').value = imageId;
        document.getElementById('setPrimaryForm').submit();
    }

    // ── Upload zone interactions ──────────────────────────────
    (function() {
        var zone = document.getElementById('imgUploadZone');
        var input = document.getElementById('imgFileInput');
        var form = document.getElementById('imgUploadForm');

        if (!zone || !input || !form) return;

        zone.addEventListener('click', function() { input.click(); });

        input.addEventListener('change', function() {
            if (input.files.length > 0) form.submit();
        });

        zone.addEventListener('dragover', function(e) {
            e.preventDefault();
            zone.classList.add('dragover');
        });

        zone.addEventListener('dragleave', function() {
            zone.classList.remove('dragover');
        });

        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            zone.classList.remove('dragover');
            if (e.dataTransfer.files.length > 0) {
                input.files = e.dataTransfer.files;
                form.submit();
            }
        });
    })();

    // ── Escape key ────────────────────────────────────────────
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
            closeImageModal();
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/vendor-footer.php'; ?>

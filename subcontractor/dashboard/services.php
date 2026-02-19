<?php
/**
 * Vendor Services Management
 * CRUD for vendor services/pricing.
 * Lists, adds, edits, deletes, toggles, and reorders vendor services.
 */

$pageTitle = 'Ydelser';

require_once __DIR__ . '/../includes/vendor-header.php';
require_once __DIR__ . '/../includes/vendor-validation.php';

$db = getDB();
$errors = [];
$editService = null;

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
                // Get next sort_order
                $sortStmt = $db->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM vendor_services WHERE vendor_id = ?");
                $sortStmt->execute([$vendorId]);
                $nextSort = (int) $sortStmt->fetchColumn();

                $stmt = $db->prepare("
                    INSERT INTO vendor_services (vendor_id, title, description, price_from, price_to, price_unit, duration_hours, is_active, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $vendorId,
                    sanitizeString($_POST['title']),
                    !empty($_POST['description']) ? trim($_POST['description']) : null,
                    (float) $_POST['price_from'],
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
        // If validation errors, fall through to display the page with errors and modal open
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
                    sanitizeString($_POST['title']),
                    !empty($_POST['description']) ? trim($_POST['description']) : null,
                    (float) $_POST['price_from'],
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
        // If validation errors, fall through and re-open the edit modal
    }

    // --- DELETE ---
    if ($action === 'delete') {
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        try {
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

    // --- REORDER ---
    if ($action === 'reorder') {
        $order = $_POST['order'] ?? [];
        if (is_array($order)) {
            try {
                $stmt = $db->prepare("UPDATE vendor_services SET sort_order = ? WHERE id = ? AND vendor_id = ?");
                foreach ($order as $position => $id) {
                    $stmt->execute([(int) $position, (int) $id, $vendorId]);
                }
                setFlash('success', 'Rækkefølge opdateret.');
            } catch (Exception $e) {
                error_log("Failed to reorder vendor services: " . $e->getMessage());
                setFlash('error', 'Der opstod en fejl. Prøv igen.');
            }
        }
        redirect('/subcontractor/dashboard/services.php');
    }
}

// ============================================================
// If editing (validation failed), load the service to pre-fill
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
// Load services for display
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

// Price unit labels
$priceUnitLabels = [
    'fixed' => 'fast pris',
    'per_person' => 'pr. person',
    'per_hour' => 'pr. time',
];
?>

<!-- Page Header -->
<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Ydelser</h1>
        <p class="page-subtitle">Administrer dine ydelser og priser</p>
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
    <!-- Empty state -->
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                </svg>
            </div>
            <h3>Ingen ydelser endnu</h3>
            <p>Tilføj dine ydelser, så arrangører kan se hvad du tilbyder og til hvilke priser.</p>
            <button type="button" class="btn btn-primary" onclick="openAddModal()">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Tilføj din første ydelse
            </button>
        </div>
    </div>
<?php else: ?>
    <!-- Services table -->
    <div class="card">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Ydelse</th>
                        <th>Pris</th>
                        <th>Varighed</th>
                        <th>Status</th>
                        <th>Handlinger</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                        <tr>
                            <td>
                                <div class="table-cell-primary"><?= htmlspecialchars($service['title']) ?></div>
                                <?php if (!empty($service['description'])): ?>
                                    <div class="table-cell-secondary" style="white-space: normal; max-width: 300px;">
                                        <?= htmlspecialchars(mb_strimwidth($service['description'], 0, 80, '...')) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="table-cell-primary">
                                    <?php if (!empty($service['price_to']) && (float) $service['price_to'] > 0): ?>
                                        <?= number_format((float) $service['price_from'], 0, ',', '.') ?> - <?= number_format((float) $service['price_to'], 0, ',', '.') ?> kr
                                    <?php else: ?>
                                        Fra <?= number_format((float) $service['price_from'], 0, ',', '.') ?> kr
                                    <?php endif; ?>
                                </div>
                                <div class="table-cell-secondary">
                                    <?= htmlspecialchars($priceUnitLabels[$service['price_unit']] ?? 'fast pris') ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($service['duration_hours']) && (float) $service['duration_hours'] > 0): ?>
                                    <?= rtrim(rtrim(number_format((float) $service['duration_hours'], 1, ',', '.'), '0'), ',') ?> timer
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($service['is_active']): ?>
                                    <span class="badge badge-active">Aktiv</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">Inaktiv</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <!-- Edit -->
                                    <button type="button" class="table-action-btn" title="Rediger"
                                        onclick="openEditModal(<?= htmlspecialchars(json_encode($service), ENT_QUOTES, 'UTF-8') ?>)">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </button>

                                    <!-- Toggle active/inactive -->
                                    <form method="POST" style="display:inline;">
                                        <?= vendorCsrfField() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="service_id" value="<?= (int) $service['id'] ?>">
                                        <button type="submit" class="table-action-btn" title="<?= $service['is_active'] ? 'Deaktiver' : 'Aktiver' ?>">
                                            <?php if ($service['is_active']): ?>
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878l4.242 4.242M21 21l-3.121-3.121"></path>
                                                </svg>
                                            <?php else: ?>
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                            <?php endif; ?>
                                        </button>
                                    </form>

                                    <!-- Delete -->
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Er du sikker på, at du vil slette denne ydelse?');">
                                        <?= vendorCsrfField() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="service_id" value="<?= (int) $service['id'] ?>">
                                        <button type="submit" class="table-action-btn" title="Slet" style="color: var(--error);">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
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
                <!-- Title -->
                <div class="form-group">
                    <label class="form-label" for="title">Titel <span class="required">*</span></label>
                    <input
                        type="text"
                        id="title"
                        name="title"
                        class="form-input"
                        placeholder="F.eks. Partytelt 6x12m"
                        value="<?= htmlspecialchars($modalData['title']) ?>"
                        required
                    >
                </div>

                <!-- Description -->
                <div class="form-group">
                    <label class="form-label" for="description">Beskrivelse</label>
                    <textarea
                        id="description"
                        name="description"
                        class="form-textarea"
                        placeholder="Beskriv ydelsen i detaljer..."
                        rows="3"
                    ><?= htmlspecialchars($modalData['description']) ?></textarea>
                </div>

                <!-- Price from / Price to -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="price_from">Pris fra <span class="required">*</span></label>
                        <input
                            type="number"
                            id="price_from"
                            name="price_from"
                            class="form-input"
                            placeholder="0,00"
                            step="0.01"
                            min="0"
                            value="<?= htmlspecialchars($modalData['price_from']) ?>"
                            required
                        >
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="price_to">Pris til <span style="font-weight:400; color:var(--text-secondary); font-size:13px;">(valgfrit)</span></label>
                        <input
                            type="number"
                            id="price_to"
                            name="price_to"
                            class="form-input"
                            placeholder="0,00"
                            step="0.01"
                            min="0"
                            value="<?= htmlspecialchars($modalData['price_to']) ?>"
                        >
                    </div>
                </div>

                <!-- Price unit / Duration -->
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
                        <label class="form-label" for="duration_hours">Varighed i timer <span style="font-weight:400; color:var(--text-secondary); font-size:13px;">(valgfrit)</span></label>
                        <input
                            type="number"
                            id="duration_hours"
                            name="duration_hours"
                            class="form-input"
                            placeholder="F.eks. 2,5"
                            step="0.5"
                            min="0"
                            value="<?= htmlspecialchars($modalData['duration_hours']) ?>"
                        >
                    </div>
                </div>

                <!-- Is active -->
                <div class="form-group">
                    <div class="form-checkbox-group">
                        <input
                            type="checkbox"
                            id="is_active"
                            name="is_active"
                            class="form-checkbox"
                            value="1"
                            <?= $modalData['is_active'] ? 'checked' : '' ?>
                        >
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

<script>
    // Open Add Modal
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

    // Open Edit Modal with pre-filled data
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

    // Close Modal
    function closeModal() {
        document.getElementById('serviceModal').classList.remove('active');
    }

    // Close modal when clicking the overlay background
    document.getElementById('serviceModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/vendor-footer.php'; ?>

<?php
/**
 * Toastmaster Management Page
 * Included by manage.php
 */

// Check if feature is available
if (!$hasToastmaster): ?>
<div class="upgrade-notice">
    <div class="upgrade-notice-content">
        <h4>Toastmaster er en premium-funktion</h4>
        <p>Opgrader til Pro eller hojere for at fa adgang til toastmaster-funktionen.</p>
    </div>
    <a href="/app/account/subscription.php" class="btn">Opgrader nu</a>
</div>
<?php return; endif;

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');

    if (!verifyAccountCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add_item') {
        $guestName = trim($_POST['guest_name'] ?? '');
        $itemType = $_POST['item_type'] ?? 'tale';
        $title = trim($_POST['title'] ?? '');
        $duration = max(1, min(30, (int)($_POST['duration'] ?? 5)));

        if ($guestName) {
            $stmt = $db->prepare("SELECT MAX(sort_order) FROM toastmaster_items WHERE event_id = ?");
            $stmt->execute([$eventId]);
            $maxOrder = $stmt->fetchColumn() ?? 0;

            $stmt = $db->prepare("
                INSERT INTO toastmaster_items (event_id, guest_name, item_type, title, duration_minutes, status, sort_order)
                VALUES (?, ?, ?, ?, ?, 'approved', ?)
            ");
            $stmt->execute([$eventId, $guestName, $itemType, $title ?: null, $duration, $maxOrder + 1]);
            echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
            exit;
        }
    }

    if ($action === 'delete_item') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        if ($itemId) {
            $stmt = $db->prepare("DELETE FROM toastmaster_items WHERE id = ? AND event_id = ?");
            $stmt->execute([$itemId, $eventId]);
            echo json_encode(['success' => true]);
            exit;
        }
    }

    if ($action === 'update_status') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $status = $_POST['status'] ?? 'pending';
        if ($itemId && in_array($status, ['pending', 'approved', 'completed'])) {
            $stmt = $db->prepare("UPDATE toastmaster_items SET status = ? WHERE id = ? AND event_id = ?");
            $stmt->execute([$status, $itemId, $eventId]);
            echo json_encode(['success' => true]);
            exit;
        }
    }

    if ($action === 'set_toastmaster') {
        $guestId = (int)($_POST['guest_id'] ?? 0);

        // Verify guest belongs to this event and has RSVP'd yes
        if ($guestId > 0) {
            $stmt = $db->prepare("SELECT id FROM guests WHERE id = ? AND event_id = ? AND rsvp_status = 'yes'");
            $stmt->execute([$guestId, $eventId]);
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Invalid guest']);
                exit;
            }
        }

        // Update event with toastmaster
        $stmt = $db->prepare("UPDATE events SET toastmaster_guest_id = ? WHERE id = ?");
        $stmt->execute([$guestId ?: null, $eventId]);

        // If guest selected, also generate access code for them
        if ($guestId > 0) {
            $stmt = $db->prepare("SELECT name, email FROM guests WHERE id = ?");
            $stmt->execute([$guestId]);
            $guest = $stmt->fetch();

            // Check if access already exists
            $stmt = $db->prepare("SELECT id FROM toastmaster_access WHERE event_id = ? AND guest_id = ?");
            $stmt->execute([$eventId, $guestId]);
            if (!$stmt->fetch()) {
                $code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
                $stmt = $db->prepare("INSERT INTO toastmaster_access (event_id, guest_id, access_code, name, email, is_primary) VALUES (?, ?, ?, ?, ?, 1)");
                $stmt->execute([$eventId, $guestId, $code, $guest['name'], $guest['email']]);
            }
        }

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'generate_access') {
        $code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
        $name = trim($_POST['name'] ?? '') ?: 'Toastmaster';
        $email = trim($_POST['email'] ?? '') ?: null;

        $stmt = $db->prepare("SELECT COUNT(*) FROM toastmaster_access WHERE event_id = ?");
        $stmt->execute([$eventId]);
        $isPrimary = ($stmt->fetchColumn() == 0) ? 1 : 0;

        $stmt = $db->prepare("INSERT INTO toastmaster_access (event_id, access_code, name, email, is_primary) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$eventId, $code, $name, $email, $isPrimary]);
        echo json_encode(['success' => true, 'code' => $code, 'id' => $db->lastInsertId()]);
        exit;
    }

    if ($action === 'delete_access') {
        $accessId = (int)($_POST['access_id'] ?? 0);
        if ($accessId) {
            $stmt = $db->prepare("DELETE FROM toastmaster_access WHERE id = ? AND event_id = ?");
            $stmt->execute([$accessId, $eventId]);
            echo json_encode(['success' => true]);
            exit;
        }
    }

    echo json_encode(['success' => false]);
    exit;
}

// Item type labels
$typeLabels = [
    'tale' => ['label' => 'Tale', 'icon' => '🎤'],
    'sang' => ['label' => 'Sang', 'icon' => '🎵'],
    'sketch' => ['label' => 'Sketch', 'icon' => '🎭'],
    'quiz' => ['label' => 'Quiz', 'icon' => '❓'],
    'leg' => ['label' => 'Leg', 'icon' => '🎲'],
    'musik' => ['label' => 'Musik', 'icon' => '🎸'],
    'andet' => ['label' => 'Andet', 'icon' => '✨']
];

// Get all items
$stmt = $db->prepare("SELECT * FROM toastmaster_items WHERE event_id = ? ORDER BY sort_order, created_at");
$stmt->execute([$eventId]);
$items = $stmt->fetchAll();

// Get toastmaster access codes
$stmt = $db->prepare("SELECT * FROM toastmaster_access WHERE event_id = ? ORDER BY created_at DESC");
$stmt->execute([$eventId]);
$accessCodes = $stmt->fetchAll();

// Get confirmed guests (RSVP = yes) for toastmaster selection
$stmt = $db->prepare("SELECT id, name, email FROM guests WHERE event_id = ? AND rsvp_status = 'yes' ORDER BY name");
$stmt->execute([$eventId]);
$confirmedGuests = $stmt->fetchAll();

// Get current designated toastmaster
$designatedToastmaster = $event['toastmaster_guest_id'] ?? null;

// Stats
$totalItems = count($items);
$pendingItems = count(array_filter($items, function($i) { return $i['status'] === 'pending'; }));
$completedItems = count(array_filter($items, function($i) { return $i['status'] === 'completed'; }));
$totalDuration = array_sum(array_column($items, 'duration_minutes'));

$csrfToken = generateAccountCsrfToken();
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
?>

<div class="page-header-actions">
    <div>
        <h2 class="section-title">Toastmaster</h2>
        <p class="section-subtitle">
            <?= $totalItems ?> indslag
            <?php if ($pendingItems > 0): ?>
                &middot; <span style="color: #f59e0b;"><?= $pendingItems ?> afventer</span>
            <?php endif; ?>
            &middot; Ca. <?= $totalDuration ?> min
        </p>
    </div>
    <div class="header-buttons">
        <button type="button" class="btn btn-secondary" onclick="showAccessModal()">Toastmaster-adgang</button>
        <button type="button" class="btn btn-primary" onclick="showAddModal()">+ Tilføj indslag</button>
    </div>
</div>

<!-- Toastmaster Selection -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header" style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
        <span style="font-size: 28px;">👑</span>
        <div>
            <h3 style="font-size: 18px; font-weight: 600; margin: 0;">Vælg Toastmaster</h3>
            <p style="font-size: 13px; color: var(--charcoal-light); margin: 4px 0 0;">Vælg en af de tilmeldte gæster som toastmaster</p>
        </div>
    </div>

    <?php if (empty($confirmedGuests)): ?>
        <div style="padding: 24px; text-align: center; background: var(--cream); border-radius: 12px;">
            <p style="color: var(--charcoal-light);">Ingen gæster har bekræftet deltagelse endnu.</p>
            <p style="font-size: 13px; color: var(--charcoal-light); margin-top: 8px;">Når gæster tilmelder sig, kan du vælge en toastmaster her.</p>
        </div>
    <?php else: ?>
        <div class="toastmaster-select-wrapper">
            <select id="toastmaster-select" class="form-input" onchange="setToastmaster(this.value)" style="font-size: 16px; padding: 16px;">
                <option value="0">-- Vælg toastmaster --</option>
                <?php foreach ($confirmedGuests as $guest): ?>
                    <option value="<?= $guest['id'] ?>" <?= $designatedToastmaster == $guest['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($guest['name']) ?>
                        <?php if (!empty($guest['email'])): ?>
                            (<?= htmlspecialchars($guest['email']) ?>)
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php if ($designatedToastmaster):
                $stmt = $db->prepare("SELECT name FROM guests WHERE id = ?");
                $stmt->execute([$designatedToastmaster]);
                $tmGuest = $stmt->fetch();
            ?>
                <div class="toastmaster-selected" style="margin-top: 16px; padding: 16px; background: linear-gradient(135deg, var(--sage-light), var(--sage)); border-radius: 12px; color: white;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span style="font-size: 32px;">🎤</span>
                        <div>
                            <strong style="font-size: 16px;"><?= htmlspecialchars($tmGuest['name'] ?? 'Ukendt') ?></strong>
                            <p style="font-size: 13px; opacity: 0.9; margin-top: 2px;">Er valgt som toastmaster</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Stats -->
<div class="stats-row">
    <div class="stat-card">
        <span class="stat-value"><?= $totalItems ?></span>
        <span class="stat-label">Indslag i alt</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= $pendingItems ?></span>
        <span class="stat-label">Afventer</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= $completedItems ?></span>
        <span class="stat-label">Gennemfort</span>
    </div>
    <div class="stat-card">
        <span class="stat-value">~<?= $totalDuration ?> min</span>
        <span class="stat-label">Samlet tid</span>
    </div>
</div>

<!-- Items List -->
<div class="card">
    <?php if (empty($items)): ?>
        <div class="empty-state">
            <span style="font-size: 48px; margin-bottom: 16px; display: block;">🎤</span>
            <h3>Ingen indslag endnu</h3>
            <p>Tilfoj indslag manuelt eller del tilmeldingslinket med gaesterne</p>
            <button type="button" class="btn btn-primary" style="margin-top: 16px;" onclick="showAddModal()">+ Tilfoj indslag</button>
        </div>
    <?php else: ?>
        <div class="tm-list">
            <?php foreach ($items as $item): ?>
                <div class="tm-item tm-item--<?= $item['status'] ?>" data-id="<?= $item['id'] ?>">
                    <div class="tm-item__icon"><?= $typeLabels[$item['item_type']]['icon'] ?? '✨' ?></div>

                    <div class="tm-item__content">
                        <div class="tm-item__title">
                            <?= htmlspecialchars($item['title'] ?: $typeLabels[$item['item_type']]['label']) ?>
                            <?php if ($item['is_secret']): ?>
                                <span class="badge" title="Hemmeligt">🤫</span>
                            <?php endif; ?>
                        </div>
                        <div class="tm-item__meta">
                            <span><?= htmlspecialchars($item['guest_name']) ?></span>
                            <span>&middot;</span>
                            <span><?= $item['duration_minutes'] ?> min</span>
                        </div>
                    </div>

                    <div class="tm-item__status">
                        <select onchange="updateStatus(<?= $item['id'] ?>, this.value)" class="form-input">
                            <option value="pending" <?= $item['status'] === 'pending' ? 'selected' : '' ?>>⏳ Afventer</option>
                            <option value="approved" <?= $item['status'] === 'approved' ? 'selected' : '' ?>>✓ Godkendt</option>
                            <option value="completed" <?= $item['status'] === 'completed' ? 'selected' : '' ?>>✅ Gennemfort</option>
                        </select>
                    </div>

                    <button type="button" class="btn btn-ghost btn-sm danger" onclick="deleteItem(<?= $item['id'] ?>)" title="Slet">🗑️</button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Add Item Modal -->
<div class="modal-overlay" id="addModal" style="display: none;">
    <div class="modal" style="max-width: 450px;">
        <div class="modal-header">
            <h3>Tilfoj indslag</h3>
            <button type="button" class="modal-close" onclick="hideAddModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Deltager *</label>
                <select id="add-guest-id" class="form-input">
                    <option value="">-- Vælg deltager --</option>
                    <?php foreach ($confirmedGuests as $guest): ?>
                        <option value="<?= $guest['id'] ?>" data-name="<?= htmlspecialchars($guest['name']) ?>">
                            <?= htmlspecialchars($guest['name']) ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="other">Anden (indtast navn)</option>
                </select>
                <input type="text" id="add-guest-name" class="form-input" placeholder="Indtast navn" style="margin-top: 8px; display: none;">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select id="add-item-type" class="form-input">
                        <?php foreach ($typeLabels as $value => $type): ?>
                            <option value="<?= $value ?>"><?= $type['icon'] ?> <?= $type['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Varighed</label>
                    <select id="add-duration" class="form-input">
                        <option value="2">2 min</option>
                        <option value="5" selected>5 min</option>
                        <option value="10">10 min</option>
                        <option value="15">15 min</option>
                        <option value="20">20+ min</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Titel (valgfrit)</label>
                <input type="text" id="add-title" class="form-input" placeholder="F.eks. 'Tale fra bedsteforaeldre'">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="hideAddModal()">Annuller</button>
            <button type="button" class="btn btn-primary" onclick="addItem()">Tilfoj</button>
        </div>
    </div>
</div>

<!-- Access Modal -->
<div class="modal-overlay" id="accessModal" style="display: none;">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Toastmaster-adgang</h3>
            <button type="button" class="modal-close" onclick="hideAccessModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p style="margin-bottom: 16px; color: var(--gray-600);">
                Giv toastmasteren adgang til at se programmet og markere indslag som gennemfort.
            </p>

            <?php if (!empty($accessCodes)): ?>
                <div class="access-list">
                    <?php foreach ($accessCodes as $access): ?>
                        <div class="access-item <?= !empty($access['is_primary']) ? 'primary' : '' ?>">
                            <div>
                                <strong><?= htmlspecialchars($access['name']) ?></strong>
                                <?php if (!empty($access['is_primary'])): ?>
                                    <span class="badge-primary">Hoved</span>
                                <?php endif; ?>
                                <div style="font-size: 12px; color: var(--gray-500); margin-top: 4px;">
                                    <?= $baseUrl ?>/toastmaster/?kode=<?= $access['access_code'] ?>
                                </div>
                            </div>
                            <div class="access-actions">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="copyAccessLink('<?= $access['access_code'] ?>')">Kopier</button>
                                <button type="button" class="btn btn-ghost btn-sm danger" onclick="deleteAccess(<?= $access['id'] ?>)">🗑️</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="color: var(--gray-500); padding: 16px; text-align: center;">Ingen adgangskoder oprettet endnu</p>
            <?php endif; ?>

            <div style="border-top: 1px solid var(--gray-200); padding-top: 16px; margin-top: 16px;">
                <h4 style="font-size: 14px; font-weight: 600; margin-bottom: 12px;">Opret ny adgang</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 8px;">
                    <input type="text" id="new-access-name" class="form-input" placeholder="Navn">
                    <input type="email" id="new-access-email" class="form-input" placeholder="Email (valgfrit)">
                </div>
                <button type="button" class="btn btn-primary" style="width: 100%;" onclick="generateAccess()">Opret adgang</button>
            </div>
        </div>
    </div>
</div>

<style>
    .stats-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }

    .stat-card {
        background: white;
        border: 1px solid var(--gray-200);
        border-radius: 12px;
        padding: 16px;
        text-align: center;
    }

    .stat-value {
        display: block;
        font-size: 24px;
        font-weight: 600;
        color: var(--gray-900);
    }

    .stat-label {
        font-size: 13px;
        color: var(--gray-500);
    }

    .tm-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .tm-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        background: var(--gray-50);
        border-radius: 8px;
        border-left: 4px solid var(--gray-300);
    }

    .tm-item--pending {
        border-left-color: #f59e0b;
    }

    .tm-item--approved {
        border-left-color: var(--primary);
    }

    .tm-item--completed {
        border-left-color: #10b981;
        opacity: 0.7;
    }

    .tm-item__icon {
        font-size: 24px;
    }

    .tm-item__content {
        flex: 1;
        min-width: 0;
    }

    .tm-item__title {
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .tm-item__meta {
        font-size: 13px;
        color: var(--gray-500);
        display: flex;
        gap: 6px;
    }

    .tm-item__status select {
        font-size: 13px;
        padding: 6px 10px;
    }

    .badge {
        font-size: 12px;
    }

    .access-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-bottom: 16px;
    }

    .access-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px;
        background: var(--gray-50);
        border-radius: 8px;
        gap: 12px;
    }

    .access-item.primary {
        background: rgba(102, 126, 234, 0.1);
        border: 2px solid var(--primary);
    }

    .access-actions {
        display: flex;
        gap: 4px;
        flex-shrink: 0;
    }

    .badge-primary {
        display: inline-block;
        padding: 2px 8px;
        font-size: 10px;
        font-weight: 600;
        background: var(--primary);
        color: white;
        border-radius: 10px;
        margin-left: 8px;
    }

    @media (max-width: 768px) {
        .stats-row {
            grid-template-columns: repeat(2, 1fr);
        }

        .tm-item {
            flex-wrap: wrap;
        }

        .tm-item__status {
            width: 100%;
            margin-top: 8px;
        }
    }
</style>

<script>
const csrfToken = '<?= $csrfToken ?>';
const eventId = <?= $eventId ?>;
const baseUrl = '<?= $baseUrl ?>';

function showAddModal() {
    document.getElementById('addModal').style.display = 'flex';
}

function hideAddModal() {
    document.getElementById('addModal').style.display = 'none';
}

function showAccessModal() {
    document.getElementById('accessModal').style.display = 'flex';
}

function hideAccessModal() {
    document.getElementById('accessModal').style.display = 'none';
}

// Toggle other name input
document.getElementById('add-guest-id').addEventListener('change', function() {
    const otherInput = document.getElementById('add-guest-name');
    if (this.value === 'other') {
        otherInput.style.display = 'block';
        otherInput.focus();
    } else {
        otherInput.style.display = 'none';
        otherInput.value = '';
    }
});

function setToastmaster(guestId) {
    fetch('?id=' + eventId + '&page=toastmaster', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': csrfToken
        },
        body: `action=set_toastmaster&guest_id=${guestId}&csrf_token=${csrfToken}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

function addItem() {
    const guestSelect = document.getElementById('add-guest-id');
    const guestNameInput = document.getElementById('add-guest-name');
    let guestName = '';

    if (guestSelect.value === 'other') {
        guestName = guestNameInput.value;
    } else if (guestSelect.value) {
        guestName = guestSelect.options[guestSelect.selectedIndex].dataset.name;
    }

    const itemType = document.getElementById('add-item-type').value;
    const title = document.getElementById('add-title').value;
    const duration = document.getElementById('add-duration').value;

    if (!guestName) {
        alert('Vælg venligst en deltager');
        return;
    }

    fetch('?id=' + eventId + '&page=toastmaster', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': csrfToken
        },
        body: `action=add_item&guest_name=${encodeURIComponent(guestName)}&item_type=${itemType}&title=${encodeURIComponent(title)}&duration=${duration}&csrf_token=${csrfToken}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

function deleteItem(itemId) {
    if (!confirm('Slet dette indslag?')) return;

    fetch('?id=' + eventId + '&page=toastmaster', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': csrfToken
        },
        body: `action=delete_item&item_id=${itemId}&csrf_token=${csrfToken}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

function updateStatus(itemId, status) {
    fetch('?id=' + eventId + '&page=toastmaster', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': csrfToken
        },
        body: `action=update_status&item_id=${itemId}&status=${status}&csrf_token=${csrfToken}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

function generateAccess() {
    const name = document.getElementById('new-access-name').value || 'Toastmaster';
    const email = document.getElementById('new-access-email').value || '';

    fetch('?id=' + eventId + '&page=toastmaster', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': csrfToken
        },
        body: `action=generate_access&name=${encodeURIComponent(name)}&email=${encodeURIComponent(email)}&csrf_token=${csrfToken}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

function deleteAccess(accessId) {
    if (!confirm('Slet denne adgang?')) return;

    fetch('?id=' + eventId + '&page=toastmaster', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': csrfToken
        },
        body: `action=delete_access&access_id=${accessId}&csrf_token=${csrfToken}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

function copyAccessLink(code) {
    const link = baseUrl + '/toastmaster/?kode=' + code;
    navigator.clipboard.writeText(link).then(() => {
        alert('Link kopieret!');
    });
}

document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
});
</script>

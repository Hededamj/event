<?php
/**
 * Admin - Toastmaster Overview
 */

// Handle AJAX before header
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {

    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/../includes/auth.php';

    if (!isLoggedIn()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }

    $db = getDB();
    $eventId = getCurrentEventId();
    $action = $_POST['action'] ?? '';

    header('Content-Type: application/json');

    if ($action === 'update_order') {
        $order = json_decode($_POST['order'] ?? '[]', true);
        if (is_array($order)) {
            foreach ($order as $index => $itemId) {
                $stmt = $db->prepare("UPDATE toastmaster_items SET sort_order = ? WHERE id = ? AND event_id = ?");
                $stmt->execute([$index, $itemId, $eventId]);
            }
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

    if ($action === 'delete_item') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        if ($itemId) {
            $stmt = $db->prepare("DELETE FROM toastmaster_items WHERE id = ? AND event_id = ?");
            $stmt->execute([$itemId, $eventId]);
            echo json_encode(['success' => true]);
            exit;
        }
    }

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

    if ($action === 'generate_access') {
        // Check if toastmaster_access table exists
        try {
            $db->exec("
                CREATE TABLE IF NOT EXISTS toastmaster_access (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    event_id INT NOT NULL,
                    access_code VARCHAR(20) NOT NULL,
                    name VARCHAR(255) DEFAULT 'Toastmaster',
                    email VARCHAR(255) DEFAULT NULL,
                    is_primary TINYINT(1) DEFAULT 0,
                    guest_id INT DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY (event_id, access_code),
                    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
                )
            ");
            // Add columns if they don't exist
            $db->exec("ALTER TABLE toastmaster_access ADD COLUMN IF NOT EXISTS email VARCHAR(255) DEFAULT NULL AFTER name");
            $db->exec("ALTER TABLE toastmaster_access ADD COLUMN IF NOT EXISTS is_primary TINYINT(1) DEFAULT 0 AFTER email");
            $db->exec("ALTER TABLE toastmaster_access ADD COLUMN IF NOT EXISTS guest_id INT DEFAULT NULL AFTER is_primary");
        } catch (Exception $e) {
            // Table might already exist or FK issue - continue anyway
        }

        // Generate new code
        $code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
        $name = trim($_POST['name'] ?? '') ?: 'Toastmaster';
        $email = trim($_POST['email'] ?? '') ?: null;

        // Check if this is the first toastmaster (should be primary)
        $stmt = $db->prepare("SELECT COUNT(*) FROM toastmaster_access WHERE event_id = ?");
        $stmt->execute([$eventId]);
        $existingCount = $stmt->fetchColumn();
        $isPrimary = ($existingCount == 0) ? 1 : 0;

        try {
            $stmt = $db->prepare("INSERT INTO toastmaster_access (event_id, access_code, name, email, is_primary) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$eventId, $code, $name, $email, $isPrimary]);
            echo json_encode(['success' => true, 'code' => $code, 'id' => $db->lastInsertId(), 'is_primary' => $isPrimary]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Kunne ikke oprette adgang: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'set_primary') {
        $accessId = (int)($_POST['access_id'] ?? 0);
        if ($accessId) {
            // Remove primary from all others
            $stmt = $db->prepare("UPDATE toastmaster_access SET is_primary = 0 WHERE event_id = ?");
            $stmt->execute([$eventId]);
            // Set this one as primary
            $stmt = $db->prepare("UPDATE toastmaster_access SET is_primary = 1 WHERE id = ? AND event_id = ?");
            $stmt->execute([$accessId, $eventId]);
            echo json_encode(['success' => true]);
            exit;
        }
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

require_once __DIR__ . '/../includes/admin-header.php';

// Create tables if they don't exist
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS toastmaster_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            guest_name VARCHAR(255) NOT NULL,
            item_type ENUM('tale', 'sang', 'sketch', 'quiz', 'leg', 'musik', 'andet') DEFAULT 'tale',
            title VARCHAR(255) DEFAULT NULL,
            description TEXT,
            duration_minutes INT DEFAULT 5,
            is_secret TINYINT(1) DEFAULT 0,
            status ENUM('pending', 'approved', 'completed') DEFAULT 'pending',
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
        )
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS toastmaster_access (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            access_code VARCHAR(20) NOT NULL,
            name VARCHAR(255) DEFAULT 'Toastmaster',
            email VARCHAR(255) DEFAULT NULL,
            is_primary TINYINT(1) DEFAULT 0,
            guest_id INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY (event_id, access_code),
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
        )
    ");
    // Add columns if they don't exist (for upgrades)
    $db->exec("ALTER TABLE toastmaster_access ADD COLUMN IF NOT EXISTS email VARCHAR(255) DEFAULT NULL AFTER name");
    $db->exec("ALTER TABLE toastmaster_access ADD COLUMN IF NOT EXISTS is_primary TINYINT(1) DEFAULT 0 AFTER email");
    $db->exec("ALTER TABLE toastmaster_access ADD COLUMN IF NOT EXISTS guest_id INT DEFAULT NULL AFTER is_primary");
} catch (Exception $e) {}

// Get all items
$stmt = $db->prepare("SELECT * FROM toastmaster_items WHERE event_id = ? ORDER BY sort_order, created_at");
$stmt->execute([$eventId]);
$items = $stmt->fetchAll();

// Get toastmaster access codes
$stmt = $db->prepare("SELECT * FROM toastmaster_access WHERE event_id = ? ORDER BY created_at DESC");
$stmt->execute([$eventId]);
$accessCodes = $stmt->fetchAll();

// Stats
$totalItems = count($items);
$pendingItems = count(array_filter($items, fn($i) => $i['status'] === 'pending'));
$completedItems = count(array_filter($items, fn($i) => $i['status'] === 'completed'));
$totalDuration = array_sum(array_column($items, 'duration_minutes'));

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

require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-header__title">Toastmaster</h1>
        <p class="page-header__subtitle">
            <?= $totalItems ?> indslag tilmeldt
            <?php if ($pendingItems > 0): ?>
                &middot; <span class="text-warning"><?= $pendingItems ?> afventer godkendelse</span>
            <?php endif; ?>
            &middot; Ca. <?= $totalDuration ?> min. total
        </p>
    </div>
    <div class="page-header__actions">
        <button onclick="openModal('access-modal')" class="btn btn--secondary">Toastmaster-adgang</button>
        <button onclick="openModal('add-modal')" class="btn btn--primary">+ Tilføj indslag</button>
    </div>
</div>

<!-- Quick Stats -->
<div class="stats-row mb-md">
    <div class="stat-card">
        <span class="stat-card__value"><?= $totalItems ?></span>
        <span class="stat-card__label">Indslag i alt</span>
    </div>
    <div class="stat-card">
        <span class="stat-card__value"><?= $pendingItems ?></span>
        <span class="stat-card__label">Afventer</span>
    </div>
    <div class="stat-card">
        <span class="stat-card__value"><?= $completedItems ?></span>
        <span class="stat-card__label">Gennemført</span>
    </div>
    <div class="stat-card">
        <span class="stat-card__value">~<?= $totalDuration ?> min</span>
        <span class="stat-card__label">Samlet tid</span>
    </div>
</div>

<!-- Items List -->
<div class="card">
    <?php if (empty($items)): ?>
        <div class="empty-state">
            <div class="empty-state__icon">🎤</div>
            <h3 class="empty-state__title">Ingen indslag endnu</h3>
            <p class="empty-state__text">
                Del linket til tilmelding med gæsterne, eller tilføj indslag manuelt
            </p>
            <div class="flex gap-sm justify-center mt-md">
                <button onclick="copyGuestLink()" class="btn btn--secondary">Kopiér gæstelink</button>
                <button onclick="openModal('add-modal')" class="btn btn--primary">+ Tilføj indslag</button>
            </div>
        </div>
    <?php else: ?>
        <div class="tm-list" id="items-list">
            <?php foreach ($items as $item): ?>
                <div class="tm-item tm-item--<?= $item['status'] ?>" data-id="<?= $item['id'] ?>">
                    <div class="tm-item__drag" title="Træk for at ændre rækkefølge">⋮⋮</div>

                    <div class="tm-item__icon"><?= $typeLabels[$item['item_type']]['icon'] ?? '✨' ?></div>

                    <div class="tm-item__content">
                        <div class="tm-item__title">
                            <?= escape($item['title'] ?: $typeLabels[$item['item_type']]['label']) ?>
                            <?php if ($item['is_secret']): ?>
                                <span class="badge badge--neutral" title="Hemmeligt for konfirmanden">🤫</span>
                            <?php endif; ?>
                        </div>
                        <div class="tm-item__meta">
                            <span><?= escape($item['guest_name']) ?></span>
                            <span>•</span>
                            <span><?= $item['duration_minutes'] ?> min</span>
                            <?php if ($item['description']): ?>
                                <span>•</span>
                                <span class="tm-item__desc" title="<?= escape($item['description']) ?>">
                                    <?= escape(mb_substr($item['description'], 0, 50)) ?><?= mb_strlen($item['description']) > 50 ? '...' : '' ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="tm-item__status">
                        <select onchange="updateStatus(<?= $item['id'] ?>, this.value)" class="form-input form-input--sm">
                            <option value="pending" <?= $item['status'] === 'pending' ? 'selected' : '' ?>>⏳ Afventer</option>
                            <option value="approved" <?= $item['status'] === 'approved' ? 'selected' : '' ?>>✓ Godkendt</option>
                            <option value="completed" <?= $item['status'] === 'completed' ? 'selected' : '' ?>>✅ Gennemført</option>
                        </select>
                    </div>

                    <div class="tm-item__actions">
                        <button onclick="deleteItem(<?= $item['id'] ?>)" class="btn btn--ghost btn--sm" title="Slet">🗑️</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-md text-center">
            <button onclick="copyGuestLink()" class="btn btn--secondary">📋 Kopiér tilmeldingslink til gæster</button>
        </div>
    <?php endif; ?>
</div>

<!-- Add Item Modal -->
<div id="add-modal" class="modal-overlay">
    <div class="modal">
        <div class="modal__header">
            <h2 class="modal__title">Tilføj indslag</h2>
            <button class="modal__close" onclick="closeModal('add-modal')">&times;</button>
        </div>
        <form onsubmit="addItem(event)">
            <div class="modal__body">
                <div class="form-group">
                    <label class="form-label">Navn *</label>
                    <input type="text" id="add-guest-name" class="form-input" required placeholder="Hvem står for indslaget?">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-md);">
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
                    <input type="text" id="add-title" class="form-input" placeholder="F.eks. 'Tale fra bedsteforældre'">
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--secondary" onclick="closeModal('add-modal')">Annuller</button>
                <button type="submit" class="btn btn--primary">Tilføj</button>
            </div>
        </form>
    </div>
</div>

<!-- Toastmaster Access Modal -->
<div id="access-modal" class="modal-overlay">
    <div class="modal" style="max-width: 550px;">
        <div class="modal__header">
            <h2 class="modal__title">Toastmaster-adgang</h2>
            <button class="modal__close" onclick="closeModal('access-modal')">&times;</button>
        </div>
        <div class="modal__body">
            <p class="mb-md">
                Giv toastmasteren adgang til at se programmet og markere indslag som gennemført -
                uden at kunne se budget og andre følsomme oplysninger.
            </p>

            <?php if (empty($accessCodes)): ?>
                <div class="empty-state" style="padding: var(--space-md);">
                    <p class="text-muted">Ingen adgangskoder oprettet endnu</p>
                    <p class="small text-muted">Den første du opretter bliver automatisk hovedtoastmaster.</p>
                </div>
            <?php else: ?>
                <div class="access-list mb-md">
                    <?php foreach ($accessCodes as $access): ?>
                        <div class="access-item <?= !empty($access['is_primary']) ? 'access-item--primary' : '' ?>">
                            <div>
                                <strong><?= escape($access['name']) ?></strong>
                                <?php if (!empty($access['is_primary'])): ?>
                                    <span class="badge badge--primary">Hovedtoastmaster</span>
                                <?php endif; ?>
                                <?php if (!empty($access['email'])): ?>
                                    <div class="small text-muted"><?= escape($access['email']) ?></div>
                                <?php endif; ?>
                                <div class="small text-muted">
                                    Link: <?= getBaseUrl() ?>/toastmaster/?kode=<?= $access['access_code'] ?>
                                </div>
                            </div>
                            <div class="access-item__actions">
                                <?php if (empty($access['is_primary'])): ?>
                                    <button onclick="setPrimary(<?= $access['id'] ?>)" class="btn btn--ghost btn--sm" title="Gør til hovedtoastmaster">⭐</button>
                                <?php endif; ?>
                                <button onclick="copyAccessLink('<?= $access['access_code'] ?>')" class="btn btn--secondary btn--sm">Kopiér</button>
                                <button onclick="deleteAccess(<?= $access['id'] ?>)" class="btn btn--ghost btn--sm">🗑️</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="small text-muted mb-md">
                    ⭐ Hovedtoastmaster modtager beskeder fra gæster. Klik på stjerne-ikonet for at skifte.
                </p>
            <?php endif; ?>

            <div style="border-top: 1px solid var(--color-border-soft); padding-top: var(--space-md);">
                <h4 class="mb-sm">Opret ny adgang</h4>
                <form onsubmit="generateAccess(event)">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <input type="text" id="new-access-name" class="form-input" placeholder="Navn (f.eks. 'Onkel Peter')">
                        <input type="email" id="new-access-email" class="form-input" placeholder="Email (til glemt kode)">
                    </div>
                    <button type="submit" class="btn btn--primary btn--block">Opret adgang</button>
                </form>
                <p class="small text-muted mt-sm">Email bruges til at sende koden igen, hvis toastmaster glemmer den.</p>
            </div>
        </div>
    </div>
</div>

<style>
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: var(--space-sm);
}

.stat-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border-soft);
    border-radius: var(--radius-md);
    padding: var(--space-sm);
    text-align: center;
}

.stat-card__value {
    display: block;
    font-size: var(--text-xl);
    font-weight: 600;
    color: var(--color-primary-deep);
}

.stat-card__label {
    font-size: var(--text-xs);
    color: var(--color-text-muted);
}

.tm-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.tm-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: var(--color-bg-subtle);
    border-radius: var(--radius-md);
    border-left: 4px solid var(--color-border);
}

.tm-item--pending {
    border-left-color: var(--color-warning);
}

.tm-item--approved {
    border-left-color: var(--color-primary);
}

.tm-item--completed {
    border-left-color: var(--color-success);
    opacity: 0.7;
}

.tm-item__drag {
    cursor: grab;
    color: var(--color-text-muted);
    padding: 0.25rem;
    user-select: none;
}

.tm-item__drag:active {
    cursor: grabbing;
}

.tm-item__icon {
    font-size: 1.5rem;
}

.tm-item__content {
    flex: 1;
    min-width: 0;
}

.tm-item__title {
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.tm-item__meta {
    font-size: 0.8rem;
    color: var(--color-text-muted);
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.tm-item__desc {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.tm-item__status select {
    font-size: 0.8rem;
    padding: 0.25rem 0.5rem;
}

.form-input--sm {
    font-size: 0.85rem;
    padding: 0.35rem 0.5rem;
}

.access-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.access-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: var(--color-bg-subtle);
    border-radius: var(--radius-md);
    gap: 1rem;
}

.access-item__actions {
    display: flex;
    gap: 0.25rem;
    flex-shrink: 0;
}

.access-item--primary {
    background: var(--color-primary-pale);
    border: 2px solid var(--color-primary);
}

.badge--primary {
    display: inline-block;
    padding: 0.15rem 0.5rem;
    font-size: 0.65rem;
    font-weight: 600;
    background: var(--color-primary);
    color: white;
    border-radius: 10px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin-left: 0.5rem;
    vertical-align: middle;
}

@media (max-width: 768px) {
    .tm-item {
        flex-wrap: wrap;
    }

    .tm-item__status,
    .tm-item__actions {
        width: 100%;
        margin-top: 0.5rem;
    }

    .tm-item__status {
        order: 1;
    }
}
</style>

<script>
// Sortable list
const list = document.getElementById('items-list');
if (list) {
    let draggedItem = null;

    list.querySelectorAll('.tm-item').forEach(item => {
        const handle = item.querySelector('.tm-item__drag');

        handle.addEventListener('mousedown', () => {
            item.draggable = true;
        });

        item.addEventListener('dragstart', (e) => {
            draggedItem = item;
            item.style.opacity = '0.5';
        });

        item.addEventListener('dragend', () => {
            item.draggable = false;
            item.style.opacity = '1';
            saveOrder();
        });

        item.addEventListener('dragover', (e) => {
            e.preventDefault();
            const rect = item.getBoundingClientRect();
            const midY = rect.top + rect.height / 2;

            if (e.clientY < midY) {
                item.parentNode.insertBefore(draggedItem, item);
            } else {
                item.parentNode.insertBefore(draggedItem, item.nextSibling);
            }
        });
    });
}

function saveOrder() {
    const items = document.querySelectorAll('.tm-item');
    const order = Array.from(items).map(item => item.dataset.id);

    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'action=update_order&order=' + encodeURIComponent(JSON.stringify(order))
    });
}

function updateStatus(itemId, status) {
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: `action=update_status&item_id=${itemId}&status=${status}`
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

    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: `action=delete_item&item_id=${itemId}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

function addItem(e) {
    e.preventDefault();

    const guestName = document.getElementById('add-guest-name').value;
    const itemType = document.getElementById('add-item-type').value;
    const title = document.getElementById('add-title').value;
    const duration = document.getElementById('add-duration').value;

    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: `action=add_item&guest_name=${encodeURIComponent(guestName)}&item_type=${itemType}&title=${encodeURIComponent(title)}&duration=${duration}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

function generateAccess(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const originalText = btn.textContent;
    btn.textContent = 'Opretter...';
    btn.disabled = true;

    const name = document.getElementById('new-access-name').value || 'Toastmaster';
    const email = document.getElementById('new-access-email').value || '';

    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: `action=generate_access&name=${encodeURIComponent(name)}&email=${encodeURIComponent(email)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Adgang oprettet!');
            location.reload();
        } else {
            alert(data.error || 'Kunne ikke oprette adgang');
            btn.textContent = originalText;
            btn.disabled = false;
        }
    })
    .catch(err => {
        alert('Netværksfejl - prøv igen');
        btn.textContent = originalText;
        btn.disabled = false;
    });
}

function deleteAccess(accessId) {
    if (!confirm('Slet denne adgang?')) return;

    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: `action=delete_access&access_id=${accessId}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

function setPrimary(accessId) {
    if (!confirm('Gør denne person til hovedtoastmaster?')) return;

    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: `action=set_primary&access_id=${accessId}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Hovedtoastmaster opdateret!');
            location.reload();
        }
    });
}

function copyAccessLink(code) {
    const link = '<?= getBaseUrl() ?>/toastmaster/?kode=' + code;
    navigator.clipboard.writeText(link).then(() => {
        showToast('Link kopieret!');
    });
}

function copyGuestLink() {
    const link = '<?= getBaseUrl() ?>/guest/indslag.php';
    navigator.clipboard.writeText(link).then(() => {
        showToast('Tilmeldingslink kopieret!');
    });
}

function showToast(message) {
    const existing = document.querySelector('.toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => toast.classList.add('toast--visible'), 10);
    setTimeout(() => {
        toast.classList.remove('toast--visible');
        setTimeout(() => toast.remove(), 300);
    }, 2000);
}

function openModal(id) {
    document.getElementById(id).classList.add('modal-overlay--active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('modal-overlay--active');
}

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('sidebar--open');
    document.getElementById('sidebar-overlay').classList.toggle('sidebar-overlay--active');
}
</script>

</main>
</div>

<div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleSidebar()"></div>

</body>
</html>

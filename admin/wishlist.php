<?php
/**
 * Admin - Wishlist Management
 * Mobile-optimized for young users
 */
require_once __DIR__ . '/../includes/admin-header.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = !empty($_POST['price']) ? (float)$_POST['price'] : null;
        $link = trim($_POST['link'] ?? '');
        $imageUrl = trim($_POST['image_url'] ?? '');
        $priority = (int)($_POST['priority'] ?? 0);

        if ($title) {
            $stmt = $db->prepare("
                INSERT INTO wishlist_items (event_id, title, description, price, link, image_url, priority)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $eventId,
                $title,
                $description ?: null,
                $price,
                $link ?: null,
                $imageUrl ?: null,
                $priority
            ]);

            setFlash('success', 'Ønske tilføjet');
            redirect(BASE_PATH . '/admin/wishlist.php');
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = !empty($_POST['price']) ? (float)$_POST['price'] : null;
        $link = trim($_POST['link'] ?? '');
        $imageUrl = trim($_POST['image_url'] ?? '');
        $priority = (int)($_POST['priority'] ?? 0);

        if ($title && $id) {
            $stmt = $db->prepare("
                UPDATE wishlist_items
                SET title = ?, description = ?, price = ?, link = ?, image_url = ?, priority = ?
                WHERE id = ? AND event_id = ?
            ");
            $stmt->execute([
                $title,
                $description ?: null,
                $price,
                $link ?: null,
                $imageUrl ?: null,
                $priority,
                $id,
                $eventId
            ]);

            setFlash('success', 'Ønske opdateret');
            redirect(BASE_PATH . '/admin/wishlist.php');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $db->prepare("DELETE FROM wishlist_items WHERE id = ? AND event_id = ?");
            $stmt->execute([$id, $eventId]);

            setFlash('success', 'Ønske slettet');
            redirect(BASE_PATH . '/admin/wishlist.php');
        }
    } elseif ($action === 'unreserve') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $db->prepare("
                UPDATE wishlist_items
                SET reserved_by_guest_id = NULL, reserved_at = NULL
                WHERE id = ? AND event_id = ?
            ");
            $stmt->execute([$id, $eventId]);

            setFlash('success', 'Reservation fjernet');
            redirect(BASE_PATH . '/admin/wishlist.php');
        }
    }
}

// Get all wishlist items
$stmt = $db->prepare("
    SELECT w.*, g.name as reserved_by_name
    FROM wishlist_items w
    LEFT JOIN guests g ON w.reserved_by_guest_id = g.id
    WHERE w.event_id = ?
    ORDER BY w.priority DESC, w.created_at ASC
");
$stmt->execute([$eventId]);
$items = $stmt->fetchAll();

// Stats
$totalItems = count($items);
$reservedItems = count(array_filter($items, fn($i) => $i['reserved_by_guest_id'] !== null));
$totalValue = array_sum(array_map(fn($i) => $i['price'] ?? 0, $items));

// Check if user is confirmand (limited view - can't see reservations)
$hideReservations = isConfirmand();

$showAddModal = isset($_GET['action']) && $_GET['action'] === 'add';

require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<style>
/* Mobile-first wishlist styles */
.wishlist-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--space-md);
    gap: var(--space-sm);
    flex-wrap: wrap;
}

.wishlist-header__title {
    font-size: var(--text-xl);
    margin: 0;
}

.wishlist-header__subtitle {
    color: var(--color-text-muted);
    font-size: var(--text-sm);
    margin-top: var(--space-3xs);
}

.wishlist-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: var(--space-sm);
    margin-bottom: var(--space-md);
}

.wishlist-stat {
    background: var(--color-surface);
    border-radius: var(--radius-md);
    padding: var(--space-sm);
    text-align: center;
    border: 1px solid var(--color-border-soft);
}

.wishlist-stat__value {
    font-family: 'Cormorant Garamond', serif;
    font-size: var(--text-xl);
    font-weight: 600;
    color: var(--color-primary-deep);
}

.wishlist-stat__label {
    font-size: var(--text-xs);
    color: var(--color-text-muted);
}

/* Card-based wishlist for mobile */
.wishlist-cards {
    display: grid;
    gap: var(--space-sm);
}

.wish-card {
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    border: 1px solid var(--color-border-soft);
    overflow: hidden;
    transition: all 0.2s ease;
}

.wish-card:active {
    transform: scale(0.98);
}

.wish-card--reserved {
    opacity: 0.7;
}

.wish-card__main {
    display: flex;
    gap: var(--space-sm);
    padding: var(--space-sm);
}

.wish-card__image {
    width: 72px;
    height: 72px;
    border-radius: var(--radius-md);
    object-fit: cover;
    flex-shrink: 0;
    background: var(--color-bg-subtle);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
}

.wish-card__image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: var(--radius-md);
}

.wish-card__content {
    flex: 1;
    min-width: 0;
}

.wish-card__title {
    font-weight: 600;
    font-size: var(--text-base);
    margin-bottom: var(--space-3xs);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.wish-card__desc {
    font-size: var(--text-sm);
    color: var(--color-text-muted);
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin-bottom: var(--space-2xs);
}

.wish-card__meta {
    display: flex;
    gap: var(--space-sm);
    align-items: center;
    flex-wrap: wrap;
}

.wish-card__price {
    font-weight: 600;
    color: var(--color-primary-deep);
}

.wish-card__priority {
    font-size: var(--text-xs);
    padding: 2px 8px;
    border-radius: var(--radius-full);
    background: var(--color-bg-subtle);
    color: var(--color-text-muted);
}

.wish-card__priority--high {
    background: var(--color-primary);
    color: white;
}

.wish-card__status {
    font-size: var(--text-xs);
    padding: 2px 8px;
    border-radius: var(--radius-full);
    background: rgba(123, 160, 91, 0.15);
    color: var(--color-success);
}

.wish-card__actions {
    display: flex;
    border-top: 1px solid var(--color-border-soft);
    background: var(--color-bg-subtle);
}

.wish-card__action {
    flex: 1;
    padding: var(--space-sm);
    border: none;
    background: none;
    font-size: var(--text-sm);
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-2xs);
    color: var(--color-text-soft);
    transition: all 0.2s;
}

.wish-card__action:hover {
    background: var(--color-border-soft);
}

.wish-card__action:active {
    background: var(--color-border);
}

.wish-card__action--danger {
    color: var(--color-error);
}

.wish-card__action + .wish-card__action {
    border-left: 1px solid var(--color-border-soft);
}

/* Floating add button for mobile */
.fab {
    position: fixed;
    bottom: calc(var(--space-md) + 60px);
    right: var(--space-md);
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: var(--color-primary);
    color: white;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    z-index: 50;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.fab:hover {
    transform: scale(1.1);
    background: var(--color-primary-deep);
}

.fab:active {
    transform: scale(0.95);
}

/* Desktop: show table, hide cards */
@media (min-width: 768px) {
    .wishlist-cards {
        display: none;
    }
    .wishlist-table-wrapper {
        display: block;
    }
    .fab {
        display: none;
    }
    .desktop-hide {
        display: none;
    }
}

/* Mobile: show cards, hide table */
@media (max-width: 767px) {
    .wishlist-table-wrapper {
        display: none;
    }
    .wishlist-cards {
        display: grid;
    }
    .page-header__actions .btn--secondary {
        display: none;
    }
    .mobile-hide {
        display: none;
    }
}

/* Improved modal for mobile */
@media (max-width: 640px) {
    .modal-overlay {
        padding: 0;
        align-items: flex-end;
    }

    .modal {
        border-radius: var(--radius-xl) var(--radius-xl) 0 0;
        max-height: 90vh;
        width: 100%;
        max-width: 100%;
    }

    .modal__body {
        padding: var(--space-md);
    }

    .modal .form-input {
        font-size: 16px; /* Prevents iOS zoom */
        padding: var(--space-sm);
    }

    .modal__footer {
        flex-direction: column;
        gap: var(--space-xs);
    }

    .modal__footer .btn {
        width: 100%;
        padding: var(--space-sm);
    }

    .form-row {
        flex-direction: column;
        gap: 0;
    }
}

/* Form row for side-by-side inputs */
.form-row {
    display: flex;
    gap: var(--space-sm);
}

.form-row .form-group {
    flex: 1;
}

/* Empty state */
.empty-state-mobile {
    text-align: center;
    padding: var(--space-xl) var(--space-md);
}

.empty-state-mobile__icon {
    font-size: 4rem;
    margin-bottom: var(--space-sm);
}

.empty-state-mobile__title {
    font-family: 'Cormorant Garamond', serif;
    font-size: var(--text-xl);
    margin-bottom: var(--space-xs);
}

.empty-state-mobile__text {
    color: var(--color-text-muted);
    margin-bottom: var(--space-md);
}
</style>

<!-- Page Header -->
<div class="wishlist-header">
    <div>
        <h1 class="wishlist-header__title">Ønskeliste</h1>
        <p class="wishlist-header__subtitle">
            <?php if ($hideReservations): ?>
                <?= $totalItems ?> ønsker
            <?php else: ?>
                <?= $reservedItems ?> af <?= $totalItems ?> reserveret
            <?php endif; ?>
        </p>
    </div>
    <div class="page-header__actions mobile-hide">
        <a href="<?= BASE_PATH ?>/guest/wishlist.php" target="_blank" class="btn btn--secondary">Se gæstevisning</a>
        <button onclick="openModal('add-modal')" class="btn btn--primary">+ Nyt ønske</button>
    </div>
</div>

<!-- Stats -->
<?php if ($totalItems > 0): ?>
<div class="wishlist-stats">
    <div class="wishlist-stat">
        <div class="wishlist-stat__value"><?= $totalItems ?></div>
        <div class="wishlist-stat__label">Ønsker</div>
    </div>
    <?php if (!$hideReservations): ?>
    <div class="wishlist-stat">
        <div class="wishlist-stat__value"><?= $reservedItems ?></div>
        <div class="wishlist-stat__label">Reserveret</div>
    </div>
    <?php endif; ?>
    <div class="wishlist-stat">
        <div class="wishlist-stat__value"><?= number_format($totalValue, 0, ',', '.') ?></div>
        <div class="wishlist-stat__label">Samlet kr.</div>
    </div>
</div>
<?php endif; ?>

<!-- Wishlist Items -->
<?php if (empty($items)): ?>
    <div class="card">
        <div class="empty-state-mobile">
            <div class="empty-state-mobile__icon">🎁</div>
            <h3 class="empty-state-mobile__title">Ingen ønsker endnu</h3>
            <p class="empty-state-mobile__text">Tilføj dine ønsker så gæsterne ved hvad du drømmer om</p>
            <button onclick="openModal('add-modal')" class="btn btn--primary btn--large">
                + Tilføj første ønske
            </button>
        </div>
    </div>
<?php else: ?>

    <!-- Mobile Card View -->
    <div class="wishlist-cards">
        <?php foreach ($items as $item): ?>
            <div class="wish-card <?= !$hideReservations && $item['reserved_by_guest_id'] ? 'wish-card--reserved' : '' ?>">
                <div class="wish-card__main">
                    <div class="wish-card__image">
                        <?php if ($item['image_url']): ?>
                            <img src="<?= escape($item['image_url']) ?>" alt="">
                        <?php else: ?>
                            🎁
                        <?php endif; ?>
                    </div>
                    <div class="wish-card__content">
                        <div class="wish-card__title"><?= escape($item['title']) ?></div>
                        <?php if ($item['description']): ?>
                            <div class="wish-card__desc"><?= escape($item['description']) ?></div>
                        <?php endif; ?>
                        <div class="wish-card__meta">
                            <?php if ($item['price']): ?>
                                <span class="wish-card__price"><?= formatCurrency($item['price']) ?></span>
                            <?php endif; ?>
                            <?php if ($item['priority'] >= 2): ?>
                                <span class="wish-card__priority wish-card__priority--high">Høj</span>
                            <?php endif; ?>
                            <?php if (!$hideReservations && $item['reserved_by_guest_id']): ?>
                                <span class="wish-card__status">Reserveret</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="wish-card__actions">
                    <?php if ($item['link']): ?>
                        <a href="<?= escape($item['link']) ?>" target="_blank" class="wish-card__action">
                            🔗 Se produkt
                        </a>
                    <?php endif; ?>
                    <button onclick='editItem(<?= json_encode($item) ?>)' class="wish-card__action">
                        ✏️ Rediger
                    </button>
                    <form method="POST" style="flex: 1; display: flex;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                        <button type="submit" class="wish-card__action wish-card__action--danger" data-confirm="Slet dette ønske?">
                            🗑️ Slet
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Desktop Table View -->
    <div class="wishlist-table-wrapper">
        <div class="card">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Ønske</th>
                            <th>Pris</th>
                            <th>Prioritet</th>
                            <?php if (!$hideReservations): ?><th>Status</th><?php endif; ?>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr class="<?= !$hideReservations && $item['reserved_by_guest_id'] ? 'table-row--muted' : '' ?>">
                                <td>
                                    <div style="display: flex; align-items: center; gap: var(--space-sm);">
                                        <?php if ($item['image_url']): ?>
                                            <img src="<?= escape($item['image_url']) ?>"
                                                 alt=""
                                                 style="width: 48px; height: 48px; object-fit: cover; border-radius: var(--radius-sm);">
                                        <?php else: ?>
                                            <div style="width: 48px; height: 48px; background: var(--color-bg-subtle); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center;">🎁</div>
                                        <?php endif; ?>
                                        <div>
                                            <strong><?= escape($item['title']) ?></strong>
                                            <?php if ($item['description']): ?>
                                                <div class="small text-muted"><?= escape(substr($item['description'], 0, 50)) ?><?= strlen($item['description']) > 50 ? '...' : '' ?></div>
                                            <?php endif; ?>
                                            <?php if ($item['link']): ?>
                                                <a href="<?= escape($item['link']) ?>" target="_blank" class="small">Se produkt ↗</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($item['price']): ?>
                                        <?= formatCurrency($item['price']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($item['priority'] >= 2): ?>
                                        <span class="badge badge--primary">Høj</span>
                                    <?php elseif ($item['priority'] >= 1): ?>
                                        <span class="badge badge--secondary">Normal</span>
                                    <?php else: ?>
                                        <span class="badge badge--neutral">Lav</span>
                                    <?php endif; ?>
                                </td>
                                <?php if (!$hideReservations): ?>
                                <td>
                                    <?php if ($item['reserved_by_guest_id']): ?>
                                        <span class="badge badge--success">
                                            Reserveret af <?= escape($item['reserved_by_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge--neutral">Ledig</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <td>
                                    <div style="display: flex; gap: var(--space-xs);">
                                        <button onclick='editItem(<?= json_encode($item) ?>)'
                                                class="btn btn--ghost btn--sm"
                                                title="Rediger">
                                            ✏️
                                        </button>
                                        <?php if (!$hideReservations && $item['reserved_by_guest_id']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="unreserve">
                                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                <button type="submit"
                                                        class="btn btn--ghost btn--sm"
                                                        title="Fjern reservation"
                                                        data-confirm="Fjern reservation?">
                                                    ↩️
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                            <button type="submit"
                                                    class="btn btn--ghost btn--sm"
                                                    title="Slet"
                                                    data-confirm="Slet dette ønske?">
                                                🗑️
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
    </div>

<?php endif; ?>

<!-- Floating Action Button (Mobile) -->
<button class="fab" onclick="openModal('add-modal')" aria-label="Tilføj ønske">+</button>

<!-- Add Modal -->
<div id="add-modal" class="modal-overlay <?= $showAddModal ? 'modal-overlay--active' : '' ?>">
    <div class="modal">
        <div class="modal__header">
            <h2 class="modal__title">Nyt ønske</h2>
            <button class="modal__close" onclick="closeModal('add-modal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal__body">
                <div class="form-group">
                    <label class="form-label">Hvad ønsker du dig? *</label>
                    <input type="text" name="title" class="form-input" required placeholder="F.eks. Bluetooth højtaler">
                </div>
                <div class="form-group">
                    <label class="form-label">Beskrivelse</label>
                    <textarea name="description" class="form-input" rows="2" placeholder="Model, farve, størrelse..."></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Pris (ca.)</label>
                        <input type="number" name="price" class="form-input" step="1" min="0" placeholder="299">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Hvor meget?</label>
                        <select name="priority" class="form-input">
                            <option value="0">Lidt</option>
                            <option value="1" selected>Normalt</option>
                            <option value="2">Meget!</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Link til produkt</label>
                    <input type="url" name="link" class="form-input" placeholder="https://...">
                </div>
                <div class="form-group">
                    <label class="form-label">Billede (URL)</label>
                    <input type="url" name="image_url" class="form-input" placeholder="https://...">
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--secondary" onclick="closeModal('add-modal')">Annuller</button>
                <button type="submit" class="btn btn--primary">Tilføj ønske</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="edit-modal" class="modal-overlay">
    <div class="modal">
        <div class="modal__header">
            <h2 class="modal__title">Rediger ønske</h2>
            <button class="modal__close" onclick="closeModal('edit-modal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit-id">
            <div class="modal__body">
                <div class="form-group">
                    <label class="form-label">Hvad ønsker du dig? *</label>
                    <input type="text" name="title" id="edit-title" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Beskrivelse</label>
                    <textarea name="description" id="edit-description" class="form-input" rows="2"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Pris (ca.)</label>
                        <input type="number" name="price" id="edit-price" class="form-input" step="1" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Hvor meget?</label>
                        <select name="priority" id="edit-priority" class="form-input">
                            <option value="0">Lidt</option>
                            <option value="1">Normalt</option>
                            <option value="2">Meget!</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Link til produkt</label>
                    <input type="url" name="link" id="edit-link" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Billede (URL)</label>
                    <input type="url" name="image_url" id="edit-image-url" class="form-input">
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--secondary" onclick="closeModal('edit-modal')">Annuller</button>
                <button type="submit" class="btn btn--primary">Gem ændringer</button>
            </div>
        </form>
    </div>
</div>

</main>
</div>

<div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleSidebar()"></div>

<script src="<?= BASE_PATH ?>/assets/js/main.js"></script>
<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    sidebar.classList.toggle('sidebar--open');
    overlay.classList.toggle('sidebar-overlay--active');
}

function editItem(item) {
    document.getElementById('edit-id').value = item.id;
    document.getElementById('edit-title').value = item.title;
    document.getElementById('edit-description').value = item.description || '';
    document.getElementById('edit-price').value = item.price || '';
    document.getElementById('edit-priority').value = item.priority || 0;
    document.getElementById('edit-link').value = item.link || '';
    document.getElementById('edit-image-url').value = item.image_url || '';
    openModal('edit-modal');
}

// Confirm delete
document.querySelectorAll('[data-confirm]').forEach(btn => {
    btn.addEventListener('click', function(e) {
        if (!confirm(this.dataset.confirm)) {
            e.preventDefault();
        }
    });
});
</script>
</body>
</html>

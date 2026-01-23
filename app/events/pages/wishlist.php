<?php
/**
 * Wishlist Management Page
 */

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyAccountCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Ugyldig anmodning.');
        redirect("?id=$eventId&page=wishlist");
    }

    $action = $_POST['action'];

    if ($action === 'add_item') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $url = trim($_POST['url'] ?? '');
        $priority = (int)($_POST['priority'] ?? 0);

        if ($title) {
            $stmt = $db->prepare("
                INSERT INTO wishlist_items (event_id, title, description, price, url, priority)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$eventId, $title, $description ?: null, $price ?: null, $url ?: null, $priority]);
            setFlash('success', 'Ønske tilføjet.');
        }
        redirect("?id=$eventId&page=wishlist");

    } elseif ($action === 'delete_item') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        if ($itemId) {
            $stmt = $db->prepare("DELETE FROM wishlist_items WHERE id = ? AND event_id = ?");
            $stmt->execute([$itemId, $eventId]);
            setFlash('success', 'Ønske slettet.');
        }
        redirect("?id=$eventId&page=wishlist");

    } elseif ($action === 'update_item') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $url = trim($_POST['url'] ?? '');
        $priority = (int)($_POST['priority'] ?? 0);

        if ($itemId && $title) {
            $stmt = $db->prepare("
                UPDATE wishlist_items SET title = ?, description = ?, price = ?, url = ?, priority = ?
                WHERE id = ? AND event_id = ?
            ");
            $stmt->execute([$title, $description ?: null, $price ?: null, $url ?: null, $priority, $itemId, $eventId]);
            setFlash('success', 'Ønske opdateret.');
        }
        redirect("?id=$eventId&page=wishlist");

    } elseif ($action === 'unreserve_item') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        if ($itemId) {
            $stmt = $db->prepare("UPDATE wishlist_items SET reserved_by_guest_id = NULL WHERE id = ? AND event_id = ?");
            $stmt->execute([$itemId, $eventId]);
            setFlash('success', 'Reservation annulleret.');
        }
        redirect("?id=$eventId&page=wishlist");
    }
}

// Get wishlist items
$stmt = $db->prepare("
    SELECT w.*, g.name as reserved_by_name
    FROM wishlist_items w
    LEFT JOIN guests g ON w.reserved_by_guest_id = g.id
    WHERE w.event_id = ?
    ORDER BY w.priority DESC, w.title ASC
");
$stmt->execute([$eventId]);
$items = $stmt->fetchAll();
?>

<div class="page-header-actions">
    <div>
        <h2 class="section-title">Ønskeliste</h2>
        <p class="section-subtitle"><?= count($items) ?> ønsker</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="showAddModal()">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        Tilføj ønske
    </button>
</div>

<?php if (empty($items)): ?>
<div class="card">
    <div class="empty-state">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"></path>
        </svg>
        <h3>Ingen ønsker endnu</h3>
        <p>Tilføj ønsker så gæsterne kan reservere gaver.</p>
        <button type="button" class="btn btn-primary" onclick="showAddModal()">Tilføj ønske</button>
    </div>
</div>
<?php else: ?>
<div class="wishlist-grid">
    <?php foreach ($items as $item): ?>
    <div class="wishlist-card <?= $item['reserved_by_guest_id'] ? 'reserved' : '' ?>">
        <div class="wishlist-card-header">
            <?php if ($item['priority'] >= 2): ?>
                <span class="priority-badge">Højt ønske</span>
            <?php endif; ?>
            <?php if ($item['reserved_by_guest_id']): ?>
                <span class="reserved-badge">Reserveret</span>
            <?php endif; ?>
        </div>
        <h3 class="wishlist-title"><?= htmlspecialchars($item['title']) ?></h3>
        <?php if ($item['description']): ?>
            <p class="wishlist-desc"><?= htmlspecialchars($item['description']) ?></p>
        <?php endif; ?>
        <?php if ($item['price']): ?>
            <div class="wishlist-price"><?= formatCurrency($item['price']) ?></div>
        <?php endif; ?>
        <?php if ($item['reserved_by_name']): ?>
            <p class="reserved-by">Reserveret af: <?= htmlspecialchars($item['reserved_by_name']) ?></p>
        <?php endif; ?>
        <div class="wishlist-actions">
            <?php if ($item['url']): ?>
                <a href="<?= htmlspecialchars($item['url']) ?>" target="_blank" class="btn btn-secondary btn-sm">Se produkt</a>
            <?php endif; ?>
            <button type="button" class="btn btn-secondary btn-sm" onclick='editItem(<?= json_encode($item) ?>)'>Rediger</button>
            <?php if ($item['reserved_by_guest_id']): ?>
                <form method="POST" style="display: inline;">
                    <?= accountCsrfField() ?>
                    <input type="hidden" name="action" value="unreserve_item">
                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                    <button type="submit" class="btn btn-secondary btn-sm">Frigiv</button>
                </form>
            <?php endif; ?>
            <button type="button" class="btn btn-secondary btn-sm danger-text" onclick="deleteItem(<?= $item['id'] ?>)">Slet</button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Add Item Modal -->
<div class="modal-overlay" id="addModal" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3>Tilføj ønske</h3>
            <button type="button" class="modal-close" onclick="hideAddModal()">&times;</button>
        </div>
        <form method="POST">
            <?= accountCsrfField() ?>
            <input type="hidden" name="action" value="add_item">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Titel *</label>
                    <input type="text" name="title" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Beskrivelse</label>
                    <textarea name="description" class="form-input" rows="2"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Pris (kr)</label>
                        <input type="number" name="price" class="form-input" step="1" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Prioritet</label>
                        <select name="priority" class="form-input">
                            <option value="0">Normal</option>
                            <option value="1">Medium</option>
                            <option value="2">Høj</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Link til produkt</label>
                    <input type="url" name="url" class="form-input" placeholder="https://...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideAddModal()">Annuller</button>
                <button type="submit" class="btn btn-primary">Tilføj</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Item Modal -->
<div class="modal-overlay" id="editModal" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3>Rediger ønske</h3>
            <button type="button" class="modal-close" onclick="hideEditModal()">&times;</button>
        </div>
        <form method="POST">
            <?= accountCsrfField() ?>
            <input type="hidden" name="action" value="update_item">
            <input type="hidden" name="item_id" id="edit_item_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Titel *</label>
                    <input type="text" name="title" id="edit_title" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Beskrivelse</label>
                    <textarea name="description" id="edit_description" class="form-input" rows="2"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Pris (kr)</label>
                        <input type="number" name="price" id="edit_price" class="form-input" step="1" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Prioritet</label>
                        <select name="priority" id="edit_priority" class="form-input">
                            <option value="0">Normal</option>
                            <option value="1">Medium</option>
                            <option value="2">Høj</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Link til produkt</label>
                    <input type="url" name="url" id="edit_url" class="form-input" placeholder="https://...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideEditModal()">Annuller</button>
                <button type="submit" class="btn btn-primary">Gem</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form -->
<form method="POST" id="deleteForm" style="display: none;">
    <?= accountCsrfField() ?>
    <input type="hidden" name="action" value="delete_item">
    <input type="hidden" name="item_id" id="delete_item_id">
</form>

<style>
    .wishlist-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
    }

    .wishlist-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        border: 1px solid var(--gray-200);
    }

    .wishlist-card.reserved {
        opacity: 0.7;
    }

    .wishlist-card-header {
        display: flex;
        gap: 8px;
        margin-bottom: 12px;
    }

    .priority-badge {
        font-size: 11px;
        font-weight: 600;
        padding: 3px 8px;
        background: #fef3c7;
        color: #b45309;
        border-radius: 4px;
    }

    .reserved-badge {
        font-size: 11px;
        font-weight: 600;
        padding: 3px 8px;
        background: #dcfce7;
        color: #15803d;
        border-radius: 4px;
    }

    .wishlist-title {
        font-size: 16px;
        font-weight: 600;
        color: var(--gray-900);
        margin-bottom: 8px;
    }

    .wishlist-desc {
        font-size: 14px;
        color: var(--gray-600);
        margin-bottom: 12px;
    }

    .wishlist-price {
        font-size: 18px;
        font-weight: 600;
        color: var(--primary);
        margin-bottom: 12px;
    }

    .reserved-by {
        font-size: 13px;
        color: var(--gray-500);
        margin-bottom: 12px;
    }

    .wishlist-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .btn-sm {
        padding: 6px 12px;
        font-size: 13px;
    }

    .danger-text {
        color: var(--danger);
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }
</style>

<script>
function showAddModal() {
    document.getElementById('addModal').style.display = 'flex';
}
function hideAddModal() {
    document.getElementById('addModal').style.display = 'none';
}
function editItem(item) {
    document.getElementById('edit_item_id').value = item.id;
    document.getElementById('edit_title').value = item.title || '';
    document.getElementById('edit_description').value = item.description || '';
    document.getElementById('edit_price').value = item.price || '';
    document.getElementById('edit_priority').value = item.priority || 0;
    document.getElementById('edit_url').value = item.url || '';
    document.getElementById('editModal').style.display = 'flex';
}
function hideEditModal() {
    document.getElementById('editModal').style.display = 'none';
}
function deleteItem(id) {
    if (confirm('Er du sikker på at du vil slette dette ønske?')) {
        document.getElementById('delete_item_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}
document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', function(e) { if (e.target === this) this.style.display = 'none'; });
});
</script>

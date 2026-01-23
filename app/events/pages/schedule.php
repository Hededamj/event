<?php
/**
 * Schedule/Timeline Management Page
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyAccountCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Ugyldig anmodning.');
        redirect("?id=$eventId&page=schedule");
    }

    $action = $_POST['action'];

    if ($action === 'add_item') {
        $time = $_POST['time'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($title) {
            $stmt = $db->prepare("INSERT INTO schedule_items (event_id, time, title, description) VALUES (?, ?, ?, ?)");
            $stmt->execute([$eventId, $time ?: null, $title, $description ?: null]);
            setFlash('success', 'Programpunkt tilføjet.');
        }
        redirect("?id=$eventId&page=schedule");

    } elseif ($action === 'delete_item') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        if ($itemId) {
            $stmt = $db->prepare("DELETE FROM schedule_items WHERE id = ? AND event_id = ?");
            $stmt->execute([$itemId, $eventId]);
            setFlash('success', 'Programpunkt slettet.');
        }
        redirect("?id=$eventId&page=schedule");

    } elseif ($action === 'update_item') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $time = $_POST['time'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($itemId && $title) {
            $stmt = $db->prepare("UPDATE schedule_items SET time = ?, title = ?, description = ? WHERE id = ? AND event_id = ?");
            $stmt->execute([$time ?: null, $title, $description ?: null, $itemId, $eventId]);
            setFlash('success', 'Programpunkt opdateret.');
        }
        redirect("?id=$eventId&page=schedule");
    }
}

$stmt = $db->prepare("SELECT * FROM schedule_items WHERE event_id = ? ORDER BY time ASC, id ASC");
$stmt->execute([$eventId]);
$items = $stmt->fetchAll();
?>

<div class="page-header-actions">
    <div>
        <h2 class="section-title">Program</h2>
        <p class="section-subtitle"><?= count($items) ?> punkter</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="showAddModal()">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        Tilføj punkt
    </button>
</div>

<?php if (empty($items)): ?>
<div class="card">
    <div class="empty-state">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <h3>Intet program endnu</h3>
        <p>Tilføj programpunkter til din dag.</p>
        <button type="button" class="btn btn-primary" onclick="showAddModal()">Tilføj punkt</button>
    </div>
</div>
<?php else: ?>
<div class="card" style="padding: 0;">
    <div class="timeline">
        <?php foreach ($items as $item): ?>
        <div class="timeline-item">
            <div class="timeline-time"><?= $item['time'] ? htmlspecialchars(substr($item['time'], 0, 5)) : '--:--' ?></div>
            <div class="timeline-content">
                <h4><?= htmlspecialchars($item['title']) ?></h4>
                <?php if ($item['description']): ?>
                    <p><?= htmlspecialchars($item['description']) ?></p>
                <?php endif; ?>
            </div>
            <div class="timeline-actions">
                <button type="button" class="row-action" onclick='editItem(<?= json_encode($item) ?>)'>
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                </button>
                <button type="button" class="row-action danger" onclick="deleteItem(<?= $item['id'] ?>)">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="modal-overlay" id="addModal" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3>Tilføj programpunkt</h3>
            <button type="button" class="modal-close" onclick="hideAddModal()">&times;</button>
        </div>
        <form method="POST">
            <?= accountCsrfField() ?>
            <input type="hidden" name="action" value="add_item">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Tidspunkt</label>
                    <input type="time" name="time" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Titel *</label>
                    <input type="text" name="title" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Beskrivelse</label>
                    <textarea name="description" class="form-input" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideAddModal()">Annuller</button>
                <button type="submit" class="btn btn-primary">Tilføj</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="editModal" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3>Rediger programpunkt</h3>
            <button type="button" class="modal-close" onclick="hideEditModal()">&times;</button>
        </div>
        <form method="POST">
            <?= accountCsrfField() ?>
            <input type="hidden" name="action" value="update_item">
            <input type="hidden" name="item_id" id="edit_item_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Tidspunkt</label>
                    <input type="time" name="time" id="edit_time" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Titel *</label>
                    <input type="text" name="title" id="edit_title" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Beskrivelse</label>
                    <textarea name="description" id="edit_description" class="form-input" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideEditModal()">Annuller</button>
                <button type="submit" class="btn btn-primary">Gem</button>
            </div>
        </form>
    </div>
</div>

<form method="POST" id="deleteForm" style="display: none;">
    <?= accountCsrfField() ?>
    <input type="hidden" name="action" value="delete_item">
    <input type="hidden" name="item_id" id="delete_item_id">
</form>

<style>
    .timeline { padding: 24px; }
    .timeline-item { display: flex; gap: 20px; padding: 16px 0; border-bottom: 1px solid var(--gray-100); align-items: flex-start; }
    .timeline-item:last-child { border-bottom: none; }
    .timeline-time { font-size: 18px; font-weight: 600; color: var(--primary); min-width: 60px; }
    .timeline-content { flex: 1; }
    .timeline-content h4 { font-size: 16px; font-weight: 600; color: var(--gray-900); margin-bottom: 4px; }
    .timeline-content p { font-size: 14px; color: var(--gray-600); }
    .timeline-actions { display: flex; gap: 4px; }
</style>

<script>
function showAddModal() { document.getElementById('addModal').style.display = 'flex'; }
function hideAddModal() { document.getElementById('addModal').style.display = 'none'; }
function editItem(item) {
    document.getElementById('edit_item_id').value = item.id;
    document.getElementById('edit_time').value = item.time ? item.time.substring(0, 5) : '';
    document.getElementById('edit_title').value = item.title;
    document.getElementById('edit_description').value = item.description || '';
    document.getElementById('editModal').style.display = 'flex';
}
function hideEditModal() { document.getElementById('editModal').style.display = 'none'; }
function deleteItem(id) {
    if (confirm('Slet dette programpunkt?')) {
        document.getElementById('delete_item_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}
document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', function(e) { if (e.target === this) this.style.display = 'none'; });
});
</script>

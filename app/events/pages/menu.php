<?php
/**
 * Menu Management Page
 */

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyAccountCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Ugyldig anmodning.');
        redirect("?id=$eventId&page=menu");
    }

    $action = $_POST['action'];

    if ($action === 'add_item') {
        $course = $_POST['course'] ?? 'main';
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        // Get max sort order
        $stmt = $db->prepare("SELECT MAX(sort_order) as max_order FROM menu_items WHERE event_id = ? AND course = ?");
        $stmt->execute([$eventId, $course]);
        $maxOrder = ($stmt->fetch()['max_order'] ?? 0) + 1;

        if ($title) {
            $stmt = $db->prepare("INSERT INTO menu_items (event_id, course, title, description, sort_order) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$eventId, $course, $title, $description ?: null, $maxOrder]);
            setFlash('success', 'Menupunkt tilføjet.');
        }
        redirect("?id=$eventId&page=menu");

    } elseif ($action === 'delete_item') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        if ($itemId) {
            $stmt = $db->prepare("DELETE FROM menu_items WHERE id = ? AND event_id = ?");
            $stmt->execute([$itemId, $eventId]);
            setFlash('success', 'Menupunkt slettet.');
        }
        redirect("?id=$eventId&page=menu");

    } elseif ($action === 'update_item') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $course = $_POST['course'] ?? 'main';
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($itemId && $title) {
            $stmt = $db->prepare("UPDATE menu_items SET course = ?, title = ?, description = ? WHERE id = ? AND event_id = ?");
            $stmt->execute([$course, $title, $description ?: null, $itemId, $eventId]);
            setFlash('success', 'Menupunkt opdateret.');
        }
        redirect("?id=$eventId&page=menu");
    }
}

// Get menu items by course
$courses = ['starter' => 'Forret', 'main' => 'Hovedret', 'dessert' => 'Dessert', 'drinks' => 'Drikkevarer', 'snacks' => 'Snacks'];
$menuItems = [];
foreach ($courses as $key => $label) {
    $stmt = $db->prepare("SELECT * FROM menu_items WHERE event_id = ? AND course = ? ORDER BY sort_order ASC");
    $stmt->execute([$eventId, $key]);
    $menuItems[$key] = $stmt->fetchAll();
}
?>

<div class="page-header-actions">
    <div>
        <h2 class="section-title">Menu</h2>
        <p class="section-subtitle">Opret menukortet til dit arrangement</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="showAddModal()">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        Tilføj ret
    </button>
</div>

<div class="menu-courses">
    <?php foreach ($courses as $courseKey => $courseLabel): ?>
    <div class="card menu-course">
        <div class="card-header">
            <h3 class="card-title"><?= $courseLabel ?></h3>
        </div>
        <?php if (empty($menuItems[$courseKey])): ?>
            <p class="empty-course">Ingen retter tilføjet endnu</p>
        <?php else: ?>
            <div class="menu-items">
                <?php foreach ($menuItems[$courseKey] as $item): ?>
                <div class="menu-item">
                    <div class="menu-item-content">
                        <h4><?= htmlspecialchars($item['title']) ?></h4>
                        <?php if ($item['description']): ?>
                            <p><?= htmlspecialchars($item['description']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="menu-item-actions">
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
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3>Tilføj ret</h3>
            <button type="button" class="modal-close" onclick="hideAddModal()">&times;</button>
        </div>
        <form method="POST">
            <?= accountCsrfField() ?>
            <input type="hidden" name="action" value="add_item">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Kategori</label>
                    <select name="course" class="form-input">
                        <?php foreach ($courses as $key => $label): ?>
                            <option value="<?= $key ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Navn *</label>
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

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3>Rediger ret</h3>
            <button type="button" class="modal-close" onclick="hideEditModal()">&times;</button>
        </div>
        <form method="POST">
            <?= accountCsrfField() ?>
            <input type="hidden" name="action" value="update_item">
            <input type="hidden" name="item_id" id="edit_item_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Kategori</label>
                    <select name="course" id="edit_course" class="form-input">
                        <?php foreach ($courses as $key => $label): ?>
                            <option value="<?= $key ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Navn *</label>
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
    .menu-courses { display: flex; flex-direction: column; gap: 24px; }
    .menu-course { padding: 20px; }
    .empty-course { color: var(--gray-400); font-size: 14px; font-style: italic; }
    .menu-items { display: flex; flex-direction: column; gap: 12px; }
    .menu-item { display: flex; justify-content: space-between; align-items: flex-start; padding: 12px; background: var(--gray-50); border-radius: 8px; }
    .menu-item h4 { font-size: 15px; font-weight: 600; color: var(--gray-900); margin-bottom: 4px; }
    .menu-item p { font-size: 14px; color: var(--gray-600); }
    .menu-item-actions { display: flex; gap: 4px; }
</style>

<script>
function showAddModal() { document.getElementById('addModal').style.display = 'flex'; }
function hideAddModal() { document.getElementById('addModal').style.display = 'none'; }
function editItem(item) {
    document.getElementById('edit_item_id').value = item.id;
    document.getElementById('edit_course').value = item.course;
    document.getElementById('edit_title').value = item.title;
    document.getElementById('edit_description').value = item.description || '';
    document.getElementById('editModal').style.display = 'flex';
}
function hideEditModal() { document.getElementById('editModal').style.display = 'none'; }
function deleteItem(id) {
    if (confirm('Slet denne ret?')) {
        document.getElementById('delete_item_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}
document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', function(e) { if (e.target === this) this.style.display = 'none'; });
});
</script>

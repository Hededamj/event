<?php
/**
 * Schedule/Timeline & Menu Management Page
 * Combined view with section tabs for Program and Menu.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyAccountCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Ugyldig anmodning.');
        $action = $_POST['action'] ?? '';
        $isMenuAction = in_array($action, ['add_menu_item', 'delete_menu_item', 'update_menu_item']);
        redirect("?id=$eventId&page=schedule" . ($isMenuAction ? '&section=menu' : ''));
    }

    $action = $_POST['action'];

    // Schedule actions
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

    // Menu actions
    } elseif ($action === 'add_menu_item') {
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
        redirect("?id=$eventId&page=schedule&section=menu");

    } elseif ($action === 'delete_menu_item') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        if ($itemId) {
            $stmt = $db->prepare("DELETE FROM menu_items WHERE id = ? AND event_id = ?");
            $stmt->execute([$itemId, $eventId]);
            setFlash('success', 'Menupunkt slettet.');
        }
        redirect("?id=$eventId&page=schedule&section=menu");

    } elseif ($action === 'update_menu_item') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $course = $_POST['course'] ?? 'main';
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($itemId && $title) {
            $stmt = $db->prepare("UPDATE menu_items SET course = ?, title = ?, description = ? WHERE id = ? AND event_id = ?");
            $stmt->execute([$course, $title, $description ?: null, $itemId, $eventId]);
            setFlash('success', 'Menupunkt opdateret.');
        }
        redirect("?id=$eventId&page=schedule&section=menu");
    }
}

// Load schedule items
$stmt = $db->prepare("SELECT * FROM schedule_items WHERE event_id = ? ORDER BY time ASC, id ASC");
$stmt->execute([$eventId]);
$items = $stmt->fetchAll();

// Load menu items by course
$courses = ['starter' => 'Forret', 'main' => 'Hovedret', 'dessert' => 'Dessert', 'drinks' => 'Drikkevarer', 'snacks' => 'Snacks'];
$menuItems = [];
foreach ($courses as $key => $label) {
    $stmt = $db->prepare("SELECT * FROM menu_items WHERE event_id = ? AND course = ? ORDER BY sort_order ASC");
    $stmt->execute([$eventId, $key]);
    $menuItems[$key] = $stmt->fetchAll();
}
$menuCount = array_sum(array_map('count', $menuItems));
$section = in_array($_GET['section'] ?? '', ['schedule', 'menu']) ? $_GET['section'] : 'schedule';
?>

<div class="page-header-actions">
    <div>
        <h2 class="section-title">Program & Menu</h2>
        <p class="section-subtitle"><?= count($items) ?> programpunkter · <?= $menuCount ?> retter</p>
    </div>
</div>

<div class="section-tabs">
    <a href="?id=<?= $eventId ?>&page=schedule&section=schedule"
       class="section-tab <?= $section !== 'menu' ? 'active' : '' ?>">
        Program (<?= count($items) ?>)
    </a>
    <a href="?id=<?= $eventId ?>&page=schedule&section=menu"
       class="section-tab <?= $section === 'menu' ? 'active' : '' ?>">
        Menu (<?= $menuCount ?>)
    </a>
</div>

<?php if ($section !== 'menu'): ?>
<!-- ===== SCHEDULE SECTION ===== -->

<div style="text-align: right; margin-bottom: 16px;">
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

<!-- Schedule Add Modal -->
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

<!-- Schedule Edit Modal -->
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

<!-- Schedule Delete Form -->
<form method="POST" id="deleteForm" style="display: none;">
    <?= accountCsrfField() ?>
    <input type="hidden" name="action" value="delete_item">
    <input type="hidden" name="item_id" id="delete_item_id">
</form>

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

<?php else: ?>
<!-- ===== MENU SECTION ===== -->

<div style="text-align: right; margin-bottom: 16px;">
    <button type="button" class="btn btn-primary" onclick="showMenuAddModal()">
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
                <?php foreach ($menuItems[$courseKey] as $menuItem): ?>
                <div class="menu-item">
                    <div class="menu-item-content">
                        <h4><?= htmlspecialchars($menuItem['title']) ?></h4>
                        <?php if ($menuItem['description']): ?>
                            <p><?= htmlspecialchars($menuItem['description']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="menu-item-actions">
                        <button type="button" class="row-action" onclick='editMenuItem(<?= json_encode($menuItem) ?>)'>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        </button>
                        <button type="button" class="row-action danger" onclick="deleteMenuItem(<?= $menuItem['id'] ?>)">
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

<!-- Menu Add Modal -->
<div class="modal-overlay" id="menuAddModal" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3>Tilføj ret</h3>
            <button type="button" class="modal-close" onclick="hideMenuAddModal()">&times;</button>
        </div>
        <form method="POST">
            <?= accountCsrfField() ?>
            <input type="hidden" name="action" value="add_menu_item">
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
                <button type="button" class="btn btn-secondary" onclick="hideMenuAddModal()">Annuller</button>
                <button type="submit" class="btn btn-primary">Tilføj</button>
            </div>
        </form>
    </div>
</div>

<!-- Menu Edit Modal -->
<div class="modal-overlay" id="menuEditModal" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3>Rediger ret</h3>
            <button type="button" class="modal-close" onclick="hideMenuEditModal()">&times;</button>
        </div>
        <form method="POST">
            <?= accountCsrfField() ?>
            <input type="hidden" name="action" value="update_menu_item">
            <input type="hidden" name="item_id" id="menu_edit_item_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Kategori</label>
                    <select name="course" id="menu_edit_course" class="form-input">
                        <?php foreach ($courses as $key => $label): ?>
                            <option value="<?= $key ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Navn *</label>
                    <input type="text" name="title" id="menu_edit_title" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Beskrivelse</label>
                    <textarea name="description" id="menu_edit_description" class="form-input" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideMenuEditModal()">Annuller</button>
                <button type="submit" class="btn btn-primary">Gem</button>
            </div>
        </form>
    </div>
</div>

<!-- Menu Delete Form -->
<form method="POST" id="menuDeleteForm" style="display: none;">
    <?= accountCsrfField() ?>
    <input type="hidden" name="action" value="delete_menu_item">
    <input type="hidden" name="item_id" id="menu_delete_item_id">
</form>

<script>
function showMenuAddModal() { document.getElementById('menuAddModal').style.display = 'flex'; }
function hideMenuAddModal() { document.getElementById('menuAddModal').style.display = 'none'; }
function editMenuItem(item) {
    document.getElementById('menu_edit_item_id').value = item.id;
    document.getElementById('menu_edit_course').value = item.course;
    document.getElementById('menu_edit_title').value = item.title;
    document.getElementById('menu_edit_description').value = item.description || '';
    document.getElementById('menuEditModal').style.display = 'flex';
}
function hideMenuEditModal() { document.getElementById('menuEditModal').style.display = 'none'; }
function deleteMenuItem(id) {
    if (confirm('Slet denne ret?')) {
        document.getElementById('menu_delete_item_id').value = id;
        document.getElementById('menuDeleteForm').submit();
    }
}
document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', function(e) { if (e.target === this) this.style.display = 'none'; });
});
</script>

<?php endif; ?>

<style>
    /* Schedule styles */
    .timeline { padding: 24px; }
    .timeline-item { display: flex; gap: 20px; padding: 16px 0; border-bottom: 1px solid var(--cream-dark); align-items: flex-start; }
    .timeline-item:last-child { border-bottom: none; }
    .timeline-time { font-size: 18px; font-weight: 600; color: var(--sage); min-width: 60px; }
    .timeline-content { flex: 1; }
    .timeline-content h4 { font-size: 16px; font-weight: 600; color: var(--charcoal); margin-bottom: 4px; }
    .timeline-content p { font-size: 14px; color: var(--charcoal-light); }
    .timeline-actions { display: flex; gap: 4px; }

    /* Menu styles */
    .menu-courses { display: flex; flex-direction: column; gap: 24px; }
    .menu-course { padding: 20px; }
    .empty-course { color: var(--charcoal-light); font-size: 14px; font-style: italic; }
    .menu-items { display: flex; flex-direction: column; gap: 12px; }
    .menu-item { display: flex; justify-content: space-between; align-items: flex-start; padding: 12px; background: var(--cream-light); border-radius: 8px; }
    .menu-item h4 { font-size: 15px; font-weight: 600; color: var(--charcoal); margin-bottom: 4px; }
    .menu-item p { font-size: 14px; color: var(--charcoal-light); }
    .menu-item-actions { display: flex; gap: 4px; }

    /* Row action buttons */
    .row-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border: none;
        background: none;
        cursor: pointer;
        color: var(--charcoal-light);
        border-radius: 8px;
        transition: all 0.2s;
    }
    .row-action:hover { background: var(--cream); color: var(--charcoal); }
    .row-action.danger:hover { background: #FEE; color: var(--error, #B84C4C); }
    .row-action svg { width: 16px; height: 16px; }

    /* Section tabs */
    .section-tabs {
        display: flex;
        gap: 4px;
        margin-bottom: 24px;
        background: var(--cream-light, #F5F3EF);
        border-radius: 12px;
        padding: 4px;
    }
    .section-tab {
        flex: 1;
        text-align: center;
        padding: 10px 16px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 500;
        color: var(--charcoal-light, #3D3D3D);
        text-decoration: none;
        transition: all 0.2s;
    }
    .section-tab.active {
        background: var(--white, #fff);
        color: var(--charcoal, #1A1A1A);
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    }
    .section-tab:hover:not(.active) { color: var(--charcoal, #1A1A1A); }
</style>

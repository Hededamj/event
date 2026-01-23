<?php
/**
 * Checklist Page (Premium Feature)
 */

if (!$hasChecklist): ?>
<div class="upgrade-notice">
    <div class="upgrade-notice-content">
        <h4>Tjekliste er en premium-funktion</h4>
        <p>Opgrader til Basis eller højere for at få adgang til tjekliste-funktionen.</p>
    </div>
    <a href="/app/account/subscription.php" class="btn">Opgrader nu</a>
</div>
<?php return; endif;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyAccountCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Ugyldig anmodning.');
        redirect("?id=$eventId&page=checklist");
    }

    $action = $_POST['action'];

    if ($action === 'add_item') {
        $task = trim($_POST['task'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $dueDate = $_POST['due_date'] ?? null;

        if ($task) {
            $stmt = $db->prepare("INSERT INTO checklist_items (event_id, task, category, due_date) VALUES (?, ?, ?, ?)");
            $stmt->execute([$eventId, $task, $category ?: null, $dueDate ?: null]);
            setFlash('success', 'Opgave tilføjet.');
        }
        redirect("?id=$eventId&page=checklist");

    } elseif ($action === 'toggle_complete') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        if ($itemId) {
            $stmt = $db->prepare("UPDATE checklist_items SET completed = NOT completed, completed_at = IF(completed, NULL, NOW()) WHERE id = ? AND event_id = ?");
            $stmt->execute([$itemId, $eventId]);
        }
        redirect("?id=$eventId&page=checklist");

    } elseif ($action === 'delete_item') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        if ($itemId) {
            $stmt = $db->prepare("DELETE FROM checklist_items WHERE id = ? AND event_id = ?");
            $stmt->execute([$itemId, $eventId]);
            setFlash('success', 'Opgave slettet.');
        }
        redirect("?id=$eventId&page=checklist");
    }
}

$stmt = $db->prepare("SELECT * FROM checklist_items WHERE event_id = ? ORDER BY completed ASC, due_date ASC, id ASC");
$stmt->execute([$eventId]);
$items = $stmt->fetchAll();

$completed = array_filter($items, fn($i) => $i['completed']);
$pending = array_filter($items, fn($i) => !$i['completed']);
?>

<div class="page-header-actions">
    <div>
        <h2 class="section-title">Tjekliste</h2>
        <p class="section-subtitle"><?= count($completed) ?> af <?= count($items) ?> opgaver udført</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="showAddModal()">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        Tilføj opgave
    </button>
</div>

<?php if (count($items) > 0): ?>
<div class="progress-bar-container">
    <div class="progress-bar">
        <div class="progress-fill" style="width: <?= count($items) > 0 ? (count($completed) / count($items) * 100) : 0 ?>%"></div>
    </div>
    <span class="progress-text"><?= count($items) > 0 ? round(count($completed) / count($items) * 100) : 0 ?>% færdig</span>
</div>
<?php endif; ?>

<?php if (empty($items)): ?>
<div class="card">
    <div class="empty-state">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
        </svg>
        <h3>Ingen opgaver endnu</h3>
        <p>Tilføj opgaver for at holde styr på planlægningen.</p>
        <button type="button" class="btn btn-primary" onclick="showAddModal()">Tilføj opgave</button>
    </div>
</div>
<?php else: ?>
<div class="card" style="padding: 0;">
    <div class="checklist">
        <?php foreach ($items as $item): ?>
        <div class="checklist-item <?= $item['completed'] ? 'completed' : '' ?>">
            <form method="POST" class="check-form">
                <?= accountCsrfField() ?>
                <input type="hidden" name="action" value="toggle_complete">
                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                <button type="submit" class="checkbox-btn">
                    <?php if ($item['completed']): ?>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <?php endif; ?>
                </button>
            </form>
            <div class="checklist-content">
                <span class="task-text"><?= htmlspecialchars($item['task']) ?></span>
                <div class="task-meta">
                    <?php if ($item['category']): ?>
                        <span class="task-category"><?= htmlspecialchars($item['category']) ?></span>
                    <?php endif; ?>
                    <?php if ($item['due_date']): ?>
                        <span class="task-due">Deadline: <?= date('d/m', strtotime($item['due_date'])) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <form method="POST" class="delete-form" onsubmit="return confirm('Slet denne opgave?')">
                <?= accountCsrfField() ?>
                <input type="hidden" name="action" value="delete_item">
                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                <button type="submit" class="row-action danger">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="modal-overlay" id="addModal" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3>Tilføj opgave</h3>
            <button type="button" class="modal-close" onclick="hideAddModal()">&times;</button>
        </div>
        <form method="POST">
            <?= accountCsrfField() ?>
            <input type="hidden" name="action" value="add_item">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Opgave *</label>
                    <input type="text" name="task" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Kategori</label>
                    <input type="text" name="category" class="form-input" placeholder="F.eks. Mad, Dekoration, Invitationer">
                </div>
                <div class="form-group">
                    <label class="form-label">Deadline</label>
                    <input type="date" name="due_date" class="form-input">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideAddModal()">Annuller</button>
                <button type="submit" class="btn btn-primary">Tilføj</button>
            </div>
        </form>
    </div>
</div>

<style>
    .progress-bar-container { display: flex; align-items: center; gap: 16px; margin-bottom: 24px; }
    .progress-bar { flex: 1; height: 8px; background: var(--gray-200); border-radius: 4px; overflow: hidden; }
    .progress-fill { height: 100%; background: var(--success); transition: width 0.3s; }
    .progress-text { font-size: 14px; font-weight: 600; color: var(--gray-600); min-width: 80px; }
    .checklist { padding: 8px 0; }
    .checklist-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-bottom: 1px solid var(--gray-100); }
    .checklist-item:last-child { border-bottom: none; }
    .checklist-item.completed { opacity: 0.6; }
    .checklist-item.completed .task-text { text-decoration: line-through; }
    .checkbox-btn { width: 24px; height: 24px; border: 2px solid var(--gray-300); border-radius: 6px; background: white; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0; }
    .checklist-item.completed .checkbox-btn { background: var(--success); border-color: var(--success); }
    .checkbox-btn svg { width: 14px; height: 14px; color: white; }
    .checklist-content { flex: 1; }
    .task-text { font-size: 15px; color: var(--gray-900); }
    .task-meta { display: flex; gap: 12px; margin-top: 4px; }
    .task-category, .task-due { font-size: 12px; color: var(--gray-500); }
    .check-form, .delete-form { display: flex; }
</style>

<script>
function showAddModal() { document.getElementById('addModal').style.display = 'flex'; }
function hideAddModal() { document.getElementById('addModal').style.display = 'none'; }
document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', function(e) { if (e.target === this) this.style.display = 'none'; });
});
</script>

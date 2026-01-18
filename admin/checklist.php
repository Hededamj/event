<?php
/**
 * Admin - Checklist Management
 */
require_once __DIR__ . '/../includes/admin-header.php';

// Categories
$categories = [
    'praktisk' => ['label' => 'Praktisk', 'icon' => '📋'],
    'mad' => ['label' => 'Mad & Drikke', 'icon' => '🍽️'],
    'pynt' => ['label' => 'Pynt & Dekoration', 'icon' => '🎈'],
    'underholdning' => ['label' => 'Underholdning', 'icon' => '🎉'],
    'general' => ['label' => 'Generelt', 'icon' => '📌']
];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $task = trim($_POST['task'] ?? '');
        $category = $_POST['category'] ?? 'general';
        $dueDate = $_POST['due_date'] ?? null;
        $assignedTo = trim($_POST['assigned_to'] ?? '');

        if ($task) {
            $stmt = $db->prepare("
                INSERT INTO checklist_items (event_id, task, category, due_date, assigned_to)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $eventId,
                $task,
                $category,
                $dueDate ?: null,
                $assignedTo ?: null
            ]);

            setFlash('success', 'Opgave tilføjet');
            redirect('/admin/checklist.php');
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $db->prepare("
                UPDATE checklist_items
                SET completed = NOT completed,
                    completed_at = CASE WHEN completed = 0 THEN NOW() ELSE NULL END
                WHERE id = ? AND event_id = ?
            ");
            $stmt->execute([$id, $eventId]);

            if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                jsonResponse(['success' => true]);
            }
            redirect('/admin/checklist.php');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $db->prepare("DELETE FROM checklist_items WHERE id = ? AND event_id = ?");
            $stmt->execute([$id, $eventId]);

            setFlash('success', 'Opgave slettet');
            redirect('/admin/checklist.php');
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $task = trim($_POST['task'] ?? '');
        $category = $_POST['category'] ?? 'general';
        $dueDate = $_POST['due_date'] ?? null;
        $assignedTo = trim($_POST['assigned_to'] ?? '');

        if ($task && $id) {
            $stmt = $db->prepare("
                UPDATE checklist_items
                SET task = ?, category = ?, due_date = ?, assigned_to = ?
                WHERE id = ? AND event_id = ?
            ");
            $stmt->execute([
                $task,
                $category,
                $dueDate ?: null,
                $assignedTo ?: null,
                $id,
                $eventId
            ]);

            setFlash('success', 'Opgave opdateret');
            redirect('/admin/checklist.php');
        }
    }
}

// Get all tasks
$stmt = $db->prepare("
    SELECT * FROM checklist_items
    WHERE event_id = ?
    ORDER BY completed ASC, due_date ASC, sort_order ASC
");
$stmt->execute([$eventId]);
$allTasks = $stmt->fetchAll();

// Group by category
$tasksByCategory = [];
foreach ($allTasks as $task) {
    $cat = $task['category'] ?: 'general';
    $tasksByCategory[$cat][] = $task;
}

// Stats
$totalTasks = count($allTasks);
$completedTasks = count(array_filter($allTasks, fn($t) => $t['completed']));
$progressPercent = $totalTasks > 0 ? round($completedTasks / $totalTasks * 100) : 0;

// Overdue tasks
$overdueTasks = array_filter($allTasks, function($t) {
    return !$t['completed'] && $t['due_date'] && strtotime($t['due_date']) < strtotime('today');
});

$showAddModal = isset($_GET['action']) && $_GET['action'] === 'add';

require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-header__title">Huskeliste</h1>
        <p class="page-header__subtitle">
            <?= $completedTasks ?> af <?= $totalTasks ?> opgaver fuldført
            <?php if (count($overdueTasks) > 0): ?>
                <span class="text-error">&middot; <?= count($overdueTasks) ?> overskredet</span>
            <?php endif; ?>
        </p>
    </div>
    <div class="page-header__actions">
        <button onclick="openModal('add-modal')" class="btn btn--primary">+ Ny opgave</button>
    </div>
</div>

<!-- Progress Card -->
<div class="card mb-md">
    <div class="flex flex-between items-center mb-sm">
        <span class="small text-muted"><?= $completedTasks ?> af <?= $totalTasks ?> opgaver</span>
        <span class="small" style="font-weight: 600; color: var(--color-primary-deep);"><?= $progressPercent ?>%</span>
    </div>
    <div class="progress" style="height: 12px;">
        <div class="progress__bar progress__bar--success" style="width: <?= $progressPercent ?>%;"></div>
    </div>
</div>

<!-- Tasks by Category -->
<?php if (empty($allTasks)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state__icon">✅</div>
            <h3 class="empty-state__title">Ingen opgaver endnu</h3>
            <p class="empty-state__text">Tilføj opgaver for at holde styr på alt det praktiske</p>
            <button onclick="openModal('add-modal')" class="btn btn--primary mt-md">
                + Ny opgave
            </button>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($categories as $catKey => $catInfo): ?>
        <?php if (isset($tasksByCategory[$catKey]) && !empty($tasksByCategory[$catKey])): ?>
            <div class="card mb-md">
                <h2 class="card__title mb-sm">
                    <span><?= $catInfo['icon'] ?></span>
                    <?= $catInfo['label'] ?>
                    <span class="small text-muted" style="font-weight: 400; margin-left: 8px;">
                        (<?= count(array_filter($tasksByCategory[$catKey], fn($t) => $t['completed'])) ?>/<?= count($tasksByCategory[$catKey]) ?>)
                    </span>
                </h2>

                <div class="checklist">
                    <?php foreach ($tasksByCategory[$catKey] as $task): ?>
                        <?php
                        $isOverdue = !$task['completed'] && $task['due_date'] && strtotime($task['due_date']) < strtotime('today');
                        ?>
                        <div class="checklist-item <?= $task['completed'] ? 'checklist-item--completed' : '' ?>">
                            <form method="POST" class="toggle-form">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= $task['id'] ?>">
                                <button type="submit" class="checklist-checkbox <?= $task['completed'] ? 'checklist-checkbox--checked' : '' ?>">
                                    <?= $task['completed'] ? '✓' : '' ?>
                                </button>
                            </form>

                            <div class="checklist-item__content">
                                <div class="checklist-item__text"><?= escape($task['task']) ?></div>
                                <div class="checklist-item__meta">
                                    <?php if ($task['assigned_to']): ?>
                                        <span>👤 <?= escape($task['assigned_to']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($task['due_date']): ?>
                                        <span class="<?= $isOverdue ? 'text-error' : '' ?>">
                                            📅 <?= formatShortDate($task['due_date']) ?>
                                            <?= $isOverdue ? '(overskredet)' : '' ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="checklist-item__actions">
                                <button onclick='editTask(<?= json_encode($task) ?>)'
                                        class="btn btn--ghost"
                                        title="Rediger">
                                    ✏️
                                </button>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $task['id'] ?>">
                                    <button type="submit"
                                            class="btn btn--ghost"
                                            title="Slet"
                                            data-confirm="Slet denne opgave?">
                                        🗑️
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Add Task Modal -->
<div id="add-modal" class="modal-overlay <?= $showAddModal ? 'modal-overlay--active' : '' ?>">
    <div class="modal">
        <div class="modal__header">
            <h2 class="modal__title">Ny opgave</h2>
            <button class="modal__close" onclick="closeModal('add-modal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal__body">
                <div class="form-group">
                    <label class="form-label">Opgave *</label>
                    <input type="text" name="task" class="form-input" required placeholder="Hvad skal gøres?">
                </div>
                <div class="form-group">
                    <label class="form-label">Kategori</label>
                    <select name="category" class="form-input">
                        <?php foreach ($categories as $key => $info): ?>
                            <option value="<?= $key ?>"><?= $info['icon'] ?> <?= $info['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Deadline</label>
                    <input type="date" name="due_date" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Ansvarlig</label>
                    <input type="text" name="assigned_to" class="form-input" placeholder="F.eks. Mor, Far, Sofie">
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--secondary" onclick="closeModal('add-modal')">Annuller</button>
                <button type="submit" class="btn btn--primary">Tilføj opgave</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Task Modal -->
<div id="edit-modal" class="modal-overlay">
    <div class="modal">
        <div class="modal__header">
            <h2 class="modal__title">Rediger opgave</h2>
            <button class="modal__close" onclick="closeModal('edit-modal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit-id">
            <div class="modal__body">
                <div class="form-group">
                    <label class="form-label">Opgave *</label>
                    <input type="text" name="task" id="edit-task" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Kategori</label>
                    <select name="category" id="edit-category" class="form-input">
                        <?php foreach ($categories as $key => $info): ?>
                            <option value="<?= $key ?>"><?= $info['icon'] ?> <?= $info['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Deadline</label>
                    <input type="date" name="due_date" id="edit-due-date" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Ansvarlig</label>
                    <input type="text" name="assigned_to" id="edit-assigned-to" class="form-input">
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

<script src="/assets/js/main.js"></script>
<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    sidebar.classList.toggle('sidebar--open');
    overlay.classList.toggle('sidebar-overlay--active');
}

function editTask(task) {
    document.getElementById('edit-id').value = task.id;
    document.getElementById('edit-task').value = task.task;
    document.getElementById('edit-category').value = task.category || 'general';
    document.getElementById('edit-due-date').value = task.due_date || '';
    document.getElementById('edit-assigned-to').value = task.assigned_to || '';
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

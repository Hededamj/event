<?php
/**
 * Admin - Schedule/Program Management
 */
require_once __DIR__ . '/../includes/admin-header.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $time = $_POST['time'] ?? '';
        $itemDate = $_POST['item_date'] ?? $event['event_date'];
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($title && $time) {
            // Get max sort order
            $stmt = $db->prepare("SELECT MAX(sort_order) FROM schedule_items WHERE event_id = ?");
            $stmt->execute([$eventId]);
            $maxOrder = $stmt->fetchColumn() ?? 0;

            $stmt = $db->prepare("
                INSERT INTO schedule_items (event_id, time, item_date, title, description, sort_order)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $eventId,
                $time,
                $itemDate,
                $title,
                $description ?: null,
                $maxOrder + 1
            ]);

            setFlash('success', 'Programpunkt tilføjet');
            redirect(BASE_PATH . '/admin/schedule.php');
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $time = $_POST['time'] ?? '';
        $itemDate = $_POST['item_date'] ?? $event['event_date'];
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($title && $time && $id) {
            $stmt = $db->prepare("
                UPDATE schedule_items
                SET time = ?, item_date = ?, title = ?, description = ?
                WHERE id = ? AND event_id = ?
            ");
            $stmt->execute([
                $time,
                $itemDate,
                $title,
                $description ?: null,
                $id,
                $eventId
            ]);

            setFlash('success', 'Programpunkt opdateret');
            redirect(BASE_PATH . '/admin/schedule.php');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $db->prepare("DELETE FROM schedule_items WHERE id = ? AND event_id = ?");
            $stmt->execute([$id, $eventId]);

            setFlash('success', 'Programpunkt slettet');
            redirect(BASE_PATH . '/admin/schedule.php');
        }
    }
}

// Get all schedule items - sort by date and time
$stmt = $db->prepare("
    SELECT * FROM schedule_items
    WHERE event_id = ?
    ORDER BY COALESCE(item_date, ?) ASC, time ASC
");
$stmt->execute([$eventId, $event['event_date']]);
$items = $stmt->fetchAll();

$showAddModal = isset($_GET['action']) && $_GET['action'] === 'add';

require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-header__title">Program</h1>
        <p class="page-header__subtitle">
            <?= count($items) ?> programpunkter for <?= formatDate($event['event_date'], true) ?>
        </p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_PATH ?>/guest/schedule.php" target="_blank" class="btn btn--secondary">Se gæstevisning</a>
        <button onclick="openModal('add-modal')" class="btn btn--primary">+ Nyt punkt</button>
    </div>
</div>

<!-- Schedule Items -->
<?php if (empty($items)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state__icon">🕐</div>
            <h3 class="empty-state__title">Intet program endnu</h3>
            <p class="empty-state__text">Tilføj programpunkter for at vise tidsplanen til gæsterne</p>
            <button onclick="openModal('add-modal')" class="btn btn--primary mt-md">
                + Nyt punkt
            </button>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="schedule-timeline">
            <?php foreach ($items as $index => $item): ?>
                <div class="schedule-item">
                    <div class="schedule-item__time">
                        <?= date('H:i', strtotime($item['time'])) ?>
                    </div>
                    <div class="schedule-item__dot"></div>
                    <div class="schedule-item__content">
                        <div class="schedule-item__title"><?= escape($item['title']) ?></div>
                        <?php if ($item['description']): ?>
                            <div class="schedule-item__description"><?= escape($item['description']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="schedule-item__actions">
                        <button onclick='editItem(<?= json_encode($item) ?>)'
                                class="btn btn--ghost btn--sm"
                                title="Rediger">
                            ✏️
                        </button>
                        <form method="POST" style="display: inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                            <button type="submit"
                                    class="btn btn--ghost btn--sm"
                                    title="Slet"
                                    data-confirm="Slet dette programpunkt?">
                                🗑️
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<style>
.schedule-timeline {
    position: relative;
    padding-left: 80px;
}

.schedule-timeline::before {
    content: '';
    position: absolute;
    left: 70px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: var(--color-border-soft);
}

.schedule-item {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: var(--space-md);
    padding: var(--space-md) 0;
    border-bottom: 1px solid var(--color-border-soft);
}

.schedule-item:last-child {
    border-bottom: none;
}

.schedule-item__time {
    position: absolute;
    left: -80px;
    width: 60px;
    text-align: right;
    font-family: 'Cormorant Garamond', serif;
    font-size: var(--text-xl);
    font-weight: 600;
    color: var(--color-primary-deep);
}

.schedule-item__dot {
    position: absolute;
    left: -14px;
    top: calc(var(--space-md) + 6px);
    width: 12px;
    height: 12px;
    background: var(--color-primary);
    border-radius: 50%;
    border: 2px solid var(--color-surface);
    z-index: 1;
}

.schedule-item__content {
    flex: 1;
    padding-left: var(--space-sm);
}

.schedule-item__title {
    font-weight: 600;
    color: var(--color-text);
}

.schedule-item__description {
    font-size: var(--text-sm);
    color: var(--color-text-muted);
    margin-top: var(--space-3xs);
}

.schedule-item__actions {
    display: flex;
    gap: var(--space-xs);
}
</style>

<!-- Add Modal -->
<div id="add-modal" class="modal-overlay <?= $showAddModal ? 'modal-overlay--active' : '' ?>">
    <div class="modal">
        <div class="modal__header">
            <h2 class="modal__title">Nyt programpunkt</h2>
            <button class="modal__close" onclick="closeModal('add-modal')">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add">
            <div class="modal__body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-md);">
                    <div class="form-group">
                        <label class="form-label">Dato *</label>
                        <input type="date" name="item_date" class="form-input" value="<?= $event['event_date'] ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tidspunkt *</label>
                        <input type="time" name="time" class="form-input" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Titel *</label>
                    <input type="text" name="title" class="form-input" required placeholder="F.eks. Kirke">
                </div>
                <div class="form-group">
                    <label class="form-label">Beskrivelse</label>
                    <textarea name="description" class="form-input" rows="2" placeholder="F.eks. Gudstjeneste i Vor Frue Kirke"></textarea>
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--secondary" onclick="closeModal('add-modal')">Annuller</button>
                <button type="submit" class="btn btn--primary">Tilføj punkt</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="edit-modal" class="modal-overlay">
    <div class="modal">
        <div class="modal__header">
            <h2 class="modal__title">Rediger programpunkt</h2>
            <button class="modal__close" onclick="closeModal('edit-modal')">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit-id">
            <div class="modal__body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-md);">
                    <div class="form-group">
                        <label class="form-label">Dato *</label>
                        <input type="date" name="item_date" id="edit-date" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tidspunkt *</label>
                        <input type="time" name="time" id="edit-time" class="form-input" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Titel *</label>
                    <input type="text" name="title" id="edit-title" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Beskrivelse</label>
                    <textarea name="description" id="edit-description" class="form-input" rows="2"></textarea>
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
    document.getElementById('edit-date').value = item.item_date || '<?= $event['event_date'] ?>';
    document.getElementById('edit-time').value = item.time;
    document.getElementById('edit-title').value = item.title;
    document.getElementById('edit-description').value = item.description || '';
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

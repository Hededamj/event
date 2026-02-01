<?php
/**
 * Admin - Menu Management
 */
require_once __DIR__ . '/../includes/admin-header.php';

// Courses
$courses = [
    'starter' => ['label' => 'Forret', 'icon' => '🥗'],
    'main' => ['label' => 'Hovedret', 'icon' => '🍽️'],
    'dessert' => ['label' => 'Dessert', 'icon' => '🍰'],
    'drink' => ['label' => 'Drikkevarer', 'icon' => '🥂'],
    'snack' => ['label' => 'Snacks', 'icon' => '🍿'],
    'other' => ['label' => 'Andet', 'icon' => '✨']
];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $course = $_POST['course'] ?? 'main';

        if ($title) {
            // Get max sort order
            $stmt = $db->prepare("SELECT MAX(sort_order) FROM menu_items WHERE event_id = ?");
            $stmt->execute([$eventId]);
            $maxOrder = $stmt->fetchColumn() ?? 0;

            $stmt = $db->prepare("
                INSERT INTO menu_items (event_id, title, description, course, sort_order)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $eventId,
                $title,
                $description ?: null,
                $course,
                $maxOrder + 1
            ]);

            setFlash('success', 'Ret tilføjet til menuen');
            redirect(BASE_PATH . '/admin/menu.php');
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $course = $_POST['course'] ?? 'main';

        if ($title && $id) {
            $stmt = $db->prepare("
                UPDATE menu_items
                SET title = ?, description = ?, course = ?
                WHERE id = ? AND event_id = ?
            ");
            $stmt->execute([
                $title,
                $description ?: null,
                $course,
                $id,
                $eventId
            ]);

            setFlash('success', 'Ret opdateret');
            redirect(BASE_PATH . '/admin/menu.php');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $db->prepare("DELETE FROM menu_items WHERE id = ? AND event_id = ?");
            $stmt->execute([$id, $eventId]);

            setFlash('success', 'Ret slettet');
            redirect(BASE_PATH . '/admin/menu.php');
        }
    }
}

// Get all menu items
$stmt = $db->prepare("
    SELECT * FROM menu_items
    WHERE event_id = ?
    ORDER BY sort_order ASC
");
$stmt->execute([$eventId]);
$items = $stmt->fetchAll();

// Group by course
$menuByCourse = [];
foreach ($items as $item) {
    $course = $item['course'] ?? 'other';
    $menuByCourse[$course][] = $item;
}

$showAddModal = isset($_GET['action']) && $_GET['action'] === 'add';

require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-header__title">Menu</h1>
        <p class="page-header__subtitle">
            <?= count($items) ?> retter på menuen
        </p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_PATH ?>/guest/menu.php" target="_blank" class="btn btn--secondary">Se gæstevisning</a>
        <button onclick="openModal('add-modal')" class="btn btn--primary">+ Ny ret</button>
    </div>
</div>

<!-- Menu Items by Course -->
<?php if (empty($items)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state__icon">🍽️</div>
            <h3 class="empty-state__title">Ingen menu endnu</h3>
            <p class="empty-state__text">Tilføj retter for at vise menuen til gæsterne</p>
            <button onclick="openModal('add-modal')" class="btn btn--primary mt-md">
                + Ny ret
            </button>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($courses as $courseKey => $courseInfo): ?>
        <?php if (isset($menuByCourse[$courseKey]) && !empty($menuByCourse[$courseKey])): ?>
            <div class="card mb-md">
                <h2 class="card__title mb-sm">
                    <span><?= $courseInfo['icon'] ?></span>
                    <?= $courseInfo['label'] ?>
                    <span class="small text-muted" style="font-weight: 400; margin-left: 8px;">
                        (<?= count($menuByCourse[$courseKey]) ?>)
                    </span>
                </h2>

                <div class="menu-list">
                    <?php foreach ($menuByCourse[$courseKey] as $item): ?>
                        <div class="menu-item">
                            <div class="menu-item__content">
                                <div class="menu-item__title"><?= escape($item['title']) ?></div>
                                <?php if ($item['description']): ?>
                                    <div class="menu-item__description"><?= escape($item['description']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="menu-item__actions">
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
                                            data-confirm="Slet denne ret?">
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

<style>
.menu-list {
    display: flex;
    flex-direction: column;
}

.menu-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--space-sm) 0;
    border-bottom: 1px solid var(--color-border-soft);
}

.menu-item:last-child {
    border-bottom: none;
}

.menu-item__content {
    flex: 1;
}

.menu-item__title {
    font-family: 'Cormorant Garamond', serif;
    font-size: var(--text-lg);
    font-weight: 500;
    color: var(--color-text);
}

.menu-item__description {
    font-size: var(--text-sm);
    color: var(--color-text-muted);
    margin-top: var(--space-3xs);
}

.menu-item__actions {
    display: flex;
    gap: var(--space-xs);
}
</style>

<!-- Add Modal -->
<div id="add-modal" class="modal-overlay <?= $showAddModal ? 'modal-overlay--active' : '' ?>">
    <div class="modal">
        <div class="modal__header">
            <h2 class="modal__title">Ny ret</h2>
            <button class="modal__close" onclick="closeModal('add-modal')">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add">
            <div class="modal__body">
                <div class="form-group">
                    <label class="form-label">Navn på ret *</label>
                    <input type="text" name="title" class="form-input" required placeholder="F.eks. Grillet laks">
                </div>
                <div class="form-group">
                    <label class="form-label">Beskrivelse</label>
                    <textarea name="description" class="form-input" rows="2" placeholder="F.eks. med grøntsager og citronsauce"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Kategori</label>
                    <select name="course" class="form-input">
                        <?php foreach ($courses as $key => $info): ?>
                            <option value="<?= $key ?>" <?= $key === 'main' ? 'selected' : '' ?>>
                                <?= $info['icon'] ?> <?= $info['label'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--secondary" onclick="closeModal('add-modal')">Annuller</button>
                <button type="submit" class="btn btn--primary">Tilføj ret</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="edit-modal" class="modal-overlay">
    <div class="modal">
        <div class="modal__header">
            <h2 class="modal__title">Rediger ret</h2>
            <button class="modal__close" onclick="closeModal('edit-modal')">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit-id">
            <div class="modal__body">
                <div class="form-group">
                    <label class="form-label">Navn på ret *</label>
                    <input type="text" name="title" id="edit-title" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Beskrivelse</label>
                    <textarea name="description" id="edit-description" class="form-input" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Kategori</label>
                    <select name="course" id="edit-course" class="form-input">
                        <?php foreach ($courses as $key => $info): ?>
                            <option value="<?= $key ?>"><?= $info['icon'] ?> <?= $info['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
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
    document.getElementById('edit-course').value = item.course || 'main';
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

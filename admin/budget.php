<?php
/**
 * Admin - Budget Management
 */
require_once __DIR__ . '/../includes/admin-header.php';

// Confirmand cannot access budget
if (isConfirmand()) {
    setFlash('error', 'Du har ikke adgang til budget-siden');
    redirect(BASE_PATH . '/admin/index.php');
}

// Categories
$categories = [
    'lokale' => ['label' => 'Lokale', 'icon' => '🏠'],
    'mad' => ['label' => 'Mad & Drikke', 'icon' => '🍽️'],
    'pynt' => ['label' => 'Pynt & Dekoration', 'icon' => '🎈'],
    'toj' => ['label' => 'Tøj', 'icon' => '👗'],
    'gaver' => ['label' => 'Gaver', 'icon' => '🎁'],
    'underholdning' => ['label' => 'Underholdning', 'icon' => '🎉'],
    'foto' => ['label' => 'Fotograf', 'icon' => '📷'],
    'andet' => ['label' => 'Andet', 'icon' => '📌']
];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $description = trim($_POST['description'] ?? '');
        $category = $_POST['category'] ?? 'andet';
        $estimated = !empty($_POST['estimated']) ? (float)$_POST['estimated'] : 0;
        $actual = !empty($_POST['actual']) ? (float)$_POST['actual'] : null;
        $paid = isset($_POST['paid']) ? 1 : 0;

        if ($description) {
            $stmt = $db->prepare("
                INSERT INTO budget_items (event_id, description, category, estimated, actual, paid)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $eventId,
                $description,
                $category,
                $estimated,
                $actual,
                $paid
            ]);

            setFlash('success', 'Budgetpost tilføjet');
            redirect(BASE_PATH . '/admin/budget.php');
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $category = $_POST['category'] ?? 'andet';
        $estimated = !empty($_POST['estimated']) ? (float)$_POST['estimated'] : 0;
        $actual = !empty($_POST['actual']) ? (float)$_POST['actual'] : null;
        $paid = isset($_POST['paid']) ? 1 : 0;

        if ($description && $id) {
            $stmt = $db->prepare("
                UPDATE budget_items
                SET description = ?, category = ?, estimated = ?, actual = ?, paid = ?
                WHERE id = ? AND event_id = ?
            ");
            $stmt->execute([
                $description,
                $category,
                $estimated,
                $actual,
                $paid,
                $id,
                $eventId
            ]);

            setFlash('success', 'Budgetpost opdateret');
            redirect(BASE_PATH . '/admin/budget.php');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $db->prepare("DELETE FROM budget_items WHERE id = ? AND event_id = ?");
            $stmt->execute([$id, $eventId]);

            setFlash('success', 'Budgetpost slettet');
            redirect(BASE_PATH . '/admin/budget.php');
        }
    } elseif ($action === 'toggle_paid') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $db->prepare("UPDATE budget_items SET paid = NOT paid WHERE id = ? AND event_id = ?");
            $stmt->execute([$id, $eventId]);
            redirect(BASE_PATH . '/admin/budget.php');
        }
    }
}

// Get all budget items
$stmt = $db->prepare("
    SELECT * FROM budget_items
    WHERE event_id = ?
    ORDER BY category ASC, created_at ASC
");
$stmt->execute([$eventId]);
$items = $stmt->fetchAll();

// Stats
$totalEstimated = array_sum(array_map(fn($i) => $i['estimated'], $items));
$totalActual = array_sum(array_map(fn($i) => $i['actual'] ?? $i['estimated'], $items));
$totalPaid = array_sum(array_map(fn($i) => $i['paid'] ? ($i['actual'] ?? $i['estimated']) : 0, $items));

// Group by category
$itemsByCategory = [];
foreach ($items as $item) {
    $cat = $item['category'] ?? 'andet';
    $itemsByCategory[$cat][] = $item;
}

$showAddModal = isset($_GET['action']) && $_GET['action'] === 'add';

require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-header__title">Budget</h1>
        <p class="page-header__subtitle">
            <?= count($items) ?> budgetposter
        </p>
    </div>
    <div class="page-header__actions">
        <button onclick="openModal('add-modal')" class="btn btn--primary">+ Ny post</button>
    </div>
</div>

<!-- Budget Summary -->
<div class="card mb-md">
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-md); text-align: center;">
        <div>
            <div style="font-size: var(--text-2xl); font-family: 'Cormorant Garamond', serif; color: var(--color-text);">
                <?= formatCurrency($totalEstimated) ?>
            </div>
            <div class="small text-muted">Estimeret</div>
        </div>
        <div>
            <div style="font-size: var(--text-2xl); font-family: 'Cormorant Garamond', serif; color: var(--color-primary-deep);">
                <?= formatCurrency($totalActual) ?>
            </div>
            <div class="small text-muted">Faktisk</div>
        </div>
        <div>
            <div style="font-size: var(--text-2xl); font-family: 'Cormorant Garamond', serif; color: var(--color-success);">
                <?= formatCurrency($totalPaid) ?>
            </div>
            <div class="small text-muted">Betalt</div>
        </div>
    </div>
    <?php if ($totalEstimated > 0): ?>
        <?php $paidPercent = round($totalPaid / $totalEstimated * 100); ?>
        <div class="mt-md">
            <div class="flex flex-between items-center mb-xs">
                <span class="small text-muted">Betalt</span>
                <span class="small text-muted"><?= $paidPercent ?>%</span>
            </div>
            <div class="progress">
                <div class="progress__bar progress__bar--success" style="width: <?= min(100, $paidPercent) ?>%;"></div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Budget Items by Category -->
<?php if (empty($items)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state__icon">💰</div>
            <h3 class="empty-state__title">Intet budget endnu</h3>
            <p class="empty-state__text">Tilføj budgetposter for at holde styr på udgifterne</p>
            <button onclick="openModal('add-modal')" class="btn btn--primary mt-md">
                + Ny post
            </button>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($categories as $catKey => $catInfo): ?>
        <?php if (isset($itemsByCategory[$catKey]) && !empty($itemsByCategory[$catKey])): ?>
            <?php
            $catEstimated = array_sum(array_map(fn($i) => $i['estimated'], $itemsByCategory[$catKey]));
            $catActual = array_sum(array_map(fn($i) => $i['actual'] ?? $i['estimated'], $itemsByCategory[$catKey]));
            ?>
            <div class="card mb-md">
                <div class="flex flex-between items-center mb-sm">
                    <h2 class="card__title">
                        <span><?= $catInfo['icon'] ?></span>
                        <?= $catInfo['label'] ?>
                    </h2>
                    <span class="text-muted"><?= formatCurrency($catActual) ?></span>
                </div>

                <div class="budget-list">
                    <?php foreach ($itemsByCategory[$catKey] as $item): ?>
                        <div class="budget-item <?= $item['paid'] ? 'budget-item--paid' : '' ?>">
                            <form method="POST" class="budget-item__checkbox">
                                <input type="hidden" name="action" value="toggle_paid">
                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                <button type="submit" class="budget-checkbox <?= $item['paid'] ? 'budget-checkbox--checked' : '' ?>">
                                    <?= $item['paid'] ? '✓' : '' ?>
                                </button>
                            </form>
                            <div class="budget-item__content">
                                <div class="budget-item__description"><?= escape($item['description']) ?></div>
                            </div>
                            <div class="budget-item__amounts">
                                <?php if ($item['actual'] !== null && $item['actual'] != $item['estimated']): ?>
                                    <span class="budget-item__estimated"><?= formatCurrency($item['estimated']) ?></span>
                                <?php endif; ?>
                                <span class="budget-item__actual"><?= formatCurrency($item['actual'] ?? $item['estimated']) ?></span>
                            </div>
                            <div class="budget-item__actions">
                                <button onclick='editItem(<?= json_encode($item) ?>)'
                                        class="btn btn--ghost btn--sm"
                                        title="Rediger">
                                    ✏️
                                </button>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                    <button type="submit"
                                            class="btn btn--ghost btn--sm"
                                            title="Slet"
                                            data-confirm="Slet denne post?">
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
.budget-list {
    display: flex;
    flex-direction: column;
}

.budget-item {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    padding: var(--space-sm) 0;
    border-bottom: 1px solid var(--color-border-soft);
}

.budget-item:last-child {
    border-bottom: none;
}

.budget-item--paid {
    opacity: 0.6;
}

.budget-item--paid .budget-item__description {
    text-decoration: line-through;
}

.budget-checkbox {
    width: 24px;
    height: 24px;
    border: 2px solid var(--color-border);
    border-radius: var(--radius-sm);
    background: transparent;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--text-sm);
    color: var(--color-success);
    transition: all 0.2s;
}

.budget-checkbox:hover {
    border-color: var(--color-primary);
}

.budget-checkbox--checked {
    background: var(--color-success);
    border-color: var(--color-success);
    color: white;
}

.budget-item__content {
    flex: 1;
}

.budget-item__description {
    color: var(--color-text);
}

.budget-item__amounts {
    text-align: right;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.budget-item__estimated {
    font-size: var(--text-xs);
    color: var(--color-text-muted);
    text-decoration: line-through;
}

.budget-item__actual {
    font-weight: 600;
    color: var(--color-text);
}

.budget-item__actions {
    display: flex;
    gap: var(--space-xs);
}
</style>

<!-- Add Modal -->
<div id="add-modal" class="modal-overlay <?= $showAddModal ? 'modal-overlay--active' : '' ?>">
    <div class="modal">
        <div class="modal__header">
            <h2 class="modal__title">Ny budgetpost</h2>
            <button class="modal__close" onclick="closeModal('add-modal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal__body">
                <div class="form-group">
                    <label class="form-label">Beskrivelse *</label>
                    <input type="text" name="description" class="form-input" required placeholder="F.eks. Blomster">
                </div>
                <div class="form-group">
                    <label class="form-label">Kategori</label>
                    <select name="category" class="form-input">
                        <?php foreach ($categories as $key => $info): ?>
                            <option value="<?= $key ?>"><?= $info['icon'] ?> <?= $info['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Estimeret (kr.)</label>
                        <input type="number" name="estimated" class="form-input" step="0.01" min="0" placeholder="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Faktisk (kr.)</label>
                        <input type="number" name="actual" class="form-input" step="0.01" min="0" placeholder="Udfyld når betalt">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-checkbox">
                        <input type="checkbox" name="paid">
                        <span>Betalt</span>
                    </label>
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--secondary" onclick="closeModal('add-modal')">Annuller</button>
                <button type="submit" class="btn btn--primary">Tilføj post</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="edit-modal" class="modal-overlay">
    <div class="modal">
        <div class="modal__header">
            <h2 class="modal__title">Rediger budgetpost</h2>
            <button class="modal__close" onclick="closeModal('edit-modal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit-id">
            <div class="modal__body">
                <div class="form-group">
                    <label class="form-label">Beskrivelse *</label>
                    <input type="text" name="description" id="edit-description" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Kategori</label>
                    <select name="category" id="edit-category" class="form-input">
                        <?php foreach ($categories as $key => $info): ?>
                            <option value="<?= $key ?>"><?= $info['icon'] ?> <?= $info['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Estimeret (kr.)</label>
                        <input type="number" name="estimated" id="edit-estimated" class="form-input" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Faktisk (kr.)</label>
                        <input type="number" name="actual" id="edit-actual" class="form-input" step="0.01" min="0">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-checkbox">
                        <input type="checkbox" name="paid" id="edit-paid">
                        <span>Betalt</span>
                    </label>
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
    document.getElementById('edit-description').value = item.description;
    document.getElementById('edit-category').value = item.category || 'andet';
    document.getElementById('edit-estimated').value = item.estimated || '';
    document.getElementById('edit-actual').value = item.actual || '';
    document.getElementById('edit-paid').checked = item.paid == 1;
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

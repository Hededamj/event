<?php
/**
 * Budget Management Page
 * Included by manage.php
 */

// Check if feature is available
if (!$hasBudget): ?>
<div class="upgrade-notice">
    <div class="upgrade-notice-content">
        <h4>Budget er en premium-funktion</h4>
        <p>Opgrader til Basis eller hojere for at fa adgang til budget-funktionen.</p>
    </div>
    <a href="/app/account/subscription.php" class="btn">Opgrader nu</a>
</div>
<?php return; endif;

// Categories
$categories = [
    'lokale' => ['label' => 'Lokale', 'icon' => '🏠'],
    'mad' => ['label' => 'Mad & Drikke', 'icon' => '🍽️'],
    'pynt' => ['label' => 'Pynt & Dekoration', 'icon' => '🎈'],
    'toj' => ['label' => 'Toj', 'icon' => '👗'],
    'gaver' => ['label' => 'Gaver', 'icon' => '🎁'],
    'underholdning' => ['label' => 'Underholdning', 'icon' => '🎉'],
    'foto' => ['label' => 'Fotograf', 'icon' => '📷'],
    'andet' => ['label' => 'Andet', 'icon' => '📌']
];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyAccountCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Ugyldig anmodning.');
        redirect("?id=$eventId&page=budget");
    }

    $action = $_POST['action'];

    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        $category = $_POST['category'] ?? 'andet';
        $estimated = !empty($_POST['estimated_cost']) ? (float)$_POST['estimated_cost'] : 0;
        $actual = !empty($_POST['actual_cost']) ? (float)$_POST['actual_cost'] : null;
        $isPaid = isset($_POST['is_paid']) ? 1 : 0;

        if ($title) {
            $stmt = $db->prepare("
                INSERT INTO budget_items (event_id, title, category, estimated_cost, actual_cost, is_paid)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$eventId, $title, $category, $estimated, $actual, $isPaid]);
            setFlash('success', 'Budgetpost tilfojet');
        }
        redirect("?id=$eventId&page=budget");

    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $category = $_POST['category'] ?? 'andet';
        $estimated = !empty($_POST['estimated_cost']) ? (float)$_POST['estimated_cost'] : 0;
        $actual = !empty($_POST['actual_cost']) ? (float)$_POST['actual_cost'] : null;
        $isPaid = isset($_POST['is_paid']) ? 1 : 0;

        if ($title && $id) {
            $stmt = $db->prepare("
                UPDATE budget_items
                SET title = ?, category = ?, estimated_cost = ?, actual_cost = ?, is_paid = ?
                WHERE id = ? AND event_id = ?
            ");
            $stmt->execute([$title, $category, $estimated, $actual, $isPaid, $id, $eventId]);
            setFlash('success', 'Budgetpost opdateret');
        }
        redirect("?id=$eventId&page=budget");

    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $db->prepare("DELETE FROM budget_items WHERE id = ? AND event_id = ?");
            $stmt->execute([$id, $eventId]);
            setFlash('success', 'Budgetpost slettet');
        }
        redirect("?id=$eventId&page=budget");

    } elseif ($action === 'toggle_paid') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $db->prepare("UPDATE budget_items SET is_paid = NOT COALESCE(is_paid, 0) WHERE id = ? AND event_id = ?");
            $stmt->execute([$id, $eventId]);
        }
        redirect("?id=$eventId&page=budget");
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
$totalEstimated = 0;
$totalActual = 0;
$totalPaid = 0;
foreach ($items as $item) {
    $totalEstimated += (float)$item['estimated_cost'];
    $actual = $item['actual_cost'] !== null ? (float)$item['actual_cost'] : (float)$item['estimated_cost'];
    $totalActual += $actual;
    if ($item['is_paid']) {
        $totalPaid += $actual;
    }
}

// Group by category
$itemsByCategory = [];
foreach ($items as $item) {
    $cat = $item['category'] ?? 'andet';
    $itemsByCategory[$cat][] = $item;
}
?>

<div class="page-header-actions">
    <div>
        <h2 class="section-title">Budget</h2>
        <p class="section-subtitle"><?= count($items) ?> budgetposter</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="showAddModal()">+ Ny post</button>
</div>

<!-- Budget Summary -->
<div class="card" style="margin-bottom: 24px;">
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; text-align: center;">
        <div>
            <div style="font-size: 28px; font-weight: 600; color: var(--gray-900);">
                <?= number_format($totalEstimated, 0, ',', '.') ?> kr
            </div>
            <div style="font-size: 13px; color: var(--gray-500);">Estimeret</div>
        </div>
        <div>
            <div style="font-size: 28px; font-weight: 600; color: var(--primary);">
                <?= number_format($totalActual, 0, ',', '.') ?> kr
            </div>
            <div style="font-size: 13px; color: var(--gray-500);">Faktisk</div>
        </div>
        <div>
            <div style="font-size: 28px; font-weight: 600; color: #10b981;">
                <?= number_format($totalPaid, 0, ',', '.') ?> kr
            </div>
            <div style="font-size: 13px; color: var(--gray-500);">Betalt</div>
        </div>
    </div>
    <?php if ($totalEstimated > 0): ?>
        <?php $paidPercent = round($totalPaid / $totalEstimated * 100); ?>
        <div style="margin-top: 20px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                <span style="font-size: 13px; color: var(--gray-500);">Betalt</span>
                <span style="font-size: 13px; color: var(--gray-500);"><?= $paidPercent ?>%</span>
            </div>
            <div style="height: 8px; background: var(--gray-200); border-radius: 4px; overflow: hidden;">
                <div style="height: 100%; background: #10b981; width: <?= min(100, $paidPercent) ?>%; transition: width 0.3s;"></div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Budget Items by Category -->
<?php if (empty($items)): ?>
    <div class="card">
        <div class="empty-state">
            <span style="font-size: 48px; margin-bottom: 16px; display: block;">💰</span>
            <h3>Intet budget endnu</h3>
            <p>Tilfoj budgetposter for at holde styr pa udgifterne</p>
            <button type="button" class="btn btn-primary" style="margin-top: 16px;" onclick="showAddModal()">+ Ny post</button>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($categories as $catKey => $catInfo): ?>
        <?php if (isset($itemsByCategory[$catKey]) && !empty($itemsByCategory[$catKey])): ?>
            <?php
            $catActual = 0;
            foreach ($itemsByCategory[$catKey] as $item) {
                $catActual += ($item['actual_cost'] !== null ? (float)$item['actual_cost'] : (float)$item['estimated_cost']);
            }
            ?>
            <div class="card" style="margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <h3 style="font-size: 16px; font-weight: 600; margin: 0;">
                        <span><?= $catInfo['icon'] ?></span> <?= $catInfo['label'] ?>
                    </h3>
                    <span style="color: var(--gray-500);"><?= number_format($catActual, 0, ',', '.') ?> kr</span>
                </div>

                <div class="budget-list">
                    <?php foreach ($itemsByCategory[$catKey] as $item): ?>
                        <div class="budget-item <?= $item['is_paid'] ? 'paid' : '' ?>">
                            <form method="POST" style="display: inline;">
                                <?= accountCsrfField() ?>
                                <input type="hidden" name="action" value="toggle_paid">
                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                <button type="submit" class="budget-checkbox <?= $item['is_paid'] ? 'checked' : '' ?>">
                                    <?= $item['is_paid'] ? '✓' : '' ?>
                                </button>
                            </form>

                            <div class="budget-content">
                                <div class="budget-title"><?= htmlspecialchars($item['title']) ?></div>
                            </div>

                            <div class="budget-amounts">
                                <?php if ($item['actual_cost'] !== null && $item['actual_cost'] != $item['estimated_cost']): ?>
                                    <span class="budget-estimated"><?= number_format($item['estimated_cost'], 0, ',', '.') ?> kr</span>
                                <?php endif; ?>
                                <span class="budget-actual"><?= number_format($item['actual_cost'] ?? $item['estimated_cost'], 0, ',', '.') ?> kr</span>
                            </div>

                            <div class="budget-actions">
                                <button type="button" class="btn btn-ghost btn-sm" onclick='editItem(<?= json_encode($item) ?>)'>✏️</button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Slet denne post?');">
                                    <?= accountCsrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                    <button type="submit" class="btn btn-ghost btn-sm danger">🗑️</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal" style="display: none;">
    <div class="modal" style="max-width: 450px;">
        <div class="modal-header">
            <h3>Tilfoj budgetpost</h3>
            <button type="button" class="modal-close" onclick="hideAddModal()">&times;</button>
        </div>
        <form method="POST">
            <?= accountCsrfField() ?>
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Beskrivelse *</label>
                    <input type="text" name="title" class="form-input" required placeholder="F.eks. Lokale leje">
                </div>
                <div class="form-group">
                    <label class="form-label">Kategori</label>
                    <select name="category" class="form-input">
                        <?php foreach ($categories as $key => $cat): ?>
                            <option value="<?= $key ?>"><?= $cat['icon'] ?> <?= $cat['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Estimeret (kr)</label>
                        <input type="number" name="estimated_cost" class="form-input" step="0.01" min="0" placeholder="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Faktisk (kr)</label>
                        <input type="number" name="actual_cost" class="form-input" step="0.01" min="0" placeholder="Tom = estimeret">
                    </div>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="is_paid">
                        <span>Allerede betalt</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideAddModal()">Annuller</button>
                <button type="submit" class="btn btn-primary">Tilfoj</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal" style="display: none;">
    <div class="modal" style="max-width: 450px;">
        <div class="modal-header">
            <h3>Rediger budgetpost</h3>
            <button type="button" class="modal-close" onclick="hideEditModal()">&times;</button>
        </div>
        <form method="POST">
            <?= accountCsrfField() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Beskrivelse *</label>
                    <input type="text" name="title" id="edit_title" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Kategori</label>
                    <select name="category" id="edit_category" class="form-input">
                        <?php foreach ($categories as $key => $cat): ?>
                            <option value="<?= $key ?>"><?= $cat['icon'] ?> <?= $cat['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Estimeret (kr)</label>
                        <input type="number" name="estimated_cost" id="edit_estimated" class="form-input" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Faktisk (kr)</label>
                        <input type="number" name="actual_cost" id="edit_actual" class="form-input" step="0.01" min="0">
                    </div>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="is_paid" id="edit_paid">
                        <span>Allerede betalt</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideEditModal()">Annuller</button>
                <button type="submit" class="btn btn-primary">Gem</button>
            </div>
        </form>
    </div>
</div>

<style>
    .budget-list {
        display: flex;
        flex-direction: column;
    }

    .budget-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 0;
        border-bottom: 1px solid var(--gray-100);
    }

    .budget-item:last-child {
        border-bottom: none;
    }

    .budget-item.paid {
        opacity: 0.6;
    }

    .budget-item.paid .budget-title {
        text-decoration: line-through;
    }

    .budget-checkbox {
        width: 24px;
        height: 24px;
        border: 2px solid var(--gray-300);
        border-radius: 6px;
        background: transparent;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        color: #10b981;
        transition: all 0.2s;
        flex-shrink: 0;
    }

    .budget-checkbox:hover {
        border-color: var(--primary);
    }

    .budget-checkbox.checked {
        background: #10b981;
        border-color: #10b981;
        color: white;
    }

    .budget-content {
        flex: 1;
        min-width: 0;
    }

    .budget-title {
        font-weight: 500;
    }

    .budget-amounts {
        text-align: right;
        white-space: nowrap;
    }

    .budget-estimated {
        font-size: 12px;
        color: var(--gray-400);
        text-decoration: line-through;
        display: block;
    }

    .budget-actual {
        font-weight: 500;
    }

    .budget-actions {
        display: flex;
        gap: 4px;
        flex-shrink: 0;
    }
</style>

<script>
function showAddModal() {
    document.getElementById('addModal').style.display = 'flex';
}

function hideAddModal() {
    document.getElementById('addModal').style.display = 'none';
}

function showEditModal() {
    document.getElementById('editModal').style.display = 'flex';
}

function hideEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function editItem(item) {
    document.getElementById('edit_id').value = item.id;
    document.getElementById('edit_title').value = item.title || '';
    document.getElementById('edit_category').value = item.category || 'andet';
    document.getElementById('edit_estimated').value = item.estimated_cost || '';
    document.getElementById('edit_actual').value = item.actual_cost || '';
    document.getElementById('edit_paid').checked = item.is_paid == 1;
    showEditModal();
}

document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
});
</script>

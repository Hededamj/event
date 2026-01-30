<?php
/**
 * Platform Admin - Partner Management
 */

require_once __DIR__ . '/../includes/admin-platform-header.php';

$db = getDB();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $partnerId = (int)($_POST['partner_id'] ?? 0);

    if ($partnerId && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        switch ($action) {
            case 'approve':
                $stmt = $db->prepare("
                    UPDATE partners
                    SET status = 'approved', approved_at = NOW(), approved_by = ?, rejection_reason = NULL
                    WHERE id = ?
                ");
                $stmt->execute([$adminId, $partnerId]);
                setFlash('success', 'Partner godkendt');
                break;

            case 'reject':
                $reason = trim($_POST['rejection_reason'] ?? '');
                $stmt = $db->prepare("
                    UPDATE partners
                    SET status = 'rejected', rejection_reason = ?
                    WHERE id = ?
                ");
                $stmt->execute([$reason ?: null, $partnerId]);
                setFlash('success', 'Partner afvist');
                break;

            case 'suspend':
                $stmt = $db->prepare("UPDATE partners SET status = 'suspended' WHERE id = ?");
                $stmt->execute([$partnerId]);
                setFlash('success', 'Partner suspenderet');
                break;

            case 'unsuspend':
                $stmt = $db->prepare("UPDATE partners SET status = 'approved' WHERE id = ?");
                $stmt->execute([$partnerId]);
                setFlash('success', 'Partner genaktiveret');
                break;

            case 'feature':
                $stmt = $db->prepare("UPDATE partners SET is_featured = 1 WHERE id = ?");
                $stmt->execute([$partnerId]);
                setFlash('success', 'Partner fremhævet');
                break;

            case 'unfeature':
                $stmt = $db->prepare("UPDATE partners SET is_featured = 0 WHERE id = ?");
                $stmt->execute([$partnerId]);
                setFlash('success', 'Partner ikke længere fremhævet');
                break;
        }
        redirect(BASE_PATH . '/admin-platform/partners.php');
    }
}

// Filters
$statusFilter = $_GET['status'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$search = trim($_GET['search'] ?? '');

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query
$where = ["1=1"];
$params = [];

if ($statusFilter) {
    $where[] = "p.status = ?";
    $params[] = $statusFilter;
}

if ($categoryFilter) {
    $where[] = "pc.slug = ?";
    $params[] = $categoryFilter;
}

if ($search) {
    $where[] = "(p.company_name LIKE ? OR p.email LIKE ? OR p.contact_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = implode(' AND ', $where);

// Count total
$stmt = $db->prepare("
    SELECT COUNT(*)
    FROM partners p
    JOIN partner_categories pc ON p.category_id = pc.id
    WHERE $whereClause
");
$stmt->execute($params);
$totalPartners = $stmt->fetchColumn();
$totalPages = ceil($totalPartners / $perPage);

// Get partners
$stmt = $db->prepare("
    SELECT p.*, pc.name as category_name, pc.icon as category_icon,
           a.name as account_name, a.email as account_email
    FROM partners p
    JOIN partner_categories pc ON p.category_id = pc.id
    LEFT JOIN accounts a ON p.account_id = a.id
    WHERE $whereClause
    ORDER BY
        CASE p.status WHEN 'pending' THEN 0 ELSE 1 END,
        p.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$partners = $stmt->fetchAll();

// Get categories for filter
$categories = $db->query("SELECT slug, name, icon FROM partner_categories WHERE is_active = 1 ORDER BY sort_order")->fetchAll();

// Get status counts
$statusCounts = [];
$stmt = $db->query("SELECT status, COUNT(*) as count FROM partners GROUP BY status");
while ($row = $stmt->fetch()) {
    $statusCounts[$row['status']] = $row['count'];
}
?>

<header class="platform-header">
    <h1 class="page-title">Partnere</h1>
    <div class="header-actions">
        <?php if (($statusCounts['pending'] ?? 0) > 0): ?>
            <span class="badge badge-warning" style="font-size: 0.9rem; padding: 0.5rem 1rem;">
                <?= $statusCounts['pending'] ?> afventer godkendelse
            </span>
        <?php endif; ?>
    </div>
</header>

<div class="platform-content">
    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
            <?= escape($flash['message']) ?>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid mb-lg">
        <div class="stat-card">
            <div class="stat-label">Afventer</div>
            <div class="stat-value" style="color: var(--color-warning);">
                <?= number_format($statusCounts['pending'] ?? 0) ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Godkendte</div>
            <div class="stat-value text-success"><?= number_format($statusCounts['approved'] ?? 0) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Afviste</div>
            <div class="stat-value"><?= number_format($statusCounts['rejected'] ?? 0) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Suspenderede</div>
            <div class="stat-value text-error"><?= number_format($statusCounts['suspended'] ?? 0) ?></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-lg">
        <form method="GET" class="flex gap-md items-center" style="flex-wrap: wrap;">
            <div class="search-box" style="flex: 1; min-width: 200px;">
                <span class="search-box-icon">&#128269;</span>
                <input type="text" name="search" class="form-input" placeholder="Søg efter navn, email..."
                       value="<?= escape($search) ?>" style="padding-left: 40px;">
            </div>

            <select name="status" class="form-input form-select" style="width: auto;">
                <option value="">Alle status</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Afventer</option>
                <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Godkendt</option>
                <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Afvist</option>
                <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Suspenderet</option>
            </select>

            <select name="category" class="form-input form-select" style="width: auto;">
                <option value="">Alle kategorier</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= escape($cat['slug']) ?>" <?= $categoryFilter === $cat['slug'] ? 'selected' : '' ?>>
                        <?= $cat['icon'] ?> <?= escape($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-primary">Filtrer</button>

            <?php if ($search || $statusFilter || $categoryFilter): ?>
                <a href="<?= BASE_PATH ?>/admin-platform/partners.php" class="btn btn-secondary">Nulstil</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Partners Table -->
    <div class="card">
        <?php if (empty($partners)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">&#127970;</div>
                <p>Ingen partnere fundet</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Partner</th>
                            <th>Kategori</th>
                            <th>Kontakt</th>
                            <th>Status</th>
                            <th>Stats</th>
                            <th>Oprettet</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($partners as $partner): ?>
                            <tr>
                                <td>
                                    <div class="font-medium"><?= escape($partner['company_name']) ?></div>
                                    <?php if ($partner['is_featured']): ?>
                                        <span class="badge badge-warning">Fremhævet</span>
                                    <?php endif; ?>
                                    <?php if ($partner['city']): ?>
                                        <div class="text-xs text-muted"><?= escape($partner['city']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span><?= $partner['category_icon'] ?></span>
                                    <?= escape($partner['category_name']) ?>
                                </td>
                                <td>
                                    <div class="text-sm"><?= escape($partner['contact_name']) ?></div>
                                    <div class="text-xs text-muted"><?= escape($partner['email']) ?></div>
                                    <?php if ($partner['phone']): ?>
                                        <div class="text-xs text-muted"><?= escape($partner['phone']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusBadge = match($partner['status']) {
                                        'approved' => 'badge-success',
                                        'pending' => 'badge-warning',
                                        'rejected' => 'badge-error',
                                        'suspended' => 'badge-error',
                                        default => 'badge-info'
                                    };
                                    $statusText = match($partner['status']) {
                                        'approved' => 'Godkendt',
                                        'pending' => 'Afventer',
                                        'rejected' => 'Afvist',
                                        'suspended' => 'Suspenderet',
                                        default => $partner['status']
                                    };
                                    ?>
                                    <span class="badge <?= $statusBadge ?>"><?= $statusText ?></span>
                                </td>
                                <td class="text-sm">
                                    <div><?= number_format($partner['view_count']) ?> visninger</div>
                                    <div><?= number_format($partner['inquiry_count']) ?> henvendelser</div>
                                </td>
                                <td class="text-sm text-muted">
                                    <?= date('d/m/Y', strtotime($partner['created_at'])) ?>
                                </td>
                                <td>
                                    <div class="flex gap-sm" style="flex-wrap: wrap;">
                                        <?php if ($partner['status'] === 'approved'): ?>
                                            <a href="<?= BASE_PATH ?>/partners/profile.php?id=<?= $partner['id'] ?>"
                                               class="btn btn-secondary btn-sm" target="_blank">
                                                Se profil
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($partner['status'] === 'pending'): ?>
                                            <form method="POST" style="display: inline;">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="partner_id" value="<?= $partner['id'] ?>">
                                                <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">
                                                    Godkend
                                                </button>
                                            </form>

                                            <button type="button" class="btn btn-danger btn-sm"
                                                    onclick="showRejectModal(<?= $partner['id'] ?>)">
                                                Afvis
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($partner['status'] === 'approved'): ?>
                                            <?php if ($partner['is_featured']): ?>
                                                <form method="POST" style="display: inline;">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="partner_id" value="<?= $partner['id'] ?>">
                                                    <button type="submit" name="action" value="unfeature" class="btn btn-secondary btn-sm">
                                                        Fjern fremhævning
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" style="display: inline;">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="partner_id" value="<?= $partner['id'] ?>">
                                                    <button type="submit" name="action" value="feature" class="btn btn-secondary btn-sm">
                                                        Fremhæv
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <form method="POST" style="display: inline;">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="partner_id" value="<?= $partner['id'] ?>">
                                                <button type="submit" name="action" value="suspend" class="btn btn-danger btn-sm"
                                                        onclick="return confirm('Suspender denne partner?')">
                                                    Suspender
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($partner['status'] === 'suspended'): ?>
                                            <form method="POST" style="display: inline;">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="partner_id" value="<?= $partner['id'] ?>">
                                                <button type="submit" name="action" value="unsuspend" class="btn btn-success btn-sm">
                                                    Genaktiver
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($partner['status'] === 'rejected'): ?>
                                            <form method="POST" style="display: inline;">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="partner_id" value="<?= $partner['id'] ?>">
                                                <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">
                                                    Godkend alligevel
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">&#8592; Forrige</a>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    ?>

                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Næste &#8594;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 12px; padding: 2rem; max-width: 400px; width: 90%;">
        <h3 style="margin-bottom: 1rem;">Afvis partner</h3>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="partner_id" id="rejectPartnerId" value="">
            <input type="hidden" name="action" value="reject">

            <div class="form-group">
                <label class="form-label">Årsag (valgfrit)</label>
                <textarea name="rejection_reason" class="form-input" rows="3"
                          placeholder="Beskriv hvorfor partneren afvises..."></textarea>
            </div>

            <div class="flex gap-sm">
                <button type="submit" class="btn btn-danger">Afvis partner</button>
                <button type="button" class="btn btn-secondary" onclick="hideRejectModal()">Annuller</button>
            </div>
        </form>
    </div>
</div>

<script>
function showRejectModal(partnerId) {
    document.getElementById('rejectPartnerId').value = partnerId;
    document.getElementById('rejectModal').style.display = 'flex';
}

function hideRejectModal() {
    document.getElementById('rejectModal').style.display = 'none';
}

// Close modal on backdrop click
document.getElementById('rejectModal').addEventListener('click', function(e) {
    if (e.target === this) hideRejectModal();
});
</script>

<?php require_once __DIR__ . '/../includes/admin-platform-footer.php'; ?>

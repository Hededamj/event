<?php
/**
 * Platform Admin - Vendor Management
 * Lists all vendors (subcontractors) with filters, status actions, and pagination.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin-platform-header.php';

$db = getDB();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $vendorId = (int)($_POST['vendor_id'] ?? 0);

    if ($vendorId && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        switch ($action) {
            case 'approve':
                $stmt = $db->prepare("
                    UPDATE vendors SET status = 'approved', approved_at = NOW(), approved_by = ?, rejection_reason = NULL
                    WHERE id = ?
                ");
                $stmt->execute([$adminId, $vendorId]);
                setFlash('success', 'Leverandør godkendt');
                break;

            case 'reject':
                $reason = trim($_POST['rejection_reason'] ?? '');
                $stmt = $db->prepare("
                    UPDATE vendors SET status = 'rejected', rejection_reason = ?
                    WHERE id = ?
                ");
                $stmt->execute([$reason ?: null, $vendorId]);
                setFlash('success', 'Leverandør afvist');
                break;

            case 'suspend':
                $stmt = $db->prepare("UPDATE vendors SET status = 'suspended' WHERE id = ?");
                $stmt->execute([$vendorId]);
                setFlash('success', 'Leverandør suspenderet');
                break;

            case 'activate':
                $stmt = $db->prepare("UPDATE vendors SET status = 'approved' WHERE id = ?");
                $stmt->execute([$vendorId]);
                setFlash('success', 'Leverandør genaktiveret');
                break;
        }
        redirect(BASE_PATH . '/admin-platform/vendors.php');
    }

    // Handle create vendor (no vendor_id needed)
    if (($action ?? '') === 'create' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $companyName = trim($_POST['company_name'] ?? '');
        $contactName = trim($_POST['contact_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';

        $createErrors = [];
        if (empty($companyName)) $createErrors[] = 'Firmanavn er påkrævet.';
        if (empty($contactName)) $createErrors[] = 'Kontaktperson er påkrævet.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $createErrors[] = 'Gyldig email er påkrævet.';
        if (strlen($password) < 8) $createErrors[] = 'Adgangskode skal være mindst 8 tegn.';

        // Check duplicate email
        if (empty($createErrors)) {
            $chk = $db->prepare("SELECT id FROM vendors WHERE email = ? LIMIT 1");
            $chk->execute([$email]);
            if ($chk->fetch()) $createErrors[] = 'Email er allerede registreret.';
        }

        if (empty($createErrors)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $autoApprove = isset($_POST['auto_approve']);
            $status = $autoApprove ? 'approved' : 'pending';

            $stmt = $db->prepare("
                INSERT INTO vendors (company_name, contact_name, email, phone, password_hash, status, approved_at, approved_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $companyName, $contactName, $email, $phone ?: null, $hash,
                $status,
                $autoApprove ? date('Y-m-d H:i:s') : null,
                $autoApprove ? $adminId : null
            ]);
            setFlash('success', 'Leverandør oprettet' . ($autoApprove ? ' og godkendt' : ''));
        } else {
            setFlash('error', implode(' ', $createErrors));
        }
        redirect(BASE_PATH . '/admin-platform/vendors.php');
    }
}

// Filters
$statusFilter = $_GET['status'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$cityFilter = trim($_GET['city'] ?? '');
$search = trim($_GET['search'] ?? '');

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query
$where = ["1=1"];
$params = [];

if ($statusFilter) {
    $where[] = "v.status = ?";
    $params[] = $statusFilter;
}

if ($categoryFilter) {
    $where[] = "EXISTS (
        SELECT 1 FROM vendor_category_links vcl
        JOIN vendor_categories vc2 ON vcl.category_id = vc2.id
        WHERE vcl.vendor_id = v.id AND vc2.slug = ?
    )";
    $params[] = $categoryFilter;
}

if ($cityFilter) {
    $where[] = "v.city LIKE ?";
    $params[] = "%$cityFilter%";
}

if ($search) {
    $where[] = "(v.company_name LIKE ? OR v.email LIKE ? OR v.contact_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = implode(' AND ', $where);

// Count total
$stmt = $db->prepare("SELECT COUNT(*) FROM vendors v WHERE $whereClause");
$stmt->execute($params);
$totalVendors = $stmt->fetchColumn();
$totalPages = ceil($totalVendors / $perPage);

// Get vendors
$stmt = $db->prepare("
    SELECT v.*,
           (SELECT GROUP_CONCAT(vc.name SEPARATOR ', ')
            FROM vendor_category_links vcl
            JOIN vendor_categories vc ON vcl.category_id = vc.id
            WHERE vcl.vendor_id = v.id) AS category_names
    FROM vendors v
    WHERE $whereClause
    ORDER BY
        CASE v.status WHEN 'pending' THEN 0 ELSE 1 END,
        v.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$vendors = $stmt->fetchAll();

// Get categories for filter
$categories = $db->query("SELECT slug, name, icon FROM vendor_categories WHERE is_active = 1 ORDER BY sort_order")->fetchAll();

// Get status counts
$statusCounts = [];
$stmt = $db->query("SELECT status, COUNT(*) as count FROM vendors GROUP BY status");
while ($row = $stmt->fetch()) {
    $statusCounts[$row['status']] = $row['count'];
}

// Total bookings across all vendors
$totalBookings = $db->query("SELECT COUNT(*) FROM bookings")->fetchColumn();

// Total commission earned
$totalCommission = $db->query("
    SELECT COALESCE(SUM(commission_amount), 0) FROM bookings
    WHERE status IN ('completed', 'reviewed', 'deposited', 'confirmed')
")->fetchColumn();
?>

<header class="platform-header">
    <h1 class="page-title">Leverandører</h1>
    <div class="header-actions">
        <?php if (($statusCounts['pending'] ?? 0) > 0): ?>
            <span class="badge badge-warning" style="font-size: 0.9rem; padding: 0.5rem 1rem;">
                <?= $statusCounts['pending'] ?> afventer godkendelse
            </span>
        <?php endif; ?>
        <button type="button" class="btn btn-primary" onclick="showCreateModal()">+ Opret leverandør</button>
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
            <div class="stat-label">Total leverandører</div>
            <div class="stat-value"><?= number_format(array_sum($statusCounts)) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Afventer godkendelse</div>
            <div class="stat-value" style="color: var(--warning);">
                <?= number_format($statusCounts['pending'] ?? 0) ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Godkendte</div>
            <div class="stat-value text-success"><?= number_format($statusCounts['approved'] ?? 0) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total bookinger</div>
            <div class="stat-value"><?= number_format($totalBookings) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total kommission</div>
            <div class="stat-value"><?= number_format($totalCommission, 2, ',', '.') ?> kr.</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-lg">
        <form method="GET" class="flex gap-md items-center" style="flex-wrap: wrap;">
            <div class="search-box" style="flex: 1; min-width: 200px;">
                <span class="search-box-icon"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg></span>
                <input type="text" name="search" class="form-input" placeholder="Sog efter navn, email..."
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

            <input type="text" name="city" class="form-input" placeholder="By..."
                   value="<?= escape($cityFilter) ?>" style="width: 150px;">

            <button type="submit" class="btn btn-primary">Filtrer</button>

            <?php if ($search || $statusFilter || $categoryFilter || $cityFilter): ?>
                <a href="<?= BASE_PATH ?>/admin-platform/vendors.php" class="btn btn-secondary">Nulstil</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Vendors Table -->
    <div class="card">
        <?php if (empty($vendors)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085"/></svg></div>
                <p>Ingen leverandører fundet</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Virksomhed</th>
                            <th>Email</th>
                            <th>Kategorier</th>
                            <th>Status</th>
                            <th>Bookinger</th>
                            <th>Rating</th>
                            <th>Oprettet</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vendors as $vendor): ?>
                            <tr>
                                <td>
                                    <div class="font-medium"><?= escape($vendor['company_name']) ?></div>
                                    <?php if (!empty($vendor['contact_name'])): ?>
                                        <div class="text-xs text-muted"><?= escape($vendor['contact_name']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($vendor['city'])): ?>
                                        <div class="text-xs text-muted"><?= escape($vendor['city']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-sm"><?= escape($vendor['email']) ?></td>
                                <td class="text-sm">
                                    <?= escape($vendor['category_names'] ?? '-') ?>
                                </td>
                                <td>
                                    <?php
                                    $badgeMap = ['approved' => 'badge-success', 'pending' => 'badge-warning', 'rejected' => 'badge-error', 'suspended' => 'badge-error'];
                                    $textMap = ['approved' => 'Godkendt', 'pending' => 'Afventer', 'rejected' => 'Afvist', 'suspended' => 'Suspenderet'];
                                    $statusBadge = $badgeMap[$vendor['status']] ?? 'badge-info';
                                    $statusText = $textMap[$vendor['status']] ?? $vendor['status'];
                                    ?>
                                    <span class="badge <?= $statusBadge ?>"><?= $statusText ?></span>
                                </td>
                                <td class="text-sm"><?= number_format($vendor['booking_count']) ?></td>
                                <td class="text-sm">
                                    <?php if ($vendor['review_count'] > 0): ?>
                                        <?= number_format($vendor['avg_rating'], 1, ',', '.') ?> / 5
                                        <div class="text-xs text-muted">(<?= $vendor['review_count'] ?> anm.)</div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-sm text-muted">
                                    <?= date('d/m/Y', strtotime($vendor['created_at'])) ?>
                                </td>
                                <td>
                                    <div class="flex gap-sm" style="flex-wrap: wrap;">
                                        <a href="<?= BASE_PATH ?>/admin-platform/vendor-detail.php?id=<?= $vendor['id'] ?>"
                                           class="btn btn-secondary btn-sm">
                                            Vis detaljer
                                        </a>

                                        <?php if ($vendor['status'] === 'pending'): ?>
                                            <form method="POST" style="display: inline;">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="vendor_id" value="<?= $vendor['id'] ?>">
                                                <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">
                                                    Godkend
                                                </button>
                                            </form>

                                            <button type="button" class="btn btn-danger btn-sm"
                                                    onclick="showRejectModal(<?= $vendor['id'] ?>)">
                                                Afvis
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($vendor['status'] === 'approved'): ?>
                                            <form method="POST" style="display: inline;">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="vendor_id" value="<?= $vendor['id'] ?>">
                                                <button type="submit" name="action" value="suspend" class="btn btn-danger btn-sm"
                                                        onclick="return confirm('Suspender denne leverandør?')">
                                                    Suspender
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($vendor['status'] === 'suspended'): ?>
                                            <form method="POST" style="display: inline;">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="vendor_id" value="<?= $vendor['id'] ?>">
                                                <button type="submit" name="action" value="activate" class="btn btn-success btn-sm">
                                                    Genaktiver
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($vendor['status'] === 'rejected'): ?>
                                            <form method="POST" style="display: inline;">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="vendor_id" value="<?= $vendor['id'] ?>">
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
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Naeste &#8594;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 16px; padding: 2rem; max-width: 400px; width: 90%;">
        <h3 style="margin-bottom: 1rem;">Afvis leverandør</h3>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="vendor_id" id="rejectVendorId" value="">
            <input type="hidden" name="action" value="reject">

            <div class="form-group">
                <label class="form-label">Arsag (valgfrit)</label>
                <textarea name="rejection_reason" class="form-input" rows="3"
                          placeholder="Beskriv hvorfor leverandøren afvises..."></textarea>
            </div>

            <div class="flex gap-sm">
                <button type="submit" class="btn btn-danger">Afvis leverandør</button>
                <button type="button" class="btn btn-secondary" onclick="hideRejectModal()">Annuller</button>
            </div>
        </form>
    </div>
</div>

<!-- Create Vendor Modal -->
<div id="createModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 16px; padding: 2rem; max-width: 480px; width: 90%;">
        <h3 style="margin-bottom: 1rem;">Opret leverandør</h3>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label class="form-label">Firmanavn</label>
                <input type="text" name="company_name" class="form-input" required placeholder="Firmanavn">
            </div>

            <div class="form-group">
                <label class="form-label">Kontaktperson</label>
                <input type="text" name="contact_name" class="form-input" required placeholder="Fulde navn">
            </div>

            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-input" required placeholder="firma@email.dk">
            </div>

            <div class="form-group">
                <label class="form-label">Telefon (valgfrit)</label>
                <input type="text" name="phone" class="form-input" placeholder="+45 12 34 56 78">
            </div>

            <div class="form-group">
                <label class="form-label">Adgangskode</label>
                <input type="password" name="password" class="form-input" required minlength="8" placeholder="Mindst 8 tegn">
            </div>

            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="auto_approve" value="1" checked style="width: 18px; height: 18px; accent-color: var(--primary);">
                    Godkend med det samme
                </label>
            </div>

            <div class="flex gap-sm">
                <button type="submit" class="btn btn-primary">Opret</button>
                <button type="button" class="btn btn-secondary" onclick="hideCreateModal()">Annuller</button>
            </div>
        </form>
    </div>
</div>

<script>
function showRejectModal(vendorId) {
    document.getElementById('rejectVendorId').value = vendorId;
    document.getElementById('rejectModal').style.display = 'flex';
}

function hideRejectModal() {
    document.getElementById('rejectModal').style.display = 'none';
}

function showCreateModal() {
    document.getElementById('createModal').style.display = 'flex';
}

function hideCreateModal() {
    document.getElementById('createModal').style.display = 'none';
}

// Close modals on backdrop click
document.getElementById('rejectModal').addEventListener('click', function(e) {
    if (e.target === this) hideRejectModal();
});
document.getElementById('createModal').addEventListener('click', function(e) {
    if (e.target === this) hideCreateModal();
});
</script>

<?php require_once __DIR__ . '/../includes/admin-platform-footer.php'; ?>

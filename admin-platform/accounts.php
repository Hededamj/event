<?php
/**
 * Platform Admin - Accounts List
 */

require_once __DIR__ . '/../includes/admin-platform-header.php';

$db = getDB();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $accountId = (int)($_POST['account_id'] ?? 0);

    if ($accountId && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        switch ($action) {
            case 'activate':
                $stmt = $db->prepare("UPDATE accounts SET is_active = 1 WHERE id = ? AND is_platform_admin = 0");
                $stmt->execute([$accountId]);
                setFlash('success', 'Konto aktiveret');
                break;

            case 'deactivate':
                $stmt = $db->prepare("UPDATE accounts SET is_active = 0 WHERE id = ? AND is_platform_admin = 0");
                $stmt->execute([$accountId]);
                setFlash('success', 'Konto deaktiveret');
                break;
        }
        redirect(BASE_PATH . '/admin-platform/accounts.php');
    }
}

// Filters
$search = trim($_GET['search'] ?? '');
$planFilter = $_GET['plan'] ?? '';
$statusFilter = $_GET['status'] ?? '';

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query
$where = ["a.is_platform_admin = 0"];
$params = [];

if ($search) {
    $where[] = "(a.name LIKE ? OR a.email LIKE ? OR a.company LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($statusFilter === 'active') {
    $where[] = "a.is_active = 1";
} elseif ($statusFilter === 'inactive') {
    $where[] = "a.is_active = 0";
}

if ($planFilter) {
    $where[] = "p.slug = ?";
    $params[] = $planFilter;
}

$whereClause = implode(' AND ', $where);

// Count total
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT a.id)
    FROM accounts a
    LEFT JOIN subscriptions s ON a.id = s.account_id AND s.status = 'active'
    LEFT JOIN plans p ON s.plan_id = p.id
    WHERE $whereClause
");
$stmt->execute($params);
$totalAccounts = $stmt->fetchColumn();
$totalPages = ceil($totalAccounts / $perPage);

// Get accounts
$stmt = $db->prepare("
    SELECT a.*,
           p.name as plan_name,
           p.slug as plan_slug,
           s.status as subscription_status,
           s.current_period_end,
           (SELECT COUNT(*) FROM events WHERE account_id = a.id) as event_count,
           (SELECT COUNT(*) FROM event_owners WHERE account_id = a.id) as owned_events
    FROM accounts a
    LEFT JOIN subscriptions s ON a.id = s.account_id AND s.status = 'active'
    LEFT JOIN plans p ON s.plan_id = p.id
    WHERE $whereClause
    ORDER BY a.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$accounts = $stmt->fetchAll();

// Get plans for filter
$plans = $db->query("SELECT slug, name FROM plans ORDER BY sort_order")->fetchAll();
?>

<header class="platform-header">
    <h1 class="page-title">Konti</h1>
    <div class="header-actions">
        <span class="text-muted"><?= number_format($totalAccounts) ?> konti i alt</span>
    </div>
</header>

<div class="platform-content">
    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
            <?= escape($flash['message']) ?>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card mb-lg">
        <form method="GET" class="flex gap-md items-center" style="flex-wrap: wrap;">
            <div class="search-box" style="flex: 1; min-width: 250px;">
                <span class="search-box-icon">&#128269;</span>
                <input type="text" name="search" class="form-input" placeholder="Søg efter navn, email eller firma..."
                       value="<?= escape($search) ?>" style="padding-left: 40px;">
            </div>

            <select name="plan" class="form-input form-select" style="width: auto;">
                <option value="">Alle planer</option>
                <?php foreach ($plans as $plan): ?>
                    <option value="<?= escape($plan['slug']) ?>" <?= $planFilter === $plan['slug'] ? 'selected' : '' ?>>
                        <?= escape($plan['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="status" class="form-input form-select" style="width: auto;">
                <option value="">Alle status</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Aktive</option>
                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inaktive</option>
            </select>

            <button type="submit" class="btn btn-primary">Filtrer</button>

            <?php if ($search || $planFilter || $statusFilter): ?>
                <a href="<?= BASE_PATH ?>/admin-platform/accounts.php" class="btn btn-secondary">Nulstil</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Accounts Table -->
    <div class="card">
        <?php if (empty($accounts)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">&#128100;</div>
                <p>Ingen konti fundet</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Konto</th>
                            <th>Plan</th>
                            <th>Events</th>
                            <th>Status</th>
                            <th>Sidst aktiv</th>
                            <th>Oprettet</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $account): ?>
                            <tr>
                                <td>
                                    <a href="<?= BASE_PATH ?>/admin-platform/account-detail.php?id=<?= $account['id'] ?>"
                                       style="text-decoration: none; color: inherit;">
                                        <div class="font-medium"><?= escape($account['name']) ?></div>
                                        <div class="text-xs text-muted"><?= escape($account['email']) ?></div>
                                        <?php if ($account['company']): ?>
                                            <div class="text-xs text-muted"><?= escape($account['company']) ?></div>
                                        <?php endif; ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if ($account['plan_name']): ?>
                                        <span class="badge badge-info"><?= escape($account['plan_name']) ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Ingen</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $account['owned_events'] ?></td>
                                <td>
                                    <?php if ($account['is_active']): ?>
                                        <span class="badge badge-success">Aktiv</span>
                                    <?php else: ?>
                                        <span class="badge badge-error">Inaktiv</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-sm text-muted">
                                    <?php if ($account['last_login_at']): ?>
                                        <?= date('d/m/Y', strtotime($account['last_login_at'])) ?>
                                    <?php else: ?>
                                        Aldrig
                                    <?php endif; ?>
                                </td>
                                <td class="text-sm text-muted">
                                    <?= date('d/m/Y', strtotime($account['created_at'])) ?>
                                </td>
                                <td>
                                    <div class="flex gap-sm">
                                        <a href="<?= BASE_PATH ?>/admin-platform/account-detail.php?id=<?= $account['id'] ?>"
                                           class="btn btn-secondary btn-sm">Detaljer</a>

                                        <form method="POST" style="display: inline;">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="account_id" value="<?= $account['id'] ?>">
                                            <?php if ($account['is_active']): ?>
                                                <button type="submit" name="action" value="deactivate"
                                                        class="btn btn-danger btn-sm"
                                                        onclick="return confirm('Deaktiver denne konto?')">
                                                    Deaktiver
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" name="action" value="activate"
                                                        class="btn btn-success btn-sm">
                                                    Aktiver
                                                </button>
                                            <?php endif; ?>
                                        </form>
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

<?php require_once __DIR__ . '/../includes/admin-platform-footer.php'; ?>

<?php
/**
 * Platform Admin - Subscriptions
 */

require_once __DIR__ . '/../includes/admin-platform-header.php';

$db = getDB();

// Filters
$planFilter = $_GET['plan'] ?? '';
$statusFilter = $_GET['status'] ?? '';

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query
$where = ["1=1"];
$params = [];

if ($planFilter) {
    $where[] = "p.slug = ?";
    $params[] = $planFilter;
}

if ($statusFilter) {
    $where[] = "s.status = ?";
    $params[] = $statusFilter;
}

$whereClause = implode(' AND ', $where);

// Count total
$stmt = $db->prepare("
    SELECT COUNT(*)
    FROM subscriptions s
    JOIN plans p ON s.plan_id = p.id
    JOIN accounts a ON s.account_id = a.id
    WHERE $whereClause
");
$stmt->execute($params);
$totalSubscriptions = $stmt->fetchColumn();
$totalPages = ceil($totalSubscriptions / $perPage);

// Get subscriptions
$stmt = $db->prepare("
    SELECT s.*, p.name as plan_name, p.slug as plan_slug, p.price_monthly,
           a.name as account_name, a.email as account_email
    FROM subscriptions s
    JOIN plans p ON s.plan_id = p.id
    JOIN accounts a ON s.account_id = a.id
    WHERE $whereClause
    ORDER BY s.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$subscriptions = $stmt->fetchAll();

// Get plans for filter
$plans = $db->query("SELECT slug, name FROM plans ORDER BY sort_order")->fetchAll();

// Get subscription stats
$stats = [];

$stmt = $db->query("SELECT COUNT(*) FROM subscriptions WHERE status = 'active'");
$stats['active'] = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM subscriptions WHERE status = 'trialing'");
$stats['trialing'] = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM subscriptions WHERE status = 'cancelled'");
$stats['cancelled'] = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM subscriptions WHERE status = 'past_due'");
$stats['past_due'] = $stmt->fetchColumn();

// MRR per plan
$stmt = $db->query("
    SELECT p.name, p.price_monthly, COUNT(s.id) as count,
           SUM(p.price_monthly) as mrr
    FROM subscriptions s
    JOIN plans p ON s.plan_id = p.id
    WHERE s.status = 'active'
    GROUP BY p.id
    ORDER BY p.sort_order
");
$planMRR = $stmt->fetchAll();
?>

<header class="platform-header">
    <h1 class="page-title">Abonnementer</h1>
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
            <div class="stat-label">Aktive</div>
            <div class="stat-value text-success"><?= number_format($stats['active']) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Prøveperiode</div>
            <div class="stat-value"><?= number_format($stats['trialing']) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Forfaldne</div>
            <div class="stat-value" style="color: var(--color-warning);"><?= number_format($stats['past_due']) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Opsagte</div>
            <div class="stat-value text-error"><?= number_format($stats['cancelled']) ?></div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: var(--space-lg);">
        <!-- Subscriptions List -->
        <div class="card">
            <!-- Filters -->
            <form method="GET" class="flex gap-md items-center mb-md" style="flex-wrap: wrap;">
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
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Aktiv</option>
                    <option value="trialing" <?= $statusFilter === 'trialing' ? 'selected' : '' ?>>Prøveperiode</option>
                    <option value="past_due" <?= $statusFilter === 'past_due' ? 'selected' : '' ?>>Forfaldent</option>
                    <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Opsagt</option>
                    <option value="paused" <?= $statusFilter === 'paused' ? 'selected' : '' ?>>Pauseret</option>
                </select>

                <button type="submit" class="btn btn-primary btn-sm">Filtrer</button>

                <?php if ($planFilter || $statusFilter): ?>
                    <a href="<?= BASE_PATH ?>/admin-platform/subscriptions.php" class="btn btn-secondary btn-sm">Nulstil</a>
                <?php endif; ?>
            </form>

            <?php if (empty($subscriptions)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">&#128179;</div>
                    <p>Ingen abonnementer fundet</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Konto</th>
                                <th>Plan</th>
                                <th>Status</th>
                                <th>Periode</th>
                                <th>Oprettet</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subscriptions as $sub): ?>
                                <tr>
                                    <td>
                                        <a href="<?= BASE_PATH ?>/admin-platform/account-detail.php?id=<?= $sub['account_id'] ?>"
                                           style="text-decoration: none; color: inherit;">
                                            <div class="font-medium"><?= escape($sub['account_name']) ?></div>
                                            <div class="text-xs text-muted"><?= escape($sub['account_email']) ?></div>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge badge-info"><?= escape($sub['plan_name']) ?></span>
                                        <div class="text-xs text-muted"><?= number_format($sub['price_monthly'], 0, ',', '.') ?> kr/md</div>
                                    </td>
                                    <td>
                                        <?php
                                        $statusBadge = match($sub['status']) {
                                            'active' => 'badge-success',
                                            'cancelled' => 'badge-error',
                                            'past_due' => 'badge-warning',
                                            'trialing' => 'badge-info',
                                            default => 'badge-info'
                                        };
                                        $statusText = match($sub['status']) {
                                            'active' => 'Aktiv',
                                            'cancelled' => 'Opsagt',
                                            'past_due' => 'Forfaldent',
                                            'trialing' => 'Prøveperiode',
                                            'paused' => 'Pauseret',
                                            default => $sub['status']
                                        };
                                        ?>
                                        <span class="badge <?= $statusBadge ?>"><?= $statusText ?></span>
                                        <?php if ($sub['cancel_at_period_end']): ?>
                                            <div class="text-xs text-error">Udløber ved periodens slut</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-sm">
                                        <?php if ($sub['current_period_end']): ?>
                                            <div>Til: <?= date('d/m/Y', strtotime($sub['current_period_end'])) ?></div>
                                        <?php endif; ?>
                                        <?php if ($sub['trial_ends_at'] && strtotime($sub['trial_ends_at']) > time()): ?>
                                            <div class="text-xs text-muted">Prøve til: <?= date('d/m/Y', strtotime($sub['trial_ends_at'])) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-sm text-muted">
                                        <?= date('d/m/Y', strtotime($sub['created_at'])) ?>
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

        <!-- MRR by Plan -->
        <div class="card">
            <h2 class="card-title">MRR per plan</h2>

            <?php if (empty($planMRR)): ?>
                <div class="empty-state">
                    <p>Ingen aktive abonnementer</p>
                </div>
            <?php else: ?>
                <?php
                $totalMRR = array_sum(array_column($planMRR, 'mrr'));
                ?>
                <div style="margin-bottom: var(--space-lg);">
                    <div class="text-sm text-muted">Total MRR</div>
                    <div style="font-size: var(--text-2xl); font-weight: 700;">
                        <?= number_format($totalMRR, 0, ',', '.') ?> kr
                    </div>
                </div>

                <?php foreach ($planMRR as $plan): ?>
                    <div style="margin-bottom: var(--space-md);">
                        <div class="flex justify-between items-center mb-xs">
                            <span class="font-medium"><?= escape($plan['name']) ?></span>
                            <span class="text-sm"><?= $plan['count'] ?> subs</span>
                        </div>
                        <div style="background: var(--color-bg-subtle); border-radius: 4px; height: 8px; overflow: hidden;">
                            <?php $percentage = $totalMRR > 0 ? ($plan['mrr'] / $totalMRR) * 100 : 0; ?>
                            <div style="background: var(--color-primary); width: <?= $percentage ?>%; height: 100%;"></div>
                        </div>
                        <div class="text-sm text-muted mt-xs">
                            <?= number_format($plan['mrr'], 0, ',', '.') ?> kr/md
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin-platform-footer.php'; ?>

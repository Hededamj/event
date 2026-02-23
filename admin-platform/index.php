<?php
/**
 * Platform Admin Dashboard
 */

require_once __DIR__ . '/../includes/admin-platform-header.php';

$db = getDB();

// Get monthly stats for chart (last 6 months)
$monthlyStats = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthName = date('M', strtotime("-$i months"));

    $newAccounts = 0;
    $revenue = 0;

    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM accounts
            WHERE is_platform_admin = 0
            AND DATE_FORMAT(created_at, '%Y-%m') = ?
        ");
        $stmt->execute([$month]);
        $newAccounts = $stmt->fetchColumn();
    } catch (Exception $e) {
        error_log('Failed to fetch monthly new accounts count for dashboard: ' . $e->getMessage());
    }

    try {
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(amount), 0) FROM payment_history
            WHERE status = 'succeeded'
            AND DATE_FORMAT(created_at, '%Y-%m') = ?
        ");
        $stmt->execute([$month]);
        $revenue = $stmt->fetchColumn();
    } catch (Exception $e) {
        error_log('Failed to fetch monthly revenue sum for dashboard: ' . $e->getMessage());
    }

    $monthlyStats[] = [
        'month' => $monthName,
        'accounts' => $newAccounts,
        'revenue' => $revenue
    ];
}

// Get recent accounts
$recentAccounts = [];
try {
    $stmt = $db->query("
        SELECT a.*, p.name as plan_name, s.status as subscription_status
        FROM accounts a
        LEFT JOIN subscriptions s ON a.id = s.account_id AND s.status = 'active'
        LEFT JOIN plans p ON s.plan_id = p.id
        WHERE a.is_platform_admin = 0
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $recentAccounts = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Failed to fetch recent accounts for dashboard: ' . $e->getMessage());
}

// Get recent payments
$recentPayments = [];
try {
    $stmt = $db->query("
        SELECT ph.*, a.name as account_name, a.email as account_email
        FROM payment_history ph
        JOIN accounts a ON ph.account_id = a.id
        ORDER BY ph.created_at DESC
        LIMIT 5
    ");
    $recentPayments = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Failed to fetch recent payments for dashboard: ' . $e->getMessage());
}

// Plans breakdown
$plansBreakdown = [];
try {
    $stmt = $db->query("
        SELECT p.name, p.price_monthly, COUNT(s.id) as subscriber_count
        FROM plans p
        LEFT JOIN subscriptions s ON p.id = s.plan_id AND s.status = 'active'
        GROUP BY p.id
        ORDER BY p.sort_order
    ");
    $plansBreakdown = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Failed to fetch plans breakdown for dashboard: ' . $e->getMessage());
}
?>

<header class="platform-header">
    <h1 class="page-title">Dashboard</h1>
    <div class="header-actions">
        <span class="text-muted text-sm"><?= date('d. F Y') ?></span>
    </div>
</header>

<div class="platform-content">
    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
            <?= escape($flash['message']) ?>
        </div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div class="stats-grid mb-lg">
        <div class="stat-card">
            <div class="stat-label">Månedlig Omsætning (MRR)</div>
            <div class="stat-value"><?= number_format($platformStats['mrr'], 0, ',', '.') ?> kr</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Aktive Abonnementer</div>
            <div class="stat-value"><?= number_format($platformStats['active_subscriptions']) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Konti i alt</div>
            <div class="stat-value"><?= number_format($platformStats['total_accounts']) ?></div>
            <div class="stat-change positive">+<?= $platformStats['new_accounts_month'] ?> denne måned</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Events i alt</div>
            <div class="stat-value"><?= number_format($platformStats['total_events']) ?></div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-lg);">
        <!-- Recent Accounts -->
        <div class="card">
            <div class="flex justify-between items-center mb-md">
                <h2 class="card-title" style="margin-bottom: 0;">Nyeste konti</h2>
                <a href="<?= BASE_PATH ?>/admin-platform/accounts.php" class="btn btn-secondary btn-sm">Se alle</a>
            </div>

            <?php if (empty($recentAccounts)): ?>
                <div class="empty-state">
                    <p>Ingen konti endnu</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Navn</th>
                                <th>Plan</th>
                                <th>Oprettet</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentAccounts as $account): ?>
                                <tr>
                                    <td>
                                        <div class="font-medium"><?= escape($account['name']) ?></div>
                                        <div class="text-xs text-muted"><?= escape($account['email']) ?></div>
                                    </td>
                                    <td>
                                        <?php if ($account['plan_name']): ?>
                                            <span class="badge badge-info"><?= escape($account['plan_name']) ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Ingen</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted text-sm">
                                        <?= date('d/m/Y', strtotime($account['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Plans Breakdown -->
        <div class="card">
            <h2 class="card-title">Abonnementer per plan</h2>

            <?php if (empty($plansBreakdown)): ?>
                <div class="empty-state">
                    <p>Ingen planer oprettet</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Plan</th>
                                <th>Pris/md</th>
                                <th>Subscribers</th>
                                <th>MRR</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($plansBreakdown as $plan): ?>
                                <tr>
                                    <td class="font-medium"><?= escape($plan['name']) ?></td>
                                    <td><?= number_format($plan['price_monthly'], 0, ',', '.') ?> kr</td>
                                    <td><?= $plan['subscriber_count'] ?></td>
                                    <td class="font-medium">
                                        <?= number_format($plan['price_monthly'] * $plan['subscriber_count'], 0, ',', '.') ?> kr
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Payments -->
    <div class="card mt-md">
        <div class="flex justify-between items-center mb-md">
            <h2 class="card-title" style="margin-bottom: 0;">Seneste betalinger</h2>
            <a href="<?= BASE_PATH ?>/admin-platform/revenue.php" class="btn btn-secondary btn-sm">Se alle</a>
        </div>

        <?php if (empty($recentPayments)): ?>
            <div class="empty-state">
                <p>Ingen betalinger endnu</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Konto</th>
                            <th>Beløb</th>
                            <th>Status</th>
                            <th>Dato</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentPayments as $payment): ?>
                            <tr>
                                <td>
                                    <div class="font-medium"><?= escape($payment['account_name']) ?></div>
                                    <div class="text-xs text-muted"><?= escape($payment['account_email']) ?></div>
                                </td>
                                <td class="font-medium"><?= number_format($payment['amount'], 0, ',', '.') ?> <?= $payment['currency'] ?? 'DKK' ?></td>
                                <td>
                                    <?php
                                    $badgeMap = ['succeeded' => 'badge-success', 'pending' => 'badge-warning', 'failed' => 'badge-error', 'refunded' => 'badge-info'];
                                    $textMap = ['succeeded' => 'Gennemført', 'pending' => 'Afventer', 'failed' => 'Fejlet', 'refunded' => 'Refunderet'];
                                    $statusBadge = isset($badgeMap[$payment['status']]) ? $badgeMap[$payment['status']] : 'badge-info';
                                    $statusText = isset($textMap[$payment['status']]) ? $textMap[$payment['status']] : $payment['status'];
                                    ?>
                                    <span class="badge <?= $statusBadge ?>"><?= $statusText ?></span>
                                </td>
                                <td class="text-muted text-sm">
                                    <?= date('d/m/Y H:i', strtotime($payment['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pending Vendors Alert -->
    <?php if ($platformStats['pending_vendors'] > 0): ?>
        <div class="alert alert-warning mt-md flex justify-between items-center">
            <span>
                <strong><?= $platformStats['pending_vendors'] ?></strong> leverandør<?= $platformStats['pending_vendors'] > 1 ? 'er' : '' ?> afventer godkendelse
            </span>
            <a href="<?= BASE_PATH ?>/admin-platform/vendors.php" class="btn btn-sm btn-secondary">Se leverandører</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/admin-platform-footer.php'; ?>

<?php
/**
 * Platform Admin - Revenue
 */

require_once __DIR__ . '/../includes/admin-platform-header.php';

$db = getDB();

// Get date range filter
$startDate = $_GET['start'] ?? date('Y-m-01');
$endDate = $_GET['end'] ?? date('Y-m-t');

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Get payments in range
$stmt = $db->prepare("
    SELECT ph.*, a.name as account_name, a.email as account_email
    FROM payment_history ph
    JOIN accounts a ON ph.account_id = a.id
    WHERE DATE(ph.created_at) BETWEEN ? AND ?
    ORDER BY ph.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute([$startDate, $endDate]);
$payments = $stmt->fetchAll();

// Count total
$stmt = $db->prepare("
    SELECT COUNT(*) FROM payment_history
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$stmt->execute([$startDate, $endDate]);
$totalPayments = $stmt->fetchColumn();
$totalPages = ceil($totalPayments / $perPage);

// Get revenue stats
$stmt = $db->prepare("
    SELECT
        SUM(CASE WHEN status = 'succeeded' THEN amount ELSE 0 END) as total_revenue,
        SUM(CASE WHEN status = 'refunded' THEN amount ELSE 0 END) as total_refunded,
        COUNT(CASE WHEN status = 'succeeded' THEN 1 END) as successful_payments,
        COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_payments
    FROM payment_history
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$stmt->execute([$startDate, $endDate]);
$stats = $stmt->fetch();

// Monthly revenue trend (last 12 months)
$monthlyRevenue = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthName = date('M Y', strtotime("-$i months"));

    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) as revenue
        FROM payment_history
        WHERE status = 'succeeded'
        AND DATE_FORMAT(created_at, '%Y-%m') = ?
    ");
    $stmt->execute([$month]);
    $revenue = $stmt->fetchColumn();

    $monthlyRevenue[] = [
        'month' => $monthName,
        'revenue' => $revenue
    ];
}

$maxRevenue = max(array_column($monthlyRevenue, 'revenue')) ?: 1;
?>

<header class="platform-header">
    <h1 class="page-title">Omsætning</h1>
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
            <div class="stat-label">MRR</div>
            <div class="stat-value"><?= number_format($platformStats['mrr'], 0, ',', '.') ?> kr</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Omsætning (valgt periode)</div>
            <div class="stat-value text-success"><?= number_format($stats['total_revenue'] ?? 0, 0, ',', '.') ?> kr</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Refunderet</div>
            <div class="stat-value text-error"><?= number_format($stats['total_refunded'] ?? 0, 0, ',', '.') ?> kr</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Betalinger</div>
            <div class="stat-value"><?= number_format($stats['successful_payments'] ?? 0) ?></div>
            <?php if (($stats['failed_payments'] ?? 0) > 0): ?>
                <div class="stat-change negative"><?= $stats['failed_payments'] ?> fejlede</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Revenue Chart -->
    <div class="card mb-lg">
        <h2 class="card-title">Månedlig omsætning (12 måneder)</h2>

        <div style="display: flex; align-items: flex-end; gap: 4px; height: 200px; padding-top: var(--space-md);">
            <?php foreach ($monthlyRevenue as $month): ?>
                <?php $height = $maxRevenue > 0 ? ($month['revenue'] / $maxRevenue) * 100 : 0; ?>
                <div style="flex: 1; display: flex; flex-direction: column; align-items: center;">
                    <div style="width: 100%; background: var(--color-primary); border-radius: 4px 4px 0 0; height: <?= max(2, $height) ?>%;"
                         title="<?= number_format($month['revenue'], 0, ',', '.') ?> kr"></div>
                    <div class="text-xs text-muted" style="margin-top: 4px; white-space: nowrap;">
                        <?= substr($month['month'], 0, 3) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="card">
        <div class="flex justify-between items-center mb-md">
            <h2 class="card-title" style="margin-bottom: 0;">Betalingshistorik</h2>

            <!-- Date Filter -->
            <form method="GET" class="flex gap-sm items-center">
                <input type="date" name="start" class="form-input" value="<?= escape($startDate) ?>" style="width: auto;">
                <span>til</span>
                <input type="date" name="end" class="form-input" value="<?= escape($endDate) ?>" style="width: auto;">
                <button type="submit" class="btn btn-primary btn-sm">Filtrer</button>
            </form>
        </div>

        <?php if (empty($payments)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">&#128176;</div>
                <p>Ingen betalinger i perioden</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Dato</th>
                            <th>Konto</th>
                            <th>Beskrivelse</th>
                            <th>Beløb</th>
                            <th>Status</th>
                            <th>Links</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td class="text-sm">
                                    <?= date('d/m/Y', strtotime($payment['created_at'])) ?>
                                    <div class="text-xs text-muted"><?= date('H:i', strtotime($payment['created_at'])) ?></div>
                                </td>
                                <td>
                                    <a href="<?= BASE_PATH ?>/admin-platform/account-detail.php?id=<?= $payment['account_id'] ?>"
                                       style="text-decoration: none; color: inherit;">
                                        <div class="font-medium"><?= escape($payment['account_name']) ?></div>
                                        <div class="text-xs text-muted"><?= escape($payment['account_email']) ?></div>
                                    </a>
                                </td>
                                <td class="text-sm"><?= escape($payment['description'] ?? '-') ?></td>
                                <td class="font-medium">
                                    <?= number_format($payment['amount'], 2, ',', '.') ?> <?= $payment['currency'] ?>
                                </td>
                                <td>
                                    <?php
                                    $statusBadge = match($payment['status']) {
                                        'succeeded' => 'badge-success',
                                        'pending' => 'badge-warning',
                                        'failed' => 'badge-error',
                                        'refunded' => 'badge-info',
                                        default => 'badge-info'
                                    };
                                    $statusText = match($payment['status']) {
                                        'succeeded' => 'Gennemført',
                                        'pending' => 'Afventer',
                                        'failed' => 'Fejlet',
                                        'refunded' => 'Refunderet',
                                        default => $payment['status']
                                    };
                                    ?>
                                    <span class="badge <?= $statusBadge ?>"><?= $statusText ?></span>
                                </td>
                                <td class="text-sm">
                                    <?php if ($payment['invoice_url']): ?>
                                        <a href="<?= escape($payment['invoice_url']) ?>" target="_blank">Faktura</a>
                                    <?php endif; ?>
                                    <?php if ($payment['receipt_url']): ?>
                                        <a href="<?= escape($payment['receipt_url']) ?>" target="_blank">Kvittering</a>
                                    <?php endif; ?>
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

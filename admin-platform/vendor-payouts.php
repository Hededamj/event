<?php
/**
 * Platform Admin - Vendor Payouts Overview
 * Shows financial overview of all vendor bookings, commissions, and payouts.
 * Includes CSV export functionality.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin-platform-header.php';
require_once __DIR__ . '/../subcontractor/includes/commission-service.php';

$db = getDB();

// Date range filter
$fromDate = $_GET['from'] ?? '';
$toDate = $_GET['to'] ?? '';

// Validate date formats
if ($fromDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
    $fromDate = '';
}
if ($toDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
    $toDate = '';
}

// Get platform revenue stats
$revenue = getPlatformRevenue($fromDate ?: null, $toDate ?: null);

// CSV Export
if (($_GET['action'] ?? '') === 'export') {
    $exportSql = "
        SELECT
            b.id AS booking_id,
            b.event_date AS dato,
            a.name AS arrangor,
            a.email AS arrangor_email,
            v.company_name AS leverandor,
            v.email AS leverandor_email,
            COALESCE(vs.title, '-') AS ydelse,
            b.quoted_price AS tilbudt_pris,
            b.depositum_amount AS depositum,
            b.commission_amount AS kommission,
            b.vendor_payout AS leverandor_udbetaling,
            b.status
        FROM bookings b
        JOIN vendors v ON b.vendor_id = v.id
        JOIN accounts a ON b.account_id = a.id
        LEFT JOIN vendor_services vs ON b.vendor_service_id = vs.id
        WHERE b.status IN ('completed', 'reviewed', 'deposited', 'confirmed')
    ";
    $exportParams = [];

    if ($fromDate) {
        $exportSql .= " AND b.paid_at >= ?";
        $exportParams[] = $fromDate;
    }
    if ($toDate) {
        $exportSql .= " AND b.paid_at <= ?";
        $exportParams[] = $toDate;
    }

    $exportSql .= " ORDER BY b.event_date DESC";

    $stmt = $db->prepare($exportSql);
    $stmt->execute($exportParams);
    $exportRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Output CSV
    ob_end_clean(); // Clear any buffered output from header
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="vendor-payouts-' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    // BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Header row
    fputcsv($output, ['Booking ID', 'Dato', 'Arrangor', 'Arrangor email', 'Leverandor', 'Leverandor email', 'Ydelse', 'Tilbudt pris', 'Depositum', 'Kommission', 'Leverandor udbetaling', 'Status'], ';');

    foreach ($exportRows as $row) {
        fputcsv($output, $row, ';');
    }

    fclose($output);
    exit;
}

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Build bookings query with filters
$where = ["b.status IN ('completed', 'reviewed', 'deposited', 'confirmed')"];
$params = [];

if ($fromDate) {
    $where[] = "b.paid_at >= ?";
    $params[] = $fromDate;
}
if ($toDate) {
    $where[] = "b.paid_at <= ?";
    $params[] = $toDate;
}

$whereClause = implode(' AND ', $where);

// Count total
$stmt = $db->prepare("SELECT COUNT(*) FROM bookings b WHERE $whereClause");
$stmt->execute($params);
$totalBookings = $stmt->fetchColumn();
$totalPages = ceil($totalBookings / $perPage);

// Get bookings
$stmt = $db->prepare("
    SELECT b.*, v.company_name AS vendor_name, v.email AS vendor_email,
           a.name AS organizer_name, a.email AS organizer_email,
           e.name AS event_name,
           COALESCE(vs.title, '-') AS service_title
    FROM bookings b
    JOIN vendors v ON b.vendor_id = v.id
    JOIN accounts a ON b.account_id = a.id
    JOIN events e ON b.event_id = e.id
    LEFT JOIN vendor_services vs ON b.vendor_service_id = vs.id
    WHERE $whereClause
    ORDER BY b.event_date DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Summary totals for current page data (we use the revenue stats for overall totals)
$summaryStmt = $db->prepare("
    SELECT
        COALESCE(SUM(b.quoted_price), 0) AS total_quoted,
        COALESCE(SUM(b.depositum_amount), 0) AS total_depositum,
        COALESCE(SUM(b.commission_amount), 0) AS total_commission,
        COALESCE(SUM(b.vendor_payout), 0) AS total_vendor_payout
    FROM bookings b
    WHERE $whereClause
");
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch();

// Booking status maps
$bookingBadgeMap = [
    'deposited' => 'badge-warning', 'confirmed' => 'badge-success',
    'completed' => 'badge-success', 'reviewed' => 'badge-success'
];
$bookingTextMap = [
    'deposited' => 'Depositum', 'confirmed' => 'Bekraeftet',
    'completed' => 'Afsluttet', 'reviewed' => 'Anmeldt'
];
?>

<header class="platform-header">
    <h1 class="page-title">Leverandor-udbetalinger</h1>
    <div class="header-actions">
        <a href="?action=export<?= $fromDate ? '&from=' . urlencode($fromDate) : '' ?><?= $toDate ? '&to=' . urlencode($toDate) : '' ?>"
           class="btn btn-secondary">
            &#128230; Eksporter CSV
        </a>
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
            <div class="stat-label">Total omsaetning (depositum)</div>
            <div class="stat-value"><?= number_format($revenue['total_depositum'], 2, ',', '.') ?> kr.</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total kommission</div>
            <div class="stat-value" style="color: var(--color-primary);">
                <?= number_format($revenue['total_commission'], 2, ',', '.') ?> kr.
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total udbetalt til leverandorer</div>
            <div class="stat-value text-success"><?= number_format($revenue['total_vendor_payouts'], 2, ',', '.') ?> kr.</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Antal bookinger</div>
            <div class="stat-value"><?= number_format($revenue['total_bookings']) ?></div>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="card mb-lg">
        <form method="GET" class="flex gap-md items-center" style="flex-wrap: wrap;">
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Fra dato</label>
                <input type="date" name="from" class="form-input" value="<?= escape($fromDate) ?>" style="width: auto;">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Til dato</label>
                <input type="date" name="to" class="form-input" value="<?= escape($toDate) ?>" style="width: auto;">
            </div>
            <div style="align-self: flex-end;">
                <button type="submit" class="btn btn-primary">Filtrer</button>
                <?php if ($fromDate || $toDate): ?>
                    <a href="<?= BASE_PATH ?>/admin-platform/vendor-payouts.php" class="btn btn-secondary">Nulstil</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Bookings Table -->
    <div class="card">
        <?php if (empty($bookings)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">&#128176;</div>
                <p>Ingen betalte bookinger fundet</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Dato</th>
                            <th>Arrangor</th>
                            <th>Leverandor</th>
                            <th>Ydelse</th>
                            <th>Tilbudt pris</th>
                            <th>Depositum</th>
                            <th>Kommission</th>
                            <th>Leverandor-udbetaling</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td class="text-sm"><?= date('d/m/Y', strtotime($booking['event_date'])) ?></td>
                                <td class="text-sm">
                                    <div class="font-medium"><?= escape($booking['organizer_name']) ?></div>
                                    <div class="text-xs text-muted"><?= escape($booking['organizer_email']) ?></div>
                                </td>
                                <td class="text-sm">
                                    <a href="<?= BASE_PATH ?>/admin-platform/vendor-detail.php?id=<?= $booking['vendor_id'] ?>"
                                       style="color: var(--color-primary); text-decoration: none;">
                                        <?= escape($booking['vendor_name']) ?>
                                    </a>
                                </td>
                                <td class="text-sm"><?= escape($booking['service_title']) ?></td>
                                <td class="text-sm">
                                    <?= $booking['quoted_price'] ? number_format($booking['quoted_price'], 2, ',', '.') . ' kr.' : '-' ?>
                                </td>
                                <td class="text-sm font-medium">
                                    <?= $booking['depositum_amount'] ? number_format($booking['depositum_amount'], 2, ',', '.') . ' kr.' : '-' ?>
                                </td>
                                <td class="text-sm" style="color: var(--color-primary);">
                                    <?= $booking['commission_amount'] ? number_format($booking['commission_amount'], 2, ',', '.') . ' kr.' : '-' ?>
                                </td>
                                <td class="text-sm text-success font-medium">
                                    <?= $booking['vendor_payout'] ? number_format($booking['vendor_payout'], 2, ',', '.') . ' kr.' : '-' ?>
                                </td>
                                <td>
                                    <span class="badge <?= $bookingBadgeMap[$booking['status']] ?? 'badge-info' ?>">
                                        <?= $bookingTextMap[$booking['status']] ?? $booking['status'] ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: var(--color-bg-subtle); font-weight: 600;">
                            <td colspan="4" class="text-sm">Total (alle filterede)</td>
                            <td class="text-sm"><?= number_format($summary['total_quoted'], 2, ',', '.') ?> kr.</td>
                            <td class="text-sm"><?= number_format($summary['total_depositum'], 2, ',', '.') ?> kr.</td>
                            <td class="text-sm" style="color: var(--color-primary);"><?= number_format($summary['total_commission'], 2, ',', '.') ?> kr.</td>
                            <td class="text-sm text-success"><?= number_format($summary['total_vendor_payout'], 2, ',', '.') ?> kr.</td>
                            <td></td>
                        </tr>
                    </tfoot>
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

<?php require_once __DIR__ . '/../includes/admin-platform-footer.php'; ?>

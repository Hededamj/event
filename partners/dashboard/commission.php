<?php
/**
 * Partner Dashboard - Commission & Financials
 */

require_once __DIR__ . '/../../includes/partner-auth.php';
require_once __DIR__ . '/../../includes/partner-commission.php';
requirePartner();

$db = getDB();
$partner = getCurrentPartner();
$partnerId = getCurrentPartnerId();

// Ensure commission tables exist
ensureCommissionTables($db);

// Get commission summary
$commissionStats = getPartnerCommissionSummary($db, $partnerId);

// Get recent completed bookings
$recentBookings = getPartnerBookings($db, $partnerId, ['status' => 'completed', 'limit' => 10]);

// Get payouts
$payouts = getPartnerPayouts($db, $partnerId, 10);

// Get commission settings for this partner
$stmt = $db->prepare("SELECT * FROM partner_commission_settings WHERE partner_id = ?");
$stmt->execute([$partnerId]);
$commissionSettings = $stmt->fetch() ?: [];

// Calculate monthly breakdown (last 6 months)
$monthlyStats = [];
for ($i = 5; $i >= 0; $i--) {
    $monthStart = date('Y-m-01', strtotime("-$i months"));
    $monthEnd = date('Y-m-t', strtotime("-$i months"));
    $monthName = date('M Y', strtotime("-$i months"));

    $stmt = $db->prepare("
        SELECT
            COUNT(*) as bookings,
            COALESCE(SUM(total_amount), 0) as revenue,
            COALESCE(SUM(commission_amount), 0) as commission
        FROM partner_bookings
        WHERE partner_id = ?
          AND status = 'completed'
          AND completed_at >= ?
          AND completed_at <= ?
    ");
    $stmt->execute([$partnerId, $monthStart, $monthEnd . ' 23:59:59']);
    $monthData = $stmt->fetch();

    $monthlyStats[] = [
        'month' => $monthName,
        'bookings' => (int)$monthData['bookings'],
        'revenue' => (float)$monthData['revenue'],
        'commission' => (float)$monthData['commission']
    ];
}

// Get flash
$flash = getFlash();

// Platform name
try {
    $stmt = $db->query("SELECT setting_value FROM platform_settings WHERE setting_key = 'platform_name'");
    $platformName = $stmt->fetchColumn() ?: 'EventPlatform';
} catch (Exception $e) {
    $platformName = 'EventPlatform';
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Økonomi - Partner Dashboard</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --color-bg: #f8fafc;
            --color-bg-subtle: #f1f5f9;
            --color-surface: #ffffff;
            --color-primary: #7c3aed;
            --color-primary-deep: #6d28d9;
            --color-primary-soft: #ede9fe;
            --color-text: #1e293b;
            --color-text-soft: #475569;
            --color-text-muted: #94a3b8;
            --color-border: #e2e8f0;
            --color-success: #22c55e;
            --color-warning: #f59e0b;
            --color-error: #ef4444;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
            --radius-md: 8px;
            --radius-lg: 12px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: var(--color-bg); color: var(--color-text); line-height: 1.6; }

        .dashboard-layout { display: flex; min-height: 100vh; }

        .sidebar {
            width: 250px; background: var(--color-surface); border-right: 1px solid var(--color-border);
            position: fixed; top: 0; left: 0; bottom: 0; display: flex; flex-direction: column;
        }
        .sidebar-brand { padding: 1.25rem; border-bottom: 1px solid var(--color-border); }
        .sidebar-brand-name { font-size: 1rem; font-weight: 600; color: var(--color-primary); }
        .sidebar-brand-label { font-size: 0.75rem; color: var(--color-text-muted); }
        .sidebar-nav { flex: 1; padding: 1rem; }
        .nav-menu { list-style: none; }
        .nav-link {
            display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem;
            color: var(--color-text-soft); text-decoration: none; border-radius: var(--radius-md);
            font-size: 0.9rem; margin-bottom: 0.25rem;
        }
        .nav-link:hover { background: var(--color-bg-subtle); }
        .nav-link.active { background: var(--color-primary-soft); color: var(--color-primary-deep); font-weight: 500; }
        .sidebar-footer { padding: 1rem; border-top: 1px solid var(--color-border); }

        .main { flex: 1; margin-left: 250px; padding: 2rem; }

        .page-header { margin-bottom: 2rem; }
        .page-title { font-size: 1.5rem; font-weight: 600; }

        .card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem; margin-bottom: 1.5rem; }
        .card-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem; }
        .stat-label { font-size: 0.875rem; color: var(--color-text-muted); margin-bottom: 0.25rem; }
        .stat-value { font-size: 1.75rem; font-weight: 700; }
        .stat-note { font-size: 0.75rem; color: var(--color-text-muted); margin-top: 0.25rem; }

        .badge { display: inline-block; padding: 0.25rem 0.5rem; border-radius: 10px; font-size: 0.75rem; font-weight: 500; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-error { background: #fee2e2; color: #991b1b; }
        .badge-info { background: var(--color-primary-soft); color: var(--color-primary); }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid var(--color-border); }
        th { font-size: 0.75rem; font-weight: 600; color: var(--color-text-muted); text-transform: uppercase; background: var(--color-bg-subtle); }
        td { font-size: 0.875rem; }

        .empty-state { text-align: center; padding: 2rem; color: var(--color-text-muted); }

        .commission-highlight {
            background: linear-gradient(135deg, var(--color-primary-soft) 0%, #fff 100%);
            border: 2px solid var(--color-primary);
        }

        .chart-container { height: 200px; display: flex; align-items: flex-end; gap: 0.5rem; padding: 1rem 0; }
        .chart-bar-wrapper { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 0.5rem; }
        .chart-bar { width: 100%; background: var(--color-primary-soft); border-radius: var(--radius-md) var(--radius-md) 0 0; min-height: 10px; transition: all 0.3s; }
        .chart-bar:hover { background: var(--color-primary); }
        .chart-label { font-size: 0.7rem; color: var(--color-text-muted); text-align: center; }
        .chart-value { font-size: 0.75rem; font-weight: 600; color: var(--color-text); }

        .info-box { background: var(--color-bg-subtle); padding: 1rem; border-radius: var(--radius-md); margin-top: 1rem; }
        .info-box-title { font-weight: 600; margin-bottom: 0.5rem; }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main { margin-left: 0; }
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="sidebar-brand-name"><?= escape($partner['company_name']) ?></div>
                <div class="sidebar-brand-label">Partner Dashboard</div>
            </div>
            <nav class="sidebar-nav">
                <ul class="nav-menu">
                    <li><a href="<?= BASE_PATH ?>/partners/dashboard/" class="nav-link">&#128200; Dashboard</a></li>
                    <li><a href="<?= BASE_PATH ?>/partners/dashboard/profile.php" class="nav-link">&#128736; Profil</a></li>
                    <li><a href="<?= BASE_PATH ?>/partners/dashboard/inquiries.php" class="nav-link">&#128172; Forespørgsler</a></li>
                    <li><a href="<?= BASE_PATH ?>/partners/dashboard/bookings.php" class="nav-link">&#128197; Bookinger</a></li>
                    <li><a href="<?= BASE_PATH ?>/partners/dashboard/commission.php" class="nav-link active">&#128176; Økonomi</a></li>
                    <li><a href="<?= BASE_PATH ?>/partners/dashboard/verification.php" class="nav-link">&#9989; Verifikation</a></li>
                    <li><a href="<?= BASE_PATH ?>/partners/dashboard/gallery.php" class="nav-link">&#128247; Galleri</a></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <a href="<?= BASE_PATH ?>/partners/profile.php?id=<?= $partnerId ?>" class="nav-link" target="_blank">&#128065; Se offentlig profil</a>
                <a href="<?= BASE_PATH ?>/partners/dashboard/logout.php" class="nav-link">&#128682; Log ud</a>
            </div>
        </aside>

        <!-- Main -->
        <main class="main">
            <div class="page-header">
                <h1 class="page-title">Økonomi & Kommission</h1>
            </div>

            <!-- Summary Stats -->
            <div class="stats-grid">
                <div class="stat-card commission-highlight">
                    <div class="stat-label">Kommission til betaling</div>
                    <div class="stat-value" style="color: var(--color-primary-deep);">
                        <?= formatDKK($commissionStats['pending_commission']) ?>
                    </div>
                    <div class="stat-note">Afventer fakturering</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total omsætning</div>
                    <div class="stat-value"><?= formatDKK($commissionStats['total_revenue']) ?></div>
                    <div class="stat-note"><?= $commissionStats['completed_bookings'] ?> gennemførte bookinger</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Betalt kommission</div>
                    <div class="stat-value"><?= formatDKK($commissionStats['paid_commission']) ?></div>
                    <div class="stat-note">I alt afregnet</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Din kommissionssats</div>
                    <div class="stat-value"><?= $commissionStats['commission_rate'] ?>%</div>
                    <div class="stat-note">Af bookingbeløb</div>
                </div>
            </div>

            <!-- Monthly Chart -->
            <div class="card">
                <h2 class="card-title">Månedlig omsætning (6 måneder)</h2>
                <?php
                $maxRevenue = max(array_column($monthlyStats, 'revenue'));
                $maxRevenue = $maxRevenue > 0 ? $maxRevenue : 1;
                ?>
                <div class="chart-container">
                    <?php foreach ($monthlyStats as $month): ?>
                        <div class="chart-bar-wrapper">
                            <div class="chart-value"><?= number_format($month['revenue'], 0, ',', '.') ?></div>
                            <div class="chart-bar" style="height: <?= ($month['revenue'] / $maxRevenue) * 150 ?>px;"></div>
                            <div class="chart-label"><?= $month['month'] ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="info-box">
                    <div class="info-box-title">Sådan fungerer kommission</div>
                    <p style="font-size: 0.875rem; color: var(--color-text-soft);">
                        Når du registrerer en booking via platformen, beregnes der <?= $commissionStats['commission_rate'] ?>% kommission.
                        Kommissionen faktureres månedligt for alle gennemførte bookinger.
                        Du modtager en faktura omkring den 1. i hver måned for foregående måneds bookinger.
                    </p>
                </div>
            </div>

            <!-- Payout History -->
            <div class="card">
                <h2 class="card-title">Betalingshistorik</h2>

                <?php if (empty($payouts)): ?>
                    <div class="empty-state">
                        <p>Ingen fakturaer endnu</p>
                        <p style="font-size: 0.8rem;">Fakturaer genereres automatisk månedligt.</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Periode</th>
                                <th>Bookinger</th>
                                <th>Beløb</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payouts as $payout): ?>
                                <tr>
                                    <td><code><?= escape($payout['payout_reference']) ?></code></td>
                                    <td>
                                        <?= date('d/m', strtotime($payout['period_start'])) ?> -
                                        <?= date('d/m/Y', strtotime($payout['period_end'])) ?>
                                    </td>
                                    <td><?= $payout['total_bookings'] ?></td>
                                    <td style="font-weight: 600;"><?= formatDKK($payout['final_amount']) ?></td>
                                    <td>
                                        <?php
                                        $statusMap = [
                                            'draft' => ['Kladde', 'badge-info'],
                                            'sent' => ['Sendt', 'badge-warning'],
                                            'paid' => ['Betalt', 'badge-success'],
                                            'overdue' => ['Forfalden', 'badge-error'],
                                            'cancelled' => ['Annulleret', 'badge-info']
                                        ];
                                        $status = $statusMap[$payout['status']] ?? [$payout['status'], 'badge-info'];
                                        ?>
                                        <span class="badge <?= $status[1] ?>"><?= $status[0] ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Recent Completed Bookings -->
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h2 class="card-title" style="margin-bottom: 0;">Seneste gennemførte bookinger</h2>
                    <a href="<?= BASE_PATH ?>/partners/dashboard/bookings.php?status=completed" style="font-size: 0.875rem; color: var(--color-primary);">Se alle →</a>
                </div>

                <?php if (empty($recentBookings)): ?>
                    <div class="empty-state">
                        <p>Ingen gennemførte bookinger endnu</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Kunde</th>
                                <th>Dato</th>
                                <th>Beløb</th>
                                <th>Kommission</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentBookings as $booking): ?>
                                <tr>
                                    <td><code><?= escape($booking['booking_reference']) ?></code></td>
                                    <td><?= escape($booking['customer_name']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($booking['event_date'])) ?></td>
                                    <td><?= formatDKK($booking['total_amount']) ?></td>
                                    <td>
                                        <?= formatDKK($booking['commission_amount']) ?>
                                        <?php if ($booking['commission_status'] === 'paid'): ?>
                                            <span class="badge badge-success">Betalt</span>
                                        <?php elseif ($booking['commission_status'] === 'invoiced'): ?>
                                            <span class="badge badge-warning">Faktureret</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>

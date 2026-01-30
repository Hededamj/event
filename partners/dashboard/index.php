<?php
/**
 * Partner Dashboard - Home
 */

require_once __DIR__ . '/../../includes/partner-auth.php';
requirePartner();

$db = getDB();
$partner = getCurrentPartner();
$partnerId = getCurrentPartnerId();

// Get stats
$stats = [];

// Inquiry count
$stmt = $db->prepare("SELECT COUNT(*) FROM partner_inquiries WHERE partner_id = ?");
$stmt->execute([$partnerId]);
$stats['total_inquiries'] = $stmt->fetchColumn();

// New inquiries
$stmt = $db->prepare("SELECT COUNT(*) FROM partner_inquiries WHERE partner_id = ? AND status = 'new'");
$stmt->execute([$partnerId]);
$stats['new_inquiries'] = $stmt->fetchColumn();

// View count
$stats['views'] = $partner['view_count'];

// Recent inquiries
$stmt = $db->prepare("
    SELECT * FROM partner_inquiries
    WHERE partner_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute([$partnerId]);
$recentInquiries = $stmt->fetchAll();

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
    <title>Partner Dashboard - <?= escape($platformName) ?></title>

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

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: var(--color-bg);
            color: var(--color-text);
            line-height: 1.6;
        }

        .dashboard-layout {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: var(--color-surface);
            border-right: 1px solid var(--color-border);
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            display: flex;
            flex-direction: column;
        }

        .sidebar-brand {
            padding: 1.25rem;
            border-bottom: 1px solid var(--color-border);
        }

        .sidebar-brand-name {
            font-size: 1rem;
            font-weight: 600;
            color: var(--color-primary);
        }

        .sidebar-brand-label {
            font-size: 0.75rem;
            color: var(--color-text-muted);
        }

        .sidebar-nav {
            flex: 1;
            padding: 1rem;
        }

        .nav-menu {
            list-style: none;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: var(--color-text-soft);
            text-decoration: none;
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .nav-link:hover {
            background: var(--color-bg-subtle);
        }

        .nav-link.active {
            background: var(--color-primary-soft);
            color: var(--color-primary-deep);
            font-weight: 500;
        }

        .nav-badge {
            margin-left: auto;
            background: var(--color-error);
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 10px;
        }

        .sidebar-footer {
            padding: 1rem;
            border-top: 1px solid var(--color-border);
        }

        /* Main */
        .main {
            flex: 1;
            margin-left: 250px;
            padding: 2rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
        }

        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--color-text-muted);
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
        }

        /* Cards */
        .card {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--color-border);
        }

        th {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--color-text-muted);
            text-transform: uppercase;
            background: var(--color-bg-subtle);
        }

        td {
            font-size: 0.875rem;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--color-primary);
            color: white;
        }

        .btn-secondary {
            background: var(--color-bg-subtle);
            color: var(--color-text);
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.8rem;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-error { background: #fee2e2; color: #991b1b; }
        .badge-info { background: var(--color-primary-soft); color: var(--color-primary); }

        /* Alert */
        .alert {
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
        }

        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        .alert-warning { background: #fef3c7; color: #92400e; }

        /* Status banner */
        .status-banner {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .status-banner.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-banner.approved {
            background: #dcfce7;
            color: #166534;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--color-text-muted);
        }

        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }
            .main {
                margin-left: 0;
            }
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
                    <li>
                        <a href="<?= BASE_PATH ?>/partners/dashboard/" class="nav-link active">
                            &#128200; Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="<?= BASE_PATH ?>/partners/dashboard/profile.php" class="nav-link">
                            &#128736; Profil
                        </a>
                    </li>
                    <li>
                        <a href="<?= BASE_PATH ?>/partners/dashboard/inquiries.php" class="nav-link">
                            &#128172; Forespørgsler
                            <?php if ($stats['new_inquiries'] > 0): ?>
                                <span class="nav-badge"><?= $stats['new_inquiries'] ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?= BASE_PATH ?>/partners/dashboard/gallery.php" class="nav-link">
                            &#128247; Galleri
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="sidebar-footer">
                <a href="<?= BASE_PATH ?>/partners/profile.php?id=<?= $partnerId ?>" class="nav-link" target="_blank">
                    &#128065; Se offentlig profil
                </a>
                <a href="<?= BASE_PATH ?>/partners/dashboard/logout.php" class="nav-link">
                    &#128682; Log ud
                </a>
            </div>
        </aside>

        <!-- Main -->
        <main class="main">
            <div class="page-header">
                <h1 class="page-title">Dashboard</h1>
            </div>

            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                    <?= escape($flash['message']) ?>
                </div>
            <?php endif; ?>

            <!-- Status Banner -->
            <?php if ($partner['status'] === 'pending'): ?>
                <div class="status-banner pending">
                    <span><strong>Afventer godkendelse</strong> - Din profil er under gennemgang og vil snart blive synlig på markedspladsen.</span>
                </div>
            <?php elseif ($partner['status'] === 'rejected'): ?>
                <div class="status-banner" style="background: #fee2e2; color: #991b1b;">
                    <span><strong>Profil afvist</strong> - <?= escape($partner['rejection_reason'] ?? 'Kontakt support for mere information.') ?></span>
                </div>
            <?php elseif ($partner['status'] === 'approved'): ?>
                <div class="status-banner approved">
                    <span><strong>Profil godkendt</strong> - Din profil er synlig på markedspladsen.</span>
                    <a href="<?= BASE_PATH ?>/partners/profile.php?id=<?= $partnerId ?>" class="btn btn-sm btn-secondary" target="_blank">
                        Se profil
                    </a>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Profilvisninger</div>
                    <div class="stat-value"><?= number_format($stats['views']) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Forespørgsler i alt</div>
                    <div class="stat-value"><?= number_format($stats['total_inquiries']) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Nye forespørgsler</div>
                    <div class="stat-value" style="color: <?= $stats['new_inquiries'] > 0 ? 'var(--color-error)' : 'inherit' ?>;">
                        <?= number_format($stats['new_inquiries']) ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Status</div>
                    <div>
                        <?php
                        $statusBadge = match($partner['status']) {
                            'approved' => 'badge-success',
                            'pending' => 'badge-warning',
                            'rejected' => 'badge-error',
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
                        <span class="badge <?= $statusBadge ?>" style="font-size: 1rem; padding: 0.5rem 1rem;">
                            <?= $statusText ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Recent Inquiries -->
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h2 class="card-title" style="margin-bottom: 0;">Seneste forespørgsler</h2>
                    <a href="<?= BASE_PATH ?>/partners/dashboard/inquiries.php" class="btn btn-secondary btn-sm">Se alle</a>
                </div>

                <?php if (empty($recentInquiries)): ?>
                    <div class="empty-state">
                        <p>Ingen forespørgsler endnu</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Fra</th>
                                <th>Dato</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentInquiries as $inquiry): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 500;"><?= escape($inquiry['name']) ?></div>
                                        <div style="font-size: 0.8rem; color: var(--color-text-muted);"><?= escape($inquiry['email']) ?></div>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($inquiry['created_at'])) ?></td>
                                    <td>
                                        <?php
                                        $inquiryBadge = match($inquiry['status']) {
                                            'new' => 'badge-error',
                                            'read' => 'badge-warning',
                                            'replied' => 'badge-success',
                                            default => 'badge-info'
                                        };
                                        $inquiryText = match($inquiry['status']) {
                                            'new' => 'Ny',
                                            'read' => 'Læst',
                                            'replied' => 'Besvaret',
                                            'closed' => 'Lukket',
                                            default => $inquiry['status']
                                        };
                                        ?>
                                        <span class="badge <?= $inquiryBadge ?>"><?= $inquiryText ?></span>
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

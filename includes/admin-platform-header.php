<?php
/**
 * Platform Admin Header
 * Included at the top of all platform admin pages
 */

ob_start();

require_once __DIR__ . '/admin-platform-auth.php';

// Require platform admin login
requirePlatformAdmin();

$db = getDB();
$adminId = getCurrentPlatformAdminId();

// Get admin details
$stmt = $db->prepare("SELECT * FROM accounts WHERE id = ?");
$stmt->execute([$adminId]);
$currentAdmin = $stmt->fetch();

// Get quick stats
$platformStats = getPlatformStats();

// Get flash message if any
$flash = getFlash();

// Current page for active nav
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Get platform name
$platformName = getPlatformSetting('platform_name', 'EventPlatform');
?>
<!DOCTYPE html>
<html lang="da" data-area="admin">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Admin - <?= escape($platformName) ?></title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Design System -->
    <link rel="stylesheet" href="/assets/css/design-system.css">

    <style>
        /* Admin-specific overrides */
        .search-box { position: relative; }
        .search-box input { padding-left: 40px; }
        .search-box-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        /* Legacy class support for admin pages */
        .platform-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: var(--space-md) 0;
            margin-bottom: var(--space-lg);
            border-bottom: 1px solid var(--border-light);
        }
        .platform-content {
            /* no special styling needed, ds-content handles padding */
        }
        .page-title {
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 600;
            color: var(--text);
        }
        .header-actions {
            display: flex;
            align-items: center;
            gap: var(--space-md);
        }

        /* Stat cards */
        .stat-card {
            background: var(--surface-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
        }
        .stat-label {
            font-size: 12px;
            font-weight: 500;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: var(--space-xs);
        }
        .stat-value {
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 600;
            color: var(--text);
            line-height: 1.2;
        }
        .stat-change {
            font-size: 12px;
            font-weight: 500;
            margin-top: var(--space-xs);
        }
        .stat-change.positive { color: var(--success); }
        .stat-change.negative { color: var(--error); }

        /* Tables */
        .table-container { overflow-x: auto; }

        /* Form select arrow */
        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg width='12' height='8' viewBox='0 0 12 8' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1.5L6 6.5L11 1.5' stroke='%236B6560' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 40px;
        }

        /* Badge info (uses accent for admin) */
        .badge-info {
            background: var(--accent-light);
            color: var(--accent-dark);
        }

        /* Sidebar nav SVG icons */
        .ds-nav-link svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }

        /* Button success */
        .btn-success {
            background: var(--success);
            color: var(--text-on-dark);
            border-color: var(--success);
        }
        .btn-success:hover:not(:disabled) {
            background: #2E7A2E;
            border-color: #2E7A2E;
        }

        /* Pagination centering */
        .pagination {
            justify-content: center;
            margin-top: var(--space-lg);
        }

        /* Text utilities */
        .text-muted { color: var(--text-secondary); }
        .text-success { color: var(--success); }
        .text-error { color: var(--error); }
        .text-sm { font-size: 13px; }
        .text-xs { font-size: 11px; }
        .font-medium { font-weight: 500; }
        .font-bold { font-weight: 700; }

        /* Empty state icon */
        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: var(--space-md);
        }

        /* Card title */
        .card-title {
            font-family: var(--font-body);
            font-size: 16px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: var(--space-md);
        }
    </style>
</head>
<body>
    <!-- Mobile sidebar overlay -->
    <div class="ds-sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Mobile hamburger toggle -->
    <button class="ds-mobile-toggle" id="menuToggle" aria-label="Toggle navigation">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
    </button>

    <!-- Sidebar -->
    <aside class="ds-sidebar" id="adminSidebar">
        <div class="ds-sidebar-header">
            <a href="<?= BASE_PATH ?>/admin-platform/index.php" class="ds-sidebar-logo">
                <span class="ds-sidebar-logo-text"><?= escape($platformName) ?></span>
            </a>
            <div class="ds-sidebar-subtitle">Platform Admin</div>
        </div>

        <nav class="ds-sidebar-nav">
            <div class="ds-nav-section-title">Oversigt</div>
            <a href="<?= BASE_PATH ?>/admin-platform/index.php" class="ds-nav-link <?= $currentPage === 'index' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
                Dashboard
            </a>

            <div class="ds-nav-section-title">Brugere</div>
            <a href="<?= BASE_PATH ?>/admin-platform/accounts.php" class="ds-nav-link <?= $currentPage === 'accounts' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                Konti
            </a>
            <a href="<?= BASE_PATH ?>/admin-platform/subscriptions.php" class="ds-nav-link <?= $currentPage === 'subscriptions' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                    <line x1="1" y1="10" x2="23" y2="10"></line>
                </svg>
                Abonnementer
            </a>

            <div class="ds-nav-section-title">Finans</div>
            <a href="<?= BASE_PATH ?>/admin-platform/revenue.php" class="ds-nav-link <?= $currentPage === 'revenue' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="1" x2="12" y2="23"></line>
                    <path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"></path>
                </svg>
                Omsaetning
            </a>

            <div class="ds-nav-section-title">Markedsplads</div>
            <a href="<?= BASE_PATH ?>/admin-platform/vendors.php" class="ds-nav-link <?= in_array($currentPage, ['vendors', 'vendor-detail']) ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                Leverandorer
                <?php if (($platformStats['pending_vendors'] ?? 0) > 0): ?>
                    <span class="ds-nav-badge"><?= $platformStats['pending_vendors'] ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= BASE_PATH ?>/admin-platform/vendor-payouts.php" class="ds-nav-link <?= $currentPage === 'vendor-payouts' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="1" x2="12" y2="23"></line>
                    <path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"></path>
                </svg>
                Udbetalinger
            </a>

            <div class="ds-nav-section-title">System</div>
            <a href="<?= BASE_PATH ?>/admin-platform/settings.php" class="ds-nav-link <?= $currentPage === 'settings' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001.08 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9c.26.604.852.997 1.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1.08z"></path>
                </svg>
                Indstillinger
            </a>
        </nav>

        <div class="ds-sidebar-footer">
            <div class="ds-sidebar-user">
                <div class="ds-sidebar-user-avatar">
                    <?= strtoupper(substr($currentAdmin['name'] ?? 'A', 0, 1)) ?>
                </div>
                <div>
                    <div class="ds-sidebar-user-name"><?= escape($currentAdmin['name'] ?? 'Admin') ?></div>
                    <div class="ds-sidebar-user-email">Platform Admin</div>
                </div>
            </div>
            <a href="<?= BASE_PATH ?>/admin-platform/logout.php" class="ds-nav-link ds-nav-link-logout" style="margin-top: var(--space-sm);">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                Log ud
            </a>
        </div>
    </aside>

    <!-- Main content -->
    <main class="ds-main">
        <div class="ds-content">

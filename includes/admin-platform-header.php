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
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Admin - <?= escape($platformName) ?></title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --color-bg: #f8fafc;
            --color-bg-subtle: #f1f5f9;
            --color-surface: #ffffff;
            --color-primary: #3b82f6;
            --color-primary-deep: #2563eb;
            --color-primary-soft: #dbeafe;
            --color-accent: #8b5cf6;
            --color-text: #1e293b;
            --color-text-soft: #475569;
            --color-text-muted: #94a3b8;
            --color-border: #e2e8f0;
            --color-border-soft: #f1f5f9;
            --color-success: #22c55e;
            --color-success-soft: #dcfce7;
            --color-warning: #f59e0b;
            --color-warning-soft: #fef3c7;
            --color-error: #ef4444;
            --color-error-soft: #fee2e2;

            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);

            --radius-sm: 6px;
            --radius-md: 8px;
            --radius-lg: 12px;

            --space-xs: 0.5rem;
            --space-sm: 0.75rem;
            --space-md: 1rem;
            --space-lg: 1.5rem;
            --space-xl: 2rem;

            --text-xs: 0.75rem;
            --text-sm: 0.875rem;
            --text-base: 1rem;
            --text-lg: 1.125rem;
            --text-xl: 1.25rem;
            --text-2xl: 1.5rem;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--color-bg);
            color: var(--color-text);
            line-height: 1.6;
        }

        .platform-layout {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .platform-sidebar {
            width: 260px;
            background: var(--color-surface);
            border-right: 1px solid var(--color-border);
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            display: flex;
            flex-direction: column;
            z-index: 100;
        }

        .sidebar-brand {
            padding: var(--space-lg);
            border-bottom: 1px solid var(--color-border);
        }

        .sidebar-brand-name {
            font-size: var(--text-lg);
            font-weight: 700;
            color: var(--color-primary);
        }

        .sidebar-brand-label {
            font-size: var(--text-xs);
            color: var(--color-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .sidebar-nav {
            flex: 1;
            padding: var(--space-md);
            overflow-y: auto;
        }

        .nav-section {
            margin-bottom: var(--space-lg);
        }

        .nav-section-title {
            font-size: var(--text-xs);
            font-weight: 600;
            color: var(--color-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: var(--space-xs) var(--space-sm);
            margin-bottom: var(--space-xs);
        }

        .nav-menu {
            list-style: none;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            padding: var(--space-sm) var(--space-md);
            color: var(--color-text-soft);
            text-decoration: none;
            border-radius: var(--radius-md);
            transition: all 0.15s ease;
            font-size: var(--text-sm);
        }

        .nav-link:hover {
            background: var(--color-bg-subtle);
            color: var(--color-text);
        }

        .nav-link.active {
            background: var(--color-primary-soft);
            color: var(--color-primary-deep);
            font-weight: 500;
        }

        .nav-link-icon {
            width: 20px;
            text-align: center;
        }

        .nav-badge {
            margin-left: auto;
            background: var(--color-error);
            color: white;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 10px;
        }

        .sidebar-footer {
            padding: var(--space-md);
            border-top: 1px solid var(--color-border);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            background: var(--color-primary-soft);
            color: var(--color-primary-deep);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: var(--text-sm);
        }

        .user-name {
            font-weight: 500;
            font-size: var(--text-sm);
        }

        .user-role {
            font-size: var(--text-xs);
            color: var(--color-text-muted);
        }

        /* Main content */
        .platform-main {
            flex: 1;
            margin-left: 260px;
        }

        .platform-header {
            background: var(--color-surface);
            border-bottom: 1px solid var(--color-border);
            padding: var(--space-md) var(--space-xl);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .page-title {
            font-size: var(--text-xl);
            font-weight: 600;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: var(--space-md);
        }

        .platform-content {
            padding: var(--space-xl);
        }

        /* Cards */
        .card {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
        }

        .card-title {
            font-size: var(--text-lg);
            font-weight: 600;
            margin-bottom: var(--space-md);
        }

        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--space-md);
        }

        .stat-card {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
        }

        .stat-label {
            font-size: var(--text-sm);
            color: var(--color-text-muted);
            margin-bottom: var(--space-xs);
        }

        .stat-value {
            font-size: var(--text-2xl);
            font-weight: 700;
            color: var(--color-text);
        }

        .stat-change {
            font-size: var(--text-xs);
            margin-top: var(--space-xs);
        }

        .stat-change.positive { color: var(--color-success); }
        .stat-change.negative { color: var(--color-error); }

        /* Tables */
        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: var(--space-sm) var(--space-md);
            text-align: left;
            border-bottom: 1px solid var(--color-border);
        }

        th {
            font-size: var(--text-xs);
            font-weight: 600;
            color: var(--color-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: var(--color-bg-subtle);
        }

        td {
            font-size: var(--text-sm);
        }

        tr:hover td {
            background: var(--color-bg-subtle);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
            padding: var(--space-sm) var(--space-md);
            border: none;
            border-radius: var(--radius-md);
            font-size: var(--text-sm);
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s ease;
        }

        .btn-primary {
            background: var(--color-primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--color-primary-deep);
        }

        .btn-secondary {
            background: var(--color-bg-subtle);
            color: var(--color-text);
        }

        .btn-secondary:hover {
            background: var(--color-border);
        }

        .btn-success {
            background: var(--color-success);
            color: white;
        }

        .btn-danger {
            background: var(--color-error);
            color: white;
        }

        .btn-sm {
            padding: var(--space-xs) var(--space-sm);
            font-size: var(--text-xs);
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: var(--text-xs);
            font-weight: 500;
        }

        .badge-success {
            background: var(--color-success-soft);
            color: var(--color-success);
        }

        .badge-warning {
            background: var(--color-warning-soft);
            color: var(--color-warning);
        }

        .badge-error {
            background: var(--color-error-soft);
            color: var(--color-error);
        }

        .badge-info {
            background: var(--color-primary-soft);
            color: var(--color-primary);
        }

        /* Forms */
        .form-group {
            margin-bottom: var(--space-md);
        }

        .form-label {
            display: block;
            font-size: var(--text-sm);
            font-weight: 500;
            margin-bottom: var(--space-xs);
        }

        .form-input {
            width: 100%;
            padding: var(--space-sm) var(--space-md);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            font-size: var(--text-sm);
            transition: border-color 0.15s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px var(--color-primary-soft);
        }

        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M2 4l4 4 4-4'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 36px;
        }

        /* Alerts */
        .alert {
            padding: var(--space-md);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-md);
        }

        .alert-success {
            background: var(--color-success-soft);
            color: #166534;
        }

        .alert-error {
            background: var(--color-error-soft);
            color: #991b1b;
        }

        .alert-warning {
            background: var(--color-warning-soft);
            color: #92400e;
        }

        /* Search */
        .search-box {
            position: relative;
        }

        .search-box input {
            padding-left: 40px;
        }

        .search-box-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--color-text-muted);
        }

        /* Pagination */
        .pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-xs);
            margin-top: var(--space-lg);
        }

        .pagination a, .pagination span {
            padding: var(--space-xs) var(--space-sm);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
            font-size: var(--text-sm);
            text-decoration: none;
            color: var(--color-text-soft);
        }

        .pagination a:hover {
            background: var(--color-bg-subtle);
        }

        .pagination .active {
            background: var(--color-primary);
            color: white;
            border-color: var(--color-primary);
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: var(--space-xl);
            color: var(--color-text-muted);
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: var(--space-md);
        }

        /* Utilities */
        .text-muted { color: var(--color-text-muted); }
        .text-success { color: var(--color-success); }
        .text-error { color: var(--color-error); }
        .text-sm { font-size: var(--text-sm); }
        .text-xs { font-size: var(--text-xs); }
        .font-medium { font-weight: 500; }
        .font-bold { font-weight: 700; }
        .mb-md { margin-bottom: var(--space-md); }
        .mb-lg { margin-bottom: var(--space-lg); }
        .mt-md { margin-top: var(--space-md); }
        .flex { display: flex; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .gap-sm { gap: var(--space-sm); }
        .gap-md { gap: var(--space-md); }
    </style>
</head>
<body>
    <div class="platform-layout">
        <!-- Sidebar -->
        <aside class="platform-sidebar">
            <div class="sidebar-brand">
                <div class="sidebar-brand-name"><?= escape($platformName) ?></div>
                <div class="sidebar-brand-label">Platform Admin</div>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Oversigt</div>
                    <ul class="nav-menu">
                        <li>
                            <a href="<?= BASE_PATH ?>/admin-platform/index.php" class="nav-link <?= $currentPage === 'index' ? 'active' : '' ?>">
                                <span class="nav-link-icon">&#128200;</span>
                                Dashboard
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Brugere</div>
                    <ul class="nav-menu">
                        <li>
                            <a href="<?= BASE_PATH ?>/admin-platform/accounts.php" class="nav-link <?= $currentPage === 'accounts' ? 'active' : '' ?>">
                                <span class="nav-link-icon">&#128100;</span>
                                Konti
                            </a>
                        </li>
                        <li>
                            <a href="<?= BASE_PATH ?>/admin-platform/subscriptions.php" class="nav-link <?= $currentPage === 'subscriptions' ? 'active' : '' ?>">
                                <span class="nav-link-icon">&#128179;</span>
                                Abonnementer
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Finans</div>
                    <ul class="nav-menu">
                        <li>
                            <a href="<?= BASE_PATH ?>/admin-platform/revenue.php" class="nav-link <?= $currentPage === 'revenue' ? 'active' : '' ?>">
                                <span class="nav-link-icon">&#128176;</span>
                                Omsætning
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Markedsplads</div>
                    <ul class="nav-menu">
                        <li>
                            <a href="<?= BASE_PATH ?>/admin-platform/partners.php" class="nav-link <?= $currentPage === 'partners' ? 'active' : '' ?>">
                                <span class="nav-link-icon">&#127970;</span>
                                Partnere
                                <?php if ($platformStats['pending_partners'] > 0): ?>
                                    <span class="nav-badge"><?= $platformStats['pending_partners'] ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">System</div>
                    <ul class="nav-menu">
                        <li>
                            <a href="<?= BASE_PATH ?>/admin-platform/settings.php" class="nav-link <?= $currentPage === 'settings' ? 'active' : '' ?>">
                                <span class="nav-link-icon">&#9881;</span>
                                Indstillinger
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">
                        <?= strtoupper(substr($currentAdmin['name'] ?? 'A', 0, 1)) ?>
                    </div>
                    <div>
                        <div class="user-name"><?= escape($currentAdmin['name'] ?? 'Admin') ?></div>
                        <div class="user-role">Platform Admin</div>
                    </div>
                </div>
                <a href="<?= BASE_PATH ?>/admin-platform/logout.php" class="nav-link" style="margin-top: var(--space-sm);">
                    <span class="nav-link-icon">&#128682;</span>
                    Log ud
                </a>
            </div>
        </aside>

        <!-- Main content -->
        <main class="platform-main">

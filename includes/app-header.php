<?php
/**
 * App Header - Main application layout wrapper
 * Include this at the top of all /app/ pages
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth-account.php';
require_once __DIR__ . '/subscription.php';

// Require login for all app pages
requireAccountLogin();

// Get current account info
$currentAccount = getCurrentAccount();
$accountId = getCurrentAccountId();
$subscription = getAccountSubscription($accountId);

// Get account's events
$db = getDB();
$stmt = $db->prepare("
    SELECT e.*, eo.role as owner_role, et.name as event_type_name, et.icon as event_type_icon
    FROM events e
    JOIN event_owners eo ON e.id = eo.event_id
    LEFT JOIN event_types et ON e.event_type_id = et.id
    WHERE eo.account_id = ? AND e.is_legacy = FALSE
    ORDER BY e.event_date DESC
");
$stmt->execute([$accountId]);
$userEvents = $stmt->fetchAll();

// Get flash message
$flash = getFlash();

// Page title (can be overridden before including this file)
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - EventPlatform</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #667eea;
            --primary-dark: #5a67d8;
            --primary-light: #a3bffa;
            --secondary: #764ba2;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --sidebar-width: 260px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--gray-50);
            color: var(--gray-800);
            min-height: 100vh;
        }

        /* Top Header */
        .app-header {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            height: 64px;
            background: white;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            z-index: 100;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .header-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--gray-900);
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .user-menu {
            position: relative;
        }

        .user-menu-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            background: none;
            border: none;
            cursor: pointer;
            border-radius: 8px;
            transition: background 0.2s;
        }

        .user-menu-btn:hover {
            background: var(--gray-100);
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
            font-weight: 600;
        }

        .user-name {
            font-size: 14px;
            font-weight: 500;
            color: var(--gray-700);
        }

        .user-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 8px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            min-width: 200px;
            padding: 8px;
            display: none;
            z-index: 200;
        }

        .user-dropdown.show {
            display: block;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            color: var(--gray-700);
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            transition: background 0.2s;
        }

        .dropdown-item:hover {
            background: var(--gray-100);
        }

        .dropdown-item svg {
            width: 18px;
            height: 18px;
            color: var(--gray-500);
        }

        .dropdown-divider {
            height: 1px;
            background: var(--gray-200);
            margin: 8px 0;
        }

        .dropdown-item.danger {
            color: var(--danger);
        }

        .dropdown-item.danger svg {
            color: var(--danger);
        }

        /* Sidebar */
        .app-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: white;
            border-right: 1px solid var(--gray-200);
            display: flex;
            flex-direction: column;
            z-index: 150;
        }

        .sidebar-header {
            padding: 20px 20px;
            border-bottom: 1px solid var(--gray-200);
        }

        .sidebar-logo {
            font-size: 22px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-decoration: none;
        }

        .sidebar-nav {
            flex: 1;
            padding: 16px 12px;
            overflow-y: auto;
        }

        .nav-section {
            margin-bottom: 24px;
        }

        .nav-section-title {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray-400);
            padding: 0 12px;
            margin-bottom: 8px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            color: var(--gray-600);
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            margin-bottom: 4px;
        }

        .nav-link:hover {
            background: var(--gray-100);
            color: var(--gray-900);
        }

        .nav-link.active {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
        }

        .nav-link svg {
            width: 20px;
            height: 20px;
        }

        .nav-link.active svg {
            color: white;
        }

        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid var(--gray-200);
        }

        .plan-badge {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px;
            background: var(--gray-50);
            border-radius: 10px;
            margin-bottom: 12px;
        }

        .plan-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .plan-label {
            font-size: 11px;
            color: var(--gray-500);
        }

        .plan-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--gray-800);
        }

        .upgrade-btn {
            display: block;
            text-align: center;
            padding: 10px 16px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .upgrade-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        /* Main Content */
        .app-main {
            margin-left: var(--sidebar-width);
            padding-top: 64px;
            min-height: 100vh;
        }

        .app-content {
            padding: 32px;
            max-width: 1400px;
        }

        /* Flash Messages */
        .flash-message {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }

        .flash-message svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .flash-message.success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #15803d;
        }

        .flash-message.error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }

        .flash-message.warning {
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #b45309;
        }

        .flash-message.info {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1d4ed8;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            :root {
                --sidebar-width: 0px;
            }

            .app-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }

            .app-sidebar.open {
                transform: translateX(0);
            }

            .app-header {
                left: 0;
            }

            .menu-toggle {
                display: flex;
            }
        }

        .menu-toggle {
            display: none;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: none;
            border: none;
            cursor: pointer;
            border-radius: 8px;
        }

        .menu-toggle:hover {
            background: var(--gray-100);
        }

        .menu-toggle svg {
            width: 24px;
            height: 24px;
            color: var(--gray-600);
        }

        /* Common Components */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 32px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--gray-900);
        }

        .page-subtitle {
            color: var(--gray-500);
            margin-top: 4px;
            font-size: 15px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            font-family: inherit;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: white;
            color: var(--gray-700);
            border: 2px solid var(--gray-200);
        }

        .btn-secondary:hover {
            border-color: var(--gray-300);
            background: var(--gray-50);
        }

        .btn svg {
            width: 18px;
            height: 18px;
        }

        .card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid var(--gray-200);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--gray-900);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state svg {
            width: 64px;
            height: 64px;
            color: var(--gray-300);
            margin-bottom: 16px;
        }

        .empty-state h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 8px;
        }

        .empty-state p {
            color: var(--gray-500);
            margin-bottom: 24px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="app-sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="/app/dashboard.php" class="sidebar-logo">EventPlatform</a>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Oversigt</div>
                <a href="/app/dashboard.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                    </svg>
                    Dashboard
                </a>
            </div>

            <?php if (!empty($userEvents)): ?>
            <div class="nav-section">
                <div class="nav-section-title">Mine arrangementer</div>
                <?php foreach (array_slice($userEvents, 0, 5) as $event): ?>
                <a href="/app/events/manage.php?id=<?= $event['id'] ?>" class="nav-link">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <?= htmlspecialchars($event['name'] ?? $event['main_person_name'] ?? 'Arrangement') ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="nav-section">
                <div class="nav-section-title">Handlinger</div>
                <a href="/app/events/create.php" class="nav-link">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Nyt arrangement
                </a>
            </div>
        </nav>

        <div class="sidebar-footer">
            <div class="plan-badge">
                <div class="plan-info">
                    <span class="plan-label">Din plan</span>
                    <span class="plan-name"><?= htmlspecialchars($subscription['plan_name'] ?? 'Gratis') ?></span>
                </div>
            </div>
            <?php if (($subscription['plan_slug'] ?? 'free') === 'free'): ?>
            <a href="/app/account/subscription.php" class="upgrade-btn">Opgrader nu</a>
            <?php endif; ?>
        </div>
    </aside>

    <!-- Top Header -->
    <header class="app-header">
        <div class="header-left">
            <button class="menu-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            <h1 class="header-title"><?= htmlspecialchars($pageTitle) ?></h1>
        </div>

        <div class="header-right">
            <div class="user-menu">
                <button class="user-menu-btn" onclick="this.nextElementSibling.classList.toggle('show')">
                    <div class="user-avatar">
                        <?= strtoupper(substr($currentAccount['name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <span class="user-name"><?= htmlspecialchars($currentAccount['name'] ?? 'Bruger') ?></span>
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
                <div class="user-dropdown">
                    <a href="/app/account/settings.php" class="dropdown-item">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Kontoindstillinger
                    </a>
                    <a href="/app/account/subscription.php" class="dropdown-item">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                        </svg>
                        Abonnement
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="/app/auth/logout.php" class="dropdown-item danger">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        Log ud
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="app-main">
        <div class="app-content">
            <?php if ($flash): ?>
                <div class="flash-message <?= htmlspecialchars($flash['type']) ?>">
                    <?php if ($flash['type'] === 'success'): ?>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    <?php elseif ($flash['type'] === 'error'): ?>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    <?php endif; ?>
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
            <?php endif; ?>

<?php
/**
 * App Header - Main application layout wrapper
 * Nordic Design System
 */

require_once __DIR__ . '/../config/saas.php';
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
    <title><?= htmlspecialchars($pageTitle) ?> - PartyParart</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --cream: #E8E4DE;
            --cream-dark: #D9D4CC;
            --cream-light: #F5F3EF;
            --sage: #8FA583;
            --sage-light: #B8C9B0;
            --sage-dark: #5D7255;
            --charcoal: #1A1A1A;
            --charcoal-light: #3D3D3D;
            --gold: #B8923D;
            --gold-light: #E8D5A3;
            --white: #FFFFFF;
            --success: #4D854D;
            --warning: #C4922D;
            --danger: #B84C4C;

            --font-display: 'Cormorant Garamond', Georgia, serif;
            --font-body: 'DM Sans', -apple-system, sans-serif;
            --ease-out: cubic-bezier(0.16, 1, 0.3, 1);
            --sidebar-width: 280px;
        }

        body {
            font-family: var(--font-body);
            background: var(--cream);
            color: var(--charcoal);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* Subtle grain texture */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.8' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E");
            opacity: 0.02;
            pointer-events: none;
            z-index: 9999;
        }

        /* Sidebar */
        .app-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: var(--white);
            border-right: 1px solid var(--cream-dark);
            display: flex;
            flex-direction: column;
            z-index: 150;
        }

        .sidebar-header {
            padding: 28px 24px;
            border-bottom: 1px solid var(--cream-dark);
        }

        .sidebar-logo {
            font-family: var(--font-display);
            font-size: 26px;
            font-weight: 500;
            color: var(--charcoal);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .sidebar-logo span {
            color: var(--sage-dark);
        }

        .sidebar-nav {
            flex: 1;
            padding: 20px 16px;
            overflow-y: auto;
        }

        .nav-section {
            margin-bottom: 28px;
        }

        .nav-section-title {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--charcoal-light);
            padding: 0 12px;
            margin-bottom: 12px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            color: var(--charcoal);
            text-decoration: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.25s var(--ease-out);
            margin-bottom: 4px;
        }

        .nav-link:hover {
            background: var(--sage-light);
            color: var(--charcoal);
            transform: translateX(4px);
        }

        .nav-link.active {
            background: var(--sage);
            color: var(--white);
        }

        .nav-link svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid var(--cream-dark);
        }

        .plan-badge {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px;
            background: var(--cream-light);
            border-radius: 14px;
            margin-bottom: 12px;
        }

        .plan-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .plan-label {
            font-size: 11px;
            color: var(--charcoal-light);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .plan-name {
            font-family: var(--font-display);
            font-size: 18px;
            font-weight: 500;
            color: var(--charcoal);
        }

        .upgrade-btn {
            display: block;
            text-align: center;
            padding: 14px 20px;
            background: var(--sage);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.25s var(--ease-out);
        }

        .upgrade-btn:hover {
            background: var(--sage-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(93, 114, 85, 0.3);
        }

        /* Top Header */
        .app-header {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            height: 72px;
            background: var(--white);
            border-bottom: 1px solid var(--cream-dark);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            z-index: 100;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .header-title {
            font-family: var(--font-display);
            font-size: 24px;
            font-weight: 500;
            color: var(--charcoal);
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
            gap: 12px;
            padding: 8px 14px;
            background: none;
            border: none;
            cursor: pointer;
            border-radius: 12px;
            transition: all 0.25s var(--ease-out);
        }

        .user-menu-btn:hover {
            background: var(--cream-light);
        }

        .user-avatar {
            width: 38px;
            height: 38px;
            background: var(--sage);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-family: var(--font-display);
            font-size: 16px;
            font-weight: 600;
        }

        .user-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--charcoal);
        }

        .user-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 8px;
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.12);
            min-width: 220px;
            padding: 8px;
            display: none;
            z-index: 200;
            border: 1px solid var(--cream-dark);
        }

        .user-dropdown.show {
            display: block;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            color: var(--charcoal);
            text-decoration: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s var(--ease-out);
        }

        .dropdown-item:hover {
            background: var(--cream-light);
        }

        .dropdown-item svg {
            width: 18px;
            height: 18px;
            color: var(--charcoal-light);
        }

        .dropdown-divider {
            height: 1px;
            background: var(--cream-dark);
            margin: 8px 0;
        }

        .dropdown-item.danger {
            color: var(--danger);
        }

        .dropdown-item.danger:hover {
            background: #FDF2F2;
        }

        .dropdown-item.danger svg {
            color: var(--danger);
        }

        /* Main Content */
        .app-main {
            margin-left: var(--sidebar-width);
            padding-top: 72px;
            min-height: 100vh;
        }

        .app-content {
            padding: 40px;
            max-width: 1400px;
        }

        /* Flash Messages */
        .flash-message {
            padding: 16px 20px;
            border-radius: 14px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: 500;
        }

        .flash-message svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .flash-message.success {
            background: #E8F5E8;
            border: 1px solid #C8E6C8;
            color: var(--success);
        }

        .flash-message.error {
            background: #FDF2F2;
            border: 1px solid #F5D5D5;
            color: var(--danger);
        }

        .flash-message.warning {
            background: #FFF8E8;
            border: 1px solid #F5E6C8;
            color: var(--warning);
        }

        /* Mobile responsive */
        @media (max-width: 1024px) {
            :root {
                --sidebar-width: 0px;
            }

            .app-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s var(--ease-out);
                width: 280px;
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
            width: 44px;
            height: 44px;
            background: none;
            border: none;
            cursor: pointer;
            border-radius: 12px;
        }

        .menu-toggle:hover {
            background: var(--cream-light);
        }

        .menu-toggle svg {
            width: 24px;
            height: 24px;
            color: var(--charcoal);
        }

        /* Common Components */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 32px;
        }

        .page-title {
            font-family: var(--font-display);
            font-size: 36px;
            font-weight: 400;
            color: var(--charcoal);
        }

        .page-subtitle {
            color: var(--charcoal-light);
            margin-top: 4px;
            font-size: 15px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 24px;
            font-family: var(--font-body);
            font-size: 14px;
            font-weight: 600;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.25s var(--ease-out);
            text-decoration: none;
        }

        .btn-primary {
            background: var(--charcoal);
            color: white;
        }

        .btn-primary:hover {
            background: var(--charcoal-light);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(26, 26, 26, 0.2);
        }

        .btn-secondary {
            background: var(--white);
            color: var(--charcoal);
            border: 2px solid var(--cream-dark);
        }

        .btn-secondary:hover {
            border-color: var(--sage);
            background: var(--cream-light);
        }

        .btn svg {
            width: 18px;
            height: 18px;
        }

        .card {
            background: var(--white);
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03), 0 4px 12px rgba(0,0,0,0.03);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .card-title {
            font-family: var(--font-display);
            font-size: 22px;
            font-weight: 500;
            color: var(--charcoal);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state svg {
            width: 64px;
            height: 64px;
            color: var(--cream-dark);
            margin-bottom: 16px;
        }

        .empty-state h3 {
            font-family: var(--font-display);
            font-size: 22px;
            font-weight: 500;
            color: var(--charcoal);
            margin-bottom: 8px;
        }

        .empty-state p {
            color: var(--charcoal-light);
            margin-bottom: 24px;
        }

        /* Forms */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--charcoal);
            margin-bottom: 8px;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px;
            font-family: var(--font-body);
            font-size: 15px;
            border: 2px solid var(--cream-dark);
            border-radius: 12px;
            background: var(--white);
            color: var(--charcoal);
            transition: all 0.25s var(--ease-out);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--sage);
            box-shadow: 0 0 0 4px rgba(143, 165, 131, 0.15);
        }

        .form-input::placeholder {
            color: #A8A39B;
        }

        select.form-input {
            cursor: pointer;
        }

        textarea.form-input {
            resize: vertical;
            min-height: 100px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="app-sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="/app/dashboard.php" class="sidebar-logo">Party<span>Parart</span></a>
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

<?php
/**
 * App Header - Main application layout wrapper
 * Nordic Design System
 */
ob_start();

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
<html lang="da" data-area="event">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - PartyParart</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/design-system.css">
    <style>
        /* User dropdown — app-header specific */
        .user-menu { position: relative; }
        .user-menu-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 14px;
            background: none;
            border: none;
            cursor: pointer;
            border-radius: var(--radius-md);
            transition: all 0.2s;
        }
        .user-menu-btn:hover { background: var(--surface); }
        .user-avatar {
            width: 38px;
            height: 38px;
            background: var(--accent);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-on-dark);
            font-family: var(--font-display);
            font-size: 16px;
            font-weight: 600;
        }
        .user-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
        }
        .user-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 8px;
            background: var(--surface-card);
            border-radius: var(--radius-lg);
            box-shadow: 0 10px 40px rgba(0,0,0,0.12);
            min-width: 220px;
            padding: 8px;
            display: none;
            z-index: 200;
            border: 1px solid var(--border);
        }
        .user-dropdown.show { display: block; }
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            color: var(--text);
            text-decoration: none;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .dropdown-item:hover { background: var(--surface); }
        .dropdown-item svg { width: 18px; height: 18px; color: var(--text-secondary); }
        .dropdown-divider { height: 1px; background: var(--border); margin: 8px 0; }
        .dropdown-item.danger { color: var(--error); }
        .dropdown-item.danger:hover { background: var(--error-light); }
        .dropdown-item.danger svg { color: var(--error); }

        /* App header bar (sits on top of dark sidebar in a unified header zone) */
        .app-header {
            position: sticky;
            top: 0;
            margin-left: var(--sidebar-width);
            height: 64px;
            background: var(--surface-card);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 var(--space-xl);
            z-index: 100;
        }
        .header-left { display: flex; align-items: center; gap: var(--space-md); }
        .header-title {
            font-family: var(--font-display);
            font-size: 24px;
            font-weight: 500;
            color: var(--text);
        }
        .header-right { display: flex; align-items: center; gap: var(--space-md); }

        /* Menu toggle */
        .menu-toggle {
            display: none;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            background: none;
            border: none;
            cursor: pointer;
            border-radius: var(--radius-md);
        }
        .menu-toggle:hover { background: var(--surface); }
        .menu-toggle svg { width: 24px; height: 24px; color: var(--text); }

        /* Main content area */
        .app-main {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }
        .app-content {
            padding: 40px;
            max-width: 1400px;
        }

        @media (max-width: 1024px) {
            .app-header { margin-left: 0; }
            .app-main { margin-left: 0; }
            .menu-toggle { display: flex; }
        }
    </style>
</head>
<body>
    <a href="#main-content" class="sr-only">Spring til indhold</a>
    <!-- Sidebar overlay for mobile -->
    <div class="ds-sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <aside class="ds-sidebar" id="sidebar">
        <div class="ds-sidebar-header">
            <a href="/app/dashboard.php" class="ds-sidebar-logo">
                <span class="ds-sidebar-logo-text">PartyParart</span>
            </a>
        </div>

        <nav class="ds-sidebar-nav">
            <div class="ds-nav-section-title">Oversigt</div>
            <a href="/app/dashboard.php" class="ds-nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                Dashboard
            </a>

            <?php if (!empty($userEvents)): ?>
            <div class="ds-nav-section-title">Mine arrangementer</div>
            <?php foreach (array_slice($userEvents, 0, 5) as $navEvent): ?>
            <a href="/app/events/manage.php?id=<?= $navEvent['id'] ?>" class="ds-nav-link">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <?= htmlspecialchars($navEvent['name'] ?? $navEvent['main_person_name'] ?? 'Arrangement') ?>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>

            <div class="ds-nav-section-title">Handlinger</div>
            <a href="/app/events/create.php" class="ds-nav-link">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Nyt arrangement
            </a>
        </nav>

        <div class="ds-sidebar-footer">
            <div class="ds-plan-badge">
                <?= htmlspecialchars($subscription['plan_name'] ?? 'Gratis') ?>
            </div>
            <?php if (($subscription['plan_slug'] ?? 'free') === 'free'): ?>
            <a href="/app/account/subscription.php" class="ds-sidebar-upgrade">Opgrader nu</a>
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
    <main class="app-main" id="main-content">
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

<?php
/**
 * Event Management - Main event administration router
 * Nordic Design
 */
ob_start();

require_once __DIR__ . '/../../config/saas.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth-account.php';
require_once __DIR__ . '/../../includes/subscription.php';

requireAccountLogin();

$accountId = getCurrentAccountId();
$db = getDB();

// Get event ID from URL
$eventId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$eventId) {
    setFlash('error', 'Ugyldigt arrangement.');
    redirect('/app/dashboard.php');
}

// Check if user has access to this event
$stmt = $db->prepare("
    SELECT e.*, eo.role as user_role, et.name as event_type_name
    FROM events e
    JOIN event_owners eo ON e.id = eo.event_id AND eo.account_id = ?
    LEFT JOIN event_types et ON e.event_type_id = et.id
    WHERE e.id = ?
");
$stmt->execute([$accountId, $eventId]);
$event = $stmt->fetch();

if (!$event) {
    setFlash('error', 'Du har ikke adgang til dette arrangement.');
    redirect('/app/dashboard.php');
}

// Get current page/tab
$page = $_GET['page'] ?? 'dashboard';
$validPages = ['dashboard', 'guests', 'wishlist', 'menu', 'schedule', 'photos', 'memories-admin', 'qr-bordkort', 'checklist', 'budget', 'vendors', 'vendor-booking', 'seating', 'toastmaster', 'invitation', 'invitation-send', 'settings'];
if (!in_array($page, $validPages)) {
    $page = 'dashboard';
}

// Redirect deprecated menu page to schedule
if ($page === 'menu') {
    header("Location: ?id=$eventId&page=schedule&section=menu");
    exit;
}

// Get subscription for feature checks
$subscription = getAccountSubscription($accountId);
$features = $subscription['features'] ?? [];

// Check premium features
$hasChecklist = !empty($features['checklist']);
$hasBudget = !empty($features['budget']);
$hasSeating = !empty($features['seating']);
$hasToastmaster = !empty($features['toastmaster']);

// Get event statistics
$stmt = $db->prepare("
    SELECT
        COUNT(*) as total_invitations,
        COALESCE(SUM(adults_count + children_count), 0) as total_guests,
        SUM(CASE WHEN rsvp_status = 'yes' THEN 1 ELSE 0 END) as accepted_invitations,
        SUM(CASE WHEN rsvp_status = 'no' THEN 1 ELSE 0 END) as declined_invitations,
        SUM(CASE WHEN rsvp_status = 'pending' THEN 1 ELSE 0 END) as pending_invitations,
        SUM(CASE WHEN rsvp_status = 'yes' THEN adults_count ELSE 0 END) as total_adults,
        SUM(CASE WHEN rsvp_status = 'yes' THEN children_count ELSE 0 END) as total_children,
        SUM(CASE WHEN rsvp_status = 'yes' THEN adults_count + children_count ELSE 0 END) as accepted,
        SUM(CASE WHEN rsvp_status = 'no' THEN adults_count + children_count ELSE 0 END) as declined,
        SUM(CASE WHEN rsvp_status = 'pending' THEN adults_count + children_count ELSE 0 END) as pending
    FROM guests
    WHERE event_id = ?
");
$stmt->execute([$eventId]);
$guestStats = $stmt->fetch();

$pageTitle = $event['name'] ?? 'Arrangement';
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
        /* Subtle grain texture overlay */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.8' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E");
            opacity: 0.02;
            pointer-events: none;
            z-index: 9999;
        }

        /* ===== Top Navigation ===== */
        .top-nav {
            background: var(--surface-card);
            border-bottom: 1px solid var(--border);
            padding: 0 24px;
            position: sticky;
            top: 0;
            z-index: 100;
            margin-left: var(--sidebar-width);
        }

        .top-nav-inner {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 72px;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .back-link {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
            padding: 8px 12px;
            border-radius: var(--radius-md);
            transition: all 0.25s var(--ease-out);
        }

        .back-link:hover {
            background: var(--surface);
            color: var(--text);
        }

        .back-link svg {
            width: 18px;
            height: 18px;
        }

        .event-title {
            font-family: var(--font-display);
            font-size: 22px;
            font-weight: 500;
            color: var(--text);
        }

        .event-badge {
            font-size: 12px;
            padding: var(--space-xs) var(--space-sm);
            border-radius: var(--radius-sm);
            background: var(--accent-light);
            color: var(--accent-dark);
            font-weight: 500;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-sage {
            background: var(--accent);
            color: var(--text-on-dark);
        }

        .btn-sage:hover {
            background: var(--accent-dark);
        }

        /* ===== Event Sidebar — Dark Theme ===== */
        .event-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: var(--surface-sidebar);
            display: flex;
            flex-direction: column;
            z-index: 150;
            overflow: hidden;
        }

        /* 3px accent stripe */
        .event-sidebar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            width: 3px;
            background: var(--accent);
            z-index: 1;
        }

        .sidebar-header {
            padding: 24px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: var(--font-display);
            font-size: 20px;
            font-weight: 600;
            color: var(--text-on-dark);
            text-decoration: none;
        }

        .sidebar-logo svg {
            width: 22px;
            height: 22px;
            color: var(--accent);
        }

        .sidebar-logo span { color: var(--accent); }

        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            padding: 8px;
        }

        .sidebar-nav::-webkit-scrollbar { width: 4px; }
        .sidebar-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 2px; }

        .sidebar-app-nav { margin-bottom: 4px; }

        .sidebar-divider {
            height: 1px;
            background: rgba(255,255,255,0.08);
            margin: 8px 0;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            color: var(--text-sidebar);
            text-decoration: none;
            font-size: 14px;
            font-weight: 400;
            border-radius: var(--radius-sm);
            transition: all 0.2s ease;
        }

        .sidebar-link svg { width: 18px; height: 18px; flex-shrink: 0; }

        .sidebar-link:hover {
            background: rgba(255,255,255,0.06);
            color: var(--text-sidebar-hover);
        }

        .sidebar-link.active {
            background: var(--accent);
            color: var(--text-on-dark);
            font-weight: 500;
        }

        .sidebar-link.premium { color: rgba(168,164,158,0.5); }

        .sidebar-link.premium::after {
            content: 'PRO';
            font-size: 9px;
            font-weight: 700;
            padding: 2px 5px;
            background: var(--warning);
            color: var(--text-on-dark);
            border-radius: 4px;
            margin-left: auto;
        }

        .sidebar-link.premium.active { color: var(--text-on-dark); }
        .sidebar-link.premium.active::after { background: rgba(255,255,255,0.3); }

        /* Sidebar Groups */
        .sidebar-group { margin-bottom: 2px; }

        .sidebar-group-header {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            padding: 8px 12px 4px;
            border: none;
            background: none;
            cursor: pointer;
            font-family: var(--font-body);
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-sidebar);
        }

        .sidebar-group-header:hover { color: var(--text-sidebar-hover); }

        .sidebar-group-icon svg { width: 16px; height: 16px; }
        .sidebar-group-label { flex: 1; text-align: left; }

        .sidebar-group-chevron {
            width: 14px;
            height: 14px;
            transition: transform 0.2s ease;
            color: var(--text-sidebar);
        }

        .sidebar-group-header[aria-expanded="false"] .sidebar-group-chevron {
            transform: rotate(-90deg);
        }

        .sidebar-group-items {
            overflow: hidden;
            transition: max-height 0.25s ease;
        }

        .sidebar-group-header[aria-expanded="false"] + .sidebar-group-items {
            max-height: 0 !important;
        }

        .sidebar-group-items .sidebar-link { padding-left: 40px; }

        .sidebar-settings-link { margin-top: 4px; }

        /* Sidebar Footer */
        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid rgba(255,255,255,0.08);
        }

        .sidebar-plan-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px;
            background: rgba(255,255,255,0.06);
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 600;
            color: var(--text-sidebar-hover);
            margin-bottom: 8px;
        }

        .sidebar-upgrade-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px;
            background: var(--accent);
            color: var(--text-on-dark);
            text-decoration: none;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 500;
            transition: background 0.2s;
        }

        .sidebar-upgrade-btn:hover { background: var(--accent-dark); }
        .sidebar-upgrade-btn svg { width: 14px; height: 14px; }

        /* ===== Mobile Hamburger ===== */
        .event-menu-toggle {
            display: none;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border: none;
            background: none;
            cursor: pointer;
            color: var(--text);
            border-radius: var(--radius-md);
            -webkit-tap-highlight-color: transparent;
        }

        .event-menu-toggle:hover { background: var(--surface); }
        .event-menu-toggle svg { width: 22px; height: 22px; }

        /* ===== Mobile Bottom Bar ===== */
        .mobile-bottom-bar {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--surface-card);
            border-top: 1px solid var(--border);
            z-index: 200;
            justify-content: space-around;
            padding: 6px 0 env(safe-area-inset-bottom, 6px);
        }

        .bottom-bar-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            padding: 8px 4px;
            border: none;
            background: none;
            cursor: pointer;
            color: var(--text-secondary);
            -webkit-tap-highlight-color: transparent;
        }

        .bottom-bar-item.active { color: var(--accent-dark); }
        .bottom-bar-item svg { width: 22px; height: 22px; }
        .bottom-bar-item span {
            font-family: var(--font-body);
            font-size: 10px;
            font-weight: 500;
        }

        /* ===== Bottom Sheet ===== */
        .bottom-sheet {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 250;
            pointer-events: none;
        }

        .bottom-sheet.open { pointer-events: auto; }

        .bottom-sheet-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.3);
            opacity: 0;
            transition: opacity 0.3s;
        }

        .bottom-sheet.open .bottom-sheet-backdrop { opacity: 1; }

        .bottom-sheet-content {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--surface-card);
            border-radius: 16px 16px 0 0;
            padding: 0 16px 16px;
            padding-bottom: calc(16px + env(safe-area-inset-bottom, 0px));
            transform: translateY(100%);
            transition: transform 0.3s var(--ease-out);
            max-height: 60vh;
            overflow-y: auto;
        }

        .bottom-sheet.open .bottom-sheet-content { transform: translateY(0); }

        .bottom-sheet-handle {
            width: 36px;
            height: 4px;
            background: var(--border);
            border-radius: 2px;
            margin: 12px auto 16px;
        }

        .bottom-sheet-title {
            font-family: var(--font-body);
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
            padding: 4px 0 8px;
        }

        .bottom-sheet-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px;
            color: var(--text);
            text-decoration: none;
            font-size: 16px;
            border-radius: var(--radius-md);
            min-height: 48px;
        }

        .bottom-sheet-link:active { background: var(--surface); }

        .bottom-sheet-link.active {
            background: var(--accent);
            color: var(--text-on-dark);
            font-weight: 500;
        }

        .bottom-sheet-link.premium { color: #B8B0A0; }
        .bottom-sheet-link.premium.active { color: var(--text-on-dark); }

        .pro-badge {
            font-size: 9px;
            font-weight: 700;
            padding: 2px 6px;
            background: var(--warning);
            color: var(--text-on-dark);
            border-radius: 4px;
        }

        .bottom-sheet-link.active .pro-badge { background: rgba(255,255,255,0.3); }

        /* ===== Main Content ===== */
        .main-content {
            max-width: 1400px;
            margin: 0 auto 0 var(--sidebar-width);
            padding: 32px 24px;
        }

        /* manage.php card override — slightly larger padding + margin */
        .card { margin-bottom: 24px; }

        /* manage.php stat-card hover effect */
        .stat-card {
            transition: all 0.25s var(--ease-out);
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* ===== Quick Actions ===== */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
        }

        .quick-action {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 24px 16px;
            background: var(--surface);
            border-radius: var(--radius-lg);
            text-decoration: none;
            color: var(--text);
            transition: all 0.25s var(--ease-out);
            text-align: center;
        }

        .quick-action:hover {
            background: var(--border);
            transform: translateY(-3px);
        }

        .quick-action svg {
            width: 32px;
            height: 32px;
            color: var(--accent-dark);
            margin-bottom: 16px;
        }

        .quick-action span {
            font-size: 14px;
            font-weight: 500;
        }

        /* ===== Filters Bar ===== */
        .filters-bar {
            margin-bottom: 24px;
        }

        .filter-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-secondary);
            background: var(--surface-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            text-decoration: none;
            transition: all 0.25s var(--ease-out);
        }

        .filter-tab:hover {
            background: var(--surface);
            border-color: var(--accent-light);
        }

        .filter-tab.active {
            background: var(--accent);
            color: var(--text-on-dark);
            border-color: var(--accent);
        }

        /* ===== Section Header ===== */
        .section-title {
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 500;
            color: var(--text);
            margin-bottom: 8px;
        }

        .section-subtitle {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .page-header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }

        /* ===== Utility ===== */
        .danger-text { color: var(--error) !important; }

        /* ===== Upgrade Notice ===== */
        .upgrade-notice {
            background: linear-gradient(135deg, #FEF8E8 0%, #FDF3D7 100%);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 24px;
            border: 1px solid #F5E6B3;
        }

        .upgrade-notice-content h4 {
            font-family: var(--font-display);
            font-size: 17px;
            font-weight: 500;
            color: #8B6914;
            margin-bottom: 4px;
        }

        .upgrade-notice-content p {
            font-size: 14px;
            color: #A17D1A;
        }

        .upgrade-notice .btn {
            background: var(--warning);
            color: var(--text-on-dark);
            white-space: nowrap;
        }

        .upgrade-notice .btn:hover {
            background: #B8952F;
        }

        /* ===== Responsive ===== */
        @media (max-width: 1024px) {
            .event-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s var(--ease-out);
                box-shadow: none;
            }
            .event-sidebar.open {
                transform: translateX(0);
                box-shadow: 4px 0 24px rgba(0,0,0,0.1);
            }
            .sidebar-overlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.4);
                z-index: 149;
            }
            .sidebar-overlay.visible {
                display: block;
            }
            .top-nav { margin-left: 0; }
            .main-content {
                margin-left: 0;
                padding-bottom: 96px;
            }
            .event-menu-toggle { display: flex; }
            .mobile-bottom-bar { display: flex; }
            .bottom-sheet { display: block; }
        }

        @media (max-width: 768px) {
            .top-nav-inner {
                flex-wrap: wrap;
                height: auto;
                padding: 16px 0;
                gap: 12px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full-width {
                grid-column: span 1;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/event-sidebar.php'; ?>
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="document.getElementById('eventSidebar').classList.remove('open');this.classList.remove('visible')"></div>

    <!-- Top Navigation -->
    <nav class="top-nav">
        <div class="top-nav-inner">
            <div class="nav-left">
                <button type="button" class="event-menu-toggle" onclick="toggleMobileSidebar()">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
                <a href="/app/dashboard.php" class="back-link">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Dashboard
                </a>
                <h1 class="event-title"><?= htmlspecialchars($event['name'] ?? 'Arrangement') ?></h1>
                <span class="event-badge"><?= htmlspecialchars($event['event_type_name'] ?? 'Arrangement') ?></span>
            </div>
            <div class="nav-right">
                <?php if ($event['slug']): ?>
                <a href="/e/<?= htmlspecialchars($event['slug']) ?>/" class="btn btn-secondary" target="_blank">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                    </svg>
                    Se gæsteside
                </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <?php
        $flash = getFlash();
        if ($flash): ?>
        <div class="flash-message <?= htmlspecialchars($flash['type']) ?>">
            <?php if ($flash['type'] === 'success'): ?>
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            <?php else: ?>
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            <?php endif; ?>
            <?= htmlspecialchars($flash['message']) ?>
        </div>
        <?php endif; ?>

        <?php
        // Include the appropriate page content
        $pageFile = __DIR__ . '/pages/' . $page . '.php';
        if (file_exists($pageFile)) {
            include $pageFile;
        } else {
            include __DIR__ . '/pages/dashboard.php';
        }
        ?>
    </main>

    <script>
    /* Sidebar group collapse/expand */
    function toggleSidebarGroup(btn) {
        var expanded = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        var items = btn.nextElementSibling;
        if (expanded) {
            items.style.maxHeight = '0';
        } else {
            items.style.maxHeight = items.scrollHeight + 'px';
        }
    }

    /* Initialize group item heights and drag handler */
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.sidebar-group-items').forEach(function(el) {
            el.style.maxHeight = el.scrollHeight + 'px';
        });

        var handle = document.querySelector('.bottom-sheet-handle');
        if (handle) handle.addEventListener('touchstart', initSheetDrag, { passive: true });
    });

    /* Bottom sheet */
    var activeSheet = null;

    function toggleBottomSheet(groupKey) {
        var sheet = document.getElementById('bottomSheet');
        if (activeSheet === groupKey) {
            closeBottomSheet();
            return;
        }
        sheet.querySelectorAll('.bottom-sheet-group').forEach(function(el) {
            el.style.display = 'none';
        });
        var target = sheet.querySelector('[data-sheet-group="' + groupKey + '"]');
        if (target) target.style.display = 'block';
        sheet.style.display = 'block';
        requestAnimationFrame(function() {
            sheet.classList.add('open');
        });
        activeSheet = groupKey;
    }

    function closeBottomSheet() {
        var sheet = document.getElementById('bottomSheet');
        sheet.classList.remove('open');
        setTimeout(function() {
            sheet.style.display = 'none';
        }, 300);
        activeSheet = null;
    }

    /* Swipe-down to close */
    var sheetStartY = 0;
    function initSheetDrag(e) {
        sheetStartY = e.touches[0].clientY;
        var content = document.querySelector('.bottom-sheet-content');
        content.addEventListener('touchmove', onSheetDrag, { passive: false });
        content.addEventListener('touchend', onSheetDragEnd);
    }

    function onSheetDrag(e) {
        var deltaY = e.touches[0].clientY - sheetStartY;
        if (deltaY > 0) {
            e.preventDefault();
            document.querySelector('.bottom-sheet-content').style.transform = 'translateY(' + deltaY + 'px)';
        }
    }

    function onSheetDragEnd(e) {
        var content = document.querySelector('.bottom-sheet-content');
        var deltaY = e.changedTouches[0].clientY - sheetStartY;
        content.removeEventListener('touchmove', onSheetDrag);
        content.removeEventListener('touchend', onSheetDragEnd);
        content.style.transform = '';
        if (deltaY > 80) {
            closeBottomSheet();
        }
    }

    /* Mobile sidebar toggle with overlay */
    function toggleMobileSidebar() {
        var sidebar = document.getElementById('eventSidebar');
        var overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('open');
        overlay.classList.toggle('visible');
    }

    /* Close mobile sidebar on outside click */
    document.addEventListener('click', function(e) {
        var sidebar = document.getElementById('eventSidebar');
        if (sidebar && sidebar.classList.contains('open')) {
            var toggle = document.querySelector('.event-menu-toggle');
            if (!sidebar.contains(e.target) && (!toggle || !toggle.contains(e.target))) {
                sidebar.classList.remove('open');
                document.getElementById('sidebarOverlay').classList.remove('visible');
            }
        }
    });
    </script>
</body>
</html>

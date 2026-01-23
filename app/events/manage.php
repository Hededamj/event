<?php
/**
 * Event Management - Main event administration router
 */
require_once __DIR__ . '/../../config/database.php';
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
$validPages = ['dashboard', 'guests', 'wishlist', 'menu', 'schedule', 'photos', 'checklist', 'budget', 'seating', 'toastmaster', 'settings'];
if (!in_array($page, $validPages)) {
    $page = 'dashboard';
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
        COUNT(*) as total_guests,
        SUM(CASE WHEN rsvp_status = 'accepted' THEN 1 ELSE 0 END) as accepted,
        SUM(CASE WHEN rsvp_status = 'declined' THEN 1 ELSE 0 END) as declined,
        SUM(CASE WHEN rsvp_status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN rsvp_status = 'accepted' THEN adults_count ELSE 0 END) as total_adults,
        SUM(CASE WHEN rsvp_status = 'accepted' THEN children_count ELSE 0 END) as total_children
    FROM guests
    WHERE event_id = ?
");
$stmt->execute([$eventId]);
$guestStats = $stmt->fetch();

$pageTitle = $event['name'] ?? 'Arrangement';
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
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --primary: #667eea;
            --primary-dark: #5a67d8;
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
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--gray-50);
            color: var(--gray-800);
            min-height: 100vh;
        }

        /* Top Navigation */
        .top-nav {
            background: white;
            border-bottom: 1px solid var(--gray-200);
            padding: 0 24px;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .top-nav-inner {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 64px;
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
            color: var(--gray-500);
            text-decoration: none;
            font-size: 14px;
            padding: 8px 12px;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .back-link:hover {
            background: var(--gray-100);
            color: var(--gray-700);
        }

        .back-link svg {
            width: 18px;
            height: 18px;
        }

        .event-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--gray-900);
        }

        .event-badge {
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 6px;
            background: rgba(102, 126, 234, 0.1);
            color: var(--primary);
            font-weight: 500;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            font-size: 14px;
            font-weight: 500;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            font-family: inherit;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
        }

        .btn-secondary {
            background: white;
            color: var(--gray-700);
            border: 1px solid var(--gray-200);
        }

        .btn svg { width: 16px; height: 16px; }

        /* Tab Navigation */
        .tabs-nav {
            background: white;
            border-bottom: 1px solid var(--gray-200);
            padding: 0 24px;
            overflow-x: auto;
        }

        .tabs-nav-inner {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            gap: 4px;
        }

        .tab-link {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 16px 16px;
            color: var(--gray-500);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
            white-space: nowrap;
            transition: all 0.2s;
        }

        .tab-link:hover {
            color: var(--gray-700);
        }

        .tab-link.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .tab-link svg {
            width: 18px;
            height: 18px;
        }

        .tab-link.premium {
            color: var(--gray-400);
        }

        .tab-link.premium::after {
            content: 'PRO';
            font-size: 9px;
            font-weight: 700;
            padding: 2px 4px;
            background: var(--warning);
            color: white;
            border-radius: 3px;
            margin-left: 4px;
        }

        /* Main Content */
        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 32px 24px;
        }

        .card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid var(--gray-200);
            margin-bottom: 24px;
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

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid var(--gray-200);
        }

        .stat-label {
            font-size: 13px;
            color: var(--gray-500);
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--gray-900);
        }

        .stat-value.success { color: var(--success); }
        .stat-value.danger { color: var(--danger); }
        .stat-value.warning { color: var(--warning); }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
        }

        .quick-action {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 24px;
            background: var(--gray-50);
            border-radius: 12px;
            text-decoration: none;
            color: var(--gray-700);
            transition: all 0.2s;
            text-align: center;
        }

        .quick-action:hover {
            background: var(--gray-100);
            transform: translateY(-2px);
        }

        .quick-action svg {
            width: 32px;
            height: 32px;
            color: var(--primary);
            margin-bottom: 12px;
        }

        .quick-action span {
            font-size: 14px;
            font-weight: 500;
        }

        /* Empty State */
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

        .flash-message svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        /* Upgrade Notice */
        .upgrade-notice {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-radius: 12px;
            padding: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 24px;
        }

        .upgrade-notice-content h4 {
            font-size: 16px;
            font-weight: 600;
            color: #92400e;
            margin-bottom: 4px;
        }

        .upgrade-notice-content p {
            font-size: 14px;
            color: #a16207;
        }

        .upgrade-notice .btn {
            background: #f59e0b;
            color: white;
            white-space: nowrap;
        }

        @media (max-width: 768px) {
            .top-nav-inner {
                flex-wrap: wrap;
                height: auto;
                padding: 12px 0;
            }

            .tabs-nav-inner {
                gap: 0;
            }

            .tab-link {
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="top-nav">
        <div class="top-nav-inner">
            <div class="nav-left">
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

    <!-- Tab Navigation -->
    <nav class="tabs-nav">
        <div class="tabs-nav-inner">
            <a href="?id=<?= $eventId ?>&page=dashboard" class="tab-link <?= $page === 'dashboard' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                Oversigt
            </a>
            <a href="?id=<?= $eventId ?>&page=guests" class="tab-link <?= $page === 'guests' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                Gæster
            </a>
            <a href="?id=<?= $eventId ?>&page=wishlist" class="tab-link <?= $page === 'wishlist' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"></path>
                </svg>
                Ønskeliste
            </a>
            <a href="?id=<?= $eventId ?>&page=menu" class="tab-link <?= $page === 'menu' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                </svg>
                Menu
            </a>
            <a href="?id=<?= $eventId ?>&page=schedule" class="tab-link <?= $page === 'schedule' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Program
            </a>
            <a href="?id=<?= $eventId ?>&page=photos" class="tab-link <?= $page === 'photos' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                Fotos
            </a>
            <a href="?id=<?= $eventId ?>&page=checklist" class="tab-link <?= $page === 'checklist' ? 'active' : '' ?> <?= !$hasChecklist ? 'premium' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                </svg>
                Tjekliste
            </a>
            <a href="?id=<?= $eventId ?>&page=seating" class="tab-link <?= $page === 'seating' ? 'active' : '' ?> <?= !$hasSeating ? 'premium' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path>
                </svg>
                Bordplan
            </a>
            <a href="?id=<?= $eventId ?>&page=settings" class="tab-link <?= $page === 'settings' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                Indstillinger
            </a>
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
            // Default dashboard content
            include __DIR__ . '/pages/dashboard.php';
        }
        ?>
    </main>
</body>
</html>

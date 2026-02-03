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
$validPages = ['dashboard', 'guests', 'wishlist', 'menu', 'schedule', 'photos', 'checklist', 'budget', 'seating', 'toastmaster', 'invitation', 'invitation-send', 'settings'];
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
        SUM(CASE WHEN rsvp_status = 'yes' THEN 1 ELSE 0 END) as accepted,
        SUM(CASE WHEN rsvp_status = 'no' THEN 1 ELSE 0 END) as declined,
        SUM(CASE WHEN rsvp_status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN rsvp_status = 'yes' THEN adults_count ELSE 0 END) as total_adults,
        SUM(CASE WHEN rsvp_status = 'yes' THEN children_count ELSE 0 END) as total_children
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
    <title><?= htmlspecialchars($pageTitle) ?> - PartyParart</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --cream: #E8E4DE;
            --cream-dark: #D9D4CC;
            --sage: #8FA583;
            --sage-light: #B8C9B0;
            --sage-dark: #5D7255;
            --charcoal: #1A1A1A;
            --charcoal-light: #3D3D3D;
            --gold: #B8923D;
            --white: #FFFFFF;
            --success: #4D854D;
            --warning: #C4922D;
            --error: #B84C4C;

            --font-display: 'Cormorant Garamond', Georgia, serif;
            --font-body: 'DM Sans', -apple-system, sans-serif;
            --ease-out: cubic-bezier(0.16, 1, 0.3, 1);
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

        /* Top Navigation */
        .top-nav {
            background: var(--white);
            border-bottom: 1px solid var(--cream-dark);
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
            height: 72px;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .back-link {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--charcoal-light);
            text-decoration: none;
            font-size: 14px;
            padding: 10px 14px;
            border-radius: 12px;
            transition: all 0.25s var(--ease-out);
        }

        .back-link:hover {
            background: var(--cream);
            color: var(--charcoal);
        }

        .back-link svg {
            width: 18px;
            height: 18px;
        }

        .event-title {
            font-family: var(--font-display);
            font-size: 22px;
            font-weight: 500;
            color: var(--charcoal);
        }

        .event-badge {
            font-size: 12px;
            padding: 6px 12px;
            border-radius: 8px;
            background: var(--sage-light);
            color: var(--sage-dark);
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
            padding: 12px 20px;
            font-family: var(--font-body);
            font-size: 14px;
            font-weight: 500;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.25s var(--ease-out);
            text-decoration: none;
        }

        .btn-primary {
            background: var(--charcoal);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--charcoal-light);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--white);
            color: var(--charcoal);
            border: 1px solid var(--cream-dark);
        }

        .btn-secondary:hover {
            background: var(--cream);
            border-color: var(--sage-light);
        }

        .btn-sage {
            background: var(--sage);
            color: var(--white);
        }

        .btn-sage:hover {
            background: var(--sage-dark);
        }

        .btn svg { width: 16px; height: 16px; }

        /* Tab Navigation */
        .tabs-nav {
            background: var(--white);
            border-bottom: 1px solid var(--cream-dark);
            padding: 0 24px;
            overflow-x: auto;
        }

        .tabs-nav::-webkit-scrollbar {
            display: none;
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
            padding: 18px 18px;
            color: var(--charcoal-light);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            margin-bottom: -1px;
            white-space: nowrap;
            transition: all 0.25s var(--ease-out);
        }

        .tab-link:hover {
            color: var(--charcoal);
            background: var(--cream);
        }

        .tab-link.active {
            color: var(--sage-dark);
            border-bottom-color: var(--sage);
            background: transparent;
        }

        .tab-link svg {
            width: 18px;
            height: 18px;
        }

        .tab-link.premium {
            color: #B8B0A0;
        }

        .tab-link.premium::after {
            content: 'PRO';
            font-size: 9px;
            font-weight: 700;
            padding: 3px 5px;
            background: var(--gold);
            color: var(--white);
            border-radius: 4px;
            margin-left: 6px;
        }

        /* Main Content */
        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 32px 24px;
        }

        .card {
            background: var(--white);
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            border: 1px solid var(--cream-dark);
            margin-bottom: 24px;
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .card-title {
            font-family: var(--font-display);
            font-size: 20px;
            font-weight: 500;
            color: var(--charcoal);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--white);
            border-radius: 16px;
            padding: 24px;
            border: 1px solid var(--cream-dark);
            transition: all 0.25s var(--ease-out);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.06);
        }

        .stat-label {
            font-size: 13px;
            color: var(--charcoal-light);
            margin-bottom: 8px;
            font-weight: 500;
        }

        .stat-value {
            font-family: var(--font-display);
            font-size: 36px;
            font-weight: 500;
            color: var(--charcoal);
        }

        .stat-value.success { color: var(--success); }
        .stat-value.error { color: var(--error); }
        .stat-value.warning { color: var(--warning); }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
        }

        .quick-action {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 28px 20px;
            background: var(--cream);
            border-radius: 16px;
            text-decoration: none;
            color: var(--charcoal);
            transition: all 0.25s var(--ease-out);
            text-align: center;
        }

        .quick-action:hover {
            background: var(--cream-dark);
            transform: translateY(-3px);
        }

        .quick-action svg {
            width: 32px;
            height: 32px;
            color: var(--sage-dark);
            margin-bottom: 14px;
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
            color: var(--sage-light);
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-family: var(--font-display);
            font-size: 20px;
            font-weight: 500;
            color: var(--charcoal);
            margin-bottom: 8px;
        }

        .empty-state p {
            color: var(--charcoal-light);
            margin-bottom: 24px;
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
        }

        .flash-message.success {
            background: #F0F7F0;
            border: 1px solid #C8E0C8;
            color: var(--success);
        }

        .flash-message.error {
            background: #FDF2F2;
            border: 1px solid #F5D5D5;
            color: var(--error);
        }

        .flash-message svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        /* Forms */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--charcoal);
            margin-bottom: 10px;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px;
            font-family: var(--font-body);
            font-size: 14px;
            border: 2px solid var(--cream-dark);
            border-radius: 12px;
            background: var(--white);
            color: var(--charcoal);
            transition: all 0.25s var(--ease-out);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--sage);
            box-shadow: 0 0 0 4px rgba(168, 181, 160, 0.15);
        }

        .form-input::placeholder {
            color: #A8A39B;
        }

        select.form-input {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%234A4A4A'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-size: 16px;
            padding-right: 44px;
        }

        textarea.form-input {
            resize: vertical;
            min-height: 100px;
        }

        .form-actions {
            padding-top: 24px;
            border-top: 1px solid var(--cream-dark);
            margin-top: 28px;
        }

        .form-hint {
            font-size: 13px;
            color: var(--charcoal-light);
            margin-top: 8px;
        }

        /* Filters Bar */
        .filters-bar {
            margin-bottom: 24px;
        }

        .filter-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 500;
            color: var(--charcoal-light);
            background: var(--white);
            border: 1px solid var(--cream-dark);
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.25s var(--ease-out);
        }

        .filter-tab:hover {
            background: var(--cream);
            border-color: var(--sage-light);
        }

        .filter-tab.active {
            background: var(--sage);
            color: var(--white);
            border-color: var(--sage);
        }

        /* Section Header */
        .section-title {
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 500;
            color: var(--charcoal);
            margin-bottom: 6px;
        }

        .section-subtitle {
            font-size: 14px;
            color: var(--charcoal-light);
        }

        .page-header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 16px;
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(44, 44, 44, 0.4);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
        }

        .modal {
            background: var(--white);
            border-radius: 24px;
            width: 100%;
            max-width: 520px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 24px 48px rgba(0,0,0,0.15);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 24px 28px;
            border-bottom: 1px solid var(--cream-dark);
        }

        .modal-header h3 {
            font-family: var(--font-display);
            font-size: 20px;
            font-weight: 500;
            color: var(--charcoal);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: var(--charcoal-light);
            cursor: pointer;
            padding: 4px;
            line-height: 1;
            transition: color 0.2s;
        }

        .modal-close:hover {
            color: var(--charcoal);
        }

        .modal-body {
            padding: 28px;
        }

        .modal-body .form-group {
            margin-bottom: 20px;
        }

        .modal-body .form-group:last-child {
            margin-bottom: 0;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding: 20px 28px;
            border-top: 1px solid var(--cream-dark);
            background: var(--cream);
            border-radius: 0 0 24px 24px;
        }

        /* Utility */
        .danger-text { color: var(--error) !important; }
        .btn-sm { padding: 10px 14px; font-size: 13px; }

        /* Upgrade Notice */
        .upgrade-notice {
            background: linear-gradient(135deg, #FEF8E8 0%, #FDF3D7 100%);
            border-radius: 16px;
            padding: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
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
            background: var(--gold);
            color: var(--white);
            white-space: nowrap;
        }

        .upgrade-notice .btn:hover {
            background: #B8952F;
        }

        @media (max-width: 768px) {
            .top-nav-inner {
                flex-wrap: wrap;
                height: auto;
                padding: 16px 0;
                gap: 12px;
            }

            .tabs-nav-inner {
                gap: 0;
            }

            .tab-link {
                padding: 14px 12px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full-width {
                grid-column: span 1;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
            <a href="?id=<?= $eventId ?>&page=invitation" class="tab-link <?= $page === 'invitation' || $page === 'invitation-send' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
                Invitation
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
            <a href="?id=<?= $eventId ?>&page=budget" class="tab-link <?= $page === 'budget' ? 'active' : '' ?> <?= !$hasBudget ? 'premium' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Budget
            </a>
            <a href="?id=<?= $eventId ?>&page=toastmaster" class="tab-link <?= $page === 'toastmaster' ? 'active' : '' ?> <?= !$hasToastmaster ? 'premium' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path>
                </svg>
                Toastmaster
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
            include __DIR__ . '/pages/dashboard.php';
        }
        ?>
    </main>
</body>
</html>

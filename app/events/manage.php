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
        COALESCE(SUM(max_guests), 0) as total_guests,
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
            --cream-light: #F5F3F0;
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

        /* Event Sidebar */
        .event-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 280px;
            background: var(--white);
            border-right: 1px solid var(--cream-dark);
            display: flex;
            flex-direction: column;
            z-index: 150;
            overflow: hidden;
        }

        .sidebar-header {
            padding: 24px 20px;
            border-bottom: 1px solid var(--cream-dark);
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: var(--font-display);
            font-size: 20px;
            font-weight: 600;
            color: var(--charcoal);
            text-decoration: none;
        }

        .sidebar-logo svg {
            width: 22px;
            height: 22px;
            color: var(--sage);
        }

        .sidebar-logo span { color: var(--sage-dark); }

        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            padding: 12px;
        }

        .sidebar-nav::-webkit-scrollbar { width: 4px; }
        .sidebar-nav::-webkit-scrollbar-thumb { background: var(--cream-dark); border-radius: 2px; }

        .sidebar-app-nav { margin-bottom: 4px; }

        .sidebar-divider {
            height: 1px;
            background: var(--cream-dark);
            margin: 8px 0;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            color: var(--charcoal-light);
            text-decoration: none;
            font-size: 14px;
            font-weight: 400;
            border-radius: 10px;
            transition: all 0.2s var(--ease-out);
        }

        .sidebar-link svg { width: 18px; height: 18px; flex-shrink: 0; }

        .sidebar-link:hover {
            background: var(--cream-light);
            color: var(--charcoal);
        }

        .sidebar-link.active {
            background: var(--sage);
            color: var(--white);
            font-weight: 500;
        }

        .sidebar-link.premium { color: #B8B0A0; }

        .sidebar-link.premium::after {
            content: 'PRO';
            font-size: 9px;
            font-weight: 700;
            padding: 2px 5px;
            background: var(--gold);
            color: var(--white);
            border-radius: 4px;
            margin-left: auto;
        }

        .sidebar-link.premium.active { color: var(--white); }
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
            color: var(--charcoal-light);
        }

        .sidebar-group-header:hover { color: var(--charcoal); }

        .sidebar-group-icon svg { width: 16px; height: 16px; }
        .sidebar-group-label { flex: 1; text-align: left; }

        .sidebar-group-chevron {
            width: 14px;
            height: 14px;
            transition: transform 0.2s var(--ease-out);
        }

        .sidebar-group-header[aria-expanded="false"] .sidebar-group-chevron {
            transform: rotate(-90deg);
        }

        .sidebar-group-items {
            overflow: hidden;
            transition: max-height 0.25s var(--ease-out);
        }

        .sidebar-group-header[aria-expanded="false"] + .sidebar-group-items {
            max-height: 0 !important;
        }

        .sidebar-group-items .sidebar-link { padding-left: 40px; }

        .sidebar-settings-link { margin-top: 4px; }

        /* Sidebar Footer */
        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid var(--cream-dark);
        }

        .sidebar-plan-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px;
            background: var(--cream-light);
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            color: var(--charcoal);
            margin-bottom: 8px;
        }

        .sidebar-upgrade-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px;
            background: var(--sage);
            color: var(--white);
            text-decoration: none;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            transition: background 0.2s;
        }

        .sidebar-upgrade-btn:hover { background: var(--sage-dark); }
        .sidebar-upgrade-btn svg { width: 14px; height: 14px; }

        /* Desktop layout offset */
        .top-nav { margin-left: 280px; }
        .main-content { margin-left: 280px; }

        /* Mobile hamburger */
        .event-menu-toggle {
            display: none;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border: none;
            background: none;
            cursor: pointer;
            color: var(--charcoal);
            border-radius: 10px;
            -webkit-tap-highlight-color: transparent;
        }

        .event-menu-toggle:hover { background: var(--cream); }
        .event-menu-toggle svg { width: 22px; height: 22px; }

        /* Mobile Bottom Bar */
        .mobile-bottom-bar {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--white);
            border-top: 1px solid var(--cream-dark);
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
            color: var(--charcoal-light);
            -webkit-tap-highlight-color: transparent;
        }

        .bottom-bar-item.active { color: var(--sage-dark); }
        .bottom-bar-item svg { width: 22px; height: 22px; }
        .bottom-bar-item span {
            font-family: var(--font-body);
            font-size: 10px;
            font-weight: 500;
        }

        /* Bottom Sheet */
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
            background: var(--white);
            border-radius: 20px 20px 0 0;
            padding: 0 20px 20px;
            padding-bottom: calc(20px + env(safe-area-inset-bottom, 0px));
            transform: translateY(100%);
            transition: transform 0.3s var(--ease-out);
            max-height: 60vh;
            overflow-y: auto;
        }

        .bottom-sheet.open .bottom-sheet-content { transform: translateY(0); }

        .bottom-sheet-handle {
            width: 36px;
            height: 4px;
            background: var(--cream-dark);
            border-radius: 2px;
            margin: 12px auto 16px;
        }

        .bottom-sheet-title {
            font-family: var(--font-body);
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--charcoal-light);
            padding: 4px 0 8px;
        }

        .bottom-sheet-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 12px;
            color: var(--charcoal);
            text-decoration: none;
            font-size: 16px;
            border-radius: 12px;
            min-height: 48px;
        }

        .bottom-sheet-link:active { background: var(--cream-light); }

        .bottom-sheet-link.active {
            background: var(--sage);
            color: var(--white);
            font-weight: 500;
        }

        .bottom-sheet-link.premium { color: #B8B0A0; }
        .bottom-sheet-link.premium.active { color: var(--white); }

        .pro-badge {
            font-size: 9px;
            font-weight: 700;
            padding: 2px 6px;
            background: var(--gold);
            color: var(--white);
            border-radius: 4px;
        }

        .bottom-sheet-link.active .pro-badge { background: rgba(255,255,255,0.3); }

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
            .top-nav { margin-left: 0; }
            .main-content {
                margin-left: 0;
                padding-bottom: 80px;
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

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/event-sidebar.php'; ?>

    <!-- Top Navigation -->
    <nav class="top-nav">
        <div class="top-nav-inner">
            <div class="nav-left">
                <button type="button" class="event-menu-toggle" onclick="document.getElementById('eventSidebar').classList.toggle('open')">
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

    /* Close mobile sidebar on outside click */
    document.addEventListener('click', function(e) {
        var sidebar = document.getElementById('eventSidebar');
        if (sidebar && sidebar.classList.contains('open')) {
            var toggle = document.querySelector('.event-menu-toggle');
            if (!sidebar.contains(e.target) && (!toggle || !toggle.contains(e.target))) {
                sidebar.classList.remove('open');
            }
        }
    });
    </script>
</body>
</html>

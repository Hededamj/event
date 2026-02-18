<?php
/**
 * Vendor Dashboard Header
 * Layout shell for all vendor dashboard pages.
 * Nordic Design System — adapted from app-header.php for the vendor portal.
 */

// Start output buffering so POST handlers can redirect after this include
ob_start();

require_once __DIR__ . '/vendor-auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Require login for all vendor dashboard pages
requireVendorLogin();

// Get current vendor data
$currentVendor = getCurrentVendor();
$vendorId = getCurrentVendorId();

// Get flash message
$flash = getFlash();

// Page title (can be overridden before including this file)
$pageTitle = $pageTitle ?? 'Dashboard';

// Determine active page from URL
$currentPath = $_SERVER['REQUEST_URI'] ?? '';
$currentPath = parse_url($currentPath, PHP_URL_PATH);

// Count unread messages (from organizers/platform, not from the vendor)
$unreadMessageCount = 0;
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM booking_messages bm
        JOIN bookings b ON bm.booking_id = b.id
        WHERE b.vendor_id = ?
          AND bm.sender_type != 'vendor'
          AND bm.is_read = 0
    ");
    $stmt->execute([$vendorId]);
    $unreadMessageCount = (int) $stmt->fetchColumn();
} catch (Exception $e) {
    error_log("Failed to count unread messages: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="da" data-area="partner">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Leverandørportal - PartyParart</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/design-system.css">
    <link rel="stylesheet" href="/subcontractor/assets/css/subcontractor.css">
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
    <aside class="ds-sidebar" id="vendorSidebar">
        <div class="ds-sidebar-header">
            <a href="/subcontractor/dashboard/" class="ds-sidebar-logo ds-sidebar-logo-text">PartyParart</a>
            <div class="ds-sidebar-subtitle"><?= htmlspecialchars($currentVendor['company_name'] ?? 'Leverandor') ?></div>
        </div>

        <nav class="ds-sidebar-nav">
            <a href="/subcontractor/dashboard/" class="ds-nav-link <?= rtrim($currentPath, '/') === '/subcontractor/dashboard' || $currentPath === '/subcontractor/dashboard/index.php' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                Oversigt
            </a>

            <a href="/subcontractor/dashboard/profile.php" class="ds-nav-link <?= strpos($currentPath, 'profile.php') !== false ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
                Profil
            </a>

            <a href="/subcontractor/dashboard/services.php" class="ds-nav-link <?= strpos($currentPath, 'services.php') !== false ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                </svg>
                Ydelser
            </a>

            <a href="/subcontractor/dashboard/gallery.php" class="ds-nav-link <?= strpos($currentPath, 'gallery.php') !== false ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                Galleri
            </a>

            <a href="/subcontractor/dashboard/bookings.php" class="ds-nav-link <?= strpos($currentPath, 'bookings.php') !== false || strpos($currentPath, 'booking-detail.php') !== false ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                Bookinger
                <?php if ($unreadMessageCount > 0): ?>
                    <span class="ds-nav-badge"><?= $unreadMessageCount > 99 ? '99+' : $unreadMessageCount ?></span>
                <?php endif; ?>
            </a>

            <a href="/subcontractor/dashboard/calendar.php" class="ds-nav-link <?= strpos($currentPath, 'calendar.php') !== false ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path>
                </svg>
                Kalender
            </a>

            <a href="/subcontractor/dashboard/earnings.php" class="ds-nav-link <?= strpos($currentPath, 'earnings.php') !== false ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Indtjening
            </a>

            <a href="/subcontractor/dashboard/reviews.php" class="ds-nav-link <?= strpos($currentPath, 'reviews.php') !== false ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                </svg>
                Anmeldelser
            </a>
        </nav>

        <div class="ds-sidebar-footer">
            <a href="/subcontractor/dashboard/logout.php" class="ds-nav-link ds-nav-link-logout">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
                Log ud
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="ds-main">
        <div class="ds-content">
            <?php if ($flash): ?>
                <div class="flash-message flash-<?= htmlspecialchars($flash['type']) ?>">
                    <?php if ($flash['type'] === 'success'): ?>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    <?php elseif ($flash['type'] === 'error'): ?>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    <?php else: ?>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    <?php endif; ?>
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
            <?php endif; ?>

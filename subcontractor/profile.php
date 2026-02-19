<?php
/**
 * Vendor Public Profile Page
 * Public-facing page displaying a vendor's full profile:
 * hero, services, gallery, reviews, and contact CTA.
 * Accessible at /subcontractor/leverandor/{id}
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/review-service.php';

// ============================================================
// Load vendor
// ============================================================

$vendorId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($vendorId <= 0) {
    http_response_code(404);
    die('Leverandor ikke fundet.');
}

$db = getDB();

$stmt = $db->prepare("
    SELECT *
    FROM vendors
    WHERE id = ? AND status = 'approved' AND is_active = 1
    LIMIT 1
");
$stmt->execute([$vendorId]);
$vendor = $stmt->fetch();

if (!$vendor) {
    http_response_code(404);
    die('Leverandor ikke fundet.');
}

// Increment view count
try {
    $stmt = $db->prepare("UPDATE vendors SET view_count = view_count + 1 WHERE id = ?");
    $stmt->execute([$vendorId]);
} catch (Exception $e) {
    error_log("Failed to increment view_count for vendor $vendorId: " . $e->getMessage());
}

// ============================================================
// Load vendor categories
// ============================================================

$categories = [];
try {
    $stmt = $db->prepare("
        SELECT vc.name, vc.slug, vc.icon
        FROM vendor_category_links vcl
        JOIN vendor_categories vc ON vc.id = vcl.category_id
        WHERE vcl.vendor_id = ?
        ORDER BY vc.sort_order ASC
    ");
    $stmt->execute([$vendorId]);
    $categories = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Failed to load vendor categories: " . $e->getMessage());
}

// ============================================================
// Load services
// ============================================================

$services = [];
try {
    $stmt = $db->prepare("
        SELECT *
        FROM vendor_services
        WHERE vendor_id = ? AND is_active = 1
        ORDER BY sort_order ASC
    ");
    $stmt->execute([$vendorId]);
    $services = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Failed to load vendor services: " . $e->getMessage());
}

// ============================================================
// Load gallery
// ============================================================

$gallery = [];
try {
    $stmt = $db->prepare("
        SELECT *
        FROM vendor_gallery
        WHERE vendor_id = ?
        ORDER BY sort_order ASC
    ");
    $stmt->execute([$vendorId]);
    $gallery = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Failed to load vendor gallery: " . $e->getMessage());
}

// ============================================================
// Load reviews
// ============================================================

$reviews = getVendorReviews($vendorId, 10, 0);

// Rating distribution (1-5 stars)
$ratingDistribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
try {
    $stmt = $db->prepare("
        SELECT rating, COUNT(*) AS cnt
        FROM vendor_reviews
        WHERE vendor_id = ? AND is_visible = 1
        GROUP BY rating
    ");
    $stmt->execute([$vendorId]);
    while ($row = $stmt->fetch()) {
        $ratingDistribution[(int) $row['rating']] = (int) $row['cnt'];
    }
} catch (Exception $e) {
    error_log("Failed to load rating distribution: " . $e->getMessage());
}

$totalReviews = array_sum($ratingDistribution);

// ============================================================
// Active tab
// ============================================================

$activeTab = $_GET['tab'] ?? 'ydelser';
if (!in_array($activeTab, ['ydelser', 'galleri', 'anmeldelser'])) {
    $activeTab = 'ydelser';
}

// Price unit labels
$priceUnitLabels = [
    'fixed'      => 'fast pris',
    'per_person'  => 'pr. person',
    'per_hour'    => 'pr. time',
];

// Set page title
$pageTitle = escape($vendor['company_name']);

require_once __DIR__ . '/includes/marketplace-header.php';
?>

<style>
/* ============================================
   Marketplace base styles (shared by public pages)
   ============================================ */
/* Base styles provided by design-system.css via marketplace-header.php */

/* ============================================
   Marketplace header styles
   ============================================ */
.mp-header {
    position: sticky;
    top: 0;
    z-index: 100;
    background: var(--white);
    border-bottom: 1px solid var(--border);
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
}

.mp-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 24px;
    display: flex;
    align-items: center;
    height: 64px;
    gap: 24px;
}

.mp-logo {
    font-family: var(--font-display);
    font-size: 22px;
    font-weight: 500;
    color: var(--text);
    flex-shrink: 0;
}

.mp-logo span { color: var(--accent-dark); }

.mp-nav {
    display: flex;
    align-items: center;
    gap: 4px;
    flex: 1;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}

.mp-nav::-webkit-scrollbar { display: none; }

.mp-nav-link {
    padding: 6px 12px;
    font-size: 13px;
    font-weight: 500;
    color: var(--text-secondary);
    border-radius: 8px;
    white-space: nowrap;
    transition: all 0.2s var(--ease-out);
}

.mp-nav-link:hover {
    background: var(--surface);
    color: var(--text);
}

.mp-header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.mp-mobile-toggle {
    display: none;
    width: 40px;
    height: 40px;
    background: none;
    border: none;
    cursor: pointer;
    border-radius: 8px;
    align-items: center;
    justify-content: center;
    color: var(--text);
    transition: background 0.2s;
}

.mp-mobile-toggle:hover { background: var(--surface); }
.mp-mobile-toggle svg { width: 24px; height: 24px; }

.mp-mobile-nav {
    display: none;
    position: fixed;
    top: 64px;
    left: 0;
    right: 0;
    background: var(--white);
    border-bottom: 1px solid var(--border);
    z-index: 99;
    padding: 12px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
}

.mp-mobile-nav.open { display: block; }

.mp-mobile-nav-link {
    display: block;
    padding: 10px 14px;
    font-size: 14px;
    font-weight: 500;
    color: var(--text);
    border-radius: 8px;
    transition: background 0.2s;
}

.mp-mobile-nav-link:hover { background: var(--surface); }

.mp-mobile-nav-divider {
    height: 1px;
    background: var(--border);
    margin: 8px 0;
}

.mp-mobile-overlay {
    display: none;
    position: fixed;
    inset: 0;
    top: 64px;
    background: rgba(0,0,0,0.3);
    z-index: 98;
}

.mp-mobile-overlay.open { display: block; }

.mp-main {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 24px;
}

/* Shared button styles for marketplace */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 22px;
    font-family: var(--font-body);
    font-size: 14px;
    font-weight: 600;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.25s var(--ease-out);
    text-decoration: none;
    white-space: nowrap;
    line-height: 1.4;
}

.btn svg { width: 18px; height: 18px; flex-shrink: 0; }

.btn-primary {
    background: var(--accent);
    color: var(--white);
}

.btn-primary:hover {
    background: var(--accent-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(122, 139, 114, 0.3);
}

.btn-secondary {
    background: var(--white);
    color: var(--text);
    border: 2px solid var(--border);
}

.btn-secondary:hover {
    border-color: var(--accent);
    background: var(--surface);
}

.btn-sm {
    padding: 8px 16px;
    font-size: 13px;
    border-radius: 8px;
}

.btn-lg {
    padding: 16px 28px;
    font-size: 16px;
    border-radius: 12px;
}

.btn-outline {
    background: transparent;
    color: var(--accent-dark);
    border: 2px solid var(--accent);
}

.btn-outline:hover {
    background: var(--accent);
    color: var(--white);
}

/* ============================================
   Profile Hero
   ============================================ */
.profile-hero {
    position: relative;
    height: 300px;
    border-radius: 20px;
    overflow: hidden;
    margin-top: 24px;
}

.profile-hero-cover {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-hero-gradient {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 60%, #5C6E56 100%);
}

.profile-hero-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.55) 0%, rgba(0,0,0,0.1) 50%, transparent 100%);
}

.profile-hero-content {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 32px;
    display: flex;
    align-items: flex-end;
    gap: 24px;
}

.profile-logo {
    width: 90px;
    height: 90px;
    border-radius: 16px;
    border: 3px solid var(--white);
    object-fit: cover;
    background: var(--white);
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.profile-logo-placeholder {
    width: 90px;
    height: 90px;
    border-radius: 16px;
    border: 3px solid var(--white);
    background: var(--accent-light);
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: var(--font-display);
    font-size: 36px;
    font-weight: 600;
    color: var(--white);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.profile-hero-info {
    flex: 1;
    min-width: 0;
}

.profile-company-name {
    font-family: var(--font-display);
    font-size: 32px;
    font-weight: 500;
    color: var(--white);
    line-height: 1.2;
    margin-bottom: 6px;
    text-shadow: 0 1px 4px rgba(0,0,0,0.2);
}

.profile-hero-meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    color: rgba(255,255,255,0.9);
    font-size: 14px;
}

.profile-hero-meta svg {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
}

.profile-hero-stars {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.profile-hero-stars .star {
    width: 18px;
    height: 18px;
}

.star-filled { color: var(--warning); fill: var(--warning); }
.star-empty { color: rgba(255,255,255,0.4); fill: none; stroke: rgba(255,255,255,0.4); }

.profile-hero-location {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.badge-nationwide {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: rgba(255,255,255,0.2);
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    backdrop-filter: blur(4px);
    color: var(--white);
}

/* ============================================
   Quick Info Bar
   ============================================ */
.profile-info-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 20px;
    padding: 18px 24px;
    background: var(--white);
    border-radius: 14px;
    margin-top: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}

.info-bar-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: var(--text-secondary);
}

.info-bar-item svg {
    width: 18px;
    height: 18px;
    color: var(--accent-dark);
    flex-shrink: 0;
}

.info-bar-item a {
    color: var(--accent-dark);
    font-weight: 600;
    transition: color 0.2s;
}

.info-bar-item a:hover {
    color: var(--text);
}

.info-bar-categories {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.info-bar-category {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    background: var(--surface);
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-secondary);
    transition: all 0.2s;
}

.info-bar-category:hover {
    background: var(--accent-light);
    color: var(--text);
}

.info-bar-divider {
    width: 1px;
    height: 24px;
    background: var(--border);
    flex-shrink: 0;
}

/* ============================================
   Content Layout
   ============================================ */
.profile-layout {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 24px;
    margin-top: 24px;
    margin-bottom: 60px;
    align-items: start;
}

/* ============================================
   Tabs
   ============================================ */
.profile-tabs {
    display: flex;
    gap: 0;
    border-bottom: 2px solid var(--border);
    margin-bottom: 24px;
}

.profile-tab {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 14px 22px;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-secondary);
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: all 0.25s var(--ease-out);
}

.profile-tab:hover {
    color: var(--text);
}

.profile-tab.active {
    color: var(--accent-dark);
    border-bottom-color: var(--accent-dark);
}

.profile-tab svg {
    width: 18px;
    height: 18px;
}

.profile-tab .tab-count {
    background: var(--surface);
    padding: 1px 8px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-secondary);
}

.profile-tab.active .tab-count {
    background: rgba(168, 181, 160, 0.2);
    color: var(--accent-dark);
}

/* ============================================
   Tab Content
   ============================================ */
.tab-panel {
    display: none;
}

.tab-panel.active {
    display: block;
}

/* ============================================
   Services (Ydelser tab)
   ============================================ */
.service-card {
    background: var(--white);
    border-radius: 14px;
    padding: 24px;
    margin-bottom: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    transition: transform 0.2s var(--ease-out), box-shadow 0.2s var(--ease-out);
}

.service-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
}

.service-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 10px;
}

.service-title {
    font-family: var(--font-display);
    font-size: 20px;
    font-weight: 500;
    color: var(--text);
    line-height: 1.3;
}

.service-price {
    text-align: right;
    flex-shrink: 0;
}

.service-price-value {
    font-family: var(--font-display);
    font-size: 22px;
    font-weight: 500;
    color: var(--text);
    line-height: 1;
}

.service-price-unit {
    font-size: 12px;
    color: var(--text-secondary);
    margin-top: 2px;
}

.service-description {
    color: var(--text-secondary);
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 14px;
}

.service-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.service-duration {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--text-secondary);
}

.service-duration svg {
    width: 16px;
    height: 16px;
    color: var(--accent-dark);
}

/* ============================================
   Gallery (Galleri tab)
   ============================================ */
.profile-gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 14px;
}

.profile-gallery-item {
    position: relative;
    aspect-ratio: 4/3;
    border-radius: 12px;
    overflow: hidden;
    cursor: pointer;
    background: var(--border);
}

.profile-gallery-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s var(--ease-out);
}

.profile-gallery-item:hover img {
    transform: scale(1.05);
}

.profile-gallery-item-caption {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 20px 12px 10px;
    background: linear-gradient(to top, rgba(0,0,0,0.6), transparent);
    color: var(--white);
    font-size: 13px;
    line-height: 1.3;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.25s var(--ease-out);
}

.profile-gallery-item:hover .profile-gallery-item-caption {
    opacity: 1;
}

/* Lightbox */
.lightbox-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.9);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 40px;
    cursor: pointer;
}

.lightbox-overlay.active {
    display: flex;
}

.lightbox-img {
    max-width: 100%;
    max-height: 90vh;
    object-fit: contain;
    border-radius: 8px;
    cursor: default;
}

.lightbox-caption {
    position: absolute;
    bottom: 16px;
    left: 50%;
    transform: translateX(-50%);
    color: var(--white);
    font-size: 14px;
    background: rgba(0,0,0,0.5);
    padding: 8px 20px;
    border-radius: 8px;
    max-width: 80%;
    text-align: center;
}

.lightbox-close {
    position: absolute;
    top: 20px;
    right: 20px;
    width: 44px;
    height: 44px;
    background: rgba(255,255,255,0.15);
    border: none;
    border-radius: 50%;
    color: var(--white);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
}

.lightbox-close:hover {
    background: rgba(255,255,255,0.3);
}

.lightbox-close svg {
    width: 24px;
    height: 24px;
}

.lightbox-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 48px;
    height: 48px;
    background: rgba(255,255,255,0.15);
    border: none;
    border-radius: 50%;
    color: var(--white);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
}

.lightbox-nav:hover {
    background: rgba(255,255,255,0.3);
}

.lightbox-nav svg {
    width: 24px;
    height: 24px;
}

.lightbox-prev { left: 20px; }
.lightbox-next { right: 20px; }

/* ============================================
   Reviews (Anmeldelser tab)
   ============================================ */
.reviews-header {
    display: flex;
    gap: 40px;
    margin-bottom: 32px;
    align-items: flex-start;
}

.reviews-score {
    text-align: center;
    flex-shrink: 0;
}

.reviews-score-value {
    font-family: var(--font-display);
    font-size: 48px;
    font-weight: 500;
    color: var(--text);
    line-height: 1;
}

.reviews-score-stars {
    display: flex;
    justify-content: center;
    gap: 2px;
    margin: 8px 0 4px;
}

.reviews-score-stars svg {
    width: 20px;
    height: 20px;
}

.reviews-score-count {
    font-size: 13px;
    color: var(--text-secondary);
}

.reviews-distribution {
    flex: 1;
    max-width: 360px;
}

.distribution-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 6px;
}

.distribution-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-secondary);
    width: 56px;
    text-align: right;
    flex-shrink: 0;
}

.distribution-bar-bg {
    flex: 1;
    height: 10px;
    background: var(--border);
    border-radius: 5px;
    overflow: hidden;
}

.distribution-bar-fill {
    height: 100%;
    background: var(--warning);
    border-radius: 5px;
    transition: width 0.5s var(--ease-out);
}

.distribution-count {
    font-size: 12px;
    color: var(--text-secondary);
    width: 30px;
    text-align: left;
    flex-shrink: 0;
}

/* Review cards */
.review-card {
    background: var(--white);
    border-radius: 14px;
    padding: 24px;
    margin-bottom: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}

.review-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 10px;
}

.review-meta {
    flex: 1;
}

.review-author {
    font-weight: 600;
    font-size: 15px;
    color: var(--text);
}

.review-date {
    font-size: 13px;
    color: var(--text-secondary);
    margin-top: 2px;
}

.review-stars {
    display: flex;
    gap: 2px;
    flex-shrink: 0;
}

.review-stars svg {
    width: 16px;
    height: 16px;
}

.review-title {
    font-family: var(--font-display);
    font-size: 18px;
    font-weight: 500;
    color: var(--text);
    margin-bottom: 6px;
}

.review-text {
    color: var(--text-secondary);
    font-size: 14px;
    line-height: 1.6;
}

.review-response {
    margin-top: 16px;
    padding: 16px;
    background: var(--surface);
    border-radius: 10px;
    border-left: 3px solid var(--accent);
}

.review-response-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--accent-dark);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 6px;
}

.review-response-text {
    color: var(--text-secondary);
    font-size: 14px;
    line-height: 1.6;
}

.review-response-date {
    font-size: 12px;
    color: var(--text-secondary);
    margin-top: 6px;
    opacity: 0.7;
}

/* ============================================
   Sidebar CTA
   ============================================ */
.profile-sidebar {
    position: sticky;
    top: 88px;
}

.sidebar-cta-card {
    background: var(--white);
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 6px 24px rgba(0,0,0,0.06);
}

.sidebar-cta-title {
    font-family: var(--font-display);
    font-size: 22px;
    font-weight: 500;
    color: var(--text);
    margin-bottom: 6px;
}

.sidebar-cta-text {
    font-size: 14px;
    color: var(--text-secondary);
    margin-bottom: 20px;
    line-height: 1.5;
}

.sidebar-cta-card .btn {
    width: 100%;
    justify-content: center;
}

.sidebar-cta-stats {
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.sidebar-stat {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    color: var(--text-secondary);
}

.sidebar-stat svg {
    width: 16px;
    height: 16px;
    color: var(--accent-dark);
    flex-shrink: 0;
}

/* ============================================
   Description section
   ============================================ */
.vendor-description {
    background: var(--white);
    border-radius: 14px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}

.vendor-description h3 {
    font-family: var(--font-display);
    font-size: 20px;
    font-weight: 500;
    color: var(--text);
    margin-bottom: 12px;
}

.vendor-description p {
    color: var(--text-secondary);
    font-size: 14px;
    line-height: 1.7;
    white-space: pre-line;
}

/* ============================================
   Empty states
   ============================================ */
.tab-empty {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-secondary);
}

.tab-empty svg {
    width: 48px;
    height: 48px;
    color: var(--border);
    margin-bottom: 12px;
}

.tab-empty h3 {
    font-family: var(--font-display);
    font-size: 20px;
    font-weight: 500;
    color: var(--text);
    margin-bottom: 4px;
}

.tab-empty p {
    font-size: 14px;
}

/* ============================================
   Footer
   ============================================ */
.mp-footer {
    border-top: 1px solid var(--border);
    padding: 24px;
    text-align: center;
    font-size: 13px;
    color: rgba(44,44,44,0.4);
    margin-top: 20px;
}

.mp-footer a {
    color: var(--accent-dark);
    transition: color 0.2s;
}

.mp-footer a:hover { color: var(--text); }

/* ============================================
   Responsive: 1024px
   ============================================ */
@media (max-width: 1024px) {
    .profile-layout {
        grid-template-columns: 1fr;
    }

    .profile-sidebar {
        position: static;
    }

    .mp-nav { display: none; }
    .mp-header-actions { display: none; }
    .mp-mobile-toggle { display: flex; }
}

/* ============================================
   Responsive: 768px
   ============================================ */
@media (max-width: 768px) {
    .profile-hero {
        height: 220px;
        border-radius: 14px;
        margin-top: 16px;
    }

    .profile-hero-content {
        padding: 20px;
        gap: 16px;
    }

    .profile-logo, .profile-logo-placeholder {
        width: 64px;
        height: 64px;
        border-radius: 12px;
        font-size: 26px;
    }

    .profile-company-name {
        font-size: 24px;
    }

    .profile-hero-meta {
        gap: 10px;
        font-size: 13px;
    }

    .profile-info-bar {
        gap: 14px;
        padding: 14px 18px;
    }

    .info-bar-divider { display: none; }

    .profile-tabs {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .profile-tab {
        padding: 12px 16px;
        font-size: 13px;
    }

    .reviews-header {
        flex-direction: column;
        gap: 20px;
    }

    .reviews-distribution {
        max-width: 100%;
    }

    .profile-gallery-grid {
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 10px;
    }

    .service-card-header {
        flex-direction: column;
        gap: 8px;
    }

    .service-price {
        text-align: left;
    }

    .lightbox-nav { display: none; }
}

/* ============================================
   Responsive: 480px
   ============================================ */
@media (max-width: 480px) {
    .mp-main {
        padding: 0 12px;
    }

    .profile-hero {
        height: 180px;
        border-radius: 12px;
    }

    .profile-hero-content {
        padding: 16px;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    .profile-company-name {
        font-size: 20px;
    }

    .profile-gallery-grid {
        grid-template-columns: 1fr 1fr;
    }

    .service-footer {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    .service-footer .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<?php
// ============================================================
// Helper: render star SVGs
// ============================================================
function renderStars(float $rating, string $class = ''): string {
    $html = '';
    $fullStars = (int) floor($rating);
    $halfStar = ($rating - $fullStars) >= 0.3;
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $fullStars) {
            $html .= '<svg class="star star-filled ' . $class . '" fill="currentColor" viewBox="0 0 24 24"><path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>';
        } elseif ($halfStar && $i === $fullStars + 1) {
            $html .= '<svg class="star star-filled ' . $class . '" fill="currentColor" viewBox="0 0 24 24"><path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" opacity="0.5"/></svg>';
            $halfStar = false;
        } else {
            $html .= '<svg class="star star-empty ' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>';
        }
    }
    return $html;
}
?>

<!-- ============================================================ -->
<!-- HERO SECTION -->
<!-- ============================================================ -->
<div class="profile-hero">
    <?php if (!empty($vendor['cover_filename'])): ?>
        <img class="profile-hero-cover" src="/uploads/vendors/<?= htmlspecialchars($vendor['cover_filename']) ?>" alt="<?= htmlspecialchars($vendor['company_name']) ?>">
    <?php else: ?>
        <div class="profile-hero-gradient"></div>
    <?php endif; ?>
    <div class="profile-hero-overlay"></div>
    <div class="profile-hero-content">
        <?php if (!empty($vendor['logo_filename'])): ?>
            <img class="profile-logo" src="/uploads/vendors/<?= htmlspecialchars($vendor['logo_filename']) ?>" alt="<?= htmlspecialchars($vendor['company_name']) ?> logo">
        <?php else: ?>
            <div class="profile-logo-placeholder">
                <?= mb_strtoupper(mb_substr($vendor['company_name'], 0, 1)) ?>
            </div>
        <?php endif; ?>
        <div class="profile-hero-info">
            <h1 class="profile-company-name"><?= htmlspecialchars($vendor['company_name']) ?></h1>
            <div class="profile-hero-meta">
                <?php if ((float) $vendor['avg_rating'] > 0): ?>
                    <span class="profile-hero-stars">
                        <?= renderStars((float) $vendor['avg_rating']) ?>
                        <span style="margin-left: 4px;"><?= number_format((float) $vendor['avg_rating'], 1, ',', '.') ?></span>
                        <span style="opacity: 0.7;">(<?= (int) $vendor['review_count'] ?> <?= (int) $vendor['review_count'] === 1 ? 'anmeldelse' : 'anmeldelser' ?>)</span>
                    </span>
                <?php else: ?>
                    <span style="opacity: 0.7;">Ingen anmeldelser endnu</span>
                <?php endif; ?>
                <?php if (!empty($vendor['city'])): ?>
                    <span class="profile-hero-location">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <?= htmlspecialchars($vendor['city']) ?>
                    </span>
                <?php endif; ?>
                <?php if ($vendor['nationwide']): ?>
                    <span class="badge-nationwide">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:14px;height:14px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Landsdekkende
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- QUICK INFO BAR -->
<!-- ============================================================ -->
<div class="profile-info-bar">
    <?php if (!empty($categories)): ?>
        <div class="info-bar-categories">
            <?php foreach ($categories as $cat): ?>
                <a href="/subcontractor/kategori/<?= htmlspecialchars($cat['slug']) ?>" class="info-bar-category">
                    <?= $cat['icon'] ?> <?= htmlspecialchars($cat['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="info-bar-divider"></div>
    <?php endif; ?>
    <?php if (!empty($vendor['phone'])): ?>
        <div class="info-bar-item">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
            <a href="tel:<?= htmlspecialchars($vendor['phone']) ?>"><?= htmlspecialchars($vendor['phone']) ?></a>
        </div>
        <div class="info-bar-divider"></div>
    <?php endif; ?>
    <?php if (!empty($vendor['website'])): ?>
        <div class="info-bar-item">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
            <a href="<?= htmlspecialchars($vendor['website']) ?>" target="_blank" rel="noopener noreferrer">Besog hjemmeside</a>
        </div>
        <div class="info-bar-divider"></div>
    <?php endif; ?>
    <div class="info-bar-item">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Svarer typisk inden for 24 timer
    </div>
</div>

<!-- ============================================================ -->
<!-- CONTENT LAYOUT: Main + Sidebar -->
<!-- ============================================================ -->
<div class="profile-layout">
    <div class="profile-content">

        <?php if (!empty($vendor['description'])): ?>
            <div class="vendor-description">
                <h3>Om <?= htmlspecialchars($vendor['company_name']) ?></h3>
                <p><?= nl2br(htmlspecialchars($vendor['description'])) ?></p>
            </div>
        <?php endif; ?>

        <!-- Tabs Navigation -->
        <nav class="profile-tabs">
            <a href="?id=<?= $vendorId ?>&tab=ydelser" class="profile-tab <?= $activeTab === 'ydelser' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Ydelser
                <span class="tab-count"><?= count($services) ?></span>
            </a>
            <a href="?id=<?= $vendorId ?>&tab=galleri" class="profile-tab <?= $activeTab === 'galleri' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Galleri
                <span class="tab-count"><?= count($gallery) ?></span>
            </a>
            <a href="?id=<?= $vendorId ?>&tab=anmeldelser" class="profile-tab <?= $activeTab === 'anmeldelser' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                Anmeldelser
                <span class="tab-count"><?= (int) $vendor['review_count'] ?></span>
            </a>
        </nav>

        <!-- ============================================ -->
        <!-- TAB: Ydelser -->
        <!-- ============================================ -->
        <div class="tab-panel <?= $activeTab === 'ydelser' ? 'active' : '' ?>">
            <?php if (empty($services)): ?>
                <div class="tab-empty">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                    <h3>Ingen ydelser listet endnu</h3>
                    <p>Denne leverandor har ikke tilfojet ydelser endnu.</p>
                </div>
            <?php else: ?>
                <?php foreach ($services as $service): ?>
                    <div class="service-card">
                        <div class="service-card-header">
                            <h3 class="service-title"><?= htmlspecialchars($service['title']) ?></h3>
                            <div class="service-price">
                                <div class="service-price-value">
                                    <?php if (!empty($service['price_to']) && (float) $service['price_to'] > 0): ?>
                                        <?= number_format((float) $service['price_from'], 0, ',', '.') ?> - <?= number_format((float) $service['price_to'], 0, ',', '.') ?> kr
                                    <?php else: ?>
                                        Fra <?= number_format((float) $service['price_from'], 0, ',', '.') ?> kr
                                    <?php endif; ?>
                                </div>
                                <div class="service-price-unit"><?= htmlspecialchars($priceUnitLabels[$service['price_unit']] ?? 'fast pris') ?></div>
                            </div>
                        </div>
                        <?php if (!empty($service['description'])): ?>
                            <p class="service-description"><?= nl2br(htmlspecialchars($service['description'])) ?></p>
                        <?php endif; ?>
                        <div class="service-footer">
                            <?php if (!empty($service['duration_hours']) && (float) $service['duration_hours'] > 0): ?>
                                <span class="service-duration">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <?= rtrim(rtrim(number_format((float) $service['duration_hours'], 1, ',', '.'), '0'), ',') ?> timer
                                </span>
                            <?php else: ?>
                                <span></span>
                            <?php endif; ?>
                            <a href="/subcontractor/book.php?vendor_id=<?= $vendorId ?>&service_id=<?= (int) $service['id'] ?>" class="btn btn-primary btn-sm">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                Send foresporgsel
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ============================================ -->
        <!-- TAB: Galleri -->
        <!-- ============================================ -->
        <div class="tab-panel <?= $activeTab === 'galleri' ? 'active' : '' ?>">
            <?php if (empty($gallery)): ?>
                <div class="tab-empty">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <h3>Ingen billeder endnu</h3>
                    <p>Denne leverandor har ikke tilfojet billeder til sit galleri endnu.</p>
                </div>
            <?php else: ?>
                <div class="profile-gallery-grid">
                    <?php foreach ($gallery as $i => $photo): ?>
                        <div class="profile-gallery-item" onclick="openLightbox(<?= $i ?>)">
                            <img
                                src="/uploads/vendors/gallery/<?= htmlspecialchars($photo['filename']) ?>"
                                alt="<?= htmlspecialchars($photo['caption'] ?: $photo['original_name']) ?>"
                                loading="lazy"
                            >
                            <?php if (!empty($photo['caption'])): ?>
                                <div class="profile-gallery-item-caption"><?= htmlspecialchars($photo['caption']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Lightbox -->
                <div class="lightbox-overlay" id="lightbox">
                    <button class="lightbox-close" onclick="closeLightbox()">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                    <?php if (count($gallery) > 1): ?>
                        <button class="lightbox-nav lightbox-prev" onclick="navigateLightbox(-1); event.stopPropagation();">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <button class="lightbox-nav lightbox-next" onclick="navigateLightbox(1); event.stopPropagation();">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    <?php endif; ?>
                    <img class="lightbox-img" id="lightboxImg" src="" alt="" onclick="event.stopPropagation();">
                    <div class="lightbox-caption" id="lightboxCaption"></div>
                </div>
            <?php endif; ?>
        </div>

        <!-- ============================================ -->
        <!-- TAB: Anmeldelser -->
        <!-- ============================================ -->
        <div class="tab-panel <?= $activeTab === 'anmeldelser' ? 'active' : '' ?>">
            <?php if (empty($reviews)): ?>
                <div class="tab-empty">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                    <h3>Ingen anmeldelser endnu</h3>
                    <p>Denne leverandor har ikke modtaget anmeldelser endnu.</p>
                </div>
            <?php else: ?>
                <!-- Rating summary + distribution -->
                <div class="reviews-header">
                    <div class="reviews-score">
                        <div class="reviews-score-value"><?= number_format((float) $vendor['avg_rating'], 1, ',', '.') ?></div>
                        <div class="reviews-score-stars">
                            <?= renderStars((float) $vendor['avg_rating']) ?>
                        </div>
                        <div class="reviews-score-count"><?= $totalReviews ?> <?= $totalReviews === 1 ? 'anmeldelse' : 'anmeldelser' ?></div>
                    </div>
                    <div class="reviews-distribution">
                        <?php for ($star = 5; $star >= 1; $star--): ?>
                            <?php $pct = $totalReviews > 0 ? round(($ratingDistribution[$star] / $totalReviews) * 100) : 0; ?>
                            <div class="distribution-row">
                                <span class="distribution-label"><?= $star ?> stjerne<?= $star > 1 ? 'r' : '' ?></span>
                                <div class="distribution-bar-bg">
                                    <div class="distribution-bar-fill" style="width: <?= $pct ?>%;"></div>
                                </div>
                                <span class="distribution-count"><?= $ratingDistribution[$star] ?></span>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Individual reviews -->
                <?php foreach ($reviews as $review): ?>
                    <div class="review-card">
                        <div class="review-card-header">
                            <div class="review-meta">
                                <div class="review-author"><?= htmlspecialchars($review['reviewer_name']) ?></div>
                                <div class="review-date"><?= formatDate($review['created_at']) ?></div>
                            </div>
                            <div class="review-stars">
                                <?= renderStars((int) $review['rating']) ?>
                            </div>
                        </div>
                        <?php if (!empty($review['title'])): ?>
                            <h4 class="review-title"><?= htmlspecialchars($review['title']) ?></h4>
                        <?php endif; ?>
                        <?php if (!empty($review['review_text'])): ?>
                            <p class="review-text"><?= nl2br(htmlspecialchars($review['review_text'])) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($review['vendor_response'])): ?>
                            <div class="review-response">
                                <div class="review-response-label">Svar fra <?= htmlspecialchars($vendor['company_name']) ?></div>
                                <div class="review-response-text"><?= nl2br(htmlspecialchars($review['vendor_response'])) ?></div>
                                <?php if (!empty($review['vendor_responded_at'])): ?>
                                    <div class="review-response-date"><?= formatDate($review['vendor_responded_at']) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

    <!-- ============================================ -->
    <!-- SIDEBAR -->
    <!-- ============================================ -->
    <aside class="profile-sidebar">
        <div class="sidebar-cta-card">
            <h3 class="sidebar-cta-title">Kontakt denne leverandor</h3>
            <p class="sidebar-cta-text">Intresseret i <?= htmlspecialchars($vendor['company_name']) ?>? Send en foresporgsel for at modtage et tilbud.</p>
            <a href="/subcontractor/book.php?vendor_id=<?= $vendorId ?>" class="btn btn-primary btn-lg">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                Send foresporgsel
            </a>
            <div class="sidebar-cta-stats">
                <?php if ((float) $vendor['avg_rating'] > 0): ?>
                    <div class="sidebar-stat">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                        <?= number_format((float) $vendor['avg_rating'], 1, ',', '.') ?> gennemsnit af <?= (int) $vendor['review_count'] ?> anmeldelser
                    </div>
                <?php endif; ?>
                <?php if ((int) $vendor['booking_count'] > 0): ?>
                    <div class="sidebar-stat">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <?= (int) $vendor['booking_count'] ?> bookinger gennemfort
                    </div>
                <?php endif; ?>
                <div class="sidebar-stat">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Svarer typisk inden for 24 timer
                </div>
                <div class="sidebar-stat">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    Depositum-garanti via PartyParart
                </div>
            </div>
        </div>
    </aside>
</div>

<!-- ============================================================ -->
<!-- LIGHTBOX SCRIPT -->
<!-- ============================================================ -->
<?php if (!empty($gallery)): ?>
<script>
(function() {
    var galleryData = <?= json_encode(array_map(function($p) {
        return [
            'src' => '/uploads/vendors/gallery/' . $p['filename'],
            'caption' => $p['caption'] ?? ''
        ];
    }, $gallery), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    var currentIndex = 0;
    var lightbox = document.getElementById('lightbox');
    var lightboxImg = document.getElementById('lightboxImg');
    var lightboxCaption = document.getElementById('lightboxCaption');

    window.openLightbox = function(index) {
        currentIndex = index;
        showImage();
        lightbox.classList.add('active');
        document.body.style.overflow = 'hidden';
    };

    window.closeLightbox = function() {
        lightbox.classList.remove('active');
        document.body.style.overflow = '';
    };

    window.navigateLightbox = function(direction) {
        currentIndex += direction;
        if (currentIndex < 0) currentIndex = galleryData.length - 1;
        if (currentIndex >= galleryData.length) currentIndex = 0;
        showImage();
    };

    function showImage() {
        var item = galleryData[currentIndex];
        lightboxImg.src = item.src;
        lightboxImg.alt = item.caption || '';
        if (item.caption) {
            lightboxCaption.textContent = item.caption;
            lightboxCaption.style.display = 'block';
        } else {
            lightboxCaption.style.display = 'none';
        }
    }

    // Close lightbox on overlay click
    lightbox.addEventListener('click', function(e) {
        if (e.target === lightbox) {
            closeLightbox();
        }
    });

    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (!lightbox.classList.contains('active')) return;
        if (e.key === 'Escape') closeLightbox();
        if (e.key === 'ArrowLeft') navigateLightbox(-1);
        if (e.key === 'ArrowRight') navigateLightbox(1);
    });
})();
</script>
<?php endif; ?>

<!-- ============================================================ -->
<!-- MOBILE NAV SCRIPT -->
<!-- ============================================================ -->
<script>
(function() {
    var toggle = document.getElementById('mpMobileToggle');
    var nav = document.getElementById('mpMobileNav');
    var overlay = document.getElementById('mpMobileOverlay');

    if (!toggle || !nav || !overlay) return;

    toggle.addEventListener('click', function() {
        nav.classList.toggle('open');
        overlay.classList.toggle('open');
    });

    overlay.addEventListener('click', function() {
        nav.classList.remove('open');
        overlay.classList.remove('open');
    });
})();
</script>

    </main>

    <!-- Simple public footer -->
    <footer class="mp-footer">
        <p>&copy; <?= date('Y') ?> PartyParart &mdash; <a href="/subcontractor/">Find leverandorer</a> &middot; <a href="/subcontractor/dashboard/register.php">Bliv leverandor</a></p>
    </footer>
</body>
</html>

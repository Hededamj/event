<?php
/**
 * Marketplace Landing / Browse Page
 * Public page for organizers to browse and find vendors.
 * No login required.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDB();

// ============================================
// Gather filter parameters from $_GET
// ============================================
$search    = isset($_GET['search']) ? trim($_GET['search']) : '';
$category  = isset($_GET['category']) ? trim($_GET['category']) : '';
$city      = isset($_GET['city']) ? trim($_GET['city']) : '';
$priceMin  = isset($_GET['price_min']) && $_GET['price_min'] !== '' ? (int) $_GET['price_min'] : null;
$priceMax  = isset($_GET['price_max']) && $_GET['price_max'] !== '' ? (int) $_GET['price_max'] : null;
$rating    = isset($_GET['rating']) && $_GET['rating'] !== '' ? (int) $_GET['rating'] : null;
$page      = max(1, (int) ($_GET['page'] ?? 1));
$perPage   = 12;
$offset    = ($page - 1) * $perPage;

// ============================================
// Fetch categories with vendor counts
// ============================================
$categories = [];
try {
    $catStmt = $db->query("
        SELECT vc.id, vc.name, vc.slug, vc.icon, vc.description,
               COUNT(DISTINCT vcl.vendor_id) AS vendor_count
        FROM vendor_categories vc
        LEFT JOIN vendor_category_links vcl ON vcl.category_id = vc.id
        LEFT JOIN vendors v ON v.id = vcl.vendor_id AND v.status = 'approved' AND v.is_active = 1
        WHERE vc.is_active = 1
        GROUP BY vc.id
        ORDER BY vc.sort_order ASC
    ");
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Marketplace: failed to fetch categories: " . $e->getMessage());
}

// ============================================
// Build vendor query with filters
// ============================================
$whereClauses = ["v.status = 'approved'", "v.is_active = 1"];
$params = [];

if ($search !== '') {
    $whereClauses[] = "(v.company_name LIKE ? OR v.short_description LIKE ? OR v.description LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($category !== '') {
    $whereClauses[] = "vc_filter.slug = ?";
    $params[] = $category;
}

if ($city !== '') {
    $whereClauses[] = "v.city LIKE ?";
    $params[] = '%' . $city . '%';
}

if ($rating !== null && $rating >= 1 && $rating <= 5) {
    $whereClauses[] = "v.avg_rating >= ?";
    $params[] = $rating;
}

$whereSQL = implode(' AND ', $whereClauses);

// Join for category filter
$categoryJoin = '';
if ($category !== '') {
    $categoryJoin = "
        INNER JOIN vendor_category_links vcl_filter ON vcl_filter.vendor_id = v.id
        INNER JOIN vendor_categories vc_filter ON vc_filter.id = vcl_filter.category_id
    ";
}

// Count total matching vendors
$countSQL = "
    SELECT COUNT(DISTINCT v.id)
    FROM vendors v
    {$categoryJoin}
    WHERE {$whereSQL}
";

try {
    $countStmt = $db->prepare($countSQL);
    $countStmt->execute($params);
    $totalVendors = (int) $countStmt->fetchColumn();
} catch (Exception $e) {
    error_log("Marketplace: count query failed: " . $e->getMessage());
    $totalVendors = 0;
}

$totalPages = max(1, (int) ceil($totalVendors / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Fetch vendors with min price from services
$vendorSQL = "
    SELECT v.id, v.company_name, v.short_description, v.city,
           v.cover_filename, v.logo_filename,
           v.avg_rating, v.review_count,
           MIN(vs.price_from) AS min_price
    FROM vendors v
    {$categoryJoin}
    LEFT JOIN vendor_services vs ON vs.vendor_id = v.id AND vs.is_active = 1
    WHERE {$whereSQL}
    GROUP BY v.id
";

// Price range filter (applied after grouping)
$havingClauses = [];
$havingParams = [];
if ($priceMin !== null) {
    $havingClauses[] = "min_price >= ?";
    $havingParams[] = $priceMin;
}
if ($priceMax !== null) {
    $havingClauses[] = "min_price <= ?";
    $havingParams[] = $priceMax;
}

if (!empty($havingClauses)) {
    $vendorSQL .= " HAVING " . implode(' AND ', $havingClauses);
}

$vendorSQL .= " ORDER BY v.avg_rating DESC, v.review_count DESC LIMIT ? OFFSET ?";

$allParams = array_merge($params, $havingParams, [$perPage, $offset]);

$vendors = [];
try {
    $vendorStmt = $db->prepare($vendorSQL);
    $vendorStmt->execute($allParams);
    $vendors = $vendorStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Marketplace: vendor query failed: " . $e->getMessage());
}

// If price filters active, recount total with HAVING
if (!empty($havingClauses)) {
    $recountSQL = "
        SELECT COUNT(*) FROM (
            SELECT v.id, MIN(vs.price_from) AS min_price
            FROM vendors v
            {$categoryJoin}
            LEFT JOIN vendor_services vs ON vs.vendor_id = v.id AND vs.is_active = 1
            WHERE {$whereSQL}
            GROUP BY v.id
            HAVING " . implode(' AND ', $havingClauses) . "
        ) AS filtered
    ";
    try {
        $recountStmt = $db->prepare($recountSQL);
        $recountStmt->execute(array_merge($params, $havingParams));
        $totalVendors = (int) $recountStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($totalVendors / $perPage));
    } catch (Exception $e) {
        error_log("Marketplace: recount query failed: " . $e->getMessage());
    }
}

// ============================================
// Helper: build pagination URL preserving GET params
// ============================================
function buildPageUrl(int $pageNum): string {
    $params = $_GET;
    $params['page'] = $pageNum;
    return '/subcontractor/?' . http_build_query($params);
}

// ============================================
// Helper: render star rating HTML
// ============================================
function renderStars(float $rating, string $sizeClass = ''): string {
    $html = '<span class="stars ' . $sizeClass . '">';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= round($rating)) {
            $html .= '<svg class="star-filled" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
        } else {
            $html .= '<svg class="star-empty" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
        }
    }
    $html .= '</span>';
    return $html;
}

// ============================================
// Include marketplace header
// ============================================
require_once __DIR__ . '/includes/marketplace-header.php';
?>

<style>
    /* ============================================
       Marketplace Index Page Styles
       ============================================ */

    .mp-container {
        max-width: 1280px;
        margin: 0 auto;
        padding: 0 24px;
    }

    /* ---- Hero Section ---- */
    .mp-hero {
        background: linear-gradient(135deg, var(--accent-light) 0%, var(--accent) 100%);
        padding: 64px 24px;
        text-align: center;
    }

    .mp-hero-inner {
        max-width: 700px;
        margin: 0 auto;
    }

    .mp-hero h1 {
        font-family: var(--font-display);
        font-size: 42px;
        font-weight: 500;
        color: var(--surface-card);
        line-height: 1.2;
        margin-bottom: 12px;
    }

    .mp-hero-subtitle {
        font-size: 16px;
        color: rgba(255, 255, 255, 0.85);
        margin-bottom: 28px;
    }

    .mp-hero-search {
        display: flex;
        gap: 8px;
        max-width: 520px;
        margin: 0 auto;
    }

    .mp-hero-search .mp-search-input {
        border-color: transparent;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
    }

    .mp-hero-search .mp-search-btn {
        background: var(--text);
    }

    .mp-hero-search .mp-search-btn:hover {
        background: var(--text-secondary);
    }

    /* ---- Category Grid ---- */
    .mp-categories-section {
        padding: 48px 24px;
    }

    .mp-section-title {
        font-family: var(--font-display);
        font-size: 28px;
        font-weight: 500;
        color: var(--text);
        text-align: center;
        margin-bottom: 32px;
    }

    .mp-category-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 16px;
        max-width: 1280px;
        margin: 0 auto;
    }

    .mp-category-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 28px 16px;
        background: var(--surface-card);
        border-radius: 16px;
        text-decoration: none;
        text-align: center;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04), 0 4px 12px rgba(0, 0, 0, 0.03);
        transition: transform 0.25s var(--ease-out), box-shadow 0.25s var(--ease-out);
    }

    .mp-category-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    }

    .mp-category-card-icon {
        font-size: 36px;
        margin-bottom: 12px;
        line-height: 1;
    }

    .mp-category-card-name {
        font-size: 15px;
        font-weight: 600;
        color: var(--text);
        margin-bottom: 4px;
    }

    .mp-category-card-count {
        font-size: 13px;
        color: var(--text-secondary);
    }

    /* ---- Filter + Vendor Grid Layout ---- */
    .mp-browse-section {
        padding: 48px 24px;
        background: var(--surface);
    }

    .mp-browse-layout {
        display: grid;
        grid-template-columns: 260px 1fr;
        gap: 32px;
        max-width: 1280px;
        margin: 0 auto;
    }

    /* ---- Filter Sidebar ---- */
    .mp-filters {
        background: var(--surface-card);
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04), 0 4px 12px rgba(0, 0, 0, 0.03);
        align-self: start;
        position: sticky;
        top: 80px;
    }

    .mp-filters-title {
        font-family: var(--font-display);
        font-size: 20px;
        font-weight: 500;
        color: var(--text);
        margin-bottom: 20px;
    }

    .mp-filter-group {
        margin-bottom: 20px;
    }

    .mp-filter-label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: var(--text);
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .mp-filter-input,
    .mp-filter-select {
        width: 100%;
        padding: 10px 12px;
        font-family: var(--font-body);
        font-size: 14px;
        border: 2px solid var(--border);
        border-radius: 8px;
        background: var(--surface-card);
        color: var(--text);
        transition: border-color 0.2s ease;
    }

    .mp-filter-input:focus,
    .mp-filter-select:focus {
        outline: none;
        border-color: var(--accent);
    }

    .mp-filter-select {
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%234A4A4A'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 10px center;
        background-size: 16px;
        padding-right: 32px;
    }

    .mp-filter-row {
        display: flex;
        gap: 8px;
    }

    .mp-filter-row .mp-filter-input {
        flex: 1;
        min-width: 0;
    }

    .mp-filter-apply {
        width: 100%;
        padding: 10px 16px;
        font-family: var(--font-body);
        font-size: 14px;
        font-weight: 600;
        color: var(--surface-card);
        background: var(--accent);
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: background 0.2s ease;
        margin-top: 4px;
    }

    .mp-filter-apply:hover {
        background: var(--accent-dark);
    }

    .mp-filter-reset {
        display: block;
        width: 100%;
        text-align: center;
        margin-top: 8px;
        font-size: 13px;
        color: var(--text-secondary);
        text-decoration: none;
        padding: 6px;
        border-radius: 6px;
        transition: background 0.2s ease;
    }

    .mp-filter-reset:hover {
        background: var(--surface);
        color: var(--text);
    }

    /* ---- Vendor Grid ---- */
    .mp-vendor-grid-wrapper {
        min-width: 0;
    }

    .mp-vendor-grid-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
    }

    .mp-vendor-grid-count {
        font-size: 14px;
        color: var(--text-secondary);
    }

    .mp-vendor-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
    }

    /* ---- Vendor Card ---- */
    .mp-vendor-card {
        background: var(--surface-card);
        border-radius: 16px;
        overflow: hidden;
        text-decoration: none;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04), 0 4px 12px rgba(0, 0, 0, 0.03);
        transition: transform 0.25s var(--ease-out), box-shadow 0.25s var(--ease-out);
        display: flex;
        flex-direction: column;
    }

    .mp-vendor-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    }

    .mp-vendor-card-image {
        width: 100%;
        height: 180px;
        object-fit: cover;
        background: var(--accent-light);
    }

    .mp-vendor-card-placeholder {
        width: 100%;
        height: 180px;
        background: linear-gradient(135deg, var(--accent-light) 0%, var(--accent) 100%);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .mp-vendor-card-placeholder svg {
        width: 48px;
        height: 48px;
        color: rgba(255, 255, 255, 0.5);
    }

    .mp-vendor-card-body {
        padding: 20px;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .mp-vendor-card-name {
        font-family: var(--font-display);
        font-size: 20px;
        font-weight: 500;
        color: var(--text);
        margin-bottom: 6px;
        line-height: 1.3;
    }

    .mp-vendor-card-desc {
        font-size: 14px;
        color: var(--text-secondary);
        line-height: 1.5;
        margin-bottom: 12px;
        flex: 1;
    }

    .mp-vendor-card-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        flex-wrap: wrap;
    }

    .mp-vendor-card-rating {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        color: var(--text-secondary);
    }

    .mp-vendor-card-rating .stars svg {
        width: 14px;
        height: 14px;
    }

    .mp-vendor-card-price {
        font-size: 14px;
        font-weight: 600;
        color: var(--accent-dark);
    }

    .mp-vendor-card-city {
        font-size: 13px;
        color: var(--text-secondary);
        margin-top: 8px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .mp-vendor-card-city svg {
        width: 14px;
        height: 14px;
        flex-shrink: 0;
    }

    /* ---- CTA Section ---- */
    .mp-cta-section {
        padding: 64px 24px;
        text-align: center;
        background: var(--surface-card);
    }

    .mp-cta-inner {
        max-width: 600px;
        margin: 0 auto;
    }

    .mp-cta-section h2 {
        font-family: var(--font-display);
        font-size: 32px;
        font-weight: 500;
        color: var(--text);
        margin-bottom: 12px;
    }

    .mp-cta-section p {
        font-size: 16px;
        color: var(--text-secondary);
        margin-bottom: 28px;
        line-height: 1.6;
    }

    .mp-cta-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 14px 28px;
        font-family: var(--font-body);
        font-size: 16px;
        font-weight: 600;
        color: var(--surface-card);
        background: var(--accent);
        border: none;
        border-radius: 12px;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.25s var(--ease-out);
    }

    .mp-cta-btn:hover {
        background: var(--accent-dark);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(122, 139, 114, 0.3);
    }

    /* ---- Public Footer ---- */
    .mp-footer {
        padding: 24px;
        text-align: center;
        font-size: 13px;
        color: rgba(44, 44, 44, 0.4);
        border-top: 1px solid var(--border);
        background: var(--surface);
    }

    .mp-footer a {
        color: rgba(44, 44, 44, 0.5);
        text-decoration: none;
        transition: color 0.2s ease;
    }

    .mp-footer a:hover {
        color: var(--text);
    }

    /* ---- Empty State ---- */
    .mp-empty {
        text-align: center;
        padding: 60px 20px;
    }

    .mp-empty-icon {
        font-size: 48px;
        margin-bottom: 16px;
        opacity: 0.4;
    }

    .mp-empty h3 {
        font-family: var(--font-display);
        font-size: 22px;
        font-weight: 500;
        color: var(--text);
        margin-bottom: 8px;
    }

    .mp-empty p {
        font-size: 15px;
        color: var(--text-secondary);
    }

    /* ---- Responsive ---- */
    @media (max-width: 900px) {
        .mp-browse-layout {
            grid-template-columns: 1fr;
        }

        .mp-filters {
            position: static;
        }
    }

    @media (max-width: 640px) {
        .mp-hero {
            padding: 40px 16px;
        }

        .mp-hero h1 {
            font-size: 28px;
        }

        .mp-hero-search {
            flex-direction: column;
        }

        .mp-category-grid {
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 12px;
        }

        .mp-category-card {
            padding: 20px 12px;
        }

        .mp-category-card-icon {
            font-size: 28px;
        }

        .mp-vendor-grid {
            grid-template-columns: 1fr;
        }

        .mp-categories-section,
        .mp-browse-section {
            padding: 32px 16px;
        }

        .mp-section-title {
            font-size: 22px;
        }

        .mp-cta-section {
            padding: 40px 16px;
        }

        .mp-cta-section h2 {
            font-size: 24px;
        }
    }
</style>

<!-- ============================================
     1. Hero Section
     ============================================ -->
<section class="mp-hero">
    <div class="mp-hero-inner">
        <h1>Find den perfekte leverandør til dit event</h1>
        <p class="mp-hero-subtitle">Gennemse hundredvis af verificerede leverandører — fra fotografer og caterere til teltudlejning og underholdning.</p>
        <form class="mp-hero-search" action="/subcontractor/" method="get">
            <input type="text"
                   name="search"
                   class="mp-search-input"
                   placeholder="Søg efter leverandører, ydelser..."
                   value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="mp-search-btn">Søg</button>
        </form>
    </div>
</section>

<!-- ============================================
     2. Category Grid
     ============================================ -->
<?php if (!empty($categories)): ?>
<section class="mp-categories-section">
    <h2 class="mp-section-title">Udforsk kategorier</h2>
    <div class="mp-category-grid">
        <?php foreach ($categories as $cat): ?>
            <a href="/subcontractor/kategori/<?= htmlspecialchars($cat['slug']) ?>" class="mp-category-card">
                <div class="mp-category-card-icon"><?= $cat['icon'] ?></div>
                <div class="mp-category-card-name"><?= htmlspecialchars($cat['name']) ?></div>
                <div class="mp-category-card-count"><?= (int) $cat['vendor_count'] ?> leverandør<?= (int) $cat['vendor_count'] !== 1 ? 'er' : '' ?></div>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ============================================
     3. Filter Sidebar + Vendor Grid
     ============================================ -->
<section class="mp-browse-section" id="leverandoerer">
    <div class="mp-browse-layout">

        <!-- Filter Sidebar -->
        <aside class="mp-filters">
            <h3 class="mp-filters-title">Filtre</h3>
            <form method="get" action="/subcontractor/#leverandoerer">

                <!-- Category filter -->
                <div class="mp-filter-group">
                    <label class="mp-filter-label" for="filter-category">Kategori</label>
                    <select class="mp-filter-select" id="filter-category" name="category">
                        <option value="">Alle kategorier</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['slug']) ?>"
                                <?= $category === $cat['slug'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- City filter -->
                <div class="mp-filter-group">
                    <label class="mp-filter-label" for="filter-city">By</label>
                    <input type="text"
                           class="mp-filter-input"
                           id="filter-city"
                           name="city"
                           placeholder="F.eks. København"
                           value="<?= htmlspecialchars($city) ?>">
                </div>

                <!-- Price range -->
                <div class="mp-filter-group">
                    <label class="mp-filter-label">Prisinterval (kr)</label>
                    <div class="mp-filter-row">
                        <input type="number"
                               class="mp-filter-input"
                               name="price_min"
                               placeholder="Min"
                               min="0"
                               value="<?= $priceMin !== null ? (int) $priceMin : '' ?>">
                        <input type="number"
                               class="mp-filter-input"
                               name="price_max"
                               placeholder="Max"
                               min="0"
                               value="<?= $priceMax !== null ? (int) $priceMax : '' ?>">
                    </div>
                </div>

                <!-- Minimum rating -->
                <div class="mp-filter-group">
                    <label class="mp-filter-label" for="filter-rating">Minimum bedømmelse</label>
                    <select class="mp-filter-select" id="filter-rating" name="rating">
                        <option value="">Alle bedømmelser</option>
                        <option value="5" <?= $rating === 5 ? 'selected' : '' ?>>5 stjerner</option>
                        <option value="4" <?= $rating === 4 ? 'selected' : '' ?>>4+ stjerner</option>
                        <option value="3" <?= $rating === 3 ? 'selected' : '' ?>>3+ stjerner</option>
                        <option value="2" <?= $rating === 2 ? 'selected' : '' ?>>2+ stjerner</option>
                        <option value="1" <?= $rating === 1 ? 'selected' : '' ?>>1+ stjerne</option>
                    </select>
                </div>

                <!-- Preserve search if present -->
                <?php if ($search !== ''): ?>
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                <?php endif; ?>

                <button type="submit" class="mp-filter-apply">Anvend filtre</button>

                <?php
                $hasFilters = $category !== '' || $city !== '' || $priceMin !== null || $priceMax !== null || $rating !== null || $search !== '';
                if ($hasFilters):
                ?>
                    <a href="/subcontractor/#leverandoerer" class="mp-filter-reset">Nulstil filtre</a>
                <?php endif; ?>
            </form>
        </aside>

        <!-- Vendor Grid -->
        <div class="mp-vendor-grid-wrapper">
            <div class="mp-vendor-grid-header">
                <span class="mp-vendor-grid-count">
                    <?= number_format($totalVendors, 0, ',', '.') ?> leverandør<?= $totalVendors !== 1 ? 'er' : '' ?> fundet
                </span>
            </div>

            <?php if (!empty($vendors)): ?>
                <div class="mp-vendor-grid">
                    <?php foreach ($vendors as $vendor): ?>
                        <a href="/subcontractor/leverandor/<?= (int) $vendor['id'] ?>" class="mp-vendor-card">
                            <?php if (!empty($vendor['cover_filename'])): ?>
                                <img class="mp-vendor-card-image"
                                     src="/uploads/vendors/<?= (int) $vendor['id'] ?>/<?= htmlspecialchars($vendor['cover_filename']) ?>"
                                     alt="<?= htmlspecialchars($vendor['company_name']) ?>"
                                     loading="lazy">
                            <?php else: ?>
                                <div class="mp-vendor-card-placeholder">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                            <?php endif; ?>

                            <div class="mp-vendor-card-body">
                                <div class="mp-vendor-card-name"><?= htmlspecialchars($vendor['company_name']) ?></div>

                                <?php if (!empty($vendor['short_description'])): ?>
                                    <div class="mp-vendor-card-desc">
                                        <?= htmlspecialchars(mb_strimwidth($vendor['short_description'], 0, 100, '...')) ?>
                                    </div>
                                <?php endif; ?>

                                <div class="mp-vendor-card-meta">
                                    <div class="mp-vendor-card-rating">
                                        <?= renderStars((float) $vendor['avg_rating']) ?>
                                        <span>(<?= (int) $vendor['review_count'] ?>)</span>
                                    </div>

                                    <?php if ($vendor['min_price'] !== null): ?>
                                        <div class="mp-vendor-card-price">
                                            Fra <?= number_format((float) $vendor['min_price'], 0, ',', '.') ?> kr
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($vendor['city'])): ?>
                                    <div class="mp-vendor-card-city">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        </svg>
                                        <?= htmlspecialchars($vendor['city']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="<?= htmlspecialchars(buildPageUrl($page - 1)) ?>">&laquo; Forrige</a>
                        <?php else: ?>
                            <span class="disabled">&laquo; Forrige</span>
                        <?php endif; ?>

                        <?php
                        // Show a window of page numbers around the current page
                        $windowSize = 2;
                        $startPage = max(1, $page - $windowSize);
                        $endPage = min($totalPages, $page + $windowSize);

                        if ($startPage > 1): ?>
                            <a href="<?= htmlspecialchars(buildPageUrl(1)) ?>">1</a>
                            <?php if ($startPage > 2): ?>
                                <span class="disabled">...</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <?php if ($i === $page): ?>
                                <span class="active"><?= $i ?></span>
                            <?php else: ?>
                                <a href="<?= htmlspecialchars(buildPageUrl($i)) ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($endPage < $totalPages): ?>
                            <?php if ($endPage < $totalPages - 1): ?>
                                <span class="disabled">...</span>
                            <?php endif; ?>
                            <a href="<?= htmlspecialchars(buildPageUrl($totalPages)) ?>"><?= $totalPages ?></a>
                        <?php endif; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="<?= htmlspecialchars(buildPageUrl($page + 1)) ?>">Næste &raquo;</a>
                        <?php else: ?>
                            <span class="disabled">Næste &raquo;</span>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>

            <?php else: ?>
                <div class="mp-empty">
                    <div class="mp-empty-icon">🔍</div>
                    <h3>Ingen leverandører fundet</h3>
                    <p>Prøv at ændre dine filtre eller søgeord for at finde leverandører.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ============================================
     4. "Bliv leverandør" CTA Section
     ============================================ -->
<section class="mp-cta-section">
    <div class="mp-cta-inner">
        <h2>Bliv leverandør på PartyParart</h2>
        <p>Er du fotograf, cater, DJ eller tilbyder andre event-ydelser? Opret din profil gratis og nå tusindvis af eventarrangører i hele Danmark.</p>
        <a href="/subcontractor/dashboard/register.php" class="mp-cta-btn">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Opret leverandørprofil
        </a>
    </div>
</section>

<!-- ============================================
     Public Footer
     ============================================ -->
<footer class="mp-footer">
    <p>&copy; <?= date('Y') ?> PartyParart. Alle rettigheder forbeholdes. &nbsp;&middot;&nbsp; <a href="/legal/privacy.php">Privatlivspolitik</a> &nbsp;&middot;&nbsp; <a href="/legal/terms.php">Handelsbetingelser</a></p>
</footer>

</body>
</html>

<?php
/**
 * Marketplace Category Page
 * Lists vendors belonging to a specific category.
 * PUBLIC page — no login required.
 *
 * Routed via .htaccess: /subcontractor/kategori/{slug} → category.php?slug={slug}
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDB();

// ---------------------------------------------------------------------------
// 1. Load category by slug
// ---------------------------------------------------------------------------
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if ($slug === '') {
    http_response_code(404);
    $pageTitle = 'Kategori ikke fundet';
    require_once __DIR__ . '/includes/marketplace-header.php';
    echo '<div style="text-align:center;padding:100px 24px;">';
    echo '<h1 style="font-family:var(--font-display);font-size:38px;margin-bottom:12px;">404</h1>';
    echo '<p style="color:var(--text-secondary);font-size:16px;margin-bottom:24px;">Kategorien blev ikke fundet.</p>';
    echo '<a href="/subcontractor/" style="color:var(--accent-dark);font-weight:600;text-decoration:underline;">Tilbage til leverandøroversigten</a>';
    echo '</div>';
    require_once __DIR__ . '/includes/marketplace-footer.php';
    exit;
}

$stmt = $db->prepare("
    SELECT id, name, slug, icon, description
    FROM vendor_categories
    WHERE slug = ? AND is_active = 1
    LIMIT 1
");
$stmt->execute([$slug]);
$category = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$category) {
    http_response_code(404);
    $pageTitle = 'Kategori ikke fundet';
    require_once __DIR__ . '/includes/marketplace-header.php';
    echo '<div style="text-align:center;padding:100px 24px;">';
    echo '<h1 style="font-family:var(--font-display);font-size:38px;margin-bottom:12px;">404</h1>';
    echo '<p style="color:var(--text-secondary);font-size:16px;margin-bottom:24px;">Kategorien blev ikke fundet.</p>';
    echo '<a href="/subcontractor/" style="color:var(--accent-dark);font-weight:600;text-decoration:underline;">Tilbage til leverandøroversigten</a>';
    echo '</div>';
    require_once __DIR__ . '/includes/marketplace-footer.php';
    exit;
}

// ---------------------------------------------------------------------------
// 2. Sort & filter parameters
// ---------------------------------------------------------------------------
$allowedSorts = [
    'rating'  => 'v.avg_rating DESC, v.review_count DESC',
    'price'   => 'min_price ASC',
    'newest'  => 'v.created_at DESC',
];
$currentSort = isset($_GET['sort']) && isset($allowedSorts[$_GET['sort']])
    ? $_GET['sort']
    : 'rating';
$orderClause = $allowedSorts[$currentSort];

$cityFilter = isset($_GET['city']) ? trim($_GET['city']) : '';

// ---------------------------------------------------------------------------
// 3. Count total vendors in this category (for pagination + display)
// ---------------------------------------------------------------------------
$countSql = "
    SELECT COUNT(DISTINCT v.id)
    FROM vendors v
    INNER JOIN vendor_category_links vcl ON vcl.vendor_id = v.id
    WHERE vcl.category_id = ?
      AND v.status = 'approved'
      AND v.is_active = 1
";
$countParams = [$category['id']];

if ($cityFilter !== '') {
    $countSql .= " AND v.city LIKE ?";
    $countParams[] = '%' . $cityFilter . '%';
}

$countStmt = $db->prepare($countSql);
$countStmt->execute($countParams);
$totalVendors = (int) $countStmt->fetchColumn();

// Pagination
$perPage = 12;
$totalPages = max(1, (int) ceil($totalVendors / $perPage));
$currentPage = isset($_GET['page']) ? max(1, min($totalPages, (int) $_GET['page'])) : 1;
$offset = ($currentPage - 1) * $perPage;

// ---------------------------------------------------------------------------
// 4. Fetch vendors
// ---------------------------------------------------------------------------
$vendorSql = "
    SELECT v.id,
           v.company_name,
           v.short_description,
           v.city,
           v.cover_filename,
           v.logo_filename,
           v.avg_rating,
           v.review_count,
           v.created_at,
           (SELECT MIN(vs.price_from)
            FROM vendor_services vs
            WHERE vs.vendor_id = v.id AND vs.is_active = 1
           ) AS min_price
    FROM vendors v
    INNER JOIN vendor_category_links vcl ON vcl.vendor_id = v.id
    WHERE vcl.category_id = ?
      AND v.status = 'approved'
      AND v.is_active = 1
";
$vendorParams = [$category['id']];

if ($cityFilter !== '') {
    $vendorSql .= " AND v.city LIKE ?";
    $vendorParams[] = '%' . $cityFilter . '%';
}

$vendorSql .= " GROUP BY v.id ORDER BY {$orderClause} LIMIT ? OFFSET ?";
$vendorParams[] = $perPage;
$vendorParams[] = $offset;

$vendorStmt = $db->prepare($vendorSql);
$vendorStmt->execute($vendorParams);
$vendors = $vendorStmt->fetchAll(PDO::FETCH_ASSOC);

// ---------------------------------------------------------------------------
// 5. Render page
// ---------------------------------------------------------------------------
$pageTitle = escape($category['name']) . ' — Find leverandører';
require_once __DIR__ . '/includes/marketplace-header.php';

/**
 * Helper: build a URL preserving current query params but overriding specific keys.
 */
function categoryUrl(array $overrides = []): string {
    global $slug;
    $params = $_GET;
    $params['slug'] = $slug; // keep slug even though .htaccess handles it
    foreach ($overrides as $k => $val) {
        if ($val === null || $val === '') {
            unset($params[$k]);
        } else {
            $params[$k] = $val;
        }
    }
    // Always link to the pretty URL
    unset($params['slug']);
    $qs = http_build_query($params);
    $url = '/subcontractor/kategori/' . urlencode($slug);
    return $qs ? $url . '?' . $qs : $url;
}

/**
 * Render star SVGs for a given rating.
 */
function renderStars(float $rating, int $max = 5): string {
    $html = '';
    for ($i = 1; $i <= $max; $i++) {
        if ($rating >= $i) {
            // Full star
            $html .= '<svg viewBox="0 0 20 20" fill="currentColor" class="star-filled"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.957a1 1 0 00.95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.37 2.448a1 1 0 00-.364 1.118l1.287 3.957c.3.921-.755 1.688-1.538 1.118l-3.37-2.448a1 1 0 00-1.175 0l-3.37 2.448c-.784.57-1.838-.197-1.539-1.118l1.287-3.957a1 1 0 00-.364-1.118L2.063 9.384c-.783-.57-.38-1.81.588-1.81h4.162a1 1 0 00.95-.69l1.286-3.957z"/></svg>';
        } elseif ($rating >= $i - 0.5) {
            // Half star (show filled)
            $html .= '<svg viewBox="0 0 20 20" fill="currentColor" class="star-filled"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.957a1 1 0 00.95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.37 2.448a1 1 0 00-.364 1.118l1.287 3.957c.3.921-.755 1.688-1.538 1.118l-3.37-2.448a1 1 0 00-1.175 0l-3.37 2.448c-.784.57-1.838-.197-1.539-1.118l1.287-3.957a1 1 0 00-.364-1.118L2.063 9.384c-.783-.57-.38-1.81.588-1.81h4.162a1 1 0 00.95-.69l1.286-3.957z"/></svg>';
        } else {
            // Empty star
            $html .= '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" class="star-empty"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.957a1 1 0 00.95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.37 2.448a1 1 0 00-.364 1.118l1.287 3.957c.3.921-.755 1.688-1.538 1.118l-3.37-2.448a1 1 0 00-1.175 0l-3.37 2.448c-.784.57-1.838-.197-1.539-1.118l1.287-3.957a1 1 0 00-.364-1.118L2.063 9.384c-.783-.57-.38-1.81.588-1.81h4.162a1 1 0 00.95-.69l1.286-3.957z"/></svg>';
        }
    }
    return $html;
}
?>

<style>
    /* ---- Category page specific styles ---- */
    .cat-hero {
        background: var(--surface-card);
        border-bottom: 1px solid var(--border);
        padding: 40px 0 32px;
    }
    .cat-hero-inner {
        max-width: 1280px;
        margin: 0 auto;
        padding: 0 24px;
    }
    .cat-hero-icon {
        font-size: 44px;
        line-height: 1;
        margin-bottom: 8px;
    }
    .cat-hero-title {
        font-family: var(--font-display);
        font-size: 36px;
        font-weight: 400;
        color: var(--text);
        line-height: 1.2;
        margin-bottom: 6px;
    }
    .cat-hero-desc {
        font-size: 16px;
        color: var(--text-secondary);
        margin-bottom: 10px;
        max-width: 600px;
    }
    .cat-hero-count {
        font-size: 14px;
        color: var(--text-secondary);
    }
    .cat-hero-count strong {
        color: var(--text);
        font-weight: 600;
    }

    /* ---- Toolbar ---- */
    .cat-toolbar {
        max-width: 1280px;
        margin: 0 auto;
        padding: 20px 24px 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }
    .cat-toolbar-sorts {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .cat-toolbar-label {
        font-size: 13px;
        font-weight: 600;
        color: var(--text-secondary);
        margin-right: 4px;
    }
    .cat-sort-link {
        display: inline-flex;
        align-items: center;
        padding: 7px 14px;
        font-family: var(--font-body);
        font-size: 13px;
        font-weight: 500;
        color: var(--text-secondary);
        background: var(--surface-card);
        border: 2px solid var(--border);
        border-radius: 8px;
        text-decoration: none;
        transition: all 0.2s ease;
        white-space: nowrap;
    }
    .cat-sort-link:hover {
        border-color: var(--accent);
        color: var(--text);
    }
    .cat-sort-link.active {
        background: var(--accent);
        color: var(--surface-card);
        border-color: var(--accent);
    }
    .cat-filter-form {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .cat-filter-input {
        padding: 7px 14px;
        font-family: var(--font-body);
        font-size: 13px;
        border: 2px solid var(--border);
        border-radius: 8px;
        background: var(--surface-card);
        color: var(--text);
        width: 180px;
        transition: all 0.2s ease;
    }
    .cat-filter-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(168, 181, 160, 0.15);
    }
    .cat-filter-input::placeholder {
        color: #B0ACA4;
    }
    .cat-filter-btn {
        padding: 7px 14px;
        font-family: var(--font-body);
        font-size: 13px;
        font-weight: 600;
        color: var(--surface-card);
        background: var(--accent);
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: background 0.2s ease;
    }
    .cat-filter-btn:hover {
        background: var(--accent-dark);
    }
    .cat-filter-clear {
        font-size: 13px;
        color: var(--text-secondary);
        text-decoration: underline;
        transition: color 0.2s ease;
    }
    .cat-filter-clear:hover {
        color: var(--text);
    }

    /* ---- Vendor grid ---- */
    .cat-vendor-grid {
        max-width: 1280px;
        margin: 0 auto;
        padding: 24px;
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 24px;
    }
    .cat-vendor-card {
        background: var(--surface-card);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04), 0 4px 12px rgba(0, 0, 0, 0.03);
        transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        display: flex;
        flex-direction: column;
    }
    .cat-vendor-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
    }
    .cat-vendor-link {
        display: flex;
        flex-direction: column;
        height: 100%;
        text-decoration: none;
        color: inherit;
    }
    .cat-vendor-cover {
        width: 100%;
        aspect-ratio: 16 / 10;
        position: relative;
        overflow: hidden;
        background: linear-gradient(135deg, var(--accent-light) 0%, var(--accent) 100%);
    }
    .cat-vendor-cover img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .cat-vendor-card:hover .cat-vendor-cover img {
        transform: scale(1.04);
    }
    .cat-vendor-cover-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 40px;
        color: var(--surface-card);
        opacity: 0.6;
    }
    .cat-vendor-body {
        padding: 18px 20px 20px;
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    .cat-vendor-name {
        font-family: var(--font-display);
        font-size: 20px;
        font-weight: 500;
        color: var(--text);
        line-height: 1.3;
        margin-bottom: 4px;
    }
    .cat-vendor-desc {
        font-size: 13px;
        color: var(--text-secondary);
        line-height: 1.5;
        margin-bottom: 12px;
        flex: 1;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .cat-vendor-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }
    .cat-vendor-rating {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .cat-vendor-stars {
        display: inline-flex;
        gap: 1px;
    }
    .cat-vendor-stars svg {
        width: 14px;
        height: 14px;
    }
    .cat-vendor-stars .star-filled {
        color: var(--warning);
        fill: var(--warning);
    }
    .cat-vendor-stars .star-empty {
        color: var(--border);
        fill: none;
        stroke: var(--border);
    }
    .cat-vendor-rating-text {
        font-size: 13px;
        font-weight: 600;
        color: var(--text);
    }
    .cat-vendor-rating-count {
        font-size: 12px;
        color: var(--text-secondary);
        font-weight: 400;
    }
    .cat-vendor-price {
        font-size: 13px;
        font-weight: 600;
        color: var(--accent-dark);
        white-space: nowrap;
    }
    .cat-vendor-city {
        font-size: 12px;
        color: var(--text-secondary);
        margin-top: 8px;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .cat-vendor-city svg {
        width: 14px;
        height: 14px;
        flex-shrink: 0;
    }

    /* ---- Empty state ---- */
    .cat-empty {
        text-align: center;
        padding: 80px 24px;
        max-width: 500px;
        margin: 0 auto;
    }
    .cat-empty-icon {
        font-size: 48px;
        margin-bottom: 16px;
    }
    .cat-empty h3 {
        font-family: var(--font-display);
        font-size: 24px;
        font-weight: 400;
        color: var(--text);
        margin-bottom: 8px;
    }
    .cat-empty p {
        font-size: 15px;
        color: var(--text-secondary);
        margin-bottom: 24px;
    }
    .cat-empty a {
        color: var(--accent-dark);
        font-weight: 600;
        text-decoration: underline;
    }

    /* ---- Pagination ---- */
    .cat-pagination {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        padding: 16px 24px 48px;
    }
    .cat-pagination a,
    .cat-pagination span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 40px;
        height: 40px;
        padding: 0 12px;
        font-size: 14px;
        font-weight: 500;
        border-radius: 10px;
        transition: all 0.2s ease;
    }
    .cat-pagination a {
        color: var(--text);
        text-decoration: none;
        background: var(--surface-card);
        border: 1px solid var(--border);
    }
    .cat-pagination a:hover {
        border-color: var(--accent);
        background: var(--surface);
    }
    .cat-pagination .pg-active {
        background: var(--accent);
        color: var(--surface-card);
        border: 1px solid var(--accent);
    }
    .cat-pagination .pg-disabled {
        color: var(--border);
        pointer-events: none;
        background: transparent;
        border: 1px solid transparent;
    }
    .cat-pagination .pg-ellipsis {
        border: none;
        background: none;
        color: var(--text-secondary);
        min-width: 32px;
    }

    /* ---- Responsive ---- */
    @media (max-width: 1024px) {
        .cat-vendor-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    @media (max-width: 768px) {
        .cat-hero { padding: 28px 0 24px; }
        .cat-hero-icon { font-size: 36px; }
        .cat-hero-title { font-size: 28px; }
        .cat-hero-desc { font-size: 15px; }
        .cat-toolbar {
            flex-direction: column;
            align-items: stretch;
            padding: 16px 16px 0;
        }
        .cat-toolbar-sorts {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            flex-wrap: nowrap;
            padding-bottom: 4px;
        }
        .cat-vendor-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            padding: 20px 16px;
        }
        .cat-vendor-body { padding: 14px 16px 16px; }
        .cat-vendor-name { font-size: 17px; }
        .cat-filter-input { width: 100%; }
    }
    @media (max-width: 640px) {
        .cat-hero { padding: 20px 0 16px; }
        .cat-hero-inner { padding: 0 16px; }
        .cat-hero-title { font-size: 24px; }
        .cat-vendor-grid {
            grid-template-columns: 1fr;
            gap: 16px;
            padding: 16px 12px;
        }
        .cat-vendor-cover { aspect-ratio: 16 / 9; }
        .cat-toolbar { padding: 12px 12px 0; }
    }
</style>

<!-- Category Hero -->
<section class="cat-hero">
    <div class="cat-hero-inner">
        <div class="cat-hero-icon"><?= $category['icon'] ?></div>
        <h1 class="cat-hero-title"><?= escape($category['name']) ?></h1>
        <?php if (!empty($category['description'])): ?>
            <p class="cat-hero-desc"><?= escape($category['description']) ?></p>
        <?php endif; ?>
        <p class="cat-hero-count">
            <strong><?= $totalVendors ?></strong> leverandør<?= $totalVendors !== 1 ? 'er' : '' ?> i denne kategori
            <?php if ($cityFilter !== ''): ?>
                i &ldquo;<?= escape($cityFilter) ?>&rdquo;
            <?php endif; ?>
        </p>
    </div>
</section>

<!-- Toolbar: Sort + City Filter -->
<div class="cat-toolbar">
    <div class="cat-toolbar-sorts">
        <span class="cat-toolbar-label">Sortér:</span>
        <a href="<?= categoryUrl(['sort' => 'rating', 'page' => null]) ?>"
           class="cat-sort-link <?= $currentSort === 'rating' ? 'active' : '' ?>">Bedst bedømt</a>
        <a href="<?= categoryUrl(['sort' => 'price', 'page' => null]) ?>"
           class="cat-sort-link <?= $currentSort === 'price' ? 'active' : '' ?>">Laveste pris</a>
        <a href="<?= categoryUrl(['sort' => 'newest', 'page' => null]) ?>"
           class="cat-sort-link <?= $currentSort === 'newest' ? 'active' : '' ?>">Nyeste</a>
    </div>

    <form class="cat-filter-form" method="get" action="/subcontractor/kategori/<?= urlencode($slug) ?>">
        <!-- Preserve sort parameter -->
        <?php if ($currentSort !== 'rating'): ?>
            <input type="hidden" name="sort" value="<?= escape($currentSort) ?>">
        <?php endif; ?>
        <input type="text"
               name="city"
               class="cat-filter-input"
               placeholder="Filtrer efter by..."
               value="<?= escape($cityFilter) ?>">
        <button type="submit" class="cat-filter-btn">Filtrer</button>
        <?php if ($cityFilter !== ''): ?>
            <a href="<?= categoryUrl(['city' => null, 'page' => null]) ?>" class="cat-filter-clear">Ryd</a>
        <?php endif; ?>
    </form>
</div>

<!-- Vendor Card Grid -->
<?php if (empty($vendors)): ?>
    <div class="cat-empty">
        <div class="cat-empty-icon"><?= $category['icon'] ?></div>
        <h3>Ingen leverandører fundet</h3>
        <p>
            <?php if ($cityFilter !== ''): ?>
                Der er ingen leverandører i &ldquo;<?= escape($cityFilter) ?>&rdquo; endnu.
                <a href="<?= categoryUrl(['city' => null]) ?>">Se alle i denne kategori</a>
            <?php else: ?>
                Der er endnu ingen leverandører i denne kategori.
                <a href="/subcontractor/">Se alle kategorier</a>
            <?php endif; ?>
        </p>
    </div>
<?php else: ?>
    <div class="cat-vendor-grid">
        <?php foreach ($vendors as $vendor): ?>
            <div class="cat-vendor-card">
                <a href="/subcontractor/leverandor/<?= (int) $vendor['id'] ?>" class="cat-vendor-link">
                    <div class="cat-vendor-cover">
                        <?php if (!empty($vendor['cover_filename'])): ?>
                            <img src="/subcontractor/uploads/covers/<?= escape($vendor['cover_filename']) ?>"
                                 alt="<?= escape($vendor['company_name']) ?>"
                                 loading="lazy">
                        <?php else: ?>
                            <div class="cat-vendor-cover-placeholder">
                                <?= $category['icon'] ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="cat-vendor-body">
                        <h3 class="cat-vendor-name"><?= escape($vendor['company_name']) ?></h3>
                        <?php if (!empty($vendor['short_description'])): ?>
                            <p class="cat-vendor-desc"><?= escape($vendor['short_description']) ?></p>
                        <?php else: ?>
                            <p class="cat-vendor-desc">&nbsp;</p>
                        <?php endif; ?>
                        <div class="cat-vendor-meta">
                            <div class="cat-vendor-rating">
                                <span class="cat-vendor-stars">
                                    <?= renderStars((float) $vendor['avg_rating']) ?>
                                </span>
                                <?php if ((float) $vendor['avg_rating'] > 0): ?>
                                    <span class="cat-vendor-rating-text">
                                        <?= number_format((float) $vendor['avg_rating'], 1, ',', '') ?>
                                    </span>
                                    <span class="cat-vendor-rating-count">
                                        (<?= (int) $vendor['review_count'] ?>)
                                    </span>
                                <?php else: ?>
                                    <span class="cat-vendor-rating-count">Ingen anmeldelser</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($vendor['min_price'] !== null): ?>
                                <span class="cat-vendor-price">
                                    Fra <?= number_format((float) $vendor['min_price'], 0, ',', '.') ?> kr
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($vendor['city'])): ?>
                            <div class="cat-vendor-city">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <?= escape($vendor['city']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <nav class="cat-pagination" aria-label="Sidenavigation">
            <?php if ($currentPage > 1): ?>
                <a href="<?= categoryUrl(['page' => $currentPage - 1]) ?>">&laquo; Forrige</a>
            <?php else: ?>
                <span class="pg-disabled">&laquo; Forrige</span>
            <?php endif; ?>

            <?php
            // Show page numbers with ellipsis
            $range = 2;
            for ($p = 1; $p <= $totalPages; $p++):
                if ($p === 1 || $p === $totalPages || ($p >= $currentPage - $range && $p <= $currentPage + $range)):
                    if ($p === $currentPage): ?>
                        <span class="pg-active"><?= $p ?></span>
                    <?php else: ?>
                        <a href="<?= categoryUrl(['page' => $p]) ?>"><?= $p ?></a>
                    <?php endif;
                elseif ($p === $currentPage - $range - 1 || $p === $currentPage + $range + 1): ?>
                    <span class="pg-ellipsis">&hellip;</span>
                <?php endif;
            endfor;
            ?>

            <?php if ($currentPage < $totalPages): ?>
                <a href="<?= categoryUrl(['page' => $currentPage + 1]) ?>">Næste &raquo;</a>
            <?php else: ?>
                <span class="pg-disabled">Næste &raquo;</span>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/marketplace-footer.php'; ?>

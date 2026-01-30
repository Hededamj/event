<?php
/**
 * Partner Marketplace - Category View
 */

require_once __DIR__ . '/../config/saas.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDB();

// Get category
$slug = $_GET['slug'] ?? '';
$search = trim($_GET['q'] ?? '');
$city = trim($_GET['city'] ?? '');
$sort = $_GET['sort'] ?? 'newest';

$currentCategory = $slug;
$category = null;

if ($slug) {
    $stmt = $db->prepare("SELECT * FROM partner_categories WHERE slug = ? AND is_active = 1");
    $stmt->execute([$slug]);
    $category = $stmt->fetch();

    if (!$category) {
        redirect(BASE_PATH . '/partners/');
    }

    $pageTitle = $category['name'];
} else {
    $pageTitle = 'Alle leverandører';
}

require_once __DIR__ . '/../includes/partner-header.php';

// Build query
$where = ["p.status = 'approved'"];
$params = [];

if ($category) {
    $where[] = "p.category_id = ?";
    $params[] = $category['id'];
}

if ($search) {
    $where[] = "(p.company_name LIKE ? OR p.description LIKE ? OR p.short_description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($city) {
    $where[] = "(p.city LIKE ? OR p.nationwide = 1)";
    $params[] = "%$city%";
}

$whereClause = implode(' AND ', $where);

$orderBy = match($sort) {
    'price_asc' => 'p.price_from ASC',
    'price_desc' => 'p.price_from DESC',
    'popular' => 'p.view_count DESC',
    'name' => 'p.company_name ASC',
    default => 'p.approved_at DESC'
};

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Count total
$stmt = $db->prepare("
    SELECT COUNT(*)
    FROM partners p
    WHERE $whereClause
");
$stmt->execute($params);
$totalPartners = $stmt->fetchColumn();
$totalPages = ceil($totalPartners / $perPage);

// Get partners
$stmt = $db->prepare("
    SELECT p.*, pc.name as category_name, pc.icon as category_icon, pc.slug as category_slug
    FROM partners p
    JOIN partner_categories pc ON p.category_id = pc.id
    WHERE $whereClause
    ORDER BY p.is_featured DESC, $orderBy
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$partners = $stmt->fetchAll();

// Get cities for filter
$stmt = $db->query("
    SELECT DISTINCT city FROM partners
    WHERE status = 'approved' AND city IS NOT NULL AND city != ''
    ORDER BY city
");
$cities = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<style>
    .category-hero {
        background: var(--color-surface);
        padding: 2rem 0;
        border-bottom: 1px solid var(--color-border);
    }

    .category-hero h1 {
        font-family: 'Playfair Display', serif;
        font-size: 2rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .category-hero p {
        color: var(--color-text-soft);
        margin-top: 0.5rem;
    }

    .filters {
        background: var(--color-surface);
        padding: 1rem 0;
        border-bottom: 1px solid var(--color-border);
        position: sticky;
        top: 65px;
        z-index: 50;
    }

    .filters-inner {
        display: flex;
        gap: 1rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .filters select,
    .filters input {
        padding: 0.5rem 1rem;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        font-size: 0.875rem;
        background: white;
    }

    .filters select:focus,
    .filters input:focus {
        outline: none;
        border-color: var(--color-primary);
    }

    .result-count {
        color: var(--color-text-muted);
        font-size: 0.9rem;
    }

    .pagination {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        margin-top: 2rem;
    }

    .pagination a,
    .pagination span {
        padding: 0.5rem 1rem;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-sm);
        text-decoration: none;
        color: var(--color-text-soft);
        font-size: 0.9rem;
    }

    .pagination a:hover {
        background: var(--color-bg-subtle);
    }

    .pagination .active {
        background: var(--color-primary);
        color: white;
        border-color: var(--color-primary);
    }

    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: var(--color-text-muted);
    }

    .empty-state-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
    }
</style>

<!-- Category Hero -->
<section class="category-hero">
    <div class="container">
        <h1>
            <?php if ($category): ?>
                <span style="font-size: 2.5rem;"><?= $category['icon'] ?></span>
                <?= escape($category['name']) ?>
            <?php elseif ($search): ?>
                Søgeresultater: "<?= escape($search) ?>"
            <?php else: ?>
                Alle leverandører
            <?php endif; ?>
        </h1>
        <?php if ($category && $category['description']): ?>
            <p><?= escape($category['description']) ?></p>
        <?php endif; ?>
    </div>
</section>

<!-- Filters -->
<section class="filters">
    <div class="container">
        <form method="GET" class="filters-inner">
            <?php if ($slug): ?>
                <input type="hidden" name="slug" value="<?= escape($slug) ?>">
            <?php endif; ?>

            <input type="text" name="q" placeholder="Søg..." value="<?= escape($search) ?>" style="min-width: 200px;">

            <select name="city">
                <option value="">Alle byer</option>
                <?php foreach ($cities as $c): ?>
                    <option value="<?= escape($c) ?>" <?= $city === $c ? 'selected' : '' ?>>
                        <?= escape($c) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="sort">
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Nyeste først</option>
                <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Mest populære</option>
                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Pris: Lav til høj</option>
                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Pris: Høj til lav</option>
                <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Navn A-Z</option>
            </select>

            <button type="submit" class="btn btn-primary">Søg</button>

            <?php if ($search || $city || $sort !== 'newest'): ?>
                <a href="<?= BASE_PATH ?>/partners/category.php<?= $slug ? '?slug=' . escape($slug) : '' ?>" class="btn btn-secondary">
                    Nulstil
                </a>
            <?php endif; ?>

            <span class="result-count" style="margin-left: auto;">
                <?= number_format($totalPartners) ?> leverandør<?= $totalPartners !== 1 ? 'er' : '' ?>
            </span>
        </form>
    </div>
</section>

<div class="container py-xl">
    <?php if (empty($partners)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">&#128533;</div>
            <h3>Ingen leverandører fundet</h3>
            <p>Prøv at ændre dine søgekriterier eller <a href="<?= BASE_PATH ?>/partners/">se alle kategorier</a>.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-3">
            <?php foreach ($partners as $partner): ?>
                <article class="card partner-card">
                    <div class="partner-card__image">
                        <?php if ($partner['cover_image_url']): ?>
                            <img src="<?= escape($partner['cover_image_url']) ?>" alt="<?= escape($partner['company_name']) ?>">
                        <?php else: ?>
                            <div style="display: flex; align-items: center; justify-content: center; height: 100%; font-size: 3rem; color: var(--color-text-muted);">
                                <?= $partner['category_icon'] ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="partner-card__body">
                        <div class="partner-card__category">
                            <?= $partner['category_icon'] ?> <?= escape($partner['category_name']) ?>
                            <?php if ($partner['is_featured']): ?>
                                <span class="badge badge-featured" style="margin-left: 0.5rem;">Fremhævet</span>
                            <?php endif; ?>
                        </div>
                        <h3 class="partner-card__name">
                            <a href="<?= BASE_PATH ?>/partners/profile.php?id=<?= $partner['id'] ?>">
                                <?= escape($partner['company_name']) ?>
                            </a>
                        </h3>
                        <p class="partner-card__desc">
                            <?= escape($partner['short_description'] ?? substr($partner['description'] ?? '', 0, 100)) ?>
                        </p>
                        <div class="partner-card__meta">
                            <div class="partner-card__price">
                                <?= $partner['price_description'] ?? ($partner['price_from'] ? 'Fra ' . number_format($partner['price_from'], 0, ',', '.') . ' kr' : '') ?>
                            </div>
                            <div class="partner-card__location">
                                <?= $partner['nationwide'] ? 'Hele landet' : escape($partner['city'] ?? '') ?>
                            </div>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">&#8592; Forrige</a>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                ?>

                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Næste &#8594;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/partner-footer.php'; ?>

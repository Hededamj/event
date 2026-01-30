<?php
/**
 * Partner Marketplace - Home
 */

$pageTitle = 'Markedsplads';
require_once __DIR__ . '/../includes/partner-header.php';

$db = getDB();

// Get featured partners
$stmt = $db->query("
    SELECT p.*, pc.name as category_name, pc.icon as category_icon, pc.slug as category_slug
    FROM partners p
    JOIN partner_categories pc ON p.category_id = pc.id
    WHERE p.status = 'approved' AND p.is_featured = 1
    ORDER BY RAND()
    LIMIT 6
");
$featuredPartners = $stmt->fetchAll();

// Get partner count per category
$stmt = $db->query("
    SELECT pc.*, COUNT(p.id) as partner_count
    FROM partner_categories pc
    LEFT JOIN partners p ON pc.id = p.category_id AND p.status = 'approved'
    WHERE pc.is_active = 1
    GROUP BY pc.id
    ORDER BY pc.sort_order
");
$categoriesWithCount = $stmt->fetchAll();

// Get recent partners
$stmt = $db->query("
    SELECT p.*, pc.name as category_name, pc.icon as category_icon, pc.slug as category_slug
    FROM partners p
    JOIN partner_categories pc ON p.category_id = pc.id
    WHERE p.status = 'approved'
    ORDER BY p.approved_at DESC
    LIMIT 6
");
$recentPartners = $stmt->fetchAll();
?>

<style>
    .hero {
        background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-deep) 100%);
        padding: 4rem 0;
        text-align: center;
        color: white;
    }

    .hero h1 {
        font-family: 'Playfair Display', serif;
        font-size: 2.5rem;
        font-weight: 600;
        margin-bottom: 1rem;
    }

    .hero p {
        font-size: 1.1rem;
        opacity: 0.9;
        max-width: 600px;
        margin: 0 auto 2rem;
    }

    .search-box {
        max-width: 500px;
        margin: 0 auto;
        position: relative;
    }

    .search-box input {
        width: 100%;
        padding: 1rem 1.25rem 1rem 3rem;
        border: none;
        border-radius: var(--radius-lg);
        font-size: 1rem;
        box-shadow: var(--shadow-lg);
    }

    .search-box input:focus {
        outline: none;
        box-shadow: var(--shadow-xl);
    }

    .search-box-icon {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        font-size: 1.2rem;
        color: var(--color-text-muted);
    }

    .categories-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 1rem;
    }

    .category-card {
        background: var(--color-surface);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        text-align: center;
        text-decoration: none;
        color: inherit;
        transition: all 0.3s;
        border: 1px solid var(--color-border);
    }

    .category-card:hover {
        border-color: var(--color-primary);
        box-shadow: var(--shadow-md);
        transform: translateY(-2px);
    }

    .category-card__icon {
        font-size: 2.5rem;
        margin-bottom: 0.75rem;
    }

    .category-card__name {
        font-weight: 600;
        margin-bottom: 0.25rem;
    }

    .category-card__count {
        font-size: 0.85rem;
        color: var(--color-text-muted);
    }

    .section {
        padding: 3rem 0;
    }

    .section-title {
        font-family: 'Playfair Display', serif;
        font-size: 1.75rem;
        margin-bottom: 1.5rem;
    }

    .section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.5rem;
    }
</style>

<!-- Hero Section -->
<section class="hero">
    <div class="container">
        <h1>Find de bedste leverandører til dit event</h1>
        <p>Udforsk vores markedsplads med kvalitetsleverandører inden for telte, catering, lokaler, musik og meget mere.</p>

        <form action="<?= BASE_PATH ?>/partners/category.php" method="GET" class="search-box">
            <span class="search-box-icon">&#128269;</span>
            <input type="text" name="q" placeholder="Søg efter leverandører..." autocomplete="off">
        </form>
    </div>
</section>

<div class="container">
    <!-- Categories -->
    <section class="section">
        <h2 class="section-title">Udforsk kategorier</h2>

        <div class="categories-grid">
            <?php foreach ($categoriesWithCount as $cat): ?>
                <a href="<?= BASE_PATH ?>/partners/category.php?slug=<?= escape($cat['slug']) ?>" class="category-card">
                    <div class="category-card__icon"><?= $cat['icon'] ?></div>
                    <div class="category-card__name"><?= escape($cat['name']) ?></div>
                    <div class="category-card__count"><?= $cat['partner_count'] ?> leverandører</div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Featured Partners -->
    <?php if (!empty($featuredPartners)): ?>
    <section class="section">
        <div class="section-header">
            <h2 class="section-title">Fremhævede leverandører</h2>
        </div>

        <div class="grid grid-3">
            <?php foreach ($featuredPartners as $partner): ?>
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
                            <span class="badge badge-featured" style="margin-left: 0.5rem;">Fremhævet</span>
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
    </section>
    <?php endif; ?>

    <!-- Recent Partners -->
    <?php if (!empty($recentPartners)): ?>
    <section class="section">
        <div class="section-header">
            <h2 class="section-title">Nyeste leverandører</h2>
            <a href="<?= BASE_PATH ?>/partners/category.php" class="btn btn-secondary">Se alle</a>
        </div>

        <div class="grid grid-3">
            <?php foreach ($recentPartners as $partner): ?>
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
    </section>
    <?php endif; ?>

    <!-- CTA Section -->
    <section class="section" style="text-align: center; padding: 4rem 2rem; background: var(--color-bg-subtle); border-radius: var(--radius-xl); margin-bottom: 2rem;">
        <h2 style="font-family: 'Playfair Display', serif; font-size: 2rem; margin-bottom: 1rem;">
            Er du leverandør?
        </h2>
        <p style="color: var(--color-text-soft); max-width: 500px; margin: 0 auto 1.5rem;">
            Bliv en del af vores markedsplads og nå ud til tusindvis af eventplanlæggere, der leder efter kvalitetsleverandører som dig.
        </p>
        <a href="<?= BASE_PATH ?>/partners/register.php" class="btn btn-primary">Opret partnerprofil</a>
    </section>
</div>

<?php require_once __DIR__ . '/../includes/partner-footer.php'; ?>

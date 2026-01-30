<?php
/**
 * Partner Marketplace - Partner Profile
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDB();

$partnerId = (int)($_GET['id'] ?? 0);

if (!$partnerId) {
    redirect(BASE_PATH . '/partners/');
}

// Get partner
$stmt = $db->prepare("
    SELECT p.*, pc.name as category_name, pc.icon as category_icon, pc.slug as category_slug
    FROM partners p
    JOIN partner_categories pc ON p.category_id = pc.id
    WHERE p.id = ? AND p.status = 'approved'
");
$stmt->execute([$partnerId]);
$partner = $stmt->fetch();

if (!$partner) {
    redirect(BASE_PATH . '/partners/');
}

// Increment view count
$stmt = $db->prepare("UPDATE partners SET view_count = view_count + 1 WHERE id = ?");
$stmt->execute([$partnerId]);

// Get gallery images
$stmt = $db->prepare("SELECT * FROM partner_gallery WHERE partner_id = ? ORDER BY sort_order");
$stmt->execute([$partnerId]);
$gallery = $stmt->fetchAll();

// Get similar partners
$stmt = $db->prepare("
    SELECT p.*, pc.name as category_name, pc.icon as category_icon
    FROM partners p
    JOIN partner_categories pc ON p.category_id = pc.id
    WHERE p.category_id = ? AND p.id != ? AND p.status = 'approved'
    ORDER BY RAND()
    LIMIT 3
");
$stmt->execute([$partner['category_id'], $partnerId]);
$similarPartners = $stmt->fetchAll();

$pageTitle = $partner['company_name'];
$currentCategory = $partner['category_slug'];

require_once __DIR__ . '/../includes/partner-header.php';
?>

<style>
    .profile-hero {
        background: var(--color-surface);
        padding: 2rem 0;
        border-bottom: 1px solid var(--color-border);
    }

    .profile-header {
        display: grid;
        grid-template-columns: 1fr 300px;
        gap: 2rem;
    }

    .profile-cover {
        aspect-ratio: 21/9;
        background: var(--color-bg-subtle);
        border-radius: var(--radius-lg);
        overflow: hidden;
        margin-bottom: 1.5rem;
    }

    .profile-cover img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .profile-info {
        display: flex;
        gap: 1.5rem;
        align-items: flex-start;
    }

    .profile-logo {
        width: 80px;
        height: 80px;
        border-radius: var(--radius-lg);
        background: var(--color-bg-subtle);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        flex-shrink: 0;
        overflow: hidden;
    }

    .profile-logo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .profile-name h1 {
        font-family: 'Playfair Display', serif;
        font-size: 1.75rem;
        margin-bottom: 0.5rem;
    }

    .profile-category {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--color-text-soft);
        margin-bottom: 0.5rem;
    }

    .profile-stats {
        display: flex;
        gap: 1.5rem;
        font-size: 0.9rem;
        color: var(--color-text-muted);
    }

    .contact-card {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        position: sticky;
        top: 100px;
    }

    .contact-card h3 {
        font-size: 1.1rem;
        margin-bottom: 1rem;
    }

    .contact-price {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--color-primary);
        margin-bottom: 1rem;
    }

    .contact-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 0;
        border-bottom: 1px solid var(--color-border);
        font-size: 0.9rem;
    }

    .contact-item:last-of-type {
        border-bottom: none;
    }

    .contact-item-icon {
        width: 20px;
        text-align: center;
        color: var(--color-text-muted);
    }

    .contact-item a {
        color: var(--color-primary);
        text-decoration: none;
    }

    .contact-item a:hover {
        text-decoration: underline;
    }

    .profile-content {
        padding: 2rem 0;
    }

    .profile-main {
        display: grid;
        grid-template-columns: 1fr 350px;
        gap: 2rem;
    }

    .profile-section {
        margin-bottom: 2rem;
    }

    .profile-section h2 {
        font-size: 1.25rem;
        font-weight: 600;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid var(--color-border);
    }

    .profile-description {
        line-height: 1.8;
        color: var(--color-text-soft);
    }

    .gallery-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.5rem;
    }

    .gallery-item {
        aspect-ratio: 1;
        border-radius: var(--radius-md);
        overflow: hidden;
        cursor: pointer;
    }

    .gallery-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s;
    }

    .gallery-item:hover img {
        transform: scale(1.05);
    }

    .inquiry-form {
        background: var(--color-bg-subtle);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
    }

    .inquiry-form h3 {
        font-size: 1.1rem;
        margin-bottom: 1rem;
    }

    .similar-partners {
        padding: 2rem 0;
        background: var(--color-bg-subtle);
    }

    @media (max-width: 968px) {
        .profile-header,
        .profile-main {
            grid-template-columns: 1fr;
        }

        .contact-card {
            position: static;
        }
    }
</style>

<!-- Profile Hero -->
<section class="profile-hero">
    <div class="container">
        <?php if ($partner['cover_image_url']): ?>
            <div class="profile-cover">
                <img src="<?= escape($partner['cover_image_url']) ?>" alt="<?= escape($partner['company_name']) ?>">
            </div>
        <?php endif; ?>

        <div class="profile-info">
            <div class="profile-logo">
                <?php if ($partner['logo_url']): ?>
                    <img src="<?= escape($partner['logo_url']) ?>" alt="">
                <?php else: ?>
                    <?= $partner['category_icon'] ?>
                <?php endif; ?>
            </div>

            <div class="profile-name">
                <h1><?= escape($partner['company_name']) ?></h1>
                <div class="profile-category">
                    <?= $partner['category_icon'] ?> <?= escape($partner['category_name']) ?>
                    <?php if ($partner['is_featured']): ?>
                        <span class="badge badge-featured">Fremhævet</span>
                    <?php endif; ?>
                </div>
                <div class="profile-stats">
                    <span>&#128065; <?= number_format($partner['view_count']) ?> visninger</span>
                    <?php if ($partner['city']): ?>
                        <span>&#128205; <?= $partner['nationwide'] ? 'Hele landet' : escape($partner['city']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="container">
    <div class="profile-main profile-content">
        <!-- Main Content -->
        <div>
            <!-- Description -->
            <div class="profile-section">
                <h2>Om <?= escape($partner['company_name']) ?></h2>
                <div class="profile-description">
                    <?= nl2br(escape($partner['description'] ?? 'Ingen beskrivelse tilgængelig.')) ?>
                </div>
            </div>

            <!-- Gallery -->
            <?php if (!empty($gallery)): ?>
                <div class="profile-section">
                    <h2>Galleri</h2>
                    <div class="gallery-grid">
                        <?php foreach ($gallery as $image): ?>
                            <div class="gallery-item">
                                <img src="<?= escape($image['image_url']) ?>" alt="<?= escape($image['caption'] ?? '') ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Inquiry Form -->
            <div class="profile-section">
                <div class="inquiry-form">
                    <h3>Send en forespørgsel</h3>

                    <?php if ($flash): ?>
                        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                            <?= escape($flash['message']) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?= BASE_PATH ?>/partners/inquiry.php">
                        <input type="hidden" name="partner_id" value="<?= $partnerId ?>">
                        <?= csrfField() ?>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label class="form-label">Dit navn *</label>
                                <input type="text" name="name" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" class="form-input" required>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label class="form-label">Telefon</label>
                                <input type="tel" name="phone" class="form-input">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Eventdato</label>
                                <input type="date" name="event_date" class="form-input">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Antal gæster</label>
                            <input type="number" name="guest_count" class="form-input" min="1">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Besked *</label>
                            <textarea name="message" class="form-input" rows="4" required
                                      placeholder="Beskriv dit event og hvad du søger hjælp til..."></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            Send forespørgsel
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <aside>
            <div class="contact-card">
                <h3>Kontakt</h3>

                <?php if ($partner['price_from'] || $partner['price_description']): ?>
                    <div class="contact-price">
                        <?= $partner['price_description'] ?? ('Fra ' . number_format($partner['price_from'], 0, ',', '.') . ' kr') ?>
                    </div>
                <?php endif; ?>

                <?php if ($partner['phone']): ?>
                    <div class="contact-item">
                        <span class="contact-item-icon">&#128222;</span>
                        <a href="tel:<?= escape($partner['phone']) ?>"><?= escape($partner['phone']) ?></a>
                    </div>
                <?php endif; ?>

                <?php if ($partner['email']): ?>
                    <div class="contact-item">
                        <span class="contact-item-icon">&#9993;</span>
                        <a href="mailto:<?= escape($partner['email']) ?>"><?= escape($partner['email']) ?></a>
                    </div>
                <?php endif; ?>

                <?php if ($partner['website']): ?>
                    <div class="contact-item">
                        <span class="contact-item-icon">&#127760;</span>
                        <a href="<?= escape($partner['website']) ?>" target="_blank" rel="noopener">
                            Besøg hjemmeside
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($partner['address'] || $partner['city']): ?>
                    <div class="contact-item">
                        <span class="contact-item-icon">&#128205;</span>
                        <span>
                            <?php if ($partner['address']): ?>
                                <?= escape($partner['address']) ?><br>
                            <?php endif; ?>
                            <?php if ($partner['postal_code'] || $partner['city']): ?>
                                <?= escape(trim($partner['postal_code'] . ' ' . $partner['city'])) ?>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endif; ?>

                <?php if ($partner['nationwide']): ?>
                    <div class="contact-item">
                        <span class="contact-item-icon">&#127758;</span>
                        <span>Dækker hele landet</span>
                    </div>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</div>

<!-- Similar Partners -->
<?php if (!empty($similarPartners)): ?>
    <section class="similar-partners">
        <div class="container">
            <h2 style="font-family: 'Playfair Display', serif; font-size: 1.5rem; margin-bottom: 1.5rem;">
                Lignende leverandører
            </h2>
            <div class="grid grid-3">
                <?php foreach ($similarPartners as $similar): ?>
                    <article class="card partner-card">
                        <div class="partner-card__image">
                            <?php if ($similar['cover_image_url']): ?>
                                <img src="<?= escape($similar['cover_image_url']) ?>" alt="<?= escape($similar['company_name']) ?>">
                            <?php else: ?>
                                <div style="display: flex; align-items: center; justify-content: center; height: 100%; font-size: 3rem; color: var(--color-text-muted);">
                                    <?= $similar['category_icon'] ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="partner-card__body">
                            <div class="partner-card__category">
                                <?= $similar['category_icon'] ?> <?= escape($similar['category_name']) ?>
                            </div>
                            <h3 class="partner-card__name">
                                <a href="<?= BASE_PATH ?>/partners/profile.php?id=<?= $similar['id'] ?>">
                                    <?= escape($similar['company_name']) ?>
                                </a>
                            </h3>
                            <p class="partner-card__desc">
                                <?= escape($similar['short_description'] ?? substr($similar['description'] ?? '', 0, 80)) ?>...
                            </p>
                            <div class="partner-card__meta">
                                <div class="partner-card__price">
                                    <?= $similar['price_description'] ?? ($similar['price_from'] ? 'Fra ' . number_format($similar['price_from'], 0, ',', '.') . ' kr' : '') ?>
                                </div>
                                <div class="partner-card__location">
                                    <?= $similar['nationwide'] ? 'Hele landet' : escape($similar['city'] ?? '') ?>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/partner-footer.php'; ?>

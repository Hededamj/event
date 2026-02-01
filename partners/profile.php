<?php
/**
 * Partner Marketplace - Partner Profile
 */

require_once __DIR__ . '/../config/saas.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/partner-auth.php';
require_once __DIR__ . '/../includes/partner-reviews.php';

$db = getDB();

// Ensure review tables exist
ensureReviewTables($db);

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

// Get reviews and rating summary
$ratingSummary = getPartnerRatingSummary($db, $partnerId);
$reviews = getPartnerReviews($db, $partnerId, 5);

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

    /* Star Rating */
    .star-rating { display: inline-flex; align-items: center; gap: 0.25rem; }
    .star { color: #fbbf24; font-size: 1rem; }
    .star--empty { color: #d1d5db; }
    .star-rating__number { margin-left: 0.5rem; font-weight: 600; color: var(--color-text); }

    .profile-rating { display: inline-flex; align-items: center; gap: 0.5rem; }

    /* Rating Summary */
    .rating-summary {
        display: flex;
        gap: 2rem;
        padding: 1.5rem;
        background: var(--color-bg-subtle);
        border-radius: var(--radius-lg);
        margin-bottom: 1.5rem;
    }
    .rating-summary__score { text-align: center; min-width: 120px; }
    .rating-summary__number { font-size: 3rem; font-weight: 700; color: var(--color-text); line-height: 1; }
    .rating-summary__stars { margin: 0.5rem 0; }
    .rating-summary__stars .star { font-size: 1.25rem; }
    .rating-summary__count { font-size: 0.875rem; color: var(--color-text-muted); }
    .rating-summary__breakdown { flex: 1; }

    .rating-bar { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.375rem; }
    .rating-bar__label { font-size: 0.8rem; width: 35px; color: var(--color-text-soft); }
    .rating-bar__track { flex: 1; height: 8px; background: var(--color-border); border-radius: 4px; overflow: hidden; }
    .rating-bar__fill { height: 100%; background: #fbbf24; border-radius: 4px; }
    .rating-bar__count { font-size: 0.8rem; width: 25px; text-align: right; color: var(--color-text-muted); }

    /* Reviews */
    .review-list { display: flex; flex-direction: column; gap: 1rem; }
    .review-card {
        padding: 1.25rem;
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
    }
    .review-card__header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem; }
    .review-card__author { display: flex; align-items: center; gap: 0.75rem; }
    .review-card__avatar {
        width: 40px; height: 40px; border-radius: 50%;
        background: var(--color-primary-soft); color: var(--color-primary);
        display: flex; align-items: center; justify-content: center;
        font-weight: 600; font-size: 1rem;
    }
    .review-card__name { font-weight: 500; display: flex; align-items: center; gap: 0.5rem; }
    .review-card__date { font-size: 0.8rem; color: var(--color-text-muted); }
    .review-card__rating .star { font-size: 0.9rem; }
    .review-card__title { font-size: 1rem; margin-bottom: 0.5rem; }
    .review-card__text { color: var(--color-text-soft); line-height: 1.6; }
    .review-card__event { font-size: 0.8rem; color: var(--color-text-muted); margin-top: 0.75rem; }
    .review-card__response {
        margin-top: 1rem; padding: 1rem;
        background: var(--color-bg-subtle); border-radius: var(--radius-md);
        font-size: 0.9rem;
    }
    .review-card__response strong { display: block; margin-bottom: 0.5rem; }

    .badge-verified { font-size: 0.7rem; padding: 0.15rem 0.4rem; background: #dcfce7; color: #166534; border-radius: 4px; }

    .empty-reviews { text-align: center; padding: 2rem; color: var(--color-text-muted); }

    /* Modal */
    .modal-overlay {
        display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5);
        z-index: 1000; align-items: center; justify-content: center; padding: 1rem;
    }
    .modal-overlay.active { display: flex; }
    .modal {
        background: white; border-radius: var(--radius-lg);
        max-width: 500px; width: 100%; max-height: 90vh; overflow-y: auto;
    }
    .modal__header {
        padding: 1.25rem; border-bottom: 1px solid var(--color-border);
        display: flex; justify-content: space-between; align-items: center;
    }
    .modal__title { font-size: 1.1rem; font-weight: 600; }
    .modal__close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--color-text-muted); }
    .modal__body { padding: 1.25rem; }
    .modal__footer { padding: 1.25rem; border-top: 1px solid var(--color-border); display: flex; justify-content: flex-end; gap: 0.75rem; }

    /* Star Input */
    .star-input { display: flex; gap: 0.25rem; font-size: 1.5rem; }
    .star-input label { cursor: pointer; color: #d1d5db; transition: color 0.15s; }
    .star-input label:hover,
    .star-input label:hover ~ label,
    .star-input input:checked ~ label { color: #fbbf24; }
    .star-input { flex-direction: row-reverse; justify-content: flex-end; }
    .star-input input { display: none; }

    @media (max-width: 968px) {
        .profile-header,
        .profile-main {
            grid-template-columns: 1fr;
        }

        .contact-card {
            position: static;
        }

        .rating-summary {
            flex-direction: column;
            gap: 1rem;
        }
        .rating-summary__score {
            display: flex;
            align-items: center;
            gap: 1rem;
            text-align: left;
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
                    <?php if ($ratingSummary['total_reviews'] > 0): ?>
                        <span class="profile-rating">
                            <?= renderStars($ratingSummary['average_rating']) ?>
                            <a href="#reviews" style="color: inherit;">(<?= $ratingSummary['total_reviews'] ?> anmeldelse<?= $ratingSummary['total_reviews'] > 1 ? 'r' : '' ?>)</a>
                        </span>
                    <?php endif; ?>
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

            <!-- Reviews Section -->
            <div class="profile-section" id="reviews">
                <h2>Anmeldelser <?php if ($ratingSummary['total_reviews'] > 0): ?>(<?= $ratingSummary['total_reviews'] ?>)<?php endif; ?></h2>

                <?php if ($ratingSummary['total_reviews'] > 0): ?>
                    <!-- Rating Summary -->
                    <div class="rating-summary">
                        <div class="rating-summary__score">
                            <div class="rating-summary__number"><?= number_format($ratingSummary['average_rating'], 1) ?></div>
                            <div class="rating-summary__stars"><?= renderStars($ratingSummary['average_rating'], false) ?></div>
                            <div class="rating-summary__count"><?= $ratingSummary['total_reviews'] ?> anmeldelse<?= $ratingSummary['total_reviews'] > 1 ? 'r' : '' ?></div>
                        </div>
                        <div class="rating-summary__breakdown">
                            <?php foreach ([5, 4, 3, 2, 1] as $stars): ?>
                                <?php
                                $starKey = ['', 'one_star', 'two_star', 'three_star', 'four_star', 'five_star'][$stars];
                                $count = $ratingSummary[$starKey] ?? 0;
                                $pct = $ratingSummary['total_reviews'] > 0 ? ($count / $ratingSummary['total_reviews']) * 100 : 0;
                                ?>
                                <div class="rating-bar">
                                    <span class="rating-bar__label"><?= $stars ?> ★</span>
                                    <div class="rating-bar__track">
                                        <div class="rating-bar__fill" style="width: <?= $pct ?>%;"></div>
                                    </div>
                                    <span class="rating-bar__count"><?= $count ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Review List -->
                    <div class="review-list">
                        <?php foreach ($reviews as $review): ?>
                            <div class="review-card">
                                <div class="review-card__header">
                                    <div class="review-card__author">
                                        <div class="review-card__avatar"><?= strtoupper(substr($review['reviewer_name'], 0, 1)) ?></div>
                                        <div>
                                            <div class="review-card__name">
                                                <?= escape($review['reviewer_name']) ?>
                                                <?php if ($review['is_verified']): ?>
                                                    <span class="badge badge-verified" title="Verificeret køb">✓ Verificeret</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="review-card__date"><?= formatReviewDate($review['created_at']) ?></div>
                                        </div>
                                    </div>
                                    <div class="review-card__rating"><?= renderStars($review['rating'], false) ?></div>
                                </div>

                                <?php if ($review['title']): ?>
                                    <h4 class="review-card__title"><?= escape($review['title']) ?></h4>
                                <?php endif; ?>

                                <p class="review-card__text"><?= nl2br(escape($review['review_text'])) ?></p>

                                <?php if ($review['event_type']): ?>
                                    <div class="review-card__event">
                                        Event: <?= escape($review['event_type']) ?>
                                        <?php if ($review['event_date']): ?>
                                            (<?= date('M Y', strtotime($review['event_date'])) ?>)
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($review['partner_response']): ?>
                                    <div class="review-card__response">
                                        <strong><?= escape($partner['company_name']) ?> svarede:</strong>
                                        <p><?= nl2br(escape($review['partner_response'])) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($ratingSummary['total_reviews'] > 5): ?>
                        <div style="text-align: center; margin-top: 1rem;">
                            <a href="<?= BASE_PATH ?>/partners/reviews.php?id=<?= $partnerId ?>" class="btn btn-secondary">
                                Se alle <?= $ratingSummary['total_reviews'] ?> anmeldelser
                            </a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-reviews">
                        <p>Ingen anmeldelser endnu</p>
                        <p class="text-muted">Vær den første til at anmelde <?= escape($partner['company_name']) ?></p>
                    </div>
                <?php endif; ?>

                <!-- Write Review Button -->
                <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--color-border);">
                    <button onclick="openModal('review-modal')" class="btn btn-secondary">
                        ✍️ Skriv en anmeldelse
                    </button>
                </div>
            </div>

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

<!-- Review Modal -->
<div class="modal-overlay" id="review-modal">
    <div class="modal">
        <div class="modal__header">
            <h3 class="modal__title">Skriv en anmeldelse</h3>
            <button class="modal__close" onclick="closeModal('review-modal')">&times;</button>
        </div>
        <form method="POST" action="<?= BASE_PATH ?>/partners/submit-review.php">
            <div class="modal__body">
                <input type="hidden" name="partner_id" value="<?= $partnerId ?>">
                <?= csrfField() ?>

                <div class="form-group">
                    <label class="form-label">Din bedømmelse *</label>
                    <div class="star-input">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>" required>
                            <label for="star<?= $i ?>">★</label>
                        <?php endfor; ?>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Dit navn *</label>
                        <input type="text" name="reviewer_name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="reviewer_email" class="form-input" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Overskrift</label>
                    <input type="text" name="title" class="form-input" placeholder="Opsummer din oplevelse">
                </div>

                <div class="form-group">
                    <label class="form-label">Din anmeldelse *</label>
                    <textarea name="review_text" class="form-input" rows="4" required
                              placeholder="Beskriv din oplevelse med <?= escape($partner['company_name']) ?>..."></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Event type</label>
                        <select name="event_type" class="form-input">
                            <option value="">Vælg...</option>
                            <option value="Bryllup">Bryllup</option>
                            <option value="Fødselsdag">Fødselsdag</option>
                            <option value="Konfirmation">Konfirmation</option>
                            <option value="Firmafest">Firmafest</option>
                            <option value="Barnedåb">Barnedåb</option>
                            <option value="Jubilæum">Jubilæum</option>
                            <option value="Andet">Andet</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Event dato</label>
                        <input type="date" name="event_date" class="form-input">
                    </div>
                </div>

                <div style="font-size: 0.8rem; color: var(--color-text-muted); margin-top: 0.5rem;">
                    Din anmeldelse vil blive gennemgået før publicering.
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('review-modal')">Annuller</button>
                <button type="submit" class="btn btn-primary">Send anmeldelse</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).classList.add('active');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) closeModal(overlay.id);
    });
});
</script>

<?php require_once __DIR__ . '/../includes/partner-footer.php'; ?>

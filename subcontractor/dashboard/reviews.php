<?php
/**
 * Vendor Reviews
 * Shows all reviews with average rating, and allows vendor to respond.
 */

$pageTitle = 'Anmeldelser';
require_once __DIR__ . '/../includes/vendor-header.php';
require_once __DIR__ . '/../includes/review-service.php';

$db = getDB();

// ── Handle POST: respond to review ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'respond') {
        // Verify CSRF
        if (!verifyVendorCsrfToken($_POST['csrf_token'] ?? '')) {
            setFlash('error', 'Ugyldig anmodning. Prøv igen.');
            redirect('/subcontractor/dashboard/reviews.php');
        }

        $reviewId = (int)($_POST['review_id'] ?? 0);
        $response = trim($_POST['response'] ?? '');

        if ($reviewId <= 0) {
            setFlash('error', 'Ugyldig anmeldelse.');
            redirect('/subcontractor/dashboard/reviews.php');
        }

        if (empty($response)) {
            setFlash('error', 'Svaret maa ikke vaere tomt.');
            redirect('/subcontractor/dashboard/reviews.php');
        }

        if (mb_strlen($response) > 2000) {
            setFlash('error', 'Svaret maa maks vaere 2000 tegn.');
            redirect('/subcontractor/dashboard/reviews.php');
        }

        $success = respondToReview($reviewId, $vendorId, $response);

        if ($success) {
            setFlash('success', 'Dit svar er blevet gemt.');
        } else {
            setFlash('error', 'Kunne ikke gemme svaret. Prøv igen.');
        }

        redirect('/subcontractor/dashboard/reviews.php');
    }
}

// ── Fetch vendor rating stats ─────────────────────────────────────
$stmt = $db->prepare("SELECT avg_rating, review_count FROM vendors WHERE id = ? LIMIT 1");
$stmt->execute([$vendorId]);
$vendorStats = $stmt->fetch();
$avgRating   = (float)($vendorStats['avg_rating'] ?? 0);
$reviewCount = (int)($vendorStats['review_count'] ?? 0);

// ── Fetch reviews ─────────────────────────────────────────────────
$reviews = getVendorReviews($vendorId, 20, 0);

/** Format a date in Danish style */
if (!function_exists('formatDanishDateReviews')) {
    function formatDanishDateReviews(string $dateStr): string {
        $months = [
            1 => 'januar', 2 => 'februar', 3 => 'marts', 4 => 'april',
            5 => 'maj', 6 => 'juni', 7 => 'juli', 8 => 'august',
            9 => 'september', 10 => 'oktober', 11 => 'november', 12 => 'december'
        ];
        $ts = strtotime($dateStr);
        if (!$ts) return $dateStr;
        $day   = (int)date('j', $ts);
        $month = $months[(int)date('n', $ts)];
        $year  = date('Y', $ts);
        return "{$day}. {$month} {$year}";
    }
}

/** Render star icons for a given rating (1-5) */
if (!function_exists('renderStarsReview')) {
    function renderStarsReview(int $rating): string {
        $html = '<div class="stars">';
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $rating) {
                $html .= '<svg class="star-filled" viewBox="0 0 24 24" fill="currentColor"><path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path></svg>';
            } else {
                $html .= '<svg class="star-empty" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path></svg>';
            }
        }
        $html .= '</div>';
        return $html;
    }
}

/** Render a float average rating as stars */
if (!function_exists('renderAvgStars')) {
    function renderAvgStars(float $rating): string {
        $fullStars = (int)floor($rating);
        $halfStar  = ($rating - $fullStars) >= 0.25;
        $html = '<div class="stars stars-lg">';
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $fullStars) {
                $html .= '<svg class="star-filled" viewBox="0 0 24 24" fill="currentColor"><path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path></svg>';
            } elseif ($i === $fullStars + 1 && $halfStar) {
                $html .= '<svg class="star-filled" viewBox="0 0 24 24" fill="currentColor" style="opacity:0.5;"><path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path></svg>';
            } else {
                $html .= '<svg class="star-empty" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path></svg>';
            }
        }
        $html .= '</div>';
        return $html;
    }
}
?>

<style>
    .reviews-summary {
        display: flex;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
    }
    .reviews-avg-number {
        font-family: var(--font-display);
        font-size: 42px;
        font-weight: 600;
        color: var(--text);
        line-height: 1;
    }
    .reviews-avg-detail {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .reviews-avg-detail .stars-lg svg {
        width: 22px;
        height: 22px;
    }
    .reviews-count-label {
        font-size: 14px;
        color: var(--text-secondary);
    }
    .review-item {
        padding: 24px 0;
        border-bottom: 1px solid var(--border);
    }
    .review-item:first-child {
        padding-top: 0;
    }
    .review-item:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }
    .review-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 10px;
        gap: 12px;
        flex-wrap: wrap;
    }
    .review-header-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .review-reviewer {
        font-weight: 600;
        font-size: 15px;
        color: var(--text);
    }
    .review-date {
        font-size: 13px;
        color: var(--text-secondary);
    }
    .review-title {
        font-weight: 600;
        font-size: 16px;
        color: var(--text);
        margin-bottom: 6px;
    }
    .review-text {
        font-size: 14px;
        line-height: 1.7;
        color: var(--text);
        margin: 0;
    }
    .review-response {
        margin-top: 16px;
        padding: 16px 20px;
        background: var(--surface);
        border-radius: 12px;
        border-left: 3px solid var(--accent);
    }
    .review-response-label {
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--accent-dark);
        margin-bottom: 6px;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .review-response-label span {
        font-weight: 400;
        text-transform: none;
        letter-spacing: 0;
        color: var(--text-secondary);
    }
    .review-response-text {
        font-size: 14px;
        line-height: 1.6;
        color: var(--text);
        margin: 0;
    }
    .review-respond-form {
        margin-top: 16px;
    }
    .review-respond-form textarea {
        width: 100%;
        padding: 12px 14px;
        border: 2px solid var(--border);
        border-radius: 10px;
        font-family: var(--font-body);
        font-size: 14px;
        line-height: 1.6;
        resize: vertical;
        min-height: 80px;
        transition: border-color 0.2s var(--ease-out);
        box-sizing: border-box;
    }
    .review-respond-form textarea:focus {
        outline: none;
        border-color: var(--accent);
    }
    .review-respond-form .form-actions {
        margin-top: 10px;
        display: flex;
        justify-content: flex-end;
    }

    @media (max-width: 768px) {
        .reviews-avg-number {
            font-size: 32px;
        }
        .review-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

<!-- Page Header -->
<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Anmeldelser</h1>
        <p class="page-subtitle">Se og besvar kundeanmeldelser</p>
    </div>
</div>

<!-- Rating Summary -->
<div class="card" style="margin-bottom: 28px;">
    <div class="card-body">
        <?php if ($reviewCount > 0): ?>
            <div class="reviews-summary">
                <div class="reviews-avg-number"><?= number_format($avgRating, 1, ',', '.') ?></div>
                <div class="reviews-avg-detail">
                    <?= renderAvgStars($avgRating) ?>
                    <div class="reviews-count-label"><?= $reviewCount ?> anmeldelse<?= $reviewCount !== 1 ? 'r' : '' ?> i alt</div>
                </div>
            </div>
        <?php else: ?>
            <div class="reviews-summary">
                <div class="reviews-avg-number">--</div>
                <div class="reviews-avg-detail">
                    <?= renderAvgStars(0) ?>
                    <div class="reviews-count-label">Ingen anmeldelser endnu</div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Reviews List -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Alle anmeldelser</h2>
    </div>
    <div class="card-body">
        <?php if (empty($reviews)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                    </svg>
                </div>
                <h3>Ingen anmeldelser endnu</h3>
                <p>Anmeldelser fra dine kunder vises her, naar de har bedoemt dig efter en afsluttet booking.</p>
            </div>
        <?php else: ?>
            <?php foreach ($reviews as $review): ?>
                <div class="review-item">
                    <!-- Review header: stars, name, date -->
                    <div class="review-header">
                        <div class="review-header-left">
                            <?= renderStarsReview((int)$review['rating']) ?>
                            <span class="review-reviewer"><?= htmlspecialchars($review['reviewer_name']) ?></span>
                        </div>
                        <span class="review-date"><?= formatDanishDateReviews($review['created_at']) ?></span>
                    </div>

                    <!-- Review title & text -->
                    <?php if (!empty($review['title'])): ?>
                        <div class="review-title"><?= htmlspecialchars($review['title']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($review['review_text'])): ?>
                        <p class="review-text"><?= nl2br(htmlspecialchars($review['review_text'])) ?></p>
                    <?php endif; ?>

                    <!-- Vendor response or respond form -->
                    <?php if (!empty($review['vendor_response'])): ?>
                        <div class="review-response">
                            <div class="review-response-label">
                                Dit svar
                                <?php if (!empty($review['vendor_responded_at'])): ?>
                                    <span>- <?= formatDanishDateReviews($review['vendor_responded_at']) ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="review-response-text"><?= nl2br(htmlspecialchars($review['vendor_response'])) ?></p>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="/subcontractor/dashboard/reviews.php" class="review-respond-form">
                            <?= vendorCsrfField() ?>
                            <input type="hidden" name="action" value="respond">
                            <input type="hidden" name="review_id" value="<?= (int)$review['id'] ?>">
                            <textarea
                                name="response"
                                placeholder="Skriv dit svar til denne anmeldelse..."
                                maxlength="2000"
                                required
                            ></textarea>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                                    </svg>
                                    Svar
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/vendor-footer.php'; ?>

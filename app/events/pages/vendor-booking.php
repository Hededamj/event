<?php
/**
 * Vendor Booking Detail Page (Organizer View)
 * Included by manage.php when $page === 'vendor-booking'
 *
 * Available variables: $event, $eventId, $accountId, $db
 */

require_once __DIR__ . '/../../../subcontractor/includes/booking-service.php';
require_once __DIR__ . '/../../../subcontractor/includes/review-service.php';

// ── Load booking ────────────────────────────────────────────
$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

if (!$bookingId) {
    setFlash('error', 'Ugyldig booking.');
    redirect("?id=$eventId&page=vendors");
}

$booking = getBooking($bookingId);

if (!$booking || (int)$booking['event_id'] !== $eventId) {
    setFlash('error', 'Booking ikke fundet.');
    redirect("?id=$eventId&page=vendors");
}

// ── Handle POST actions ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyAccountCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Ugyldig anmodning.');
        redirect("?id=$eventId&page=vendor-booking&booking_id=$bookingId");
    }

    $action = $_POST['action'];

    // Accept quote
    if ($action === 'accept') {
        $result = acceptQuote($bookingId, $accountId);
        if ($result['success']) {
            setFlash('success', 'Tilbud accepteret. Du kan nu betale depositum.');
        } else {
            setFlash('error', $result['error'] ?? 'Kunne ikke acceptere tilbud.');
        }
        redirect("?id=$eventId&page=vendor-booking&booking_id=$bookingId");

    // Decline quote
    } elseif ($action === 'decline') {
        $reason = trim($_POST['reason'] ?? '');
        $result = cancelBooking($bookingId, 'organizer', $reason);
        if ($result) {
            setFlash('success', 'Booking afvist.');
        } else {
            setFlash('error', 'Kunne ikke afvise booking.');
        }
        redirect("?id=$eventId&page=vendors");

    // Complete booking
    } elseif ($action === 'complete') {
        $result = completeBooking($bookingId, $accountId);
        if ($result) {
            setFlash('success', 'Booking markeret som afsluttet.');
        } else {
            setFlash('error', 'Kunne ikke afslutte booking.');
        }
        redirect("?id=$eventId&page=vendor-booking&booking_id=$bookingId");

    // Cancel booking
    } elseif ($action === 'cancel') {
        $reason = trim($_POST['reason'] ?? '');
        $result = cancelBooking($bookingId, 'organizer', $reason);
        if ($result) {
            setFlash('success', 'Booking annulleret.');
        } else {
            setFlash('error', 'Kunne ikke annullere booking.');
        }
        redirect("?id=$eventId&page=vendors");

    // Submit review
    } elseif ($action === 'review') {
        $rating = (int)($_POST['rating'] ?? 0);
        $title  = trim($_POST['title'] ?? '');
        $text   = trim($_POST['review_text'] ?? '');
        $result = createReview($bookingId, (int)$booking['vendor_id'], $accountId, $rating, $title ?: null, $text ?: null);
        if ($result['success']) {
            setFlash('success', 'Tak for din anmeldelse!');
        } else {
            setFlash('error', $result['error'] ?? 'Kunne ikke oprette anmeldelse.');
        }
        redirect("?id=$eventId&page=vendor-booking&booking_id=$bookingId");

    // Send message
    } elseif ($action === 'message') {
        $message = trim($_POST['message'] ?? '');
        if ($message) {
            sendBookingMessage($bookingId, 'organizer', $message);
        }
        redirect("?id=$eventId&page=vendor-booking&booking_id=$bookingId");
    }
}

// ── Fetch additional data ───────────────────────────────────

// Messages
$messages = getBookingMessages($bookingId);
markMessagesRead($bookingId, 'organizer');

// Review (for completed bookings)
$existingReview = null;
if (in_array($booking['status'], ['completed', 'reviewed'])) {
    $existingReview = getReviewForBooking($bookingId);
}

// Status helpers
$statusLabels = [
    'requested'  => 'Anmodet',
    'quoted'     => 'Tilbud modtaget',
    'accepted'   => 'Accepteret',
    'deposited'  => 'Depositum betalt',
    'confirmed'  => 'Bekraeftet',
    'completed'  => 'Afsluttet',
    'reviewed'   => 'Anmeldt',
    'cancelled'  => 'Annulleret',
    'refunded'   => 'Refunderet',
    'disputed'   => 'Tvist',
];

$statusColors = [
    'requested'  => 'background: #FEF3C7; color: #92400E;',
    'quoted'     => 'background: #DBEAFE; color: #1E40AF;',
    'accepted'   => 'background: #D1FAE5; color: #065F46;',
    'deposited'  => 'background: #D1FAE5; color: #065F46;',
    'confirmed'  => 'background: #D1FAE5; color: #065F46;',
    'completed'  => 'background: #E0E7FF; color: #3730A3;',
    'reviewed'   => 'background: #E0E7FF; color: #3730A3;',
    'cancelled'  => 'background: #FEE2E2; color: #991B1B;',
    'refunded'   => 'background: #FEE2E2; color: #991B1B;',
    'disputed'   => 'background: #FEE2E2; color: #991B1B;',
];

$status = $booking['status'];
$vendorProfileUrl = '/subcontractor/leverandor/' . (int)$booking['vendor_id'];

// Event countdown
$eventCountdown = '';
if (!empty($booking['event_date'])) {
    $eventDate = new DateTime($booking['event_date']);
    $now = new DateTime();
    if ($eventDate > $now) {
        $diff = $now->diff($eventDate);
        if ($diff->days === 0) {
            $eventCountdown = 'I dag!';
        } elseif ($diff->days === 1) {
            $eventCountdown = '1 dag';
        } else {
            $eventCountdown = $diff->days . ' dage';
        }
    } else {
        $eventCountdown = 'Afholdt';
    }
}
?>

<style>
    .booking-back-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: var(--charcoal-light);
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        padding: 8px 0;
        margin-bottom: 24px;
        transition: color 0.2s;
    }
    .booking-back-link:hover { color: var(--charcoal); }
    .booking-back-link svg { width: 18px; height: 18px; }

    .booking-grid {
        display: grid;
        grid-template-columns: 1fr 380px;
        gap: 24px;
        align-items: start;
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid var(--cream-dark);
        font-size: 14px;
    }
    .info-row:last-child { border-bottom: none; }
    .info-label { color: var(--charcoal-light); font-weight: 500; }
    .info-value { color: var(--charcoal); font-weight: 500; text-align: right; }

    .status-badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
    }

    .quote-message-box {
        background: var(--cream);
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 16px;
        font-size: 14px;
        line-height: 1.6;
        color: var(--charcoal);
    }

    .financial-row {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        font-size: 14px;
    }
    .financial-row.total {
        border-top: 2px solid var(--charcoal);
        margin-top: 8px;
        padding-top: 12px;
        font-weight: 600;
    }

    .guarantee-note {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 16px;
        padding: 12px 16px;
        background: #F0F7F0;
        border: 1px solid #C8E0C8;
        border-radius: 10px;
        font-size: 13px;
        color: var(--success);
    }
    .guarantee-note svg { width: 18px; height: 18px; flex-shrink: 0; }

    .contact-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 0;
        font-size: 14px;
    }
    .contact-item svg { width: 18px; height: 18px; color: var(--sage-dark); flex-shrink: 0; }
    .contact-item a { color: var(--sage-dark); text-decoration: none; }
    .contact-item a:hover { text-decoration: underline; }

    .countdown-box {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px;
        background: var(--cream);
        border-radius: 12px;
        margin-top: 16px;
    }
    .countdown-number {
        font-family: var(--font-display);
        font-size: 32px;
        font-weight: 500;
        color: var(--sage-dark);
    }
    .countdown-label {
        font-size: 13px;
        color: var(--charcoal-light);
    }

    /* Message thread */
    .message-thread {
        max-height: 500px;
        overflow-y: auto;
        padding: 20px 0;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .msg-bubble {
        max-width: 80%;
        padding: 12px 16px;
        border-radius: 16px;
        font-size: 14px;
        line-height: 1.5;
    }
    .msg-bubble.organizer {
        align-self: flex-end;
        background: var(--sage);
        color: var(--white);
        border-bottom-right-radius: 4px;
    }
    .msg-bubble.vendor {
        align-self: flex-start;
        background: var(--white);
        color: var(--charcoal);
        border: 1px solid var(--cream-dark);
        border-bottom-left-radius: 4px;
    }
    .msg-bubble.platform {
        align-self: center;
        background: #FEF3C7;
        color: #92400E;
        font-size: 13px;
        border-radius: 10px;
    }
    .msg-meta {
        font-size: 11px;
        margin-top: 4px;
        opacity: 0.7;
    }
    .msg-bubble.organizer .msg-meta { text-align: right; }

    .message-form {
        display: flex;
        gap: 12px;
        margin-top: 16px;
    }
    .message-form textarea {
        flex: 1;
        resize: none;
        min-height: 44px;
    }

    /* Star rating input */
    .star-rating-input {
        display: flex;
        gap: 4px;
        margin-bottom: 8px;
    }
    .star-rating-input input[type="radio"] {
        display: none;
    }
    .star-rating-input label {
        font-size: 28px;
        color: var(--cream-dark);
        cursor: pointer;
        transition: color 0.15s;
        line-height: 1;
    }
    .star-rating-input label:hover,
    .star-rating-input label:hover ~ label {
        color: var(--gold);
    }
    .star-rating-input input[type="radio"]:checked ~ label {
        color: var(--gold);
    }
    /* Reverse order trick for CSS-only hover */
    .star-rating-input {
        flex-direction: row-reverse;
        justify-content: flex-end;
    }

    .existing-review {
        background: var(--cream);
        border-radius: 12px;
        padding: 20px;
    }
    .review-stars {
        color: var(--gold);
        font-size: 20px;
        letter-spacing: 2px;
        margin-bottom: 8px;
    }

    .action-buttons {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }

    .btn-danger {
        background: var(--white);
        color: var(--error);
        border: 1px solid var(--error);
    }
    .btn-danger:hover {
        background: #FDF2F2;
    }

    @media (max-width: 900px) {
        .booking-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- Back link -->
<a href="?id=<?= $eventId ?>&page=vendors" class="booking-back-link">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
    </svg>
    Tilbage til leverandorer
</a>

<div class="booking-grid">
    <!-- ============================================================
         LEFT COLUMN: Main content
         ============================================================ -->
    <div>
        <!-- Booking Info Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Booking #<?= $bookingId ?></h3>
                <span class="status-badge" style="<?= $statusColors[$status] ?? '' ?>">
                    <?= $statusLabels[$status] ?? ucfirst($status) ?>
                </span>
            </div>

            <div class="info-row">
                <span class="info-label">Leverandor</span>
                <span class="info-value">
                    <a href="<?= htmlspecialchars($vendorProfileUrl) ?>" target="_blank" style="color: var(--sage-dark); text-decoration: none;">
                        <?= htmlspecialchars($booking['vendor_company_name']) ?>
                    </a>
                    <?php if (!empty($booking['vendor_city'])): ?>
                        <span style="color: var(--charcoal-light); font-weight: 400;"> &middot; <?= htmlspecialchars($booking['vendor_city']) ?></span>
                    <?php endif; ?>
                </span>
            </div>

            <div class="info-row">
                <span class="info-label">Ydelse</span>
                <span class="info-value">
                    <?= htmlspecialchars($booking['service_title'] ?? 'Generel foresporgsel') ?>
                    <?php if (!empty($booking['service_price_from'])): ?>
                        <span style="color: var(--charcoal-light); font-weight: 400;">
                            (fra <?= number_format((float)$booking['service_price_from'], 2, ',', '.') ?> kr)
                        </span>
                    <?php endif; ?>
                </span>
            </div>

            <div class="info-row">
                <span class="info-label">Arrangement</span>
                <span class="info-value">
                    <?= htmlspecialchars($booking['event_title']) ?>
                    <?php if (!empty($booking['event_date'])): ?>
                        <span style="color: var(--charcoal-light); font-weight: 400;">
                            &middot; <?= date('d. M Y', strtotime($booking['event_date'])) ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($booking['guest_count'])): ?>
                        <span style="color: var(--charcoal-light); font-weight: 400;">
                            &middot; <?= (int)$booking['guest_count'] ?> gaester
                        </span>
                    <?php endif; ?>
                </span>
            </div>

            <div class="info-row">
                <span class="info-label">Oprettet</span>
                <span class="info-value" style="color: var(--charcoal-light); font-weight: 400;">
                    <?= date('d. M Y, H:i', strtotime($booking['created_at'])) ?>
                </span>
            </div>
        </div>

        <!-- Financial Breakdown (if quote exists) -->
        <?php if (!empty($booking['quoted_price'])): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Prisoverslag</h3>
            </div>

            <div class="financial-row">
                <span>Leverandorens pris</span>
                <span style="font-weight: 500;"><?= number_format((float)$booking['quoted_price'], 2, ',', '.') ?> kr</span>
            </div>
            <div class="financial-row">
                <span>Depositum (25%)</span>
                <span style="font-weight: 500;"><?= number_format((float)$booking['depositum_amount'], 2, ',', '.') ?> kr</span>
            </div>

            <div class="guarantee-note">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                </svg>
                Dit depositum er beskyttet af PartyParart-garantien
            </div>
        </div>
        <?php endif; ?>

        <!-- Status-Specific Actions -->
        <?php if ($status === 'quoted'): ?>
        <!-- Quoted: Show vendor's message and accept/decline buttons -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Leverandorens tilbud</h3>
            </div>

            <?php if (!empty($booking['vendor_message'])): ?>
                <div class="quote-message-box">
                    <?= nl2br(htmlspecialchars($booking['vendor_message'])) ?>
                </div>
            <?php endif; ?>

            <div class="action-buttons">
                <form method="POST" style="display: inline;">
                    <?= accountCsrfField() ?>
                    <input type="hidden" name="action" value="accept">
                    <button type="submit" class="btn btn-sage">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:16px;height:16px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Accepter tilbud
                    </button>
                </form>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('declineSection').style.display='block'; this.style.display='none';">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:16px;height:16px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    Afvis
                </button>
            </div>

            <div id="declineSection" style="display: none; margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--cream-dark);">
                <form method="POST">
                    <?= accountCsrfField() ?>
                    <input type="hidden" name="action" value="decline">
                    <div class="form-group">
                        <label class="form-label">Begrundelse (valgfri)</label>
                        <textarea name="reason" class="form-input" rows="3" placeholder="Fortael hvorfor du afviser tilbuddet..."></textarea>
                    </div>
                    <div style="margin-top: 12px; display: flex; gap: 12px;">
                        <button type="submit" class="btn btn-danger">Bekraeft afvisning</button>
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('declineSection').style.display='none';">Annuller</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($status === 'accepted'): ?>
        <!-- Accepted: Pay deposit button -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Betal depositum</h3>
            </div>
            <p style="font-size: 14px; color: var(--charcoal-light); margin-bottom: 20px;">
                Dit tilbud er accepteret. Betal depositum for at bekraefte bookingen.
            </p>
            <a href="/subcontractor/payment.php?booking_id=<?= $bookingId ?>" class="btn btn-sage">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:16px;height:16px;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                </svg>
                Betal depositum (<?= number_format((float)$booking['depositum_amount'], 2, ',', '.') ?> kr)
            </a>
        </div>
        <?php endif; ?>

        <?php if (in_array($status, ['deposited', 'confirmed'])): ?>
        <!-- Deposited/Confirmed: Vendor contact info + countdown -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Leverandorkontakt</h3>
            </div>

            <?php if (!empty($booking['vendor_phone'])): ?>
            <div class="contact-item">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                </svg>
                <a href="tel:<?= htmlspecialchars($booking['vendor_phone']) ?>"><?= htmlspecialchars($booking['vendor_phone']) ?></a>
            </div>
            <?php endif; ?>

            <?php if (!empty($booking['vendor_email'])): ?>
            <div class="contact-item">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
                <a href="mailto:<?= htmlspecialchars($booking['vendor_email']) ?>"><?= htmlspecialchars($booking['vendor_email']) ?></a>
            </div>
            <?php endif; ?>

            <?php if ($eventCountdown): ?>
            <div class="countdown-box">
                <div>
                    <div class="countdown-number"><?= htmlspecialchars($eventCountdown) ?></div>
                    <div class="countdown-label">til dit arrangement</div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($status === 'confirmed'): ?>
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--cream-dark);">
                <form method="POST" onsubmit="return confirm('Er du sikker pa at du vil markere bookingen som afsluttet?');">
                    <?= accountCsrfField() ?>
                    <input type="hidden" name="action" value="complete">
                    <button type="submit" class="btn btn-primary">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:16px;height:16px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Marker som afsluttet
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($status === 'completed'): ?>
        <!-- Completed: Review form or existing review -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Anmeldelse</h3>
            </div>

            <?php if ($existingReview): ?>
                <div class="existing-review">
                    <div class="review-stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?= $i <= (int)$existingReview['rating'] ? '&#9733;' : '&#9734;' ?>
                        <?php endfor; ?>
                    </div>
                    <?php if (!empty($existingReview['title'])): ?>
                        <div style="font-weight: 600; margin-bottom: 4px;"><?= htmlspecialchars($existingReview['title']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($existingReview['review_text'])): ?>
                        <div style="font-size: 14px; color: var(--charcoal-light); line-height: 1.6;">
                            <?= nl2br(htmlspecialchars($existingReview['review_text'])) ?>
                        </div>
                    <?php endif; ?>
                    <div style="font-size: 12px; color: var(--charcoal-light); margin-top: 12px;">
                        Oprettet <?= date('d. M Y', strtotime($existingReview['created_at'])) ?>
                    </div>

                    <?php if (!empty($existingReview['vendor_response'])): ?>
                        <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--cream-dark);">
                            <div style="font-size: 12px; font-weight: 600; color: var(--charcoal-light); margin-bottom: 4px;">
                                Svar fra <?= htmlspecialchars($booking['vendor_company_name']) ?>
                            </div>
                            <div style="font-size: 14px; color: var(--charcoal); line-height: 1.6;">
                                <?= nl2br(htmlspecialchars($existingReview['vendor_response'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <form method="POST">
                    <?= accountCsrfField() ?>
                    <input type="hidden" name="action" value="review">

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label class="form-label">Bedommelse *</label>
                        <div class="star-rating-input">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>" required>
                                <label for="star<?= $i ?>">&#9733;</label>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label class="form-label">Overskrift</label>
                        <input type="text" name="title" class="form-input" placeholder="F.eks. Fantastisk oplevelse!" maxlength="100">
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label class="form-label">Din anmeldelse</label>
                        <textarea name="review_text" class="form-input" rows="4" placeholder="Fortael om din oplevelse med denne leverandor..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:16px;height:16px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                        </svg>
                        Indsend anmeldelse
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Completed with issues: Refund request -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Problemer?</h3>
            </div>
            <p style="font-size: 14px; color: var(--charcoal-light); margin-bottom: 16px;">
                Hvis du har oplevet problemer med leverandoren, kan du anmode om refundering.
            </p>
            <a href="mailto:support@partyparart.dk?subject=Refundering%20-%20Booking%20%23<?= $bookingId ?>" class="btn btn-danger">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:16px;height:16px;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Anmod om refundering
            </a>
        </div>
        <?php endif; ?>

        <!-- Cancel booking (active statuses: requested, quoted, accepted) -->
        <?php if (in_array($status, ['requested', 'quoted', 'accepted'])): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Annuller booking</h3>
            </div>
            <form method="POST" onsubmit="return confirm('Er du sikker pa at du vil annullere denne booking?');">
                <?= accountCsrfField() ?>
                <input type="hidden" name="action" value="cancel">
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">Begrundelse (valgfri)</label>
                    <textarea name="reason" class="form-input" rows="3" placeholder="Fortael hvorfor du annullerer..."></textarea>
                </div>
                <button type="submit" class="btn btn-danger">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:16px;height:16px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                    </svg>
                    Annuller booking
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Message Thread -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Beskeder</h3>
                <span style="font-size: 13px; color: var(--charcoal-light);"><?= count($messages) ?> besked<?= count($messages) !== 1 ? 'er' : '' ?></span>
            </div>

            <?php if (empty($messages)): ?>
                <div style="text-align: center; padding: 32px 0; color: var(--charcoal-light); font-size: 14px;">
                    Ingen beskeder endnu. Send den forste besked nedenfor.
                </div>
            <?php else: ?>
                <div class="message-thread">
                    <?php foreach ($messages as $msg): ?>
                        <div class="msg-bubble <?= htmlspecialchars($msg['sender_type']) ?>">
                            <div><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                            <div class="msg-meta">
                                <?= date('d. M, H:i', strtotime($msg['created_at'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!in_array($status, ['cancelled', 'refunded'])): ?>
            <form method="POST" class="message-form" style="border-top: 1px solid var(--cream-dark); padding-top: 16px;">
                <?= accountCsrfField() ?>
                <input type="hidden" name="action" value="message">
                <textarea name="message" class="form-input" rows="2" placeholder="Skriv en besked..." required style="min-height: 44px;"></textarea>
                <button type="submit" class="btn btn-sage" style="align-self: flex-end;">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:16px;height:16px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                    </svg>
                    Send besked
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================
         RIGHT COLUMN: Sidebar summary
         ============================================================ -->
    <div>
        <!-- Quick status summary card -->
        <div class="card" style="position: sticky; top: 100px;">
            <h4 style="font-family: var(--font-display); font-size: 18px; font-weight: 500; margin-bottom: 16px;">Opsummering</h4>

            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <div style="width: 48px; height: 48px; background: var(--sage-light); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <svg fill="none" stroke="var(--sage-dark)" viewBox="0 0 24 24" style="width: 24px; height: 24px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"></path>
                    </svg>
                </div>
                <div>
                    <div style="font-weight: 600; font-size: 15px;">
                        <a href="<?= htmlspecialchars($vendorProfileUrl) ?>" target="_blank" style="color: var(--charcoal); text-decoration: none;">
                            <?= htmlspecialchars($booking['vendor_company_name']) ?>
                        </a>
                    </div>
                    <div style="font-size: 13px; color: var(--charcoal-light);">
                        <?= htmlspecialchars($booking['service_title'] ?? 'Generel') ?>
                    </div>
                </div>
            </div>

            <div style="padding: 12px 0; border-top: 1px solid var(--cream-dark);">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span style="font-size: 13px; color: var(--charcoal-light);">Status</span>
                    <span class="status-badge" style="<?= $statusColors[$status] ?? '' ?>">
                        <?= $statusLabels[$status] ?? ucfirst($status) ?>
                    </span>
                </div>

                <?php if (!empty($booking['quoted_price'])): ?>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px;">
                    <span style="color: var(--charcoal-light);">Pris</span>
                    <span style="font-weight: 600;"><?= number_format((float)$booking['quoted_price'], 2, ',', '.') ?> kr</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px;">
                    <span style="color: var(--charcoal-light);">Depositum</span>
                    <span style="font-weight: 500;"><?= number_format((float)$booking['depositum_amount'], 2, ',', '.') ?> kr</span>
                </div>
                <?php endif; ?>

                <?php if (!empty($booking['event_date'])): ?>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px;">
                    <span style="color: var(--charcoal-light);">Dato</span>
                    <span style="font-weight: 500;"><?= date('d. M Y', strtotime($booking['event_date'])) ?></span>
                </div>
                <?php endif; ?>

                <?php if (!empty($booking['guest_count'])): ?>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px;">
                    <span style="color: var(--charcoal-light);">Gaester</span>
                    <span style="font-weight: 500;"><?= (int)$booking['guest_count'] ?></span>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($eventCountdown && in_array($status, ['deposited', 'confirmed'])): ?>
            <div class="countdown-box" style="margin-top: 0;">
                <div>
                    <div class="countdown-number"><?= htmlspecialchars($eventCountdown) ?></div>
                    <div class="countdown-label">til dit arrangement</div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick action buttons based on status -->
            <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--cream-dark);">
                <?php if ($status === 'quoted'): ?>
                    <form method="POST" style="width: 100%;">
                        <?= accountCsrfField() ?>
                        <input type="hidden" name="action" value="accept">
                        <button type="submit" class="btn btn-sage" style="width: 100%; justify-content: center;">Accepter tilbud</button>
                    </form>
                <?php elseif ($status === 'accepted'): ?>
                    <a href="/subcontractor/payment.php?booking_id=<?= $bookingId ?>" class="btn btn-sage" style="width: 100%; justify-content: center;">Betal depositum</a>
                <?php endif; ?>

                <a href="?id=<?= $eventId ?>&page=vendors" class="btn btn-secondary" style="width: 100%; justify-content: center; margin-top: 8px;">
                    Alle leverandorer
                </a>
            </div>
        </div>
    </div>
</div>

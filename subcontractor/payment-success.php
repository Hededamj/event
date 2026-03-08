<?php
/**
 * Payment Success Page
 *
 * Displayed after a successful Stripe Checkout session for a booking depositum.
 * Shows confirmation details and links back to the event.
 *
 * GET params: booking_id, session_id
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth-account.php';
require_once __DIR__ . '/includes/booking-service.php';

// Require account login
requireAccountLogin();

$accountId = getCurrentAccountId();

// ============================================================
// Validate params
// ============================================================

$bookingId = isset($_GET['booking_id']) ? (int) $_GET['booking_id'] : 0;
$sessionId = $_GET['session_id'] ?? '';

$booking = null;
$error = null;

if (empty($sessionId)) {
    $error = 'Ugyldig betalingssession.';
} elseif ($bookingId <= 0) {
    $error = 'Ugyldig booking.';
} else {
    $booking = getBooking($bookingId);
    if (!$booking) {
        $error = 'Booking ikke fundet.';
    }
}

// Verify organizer owns booking
if ($booking && !$error) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT eo.id
        FROM event_owners eo
        WHERE eo.event_id = ? AND eo.account_id = ? AND eo.role = 'owner'
        LIMIT 1
    ");
    $stmt->execute([$booking['event_id'], $accountId]);
    if (!$stmt->fetch()) {
        $error = 'Du har ikke adgang til denne booking.';
        $booking = null;
    }
}

$depositumAmount = $booking ? (float) $booking['depositum_amount'] : 0;

$pageTitle = 'Betaling bekræftet';
require_once __DIR__ . '/includes/marketplace-header.php';
?>

<style>
/* ============================================
   Payment Success Styles
   ============================================ */
.success-wrapper {
    max-width: 580px;
    margin: 40px auto 60px;
    padding: 0 24px;
}

.success-card {
    background: var(--surface-card);
    border-radius: 16px;
    padding: 40px 32px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 6px 24px rgba(0,0,0,0.06);
    text-align: center;
}

.success-icon {
    width: 64px;
    height: 64px;
    background: rgba(107, 158, 107, 0.12);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
}

.success-icon svg {
    width: 32px;
    height: 32px;
    color: var(--success);
}

.success-title {
    font-family: var(--font-display);
    font-size: 28px;
    font-weight: 500;
    color: var(--text);
    margin-bottom: 8px;
}

.success-subtitle {
    font-size: 15px;
    color: var(--text-secondary);
    line-height: 1.6;
    margin-bottom: 28px;
}

.success-details {
    background: var(--surface);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 28px;
    text-align: left;
}

.success-detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    font-size: 14px;
    color: var(--text-secondary);
}

.success-detail-row + .success-detail-row {
    border-top: 1px solid var(--border);
}

.success-detail-label {
    font-weight: 500;
}

.success-detail-value {
    font-weight: 600;
    color: var(--text);
}

.success-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.success-btn-primary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 14px 24px;
    font-family: var(--font-body);
    font-size: 15px;
    font-weight: 600;
    color: var(--surface-card);
    background: var(--accent);
    border: none;
    border-radius: 10px;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.25s var(--ease-out);
}

.success-btn-primary:hover {
    background: var(--accent-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(122, 139, 114, 0.3);
}

.success-btn-primary svg {
    width: 18px;
    height: 18px;
}

.success-error {
    background: rgba(199, 93, 93, 0.08);
    border: 1px solid rgba(199, 93, 93, 0.2);
    border-radius: 10px;
    padding: 16px 20px;
    color: var(--error);
    font-size: 14px;
    margin-bottom: 20px;
    text-align: left;
}

@media (max-width: 640px) {
    .success-wrapper {
        margin-top: 24px;
        padding: 0 16px;
    }

    .success-card {
        padding: 32px 20px;
    }

    .success-title {
        font-size: 24px;
    }
}
</style>

    <main class="mp-main">
        <div class="success-wrapper">
            <div class="success-card">
                <?php if ($error): ?>
                    <div class="success-error"><?= escape($error) ?></div>
                    <a href="/app/dashboard.php" class="success-btn-primary">Tilbage til dashboard</a>
                <?php else: ?>
                    <div class="success-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>

                    <h1 class="success-title">Betaling bekræftet!</h1>
                    <p class="success-subtitle">
                        Depositum pa <?= formatCurrency($depositumAmount) ?> er betalt. Leverandøren vil bekræfte din booking.
                    </p>

                    <div class="success-details">
                        <div class="success-detail-row">
                            <span class="success-detail-label">Leverandør</span>
                            <span class="success-detail-value"><?= escape($booking['vendor_company_name']) ?></span>
                        </div>
                        <?php if (!empty($booking['service_title'])): ?>
                        <div class="success-detail-row">
                            <span class="success-detail-label">Ydelse</span>
                            <span class="success-detail-value"><?= escape($booking['service_title']) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="success-detail-row">
                            <span class="success-detail-label">Event</span>
                            <span class="success-detail-value"><?= escape($booking['event_title']) ?></span>
                        </div>
                        <div class="success-detail-row">
                            <span class="success-detail-label">Depositum betalt</span>
                            <span class="success-detail-value"><?= formatCurrency($depositumAmount) ?></span>
                        </div>
                    </div>

                    <div class="success-actions">
                        <a href="/app/events/manage.php?event=<?= (int) $booking['event_id'] ?>&page=vendors" class="success-btn-primary">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                            Tilbage til event
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer class="mp-footer">
        <p>&copy; <?= date('Y') ?> PartyParart &mdash; <a href="/subcontractor/">Find leverandører</a></p>
    </footer>

    <script>
    (function() {
        var toggle = document.getElementById('mpMobileToggle');
        var nav = document.getElementById('mpMobileNav');
        if (toggle && nav) {
            toggle.addEventListener('click', function() {
                nav.classList.toggle('open');
            });
        }
    })();
    </script>
</body>
</html>

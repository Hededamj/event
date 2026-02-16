<?php
/**
 * Payment Cancelled Page
 *
 * Displayed when the user cancels the Stripe Checkout session.
 * Provides links to retry payment or return to the event.
 *
 * GET param: booking_id
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth-account.php';
require_once __DIR__ . '/includes/booking-service.php';

// Require account login
requireAccountLogin();

$accountId = getCurrentAccountId();

// ============================================================
// Load booking (optional — page works even without valid booking)
// ============================================================

$bookingId = isset($_GET['booking_id']) ? (int) $_GET['booking_id'] : 0;
$booking = null;

if ($bookingId > 0) {
    $booking = getBooking($bookingId);

    // Verify organizer owns booking
    if ($booking) {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT eo.id
            FROM event_owners eo
            WHERE eo.event_id = ? AND eo.account_id = ? AND eo.role = 'owner'
            LIMIT 1
        ");
        $stmt->execute([$booking['event_id'], $accountId]);
        if (!$stmt->fetch()) {
            $booking = null;
        }
    }
}

$pageTitle = 'Betaling annulleret';
require_once __DIR__ . '/includes/marketplace-header.php';
?>

<style>
/* ============================================
   Payment Cancel Styles
   ============================================ */
.cancel-wrapper {
    max-width: 580px;
    margin: 40px auto 60px;
    padding: 0 24px;
}

.cancel-card {
    background: var(--white);
    border-radius: 16px;
    padding: 40px 32px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 6px 24px rgba(0,0,0,0.06);
    text-align: center;
}

.cancel-icon {
    width: 64px;
    height: 64px;
    background: rgba(212, 168, 67, 0.12);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
}

.cancel-icon svg {
    width: 32px;
    height: 32px;
    color: var(--warning);
}

.cancel-title {
    font-family: var(--font-display);
    font-size: 28px;
    font-weight: 500;
    color: var(--charcoal);
    margin-bottom: 8px;
}

.cancel-subtitle {
    font-size: 15px;
    color: var(--charcoal-light);
    line-height: 1.6;
    margin-bottom: 28px;
}

.cancel-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.cancel-btn-primary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 14px 24px;
    font-family: var(--font-body);
    font-size: 15px;
    font-weight: 600;
    color: var(--white);
    background: var(--sage);
    border: none;
    border-radius: 10px;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.25s var(--ease-out);
}

.cancel-btn-primary:hover {
    background: var(--sage-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(122, 139, 114, 0.3);
}

.cancel-btn-primary svg {
    width: 18px;
    height: 18px;
}

.cancel-btn-secondary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 14px 24px;
    font-family: var(--font-body);
    font-size: 15px;
    font-weight: 600;
    color: var(--charcoal);
    background: var(--white);
    border: 2px solid var(--cream-dark);
    border-radius: 10px;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.25s var(--ease-out);
}

.cancel-btn-secondary:hover {
    border-color: var(--sage);
    background: var(--cream);
}

.cancel-btn-secondary svg {
    width: 18px;
    height: 18px;
}

@media (max-width: 640px) {
    .cancel-wrapper {
        margin-top: 24px;
        padding: 0 16px;
    }

    .cancel-card {
        padding: 32px 20px;
    }

    .cancel-title {
        font-size: 24px;
    }
}
</style>

    <main class="mp-main">
        <div class="cancel-wrapper">
            <div class="cancel-card">
                <div class="cancel-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                </div>

                <h1 class="cancel-title">Betaling annulleret</h1>
                <p class="cancel-subtitle">
                    Du kan altid betale senere fra din booking-oversigt.
                </p>

                <div class="cancel-actions">
                    <?php if ($booking && $bookingId > 0): ?>
                        <a href="/subcontractor/payment.php?booking_id=<?= $bookingId ?>" class="cancel-btn-primary">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Prov igen
                        </a>
                        <a href="/app/events/manage.php?event=<?= (int) $booking['event_id'] ?>&page=vendors" class="cancel-btn-secondary">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                            Tilbage til event
                        </a>
                    <?php else: ?>
                        <a href="/app/dashboard.php" class="cancel-btn-primary">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                            Tilbage til dashboard
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <footer class="mp-footer">
        <p>&copy; <?= date('Y') ?> PartyParart &mdash; <a href="/subcontractor/">Find leverandorer</a></p>
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

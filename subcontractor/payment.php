<?php
/**
 * Payment Initiation Page (Stripe Connect)
 *
 * Displays a payment summary for a booking depositum and initiates
 * Stripe Checkout via createDepositumCheckout().
 *
 * GET param: booking_id
 * POST action: pay
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth-account.php';
require_once __DIR__ . '/includes/booking-service.php';
require_once __DIR__ . '/includes/payment-service.php';

// Require account login
requireAccountLogin();

$accountId = getCurrentAccountId();
$error = null;
$booking = null;

// ============================================================
// Load booking
// ============================================================

$bookingId = isset($_GET['booking_id']) ? (int) $_GET['booking_id'] : 0;

if ($bookingId <= 0) {
    $error = 'Ugyldig booking.';
} else {
    $booking = getBooking($bookingId);

    if (!$booking) {
        $error = 'Booking ikke fundet.';
    }
}

// ============================================================
// Verify organizer owns booking (via event_owners)
// ============================================================

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

// ============================================================
// Verify booking status is 'accepted'
// ============================================================

if ($booking && !$error) {
    if ($booking['status'] !== 'accepted') {
        $error = 'Denne booking er ikke klar til betaling. Status: ' . escape($booking['status']);
        $booking = null;
    }
}

// ============================================================
// Verify vendor has stripe_account_id
// ============================================================

if ($booking && !$error) {
    if (empty($booking['vendor_stripe_account_id'])) {
        $error = 'Leverandøren har ikke opsat betalingsmodtagelse endnu. Kontakt leverandøren.';
        $booking = null;
    }
}

// ============================================================
// Handle POST: initiate Stripe Checkout
// ============================================================

if ($booking && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pay') {
    // Verify CSRF
    if (!verifyAccountCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Ugyldig anmodning. Prøv igen.';
    } else {
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        $successUrl = $baseUrl . '/subcontractor/payment-success.php?booking_id=' . $booking['id'] . '&session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = $baseUrl . '/subcontractor/payment-cancel.php?booking_id=' . $booking['id'];

        $session = createDepositumCheckout($booking, $successUrl, $cancelUrl);

        if ($session && !empty($session['url'])) {
            redirect($session['url']);
        } else {
            $error = 'Kunne ikke oprette betalingssession. Prøv igen senere.';
        }
    }
}

// ============================================================
// Calculate display values
// ============================================================

$depositumAmount = $booking ? (float) $booking['depositum_amount'] : 0;
$quotedPrice = $booking ? (float) $booking['quoted_price'] : 0;

$pageTitle = 'Betal depositum';
require_once __DIR__ . '/includes/marketplace-header.php';
?>

<style>
/* ============================================
   Payment Page Styles
   ============================================ */
.payment-wrapper {
    max-width: 640px;
    margin: 40px auto 60px;
    padding: 0 24px;
}

.payment-card {
    background: var(--surface-card);
    border-radius: 16px;
    padding: 32px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 6px 24px rgba(0,0,0,0.06);
}

.payment-title {
    font-family: var(--font-display);
    font-size: 28px;
    font-weight: 500;
    color: var(--text);
    margin-bottom: 24px;
}

.payment-error {
    background: rgba(199, 93, 93, 0.08);
    border: 1px solid rgba(199, 93, 93, 0.2);
    border-radius: 10px;
    padding: 16px 20px;
    color: var(--error);
    font-size: 14px;
    margin-bottom: 20px;
}

.payment-summary {
    margin-bottom: 24px;
}

.payment-summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid var(--border);
    font-size: 14px;
    color: var(--text-secondary);
}

.payment-summary-row:last-child {
    border-bottom: none;
}

.payment-summary-label {
    font-weight: 500;
}

.payment-summary-value {
    font-weight: 600;
    color: var(--text);
}

.payment-total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 0;
    border-top: 2px solid var(--text);
    margin-top: 8px;
    font-size: 16px;
    font-weight: 600;
    color: var(--text);
}

.payment-total-amount {
    font-family: var(--font-display);
    font-size: 24px;
    font-weight: 500;
}

.payment-guarantee {
    background: rgba(168, 181, 160, 0.1);
    border: 1px solid rgba(168, 181, 160, 0.3);
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 24px;
    display: flex;
    gap: 12px;
    align-items: flex-start;
}

.payment-guarantee svg {
    width: 24px;
    height: 24px;
    color: var(--accent-dark);
    flex-shrink: 0;
    margin-top: 1px;
}

.payment-guarantee-text {
    font-size: 13px;
    color: var(--text-secondary);
    line-height: 1.6;
}

.payment-guarantee-text strong {
    color: var(--text);
    font-weight: 600;
    display: block;
    margin-bottom: 2px;
    font-size: 14px;
}

.payment-btn {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 16px 28px;
    font-family: var(--font-body);
    font-size: 16px;
    font-weight: 600;
    color: var(--surface-card);
    background: var(--accent);
    border: none;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.25s var(--ease-out);
}

.payment-btn:hover {
    background: var(--accent-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(122, 139, 114, 0.3);
}

.payment-btn svg {
    width: 20px;
    height: 20px;
}

.payment-back {
    display: block;
    text-align: center;
    margin-top: 16px;
    font-size: 14px;
    color: var(--text-secondary);
    text-decoration: none;
    transition: color 0.2s;
}

.payment-back:hover {
    color: var(--text);
}

@media (max-width: 640px) {
    .payment-wrapper {
        margin-top: 24px;
        padding: 0 16px;
    }

    .payment-card {
        padding: 24px 20px;
    }

    .payment-title {
        font-size: 24px;
    }
}
</style>

    <main class="mp-main">
        <div class="payment-wrapper">
            <?php if ($error): ?>
                <div class="payment-card">
                    <h1 class="payment-title">Betal depositum</h1>
                    <div class="payment-error"><?= escape($error) ?></div>
                    <a href="/app/dashboard.php" class="payment-back">Tilbage til dashboard</a>
                </div>
            <?php elseif ($booking): ?>
                <div class="payment-card">
                    <h1 class="payment-title">Betal depositum</h1>

                    <!-- Payment Summary -->
                    <div class="payment-summary">
                        <div class="payment-summary-row">
                            <span class="payment-summary-label">Leverandor</span>
                            <span class="payment-summary-value"><?= escape($booking['vendor_company_name']) ?></span>
                        </div>
                        <?php if (!empty($booking['service_title'])): ?>
                        <div class="payment-summary-row">
                            <span class="payment-summary-label">Ydelse</span>
                            <span class="payment-summary-value"><?= escape($booking['service_title']) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="payment-summary-row">
                            <span class="payment-summary-label">Event</span>
                            <span class="payment-summary-value"><?= escape($booking['event_title']) ?></span>
                        </div>
                        <div class="payment-summary-row">
                            <span class="payment-summary-label">Tilbudt pris</span>
                            <span class="payment-summary-value"><?= formatCurrency($quotedPrice) ?></span>
                        </div>
                        <div class="payment-summary-row">
                            <span class="payment-summary-label">Depositum (25%)</span>
                            <span class="payment-summary-value"><?= formatCurrency($depositumAmount) ?></span>
                        </div>
                        <div class="payment-total-row">
                            <span>At betale nu</span>
                            <span class="payment-total-amount"><?= formatCurrency($depositumAmount) ?></span>
                        </div>
                    </div>

                    <!-- Guarantee -->
                    <div class="payment-guarantee">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        <div class="payment-guarantee-text">
                            <strong>PartyParart-garantien</strong>
                            Dit depositum er beskyttet af PartyParart-garantien. Leverer leverandoren ikke som aftalt, refunderer vi dit fulde depositum.
                        </div>
                    </div>

                    <!-- Pay Button -->
                    <form method="POST" action="?booking_id=<?= (int) $booking['id'] ?>">
                        <input type="hidden" name="action" value="pay">
                        <?= accountCsrfField() ?>
                        <button type="submit" class="payment-btn">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                            </svg>
                            Betal depositum - <?= formatCurrency($depositumAmount) ?>
                        </button>
                    </form>

                    <a href="/app/events/manage.php?event=<?= (int) $booking['event_id'] ?>&page=vendors" class="payment-back">
                        Tilbage til event
                    </a>
                </div>
            <?php endif; ?>
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

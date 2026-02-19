<?php
/**
 * Vendor Booking Detail
 * Single booking view with info, financial breakdown, actions, and messaging.
 */

$pageTitle = 'Booking detaljer';
require_once __DIR__ . '/../includes/vendor-header.php';
require_once __DIR__ . '/../includes/booking-service.php';

// ============================================================
// Helpers (re-declare if not defined by index.php)
// ============================================================
if (!function_exists('formatDanishDate')) {
    function formatDanishDate(string $dateStr): string {
        $months = [
            1 => 'januar', 2 => 'februar', 3 => 'marts', 4 => 'april',
            5 => 'maj', 6 => 'juni', 7 => 'juli', 8 => 'august',
            9 => 'september', 10 => 'oktober', 11 => 'november', 12 => 'december'
        ];
        $ts = strtotime($dateStr);
        if (!$ts) return $dateStr;
        $day = (int) date('j', $ts);
        $month = $months[(int) date('n', $ts)];
        $year = date('Y', $ts);
        return "{$day}. {$month} {$year}";
    }
}

if (!function_exists('statusLabel')) {
    function statusLabel(string $status): string {
        $labels = [
            'requested'  => 'Forespurgt',
            'quoted'     => 'Tilbudt',
            'accepted'   => 'Accepteret',
            'deposited'  => 'Depositum betalt',
            'confirmed'  => 'Bekræftet',
            'completed'  => 'Afsluttet',
            'reviewed'   => 'Anmeldt',
            'cancelled'  => 'Annulleret',
            'refunded'   => 'Refunderet',
            'disputed'   => 'Disputeret',
        ];
        return $labels[$status] ?? ucfirst($status);
    }
}

// ============================================================
// Load booking
// ============================================================
$bookingId = (int) ($_GET['id'] ?? 0);
if ($bookingId <= 0) {
    setFlash('error', 'Ugyldig booking.');
    redirect('/subcontractor/dashboard/bookings.php');
}

$booking = getBooking($bookingId);

// Verify booking exists and belongs to this vendor
if (!$booking || (int) $booking['vendor_id'] !== $vendorId) {
    setFlash('error', 'Booking ikke fundet.');
    redirect('/subcontractor/dashboard/bookings.php');
}

// ============================================================
// Handle POST actions
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyVendorCsrfToken($csrfToken)) {
        setFlash('error', 'Ugyldig anmodning. Prøv igen.');
        redirect('/subcontractor/dashboard/booking-detail.php?id=' . $bookingId);
    }

    // --- QUOTE ---
    if ($action === 'quote') {
        $price = (float) ($_POST['price'] ?? 0);
        $message = trim($_POST['message'] ?? '');

        if ($price <= 0) {
            setFlash('error', 'Pris skal være et positivt tal.');
            redirect('/subcontractor/dashboard/booking-detail.php?id=' . $bookingId);
        }

        $result = submitQuote($bookingId, $vendorId, $price, $message);

        if ($result['success']) {
            setFlash('success', 'Tilbud sendt til arrangøren.');
        } else {
            setFlash('error', $result['error'] ?? 'Kunne ikke sende tilbud.');
        }
        redirect('/subcontractor/dashboard/booking-detail.php?id=' . $bookingId);
    }

    // --- CONFIRM ---
    if ($action === 'confirm') {
        $result = vendorConfirmBooking($bookingId, $vendorId);

        if ($result) {
            setFlash('success', 'Booking bekræftet.');
        } else {
            setFlash('error', 'Kunne ikke bekræfte booking.');
        }
        redirect('/subcontractor/dashboard/booking-detail.php?id=' . $bookingId);
    }

    // --- MESSAGE ---
    if ($action === 'message') {
        $message = trim($_POST['message'] ?? '');

        if (empty($message)) {
            setFlash('error', 'Besked kan ikke være tom.');
            redirect('/subcontractor/dashboard/booking-detail.php?id=' . $bookingId);
        }

        $result = sendBookingMessage($bookingId, 'vendor', $message);

        if ($result) {
            setFlash('success', 'Besked sendt.');
        } else {
            setFlash('error', 'Kunne ikke sende besked.');
        }
        redirect('/subcontractor/dashboard/booking-detail.php?id=' . $bookingId);
    }
}

// ============================================================
// Load messages and mark as read
// ============================================================
$messages = getBookingMessages($bookingId);
markMessagesRead($bookingId, 'vendor');

// ============================================================
// Computed values
// ============================================================
$status = $booking['status'];
$hasQuote = !empty($booking['quoted_price']) && (float) $booking['quoted_price'] > 0;

// Countdown to event date
$eventDateTs = strtotime($booking['event_date'] ?? '');
$daysUntilEvent = null;
if ($eventDateTs) {
    $now = strtotime('today');
    $daysUntilEvent = (int) round(($eventDateTs - $now) / 86400);
}
?>

<!-- Page Header -->
<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Booking #<?= $bookingId ?></h1>
        <p class="page-subtitle">
            <a href="/subcontractor/dashboard/bookings.php" style="color: var(--accent-dark); text-decoration: none;">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 16px; height: 16px; vertical-align: text-bottom; margin-right: 4px;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Tilbage til bookinger
            </a>
        </p>
    </div>
    <div class="page-header-actions">
        <span class="badge badge-<?= htmlspecialchars($status) ?>" style="font-size: 14px; padding: 6px 16px;">
            <?= htmlspecialchars(statusLabel($status)) ?>
        </span>
    </div>
</div>

<!-- Booking Info Card -->
<div class="card mb-3">
    <div class="card-header">
        <h2 class="card-title">Booking information</h2>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <div class="text-sm text-muted" style="margin-bottom: 4px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Event</div>
                <div class="table-cell-primary" style="margin-bottom: 4px;"><?= htmlspecialchars($booking['event_title'] ?? '—') ?></div>
                <?php if (!empty($booking['event_date'])): ?>
                    <div class="text-sm text-muted"><?= formatDanishDate($booking['event_date']) ?></div>
                <?php endif; ?>
                <?php if (!empty($booking['guest_count'])): ?>
                    <div class="text-sm text-muted"><?= (int) $booking['guest_count'] ?> gæster</div>
                <?php endif; ?>
            </div>
            <div>
                <div class="text-sm text-muted" style="margin-bottom: 4px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Arrangør</div>
                <div class="table-cell-primary"><?= htmlspecialchars($booking['account_name'] ?? '—') ?></div>
            </div>
            <?php if (!empty($booking['service_title'])): ?>
                <div>
                    <div class="text-sm text-muted" style="margin-bottom: 4px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Ydelse</div>
                    <div class="table-cell-primary"><?= htmlspecialchars($booking['service_title']) ?></div>
                </div>
            <?php endif; ?>
            <div>
                <div class="text-sm text-muted" style="margin-bottom: 4px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Oprettet</div>
                <div class="table-cell-primary"><?= !empty($booking['created_at']) ? formatDanishDate($booking['created_at']) : '—' ?></div>
            </div>
        </div>
    </div>
</div>

<?php if ($hasQuote): ?>
<!-- Financial Breakdown -->
<div class="card mb-3">
    <div class="card-header">
        <h2 class="card-title">Økonomi</h2>
    </div>
    <div class="card-body">
        <div style="display: flex; flex-direction: column; gap: 12px;">
            <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 12px; border-bottom: 1px solid var(--border);">
                <span class="text-muted">Tilbudt pris</span>
                <span class="table-cell-primary"><?= number_format((float) $booking['quoted_price'], 2, ',', '.') ?> kr</span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 12px; border-bottom: 1px solid var(--border);">
                <span class="text-muted">Depositum (25%)</span>
                <span><?= number_format((float) $booking['depositum_amount'], 2, ',', '.') ?> kr</span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 12px; border-bottom: 1px solid var(--border);">
                <span class="text-muted">Kommission (15%)</span>
                <span style="color: var(--error);">-<?= number_format((float) $booking['commission_amount'], 2, ',', '.') ?> kr</span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 4px;">
                <span style="font-weight: 600;">Din udbetaling</span>
                <span style="font-weight: 600; font-size: 18px; color: var(--success);"><?= number_format((float) $booking['vendor_payout'], 2, ',', '.') ?> kr</span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Action Section -->
<div class="card mb-3">
    <div class="card-header">
        <h2 class="card-title">Handling</h2>
    </div>
    <div class="card-body">
        <?php if ($status === 'requested'): ?>
            <!-- Quote form -->
            <div style="margin-bottom: 16px;">
                <p class="text-muted" style="margin-bottom: 16px;">Arrangøren har sendt en forespørgsel. Angiv din pris og send et tilbud.</p>
                <?php if (!empty($booking['organizer_message'])): ?>
                    <div style="background: var(--surface); border-radius: 10px; padding: 16px; margin-bottom: 20px;">
                        <div class="text-sm" style="font-weight: 600; margin-bottom: 6px;">Besked fra arrangør:</div>
                        <p style="margin: 0; font-size: 14px; line-height: 1.6;"><?= nl2br(htmlspecialchars($booking['organizer_message'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>
            <form method="POST">
                <?= vendorCsrfField() ?>
                <input type="hidden" name="action" value="quote">

                <div class="form-group">
                    <label class="form-label" for="price">Pris (DKK) <span class="required">*</span></label>
                    <input type="number" id="price" name="price" class="form-input"
                           placeholder="F.eks. 5000" step="0.01" min="1" required
                           style="max-width: 300px;">
                    <span class="form-hint">Angiv din totalpris for denne booking.</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="quote_message">Besked til arrangør</label>
                    <textarea id="quote_message" name="message" class="form-textarea"
                              placeholder="Beskriv hvad tilbuddet inkluderer..." rows="4"></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                        </svg>
                        Send tilbud
                    </button>
                </div>
            </form>

        <?php elseif ($status === 'quoted'): ?>
            <div style="background: rgba(201, 169, 98, 0.1); border: 1px solid rgba(201, 169, 98, 0.25); border-radius: 10px; padding: 20px; display: flex; align-items: center; gap: 12px;">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 24px; height: 24px; color: var(--warning); flex-shrink: 0;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div>
                    <div style="font-weight: 600; margin-bottom: 2px;">Tilbud sendt</div>
                    <p class="text-muted" style="margin: 0; font-size: 14px;">Dit tilbud afventer svar fra arrangøren.</p>
                </div>
            </div>

        <?php elseif ($status === 'accepted'): ?>
            <div style="background: rgba(168, 181, 160, 0.12); border: 1px solid rgba(168, 181, 160, 0.3); border-radius: 10px; padding: 20px; display: flex; align-items: center; gap: 12px;">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 24px; height: 24px; color: var(--accent-dark); flex-shrink: 0;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div>
                    <div style="font-weight: 600; margin-bottom: 2px;">Tilbud accepteret</div>
                    <p class="text-muted" style="margin: 0; font-size: 14px;">Afventer betaling fra arrangør. Du modtager besked, når depositum er betalt.</p>
                </div>
            </div>

        <?php elseif ($status === 'deposited'): ?>
            <div style="margin-bottom: 16px;">
                <div style="background: rgba(107, 158, 107, 0.1); border: 1px solid rgba(107, 158, 107, 0.25); border-radius: 10px; padding: 20px; display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 24px; height: 24px; color: var(--success); flex-shrink: 0;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <div style="font-weight: 600; margin-bottom: 2px;">Depositum betalt</div>
                        <p class="text-muted" style="margin: 0; font-size: 14px;">Arrangøren har betalt depositum. Bekræft bookingen for at bekræfte din deltagelse.</p>
                    </div>
                </div>
                <form method="POST">
                    <?= vendorCsrfField() ?>
                    <input type="hidden" name="action" value="confirm">
                    <button type="submit" class="btn btn-primary btn-lg"
                            onclick="return confirm('Er du sikker på, at du vil bekræfte denne booking?');">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Bekræft booking
                    </button>
                </form>
            </div>

        <?php elseif ($status === 'confirmed'): ?>
            <div style="background: rgba(107, 158, 107, 0.1); border: 1px solid rgba(107, 158, 107, 0.25); border-radius: 10px; padding: 20px;">
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 24px; height: 24px; color: var(--success); flex-shrink: 0;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div style="font-weight: 600;">Booking bekræftet</div>
                </div>
                <?php if ($daysUntilEvent !== null): ?>
                    <?php if ($daysUntilEvent > 0): ?>
                        <div style="display: flex; align-items: center; gap: 16px; margin-top: 12px;">
                            <div style="background: var(--surface-card); border-radius: 12px; padding: 16px 24px; text-align: center;">
                                <div style="font-family: var(--font-display); font-size: 36px; font-weight: 500; color: var(--accent-dark); line-height: 1;"><?= $daysUntilEvent ?></div>
                                <div class="text-sm text-muted" style="margin-top: 4px;">dage til event</div>
                            </div>
                            <div>
                                <div class="table-cell-primary"><?= htmlspecialchars($booking['event_title'] ?? '') ?></div>
                                <div class="text-sm text-muted"><?= formatDanishDate($booking['event_date']) ?></div>
                                <?php if (!empty($booking['guest_count'])): ?>
                                    <div class="text-sm text-muted"><?= (int) $booking['guest_count'] ?> gæster</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php elseif ($daysUntilEvent === 0): ?>
                        <p style="margin: 0; font-size: 14px; font-weight: 600; color: var(--accent-dark);">Eventet er i dag!</p>
                    <?php else: ?>
                        <p class="text-muted" style="margin: 0; font-size: 14px;">Eventet fandt sted <?= formatDanishDate($booking['event_date']) ?>.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

        <?php elseif ($status === 'completed' || $status === 'reviewed'): ?>
            <div style="display: flex; align-items: center; gap: 12px;">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 24px; height: 24px; color: var(--text-secondary); flex-shrink: 0;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <div>
                    <span class="badge badge-<?= htmlspecialchars($status) ?>"><?= htmlspecialchars(statusLabel($status)) ?></span>
                    <p class="text-muted" style="margin: 8px 0 0; font-size: 14px;">Denne booking er afsluttet.</p>
                </div>
            </div>

        <?php elseif ($status === 'cancelled'): ?>
            <div style="background: rgba(199, 93, 93, 0.08); border: 1px solid rgba(199, 93, 93, 0.2); border-radius: 10px; padding: 20px; display: flex; align-items: center; gap: 12px;">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 24px; height: 24px; color: var(--error); flex-shrink: 0;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
                <div>
                    <div style="font-weight: 600; margin-bottom: 2px;">Booking annulleret</div>
                    <?php if (!empty($booking['cancel_reason'])): ?>
                        <p class="text-muted" style="margin: 0; font-size: 14px;"><?= htmlspecialchars($booking['cancel_reason']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($status === 'refunded'): ?>
            <div style="background: rgba(199, 93, 93, 0.08); border: 1px solid rgba(199, 93, 93, 0.2); border-radius: 10px; padding: 20px; display: flex; align-items: center; gap: 12px;">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 24px; height: 24px; color: var(--error); flex-shrink: 0;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                </svg>
                <div>
                    <div style="font-weight: 600; margin-bottom: 2px;">Refunderet</div>
                    <p class="text-muted" style="margin: 0; font-size: 14px;">Denne booking er blevet refunderet.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Message Thread -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Beskeder</h2>
        <?php if (count($messages) > 0): ?>
            <span class="text-sm text-muted"><?= count($messages) ?> besked<?= count($messages) !== 1 ? 'er' : '' ?></span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (empty($messages)): ?>
            <div class="empty-state" style="padding: 40px 20px;">
                <div class="empty-state-icon" style="width: 48px; height: 48px;">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                </div>
                <h3 style="font-size: 18px;">Ingen beskeder endnu</h3>
                <p style="font-size: 14px;">Send den første besked til arrangøren.</p>
            </div>
        <?php else: ?>
            <div class="message-thread">
                <?php foreach ($messages as $msg): ?>
                    <?php
                        $senderType = $msg['sender_type'] ?? 'organizer';
                        $bubbleClass = 'message-bubble-' . htmlspecialchars($senderType);
                        $senderLabels = [
                            'vendor'    => 'Dig',
                            'organizer' => 'Arrangør',
                            'platform'  => 'PartyParart',
                        ];
                        $senderLabel = isset($senderLabels[$senderType]) ? $senderLabels[$senderType] : ucfirst($senderType);
                        $msgTime = !empty($msg['created_at']) ? date('d/m/Y H:i', strtotime($msg['created_at'])) : '';
                    ?>
                    <div class="message-bubble <?= $bubbleClass ?>">
                        <div><?= nl2br(htmlspecialchars($msg['message'] ?? '')) ?></div>
                        <div class="message-meta">
                            <span class="message-sender"><?= htmlspecialchars($senderLabel) ?></span>
                            <span class="message-time"><?= htmlspecialchars($msgTime) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- New message form -->
        <form method="POST" style="margin-top: 20px;">
            <?= vendorCsrfField() ?>
            <input type="hidden" name="action" value="message">
            <div class="message-input-wrapper">
                <textarea name="message" class="form-textarea" placeholder="Skriv en besked til arrangøren..." rows="3" required></textarea>
                <button type="submit" class="btn btn-primary" style="flex-shrink: 0;">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                    </svg>
                    Send besked
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/vendor-footer.php'; ?>

<?php
/**
 * Vendor Earnings
 * Shows financial overview: total earnings, monthly earnings, pending payouts,
 * Stripe Connect onboarding banner, and completed bookings table.
 */

$pageTitle = 'Indtjening';
require_once __DIR__ . '/../includes/vendor-header.php';
require_once __DIR__ . '/../includes/booking-service.php';
require_once __DIR__ . '/../includes/commission-service.php';

$db = getDB();

// ── Fetch vendor's Stripe onboarding status ──────────────────────
$stmt = $db->prepare("SELECT stripe_account_id, stripe_onboarding_complete, email, company_name FROM vendors WHERE id = ? LIMIT 1");
$stmt->execute([$vendorId]);
$vendorRow = $stmt->fetch();
$stripeOnboardingComplete = !empty($vendorRow['stripe_onboarding_complete']);
$stripeAccountId = $vendorRow['stripe_account_id'] ?? null;

// ── Build base URL for redirects ─────────────────────────────────
$baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

// ── Handle GET: onboard_refresh (session expired) ────────────────
if (isset($_GET['onboard_refresh']) && $_GET['onboard_refresh'] === '1') {
    setFlash('info', 'Sessionen er udløbet. Prøv igen.');
    redirect('/subcontractor/dashboard/earnings.php');
}

// ── Handle GET: onboard_return (back from Stripe) ────────────────
if (isset($_GET['onboard_return']) && $_GET['onboard_return'] === '1') {
    if (!empty($stripeAccountId)) {
        require_once __DIR__ . '/../includes/payment-service.php';
        if (isOnboardingComplete($stripeAccountId)) {
            $stmtUpdate = $db->prepare("UPDATE vendors SET stripe_onboarding_complete = 1 WHERE id = ?");
            $stmtUpdate->execute([$vendorId]);
            $stripeOnboardingComplete = true;
            setFlash('success', 'Din Stripe-konto er forbundet! Du kan nu modtage udbetalinger.');
        } else {
            setFlash('info', 'Opsætningen er ikke fuldført endnu. Prøv igen.');
        }
    }
    redirect('/subcontractor/dashboard/earnings.php');
}

// ── Handle POST: setup_stripe (initiate onboarding) ──────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'setup_stripe') {
    if (!verifyVendorCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Ugyldig anmodning. Prøv igen.');
        redirect('/subcontractor/dashboard/earnings.php');
    }

    require_once __DIR__ . '/../includes/payment-service.php';

    // Create Connect account if vendor doesn't have one yet
    if (empty($stripeAccountId)) {
        $stripeAccountId = createConnectAccount(
            $vendorRow['email'],
            $vendorRow['company_name']
        );
        if (!$stripeAccountId) {
            setFlash('error', 'Kunne ikke oprette Stripe-konto. Prøv igen senere.');
            redirect('/subcontractor/dashboard/earnings.php');
        }
        $stmtSave = $db->prepare("UPDATE vendors SET stripe_account_id = ? WHERE id = ?");
        $stmtSave->execute([$stripeAccountId, $vendorId]);
    }

    // Create onboarding link and redirect to Stripe
    $returnUrl  = $baseUrl . '/subcontractor/dashboard/earnings.php?onboard_return=1';
    $refreshUrl = $baseUrl . '/subcontractor/dashboard/earnings.php?onboard_refresh=1';

    $onboardingUrl = createOnboardingLink($stripeAccountId, $returnUrl, $refreshUrl);
    if (!$onboardingUrl) {
        setFlash('error', 'Kunne ikke oprette onboarding-link. Prøv igen senere.');
        redirect('/subcontractor/dashboard/earnings.php');
    }

    header("Location: $onboardingUrl");
    exit;
}

// ── All-time earnings ────────────────────────────────────────────
$allTimeEarnings = getVendorEarnings($vendorId);

// ── This month's earnings ────────────────────────────────────────
$firstDayOfMonth = date('Y-m-01');
$lastDayOfMonth  = date('Y-m-t');
$monthEarnings   = getVendorEarnings($vendorId, $firstDayOfMonth, $lastDayOfMonth);

// ── Pending payout (deposited/confirmed but not yet completed) ──
$stmtPending = $db->prepare("
    SELECT COALESCE(SUM(vendor_payout), 0) AS pending_payout
    FROM bookings
    WHERE vendor_id = ?
      AND status IN ('deposited', 'confirmed')
");
$stmtPending->execute([$vendorId]);
$pendingPayout = (float)$stmtPending->fetchColumn();

// ── Completed bookings table ─────────────────────────────────────
$stmtCompleted = $db->prepare("
    SELECT
        b.id,
        b.event_date,
        b.quoted_price,
        b.depositum_amount,
        b.commission_amount,
        b.vendor_payout,
        b.completed_at,
        b.status,
        e.name AS event_title,
        vs.title AS service_title
    FROM bookings b
    JOIN events e ON e.id = b.event_id
    LEFT JOIN vendor_services vs ON vs.id = b.vendor_service_id
    WHERE b.vendor_id = ?
      AND b.status IN ('completed', 'reviewed')
    ORDER BY b.completed_at DESC
");
$stmtCompleted->execute([$vendorId]);
$completedBookings = $stmtCompleted->fetchAll();

// ── Compute totals for summary row ───────────────────────────────
$totalQuoted    = 0;
$totalDepositum = 0;
$totalCommission = 0;
$totalPayout    = 0;
foreach ($completedBookings as $cb) {
    $totalQuoted    += (float)$cb['quoted_price'];
    $totalDepositum += (float)$cb['depositum_amount'];
    $totalCommission += (float)$cb['commission_amount'];
    $totalPayout    += (float)$cb['vendor_payout'];
}

// Danish month names for display
$danishMonths = [
    1 => 'Januar', 2 => 'Februar', 3 => 'Marts', 4 => 'April',
    5 => 'Maj', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'December'
];
$currentMonthName = $danishMonths[(int)date('n')];

/** Format a date in Danish style */
if (!function_exists('formatDanishDateEarnings')) {
    function formatDanishDateEarnings(string $dateStr): string {
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
?>

<style>
    .stripe-banner {
        background: linear-gradient(135deg, #635BFF 0%, #7B73FF 100%);
        color: #fff;
        border-radius: 14px;
        padding: 24px 28px;
        margin-bottom: 28px;
        display: flex;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
    }
    .stripe-banner-icon {
        width: 48px;
        height: 48px;
        background: rgba(255,255,255,0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .stripe-banner-icon svg {
        width: 24px;
        height: 24px;
    }
    .stripe-banner-content {
        flex: 1;
        min-width: 200px;
    }
    .stripe-banner-content h3 {
        font-family: var(--font-display);
        font-size: 18px;
        font-weight: 600;
        margin: 0 0 4px;
    }
    .stripe-banner-content p {
        margin: 0;
        font-size: 14px;
        opacity: 0.9;
        line-height: 1.5;
    }
    .stripe-banner .btn-stripe {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: #fff;
        color: #635BFF;
        font-weight: 600;
        font-size: 14px;
        border-radius: 10px;
        text-decoration: none;
        border: none;
        cursor: pointer;
        transition: all 0.2s var(--ease-out);
        white-space: nowrap;
    }
    .stripe-banner .btn-stripe:hover {
        background: #F0EFFF;
        transform: translateY(-1px);
    }
    .stripe-banner .btn-stripe svg {
        width: 16px;
        height: 16px;
    }
    .earnings-table-wrapper {
        overflow-x: auto;
        margin: 0 -20px;
        padding: 0 20px;
    }
    .earnings-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }
    .earnings-table th {
        text-align: left;
        padding: 12px 14px;
        font-weight: 600;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-secondary);
        border-bottom: 2px solid var(--border);
        white-space: nowrap;
    }
    .earnings-table td {
        padding: 12px 14px;
        border-bottom: 1px solid var(--border);
        color: var(--text);
        white-space: nowrap;
    }
    .earnings-table tr:last-child td {
        border-bottom: none;
    }
    .earnings-table .summary-row td {
        border-top: 2px solid var(--text);
        border-bottom: none;
        font-weight: 700;
        padding-top: 14px;
        color: var(--text);
    }
    .earnings-table .amount {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }
    .earnings-table a {
        color: var(--accent-dark);
        text-decoration: none;
        font-weight: 500;
    }
    .earnings-table a:hover {
        text-decoration: underline;
    }

    @media (max-width: 768px) {
        .stripe-banner {
            padding: 18px 20px;
            gap: 14px;
        }
        .stripe-banner-content h3 {
            font-size: 16px;
        }
        .earnings-table th,
        .earnings-table td {
            padding: 10px 8px;
            font-size: 13px;
        }
    }
</style>

<!-- Page Header -->
<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Indtjening</h1>
        <p class="page-subtitle">Overblik over din omsaetning og udbetalinger</p>
    </div>
</div>

<?php if (!$stripeOnboardingComplete): ?>
<!-- Stripe Connect Onboarding Banner -->
<div class="stripe-banner">
    <div class="stripe-banner-icon">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
        </svg>
    </div>
    <div class="stripe-banner-content">
        <?php if (!empty($stripeAccountId)): ?>
            <h3>Fortsæt opsætning af udbetaling</h3>
            <p>Du har påbegyndt opsætningen, men den er ikke fuldført endnu. Fortsæt for at modtage udbetalinger.</p>
        <?php else: ?>
            <h3>Opsæt udbetaling</h3>
            <p>For at modtage udbetalinger skal du forbinde din bankkonto via Stripe. Det tager kun et par minutter.</p>
        <?php endif; ?>
    </div>
    <form method="POST" style="flex-shrink: 0;">
        <?= vendorCsrfField() ?>
        <button type="submit" name="action" value="setup_stripe" class="btn-stripe">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
            </svg>
            <?= !empty($stripeAccountId) ? 'Fortsæt opsætning' : 'Opsæt udbetaling' ?>
        </button>
    </form>
</div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="stats-grid">
    <!-- All-time earnings -->
    <div class="stat-card">
        <div class="stat-info">
            <div class="stat-value"><?= number_format($allTimeEarnings['total_paid_out'], 2, ',', '.') ?> kr</div>
            <div class="stat-label">Total indtjent</div>
        </div>
        <div class="stat-icon success">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
    </div>

    <!-- This month -->
    <div class="stat-card">
        <div class="stat-info">
            <div class="stat-value"><?= number_format($monthEarnings['total_paid_out'], 2, ',', '.') ?> kr</div>
            <div class="stat-label">Denne maaned (<?= $currentMonthName ?>)</div>
        </div>
        <div class="stat-icon sage">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
            </svg>
        </div>
    </div>

    <!-- Pending payout -->
    <div class="stat-card">
        <div class="stat-info">
            <div class="stat-value"><?= number_format($pendingPayout, 2, ',', '.') ?> kr</div>
            <div class="stat-label">Afventende udbetaling</div>
        </div>
        <div class="stat-icon gold">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
    </div>
</div>

<!-- Completed Bookings Table -->
<div class="card" style="margin-top: 28px;">
    <div class="card-header">
        <h2 class="card-title">Afsluttede bookinger</h2>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($completedBookings)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                </div>
                <h3>Ingen afsluttede bookinger</h3>
                <p>Afsluttede bookinger og din indtjening vises her.</p>
            </div>
        <?php else: ?>
            <div class="earnings-table-wrapper">
                <table class="earnings-table">
                    <thead>
                        <tr>
                            <th>Dato</th>
                            <th>Event</th>
                            <th>Ydelse</th>
                            <th class="amount">Tilbudt pris</th>
                            <th class="amount">Depositum</th>
                            <th class="amount">Kommission</th>
                            <th class="amount">Din udbetaling</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($completedBookings as $cb): ?>
                            <tr>
                                <td><?= formatDanishDateEarnings($cb['completed_at'] ?? $cb['event_date']) ?></td>
                                <td>
                                    <a href="/subcontractor/dashboard/booking-detail.php?id=<?= (int)$cb['id'] ?>">
                                        <?= htmlspecialchars($cb['event_title']) ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($cb['service_title'] ?? '-') ?></td>
                                <td class="amount"><?= number_format((float)$cb['quoted_price'], 2, ',', '.') ?> kr</td>
                                <td class="amount"><?= number_format((float)$cb['depositum_amount'], 2, ',', '.') ?> kr</td>
                                <td class="amount"><?= number_format((float)$cb['commission_amount'], 2, ',', '.') ?> kr</td>
                                <td class="amount" style="font-weight: 600;"><?= number_format((float)$cb['vendor_payout'], 2, ',', '.') ?> kr</td>
                            </tr>
                        <?php endforeach; ?>
                        <!-- Summary row -->
                        <tr class="summary-row">
                            <td colspan="3">Total (<?= count($completedBookings) ?> bookinger)</td>
                            <td class="amount"><?= number_format($totalQuoted, 2, ',', '.') ?> kr</td>
                            <td class="amount"><?= number_format($totalDepositum, 2, ',', '.') ?> kr</td>
                            <td class="amount"><?= number_format($totalCommission, 2, ',', '.') ?> kr</td>
                            <td class="amount"><?= number_format($totalPayout, 2, ',', '.') ?> kr</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/vendor-footer.php'; ?>

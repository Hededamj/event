<?php
/**
 * Booking Request Page
 * Organizers send booking requests to vendors from this page.
 * GET params: vendor_id (required), service_id (optional)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth-account.php';
require_once __DIR__ . '/includes/booking-service.php';
require_once __DIR__ . '/includes/vendor-validation.php';

// Require organizer login
requireAccountLogin();

$accountId = getCurrentAccountId();
$account = getCurrentAccount();
$db = getDB();

// ============================================================
// Load vendor
// ============================================================

$vendorId = isset($_GET['vendor_id']) ? (int) $_GET['vendor_id'] : 0;
$serviceId = isset($_GET['service_id']) ? (int) $_GET['service_id'] : null;

if ($vendorId <= 0) {
    setFlash('error', 'Leverandør ikke fundet.');
    redirect('/subcontractor/');
}

$stmt = $db->prepare("
    SELECT id, company_name, contact_name, city, avg_rating, review_count,
           booking_count, short_description, logo_filename, nationwide
    FROM vendors
    WHERE id = ? AND status = 'approved' AND is_active = 1
    LIMIT 1
");
$stmt->execute([$vendorId]);
$vendor = $stmt->fetch();

if (!$vendor) {
    setFlash('error', 'Leverandør ikke fundet eller ikke godkendt.');
    redirect('/subcontractor/');
}

// ============================================================
// Load selected service (if service_id provided)
// ============================================================

$selectedService = null;
if ($serviceId !== null && $serviceId > 0) {
    $stmt = $db->prepare("
        SELECT id, title, description, price_from, price_to, price_unit, duration_hours
        FROM vendor_services
        WHERE id = ? AND vendor_id = ? AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$serviceId, $vendorId]);
    $selectedService = $stmt->fetch();
    if (!$selectedService) {
        $serviceId = null;
    }
}

// ============================================================
// Load organizer's events
// ============================================================

$stmt = $db->prepare("
    SELECT e.id, e.name, e.event_date,
           (SELECT SUM(g.adults_count + g.children_count) FROM guests g WHERE g.event_id = e.id) AS guest_count
    FROM event_owners eo
    JOIN events e ON e.id = eo.event_id
    WHERE eo.account_id = ? AND eo.role = 'owner'
    ORDER BY e.event_date ASC
");
$stmt->execute([$accountId]);
$events = $stmt->fetchAll();

// Price unit labels
$priceUnitLabels = [
    'fixed'      => 'fast pris',
    'per_person'  => 'pr. person',
    'per_hour'    => 'pr. time',
];

// ============================================================
// Handle POST
// ============================================================

$errors = [];
$formData = [
    'event_id'    => '',
    'event_date'  => '',
    'guest_count' => '',
    'message'     => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyAccountCsrfToken($csrfToken)) {
        $errors[] = 'Ugyldig sikkerhedstoken. Prøv venligst igen.';
    }

    // Collect form data
    $formData['event_id']    = $_POST['event_id'] ?? '';
    $formData['event_date']  = trim($_POST['event_date'] ?? '');
    $formData['guest_count'] = trim($_POST['guest_count'] ?? '');
    $formData['message']     = $_POST['message'] ?? '';

    // Validate event_id
    $eventId = (int) $formData['event_id'];
    if ($eventId <= 0) {
        $errors[] = 'Valg venligst et event.';
    }

    if (empty($errors)) {
        // Validate booking request fields
        $validationErrors = validateBookingRequest([
            'event_date'  => $formData['event_date'],
            'message'     => $formData['message'],
            'guest_count' => $formData['guest_count'] !== '' ? $formData['guest_count'] : null,
        ]);
        $errors = array_merge($errors, $validationErrors);
    }

    if (empty($errors)) {
        $guestCount = $formData['guest_count'] !== '' ? (int) $formData['guest_count'] : null;

        $result = createBookingRequest(
            $eventId,
            $vendorId,
            $accountId,
            $serviceId,
            trim($formData['message']),
            $formData['event_date'],
            $guestCount
        );

        if ($result['success']) {
            setFlash('success', 'Din forespørgsel er sendt! Leverandøren vender tilbage med et tilbud.');
            redirect('/app/events/manage.php?event=' . $eventId . '&page=vendors');
        } else {
            $errors[] = $result['error'] ?? 'Der opstod en fejl. Prøv venligst igen.';
        }
    }
}

// ============================================================
// Build events JSON for JavaScript auto-fill
// ============================================================

$eventsJson = json_encode(array_map(function ($e) {
    return [
        'id'          => (int) $e['id'],
        'name'        => $e['name'],
        'event_date'  => $e['event_date'] ?? '',
        'guest_count' => $e['guest_count'] !== null ? (int) $e['guest_count'] : '',
    ];
}, $events), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// ============================================================
// Page title
// ============================================================

$pageTitle = 'Send forespørgsel til' . escape($vendor['company_name']);

require_once __DIR__ . '/includes/marketplace-header.php';
?>

<style>
/* ============================================
   Booking Page Base Styles
   ============================================ */
:root {
    --cream: #FAF9F7;
    --cream-dark: #F5F3EF;
    --sage: #A8B5A0;
    --sage-light: #C8D4C2;
    --sage-dark: #7A8B72;
    --charcoal: #2C2C2C;
    --charcoal-light: #4A4A4A;
    --gold: #C9A962;
    --white: #FFFFFF;
    --error: #C75D5D;
    --success: #6B9E6B;
    --warning: #D4A843;
    --font-display: 'Cormorant Garamond', Georgia, serif;
    --font-body: 'DM Sans', -apple-system, sans-serif;
    --ease-out: cubic-bezier(0.16, 1, 0.3, 1);
}

*, *::before, *::after {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: var(--font-body);
    background: var(--surface);
    color: var(--text);
    min-height: 100vh;
    -webkit-font-smoothing: antialiased;
    line-height: 1.5;
}

a { color: inherit; text-decoration: none; }

/* ============================================
   Page Container
   ============================================ */
.book-container {
    max-width: 1100px;
    margin: 0 auto;
    padding: 32px 24px 60px;
}

.book-back-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    font-weight: 500;
    color: var(--text-secondary);
    margin-bottom: 24px;
    transition: color 0.2s;
}

.book-back-link:hover {
    color: var(--accent-dark);
}

.book-back-link svg {
    width: 18px;
    height: 18px;
}

.book-page-title {
    font-family: var(--font-display);
    font-size: 32px;
    font-weight: 500;
    color: var(--text);
    margin-bottom: 8px;
    line-height: 1.2;
}

.book-page-subtitle {
    font-size: 15px;
    color: var(--text-secondary);
    margin-bottom: 32px;
}

/* ============================================
   Layout: Form Left + Vendor Card Right
   ============================================ */
.book-layout {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 32px;
    align-items: start;
}

/* ============================================
   Alerts
   ============================================ */
.book-alert {
    padding: 14px 18px;
    border-radius: 10px;
    font-size: 14px;
    margin-bottom: 20px;
    line-height: 1.5;
}

.book-alert-error {
    background: rgba(199, 93, 93, 0.08);
    border: 1px solid rgba(199, 93, 93, 0.2);
    color: var(--error);
}

.book-alert-error ul {
    margin: 6px 0 0 16px;
    padding: 0;
}

.book-alert-error li {
    margin-bottom: 2px;
}

.book-alert-warning {
    background: rgba(212, 168, 67, 0.08);
    border: 1px solid rgba(212, 168, 67, 0.2);
    color: #8B6914;
}

/* ============================================
   Form Card
   ============================================ */
.book-form-card {
    background: var(--surface-card);
    border-radius: 16px;
    padding: 32px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 4px 16px rgba(0,0,0,0.04);
}

.book-form-section-title {
    font-family: var(--font-display);
    font-size: 20px;
    font-weight: 500;
    color: var(--text);
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border);
}

.book-form-group {
    margin-bottom: 20px;
}

.book-form-group:last-child {
    margin-bottom: 0;
}

.book-form-label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 6px;
}

.book-form-label .required {
    color: var(--error);
    margin-left: 2px;
}

.book-form-hint {
    font-size: 13px;
    color: var(--text-secondary);
    margin-top: 4px;
}

.book-form-input,
.book-form-select,
.book-form-textarea {
    width: 100%;
    padding: 12px 16px;
    font-family: var(--font-body);
    font-size: 14px;
    color: var(--text);
    background: var(--surface-card);
    border: 2px solid var(--border);
    border-radius: 10px;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
    -webkit-appearance: none;
    appearance: none;
}

.book-form-input:focus,
.book-form-select:focus,
.book-form-textarea:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(168, 181, 160, 0.15);
}

.book-form-input::placeholder,
.book-form-textarea::placeholder {
    color: #B0ACA4;
}

.book-form-select {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' stroke='%234A4A4A' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M4 6l4 4 4-4'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 14px center;
    background-size: 16px;
    padding-right: 40px;
    cursor: pointer;
}

.book-form-textarea {
    resize: vertical;
    min-height: 140px;
    line-height: 1.6;
}

.book-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.book-form-char-count {
    text-align: right;
    font-size: 12px;
    color: var(--text-secondary);
    margin-top: 4px;
}

.book-form-char-count.warning {
    color: var(--warning);
}

.book-form-char-count.over {
    color: var(--error);
    font-weight: 600;
}

/* ============================================
   Submit Button
   ============================================ */
.book-submit-section {
    margin-top: 28px;
    padding-top: 20px;
    border-top: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 16px;
}

.book-submit-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 14px 28px;
    font-family: var(--font-body);
    font-size: 15px;
    font-weight: 600;
    color: var(--surface-card);
    background: var(--accent);
    border: none;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.25s var(--ease-out);
    white-space: nowrap;
}

.book-submit-btn:hover {
    background: var(--accent-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(122, 139, 114, 0.3);
}

.book-submit-btn svg {
    width: 20px;
    height: 20px;
}

.book-cancel-link {
    font-size: 14px;
    color: var(--text-secondary);
    transition: color 0.2s;
}

.book-cancel-link:hover {
    color: var(--text);
}

/* ============================================
   Vendor Sidebar Card
   ============================================ */
.book-vendor-card {
    background: var(--surface-card);
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 6px 24px rgba(0,0,0,0.06);
    position: sticky;
    top: 88px;
}

.book-vendor-header {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 18px;
}

.book-vendor-logo {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    object-fit: cover;
    background: var(--surface);
    flex-shrink: 0;
}

.book-vendor-logo-placeholder {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    background: var(--accent-light);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: var(--font-display);
    font-size: 24px;
    font-weight: 600;
    color: var(--surface-card);
    flex-shrink: 0;
}

.book-vendor-info h3 {
    font-family: var(--font-display);
    font-size: 20px;
    font-weight: 500;
    color: var(--text);
    line-height: 1.2;
}

.book-vendor-location {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 13px;
    color: var(--text-secondary);
    margin-top: 2px;
}

.book-vendor-location svg {
    width: 14px;
    height: 14px;
    flex-shrink: 0;
}

.book-vendor-rating {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    color: var(--text-secondary);
    margin-bottom: 18px;
    padding-bottom: 18px;
    border-bottom: 1px solid var(--border);
}

.book-vendor-rating svg {
    width: 18px;
    height: 18px;
}

.book-vendor-rating .star-filled {
    color: var(--warning);
    fill: var(--warning);
}

.book-vendor-rating-value {
    font-weight: 600;
    color: var(--text);
}

/* ============================================
   Service Detail on Sidebar
   ============================================ */
.book-service-detail {
    margin-bottom: 18px;
    padding-bottom: 18px;
    border-bottom: 1px solid var(--border);
}

.book-service-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--accent-dark);
    margin-bottom: 8px;
}

.book-service-name {
    font-family: var(--font-display);
    font-size: 18px;
    font-weight: 500;
    color: var(--text);
    margin-bottom: 4px;
}

.book-service-price {
    font-size: 14px;
    color: var(--text-secondary);
}

.book-service-price strong {
    color: var(--text);
    font-weight: 600;
}

.book-service-description {
    font-size: 13px;
    color: var(--text-secondary);
    margin-top: 8px;
    line-height: 1.5;
}

/* ============================================
   Vendor card info items
   ============================================ */
.book-vendor-stats {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.book-vendor-stat {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    color: var(--text-secondary);
}

.book-vendor-stat svg {
    width: 16px;
    height: 16px;
    color: var(--accent-dark);
    flex-shrink: 0;
}

/* ============================================
   No Events Warning
   ============================================ */
.book-no-events {
    text-align: center;
    padding: 40px 24px;
}

.book-no-events svg {
    width: 48px;
    height: 48px;
    color: var(--border);
    margin-bottom: 12px;
}

.book-no-events h3 {
    font-family: var(--font-display);
    font-size: 20px;
    font-weight: 500;
    color: var(--text);
    margin-bottom: 6px;
}

.book-no-events p {
    font-size: 14px;
    color: var(--text-secondary);
    margin-bottom: 20px;
}

.book-no-events-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    font-family: var(--font-body);
    font-size: 14px;
    font-weight: 600;
    color: var(--surface-card);
    background: var(--accent);
    border-radius: 10px;
    transition: all 0.25s var(--ease-out);
}

.book-no-events-btn:hover {
    background: var(--accent-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(122, 139, 114, 0.3);
}

/* ============================================
   Responsive: 1024px
   ============================================ */
@media (max-width: 1024px) {
    .book-layout {
        grid-template-columns: 1fr;
    }

    .book-vendor-card {
        position: static;
        order: -1;
    }
}

/* ============================================
   Responsive: 768px
   ============================================ */
@media (max-width: 768px) {
    .book-container {
        padding: 20px 16px 40px;
    }

    .book-page-title {
        font-size: 26px;
    }

    .book-form-card {
        padding: 24px 20px;
    }

    .book-form-row {
        grid-template-columns: 1fr;
    }

    .book-submit-section {
        flex-direction: column;
        align-items: stretch;
    }

    .book-submit-btn {
        justify-content: center;
    }

    .book-cancel-link {
        text-align: center;
    }
}

/* ============================================
   Responsive: 480px
   ============================================ */
@media (max-width: 480px) {
    .book-container {
        padding: 16px 12px 32px;
    }

    .book-page-title {
        font-size: 22px;
    }

    .book-form-card {
        padding: 20px 16px;
    }

    .book-vendor-card {
        padding: 20px;
    }
}
</style>

<div class="book-container">

    <!-- Back link -->
    <a href="/subcontractor/profile.php?id=<?= $vendorId ?>" class="book-back-link">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Tilbage til <?= escape($vendor['company_name']) ?>
    </a>

    <h1 class="book-page-title">Send forespørgsel</h1>
    <p class="book-page-subtitle">Udfyld formularen for at sende en forespørgsel til <?= escape($vendor['company_name']) ?>. De vender tilbage med et tilbud.</p>

    <!-- Errors -->
    <?php if (!empty($errors)): ?>
        <div class="book-alert book-alert-error">
            <strong>Der opstod fejl:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= escape($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="book-layout">

        <!-- ============================================ -->
        <!-- LEFT: Form -->
        <!-- ============================================ -->
        <div class="book-form-area">

            <?php if (empty($events)): ?>
                <!-- No events state -->
                <div class="book-form-card">
                    <div class="book-no-events">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <h3>Du har ingen events endnu</h3>
                        <p>For at sende en forespørgsel skal du forst oprette et event.</p>
                        <a href="/app/events/create.php?return=<?= urlencode('/subcontractor/book.php?vendor_id=' . $vendorId . ($serviceId ? '&service_id=' . $serviceId : '')) ?>" class="book-no-events-btn">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Opret et event
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Booking form -->
                <form method="POST" action="/subcontractor/book.php?vendor_id=<?= $vendorId ?><?= $serviceId ? '&service_id=' . $serviceId : '' ?>" class="book-form-card" id="bookingForm">
                    <?= accountCsrfField() ?>

                    <h2 class="book-form-section-title">Detaljer om dit arrangement</h2>

                    <!-- Event selector -->
                    <div class="book-form-group">
                        <label class="book-form-label" for="event_id">
                            Valg event <span class="required">*</span>
                        </label>
                        <select class="book-form-select" id="event_id" name="event_id" required>
                            <option value="">-- Valg et event --</option>
                            <?php foreach ($events as $event): ?>
                                <option
                                    value="<?= (int) $event['id'] ?>"
                                    <?= $formData['event_id'] == $event['id'] ? 'selected' : '' ?>
                                    data-date="<?= escape($event['event_date'] ?? '') ?>"
                                    data-guests="<?= $event['guest_count'] !== null ? (int) $event['guest_count'] : '' ?>"
                                >
                                    <?= escape($event['name']) ?>
                                    <?php if (!empty($event['event_date'])): ?>
                                        (<?= formatDate($event['event_date']) ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Event date + Guest count -->
                    <div class="book-form-row">
                        <div class="book-form-group">
                            <label class="book-form-label" for="event_date">
                                Eventdato <span class="required">*</span>
                            </label>
                            <input
                                type="date"
                                class="book-form-input"
                                id="event_date"
                                name="event_date"
                                value="<?= escape($formData['event_date']) ?>"
                                min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                                required
                            >
                        </div>
                        <div class="book-form-group">
                            <label class="book-form-label" for="guest_count">
                                Antal gaster
                            </label>
                            <input
                                type="number"
                                class="book-form-input"
                                id="guest_count"
                                name="guest_count"
                                value="<?= escape($formData['guest_count']) ?>"
                                min="1"
                                placeholder="F.eks. 50"
                            >
                        </div>
                    </div>

                    <!-- Message -->
                    <div class="book-form-group">
                        <label class="book-form-label" for="message">
                            Besked til leverandøren <span class="required">*</span>
                        </label>
                        <textarea
                            class="book-form-textarea"
                            id="message"
                            name="message"
                            maxlength="2000"
                            required
                            placeholder="Beskriv dit arrangement og dine onsker..."
                        ><?= escape($formData['message']) ?></textarea>
                        <div class="book-form-char-count" id="charCount">
                            <span id="charCurrent">0</span> / 2000
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="book-submit-section">
                        <button type="submit" class="book-submit-btn">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                            Send forespørgsel
                        </button>
                        <a href="/subcontractor/profile.php?id=<?= $vendorId ?>" class="book-cancel-link">Annuller</a>
                    </div>
                </form>
            <?php endif; ?>

        </div>

        <!-- ============================================ -->
        <!-- RIGHT: Vendor Card -->
        <!-- ============================================ -->
        <aside>
            <div class="book-vendor-card">

                <!-- Vendor header -->
                <div class="book-vendor-header">
                    <?php if (!empty($vendor['logo_filename'])): ?>
                        <img class="book-vendor-logo" src="/uploads/vendors/<?= escape($vendor['logo_filename']) ?>" alt="<?= escape($vendor['company_name']) ?>">
                    <?php else: ?>
                        <div class="book-vendor-logo-placeholder">
                            <?= mb_strtoupper(mb_substr($vendor['company_name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <div class="book-vendor-info">
                        <h3><?= escape($vendor['company_name']) ?></h3>
                        <?php if (!empty($vendor['city'])): ?>
                            <div class="book-vendor-location">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <?= escape($vendor['city']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Rating -->
                <?php if ((float) $vendor['avg_rating'] > 0): ?>
                    <div class="book-vendor-rating">
                        <svg class="star-filled" fill="currentColor" viewBox="0 0 24 24"><path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                        <span class="book-vendor-rating-value"><?= number_format((float) $vendor['avg_rating'], 1, ',', '.') ?></span>
                        <span>(<?= (int) $vendor['review_count'] ?> <?= (int) $vendor['review_count'] === 1 ? 'anmeldelse' : 'anmeldelser' ?>)</span>
                    </div>
                <?php else: ?>
                    <div class="book-vendor-rating">
                        <span>Ingen anmeldelser endnu</span>
                    </div>
                <?php endif; ?>

                <!-- Selected service detail -->
                <?php if ($selectedService): ?>
                    <div class="book-service-detail">
                        <div class="book-service-label">Valgt ydelse</div>
                        <div class="book-service-name"><?= escape($selectedService['title']) ?></div>
                        <div class="book-service-price">
                            <?php if (!empty($selectedService['price_to']) && (float) $selectedService['price_to'] > 0): ?>
                                <strong><?= number_format((float) $selectedService['price_from'], 0, ',', '.') ?> - <?= number_format((float) $selectedService['price_to'], 0, ',', '.') ?> kr</strong>
                            <?php else: ?>
                                Fra <strong><?= number_format((float) $selectedService['price_from'], 0, ',', '.') ?> kr</strong>
                            <?php endif; ?>
                            <span style="margin-left: 4px;"><?= escape($priceUnitLabels[$selectedService['price_unit']] ?? 'fast pris') ?></span>
                        </div>
                        <?php if (!empty($selectedService['description'])): ?>
                            <p class="book-service-description"><?= nl2br(escape(mb_strimwidth($selectedService['description'], 0, 200, '...'))) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Stats -->
                <div class="book-vendor-stats">
                    <?php if ((int) $vendor['booking_count'] > 0): ?>
                        <div class="book-vendor-stat">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <?= (int) $vendor['booking_count'] ?> bookinger gennemfort
                        </div>
                    <?php endif; ?>
                    <div class="book-vendor-stat">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Svarer typisk inden for 24 timer
                    </div>
                    <div class="book-vendor-stat">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        Depositum-garanti via PartyParart
                    </div>
                </div>

            </div>
        </aside>

    </div>
</div>

<!-- ============================================================ -->
<!-- JavaScript: Event auto-fill + Character counter -->
<!-- ============================================================ -->
<script>
(function() {
    var eventsData = <?= $eventsJson ?>;
    var eventSelect = document.getElementById('event_id');
    var dateInput = document.getElementById('event_date');
    var guestInput = document.getElementById('guest_count');
    var messageArea = document.getElementById('message');
    var charCurrent = document.getElementById('charCurrent');
    var charCount = document.getElementById('charCount');

    // Auto-fill date + guest count when event is selected
    if (eventSelect && dateInput && guestInput) {
        eventSelect.addEventListener('change', function() {
            var selectedId = parseInt(this.value, 10);
            if (!selectedId) return;

            for (var i = 0; i < eventsData.length; i++) {
                if (eventsData[i].id === selectedId) {
                    if (eventsData[i].event_date) {
                        dateInput.value = eventsData[i].event_date;
                    }
                    if (eventsData[i].guest_count !== '') {
                        guestInput.value = eventsData[i].guest_count;
                    }
                    break;
                }
            }
        });
    }

    // Character counter for message
    if (messageArea && charCurrent && charCount) {
        function updateCharCount() {
            var len = messageArea.value.length;
            charCurrent.textContent = len;

            charCount.classList.remove('warning', 'over');
            if (len > 2000) {
                charCount.classList.add('over');
            } else if (len > 1800) {
                charCount.classList.add('warning');
            }
        }

        messageArea.addEventListener('input', updateCharCount);
        // Initialize on page load (for form re-display with errors)
        updateCharCount();
    }
})();
</script>

<?php require_once __DIR__ . '/includes/marketplace-footer.php'; ?>

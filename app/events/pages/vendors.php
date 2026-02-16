<?php
/**
 * Vendors Management Page
 * Included by manage.php when $page === 'vendors'
 *
 * Available variables: $event, $eventId, $accountId, $db
 * Shows marketplace bookings and manually added vendors.
 */

require_once __DIR__ . '/../../../subcontractor/includes/booking-service.php';

// ── Handle POST actions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyAccountCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Ugyldig anmodning.');
        redirect("?id=$eventId&page=vendors");
    }

    $action = $_POST['action'];

    // ── Add manual vendor ──
    if ($action === 'add_manual') {
        $companyName  = trim($_POST['company_name'] ?? '');
        $category     = trim($_POST['category'] ?? '');
        $contactName  = trim($_POST['contact_name'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $phone        = trim($_POST['phone'] ?? '');
        $agreedPrice  = !empty($_POST['agreed_price']) ? (float)$_POST['agreed_price'] : null;
        $notes        = trim($_POST['notes'] ?? '');

        if ($companyName) {
            $stmt = $db->prepare("
                INSERT INTO manual_vendors (event_id, company_name, category, contact_name, email, phone, agreed_price, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$eventId, $companyName, $category, $contactName, $email, $phone, $agreedPrice, $notes]);
            setFlash('success', 'Leverandor tilfojet.');
        }
        redirect("?id=$eventId&page=vendors");

    // ── Edit manual vendor ──
    } elseif ($action === 'edit_manual') {
        $id           = (int)($_POST['id'] ?? 0);
        $companyName  = trim($_POST['company_name'] ?? '');
        $category     = trim($_POST['category'] ?? '');
        $contactName  = trim($_POST['contact_name'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $phone        = trim($_POST['phone'] ?? '');
        $agreedPrice  = !empty($_POST['agreed_price']) ? (float)$_POST['agreed_price'] : null;
        $notes        = trim($_POST['notes'] ?? '');

        if ($companyName && $id) {
            $stmt = $db->prepare("
                UPDATE manual_vendors
                SET company_name = ?, category = ?, contact_name = ?, email = ?, phone = ?, agreed_price = ?, notes = ?
                WHERE id = ? AND event_id = ?
            ");
            $stmt->execute([$companyName, $category, $contactName, $email, $phone, $agreedPrice, $notes, $id, $eventId]);
            setFlash('success', 'Leverandor opdateret.');
        }
        redirect("?id=$eventId&page=vendors");

    // ── Delete manual vendor ──
    } elseif ($action === 'delete_manual') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $db->prepare("DELETE FROM manual_vendors WHERE id = ? AND event_id = ?");
            $stmt->execute([$id, $eventId]);
            setFlash('success', 'Leverandor slettet.');
        }
        redirect("?id=$eventId&page=vendors");

    // ── Toggle paid status ──
    } elseif ($action === 'toggle_paid') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $db->prepare("
                UPDATE manual_vendors
                SET is_paid = NOT COALESCE(is_paid, 0),
                    paid_at = CASE WHEN COALESCE(is_paid, 0) = 0 THEN CURDATE() ELSE NULL END
                WHERE id = ? AND event_id = ?
            ");
            $stmt->execute([$id, $eventId]);
        }
        redirect("?id=$eventId&page=vendors");
    }
}

// ── Fetch data ───────────────────────────────────────────────

// Marketplace bookings
$bookings = getEventBookings($eventId);

// Manual vendors
$stmt = $db->prepare("SELECT * FROM manual_vendors WHERE event_id = ? ORDER BY created_at ASC");
$stmt->execute([$eventId]);
$manualVendors = $stmt->fetchAll();

// ── Budget summary calculations ──────────────────────────────
$marketplaceTotal    = 0;
$marketplaceDeposit  = 0;
foreach ($bookings as $b) {
    if ($b['quoted_price'] && !in_array($b['status'], ['cancelled', 'refunded'])) {
        $marketplaceTotal += (float)$b['quoted_price'];
    }
    if ($b['depositum_amount'] && in_array($b['status'], ['deposited', 'confirmed', 'completed', 'reviewed'])) {
        $marketplaceDeposit += (float)$b['depositum_amount'];
    }
}

$manualTotal = 0;
$manualPaid  = 0;
foreach ($manualVendors as $mv) {
    if ($mv['agreed_price']) {
        $manualTotal += (float)$mv['agreed_price'];
    }
    if ($mv['is_paid'] && $mv['agreed_price']) {
        $manualPaid += (float)$mv['agreed_price'];
    }
}

$grandTotal = $marketplaceTotal + $manualTotal;
$totalPaid  = $marketplaceDeposit + $manualPaid;

// Status labels in Danish
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
?>

<!-- ============================================================
     SECTION 1: Marketplace Bookings
     ============================================================ -->

<div class="page-header-actions">
    <div>
        <h2 class="section-title">Leverandorer fra markedspladsen</h2>
        <p class="section-subtitle"><?= count($bookings) ?> booking<?= count($bookings) !== 1 ? 's' : '' ?></p>
    </div>
    <a href="/subcontractor/" class="btn btn-primary">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:16px;height:16px;">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
        </svg>
        Find leverandor
    </a>
</div>

<?php if (empty($bookings)): ?>
<div class="card">
    <div class="empty-state">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"></path>
        </svg>
        <h3>Ingen bookinger endnu</h3>
        <p>Find leverandorer pa markedspladsen og send en bookingforesporgsel.</p>
        <a href="/subcontractor/" class="btn btn-primary" style="margin-top: 16px;">Udforsk markedspladsen</a>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <thead>
                <tr style="border-bottom: 2px solid var(--cream-dark);">
                    <th style="text-align: left; padding: 12px 8px; font-weight: 600; color: var(--charcoal-light);">Leverandor</th>
                    <th style="text-align: left; padding: 12px 8px; font-weight: 600; color: var(--charcoal-light);">Ydelse</th>
                    <th style="text-align: right; padding: 12px 8px; font-weight: 600; color: var(--charcoal-light);">Pris</th>
                    <th style="text-align: right; padding: 12px 8px; font-weight: 600; color: var(--charcoal-light);">Depositum</th>
                    <th style="text-align: center; padding: 12px 8px; font-weight: 600; color: var(--charcoal-light);">Status</th>
                    <th style="text-align: right; padding: 12px 8px; font-weight: 600; color: var(--charcoal-light);">Handling</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $b): ?>
                <tr style="border-bottom: 1px solid var(--cream-dark);">
                    <td style="padding: 12px 8px; font-weight: 500;">
                        <?= htmlspecialchars($b['vendor_company_name']) ?>
                    </td>
                    <td style="padding: 12px 8px; color: var(--charcoal-light);">
                        <?= htmlspecialchars($b['service_title'] ?? 'Generel') ?>
                    </td>
                    <td style="padding: 12px 8px; text-align: right;">
                        <?php if ($b['quoted_price']): ?>
                            <?= number_format((float)$b['quoted_price'], 0, ',', '.') ?> kr
                        <?php else: ?>
                            <span style="color: var(--charcoal-light);">-</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 12px 8px; text-align: right;">
                        <?php if ($b['depositum_amount']): ?>
                            <?= number_format((float)$b['depositum_amount'], 0, ',', '.') ?> kr
                        <?php else: ?>
                            <span style="color: var(--charcoal-light);">-</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 12px 8px; text-align: center;">
                        <span style="display: inline-block; padding: 4px 10px; border-radius: 8px; font-size: 12px; font-weight: 600; <?= $statusColors[$b['status']] ?? '' ?>">
                            <?= $statusLabels[$b['status']] ?? ucfirst($b['status']) ?>
                        </span>
                    </td>
                    <td style="padding: 12px 8px; text-align: right; white-space: nowrap;">
                        <?php
                        $bookingDetailUrl = "?id=$eventId&page=vendor-booking&booking_id=" . $b['id'];
                        switch ($b['status']):
                            case 'requested': ?>
                                <span style="font-size: 13px; color: var(--charcoal-light);">Afventer svar</span>
                                <?php break;
                            case 'quoted': ?>
                                <a href="<?= $bookingDetailUrl ?>" class="btn btn-sm btn-primary">Se tilbud</a>
                                <?php break;
                            case 'accepted': ?>
                                <a href="/subcontractor/payment.php?booking_id=<?= $b['id'] ?>" class="btn btn-sm btn-sage">Betal depositum</a>
                                <?php break;
                            case 'deposited':
                            case 'confirmed': ?>
                                <span style="display: inline-block; padding: 4px 10px; border-radius: 8px; font-size: 12px; font-weight: 600; background: #D1FAE5; color: #065F46;">Bekraeftet</span>
                                <a href="<?= $bookingDetailUrl ?>" class="btn btn-sm btn-secondary" style="margin-left: 4px;">Beskeder</a>
                                <?php break;
                            case 'completed': ?>
                                <a href="<?= $bookingDetailUrl ?>" class="btn btn-sm btn-secondary">Skriv anmeldelse</a>
                                <?php break;
                            case 'cancelled':
                            case 'refunded': ?>
                                <span style="font-size: 13px; color: var(--charcoal-light);">
                                    <?= $statusLabels[$b['status']] ?>
                                </span>
                                <?php break;
                            default: ?>
                                <a href="<?= $bookingDetailUrl ?>" class="btn btn-sm btn-secondary">Detaljer</a>
                        <?php endswitch; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>


<!-- ============================================================
     SECTION 2: Manual Vendors
     ============================================================ -->

<div class="page-header-actions" style="margin-top: 40px;">
    <div>
        <h2 class="section-title">Mine egne leverandorer</h2>
        <p class="section-subtitle"><?= count($manualVendors) ?> leverandor<?= count($manualVendors) !== 1 ? 'er' : '' ?></p>
    </div>
    <button type="button" class="btn btn-primary" onclick="showManualAddModal()">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:16px;height:16px;">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        Tilfoj leverandor
    </button>
</div>

<?php if (empty($manualVendors)): ?>
<div class="card">
    <div class="empty-state">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
        </svg>
        <h3>Ingen manuelle leverandorer endnu</h3>
        <p>Tilfoj leverandorer du har booket udenfor markedspladsen.</p>
        <button type="button" class="btn btn-primary" style="margin-top: 16px;" onclick="showManualAddModal()">Tilfoj leverandor</button>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <thead>
                <tr style="border-bottom: 2px solid var(--cream-dark);">
                    <th style="text-align: left; padding: 12px 8px; font-weight: 600; color: var(--charcoal-light);">Virksomhed</th>
                    <th style="text-align: left; padding: 12px 8px; font-weight: 600; color: var(--charcoal-light);">Kategori</th>
                    <th style="text-align: left; padding: 12px 8px; font-weight: 600; color: var(--charcoal-light);">Kontakt</th>
                    <th style="text-align: right; padding: 12px 8px; font-weight: 600; color: var(--charcoal-light);">Pris</th>
                    <th style="text-align: center; padding: 12px 8px; font-weight: 600; color: var(--charcoal-light);">Betalt</th>
                    <th style="text-align: right; padding: 12px 8px; font-weight: 600; color: var(--charcoal-light);">Handlinger</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($manualVendors as $mv): ?>
                <tr style="border-bottom: 1px solid var(--cream-dark);">
                    <td style="padding: 12px 8px; font-weight: 500;">
                        <?= htmlspecialchars($mv['company_name']) ?>
                        <?php if (!empty($mv['notes'])): ?>
                            <div style="font-size: 12px; color: var(--charcoal-light); margin-top: 2px;"><?= htmlspecialchars(mb_strimwidth($mv['notes'], 0, 60, '...')) ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 12px 8px; color: var(--charcoal-light);">
                        <?= htmlspecialchars($mv['category'] ?: '-') ?>
                    </td>
                    <td style="padding: 12px 8px;">
                        <?php if (!empty($mv['contact_name'])): ?>
                            <div style="font-weight: 500;"><?= htmlspecialchars($mv['contact_name']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($mv['email'])): ?>
                            <div style="font-size: 12px; color: var(--charcoal-light);"><?= htmlspecialchars($mv['email']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($mv['phone'])): ?>
                            <div style="font-size: 12px; color: var(--charcoal-light);"><?= htmlspecialchars($mv['phone']) ?></div>
                        <?php endif; ?>
                        <?php if (empty($mv['contact_name']) && empty($mv['email']) && empty($mv['phone'])): ?>
                            <span style="color: var(--charcoal-light);">-</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 12px 8px; text-align: right;">
                        <?php if ($mv['agreed_price']): ?>
                            <?= number_format((float)$mv['agreed_price'], 0, ',', '.') ?> kr
                        <?php else: ?>
                            <span style="color: var(--charcoal-light);">-</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 12px 8px; text-align: center;">
                        <form method="POST" style="display: inline;">
                            <?= accountCsrfField() ?>
                            <input type="hidden" name="action" value="toggle_paid">
                            <input type="hidden" name="id" value="<?= $mv['id'] ?>">
                            <button type="submit" style="background: none; border: 2px solid <?= $mv['is_paid'] ? '#10b981' : 'var(--cream-dark)' ?>; width: 28px; height: 28px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 14px; color: white; <?= $mv['is_paid'] ? 'background: #10b981;' : '' ?> transition: all 0.2s;" title="<?= $mv['is_paid'] ? 'Marker som ubetalt' : 'Marker som betalt' ?>">
                                <?= $mv['is_paid'] ? '&#10003;' : '' ?>
                            </button>
                        </form>
                    </td>
                    <td style="padding: 12px 8px; text-align: right; white-space: nowrap;">
                        <button type="button" class="btn btn-sm btn-secondary" onclick='editManualVendor(<?= json_encode($mv) ?>)'>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:14px;height:14px;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                        </button>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Slet denne leverandor?');">
                            <?= accountCsrfField() ?>
                            <input type="hidden" name="action" value="delete_manual">
                            <input type="hidden" name="id" value="<?= $mv['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-secondary danger-text" style="border-color: var(--error);">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:14px;height:14px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>


<!-- ============================================================
     Budget Summary
     ============================================================ -->

<div class="card" style="margin-top: 40px;">
    <div class="card-header">
        <h3 class="card-title">Leverandoroversigt</h3>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 24px; text-align: center;">
        <div>
            <div style="font-family: var(--font-display); font-size: 28px; font-weight: 500; color: var(--charcoal);">
                <?= number_format($grandTotal, 0, ',', '.') ?> kr
            </div>
            <div style="font-size: 13px; color: var(--charcoal-light); margin-top: 4px;">Samlet leverandorudgift</div>
        </div>
        <div>
            <div style="font-family: var(--font-display); font-size: 28px; font-weight: 500; color: var(--sage-dark);">
                <?= number_format($marketplaceDeposit, 0, ',', '.') ?> kr
            </div>
            <div style="font-size: 13px; color: var(--charcoal-light); margin-top: 4px;">Depositum betalt</div>
        </div>
        <div>
            <div style="font-family: var(--font-display); font-size: 28px; font-weight: 500; color: #10b981;">
                <?= number_format($manualPaid, 0, ',', '.') ?> kr
            </div>
            <div style="font-size: 13px; color: var(--charcoal-light); margin-top: 4px;">Manuelle betalt</div>
        </div>
        <div>
            <div style="font-family: var(--font-display); font-size: 28px; font-weight: 500; color: var(--warning);">
                <?= number_format($grandTotal - $totalPaid, 0, ',', '.') ?> kr
            </div>
            <div style="font-size: 13px; color: var(--charcoal-light); margin-top: 4px;">Udestaaende</div>
        </div>
    </div>
    <?php if ($grandTotal > 0): ?>
        <?php $paidPercent = round($totalPaid / $grandTotal * 100); ?>
        <div style="margin-top: 24px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                <span style="font-size: 13px; color: var(--charcoal-light);">Betalt</span>
                <span style="font-size: 13px; color: var(--charcoal-light);"><?= $paidPercent ?>%</span>
            </div>
            <div style="height: 8px; background: var(--cream-dark); border-radius: 4px; overflow: hidden;">
                <div style="height: 100%; background: #10b981; width: <?= min(100, $paidPercent) ?>%; border-radius: 4px; transition: width 0.3s;"></div>
            </div>
        </div>
    <?php endif; ?>
</div>


<!-- ============================================================
     Add Manual Vendor Modal
     ============================================================ -->

<div class="modal-overlay" id="manualAddModal" style="display: none;">
    <div class="modal" style="max-width: 520px;">
        <div class="modal-header">
            <h3>Tilfoj leverandor</h3>
            <button type="button" class="modal-close" onclick="hideManualAddModal()">&times;</button>
        </div>
        <form method="POST">
            <?= accountCsrfField() ?>
            <input type="hidden" name="action" value="add_manual">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Virksomhedsnavn *</label>
                        <input type="text" name="company_name" class="form-input" required placeholder="F.eks. Jensens Catering">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Kategori</label>
                        <input type="text" name="category" class="form-input" placeholder="F.eks. Catering, DJ, Fotograf">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Kontaktperson</label>
                        <input type="text" name="contact_name" class="form-input" placeholder="Navn">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Aftalt pris (kr)</label>
                        <input type="number" name="agreed_price" class="form-input" step="0.01" min="0" placeholder="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-input" placeholder="email@eksempel.dk">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Telefon</label>
                        <input type="tel" name="phone" class="form-input" placeholder="+45 12 34 56 78">
                    </div>
                    <div class="form-group full-width">
                        <label class="form-label">Noter</label>
                        <textarea name="notes" class="form-input" rows="3" placeholder="Eventuelle noter om aftalen..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideManualAddModal()">Annuller</button>
                <button type="submit" class="btn btn-primary">Tilfoj</button>
            </div>
        </form>
    </div>
</div>


<!-- ============================================================
     Edit Manual Vendor Modal
     ============================================================ -->

<div class="modal-overlay" id="manualEditModal" style="display: none;">
    <div class="modal" style="max-width: 520px;">
        <div class="modal-header">
            <h3>Rediger leverandor</h3>
            <button type="button" class="modal-close" onclick="hideManualEditModal()">&times;</button>
        </div>
        <form method="POST">
            <?= accountCsrfField() ?>
            <input type="hidden" name="action" value="edit_manual">
            <input type="hidden" name="id" id="edit_mv_id">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Virksomhedsnavn *</label>
                        <input type="text" name="company_name" id="edit_mv_company" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Kategori</label>
                        <input type="text" name="category" id="edit_mv_category" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Kontaktperson</label>
                        <input type="text" name="contact_name" id="edit_mv_contact" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Aftalt pris (kr)</label>
                        <input type="number" name="agreed_price" id="edit_mv_price" class="form-input" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="edit_mv_email" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Telefon</label>
                        <input type="tel" name="phone" id="edit_mv_phone" class="form-input">
                    </div>
                    <div class="form-group full-width">
                        <label class="form-label">Noter</label>
                        <textarea name="notes" id="edit_mv_notes" class="form-input" rows="3"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideManualEditModal()">Annuller</button>
                <button type="submit" class="btn btn-primary">Gem</button>
            </div>
        </form>
    </div>
</div>


<script>
// ── Manual Vendor Add Modal ──
function showManualAddModal() {
    document.getElementById('manualAddModal').style.display = 'flex';
}
function hideManualAddModal() {
    document.getElementById('manualAddModal').style.display = 'none';
}

// ── Manual Vendor Edit Modal ──
function showManualEditModal() {
    document.getElementById('manualEditModal').style.display = 'flex';
}
function hideManualEditModal() {
    document.getElementById('manualEditModal').style.display = 'none';
}

function editManualVendor(vendor) {
    document.getElementById('edit_mv_id').value = vendor.id;
    document.getElementById('edit_mv_company').value = vendor.company_name || '';
    document.getElementById('edit_mv_category').value = vendor.category || '';
    document.getElementById('edit_mv_contact').value = vendor.contact_name || '';
    document.getElementById('edit_mv_price').value = vendor.agreed_price || '';
    document.getElementById('edit_mv_email').value = vendor.email || '';
    document.getElementById('edit_mv_phone').value = vendor.phone || '';
    document.getElementById('edit_mv_notes').value = vendor.notes || '';
    showManualEditModal();
}

// Close modals when clicking overlay
document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
});
</script>

<?php
/**
 * Platform Admin - Vendor Detail View
 * Shows full vendor profile, booking history, reviews, earnings, and admin controls.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin-platform-header.php';
require_once __DIR__ . '/../subcontractor/includes/commission-service.php';

$db = getDB();
$vendorId = (int)($_GET['id'] ?? 0);

if (!$vendorId) {
    redirect(BASE_PATH . '/admin-platform/vendors.php');
}

// Get vendor
$stmt = $db->prepare("SELECT * FROM vendors WHERE id = ?");
$stmt->execute([$vendorId]);
$vendor = $stmt->fetch();

if (!$vendor) {
    setFlash('error', 'Leverandor ikke fundet');
    redirect(BASE_PATH . '/admin-platform/vendors.php');
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        switch ($action) {
            case 'update_status':
                $newStatus = $_POST['status'] ?? '';
                $allowed = ['pending', 'approved', 'rejected', 'suspended'];
                if (in_array($newStatus, $allowed)) {
                    $extra = '';
                    $extraParams = [];
                    if ($newStatus === 'approved') {
                        $extra = ', approved_at = NOW(), approved_by = ?, rejection_reason = NULL';
                        $extraParams[] = $adminId;
                    }
                    if ($newStatus === 'rejected') {
                        $reason = trim($_POST['rejection_reason'] ?? '');
                        $extra = ', rejection_reason = ?';
                        $extraParams[] = $reason ?: null;
                    }
                    $stmt = $db->prepare("UPDATE vendors SET status = ?{$extra} WHERE id = ?");
                    $stmt->execute(array_merge([$newStatus], $extraParams, [$vendorId]));
                    setFlash('success', 'Status opdateret');
                }
                break;

            case 'save_notes':
                $notes = trim($_POST['admin_notes'] ?? '');
                // Store admin notes - we use a simple approach with a dedicated column or
                // a separate table. For now, we'll check if the column exists.
                try {
                    $stmt = $db->prepare("UPDATE vendors SET admin_notes = ? WHERE id = ?");
                    $stmt->execute([$notes, $vendorId]);
                    setFlash('success', 'Noter gemt');
                } catch (Exception $e) {
                    // Column might not exist yet - silently handle
                    setFlash('error', 'Kunne ikke gemme noter. Kolonnen admin_notes mangler muligvis.');
                }
                break;
        }
        redirect(BASE_PATH . '/admin-platform/vendor-detail.php?id=' . $vendorId);
    }
}

// Refresh vendor data
$stmt = $db->prepare("SELECT * FROM vendors WHERE id = ?");
$stmt->execute([$vendorId]);
$vendor = $stmt->fetch();

// Get vendor categories
$stmt = $db->prepare("
    SELECT vc.name, vc.icon, vc.slug
    FROM vendor_category_links vcl
    JOIN vendor_categories vc ON vcl.category_id = vc.id
    WHERE vcl.vendor_id = ?
    ORDER BY vc.sort_order
");
$stmt->execute([$vendorId]);
$vendorCategories = $stmt->fetchAll();

// Get vendor services
$stmt = $db->prepare("
    SELECT * FROM vendor_services
    WHERE vendor_id = ?
    ORDER BY sort_order, created_at DESC
");
$stmt->execute([$vendorId]);
$services = $stmt->fetchAll();

// Get booking history
$stmt = $db->prepare("
    SELECT b.*, e.name as event_name, a.name as organizer_name, a.email as organizer_email,
           vs.title as service_title
    FROM bookings b
    JOIN events e ON b.event_id = e.id
    JOIN accounts a ON b.account_id = a.id
    LEFT JOIN vendor_services vs ON b.vendor_service_id = vs.id
    WHERE b.vendor_id = ?
    ORDER BY b.created_at DESC
    LIMIT 50
");
$stmt->execute([$vendorId]);
$bookings = $stmt->fetchAll();

// Get reviews
$stmt = $db->prepare("
    SELECT vr.*, a.name as reviewer_name
    FROM vendor_reviews vr
    JOIN accounts a ON vr.account_id = a.id
    WHERE vr.vendor_id = ?
    ORDER BY vr.created_at DESC
    LIMIT 20
");
$stmt->execute([$vendorId]);
$reviews = $stmt->fetchAll();

// Get earnings summary
$earnings = getVendorEarnings($vendorId);

// Status maps
$statusBadgeMap = ['approved' => 'badge-success', 'pending' => 'badge-warning', 'rejected' => 'badge-error', 'suspended' => 'badge-error'];
$statusTextMap = ['approved' => 'Godkendt', 'pending' => 'Afventer', 'rejected' => 'Afvist', 'suspended' => 'Suspenderet'];

$bookingBadgeMap = [
    'requested' => 'badge-info', 'quoted' => 'badge-info', 'accepted' => 'badge-warning',
    'deposited' => 'badge-warning', 'confirmed' => 'badge-success', 'completed' => 'badge-success',
    'reviewed' => 'badge-success', 'cancelled' => 'badge-error', 'refunded' => 'badge-error',
    'disputed' => 'badge-error'
];
$bookingTextMap = [
    'requested' => 'Anmodet', 'quoted' => 'Tilbudt', 'accepted' => 'Accepteret',
    'deposited' => 'Depositum', 'confirmed' => 'Bekraeftet', 'completed' => 'Afsluttet',
    'reviewed' => 'Anmeldt', 'cancelled' => 'Annulleret', 'refunded' => 'Refunderet',
    'disputed' => 'Tvist'
];
?>

<header class="platform-header">
    <h1 class="page-title">
        <a href="<?= BASE_PATH ?>/admin-platform/vendors.php" style="color: var(--color-text-muted); text-decoration: none;">Leverandorer</a>
        &rsaquo; <?= escape($vendor['company_name']) ?>
    </h1>
    <div class="header-actions">
        <span class="badge <?= $statusBadgeMap[$vendor['status']] ?? 'badge-info' ?>" style="font-size: 0.9rem; padding: 0.5rem 1rem;">
            <?= $statusTextMap[$vendor['status']] ?? $vendor['status'] ?>
        </span>
    </div>
</header>

<div class="platform-content">
    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
            <?= escape($flash['message']) ?>
        </div>
    <?php endif; ?>

    <!-- Earnings Stats -->
    <div class="stats-grid mb-lg">
        <div class="stat-card">
            <div class="stat-label">Bookinger (afsluttede)</div>
            <div class="stat-value"><?= number_format($earnings['booking_count']) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total omsaetning</div>
            <div class="stat-value"><?= number_format($earnings['total_earned'], 2, ',', '.') ?> kr.</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Kommission (platform)</div>
            <div class="stat-value"><?= number_format($earnings['total_commission'], 2, ',', '.') ?> kr.</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Udbetalt til leverandor</div>
            <div class="stat-value text-success"><?= number_format($earnings['total_paid_out'], 2, ',', '.') ?> kr.</div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-lg);">
        <!-- Vendor Profile -->
        <div class="card">
            <h2 class="card-title">Virksomhedsinformation</h2>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-md);">
                <div>
                    <div class="text-sm text-muted">Virksomhedsnavn</div>
                    <div class="font-medium"><?= escape($vendor['company_name']) ?></div>
                </div>
                <div>
                    <div class="text-sm text-muted">Kontaktperson</div>
                    <div class="font-medium"><?= escape($vendor['contact_name'] ?? '-') ?></div>
                </div>
                <div>
                    <div class="text-sm text-muted">Email</div>
                    <div class="font-medium"><?= escape($vendor['email']) ?></div>
                </div>
                <div>
                    <div class="text-sm text-muted">Telefon</div>
                    <div class="font-medium"><?= escape($vendor['phone'] ?? '-') ?></div>
                </div>
                <div>
                    <div class="text-sm text-muted">Website</div>
                    <div class="font-medium">
                        <?php if ($vendor['website']): ?>
                            <a href="<?= escape($vendor['website']) ?>" target="_blank" style="color: var(--color-primary);">
                                <?= escape($vendor['website']) ?>
                            </a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <div class="text-sm text-muted">By</div>
                    <div class="font-medium"><?= escape($vendor['city'] ?? '-') ?></div>
                </div>
                <div>
                    <div class="text-sm text-muted">Adresse</div>
                    <div class="font-medium"><?= escape($vendor['address'] ?? '-') ?></div>
                </div>
                <div>
                    <div class="text-sm text-muted">Postnummer</div>
                    <div class="font-medium"><?= escape($vendor['postal_code'] ?? '-') ?></div>
                </div>
                <div>
                    <div class="text-sm text-muted">Landsdaekkende</div>
                    <div class="font-medium"><?= $vendor['nationwide'] ? 'Ja' : 'Nej' ?></div>
                </div>
                <div>
                    <div class="text-sm text-muted">Rating</div>
                    <div class="font-medium">
                        <?php if ($vendor['review_count'] > 0): ?>
                            <?= number_format($vendor['avg_rating'], 1, ',', '.') ?> / 5 (<?= $vendor['review_count'] ?> anm.)
                        <?php else: ?>
                            Ingen anmeldelser
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <div class="text-sm text-muted">Visninger</div>
                    <div class="font-medium"><?= number_format($vendor['view_count']) ?></div>
                </div>
                <div>
                    <div class="text-sm text-muted">Oprettet</div>
                    <div class="font-medium"><?= date('d/m/Y H:i', strtotime($vendor['created_at'])) ?></div>
                </div>
                <div>
                    <div class="text-sm text-muted">Sidst logget ind</div>
                    <div class="font-medium">
                        <?= $vendor['last_login_at'] ? date('d/m/Y H:i', strtotime($vendor['last_login_at'])) : 'Aldrig' ?>
                    </div>
                </div>
                <?php if ($vendor['approved_at']): ?>
                <div>
                    <div class="text-sm text-muted">Godkendt</div>
                    <div class="font-medium"><?= date('d/m/Y H:i', strtotime($vendor['approved_at'])) ?></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Categories -->
            <?php if (!empty($vendorCategories)): ?>
                <hr style="margin: var(--space-lg) 0; border: none; border-top: 1px solid var(--color-border);">
                <div class="text-sm text-muted mb-md">Kategorier</div>
                <div class="flex gap-sm" style="flex-wrap: wrap;">
                    <?php foreach ($vendorCategories as $cat): ?>
                        <span class="badge badge-info"><?= $cat['icon'] ?> <?= escape($cat['name']) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Description -->
            <?php if ($vendor['description']): ?>
                <hr style="margin: var(--space-lg) 0; border: none; border-top: 1px solid var(--color-border);">
                <div class="text-sm text-muted mb-md">Beskrivelse</div>
                <div class="text-sm"><?= nl2br(escape($vendor['description'])) ?></div>
            <?php endif; ?>
        </div>

        <!-- Admin Controls & Stripe -->
        <div>
            <!-- Status Control -->
            <div class="card mb-lg">
                <h2 class="card-title">Administrer status</h2>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_status">

                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-input form-select">
                            <option value="pending" <?= $vendor['status'] === 'pending' ? 'selected' : '' ?>>Afventer</option>
                            <option value="approved" <?= $vendor['status'] === 'approved' ? 'selected' : '' ?>>Godkendt</option>
                            <option value="rejected" <?= $vendor['status'] === 'rejected' ? 'selected' : '' ?>>Afvist</option>
                            <option value="suspended" <?= $vendor['status'] === 'suspended' ? 'selected' : '' ?>>Suspenderet</option>
                        </select>
                    </div>

                    <div class="form-group" id="rejectionReasonGroup" style="<?= $vendor['status'] === 'rejected' ? '' : 'display: none;' ?>">
                        <label class="form-label">Afvisningsgrund</label>
                        <textarea name="rejection_reason" class="form-input" rows="2"
                                  placeholder="Beskriv arsagen..."><?= escape($vendor['rejection_reason'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">Gem status</button>
                </form>
            </div>

            <!-- Stripe Connect Status -->
            <div class="card mb-lg">
                <h2 class="card-title">Stripe Connect</h2>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-md);">
                    <div>
                        <div class="text-sm text-muted">Account ID</div>
                        <div class="font-medium">
                            <?php if ($vendor['stripe_account_id']): ?>
                                <code style="font-size: var(--text-xs);"><?= escape($vendor['stripe_account_id']) ?></code>
                            <?php else: ?>
                                <span class="text-muted">Ikke oprettet</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <div class="text-sm text-muted">Onboarding</div>
                        <div class="font-medium">
                            <?php if ($vendor['stripe_onboarding_complete']): ?>
                                <span class="badge badge-success">Komplet</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Ufuldstaendig</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Admin Notes -->
            <div class="card">
                <h2 class="card-title">Admin noter</h2>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="save_notes">
                    <div class="form-group">
                        <textarea name="admin_notes" class="form-input" rows="4"
                                  placeholder="Interne noter om denne leverandor..."><?= escape($vendor['admin_notes'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-secondary">Gem noter</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Services -->
    <div class="card mt-md">
        <h2 class="card-title">Ydelser (<?= count($services) ?>)</h2>

        <?php if (empty($services)): ?>
            <div class="empty-state">
                <p>Ingen ydelser oprettet</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Titel</th>
                            <th>Pris fra</th>
                            <th>Pris til</th>
                            <th>Prisenhed</th>
                            <th>Varighed</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $service): ?>
                            <tr>
                                <td>
                                    <div class="font-medium"><?= escape($service['title']) ?></div>
                                    <?php if ($service['description']): ?>
                                        <div class="text-xs text-muted"><?= escape(mb_substr($service['description'], 0, 80)) ?>...</div>
                                    <?php endif; ?>
                                </td>
                                <td><?= number_format($service['price_from'], 2, ',', '.') ?> kr.</td>
                                <td><?= $service['price_to'] ? number_format($service['price_to'], 2, ',', '.') . ' kr.' : '-' ?></td>
                                <td>
                                    <?php
                                    $unitMap = ['fixed' => 'Fast pris', 'per_person' => 'Pr. person', 'per_hour' => 'Pr. time'];
                                    echo $unitMap[$service['price_unit']] ?? $service['price_unit'];
                                    ?>
                                </td>
                                <td><?= $service['duration_hours'] ? $service['duration_hours'] . ' timer' : '-' ?></td>
                                <td>
                                    <?php if ($service['is_active']): ?>
                                        <span class="badge badge-success">Aktiv</span>
                                    <?php else: ?>
                                        <span class="badge badge-error">Inaktiv</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Booking History -->
    <div class="card mt-md">
        <h2 class="card-title">Bookinghistorik (<?= count($bookings) ?>)</h2>

        <?php if (empty($bookings)): ?>
            <div class="empty-state">
                <p>Ingen bookinger</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Dato</th>
                            <th>Event</th>
                            <th>Arrangor</th>
                            <th>Ydelse</th>
                            <th>Pris</th>
                            <th>Status</th>
                            <th>Oprettet</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td class="text-sm"><?= date('d/m/Y', strtotime($booking['event_date'])) ?></td>
                                <td class="text-sm font-medium"><?= escape($booking['event_name']) ?></td>
                                <td class="text-sm">
                                    <div><?= escape($booking['organizer_name']) ?></div>
                                    <div class="text-xs text-muted"><?= escape($booking['organizer_email']) ?></div>
                                </td>
                                <td class="text-sm"><?= escape($booking['service_title'] ?? '-') ?></td>
                                <td class="text-sm font-medium">
                                    <?= $booking['quoted_price'] ? number_format($booking['quoted_price'], 2, ',', '.') . ' kr.' : '-' ?>
                                </td>
                                <td>
                                    <span class="badge <?= $bookingBadgeMap[$booking['status']] ?? 'badge-info' ?>">
                                        <?= $bookingTextMap[$booking['status']] ?? $booking['status'] ?>
                                    </span>
                                </td>
                                <td class="text-sm text-muted"><?= date('d/m/Y', strtotime($booking['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Reviews -->
    <div class="card mt-md">
        <h2 class="card-title">Anmeldelser (<?= count($reviews) ?>)</h2>

        <?php if (empty($reviews)): ?>
            <div class="empty-state">
                <p>Ingen anmeldelser</p>
            </div>
        <?php else: ?>
            <?php foreach ($reviews as $review): ?>
                <div style="border-bottom: 1px solid var(--color-border); padding: var(--space-md) 0; <?= $review === end($reviews) ? 'border-bottom: none;' : '' ?>">
                    <div class="flex justify-between items-center mb-md">
                        <div>
                            <span class="font-medium"><?= escape($review['reviewer_name']) ?></span>
                            <span class="text-muted text-sm" style="margin-left: var(--space-sm);">
                                <?= date('d/m/Y', strtotime($review['created_at'])) ?>
                            </span>
                        </div>
                        <div>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span style="color: <?= $i <= $review['rating'] ? '#f59e0b' : '#e2e8f0' ?>;">&#9733;</span>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <?php if ($review['title']): ?>
                        <div class="font-medium text-sm mb-md"><?= escape($review['title']) ?></div>
                    <?php endif; ?>
                    <?php if ($review['review_text']): ?>
                        <div class="text-sm"><?= nl2br(escape($review['review_text'])) ?></div>
                    <?php endif; ?>
                    <?php if ($review['vendor_response']): ?>
                        <div style="margin-top: var(--space-sm); padding: var(--space-sm); background: var(--color-bg-subtle); border-radius: var(--radius-md);">
                            <div class="text-xs text-muted">Svar fra leverandor:</div>
                            <div class="text-sm"><?= nl2br(escape($review['vendor_response'])) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if (!$review['is_visible']): ?>
                        <span class="badge badge-error mt-md">Skjult</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
// Show/hide rejection reason field based on status dropdown
document.querySelector('select[name="status"]').addEventListener('change', function() {
    var group = document.getElementById('rejectionReasonGroup');
    group.style.display = this.value === 'rejected' ? '' : 'none';
});
</script>

<?php require_once __DIR__ . '/../includes/admin-platform-footer.php'; ?>

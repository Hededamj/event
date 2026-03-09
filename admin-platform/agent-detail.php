<?php
/**
 * Platform Admin - Agent Detail View
 * Shows agent profile, referrals, provision breakdown, and admin controls.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin-platform-header.php';
require_once __DIR__ . '/../subcontractor/includes/referral-service.php';

$db = getDB();
$agentId = (int)($_GET['id'] ?? 0);

if (!$agentId) {
    redirect(BASE_PATH . '/admin-platform/agents.php');
}

// Get agent with account info
$stmt = $db->prepare("
    SELECT ra.*, a.name AS account_name, a.email AS account_email
    FROM referral_agents ra
    JOIN accounts a ON ra.account_id = a.id
    WHERE ra.id = ?
");
$stmt->execute([$agentId]);
$agent = $stmt->fetch();

if (!$agent) {
    setFlash('error', 'Agent ikke fundet');
    redirect(BASE_PATH . '/admin-platform/agents.php');
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        switch ($action) {
            case 'update_rate':
                $newRate = trim($_POST['provision_rate'] ?? '');
                $rateValue = $newRate !== '' ? (float)$newRate / 100 : null;
                $stmt = $db->prepare("UPDATE referral_agents SET provision_rate = ? WHERE id = ?");
                $stmt->execute([$rateValue, $agentId]);
                setFlash('success', 'Provisionssats opdateret');
                break;

            case 'mark_paid':
                $linkId = (int)($_POST['link_id'] ?? 0);
                if ($linkId) {
                    $stmt = $db->prepare("UPDATE referral_links SET last_paid_at = CURDATE() WHERE id = ? AND agent_id = ?");
                    $stmt->execute([$linkId, $agentId]);
                    setFlash('success', 'Markeret som udbetalt');
                }
                break;

            case 'deactivate':
                deactivateAgent($agentId);
                setFlash('success', 'Agent deaktiveret');
                break;

            case 'activate':
                activateAgent($agentId);
                setFlash('success', 'Agent aktiveret');
                break;

            case 'update_notes':
                $notes = trim($_POST['notes'] ?? '');
                $stmt = $db->prepare("UPDATE referral_agents SET notes = ? WHERE id = ?");
                $stmt->execute([$notes ?: null, $agentId]);
                setFlash('success', 'Noter gemt');
                break;
        }
        redirect(BASE_PATH . '/admin-platform/agent-detail.php?id=' . $agentId);
    }
}

// Refresh agent data
$stmt = $db->prepare("
    SELECT ra.*, a.name AS account_name, a.email AS account_email
    FROM referral_agents ra
    JOIN accounts a ON ra.account_id = a.id
    WHERE ra.id = ?
");
$stmt->execute([$agentId]);
$agent = $stmt->fetch();

// Get referrals with vendor info
$stmt = $db->prepare("
    SELECT rl.*, v.company_name, v.email AS vendor_email, v.status AS vendor_status,
           v.booking_count AS vendor_booking_count
    FROM referral_links rl
    JOIN vendors v ON rl.vendor_id = v.id
    WHERE rl.agent_id = ?
    ORDER BY rl.created_at DESC
");
$stmt->execute([$agentId]);
$referrals = $stmt->fetchAll();

// Calculate stats
$totalReferrals = count($referrals);
$activeVendors = 0;
$totalEarned = 0;
$unpaidAmount = 0;

foreach ($referrals as $ref) {
    if (($ref['vendor_booking_count'] ?? 0) > 0) $activeVendors++;
    $totalEarned += (float)$ref['total_earned'];
    // Unpaid = earned since last_paid_at (or all if never paid)
    if (empty($ref['last_paid_at'])) {
        $unpaidAmount += (float)$ref['total_earned'];
    }
}

// Get booking provisions for this agent's referrals
$stmt = $db->prepare("
    SELECT b.id AS booking_id, b.created_at AS booking_date, b.quoted_price, b.depositum_amount,
           b.agent_provision_amount, b.status AS booking_status,
           v.company_name, rl.id AS link_id, rl.provision_rate
    FROM bookings b
    JOIN referral_links rl ON b.referral_link_id = rl.id
    JOIN vendors v ON b.vendor_id = v.id
    WHERE rl.agent_id = ?
      AND b.agent_provision_amount IS NOT NULL
      AND b.agent_provision_amount > 0
    ORDER BY b.created_at DESC
    LIMIT 100
");
$stmt->execute([$agentId]);
$provisionBookings = $stmt->fetchAll();

// Display provision rate
$displayRate = $agent['provision_rate'] !== null
    ? number_format((float)$agent['provision_rate'] * 100, 2, ',', '.') . '%'
    : 'Standard: 1%';
?>

<header class="platform-header">
    <h1 class="page-title">
        <a href="<?= BASE_PATH ?>/admin-platform/agents.php" style="color: var(--text-secondary); text-decoration: none;">Agenter</a>
        &rsaquo; <?= escape($agent['account_name']) ?>
    </h1>
    <div class="header-actions">
        <?php if ($agent['is_active']): ?>
            <span class="badge badge-success" style="font-size: 0.9rem; padding: 0.5rem 1rem;">Aktiv</span>
        <?php else: ?>
            <span class="badge badge-error" style="font-size: 0.9rem; padding: 0.5rem 1rem;">Inaktiv</span>
        <?php endif; ?>
    </div>
</header>

<div class="platform-content">
    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
            <?= escape($flash['message']) ?>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid mb-lg">
        <div class="stat-card">
            <div class="stat-label">Total referrals</div>
            <div class="stat-value"><?= number_format($totalReferrals) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Aktive leverandoerer</div>
            <div class="stat-value"><?= number_format($activeVendors) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total optjent</div>
            <div class="stat-value"><?= number_format($totalEarned, 2, ',', '.') ?> kr.</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Ikke udbetalt</div>
            <div class="stat-value" style="color: var(--warning);"><?= number_format($unpaidAmount, 2, ',', '.') ?> kr.</div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-lg);">
        <!-- Agent Info -->
        <div class="card">
            <h2 class="card-title">Agentinformation</h2>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-md);">
                <div>
                    <div class="text-sm text-muted">Navn</div>
                    <div class="font-medium"><?= escape($agent['account_name']) ?></div>
                </div>
                <div>
                    <div class="text-sm text-muted">Email</div>
                    <div class="font-medium"><?= escape($agent['account_email']) ?></div>
                </div>
                <div>
                    <div class="text-sm text-muted">Referral-kode</div>
                    <div class="font-medium">
                        <code style="font-size: 13px; background: var(--surface); padding: 2px 8px; border-radius: 4px;">
                            <?= escape($agent['referral_code']) ?>
                        </code>
                    </div>
                </div>
                <div>
                    <div class="text-sm text-muted">Oprettet</div>
                    <div class="font-medium"><?= date('d/m/Y H:i', strtotime($agent['created_at'])) ?></div>
                </div>
            </div>
        </div>

        <!-- Admin Controls -->
        <div>
            <!-- Provision Rate -->
            <div class="card mb-lg">
                <h2 class="card-title">Provisionssats</h2>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_rate">

                    <div class="form-group">
                        <label class="form-label">Sats (%)</label>
                        <input type="number" name="provision_rate" class="form-input" step="0.01" min="0" max="100"
                               placeholder="Standard: 1%"
                               value="<?= $agent['provision_rate'] !== null ? number_format((float)$agent['provision_rate'] * 100, 2, '.', '') : '' ?>">
                        <p class="text-xs text-muted" style="margin-top: 4px;">
                            Nuvaerende: <?= $displayRate ?>. Lad feltet staa tomt for standardsats.
                        </p>
                    </div>

                    <button type="submit" class="btn btn-primary">Gem sats</button>
                </form>
            </div>

            <!-- Status Toggle -->
            <div class="card mb-lg">
                <h2 class="card-title">Status</h2>
                <form method="POST">
                    <?= csrfField() ?>
                    <?php if ($agent['is_active']): ?>
                        <p class="text-sm" style="margin-bottom: var(--space-md);">
                            Agenten er <strong style="color: var(--success);">aktiv</strong> og kan referere nye leverandoerer.
                        </p>
                        <button type="submit" name="action" value="deactivate" class="btn btn-danger"
                                onclick="return confirm('Deaktiver denne agent?')">
                            Deaktiver agent
                        </button>
                    <?php else: ?>
                        <p class="text-sm" style="margin-bottom: var(--space-md);">
                            Agenten er <strong style="color: var(--error);">inaktiv</strong> og kan ikke referere nye leverandoerer.
                        </p>
                        <button type="submit" name="action" value="activate" class="btn btn-success">
                            Aktiver agent
                        </button>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Notes -->
            <div class="card">
                <h2 class="card-title">Noter</h2>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_notes">
                    <div class="form-group">
                        <textarea name="notes" class="form-input" rows="4"
                                  placeholder="Interne noter om denne agent..."><?= escape($agent['notes'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-secondary">Gem noter</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Referral List -->
    <div class="card mt-md">
        <h2 class="card-title">Henviste leverandoerer (<?= $totalReferrals ?>)</h2>

        <?php if (empty($referrals)): ?>
            <div class="empty-state">
                <p>Ingen henviste leverandoerer</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Leverandoer</th>
                            <th>Registreret</th>
                            <th>Status</th>
                            <th>Rate</th>
                            <th>Udloeber</th>
                            <th>Optjent</th>
                            <th>Udbetalt</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($referrals as $ref): ?>
                            <?php
                            $vendorStatus = $ref['vendor_status'] ?? 'pending';
                            $badgeMap = ['approved' => 'badge-success', 'pending' => 'badge-warning', 'rejected' => 'badge-error', 'suspended' => 'badge-error'];
                            $textMap = ['approved' => 'Godkendt', 'pending' => 'Afventer', 'rejected' => 'Afvist', 'suspended' => 'Suspenderet'];
                            ?>
                            <tr>
                                <td>
                                    <div class="font-medium"><?= escape($ref['company_name']) ?></div>
                                    <div class="text-xs text-muted"><?= escape($ref['vendor_email'] ?? '') ?></div>
                                </td>
                                <td class="text-sm text-muted">
                                    <?= date('d/m/Y', strtotime($ref['created_at'])) ?>
                                </td>
                                <td>
                                    <span class="badge <?= $badgeMap[$vendorStatus] ?? 'badge-info' ?>">
                                        <?= $textMap[$vendorStatus] ?? $vendorStatus ?>
                                    </span>
                                </td>
                                <td class="text-sm">
                                    <?= number_format((float)$ref['provision_rate'] * 100, 2, ',', '.') ?>%
                                </td>
                                <td class="text-sm text-muted">
                                    <?= date('d/m/Y', strtotime($ref['provision_until'])) ?>
                                </td>
                                <td class="text-sm font-medium">
                                    <?= number_format((float)$ref['total_earned'], 2, ',', '.') ?> kr.
                                </td>
                                <td class="text-sm text-muted">
                                    <?= $ref['last_paid_at'] ? date('d/m/Y', strtotime($ref['last_paid_at'])) : 'Aldrig' ?>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="mark_paid">
                                        <input type="hidden" name="link_id" value="<?= $ref['id'] ?>">
                                        <button type="submit" class="btn btn-success btn-sm"
                                                onclick="return confirm('Marker som udbetalt?')">
                                            Marker udbetalt
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Provision Detail (bookings) -->
    <div class="card mt-md">
        <h2 class="card-title">Provisionsdetaljer - bookinger (<?= count($provisionBookings) ?>)</h2>

        <?php if (empty($provisionBookings)): ?>
            <div class="empty-state">
                <p>Ingen bookinger med provision endnu</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Booking</th>
                            <th>Leverandoer</th>
                            <th>Dato</th>
                            <th>Pris</th>
                            <th>Depositum</th>
                            <th>Rate</th>
                            <th>Agent-provision</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
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
                        <?php foreach ($provisionBookings as $pb): ?>
                            <tr>
                                <td class="text-sm">#<?= (int)$pb['booking_id'] ?></td>
                                <td class="text-sm font-medium"><?= escape($pb['company_name']) ?></td>
                                <td class="text-sm text-muted"><?= date('d/m/Y', strtotime($pb['booking_date'])) ?></td>
                                <td class="text-sm">
                                    <?= $pb['quoted_price'] ? number_format((float)$pb['quoted_price'], 2, ',', '.') . ' kr.' : '-' ?>
                                </td>
                                <td class="text-sm">
                                    <?= $pb['depositum_amount'] ? number_format((float)$pb['depositum_amount'], 2, ',', '.') . ' kr.' : '-' ?>
                                </td>
                                <td class="text-sm">
                                    <?= number_format((float)$pb['provision_rate'] * 100, 2, ',', '.') ?>%
                                </td>
                                <td class="text-sm font-medium" style="color: var(--success);">
                                    <?= number_format((float)$pb['agent_provision_amount'], 2, ',', '.') ?> kr.
                                </td>
                                <td>
                                    <span class="badge <?= $bookingBadgeMap[$pb['booking_status']] ?? 'badge-info' ?>">
                                        <?= $bookingTextMap[$pb['booking_status']] ?? escape($pb['booking_status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin-platform-footer.php'; ?>

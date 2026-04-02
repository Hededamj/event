<?php
/**
 * Platform Admin - Account Detail
 */

require_once __DIR__ . '/../includes/admin-platform-header.php';

$db = getDB();
$accountId = (int)($_GET['id'] ?? 0);

if (!$accountId) {
    redirect(BASE_PATH . '/admin-platform/accounts.php');
}

// Get account
$stmt = $db->prepare("SELECT * FROM accounts WHERE id = ?");
$stmt->execute([$accountId]);
$account = $stmt->fetch();

if (!$account) {
    setFlash('error', 'Konto ikke fundet');
    redirect(BASE_PATH . '/admin-platform/accounts.php');
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        switch ($action) {
            case 'activate':
                $stmt = $db->prepare("UPDATE accounts SET is_active = 1 WHERE id = ?");
                $stmt->execute([$accountId]);
                setFlash('success', 'Konto aktiveret');
                break;

            case 'deactivate':
                $stmt = $db->prepare("UPDATE accounts SET is_active = 0 WHERE id = ?");
                $stmt->execute([$accountId]);
                setFlash('success', 'Konto deaktiveret');
                break;

            case 'update':
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');

                if ($name && $email) {
                    $stmt = $db->prepare("UPDATE accounts SET name = ?, email = ?, phone = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $phone, $accountId]);
                    setFlash('success', 'Konto opdateret');
                }
                break;

            case 'change_plan':
                $newPlanId = (int)($_POST['plan_id'] ?? 0);
                if ($newPlanId) {
                    // Check plan exists
                    $planCheck = $db->prepare("SELECT id, name FROM plans WHERE id = ?");
                    $planCheck->execute([$newPlanId]);
                    $newPlan = $planCheck->fetch();

                    if ($newPlan) {
                        // Check if account has an active subscription
                        $subCheck = $db->prepare("SELECT id FROM subscriptions WHERE account_id = ? AND status = 'active' LIMIT 1");
                        $subCheck->execute([$accountId]);
                        $existingSub = $subCheck->fetch();

                        if ($existingSub) {
                            $stmt = $db->prepare("UPDATE subscriptions SET plan_id = ?, updated_at = NOW() WHERE id = ?");
                            $stmt->execute([$newPlanId, $existingSub['id']]);
                        } else {
                            $stmt = $db->prepare("INSERT INTO subscriptions (account_id, plan_id, status, created_at, updated_at) VALUES (?, ?, 'active', NOW(), NOW())");
                            $stmt->execute([$accountId, $newPlanId]);
                        }
                        setFlash('success', 'Plan ændret til ' . $newPlan['name']);
                    }
                }
                break;
        }
        redirect(BASE_PATH . '/admin-platform/account-detail.php?id=' . $accountId);
    }
}

// Refresh account data
$stmt = $db->prepare("SELECT * FROM accounts WHERE id = ?");
$stmt->execute([$accountId]);
$account = $stmt->fetch();

// Get subscription
$stmt = $db->prepare("
    SELECT s.*, p.name as plan_name, p.slug as plan_slug, p.price_monthly, p.max_guests, p.max_events
    FROM subscriptions s
    JOIN plans p ON s.plan_id = p.id
    WHERE s.account_id = ?
    ORDER BY s.created_at DESC
    LIMIT 1
");
$stmt->execute([$accountId]);
$subscription = $stmt->fetch();

// Get events
$stmt = $db->prepare("
    SELECT e.*, et.name as event_type_name,
           (SELECT COUNT(*) FROM guests WHERE event_id = e.id) as guest_count
    FROM event_owners eo
    JOIN events e ON eo.event_id = e.id
    LEFT JOIN event_types et ON e.event_type_id = et.id
    WHERE eo.account_id = ?
    ORDER BY e.created_at DESC
");
$stmt->execute([$accountId]);
$events = $stmt->fetchAll();

// Get payment history
$stmt = $db->prepare("
    SELECT * FROM payment_history
    WHERE account_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$accountId]);
$payments = $stmt->fetchAll();

// Get all plans for dropdown
$allPlans = $db->query("SELECT id, name, slug, price_monthly FROM plans ORDER BY sort_order")->fetchAll();

// Get login history
$stmt = $db->prepare("
    SELECT * FROM account_sessions
    WHERE account_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute([$accountId]);
$sessions = $stmt->fetchAll();
?>

<header class="platform-header">
    <h1 class="page-title">
        <a href="<?= BASE_PATH ?>/admin-platform/accounts.php" style="color: var(--text-secondary); text-decoration: none;">Konti</a>
        &rsaquo; <?= escape($account['name']) ?>
    </h1>
    <div class="header-actions">
        <form method="POST" style="display: inline;">
            <?= csrfField() ?>
            <?php if ($account['is_active']): ?>
                <button type="submit" name="action" value="deactivate" class="btn btn-danger"
                        onclick="return confirm('Deaktiver denne konto?')">
                    Deaktiver konto
                </button>
            <?php else: ?>
                <button type="submit" name="action" value="activate" class="btn btn-success">
                    Aktiver konto
                </button>
            <?php endif; ?>
        </form>
    </div>
</header>

<div class="platform-content">
    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
            <?= escape($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-lg);">
        <!-- Account Info -->
        <div class="card">
            <h2 class="card-title">Kontoinformation</h2>

            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update">

                <div class="form-group">
                    <label class="form-label">Navn</label>
                    <input type="text" name="name" class="form-input" value="<?= escape($account['name']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input" value="<?= escape($account['email']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Telefon</label>
                    <input type="text" name="phone" class="form-input" value="<?= escape($account['phone'] ?? '') ?>">
                </div>

                <div class="flex gap-md">
                    <button type="submit" class="btn btn-primary">Gem ændringer</button>
                </div>
            </form>

            <hr style="margin: var(--space-lg) 0; border: none; border-top: 1px solid var(--border);">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-md);">
                <div>
                    <div class="text-sm text-muted">Status</div>
                    <div class="font-medium">
                        <?php if ($account['is_active']): ?>
                            <span class="badge badge-success">Aktiv</span>
                        <?php else: ?>
                            <span class="badge badge-error">Inaktiv</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <div class="text-sm text-muted">Email verificeret</div>
                    <div class="font-medium">
                        <?php if (!empty($account['email_verified_at'])): ?>
                            <span class="badge badge-success">Ja</span>
                        <?php else: ?>
                            <span class="badge badge-warning">Nej</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <div class="text-sm text-muted">Oprettet</div>
                    <div class="font-medium"><?= date('d/m/Y H:i', strtotime($account['created_at'])) ?></div>
                </div>
                <div>
                    <div class="text-sm text-muted">Sidst logget ind</div>
                    <div class="font-medium">
                        <?= $account['last_login_at'] ? date('d/m/Y H:i', strtotime($account['last_login_at'])) : 'Aldrig' ?>
                    </div>
                </div>
                <div>
                    <div class="text-sm text-muted">Antal logins</div>
                    <div class="font-medium"><?= $account['login_count'] ?? 0 ?></div>
                </div>
            </div>
        </div>

        <!-- Subscription -->
        <div class="card">
            <h2 class="card-title">Abonnement</h2>

            <?php if ($subscription): ?>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-md);">
                    <div>
                        <div class="text-sm text-muted">Plan</div>
                        <div class="font-medium"><?= escape($subscription['plan_name']) ?></div>
                    </div>
                    <div>
                        <div class="text-sm text-muted">Pris</div>
                        <div class="font-medium"><?= number_format($subscription['price_monthly'], 0, ',', '.') ?> kr/md</div>
                    </div>
                    <div>
                        <div class="text-sm text-muted">Status</div>
                        <div class="font-medium">
                            <?php
                            $subBadgeMap = ['active' => 'badge-success', 'cancelled' => 'badge-error', 'past_due' => 'badge-warning', 'trialing' => 'badge-info'];
                            $subTextMap = ['active' => 'Aktiv', 'cancelled' => 'Opsagt', 'past_due' => 'Forfaldent', 'trialing' => 'Prøveperiode', 'paused' => 'Pauseret'];
                            $statusBadge = isset($subBadgeMap[$subscription['status']]) ? $subBadgeMap[$subscription['status']] : 'badge-info';
                            $statusText = isset($subTextMap[$subscription['status']]) ? $subTextMap[$subscription['status']] : $subscription['status'];
                            ?>
                            <span class="badge <?= $statusBadge ?>"><?= $statusText ?></span>
                        </div>
                    </div>
                    <div>
                        <div class="text-sm text-muted">Periode udløber</div>
                        <div class="font-medium">
                            <?= $subscription['current_period_end'] ? date('d/m/Y', strtotime($subscription['current_period_end'])) : '-' ?>
                        </div>
                    </div>
                    <div>
                        <div class="text-sm text-muted">Maks gæster</div>
                        <div class="font-medium"><?= $subscription['max_guests'] ?></div>
                    </div>
                    <div>
                        <div class="text-sm text-muted">Maks events</div>
                        <div class="font-medium"><?= $subscription['max_events'] ?></div>
                    </div>
                </div>

                <?php if (!empty($subscription['stripe_customer_id'])): ?>
                    <div style="margin-top: var(--space-md);">
                        <div class="text-sm text-muted">Stripe Customer ID</div>
                        <code style="font-size: 11px;"><?= escape($subscription['stripe_customer_id']) ?></code>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <p>Ingen aktivt abonnement</p>
                </div>
            <?php endif; ?>

            <hr style="margin: var(--space-lg) 0; border: none; border-top: 1px solid var(--border);">

            <form method="POST" class="flex gap-md items-center" style="flex-wrap: wrap;">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="change_plan">
                <label class="form-label" style="margin-bottom: 0;">Skift plan:</label>
                <select name="plan_id" class="form-input form-select" style="width: auto;">
                    <?php foreach ($allPlans as $plan): ?>
                        <option value="<?= $plan['id'] ?>"
                            <?= ($subscription && $subscription['plan_id'] == $plan['id']) ? 'selected' : '' ?>>
                            <?= escape($plan['name']) ?> (<?= number_format($plan['price_monthly'], 0, ',', '.') ?> kr/md)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">Gem</button>
            </form>
        </div>
    </div>

    <!-- Events -->
    <div class="card mt-md">
        <h2 class="card-title">Events (<?= count($events) ?>)</h2>

        <?php if (empty($events)): ?>
            <div class="empty-state">
                <p>Ingen events</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Type</th>
                            <th>Dato</th>
                            <th>Gæster</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event): ?>
                            <tr>
                                <td>
                                    <div class="font-medium"><?= escape($event['name']) ?></div>
                                    <?php if ($event['slug']): ?>
                                        <div class="text-xs text-muted">/<?= escape($event['slug']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= escape($event['event_type_name'] ?? '-') ?></td>
                                <td><?= $event['event_date'] ? date('d/m/Y', strtotime($event['event_date'])) : '-' ?></td>
                                <td><?= $event['guest_count'] ?></td>
                                <td>
                                    <?php
                                    $eventBadgeMap = ['active' => 'badge-success', 'draft' => 'badge-warning', 'completed' => 'badge-info', 'archived' => 'badge-error'];
                                    $evStatus = $event['status'] ?? 'active';
                                    $statusBadge = isset($eventBadgeMap[$evStatus]) ? $eventBadgeMap[$evStatus] : 'badge-info';
                                    ?>
                                    <span class="badge <?= $statusBadge ?>"><?= ucfirst($evStatus) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Payment History -->
    <div class="card mt-md">
        <h2 class="card-title">Betalingshistorik</h2>

        <?php if (empty($payments)): ?>
            <div class="empty-state">
                <p>Ingen betalinger</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Dato</th>
                            <th>Beskrivelse</th>
                            <th>Beløb</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($payment['created_at'])) ?></td>
                                <td><?= escape($payment['description'] ?? '-') ?></td>
                                <td class="font-medium"><?= number_format($payment['amount'], 0, ',', '.') ?> <?= $payment['currency'] ?></td>
                                <td>
                                    <?php
                                    $payBadgeMap = ['succeeded' => 'badge-success', 'pending' => 'badge-warning', 'failed' => 'badge-error', 'refunded' => 'badge-info'];
                                    $payTextMap = ['succeeded' => 'Gennemført', 'pending' => 'Afventer', 'failed' => 'Fejlet', 'refunded' => 'Refunderet'];
                                    $statusBadge = isset($payBadgeMap[$payment['status']]) ? $payBadgeMap[$payment['status']] : 'badge-info';
                                    $statusText = isset($payTextMap[$payment['status']]) ? $payTextMap[$payment['status']] : $payment['status'];
                                    ?>
                                    <span class="badge <?= $statusBadge ?>"><?= $statusText ?></span>
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

<?php
/**
 * Account Billing & Payment History
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth-account.php';
require_once __DIR__ . '/../../includes/subscription.php';
require_once __DIR__ . '/../../includes/stripe.php';

requireAccountLogin();

$db = getDB();
$account = getCurrentAccount();
$accountId = $account['id'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Open customer portal
    if ($action === 'open_portal') {
        $returnUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/app/account/billing.php';
        $portal = createPortalSession($db, $accountId, $returnUrl);

        if ($portal && !empty($portal['url'])) {
            redirect($portal['url']);
        } else {
            setFlash('error', 'Kunne ikke åbne betalingsportalen. Prøv igen.');
        }
    }

    // Start checkout for plan upgrade
    if ($action === 'subscribe' && !empty($_POST['plan'])) {
        $planSlug = $_POST['plan'];
        $session = createCheckoutSession($db, $accountId, $planSlug);

        if ($session && !empty($session['url'])) {
            redirect($session['url']);
        } else {
            setFlash('error', 'Kunne ikke starte betalingsflow. Prøv igen.');
        }
    }

    // Cancel subscription
    if ($action === 'cancel' && !empty($_POST['subscription_id'])) {
        $stmt = $db->prepare("SELECT stripe_subscription_id FROM subscriptions WHERE id = ? AND account_id = ?");
        $stmt->execute([$_POST['subscription_id'], $accountId]);
        $sub = $stmt->fetch();

        if ($sub && cancelSubscription($sub['stripe_subscription_id'])) {
            $stmt = $db->prepare("UPDATE subscriptions SET cancel_at_period_end = 1, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$_POST['subscription_id']]);
            setFlash('success', 'Dit abonnement vil blive annulleret ved periodens udløb.');
        } else {
            setFlash('error', 'Kunne ikke annullere abonnement. Prøv igen.');
        }
    }

    // Reactivate subscription
    if ($action === 'reactivate' && !empty($_POST['subscription_id'])) {
        $stmt = $db->prepare("SELECT stripe_subscription_id FROM subscriptions WHERE id = ? AND account_id = ?");
        $stmt->execute([$_POST['subscription_id'], $accountId]);
        $sub = $stmt->fetch();

        if ($sub && reactivateSubscription($sub['stripe_subscription_id'])) {
            $stmt = $db->prepare("UPDATE subscriptions SET cancel_at_period_end = 0, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$_POST['subscription_id']]);
            setFlash('success', 'Dit abonnement er genaktiveret!');
        } else {
            setFlash('error', 'Kunne ikke genaktivere abonnement. Prøv igen.');
        }
    }

    redirect('/app/account/billing.php');
}

// Get current subscription
$subscription = getAccountSubscription($db, $accountId);
$currentPlan = $subscription['plan'] ?? null;
$subscriptionRecord = null;

$stmt = $db->prepare("
    SELECT s.*, p.name as plan_name, p.slug as plan_slug
    FROM subscriptions s
    JOIN plans p ON s.plan_id = p.id
    WHERE s.account_id = ? AND s.status IN ('active', 'past_due')
    ORDER BY s.created_at DESC LIMIT 1
");
$stmt->execute([$accountId]);
$subscriptionRecord = $stmt->fetch();

// Get payment history
$stmt = $db->prepare("
    SELECT * FROM payment_history
    WHERE account_id = ?
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute([$accountId]);
$payments = $stmt->fetchAll();

// Get all available plans for upgrade
$plans = getAllPlans($db);

$flash = getFlash();

require_once __DIR__ . '/../../includes/app-header.php';
?>

<div class="page-header">
    <div class="page-header-content">
        <h1>Fakturering</h1>
        <p class="page-subtitle">Administrer dit abonnement og se betalingshistorik</p>
    </div>
</div>

<div class="content-container">
    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?>" style="margin-bottom: 24px;">
        <?= htmlspecialchars($flash['message']) ?>
    </div>
    <?php endif; ?>

    <!-- Current Subscription -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Dit abonnement</h2>
        </div>
        <div class="card-body">
            <?php if ($subscriptionRecord): ?>
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 20px;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                            <span style="font-size: 28px; font-weight: 700; color: var(--primary);">
                                <?= htmlspecialchars($subscriptionRecord['plan_name']) ?>
                            </span>
                            <?php if ($subscriptionRecord['status'] === 'active'): ?>
                                <?php if ($subscriptionRecord['cancel_at_period_end']): ?>
                                    <span class="badge badge-warning">Annulleres</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Aktiv</span>
                                <?php endif; ?>
                            <?php elseif ($subscriptionRecord['status'] === 'past_due'): ?>
                                <span class="badge badge-danger">Betaling mangler</span>
                            <?php endif; ?>
                        </div>
                        <p style="color: var(--gray-600);">
                            <?php if ($subscriptionRecord['cancel_at_period_end']): ?>
                                Udløber: <?= date('j. F Y', strtotime($subscriptionRecord['current_period_end'])) ?>
                            <?php else: ?>
                                Næste fornyelse: <?= date('j. F Y', strtotime($subscriptionRecord['current_period_end'])) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                        <?php if ($subscriptionRecord['cancel_at_period_end']): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="reactivate">
                                <input type="hidden" name="subscription_id" value="<?= $subscriptionRecord['id'] ?>">
                                <button type="submit" class="btn btn-primary">Genaktiver</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="open_portal">
                                <button type="submit" class="btn btn-secondary">Administrer betaling</button>
                            </form>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Er du sikker på at du vil annullere dit abonnement?');">
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="subscription_id" value="<?= $subscriptionRecord['id'] ?>">
                                <button type="submit" class="btn btn-ghost" style="color: var(--danger);">Annuller</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($subscriptionRecord['status'] === 'past_due'): ?>
                <div style="margin-top: 20px; padding: 16px; background: #fef2f2; border-radius: 12px; border-left: 4px solid var(--danger);">
                    <p style="color: #991b1b; font-weight: 600;">Din betaling kunne ikke gennemføres</p>
                    <p style="color: #7f1d1d; font-size: 14px; margin-top: 4px;">
                        Opdater dine betalingsoplysninger for at undgå at miste adgang til premium-funktioner.
                    </p>
                    <form method="POST" style="margin-top: 12px;">
                        <input type="hidden" name="action" value="open_portal">
                        <button type="submit" class="btn btn-primary">Opdater betalingsmetode</button>
                    </form>
                </div>
                <?php endif; ?>

            <?php else: ?>
                <div style="text-align: center; padding: 32px 16px;">
                    <div style="width: 64px; height: 64px; margin: 0 auto 16px; background: var(--gray-100); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <svg width="32" height="32" fill="none" stroke="var(--gray-400)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                    </div>
                    <h3 style="font-size: 18px; margin-bottom: 8px;">Gratis plan</h3>
                    <p style="color: var(--gray-600); margin-bottom: 20px;">Du bruger den gratis plan med begrænsede funktioner.</p>
                    <a href="/app/account/subscription.php" class="btn btn-primary">Opgrader nu</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Plan Features Comparison (if on free) -->
    <?php if (!$subscriptionRecord): ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Sammenlign planer</h2>
        </div>
        <div class="card-body" style="padding: 0; overflow-x: auto;">
            <table class="table" style="min-width: 600px;">
                <thead>
                    <tr>
                        <th style="width: 30%;">Funktion</th>
                        <?php foreach ($plans as $plan): ?>
                        <th style="text-align: center;"><?= htmlspecialchars($plan['name']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Max gæster</td>
                        <?php foreach ($plans as $plan): ?>
                        <td style="text-align: center;"><?= $plan['max_guests'] ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td>Max arrangementer</td>
                        <?php foreach ($plans as $plan): ?>
                        <td style="text-align: center;"><?= $plan['max_events'] > 100 ? 'Ubegrænset' : $plan['max_events'] ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td>Bordplan</td>
                        <?php foreach ($plans as $plan): ?>
                        <?php $features = json_decode($plan['features'], true); ?>
                        <td style="text-align: center;"><?= !empty($features['seating']) ? '<span style="color: var(--success);">&#10003;</span>' : '<span style="color: var(--gray-400);">&#10007;</span>' ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td>Tjekliste</td>
                        <?php foreach ($plans as $plan): ?>
                        <?php $features = json_decode($plan['features'], true); ?>
                        <td style="text-align: center;"><?= !empty($features['checklist']) ? '<span style="color: var(--success);">&#10003;</span>' : '<span style="color: var(--gray-400);">&#10007;</span>' ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td>Toastmaster</td>
                        <?php foreach ($plans as $plan): ?>
                        <?php $features = json_decode($plan['features'], true); ?>
                        <td style="text-align: center;"><?= !empty($features['toastmaster']) ? '<span style="color: var(--success);">&#10003;</span>' : '<span style="color: var(--gray-400);">&#10007;</span>' ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td>Budget</td>
                        <?php foreach ($plans as $plan): ?>
                        <?php $features = json_decode($plan['features'], true); ?>
                        <td style="text-align: center;"><?= !empty($features['budget']) ? '<span style="color: var(--success);">&#10003;</span>' : '<span style="color: var(--gray-400);">&#10007;</span>' ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td><strong>Pris pr. måned</strong></td>
                        <?php foreach ($plans as $plan): ?>
                        <td style="text-align: center; font-weight: 600;">
                            <?= $plan['price_monthly'] > 0 ? number_format($plan['price_monthly'], 0, ',', '.') . ' kr' : 'Gratis' ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td></td>
                        <?php foreach ($plans as $plan): ?>
                        <td style="text-align: center; padding: 16px;">
                            <?php if ($plan['slug'] === 'free'): ?>
                                <span class="badge badge-success">Aktiv</span>
                            <?php else: ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="subscribe">
                                    <input type="hidden" name="plan" value="<?= $plan['slug'] === 'basic' ? 'basis' : $plan['slug'] ?>">
                                    <button type="submit" class="btn btn-primary btn-sm">Vælg</button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Payment History -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Betalingshistorik</h2>
        </div>
        <div class="card-body">
            <?php if (empty($payments)): ?>
                <div class="empty-state">
                    <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                    <p>Ingen betalinger endnu</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Dato</th>
                                <th>Beskrivelse</th>
                                <th style="text-align: right;">Beløb</th>
                                <th style="text-align: center;">Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?= date('j. M Y', strtotime($payment['created_at'])) ?></td>
                                <td><?= htmlspecialchars($payment['description'] ?? 'Abonnement') ?></td>
                                <td style="text-align: right;"><?= number_format($payment['amount'], 2, ',', '.') ?> <?= strtoupper($payment['currency']) ?></td>
                                <td style="text-align: center;">
                                    <?php if ($payment['status'] === 'paid' || $payment['status'] === 'succeeded'): ?>
                                        <span class="badge badge-success">Betalt</span>
                                    <?php elseif ($payment['status'] === 'pending'): ?>
                                        <span class="badge badge-warning">Afventer</span>
                                    <?php elseif ($payment['status'] === 'failed'): ?>
                                        <span class="badge badge-danger">Fejlet</span>
                                    <?php elseif ($payment['status'] === 'refunded'): ?>
                                        <span class="badge badge-info">Refunderet</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($payment['receipt_url']): ?>
                                        <a href="<?= htmlspecialchars($payment['receipt_url']) ?>" target="_blank" style="color: var(--primary); font-size: 14px;">Kvittering</a>
                                    <?php elseif ($payment['invoice_url']): ?>
                                        <a href="<?= htmlspecialchars($payment['invoice_url']) ?>" target="_blank" style="color: var(--primary); font-size: 14px;">Faktura</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- FAQ -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Ofte stillede spørgsmål</h2>
        </div>
        <div class="card-body">
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <div>
                    <h4 style="font-weight: 600; margin-bottom: 6px;">Hvordan annullerer jeg mit abonnement?</h4>
                    <p style="color: var(--gray-600); font-size: 14px;">
                        Du kan annullere dit abonnement når som helst ved at klikke på "Annuller" ovenfor. Du beholder adgang til betalte funktioner indtil periodens udløb.
                    </p>
                </div>
                <div>
                    <h4 style="font-weight: 600; margin-bottom: 6px;">Kan jeg skifte plan?</h4>
                    <p style="color: var(--gray-600); font-size: 14px;">
                        Ja, du kan opgradere eller nedgradere din plan når som helst via "Administrer betaling". Ændringer træder i kraft ved næste faktureringsperiode.
                    </p>
                </div>
                <div>
                    <h4 style="font-weight: 600; margin-bottom: 6px;">Hvad sker der med mine data hvis jeg annullerer?</h4>
                    <p style="color: var(--gray-600); font-size: 14px;">
                        Dine data bevares i 30 dage efter annullering. Du kan genaktivere dit abonnement og få fuld adgang igen.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/app-footer.php'; ?>

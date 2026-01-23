<?php
/**
 * Subscription Management Page
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

// Handle Stripe checkout return
if (isset($_GET['success']) && $_GET['success'] == '1') {
    setFlash('success', 'Dit abonnement er nu aktivt! Tak for din tilmelding.');
    redirect('/app/account/subscription.php');
}

if (isset($_GET['cancelled']) && $_GET['cancelled'] == '1') {
    setFlash('info', 'Betalingen blev annulleret. Du kan prøve igen når som helst.');
    redirect('/app/account/subscription.php');
}

// Handle subscribe action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'subscribe') {
    $planSlug = $_POST['plan'] ?? '';

    // Map plan slugs to Stripe price slugs
    $slugMap = ['basic' => 'basis', 'premium' => 'premium', 'pro' => 'pro'];
    $stripeSlug = $slugMap[$planSlug] ?? $planSlug;

    $session = createCheckoutSession($db, $accountId, $stripeSlug);

    if ($session && !empty($session['url'])) {
        redirect($session['url']);
    } else {
        setFlash('error', 'Kunne ikke starte betalingsflow. Prøv igen.');
    }
}

$flash = getFlash();
$pageTitle = 'Abonnement';
require_once __DIR__ . '/../../includes/app-header.php';

// Get all plans
$allPlans = getAllPlans($db);

// Get current subscription details
$subscription = getAccountSubscription($db, $accountId);
$currentPlan = $subscription ?? null;
$currentPlanSlug = $currentPlan['plan_slug'] ?? 'free';
?>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?>" style="margin-bottom: 24px;">
    <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Dit abonnement</h1>
        <p class="page-subtitle">Administrer din plan og fakturering</p>
    </div>
    <div>
        <a href="/app/account/billing.php" class="btn btn-secondary">Se betalingshistorik</a>
    </div>
</div>

<!-- Current Plan -->
<div class="card current-plan-card">
    <div class="current-plan-header">
        <div>
            <span class="plan-label">Din nuværende plan</span>
            <h2 class="current-plan-name"><?= htmlspecialchars($currentPlan['plan_name'] ?? 'Gratis') ?></h2>
        </div>
        <?php if ($currentPlanSlug !== 'free'): ?>
        <div class="plan-price">
            <span class="price-amount"><?= formatCurrency($currentPlan['price_monthly'] ?? 0) ?></span>
            <span class="price-period">/måned</span>
        </div>
        <?php endif; ?>
    </div>

    <div class="current-plan-features">
        <div class="feature-item">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>
            <span>Op til <?= (int)($currentPlan['max_guests'] ?? 30) ?> gæster per arrangement</span>
        </div>
        <div class="feature-item">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            <span><?= ($currentPlan['max_events'] ?? 1) === 999 ? 'Ubegrænsede' : 'Op til ' . ($currentPlan['max_events'] ?? 1) ?> arrangementer</span>
        </div>
    </div>

    <?php if ($currentPlan && $currentPlan['current_period_end']): ?>
    <div class="billing-info">
        <span>Næste fakturering: <?= htmlspecialchars(formatDate($currentPlan['current_period_end'])) ?></span>
    </div>
    <?php endif; ?>
</div>

<!-- Available Plans -->
<h2 class="section-title">Tilgængelige planer</h2>
<p class="section-subtitle">Vælg den plan der passer til dit arrangement</p>

<div class="plans-grid">
    <?php foreach ($allPlans as $plan):
        $isCurrentPlan = $plan['slug'] === $currentPlanSlug;
        $features = $plan['features'] ?? [];
        $formattedFeatures = formatPlanFeatures($features);
    ?>
    <div class="plan-card <?= $isCurrentPlan ? 'current' : '' ?> <?= $plan['slug'] === 'premium' ? 'popular' : '' ?>">
        <?php if ($plan['slug'] === 'premium'): ?>
        <div class="popular-badge">Mest populære</div>
        <?php endif; ?>

        <div class="plan-card-header">
            <h3 class="plan-name"><?= htmlspecialchars($plan['name']) ?></h3>
            <div class="plan-pricing">
                <?php if ($plan['price_monthly'] > 0): ?>
                    <span class="plan-price"><?= formatCurrency($plan['price_monthly']) ?></span>
                    <span class="plan-period">/måned</span>
                <?php else: ?>
                    <span class="plan-price">Gratis</span>
                <?php endif; ?>
            </div>
            <?php if ($plan['price_yearly'] > 0): ?>
            <div class="yearly-price">
                eller <?= formatCurrency($plan['price_yearly']) ?>/år (spar <?= round((1 - $plan['price_yearly'] / ($plan['price_monthly'] * 12)) * 100) ?>%)
            </div>
            <?php endif; ?>
        </div>

        <div class="plan-card-features">
            <div class="feature">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span>Op til <?= (int)$plan['max_guests'] ?> gæster</span>
            </div>
            <div class="feature">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span><?= $plan['max_events'] === 999 ? 'Ubegrænsede' : $plan['max_events'] ?> arrangementer</span>
            </div>
            <div class="feature">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span>Gæstehåndtering & RSVP</span>
            </div>
            <div class="feature">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span>Ønskeliste & menu</span>
            </div>
            <div class="feature">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span>Fotogalleri</span>
            </div>

            <?php if (!empty($features['checklist'])): ?>
            <div class="feature">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span>Tjekliste</span>
            </div>
            <?php endif; ?>

            <?php if (!empty($features['seating'])): ?>
            <div class="feature">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span>Bordplan</span>
            </div>
            <?php endif; ?>

            <?php if (!empty($features['budget'])): ?>
            <div class="feature">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span>Budget-styring</span>
            </div>
            <?php endif; ?>

            <?php if (!empty($features['toastmaster'])): ?>
            <div class="feature">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span>Toastmaster-koordinering</span>
            </div>
            <?php endif; ?>

            <?php if (!empty($features['custom_domain'])): ?>
            <div class="feature">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span>Eget domæne</span>
            </div>
            <?php endif; ?>
        </div>

        <div class="plan-card-footer">
            <?php if ($isCurrentPlan): ?>
                <button class="btn btn-secondary" disabled>Din nuværende plan</button>
            <?php elseif ($plan['price_monthly'] == 0): ?>
                <button class="btn btn-secondary" disabled>Gratis plan</button>
            <?php else: ?>
                <form method="POST" style="width: 100%;">
                    <input type="hidden" name="action" value="subscribe">
                    <input type="hidden" name="plan" value="<?= htmlspecialchars($plan['slug']) ?>">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <?= $plan['price_monthly'] > ($currentPlan['price_monthly'] ?? 0) ? 'Opgrader' : 'Skift plan' ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- FAQ -->
<div class="card faq-section">
    <h2 class="card-title">Ofte stillede spørgsmål</h2>

    <div class="faq-item">
        <h4>Kan jeg opgradere eller nedgradere min plan?</h4>
        <p>Ja, du kan til enhver tid skifte plan. Ved opgradering får du adgang til de nye funktioner med det samme. Ved nedgradering beholder du din nuværende plan indtil næste faktureringsperiode.</p>
    </div>

    <div class="faq-item">
        <h4>Hvordan fungerer betalingen?</h4>
        <p>Vi bruger Stripe til sikker betaling. Du kan betale med Visa, Mastercard eller MobilePay. Betalingen trækkes automatisk hver måned eller år, afhængigt af din valgte plan.</p>
    </div>

    <div class="faq-item">
        <h4>Kan jeg annullere mit abonnement?</h4>
        <p>Ja, du kan annullere når som helst. Du beholder adgang til din plan indtil udløbet af den betalte periode.</p>
    </div>

    <div class="faq-item">
        <h4>Hvad sker der med mine data hvis jeg nedgraderer?</h4>
        <p>Dine data forbliver gemt, men du mister adgang til premium-funktioner. Hvis du har flere gæster end din nye plan tillader, kan du ikke tilføje flere, men eksisterende gæster bevares.</p>
    </div>
</div>

<style>
    .current-plan-card {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        color: white;
        margin-bottom: 40px;
    }

    .current-plan-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 24px;
    }

    .plan-label {
        font-size: 13px;
        opacity: 0.8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .current-plan-name {
        font-size: 32px;
        font-weight: 700;
        margin-top: 4px;
    }

    .plan-price {
        text-align: right;
    }

    .price-amount {
        font-size: 28px;
        font-weight: 700;
    }

    .price-period {
        font-size: 14px;
        opacity: 0.8;
    }

    .current-plan-features {
        display: flex;
        gap: 24px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }

    .current-plan-features .feature-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
    }

    .current-plan-features .feature-item svg {
        width: 18px;
        height: 18px;
        opacity: 0.8;
    }

    .billing-info {
        padding-top: 16px;
        border-top: 1px solid rgba(255,255,255,0.2);
        font-size: 14px;
        opacity: 0.8;
    }

    .section-title {
        font-size: 24px;
        font-weight: 700;
        color: var(--gray-900);
        margin-bottom: 8px;
    }

    .section-subtitle {
        color: var(--gray-500);
        margin-bottom: 24px;
    }

    .plans-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 24px;
        margin-bottom: 40px;
    }

    .plan-card {
        background: white;
        border-radius: 16px;
        padding: 24px;
        border: 2px solid var(--gray-200);
        display: flex;
        flex-direction: column;
        position: relative;
        transition: all 0.2s;
    }

    .plan-card:hover {
        border-color: var(--gray-300);
        box-shadow: 0 8px 24px rgba(0,0,0,0.08);
    }

    .plan-card.current {
        border-color: var(--primary);
        background: linear-gradient(to bottom, rgba(102, 126, 234, 0.05), transparent);
    }

    .plan-card.popular {
        border-color: var(--primary);
    }

    .popular-badge {
        position: absolute;
        top: -12px;
        left: 50%;
        transform: translateX(-50%);
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        color: white;
        padding: 4px 16px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }

    .plan-card-header {
        text-align: center;
        padding-bottom: 20px;
        border-bottom: 1px solid var(--gray-100);
        margin-bottom: 20px;
    }

    .plan-card .plan-name {
        font-size: 20px;
        font-weight: 700;
        color: var(--gray-900);
        margin-bottom: 12px;
    }

    .plan-pricing {
        display: flex;
        align-items: baseline;
        justify-content: center;
        gap: 4px;
    }

    .plan-card .plan-price {
        font-size: 36px;
        font-weight: 700;
        color: var(--gray-900);
    }

    .plan-card .plan-period {
        color: var(--gray-500);
        font-size: 14px;
    }

    .yearly-price {
        font-size: 13px;
        color: var(--success);
        margin-top: 8px;
    }

    .plan-card-features {
        flex: 1;
        margin-bottom: 24px;
    }

    .feature {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 0;
        font-size: 14px;
        color: var(--gray-700);
    }

    .feature svg {
        width: 18px;
        height: 18px;
        color: var(--success);
        flex-shrink: 0;
    }

    .plan-card-footer .btn {
        width: 100%;
    }

    .faq-section {
        margin-top: 20px;
    }

    .faq-item {
        padding: 20px 0;
        border-bottom: 1px solid var(--gray-100);
    }

    .faq-item:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .faq-item h4 {
        font-size: 16px;
        font-weight: 600;
        color: var(--gray-900);
        margin-bottom: 8px;
    }

    .faq-item p {
        font-size: 14px;
        color: var(--gray-600);
        line-height: 1.6;
    }

    @media (max-width: 640px) {
        .plans-grid {
            grid-template-columns: 1fr;
        }

        .current-plan-header {
            flex-direction: column;
            gap: 16px;
        }

        .plan-price {
            text-align: left;
        }
    }
</style>

<?php require_once __DIR__ . '/../../includes/app-footer.php'; ?>

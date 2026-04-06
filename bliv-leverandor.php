<?php
/**
 * Vendor Landing Page - "Bliv leverandør"
 * Public page to attract vendors to register on the platform.
 * Accepts ?ref=CODE for referral agent tracking.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// --- Referral agent lookup ---
$db = getDB();
$refCode = trim($_GET['ref'] ?? '');
$agentName = null;
if ($refCode !== '') {
    $stmt = $db->prepare("
        SELECT a.name
        FROM referral_agents ra
        JOIN accounts a ON a.id = ra.account_id
        WHERE ra.referral_code = ? AND ra.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$refCode]);
    $agentName = $stmt->fetchColumn();
    if (!$agentName) {
        $refCode = ''; // invalid code, ignore
    }
}

// --- Fetch vendor categories ---
$categories = $db->query("
    SELECT vc.id, vc.name, vc.slug, vc.icon,
           COUNT(DISTINCT vcl.vendor_id) AS vendor_count
    FROM vendor_categories vc
    LEFT JOIN vendor_category_links vcl ON vcl.category_id = vc.id
    LEFT JOIN vendors v ON v.id = vcl.vendor_id AND v.status = 'approved' AND v.is_active = 1
    WHERE vc.is_active = 1
    GROUP BY vc.id
    ORDER BY vc.sort_order ASC
")->fetchAll(PDO::FETCH_ASSOC);

// --- Count total approved vendors ---
$totalVendors = (int)$db->query("
    SELECT COUNT(*) FROM vendors WHERE status = 'approved' AND is_active = 1
")->fetchColumn();

// --- Build registration URL ---
$registerUrl = '/subcontractor/dashboard/register.php';
if ($refCode) {
    $registerUrl .= '?ref=' . urlencode($refCode);
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bliv leverandør - PartyParart</title>
    <meta name="description" content="Opret en gratis leverandørprofil på PartyParart og bliv fundet af hundredvis af arrangører. Ingen oprettelsesgebyr – du betaler kun ved ordre.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --surface: #FAF9F7;
            --border: #F5F3EF;
            --accent: #A8B5A0;
            --accent-light: #C8D4C2;
            --accent-dark: #7A8B72;
            --text: #2C2C2C;
            --text-secondary: #4A4A4A;
            --warning: #C9A962;
            --white: #FFFFFF;
            --error: #C75D5D;
            --font-display: 'Cormorant Garamond', Georgia, serif;
            --font-body: 'DM Sans', -apple-system, sans-serif;
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: var(--font-body);
            color: var(--text);
            line-height: 1.6;
            background: var(--surface);
            -webkit-font-smoothing: antialiased;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.8' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E");
            opacity: 0.015;
            pointer-events: none;
            z-index: 9999;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* ===== TOP BAR ===== */
        .topbar {
            padding: 16px 0;
            background: var(--white);
            border-bottom: 1px solid var(--border);
        }

        .topbar .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            font-family: var(--font-display);
            font-size: 26px;
            font-weight: 500;
            color: var(--text);
            text-decoration: none;
            letter-spacing: -0.02em;
        }

        .logo span { color: var(--accent-dark); }

        .topbar-login {
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: color 0.3s;
        }

        .topbar-login:hover { color: var(--accent-dark); }

        /* ===== HERO ===== */
        .hero {
            background: linear-gradient(135deg, var(--accent-light) 0%, var(--accent) 50%, var(--accent-dark) 100%);
            padding: 96px 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at 30% 20%, rgba(255,255,255,0.15) 0%, transparent 60%),
                        radial-gradient(ellipse at 70% 80%, rgba(0,0,0,0.06) 0%, transparent 50%);
            pointer-events: none;
        }

        .hero-content {
            position: relative;
            z-index: 1;
        }

        .ref-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.3);
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 14px;
            color: var(--white);
            margin-bottom: 24px;
            font-weight: 500;
        }

        .hero h1 {
            font-family: var(--font-display);
            font-size: clamp(36px, 5vw, 56px);
            font-weight: 600;
            color: var(--white);
            line-height: 1.15;
            margin-bottom: 24px;
            letter-spacing: -0.02em;
        }

        .hero p {
            font-size: clamp(16px, 2vw, 19px);
            color: rgba(255,255,255,0.9);
            max-width: 580px;
            margin: 0 auto 48px;
            line-height: 1.7;
        }

        .btn-cta {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 16px 32px;
            font-size: 16px;
            font-weight: 600;
            font-family: var(--font-body);
            background: var(--accent-dark);
            color: var(--white);
            border: none;
            border-radius: 50px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }

        .btn-cta:hover {
            background: #6A7E62;
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.2);
        }

        .btn-cta svg {
            width: 18px;
            height: 18px;
            transition: transform 0.3s;
        }

        .btn-cta:hover svg {
            transform: translateX(3px);
        }

        .hero-sub {
            margin-top: 16px;
            font-size: 14px;
            color: rgba(255,255,255,0.7);
        }

        /* ===== SECTIONS SHARED ===== */
        .section {
            padding: 96px 0;
        }

        .section-title {
            font-family: var(--font-display);
            font-size: clamp(28px, 3.5vw, 40px);
            font-weight: 600;
            text-align: center;
            margin-bottom: 16px;
            letter-spacing: -0.02em;
            color: var(--text);
        }

        .section-subtitle {
            text-align: center;
            color: var(--text-secondary);
            font-size: 17px;
            max-width: 540px;
            margin: 0 auto 48px;
        }

        /* ===== FORDELE (3 CARDS) ===== */
        .fordele { background: var(--white); }

        .cards-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 32px;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 32px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.06);
            border-color: var(--accent-light);
        }

        .card-icon {
            width: 52px;
            height: 52px;
            background: var(--accent-light);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
        }

        .card-icon svg {
            width: 24px;
            height: 24px;
            color: var(--accent-dark);
        }

        .card h3 {
            font-family: var(--font-display);
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 12px;
            letter-spacing: -0.01em;
        }

        .card p {
            color: var(--text-secondary);
            font-size: 15px;
            line-height: 1.65;
        }

        /* ===== STEPS ===== */
        .steps-section { background: var(--surface); }

        .steps {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 48px;
            position: relative;
        }

        .steps::before {
            content: '';
            position: absolute;
            top: 32px;
            left: calc(16.66% + 24px);
            right: calc(16.66% + 24px);
            height: 2px;
            background: var(--accent-light);
        }

        .step {
            text-align: center;
            position: relative;
        }

        .step-number {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: var(--accent);
            color: var(--white);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-family: var(--font-display);
            font-size: 26px;
            font-weight: 600;
            margin-bottom: 24px;
            position: relative;
            z-index: 1;
        }

        .step h3 {
            font-family: var(--font-display);
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 8px;
            letter-spacing: -0.01em;
        }

        .step p {
            color: var(--text-secondary);
            font-size: 15px;
            line-height: 1.65;
            max-width: 280px;
            margin: 0 auto;
        }

        /* ===== CATEGORIES ===== */
        .categories-section { background: var(--white); }

        .cat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
        }

        .cat-card {
            display: flex;
            align-items: center;
            gap: 16px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            transition: all 0.3s ease;
        }

        .cat-card:hover {
            border-color: var(--accent-light);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
        }

        .cat-icon {
            font-size: 24px;
            flex-shrink: 0;
            width: 40px;
            text-align: center;
        }

        .cat-info { flex: 1; min-width: 0; }

        .cat-name {
            font-weight: 600;
            font-size: 15px;
            color: var(--text);
        }

        .cat-count {
            font-size: 13px;
            color: var(--text-secondary);
            margin-top: 2px;
        }

        /* ===== BOTTOM CTA ===== */
        .bottom-cta {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
            padding: 96px 0;
            text-align: center;
        }

        .bottom-cta h2 {
            font-family: var(--font-display);
            font-size: clamp(30px, 4vw, 44px);
            font-weight: 600;
            color: var(--white);
            margin-bottom: 32px;
            letter-spacing: -0.02em;
        }

        .bottom-cta .btn-cta {
            background: var(--white);
            color: var(--accent-dark);
        }

        .bottom-cta .btn-cta:hover {
            background: var(--surface);
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.15);
        }

        .bottom-cta .support-text {
            margin-top: 24px;
            font-size: 14px;
            color: rgba(255,255,255,0.7);
        }

        .bottom-cta .support-text a {
            color: rgba(255,255,255,0.9);
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        /* ===== SOCIAL PROOF ===== */
        .social-proof {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-top: 40px;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            padding: 10px 20px;
            border-radius: 50px;
            font-size: 14px;
            color: rgba(255,255,255,0.85);
        }

        .social-proof svg {
            width: 18px;
            height: 18px;
            color: rgba(255,255,255,0.7);
        }

        /* ===== ANIMATIONS ===== */
        .fade-up {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.7s cubic-bezier(0.4, 0, 0.2, 1), transform 0.7s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .fade-up.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .fade-up:nth-child(2) { transition-delay: 0.1s; }
        .fade-up:nth-child(3) { transition-delay: 0.2s; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .cards-row {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .steps {
                grid-template-columns: 1fr;
                gap: 40px;
            }

            .steps::before { display: none; }

            .cat-grid {
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }

            .hero { padding: 72px 0 80px; }
            .section { padding: 64px 0; }
            .bottom-cta { padding: 64px 0; }

            .card { padding: 28px 24px; }
        }

        @media (max-width: 480px) {
            .cat-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<!-- Top bar -->
<div class="topbar">
    <div class="container">
        <a href="/" class="logo">Party<span>Par</span>art</a>
        <a href="/subcontractor/dashboard/login.php" class="topbar-login">Log ind</a>
    </div>
</div>

<!-- Hero -->
<section class="hero">
    <div class="container hero-content">
        <?php if ($agentName): ?>
            <div class="ref-badge">Anbefalet af <?= htmlspecialchars($agentName) ?></div>
        <?php endif; ?>

        <h1>Få flere kunder<br>til din virksomhed</h1>
        <p>Opret en gratis profil og bliv fundet af hundredvis af arrangører der søger leverandører til deres events</p>

        <a href="<?= htmlspecialchars($registerUrl) ?>" class="btn-cta">
            Opret din profil gratis
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
        </a>
        <div class="hero-sub">Ingen oprettelsesgebyr. Du betaler kun når du får en ordre.</div>

        <?php if ($totalVendors > 0): ?>
        <div class="social-proof">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <?= $totalVendors ?> leverandør<?= $totalVendors !== 1 ? 'er' : '' ?> er allerede tilmeldt
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- 3 Fordele -->
<section class="section fordele">
    <div class="container">
        <h2 class="section-title">Hvorfor vælge PartyParart?</h2>
        <div class="cards-row">
            <div class="card fade-up">
                <div class="card-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </div>
                <h3>Gratis eksponering</h3>
                <p>Bliv fundet af arrangører der aktivt søger leverandører til bryllupper, fødselsdage og firmafester</p>
            </div>
            <div class="card fade-up">
                <div class="card-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                </div>
                <h3>Dit eget dashboard</h3>
                <p>Administrer din profil, galleri, priser og bookinger fra ét samlet overblik</p>
            </div>
            <div class="card fade-up">
                <div class="card-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h3>Betal kun ved ordre</h3>
                <p>Ingen månedlige gebyrer. Du betaler kun et lille depositum-gebyr når du får en booking</p>
            </div>
        </div>
    </div>
</section>

<!-- Saadan virker det -->
<section class="section steps-section">
    <div class="container">
        <h2 class="section-title">Sådan virker det</h2>
        <p class="section-subtitle">Tre enkle trin fra oprettelse til betaling</p>
        <div class="steps">
            <div class="step fade-up">
                <div class="step-number">1</div>
                <h3>Opret din profil</h3>
                <p>Tilføj dine ydelser, priser og billeder. Det tager kun 5 minutter.</p>
            </div>
            <div class="step fade-up">
                <div class="step-number">2</div>
                <h3>Modtag forespørgsler</h3>
                <p>Arrangører finder dig og sender bookingforespørgsler direkte.</p>
            </div>
            <div class="step fade-up">
                <div class="step-number">3</div>
                <h3>Acceptér og bliv betalt</h3>
                <p>Svar med et tilbud, bekræft bookingen og modtag betaling via platformen.</p>
            </div>
        </div>
    </div>
</section>

<!-- Kategorier -->
<?php if (!empty($categories)): ?>
<section class="section categories-section">
    <div class="container">
        <h2 class="section-title">Vi søger leverandører inden for...</h2>
        <p class="section-subtitle">Uanset din branche er der arrangører der leder efter dig</p>
        <div class="cat-grid">
            <?php foreach ($categories as $cat): ?>
            <div class="cat-card fade-up">
                <div class="cat-icon"><?= $cat['icon'] ?: '&#9733;' ?></div>
                <div class="cat-info">
                    <div class="cat-name"><?= htmlspecialchars($cat['name']) ?></div>
                    <?php if ((int)$cat['vendor_count'] > 0): ?>
                        <div class="cat-count"><?= (int)$cat['vendor_count'] ?> leverandør<?= (int)$cat['vendor_count'] !== 1 ? 'er' : '' ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Bottom CTA -->
<section class="bottom-cta">
    <div class="container">
        <h2>Kom i gang på 5 minutter</h2>
        <a href="<?= htmlspecialchars($registerUrl) ?>" class="btn-cta">
            Opret din profil gratis
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
        </a>
        <div class="support-text">Har du spørgsmål? Kontakt os på <a href="mailto:support@partyparart.dk">support@partyparart.dk</a></div>
    </div>
</section>

<!-- Scroll-reveal animation -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15 });

    document.querySelectorAll('.fade-up').forEach(function(el) {
        observer.observe(el);
    });
});
</script>

</body>
</html>

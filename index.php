<?php
/**
 * Event Platform - Landing Page
 * Immersive Portrait Design - Photo IS the page
 * Cache bust: 20260118-1756
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$db = getDB();
$stmt = $db->query("SELECT * FROM events ORDER BY id LIMIT 1");
$event = $stmt->fetch();

if (!$event) {
    $showSetup = true;
} else {
    $showSetup = false;
    $error = $_GET['error'] ?? null;
}

// Auto-login if valid code in URL (but stay on landing page)
$guestFromUrl = null;
if (!$showSetup && isset($_GET['kode'])) {
    $code = preg_replace('/[^0-9]/', '', $_GET['kode']);
    if (strlen($code) === 6) {
        $stmt = $db->prepare("SELECT * FROM guests WHERE unique_code = ? AND event_id = ?");
        $stmt->execute([$code, $event['id']]);
        $guestFromUrl = $stmt->fetch();

        // Log in but stay on invitation page
        if ($guestFromUrl) {
            loginGuest($guestFromUrl['id'], $event['id']);
        }
    }
}

if (!$showSetup && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guest_code'])) {
    $code = preg_replace('/[^0-9]/', '', $_POST['guest_code']);
    if (strlen($code) === 6) {
        $stmt = $db->prepare("SELECT * FROM guests WHERE unique_code = ? AND event_id = ?");
        $stmt->execute([$code, $event['id']]);
        $guest = $stmt->fetch();
        if ($guest) {
            loginGuest($guest['id'], $event['id']);
            // If already RSVP'd, go to home page. Otherwise go to RSVP form
            if ($guest['rsvp_status'] !== 'pending') {
                redirect(BASE_PATH . '/guest/index.php');
            } else {
                redirect(BASE_PATH . '/guest/rsvp.php');
            }
        } else {
            $error = 'invalid_code';
        }
    } else {
        $error = 'invalid_code';
    }
}

function formatDanishDate($date): string {
    $months = [1=>'januar',2=>'februar',3=>'marts',4=>'april',5=>'maj',6=>'juni',7=>'juli',8=>'august',9=>'september',10=>'oktober',11=>'november',12=>'december'];
    $t = strtotime($date);
    return date('j', $t) . '. ' . $months[(int)date('n', $t)] . ' ' . date('Y', $t);
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $showSetup ? 'Event Platform' : escape($event['confirmand_name']) . 's ' . escape($event['name']) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=Manrope:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --blush: #D4A5A5;
            --blush-dark: #B88A8A;
            --blush-light: #E8CECE;
            --cream: #FFF9F7;
            --ink: #2A2222;
            --white: #FFFFFF;
        }

        html, body {
            height: 100%;
        }

        @media (min-width: 769px) {
            html, body {
                overflow: hidden;
            }
        }

        body {
            font-family: 'Manrope', sans-serif;
            background: var(--ink);
            color: var(--white);
        }

        /* ===== FULL SCREEN PHOTO BACKGROUND ===== */
        .hero {
            position: fixed;
            inset: 0;
            z-index: 1;
        }

        .hero__photo {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center top;
            opacity: 0;
            transform: scale(1.1);
            transition: opacity 1.5s ease, transform 8s ease-out;
        }

        /* Billede 3 - juster position op */
        .hero__photo[data-index="2"] {
            object-position: center 20%;
        }

        .hero__photo.active {
            opacity: 1;
            transform: scale(1);
        }

        /* Color overlay matching the blush palette */
        .hero__overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(
                to bottom,
                rgba(212, 165, 165, 0.15) 0%,
                rgba(42, 34, 34, 0.3) 60%,
                rgba(42, 34, 34, 0.85) 100%
            );
            z-index: 2;
        }

        /* ===== FLOATING CONTENT ===== */
        .content {
            position: relative;
            z-index: 10;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 0 6vw 8vh;
        }

        @media (max-width: 768px) {
            .content {
                padding: 15vh 5vw 4vh;
                justify-content: flex-start;
                overflow-y: auto;
            }

            .event-type {
                font-size: 1.3rem !important;
                margin-bottom: 0.25rem;
            }

            .hero-name {
                font-size: clamp(3rem, 15vw, 5rem) !important;
                margin-bottom: 1rem;
            }

            .welcome-text {
                font-size: 0.95rem !important;
                margin-bottom: 1rem;
            }

            .info-row {
                gap: 1rem !important;
            }

            .info-block__value {
                font-size: 1rem;
            }

            .code-card {
                padding: 1rem 1.25rem;
            }
        }

        /* ===== EVENT TYPE - Above name ===== */
        .event-type {
            font-family: 'Playfair Display', serif;
            font-size: clamp(1.8rem, 5vw, 3.5rem);
            font-style: italic;
            font-weight: 400;
            color: var(--blush-light);
            text-shadow: 0 2px 30px rgba(0,0,0,0.3);
            margin-bottom: 0.5rem;
            opacity: 0;
            animation: fadeUp 1s ease 0.3s forwards;
        }

        /* ===== WELCOME TEXT ===== */
        .welcome-text {
            font-family: 'Playfair Display', serif;
            font-size: 1.1rem;
            font-style: italic;
            color: var(--blush-light);
            text-shadow: 0 2px 20px rgba(0,0,0,0.3);
            max-width: 500px;
            line-height: 1.6;
            margin-bottom: 1.5rem;
            opacity: 0;
            animation: fadeUp 1s ease 1.1s forwards;
        }

        /* ===== THE NAME - Huge, bottom-aligned ===== */
        .hero-name {
            font-family: 'Playfair Display', serif;
            font-size: clamp(5rem, 20vw, 18rem);
            font-weight: 400;
            line-height: 0.8;
            letter-spacing: -0.02em;
            color: var(--white);
            text-shadow: 0 4px 60px rgba(0,0,0,0.4);
            margin-bottom: 2rem;
            opacity: 0;
            transform: translateY(80px);
            animation: heroReveal 1.2s cubic-bezier(0.16, 1, 0.3, 1) 0.5s forwards;
        }

        @keyframes heroReveal {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ===== EVENT INFO ROW ===== */
        .info-row {
            display: flex;
            align-items: flex-end;
            gap: 4rem;
            flex-wrap: wrap;
            opacity: 0;
            animation: fadeUp 1s ease 1s forwards;
        }

        @media (max-width: 768px) {
            .info-row {
                gap: 2rem;
                flex-direction: column;
                align-items: flex-start;
            }
        }

        .info-block {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }

        .info-block__label {
            font-size: 0.65rem;
            font-weight: 600;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--blush-light);
        }

        .info-block__value {
            font-family: 'Playfair Display', serif;
            font-size: 1.2rem;
            font-style: italic;
            color: var(--white);
        }

        /* ===== CODE ENTRY CARD ===== */
        .code-card {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 16px;
            padding: 1.5rem 2rem;
            max-width: 360px;
            opacity: 0;
            animation: fadeUp 1s ease 1.2s forwards;
        }

        .code-card__title {
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--blush-light);
            margin-bottom: 1rem;
        }

        .code-form {
            display: flex;
            gap: 0.75rem;
        }

        .code-input {
            flex: 1;
            padding: 1rem 1.2rem;
            font-family: 'Playfair Display', serif;
            font-size: 1.3rem;
            letter-spacing: 0.3em;
            text-align: center;
            color: var(--white);
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            outline: none;
            transition: all 0.3s ease;
        }

        .code-input:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: var(--blush);
        }

        .code-input::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }

        .btn {
            padding: 1rem 1.8rem;
            font-family: 'Manrope', sans-serif;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            text-decoration: none;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn--primary {
            background: var(--blush);
            color: var(--ink);
        }

        .btn--primary:hover {
            background: var(--blush-light);
            transform: translateY(-2px);
        }

        .btn--large {
            padding: 1.2rem 2.5rem;
        }

        /* Alert */
        .alert {
            background: rgba(212, 165, 165, 0.2);
            border-left: 3px solid var(--blush);
            color: var(--white);
            padding: 0.8rem 1rem;
            margin-bottom: 1rem;
            font-size: 0.85rem;
            border-radius: 0 8px 8px 0;
        }

        /* Guest Greeting */
        .guest-greeting {
            font-family: 'Playfair Display', serif;
            font-size: 1.4rem;
            font-style: italic;
            color: var(--blush-light);
            margin-bottom: 0.5rem;
            opacity: 0;
            animation: fadeUp 1s ease 0.8s forwards;
        }

        /* ===== THUMBNAIL NAVIGATION - Top Right ===== */
        .thumbnails {
            position: fixed;
            top: 2rem;
            right: 2rem;
            z-index: 20;
            display: flex;
            gap: 0.75rem;
            opacity: 0;
            animation: fadeIn 1s ease 1.5s forwards;
        }

        @media (max-width: 768px) {
            .thumbnails {
                top: 1rem;
                right: 1rem;
                gap: 0.5rem;
            }
        }

        .thumbnail {
            width: 70px;
            height: 90px;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            opacity: 0.6;
        }

        @media (max-width: 768px) {
            .thumbnail {
                width: 50px;
                height: 65px;
            }
        }

        .thumbnail:hover {
            opacity: 0.9;
            transform: translateY(-4px);
        }

        .thumbnail.active {
            border-color: var(--blush);
            opacity: 1;
            transform: scale(1.05);
        }

        .thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* ===== EVENT LABEL - Top Left ===== */
        .event-badge {
            position: fixed;
            top: 2rem;
            left: 2rem;
            z-index: 20;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            opacity: 0;
            animation: fadeIn 1s ease 1.3s forwards;
        }

        @media (max-width: 768px) {
            .event-badge {
                top: 1rem;
                left: 1rem;
            }
        }

        .event-badge__icon {
            width: 32px;
            height: 32px;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }

        .event-badge__text {
            font-size: 0.7rem;
            font-weight: 500;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--white);
        }

        /* Animations */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Setup Screen */
        .setup-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background: var(--cream);
            color: var(--ink);
        }

        .setup-card {
            text-align: center;
            max-width: 400px;
        }

        .setup-card h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .setup-card p {
            color: #666;
            margin-bottom: 2rem;
        }

        .setup-card .btn--primary {
            color: var(--white);
            background: var(--ink);
        }
    </style>
</head>
<body>
    <?php if ($showSetup): ?>
    <div class="setup-page">
        <div class="setup-card">
            <h1>Event Platform</h1>
            <p>Der er endnu ikke oprettet et event.</p>
            <a href="<?= BASE_PATH ?>/admin/login.php" class="btn btn--primary">Gå til admin</a>
        </div>
    </div>

    <?php else: ?>
    <!-- FULL SCREEN HERO PHOTO -->
    <div class="hero">
        <img src="<?= BASE_PATH ?>/assets/images/sofie-1.jpg" class="hero__photo active" data-index="0" alt="">
        <img src="<?= BASE_PATH ?>/assets/images/sofie-2.jpg" class="hero__photo" data-index="1" alt="">
        <img src="<?= BASE_PATH ?>/assets/images/sofie-3.jpg" class="hero__photo" data-index="2" alt="">
        <div class="hero__overlay"></div>
    </div>

    <!-- Thumbnail Navigation - Top Right -->
    <nav class="thumbnails">
        <button class="thumbnail active" data-index="0">
            <img src="<?= BASE_PATH ?>/assets/images/sofie-1.jpg" alt="">
        </button>
        <button class="thumbnail" data-index="1">
            <img src="<?= BASE_PATH ?>/assets/images/sofie-2.jpg" alt="">
        </button>
        <button class="thumbnail" data-index="2">
            <img src="<?= BASE_PATH ?>/assets/images/sofie-3.jpg" alt="">
        </button>
    </nav>

    <!-- Main Content - Bottom -->
    <main class="content">
        <?php if ($guestFromUrl): ?>
            <p class="guest-greeting">Kære <?= escape($guestFromUrl['name']) ?></p>
        <?php endif; ?>

        <p class="event-type"><?= escape($event['name']) ?></p>
        <h1 class="hero-name"><?= escape($event['confirmand_name']) ?></h1>

        <?php if ($event['welcome_text']): ?>
            <p class="welcome-text"><?= nl2br(escape($event['welcome_text'])) ?></p>
        <?php endif; ?>

        <div class="info-row">
            <div class="info-block">
                <span class="info-block__label">Dato</span>
                <span class="info-block__value"><?= formatDanishDate($event['event_date']) ?></span>
            </div>
            <?php if ($event['event_time']): ?>
            <div class="info-block">
                <span class="info-block__label">Tid</span>
                <span class="info-block__value">Kl. <?= date('H:i', strtotime($event['event_time'])) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($event['location']): ?>
            <div class="info-block">
                <span class="info-block__label">Sted</span>
                <span class="info-block__value"><?= escape(explode("\n", $event['location'])[0]) ?></span>
            </div>
            <?php endif; ?>

            <div class="code-card">
                <?php if ($error === 'invalid_code'): ?>
                    <div class="alert">Ugyldig kode - prøv igen</div>
                <?php endif; ?>

                <?php if ($guestFromUrl): ?>
                    <?php if ($guestFromUrl['rsvp_status'] === 'pending'): ?>
                        <a href="<?= BASE_PATH ?>/guest/rsvp.php" class="btn btn--primary btn--large">Svar på invitation</a>
                    <?php else: ?>
                        <a href="<?= BASE_PATH ?>/guest/index.php" class="btn btn--primary btn--large">Se invitation</a>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="code-card__title">Indtast din kode</p>
                    <form method="POST" class="code-form">
                        <input type="text"
                               name="guest_code"
                               class="code-input"
                               placeholder="000000"
                               maxlength="6"
                               pattern="[0-9]{6}"
                               inputmode="numeric"
                               autocomplete="off"
                               required>
                        <button type="submit" class="btn btn--primary">OK</button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Diskret admin login -->
            <a href="<?= BASE_PATH ?>/admin/login.php" class="admin-link">⚙️</a>
        </div>
    </main>

    <style>
    .admin-link {
        position: fixed;
        bottom: 1rem;
        right: 1rem;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255,255,255,0.1);
        border-radius: 50%;
        text-decoration: none;
        font-size: 1rem;
        opacity: 0.3;
        transition: opacity 0.3s;
    }
    .admin-link:hover {
        opacity: 1;
        background: rgba(255,255,255,0.2);
    }
    </style>

    <script>
        // Photo switching
        const photos = document.querySelectorAll('.hero__photo');
        const thumbs = document.querySelectorAll('.thumbnail');
        let current = 0;

        function showPhoto(index) {
            photos.forEach((p, i) => p.classList.toggle('active', i === index));
            thumbs.forEach((t, i) => t.classList.toggle('active', i === index));
            current = index;
        }

        thumbs.forEach(thumb => {
            thumb.addEventListener('click', () => {
                showPhoto(parseInt(thumb.dataset.index));
            });
        });

        // Auto-rotate
        setInterval(() => {
            showPhoto((current + 1) % photos.length);
        }, 6000);

        // Code auto-submit
        const input = document.querySelector('.code-input');
        if (input) {
            let done = false;
            input.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '');
                if (this.value.length === 6 && !done) {
                    done = true;
                    this.style.background = 'rgba(139, 168, 136, 0.3)';
                    setTimeout(() => this.form.submit(), 400);
                }
            });
            setTimeout(() => input.focus(), 1500);
        }
    </script>
    <?php endif; ?>
</body>
</html>

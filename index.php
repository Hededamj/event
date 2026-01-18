<?php
/**
 * Event Platform - Landing Page
 * The first impression - elegant, memorable, inviting
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Get the event (for MVP, we get the first one)
$db = getDB();
$stmt = $db->query("SELECT * FROM events ORDER BY id LIMIT 1");
$event = $stmt->fetch();

// If no event exists, show setup message
if (!$event) {
    $showSetup = true;
} else {
    $showSetup = false;
    $theme = $event['theme'] ?? 'girl';
    $error = $_GET['error'] ?? null;
}

// Handle guest code submission
if (!$showSetup && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guest_code'])) {
    $code = preg_replace('/[^0-9]/', '', $_POST['guest_code']);

    if (strlen($code) === 6) {
        $stmt = $db->prepare("SELECT * FROM guests WHERE unique_code = ? AND event_id = ?");
        $stmt->execute([$code, $event['id']]);
        $guest = $stmt->fetch();

        if ($guest) {
            loginGuest($guest['id'], $event['id']);
            redirect('/guest/index.php');
        } else {
            $error = 'invalid_code';
        }
    } else {
        $error = 'invalid_code';
    }
}

// Handle organizer login
if (!$showSetup && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_email'])) {
    $email = trim($_POST['login_email']);
    $password = $_POST['login_password'];

    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND event_id = ?");
    $stmt->execute([$email, $event['id']]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        login($user['id'], $event['id']);
        redirect('/admin/index.php');
    } else {
        $error = 'invalid_credentials';
    }
}

// Format event date beautifully
function formatEventDate($date): string {
    $months = [
        1 => 'januar', 2 => 'februar', 3 => 'marts', 4 => 'april',
        5 => 'maj', 6 => 'juni', 7 => 'juli', 8 => 'august',
        9 => 'september', 10 => 'oktober', 11 => 'november', 12 => 'december'
    ];
    $days = ['søndag', 'mandag', 'tirsdag', 'onsdag', 'torsdag', 'fredag', 'lørdag'];

    $timestamp = strtotime($date);
    $dayName = $days[date('w', $timestamp)];
    $day = date('j', $timestamp);
    $month = $months[(int)date('n', $timestamp)];
    $year = date('Y', $timestamp);

    return ucfirst($dayName) . ' d. ' . $day . '. ' . $month . ' ' . $year;
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $showSetup ? 'Event Platform' : escape($event['name']) ?></title>
    <meta name="description" content="<?= $showSetup ? 'Event Platform' : escape($event['name']) ?>">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">

    <!-- Styles -->
    <link rel="stylesheet" href="/assets/css/main.css">
    <?php if (!$showSetup): ?>
    <link rel="stylesheet" href="/assets/css/theme-<?= escape($theme) ?>.css">
    <?php endif; ?>

    <style>
        /* Landing Page Specific Styles */
        .landing {
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }

        /* Animated background */
        .landing__bg {
            position: fixed;
            inset: 0;
            z-index: -1;
            background: var(--color-bg);
        }

        .landing__bg::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(
                ellipse at 30% 20%,
                var(--color-primary-pale) 0%,
                transparent 50%
            ),
            radial-gradient(
                ellipse at 70% 80%,
                var(--color-accent-pale) 0%,
                transparent 40%
            );
            animation: bgFloat 20s ease-in-out infinite;
        }

        @keyframes bgFloat {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(2%, 1%) rotate(1deg); }
            66% { transform: translate(-1%, 2%) rotate(-1deg); }
        }

        /* Hero Section */
        .hero {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: var(--space-xl) var(--space-md);
            position: relative;
        }

        /* Decorative elements */
        .hero__decoration {
            position: absolute;
            opacity: 0.15;
            pointer-events: none;
        }

        .hero__decoration--1 {
            top: 10%;
            left: 5%;
            font-size: 8rem;
            animation: float 6s ease-in-out infinite;
        }

        .hero__decoration--2 {
            bottom: 15%;
            right: 8%;
            font-size: 6rem;
            animation: float 8s ease-in-out infinite;
            animation-delay: -3s;
        }

        .hero__decoration--3 {
            top: 20%;
            right: 12%;
            font-size: 4rem;
            animation: float 7s ease-in-out infinite;
            animation-delay: -5s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
        }

        /* Main content */
        .hero__content {
            max-width: 600px;
            opacity: 0;
            animation: fadeInUp 1s var(--ease-out-expo) 0.2s forwards;
        }

        .hero__eyebrow {
            font-size: var(--text-xs);
            text-transform: uppercase;
            letter-spacing: 0.2em;
            color: var(--color-accent);
            margin-bottom: var(--space-sm);
            font-weight: 500;
        }

        .hero__title {
            font-size: var(--text-display);
            font-weight: 400;
            color: var(--color-primary-deep);
            margin-bottom: var(--space-xs);
            line-height: 0.95;
        }

        .hero__title em {
            font-style: italic;
            color: var(--color-text);
        }

        .hero__subtitle {
            font-family: 'Cormorant Garamond', serif;
            font-size: var(--text-xl);
            font-weight: 400;
            color: var(--color-text-soft);
            margin-bottom: var(--space-sm);
        }

        .hero__date {
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
            padding: var(--space-xs) var(--space-md);
            background: var(--color-surface);
            border-radius: var(--radius-full);
            box-shadow: var(--shadow-sm);
            font-family: 'Cormorant Garamond', serif;
            font-size: var(--text-lg);
            color: var(--color-text);
            margin-bottom: var(--space-md);
        }

        .hero__date-icon {
            color: var(--color-accent);
        }

        .hero__location {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-2xs);
            color: var(--color-text-muted);
            font-size: var(--text-sm);
            margin-bottom: var(--space-lg);
        }

        .hero__welcome {
            max-width: 480px;
            margin: 0 auto var(--space-xl);
            line-height: 1.8;
            color: var(--color-text-soft);
        }

        /* Entry cards */
        .entry-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: var(--space-md);
            width: 100%;
            max-width: 640px;
            opacity: 0;
            animation: fadeInUp 1s var(--ease-out-expo) 0.5s forwards;
        }

        .entry-card {
            background: var(--color-surface);
            border-radius: var(--radius-xl);
            padding: var(--space-md);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--color-border-soft);
            transition: all var(--duration-normal) var(--ease-out-expo);
            position: relative;
            overflow: hidden;
        }

        .entry-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--color-primary), var(--color-accent));
            transform: scaleX(0);
            transition: transform var(--duration-normal) var(--ease-out-expo);
        }

        .entry-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .entry-card:hover::before {
            transform: scaleX(1);
        }

        .entry-card:focus-within {
            box-shadow: var(--shadow-lg), 0 0 0 3px var(--color-primary-soft);
        }

        .entry-card__header {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
            margin-bottom: var(--space-sm);
        }

        .entry-card__icon {
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--color-primary-pale);
            border-radius: var(--radius-md);
            font-size: 1.25rem;
        }

        .entry-card--organizer .entry-card__icon {
            background: var(--color-bg-subtle);
        }

        .entry-card__title {
            font-family: 'Cormorant Garamond', serif;
            font-size: var(--text-lg);
            font-weight: 500;
            color: var(--color-text);
        }

        .entry-card__desc {
            font-size: var(--text-sm);
            color: var(--color-text-muted);
            margin-bottom: var(--space-sm);
        }

        /* Alert styling */
        .hero__alert {
            max-width: 400px;
            margin: 0 auto var(--space-md);
            opacity: 0;
            animation: fadeInUp 0.5s var(--ease-out-expo) forwards;
        }

        /* Login form (hidden by default) */
        .login-form {
            display: none;
            margin-top: var(--space-sm);
            padding-top: var(--space-sm);
            border-top: 1px solid var(--color-border-soft);
        }

        .login-form.active {
            display: block;
            animation: fadeInUp 0.3s var(--ease-out-expo);
        }

        /* Footer */
        .landing__footer {
            text-align: center;
            padding: var(--space-md);
            color: var(--color-text-muted);
            font-size: var(--text-xs);
            opacity: 0;
            animation: fadeIn 1s var(--ease-out-expo) 1s forwards;
        }

        /* Setup screen */
        .setup-screen {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-xl) var(--space-md);
            background: linear-gradient(135deg, #FFFBF7 0%, #F7F9FC 100%);
        }

        .setup-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: var(--space-xl);
            box-shadow: var(--shadow-lg);
            max-width: 500px;
            width: 100%;
            text-align: center;
        }

        .setup-card__icon {
            font-size: 4rem;
            margin-bottom: var(--space-md);
        }

        /* Responsive */
        @media (max-width: 640px) {
            .entry-cards {
                grid-template-columns: 1fr;
            }

            .hero__decoration {
                display: none;
            }
        }
    </style>
</head>
<body>
    <?php if ($showSetup): ?>
    <!-- Setup Screen -->
    <div class="setup-screen">
        <div class="setup-card">
            <div class="setup-card__icon">✨</div>
            <h1 class="h2 mb-sm">Velkommen til Event Platform</h1>
            <p class="lead mb-md">
                Der er endnu ikke oprettet et event. Kør <code>database/schema.sql</code> og <code>database/seed.sql</code> for at komme i gang.
            </p>
            <p class="text-muted small">
                Derefter kan du logge ind som administrator med:<br>
                <strong>admin@example.com</strong> / <strong>password</strong>
            </p>
        </div>
    </div>

    <?php else: ?>
    <!-- Landing Page -->
    <div class="landing">
        <div class="landing__bg"></div>

        <main class="hero">
            <!-- Decorative elements -->
            <span class="hero__decoration hero__decoration--1">✦</span>
            <span class="hero__decoration hero__decoration--2">❋</span>
            <span class="hero__decoration hero__decoration--3">✧</span>

            <div class="hero__content">
                <p class="hero__eyebrow">Du er inviteret til</p>

                <h1 class="hero__title">
                    <?= escape($event['confirmand_name']) ?><em>s</em>
                </h1>

                <p class="hero__subtitle"><?= escape($event['name']) ?></p>

                <div class="hero__date">
                    <span class="hero__date-icon">✦</span>
                    <span><?= formatEventDate($event['event_date']) ?></span>
                    <?php if ($event['event_time']): ?>
                        <span>kl. <?= date('H:i', strtotime($event['event_time'])) ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($event['location']): ?>
                    <p class="hero__location">
                        <span>📍</span>
                        <span><?= escape($event['location']) ?></span>
                    </p>
                <?php endif; ?>

                <?php if ($event['welcome_text']): ?>
                    <p class="hero__welcome">
                        <?= nl2br(escape($event['welcome_text'])) ?>
                    </p>
                <?php endif; ?>

                <?php if ($error === 'invalid_code'): ?>
                    <div class="alert alert--error hero__alert">
                        <span>⚠</span>
                        <span>Koden er ugyldig. Tjek din invitation og prøv igen.</span>
                    </div>
                <?php elseif ($error === 'invalid_credentials'): ?>
                    <div class="alert alert--error hero__alert">
                        <span>⚠</span>
                        <span>Forkert email eller adgangskode.</span>
                    </div>
                <?php endif; ?>

                <div class="entry-cards">
                    <!-- Guest Entry -->
                    <div class="entry-card">
                        <div class="entry-card__header">
                            <div class="entry-card__icon">🎉</div>
                            <h2 class="entry-card__title">Gæst</h2>
                        </div>
                        <p class="entry-card__desc">
                            Indtast din personlige kode fra invitationen
                        </p>
                        <form method="POST" id="guest-form">
                            <div class="form-group">
                                <input type="text"
                                       name="guest_code"
                                       class="form-input code-input"
                                       placeholder="000000"
                                       maxlength="6"
                                       pattern="[0-9]{6}"
                                       inputmode="numeric"
                                       autocomplete="off"
                                       required
                                       aria-label="Din personlige kode">
                            </div>
                            <button type="submit" class="btn btn--primary btn--block">
                                Fortsæt
                            </button>
                        </form>
                    </div>

                    <!-- Organizer Entry -->
                    <div class="entry-card entry-card--organizer">
                        <div class="entry-card__header">
                            <div class="entry-card__icon">⚙️</div>
                            <h2 class="entry-card__title">Arrangør</h2>
                        </div>
                        <p class="entry-card__desc">
                            Log ind for at administrere eventet
                        </p>
                        <button type="button"
                                class="btn btn--secondary btn--block"
                                onclick="toggleLoginForm()"
                                id="login-toggle">
                            Log ind
                        </button>

                        <form method="POST" class="login-form" id="login-form">
                            <div class="form-group">
                                <input type="email"
                                       name="login_email"
                                       class="form-input"
                                       placeholder="Email"
                                       required
                                       autocomplete="email">
                            </div>
                            <div class="form-group">
                                <input type="password"
                                       name="login_password"
                                       class="form-input"
                                       placeholder="Adgangskode"
                                       required
                                       autocomplete="current-password">
                            </div>
                            <button type="submit" class="btn btn--primary btn--block">
                                Log ind
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>

        <footer class="landing__footer">
            <p>Lavet med ❤️ til <?= escape($event['confirmand_name']) ?></p>
        </footer>
    </div>

    <script>
        // Auto-format guest code input
        const codeInput = document.querySelector('.code-input');
        if (codeInput) {
            codeInput.addEventListener('input', function(e) {
                // Only allow numbers
                this.value = this.value.replace(/[^0-9]/g, '');
            });

            // Auto-submit when 6 digits entered
            codeInput.addEventListener('keyup', function(e) {
                if (this.value.length === 6) {
                    // Small delay for visual feedback
                    setTimeout(() => {
                        this.form.submit();
                    }, 150);
                }
            });

            // Focus code input on page load
            setTimeout(() => {
                codeInput.focus();
            }, 800);
        }

        // Toggle login form
        function toggleLoginForm() {
            const form = document.getElementById('login-form');
            const toggle = document.getElementById('login-toggle');

            form.classList.toggle('active');

            if (form.classList.contains('active')) {
                toggle.style.display = 'none';
                form.querySelector('input[type="email"]').focus();
            } else {
                toggle.style.display = 'block';
            }
        }

        // Show login form if there was a credentials error
        <?php if ($error === 'invalid_credentials'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            toggleLoginForm();
        });
        <?php endif; ?>
    </script>
    <?php endif; ?>
</body>
</html>

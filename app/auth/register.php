<?php
/**
 * Account Registration Page - Nordic Design (Mobile-First)
 */
require_once __DIR__ . '/../../config/saas.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth-account.php';

// Redirect if already logged in
if (isAccountLoggedIn()) {
    redirect('/app/dashboard.php');
}

$error = '';
$success = '';
$formData = [
    'name' => '',
    'email' => '',
    'phone' => ''
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyAccountCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Ugyldig anmodning. Prøv igen.';
    } else {
        $formData['name'] = trim($_POST['name'] ?? '');
        $formData['email'] = trim($_POST['email'] ?? '');
        $formData['phone'] = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        // Validation
        if (empty($formData['name'])) {
            $error = 'Indtast venligst dit navn.';
        } elseif (empty($formData['email'])) {
            $error = 'Indtast venligst din email.';
        } elseif (empty($password)) {
            $error = 'Indtast venligst en adgangskode.';
        } elseif (strlen($password) < 8) {
            $error = 'Adgangskoden skal være mindst 8 tegn.';
        } elseif ($password !== $passwordConfirm) {
            $error = 'Adgangskoderne matcher ikke.';
        } else {
            // Try to register
            $result = registerAccount(
                $formData['email'],
                $password,
                $formData['name'],
                $formData['phone'] ?: null
            );

            if ($result['success']) {
                // Auto-login after registration
                accountLogin($result['account_id'], $formData['email'], $formData['name']);
                redirect('/app/dashboard.php?welcome=1');
            } else {
                $error = $result['error'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Opret konto - PartyParart</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/design-system.css">
    <style>
        /* =============================================
           MOBILE-FIRST: Base styles (< 768px)
           ============================================= */
        body {
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
        }

        .auth-layout {
            display: flex;
            flex-direction: column;
            width: 100%;
            min-height: 100vh;
            min-height: 100dvh;
        }

        /* Mobile branded header (replaces hidden visual panel) */
        .auth-visual {
            padding: 24px 20px;
            text-align: center;
            color: var(--text-on-dark);
            position: relative;
            overflow: hidden;
        }

        /* Photo mosaic background */
        .visual-mosaic {
            position: absolute;
            inset: 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 1fr 1fr;
            gap: 3px;
        }

        .visual-mosaic-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .visual-mosaic-img:nth-child(1) { object-position: center 20%; }
        .visual-mosaic-img:nth-child(2) { object-position: center 30%; }
        .visual-mosaic-img:nth-child(3) { object-position: center 40%; }
        .visual-mosaic-img:nth-child(4) { object-position: center 25%; }

        /* Dark overlay on photos */
        .auth-visual::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(160deg, rgba(122,139,114,0.88) 0%, rgba(44,44,44,0.82) 100%);
            z-index: 1;
        }

        .visual-content {
            position: relative;
            z-index: 2;
        }

        .visual-icon {
            width: 48px;
            height: 48px;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(8px);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
        }

        .visual-icon svg {
            width: 24px;
            height: 24px;
        }

        .visual-title {
            font-family: var(--font-display);
            font-size: 22px;
            font-weight: 400;
            margin-bottom: 4px;
            line-height: 1.2;
        }

        .visual-text {
            font-size: 14px;
            opacity: 0.9;
            line-height: 1.5;
        }

        /* Features list - hidden on mobile, shown on desktop */
        .features-list {
            display: none;
        }

        /* Photo accent strip on mobile */
        .visual-photo-strip {
            display: flex;
            gap: 8px;
            margin-top: 16px;
            justify-content: center;
        }

        .visual-photo-strip img {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            object-fit: cover;
            border: 2px solid rgba(255,255,255,0.3);
        }

        /* Form section */
        .auth-form-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 24px 20px 32px;
            background: var(--surface);
        }

        .auth-header {
            margin-bottom: 20px;
        }

        .auth-logo {
            font-family: var(--font-display);
            font-size: 24px;
            font-weight: 500;
            color: var(--text);
            text-decoration: none;
            display: inline-block;
            margin-bottom: 16px;
        }

        .auth-logo span {
            color: var(--accent-dark);
        }

        .auth-title {
            font-family: var(--font-display);
            font-size: 26px;
            font-weight: 400;
            color: var(--text);
            margin-bottom: 6px;
        }

        .auth-subtitle {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .auth-card {
            background: var(--surface-card);
            border-radius: var(--radius-lg);
            padding: 24px;
            box-shadow:
                0 1px 2px rgba(0,0,0,0.04),
                0 4px 16px rgba(0,0,0,0.04);
            animation: fadeIn 0.6s var(--ease-out);
        }

        /* Error/success messages */
        .error-message {
            background: var(--error-light);
            border: 1px solid rgba(193, 75, 75, 0.2);
            color: var(--error);
            padding: 12px 14px;
            border-radius: var(--radius-md);
            font-size: 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .error-message svg {
            flex-shrink: 0;
        }

        /* Form elements */
        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 6px;
        }

        .form-label .optional {
            font-weight: 400;
            color: var(--text-secondary);
        }

        .form-input {
            width: 100%;
            padding: 12px 14px;
            font-family: var(--font-body);
            font-size: 16px; /* 16px prevents iOS zoom */
            border: 1.5px solid var(--border);
            border-radius: var(--radius-md);
            background: var(--surface-card);
            color: var(--text);
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
            -webkit-appearance: none;
            appearance: none;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-light);
        }

        .form-input::placeholder {
            color: var(--text-secondary);
            opacity: 0.6;
        }

        .form-hint {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        /* Buttons */
        .btn-submit {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 14px 24px;
            font-family: var(--font-body);
            font-size: 15px;
            font-weight: 600;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: background 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
            background: var(--text);
            color: var(--text-on-dark);
            -webkit-appearance: none;
            appearance: none;
        }

        .btn-submit:hover {
            background: var(--text-secondary);
        }

        .btn-submit:active {
            transform: scale(0.98);
        }

        .btn-submit svg {
            width: 18px;
            height: 18px;
        }

        .terms-text {
            font-size: 12px;
            color: var(--text-secondary);
            text-align: center;
            margin-top: 14px;
            line-height: 1.6;
        }

        .terms-text a {
            color: var(--accent-dark);
            text-decoration: none;
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 20px 0;
            color: var(--text-secondary);
            font-size: 13px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .divider span {
            padding: 0 14px;
        }

        .alt-link {
            display: block;
            text-align: center;
            padding: 12px 20px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-md);
            color: var(--text);
            text-decoration: none;
            font-weight: 500;
            font-size: 15px;
            transition: border-color 0.15s ease, background 0.15s ease;
        }

        .alt-link:hover {
            border-color: var(--accent);
            background: var(--accent-light);
        }

        .form-footer {
            margin-top: 20px;
            text-align: center;
        }

        .form-footer a {
            color: var(--accent-dark);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* =============================================
           TABLET: >= 768px
           ============================================= */
        @media (min-width: 768px) {
            .auth-visual {
                padding: 32px;
            }

            .visual-mosaic {
                grid-template-columns: 1fr 1fr 1fr;
                grid-template-rows: 1fr;
            }

            .visual-photo-strip img {
                width: 64px;
                height: 64px;
            }

            .visual-icon {
                width: 56px;
                height: 56px;
                border-radius: 18px;
            }

            .visual-icon svg {
                width: 28px;
                height: 28px;
            }

            .visual-title {
                font-size: 26px;
            }

            .auth-form-section {
                padding: 40px;
                align-items: center;
                justify-content: center;
            }

            .auth-header {
                margin-bottom: 28px;
                max-width: 440px;
                width: 100%;
            }

            .auth-logo {
                font-size: 28px;
            }

            .auth-title {
                font-size: 32px;
            }

            .auth-subtitle {
                font-size: 15px;
            }

            .auth-card {
                padding: 36px;
                max-width: 440px;
                width: 100%;
            }

            .form-group {
                margin-bottom: 20px;
            }

            .form-input {
                padding: 14px 16px;
            }

            .btn-submit {
                padding: 16px 32px;
            }
        }

        /* =============================================
           DESKTOP: >= 1024px — Split layout
           ============================================= */
        @media (min-width: 1024px) {
            .auth-layout {
                flex-direction: row;
            }

            .auth-visual {
                width: 45%;
                min-height: 100vh;
                min-height: 100dvh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 48px;
                position: sticky;
                top: 0;
            }

            .visual-mosaic {
                grid-template-columns: 1fr 1fr;
                grid-template-rows: 1fr 1fr;
            }

            .visual-photo-strip {
                display: none;
            }

            .visual-icon {
                width: 80px;
                height: 80px;
                border-radius: 24px;
                margin-bottom: 32px;
                backdrop-filter: blur(12px);
            }

            .visual-icon svg {
                width: 40px;
                height: 40px;
            }

            .visual-title {
                font-size: 32px;
                margin-bottom: 16px;
                text-shadow: 0 2px 12px rgba(0,0,0,0.15);
            }

            .visual-text {
                font-size: 16px;
                max-width: 320px;
                margin: 0 auto;
                line-height: 1.6;
            }

            .features-list {
                display: block;
                margin-top: 32px;
                text-align: left;
                max-width: 280px;
                margin-left: auto;
                margin-right: auto;
            }

            .feature-item {
                display: flex;
                align-items: center;
                gap: 12px;
                font-size: 15px;
                opacity: 0.9;
                margin-bottom: 12px;
            }

            .feature-item svg {
                width: 20px;
                height: 20px;
                flex-shrink: 0;
            }

            .auth-form-section {
                width: 55%;
                min-height: 100vh;
                min-height: 100dvh;
                padding: 48px 64px;
            }

            .auth-header {
                margin-bottom: 32px;
            }

            .auth-logo {
                margin-bottom: 24px;
            }

            .auth-title {
                font-size: 36px;
            }

            .btn-submit:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 24px rgba(44,44,44,0.2);
            }
        }
    </style>
</head>
<body>
    <div class="auth-layout">
        <div class="auth-visual">
            <div class="visual-mosaic">
                <picture><source srcset="/billeder/kort-konfirmation.webp" type="image/webp"><img class="visual-mosaic-img" src="/billeder/kort-konfirmation.jpg" alt="" loading="eager"></picture>
                <picture><source srcset="/billeder/kort-bryllup.webp" type="image/webp"><img class="visual-mosaic-img" src="/billeder/kort-bryllup.jpg" alt="" loading="eager"></picture>
                <picture><source srcset="/billeder/kort-foedselsdag.webp" type="image/webp"><img class="visual-mosaic-img" src="/billeder/kort-foedselsdag.jpg" alt="" loading="eager"></picture>
                <picture><source srcset="/billeder/kort-jubileum.webp" type="image/webp"><img class="visual-mosaic-img" src="/billeder/kort-jubileum.jpg" alt="" loading="eager"></picture>
            </div>
            <div class="visual-content">
                <div class="visual-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                    </svg>
                </div>
                <h2 class="visual-title">Kom godt i gang</h2>
                <p class="visual-text">
                    Opret din gratis konto og begynd at planlægge dit arrangement med stil.
                </p>
                <div class="visual-photo-strip">
                    <picture><source srcset="/billeder/kort-studenterfest.webp" type="image/webp"><img src="/billeder/kort-studenterfest.jpg" alt="Studenterfest"></picture>
                    <picture><source srcset="/billeder/kort-temafest.webp" type="image/webp"><img src="/billeder/kort-temafest.jpg" alt="Temafest"></picture>
                    <picture><source srcset="/billeder/kort-halloween.webp" type="image/webp"><img src="/billeder/kort-halloween.jpg" alt="Halloween"></picture>
                </div>
                <div class="features-list">
                    <div class="feature-item">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span>Op til 30 gæster</span>
                    </div>
                    <div class="feature-item">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span>Gæstehåndtering og RSVP</span>
                    </div>
                    <div class="feature-item">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span>Ønskeliste og menu</span>
                    </div>
                    <div class="feature-item">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span>Fotogalleri</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="auth-form-section">
            <div class="auth-header">
                <a href="/" class="auth-logo">Party<span>Parart</span></a>
                <h1 class="auth-title">Opret din konto</h1>
                <p class="auth-subtitle">Kom i gang med at planlægge dit arrangement</p>
            </div>

            <div class="auth-card">
                <?php if ($error): ?>
                    <div class="error-message">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?= accountCsrfField() ?>

                    <div class="form-group">
                        <label class="form-label" for="name">Dit navn</label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            class="form-input"
                            placeholder="Fornavn Efternavn"
                            value="<?= htmlspecialchars($formData['name']) ?>"
                            required
                            autofocus
                            autocomplete="name"
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="email">Email</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-input"
                            placeholder="din@email.dk"
                            value="<?= htmlspecialchars($formData['email']) ?>"
                            required
                            autocomplete="email"
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="phone">Telefon <span class="optional">(valgfrit)</span></label>
                        <input
                            type="tel"
                            id="phone"
                            name="phone"
                            class="form-input"
                            placeholder="12 34 56 78"
                            value="<?= htmlspecialchars($formData['phone']) ?>"
                            autocomplete="tel"
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">Adgangskode</label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-input"
                            placeholder="Mindst 8 tegn"
                            required
                            minlength="8"
                            autocomplete="new-password"
                        >
                        <div class="form-hint">Mindst 8 tegn</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password_confirm">Bekræft adgangskode</label>
                        <input
                            type="password"
                            id="password_confirm"
                            name="password_confirm"
                            class="form-input"
                            placeholder="Gentag adgangskode"
                            required
                            autocomplete="new-password"
                        >
                    </div>

                    <button type="submit" class="btn-submit">
                        Opret konto
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>

                    <p class="terms-text">
                        Ved at oprette en konto accepterer du vores
                        <a href="/legal/vilkaar.php">Vilkår og betingelser</a> og
                        <a href="/legal/privatlivspolitik.php">Privatlivspolitik</a>.
                    </p>
                </form>

                <div class="divider"><span>eller</span></div>

                <a href="/app/auth/login.php" class="alt-link">Log ind med eksisterende konto</a>

                <div class="form-footer">
                    <a href="/">Tilbage til forsiden</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

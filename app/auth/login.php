<?php
/**
 * Account Login Page - Nordic Design (Mobile-First)
 */
require_once __DIR__ . '/../../config/saas.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth-account.php';

// Redirect if already logged in
if (isAccountLoggedIn()) {
    redirect('/app/dashboard.php');
}

$error = '';
$email = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyAccountCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Ugyldig anmodning. Prøv igen.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Udfyld venligst email og adgangskode.';
        } else {
            // Check rate limiting before attempting login
            $lockoutMinutes = isLoginRateLimited($email);
            if ($lockoutMinutes > 0) {
                $error = "For mange loginforsøg. Prøv igen om $lockoutMinutes minut" . ($lockoutMinutes > 1 ? 'ter' : '') . '.';
            } else {
                $result = verifyAccountPassword($email, $password);

                if ($result === null) {
                    recordLoginAttempt($email);
                    $error = 'Forkert email eller adgangskode.';
                } elseif (isset($result['error']) && $result['error'] === 'account_inactive') {
                    $error = 'Din konto er deaktiveret. Kontakt support.';
                } else {
                    // Successful login - clear failed attempts
                    clearLoginAttempts($email);
                    accountLogin($result['id'], $result['email'], $result['name']);

                    // Redirect to return URL or dashboard
                    $returnUrl = $_GET['return'] ?? '/app/dashboard.php';
                    // Validate return URL is local (reject //evil.com and non-slash starts)
                    if (!preg_match('#^/[^/]#', $returnUrl)) {
                        $returnUrl = '/app/dashboard.php';
                    }
                    redirect($returnUrl);
                }
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
    <title>Log ind - PartyParart</title>
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

        /* Mobile branded header */
        .auth-visual {
            background: linear-gradient(160deg, var(--accent) 0%, var(--accent-dark) 100%);
            padding: 24px 20px;
            text-align: center;
            color: var(--text-on-dark);
            position: relative;
            overflow: hidden;
        }

        .auth-visual::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 20% 80%, rgba(255,255,255,0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255,255,255,0.08) 0%, transparent 40%);
        }

        .visual-content {
            position: relative;
            z-index: 1;
        }

        .visual-icon {
            width: 48px;
            height: 48px;
            background: rgba(255,255,255,0.15);
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

        .form-input {
            width: 100%;
            padding: 12px 14px;
            font-family: var(--font-body);
            font-size: 16px;
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

        .remember-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: var(--text-secondary);
            cursor: pointer;
        }

        .checkbox-label input {
            width: 18px;
            height: 18px;
            accent-color: var(--accent);
        }

        .form-link {
            color: var(--accent-dark);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

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

            .visual-icon {
                width: 80px;
                height: 80px;
                border-radius: 24px;
                margin-bottom: 32px;
            }

            .visual-icon svg {
                width: 40px;
                height: 40px;
            }

            .visual-title {
                font-size: 32px;
                margin-bottom: 16px;
            }

            .visual-text {
                font-size: 16px;
                max-width: 320px;
                margin: 0 auto;
                line-height: 1.6;
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
            <div class="visual-content">
                <div class="visual-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 15.546c-.523 0-1.046.151-1.5.454a2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.701 2.701 0 00-1.5-.454M9 6v2m3-2v2m3-2v2M9 3h.01M12 3h.01M15 3h.01M21 21v-7a2 2 0 00-2-2H5a2 2 0 00-2 2v7h18zm-3-9v-2a2 2 0 00-2-2H8a2 2 0 00-2 2v2h12z"></path>
                    </svg>
                </div>
                <h2 class="visual-title">Skab uforglemmelige øjeblikke</h2>
                <p class="visual-text">
                    Planlæg din fest med stil. Gæsteliste, ønskeliste, program og billeder - alt samlet ét sted.
                </p>
            </div>
        </div>

        <div class="auth-form-section">
            <div class="auth-header">
                <a href="/" class="auth-logo">Party<span>Parart</span></a>
                <h1 class="auth-title">Velkommen tilbage</h1>
                <p class="auth-subtitle">Log ind for at administrere dine arrangementer</p>
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
                        <label class="form-label" for="email">Email</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-input"
                            placeholder="din@email.dk"
                            value="<?= htmlspecialchars($email) ?>"
                            required
                            autofocus
                            autocomplete="email"
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">Adgangskode</label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-input"
                            placeholder="Din adgangskode"
                            required
                            autocomplete="current-password"
                        >
                    </div>

                    <div class="remember-row">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remember" value="1">
                            Husk mig
                        </label>
                        <a href="/app/auth/forgot-password.php" class="form-link">Glemt adgangskode?</a>
                    </div>

                    <button type="submit" class="btn-submit">
                        Log ind
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </form>

                <div class="divider"><span>eller</span></div>

                <a href="/register" class="alt-link">Opret ny konto</a>

                <div class="form-footer">
                    <a href="/">Tilbage til forsiden</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

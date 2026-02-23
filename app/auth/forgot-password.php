<?php
/**
 * Forgot Password Page - Nordic Design (Mobile-First)
 */
require_once __DIR__ . '/../../config/saas.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth-account.php';
require_once __DIR__ . '/../../includes/email-service.php';

// Redirect if already logged in
if (isAccountLoggedIn()) {
    redirect('/app/dashboard.php');
}

$error = '';
$success = '';
$email = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyAccountCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Ugyldig anmodning. Prøv igen.';
    } else {
        $email = trim($_POST['email'] ?? '');

        if (empty($email)) {
            $error = 'Indtast venligst din email.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Indtast en gyldig email-adresse.';
        } else {
            $token = createPasswordReset($email);

            // Always show success message (don't reveal if email exists)
            $success = 'Hvis der findes en konto med denne email, har vi sendt instruktioner til at nulstille din adgangskode.';

            if ($token) {
                $emailService = getEmailService();
                $result = $emailService->sendPasswordReset($email, $token);
                if (!$result['success']) {
                    error_log('Failed to send password reset email to ' . $email . ': ' . ($result['error'] ?? 'unknown'));
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
    <title>Glemt adgangskode - PartyParart</title>
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
            line-height: 1.5;
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

        .success-message {
            background: var(--success-light);
            border: 1px solid rgba(61, 139, 61, 0.2);
            color: var(--success);
            padding: 12px 14px;
            border-radius: var(--radius-md);
            font-size: 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .success-message svg {
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

        .back-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 24px;
            color: var(--accent-dark);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

        .back-link svg {
            width: 16px;
            height: 16px;
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                    </svg>
                </div>
                <h2 class="visual-title">Ingen bekymring</h2>
                <p class="visual-text">
                    Det sker for os alle. Indtast din email, og vi sender dig et link til at nulstille din adgangskode.
                </p>
            </div>
        </div>

        <div class="auth-form-section">
            <div class="auth-header">
                <a href="/" class="auth-logo">Party<span>Parart</span></a>
                <h1 class="auth-title">Nulstil adgangskode</h1>
                <p class="auth-subtitle">Indtast din email, og vi sender dig instruktioner til at nulstille din adgangskode.</p>
            </div>

            <div class="auth-card">
                <?php if ($error): ?>
                    <div class="error-message">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="success-message">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php else: ?>
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

                        <button type="submit" class="btn-submit">
                            Send nulstillingslink
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        </button>
                    </form>
                <?php endif; ?>

                <a href="/app/auth/login.php" class="back-link">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Tilbage til login
                </a>
            </div>
        </div>
    </div>
</body>
</html>

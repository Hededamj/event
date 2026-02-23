<?php
/**
 * Forgot Password Page - Nordic Design
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
        body {
            min-height: 100vh;
            display: flex;
        }

        .login-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            width: 100%;
            min-height: 100vh;
        }

        @media (max-width: 900px) {
            .login-layout {
                grid-template-columns: 1fr;
            }
            .login-visual {
                display: none;
            }
        }

        /* Left side - Visual */
        .login-visual {
            background: linear-gradient(160deg, var(--accent) 0%, var(--accent-dark) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px;
            position: relative;
            overflow: hidden;
        }

        .login-visual::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 20% 80%, rgba(255,255,255,0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255,255,255,0.08) 0%, transparent 40%);
        }

        .visual-content {
            text-align: center;
            color: var(--text-on-dark);
            position: relative;
            z-index: 1;
        }

        .visual-icon {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.15);
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 32px;
        }

        .visual-icon svg {
            width: 40px;
            height: 40px;
        }

        .visual-title {
            font-family: var(--font-display);
            font-size: 32px;
            font-weight: 400;
            margin-bottom: 16px;
            line-height: 1.2;
        }

        .visual-text {
            font-size: 16px;
            opacity: 0.9;
            max-width: 320px;
            line-height: 1.6;
        }

        /* Right side - Form */
        .login-form-section {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: clamp(32px, 8vw, 80px);
            background: var(--surface);
        }

        .login-header {
            margin-bottom: 40px;
        }

        .login-logo {
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 500;
            color: var(--text);
            text-decoration: none;
            display: inline-block;
            margin-bottom: 32px;
        }

        .login-logo span {
            color: var(--accent-dark);
        }

        .login-title {
            font-family: var(--font-display);
            font-size: clamp(28px, 4vw, 36px);
            font-weight: 400;
            color: var(--text);
            margin-bottom: 8px;
        }

        .login-subtitle {
            font-size: 15px;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .login-card {
            background: var(--surface-card);
            border-radius: var(--radius-lg);
            padding: 40px;
            box-shadow:
                0 1px 2px rgba(0,0,0,0.04),
                0 4px 16px rgba(0,0,0,0.04);
            max-width: 440px;
            animation: fadeIn 0.6s var(--ease-out);
        }

        .error-message {
            background: var(--error-light);
            border: 1px solid #F5D5D5;
            color: var(--error);
            padding: 14px 18px;
            border-radius: var(--radius-md);
            font-size: 14px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .success-message {
            background: var(--success-light);
            border: 1px solid #A7D9A7;
            color: var(--success);
            padding: 14px 18px;
            border-radius: var(--radius-md);
            font-size: 14px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 10px;
        }

        .form-input {
            width: 100%;
            padding: 16px 18px;
            font-family: var(--font-body);
            font-size: 15px;
            border: 2px solid var(--border);
            border-radius: var(--radius-md);
            background: var(--surface-card);
            color: var(--text);
            transition: all 0.3s var(--ease-out);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 4px var(--accent-light);
        }

        .form-input::placeholder {
            color: #A8A39B;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 16px 32px;
            font-family: var(--font-body);
            font-size: 15px;
            font-weight: 600;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s var(--ease-out);
            width: 100%;
        }

        .btn-primary {
            background: var(--text);
            color: var(--text-on-dark);
        }

        .btn-primary:hover {
            background: var(--text-secondary);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(44,44,44,0.2);
        }

        .back-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 28px;
            color: var(--accent-dark);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: var(--text);
        }

        .back-link svg {
            width: 16px;
            height: 16px;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="login-layout">
        <div class="login-visual">
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

        <div class="login-form-section">
            <div class="login-header">
                <a href="/" class="login-logo">Party<span>Parart</span></a>
                <h1 class="login-title">Nulstil adgangskode</h1>
                <p class="login-subtitle">Indtast din email, og vi sender dig instruktioner til at nulstille din adgangskode.</p>
            </div>

            <div class="login-card">
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
                            >
                        </div>

                        <button type="submit" class="btn btn-primary">
                            Send nulstillingslink
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
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

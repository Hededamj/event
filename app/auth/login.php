<?php
/**
 * Account Login Page - Nordic Design
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
            color: var(--white);
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
        }

        .login-card {
            background: var(--surface-card);
            border-radius: var(--radius-lg);
            padding: 40px;
            box-shadow:
                0 1px 2px rgba(0,0,0,0.04),
                0 4px 16px rgba(0,0,0,0.04);
            max-width: 440px;
        }

        .error-message {
            background: #FDF2F2;
            border: 1px solid #F5D5D5;
            color: var(--error);
            padding: 14px 18px;
            border-radius: 14px;
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
            border-radius: 14px;
            background: var(--white);
            color: var(--text);
            transition: all 0.3s var(--ease-out);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(168, 181, 160, 0.15);
        }

        .form-input::placeholder {
            color: #A8A39B;
        }

        .remember-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 10px;
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
            transition: color 0.2s;
        }

        .form-link:hover {
            color: var(--text);
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
            border-radius: 14px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s var(--ease-out);
            width: 100%;
        }

        .btn-primary {
            background: var(--text);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--text-secondary);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(44,44,44,0.2);
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 28px 0;
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
            padding: 0 16px;
        }

        .register-link {
            display: block;
            text-align: center;
            padding: 16px 24px;
            border: 2px solid var(--border);
            border-radius: 14px;
            color: var(--text);
            text-decoration: none;
            font-weight: 500;
            font-size: 15px;
            transition: all 0.3s var(--ease-out);
        }

        .register-link:hover {
            border-color: var(--accent);
            background: var(--surface);
        }

        .form-footer {
            margin-top: 28px;
            text-align: center;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-card {
            animation: fadeIn 0.6s var(--ease-out);
        }
    </style>
</head>
<body>
    <div class="login-layout">
        <div class="login-visual">
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

        <div class="login-form-section">
            <div class="login-header">
                <a href="/" class="login-logo">Party<span>Parart</span></a>
                <h1 class="login-title">Velkommen tilbage</h1>
                <p class="login-subtitle">Log ind for at administrere dine arrangementer</p>
            </div>

            <div class="login-card">
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
                        >
                    </div>

                    <div class="remember-row">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remember" value="1">
                            Husk mig
                        </label>
                        <a href="/app/auth/forgot-password.php" class="form-link">Glemt adgangskode?</a>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Log ind
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </form>

                <div class="divider"><span>eller</span></div>

                <a href="/register" class="register-link">Opret ny konto</a>

                <div class="form-footer">
                    <a href="/" class="form-link">Tilbage til forsiden</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

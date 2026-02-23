<?php
/**
 * Account Registration Page - Nordic Design
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

        .features-list {
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

        /* Right side - Form */
        .login-form-section {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: clamp(32px, 6vw, 64px);
            background: var(--surface);
        }

        .login-header {
            margin-bottom: 32px;
        }

        .login-logo {
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 500;
            color: var(--text);
            text-decoration: none;
            display: inline-block;
            margin-bottom: 24px;
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
            padding: 36px;
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

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 8px;
        }

        .form-label .optional {
            font-weight: 400;
            color: var(--text-secondary);
        }

        .form-input {
            width: 100%;
            padding: 14px 16px;
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

        .form-hint {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 4px;
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

        .terms-text {
            font-size: 12px;
            color: var(--text-secondary);
            text-align: center;
            margin-top: 16px;
            line-height: 1.6;
        }

        .terms-text a {
            color: var(--accent-dark);
            text-decoration: none;
        }

        .terms-text a:hover {
            color: var(--text);
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 24px 0;
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

        .login-link {
            display: block;
            text-align: center;
            padding: 14px 24px;
            border: 2px solid var(--border);
            border-radius: var(--radius-md);
            color: var(--text);
            text-decoration: none;
            font-weight: 500;
            font-size: 15px;
            transition: all 0.3s var(--ease-out);
        }

        .login-link:hover {
            border-color: var(--accent);
            background: var(--surface);
        }

        .form-footer {
            margin-top: 24px;
            text-align: center;
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                    </svg>
                </div>
                <h2 class="visual-title">Kom godt i gang</h2>
                <p class="visual-text">
                    Opret din gratis konto og begynd at planlægge dit arrangement med stil.
                </p>
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

        <div class="login-form-section">
            <div class="login-header">
                <a href="/" class="login-logo">Party<span>Parart</span></a>
                <h1 class="login-title">Opret din konto</h1>
                <p class="login-subtitle">Kom i gang med at planlægge dit arrangement</p>
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
                        >
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Opret konto
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>

                    <p class="terms-text">
                        Ved at oprette en konto accepterer du vores
                        <a href="/vilkaar">Vilkår og betingelser</a> og
                        <a href="/privatlivspolitik">Privatlivspolitik</a>.
                    </p>
                </form>

                <div class="divider"><span>eller</span></div>

                <a href="/app/auth/login.php" class="login-link">Log ind med eksisterende konto</a>

                <div class="form-footer">
                    <a href="/" class="form-link">Tilbage til forsiden</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

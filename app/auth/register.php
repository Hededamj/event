<?php
/**
 * Account Registration Page
 */
require_once __DIR__ . '/../../config/database.php';
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
    <title>Opret konto - EventPlatform</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .auth-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            width: 100%;
            max-width: 420px;
            padding: 48px 40px;
        }

        .auth-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .auth-logo {
            font-size: 28px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 8px;
        }

        .auth-title {
            font-size: 24px;
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 8px;
        }

        .auth-subtitle {
            color: #6b7280;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #374151;
            margin-bottom: 6px;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            font-size: 15px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            transition: all 0.2s;
            font-family: inherit;
        }

        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-input::placeholder {
            color: #9ca3af;
        }

        .form-hint {
            font-size: 12px;
            color: #9ca3af;
            margin-top: 4px;
        }

        .error-message {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .btn {
            width: 100%;
            padding: 14px 24px;
            font-size: 15px;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-footer {
            margin-top: 24px;
            text-align: center;
        }

        .form-link {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

        .form-link:hover {
            text-decoration: underline;
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 24px 0;
            color: #9ca3af;
            font-size: 13px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e5e7eb;
        }

        .divider span {
            padding: 0 16px;
        }

        .login-link {
            display: block;
            text-align: center;
            padding: 14px 24px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            color: #374151;
            text-decoration: none;
            font-weight: 500;
            font-size: 15px;
            transition: all 0.2s;
        }

        .login-link:hover {
            border-color: #667eea;
            color: #667eea;
        }

        .terms-text {
            font-size: 12px;
            color: #6b7280;
            text-align: center;
            margin-top: 16px;
            line-height: 1.6;
        }

        .terms-text a {
            color: #667eea;
            text-decoration: none;
        }

        .terms-text a:hover {
            text-decoration: underline;
        }

        .features-list {
            background: #f8fafc;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .features-list h3 {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 12px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 8px;
        }

        .feature-item:last-child {
            margin-bottom: 0;
        }

        .feature-item svg {
            width: 16px;
            height: 16px;
            color: #22c55e;
        }

        @media (max-width: 480px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .auth-container {
                padding: 32px 24px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <div class="auth-logo">EventPlatform</div>
            <h1 class="auth-title">Opret din konto</h1>
            <p class="auth-subtitle">Kom i gang med at planlægge dit arrangement</p>
        </div>

        <div class="features-list">
            <h3>Med en gratis konto får du:</h3>
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

        <?php if ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
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
                <label class="form-label" for="phone">Telefon <span style="color: #9ca3af">(valgfrit)</span></label>
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

            <button type="submit" class="btn btn-primary">Opret konto</button>

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
</body>
</html>

<?php
/**
 * Account Login Page
 */
require_once __DIR__ . '/../../config/database.php';
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
            $result = verifyAccountPassword($email, $password);

            if ($result === null) {
                $error = 'Forkert email eller adgangskode.';
            } elseif (isset($result['error']) && $result['error'] === 'account_inactive') {
                $error = 'Din konto er deaktiveret. Kontakt support.';
            } else {
                // Successful login
                accountLogin($result['id'], $result['email'], $result['name']);

                // Redirect to return URL or dashboard
                $returnUrl = $_GET['return'] ?? '/app/dashboard.php';
                // Validate return URL is local
                if (strpos($returnUrl, '/') !== 0) {
                    $returnUrl = '/app/dashboard.php';
                }
                redirect($returnUrl);
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
    <title>Log ind - EventPlatform</title>
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

        .register-link {
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

        .register-link:hover {
            border-color: #667eea;
            color: #667eea;
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
            color: #6b7280;
            cursor: pointer;
        }

        .checkbox-label input {
            width: 16px;
            height: 16px;
            accent-color: #667eea;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <div class="auth-logo">EventPlatform</div>
            <h1 class="auth-title">Velkommen tilbage</h1>
            <p class="auth-subtitle">Log ind for at administrere dine arrangementer</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
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

            <button type="submit" class="btn btn-primary">Log ind</button>
        </form>

        <div class="divider"><span>eller</span></div>

        <a href="/app/auth/register.php" class="register-link">Opret ny konto</a>

        <div class="form-footer">
            <a href="/" class="form-link">Tilbage til forsiden</a>
        </div>
    </div>
</body>
</html>

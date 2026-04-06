<?php
/**
 * Platform Admin Login
 */

require_once __DIR__ . '/../includes/admin-platform-auth.php';
require_once __DIR__ . '/../includes/rate-limiter.php';

// If already logged in, redirect to dashboard
if (isPlatformAdmin()) {
    redirect(BASE_PATH . '/admin-platform/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Ugyldig anmodning. Prøv igen.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Indtast email og adgangskode';
        } else {
            // Rate limiting
            $db = getDB();
            $rateLimit = checkRateLimit($db, 'admin_login', 5, 900);
            if (!$rateLimit['allowed']) {
                $error = 'For mange loginforsøg. Prøv igen om ' . ceil($rateLimit['retry_after'] / 60) . ' minutter.';
            } else {
                // Find admin account
                $stmt = $db->prepare("
                    SELECT id, password_hash, name, is_active, is_platform_admin
                    FROM accounts
                    WHERE email = ? AND is_platform_admin = 1
                ");
                $stmt->execute([$email]);
                $admin = $stmt->fetch();

                if ($admin && password_verify($password, $admin['password_hash'])) {
                    if (!$admin['is_active']) {
                        $error = 'Din konto er deaktiveret';
                    } else {
                        loginPlatformAdmin($admin['id']);
                        redirect(BASE_PATH . '/admin-platform/index.php');
                    }
                } else {
                    recordRateLimitAttempt($db, 'admin_login', 5, 900);
                    $error = 'Forkert email eller adgangskode';
                }
            }
        }
    }
}

$platformName = getPlatformSetting('platform_name', 'EventPlatform');
?>
<!DOCTYPE html>
<html lang="da" data-area="admin">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Admin Login - <?= escape($platformName) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/design-system.css">

    <style>
        body {
            background: var(--surface);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .login-container {
            width: 100%;
            max-width: 400px;
        }

        .login-brand {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-brand-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent);
        }

        .login-brand-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .login-card {
            background: var(--surface-card);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            padding: 2rem;
        }

        .login-title {
            font-size: 1.25rem;
            font-weight: 600;
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            font-family: var(--font-body);
            transition: border-color 0.15s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(124, 109, 175, 0.1);
        }

        .btn {
            display: block;
            width: 100%;
            padding: 0.75rem 1rem;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            font-family: var(--font-body);
            font-weight: 500;
            cursor: pointer;
            transition: background 0.15s ease;
        }

        .btn:hover {
            background: var(--accent-dark);
        }

        .alert {
            padding: 0.75rem 1rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }

        .alert-error {
            background: var(--error-light);
            color: var(--error);
        }

        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .login-footer a {
            color: var(--accent);
            text-decoration: none;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-brand">
            <div class="login-brand-name"><?= escape($platformName) ?></div>
            <div class="login-brand-label">Platform Administration</div>
        </div>

        <div class="login-card">
            <h1 class="login-title">Log ind</h1>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= escape($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <?= csrfField() ?>
                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-input" required autofocus
                           value="<?= escape($_POST['email'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Adgangskode</label>
                    <input type="password" id="password" name="password" class="form-input" required>
                </div>

                <button type="submit" class="btn">Log ind</button>
            </form>
        </div>

        <div class="login-footer">
            <a href="<?= BASE_PATH ?>/">Tilbage til forsiden</a>
        </div>
    </div>
</body>
</html>

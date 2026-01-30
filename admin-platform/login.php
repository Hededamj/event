<?php
/**
 * Platform Admin Login
 */

require_once __DIR__ . '/../includes/admin-platform-auth.php';

// If already logged in, redirect to dashboard
if (isPlatformAdmin()) {
    redirect(BASE_PATH . '/admin-platform/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Indtast email og adgangskode';
    } else {
        $db = getDB();

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
            $error = 'Forkert email eller adgangskode';
        }
    }
}

$platformName = getPlatformSetting('platform_name', 'EventPlatform');
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Admin Login - <?= escape($platformName) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --color-bg: #f8fafc;
            --color-surface: #ffffff;
            --color-primary: #3b82f6;
            --color-primary-deep: #2563eb;
            --color-text: #1e293b;
            --color-text-muted: #64748b;
            --color-border: #e2e8f0;
            --color-error: #ef4444;
            --color-error-soft: #fee2e2;
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
            --radius-lg: 12px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--color-bg);
            color: var(--color-text);
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
            color: var(--color-primary);
        }

        .login-brand-label {
            font-size: 0.875rem;
            color: var(--color-text-muted);
        }

        .login-card {
            background: var(--color-surface);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
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
            border: 1px solid var(--color-border);
            border-radius: 8px;
            font-size: 0.875rem;
            transition: border-color 0.15s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .btn {
            display: block;
            width: 100%;
            padding: 0.75rem 1rem;
            background: var(--color-primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.15s ease;
        }

        .btn:hover {
            background: var(--color-primary-deep);
        }

        .alert {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }

        .alert-error {
            background: var(--color-error-soft);
            color: var(--color-error);
        }

        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.875rem;
            color: var(--color-text-muted);
        }

        .login-footer a {
            color: var(--color-primary);
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

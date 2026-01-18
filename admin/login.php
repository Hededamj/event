<?php
/**
 * Admin Login Page
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect(BASE_PATH . '/admin/index.php');
}

// Get event for theme
$db = getDB();
$stmt = $db->query("SELECT * FROM events ORDER BY id LIMIT 1");
$event = $stmt->fetch();
$theme = $event['theme'] ?? 'girl';

$error = null;

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password && $event) {
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND event_id = ?");
        $stmt->execute([$email, $event['id']]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            login($user['id'], $event['id']);
            redirect(BASE_PATH . '/admin/index.php');
        } else {
            $error = 'Forkert email eller adgangskode.';
        }
    } else {
        $error = 'Udfyld venligst email og adgangskode.';
    }
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log ind - Admin</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">

    <!-- Styles -->
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/main.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/theme-<?= escape($theme) ?>.css">

    <style>
        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-lg);
            background: var(--color-bg);
        }

        .login-card {
            background: var(--color-surface);
            border-radius: var(--radius-xl);
            padding: var(--space-xl);
            box-shadow: var(--shadow-lg);
            max-width: 400px;
            width: 100%;
        }

        .login-card__header {
            text-align: center;
            margin-bottom: var(--space-lg);
        }

        .login-card__icon {
            font-size: 3rem;
            margin-bottom: var(--space-sm);
        }

        .login-card__title {
            font-family: 'Cormorant Garamond', serif;
            font-size: var(--text-2xl);
            color: var(--color-primary-deep);
            margin-bottom: var(--space-2xs);
        }

        .login-card__subtitle {
            color: var(--color-text-muted);
            font-size: var(--text-sm);
        }

        .login-card__footer {
            text-align: center;
            margin-top: var(--space-lg);
            padding-top: var(--space-md);
            border-top: 1px solid var(--color-border-soft);
        }
    </style>
</head>
<body>
    <div class="login-page">
        <div class="login-card">
            <div class="login-card__header">
                <div class="login-card__icon">⚙️</div>
                <h1 class="login-card__title">Arrangør Login</h1>
                <p class="login-card__subtitle">Log ind for at administrere eventet</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert--error mb-md">
                    <?= escape($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email"
                           name="email"
                           class="form-input"
                           required
                           autocomplete="email"
                           autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label">Adgangskode</label>
                    <input type="password"
                           name="password"
                           class="form-input"
                           required
                           autocomplete="current-password">
                </div>

                <button type="submit" class="btn btn--primary btn--block btn--large">
                    Log ind
                </button>
            </form>

            <div class="login-card__footer">
                <a href="<?= BASE_PATH ?>/" class="text-muted small">
                    ← Tilbage til invitationen
                </a>
            </div>
        </div>
    </div>
</body>
</html>

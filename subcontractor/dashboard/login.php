<?php
/**
 * Vendor Login Page - Nordic Design
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../includes/vendor-auth.php';
require_once __DIR__ . '/../../includes/auth-account.php';

// Redirect if already logged in
if (isVendorLoggedIn()) {
    redirect('/subcontractor/dashboard/');
}

$error = '';
$email = '';

// Check for flash messages (e.g. from registration redirect)
$flash = getFlash();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyVendorCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Ugyldig anmodning. Pr\xC3\xB8v igen.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Udfyld venligst email og adgangskode.';
        } else {
            // Check rate limiting before attempting login
            $lockoutMinutes = isLoginRateLimited($email);
            if ($lockoutMinutes > 0) {
                $error = "For mange loginfors\xC3\xB8g. Pr\xC3\xB8v igen om $lockoutMinutes minut" . ($lockoutMinutes > 1 ? 'ter' : '') . '.';
            } else {
                $result = verifyVendorPassword($email, $password);

                if ($result === null) {
                    recordLoginAttempt($email);
                    $error = 'Forkert email eller adgangskode.';
                } elseif (isset($result['error'])) {
                    switch ($result['error']) {
                        case 'vendor_pending':
                            $error = "Din ans\xC3\xB8gning er under behandling. Vi vender tilbage hurtigst muligt.";
                            break;
                        case 'vendor_rejected':
                            $error = "Din ans\xC3\xB8gning er desv\xC3\xA6rre afvist. Kontakt os for mere information.";
                            break;
                        case 'vendor_suspended':
                            $error = 'Din konto er suspenderet. Kontakt support.';
                            break;
                        case 'vendor_inactive':
                            $error = 'Din konto er deaktiveret. Kontakt support.';
                            break;
                        default:
                            $error = "Der opstod en fejl. Pr\xC3\xB8v igen.";
                    }
                } else {
                    // Successful login - clear failed attempts
                    clearLoginAttempts($email);
                    vendorLogin($result['id'], $result['email'], $result['company_name']);

                    // Redirect to return URL or dashboard
                    $returnUrl = $_GET['return'] ?? '/subcontractor/dashboard/';
                    // Validate return URL is local
                    if (strpos($returnUrl, '/') !== 0) {
                        $returnUrl = '/subcontractor/dashboard/';
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
    <title>Log ind - Leverand&#248;rportal - PartyParart</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --surface: #FAF9F7;
            --border: #F5F3EF;
            --accent: #A8B5A0;
            --accent-light: #C8D4C2;
            --accent-dark: #7A8B72;
            --text: #2C2C2C;
            --text-secondary: #4A4A4A;
            --warning: #C9A962;
            --white: #FFFFFF;
            --error: #C75D5D;
            --success: #5DA87A;

            --font-display: 'Cormorant Garamond', Georgia, serif;
            --font-body: 'DM Sans', -apple-system, sans-serif;
            --ease-out: cubic-bezier(0.16, 1, 0.3, 1);
        }

        body {
            font-family: var(--font-body);
            background: var(--surface);
            min-height: 100vh;
            display: flex;
            color: var(--text);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        /* Subtle grain texture */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.8' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E");
            opacity: 0.025;
            pointer-events: none;
            z-index: 9999;
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
            background: var(--white);
            border-radius: 24px;
            padding: 40px;
            box-shadow:
                0 1px 2px rgba(0,0,0,0.04),
                0 4px 16px rgba(0,0,0,0.04);
            max-width: 440px;
            animation: fadeIn 0.6s var(--ease-out);
        }

        .flash-message {
            padding: 14px 18px;
            border-radius: 14px;
            font-size: 14px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .flash-success {
            background: #F0F7F2;
            border: 1px solid #C8E6C9;
            color: var(--success);
        }

        .flash-error {
            background: #FDF2F2;
            border: 1px solid #F5D5D5;
            color: var(--error);
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
    </style>
</head>
<body>
    <div class="login-layout">
        <div class="login-visual">
            <div class="visual-content">
                <div class="visual-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                </div>
                <h2 class="visual-title">Leverand&#248;rportal</h2>
                <p class="visual-text">
                    Log ind for at administrere dine bookinger og ydelser
                </p>
            </div>
        </div>

        <div class="login-form-section">
            <div class="login-header">
                <a href="/" class="login-logo">Party<span>Parart</span></a>
                <h1 class="login-title">Velkommen tilbage</h1>
                <p class="login-subtitle">Log ind p&#229; din leverand&#248;rkonto</p>
            </div>

            <div class="login-card">
                <?php if ($flash): ?>
                    <div class="flash-message flash-<?= htmlspecialchars($flash['type']) ?>">
                        <?php if ($flash['type'] === 'success'): ?>
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <?php else: ?>
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <?php endif; ?>
                        <?= htmlspecialchars($flash['message']) ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="error-message">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?= vendorCsrfField() ?>

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

                <a href="/subcontractor/dashboard/register.php" class="register-link">Opret leverand&#248;rkonto</a>

                <div class="form-footer">
                    <a href="/" class="form-link">Tilbage til forsiden</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

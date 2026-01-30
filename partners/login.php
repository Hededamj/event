<?php
/**
 * Partner Login
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/partner-auth.php';

// If already logged in as partner, redirect to dashboard
if (isPartner()) {
    redirect(BASE_PATH . '/partners/dashboard/');
}

$db = getDB();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Indtast email og adgangskode';
    } else {
        // Find account
        $stmt = $db->prepare("
            SELECT a.id, a.password_hash, a.name, a.is_active
            FROM accounts a
            WHERE a.email = ?
        ");
        $stmt->execute([$email]);
        $account = $stmt->fetch();

        if ($account && password_verify($password, $account['password_hash'])) {
            if (!$account['is_active']) {
                $error = 'Din konto er deaktiveret';
            } else {
                // Check if they have a partner profile
                $partner = getPartnerByAccountId($account['id']);

                if ($partner) {
                    loginPartner($account['id'], $partner['id']);
                    redirect(BASE_PATH . '/partners/dashboard/');
                } else {
                    $error = 'Ingen partnerprofil fundet for denne konto. <a href="' . BASE_PATH . '/partners/register.php">Opret en her</a>';
                }
            }
        } else {
            $error = 'Forkert email eller adgangskode';
        }
    }
}

$pageTitle = 'Partner login';
require_once __DIR__ . '/../includes/partner-header.php';
?>

<style>
    .login-container {
        max-width: 400px;
        margin: 0 auto;
        padding: 4rem 1.5rem;
    }

    .login-header {
        text-align: center;
        margin-bottom: 2rem;
    }

    .login-header h1 {
        font-family: 'Playfair Display', serif;
        font-size: 1.75rem;
        margin-bottom: 0.5rem;
    }

    .login-card {
        background: var(--color-surface);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-md);
        padding: 2rem;
    }
</style>

<div class="login-container">
    <div class="login-header">
        <h1>Partner login</h1>
        <p style="color: var(--color-text-muted);">Log ind for at administrere din partnerprofil</p>
    </div>

    <div class="login-card">
        <?php if ($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-input" required autofocus
                       value="<?= escape($_POST['email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Adgangskode</label>
                <input type="password" name="password" class="form-input" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">Log ind</button>
        </form>

        <p style="text-align: center; margin-top: 1.5rem; font-size: 0.9rem; color: var(--color-text-muted);">
            Har du ikke en konto? <a href="<?= BASE_PATH ?>/partners/register.php" style="color: var(--color-primary);">Bliv partner</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/partner-footer.php'; ?>

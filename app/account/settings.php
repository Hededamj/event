<?php
/**
 * Account Settings Page
 */
$pageTitle = 'Kontoindstillinger';
require_once __DIR__ . '/../../includes/app-header.php';

$error = '';
$success = '';

// Get full account data
$stmt = $db->prepare("SELECT * FROM accounts WHERE id = ?");
$stmt->execute([$accountId]);
$account = $stmt->fetch();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyAccountCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Ugyldig anmodning. Prøv igen.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_profile') {
            $name = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');

            if (empty($name)) {
                $error = 'Navn er påkrævet.';
            } else {
                $stmt = $db->prepare("UPDATE accounts SET name = ?, phone = ? WHERE id = ?");
                $stmt->execute([$name, $phone ?: null, $accountId]);

                // Update session
                $_SESSION['account_name'] = $name;

                setFlash('success', 'Dine oplysninger er opdateret.');
                redirect('/app/account/settings.php');
            }
        } elseif ($action === 'change_password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($currentPassword) || empty($newPassword)) {
                $error = 'Udfyld alle felter.';
            } elseif (!password_verify($currentPassword, $account['password_hash'])) {
                $error = 'Nuværende adgangskode er forkert.';
            } elseif (strlen($newPassword) < 8) {
                $error = 'Ny adgangskode skal være mindst 8 tegn.';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'Adgangskoderne matcher ikke.';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE accounts SET password_hash = ? WHERE id = ?");
                $stmt->execute([$newHash, $accountId]);

                setFlash('success', 'Din adgangskode er ændret.');
                redirect('/app/account/settings.php');
            }
        } elseif ($action === 'change_email') {
            $newEmail = trim($_POST['new_email'] ?? '');
            $password = $_POST['email_password'] ?? '';

            if (empty($newEmail) || empty($password)) {
                $error = 'Udfyld alle felter.';
            } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $error = 'Ugyldig email-adresse.';
            } elseif (!password_verify($password, $account['password_hash'])) {
                $error = 'Forkert adgangskode.';
            } else {
                // Check if email is already in use
                $stmt = $db->prepare("SELECT id FROM accounts WHERE email = ? AND id != ?");
                $stmt->execute([$newEmail, $accountId]);
                if ($stmt->fetch()) {
                    $error = 'Denne email er allerede i brug.';
                } else {
                    $stmt = $db->prepare("UPDATE accounts SET email = ? WHERE id = ?");
                    $stmt->execute([$newEmail, $accountId]);

                    // Update session
                    $_SESSION['account_email'] = $newEmail;

                    setFlash('success', 'Din email er ændret.');
                    redirect('/app/account/settings.php');
                }
            }
        }
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Kontoindstillinger</h1>
        <p class="page-subtitle">Administrer dine kontooplysninger og sikkerhed</p>
    </div>
</div>

<?php if ($error): ?>
    <div class="flash-message error">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="settings-grid">
    <!-- Profile Settings -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Profiloplysninger</h2>
        </div>
        <form method="POST" action="">
            <?= accountCsrfField() ?>
            <input type="hidden" name="action" value="update_profile">

            <div class="form-group">
                <label class="form-label" for="name">Navn</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    class="form-input"
                    value="<?= htmlspecialchars($account['name']) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="email_display">Email</label>
                <input
                    type="email"
                    id="email_display"
                    class="form-input"
                    value="<?= htmlspecialchars($account['email']) ?>"
                    disabled
                >
                <p class="form-hint">Brug formularen nedenfor for at ændre email.</p>
            </div>

            <div class="form-group">
                <label class="form-label" for="phone">Telefon</label>
                <input
                    type="tel"
                    id="phone"
                    name="phone"
                    class="form-input"
                    value="<?= htmlspecialchars($account['phone'] ?? '') ?>"
                    placeholder="12 34 56 78"
                >
            </div>

            <button type="submit" class="btn btn-primary">Gem ændringer</button>
        </form>
    </div>

    <!-- Change Email -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Skift email</h2>
        </div>
        <form method="POST" action="">
            <?= accountCsrfField() ?>
            <input type="hidden" name="action" value="change_email">

            <div class="form-group">
                <label class="form-label" for="new_email">Ny email</label>
                <input
                    type="email"
                    id="new_email"
                    name="new_email"
                    class="form-input"
                    placeholder="ny@email.dk"
                    required
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="email_password">Bekræft med adgangskode</label>
                <input
                    type="password"
                    id="email_password"
                    name="email_password"
                    class="form-input"
                    placeholder="Din nuværende adgangskode"
                    required
                >
            </div>

            <button type="submit" class="btn btn-primary">Skift email</button>
        </form>
    </div>

    <!-- Change Password -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Skift adgangskode</h2>
        </div>
        <form method="POST" action="">
            <?= accountCsrfField() ?>
            <input type="hidden" name="action" value="change_password">

            <div class="form-group">
                <label class="form-label" for="current_password">Nuværende adgangskode</label>
                <input
                    type="password"
                    id="current_password"
                    name="current_password"
                    class="form-input"
                    required
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="new_password">Ny adgangskode</label>
                <input
                    type="password"
                    id="new_password"
                    name="new_password"
                    class="form-input"
                    placeholder="Mindst 8 tegn"
                    required
                    minlength="8"
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="confirm_password">Bekræft ny adgangskode</label>
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    class="form-input"
                    required
                >
            </div>

            <button type="submit" class="btn btn-primary">Skift adgangskode</button>
        </form>
    </div>

    <!-- Account Info -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Kontoinformation</h2>
        </div>
        <div class="info-list">
            <div class="info-item">
                <span class="info-label">Konto oprettet</span>
                <span class="info-value"><?= htmlspecialchars(formatDate($account['created_at'])) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Seneste login</span>
                <span class="info-value">
                    <?= $account['last_login_at'] ? htmlspecialchars(formatDate($account['last_login_at'])) : 'Aldrig' ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Antal logins</span>
                <span class="info-value"><?= (int)$account['login_count'] ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Email verificeret</span>
                <span class="info-value">
                    <?php if ($account['email_verified_at']): ?>
                        <span class="badge badge-success">Ja</span>
                    <?php else: ?>
                        <span class="badge badge-warning">Nej</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>
</div>

<style>
    .settings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 24px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-label {
        display: block;
        font-size: 14px;
        font-weight: 500;
        color: var(--gray-700);
        margin-bottom: 6px;
    }

    .form-input {
        width: 100%;
        padding: 10px 14px;
        font-size: 14px;
        border: 2px solid var(--gray-200);
        border-radius: 8px;
        transition: all 0.2s;
        font-family: inherit;
    }

    .form-input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .form-input:disabled {
        background: var(--gray-50);
        color: var(--gray-500);
        cursor: not-allowed;
    }

    .form-hint {
        font-size: 12px;
        color: var(--gray-500);
        margin-top: 4px;
    }

    .info-list {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .info-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 16px;
        border-bottom: 1px solid var(--gray-100);
    }

    .info-item:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .info-label {
        font-size: 14px;
        color: var(--gray-500);
    }

    .info-value {
        font-size: 14px;
        font-weight: 500;
        color: var(--gray-900);
    }

    .badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        font-size: 12px;
        font-weight: 500;
        border-radius: 6px;
    }

    .badge-success {
        background: #dcfce7;
        color: #15803d;
    }

    .badge-warning {
        background: #fef3c7;
        color: #b45309;
    }

    @media (max-width: 640px) {
        .settings-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php require_once __DIR__ . '/../../includes/app-footer.php'; ?>

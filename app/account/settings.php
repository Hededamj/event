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

    <!-- GDPR / Privacy -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Privatliv og data (GDPR)</h2>
        </div>
        <p style="color: var(--text-secondary); font-size: 14px; margin-bottom: 20px;">
            Du har ret til at se, eksportere og slette dine personlige data.
        </p>
        <div class="gdpr-actions">
            <a href="/app/account/export-data.php" class="gdpr-action-link">
                <span class="gdpr-action-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                </span>
                <span>
                    <strong>Download mine data</strong>
                    <small>Eksporter alle dine data i JSON eller HTML format</small>
                </span>
            </a>
            <a href="/app/account/delete-account.php" class="gdpr-action-link gdpr-action-danger">
                <span class="gdpr-action-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                    </svg>
                </span>
                <span>
                    <strong>Slet min konto</strong>
                    <small>Anmod om permanent sletning af din konto og data</small>
                </span>
            </a>
        </div>
        <p style="color: var(--text-secondary); font-size: 12px; margin-top: 16px;">
            Læs vores <a href="/legal/privacy.php" target="_blank" style="color: var(--accent);">Privatlivspolitik</a>
            og <a href="/legal/terms.php" target="_blank" style="color: var(--accent);">Vilkår og betingelser</a>
        </p>
    </div>
</div>

<style>
    .settings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 24px;
    }

    .form-input:disabled {
        background: var(--surface);
        color: var(--text-secondary);
        cursor: not-allowed;
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
        border-bottom: 1px solid var(--border-light);
    }

    .info-item:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .info-label {
        font-size: 14px;
        color: var(--text-secondary);
    }

    .info-value {
        font-size: 14px;
        font-weight: 500;
        color: var(--text);
    }

    @media (max-width: 640px) {
        .settings-grid {
            grid-template-columns: 1fr;
        }
    }

    .gdpr-actions {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .gdpr-action-link {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 16px;
        background: var(--surface);
        border-radius: var(--radius-sm);
        text-decoration: none;
        transition: all 0.2s;
    }

    .gdpr-action-link:hover {
        background: var(--border-light);
    }

    .gdpr-action-link span {
        display: flex;
        flex-direction: column;
    }

    .gdpr-action-link strong {
        color: var(--text);
        font-size: 14px;
    }

    .gdpr-action-link small {
        color: var(--text-secondary);
        font-size: 12px;
        margin-top: 2px;
    }

    .gdpr-action-icon {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--surface-card);
        border-radius: var(--radius-sm);
        flex-shrink: 0;
    }

    .gdpr-action-icon svg {
        width: 20px;
        height: 20px;
        color: var(--accent);
    }

    .gdpr-action-danger .gdpr-action-icon {
        background: var(--error-light);
    }

    .gdpr-action-danger .gdpr-action-icon svg {
        color: var(--error);
    }

    .gdpr-action-danger:hover {
        background: var(--error-light);
    }
</style>

<?php require_once __DIR__ . '/../../includes/app-footer.php'; ?>

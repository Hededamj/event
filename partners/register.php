<?php
/**
 * Partner Registration
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/partner-auth.php';

// If already logged in as partner, redirect to dashboard
if (isPartner()) {
    redirect(BASE_PATH . '/partners/dashboard/');
}

$db = getDB();

// Get categories
$categories = $db->query("SELECT * FROM partner_categories WHERE is_active = 1 ORDER BY sort_order")->fetchAll();

$errors = [];
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ugyldig formular. Prøv igen.';
    } else {
        // Collect form data
        $formData = [
            'company_name' => trim($_POST['company_name'] ?? ''),
            'category_id' => (int)($_POST['category_id'] ?? 0),
            'contact_name' => trim($_POST['contact_name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'password_confirm' => $_POST['password_confirm'] ?? '',
            'description' => trim($_POST['description'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
            'website' => trim($_POST['website'] ?? ''),
        ];

        // Validate
        if (empty($formData['company_name'])) {
            $errors[] = 'Firmanavn er påkrævet';
        }

        if (empty($formData['category_id'])) {
            $errors[] = 'Vælg en kategori';
        }

        if (empty($formData['contact_name'])) {
            $errors[] = 'Kontaktperson er påkrævet';
        }

        if (empty($formData['email']) || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Gyldig email er påkrævet';
        }

        if (strlen($formData['password']) < 8) {
            $errors[] = 'Adgangskode skal være mindst 8 tegn';
        }

        if ($formData['password'] !== $formData['password_confirm']) {
            $errors[] = 'Adgangskoderne matcher ikke';
        }

        // Check if email already exists
        $stmt = $db->prepare("SELECT id FROM accounts WHERE email = ?");
        $stmt->execute([$formData['email']]);
        if ($stmt->fetch()) {
            $errors[] = 'Email er allerede i brug. <a href="' . BASE_PATH . '/partners/login.php">Log ind her</a>';
        }

        if (empty($errors)) {
            $db->beginTransaction();

            try {
                // Create account
                $stmt = $db->prepare("
                    INSERT INTO accounts (email, password_hash, name, phone, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $formData['email'],
                    password_hash($formData['password'], PASSWORD_DEFAULT),
                    $formData['contact_name'],
                    $formData['phone']
                ]);
                $accountId = $db->lastInsertId();

                // Create partner profile
                $stmt = $db->prepare("
                    INSERT INTO partners (account_id, category_id, company_name, description, contact_name, email, phone, website, city, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([
                    $accountId,
                    $formData['category_id'],
                    $formData['company_name'],
                    $formData['description'],
                    $formData['contact_name'],
                    $formData['email'],
                    $formData['phone'],
                    $formData['website'] ?: null,
                    $formData['city']
                ]);
                $partnerId = $db->lastInsertId();

                $db->commit();

                // Log in the new partner
                loginPartner($accountId, $partnerId);

                setFlash('success', 'Din partnerprofil er oprettet og afventer godkendelse. Du kan nu udfylde resten af din profil.');
                redirect(BASE_PATH . '/partners/dashboard/');

            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = 'Der opstod en fejl. Prøv igen.';
            }
        }
    }
}

$pageTitle = 'Bliv partner';
require_once __DIR__ . '/../includes/partner-header.php';
?>

<style>
    .register-container {
        max-width: 600px;
        margin: 0 auto;
        padding: 3rem 1.5rem;
    }

    .register-header {
        text-align: center;
        margin-bottom: 2rem;
    }

    .register-header h1 {
        font-family: 'Playfair Display', serif;
        font-size: 2rem;
        margin-bottom: 0.5rem;
    }

    .register-header p {
        color: var(--color-text-soft);
    }

    .register-card {
        background: var(--color-surface);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-md);
        padding: 2rem;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }

    .benefits {
        background: var(--color-primary-soft);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        margin-bottom: 2rem;
    }

    .benefits h3 {
        font-size: 1rem;
        margin-bottom: 0.75rem;
        color: var(--color-primary-deep);
    }

    .benefits ul {
        list-style: none;
        font-size: 0.9rem;
    }

    .benefits li {
        padding: 0.25rem 0;
    }

    .benefits li::before {
        content: '✓ ';
        color: var(--color-primary);
        font-weight: bold;
    }

    @media (max-width: 600px) {
        .form-row {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="register-container">
    <div class="register-header">
        <h1>Bliv partner</h1>
        <p>Opret din partnerprofil og nå ud til tusindvis af eventplanlæggere</p>
    </div>

    <div class="benefits">
        <h3>Som partner får du</h3>
        <ul>
            <li>Synlighed på vores markedsplads</li>
            <li>Forespørgsler direkte fra eventplanlæggere</li>
            <li>Statistik over visninger og henvendelser</li>
            <li>Mulighed for at uploade galleri og priser</li>
        </ul>
    </div>

    <div class="register-card">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin: 0; padding-left: 1rem;">
                    <?php foreach ($errors as $error): ?>
                        <li><?= $error ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST">
            <?= csrfField() ?>

            <div class="form-group">
                <label class="form-label">Firmanavn *</label>
                <input type="text" name="company_name" class="form-input" required
                       value="<?= escape($formData['company_name'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Kategori *</label>
                <select name="category_id" class="form-input" required>
                    <option value="">Vælg kategori...</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($formData['category_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>>
                            <?= $cat['icon'] ?> <?= escape($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Kontaktperson *</label>
                    <input type="text" name="contact_name" class="form-input" required
                           value="<?= escape($formData['contact_name'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Telefon</label>
                    <input type="tel" name="phone" class="form-input"
                           value="<?= escape($formData['phone'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Email *</label>
                <input type="email" name="email" class="form-input" required
                       value="<?= escape($formData['email'] ?? '') ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Adgangskode *</label>
                    <input type="password" name="password" class="form-input" required minlength="8">
                </div>

                <div class="form-group">
                    <label class="form-label">Bekræft adgangskode *</label>
                    <input type="password" name="password_confirm" class="form-input" required minlength="8">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">By/Område</label>
                <input type="text" name="city" class="form-input" placeholder="F.eks. København"
                       value="<?= escape($formData['city'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Hjemmeside</label>
                <input type="url" name="website" class="form-input" placeholder="https://"
                       value="<?= escape($formData['website'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Kort beskrivelse af din virksomhed</label>
                <textarea name="description" class="form-input" rows="4"
                          placeholder="Fortæl kort om hvad I tilbyder..."><?= escape($formData['description'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">
                Opret partnerprofil
            </button>
        </form>

        <p style="text-align: center; margin-top: 1.5rem; font-size: 0.9rem; color: var(--color-text-muted);">
            Har du allerede en konto? <a href="<?= BASE_PATH ?>/partners/login.php" style="color: var(--color-primary);">Log ind her</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/partner-footer.php'; ?>

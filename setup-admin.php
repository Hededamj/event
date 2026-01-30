<?php
/**
 * One-time admin setup script
 * Creates or updates the platform admin account
 *
 * DELETE THIS FILE AFTER USE!
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/saas.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = 'mail@hededam.dk'; // Hardcoded for security
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $name = trim($_POST['name'] ?? 'Platform Admin');

    if (strlen($password) < 8) {
        $error = 'Adgangskoden skal være mindst 8 tegn';
    } elseif ($password !== $confirmPassword) {
        $error = 'Adgangskoderne matcher ikke';
    } else {
        try {
            $db = getDB();
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            // Check if account exists
            $stmt = $db->prepare("SELECT id FROM accounts WHERE email = ?");
            $stmt->execute([$email]);
            $existing = $stmt->fetch();

            if ($existing) {
                // Update existing account
                $stmt = $db->prepare("
                    UPDATE accounts
                    SET password_hash = ?,
                        name = ?,
                        is_platform_admin = 1,
                        is_active = 1
                    WHERE email = ?
                ");
                $stmt->execute([$passwordHash, $name, $email]);
                $message = "Admin-konto opdateret! Du kan nu logge ind med $email";
            } else {
                // Create new account
                $stmt = $db->prepare("
                    INSERT INTO accounts (email, password_hash, name, is_platform_admin, is_active, created_at)
                    VALUES (?, ?, ?, 1, 1, NOW())
                ");
                $stmt->execute([$email, $passwordHash, $name]);
                $message = "Admin-konto oprettet! Du kan nu logge ind med $email";
            }
        } catch (Exception $e) {
            $error = 'Fejl: ' . $e->getMessage();
        }
    }
}

// Check current state
$currentState = '';
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, email, name, is_active, is_platform_admin, password_hash FROM accounts WHERE email = ?");
    $stmt->execute(['mail@hededam.dk']);
    $account = $stmt->fetch();

    if ($account) {
        $currentState = "Eksisterende konto: {$account['name']} ({$account['email']})<br>";
        $currentState .= "- is_active: " . ($account['is_active'] ? 'Ja' : 'Nej') . "<br>";
        $currentState .= "- is_platform_admin: " . ($account['is_platform_admin'] ? 'Ja' : 'Nej') . "<br>";
        $currentState .= "- password_hash: " . (!empty($account['password_hash']) ? 'Sat' : 'MANGLER') . "<br>";
    } else {
        $currentState = "Ingen konto fundet med email: mail@hededam.dk";
    }
} catch (Exception $e) {
    $currentState = "Kunne ikke tjekke: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Setup</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f5f5f5;
            padding: 2rem;
            max-width: 500px;
            margin: 0 auto;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        h1 { margin-bottom: 1rem; font-size: 1.5rem; }
        .status {
            background: #f0f0f0;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            line-height: 1.6;
        }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        button {
            width: 100%;
            padding: 0.75rem;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
        }
        button:hover { background: #2563eb; }
        .message {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .success { background: #d1fae5; color: #065f46; }
        .error { background: #fee2e2; color: #b91c1c; }
        .warning {
            background: #fef3c7;
            color: #92400e;
            padding: 1rem;
            border-radius: 4px;
            margin-top: 1rem;
            font-weight: 500;
        }
        a { color: #3b82f6; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Platform Admin Setup</h1>

        <div class="status">
            <strong>Nuværende status:</strong><br>
            <?= $currentState ?>
        </div>

        <?php if ($message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
            <p><a href="/admin-platform/login.php">Gå til login</a></p>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="message error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Email (låst)</label>
                    <input type="email" value="mail@hededam.dk" disabled>
                </div>

                <div class="form-group">
                    <label for="name">Navn</label>
                    <input type="text" id="name" name="name" value="Platform Admin" required>
                </div>

                <div class="form-group">
                    <label for="password">Ny adgangskode</label>
                    <input type="password" id="password" name="password" required minlength="8">
                </div>

                <div class="form-group">
                    <label for="confirm_password">Bekræft adgangskode</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                </div>

                <button type="submit">Opret/Opdater Admin</button>
            </form>
        <?php endif; ?>

        <div class="warning">
            SLET DENNE FIL (setup-admin.php) NÅR DU ER FÆRDIG!
        </div>
    </div>
</body>
</html>

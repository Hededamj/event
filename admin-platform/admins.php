<?php
/**
 * Platform Admin - Manage Administrators
 */

require_once __DIR__ . '/../includes/admin-platform-header.php';

$db = getDB();
$myId = getCurrentPlatformAdminId();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $accountId = (int)($_POST['account_id'] ?? 0);

    switch ($action) {
        case 'promote':
            // Promote existing account to admin
            if ($accountId && $accountId !== $myId) {
                $stmt = $db->prepare("UPDATE accounts SET is_platform_admin = 1 WHERE id = ? AND is_active = 1");
                $stmt->execute([$accountId]);
                if ($stmt->rowCount()) {
                    setFlash('success', 'Administrator tilfojet');
                } else {
                    setFlash('error', 'Kontoen kunne ikke gores til administrator. Tjek at kontoen er aktiv.');
                }
            }
            break;

        case 'demote':
            // Remove admin rights (cannot demote yourself)
            if ($accountId && $accountId !== $myId) {
                $stmt = $db->prepare("UPDATE accounts SET is_platform_admin = 0 WHERE id = ? AND id != ?");
                $stmt->execute([$accountId, $myId]);
                if ($stmt->rowCount()) {
                    setFlash('success', 'Administratorrettigheder fjernet');
                } else {
                    setFlash('error', 'Kunne ikke fjerne rettigheder.');
                }
            } else {
                setFlash('error', 'Du kan ikke fjerne dine egne administratorrettigheder.');
            }
            break;

        case 'create':
            // Create new admin account
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            $errors = [];
            if (empty($name)) $errors[] = 'Navn er pakraevet.';
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Gyldig email er pakraevet.';
            if (strlen($password) < 8) $errors[] = 'Adgangskode skal vaere mindst 8 tegn.';

            if (empty($errors)) {
                $chk = $db->prepare("SELECT id FROM accounts WHERE email = ? LIMIT 1");
                $chk->execute([$email]);
                if ($chk->fetch()) {
                    $errors[] = 'Email er allerede registreret.';
                }
            }

            if (empty($errors)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("
                    INSERT INTO accounts (name, email, password_hash, is_active, is_platform_admin, email_verified_at, created_at, updated_at)
                    VALUES (?, ?, ?, 1, 1, NOW(), NOW(), NOW())
                ");
                $stmt->execute([$name, $email, $hash]);
                setFlash('success', 'Ny administrator oprettet: ' . $name);
            } else {
                setFlash('error', implode(' ', $errors));
            }
            break;
    }
    redirect(BASE_PATH . '/admin-platform/admins.php');
}

// Get all admins
$admins = $db->query("
    SELECT id, name, email, is_active, last_login_at, created_at
    FROM accounts
    WHERE is_platform_admin = 1
    ORDER BY created_at ASC
")->fetchAll();

// Get non-admin accounts for promote dropdown
$accounts = $db->query("
    SELECT id, name, email
    FROM accounts
    WHERE is_platform_admin = 0 AND is_active = 1
    ORDER BY name ASC
")->fetchAll();
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>" style="padding: 12px 16px; border-radius: var(--radius-sm); margin-bottom: var(--space-lg); background: <?= $flash['type'] === 'error' ? 'var(--error-light)' : '#E8F0E4' ?>; color: <?= $flash['type'] === 'error' ? 'var(--error)' : 'var(--accent-dark, #4D6E42)' ?>;">
        <?= escape($flash['message']) ?>
    </div>
<?php endif; ?>

<div class="platform-header">
    <h1 class="page-title">Administratorer</h1>
    <div class="header-actions">
        <button type="button" class="btn btn-primary" onclick="document.getElementById('create-modal').style.display='flex'">
            + Opret ny administrator
        </button>
    </div>
</div>

<!-- Current admins -->
<div style="background: var(--surface-card); border: 1px solid var(--border-light); border-radius: var(--radius-lg); overflow: hidden; margin-bottom: var(--space-xl);">
    <div style="padding: var(--space-md) var(--space-lg); border-bottom: 1px solid var(--border-light);">
        <h2 class="card-title" style="margin: 0;">Aktive administratorer (<?= count($admins) ?>)</h2>
    </div>
    <div class="table-container">
        <table class="ds-table">
            <thead>
                <tr>
                    <th>Navn</th>
                    <th>Email</th>
                    <th>Sidst logget ind</th>
                    <th>Oprettet</th>
                    <th style="width: 120px;">Handling</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($admins as $admin): ?>
                <tr>
                    <td>
                        <strong><?= escape($admin['name']) ?></strong>
                        <?php if ($admin['id'] === $myId): ?>
                            <span class="badge badge-info" style="margin-left: 6px;">Dig</span>
                        <?php endif; ?>
                    </td>
                    <td><?= escape($admin['email']) ?></td>
                    <td>
                        <?php if ($admin['last_login_at']): ?>
                            <?= date('d/m/Y H:i', strtotime($admin['last_login_at'])) ?>
                        <?php else: ?>
                            <span class="text-muted">Aldrig</span>
                        <?php endif; ?>
                    </td>
                    <td><?= date('d/m/Y', strtotime($admin['created_at'])) ?></td>
                    <td>
                        <?php if ($admin['id'] !== $myId): ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Er du sikker pa at du vil fjerne administratorrettigheder fra <?= escape($admin['name']) ?>?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="demote">
                            <input type="hidden" name="account_id" value="<?= $admin['id'] ?>">
                            <button type="submit" class="btn btn-sm" style="background:var(--error);color:#fff;border:none;padding:6px 12px;border-radius:var(--radius-sm);font-size:12px;cursor:pointer;">Fjern admin</button>
                        </form>
                        <?php else: ?>
                            <span class="text-muted text-sm">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Promote existing account -->
<?php if (!empty($accounts)): ?>
<div style="background: var(--surface-card); border: 1px solid var(--border-light); border-radius: var(--radius-lg); overflow: hidden; margin-bottom: var(--space-xl);">
    <div style="padding: var(--space-md) var(--space-lg); border-bottom: 1px solid var(--border-light);">
        <h2 class="card-title" style="margin: 0;">Forfrem eksisterende konto</h2>
    </div>
    <div style="padding: var(--space-lg);">
        <p class="text-muted text-sm" style="margin-bottom: var(--space-md);">Giv en eksisterende brugerkonto administratoradgang.</p>
        <form method="POST" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="promote">
            <div style="flex:1;min-width:250px;">
                <label class="form-label" style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Vaelg konto</label>
                <select name="account_id" class="form-input form-select" required style="width:100%;padding:8px 16px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:14px;">
                    <option value="">-- Vaelg en konto --</option>
                    <?php foreach ($accounts as $acc): ?>
                    <option value="<?= $acc['id'] ?>"><?= escape($acc['name']) ?> (<?= escape($acc['email']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="padding:8px 16px;" onclick="return confirm('Vil du give denne konto administratorrettigheder?')">Forfrem til admin</button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Create new admin modal -->
<div id="create-modal" style="display:none;position:fixed;inset:0;z-index:1000;align-items:center;justify-content:center;background:rgba(0,0,0,0.5);" onclick="if(event.target===this)this.style.display='none'">
    <div style="background:var(--surface-card);border-radius:var(--radius-lg);padding:var(--space-xl);width:100%;max-width:440px;box-shadow:0 20px 60px rgba(0,0,0,0.15);">
        <h2 style="font-size:20px;font-weight:600;margin:0 0 var(--space-lg);">Opret ny administrator</h2>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">

            <div style="margin-bottom:var(--space-md);">
                <label class="form-label" style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Navn</label>
                <input type="text" name="name" class="form-input" required style="width:100%;padding:8px 16px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:14px;">
            </div>

            <div style="margin-bottom:var(--space-md);">
                <label class="form-label" style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Email</label>
                <input type="email" name="email" class="form-input" required style="width:100%;padding:8px 16px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:14px;">
            </div>

            <div style="margin-bottom:var(--space-lg);">
                <label class="form-label" style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Adgangskode</label>
                <input type="password" name="password" class="form-input" required minlength="8" style="width:100%;padding:8px 16px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:14px;">
                <span class="text-muted text-xs" style="display:block;margin-top:4px;">Mindst 8 tegn</span>
            </div>

            <div style="display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" class="btn" style="background:var(--surface);color:var(--text);border:1px solid var(--border);padding:8px 16px;border-radius:var(--radius-sm);cursor:pointer;" onclick="document.getElementById('create-modal').style.display='none'">Annuller</button>
                <button type="submit" class="btn btn-primary" style="padding:8px 16px;">Opret administrator</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin-platform-footer.php'; ?>

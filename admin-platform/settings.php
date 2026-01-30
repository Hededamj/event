<?php
/**
 * Platform Admin - Settings
 */

require_once __DIR__ . '/../includes/admin-platform-header.php';

$db = getDB();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $settings = [
            'platform_name' => trim($_POST['platform_name'] ?? ''),
            'support_email' => trim($_POST['support_email'] ?? ''),
            'partner_approval_required' => isset($_POST['partner_approval_required']) ? '1' : '0',
            'commission_percentage' => (int)($_POST['commission_percentage'] ?? 10),
            'trial_days' => (int)($_POST['trial_days'] ?? 14),
        ];

        foreach ($settings as $key => $value) {
            setPlatformSetting($key, (string)$value);
        }

        setFlash('success', 'Indstillinger gemt');
        redirect(BASE_PATH . '/admin-platform/settings.php');
    }
}

// Get current settings
$settings = [
    'platform_name' => getPlatformSetting('platform_name', 'EventPlatform'),
    'support_email' => getPlatformSetting('support_email', ''),
    'partner_approval_required' => getPlatformSetting('partner_approval_required', '1'),
    'commission_percentage' => getPlatformSetting('commission_percentage', '10'),
    'trial_days' => getPlatformSetting('trial_days', '14'),
];

// Get plans for management
$plans = $db->query("SELECT * FROM plans ORDER BY sort_order")->fetchAll();
?>

<header class="platform-header">
    <h1 class="page-title">Indstillinger</h1>
</header>

<div class="platform-content">
    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
            <?= escape($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-lg);">
        <!-- General Settings -->
        <div class="card">
            <h2 class="card-title">Generelle indstillinger</h2>

            <form method="POST">
                <?= csrfField() ?>

                <div class="form-group">
                    <label class="form-label">Platform navn</label>
                    <input type="text" name="platform_name" class="form-input"
                           value="<?= escape($settings['platform_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Support email</label>
                    <input type="email" name="support_email" class="form-input"
                           value="<?= escape($settings['support_email']) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Prøveperiode (dage)</label>
                    <input type="number" name="trial_days" class="form-input"
                           value="<?= escape($settings['trial_days']) ?>" min="0" max="90">
                </div>

                <button type="submit" class="btn btn-primary">Gem indstillinger</button>
            </form>
        </div>

        <!-- Partner Settings -->
        <div class="card">
            <h2 class="card-title">Partner indstillinger</h2>

            <form method="POST">
                <?= csrfField() ?>

                <input type="hidden" name="platform_name" value="<?= escape($settings['platform_name']) ?>">
                <input type="hidden" name="support_email" value="<?= escape($settings['support_email']) ?>">
                <input type="hidden" name="trial_days" value="<?= escape($settings['trial_days']) ?>">

                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" name="partner_approval_required"
                               <?= $settings['partner_approval_required'] === '1' ? 'checked' : '' ?>>
                        Kræv godkendelse af nye partnere
                    </label>
                    <p class="text-sm text-muted">Hvis aktiveret, skal nye partnere godkendes manuelt før de vises på markedspladsen.</p>
                </div>

                <div class="form-group">
                    <label class="form-label">Kommission (%)</label>
                    <input type="number" name="commission_percentage" class="form-input"
                           value="<?= escape($settings['commission_percentage']) ?>" min="0" max="50">
                    <p class="text-sm text-muted">Platform kommission på partner bookinger.</p>
                </div>

                <button type="submit" class="btn btn-primary">Gem indstillinger</button>
            </form>
        </div>
    </div>

    <!-- Plans -->
    <div class="card mt-lg">
        <h2 class="card-title">Abonnementsplaner</h2>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Plan</th>
                        <th>Pris/md</th>
                        <th>Pris/år</th>
                        <th>Maks gæster</th>
                        <th>Maks events</th>
                        <th>Features</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plans as $plan): ?>
                        <?php $features = json_decode($plan['features'] ?? '{}', true); ?>
                        <tr>
                            <td>
                                <div class="font-medium"><?= escape($plan['name']) ?></div>
                                <div class="text-xs text-muted"><?= escape($plan['slug']) ?></div>
                            </td>
                            <td><?= number_format($plan['price_monthly'], 0, ',', '.') ?> kr</td>
                            <td><?= number_format($plan['price_yearly'], 0, ',', '.') ?> kr</td>
                            <td><?= number_format($plan['max_guests']) ?></td>
                            <td><?= $plan['max_events'] >= 999 ? 'Ubegrænset' : $plan['max_events'] ?></td>
                            <td class="text-sm">
                                <?php if ($features): ?>
                                    <?php foreach ($features as $feature => $enabled): ?>
                                        <?php if ($enabled): ?>
                                            <span class="badge badge-success" style="margin-right: 2px; margin-bottom: 2px;">
                                                <?= escape(ucfirst($feature)) ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($plan['is_active']): ?>
                                    <span class="badge badge-success">Aktiv</span>
                                <?php else: ?>
                                    <span class="badge badge-error">Inaktiv</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p class="text-sm text-muted mt-md">
            For at ændre planer, kontakt venligst den tekniske administrator.
        </p>
    </div>

    <!-- Admin Users -->
    <div class="card mt-lg">
        <h2 class="card-title">Platform administratorer</h2>

        <?php
        $admins = $db->query("SELECT id, name, email, last_login_at, created_at FROM accounts WHERE is_platform_admin = 1")->fetchAll();
        ?>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Navn</th>
                        <th>Email</th>
                        <th>Sidst logget ind</th>
                        <th>Oprettet</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($admins as $admin): ?>
                        <tr>
                            <td class="font-medium"><?= escape($admin['name']) ?></td>
                            <td><?= escape($admin['email']) ?></td>
                            <td class="text-sm text-muted">
                                <?= $admin['last_login_at'] ? date('d/m/Y H:i', strtotime($admin['last_login_at'])) : 'Aldrig' ?>
                            </td>
                            <td class="text-sm text-muted"><?= date('d/m/Y', strtotime($admin['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin-platform-footer.php'; ?>

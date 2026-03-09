<?php
/**
 * Platform Admin - Agent Management
 * Lists all referral agents with stats, create/activate/deactivate actions.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin-platform-header.php';
require_once __DIR__ . '/../subcontractor/includes/referral-service.php';

$db = getDB();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        switch ($action) {
            case 'create':
                $email = trim($_POST['email'] ?? '');
                $provisionRate = trim($_POST['provision_rate'] ?? '');
                $notes = trim($_POST['notes'] ?? '');

                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    setFlash('error', 'Gyldig email er paakraevet.');
                    break;
                }

                // Look up account by email
                $stmt = $db->prepare("SELECT id, name, email FROM accounts WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $account = $stmt->fetch();

                if (!$account) {
                    setFlash('error', 'Ingen konto fundet med denne email.');
                    break;
                }

                // Check if already an agent
                $existing = getAgentByAccountId($account['id']);
                if ($existing) {
                    setFlash('error', 'Denne konto er allerede registreret som agent.');
                    break;
                }

                $rate = $provisionRate !== '' ? (float)$provisionRate / 100 : null;
                $code = createAgent($account['id'], $rate, $notes ?: null);

                if ($code) {
                    setFlash('success', 'Agent oprettet med kode: ' . $code);
                } else {
                    setFlash('error', 'Kunne ikke oprette agent. Proev igen.');
                }
                break;

            case 'deactivate':
                $agentId = (int)($_POST['agent_id'] ?? 0);
                if ($agentId) {
                    deactivateAgent($agentId);
                    setFlash('success', 'Agent deaktiveret');
                }
                break;

            case 'activate':
                $agentId = (int)($_POST['agent_id'] ?? 0);
                if ($agentId) {
                    activateAgent($agentId);
                    setFlash('success', 'Agent aktiveret');
                }
                break;
        }
        redirect(BASE_PATH . '/admin-platform/agents.php');
    }
}

// Get all agents with account info and stats
$agents = $db->query("
    SELECT ra.*,
           a.name AS account_name,
           a.email AS account_email,
           (SELECT COUNT(*) FROM referral_links rl WHERE rl.agent_id = ra.id) AS referral_count,
           (SELECT COALESCE(SUM(rl2.total_earned), 0) FROM referral_links rl2 WHERE rl2.agent_id = ra.id) AS total_earned
    FROM referral_agents ra
    JOIN accounts a ON ra.account_id = a.id
    ORDER BY ra.created_at DESC
")->fetchAll();

// Stats
$totalAgents = count($agents);
$activeAgents = 0;
$totalPaid = 0;
foreach ($agents as $ag) {
    if ($ag['is_active']) $activeAgents++;
    $totalPaid += (float)$ag['total_earned'];
}
?>

<header class="platform-header">
    <h1 class="page-title">Agenter</h1>
    <div class="header-actions">
        <button type="button" class="btn btn-primary" onclick="showCreateModal()">+ Opret agent</button>
    </div>
</header>

<div class="platform-content">
    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
            <?= escape($flash['message']) ?>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid mb-lg">
        <div class="stat-card">
            <div class="stat-label">Total agenter</div>
            <div class="stat-value"><?= number_format($totalAgents) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Aktive agenter</div>
            <div class="stat-value text-success"><?= number_format($activeAgents) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total provision udbetalt</div>
            <div class="stat-value"><?= number_format($totalPaid, 2, ',', '.') ?> kr.</div>
        </div>
    </div>

    <!-- Agents Table -->
    <div class="card">
        <?php if (empty($agents)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
                    </svg>
                </div>
                <p>Ingen agenter oprettet endnu</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Agent</th>
                            <th>Email</th>
                            <th>Referral-kode</th>
                            <th>Referrals</th>
                            <th>Optjent</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($agents as $ag): ?>
                            <tr>
                                <td class="font-medium"><?= escape($ag['account_name']) ?></td>
                                <td class="text-sm"><?= escape($ag['account_email']) ?></td>
                                <td class="text-sm">
                                    <code style="font-size: 12px; background: var(--surface); padding: 2px 6px; border-radius: 4px;">
                                        <?= escape($ag['referral_code']) ?>
                                    </code>
                                </td>
                                <td class="text-sm"><?= number_format($ag['referral_count']) ?></td>
                                <td class="text-sm font-medium"><?= number_format((float)$ag['total_earned'], 2, ',', '.') ?> kr.</td>
                                <td>
                                    <?php if ($ag['is_active']): ?>
                                        <span class="badge badge-success">Aktiv</span>
                                    <?php else: ?>
                                        <span class="badge badge-error">Inaktiv</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="flex gap-sm" style="flex-wrap: wrap;">
                                        <a href="<?= BASE_PATH ?>/admin-platform/agent-detail.php?id=<?= $ag['id'] ?>"
                                           class="btn btn-secondary btn-sm">
                                            Detaljer
                                        </a>

                                        <?php if ($ag['is_active']): ?>
                                            <form method="POST" style="display: inline;">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="agent_id" value="<?= $ag['id'] ?>">
                                                <button type="submit" name="action" value="deactivate" class="btn btn-danger btn-sm"
                                                        onclick="return confirm('Deaktiver denne agent?')">
                                                    Deaktiver
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline;">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="agent_id" value="<?= $ag['id'] ?>">
                                                <button type="submit" name="action" value="activate" class="btn btn-success btn-sm">
                                                    Aktiver
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Agent Modal -->
<div id="createModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 12px; padding: 2rem; max-width: 480px; width: 90%;">
        <h3 style="margin-bottom: 1rem;">Opret agent</h3>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label class="form-label">Email (konto-email)</label>
                <input type="email" name="email" class="form-input" required placeholder="bruger@email.dk">
                <p class="text-xs text-muted" style="margin-top: 4px;">Kontoen skal allerede vaere oprettet paa platformen.</p>
            </div>

            <div class="form-group">
                <label class="form-label">Provisionssats (valgfrit)</label>
                <input type="number" name="provision_rate" class="form-input" step="0.01" min="0" max="100" placeholder="Standard: 1%">
                <p class="text-xs text-muted" style="margin-top: 4px;">Angiv i procent. Lad feltet staa tomt for standardsats (1%).</p>
            </div>

            <div class="form-group">
                <label class="form-label">Noter (valgfrit)</label>
                <textarea name="notes" class="form-input" rows="3" placeholder="Interne noter om denne agent..."></textarea>
            </div>

            <div class="flex gap-sm">
                <button type="submit" class="btn btn-primary">Opret</button>
                <button type="button" class="btn btn-secondary" onclick="hideCreateModal()">Annuller</button>
            </div>
        </form>
    </div>
</div>

<script>
function showCreateModal() {
    document.getElementById('createModal').style.display = 'flex';
}

function hideCreateModal() {
    document.getElementById('createModal').style.display = 'none';
}

// Close modal on backdrop click
document.getElementById('createModal').addEventListener('click', function(e) {
    if (e.target === this) hideCreateModal();
});
</script>

<?php require_once __DIR__ . '/../includes/admin-platform-footer.php'; ?>

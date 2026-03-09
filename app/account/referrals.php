<?php
/**
 * Agent Dashboard - Referral Program
 * Shows referral link, stats, and referred vendors for active agents.
 */
$pageTitle = 'Referral-program';
require_once __DIR__ . '/../../includes/app-header.php';
require_once __DIR__ . '/../../subcontractor/includes/referral-service.php';

// Check if this account is an active agent
$agent = getAgentByAccountId($accountId);

if (!$agent) {
    ?>
    <div class="page-header">
        <div>
            <h1 class="page-title">Referral-program</h1>
        </div>
    </div>
    <div class="card" style="text-align: center; padding: 3rem;">
        <p style="color: var(--text-secondary); font-size: 16px;">
            Du er ikke aktiveret som agent. Kontakt os hvis du er interesseret i at blive referral-agent.
        </p>
    </div>
    <?php
    require_once __DIR__ . '/../../includes/app-footer.php';
    exit;
}

// Get dashboard data
$dashboard = getAgentDashboard($agent['id']);
$referrals = $dashboard['referrals'] ?? [];
$stats = $dashboard['stats'] ?? [];
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Referral-program</h1>
        <p class="page-subtitle">Del dit link og optjen provision for henviste leverandoerer</p>
    </div>
</div>

<!-- Referral Link -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <h2 class="card-title">Dit referral-link</h2>
    </div>
    <p style="color: var(--text-secondary); font-size: 14px; margin-bottom: 16px;">
        Del dette link med leverandoerer du kender. Naar de opretter sig og faar bookinger, optjener du provision.
    </p>
    <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
        <input
            type="text"
            id="referralLink"
            class="form-input"
            value="https://partyparart.dk/bliv-leverandor?ref=<?= htmlspecialchars($agent['referral_code']) ?>"
            readonly
            style="flex: 1; min-width: 250px; background: var(--surface); cursor: text;"
        >
        <button type="button" class="btn btn-primary" onclick="copyReferralLink()">
            Kopier link
        </button>
    </div>
    <p id="copyFeedback" style="color: var(--accent); font-size: 13px; margin-top: 8px; display: none;">
        Link kopieret!
    </p>
</div>

<!-- Stats -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-label">Referrede leverandoerer</div>
        <div class="stat-value"><?= (int)($stats['approved_count'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Aktive leverandoerer</div>
        <div class="stat-value"><?= (int)($stats['active_count'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Optjent provision</div>
        <div class="stat-value"><?= number_format((float)($stats['total_earned'] ?? 0), 2, ',', '.') ?> kr.</div>
    </div>
</div>

<!-- Referral Table -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Henviste leverandoerer</h2>
    </div>

    <?php if (empty($referrals)): ?>
        <div style="text-align: center; padding: 2rem; color: var(--text-secondary);">
            <p>Du har endnu ikke henvist nogen leverandoerer.</p>
            <p style="font-size: 13px; margin-top: 8px;">Del dit referral-link ovenfor for at komme i gang.</p>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="text-align: left; padding: 12px 16px; border-bottom: 2px solid var(--border-light); font-size: 13px; color: var(--text-secondary); font-weight: 600;">Leverandoer</th>
                        <th style="text-align: left; padding: 12px 16px; border-bottom: 2px solid var(--border-light); font-size: 13px; color: var(--text-secondary); font-weight: 600;">Registreret</th>
                        <th style="text-align: left; padding: 12px 16px; border-bottom: 2px solid var(--border-light); font-size: 13px; color: var(--text-secondary); font-weight: 600;">Status</th>
                        <th style="text-align: left; padding: 12px 16px; border-bottom: 2px solid var(--border-light); font-size: 13px; color: var(--text-secondary); font-weight: 600;">Provision udloeber</th>
                        <th style="text-align: right; padding: 12px 16px; border-bottom: 2px solid var(--border-light); font-size: 13px; color: var(--text-secondary); font-weight: 600;">Optjent</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($referrals as $ref): ?>
                        <tr>
                            <td style="padding: 12px 16px; border-bottom: 1px solid var(--border-light); font-size: 14px;">
                                <?= htmlspecialchars($ref['company_name'] ?? '-') ?>
                            </td>
                            <td style="padding: 12px 16px; border-bottom: 1px solid var(--border-light); font-size: 14px; color: var(--text-secondary);">
                                <?= $ref['created_at'] ? date('d/m/Y', strtotime($ref['created_at'])) : '-' ?>
                            </td>
                            <td style="padding: 12px 16px; border-bottom: 1px solid var(--border-light);">
                                <?php
                                $vendorStatus = $ref['vendor_status'] ?? 'pending';
                                $badgeClass = 'badge-warning';
                                $badgeText = 'Afventer';
                                if ($vendorStatus === 'approved') {
                                    $badgeClass = 'badge-success';
                                    $badgeText = 'Godkendt';
                                } elseif ($vendorStatus === 'rejected') {
                                    $badgeClass = 'badge-error';
                                    $badgeText = 'Afvist';
                                }
                                ?>
                                <span class="badge <?= $badgeClass ?>"><?= $badgeText ?></span>
                            </td>
                            <td style="padding: 12px 16px; border-bottom: 1px solid var(--border-light); font-size: 14px; color: var(--text-secondary);">
                                <?= $ref['provision_until'] ? date('d/m/Y', strtotime($ref['provision_until'])) : '-' ?>
                            </td>
                            <td style="padding: 12px 16px; border-bottom: 1px solid var(--border-light); font-size: 14px; text-align: right; font-weight: 500;">
                                <?= number_format((float)($ref['total_earned'] ?? 0), 2, ',', '.') ?> kr.
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
    }

    .stat-card {
        background: var(--surface-card);
        border: 1px solid var(--border-light);
        border-radius: var(--radius-md);
        padding: 20px;
    }

    .stat-label {
        font-size: 13px;
        color: var(--text-secondary);
        margin-bottom: 8px;
    }

    .stat-value {
        font-size: 24px;
        font-weight: 700;
        color: var(--text);
    }

    @media (max-width: 640px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
function copyReferralLink() {
    var input = document.getElementById('referralLink');
    navigator.clipboard.writeText(input.value).then(function() {
        var feedback = document.getElementById('copyFeedback');
        feedback.style.display = 'block';
        setTimeout(function() {
            feedback.style.display = 'none';
        }, 3000);
    }).catch(function() {
        // Fallback for older browsers
        input.select();
        document.execCommand('copy');
        var feedback = document.getElementById('copyFeedback');
        feedback.style.display = 'block';
        setTimeout(function() {
            feedback.style.display = 'none';
        }, 3000);
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/app-footer.php'; ?>

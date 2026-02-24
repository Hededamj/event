<?php
/**
 * Invitation Send Interface
 * Admin UI for sending invitation emails to guests
 */

require_once __DIR__ . '/../../../includes/invitation-functions.php';
require_once __DIR__ . '/../../../includes/email-service.php';

$emailService = getEmailService($db);
$invitationConfig = getInvitationConfig($db, $eventId);

// Check if email is configured
$emailConfigured = $emailService->isConfigured();

// Get all guests for this event
$stmt = $db->prepare("
    SELECT g.*,
           (SELECT COUNT(*) FROM invitation_emails ie WHERE ie.guest_id = g.id AND ie.email_type = 'invitation') as invitation_sent_count,
           (SELECT ie.status FROM invitation_emails ie WHERE ie.guest_id = g.id AND ie.email_type = 'invitation' ORDER BY ie.created_at DESC LIMIT 1) as last_invitation_status,
           (SELECT ie.sent_at FROM invitation_emails ie WHERE ie.guest_id = g.id AND ie.email_type = 'invitation' ORDER BY ie.created_at DESC LIMIT 1) as last_sent_at
    FROM guests g
    WHERE g.event_id = ?
    ORDER BY g.name ASC
");
$stmt->execute([$eventId]);
$guests = $stmt->fetchAll();

// Get email statistics
$emailStats = getInvitationEmailStats($db, $eventId);

// Handle send actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $emailConfigured) {
    $action = $_POST['action'] ?? '';

    if ($action === 'send-selected') {
        $selectedGuests = $_POST['guests'] ?? [];
        $emailType = $_POST['email_type'] ?? 'invitation';

        if (!empty($selectedGuests)) {
            // Get selected guest data
            $placeholders = implode(',', array_fill(0, count($selectedGuests), '?'));
            $stmt = $db->prepare("SELECT * FROM guests WHERE id IN ($placeholders) AND event_id = ?");
            $stmt->execute([...$selectedGuests, $eventId]);
            $guestsToEmail = $stmt->fetchAll();

            $results = ['success' => 0, 'failed' => 0, 'errors' => []];

            foreach ($guestsToEmail as $guest) {
                if (empty($guest['email'])) {
                    $results['failed']++;
                    $results['errors'][] = ['name' => $guest['name'], 'error' => 'Ingen email'];
                    continue;
                }

                if ($emailType === 'reminder') {
                    $result = $emailService->sendReminder($guest, $event, $invitationConfig);
                } else {
                    $result = $emailService->sendInvitation($guest, $event, $invitationConfig);
                }

                if ($result['success']) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = ['name' => $guest['name'], 'error' => $result['error']];
                }
            }

            if ($results['success'] > 0) {
                setFlash('success', "{$results['success']} email(s) sendt succesfuldt" .
                    ($results['failed'] > 0 ? ", {$results['failed']} fejlede" : ""));
            } else {
                setFlash('error', "Kunne ikke sende emails. {$results['failed']} fejlede.");
            }
        }

        redirect("/app/events/manage.php?id=$eventId&page=invitation-send");
    }

    if ($action === 'send-all-pending') {
        $guestsWithoutEmail = getGuestsWithoutInvitation($db, $eventId);
        $results = $emailService->sendBulkInvitations($guestsWithoutEmail, $event, $invitationConfig);

        if ($results['success'] > 0) {
            setFlash('success', "{$results['success']} invitationer sendt!" .
                ($results['failed'] > 0 ? " {$results['failed']} fejlede." : ""));
        } else {
            setFlash('error', "Ingen invitationer blev sendt.");
        }

        redirect("/app/events/manage.php?id=$eventId&page=invitation-send");
    }
}

// Filter guests
$filter = $_GET['filter'] ?? 'all';
$filteredGuests = match($filter) {
    'not-sent' => array_filter($guests, fn($g) => $g['invitation_sent_count'] == 0 && !empty($g['email'])),
    'sent' => array_filter($guests, fn($g) => $g['invitation_sent_count'] > 0),
    'no-email' => array_filter($guests, fn($g) => empty($g['email'])),
    'pending' => array_filter($guests, fn($g) => $g['rsvp_status'] === 'pending'),
    default => $guests
};
?>

<style>
    .stats-row {
        display: flex;
        gap: 16px;
        margin-bottom: 24px;
        flex-wrap: wrap;
    }

    .stat-mini {
        background: var(--white);
        padding: 16px 20px;
        border-radius: 12px;
        border: 1px solid var(--border);
        min-width: 120px;
    }

    .stat-mini-value {
        font-size: 24px;
        font-weight: 600;
        color: var(--text);
    }

    .stat-mini-label {
        font-size: 12px;
        color: var(--text-secondary);
        margin-top: 2px;
    }

    .stat-mini.success .stat-mini-value { color: var(--success); }
    .stat-mini.warning .stat-mini-value { color: var(--warning); }
    .stat-mini.error .stat-mini-value { color: var(--error); }

    .guest-table {
        width: 100%;
        border-collapse: collapse;
    }

    .guest-table th,
    .guest-table td {
        padding: 14px 16px;
        text-align: left;
        border-bottom: 1px solid var(--border);
    }

    .guest-table th {
        font-size: 12px;
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        background: var(--surface);
    }

    .guest-table tr:hover td {
        background: #FAFAF8;
    }

    .guest-name {
        font-weight: 500;
    }

    .guest-email {
        font-size: 13px;
        color: var(--text-secondary);
    }

    .no-email {
        font-size: 12px;
        color: #B8B0A0;
        font-style: italic;
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
    }

    .status-pill.sent { background: #E8F5E8; color: var(--success); }
    .status-pill.delivered { background: #E0F2FE; color: #0369A1; }
    .status-pill.opened { background: #FEF3C7; color: #B45309; }
    .status-pill.clicked { background: #DCFCE7; color: #15803D; }
    .status-pill.bounced { background: #FEE2E2; color: var(--error); }
    .status-pill.failed { background: #FEE2E2; color: var(--error); }
    .status-pill.pending { background: var(--surface); color: var(--text-secondary); }
    .status-pill.not-sent { background: var(--border); color: var(--text-secondary); }

    .checkbox-cell {
        width: 40px;
    }

    .checkbox-cell input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }

    .bulk-actions {
        display: flex;
        gap: 12px;
        align-items: center;
        padding: 16px;
        background: var(--surface);
        border-radius: 12px;
        margin-bottom: 20px;
    }

    .bulk-actions span {
        font-size: 14px;
        color: var(--text-secondary);
    }

    .config-warning {
        background: #FEF3C7;
        border: 1px solid #FDE68A;
        border-radius: 14px;
        padding: 20px 24px;
        margin-bottom: 24px;
        display: flex;
        align-items: flex-start;
        gap: 16px;
    }

    .config-warning svg {
        width: 24px;
        height: 24px;
        color: #B45309;
        flex-shrink: 0;
    }

    .config-warning h4 {
        font-size: 15px;
        color: #92400E;
        margin-bottom: 4px;
    }

    .config-warning p {
        font-size: 14px;
        color: #A16207;
    }

    .config-warning code {
        background: rgba(0,0,0,0.06);
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 13px;
    }

    .send-date {
        font-size: 12px;
        color: var(--text-secondary);
    }
</style>

<div class="page-header-actions">
    <div>
        <h2 class="section-title">Send invitationer</h2>
        <p class="section-subtitle">Send invitationer via email til dine gæster</p>
    </div>
    <a href="?id=<?= $eventId ?>&page=invitation" class="btn btn-secondary">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
        </svg>
        Tilbage til invitation
    </a>
</div>

<?php if (!$emailConfigured): ?>
<div class="config-warning">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
    </svg>
    <div>
        <h4>Email service ikke konfigureret</h4>
        <p>
            For at sende emails skal du tilføje din Resend API nøgle i <code>.env</code> filen:<br>
            <code>RESEND_API_KEY=re_xxxxxx</code>
        </p>
    </div>
</div>
<?php endif; ?>

<?php if (empty($invitationConfig['is_published'])): ?>
<div class="config-warning">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
    </svg>
    <div>
        <h4>Invitation ikke offentliggjort</h4>
        <p>
            Din invitation er ikke offentliggjort endnu. Gæster kan se invitationen efter du <a href="?id=<?= $eventId ?>&page=invitation&step=6">offentliggør den</a>.
        </p>
    </div>
</div>
<?php endif; ?>

<!-- Email Statistics -->
<div class="stats-row">
    <div class="stat-mini">
        <div class="stat-mini-value"><?= count($guests) ?></div>
        <div class="stat-mini-label">Gæster i alt</div>
    </div>
    <div class="stat-mini success">
        <div class="stat-mini-value"><?= $emailStats['sent'] + $emailStats['delivered'] + $emailStats['opened'] + $emailStats['clicked'] ?></div>
        <div class="stat-mini-label">Sendt</div>
    </div>
    <div class="stat-mini warning">
        <div class="stat-mini-value"><?= $emailStats['opened'] + $emailStats['clicked'] ?></div>
        <div class="stat-mini-label">Åbnet</div>
    </div>
    <div class="stat-mini">
        <div class="stat-mini-value"><?= $emailStats['clicked'] ?></div>
        <div class="stat-mini-label">Klikket</div>
    </div>
    <div class="stat-mini error">
        <div class="stat-mini-value"><?= $emailStats['bounced'] + $emailStats['failed'] ?></div>
        <div class="stat-mini-label">Fejlet</div>
    </div>
</div>

<!-- Filter Tabs -->
<div class="filters-bar">
    <div class="filter-tabs">
        <a href="?id=<?= $eventId ?>&page=invitation-send&filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">
            Alle (<?= count($guests) ?>)
        </a>
        <a href="?id=<?= $eventId ?>&page=invitation-send&filter=not-sent" class="filter-tab <?= $filter === 'not-sent' ? 'active' : '' ?>">
            Ikke sendt (<?= count(array_filter($guests, fn($g) => $g['invitation_sent_count'] == 0 && !empty($g['email']))) ?>)
        </a>
        <a href="?id=<?= $eventId ?>&page=invitation-send&filter=sent" class="filter-tab <?= $filter === 'sent' ? 'active' : '' ?>">
            Sendt (<?= count(array_filter($guests, fn($g) => $g['invitation_sent_count'] > 0)) ?>)
        </a>
        <a href="?id=<?= $eventId ?>&page=invitation-send&filter=pending" class="filter-tab <?= $filter === 'pending' ? 'active' : '' ?>">
            Afventer svar (<?= count(array_filter($guests, fn($g) => $g['rsvp_status'] === 'pending')) ?>)
        </a>
        <a href="?id=<?= $eventId ?>&page=invitation-send&filter=no-email" class="filter-tab <?= $filter === 'no-email' ? 'active' : '' ?>">
            Ingen email (<?= count(array_filter($guests, fn($g) => empty($g['email']))) ?>)
        </a>
    </div>
</div>

<div class="card">
    <form method="POST" id="send-form">
        <input type="hidden" name="action" value="send-selected">

        <div class="bulk-actions">
            <span id="selected-count">0 valgt</span>
            <select name="email_type" class="form-input" style="width: auto; padding: 10px 40px 10px 14px;">
                <option value="invitation">Send invitation</option>
                <option value="reminder">Send påmindelse</option>
            </select>
            <button type="submit" class="btn btn-sage" <?= !$emailConfigured ? 'disabled' : '' ?>>
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
                Send til valgte
            </button>
        </div>

        <table class="guest-table">
            <thead>
                <tr>
                    <th class="checkbox-cell">
                        <input type="checkbox" id="select-all">
                    </th>
                    <th>Gæst</th>
                    <th>RSVP</th>
                    <th>Email status</th>
                    <th>Sendt</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filteredGuests as $guest): ?>
                <tr>
                    <td class="checkbox-cell">
                        <?php if (!empty($guest['email'])): ?>
                        <input type="checkbox" name="guests[]" value="<?= $guest['id'] ?>" class="guest-checkbox">
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="guest-name"><?= htmlspecialchars($guest['name']) ?></div>
                        <?php if (!empty($guest['email'])): ?>
                        <div class="guest-email"><?= htmlspecialchars($guest['email']) ?></div>
                        <?php else: ?>
                        <div class="no-email">Ingen email</div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $rsvpClass = match($guest['rsvp_status']) {
                            'yes' => 'sent',
                            'no' => 'failed',
                            default => 'pending'
                        };
                        $rsvpText = match($guest['rsvp_status']) {
                            'yes' => 'Deltager',
                            'no' => 'Deltager ikke',
                            default => 'Afventer'
                        };
                        ?>
                        <span class="status-pill <?= $rsvpClass ?>"><?= $rsvpText ?></span>
                    </td>
                    <td>
                        <?php if ($guest['invitation_sent_count'] > 0): ?>
                        <span class="status-pill <?= $guest['last_invitation_status'] ?? 'sent' ?>">
                            <?= match($guest['last_invitation_status']) {
                                'delivered' => 'Leveret',
                                'opened' => 'Åbnet',
                                'clicked' => 'Klikket',
                                'bounced' => 'Bounced',
                                'failed' => 'Fejlet',
                                default => 'Sendt'
                            } ?>
                        </span>
                        <?php else: ?>
                        <span class="status-pill not-sent">Ikke sendt</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($guest['last_sent_at']): ?>
                        <span class="send-date"><?= (new DateTime($guest['last_sent_at']))->format('d/m/Y H:i') ?></span>
                        <?php else: ?>
                        <span class="send-date">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php if (empty($filteredGuests)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                        Ingen gæster matcher dette filter
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </form>
</div>

<!-- Quick Send All Button -->
<?php
$guestsWithoutInvitation = count(array_filter($guests, fn($g) => $g['invitation_sent_count'] == 0 && !empty($g['email'])));
if ($guestsWithoutInvitation > 0 && $emailConfigured):
?>
<div class="card" style="text-align: center; padding: 32px;">
    <h3 class="card-title" style="margin-bottom: 12px;">Send til alle der ikke har modtaget</h3>
    <p style="color: var(--text-secondary); margin-bottom: 20px;">
        <?= $guestsWithoutInvitation ?> gæst(er) har ikke modtaget en invitation endnu
    </p>
    <form method="POST" style="display: inline;">
        <input type="hidden" name="action" value="send-all-pending">
        <button type="submit" class="btn btn-primary" onclick="return confirm('Er du sikker på at du vil sende invitationer til alle <?= $guestsWithoutInvitation ?> gæster?')">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
            </svg>
            Send til alle (<?= $guestsWithoutInvitation ?>)
        </button>
    </form>
</div>
<?php endif; ?>

<script>
const selectAll = document.getElementById('select-all');
const checkboxes = document.querySelectorAll('.guest-checkbox');
const selectedCount = document.getElementById('selected-count');

function updateSelectedCount() {
    const count = document.querySelectorAll('.guest-checkbox:checked').length;
    selectedCount.textContent = count + ' valgt';
}

selectAll?.addEventListener('change', () => {
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
    updateSelectedCount();
});

checkboxes.forEach(cb => {
    cb.addEventListener('change', updateSelectedCount);
});
</script>

<?php
/**
 * User Dashboard - Overview of all events
 */
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/app-header.php';

// Check for welcome message (new registration)
$showWelcome = isset($_GET['welcome']);

// Get event statistics for each event
$eventStats = [];
foreach ($userEvents as $event) {
    $eventId = $event['id'];

    // Get guest counts
    $stmt = $db->prepare("
        SELECT
            COUNT(*) as total_guests,
            SUM(CASE WHEN rsvp_status = 'accepted' THEN 1 ELSE 0 END) as accepted,
            SUM(CASE WHEN rsvp_status = 'declined' THEN 1 ELSE 0 END) as declined,
            SUM(CASE WHEN rsvp_status = 'pending' THEN 1 ELSE 0 END) as pending
        FROM guests
        WHERE event_id = ?
    ");
    $stmt->execute([$eventId]);
    $guestStats = $stmt->fetch();

    $eventStats[$eventId] = [
        'guests' => $guestStats
    ];
}

// Check if user can create more events
$canCreate = canCreateEvent($accountId);
$eventLimit = getAccountEventLimit($accountId);
$currentEventCount = count($userEvents);
?>

<?php if ($showWelcome): ?>
<div class="welcome-banner">
    <div class="welcome-content">
        <h2>Velkommen til EventPlatform!</h2>
        <p>Din konto er oprettet og klar til brug. Kom i gang med at oprette dit første arrangement.</p>
        <a href="/app/events/create.php" class="btn btn-primary">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Opret dit første arrangement
        </a>
    </div>
</div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Mine arrangementer</h1>
        <p class="page-subtitle"><?= $currentEventCount ?> af <?= $eventLimit === 999 ? 'ubegrænset' : $eventLimit ?> arrangementer</p>
    </div>
    <?php if ($canCreate): ?>
    <a href="/app/events/create.php" class="btn btn-primary">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        Nyt arrangement
    </a>
    <?php else: ?>
    <a href="/app/account/subscription.php" class="btn btn-secondary">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
        </svg>
        Opgrader for flere
    </a>
    <?php endif; ?>
</div>

<?php if (empty($userEvents)): ?>
    <div class="card">
        <div class="empty-state">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            <h3>Ingen arrangementer endnu</h3>
            <p>Opret dit første arrangement og begynd at invitere gæster.</p>
            <a href="/app/events/create.php" class="btn btn-primary">Opret arrangement</a>
        </div>
    </div>
<?php else: ?>
    <div class="events-grid">
        <?php foreach ($userEvents as $event):
            $stats = $eventStats[$event['id']] ?? [];
            $guestStats = $stats['guests'] ?? [];
            $totalGuests = (int)($guestStats['total_guests'] ?? 0);
            $acceptedGuests = (int)($guestStats['accepted'] ?? 0);
            $declinedGuests = (int)($guestStats['declined'] ?? 0);
            $pendingGuests = (int)($guestStats['pending'] ?? 0);

            $eventDate = $event['event_date'] ?? null;
            $daysUntil = $eventDate ? (int)((strtotime($eventDate) - time()) / 86400) : null;
        ?>
        <div class="event-card">
            <div class="event-card-header">
                <div class="event-type-badge">
                    <?= htmlspecialchars($event['event_type_name'] ?? 'Arrangement') ?>
                </div>
                <div class="event-status status-<?= htmlspecialchars($event['status'] ?? 'active') ?>">
                    <?php
                    $statusLabels = [
                        'draft' => 'Kladde',
                        'active' => 'Aktiv',
                        'completed' => 'Afsluttet',
                        'archived' => 'Arkiveret'
                    ];
                    echo htmlspecialchars($statusLabels[$event['status'] ?? 'active'] ?? $event['status']);
                    ?>
                </div>
            </div>

            <h3 class="event-card-title">
                <?= htmlspecialchars($event['name'] ?? $event['main_person_name'] ?? 'Arrangement') ?>
            </h3>

            <?php if ($eventDate): ?>
            <div class="event-card-date">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <?= htmlspecialchars(formatDate($eventDate, true)) ?>
                <?php if ($daysUntil !== null && $daysUntil >= 0): ?>
                    <span class="days-until">(<?= $daysUntil === 0 ? 'I dag!' : "om $daysUntil dage" ?>)</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="event-card-stats">
                <div class="stat">
                    <span class="stat-value"><?= $totalGuests ?></span>
                    <span class="stat-label">Gæster</span>
                </div>
                <div class="stat">
                    <span class="stat-value stat-success"><?= $acceptedGuests ?></span>
                    <span class="stat-label">Kommer</span>
                </div>
                <div class="stat">
                    <span class="stat-value stat-danger"><?= $declinedGuests ?></span>
                    <span class="stat-label">Afbud</span>
                </div>
                <div class="stat">
                    <span class="stat-value stat-warning"><?= $pendingGuests ?></span>
                    <span class="stat-label">Afventer</span>
                </div>
            </div>

            <?php if ($totalGuests > 0): ?>
            <div class="rsvp-progress">
                <div class="rsvp-bar">
                    <div class="rsvp-bar-accepted" style="width: <?= ($acceptedGuests / $totalGuests) * 100 ?>%"></div>
                    <div class="rsvp-bar-declined" style="width: <?= ($declinedGuests / $totalGuests) * 100 ?>%"></div>
                </div>
            </div>
            <?php endif; ?>

            <div class="event-card-actions">
                <a href="/app/events/manage.php?id=<?= $event['id'] ?>" class="btn btn-primary btn-sm">
                    Administrer
                </a>
                <?php if ($event['slug']): ?>
                <a href="/e/<?= htmlspecialchars($event['slug']) ?>/" class="btn btn-secondary btn-sm" target="_blank">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                    </svg>
                    Se side
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if ($canCreate): ?>
        <a href="/app/events/create.php" class="event-card event-card-create">
            <div class="create-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
            </div>
            <span>Opret nyt arrangement</span>
        </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<style>
    .welcome-banner {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border-radius: 16px;
        padding: 32px;
        margin-bottom: 32px;
        color: white;
    }

    .welcome-content h2 {
        font-size: 24px;
        font-weight: 700;
        margin-bottom: 8px;
    }

    .welcome-content p {
        opacity: 0.9;
        margin-bottom: 24px;
        font-size: 15px;
    }

    .welcome-banner .btn {
        background: white;
        color: var(--primary);
    }

    .welcome-banner .btn:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }

    .events-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        gap: 24px;
    }

    .event-card {
        background: white;
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        border: 1px solid var(--gray-200);
        transition: all 0.2s;
    }

    .event-card:hover {
        box-shadow: 0 8px 24px rgba(0,0,0,0.08);
        transform: translateY(-2px);
    }

    .event-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }

    .event-type-badge {
        font-size: 12px;
        font-weight: 600;
        color: var(--primary);
        background: rgba(102, 126, 234, 0.1);
        padding: 4px 10px;
        border-radius: 6px;
    }

    .event-status {
        font-size: 12px;
        font-weight: 500;
        padding: 4px 10px;
        border-radius: 6px;
    }

    .status-active {
        background: #dcfce7;
        color: #15803d;
    }

    .status-draft {
        background: var(--gray-100);
        color: var(--gray-600);
    }

    .status-completed {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .status-archived {
        background: var(--gray-100);
        color: var(--gray-500);
    }

    .event-card-title {
        font-size: 20px;
        font-weight: 700;
        color: var(--gray-900);
        margin-bottom: 8px;
    }

    .event-card-date {
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--gray-500);
        font-size: 14px;
        margin-bottom: 20px;
    }

    .event-card-date svg {
        width: 16px;
        height: 16px;
    }

    .days-until {
        color: var(--primary);
        font-weight: 500;
    }

    .event-card-stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        margin-bottom: 16px;
    }

    .stat {
        text-align: center;
    }

    .stat-value {
        display: block;
        font-size: 20px;
        font-weight: 700;
        color: var(--gray-900);
    }

    .stat-value.stat-success {
        color: var(--success);
    }

    .stat-value.stat-danger {
        color: var(--danger);
    }

    .stat-value.stat-warning {
        color: var(--warning);
    }

    .stat-label {
        font-size: 12px;
        color: var(--gray-500);
    }

    .rsvp-progress {
        margin-bottom: 20px;
    }

    .rsvp-bar {
        height: 6px;
        background: var(--gray-100);
        border-radius: 3px;
        overflow: hidden;
        display: flex;
    }

    .rsvp-bar-accepted {
        background: var(--success);
    }

    .rsvp-bar-declined {
        background: var(--danger);
    }

    .event-card-actions {
        display: flex;
        gap: 12px;
    }

    .btn-sm {
        padding: 8px 16px;
        font-size: 13px;
    }

    .event-card-create {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 280px;
        border: 2px dashed var(--gray-300);
        background: transparent;
        color: var(--gray-500);
        text-decoration: none;
        transition: all 0.2s;
    }

    .event-card-create:hover {
        border-color: var(--primary);
        color: var(--primary);
        background: rgba(102, 126, 234, 0.02);
    }

    .create-icon {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: var(--gray-100);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 12px;
        transition: all 0.2s;
    }

    .event-card-create:hover .create-icon {
        background: rgba(102, 126, 234, 0.1);
    }

    .create-icon svg {
        width: 24px;
        height: 24px;
    }

    @media (max-width: 640px) {
        .events-grid {
            grid-template-columns: 1fr;
        }

        .event-card-stats {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<?php require_once __DIR__ . '/../includes/app-footer.php'; ?>

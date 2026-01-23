<?php
/**
 * Event Dashboard - Overview page
 * Included by manage.php
 */

// Calculate days until event
$eventDate = $event['event_date'] ?? null;
$daysUntil = $eventDate ? (int)ceil((strtotime($eventDate) - time()) / 86400) : null;
?>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Inviterede gæster</div>
        <div class="stat-value"><?= (int)$guestStats['total_guests'] ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Kommer</div>
        <div class="stat-value success"><?= (int)$guestStats['accepted'] ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Afbud</div>
        <div class="stat-value danger"><?= (int)$guestStats['declined'] ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Afventer svar</div>
        <div class="stat-value warning"><?= (int)$guestStats['pending'] ?></div>
    </div>
    <?php if ($daysUntil !== null): ?>
    <div class="stat-card">
        <div class="stat-label">Dage til arrangement</div>
        <div class="stat-value <?= $daysUntil <= 7 ? 'warning' : '' ?>"><?= $daysUntil ?></div>
    </div>
    <?php endif; ?>
    <div class="stat-card">
        <div class="stat-label">Samlet antal (voksne + børn)</div>
        <div class="stat-value"><?= (int)$guestStats['total_adults'] + (int)$guestStats['total_children'] ?></div>
    </div>
</div>

<!-- Event Details Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Arrangementdetaljer</h2>
        <a href="?id=<?= $eventId ?>&page=settings" class="btn btn-secondary">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
            </svg>
            Rediger
        </a>
    </div>
    <div class="details-grid">
        <div class="detail-item">
            <span class="detail-label">Hovedperson</span>
            <span class="detail-value"><?= htmlspecialchars($event['main_person_name'] ?? '-') ?>
                <?php if ($event['secondary_person_name']): ?>
                    & <?= htmlspecialchars($event['secondary_person_name']) ?>
                <?php endif; ?>
            </span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Dato</span>
            <span class="detail-value"><?= $eventDate ? htmlspecialchars(formatDate($eventDate, true)) : 'Ikke angivet' ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Tid</span>
            <span class="detail-value"><?= $event['event_time'] ? htmlspecialchars(substr($event['event_time'], 0, 5)) : 'Ikke angivet' ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Sted</span>
            <span class="detail-value"><?= htmlspecialchars($event['location'] ?? 'Ikke angivet') ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Gæstelink</span>
            <span class="detail-value">
                <?php if ($event['slug']): ?>
                    <code style="background: var(--gray-100); padding: 4px 8px; border-radius: 4px; font-size: 13px;">
                        /e/<?= htmlspecialchars($event['slug']) ?>/
                    </code>
                <?php else: ?>
                    Ikke tilgængelig
                <?php endif; ?>
            </span>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Hurtige handlinger</h2>
    </div>
    <div class="quick-actions">
        <a href="?id=<?= $eventId ?>&page=guests" class="quick-action">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
            </svg>
            <span>Tilføj gæster</span>
        </a>
        <a href="?id=<?= $eventId ?>&page=wishlist" class="quick-action">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"></path>
            </svg>
            <span>Administrer ønskeliste</span>
        </a>
        <a href="?id=<?= $eventId ?>&page=menu" class="quick-action">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
            </svg>
            <span>Opret menu</span>
        </a>
        <a href="?id=<?= $eventId ?>&page=schedule" class="quick-action">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span>Lav program</span>
        </a>
        <?php if ($event['slug']): ?>
        <a href="/e/<?= htmlspecialchars($event['slug']) ?>/" class="quick-action" target="_blank">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
            </svg>
            <span>Se gæsteside</span>
        </a>
        <?php endif; ?>
    </div>
</div>

<?php
// Get recent RSVPs
$stmt = $db->prepare("
    SELECT g.*, DATE_FORMAT(g.rsvp_responded_at, '%d/%m kl. %H:%i') as responded_time
    FROM guests g
    WHERE g.event_id = ? AND g.rsvp_status != 'pending'
    ORDER BY g.rsvp_responded_at DESC
    LIMIT 5
");
$stmt->execute([$eventId]);
$recentRsvps = $stmt->fetchAll();

if (!empty($recentRsvps)):
?>
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Seneste svar</h2>
        <a href="?id=<?= $eventId ?>&page=guests" class="btn btn-secondary">Se alle</a>
    </div>
    <div class="rsvp-list">
        <?php foreach ($recentRsvps as $rsvp): ?>
        <div class="rsvp-item">
            <div class="rsvp-info">
                <span class="rsvp-name"><?= htmlspecialchars($rsvp['name']) ?></span>
                <span class="rsvp-time"><?= htmlspecialchars($rsvp['responded_time']) ?></span>
            </div>
            <span class="rsvp-status status-<?= $rsvp['rsvp_status'] ?>">
                <?= $rsvp['rsvp_status'] === 'accepted' ? 'Kommer' : 'Kommer ikke' ?>
            </span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<style>
    .details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 20px;
    }

    .detail-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .detail-label {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--gray-500);
        font-weight: 500;
    }

    .detail-value {
        font-size: 15px;
        color: var(--gray-900);
    }

    .rsvp-list {
        display: flex;
        flex-direction: column;
    }

    .rsvp-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid var(--gray-100);
    }

    .rsvp-item:last-child {
        border-bottom: none;
    }

    .rsvp-info {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .rsvp-name {
        font-weight: 500;
        color: var(--gray-900);
    }

    .rsvp-time {
        font-size: 13px;
        color: var(--gray-500);
    }

    .rsvp-status {
        font-size: 13px;
        font-weight: 500;
        padding: 4px 10px;
        border-radius: 6px;
    }

    .status-accepted {
        background: #dcfce7;
        color: #15803d;
    }

    .status-declined {
        background: #fef2f2;
        color: #dc2626;
    }
</style>

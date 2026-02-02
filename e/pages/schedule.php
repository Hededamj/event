<?php
/**
 * Guest Schedule Page - Nordic Design
 */

$stmt = $db->prepare("SELECT * FROM schedule_items WHERE event_id = ? ORDER BY time ASC, id ASC");
$stmt->execute([$eventId]);
$items = $stmt->fetchAll();
?>

<!-- Page Header -->
<div class="page-header" style="text-align: center;">
    <h1 class="page-header-title">Program</h1>
    <p class="page-header-subtitle">
        <?= $event['event_date'] ? htmlspecialchars(formatDate($event['event_date'], true)) : 'Dagens program' ?>
    </p>
</div>

<?php if (empty($items)): ?>
<div class="card">
    <div class="empty-state">
        <div class="empty-state-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        </div>
        <h3>Kommer snart</h3>
        <p>Programmet er ikke offentliggjort endnu</p>
    </div>
</div>
<?php else: ?>
<div class="timeline">
    <?php foreach ($items as $index => $item): ?>
    <div class="timeline-item">
        <div class="timeline-time">
            <?= $item['time'] ? htmlspecialchars(substr($item['time'], 0, 5)) : '--:--' ?>
        </div>
        <div class="timeline-dot"></div>
        <div class="timeline-content">
            <h3><?= htmlspecialchars($item['title']) ?></h3>
            <?php if ($item['description']): ?>
                <p><?= htmlspecialchars($item['description']) ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<style>
    .timeline {
        position: relative;
        padding-left: 100px;
    }

    .timeline::before {
        content: '';
        position: absolute;
        left: 86px;
        top: 8px;
        bottom: 8px;
        width: 2px;
        background: var(--cream-dark);
    }

    .timeline-item {
        position: relative;
        padding-bottom: 32px;
        animation: fadeIn 0.6s var(--ease-out) both;
    }

    .timeline-item:nth-child(1) { animation-delay: 0.05s; }
    .timeline-item:nth-child(2) { animation-delay: 0.1s; }
    .timeline-item:nth-child(3) { animation-delay: 0.15s; }
    .timeline-item:nth-child(4) { animation-delay: 0.2s; }
    .timeline-item:nth-child(5) { animation-delay: 0.25s; }

    .timeline-item:last-child {
        padding-bottom: 0;
    }

    .timeline-time {
        position: absolute;
        left: -100px;
        width: 80px;
        text-align: right;
        font-family: var(--font-display);
        font-size: 18px;
        font-weight: 500;
        color: var(--sage-dark);
        padding-top: 2px;
    }

    .timeline-dot {
        position: absolute;
        left: -14px;
        top: 6px;
        width: 12px;
        height: 12px;
        background: var(--sage);
        border-radius: 50%;
        border: 3px solid var(--cream);
        box-shadow: 0 0 0 2px var(--sage-light);
    }

    .timeline-content {
        background: var(--white);
        border-radius: 16px;
        padding: 20px 24px;
        box-shadow:
            0 1px 2px rgba(0,0,0,0.03),
            0 4px 12px rgba(0,0,0,0.03);
    }

    .timeline-content h3 {
        font-family: var(--font-display);
        font-size: 18px;
        font-weight: 500;
        color: var(--charcoal);
        margin-bottom: 4px;
    }

    .timeline-content p {
        font-size: 14px;
        color: var(--charcoal-light);
        line-height: 1.5;
    }

    @media (max-width: 480px) {
        .timeline {
            padding-left: 24px;
        }

        .timeline::before {
            left: 5px;
        }

        .timeline-time {
            position: relative;
            left: 0;
            width: auto;
            text-align: left;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .timeline-dot {
            left: -24px;
        }
    }
</style>

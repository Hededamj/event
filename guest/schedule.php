<?php
/**
 * Guest - Schedule/Program View
 */
require_once __DIR__ . '/../includes/guest-header.php';

// Get schedule items
$stmt = $db->prepare("
    SELECT * FROM schedule_items
    WHERE event_id = ?
    ORDER BY time ASC, sort_order ASC
");
$stmt->execute([$eventId]);
$scheduleItems = $stmt->fetchAll();
?>

<h1 class="h2 mb-sm text-center">Program</h1>
<p class="text-center text-muted mb-lg">
    Tidsplan for <?= formatDate($event['event_date'], true) ?>
</p>

<?php if (empty($scheduleItems)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state__icon">🕐</div>
            <h3 class="empty-state__title">Programmet kommer snart</h3>
            <p class="empty-state__text">Programmet er ikke offentliggjort endnu</p>
        </div>
    </div>

<?php else: ?>

    <div class="card">
        <div class="timeline">
            <?php foreach ($scheduleItems as $index => $item): ?>
                <div class="timeline__item" style="display: flex; gap: var(--space-md); <?= $index > 0 ? 'margin-top: var(--space-md); padding-top: var(--space-md); border-top: 1px solid var(--color-border-soft);' : '' ?>">
                    <!-- Time -->
                    <div style="min-width: 60px; text-align: right;">
                        <span style="font-family: 'Cormorant Garamond', serif; font-size: var(--text-xl); font-weight: 600; color: var(--color-primary-deep);">
                            <?= date('H:i', strtotime($item['time'])) ?>
                        </span>
                    </div>

                    <!-- Content -->
                    <div style="flex: 1; position: relative; padding-left: var(--space-md);">
                        <!-- Timeline dot -->
                        <div style="position: absolute; left: 0; top: 8px; width: 12px; height: 12px; background: var(--color-primary); border-radius: 50%; border: 2px solid var(--color-surface);"></div>

                        <!-- Timeline line -->
                        <?php if ($index < count($scheduleItems) - 1): ?>
                            <div style="position: absolute; left: 5px; top: 24px; bottom: -var(--space-md); width: 2px; background: var(--color-border-soft);"></div>
                        <?php endif; ?>

                        <h3 style="font-size: var(--text-base); font-weight: 600; margin-bottom: var(--space-3xs);">
                            <?= escape($item['title']) ?>
                        </h3>
                        <?php if ($item['description']): ?>
                            <p class="small text-muted" style="line-height: 1.6;">
                                <?= escape($item['description']) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($event['location']): ?>
        <div class="card mt-md" style="background: var(--color-bg-subtle);">
            <p class="flex items-center gap-sm">
                <span>📍</span>
                <span><strong>Adresse:</strong> <?= escape($event['location']) ?></span>
            </p>
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/guest-footer.php'; ?>
</body>
</html>

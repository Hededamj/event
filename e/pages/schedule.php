<?php
/**
 * Guest Schedule Page
 */

$stmt = $db->prepare("SELECT * FROM schedule_items WHERE event_id = ? ORDER BY time ASC, id ASC");
$stmt->execute([$eventId]);
$items = $stmt->fetchAll();
?>

<h1 class="serif" style="font-size: 24px; text-align: center; margin-bottom: 8px;">Program</h1>
<p style="text-align: center; color: var(--gray-600); margin-bottom: 24px;">
    <?= $event['event_date'] ? htmlspecialchars(formatDate($event['event_date'], true)) : 'Dagens program' ?>
</p>

<?php if (empty($items)): ?>
<div class="card">
    <div class="empty-state">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <p>Programmet er ikke offentliggjort endnu</p>
    </div>
</div>
<?php else: ?>
<div class="card" style="padding: 0;">
    <div style="padding: 16px;">
        <?php foreach ($items as $index => $item): ?>
        <div style="display: flex; gap: 16px; <?= $index < count($items) - 1 ? 'padding-bottom: 20px; margin-bottom: 20px; border-bottom: 1px solid var(--gray-100);' : '' ?>">
            <div style="min-width: 60px; text-align: right;">
                <span style="font-size: 18px; font-weight: 600; color: var(--primary);">
                    <?= $item['time'] ? htmlspecialchars(substr($item['time'], 0, 5)) : '--:--' ?>
                </span>
            </div>
            <div style="flex: 1; padding-top: 2px;">
                <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 4px;"><?= htmlspecialchars($item['title']) ?></h3>
                <?php if ($item['description']): ?>
                    <p style="font-size: 14px; color: var(--gray-600);"><?= htmlspecialchars($item['description']) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

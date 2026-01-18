<?php
/**
 * Guest - Wishlist View
 */
require_once __DIR__ . '/../includes/guest-header.php';

// Handle reservation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $itemId = (int)($_POST['item_id'] ?? 0);

    if ($action === 'reserve' && $itemId) {
        // Check if not already reserved
        $stmt = $db->prepare("
            SELECT id FROM wishlist_items
            WHERE id = ? AND event_id = ? AND reserved_by_guest_id IS NULL
        ");
        $stmt->execute([$itemId, $eventId]);

        if ($stmt->fetch()) {
            $stmt = $db->prepare("
                UPDATE wishlist_items
                SET reserved_by_guest_id = ?, reserved_at = NOW()
                WHERE id = ? AND event_id = ?
            ");
            $stmt->execute([$guestId, $itemId, $eventId]);
            setFlash('success', 'Du har reserveret gaven!');
        }
        redirect('/guest/wishlist.php');

    } elseif ($action === 'unreserve' && $itemId) {
        // Only unreserve if this guest reserved it
        $stmt = $db->prepare("
            UPDATE wishlist_items
            SET reserved_by_guest_id = NULL, reserved_at = NULL
            WHERE id = ? AND event_id = ? AND reserved_by_guest_id = ?
        ");
        $stmt->execute([$itemId, $eventId, $guestId]);
        setFlash('success', 'Reservationen er fjernet');
        redirect('/guest/wishlist.php');
    }
}

// Get wishlist items
$stmt = $db->prepare("
    SELECT w.*, g.name as reserved_by_name
    FROM wishlist_items w
    LEFT JOIN guests g ON w.reserved_by_guest_id = g.id
    WHERE w.event_id = ?
    ORDER BY w.priority DESC, w.created_at ASC
");
$stmt->execute([$eventId]);
$items = $stmt->fetchAll();

// Separate available and reserved
$availableItems = array_filter($items, fn($i) => $i['reserved_by_guest_id'] === null);
$reservedItems = array_filter($items, fn($i) => $i['reserved_by_guest_id'] !== null);
$myReservations = array_filter($items, fn($i) => $i['reserved_by_guest_id'] == $guestId);
?>

<h1 class="h2 mb-sm text-center"><?= escape($event['confirmand_name']) ?>s ønskeliste</h1>
<p class="text-center text-muted mb-md">
    Reservér et ønske for at undgå at flere køber det samme
</p>

<?php if (!empty($myReservations)): ?>
    <div class="card mb-md" style="background: var(--color-bg-subtle); border-color: var(--color-primary-soft);">
        <h2 class="card__title mb-sm">Dine reservationer</h2>
        <?php foreach ($myReservations as $item): ?>
            <div class="flex flex-between items-center" style="padding: var(--space-xs) 0; border-bottom: 1px solid var(--color-border-soft);">
                <div>
                    <strong><?= escape($item['title']) ?></strong>
                    <?php if ($item['price']): ?>
                        <span class="text-muted small"> - <?= formatCurrency($item['price']) ?></span>
                    <?php endif; ?>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="unreserve">
                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                    <button type="submit" class="btn btn--ghost small" onclick="return confirm('Fjern reservation?')">
                        Fjern
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (empty($items)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state__icon">🎁</div>
            <h3 class="empty-state__title">Ingen ønsker endnu</h3>
            <p class="empty-state__text">Ønskelisten er ikke oprettet endnu</p>
        </div>
    </div>

<?php else: ?>

    <?php if (!empty($availableItems)): ?>
        <h2 class="h4 mb-sm">Ledige ønsker (<?= count($availableItems) ?>)</h2>
        <div style="display: grid; gap: var(--space-sm); margin-bottom: var(--space-lg);">
            <?php foreach ($availableItems as $item): ?>
                <div class="card" style="padding: var(--space-sm);">
                    <div class="flex gap-sm">
                        <?php if ($item['image_url']): ?>
                            <img src="<?= escape($item['image_url']) ?>"
                                 alt="<?= escape($item['title']) ?>"
                                 style="width: 80px; height: 80px; object-fit: cover; border-radius: var(--radius-md);">
                        <?php else: ?>
                            <div style="width: 80px; height: 80px; background: var(--color-bg-subtle); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 2rem;">
                                🎁
                            </div>
                        <?php endif; ?>

                        <div style="flex: 1; min-width: 0;">
                            <h3 style="font-size: var(--text-base); margin-bottom: var(--space-3xs);">
                                <?= escape($item['title']) ?>
                            </h3>

                            <?php if ($item['description']): ?>
                                <p class="small text-muted mb-xs"><?= escape($item['description']) ?></p>
                            <?php endif; ?>

                            <div class="flex flex-between items-center">
                                <?php if ($item['price']): ?>
                                    <span class="text-primary" style="font-weight: 600;">
                                        <?= formatCurrency($item['price']) ?>
                                    </span>
                                <?php else: ?>
                                    <span></span>
                                <?php endif; ?>

                                <div class="flex gap-xs">
                                    <?php if ($item['link']): ?>
                                        <a href="<?= escape($item['link']) ?>"
                                           target="_blank"
                                           rel="noopener"
                                           class="btn btn--ghost small">
                                            Se produkt
                                        </a>
                                    <?php endif; ?>

                                    <form method="POST">
                                        <input type="hidden" name="action" value="reserve">
                                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="btn btn--primary small">
                                            Reservér
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($reservedItems)): ?>
        <h2 class="h4 mb-sm text-muted">Allerede reserveret (<?= count($reservedItems) ?>)</h2>
        <div style="display: grid; gap: var(--space-xs);">
            <?php foreach ($reservedItems as $item): ?>
                <div class="card card--flat" style="padding: var(--space-sm); opacity: 0.6;">
                    <div class="flex gap-sm items-center">
                        <div style="width: 48px; height: 48px; background: var(--color-border-soft); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center;">
                            ✓
                        </div>
                        <div style="flex: 1;">
                            <span style="text-decoration: line-through;"><?= escape($item['title']) ?></span>
                            <?php if ($item['price']): ?>
                                <span class="text-muted small"> - <?= formatCurrency($item['price']) ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="badge badge--neutral">Reserveret</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/guest-footer.php'; ?>
</body>
</html>

<?php
/**
 * Guest Wishlist Page
 */

// Handle reservation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reserve_item'])) {
    $itemId = (int)$_POST['reserve_item'];

    // Check if item is available
    $stmt = $db->prepare("SELECT * FROM wishlist_items WHERE id = ? AND event_id = ? AND reserved_by_guest_id IS NULL");
    $stmt->execute([$itemId, $eventId]);
    $item = $stmt->fetch();

    if ($item) {
        $stmt = $db->prepare("UPDATE wishlist_items SET reserved_by_guest_id = ? WHERE id = ?");
        $stmt->execute([$currentGuest['id'], $itemId]);
        setFlash('success', 'Du har reserveret: ' . $item['title']);
    }
    redirect("/e/$slug/wishlist");
}

// Handle unreservation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unreserve_item'])) {
    $itemId = (int)$_POST['unreserve_item'];

    $stmt = $db->prepare("UPDATE wishlist_items SET reserved_by_guest_id = NULL WHERE id = ? AND event_id = ? AND reserved_by_guest_id = ?");
    $stmt->execute([$itemId, $eventId, $currentGuest['id']]);
    setFlash('success', 'Reservation annulleret.');
    redirect("/e/$slug/wishlist");
}

// Get wishlist items
$stmt = $db->prepare("SELECT * FROM wishlist_items WHERE event_id = ? ORDER BY priority DESC, title ASC");
$stmt->execute([$eventId]);
$items = $stmt->fetchAll();
?>

<h1 class="serif" style="font-size: 24px; text-align: center; margin-bottom: 8px;">Ønskeliste</h1>
<p style="text-align: center; color: var(--gray-600); margin-bottom: 24px;">
    Reservér en gave så andre ved hvad du giver
</p>

<?php if (empty($items)): ?>
<div class="card">
    <div class="empty-state">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"></path></svg>
        <p>Ingen ønsker tilføjet endnu</p>
    </div>
</div>
<?php else: ?>
<div style="display: flex; flex-direction: column; gap: 12px;">
    <?php foreach ($items as $item):
        $isReserved = $item['reserved_by_guest_id'] !== null;
        $isMyReservation = $item['reserved_by_guest_id'] == $currentGuest['id'];
    ?>
    <div class="card" style="<?= $isReserved && !$isMyReservation ? 'opacity: 0.6;' : '' ?>">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px;">
            <div style="flex: 1;">
                <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 4px;"><?= htmlspecialchars($item['title']) ?></h3>
                <?php if ($item['description']): ?>
                    <p style="font-size: 14px; color: var(--gray-600); margin-bottom: 8px;"><?= htmlspecialchars($item['description']) ?></p>
                <?php endif; ?>
                <?php if ($item['price']): ?>
                    <p style="font-size: 15px; font-weight: 600; color: var(--primary);"><?= formatCurrency($item['price']) ?></p>
                <?php endif; ?>
            </div>
            <div>
                <?php if ($isMyReservation): ?>
                    <form method="POST">
                        <button type="submit" name="unreserve_item" value="<?= $item['id'] ?>" class="btn btn-secondary" style="padding: 8px 12px; font-size: 13px;">
                            Annuller
                        </button>
                    </form>
                <?php elseif ($isReserved): ?>
                    <span style="font-size: 12px; background: var(--gray-100); padding: 6px 10px; border-radius: 6px; color: var(--gray-600);">Reserveret</span>
                <?php else: ?>
                    <form method="POST">
                        <button type="submit" name="reserve_item" value="<?= $item['id'] ?>" class="btn btn-primary" style="padding: 8px 12px; font-size: 13px;">
                            Reservér
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($item['url'] && !$isReserved): ?>
        <a href="<?= htmlspecialchars($item['url']) ?>" target="_blank" style="display: inline-block; margin-top: 12px; color: var(--primary); font-size: 13px; text-decoration: none;">
            Se produkt &rarr;
        </a>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

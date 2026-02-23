<?php
/**
 * Guest Wishlist Page - Nordic Design
 */

// Handle reservation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reserve_item'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Ugyldig anmodning. Prøv igen.');
        redirect("/e/$slug/wishlist");
    }
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
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Ugyldig anmodning. Prøv igen.');
        redirect("/e/$slug/wishlist");
    }
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

<!-- Page Header -->
<div class="page-header" style="text-align: center;">
    <h1 class="page-header-title">Ønskeliste</h1>
    <p class="page-header-subtitle">Reservér en gave så andre ved hvad du giver</p>
</div>

<?php if (empty($items)): ?>
<div class="card">
    <div class="empty-state">
        <div class="empty-state-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"></path></svg>
        </div>
        <h3>Ingen ønsker endnu</h3>
        <p>Ønskelisten er ikke oprettet endnu</p>
    </div>
</div>
<?php else: ?>
<div style="display: flex; flex-direction: column; gap: 16px;">
    <?php foreach ($items as $item):
        $isReserved = $item['reserved_by_guest_id'] !== null;
        $isMyReservation = $item['reserved_by_guest_id'] == $currentGuest['id'];
    ?>
    <div class="card" style="<?= $isReserved && !$isMyReservation ? 'opacity: 0.5;' : '' ?>">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px;">
            <div style="flex: 1;">
                <h3 style="font-family: var(--font-display); font-size: 18px; font-weight: 500; margin-bottom: 6px; color: var(--charcoal);">
                    <?= htmlspecialchars($item['title']) ?>
                </h3>
                <?php if ($item['description']): ?>
                    <p style="font-size: 14px; color: var(--charcoal-light); margin-bottom: 10px; line-height: 1.5;">
                        <?= htmlspecialchars($item['description']) ?>
                    </p>
                <?php endif; ?>
                <?php if ($item['price']): ?>
                    <p style="font-size: 16px; font-weight: 600; color: var(--sage-dark);">
                        <?= formatCurrency($item['price']) ?>
                    </p>
                <?php endif; ?>
            </div>
            <div>
                <?php if ($isMyReservation): ?>
                    <form method="POST">
                        <?= csrfField() ?>
                        <button type="submit" name="unreserve_item" value="<?= $item['id'] ?>" class="btn btn-secondary" style="padding: 10px 16px; font-size: 13px;">
                            Annuller
                        </button>
                    </form>
                <?php elseif ($isReserved): ?>
                    <span class="status-badge" style="background: var(--cream); color: var(--charcoal-light);">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Reserveret
                    </span>
                <?php else: ?>
                    <form method="POST">
                        <?= csrfField() ?>
                        <button type="submit" name="reserve_item" value="<?= $item['id'] ?>" class="btn btn-sage" style="padding: 10px 16px; font-size: 13px;">
                            Reservér
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($item['url'] && !$isReserved): ?>
        <a href="<?= htmlspecialchars($item['url']) ?>" target="_blank" rel="noopener" style="display: inline-flex; align-items: center; gap: 6px; margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--cream-dark); color: var(--sage-dark); font-size: 13px; font-weight: 500; text-decoration: none;">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
            Se produkt
        </a>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

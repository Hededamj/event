<?php
/**
 * Guest - Home Page
 */
require_once __DIR__ . '/../includes/guest-header.php';

// Get wishlist stats (for display)
$stmt = $db->prepare("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN reserved_by_guest_id IS NOT NULL THEN 1 ELSE 0 END) as reserved
    FROM wishlist_items
    WHERE event_id = ?
");
$stmt->execute([$eventId]);
$wishlistStats = $stmt->fetch();
?>

<!-- Hero Section -->
<div class="guest-hero">
    <div class="guest-hero__icon">✨</div>
    <h1 class="guest-hero__title"><?= escape($event['confirmand_name']) ?></h1>
    <p class="guest-hero__subtitle"><?= escape($event['name']) ?></p>
    <div class="guest-hero__date">
        <span>✦</span>
        <span><?= formatDate($event['event_date'], true) ?></span>
        <?php if ($event['event_time']): ?>
            <span>kl. <?= date('H:i', strtotime($event['event_time'])) ?></span>
        <?php endif; ?>
    </div>
    <?php if ($event['location']): ?>
        <p class="mt-sm text-muted small">
            📍 <?= escape($event['location']) ?>
        </p>
    <?php endif; ?>
</div>

<?php if ($event['welcome_text']): ?>
    <div class="card mb-md">
        <p style="line-height: 1.8; text-align: center;">
            <?= nl2br(escape($event['welcome_text'])) ?>
        </p>
    </div>
<?php endif; ?>

<!-- RSVP Card -->
<div class="card mb-md">
    <h2 class="card__title mb-sm">Din tilmelding</h2>

    <?php if ($guest['rsvp_status'] === 'pending'): ?>
        <p class="mb-md text-soft">
            Vi har endnu ikke modtaget dit svar. Kan du komme til festen?
        </p>
        <div class="flex gap-sm">
            <a href="<?= BASE_PATH ?>/guest/rsvp.php" class="btn btn--primary" style="flex: 1;">
                Ja, jeg kommer! 🎉
            </a>
            <a href="<?= BASE_PATH ?>/guest/rsvp.php?decline=1" class="btn btn--secondary" style="flex: 1;">
                Jeg kan desværre ikke
            </a>
        </div>

    <?php elseif ($guest['rsvp_status'] === 'yes'): ?>
        <div class="alert alert--success mb-sm">
            <span>✓</span>
            <span>Du har bekræftet din deltagelse!</span>
        </div>
        <div class="mb-sm">
            <p class="small">
                <strong>Antal:</strong> <?= $guest['adults_count'] ?> person<?= $guest['adults_count'] > 1 ? 'er' : '' ?>
            </p>
            <?php if ($guest['dietary_notes']): ?>
                <p class="small">
                    <strong>Kostbehov:</strong> <?= escape($guest['dietary_notes']) ?>
                </p>
            <?php endif; ?>
        </div>
        <div class="flex gap-sm">
            <a href="<?= BASE_PATH ?>/guest/rsvp.php" class="btn btn--secondary" style="flex: 1;">
                Ret tilmelding
            </a>
            <a href="<?= BASE_PATH ?>/guest/rsvp.php?decline=1" class="btn btn--ghost" style="flex: 1;">
                Meld afbud
            </a>
        </div>

    <?php else: ?>
        <div class="alert alert--warning mb-sm">
            <span>ℹ</span>
            <span>Du har meldt afbud til festen</span>
        </div>
        <p class="small text-muted mb-sm">
            Har du skiftet mening? Du er stadig velkommen!
        </p>
        <a href="<?= BASE_PATH ?>/guest/rsvp.php" class="btn btn--primary btn--block">
            Tilmeld dig alligevel
        </a>
    <?php endif; ?>
</div>

<!-- Quick Links -->
<?php
$hasAnyLinks = ($event['show_wishlist'] ?? true) || ($event['show_menu'] ?? true) || ($event['show_schedule'] ?? true) || ($event['show_photos'] ?? true);
?>
<?php if ($hasAnyLinks): ?>
<div class="card">
    <h2 class="card__title mb-sm">Mere information</h2>

    <div style="display: grid; gap: var(--space-xs);">
        <?php if ($event['show_wishlist'] ?? true): ?>
        <a href="<?= BASE_PATH ?>/guest/wishlist.php" class="card card--flat" style="display: flex; align-items: center; gap: var(--space-sm); padding: var(--space-sm);">
            <span style="font-size: 1.5rem;">🎁</span>
            <div style="flex: 1;">
                <strong>Ønskeliste</strong>
                <p class="small text-muted" style="margin: 0;">
                    <?= $wishlistStats['total'] - $wishlistStats['reserved'] ?> af <?= $wishlistStats['total'] ?> ønsker ledige
                </p>
            </div>
            <span class="text-muted">→</span>
        </a>
        <?php endif; ?>

        <?php if ($event['show_menu'] ?? true): ?>
        <a href="<?= BASE_PATH ?>/guest/menu.php" class="card card--flat" style="display: flex; align-items: center; gap: var(--space-sm); padding: var(--space-sm);">
            <span style="font-size: 1.5rem;">🍽️</span>
            <div style="flex: 1;">
                <strong>Menu</strong>
                <p class="small text-muted" style="margin: 0;">Se hvad der serveres</p>
            </div>
            <span class="text-muted">→</span>
        </a>
        <?php endif; ?>

        <?php if ($event['show_schedule'] ?? true): ?>
        <a href="<?= BASE_PATH ?>/guest/schedule.php" class="card card--flat" style="display: flex; align-items: center; gap: var(--space-sm); padding: var(--space-sm);">
            <span style="font-size: 1.5rem;">🕐</span>
            <div style="flex: 1;">
                <strong>Program</strong>
                <p class="small text-muted" style="margin: 0;">Tidsplan for dagen</p>
            </div>
            <span class="text-muted">→</span>
        </a>
        <?php endif; ?>

        <?php if ($event['show_photos'] ?? true): ?>
        <a href="<?= BASE_PATH ?>/guest/photos.php" class="card card--flat" style="display: flex; align-items: center; gap: var(--space-sm); padding: var(--space-sm);">
            <span style="font-size: 1.5rem;">📷</span>
            <div style="flex: 1;">
                <strong>Billeder</strong>
                <p class="small text-muted" style="margin: 0;">Del dine billeder fra festen</p>
            </div>
            <span class="text-muted">→</span>
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/guest-footer.php'; ?>
</body>
</html>

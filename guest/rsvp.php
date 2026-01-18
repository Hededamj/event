<?php
/**
 * Guest - RSVP Form
 */
require_once __DIR__ . '/../includes/guest-header.php';

$decline = isset($_GET['decline']);
$success = false;
$successType = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rsvpStatus = $_POST['rsvp_status'] ?? 'yes';
    $adultsCount = max(1, (int)($_POST['adults_count'] ?? 1));
    $childrenCount = max(0, (int)($_POST['children_count'] ?? 0));
    $dietaryNotes = trim($_POST['dietary_notes'] ?? '');

    $stmt = $db->prepare("
        UPDATE guests
        SET rsvp_status = ?,
            adults_count = ?,
            children_count = ?,
            dietary_notes = ?,
            rsvp_date = NOW()
        WHERE id = ? AND event_id = ?
    ");

    $stmt->execute([
        $rsvpStatus,
        $rsvpStatus === 'yes' ? $adultsCount : 0,
        $rsvpStatus === 'yes' ? $childrenCount : 0,
        $dietaryNotes ?: null,
        $guestId,
        $eventId
    ]);

    $success = true;
    $successType = $rsvpStatus;

    // Refresh guest data
    $stmt = $db->prepare("SELECT * FROM guests WHERE id = ?");
    $stmt->execute([$guestId]);
    $guest = $stmt->fetch();
}
?>

<?php if ($success): ?>
    <!-- Success Message -->
    <div class="guest-hero">
        <?php if ($successType === 'yes'): ?>
            <div class="guest-hero__icon">🎉</div>
            <h1 class="guest-hero__title">Tak for din tilmelding!</h1>
            <p class="lead mt-sm">
                Vi glæder os til at se dig til <?= escape($event['confirmand_name']) ?>s <?= escape($event['name']) ?>.
            </p>
        <?php else: ?>
            <div class="guest-hero__icon">💌</div>
            <h1 class="guest-hero__title">Tak for din besked</h1>
            <p class="lead mt-sm">
                Vi er kede af at du ikke kan komme, men tak fordi du gav os besked.
            </p>
        <?php endif; ?>
    </div>

    <div class="card mb-md text-center">
        <?php if ($successType === 'yes'): ?>
            <h3 class="mb-sm">Din tilmelding</h3>
            <p class="mb-xs">
                <strong><?= $guest['adults_count'] ?></strong> voksen<?= $guest['adults_count'] > 1 ? 'e' : '' ?>
                <?php if ($guest['children_count'] > 0): ?>
                    og <strong><?= $guest['children_count'] ?></strong> barn/børn
                <?php endif; ?>
            </p>
            <?php if ($guest['dietary_notes']): ?>
                <p class="small text-muted">
                    Kostbehov: <?= escape($guest['dietary_notes']) ?>
                </p>
            <?php endif; ?>
        <?php endif; ?>

        <a href="/guest/index.php" class="btn btn--primary btn--block mt-md">
            Tilbage til forsiden
        </a>
    </div>

    <div class="card">
        <h3 class="card__title mb-sm">Se mere</h3>
        <div style="display: grid; gap: var(--space-xs);">
            <a href="/guest/wishlist.php" class="btn btn--secondary btn--block" style="justify-content: flex-start;">
                🎁 Se ønskeliste
            </a>
            <a href="/guest/menu.php" class="btn btn--secondary btn--block" style="justify-content: flex-start;">
                🍽️ Se menu
            </a>
            <a href="/guest/schedule.php" class="btn btn--secondary btn--block" style="justify-content: flex-start;">
                🕐 Se program
            </a>
        </div>
    </div>

<?php else: ?>
    <!-- RSVP Form -->
    <div class="guest-hero" style="padding: var(--space-md);">
        <h1 class="guest-hero__title" style="font-size: var(--text-xl);">
            <?= $decline ? 'Meld afbud' : 'Tilmeld dig' ?>
        </h1>
        <p class="text-soft small">
            <?= escape($event['confirmand_name']) ?>s <?= escape($event['name']) ?>
            <br>
            <?= formatDate($event['event_date'], true) ?>
        </p>
    </div>

    <div class="card">
        <form method="POST">
            <input type="hidden" name="rsvp_status" value="<?= $decline ? 'no' : 'yes' ?>">

            <?php if (!$decline): ?>
                <!-- Coming - need details -->
                <div class="form-group">
                    <label class="form-label">Antal voksne *</label>
                    <select name="adults_count" class="form-input">
                        <?php for ($i = 1; $i <= 10; $i++): ?>
                            <option value="<?= $i ?>" <?= ($guest['adults_count'] ?? 1) == $i ? 'selected' : '' ?>>
                                <?= $i ?> voksen<?= $i > 1 ? 'e' : '' ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Antal børn</label>
                    <select name="children_count" class="form-input">
                        <?php for ($i = 0; $i <= 10; $i++): ?>
                            <option value="<?= $i ?>" <?= ($guest['children_count'] ?? 0) == $i ? 'selected' : '' ?>>
                                <?= $i ?> barn/børn
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Allergier eller kostbehov?</label>
                    <textarea name="dietary_notes"
                              class="form-input"
                              rows="3"
                              placeholder="F.eks. vegetar, glutenfri, nøddeallergi..."><?= escape($guest['dietary_notes'] ?? '') ?></textarea>
                    <p class="small text-muted mt-xs">
                        Fortæl os gerne hvis du eller dine gæster har særlige behov, så vi kan tage højde for det.
                    </p>
                </div>

            <?php else: ?>
                <!-- Declining -->
                <p class="mb-md" style="text-align: center;">
                    Vi er kede af at høre at du ikke kan komme.
                    <br>
                    Tak fordi du giver os besked.
                </p>

                <div class="form-group">
                    <label class="form-label">Vil du sende en hilsen? (valgfrit)</label>
                    <textarea name="dietary_notes"
                              class="form-input"
                              rows="3"
                              placeholder="Send en hilsen til <?= escape($event['confirmand_name']) ?>..."><?= escape($guest['dietary_notes'] ?? '') ?></textarea>
                </div>

                <input type="hidden" name="adults_count" value="0">
                <input type="hidden" name="children_count" value="0">
            <?php endif; ?>

            <button type="submit" class="btn btn--primary btn--large btn--block">
                <?= $decline ? 'Send afbud' : 'Bekræft tilmelding' ?>
            </button>

            <a href="/guest/index.php" class="btn btn--ghost btn--block mt-sm">
                Annuller
            </a>
        </form>
    </div>

    <?php if (!$decline && $guest['rsvp_status'] !== 'pending'): ?>
        <div class="text-center mt-md">
            <a href="?decline=1" class="link-underline small text-muted">
                Jeg kan alligevel ikke komme
            </a>
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/guest-footer.php'; ?>
</body>
</html>

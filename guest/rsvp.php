<?php
/**
 * Guest - RSVP Form
 */
require_once __DIR__ . '/../includes/guest-header.php';
require_once __DIR__ . '/../includes/gdpr.php';

$decline = isset($_GET['decline']);
$success = false;
$successType = null;

// Get max guests for this invitation
$maxGuests = max(1, (int)($guest['max_guests'] ?? 1));

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $rsvpStatus = $_POST['rsvp_status'] ?? 'yes';
    $adultsCount = max(1, min($maxGuests, (int)($_POST['adults_count'] ?? 1))); // Limit to max_guests
    $childrenCount = max(0, (int)($_POST['children_count'] ?? 0));
    $dietaryNotes = trim($_POST['dietary_notes'] ?? '');

    // Collect guest names
    $guestNames = [];
    if ($rsvpStatus === 'yes' && isset($_POST['guest_names']) && is_array($_POST['guest_names'])) {
        foreach ($_POST['guest_names'] as $name) {
            $name = trim($name);
            if (!empty($name)) {
                $guestNames[] = $name;
            }
        }
    }
    $guestNamesJson = !empty($guestNames) ? json_encode($guestNames, JSON_UNESCAPED_UNICODE) : null;

    // Ensure total doesn't exceed max
    $totalGuests = $adultsCount + $childrenCount;
    if ($totalGuests > $maxGuests) {
        $childrenCount = max(0, $maxGuests - $adultsCount);
    }

    $stmt = $db->prepare("
        UPDATE guests
        SET rsvp_status = ?,
            adults_count = ?,
            children_count = ?,
            dietary_notes = ?,
            guest_names = ?,
            rsvp_date = NOW()
        WHERE id = ? AND event_id = ?
    ");

    $stmt->execute([
        $rsvpStatus,
        $rsvpStatus === 'yes' ? $adultsCount : 0,
        $rsvpStatus === 'yes' ? $childrenCount : 0,
        $dietaryNotes ?: null,
        $guestNamesJson,
        $guestId,
        $eventId
    ]);

    // Record GDPR consent
    $privacyConsent = isset($_POST['privacy_consent']);
    $marketingConsent = isset($_POST['marketing_consent']);
    if ($privacyConsent) {
        recordGuestPrivacyConsent($db, $guestId, $privacyConsent, $marketingConsent);
    }

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
                <strong><?= $guest['adults_count'] ?></strong> person<?= $guest['adults_count'] > 1 ? 'er' : '' ?>
            </p>
            <?php if ($guest['dietary_notes']): ?>
                <p class="small text-muted">
                    Kostbehov: <?= escape($guest['dietary_notes']) ?>
                </p>
            <?php endif; ?>
        <?php endif; ?>

        <a href="<?= BASE_PATH ?>/guest/index.php" class="btn btn--primary btn--block mt-md">
            Tilbage til forsiden
        </a>
    </div>

    <div class="card">
        <h3 class="card__title mb-sm">Se mere</h3>
        <div style="display: grid; gap: var(--space-xs);">
            <a href="<?= BASE_PATH ?>/guest/wishlist.php" class="btn btn--secondary btn--block" style="justify-content: flex-start;">
                🎁 Se ønskeliste
            </a>
            <a href="<?= BASE_PATH ?>/guest/menu.php" class="btn btn--secondary btn--block" style="justify-content: flex-start;">
                🍽️ Se menu
            </a>
            <a href="<?= BASE_PATH ?>/guest/schedule.php" class="btn btn--secondary btn--block" style="justify-content: flex-start;">
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
            <?= csrfField() ?>
            <input type="hidden" name="rsvp_status" value="<?= $decline ? 'no' : 'yes' ?>">

            <?php if (!$decline): ?>
                <?php
                // Get existing guest names
                $existingNames = [];
                if (!empty($guest['guest_names'])) {
                    $existingNames = json_decode($guest['guest_names'], true) ?: [];
                }
                $currentCount = max(1, (int)($guest['adults_count'] ?? 1));
                ?>
                <!-- Coming - need details -->
                <div class="form-group">
                    <label class="form-label">Antal personer *</label>
                    <select name="adults_count" id="adults_count" class="form-input" onchange="updateNameFields()">
                        <?php for ($i = 1; $i <= $maxGuests; $i++): ?>
                            <option value="<?= $i ?>" <?= $currentCount == $i ? 'selected' : '' ?>>
                                <?= $i ?> person<?= $i > 1 ? 'er' : '' ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <p class="small text-muted mt-xs">
                        <?php if ($maxGuests > 1): ?>
                            Denne invitation gælder for op til <?= $maxGuests ?> personer.
                        <?php else: ?>
                            Hvor mange kommer I i alt?
                        <?php endif; ?>
                    </p>
                </div>

                <input type="hidden" name="children_count" value="0">

                <!-- Guest Names -->
                <div class="form-group" id="guest-names-section">
                    <label class="form-label">Navne på deltagere *</label>
                    <p class="small text-muted mb-sm">Til bordplan</p>
                    <div id="guest-names-container">
                        <?php for ($i = 0; $i < $maxGuests; $i++): ?>
                            <div class="guest-name-field" id="name-field-<?= $i ?>" style="<?= $i >= $currentCount ? 'display:none;' : '' ?> margin-bottom: 0.5rem;">
                                <input type="text"
                                       name="guest_names[]"
                                       class="form-input"
                                       placeholder="Navn på person <?= $i + 1 ?>"
                                       value="<?= escape($existingNames[$i] ?? '') ?>"
                                       <?= $i < $currentCount ? 'required' : '' ?>>
                            </div>
                        <?php endfor; ?>
                    </div>
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

                <script>
                function updateNameFields() {
                    const count = parseInt(document.getElementById('adults_count').value);
                    const maxGuests = <?= $maxGuests ?>;

                    for (let i = 0; i < maxGuests; i++) {
                        const field = document.getElementById('name-field-' + i);
                        const input = field.querySelector('input');

                        if (i < count) {
                            field.style.display = 'block';
                            input.required = true;
                        } else {
                            field.style.display = 'none';
                            input.required = false;
                            input.value = '';
                        }
                    }
                }
                </script>

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

            <!-- GDPR Consent -->
            <div class="form-group consent-section" style="margin-top: var(--space-md); padding-top: var(--space-md); border-top: 1px solid var(--color-border);">
                <div class="consent-checkbox" style="margin-bottom: var(--space-sm);">
                    <label style="display: flex; align-items: flex-start; gap: var(--space-xs); cursor: pointer;">
                        <input type="checkbox"
                               name="privacy_consent"
                               value="1"
                               required
                               style="margin-top: 3px; flex-shrink: 0;"
                               <?= !empty($guest['privacy_consent']) ? 'checked' : '' ?>>
                        <span class="small">
                            Jeg accepterer at mine oplysninger gemmes til brug for dette arrangement.
                            <a href="<?= BASE_PATH ?>/legal/privacy.php" target="_blank" class="link-underline">Læs privatlivspolitik</a> *
                        </span>
                    </label>
                </div>
                <div class="consent-checkbox">
                    <label style="display: flex; align-items: flex-start; gap: var(--space-xs); cursor: pointer;">
                        <input type="checkbox"
                               name="marketing_consent"
                               value="1"
                               style="margin-top: 3px; flex-shrink: 0;"
                               <?= !empty($guest['marketing_consent']) ? 'checked' : '' ?>>
                        <span class="small text-muted">
                            Jeg vil gerne modtage nyheder og tilbud fra Partypart (valgfrit)
                        </span>
                    </label>
                </div>
            </div>

            <button type="submit" class="btn btn--primary btn--large btn--block">
                <?= $decline ? 'Send afbud' : 'Bekræft tilmelding' ?>
            </button>

            <a href="<?= BASE_PATH ?>/guest/index.php" class="btn btn--ghost btn--block mt-sm">
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

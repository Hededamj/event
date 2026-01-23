<?php
/**
 * Guest RSVP Page
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rsvp_action'])) {
    $rsvpStatus = $_POST['rsvp_action'] === 'accept' ? 'accepted' : 'declined';
    $adultsCount = max(1, (int)($_POST['adults_count'] ?? 1));
    $childrenCount = max(0, (int)($_POST['children_count'] ?? 0));
    $dietaryNotes = trim($_POST['dietary_notes'] ?? '');

    $stmt = $db->prepare("
        UPDATE guests SET
            rsvp_status = ?,
            adults_count = ?,
            children_count = ?,
            dietary_notes = ?,
            rsvp_responded_at = NOW()
        WHERE id = ? AND event_id = ?
    ");
    $stmt->execute([
        $rsvpStatus,
        $rsvpStatus === 'accepted' ? $adultsCount : 0,
        $rsvpStatus === 'accepted' ? $childrenCount : 0,
        $rsvpStatus === 'accepted' ? ($dietaryNotes ?: null) : null,
        $currentGuest['id'],
        $eventId
    ]);

    $message = $rsvpStatus === 'accepted' ? 'Tak for din tilmelding!' : 'Vi har registreret dit afbud.';
    setFlash('success', $message);
    redirect("/e/$slug/home");
}
?>

<h1 class="serif" style="font-size: 24px; text-align: center; margin-bottom: 24px;">Tilmelding</h1>

<div class="card">
    <p style="text-align: center; margin-bottom: 24px; color: var(--gray-600);">
        Vil du deltage i <?= htmlspecialchars($event['name'] ?? 'arrangementet') ?>?
    </p>

    <form method="POST" id="rsvpForm">
        <!-- Accept Form -->
        <div id="acceptForm" style="display: none;">
            <div class="form-group">
                <label class="form-label">Antal voksne</label>
                <select name="adults_count" class="form-input">
                    <?php for ($i = 1; $i <= 10; $i++): ?>
                        <option value="<?= $i ?>" <?= ($currentGuest['adults_count'] ?? 1) == $i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Antal børn</label>
                <select name="children_count" class="form-input">
                    <?php for ($i = 0; $i <= 10; $i++): ?>
                        <option value="<?= $i ?>" <?= ($currentGuest['children_count'] ?? 0) == $i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Allergier eller diætbehov</label>
                <textarea name="dietary_notes" class="form-input" rows="3" placeholder="F.eks. glutenfri, vegetar, nøddeallergi..."><?= htmlspecialchars($currentGuest['dietary_notes'] ?? '') ?></textarea>
            </div>

            <button type="submit" name="rsvp_action" value="accept" class="btn btn-primary btn-full" style="margin-bottom: 12px;">
                Bekræft tilmelding
            </button>
            <button type="button" class="btn btn-secondary btn-full" onclick="showChoices()">Tilbage</button>
        </div>

        <!-- Choice Buttons -->
        <div id="choiceButtons">
            <button type="button" class="btn btn-primary btn-full" style="margin-bottom: 12px;" onclick="showAcceptForm()">
                Ja, jeg kommer!
            </button>
            <button type="submit" name="rsvp_action" value="decline" class="btn btn-secondary btn-full">
                Desværre, jeg kan ikke komme
            </button>
        </div>
    </form>
</div>

<?php if ($currentGuest['rsvp_status'] !== 'pending'): ?>
<p style="text-align: center; color: var(--gray-400); font-size: 14px; margin-top: 16px;">
    Du har allerede svaret. Du kan ændre dit svar ovenfor.
</p>
<?php endif; ?>

<script>
function showAcceptForm() {
    document.getElementById('choiceButtons').style.display = 'none';
    document.getElementById('acceptForm').style.display = 'block';
}

function showChoices() {
    document.getElementById('choiceButtons').style.display = 'block';
    document.getElementById('acceptForm').style.display = 'none';
}

// If already accepted, show the form directly
<?php if ($currentGuest['rsvp_status'] === 'accepted'): ?>
showAcceptForm();
<?php endif; ?>
</script>

<?php
/**
 * Guest RSVP Page
 */
require_once __DIR__ . '/../../includes/gdpr.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['rsvp_action'])) {
    $action = trim($_POST['rsvp_action']);
    $rsvpStatus = ($action === 'accept') ? 'accepted' : 'declined';
    $adultsCount = max(1, (int)($_POST['adults_count'] ?? 1));
    $childrenCount = max(0, (int)($_POST['children_count'] ?? 0));
    $dietaryNotes = trim($_POST['dietary_notes'] ?? '');

    // Collect guest names as JSON array
    $guestNamesArray = [];
    if ($rsvpStatus === 'accepted') {
        // Collect adult names
        for ($i = 1; $i <= $adultsCount; $i++) {
            $name = trim($_POST["adult_name_$i"] ?? '');
            if ($name) {
                $guestNamesArray[] = ['name' => $name, 'type' => 'adult'];
            }
        }
        // Collect children names
        for ($i = 1; $i <= $childrenCount; $i++) {
            $name = trim($_POST["child_name_$i"] ?? '');
            $age = trim($_POST["child_age_$i"] ?? '');
            if ($name) {
                $guestNamesArray[] = ['name' => $name, 'type' => 'child', 'age' => $age];
            }
        }
    }
    $guestNamesJson = !empty($guestNamesArray) ? json_encode($guestNamesArray, JSON_UNESCAPED_UNICODE) : null;

    $stmt = $db->prepare("
        UPDATE guests SET
            rsvp_status = ?,
            adults_count = ?,
            children_count = ?,
            guest_names = ?,
            dietary_notes = ?,
            rsvp_responded_at = NOW()
        WHERE id = ? AND event_id = ?
    ");
    $stmt->execute([
        $rsvpStatus,
        $rsvpStatus === 'accepted' ? $adultsCount : 0,
        $rsvpStatus === 'accepted' ? $childrenCount : 0,
        $guestNamesJson,
        $rsvpStatus === 'accepted' ? ($dietaryNotes ?: null) : null,
        $currentGuest['id'],
        $eventId
    ]);

    if ($stmt->rowCount() === 0) {
        setFlash('error', "Fejl: Kunne ikke opdatere tilmelding.");
        redirect("/e/$slug/rsvp");
    }

    // Record GDPR consent
    $privacyConsent = isset($_POST['privacy_consent']);
    $marketingConsent = isset($_POST['marketing_consent']);
    if ($privacyConsent) {
        recordGuestPrivacyConsent($db, $currentGuest['id'], $privacyConsent, $marketingConsent);
    }

    $message = $rsvpStatus === 'accepted' ? 'Tak for din tilmelding!' : 'Vi har registreret dit afbud.';
    setFlash('success', $message);
    redirect("/e/$slug/home");
}

// Parse existing guest names from JSON
$existingNames = [];
if (!empty($currentGuest['guest_names'])) {
    $decoded = json_decode($currentGuest['guest_names'], true);
    if (is_array($decoded)) {
        $existingNames = $decoded;
    }
}
$existingAdults = array_filter($existingNames, fn($g) => ($g['type'] ?? '') === 'adult');
$existingChildren = array_filter($existingNames, fn($g) => ($g['type'] ?? '') === 'child');
$existingAdults = array_values($existingAdults);
$existingChildren = array_values($existingChildren);
?>

<h1 class="serif" style="font-size: 24px; text-align: center; margin-bottom: 24px;">Tilmelding</h1>

<div class="card">
    <p style="text-align: center; margin-bottom: 24px; color: var(--gray-600);">
        Vil du deltage i <?= htmlspecialchars($event['name'] ?? 'arrangementet') ?>?
    </p>

    <form method="POST" id="rsvpForm">
        <input type="hidden" name="rsvp_action" id="rsvpActionInput" value="">
        <!-- Accept Form -->
        <div id="acceptForm" style="display: none;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px;">
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Antal voksne</label>
                    <select name="adults_count" id="adultsCount" class="form-input" onchange="updateNameFields()">
                        <?php for ($i = 1; $i <= 10; $i++): ?>
                            <option value="<?= $i ?>" <?= ($currentGuest['adults_count'] ?? 1) == $i ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Antal børn</label>
                    <select name="children_count" id="childrenCount" class="form-input" onchange="updateNameFields()">
                        <?php for ($i = 0; $i <= 10; $i++): ?>
                            <option value="<?= $i ?>" <?= ($currentGuest['children_count'] ?? 0) == $i ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <!-- Dynamic name fields -->
            <div id="nameFields">
                <div id="adultFields"></div>
                <div id="childFields"></div>
            </div>

            <div class="form-group">
                <label class="form-label">Allergier eller diætbehov</label>
                <textarea name="dietary_notes" class="form-input" rows="2" placeholder="F.eks. glutenfri, vegetar, nøddeallergi..."><?= htmlspecialchars($currentGuest['dietary_notes'] ?? '') ?></textarea>
            </div>

            <!-- GDPR Consent -->
            <div class="form-group" style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--gray-200);">
                <label style="display: flex; align-items: flex-start; gap: 8px; cursor: pointer; margin-bottom: 12px;">
                    <input type="checkbox" name="privacy_consent" value="1" required style="margin-top: 3px;"
                           <?= !empty($currentGuest['privacy_consent']) ? 'checked' : '' ?>>
                    <span style="font-size: 13px; color: var(--gray-600);">
                        Jeg accepterer at mine oplysninger gemmes til brug for dette arrangement. *
                    </span>
                </label>
            </div>

            <button type="button" class="btn btn-primary btn-full" style="margin-bottom: 12px;" onclick="submitRsvp('accept')">
                Bekræft tilmelding
            </button>
            <button type="button" class="btn btn-secondary btn-full" onclick="showChoices()">Tilbage</button>
        </div>

        <!-- Choice Buttons -->
        <div id="choiceButtons">
            <button type="button" class="btn btn-primary btn-full" style="margin-bottom: 12px;" onclick="showAcceptForm()">
                Ja, jeg kommer!
            </button>
            <button type="button" class="btn btn-secondary btn-full" onclick="submitRsvp('decline')">
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

<style>
    .name-field-group {
        margin-bottom: 12px;
        padding: 12px;
        background: var(--gray-50);
        border-radius: 8px;
    }
    .name-field-group label {
        display: block;
        font-size: 13px;
        font-weight: 500;
        color: var(--gray-600);
        margin-bottom: 6px;
    }
    .child-row {
        display: grid;
        grid-template-columns: 1fr 80px;
        gap: 8px;
    }
</style>

<script>
// Existing names from database
const existingAdults = <?= json_encode($existingAdults) ?>;
const existingChildren = <?= json_encode($existingChildren) ?>;

function updateNameFields() {
    const adultsCount = parseInt(document.getElementById('adultsCount').value);
    const childrenCount = parseInt(document.getElementById('childrenCount').value);

    // Adult fields
    let adultHtml = '';
    if (adultsCount > 0) {
        adultHtml = '<p style="font-size: 13px; font-weight: 600; color: var(--gray-700); margin-bottom: 8px;">Voksne</p>';
        for (let i = 1; i <= adultsCount; i++) {
            const existing = existingAdults[i-1] || {};
            adultHtml += `
                <div class="name-field-group">
                    <label>Voksen ${i}</label>
                    <input type="text" name="adult_name_${i}" class="form-input"
                           placeholder="Fulde navn" value="${existing.name || ''}" required>
                </div>
            `;
        }
    }
    document.getElementById('adultFields').innerHTML = adultHtml;

    // Child fields
    let childHtml = '';
    if (childrenCount > 0) {
        childHtml = '<p style="font-size: 13px; font-weight: 600; color: var(--gray-700); margin-bottom: 8px; margin-top: 16px;">Børn</p>';
        for (let i = 1; i <= childrenCount; i++) {
            const existing = existingChildren[i-1] || {};
            childHtml += `
                <div class="name-field-group">
                    <label>Barn ${i}</label>
                    <div class="child-row">
                        <input type="text" name="child_name_${i}" class="form-input"
                               placeholder="Navn" value="${existing.name || ''}" required>
                        <input type="text" name="child_age_${i}" class="form-input"
                               placeholder="Alder" value="${existing.age || ''}">
                    </div>
                </div>
            `;
        }
    }
    document.getElementById('childFields').innerHTML = childHtml;
}

function showAcceptForm() {
    document.getElementById('choiceButtons').style.display = 'none';
    document.getElementById('acceptForm').style.display = 'block';
    updateNameFields();
}

function showChoices() {
    document.getElementById('choiceButtons').style.display = 'block';
    document.getElementById('acceptForm').style.display = 'none';
}

function submitRsvp(action) {
    document.getElementById('rsvpActionInput').value = action;
    document.getElementById('rsvpForm').submit();
}

// If already accepted, show the form directly
<?php if ($currentGuest['rsvp_status'] === 'accepted'): ?>
showAcceptForm();
<?php endif; ?>
</script>

<?php
/**
 * Guest RSVP Page - Nordic Design
 */
require_once __DIR__ . '/../../includes/gdpr.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['rsvp_action'])) {
    $action = trim($_POST['rsvp_action']);
    // ENUM values are: 'pending', 'yes', 'no'
    $rsvpStatus = ($action === 'accept') ? 'yes' : 'no';
    $adultsCount = max(1, (int)($_POST['adults_count'] ?? 1));
    $childrenCount = max(0, (int)($_POST['children_count'] ?? 0));
    $dietaryNotes = trim($_POST['dietary_notes'] ?? '');

    // Collect guest names as JSON array
    $guestNamesArray = [];
    if ($rsvpStatus === 'yes') {
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
        $rsvpStatus === 'yes' ? $adultsCount : 0,
        $rsvpStatus === 'yes' ? $childrenCount : 0,
        $guestNamesJson,
        $rsvpStatus === 'yes' ? ($dietaryNotes ?: null) : null,
        $currentGuest['id'],
        $eventId
    ]);


    // Record GDPR consent
    $privacyConsent = isset($_POST['privacy_consent']);
    $marketingConsent = isset($_POST['marketing_consent']);
    if ($privacyConsent) {
        recordGuestPrivacyConsent($db, $currentGuest['id'], $privacyConsent, $marketingConsent);
    }

    $message = $rsvpStatus === 'yes' ? 'Tak for din tilmelding!' : 'Vi har registreret dit afbud.';
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

<!-- Page Header -->
<div class="page-header" style="text-align: center;">
    <h1 class="page-header-title">Tilmelding</h1>
    <p class="page-header-subtitle">
        Vil du deltage i <?= htmlspecialchars($event['name'] ?? 'arrangementet') ?>?
    </p>
</div>

<div class="card">
    <form method="POST" id="rsvpForm">
        <input type="hidden" name="rsvp_action" id="rsvpActionInput" value="">

        <!-- Accept Form -->
        <div id="acceptForm" style="display: none;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
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
            <div class="form-group" style="margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--cream-dark);">
                <label style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer;">
                    <input type="checkbox" name="privacy_consent" value="1" required style="margin-top: 4px; width: 18px; height: 18px; accent-color: var(--sage);"
                           <?= !empty($currentGuest['privacy_consent']) ? 'checked' : '' ?>>
                    <span style="font-size: 13px; color: var(--charcoal-light); line-height: 1.5;">
                        Jeg accepterer at mine oplysninger gemmes til brug for dette arrangement. *
                    </span>
                </label>
            </div>

            <button type="button" class="btn btn-sage btn-full" style="margin-bottom: 12px;" onclick="submitRsvp('accept')">
                Bekræft tilmelding
            </button>
            <button type="button" class="btn btn-secondary btn-full" onclick="showChoices()">Tilbage</button>
        </div>

        <!-- Choice Buttons -->
        <div id="choiceButtons" style="text-align: center;">
            <div style="margin-bottom: 32px;">
                <div style="width: 72px; height: 72px; background: var(--cream); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; color: var(--sage-dark);">
                    <svg width="36" height="36" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <p style="font-size: 15px; color: var(--charcoal-light);">Giv os besked om du deltager</p>
            </div>

            <button type="button" class="btn btn-sage btn-full" style="margin-bottom: 12px;" onclick="showAcceptForm()">
                Ja, jeg kommer!
            </button>
            <button type="button" class="btn btn-secondary btn-full" onclick="submitRsvp('decline')">
                Desværre, jeg kan ikke komme
            </button>
        </div>
    </form>
</div>

<?php if ($currentGuest['rsvp_status'] !== 'pending'): ?>
<p style="text-align: center; color: var(--charcoal-light); font-size: 14px; margin-top: 20px;">
    Du har allerede svaret. Du kan ændre dit svar ovenfor.
</p>
<?php endif; ?>

<style>
    .name-field-group {
        margin-bottom: 16px;
        padding: 16px;
        background: var(--cream);
        border-radius: 14px;
    }
    .name-field-group label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: var(--charcoal-light);
        margin-bottom: 8px;
    }
    .name-section-title {
        font-size: 14px;
        font-weight: 600;
        color: var(--charcoal);
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .name-section-title svg {
        width: 18px;
        height: 18px;
        color: var(--sage-dark);
    }
    .child-row {
        display: grid;
        grid-template-columns: 1fr 80px;
        gap: 10px;
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
        adultHtml = '<div class="name-section-title"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg> Voksne</div>';
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
        childHtml = '<div class="name-section-title" style="margin-top: 24px;"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> Børn</div>';
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
<?php if ($currentGuest['rsvp_status'] === 'yes'): ?>
showAcceptForm();
<?php endif; ?>
</script>

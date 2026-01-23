<?php
/**
 * Event Settings Page
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyAccountCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Ugyldig anmodning.');
        redirect("?id=$eventId&page=settings");
    }

    $action = $_POST['action'];

    if ($action === 'update_settings') {
        $name = trim($_POST['name'] ?? '');
        $mainPersonName = trim($_POST['main_person_name'] ?? '');
        $secondaryPersonName = trim($_POST['secondary_person_name'] ?? '');
        $eventDate = $_POST['event_date'] ?? null;
        $eventTime = $_POST['event_time'] ?? null;
        $location = trim($_POST['location'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $welcomeText = trim($_POST['welcome_text'] ?? '');
        $theme = $_POST['theme'] ?? 'elegant';
        $status = $_POST['status'] ?? 'active';

        $stmt = $db->prepare("
            UPDATE events SET
                name = ?, main_person_name = ?, secondary_person_name = ?,
                event_date = ?, event_time = ?, location = ?, address = ?,
                welcome_text = ?, theme = ?, status = ?
            WHERE id = ? AND account_id = ?
        ");
        $stmt->execute([
            $name, $mainPersonName, $secondaryPersonName ?: null,
            $eventDate, $eventTime ?: null, $location ?: null, $address ?: null,
            $welcomeText ?: null, $theme, $status,
            $eventId, $accountId
        ]);

        setFlash('success', 'Indstillinger gemt.');
        redirect("?id=$eventId&page=settings");

    } elseif ($action === 'delete_event') {
        // Verify password before deletion
        $password = $_POST['delete_password'] ?? '';
        $stmt = $db->prepare("SELECT password_hash FROM accounts WHERE id = ?");
        $stmt->execute([$accountId]);
        $account = $stmt->fetch();

        if (!password_verify($password, $account['password_hash'])) {
            setFlash('error', 'Forkert adgangskode.');
            redirect("?id=$eventId&page=settings");
        }

        // Delete event (cascades to related tables)
        $stmt = $db->prepare("DELETE FROM events WHERE id = ? AND account_id = ?");
        $stmt->execute([$eventId, $accountId]);

        setFlash('success', 'Arrangement slettet.');
        redirect('/app/dashboard.php');
    }
}

// Get event types for dropdown
$eventTypes = getAllEventTypes();
?>

<div class="settings-sections">
    <!-- General Settings -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Generelle indstillinger</h2>
        </div>
        <form method="POST">
            <?= accountCsrfField() ?>
            <input type="hidden" name="action" value="update_settings">

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Arrangementsnavn</label>
                    <input type="text" name="name" class="form-input" value="<?= htmlspecialchars($event['name'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-input">
                        <option value="draft" <?= ($event['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Kladde</option>
                        <option value="active" <?= ($event['status'] ?? '') === 'active' ? 'selected' : '' ?>>Aktiv</option>
                        <option value="completed" <?= ($event['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Afsluttet</option>
                        <option value="archived" <?= ($event['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Arkiveret</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Hovedperson</label>
                    <input type="text" name="main_person_name" class="form-input" value="<?= htmlspecialchars($event['main_person_name'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Partner/Sekundær person</label>
                    <input type="text" name="secondary_person_name" class="form-input" value="<?= htmlspecialchars($event['secondary_person_name'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Dato</label>
                    <input type="date" name="event_date" class="form-input" value="<?= htmlspecialchars($event['event_date'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Tidspunkt</label>
                    <input type="time" name="event_time" class="form-input" value="<?= $event['event_time'] ? htmlspecialchars(substr($event['event_time'], 0, 5)) : '' ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Sted</label>
                    <input type="text" name="location" class="form-input" value="<?= htmlspecialchars($event['location'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Adresse</label>
                    <input type="text" name="address" class="form-input" value="<?= htmlspecialchars($event['address'] ?? '') ?>">
                </div>

                <div class="form-group full-width">
                    <label class="form-label">Velkomsttekst</label>
                    <textarea name="welcome_text" class="form-input" rows="3"><?= htmlspecialchars($event['welcome_text'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Tema</label>
                    <select name="theme" class="form-input">
                        <option value="elegant" <?= ($event['theme'] ?? '') === 'elegant' ? 'selected' : '' ?>>Elegant</option>
                        <option value="romantic" <?= ($event['theme'] ?? '') === 'romantic' ? 'selected' : '' ?>>Romantisk</option>
                        <option value="modern" <?= ($event['theme'] ?? '') === 'modern' ? 'selected' : '' ?>>Moderne</option>
                        <option value="nature" <?= ($event['theme'] ?? '') === 'nature' ? 'selected' : '' ?>>Natur</option>
                        <option value="golden" <?= ($event['theme'] ?? '') === 'golden' ? 'selected' : '' ?>>Guld</option>
                        <option value="minimal" <?= ($event['theme'] ?? '') === 'minimal' ? 'selected' : '' ?>>Minimalistisk</option>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Gem ændringer</button>
            </div>
        </form>
    </div>

    <!-- Share Link -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Gæstelink</h2>
        </div>
        <div class="share-section">
            <p class="share-desc">Del dette link med dine gæster så de kan tilgå deres invitation:</p>
            <?php if ($event['slug']): ?>
            <div class="share-link-box">
                <code id="shareLink"><?= htmlspecialchars(baseUrl()) ?>/e/<?= htmlspecialchars($event['slug']) ?>/</code>
                <button type="button" class="btn btn-secondary" onclick="copyLink()">Kopier</button>
            </div>
            <?php else: ?>
            <p class="no-link">Intet link tilgængeligt.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Danger Zone -->
    <div class="card danger-zone">
        <div class="card-header">
            <h2 class="card-title" style="color: var(--danger);">Farezone</h2>
        </div>
        <div class="danger-content">
            <div class="danger-item">
                <div>
                    <h4>Slet arrangement</h4>
                    <p>Når arrangementet er slettet, kan det ikke gendannes. Alle data inkl. gæster, ønskeliste og fotos slettes permanent.</p>
                </div>
                <button type="button" class="btn btn-danger" onclick="showDeleteModal()">Slet arrangement</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal-overlay" id="deleteModal" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3>Slet arrangement</h3>
            <button type="button" class="modal-close" onclick="hideDeleteModal()">&times;</button>
        </div>
        <form method="POST">
            <?= accountCsrfField() ?>
            <input type="hidden" name="action" value="delete_event">
            <div class="modal-body">
                <p class="delete-warning">Du er ved at slette <strong><?= htmlspecialchars($event['name']) ?></strong>. Denne handling kan ikke fortrydes.</p>
                <div class="form-group">
                    <label class="form-label">Bekræft med din adgangskode</label>
                    <input type="password" name="delete_password" class="form-input" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideDeleteModal()">Annuller</button>
                <button type="submit" class="btn btn-danger">Slet permanent</button>
            </div>
        </form>
    </div>
</div>

<style>
    .settings-sections { display: flex; flex-direction: column; gap: 24px; }
    .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 24px; }
    .form-group.full-width { grid-column: span 2; }
    .form-actions { padding-top: 16px; border-top: 1px solid var(--gray-100); }
    .share-section { padding: 8px 0; }
    .share-desc { color: var(--gray-600); margin-bottom: 16px; font-size: 14px; }
    .share-link-box { display: flex; gap: 12px; align-items: center; background: var(--gray-50); padding: 12px; border-radius: 8px; }
    .share-link-box code { flex: 1; font-size: 14px; word-break: break-all; }
    .danger-zone { border-color: #fecaca; }
    .danger-content { padding: 8px 0; }
    .danger-item { display: flex; justify-content: space-between; align-items: center; gap: 24px; }
    .danger-item h4 { font-size: 15px; font-weight: 600; color: var(--gray-900); margin-bottom: 4px; }
    .danger-item p { font-size: 14px; color: var(--gray-600); }
    .btn-danger { background: var(--danger); color: white; }
    .delete-warning { background: #fef2f2; border: 1px solid #fecaca; padding: 12px; border-radius: 8px; margin-bottom: 20px; color: #dc2626; font-size: 14px; }
    @media (max-width: 640px) { .form-grid { grid-template-columns: 1fr; } .form-group.full-width { grid-column: span 1; } .danger-item { flex-direction: column; align-items: flex-start; } }
</style>

<script>
function copyLink() {
    const link = document.getElementById('shareLink').textContent;
    navigator.clipboard.writeText(link).then(() => {
        alert('Link kopieret!');
    });
}
function showDeleteModal() { document.getElementById('deleteModal').style.display = 'flex'; }
function hideDeleteModal() { document.getElementById('deleteModal').style.display = 'none'; }
document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', function(e) { if (e.target === this) this.style.display = 'none'; });
});
</script>

<?php
/**
 * Guests Management Page
 * Included by manage.php
 */

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyAccountCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Ugyldig anmodning.');
        redirect("?id=$eventId&page=guests");
    }

    $action = $_POST['action'];

    if ($action === 'add_guest') {
        $name = trim($_POST['guest_name'] ?? '');
        $email = trim($_POST['guest_email'] ?? '');
        $phone = trim($_POST['guest_phone'] ?? '');
        $notes = trim($_POST['guest_notes'] ?? '');

        if ($name) {
            // Generate unique code
            $code = generateGuestCode();
            $attempts = 0;
            while ($attempts < 10) {
                $stmt = $db->prepare("SELECT id FROM guests WHERE event_id = ? AND unique_code = ?");
                $stmt->execute([$eventId, $code]);
                if (!$stmt->fetch()) break;
                $code = generateGuestCode();
                $attempts++;
            }

            $stmt = $db->prepare("
                INSERT INTO guests (event_id, name, email, phone, notes, unique_code, rsvp_status)
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$eventId, $name, $email ?: null, $phone ?: null, $notes ?: null, $code]);

            setFlash('success', 'Gæst tilføjet.');
        }
        redirect("?id=$eventId&page=guests");

    } elseif ($action === 'delete_guest') {
        $guestId = (int)($_POST['guest_id'] ?? 0);
        if ($guestId) {
            $stmt = $db->prepare("DELETE FROM guests WHERE id = ? AND event_id = ?");
            $stmt->execute([$guestId, $eventId]);
            setFlash('success', 'Gæst slettet.');
        }
        redirect("?id=$eventId&page=guests");

    } elseif ($action === 'update_guest') {
        $guestId = (int)($_POST['guest_id'] ?? 0);
        $name = trim($_POST['guest_name'] ?? '');
        $email = trim($_POST['guest_email'] ?? '');
        $phone = trim($_POST['guest_phone'] ?? '');
        $notes = trim($_POST['guest_notes'] ?? '');

        if ($guestId && $name) {
            $stmt = $db->prepare("
                UPDATE guests SET name = ?, email = ?, phone = ?, notes = ?
                WHERE id = ? AND event_id = ?
            ");
            $stmt->execute([$name, $email ?: null, $phone ?: null, $notes ?: null, $guestId, $eventId]);
            setFlash('success', 'Gæst opdateret.');
        }
        redirect("?id=$eventId&page=guests");

    } elseif ($action === 'toggle_invitation') {
        $guestId = (int)($_POST['guest_id'] ?? 0);
        if ($guestId) {
            $stmt = $db->prepare("
                UPDATE guests SET invitation_sent = NOT invitation_sent, invitation_sent_at = IF(invitation_sent, NULL, NOW())
                WHERE id = ? AND event_id = ?
            ");
            $stmt->execute([$guestId, $eventId]);
        }

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            jsonResponse(['success' => true]);
        }
        redirect("?id=$eventId&page=guests");
    }
}

// Get guests with filters
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

$whereClause = "WHERE event_id = ?";
$params = [$eventId];

if ($filter === 'accepted') {
    $whereClause .= " AND rsvp_status = 'accepted'";
} elseif ($filter === 'declined') {
    $whereClause .= " AND rsvp_status = 'declined'";
} elseif ($filter === 'pending') {
    $whereClause .= " AND rsvp_status = 'pending'";
} elseif ($filter === 'not_sent') {
    $whereClause .= " AND invitation_sent = FALSE";
}

if ($search) {
    $whereClause .= " AND (name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$stmt = $db->prepare("
    SELECT * FROM guests $whereClause ORDER BY name ASC
");
$stmt->execute($params);
$guests = $stmt->fetchAll();
?>

<div class="page-header-actions">
    <div>
        <h2 class="section-title">Gæsteliste</h2>
        <p class="section-subtitle"><?= count($guests) ?> gæster</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="showAddModal()">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        Tilføj gæst
    </button>
</div>

<!-- Filters -->
<div class="filters-bar">
    <div class="filter-tabs">
        <a href="?id=<?= $eventId ?>&page=guests&filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">
            Alle (<?= $guestStats['total_guests'] ?>)
        </a>
        <a href="?id=<?= $eventId ?>&page=guests&filter=accepted" class="filter-tab <?= $filter === 'accepted' ? 'active' : '' ?>">
            Kommer (<?= $guestStats['accepted'] ?>)
        </a>
        <a href="?id=<?= $eventId ?>&page=guests&filter=declined" class="filter-tab <?= $filter === 'declined' ? 'active' : '' ?>">
            Afbud (<?= $guestStats['declined'] ?>)
        </a>
        <a href="?id=<?= $eventId ?>&page=guests&filter=pending" class="filter-tab <?= $filter === 'pending' ? 'active' : '' ?>">
            Afventer (<?= $guestStats['pending'] ?>)
        </a>
    </div>
    <form class="search-form" method="GET">
        <input type="hidden" name="id" value="<?= $eventId ?>">
        <input type="hidden" name="page" value="guests">
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
        <input type="text" name="search" placeholder="Søg efter navn eller email..." value="<?= htmlspecialchars($search) ?>" class="search-input">
    </form>
</div>

<!-- Guests Table -->
<?php if (empty($guests)): ?>
<div class="card">
    <div class="empty-state">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
        </svg>
        <h3>Ingen gæster endnu</h3>
        <p>Tilføj dine første gæster for at komme i gang.</p>
        <button type="button" class="btn btn-primary" onclick="showAddModal()">Tilføj gæst</button>
    </div>
</div>
<?php else: ?>
<div class="card" style="padding: 0; overflow: hidden;">
    <table class="data-table">
        <thead>
            <tr>
                <th>Navn</th>
                <th>Email</th>
                <th>Kode</th>
                <th>Status</th>
                <th>Antal</th>
                <th>Invitation</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($guests as $guest): ?>
            <tr>
                <td>
                    <div class="guest-name">
                        <strong><?= htmlspecialchars($guest['name']) ?></strong>
                        <?php if ($guest['phone']): ?>
                            <span class="guest-phone"><?= htmlspecialchars($guest['phone']) ?></span>
                        <?php endif; ?>
                    </div>
                </td>
                <td><?= htmlspecialchars($guest['email'] ?? '-') ?></td>
                <td>
                    <code class="guest-code"><?= htmlspecialchars($guest['unique_code']) ?></code>
                </td>
                <td>
                    <span class="status-badge status-<?= $guest['rsvp_status'] ?>">
                        <?php
                        $statusLabels = ['pending' => 'Afventer', 'accepted' => 'Kommer', 'declined' => 'Kommer ikke'];
                        echo $statusLabels[$guest['rsvp_status']] ?? $guest['rsvp_status'];
                        ?>
                    </span>
                </td>
                <td>
                    <?php if ($guest['rsvp_status'] === 'accepted'): ?>
                        <?= (int)$guest['adults_count'] ?> + <?= (int)$guest['children_count'] ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td>
                    <button type="button" class="invitation-toggle <?= $guest['invitation_sent'] ? 'sent' : '' ?>"
                            onclick="toggleInvitation(<?= $guest['id'] ?>)" title="<?= $guest['invitation_sent'] ? 'Sendt' : 'Ikke sendt' ?>">
                        <?php if ($guest['invitation_sent']): ?>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        <?php else: ?>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                        <?php endif; ?>
                    </button>
                </td>
                <td>
                    <div class="row-actions">
                        <button type="button" class="row-action" onclick='editGuest(<?= json_encode($guest) ?>)' title="Rediger">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                        </button>
                        <button type="button" class="row-action danger" onclick="deleteGuest(<?= $guest['id'] ?>, '<?= htmlspecialchars(addslashes($guest['name'])) ?>')" title="Slet">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Add Guest Modal -->
<div class="modal-overlay" id="addModal" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3>Tilføj gæst</h3>
            <button type="button" class="modal-close" onclick="hideAddModal()">&times;</button>
        </div>
        <form method="POST">
            <?= accountCsrfField() ?>
            <input type="hidden" name="action" value="add_guest">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Navn *</label>
                    <input type="text" name="guest_name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="guest_email" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Telefon</label>
                    <input type="tel" name="guest_phone" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Noter</label>
                    <textarea name="guest_notes" class="form-input" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideAddModal()">Annuller</button>
                <button type="submit" class="btn btn-primary">Tilføj</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Guest Modal -->
<div class="modal-overlay" id="editModal" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3>Rediger gæst</h3>
            <button type="button" class="modal-close" onclick="hideEditModal()">&times;</button>
        </div>
        <form method="POST">
            <?= accountCsrfField() ?>
            <input type="hidden" name="action" value="update_guest">
            <input type="hidden" name="guest_id" id="edit_guest_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Navn *</label>
                    <input type="text" name="guest_name" id="edit_guest_name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="guest_email" id="edit_guest_email" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Telefon</label>
                    <input type="tel" name="guest_phone" id="edit_guest_phone" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Noter</label>
                    <textarea name="guest_notes" id="edit_guest_notes" class="form-input" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideEditModal()">Annuller</button>
                <button type="submit" class="btn btn-primary">Gem</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Guest Form -->
<form method="POST" id="deleteForm" style="display: none;">
    <?= accountCsrfField() ?>
    <input type="hidden" name="action" value="delete_guest">
    <input type="hidden" name="guest_id" id="delete_guest_id">
</form>

<!-- Toggle Invitation Form -->
<form method="POST" id="toggleInvitationForm" style="display: none;">
    <?= accountCsrfField() ?>
    <input type="hidden" name="action" value="toggle_invitation">
    <input type="hidden" name="guest_id" id="toggle_guest_id">
</form>

<style>
    .page-header-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 24px;
    }

    .section-title {
        font-size: 24px;
        font-weight: 700;
        color: var(--gray-900);
        margin-bottom: 4px;
    }

    .section-subtitle {
        color: var(--gray-500);
        font-size: 14px;
    }

    .filters-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 16px;
    }

    .filter-tabs {
        display: flex;
        gap: 8px;
    }

    .filter-tab {
        padding: 8px 16px;
        font-size: 14px;
        color: var(--gray-600);
        text-decoration: none;
        border-radius: 8px;
        transition: all 0.2s;
    }

    .filter-tab:hover {
        background: var(--gray-100);
    }

    .filter-tab.active {
        background: var(--primary);
        color: white;
    }

    .search-form {
        display: flex;
    }

    .search-input {
        padding: 8px 16px;
        border: 1px solid var(--gray-200);
        border-radius: 8px;
        font-size: 14px;
        width: 250px;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table th,
    .data-table td {
        padding: 12px 16px;
        text-align: left;
        border-bottom: 1px solid var(--gray-100);
    }

    .data-table th {
        background: var(--gray-50);
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--gray-500);
    }

    .data-table tbody tr:hover {
        background: var(--gray-50);
    }

    .guest-name {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .guest-phone {
        font-size: 13px;
        color: var(--gray-500);
    }

    .guest-code {
        background: var(--gray-100);
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 13px;
        font-family: monospace;
    }

    .status-badge {
        display: inline-block;
        padding: 4px 10px;
        font-size: 12px;
        font-weight: 500;
        border-radius: 6px;
    }

    .status-pending {
        background: #fef3c7;
        color: #b45309;
    }

    .status-accepted {
        background: #dcfce7;
        color: #15803d;
    }

    .status-declined {
        background: #fef2f2;
        color: #dc2626;
    }

    .invitation-toggle {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--gray-100);
        border: none;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .invitation-toggle svg {
        width: 18px;
        height: 18px;
        color: var(--gray-400);
    }

    .invitation-toggle.sent {
        background: #dcfce7;
    }

    .invitation-toggle.sent svg {
        color: #15803d;
    }

    .row-actions {
        display: flex;
        gap: 8px;
    }

    .row-action {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: none;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        color: var(--gray-400);
        transition: all 0.2s;
    }

    .row-action:hover {
        background: var(--gray-100);
        color: var(--gray-700);
    }

    .row-action.danger:hover {
        background: #fef2f2;
        color: #dc2626;
    }

    .row-action svg {
        width: 18px;
        height: 18px;
    }

    /* Modal */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        padding: 20px;
    }

    .modal {
        background: white;
        border-radius: 16px;
        width: 100%;
        max-width: 500px;
        max-height: 90vh;
        overflow: auto;
    }

    .modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 20px 24px;
        border-bottom: 1px solid var(--gray-200);
    }

    .modal-header h3 {
        font-size: 18px;
        font-weight: 600;
    }

    .modal-close {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: none;
        border: none;
        font-size: 24px;
        color: var(--gray-400);
        cursor: pointer;
        border-radius: 6px;
    }

    .modal-close:hover {
        background: var(--gray-100);
        color: var(--gray-700);
    }

    .modal-body {
        padding: 24px;
    }

    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        padding: 16px 24px;
        border-top: 1px solid var(--gray-200);
    }

    .form-group {
        margin-bottom: 16px;
    }

    .form-group:last-child {
        margin-bottom: 0;
    }

    .form-label {
        display: block;
        font-size: 14px;
        font-weight: 500;
        color: var(--gray-700);
        margin-bottom: 6px;
    }

    .form-input {
        width: 100%;
        padding: 10px 14px;
        font-size: 14px;
        border: 1px solid var(--gray-200);
        border-radius: 8px;
        font-family: inherit;
    }

    .form-input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
</style>

<script>
function showAddModal() {
    document.getElementById('addModal').style.display = 'flex';
}

function hideAddModal() {
    document.getElementById('addModal').style.display = 'none';
}

function editGuest(guest) {
    document.getElementById('edit_guest_id').value = guest.id;
    document.getElementById('edit_guest_name').value = guest.name || '';
    document.getElementById('edit_guest_email').value = guest.email || '';
    document.getElementById('edit_guest_phone').value = guest.phone || '';
    document.getElementById('edit_guest_notes').value = guest.notes || '';
    document.getElementById('editModal').style.display = 'flex';
}

function hideEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function deleteGuest(id, name) {
    if (confirm('Er du sikker på at du vil slette ' + name + '?')) {
        document.getElementById('delete_guest_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}

function toggleInvitation(id) {
    document.getElementById('toggle_guest_id').value = id;
    document.getElementById('toggleInvitationForm').submit();
}

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
});
</script>

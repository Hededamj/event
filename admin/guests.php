<?php
/**
 * Admin - Guest List Management
 */
require_once __DIR__ . '/../includes/admin-header.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($name) {
            // Generate unique code
            $code = generateGuestCode();
            $stmt = $db->prepare("SELECT id FROM guests WHERE unique_code = ? AND event_id = ?");
            $stmt->execute([$code, $eventId]);

            // Ensure uniqueness
            while ($stmt->fetch()) {
                $code = generateGuestCode();
                $stmt->execute([$code, $eventId]);
            }

            $stmt = $db->prepare("
                INSERT INTO guests (event_id, name, email, phone, notes, unique_code)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $eventId,
                $name,
                $email ?: null,
                $phone ?: null,
                $notes ?: null,
                $code
            ]);

            setFlash('success', "Gæst tilføjet! Kode: $code");
            redirect(BASE_PATH . '/admin/guests.php');
        }

    } elseif ($action === 'bulk_add') {
        $names = trim($_POST['names'] ?? '');
        $lines = array_filter(array_map('trim', explode("\n", $names)));
        $added = 0;

        foreach ($lines as $name) {
            if (empty($name)) continue;

            // Generate unique code
            $code = generateGuestCode();
            $stmt = $db->prepare("SELECT id FROM guests WHERE unique_code = ? AND event_id = ?");
            $stmt->execute([$code, $eventId]);

            while ($stmt->fetch()) {
                $code = generateGuestCode();
                $stmt->execute([$code, $eventId]);
            }

            $stmt = $db->prepare("
                INSERT INTO guests (event_id, name, unique_code)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$eventId, $name, $code]);
            $added++;
        }

        setFlash('success', "$added gæster tilføjet!");
        redirect(BASE_PATH . '/admin/guests.php');

    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($name && $id) {
            $stmt = $db->prepare("
                UPDATE guests
                SET name = ?, email = ?, phone = ?, notes = ?
                WHERE id = ? AND event_id = ?
            ");
            $stmt->execute([
                $name,
                $email ?: null,
                $phone ?: null,
                $notes ?: null,
                $id,
                $eventId
            ]);

            setFlash('success', 'Gæst opdateret');
            redirect(BASE_PATH . '/admin/guests.php');
        }

    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $db->prepare("DELETE FROM guests WHERE id = ? AND event_id = ?");
            $stmt->execute([$id, $eventId]);

            setFlash('success', 'Gæst slettet');
            redirect(BASE_PATH . '/admin/guests.php');
        }

    } elseif ($action === 'toggle_invitation') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $db->prepare("
                UPDATE guests
                SET invitation_sent = NOT COALESCE(invitation_sent, 0),
                    invitation_sent_at = CASE WHEN COALESCE(invitation_sent, 0) = 0 THEN NOW() ELSE NULL END
                WHERE id = ? AND event_id = ?
            ");
            $stmt->execute([$id, $eventId]);

            redirect(BASE_PATH . '/admin/guests.php?filter=' . ($_GET['filter'] ?? 'all'));
        }
    }
}

// Get filter
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');

// Build query
$whereClause = "event_id = ?";
$params = [$eventId];

if ($filter === 'yes') {
    $whereClause .= " AND rsvp_status = 'yes'";
} elseif ($filter === 'no') {
    $whereClause .= " AND rsvp_status = 'no'";
} elseif ($filter === 'pending') {
    $whereClause .= " AND rsvp_status = 'pending'";
} elseif ($filter === 'not_invited') {
    $whereClause .= " AND (invitation_sent = 0 OR invitation_sent IS NULL)";
} elseif ($filter === 'invited') {
    $whereClause .= " AND invitation_sent = 1";
}

if ($search) {
    $whereClause .= " AND (name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$stmt = $db->prepare("SELECT * FROM guests WHERE $whereClause ORDER BY name ASC");
$stmt->execute($params);
$guests = $stmt->fetchAll();

// Count by status
$stmt = $db->prepare("
    SELECT
        rsvp_status,
        COUNT(*) as count
    FROM guests
    WHERE event_id = ?
    GROUP BY rsvp_status
");
$stmt->execute([$eventId]);
$statusCounts = [];
while ($row = $stmt->fetch()) {
    $statusCounts[$row['rsvp_status']] = $row['count'];
}
$totalGuests = array_sum($statusCounts);

// Count invitations
$stmt = $db->prepare("
    SELECT
        SUM(CASE WHEN invitation_sent = 1 THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN invitation_sent = 0 OR invitation_sent IS NULL THEN 1 ELSE 0 END) as not_sent
    FROM guests
    WHERE event_id = ?
");
$stmt->execute([$eventId]);
$invitationCounts = $stmt->fetch();
$invitationsSent = (int)($invitationCounts['sent'] ?? 0);
$invitationsNotSent = (int)($invitationCounts['not_sent'] ?? 0);

// Check if we should open modals
$showAddModal = isset($_GET['action']) && $_GET['action'] === 'add';
$showBulkModal = isset($_GET['action']) && $_GET['action'] === 'bulk';

require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-header__title">Gæsteliste</h1>
        <p class="page-header__subtitle">
            <?= $guestStats['confirmed'] ?> bekræftet af <?= $totalGuests ?> gæster
            &middot;
            <span class="<?= $invitationsNotSent > 0 ? 'text-warning' : 'text-success' ?>">
                <?= $invitationsSent ?>/<?= $totalGuests ?> invitationer sendt
            </span>
        </p>
    </div>
    <div class="page-header__actions">
        <button onclick="openModal('bulk-modal')" class="btn btn--secondary">+ Tilføj mange</button>
        <button onclick="openModal('add-modal')" class="btn btn--primary">+ Tilføj gæst</button>
    </div>
</div>

<!-- Invitation Progress -->
<?php if ($totalGuests > 0): ?>
<div class="card mb-md">
    <div class="flex flex-between items-center mb-sm">
        <span class="small"><strong>Invitationer sendt</strong></span>
        <span class="small" style="font-weight: 600;">
            <?= $invitationsSent ?> af <?= $totalGuests ?>
            (<?= $totalGuests > 0 ? round($invitationsSent / $totalGuests * 100) : 0 ?>%)
        </span>
    </div>
    <div class="progress" style="height: 8px;">
        <div class="progress__bar progress__bar--success" style="width: <?= $totalGuests > 0 ? ($invitationsSent / $totalGuests * 100) : 0 ?>%;"></div>
    </div>
    <?php if ($invitationsNotSent > 0): ?>
        <p class="small text-muted mt-xs">
            <a href="?filter=not_invited" style="color: var(--color-warning);">
                <?= $invitationsNotSent ?> gæst<?= $invitationsNotSent !== 1 ? 'er' : '' ?> mangler invitation
            </a>
        </p>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="card mb-md">
    <div class="flex gap-sm" style="flex-wrap: wrap; align-items: center;">
        <div class="filter-tabs">
            <a href="?filter=all" class="filter-tab <?= $filter === 'all' ? 'filter-tab--active' : '' ?>">
                Alle (<?= $totalGuests ?>)
            </a>
            <a href="?filter=not_invited" class="filter-tab <?= $filter === 'not_invited' ? 'filter-tab--active' : '' ?>">
                Mangler invitation (<?= $invitationsNotSent ?>)
            </a>
            <a href="?filter=pending" class="filter-tab <?= $filter === 'pending' ? 'filter-tab--active' : '' ?>">
                Afventer svar (<?= $statusCounts['pending'] ?? 0 ?>)
            </a>
            <a href="?filter=yes" class="filter-tab <?= $filter === 'yes' ? 'filter-tab--active' : '' ?>">
                Kommer (<?= $statusCounts['yes'] ?? 0 ?>)
            </a>
            <a href="?filter=no" class="filter-tab <?= $filter === 'no' ? 'filter-tab--active' : '' ?>">
                Afbud (<?= $statusCounts['no'] ?? 0 ?>)
            </a>
        </div>

        <form method="GET" class="inline-form" style="margin-left: auto;">
            <input type="hidden" name="filter" value="<?= escape($filter) ?>">
            <input type="text"
                   name="search"
                   class="form-input"
                   placeholder="Søg efter navn..."
                   value="<?= escape($search) ?>"
                   style="width: 200px;">
            <button type="submit" class="btn btn--secondary">Søg</button>
            <?php if ($search): ?>
                <a href="?filter=<?= escape($filter) ?>" class="btn btn--ghost">Ryd</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Guest Table -->
<div class="card">
    <?php if (empty($guests)): ?>
        <div class="empty-state">
            <div class="empty-state__icon">👥</div>
            <h3 class="empty-state__title">
                <?php if ($search): ?>
                    Ingen resultater for "<?= escape($search) ?>"
                <?php elseif ($filter === 'not_invited'): ?>
                    Alle gæster har fået invitation!
                <?php elseif ($filter !== 'all'): ?>
                    Ingen gæster med denne status
                <?php else: ?>
                    Ingen gæster endnu
                <?php endif; ?>
            </h3>
            <p class="empty-state__text">
                <?php if (!$search && $filter === 'all'): ?>
                    Tilføj gæster enkeltvis eller mange på én gang
                <?php endif; ?>
            </p>
            <?php if (!$search && $filter === 'all'): ?>
                <div class="flex gap-sm justify-center mt-md">
                    <button onclick="openModal('bulk-modal')" class="btn btn--secondary">
                        + Tilføj mange gæster
                    </button>
                    <button onclick="openModal('add-modal')" class="btn btn--primary">
                        + Tilføj gæst
                    </button>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Invitation</th>
                        <th>Navn</th>
                        <th>Kode</th>
                        <th>RSVP</th>
                        <th>Antal</th>
                        <th style="width: 100px;">Handlinger</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($guests as $guest): ?>
                        <tr>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_invitation">
                                    <input type="hidden" name="id" value="<?= $guest['id'] ?>">
                                    <button type="submit"
                                            class="btn btn--ghost"
                                            title="<?= $guest['invitation_sent'] ? 'Markér som ikke sendt' : 'Markér som sendt' ?>"
                                            style="font-size: 1.2em; padding: 4px 8px;">
                                        <?php if ($guest['invitation_sent']): ?>
                                            <span style="color: var(--color-success);">✓</span>
                                        <?php else: ?>
                                            <span style="color: var(--color-text-muted);">○</span>
                                        <?php endif; ?>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <strong><?= escape($guest['name']) ?></strong>
                                <?php if ($guest['email']): ?>
                                    <br>
                                    <span class="small text-muted"><?= escape($guest['email']) ?></span>
                                <?php endif; ?>
                                <?php if ($guest['phone']): ?>
                                    <br>
                                    <span class="small text-muted"><?= escape($guest['phone']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <code style="background: var(--color-bg-subtle); padding: 4px 8px; border-radius: 4px; font-family: 'Cormorant Garamond', serif; font-size: 1.1em; letter-spacing: 0.1em;">
                                    <?= escape($guest['unique_code']) ?>
                                </code>
                                <button onclick="copyCode('<?= escape($guest['unique_code']) ?>')"
                                        class="btn btn--ghost small"
                                        title="Kopiér kode"
                                        style="padding: 2px 6px; margin-left: 4px;">
                                    📋
                                </button>
                            </td>
                            <td>
                                <?php if ($guest['rsvp_status'] === 'yes'): ?>
                                    <span class="badge badge--success">Kommer</span>
                                <?php elseif ($guest['rsvp_status'] === 'no'): ?>
                                    <span class="badge badge--error">Afbud</span>
                                <?php else: ?>
                                    <span class="badge badge--neutral">Afventer</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($guest['rsvp_status'] === 'yes'): ?>
                                    <?= $guest['adults_count'] ?>v
                                    <?php if ($guest['children_count'] > 0): ?>
                                        + <?= $guest['children_count'] ?>b
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-row">
                                    <button onclick='editGuest(<?= json_encode($guest) ?>)'
                                            class="btn btn--ghost"
                                            title="Rediger">
                                        ✏️
                                    </button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $guest['id'] ?>">
                                        <button type="submit"
                                                class="btn btn--ghost"
                                                title="Slet"
                                                data-confirm="Er du sikker på du vil slette <?= escape($guest['name']) ?>?">
                                            🗑️
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Summary -->
        <div class="dashboard-card__footer mt-md">
            <p class="small text-muted">
                Viser <?= count($guests) ?> gæst<?= count($guests) !== 1 ? 'er' : '' ?>
                <?php if ($guestStats['confirmed'] > 0): ?>
                    &middot; <strong><?= $guestStats['total_adults'] ?></strong> voksne og
                    <strong><?= $guestStats['total_children'] ?></strong> børn bekræftet
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>
</div>

<!-- Bulk Add Modal -->
<div id="bulk-modal" class="modal-overlay <?= $showBulkModal ? 'modal-overlay--active' : '' ?>">
    <div class="modal" style="max-width: 500px;">
        <div class="modal__header">
            <h2 class="modal__title">Tilføj mange gæster</h2>
            <button class="modal__close" onclick="closeModal('bulk-modal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="bulk_add">
            <div class="modal__body">
                <div class="form-group">
                    <label class="form-label">Navne (ét navn per linje)</label>
                    <textarea name="names"
                              class="form-input"
                              rows="12"
                              required
                              placeholder="Mormor og Morfar
Onkel Peter
Tante Lisa og Onkel Hans
Fætter Magnus
Kusine Emma
..."
                              style="font-family: inherit;"></textarea>
                    <p class="small text-muted mt-xs">
                        Skriv ét navn eller én familie per linje.
                        Hver linje får sin egen unikke kode.
                    </p>
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--secondary" onclick="closeModal('bulk-modal')">Annuller</button>
                <button type="submit" class="btn btn--primary">Tilføj alle</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Guest Modal -->
<div id="add-modal" class="modal-overlay <?= $showAddModal ? 'modal-overlay--active' : '' ?>">
    <div class="modal">
        <div class="modal__header">
            <h2 class="modal__title">Tilføj gæst</h2>
            <button class="modal__close" onclick="closeModal('add-modal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal__body">
                <div class="form-group">
                    <label class="form-label">Navn *</label>
                    <input type="text" name="name" class="form-input" required placeholder="F.eks. Mormor og Morfar">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input" placeholder="email@example.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Telefon</label>
                    <input type="tel" name="phone" class="form-input" placeholder="12345678">
                </div>
                <div class="form-group">
                    <label class="form-label">Noter (kun for dig)</label>
                    <textarea name="notes" class="form-input" rows="2" placeholder="Interne noter..."></textarea>
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--secondary" onclick="closeModal('add-modal')">Annuller</button>
                <button type="submit" class="btn btn--primary">Tilføj gæst</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Guest Modal -->
<div id="edit-modal" class="modal-overlay">
    <div class="modal">
        <div class="modal__header">
            <h2 class="modal__title">Rediger gæst</h2>
            <button class="modal__close" onclick="closeModal('edit-modal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit-id">
            <div class="modal__body">
                <div class="form-group">
                    <label class="form-label">Navn *</label>
                    <input type="text" name="name" id="edit-name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="edit-email" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Telefon</label>
                    <input type="tel" name="phone" id="edit-phone" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Noter (kun for dig)</label>
                    <textarea name="notes" id="edit-notes" class="form-input" rows="2"></textarea>
                </div>

                <!-- Read-only info -->
                <div class="mt-md" style="padding-top: var(--space-sm); border-top: 1px solid var(--color-border-soft);">
                    <p class="small text-muted mb-xs">
                        <strong>Kode:</strong> <span id="edit-code"></span>
                    </p>
                    <p class="small text-muted mb-xs">
                        <strong>Status:</strong> <span id="edit-status"></span>
                    </p>
                    <p class="small text-muted" id="edit-dietary" style="display: none;">
                        <strong>Kostbehov:</strong> <span id="edit-dietary-text"></span>
                    </p>
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--secondary" onclick="closeModal('edit-modal')">Annuller</button>
                <button type="submit" class="btn btn--primary">Gem ændringer</button>
            </div>
        </form>
    </div>
</div>

</main>
</div>

<div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleSidebar()"></div>

<script src="<?= BASE_PATH ?>/assets/js/main.js"></script>
<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    sidebar.classList.toggle('sidebar--open');
    overlay.classList.toggle('sidebar-overlay--active');
}

function editGuest(guest) {
    document.getElementById('edit-id').value = guest.id;
    document.getElementById('edit-name').value = guest.name;
    document.getElementById('edit-email').value = guest.email || '';
    document.getElementById('edit-phone').value = guest.phone || '';
    document.getElementById('edit-notes').value = guest.notes || '';
    document.getElementById('edit-code').textContent = guest.unique_code;

    const statusMap = { 'yes': 'Kommer', 'no': 'Afbud', 'pending': 'Afventer svar' };
    document.getElementById('edit-status').textContent = statusMap[guest.rsvp_status] || 'Ukendt';

    if (guest.dietary_notes) {
        document.getElementById('edit-dietary').style.display = 'block';
        document.getElementById('edit-dietary-text').textContent = guest.dietary_notes;
    } else {
        document.getElementById('edit-dietary').style.display = 'none';
    }

    openModal('edit-modal');
}

function copyCode(code) {
    navigator.clipboard.writeText(code).then(() => {
        // Show brief feedback
        const btn = event.target;
        const originalText = btn.textContent;
        btn.textContent = '✓';
        setTimeout(() => btn.textContent = originalText, 1000);
    });
}

// Confirm delete
document.querySelectorAll('[data-confirm]').forEach(btn => {
    btn.addEventListener('click', function(e) {
        if (!confirm(this.dataset.confirm)) {
            e.preventDefault();
        }
    });
});
</script>
</body>
</html>

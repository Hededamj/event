<?php
/**
 * Guests Management Page
 * Included by manage.php
 * Full-featured guest management with bulk add, CSV import, and export
 */

// Check if viewing export
$showExport = isset($_GET['view']) && $_GET['view'] === 'export';

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
        $maxGuests = max(1, (int)($_POST['max_guests'] ?? 1));
        $dietaryNotes = trim($_POST['dietary_notes'] ?? '');

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
                INSERT INTO guests (event_id, name, email, phone, notes, unique_code, rsvp_status, max_guests, dietary_notes)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?)
            ");
            $stmt->execute([$eventId, $name, $email ?: null, $phone ?: null, $notes ?: null, $code, $maxGuests, $dietaryNotes ?: null]);

            setFlash('success', "Gæst tilføjet! Kode: $code");
        }
        redirect("?id=$eventId&page=guests");

    } elseif ($action === 'bulk_add') {
        $names = trim($_POST['names'] ?? '');
        $defaultMaxGuests = max(1, (int)($_POST['default_max_guests'] ?? 1));
        $lines = array_filter(array_map('trim', explode("\n", $names)));
        $added = 0;

        foreach ($lines as $line) {
            if (empty($line)) continue;

            // Parse "Name (4)" format to get max guests, otherwise use default
            $maxGuests = $defaultMaxGuests;
            $name = $line;
            if (preg_match('/^(.+?)\s*\((\d+)\)\s*$/', $line, $matches)) {
                $name = trim($matches[1]);
                $maxGuests = max(1, (int)$matches[2]);
            }

            // Generate unique code
            $code = generateGuestCode();
            $stmt = $db->prepare("SELECT id FROM guests WHERE unique_code = ? AND event_id = ?");
            $stmt->execute([$code, $eventId]);

            while ($stmt->fetch()) {
                $code = generateGuestCode();
                $stmt->execute([$code, $eventId]);
            }

            $stmt = $db->prepare("
                INSERT INTO guests (event_id, name, max_guests, unique_code, rsvp_status)
                VALUES (?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$eventId, $name, $maxGuests, $code]);
            $added++;
        }

        setFlash('success', "$added gæster tilføjet!");
        redirect("?id=$eventId&page=guests");

    } elseif ($action === 'csv_import') {
        // CSV Import with column mapping
        $csvData = $_POST['csv_data'] ?? '';
        $mapping = $_POST['mapping'] ?? [];
        $skipDuplicates = isset($_POST['skip_duplicates']);

        if (empty($csvData) || empty($mapping)) {
            setFlash('error', 'Ingen data at importere');
            redirect("?id=$eventId&page=guests");
        }

        // Decode the JSON data
        $rows = json_decode($csvData, true);
        if (!$rows || !is_array($rows)) {
            setFlash('error', 'Ugyldig CSV data');
            redirect("?id=$eventId&page=guests");
        }

        // Get existing emails and names for duplicate check
        $existingEmails = [];
        $existingNames = [];
        $stmt = $db->prepare("SELECT LOWER(email) as email, LOWER(name) as name FROM guests WHERE event_id = ?");
        $stmt->execute([$eventId]);
        while ($row = $stmt->fetch()) {
            if ($row['email']) $existingEmails[$row['email']] = true;
            if ($row['name']) $existingNames[$row['name']] = true;
        }

        $added = 0;
        $skipped = 0;
        $errors = [];

        $db->beginTransaction();
        try {
        foreach ($rows as $index => $row) {
            // Map columns to fields
            $name = '';
            $email = '';
            $phone = '';
            $maxGuests = 1;
            $notes = '';

            foreach ($mapping as $csvCol => $field) {
                $value = trim($row[$csvCol] ?? '');
                switch ($field) {
                    case 'name': $name = $value; break;
                    case 'email': $email = $value; break;
                    case 'phone': $phone = $value; break;
                    case 'max_guests': $maxGuests = max(1, (int)$value ?: 1); break;
                    case 'notes': $notes = $value; break;
                }
            }

            // Skip empty names
            if (empty($name)) {
                $errors[] = "Række " . ($index + 2) . ": Mangler navn";
                continue;
            }

            // Check for duplicates
            if ($skipDuplicates) {
                $isDuplicate = false;
                if ($email && isset($existingEmails[strtolower($email)])) {
                    $isDuplicate = true;
                }
                if (isset($existingNames[strtolower($name)])) {
                    $isDuplicate = true;
                }
                if ($isDuplicate) {
                    $skipped++;
                    continue;
                }
            }

            // Validate email format if provided
            if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Række " . ($index + 2) . ": Ugyldig email '$email'";
                $email = ''; // Clear invalid email but still import
            }

            // Generate unique code
            $code = generateGuestCode();
            $stmt = $db->prepare("SELECT id FROM guests WHERE unique_code = ? AND event_id = ?");
            $stmt->execute([$code, $eventId]);
            while ($stmt->fetch()) {
                $code = generateGuestCode();
                $stmt->execute([$code, $eventId]);
            }

            // Insert guest
            $stmt = $db->prepare("
                INSERT INTO guests (event_id, name, max_guests, email, phone, notes, unique_code, rsvp_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([
                $eventId,
                $name,
                $maxGuests,
                $email ?: null,
                $phone ?: null,
                $notes ?: null,
                $code
            ]);

            // Track for duplicate check
            if ($email) $existingEmails[strtolower($email)] = true;
            $existingNames[strtolower($name)] = true;

            $added++;
        }
        $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            error_log('CSV guest import failed: ' . $e->getMessage());
            setFlash('error', 'Import fejlede. Ingen gæster blev tilføjet.');
            redirect("?id=$eventId&page=guests");
        }

        // Build result message
        $message = "$added gæster importeret!";
        if ($skipped > 0) {
            $message .= " ($skipped duplikater sprunget over)";
        }
        if (count($errors) > 0 && count($errors) <= 5) {
            $message .= " Advarsler: " . implode('; ', $errors);
        } elseif (count($errors) > 5) {
            $message .= " " . count($errors) . " rækker havde problemer.";
        }

        setFlash($added > 0 ? 'success' : 'warning', $message);
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
        $maxGuests = max(1, (int)($_POST['max_guests'] ?? 1));
        $rsvpStatus = $_POST['rsvp_status'] ?? null;
        $adultsCount = (int)($_POST['adults_count'] ?? 1);
        $childrenCount = (int)($_POST['children_count'] ?? 0);
        $dietaryNotes = trim($_POST['dietary_notes'] ?? '');

        // Collect guest names for seating
        $guestNames = [];
        if ($rsvpStatus === 'yes' && isset($_POST['guest_names']) && is_array($_POST['guest_names'])) {
            foreach ($_POST['guest_names'] as $guestName) {
                $guestName = trim($guestName);
                if (!empty($guestName)) {
                    $guestNames[] = $guestName;
                }
            }
        }
        $guestNamesJson = !empty($guestNames) ? json_encode($guestNames, JSON_UNESCAPED_UNICODE) : null;

        if ($guestId && $name) {
            $sql = "UPDATE guests SET name = ?, max_guests = ?, email = ?, phone = ?, notes = ?";
            $params = [$name, $maxGuests, $email ?: null, $phone ?: null, $notes ?: null];

            // Only update RSVP if provided
            if ($rsvpStatus && in_array($rsvpStatus, ['pending', 'yes', 'no'])) {
                $sql .= ", rsvp_status = ?, adults_count = ?, children_count = ?, guest_names = ?, dietary_notes = ?";
                $params[] = $rsvpStatus;
                $params[] = $rsvpStatus === 'yes' ? $adultsCount : 0;
                $params[] = $rsvpStatus === 'yes' ? $childrenCount : 0;
                $params[] = $rsvpStatus === 'yes' ? $guestNamesJson : null;
                $params[] = $rsvpStatus === 'yes' ? ($dietaryNotes ?: null) : null;

                // Set rsvp_date if changing to accepted/declined
                if ($rsvpStatus !== 'pending') {
                    $sql .= ", rsvp_date = NOW()";
                }
            }

            $sql .= " WHERE id = ? AND event_id = ?";
            $params[] = $guestId;
            $params[] = $eventId;

            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            setFlash('success', 'Gæst opdateret.');
        }
        redirect("?id=$eventId&page=guests");

    } elseif ($action === 'toggle_invitation') {
        $guestId = (int)($_POST['guest_id'] ?? 0);
        if ($guestId) {
            $stmt = $db->prepare("
                UPDATE guests SET invitation_sent = NOT COALESCE(invitation_sent, 0), invitation_sent_at = IF(COALESCE(invitation_sent, 0) = 0, NOW(), NULL)
                WHERE id = ? AND event_id = ?
            ");
            $stmt->execute([$guestId, $eventId]);
        }

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            // Get new state
            $stmt = $db->prepare("SELECT invitation_sent FROM guests WHERE id = ? AND event_id = ?");
            $stmt->execute([$guestId, $eventId]);
            $newState = $stmt->fetchColumn();
            jsonResponse(['success' => true, 'invitation_sent' => (bool)$newState]);
        }
        redirect("?id=$eventId&page=guests");
    }
}

// Get guests with filters
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

$whereClause = "WHERE event_id = ?";
$params = [$eventId];

if ($filter === 'yes') {
    $whereClause .= " AND rsvp_status = 'yes'";
} elseif ($filter === 'no') {
    $whereClause .= " AND rsvp_status = 'no'";
} elseif ($filter === 'pending') {
    $whereClause .= " AND rsvp_status = 'pending'";
} elseif ($filter === 'not_sent') {
    $whereClause .= " AND (invitation_sent = 0 OR invitation_sent IS NULL)";
} elseif ($filter === 'sent') {
    $whereClause .= " AND invitation_sent = 1";
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

// Get base URL for links
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$eventBaseUrl = $baseUrl . '/e/' . ($event['slug'] ?? $eventId);

// ==========================================
// EXPORT VIEW
// ==========================================
if ($showExport):
?>
<div class="page-header-actions">
    <div>
        <h2 class="section-title">Eksportér invitationslinks</h2>
        <p class="section-subtitle"><?= count($guests) ?> gæster</p>
    </div>
    <a href="?id=<?= $eventId ?>&page=guests" class="btn btn-secondary">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
        </svg>
        Tilbage til gæsteliste
    </a>
</div>

<div class="card">
    <div class="export-tabs">
        <button class="export-tab active" onclick="showExportView('list')">Liste</button>
        <button class="export-tab" onclick="showExportView('text')">Tekst (til kopiering)</button>
        <button class="export-tab" onclick="showExportView('csv')">CSV</button>
    </div>

    <!-- List View -->
    <div id="export-view-list" class="export-view active">
        <?php foreach ($guests as $guest): ?>
        <div class="export-row">
            <div class="export-name"><?= htmlspecialchars($guest['name']) ?></div>
            <div class="export-link"><?= $eventBaseUrl ?>?kode=<?= htmlspecialchars($guest['unique_code']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Text View -->
    <div id="export-view-text" class="export-view">
        <textarea id="text-export" readonly style="width: 100%; height: 400px; font-family: monospace; font-size: 13px; padding: 16px; border: 1px solid var(--border); border-radius: 8px;"><?php foreach ($guests as $guest): ?>
<?= htmlspecialchars($guest['name']) ?>
<?= htmlspecialchars($eventBaseUrl) ?>?kode=<?= htmlspecialchars($guest['unique_code']) ?>

<?php endforeach; ?></textarea>
        <div style="margin-top: 12px;">
            <button type="button" class="btn btn-primary" onclick="copyExportText()">Kopiér alt</button>
        </div>
    </div>

    <!-- CSV View -->
    <div id="export-view-csv" class="export-view">
        <textarea id="csv-export" readonly style="width: 100%; height: 400px; font-family: monospace; font-size: 13px; padding: 16px; border: 1px solid var(--border); border-radius: 8px;">Navn;Kode;Link
<?php foreach ($guests as $guest): ?>
<?= $guest['name'] ?>;<?= $guest['unique_code'] ?>;<?= $eventBaseUrl ?>?kode=<?= $guest['unique_code'] ?>
<?php endforeach; ?></textarea>
        <div style="margin-top: 12px;">
            <button type="button" class="btn btn-primary" onclick="copyExportCSV()">Kopiér CSV</button>
            <span style="margin-left: 12px; color: var(--text-secondary); font-size: 13px;">Kan indsættes direkte i Excel</span>
        </div>
    </div>
</div>

<style>
    .export-tabs {
        display: flex;
        gap: 8px;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 1px solid var(--border);
    }
    .export-tab {
        padding: 8px 16px;
        border: 1px solid var(--border);
        background: white;
        border-radius: 8px;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .export-tab:hover {
        background: var(--surface);
    }
    .export-tab.active {
        background: var(--accent);
        color: white;
        border-color: var(--accent);
    }
    .export-view {
        display: none;
    }
    .export-view.active {
        display: block;
    }
    .export-row {
        padding: 12px 0;
        border-bottom: 1px solid var(--border-light);
    }
    .export-row:last-child {
        border-bottom: none;
    }
    .export-name {
        font-weight: 500;
        margin-bottom: 4px;
    }
    .export-link {
        font-family: monospace;
        font-size: 13px;
        color: var(--text-secondary);
        word-break: break-all;
    }
</style>

<script>
function showExportView(view) {
    document.querySelectorAll('.export-view').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.export-tab').forEach(el => el.classList.remove('active'));
    document.getElementById('export-view-' + view).classList.add('active');
    event.target.classList.add('active');
}

function copyExportText() {
    const text = document.getElementById('text-export').value;
    navigator.clipboard.writeText(text).then(() => {
        alert('Kopieret til udklipsholder!');
    });
}

function copyExportCSV() {
    const text = document.getElementById('csv-export').value;
    navigator.clipboard.writeText(text).then(() => {
        alert('CSV kopieret til udklipsholder!');
    });
}
</script>

<?php
return; // Exit early for export view
endif;

// ==========================================
// MAIN GUEST LIST VIEW
// ==========================================
?>

<div class="page-header-actions">
    <div>
        <h2 class="section-title">Gæsteliste</h2>
        <p class="section-subtitle">
            <?= count($guests) ?> gæster
            <?php if ($guestStats['total_guests'] > 0): ?>
                &middot; <?= $invitationsSent ?>/<?= $guestStats['total_guests'] ?> invitationer sendt
            <?php endif; ?>
        </p>
    </div>
    <div class="header-buttons">
        <a href="?id=<?= $eventId ?>&page=guests&view=export" class="btn btn-secondary">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
            </svg>
            Eksportér links
        </a>
        <button type="button" class="btn btn-secondary" onclick="showImportModal()">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
            </svg>
            Importér CSV
        </button>
        <button type="button" class="btn btn-secondary" onclick="showBulkModal()">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
            Tilføj mange
        </button>
        <button type="button" class="btn btn-primary" onclick="showAddModal()">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Tilføj gæst
        </button>
    </div>
</div>

<!-- Invitation Progress -->
<?php if ($guestStats['total_guests'] > 0): ?>
<div class="card" style="margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
        <span style="font-size: 14px; font-weight: 500;">Invitationer sendt</span>
        <span style="font-size: 14px; font-weight: 600;">
            <?= $invitationsSent ?> af <?= $guestStats['total_guests'] ?>
            (<?= $guestStats['total_guests'] > 0 ? round($invitationsSent / $guestStats['total_guests'] * 100) : 0 ?>%)
        </span>
    </div>
    <div style="height: 8px; background: var(--border); border-radius: 4px; overflow: hidden;">
        <div style="height: 100%; background: #10b981; width: <?= $guestStats['total_guests'] > 0 ? ($invitationsSent / $guestStats['total_guests'] * 100) : 0 ?>%; transition: width 0.3s;"></div>
    </div>
    <?php if ($invitationsNotSent > 0): ?>
    <p style="font-size: 13px; color: #f59e0b; margin-top: 8px;">
        <a href="?id=<?= $eventId ?>&page=guests&filter=not_sent" style="color: inherit;">
            <?= $invitationsNotSent ?> gæst<?= $invitationsNotSent !== 1 ? 'er' : '' ?> mangler invitation
        </a>
    </p>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="filters-bar">
    <div class="filter-tabs">
        <a href="?id=<?= $eventId ?>&page=guests&filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">
            Alle (<?= $guestStats['total_guests'] ?>)
        </a>
        <a href="?id=<?= $eventId ?>&page=guests&filter=not_sent" class="filter-tab <?= $filter === 'not_sent' ? 'active' : '' ?>">
            Mangler invitation (<?= $invitationsNotSent ?>)
        </a>
        <a href="?id=<?= $eventId ?>&page=guests&filter=pending" class="filter-tab <?= $filter === 'pending' ? 'active' : '' ?>">
            Afventer (<?= $guestStats['pending'] ?>)
        </a>
        <a href="?id=<?= $eventId ?>&page=guests&filter=yes" class="filter-tab <?= $filter === 'yes' ? 'active' : '' ?>">
            Kommer (<?= $guestStats['accepted'] ?>)
        </a>
        <a href="?id=<?= $eventId ?>&page=guests&filter=no" class="filter-tab <?= $filter === 'no' ? 'active' : '' ?>">
            Afbud (<?= $guestStats['declined'] ?>)
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
        <div style="display: flex; gap: 12px; margin-top: 16px;">
            <button type="button" class="btn btn-secondary" onclick="showBulkModal()">Tilføj mange gæster</button>
            <button type="button" class="btn btn-primary" onclick="showAddModal()">Tilføj gæst</button>
        </div>
    </div>
</div>
<?php else: ?>
<div class="card" style="padding: 0; overflow: hidden;">
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 50px;">Inv.</th>
                <th>Navn</th>
                <th>Max</th>
                <th>Kode</th>
                <th>Status</th>
                <th>Antal</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($guests as $guest): ?>
            <tr>
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
                    <div class="guest-name">
                        <strong><?= htmlspecialchars($guest['name']) ?></strong>
                        <?php if ($guest['email']): ?>
                            <span class="guest-email"><?= htmlspecialchars($guest['email']) ?></span>
                        <?php endif; ?>
                        <?php if ($guest['phone']): ?>
                            <span class="guest-phone"><?= htmlspecialchars($guest['phone']) ?></span>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <span class="max-badge"><?= (int)($guest['max_guests'] ?? 1) ?></span>
                </td>
                <td>
                    <div style="display: flex; align-items: center; gap: 4px;">
                        <code class="guest-code"><?= htmlspecialchars($guest['unique_code']) ?></code>
                        <button type="button" class="copy-btn" onclick="copyLink('<?= htmlspecialchars($guest['unique_code']) ?>')" title="Kopiér link">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 14px; height: 14px;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path>
                            </svg>
                        </button>
                    </div>
                </td>
                <td>
                    <span class="status-badge status-<?= $guest['rsvp_status'] ?>">
                        <?php
                        $statusLabels = ['pending' => 'Afventer', 'yes' => 'Kommer', 'no' => 'Kommer ikke'];
                        echo $statusLabels[$guest['rsvp_status']] ?? $guest['rsvp_status'];
                        ?>
                    </span>
                </td>
                <td>
                    <?php if ($guest['rsvp_status'] === 'yes'): ?>
                        <?= (int)$guest['adults_count'] ?>v + <?= (int)$guest['children_count'] ?>b
                        <?php if (!empty($guest['guest_names'])): ?>
                            <?php
                            $namesData = json_decode($guest['guest_names'], true);
                            $namesList = [];
                            if (is_array($namesData)) {
                                foreach ($namesData as $n) {
                                    if (is_array($n) && isset($n['name'])) {
                                        $namesList[] = $n['name'] . (!empty($n['age']) ? " ({$n['age']})" : '');
                                    } elseif (is_string($n)) {
                                        $namesList[] = $n;
                                    }
                                }
                            }
                            ?>
                            <?php if (count($namesList) > 0): ?>
                                <br><span style="font-size: 12px; color: var(--text-secondary);" title="<?= htmlspecialchars(implode(', ', $namesList)) ?>">
                                    <?= htmlspecialchars(implode(', ', array_slice($namesList, 0, 3))) ?><?= count($namesList) > 3 ? '...' : '' ?>
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
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

<!-- Summary -->
<div style="margin-top: 16px; padding: 12px 16px; background: var(--surface); border-radius: 8px;">
    <p style="font-size: 13px; color: var(--text-secondary); margin: 0;">
        Viser <?= count($guests) ?> gæst<?= count($guests) !== 1 ? 'er' : '' ?>
        <?php if ($guestStats['accepted'] > 0): ?>
            &middot; <strong><?= $guestStats['total_adults'] ?? 0 ?></strong> voksne og
            <strong><?= $guestStats['total_children'] ?? 0 ?></strong> børn bekræftet
        <?php endif; ?>
    </p>
</div>
<?php endif; ?>

<!-- Add Guest Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Tilføj gæst</h3>
            <button type="button" class="modal-close" onclick="hideAddModal()">&times;</button>
        </div>
        <form method="POST">
            <?= accountCsrfField() ?>
            <input type="hidden" name="action" value="add_guest">
            <div class="modal-body">
                <div style="display: grid; grid-template-columns: 1fr 100px; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Navn *</label>
                        <input type="text" name="guest_name" class="form-input" required placeholder="F.eks. Mormor og Morfar">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Max gæster</label>
                        <input type="number" name="max_guests" class="form-input" value="1" min="1" max="20">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="guest_email" class="form-input" placeholder="email@example.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Telefon</label>
                    <input type="tel" name="guest_phone" class="form-input" placeholder="12345678">
                </div>
                <div class="form-group">
                    <label class="form-label">Kostbehov/allergier</label>
                    <input type="text" name="dietary_notes" class="form-input" placeholder="F.eks. vegetar, glutenfri...">
                </div>
                <div class="form-group">
                    <label class="form-label">Noter (kun for dig)</label>
                    <textarea name="guest_notes" class="form-input" rows="2" placeholder="Interne noter..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideAddModal()">Annuller</button>
                <button type="submit" class="btn btn-primary">Tilføj</button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Add Modal -->
<div class="modal-overlay" id="bulkModal">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Tilføj mange gæster</h3>
            <button type="button" class="modal-close" onclick="hideBulkModal()">&times;</button>
        </div>
        <form method="POST">
            <?= accountCsrfField() ?>
            <input type="hidden" name="action" value="bulk_add">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Standard max gæster</label>
                    <input type="number" name="default_max_guests" class="form-input" value="1" min="1" max="20" style="width: 100px;">
                    <p style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">Bruges hvis ikke angivet per linje</p>
                </div>
                <div class="form-group">
                    <label class="form-label">Navne (ét navn per linje)</label>
                    <textarea name="names" class="form-input" rows="12" required placeholder="Mormor og Morfar (2)
Onkel Peter (4)
Tante Lisa
Fætter Magnus (1)
..."></textarea>
                    <p style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">
                        Skriv ét navn per linje. Tilføj <strong>(antal)</strong> for at angive max gæster.<br>
                        F.eks. "Peter og familie (4)" = max 4 gæster.
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideBulkModal()">Annuller</button>
                <button type="submit" class="btn btn-primary">Tilføj alle</button>
            </div>
        </form>
    </div>
</div>

<!-- CSV Import Modal -->
<div class="modal-overlay" id="importModal">
    <div class="modal" style="max-width: 700px;">
        <div class="modal-header">
            <h3>Importér gæster fra CSV</h3>
            <button type="button" class="modal-close" onclick="hideImportModal()">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Step 1: File Upload -->
            <div id="import-step-1">
                <div class="form-group">
                    <label class="form-label">Vælg CSV-fil</label>
                    <input type="file" id="csv-file-input" accept=".csv,.txt" class="form-input" onchange="handleFileSelect(event)">
                    <p style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">
                        Understøtter CSV-filer med semikolon (;) eller komma (,) som separator.<br>
                        Første række skal indeholde kolonnenavne.
                    </p>
                </div>

                <div class="form-group" style="margin-top: 24px;">
                    <label class="form-label">Eller indsæt fra Excel</label>
                    <textarea id="csv-paste-area" class="form-input" rows="6" placeholder="Kopiér data fra Excel og indsæt her...
Navn;Email;Telefon;Max gæster
Peter Hansen;peter@email.dk;12345678;2
Marie Jensen;marie@email.dk;;1"></textarea>
                    <button type="button" class="btn btn-secondary" style="margin-top: 8px;" onclick="parseTextArea()">Indlæs data</button>
                </div>
            </div>

            <!-- Step 2: Column Mapping -->
            <div id="import-step-2" style="display: none;">
                <div style="background: #eff6ff; border: 1px solid #bfdbfe; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;">
                    <strong>Trin 2:</strong> Vælg hvilke kolonner der skal bruges til hvad
                </div>

                <div id="column-mapping-container"></div>

                <div class="form-group" style="margin-top: 16px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" id="skip-duplicates" checked>
                        <span>Spring duplikater over (samme navn eller email)</span>
                    </label>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 16px;">
                    <button type="button" class="btn btn-secondary" onclick="goToStep(1)">Tilbage</button>
                    <button type="button" class="btn btn-primary" onclick="goToStep(3)">Se preview</button>
                </div>
            </div>

            <!-- Step 3: Preview -->
            <div id="import-step-3" style="display: none;">
                <div style="background: #eff6ff; border: 1px solid #bfdbfe; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;">
                    <strong>Trin 3:</strong> Bekræft import
                </div>

                <div id="preview-summary" style="margin-bottom: 16px;"></div>

                <div style="max-height: 300px; overflow-y: auto; border: 1px solid var(--border); border-radius: 8px;">
                    <table class="data-table" id="preview-table">
                        <thead></thead>
                        <tbody></tbody>
                    </table>
                </div>

                <div id="preview-errors" style="margin-top: 16px; display: none;">
                    <div style="background: #fef3c7; border: 1px solid #fcd34d; padding: 12px 16px; border-radius: 8px;">
                        <strong>Advarsler:</strong>
                        <ul id="error-list" style="margin: 8px 0 0 20px; padding: 0;"></ul>
                    </div>
                </div>

                <form method="POST" id="import-form">
                    <?= accountCsrfField() ?>
                    <input type="hidden" name="action" value="csv_import">
                    <input type="hidden" name="csv_data" id="csv-data-input">

                    <div style="display: flex; gap: 12px; margin-top: 16px;">
                        <button type="button" class="btn btn-secondary" onclick="goToStep(2)">Tilbage</button>
                        <button type="submit" class="btn btn-primary" id="import-submit-btn">Importér gæster</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Guest Modal -->
<div class="modal-overlay" id="editModal">
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
                <div style="display: grid; grid-template-columns: 1fr 100px; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Navn *</label>
                        <input type="text" name="guest_name" id="edit_guest_name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Max gæster</label>
                        <input type="number" name="max_guests" id="edit_max_guests" class="form-input" value="1" min="1" max="20">
                    </div>
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
                    <label class="form-label">Noter (kun for dig)</label>
                    <textarea name="guest_notes" id="edit_guest_notes" class="form-input" rows="2"></textarea>
                </div>

                <!-- RSVP Status -->
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border);">
                    <div class="form-group">
                        <label class="form-label">RSVP Status</label>
                        <select name="rsvp_status" id="edit_rsvp_status" class="form-input" onchange="toggleRsvpDetails()">
                            <option value="pending">Afventer svar</option>
                            <option value="yes">Kommer</option>
                            <option value="no">Kommer ikke</option>
                        </select>
                    </div>

                    <div id="rsvp-details" style="display: none;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="form-group">
                                <label class="form-label">Antal voksne</label>
                                <input type="number" name="adults_count" id="edit_adults" class="form-input" min="1" value="1" onchange="updateEditNameFields()">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Antal børn</label>
                                <input type="number" name="children_count" id="edit_children" class="form-input" min="0" value="0">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Navne på deltagere (til bordplan)</label>
                            <div id="edit-guest-names-inputs"></div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Kostbehov/allergier</label>
                            <textarea name="dietary_notes" id="edit_dietary" class="form-input" rows="2" placeholder="F.eks. vegetar, glutenfri..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Read-only info -->
                <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border);">
                    <p style="font-size: 13px; color: var(--text-secondary); margin: 0;">
                        <strong>Kode:</strong> <span id="edit_code"></span>
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideEditModal()">Annuller</button>
                <button type="submit" class="btn btn-primary">Gem ændringer</button>
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
        flex-wrap: wrap;
        gap: 16px;
    }

    .header-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .section-title {
        font-size: 24px;
        font-weight: 700;
        color: var(--text);
        margin-bottom: 4px;
    }

    .section-subtitle {
        color: var(--text-secondary);
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
        flex-wrap: wrap;
    }

    .filter-tab {
        padding: 8px 16px;
        font-size: 14px;
        color: var(--text-secondary);
        text-decoration: none;
        border-radius: 8px;
        transition: all 0.2s;
    }

    .filter-tab:hover {
        background: var(--border-light);
    }

    .filter-tab.active {
        background: var(--accent);
        color: white;
    }

    .search-form {
        display: flex;
    }

    .search-input {
        padding: 8px 16px;
        border: 1px solid var(--border);
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
        border-bottom: 1px solid var(--border-light);
    }

    .data-table th {
        background: var(--surface);
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-secondary);
    }

    .data-table tbody tr:hover {
        background: var(--surface);
    }

    .guest-name {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .guest-email, .guest-phone {
        font-size: 13px;
        color: var(--text-secondary);
    }

    .guest-code {
        background: var(--border-light);
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 13px;
        font-family: monospace;
    }

    .copy-btn {
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: none;
        border: none;
        cursor: pointer;
        color: var(--text-secondary);
        border-radius: 4px;
    }

    .copy-btn:hover {
        background: var(--border-light);
        color: var(--text-secondary);
    }

    .max-badge {
        display: inline-block;
        padding: 2px 8px;
        background: var(--border-light);
        border-radius: 4px;
        font-size: 13px;
        font-weight: 500;
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
        background: var(--border-light);
        border: none;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .invitation-toggle svg {
        width: 18px;
        height: 18px;
        color: var(--text-secondary);
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
        color: var(--text-secondary);
        transition: all 0.2s;
    }

    .row-action:hover {
        background: var(--border-light);
        color: var(--text);
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
        border-bottom: 1px solid var(--border);
    }

    .modal-header h3 {
        font-size: 18px;
        font-weight: 600;
        margin: 0;
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
        color: var(--text-secondary);
        cursor: pointer;
        border-radius: 6px;
    }

    .modal-close:hover {
        background: var(--border-light);
        color: var(--text);
    }

    .modal-body {
        padding: 24px;
    }

    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        padding: 16px 24px;
        border-top: 1px solid var(--border);
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
        color: var(--text);
        margin-bottom: 6px;
    }

    .form-input {
        width: 100%;
        padding: 10px 14px;
        font-size: 14px;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-family: inherit;
    }

    .form-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
</style>

<script>
// Store current guest data for name field generation
let currentEditGuest = null;

function showAddModal() {
    document.getElementById('addModal').classList.add('active');
}

function hideAddModal() {
    document.getElementById('addModal').classList.remove('active');
}

function showBulkModal() {
    document.getElementById('bulkModal').classList.add('active');
}

function hideBulkModal() {
    document.getElementById('bulkModal').classList.remove('active');
}

function showImportModal() {
    document.getElementById('importModal').classList.add('active');
    goToStep(1);
}

function hideImportModal() {
    document.getElementById('importModal').classList.remove('active');
}

function editGuest(guest) {
    currentEditGuest = guest;

    document.getElementById('edit_guest_id').value = guest.id;
    document.getElementById('edit_guest_name').value = guest.name || '';
    document.getElementById('edit_max_guests').value = guest.max_guests || 1;
    document.getElementById('edit_guest_email').value = guest.email || '';
    document.getElementById('edit_guest_phone').value = guest.phone || '';
    document.getElementById('edit_guest_notes').value = guest.notes || '';
    document.getElementById('edit_code').textContent = guest.unique_code;

    // Set RSVP status
    document.getElementById('edit_rsvp_status').value = guest.rsvp_status || 'pending';
    document.getElementById('edit_adults').value = guest.adults_count || 1;
    document.getElementById('edit_children').value = guest.children_count || 0;
    document.getElementById('edit_dietary').value = guest.dietary_notes || '';
    toggleRsvpDetails();

    // Generate name input fields
    updateEditNameFields();

    document.getElementById('editModal').classList.add('active');
}

function hideEditModal() {
    document.getElementById('editModal').classList.remove('active');
}

function updateEditNameFields() {
    const container = document.getElementById('edit-guest-names-inputs');
    const count = parseInt(document.getElementById('edit_adults').value) || 1;

    // Get existing names
    let existingNames = [];
    if (currentEditGuest && currentEditGuest.guest_names) {
        try {
            existingNames = JSON.parse(currentEditGuest.guest_names) || [];
        } catch (e) {}
    }

    // Also check current input values to preserve user input
    const currentInputs = container.querySelectorAll('input[name="guest_names[]"]');
    currentInputs.forEach((input, i) => {
        if (input.value) {
            existingNames[i] = input.value;
        }
    });

    // Clear container
    container.innerHTML = '';

    // Generate input fields based on adults count
    for (let i = 0; i < count; i++) {
        const div = document.createElement('div');
        div.style.marginBottom = '8px';

        const input = document.createElement('input');
        input.type = 'text';
        input.name = 'guest_names[]';
        input.className = 'form-input';
        input.placeholder = 'Navn på person ' + (i + 1);
        input.value = existingNames[i] || '';

        div.appendChild(input);
        container.appendChild(div);
    }
}

function toggleRsvpDetails() {
    const status = document.getElementById('edit_rsvp_status').value;
    const details = document.getElementById('rsvp-details');
    details.style.display = status === 'yes' ? 'block' : 'none';
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

function copyLink(code) {
    const fullLink = '<?= $eventBaseUrl ?>?kode=' + code;
    navigator.clipboard.writeText(fullLink).then(() => {
        alert('Link kopieret til udklipsholder!');
    });
}

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

// CSV Import Functions loaded from external file
</script>
<script src="/assets/js/csv-import.js"></script>

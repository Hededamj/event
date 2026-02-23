<?php
/**
 * Admin - Guest List Management
 */

// Handle AJAX requests BEFORE including header (which outputs HTML)
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {

    // Suppress PHP error output for AJAX requests
    error_reporting(0);
    ini_set('display_errors', 0);

    header('Content-Type: application/json');

    try {
        // Include only what we need for auth and DB
        require_once __DIR__ . '/../config/database.php';
        require_once __DIR__ . '/../includes/functions.php';
        require_once __DIR__ . '/../includes/auth.php';

        if (!isLoggedIn()) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit;
        }

        // Verify CSRF token for AJAX requests
        $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!verifyCsrfToken($csrfToken)) {
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }

        $db = getDB();
        $eventId = getCurrentEventId();
        $action = $_POST['action'] ?? '';

    if ($action === 'toggle_invitation') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $db->prepare("
                UPDATE guests
                SET invitation_sent = NOT COALESCE(invitation_sent, 0),
                    invitation_sent_at = CASE WHEN COALESCE(invitation_sent, 0) = 0 THEN NOW() ELSE NULL END
                WHERE id = ? AND event_id = ?
            ");
            $stmt->execute([$id, $eventId]);

            // Get new state
            $stmt = $db->prepare("SELECT invitation_sent FROM guests WHERE id = ? AND event_id = ?");
            $stmt->execute([$id, $eventId]);
            $newState = $stmt->fetchColumn();

            echo json_encode(['success' => true, 'invitation_sent' => (bool)$newState]);
            exit;
        }
    }

    if ($action === 'make_toastmaster') {
        $guestId = (int)($_POST['guest_id'] ?? 0);
        if ($guestId) {
            // Get guest info
            $stmt = $db->prepare("SELECT name, email FROM guests WHERE id = ? AND event_id = ?");
            $stmt->execute([$guestId, $eventId]);
            $guestInfo = $stmt->fetch();

            if ($guestInfo) {
                // Check if already a toastmaster
                $stmt = $db->prepare("SELECT id FROM toastmaster_access WHERE event_id = ? AND guest_id = ?");
                $stmt->execute([$eventId, $guestId]);
                if ($stmt->fetch()) {
                    echo json_encode(['success' => false, 'error' => 'Denne gæst er allerede toastmaster']);
                    exit;
                }

                // Generate access code
                $code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));

                // Check if this will be the first (primary) toastmaster
                $stmt = $db->prepare("SELECT COUNT(*) FROM toastmaster_access WHERE event_id = ?");
                $stmt->execute([$eventId]);
                $isPrimary = ($stmt->fetchColumn() == 0) ? 1 : 0;

                // Create toastmaster access
                $stmt = $db->prepare("
                    INSERT INTO toastmaster_access (event_id, access_code, name, email, is_primary, guest_id)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$eventId, $code, $guestInfo['name'], $guestInfo['email'], $isPrimary, $guestId]);

                echo json_encode([
                    'success' => true,
                    'code' => $code,
                    'is_primary' => (bool)$isPrimary
                ]);
                exit;
            }
        }
        echo json_encode(['success' => false, 'error' => 'Gæst ikke fundet']);
        exit;
    }

    if ($action === 'remove_toastmaster') {
        $guestId = (int)($_POST['guest_id'] ?? 0);
        if ($guestId) {
            $stmt = $db->prepare("DELETE FROM toastmaster_access WHERE event_id = ? AND guest_id = ?");
            $stmt->execute([$eventId, $guestId]);
            echo json_encode(['success' => true]);
            exit;
        }
    }

    echo json_encode(['success' => false]);
    exit;

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
        exit;
    }
}

require_once __DIR__ . '/../includes/admin-header.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $maxGuests = max(1, (int)($_POST['max_guests'] ?? 1));
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
                INSERT INTO guests (event_id, name, max_guests, email, phone, notes, unique_code)
                VALUES (?, ?, ?, ?, ?, ?, ?)
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

            setFlash('success', "Gæst tilføjet! Kode: $code");
            redirect(BASE_PATH . '/admin/guests.php');
        }

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
                INSERT INTO guests (event_id, name, max_guests, unique_code)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$eventId, $name, $maxGuests, $code]);
            $added++;
        }

        setFlash('success', "$added gæster tilføjet!");
        redirect(BASE_PATH . '/admin/guests.php');

    } elseif ($action === 'csv_import') {
        // CSV Import with column mapping
        $csvData = $_POST['csv_data'] ?? '';
        $mapping = $_POST['mapping'] ?? [];
        $skipDuplicates = isset($_POST['skip_duplicates']);

        if (empty($csvData) || empty($mapping)) {
            setFlash('error', 'Ingen data at importere');
            redirect(BASE_PATH . '/admin/guests.php');
        }

        // Decode the JSON data
        $rows = json_decode($csvData, true);
        if (!$rows || !is_array($rows)) {
            setFlash('error', 'Ugyldig CSV data');
            redirect(BASE_PATH . '/admin/guests.php');
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
                INSERT INTO guests (event_id, name, max_guests, email, phone, notes, unique_code)
                VALUES (?, ?, ?, ?, ?, ?, ?)
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
        redirect(BASE_PATH . '/admin/guests.php');

    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $maxGuests = max(1, (int)($_POST['max_guests'] ?? 1));
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $rsvpStatus = $_POST['rsvp_status'] ?? null;
        $adultsCount = (int)($_POST['adults_count'] ?? 1);
        $childrenCount = (int)($_POST['children_count'] ?? 0);
        $dietaryNotes = trim($_POST['dietary_notes'] ?? '');

        // Collect guest names
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

        if ($name && $id) {
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

                // Set rsvp_date if changing to yes/no
                if ($rsvpStatus !== 'pending') {
                    $sql .= ", rsvp_date = NOW()";
                }
            }

            $sql .= " WHERE id = ? AND event_id = ?";
            $params[] = $id;
            $params[] = $eventId;

            $stmt = $db->prepare($sql);
            $stmt->execute($params);

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
        // Non-AJAX fallback (AJAX is handled at top of file)
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

// Get toastmasters linked to guests
$toastmasterGuests = [];
try {
    // Auto-link unlinked toastmasters to guests by matching name
    $stmt = $db->prepare("SELECT id, name FROM toastmaster_access WHERE event_id = ? AND guest_id IS NULL AND name IS NOT NULL");
    $stmt->execute([$eventId]);
    $unlinkedToastmasters = $stmt->fetchAll();

    foreach ($unlinkedToastmasters as $tm) {
        // Try to find a guest with matching name
        $stmtGuest = $db->prepare("SELECT id FROM guests WHERE event_id = ? AND LOWER(name) = LOWER(?)");
        $stmtGuest->execute([$eventId, $tm['name']]);
        $matchingGuest = $stmtGuest->fetch();

        if ($matchingGuest) {
            // Link the toastmaster to this guest
            $stmtUpdate = $db->prepare("UPDATE toastmaster_access SET guest_id = ? WHERE id = ?");
            $stmtUpdate->execute([$matchingGuest['id'], $tm['id']]);
        }
    }

    $stmt = $db->prepare("SELECT guest_id, access_code, is_primary FROM toastmaster_access WHERE event_id = ? AND guest_id IS NOT NULL");
    $stmt->execute([$eventId]);
    while ($row = $stmt->fetch()) {
        $toastmasterGuests[$row['guest_id']] = [
            'code' => $row['access_code'],
            'is_primary' => $row['is_primary']
        ];
    }
} catch (Exception $e) {
    // Table might not exist yet or other error
}

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
$showExport = isset($_GET['action']) && $_GET['action'] === 'export';

// Get base URL for links
$baseUrl = getBaseUrl() . '/';

// If export view, show simple list
if ($showExport):
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eksportér invitationslinks</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Outfit', sans-serif;
            background: #f8f9fa;
            padding: 2rem;
            line-height: 1.6;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #1a1a1a;
        }
        .subtitle {
            color: #666;
            margin-bottom: 1.5rem;
        }
        .actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.875rem;
            cursor: pointer;
            text-decoration: none;
            border: none;
            font-family: inherit;
        }
        .btn--primary {
            background: #8B7355;
            color: white;
        }
        .btn--secondary {
            background: white;
            color: #333;
            border: 1px solid #ddd;
        }
        .export-box {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        .export-list {
            font-family: monospace;
            font-size: 0.8rem;
            white-space: pre-wrap;
            word-break: break-all;
            line-height: 2;
            color: #333;
        }
        .guest-row {
            padding: 0.5rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .guest-row:last-child {
            border-bottom: none;
        }
        .guest-name {
            font-weight: 500;
            color: #1a1a1a;
        }
        .guest-link {
            color: #666;
            font-size: 0.75rem;
        }
        .copy-hint {
            font-size: 0.75rem;
            color: #999;
            margin-top: 1rem;
        }
        textarea {
            width: 100%;
            height: 300px;
            font-family: monospace;
            font-size: 0.8rem;
            padding: 1rem;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            resize: vertical;
        }
        .tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .tab {
            padding: 0.5rem 1rem;
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.875rem;
        }
        .tab.active {
            background: #8B7355;
            color: white;
            border-color: #8B7355;
        }
        .view { display: none; }
        .view.active { display: block; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Invitationslinks</h1>
        <p class="subtitle"><?= count($guests) ?> gæster</p>

        <div class="actions">
            <a href="<?= BASE_PATH ?>/admin/guests.php" class="btn btn--secondary">← Tilbage til gæsteliste</a>
            <button onclick="copyAll()" class="btn btn--primary">Kopiér alle</button>
        </div>

        <div class="tabs">
            <button class="tab active" onclick="showView('list')">Liste</button>
            <button class="tab" onclick="showView('text')">Tekst (til kopiering)</button>
            <button class="tab" onclick="showView('csv')">CSV</button>
        </div>

        <!-- List View -->
        <div id="view-list" class="view active">
            <div class="export-box">
                <?php foreach ($guests as $guest): ?>
                <div class="guest-row">
                    <div class="guest-name"><?= escape($guest['name']) ?></div>
                    <div class="guest-link"><?= $baseUrl ?>?kode=<?= escape($guest['unique_code']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Text View -->
        <div id="view-text" class="view">
            <textarea id="text-export" readonly><?php foreach ($guests as $guest): ?>
<?= htmlspecialchars($guest['name']) ?>
<?= htmlspecialchars($baseUrl) ?>?kode=<?= htmlspecialchars($guest['unique_code']) ?>

<?php endforeach; ?></textarea>
            <p class="copy-hint">Markér alt (Ctrl+A) og kopiér (Ctrl+C)</p>
        </div>

        <!-- CSV View -->
        <div id="view-csv" class="view">
            <textarea id="csv-export" readonly>Navn;Kode;Link
<?php foreach ($guests as $guest): ?>
<?= $guest['name'] ?>;<?= $guest['unique_code'] ?>;<?= $baseUrl ?>?kode=<?= $guest['unique_code'] ?>
<?php endforeach; ?></textarea>
            <p class="copy-hint">Kan indsættes direkte i Excel</p>
        </div>
    </div>

    <script>
    function showView(view) {
        document.querySelectorAll('.view').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
        document.getElementById('view-' + view).classList.add('active');
        event.target.classList.add('active');
    }

    function copyAll() {
        const text = document.getElementById('text-export').value;
        navigator.clipboard.writeText(text).then(() => {
            alert('Kopieret til udklipsholder!');
        });
    }
    </script>
</body>
</html>
<?php
exit;
endif;

require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<style>
.badge--toastmaster {
    display: inline-block;
    padding: 0.15rem 0.5rem;
    font-size: 0.65rem;
    font-weight: 600;
    background: linear-gradient(135deg, #8B5CF6 0%, #A78BFA 100%);
    color: white;
    border-radius: 10px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin-left: 0.5rem;
    vertical-align: middle;
}
</style>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-header__title">Gæsteliste</h1>
        <p class="page-header__subtitle">
            <strong><?= $guestStats['total_confirmed'] ?? 0 ?></strong> tilmeldte af <strong><?= $guestStats['total_invited'] ?? 0 ?></strong> inviterede personer
            &middot;
            <span class="<?= $invitationsNotSent > 0 ? 'text-warning' : 'text-success' ?>">
                <?= $invitationsSent ?>/<?= $totalGuests ?> invitationer sendt
            </span>
        </p>
    </div>
    <div class="page-header__actions">
        <a href="?action=export" class="btn btn--ghost">Eksportér links</a>
        <button onclick="openModal('import-modal')" class="btn btn--secondary">📥 Importér CSV</button>
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
                        <th>Max</th>
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
                                <button type="button"
                                        class="btn btn--ghost invitation-toggle"
                                        data-id="<?= $guest['id'] ?>"
                                        title="<?= $guest['invitation_sent'] ? 'Markér som ikke sendt' : 'Markér som sendt' ?>"
                                        style="font-size: 1.2em; padding: 4px 8px;">
                                    <?php if ($guest['invitation_sent']): ?>
                                        <span style="color: var(--color-success);">✓</span>
                                    <?php else: ?>
                                        <span style="color: var(--color-text-muted);">○</span>
                                    <?php endif; ?>
                                </button>
                            </td>
                            <td>
                                <strong><?= escape($guest['name']) ?></strong>
                                <?php if (isset($toastmasterGuests[$guest['id']])): ?>
                                    <span class="badge badge--toastmaster" title="Toastmaster - Kode: <?= $toastmasterGuests[$guest['id']]['code'] ?>">
                                        🎤 <?= $toastmasterGuests[$guest['id']]['is_primary'] ? 'Hovedtoastmaster' : 'Toastmaster' ?>
                                    </span>
                                <?php endif; ?>
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
                                <span class="badge badge--neutral"><?= (int)($guest['max_guests'] ?? 1) ?></span>
                            </td>
                            <td>
                                <code style="background: var(--color-bg-subtle); padding: 4px 8px; border-radius: 4px; font-family: 'Cormorant Garamond', serif; font-size: 1.1em; letter-spacing: 0.1em;">
                                    <?= escape($guest['unique_code']) ?>
                                </code>
                                <button onclick="copyCode('<?= escape($guest['unique_code']) ?>')"
                                        class="btn btn--ghost small"
                                        title="Kopiér invitationslink"
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
                                    <?php if (!empty($guest['guest_names'])): ?>
                                        <?php $names = json_decode($guest['guest_names'], true); ?>
                                        <?php if ($names): ?>
                                            <br>
                                            <span class="small text-muted" title="<?= escape(implode(', ', $names)) ?>">
                                                <?= escape(implode(', ', array_slice($names, 0, 3))) ?><?= count($names) > 3 ? '...' : '' ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-row">
                                    <?php if (isset($toastmasterGuests[$guest['id']])): ?>
                                        <button onclick="removeToastmaster(<?= $guest['id'] ?>, '<?= escape($guest['name']) ?>')"
                                                class="btn btn--ghost"
                                                title="Fjern som toastmaster">
                                            🎤❌
                                        </button>
                                    <?php else: ?>
                                        <button onclick="makeToastmaster(<?= $guest['id'] ?>, '<?= escape($guest['name']) ?>')"
                                                class="btn btn--ghost"
                                                title="Gør til toastmaster">
                                            🎤
                                        </button>
                                    <?php endif; ?>
                                    <button onclick='editGuest(<?= json_encode($guest) ?>)'
                                            class="btn btn--ghost"
                                            title="Rediger">
                                        ✏️
                                    </button>
                                    <form method="POST" style="display: inline;">
                                        <?= csrfField() ?>
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

<!-- CSV Import Modal -->
<div id="import-modal" class="modal-overlay">
    <div class="modal" style="max-width: 700px;">
        <div class="modal__header">
            <h2 class="modal__title">Importér gæster fra CSV</h2>
            <button class="modal__close" onclick="closeModal('import-modal')">&times;</button>
        </div>
        <div class="modal__body">
            <!-- Step 1: File Upload -->
            <div id="import-step-1">
                <div class="form-group">
                    <label class="form-label">Vælg CSV-fil</label>
                    <input type="file" id="csv-file-input" accept=".csv,.txt" class="form-input" onchange="handleFileSelect(event)">
                    <p class="small text-muted mt-xs">
                        Understøtter CSV-filer med semikolon (;) eller komma (,) som separator.<br>
                        Første række skal indeholde kolonnenavne.
                    </p>
                </div>

                <div class="form-group mt-md">
                    <label class="form-label">Eller indsæt fra Excel</label>
                    <textarea id="csv-paste-area" class="form-input" rows="6"
                              placeholder="Kopiér data fra Excel og indsæt her...
Navn;Email;Telefon;Max gæster
Peter Hansen;peter@email.dk;12345678;2
Marie Jensen;marie@email.dk;;1"
                              onpaste="handlePaste(event)"></textarea>
                    <button type="button" class="btn btn--secondary mt-sm" onclick="parseTextArea()">Indlæs data</button>
                </div>
            </div>

            <!-- Step 2: Column Mapping -->
            <div id="import-step-2" style="display: none;">
                <div class="alert alert--info mb-md">
                    <strong>Trin 2:</strong> Vælg hvilke kolonner der skal bruges til hvad
                </div>

                <div id="column-mapping-container"></div>

                <div class="form-group mt-md">
                    <label class="flex items-center gap-sm">
                        <input type="checkbox" id="skip-duplicates" checked>
                        <span>Spring duplikater over (samme navn eller email)</span>
                    </label>
                </div>

                <div class="flex gap-sm mt-md">
                    <button type="button" class="btn btn--secondary" onclick="goToStep(1)">← Tilbage</button>
                    <button type="button" class="btn btn--primary" onclick="goToStep(3)">Se preview →</button>
                </div>
            </div>

            <!-- Step 3: Preview -->
            <div id="import-step-3" style="display: none;">
                <div class="alert alert--info mb-md">
                    <strong>Trin 3:</strong> Bekræft import
                </div>

                <div id="preview-summary" class="mb-md"></div>

                <div class="table-container" style="max-height: 300px; overflow-y: auto;">
                    <table class="table" id="preview-table">
                        <thead></thead>
                        <tbody></tbody>
                    </table>
                </div>

                <div id="preview-errors" class="mt-md" style="display: none;">
                    <div class="alert alert--warning">
                        <strong>Advarsler:</strong>
                        <ul id="error-list" class="mt-xs" style="margin-bottom: 0; padding-left: 1.5rem;"></ul>
                    </div>
                </div>

                <form method="POST" id="import-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="csv_import">
                    <input type="hidden" name="csv_data" id="csv-data-input">
                    <input type="hidden" name="mapping" id="mapping-input">
                    <input type="hidden" name="skip_duplicates" id="skip-duplicates-input">

                    <div class="flex gap-sm mt-md">
                        <button type="button" class="btn btn--secondary" onclick="goToStep(2)">← Tilbage</button>
                        <button type="submit" class="btn btn--primary" id="import-submit-btn">Importér gæster</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Add Modal -->
<div id="bulk-modal" class="modal-overlay <?= $showBulkModal ? 'modal-overlay--active' : '' ?>">
    <div class="modal" style="max-width: 500px;">
        <div class="modal__header">
            <h2 class="modal__title">Tilføj mange gæster</h2>
            <button class="modal__close" onclick="closeModal('bulk-modal')">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="bulk_add">
            <div class="modal__body">
                <div class="form-group">
                    <label class="form-label">Standard max gæster</label>
                    <input type="number" name="default_max_guests" class="form-input" value="1" min="1" max="20" style="width: 100px;">
                    <p class="small text-muted mt-xs">Bruges hvis ikke angivet per linje</p>
                </div>
                <div class="form-group">
                    <label class="form-label">Navne (ét navn per linje)</label>
                    <textarea name="names"
                              class="form-input"
                              rows="10"
                              required
                              placeholder="Mormor og Morfar (2)
Onkel Peter (4)
Tante Lisa
Fætter Magnus (1)
..."
                              style="font-family: inherit;"></textarea>
                    <p class="small text-muted mt-xs">
                        Skriv ét navn per linje. Tilføj <strong>(antal)</strong> for at angive max gæster.<br>
                        F.eks. "Peter og familie (4)" = max 4 gæster.
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
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add">
            <div class="modal__body">
                <div style="display: grid; grid-template-columns: 1fr auto; gap: var(--space-md);">
                    <div class="form-group">
                        <label class="form-label">Navn *</label>
                        <input type="text" name="name" class="form-input" required placeholder="F.eks. Mormor og Morfar">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Max gæster</label>
                        <input type="number" name="max_guests" class="form-input" value="1" min="1" max="20" style="width: 80px;">
                    </div>
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
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit-id">
            <div class="modal__body">
                <div style="display: grid; grid-template-columns: 1fr auto; gap: var(--space-md);">
                    <div class="form-group">
                        <label class="form-label">Navn *</label>
                        <input type="text" name="name" id="edit-name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Max gæster</label>
                        <input type="number" name="max_guests" id="edit-max-guests" class="form-input" value="1" min="1" max="20" style="width: 80px;">
                    </div>
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

                <!-- RSVP Status -->
                <div style="margin-top: var(--space-md); padding-top: var(--space-md); border-top: 1px solid var(--color-border-soft);">
                    <div class="form-group">
                        <label class="form-label">RSVP Status</label>
                        <select name="rsvp_status" id="edit-rsvp-status" class="form-input" onchange="toggleRsvpDetails()">
                            <option value="pending">Afventer svar</option>
                            <option value="yes">Kommer</option>
                            <option value="no">Afbud</option>
                        </select>
                    </div>

                    <div id="rsvp-details" style="display: none;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-md);">
                            <div class="form-group">
                                <label class="form-label">Antal voksne</label>
                                <input type="number" name="adults_count" id="edit-adults" class="form-input" min="1" value="1" onchange="updateEditNameFields()">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Antal børn</label>
                                <input type="number" name="children_count" id="edit-children" class="form-input" min="0" value="0">
                            </div>
                        </div>

                        <div class="form-group mt-sm">
                            <label class="form-label">Navne på deltagere (til bordplan)</label>
                            <div id="edit-guest-names-inputs"></div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Kostbehov/allergier</label>
                            <textarea name="dietary_notes" id="edit-dietary-input" class="form-input" rows="2" placeholder="F.eks. vegetar, glutenfri..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Read-only info -->
                <div class="mt-md" style="padding-top: var(--space-sm); border-top: 1px solid var(--color-border-soft);">
                    <p class="small text-muted mb-xs">
                        <strong>Kode:</strong> <span id="edit-code"></span>
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

// Store current guest data for name field generation
let currentEditGuest = null;

function editGuest(guest) {
    currentEditGuest = guest;

    document.getElementById('edit-id').value = guest.id;
    document.getElementById('edit-name').value = guest.name;
    document.getElementById('edit-max-guests').value = guest.max_guests || 1;
    document.getElementById('edit-email').value = guest.email || '';
    document.getElementById('edit-phone').value = guest.phone || '';
    document.getElementById('edit-notes').value = guest.notes || '';
    document.getElementById('edit-code').textContent = guest.unique_code;

    // Set RSVP status
    document.getElementById('edit-rsvp-status').value = guest.rsvp_status || 'pending';
    document.getElementById('edit-adults').value = guest.adults_count || 1;
    document.getElementById('edit-children').value = guest.children_count || 0;
    document.getElementById('edit-dietary-input').value = guest.dietary_notes || '';
    toggleRsvpDetails();

    // Generate name input fields
    updateEditNameFields();

    openModal('edit-modal');
}

function updateEditNameFields() {
    const container = document.getElementById('edit-guest-names-inputs');
    const count = parseInt(document.getElementById('edit-adults').value) || 1;
    const maxGuests = parseInt(document.getElementById('edit-max-guests').value) || 10;

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
        if (input.value && i < existingNames.length) {
            existingNames[i] = input.value;
        } else if (input.value) {
            existingNames[i] = input.value;
        }
    });

    // Clear container
    container.innerHTML = '';

    // Generate input fields based on adults count
    for (let i = 0; i < count; i++) {
        const div = document.createElement('div');
        div.style.marginBottom = '0.5rem';

        const input = document.createElement('input');
        input.type = 'text';
        input.name = 'guest_names[]';
        input.className = 'form-input';
        input.placeholder = `Navn på person ${i + 1}`;
        input.value = existingNames[i] || '';

        div.appendChild(input);
        container.appendChild(div);
    }
}

function toggleRsvpDetails() {
    const status = document.getElementById('edit-rsvp-status').value;
    const details = document.getElementById('rsvp-details');
    details.style.display = status === 'yes' ? 'block' : 'none';
}

function copyCode(code) {
    const fullLink = '<?= getBaseUrl() ?>/?kode=' + code;
    navigator.clipboard.writeText(fullLink).then(() => {
        showToast('Link kopieret til udklipsholder!');
    });
}

function showToast(message) {
    // Remove existing toast
    const existing = document.querySelector('.toast');
    if (existing) existing.remove();

    // Create toast
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.textContent = message;
    document.body.appendChild(toast);

    // Trigger animation
    setTimeout(() => toast.classList.add('toast--visible'), 10);

    // Remove after delay
    setTimeout(() => {
        toast.classList.remove('toast--visible');
        setTimeout(() => toast.remove(), 300);
    }, 2000);
}

// Confirm delete
document.querySelectorAll('[data-confirm]').forEach(btn => {
    btn.addEventListener('click', function(e) {
        if (!confirm(this.dataset.confirm)) {
            e.preventDefault();
        }
    });
});

// Toastmaster functions
function makeToastmaster(guestId, guestName) {
    if (!confirm(`Gør ${guestName} til toastmaster?`)) return;

    fetch('<?= BASE_PATH ?>/admin/guests.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
        },
        body: `action=make_toastmaster&guest_id=${guestId}&csrf_token=${document.querySelector('meta[name="csrf-token"]').content}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const msg = data.is_primary
                ? `${guestName} er nu hovedtoastmaster! Kode: ${data.code}`
                : `${guestName} er nu toastmaster! Kode: ${data.code}`;
            showToast(msg);
            location.reload();
        } else {
            alert(data.error || 'Kunne ikke oprette toastmaster');
        }
    });
}

function removeToastmaster(guestId, guestName) {
    if (!confirm(`Fjern ${guestName} som toastmaster?`)) return;

    fetch('<?= BASE_PATH ?>/admin/guests.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
        },
        body: `action=remove_toastmaster&guest_id=${guestId}&csrf_token=${document.querySelector('meta[name="csrf-token"]').content}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(`${guestName} er ikke længere toastmaster`);
            location.reload();
        }
    });
}

// Toggle invitation via AJAX (no page reload)
document.querySelectorAll('.invitation-toggle').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.dataset.id;
        const button = this;
        const span = button.querySelector('span');

        // Disable button during request
        button.disabled = true;

        fetch('<?= BASE_PATH ?>/admin/guests.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            },
            body: 'action=toggle_invitation&id=' + id + '&csrf_token=' + document.querySelector('meta[name="csrf-token"]').content
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.invitation_sent) {
                    span.textContent = '✓';
                    span.style.color = 'var(--color-success)';
                    button.title = 'Markér som ikke sendt';
                } else {
                    span.textContent = '○';
                    span.style.color = 'var(--color-text-muted)';
                    button.title = 'Markér som sendt';
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
        })
        .finally(() => {
            button.disabled = false;
        });
    });
});

// =====================
// CSV Import Functions
// =====================

let csvHeaders = [];
let csvRows = [];
let columnMapping = {};

function handleFileSelect(event) {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(e) {
        const content = e.target.result;
        parseCSV(content);
    };
    reader.readAsText(file, 'UTF-8');
}

function handlePaste(event) {
    // Let the paste happen, then parse
    setTimeout(() => parseTextArea(), 100);
}

function parseTextArea() {
    const content = document.getElementById('csv-paste-area').value;
    if (content.trim()) {
        parseCSV(content);
    }
}

function parseCSV(content) {
    // Detect separator (semicolon or comma)
    const firstLine = content.split('\n')[0];
    const separator = firstLine.includes(';') ? ';' : ',';

    // Parse CSV
    const lines = content.trim().split('\n');
    if (lines.length < 2) {
        alert('CSV-filen skal have mindst én header-række og én data-række');
        return;
    }

    // Parse header
    csvHeaders = parseCSVLine(lines[0], separator);

    // Parse data rows
    csvRows = [];
    for (let i = 1; i < lines.length; i++) {
        const line = lines[i].trim();
        if (!line) continue;

        const values = parseCSVLine(line, separator);
        const row = {};
        csvHeaders.forEach((header, index) => {
            row[index] = values[index] || '';
        });
        csvRows.push(row);
    }

    if (csvRows.length === 0) {
        alert('Ingen data-rækker fundet i CSV-filen');
        return;
    }

    // Initialize column mapping and go to step 2
    initializeColumnMapping();
    goToStep(2);
}

function parseCSVLine(line, separator) {
    const values = [];
    let current = '';
    let inQuotes = false;

    for (let i = 0; i < line.length; i++) {
        const char = line[i];

        if (char === '"') {
            inQuotes = !inQuotes;
        } else if (char === separator && !inQuotes) {
            values.push(current.trim());
            current = '';
        } else {
            current += char;
        }
    }
    values.push(current.trim());

    return values;
}

function initializeColumnMapping() {
    const container = document.getElementById('column-mapping-container');
    container.innerHTML = '';

    // Field options
    const fieldOptions = [
        { value: '', label: '-- Spring over --' },
        { value: 'name', label: 'Navn (påkrævet)' },
        { value: 'email', label: 'Email' },
        { value: 'phone', label: 'Telefon' },
        { value: 'max_guests', label: 'Max gæster' },
        { value: 'notes', label: 'Noter' }
    ];

    // Auto-detect mapping based on header names
    const autoMapping = {};
    csvHeaders.forEach((header, index) => {
        const headerLower = header.toLowerCase().trim();
        if (headerLower.includes('navn') || headerLower === 'name') {
            autoMapping[index] = 'name';
        } else if (headerLower.includes('email') || headerLower.includes('mail') || headerLower.includes('e-mail')) {
            autoMapping[index] = 'email';
        } else if (headerLower.includes('telefon') || headerLower.includes('phone') || headerLower.includes('tlf') || headerLower.includes('mobil')) {
            autoMapping[index] = 'phone';
        } else if (headerLower.includes('max') || headerLower.includes('antal') || headerLower.includes('gæster')) {
            autoMapping[index] = 'max_guests';
        } else if (headerLower.includes('note') || headerLower.includes('kommentar') || headerLower.includes('bemærk')) {
            autoMapping[index] = 'notes';
        }
    });

    // Create mapping UI
    csvHeaders.forEach((header, index) => {
        const row = document.createElement('div');
        row.style.cssText = 'display: grid; grid-template-columns: 1fr auto 1fr; gap: 1rem; align-items: center; margin-bottom: 0.75rem;';

        // Sample data from first row
        const sampleData = csvRows[0] ? (csvRows[0][index] || '(tom)') : '(tom)';

        row.innerHTML = `
            <div>
                <strong>${escapeHtml(header)}</strong>
                <div class="small text-muted">${escapeHtml(sampleData.substring(0, 30))}${sampleData.length > 30 ? '...' : ''}</div>
            </div>
            <div>→</div>
            <select class="form-input column-mapping-select" data-col="${index}">
                ${fieldOptions.map(opt =>
                    `<option value="${opt.value}" ${autoMapping[index] === opt.value ? 'selected' : ''}>${opt.label}</option>`
                ).join('')}
            </select>
        `;

        container.appendChild(row);
    });

    // Check if name column is mapped
    validateMapping();

    // Add change listeners
    container.querySelectorAll('.column-mapping-select').forEach(select => {
        select.addEventListener('change', validateMapping);
    });
}

function validateMapping() {
    const selects = document.querySelectorAll('.column-mapping-select');
    let hasName = false;

    selects.forEach(select => {
        if (select.value === 'name') hasName = true;
    });

    // Show warning if no name column
    let warning = document.getElementById('mapping-warning');
    if (!hasName) {
        if (!warning) {
            warning = document.createElement('div');
            warning.id = 'mapping-warning';
            warning.className = 'alert alert--warning mt-md';
            warning.textContent = 'Du skal vælge en kolonne til "Navn"';
            document.getElementById('column-mapping-container').appendChild(warning);
        }
    } else if (warning) {
        warning.remove();
    }

    return hasName;
}

function goToStep(step) {
    document.getElementById('import-step-1').style.display = step === 1 ? 'block' : 'none';
    document.getElementById('import-step-2').style.display = step === 2 ? 'block' : 'none';
    document.getElementById('import-step-3').style.display = step === 3 ? 'block' : 'none';

    if (step === 3) {
        if (!validateMapping()) {
            alert('Du skal vælge en kolonne til "Navn"');
            goToStep(2);
            return;
        }
        buildPreview();
    }
}

function buildPreview() {
    // Collect mapping
    columnMapping = {};
    document.querySelectorAll('.column-mapping-select').forEach(select => {
        const col = select.dataset.col;
        const field = select.value;
        if (field) {
            columnMapping[col] = field;
        }
    });

    // Build preview table
    const thead = document.querySelector('#preview-table thead');
    const tbody = document.querySelector('#preview-table tbody');

    // Headers
    const mappedFields = Object.values(columnMapping);
    const fieldLabels = {
        'name': 'Navn',
        'email': 'Email',
        'phone': 'Telefon',
        'max_guests': 'Max gæster',
        'notes': 'Noter'
    };

    thead.innerHTML = '<tr>' + mappedFields.map(f => `<th>${fieldLabels[f] || f}</th>`).join('') + '<th>Status</th></tr>';

    // Rows
    const errors = [];
    let validCount = 0;

    tbody.innerHTML = csvRows.slice(0, 20).map((row, idx) => {
        let rowErrors = [];

        // Get mapped values
        const mappedValues = {};
        for (const [col, field] of Object.entries(columnMapping)) {
            mappedValues[field] = row[col] || '';
        }

        // Validate
        if (!mappedValues.name || !mappedValues.name.trim()) {
            rowErrors.push('Mangler navn');
        }
        if (mappedValues.email && !isValidEmail(mappedValues.email)) {
            rowErrors.push('Ugyldig email');
        }

        if (rowErrors.length === 0) {
            validCount++;
        } else {
            errors.push({ row: idx + 2, errors: rowErrors });
        }

        const statusHtml = rowErrors.length === 0
            ? '<span class="badge badge--success">OK</span>'
            : `<span class="badge badge--warning" title="${escapeHtml(rowErrors.join(', '))}">${rowErrors.length} problem${rowErrors.length > 1 ? 'er' : ''}</span>`;

        return '<tr>' +
            mappedFields.map(f => `<td>${escapeHtml((mappedValues[f] || '').substring(0, 50))}</td>`).join('') +
            `<td>${statusHtml}</td></tr>`;
    }).join('');

    if (csvRows.length > 20) {
        tbody.innerHTML += `<tr><td colspan="${mappedFields.length + 1}" class="text-muted text-center">... og ${csvRows.length - 20} flere rækker</td></tr>`;
    }

    // Summary
    document.getElementById('preview-summary').innerHTML = `
        <div class="flex gap-md">
            <div><strong>${csvRows.length}</strong> rækker fundet</div>
            <div><strong class="text-success">${validCount}</strong> gyldige</div>
            ${errors.length > 0 ? `<div><strong class="text-warning">${errors.length}</strong> med problemer</div>` : ''}
        </div>
    `;

    // Errors
    if (errors.length > 0) {
        document.getElementById('preview-errors').style.display = 'block';
        document.getElementById('error-list').innerHTML = errors.slice(0, 10).map(e =>
            `<li>Række ${e.row}: ${escapeHtml(e.errors.join(', '))}</li>`
        ).join('') + (errors.length > 10 ? `<li>... og ${errors.length - 10} flere</li>` : '');
    } else {
        document.getElementById('preview-errors').style.display = 'none';
    }

    // Prepare form data
    document.getElementById('csv-data-input').value = JSON.stringify(csvRows);
    document.getElementById('mapping-input').name = '';  // Clear old

    // Create mapping inputs
    const form = document.getElementById('import-form');
    form.querySelectorAll('.mapping-field').forEach(el => el.remove());
    for (const [col, field] of Object.entries(columnMapping)) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = `mapping[${col}]`;
        input.value = field;
        input.className = 'mapping-field';
        form.appendChild(input);
    }

    // Skip duplicates
    const skipDupInput = document.getElementById('skip-duplicates-input');
    skipDupInput.value = document.getElementById('skip-duplicates').checked ? '1' : '';
    skipDupInput.name = document.getElementById('skip-duplicates').checked ? 'skip_duplicates' : '';

    // Update button text
    document.getElementById('import-submit-btn').textContent = `Importér ${validCount} gæster`;
}

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Reset import modal when opened
const importModal = document.getElementById('import-modal');
if (importModal) {
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.target.classList.contains('modal-overlay--active')) {
                // Modal opened - reset to step 1
                goToStep(1);
                document.getElementById('csv-file-input').value = '';
                document.getElementById('csv-paste-area').value = '';
                csvHeaders = [];
                csvRows = [];
                columnMapping = {};
            }
        });
    });
    observer.observe(importModal, { attributes: true, attributeFilter: ['class'] });
}
</script>
</body>
</html>

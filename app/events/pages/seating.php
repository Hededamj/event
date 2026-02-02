<?php
/**
 * Seating Plan / Bordplan Page
 * Included by manage.php
 * Visual seating arrangement with drag-and-drop
 */

// Check if feature is available
if (!$hasSeating): ?>
<div class="upgrade-notice">
    <div class="upgrade-notice-content">
        <h4>Bordplan er en premium-funktion</h4>
        <p>Opgrader til Basis eller hojere for at fa adgang til bordplan-funktionen.</p>
    </div>
    <a href="/app/account/subscription.php" class="btn">Opgrader nu</a>
</div>
<?php return; endif;

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');

    if (!verifyAccountCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add_table') {
        $name = trim($_POST['name'] ?? '');
        $type = $_POST['table_type'] ?? 'round';
        $capacity = max(2, min(20, (int)($_POST['capacity'] ?? 8)));

        if ($name) {
            $stmt = $db->prepare("SELECT MAX(sort_order) FROM seating_tables WHERE event_id = ?");
            $stmt->execute([$eventId]);
            $maxOrder = $stmt->fetchColumn() ?? 0;

            $stmt = $db->prepare("
                INSERT INTO seating_tables (event_id, name, table_type, capacity, sort_order)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$eventId, $name, $type, $capacity, $maxOrder + 1]);

            echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
            exit;
        }
        echo json_encode(['success' => false, 'error' => 'Navn pakraevet']);
        exit;
    }

    if ($action === 'delete_table') {
        $tableId = (int)($_POST['table_id'] ?? 0);
        if ($tableId) {
            $stmt = $db->prepare("DELETE FROM seating_assignments WHERE table_id = ? AND event_id = ?");
            $stmt->execute([$tableId, $eventId]);

            $stmt = $db->prepare("DELETE FROM seating_tables WHERE id = ? AND event_id = ?");
            $stmt->execute([$tableId, $eventId]);

            echo json_encode(['success' => true]);
            exit;
        }
    }

    if ($action === 'update_table') {
        $tableId = (int)($_POST['table_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $capacity = max(2, min(20, (int)($_POST['capacity'] ?? 8)));

        if ($tableId && $name) {
            $stmt = $db->prepare("UPDATE seating_tables SET name = ?, capacity = ? WHERE id = ? AND event_id = ?");
            $stmt->execute([$name, $capacity, $tableId, $eventId]);
            echo json_encode(['success' => true]);
            exit;
        }
    }

    if ($action === 'assign_guest') {
        $tableId = (int)($_POST['table_id'] ?? 0);
        $seatNumber = (int)($_POST['seat_number'] ?? 0);
        $guestName = trim($_POST['guest_name'] ?? '');
        $guestId = !empty($_POST['guest_id']) ? (int)$_POST['guest_id'] : null;

        if ($tableId && $guestName && $seatNumber > 0) {
            // Check if seat is already taken
            $stmt = $db->prepare("SELECT id FROM seating_assignments WHERE table_id = ? AND seat_number = ?");
            $stmt->execute([$tableId, $seatNumber]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Pladsen er optaget']);
                exit;
            }

            $stmt = $db->prepare("
                INSERT INTO seating_assignments (event_id, table_id, guest_name, seat_number, guest_id)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$eventId, $tableId, $guestName, $seatNumber, $guestId]);

            echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
            exit;
        }
        echo json_encode(['success' => false, 'error' => 'Manglende data']);
        exit;
    }

    if ($action === 'remove_guest') {
        $assignmentId = (int)($_POST['assignment_id'] ?? 0);
        if ($assignmentId) {
            $stmt = $db->prepare("DELETE FROM seating_assignments WHERE id = ? AND event_id = ?");
            $stmt->execute([$assignmentId, $eventId]);

            echo json_encode(['success' => true]);
            exit;
        }
    }

    if ($action === 'move_guest') {
        $assignmentId = (int)($_POST['assignment_id'] ?? 0);
        $newTableId = (int)($_POST['new_table_id'] ?? 0);
        $newSeatNumber = (int)($_POST['seat_number'] ?? 0);

        if ($assignmentId && $newTableId && $newSeatNumber > 0) {
            // Check if seat is already taken (by someone else)
            $stmt = $db->prepare("SELECT id FROM seating_assignments WHERE table_id = ? AND seat_number = ? AND id != ?");
            $stmt->execute([$newTableId, $newSeatNumber, $assignmentId]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Pladsen er optaget']);
                exit;
            }

            $stmt = $db->prepare("UPDATE seating_assignments SET table_id = ?, seat_number = ? WHERE id = ? AND event_id = ?");
            $stmt->execute([$newTableId, $newSeatNumber, $assignmentId, $eventId]);

            echo json_encode(['success' => true]);
            exit;
        }
    }

    if ($action === 'clear_all_seats') {
        $stmt = $db->prepare("DELETE FROM seating_assignments WHERE event_id = ?");
        $stmt->execute([$eventId]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'create_tables_auto') {
        $tableType = $_POST['table_type'] ?? 'round';
        $capacity = max(4, min(16, (int)($_POST['capacity'] ?? 8)));
        $tableCount = max(1, min(50, (int)($_POST['table_count'] ?? 5)));

        // Delete existing tables and assignments
        $db->prepare("DELETE FROM seating_assignments WHERE event_id = ?")->execute([$eventId]);
        $db->prepare("DELETE FROM seating_tables WHERE event_id = ?")->execute([$eventId]);

        // Create tables
        $stmt = $db->prepare("
            INSERT INTO seating_tables (event_id, name, table_type, capacity, sort_order)
            VALUES (?, ?, ?, ?, ?)
        ");

        for ($i = 1; $i <= $tableCount; $i++) {
            $stmt->execute([$eventId, "Bord $i", $tableType, $capacity, $i]);
        }

        echo json_encode(['success' => true, 'tables_created' => $tableCount]);
        exit;
    }

    echo json_encode(['success' => false]);
    exit;
}

// Handle POST form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyAccountCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Ugyldig anmodning.');
        redirect("?id=$eventId&page=seating");
    }

    $action = $_POST['action'];

    if ($action === 'add_table') {
        $name = trim($_POST['name'] ?? '');
        $type = $_POST['table_type'] ?? 'round';
        $capacity = max(2, min(20, (int)($_POST['capacity'] ?? 8)));

        if ($name) {
            $stmt = $db->prepare("SELECT MAX(sort_order) FROM seating_tables WHERE event_id = ?");
            $stmt->execute([$eventId]);
            $maxOrder = $stmt->fetchColumn() ?? 0;

            $stmt = $db->prepare("
                INSERT INTO seating_tables (event_id, name, table_type, capacity, sort_order)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$eventId, $name, $type, $capacity, $maxOrder + 1]);
            setFlash('success', 'Bord tilfojet.');
        }
        redirect("?id=$eventId&page=seating");
    }
}

// Get all tables
$stmt = $db->prepare("SELECT * FROM seating_tables WHERE event_id = ? ORDER BY sort_order");
$stmt->execute([$eventId]);
$tables = $stmt->fetchAll();

// Get all assignments
$stmt = $db->prepare("SELECT * FROM seating_assignments WHERE event_id = ?");
$stmt->execute([$eventId]);
$assignments = $stmt->fetchAll();

// Group assignments by table
$assignmentsByTable = [];
foreach ($assignments as $a) {
    $assignmentsByTable[$a['table_id']][] = $a;
}

// Get confirmed guests with their individual names
$stmt = $db->prepare("
    SELECT g.id, g.name, g.guest_names, g.adults_count
    FROM guests g
    WHERE g.event_id = ? AND g.rsvp_status = 'accepted'
    ORDER BY g.name
");
$stmt->execute([$eventId]);
$confirmedGuests = $stmt->fetchAll();

// Build list of all guest names (individual people)
$allGuestNames = [];
foreach ($confirmedGuests as $guest) {
    if (!empty($guest['guest_names'])) {
        $names = json_decode($guest['guest_names'], true);
        if ($names) {
            foreach ($names as $name) {
                $personName = is_array($name) ? ($name['name'] ?? $name) : $name;
                $allGuestNames[] = [
                    'name' => $personName,
                    'guest_id' => $guest['id'],
                    'invitation' => $guest['name']
                ];
            }
        }
    } else {
        // Fallback to invitation name
        $allGuestNames[] = [
            'name' => $guest['name'],
            'guest_id' => $guest['id'],
            'invitation' => $guest['name']
        ];
    }
}

// Find which guests are already assigned
$assignedNames = array_column($assignments, 'guest_name');
$unassignedGuests = array_filter($allGuestNames, function($g) use ($assignedNames) {
    return !in_array($g['name'], $assignedNames);
});

// Stats
$totalSeats = array_sum(array_column($tables, 'capacity'));
$totalAssigned = count($assignments);
$totalGuests = count($allGuestNames);

$csrfToken = generateAccountCsrfToken();
?>

<div class="page-header-actions">
    <div>
        <h2 class="section-title">Bordplan</h2>
        <p class="section-subtitle">
            <?= $totalAssigned ?> af <?= $totalGuests ?> gaester placeret
            &middot; <?= count($tables) ?> borde med <?= $totalSeats ?> pladser
        </p>
    </div>
    <div class="header-buttons">
        <?php if ($totalAssigned > 0): ?>
        <button type="button" class="btn btn-secondary" onclick="clearAllSeats()">Ryd alle</button>
        <?php endif; ?>
        <button type="button" class="btn btn-secondary" onclick="showAutoModal()">Auto-opret borde</button>
        <button type="button" class="btn btn-primary" onclick="showAddTableModal()">+ Nyt bord</button>
    </div>
</div>

<?php if ($totalGuests === 0): ?>
<div class="card">
    <div class="empty-state">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 48px; height: 48px; margin-bottom: 16px; color: var(--gray-400);">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path>
        </svg>
        <h3>Ingen bekraeftede gaester endnu</h3>
        <p>Vent til gaesterne har svaret pa invitationen</p>
    </div>
</div>
<?php else: ?>

<div class="seating-layout">
    <!-- Unassigned Guests Panel -->
    <div class="seating-sidebar">
        <div class="card">
            <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 12px;">
                Ikke placeret (<?= count($unassignedGuests) ?>)
            </h3>
            <div class="guest-pool" id="guest-pool">
                <?php if (empty($unassignedGuests)): ?>
                    <p style="color: var(--gray-500); text-align: center; padding: 16px; font-size: 14px;">
                        Alle gaester er placeret!
                    </p>
                <?php else: ?>
                    <?php foreach ($unassignedGuests as $guest): ?>
                        <div class="guest-chip"
                             draggable="true"
                             data-guest-name="<?= htmlspecialchars($guest['name']) ?>"
                             data-guest-id="<?= $guest['guest_id'] ?>">
                            <span class="guest-chip__name"><?= htmlspecialchars($guest['name']) ?></span>
                            <span class="guest-chip__info"><?= htmlspecialchars($guest['invitation']) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tables Area -->
    <div class="seating-main">
        <?php if (empty($tables)): ?>
            <div class="card">
                <div class="empty-state">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 48px; height: 48px; margin-bottom: 16px; color: var(--gray-400);">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                    </svg>
                    <h3>Ingen borde endnu</h3>
                    <p>Tilfoj borde for at begynde bordplanen</p>
                    <button type="button" class="btn btn-primary" style="margin-top: 16px;" onclick="showAddTableModal()">+ Tilfoj forste bord</button>
                </div>
            </div>
        <?php else: ?>
            <div class="tables-grid">
                <?php foreach ($tables as $table):
                    $tableAssignments = $assignmentsByTable[$table['id']] ?? [];
                    $seatAssignments = [];
                    foreach ($tableAssignments as $a) {
                        $seatAssignments[$a['seat_number']] = $a;
                    }
                    $seatsTaken = count($tableAssignments);
                    $seatsLeft = $table['capacity'] - $seatsTaken;
                    $capacity = $table['capacity'];
                ?>
                    <div class="table-card" data-table-id="<?= $table['id'] ?>">
                        <div class="table-card__header">
                            <h3 class="table-card__name"><?= htmlspecialchars($table['name']) ?></h3>
                            <span class="table-card__count <?= $seatsLeft === 0 ? 'full' : '' ?>">
                                <?= $seatsTaken ?>/<?= $capacity ?>
                            </span>
                        </div>

                        <div class="seats-grid">
                            <?php for ($seat = 1; $seat <= $capacity; $seat++):
                                $assignment = $seatAssignments[$seat] ?? null;
                                $isEmpty = !$assignment;
                            ?>
                                <div class="seat <?= $isEmpty ? 'seat--empty' : 'seat--occupied' ?>"
                                     data-seat-number="<?= $seat ?>"
                                     data-table-id="<?= $table['id'] ?>"
                                     <?php if ($assignment): ?>
                                     data-assignment-id="<?= $assignment['id'] ?>"
                                     data-guest-name="<?= htmlspecialchars($assignment['guest_name']) ?>"
                                     draggable="true"
                                     <?php endif; ?>
                                     ondragover="allowDrop(event)"
                                     ondrop="dropOnSeat(event, <?= $table['id'] ?>, <?= $seat ?>)">
                                    <span class="seat__number"><?= $seat ?></span>
                                    <?php if ($assignment): ?>
                                        <span class="seat__name"><?= htmlspecialchars($assignment['guest_name']) ?></span>
                                        <button type="button" class="seat__remove" onclick="removeGuest(<?= $assignment['id'] ?>)">&times;</button>
                                    <?php endif; ?>
                                </div>
                            <?php endfor; ?>
                        </div>

                        <div class="table-card__actions">
                            <button type="button" class="btn btn-ghost btn-sm" onclick='editTable(<?= json_encode($table) ?>)'>Rediger</button>
                            <button type="button" class="btn btn-ghost btn-sm danger" onclick="deleteTable(<?= $table['id'] ?>, '<?= htmlspecialchars(addslashes($table['name'])) ?>')">Slet</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Add Table Modal -->
<div class="modal-overlay" id="addTableModal" style="display: none;">
    <div class="modal" style="max-width: 400px;">
        <div class="modal-header">
            <h3>Tilfoj bord</h3>
            <button type="button" class="modal-close" onclick="hideAddTableModal()">&times;</button>
        </div>
        <form method="POST">
            <?= accountCsrfField() ?>
            <input type="hidden" name="action" value="add_table">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Bordnavn *</label>
                    <input type="text" name="name" class="form-input" required placeholder="F.eks. Bord 1">
                </div>
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select name="table_type" class="form-input">
                        <option value="round">Rundt bord</option>
                        <option value="rectangle">Rektangulaert</option>
                        <option value="square">Firkantet</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Antal pladser</label>
                    <input type="number" name="capacity" class="form-input" value="8" min="2" max="20">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideAddTableModal()">Annuller</button>
                <button type="submit" class="btn btn-primary">Tilfoj</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Table Modal -->
<div class="modal-overlay" id="editTableModal" style="display: none;">
    <div class="modal" style="max-width: 400px;">
        <div class="modal-header">
            <h3>Rediger bord</h3>
            <button type="button" class="modal-close" onclick="hideEditTableModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Bordnavn *</label>
                <input type="text" id="edit_table_name" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Antal pladser</label>
                <input type="number" id="edit_table_capacity" class="form-input" value="8" min="2" max="20">
            </div>
            <input type="hidden" id="edit_table_id">
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="hideEditTableModal()">Annuller</button>
            <button type="button" class="btn btn-primary" onclick="saveTableEdit()">Gem</button>
        </div>
    </div>
</div>

<!-- Auto Create Tables Modal -->
<div class="modal-overlay" id="autoModal" style="display: none;">
    <div class="modal" style="max-width: 400px;">
        <div class="modal-header">
            <h3>Auto-opret borde</h3>
            <button type="button" class="modal-close" onclick="hideAutoModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div style="background: #fef3c7; border: 1px solid #fcd34d; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px;">
                <strong>Advarsel:</strong> Dette sletter alle eksisterende borde og placeringer.
            </div>
            <div class="form-group">
                <label class="form-label">Bordtype</label>
                <select id="auto_table_type" class="form-input">
                    <option value="round">Rundt bord</option>
                    <option value="rectangle">Rektangulaert</option>
                    <option value="square">Firkantet</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Pladser per bord</label>
                <input type="number" id="auto_capacity" class="form-input" value="8" min="4" max="16">
            </div>
            <div class="form-group">
                <label class="form-label">Antal borde</label>
                <input type="number" id="auto_table_count" class="form-input" value="5" min="1" max="50">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="hideAutoModal()">Annuller</button>
            <button type="button" class="btn btn-primary" onclick="createTablesAuto()">Opret borde</button>
        </div>
    </div>
</div>

<style>
    .seating-layout {
        display: grid;
        grid-template-columns: 280px 1fr;
        gap: 24px;
    }

    .seating-sidebar {
        position: sticky;
        top: 24px;
        align-self: start;
    }

    .guest-pool {
        max-height: 60vh;
        overflow-y: auto;
    }

    .guest-chip {
        display: flex;
        flex-direction: column;
        padding: 10px 14px;
        background: var(--gray-50);
        border: 1px solid var(--gray-200);
        border-radius: 8px;
        margin-bottom: 8px;
        cursor: grab;
        transition: all 0.2s;
    }

    .guest-chip:hover {
        background: var(--gray-100);
        border-color: var(--gray-300);
    }

    .guest-chip:active {
        cursor: grabbing;
    }

    .guest-chip__name {
        font-weight: 500;
        font-size: 14px;
    }

    .guest-chip__info {
        font-size: 12px;
        color: var(--gray-500);
    }

    .tables-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
    }

    .table-card {
        background: white;
        border: 1px solid var(--gray-200);
        border-radius: 12px;
        padding: 16px;
    }

    .table-card__header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }

    .table-card__name {
        font-size: 16px;
        font-weight: 600;
        margin: 0;
    }

    .table-card__count {
        background: var(--gray-100);
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 500;
    }

    .table-card__count.full {
        background: #dcfce7;
        color: #15803d;
    }

    .seats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 8px;
        margin-bottom: 16px;
    }

    .seat {
        position: relative;
        aspect-ratio: 1;
        background: var(--gray-50);
        border: 2px dashed var(--gray-300);
        border-radius: 8px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        transition: all 0.2s;
        cursor: pointer;
        min-height: 60px;
    }

    .seat--empty:hover {
        border-color: var(--primary);
        background: rgba(102, 126, 234, 0.05);
    }

    .seat--occupied {
        background: white;
        border: 2px solid var(--primary);
        cursor: grab;
    }

    .seat--occupied:active {
        cursor: grabbing;
    }

    .seat__number {
        position: absolute;
        top: 4px;
        left: 6px;
        font-size: 10px;
        color: var(--gray-400);
        font-weight: 600;
    }

    .seat__name {
        font-size: 11px;
        text-align: center;
        padding: 4px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 100%;
    }

    .seat__remove {
        position: absolute;
        top: 2px;
        right: 2px;
        width: 18px;
        height: 18px;
        display: none;
        align-items: center;
        justify-content: center;
        background: #dc2626;
        color: white;
        border: none;
        border-radius: 50%;
        font-size: 14px;
        line-height: 1;
        cursor: pointer;
    }

    .seat:hover .seat__remove {
        display: flex;
    }

    .table-card__actions {
        display: flex;
        gap: 8px;
        padding-top: 12px;
        border-top: 1px solid var(--gray-100);
    }

    .btn-ghost {
        background: none;
        border: none;
        color: var(--gray-600);
        padding: 6px 12px;
        font-size: 13px;
        cursor: pointer;
        border-radius: 6px;
    }

    .btn-ghost:hover {
        background: var(--gray-100);
    }

    .btn-ghost.danger:hover {
        background: #fef2f2;
        color: #dc2626;
    }

    .btn-sm {
        padding: 6px 12px;
        font-size: 13px;
    }

    /* Drag styles */
    .seat.drag-over {
        border-color: #10b981;
        background: rgba(16, 185, 129, 0.1);
    }

    .dragging {
        opacity: 0.5;
    }

    @media (max-width: 768px) {
        .seating-layout {
            grid-template-columns: 1fr;
        }

        .seating-sidebar {
            position: static;
        }

        .seats-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }
</style>

<script>
const csrfToken = '<?= $csrfToken ?>';
const eventId = <?= $eventId ?>;
let draggedElement = null;

// Drag and drop
document.querySelectorAll('.guest-chip').forEach(chip => {
    chip.addEventListener('dragstart', function(e) {
        draggedElement = this;
        this.classList.add('dragging');
        e.dataTransfer.setData('text/plain', JSON.stringify({
            type: 'guest',
            name: this.dataset.guestName,
            guestId: this.dataset.guestId
        }));
    });

    chip.addEventListener('dragend', function() {
        this.classList.remove('dragging');
        draggedElement = null;
    });
});

document.querySelectorAll('.seat--occupied').forEach(seat => {
    seat.addEventListener('dragstart', function(e) {
        draggedElement = this;
        this.classList.add('dragging');
        e.dataTransfer.setData('text/plain', JSON.stringify({
            type: 'seated',
            assignmentId: this.dataset.assignmentId,
            name: this.dataset.guestName
        }));
    });

    seat.addEventListener('dragend', function() {
        this.classList.remove('dragging');
        draggedElement = null;
    });
});

function allowDrop(e) {
    e.preventDefault();
    e.currentTarget.classList.add('drag-over');
}

document.querySelectorAll('.seat').forEach(seat => {
    seat.addEventListener('dragleave', function() {
        this.classList.remove('drag-over');
    });
});

function dropOnSeat(e, tableId, seatNumber) {
    e.preventDefault();
    e.currentTarget.classList.remove('drag-over');

    const data = JSON.parse(e.dataTransfer.getData('text/plain'));

    if (data.type === 'guest') {
        // New guest from pool
        assignGuest(tableId, seatNumber, data.name, data.guestId);
    } else if (data.type === 'seated') {
        // Moving existing guest
        moveGuest(data.assignmentId, tableId, seatNumber);
    }
}

function assignGuest(tableId, seatNumber, guestName, guestId) {
    fetch('?id=' + eventId + '&page=seating', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': csrfToken
        },
        body: `action=assign_guest&table_id=${tableId}&seat_number=${seatNumber}&guest_name=${encodeURIComponent(guestName)}&guest_id=${guestId || ''}&csrf_token=${csrfToken}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Kunne ikke placere gaest');
        }
    });
}

function moveGuest(assignmentId, newTableId, newSeatNumber) {
    fetch('?id=' + eventId + '&page=seating', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': csrfToken
        },
        body: `action=move_guest&assignment_id=${assignmentId}&new_table_id=${newTableId}&seat_number=${newSeatNumber}&csrf_token=${csrfToken}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Kunne ikke flytte gaest');
        }
    });
}

function removeGuest(assignmentId) {
    fetch('?id=' + eventId + '&page=seating', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': csrfToken
        },
        body: `action=remove_guest&assignment_id=${assignmentId}&csrf_token=${csrfToken}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

function clearAllSeats() {
    if (!confirm('Er du sikker pa at du vil fjerne alle placeringer?')) return;

    fetch('?id=' + eventId + '&page=seating', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': csrfToken
        },
        body: `action=clear_all_seats&csrf_token=${csrfToken}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

function deleteTable(tableId, tableName) {
    if (!confirm(`Er du sikker pa at du vil slette ${tableName}?`)) return;

    fetch('?id=' + eventId + '&page=seating', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': csrfToken
        },
        body: `action=delete_table&table_id=${tableId}&csrf_token=${csrfToken}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

function showAddTableModal() {
    document.getElementById('addTableModal').style.display = 'flex';
}

function hideAddTableModal() {
    document.getElementById('addTableModal').style.display = 'none';
}

function editTable(table) {
    document.getElementById('edit_table_id').value = table.id;
    document.getElementById('edit_table_name').value = table.name;
    document.getElementById('edit_table_capacity').value = table.capacity;
    document.getElementById('editTableModal').style.display = 'flex';
}

function hideEditTableModal() {
    document.getElementById('editTableModal').style.display = 'none';
}

function saveTableEdit() {
    const tableId = document.getElementById('edit_table_id').value;
    const name = document.getElementById('edit_table_name').value;
    const capacity = document.getElementById('edit_table_capacity').value;

    fetch('?id=' + eventId + '&page=seating', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': csrfToken
        },
        body: `action=update_table&table_id=${tableId}&name=${encodeURIComponent(name)}&capacity=${capacity}&csrf_token=${csrfToken}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

function showAutoModal() {
    document.getElementById('autoModal').style.display = 'flex';
}

function hideAutoModal() {
    document.getElementById('autoModal').style.display = 'none';
}

function createTablesAuto() {
    const tableType = document.getElementById('auto_table_type').value;
    const capacity = document.getElementById('auto_capacity').value;
    const tableCount = document.getElementById('auto_table_count').value;

    if (!confirm('Dette vil slette alle eksisterende borde og placeringer. Fortsaet?')) return;

    fetch('?id=' + eventId + '&page=seating', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': csrfToken
        },
        body: `action=create_tables_auto&table_type=${tableType}&capacity=${capacity}&table_count=${tableCount}&csrf_token=${csrfToken}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

// Close modals on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
});
</script>

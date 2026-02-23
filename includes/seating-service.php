<?php
/**
 * Seating Service
 * Shared seating/bordplan functions used by both admin/bordplan.php and app/events/pages/seating.php
 */

/**
 * Add a new seating table
 *
 * @return int|false New table ID or false on failure
 */
function addSeatingTable(PDO $db, int $eventId, string $name, string $type = 'round', int $capacity = 8): int|false {
    $name = trim($name);
    if (empty($name)) return false;

    $capacity = max(2, min(20, $capacity));

    $stmt = $db->prepare("SELECT MAX(sort_order) FROM seating_tables WHERE event_id = ?");
    $stmt->execute([$eventId]);
    $maxOrder = $stmt->fetchColumn() ?? 0;

    $stmt = $db->prepare("
        INSERT INTO seating_tables (event_id, name, table_type, capacity, sort_order)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$eventId, $name, $type, $capacity, $maxOrder + 1]);

    return (int)$db->lastInsertId();
}

/**
 * Delete a seating table and all its assignments
 */
function deleteSeatingTable(PDO $db, int $eventId, int $tableId): bool {
    $db->prepare("DELETE FROM seating_assignments WHERE table_id = ? AND event_id = ?")->execute([$tableId, $eventId]);
    $stmt = $db->prepare("DELETE FROM seating_tables WHERE id = ? AND event_id = ?");
    $stmt->execute([$tableId, $eventId]);
    return $stmt->rowCount() > 0;
}

/**
 * Update a seating table's name and capacity
 */
function updateSeatingTable(PDO $db, int $eventId, int $tableId, string $name, int $capacity): bool {
    $name = trim($name);
    if (empty($name)) return false;

    $capacity = max(2, min(20, $capacity));
    $stmt = $db->prepare("UPDATE seating_tables SET name = ?, capacity = ? WHERE id = ? AND event_id = ?");
    $stmt->execute([$name, $capacity, $tableId, $eventId]);
    return $stmt->rowCount() > 0;
}

/**
 * Assign a guest to a seat
 *
 * @return int|false New assignment ID or false on failure
 */
function assignGuestToSeat(PDO $db, int $eventId, int $tableId, int $seatNumber, string $guestName, ?int $guestId = null): int|false {
    $guestName = trim($guestName);
    if (empty($guestName) || $seatNumber < 1) return false;

    // Check if seat is already taken
    $stmt = $db->prepare("SELECT id FROM seating_assignments WHERE table_id = ? AND seat_number = ?");
    $stmt->execute([$tableId, $seatNumber]);
    if ($stmt->fetch()) {
        return false;
    }

    $stmt = $db->prepare("
        INSERT INTO seating_assignments (event_id, table_id, guest_name, seat_number, guest_id)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$eventId, $tableId, $guestName, $seatNumber, $guestId]);

    return (int)$db->lastInsertId();
}

/**
 * Remove a guest from their seat
 */
function removeGuestFromSeat(PDO $db, int $eventId, int $assignmentId): bool {
    $stmt = $db->prepare("DELETE FROM seating_assignments WHERE id = ? AND event_id = ?");
    $stmt->execute([$assignmentId, $eventId]);
    return $stmt->rowCount() > 0;
}

/**
 * Move a guest to a different seat
 *
 * @return bool True on success, false if seat is occupied
 */
function moveGuestSeat(PDO $db, int $eventId, int $assignmentId, int $newTableId, int $newSeatNumber): bool {
    if ($newSeatNumber < 1) return false;

    // Check if destination seat is taken by someone else
    $stmt = $db->prepare("SELECT id FROM seating_assignments WHERE table_id = ? AND seat_number = ? AND id != ?");
    $stmt->execute([$newTableId, $newSeatNumber, $assignmentId]);
    if ($stmt->fetch()) {
        return false;
    }

    $stmt = $db->prepare("UPDATE seating_assignments SET table_id = ?, seat_number = ? WHERE id = ? AND event_id = ?");
    $stmt->execute([$newTableId, $newSeatNumber, $assignmentId, $eventId]);
    return $stmt->rowCount() > 0;
}

/**
 * Clear all seat assignments for an event
 */
function clearAllSeats(PDO $db, int $eventId): int {
    $stmt = $db->prepare("DELETE FROM seating_assignments WHERE event_id = ?");
    $stmt->execute([$eventId]);
    return $stmt->rowCount();
}

/**
 * Auto-create tables for an event
 *
 * @return int Number of tables created
 */
function createTablesAuto(PDO $db, int $eventId, string $tableType, int $capacity, int $tableCount): int {
    $capacity = max(4, min(16, $capacity));
    $tableCount = max(1, min(50, $tableCount));

    // Delete existing tables and assignments
    $db->prepare("DELETE FROM seating_assignments WHERE event_id = ?")->execute([$eventId]);
    $db->prepare("DELETE FROM seating_tables WHERE event_id = ?")->execute([$eventId]);

    $stmt = $db->prepare("
        INSERT INTO seating_tables (event_id, name, table_type, capacity, sort_order)
        VALUES (?, ?, ?, ?, ?)
    ");

    for ($i = 1; $i <= $tableCount; $i++) {
        $stmt->execute([$eventId, "Bord $i", $tableType, $capacity, $i]);
    }

    return $tableCount;
}

/**
 * Get all seating tables for an event
 */
function getSeatingTables(PDO $db, int $eventId): array {
    $stmt = $db->prepare("SELECT * FROM seating_tables WHERE event_id = ? ORDER BY sort_order");
    $stmt->execute([$eventId]);
    return $stmt->fetchAll();
}

/**
 * Get all seat assignments for an event
 */
function getSeatingAssignments(PDO $db, int $eventId): array {
    $stmt = $db->prepare("SELECT * FROM seating_assignments WHERE event_id = ?");
    $stmt->execute([$eventId]);
    return $stmt->fetchAll();
}

/**
 * Get confirmed guests with individual names for seating
 *
 * @return array List of individual guest names with metadata
 */
function getGuestNamesForSeating(PDO $db, int $eventId): array {
    $stmt = $db->prepare("
        SELECT g.id, g.name, g.guest_names, g.adults_count
        FROM guests g
        WHERE g.event_id = ? AND g.rsvp_status IN ('yes', 'accepted')
        ORDER BY g.name
    ");
    $stmt->execute([$eventId]);
    $confirmedGuests = $stmt->fetchAll();

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
            $allGuestNames[] = [
                'name' => $guest['name'],
                'guest_id' => $guest['id'],
                'invitation' => $guest['name']
            ];
        }
    }

    return $allGuestNames;
}

/**
 * Get seating statistics
 *
 * @return array ['total_seats' => int, 'assigned' => int, 'total_guests' => int, 'unassigned_guests' => array]
 */
function getSeatingStats(array $tables, array $assignments, array $allGuestNames): array {
    $assignedNames = array_column($assignments, 'guest_name');
    $unassigned = array_filter($allGuestNames, function($g) use ($assignedNames) {
        return !in_array($g['name'], $assignedNames);
    });

    return [
        'total_seats' => array_sum(array_column($tables, 'capacity')),
        'assigned' => count($assignments),
        'total_guests' => count($allGuestNames),
        'unassigned_guests' => array_values($unassigned),
    ];
}

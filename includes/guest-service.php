<?php
/**
 * Guest Service
 * Shared guest management functions used by both admin/guests.php and app/events/pages/guests.php
 */

require_once __DIR__ . '/functions.php';

/**
 * Generate a unique guest code for an event
 */
function generateUniqueGuestCode(PDO $db, int $eventId, int $maxAttempts = 10): string {
    $code = generateGuestCode();
    $stmt = $db->prepare("SELECT id FROM guests WHERE event_id = ? AND unique_code = ?");

    for ($i = 0; $i < $maxAttempts; $i++) {
        $stmt->execute([$eventId, $code]);
        if (!$stmt->fetch()) {
            return $code;
        }
        $code = generateGuestCode();
    }

    return $code;
}

/**
 * Add a single guest to an event
 *
 * @return array ['success' => bool, 'id' => int|null, 'code' => string|null, 'error' => string|null]
 */
function addGuest(PDO $db, int $eventId, array $data): array {
    $name = trim($data['name'] ?? '');
    if (empty($name)) {
        return ['success' => false, 'error' => 'Navn er påkrævet'];
    }

    $code = generateUniqueGuestCode($db, $eventId);

    $stmt = $db->prepare("
        INSERT INTO guests (event_id, name, email, phone, notes, unique_code, rsvp_status, max_guests, dietary_notes)
        VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?)
    ");
    $stmt->execute([
        $eventId,
        $name,
        !empty($data['email']) ? trim($data['email']) : null,
        !empty($data['phone']) ? trim($data['phone']) : null,
        !empty($data['notes']) ? trim($data['notes']) : null,
        $code,
        max(1, (int)($data['max_guests'] ?? 1)),
        !empty($data['dietary_notes']) ? trim($data['dietary_notes']) : null,
    ]);

    return [
        'success' => true,
        'id' => (int)$db->lastInsertId(),
        'code' => $code,
    ];
}

/**
 * Bulk add guests from a newline-separated list
 * Supports "Name (N)" format for max_guests
 *
 * @return array ['added' => int, 'errors' => string[]]
 */
function bulkAddGuests(PDO $db, int $eventId, string $nameList, int $defaultMaxGuests = 1): array {
    $lines = array_filter(array_map('trim', explode("\n", $nameList)));
    $added = 0;
    $errors = [];

    foreach ($lines as $line) {
        if (empty($line)) continue;

        $maxGuests = $defaultMaxGuests;
        $name = $line;
        if (preg_match('/^(.+?)\s*\((\d+)\)\s*$/', $line, $matches)) {
            $name = trim($matches[1]);
            $maxGuests = max(1, (int)$matches[2]);
        }

        $code = generateUniqueGuestCode($db, $eventId);

        $stmt = $db->prepare("
            INSERT INTO guests (event_id, name, max_guests, unique_code, rsvp_status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$eventId, $name, $maxGuests, $code]);
        $added++;
    }

    return ['added' => $added, 'errors' => $errors];
}

/**
 * Import guests from parsed CSV data with column mapping
 *
 * @param array $rows Decoded CSV rows (array of arrays)
 * @param array $mapping Column index => field name mapping
 * @param bool $skipDuplicates Skip rows matching existing email or name
 * @return array ['added' => int, 'skipped' => int, 'errors' => string[]]
 */
function importGuestsFromCsv(PDO $db, int $eventId, array $rows, array $mapping, bool $skipDuplicates = false): array {
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

            if (empty($name)) {
                $errors[] = "Række " . ($index + 2) . ": Mangler navn";
                continue;
            }

            if ($skipDuplicates) {
                if (($email && isset($existingEmails[strtolower($email)])) ||
                    isset($existingNames[strtolower($name)])) {
                    $skipped++;
                    continue;
                }
            }

            if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Række " . ($index + 2) . ": Ugyldig email '$email'";
                $email = '';
            }

            $code = generateUniqueGuestCode($db, $eventId);

            $stmt = $db->prepare("
                INSERT INTO guests (event_id, name, max_guests, email, phone, notes, unique_code, rsvp_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([
                $eventId, $name, $maxGuests,
                $email ?: null, $phone ?: null, $notes ?: null,
                $code
            ]);

            if ($email) $existingEmails[strtolower($email)] = true;
            $existingNames[strtolower($name)] = true;
            $added++;
        }
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        error_log('CSV guest import failed: ' . $e->getMessage());
        return ['added' => 0, 'skipped' => 0, 'errors' => ['Import fejlede: ' . $e->getMessage()]];
    }

    return ['added' => $added, 'skipped' => $skipped, 'errors' => $errors];
}

/**
 * Update a guest record
 */
function updateGuest(PDO $db, int $eventId, int $guestId, array $data): bool {
    $name = trim($data['name'] ?? '');
    if (empty($name)) return false;

    $sql = "UPDATE guests SET name = ?, max_guests = ?, email = ?, phone = ?, notes = ?";
    $params = [
        $name,
        max(1, (int)($data['max_guests'] ?? 1)),
        !empty($data['email']) ? trim($data['email']) : null,
        !empty($data['phone']) ? trim($data['phone']) : null,
        !empty($data['notes']) ? trim($data['notes']) : null,
    ];

    $rsvpStatus = $data['rsvp_status'] ?? null;
    if ($rsvpStatus && in_array($rsvpStatus, ['pending', 'yes', 'no'])) {
        // Collect guest names
        $guestNames = [];
        if ($rsvpStatus === 'yes' && isset($data['guest_names']) && is_array($data['guest_names'])) {
            foreach ($data['guest_names'] as $gn) {
                $gn = trim($gn);
                if (!empty($gn)) $guestNames[] = $gn;
            }
        }
        $guestNamesJson = !empty($guestNames) ? json_encode($guestNames, JSON_UNESCAPED_UNICODE) : null;

        $dietaryNotes = trim($data['dietary_notes'] ?? '');

        $sql .= ", rsvp_status = ?, adults_count = ?, children_count = ?, guest_names = ?, dietary_notes = ?";
        $params[] = $rsvpStatus;
        $params[] = $rsvpStatus === 'yes' ? (int)($data['adults_count'] ?? 1) : 0;
        $params[] = $rsvpStatus === 'yes' ? (int)($data['children_count'] ?? 0) : 0;
        $params[] = $rsvpStatus === 'yes' ? $guestNamesJson : null;
        $params[] = $rsvpStatus === 'yes' ? ($dietaryNotes ?: null) : null;

        if ($rsvpStatus !== 'pending') {
            $sql .= ", rsvp_date = NOW()";
        }
    }

    $sql .= " WHERE id = ? AND event_id = ?";
    $params[] = $guestId;
    $params[] = $eventId;

    $stmt = $db->prepare($sql);
    return $stmt->execute($params);
}

/**
 * Delete a guest
 */
function deleteGuest(PDO $db, int $eventId, int $guestId): bool {
    $stmt = $db->prepare("DELETE FROM guests WHERE id = ? AND event_id = ?");
    $stmt->execute([$guestId, $eventId]);
    return $stmt->rowCount() > 0;
}

/**
 * Toggle invitation sent status
 *
 * @return bool|null New invitation_sent state, or null on failure
 */
function toggleInvitationStatus(PDO $db, int $eventId, int $guestId): ?bool {
    $stmt = $db->prepare("
        UPDATE guests
        SET invitation_sent = NOT COALESCE(invitation_sent, 0),
            invitation_sent_at = CASE WHEN COALESCE(invitation_sent, 0) = 0 THEN NOW() ELSE NULL END
        WHERE id = ? AND event_id = ?
    ");
    $stmt->execute([$guestId, $eventId]);

    if ($stmt->rowCount() === 0) return null;

    $stmt = $db->prepare("SELECT invitation_sent FROM guests WHERE id = ? AND event_id = ?");
    $stmt->execute([$guestId, $eventId]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Get guests with optional filtering and search
 */
function getFilteredGuests(PDO $db, int $eventId, string $filter = 'all', string $search = ''): array {
    $sql = "SELECT * FROM guests WHERE event_id = ?";
    $params = [$eventId];

    switch ($filter) {
        case 'yes':
            $sql .= " AND rsvp_status = 'yes'";
            break;
        case 'no':
            $sql .= " AND rsvp_status = 'no'";
            break;
        case 'pending':
            $sql .= " AND rsvp_status = 'pending'";
            break;
        case 'not_sent':
        case 'not_invited':
            $sql .= " AND (invitation_sent = 0 OR invitation_sent IS NULL)";
            break;
        case 'sent':
        case 'invited':
            $sql .= " AND invitation_sent = 1";
            break;
    }

    if ($search) {
        $sql .= " AND (name LIKE ? OR email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $sql .= " ORDER BY name ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get invitation counts for an event
 *
 * @return array ['sent' => int, 'not_sent' => int]
 */
function getInvitationCounts(PDO $db, int $eventId): array {
    $stmt = $db->prepare("
        SELECT
            SUM(CASE WHEN invitation_sent = 1 THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN invitation_sent = 0 OR invitation_sent IS NULL THEN 1 ELSE 0 END) as not_sent
        FROM guests WHERE event_id = ?
    ");
    $stmt->execute([$eventId]);
    $row = $stmt->fetch();
    return [
        'sent' => (int)($row['sent'] ?? 0),
        'not_sent' => (int)($row['not_sent'] ?? 0),
    ];
}

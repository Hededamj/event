<?php
require_once __DIR__ . '/config/database.php';

echo "<pre>";
$db = getDB();

// Find guest - show all details
$search = $_GET['search'] ?? 'Jacob';
$stmt = $db->prepare("SELECT * FROM guests WHERE name LIKE ?");
$stmt->execute(["%$search%"]);
$guests = $stmt->fetchAll();

echo "=== GÆSTER MED '$search' I NAVNET ===\n\n";
foreach ($guests as $g) {
    echo "ID: {$g['id']}\n";
    echo "Navn: {$g['name']}\n";
    echo "Event ID: {$g['event_id']}\n";
    echo "RSVP Status: {$g['rsvp_status']}\n";
    echo "Kode: {$g['unique_code']}\n";
    echo "Adults: {$g['adults_count']}, Children: {$g['children_count']}\n";
    echo "Guest Names: {$g['guest_names']}\n";
    echo "RSVP responded at: {$g['rsvp_responded_at']}\n";
    echo str_repeat("-", 50) . "\n\n";
}

// Show all events
echo "\n=== ALLE EVENTS ===\n\n";
$stmt = $db->query("SELECT id, name, slug FROM events");
foreach ($stmt->fetchAll() as $e) {
    echo "Event ID {$e['id']}: {$e['name']} (slug: {$e['slug']})\n";
}

// Actions
if (isset($_GET['reset'])) {
    $id = (int)$_GET['reset'];
    $stmt = $db->prepare("UPDATE guests SET rsvp_status = 'pending' WHERE id = ?");
    $stmt->execute([$id]);
    echo "\n\n*** Status nulstillet for gæst ID $id ***\n";
}

if (isset($_GET['accept'])) {
    $id = (int)$_GET['accept'];
    $stmt = $db->prepare("UPDATE guests SET rsvp_status = 'accepted', adults_count = 1 WHERE id = ?");
    $stmt->execute([$id]);
    echo "\n\n*** Gæst ID $id sat til ACCEPTED ***\n";
}

if (isset($_GET['fix_event'])) {
    $guestId = (int)$_GET['fix_event'];
    $eventId = (int)$_GET['event_id'];
    $stmt = $db->prepare("UPDATE guests SET event_id = ? WHERE id = ?");
    $stmt->execute([$eventId, $guestId]);
    echo "\n\n*** Gæst ID $guestId sat til Event ID $eventId ***\n";
}

echo "\n\n=== HANDLINGER ===\n";
foreach ($guests as $g) {
    echo "<a href='?reset={$g['id']}'>Nulstil {$g['name']}</a> | ";
    echo "<a href='?accept={$g['id']}'>Sæt til ACCEPTED</a> | ";
    echo "<a href='?fix_event={$g['id']}&event_id=3'>Flyt til Event 3</a>\n";
}
echo "</pre>";

<?php
require_once __DIR__ . '/config/database.php';

echo "<pre>";
$db = getDB();

echo "=== GUESTS TABLE STRUCTURE ===\n\n";
$stmt = $db->query("DESCRIBE guests");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['Field']}: {$row['Type']} (Null: {$row['Null']}, Default: {$row['Default']})\n";
}

echo "\n\n=== GUEST ID 1 DATA ===\n\n";
$stmt = $db->query("SELECT * FROM guests WHERE id = 1");
$guest = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($guest);

echo "\n\n=== TEST UPDATE ===\n\n";
$db->exec("UPDATE guests SET rsvp_status = 'accepted' WHERE id = 1");
echo "Ran: UPDATE guests SET rsvp_status = 'accepted' WHERE id = 1\n\n";

$stmt = $db->query("SELECT id, name, rsvp_status, event_id FROM guests WHERE id = 1");
$guest = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Result after update:\n";
print_r($guest);

echo "</pre>";

<?php
/**
 * Delete all guests and wishlist - DELETE AFTER USE
 */
require_once __DIR__ . '/config/database.php';

try {
    $db = getDB();

    // Get event ID
    $stmt = $db->query("SELECT id FROM events LIMIT 1");
    $event = $stmt->fetch();

    if ($event) {
        // Delete guests
        $stmt = $db->prepare("DELETE FROM guests WHERE event_id = ?");
        $stmt->execute([$event['id']]);
        $deletedGuests = $stmt->rowCount();

        // Delete wishlist items
        $stmt = $db->prepare("DELETE FROM wishlist_items WHERE event_id = ?");
        $stmt->execute([$event['id']]);
        $deletedWishes = $stmt->rowCount();

        // Delete checklist items
        $stmt = $db->prepare("DELETE FROM checklist_items WHERE event_id = ?");
        $stmt->execute([$event['id']]);
        $deletedChecklist = $stmt->rowCount();

        echo "<p style='color:green'>✓ $deletedGuests gæster slettet!</p>";
        echo "<p style='color:green'>✓ $deletedWishes ønsker slettet!</p>";
        echo "<p style='color:green'>✓ $deletedChecklist huskeliste-punkter slettet!</p>";
    } else {
        echo "<p>Ingen event fundet</p>";
    }

    echo "<p><a href='/sofie/admin/guests.php'>Gå til gæstelisten</a></p>";
    echo "<p style='color:red'><strong>SLET DENNE FIL!</strong></p>";

} catch (PDOException $e) {
    echo "<p style='color:red'>Fejl: " . htmlspecialchars($e->getMessage()) . "</p>";
}

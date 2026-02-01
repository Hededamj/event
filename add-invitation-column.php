<?php
/**
 * Add invitation_sent column to guests table
 * DELETE AFTER USE
 */
require_once __DIR__ . '/config/database.php';

try {
    $db = getDB();

    // Check if column exists
    $stmt = $db->query("SHOW COLUMNS FROM guests LIKE 'invitation_sent'");
    if ($stmt->fetch()) {
        echo "<p>Kolonnen 'invitation_sent' findes allerede.</p>";
    } else {
        $db->exec("ALTER TABLE guests ADD COLUMN invitation_sent TINYINT(1) DEFAULT 0 AFTER unique_code");
        $db->exec("ALTER TABLE guests ADD COLUMN invitation_sent_at DATETIME NULL AFTER invitation_sent");
        echo "<p style='color:green'>✓ Kolonne 'invitation_sent' tilføjet!</p>";
    }

    echo "<p><a href='/sofie/admin/guests.php'>Gå til gæstelisten</a></p>";
    echo "<p style='color:red'><strong>SLET DENNE FIL!</strong></p>";

} catch (PDOException $e) {
    echo "<p style='color:red'>Fejl: " . htmlspecialchars($e->getMessage()) . "</p>";
}

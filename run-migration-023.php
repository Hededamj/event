<?php
/**
 * Run migration 023: Registration mode + self-registered guests
 * Execute once, then delete from server.
 */
require_once __DIR__ . '/config/database.php';

echo "<pre>\n";
echo "=== Migration 023: Registration Mode ===\n\n";

try {
    $db = getDB();

    $statements = [
        "ALTER TABLE events ADD COLUMN registration_mode ENUM('invite', 'open') DEFAULT 'invite' AFTER status",
        "ALTER TABLE guests ADD COLUMN self_registered TINYINT(1) NOT NULL DEFAULT 0 AFTER notes",
    ];

    foreach ($statements as $i => $sql) {
        try {
            $db->exec($sql);
            echo "Statement " . ($i + 1) . ": OK\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "Statement " . ($i + 1) . ": SKIPPED (already exists)\n";
            } else {
                echo "Statement " . ($i + 1) . ": ERROR - " . $e->getMessage() . "\n";
            }
        }
    }

    echo "\nMigration 023 complete!\n";
} catch (Exception $e) {
    echo "FATAL: " . $e->getMessage() . "\n";
}
echo "</pre>";

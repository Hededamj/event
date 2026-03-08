<?php
/**
 * Run vendor/subcontractor migrations: 015, 016, 019
 * DELETE THIS FILE AFTER RUNNING
 *
 * Usage: ?run=yes (or ?run=yes&skip016=1 to skip dropping old partner tables)
 */
if (!isset($_GET['run']) || $_GET['run'] !== 'yes') {
    die('Add ?run=yes to execute. Add &skip016=1 to skip dropping old partner tables.');
}

require_once __DIR__ . '/config/database.php';
$db = getDB();

$skip016 = isset($_GET['skip016']);

echo "<pre>\n";
echo "=== Vendor/Subcontractor Migration Runner ===\n\n";

// ── Migration 015: Full subcontractor schema ──
echo "--- Migration 015: Subcontractor Module ---\n";
$sql015 = file_get_contents(__DIR__ . '/database/migrations/015_subcontractor_module.sql');
if (!$sql015) {
    die("ERROR: Could not read 015_subcontractor_module.sql\n");
}

// Split by statement (handle multi-statement SQL)
$statements = array_filter(array_map('trim', explode(';', $sql015)));
$ok = 0;
$skip = 0;
foreach ($statements as $i => $stmt) {
    if (empty($stmt) || strpos($stmt, '--') === 0) {
        continue;
    }
    try {
        $db->exec($stmt);
        $ok++;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false || strpos($e->getMessage(), 'Duplicate') !== false) {
            $skip++;
            echo "  Step " . ($i + 1) . ": SKIPPED (already exists)\n";
        } else {
            echo "  Step " . ($i + 1) . ": ERROR - " . $e->getMessage() . "\n";
            echo "  SQL: " . substr($stmt, 0, 120) . "...\n";
        }
    }
}
echo "  015 complete: $ok OK, $skip skipped\n\n";

// Verify tables
$tables = ['vendor_categories', 'vendors', 'vendor_category_links', 'vendor_services',
           'vendor_gallery', 'bookings', 'booking_messages', 'vendor_reviews',
           'manual_vendors', 'refund_requests'];
echo "  Verifying tables:\n";
foreach ($tables as $table) {
    try {
        $count = $db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        echo "    $table: OK ($count rows)\n";
    } catch (PDOException $e) {
        echo "    $table: MISSING!\n";
    }
}

// ── Migration 016: Drop old partner tables (optional) ──
echo "\n--- Migration 016: Drop Old Partner Tables ---\n";
if ($skip016) {
    echo "  SKIPPED (skip016 flag set)\n";
} else {
    $sql016 = file_get_contents(__DIR__ . '/database/migrations/016_drop_old_partner_tables.sql');
    if ($sql016) {
        $statements016 = array_filter(array_map('trim', explode(';', $sql016)));
        foreach ($statements016 as $stmt) {
            if (empty($stmt) || strpos($stmt, '--') === 0) continue;
            try {
                $db->exec($stmt);
                echo "  OK: " . substr($stmt, 0, 80) . "\n";
            } catch (PDOException $e) {
                echo "  NOTE: " . $e->getMessage() . "\n";
            }
        }
        echo "  016 complete\n";
    } else {
        echo "  ERROR: Could not read 016_drop_old_partner_tables.sql\n";
    }
}

// ── Migration 019: Vendor onboarding flag ──
echo "\n--- Migration 019: Vendor Onboarding ---\n";
$sql019 = file_get_contents(__DIR__ . '/database/migrations/019_vendor_onboarding.sql');
if ($sql019) {
    $statements019 = array_filter(array_map('trim', explode(';', $sql019)));
    foreach ($statements019 as $stmt) {
        if (empty($stmt) || strpos($stmt, '--') === 0) continue;
        try {
            $db->exec($stmt);
            echo "  OK: " . substr($stmt, 0, 80) . "\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "  SKIPPED: Column already exists\n";
            } else {
                echo "  ERROR: " . $e->getMessage() . "\n";
            }
        }
    }
    echo "  019 complete\n";
} else {
    echo "  ERROR: Could not read 019_vendor_onboarding.sql\n";
}

// ── Webhook idempotency table ──
echo "\n--- Webhook Idempotency Table ---\n";
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS processed_webhooks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            stripe_event_id VARCHAR(255) NOT NULL UNIQUE,
            event_type VARCHAR(100),
            processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_processed_at (processed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  OK: processed_webhooks table created\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo "  SKIPPED: Table already exists\n";
    } else {
        echo "  ERROR: " . $e->getMessage() . "\n";
    }
}

echo "\n=== All migrations complete ===\n";
echo "</pre>\n";
echo "<p><strong>DELETE THIS FILE NOW: run-migration-vendor.php</strong></p>\n";

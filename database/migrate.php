<?php
/**
 * Database Migration Runner
 *
 * Usage: php database/migrate.php [options]
 *
 * Options:
 *   --dry-run    Show which migrations would run without executing
 *   --force      Run all migrations even if already executed
 *   --single=N   Run only migration number N (e.g., --single=007)
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from CLI');
}

require_once __DIR__ . '/../config/database.php';

$migrationsDir = __DIR__ . '/migrations';
$dryRun = in_array('--dry-run', $argv);
$force = in_array('--force', $argv);
$single = null;

// Check for --single option
foreach ($argv as $arg) {
    if (strpos($arg, '--single=') === 0) {
        $single = substr($arg, 9);
        break;
    }
}

echo "===========================================\n";
echo "  Partypart Database Migration Runner\n";
echo "===========================================\n\n";

if ($dryRun) {
    echo "[DRY RUN MODE - No changes will be made]\n\n";
}

// Ensure migrations table exists
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS _migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Exception $e) {
    die("Error creating migrations table: " . $e->getMessage() . "\n");
}

// Get executed migrations
$executed = [];
if (!$force) {
    $stmt = $db->query("SELECT migration FROM _migrations");
    $executed = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Get migration files
$files = glob($migrationsDir . '/*.sql');
sort($files);

if (empty($files)) {
    echo "No migration files found.\n";
    exit(0);
}

// Filter for single migration if specified
if ($single !== null) {
    $files = array_filter($files, function($file) use ($single) {
        return strpos(basename($file), $single . '_') === 0;
    });
    if (empty($files)) {
        echo "Migration $single not found.\n";
        exit(1);
    }
}

$pending = [];
$skipped = [];

foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $executed)) {
        $skipped[] = $name;
    } else {
        $pending[] = $file;
    }
}

echo "Migrations status:\n";
echo "  - Already executed: " . count($skipped) . "\n";
echo "  - Pending: " . count($pending) . "\n\n";

if (empty($pending)) {
    echo "All migrations have been executed.\n";
    exit(0);
}

echo "Pending migrations:\n";
foreach ($pending as $file) {
    echo "  - " . basename($file) . "\n";
}
echo "\n";

if ($dryRun) {
    echo "[DRY RUN] Would execute " . count($pending) . " migrations.\n";
    exit(0);
}

// Execute pending migrations
$success = 0;
$failed = 0;

foreach ($pending as $file) {
    $name = basename($file);
    echo "Running: $name ... ";

    $sql = file_get_contents($file);

    if (empty(trim($sql))) {
        echo "SKIP (empty)\n";
        continue;
    }

    $db->beginTransaction();

    try {
        // Split by semicolon but be careful with stored procedures
        $statements = array_filter(
            array_map('trim', preg_split('/;\s*$/m', $sql)),
            function($s) { return !empty($s); }
        );

        foreach ($statements as $statement) {
            if (!empty(trim($statement))) {
                $db->exec($statement);
            }
        }

        // Record migration
        $stmt = $db->prepare("INSERT INTO _migrations (migration) VALUES (?)");
        $stmt->execute([$name]);

        $db->commit();
        echo "OK\n";
        $success++;

    } catch (Exception $e) {
        $db->rollBack();
        echo "FAILED\n";
        echo "  Error: " . $e->getMessage() . "\n";
        $failed++;

        // Ask to continue or abort
        echo "  Continue with next migration? (y/n): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);

        if (trim(strtolower($line)) !== 'y') {
            echo "\nMigration aborted.\n";
            break;
        }
    }
}

echo "\n===========================================\n";
echo "Migration complete!\n";
echo "  - Successful: $success\n";
echo "  - Failed: $failed\n";
echo "===========================================\n";

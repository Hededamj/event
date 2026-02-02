<?php
/**
 * Migration Runner for 010_app_features.sql
 * Run this once to add all new columns and tables
 */

require_once __DIR__ . '/config/database.php';

echo "<pre style='font-family: monospace; padding: 20px;'>\n";
echo "===========================================\n";
echo "Running Migration: 010_app_features.sql\n";
echo "===========================================\n\n";

try {
    $db = getDB();

    // Read and execute migration file
    $migrationFile = __DIR__ . '/database/migrations/010_app_features.sql';

    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: $migrationFile");
    }

    $sql = file_get_contents($migrationFile);

    // Split by semicolon (but be careful with stored procedures)
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    $successCount = 0;
    $skipCount = 0;
    $errorCount = 0;

    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }

        // Skip comments
        $statement = preg_replace('/--.*$/m', '', $statement);
        $statement = trim($statement);

        if (empty($statement)) {
            continue;
        }

        try {
            $db->exec($statement);

            // Determine what was done
            if (stripos($statement, 'CREATE TABLE') !== false) {
                preg_match('/CREATE TABLE IF NOT EXISTS\s+(\w+)/i', $statement, $matches);
                $table = $matches[1] ?? 'unknown';
                echo "[OK] Created table: $table\n";
            } elseif (stripos($statement, 'ALTER TABLE') !== false) {
                preg_match('/ALTER TABLE\s+(\w+)\s+ADD COLUMN.*?(\w+)\s/i', $statement, $matches);
                $table = $matches[1] ?? 'unknown';
                $column = $matches[2] ?? 'column';
                echo "[OK] Added column $column to $table\n";
            } elseif (stripos($statement, 'UPDATE plans') !== false) {
                echo "[OK] Updated plan features\n";
            } else {
                echo "[OK] Executed statement\n";
            }

            $successCount++;
        } catch (PDOException $e) {
            // Check if it's a "already exists" error - that's OK
            if (strpos($e->getMessage(), 'Duplicate column') !== false ||
                strpos($e->getMessage(), 'already exists') !== false) {
                echo "[SKIP] Already exists - " . substr($statement, 0, 60) . "...\n";
                $skipCount++;
            } else {
                echo "[ERROR] " . $e->getMessage() . "\n";
                echo "        Statement: " . substr($statement, 0, 80) . "...\n";
                $errorCount++;
            }
        }
    }

    echo "\n===========================================\n";
    echo "Migration Complete!\n";
    echo "===========================================\n";
    echo "Success: $successCount\n";
    echo "Skipped: $skipCount\n";
    echo "Errors:  $errorCount\n";
    echo "\n";

    // Verify tables exist
    echo "Verifying tables...\n";
    $tables = ['seating_tables', 'seating_assignments', 'toastmaster_items', 'toastmaster_access', 'budget_items'];

    foreach ($tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        if ($stmt->fetch()) {
            echo "[OK] Table '$table' exists\n";
        } else {
            echo "[MISSING] Table '$table' NOT found!\n";
        }
    }

    // Verify guest columns
    echo "\nVerifying guest columns...\n";
    $stmt = $db->query("DESCRIBE guests");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $expectedColumns = ['max_guests', 'guest_names', 'dietary_notes', 'internal_notes', 'invitation_sent', 'invitation_sent_at'];
    foreach ($expectedColumns as $col) {
        if (in_array($col, $columns)) {
            echo "[OK] Column 'guests.$col' exists\n";
        } else {
            echo "[MISSING] Column 'guests.$col' NOT found!\n";
        }
    }

    echo "\n</pre>";

    // Add delete button
    echo "<br><form method='post' style='padding: 20px;'>";
    echo "<input type='hidden' name='delete_self' value='1'>";
    echo "<button type='submit' style='background: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;'>Delete this migration script</button>";
    echo "</form>";

    // Handle self-deletion
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_self'])) {
        unlink(__FILE__);
        echo "<script>alert('Migration script deleted!'); window.location.href = '/app/dashboard.php';</script>";
    }

} catch (Exception $e) {
    echo "[FATAL ERROR] " . $e->getMessage() . "\n";
    echo "</pre>";
}

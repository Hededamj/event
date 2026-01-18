<?php
/**
 * Database Setup Script
 * Run this ONCE to create tables and seed data
 * DELETE THIS FILE after setup!
 */

require_once __DIR__ . '/config/database.php';

echo "<h1>Event Platform Setup</h1>";

try {
    $db = getDB();
    echo "<p style='color: green;'>✓ Database forbindelse OK</p>";

    // Read and execute schema
    $schema = file_get_contents(__DIR__ . '/database/schema.sql');
    $statements = array_filter(array_map('trim', explode(';', $schema)));

    $tableCount = 0;
    foreach ($statements as $sql) {
        if (!empty($sql) && stripos($sql, 'CREATE TABLE') !== false) {
            $db->exec($sql);
            $tableCount++;
        }
    }
    echo "<p style='color: green;'>✓ $tableCount tabeller oprettet</p>";

    // Read and execute seed
    $seed = file_get_contents(__DIR__ . '/database/seed.sql');
    $statements = array_filter(array_map('trim', explode(';', $seed)));

    foreach ($statements as $sql) {
        if (!empty($sql) && strlen($sql) > 10) {
            try {
                $db->exec($sql);
            } catch (PDOException $e) {
                // Ignore duplicate entry errors for seed data
                if (strpos($e->getMessage(), 'Duplicate') === false) {
                    echo "<p style='color: orange;'>Advarsel: " . htmlspecialchars($e->getMessage()) . "</p>";
                }
            }
        }
    }
    echo "<p style='color: green;'>✓ Test-data indsat</p>";

    echo "<hr>";
    echo "<h2>Setup fuldført!</h2>";
    echo "<p><strong>Login:</strong></p>";
    echo "<ul>";
    echo "<li>Arrangør: admin@example.com / password</li>";
    echo "<li>Gæst: Brug kode 123456</li>";
    echo "</ul>";
    echo "<p><a href='/sofie/'>Gå til forsiden</a></p>";
    echo "<p style='color: red; font-weight: bold;'>VIGTIGT: Slet denne fil (setup.php) efter brug!</p>";

} catch (PDOException $e) {
    echo "<p style='color: red;'>FEJL: " . htmlspecialchars($e->getMessage()) . "</p>";
}

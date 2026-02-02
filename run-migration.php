<?php
/**
 * Database Migration Runner
 * Kør denne fil i browseren: /database/run-migration.php
 * SLET FILEN EFTER BRUG!
 */

// Sikkerhedstjek - kræver ?run=yes parameter
if (!isset($_GET['run']) || $_GET['run'] !== 'yes') {
    echo "<h1>Database Migration Runner</h1>";
    echo "<p>Denne fil kører database migrations for invitation systemet.</p>";
    echo "<p><strong>Migrations der vil blive kørt:</strong></p>";
    echo "<ul>";
    echo "<li>011_invitation_system.sql - Opretter tabeller</li>";
    echo "<li>invitation-templates.sql - Indsætter skabeloner</li>";
    echo "</ul>";
    echo "<p style='color: red;'><strong>VIGTIGT:</strong> Slet denne fil efter brug!</p>";
    echo "<p><a href='?run=yes' style='display:inline-block; padding:12px 24px; background:#8FA583; color:white; text-decoration:none; border-radius:8px;'>Kør migrations nu</a></p>";
    exit;
}

require_once __DIR__ . '/config/saas.php';

$db = getDB();

echo "<pre style='font-family: monospace; background: #1a1a1a; color: #fff; padding: 20px; border-radius: 8px;'>";
echo "=== Database Migration Runner ===\n\n";

$migrations = [
    'database/migrations/011_invitation_system.sql' => 'Invitation System Schema',
    'database/seeds/invitation-templates.sql' => 'Invitation Templates Seed'
];

$success = 0;
$failed = 0;

foreach ($migrations as $file => $name) {
    $path = __DIR__ . '/' . $file;

    echo "▶ Kører: <strong>$name</strong>\n";
    echo "  Fil: $file\n";

    if (!file_exists($path)) {
        echo "  <span style='color: #ff6b6b;'>✗ FEJL: Fil ikke fundet!</span>\n\n";
        $failed++;
        continue;
    }

    $sql = file_get_contents($path);

    // Split SQL into individual statements
    // Remove comments and split by semicolon
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        fn($s) => !empty($s) && !preg_match('/^--/', trim($s))
    );

    $stmtCount = 0;
    $errors = [];

    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }

        try {
            $db->exec($statement);
            $stmtCount++;
        } catch (PDOException $e) {
            // Ignore "already exists" errors for CREATE TABLE IF NOT EXISTS
            if (strpos($e->getMessage(), 'already exists') === false &&
                strpos($e->getMessage(), 'Duplicate') === false) {
                $errors[] = $e->getMessage();
            } else {
                $stmtCount++; // Count as success if it's just "already exists"
            }
        }
    }

    if (empty($errors)) {
        echo "  <span style='color: #4ecdc4;'>✓ SUCCESS: $stmtCount statements kørt</span>\n\n";
        $success++;
    } else {
        echo "  <span style='color: #ffe66d;'>⚠ DELVIST: $stmtCount OK, " . count($errors) . " fejl</span>\n";
        foreach ($errors as $err) {
            echo "    - " . htmlspecialchars(substr($err, 0, 100)) . "\n";
        }
        echo "\n";
        $failed++;
    }
}

echo "=================================\n";
echo "Resultat: <strong style='color: #4ecdc4;'>$success OK</strong>";
if ($failed > 0) {
    echo " / <strong style='color: #ff6b6b;'>$failed fejlet</strong>";
}
echo "\n\n";

if ($success > 0) {
    echo "<span style='color: #4ecdc4;'>✓ Migration færdig!</span>\n\n";
    echo "Næste trin:\n";
    echo "1. Slet denne fil (run-migration.php)\n";
    echo "2. Gå til /app/events/manage.php?id=X&page=invitation\n";
    echo "3. Tilføj SENDGRID_API_KEY til .env for email\n";
}

echo "</pre>";

echo "<p style='margin-top: 20px;'>";
echo "<a href='/app/dashboard.php' style='display:inline-block; padding:10px 20px; background:#8FA583; color:white; text-decoration:none; border-radius:6px; margin-right:10px;'>Gå til Dashboard</a>";
echo "<a href='?run=yes' style='display:inline-block; padding:10px 20px; background:#666; color:white; text-decoration:none; border-radius:6px;'>Kør igen</a>";
echo "</p>";

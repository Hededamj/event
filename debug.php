<?php
require_once __DIR__ . '/config/database.php';

echo "<h1>Debug</h1>";

try {
    $db = getDB();
    echo "<p style='color:green'>Database forbindelse OK</p>";

    // Check events table
    $stmt = $db->query("SELECT * FROM events LIMIT 1");
    $event = $stmt->fetch();

    if ($event) {
        echo "<h2>Event data:</h2>";
        echo "<pre>";
        print_r($event);
        echo "</pre>";
    } else {
        echo "<p style='color:red'>Ingen events fundet!</p>";

        // Try to insert seed data
        echo "<p>Indsætter test-event...</p>";
        $db->exec("INSERT INTO events (name, event_date, event_time, location, theme, welcome_text, confirmand_name) VALUES ('Konfirmation', '2026-05-10', '12:00:00', 'Hjemme hos familien', 'girl', 'Velkommen til Sofies konfirmation!', 'Sofie')");
        echo "<p style='color:green'>Event oprettet! <a href='/sofie/'>Gå til forsiden</a></p>";
    }

    // Check tables
    echo "<h2>Tabeller:</h2>";
    $stmt = $db->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        echo "- " . $row[0] . "<br>";
    }

} catch (PDOException $e) {
    echo "<p style='color:red'>Fejl: " . htmlspecialchars($e->getMessage()) . "</p>";
}

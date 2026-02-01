<?php
/**
 * Create Admin User - DELETE AFTER USE
 */
require_once __DIR__ . '/config/database.php';

$email = 'Mail@hededam.dk';
$password = 'Plantagevej12_';
$name = 'Administrator';

try {
    $db = getDB();

    // Get event ID
    $stmt = $db->query("SELECT id FROM events LIMIT 1");
    $event = $stmt->fetch();

    if (!$event) {
        die("Ingen event fundet");
    }

    // Hash password
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // Check if user exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);

    if ($stmt->fetch()) {
        // Update existing
        $stmt = $db->prepare("UPDATE users SET password_hash = ?, name = ? WHERE email = ?");
        $stmt->execute([$hash, $name, $email]);
        echo "Bruger opdateret!<br>";
    } else {
        // Insert new
        $stmt = $db->prepare("INSERT INTO users (event_id, email, password_hash, name, role) VALUES (?, ?, ?, ?, 'admin')");
        $stmt->execute([$event['id'], $email, $hash, $name]);
        echo "Bruger oprettet!<br>";
    }

    echo "<p>Email: $email<br>Password: $password</p>";
    echo "<p><a href='/sofie/'>Gå til login</a></p>";
    echo "<p style='color:red;'><strong>SLET DENNE FIL NU!</strong></p>";

} catch (PDOException $e) {
    echo "Fejl: " . htmlspecialchars($e->getMessage());
}

<?php
require_once __DIR__ . '/config/database.php';

$email = 'Mail@hededam.dk';
$testPassword = 'Plantagevej12_';

try {
    $db = getDB();

    echo "<h2>Brugere i databasen:</h2>";
    $stmt = $db->query("SELECT id, email, name, role, password_hash FROM users");
    $users = $stmt->fetchAll();

    foreach ($users as $user) {
        echo "<p><strong>" . htmlspecialchars($user['email']) . "</strong><br>";
        echo "Navn: " . htmlspecialchars($user['name']) . "<br>";
        echo "Role: " . htmlspecialchars($user['role']) . "<br>";
        echo "Hash: " . substr($user['password_hash'], 0, 20) . "...<br>";

        // Test password
        if (password_verify($testPassword, $user['password_hash'])) {
            echo "<span style='color:green'>Password '$testPassword' VIRKER for denne bruger!</span>";
        } else {
            echo "<span style='color:red'>Password '$testPassword' virker IKKE</span>";
        }
        echo "</p><hr>";
    }

    // Also check if email lookup is case-sensitive
    echo "<h2>Søgning efter '$email':</h2>";
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $found = $stmt->fetch();

    if ($found) {
        echo "<p style='color:green'>Bruger fundet med email '$email'</p>";
    } else {
        echo "<p style='color:red'>Ingen bruger fundet med email '$email'</p>";

        // Try case-insensitive
        $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(email) = LOWER(?)");
        $stmt->execute([$email]);
        $found = $stmt->fetch();
        if ($found) {
            echo "<p>Men fundet med case-insensitive søgning: " . htmlspecialchars($found['email']) . "</p>";
        }
    }

} catch (PDOException $e) {
    echo "Fejl: " . htmlspecialchars($e->getMessage());
}

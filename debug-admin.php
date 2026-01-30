<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/saas.php';

echo "<h2>Debug Admin Login</h2>";

try {
    $db = getDB();
    echo "<p style='color:green'>✓ Database forbindelse OK</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Database fejl: " . $e->getMessage() . "</p>";
    exit;
}

// Check if accounts table has is_platform_admin column
try {
    $stmt = $db->query("SHOW COLUMNS FROM accounts LIKE 'is_platform_admin'");
    $column = $stmt->fetch();
    if ($column) {
        echo "<p style='color:green'>✓ is_platform_admin kolonne findes</p>";
    } else {
        echo "<p style='color:red'>✗ is_platform_admin kolonne mangler - kør migration!</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Fejl: " . $e->getMessage() . "</p>";
}

// Check for admin account
try {
    $stmt = $db->prepare("SELECT id, email, name, is_active, is_platform_admin, password_hash FROM accounts WHERE email = ?");
    $stmt->execute(['mail@hededam.dk']);
    $account = $stmt->fetch();

    if ($account) {
        echo "<p style='color:green'>✓ Konto fundet: " . htmlspecialchars($account['name']) . " (" . htmlspecialchars($account['email']) . ")</p>";
        echo "<p>- is_active: " . ($account['is_active'] ? 'Ja' : 'Nej') . "</p>";
        echo "<p>- is_platform_admin: " . ($account['is_platform_admin'] ? 'Ja' : 'Nej') . "</p>";
        echo "<p>- password_hash findes: " . (!empty($account['password_hash']) ? 'Ja' : 'Nej') . "</p>";

        if (!$account['is_platform_admin']) {
            echo "<p style='color:orange'>⚠ Konto er IKKE platform admin. Kør:</p>";
            echo "<code>UPDATE accounts SET is_platform_admin = 1 WHERE email = 'mail@hededam.dk';</code>";
        }
    } else {
        echo "<p style='color:red'>✗ Ingen konto fundet med email: mail@hededam.dk</p>";

        // List all accounts
        $stmt = $db->query("SELECT id, email, name FROM accounts LIMIT 10");
        $accounts = $stmt->fetchAll();
        if ($accounts) {
            echo "<p>Eksisterende konti:</p><ul>";
            foreach ($accounts as $a) {
                echo "<li>" . htmlspecialchars($a['email']) . " - " . htmlspecialchars($a['name']) . "</li>";
            }
            echo "</ul>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Fejl ved kontosøgning: " . $e->getMessage() . "</p>";
}

// Check platform_settings table
try {
    $stmt = $db->query("SELECT * FROM platform_settings");
    $settings = $stmt->fetchAll();
    echo "<p style='color:green'>✓ platform_settings tabel findes (" . count($settings) . " indstillinger)</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>✗ platform_settings tabel mangler - kør migration!</p>";
}

echo "<hr><p><a href='/admin-platform/login.php'>Tilbage til login</a></p>";
echo "<p style='color:red'><strong>SLET DENNE FIL NÅR DU ER FÆRDIG!</strong></p>";

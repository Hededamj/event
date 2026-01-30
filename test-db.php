<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Test</h2>";

// Test 1: Can we connect?
echo "<p>1. Testing database connection...</p>";

try {
    $dsn = 'mysql:host=mysql71.unoeuro.com;dbname=hededam_dk_db;charset=utf8mb4';
    $pdo = new PDO($dsn, 'hededam_dk', 'Plantagevej12', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "<p style='color:green'>OK - Connected to database</p>";
} catch (PDOException $e) {
    echo "<p style='color:red'>FEJL: " . $e->getMessage() . "</p>";
    exit;
}

// Test 2: Does accounts table exist?
echo "<p>2. Testing accounts table...</p>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM accounts");
    $result = $stmt->fetch();
    echo "<p style='color:green'>OK - accounts table exists with {$result['cnt']} rows</p>";
} catch (PDOException $e) {
    echo "<p style='color:red'>FEJL: " . $e->getMessage() . "</p>";
}

// Test 3: Check for is_platform_admin column
echo "<p>3. Testing is_platform_admin column...</p>";
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM accounts LIKE 'is_platform_admin'");
    $col = $stmt->fetch();
    if ($col) {
        echo "<p style='color:green'>OK - is_platform_admin column exists</p>";
    } else {
        echo "<p style='color:red'>MANGLER - is_platform_admin column findes ikke. Kør migration!</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color:red'>FEJL: " . $e->getMessage() . "</p>";
}

// Test 4: Check for platform_settings table
echo "<p>4. Testing platform_settings table...</p>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM platform_settings");
    $result = $stmt->fetch();
    echo "<p style='color:green'>OK - platform_settings table exists with {$result['cnt']} rows</p>";
} catch (PDOException $e) {
    echo "<p style='color:red'>MANGLER - platform_settings table: " . $e->getMessage() . "</p>";
}

// Test 5: Look for admin account
echo "<p>5. Looking for mail@hededam.dk...</p>";
try {
    $stmt = $pdo->prepare("SELECT id, email, name, is_active, password_hash FROM accounts WHERE email = ?");
    $stmt->execute(['mail@hededam.dk']);
    $account = $stmt->fetch();

    if ($account) {
        echo "<p style='color:green'>OK - Konto fundet: " . htmlspecialchars($account['name']) . "</p>";
        echo "<p>- ID: {$account['id']}</p>";
        echo "<p>- is_active: " . ($account['is_active'] ? 'Ja' : 'Nej') . "</p>";
        echo "<p>- password_hash: " . (!empty($account['password_hash']) ? 'SAT (' . strlen($account['password_hash']) . ' chars)' : 'MANGLER!') . "</p>";

        // Check is_platform_admin
        try {
            $stmt2 = $pdo->prepare("SELECT is_platform_admin FROM accounts WHERE id = ?");
            $stmt2->execute([$account['id']]);
            $admin = $stmt2->fetch();
            echo "<p>- is_platform_admin: " . ($admin['is_platform_admin'] ? 'Ja' : 'NEJ - skal sættes!') . "</p>";
        } catch (PDOException $e) {
            echo "<p>- is_platform_admin: Kolonne findes ikke</p>";
        }
    } else {
        echo "<p style='color:orange'>Konto ikke fundet - skal oprettes</p>";

        // List existing accounts
        $stmt = $pdo->query("SELECT email, name FROM accounts LIMIT 5");
        $accounts = $stmt->fetchAll();
        if ($accounts) {
            echo "<p>Eksisterende konti:</p><ul>";
            foreach ($accounts as $a) {
                echo "<li>" . htmlspecialchars($a['email']) . " - " . htmlspecialchars($a['name']) . "</li>";
            }
            echo "</ul>";
        }
    }
} catch (PDOException $e) {
    echo "<p style='color:red'>FEJL: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><strong>SLET DENNE FIL NÅR DU ER FÆRDIG!</strong></p>";

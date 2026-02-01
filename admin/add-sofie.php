<?php
/**
 * One-time script to add Sofie user
 * DELETE THIS FILE AFTER USE
 */
require_once __DIR__ . '/../config/database.php';

$db = getDB();

// Get event ID
$stmt = $db->query("SELECT id FROM events ORDER BY id LIMIT 1");
$event = $stmt->fetch();

if (!$event) {
    die('No event found');
}

$email = 'sofie@hededam.dk';
$password = 'Plantagevej12_';
$name = 'Sofie';
$role = 'confirmand';

// Check if user already exists
$stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND event_id = ?");
$stmt->execute([$email, $event['id']]);

if ($stmt->fetch()) {
    echo "User already exists!";
} else {
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $db->prepare("
        INSERT INTO users (event_id, email, password_hash, name, role)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$event['id'], $email, $hash, $name, $role]);

    echo "User created successfully!<br>";
    echo "Email: $email<br>";
    echo "Role: $role<br><br>";
    echo "<strong>DELETE THIS FILE NOW!</strong>";
}

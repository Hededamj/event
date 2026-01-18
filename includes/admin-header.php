<?php
/**
 * Admin Header
 * Included at the top of all admin pages
 */

// Start output buffering to allow redirects
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// Require organizer login
requireLogin();

$db = getDB();
$eventId = getCurrentEventId();
$userId = getCurrentUserId();

// Get event details
$stmt = $db->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) {
    logout();
    redirect(BASE_PATH . '/index.php');
}

$theme = $event['theme'] ?? 'girl';

// Get current user
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();

// Get quick stats for sidebar/header
$stmt = $db->prepare("
    SELECT
        COUNT(*) as total_guests,
        SUM(CASE WHEN rsvp_status = 'yes' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN rsvp_status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN rsvp_status = 'no' THEN 1 ELSE 0 END) as declined,
        SUM(CASE WHEN rsvp_status = 'yes' THEN adults_count ELSE 0 END) as total_adults,
        SUM(CASE WHEN rsvp_status = 'yes' THEN children_count ELSE 0 END) as total_children
    FROM guests WHERE event_id = ?
");
$stmt->execute([$eventId]);
$guestStats = $stmt->fetch();

// Days until event
$eventDate = new DateTime($event['event_date']);
$today = new DateTime('today');
$daysUntil = $today->diff($eventDate)->days;
$isPast = $eventDate < $today;

// Get flash message if any
$flash = getFlash();

// Current page for active nav
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($event['name']) ?> - Admin</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">

    <!-- Styles -->
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/main.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/theme-<?= escape($theme) ?>.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/admin.css">
</head>
<body class="admin-body">
    <div class="admin-layout">

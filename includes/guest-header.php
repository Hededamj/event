<?php
/**
 * Guest Header
 * Included at the top of all guest pages
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// Require guest or organizer access
requireGuest();

$db = getDB();
$eventId = getCurrentEventId();
$guestId = getCurrentGuestId();

// Get event details
$stmt = $db->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) {
    logout();
    redirect('/index.php');
}

$theme = $event['theme'] ?? 'girl';

// Get guest details
$guest = null;
if ($guestId) {
    $stmt = $db->prepare("SELECT * FROM guests WHERE id = ?");
    $stmt->execute([$guestId]);
    $guest = $stmt->fetch();
}

// Get flash message if any
$flash = getFlash();

// Current page for nav
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($event['name']) ?></title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">

    <!-- Styles -->
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/theme-<?= escape($theme) ?>.css">

    <style>
        /* Guest-specific styles */
        .guest-layout {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .guest-nav {
            background: var(--color-surface);
            border-bottom: 1px solid var(--color-border-soft);
            padding: var(--space-sm) var(--space-md);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .guest-nav__inner {
            max-width: 600px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .guest-nav__brand {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .guest-nav__brand-icon {
            color: var(--color-primary);
        }

        .guest-nav__brand-name {
            font-family: 'Cormorant Garamond', serif;
            font-size: var(--text-lg);
            font-weight: 600;
            color: var(--color-text);
        }

        .guest-nav__user {
            font-size: var(--text-sm);
            color: var(--color-text-muted);
        }

        .guest-content {
            flex: 1;
            max-width: 600px;
            margin: 0 auto;
            padding: var(--space-lg) var(--space-md);
            width: 100%;
        }

        .guest-hero {
            text-align: center;
            margin-bottom: var(--space-lg);
            padding: var(--space-lg) var(--space-md);
            background: linear-gradient(180deg, var(--color-bg-subtle) 0%, var(--color-bg) 100%);
            border-radius: var(--radius-xl);
        }

        .guest-hero__icon {
            font-size: 3rem;
            margin-bottom: var(--space-sm);
        }

        .guest-hero__title {
            font-size: var(--text-2xl);
            color: var(--color-primary-deep);
            margin-bottom: var(--space-2xs);
        }

        .guest-hero__subtitle {
            color: var(--color-text-soft);
            margin-bottom: var(--space-xs);
        }

        .guest-hero__date {
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
            font-family: 'Cormorant Garamond', serif;
            font-size: var(--text-lg);
            color: var(--color-accent);
        }

        .guest-footer {
            text-align: center;
            padding: var(--space-md);
            color: var(--color-text-muted);
            font-size: var(--text-xs);
        }

        /* Bottom nav for guests */
        .guest-bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--color-surface);
            border-top: 1px solid var(--color-border-soft);
            padding: var(--space-xs) var(--space-md);
            z-index: 100;
        }

        .guest-bottom-nav__inner {
            max-width: 600px;
            margin: 0 auto;
            display: flex;
            justify-content: space-around;
        }

        .guest-bottom-nav__link {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            padding: var(--space-2xs);
            color: var(--color-text-muted);
            font-size: var(--text-xs);
            text-decoration: none;
            transition: color var(--duration-fast);
        }

        .guest-bottom-nav__link:hover,
        .guest-bottom-nav__link--active {
            color: var(--color-primary-deep);
        }

        .guest-bottom-nav__icon {
            font-size: 1.25rem;
        }

        /* Add padding for bottom nav */
        .guest-content {
            padding-bottom: calc(var(--space-lg) + 70px);
        }
    </style>
</head>
<body>
    <div class="guest-layout">
        <nav class="guest-nav">
            <div class="guest-nav__inner">
                <div class="guest-nav__brand">
                    <span class="guest-nav__brand-icon">✦</span>
                    <span class="guest-nav__brand-name"><?= escape($event['confirmand_name']) ?></span>
                </div>
                <span class="guest-nav__user">
                    Hej, <?= escape($guest['name'] ?? 'Gæst') ?>
                </span>
            </div>
        </nav>

        <main class="guest-content">
            <?php if ($flash): ?>
                <div class="alert alert--<?= escape($flash['type']) ?> mb-md animate-fade-in-up">
                    <?= escape($flash['message']) ?>
                </div>
            <?php endif; ?>

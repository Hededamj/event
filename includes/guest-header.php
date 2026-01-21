<?php
/**
 * Guest Header - Immersive Design
 * Matches the landing page aesthetic
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

requireGuest();

$db = getDB();
$eventId = getCurrentEventId();
$guestId = getCurrentGuestId();

$stmt = $db->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) {
    logout();
    redirect(BASE_PATH . '/index.php');
}

$guest = null;
if ($guestId) {
    $stmt = $db->prepare("SELECT * FROM guests WHERE id = ?");
    $stmt->execute([$guestId]);
    $guest = $stmt->fetch();
}

$flash = getFlash();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($event['confirmand_name']) ?>s <?= escape($event['name']) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=Manrope:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --blush: #D4A5A5;
            --blush-dark: #B88A8A;
            --blush-light: #E8CECE;
            --blush-pale: #F7EEEE;
            --cream: #FFF9F7;
            --ink: #2A2222;
            --ink-soft: #5C4F4F;
            --white: #FFFFFF;
            --success: #7A9E7A;
            --warning: #C9A227;

            /* Legacy compatibility */
            --color-bg-subtle: var(--blush-pale);
            --color-primary: var(--blush);
            --color-primary-soft: var(--blush-light);
            --color-primary-deep: var(--blush-dark);
            --color-border-soft: var(--blush-light);
            --color-surface: var(--white);
            --color-text: var(--ink);
            --color-text-muted: var(--ink-soft);

            /* Spacing */
            --space-3xs: 0.125rem;
            --space-2xs: 0.25rem;
            --space-xs: 0.5rem;
            --space-sm: 0.75rem;
            --space-md: 1rem;
            --space-lg: 1.5rem;
            --space-xl: 2rem;

            /* Text sizes */
            --text-xs: 0.75rem;
            --text-sm: 0.875rem;
            --text-base: 1rem;
            --text-lg: 1.125rem;
            --text-xl: 1.25rem;
            --text-2xl: 1.5rem;

            /* Radius */
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
        }

        html, body {
            min-height: 100vh;
        }

        body {
            font-family: 'Manrope', sans-serif;
            background: var(--cream);
            color: var(--ink);
            line-height: 1.6;
        }

        /* Layout */
        .guest-layout {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Top Navigation */
        .guest-nav {
            background: var(--white);
            border-bottom: 1px solid var(--blush-light);
            padding: 1rem 1.5rem;
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
            gap: 0.5rem;
            text-decoration: none;
        }

        .guest-nav__brand-icon {
            color: var(--blush);
            font-size: 1.2rem;
        }

        .guest-nav__brand-name {
            font-family: 'Playfair Display', serif;
            font-size: 1.25rem;
            font-weight: 400;
            color: var(--ink);
        }

        .guest-nav__user {
            font-size: 0.85rem;
            color: var(--ink-soft);
        }

        /* Main Content */
        .guest-content {
            flex: 1;
            max-width: 600px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
            padding-bottom: 100px;
            width: 100%;
        }

        /* Hero Section */
        .guest-hero {
            text-align: center;
            padding: 2.5rem 1.5rem;
            margin-bottom: 1.5rem;
            background: linear-gradient(180deg, var(--blush-pale) 0%, var(--cream) 100%);
            border-radius: 20px;
            border: 1px solid var(--blush-light);
        }

        .guest-hero__icon {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
        }

        .guest-hero__title {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 400;
            color: var(--ink);
            margin-bottom: 0.25rem;
        }

        .guest-hero__subtitle {
            font-family: 'Playfair Display', serif;
            font-size: 1.1rem;
            font-style: italic;
            color: var(--blush-dark);
            margin-bottom: 0.75rem;
        }

        .guest-hero__date {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: var(--ink-soft);
        }

        /* Cards */
        .card {
            background: var(--white);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: 1px solid var(--blush-light);
        }

        .card--flat {
            background: var(--blush-pale);
            border: none;
        }

        .card__title {
            font-family: 'Playfair Display', serif;
            font-size: 1.25rem;
            font-weight: 400;
            color: var(--ink);
            margin-bottom: 0.5rem;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.9rem 1.5rem;
            font-family: 'Manrope', sans-serif;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn--primary {
            background: var(--blush);
            color: var(--white);
        }

        .btn--primary:hover {
            background: var(--blush-dark);
            transform: translateY(-2px);
        }

        .btn--secondary {
            background: var(--blush-pale);
            color: var(--ink);
        }

        .btn--secondary:hover {
            background: var(--blush-light);
        }

        .btn--ghost {
            background: transparent;
            color: var(--ink-soft);
        }

        .btn--ghost:hover {
            background: var(--blush-pale);
        }

        .btn--block {
            display: flex;
            width: 100%;
        }

        .btn--large {
            padding: 1.1rem 2rem;
            font-size: 0.95rem;
        }

        /* Forms */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--ink-soft);
            margin-bottom: 0.5rem;
        }

        .form-input {
            width: 100%;
            padding: 0.9rem 1rem;
            font-family: 'Manrope', sans-serif;
            font-size: 1rem;
            color: var(--ink);
            background: var(--white);
            border: 2px solid var(--blush-light);
            border-radius: 10px;
            outline: none;
            transition: border-color 0.3s;
        }

        .form-input:focus {
            border-color: var(--blush);
        }

        select.form-input {
            cursor: pointer;
        }

        textarea.form-input {
            resize: vertical;
            min-height: 100px;
        }

        /* Alerts */
        .alert {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.25rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .alert--success {
            background: rgba(122, 158, 122, 0.15);
            border: 1px solid var(--success);
            color: #3D5C3D;
        }

        .alert--warning {
            background: rgba(201, 162, 39, 0.15);
            border: 1px solid var(--warning);
            color: #8B7020;
        }

        .alert--error {
            background: rgba(184, 138, 138, 0.2);
            border: 1px solid var(--blush-dark);
            color: var(--ink);
        }

        /* Utilities */
        .text-center { text-align: center; }
        .text-muted { color: var(--ink-soft); }
        .text-soft { color: var(--ink-soft); }
        .small { font-size: 0.85rem; }
        .lead { font-size: 1.1rem; line-height: 1.7; }

        .mt-xs { margin-top: 0.25rem; }
        .mt-sm { margin-top: 0.5rem; }
        .mt-md { margin-top: 1rem; }
        .mt-lg { margin-top: 1.5rem; }

        .mb-xs { margin-bottom: 0.25rem; }
        .mb-sm { margin-bottom: 0.5rem; }
        .mb-md { margin-bottom: 1rem; }
        .mb-lg { margin-bottom: 1.5rem; }

        .flex { display: flex; }
        .gap-sm { gap: 0.75rem; }
        .gap-xs { gap: 0.5rem; }

        .link-underline {
            text-decoration: underline;
            color: var(--ink-soft);
        }

        .link-underline:hover {
            color: var(--blush-dark);
        }

        /* Headings */
        .h2 {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 400;
            color: var(--ink);
        }

        .h4 {
            font-family: 'Playfair Display', serif;
            font-size: 1.1rem;
            font-weight: 400;
            color: var(--ink);
        }

        /* Flexbox utilities */
        .flex-between {
            display: flex;
            justify-content: space-between;
        }

        .items-center {
            align-items: center;
        }

        /* Badge */
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .badge--neutral {
            background: var(--blush-light);
            color: var(--ink-soft);
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 2rem 1rem;
        }

        .empty-state__icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .empty-state__title {
            font-family: 'Playfair Display', serif;
            font-size: 1.25rem;
            font-weight: 400;
            color: var(--ink);
            margin-bottom: 0.5rem;
        }

        .empty-state__text {
            font-size: 0.9rem;
            color: var(--ink-soft);
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(42, 34, 34, 0.85);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 1rem;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal {
            background: var(--white);
            border-radius: 16px;
            padding: 1.5rem;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
        }

        /* Text color utilities */
        .text-primary {
            color: var(--blush-dark);
        }

        /* Bottom Navigation */
        .guest-bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--white);
            border-top: 1px solid var(--blush-light);
            padding: 0.5rem 1rem;
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
            padding: 0.5rem;
            color: var(--ink-soft);
            font-size: 0.7rem;
            text-decoration: none !important;
            border: none;
            background: none;
            cursor: pointer;
            font-family: 'Manrope', sans-serif;
            transition: color 0.2s;
        }

        /* Reset all links */
        a {
            text-decoration: none;
            color: inherit;
        }

        .guest-bottom-nav__link:hover,
        .guest-bottom-nav__link--active {
            color: var(--blush-dark);
        }

        .guest-bottom-nav__icon {
            font-size: 1.3rem;
        }

        /* Animations */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
        }
    </style>
</head>
<body>
    <div class="guest-layout">
        <nav class="guest-nav">
            <div class="guest-nav__inner">
                <a href="<?= BASE_PATH ?>/guest/index.php" class="guest-nav__brand">
                    <span class="guest-nav__brand-icon">✦</span>
                    <span class="guest-nav__brand-name"><?= escape($event['confirmand_name']) ?></span>
                </a>
                <span class="guest-nav__user">
                    <?= escape($guest['name'] ?? 'Gæst') ?>
                </span>
            </div>
        </nav>

        <main class="guest-content">
            <?php if ($flash): ?>
                <div class="alert alert--<?= escape($flash['type']) ?> mb-md animate-fade-in-up">
                    <?= escape($flash['message']) ?>
                </div>
            <?php endif; ?>

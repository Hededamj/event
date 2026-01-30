<?php
/**
 * Partner Marketplace Header
 * For public partner browsing pages
 */

ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = getDB();

// Get platform name
try {
    $stmt = $db->query("SELECT setting_value FROM platform_settings WHERE setting_key = 'platform_name'");
    $platformName = $stmt->fetchColumn() ?: 'EventPlatform';
} catch (Exception $e) {
    $platformName = 'EventPlatform';
}

// Get categories for navigation
try {
    $categories = $db->query("SELECT * FROM partner_categories WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
} catch (Exception $e) {
    $categories = [];
}

// Get flash message
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? escape($pageTitle) . ' - ' : '' ?>Leverandører - <?= escape($platformName) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --color-bg: #fafaf9;
            --color-bg-subtle: #f5f5f4;
            --color-surface: #ffffff;
            --color-primary: #7c3aed;
            --color-primary-deep: #6d28d9;
            --color-primary-soft: #ede9fe;
            --color-accent: #f59e0b;
            --color-text: #1c1917;
            --color-text-soft: #57534e;
            --color-text-muted: #a8a29e;
            --color-border: #e7e5e4;
            --color-success: #22c55e;
            --color-warning: #f59e0b;
            --color-error: #ef4444;

            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.1);

            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 24px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--color-bg);
            color: var(--color-text);
            line-height: 1.6;
        }

        /* Header */
        .site-header {
            background: var(--color-surface);
            border-bottom: 1px solid var(--color-border);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .site-logo {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--color-primary);
            text-decoration: none;
        }

        .site-logo span {
            color: var(--color-text);
            font-weight: 500;
        }

        .header-nav {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .header-nav a {
            color: var(--color-text-soft);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: color 0.2s;
        }

        .header-nav a:hover {
            color: var(--color-primary);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--color-primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--color-primary-deep);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--color-bg-subtle);
            color: var(--color-text);
        }

        .btn-secondary:hover {
            background: var(--color-border);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--color-border);
            color: var(--color-text);
        }

        .btn-outline:hover {
            border-color: var(--color-primary);
            color: var(--color-primary);
        }

        /* Main content */
        .main-content {
            min-height: calc(100vh - 200px);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        /* Category nav */
        .category-nav {
            background: var(--color-surface);
            border-bottom: 1px solid var(--color-border);
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .category-nav-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
            display: flex;
            gap: 0.5rem;
        }

        .category-nav a {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem 1.25rem;
            color: var(--color-text-soft);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            white-space: nowrap;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
        }

        .category-nav a:hover,
        .category-nav a.active {
            color: var(--color-primary);
            border-bottom-color: var(--color-primary);
        }

        .category-nav .cat-icon {
            font-size: 1.1rem;
        }

        /* Cards */
        .card {
            background: var(--color-surface);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: all 0.3s;
        }

        .card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        /* Partner card */
        .partner-card {
            display: flex;
            flex-direction: column;
        }

        .partner-card__image {
            aspect-ratio: 16/10;
            background: var(--color-bg-subtle);
            overflow: hidden;
        }

        .partner-card__image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .partner-card__body {
            padding: 1.25rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .partner-card__category {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--color-primary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .partner-card__name {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .partner-card__name a {
            color: inherit;
            text-decoration: none;
        }

        .partner-card__name a:hover {
            color: var(--color-primary);
        }

        .partner-card__desc {
            color: var(--color-text-soft);
            font-size: 0.875rem;
            line-height: 1.5;
            margin-bottom: 1rem;
            flex: 1;
        }

        .partner-card__meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 1rem;
            border-top: 1px solid var(--color-border);
        }

        .partner-card__price {
            font-weight: 600;
            color: var(--color-text);
        }

        .partner-card__location {
            font-size: 0.8rem;
            color: var(--color-text-muted);
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 100px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .badge-featured {
            background: var(--color-accent);
            color: white;
        }

        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-error { background: #fee2e2; color: #991b1b; }

        /* Grid */
        .grid {
            display: grid;
            gap: 1.5rem;
        }

        .grid-3 {
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        }

        .grid-4 {
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        }

        /* Forms */
        .form-group { margin-bottom: 1rem; }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px var(--color-primary-soft);
        }

        textarea.form-input {
            resize: vertical;
            min-height: 120px;
        }

        /* Alerts */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
        }

        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        .alert-warning { background: #fef3c7; color: #92400e; }

        /* Utilities */
        .text-muted { color: var(--color-text-muted); }
        .text-sm { font-size: 0.875rem; }
        .font-medium { font-weight: 500; }
        .mt-lg { margin-top: 2rem; }
        .mb-lg { margin-bottom: 2rem; }
        .py-xl { padding-top: 3rem; padding-bottom: 3rem; }

        /* Responsive */
        @media (max-width: 768px) {
            .header-nav {
                gap: 1rem;
            }

            .header-nav a:not(.btn) {
                display: none;
            }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="<?= BASE_PATH ?>/partners/" class="site-logo">
                <?= escape($platformName) ?> <span>Leverandører</span>
            </a>

            <nav class="header-nav">
                <a href="<?= BASE_PATH ?>/partners/">Alle kategorier</a>
                <a href="<?= BASE_PATH ?>/partners/register.php" class="btn btn-outline">Bliv partner</a>
            </nav>
        </div>
    </header>

    <!-- Category Navigation -->
    <?php if (!empty($categories)): ?>
    <nav class="category-nav">
        <div class="category-nav-inner">
            <?php foreach ($categories as $cat): ?>
                <a href="<?= BASE_PATH ?>/partners/category.php?slug=<?= escape($cat['slug']) ?>"
                   class="<?= (isset($currentCategory) && $currentCategory === $cat['slug']) ? 'active' : '' ?>">
                    <span class="cat-icon"><?= $cat['icon'] ?></span>
                    <?= escape($cat['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>
    <?php endif; ?>

    <main class="main-content">

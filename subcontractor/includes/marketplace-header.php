<?php
/**
 * Marketplace Public Header
 * Public-facing header for organizers/visitors browsing the vendor marketplace.
 * NOT the vendor dashboard header — this is for /subcontractor/ public pages.
 *
 * Nordic Design System.
 */

// Start session if not already started (needed for login check)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in as account (organizer)
$isLoggedIn = isset($_SESSION['account_id']) && !empty($_SESSION['account_id']);

// Fetch active vendor categories for the category nav
$marketplaceCategories = [];
try {
    $db = getDB();
    $catStmt = $db->query("
        SELECT id, name, slug, icon
        FROM vendor_categories
        WHERE is_active = 1
        ORDER BY sort_order ASC
    ");
    $marketplaceCategories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Marketplace header: failed to fetch categories: " . $e->getMessage());
}

// Page title (can be overridden before including this file)
$pageTitle = $pageTitle ?? 'Find leverandører';

// Current search value
$currentSearch = isset($_GET['search']) ? trim($_GET['search']) : '';
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - PartyParart</title>
    <meta name="description" content="Find de bedste leverandører til dit event. Gennemse fotografer, caterere, DJs, teltudlejning og meget mere på PartyParart.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/design-system.css">
    <link rel="stylesheet" href="/subcontractor/assets/css/subcontractor.css">
    <style>
        /* ============================================
           Marketplace Public Layout
           ============================================ */

        body.marketplace-page {
            margin: 0;
            padding: 0;
        }

        /* ---- Top bar ---- */
        .mp-topbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: var(--surface-card);
            border-bottom: 1px solid var(--border);
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
        }

        .mp-topbar-inner {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 64px;
            gap: 24px;
        }

        .mp-logo {
            font-family: var(--font-display);
            font-size: 24px;
            font-weight: 500;
            color: var(--text);
            text-decoration: none;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .mp-logo span {
            color: var(--accent);
        }

        .mp-nav {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .mp-nav-link {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .mp-nav-link:hover {
            color: var(--text);
            background: var(--surface);
        }

        .mp-nav-link.active {
            color: var(--accent-dark);
            background: rgba(168, 181, 160, 0.12);
        }

        .mp-nav-btn {
            display: inline-flex;
            align-items: center;
            padding: 8px 20px;
            font-family: var(--font-body);
            font-size: 14px;
            font-weight: 600;
            color: var(--surface-card);
            background: var(--accent);
            border: none;
            border-radius: 8px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .mp-nav-btn:hover {
            background: var(--accent-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(122, 139, 114, 0.25);
        }

        /* ---- Category horizontal scroll nav ---- */
        .mp-category-bar {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
        }

        .mp-category-bar-inner {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            align-items: center;
            gap: 4px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }

        .mp-category-bar-inner::-webkit-scrollbar {
            display: none;
        }

        .mp-cat-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 12px 16px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
            text-decoration: none;
            white-space: nowrap;
            border-bottom: 2px solid transparent;
            transition: all 0.2s ease;
        }

        .mp-cat-link:hover {
            color: var(--text);
            border-bottom-color: var(--accent-light);
        }

        .mp-cat-link.active {
            color: var(--accent-dark);
            border-bottom-color: var(--accent-dark);
        }

        .mp-cat-icon {
            font-size: 16px;
            line-height: 1;
        }

        /* ---- Search bar in header ---- */
        .mp-search-bar {
            max-width: 1280px;
            margin: 0 auto;
            padding: 16px 24px;
            background: var(--surface);
        }

        .mp-search-form {
            display: flex;
            gap: 8px;
            max-width: 540px;
        }

        .mp-search-input {
            flex: 1;
            padding: 10px 16px;
            font-family: var(--font-body);
            font-size: 14px;
            border: 2px solid var(--border);
            border-radius: 10px;
            background: var(--surface-card);
            color: var(--text);
            transition: border-color 0.2s ease;
        }

        .mp-search-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(168, 181, 160, 0.15);
        }

        .mp-search-input::placeholder {
            color: #B0ACA4;
        }

        .mp-search-btn {
            padding: 10px 20px;
            font-family: var(--font-body);
            font-size: 14px;
            font-weight: 600;
            color: var(--surface-card);
            background: var(--accent);
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: background 0.2s ease;
            white-space: nowrap;
        }

        .mp-search-btn:hover {
            background: var(--accent-dark);
        }

        /* ---- Mobile ---- */
        .mp-mobile-toggle {
            display: none;
            width: 40px;
            height: 40px;
            align-items: center;
            justify-content: center;
            border: none;
            background: var(--surface);
            border-radius: 8px;
            cursor: pointer;
            color: var(--text);
        }

        .mp-mobile-toggle svg {
            width: 22px;
            height: 22px;
        }

        .mp-mobile-nav {
            display: none;
            background: var(--surface-card);
            border-bottom: 1px solid var(--border);
            padding: 12px 16px;
            flex-direction: column;
            gap: 4px;
        }

        .mp-mobile-nav.open {
            display: flex;
        }

        @media (max-width: 768px) {
            .mp-topbar-inner {
                padding: 0 16px;
                height: 56px;
            }

            .mp-nav {
                display: none;
            }

            .mp-mobile-toggle {
                display: flex;
            }

            .mp-mobile-nav .mp-nav-link,
            .mp-mobile-nav .mp-nav-btn {
                width: 100%;
                justify-content: center;
                text-align: center;
            }

            .mp-category-bar-inner {
                padding: 0 16px;
            }

            .mp-search-bar {
                padding: 12px 16px;
            }
        }
    </style>
</head>
<body class="marketplace-page">

    <!-- Top navigation bar -->
    <header class="mp-topbar">
        <div class="mp-topbar-inner">
            <a href="/" class="mp-logo">Party<span>Parart</span></a>

            <nav class="mp-nav">
                <a href="/subcontractor/" class="mp-nav-link active">Find leverandører</a>
                <?php if ($isLoggedIn): ?>
                    <a href="/app/dashboard.php" class="mp-nav-link">Min konto</a>
                <?php else: ?>
                    <a href="/app/auth/login.php" class="mp-nav-link">Log ind</a>
                <?php endif; ?>
                <a href="/subcontractor/dashboard/register.php" class="mp-nav-btn">Bliv leverandør</a>
            </nav>

            <button class="mp-mobile-toggle" id="mpMobileToggle" aria-label="Menu">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
        </div>

        <!-- Mobile nav dropdown -->
        <div class="mp-mobile-nav" id="mpMobileNav">
            <a href="/subcontractor/" class="mp-nav-link active">Find leverandører</a>
            <?php if ($isLoggedIn): ?>
                <a href="/app/dashboard.php" class="mp-nav-link">Min konto</a>
            <?php else: ?>
                <a href="/app/auth/login.php" class="mp-nav-link">Log ind</a>
            <?php endif; ?>
            <a href="/subcontractor/dashboard/register.php" class="mp-nav-btn">Bliv leverandør</a>
        </div>
    </header>

    <!-- Category horizontal scroll nav -->
    <?php if (!empty($marketplaceCategories)): ?>
    <nav class="mp-category-bar">
        <div class="mp-category-bar-inner">
            <?php
            $currentCatSlug = $_GET['slug'] ?? ($_GET['category'] ?? '');
            foreach ($marketplaceCategories as $cat):
            ?>
                <a href="/subcontractor/kategori/<?= htmlspecialchars($cat['slug']) ?>"
                   class="mp-cat-link <?= $currentCatSlug === $cat['slug'] ? 'active' : '' ?>">
                    <span class="mp-cat-icon"><?= $cat['icon'] ?></span>
                    <?= htmlspecialchars($cat['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>
    <?php endif; ?>

    <!-- Search bar -->
    <div class="mp-search-bar">
        <form class="mp-search-form" action="/subcontractor/" method="get">
            <input type="text"
                   name="search"
                   class="mp-search-input"
                   placeholder="Søg efter leverandører..."
                   value="<?= htmlspecialchars($currentSearch) ?>">
            <button type="submit" class="mp-search-btn">Søg</button>
        </form>
    </div>

    <script>
        // Mobile menu toggle
        (function() {
            var toggle = document.getElementById('mpMobileToggle');
            var nav = document.getElementById('mpMobileNav');
            if (toggle && nav) {
                toggle.addEventListener('click', function() {
                    nav.classList.toggle('open');
                });
            }
        })();
    </script>

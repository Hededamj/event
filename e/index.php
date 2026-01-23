<?php
/**
 * Guest Event Router
 * Handles /e/{slug}/ and /e/{slug}/{page}/ URLs
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$db = getDB();

// Get slug from URL (set by .htaccess)
$slug = $_GET['slug'] ?? '';
$page = $_GET['page'] ?? 'landing';

if (empty($slug)) {
    http_response_code(404);
    die('Arrangement ikke fundet.');
}

// Find event by slug
$stmt = $db->prepare("
    SELECT e.*, et.name as event_type_name
    FROM events e
    LEFT JOIN event_types et ON e.event_type_id = et.id
    WHERE e.slug = ? AND e.status = 'active'
");
$stmt->execute([$slug]);
$event = $stmt->fetch();

if (!$event) {
    http_response_code(404);
    die('Arrangement ikke fundet.');
}

$eventId = $event['id'];

// Theme colors
$themes = [
    'elegant' => ['primary' => '#667eea', 'secondary' => '#764ba2', 'bg' => '#f8fafc', 'text' => '#1f2937'],
    'romantic' => ['primary' => '#D4A5A5', 'secondary' => '#C48B8B', 'bg' => '#FFF9F7', 'text' => '#2A2222'],
    'modern' => ['primary' => '#0ea5e9', 'secondary' => '#0284c7', 'bg' => '#f0f9ff', 'text' => '#0c4a6e'],
    'nature' => ['primary' => '#22c55e', 'secondary' => '#16a34a', 'bg' => '#f0fdf4', 'text' => '#166534'],
    'golden' => ['primary' => '#f59e0b', 'secondary' => '#d97706', 'bg' => '#fffbeb', 'text' => '#78350f'],
    'minimal' => ['primary' => '#1f2937', 'secondary' => '#374151', 'bg' => '#ffffff', 'text' => '#1f2937']
];
$theme = $themes[$event['theme'] ?? 'elegant'] ?? $themes['elegant'];

// Check if guest is logged in
$guestLoggedIn = isGuest() && getCurrentEventId() == $eventId;
$currentGuest = null;
if ($guestLoggedIn) {
    $stmt = $db->prepare("SELECT * FROM guests WHERE id = ?");
    $stmt->execute([getCurrentGuestId()]);
    $currentGuest = $stmt->fetch();
}

// Handle guest login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guest_code'])) {
    $code = preg_replace('/[^0-9]/', '', $_POST['guest_code']);

    $stmt = $db->prepare("SELECT * FROM guests WHERE event_id = ? AND unique_code = ?");
    $stmt->execute([$eventId, $code]);
    $guest = $stmt->fetch();

    if ($guest) {
        loginGuest($guest['id'], $eventId);
        $redirectPage = $guest['rsvp_status'] === 'pending' ? 'rsvp' : 'home';
        redirect("/e/$slug/$redirectPage");
    } else {
        $loginError = 'Ugyldig kode. Prøv igen.';
    }
}

// Guest logout
if (isset($_GET['logout'])) {
    logout();
    redirect("/e/$slug/");
}

// Valid pages
$validPages = ['landing', 'home', 'rsvp', 'wishlist', 'menu', 'schedule', 'photos', 'indslag'];
if (!in_array($page, $validPages)) {
    $page = 'landing';
}

// If logged in and on landing, go to home
if ($guestLoggedIn && $page === 'landing') {
    redirect("/e/$slug/home");
}

// If not logged in and trying to access protected page, go to landing
if (!$guestLoggedIn && $page !== 'landing') {
    redirect("/e/$slug/");
}

// Get flash message
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($event['name'] ?? 'Arrangement') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --primary: <?= $theme['primary'] ?>;
            --secondary: <?= $theme['secondary'] ?>;
            --bg: <?= $theme['bg'] ?>;
            --text: <?= $theme['text'] ?>;
            --white: #ffffff;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-400: #9ca3af;
            --gray-600: #4b5563;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        .serif { font-family: 'Playfair Display', serif; }

        /* Navigation */
        .guest-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--white);
            border-top: 1px solid var(--gray-200);
            padding: 8px 16px;
            padding-bottom: calc(8px + env(safe-area-inset-bottom));
            z-index: 100;
            display: flex;
            justify-content: space-around;
        }

        .nav-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            padding: 8px 12px;
            color: var(--gray-400);
            text-decoration: none;
            font-size: 11px;
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav-link.active, .nav-link:hover {
            color: var(--primary);
        }

        .nav-link svg {
            width: 24px;
            height: 24px;
        }

        /* Main Content */
        .guest-main {
            min-height: 100vh;
            padding-bottom: 80px;
        }

        .page-content {
            max-width: 600px;
            margin: 0 auto;
            padding: 24px 20px;
        }

        /* Cards */
        .card {
            background: var(--white);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .card-title {
            font-family: 'Playfair Display', serif;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 16px;
            color: var(--text);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 28px;
            font-size: 15px;
            font-weight: 600;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            font-family: inherit;
        }

        .btn-primary {
            background: var(--primary);
            color: var(--white);
        }

        .btn-primary:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--gray-100);
            color: var(--text);
        }

        .btn-full { width: 100%; }

        /* Form Elements */
        .form-group { margin-bottom: 20px; }
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--text);
        }
        .form-input {
            width: 100%;
            padding: 14px 16px;
            font-size: 16px;
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            font-family: inherit;
            transition: border-color 0.2s;
        }
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
        }

        /* Flash Messages */
        .flash {
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        .flash-success { background: #dcfce7; color: #15803d; }
        .flash-error { background: #fef2f2; color: #dc2626; }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray-400);
        }
        .empty-state svg { width: 48px; height: 48px; margin-bottom: 12px; }

        /* Landing Page Specific */
        .landing-hero {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            text-align: center;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: var(--white);
        }

        .landing-title {
            font-family: 'Playfair Display', serif;
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .landing-subtitle {
            font-size: 18px;
            opacity: 0.9;
            margin-bottom: 8px;
        }

        .landing-date {
            font-size: 16px;
            opacity: 0.8;
            margin-bottom: 40px;
        }

        .code-form {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 32px;
            width: 100%;
            max-width: 360px;
        }

        .code-form h3 {
            font-family: 'Playfair Display', serif;
            font-size: 20px;
            margin-bottom: 8px;
        }

        .code-form p {
            font-size: 14px;
            opacity: 0.8;
            margin-bottom: 20px;
        }

        .code-input {
            width: 100%;
            padding: 16px;
            font-size: 24px;
            text-align: center;
            letter-spacing: 8px;
            background: rgba(255,255,255,0.9);
            border: none;
            border-radius: 12px;
            color: var(--text);
            margin-bottom: 16px;
        }

        .code-input::placeholder {
            letter-spacing: 2px;
            font-size: 16px;
        }

        .code-error {
            background: rgba(239, 68, 68, 0.2);
            color: white;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <?php if ($page === 'landing'): ?>
    <!-- Landing Page -->
    <div class="landing-hero">
        <h1 class="landing-title"><?= htmlspecialchars($event['main_person_name'] ?? '') ?><?= $event['secondary_person_name'] ? ' & ' . htmlspecialchars($event['secondary_person_name']) : '' ?></h1>
        <p class="landing-subtitle"><?= htmlspecialchars($event['event_type_name'] ?? 'Arrangement') ?></p>
        <?php if ($event['event_date']): ?>
        <p class="landing-date"><?= htmlspecialchars(formatDate($event['event_date'], true)) ?></p>
        <?php endif; ?>

        <div class="code-form">
            <h3>Velkommen</h3>
            <p>Indtast din personlige kode fra invitationen</p>

            <?php if (!empty($loginError)): ?>
            <div class="code-error"><?= htmlspecialchars($loginError) ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="text" name="guest_code" class="code-input" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required autofocus>
                <button type="submit" class="btn btn-primary btn-full">Fortsæt</button>
            </form>
        </div>
    </div>

    <?php else: ?>
    <!-- Logged In Pages -->
    <main class="guest-main">
        <div class="page-content">
            <?php if ($flash): ?>
            <div class="flash flash-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
            <?php endif; ?>

            <?php
            $pageFile = __DIR__ . '/pages/' . $page . '.php';
            if (file_exists($pageFile)) {
                include $pageFile;
            } else {
                include __DIR__ . '/pages/home.php';
            }
            ?>
        </div>
    </main>

    <!-- Bottom Navigation -->
    <nav class="guest-nav">
        <a href="/e/<?= $slug ?>/home" class="nav-link <?= $page === 'home' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
            Hjem
        </a>
        <a href="/e/<?= $slug ?>/rsvp" class="nav-link <?= $page === 'rsvp' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
            RSVP
        </a>
        <a href="/e/<?= $slug ?>/schedule" class="nav-link <?= $page === 'schedule' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            Program
        </a>
        <a href="/e/<?= $slug ?>/indslag" class="nav-link <?= $page === 'indslag' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path></svg>
            Indslag
        </a>
        <a href="/e/<?= $slug ?>/photos" class="nav-link <?= $page === 'photos' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            Fotos
        </a>
    </nav>
    <?php endif; ?>
</body>
</html>

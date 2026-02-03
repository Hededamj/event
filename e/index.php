<?php
/**
 * Guest Event Router - Nordic Celebration Design
 * Handles /e/{slug}/ and /e/{slug}/{page}/ URLs
 */
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/rate-limiter.php';
require_once __DIR__ . '/../includes/invitation-functions.php';

$db = getDB();

$slug = $_GET['slug'] ?? '';
$page = $_GET['page'] ?? 'landing';

if (empty($slug)) {
    http_response_code(404);
    die('Arrangement ikke fundet.');
}

$stmt = $db->prepare("
    SELECT e.*, et.name as event_type_name
    FROM events e
    LEFT JOIN event_types et ON e.event_type_id = et.id
    WHERE e.slug = ?
");
$stmt->execute([$slug]);
$event = $stmt->fetch();

if (!$event) {
    http_response_code(404);
    die('Arrangement ikke fundet.');
}

if ($event['status'] !== 'active') {
    $statusMessages = [
        'draft' => 'Dette arrangement er ikke offentliggjort endnu.',
        'completed' => 'Dette arrangement er afsluttet.',
        'archived' => 'Dette arrangement er arkiveret.'
    ];
    die($statusMessages[$event['status']] ?? 'Arrangement er ikke tilgængeligt.');
}

$eventId = $event['id'];

$guestLoggedIn = isGuest() && getCurrentEventId() == $eventId;
$currentGuest = null;
if ($guestLoggedIn) {
    $stmt = $db->prepare("SELECT * FROM guests WHERE id = ?");
    $stmt->execute([getCurrentGuestId()]);
    $currentGuest = $stmt->fetch();
}

// Auto-login via URL code
if (!$guestLoggedIn && isset($_GET['kode']) && !empty($_GET['kode'])) {
    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_GET['kode']));
    $stmt = $db->prepare("SELECT * FROM guests WHERE event_id = ? AND unique_code = ?");
    $stmt->execute([$eventId, $code]);
    $guest = $stmt->fetch();

    if ($guest) {
        loginGuest($guest['id'], $eventId);
        auditGuestLogin($db, $guest['id'], $eventId);
        redirect("/e/$slug/" . ($guest['rsvp_status'] === 'pending' ? 'rsvp' : 'home'));
    }
}

// Manual login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guest_code'])) {
    $rateLimit = checkRateLimit($db, 'guest_login_' . $eventId, 5, 900);

    if (!$rateLimit['allowed']) {
        $loginError = 'For mange forsøg. Prøv igen senere.';
    } else {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_POST['guest_code']));
        $stmt = $db->prepare("SELECT * FROM guests WHERE event_id = ? AND unique_code = ?");
        $stmt->execute([$eventId, $code]);
        $guest = $stmt->fetch();

        if ($guest) {
            clearRateLimit($db, 'guest_login_' . $eventId);
            loginGuest($guest['id'], $eventId);
            auditGuestLogin($db, $guest['id'], $eventId);
            redirect("/e/$slug/" . ($guest['rsvp_status'] === 'pending' ? 'rsvp' : 'home'));
        } else {
            recordRateLimitAttempt($db, 'guest_login_' . $eventId, 5, 900, 1800);
            $loginError = "Ugyldig kode. Prøv igen.";
        }
    }
}

if (isset($_GET['logout'])) {
    logout();
    redirect("/e/$slug/");
}

$validPages = ['landing', 'home', 'rsvp', 'wishlist', 'menu', 'schedule', 'photos', 'memories', 'indslag'];
if (!in_array($page, $validPages)) $page = 'landing';

if ($guestLoggedIn && $page === 'landing') redirect("/e/$slug/home");
if (!$guestLoggedIn && $page !== 'landing') redirect("/e/$slug/");

$flash = getFlash();

// Navigation items with Phosphor-style icons
$navItems = [
    ['slug' => 'home', 'label' => 'Oversigt', 'icon' => 'home'],
    ['slug' => 'rsvp', 'label' => 'Tilmelding', 'icon' => 'check-circle'],
    ['slug' => 'schedule', 'label' => 'Program', 'icon' => 'calendar'],
    ['slug' => 'wishlist', 'label' => 'Ønsker', 'icon' => 'gift'],
    ['slug' => 'memories', 'label' => 'Minder', 'icon' => 'clock'],
    ['slug' => 'photos', 'label' => 'Galleri', 'icon' => 'camera'],
    ['slug' => 'indslag', 'label' => 'Indslag', 'icon' => 'microphone'],
];

// Load invitation configuration
$invitationConfig = getInvitationConfig($db, $eventId);
$useInvitationLayout = $invitationConfig['is_published'] && $guestLoggedIn && $page === 'home';

// If invitation is published and guest is on home, render invitation layout
if ($useInvitationLayout) {
    // Get invitation images
    $invitationImages = getInvitationImages($db, $eventId);
    $heroImage = null;
    $galleryImages = [];
    foreach ($invitationImages as $image) {
        if ($image['image_role'] === 'hero') {
            $heroImage = $image;
        } else {
            $galleryImages[] = $image;
        }
    }

    // Prepare personalized content
    $greeting = personalizeGreeting($invitationConfig['greeting_template'] ?? 'Kære {guest_name}', $currentGuest);
    $fonts = getInvitationFontFamily($invitationConfig['font_style'] ?? 'elegant');
    $cssVars = getInvitationCssVariables($invitationConfig);

    // Format event date
    $eventDate = null;
    $months = ['', 'januar', 'februar', 'marts', 'april', 'maj', 'juni', 'juli', 'august', 'september', 'oktober', 'november', 'december'];
    if ($event['event_date']) {
        $date = new DateTime($event['event_date']);
        $eventDate = [
            'day' => $date->format('j'),
            'month' => $months[(int)$date->format('n')],
            'year' => $date->format('Y'),
            'weekday' => ['søndag', 'mandag', 'tirsdag', 'onsdag', 'torsdag', 'fredag', 'lørdag'][(int)$date->format('w')],
            'time' => $event['event_time'] ? (new DateTime($event['event_time']))->format('H:i') : null
        ];
    }

    // Render the invitation layout
    $layoutFile = __DIR__ . '/layouts/invitation-' . ($invitationConfig['layout_style'] ?? 'split') . '.php';
    if (file_exists($layoutFile)) {
        include $layoutFile;
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= htmlspecialchars($event['name'] ?? 'Arrangement') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --cream: #FAF9F7;
            --cream-dark: #EBE8E2;
            --sage: #8FA583;
            --sage-light: #B8C9B0;
            --sage-dark: #5D7255;
            --charcoal: #1A1A1A;
            --charcoal-light: #3D3D3D;
            --gold: #B8923D;
            --gold-light: #E8D5A3;
            --white: #FFFFFF;
            --error: #B84C4C;
            --success: #4D854D;

            --font-display: 'Cormorant Garamond', Georgia, serif;
            --font-body: 'DM Sans', -apple-system, sans-serif;

            --ease-out: cubic-bezier(0.16, 1, 0.3, 1);
            --ease-in-out: cubic-bezier(0.65, 0, 0.35, 1);

            --nav-width: 72px;
            --nav-width-expanded: 200px;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }

        body {
            font-family: var(--font-body);
            background: var(--cream);
            color: var(--charcoal);
            min-height: 100vh;
            min-height: 100dvh;
            line-height: 1.6;
            font-size: 15px;
            -webkit-font-smoothing: antialiased;
        }

        /* Subtle grain texture overlay */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.8' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E");
            opacity: 0.025;
            pointer-events: none;
            z-index: 9999;
        }

        .serif { font-family: var(--font-display); }

        /* ============================================
           LANDING PAGE
           ============================================ */
        .landing {
            min-height: 100vh;
            min-height: 100dvh;
            display: grid;
            grid-template-columns: 1fr 1fr;
            overflow: hidden;
        }

        @media (max-width: 900px) {
            .landing { grid-template-columns: 1fr; }
            .landing-visual { display: none; }
        }

        .landing-visual {
            background: linear-gradient(160deg, var(--sage) 0%, var(--sage-dark) 100%);
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .landing-visual::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 20% 80%, rgba(255,255,255,0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255,255,255,0.08) 0%, transparent 40%);
        }

        .visual-content {
            text-align: center;
            color: var(--white);
            padding: 40px;
            animation: fadeIn 1s var(--ease-out);
        }

        .visual-date {
            font-family: var(--font-display);
            font-size: clamp(48px, 8vw, 96px);
            font-weight: 400;
            line-height: 1;
            letter-spacing: -0.02em;
        }

        .visual-month {
            font-family: var(--font-display);
            font-size: clamp(20px, 3vw, 32px);
            font-weight: 400;
            text-transform: uppercase;
            letter-spacing: 0.2em;
            margin-top: 8px;
            opacity: 0.9;
        }

        .visual-year {
            font-size: 14px;
            letter-spacing: 0.3em;
            margin-top: 24px;
            opacity: 0.7;
        }

        .landing-content {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: clamp(32px, 8vw, 80px);
            background: var(--cream);
            position: relative;
        }

        .landing-header {
            margin-bottom: 48px;
            animation: fadeIn 0.8s var(--ease-out);
        }

        .landing-eyebrow {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--sage-dark);
            margin-bottom: 16px;
        }

        .landing-title {
            font-family: var(--font-display);
            font-size: clamp(36px, 6vw, 56px);
            font-weight: 400;
            line-height: 1.1;
            color: var(--charcoal);
            margin-bottom: 12px;
        }

        .landing-subtitle {
            font-size: 16px;
            color: var(--charcoal-light);
        }

        .login-card {
            background: var(--white);
            border-radius: 20px;
            padding: 36px;
            box-shadow:
                0 1px 2px rgba(0,0,0,0.04),
                0 4px 16px rgba(0,0,0,0.04);
            animation: fadeIn 0.8s var(--ease-out) 0.1s both;
            max-width: 400px;
        }

        .login-card h2 {
            font-family: var(--font-display);
            font-size: 24px;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .login-card p {
            font-size: 14px;
            color: var(--charcoal-light);
            margin-bottom: 28px;
        }

        .code-input-wrapper {
            position: relative;
            margin-bottom: 20px;
        }

        .code-input {
            width: 100%;
            padding: 18px 20px;
            font-family: var(--font-body);
            font-size: 20px;
            font-weight: 500;
            letter-spacing: 0.25em;
            text-align: center;
            text-transform: uppercase;
            border: 2px solid var(--cream-dark);
            border-radius: 14px;
            background: var(--cream);
            color: var(--charcoal);
            transition: all 0.3s var(--ease-out);
        }

        .code-input:focus {
            outline: none;
            border-color: var(--sage);
            background: var(--white);
        }

        .code-input::placeholder {
            color: #B5B0A8;
            letter-spacing: 0.15em;
            font-size: 15px;
        }

        .login-error {
            background: #FDF2F2;
            border: 1px solid #F5D5D5;
            color: var(--error);
            padding: 14px 16px;
            border-radius: 12px;
            font-size: 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* ============================================
           BUTTONS
           ============================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 16px 32px;
            font-family: var(--font-body);
            font-size: 15px;
            font-weight: 600;
            border: none;
            border-radius: 14px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s var(--ease-out);
            position: relative;
            overflow: hidden;
        }

        .btn-primary {
            background: var(--charcoal);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--charcoal-light);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(44,44,44,0.2);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-secondary {
            background: var(--cream-dark);
            color: var(--charcoal);
        }

        .btn-secondary:hover {
            background: #EBE8E2;
        }

        .btn-sage {
            background: var(--sage);
            color: var(--white);
        }

        .btn-sage:hover {
            background: var(--sage-dark);
            transform: translateY(-2px);
        }

        .btn-full { width: 100%; }

        .btn-icon {
            width: 44px;
            height: 44px;
            padding: 0;
            border-radius: 12px;
        }

        /* ============================================
           MAIN LAYOUT (Logged In)
           ============================================ */
        .app-layout {
            display: flex;
            min-height: 100vh;
            min-height: 100dvh;
        }

        /* Right-side navigation */
        .app-nav {
            position: fixed;
            right: 0;
            top: 0;
            bottom: 0;
            width: var(--nav-width);
            background: var(--white);
            border-left: 1px solid rgba(0,0,0,0.06);
            display: flex;
            flex-direction: column;
            padding: 24px 12px;
            z-index: 100;
            transition: width 0.4s var(--ease-out);
        }

        .app-nav:hover {
            width: var(--nav-width-expanded);
            box-shadow: -8px 0 32px rgba(0,0,0,0.08);
        }

        .nav-brand {
            width: 40px;
            height: 40px;
            background: var(--sage);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-family: var(--font-display);
            font-size: 18px;
            font-weight: 600;
            margin: 0 auto 32px;
            flex-shrink: 0;
        }

        .nav-links {
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex: 1;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px;
            color: var(--charcoal);
            text-decoration: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.25s var(--ease-out);
            white-space: nowrap;
            overflow: hidden;
            position: relative;
        }

        .nav-link svg {
            width: 22px;
            height: 22px;
            flex-shrink: 0;
            stroke-width: 1.75;
        }

        .nav-link span {
            opacity: 0;
            transition: opacity 0.25s var(--ease-out);
        }

        .app-nav:hover .nav-link span {
            opacity: 1;
        }

        .nav-link:hover {
            background: var(--sage-light);
            color: var(--charcoal);
            transform: translateX(-4px);
        }

        .nav-link:hover svg {
            color: var(--sage-dark);
            transform: scale(1.1);
        }

        .nav-link svg {
            transition: all 0.25s var(--ease-out);
        }

        .nav-link.active {
            background: var(--sage);
            color: var(--white);
        }

        .nav-link.active:hover {
            background: var(--sage-dark);
            transform: translateX(-4px);
        }

        .nav-link.active:hover svg {
            color: var(--white);
        }

        /* Tooltip on hover (when collapsed) */
        .nav-link::before {
            content: attr(data-tooltip);
            position: absolute;
            right: calc(100% + 12px);
            top: 50%;
            transform: translateY(-50%) translateX(8px);
            background: var(--charcoal);
            color: var(--white);
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s var(--ease-out);
            pointer-events: none;
            z-index: 1000;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            right: calc(100% + 4px);
            top: 50%;
            transform: translateY(-50%);
            border: 6px solid transparent;
            border-left-color: var(--charcoal);
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s var(--ease-out);
            pointer-events: none;
        }

        .app-nav:not(:hover) .nav-link:hover::before {
            opacity: 1;
            visibility: visible;
            transform: translateY(-50%) translateX(0);
        }

        .app-nav:not(:hover) .nav-link:hover::after {
            opacity: 1;
            visibility: visible;
        }

        .nav-footer {
            margin-top: auto;
            padding-top: 24px;
            border-top: 1px solid var(--cream-dark);
        }

        .nav-logout {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px;
            color: var(--charcoal-light);
            text-decoration: none;
            border-radius: 12px;
            font-size: 13px;
            transition: all 0.25s var(--ease-out);
            white-space: nowrap;
            overflow: hidden;
        }

        .nav-logout:hover {
            background: #FDF2F2;
            color: var(--error);
            transform: translateX(-4px);
        }

        .nav-logout svg {
            transition: all 0.25s var(--ease-out);
        }

        .nav-logout:hover svg {
            transform: scale(1.1);
        }

        .nav-logout svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .nav-logout span {
            opacity: 0;
            transition: opacity 0.25s var(--ease-out);
        }

        .app-nav:hover .nav-logout span {
            opacity: 1;
        }

        /* Main content area */
        .app-main {
            flex: 1;
            margin-right: var(--nav-width);
        }

        .page-container {
            max-width: 680px;
            margin: 0 auto;
            padding: 48px 32px 80px;
        }

        /* Page header */
        .page-header {
            margin-bottom: 40px;
            animation: fadeIn 0.6s var(--ease-out);
        }

        .page-header-greeting {
            font-size: 14px;
            color: var(--sage-dark);
            font-weight: 500;
            margin-bottom: 8px;
        }

        .page-header-title {
            font-family: var(--font-display);
            font-size: clamp(32px, 5vw, 42px);
            font-weight: 400;
            color: var(--charcoal);
            line-height: 1.15;
        }

        .page-header-subtitle {
            font-size: 15px;
            color: var(--charcoal);
            opacity: 0.7;
            margin-top: 8px;
        }

        /* ============================================
           CARDS
           ============================================ */
        .card {
            background: var(--white);
            border-radius: 20px;
            padding: 28px;
            margin-bottom: 20px;
            box-shadow:
                0 1px 2px rgba(0,0,0,0.03),
                0 4px 12px rgba(0,0,0,0.03);
            animation: fadeIn 0.6s var(--ease-out) both;
        }

        .card:nth-child(2) { animation-delay: 0.05s; }
        .card:nth-child(3) { animation-delay: 0.1s; }
        .card:nth-child(4) { animation-delay: 0.15s; }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .card-title {
            font-family: var(--font-display);
            font-size: 22px;
            font-weight: 500;
            color: var(--charcoal);
        }

        .card-icon {
            width: 44px;
            height: 44px;
            background: var(--cream);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--sage-dark);
        }

        .card-icon svg {
            width: 22px;
            height: 22px;
        }

        /* Status badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 16px;
            border-radius: 100px;
            font-size: 13px;
            font-weight: 600;
        }

        .status-success {
            background: #E8F5E8;
            color: var(--success);
        }

        .status-warning {
            background: #FFF8E8;
            color: #B8860B;
        }

        .status-error {
            background: #FDF2F2;
            color: var(--error);
        }

        /* Info rows */
        .info-row {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 16px 0;
            border-bottom: 1px solid var(--cream-dark);
        }

        .info-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .info-row:first-child {
            padding-top: 0;
        }

        .info-icon {
            width: 40px;
            height: 40px;
            background: var(--cream);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--sage-dark);
            flex-shrink: 0;
        }

        .info-icon svg {
            width: 20px;
            height: 20px;
        }

        .info-content h4 {
            font-size: 15px;
            font-weight: 600;
            color: var(--charcoal);
            margin-bottom: 2px;
        }

        .info-content p {
            font-size: 14px;
            color: var(--charcoal-light);
        }

        /* Quick action grid */
        .quick-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-top: 24px;
        }

        .quick-card {
            background: var(--white);
            border-radius: 16px;
            padding: 24px;
            text-decoration: none;
            color: inherit;
            text-align: center;
            box-shadow:
                0 1px 2px rgba(0,0,0,0.03),
                0 4px 12px rgba(0,0,0,0.03);
            transition: all 0.3s var(--ease-out);
            animation: fadeIn 0.6s var(--ease-out) both;
        }

        .quick-card:nth-child(1) { animation-delay: 0.1s; }
        .quick-card:nth-child(2) { animation-delay: 0.15s; }
        .quick-card:nth-child(3) { animation-delay: 0.2s; }
        .quick-card:nth-child(4) { animation-delay: 0.25s; }

        .quick-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.08);
        }

        .quick-card-icon {
            width: 52px;
            height: 52px;
            background: var(--cream);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px;
            color: var(--sage-dark);
            transition: all 0.3s var(--ease-out);
        }

        .quick-card:hover .quick-card-icon {
            background: var(--sage);
            color: var(--white);
        }

        .quick-card-icon svg {
            width: 26px;
            height: 26px;
        }

        .quick-card h3 {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .quick-card p {
            font-size: 13px;
            color: var(--charcoal-light);
        }

        /* ============================================
           FORMS
           ============================================ */
        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--charcoal);
            margin-bottom: 10px;
        }

        .form-input {
            width: 100%;
            padding: 16px 18px;
            font-family: var(--font-body);
            font-size: 15px;
            border: 2px solid var(--cream-dark);
            border-radius: 14px;
            background: var(--white);
            color: var(--charcoal);
            transition: all 0.3s var(--ease-out);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--sage);
            box-shadow: 0 0 0 4px rgba(168, 181, 160, 0.15);
        }

        .form-input::placeholder {
            color: #A8A39B;
        }

        select.form-input {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%234A4A4A'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            background-size: 18px;
            padding-right: 48px;
        }

        textarea.form-input {
            resize: vertical;
            min-height: 100px;
        }

        .form-hint {
            font-size: 13px;
            color: var(--charcoal-light);
            margin-top: 8px;
        }

        /* ============================================
           FLASH MESSAGES
           ============================================ */
        .flash {
            padding: 16px 20px;
            border-radius: 14px;
            margin-bottom: 24px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.4s var(--ease-out);
        }

        .flash-success {
            background: #E8F5E8;
            color: var(--success);
        }

        .flash-error {
            background: #FDF2F2;
            color: var(--error);
        }

        .flash svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        /* ============================================
           EMPTY STATE
           ============================================ */
        .empty-state {
            text-align: center;
            padding: 48px 24px;
        }

        .empty-state-icon {
            width: 64px;
            height: 64px;
            background: var(--cream);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: var(--charcoal-light);
        }

        .empty-state-icon svg {
            width: 28px;
            height: 28px;
        }

        .empty-state h3 {
            font-family: var(--font-display);
            font-size: 20px;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .empty-state p {
            font-size: 14px;
            color: var(--charcoal-light);
            max-width: 280px;
            margin: 0 auto;
        }

        /* ============================================
           MOBILE RESPONSIVE
           ============================================ */
        @media (max-width: 768px) {
            :root {
                --nav-width: 0px;
                --nav-width-expanded: 0px;
            }

            .app-nav {
                position: fixed;
                right: auto;
                left: 0;
                top: auto;
                bottom: 0;
                width: 100%;
                height: auto;
                flex-direction: row;
                padding: 8px 12px;
                padding-bottom: calc(8px + env(safe-area-inset-bottom));
                border-left: none;
                border-top: 1px solid rgba(0,0,0,0.06);
            }

            .app-nav:hover {
                width: 100%;
            }

            .nav-brand { display: none; }

            .nav-links {
                flex-direction: row;
                justify-content: space-around;
                width: 100%;
                gap: 0;
            }

            .nav-link {
                flex-direction: column;
                gap: 4px;
                padding: 10px 12px;
                font-size: 10px;
                border-radius: 10px;
            }

            .nav-link svg {
                width: 24px;
                height: 24px;
            }

            .nav-link span {
                opacity: 1;
            }

            .app-nav:hover .nav-link span {
                opacity: 1;
            }

            .nav-footer { display: none; }

            .app-main {
                margin-right: 0;
                padding-bottom: 90px;
            }

            .page-container {
                padding: 24px 20px 100px;
            }

            .quick-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Hide certain nav items on mobile */
        @media (max-width: 768px) {
            .nav-link[data-mobile-hide="true"] {
                display: none;
            }
        }
    </style>
</head>
<body>
    <?php if ($page === 'landing'): ?>
    <!-- ============================================
         LANDING PAGE
         ============================================ -->
    <div class="landing">
        <div class="landing-visual">
            <div class="visual-content">
                <?php if ($event['event_date']):
                    $date = new DateTime($event['event_date']);
                    $months = ['', 'Januar', 'Februar', 'Marts', 'April', 'Maj', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'December'];
                ?>
                <div class="visual-date"><?= $date->format('j') ?></div>
                <div class="visual-month"><?= $months[(int)$date->format('n')] ?></div>
                <div class="visual-year"><?= $date->format('Y') ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="landing-content">
            <div class="landing-header">
                <div class="landing-eyebrow"><?= htmlspecialchars($event['event_type_name'] ?? 'Invitation') ?></div>
                <h1 class="landing-title">
                    <?= htmlspecialchars($event['main_person_name'] ?? $event['name']) ?>
                    <?php if (!empty($event['secondary_person_name'])): ?>
                    <span style="opacity: 0.4; font-weight: 400;">&</span> <?= htmlspecialchars($event['secondary_person_name']) ?>
                    <?php endif; ?>
                </h1>
                <p class="landing-subtitle"><?= htmlspecialchars($event['location'] ?? '') ?></p>
            </div>

            <div class="login-card">
                <h2 class="serif">Velkommen</h2>
                <p>Indtast din personlige kode fra invitationen for at fortsætte.</p>

                <?php if (!empty($loginError)): ?>
                <div class="login-error">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <?= htmlspecialchars($loginError) ?>
                </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="code-input-wrapper">
                        <input type="text"
                               name="guest_code"
                               class="code-input"
                               placeholder="Din kode"
                               maxlength="8"
                               autocomplete="off"
                               autofocus
                               required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-full">
                        Fortsæt
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- ============================================
         LOGGED IN PAGES
         ============================================ -->
    <div class="app-layout">
        <main class="app-main">
            <div class="page-container">
                <?php if ($flash): ?>
                <div class="flash flash-<?= $flash['type'] ?>">
                    <?php if ($flash['type'] === 'success'): ?>
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <?php else: ?>
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    <?php endif; ?>
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
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

        <!-- Right-side Navigation -->
        <nav class="app-nav">
            <div class="nav-brand"><?= strtoupper(substr($event['main_person_name'] ?? 'E', 0, 1)) ?></div>

            <div class="nav-links">
                <?php foreach ($navItems as $item): ?>
                <a href="/e/<?= $slug ?>/<?= $item['slug'] ?>"
                   class="nav-link <?= $page === $item['slug'] ? 'active' : '' ?>"
                   data-tooltip="<?= $item['label'] ?>"
                   <?= in_array($item['slug'], ['menu']) ? 'data-mobile-hide="true"' : '' ?>>
                    <?php include __DIR__ . '/icons/' . $item['icon'] . '.svg.php'; ?>
                    <span><?= $item['label'] ?></span>
                </a>
                <?php endforeach; ?>
            </div>

            <div class="nav-footer">
                <a href="/e/<?= $slug ?>/?logout=1" class="nav-logout">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    <span>Log ud</span>
                </a>
            </div>
        </nav>
    </div>
    <?php endif; ?>
</body>
</html>

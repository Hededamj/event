<?php
/**
 * Invitation Preview API
 * Generates preview HTML for the invitation konfigurator
 */

require_once __DIR__ . '/../config/saas.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth-account.php';
require_once __DIR__ . '/../includes/invitation-functions.php';

// Require authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Ikke logget ind']);
    exit;
}

$accountId = getCurrentAccountId();
$db = getDB();

// Get event ID and verify access
$eventId = isset($_REQUEST['event_id']) ? (int)$_REQUEST['event_id'] : 0;

if (!$eventId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Manglende event_id']);
    exit;
}

// Check access to event
$stmt = $db->prepare("
    SELECT e.*, et.name as event_type_name
    FROM events e
    JOIN event_owners eo ON e.id = eo.event_id AND eo.account_id = ?
    LEFT JOIN event_types et ON e.event_type_id = et.id
    WHERE e.id = ?
");
$stmt->execute([$accountId, $eventId]);
$event = $stmt->fetch();

if (!$event) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Ingen adgang til dette arrangement']);
    exit;
}

// Get config (either saved or from POST for live preview)
$config = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $config = array_merge(getInvitationConfig($db, $eventId), $input ?? []);
} else {
    $config = getInvitationConfig($db, $eventId);
}

// Get images
$images = getInvitationImages($db, $eventId);
$heroImage = null;
$galleryImages = [];

foreach ($images as $image) {
    $image['url'] = '/uploads/invitations/' . $image['filename'];
    if ($image['image_role'] === 'hero') {
        $heroImage = $image;
    } else {
        $galleryImages[] = $image;
    }
}

// Sample guest for preview
$sampleGuest = [
    'name' => 'Mormor & Morfar',
    'guest_names' => json_encode(['Mormor', 'Morfar'])
];

// Generate personalized greeting
$greeting = personalizeGreeting($config['greeting_template'] ?? 'Kære {guest_name}', $sampleGuest);

// Get layout and fonts
$layoutStyle = $config['layout_style'] ?? 'split';
$fonts = getInvitationFontFamily($config['font_style'] ?? 'elegant');

// Generate CSS variables
$cssVars = getInvitationCssVariables($config);

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

// Output HTML preview
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitation Preview</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=DM+Sans:wght@400;500;600&family=Playfair+Display:wght@400;500;600&family=Inter:wght@400;500;600&family=Quicksand:wght@400;500;600&family=Nunito:wght@400;500;600&family=Lora:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        <?= $cssVars ?>

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --font-display: <?= $fonts['display'] ?>;
            --font-body: <?= $fonts['body'] ?>;
        }

        body {
            font-family: var(--font-body);
            background: var(--inv-background);
            color: var(--inv-text);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        .preview-container {
            min-height: 100vh;
        }

        /* Split Layout */
        .layout-split {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 100vh;
        }

        .layout-split .hero-section {
            position: sticky;
            top: 0;
            height: 100vh;
        }

        .layout-split .content-section {
            padding: 60px 48px;
        }

        /* Centered Layout */
        .layout-centered {
            max-width: 680px;
            margin: 0 auto;
            padding: 60px 24px;
        }

        .layout-centered .hero-section {
            border-radius: 24px;
            overflow: hidden;
            margin-bottom: 48px;
            aspect-ratio: 16/10;
        }

        /* Fullscreen Layout */
        .layout-fullscreen .hero-section {
            position: relative;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .layout-fullscreen .hero-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to bottom, rgba(0,0,0,0.3), rgba(0,0,0,0.6));
        }

        .layout-fullscreen .hero-content {
            position: relative;
            z-index: 1;
            color: #fff;
            text-align: center;
            padding: 48px;
        }

        .layout-fullscreen .content-section {
            max-width: 680px;
            margin: 0 auto;
            padding: 80px 24px;
        }

        /* Minimal Layout */
        .layout-minimal {
            max-width: 600px;
            margin: 0 auto;
            padding: 80px 24px;
            text-align: center;
        }

        .layout-minimal .hero-section {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 48px;
        }

        /* Classic Layout */
        .layout-classic {
            max-width: 520px;
            margin: 60px auto;
            background: #fff;
            border: 2px solid var(--inv-secondary);
            padding: 48px;
        }

        .layout-classic .hero-section {
            margin: -48px -48px 32px;
            aspect-ratio: 3/2;
        }

        /* Hero Image */
        .hero-section {
            background-size: cover;
            background-position: center;
            background-color: var(--inv-secondary);
        }

        .hero-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #fff;
            font-size: 14px;
            opacity: 0.7;
        }

        /* Content Sections */
        .greeting {
            font-family: var(--font-display);
            font-size: clamp(28px, 4vw, 42px);
            font-weight: 400;
            margin-bottom: 24px;
            color: var(--inv-primary);
        }

        .headline {
            font-family: var(--font-display);
            font-size: clamp(36px, 5vw, 56px);
            font-weight: 500;
            line-height: 1.1;
            margin-bottom: 32px;
            color: var(--inv-primary);
        }

        .message {
            font-size: 16px;
            line-height: 1.8;
            margin-bottom: 40px;
            white-space: pre-line;
        }

        .event-details {
            background: rgba(0,0,0,0.03);
            border-radius: 16px;
            padding: 28px;
            margin-bottom: 40px;
        }

        .detail-item {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 12px 0;
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-icon {
            width: 40px;
            height: 40px;
            background: var(--inv-secondary);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            flex-shrink: 0;
        }

        .detail-icon svg {
            width: 20px;
            height: 20px;
        }

        .detail-content h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .detail-content p {
            font-size: 14px;
            opacity: 0.7;
        }

        /* Countdown */
        .countdown {
            display: flex;
            justify-content: center;
            gap: 24px;
            margin: 40px 0;
            text-align: center;
        }

        .countdown-item {
            background: var(--inv-secondary);
            color: #fff;
            padding: 20px 24px;
            border-radius: 12px;
            min-width: 80px;
        }

        .countdown-value {
            font-family: var(--font-display);
            font-size: 36px;
            font-weight: 500;
            line-height: 1;
        }

        .countdown-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-top: 4px;
            opacity: 0.8;
        }

        /* Gallery */
        .gallery {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin: 40px 0;
        }

        .gallery-item {
            aspect-ratio: 1;
            border-radius: 12px;
            overflow: hidden;
            background-size: cover;
            background-position: center;
            background-color: var(--inv-secondary);
        }

        /* RSVP Section */
        .rsvp-section {
            background: var(--inv-secondary);
            color: #fff;
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            margin-top: 40px;
        }

        .rsvp-section h3 {
            font-family: var(--font-display);
            font-size: 28px;
            margin-bottom: 12px;
        }

        .rsvp-section p {
            opacity: 0.9;
            margin-bottom: 24px;
        }

        .rsvp-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #fff;
            color: var(--inv-primary);
            padding: 16px 32px;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: transform 0.2s;
        }

        .rsvp-btn:hover {
            transform: translateY(-2px);
        }

        /* Closing */
        .closing {
            text-align: center;
            font-family: var(--font-display);
            font-size: 20px;
            font-style: italic;
            margin-top: 48px;
            opacity: 0.8;
        }

        @media (max-width: 768px) {
            .layout-split {
                grid-template-columns: 1fr;
            }

            .layout-split .hero-section {
                position: relative;
                height: 50vh;
            }

            .layout-split .content-section {
                padding: 32px 20px;
            }

            .countdown {
                gap: 12px;
            }

            .countdown-item {
                padding: 16px;
                min-width: 60px;
            }

            .countdown-value {
                font-size: 28px;
            }

            .gallery {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="preview-container layout-<?= htmlspecialchars($layoutStyle) ?>">
        <?php if ($layoutStyle === 'split'): ?>
            <!-- Split Layout -->
            <div class="hero-section" style="<?= $heroImage ? 'background-image: url(' . htmlspecialchars($heroImage['url']) . ')' : '' ?>">
                <?php if (!$heroImage): ?>
                <div class="hero-placeholder">Tilføj et hovedbillede</div>
                <?php endif; ?>
            </div>
            <div class="content-section">
                <?php include __DIR__ . '/../e/partials/invitation-content.php'; ?>
            </div>

        <?php elseif ($layoutStyle === 'centered'): ?>
            <!-- Centered Layout -->
            <?php if ($heroImage || true): ?>
            <div class="hero-section" style="<?= $heroImage ? 'background-image: url(' . htmlspecialchars($heroImage['url']) . ')' : '' ?>">
                <?php if (!$heroImage): ?>
                <div class="hero-placeholder">Tilføj et hovedbillede</div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php include __DIR__ . '/../e/partials/invitation-content.php'; ?>

        <?php elseif ($layoutStyle === 'fullscreen'): ?>
            <!-- Fullscreen Layout -->
            <div class="hero-section" style="<?= $heroImage ? 'background-image: url(' . htmlspecialchars($heroImage['url']) . ')' : '' ?>">
                <div class="hero-overlay"></div>
                <div class="hero-content">
                    <p class="greeting"><?= htmlspecialchars($greeting) ?></p>
                    <h1 class="headline"><?= htmlspecialchars($config['headline_text'] ?? $event['name']) ?></h1>
                    <?php if ($eventDate): ?>
                    <p style="font-size: 18px; opacity: 0.9;">
                        <?= ucfirst($eventDate['weekday']) ?> d. <?= $eventDate['day'] ?>. <?= $eventDate['month'] ?> <?= $eventDate['year'] ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="content-section">
                <?php include __DIR__ . '/../e/partials/invitation-content.php'; ?>
            </div>

        <?php elseif ($layoutStyle === 'minimal'): ?>
            <!-- Minimal Layout -->
            <?php if ($heroImage): ?>
            <div class="hero-section" style="background-image: url(<?= htmlspecialchars($heroImage['url']) ?>)"></div>
            <?php endif; ?>
            <?php include __DIR__ . '/../e/partials/invitation-content.php'; ?>

        <?php else: ?>
            <!-- Classic Layout -->
            <?php if ($heroImage): ?>
            <div class="hero-section" style="background-image: url(<?= htmlspecialchars($heroImage['url']) ?>)"></div>
            <?php endif; ?>
            <?php include __DIR__ . '/../e/partials/invitation-content.php'; ?>
        <?php endif; ?>
    </div>
</body>
</html>

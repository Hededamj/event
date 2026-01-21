<?php
/**
 * Generate Static Invitation Pages
 * Creates individual HTML files for each guest
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Require login
requireLogin();

$db = getDB();
$eventId = getCurrentEventId();

// Get event details
$stmt = $db->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch();

// Get all guests
$stmt = $db->prepare("SELECT * FROM guests WHERE event_id = ? ORDER BY name");
$stmt->execute([$eventId]);
$guests = $stmt->fetchAll();

$theme = $event['theme'] ?? 'girl';
$generated = [];
$errors = [];

// Create invitations directory if not exists
$invitationsDir = __DIR__ . '/../invitationer';
if (!is_dir($invitationsDir)) {
    mkdir($invitationsDir, 0755, true);
}

// Format date in Danish
function formatEventDateDanish($date) {
    $months = [
        1 => 'januar', 2 => 'februar', 3 => 'marts', 4 => 'april',
        5 => 'maj', 6 => 'juni', 7 => 'juli', 8 => 'august',
        9 => 'september', 10 => 'oktober', 11 => 'november', 12 => 'december'
    ];
    $days = ['søndag', 'mandag', 'tirsdag', 'onsdag', 'torsdag', 'fredag', 'lørdag'];

    $timestamp = strtotime($date);
    $dayName = $days[date('w', $timestamp)];
    $day = date('j', $timestamp);
    $month = $months[(int)date('n', $timestamp)];
    $year = date('Y', $timestamp);

    return ucfirst($dayName) . ' d. ' . $day . '. ' . $month . ' ' . $year;
}

// Generate slug from name
function slugify($name) {
    $slug = mb_strtolower($name, 'UTF-8');
    $slug = str_replace(['æ', 'ø', 'å', 'Æ', 'Ø', 'Å'], ['ae', 'oe', 'aa', 'ae', 'oe', 'aa'], $slug);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

// Handle generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    foreach ($guests as $guest) {
        $filename = $guest['unique_code'] . '.html';
        $filepath = $invitationsDir . '/' . $filename;

        $html = generateInvitationHTML($guest, $event, $theme);

        if (file_put_contents($filepath, $html)) {
            $generated[] = [
                'name' => $guest['name'],
                'code' => $guest['unique_code'],
                'file' => $filename,
                'url' => 'https://hededam.dk/sofie/invitationer/' . $filename
            ];
        } else {
            $errors[] = "Kunne ikke oprette fil for: " . $guest['name'];
        }
    }
}

function generateInvitationHTML($guest, $event, $theme) {
    $guestName = htmlspecialchars($guest['name'], ENT_QUOTES, 'UTF-8');
    $confirmandName = htmlspecialchars($event['confirmand_name'], ENT_QUOTES, 'UTF-8');
    $eventName = htmlspecialchars($event['name'], ENT_QUOTES, 'UTF-8');
    $locationRaw = $event['location'] ?? '';
    $location = nl2br(htmlspecialchars($locationRaw, ENT_QUOTES, 'UTF-8'));
    $welcomeText = nl2br(htmlspecialchars($event['welcome_text'] ?? '', ENT_QUOTES, 'UTF-8'));
    $eventDate = formatEventDateDanish($event['event_date']);
    $eventTime = $event['event_time'] ? date('H:i', strtotime($event['event_time'])) : '';
    $guestCode = $guest['unique_code'];

    // Google Maps link
    $mapsUrl = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($locationRaw);

    // Calendar event (Google Calendar)
    $calendarTitle = $confirmandName . 's ' . $eventName;
    $startDate = date('Ymd', strtotime($event['event_date']));
    $startTime = $event['event_time'] ? date('His', strtotime($event['event_time'])) : '120000';
    $endTime = $event['event_time'] ? date('His', strtotime($event['event_time'] . ' +3 hours')) : '150000';
    $calendarUrl = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
        . '&text=' . urlencode($calendarTitle)
        . '&dates=' . $startDate . 'T' . $startTime . '/' . $startDate . 'T' . $endTime
        . '&location=' . urlencode($locationRaw)
        . '&details=' . urlencode('Invitation til ' . $calendarTitle);

    $basePath = '/sofie';

    return <<<HTML
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitation til {$guestName} - {$confirmandName}s {$eventName}</title>
    <meta name="description" content="Kære {$guestName}, du er inviteret til {$confirmandName}s {$eventName}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">

    <!-- Styles -->
    <link rel="stylesheet" href="{$basePath}/assets/css/main.css">
    <link rel="stylesheet" href="{$basePath}/assets/css/theme-{$theme}.css">

    <style>
        .invitation {
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }

        .invitation__bg {
            position: fixed;
            inset: 0;
            z-index: -1;
            background: var(--color-bg);
        }

        .invitation__bg::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(
                ellipse at 30% 20%,
                var(--color-primary-pale) 0%,
                transparent 50%
            ),
            radial-gradient(
                ellipse at 70% 80%,
                var(--color-accent-pale) 0%,
                transparent 40%
            );
            animation: bgFloat 20s ease-in-out infinite;
        }

        @keyframes bgFloat {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(2%, 1%) rotate(1deg); }
            66% { transform: translate(-1%, 2%) rotate(-1deg); }
        }

        .invitation__content {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: var(--space-xl) var(--space-md);
            position: relative;
        }

        .invitation__card {
            max-width: 500px;
            opacity: 0;
            animation: fadeInUp 1s var(--ease-out-expo) 0.2s forwards;
        }

        .invitation__greeting {
            font-family: 'Cormorant Garamond', serif;
            font-size: var(--text-xl);
            font-style: italic;
            color: var(--color-accent);
            margin-bottom: var(--space-sm);
        }

        .invitation__eyebrow {
            font-size: var(--text-xs);
            text-transform: uppercase;
            letter-spacing: 0.2em;
            color: var(--color-accent);
            margin-bottom: var(--space-sm);
            font-weight: 500;
        }

        .invitation__title {
            font-family: 'Cormorant Garamond', serif;
            font-size: var(--text-display);
            font-weight: 400;
            color: var(--color-primary-deep);
            margin-bottom: var(--space-xs);
            line-height: 0.95;
        }

        .invitation__title em {
            font-style: italic;
            color: var(--color-text);
        }

        .invitation__subtitle {
            font-family: 'Cormorant Garamond', serif;
            font-size: var(--text-xl);
            font-weight: 400;
            color: var(--color-text-soft);
            margin-bottom: var(--space-md);
        }

        .invitation__date {
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
            padding: var(--space-xs) var(--space-md);
            background: var(--color-surface);
            border-radius: var(--radius-full);
            box-shadow: var(--shadow-sm);
            font-family: 'Cormorant Garamond', serif;
            font-size: var(--text-lg);
            color: var(--color-text);
            margin-bottom: var(--space-md);
        }

        .invitation__address {
            display: flex;
            align-items: flex-start;
            justify-content: center;
            gap: var(--space-sm);
            margin-bottom: var(--space-lg);
            padding: var(--space-md) var(--space-lg);
            background: var(--color-surface);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
        }

        .invitation__address-icon {
            color: var(--color-primary);
            flex-shrink: 0;
            margin-top: 2px;
        }

        .invitation__address-text {
            font-style: normal;
            font-family: 'Cormorant Garamond', serif;
            font-size: var(--text-base);
            color: var(--color-text);
            line-height: 1.5;
            text-align: left;
        }

        /* Clickable date and address */
        .invitation__date--link,
        .invitation__address--link {
            text-decoration: none;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .invitation__date--link:hover,
        .invitation__address--link:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .invitation__date-hint,
        .invitation__address-hint {
            display: block;
            font-size: 0.7rem;
            font-family: 'Outfit', sans-serif;
            color: var(--color-accent);
            margin-top: 0.5rem;
            opacity: 0.7;
            transition: opacity 0.2s ease;
        }

        .invitation__date--link:hover .invitation__date-hint,
        .invitation__address--link:hover .invitation__address-hint {
            opacity: 1;
        }

        .invitation__welcome {
            max-width: 400px;
            margin: 0 auto var(--space-lg);
            line-height: 1.8;
            color: var(--color-text-soft);
        }

        .invitation__cta {
            background: var(--color-surface);
            border-radius: var(--radius-xl);
            padding: var(--space-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--color-border-soft);
            max-width: 320px;
            margin: 0 auto;
            opacity: 0;
            animation: fadeInUp 1s var(--ease-out-expo) 0.5s forwards;
        }

        .invitation__cta-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: var(--text-lg);
            font-weight: 500;
            color: var(--color-text);
            margin-bottom: var(--space-sm);
        }

        .invitation__footer {
            text-align: center;
            padding: var(--space-md);
            color: var(--color-text-muted);
            font-size: var(--text-xs);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 640px) {
            .invitation__title {
                font-size: var(--text-3xl);
            }
        }
    </style>
</head>
<body>
    <div class="invitation">
        <div class="invitation__bg"></div>

        <main class="invitation__content">
            <div class="invitation__card">
                <p class="invitation__greeting">Kære {$guestName}</p>

                <p class="invitation__eyebrow">Du er inviteret til</p>

                <h1 class="invitation__title">
                    {$confirmandName}<em>s</em>
                </h1>

                <p class="invitation__subtitle">{$eventName}</p>

                <a href="{$calendarUrl}" target="_blank" class="invitation__date invitation__date--link">
                    <span style="color: var(--color-accent);">✦</span>
                    <span>{$eventDate}</span>
                    <span>kl. {$eventTime}</span>
                    <span class="invitation__date-hint">Tilføj til kalender</span>
                </a>

                <a href="{$mapsUrl}" target="_blank" class="invitation__address invitation__address--link">
                    <div class="invitation__address-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                            <circle cx="12" cy="10" r="3"/>
                        </svg>
                    </div>
                    <address class="invitation__address-text">
                        {$location}
                        <span class="invitation__address-hint">Åbn i Google Maps</span>
                    </address>
                </a>

                <p class="invitation__welcome">{$welcomeText}</p>

                <div class="invitation__cta">
                    <p class="invitation__cta-title">Giv venligst besked</p>
                    <a href="{$basePath}/index.php?kode={$guestCode}" class="btn btn--primary btn--block btn--large">
                        Gå til tilmelding
                    </a>
                </div>
            </div>
        </main>

        <footer class="invitation__footer">
            <p>Vi glæder os til at se dig!</p>
        </footer>
    </div>
</body>
</html>
HTML;
}

// Get theme
$theme = $event['theme'] ?? 'girl';
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generér invitationssider</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Outfit', sans-serif;
            background: #f8f9fa;
            padding: 2rem;
            line-height: 1.6;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        .subtitle {
            color: #666;
            margin-bottom: 1.5rem;
        }
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
            border: none;
            font-family: inherit;
            display: inline-block;
        }
        .btn--primary {
            background: #8B7355;
            color: white;
        }
        .btn--secondary {
            background: white;
            color: #333;
            border: 1px solid #ddd;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: 1px solid #e0e0e0;
        }
        .success {
            background: #e8f5e9;
            border-color: #4caf50;
            color: #2e7d32;
        }
        .error {
            background: #ffebee;
            border-color: #f44336;
            color: #c62828;
        }
        .links-list {
            margin-top: 1rem;
        }
        .link-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            border-bottom: 1px solid #f0f0f0;
        }
        .link-item:last-child {
            border-bottom: none;
        }
        .link-url {
            font-family: monospace;
            font-size: 0.8rem;
            color: #666;
            word-break: break-all;
        }
        .copy-btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
        }
        .copy-btn:hover {
            background: #e0e0e0;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 1rem;
            color: #666;
            text-decoration: none;
        }
        .back-link:hover {
            color: #333;
        }
        textarea {
            width: 100%;
            height: 200px;
            font-family: monospace;
            font-size: 0.8rem;
            padding: 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="<?= BASE_PATH ?>/admin/guests.php" class="back-link">← Tilbage til gæsteliste</a>

        <h1>Generér invitationssider</h1>
        <p class="subtitle"><?= count($guests) ?> gæster vil få deres egen side</p>

        <?php if (!empty($errors)): ?>
            <div class="card error">
                <strong>Fejl:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($generated)): ?>
            <div class="card success">
                <strong><?= count($generated) ?> sider genereret!</strong>
                <div class="links-list">
                    <?php foreach ($generated as $item): ?>
                        <div class="link-item">
                            <div>
                                <strong><?= htmlspecialchars($item['name']) ?></strong><br>
                                <span class="link-url"><?= htmlspecialchars($item['url']) ?></span>
                            </div>
                            <button class="copy-btn" onclick="copyLink('<?= htmlspecialchars($item['url']) ?>')">Kopiér</button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <textarea id="all-links" readonly><?php foreach ($generated as $item): ?>
<?= $item['name'] ?>
<?= $item['url'] ?>

<?php endforeach; ?></textarea>
                <button class="btn btn--secondary" style="margin-top: 0.5rem;" onclick="copyAll()">Kopiér alle links</button>
            </div>
        <?php else: ?>
            <div class="card">
                <p>Dette vil oprette en personlig invitationsside for hver gæst.</p>
                <p style="margin-top: 0.5rem; color: #666;">
                    F.eks. <code>hededam.dk/sofie/invitationer/farmor.html</code>
                </p>

                <form method="POST" style="margin-top: 1.5rem;">
                    <button type="submit" name="generate" value="1" class="btn btn--primary">
                        Generér <?= count($guests) ?> invitationssider
                    </button>
                </form>
            </div>

            <div class="card" style="margin-top: 1rem;">
                <strong>Forhåndsvisning af gæster:</strong>
                <ul style="margin-top: 0.5rem; padding-left: 1.5rem;">
                    <?php foreach (array_slice($guests, 0, 10) as $guest): ?>
                        <li><?= htmlspecialchars($guest['name']) ?> → <?= htmlspecialchars($guest['unique_code']) ?>.html</li>
                    <?php endforeach; ?>
                    <?php if (count($guests) > 10): ?>
                        <li>... og <?= count($guests) - 10 ?> flere</li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <script>
    function copyLink(url) {
        navigator.clipboard.writeText(url).then(() => {
            event.target.textContent = 'Kopieret!';
            setTimeout(() => event.target.textContent = 'Kopiér', 1500);
        });
    }

    function copyAll() {
        const text = document.getElementById('all-links').value;
        navigator.clipboard.writeText(text).then(() => {
            alert('Alle links kopieret!');
        });
    }
    </script>
</body>
</html>

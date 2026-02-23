<?php
/**
 * Toastmaster Punch-Out Page
 * Separate access for toastmasters to view and manage the program
 * Mobile-first design with print functionality
 */
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDB();

// Messages for forgot code
$forgotMessage = '';
$forgotError = '';

// Handle forgot code request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'forgot_code') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $forgotError = 'Indtast venligst din email';
    } else {
        // Look up access by email
        $stmt = $db->prepare("
            SELECT ta.*, e.name as event_name, e.confirmand_name
            FROM toastmaster_access ta
            JOIN events e ON ta.event_id = e.id
            WHERE ta.email = ?
        ");
        $stmt->execute([$email]);
        $access = $stmt->fetch();

        if ($access) {
            // Send email with code
            $to = $access['email'];
            $subject = "Din toastmaster-kode til " . $access['confirmand_name'] . "s " . $access['event_name'];
            $link = getBaseUrl() . "/toastmaster/?kode=" . $access['access_code'];

            $message = "Hej " . $access['name'] . ",\n\n";
            $message .= "Du har bedt om din toastmaster-kode.\n\n";
            $message .= "Din kode er: " . $access['access_code'] . "\n\n";
            $message .= "Du kan også bruge dette direkte link:\n" . $link . "\n\n";
            $message .= "Venlig hilsen,\n";
            $message .= $access['confirmand_name'] . "s " . $access['event_name'];

            $headers = "From: noreply@hededam.dk\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

            if (mail($to, $subject, $message, $headers)) {
                $forgotMessage = 'Koden er sendt til din email!';
            } else {
                $forgotError = 'Kunne ikke sende email. Kontakt arrangøren.';
            }
        } else {
            // Don't reveal if email exists or not
            $forgotMessage = 'Hvis denne email er registreret, vil du modtage koden.';
        }
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    unset($_SESSION['toastmaster_code']);
    unset($_SESSION['toastmaster_event_id']);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Check for access code
$accessCode = $_GET['kode'] ?? $_SESSION['toastmaster_code'] ?? null;
$toastmaster = null;
$event = null;
$eventId = null;

if ($accessCode) {
    // Validate access code
    $stmt = $db->prepare("
        SELECT ta.*, e.*
        FROM toastmaster_access ta
        JOIN events e ON ta.event_id = e.id
        WHERE ta.access_code = ?
    ");
    $stmt->execute([$accessCode]);
    $result = $stmt->fetch();

    if ($result) {
        $toastmaster = [
            'id' => $result['id'],
            'name' => $result['name'],
            'access_code' => $result['access_code']
        ];
        $eventId = $result['event_id'];
        $event = $result;

        // Store in session
        $_SESSION['toastmaster_code'] = $accessCode;
        $_SESSION['toastmaster_event_id'] = $eventId;
    }
}

// Ensure messages table exists
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS toastmaster_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            guest_id INT DEFAULT NULL,
            guest_name VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            is_from_toastmaster TINYINT(1) DEFAULT 0,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
        )
    ");
} catch (Exception $e) {
    error_log('Failed to create toastmaster_messages table in toastmaster/index: ' . $e->getMessage());
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $eventId) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $status = $_POST['status'] ?? 'pending';
        if ($itemId && in_array($status, ['pending', 'approved', 'completed'])) {
            $stmt = $db->prepare("UPDATE toastmaster_items SET status = ? WHERE id = ? AND event_id = ?");
            $stmt->execute([$status, $itemId, $eventId]);
            echo json_encode(['success' => true]);
            exit;
        }
    }

    if ($action === 'update_order') {
        $order = json_decode($_POST['order'] ?? '[]', true);
        if (is_array($order)) {
            foreach ($order as $index => $itemId) {
                $stmt = $db->prepare("UPDATE toastmaster_items SET sort_order = ? WHERE id = ? AND event_id = ?");
                $stmt->execute([$index, $itemId, $eventId]);
            }
            echo json_encode(['success' => true]);
            exit;
        }
    }

    if ($action === 'send_message') {
        $guestId = (int)($_POST['guest_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        if ($guestId && $message) {
            // Get guest name for context
            $stmt = $db->prepare("SELECT guest_name FROM toastmaster_messages WHERE guest_id = ? LIMIT 1");
            $stmt->execute([$guestId]);
            $guestName = $stmt->fetchColumn() ?: 'Gæst';

            $stmt = $db->prepare("
                INSERT INTO toastmaster_messages (event_id, guest_id, guest_name, message, is_from_toastmaster)
                VALUES (?, ?, ?, ?, 1)
            ");
            $stmt->execute([$eventId, $guestId, $guestName, $message]);
            echo json_encode(['success' => true]);
            exit;
        }
    }

    echo json_encode(['success' => false]);
    exit;
}

// If no valid access, show login form
if (!$toastmaster):
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Toastmaster Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/sofie/assets/css/main.css">
    <link rel="stylesheet" href="/sofie/assets/css/theme-girl.css">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#D4A5A5">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: linear-gradient(135deg, var(--color-bg) 0%, var(--color-primary-pale) 100%);
        }
        .login-card {
            background: var(--color-surface);
            border-radius: var(--radius-xl);
            padding: 2rem;
            max-width: 360px;
            width: 100%;
            text-align: center;
            box-shadow: var(--shadow-lg);
        }
        .login-icon {
            font-size: 3.5rem;
            margin-bottom: 1rem;
        }
        .login-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .login-subtitle {
            color: var(--color-text-muted);
            margin-bottom: 1.5rem;
        }
        .login-input {
            text-align: center;
            font-size: 1.25rem;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            padding: 1rem;
        }
        .alert--error {
            background: #fef2f2;
            color: #dc2626;
            padding: 0.75rem 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        .alert--success {
            background: #f0fdf4;
            color: #16a34a;
            padding: 0.75rem 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        .forgot-link {
            display: block;
            width: 100%;
            margin-top: 1rem;
            padding: 0.5rem;
            background: none;
            border: none;
            color: var(--color-text-muted);
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: underline;
        }
        .forgot-link:hover {
            color: var(--color-primary-deep);
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-icon">🎤</div>
        <h1 class="login-title">Toastmaster</h1>
        <p class="login-subtitle">Indtast din adgangskode</p>

        <?php if ($accessCode && !$toastmaster): ?>
            <div class="alert--error">Ugyldig adgangskode</div>
        <?php endif; ?>

        <?php if ($forgotMessage): ?>
            <div class="alert--success"><?= escape($forgotMessage) ?></div>
        <?php endif; ?>

        <?php if ($forgotError): ?>
            <div class="alert--error"><?= escape($forgotError) ?></div>
        <?php endif; ?>

        <!-- Login Form -->
        <div id="login-form">
            <form method="GET">
                <div class="form-group">
                    <input type="text"
                           name="kode"
                           class="form-input login-input"
                           placeholder="KODE"
                           autocomplete="off"
                           autocapitalize="characters"
                           required>
                </div>
                <button type="submit" class="btn btn--primary btn--block" style="padding: 1rem;">Log ind</button>
            </form>
            <button onclick="showForgotForm()" class="forgot-link">Glemt kode?</button>
        </div>

        <!-- Forgot Code Form -->
        <div id="forgot-form" style="display: none;">
            <form method="POST">
                <input type="hidden" name="action" value="forgot_code">
                <p class="login-subtitle" style="margin-bottom: 1rem;">Indtast din email, så sender vi koden til dig.</p>
                <div class="form-group">
                    <input type="email"
                           name="email"
                           class="form-input"
                           placeholder="Din email"
                           required
                           style="text-align: center;">
                </div>
                <button type="submit" class="btn btn--primary btn--block" style="padding: 1rem;">Send kode</button>
            </form>
            <button onclick="showLoginForm()" class="forgot-link">Tilbage til login</button>
        </div>
    </div>

    <script>
    function showForgotForm() {
        document.getElementById('login-form').style.display = 'none';
        document.getElementById('forgot-form').style.display = 'block';
    }
    function showLoginForm() {
        document.getElementById('forgot-form').style.display = 'none';
        document.getElementById('login-form').style.display = 'block';
    }
    <?php if ($forgotMessage || $forgotError): ?>
    // Show forgot form if there was a message
    showForgotForm();
    <?php endif; ?>
    </script>
</body>
</html>
<?php
exit;
endif;

// Get all items for this event
$stmt = $db->prepare("SELECT * FROM toastmaster_items WHERE event_id = ? ORDER BY sort_order, created_at");
$stmt->execute([$eventId]);
$items = $stmt->fetchAll();

// Stats
$totalItems = count($items);
$completedItems = count(array_filter($items, fn($i) => $i['status'] === 'completed'));
$totalDuration = array_sum(array_column($items, 'duration_minutes'));
$remainingDuration = array_sum(array_map(fn($i) => $i['status'] !== 'completed' ? $i['duration_minutes'] : 0, $items));

// Get messages grouped by guest
$stmt = $db->prepare("
    SELECT guest_id, guest_name, MAX(created_at) as last_message,
           SUM(CASE WHEN is_from_toastmaster = 0 AND is_read = 0 THEN 1 ELSE 0 END) as unread_count,
           COUNT(*) as total_messages
    FROM toastmaster_messages
    WHERE event_id = ? AND guest_id IS NOT NULL
    GROUP BY guest_id, guest_name
    ORDER BY last_message DESC
");
$stmt->execute([$eventId]);
$conversations = $stmt->fetchAll();

// Count total unread messages
$totalUnread = array_sum(array_column($conversations, 'unread_count'));

// Active tab
$activeTab = $_GET['view'] ?? 'program';

// Item type labels
$typeLabels = [
    'tale' => ['label' => 'Tale', 'icon' => '🎤'],
    'sang' => ['label' => 'Sang', 'icon' => '🎵'],
    'sketch' => ['label' => 'Sketch', 'icon' => '🎭'],
    'quiz' => ['label' => 'Quiz', 'icon' => '❓'],
    'leg' => ['label' => 'Leg', 'icon' => '🎲'],
    'musik' => ['label' => 'Musik', 'icon' => '🎸'],
    'andet' => ['label' => 'Andet', 'icon' => '✨']
];

// Event date
$eventDate = new DateTime($event['event_date']);
$dateFormatted = $eventDate->format('j. F Y');
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Toastmaster - <?= escape($event['confirmand_name']) ?>s <?= escape($event['name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/sofie/assets/css/main.css">
    <link rel="stylesheet" href="/sofie/assets/css/theme-girl.css">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="theme-color" content="#D4A5A5">
    <link rel="apple-touch-icon" href="/sofie/assets/images/icon-192.png">
</head>
<body>

<div class="tm-app">
    <!-- Header -->
    <header class="tm-header">
        <div class="tm-header__brand">
            <span class="tm-header__icon">🎤</span>
            <div class="tm-header__info">
                <strong><?= escape($toastmaster['name']) ?></strong>
                <span><?= escape($event['confirmand_name']) ?>s <?= escape($event['name']) ?></span>
            </div>
        </div>
        <div class="tm-header__actions">
            <button onclick="window.print()" class="tm-header__btn" title="Print program">
                🖨️
            </button>
            <a href="?logout=1" class="tm-header__btn" title="Log ud">
                🚪
            </a>
        </div>
    </header>

    <!-- Stats Cards -->
    <div class="tm-stats">
        <div class="tm-stat">
            <div class="tm-stat__value"><?= $completedItems ?>/<?= $totalItems ?></div>
            <div class="tm-stat__label">Gennemført</div>
        </div>
        <div class="tm-stat">
            <div class="tm-stat__value">~<?= $remainingDuration ?> min</div>
            <div class="tm-stat__label">Tilbage</div>
        </div>
    </div>

    <!-- Progress Bar -->
    <div class="tm-progress">
        <div class="tm-progress__bar" style="width: <?= $totalItems > 0 ? ($completedItems / $totalItems * 100) : 0 ?>%"></div>
    </div>

    <!-- Tab Navigation -->
    <div class="tm-tabs">
        <a href="?view=program" class="tm-tab <?= $activeTab === 'program' ? 'tm-tab--active' : '' ?>">
            Program
        </a>
        <a href="?view=messages" class="tm-tab <?= $activeTab === 'messages' ? 'tm-tab--active' : '' ?>">
            Beskeder
            <?php if ($totalUnread > 0): ?>
                <span class="tm-tab__badge"><?= $totalUnread ?></span>
            <?php endif; ?>
        </a>
    </div>

    <!-- Print Header (only visible when printing) -->
    <div class="print-header">
        <h1><?= escape($event['confirmand_name']) ?>s <?= escape($event['name']) ?></h1>
        <p><?= $dateFormatted ?></p>
        <p>Program - <?= $totalItems ?> indslag, ca. <?= $totalDuration ?> minutter</p>
    </div>

    <!-- Items List (always rendered for print) -->
    <div class="tm-list <?= $activeTab !== 'program' ? 'tm-list--hidden' : '' ?>" id="items-list">
        <?php if (empty($items)): ?>
            <div class="tm-empty">
                <div class="tm-empty__icon">📋</div>
                <p>Ingen indslag tilmeldt endnu</p>
            </div>
        <?php else: ?>
            <?php $num = 1; foreach ($items as $item): ?>
                <div class="tm-card tm-card--<?= $item['status'] ?>" data-id="<?= $item['id'] ?>">
                    <div class="tm-card__number"><?= $num++ ?></div>
                    <div class="tm-card__drag" title="Træk for at flytte">⋮⋮</div>

                    <div class="tm-card__icon"><?= $typeLabels[$item['item_type']]['icon'] ?? '✨' ?></div>

                    <div class="tm-card__content">
                        <div class="tm-card__title">
                            <?= escape($item['title'] ?: $typeLabels[$item['item_type']]['label']) ?>
                            <?php if ($item['is_secret']): ?>
                                <span class="tm-card__secret" title="Hemmeligt">🤫</span>
                            <?php endif; ?>
                        </div>
                        <div class="tm-card__meta">
                            <span class="tm-card__name"><?= escape($item['guest_name']) ?></span>
                            <span class="tm-card__duration"><?= $item['duration_minutes'] ?> min</span>
                        </div>
                        <?php if ($item['description']): ?>
                            <div class="tm-card__desc"><?= nl2br(escape($item['description'])) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="tm-card__action">
                        <?php if ($item['status'] === 'completed'): ?>
                            <button onclick="setStatus(<?= $item['id'] ?>, 'approved')" class="tm-btn tm-btn--done" aria-label="Marker som ikke færdig">
                                ✅
                            </button>
                        <?php else: ?>
                            <button onclick="setStatus(<?= $item['id'] ?>, 'completed')" class="tm-btn" aria-label="Marker som færdig">
                                Færdig
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Messages View -->
    <div class="tm-messages-view <?= $activeTab !== 'messages' ? 'tm-messages-view--hidden' : '' ?>">
        <?php if (empty($conversations)): ?>
            <div class="tm-empty">
                <div class="tm-empty__icon">💬</div>
                <p>Ingen beskeder fra gæster endnu</p>
            </div>
        <?php else: ?>
            <?php
            // Check if viewing specific conversation
            $viewGuestId = isset($_GET['guest']) ? (int)$_GET['guest'] : null;

            if ($viewGuestId):
                // Get messages for this guest
                $stmt = $db->prepare("
                    SELECT * FROM toastmaster_messages
                    WHERE event_id = ? AND guest_id = ?
                    ORDER BY created_at ASC
                ");
                $stmt->execute([$eventId, $viewGuestId]);
                $guestMessages = $stmt->fetchAll();

                // Mark as read
                $stmt = $db->prepare("
                    UPDATE toastmaster_messages
                    SET is_read = 1
                    WHERE event_id = ? AND guest_id = ? AND is_from_toastmaster = 0
                ");
                $stmt->execute([$eventId, $viewGuestId]);

                $guestName = $guestMessages[0]['guest_name'] ?? 'Gæst';
            ?>
                <div class="tm-chat">
                    <div class="tm-chat__header">
                        <a href="?view=messages" class="tm-chat__back">← Tilbage</a>
                        <span class="tm-chat__title"><?= escape($guestName) ?></span>
                    </div>

                    <div class="tm-chat__messages" id="chat-messages">
                        <?php foreach ($guestMessages as $msg): ?>
                            <div class="tm-chat__msg <?= $msg['is_from_toastmaster'] ? 'tm-chat__msg--sent' : 'tm-chat__msg--received' ?>">
                                <div class="tm-chat__msg-text"><?= nl2br(escape($msg['message'])) ?></div>
                                <div class="tm-chat__msg-time"><?= date('j/n H:i', strtotime($msg['created_at'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <form class="tm-chat__form" onsubmit="sendMessage(event, <?= $viewGuestId ?>)">
                        <input type="text" id="message-input" class="tm-chat__input" placeholder="Skriv en besked..." required>
                        <button type="submit" class="tm-btn">Send</button>
                    </form>
                </div>
            <?php else: ?>
                <!-- Conversation List -->
                <div class="tm-conv-list">
                    <?php foreach ($conversations as $conv): ?>
                        <a href="?view=messages&guest=<?= $conv['guest_id'] ?>" class="tm-conv">
                            <div class="tm-conv__avatar">👤</div>
                            <div class="tm-conv__info">
                                <div class="tm-conv__name">
                                    <?= escape($conv['guest_name']) ?>
                                    <?php if ($conv['unread_count'] > 0): ?>
                                        <span class="tm-conv__badge"><?= $conv['unread_count'] ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="tm-conv__time">
                                    <?= $conv['total_messages'] ?> beskeder • <?= date('j/n H:i', strtotime($conv['last_message'])) ?>
                                </div>
                            </div>
                            <div class="tm-conv__arrow">→</div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Print Footer -->
    <div class="print-footer">
        <p>Toastmaster: <?= escape($toastmaster['name']) ?></p>
    </div>
</div>

<style>
/* =====================
   Mobile-First Base Styles
   ===================== */
* {
    box-sizing: border-box;
}

body {
    background: var(--color-bg);
    margin: 0;
    padding: 0;
    min-height: 100vh;
    -webkit-tap-highlight-color: transparent;
}

.tm-app {
    max-width: 100%;
    padding: 1rem;
    padding-bottom: 2rem;
}

/* Header */
.tm-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    margin-bottom: 1rem;
    border-bottom: 1px solid var(--color-border-soft);
}

.tm-header__brand {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
    flex: 1;
}

.tm-header__icon {
    font-size: 1.75rem;
    flex-shrink: 0;
}

.tm-header__info {
    display: flex;
    flex-direction: column;
    line-height: 1.3;
    min-width: 0;
}

.tm-header__info strong {
    font-size: 1rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.tm-header__info span {
    font-size: 0.75rem;
    color: var(--color-text-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.tm-header__actions {
    display: flex;
    gap: 0.5rem;
    flex-shrink: 0;
}

.tm-header__btn {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--color-surface);
    border: 1px solid var(--color-border-soft);
    border-radius: var(--radius-md);
    font-size: 1.25rem;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s;
}

.tm-header__btn:hover {
    background: var(--color-bg-subtle);
}

/* Stats */
.tm-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.tm-stat {
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    padding: 1rem;
    text-align: center;
    box-shadow: var(--shadow-sm);
}

.tm-stat__value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--color-primary-deep);
    line-height: 1.2;
}

.tm-stat__label {
    font-size: 0.75rem;
    color: var(--color-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* Progress */
.tm-progress {
    height: 8px;
    background: var(--color-border-soft);
    border-radius: 4px;
    margin-bottom: 1.5rem;
    overflow: hidden;
}

.tm-progress__bar {
    height: 100%;
    background: linear-gradient(90deg, var(--color-success) 0%, #4ade80 100%);
    transition: width 0.5s ease;
    border-radius: 4px;
}

/* Empty State */
.tm-empty {
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    padding: 3rem 1.5rem;
    text-align: center;
}

.tm-empty__icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.tm-empty p {
    color: var(--color-text-muted);
    margin: 0;
}

/* Items List */
.tm-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.tm-list--hidden {
    display: none;
}

.tm-messages-view--hidden {
    display: none;
}

/* Card */
.tm-card {
    display: grid;
    grid-template-columns: auto auto auto 1fr auto;
    gap: 0.5rem;
    align-items: start;
    padding: 1rem;
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    border-left: 4px solid var(--color-primary);
    transition: all 0.2s;
}

.tm-card--pending {
    border-left-color: var(--color-warning);
}

.tm-card--completed {
    border-left-color: var(--color-success);
    opacity: 0.65;
}

.tm-card--completed .tm-card__title {
    text-decoration: line-through;
    color: var(--color-text-muted);
}

.tm-card__number {
    width: 24px;
    height: 24px;
    background: var(--color-primary-pale);
    color: var(--color-primary-deep);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 600;
    flex-shrink: 0;
}

.tm-card--completed .tm-card__number {
    background: var(--color-success);
    color: white;
}

.tm-card__drag {
    color: var(--color-text-muted);
    cursor: grab;
    padding: 0.25rem;
    touch-action: none;
    user-select: none;
    font-size: 1rem;
}

.tm-card__drag:active {
    cursor: grabbing;
}

.tm-card__icon {
    font-size: 1.5rem;
    flex-shrink: 0;
}

.tm-card__content {
    min-width: 0;
}

.tm-card__title {
    font-weight: 600;
    font-size: 1rem;
    line-height: 1.3;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.tm-card__secret {
    font-size: 0.9rem;
}

.tm-card__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.25rem;
    font-size: 0.8rem;
    color: var(--color-text-muted);
}

.tm-card__name {
    font-weight: 500;
}

.tm-card__duration {
    background: var(--color-bg-subtle);
    padding: 0.1rem 0.4rem;
    border-radius: var(--radius-sm);
}

.tm-card__desc {
    margin-top: 0.5rem;
    padding: 0.5rem;
    background: var(--color-bg-subtle);
    border-radius: var(--radius-sm);
    font-size: 0.8rem;
    color: var(--color-text-soft);
    line-height: 1.4;
}

.tm-card__action {
    align-self: center;
}

/* Buttons */
.tm-btn {
    padding: 0.6rem 1rem;
    background: var(--color-primary);
    color: white;
    border: none;
    border-radius: var(--radius-md);
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
    -webkit-tap-highlight-color: transparent;
}

.tm-btn:hover {
    background: var(--color-primary-deep);
    transform: scale(1.02);
}

.tm-btn:active {
    transform: scale(0.98);
}

.tm-btn--done {
    background: var(--color-success);
    font-size: 1.25rem;
    padding: 0.4rem 0.8rem;
}

.tm-btn--done:hover {
    background: #3d8b40;
}

/* Dragging */
.tm-card.dragging {
    opacity: 0.5;
    transform: scale(1.02);
    box-shadow: var(--shadow-lg);
}

/* Print header/footer - hidden on screen */
.print-header,
.print-footer {
    display: none;
}

/* =====================
   Tablet+ Styles
   ===================== */
@media (min-width: 600px) {
    .tm-app {
        max-width: 600px;
        margin: 0 auto;
        padding: 1.5rem;
    }

    .tm-stat__value {
        font-size: 2rem;
    }

    .tm-card {
        padding: 1.25rem;
    }

    .tm-card__icon {
        font-size: 1.75rem;
    }
}

/* =====================
   Print Styles
   ===================== */
@media print {
    @page {
        margin: 1.5cm;
        size: A4;
    }

    body {
        background: white !important;
        font-size: 11pt;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .tm-app {
        max-width: 100%;
        padding: 0;
    }

    .tm-header,
    .tm-stats,
    .tm-progress,
    .tm-card__drag,
    .tm-card__action,
    .tm-header__actions {
        display: none !important;
    }

    .print-header {
        display: block !important;
        text-align: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #000;
    }

    .print-header h1 {
        font-family: 'Cormorant Garamond', Georgia, serif;
        font-size: 24pt;
        margin: 0 0 0.25rem 0;
    }

    .print-header p {
        margin: 0.25rem 0;
        color: #555;
    }

    .print-footer {
        display: block !important;
        text-align: center;
        margin-top: 2rem;
        padding-top: 1rem;
        border-top: 1px solid #ccc;
        font-size: 10pt;
        color: #666;
    }

    .tm-list {
        gap: 0.5rem;
    }

    .tm-card {
        grid-template-columns: auto auto 1fr;
        padding: 0.75rem;
        border-left-width: 3px;
        box-shadow: none;
        border: 1px solid #ddd;
        page-break-inside: avoid;
    }

    .tm-card--completed {
        opacity: 1;
        background: #f5f5f5;
    }

    .tm-card__number {
        width: 20px;
        height: 20px;
        font-size: 0.7rem;
    }

    .tm-card__icon {
        font-size: 1.25rem;
    }

    .tm-card__title {
        font-size: 11pt;
    }

    .tm-card__meta {
        font-size: 9pt;
    }

    .tm-card__desc {
        font-size: 9pt;
        background: #f9f9f9;
    }

    .tm-empty {
        display: none;
    }

    .tm-tabs,
    .tm-messages-view,
    .tm-messages-view--hidden {
        display: none !important;
    }

    /* Always show program list when printing */
    .tm-list--hidden {
        display: flex !important;
    }
}

/* =====================
   Tabs
   ===================== */
.tm-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    padding: 0.25rem;
    border: 1px solid var(--color-border-soft);
}

.tm-tab {
    flex: 1;
    padding: 0.75rem 1rem;
    text-align: center;
    text-decoration: none;
    color: var(--color-text-muted);
    font-size: 0.9rem;
    font-weight: 500;
    border-radius: var(--radius-md);
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.tm-tab:hover {
    color: var(--color-text);
}

.tm-tab--active {
    background: var(--color-primary);
    color: white;
}

.tm-tab__badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    background: #dc2626;
    color: white;
    font-size: 0.7rem;
    font-weight: 700;
    border-radius: 9px;
}

.tm-tab--active .tm-tab__badge {
    background: white;
    color: var(--color-primary-deep);
}

/* =====================
   Conversations List
   ===================== */
.tm-conv-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.tm-conv {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    text-decoration: none;
    color: inherit;
    border: 1px solid var(--color-border-soft);
    transition: all 0.2s;
}

.tm-conv:hover {
    border-color: var(--color-primary);
}

.tm-conv__avatar {
    font-size: 1.5rem;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--color-primary-pale);
    border-radius: 50%;
}

.tm-conv__info {
    flex: 1;
    min-width: 0;
}

.tm-conv__name {
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.tm-conv__badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    background: #dc2626;
    color: white;
    font-size: 0.7rem;
    font-weight: 700;
    border-radius: 9px;
}

.tm-conv__time {
    font-size: 0.8rem;
    color: var(--color-text-muted);
}

.tm-conv__arrow {
    color: var(--color-text-muted);
}

/* =====================
   Chat View
   ===================== */
.tm-chat {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 220px);
    min-height: 400px;
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    border: 1px solid var(--color-border-soft);
    overflow: hidden;
}

.tm-chat__header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border-bottom: 1px solid var(--color-border-soft);
}

.tm-chat__back {
    color: var(--color-primary);
    text-decoration: none;
    font-size: 0.9rem;
}

.tm-chat__title {
    font-weight: 600;
}

.tm-chat__messages {
    flex: 1;
    overflow-y: auto;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    background: var(--color-bg-subtle);
}

.tm-chat__msg {
    max-width: 80%;
    padding: 0.75rem 1rem;
    border-radius: 16px;
}

.tm-chat__msg--sent {
    margin-left: auto;
    background: var(--color-primary);
    color: white;
    border-bottom-right-radius: 4px;
}

.tm-chat__msg--received {
    margin-right: auto;
    background: var(--color-surface);
    border: 1px solid var(--color-border-soft);
    border-bottom-left-radius: 4px;
}

.tm-chat__msg-text {
    font-size: 0.9rem;
    line-height: 1.4;
}

.tm-chat__msg-time {
    font-size: 0.7rem;
    opacity: 0.7;
    margin-top: 0.25rem;
    text-align: right;
}

.tm-chat__form {
    display: flex;
    gap: 0.5rem;
    padding: 1rem;
    border-top: 1px solid var(--color-border-soft);
}

.tm-chat__input {
    flex: 1;
    padding: 0.75rem 1rem;
    border: 2px solid var(--color-border-soft);
    border-radius: var(--radius-md);
    font-size: 0.9rem;
    outline: none;
}

.tm-chat__input:focus {
    border-color: var(--color-primary);
}
</style>

<script>
function setStatus(itemId, status) {
    // Optimistic UI update
    const card = document.querySelector(`.tm-card[data-id="${itemId}"]`);
    if (card) {
        card.style.opacity = '0.5';
    }

    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=update_status&item_id=${itemId}&status=${status}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            if (card) card.style.opacity = '1';
            alert('Kunne ikke opdatere status');
        }
    })
    .catch(() => {
        if (card) card.style.opacity = '1';
        alert('Netværksfejl - prøv igen');
    });
}

// Drag and drop for reordering
const list = document.getElementById('items-list');
let draggedItem = null;

if (list) {
    const cards = list.querySelectorAll('.tm-card');

    cards.forEach(card => {
        const handle = card.querySelector('.tm-card__drag');
        if (!handle) return;

        // Mouse events
        handle.addEventListener('mousedown', () => card.draggable = true);

        // Touch events for mobile
        let touchStartY = 0;
        let touchCurrentY = 0;

        handle.addEventListener('touchstart', (e) => {
            touchStartY = e.touches[0].clientY;
            draggedItem = card;
            card.classList.add('dragging');
        }, {passive: true});

        handle.addEventListener('touchmove', (e) => {
            if (!draggedItem) return;
            e.preventDefault();
            touchCurrentY = e.touches[0].clientY;

            // Find card under touch point
            const elemBelow = document.elementFromPoint(
                e.touches[0].clientX,
                e.touches[0].clientY
            );
            const cardBelow = elemBelow?.closest('.tm-card');

            if (cardBelow && cardBelow !== draggedItem) {
                const rect = cardBelow.getBoundingClientRect();
                const midY = rect.top + rect.height / 2;
                if (touchCurrentY < midY) {
                    list.insertBefore(draggedItem, cardBelow);
                } else {
                    list.insertBefore(draggedItem, cardBelow.nextSibling);
                }
            }
        }, {passive: false});

        handle.addEventListener('touchend', () => {
            if (draggedItem) {
                draggedItem.classList.remove('dragging');
                saveOrder();
                draggedItem = null;
            }
        });

        // Desktop drag events
        card.addEventListener('dragstart', () => {
            draggedItem = card;
            card.classList.add('dragging');
        });

        card.addEventListener('dragend', () => {
            card.draggable = false;
            card.classList.remove('dragging');
            saveOrder();
            draggedItem = null;
        });

        card.addEventListener('dragover', (e) => {
            e.preventDefault();
            if (draggedItem && draggedItem !== card) {
                const rect = card.getBoundingClientRect();
                const midY = rect.top + rect.height / 2;
                if (e.clientY < midY) {
                    list.insertBefore(draggedItem, card);
                } else {
                    list.insertBefore(draggedItem, card.nextSibling);
                }
            }
        });
    });
}

function saveOrder() {
    const items = document.querySelectorAll('.tm-card');
    const order = Array.from(items).map(item => item.dataset.id);

    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=update_order&order=' + encodeURIComponent(JSON.stringify(order))
    });
}

function sendMessage(e, guestId) {
    e.preventDefault();
    const input = document.getElementById('message-input');
    const message = input.value.trim();
    if (!message) return;

    const btn = e.target.querySelector('button');
    btn.disabled = true;
    btn.textContent = '...';

    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=send_message&guest_id=${guestId}&message=${encodeURIComponent(message)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Kunne ikke sende besked');
            btn.disabled = false;
            btn.textContent = 'Send';
        }
    })
    .catch(() => {
        alert('Netværksfejl');
        btn.disabled = false;
        btn.textContent = 'Send';
    });
}

// Scroll chat to bottom on load
document.addEventListener('DOMContentLoaded', function() {
    const chatMessages = document.getElementById('chat-messages');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
});
</script>

</body>
</html>

<?php
/**
 * Guest - Indslag (Performance/Contribution Signup) & Chat with Toastmaster
 */
require_once __DIR__ . '/../includes/guest-header.php';

// Ensure tables exist
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS toastmaster_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            guest_name VARCHAR(255) NOT NULL,
            item_type ENUM('tale', 'sang', 'sketch', 'quiz', 'leg', 'musik', 'andet') DEFAULT 'tale',
            title VARCHAR(255) DEFAULT NULL,
            description TEXT,
            duration_minutes INT DEFAULT 5,
            is_secret TINYINT(1) DEFAULT 0,
            status ENUM('pending', 'approved', 'completed') DEFAULT 'pending',
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
        )
    ");

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
    error_log('Failed to create toastmaster tables (toastmaster_items/toastmaster_messages) in guest/indslag: ' . $e->getMessage());
}

// Check if there's a primary toastmaster (or any toastmaster)
$stmt = $db->prepare("SELECT * FROM toastmaster_access WHERE event_id = ? ORDER BY is_primary DESC, created_at ASC LIMIT 1");
$stmt->execute([$eventId]);
$primaryToastmaster = $stmt->fetch();
$hasToastmaster = !empty($primaryToastmaster);

$success = false;
$successType = '';
$error = '';
$activeTab = $_GET['tab'] ?? 'indslag';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? 'add_indslag';

    if ($action === 'add_indslag') {
        $guestName = trim($_POST['guest_name'] ?? '');
        $itemType = $_POST['item_type'] ?? 'tale';
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $duration = max(1, min(30, (int)($_POST['duration'] ?? 5)));
        $isSecret = isset($_POST['is_secret']) ? 1 : 0;

        if (empty($guestName)) {
            $error = 'Indtast venligst dit navn';
        } else {
            $stmt = $db->prepare("SELECT MAX(sort_order) FROM toastmaster_items WHERE event_id = ?");
            $stmt->execute([$eventId]);
            $maxOrder = $stmt->fetchColumn() ?? 0;

            $stmt = $db->prepare("
                INSERT INTO toastmaster_items (event_id, guest_name, item_type, title, description, duration_minutes, is_secret, status, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)
            ");
            $stmt->execute([$eventId, $guestName, $itemType, $title ?: null, $description ?: null, $duration, $isSecret, $maxOrder + 1]);
            $success = true;
            $successType = 'indslag';
        }
    }

    if ($action === 'send_message') {
        $messageText = trim($_POST['message'] ?? '');
        $senderName = trim($_POST['sender_name'] ?? $guest['name'] ?? 'Gæst');

        if (empty($messageText)) {
            $error = 'Skriv venligst en besked';
            $activeTab = 'chat';
        } else {
            $stmt = $db->prepare("
                INSERT INTO toastmaster_messages (event_id, guest_id, guest_name, message, is_from_toastmaster)
                VALUES (?, ?, ?, ?, 0)
            ");
            $stmt->execute([$eventId, $guestId, $senderName, $messageText]);
            $success = true;
            $successType = 'message';
            $activeTab = 'chat';
        }
    }
}

// Get existing items from this guest
$myItemsStmt = $db->prepare("
    SELECT * FROM toastmaster_items
    WHERE event_id = ? AND guest_name = ?
    ORDER BY created_at DESC
");
$myItemsStmt->execute([$eventId, $guest['name'] ?? '']);
$myItems = $myItemsStmt->fetchAll();

// Get public items (non-secret)
$publicItemsStmt = $db->prepare("
    SELECT * FROM toastmaster_items
    WHERE event_id = ? AND is_secret = 0
    ORDER BY sort_order, created_at
");
$publicItemsStmt->execute([$eventId]);
$publicItems = $publicItemsStmt->fetchAll();

// Get messages for this guest
$messagesStmt = $db->prepare("
    SELECT * FROM toastmaster_messages
    WHERE event_id = ? AND guest_id = ?
    ORDER BY created_at ASC
");
$messagesStmt->execute([$eventId, $guestId]);
$messages = $messagesStmt->fetchAll();

// Count unread messages from toastmaster
$unreadStmt = $db->prepare("
    SELECT COUNT(*) FROM toastmaster_messages
    WHERE event_id = ? AND guest_id = ? AND is_from_toastmaster = 1 AND is_read = 0
");
$unreadStmt->execute([$eventId, $guestId]);
$unreadCount = $unreadStmt->fetchColumn();

// Mark messages from toastmaster as read if viewing chat
if ($activeTab === 'chat') {
    $stmt = $db->prepare("
        UPDATE toastmaster_messages
        SET is_read = 1
        WHERE event_id = ? AND guest_id = ? AND is_from_toastmaster = 1
    ");
    $stmt->execute([$eventId, $guestId]);
}

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
?>

<?php if ($success && $successType === 'indslag'): ?>
    <div class="guest-hero">
        <div class="guest-hero__icon">🎉</div>
        <h1 class="guest-hero__title">Tak for din tilmelding!</h1>
        <p class="lead mt-sm">
            Dit indslag er registreret. Toastmasteren vil kontakte dig hvis der er spørgsmål.
        </p>
    </div>

    <div class="card text-center">
        <a href="<?= BASE_PATH ?>/guest/indslag.php" class="btn btn--secondary mb-sm btn--block">
            Tilmeld endnu et indslag
        </a>
        <a href="<?= BASE_PATH ?>/guest/indslag.php?tab=chat" class="btn btn--ghost btn--block">
            Skriv til toastmaster
        </a>
    </div>

<?php else: ?>

    <div class="guest-hero" style="padding: var(--space-lg);">
        <div class="guest-hero__icon">🎤</div>
        <h1 class="guest-hero__title">Indslag & Beskeder</h1>
        <p class="text-soft small">
            Tilmeld tale, sang eller andet - og skriv til toastmaster
        </p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert--error mb-md"><?= escape($error) ?></div>
    <?php endif; ?>

    <?php if ($success && $successType === 'message'): ?>
        <div class="alert alert--success mb-md">
            Din besked er sendt til toastmaster!
        </div>
    <?php endif; ?>

    <!-- Tab Navigation -->
    <div class="tabs mb-md">
        <a href="?tab=indslag" class="tab <?= $activeTab === 'indslag' ? 'tab--active' : '' ?>">
            Tilmeld indslag
        </a>
        <a href="?tab=chat" class="tab <?= $activeTab === 'chat' ? 'tab--active' : '' ?>">
            <?php if ($hasToastmaster): ?>
                Skriv til <?= escape($primaryToastmaster['name'] ?? 'toastmaster') ?>
            <?php else: ?>
                Chat
            <?php endif; ?>
            <?php if ($unreadCount > 0): ?>
                <span class="tab-badge"><?= $unreadCount ?></span>
            <?php endif; ?>
        </a>
    </div>

    <?php if ($activeTab === 'indslag'): ?>
    <!-- Indslag Tab -->
    <div class="card mb-md">
        <h3 class="card__title mb-sm">Tilmeld et indslag</h3>
        <p class="small text-muted mb-md">
            Vil du holde en tale, synge en sang eller lave noget sjovt?
        </p>

        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_indslag">

            <div class="form-group">
                <label class="form-label">Dit navn *</label>
                <input type="text"
                       name="guest_name"
                       class="form-input"
                       value="<?= escape($guest['name'] ?? '') ?>"
                       placeholder="Dit navn"
                       required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Type indslag</label>
                    <select name="item_type" class="form-input">
                        <?php foreach ($typeLabels as $value => $type): ?>
                            <option value="<?= $value ?>"><?= $type['icon'] ?> <?= $type['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Ca. varighed</label>
                    <select name="duration" class="form-input">
                        <option value="2">2 min</option>
                        <option value="5" selected>5 min</option>
                        <option value="10">10 min</option>
                        <option value="15">15 min</option>
                        <option value="20">20+ min</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Titel (valgfrit)</label>
                <input type="text"
                       name="title"
                       class="form-input"
                       placeholder="F.eks. 'Tale fra bedsteforældre'">
            </div>

            <div class="form-group">
                <label class="form-label">Beskrivelse (valgfrit)</label>
                <textarea name="description"
                          class="form-input"
                          rows="3"
                          placeholder="Kort beskrivelse..."></textarea>
                <p class="small text-muted mt-xs">Kun synlig for toastmaster</p>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_secret" value="1">
                    <span>Hemmeligt for <?= escape($event['confirmand_name']) ?></span>
                </label>
            </div>

            <button type="submit" class="btn btn--primary btn--block btn--large">
                Tilmeld indslag
            </button>
        </form>
    </div>

    <?php if (!empty($publicItems)): ?>
    <div class="card">
        <h3 class="card__title mb-sm">Tilmeldte indslag</h3>
        <div class="item-list">
            <?php foreach ($publicItems as $item): ?>
                <div class="item-row">
                    <span class="item-row__icon"><?= $typeLabels[$item['item_type']]['icon'] ?? '✨' ?></span>
                    <div class="item-row__content">
                        <strong><?= escape($item['title'] ?: $typeLabels[$item['item_type']]['label']) ?></strong>
                        <span class="small text-muted">af <?= escape($item['guest_name']) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <!-- Chat Tab -->
    <div class="card">
        <h3 class="card__title mb-sm">Skriv til toastmaster</h3>

        <?php if (!$hasToastmaster): ?>
            <!-- No toastmaster assigned yet -->
            <div class="chat-empty" style="padding: 2rem 1rem;">
                <div style="font-size: 2.5rem; margin-bottom: 1rem;">🎤</div>
                <p class="text-muted">Der er ikke udpeget en toastmaster endnu.</p>
                <p class="small text-muted mt-sm">Når arrangøren har oprettet en toastmaster, kan du skrive beskeder her.</p>
            </div>
        <?php else: ?>
            <p class="small text-muted mb-md">
                Har du spørgsmål om dit indslag eller vil du koordinere noget?
                <?php if (!empty($primaryToastmaster['name']) && $primaryToastmaster['name'] !== 'Toastmaster'): ?>
                    <br>Toastmaster: <strong><?= escape($primaryToastmaster['name']) ?></strong>
                <?php endif; ?>
            </p>

            <!-- Messages -->
            <div class="chat-messages" id="chat-messages">
                <?php if (empty($messages)): ?>
                    <div class="chat-empty">
                        <p class="text-muted small">Ingen beskeder endnu. Start samtalen!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                        <div class="chat-message <?= $msg['is_from_toastmaster'] ? 'chat-message--received' : 'chat-message--sent' ?>">
                            <div class="chat-message__sender">
                                <?= $msg['is_from_toastmaster'] ? escape($primaryToastmaster['name'] ?? 'Toastmaster') : escape($msg['guest_name']) ?>
                            </div>
                            <div class="chat-message__text"><?= nl2br(escape($msg['message'])) ?></div>
                            <div class="chat-message__time">
                                <?= date('j/n H:i', strtotime($msg['created_at'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Send Message Form -->
            <form method="POST" class="chat-form">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="send_message">
                <input type="hidden" name="sender_name" value="<?= escape($guest['name'] ?? 'Gæst') ?>">

                <div class="chat-input-wrapper">
                    <textarea name="message"
                              class="form-input chat-input"
                              rows="2"
                              placeholder="Skriv en besked til <?= escape($primaryToastmaster['name'] ?? 'toastmaster') ?>..."
                              required></textarea>
                    <button type="submit" class="btn btn--primary chat-send">Send</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>

<?php endif; ?>

<style>
/* Tabs */
.tabs {
    display: flex;
    gap: 0.5rem;
    background: var(--white);
    border-radius: 12px;
    padding: 0.25rem;
    border: 1px solid var(--blush-light);
}

.tab {
    flex: 1;
    padding: 0.75rem 1rem;
    background: none;
    border: none;
    border-radius: 10px;
    font-family: 'Manrope', sans-serif;
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--ink-soft);
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.tab:hover {
    color: var(--ink);
}

.tab--active {
    background: var(--blush);
    color: var(--white);
}

.tab-badge {
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

.tab--active .tab-badge {
    background: white;
    color: var(--blush-dark);
}

/* Checkbox */
.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: var(--blush);
}

/* Items */
.item-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.item-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: var(--blush-pale);
    border-radius: 10px;
}

.item-row__icon {
    font-size: 1.5rem;
}

.item-row__content {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

/* Chat */
.chat-messages {
    max-height: 350px;
    overflow-y: auto;
    margin-bottom: 1rem;
    padding: 0.75rem;
    background: var(--blush-pale);
    border-radius: 12px;
}

.chat-empty {
    text-align: center;
    padding: 2rem 1rem;
}

.chat-message {
    max-width: 85%;
    margin-bottom: 0.75rem;
    padding: 0.75rem 1rem;
    border-radius: 16px;
}

.chat-message--sent {
    margin-left: auto;
    background: var(--blush);
    color: var(--white);
    border-bottom-right-radius: 4px;
}

.chat-message--received {
    margin-right: auto;
    background: var(--white);
    border: 1px solid var(--blush-light);
    border-bottom-left-radius: 4px;
}

.chat-message__sender {
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    opacity: 0.7;
    margin-bottom: 0.25rem;
}

.chat-message__text {
    font-size: 0.9rem;
    line-height: 1.4;
}

.chat-message__time {
    font-size: 0.7rem;
    opacity: 0.6;
    margin-top: 0.25rem;
    text-align: right;
}

.chat-form {
    margin-top: 0.5rem;
}

.chat-input-wrapper {
    display: flex;
    gap: 0.5rem;
    align-items: flex-end;
}

.chat-input {
    flex: 1;
    min-height: unset;
    resize: none;
}

.chat-send {
    flex-shrink: 0;
    padding: 0.9rem 1.25rem;
}
</style>

<script>
// Scroll chat to bottom on load
document.addEventListener('DOMContentLoaded', function() {
    const chatMessages = document.getElementById('chat-messages');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
});
</script>

<?php require_once __DIR__ . '/../includes/guest-footer.php'; ?>
</body>
</html>

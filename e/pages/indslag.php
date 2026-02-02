<?php
/**
 * Guest Indslag (Performance/Contribution) Page - Nordic Design
 */

// Ensure toastmaster tables exist
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
} catch (Exception $e) {}

// Check if there's a toastmaster
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
        $senderName = trim($_POST['sender_name'] ?? $currentGuest['name'] ?? 'Gæst');

        if (empty($messageText)) {
            $error = 'Skriv venligst en besked';
            $activeTab = 'chat';
        } else {
            $stmt = $db->prepare("
                INSERT INTO toastmaster_messages (event_id, guest_id, guest_name, message, is_from_toastmaster)
                VALUES (?, ?, ?, ?, 0)
            ");
            $stmt->execute([$eventId, $currentGuest['id'], $senderName, $messageText]);
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
$myItemsStmt->execute([$eventId, $currentGuest['name'] ?? '']);
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
$messagesStmt->execute([$eventId, $currentGuest['id']]);
$messages = $messagesStmt->fetchAll();

// Count unread messages from toastmaster
$unreadStmt = $db->prepare("
    SELECT COUNT(*) FROM toastmaster_messages
    WHERE event_id = ? AND guest_id = ? AND is_from_toastmaster = 1 AND is_read = 0
");
$unreadStmt->execute([$eventId, $currentGuest['id']]);
$unreadCount = $unreadStmt->fetchColumn();

// Mark messages from toastmaster as read if viewing chat
if ($activeTab === 'chat') {
    $stmt = $db->prepare("
        UPDATE toastmaster_messages
        SET is_read = 1
        WHERE event_id = ? AND guest_id = ? AND is_from_toastmaster = 1
    ");
    $stmt->execute([$eventId, $currentGuest['id']]);
}

// Item type labels with icons
$typeLabels = [
    'tale' => ['label' => 'Tale', 'icon' => 'microphone'],
    'sang' => ['label' => 'Sang', 'icon' => 'music'],
    'sketch' => ['label' => 'Sketch', 'icon' => 'theater'],
    'quiz' => ['label' => 'Quiz', 'icon' => 'question'],
    'leg' => ['label' => 'Leg', 'icon' => 'game'],
    'musik' => ['label' => 'Musik', 'icon' => 'music'],
    'andet' => ['label' => 'Andet', 'icon' => 'star']
];

// Get main person name for display
$mainPersonName = $event['main_person_name'] ?? 'konfirmanden';
?>

<?php if ($success && $successType === 'indslag'): ?>
<!-- Success State -->
<div class="card" style="text-align: center; padding: 48px 28px;">
    <div style="width: 80px; height: 80px; background: var(--sage-light); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px;">
        <svg width="40" height="40" fill="none" stroke="var(--sage-dark)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
    </div>
    <h2 class="serif" style="font-size: 26px; margin-bottom: 12px; color: var(--charcoal);">Tak for din tilmelding!</h2>
    <p style="color: var(--charcoal-light); margin-bottom: 28px; line-height: 1.6;">
        Dit indslag er registreret. Toastmasteren vil kontakte dig hvis der er spørgsmål.
    </p>
    <a href="/e/<?= $slug ?>/indslag" class="btn btn-secondary" style="margin-bottom: 12px;">Tilmeld endnu et indslag</a>
    <br>
    <a href="/e/<?= $slug ?>/indslag?tab=chat" style="color: var(--sage-dark); font-size: 14px; font-weight: 500;">Skriv til toastmaster</a>
</div>

<?php else: ?>

<!-- Page Header -->
<div class="page-header" style="text-align: center;">
    <h1 class="page-header-title">Indslag & Beskeder</h1>
    <p class="page-header-subtitle">Tilmeld tale, sang eller andet - og skriv til toastmaster</p>
</div>

<?php if ($error): ?>
<div class="flash flash-error">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
    <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<?php if ($success && $successType === 'message'): ?>
<div class="flash flash-success">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
    Din besked er sendt til toastmaster!
</div>
<?php endif; ?>

<!-- Tab Navigation -->
<div class="tab-nav">
    <a href="?tab=indslag" class="tab-item <?= $activeTab === 'indslag' ? 'active' : '' ?>">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path></svg>
        Tilmeld indslag
    </a>
    <a href="?tab=chat" class="tab-item <?= $activeTab === 'chat' ? 'active' : '' ?>">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
        <?php if ($hasToastmaster): ?>
            <?= htmlspecialchars($primaryToastmaster['name'] ?? 'Toastmaster') ?>
        <?php else: ?>
            Chat
        <?php endif; ?>
        <?php if ($unreadCount > 0): ?>
            <span class="badge"><?= $unreadCount ?></span>
        <?php endif; ?>
    </a>
</div>

<?php if ($activeTab === 'indslag'): ?>
<!-- Indslag Tab -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Tilmeld et indslag</h3>
        <div class="card-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path></svg>
        </div>
    </div>
    <p style="font-size: 14px; color: var(--charcoal-light); margin-bottom: 24px;">
        Vil du holde en tale, synge en sang eller lave noget sjovt?
    </p>

    <form method="POST">
        <input type="hidden" name="action" value="add_indslag">

        <div class="form-group">
            <label class="form-label">Dit navn *</label>
            <input type="text"
                   name="guest_name"
                   class="form-input"
                   value="<?= htmlspecialchars($currentGuest['name'] ?? '') ?>"
                   placeholder="Dit navn"
                   required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div class="form-group">
                <label class="form-label">Type indslag</label>
                <select name="item_type" class="form-input">
                    <option value="tale">Tale</option>
                    <option value="sang">Sang</option>
                    <option value="sketch">Sketch</option>
                    <option value="quiz">Quiz</option>
                    <option value="leg">Leg</option>
                    <option value="musik">Musik</option>
                    <option value="andet">Andet</option>
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
            <p class="form-hint">Kun synlig for toastmaster</p>
        </div>

        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="is_secret" value="1">
                <span>Hemmeligt for <?= htmlspecialchars($mainPersonName) ?></span>
            </label>
        </div>

        <button type="submit" class="btn btn-sage btn-full">Tilmeld indslag</button>
    </form>
</div>

<?php if (!empty($publicItems)): ?>
<div class="card">
    <h3 class="card-title">Tilmeldte indslag</h3>
    <div style="display: flex; flex-direction: column; gap: 12px;">
        <?php foreach ($publicItems as $item): ?>
            <div class="indslag-item">
                <div class="indslag-icon">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path></svg>
                </div>
                <div>
                    <strong><?= htmlspecialchars($item['title'] ?: $typeLabels[$item['item_type']]['label']) ?></strong>
                    <span style="font-size: 13px; color: var(--charcoal-light); display: block;">af <?= htmlspecialchars($item['guest_name']) ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php else: ?>
<!-- Chat Tab -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Skriv til toastmaster</h3>
        <div class="card-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
        </div>
    </div>

    <?php if (!$hasToastmaster): ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path></svg>
            </div>
            <h3>Ingen toastmaster endnu</h3>
            <p>Når arrangøren har oprettet en toastmaster, kan du skrive beskeder her.</p>
        </div>
    <?php else: ?>
        <p style="font-size: 14px; color: var(--charcoal-light); margin-bottom: 20px;">
            Har du spørgsmål om dit indslag eller vil du koordinere noget?
            <?php if (!empty($primaryToastmaster['name']) && $primaryToastmaster['name'] !== 'Toastmaster'): ?>
                <br>Toastmaster: <strong><?= htmlspecialchars($primaryToastmaster['name']) ?></strong>
            <?php endif; ?>
        </p>

        <!-- Messages -->
        <div class="chat-messages">
            <?php if (empty($messages)): ?>
                <div style="text-align: center; padding: 32px 16px;">
                    <p style="color: var(--charcoal-light); font-size: 14px;">Ingen beskeder endnu. Start samtalen!</p>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="chat-bubble <?= $msg['is_from_toastmaster'] ? 'incoming' : 'outgoing' ?>">
                        <div class="chat-sender">
                            <?= $msg['is_from_toastmaster'] ? htmlspecialchars($primaryToastmaster['name'] ?? 'Toastmaster') : 'Dig' ?>
                        </div>
                        <div class="chat-text"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                        <div class="chat-time">
                            <?= date('j/n H:i', strtotime($msg['created_at'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Send Message Form -->
        <form method="POST" class="chat-form">
            <input type="hidden" name="action" value="send_message">
            <input type="hidden" name="sender_name" value="<?= htmlspecialchars($currentGuest['name'] ?? 'Gæst') ?>">

            <div style="display: flex; gap: 10px; align-items: flex-end;">
                <textarea name="message"
                          class="form-input"
                          rows="2"
                          style="flex: 1; resize: none;"
                          placeholder="Skriv en besked..."
                          required></textarea>
                <button type="submit" class="btn btn-sage" style="padding: 14px 20px;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php endif; ?>

<style>
    .tab-nav {
        display: flex;
        gap: 8px;
        background: var(--white);
        border-radius: 16px;
        padding: 6px;
        margin-bottom: 24px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    }

    .tab-item {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 14px 16px;
        background: transparent;
        color: var(--charcoal-light);
        border-radius: 12px;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.25s var(--ease-out);
    }

    .tab-item:hover {
        background: var(--cream);
    }

    .tab-item.active {
        background: var(--sage);
        color: var(--white);
    }

    .tab-item .badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 20px;
        padding: 0 6px;
        background: var(--error);
        color: white;
        font-size: 11px;
        font-weight: 700;
        border-radius: 10px;
    }

    .checkbox-label {
        display: flex;
        align-items: center;
        gap: 12px;
        cursor: pointer;
    }

    .checkbox-label input {
        width: 20px;
        height: 20px;
        accent-color: var(--sage);
    }

    .checkbox-label span {
        font-size: 15px;
        color: var(--charcoal);
    }

    .indslag-item {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 14px 16px;
        background: var(--cream);
        border-radius: 14px;
    }

    .indslag-icon {
        width: 44px;
        height: 44px;
        background: var(--white);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--sage-dark);
        flex-shrink: 0;
    }

    .chat-messages {
        max-height: 380px;
        overflow-y: auto;
        margin-bottom: 20px;
        padding: 16px;
        background: var(--cream);
        border-radius: 16px;
    }

    .chat-bubble {
        max-width: 85%;
        margin-bottom: 16px;
        padding: 14px 18px;
        border-radius: 18px;
    }

    .chat-bubble.incoming {
        margin-right: auto;
        background: var(--white);
        border: 1px solid var(--cream-dark);
        border-bottom-left-radius: 6px;
    }

    .chat-bubble.outgoing {
        margin-left: auto;
        background: var(--sage);
        color: var(--white);
        border-bottom-right-radius: 6px;
    }

    .chat-sender {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        opacity: 0.7;
        margin-bottom: 6px;
    }

    .chat-text {
        font-size: 14px;
        line-height: 1.5;
    }

    .chat-time {
        font-size: 11px;
        opacity: 0.6;
        margin-top: 8px;
        text-align: right;
    }
</style>

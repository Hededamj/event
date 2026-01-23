<?php
/**
 * Guest Indslag (Performance/Contribution) Page
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

// Get main person name for display
$mainPersonName = $event['main_person_name'] ?? 'konfirmanden';
?>

<?php if ($success && $successType === 'indslag'): ?>
<div class="card" style="text-align: center; padding: 40px 24px;">
    <div style="font-size: 48px; margin-bottom: 16px;">🎉</div>
    <h2 class="serif" style="font-size: 24px; margin-bottom: 12px;">Tak for din tilmelding!</h2>
    <p style="color: var(--gray-600); margin-bottom: 24px;">
        Dit indslag er registreret. Toastmasteren vil kontakte dig hvis der er spørgsmål.
    </p>
    <a href="/e/<?= $slug ?>/indslag" class="btn btn-secondary" style="margin-bottom: 12px;">Tilmeld endnu et indslag</a>
    <br>
    <a href="/e/<?= $slug ?>/indslag?tab=chat" style="color: var(--primary); font-size: 14px;">Skriv til toastmaster</a>
</div>

<?php else: ?>

<h1 class="serif" style="font-size: 24px; text-align: center; margin-bottom: 8px;">Indslag & Beskeder</h1>
<p style="text-align: center; color: var(--gray-600); margin-bottom: 24px;">
    Tilmeld tale, sang eller andet - og skriv til toastmaster
</p>

<?php if ($error): ?>
<div class="flash flash-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($success && $successType === 'message'): ?>
<div class="flash flash-success">Din besked er sendt til toastmaster!</div>
<?php endif; ?>

<!-- Tab Navigation -->
<div style="display: flex; gap: 8px; background: var(--white); border-radius: 12px; padding: 4px; margin-bottom: 20px;">
    <a href="?tab=indslag" style="flex: 1; padding: 12px 16px; background: <?= $activeTab === 'indslag' ? 'var(--primary)' : 'transparent' ?>; color: <?= $activeTab === 'indslag' ? 'var(--white)' : 'var(--gray-600)' ?>; border-radius: 10px; text-align: center; text-decoration: none; font-size: 14px; font-weight: 500; transition: all 0.2s;">
        Tilmeld indslag
    </a>
    <a href="?tab=chat" style="flex: 1; padding: 12px 16px; background: <?= $activeTab === 'chat' ? 'var(--primary)' : 'transparent' ?>; color: <?= $activeTab === 'chat' ? 'var(--white)' : 'var(--gray-600)' ?>; border-radius: 10px; text-align: center; text-decoration: none; font-size: 14px; font-weight: 500; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 6px;">
        <?php if ($hasToastmaster): ?>
            Skriv til <?= htmlspecialchars($primaryToastmaster['name'] ?? 'toastmaster') ?>
        <?php else: ?>
            Chat
        <?php endif; ?>
        <?php if ($unreadCount > 0): ?>
            <span style="display: inline-flex; align-items: center; justify-content: center; min-width: 18px; height: 18px; padding: 0 5px; background: #dc2626; color: white; font-size: 11px; font-weight: 700; border-radius: 9px;"><?= $unreadCount ?></span>
        <?php endif; ?>
    </a>
</div>

<?php if ($activeTab === 'indslag'): ?>
<!-- Indslag Tab -->
<div class="card">
    <h3 class="card-title">Tilmeld et indslag</h3>
    <p style="font-size: 14px; color: var(--gray-600); margin-bottom: 20px;">
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

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
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
            <p style="font-size: 12px; color: var(--gray-400); margin-top: 4px;">Kun synlig for toastmaster</p>
        </div>

        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="checkbox" name="is_secret" value="1" style="width: 18px; height: 18px; accent-color: var(--primary);">
                <span>Hemmeligt for <?= htmlspecialchars($mainPersonName) ?></span>
            </label>
        </div>

        <button type="submit" class="btn btn-primary btn-full">Tilmeld indslag</button>
    </form>
</div>

<?php if (!empty($publicItems)): ?>
<div class="card">
    <h3 class="card-title">Tilmeldte indslag</h3>
    <div style="display: flex; flex-direction: column; gap: 12px;">
        <?php foreach ($publicItems as $item): ?>
            <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--gray-100); border-radius: 12px;">
                <span style="font-size: 24px;"><?= $typeLabels[$item['item_type']]['icon'] ?? '✨' ?></span>
                <div>
                    <strong style="display: block;"><?= htmlspecialchars($item['title'] ?: $typeLabels[$item['item_type']]['label']) ?></strong>
                    <span style="font-size: 13px; color: var(--gray-600);">af <?= htmlspecialchars($item['guest_name']) ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php else: ?>
<!-- Chat Tab -->
<div class="card">
    <h3 class="card-title">Skriv til toastmaster</h3>

    <?php if (!$hasToastmaster): ?>
        <div style="text-align: center; padding: 32px 16px;">
            <div style="font-size: 40px; margin-bottom: 16px;">🎤</div>
            <p style="color: var(--gray-600);">Der er ikke udpeget en toastmaster endnu.</p>
            <p style="font-size: 13px; color: var(--gray-400); margin-top: 8px;">Når arrangøren har oprettet en toastmaster, kan du skrive beskeder her.</p>
        </div>
    <?php else: ?>
        <p style="font-size: 14px; color: var(--gray-600); margin-bottom: 16px;">
            Har du spørgsmål om dit indslag eller vil du koordinere noget?
            <?php if (!empty($primaryToastmaster['name']) && $primaryToastmaster['name'] !== 'Toastmaster'): ?>
                <br>Toastmaster: <strong><?= htmlspecialchars($primaryToastmaster['name']) ?></strong>
            <?php endif; ?>
        </p>

        <!-- Messages -->
        <div style="max-height: 350px; overflow-y: auto; margin-bottom: 16px; padding: 12px; background: var(--gray-100); border-radius: 12px;">
            <?php if (empty($messages)): ?>
                <div style="text-align: center; padding: 24px 16px;">
                    <p style="color: var(--gray-400); font-size: 14px;">Ingen beskeder endnu. Start samtalen!</p>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <div style="max-width: 85%; margin-bottom: 12px; padding: 12px 16px; border-radius: 16px; <?= $msg['is_from_toastmaster'] ? 'margin-right: auto; background: var(--white); border: 1px solid var(--gray-200); border-bottom-left-radius: 4px;' : 'margin-left: auto; background: var(--primary); color: var(--white); border-bottom-right-radius: 4px;' ?>">
                        <div style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; opacity: 0.7; margin-bottom: 4px;">
                            <?= $msg['is_from_toastmaster'] ? htmlspecialchars($primaryToastmaster['name'] ?? 'Toastmaster') : htmlspecialchars($msg['guest_name']) ?>
                        </div>
                        <div style="font-size: 14px; line-height: 1.4;"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                        <div style="font-size: 11px; opacity: 0.6; margin-top: 4px; text-align: right;">
                            <?= date('j/n H:i', strtotime($msg['created_at'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Send Message Form -->
        <form method="POST">
            <input type="hidden" name="action" value="send_message">
            <input type="hidden" name="sender_name" value="<?= htmlspecialchars($currentGuest['name'] ?? 'Gæst') ?>">

            <div style="display: flex; gap: 8px; align-items: flex-end;">
                <textarea name="message"
                          class="form-input"
                          rows="2"
                          style="flex: 1; resize: none;"
                          placeholder="Skriv en besked til <?= htmlspecialchars($primaryToastmaster['name'] ?? 'toastmaster') ?>..."
                          required></textarea>
                <button type="submit" class="btn btn-primary" style="padding: 14px 20px;">Send</button>
            </div>
        </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php endif; ?>

<?php
/**
 * Admin - Event Settings
 */

// Handle form submission BEFORE including header (which outputs HTML)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Require login and get event ID early
requireLogin();
$db = getDB();
$eventId = getCurrentEventId();

// Check if visibility columns exist
$visibilityColumnsExist = false;
try {
    $checkStmt = $db->query("SHOW COLUMNS FROM events LIKE 'show_wishlist'");
    $visibilityColumnsExist = $checkStmt->rowCount() > 0;
} catch (Exception $e) {
    $visibilityColumnsExist = false;
}

// If columns don't exist, try to add them
if (!$visibilityColumnsExist) {
    try {
        $db->exec("
            ALTER TABLE events
            ADD COLUMN show_wishlist BOOLEAN DEFAULT TRUE,
            ADD COLUMN show_menu BOOLEAN DEFAULT TRUE,
            ADD COLUMN show_schedule BOOLEAN DEFAULT TRUE,
            ADD COLUMN show_photos BOOLEAN DEFAULT TRUE
        ");
        $visibilityColumnsExist = true;
    } catch (Exception $e) {
        // Columns might already exist or user doesn't have ALTER permission
    }
}

// Check if oenskesky_url column exists
$oenskeskyColumnExists = false;
try {
    $checkStmt = $db->query("SHOW COLUMNS FROM events LIKE 'oenskesky_url'");
    $oenskeskyColumnExists = $checkStmt->rowCount() > 0;
} catch (Exception $e) {
    $oenskeskyColumnExists = false;
}

// If column doesn't exist, try to add it
if (!$oenskeskyColumnExists) {
    try {
        $db->exec("ALTER TABLE events ADD COLUMN oenskesky_url VARCHAR(500) NULL");
        $oenskeskyColumnExists = true;
    } catch (Exception $e) {
        // Column might already exist or user doesn't have ALTER permission
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_event') {
        $confirmandName = trim($_POST['confirmand_name'] ?? '');
        $eventName = trim($_POST['event_name'] ?? '');
        $eventDate = $_POST['event_date'] ?? '';
        $eventTime = $_POST['event_time'] ?? '';
        $location = trim($_POST['location'] ?? '');
        $welcomeText = trim($_POST['welcome_text'] ?? '');
        $theme = $_POST['theme'] ?? 'girl';
        $oenskeskyUrl = trim($_POST['oenskesky_url'] ?? '');

        if ($confirmandName && $eventName && $eventDate) {
            if ($visibilityColumnsExist) {
                // Visibility settings
                $showWishlist = isset($_POST['show_wishlist']) ? 1 : 0;
                $showMenu = isset($_POST['show_menu']) ? 1 : 0;
                $showSchedule = isset($_POST['show_schedule']) ? 1 : 0;
                $showPhotos = isset($_POST['show_photos']) ? 1 : 0;

                $sql = "
                    UPDATE events
                    SET confirmand_name = ?,
                        name = ?,
                        event_date = ?,
                        event_time = ?,
                        location = ?,
                        welcome_text = ?,
                        theme = ?,
                        show_wishlist = ?,
                        show_menu = ?,
                        show_schedule = ?,
                        show_photos = ?";

                $params = [
                    $confirmandName,
                    $eventName,
                    $eventDate,
                    $eventTime ?: null,
                    $location ?: null,
                    $welcomeText ?: null,
                    $theme,
                    $showWishlist,
                    $showMenu,
                    $showSchedule,
                    $showPhotos
                ];

                // Add oenskesky_url if column exists
                if ($oenskeskyColumnExists) {
                    $sql .= ", oenskesky_url = ?";
                    $params[] = $oenskeskyUrl ?: null;
                }

                $sql .= " WHERE id = ?";
                $params[] = $eventId;

                $stmt = $db->prepare($sql);
                $stmt->execute($params);
            } else {
                // Without visibility columns
                $sql = "
                    UPDATE events
                    SET confirmand_name = ?,
                        name = ?,
                        event_date = ?,
                        event_time = ?,
                        location = ?,
                        welcome_text = ?,
                        theme = ?";

                $params = [
                    $confirmandName,
                    $eventName,
                    $eventDate,
                    $eventTime ?: null,
                    $location ?: null,
                    $welcomeText ?: null,
                    $theme
                ];

                // Add oenskesky_url if column exists
                if ($oenskeskyColumnExists) {
                    $sql .= ", oenskesky_url = ?";
                    $params[] = $oenskeskyUrl ?: null;
                }

                $sql .= " WHERE id = ?";
                $params[] = $eventId;

                $stmt = $db->prepare($sql);
                $stmt->execute($params);
            }

            setFlash('success', 'Indstillinger gemt!');
            redirect(BASE_PATH . '/admin/settings.php');
        }
    }
}

// Now include header (outputs HTML)
require_once __DIR__ . '/../includes/admin-header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-header__title">Indstillinger</h1>
        <p class="page-header__subtitle">
            Konfigurer event-detaljer og invitationstekst
        </p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_PATH ?>/" target="_blank" class="btn btn--secondary">Se forside</a>
    </div>
</div>

<!-- Settings Form -->
<div class="card">
    <form method="POST">
        <input type="hidden" name="action" value="update_event">

        <h2 class="card__title mb-md">Event-information</h2>

        <div class="form-group">
            <label class="form-label">Konfirmandens navn *</label>
            <input type="text"
                   name="confirmand_name"
                   class="form-input"
                   value="<?= escape($event['confirmand_name']) ?>"
                   required
                   placeholder="F.eks. Sofie">
            <p class="small text-muted mt-xs">Navnet der vises på forsiden og i invitationen</p>
        </div>

        <div class="form-group">
            <label class="form-label">Event-titel *</label>
            <input type="text"
                   name="event_name"
                   class="form-input"
                   value="<?= escape($event['name']) ?>"
                   required
                   placeholder="F.eks. Konfirmation">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-md);">
            <div class="form-group">
                <label class="form-label">Dato *</label>
                <input type="date"
                       name="event_date"
                       class="form-input"
                       value="<?= escape($event['event_date']) ?>"
                       required>
            </div>

            <div class="form-group">
                <label class="form-label">Tidspunkt</label>
                <input type="time"
                       name="event_time"
                       class="form-input"
                       value="<?= escape($event['event_time'] ? substr($event['event_time'], 0, 5) : '') ?>">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Adresse</label>
            <textarea name="location"
                      class="form-input"
                      rows="2"
                      placeholder="F.eks.&#10;Skovvej 12&#10;1234 Byname"><?= escape($event['location']) ?></textarea>
            <p class="small text-muted mt-xs">Adressen vises på invitationen. Brug linjeskift for flot formatering.</p>
        </div>

        <div class="form-group">
            <label class="form-label">Tema/Farver</label>
            <div style="display: flex; gap: var(--space-md);">
                <label style="display: flex; align-items: center; gap: var(--space-xs); cursor: pointer;">
                    <input type="radio"
                           name="theme"
                           value="girl"
                           <?= $event['theme'] === 'girl' ? 'checked' : '' ?>>
                    <span style="display: inline-block; width: 20px; height: 20px; background: linear-gradient(135deg, #D4A5A5, #C9A227); border-radius: 50%;"></span>
                    <span>Pige (rosa/guld)</span>
                </label>
                <label style="display: flex; align-items: center; gap: var(--space-xs); cursor: pointer;">
                    <input type="radio"
                           name="theme"
                           value="boy"
                           <?= $event['theme'] === 'boy' ? 'checked' : '' ?>>
                    <span style="display: inline-block; width: 20px; height: 20px; background: linear-gradient(135deg, #5A7094, #8E9AAB); border-radius: 50%;"></span>
                    <span>Dreng (blå/sølv)</span>
                </label>
            </div>
        </div>

        <hr style="margin: var(--space-lg) 0; border: none; border-top: 1px solid var(--color-border-soft);">

        <h2 class="card__title mb-md">Velkomsttekst</h2>

        <div class="form-group">
            <label class="form-label">Tekst på forsiden</label>
            <textarea name="welcome_text"
                      class="form-input"
                      rows="4"
                      placeholder="Skriv en personlig velkomsttekst som gæsterne ser når de besøger siden..."><?= escape($event['welcome_text']) ?></textarea>
            <p class="small text-muted mt-xs">
                Denne tekst vises på forsiden under dato og lokation.
                Du kan skrive en personlig hilsen til gæsterne.
            </p>
        </div>

        <?php if ($visibilityColumnsExist): ?>
        <hr style="margin: var(--space-lg) 0; border: none; border-top: 1px solid var(--color-border-soft);">

        <h2 class="card__title mb-md">Gæstevisning</h2>
        <p class="text-muted mb-md">Vælg hvilke sider gæsterne kan se på invitationen:</p>

        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: var(--space-md);">
            <label class="visibility-toggle">
                <input type="checkbox"
                       name="show_wishlist"
                       <?= ($event['show_wishlist'] ?? 1) ? 'checked' : '' ?>>
                <span class="visibility-toggle__icon">🎁</span>
                <span class="visibility-toggle__label">
                    <strong>Ønskeliste</strong>
                    <span>Gæster kan se og reservere ønsker</span>
                </span>
            </label>

            <label class="visibility-toggle">
                <input type="checkbox"
                       name="show_menu"
                       <?= ($event['show_menu'] ?? 1) ? 'checked' : '' ?>>
                <span class="visibility-toggle__icon">🍽️</span>
                <span class="visibility-toggle__label">
                    <strong>Menu</strong>
                    <span>Gæster kan se hvad der serveres</span>
                </span>
            </label>

            <label class="visibility-toggle">
                <input type="checkbox"
                       name="show_schedule"
                       <?= ($event['show_schedule'] ?? 1) ? 'checked' : '' ?>>
                <span class="visibility-toggle__icon">🕐</span>
                <span class="visibility-toggle__label">
                    <strong>Program</strong>
                    <span>Gæster kan se tidsplanen</span>
                </span>
            </label>

            <label class="visibility-toggle">
                <input type="checkbox"
                       name="show_photos"
                       <?= ($event['show_photos'] ?? 1) ? 'checked' : '' ?>>
                <span class="visibility-toggle__icon">📷</span>
                <span class="visibility-toggle__label">
                    <strong>Billeder</strong>
                    <span>Gæster kan uploade og se billeder</span>
                </span>
            </label>
        </div>

        <style>
            .visibility-toggle {
                display: flex;
                align-items: center;
                gap: var(--space-sm);
                padding: var(--space-md);
                background: var(--color-bg-subtle);
                border-radius: var(--radius-md);
                cursor: pointer;
                transition: all 0.2s;
                border: 2px solid transparent;
            }

            .visibility-toggle:hover {
                background: var(--color-primary-pale);
            }

            .visibility-toggle:has(input:checked) {
                background: var(--color-primary-pale);
                border-color: var(--color-primary-soft);
            }

            .visibility-toggle input {
                width: 20px;
                height: 20px;
                accent-color: var(--color-primary);
            }

            .visibility-toggle__icon {
                font-size: 1.5rem;
            }

            .visibility-toggle__label {
                display: flex;
                flex-direction: column;
            }

            .visibility-toggle__label strong {
                color: var(--color-text);
            }

            .visibility-toggle__label span:last-child {
                font-size: var(--text-xs);
                color: var(--color-text-muted);
            }
        </style>
        <?php endif; ?>

        <hr style="margin: var(--space-lg) 0; border: none; border-top: 1px solid var(--color-border-soft);">

        <h2 class="card__title mb-md">Ønskesky / Gaveidéer</h2>

        <div class="form-group">
            <label class="form-label">Link til Ønskeskyen</label>
            <input type="url"
                   name="oenskesky_url"
                   class="form-input"
                   value="<?= escape($event['oenskesky_url'] ?? '') ?>"
                   placeholder="https://www.oenskeskyen.dk/...">
            <p class="small text-muted mt-xs">
                Indsæt linket til jeres ønskeliste på Ønskeskyen, Gaveønsker.dk eller lignende.
                Gæster vil kunne åbne listen direkte fra deres invitation.
            </p>
        </div>

        <hr style="margin: var(--space-lg) 0; border: none; border-top: 1px solid var(--color-border-soft);">

        <h2 class="card__title mb-md">Forhåndsvisning</h2>

        <div style="background: var(--color-bg-subtle); border-radius: var(--radius-lg); padding: var(--space-lg); text-align: center; margin-bottom: var(--space-lg);">
            <p style="font-size: var(--text-xs); text-transform: uppercase; letter-spacing: 0.2em; color: var(--color-accent); margin-bottom: var(--space-xs);">Du er inviteret til</p>
            <h3 style="font-size: var(--text-2xl); color: var(--color-primary-deep); margin-bottom: var(--space-2xs);">
                <span id="preview-name"><?= escape($event['confirmand_name']) ?></span><em style="font-style: italic;">s</em>
            </h3>
            <p style="font-family: 'Cormorant Garamond', serif; font-size: var(--text-lg); color: var(--color-text-soft); margin-bottom: var(--space-sm);" id="preview-event">
                <?= escape($event['name']) ?>
            </p>
            <p style="color: var(--color-text-muted); font-size: var(--text-sm);" id="preview-location">
                <?= escape($event['location']) ?>
            </p>
        </div>

        <div class="flex gap-sm">
            <button type="submit" class="btn btn--primary">Gem indstillinger</button>
            <a href="<?= BASE_PATH ?>/" target="_blank" class="btn btn--secondary">Se forside</a>
        </div>
    </form>
</div>

<!-- Invitation Link -->
<div class="card mt-lg">
    <h2 class="card__title mb-sm">Invitationslink</h2>
    <p class="text-muted mb-md">Del dette link med dine gæster:</p>

    <div style="display: flex; gap: var(--space-sm); align-items: center;">
        <input type="text"
               class="form-input"
               value="https://hededam.dk/sofie/"
               readonly
               id="invite-link"
               style="flex: 1; font-family: monospace;">
        <button type="button"
                class="btn btn--primary"
                onclick="copyInviteLink()">
            Kopiér link
        </button>
    </div>

    <p class="small text-muted mt-sm">
        Gæster skal også bruge deres personlige kode for at tilmelde sig.
    </p>
</div>

</main>
</div>

<div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleSidebar()"></div>

<script src="<?= BASE_PATH ?>/assets/js/main.js"></script>
<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    sidebar.classList.toggle('sidebar--open');
    overlay.classList.toggle('sidebar-overlay--active');
}

// Live preview
document.querySelector('input[name="confirmand_name"]').addEventListener('input', function() {
    document.getElementById('preview-name').textContent = this.value || 'Navn';
});

document.querySelector('input[name="event_name"]').addEventListener('input', function() {
    document.getElementById('preview-event').textContent = this.value || 'Event';
});

document.querySelector('input[name="location"]').addEventListener('input', function() {
    document.getElementById('preview-location').textContent = this.value || '';
});

function copyInviteLink() {
    const input = document.getElementById('invite-link');
    input.select();
    navigator.clipboard.writeText(input.value).then(() => {
        const btn = event.target;
        const original = btn.textContent;
        btn.textContent = 'Kopieret!';
        setTimeout(() => btn.textContent = original, 2000);
    });
}
</script>
</body>
</html>

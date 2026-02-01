<?php
/**
 * Admin - Photos Management
 */
require_once __DIR__ . '/../includes/admin-header.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'approve') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $db->prepare("UPDATE photos SET approved = 1 WHERE id = ? AND event_id = ?");
            $stmt->execute([$id, $eventId]);
            setFlash('success', 'Billede godkendt');
            redirect(BASE_PATH . '/admin/photos.php');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            // Get filename first
            $stmt = $db->prepare("SELECT filename FROM photos WHERE id = ? AND event_id = ?");
            $stmt->execute([$id, $eventId]);
            $photo = $stmt->fetch();

            if ($photo) {
                // Delete file
                $filePath = __DIR__ . '/../uploads/photos/' . $photo['filename'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }

                // Delete from database
                $stmt = $db->prepare("DELETE FROM photos WHERE id = ? AND event_id = ?");
                $stmt->execute([$id, $eventId]);
            }

            setFlash('success', 'Billede slettet');
            redirect(BASE_PATH . '/admin/photos.php');
        }
    }
}

// Get all photos
$stmt = $db->prepare("
    SELECT p.*, g.name as guest_name
    FROM photos p
    LEFT JOIN guests g ON p.uploaded_by_guest_id = g.id
    WHERE p.event_id = ?
    ORDER BY p.uploaded_at DESC
");
$stmt->execute([$eventId]);
$photos = $stmt->fetchAll();

// Separate pending and approved
$pendingPhotos = array_filter($photos, fn($p) => !$p['approved']);
$approvedPhotos = array_filter($photos, fn($p) => $p['approved']);

require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-header__title">Billeder</h1>
        <p class="page-header__subtitle">
            <?= count($approvedPhotos) ?> godkendte billeder
            <?php if (count($pendingPhotos) > 0): ?>
                <span class="text-warning">&middot; <?= count($pendingPhotos) ?> afventer godkendelse</span>
            <?php endif; ?>
        </p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_PATH ?>/guest/photos.php" target="_blank" class="btn btn--secondary">Se gæstevisning</a>
    </div>
</div>

<!-- Pending Photos -->
<?php if (!empty($pendingPhotos)): ?>
    <div class="card mb-md">
        <h2 class="card__title mb-sm">Afventer godkendelse (<?= count($pendingPhotos) ?>)</h2>
        <div class="photo-grid">
            <?php foreach ($pendingPhotos as $photo): ?>
                <div class="photo-card photo-card--pending">
                    <img src="<?= BASE_PATH ?>/uploads/photos/<?= escape($photo['filename']) ?>"
                         alt="<?= escape($photo['caption'] ?? '') ?>"
                         class="photo-card__image">
                    <div class="photo-card__overlay">
                        <div class="photo-card__meta">
                            <?php if ($photo['caption']): ?>
                                <p class="photo-card__caption"><?= escape($photo['caption']) ?></p>
                            <?php endif; ?>
                            <p class="photo-card__uploader">Af: <?= escape($photo['guest_name'] ?? 'Ukendt') ?></p>
                        </div>
                        <div class="photo-card__actions">
                            <form method="POST" style="display: inline;">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="id" value="<?= $photo['id'] ?>">
                                <button type="submit" class="btn btn--primary btn--sm">Godkend</button>
                            </form>
                            <form method="POST" style="display: inline;">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $photo['id'] ?>">
                                <button type="submit" class="btn btn--ghost btn--sm" data-confirm="Slet dette billede?">Slet</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Approved Photos -->
<?php if (empty($photos)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state__icon">📷</div>
            <h3 class="empty-state__title">Ingen billeder endnu</h3>
            <p class="empty-state__text">Gæster kan uploade billeder fra deres visning</p>
        </div>
    </div>
<?php elseif (!empty($approvedPhotos)): ?>
    <div class="card">
        <h2 class="card__title mb-sm">Godkendte billeder (<?= count($approvedPhotos) ?>)</h2>
        <div class="photo-grid">
            <?php foreach ($approvedPhotos as $photo): ?>
                <div class="photo-card">
                    <img src="<?= BASE_PATH ?>/uploads/photos/<?= escape($photo['filename']) ?>"
                         alt="<?= escape($photo['caption'] ?? '') ?>"
                         class="photo-card__image">
                    <div class="photo-card__overlay">
                        <div class="photo-card__meta">
                            <?php if ($photo['caption']): ?>
                                <p class="photo-card__caption"><?= escape($photo['caption']) ?></p>
                            <?php endif; ?>
                            <p class="photo-card__uploader">Af: <?= escape($photo['guest_name'] ?? 'Ukendt') ?></p>
                        </div>
                        <div class="photo-card__actions">
                            <form method="POST" style="display: inline;">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $photo['id'] ?>">
                                <button type="submit" class="btn btn--ghost btn--sm" data-confirm="Slet dette billede?">🗑️</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<style>
.photo-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: var(--space-md);
}

.photo-card {
    position: relative;
    border-radius: var(--radius-md);
    overflow: hidden;
    background: var(--color-bg-subtle);
}

.photo-card--pending {
    border: 2px solid var(--color-warning);
}

.photo-card__image {
    width: 100%;
    aspect-ratio: 1;
    object-fit: cover;
    display: block;
}

.photo-card__overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, transparent 50%);
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    padding: var(--space-sm);
    opacity: 0;
    transition: opacity 0.2s;
}

.photo-card:hover .photo-card__overlay {
    opacity: 1;
}

.photo-card__caption {
    color: white;
    font-size: var(--text-sm);
    margin-bottom: var(--space-3xs);
}

.photo-card__uploader {
    color: rgba(255,255,255,0.7);
    font-size: var(--text-xs);
    margin-bottom: var(--space-xs);
}

.photo-card__actions {
    display: flex;
    gap: var(--space-xs);
}
</style>

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

// Confirm delete
document.querySelectorAll('[data-confirm]').forEach(btn => {
    btn.addEventListener('click', function(e) {
        if (!confirm(this.dataset.confirm)) {
            e.preventDefault();
        }
    });
});
</script>
</body>
</html>

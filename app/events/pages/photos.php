<?php
/**
 * Photos Management Page
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyAccountCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Ugyldig anmodning.');
        redirect("?id=$eventId&page=photos");
    }

    $action = $_POST['action'];

    if ($action === 'delete_photo') {
        $photoId = (int)($_POST['photo_id'] ?? 0);
        if ($photoId) {
            // Get filename first
            $stmt = $db->prepare("SELECT filename FROM photos WHERE id = ? AND event_id = ?");
            $stmt->execute([$photoId, $eventId]);
            $photo = $stmt->fetch();

            if ($photo) {
                // Delete from database
                $stmt = $db->prepare("DELETE FROM photos WHERE id = ? AND event_id = ?");
                $stmt->execute([$photoId, $eventId]);

                // Delete file
                $filepath = __DIR__ . '/../../../uploads/photos/' . $photo['filename'];
                if (file_exists($filepath)) {
                    unlink($filepath);
                }

                setFlash('success', 'Foto slettet.');
            }
        }
        redirect("?id=$eventId&page=photos");

    } elseif ($action === 'toggle_approve') {
        $photoId = (int)($_POST['photo_id'] ?? 0);
        if ($photoId) {
            $stmt = $db->prepare("UPDATE photos SET approved = NOT approved WHERE id = ? AND event_id = ?");
            $stmt->execute([$photoId, $eventId]);
            setFlash('success', 'Godkendelsesstatus ændret.');
        }
        redirect("?id=$eventId&page=photos");
    }
}

// Get photos
$filter = $_GET['filter'] ?? 'all';
$whereClause = "WHERE event_id = ?";
if ($filter === 'approved') {
    $whereClause .= " AND approved = TRUE";
} elseif ($filter === 'pending') {
    $whereClause .= " AND approved = FALSE";
}

$stmt = $db->prepare("
    SELECT p.*, g.name as guest_name
    FROM photos p
    LEFT JOIN guests g ON p.uploaded_by_guest_id = g.id
    $whereClause
    ORDER BY p.created_at DESC
");
$stmt->execute([$eventId]);
$photos = $stmt->fetchAll();

// Count stats
$stmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN approved THEN 1 ELSE 0 END) as approved_count FROM photos WHERE event_id = ?");
$stmt->execute([$eventId]);
$photoStats = $stmt->fetch();
?>

<div class="page-header-actions">
    <div>
        <h2 class="section-title">Fotogalleri</h2>
        <p class="section-subtitle"><?= (int)$photoStats['total'] ?> fotos (<?= (int)$photoStats['approved_count'] ?> godkendt)</p>
    </div>
</div>

<div class="filters-bar">
    <div class="filter-tabs">
        <a href="?id=<?= $eventId ?>&page=photos&filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">Alle</a>
        <a href="?id=<?= $eventId ?>&page=photos&filter=approved" class="filter-tab <?= $filter === 'approved' ? 'active' : '' ?>">Godkendte</a>
        <a href="?id=<?= $eventId ?>&page=photos&filter=pending" class="filter-tab <?= $filter === 'pending' ? 'active' : '' ?>">Afventer</a>
    </div>
</div>

<?php if (empty($photos)): ?>
<div class="card">
    <div class="empty-state">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
        </svg>
        <h3>Ingen fotos endnu</h3>
        <p>Fotos uploadet af gæster vises her.</p>
    </div>
</div>
<?php else: ?>
<div class="photos-grid">
    <?php foreach ($photos as $photo): ?>
    <div class="photo-card <?= $photo['approved'] ? '' : 'pending' ?>">
        <div class="photo-image">
            <img src="/uploads/photos/<?= htmlspecialchars($photo['filename']) ?>" alt="Foto">
            <?php if (!$photo['approved']): ?>
                <span class="pending-badge">Afventer godkendelse</span>
            <?php endif; ?>
        </div>
        <div class="photo-info">
            <span class="photo-uploader"><?= htmlspecialchars($photo['guest_name'] ?? 'Admin') ?></span>
            <span class="photo-date"><?= date('d/m H:i', strtotime($photo['created_at'])) ?></span>
        </div>
        <div class="photo-actions">
            <form method="POST" style="display: inline;">
                <?= accountCsrfField() ?>
                <input type="hidden" name="action" value="toggle_approve">
                <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
                <button type="submit" class="btn btn-secondary btn-sm">
                    <?= $photo['approved'] ? 'Skjul' : 'Godkend' ?>
                </button>
            </form>
            <form method="POST" style="display: inline;" onsubmit="return confirm('Slet dette foto?')">
                <?= accountCsrfField() ?>
                <input type="hidden" name="action" value="delete_photo">
                <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
                <button type="submit" class="btn btn-secondary btn-sm danger-text">Slet</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<style>
    .photos-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 20px;
    }
    .photo-card {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid var(--gray-200);
    }
    .photo-card.pending {
        border-color: var(--warning);
    }
    .photo-image {
        position: relative;
        aspect-ratio: 4/3;
        background: var(--gray-100);
    }
    .photo-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .pending-badge {
        position: absolute;
        top: 8px;
        left: 8px;
        background: var(--warning);
        color: white;
        font-size: 11px;
        font-weight: 600;
        padding: 4px 8px;
        border-radius: 4px;
    }
    .photo-info {
        padding: 12px;
        display: flex;
        justify-content: space-between;
        font-size: 13px;
    }
    .photo-uploader {
        font-weight: 500;
        color: var(--gray-700);
    }
    .photo-date {
        color: var(--gray-500);
    }
    .photo-actions {
        padding: 0 12px 12px;
        display: flex;
        gap: 8px;
    }
</style>

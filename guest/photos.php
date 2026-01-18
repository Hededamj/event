<?php
/**
 * Guest - Photos View & Upload
 */
require_once __DIR__ . '/../includes/guest-header.php';

$uploadError = null;
$uploadSuccess = false;

// Handle photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    $file = $_FILES['photo'];
    $errors = validateImageUpload($file);

    if (empty($errors)) {
        $filename = generateUploadFilename($file['name']);
        $uploadPath = __DIR__ . '/../uploads/photos/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            $caption = trim($_POST['caption'] ?? '');

            $stmt = $db->prepare("
                INSERT INTO photos (event_id, uploaded_by_guest_id, filename, original_name, caption)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $eventId,
                $guestId,
                $filename,
                $file['name'],
                $caption ?: null
            ]);

            $uploadSuccess = true;
            setFlash('success', 'Tak for billedet!');
            redirect('/guest/photos.php');
        } else {
            $uploadError = 'Kunne ikke uploade filen. Prøv igen.';
        }
    } else {
        $uploadError = implode(' ', $errors);
    }
}

// Get photos
$stmt = $db->prepare("
    SELECT p.*, g.name as guest_name, u.name as user_name
    FROM photos p
    LEFT JOIN guests g ON p.uploaded_by_guest_id = g.id
    LEFT JOIN users u ON p.uploaded_by_user_id = u.id
    WHERE p.event_id = ? AND p.approved = 1
    ORDER BY p.uploaded_at DESC
");
$stmt->execute([$eventId]);
$photos = $stmt->fetchAll();
?>

<h1 class="h2 mb-sm text-center">Billeder</h1>
<p class="text-center text-muted mb-md">
    Del dine billeder fra <?= escape($event['confirmand_name']) ?>s <?= escape($event['name']) ?>
</p>

<!-- Upload Form -->
<div class="card mb-md">
    <h2 class="card__title mb-sm">Del et billede</h2>

    <?php if ($uploadError): ?>
        <div class="alert alert--error mb-sm"><?= escape($uploadError) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label class="form-label">Vælg billede</label>
            <input type="file"
                   name="photo"
                   accept="image/jpeg,image/png,image/gif,image/webp"
                   class="form-input"
                   required
                   style="padding: var(--space-xs);">
            <p class="small text-muted mt-xs">
                JPEG, PNG, GIF eller WebP. Maks 10MB.
            </p>
        </div>

        <div class="form-group">
            <label class="form-label">Billedtekst (valgfrit)</label>
            <input type="text"
                   name="caption"
                   class="form-input"
                   placeholder="Skriv en kort tekst...">
        </div>

        <button type="submit" class="btn btn--primary btn--block">
            Upload billede
        </button>
    </form>
</div>

<!-- Photo Gallery -->
<?php if (empty($photos)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state__icon">📷</div>
            <h3 class="empty-state__title">Ingen billeder endnu</h3>
            <p class="empty-state__text">Vær den første til at dele et billede!</p>
        </div>
    </div>

<?php else: ?>
    <h2 class="h4 mb-sm">Galleri (<?= count($photos) ?>)</h2>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: var(--space-sm);">
        <?php foreach ($photos as $photo): ?>
            <div class="card" style="padding: 0; overflow: hidden;">
                <img src="/uploads/photos/<?= escape($photo['filename']) ?>"
                     alt="<?= escape($photo['caption'] ?? 'Billede fra festen') ?>"
                     style="width: 100%; aspect-ratio: 1; object-fit: cover; cursor: pointer;"
                     onclick="openPhotoModal('<?= escape($photo['filename']) ?>', '<?= escape(addslashes($photo['caption'] ?? '')) ?>')">

                <?php if ($photo['caption']): ?>
                    <div style="padding: var(--space-xs);">
                        <p class="small"><?= escape($photo['caption']) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Photo Modal -->
<div id="photo-modal" class="modal-overlay" onclick="closeModal('photo-modal')">
    <div class="modal" style="max-width: 90vw; max-height: 90vh; padding: 0; background: transparent; box-shadow: none;" onclick="event.stopPropagation()">
        <img id="modal-photo" src="" alt="" style="max-width: 100%; max-height: 80vh; border-radius: var(--radius-lg);">
        <p id="modal-caption" class="text-center mt-sm" style="color: white;"></p>
    </div>
</div>

<script>
function openPhotoModal(filename, caption) {
    document.getElementById('modal-photo').src = '/uploads/photos/' + filename;
    document.getElementById('modal-caption').textContent = caption || '';
    openModal('photo-modal');
}
</script>

<?php require_once __DIR__ . '/../includes/guest-footer.php'; ?>
</body>
</html>

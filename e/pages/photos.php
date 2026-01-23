<?php
/**
 * Guest Photos Page
 */

// Handle photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    $file = $_FILES['photo'];
    $errors = validateImageUpload($file);

    if (empty($errors)) {
        $uploadDir = __DIR__ . '/../../uploads/photos/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = generateUploadFilename($file['name']);
        $filepath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $stmt = $db->prepare("INSERT INTO photos (event_id, uploaded_by_guest_id, filename, approved) VALUES (?, ?, ?, FALSE)");
            $stmt->execute([$eventId, $currentGuest['id'], $filename]);
            setFlash('success', 'Foto uploadet! Det vises når det er godkendt.');
        } else {
            setFlash('error', 'Kunne ikke uploade billedet. Prøv igen.');
        }
    } else {
        setFlash('error', implode(' ', $errors));
    }
    redirect("/e/$slug/photos");
}

// Get approved photos
$stmt = $db->prepare("
    SELECT p.*, g.name as uploader_name
    FROM photos p
    LEFT JOIN guests g ON p.uploaded_by_guest_id = g.id
    WHERE p.event_id = ? AND p.approved = TRUE
    ORDER BY p.created_at DESC
");
$stmt->execute([$eventId]);
$photos = $stmt->fetchAll();

// Get user's pending photos
$stmt = $db->prepare("SELECT * FROM photos WHERE event_id = ? AND uploaded_by_guest_id = ? AND approved = FALSE");
$stmt->execute([$eventId, $currentGuest['id']]);
$pendingPhotos = $stmt->fetchAll();
?>

<h1 class="serif" style="font-size: 24px; text-align: center; margin-bottom: 8px;">Fotogalleri</h1>
<p style="text-align: center; color: var(--gray-600); margin-bottom: 24px;">
    Del dine billeder fra dagen
</p>

<!-- Upload Section -->
<div class="card" style="text-align: center;">
    <form method="POST" enctype="multipart/form-data" id="uploadForm">
        <input type="file" name="photo" id="photoInput" accept="image/*" style="display: none;" onchange="this.form.submit()">
        <button type="button" class="btn btn-primary" onclick="document.getElementById('photoInput').click()">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            Upload billede
        </button>
    </form>
    <p style="font-size: 13px; color: var(--gray-400); margin-top: 12px;">Max 10MB. JPEG, PNG eller WebP.</p>
</div>

<?php if (!empty($pendingPhotos)): ?>
<div class="card" style="background: #fffbeb; border: 1px solid #fde68a;">
    <p style="font-size: 14px; color: #b45309;">
        <strong><?= count($pendingPhotos) ?> billede<?= count($pendingPhotos) > 1 ? 'r' : '' ?></strong> afventer godkendelse.
    </p>
</div>
<?php endif; ?>

<?php if (empty($photos)): ?>
<div class="card">
    <div class="empty-state">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
        <p>Ingen billeder endnu. Vær den første til at dele!</p>
    </div>
</div>
<?php else: ?>
<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px;">
    <?php foreach ($photos as $photo): ?>
    <div style="aspect-ratio: 1; border-radius: 12px; overflow: hidden; background: var(--gray-100);">
        <img src="/uploads/photos/<?= htmlspecialchars($photo['filename']) ?>" alt="Billede" style="width: 100%; height: 100%; object-fit: cover;">
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<style>
    @media (min-width: 480px) {
        div[style*="grid-template-columns"] {
            grid-template-columns: repeat(3, 1fr) !important;
        }
    }
</style>

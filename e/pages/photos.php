<?php
/**
 * Guest Photos Page - Nordic Design
 */

// Handle photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Ugyldig anmodning. Prøv igen.');
        redirect("/e/$slug/photos");
    }
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
            $stmt = $db->prepare("INSERT INTO photos (event_id, uploaded_by_guest_id, filename, approved) VALUES (?, ?, ?, TRUE)");
            $stmt->execute([$eventId, $currentGuest['id'], $filename]);
            setFlash('success', 'Foto uploadet!');
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

?>

<!-- Page Header -->
<div class="page-header" style="text-align: center;">
    <h1 class="page-header-title">Fotogalleri</h1>
    <p class="page-header-subtitle">Del dine billeder fra dagen</p>
</div>

<!-- Upload Section -->
<div class="card" style="text-align: center;">
    <form method="POST" enctype="multipart/form-data" id="uploadForm">
        <?= csrfField() ?>
        <input type="file" name="photo" id="photoInput" accept="image/*" style="display: none;" onchange="this.form.submit()">
        <div style="width: 64px; height: 64px; background: var(--cream); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; color: var(--sage-dark);">
            <svg width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
        </div>
        <button type="button" class="btn btn-sage" onclick="document.getElementById('photoInput').click()">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Upload billede
        </button>
    </form>
    <p style="font-size: 13px; color: var(--charcoal-light); margin-top: 16px;">Max 10MB. JPEG, PNG eller WebP.</p>
</div>


<?php if (empty($photos)): ?>
<div class="card">
    <div class="empty-state">
        <div class="empty-state-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
        </div>
        <h3>Ingen billeder endnu</h3>
        <p>Vær den første til at dele et billede!</p>
    </div>
</div>
<?php else: ?>
<div class="photo-grid">
    <?php foreach ($photos as $photo): ?>
    <div class="photo-item">
        <img src="/uploads/photos/<?= htmlspecialchars($photo['filename']) ?>" alt="Billede" loading="lazy">
        <?php if ($photo['uploader_name']): ?>
        <div class="photo-overlay">
            <span><?= htmlspecialchars($photo['uploader_name']) ?></span>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<style>
    .photo-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }

    .photo-item {
        position: relative;
        aspect-ratio: 1;
        border-radius: 16px;
        overflow: hidden;
        background: var(--cream);
    }

    .photo-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.4s var(--ease-out);
    }

    .photo-item:hover img {
        transform: scale(1.05);
    }

    .photo-overlay {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 12px;
        background: linear-gradient(transparent, rgba(0,0,0,0.5));
        color: var(--white);
        font-size: 12px;
        font-weight: 500;
        opacity: 0;
        transition: opacity 0.3s;
    }

    .photo-item:hover .photo-overlay {
        opacity: 1;
    }

    @media (min-width: 480px) {
        .photo-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }
</style>

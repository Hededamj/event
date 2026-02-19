<?php
/**
 * Vendor Gallery Management
 * Photo gallery for vendors — upload, edit captions, reorder, and delete images.
 * Maximum 20 images per vendor.
 */

$pageTitle = 'Galleri';

require_once __DIR__ . '/../includes/vendor-header.php';
require_once __DIR__ . '/../includes/vendor-validation.php';

$db = getDB();
$maxPhotos = 20;
$maxUploadAtOnce = 5;

// Ensure gallery upload directory exists
$galleryDir = __DIR__ . '/../../uploads/vendors/gallery';
if (!is_dir($galleryDir)) {
    mkdir($galleryDir, 0755, true);
}

// ============================================================
// Handle POST actions
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyVendorCsrfToken($csrfToken)) {
        setFlash('error', 'Ugyldig anmodning. Prøv igen.');
        redirect('/subcontractor/dashboard/gallery.php');
    }

    // --- UPLOAD ---
    if ($action === 'upload') {
        // Count existing photos
        $countStmt = $db->prepare("SELECT COUNT(*) FROM vendor_gallery WHERE vendor_id = ?");
        $countStmt->execute([$vendorId]);
        $currentCount = (int) $countStmt->fetchColumn();

        if ($currentCount >= $maxPhotos) {
            setFlash('error', 'Du har allerede det maksimale antal billeder (' . $maxPhotos . ').');
            redirect('/subcontractor/dashboard/gallery.php');
        }

        // Process uploaded files
        $files = $_FILES['gallery_images'] ?? null;
        if (!$files || !is_array($files['name']) || empty($files['name'][0])) {
            setFlash('error', 'Ingen billeder valgt.');
            redirect('/subcontractor/dashboard/gallery.php');
        }

        $fileCount = count($files['name']);
        if ($fileCount > $maxUploadAtOnce) {
            setFlash('error', 'Du kan højst uploade ' . $maxUploadAtOnce . ' billeder ad gangen.');
            redirect('/subcontractor/dashboard/gallery.php');
        }

        $remaining = $maxPhotos - $currentCount;
        if ($fileCount > $remaining) {
            setFlash('error', 'Du kan kun uploade ' . $remaining . ' billede(r) mere (maks ' . $maxPhotos . ' i alt).');
            redirect('/subcontractor/dashboard/gallery.php');
        }

        // Get next sort_order
        $sortStmt = $db->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM vendor_gallery WHERE vendor_id = ?");
        $sortStmt->execute([$vendorId]);
        $nextSort = (int) $sortStmt->fetchColumn();

        $timestamp = time();
        $uploadedCount = 0;
        $errors = [];

        $insertStmt = $db->prepare("
            INSERT INTO vendor_gallery (vendor_id, filename, original_name, caption, sort_order)
            VALUES (?, ?, ?, '', ?)
        ");

        for ($i = 0; $i < $fileCount; $i++) {
            $file = [
                'name'     => $files['name'][$i],
                'type'     => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i],
            ];

            // Skip empty file slots
            if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            // Validate
            $fileErrors = validateVendorImageUpload($file);
            if (!empty($fileErrors)) {
                $errors[] = htmlspecialchars($file['name']) . ': ' . implode(', ', $fileErrors);
                continue;
            }

            // Generate unique filename
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = 'vendor_' . $vendorId . '_' . $timestamp . '_' . $i . '.' . $ext;

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $galleryDir . '/' . $filename)) {
                $errors[] = htmlspecialchars($file['name']) . ': Upload fejlede.';
                continue;
            }

            // Insert into database
            try {
                $nextSort++;
                $insertStmt->execute([
                    $vendorId,
                    $filename,
                    $file['name'],
                    $nextSort,
                ]);
                $uploadedCount++;
            } catch (Exception $e) {
                error_log("Failed to insert gallery image: " . $e->getMessage());
                // Clean up the file if DB insert failed
                @unlink($galleryDir . '/' . $filename);
                $errors[] = htmlspecialchars($file['name']) . ': Databasefejl.';
            }
        }

        if ($uploadedCount > 0 && empty($errors)) {
            setFlash('success', $uploadedCount . ' billede(r) uploadet.');
        } elseif ($uploadedCount > 0 && !empty($errors)) {
            setFlash('info', $uploadedCount . ' billede(r) uploadet, men nogle fejlede: ' . implode(' | ', $errors));
        } else {
            setFlash('error', 'Ingen billeder blev uploadet. ' . implode(' | ', $errors));
        }
        redirect('/subcontractor/dashboard/gallery.php');
    }

    // --- UPDATE CAPTION ---
    if ($action === 'update_caption') {
        $imageId = (int) ($_POST['image_id'] ?? 0);
        $caption = trim($_POST['caption'] ?? '');

        if (mb_strlen($caption) > 255) {
            setFlash('error', 'Billedtekst må højst være 255 tegn.');
            redirect('/subcontractor/dashboard/gallery.php');
        }

        try {
            $stmt = $db->prepare("UPDATE vendor_gallery SET caption = ? WHERE id = ? AND vendor_id = ?");
            $stmt->execute([$caption, $imageId, $vendorId]);
            setFlash('success', 'Billedtekst opdateret.');
        } catch (Exception $e) {
            error_log("Failed to update gallery caption: " . $e->getMessage());
            setFlash('error', 'Der opstod en fejl. Prøv igen.');
        }
        redirect('/subcontractor/dashboard/gallery.php');
    }

    // --- DELETE ---
    if ($action === 'delete') {
        $imageId = (int) ($_POST['image_id'] ?? 0);

        try {
            // Fetch filename before deleting
            $stmt = $db->prepare("SELECT filename FROM vendor_gallery WHERE id = ? AND vendor_id = ?");
            $stmt->execute([$imageId, $vendorId]);
            $image = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($image) {
                // Delete from database
                $delStmt = $db->prepare("DELETE FROM vendor_gallery WHERE id = ? AND vendor_id = ?");
                $delStmt->execute([$imageId, $vendorId]);

                // Delete file from disk
                $filePath = $galleryDir . '/' . $image['filename'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }

                setFlash('success', 'Billede slettet.');
            } else {
                setFlash('error', 'Billede ikke fundet.');
            }
        } catch (Exception $e) {
            error_log("Failed to delete gallery image: " . $e->getMessage());
            setFlash('error', 'Der opstod en fejl. Prøv igen.');
        }
        redirect('/subcontractor/dashboard/gallery.php');
    }

    // --- REORDER ---
    if ($action === 'reorder') {
        $order = $_POST['order'] ?? [];
        if (is_array($order)) {
            try {
                $stmt = $db->prepare("UPDATE vendor_gallery SET sort_order = ? WHERE id = ? AND vendor_id = ?");
                foreach ($order as $position => $id) {
                    $stmt->execute([(int) $position, (int) $id, $vendorId]);
                }
                setFlash('success', 'Rækkefølge opdateret.');
            } catch (Exception $e) {
                error_log("Failed to reorder gallery images: " . $e->getMessage());
                setFlash('error', 'Der opstod en fejl. Prøv igen.');
            }
        }
        redirect('/subcontractor/dashboard/gallery.php');
    }
}

// ============================================================
// Load gallery images
// ============================================================
try {
    $stmt = $db->prepare("
        SELECT * FROM vendor_gallery
        WHERE vendor_id = ?
        ORDER BY sort_order ASC, created_at ASC
    ");
    $stmt->execute([$vendorId]);
    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to load gallery images: " . $e->getMessage());
    $photos = [];
}

$photoCount = count($photos);
?>

<style>
    .upload-zone {
        border: 2px dashed var(--border);
        border-radius: 12px;
        padding: 32px 24px;
        text-align: center;
        cursor: pointer;
        transition: all 0.25s var(--ease-out);
        margin-bottom: 24px;
        background: var(--white);
    }
    .upload-zone:hover,
    .upload-zone.dragover {
        border-color: var(--accent);
        background: rgba(168, 181, 160, 0.05);
    }
    .upload-zone svg.upload-icon {
        width: 40px;
        height: 40px;
        color: var(--text-secondary);
        margin-bottom: 12px;
    }
    .upload-zone p {
        font-size: 14px;
        color: var(--text-secondary);
        margin: 0;
    }
    .upload-zone .upload-link {
        color: var(--accent-dark);
        font-weight: 600;
        text-decoration: underline;
        cursor: pointer;
    }
    .upload-zone .upload-hint {
        font-size: 12px;
        color: var(--text-secondary);
        margin-top: 8px;
        opacity: 0.7;
    }
    .upload-zone input[type="file"] {
        display: none;
    }

    .gallery-caption {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(to top, rgba(0,0,0,0.6), transparent);
        color: var(--white);
        padding: 24px 10px 8px;
        font-size: 12px;
        line-height: 1.3;
        pointer-events: none;
    }

    .gallery-item-overlay {
        justify-content: space-between;
        align-items: flex-start;
    }

    .gallery-item-actions {
        margin-left: auto;
    }

    .gallery-item-actions button.edit-btn:hover {
        background: var(--accent-dark);
        color: var(--white);
    }

    /* Caption edit modal */
    .caption-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }
    .caption-modal-overlay.active {
        display: flex;
    }
    .caption-modal {
        background: var(--white);
        border-radius: 16px;
        padding: 28px;
        width: 90%;
        max-width: 440px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
    }
    .caption-modal h3 {
        font-family: var(--font-display);
        font-size: 20px;
        font-weight: 500;
        color: var(--text);
        margin: 0 0 16px;
    }
    .caption-modal .form-group {
        margin-bottom: 16px;
    }
    .caption-modal .modal-footer {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 20px;
    }

    .photo-counter {
        font-size: 14px;
        color: var(--text-secondary);
        font-weight: 500;
    }
</style>

<!-- Page Header -->
<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Galleri</h1>
        <p class="page-subtitle">
            <span class="photo-counter"><?= $photoCount ?> / <?= $maxPhotos ?> billeder</span>
        </p>
    </div>
</div>

<!-- Upload Zone -->
<?php if ($photoCount < $maxPhotos): ?>
    <form method="POST" action="" enctype="multipart/form-data" id="uploadForm">
        <?= vendorCsrfField() ?>
        <input type="hidden" name="action" value="upload">
        <div class="upload-zone" id="uploadZone" onclick="document.getElementById('galleryFileInput').click();">
            <svg class="upload-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            <p><span class="upload-link">Klik for at uploade</span> eller traek billeder hertil</p>
            <p class="upload-hint">JPEG, PNG, GIF eller WebP. Maks 5MB pr. billede. Op til <?= $maxUploadAtOnce ?> ad gangen.</p>
            <input
                type="file"
                id="galleryFileInput"
                name="gallery_images[]"
                multiple
                accept="image/jpeg,image/png,image/gif,image/webp"
            >
        </div>
    </form>
<?php endif; ?>

<!-- Gallery Grid or Empty State -->
<?php if (empty($photos)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            </div>
            <h3>Ingen billeder endnu</h3>
            <p>Upload billeder af dit arbejde for at vise arrangorer hvad du kan tilbyde.</p>
        </div>
    </div>
<?php else: ?>
    <div class="gallery-grid">
        <?php foreach ($photos as $photo): ?>
            <div class="gallery-item" data-id="<?= (int) $photo['id'] ?>">
                <img
                    src="/uploads/vendors/gallery/<?= htmlspecialchars($photo['filename']) ?>"
                    alt="<?= htmlspecialchars($photo['caption'] ?: $photo['original_name']) ?>"
                    loading="lazy"
                >

                <?php if (!empty($photo['caption'])): ?>
                    <div class="gallery-caption"><?= htmlspecialchars($photo['caption']) ?></div>
                <?php endif; ?>

                <div class="gallery-item-overlay">
                    <div class="gallery-item-actions">
                        <!-- Edit caption -->
                        <button
                            type="button"
                            class="edit-btn"
                            title="Rediger billedtekst"
                            onclick="openCaptionModal(<?= (int) $photo['id'] ?>, <?= htmlspecialchars(json_encode($photo['caption'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)"
                        >
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                        </button>

                        <!-- Delete -->
                        <button
                            type="button"
                            title="Slet billede"
                            onclick="deleteImage(<?= (int) $photo['id'] ?>)"
                        >
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Hidden delete form -->
<form method="POST" id="deleteForm" style="display: none;">
    <?= vendorCsrfField() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="image_id" id="deleteImageId" value="">
</form>

<!-- Hidden reorder form -->
<form method="POST" id="reorderForm" style="display: none;">
    <?= vendorCsrfField() ?>
    <input type="hidden" name="action" value="reorder">
</form>

<!-- Caption Edit Modal -->
<div class="caption-modal-overlay" id="captionModal">
    <div class="caption-modal">
        <h3>Rediger billedtekst</h3>
        <form method="POST" id="captionForm">
            <?= vendorCsrfField() ?>
            <input type="hidden" name="action" value="update_caption">
            <input type="hidden" name="image_id" id="captionImageId" value="">

            <div class="form-group">
                <label class="form-label" for="captionInput">Billedtekst</label>
                <input
                    type="text"
                    id="captionInput"
                    name="caption"
                    class="form-input"
                    placeholder="Beskriv billedet..."
                    maxlength="255"
                >
                <span class="form-hint">Maks 255 tegn</span>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeCaptionModal()">Annuller</button>
                <button type="submit" class="btn btn-primary">Gem</button>
            </div>
        </form>
    </div>
</div>

<script>
    // ── Upload zone interactions ──────────────────────────────────
    (function() {
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('galleryFileInput');
        const uploadForm = document.getElementById('uploadForm');

        if (!uploadZone || !fileInput || !uploadForm) return;

        // Submit form when files are selected
        fileInput.addEventListener('change', function() {
            if (fileInput.files.length > 0) {
                uploadForm.submit();
            }
        });

        // Drag and drop
        uploadZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });

        uploadZone.addEventListener('dragleave', function() {
            uploadZone.classList.remove('dragover');
        });

        uploadZone.addEventListener('drop', function(e) {
            e.preventDefault();
            uploadZone.classList.remove('dragover');

            if (e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                uploadForm.submit();
            }
        });
    })();

    // ── Caption modal ─────────────────────────────────────────────
    function openCaptionModal(imageId, currentCaption) {
        document.getElementById('captionImageId').value = imageId;
        document.getElementById('captionInput').value = currentCaption || '';
        document.getElementById('captionModal').classList.add('active');
        // Focus the input after a brief delay for animation
        setTimeout(function() {
            document.getElementById('captionInput').focus();
        }, 100);
    }

    function closeCaptionModal() {
        document.getElementById('captionModal').classList.remove('active');
    }

    // Close modal on overlay click
    document.getElementById('captionModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeCaptionModal();
        }
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeCaptionModal();
        }
    });

    // ── Delete image ──────────────────────────────────────────────
    function deleteImage(imageId) {
        if (!confirm('Er du sikker på, at du vil slette dette billede?')) return;
        document.getElementById('deleteImageId').value = imageId;
        document.getElementById('deleteForm').submit();
    }
</script>

<?php require_once __DIR__ . '/../includes/vendor-footer.php'; ?>

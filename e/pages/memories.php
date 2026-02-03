<?php
/**
 * Guest Memories Timeline Page - Nordic Design
 * Guests can upload pictures with year/description to create a timeline
 */

require_once __DIR__ . '/../../includes/qr-functions.php';

// Check if timeline is enabled for this event
$timelineEnabled = !empty($event['timeline_enabled']);
$timelineStartDate = $event['timeline_start_date'] ?? null;
$timelineLabel = $event['timeline_label'] ?? 'Minder gennem tiden';

// If timeline not enabled, show message
if (!$timelineEnabled) {
    ?>
    <div class="page-header" style="text-align: center;">
        <h1 class="page-header-title">Minde-tidslinje</h1>
        <p class="page-header-subtitle">Denne funktion er ikke aktiveret for dette arrangement</p>
    </div>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <h3>Kommer snart</h3>
            <p>Arrangøren har endnu ikke aktiveret minde-tidslinjen.</p>
        </div>
    </div>
    <?php
    return;
}

// Calculate timeline years
$timelineYears = getTimelineYears($timelineStartDate, $event['event_date']);
$minYear = !empty($timelineYears) ? min($timelineYears) : (int)date('Y') - 50;
$maxYear = !empty($timelineYears) ? max($timelineYears) : (int)date('Y');

// Handle memory upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['memory_photo'])) {
    $file = $_FILES['memory_photo'];
    $memoryYear = isset($_POST['memory_year']) ? (int)$_POST['memory_year'] : null;
    $caption = trim($_POST['caption'] ?? '');

    // Validate year
    if (!$memoryYear || $memoryYear < $minYear || $memoryYear > $maxYear) {
        setFlash('error', 'Vælg et gyldigt årstal.');
        redirect("/e/$slug/memories");
    }

    // Validate image
    $errors = validateImageUpload($file);

    if (empty($errors)) {
        $uploadDir = __DIR__ . '/../../uploads/memories/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = generateUploadFilename($file['name']);
        $filepath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $stmt = $db->prepare("
                INSERT INTO timeline_memories (event_id, uploaded_by_guest_id, filename, original_filename, caption, memory_year, approved)
                VALUES (?, ?, ?, ?, ?, ?, TRUE)
            ");
            $stmt->execute([
                $eventId,
                $currentGuest['id'],
                $filename,
                $file['name'],
                $caption ?: null,
                $memoryYear
            ]);
            setFlash('success', 'Dit minde er tilføjet til tidslinjen!');
        } else {
            setFlash('error', 'Kunne ikke uploade billedet. Prøv igen.');
        }
    } else {
        setFlash('error', implode(' ', $errors));
    }
    redirect("/e/$slug/memories");
}

// Get approved memories for this event
$stmt = $db->prepare("
    SELECT m.*, g.name as uploader_name
    FROM timeline_memories m
    LEFT JOIN guests g ON m.uploaded_by_guest_id = g.id
    WHERE m.event_id = ? AND m.approved = 1
    ORDER BY m.memory_year ASC, m.created_at ASC
");
$stmt->execute([$eventId]);
$memories = $stmt->fetchAll();

// Group memories by year
$memoriesByYear = [];
foreach ($memories as $memory) {
    $year = $memory['memory_year'];
    if (!isset($memoriesByYear[$year])) {
        $memoriesByYear[$year] = [];
    }
    $memoriesByYear[$year][] = $memory;
}

?>

<!-- Page Header -->
<div class="page-header" style="text-align: center;">
    <h1 class="page-header-title"><?= htmlspecialchars($timelineLabel) ?></h1>
    <p class="page-header-subtitle">Del dine minder og billeder fra årene der er gået</p>
</div>

<!-- Upload Section -->
<div class="card upload-memory-card">
    <div class="card-header">
        <h2 class="card-title">Tilføj et minde</h2>
        <div class="card-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 4v16m8-8H4"></path></svg>
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data" id="memoryForm" class="memory-form">
        <div class="upload-zone" id="uploadZone">
            <input type="file" name="memory_photo" id="memoryPhoto" accept="image/*" required>
            <div class="upload-zone-content">
                <div class="upload-zone-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
                <p class="upload-zone-text">Klik eller træk et billede hertil</p>
                <p class="upload-zone-hint">JPEG, PNG eller WebP. Max 10MB.</p>
            </div>
            <div class="upload-preview" id="uploadPreview" style="display: none;">
                <img id="previewImage" alt="Preview">
                <button type="button" class="remove-preview" id="removePreview">&times;</button>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Årstal</label>
                <select name="memory_year" class="form-input" required>
                    <option value="">Vælg årstal...</option>
                    <?php foreach (array_reverse($timelineYears) as $year): ?>
                    <option value="<?= $year ?>"><?= $year ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Kort beskrivelse <span style="color: var(--charcoal-light); font-weight: 400;">(valgfrit)</span></label>
            <input type="text" name="caption" class="form-input" placeholder="F.eks. &quot;Fra sommerferien i Grækenland&quot;" maxlength="200">
            <p class="form-hint">Max 200 tegn</p>
        </div>

        <button type="submit" class="btn btn-sage btn-full">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Tilføj til tidslinjen
        </button>
    </form>
</div>

<!-- Timeline -->
<?php if (empty($memories)): ?>
<div class="card">
    <div class="empty-state">
        <div class="empty-state-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        </div>
        <h3>Ingen minder endnu</h3>
        <p>Vær den første til at dele et minde fra fortiden!</p>
    </div>
</div>
<?php else: ?>
<div class="timeline-container">
    <div class="timeline">
        <?php
        // Show years with memories and some milestone years
        $yearsToShow = array_keys($memoriesByYear);
        sort($yearsToShow);

        foreach ($yearsToShow as $year):
            $yearMemories = $memoriesByYear[$year];
        ?>
        <div class="timeline-year" data-year="<?= $year ?>">
            <div class="timeline-year-marker">
                <span class="year-label"><?= $year ?></span>
                <span class="memory-count"><?= count($yearMemories) ?> <?= count($yearMemories) === 1 ? 'minde' : 'minder' ?></span>
            </div>
            <div class="timeline-year-content">
                <?php foreach ($yearMemories as $memory): ?>
                <div class="memory-card" data-id="<?= $memory['id'] ?>">
                    <div class="memory-image">
                        <img src="/uploads/memories/<?= htmlspecialchars($memory['filename']) ?>"
                             alt="<?= htmlspecialchars($memory['caption'] ?? 'Minde fra ' . $memory['memory_year']) ?>"
                             loading="lazy">
                    </div>
                    <?php if ($memory['caption'] || $memory['uploader_name']): ?>
                    <div class="memory-info">
                        <?php if ($memory['caption']): ?>
                        <p class="memory-caption"><?= htmlspecialchars($memory['caption']) ?></p>
                        <?php endif; ?>
                        <?php if ($memory['uploader_name']): ?>
                        <p class="memory-uploader">Delt af <?= htmlspecialchars($memory['uploader_name']) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Lightbox for viewing images -->
<div class="lightbox" id="lightbox">
    <button class="lightbox-close" id="lightboxClose">&times;</button>
    <div class="lightbox-content">
        <img id="lightboxImage" src="" alt="">
        <div class="lightbox-info" id="lightboxInfo"></div>
    </div>
</div>
<?php endif; ?>

<style>
/* Upload Zone */
.upload-memory-card {
    margin-bottom: 32px;
}

.memory-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.upload-zone {
    position: relative;
    border: 2px dashed var(--cream-dark);
    border-radius: 16px;
    padding: 32px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s var(--ease-out);
    background: var(--cream);
}

.upload-zone:hover {
    border-color: var(--sage);
    background: #F5F3EF;
}

.upload-zone.dragover {
    border-color: var(--sage);
    background: var(--sage-light);
}

.upload-zone input[type="file"] {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
}

.upload-zone-icon {
    width: 56px;
    height: 56px;
    background: var(--white);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    color: var(--sage-dark);
}

.upload-zone-icon svg {
    width: 28px;
    height: 28px;
}

.upload-zone-text {
    font-weight: 500;
    color: var(--charcoal);
    margin-bottom: 4px;
}

.upload-zone-hint {
    font-size: 13px;
    color: var(--charcoal-light);
}

.upload-preview {
    position: relative;
}

.upload-preview img {
    max-width: 100%;
    max-height: 200px;
    border-radius: 12px;
    object-fit: contain;
}

.remove-preview {
    position: absolute;
    top: -8px;
    right: -8px;
    width: 28px;
    height: 28px;
    background: var(--error);
    color: var(--white);
    border: none;
    border-radius: 50%;
    font-size: 18px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
}

/* Timeline Styles */
.timeline-container {
    position: relative;
}

.timeline {
    display: flex;
    flex-direction: column;
    gap: 0;
}

.timeline-year {
    display: flex;
    gap: 20px;
    position: relative;
}

.timeline-year::before {
    content: '';
    position: absolute;
    left: 50px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: var(--sage-light);
}

.timeline-year:last-child::before {
    height: 36px;
}

.timeline-year-marker {
    flex-shrink: 0;
    width: 100px;
    padding-top: 8px;
    text-align: right;
    position: relative;
}

.timeline-year-marker::after {
    content: '';
    position: absolute;
    right: -30px;
    top: 16px;
    width: 12px;
    height: 12px;
    background: var(--sage);
    border: 3px solid var(--white);
    border-radius: 50%;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.year-label {
    display: block;
    font-family: var(--font-display);
    font-size: 24px;
    font-weight: 500;
    color: var(--charcoal);
}

.memory-count {
    font-size: 12px;
    color: var(--charcoal-light);
}

.timeline-year-content {
    flex: 1;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 12px;
    padding: 8px 0 32px 20px;
}

/* Memory Cards */
.memory-card {
    background: var(--white);
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    cursor: pointer;
    transition: all 0.3s var(--ease-out);
}

.memory-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
}

.memory-image {
    aspect-ratio: 1;
    overflow: hidden;
}

.memory-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.4s var(--ease-out);
}

.memory-card:hover .memory-image img {
    transform: scale(1.05);
}

.memory-info {
    padding: 12px;
}

.memory-caption {
    font-size: 13px;
    color: var(--charcoal);
    margin-bottom: 4px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.memory-uploader {
    font-size: 11px;
    color: var(--charcoal-light);
}

/* Lightbox */
.lightbox {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.9);
    z-index: 2000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.lightbox.active {
    display: flex;
}

.lightbox-close {
    position: absolute;
    top: 20px;
    right: 20px;
    width: 44px;
    height: 44px;
    background: rgba(255,255,255,0.1);
    border: none;
    border-radius: 50%;
    color: var(--white);
    font-size: 28px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
}

.lightbox-close:hover {
    background: rgba(255,255,255,0.2);
}

.lightbox-content {
    max-width: 90vw;
    max-height: 90vh;
    text-align: center;
}

.lightbox-content img {
    max-width: 100%;
    max-height: 80vh;
    object-fit: contain;
    border-radius: 8px;
}

.lightbox-info {
    color: var(--white);
    margin-top: 16px;
    font-size: 14px;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .timeline-year {
        flex-direction: column;
        gap: 8px;
    }

    .timeline-year::before {
        display: none;
    }

    .timeline-year-marker {
        width: auto;
        text-align: left;
        padding: 16px 20px;
        background: var(--cream);
        border-radius: 12px;
        display: flex;
        align-items: baseline;
        gap: 12px;
    }

    .timeline-year-marker::after {
        display: none;
    }

    .year-label {
        font-size: 20px;
    }

    .timeline-year-content {
        padding: 0 0 24px 0;
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<script>
// Upload Zone interactions
const uploadZone = document.getElementById('uploadZone');
const photoInput = document.getElementById('memoryPhoto');
const uploadPreview = document.getElementById('uploadPreview');
const previewImage = document.getElementById('previewImage');
const removePreviewBtn = document.getElementById('removePreview');
const uploadContent = document.querySelector('.upload-zone-content');

if (uploadZone) {
    // Drag and drop
    ['dragenter', 'dragover'].forEach(eventName => {
        uploadZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });
    });

    ['dragleave', 'drop'].forEach(eventName => {
        uploadZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
        });
    });

    uploadZone.addEventListener('drop', (e) => {
        const file = e.dataTransfer.files[0];
        if (file && file.type.startsWith('image/')) {
            photoInput.files = e.dataTransfer.files;
            showPreview(file);
        }
    });

    // File input change
    photoInput.addEventListener('change', (e) => {
        if (e.target.files[0]) {
            showPreview(e.target.files[0]);
        }
    });

    // Remove preview
    if (removePreviewBtn) {
        removePreviewBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            photoInput.value = '';
            uploadPreview.style.display = 'none';
            uploadContent.style.display = 'block';
        });
    }
}

function showPreview(file) {
    const reader = new FileReader();
    reader.onload = (e) => {
        previewImage.src = e.target.result;
        uploadPreview.style.display = 'block';
        uploadContent.style.display = 'none';
    };
    reader.readAsDataURL(file);
}

// Lightbox
const lightbox = document.getElementById('lightbox');
const lightboxImage = document.getElementById('lightboxImage');
const lightboxInfo = document.getElementById('lightboxInfo');
const lightboxClose = document.getElementById('lightboxClose');

document.querySelectorAll('.memory-card').forEach(card => {
    card.addEventListener('click', () => {
        const img = card.querySelector('img');
        const caption = card.querySelector('.memory-caption');
        const uploader = card.querySelector('.memory-uploader');

        lightboxImage.src = img.src;
        lightboxInfo.innerHTML = '';

        if (caption) {
            lightboxInfo.innerHTML += '<p>' + caption.textContent + '</p>';
        }
        if (uploader) {
            lightboxInfo.innerHTML += '<p style="opacity: 0.7; font-size: 12px;">' + uploader.textContent + '</p>';
        }

        lightbox.classList.add('active');
        document.body.style.overflow = 'hidden';
    });
});

if (lightboxClose) {
    lightboxClose.addEventListener('click', closeLightbox);
}

if (lightbox) {
    lightbox.addEventListener('click', (e) => {
        if (e.target === lightbox) {
            closeLightbox();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && lightbox.classList.contains('active')) {
            closeLightbox();
        }
    });
}

function closeLightbox() {
    lightbox.classList.remove('active');
    document.body.style.overflow = '';
}
</script>

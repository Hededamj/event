<?php
/**
 * Vendor Profile Editor
 * Allows vendors to edit their company profile, description, location,
 * categories, and upload logo/cover images.
 */
$pageTitle = 'Profil';

require_once __DIR__ . '/../includes/vendor-header.php';
require_once __DIR__ . '/../includes/vendor-validation.php';

$db = getDB();
$errors = [];

// ── Fetch full vendor data ──────────────────────────────────────
$stmt = $db->prepare("SELECT * FROM vendors WHERE id = ? LIMIT 1");
$stmt->execute([$vendorId]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    setFlash('error', 'Leverandor ikke fundet.');
    redirect('/subcontractor/dashboard/');
}

// ── Fetch vendor's current category links ───────────────────────
$catLinkStmt = $db->prepare("
    SELECT vcl.category_id, vc.name
    FROM vendor_category_links vcl
    JOIN vendor_categories vc ON vcl.category_id = vc.id
    WHERE vcl.vendor_id = ?
");
$catLinkStmt->execute([$vendorId]);
$vendorCategoryIds = $catLinkStmt->fetchAll(PDO::FETCH_COLUMN, 0);

// ── Fetch all active categories ─────────────────────────────────
$allCatStmt = $db->query("SELECT id, name, icon FROM vendor_categories WHERE is_active = 1 ORDER BY sort_order ASC");
$allCategories = $allCatStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Ensure uploads directory exists ─────────────────────────────
$uploadDir = __DIR__ . '/../../uploads/vendors';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// ── Handle POST ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyVendorCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ugyldig anmodning. Prov igen.';
    } else {
        // Collect form data
        $formData = [
            'company_name'     => trim($_POST['company_name'] ?? ''),
            'contact_name'     => trim($_POST['contact_name'] ?? ''),
            'phone'            => trim($_POST['phone'] ?? ''),
            'website'          => trim($_POST['website'] ?? ''),
            'description'      => trim($_POST['description'] ?? ''),
            'short_description'=> trim($_POST['short_description'] ?? ''),
            'address'          => trim($_POST['address'] ?? ''),
            'city'             => trim($_POST['city'] ?? ''),
            'postal_code'      => trim($_POST['postal_code'] ?? ''),
            'nationwide'       => isset($_POST['nationwide']) ? 1 : 0,
            'service_areas'    => trim($_POST['service_areas'] ?? ''),
        ];
        $selectedCategories = array_filter(array_map('intval', $_POST['categories'] ?? []));

        // 1. Validate profile fields
        $errors = validateVendorProfile($formData);

        // 2. Handle logo upload
        $newLogoFilename = null;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $logoErrors = validateVendorImageUpload($_FILES['logo']);
            if (!empty($logoErrors)) {
                $errors = array_merge($errors, $logoErrors);
            } else {
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                $newLogoFilename = 'vendor_' . $vendorId . '_logo_' . time() . '.' . $ext;
                if (!move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . '/' . $newLogoFilename)) {
                    $errors[] = 'Logo upload fejlede. Prov igen.';
                    $newLogoFilename = null;
                }
            }
        }

        // 3. Handle cover upload
        $newCoverFilename = null;
        if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
            $coverErrors = validateVendorImageUpload($_FILES['cover']);
            if (!empty($coverErrors)) {
                $errors = array_merge($errors, $coverErrors);
            } else {
                $ext = strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION));
                $newCoverFilename = 'vendor_' . $vendorId . '_cover_' . time() . '.' . $ext;
                if (!move_uploaded_file($_FILES['cover']['tmp_name'], $uploadDir . '/' . $newCoverFilename)) {
                    $errors[] = 'Cover-billede upload fejlede. Prov igen.';
                    $newCoverFilename = null;
                }
            }
        }

        // 4. If no errors, save everything
        if (empty($errors)) {
            // Build UPDATE query
            $updateFields = [
                'company_name'      => $formData['company_name'],
                'contact_name'      => $formData['contact_name'],
                'phone'             => $formData['phone'] ?: null,
                'website'           => $formData['website'] ?: null,
                'description'       => $formData['description'] ?: null,
                'short_description' => $formData['short_description'] ?: null,
                'address'           => $formData['address'] ?: null,
                'city'              => $formData['city'] ?: null,
                'postal_code'       => $formData['postal_code'] ?: null,
                'nationwide'        => $formData['nationwide'],
                'service_areas'     => $formData['service_areas'] ?: null,
            ];

            // Handle logo replacement
            if ($newLogoFilename) {
                // Delete old logo file
                if (!empty($vendor['logo_filename'])) {
                    $oldLogoPath = $uploadDir . '/' . $vendor['logo_filename'];
                    if (file_exists($oldLogoPath)) {
                        unlink($oldLogoPath);
                    }
                }
                $updateFields['logo_filename'] = $newLogoFilename;
            }

            // Handle cover replacement
            if ($newCoverFilename) {
                // Delete old cover file
                if (!empty($vendor['cover_filename'])) {
                    $oldCoverPath = $uploadDir . '/' . $vendor['cover_filename'];
                    if (file_exists($oldCoverPath)) {
                        unlink($oldCoverPath);
                    }
                }
                $updateFields['cover_filename'] = $newCoverFilename;
            }

            // Build SET clause
            $setClauses = [];
            $params = [];
            foreach ($updateFields as $col => $val) {
                $setClauses[] = "$col = ?";
                $params[] = $val;
            }
            $params[] = $vendorId;

            $sql = "UPDATE vendors SET " . implode(', ', $setClauses) . " WHERE id = ?";
            $updateStmt = $db->prepare($sql);
            $updateStmt->execute($params);

            // Re-sync categories: delete all, re-insert selected
            $db->prepare("DELETE FROM vendor_category_links WHERE vendor_id = ?")->execute([$vendorId]);
            if (!empty($selectedCategories)) {
                $linkStmt = $db->prepare("INSERT INTO vendor_category_links (vendor_id, category_id) VALUES (?, ?)");
                foreach ($selectedCategories as $catId) {
                    try {
                        $linkStmt->execute([$vendorId, $catId]);
                    } catch (Exception $e) {
                        error_log("Failed to insert vendor category link: " . $e->getMessage());
                    }
                }
            }

            // Update session company name if changed
            if ($formData['company_name'] !== ($vendor['company_name'] ?? '')) {
                $_SESSION['vendor_company_name'] = $formData['company_name'];
            }

            setFlash('success', 'Din profil er opdateret');
            redirect('/subcontractor/dashboard/profile.php');
        }

        // If errors, update the vendor array with submitted data so the form shows it
        $vendor = array_merge($vendor, $formData);
        $vendorCategoryIds = $selectedCategories;
    }
}
?>

<style>
    .profile-section {
        margin-bottom: 32px;
    }
    .profile-section:last-child {
        margin-bottom: 0;
    }
    .profile-section-heading {
        font-family: var(--font-display);
        font-size: 22px;
        font-weight: 500;
        color: var(--text);
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 2px solid var(--border);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .profile-section-heading svg {
        width: 22px;
        height: 22px;
        color: var(--accent-dark);
        flex-shrink: 0;
    }
    .error-list {
        background: #FDF2F2;
        border: 1px solid #F5D5D5;
        color: var(--error);
        padding: 14px 18px;
        border-radius: 12px;
        font-size: 14px;
        margin-bottom: 24px;
    }
    .error-list ul {
        margin: 0;
        padding-left: 18px;
    }
    .error-list li {
        margin-bottom: 4px;
    }
    .error-list li:last-child {
        margin-bottom: 0;
    }
    .char-counter {
        text-align: right;
        font-size: 12px;
        color: var(--text-secondary);
        margin-top: 6px;
    }
    .char-counter.over-limit {
        color: var(--error);
        font-weight: 600;
    }
    .category-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
    @media (max-width: 500px) {
        .category-grid {
            grid-template-columns: 1fr;
        }
    }
    .category-option {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 14px;
        border: 2px solid var(--border);
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.2s var(--ease-out);
        font-size: 14px;
    }
    .category-option:hover {
        border-color: var(--accent-light);
        background: var(--surface);
    }
    .category-option input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: var(--accent-dark);
        flex-shrink: 0;
        cursor: pointer;
    }
    .category-option:has(input:checked) {
        border-color: var(--accent);
        background: rgba(168, 181, 160, 0.08);
    }
    .category-option:has(input:checked) .category-label-text {
        font-weight: 600;
    }
    .category-icon {
        font-size: 18px;
        flex-shrink: 0;
    }
    .image-upload-area {
        display: flex;
        align-items: flex-start;
        gap: 20px;
        flex-wrap: wrap;
    }
    .image-preview {
        width: 120px;
        height: 120px;
        border-radius: 12px;
        overflow: hidden;
        border: 2px solid var(--border);
        background: var(--surface);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .image-preview.cover {
        width: 200px;
        height: 120px;
        border-radius: 12px;
    }
    .image-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .image-preview .no-image {
        color: var(--text-secondary);
        font-size: 13px;
        text-align: center;
        padding: 8px;
    }
    .image-preview .no-image svg {
        width: 32px;
        height: 32px;
        margin-bottom: 4px;
        opacity: 0.4;
    }
    .image-upload-info {
        flex: 1;
        min-width: 200px;
    }
    .image-upload-info .form-hint {
        margin-top: 8px;
    }
    .form-input-file {
        width: 100%;
        padding: 10px 0;
        font-family: var(--font-body);
        font-size: 14px;
        color: var(--text);
    }
    .form-input-file::file-selector-button {
        padding: 8px 16px;
        font-family: var(--font-body);
        font-size: 13px;
        font-weight: 600;
        border: 2px solid var(--border);
        border-radius: 8px;
        background: var(--white);
        color: var(--text);
        cursor: pointer;
        margin-right: 12px;
        transition: all 0.2s var(--ease-out);
    }
    .form-input-file::file-selector-button:hover {
        border-color: var(--accent);
        background: var(--surface);
    }
</style>

<!-- Page Header -->
<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Rediger profil</h1>
        <p class="page-subtitle">Administrer din virksomhedsprofil og synlighed</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="error-list">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" action="" enctype="multipart/form-data">
            <?= vendorCsrfField() ?>

            <!-- Section: Virksomhedsinfo -->
            <div class="profile-section">
                <h3 class="profile-section-heading">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                    Virksomhedsinfo
                </h3>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="company_name">Firmanavn <span class="required">*</span></label>
                        <input
                            type="text"
                            id="company_name"
                            name="company_name"
                            class="form-input"
                            placeholder="Dit firmanavn"
                            value="<?= htmlspecialchars($vendor['company_name'] ?? '') ?>"
                            required
                        >
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="contact_name">Kontaktperson <span class="required">*</span></label>
                        <input
                            type="text"
                            id="contact_name"
                            name="contact_name"
                            class="form-input"
                            placeholder="Fulde navn"
                            value="<?= htmlspecialchars($vendor['contact_name'] ?? '') ?>"
                            required
                        >
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="phone">Telefon</label>
                        <input
                            type="tel"
                            id="phone"
                            name="phone"
                            class="form-input"
                            placeholder="+45 12 34 56 78"
                            value="<?= htmlspecialchars($vendor['phone'] ?? '') ?>"
                        >
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="website">Webside</label>
                        <input
                            type="url"
                            id="website"
                            name="website"
                            class="form-input"
                            placeholder="https://www.dinvirksomhed.dk"
                            value="<?= htmlspecialchars($vendor['website'] ?? '') ?>"
                        >
                    </div>
                </div>
            </div>

            <!-- Section: Beskrivelse -->
            <div class="profile-section">
                <h3 class="profile-section-heading">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path>
                    </svg>
                    Beskrivelse
                </h3>

                <div class="form-group">
                    <label class="form-label" for="short_description">Kort beskrivelse</label>
                    <textarea
                        id="short_description"
                        name="short_description"
                        class="form-textarea"
                        placeholder="En kort beskrivelse der vises i sogeresultater..."
                        maxlength="500"
                        rows="3"
                    ><?= htmlspecialchars($vendor['short_description'] ?? '') ?></textarea>
                    <div class="char-counter" id="shortDescCounter">
                        <span id="shortDescCount"><?= mb_strlen($vendor['short_description'] ?? '') ?></span> / 500 tegn
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Fuld beskrivelse</label>
                    <textarea
                        id="description"
                        name="description"
                        class="form-textarea"
                        placeholder="Beskriv din virksomhed, jeres ydelser, erfaring og hvad der gor jer unikke..."
                        maxlength="5000"
                        rows="8"
                    ><?= htmlspecialchars($vendor['description'] ?? '') ?></textarea>
                    <span class="form-hint">Maks 5000 tegn. Denne tekst vises pa din fulde profil.</span>
                </div>
            </div>

            <!-- Section: Beliggenhed -->
            <div class="profile-section">
                <h3 class="profile-section-heading">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    Beliggenhed
                </h3>

                <div class="form-group">
                    <label class="form-label" for="address">Adresse</label>
                    <input
                        type="text"
                        id="address"
                        name="address"
                        class="form-input"
                        placeholder="Vejnavn og nummer"
                        value="<?= htmlspecialchars($vendor['address'] ?? '') ?>"
                    >
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="city">By</label>
                        <input
                            type="text"
                            id="city"
                            name="city"
                            class="form-input"
                            placeholder="By"
                            value="<?= htmlspecialchars($vendor['city'] ?? '') ?>"
                        >
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="postal_code">Postnummer</label>
                        <input
                            type="text"
                            id="postal_code"
                            name="postal_code"
                            class="form-input"
                            placeholder="Postnummer"
                            value="<?= htmlspecialchars($vendor['postal_code'] ?? '') ?>"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-checkbox-group">
                        <input
                            type="checkbox"
                            name="nationwide"
                            value="1"
                            class="form-checkbox"
                            <?= !empty($vendor['nationwide']) ? 'checked' : '' ?>
                        >
                        <span class="form-checkbox-label">Vi daekker hele Danmark</span>
                    </label>
                </div>

                <div class="form-group">
                    <label class="form-label" for="service_areas">Serviceomrader</label>
                    <textarea
                        id="service_areas"
                        name="service_areas"
                        class="form-textarea"
                        placeholder="Kobenhavn, Aarhus, Odense, Aalborg..."
                        rows="3"
                    ><?= htmlspecialchars($vendor['service_areas'] ?? '') ?></textarea>
                    <span class="form-hint">Angiv byer/omrader I daekker, adskilt med komma</span>
                </div>
            </div>

            <!-- Section: Kategorier -->
            <div class="profile-section">
                <h3 class="profile-section-heading">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                    </svg>
                    Kategorier
                </h3>

                <div class="category-grid">
                    <?php foreach ($allCategories as $cat): ?>
                        <label class="category-option">
                            <input
                                type="checkbox"
                                name="categories[]"
                                value="<?= (int)$cat['id'] ?>"
                                <?= in_array((int)$cat['id'], $vendorCategoryIds) ? 'checked' : '' ?>
                            >
                            <span class="category-icon"><?= htmlspecialchars($cat['icon'] ?? '') ?></span>
                            <span class="category-label-text"><?= htmlspecialchars($cat['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Section: Billeder -->
            <div class="profile-section">
                <h3 class="profile-section-heading">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    Billeder
                </h3>

                <!-- Logo upload -->
                <div class="form-group">
                    <label class="form-label">Logo</label>
                    <div class="image-upload-area">
                        <div class="image-preview">
                            <?php if (!empty($vendor['logo_filename'])): ?>
                                <img src="/uploads/vendors/<?= htmlspecialchars($vendor['logo_filename']) ?>" alt="Logo">
                            <?php else: ?>
                                <div class="no-image">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    Intet logo
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="image-upload-info">
                            <input
                                type="file"
                                id="logo"
                                name="logo"
                                class="form-input-file"
                                accept="image/jpeg,image/png,image/gif,image/webp"
                            >
                            <span class="form-hint">JPEG, PNG, GIF eller WebP. Maks 5MB.</span>
                        </div>
                    </div>
                </div>

                <!-- Cover image upload -->
                <div class="form-group">
                    <label class="form-label">Cover-billede</label>
                    <div class="image-upload-area">
                        <div class="image-preview cover">
                            <?php if (!empty($vendor['cover_filename'])): ?>
                                <img src="/uploads/vendors/<?= htmlspecialchars($vendor['cover_filename']) ?>" alt="Cover">
                            <?php else: ?>
                                <div class="no-image">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    Intet cover
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="image-upload-info">
                            <input
                                type="file"
                                id="cover"
                                name="cover"
                                class="form-input-file"
                                accept="image/jpeg,image/png,image/gif,image/webp"
                            >
                            <span class="form-hint">JPEG, PNG, GIF eller WebP. Maks 5MB. Anbefalet: 1200x400px.</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-lg">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Gem profil
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Character counter for short_description
(function() {
    const textarea = document.getElementById('short_description');
    const counter = document.getElementById('shortDescCount');
    const counterWrap = document.getElementById('shortDescCounter');
    if (!textarea || !counter) return;

    function updateCount() {
        const len = textarea.value.length;
        counter.textContent = len;
        if (len > 500) {
            counterWrap.classList.add('over-limit');
        } else {
            counterWrap.classList.remove('over-limit');
        }
    }

    textarea.addEventListener('input', updateCount);
    updateCount();
})();
</script>

<?php require_once __DIR__ . '/../includes/vendor-footer.php'; ?>

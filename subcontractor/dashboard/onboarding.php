<?php
/**
 * Vendor Onboarding Wizard
 * Post-login wizard for new vendors to complete their profile setup.
 * 4 steps: Categories, Description, Logo, Done.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../includes/vendor-auth.php';
require_once __DIR__ . '/../includes/vendor-validation.php';

// Require login
requireVendorLogin();

$vendorId = getCurrentVendorId();
$currentVendor = getCurrentVendor();
$db = getDB();

// If already onboarded, go to dashboard
if (isVendorOnboarded()) {
    redirect('/subcontractor/dashboard/');
}

$errors = [];
$step = isset($_GET['step']) ? (int)$_GET['step'] : 0;

// Auto-detect step if not explicitly set
if ($step === 0) {
    $step = getVendorOnboardingStep($vendorId);
}

// Clamp step range
if ($step < 1) $step = 1;
if ($step > 4) $step = 4;

// Handle POST submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyVendorCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ugyldig anmodning. Prøv igen.';
    } else {
        $postStep = (int)($_POST['step'] ?? 0);

        if ($postStep === 1) {
            // Categories
            $selectedCategories = $_POST['categories'] ?? [];
            $errors = validateOnboardingCategories($selectedCategories);

            if (empty($errors)) {
                $filtered = array_filter(array_map('intval', $selectedCategories));
                // Delete existing and re-insert
                $db->prepare("DELETE FROM vendor_category_links WHERE vendor_id = ?")->execute([$vendorId]);
                $linkStmt = $db->prepare("INSERT INTO vendor_category_links (vendor_id, category_id) VALUES (?, ?)");
                foreach ($filtered as $catId) {
                    try {
                        $linkStmt->execute([$vendorId, $catId]);
                    } catch (Exception $e) {
                        error_log("Failed to insert vendor category link: " . $e->getMessage());
                    }
                }
                redirect('/subcontractor/dashboard/onboarding.php?step=2');
            }
        } elseif ($postStep === 2) {
            // Description
            $shortDescription = trim($_POST['short_description'] ?? '');
            $fullDescription = trim($_POST['description'] ?? '');
            $errors = validateOnboardingDescription($shortDescription);

            // Optional full description max length
            if (!empty($fullDescription) && mb_strlen($fullDescription) > 5000) {
                $errors[] = 'Fuld beskrivelse må højst være 5000 tegn.';
            }

            if (empty($errors)) {
                $stmt = $db->prepare("UPDATE vendors SET short_description = ?, description = ? WHERE id = ?");
                $stmt->execute([$shortDescription, $fullDescription ?: null, $vendorId]);
                redirect('/subcontractor/dashboard/onboarding.php?step=3');
            } else {
                $step = 2;
            }
        } elseif ($postStep === 3) {
            // Logo upload (optional — can skip)
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $errors = validateVendorImageUpload($_FILES['logo']);
                if (empty($errors)) {
                    $uploadDir = __DIR__ . '/../../uploads/vendors';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                    $filename = 'vendor_' . $vendorId . '_logo_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . '/' . $filename)) {
                        $stmt = $db->prepare("UPDATE vendors SET logo_filename = ? WHERE id = ?");
                        $stmt->execute([$filename, $vendorId]);
                    } else {
                        $errors[] = 'Logo upload fejlede. Prøv igen.';
                    }
                }
                if (!empty($errors)) {
                    $step = 3;
                } else {
                    redirect('/subcontractor/dashboard/onboarding.php?step=4');
                }
            } else {
                // Skip logo — go to step 4
                redirect('/subcontractor/dashboard/onboarding.php?step=4');
            }
        } elseif ($postStep === 4) {
            // Complete onboarding
            markOnboardingComplete($vendorId);
            setFlash('success', 'Velkommen! Din profil er nu klar.');
            redirect('/subcontractor/dashboard/');
        }
    }
}

// Load data for each step
$allCategories = [];
$vendorCategoryIds = [];
$vendor = [];

if ($step === 1) {
    try {
        $catStmt = $db->query("SELECT id, name, icon FROM vendor_categories WHERE is_active = 1 ORDER BY sort_order ASC");
        $allCategories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

        $linkStmt = $db->prepare("SELECT category_id FROM vendor_category_links WHERE vendor_id = ?");
        $linkStmt->execute([$vendorId]);
        $vendorCategoryIds = $linkStmt->fetchAll(PDO::FETCH_COLUMN, 0);
    } catch (Exception $e) {
        error_log("Failed to load categories: " . $e->getMessage());
    }
} elseif ($step === 2 || $step === 3 || $step === 4) {
    $stmt = $db->prepare("SELECT * FROM vendors WHERE id = ? LIMIT 1");
    $stmt->execute([$vendorId]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

if ($step === 4) {
    // Load category names for summary
    try {
        $catStmt = $db->prepare("
            SELECT vc.name FROM vendor_category_links vcl
            JOIN vendor_categories vc ON vcl.category_id = vc.id
            WHERE vcl.vendor_id = ?
            ORDER BY vc.sort_order ASC
        ");
        $catStmt->execute([$vendorId]);
        $vendorCategoryNames = $catStmt->fetchAll(PDO::FETCH_COLUMN, 0);
    } catch (Exception $e) {
        $vendorCategoryNames = [];
    }
}

$stepLabels = [1 => 'Kategorier', 2 => 'Beskrivelse', 3 => 'Logo', 4 => 'Færdig'];
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Opsætning - Leverandørportal - PartyParart</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --surface: #FAF9F7;
            --surface-card: #FFFFFF;
            --border: #F5F3EF;
            --accent: #A8B5A0;
            --accent-light: #C8D4C2;
            --accent-dark: #7A8B72;
            --text: #2C2C2C;
            --text-secondary: #4A4A4A;
            --white: #FFFFFF;
            --error: #C75D5D;
            --success: #5DA87A;

            --font-display: 'Cormorant Garamond', Georgia, serif;
            --font-body: 'DM Sans', -apple-system, sans-serif;
            --ease-out: cubic-bezier(0.16, 1, 0.3, 1);
        }

        body {
            font-family: var(--font-body);
            background: var(--surface);
            min-height: 100vh;
            color: var(--text);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.8' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E");
            opacity: 0.025;
            pointer-events: none;
            z-index: 9999;
        }

        .onboarding-wrapper {
            max-width: 640px;
            margin: 0 auto;
            padding: clamp(24px, 6vw, 64px) clamp(16px, 4vw, 32px);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .onboarding-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .onboarding-logo {
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 500;
            color: var(--text);
            text-decoration: none;
            display: inline-block;
            margin-bottom: 24px;
        }

        .onboarding-logo span {
            color: var(--accent-dark);
        }

        .onboarding-title {
            font-family: var(--font-display);
            font-size: clamp(28px, 4vw, 36px);
            font-weight: 400;
            color: var(--text);
            margin-bottom: 8px;
        }

        .onboarding-subtitle {
            font-size: 15px;
            color: var(--text-secondary);
        }

        /* Step indicator */
        .step-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 32px;
        }

        .step-dot {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .step-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 600;
            border: 2px solid var(--border);
            color: var(--text-secondary);
            background: var(--surface-card);
            transition: all 0.3s var(--ease-out);
        }

        .step-circle.active {
            border-color: var(--accent);
            background: var(--accent);
            color: var(--white);
        }

        .step-circle.completed {
            border-color: var(--accent-dark);
            background: var(--accent-dark);
            color: var(--white);
        }

        .step-line {
            width: 32px;
            height: 2px;
            background: var(--border);
            transition: background 0.3s var(--ease-out);
        }

        .step-line.completed {
            background: var(--accent-dark);
        }

        .step-label {
            display: none;
        }

        @media (min-width: 500px) {
            .step-label {
                display: block;
                font-size: 12px;
                color: var(--text-secondary);
                text-align: center;
                margin-top: 6px;
            }
            .step-dot {
                flex-direction: column;
                gap: 0;
            }
            .step-label.active {
                color: var(--text);
                font-weight: 600;
            }
        }

        /* Card */
        .onboarding-card {
            background: var(--surface-card);
            border-radius: 24px;
            padding: clamp(24px, 4vw, 40px);
            box-shadow:
                0 1px 2px rgba(0,0,0,0.04),
                0 4px 16px rgba(0,0,0,0.04);
            animation: fadeIn 0.6s var(--ease-out);
            flex: 1;
        }

        .step-heading {
            font-family: var(--font-display);
            font-size: 24px;
            font-weight: 500;
            color: var(--text);
            margin-bottom: 8px;
        }

        .step-description {
            font-size: 15px;
            color: var(--text-secondary);
            margin-bottom: 24px;
        }

        .error-list {
            background: #FDF2F2;
            border: 1px solid #F5D5D5;
            color: var(--error);
            padding: 14px 18px;
            border-radius: 14px;
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

        /* Form elements */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 8px;
        }

        .form-label .optional {
            font-weight: 400;
            color: var(--text-secondary);
            font-size: 13px;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px;
            font-family: var(--font-body);
            font-size: 15px;
            border: 2px solid var(--border);
            border-radius: 14px;
            background: var(--surface-card);
            color: var(--text);
            transition: all 0.3s var(--ease-out);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(168, 181, 160, 0.15);
        }

        .form-input::placeholder {
            color: #A8A39B;
        }

        textarea.form-input {
            resize: vertical;
            min-height: 100px;
        }

        .form-hint {
            display: block;
            font-size: 13px;
            color: var(--text-secondary);
            margin-top: 6px;
        }

        .char-count {
            text-align: right;
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 6px;
        }

        /* Category grid */
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
            border-radius: 12px;
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
            accent-color: var(--accent);
            flex-shrink: 0;
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

        /* Logo upload */
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

        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .no-image {
            text-align: center;
            color: var(--text-secondary);
            font-size: 12px;
            padding: 12px;
        }

        .no-image svg {
            display: block;
            margin: 0 auto 6px;
            width: 32px;
            height: 32px;
            opacity: 0.4;
        }

        .image-upload-info {
            flex: 1;
            min-width: 180px;
        }

        .form-input-file {
            width: 100%;
            padding: 12px;
            font-family: var(--font-body);
            font-size: 14px;
            border: 2px dashed var(--border);
            border-radius: 14px;
            background: var(--surface);
            cursor: pointer;
            transition: border-color 0.2s;
        }

        .form-input-file:hover {
            border-color: var(--accent-light);
        }

        /* Summary */
        .summary-section {
            margin-bottom: 20px;
        }

        .summary-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .summary-value {
            font-size: 15px;
            color: var(--text);
            line-height: 1.5;
        }

        .summary-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .summary-tag {
            display: inline-block;
            padding: 6px 14px;
            background: rgba(168, 181, 160, 0.12);
            border-radius: 20px;
            font-size: 13px;
            color: var(--accent-dark);
            font-weight: 500;
        }

        .success-icon {
            width: 64px;
            height: 64px;
            background: rgba(93, 168, 122, 0.12);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .success-icon svg {
            width: 32px;
            height: 32px;
            color: var(--success);
        }

        /* Buttons */
        .btn-row {
            display: flex;
            gap: 12px;
            margin-top: 28px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 16px 32px;
            font-family: var(--font-body);
            font-size: 15px;
            font-weight: 600;
            border: none;
            border-radius: 14px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s var(--ease-out);
        }

        .btn-primary {
            background: var(--text);
            color: var(--surface-card);
            flex: 1;
        }

        .btn-primary:hover {
            background: var(--text-secondary);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(44,44,44,0.2);
        }

        .btn-secondary {
            background: var(--surface);
            color: var(--text);
            border: 2px solid var(--border);
        }

        .btn-secondary:hover {
            border-color: var(--accent);
            background: var(--surface-card);
        }

        .btn-skip {
            background: transparent;
            color: var(--text-secondary);
            font-size: 14px;
            padding: 16px 20px;
        }

        .btn-skip:hover {
            color: var(--text);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="onboarding-wrapper">
        <div class="onboarding-header">
            <a href="/" class="onboarding-logo">Party<span>Parart</span></a>
            <h1 class="onboarding-title">Opsæt din profil</h1>
            <p class="onboarding-subtitle">Udfyld et par oplysninger, så er du klar</p>
        </div>

        <!-- Step indicator -->
        <div class="step-indicator">
            <?php for ($i = 1; $i <= 4; $i++): ?>
                <?php if ($i > 1): ?>
                    <div class="step-line <?= $i <= $step ? 'completed' : '' ?>"></div>
                <?php endif; ?>
                <div class="step-dot">
                    <div class="step-circle <?= $i === $step ? 'active' : ($i < $step ? 'completed' : '') ?>">
                        <?php if ($i < $step): ?>
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                        <?php else: ?>
                            <?= $i ?>
                        <?php endif; ?>
                    </div>
                    <span class="step-label <?= $i === $step ? 'active' : '' ?>"><?= $stepLabels[$i] ?></span>
                </div>
            <?php endfor; ?>
        </div>

        <div class="onboarding-card">
            <?php if (!empty($errors)): ?>
                <div class="error-list">
                    <ul>
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
                <!-- Step 1: Kategorier -->
                <h2 class="step-heading">Vælg dine kategorier</h2>
                <p class="step-description">Vælg de kategorier, der bedst beskriver dine ydelser. Du kan altid ændre det senere.</p>

                <form method="POST" action="">
                    <?= vendorCsrfField() ?>
                    <input type="hidden" name="step" value="1">

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

                    <div class="btn-row">
                        <button type="submit" class="btn btn-primary">
                            Næste
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </button>
                    </div>
                </form>

            <?php elseif ($step === 2): ?>
                <!-- Step 2: Beskrivelse -->
                <h2 class="step-heading">Beskriv din virksomhed</h2>
                <p class="step-description">En god beskrivelse hjælper arrangører med at finde og vælge dig.</p>

                <form method="POST" action="">
                    <?= vendorCsrfField() ?>
                    <input type="hidden" name="step" value="2">

                    <div class="form-group">
                        <label class="form-label" for="short_description">Kort beskrivelse</label>
                        <textarea
                            id="short_description"
                            name="short_description"
                            class="form-input"
                            placeholder="Beskriv kort hvad din virksomhed tilbyder..."
                            maxlength="500"
                            required
                        ><?= htmlspecialchars($vendor['short_description'] ?? '') ?></textarea>
                        <span class="form-hint">Mindst 50 tegn, maks 500 tegn.</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="description">Fuld beskrivelse <span class="optional">(valgfrit)</span></label>
                        <textarea
                            id="description"
                            name="description"
                            class="form-input"
                            placeholder="Uddyb dine ydelser, erfaring, hvad der gør jer unikke..."
                            maxlength="5000"
                            style="min-height: 150px;"
                        ><?= htmlspecialchars($vendor['description'] ?? '') ?></textarea>
                        <span class="form-hint">Maks 5000 tegn.</span>
                    </div>

                    <div class="btn-row">
                        <a href="/subcontractor/dashboard/onboarding.php?step=1" class="btn btn-secondary">Tilbage</a>
                        <button type="submit" class="btn btn-primary">
                            Næste
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </button>
                    </div>
                </form>

            <?php elseif ($step === 3): ?>
                <!-- Step 3: Logo -->
                <h2 class="step-heading">Upload dit logo</h2>
                <p class="step-description">Et logo gør din profil mere professionel. Du kan også tilføje det senere.</p>

                <form method="POST" action="" enctype="multipart/form-data">
                    <?= vendorCsrfField() ?>
                    <input type="hidden" name="step" value="3">

                    <div class="form-group">
                        <div class="image-upload-area">
                            <div class="image-preview">
                                <?php if (!empty($vendor['logo_filename'])): ?>
                                    <img src="/uploads/vendors/<?= htmlspecialchars($vendor['logo_filename']) ?>" alt="Logo">
                                <?php else: ?>
                                    <div class="no-image">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
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

                    <div class="btn-row">
                        <a href="/subcontractor/dashboard/onboarding.php?step=2" class="btn btn-secondary">Tilbage</a>
                        <button type="submit" name="skip" class="btn btn-skip">Spring over</button>
                        <button type="submit" class="btn btn-primary">
                            Upload og fortsæt
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </button>
                    </div>
                </form>

            <?php elseif ($step === 4): ?>
                <!-- Step 4: Done -->
                <div style="text-align: center;">
                    <div class="success-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <h2 class="step-heading" style="text-align: center;">Din profil er klar!</h2>
                    <p class="step-description" style="text-align: center;">Her er et overblik over dine oplysninger. Du kan altid redigere dem fra din profil.</p>
                </div>

                <div class="summary-section">
                    <div class="summary-label">Kategorier</div>
                    <div class="summary-tags">
                        <?php foreach ($vendorCategoryNames ?? [] as $name): ?>
                            <span class="summary-tag"><?= htmlspecialchars($name) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="summary-section">
                    <div class="summary-label">Kort beskrivelse</div>
                    <div class="summary-value"><?= nl2br(htmlspecialchars($vendor['short_description'] ?? '')) ?></div>
                </div>

                <?php if (!empty($vendor['description'])): ?>
                    <div class="summary-section">
                        <div class="summary-label">Fuld beskrivelse</div>
                        <div class="summary-value"><?= nl2br(htmlspecialchars($vendor['description'])) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($vendor['logo_filename'])): ?>
                    <div class="summary-section">
                        <div class="summary-label">Logo</div>
                        <div class="image-preview">
                            <img src="/uploads/vendors/<?= htmlspecialchars($vendor['logo_filename']) ?>" alt="Logo">
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?= vendorCsrfField() ?>
                    <input type="hidden" name="step" value="4">

                    <div class="btn-row">
                        <a href="/subcontractor/dashboard/onboarding.php?step=3" class="btn btn-secondary">Tilbage</a>
                        <button type="submit" class="btn btn-primary">
                            Gå til dashboard
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

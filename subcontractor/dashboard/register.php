<?php
/**
 * Vendor Registration Page - Nordic Design
 * Multi-section registration form for new vendor/subcontractor accounts.
 * Follows the same auth page layout as app/auth/login.php (visual left panel + form right panel).
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../includes/vendor-auth.php';
require_once __DIR__ . '/../includes/vendor-validation.php';

// Redirect if already logged in as vendor
if (isVendorLoggedIn()) {
    redirect('/subcontractor/dashboard/');
}

$errors = [];
$formData = [
    'company_name' => '',
    'contact_name' => '',
    'email' => '',
    'phone' => '',
    'password' => '',
    'password_confirm' => '',
    'categories' => [],
    'short_description' => '',
];

// Fetch active vendor categories
$db = getDB();
$catStmt = $db->query("SELECT id, name, icon FROM vendor_categories WHERE is_active = 1 ORDER BY sort_order ASC");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyVendorCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ugyldig anmodning. Prøv igen.';
    } else {
        // Preserve input
        $formData['company_name'] = trim($_POST['company_name'] ?? '');
        $formData['contact_name'] = trim($_POST['contact_name'] ?? '');
        $formData['email'] = trim($_POST['email'] ?? '');
        $formData['phone'] = trim($_POST['phone'] ?? '');
        $formData['password'] = $_POST['password'] ?? '';
        $formData['password_confirm'] = $_POST['password_confirm'] ?? '';
        $formData['categories'] = $_POST['categories'] ?? [];
        $formData['short_description'] = trim($_POST['short_description'] ?? '');

        // 1. Validate with validateVendorRegistration()
        $errors = validateVendorRegistration([
            'email' => $formData['email'],
            'password' => $formData['password'],
            'company_name' => $formData['company_name'],
            'contact_name' => $formData['contact_name'],
        ]);

        // 2. Validate passwords match
        if ($formData['password'] !== $formData['password_confirm']) {
            $errors[] = 'Adgangskoderne er ikke ens.';
        }

        // 3. Validate at least 1 category selected
        $selectedCategories = array_filter(array_map('intval', $formData['categories']));
        if (empty($selectedCategories)) {
            $errors[] = 'Vælg mindst én kategori.';
        }

        // 4. Validate short_description required, max 500
        if (empty($formData['short_description'])) {
            $errors[] = 'Kort beskrivelse er påkrævet.';
        } elseif (mb_strlen($formData['short_description']) > 500) {
            $errors[] = 'Kort beskrivelse må højst være 500 tegn.';
        }

        // If no errors, proceed with registration
        if (empty($errors)) {
            // 4. Call registerVendor()
            $result = registerVendor(
                $formData['email'],
                $formData['password'],
                $formData['company_name'],
                $formData['contact_name'],
                !empty($formData['phone']) ? $formData['phone'] : null
            );

            if ($result['success']) {
                $vendorId = $result['vendor_id'];

                // 5. Insert category links
                $linkStmt = $db->prepare("INSERT INTO vendor_category_links (vendor_id, category_id) VALUES (?, ?)");
                foreach ($selectedCategories as $catId) {
                    try {
                        $linkStmt->execute([$vendorId, $catId]);
                    } catch (Exception $e) {
                        error_log("Failed to insert vendor category link: " . $e->getMessage());
                    }
                }

                // 6. Update short_description
                try {
                    $descStmt = $db->prepare("UPDATE vendors SET short_description = ? WHERE id = ?");
                    $descStmt->execute([$formData['short_description'], $vendorId]);
                } catch (Exception $e) {
                    error_log("Failed to update vendor short_description: " . $e->getMessage());
                }

                // 7. Redirect to login with flash message
                setFlash('success', 'Din registrering er modtaget! Vi gennemgår din ansøgning hurtigst muligt.');
                redirect('/subcontractor/dashboard/login.php');
            } else {
                // Registration failed
                $errors[] = $result['error'] ?? 'Der opstod en fejl. Prøv igen.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bliv leverandør - PartyParart</title>
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
            --border: #F5F3EF;
            --accent: #A8B5A0;
            --accent-light: #C8D4C2;
            --accent-dark: #7A8B72;
            --text: #2C2C2C;
            --text-secondary: #4A4A4A;
            --warning: #C9A962;
            --white: #FFFFFF;
            --error: #C75D5D;

            --font-display: 'Cormorant Garamond', Georgia, serif;
            --font-body: 'DM Sans', -apple-system, sans-serif;
            --ease-out: cubic-bezier(0.16, 1, 0.3, 1);
        }

        body {
            font-family: var(--font-body);
            background: var(--surface);
            min-height: 100vh;
            display: flex;
            color: var(--text);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        /* Subtle grain texture */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.8' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E");
            opacity: 0.025;
            pointer-events: none;
            z-index: 9999;
        }

        .register-layout {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            width: 100%;
            min-height: 100vh;
        }

        @media (max-width: 900px) {
            .register-layout {
                grid-template-columns: 1fr;
            }
            .register-visual {
                display: none;
            }
        }

        /* Left side - Visual */
        .register-visual {
            background: linear-gradient(160deg, var(--accent) 0%, var(--accent-dark) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px;
            position: relative;
            overflow: hidden;
            position: sticky;
            top: 0;
            height: 100vh;
        }

        .register-visual::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 20% 80%, rgba(255,255,255,0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255,255,255,0.08) 0%, transparent 40%);
        }

        .visual-content {
            text-align: center;
            color: var(--white);
            position: relative;
            z-index: 1;
        }

        .visual-icon {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.15);
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 32px;
        }

        .visual-icon svg {
            width: 40px;
            height: 40px;
        }

        .visual-title {
            font-family: var(--font-display);
            font-size: 32px;
            font-weight: 400;
            margin-bottom: 16px;
            line-height: 1.2;
        }

        .visual-text {
            font-size: 16px;
            opacity: 0.9;
            max-width: 320px;
            line-height: 1.6;
        }

        .visual-features {
            margin-top: 40px;
            text-align: left;
            max-width: 280px;
            margin-left: auto;
            margin-right: auto;
        }

        .visual-feature {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            font-size: 15px;
            opacity: 0.9;
        }

        .visual-feature svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
            opacity: 0.8;
        }

        /* Right side - Form */
        .register-form-section {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: clamp(32px, 6vw, 64px);
            background: var(--surface);
            min-height: 100vh;
        }

        .register-header {
            margin-bottom: 32px;
        }

        .register-logo {
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 500;
            color: var(--text);
            text-decoration: none;
            display: inline-block;
            margin-bottom: 24px;
        }

        .register-logo span {
            color: var(--accent-dark);
        }

        .register-title {
            font-family: var(--font-display);
            font-size: clamp(28px, 4vw, 36px);
            font-weight: 400;
            color: var(--text);
            margin-bottom: 8px;
        }

        .register-subtitle {
            font-size: 15px;
            color: var(--text-secondary);
        }

        .register-card {
            background: var(--white);
            border-radius: 24px;
            padding: 40px;
            box-shadow:
                0 1px 2px rgba(0,0,0,0.04),
                0 4px 16px rgba(0,0,0,0.04);
            max-width: 520px;
            animation: fadeIn 0.6s var(--ease-out);
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

        .section-heading {
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

        .section-heading .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            background: var(--accent);
            color: var(--white);
            border-radius: 50%;
            font-family: var(--font-body);
            font-size: 13px;
            font-weight: 600;
            flex-shrink: 0;
        }

        .form-section {
            margin-bottom: 32px;
        }

        .form-section:last-of-type {
            margin-bottom: 28px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        @media (max-width: 500px) {
            .form-row {
                grid-template-columns: 1fr;
            }
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
            background: var(--white);
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

        .char-count {
            text-align: right;
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 6px;
        }

        .category-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        @media (max-width: 400px) {
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

        .category-option input[type="checkbox"]:checked + .category-label-text {
            font-weight: 600;
        }

        .category-option:has(input:checked) {
            border-color: var(--accent);
            background: rgba(168, 181, 160, 0.08);
        }

        .category-icon {
            font-size: 18px;
            flex-shrink: 0;
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
            width: 100%;
        }

        .btn-primary {
            background: var(--text);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--text-secondary);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(44,44,44,0.2);
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 24px 0;
            color: var(--text-secondary);
            font-size: 13px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .divider span {
            padding: 0 16px;
        }

        .login-link {
            display: block;
            text-align: center;
            padding: 16px 24px;
            border: 2px solid var(--border);
            border-radius: 14px;
            color: var(--text);
            text-decoration: none;
            font-weight: 500;
            font-size: 15px;
            transition: all 0.3s var(--ease-out);
        }

        .login-link:hover {
            border-color: var(--accent);
            background: var(--surface);
        }

        .form-footer {
            margin-top: 24px;
            text-align: center;
        }

        .form-link {
            color: var(--accent-dark);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.2s;
        }

        .form-link:hover {
            color: var(--text);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="register-layout">
        <div class="register-visual">
            <div class="visual-content">
                <div class="visual-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                </div>
                <h2 class="visual-title">Bliv leverandør</h2>
                <p class="visual-text">
                    Registrer din virksomhed og nå ud til tusindvis af arrangementer
                </p>
                <div class="visual-features">
                    <div class="visual-feature">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Vis dine ydelser frem
                    </div>
                    <div class="visual-feature">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Modtag bookingforespørgsler
                    </div>
                    <div class="visual-feature">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Bliv fundet af arrangører
                    </div>
                </div>
            </div>
        </div>

        <div class="register-form-section">
            <div class="register-header">
                <a href="/" class="register-logo">Party<span>Parart</span></a>
                <h1 class="register-title">Opret leverandørkonto</h1>
                <p class="register-subtitle">Udfyld formularen herunder for at komme i gang</p>
            </div>

            <div class="register-card">
                <?php if (!empty($errors)): ?>
                    <div class="error-list">
                        <ul>
                            <?php foreach ($errors as $err): ?>
                                <li><?= htmlspecialchars($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?= vendorCsrfField() ?>

                    <!-- Step 1: Virksomhedsinfo -->
                    <div class="form-section">
                        <h3 class="section-heading">
                            <span class="step-number">1</span>
                            Virksomhedsinfo
                        </h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="company_name">Firmanavn</label>
                                <input
                                    type="text"
                                    id="company_name"
                                    name="company_name"
                                    class="form-input"
                                    placeholder="Dit firmanavn"
                                    value="<?= htmlspecialchars($formData['company_name']) ?>"
                                    required
                                    autofocus
                                >
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="contact_name">Kontaktperson</label>
                                <input
                                    type="text"
                                    id="contact_name"
                                    name="contact_name"
                                    class="form-input"
                                    placeholder="Fulde navn"
                                    value="<?= htmlspecialchars($formData['contact_name']) ?>"
                                    required
                                >
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="email">Email</label>
                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    class="form-input"
                                    placeholder="firma@email.dk"
                                    value="<?= htmlspecialchars($formData['email']) ?>"
                                    required
                                >
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="phone">Telefon <span class="optional">(valgfrit)</span></label>
                                <input
                                    type="tel"
                                    id="phone"
                                    name="phone"
                                    class="form-input"
                                    placeholder="+45 12 34 56 78"
                                    value="<?= htmlspecialchars($formData['phone']) ?>"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Adgangskode -->
                    <div class="form-section">
                        <h3 class="section-heading">
                            <span class="step-number">2</span>
                            Adgangskode
                        </h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="password">Adgangskode</label>
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    class="form-input"
                                    placeholder="Mindst 8 tegn"
                                    required
                                    minlength="8"
                                >
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="password_confirm">Bekræft adgangskode</label>
                                <input
                                    type="password"
                                    id="password_confirm"
                                    name="password_confirm"
                                    class="form-input"
                                    placeholder="Gentag adgangskode"
                                    required
                                    minlength="8"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Kategorier -->
                    <div class="form-section">
                        <h3 class="section-heading">
                            <span class="step-number">3</span>
                            Kategorier
                        </h3>

                        <div class="category-grid">
                            <?php foreach ($categories as $cat): ?>
                                <label class="category-option">
                                    <input
                                        type="checkbox"
                                        name="categories[]"
                                        value="<?= (int)$cat['id'] ?>"
                                        <?= in_array((int)$cat['id'], array_map('intval', $formData['categories'])) ? 'checked' : '' ?>
                                    >
                                    <span class="category-icon"><?= htmlspecialchars($cat['icon'] ?? '') ?></span>
                                    <span class="category-label-text"><?= htmlspecialchars($cat['name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Step 4: Beskrivelse -->
                    <div class="form-section">
                        <h3 class="section-heading">
                            <span class="step-number">4</span>
                            Beskrivelse
                        </h3>

                        <div class="form-group">
                            <label class="form-label" for="short_description">Kort beskrivelse</label>
                            <textarea
                                id="short_description"
                                name="short_description"
                                class="form-input"
                                placeholder="Beskriv din virksomhed og de ydelser I tilbyder..."
                                maxlength="500"
                                required
                            ><?= htmlspecialchars($formData['short_description']) ?></textarea>
                            <div class="char-count">Maks 500 tegn</div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Opret konto
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </form>

                <div class="divider"><span>eller</span></div>

                <a href="/subcontractor/dashboard/login.php" class="login-link">Har du allerede en konto? Log ind</a>

                <div class="form-footer">
                    <a href="/" class="form-link">Tilbage til forsiden</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

<?php
/**
 * Partner Dashboard - Edit Profile
 */

require_once __DIR__ . '/../../includes/partner-auth.php';
requirePartner();

$db = getDB();
$partner = getCurrentPartner();
$partnerId = getCurrentPartnerId();

// Get categories
$categories = $db->query("SELECT * FROM partner_categories WHERE is_active = 1 ORDER BY sort_order")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $data = [
            'company_name' => trim($_POST['company_name'] ?? ''),
            'short_description' => trim($_POST['short_description'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'category_id' => (int)($_POST['category_id'] ?? $partner['category_id']),
            'contact_name' => trim($_POST['contact_name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'website' => trim($_POST['website'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
            'postal_code' => trim($_POST['postal_code'] ?? ''),
            'price_from' => (int)($_POST['price_from'] ?? 0) ?: null,
            'price_description' => trim($_POST['price_description'] ?? ''),
            'nationwide' => isset($_POST['nationwide']) ? 1 : 0,
        ];

        $stmt = $db->prepare("
            UPDATE partners SET
                company_name = ?,
                short_description = ?,
                description = ?,
                category_id = ?,
                contact_name = ?,
                email = ?,
                phone = ?,
                website = ?,
                address = ?,
                city = ?,
                postal_code = ?,
                price_from = ?,
                price_description = ?,
                nationwide = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $data['company_name'],
            $data['short_description'] ?: null,
            $data['description'] ?: null,
            $data['category_id'],
            $data['contact_name'],
            $data['email'],
            $data['phone'] ?: null,
            $data['website'] ?: null,
            $data['address'] ?: null,
            $data['city'] ?: null,
            $data['postal_code'] ?: null,
            $data['price_from'],
            $data['price_description'] ?: null,
            $data['nationwide'],
            $partnerId
        ]);

        setFlash('success', 'Profil opdateret');
        redirect(BASE_PATH . '/partners/dashboard/profile.php');
    }
}

// Refresh partner data
$partner = getCurrentPartner();

// Get flash
$flash = getFlash();

// New inquiries count for sidebar
$stmt = $db->prepare("SELECT COUNT(*) FROM partner_inquiries WHERE partner_id = ? AND status = 'new'");
$stmt->execute([$partnerId]);
$newInquiries = $stmt->fetchColumn();

// Platform name
try {
    $stmt = $db->query("SELECT setting_value FROM platform_settings WHERE setting_key = 'platform_name'");
    $platformName = $stmt->fetchColumn() ?: 'EventPlatform';
} catch (Exception $e) {
    $platformName = 'EventPlatform';
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rediger profil - Partner Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-bg: #f8fafc;
            --color-bg-subtle: #f1f5f9;
            --color-surface: #ffffff;
            --color-primary: #7c3aed;
            --color-primary-deep: #6d28d9;
            --color-primary-soft: #ede9fe;
            --color-text: #1e293b;
            --color-text-soft: #475569;
            --color-text-muted: #94a3b8;
            --color-border: #e2e8f0;
            --color-success: #22c55e;
            --color-error: #ef4444;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --radius-md: 8px;
            --radius-lg: 12px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: var(--color-bg);
            color: var(--color-text);
            line-height: 1.6;
        }

        .dashboard-layout { display: flex; min-height: 100vh; }

        .sidebar {
            width: 250px;
            background: var(--color-surface);
            border-right: 1px solid var(--color-border);
            position: fixed;
            top: 0; left: 0; bottom: 0;
            display: flex;
            flex-direction: column;
        }

        .sidebar-brand { padding: 1.25rem; border-bottom: 1px solid var(--color-border); }
        .sidebar-brand-name { font-size: 1rem; font-weight: 600; color: var(--color-primary); }
        .sidebar-brand-label { font-size: 0.75rem; color: var(--color-text-muted); }
        .sidebar-nav { flex: 1; padding: 1rem; }
        .nav-menu { list-style: none; }
        .nav-link {
            display: flex; align-items: center; gap: 0.75rem;
            padding: 0.75rem 1rem; color: var(--color-text-soft);
            text-decoration: none; border-radius: var(--radius-md);
            font-size: 0.9rem; margin-bottom: 0.25rem;
        }
        .nav-link:hover { background: var(--color-bg-subtle); }
        .nav-link.active { background: var(--color-primary-soft); color: var(--color-primary-deep); font-weight: 500; }
        .nav-badge { margin-left: auto; background: var(--color-error); color: white; font-size: 0.7rem; padding: 2px 6px; border-radius: 10px; }
        .sidebar-footer { padding: 1rem; border-top: 1px solid var(--color-border); }

        .main { flex: 1; margin-left: 250px; padding: 2rem; }
        .page-header { margin-bottom: 2rem; }
        .page-title { font-size: 1.5rem; font-weight: 600; }

        .card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem; margin-bottom: 1.5rem; }
        .card-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--color-border); }

        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; }
        .form-input {
            width: 100%; padding: 0.75rem 1rem;
            border: 1px solid var(--color-border); border-radius: var(--radius-md);
            font-size: 0.9rem;
        }
        .form-input:focus { outline: none; border-color: var(--color-primary); box-shadow: 0 0 0 3px var(--color-primary-soft); }
        textarea.form-input { resize: vertical; min-height: 100px; }
        .form-hint { font-size: 0.8rem; color: var(--color-text-muted); margin-top: 0.25rem; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

        .btn {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.75rem 1.5rem; border: none; border-radius: var(--radius-md);
            font-size: 0.9rem; font-weight: 500; cursor: pointer; text-decoration: none;
        }
        .btn-primary { background: var(--color-primary); color: white; }
        .btn-secondary { background: var(--color-bg-subtle); color: var(--color-text); }

        .alert { padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }

        .checkbox-label { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; }
        .checkbox-label input { width: 1rem; height: 1rem; }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main { margin-left: 0; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="sidebar-brand-name"><?= escape($partner['company_name']) ?></div>
                <div class="sidebar-brand-label">Partner Dashboard</div>
            </div>
            <nav class="sidebar-nav">
                <ul class="nav-menu">
                    <li><a href="<?= BASE_PATH ?>/partners/dashboard/" class="nav-link">&#128200; Dashboard</a></li>
                    <li><a href="<?= BASE_PATH ?>/partners/dashboard/profile.php" class="nav-link active">&#128736; Profil</a></li>
                    <li>
                        <a href="<?= BASE_PATH ?>/partners/dashboard/inquiries.php" class="nav-link">
                            &#128172; Forespørgsler
                            <?php if ($newInquiries > 0): ?><span class="nav-badge"><?= $newInquiries ?></span><?php endif; ?>
                        </a>
                    </li>
                    <li><a href="<?= BASE_PATH ?>/partners/dashboard/gallery.php" class="nav-link">&#128247; Galleri</a></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <a href="<?= BASE_PATH ?>/partners/profile.php?id=<?= $partnerId ?>" class="nav-link" target="_blank">&#128065; Se offentlig profil</a>
                <a href="<?= BASE_PATH ?>/partners/dashboard/logout.php" class="nav-link">&#128682; Log ud</a>
            </div>
        </aside>

        <!-- Main -->
        <main class="main">
            <div class="page-header">
                <h1 class="page-title">Rediger profil</h1>
            </div>

            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                    <?= escape($flash['message']) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <?= csrfField() ?>

                <!-- Basic Info -->
                <div class="card">
                    <h2 class="card-title">Grundlæggende information</h2>

                    <div class="form-group">
                        <label class="form-label">Firmanavn *</label>
                        <input type="text" name="company_name" class="form-input" required
                               value="<?= escape($partner['company_name']) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Kategori *</label>
                        <select name="category_id" class="form-input" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $partner['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                    <?= $cat['icon'] ?> <?= escape($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Kort beskrivelse</label>
                        <input type="text" name="short_description" class="form-input" maxlength="500"
                               value="<?= escape($partner['short_description'] ?? '') ?>"
                               placeholder="En kort sætning om hvad I tilbyder">
                        <div class="form-hint">Max 500 tegn. Vises i søgeresultater.</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Fuld beskrivelse</label>
                        <textarea name="description" class="form-input" rows="6"
                                  placeholder="Fortæl mere detaljeret om jeres services, erfaring, osv."><?= escape($partner['description'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Contact -->
                <div class="card">
                    <h2 class="card-title">Kontaktinformation</h2>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Kontaktperson *</label>
                            <input type="text" name="contact_name" class="form-input" required
                                   value="<?= escape($partner['contact_name']) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-input" required
                                   value="<?= escape($partner['email']) ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Telefon</label>
                            <input type="tel" name="phone" class="form-input"
                                   value="<?= escape($partner['phone'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Hjemmeside</label>
                            <input type="url" name="website" class="form-input" placeholder="https://"
                                   value="<?= escape($partner['website'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <!-- Location -->
                <div class="card">
                    <h2 class="card-title">Adresse og serviceområde</h2>

                    <div class="form-group">
                        <label class="form-label">Adresse</label>
                        <input type="text" name="address" class="form-input"
                               value="<?= escape($partner['address'] ?? '') ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Postnummer</label>
                            <input type="text" name="postal_code" class="form-input"
                                   value="<?= escape($partner['postal_code'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">By</label>
                            <input type="text" name="city" class="form-input"
                                   value="<?= escape($partner['city'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="nationwide" <?= $partner['nationwide'] ? 'checked' : '' ?>>
                            Vi dækker hele landet
                        </label>
                    </div>
                </div>

                <!-- Pricing -->
                <div class="card">
                    <h2 class="card-title">Priser</h2>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Fra-pris (DKK)</label>
                            <input type="number" name="price_from" class="form-input" min="0"
                                   value="<?= $partner['price_from'] ?? '' ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Prisbeskrivelse</label>
                            <input type="text" name="price_description" class="form-input"
                                   value="<?= escape($partner['price_description'] ?? '') ?>"
                                   placeholder="F.eks. 'Fra 5.000 kr' eller 'Kontakt for pris'">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Gem ændringer</button>
            </form>
        </main>
    </div>
</body>
</html>

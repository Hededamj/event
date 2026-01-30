<?php
/**
 * Partner Dashboard - Gallery
 */

require_once __DIR__ . '/../../includes/partner-auth.php';
requirePartner();

$db = getDB();
$partner = getCurrentPartner();
$partnerId = getCurrentPartnerId();

// Handle image actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add':
            $imageUrl = trim($_POST['image_url'] ?? '');
            $caption = trim($_POST['caption'] ?? '');

            if ($imageUrl) {
                $stmt = $db->prepare("
                    INSERT INTO partner_gallery (partner_id, image_url, caption, sort_order)
                    VALUES (?, ?, ?, (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM partner_gallery pg WHERE pg.partner_id = ?))
                ");
                $stmt->execute([$partnerId, $imageUrl, $caption ?: null, $partnerId]);
                setFlash('success', 'Billede tilføjet');
            }
            break;

        case 'delete':
            $imageId = (int)($_POST['image_id'] ?? 0);
            if ($imageId) {
                $stmt = $db->prepare("DELETE FROM partner_gallery WHERE id = ? AND partner_id = ?");
                $stmt->execute([$imageId, $partnerId]);
                setFlash('success', 'Billede slettet');
            }
            break;

        case 'update_cover':
            $coverUrl = trim($_POST['cover_url'] ?? '');
            $stmt = $db->prepare("UPDATE partners SET cover_image_url = ? WHERE id = ?");
            $stmt->execute([$coverUrl ?: null, $partnerId]);
            setFlash('success', 'Coverbillede opdateret');
            break;

        case 'update_logo':
            $logoUrl = trim($_POST['logo_url'] ?? '');
            $stmt = $db->prepare("UPDATE partners SET logo_url = ? WHERE id = ?");
            $stmt->execute([$logoUrl ?: null, $partnerId]);
            setFlash('success', 'Logo opdateret');
            break;
    }

    redirect(BASE_PATH . '/partners/dashboard/gallery.php');
}

// Refresh partner data
$partner = getCurrentPartner();

// Get gallery images
$stmt = $db->prepare("SELECT * FROM partner_gallery WHERE partner_id = ? ORDER BY sort_order");
$stmt->execute([$partnerId]);
$gallery = $stmt->fetchAll();

// Stats
$stmt = $db->prepare("SELECT COUNT(*) FROM partner_inquiries WHERE partner_id = ? AND status = 'new'");
$stmt->execute([$partnerId]);
$newInquiries = $stmt->fetchColumn();

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galleri - Partner Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-bg: #f8fafc; --color-bg-subtle: #f1f5f9; --color-surface: #ffffff;
            --color-primary: #7c3aed; --color-primary-deep: #6d28d9; --color-primary-soft: #ede9fe;
            --color-text: #1e293b; --color-text-soft: #475569; --color-text-muted: #94a3b8;
            --color-border: #e2e8f0; --color-success: #22c55e; --color-error: #ef4444;
            --radius-md: 8px; --radius-lg: 12px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: var(--color-bg); color: var(--color-text); line-height: 1.6; }
        .dashboard-layout { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: var(--color-surface); border-right: 1px solid var(--color-border); position: fixed; top: 0; left: 0; bottom: 0; display: flex; flex-direction: column; }
        .sidebar-brand { padding: 1.25rem; border-bottom: 1px solid var(--color-border); }
        .sidebar-brand-name { font-size: 1rem; font-weight: 600; color: var(--color-primary); }
        .sidebar-brand-label { font-size: 0.75rem; color: var(--color-text-muted); }
        .sidebar-nav { flex: 1; padding: 1rem; }
        .nav-menu { list-style: none; }
        .nav-link { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: var(--color-text-soft); text-decoration: none; border-radius: var(--radius-md); font-size: 0.9rem; margin-bottom: 0.25rem; }
        .nav-link:hover { background: var(--color-bg-subtle); }
        .nav-link.active { background: var(--color-primary-soft); color: var(--color-primary-deep); font-weight: 500; }
        .nav-badge { margin-left: auto; background: var(--color-error); color: white; font-size: 0.7rem; padding: 2px 6px; border-radius: 10px; }
        .sidebar-footer { padding: 1rem; border-top: 1px solid var(--color-border); }
        .main { flex: 1; margin-left: 250px; padding: 2rem; }
        .page-header { margin-bottom: 2rem; }
        .page-title { font-size: 1.5rem; font-weight: 600; }
        .card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem; margin-bottom: 1.5rem; }
        .card-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem; }
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; }
        .form-input { width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.9rem; }
        .form-input:focus { outline: none; border-color: var(--color-primary); }
        .form-hint { font-size: 0.8rem; color: var(--color-text-muted); margin-top: 0.25rem; }
        .btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border: none; border-radius: var(--radius-md); font-size: 0.85rem; font-weight: 500; cursor: pointer; text-decoration: none; }
        .btn-primary { background: var(--color-primary); color: white; }
        .btn-secondary { background: var(--color-bg-subtle); color: var(--color-text); }
        .btn-danger { background: var(--color-error); color: white; }
        .btn-sm { padding: 0.375rem 0.75rem; font-size: 0.8rem; }
        .alert { padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        .image-preview { width: 100%; max-width: 300px; aspect-ratio: 16/9; background: var(--color-bg-subtle); border-radius: var(--radius-md); overflow: hidden; margin-bottom: 1rem; display: flex; align-items: center; justify-content: center; color: var(--color-text-muted); }
        .image-preview img { width: 100%; height: 100%; object-fit: cover; }
        .gallery-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; }
        .gallery-item { position: relative; aspect-ratio: 1; border-radius: var(--radius-md); overflow: hidden; background: var(--color-bg-subtle); }
        .gallery-item img { width: 100%; height: 100%; object-fit: cover; }
        .gallery-item-actions { position: absolute; top: 0.5rem; right: 0.5rem; opacity: 0; transition: opacity 0.2s; }
        .gallery-item:hover .gallery-item-actions { opacity: 1; }
        .empty-state { text-align: center; padding: 2rem; color: var(--color-text-muted); }
        @media (max-width: 768px) { .sidebar { display: none; } .main { margin-left: 0; } }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="sidebar-brand-name"><?= escape($partner['company_name']) ?></div>
                <div class="sidebar-brand-label">Partner Dashboard</div>
            </div>
            <nav class="sidebar-nav">
                <ul class="nav-menu">
                    <li><a href="<?= BASE_PATH ?>/partners/dashboard/" class="nav-link">&#128200; Dashboard</a></li>
                    <li><a href="<?= BASE_PATH ?>/partners/dashboard/profile.php" class="nav-link">&#128736; Profil</a></li>
                    <li>
                        <a href="<?= BASE_PATH ?>/partners/dashboard/inquiries.php" class="nav-link">
                            &#128172; Forespørgsler
                            <?php if ($newInquiries > 0): ?><span class="nav-badge"><?= $newInquiries ?></span><?php endif; ?>
                        </a>
                    </li>
                    <li><a href="<?= BASE_PATH ?>/partners/dashboard/gallery.php" class="nav-link active">&#128247; Galleri</a></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <a href="<?= BASE_PATH ?>/partners/profile.php?id=<?= $partnerId ?>" class="nav-link" target="_blank">&#128065; Se offentlig profil</a>
                <a href="<?= BASE_PATH ?>/partners/dashboard/logout.php" class="nav-link">&#128682; Log ud</a>
            </div>
        </aside>

        <main class="main">
            <div class="page-header">
                <h1 class="page-title">Galleri</h1>
            </div>

            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                    <?= escape($flash['message']) ?>
                </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <!-- Cover Image -->
                <div class="card">
                    <h2 class="card-title">Coverbillede</h2>
                    <div class="image-preview">
                        <?php if ($partner['cover_image_url']): ?>
                            <img src="<?= escape($partner['cover_image_url']) ?>" alt="Cover">
                        <?php else: ?>
                            Intet coverbillede
                        <?php endif; ?>
                    </div>
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="update_cover">
                        <div class="form-group">
                            <label class="form-label">Billede URL</label>
                            <input type="url" name="cover_url" class="form-input" placeholder="https://..."
                                   value="<?= escape($partner['cover_image_url'] ?? '') ?>">
                            <div class="form-hint">Upload dit billede til f.eks. Imgur og indsæt URL'en her</div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Gem</button>
                    </form>
                </div>

                <!-- Logo -->
                <div class="card">
                    <h2 class="card-title">Logo</h2>
                    <div class="image-preview" style="aspect-ratio: 1; max-width: 150px;">
                        <?php if ($partner['logo_url']): ?>
                            <img src="<?= escape($partner['logo_url']) ?>" alt="Logo">
                        <?php else: ?>
                            Intet logo
                        <?php endif; ?>
                    </div>
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="update_logo">
                        <div class="form-group">
                            <label class="form-label">Logo URL</label>
                            <input type="url" name="logo_url" class="form-input" placeholder="https://..."
                                   value="<?= escape($partner['logo_url'] ?? '') ?>">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Gem</button>
                    </form>
                </div>
            </div>

            <!-- Gallery Images -->
            <div class="card">
                <h2 class="card-title">Galleribilleder</h2>

                <!-- Add new image -->
                <form method="POST" style="margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--color-border);">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add">
                    <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 1rem; align-items: end;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Billede URL</label>
                            <input type="url" name="image_url" class="form-input" placeholder="https://..." required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Billedtekst (valgfrit)</label>
                            <input type="text" name="caption" class="form-input" placeholder="Beskriv billedet...">
                        </div>
                        <button type="submit" class="btn btn-primary">Tilføj billede</button>
                    </div>
                </form>

                <?php if (empty($gallery)): ?>
                    <div class="empty-state">
                        <p>Ingen billeder i galleriet endnu</p>
                    </div>
                <?php else: ?>
                    <div class="gallery-grid">
                        <?php foreach ($gallery as $image): ?>
                            <div class="gallery-item">
                                <img src="<?= escape($image['image_url']) ?>" alt="<?= escape($image['caption'] ?? '') ?>">
                                <div class="gallery-item-actions">
                                    <form method="POST" style="display: inline;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="image_id" value="<?= $image['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm"
                                                onclick="return confirm('Slet dette billede?')">
                                            &#10005;
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>

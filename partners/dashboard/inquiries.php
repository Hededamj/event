<?php
/**
 * Partner Dashboard - Inquiries
 */

require_once __DIR__ . '/../../includes/partner-auth.php';
requirePartner();

$db = getDB();
$partner = getCurrentPartner();
$partnerId = getCurrentPartnerId();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $inquiryId = (int)($_POST['inquiry_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($inquiryId) {
        // Verify ownership
        $stmt = $db->prepare("SELECT id FROM partner_inquiries WHERE id = ? AND partner_id = ?");
        $stmt->execute([$inquiryId, $partnerId]);

        if ($stmt->fetch()) {
            switch ($action) {
                case 'mark_read':
                    $stmt = $db->prepare("UPDATE partner_inquiries SET status = 'read' WHERE id = ? AND status = 'new'");
                    $stmt->execute([$inquiryId]);
                    break;

                case 'mark_replied':
                    $reply = trim($_POST['reply'] ?? '');
                    if ($reply) {
                        $stmt = $db->prepare("UPDATE partner_inquiries SET status = 'replied', partner_reply = ?, replied_at = NOW() WHERE id = ?");
                        $stmt->execute([$reply, $inquiryId]);
                        setFlash('success', 'Svar sendt');
                    }
                    break;

                case 'close':
                    $stmt = $db->prepare("UPDATE partner_inquiries SET status = 'closed' WHERE id = ?");
                    $stmt->execute([$inquiryId]);
                    break;
            }
        }
    }

    redirect(BASE_PATH . '/partners/dashboard/inquiries.php');
}

// Get inquiries
$statusFilter = $_GET['status'] ?? '';

$where = ["partner_id = ?"];
$params = [$partnerId];

if ($statusFilter) {
    $where[] = "status = ?";
    $params[] = $statusFilter;
}

$whereClause = implode(' AND ', $where);

$stmt = $db->prepare("SELECT * FROM partner_inquiries WHERE $whereClause ORDER BY created_at DESC");
$stmt->execute($params);
$inquiries = $stmt->fetchAll();

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
    <title>Forespørgsler - Partner Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-bg: #f8fafc; --color-bg-subtle: #f1f5f9; --color-surface: #ffffff;
            --color-primary: #7c3aed; --color-primary-deep: #6d28d9; --color-primary-soft: #ede9fe;
            --color-text: #1e293b; --color-text-soft: #475569; --color-text-muted: #94a3b8;
            --color-border: #e2e8f0; --color-success: #22c55e; --color-warning: #f59e0b; --color-error: #ef4444;
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
        .page-header { margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; }
        .page-title { font-size: 1.5rem; font-weight: 600; }
        .card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); margin-bottom: 1rem; overflow: hidden; }
        .inquiry-header { padding: 1rem 1.5rem; border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center; background: var(--color-bg-subtle); }
        .inquiry-header.new { background: #fef3c7; }
        .inquiry-body { padding: 1.5rem; }
        .inquiry-meta { display: flex; gap: 2rem; margin-bottom: 1rem; font-size: 0.875rem; color: var(--color-text-soft); }
        .inquiry-message { background: var(--color-bg-subtle); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1rem; }
        .inquiry-reply { background: var(--color-primary-soft); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1rem; }
        .inquiry-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border: none; border-radius: var(--radius-md); font-size: 0.85rem; font-weight: 500; cursor: pointer; text-decoration: none; }
        .btn-primary { background: var(--color-primary); color: white; }
        .btn-secondary { background: var(--color-bg-subtle); color: var(--color-text); }
        .btn-sm { padding: 0.375rem 0.75rem; font-size: 0.8rem; }
        .badge { display: inline-block; padding: 0.25rem 0.5rem; border-radius: 10px; font-size: 0.75rem; font-weight: 500; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-error { background: #fee2e2; color: #991b1b; }
        .badge-info { background: var(--color-primary-soft); color: var(--color-primary); }
        .alert { padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; }
        .alert-success { background: #dcfce7; color: #166534; }
        .filters { margin-bottom: 1.5rem; display: flex; gap: 0.5rem; }
        .filters a { padding: 0.5rem 1rem; border-radius: var(--radius-md); text-decoration: none; color: var(--color-text-soft); font-size: 0.875rem; border: 1px solid var(--color-border); }
        .filters a:hover, .filters a.active { background: var(--color-primary-soft); color: var(--color-primary); border-color: var(--color-primary); }
        .empty-state { text-align: center; padding: 3rem; color: var(--color-text-muted); }
        .form-input { width: 100%; padding: 0.75rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.9rem; resize: vertical; }
        .reply-form { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--color-border); }
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
                        <a href="<?= BASE_PATH ?>/partners/dashboard/inquiries.php" class="nav-link active">
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

        <main class="main">
            <div class="page-header">
                <h1 class="page-title">Forespørgsler</h1>
            </div>

            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                    <?= escape($flash['message']) ?>
                </div>
            <?php endif; ?>

            <div class="filters">
                <a href="?" class="<?= !$statusFilter ? 'active' : '' ?>">Alle</a>
                <a href="?status=new" class="<?= $statusFilter === 'new' ? 'active' : '' ?>">Nye</a>
                <a href="?status=read" class="<?= $statusFilter === 'read' ? 'active' : '' ?>">Læst</a>
                <a href="?status=replied" class="<?= $statusFilter === 'replied' ? 'active' : '' ?>">Besvaret</a>
                <a href="?status=closed" class="<?= $statusFilter === 'closed' ? 'active' : '' ?>">Lukkede</a>
            </div>

            <?php if (empty($inquiries)): ?>
                <div class="empty-state">
                    <p>Ingen forespørgsler<?= $statusFilter ? ' med denne status' : '' ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($inquiries as $inquiry): ?>
                    <?php
                    $statusBadge = match($inquiry['status']) {
                        'new' => 'badge-error',
                        'read' => 'badge-warning',
                        'replied' => 'badge-success',
                        default => 'badge-info'
                    };
                    $statusText = match($inquiry['status']) {
                        'new' => 'Ny',
                        'read' => 'Læst',
                        'replied' => 'Besvaret',
                        'closed' => 'Lukket',
                        default => $inquiry['status']
                    };
                    ?>
                    <div class="card">
                        <div class="inquiry-header <?= $inquiry['status'] === 'new' ? 'new' : '' ?>">
                            <div>
                                <strong><?= escape($inquiry['name']) ?></strong>
                                <span style="color: var(--color-text-muted); margin-left: 0.5rem;"><?= escape($inquiry['email']) ?></span>
                            </div>
                            <span class="badge <?= $statusBadge ?>"><?= $statusText ?></span>
                        </div>
                        <div class="inquiry-body">
                            <div class="inquiry-meta">
                                <span>&#128197; <?= date('d/m/Y H:i', strtotime($inquiry['created_at'])) ?></span>
                                <?php if ($inquiry['phone']): ?>
                                    <span>&#128222; <?= escape($inquiry['phone']) ?></span>
                                <?php endif; ?>
                                <?php if ($inquiry['event_date']): ?>
                                    <span>&#127881; Event: <?= date('d/m/Y', strtotime($inquiry['event_date'])) ?></span>
                                <?php endif; ?>
                                <?php if ($inquiry['guest_count']): ?>
                                    <span>&#128101; <?= $inquiry['guest_count'] ?> gæster</span>
                                <?php endif; ?>
                            </div>

                            <div class="inquiry-message">
                                <?= nl2br(escape($inquiry['message'])) ?>
                            </div>

                            <?php if ($inquiry['partner_reply']): ?>
                                <div class="inquiry-reply">
                                    <strong>Dit svar (<?= date('d/m/Y', strtotime($inquiry['replied_at'])) ?>):</strong><br>
                                    <?= nl2br(escape($inquiry['partner_reply'])) ?>
                                </div>
                            <?php endif; ?>

                            <div class="inquiry-actions">
                                <?php if ($inquiry['status'] === 'new'): ?>
                                    <form method="POST" style="display: inline;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="inquiry_id" value="<?= $inquiry['id'] ?>">
                                        <button type="submit" name="action" value="mark_read" class="btn btn-secondary btn-sm">
                                            Marker som læst
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <a href="mailto:<?= escape($inquiry['email']) ?>" class="btn btn-primary btn-sm">
                                    Send email
                                </a>

                                <?php if ($inquiry['phone']): ?>
                                    <a href="tel:<?= escape($inquiry['phone']) ?>" class="btn btn-secondary btn-sm">
                                        Ring op
                                    </a>
                                <?php endif; ?>

                                <?php if ($inquiry['status'] !== 'closed'): ?>
                                    <form method="POST" style="display: inline;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="inquiry_id" value="<?= $inquiry['id'] ?>">
                                        <button type="submit" name="action" value="close" class="btn btn-secondary btn-sm">
                                            Luk
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <?php if ($inquiry['status'] !== 'replied' && $inquiry['status'] !== 'closed'): ?>
                                <div class="reply-form">
                                    <form method="POST">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="inquiry_id" value="<?= $inquiry['id'] ?>">
                                        <input type="hidden" name="action" value="mark_replied">
                                        <textarea name="reply" class="form-input" rows="3" placeholder="Skriv et svar..." required></textarea>
                                        <button type="submit" class="btn btn-primary btn-sm" style="margin-top: 0.5rem;">
                                            Gem svar
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>

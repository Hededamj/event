<?php
/**
 * Memories Admin Page
 * Moderate and manage timeline memories
 */

require_once __DIR__ . '/../../../includes/qr-functions.php';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyAccountCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Ugyldig anmodning.');
        redirect("?id=$eventId&page=memories-admin");
    }

    $action = $_POST['action'];
    $memoryId = (int)($_POST['memory_id'] ?? 0);

    if ($memoryId) {
        // Verify memory belongs to this event
        $stmt = $db->prepare("SELECT id FROM timeline_memories WHERE id = ? AND event_id = ?");
        $stmt->execute([$memoryId, $eventId]);

        if ($stmt->fetch()) {
            switch ($action) {
                case 'approve':
                    $stmt = $db->prepare("UPDATE timeline_memories SET approved = 1 WHERE id = ?");
                    $stmt->execute([$memoryId]);
                    setFlash('success', 'Mindet er godkendt.');
                    break;

                case 'reject':
                    $stmt = $db->prepare("UPDATE timeline_memories SET approved = 0 WHERE id = ?");
                    $stmt->execute([$memoryId]);
                    setFlash('success', 'Mindet er afvist.');
                    break;

                case 'delete':
                    // Get filename first
                    $stmt = $db->prepare("SELECT filename FROM timeline_memories WHERE id = ?");
                    $stmt->execute([$memoryId]);
                    $memory = $stmt->fetch();

                    if ($memory) {
                        // Delete file
                        $filePath = __DIR__ . '/../../../uploads/memories/' . $memory['filename'];
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }

                        // Delete from database
                        $stmt = $db->prepare("DELETE FROM timeline_memories WHERE id = ?");
                        $stmt->execute([$memoryId]);
                        setFlash('success', 'Mindet er slettet.');
                    }
                    break;
            }
        }
    }

    redirect("?id=$eventId&page=memories-admin");
}

// Get filter
$filter = $_GET['filter'] ?? 'all';

// Build query based on filter
$whereClause = "WHERE m.event_id = ?";
$params = [$eventId];

if ($filter === 'pending') {
    $whereClause .= " AND m.approved = 0";
} elseif ($filter === 'approved') {
    $whereClause .= " AND m.approved = 1";
}

// Get memories
$stmt = $db->prepare("
    SELECT m.*, g.name as uploader_name
    FROM timeline_memories m
    LEFT JOIN guests g ON m.uploaded_by_guest_id = g.id
    $whereClause
    ORDER BY m.created_at DESC
");
$stmt->execute($params);
$memories = $stmt->fetchAll();

// Get counts for tabs
$stmt = $db->prepare("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN approved = 1 THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN approved = 0 THEN 1 ELSE 0 END) as pending
    FROM timeline_memories WHERE event_id = ?
");
$stmt->execute([$eventId]);
$counts = $stmt->fetch();

// Get timeline settings
$timelineEnabled = !empty($event['timeline_enabled']);
$timelineStartDate = $event['timeline_start_date'] ?? null;
$timelineLabel = $event['timeline_label'] ?? 'Minder gennem tiden';

// Calculate years
$timelineYears = getTimelineYears($timelineStartDate, $event['event_date']);
$minYear = !empty($timelineYears) ? min($timelineYears) : null;
$maxYear = !empty($timelineYears) ? max($timelineYears) : null;
?>

<div class="page-header-actions">
    <div>
        <h1 class="section-title">Minde-tidslinje</h1>
        <p class="section-subtitle">Administrer minder uploadet af gæster</p>
    </div>
    <?php if ($event['slug']): ?>
    <a href="/e/<?= htmlspecialchars($event['slug']) ?>/memories" class="btn btn-secondary" target="_blank">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
        </svg>
        Se gæsteside
    </a>
    <?php endif; ?>
</div>

<!-- Status Card -->
<?php if (!$timelineEnabled): ?>
<div class="upgrade-notice" style="background: #FDF2F2; border-color: #F5D5D5;">
    <div class="upgrade-notice-content">
        <h4 style="color: var(--error);">Tidslinje ikke aktiveret</h4>
        <p style="color: #9B3939;">Gå til Indstillinger for at aktivere minde-tidslinjen for dette arrangement.</p>
    </div>
    <a href="?id=<?= $eventId ?>&page=settings" class="btn" style="background: var(--error);">
        Gå til indstillinger
    </a>
</div>
<?php else: ?>
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-label">Status</div>
        <div class="stat-value success" style="font-size: 24px;">Aktiv</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Periode</div>
        <div class="stat-value" style="font-size: 24px;">
            <?php if ($minYear && $maxYear): ?>
            <?= $minYear ?> - <?= $maxYear ?>
            <?php else: ?>
            Ikke konfigureret
            <?php endif; ?>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total minder</div>
        <div class="stat-value"><?= $counts['total'] ?? 0 ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Afventer godkendelse</div>
        <div class="stat-value <?= ($counts['pending'] ?? 0) > 0 ? 'warning' : '' ?>"><?= $counts['pending'] ?? 0 ?></div>
    </div>
</div>
<?php endif; ?>

<!-- Filter Tabs -->
<div class="filters-bar">
    <div class="filter-tabs">
        <a href="?id=<?= $eventId ?>&page=memories-admin&filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">
            Alle (<?= $counts['total'] ?? 0 ?>)
        </a>
        <a href="?id=<?= $eventId ?>&page=memories-admin&filter=approved" class="filter-tab <?= $filter === 'approved' ? 'active' : '' ?>">
            Godkendt (<?= $counts['approved'] ?? 0 ?>)
        </a>
        <a href="?id=<?= $eventId ?>&page=memories-admin&filter=pending" class="filter-tab <?= $filter === 'pending' ? 'active' : '' ?>">
            Afventer (<?= $counts['pending'] ?? 0 ?>)
        </a>
    </div>
</div>

<!-- Memories Grid -->
<?php if (empty($memories)): ?>
<div class="card">
    <div class="empty-state">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <h3>Ingen minder endnu</h3>
        <p>Gæster kan uploade minder via gæstesiden.</p>
    </div>
</div>
<?php else: ?>
<div class="memories-admin-grid">
    <?php foreach ($memories as $memory): ?>
    <div class="memory-admin-card <?= $memory['approved'] ? '' : 'pending' ?>">
        <div class="memory-admin-image">
            <img src="/uploads/memories/<?= htmlspecialchars($memory['filename']) ?>"
                 alt="<?= htmlspecialchars($memory['caption'] ?? 'Minde') ?>"
                 loading="lazy">
            <?php if (!$memory['approved']): ?>
            <div class="pending-badge">Afventer</div>
            <?php endif; ?>
        </div>

        <div class="memory-admin-info">
            <div class="memory-year-badge"><?= $memory['memory_year'] ?></div>

            <?php if ($memory['caption']): ?>
            <p class="memory-caption"><?= htmlspecialchars($memory['caption']) ?></p>
            <?php endif; ?>

            <div class="memory-meta">
                <?php if ($memory['uploader_name']): ?>
                <span class="uploader"><?= htmlspecialchars($memory['uploader_name']) ?></span>
                <?php endif; ?>
                <span class="date"><?= date('d/m/Y H:i', strtotime($memory['created_at'])) ?></span>
            </div>
        </div>

        <div class="memory-admin-actions">
            <form method="POST" style="display: contents;">
                <?= accountCsrfField() ?>
                <input type="hidden" name="memory_id" value="<?= $memory['id'] ?>">

                <?php if (!$memory['approved']): ?>
                <button type="submit" name="action" value="approve" class="btn btn-sm btn-sage" title="Godkend">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </button>
                <?php else: ?>
                <button type="submit" name="action" value="reject" class="btn btn-sm btn-secondary" title="Afvis">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                    </svg>
                </button>
                <?php endif; ?>

                <button type="submit" name="action" value="delete" class="btn btn-sm btn-secondary danger-text"
                        onclick="return confirm('Er du sikker på at du vil slette dette minde permanent?')" title="Slet">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<style>
.memories-admin-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.memory-admin-card {
    background: var(--white);
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid var(--cream-dark);
    transition: all 0.25s var(--ease-out);
}

.memory-admin-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
}

.memory-admin-card.pending {
    border-color: var(--warning);
    background: #FFFBF5;
}

.memory-admin-image {
    position: relative;
    aspect-ratio: 4/3;
    overflow: hidden;
}

.memory-admin-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.pending-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    background: var(--warning);
    color: white;
    font-size: 11px;
    font-weight: 600;
    padding: 5px 10px;
    border-radius: 6px;
}

.memory-admin-info {
    padding: 16px;
}

.memory-year-badge {
    display: inline-block;
    background: var(--cream);
    color: var(--charcoal);
    font-size: 13px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 6px;
    margin-bottom: 8px;
}

.memory-admin-info .memory-caption {
    font-size: 14px;
    color: var(--charcoal);
    margin-bottom: 8px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.memory-meta {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: var(--charcoal-light);
}

.memory-admin-actions {
    padding: 12px 16px;
    border-top: 1px solid var(--cream-dark);
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}

.memory-admin-actions .btn-sm {
    padding: 8px 12px;
}
</style>

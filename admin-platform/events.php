<?php
/**
 * Platform Admin - All Events
 */

require_once __DIR__ . '/../includes/admin-platform-header.php';

$db = getDB();

// Filters
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$typeFilter = (int)($_GET['type'] ?? 0);

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Build query
$where = ["1=1", "e.is_legacy = FALSE"];
$params = [];

if ($search) {
    $where[] = "(e.name LIKE ? OR e.slug LIKE ? OR a.name LIKE ? OR a.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($statusFilter) {
    $where[] = "e.status = ?";
    $params[] = $statusFilter;
}

if ($typeFilter) {
    $where[] = "e.event_type_id = ?";
    $params[] = $typeFilter;
}

$whereClause = implode(' AND ', $where);

// Count total
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT e.id)
    FROM events e
    LEFT JOIN event_owners eo ON e.id = eo.event_id AND eo.role = 'owner'
    LEFT JOIN accounts a ON eo.account_id = a.id
    WHERE $whereClause
");
$stmt->execute($params);
$totalEvents = $stmt->fetchColumn();
$totalPages = ceil($totalEvents / $perPage);

// Get events
$stmt = $db->prepare("
    SELECT e.*,
           et.name as event_type_name,
           et.icon as event_type_icon,
           a.name as owner_name,
           a.email as owner_email,
           a.id as owner_account_id,
           (SELECT COUNT(*) FROM guests WHERE event_id = e.id) as guest_count,
           (SELECT COUNT(*) FROM guests WHERE event_id = e.id AND rsvp_status = 'yes') as confirmed_guests
    FROM events e
    LEFT JOIN event_owners eo ON e.id = eo.event_id AND eo.role = 'owner'
    LEFT JOIN accounts a ON eo.account_id = a.id
    LEFT JOIN event_types et ON e.event_type_id = et.id
    WHERE $whereClause
    ORDER BY e.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$events = $stmt->fetchAll();

// Get event types for filter
$eventTypes = $db->query("SELECT id, name FROM event_types ORDER BY sort_order")->fetchAll();
?>

<header class="platform-header">
    <h1 class="page-title">Alle Events</h1>
    <div class="header-actions">
        <span class="text-muted"><?= number_format($totalEvents) ?> events i alt</span>
    </div>
</header>

<div class="platform-content">
    <?php $flash = getFlash(); ?>
    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
            <?= escape($flash['message']) ?>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card mb-lg">
        <form method="GET" class="flex gap-md items-center" style="flex-wrap: wrap;">
            <div class="search-box" style="flex: 1; min-width: 250px;">
                <span class="search-box-icon"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg></span>
                <input type="text" name="search" class="form-input" placeholder="Søg efter event-navn, slug, ejer..."
                       value="<?= escape($search) ?>" style="padding-left: 40px;">
            </div>

            <select name="type" class="form-input form-select" style="width: auto;">
                <option value="">Alle typer</option>
                <?php foreach ($eventTypes as $et): ?>
                    <option value="<?= $et['id'] ?>" <?= $typeFilter === $et['id'] ? 'selected' : '' ?>>
                        <?= escape($et['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="status" class="form-input form-select" style="width: auto;">
                <option value="">Alle status</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Aktiv</option>
                <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Kladde</option>
                <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Afsluttet</option>
                <option value="archived" <?= $statusFilter === 'archived' ? 'selected' : '' ?>>Arkiveret</option>
            </select>

            <button type="submit" class="btn btn-primary">Filtrer</button>

            <?php if ($search || $statusFilter || $typeFilter): ?>
                <a href="<?= BASE_PATH ?>/admin-platform/events.php" class="btn btn-secondary">Nulstil</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Events Table -->
    <div class="card">
        <?php if (empty($events)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg></div>
                <p>Ingen events fundet</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Ejer</th>
                            <th>Type</th>
                            <th>Dato</th>
                            <th>Gæster</th>
                            <th>Status</th>
                            <th>Oprettet</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event): ?>
                            <tr>
                                <td>
                                    <div class="font-medium"><?= escape($event['name']) ?></div>
                                    <?php if ($event['slug']): ?>
                                        <div class="text-xs text-muted">/<?= escape($event['slug']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($event['owner_account_id']): ?>
                                        <a href="<?= BASE_PATH ?>/admin-platform/account-detail.php?id=<?= $event['owner_account_id'] ?>"
                                           style="text-decoration: none; color: inherit;">
                                            <div class="font-medium"><?= escape($event['owner_name']) ?></div>
                                            <div class="text-xs text-muted"><?= escape($event['owner_email']) ?></div>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= escape($event['event_type_name'] ?? '-') ?></td>
                                <td class="text-sm">
                                    <?= $event['event_date'] ? date('d/m/Y', strtotime($event['event_date'])) : '-' ?>
                                </td>
                                <td>
                                    <span class="font-medium"><?= $event['confirmed_guests'] ?></span>
                                    <span class="text-muted text-xs">/ <?= $event['guest_count'] ?></span>
                                </td>
                                <td>
                                    <?php
                                    $eventBadgeMap = ['active' => 'badge-success', 'draft' => 'badge-warning', 'completed' => 'badge-info', 'archived' => 'badge-error'];
                                    $eventTextMap = ['active' => 'Aktiv', 'draft' => 'Kladde', 'completed' => 'Afsluttet', 'archived' => 'Arkiveret'];
                                    $evStatus = $event['status'] ?? 'active';
                                    $statusBadge = $eventBadgeMap[$evStatus] ?? 'badge-info';
                                    $statusText = $eventTextMap[$evStatus] ?? ucfirst($evStatus);
                                    ?>
                                    <span class="badge <?= $statusBadge ?>"><?= $statusText ?></span>
                                </td>
                                <td class="text-sm text-muted">
                                    <?= date('d/m/Y', strtotime($event['created_at'])) ?>
                                </td>
                                <td>
                                    <a href="<?= BASE_PATH ?>/admin-platform/event-detail.php?id=<?= $event['id'] ?>"
                                       class="btn btn-sm btn-secondary">Se</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="pagination" style="margin-top: var(--space-lg); display: flex; gap: var(--space-xs); justify-content: center;">
                    <?php
                    $queryParams = $_GET;
                    for ($i = 1; $i <= $totalPages; $i++):
                        $queryParams['page'] = $i;
                        $qs = http_build_query($queryParams);
                    ?>
                        <a href="<?= BASE_PATH ?>/admin-platform/events.php?<?= $qs ?>"
                           class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin-platform-footer.php'; ?>

<?php
/**
 * Admin Dashboard - Overview
 */
require_once __DIR__ . '/../includes/admin-header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';

// Get upcoming tasks
$stmt = $db->prepare("
    SELECT * FROM checklist_items
    WHERE event_id = ? AND completed = 0
    ORDER BY due_date ASC, sort_order ASC
    LIMIT 5
");
$stmt->execute([$eventId]);
$upcomingTasks = $stmt->fetchAll();

// Get recent RSVPs
$stmt = $db->prepare("
    SELECT * FROM guests
    WHERE event_id = ? AND rsvp_status != 'pending'
    ORDER BY rsvp_date DESC
    LIMIT 5
");
$stmt->execute([$eventId]);
$recentRsvps = $stmt->fetchAll();

// Get checklist progress
$stmt = $db->prepare("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) as completed
    FROM checklist_items
    WHERE event_id = ?
");
$stmt->execute([$eventId]);
$checklistStats = $stmt->fetch();
$checklistProgress = $checklistStats['total'] > 0
    ? round($checklistStats['completed'] / $checklistStats['total'] * 100)
    : 0;

// Get budget summary
$stmt = $db->prepare("
    SELECT
        SUM(estimated) as total_estimated,
        SUM(actual) as total_actual,
        SUM(CASE WHEN paid = 1 THEN actual ELSE 0 END) as total_paid
    FROM budget_items
    WHERE event_id = ?
");
$stmt->execute([$eventId]);
$budgetStats = $stmt->fetch();

// Get wishlist stats
$stmt = $db->prepare("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN reserved_by_guest_id IS NOT NULL THEN 1 ELSE 0 END) as reserved
    FROM wishlist_items
    WHERE event_id = ?
");
$stmt->execute([$eventId]);
$wishlistStats = $stmt->fetch();
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-header__title">Velkommen tilbage! 👋</h1>
        <p class="page-header__subtitle">
            Her er overblikket over <?= escape($event['confirmand_name']) ?>s <?= escape($event['name']) ?>
        </p>
    </div>
    <div class="page-header__actions">
        <a href="/admin/guests.php?action=add" class="btn btn--primary">+ Tilføj gæst</a>
        <a href="/" target="_blank" class="btn btn--secondary">Se gæstevisning</a>
    </div>
</div>

<!-- Quick Stats -->
<div class="quick-stats">
    <div class="quick-stat">
        <div class="quick-stat__value"><?= $guestStats['confirmed'] ?></div>
        <div class="quick-stat__label">Bekræftet</div>
    </div>
    <div class="quick-stat">
        <div class="quick-stat__value"><?= $guestStats['pending'] ?></div>
        <div class="quick-stat__label">Afventer svar</div>
    </div>
    <div class="quick-stat">
        <div class="quick-stat__value"><?= $guestStats['total_adults'] + $guestStats['total_children'] ?></div>
        <div class="quick-stat__label">Gæster i alt</div>
    </div>
    <div class="quick-stat">
        <div class="quick-stat__value"><?= $checklistProgress ?>%</div>
        <div class="quick-stat__label">Huskeliste</div>
    </div>
</div>

<!-- Dashboard Grid -->
<div class="dashboard-grid">
    <!-- RSVP Overview -->
    <div class="dashboard-card">
        <div class="dashboard-card__header">
            <h2 class="dashboard-card__title">
                <span>👥</span> RSVP Status
            </h2>
            <a href="/admin/guests.php" class="btn btn--ghost">Se alle</a>
        </div>
        <div class="dashboard-card__body">
            <!-- Visual breakdown -->
            <div class="flex gap-sm mb-md" style="height: 12px; border-radius: 6px; overflow: hidden; background: var(--color-border-soft);">
                <?php if ($guestStats['total_guests'] > 0): ?>
                    <?php $confirmedPct = $guestStats['confirmed'] / $guestStats['total_guests'] * 100; ?>
                    <?php $declinedPct = $guestStats['declined'] / $guestStats['total_guests'] * 100; ?>
                    <?php $pendingPct = $guestStats['pending'] / $guestStats['total_guests'] * 100; ?>
                    <div style="width: <?= $confirmedPct ?>%; background: var(--color-success);"></div>
                    <div style="width: <?= $declinedPct ?>%; background: var(--color-error);"></div>
                    <div style="width: <?= $pendingPct ?>%; background: var(--color-warning);"></div>
                <?php endif; ?>
            </div>

            <div class="stats" style="text-align: left;">
                <div>
                    <span class="badge badge--success">✓</span>
                    <span style="margin-left: 8px;"><?= $guestStats['confirmed'] ?> kommer</span>
                </div>
                <div>
                    <span class="badge badge--warning">?</span>
                    <span style="margin-left: 8px;"><?= $guestStats['pending'] ?> afventer</span>
                </div>
                <div>
                    <span class="badge badge--error">✗</span>
                    <span style="margin-left: 8px;"><?= $guestStats['declined'] ?> afbud</span>
                </div>
            </div>

            <?php if ($guestStats['confirmed'] > 0): ?>
                <div class="mt-md" style="padding-top: var(--space-sm); border-top: 1px solid var(--color-border-soft);">
                    <p class="text-muted small">
                        <strong><?= $guestStats['total_adults'] ?></strong> voksne og
                        <strong><?= $guestStats['total_children'] ?></strong> børn bekræftet
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Upcoming Tasks -->
    <div class="dashboard-card">
        <div class="dashboard-card__header">
            <h2 class="dashboard-card__title">
                <span>✅</span> Kommende opgaver
            </h2>
            <a href="/admin/checklist.php" class="btn btn--ghost">Se alle</a>
        </div>
        <div class="dashboard-card__body">
            <?php if (empty($upcomingTasks)): ?>
                <div class="empty-state" style="padding: var(--space-md);">
                    <p class="text-muted">Alle opgaver er fuldført! 🎉</p>
                </div>
            <?php else: ?>
                <?php foreach ($upcomingTasks as $task): ?>
                    <div class="list-item">
                        <div class="list-item__content">
                            <div class="list-item__title"><?= escape($task['task']) ?></div>
                            <?php if ($task['assigned_to']): ?>
                                <div class="list-item__subtitle"><?= escape($task['assigned_to']) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php if ($task['due_date']): ?>
                            <?php
                            $dueDate = strtotime($task['due_date']);
                            $isOverdue = $dueDate < time();
                            ?>
                            <div class="list-item__meta <?= $isOverdue ? 'text-error' : '' ?>">
                                <?= formatShortDate($task['due_date']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php if ($checklistStats['total'] > 0): ?>
            <div class="dashboard-card__footer">
                <div class="flex flex-between items-center mb-xs">
                    <span class="small text-muted"><?= $checklistStats['completed'] ?> af <?= $checklistStats['total'] ?></span>
                    <span class="small text-muted"><?= $checklistProgress ?>%</span>
                </div>
                <div class="progress">
                    <div class="progress__bar progress__bar--success" style="width: <?= $checklistProgress ?>%;"></div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent RSVPs -->
    <div class="dashboard-card">
        <div class="dashboard-card__header">
            <h2 class="dashboard-card__title">
                <span>🔔</span> Seneste svar
            </h2>
        </div>
        <div class="dashboard-card__body">
            <?php if (empty($recentRsvps)): ?>
                <div class="empty-state" style="padding: var(--space-md);">
                    <p class="text-muted">Ingen svar endnu</p>
                </div>
            <?php else: ?>
                <?php foreach ($recentRsvps as $rsvp): ?>
                    <div class="list-item">
                        <div class="list-item__content">
                            <div class="list-item__title"><?= escape($rsvp['name']) ?></div>
                            <?php if ($rsvp['rsvp_status'] === 'yes'): ?>
                                <div class="list-item__subtitle">
                                    <?= $rsvp['adults_count'] ?> voksen<?= $rsvp['adults_count'] > 1 ? 'e' : '' ?>
                                    <?php if ($rsvp['children_count'] > 0): ?>
                                        + <?= $rsvp['children_count'] ?> barn
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($rsvp['rsvp_status'] === 'yes'): ?>
                            <span class="badge badge--success">Kommer</span>
                        <?php else: ?>
                            <span class="badge badge--error">Afbud</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Budget Summary -->
    <div class="dashboard-card">
        <div class="dashboard-card__header">
            <h2 class="dashboard-card__title">
                <span>💰</span> Budget
            </h2>
            <a href="/admin/budget.php" class="btn btn--ghost">Se detaljer</a>
        </div>
        <div class="dashboard-card__body">
            <div class="stats">
                <div class="stat" style="padding: var(--space-sm);">
                    <div class="stat__value" style="font-size: var(--text-xl);">
                        <?= formatCurrency($budgetStats['total_estimated'] ?? 0) ?>
                    </div>
                    <div class="stat__label">Estimeret</div>
                </div>
                <div class="stat" style="padding: var(--space-sm);">
                    <div class="stat__value" style="font-size: var(--text-xl);">
                        <?= formatCurrency($budgetStats['total_paid'] ?? 0) ?>
                    </div>
                    <div class="stat__label">Betalt</div>
                </div>
            </div>

            <?php if ($budgetStats['total_estimated'] > 0): ?>
                <?php $budgetProgress = min(100, round(($budgetStats['total_paid'] ?? 0) / $budgetStats['total_estimated'] * 100)); ?>
                <div class="mt-sm">
                    <div class="progress">
                        <div class="progress__bar" style="width: <?= $budgetProgress ?>%;"></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Wishlist -->
    <div class="dashboard-card">
        <div class="dashboard-card__header">
            <h2 class="dashboard-card__title">
                <span>🎁</span> Ønskeliste
            </h2>
            <a href="/admin/wishlist.php" class="btn btn--ghost">Se alle</a>
        </div>
        <div class="dashboard-card__body">
            <div class="stats">
                <div class="stat" style="padding: var(--space-sm);">
                    <div class="stat__value" style="font-size: var(--text-xl);">
                        <?= $wishlistStats['total'] ?? 0 ?>
                    </div>
                    <div class="stat__label">Ønsker</div>
                </div>
                <div class="stat" style="padding: var(--space-sm);">
                    <div class="stat__value" style="font-size: var(--text-xl);">
                        <?= $wishlistStats['reserved'] ?? 0 ?>
                    </div>
                    <div class="stat__label">Reserveret</div>
                </div>
            </div>

            <?php if ($wishlistStats['total'] > 0): ?>
                <?php $wishlistProgress = round(($wishlistStats['reserved'] ?? 0) / $wishlistStats['total'] * 100); ?>
                <div class="mt-sm">
                    <div class="progress">
                        <div class="progress__bar progress__bar--success" style="width: <?= $wishlistProgress ?>%;"></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card mt-lg">
    <h3 class="card__title mb-sm">Hurtige handlinger</h3>
    <div class="action-row">
        <a href="/admin/guests.php?action=add" class="btn btn--secondary">+ Tilføj gæst</a>
        <a href="/admin/checklist.php?action=add" class="btn btn--secondary">+ Ny opgave</a>
        <a href="/admin/wishlist.php?action=add" class="btn btn--secondary">+ Nyt ønske</a>
        <a href="/admin/menu.php?action=add" class="btn btn--secondary">+ Tilføj til menu</a>
    </div>
</div>

</main>
</div>

<!-- Sidebar overlay for mobile -->
<div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleSidebar()"></div>

<script src="/assets/js/main.js"></script>
<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    sidebar.classList.toggle('sidebar--open');
    overlay.classList.toggle('sidebar-overlay--active');
}
</script>
</body>
</html>

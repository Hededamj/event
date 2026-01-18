<?php
/**
 * Admin Dashboard - Overview
 * Refined editorial design
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

// Get invitation stats
$stmt = $db->prepare("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN invitation_sent = 1 THEN 1 ELSE 0 END) as sent
    FROM guests
    WHERE event_id = ?
");
$stmt->execute([$eventId]);
$invitationStats = $stmt->fetch();
?>

<style>
/* Dashboard Hero */
.dashboard-hero {
    background: linear-gradient(135deg, var(--color-primary-pale) 0%, var(--color-surface) 100%);
    border-radius: var(--radius-xl);
    padding: var(--space-xl);
    margin-bottom: var(--space-lg);
    position: relative;
    overflow: hidden;
}

.dashboard-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 60%;
    height: 200%;
    background: radial-gradient(ellipse, var(--color-accent-pale) 0%, transparent 70%);
    opacity: 0.5;
    pointer-events: none;
}

.dashboard-hero__content {
    position: relative;
    z-index: 1;
}

.dashboard-hero__eyebrow {
    font-size: var(--text-xs);
    text-transform: uppercase;
    letter-spacing: 0.15em;
    color: var(--color-accent);
    margin-bottom: var(--space-2xs);
    font-weight: 500;
}

.dashboard-hero__title {
    font-family: 'Cormorant Garamond', serif;
    font-size: var(--text-3xl);
    font-weight: 400;
    color: var(--color-primary-deep);
    margin-bottom: var(--space-xs);
    line-height: 1.1;
}

.dashboard-hero__title em {
    font-style: italic;
    color: var(--color-text);
}

.dashboard-hero__subtitle {
    color: var(--color-text-soft);
    max-width: 500px;
}

.dashboard-hero__actions {
    display: flex;
    gap: var(--space-sm);
    margin-top: var(--space-md);
}

/* Stat Cards Grid */
.stat-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: var(--space-md);
    margin-bottom: var(--space-lg);
}

@media (max-width: 1200px) {
    .stat-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 640px) {
    .stat-grid { grid-template-columns: 1fr; }
}

.stat-card {
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    padding: var(--space-lg);
    border: 1px solid var(--color-border-soft);
    position: relative;
    overflow: hidden;
    transition: all var(--duration-normal) var(--ease-out-quart);
}

.stat-card:hover {
    border-color: var(--color-primary-soft);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.stat-card__icon {
    width: 48px;
    height: 48px;
    background: var(--color-bg-subtle);
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: var(--space-md);
    color: var(--color-primary);
}

.stat-card__icon svg {
    width: 24px;
    height: 24px;
    stroke: currentColor;
    stroke-width: 1.5;
    fill: none;
}

.stat-card__value {
    font-family: 'Cormorant Garamond', serif;
    font-size: var(--text-3xl);
    font-weight: 500;
    color: var(--color-primary-deep);
    line-height: 1;
    margin-bottom: var(--space-2xs);
}

.stat-card__label {
    font-size: var(--text-sm);
    color: var(--color-text-muted);
    margin-bottom: var(--space-sm);
}

.stat-card__detail {
    font-size: var(--text-xs);
    color: var(--color-text-muted);
    padding-top: var(--space-sm);
    border-top: 1px solid var(--color-border-soft);
}

.stat-card__detail strong {
    color: var(--color-text);
}

/* Content Grid */
.content-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--space-lg);
}

@media (max-width: 1024px) {
    .content-grid { grid-template-columns: 1fr; }
}

/* Section Card */
.section-card {
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    border: 1px solid var(--color-border-soft);
    overflow: hidden;
}

.section-card__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--space-md) var(--space-lg);
    border-bottom: 1px solid var(--color-border-soft);
}

.section-card__title {
    font-family: 'Cormorant Garamond', serif;
    font-size: var(--text-lg);
    font-weight: 500;
    color: var(--color-text);
    display: flex;
    align-items: center;
    gap: var(--space-xs);
}

.section-card__title svg {
    width: 20px;
    height: 20px;
    stroke: var(--color-primary);
    stroke-width: 1.5;
    fill: none;
}

.section-card__body {
    padding: var(--space-md) var(--space-lg);
}

.section-card__footer {
    padding: var(--space-sm) var(--space-lg);
    background: var(--color-bg-subtle);
    border-top: 1px solid var(--color-border-soft);
}

/* RSVP Visual Bar */
.rsvp-bar {
    display: flex;
    height: 8px;
    border-radius: var(--radius-full);
    overflow: hidden;
    background: var(--color-border-soft);
    margin-bottom: var(--space-md);
}

.rsvp-bar__segment {
    transition: width var(--duration-slow) var(--ease-out-expo);
}

.rsvp-bar__segment--yes { background: var(--color-success); }
.rsvp-bar__segment--no { background: var(--color-error); }
.rsvp-bar__segment--pending { background: var(--color-warning); }

/* RSVP Legend */
.rsvp-legend {
    display: flex;
    flex-direction: column;
    gap: var(--space-xs);
}

.rsvp-legend__item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--space-xs) 0;
}

.rsvp-legend__label {
    display: flex;
    align-items: center;
    gap: var(--space-xs);
}

.rsvp-legend__dot {
    width: 10px;
    height: 10px;
    border-radius: var(--radius-full);
}

.rsvp-legend__dot--yes { background: var(--color-success); }
.rsvp-legend__dot--no { background: var(--color-error); }
.rsvp-legend__dot--pending { background: var(--color-warning); }

.rsvp-legend__value {
    font-family: 'Cormorant Garamond', serif;
    font-size: var(--text-lg);
    font-weight: 500;
    color: var(--color-text);
}

/* Task List */
.task-list {
    display: flex;
    flex-direction: column;
}

.task-item {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    padding: var(--space-sm) 0;
    border-bottom: 1px solid var(--color-border-soft);
}

.task-item:last-child {
    border-bottom: none;
}

.task-item__bullet {
    width: 6px;
    height: 6px;
    background: var(--color-primary);
    border-radius: var(--radius-full);
    flex-shrink: 0;
}

.task-item__content {
    flex: 1;
    min-width: 0;
}

.task-item__title {
    font-size: var(--text-sm);
    color: var(--color-text);
}

.task-item__meta {
    font-size: var(--text-xs);
    color: var(--color-text-muted);
}

.task-item__date {
    font-size: var(--text-xs);
    color: var(--color-text-muted);
    white-space: nowrap;
}

.task-item__date--overdue {
    color: var(--color-error);
    font-weight: 500;
}

/* Activity List */
.activity-list {
    display: flex;
    flex-direction: column;
}

.activity-item {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    padding: var(--space-sm) 0;
    border-bottom: 1px solid var(--color-border-soft);
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-item__avatar {
    width: 36px;
    height: 36px;
    background: var(--color-primary-soft);
    color: var(--color-primary-deep);
    border-radius: var(--radius-full);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 500;
    font-size: var(--text-sm);
    flex-shrink: 0;
}

.activity-item__content {
    flex: 1;
    min-width: 0;
}

.activity-item__name {
    font-size: var(--text-sm);
    font-weight: 500;
    color: var(--color-text);
}

.activity-item__detail {
    font-size: var(--text-xs);
    color: var(--color-text-muted);
}

.activity-item__status {
    font-size: var(--text-xs);
    font-weight: 500;
    padding: var(--space-3xs) var(--space-xs);
    border-radius: var(--radius-sm);
}

.activity-item__status--yes {
    background: rgba(34, 197, 94, 0.1);
    color: var(--color-success);
}

.activity-item__status--no {
    background: rgba(239, 68, 68, 0.1);
    color: var(--color-error);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: var(--space-lg);
    color: var(--color-text-muted);
}

.empty-state__icon {
    width: 48px;
    height: 48px;
    margin: 0 auto var(--space-sm);
    opacity: 0.3;
}

/* Quick Actions */
.quick-actions {
    display: flex;
    gap: var(--space-sm);
    flex-wrap: wrap;
    margin-top: var(--space-lg);
}

.quick-action {
    display: flex;
    align-items: center;
    gap: var(--space-xs);
    padding: var(--space-sm) var(--space-md);
    background: var(--color-surface);
    border: 1px solid var(--color-border-soft);
    border-radius: var(--radius-md);
    color: var(--color-text);
    font-size: var(--text-sm);
    text-decoration: none;
    transition: all var(--duration-fast);
}

.quick-action:hover {
    border-color: var(--color-primary);
    color: var(--color-primary-deep);
}

.quick-action svg {
    width: 16px;
    height: 16px;
    stroke: currentColor;
    stroke-width: 1.5;
    fill: none;
}
</style>

<!-- Dashboard Hero -->
<div class="dashboard-hero">
    <div class="dashboard-hero__content">
        <p class="dashboard-hero__eyebrow">Overblik</p>
        <h1 class="dashboard-hero__title">
            <?= escape($event['confirmand_name']) ?><em>s</em> <?= escape($event['name']) ?>
        </h1>
        <p class="dashboard-hero__subtitle">
            <?php if ($daysUntil === 0): ?>
                Det er i dag! Alt er klar.
            <?php elseif ($isPast): ?>
                Eventet er afholdt. Tak for denne gang!
            <?php else: ?>
                <?= $daysUntil ?> dage tilbage. Her er status på forberedelserne.
            <?php endif; ?>
        </p>
        <div class="dashboard-hero__actions">
            <a href="<?= BASE_PATH ?>/admin/guests.php" class="btn btn--primary">Se gæsteliste</a>
            <a href="<?= BASE_PATH ?>/" target="_blank" class="btn btn--secondary">Forhåndsvis invitation</a>
        </div>
    </div>
</div>

<!-- Stat Cards -->
<div class="stat-grid">
    <!-- Gæster -->
    <div class="stat-card">
        <div class="stat-card__icon">
            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div class="stat-card__value"><?= $guestStats['confirmed'] ?></div>
        <div class="stat-card__label">Bekræftede gæster</div>
        <div class="stat-card__detail">
            <strong><?= $guestStats['total_adults'] ?></strong> voksne &middot;
            <strong><?= $guestStats['total_children'] ?></strong> børn
        </div>
    </div>

    <!-- Afventer -->
    <div class="stat-card">
        <div class="stat-card__icon">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-card__value"><?= $guestStats['pending'] ?></div>
        <div class="stat-card__label">Afventer svar</div>
        <div class="stat-card__detail">
            <strong><?= $invitationStats['sent'] ?? 0 ?></strong> af <strong><?= $invitationStats['total'] ?? 0 ?></strong> invitationer sendt
        </div>
    </div>

    <!-- Huskeliste -->
    <div class="stat-card">
        <div class="stat-card__icon">
            <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <div class="stat-card__value"><?= $checklistProgress ?>%</div>
        <div class="stat-card__label">Huskeliste fuldført</div>
        <div class="stat-card__detail">
            <strong><?= $checklistStats['completed'] ?></strong> af <strong><?= $checklistStats['total'] ?></strong> opgaver
        </div>
    </div>

    <!-- Ønsker -->
    <div class="stat-card">
        <div class="stat-card__icon">
            <svg viewBox="0 0 24 24"><path d="M20 12v10H4V12"/><path d="M2 7h20v5H2z"/><path d="M12 22V7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg>
        </div>
        <div class="stat-card__value"><?= $wishlistStats['reserved'] ?? 0 ?></div>
        <div class="stat-card__label">Ønsker reserveret</div>
        <div class="stat-card__detail">
            af <strong><?= $wishlistStats['total'] ?? 0 ?></strong> på ønskelisten
        </div>
    </div>
</div>

<!-- Content Grid -->
<div class="content-grid">
    <!-- RSVP Status -->
    <div class="section-card">
        <div class="section-card__header">
            <h2 class="section-card__title">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                RSVP Status
            </h2>
            <a href="<?= BASE_PATH ?>/admin/guests.php" class="btn btn--ghost btn--sm">Se alle</a>
        </div>
        <div class="section-card__body">
            <?php if ($guestStats['total_guests'] > 0): ?>
                <?php
                $confirmedPct = $guestStats['confirmed'] / $guestStats['total_guests'] * 100;
                $declinedPct = $guestStats['declined'] / $guestStats['total_guests'] * 100;
                $pendingPct = $guestStats['pending'] / $guestStats['total_guests'] * 100;
                ?>
                <div class="rsvp-bar">
                    <div class="rsvp-bar__segment rsvp-bar__segment--yes" style="width: <?= $confirmedPct ?>%;"></div>
                    <div class="rsvp-bar__segment rsvp-bar__segment--no" style="width: <?= $declinedPct ?>%;"></div>
                    <div class="rsvp-bar__segment rsvp-bar__segment--pending" style="width: <?= $pendingPct ?>%;"></div>
                </div>

                <div class="rsvp-legend">
                    <div class="rsvp-legend__item">
                        <span class="rsvp-legend__label">
                            <span class="rsvp-legend__dot rsvp-legend__dot--yes"></span>
                            Kommer
                        </span>
                        <span class="rsvp-legend__value"><?= $guestStats['confirmed'] ?></span>
                    </div>
                    <div class="rsvp-legend__item">
                        <span class="rsvp-legend__label">
                            <span class="rsvp-legend__dot rsvp-legend__dot--pending"></span>
                            Afventer
                        </span>
                        <span class="rsvp-legend__value"><?= $guestStats['pending'] ?></span>
                    </div>
                    <div class="rsvp-legend__item">
                        <span class="rsvp-legend__label">
                            <span class="rsvp-legend__dot rsvp-legend__dot--no"></span>
                            Afbud
                        </span>
                        <span class="rsvp-legend__value"><?= $guestStats['declined'] ?></span>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>Ingen gæster tilføjet endnu</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Kommende Opgaver -->
    <div class="section-card">
        <div class="section-card__header">
            <h2 class="section-card__title">
                <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                Kommende opgaver
            </h2>
            <a href="<?= BASE_PATH ?>/admin/checklist.php" class="btn btn--ghost btn--sm">Se alle</a>
        </div>
        <div class="section-card__body">
            <?php if (empty($upcomingTasks)): ?>
                <div class="empty-state">
                    <p>Alle opgaver er fuldført</p>
                </div>
            <?php else: ?>
                <div class="task-list">
                    <?php foreach ($upcomingTasks as $task): ?>
                        <?php
                        $isOverdue = $task['due_date'] && strtotime($task['due_date']) < time();
                        ?>
                        <div class="task-item">
                            <span class="task-item__bullet"></span>
                            <div class="task-item__content">
                                <div class="task-item__title"><?= escape($task['task']) ?></div>
                                <?php if ($task['assigned_to']): ?>
                                    <div class="task-item__meta"><?= escape($task['assigned_to']) ?></div>
                                <?php endif; ?>
                            </div>
                            <?php if ($task['due_date']): ?>
                                <span class="task-item__date <?= $isOverdue ? 'task-item__date--overdue' : '' ?>">
                                    <?= formatShortDate($task['due_date']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($checklistStats['total'] > 0): ?>
            <div class="section-card__footer">
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

    <!-- Seneste Svar -->
    <div class="section-card">
        <div class="section-card__header">
            <h2 class="section-card__title">
                <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                Seneste svar
            </h2>
        </div>
        <div class="section-card__body">
            <?php if (empty($recentRsvps)): ?>
                <div class="empty-state">
                    <p>Ingen svar modtaget endnu</p>
                </div>
            <?php else: ?>
                <div class="activity-list">
                    <?php foreach ($recentRsvps as $rsvp): ?>
                        <div class="activity-item">
                            <div class="activity-item__avatar">
                                <?= strtoupper(substr($rsvp['name'], 0, 1)) ?>
                            </div>
                            <div class="activity-item__content">
                                <div class="activity-item__name"><?= escape($rsvp['name']) ?></div>
                                <?php if ($rsvp['rsvp_status'] === 'yes'): ?>
                                    <div class="activity-item__detail">
                                        <?= $rsvp['adults_count'] ?> voksen<?= $rsvp['adults_count'] > 1 ? 'e' : '' ?>
                                        <?php if ($rsvp['children_count'] > 0): ?>
                                            + <?= $rsvp['children_count'] ?> børn
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <span class="activity-item__status activity-item__status--<?= $rsvp['rsvp_status'] ?>">
                                <?= $rsvp['rsvp_status'] === 'yes' ? 'Kommer' : 'Afbud' ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Budget & Ønsker -->
    <div class="section-card">
        <div class="section-card__header">
            <h2 class="section-card__title">
                <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                Økonomi
            </h2>
            <a href="<?= BASE_PATH ?>/admin/budget.php" class="btn btn--ghost btn--sm">Se budget</a>
        </div>
        <div class="section-card__body">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-md);">
                <div>
                    <div style="font-size: var(--text-xs); color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: var(--space-2xs);">Estimeret</div>
                    <div style="font-family: 'Cormorant Garamond', serif; font-size: var(--text-xl); color: var(--color-text);">
                        <?= formatCurrency($budgetStats['total_estimated'] ?? 0) ?>
                    </div>
                </div>
                <div>
                    <div style="font-size: var(--text-xs); color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: var(--space-2xs);">Betalt</div>
                    <div style="font-family: 'Cormorant Garamond', serif; font-size: var(--text-xl); color: var(--color-success);">
                        <?= formatCurrency($budgetStats['total_paid'] ?? 0) ?>
                    </div>
                </div>
            </div>
            <?php if ($budgetStats['total_estimated'] > 0): ?>
                <?php $budgetProgress = min(100, round(($budgetStats['total_paid'] ?? 0) / $budgetStats['total_estimated'] * 100)); ?>
                <div class="mt-md">
                    <div class="progress">
                        <div class="progress__bar" style="width: <?= $budgetProgress ?>%;"></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="quick-actions">
    <a href="<?= BASE_PATH ?>/admin/guests.php?action=add" class="quick-action">
        <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
        Tilføj gæst
    </a>
    <a href="<?= BASE_PATH ?>/admin/checklist.php" class="quick-action">
        <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        Ny opgave
    </a>
    <a href="<?= BASE_PATH ?>/admin/wishlist.php" class="quick-action">
        <svg viewBox="0 0 24 24"><path d="M20 12v10H4V12"/><path d="M2 7h20v5H2z"/><path d="M12 22V7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg>
        Tilføj ønske
    </a>
    <a href="<?= BASE_PATH ?>/admin/settings.php" class="quick-action">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        Indstillinger
    </a>
</div>

</main>
</div>

<!-- Sidebar overlay for mobile -->
<div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleSidebar()"></div>

<script src="<?= BASE_PATH ?>/assets/js/main.js"></script>
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

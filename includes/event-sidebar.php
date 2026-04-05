<?php
/**
 * Event Sidebar Navigation Component
 *
 * Renders grouped sidebar navigation for event management.
 * Outputs three HTML blocks: desktop sidebar, mobile bottom bar, bottom sheet.
 *
 * Expected variables from parent scope:
 * @var int    $eventId      Current event ID
 * @var array  $event        Event data (name, slug, event_type_name)
 * @var string $page         Current page identifier
 * @var array  $subscription Subscription data (plan_name, plan_slug)
 * @var bool   $hasChecklist Feature flag
 * @var bool   $hasBudget    Feature flag
 * @var bool   $hasSeating   Feature flag
 * @var bool   $hasToastmaster Feature flag
 */

// Ensure $eventId is an integer for safe output
$eventId = (int)$eventId;

// Define navigation groups with their items
$sidebarGroups = [
    'planning' => [
        'label' => 'Planlægning',
        'short_label' => 'Plan',
        'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"></path><rect x="9" y="3" width="6" height="4" rx="1"></rect><path d="M9 14l2 2 4-4"></path></svg>',
        'items' => [
            ['label' => 'Oversigt', 'page' => 'dashboard', 'also_active' => []],
            ['label' => 'Tjekliste', 'page' => 'checklist', 'also_active' => [], 'premium' => !$hasChecklist],
            ['label' => 'Program', 'page' => 'schedule', 'also_active' => ['menu']],
            ['label' => 'Budget', 'page' => 'budget', 'also_active' => [], 'premium' => !$hasBudget],
            ['label' => 'Bordplan', 'page' => 'seating', 'also_active' => [], 'premium' => !$hasSeating],
        ],
    ],
    'guests' => [
        'label' => 'Gæster',
        'short_label' => 'Gæster',
        'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 00-3-3.87"></path><path d="M16 3.13a4 4 0 010 7.75"></path></svg>',
        'items' => [
            ['label' => 'Gæsteliste', 'page' => 'guests', 'also_active' => []],
            ['label' => 'Invitation', 'page' => 'invitation', 'also_active' => []],
            ['label' => 'Send invitation', 'page' => 'invitation-send', 'also_active' => []],
            ['label' => 'QR-Bordkort', 'page' => 'qr-bordkort', 'also_active' => []],
        ],
    ],
    'content' => [
        'label' => 'Indhold',
        'short_label' => 'Indhold',
        'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12v10H4V12"></path><path d="M2 7h20v5H2z"></path><path d="M12 22V7"></path><path d="M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z"></path></svg>',
        'items' => [
            ['label' => 'Ønskeliste', 'page' => 'wishlist', 'also_active' => []],
            ['label' => 'Fotos', 'page' => 'photos', 'also_active' => []],
            ['label' => 'Minder', 'page' => 'memories-admin', 'also_active' => []],
        ],
    ],
    'extra' => [
        'label' => 'Ekstra',
        'short_label' => 'Ekstra',
        'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"></path><path d="M19 10v2a7 7 0 01-14 0v-2"></path><line x1="12" y1="19" x2="12" y2="23"></line><line x1="8" y1="23" x2="16" y2="23"></line></svg>',
        'items' => [
            ['label' => 'Toastmaster', 'page' => 'toastmaster', 'also_active' => [], 'premium' => !$hasToastmaster],
            ['label' => 'Leverandører', 'page' => 'vendors', 'also_active' => ['vendor-booking']],
        ],
    ],
];

// Settings is a direct link, not a group with sub-items
$settingsIcon = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09a1.65 1.65 0 00-1.08-1.51 1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09a1.65 1.65 0 001.51-1.08 1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001.08 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1.08z"></path></svg>';

// Hide invitation-send for open registration events
if (($event['registration_mode'] ?? 'invite') === 'open') {
    $sidebarGroups['guests']['items'] = array_filter($sidebarGroups['guests']['items'], function($item) {
        return $item['page'] !== 'invitation-send';
    });
}

// Determine which group is active based on current $page
$activeGroup = '';
foreach ($sidebarGroups as $groupKey => $group) {
    foreach ($group['items'] as $item) {
        if ($page === $item['page'] || in_array($page, $item['also_active'])) {
            $activeGroup = $groupKey;
            break 2;
        }
    }
}
if ($page === 'settings') {
    $activeGroup = 'settings';
}

// Plan info for footer
$planName = htmlspecialchars($subscription['plan_name'] ?? 'Gratis');
$planSlug = $subscription['plan_slug'] ?? 'free';
$isFreePlan = ($planSlug === 'free' || empty($subscription));
?>

<!-- ============================================== -->
<!-- A. Desktop Sidebar                             -->
<!-- ============================================== -->
<aside class="event-sidebar" id="eventSidebar">
    <div class="sidebar-header">
        <a href="/app/dashboard.php" class="sidebar-logo">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
            <span>PartyParart</span>
        </a>
    </div>

    <nav class="sidebar-nav">
        <!-- App-level nav -->
        <div class="sidebar-app-nav">
            <a href="/app/dashboard.php" class="sidebar-link">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
                <span>Dashboard</span>
            </a>
        </div>

        <div class="sidebar-divider"></div>

        <!-- Event navigation groups -->
        <?php foreach ($sidebarGroups as $groupKey => $group): ?>
        <div class="sidebar-group" data-group="<?= $groupKey ?>">
            <button class="sidebar-group-header" aria-expanded="true" onclick="toggleSidebarGroup(this)">
                <span class="sidebar-group-icon"><?= $group['icon'] ?></span>
                <span class="sidebar-group-label"><?= $group['label'] ?></span>
                <svg class="sidebar-group-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </button>
            <div class="sidebar-group-items">
                <?php foreach ($group['items'] as $item):
                    $isActive = ($page === $item['page'] || in_array($page, $item['also_active']));
                    $isPremium = !empty($item['premium']);
                    $classes = 'sidebar-link';
                    if ($isActive) $classes .= ' active';
                    if ($isPremium) $classes .= ' premium';
                ?>
                <a href="?id=<?= $eventId ?>&page=<?= $item['page'] ?>" class="<?= $classes ?>">
                    <span><?= $item['label'] ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Settings direct link -->
        <div class="sidebar-divider"></div>
        <a href="?id=<?= $eventId ?>&page=settings" class="sidebar-link sidebar-settings-link<?= $page === 'settings' ? ' active' : '' ?>">
            <?= $settingsIcon ?>
            <span>Indstillinger</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-plan-badge plan-<?= htmlspecialchars($planSlug) ?>">
            <?= $planName ?>
        </div>
        <?php if ($isFreePlan): ?>
        <a href="/app/account/billing.php" class="sidebar-upgrade-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
            </svg>
            <span>Opgrader</span>
        </a>
        <?php endif; ?>
    </div>
</aside>

<!-- ============================================== -->
<!-- B. Mobile Bottom Bar                           -->
<!-- ============================================== -->
<nav class="mobile-bottom-bar" id="mobileBottomBar">
    <?php foreach ($sidebarGroups as $groupKey => $group): ?>
    <button class="bottom-bar-item<?= $activeGroup === $groupKey ? ' active' : '' ?>" data-group="<?= $groupKey ?>" onclick="toggleBottomSheet('<?= $groupKey ?>')">
        <?= $group['icon'] ?>
        <span><?= $group['short_label'] ?></span>
    </button>
    <?php endforeach; ?>
    <button class="bottom-bar-item<?= $activeGroup === 'settings' ? ' active' : '' ?>" data-group="settings" onclick="window.location.href='?id=<?= $eventId ?>&page=settings'">
        <?= $settingsIcon ?>
        <span>Indst.</span>
    </button>
</nav>

<!-- ============================================== -->
<!-- C. Bottom Sheet                                -->
<!-- ============================================== -->
<div class="bottom-sheet" id="bottomSheet">
    <div class="bottom-sheet-backdrop" onclick="closeBottomSheet()"></div>
    <div class="bottom-sheet-content">
        <div class="bottom-sheet-handle"></div>

        <?php foreach ($sidebarGroups as $groupKey => $group): ?>
        <div class="bottom-sheet-group" data-sheet-group="<?= $groupKey ?>" style="display:none">
            <h3 class="bottom-sheet-title"><?= $group['label'] ?></h3>
            <?php foreach ($group['items'] as $item):
                $isActive = ($page === $item['page'] || in_array($page, $item['also_active']));
                $isPremium = !empty($item['premium']);
                $classes = 'bottom-sheet-link';
                if ($isActive) $classes .= ' active';
                if ($isPremium) $classes .= ' premium';
            ?>
            <a href="?id=<?= $eventId ?>&page=<?= $item['page'] ?>" class="<?= $classes ?>">
                <span><?= $item['label'] ?></span>
                <?php if ($isPremium): ?>
                <span class="pro-badge">PRO</span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

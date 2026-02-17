# Menu Restructuring Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace the 14-tab horizontal navigation in event management with a grouped sidebar (desktop) and bottom-bar + slide-up sheet (mobile).

**Architecture:** The event management page (`manage.php`) is a standalone PHP page with its own full HTML document (not using `app-header.php`). We'll restructure its layout from top-nav + tab-nav + content to sidebar + top-header + content. The sidebar contains both app-level navigation (dashboard, events) and grouped event navigation. On mobile (<1024px), the sidebar is replaced by a sticky bottom-bar with 5 group icons that open a bottom-sheet with sub-items.

**Tech Stack:** PHP 7.4+ (procedural), vanilla JS, custom CSS, no frameworks.

---

## Task 1: Add event sidebar component

Create a new PHP include file that renders the grouped event sidebar navigation.

**Files:**
- Create: `includes/event-sidebar.php`

**Step 1: Create the sidebar include file**

This file expects these variables to be available from the parent scope:
- `$eventId` (int) - current event ID
- `$event` (array) - event data with `name`, `slug`, `event_type_name`
- `$page` (string) - current page identifier
- `$subscription` (array) - subscription data with `plan_name`, `plan_slug`
- `$hasChecklist`, `$hasBudget`, `$hasSeating`, `$hasToastmaster` (bool) - feature flags

```php
<?php
/**
 * Event Sidebar Navigation
 * Grouped navigation for event management pages.
 *
 * Expected variables from parent scope:
 * $eventId, $event, $page, $subscription,
 * $hasChecklist, $hasBudget, $hasSeating, $hasToastmaster
 */

// Navigation group definitions
$navGroups = [
    'planning' => [
        'label' => 'Planlægning',
        'icon' => '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>',
        'items' => [
            ['page' => 'dashboard', 'label' => 'Oversigt', 'premium' => false],
            ['page' => 'checklist', 'label' => 'Tjekliste', 'premium' => !$hasChecklist],
            ['page' => 'schedule', 'label' => 'Program', 'premium' => false, 'also_active' => ['menu']],
            ['page' => 'budget', 'label' => 'Budget', 'premium' => !$hasBudget],
            ['page' => 'seating', 'label' => 'Bordplan', 'premium' => !$hasSeating],
        ],
    ],
    'guests' => [
        'label' => 'Gæster',
        'icon' => '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>',
        'items' => [
            ['page' => 'guests', 'label' => 'Gæsteliste', 'premium' => false],
            ['page' => 'invitation', 'label' => 'Invitation', 'premium' => false, 'also_active' => ['invitation-send']],
            ['page' => 'invitation-send', 'label' => 'Send invitation', 'premium' => false],
            ['page' => 'qr-bordkort', 'label' => 'QR-Bordkort', 'premium' => false],
        ],
    ],
    'content' => [
        'label' => 'Indhold',
        'icon' => '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/></svg>',
        'items' => [
            ['page' => 'wishlist', 'label' => 'Ønskeliste', 'premium' => false],
            ['page' => 'photos', 'label' => 'Fotos', 'premium' => false],
            ['page' => 'memories-admin', 'label' => 'Minder', 'premium' => false],
        ],
    ],
    'extra' => [
        'label' => 'Ekstra',
        'icon' => '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>',
        'items' => [
            ['page' => 'toastmaster', 'label' => 'Toastmaster', 'premium' => !$hasToastmaster],
            ['page' => 'vendors', 'label' => 'Leverandører', 'premium' => false, 'also_active' => ['vendor-booking']],
        ],
    ],
];

// Determine which group is active based on current page
$activeGroup = 'planning';
foreach ($navGroups as $groupKey => $group) {
    foreach ($group['items'] as $item) {
        $pages = array_merge([$item['page']], $item['also_active'] ?? []);
        if (in_array($page, $pages)) {
            $activeGroup = $groupKey;
            break 2;
        }
    }
}
if ($page === 'settings') {
    $activeGroup = 'settings';
}
?>

<!-- Event Sidebar (Desktop) -->
<aside class="event-sidebar" id="eventSidebar">
    <div class="event-sidebar-header">
        <a href="/app/dashboard.php" class="sidebar-logo">Party<span>Parart</span></a>
    </div>

    <nav class="event-sidebar-nav">
        <!-- App-level navigation -->
        <div class="sidebar-section">
            <div class="sidebar-section-title">Oversigt</div>
            <a href="/app/dashboard.php" class="sidebar-link">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                Dashboard
            </a>
        </div>

        <!-- Divider between app-nav and event-nav -->
        <div class="sidebar-divider"></div>

        <!-- Event navigation groups -->
        <?php foreach ($navGroups as $groupKey => $group): ?>
        <div class="sidebar-group" data-group="<?= $groupKey ?>">
            <button type="button" class="sidebar-group-header" onclick="toggleSidebarGroup(this)" aria-expanded="true">
                <span class="sidebar-group-icon"><?= $group['icon'] ?></span>
                <span class="sidebar-group-label"><?= $group['label'] ?></span>
                <svg class="sidebar-group-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div class="sidebar-group-items">
                <?php foreach ($group['items'] as $item):
                    $itemPages = array_merge([$item['page']], $item['also_active'] ?? []);
                    $isActive = in_array($page, $itemPages);
                ?>
                <a href="?id=<?= $eventId ?>&page=<?= $item['page'] ?>"
                   class="sidebar-link <?= $isActive ? 'active' : '' ?> <?= $item['premium'] ? 'premium' : '' ?>">
                    <?= htmlspecialchars($item['label']) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Settings (direct link, no group) -->
        <div class="sidebar-section sidebar-settings">
            <a href="?id=<?= $eventId ?>&page=settings"
               class="sidebar-link <?= $page === 'settings' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Indstillinger
            </a>
        </div>
    </nav>

    <div class="event-sidebar-footer">
        <div class="plan-badge">
            <div class="plan-info">
                <span class="plan-label">Din plan</span>
                <span class="plan-name"><?= htmlspecialchars($subscription['plan_name'] ?? 'Gratis') ?></span>
            </div>
        </div>
        <?php if (($subscription['plan_slug'] ?? 'free') === 'free'): ?>
        <a href="/app/account/subscription.php" class="upgrade-btn">Opgrader nu</a>
        <?php endif; ?>
    </div>
</aside>

<!-- Mobile Bottom Bar -->
<nav class="mobile-bottom-bar" id="mobileBottomBar">
    <?php
    $bottomBarGroups = [
        'planning' => ['label' => 'Plan', 'icon' => $navGroups['planning']['icon']],
        'guests'   => ['label' => 'Gæster', 'icon' => $navGroups['guests']['icon']],
        'content'  => ['label' => 'Indhold', 'icon' => $navGroups['content']['icon']],
        'extra'    => ['label' => 'Ekstra', 'icon' => $navGroups['extra']['icon']],
        'settings' => ['label' => 'Indst.', 'icon' => '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>'],
    ];
    foreach ($bottomBarGroups as $groupKey => $bbGroup): ?>
    <button type="button"
            class="bottom-bar-item <?= $activeGroup === $groupKey ? 'active' : '' ?>"
            data-group="<?= $groupKey ?>"
            onclick="<?= $groupKey === 'settings' ? "window.location='?id={$eventId}&page=settings'" : "toggleBottomSheet('{$groupKey}')" ?>">
        <span class="bottom-bar-icon"><?= $bbGroup['icon'] ?></span>
        <span class="bottom-bar-label"><?= $bbGroup['label'] ?></span>
    </button>
    <?php endforeach; ?>
</nav>

<!-- Mobile Bottom Sheet -->
<div class="bottom-sheet-backdrop" id="bottomSheetBackdrop" onclick="closeBottomSheet()"></div>
<div class="bottom-sheet" id="bottomSheet">
    <div class="bottom-sheet-handle" ontouchstart="initSheetDrag(event)"></div>
    <?php foreach ($navGroups as $groupKey => $group): ?>
    <div class="bottom-sheet-group" data-sheet-group="<?= $groupKey ?>" style="display: none;">
        <div class="bottom-sheet-title"><?= $group['label'] ?></div>
        <?php foreach ($group['items'] as $item):
            $itemPages = array_merge([$item['page']], $item['also_active'] ?? []);
            $isActive = in_array($page, $itemPages);
        ?>
        <a href="?id=<?= $eventId ?>&page=<?= $item['page'] ?>"
           class="bottom-sheet-link <?= $isActive ? 'active' : '' ?> <?= $item['premium'] ? 'premium' : '' ?>">
            <?= htmlspecialchars($item['label']) ?>
            <?php if ($item['premium']): ?>
            <span class="pro-badge">PRO</span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
</div>
```

**Step 2: Verify file was created**

Run: `ls -la includes/event-sidebar.php`
Expected: File exists with reasonable size (~6-8KB)

**Step 3: Commit**

```bash
git add includes/event-sidebar.php
git commit -m "feat: add grouped event sidebar navigation component"
```

---

## Task 2: Add CSS for sidebar, bottom-bar, and bottom-sheet

Add all new CSS styles to `manage.php`. This replaces the existing tab navigation CSS and adds sidebar + mobile navigation styles.

**Files:**
- Modify: `app/events/manage.php:243-306` (replace tab CSS with sidebar + mobile CSS)

**Step 1: Replace tab CSS with sidebar + mobile CSS**

In `manage.php`, replace the entire CSS block from `/* Tab Navigation */` (line 243) through the `.tab-link.premium::after` closing brace (line 306) with the new sidebar, bottom-bar, and bottom-sheet styles.

Replace lines 243-306 (the `/* Tab Navigation */` section) with:

```css
/* Event Sidebar */
.event-sidebar {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    width: 280px;
    background: var(--white);
    border-right: 1px solid var(--cream-dark);
    display: flex;
    flex-direction: column;
    z-index: 150;
    overflow: hidden;
}

.event-sidebar-header {
    padding: 28px 24px;
    border-bottom: 1px solid var(--cream-dark);
}

.event-sidebar-header .sidebar-logo {
    font-family: var(--font-display);
    font-size: 22px;
    font-weight: 600;
    color: var(--charcoal);
    text-decoration: none;
}

.event-sidebar-header .sidebar-logo span {
    color: var(--sage);
}

.event-sidebar-nav {
    flex: 1;
    overflow-y: auto;
    padding: 16px 12px;
}

.event-sidebar-nav::-webkit-scrollbar {
    width: 4px;
}

.event-sidebar-nav::-webkit-scrollbar-thumb {
    background: var(--cream-dark);
    border-radius: 2px;
}

/* App-level sidebar section */
.sidebar-section {
    margin-bottom: 8px;
}

.sidebar-section-title,
.sidebar-group-header {
    font-family: var(--font-body);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--charcoal-light);
    padding: 8px 12px 4px;
}

.sidebar-divider {
    height: 1px;
    background: var(--cream-dark);
    margin: 12px 0;
}

/* Sidebar links */
.sidebar-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    color: var(--charcoal-light);
    text-decoration: none;
    font-size: 14px;
    font-weight: 400;
    border-radius: 10px;
    transition: all 0.2s var(--ease-out);
}

.sidebar-link:hover {
    background: var(--cream-light);
    color: var(--charcoal);
}

.sidebar-link.active {
    background: var(--sage);
    color: var(--white);
    font-weight: 500;
}

.sidebar-link svg {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
}

.sidebar-link.premium {
    color: #B8B0A0;
}

.sidebar-link.premium::after {
    content: 'PRO';
    font-size: 9px;
    font-weight: 700;
    padding: 2px 5px;
    background: var(--gold);
    color: var(--white);
    border-radius: 4px;
    margin-left: auto;
}

.sidebar-link.premium.active {
    color: var(--white);
}

.sidebar-link.premium.active::after {
    background: rgba(255,255,255,0.3);
}

/* Sidebar groups */
.sidebar-group {
    margin-bottom: 4px;
}

.sidebar-group-header {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 10px 12px 6px;
    border: none;
    background: none;
    cursor: pointer;
    font-family: var(--font-body);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--charcoal-light);
}

.sidebar-group-header:hover {
    color: var(--charcoal);
}

.sidebar-group-icon svg {
    width: 16px;
    height: 16px;
}

.sidebar-group-label {
    flex: 1;
    text-align: left;
}

.sidebar-group-chevron {
    width: 14px;
    height: 14px;
    transition: transform 0.2s var(--ease-out);
}

.sidebar-group-header[aria-expanded="false"] .sidebar-group-chevron {
    transform: rotate(-90deg);
}

.sidebar-group-items {
    overflow: hidden;
    transition: max-height 0.25s var(--ease-out);
}

.sidebar-group-header[aria-expanded="false"] + .sidebar-group-items {
    max-height: 0 !important;
}

.sidebar-group-items .sidebar-link {
    padding-left: 40px;
}

/* Sidebar footer */
.event-sidebar-footer {
    padding: 16px;
    border-top: 1px solid var(--cream-dark);
}

.event-sidebar-footer .plan-badge {
    display: flex;
    align-items: center;
    padding: 12px;
    background: var(--cream-light);
    border-radius: 12px;
    margin-bottom: 8px;
}

.event-sidebar-footer .plan-info {
    display: flex;
    flex-direction: column;
}

.event-sidebar-footer .plan-label {
    font-size: 11px;
    color: var(--charcoal-light);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.event-sidebar-footer .plan-name {
    font-size: 14px;
    font-weight: 600;
    color: var(--charcoal);
}

.event-sidebar-footer .upgrade-btn {
    display: block;
    text-align: center;
    padding: 10px;
    background: var(--sage);
    color: var(--white);
    text-decoration: none;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 500;
    transition: background 0.2s;
}

.event-sidebar-footer .upgrade-btn:hover {
    background: var(--sage-dark);
}

.sidebar-settings {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid var(--cream-dark);
}

/* Layout adjustments for sidebar */
.top-nav {
    margin-left: 280px;
}

.main-content {
    margin-left: 280px;
}

/* Mobile hamburger for sidebar on manage page */
.event-menu-toggle {
    display: none;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border: none;
    background: none;
    cursor: pointer;
    color: var(--charcoal);
    border-radius: 10px;
    transition: background 0.2s;
}

.event-menu-toggle:hover {
    background: var(--cream);
}

.event-menu-toggle svg {
    width: 22px;
    height: 22px;
}

/* Mobile Bottom Bar */
.mobile-bottom-bar {
    display: none;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: var(--white);
    border-top: 1px solid var(--cream-dark);
    z-index: 200;
    justify-content: space-around;
    padding: 6px 0 env(safe-area-inset-bottom, 6px);
}

.bottom-bar-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
    padding: 8px 12px;
    border: none;
    background: none;
    cursor: pointer;
    color: var(--charcoal-light);
    transition: color 0.2s;
    -webkit-tap-highlight-color: transparent;
}

.bottom-bar-item.active {
    color: var(--sage-dark);
}

.bottom-bar-icon svg {
    width: 22px;
    height: 22px;
}

.bottom-bar-label {
    font-family: var(--font-body);
    font-size: 10px;
    font-weight: 500;
}

/* Bottom Sheet */
.bottom-sheet-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.3);
    z-index: 250;
    opacity: 0;
    transition: opacity 0.3s;
}

.bottom-sheet-backdrop.visible {
    opacity: 1;
}

.bottom-sheet {
    display: none;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: var(--white);
    border-radius: 20px 20px 0 0;
    z-index: 300;
    padding: 0 20px 20px;
    transform: translateY(100%);
    transition: transform 0.3s var(--ease-out);
    max-height: 60vh;
    overflow-y: auto;
    padding-bottom: calc(20px + env(safe-area-inset-bottom, 0px));
}

.bottom-sheet.open {
    transform: translateY(0);
}

.bottom-sheet-handle {
    width: 36px;
    height: 4px;
    background: var(--cream-dark);
    border-radius: 2px;
    margin: 12px auto 16px;
    cursor: grab;
}

.bottom-sheet-title {
    font-family: var(--font-body);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--charcoal-light);
    padding: 4px 0 8px;
}

.bottom-sheet-link {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 12px;
    color: var(--charcoal);
    text-decoration: none;
    font-size: 16px;
    border-radius: 12px;
    transition: background 0.15s;
    min-height: 48px;
}

.bottom-sheet-link:hover,
.bottom-sheet-link:active {
    background: var(--cream-light);
}

.bottom-sheet-link.active {
    background: var(--sage);
    color: var(--white);
    font-weight: 500;
}

.bottom-sheet-link.premium {
    color: #B8B0A0;
}

.bottom-sheet-link.premium.active {
    color: var(--white);
}

.pro-badge {
    font-size: 9px;
    font-weight: 700;
    padding: 2px 6px;
    background: var(--gold);
    color: var(--white);
    border-radius: 4px;
}

.bottom-sheet-link.active .pro-badge {
    background: rgba(255,255,255,0.3);
}
```

**Step 2: Update the responsive media query**

In `manage.php`, modify the existing `@media (max-width: 768px)` block (lines 713-740) to also handle sidebar/bottom-bar at the 1024px breakpoint.

Add this new media query **before** the existing 768px one (insert at line 713):

```css
@media (max-width: 1024px) {
    .event-sidebar {
        display: none;
    }

    .top-nav {
        margin-left: 0;
    }

    .main-content {
        margin-left: 0;
        padding-bottom: 80px;
    }

    .event-menu-toggle {
        display: flex;
    }

    .mobile-bottom-bar {
        display: flex;
    }

    .bottom-sheet {
        display: block;
    }
}
```

**Step 3: Verify styles are syntactically correct**

Open `manage.php` in a browser and check the developer console for CSS parse errors.

**Step 4: Commit**

```bash
git add app/events/manage.php
git commit -m "style: add CSS for grouped sidebar, bottom-bar, and bottom-sheet"
```

---

## Task 3: Restructure manage.php HTML layout

Replace the tab navigation HTML with the sidebar include, and add the mobile hamburger button.

**Files:**
- Modify: `app/events/manage.php:744-859` (replace top-nav + tabs with sidebar + header)

**Step 1: Add sidebar include before the top-nav**

In `manage.php`, insert the sidebar include right after `<body>` (line 743). Insert before line 744:

```php
<?php include __DIR__ . '/../../includes/event-sidebar.php'; ?>
```

**Step 2: Add hamburger button to top-nav**

In `manage.php`, in the `.nav-left` div (around line 747), add a mobile hamburger button as the first child:

```html
<button class="event-menu-toggle" onclick="document.getElementById('eventSidebar').classList.toggle('open')">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
    </svg>
</button>
```

**Step 3: Remove the entire tab navigation**

Delete lines 770-859 (the `<!-- Tab Navigation -->` nav element and all its contents).

**Step 4: Handle menu page redirect**

In `manage.php` around line 44-47, add a redirect for the deprecated `menu` page. After the `$page` validation:

```php
// Redirect deprecated menu page to schedule
if ($page === 'menu') {
    header("Location: ?id=$eventId&page=schedule&section=menu");
    exit;
}
```

Also keep 'menu' in `$validPages` for backward compatibility, but the redirect will catch it before it reaches the include.

**Step 5: Verify the page loads correctly**

Open `/app/events/manage.php?id=1` in a browser. Expected:
- Sidebar visible on left with grouped navigation
- No tab bar visible
- Content area shifted right by 280px
- On mobile viewport (< 1024px): sidebar hidden, bottom-bar visible

**Step 6: Commit**

```bash
git add app/events/manage.php includes/event-sidebar.php
git commit -m "feat: replace tab navigation with grouped sidebar layout"
```

---

## Task 4: Add JavaScript for sidebar and mobile interactions

Add the JavaScript for sidebar group toggling, bottom-sheet interactions, and mobile menu.

**Files:**
- Modify: `app/events/manage.php` (add script before closing `</body>`)

**Step 1: Add the navigation JavaScript**

In `manage.php`, add this script block before the closing `</body>` tag (line 890):

```html
<script>
// Sidebar group collapse/expand
function toggleSidebarGroup(btn) {
    var expanded = btn.getAttribute('aria-expanded') === 'true';
    btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
    var items = btn.nextElementSibling;
    if (expanded) {
        items.style.maxHeight = '0';
    } else {
        items.style.maxHeight = items.scrollHeight + 'px';
    }
}

// Set initial max-height for all group items (so they're visible by default)
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.sidebar-group-items').forEach(function(el) {
        el.style.maxHeight = el.scrollHeight + 'px';
    });
});

// Bottom sheet
var activeSheet = null;

function toggleBottomSheet(groupKey) {
    var backdrop = document.getElementById('bottomSheetBackdrop');
    var sheet = document.getElementById('bottomSheet');

    // If tapping same group, close
    if (activeSheet === groupKey) {
        closeBottomSheet();
        return;
    }

    // Hide all sheet groups, show the selected one
    document.querySelectorAll('.bottom-sheet-group').forEach(function(el) {
        el.style.display = 'none';
    });
    var target = document.querySelector('[data-sheet-group="' + groupKey + '"]');
    if (target) target.style.display = 'block';

    // Show backdrop and sheet
    backdrop.style.display = 'block';
    sheet.classList.add('open');
    requestAnimationFrame(function() {
        backdrop.classList.add('visible');
    });

    activeSheet = groupKey;
}

function closeBottomSheet() {
    var backdrop = document.getElementById('bottomSheetBackdrop');
    var sheet = document.getElementById('bottomSheet');

    backdrop.classList.remove('visible');
    sheet.classList.remove('open');

    setTimeout(function() {
        backdrop.style.display = 'none';
    }, 300);

    activeSheet = null;
}

// Swipe-down to close bottom sheet
var sheetStartY = 0;
function initSheetDrag(e) {
    sheetStartY = e.touches[0].clientY;
    var sheet = document.getElementById('bottomSheet');
    sheet.addEventListener('touchmove', onSheetDrag, { passive: false });
    sheet.addEventListener('touchend', onSheetDragEnd);
}

function onSheetDrag(e) {
    var deltaY = e.touches[0].clientY - sheetStartY;
    if (deltaY > 0) {
        e.preventDefault();
        document.getElementById('bottomSheet').style.transform = 'translateY(' + deltaY + 'px)';
    }
}

function onSheetDragEnd(e) {
    var sheet = document.getElementById('bottomSheet');
    var deltaY = e.changedTouches[0].clientY - sheetStartY;
    sheet.removeEventListener('touchmove', onSheetDrag);
    sheet.removeEventListener('touchend', onSheetDragEnd);
    sheet.style.transform = '';

    if (deltaY > 80) {
        closeBottomSheet();
    } else {
        sheet.classList.add('open');
    }
}

// Mobile sidebar overlay
document.addEventListener('click', function(e) {
    var sidebar = document.getElementById('eventSidebar');
    var toggle = document.querySelector('.event-menu-toggle');
    if (sidebar && sidebar.classList.contains('open') &&
        !sidebar.contains(e.target) && !toggle.contains(e.target)) {
        sidebar.classList.remove('open');
    }
});
</script>
```

**Step 2: Add mobile sidebar CSS for slide-in behavior**

Inside the `@media (max-width: 1024px)` block added in Task 2, update `.event-sidebar` to support slide-in on mobile:

```css
.event-sidebar {
    display: flex;
    transform: translateX(-100%);
    transition: transform 0.3s var(--ease-out);
    box-shadow: none;
}

.event-sidebar.open {
    transform: translateX(0);
    box-shadow: 4px 0 24px rgba(0,0,0,0.1);
}
```

Note: This replaces the `display: none` from Task 2's media query with a slide-in/out pattern, so the hamburger menu works on mobile too.

**Step 3: Test interactions**

1. Desktop: Click group headers to collapse/expand groups
2. Mobile (resize to <1024px): Verify bottom-bar shows, tap groups to open sheet
3. Mobile: Swipe down on sheet to close
4. Mobile: Tap backdrop to close sheet

**Step 4: Commit**

```bash
git add app/events/manage.php
git commit -m "feat: add JS for sidebar toggling, bottom-sheet, and mobile interactions"
```

---

## Task 5: Integrate Menu into Schedule page

Move the menu (food/drink) functionality into the schedule page as a tabbed section.

**Files:**
- Modify: `app/events/pages/schedule.php`

**Step 1: Add menu data loading and POST handling**

At the top of `schedule.php` (line 1), the file already handles schedule POST actions. Add menu POST handling after the schedule actions (after line 47, before the schedule query on line 50).

Add this block between the closing `}` of the POST handler and the schedule query:

```php
// Handle menu actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add_menu_item') {
        if (!verifyAccountCsrfToken($_POST['csrf_token'] ?? '')) {
            setFlash('error', 'Ugyldig anmodning.');
            redirect("?id=$eventId&page=schedule&section=menu");
        }
        $course = $_POST['course'] ?? 'main';
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $stmt = $db->prepare("SELECT MAX(sort_order) as max_order FROM menu_items WHERE event_id = ? AND course = ?");
        $stmt->execute([$eventId, $course]);
        $maxOrder = ($stmt->fetch()['max_order'] ?? 0) + 1;
        if ($title) {
            $stmt = $db->prepare("INSERT INTO menu_items (event_id, course, title, description, sort_order) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$eventId, $course, $title, $description ?: null, $maxOrder]);
            setFlash('success', 'Menupunkt tilføjet.');
        }
        redirect("?id=$eventId&page=schedule&section=menu");

    } elseif ($action === 'delete_menu_item') {
        if (!verifyAccountCsrfToken($_POST['csrf_token'] ?? '')) {
            setFlash('error', 'Ugyldig anmodning.');
            redirect("?id=$eventId&page=schedule&section=menu");
        }
        $itemId = (int)($_POST['item_id'] ?? 0);
        if ($itemId) {
            $stmt = $db->prepare("DELETE FROM menu_items WHERE id = ? AND event_id = ?");
            $stmt->execute([$itemId, $eventId]);
            setFlash('success', 'Menupunkt slettet.');
        }
        redirect("?id=$eventId&page=schedule&section=menu");

    } elseif ($action === 'update_menu_item') {
        if (!verifyAccountCsrfToken($_POST['csrf_token'] ?? '')) {
            setFlash('error', 'Ugyldig anmodning.');
            redirect("?id=$eventId&page=schedule&section=menu");
        }
        $itemId = (int)($_POST['item_id'] ?? 0);
        $course = $_POST['course'] ?? 'main';
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if ($itemId && $title) {
            $stmt = $db->prepare("UPDATE menu_items SET course = ?, title = ?, description = ? WHERE id = ? AND event_id = ?");
            $stmt->execute([$course, $title, $description ?: null, $itemId, $eventId]);
            setFlash('success', 'Menupunkt opdateret.');
        }
        redirect("?id=$eventId&page=schedule&section=menu");
    }
}
```

**Important:** The schedule page already has a POST handler at lines 6-47 that checks `$_SERVER['REQUEST_METHOD'] === 'POST'`. The menu actions use different action names (`add_menu_item`, `delete_menu_item`, `update_menu_item`) so they won't conflict with the schedule actions (`add_item`, `delete_item`, `update_item`). However, the existing POST handler structure needs to be adjusted so both schedule and menu actions are handled. The simplest approach: add the menu actions to the **existing** `if ($action === ...)` chain as additional `elseif` branches.

After the schedule data query, add menu data loading:

```php
// Load menu data
$courses = ['starter' => 'Forret', 'main' => 'Hovedret', 'dessert' => 'Dessert', 'drinks' => 'Drikkevarer', 'snacks' => 'Snacks'];
$menuItems = [];
foreach ($courses as $key => $label) {
    $stmt = $db->prepare("SELECT * FROM menu_items WHERE event_id = ? AND course = ? ORDER BY sort_order ASC");
    $stmt->execute([$eventId, $key]);
    $menuItems[$key] = $stmt->fetchAll();
}
$menuCount = array_sum(array_map('count', $menuItems));

// Determine active section
$section = $_GET['section'] ?? 'schedule';
```

**Step 2: Add section tabs to the page header**

Replace the existing page header (lines 55-66) with a tabbed header:

```php
<div class="page-header-actions">
    <div>
        <h2 class="section-title">Program & Menu</h2>
        <p class="section-subtitle"><?= count($items) ?> programpunkter · <?= $menuCount ?> retter</p>
    </div>
</div>

<!-- Section tabs -->
<div class="section-tabs">
    <a href="?id=<?= $eventId ?>&page=schedule&section=schedule"
       class="section-tab <?= $section !== 'menu' ? 'active' : '' ?>">
        Program (<?= count($items) ?>)
    </a>
    <a href="?id=<?= $eventId ?>&page=schedule&section=menu"
       class="section-tab <?= $section === 'menu' ? 'active' : '' ?>">
        Menu (<?= $menuCount ?>)
    </a>
</div>
```

**Step 3: Wrap existing schedule content in a conditional**

Wrap the existing schedule display (the empty state + timeline, currently lines 68-103) in:

```php
<?php if ($section !== 'menu'): ?>
    <!-- existing schedule content here -->
    <!-- ... plus schedule modals and JS ... -->
<?php else: ?>
    <!-- Menu content (copied from menu.php, adapted) -->
    <!-- ... menu courses display, modals, JS ... -->
<?php endif; ?>
```

The menu section content is essentially the HTML from `pages/menu.php` lines 66-223 (the display, modals, and JS), but with:
- Action values changed: `add_item` → `add_menu_item`, `delete_item` → `delete_menu_item`, `update_item` → `update_menu_item`
- All redirect URLs changed from `page=menu` to `page=schedule&section=menu`
- The add button text remains "Tilføj ret"

**Step 4: Add section tab CSS**

Add to the `<style>` block in schedule.php (lines 174-183):

```css
.section-tabs {
    display: flex;
    gap: 4px;
    margin-bottom: 24px;
    background: var(--cream-light);
    border-radius: 12px;
    padding: 4px;
}
.section-tab {
    flex: 1;
    text-align: center;
    padding: 10px 16px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 500;
    color: var(--charcoal-light);
    text-decoration: none;
    transition: all 0.2s;
}
.section-tab.active {
    background: var(--white);
    color: var(--charcoal);
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}
.section-tab:hover:not(.active) {
    color: var(--charcoal);
}
```

**Step 5: Verify**

1. Open `/app/events/manage.php?id=1&page=schedule` - should show Program tab active
2. Click "Menu" tab - should show menu courses
3. Add/edit/delete menu items - should work and stay on menu section
4. Add/edit/delete schedule items - should work and stay on schedule section

**Step 6: Commit**

```bash
git add app/events/pages/schedule.php
git commit -m "feat: integrate menu management into schedule page as tabbed section"
```

---

## Task 6: Keep menu.php as redirect fallback

Instead of deleting `menu.php`, convert it to a simple redirect. This handles any bookmarks or cached URLs.

**Files:**
- Modify: `app/events/pages/menu.php`

**Step 1: Replace menu.php contents with redirect**

Replace the entire file content with:

```php
<?php
/**
 * Menu page - redirected to schedule page with menu section.
 * Kept for backward compatibility with bookmarks/cached URLs.
 */
header("Location: ?id=$eventId&page=schedule&section=menu");
exit;
```

Note: `$eventId` is available from the parent scope (manage.php sets it before including the page file).

**Step 2: Verify redirect**

Open `/app/events/manage.php?id=1&page=menu` - should redirect to `?id=1&page=schedule&section=menu`

**Step 3: Commit**

```bash
git add app/events/pages/menu.php
git commit -m "refactor: convert menu.php to redirect to schedule page"
```

---

## Task 7: Visual polish and cross-browser testing

Final adjustments for visual consistency and edge cases.

**Files:**
- Modify: `app/events/manage.php` (minor CSS tweaks)

**Step 1: Ensure the active event is highlighted in sidebar**

The sidebar's "Dashboard" link goes to `/app/dashboard.php`. Verify there's no broken active state when on the manage page. The sidebar-link for Dashboard should NOT be active when viewing an event (it's a different page).

**Step 2: Test all navigation paths**

Manually verify each sidebar link navigates correctly:
- [ ] Planlægning: Oversigt, Tjekliste, Program, Budget, Bordplan
- [ ] Gæster: Gæsteliste, Invitation, Send invitation, QR-Bordkort
- [ ] Indhold: Ønskeliste, Fotos, Minder
- [ ] Ekstra: Toastmaster, Leverandører
- [ ] Indstillinger
- [ ] Dashboard (back to app)
- [ ] Plan badge and upgrade button

**Step 3: Test mobile**

- [ ] Bottom bar shows 5 icons
- [ ] Tapping each group opens correct sheet
- [ ] Sheet links navigate correctly
- [ ] Swipe-down closes sheet
- [ ] Backdrop tap closes sheet
- [ ] Active group/item highlighted correctly
- [ ] Hamburger opens sidebar overlay
- [ ] Tapping outside sidebar closes it

**Step 4: Test premium badges**

- On Free plan: PRO badges should show on Tjekliste, Budget, Bordplan, Toastmaster
- On Premium plan: No PRO badges should show

**Step 5: Commit any fixes**

```bash
git add -A
git commit -m "fix: visual polish and cross-browser fixes for sidebar navigation"
```

---

## Summary of all files changed

| File | Action | Description |
|------|--------|-------------|
| `includes/event-sidebar.php` | Create | New grouped sidebar + bottom-bar + bottom-sheet component |
| `app/events/manage.php` | Modify | Replace tab CSS/HTML with sidebar layout, add JS |
| `app/events/pages/schedule.php` | Modify | Add menu section as tab within schedule page |
| `app/events/pages/menu.php` | Modify | Convert to redirect (backward compat) |

**No database changes required.** URL structure remains the same with one addition: `&section=menu` parameter on the schedule page.

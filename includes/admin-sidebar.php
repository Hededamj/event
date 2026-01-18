<!-- Admin Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar__header">
        <div class="sidebar__brand">
            <span class="sidebar__brand-icon">✦</span>
            <div>
                <span class="sidebar__brand-name"><?= escape($event['confirmand_name']) ?></span>
                <span class="sidebar__brand-date"><?= formatDate($event['event_date']) ?></span>
            </div>
        </div>
        <button class="sidebar__close hide-desktop" onclick="toggleSidebar()" aria-label="Luk menu">
            &times;
        </button>
    </div>

    <!-- Days until event -->
    <div class="sidebar__countdown">
        <?php if ($isPast): ?>
            <span class="sidebar__countdown-label">Eventet er afholdt</span>
        <?php elseif ($daysUntil === 0): ?>
            <span class="sidebar__countdown-value">I dag!</span>
            <span class="sidebar__countdown-label">Det er dagen!</span>
        <?php else: ?>
            <span class="sidebar__countdown-value"><?= $daysUntil ?></span>
            <span class="sidebar__countdown-label">dage til festen</span>
        <?php endif; ?>
    </div>

    <nav class="sidebar__nav">
        <ul class="sidebar__menu">
            <li>
                <a href="/admin/index.php" class="sidebar__link <?= $currentPage === 'index' ? 'sidebar__link--active' : '' ?>">
                    <span class="sidebar__link-icon">📊</span>
                    <span>Overblik</span>
                </a>
            </li>
            <li>
                <a href="/admin/guests.php" class="sidebar__link <?= $currentPage === 'guests' ? 'sidebar__link--active' : '' ?>">
                    <span class="sidebar__link-icon">👥</span>
                    <span>Gæsteliste</span>
                    <?php if ($guestStats['pending'] > 0): ?>
                        <span class="sidebar__badge"><?= $guestStats['pending'] ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="/admin/checklist.php" class="sidebar__link <?= $currentPage === 'checklist' ? 'sidebar__link--active' : '' ?>">
                    <span class="sidebar__link-icon">✅</span>
                    <span>Huskeliste</span>
                </a>
            </li>
            <li>
                <a href="/admin/wishlist.php" class="sidebar__link <?= $currentPage === 'wishlist' ? 'sidebar__link--active' : '' ?>">
                    <span class="sidebar__link-icon">🎁</span>
                    <span>Ønskeliste</span>
                </a>
            </li>
            <li>
                <a href="/admin/menu.php" class="sidebar__link <?= $currentPage === 'menu' ? 'sidebar__link--active' : '' ?>">
                    <span class="sidebar__link-icon">🍽️</span>
                    <span>Menu</span>
                </a>
            </li>
            <li>
                <a href="/admin/schedule.php" class="sidebar__link <?= $currentPage === 'schedule' ? 'sidebar__link--active' : '' ?>">
                    <span class="sidebar__link-icon">🕐</span>
                    <span>Tidsplan</span>
                </a>
            </li>
            <li>
                <a href="/admin/photos.php" class="sidebar__link <?= $currentPage === 'photos' ? 'sidebar__link--active' : '' ?>">
                    <span class="sidebar__link-icon">📷</span>
                    <span>Billeder</span>
                </a>
            </li>
            <li>
                <a href="/admin/budget.php" class="sidebar__link <?= $currentPage === 'budget' ? 'sidebar__link--active' : '' ?>">
                    <span class="sidebar__link-icon">💰</span>
                    <span>Budget</span>
                </a>
            </li>
        </ul>

        <div class="sidebar__divider"></div>

        <ul class="sidebar__menu">
            <li>
                <a href="/admin/settings.php" class="sidebar__link <?= $currentPage === 'settings' ? 'sidebar__link--active' : '' ?>">
                    <span class="sidebar__link-icon">⚙️</span>
                    <span>Indstillinger</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="sidebar__footer">
        <div class="sidebar__user">
            <div class="sidebar__user-avatar">
                <?= strtoupper(substr($currentUser['name'], 0, 1)) ?>
            </div>
            <div class="sidebar__user-info">
                <span class="sidebar__user-name"><?= escape($currentUser['name']) ?></span>
                <span class="sidebar__user-role"><?= ucfirst($currentUser['role']) ?></span>
            </div>
        </div>
        <a href="/admin/logout.php" class="btn btn--ghost btn--block mt-sm">
            Log ud
        </a>
    </div>
</aside>

<!-- Mobile menu toggle -->
<button class="mobile-menu-toggle hide-desktop" onclick="toggleSidebar()" aria-label="Åbn menu">
    <span></span>
    <span></span>
    <span></span>
</button>

<!-- Main content wrapper starts -->
<main class="admin-main">
    <!-- Top bar for mobile -->
    <header class="admin-topbar hide-desktop">
        <button class="admin-topbar__menu" onclick="toggleSidebar()">☰</button>
        <span class="admin-topbar__title"><?= escape($event['confirmand_name']) ?></span>
        <a href="/" class="admin-topbar__preview" title="Se gæstevisning">👁</a>
    </header>

    <?php if ($flash): ?>
        <div class="alert alert--<?= escape($flash['type']) ?> mb-md animate-fade-in-up">
            <?= escape($flash['message']) ?>
        </div>
    <?php endif; ?>

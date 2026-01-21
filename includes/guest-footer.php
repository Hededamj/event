        </main>

        <script>
        function openModal(id) {
            document.getElementById(id).classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
            document.body.style.overflow = '';
        }
        function openOenskesky() {
            document.getElementById('oenskesky-modal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeOenskesky() {
            document.getElementById('oenskesky-modal').classList.remove('active');
            document.body.style.overflow = '';
        }
        </script>

        <?php if (!empty($event['oenskesky_url'])): ?>
        <!-- Ønskesky Modal -->
        <div id="oenskesky-modal" class="modal-overlay" onclick="if(event.target === this) closeOenskesky()">
            <div class="oenskesky-modal">
                <div class="oenskesky-modal__header">
                    <h3>Ønskeliste</h3>
                    <button onclick="closeOenskesky()" class="oenskesky-modal__close">&times;</button>
                </div>
                <div class="oenskesky-modal__body">
                    <iframe src="<?= escape($event['oenskesky_url']) ?>" frameborder="0"></iframe>
                </div>
                <div class="oenskesky-modal__footer">
                    <a href="<?= escape($event['oenskesky_url']) ?>" target="_blank" class="btn btn--secondary">
                        Åbn i nyt vindue ↗
                    </a>
                </div>
            </div>
        </div>

        <style>
        .oenskesky-modal {
            background: var(--white);
            border-radius: 16px;
            width: 100%;
            max-width: 600px;
            height: 85vh;
            max-height: 700px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .oenskesky-modal__header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--blush-light);
        }
        .oenskesky-modal__header h3 {
            font-family: 'Playfair Display', serif;
            font-size: 1.25rem;
            font-weight: 400;
            margin: 0;
        }
        .oenskesky-modal__close {
            background: none;
            border: none;
            font-size: 1.75rem;
            color: var(--ink-soft);
            cursor: pointer;
            padding: 0;
            line-height: 1;
        }
        .oenskesky-modal__close:hover {
            color: var(--ink);
        }
        .oenskesky-modal__body {
            flex: 1;
            overflow: hidden;
        }
        .oenskesky-modal__body iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
        .oenskesky-modal__footer {
            padding: 1rem 1.25rem;
            border-top: 1px solid var(--blush-light);
            text-align: center;
        }
        </style>
        <?php endif; ?>

        <!-- Bottom Navigation -->
        <nav class="guest-bottom-nav">
            <div class="guest-bottom-nav__inner">
                <a href="<?= BASE_PATH ?>/guest/index.php" class="guest-bottom-nav__link <?= $currentPage === 'index' ? 'guest-bottom-nav__link--active' : '' ?>">
                    <span class="guest-bottom-nav__icon">🏠</span>
                    <span>Hjem</span>
                </a>
                <?php if (!empty($event['oenskesky_url'])): ?>
                <button onclick="openOenskesky()" class="guest-bottom-nav__link">
                    <span class="guest-bottom-nav__icon">🌟</span>
                    <span>Ønskesky</span>
                </button>
                <?php endif; ?>
                <?php if ($event['show_wishlist'] ?? true): ?>
                <a href="<?= BASE_PATH ?>/guest/wishlist.php" class="guest-bottom-nav__link <?= $currentPage === 'wishlist' ? 'guest-bottom-nav__link--active' : '' ?>">
                    <span class="guest-bottom-nav__icon">🎁</span>
                    <span>Ønsker</span>
                </a>
                <?php endif; ?>
                <?php if ($event['show_menu'] ?? true): ?>
                <a href="<?= BASE_PATH ?>/guest/menu.php" class="guest-bottom-nav__link <?= $currentPage === 'menu' ? 'guest-bottom-nav__link--active' : '' ?>">
                    <span class="guest-bottom-nav__icon">🍽️</span>
                    <span>Menu</span>
                </a>
                <?php endif; ?>
                <?php if ($event['show_schedule'] ?? true): ?>
                <a href="<?= BASE_PATH ?>/guest/schedule.php" class="guest-bottom-nav__link <?= $currentPage === 'schedule' ? 'guest-bottom-nav__link--active' : '' ?>">
                    <span class="guest-bottom-nav__icon">🕐</span>
                    <span>Program</span>
                </a>
                <?php endif; ?>
                <?php if ($event['show_photos'] ?? true): ?>
                <a href="<?= BASE_PATH ?>/guest/photos.php" class="guest-bottom-nav__link <?= $currentPage === 'photos' ? 'guest-bottom-nav__link--active' : '' ?>">
                    <span class="guest-bottom-nav__icon">📷</span>
                    <span>Billeder</span>
                </a>
                <?php endif; ?>
            </div>
        </nav>
    </div>
</body>
</html>

        </main>

        <!-- Bottom Navigation -->
        <nav class="guest-bottom-nav">
            <div class="guest-bottom-nav__inner">
                <a href="/guest/index.php" class="guest-bottom-nav__link <?= $currentPage === 'index' ? 'guest-bottom-nav__link--active' : '' ?>">
                    <span class="guest-bottom-nav__icon">🏠</span>
                    <span>Hjem</span>
                </a>
                <a href="/guest/wishlist.php" class="guest-bottom-nav__link <?= $currentPage === 'wishlist' ? 'guest-bottom-nav__link--active' : '' ?>">
                    <span class="guest-bottom-nav__icon">🎁</span>
                    <span>Ønsker</span>
                </a>
                <a href="/guest/menu.php" class="guest-bottom-nav__link <?= $currentPage === 'menu' ? 'guest-bottom-nav__link--active' : '' ?>">
                    <span class="guest-bottom-nav__icon">🍽️</span>
                    <span>Menu</span>
                </a>
                <a href="/guest/schedule.php" class="guest-bottom-nav__link <?= $currentPage === 'schedule' ? 'guest-bottom-nav__link--active' : '' ?>">
                    <span class="guest-bottom-nav__icon">🕐</span>
                    <span>Program</span>
                </a>
            </div>
        </nav>

        <footer class="guest-footer" style="padding-bottom: 80px;">
            <p>Lavet med ❤️ til <?= escape($event['confirmand_name']) ?></p>
        </footer>
    </div>

    <script src="/assets/js/main.js"></script>

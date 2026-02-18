        </div>
    </main>

    <script>
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            var dropdown = document.querySelector('.user-dropdown');
            var menuBtn = document.querySelector('.user-menu-btn');
            if (dropdown && !menuBtn.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });

        // Mobile sidebar toggle
        var menuToggle = document.querySelector('.menu-toggle');
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebarOverlay');

        if (menuToggle) {
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('open');
                overlay.classList.toggle('active');
            });
        }

        if (overlay) {
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
            });
        }

        // Close sidebar on outside click (mobile)
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 1024 && sidebar && sidebar.classList.contains('open')) {
                if (!sidebar.contains(e.target) && (!menuToggle || !menuToggle.contains(e.target))) {
                    sidebar.classList.remove('open');
                    overlay.classList.remove('active');
                }
            }
        });
    </script>
</body>
</html>

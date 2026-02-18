        </div>
    </main>

    <script>
        // Flash message auto-hide
        document.addEventListener('DOMContentLoaded', function() {
            var alerts = document.querySelectorAll('.alert, .alert-success, .alert-error, .alert-warning');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.transition = 'opacity 0.3s ease';
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
        });

        // Mobile sidebar toggle
        var menuToggle = document.getElementById('menuToggle');
        var adminSidebar = document.getElementById('adminSidebar');
        var sidebarOverlay = document.getElementById('sidebarOverlay');

        if (menuToggle) {
            menuToggle.addEventListener('click', function() {
                adminSidebar.classList.toggle('open');
                sidebarOverlay.classList.toggle('active');
            });
        }

        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', function() {
                adminSidebar.classList.remove('open');
                sidebarOverlay.classList.remove('active');
            });
        }
    </script>
</body>
</html>
<?php ob_end_flush(); ?>

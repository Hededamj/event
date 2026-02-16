        </div>
    </main>

    <footer class="vendor-footer">
        <p>PartyParart Leverandorportal</p>
    </footer>

    <script src="/subcontractor/assets/js/subcontractor.js"></script>
    <script>
        // Mobile sidebar toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('vendorSidebar');
        const overlay = document.getElementById('sidebarOverlay');

        if (menuToggle && sidebar && overlay) {
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('open');
                overlay.classList.toggle('open');
                document.body.classList.toggle('sidebar-open');
            });

            overlay.addEventListener('click', function() {
                sidebar.classList.remove('open');
                overlay.classList.remove('open');
                document.body.classList.remove('sidebar-open');
            });
        }

        // Auto-dismiss flash messages after 5 seconds
        document.querySelectorAll('.flash-message').forEach(function(el) {
            setTimeout(function() {
                el.style.opacity = '0';
                el.style.transform = 'translateY(-10px)';
                setTimeout(function() { el.remove(); }, 300);
            }, 5000);
        });
    </script>
</body>
</html>

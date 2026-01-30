    </main>

    <footer style="background: var(--color-surface); border-top: 1px solid var(--color-border); padding: 2rem 0; margin-top: 3rem;">
        <div class="container" style="text-align: center;">
            <p style="color: var(--color-text-muted); font-size: 0.875rem;">
                &copy; <?= date('Y') ?> <?= escape($platformName ?? 'EventPlatform') ?>. Alle rettigheder forbeholdes.
            </p>
            <p style="color: var(--color-text-muted); font-size: 0.8rem; margin-top: 0.5rem;">
                <a href="<?= BASE_PATH ?>/" style="color: inherit;">Tilbage til hovedsiden</a>
                &middot;
                <a href="<?= BASE_PATH ?>/partners/register.php" style="color: inherit;">Bliv partner</a>
            </p>
        </div>
    </footer>

    <script>
        // Alert auto-hide
        document.querySelectorAll('.alert').forEach(function(alert) {
            setTimeout(function() {
                alert.style.transition = 'opacity 0.3s';
                alert.style.opacity = '0';
                setTimeout(function() { alert.remove(); }, 300);
            }, 5000);
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>

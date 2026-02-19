<?php
/**
 * Marketplace Public Footer
 * Simple footer for public-facing marketplace pages.
 */
?>

    <footer style="background:var(--white);border-top:1px solid var(--border);padding:32px 24px;text-align:center;">
        <div style="max-width:1280px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
            <a href="/" style="font-family:var(--font-display);font-size:18px;font-weight:500;color:var(--text);text-decoration:none;">
                Party<span style="color:var(--accent);">Parart</span>
            </a>
            <span style="font-size:13px;color:var(--text-secondary);">
                &copy; <?= date('Y') ?> PartyParart. Alle rettigheder forbeholdes.
            </span>
            <div style="display:flex;gap:16px;">
                <a href="/subcontractor/dashboard/register.php" style="font-size:13px;color:var(--text-secondary);text-decoration:none;transition:color 0.2s;">Bliv leverandør</a>
                <a href="/subcontractor/dashboard/login.php" style="font-size:13px;color:var(--text-secondary);text-decoration:none;transition:color 0.2s;">Leverandør login</a>
            </div>
        </div>
    </footer>

</body>
</html>

<?php
/**
 * Seating Plan Page (Premium Feature)
 */

if (!$hasSeating): ?>
<div class="upgrade-notice">
    <div class="upgrade-notice-content">
        <h4>Bordplan er en premium-funktion</h4>
        <p>Opgrader til Basis eller højere for at få adgang til bordplan-funktionen.</p>
    </div>
    <a href="/app/account/subscription.php" class="btn">Opgrader nu</a>
</div>
<?php return; endif;

// Get guests who are attending
$stmt = $db->prepare("SELECT * FROM guests WHERE event_id = ? AND rsvp_status = 'accepted' ORDER BY name ASC");
$stmt->execute([$eventId]);
$attendingGuests = $stmt->fetchAll();
?>

<div class="page-header-actions">
    <div>
        <h2 class="section-title">Bordplan</h2>
        <p class="section-subtitle"><?= count($attendingGuests) ?> gæster at placere</p>
    </div>
</div>

<div class="card">
    <div class="coming-soon">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path>
        </svg>
        <h3>Bordplan kommer snart</h3>
        <p>Vi arbejder på en visuel bordplan-editor hvor du nemt kan placere dine gæster. Funktionen kommer snart!</p>
    </div>
</div>

<?php if (!empty($attendingGuests)): ?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Gæster der kommer</h3>
    </div>
    <div class="guests-list">
        <?php foreach ($attendingGuests as $guest): ?>
        <div class="guest-chip">
            <?= htmlspecialchars($guest['name']) ?>
            <span class="guest-count"><?= (int)$guest['adults_count'] + (int)$guest['children_count'] ?> pers.</span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<style>
    .coming-soon { text-align: center; padding: 60px 20px; }
    .coming-soon svg { width: 64px; height: 64px; color: var(--gray-300); margin-bottom: 16px; }
    .coming-soon h3 { font-size: 18px; font-weight: 600; color: var(--gray-700); margin-bottom: 8px; }
    .coming-soon p { color: var(--gray-500); max-width: 400px; margin: 0 auto; }
    .guests-list { display: flex; flex-wrap: wrap; gap: 8px; }
    .guest-chip { display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; background: var(--gray-100); border-radius: 8px; font-size: 14px; }
    .guest-count { font-size: 12px; color: var(--gray-500); }
</style>

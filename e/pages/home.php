<?php
/**
 * Guest Home Page - Nordic Design
 */
?>

<!-- Page Header -->
<div class="page-header">
    <p class="page-header-greeting">Velkommen tilbage</p>
    <h1 class="page-header-title"><?= htmlspecialchars($currentGuest['name'] ?? 'Gæst') ?></h1>
    <p class="page-header-subtitle"><?= htmlspecialchars($event['name'] ?? 'Arrangementet') ?></p>
</div>

<?php if ($event['welcome_text']): ?>
<div class="card">
    <p style="line-height: 1.8; color: var(--charcoal-light);"><?= nl2br(htmlspecialchars($event['welcome_text'])) ?></p>
</div>
<?php endif; ?>

<!-- RSVP Status Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Din tilmelding</h3>
        <div class="card-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        </div>
    </div>

    <?php if ($currentGuest['rsvp_status'] === 'pending'): ?>
        <p style="margin-bottom: 20px; color: var(--charcoal-light);">Du har endnu ikke svaret på invitationen.</p>
        <a href="/e/<?= $slug ?>/rsvp" class="btn btn-sage btn-full">Svar på invitation</a>
    <?php elseif ($currentGuest['rsvp_status'] === 'yes'): ?>
        <div class="status-badge status-success" style="margin-bottom: 16px;">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            Du deltager
        </div>
        <p style="font-size: 14px; color: var(--charcoal-light); margin-bottom: 16px;">
            <?= (int)$currentGuest['adults_count'] ?> voksne<?php if ($currentGuest['children_count'] > 0): ?>, <?= (int)$currentGuest['children_count'] ?> børn<?php endif; ?>
        </p>
        <a href="/e/<?= $slug ?>/rsvp" class="btn btn-secondary btn-full">Rediger tilmelding</a>
    <?php else: ?>
        <div class="status-badge status-error" style="margin-bottom: 16px;">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            Du har meldt afbud
        </div>
        <a href="/e/<?= $slug ?>/rsvp" class="btn btn-secondary btn-full">Ændre svar</a>
    <?php endif; ?>
</div>

<!-- Event Info Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Praktisk information</h3>
        <div class="card-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        </div>
    </div>

    <?php if ($event['event_date']): ?>
    <div class="info-row">
        <div class="info-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
        </div>
        <div class="info-content">
            <h4><?= htmlspecialchars(formatDate($event['event_date'], true)) ?></h4>
            <?php if ($event['event_time']): ?>
                <p>kl. <?= htmlspecialchars(substr($event['event_time'], 0, 5)) ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($event['location']): ?>
    <div class="info-row">
        <div class="info-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
        </div>
        <div class="info-content">
            <h4><?= htmlspecialchars($event['location']) ?></h4>
            <?php if ($event['address']): ?>
                <p><?= htmlspecialchars($event['address']) ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Quick Actions -->
<div class="quick-grid">
    <a href="/e/<?= $slug ?>/wishlist" class="quick-card">
        <div class="quick-card-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"></path></svg>
        </div>
        <h3>Ønskeliste</h3>
        <p>Se og reservér gaver</p>
    </a>
    <a href="/e/<?= $slug ?>/photos" class="quick-card">
        <div class="quick-card-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
        </div>
        <h3>Galleri</h3>
        <p>Del dine billeder</p>
    </a>
    <a href="/e/<?= $slug ?>/schedule" class="quick-card">
        <div class="quick-card-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
        </div>
        <h3>Program</h3>
        <p>Se dagens program</p>
    </a>
    <a href="/e/<?= $slug ?>/indslag" class="quick-card">
        <div class="quick-card-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path></svg>
        </div>
        <h3>Indslag</h3>
        <p>Tilmeld dit indslag</p>
    </a>
</div>

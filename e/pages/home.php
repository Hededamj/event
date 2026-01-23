<?php
/**
 * Guest Home Page
 */
?>

<div style="text-align: center; margin-bottom: 24px;">
    <h1 class="serif" style="font-size: 28px; margin-bottom: 8px;">
        Hej <?= htmlspecialchars($currentGuest['name'] ?? 'Gæst') ?>!
    </h1>
    <p style="color: var(--gray-600);">Velkommen til <?= htmlspecialchars($event['name'] ?? 'arrangementet') ?></p>
</div>

<?php if ($event['welcome_text']): ?>
<div class="card">
    <p style="line-height: 1.7;"><?= nl2br(htmlspecialchars($event['welcome_text'])) ?></p>
</div>
<?php endif; ?>

<!-- RSVP Status Card -->
<div class="card">
    <h3 class="card-title">Din tilmelding</h3>
    <?php if ($currentGuest['rsvp_status'] === 'pending'): ?>
        <p style="margin-bottom: 16px; color: var(--gray-600);">Du har endnu ikke svaret på invitationen.</p>
        <a href="/e/<?= $slug ?>/rsvp" class="btn btn-primary btn-full">Svar nu</a>
    <?php elseif ($currentGuest['rsvp_status'] === 'accepted'): ?>
        <div style="display: flex; align-items: center; gap: 12px; padding: 16px; background: #dcfce7; border-radius: 12px;">
            <svg width="24" height="24" fill="none" stroke="#15803d" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            <div>
                <strong style="color: #15803d;">Du har tilmeldt dig!</strong>
                <p style="font-size: 14px; color: #166534; margin-top: 2px;">
                    <?= (int)$currentGuest['adults_count'] ?> voksne<?php if ($currentGuest['children_count'] > 0): ?>, <?= (int)$currentGuest['children_count'] ?> børn<?php endif; ?>
                </p>
            </div>
        </div>
        <a href="/e/<?= $slug ?>/rsvp" style="display: block; text-align: center; margin-top: 12px; color: var(--primary); font-size: 14px;">Ændr tilmelding</a>
    <?php else: ?>
        <div style="display: flex; align-items: center; gap: 12px; padding: 16px; background: #fef2f2; border-radius: 12px;">
            <svg width="24" height="24" fill="none" stroke="#dc2626" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            <div>
                <strong style="color: #dc2626;">Du har meldt afbud</strong>
            </div>
        </div>
        <a href="/e/<?= $slug ?>/rsvp" style="display: block; text-align: center; margin-top: 12px; color: var(--primary); font-size: 14px;">Ændr tilmelding</a>
    <?php endif; ?>
</div>

<!-- Event Info -->
<div class="card">
    <h3 class="card-title">Praktisk information</h3>
    <div style="display: flex; flex-direction: column; gap: 16px;">
        <?php if ($event['event_date']): ?>
        <div style="display: flex; gap: 12px;">
            <svg width="20" height="20" fill="none" stroke="var(--gray-400)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            <div>
                <strong><?= htmlspecialchars(formatDate($event['event_date'], true)) ?></strong>
                <?php if ($event['event_time']): ?>
                    <p style="font-size: 14px; color: var(--gray-600);">kl. <?= htmlspecialchars(substr($event['event_time'], 0, 5)) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($event['location']): ?>
        <div style="display: flex; gap: 12px;">
            <svg width="20" height="20" fill="none" stroke="var(--gray-400)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
            <div>
                <strong><?= htmlspecialchars($event['location']) ?></strong>
                <?php if ($event['address']): ?>
                    <p style="font-size: 14px; color: var(--gray-600);"><?= htmlspecialchars($event['address']) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Links -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
    <a href="/e/<?= $slug ?>/wishlist" class="card" style="text-align: center; text-decoration: none; color: inherit;">
        <svg width="32" height="32" fill="none" stroke="var(--primary)" viewBox="0 0 24 24" style="margin-bottom: 8px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"></path></svg>
        <strong style="display: block;">Ønskeliste</strong>
        <span style="font-size: 13px; color: var(--gray-600);">Se ønsker</span>
    </a>
    <a href="/e/<?= $slug ?>/photos" class="card" style="text-align: center; text-decoration: none; color: inherit;">
        <svg width="32" height="32" fill="none" stroke="var(--primary)" viewBox="0 0 24 24" style="margin-bottom: 8px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
        <strong style="display: block;">Fotos</strong>
        <span style="font-size: 13px; color: var(--gray-600);">Del billeder</span>
    </a>
</div>

<div style="text-align: center; margin-top: 24px;">
    <a href="/e/<?= $slug ?>/?logout=1" style="color: var(--gray-400); font-size: 13px; text-decoration: none;">Log ud</a>
</div>

<?php
/**
 * Invitation Content Partial
 * Reusable content sections for invitation layouts
 *
 * Expected variables:
 * - $greeting: Personalized greeting text
 * - $config: Invitation configuration array
 * - $event: Event data array
 * - $eventDate: Formatted event date array (day, month, year, weekday, time)
 * - $galleryImages: Array of gallery images
 * - $layoutStyle: Current layout style
 */

$showGreeting = $layoutStyle !== 'fullscreen'; // Greeting shown in hero for fullscreen
?>

<?php if ($showGreeting): ?>
<p class="greeting" data-editable="greeting_template"><?= htmlspecialchars($greeting) ?></p>
<?php endif; ?>

<?php if (!empty($config['headline_text']) && $layoutStyle !== 'fullscreen'): ?>
<h1 class="headline" data-editable="headline_text"><?= htmlspecialchars($config['headline_text']) ?></h1>
<?php endif; ?>

<?php if (!empty($config['invitation_message'])): ?>
<div class="message" data-editable="invitation_message"><?= nl2br(htmlspecialchars($config['invitation_message'])) ?></div>
<?php endif; ?>

<?php if ($eventDate): ?>
<div class="event-details" data-section="details">
    <div class="detail-item">
        <div class="detail-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
        </div>
        <div class="detail-content">
            <h4>Dato</h4>
            <p><?= ucfirst($eventDate['weekday']) ?> d. <?= $eventDate['day'] ?>. <?= $eventDate['month'] ?> <?= $eventDate['year'] ?></p>
        </div>
    </div>

    <?php if ($eventDate['time']): ?>
    <div class="detail-item">
        <div class="detail-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
        <div class="detail-content">
            <h4>Tidspunkt</h4>
            <p>Kl. <?= $eventDate['time'] ?></p>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($event['location'])): ?>
    <div class="detail-item">
        <div class="detail-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
        </div>
        <div class="detail-content">
            <h4>Sted</h4>
            <p><?= htmlspecialchars($event['location']) ?></p>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!empty($config['show_countdown']) && $eventDate): ?>
<div class="countdown" id="countdown" data-section="countdown">
    <div class="countdown-item">
        <div class="countdown-value" id="countdown-days">--</div>
        <div class="countdown-label">Dage</div>
    </div>
    <div class="countdown-item">
        <div class="countdown-value" id="countdown-hours">--</div>
        <div class="countdown-label">Timer</div>
    </div>
    <div class="countdown-item">
        <div class="countdown-value" id="countdown-minutes">--</div>
        <div class="countdown-label">Min</div>
    </div>
</div>
<script>
(function() {
    var eventDate = new Date(<?= json_encode($event['event_date'] . ($event['event_time'] ? 'T' . $event['event_time'] : 'T12:00:00')) ?>);

    function updateCountdown() {
        var now = new Date();
        var diff = eventDate - now;

        if (diff <= 0) {
            document.getElementById('countdown').style.display = 'none';
            return;
        }

        var days = Math.floor(diff / (1000 * 60 * 60 * 24));
        var hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        var minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));

        document.getElementById('countdown-days').textContent = days;
        document.getElementById('countdown-hours').textContent = hours;
        document.getElementById('countdown-minutes').textContent = minutes;
    }

    updateCountdown();
    setInterval(updateCountdown, 60000);
})();
</script>
<?php endif; ?>

<?php if (!empty($galleryImages)): ?>
<div class="gallery" data-section="gallery">
    <?php foreach (array_slice($galleryImages, 0, 6) as $image): ?>
    <div class="gallery-item" style="background-image: url(<?= htmlspecialchars($image['url']) ?>)"></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($config['show_map']) && !empty($event['location'])): ?>
<div class="map-section" data-section="map" style="margin: 32px 0;">
    <h3 style="font-size: 18px; margin-bottom: 12px;">Sådan finder du os</h3>
    <div style="border-radius: 12px; overflow: hidden; border: 1px solid rgba(0,0,0,0.1);">
        <iframe
            width="100%"
            height="250"
            style="border:0; display:block;"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            src="https://www.google.com/maps?q=<?= urlencode($event['location']) ?>&output=embed">
        </iframe>
    </div>
    <p style="margin-top: 8px; font-size: 14px; opacity: 0.7;">
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: -2px;">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
        </svg>
        <?= htmlspecialchars($event['location']) ?>
    </p>
</div>
<?php endif; ?>

<?php if (!empty($config['show_schedule'])): ?>
<div class="schedule-section" data-section="schedule" style="margin: 32px 0; text-align: center;">
    <h3 style="font-size: 18px; margin-bottom: 8px;">Dagens program</h3>
    <p style="font-size: 14px; opacity: 0.7;">Se hele programmet for dagen på event-siden</p>
</div>
<?php endif; ?>

<?php if (!empty($config['show_rsvp'])): ?>
<div class="rsvp-section" data-section="rsvp">
    <h3>Svar på invitationen</h3>
    <p>Vi vil elske at høre om du kan deltage</p>
    <a href="#" class="rsvp-btn">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        Giv dit svar
    </a>
</div>
<?php endif; ?>

<?php if (!empty($config['closing_text'])): ?>
<p class="closing" data-editable="closing_text"><?= htmlspecialchars($config['closing_text']) ?></p>
<?php endif; ?>

<?php if (!empty($showLoginInInvitation)): ?>
<div class="login-section" style="margin-top: 48px; padding: 32px; background: rgba(0,0,0,0.03); border-radius: 16px; text-align: center;">
    <?php if (($event['registration_mode'] ?? 'invite') === 'open'): ?>
        <h3 style="font-family: var(--font-display, 'Cormorant Garamond', serif); font-size: 22px; margin-bottom: 8px;">Tilmeld dig</h3>
        <p style="font-size: 14px; opacity: 0.7; margin-bottom: 20px;">Udfyld dit navn for at tilmelde dig arrangementet</p>
        <?php if (!empty($loginError)): ?>
        <div style="background: #B84C4C; color: white; padding: 10px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px;">
            <?= htmlspecialchars($loginError) ?>
        </div>
        <?php endif; ?>
        <form method="POST" style="max-width: 320px; margin: 0 auto; text-align: left;">
            <input type="hidden" name="open_register" value="1">
            <div style="margin-bottom: 12px;">
                <input type="text" name="reg_name" placeholder="Dit navn" required
                       style="width: 100%; padding: 14px 16px; border: 2px solid rgba(0,0,0,0.1); border-radius: 10px; font-size: 16px; font-family: inherit; background: white;">
            </div>
            <div style="margin-bottom: 12px;">
                <input type="email" name="reg_email" placeholder="Email (valgfrit)"
                       style="width: 100%; padding: 14px 16px; border: 2px solid rgba(0,0,0,0.1); border-radius: 10px; font-size: 16px; font-family: inherit; background: white;">
            </div>
            <button type="submit" style="width: 100%; padding: 14px; background: var(--inv-secondary, #8FA583); color: white; border: none; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; font-family: inherit;">
                Tilmeld mig
            </button>
        </form>
    <?php else: ?>
        <h3 style="font-family: var(--font-display, 'Cormorant Garamond', serif); font-size: 22px; margin-bottom: 8px;">Har du en invitation?</h3>
        <p style="font-size: 14px; opacity: 0.7; margin-bottom: 20px;">Indtast din personlige kode for at svare og se flere detaljer</p>
        <?php if (!empty($loginError)): ?>
        <div style="background: #B84C4C; color: white; padding: 10px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px;">
            <?= htmlspecialchars($loginError) ?>
        </div>
        <?php endif; ?>
        <form method="POST" style="max-width: 280px; margin: 0 auto;">
            <input type="text"
                   name="guest_code"
                   placeholder="Din kode"
                   maxlength="8"
                   autocomplete="off"
                   required
                   style="width: 100%; padding: 14px 16px; border: 2px solid rgba(0,0,0,0.1); border-radius: 10px; font-size: 18px; text-align: center; letter-spacing: 3px; text-transform: uppercase; font-family: inherit; background: white; margin-bottom: 12px;">
            <button type="submit" style="width: 100%; padding: 14px; background: var(--inv-secondary, #8FA583); color: white; border: none; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; font-family: inherit;">
                Log ind
            </button>
        </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
/**
 * Invitation Layout: Slideshow
 * Fullscreen hero images with automatic slideshow - inspired by hededam.dk/sofie
 */

$fonts = getInvitationFontFamily($config['font_style'] ?? 'elegant');
$heroImage = getHeroImage($db, $event['id']);
$galleryImages = getInvitationImages($db, $event['id'], 'gallery');

// Combine hero + gallery for slideshow
$slideshowImages = [];
if ($heroImage) {
    $slideshowImages[] = $heroImage;
}
foreach ($galleryImages as $img) {
    $slideshowImages[] = $img;
}

// Use event colors or defaults
$primaryColor = $config['color_primary'] ?? '#1A1A1A';
$secondaryColor = $config['color_secondary'] ?? '#D4A5A5';
$accentColor = $config['color_accent'] ?? '#B88A8A';
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=DM+Sans:wght@400;500&display=swap');

    :root {
        --inv-primary: <?= htmlspecialchars($primaryColor) ?>;
        --inv-secondary: <?= htmlspecialchars($secondaryColor) ?>;
        --inv-accent: <?= htmlspecialchars($accentColor) ?>;
        --font-display: <?= $fonts['display'] ?>;
        --font-body: <?= $fonts['body'] ?>;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    html, body {
        height: 100%;
        overflow-x: hidden;
    }

    body {
        font-family: var(--font-body);
        background: #0a0a0a;
        color: #fff;
    }

    /* ===== SLIDESHOW BACKGROUND ===== */
    .slideshow-container {
        position: fixed;
        inset: 0;
        z-index: 0;
    }

    .slideshow-slide {
        position: absolute;
        inset: 0;
        opacity: 0;
        transform: scale(1.1);
        transition: opacity 1.5s ease;
    }

    .slideshow-slide.active {
        opacity: 1;
        transform: scale(1);
        transition: opacity 1.5s ease, transform 8s ease-out;
    }

    .slideshow-slide img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center top;
    }

    /* ===== GRADIENT OVERLAY ===== */
    .slideshow-overlay {
        position: fixed;
        inset: 0;
        background: linear-gradient(
            to bottom,
            rgba(212, 165, 165, 0.1) 0%,
            rgba(42, 34, 34, 0.25) 50%,
            rgba(42, 34, 34, 0.8) 100%
        );
        z-index: 1;
        pointer-events: none;
    }

    /* ===== THUMBNAIL NAVIGATION ===== */
    .slideshow-nav {
        position: fixed;
        top: 2rem;
        right: 2rem;
        display: flex;
        flex-direction: column;
        gap: 10px;
        z-index: 10;
    }

    .slideshow-thumb {
        width: 70px;
        height: 90px;
        border-radius: 8px;
        overflow: hidden;
        cursor: pointer;
        opacity: 0.5;
        border: 2px solid transparent;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .slideshow-thumb:hover {
        opacity: 0.9;
        transform: translateY(-4px);
    }

    .slideshow-thumb.active {
        opacity: 1;
        border-color: var(--inv-secondary);
        transform: scale(1.05);
        box-shadow: 0 4px 20px rgba(0,0,0,0.4);
    }

    .slideshow-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    /* ===== MAIN CONTENT ===== */
    .invitation-wrapper {
        position: relative;
        z-index: 5;
        min-height: 100vh;
        min-height: 100dvh;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        padding: 3rem 4rem 4rem;
    }

    .invitation-content {
        max-width: 800px;
    }

    /* ===== EVENT TYPE ===== */
    .inv-event-type {
        font-family: 'Playfair Display', var(--font-display);
        font-style: italic;
        font-size: clamp(1.6rem, 4vw, 3rem);
        font-weight: 400;
        color: var(--inv-secondary);
        margin-bottom: 0.5rem;
        opacity: 0;
        animation: fadeSlideUp 1s ease-out 0.3s forwards;
    }

    /* ===== GREETING ===== */
    .inv-greeting {
        font-family: 'Playfair Display', var(--font-display);
        font-style: italic;
        font-size: 1.3rem;
        font-weight: 400;
        color: rgba(255,255,255,0.85);
        margin-bottom: 0.75rem;
        opacity: 0;
        animation: fadeSlideUp 1s ease-out 0.5s forwards;
    }

    /* ===== MAIN HEADLINE ===== */
    .inv-headline {
        font-family: 'Playfair Display', var(--font-display);
        font-size: clamp(4rem, 18vw, 14rem);
        font-weight: 400;
        line-height: 0.85;
        letter-spacing: -0.02em;
        margin-bottom: 1.5rem;
        opacity: 0;
        animation: heroReveal 1.4s cubic-bezier(0.16, 1, 0.3, 1) 0.7s forwards;
    }

    /* ===== MESSAGE ===== */
    .inv-message {
        font-family: 'Playfair Display', var(--font-display);
        font-style: italic;
        font-size: 1.1rem;
        line-height: 1.8;
        color: rgba(255,255,255,0.8);
        max-width: 500px;
        margin-bottom: 2.5rem;
        opacity: 0;
        animation: fadeSlideUp 1s ease-out 1.1s forwards;
    }

    /* ===== EVENT DETAILS ===== */
    .inv-details {
        display: flex;
        flex-wrap: wrap;
        gap: 2.5rem;
        margin-bottom: 2.5rem;
        opacity: 0;
        animation: fadeSlideUp 1s ease-out 1.3s forwards;
    }

    .inv-detail {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .inv-detail-label {
        font-family: var(--font-body);
        font-size: 0.7rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.15em;
        color: var(--inv-secondary);
    }

    .inv-detail-value {
        font-family: 'Playfair Display', var(--font-display);
        font-size: 1.1rem;
        color: #fff;
    }

    /* ===== COUNTDOWN ===== */
    .inv-countdown {
        display: flex;
        gap: 2rem;
        margin-bottom: 2.5rem;
        opacity: 0;
        animation: fadeSlideUp 1s ease-out 1.5s forwards;
    }

    .countdown-item {
        text-align: left;
    }

    .countdown-value {
        font-family: 'Playfair Display', var(--font-display);
        font-size: clamp(2.5rem, 6vw, 4rem);
        font-weight: 400;
        line-height: 1;
        color: #fff;
    }

    .countdown-label {
        font-family: var(--font-body);
        font-size: 0.7rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.15em;
        color: var(--inv-secondary);
        margin-top: 4px;
    }

    /* ===== CTA BUTTON ===== */
    .inv-cta {
        opacity: 0;
        animation: fadeSlideUp 1s ease-out 1.7s forwards;
    }

    .inv-cta a {
        display: inline-flex;
        align-items: center;
        gap: 12px;
        padding: 1rem 2rem;
        background: var(--inv-secondary);
        color: #1a1a1a;
        text-decoration: none;
        font-family: var(--font-body);
        font-weight: 600;
        font-size: 0.85rem;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        border-radius: 50px;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .inv-cta a:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 40px rgba(212, 165, 165, 0.35);
        background: var(--inv-accent);
    }

    .inv-cta svg {
        width: 18px;
        height: 18px;
        transition: transform 0.3s ease;
    }

    .inv-cta a:hover svg {
        transform: translateX(4px);
    }

    /* ===== CLOSING TEXT ===== */
    .inv-closing {
        position: fixed;
        bottom: 2rem;
        right: 4rem;
        font-family: 'Playfair Display', var(--font-display);
        font-style: italic;
        font-size: 0.95rem;
        color: rgba(255,255,255,0.4);
        opacity: 0;
        animation: fadeIn 1s ease-out 2.2s forwards;
    }

    /* ===== ANIMATIONS ===== */
    @keyframes fadeSlideUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes heroReveal {
        from {
            opacity: 0;
            transform: translateY(80px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 768px) {
        .invitation-wrapper {
            justify-content: flex-start;
            padding: 6rem 1.5rem 3rem;
            min-height: auto;
            padding-bottom: 140px;
        }

        .slideshow-nav {
            top: auto;
            bottom: 1.5rem;
            right: 1.5rem;
            flex-direction: row;
        }

        .slideshow-thumb {
            width: 50px;
            height: 65px;
        }

        .inv-details {
            flex-direction: column;
            gap: 1.25rem;
        }

        .inv-countdown {
            gap: 1.5rem;
        }

        .inv-closing {
            position: relative;
            bottom: auto;
            right: auto;
            margin-top: 3rem;
            text-align: left;
        }

        .inv-headline {
            font-size: clamp(3rem, 15vw, 6rem);
        }
    }
</style>

<!-- Slideshow Background -->
<div class="slideshow-container">
    <?php if (count($slideshowImages) > 0): ?>
        <?php foreach ($slideshowImages as $index => $image): ?>
        <div class="slideshow-slide <?= $index === 0 ? 'active' : '' ?>" data-index="<?= $index ?>">
            <img src="/uploads/invitations/<?= htmlspecialchars($image['filename']) ?>" alt="">
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="slideshow-slide active" style="background: linear-gradient(135deg, #1a1a1a 0%, #2a2222 100%);"></div>
    <?php endif; ?>
</div>

<div class="slideshow-overlay"></div>

<?php if (count($slideshowImages) > 1): ?>
<div class="slideshow-nav">
    <?php foreach ($slideshowImages as $index => $image): ?>
    <div class="slideshow-thumb <?= $index === 0 ? 'active' : '' ?>" data-index="<?= $index ?>">
        <img src="/uploads/invitations/<?= htmlspecialchars($image['filename']) ?>" alt="">
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Content -->
<div class="invitation-wrapper">
    <div class="invitation-content">
        <?php if (!empty($event['event_type_name'])): ?>
        <div class="inv-event-type"><?= htmlspecialchars($event['event_type_name']) ?></div>
        <?php endif; ?>

        <?php if (!empty($greeting)): ?>
        <div class="inv-greeting"><?= htmlspecialchars($greeting) ?></div>
        <?php endif; ?>

        <h1 class="inv-headline">
            <?= htmlspecialchars($config['headline_text'] ?: $event['person1_name']) ?>
        </h1>

        <?php if (!empty($config['invitation_message'])): ?>
        <div class="inv-message">
            <?= nl2br(htmlspecialchars($config['invitation_message'])) ?>
        </div>
        <?php endif; ?>

        <div class="inv-details">
            <div class="inv-detail">
                <span class="inv-detail-label">Dato</span>
                <span class="inv-detail-value"><?= formatDanishDate($event['event_date']) ?></span>
            </div>

            <?php if (!empty($event['event_time'])): ?>
            <div class="inv-detail">
                <span class="inv-detail-label">Tidspunkt</span>
                <span class="inv-detail-value"><?= htmlspecialchars($event['event_time']) ?></span>
            </div>
            <?php endif; ?>

            <?php if (!empty($event['venue_name'])): ?>
            <div class="inv-detail">
                <span class="inv-detail-label">Sted</span>
                <span class="inv-detail-value"><?= htmlspecialchars($event['venue_name']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($config['show_countdown'] ?? true): ?>
        <div class="inv-countdown" id="countdown" data-date="<?= $event['event_date'] ?>">
            <div class="countdown-item">
                <div class="countdown-value" id="countdown-days">--</div>
                <div class="countdown-label">Dage</div>
            </div>
            <div class="countdown-item">
                <div class="countdown-value" id="countdown-hours">--</div>
                <div class="countdown-label">Timer</div>
            </div>
            <div class="countdown-item">
                <div class="countdown-value" id="countdown-mins">--</div>
                <div class="countdown-label">Minutter</div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($config['show_rsvp'] ?? true): ?>
        <div class="inv-cta">
            <a href="#rsvp">
                Svar på invitation
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                </svg>
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($config['closing_text'])): ?>
<div class="inv-closing"><?= htmlspecialchars($config['closing_text']) ?></div>
<?php endif; ?>

<script>
// Slideshow
(function() {
    const slides = document.querySelectorAll('.slideshow-slide');
    const thumbs = document.querySelectorAll('.slideshow-thumb');
    if (slides.length <= 1) return;

    let currentIndex = 0;
    let intervalId;

    function showSlide(index) {
        slides.forEach((slide, i) => {
            if (i === index) {
                slide.classList.add('active');
            } else {
                slide.classList.remove('active');
            }
        });
        thumbs.forEach((thumb, i) => {
            if (i === index) {
                thumb.classList.add('active');
            } else {
                thumb.classList.remove('active');
            }
        });
        currentIndex = index;
    }

    function nextSlide() {
        showSlide((currentIndex + 1) % slides.length);
    }

    function startAutoplay() {
        intervalId = setInterval(nextSlide, 6000);
    }

    function stopAutoplay() {
        clearInterval(intervalId);
    }

    thumbs.forEach(function(thumb, index) {
        thumb.addEventListener('click', function() {
            stopAutoplay();
            showSlide(index);
            startAutoplay();
        });
    });

    startAutoplay();
})();

// Countdown
(function() {
    var countdownEl = document.getElementById('countdown');
    if (!countdownEl) return;

    var targetDate = new Date(countdownEl.dataset.date + 'T00:00:00');

    function updateCountdown() {
        var now = new Date();
        var diff = targetDate - now;

        if (diff <= 0) {
            document.getElementById('countdown-days').textContent = '0';
            document.getElementById('countdown-hours').textContent = '0';
            document.getElementById('countdown-mins').textContent = '0';
            return;
        }

        var days = Math.floor(diff / (1000 * 60 * 60 * 24));
        var hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        var mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));

        document.getElementById('countdown-days').textContent = days;
        document.getElementById('countdown-hours').textContent = hours;
        document.getElementById('countdown-mins').textContent = mins;
    }

    updateCountdown();
    setInterval(updateCountdown, 60000);
})();
</script>

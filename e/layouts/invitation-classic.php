<?php
/**
 * Invitation Layout: Classic
 * Traditional card-style invitation with border
 */
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= htmlspecialchars($event['name'] ?? 'Invitation') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=DM+Sans:wght@400;500;600&family=Playfair+Display:wght@400;500;600&family=Inter:wght@400;500;600&family=Quicksand:wght@400;500;600&family=Nunito:wght@400;500;600&family=Lora:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        <?= $cssVars ?>

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --font-display: <?= $fonts['display'] ?>;
            --font-body: <?= $fonts['body'] ?>;
        }

        body {
            font-family: var(--font-body);
            background: var(--inv-background);
            color: var(--inv-text);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            padding: 40px 20px;
        }

        .invitation-card {
            max-width: 520px;
            margin: 0 auto;
            background: #fff;
            border: 3px solid var(--inv-secondary);
            border-radius: 4px;
            padding: 48px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        }

        .hero-section {
            margin: -48px -48px 32px;
            aspect-ratio: 3/2;
            background-size: cover;
            background-position: center;
            background-color: var(--inv-secondary);
        }

        .card-content {
            text-align: center;
        }

        .ornament {
            width: 80px;
            height: 1px;
            background: var(--inv-secondary);
            margin: 0 auto 24px;
            position: relative;
        }

        .ornament::before {
            content: '';
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            width: 12px;
            height: 12px;
            background: var(--inv-secondary);
            border-radius: 50%;
        }

        .greeting {
            font-family: var(--font-display);
            font-size: clamp(22px, 3vw, 28px);
            font-weight: 400;
            margin-bottom: 12px;
            color: var(--inv-primary);
        }

        .headline {
            font-family: var(--font-display);
            font-size: clamp(28px, 4vw, 40px);
            font-weight: 500;
            line-height: 1.15;
            margin-bottom: 24px;
            color: var(--inv-primary);
        }

        .message {
            font-size: 15px;
            line-height: 1.8;
            margin-bottom: 32px;
            white-space: pre-line;
            text-align: center;
        }

        .event-info {
            border-top: 1px solid rgba(0,0,0,0.08);
            border-bottom: 1px solid rgba(0,0,0,0.08);
            padding: 24px 0;
            margin: 24px 0;
        }

        .event-info-item {
            padding: 8px 0;
        }

        .event-info-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            color: var(--inv-secondary);
            margin-bottom: 4px;
        }

        .event-info-value {
            font-family: var(--font-display);
            font-size: 18px;
            font-weight: 500;
        }

        .countdown {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin: 32px 0;
        }

        .countdown-item {
            background: var(--inv-secondary);
            color: #fff;
            padding: 14px 16px;
            border-radius: 8px;
            text-align: center;
            min-width: 60px;
        }

        .countdown-value {
            font-family: var(--font-display);
            font-size: 24px;
            font-weight: 500;
            line-height: 1;
        }

        .countdown-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-top: 4px;
            opacity: 0.8;
        }

        .gallery {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin: 24px 0;
        }

        .gallery-item {
            aspect-ratio: 1;
            border-radius: 4px;
            overflow: hidden;
            background-size: cover;
            background-position: center;
        }

        .rsvp-section {
            background: var(--inv-secondary);
            color: #fff;
            padding: 28px;
            border-radius: 8px;
            margin-top: 32px;
        }

        .rsvp-section h3 {
            font-family: var(--font-display);
            font-size: 20px;
            margin-bottom: 8px;
        }

        .rsvp-section p {
            opacity: 0.9;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .rsvp-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #fff;
            color: var(--inv-primary);
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            transition: transform 0.2s;
        }

        .rsvp-btn:hover { transform: translateY(-2px); }

        .closing {
            font-family: var(--font-display);
            font-size: 16px;
            font-style: italic;
            margin-top: 32px;
            opacity: 0.8;
        }

        @media (max-width: 560px) {
            body { padding: 20px 12px; }
            .invitation-card { padding: 32px 24px; }
            .hero-section { margin: -32px -24px 24px; }
            .countdown { gap: 8px; }
            .countdown-item { padding: 10px 12px; min-width: 50px; }
            .countdown-value { font-size: 20px; }
            .gallery { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <div class="invitation-card">
        <?php if ($heroImage): ?>
        <div class="hero-section" style="background-image: url(/uploads/invitations/<?= htmlspecialchars($heroImage['filename']) ?>)"></div>
        <?php endif; ?>

        <div class="card-content">
            <div class="ornament"></div>

            <p class="greeting"><?= htmlspecialchars($greeting) ?></p>
            <h1 class="headline"><?= htmlspecialchars($invitationConfig['headline_text'] ?? $event['name']) ?></h1>

            <?php if (!empty($invitationConfig['invitation_message'])): ?>
            <div class="message"><?= nl2br(htmlspecialchars($invitationConfig['invitation_message'])) ?></div>
            <?php endif; ?>

            <?php if ($eventDate): ?>
            <div class="event-info">
                <div class="event-info-item">
                    <div class="event-info-label">Dato</div>
                    <div class="event-info-value"><?= ucfirst($eventDate['weekday']) ?> d. <?= $eventDate['day'] ?>. <?= $eventDate['month'] ?> <?= $eventDate['year'] ?></div>
                </div>
                <?php if ($eventDate['time']): ?>
                <div class="event-info-item">
                    <div class="event-info-label">Tidspunkt</div>
                    <div class="event-info-value">Kl. <?= $eventDate['time'] ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($event['location'])): ?>
                <div class="event-info-item">
                    <div class="event-info-label">Sted</div>
                    <div class="event-info-value"><?= htmlspecialchars($event['location']) ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($invitationConfig['show_countdown']) && $eventDate): ?>
            <div class="countdown" id="countdown">
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
                const eventDate = new Date('<?= $event['event_date'] ?><?= $event['event_time'] ? 'T' . $event['event_time'] : 'T12:00:00' ?>');
                function updateCountdown() {
                    const now = new Date();
                    const diff = eventDate - now;
                    if (diff <= 0) { document.getElementById('countdown').style.display = 'none'; return; }
                    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
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
            <div class="gallery">
                <?php foreach (array_slice($galleryImages, 0, 6) as $image): ?>
                <div class="gallery-item" style="background-image: url(/uploads/invitations/<?= htmlspecialchars($image['filename']) ?>)"></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($invitationConfig['show_rsvp'])): ?>
            <div class="rsvp-section">
                <h3>Svar på invitationen</h3>
                <p>Vi vil elske at høre om du kan deltage</p>
                <a href="/e/<?= htmlspecialchars($event['slug']) ?>/rsvp" class="rsvp-btn">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Giv dit svar
                </a>
            </div>
            <?php endif; ?>

            <?php if (!empty($invitationConfig['closing_text'])): ?>
            <p class="closing"><?= htmlspecialchars($invitationConfig['closing_text']) ?></p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

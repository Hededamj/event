<?php
/**
 * Invitation Layout: Fullscreen
 * Dramatic fullscreen hero with overlay content
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
        }

        .hero-section {
            position: relative;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-size: cover;
            background-position: center;
            background-color: var(--inv-primary);
        }

        .hero-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to bottom, rgba(0,0,0,0.3), rgba(0,0,0,0.6));
        }

        .hero-content {
            position: relative;
            z-index: 1;
            color: #fff;
            text-align: center;
            padding: 48px;
            max-width: 800px;
        }

        .hero-greeting {
            font-family: var(--font-display);
            font-size: clamp(24px, 4vw, 36px);
            font-weight: 400;
            margin-bottom: 16px;
            opacity: 0.95;
        }

        .hero-headline {
            font-family: var(--font-display);
            font-size: clamp(42px, 8vw, 80px);
            font-weight: 500;
            line-height: 1.05;
            margin-bottom: 24px;
        }

        .hero-date {
            font-size: 18px;
            opacity: 0.9;
            letter-spacing: 0.05em;
        }

        .scroll-indicator {
            position: absolute;
            bottom: 40px;
            left: 50%;
            transform: translateX(-50%);
            color: #fff;
            opacity: 0.7;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateX(-50%) translateY(0); }
            50% { transform: translateX(-50%) translateY(10px); }
        }

        .content-section {
            max-width: 680px;
            margin: 0 auto;
            padding: 80px 24px;
        }

        .greeting {
            font-family: var(--font-display);
            font-size: clamp(24px, 3vw, 32px);
            font-weight: 400;
            margin-bottom: 24px;
            color: var(--inv-primary);
        }

        .message {
            font-size: 16px;
            line-height: 1.8;
            margin-bottom: 40px;
            white-space: pre-line;
        }

        .event-details {
            background: rgba(0,0,0,0.03);
            border-radius: 16px;
            padding: 28px;
            margin-bottom: 40px;
        }

        .detail-item {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 12px 0;
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }

        .detail-item:last-child { border-bottom: none; }

        .detail-icon {
            width: 40px;
            height: 40px;
            background: var(--inv-secondary);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            flex-shrink: 0;
        }

        .detail-icon svg { width: 20px; height: 20px; }

        .detail-content h4 { font-size: 14px; font-weight: 600; margin-bottom: 2px; }
        .detail-content p { font-size: 14px; opacity: 0.7; }

        .countdown {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 40px 0;
        }

        .countdown-item {
            background: var(--inv-secondary);
            color: #fff;
            padding: 20px 24px;
            border-radius: 12px;
            text-align: center;
        }

        .countdown-value {
            font-family: var(--font-display);
            font-size: 36px;
            font-weight: 500;
            line-height: 1;
        }

        .countdown-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-top: 4px;
            opacity: 0.8;
        }

        .gallery {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin: 40px 0;
        }

        .gallery-item {
            aspect-ratio: 1;
            border-radius: 12px;
            overflow: hidden;
            background-size: cover;
            background-position: center;
        }

        .rsvp-section {
            background: var(--inv-secondary);
            color: #fff;
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            margin-top: 40px;
        }

        .rsvp-section h3 { font-family: var(--font-display); font-size: 28px; margin-bottom: 12px; }
        .rsvp-section p { opacity: 0.9; margin-bottom: 24px; }

        .rsvp-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #fff;
            color: var(--inv-primary);
            padding: 16px 32px;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: transform 0.2s;
        }

        .rsvp-btn:hover { transform: translateY(-2px); }

        .closing {
            text-align: center;
            font-family: var(--font-display);
            font-size: 20px;
            font-style: italic;
            margin-top: 48px;
            opacity: 0.8;
        }

        @media (max-width: 600px) {
            .hero-content { padding: 24px; }
            .content-section { padding: 48px 20px; }
            .countdown { gap: 12px; flex-wrap: wrap; }
            .countdown-item { padding: 16px; }
            .countdown-value { font-size: 28px; }
            .gallery { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <div class="hero-section" style="<?= $heroImage ? 'background-image: url(/uploads/invitations/' . htmlspecialchars($heroImage['filename']) . ')' : '' ?>">
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <p class="hero-greeting"><?= htmlspecialchars($greeting) ?></p>
            <h1 class="hero-headline"><?= htmlspecialchars($invitationConfig['headline_text'] ?? $event['name']) ?></h1>
            <?php if ($eventDate): ?>
            <p class="hero-date"><?= ucfirst($eventDate['weekday']) ?> d. <?= $eventDate['day'] ?>. <?= $eventDate['month'] ?> <?= $eventDate['year'] ?></p>
            <?php endif; ?>
        </div>
        <div class="scroll-indicator">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
            </svg>
        </div>
    </div>

    <div class="content-section">
        <?php
        $layoutStyle = 'fullscreen';
        include __DIR__ . '/../partials/invitation-content.php';
        ?>
    </div>
</body>
</html>

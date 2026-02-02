<?php
/**
 * Invitation Layout: Centered
 * Elegant centered layout with hero at top
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

        .invitation-layout {
            max-width: 680px;
            margin: 0 auto;
            padding: 60px 24px;
        }

        .hero-section {
            aspect-ratio: 16/10;
            border-radius: 24px;
            overflow: hidden;
            margin-bottom: 48px;
            background-size: cover;
            background-position: center;
            background-color: var(--inv-secondary);
        }

        .greeting {
            font-family: var(--font-display);
            font-size: clamp(28px, 4vw, 42px);
            font-weight: 400;
            margin-bottom: 16px;
            color: var(--inv-primary);
            text-align: center;
        }

        .headline {
            font-family: var(--font-display);
            font-size: clamp(36px, 5vw, 56px);
            font-weight: 500;
            line-height: 1.1;
            margin-bottom: 32px;
            color: var(--inv-primary);
            text-align: center;
        }

        .message {
            font-size: 16px;
            line-height: 1.8;
            margin-bottom: 40px;
            white-space: pre-line;
            text-align: center;
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

        .detail-content h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .detail-content p {
            font-size: 14px;
            opacity: 0.7;
        }

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
            min-width: 80px;
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

        .rsvp-section h3 {
            font-family: var(--font-display);
            font-size: 28px;
            margin-bottom: 12px;
        }

        .rsvp-section p {
            opacity: 0.9;
            margin-bottom: 24px;
        }

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
            .invitation-layout { padding: 32px 20px; }
            .hero-section { aspect-ratio: 4/3; }
            .countdown { gap: 12px; }
            .countdown-item { padding: 16px; min-width: 60px; }
            .countdown-value { font-size: 28px; }
            .gallery { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <div class="invitation-layout">
        <?php if ($heroImage): ?>
        <div class="hero-section" style="background-image: url(/uploads/invitations/<?= htmlspecialchars($heroImage['filename']) ?>)"></div>
        <?php endif; ?>

        <?php
        $layoutStyle = 'centered';
        include __DIR__ . '/../partials/invitation-content.php';
        ?>
    </div>
</body>
</html>

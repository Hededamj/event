<?php
/**
 * Invitation Layout: Minimal
 * Clean, text-focused design with optional circular image
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
            max-width: 560px;
            margin: 0 auto;
            padding: 80px 24px;
            text-align: center;
        }

        .hero-section {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 48px;
            background-size: cover;
            background-position: center;
            background-color: var(--inv-secondary);
        }

        .greeting {
            font-family: var(--font-display);
            font-size: clamp(24px, 4vw, 32px);
            font-weight: 400;
            margin-bottom: 16px;
            color: var(--inv-primary);
        }

        .headline {
            font-family: var(--font-display);
            font-size: clamp(32px, 5vw, 48px);
            font-weight: 500;
            line-height: 1.15;
            margin-bottom: 32px;
            color: var(--inv-primary);
        }

        .message {
            font-size: 16px;
            line-height: 1.8;
            margin-bottom: 40px;
            white-space: pre-line;
        }

        .divider {
            width: 60px;
            height: 1px;
            background: var(--inv-secondary);
            margin: 40px auto;
        }

        .event-details {
            text-align: left;
            margin-bottom: 40px;
        }

        .detail-item {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 16px 0;
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
            gap: 16px;
            margin: 40px 0;
        }

        .countdown-item {
            background: var(--inv-secondary);
            color: #fff;
            padding: 16px 20px;
            border-radius: 10px;
            text-align: center;
        }

        .countdown-value {
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 500;
            line-height: 1;
        }

        .countdown-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-top: 4px;
            opacity: 0.8;
        }

        .gallery { display: none; }

        .rsvp-section {
            background: var(--inv-secondary);
            color: #fff;
            padding: 32px;
            border-radius: 16px;
            margin-top: 40px;
        }

        .rsvp-section h3 { font-family: var(--font-display); font-size: 22px; margin-bottom: 10px; }
        .rsvp-section p { opacity: 0.9; margin-bottom: 20px; font-size: 14px; }

        .rsvp-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #fff;
            color: var(--inv-primary);
            padding: 14px 28px;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            transition: transform 0.2s;
        }

        .rsvp-btn:hover { transform: translateY(-2px); }

        .closing {
            font-family: var(--font-display);
            font-size: 18px;
            font-style: italic;
            margin-top: 48px;
            opacity: 0.8;
        }

        @media (max-width: 480px) {
            .invitation-layout { padding: 48px 20px; }
            .hero-section { width: 140px; height: 140px; }
            .countdown { gap: 10px; }
            .countdown-item { padding: 12px 16px; }
            .countdown-value { font-size: 24px; }
        }
    </style>
</head>
<body>
    <div class="invitation-layout">
        <?php if ($heroImage): ?>
        <div class="hero-section" style="background-image: url(/uploads/invitations/<?= htmlspecialchars($heroImage['filename']) ?>)"></div>
        <?php endif; ?>

        <?php
        $layoutStyle = 'minimal';
        include __DIR__ . '/../partials/invitation-content.php';
        ?>
    </div>
</body>
</html>

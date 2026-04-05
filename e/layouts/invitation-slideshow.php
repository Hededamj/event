<?php
/**
 * Invitation Layout: Slideshow
 * Fullscreen hero images with automatic slideshow, content overlaid at bottom
 */

// Combine hero + gallery for slideshow
$slideshowImages = [];
if ($heroImage) {
    $slideshowImages[] = $heroImage;
}
foreach ($galleryImages as $img) {
    $slideshowImages[] = $img;
}
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

        html, body { height: 100%; overflow-x: hidden; }

        body {
            font-family: var(--font-body);
            background: #0a0a0a;
            color: #fff;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        /* ===== SLIDESHOW BACKGROUND ===== */
        .slideshow-container { position: fixed; inset: 0; z-index: 0; }

        .slideshow-slide {
            position: absolute; inset: 0;
            opacity: 0; transform: scale(1.1);
            transition: opacity 1.5s ease;
        }
        .slideshow-slide.active {
            opacity: 1; transform: scale(1);
            transition: opacity 1.5s ease, transform 8s ease-out;
        }
        .slideshow-slide img { width: 100%; height: 100%; object-fit: cover; }

        .slideshow-overlay {
            position: fixed; inset: 0; z-index: 1; pointer-events: none;
            background: linear-gradient(to bottom, rgba(0,0,0,0.1) 0%, rgba(0,0,0,0.3) 50%, rgba(0,0,0,0.85) 100%);
        }

        /* ===== THUMBNAIL NAV ===== */
        .slideshow-nav {
            position: fixed; top: 2rem; right: 2rem;
            display: flex; flex-direction: column; gap: 10px; z-index: 10;
        }
        .slideshow-thumb {
            width: 60px; height: 78px; border-radius: 8px; overflow: hidden;
            cursor: pointer; opacity: 0.5; border: 2px solid transparent;
            transition: all 0.4s ease;
        }
        .slideshow-thumb:hover { opacity: 0.9; }
        .slideshow-thumb.active {
            opacity: 1; border-color: var(--inv-secondary, #8FA583);
            box-shadow: 0 4px 20px rgba(0,0,0,0.4);
        }
        .slideshow-thumb img { width: 100%; height: 100%; object-fit: cover; }

        /* ===== CONTENT WRAPPER ===== */
        .invitation-wrapper {
            position: relative; z-index: 5;
            min-height: 100vh; min-height: 100dvh;
            display: flex; flex-direction: column; justify-content: flex-end;
            padding: 3rem 4rem 4rem;
        }

        .content-section { max-width: 700px; }

        /* Override invitation-content.php colors for dark background */
        .content-section .greeting,
        .content-section .headline { color: #fff; }
        .content-section .message { color: rgba(255,255,255,0.85); }
        .content-section .closing { color: rgba(255,255,255,0.6); }
        .content-section .event-details { background: rgba(255,255,255,0.08); }
        .content-section .detail-icon { background: var(--inv-secondary, #8FA583); color: #fff; }
        .content-section .detail-content h4 { color: rgba(255,255,255,0.7); }
        .content-section .detail-content p { color: #fff; }
        .content-section .countdown { margin: 32px 0; }
        .content-section .countdown-item { background: rgba(255,255,255,0.1); color: #fff; }
        .content-section .countdown-value { color: #fff; }
        .content-section .countdown-label { color: rgba(255,255,255,0.7); }
        .content-section .rsvp-section { background: var(--inv-secondary, #8FA583); }
        .content-section .login-section { background: rgba(255,255,255,0.08); }
        .content-section .login-section h3 { color: #fff; }
        .content-section .login-section p { color: rgba(255,255,255,0.7); }
        .content-section .login-section input { background: rgba(255,255,255,0.9); color: #1a1a1a; }

        /* Map section override */
        .content-section .map-section h3 { color: #fff; }
        .content-section .map-section p { color: rgba(255,255,255,0.6); }
        .content-section .schedule-section h3 { color: #fff; }
        .content-section .schedule-section p { color: rgba(255,255,255,0.6); }

        @media (max-width: 768px) {
            .invitation-wrapper { padding: 6rem 1.5rem 3rem; }
            .slideshow-nav {
                top: auto; bottom: 1.5rem; right: 1.5rem;
                flex-direction: row;
            }
            .slideshow-thumb { width: 44px; height: 58px; }
        }
    </style>
</head>
<body>
    <!-- Slideshow Background -->
    <div class="slideshow-container">
        <?php if (count($slideshowImages) > 0): ?>
            <?php foreach ($slideshowImages as $index => $image): ?>
            <div class="slideshow-slide <?= $index === 0 ? 'active' : '' ?>" data-index="<?= $index ?>">
                <img src="/uploads/invitations/<?= htmlspecialchars($image['filename']) ?>" alt="" loading="<?= $index === 0 ? 'eager' : 'lazy' ?>">
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="slideshow-slide active" style="background: linear-gradient(135deg, #1a1a1a 0%, #2a2222 100%); width:100%; height:100%;"></div>
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
        <div class="content-section">
            <?php
            $layoutStyle = 'slideshow';
            $config = $invitationConfig;
            include __DIR__ . '/../partials/invitation-content.php';
            ?>
        </div>
    </div>

    <?php if (count($slideshowImages) > 1): ?>
    <script>
    (function() {
        var slides = document.querySelectorAll('.slideshow-slide');
        var thumbs = document.querySelectorAll('.slideshow-thumb');
        var currentIndex = 0;
        var intervalId;

        function showSlide(index) {
            for (var i = 0; i < slides.length; i++) {
                slides[i].classList.toggle('active', i === index);
            }
            for (var i = 0; i < thumbs.length; i++) {
                thumbs[i].classList.toggle('active', i === index);
            }
            currentIndex = index;
        }

        function startAutoplay() {
            intervalId = setInterval(function() {
                showSlide((currentIndex + 1) % slides.length);
            }, 6000);
        }

        for (var i = 0; i < thumbs.length; i++) {
            (function(index) {
                thumbs[index].addEventListener('click', function() {
                    clearInterval(intervalId);
                    showSlide(index);
                    startAutoplay();
                });
            })(i);
        }

        startAutoplay();
    })();
    </script>
    <?php endif; ?>
</body>
</html>

<?php
/**
 * PartyParart Landing Page
 * Celebrating life's precious moments
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PartyParart - Skab uforglemmelige øjeblikke</title>
    <meta name="description" content="PartyParart gør dine livsfejringer magiske. Personlige invitationer, gæstehåndtering og smukke oplevelser til konfirmationer, bryllupper, fødselsdage og jubilæer.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --surface: #FAF9F7;
            --surface-card: #FFFFFF;
            --border: #E2DED8;
            --border-light: #EDEAE5;
            --text: #1A1A1A;
            --text-secondary: #6B6560;
            --accent: #6B8F5E;
            --accent-light: #E8F0E4;
            --accent-dark: #4D6E42;
            --warning: #C4922D;
            --warning-light: #FDF6E8;
            --success: #3D8B3D;
            --error: #C14B4B;
            --blush: #D4A5A5;
            --white: #FFFFFF;
            --font-display: 'Cormorant Garamond', Georgia, serif;
            --font-body: 'DM Sans', -apple-system, sans-serif;
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'DM Sans', -apple-system, sans-serif;
            color: var(--text);
            line-height: 1.6;
            background: var(--surface);
            -webkit-font-smoothing: antialiased;
        }

        /* Subtle grain texture */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.8' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E");
            opacity: 0.015;
            pointer-events: none;
            z-index: 9999;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* ===== HEADER ===== */
        .header {
            padding: 20px 0;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(250, 249, 247, 0.9);
            backdrop-filter: blur(20px);
            z-index: 100;
            transition: all 0.4s ease;
        }

        .header.scrolled {
            padding: 14px 0;
            box-shadow: 0 2px 40px rgba(0,0,0,0.06);
        }

        .header .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            font-family: 'Cormorant Garamond', serif;
            font-size: 28px;
            font-weight: 500;
            color: var(--text);
            text-decoration: none;
            letter-spacing: -0.02em;
        }

        .logo span { color: var(--accent-dark); }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 40px;
        }

        .nav-links a {
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            font-size: 15px;
            transition: color 0.3s;
        }

        .nav-links a:hover { color: var(--accent-dark); }

        .nav-links a.btn-primary { color: white; }
        .nav-links a.btn-primary:hover { color: white; }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 28px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-primary {
            background: var(--accent);
            color: white;
        }

        .btn-primary:hover {
            background: var(--accent-dark);
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(143, 165, 131, 0.35);
        }

        .btn-secondary {
            background: transparent;
            color: var(--text);
            border: 2px solid var(--border);
        }

        .btn-secondary:hover {
            border-color: var(--accent);
            color: var(--accent-dark);
        }

        .btn-gold {
            background: var(--warning);
            color: white;
        }

        .btn-gold:hover {
            background: var(--warning-light);
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(184, 146, 61, 0.35);
        }

        /* ===== HERO ===== */
        .hero {
            position: relative;
            overflow: hidden;
        }

        .hero-image {
            width: 100%;
            height: 60vh;
            min-height: 360px;
            object-fit: cover;
            object-position: center 30%;
            display: block;
        }

        .hero-text {
            padding: 40px 24px 60px;
            max-width: 600px;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
            color: var(--accent-dark);
            margin-bottom: 28px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.04);
        }

        .hero-badge svg {
            width: 16px;
            height: 16px;
            color: var(--warning);
        }

        .hero h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 36px;
            font-weight: 500;
            line-height: 1.15;
            margin-bottom: 24px;
            letter-spacing: -0.02em;
        }

        .hero h1 em {
            font-style: italic;
            color: var(--accent-dark);
        }

        .hero-description {
            font-size: 17px;
            color: var(--text-secondary);
            line-height: 1.8;
            margin-bottom: 40px;
        }

        .hero-buttons {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        /* Desktop hero: image as background with text overlay */
        @media (min-width: 1024px) {
            .hero {
                min-height: 100vh;
                display: flex;
                align-items: flex-end;
            }

            .hero-image {
                position: absolute;
                inset: 0;
                height: 100%;
                z-index: 0;
            }

            .hero::after {
                content: '';
                position: absolute;
                inset: 0;
                background: linear-gradient(to top, rgba(0,0,0,0.65) 0%, rgba(0,0,0,0.2) 40%, transparent 70%);
                z-index: 1;
            }

            .hero-text {
                position: relative;
                z-index: 2;
                padding: 0 0 80px;
                max-width: 620px;
                margin-left: 0;
            }

            .hero .container {
                width: 100%;
            }

            .hero h1 {
                font-size: clamp(42px, 5vw, 64px);
                color: white;
            }

            .hero h1 em {
                color: var(--accent-light);
            }

            .hero-description {
                color: rgba(255,255,255,0.85);
            }

            .hero-badge {
                background: rgba(255,255,255,0.15);
                backdrop-filter: blur(10px);
                border-color: rgba(255,255,255,0.2);
                color: white;
            }

            .hero-badge svg {
                color: var(--warning);
            }

            .hero .btn-secondary {
                color: white;
                border-color: rgba(255,255,255,0.4);
            }

            .hero .btn-secondary:hover {
                border-color: white;
                color: white;
            }
        }

        /* ===== TRUST BAR ===== */
        .trust-bar {
            padding: 60px 0;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }

        .trust-bar .container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 60px;
            flex-wrap: wrap;
        }

        .trust-item {
            text-align: center;
        }

        .trust-number {
            font-family: 'Cormorant Garamond', serif;
            font-size: 36px;
            font-weight: 500;
            color: var(--accent-dark);
            margin-bottom: 4px;
        }

        .trust-label {
            font-size: 13px;
            color: var(--text-secondary);
        }

        /* ===== FEATURES ===== */
        .features {
            padding: 120px 0;
        }

        .section-header {
            text-align: center;
            max-width: 600px;
            margin: 0 auto 80px;
        }

        .section-eyebrow {
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 16px;
        }

        .section-header h2 {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(32px, 4vw, 48px);
            font-weight: 500;
            line-height: 1.2;
            margin-bottom: 20px;
        }

        .section-header p {
            font-size: 17px;
            color: var(--text-secondary);
            line-height: 1.7;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 32px;
        }

        .feature-card {
            padding: 40px 32px;
            background: var(--white);
            border-radius: 24px;
            border: 1px solid var(--border);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 30px 60px rgba(0,0,0,0.08);
            border-color: transparent;
        }

        .feature-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--accent-light) 0%, var(--accent) 100%);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
        }

        .feature-icon svg {
            width: 28px;
            height: 28px;
            color: white;
        }

        .feature-card h3 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 24px;
            font-weight: 500;
            margin-bottom: 12px;
        }

        .feature-card p {
            color: var(--text-secondary);
            font-size: 15px;
            line-height: 1.7;
        }

        /* ===== INVITATION SHOWCASE ===== */
        .invitation-showcase {
            padding: 120px 0;
            background: var(--text);
            color: white;
            position: relative;
            overflow: hidden;
        }

        .invitation-showcase::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(143, 165, 131, 0.1) 0%, transparent 50%);
        }

        .invitation-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 80px;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .invitation-text .section-eyebrow {
            color: var(--warning);
        }

        .invitation-text h2 {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(32px, 4vw, 48px);
            font-weight: 500;
            line-height: 1.2;
            margin-bottom: 24px;
        }

        .invitation-text p {
            font-size: 17px;
            color: rgba(255,255,255,0.7);
            line-height: 1.8;
            margin-bottom: 32px;
        }

        .invitation-features {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-bottom: 40px;
        }

        .invitation-feature {
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }

        .invitation-feature-icon {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .invitation-feature-icon svg {
            width: 20px;
            height: 20px;
            color: var(--warning);
        }

        .invitation-feature h4 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .invitation-feature p {
            font-size: 14px;
            color: rgba(255,255,255,0.6);
            margin: 0;
        }

        .invitation-preview {
            position: relative;
        }

        .preview-phone {
            background: #0a0a0a;
            border-radius: 40px;
            padding: 12px;
            box-shadow: 0 40px 80px rgba(0,0,0,0.4);
            max-width: 340px;
            margin: 0 auto;
        }

        .preview-screen {
            background: linear-gradient(180deg, var(--accent-dark) 0%, var(--text) 100%);
            border-radius: 32px;
            aspect-ratio: 9/16;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 40px 24px;
            position: relative;
            overflow: hidden;
        }

        .preview-screen::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to bottom, transparent 40%, rgba(0,0,0,0.7) 100%);
        }

        .preview-content {
            position: relative;
            z-index: 1;
        }

        .preview-event-type {
            font-family: 'Cormorant Garamond', serif;
            font-style: italic;
            font-size: 18px;
            color: var(--blush);
            margin-bottom: 8px;
        }

        .preview-name {
            font-family: 'Cormorant Garamond', serif;
            font-size: 42px;
            font-weight: 400;
            line-height: 1;
            margin-bottom: 16px;
        }

        .preview-date {
            font-size: 13px;
            color: rgba(255,255,255,0.7);
        }

        /* ===== EVENT TYPES ===== */
        .event-types {
            padding: 80px 0;
        }

        .event-types-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .event-type-card {
            background: var(--white);
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid var(--border);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .event-type-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 30px 60px rgba(0,0,0,0.08);
            border-color: var(--accent);
        }

        .event-type-card img {
            width: 100%;
            height: 220px;
            object-fit: cover;
            display: block;
        }

        .event-type-card-body {
            padding: 24px;
            text-align: center;
        }

        .event-type-card h3 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 22px;
            font-weight: 500;
            margin-bottom: 6px;
        }

        .event-type-card p {
            font-size: 14px;
            color: var(--text-secondary);
        }

        @media (min-width: 768px) {
            .event-types { padding: 120px 0; }
            .event-types-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 24px;
            }
            .event-type-card:last-child {
                grid-column: 1 / -1;
                max-width: calc(50% - 12px);
                margin: 0 auto;
            }
            .event-type-card img { height: 250px; }
        }

        @media (min-width: 1024px) {
            .event-types-grid {
                grid-template-columns: repeat(5, 1fr);
            }
            .event-type-card:last-child {
                grid-column: auto;
                max-width: none;
                margin: 0;
            }
            .event-type-card img { height: 200px; }
        }

        /* ===== ATMOSPHERE BREAK ===== */
        .atmosphere-break {
            height: 400px;
            background: url('/billeder/stemning-stort-arrangement.png') center/cover no-repeat;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .atmosphere-break::before {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.45);
        }

        .atmosphere-break p {
            position: relative;
            z-index: 1;
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(28px, 5vw, 48px);
            font-style: italic;
            font-weight: 400;
            color: white;
            text-align: center;
            padding: 0 24px;
            max-width: 700px;
            line-height: 1.3;
        }

        @media (min-width: 1024px) {
            .atmosphere-break {
                height: 500px;
                background-attachment: fixed;
            }
        }

        /* ===== TESTIMONIAL ===== */
        .testimonial {
            padding: 120px 0;
            background: var(--white);
        }

        .testimonial-content {
            max-width: 800px;
            margin: 0 auto;
            text-align: center;
        }

        .testimonial-quote {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(24px, 3vw, 36px);
            font-style: italic;
            line-height: 1.6;
            margin-bottom: 40px;
            color: var(--text);
        }

        .testimonial-author {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
        }

        .testimonial-avatar {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, var(--accent-light), var(--blush));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Cormorant Garamond', serif;
            font-size: 24px;
            color: white;
        }

        .testimonial-info h4 {
            font-size: 16px;
            font-weight: 600;
        }

        .testimonial-info p {
            font-size: 14px;
            color: var(--text-secondary);
        }

        /* ===== CTA ===== */
        .cta {
            padding: 120px 0;
        }

        .cta-box {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
            border-radius: 40px;
            padding: 80px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .cta-box::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 60%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        }

        .cta-box h2 {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(32px, 4vw, 48px);
            font-weight: 500;
            color: white;
            margin-bottom: 20px;
            position: relative;
        }

        .cta-box p {
            font-size: 18px;
            color: rgba(255,255,255,0.85);
            margin-bottom: 40px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
            position: relative;
        }

        .cta-box .btn {
            background: white;
            color: var(--accent-dark);
            padding: 18px 40px;
            font-size: 16px;
            position: relative;
        }

        .cta-box .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        }

        /* ===== FOOTER ===== */
        .footer {
            padding: 60px 0;
            background: var(--text);
            color: white;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .footer-logo {
            font-family: 'Cormorant Garamond', serif;
            font-size: 24px;
            font-weight: 500;
        }

        .footer-logo span { color: var(--accent-light); }

        .footer-links {
            display: flex;
            gap: 40px;
        }

        .footer-links a {
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
        }

        .footer-links a:hover { color: white; }

        .footer-copy {
            font-size: 13px;
            color: rgba(255,255,255,0.4);
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .features-grid { grid-template-columns: repeat(2, 1fr); }
            .invitation-content { grid-template-columns: 1fr; }
            .invitation-preview { margin-top: 60px; }
        }

        @media (max-width: 768px) {
            .nav-links { display: none; }
            .features-grid { grid-template-columns: 1fr; }
            .cta-box { padding: 60px 32px; border-radius: 28px; }
            .footer-content { flex-direction: column; gap: 24px; text-align: center; }
            .footer-links { flex-wrap: wrap; justify-content: center; gap: 24px; }
        }
    </style>
</head>
<body>
    <header class="header" id="header">
        <div class="container">
            <a href="/" class="logo">Party<span>Parart</span></a>
            <nav class="nav-links">
                <a href="#features">Funktioner</a>
                <a href="#events">Arrangementer</a>
                <a href="/app/auth/login.php">Log ind</a>
                <a href="/app/auth/register.php" class="btn btn-primary">Kom i gang gratis</a>
            </nav>
        </div>
    </header>

    <section class="hero">
        <img src="/billeder/hero-konfirmation.png"
             alt="Konfirmationsfest i haven med lyskæder, glade gæster og dansk sommer"
             class="hero-image"
             loading="eager"
             fetchpriority="high">
        <div class="container">
            <div class="hero-text">
                <div class="hero-badge">
                    <svg fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    Skab magiske øjeblikke
                </div>
                <h1>Gør livets store <em>fejringer</em> uforglemmelige</h1>
                <p class="hero-description">
                    PartyParart er din partner i at skabe perfekte arrangementer.
                    Fra smukke personlige invitationer til nem gæstehåndtering
                    – vi hjælper dig med at fejre livets vigtigste øjeblikke.
                </p>
                <div class="hero-buttons">
                    <a href="/app/auth/register.php" class="btn btn-primary">
                        Start dit arrangement
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                        </svg>
                    </a>
                    <a href="#features" class="btn btn-secondary">Se hvordan det virker</a>
                </div>
            </div>
        </div>
    </section>

    <section class="trust-bar">
        <div class="container">
            <div class="trust-item">
                <div class="trust-number">2.500+</div>
                <div class="trust-label">Arrangementer afholdt</div>
            </div>
            <div class="trust-item">
                <div class="trust-number">50.000+</div>
                <div class="trust-label">Glade gæster</div>
            </div>
            <div class="trust-item">
                <div class="trust-number">4.9/5</div>
                <div class="trust-label">Bedømmelse</div>
            </div>
            <div class="trust-item">
                <div class="trust-number">100%</div>
                <div class="trust-label">Dansk support</div>
            </div>
        </div>
    </section>

    <section class="features" id="features">
        <div class="container">
            <div class="section-header">
                <div class="section-eyebrow">Funktioner</div>
                <h2>Alt du behøver til det perfekte arrangement</h2>
                <p>Vi har samlet alle værktøjerne, så du kan fokusere på det vigtigste – at nyde festen.</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </div>
                    <h3>Smukke invitationer</h3>
                    <p>Design personlige invitationer med billeder, animationer og dit helt eget look. Send direkte til gæsternes inbox.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                    <h3>Nem gæstehåndtering</h3>
                    <p>Hold styr på tilmeldinger, allergier og særlige ønsker. Se RSVP-status i realtid og send påmindelser.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/></svg>
                    </div>
                    <h3>Digital ønskeliste</h3>
                    <p>Lad gæsterne reservere gaver online. Slut med dobbeltgaver og pinlige situationer.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <h3>Fælles fotogalleri</h3>
                    <p>Alle gæster kan dele deres billeder fra dagen ét samlet sted. Skab minder sammen.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h3>Program & tidsplan</h3>
                    <p>Del dagens program med gæsterne så alle ved hvad der skal ske og hvornår.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>
                    </div>
                    <h3>Interaktiv bordplan</h3>
                    <p>Planlæg bordplanen visuelt. Træk og slip gæster og sørg for den perfekte placering.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="invitation-showcase">
        <div class="container">
            <div class="invitation-content">
                <div class="invitation-text">
                    <div class="section-eyebrow">Invitationer</div>
                    <h2>Invitationer der gør indtryk</h2>
                    <p>Skab personlige invitationer med smukke billeder, elegante skrifttyper og din helt egen stil. Vores invitationssystem gør det nemt at imponere dine gæster fra første øjeblik.</p>

                    <div class="invitation-features">
                        <div class="invitation-feature">
                            <div class="invitation-feature-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                            <div>
                                <h4>Fullscreen billede-slideshow</h4>
                                <p>Vis dine bedste billeder i et smukt animeret slideshow</p>
                            </div>
                        </div>
                        <div class="invitation-feature">
                            <div class="invitation-feature-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            </div>
                            <div>
                                <h4>Personlig hilsen til hver gæst</h4>
                                <p>"Kære Mormor & Morfar" – hver gæst føler sig speciel</p>
                            </div>
                        </div>
                        <div class="invitation-feature">
                            <div class="invitation-feature-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
                            </div>
                            <div>
                                <h4>Vælg dit eget design</h4>
                                <p>Farver, skrifttyper og layouts der matcher din stil</p>
                            </div>
                        </div>
                    </div>

                    <a href="/app/auth/register.php" class="btn btn-gold">
                        Prøv det gratis
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                        </svg>
                    </a>
                </div>
                <div class="invitation-preview">
                    <div class="preview-phone">
                        <div class="preview-screen">
                            <div class="preview-content">
                                <div class="preview-event-type">Konfirmation</div>
                                <div class="preview-name">Sofie</div>
                                <div class="preview-date">18. maj 2025 • Kl. 13:00</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="event-types" id="events">
        <div class="container">
            <div class="section-header">
                <div class="section-eyebrow">Arrangementer</div>
                <h2>Til alle livets store øjeblikke</h2>
                <p>Uanset hvilken fejring du planlægger, har vi værktøjerne til at gøre det perfekt.</p>
            </div>
            <div class="event-types-grid">
                <div class="event-type-card">
                    <img src="/billeder/kort-konfirmation.png" alt="Ung konfirmand med blomsterkrans i haven" loading="lazy">
                    <div class="event-type-card-body">
                        <h3>Konfirmation</h3>
                        <p>Fejr den store dag med stil</p>
                    </div>
                </div>
                <div class="event-type-card">
                    <img src="/billeder/kort-bryllup.png" alt="Brudepar danser første dans under lyskæder" loading="lazy">
                    <div class="event-type-card-body">
                        <h3>Bryllup</h3>
                        <p>Planlæg den perfekte dag</p>
                    </div>
                </div>
                <div class="event-type-card">
                    <img src="/billeder/kort-foedselsdag.png" alt="Kvinde med krone puster lys ud på fødselsdagskage" loading="lazy">
                    <div class="event-type-card-body">
                        <h3>Fødselsdag</h3>
                        <p>Mærkedage fortjener at fejres</p>
                    </div>
                </div>
                <div class="event-type-card">
                    <img src="/billeder/kort-jubileum.png" alt="Mand griner ved 50 års jubilæumsfest med guldballoner" loading="lazy">
                    <div class="event-type-card-body">
                        <h3>Jubilæum</h3>
                        <p>Fejr milepælene sammen</p>
                    </div>
                </div>
                <div class="event-type-card">
                    <img src="/billeder/kort-temafest.png" alt="Familie i kostumer til hyggelig Halloween-temafest" loading="lazy">
                    <div class="event-type-card-body">
                        <h3>Temafest</h3>
                        <p>Giv festen et unikt tema</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="atmosphere-break" aria-label="Stemningsbillede fra stort arrangement">
        <p>Fra intime middage til store fejringer</p>
    </section>

    <section class="testimonial">
        <div class="container">
            <div class="testimonial-content">
                <div class="testimonial-quote">
                    "PartyParart gjorde planlægningen af Sofies konfirmation så nem. Gæsterne var begejstrede for invitationerne, og jeg havde fuldstændig overblik over alt. Kan varmt anbefales!"
                </div>
                <div class="testimonial-author">
                    <div class="testimonial-avatar">M</div>
                    <div class="testimonial-info">
                        <h4>Maria Jensen</h4>
                        <p>Mor til konfirmand, Roskilde</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="cta">
        <div class="container">
            <div class="cta-box">
                <h2>Klar til at skabe magi?</h2>
                <p>Start dit arrangement gratis i dag og oplev hvor nemt det kan være at planlægge livets store øjeblikke.</p>
                <a href="/app/auth/register.php" class="btn">
                    Kom i gang gratis
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                    </svg>
                </a>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">Party<span>Parart</span></div>
                <div class="footer-links">
                    <a href="#">Om os</a>
                    <a href="#">Priser</a>
                    <a href="#">Kontakt</a>
                    <a href="#">Privatlivspolitik</a>
                </div>
                <div class="footer-copy">© 2025 PartyParart. Alle rettigheder forbeholdes.</div>
            </div>
        </div>
    </footer>

    <script>
        // Header scroll effect
        const header = document.getElementById('header');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });
    </script>
</body>
</html>

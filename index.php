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
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 140px 0 100px;
            position: relative;
            overflow: hidden;
        }

        .hero-bg {
            position: absolute;
            top: -10%;
            right: -5%;
            width: 55%;
            height: 120%;
            background: linear-gradient(135deg, var(--accent-light) 0%, var(--accent) 50%, var(--accent-dark) 100%);
            border-radius: 0 0 0 40%;
            opacity: 0.15;
            z-index: -1;
        }

        .hero-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 80px;
            align-items: center;
        }

        .hero-text {
            max-width: 560px;
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
            font-size: clamp(42px, 5vw, 64px);
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
            font-size: 18px;
            color: var(--text-secondary);
            line-height: 1.8;
            margin-bottom: 40px;
        }

        .hero-buttons {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .hero-visual {
            position: relative;
        }

        .hero-image-stack {
            position: relative;
            height: 500px;
        }

        .hero-card {
            position: absolute;
            background: var(--white);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .hero-card-1 {
            width: 320px;
            height: 400px;
            top: 0;
            right: 0;
            z-index: 3;
            animation: float 6s ease-in-out infinite;
        }

        .hero-card-2 {
            width: 260px;
            height: 320px;
            top: 60px;
            right: 200px;
            z-index: 2;
            animation: float 6s ease-in-out infinite 1s;
        }

        .hero-card-3 {
            width: 200px;
            padding: 24px;
            bottom: 40px;
            right: 40px;
            z-index: 4;
            animation: float 6s ease-in-out infinite 2s;
        }

        .hero-card-content {
            padding: 24px;
        }

        .hero-card-image {
            height: 65%;
            background: linear-gradient(135deg, var(--accent-light) 0%, var(--blush) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Cormorant Garamond', serif;
            font-size: 80px;
            font-style: italic;
            color: white;
            opacity: 0.8;
        }

        .hero-card h4 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 20px;
            margin-bottom: 4px;
        }

        .hero-card p {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .hero-card-3 .stat {
            font-family: 'Cormorant Garamond', serif;
            font-size: 48px;
            font-weight: 500;
            color: var(--accent-dark);
            line-height: 1;
            margin-bottom: 8px;
        }

        .hero-card-3 .stat-label {
            font-size: 13px;
            color: var(--text-secondary);
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
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
            padding: 120px 0;
        }

        .event-types-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
        }

        .event-type-card {
            padding: 40px 28px;
            background: var(--white);
            border-radius: 24px;
            text-align: center;
            border: 1px solid var(--border);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .event-type-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 30px 60px rgba(0,0,0,0.08);
            border-color: var(--accent);
        }

        .event-type-icon {
            width: 72px;
            height: 72px;
            background: var(--surface);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            transition: all 0.4s;
        }

        .event-type-card:hover .event-type-icon {
            background: var(--accent);
        }

        .event-type-icon svg {
            width: 32px;
            height: 32px;
            color: var(--accent-dark);
            transition: color 0.4s;
        }

        .event-type-card:hover .event-type-icon svg {
            color: white;
        }

        .event-type-card h3 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 22px;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .event-type-card p {
            font-size: 14px;
            color: var(--text-secondary);
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
            .hero-content { grid-template-columns: 1fr; gap: 60px; }
            .hero-visual { display: none; }
            .features-grid { grid-template-columns: repeat(2, 1fr); }
            .invitation-content { grid-template-columns: 1fr; }
            .invitation-preview { margin-top: 60px; }
            .event-types-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px) {
            .nav-links { display: none; }
            .hero { padding: 120px 0 80px; }
            .hero h1 { font-size: 36px; }
            .features-grid { grid-template-columns: 1fr; }
            .event-types-grid { grid-template-columns: 1fr; }
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
        <div class="hero-bg"></div>
        <div class="container">
            <div class="hero-content">
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
                <div class="hero-visual">
                    <div class="hero-image-stack">
                        <div class="hero-card hero-card-1">
                            <div class="hero-card-image">S</div>
                            <div class="hero-card-content">
                                <h4>Sofies Konfirmation</h4>
                                <p>18. maj 2025 • 45 gæster</p>
                            </div>
                        </div>
                        <div class="hero-card hero-card-2">
                            <div class="hero-card-image" style="background: linear-gradient(135deg, var(--warning-light), var(--warning));">A</div>
                            <div class="hero-card-content">
                                <h4>Anna & Peters Bryllup</h4>
                                <p>22. august 2025</p>
                            </div>
                        </div>
                        <div class="hero-card hero-card-3">
                            <div class="stat">98%</div>
                            <div class="stat-label">af vores brugere anbefaler os</div>
                        </div>
                    </div>
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
                    <div class="event-type-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v18m-9-9h18"/></svg>
                    </div>
                    <h3>Konfirmation</h3>
                    <p>Fejr den store dag med stil</p>
                </div>
                <div class="event-type-card">
                    <div class="event-type-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                    </div>
                    <h3>Bryllup</h3>
                    <p>Planlæg den perfekte dag</p>
                </div>
                <div class="event-type-card">
                    <div class="event-type-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 15.546c-.523 0-1.046.151-1.5.454a2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0A1.75 1.75 0 013 15.546V19a2 2 0 002 2h14a2 2 0 002-2v-3.454z"/></svg>
                    </div>
                    <h3>Fødselsdag</h3>
                    <p>Mærkedage fortjener at fejres</p>
                </div>
                <div class="event-type-card">
                    <div class="event-type-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                    </div>
                    <h3>Jubilæum</h3>
                    <p>Fejr milepælene sammen</p>
                </div>
            </div>
        </div>
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

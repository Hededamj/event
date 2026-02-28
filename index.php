<?php
/**
 * PartyParart Landing Page
 * Redesigned 2026-02-28 — Focus on signup conversion
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PartyParart — Planlæg festen, vi holder styr på resten</title>
    <meta name="description" content="Gratis festplanlægning for alle. Invitationer, gæstehåndtering, toastmaster-koordinering og meget mere — alt samlet ét sted. Fra 5 til 500 gæster.">
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

        .btn-gold {
            background: var(--warning);
            color: white;
        }

        .btn-gold:hover {
            background: #B8841F;
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(184, 146, 61, 0.35);
        }

        /* ===== HERO WITH PARALLAX ===== */
        .hero {
            position: relative;
            min-height: 80vh;
            display: flex;
            align-items: flex-end;
            overflow: hidden;
        }

        .hero-bg {
            position: absolute;
            left: 0;
            right: 0;
            top: -15%;
            height: 130%;
            background: url('/billeder/hero-konfirmation.png') center 30% / cover no-repeat;
            z-index: 0;
            will-change: transform;
        }

        .hero::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.7) 0%, rgba(0,0,0,0.3) 40%, rgba(0,0,0,0.1) 70%);
            z-index: 1;
        }

        .hero .container {
            width: 100%;
            position: relative;
            z-index: 2;
        }

        .hero-text {
            padding: 40px 0 60px;
            max-width: 620px;
        }

        .hero-free-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--warning);
            border-radius: 50px;
            font-size: 13px;
            font-weight: 700;
            color: white;
            margin-bottom: 28px;
            box-shadow: 0 4px 20px rgba(196, 146, 45, 0.4);
        }

        .hero h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 36px;
            font-weight: 500;
            line-height: 1.15;
            margin-bottom: 24px;
            letter-spacing: -0.02em;
            color: white;
        }

        .hero h1 em {
            font-style: italic;
            color: var(--accent-light);
        }

        .hero-description {
            font-size: 17px;
            color: rgba(255,255,255,0.85);
            line-height: 1.8;
            margin-bottom: 40px;
        }

        @media (min-width: 1024px) {
            .hero {
                min-height: 100vh;
            }

            .hero h1 {
                font-size: clamp(42px, 5vw, 64px);
            }

            .hero-text {
                padding: 0 0 80px;
            }
        }

        /* ===== SHARED SECTION STYLES ===== */
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

        /* ===== FEATURES ===== */
        .features-intro {
            padding: 80px 0 40px;
        }

        .feature-section {
            padding: 60px 0;
        }

        .feature-section:nth-child(even) {
            background: var(--white);
        }

        .feature-layout {
            display: grid;
            grid-template-columns: 1fr;
            gap: 40px;
            align-items: center;
        }

        .feature-screenshot {
            padding: 32px;
            background: var(--surface);
            border-radius: 24px;
            border: 1px solid var(--border);
            min-height: 280px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .feature-section:nth-child(even) .feature-screenshot {
            background: var(--surface);
        }

        .feature-text h3 {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(28px, 4vw, 40px);
            font-weight: 500;
            margin-bottom: 16px;
            line-height: 1.2;
        }

        .feature-text p {
            color: var(--text-secondary);
            font-size: 17px;
            line-height: 1.7;
            margin-bottom: 16px;
        }

        .feature-text .feature-eyebrow {
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 12px;
        }

        @media (min-width: 1024px) {
            .features-intro {
                padding: 120px 0 40px;
            }

            .feature-section {
                padding: 80px 0;
            }

            .feature-layout {
                grid-template-columns: 1fr 1fr;
                gap: 80px;
            }

            .feature-section:nth-child(even) .feature-layout {
                direction: rtl;
            }

            .feature-section:nth-child(even) .feature-layout > * {
                direction: ltr;
            }
        }

        /* ===== SIMULATED UI ===== */
        .sim-summary {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            background: var(--white);
            border-radius: 12px;
            margin-bottom: 12px;
            font-size: 14px;
            font-weight: 600;
        }

        .sim-summary-stat {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .sim-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .sim-dot-green { background: var(--success); }
        .sim-dot-gold { background: var(--warning); }
        .sim-dot-red { background: var(--error); }

        .sim-progress-bar-full {
            height: 6px;
            background: var(--border-light);
            border-radius: 3px;
            overflow: hidden;
            margin-top: 8px;
        }

        .sim-progress-bar-full > div {
            height: 100%;
            border-radius: 3px;
            background: var(--accent);
        }

        .sim-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: var(--white);
            border-radius: 12px;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .sim-row:last-child { margin-bottom: 0; }

        .sim-row-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sim-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 600;
            color: white;
            flex-shrink: 0;
        }

        .sim-badge {
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .sim-badge-green { background: var(--accent-light); color: var(--accent-dark); }
        .sim-badge-gold { background: #FDF6E8; color: #96700A; }
        .sim-badge-gray { background: #F3F2F0; color: var(--text-secondary); }

        .sim-name { font-weight: 500; color: var(--text); }
        .sim-detail { color: var(--text-secondary); font-size: 12px; }

        .sim-btn-reminder {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: var(--accent-light);
            color: var(--accent-dark);
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 12px;
        }

        /* Invitation card mockup */
        .sim-invitation-card-wrap {
            padding: 24px;
            background: linear-gradient(135deg, #F5EDE4, var(--surface));
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sim-invitation-card {
            width: 100%;
            max-width: 320px;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            background: #1A1A1A;
        }

        .sim-invitation-img {
            height: 200px;
            background-size: cover;
            background-position: center 15%;
            position: relative;
        }

        .sim-invitation-body {
            padding: 28px 24px;
            text-align: center;
            color: white;
        }

        .sim-invitation-ornament {
            font-size: 11px;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.45);
            margin-bottom: 8px;
        }

        .sim-invitation-type {
            font-family: 'Cormorant Garamond', serif;
            font-style: italic;
            font-size: 15px;
            color: var(--blush);
            margin-bottom: 4px;
        }

        .sim-invitation-name {
            font-family: 'Cormorant Garamond', serif;
            font-size: 32px;
            font-weight: 400;
            line-height: 1.1;
            margin-bottom: 20px;
        }

        .sim-invitation-details {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 20px;
        }

        .sim-invitation-detail {
            font-size: 12px;
            color: rgba(255,255,255,0.7);
        }

        .sim-invitation-rsvp {
            display: inline-block;
            padding: 10px 28px;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.25);
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            color: white;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* Mini invitation preview */
        .sim-mini-invite {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            background: linear-gradient(135deg, var(--accent-light), #F5EDE4);
            border-radius: 12px;
            margin-bottom: 12px;
            font-size: 13px;
        }

        .sim-mini-invite-icon {
            width: 36px;
            height: 36px;
            background: var(--accent);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            flex-shrink: 0;
        }

        .sim-mini-invite-text {
            font-weight: 600;
            color: var(--accent-dark);
        }

        .sim-mini-invite-sub {
            font-size: 11px;
            color: var(--text-secondary);
        }

        /* Timeline (Toastmaster) */
        .sim-timeline {
            position: relative;
            padding-left: 24px;
        }

        .sim-timeline::before {
            content: '';
            position: absolute;
            left: 6px;
            top: 4px;
            bottom: 4px;
            width: 2px;
            background: var(--border);
        }

        .sim-timeline-item {
            position: relative;
            padding: 10px 0 10px 16px;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .sim-timeline-item::before {
            content: '';
            position: absolute;
            left: -22px;
            top: 16px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--accent);
            border: 2px solid var(--surface);
        }

        .sim-timeline-item.active::before {
            background: var(--warning);
            box-shadow: 0 0 0 4px rgba(196, 146, 45, 0.2);
        }

        .sim-timeline-left {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sim-timeline-time {
            font-size: 12px;
            color: var(--accent-dark);
            font-weight: 600;
        }

        .sim-timeline-title {
            font-weight: 500;
            color: var(--text);
        }

        .sim-timeline-icon {
            font-size: 16px;
            flex-shrink: 0;
        }

        .sim-timeline-badge {
            padding: 3px 10px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 600;
        }

        .sim-now-marker {
            position: absolute;
            left: -2px;
            width: calc(100% + 26px);
            height: 2px;
            background: var(--warning);
            z-index: 1;
        }

        .sim-now-label {
            position: absolute;
            right: 0;
            top: -8px;
            font-size: 10px;
            font-weight: 700;
            color: var(--warning);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ===== EVENT TYPES ===== */
        .event-types {
            padding: 80px 0;
        }

        .event-types-grid {
            display: flex;
            gap: 20px;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 8px;
        }

        .event-types-grid::-webkit-scrollbar { display: none; }

        .event-type-card {
            background: var(--white);
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid var(--border);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            flex: 0 0 200px;
            scroll-snap-align: start;
        }

        .event-type-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 30px 60px rgba(0,0,0,0.08);
            border-color: var(--accent);
        }

        .event-type-card img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            display: block;
        }

        .event-type-card-body {
            padding: 20px;
            text-align: center;
        }

        .event-type-card h3 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 20px;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .event-type-card p {
            font-size: 13px;
            color: var(--text-secondary);
        }

        /* Placeholder card (no image) */
        .event-type-placeholder {
            height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            background: linear-gradient(135deg, var(--surface), var(--border-light));
        }

        @media (min-width: 768px) {
            .event-types { padding: 120px 0; }
            .event-types-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 24px;
                overflow: visible;
            }
            .event-type-card { flex: none; }
        }

        @media (min-width: 1200px) {
            .event-types-grid {
                grid-template-columns: repeat(7, 1fr);
            }
        }

        /* ===== VENDOR / MARKETPLACE ===== */
        .vendors {
            padding: 80px 0;
            background: var(--surface);
        }

        .vendor-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .vendor-card {
            background: var(--white);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid var(--border);
            transition: all 0.3s ease;
        }

        .vendor-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.06);
        }

        .vendor-card-img {
            height: 140px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
        }

        .vendor-card-body {
            padding: 20px;
        }

        .vendor-card-category {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--accent);
            margin-bottom: 6px;
        }

        .vendor-card-name {
            font-family: 'Cormorant Garamond', serif;
            font-size: 20px;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .vendor-card-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .vendor-card-stars {
            color: var(--warning);
            letter-spacing: 1px;
        }

        .vendor-card-price {
            font-weight: 600;
            color: var(--text);
        }

        .vendor-cta {
            text-align: center;
            margin-top: 40px;
        }

        .vendor-cta a {
            color: var(--accent-dark);
            font-weight: 600;
            text-decoration: none;
            font-size: 15px;
            border-bottom: 2px solid var(--accent-light);
            padding-bottom: 2px;
            transition: border-color 0.3s;
        }

        .vendor-cta a:hover { border-color: var(--accent-dark); }

        @media (min-width: 768px) {
            .vendors { padding: 120px 0; }
            .vendor-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 1024px) {
            .vendor-grid {
                grid-template-columns: repeat(4, 1fr);
            }
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

        /* ===== MEMORIES SHOWCASE ===== */
        .memories-showcase {
            padding: 120px 0;
            background: var(--text);
            color: white;
            position: relative;
            overflow: hidden;
        }

        .memories-showcase::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(143, 165, 131, 0.1) 0%, transparent 50%);
        }

        .memories-content {
            display: grid;
            grid-template-columns: 1fr;
            gap: 60px;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .memories-text h2 {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(32px, 4vw, 48px);
            font-weight: 500;
            line-height: 1.2;
            margin-bottom: 24px;
        }

        .memories-text p {
            font-size: 17px;
            color: rgba(255,255,255,0.7);
            line-height: 1.8;
            margin-bottom: 32px;
        }

        .memories-features {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-bottom: 40px;
        }

        .memories-feature {
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }

        .memories-feature-icon {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .memories-feature-icon svg {
            width: 20px;
            height: 20px;
            color: var(--warning);
        }

        .memories-feature h4 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .memories-feature p {
            font-size: 14px;
            color: rgba(255,255,255,0.6);
            margin: 0;
        }

        /* Memories timeline mockup */
        .memories-preview { position: relative; }

        .memories-timeline-mock {
            background: #252525;
            border-radius: 24px;
            padding: 32px;
            max-width: 380px;
            margin: 0 auto;
            box-shadow: 0 40px 80px rgba(0,0,0,0.3);
        }

        .memories-timeline-header {
            text-align: center;
            margin-bottom: 28px;
        }

        .memories-timeline-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 24px;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .memories-timeline-sub {
            font-size: 13px;
            color: rgba(255,255,255,0.5);
        }

        .memories-timeline-line {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            overflow: hidden;
        }

        .memories-timeline-entry {
            flex: 1;
            text-align: center;
        }

        .memories-timeline-year {
            font-size: 11px;
            font-weight: 700;
            color: var(--warning);
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .memories-timeline-photo {
            width: 100%;
            aspect-ratio: 1;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 8px;
        }

        .memories-timeline-caption {
            font-size: 11px;
            color: rgba(255,255,255,0.6);
            line-height: 1.3;
        }

        .memories-guestbook-mock {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 20px;
        }

        .memories-guestbook-entry {
            padding: 16px;
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
        }

        .memories-guestbook-quote {
            font-family: 'Cormorant Garamond', serif;
            font-style: italic;
            font-size: 16px;
            color: rgba(255,255,255,0.8);
            line-height: 1.5;
            margin-bottom: 8px;
        }

        .memories-guestbook-author {
            font-size: 12px;
            color: rgba(255,255,255,0.4);
        }

        @media (min-width: 1024px) {
            .memories-content {
                grid-template-columns: 1fr 1fr;
                gap: 80px;
            }
        }

        /* ===== EARLY ADOPTER ===== */
        .early-adopter {
            padding: 120px 0;
            background: var(--white);
        }

        .early-adopter-content {
            max-width: 700px;
            margin: 0 auto;
            text-align: center;
        }

        .early-adopter h2 {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(28px, 4vw, 42px);
            font-weight: 500;
            line-height: 1.2;
            margin-bottom: 20px;
        }

        .early-adopter p {
            font-size: 17px;
            color: var(--text-secondary);
            line-height: 1.8;
            margin-bottom: 40px;
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

        /* ===== MOBILE NAV ===== */
        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
        }

        .mobile-toggle svg {
            width: 24px;
            height: 24px;
            color: var(--text);
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .mobile-toggle { display: block; }

            .nav-links {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: var(--white);
                flex-direction: column;
                padding: 24px;
                gap: 20px;
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                border-top: 1px solid var(--border);
            }

            .nav-links.open { display: flex; }

            .cta-box { padding: 60px 32px; border-radius: 28px; }
            .footer-content { flex-direction: column; gap: 24px; text-align: center; }
            .footer-links { flex-wrap: wrap; justify-content: center; gap: 24px; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                transition-duration: 0.01ms !important;
                animation-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>
    <header class="header" id="header">
        <div class="container">
            <a href="/" class="logo">Party<span>Parart</span></a>
            <button class="mobile-toggle" id="mobileToggle" aria-label="Åbn menu">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <nav class="nav-links" id="navLinks">
                <a href="#features">Funktioner</a>
                <a href="#events">Arrangementer</a>
                <a href="/app/auth/login.php">Log ind</a>
                <a href="/app/auth/register.php" class="btn btn-primary">Prøv gratis</a>
            </nav>
        </div>
    </header>

    <!-- HERO with parallax -->
    <section class="hero">
        <div class="hero-bg" id="heroBg"></div>
        <div class="container">
            <div class="hero-text">
                <div class="hero-free-badge">
                    &#10022; Alle funktioner gratis frem til juni 2026
                </div>
                <h1>Planlæg <em>festen</em> — vi holder styr på resten</h1>
                <p class="hero-description">
                    Fra studentergilde til bryllup, fra 5 til 500 gæster — PartyParart giver dig overblikket, så du kan nyde festen.
                </p>
                <a href="/app/auth/register.php" class="btn btn-primary">
                    Opret dit arrangement gratis
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                    </svg>
                </a>
            </div>
        </div>
    </section>

    <!-- FEATURES INTRO -->
    <div class="features-intro" id="features">
        <div class="container">
            <div class="section-header">
                <div class="section-eyebrow">Funktioner</div>
                <h2>Alt du behøver —<br>samlet &eacute;t&nbsp;sted</h2>
                <p>Fra invitationer til toastmaster-koordinering. Vi giver dig overblikket, så du kan fokusere på festen.</p>
            </div>
        </div>
    </div>

    <!-- Feature 1: Invitationer + RSVP -->
    <section class="feature-section">
        <div class="container">
            <div class="feature-layout">
                <div class="feature-screenshot sim-invitation-card-wrap">
                    <div class="sim-invitation-card">
                        <div class="sim-invitation-img" style="background-image: url('/billeder/kort-konfirmation.png');"></div>
                        <div class="sim-invitation-body">
                            <div class="sim-invitation-ornament">&#10045; Du er inviteret &#10045;</div>
                            <div class="sim-invitation-type">Konfirmation</div>
                            <div class="sim-invitation-name">Sofies<br>Konfirmation</div>
                            <div class="sim-invitation-details">
                                <div class="sim-invitation-detail">&#128197; Lørdag d. 18. maj 2025</div>
                                <div class="sim-invitation-detail">&#128336; Kl. 13:00 &mdash; Kirke &bull; Kl. 15:00 &mdash; Fest</div>
                                <div class="sim-invitation-detail">&#128205; Skovriderkroen, Charlottenlund</div>
                            </div>
                            <div class="sim-invitation-rsvp">Bekræft deltagelse</div>
                        </div>
                    </div>
                </div>
                <div class="feature-text">
                    <div class="feature-eyebrow">Invitationer</div>
                    <h3>Send smukke invitationer og følg hvert eneste svar</h3>
                    <p>Design din invitation, send den direkte til gæsterne, og se i realtid hvem der har åbnet, bekræftet eller afslået. Ingen manuelt regneark — alt opdateres automatisk.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Feature 2: Gæstehåndtering -->
    <section class="feature-section">
        <div class="container">
            <div class="feature-layout">
                <div class="feature-screenshot">
                    <div class="sim-summary">
                        <div class="sim-summary-stat"><div class="sim-dot sim-dot-green"></div> 24 bekræftet</div>
                        <div class="sim-summary-stat"><div class="sim-dot sim-dot-gold"></div> 8 afventer</div>
                        <div class="sim-summary-stat"><div class="sim-dot sim-dot-red"></div> 2 afbud</div>
                    </div>
                    <div class="sim-progress-bar-full">
                        <div style="width: 70%;"></div>
                    </div>
                    <div style="margin-top: 16px;">
                        <div class="sim-row">
                            <div class="sim-row-left">
                                <div class="sim-avatar" style="background:#6B8F5E;">AH</div>
                                <div>
                                    <div class="sim-name">Anna Hansen</div>
                                    <div class="sim-detail">2 voksne, 1 barn</div>
                                </div>
                            </div>
                            <div class="sim-badge sim-badge-green">Bekræftet</div>
                        </div>
                        <div class="sim-row">
                            <div class="sim-row-left">
                                <div class="sim-avatar" style="background:#C4922D;">KS</div>
                                <div>
                                    <div class="sim-name">Klaus Sørensen</div>
                                    <div class="sim-detail">1 voksen</div>
                                </div>
                            </div>
                            <div class="sim-badge sim-badge-gold">Afventer</div>
                        </div>
                        <div class="sim-row">
                            <div class="sim-row-left">
                                <div class="sim-avatar" style="background:#7C6DAF;">BM</div>
                                <div>
                                    <div class="sim-name">Birgitte Møller</div>
                                    <div class="sim-detail">2 voksne</div>
                                </div>
                            </div>
                            <div class="sim-badge sim-badge-gold">Afventer</div>
                        </div>
                    </div>
                    <div class="sim-btn-reminder">
                        &#9993; Send påmindelse til 8 afventende
                    </div>
                </div>
                <div class="feature-text">
                    <div class="feature-eyebrow">Gæstehåndtering</div>
                    <h3>Ved altid præcis hvem der kommer — og hvem du mangler svar fra</h3>
                    <p>Følg alle tilmeldinger ét sted. Se hvem der har bekræftet, hvem der mangler at svare, og hvor mange du skal planlægge efter. Slut med at tælle svar på tværs af sms'er og mails.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Feature 3: Toastmaster -->
    <section class="feature-section">
        <div class="container">
            <div class="feature-layout">
                <div class="feature-screenshot">
                    <div class="sim-timeline">
                        <div class="sim-timeline-item">
                            <div class="sim-timeline-left">
                                <div class="sim-timeline-time">15:30</div>
                                <div class="sim-timeline-icon">&#127908;</div>
                                <div class="sim-timeline-title">Velkomsttale — Far</div>
                            </div>
                            <div class="sim-timeline-badge sim-badge-green">Klar</div>
                        </div>
                        <div class="sim-timeline-item active" style="position:relative;">
                            <div class="sim-now-marker" style="top:0;">
                                <div class="sim-now-label">Nu</div>
                            </div>
                            <div class="sim-timeline-left">
                                <div class="sim-timeline-time">16:00</div>
                                <div class="sim-timeline-icon">&#127925;</div>
                                <div class="sim-timeline-title">Sang — Mormor & Morfar</div>
                            </div>
                            <div class="sim-timeline-badge sim-badge-gold">I gang</div>
                        </div>
                        <div class="sim-timeline-item">
                            <div class="sim-timeline-left">
                                <div class="sim-timeline-time">17:15</div>
                                <div class="sim-timeline-icon">&#127908;</div>
                                <div class="sim-timeline-title">Tale — Bedste veninde</div>
                            </div>
                            <div class="sim-timeline-badge sim-badge-green">Klar</div>
                        </div>
                        <div class="sim-timeline-item">
                            <div class="sim-timeline-left">
                                <div class="sim-timeline-time">18:00</div>
                                <div class="sim-timeline-icon">&#129513;</div>
                                <div class="sim-timeline-title">Quiz — Onkel Henrik</div>
                            </div>
                            <div class="sim-timeline-badge sim-badge-green">Klar</div>
                        </div>
                        <div class="sim-timeline-item">
                            <div class="sim-timeline-left">
                                <div class="sim-timeline-time">19:00</div>
                                <div class="sim-timeline-icon">&#127873;</div>
                                <div class="sim-timeline-title">Overraskelse — Kusine Marie</div>
                            </div>
                            <div class="sim-timeline-badge sim-badge-gray">Afventer</div>
                        </div>
                    </div>
                </div>
                <div class="feature-text">
                    <div class="feature-eyebrow">Toastmaster</div>
                    <h3>Giv din toastmaster et værktøj — ikke en hovedpine</h3>
                    <p>Del programmet med din toastmaster så de kan koordinere taler, sange og overraskelser. Alle ved hvornår de er på — ingen akavet stilhed, ingen overlap.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- EVENT TYPES -->
    <section class="event-types" id="events">
        <div class="container">
            <div class="section-header">
                <div class="section-eyebrow">Arrangementer</div>
                <h2>Til alle livets fester — store som små</h2>
                <p>Uanset hvilken fejring du planlægger, har vi værktøjerne til at gøre det uforglemmelig.</p>
            </div>
            <div class="event-types-grid">
                <div class="event-type-card">
                    <img src="/billeder/kort-konfirmation.png" alt="Konfirmation" loading="lazy" style="object-position: center 15%;">
                    <div class="event-type-card-body">
                        <h3>Konfirmation</h3>
                        <p>Fejr den store dag</p>
                    </div>
                </div>
                <div class="event-type-card">
                    <img src="/billeder/kort-bryllup.png" alt="Bryllup" loading="lazy" style="object-position: center 20%;">
                    <div class="event-type-card-body">
                        <h3>Bryllup</h3>
                        <p>Den perfekte dag</p>
                    </div>
                </div>
                <div class="event-type-card">
                    <img src="/billeder/kort-foedselsdag.png" alt="Fødselsdag" loading="lazy">
                    <div class="event-type-card-body">
                        <h3>Fødselsdag</h3>
                        <p>Fra 18 til 70 år</p>
                    </div>
                </div>
                <div class="event-type-card">
                    <img src="/billeder/kort-jubileum.png" alt="Jubilæum" loading="lazy">
                    <div class="event-type-card-body">
                        <h3>Jubilæum</h3>
                        <p>Fejr milepælene</p>
                    </div>
                </div>
                <div class="event-type-card">
                    <img src="/billeder/kort-studenterfest.png" alt="Studenterfest" loading="lazy">
                    <div class="event-type-card-body">
                        <h3>Studenterfest</h3>
                        <p>Fejr huen med stil</p>
                    </div>
                </div>
                <div class="event-type-card">
                    <img src="/billeder/kort-halloween.png" alt="Halloweenfest" loading="lazy">
                    <div class="event-type-card-body">
                        <h3>Halloweenfest</h3>
                        <p>Uhyggeligt sjovt</p>
                    </div>
                </div>
                <div class="event-type-card">
                    <img src="/billeder/kort-temafest.png" alt="Temafest" loading="lazy">
                    <div class="event-type-card-body">
                        <h3>Temafest</h3>
                        <p>Giv festen et tema</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- VENDOR / MARKETPLACE -->
    <section class="vendors">
        <div class="container">
            <div class="section-header">
                <div class="section-eyebrow">Markedsplads</div>
                <h2>Find de rette leverandører til din fest</h2>
                <p>Gennemse lokale caterere, fotografer, DJs, blomsterdekoratører og meget mere — direkte fra din festside. Læs anmeldelser, sammenlign priser, og book med ét klik.</p>
            </div>
            <div class="vendor-grid">
                <div class="vendor-card">
                    <img class="vendor-card-img" src="/billeder/vendor-catering.png" alt="Catering" loading="lazy" style="width:100%;object-fit:cover;">
                    <div class="vendor-card-body">
                        <div class="vendor-card-category">Catering</div>
                        <div class="vendor-card-name">Skovriderkroen</div>
                        <div class="vendor-card-meta">
                            <div class="vendor-card-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
                            <div class="vendor-card-price">Fra 149 kr/kuvert</div>
                        </div>
                    </div>
                </div>
                <div class="vendor-card">
                    <img class="vendor-card-img" src="/billeder/vendor-fotograf.png" alt="Fotograf" loading="lazy" style="width:100%;object-fit:cover;">
                    <div class="vendor-card-body">
                        <div class="vendor-card-category">Fotografi</div>
                        <div class="vendor-card-name">Fotograf Mikkelsen</div>
                        <div class="vendor-card-meta">
                            <div class="vendor-card-stars">&#9733;&#9733;&#9733;&#9733;&#9734;</div>
                            <div class="vendor-card-price">Fra 3.500 kr</div>
                        </div>
                    </div>
                </div>
                <div class="vendor-card">
                    <img class="vendor-card-img" src="/billeder/vendor-dj.png" alt="DJ" loading="lazy" style="width:100%;object-fit:cover;">
                    <div class="vendor-card-body">
                        <div class="vendor-card-category">Underholdning</div>
                        <div class="vendor-card-name">DJ Eastbeat</div>
                        <div class="vendor-card-meta">
                            <div class="vendor-card-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
                            <div class="vendor-card-price">Fra 4.000 kr</div>
                        </div>
                    </div>
                </div>
                <div class="vendor-card">
                    <img class="vendor-card-img" src="/billeder/vendor-blomster.png" alt="Blomster & dekoration" loading="lazy" style="width:100%;object-fit:cover;">
                    <div class="vendor-card-body">
                        <div class="vendor-card-category">Blomster & dekoration</div>
                        <div class="vendor-card-name">Blomster af Maria</div>
                        <div class="vendor-card-meta">
                            <div class="vendor-card-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
                            <div class="vendor-card-price">Fra 2.500 kr</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="vendor-cta">
                <a href="/app/auth/register.php">Se alle leverandører &rarr;</a>
            </div>
        </div>
    </section>

    <!-- ATMOSPHERE BREAK with parallax -->
    <section class="atmosphere-break" aria-label="Stemningsbillede">
        <p>Din fest. Dit overblik. Helt gratis.</p>
    </section>

    <!-- MEMORIES SHOWCASE -->
    <section class="memories-showcase">
        <div class="container">
            <div class="memories-content">
                <div class="memories-text">
                    <div class="section-eyebrow" style="color:var(--warning);">Minder</div>
                    <h2>Saml minderne fra hele livet — ét sted</h2>
                    <p>Lad gæsterne uploade billeder og beskeder, og byg en smuk mindelinje fra den fejredes liv. Fra barndomsbilleder til festens bedste øjeblikke — alt samlet i ét fælles arkiv.</p>

                    <div class="memories-features">
                        <div class="memories-feature">
                            <div class="memories-feature-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                            <div>
                                <h4>Fælles billedarkiv</h4>
                                <p>Gæsterne uploader direkte fra telefonen — alle billeder samles automatisk</p>
                            </div>
                        </div>
                        <div class="memories-feature">
                            <div class="memories-feature-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div>
                                <h4>Foto-mindelinje</h4>
                                <p>En tidslinje gennem livet — fra barndom til i dag, med billeder og hilsner</p>
                            </div>
                        </div>
                        <div class="memories-feature">
                            <div class="memories-feature-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                            </div>
                            <div>
                                <h4>Gæstebog med hilsner</h4>
                                <p>Personlige beskeder der bevares for altid</p>
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
                <div class="memories-preview">
                    <div class="memories-timeline-mock">
                        <div class="memories-timeline-header">
                            <div class="memories-timeline-title">Sofies Mindelinje</div>
                            <div class="memories-timeline-sub">24 billeder &bull; 8 hilsner</div>
                        </div>
                        <div class="memories-timeline-line">
                            <div class="memories-timeline-entry">
                                <div class="memories-timeline-year">2011</div>
                                <div class="memories-timeline-photo" style="background: url('/billeder/minde-barndom.png') center/cover;"></div>
                                <div class="memories-timeline-caption">Den lille prinsesse</div>
                            </div>
                            <div class="memories-timeline-entry">
                                <div class="memories-timeline-year">2016</div>
                                <div class="memories-timeline-photo" style="background: url('/billeder/minde-sport.png') center/cover;"></div>
                                <div class="memories-timeline-caption">Ridestjernen</div>
                            </div>
                            <div class="memories-timeline-entry">
                                <div class="memories-timeline-year">2022</div>
                                <div class="memories-timeline-photo" style="background: url('/billeder/minde-skole.png') center/cover;"></div>
                                <div class="memories-timeline-caption">Sidste skoledag</div>
                            </div>
                            <div class="memories-timeline-entry">
                                <div class="memories-timeline-year">2025</div>
                                <div class="memories-timeline-photo" style="background: url('/billeder/minde-konfirmation.png') center/cover;"></div>
                                <div class="memories-timeline-caption">Konfirmationen!</div>
                            </div>
                        </div>
                        <div class="memories-guestbook-mock">
                            <div class="memories-guestbook-entry">
                                <div class="memories-guestbook-quote">"Tak for en fantastisk dag! Vi er så stolte af dig"</div>
                                <div class="memories-guestbook-author">— Mormor & Morfar</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- EARLY ADOPTER -->
    <section class="early-adopter">
        <div class="container">
            <div class="early-adopter-content">
                <div class="section-eyebrow">Bliv en del af holdet</div>
                <h2>Vær blandt de første der prøver PartyParart</h2>
                <p>Vi er i gang med at bygge Danmarks bedste festplanlægger — og lige nu er alle funktioner gratis. Hjælp os med at gøre platformen endnu bedre.</p>
                <a href="/app/auth/register.php" class="btn btn-primary">
                    Opret dit arrangement gratis
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                    </svg>
                </a>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="cta">
        <div class="container">
            <div class="cta-box">
                <h2>Alt er gratis frem til juni — kom i gang på 30 sekunder</h2>
                <p>Opret dit arrangement, inviter dine gæster, og oplev hvordan det føles at have fuldstændig overblik.</p>
                <a href="/app/auth/register.php" class="btn">
                    Opret dit arrangement gratis
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
                    <a href="#">Kontakt</a>
                    <a href="#">Privatlivspolitik</a>
                </div>
                <div class="footer-copy">&copy; 2026 PartyParart. Alle rettigheder forbeholdes.</div>
            </div>
        </div>
    </footer>

    <script>
        // Mobile menu toggle
        const mobileToggle = document.getElementById('mobileToggle');
        const navLinks = document.getElementById('navLinks');
        mobileToggle.addEventListener('click', () => {
            navLinks.classList.toggle('open');
        });

        // Header scroll effect
        const header = document.getElementById('header');
        window.addEventListener('scroll', () => {
            header.classList.toggle('scrolled', window.scrollY > 50);
        }, { passive: true });

        // Hero parallax effect
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (!prefersReducedMotion) {
            const heroBg = document.getElementById('heroBg');
            window.addEventListener('scroll', () => {
                const scrolled = window.scrollY;
                if (scrolled < window.innerHeight) {
                    heroBg.style.transform = 'translateY(' + (scrolled * 0.3) + 'px)';
                }
            }, { passive: true });
        }

    </script>
</body>
</html>

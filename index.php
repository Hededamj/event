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
    <title>PartyParart — Din perfekte event planner | Planlæg festen nemt</title>
    <meta name="description" content="Vi gør festplanlægning til en leg. Invitationer, gæstehåndtering, bordplan, budget og meget mere — alt samlet ét sted. Din event-makker fra start til slut.">
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
            padding: 80px 0;
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
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .feature-card {
            background: var(--white);
            border-radius: 24px;
            border: 1px solid var(--border);
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .feature-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 24px 48px rgba(0,0,0,0.08);
            border-color: var(--accent);
        }

        .feature-screenshot {
            padding: 20px;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            min-height: 180px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .feature-card-body {
            padding: 24px;
        }

        .feature-card-body h3 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 22px;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .feature-card-body p {
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.6;
        }

        /* Simulated UI elements */
        .sim-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
            background: var(--white);
            border-radius: 10px;
            margin-bottom: 6px;
            font-size: 12px;
        }

        .sim-row:last-child {
            margin-bottom: 0;
        }

        .sim-row-left {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sim-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
            color: white;
            flex-shrink: 0;
        }

        .sim-badge {
            padding: 3px 10px;
            border-radius: 50px;
            font-size: 10px;
            font-weight: 600;
            white-space: nowrap;
        }

        .sim-badge-green {
            background: var(--accent-light);
            color: var(--accent-dark);
        }

        .sim-badge-gold {
            background: #FDF6E8;
            color: #96700A;
        }

        .sim-badge-blue {
            background: #E8F0FE;
            color: #1A56DB;
        }

        .sim-badge-gray {
            background: #F3F2F0;
            color: var(--text-secondary);
        }

        .sim-badge-blush {
            background: #FAE8E8;
            color: #8B4545;
        }

        .sim-name {
            font-weight: 500;
            color: var(--text);
        }

        .sim-detail {
            color: var(--text-secondary);
            font-size: 11px;
        }

        /* Budget progress bar */
        .sim-progress-wrap {
            margin-bottom: 10px;
        }

        .sim-progress-label {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            margin-bottom: 4px;
            color: var(--text-secondary);
        }

        .sim-progress-label span:last-child {
            font-weight: 600;
            color: var(--text);
        }

        .sim-progress {
            height: 8px;
            background: var(--border-light, #EDEAE5);
            border-radius: 4px;
            overflow: hidden;
        }

        .sim-progress-bar {
            height: 100%;
            border-radius: 4px;
            background: var(--accent);
        }

        /* Timeline */
        .sim-timeline {
            position: relative;
            padding-left: 20px;
        }

        .sim-timeline::before {
            content: '';
            position: absolute;
            left: 5px;
            top: 4px;
            bottom: 4px;
            width: 2px;
            background: var(--border);
        }

        .sim-timeline-item {
            position: relative;
            padding: 6px 0 6px 12px;
            font-size: 12px;
        }

        .sim-timeline-item::before {
            content: '';
            position: absolute;
            left: -19px;
            top: 12px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--accent);
            border: 2px solid var(--white);
        }

        .sim-timeline-time {
            font-size: 10px;
            color: var(--accent-dark);
            font-weight: 600;
        }

        .sim-timeline-title {
            font-weight: 500;
            color: var(--text);
        }

        /* Table layout for bordplan */
        .sim-tables {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .sim-table {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 2px solid var(--accent);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: var(--white);
            position: relative;
        }

        .sim-table-label {
            font-size: 9px;
            font-weight: 600;
            color: var(--accent-dark);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .sim-table-count {
            font-family: 'Cormorant Garamond', serif;
            font-size: 22px;
            font-weight: 500;
            color: var(--text);
            line-height: 1;
        }

        .sim-table-names {
            font-size: 8px;
            color: var(--text-secondary);
            text-align: center;
            line-height: 1.2;
        }

        /* Photo grid */
        .sim-photos {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 6px;
        }

        .sim-photo {
            aspect-ratio: 1;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        /* Checklist / wishlist */
        .sim-check-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 7px 12px;
            background: var(--white);
            border-radius: 10px;
            margin-bottom: 6px;
            font-size: 12px;
        }

        .sim-check-item:last-child {
            margin-bottom: 0;
        }

        .sim-checkbox {
            width: 18px;
            height: 18px;
            border-radius: 5px;
            border: 2px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .sim-checkbox.checked {
            background: var(--accent);
            border-color: var(--accent);
            color: white;
        }

        .sim-checkbox svg {
            width: 12px;
            height: 12px;
        }

        .sim-check-text {
            flex: 1;
            font-weight: 500;
            color: var(--text);
        }

        .sim-check-text.done {
            text-decoration: line-through;
            color: var(--text-secondary);
        }

        .sim-check-meta {
            font-size: 10px;
            color: var(--text-secondary);
        }

        @media (min-width: 768px) {
            .features { padding: 120px 0; }
            .features-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 24px;
            }
        }

        @media (min-width: 1024px) {
            .features-grid {
                grid-template-columns: repeat(4, 1fr);
            }
            .feature-screenshot {
                min-height: 200px;
            }
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
            border-radius: 32px;
            aspect-ratio: 9/16;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 0;
            position: relative;
            overflow: hidden;
        }

        .preview-slider {
            position: absolute;
            inset: 0;
            z-index: 0;
        }
        .preview-slide {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center 15%;
            opacity: 0;
            transition: opacity 1.2s ease-in-out;
        }
        .preview-slide.active {
            opacity: 1;
        }

        .preview-screen::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to bottom, transparent 25%, rgba(0,0,0,0.75) 100%);
        }

        .preview-content {
            position: relative;
            z-index: 1;
            padding: 32px 24px;
        }

        .preview-ornament {
            font-size: 13px;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.5);
            margin-bottom: 12px;
        }

        .preview-event-type {
            font-family: 'Cormorant Garamond', serif;
            font-style: italic;
            font-size: 16px;
            color: var(--blush);
            margin-bottom: 6px;
        }

        .preview-name {
            font-family: 'Cormorant Garamond', serif;
            font-size: 38px;
            font-weight: 400;
            line-height: 1.1;
            margin-bottom: 20px;
        }

        .preview-details {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 20px;
        }

        .preview-detail {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 12px;
            color: rgba(255,255,255,0.75);
        }

        .preview-detail svg {
            width: 14px;
            height: 14px;
            flex-shrink: 0;
            color: var(--blush);
        }

        .preview-rsvp {
            display: inline-block;
            padding: 10px 24px;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.25);
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            color: white;
            letter-spacing: 1px;
            text-transform: uppercase;
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
            .invitation-content { grid-template-columns: 1fr; }
            .invitation-preview { margin-top: 60px; }
        }

        @media (max-width: 768px) {
            .nav-links { display: none; }
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
                    <svg fill="currentColor" viewBox="0 0 20 20"><path d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z"/></svg>
                    Din perfekte event planner
                </div>
                <h1>Lad <em>festen</em> begynde</h1>
                <p class="hero-description">
                    Uanset om det er 20 eller 500 gæster — vi giver dig overblikket,
                    så du kan fokusere på det vigtige. Alt samlet ét sted: invitationer,
                    gæster, bordplan, budget og meget mere.
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

    <section class="features" id="features">
        <div class="container">
            <div class="section-header">
                <div class="section-eyebrow">Funktioner</div>
                <h2>Alt du behøver — samlet ét sted</h2>
                <p>Din event-makker fra start til slut. Vi giver dig overblikket, så du kan fokusere på det vigtige.</p>
            </div>
            <div class="features-grid">

                <!-- Invitationer -->
                <div class="feature-card">
                    <div class="feature-screenshot">
                        <div class="sim-row">
                            <div class="sim-row-left">
                                <div class="sim-avatar" style="background:#6B8F5E;">MJ</div>
                                <div>
                                    <div class="sim-name">Mette Jensen</div>
                                    <div class="sim-detail">Sendt i går</div>
                                </div>
                            </div>
                            <div class="sim-badge sim-badge-green">Bekræftet</div>
                        </div>
                        <div class="sim-row">
                            <div class="sim-row-left">
                                <div class="sim-avatar" style="background:#C4922D;">TN</div>
                                <div>
                                    <div class="sim-name">Thomas Nielsen</div>
                                    <div class="sim-detail">Sendt i går</div>
                                </div>
                            </div>
                            <div class="sim-badge sim-badge-gold">Åbnet</div>
                        </div>
                        <div class="sim-row">
                            <div class="sim-row-left">
                                <div class="sim-avatar" style="background:#D4A5A5;">LP</div>
                                <div>
                                    <div class="sim-name">Line Pedersen</div>
                                    <div class="sim-detail">Sendt for 2 dage siden</div>
                                </div>
                            </div>
                            <div class="sim-badge sim-badge-green">Bekræftet</div>
                        </div>
                    </div>
                    <div class="feature-card-body">
                        <h3>Invitationer</h3>
                        <p>Fuldt overblik over tilmeldingerne af dine gæster</p>
                    </div>
                </div>

                <!-- Gæstehåndtering -->
                <div class="feature-card">
                    <div class="feature-screenshot">
                        <div class="sim-row">
                            <div class="sim-row-left">
                                <div class="sim-avatar" style="background:#6B8F5E;">AH</div>
                                <div>
                                    <div class="sim-name">Anna Hansen</div>
                                    <div class="sim-detail">2 voksne, 1 barn</div>
                                </div>
                            </div>
                            <div class="sim-badge sim-badge-blush">Glutenfri</div>
                        </div>
                        <div class="sim-row">
                            <div class="sim-row-left">
                                <div class="sim-avatar" style="background:#7C6DAF;">KS</div>
                                <div>
                                    <div class="sim-name">Klaus Sørensen</div>
                                    <div class="sim-detail">1 voksen</div>
                                </div>
                            </div>
                            <div class="sim-badge sim-badge-gray">Ingen allergier</div>
                        </div>
                        <div class="sim-row">
                            <div class="sim-row-left">
                                <div class="sim-avatar" style="background:#C4922D;">BM</div>
                                <div>
                                    <div class="sim-name">Birgitte Møller</div>
                                    <div class="sim-detail">2 voksne</div>
                                </div>
                            </div>
                            <div class="sim-badge sim-badge-blush">Laktosefri</div>
                        </div>
                    </div>
                    <div class="feature-card-body">
                        <h3>Gæstehåndtering</h3>
                        <p>Allergier, børn, voksne — du har styr på alle detaljer</p>
                    </div>
                </div>

                <!-- Bordplan -->
                <div class="feature-card">
                    <div class="feature-screenshot">
                        <div class="sim-tables">
                            <div class="sim-table">
                                <div class="sim-table-label">Bord 1</div>
                                <div class="sim-table-count">8</div>
                                <div class="sim-table-names">Familie</div>
                            </div>
                            <div class="sim-table">
                                <div class="sim-table-label">Bord 2</div>
                                <div class="sim-table-count">6</div>
                                <div class="sim-table-names">Venner</div>
                            </div>
                            <div class="sim-table">
                                <div class="sim-table-label">Bord 3</div>
                                <div class="sim-table-count">8</div>
                                <div class="sim-table-names">Kolleger</div>
                            </div>
                        </div>
                    </div>
                    <div class="feature-card-body">
                        <h3>Bordplan</h3>
                        <p>Den rigtige person ved det rigtige bord, uden kaos</p>
                    </div>
                </div>

                <!-- Toastmaster -->
                <div class="feature-card">
                    <div class="feature-screenshot">
                        <div class="sim-timeline">
                            <div class="sim-timeline-item">
                                <div class="sim-timeline-time">15:30</div>
                                <div class="sim-timeline-title">Velkomsttale — Far</div>
                            </div>
                            <div class="sim-timeline-item">
                                <div class="sim-timeline-time">16:00</div>
                                <div class="sim-timeline-title">Sang — Mormor & Morfar</div>
                            </div>
                            <div class="sim-timeline-item">
                                <div class="sim-timeline-time">17:15</div>
                                <div class="sim-timeline-title">Tale — Bedste veninde</div>
                            </div>
                            <div class="sim-timeline-item">
                                <div class="sim-timeline-time">18:00</div>
                                <div class="sim-timeline-title">Quiz — Onkel Henrik</div>
                            </div>
                        </div>
                    </div>
                    <div class="feature-card-body">
                        <h3>Toastmaster</h3>
                        <p>Styring af taler så festen flyder uden akavede pauser</p>
                    </div>
                </div>

                <!-- Budget -->
                <div class="feature-card">
                    <div class="feature-screenshot">
                        <div class="sim-progress-wrap">
                            <div class="sim-progress-label"><span>Forplejning</span><span>8.500 kr</span></div>
                            <div class="sim-progress"><div class="sim-progress-bar" style="width:75%;"></div></div>
                        </div>
                        <div class="sim-progress-wrap">
                            <div class="sim-progress-label"><span>Lokale</span><span>5.000 kr</span></div>
                            <div class="sim-progress"><div class="sim-progress-bar" style="width:100%; background:var(--warning);"></div></div>
                        </div>
                        <div class="sim-progress-wrap">
                            <div class="sim-progress-label"><span>Underholdning</span><span>2.200 kr</span></div>
                            <div class="sim-progress"><div class="sim-progress-bar" style="width:45%;"></div></div>
                        </div>
                        <div class="sim-progress-wrap">
                            <div class="sim-progress-label"><span>Dekoration</span><span>1.800 kr</span></div>
                            <div class="sim-progress"><div class="sim-progress-bar" style="width:60%; background:var(--blush);"></div></div>
                        </div>
                    </div>
                    <div class="feature-card-body">
                        <h3>Budget</h3>
                        <p>Hold styr på hver en krone fra start til slut</p>
                    </div>
                </div>

                <!-- Ønskeliste -->
                <div class="feature-card">
                    <div class="feature-screenshot">
                        <div class="sim-check-item">
                            <div class="sim-checkbox checked"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></div>
                            <div class="sim-check-text done">AirPods Max</div>
                            <div class="sim-badge sim-badge-green" style="font-size:9px;">Reserveret</div>
                        </div>
                        <div class="sim-check-item">
                            <div class="sim-checkbox checked"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></div>
                            <div class="sim-check-text done">Rejsegavekort</div>
                            <div class="sim-badge sim-badge-green" style="font-size:9px;">Reserveret</div>
                        </div>
                        <div class="sim-check-item">
                            <div class="sim-checkbox"></div>
                            <div class="sim-check-text">Bluetooth højtaler</div>
                            <div class="sim-badge sim-badge-gray" style="font-size:9px;">Ledig</div>
                        </div>
                        <div class="sim-check-item">
                            <div class="sim-checkbox"></div>
                            <div class="sim-check-text">Pengegave</div>
                            <div class="sim-badge sim-badge-gray" style="font-size:9px;">Ledig</div>
                        </div>
                    </div>
                    <div class="feature-card-body">
                        <h3>Ønskeliste</h3>
                        <p>Ingen dobbeltgaver — gæsterne ser hvad der mangler</p>
                    </div>
                </div>

                <!-- Program -->
                <div class="feature-card">
                    <div class="feature-screenshot">
                        <div class="sim-timeline">
                            <div class="sim-timeline-item">
                                <div class="sim-timeline-time">13:00</div>
                                <div class="sim-timeline-title">Kirke — Vor Frue</div>
                            </div>
                            <div class="sim-timeline-item">
                                <div class="sim-timeline-time">15:00</div>
                                <div class="sim-timeline-title">Velkomstdrink i haven</div>
                            </div>
                            <div class="sim-timeline-item">
                                <div class="sim-timeline-time">16:30</div>
                                <div class="sim-timeline-title">Middag serveres</div>
                            </div>
                            <div class="sim-timeline-item">
                                <div class="sim-timeline-time">20:00</div>
                                <div class="sim-timeline-title">Fest & dans</div>
                            </div>
                        </div>
                    </div>
                    <div class="feature-card-body">
                        <h3>Program</h3>
                        <p>Alle ved hvad der sker og hvornår — ingen forvirring</p>
                    </div>
                </div>

                <!-- Minder -->
                <div class="feature-card">
                    <div class="feature-screenshot">
                        <div class="sim-photos">
                            <div class="sim-photo" style="background:var(--accent-light);">&#128247;</div>
                            <div class="sim-photo" style="background:#FDF6E8;">&#127880;</div>
                            <div class="sim-photo" style="background:#FAE8E8;">&#128150;</div>
                            <div class="sim-photo" style="background:#E8F0FE;">&#127874;</div>
                            <div class="sim-photo" style="background:var(--accent-light);">&#127881;</div>
                            <div class="sim-photo" style="background:#FDF6E8;">&#128248;</div>
                        </div>
                        <div style="margin-top:10px; padding:8px 12px; background:var(--white); border-radius:10px;">
                            <div style="font-size:11px; color:var(--text-secondary); font-style:italic;">"Tak for en fantastisk dag! Vi elsker jer &#10084;&#65039;"</div>
                            <div style="font-size:10px; color:var(--text-secondary); margin-top:2px;">— Mormor & Morfar</div>
                        </div>
                    </div>
                    <div class="feature-card-body">
                        <h3>Minder</h3>
                        <p>Fælles fotoarkiv, gæstebog og mindelinje</p>
                    </div>
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
                            <div class="preview-slider">
                                <div class="preview-slide active" style="background-image: url('/billeder/kort-konfirmation.png')"></div>
                                <div class="preview-slide" style="background-image: url('/billeder/Ungdom med basketball p%C3%A5 banen.png')"></div>
                                <div class="preview-slide" style="background-image: url('/billeder/Venner i skolegangen, smil og latter.png')"></div>
                            </div>
                            <div class="preview-content">
                                <div class="preview-ornament">&#10045; Du er inviteret &#10045;</div>
                                <div class="preview-event-type">Konfirmation</div>
                                <div class="preview-name">Sofies<br>Konfirmation</div>
                                <div class="preview-details">
                                    <div class="preview-detail">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        Lørdag d. 18. maj 2025
                                    </div>
                                    <div class="preview-detail">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        Kl. 13:00 - Kirke &bull; Kl. 15:00 - Fest
                                    </div>
                                    <div class="preview-detail">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        Skovriderkroen, Charlottenlund
                                    </div>
                                </div>
                                <div class="preview-rsvp">Bekræft deltagelse</div>
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
                    <img src="/billeder/kort-konfirmation.png" alt="Ung konfirmand med blomsterkrans i haven" loading="lazy" style="object-position: center 15%;">
                    <div class="event-type-card-body">
                        <h3>Konfirmation</h3>
                        <p>Fejr den store dag med stil</p>
                    </div>
                </div>
                <div class="event-type-card">
                    <img src="/billeder/kort-bryllup.png" alt="Brudepar danser første dans under lyskæder" loading="lazy" style="object-position: center 20%;">
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
                <h2>Vi gør festplanlægning til en leg</h2>
                <p>Start dit arrangement gratis i dag og oplev hvordan det føles at have en event-makker fra start til slut.</p>
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

        // Invitation preview slider
        const slides = document.querySelectorAll('.preview-slide');
        if (slides.length > 1) {
            let current = 0;
            setInterval(() => {
                slides[current].classList.remove('active');
                current = (current + 1) % slides.length;
                slides[current].classList.add('active');
            }, 4000);
        }
    </script>
</body>
</html>

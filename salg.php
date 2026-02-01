<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PartyParart - Alt til din fest, samlet ét sted</title>
    <meta name="description" content="Skab overblik over din fest. Gæsteliste, ønskeliste, menu, program og billeder - alt samlet på én smuk side. Perfekt til konfirmationer, bryllupper, fødselsdage og jubilæer.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,400;0,9..144,600;1,9..144,400&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --cream: #FDF8F3;
            --cream-dark: #F5EDE4;
            --burgundy: #722F37;
            --burgundy-deep: #5A252C;
            --burgundy-light: #8B4049;
            --gold: #C9A227;
            --gold-soft: #E8D5A3;
            --sage: #7A8B6E;
            --sage-soft: #B8C4AE;
            --ink: #2D2622;
            --ink-soft: #5C524A;
            --white: #FFFFFF;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--cream);
            color: var(--ink);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Typography */
        h1, h2, h3, h4 {
            font-family: 'Fraunces', serif;
            font-weight: 400;
            line-height: 1.2;
        }

        /* Utility */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        /* ===== NAVIGATION ===== */
        .nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            padding: 1rem 0;
            transition: all 0.4s ease;
        }

        .nav--scrolled {
            background: rgba(253, 248, 243, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(45, 38, 34, 0.08);
        }

        .nav__inner {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav__logo {
            font-family: 'Fraunces', serif;
            font-size: 1.5rem;
            color: var(--burgundy);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav__logo-icon {
            font-size: 1.2rem;
        }

        .nav__cta {
            background: var(--burgundy);
            color: var(--white);
            padding: 0.75rem 1.5rem;
            border-radius: 100px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .nav__cta:hover {
            background: var(--burgundy-deep);
            transform: translateY(-2px);
        }

        /* ===== HERO ===== */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 8rem 0 4rem;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 80%;
            height: 150%;
            background: radial-gradient(ellipse, rgba(201, 162, 39, 0.08) 0%, transparent 60%);
            pointer-events: none;
        }

        .hero::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -20%;
            width: 60%;
            height: 100%;
            background: radial-gradient(ellipse, rgba(114, 47, 55, 0.05) 0%, transparent 60%);
            pointer-events: none;
        }

        .hero__content {
            position: relative;
            z-index: 1;
            max-width: 800px;
        }

        .hero__badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--gold-soft);
            color: var(--burgundy);
            padding: 0.5rem 1rem;
            border-radius: 100px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 2rem;
            animation: fadeInUp 0.8s ease forwards;
        }

        .hero__title {
            font-size: clamp(2.5rem, 6vw, 4.5rem);
            color: var(--ink);
            margin-bottom: 1.5rem;
            animation: fadeInUp 0.8s ease 0.1s forwards;
            opacity: 0;
        }

        .hero__title em {
            font-style: italic;
            color: var(--burgundy);
        }

        .hero__subtitle {
            font-size: clamp(1.1rem, 2vw, 1.35rem);
            color: var(--ink-soft);
            max-width: 600px;
            margin-bottom: 2.5rem;
            animation: fadeInUp 0.8s ease 0.2s forwards;
            opacity: 0;
        }

        .hero__actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            animation: fadeInUp 0.8s ease 0.3s forwards;
            opacity: 0;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem 2rem;
            border-radius: 100px;
            font-family: 'DM Sans', sans-serif;
            font-size: 1rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
        }

        .btn--primary {
            background: var(--burgundy);
            color: var(--white);
        }

        .btn--primary:hover {
            background: var(--burgundy-deep);
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(114, 47, 55, 0.3);
        }

        .btn--secondary {
            background: var(--white);
            color: var(--ink);
            border: 2px solid var(--cream-dark);
        }

        .btn--secondary:hover {
            border-color: var(--burgundy);
            color: var(--burgundy);
        }

        .btn__play {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            background: var(--burgundy);
            color: var(--white);
            border-radius: 50%;
            font-size: 0.6rem;
            padding-left: 2px;
            transition: all 0.3s ease;
        }

        .btn--secondary:hover .btn__play {
            transform: scale(1.1);
        }

        /* ===== DEMO MODAL ===== */
        .demo-overlay {
            position: fixed;
            inset: 0;
            background: rgba(20, 15, 12, 0.92);
            backdrop-filter: blur(20px);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .demo-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .demo-close {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            width: 50px;
            height: 50px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 50%;
            color: var(--white);
            font-size: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }

        .demo-close:hover {
            background: rgba(255,255,255,0.2);
            transform: rotate(90deg);
        }

        .demo-container {
            width: 100%;
            max-width: 1100px;
            padding: 2rem;
            transform: scale(0.9) translateY(30px);
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .demo-overlay.active .demo-container {
            transform: scale(1) translateY(0);
        }

        .demo-header {
            text-align: center;
            margin-bottom: 2rem;
            opacity: 0;
            transform: translateY(-20px);
            transition: all 0.5s ease 0.2s;
        }

        .demo-overlay.active .demo-header {
            opacity: 1;
            transform: translateY(0);
        }

        .demo-header h2 {
            font-family: 'Fraunces', serif;
            font-size: 2rem;
            color: var(--white);
            margin-bottom: 0.5rem;
        }

        .demo-header p {
            color: rgba(255,255,255,0.6);
        }

        .demo-carousel {
            position: relative;
            overflow: hidden;
            border-radius: 24px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
        }

        .demo-slides {
            display: flex;
            transition: transform 0.7s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .demo-slide {
            min-width: 100%;
            padding: 2rem;
            display: flex;
            gap: 3rem;
            align-items: center;
        }

        @media (max-width: 768px) {
            .demo-slide {
                flex-direction: column;
                padding: 1.5rem;
                gap: 1.5rem;
            }
        }

        .demo-slide__info {
            flex: 0 0 280px;
            color: var(--white);
        }

        @media (max-width: 768px) {
            .demo-slide__info {
                flex: none;
                text-align: center;
            }
        }

        .demo-slide__icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--gold) 0%, var(--gold-soft) 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1.5rem;
            animation: iconFloat 4s ease-in-out infinite;
        }

        @media (max-width: 768px) {
            .demo-slide__icon {
                margin: 0 auto 1rem;
            }
        }

        @keyframes iconFloat {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-8px) rotate(3deg); }
        }

        .demo-slide__title {
            font-family: 'Fraunces', serif;
            font-size: 1.75rem;
            margin-bottom: 0.75rem;
        }

        .demo-slide__text {
            color: rgba(255,255,255,0.7);
            line-height: 1.7;
        }

        .demo-slide__mockup {
            flex: 1;
            min-height: 400px;
            position: relative;
        }

        @media (max-width: 768px) {
            .demo-slide__mockup {
                width: 100%;
                min-height: 300px;
            }
        }

        /* Device Frame */
        .device-frame {
            background: var(--white);
            border-radius: 20px;
            overflow: hidden;
            box-shadow:
                0 25px 80px rgba(0,0,0,0.4),
                0 0 0 1px rgba(255,255,255,0.1),
                inset 0 1px 0 rgba(255,255,255,0.2);
            height: 100%;
            display: flex;
            flex-direction: column;
            transform: perspective(1000px) rotateY(-5deg) rotateX(2deg);
            transition: transform 0.5s ease;
        }

        .demo-slide:hover .device-frame {
            transform: perspective(1000px) rotateY(-2deg) rotateX(1deg);
        }

        .device-frame__header {
            background: var(--cream);
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-bottom: 1px solid var(--cream-dark);
        }

        .device-frame__dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--cream-dark);
        }

        .device-frame__dot:nth-child(1) { background: #FF6B6B; }
        .device-frame__dot:nth-child(2) { background: #FFD93D; }
        .device-frame__dot:nth-child(3) { background: #6BCB77; }

        .device-frame__url {
            flex: 1;
            background: var(--white);
            border-radius: 6px;
            padding: 0.4rem 0.75rem;
            font-size: 0.75rem;
            color: var(--ink-soft);
            margin-left: 0.5rem;
        }

        .device-frame__content {
            flex: 1;
            padding: 1.25rem;
            overflow: hidden;
            background: var(--cream);
        }

        /* Mockup UI Components */
        .mockup-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--cream-dark);
        }

        .mockup-title {
            font-family: 'Fraunces', serif;
            font-size: 1.1rem;
            color: var(--burgundy);
        }

        .mockup-badge {
            background: var(--sage-soft);
            color: var(--sage);
            padding: 0.25rem 0.75rem;
            border-radius: 100px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        /* Guest List Mockup */
        .mockup-guest {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.6rem;
            background: var(--white);
            border-radius: 12px;
            margin-bottom: 0.5rem;
            animation: slideInRight 0.5s ease forwards;
            opacity: 0;
        }

        .demo-overlay.active .mockup-guest:nth-child(1) { animation-delay: 0.5s; }
        .demo-overlay.active .mockup-guest:nth-child(2) { animation-delay: 0.6s; }
        .demo-overlay.active .mockup-guest:nth-child(3) { animation-delay: 0.7s; }
        .demo-overlay.active .mockup-guest:nth-child(4) { animation-delay: 0.8s; }

        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .mockup-guest__avatar {
            width: 36px;
            height: 36px;
            background: var(--gold-soft);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }

        .mockup-guest__name {
            flex: 1;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .mockup-guest__status {
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
            border-radius: 100px;
        }

        .mockup-guest__status--yes {
            background: #E8F5E9;
            color: #2E7D32;
        }

        .mockup-guest__status--pending {
            background: #FFF8E1;
            color: #F57C00;
        }

        /* Wishlist Mockup */
        .mockup-wish {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: var(--white);
            border-radius: 12px;
            margin-bottom: 0.5rem;
            animation: scaleIn 0.4s ease forwards;
            opacity: 0;
        }

        .demo-overlay.active .mockup-wish:nth-child(1) { animation-delay: 0.5s; }
        .demo-overlay.active .mockup-wish:nth-child(2) { animation-delay: 0.65s; }
        .demo-overlay.active .mockup-wish:nth-child(3) { animation-delay: 0.8s; }

        @keyframes scaleIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }

        .mockup-wish__img {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--cream-dark) 0%, var(--cream) 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .mockup-wish__info {
            flex: 1;
        }

        .mockup-wish__name {
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 0.2rem;
        }

        .mockup-wish__price {
            font-size: 0.75rem;
            color: var(--ink-soft);
        }

        .mockup-wish__reserved {
            background: var(--burgundy);
            color: var(--white);
            font-size: 0.65rem;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
        }

        /* Menu Mockup */
        .mockup-timeline {
            position: relative;
            padding-left: 1.5rem;
        }

        .mockup-timeline::before {
            content: '';
            position: absolute;
            left: 5px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--gold-soft);
        }

        .mockup-time-item {
            position: relative;
            padding: 0.75rem;
            background: var(--white);
            border-radius: 12px;
            margin-bottom: 0.75rem;
            margin-left: 0.5rem;
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }

        .demo-overlay.active .mockup-time-item:nth-child(1) { animation-delay: 0.4s; }
        .demo-overlay.active .mockup-time-item:nth-child(2) { animation-delay: 0.55s; }
        .demo-overlay.active .mockup-time-item:nth-child(3) { animation-delay: 0.7s; }
        .demo-overlay.active .mockup-time-item:nth-child(4) { animation-delay: 0.85s; }

        .mockup-time-item::before {
            content: '';
            position: absolute;
            left: -1.35rem;
            top: 1rem;
            width: 12px;
            height: 12px;
            background: var(--gold);
            border-radius: 50%;
            border: 3px solid var(--cream);
        }

        .mockup-time-item__time {
            font-size: 0.7rem;
            color: var(--burgundy);
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .mockup-time-item__title {
            font-size: 0.85rem;
            font-weight: 500;
        }

        /* Gallery Mockup */
        .mockup-gallery {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.5rem;
        }

        .mockup-photo {
            aspect-ratio: 1;
            background: linear-gradient(135deg, var(--cream-dark) 0%, var(--sage-soft) 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            animation: popIn 0.4s ease forwards;
            opacity: 0;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .mockup-photo:hover {
            transform: scale(1.05);
        }

        .demo-overlay.active .mockup-photo:nth-child(1) { animation-delay: 0.3s; }
        .demo-overlay.active .mockup-photo:nth-child(2) { animation-delay: 0.4s; }
        .demo-overlay.active .mockup-photo:nth-child(3) { animation-delay: 0.5s; }
        .demo-overlay.active .mockup-photo:nth-child(4) { animation-delay: 0.6s; }
        .demo-overlay.active .mockup-photo:nth-child(5) { animation-delay: 0.7s; }
        .demo-overlay.active .mockup-photo:nth-child(6) { animation-delay: 0.8s; }

        @keyframes popIn {
            from { opacity: 0; transform: scale(0.5); }
            to { opacity: 1; transform: scale(1); }
        }

        .mockup-photo:nth-child(1) { background: linear-gradient(135deg, #FFE5EC 0%, #FFC2D1 100%); }
        .mockup-photo:nth-child(2) { background: linear-gradient(135deg, #E8F5E9 0%, #C8E6C9 100%); }
        .mockup-photo:nth-child(3) { background: linear-gradient(135deg, #FFF8E1 0%, #FFECB3 100%); }
        .mockup-photo:nth-child(4) { background: linear-gradient(135deg, #E3F2FD 0%, #BBDEFB 100%); }
        .mockup-photo:nth-child(5) { background: linear-gradient(135deg, #F3E5F5 0%, #E1BEE7 100%); }
        .mockup-photo:nth-child(6) { background: linear-gradient(135deg, #FFCCBC 0%, #FFAB91 100%); }

        /* Invitation Mockup */
        .mockup-invite {
            text-align: center;
            padding: 1rem;
        }

        .mockup-invite__greeting {
            font-family: 'Fraunces', serif;
            font-size: 1.1rem;
            font-style: italic;
            color: var(--burgundy);
            margin-bottom: 1rem;
            animation: fadeInUp 0.5s ease 0.2s forwards;
            opacity: 0;
        }

        .demo-overlay.active .mockup-invite__greeting {
            opacity: 1;
        }

        .mockup-invite__hero {
            background: linear-gradient(135deg, var(--burgundy) 0%, var(--burgundy-light) 100%);
            border-radius: 16px;
            padding: 2rem 1rem;
            color: var(--white);
            margin-bottom: 1rem;
            animation: fadeInUp 0.6s ease 0.3s forwards;
            opacity: 0;
        }

        .demo-overlay.active .mockup-invite__hero {
            opacity: 1;
        }

        .mockup-invite__title {
            font-family: 'Fraunces', serif;
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
        }

        .mockup-invite__subtitle {
            font-size: 0.8rem;
            opacity: 0.8;
        }

        .mockup-invite__btn {
            display: inline-block;
            background: var(--gold);
            color: var(--ink);
            padding: 0.6rem 1.5rem;
            border-radius: 100px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-top: 1rem;
            animation: pulse 2s ease infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(201, 162, 39, 0.4); }
            50% { transform: scale(1.02); box-shadow: 0 0 0 10px rgba(201, 162, 39, 0); }
        }

        /* Demo Navigation */
        .demo-nav {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .demo-nav__btn {
            width: 50px;
            height: 50px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 50%;
            color: var(--white);
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .demo-nav__btn:hover {
            background: rgba(255,255,255,0.2);
            transform: scale(1.1);
        }

        .demo-nav__btn:disabled {
            opacity: 0.3;
            cursor: not-allowed;
            transform: none;
        }

        .demo-nav__dots {
            display: flex;
            gap: 0.5rem;
        }

        .demo-nav__dot {
            width: 10px;
            height: 10px;
            background: rgba(255,255,255,0.2);
            border: none;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .demo-nav__dot.active {
            background: var(--gold);
            width: 28px;
            border-radius: 5px;
        }

        .demo-nav__dot:hover:not(.active) {
            background: rgba(255,255,255,0.4);
        }

        /* Progress Bar Indicator */
        .demo-progress {
            position: relative;
            margin-top: 1.5rem;
            padding: 0 2rem;
        }

        .demo-progress__track {
            height: 3px;
            background: rgba(255,255,255,0.15);
            border-radius: 3px;
            overflow: hidden;
        }

        .demo-progress__bar {
            height: 100%;
            background: linear-gradient(90deg, var(--gold) 0%, var(--gold-soft) 100%);
            border-radius: 3px;
            transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .demo-progress__bar::after {
            content: '';
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 12px;
            height: 12px;
            background: var(--gold);
            border-radius: 50%;
            box-shadow: 0 0 12px rgba(201, 162, 39, 0.6);
        }

        .demo-progress__labels {
            display: flex;
            justify-content: space-between;
            margin-top: 0.75rem;
        }

        .demo-progress__label {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.4);
            transition: all 0.3s ease;
            cursor: pointer;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
        }

        .demo-progress__label:hover {
            color: rgba(255,255,255,0.7);
        }

        .demo-progress__label.active {
            color: var(--gold);
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .demo-progress__labels {
                display: none;
            }

            .demo-progress {
                padding: 0 1rem;
            }
        }

        /* Shimmer effect on device frame */
        .device-frame::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                90deg,
                transparent 0%,
                rgba(255,255,255,0.1) 50%,
                transparent 100%
            );
            animation: shimmer 3s ease-in-out infinite;
        }

        @keyframes shimmer {
            0% { left: -100%; }
            50%, 100% { left: 100%; }
        }

        /* Floating elements */
        .hero__float {
            position: absolute;
            right: 5%;
            top: 50%;
            transform: translateY(-50%);
            display: none;
        }

        @media (min-width: 1024px) {
            .hero__float {
                display: block;
            }
        }

        .float-card {
            background: var(--white);
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 20px 60px rgba(45, 38, 34, 0.12);
            animation: float 6s ease-in-out infinite;
            width: 280px;
        }

        .float-card--1 {
            transform: rotate(-3deg);
            animation-delay: 0s;
        }

        .float-card--2 {
            transform: rotate(2deg) translateX(40px) translateY(-20px);
            animation-delay: 2s;
            margin-top: 1rem;
        }

        .float-card__icon {
            font-size: 2rem;
            margin-bottom: 0.75rem;
        }

        .float-card__title {
            font-family: 'Fraunces', serif;
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }

        .float-card__text {
            font-size: 0.85rem;
            color: var(--ink-soft);
        }

        /* ===== EVENT TYPES ===== */
        .events {
            padding: 6rem 0;
            background: var(--white);
        }

        .section-header {
            text-align: center;
            max-width: 600px;
            margin: 0 auto 4rem;
        }

        .section-label {
            display: inline-block;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            color: var(--gold);
            margin-bottom: 1rem;
        }

        .section-title {
            font-size: clamp(2rem, 4vw, 3rem);
            margin-bottom: 1rem;
        }

        .section-text {
            color: var(--ink-soft);
            font-size: 1.1rem;
        }

        .events__grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .event-card {
            background: var(--cream);
            border-radius: 24px;
            padding: 2rem;
            text-align: center;
            transition: all 0.4s ease;
            cursor: default;
            position: relative;
            overflow: hidden;
        }

        .event-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, var(--burgundy), var(--burgundy-light));
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        .event-card:hover::before {
            opacity: 1;
        }

        .event-card:hover {
            transform: translateY(-8px);
        }

        .event-card__content {
            position: relative;
            z-index: 1;
            transition: color 0.4s ease;
        }

        .event-card:hover .event-card__content {
            color: var(--white);
        }

        .event-card__icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
        }

        .event-card__title {
            font-family: 'Fraunces', serif;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .event-card__text {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        /* ===== FEATURES ===== */
        .features {
            padding: 6rem 0;
            background: linear-gradient(180deg, var(--cream) 0%, var(--cream-dark) 100%);
        }

        .features__grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .feature {
            display: flex;
            gap: 1.25rem;
            align-items: flex-start;
        }

        .feature__icon {
            width: 56px;
            height: 56px;
            background: var(--burgundy);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .feature__title {
            font-family: 'Fraunces', serif;
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
        }

        .feature__text {
            color: var(--ink-soft);
            font-size: 0.95rem;
        }

        /* ===== HOW IT WORKS ===== */
        .how {
            padding: 6rem 0;
            background: var(--burgundy);
            color: var(--white);
        }

        .how .section-label {
            color: var(--gold-soft);
        }

        .how .section-text {
            color: rgba(255,255,255,0.7);
        }

        .how__steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 3rem;
            margin-top: 4rem;
        }

        .step {
            text-align: center;
            position: relative;
        }

        .step__number {
            width: 60px;
            height: 60px;
            background: rgba(255,255,255,0.1);
            border: 2px solid var(--gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Fraunces', serif;
            font-size: 1.5rem;
            margin: 0 auto 1.5rem;
            color: var(--gold);
        }

        .step__title {
            font-family: 'Fraunces', serif;
            font-size: 1.35rem;
            margin-bottom: 0.75rem;
        }

        .step__text {
            color: rgba(255,255,255,0.7);
            font-size: 0.95rem;
        }

        /* ===== TESTIMONIAL ===== */
        .testimonial {
            padding: 6rem 0;
            background: var(--white);
        }

        .testimonial__card {
            max-width: 800px;
            margin: 0 auto;
            text-align: center;
        }

        .testimonial__quote {
            font-family: 'Fraunces', serif;
            font-size: clamp(1.5rem, 3vw, 2rem);
            font-style: italic;
            color: var(--ink);
            margin-bottom: 2rem;
            line-height: 1.5;
        }

        .testimonial__quote::before {
            content: '"';
            color: var(--gold);
            font-size: 3rem;
            display: block;
            margin-bottom: 1rem;
        }

        .testimonial__author {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }

        .testimonial__avatar {
            width: 56px;
            height: 56px;
            background: var(--sage-soft);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .testimonial__name {
            font-weight: 600;
        }

        .testimonial__event {
            color: var(--ink-soft);
            font-size: 0.9rem;
        }

        /* ===== CTA ===== */
        .cta {
            padding: 6rem 0;
            background: var(--cream);
            text-align: center;
        }

        .cta__box {
            background: linear-gradient(135deg, var(--burgundy) 0%, var(--burgundy-deep) 100%);
            border-radius: 32px;
            padding: 4rem 2rem;
            color: var(--white);
            position: relative;
            overflow: hidden;
        }

        .cta__box::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 50%;
            height: 100%;
            background: radial-gradient(ellipse at top right, rgba(201, 162, 39, 0.2) 0%, transparent 60%);
            pointer-events: none;
        }

        .cta__title {
            font-size: clamp(2rem, 4vw, 3rem);
            margin-bottom: 1rem;
            position: relative;
        }

        .cta__text {
            font-size: 1.1rem;
            opacity: 0.85;
            max-width: 500px;
            margin: 0 auto 2rem;
            position: relative;
        }

        .cta__actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            position: relative;
        }

        .cta .btn--primary {
            background: var(--gold);
            color: var(--ink);
        }

        .cta .btn--primary:hover {
            background: var(--gold-soft);
        }

        .cta .btn--secondary {
            background: transparent;
            border-color: rgba(255,255,255,0.3);
            color: var(--white);
        }

        .cta .btn--secondary:hover {
            border-color: var(--white);
            background: rgba(255,255,255,0.1);
        }

        /* ===== FOOTER ===== */
        .footer {
            padding: 3rem 0;
            background: var(--ink);
            color: rgba(255,255,255,0.6);
            text-align: center;
        }

        .footer__logo {
            font-family: 'Fraunces', serif;
            font-size: 1.25rem;
            color: var(--white);
            margin-bottom: 1rem;
        }

        .footer__text {
            font-size: 0.9rem;
        }

        .footer__link {
            color: var(--gold);
            text-decoration: none;
        }

        .footer__link:hover {
            text-decoration: underline;
        }

        /* ===== ANIMATIONS ===== */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0) rotate(-3deg);
            }
            50% {
                transform: translateY(-15px) rotate(-3deg);
            }
        }

        /* Scroll animations */
        .animate-on-scroll {
            opacity: 0;
            transform: translateY(40px);
            transition: all 0.8s ease;
        }

        .animate-on-scroll.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .hero {
                padding: 7rem 0 3rem;
                min-height: auto;
            }

            .events, .features, .how, .testimonial, .cta {
                padding: 4rem 0;
            }

            .cta__box {
                padding: 3rem 1.5rem;
            }

            .hero__actions {
                flex-direction: column;
            }

            .hero__actions .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<!-- Navigation -->
<nav class="nav" id="nav">
    <div class="container nav__inner">
        <a href="#" class="nav__logo">
            <span class="nav__logo-icon">✦</span>
            PartyParart
        </a>
        <a href="#kontakt" class="nav__cta">Kom i gang</a>
    </div>
</nav>

<!-- Hero -->
<section class="hero">
    <div class="container">
        <div class="hero__content">
            <div class="hero__badge">
                <span>✨</span>
                Gør din fest uforglemmelig
            </div>
            <h1 class="hero__title">
                Alt til din fest,<br>
                samlet <em>ét sted</em>
            </h1>
            <p class="hero__subtitle">
                Skab overblik og glæd dine gæster med en smuk, personlig festside.
                Gæsteliste, ønskeliste, menu, program og billeder -
                alt hvad du behøver til den perfekte fest.
            </p>
            <div class="hero__actions">
                <a href="#kontakt" class="btn btn--primary">
                    Start din festside
                    <span>→</span>
                </a>
                <button class="btn btn--secondary" onclick="openDemo()">
                    <span class="btn__play">▶</span>
                    Se demo
                </button>
            </div>
        </div>

        <div class="hero__float">
            <div class="float-card float-card--1">
                <span class="float-card__icon">👥</span>
                <div class="float-card__title">32 har svaret</div>
                <div class="float-card__text">28 kommer • 4 afbud</div>
            </div>
            <div class="float-card float-card--2">
                <span class="float-card__icon">🎁</span>
                <div class="float-card__title">Ønskeliste</div>
                <div class="float-card__text">12 af 18 ønsker reserveret</div>
            </div>
        </div>
    </div>
</section>

<!-- Event Types -->
<section class="events" id="arrangementer">
    <div class="container">
        <header class="section-header animate-on-scroll">
            <span class="section-label">Til enhver lejlighed</span>
            <h2 class="section-title">Én platform, mange fester</h2>
            <p class="section-text">
                Uanset hvad du fejrer, hjælper vi dig med at samle alle trådene
            </p>
        </header>

        <div class="events__grid">
            <div class="event-card animate-on-scroll">
                <div class="event-card__content">
                    <span class="event-card__icon">⛪</span>
                    <h3 class="event-card__title">Konfirmation</h3>
                    <p class="event-card__text">Den store dag fortjener det bedste overblik</p>
                </div>
            </div>
            <div class="event-card animate-on-scroll">
                <div class="event-card__content">
                    <span class="event-card__icon">💒</span>
                    <h3 class="event-card__title">Bryllup</h3>
                    <p class="event-card__text">Saml alle detaljer til jeres drømmebryllup</p>
                </div>
            </div>
            <div class="event-card animate-on-scroll">
                <div class="event-card__content">
                    <span class="event-card__icon">🎂</span>
                    <h3 class="event-card__title">Fødselsdag</h3>
                    <p class="event-card__text">Runde fødselsdage og milepæle</p>
                </div>
            </div>
            <div class="event-card animate-on-scroll">
                <div class="event-card__content">
                    <span class="event-card__icon">🥂</span>
                    <h3 class="event-card__title">Jubilæum</h3>
                    <p class="event-card__text">Fejr årene sammen med stil</p>
                </div>
            </div>
            <div class="event-card animate-on-scroll">
                <div class="event-card__content">
                    <span class="event-card__icon">👶</span>
                    <h3 class="event-card__title">Barnedåb</h3>
                    <p class="event-card__text">Velkomst til de mindste</p>
                </div>
            </div>
            <div class="event-card animate-on-scroll">
                <div class="event-card__content">
                    <span class="event-card__icon">🎉</span>
                    <h3 class="event-card__title">Andet</h3>
                    <p class="event-card__text">Dimission, afsked, eller bare en god fest</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features -->
<section class="features" id="funktioner">
    <div class="container">
        <header class="section-header animate-on-scroll">
            <span class="section-label">Alt du behøver</span>
            <h2 class="section-title">Skab overblik, slip for stress</h2>
            <p class="section-text">
                Din festside samler alt det praktiske, så du kan fokusere på det vigtige - at fejre
            </p>
        </header>

        <div class="features__grid">
            <div class="feature animate-on-scroll">
                <div class="feature__icon">👥</div>
                <div>
                    <h3 class="feature__title">Smart gæsteliste</h3>
                    <p class="feature__text">Hold styr på tilmeldinger, afbud, antal personer og kostbehov. Gæster tilmelder sig selv med deres personlige kode.</p>
                </div>
            </div>
            <div class="feature animate-on-scroll">
                <div class="feature__icon">🎁</div>
                <div>
                    <h3 class="feature__title">Ønskeliste med reservation</h3>
                    <p class="feature__text">Gæster kan reservere gaver anonymt - så undgår I dobbeltgaver og konfirmanden kan ikke se hvem der giver hvad.</p>
                </div>
            </div>
            <div class="feature animate-on-scroll">
                <div class="feature__icon">🍽️</div>
                <div>
                    <h3 class="feature__title">Menu & program</h3>
                    <p class="feature__text">Vis gæsterne hvad der serveres og hvornår. Opdatér nemt når planerne ændrer sig.</p>
                </div>
            </div>
            <div class="feature animate-on-scroll">
                <div class="feature__icon">📷</div>
                <div>
                    <h3 class="feature__title">Delte billeder</h3>
                    <p class="feature__text">Alle gæster kan uploade deres billeder til et fælles galleri. Saml minderne ét sted.</p>
                </div>
            </div>
            <div class="feature animate-on-scroll">
                <div class="feature__icon">📱</div>
                <div>
                    <h3 class="feature__title">Mobilvenligt</h3>
                    <p class="feature__text">Både gæster og arrangører kan bruge siden fra mobilen. Perfekt til at tjekke detaljer på farten.</p>
                </div>
            </div>
            <div class="feature animate-on-scroll">
                <div class="feature__icon">✨</div>
                <div>
                    <h3 class="feature__title">Smukt design</h3>
                    <p class="feature__text">En elegant invitation gæsterne vil huske. Tilpas farver og billeder til jeres fest.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- How it works -->
<section class="how">
    <div class="container">
        <header class="section-header animate-on-scroll">
            <span class="section-label">Sådan virker det</span>
            <h2 class="section-title">Klar på 3 simple trin</h2>
            <p class="section-text">
                Vi sætter din festside op - du fokuserer på festen
            </p>
        </header>

        <div class="how__steps">
            <div class="step animate-on-scroll">
                <div class="step__number">1</div>
                <h3 class="step__title">Fortæl os om festen</h3>
                <p class="step__text">Kontakt os med dato, antal gæster og hvad I ønsker. Vi opretter jeres personlige festside.</p>
            </div>
            <div class="step animate-on-scroll">
                <div class="step__number">2</div>
                <h3 class="step__title">Tilføj indhold</h3>
                <p class="step__text">Log ind og tilføj gæster, ønskeliste, menu og program. Konfirmanden kan selv styre sin ønskeliste.</p>
            </div>
            <div class="step animate-on-scroll">
                <div class="step__number">3</div>
                <h3 class="step__title">Del med gæsterne</h3>
                <p class="step__text">Send det personlige link til dine gæster. De tilmelder sig, reserverer gaver og ser alle detaljer.</p>
            </div>
        </div>
    </div>
</section>

<!-- Testimonial -->
<section class="testimonial">
    <div class="container">
        <div class="testimonial__card animate-on-scroll">
            <blockquote class="testimonial__quote">
                Endelig et sted hvor alt er samlet! Sofie kunne selv tilføje sine ønsker,
                gæsterne fandt selv rundt, og vi slap for at svare på 100 spørgsmål.
                Det gjorde planlægningen så meget nemmere.
            </blockquote>
            <div class="testimonial__author">
                <div class="testimonial__avatar">👩</div>
                <div>
                    <div class="testimonial__name">Maria H.</div>
                    <div class="testimonial__event">Konfirmation 2025</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta" id="kontakt">
    <div class="container">
        <div class="cta__box animate-on-scroll">
            <h2 class="cta__title">Klar til at komme i gang?</h2>
            <p class="cta__text">
                Kontakt os i dag og få din egen festside. Vi hjælper dig med at skabe overblik og glæde dine gæster.
            </p>
            <div class="cta__actions">
                <a href="mailto:kontakt@hededam.dk" class="btn btn--primary">
                    Skriv til os
                    <span>→</span>
                </a>
                <a href="sofie/" class="btn btn--secondary">
                    Se eksempel
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Demo Modal -->
<div class="demo-overlay" id="demoOverlay">
    <button class="demo-close" onclick="closeDemo()">×</button>

    <div class="demo-container">
        <div class="demo-header">
            <h2>Se hvordan det virker</h2>
            <p>Udforsk alle funktionerne i PartyParart</p>
        </div>

        <div class="demo-carousel">
            <div class="demo-slides" id="demoSlides">

                <!-- Slide 1: Gæsteliste -->
                <div class="demo-slide">
                    <div class="demo-slide__info">
                        <div class="demo-slide__icon">👥</div>
                        <h3 class="demo-slide__title">Smart gæsteliste</h3>
                        <p class="demo-slide__text">Hold styr på hvem der kommer, allergier og særlige ønsker. Gæster tilmelder sig selv med en personlig kode.</p>
                    </div>
                    <div class="demo-slide__mockup">
                        <div class="device-frame">
                            <div class="device-frame__header">
                                <span class="device-frame__dot"></span>
                                <span class="device-frame__dot"></span>
                                <span class="device-frame__dot"></span>
                                <span class="device-frame__url">partyparart.dk/sofie/admin</span>
                            </div>
                            <div class="device-frame__content">
                                <div class="mockup-header">
                                    <span class="mockup-title">Gæsteliste</span>
                                    <span class="mockup-badge">32 gæster</span>
                                </div>
                                <div class="mockup-guest">
                                    <div class="mockup-guest__avatar">👩</div>
                                    <div class="mockup-guest__name">Mormor & Morfar</div>
                                    <span class="mockup-guest__status mockup-guest__status--yes">Kommer ✓</span>
                                </div>
                                <div class="mockup-guest">
                                    <div class="mockup-guest__avatar">👨</div>
                                    <div class="mockup-guest__name">Onkel Peter + 2</div>
                                    <span class="mockup-guest__status mockup-guest__status--yes">Kommer ✓</span>
                                </div>
                                <div class="mockup-guest">
                                    <div class="mockup-guest__avatar">👩</div>
                                    <div class="mockup-guest__name">Familie Jensen</div>
                                    <span class="mockup-guest__status mockup-guest__status--pending">Venter</span>
                                </div>
                                <div class="mockup-guest">
                                    <div class="mockup-guest__avatar">👴</div>
                                    <div class="mockup-guest__name">Farfar</div>
                                    <span class="mockup-guest__status mockup-guest__status--yes">Kommer ✓</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Slide 2: Ønskeliste -->
                <div class="demo-slide">
                    <div class="demo-slide__info">
                        <div class="demo-slide__icon">🎁</div>
                        <h3 class="demo-slide__title">Ønskeliste med reservation</h3>
                        <p class="demo-slide__text">Gæster kan anonymt reservere gaver. Konfirmanden ser ikke hvem der giver hvad - perfekt til overraskelser.</p>
                    </div>
                    <div class="demo-slide__mockup">
                        <div class="device-frame">
                            <div class="device-frame__header">
                                <span class="device-frame__dot"></span>
                                <span class="device-frame__dot"></span>
                                <span class="device-frame__dot"></span>
                                <span class="device-frame__url">partyparart.dk/sofie/oensker</span>
                            </div>
                            <div class="device-frame__content">
                                <div class="mockup-header">
                                    <span class="mockup-title">Sofies ønsker</span>
                                    <span class="mockup-badge">8 af 12</span>
                                </div>
                                <div class="mockup-wish">
                                    <div class="mockup-wish__img">🎧</div>
                                    <div class="mockup-wish__info">
                                        <div class="mockup-wish__name">AirPods Pro</div>
                                        <div class="mockup-wish__price">1.899 kr</div>
                                    </div>
                                    <span class="mockup-wish__reserved">Reserveret</span>
                                </div>
                                <div class="mockup-wish">
                                    <div class="mockup-wish__img">👟</div>
                                    <div class="mockup-wish__info">
                                        <div class="mockup-wish__name">Nike Dunks</div>
                                        <div class="mockup-wish__price">1.200 kr</div>
                                    </div>
                                    <span class="mockup-wish__reserved">Reserveret</span>
                                </div>
                                <div class="mockup-wish">
                                    <div class="mockup-wish__img">💳</div>
                                    <div class="mockup-wish__info">
                                        <div class="mockup-wish__name">Gavekort til Matas</div>
                                        <div class="mockup-wish__price">500 kr</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Slide 3: Program -->
                <div class="demo-slide">
                    <div class="demo-slide__info">
                        <div class="demo-slide__icon">📋</div>
                        <h3 class="demo-slide__title">Menu & program</h3>
                        <p class="demo-slide__text">Vis gæsterne hvad der serveres og hvornår. Alle ved hvad de kan forvente - ingen overraskelser.</p>
                    </div>
                    <div class="demo-slide__mockup">
                        <div class="device-frame">
                            <div class="device-frame__header">
                                <span class="device-frame__dot"></span>
                                <span class="device-frame__dot"></span>
                                <span class="device-frame__dot"></span>
                                <span class="device-frame__url">partyparart.dk/sofie/program</span>
                            </div>
                            <div class="device-frame__content">
                                <div class="mockup-header">
                                    <span class="mockup-title">Dagens program</span>
                                </div>
                                <div class="mockup-timeline">
                                    <div class="mockup-time-item">
                                        <div class="mockup-time-item__time">12:00</div>
                                        <div class="mockup-time-item__title">Kirken - Konfirmation</div>
                                    </div>
                                    <div class="mockup-time-item">
                                        <div class="mockup-time-item__time">14:00</div>
                                        <div class="mockup-time-item__title">Ankomst & velkomstdrink</div>
                                    </div>
                                    <div class="mockup-time-item">
                                        <div class="mockup-time-item__time">15:00</div>
                                        <div class="mockup-time-item__title">Frokostbuffet serveres</div>
                                    </div>
                                    <div class="mockup-time-item">
                                        <div class="mockup-time-item__time">17:00</div>
                                        <div class="mockup-time-item__title">Kage & kaffe</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Slide 4: Billeder -->
                <div class="demo-slide">
                    <div class="demo-slide__info">
                        <div class="demo-slide__icon">📸</div>
                        <h3 class="demo-slide__title">Fælles fotogalleri</h3>
                        <p class="demo-slide__text">Alle gæster kan uploade deres billeder til ét samlet galleri. Minderne bliver aldrig spredt ud over 10 telefoner igen.</p>
                    </div>
                    <div class="demo-slide__mockup">
                        <div class="device-frame">
                            <div class="device-frame__header">
                                <span class="device-frame__dot"></span>
                                <span class="device-frame__dot"></span>
                                <span class="device-frame__dot"></span>
                                <span class="device-frame__url">partyparart.dk/sofie/billeder</span>
                            </div>
                            <div class="device-frame__content">
                                <div class="mockup-header">
                                    <span class="mockup-title">Billeder fra festen</span>
                                    <span class="mockup-badge">24 billeder</span>
                                </div>
                                <div class="mockup-gallery">
                                    <div class="mockup-photo">📷</div>
                                    <div class="mockup-photo">🎉</div>
                                    <div class="mockup-photo">👨‍👩‍👧</div>
                                    <div class="mockup-photo">🎂</div>
                                    <div class="mockup-photo">🥂</div>
                                    <div class="mockup-photo">💐</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Slide 5: Invitation -->
                <div class="demo-slide">
                    <div class="demo-slide__info">
                        <div class="demo-slide__icon">✉️</div>
                        <h3 class="demo-slide__title">Smuk invitation</h3>
                        <p class="demo-slide__text">Hver gæst får deres personlige invitationsside med alle detaljer. Elegant, mobilvenlig og nem at dele.</p>
                    </div>
                    <div class="demo-slide__mockup">
                        <div class="device-frame">
                            <div class="device-frame__header">
                                <span class="device-frame__dot"></span>
                                <span class="device-frame__dot"></span>
                                <span class="device-frame__dot"></span>
                                <span class="device-frame__url">partyparart.dk/sofie/?kode=123456</span>
                            </div>
                            <div class="device-frame__content">
                                <div class="mockup-invite">
                                    <div class="mockup-invite__greeting">Kære Mormor og Morfar</div>
                                    <div class="mockup-invite__hero">
                                        <div class="mockup-invite__title">Sofies Konfirmation</div>
                                        <div class="mockup-invite__subtitle">Lørdag d. 10. maj 2025</div>
                                        <div class="mockup-invite__btn">Tilmeld dig →</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="demo-nav">
            <button class="demo-nav__btn" id="demoPrev" onclick="prevSlide()">←</button>
            <div class="demo-nav__dots" id="demoDots"></div>
            <button class="demo-nav__btn" id="demoNext" onclick="nextSlide()">→</button>
        </div>

        <div class="demo-progress">
            <div class="demo-progress__track">
                <div class="demo-progress__bar" id="demoProgress"></div>
            </div>
            <div class="demo-progress__labels">
                <span class="demo-progress__label active" onclick="goToSlide(0)">Gæster</span>
                <span class="demo-progress__label" onclick="goToSlide(1)">Ønsker</span>
                <span class="demo-progress__label" onclick="goToSlide(2)">Program</span>
                <span class="demo-progress__label" onclick="goToSlide(3)">Billeder</span>
                <span class="demo-progress__label" onclick="goToSlide(4)">Invitation</span>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="footer__logo">✦ PartyParart</div>
        <p class="footer__text">
            Skabt med kærlighed i Danmark
            <br>
            <a href="mailto:kontakt@hededam.dk" class="footer__link">kontakt@hededam.dk</a>
        </p>
    </div>
</footer>

<script>
// Scroll-triggered animations
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('visible');
        }
    });
}, { threshold: 0.1 });

document.querySelectorAll('.animate-on-scroll').forEach(el => {
    observer.observe(el);
});

// Nav scroll effect
window.addEventListener('scroll', () => {
    const nav = document.getElementById('nav');
    if (window.scrollY > 50) {
        nav.classList.add('nav--scrolled');
    } else {
        nav.classList.remove('nav--scrolled');
    }
});

// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});

// ===== DEMO MODAL =====
let currentSlide = 0;
const totalSlides = 5;
let touchStartX = 0;
let touchEndX = 0;

// Initialize dots
function initDots() {
    const dotsContainer = document.getElementById('demoDots');
    dotsContainer.innerHTML = '';
    for (let i = 0; i < totalSlides; i++) {
        const dot = document.createElement('button');
        dot.className = 'demo-nav__dot' + (i === 0 ? ' active' : '');
        dot.onclick = () => goToSlide(i);
        dotsContainer.appendChild(dot);
    }
}

function updateSlide() {
    const slides = document.getElementById('demoSlides');
    slides.style.transform = `translateX(-${currentSlide * 100}%)`;

    // Update dots
    document.querySelectorAll('.demo-nav__dot').forEach((dot, i) => {
        dot.classList.toggle('active', i === currentSlide);
    });

    // Update progress bar
    const progress = ((currentSlide + 1) / totalSlides) * 100;
    document.getElementById('demoProgress').style.width = progress + '%';

    // Update labels
    document.querySelectorAll('.demo-progress__label').forEach((label, i) => {
        label.classList.toggle('active', i === currentSlide);
    });

    // Update buttons
    document.getElementById('demoPrev').disabled = currentSlide === 0;
    document.getElementById('demoNext').disabled = currentSlide === totalSlides - 1;
}

function nextSlide() {
    if (currentSlide < totalSlides - 1) {
        currentSlide++;
        updateSlide();
    }
}

function prevSlide() {
    if (currentSlide > 0) {
        currentSlide--;
        updateSlide();
    }
}

function goToSlide(index) {
    currentSlide = index;
    updateSlide();
}

function openDemo() {
    currentSlide = 0;
    updateSlide();
    document.getElementById('demoOverlay').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDemo() {
    document.getElementById('demoOverlay').classList.remove('active');
    document.body.style.overflow = '';
}

// Keyboard navigation
document.addEventListener('keydown', (e) => {
    const overlay = document.getElementById('demoOverlay');
    if (!overlay.classList.contains('active')) return;

    if (e.key === 'Escape') closeDemo();
    if (e.key === 'ArrowRight') nextSlide();
    if (e.key === 'ArrowLeft') prevSlide();
});

// Touch/swipe support
document.getElementById('demoOverlay').addEventListener('touchstart', (e) => {
    touchStartX = e.changedTouches[0].screenX;
}, { passive: true });

document.getElementById('demoOverlay').addEventListener('touchend', (e) => {
    touchEndX = e.changedTouches[0].screenX;
    handleSwipe();
}, { passive: true });

function handleSwipe() {
    const swipeThreshold = 50;
    const diff = touchStartX - touchEndX;

    if (Math.abs(diff) > swipeThreshold) {
        if (diff > 0) {
            nextSlide(); // Swipe left = next
        } else {
            prevSlide(); // Swipe right = prev
        }
    }
}

// Click outside to close
document.getElementById('demoOverlay').addEventListener('click', (e) => {
    if (e.target.id === 'demoOverlay') {
        closeDemo();
    }
});

// Initialize on load
initDots();
</script>

</body>
</html>

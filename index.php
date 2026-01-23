<?php
/**
 * Sales Landing Page / Root Router
 *
 * - If accessing from /sofie/, the original event pages handle it
 * - Root (/) shows the sales landing page
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Check if this is being accessed from a legacy path
// If so, let the existing system handle it
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
if (strpos($requestUri, '/sofie') === 0) {
    // Legacy path - let the original code in /sofie/ handle it
    // This file should not be reached for /sofie/ paths
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventPlatform - Planlæg dit arrangement nemt og hurtigt</title>
    <meta name="description" content="EventPlatform gør det nemt at planlægge konfirmationer, bryllupper, fødselsdage og andre arrangementer. Gæstehåndtering, ønskeliste, menu og meget mere.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --text: #1f2937;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
        }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            color: var(--text);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
        }

        .header {
            padding: 20px 0;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            z-index: 100;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .header .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            font-size: 24px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 32px;
        }

        .nav-links a {
            color: var(--gray-600);
            text-decoration: none;
            font-weight: 500;
            font-size: 15px;
        }

        .nav-links a:hover { color: var(--primary); }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            font-size: 15px;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .btn-outline {
            background: white;
            color: var(--text);
            border: 2px solid #e5e7eb;
        }

        .hero {
            padding: 160px 0 100px;
            text-align: center;
            background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
        }

        .hero-badge {
            display: inline-block;
            padding: 8px 16px;
            background: rgba(102, 126, 234, 0.1);
            color: var(--primary);
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 24px;
        }

        .hero h1 {
            font-family: 'Playfair Display', serif;
            font-size: 56px;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 24px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .hero p {
            font-size: 20px;
            color: var(--gray-500);
            max-width: 600px;
            margin: 0 auto 40px;
        }

        .hero-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .hero-buttons .btn { padding: 16px 32px; font-size: 16px; }

        .features { padding: 100px 0; }

        .section-header {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-header h2 {
            font-family: 'Playfair Display', serif;
            font-size: 40px;
            font-weight: 700;
            margin-bottom: 16px;
        }

        .section-header p { font-size: 18px; color: var(--gray-500); }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 32px;
        }

        .feature-card {
            padding: 32px;
            background: white;
            border-radius: 20px;
            border: 1px solid #e5e7eb;
            transition: all 0.3s;
        }

        .feature-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.08);
        }

        .feature-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .feature-icon svg { width: 28px; height: 28px; color: white; }
        .feature-card h3 { font-size: 20px; font-weight: 700; margin-bottom: 12px; }
        .feature-card p { color: var(--gray-500); font-size: 15px; }

        .pricing { padding: 100px 0; background: #f8fafc; }

        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
        }

        .pricing-card {
            background: white;
            border-radius: 20px;
            padding: 32px;
            border: 2px solid #e5e7eb;
            position: relative;
        }

        .pricing-card.popular {
            border-color: var(--primary);
            transform: scale(1.05);
        }

        .popular-badge {
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--primary);
            color: white;
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 700;
        }

        .pricing-card h3 { font-size: 22px; font-weight: 700; margin-bottom: 8px; }
        .pricing-price { font-size: 40px; font-weight: 800; margin-bottom: 4px; }
        .pricing-price span { font-size: 16px; font-weight: 500; color: var(--gray-500); }
        .pricing-desc { color: var(--gray-500); font-size: 14px; margin-bottom: 24px; }
        .pricing-features { list-style: none; margin-bottom: 24px; }

        .pricing-features li {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            font-size: 14px;
            color: var(--gray-600);
        }

        .pricing-features li svg { width: 18px; height: 18px; color: #22c55e; }
        .pricing-card .btn { width: 100%; justify-content: center; }

        .cta { padding: 100px 0; text-align: center; }

        .cta-box {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 32px;
            padding: 80px 40px;
            color: white;
        }

        .cta-box h2 {
            font-family: 'Playfair Display', serif;
            font-size: 40px;
            font-weight: 700;
            margin-bottom: 16px;
        }

        .cta-box p { font-size: 18px; opacity: 0.9; margin-bottom: 32px; }
        .cta-box .btn { background: white; color: var(--primary); }

        .footer { padding: 60px 0; background: var(--text); color: white; }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .footer-logo { font-size: 20px; font-weight: 800; }
        .footer-links { display: flex; gap: 32px; }
        .footer-links a { color: rgba(255,255,255,0.7); text-decoration: none; font-size: 14px; }
        .footer-links a:hover { color: white; }

        @media (max-width: 1024px) {
            .features-grid { grid-template-columns: repeat(2, 1fr); }
            .pricing-grid { grid-template-columns: repeat(2, 1fr); }
            .pricing-card.popular { transform: none; }
        }

        @media (max-width: 768px) {
            .nav-links { display: none; }
            .hero h1 { font-size: 36px; }
            .hero p { font-size: 18px; }
            .features-grid { grid-template-columns: 1fr; }
            .pricing-grid { grid-template-columns: 1fr; }
            .footer-content { flex-direction: column; gap: 24px; text-align: center; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <a href="/" class="logo">EventPlatform</a>
            <nav class="nav-links">
                <a href="#features">Funktioner</a>
                <a href="#pricing">Priser</a>
                <a href="/app/auth/login.php">Log ind</a>
                <a href="/app/auth/register.php" class="btn btn-primary">Kom i gang</a>
            </nav>
        </div>
    </header>

    <section class="hero">
        <div class="container">
            <span class="hero-badge">Planlæg dit arrangement nemt</span>
            <h1>Det perfekte værktøj til dit næste arrangement</h1>
            <p>EventPlatform gør det nemt at planlægge konfirmationer, bryllupper, fødselsdage og andre arrangementer. Alt på ét sted.</p>
            <div class="hero-buttons">
                <a href="/app/auth/register.php" class="btn btn-primary">Opret gratis konto</a>
                <a href="#features" class="btn btn-outline">Se funktioner</a>
            </div>
        </div>
    </section>

    <section class="features" id="features">
        <div class="container">
            <div class="section-header">
                <h2>Alt hvad du behøver</h2>
                <p>Kraftfulde værktøjer til at gøre planlægningen nem</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    </div>
                    <h3>Gæstehåndtering</h3>
                    <p>Hold styr på alle dine gæster, send invitationer og følg RSVP-status i realtid.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"></path></svg>
                    </div>
                    <h3>Ønskeliste</h3>
                    <p>Opret en ønskeliste så gæsterne kan reservere gaver og undgå dobbeltgaver.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h3>Program & Menu</h3>
                    <p>Del programmet og menuen med dine gæster så de ved hvad der skal ske.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                    <h3>Fotogalleri</h3>
                    <p>Lad gæsterne uploade billeder fra dagen til et fælles galleri.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                    </div>
                    <h3>Tjekliste</h3>
                    <p>Hold styr på alle opgaver med en smart tjekliste. Glem aldrig noget.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path></svg>
                    </div>
                    <h3>Bordplan</h3>
                    <p>Planlæg bordplanen visuelt og sørg for at alle sidder godt.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="pricing" id="pricing">
        <div class="container">
            <div class="section-header">
                <h2>Enkle priser</h2>
                <p>Vælg den plan der passer til dit arrangement</p>
            </div>
            <div class="pricing-grid">
                <div class="pricing-card">
                    <h3>Gratis</h3>
                    <div class="pricing-price">0 kr <span>/md</span></div>
                    <p class="pricing-desc">For små arrangementer</p>
                    <ul class="pricing-features">
                        <li><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Op til 30 gæster</li>
                        <li><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> 1 arrangement</li>
                        <li><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Gæstehåndtering</li>
                        <li><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Ønskeliste & menu</li>
                    </ul>
                    <a href="/app/auth/register.php" class="btn btn-outline">Kom i gang</a>
                </div>
                <div class="pricing-card">
                    <h3>Basis</h3>
                    <div class="pricing-price">99 kr <span>/md</span></div>
                    <p class="pricing-desc">For de fleste arrangementer</p>
                    <ul class="pricing-features">
                        <li><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Op til 100 gæster</li>
                        <li><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> 3 arrangementer</li>
                        <li><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Bordplan</li>
                        <li><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Tjekliste</li>
                    </ul>
                    <a href="/app/auth/register.php" class="btn btn-outline">Vælg Basis</a>
                </div>
                <div class="pricing-card popular">
                    <span class="popular-badge">Populær</span>
                    <h3>Premium</h3>
                    <div class="pricing-price">199 kr <span>/md</span></div>
                    <p class="pricing-desc">For større arrangementer</p>
                    <ul class="pricing-features">
                        <li><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Op til 300 gæster</li>
                        <li><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> 10 arrangementer</li>
                        <li><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Budget-styring</li>
                        <li><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Toastmaster</li>
                    </ul>
                    <a href="/app/auth/register.php" class="btn btn-primary">Vælg Premium</a>
                </div>
                <div class="pricing-card">
                    <h3>Pro</h3>
                    <div class="pricing-price">499 kr <span>/md</span></div>
                    <p class="pricing-desc">For professionelle</p>
                    <ul class="pricing-features">
                        <li><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Op til 1000 gæster</li>
                        <li><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Ubegrænsede arr.</li>
                        <li><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Eget domæne</li>
                        <li><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Prioriteret support</li>
                    </ul>
                    <a href="/app/auth/register.php" class="btn btn-outline">Vælg Pro</a>
                </div>
            </div>
        </div>
    </section>

    <section class="cta">
        <div class="container">
            <div class="cta-box">
                <h2>Klar til at komme i gang?</h2>
                <p>Opret din gratis konto i dag og begynd at planlægge dit arrangement.</p>
                <a href="/app/auth/register.php" class="btn">Opret gratis konto</a>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">EventPlatform</div>
                <div class="footer-links">
                    <a href="#">Vilkår</a>
                    <a href="#">Privatlivspolitik</a>
                    <a href="#">Kontakt</a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>

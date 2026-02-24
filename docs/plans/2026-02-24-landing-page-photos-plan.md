# Landing Page Photo Integration — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace abstract gradient hero and icon-based event cards with real event photos, add atmosphere section — mobile-first.

**Architecture:** All changes in a single file (`index.php`) which contains inline `<style>` and HTML. Three areas: hero CSS+HTML, event-types CSS+HTML, new atmosphere-break CSS+HTML. Responsive breakpoints at 768px and 1024px.

**Tech Stack:** HTML, CSS (inline in PHP file), no JS changes needed.

**Design doc:** `docs/plans/2026-02-24-landing-page-photos-design.md`

---

### Task 1: Replace Hero CSS (mobile-first)

**Files:**
- Modify: `index.php:169-339` (CSS hero section)

**Step 1: Replace hero CSS block**

Remove all CSS from `/* ===== HERO ===== */` through the `@keyframes float` block (lines 169-339). Replace with:

```css
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
```

**Step 2: Verify** — Open in browser, check mobile (narrow viewport) shows image above text, desktop shows full-bleed image with overlay text.

**Step 3: Commit**

```bash
git add index.php
git commit -m "feat(landing): replace hero CSS with photo-driven mobile-first layout"
```

---

### Task 2: Replace Hero HTML

**Files:**
- Modify: `index.php:851-900` (HTML hero section)

**Step 1: Replace hero HTML**

Remove the hero section HTML (from `<section class="hero">` through its closing `</section>`). Replace with:

```html
<section class="hero">
    <img src="/billeder/hero-konfirmation.png"
         alt="Konfirmationsfest i haven med lyskæder, glade gæster og dansk sommer"
         class="hero-image"
         loading="eager">
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
```

**Step 2: Verify** — Refresh browser. Mobile: image top, text below. Desktop: full-bleed hero with text overlay.

**Step 3: Commit**

```bash
git add index.php
git commit -m "feat(landing): replace hero HTML with photo hero"
```

---

### Task 3: Replace Event Types CSS

**Files:**
- Modify: `index.php:604-667` (CSS event-types section)

**Step 1: Replace event-types CSS block**

Remove CSS from `/* ===== EVENT TYPES ===== */` through `.event-type-card p` (lines 604-667). Replace with:

```css
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
    /* Center the 5th card */
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
```

**Step 2: Verify** — Check all 3 breakpoints render correctly.

**Step 3: Commit**

```bash
git add index.php
git commit -m "feat(landing): event-types CSS for photo cards, mobile-first"
```

---

### Task 4: Replace Event Types HTML

**Files:**
- Modify: `index.php:1037-1075` (HTML event-types section)

**Step 1: Replace event-types HTML**

Remove the event-types section HTML. Replace with:

```html
<section class="event-types" id="events">
    <div class="container">
        <div class="section-header">
            <div class="section-eyebrow">Arrangementer</div>
            <h2>Til alle livets store øjeblikke</h2>
            <p>Uanset hvilken fejring du planlægger, har vi værktøjerne til at gøre det perfekt.</p>
        </div>
        <div class="event-types-grid">
            <div class="event-type-card">
                <img src="/billeder/kort-konfirmation.png" alt="Konfirmationsfest" loading="lazy">
                <div class="event-type-card-body">
                    <h3>Konfirmation</h3>
                    <p>Fejr den store dag med stil</p>
                </div>
            </div>
            <div class="event-type-card">
                <img src="/billeder/kort-bryllup.png" alt="Bryllupsfest" loading="lazy">
                <div class="event-type-card-body">
                    <h3>Bryllup</h3>
                    <p>Planlæg den perfekte dag</p>
                </div>
            </div>
            <div class="event-type-card">
                <img src="/billeder/kort-foedselsdag.png" alt="Fødselsdagsfest" loading="lazy">
                <div class="event-type-card-body">
                    <h3>Fødselsdag</h3>
                    <p>Mærkedage fortjener at fejres</p>
                </div>
            </div>
            <div class="event-type-card">
                <img src="/billeder/kort-jubileum.png" alt="Jubilæumsfest" loading="lazy">
                <div class="event-type-card-body">
                    <h3>Jubilæum</h3>
                    <p>Fejr milepælene sammen</p>
                </div>
            </div>
            <div class="event-type-card">
                <img src="/billeder/kort-temafest.png" alt="Temafest" loading="lazy">
                <div class="event-type-card-body">
                    <h3>Temafest</h3>
                    <p>Giv festen et unikt tema</p>
                </div>
            </div>
        </div>
    </div>
</section>
```

**Step 2: Verify** — 5 photo cards with images visible at all breakpoints.

**Step 3: Commit**

```bash
git add index.php
git commit -m "feat(landing): event-types HTML with photo cards and temafest"
```

---

### Task 5: Add Atmosphere Break Section (CSS + HTML)

**Files:**
- Modify: `index.php` — add CSS before `/* ===== TESTIMONIAL ===== */` (after event-types CSS)
- Modify: `index.php` — add HTML between `</section>` (event-types) and `<section class="testimonial">`

**Step 1: Add atmosphere-break CSS**

Insert before `/* ===== TESTIMONIAL ===== */`:

```css
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
```

**Step 2: Add atmosphere-break HTML**

Insert between event-types `</section>` and `<section class="testimonial">`:

```html
<section class="atmosphere-break">
    <p>Fra intime middage til store fejringer</p>
</section>
```

**Step 3: Verify** — Full-width photo section with dark overlay and centered text. Parallax on desktop.

**Step 4: Commit**

```bash
git add index.php
git commit -m "feat(landing): add atmosphere break section with large event photo"
```

---

### Task 6: Clean Up Responsive Overrides

**Files:**
- Modify: `index.php:816-835` (responsive media queries)

**Step 1: Update responsive breakpoints**

The old responsive rules reference removed classes (`.hero-content`, `.hero-visual`, `.event-types-grid`). These are now handled inline in the component CSS. Update the media queries:

In the `@media (max-width: 1024px)` block, remove:
- `.hero-content { grid-template-columns: 1fr; gap: 60px; }`
- `.hero-visual { display: none; }`
- `.event-types-grid { grid-template-columns: repeat(2, 1fr); }`

In the `@media (max-width: 768px)` block, remove:
- `.hero h1 { font-size: 36px; }` (now in base styles)
- `.event-types-grid { grid-template-columns: 1fr; }` (now in component CSS)

Keep the `.hero { padding: 120px 0 80px; }` rule only if still needed — but with the new layout this can be removed too since hero uses the image for height.

**Step 2: Verify** — No visual regressions at any breakpoint.

**Step 3: Commit**

```bash
git add index.php
git commit -m "refactor(landing): clean up outdated responsive overrides"
```

---

### Task 7: Visual QA and Final Commit

**Step 1: Test all breakpoints**

- 375px (mobile)
- 768px (tablet)
- 1024px (desktop)
- 1440px (wide desktop)

Check:
- Hero image loads and fills correctly
- Hero text is readable on both mobile and desktop
- Event type cards show images, correct grid at each breakpoint
- Atmosphere break photo visible with parallax on desktop
- No horizontal scroll on mobile
- All CTAs still work

**Step 2: Final commit if any tweaks needed**

```bash
git add index.php
git commit -m "fix(landing): visual QA adjustments"
```

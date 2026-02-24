# Landing Page Photo Integration Design

**Date:** 2026-02-24
**Approach:** A — Photo-driven redesign (mobile-first)
**Brand:** Nordisk hygge, kvalitet, varme, minder. Ikke abefester.

## Assets

```
billeder/
├── hero-konfirmation.png           — Hero (bred havefest, konfirmation)
├── kort-bryllup.png                — Event-kort (første dans)
├── kort-foedselsdag.png            — Event-kort (kage, krone, veninder)
├── kort-jubileum.png               — Event-kort (50 år, latter, guldballoner)
├── kort-konfirmation.png           — Event-kort (blomsterkrans, portræt)
├── kort-temafest.png               — Event-kort (Halloween familie)
├── stemning-temafest.png           — Stemningsbillede (Halloween havefest)
└── stemning-stort-arrangement.png  — Stemningsbillede (stor gala, 200+ gæster)
```

## Changes

### 1. Hero Section (replace)

**Current:** Abstract gradient bg + floating card stack (hidden on mobile).
**New:** Full-width background image with gradient overlay.

- Mobile (base): Image fills ~60vh top, text below on light surface
- Desktop (1024px+): Full-height hero, text overlaid bottom-left with dark gradient
- White text on image, badge/h1/description/CTAs preserved
- Image: `hero-konfirmation.png`
- Gradient: linear-gradient(to top, rgba(0,0,0,0.6) 0%, transparent 60%)

### 2. Trust Bar — No changes

### 3. Features — No changes

### 4. Invitation Showcase — No changes

### 5. Event Types (replace)

**Current:** 4 icon-cards (konfirmation, bryllup, fødselsdag, jubilæum).
**New:** 5 photo-cards with real images.

Cards:
- Konfirmation → `kort-konfirmation.png`
- Bryllup → `kort-bryllup.png`
- Fødselsdag → `kort-foedselsdag.png`
- Jubilæum → `kort-jubileum.png`
- Temafest → `kort-temafest.png`

Card anatomy:
- Image top (object-fit: cover, ~250px height)
- Title + description below
- Border-radius: 24px, overflow: hidden

Responsive:
- Mobile: 1 column
- Tablet (768px+): 2 columns + 1 centered
- Desktop (1024px+): 5 columns in one row

### 6. Atmosphere Break (new section)

**New section** between event types and testimonial.

- Full-width `stemning-stort-arrangement.png`
- background-size: cover, background-position: center
- Fixed attachment on desktop (parallax), scroll on mobile
- Height: ~400px mobile, ~500px desktop
- Dark overlay with centered text: "Fra intime middage til store fejringer"
- Serif font, large size

### 7. Testimonial — No changes
### 8. CTA — No changes
### 9. Footer — No changes

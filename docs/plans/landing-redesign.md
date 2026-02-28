# Landingsside Redesign — PartyParart

**Dato:** 2026-02-28
**Status:** Godkendt af bruger, klar til implementering

## Kontekst

Alle features gøres gratis frem til juni 2026 for at:
1. Teste POC og features med rigtige brugere
2. Indsamle ægte testimonials
3. Maksimere signups (primært mål)

## Målgruppe

Alle i Danmark der planlægger en fest — fra intime middage til events med 1.500 gæster. Alle aldre, alle anledninger.

## Beslutningslog

| # | Beslutning | Alternativ | Begrundelse |
|---|---|---|---|
| 1 | Maksimer signups som primært mål | Kvalificerede signups, brand awareness | Gratis adgang = volumen først |
| 2 | Direkte til "Opret arrangement" efter signup | Dashboard, onboarding | Mindst mulig friktion |
| 3 | 3 features: Invitationer, Gæstehåndtering, Toastmaster | Ønskeliste, Budget, Bordplan | Toastmaster er unik differentiator |
| 4 | Gratis-badge i hero + gentaget i CTA | Kun CTA, separat sektion | Maksimal synlighed |
| 5 | Forbedrede simulerede mockups | Rigtige screenshots, ikoner | Hurtigst, kan opgraderes |
| 6 | Parallax billede-hero | Video-hero, split hero | Video eksisterer ikke endnu |
| 7 | Bred målgruppe: alle fester, 5-500 gæster | Kun forældre/konfirmation | Platformen er for alle |
| 8 | 7 event-typer (inkl. studenterfest, halloween) | 5 som nu | Kommunikerer bredde |
| 9 | Marketplace-sektion med leverandør-kort | Udeladt/teaser | Bruger vil vise det nu |
| 10 | Ærlig "early adopter" erstat falsk testimonial | Beholde opdigtet citat | Bygger tillid |

## Sidestruktur (top til bund)

### 1. Header
- Logo + nav som nu
- CTA-knap: "Prøv gratis"
- Nav-links: Funktioner, Arrangementer, Log ind

### 2. Hero (parallax)
- Parallax baggrundsbillede (`hero-konfirmation.png`)
- Gratis-badge: `★ Alle funktioner gratis frem til juni 2026` (guld/amber farve)
- H1: "Planlæg festen — vi holder styr på resten"
- Undertekst: "Fra studentergilde til bryllup, fra 5 til 500 gæster — PartyParart giver dig overblikket, så du kan nyde festen."
- CTA: "Opret dit arrangement gratis" (kun én knap)

### 3. Feature: Invitationer + RSVP
- Eyebrow: "Invitationer"
- H3: "Send smukke invitationer og følg hvert eneste svar"
- Tekst: "Design din invitation, send den direkte til gæsterne, og se i realtid hvem der har åbnet, bekræftet eller afslået. Ingen manuelt regneark — alt opdateres automatisk."
- Mockup: Gæsterækker med mini-invitation preview, farve-dots status, tæller-summary "12 bekræftet · 3 afventer · 1 afbud"

### 4. Feature: Gæstehåndtering
- Eyebrow: "Gæstehåndtering"
- H3: "Ved altid præcis hvem der kommer — og hvem du mangler svar fra"
- Tekst: "Følg alle tilmeldinger ét sted. Se hvem der har bekræftet, hvem der mangler at svare, og hvor mange du skal planlægge efter. Slut med at tælle svar på tværs af sms'er og mails."
- Mockup: Progress-bar "24 bekræftet · 8 afventer · 2 afbud", gæsterækker med status-dots og antal personer, "Send påmindelse" knap

### 5. Feature: Toastmaster
- Eyebrow: "Toastmaster"
- H3: "Giv din toastmaster et værktøj — ikke en hovedpine"
- Tekst: "Del programmet med din toastmaster så de kan koordinere taler, sange og overraskelser. Alle ved hvornår de er på — ingen akavet stilhed, ingen overlap."
- Mockup: Tidslinje med "nu"-markør, type-ikoner (🎤🎵🎁🧩), status-badges (Klar/Bekræftet)

### 6. Event-typer (7 kort)
- H2: "Til alle livets fester — store som små"
- Kort: Konfirmation, Bryllup, Fødselsdag, Jubilæum, Studenterfest, Halloweenfest, Temafest
- Mobil: horisontal scroll, Desktop: grid

### 7. Leverandører (NY sektion)
- Eyebrow: "Markedsplads"
- H2: "Find de rette leverandører til din fest"
- Tekst: "Gennemse lokale caterere, fotografer, DJs, blomsterdekoratører og meget mere — direkte fra din festside. Læs anmeldelser, sammenlign priser, og book med ét klik."
- Mockup: 3-4 leverandør-kort med foto, navn, kategori, stjerner, pris

### 8. Parallax break
- Tekst: "Din fest. Dit overblik. Helt gratis."

### 9. Invitations-showcase (beholdes)
- Mørk sektion med telefon-mockup og auto-slider
- Ingen ændringer

### 10. Early adopter CTA
- H2: "Vær blandt de første der prøver PartyParart"
- Tekst: "Vi er i gang med at bygge Danmarks bedste festplanlægger — og lige nu er alle funktioner gratis. Hjælp os med at gøre platformen endnu bedre."

### 11. CTA-boks
- H2: "Alt er gratis frem til juni — kom i gang på 30 sekunder"
- CTA: "Opret dit arrangement gratis"

### 12. Footer (beholdes, copyright 2026)

## Fjernet

- 5 feature-sektioner: Bordplan, Budget, Ønskeliste, Program, Minder
- Tom trust bar
- Falsk testimonial fra "Maria Jensen"
- Sekundær CTA i hero ("Se hvordan det virker")
- Generisk hero-tekst og badge

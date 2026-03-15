# Invitation Configurator Redesign

**Dato:** 2026-03-15
**Status:** Design godkendt, klar til implementering

## Oversigt

Redesign af invitations-konfiguratoren fra en 6-trins wizard til en **smart wizard med integreret visual editor**. Layout-valg flyttes til første trin med animerede previews som wow-faktor. Preview-panelet fungerer samtidig som editor med inline text editing, drag-drop sektioner og svævende toolbar.

## Målgruppe

Ikke-tech-savvy danskere der planlægger fester (konfirmation, bryllup, fødselsdage etc.). Wizarden skal guide trygt, editoren giver kreativ frihed til dem der vil finpudse.

---

## Skærmlayout

### Trin 1: Layout Showcase (fullscreen)

Overtager hele skærmen (ingen sidebar/preview-split).

**Grid:** 3×2 (desktop), 2×1 scroll (mobil)
**Kort:** ~400×500px med layoutets navn og kort beskrivelse

**Animationer ved hover (ren CSS keyframes, ingen server-kald):**

| Layout | Animation |
|--------|-----------|
| Split | Tekst-kolonnen scroller langsomt ned, hero forbliver fast |
| Centered | Overskrift og tekst fader ind oppefra, sekventielt |
| Fullscreen | Gradient-overlay fader ind over hero, tekst glider op |
| Minimal | Cirkulært billede pulserer subtilt, tekst fader ind |
| Classic | Kort "åbner sig" som et fysisk invitationskort |
| Slideshow | Billeder crossfader langsomt |

**Interaktion:**
- Hover: kort løfter sig (subtle shadow + scale 1.02)
- Klik: accent-border + checkmark, smooth transition til sidebar+preview layout
- Automatisk videre til trin 2

### Trin 2-6: Sidebar + Preview/Editor

**Sidebar (venstre, 360px):**
- Collapsible til icon-bar (48px) for mere preview-plads
- Vertikale tabs/ikoner i toppen for trin-navigation
- Fri navigation mellem trin (ikke tvunget sekventielt)
- Aktivt trin highlightes, færdige trin får checkmark

**Preview/Editor (højre, resten af skærmen):**
- Live preview af invitationen renderet direkte i DOM (ikke iframe)
- Namespace under `.inv-preview` for CSS-isolation
- Fungerer samtidig som editor (se Editor-funktioner nedenfor)

---

## Sidebar-trin

### Trin 2: Billeder
- Hero-billede: stor dropzone (drag-drop eller klik) med thumbnail-preview
- Galleri: grid af 6 pladser, drag-drop for reordering
- Billeder vises i preview med det samme efter upload
- Simpel center-crop indikator

### Trin 3: Tekst
- 4 felter: Hilsen, Overskrift, Besked, Afslutning
- Felterne highlightes i preview når de har fokus i sidebar
- `{guest_name}` placeholder forklaret med tooltip
- Preview viser "Kære Anna & Peter" som eksempel

### Trin 4: Design
- Font-stil: 5 valgmuligheder vist i sit eget typeface
  - Elegant (Cormorant Garamond + DM Sans)
  - Modern (Inter)
  - Playful (Quicksand + Nunito)
  - Traditional (Playfair Display + Lora)
  - Minimal (DM Sans)
- Farver: 5 farvepickers (primary, secondary, accent, text, background)
- Preset-paletter: 4-5 forhåndsdefinerede farvekombinationer som udgangspunkt
- Alle ændringer instant i preview (client-side CSS-variable)

### Trin 5: Sektioner
- Toggle-switches: Nedtælling, Kort, Tidsplan, RSVP
- Drag-handles for reordering (synkroniseret med preview)
- Preview scroller automatisk til sektionen der toggles

### Trin 6: Publicer
- Tjekliste (mangler hero-billede? mangler besked? etc.)
- Publicer-toggle
- Del-link med kopier-knap

---

## Editor-funktioner i preview-panelet

### Inline text editing
- Hoverable tekst-elementer får subtle dashed border ved hover
- Klik → elementet bliver `contenteditable`, cursor placeres
- Tekst synkroniseres til sidebar-felterne og omvendt
- Escape eller klik udenfor → afslut redigering

### Svævende toolbar
- Dukker op over det valgte element
- Tekst-elementer: font-størrelse, fed/kursiv, farve-picker
- Sektioner: flytte op/ned, skjul/vis
- Forsvinder ved klik udenfor

### Drag-drop sektioner
- Drag-handle i venstre side af sektioner ved hover
- Drag op/ned for at reordne
- Synkroniseret med sektionslisten i sidebar trin 5

### To-vejs synkronisering
- Sidebar → preview: instant (client-side for tekst/farver/fonte)
- Preview → sidebar: felter opdateres ved inline redigering
- Layout-skift: server-side rendering (kræver ny HTML-struktur)

### Auto-save
- Debounced auto-save hvert 2 sekunder
- Ingen manuel gem-knap

---

## Teknisk arkitektur

### Rendering-strategi: Hybrid
- **Client-side:** Tekst, farver, fonte (instant via CSS-variable og DOM-manipulation)
- **Server-side:** Layout-skift (henter ny HTML-struktur fra server, da de 6 layouts har fundamentalt forskellig HTML)

### DOM-rendering (ikke iframe)
- Preview renderes direkte i DOM under `.inv-preview` container
- Invitation-CSS namespaced med `.inv-preview` prefix for at undgå konflikter med editor-CSS
- Eksisterende `--inv-*` CSS-variable prefix genbruges

### Editor-lag
- Transparent editor-layer over preview-elementerne
- Event-listeners på klikbare elementer for inline editing og toolbar
- Drag-drop via vanilla JS (HTML5 Drag and Drop API)

### Eksisterende infrastruktur der genbruges
- DB-schema: `invitation_configs`, `invitation_images` (uændret)
- Layout-filer: `/e/layouts/invitation-*.php` (renderes server-side ved layout-skift)
- Delt indhold: `/e/partials/invitation-content.php`
- API: `/api/invitation-preview.php` (bruges til initial load og layout-skift)
- Font-mapping og CSS-variable system

---

## Beslutningslog

| # | Beslutning | Alternativer overvejet | Begrundelse |
|---|-----------|----------------------|-------------|
| 1 | Animerede CSS-previews ved hover på layout-kort | Statiske screenshots, interaktive previews med brugerdata | Wow-faktor uden kompleksitet — ren CSS keyframes |
| 2 | Smart wizard + editor i preview-panelet | Ren editor, ren wizard, wizard→separat editor | Wizard guider trygt, editor giver kreativ frihed, ét view |
| 3 | Editor integreret i preview (ikke separat view) | Separat editor-trin, post-wizard editor, toggle-knap | Naturligt — ingen mode-skift, sidebar og preview er ét workspace |
| 4 | Hybrid rendering (client + server) | Fuld client-side, fuld server-side | Instant feedback på hyppige ændringer, undgår duplikering af layout-logik |
| 5 | Direkte DOM (ikke iframe) | Iframe med overlay og postMessage | Nemmere inline editing, drag-drop, toolbar. CSS-konflikter løses med namespace |
| 6 | Fullscreen layout showcase i trin 1 | Showcase i sidebar, dropdown | 6 store animerede kort kræver plads, preview giver ikke mening uden layout |
| 7 | Fri trin-navigation | Tvunget sekventiel wizard | Brugeren skal kunne hoppe frit — editoren gør det naturligt |
| 8 | Auto-save med 2 sek debounce | Manuel gem-knap | Moderne UX, ingen risiko for tabt arbejde |
| 9 | Preset-farvepaletter + individuelle pickers | Kun pickers, kun presets | Presets for hurtig start, pickers for fuld kontrol |

---

## Antagelser

- Antal layouts forbliver 6 (nye tilføjes af teamet, ikke brugere)
- Ingen samtidig redigering — én bruger per invitation
- Eksisterende DB-schema kan genbruges med evt. små tilføjelser
- Skabelon-systemet (nuværende trin 1) kan integreres som presets i Design-trinnet
- Vedligehold er teamet — koden skal være let at forstå, ikke over-engineered

## Ikke-funktionelle krav

- Preview-opdateringer ved tekst/farve/font-ændringer skal føles øjeblikkelige (<50ms)
- Layout-skift accepterer kort loading (~500ms server round-trip)
- Mobil: sidebar collapser til bund-panel, preview forbliver primær
- Auto-save skal være robust (retry ved netværksfejl, conflict detection)

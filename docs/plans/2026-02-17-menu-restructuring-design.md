# Menu Restructuring Design

**Date:** 2026-02-17
**Status:** Approved

## Problem

The event management interface has 14 tabs on a single horizontal tab bar. This overwhelms customers, especially on mobile (60%+ of traffic). Customers use most features, so hiding them is not viable — the navigation needs better organization.

## Solution

Replace the tab bar with **grouped sidebar navigation** (desktop) and **bottom-bar + slide-up sheet** (mobile).

## Navigation Structure

Five groups containing all 14 features:

### 1. Planlaegning (Planning) — Icon: clipboard
- **Oversigt** (Overview) — event dashboard/start page
- **Tjekliste** (Checklist) — Basic+ tier
- **Program** (Schedule) — includes Menu (food/drink) as integrated section
- **Budget** — Basic+ tier
- **Bordplan** (Seating) — Basic+ tier

### 2. Gaester (Guests) — Icon: people
- **Gaesteliste** (Guest list)
- **Invitation**
- **Send invitation** — promoted from sub-page to own nav item
- **QR-Bordkort** (QR Place Cards)

### 3. Indhold (Content) — Icon: gift
- **Oenskeliste** (Wishlist)
- **Fotos** (Photos)
- **Minder** (Memories)

### 4. Ekstra (Extra) — Icon: microphone
- **Toastmaster** — Premium tier
- **Leverandoerer** (Vendors)

### 5. Indstillinger (Settings) — Icon: gear
- Direct link (no sub-items)

## Desktop Layout (1024px+)

```
+----------------------+---------------------------+
| App Sidebar (280px)  | Event Header              |
| PartyParart logo     | <- Dashboard  "Event Name"|
|                      | [Se gaesteside]           |
| Mine arrangementer   +---------------------------+
|  - Bryllup (active)  |                           |
|  - Foedselsdag       |  Page Content             |
|                      |  (full width)             |
| + Nyt arrangement    |                           |
|                      |                           |
| --- Event Nav ---    |                           |
| Planning          v  |                           |
|   Oversigt           |                           |
|   Tjekliste     PRO  |                           |
|   Program            |                           |
|   Budget        PRO  |                           |
|   Bordplan      PRO  |                           |
|                      |                           |
| Gaester           v  |                           |
|   Gaesteliste        |                           |
|   Invitation         |                           |
|   Send invitation    |                           |
|   QR-Bordkort        |                           |
|                      |                           |
| Indhold           v  |                           |
|   Oenskeliste        |                           |
|   Fotos              |                           |
|   Minder             |                           |
|                      |                           |
| Ekstra            v  |                           |
|   Toastmaster   PRO  |                           |
|   Leverandoerer      |                           |
|                      |                           |
| Indstillinger        |                           |
|                      |                           |
| Plan: Basic          |                           |
| [Opgrader]           |                           |
+----------------------+---------------------------+
```

### Sidebar Behavior
- Groups are **open by default** — all items visible without clicking
- Groups are **collapsible** via click on group header (chevron toggles)
- Active page highlighted with sage-green background + left border accent
- PRO badges (gold pill) shown to right of tier-gated items
- Sidebar scrolls independently from content area

## Mobile Layout (< 1024px)

### Bottom Bar
```
+---------------------------+
| <- Dashboard  Event Name  |  <- Sticky header
| [Se gaesteside]           |
+---------------------------+
|                           |
|  Page Content             |
|  (full width)             |
|                           |
+---------------------------+
| Plan  Gaest Indh  Ekst  Ind |  <- Bottom bar
| (clipboard)(people)(gift)(mic)(gear) |
+---------------------------+
```

### Bottom Sheet (on group tap)
```
+---------------------------+
| <- Dashboard  Event Name  |
+---------------------------+
|  (dimmed page content)    |
+---------------------------+
| +- Planning ------------+ |
| |  --- drag handle ---   | |
| |  Oversigt              | |
| |  Tjekliste        PRO  | |
| |  Program               | |
| |  Budget           PRO  | |
| |  Bordplan         PRO  | |
| +------------------------+ |
+---------------------------+
| Plan  Gaest Indh  Ekst  Ind |
+---------------------------+
```

### Mobile Behavior
- Bottom bar always visible with 5 group icons + labels (10px text)
- Tap group icon -> bottom sheet slides up with sub-items
- Tap sub-item -> navigate to page, sheet closes
- Tap active group again -> sheet closes
- Swipe down on sheet -> sheet closes
- Active group highlighted in sage green
- App-level navigation (events, account) via hamburger icon in header -> slide-in overlay

## Visual Design

### Colors & Typography
- Existing palette: sage green, cream, charcoal, gold accents
- Group headers: DM Sans semi-bold, uppercase, muted color, with icon
- Sub-items: DM Sans regular, indented with padding-left
- Active item: sage-50 background, darker text, left border accent
- Hover state: sage-25 background

### Mobile Specifics
- Minimum tap target: 48px height
- Bottom sheet: 16px top border-radius, drag handle bar
- Semi-transparent backdrop behind sheet
- Animation: 300ms ease-out slide-up

## Technical Approach

- **Standard PHP page-loads** — no SPA or AJAX content loading
- New include file: `includes/event-sidebar.php` (replaces tab nav in manage.php)
- New include file: `includes/event-bottom-bar.php` (mobile bottom nav)
- Bottom sheet: vanilla JS + CSS (no framework)
- Menu feature merged into Program page (no separate menu page needed)
- URL structure unchanged: `/app/events/manage.php?id=X&page=Y`

### Key Changes
- `manage.php`: Remove tab navigation, include new sidebar/bottom-bar
- `includes/app-header.php`: Adjust sidebar to accommodate event-nav zone
- `pages/schedule.php`: Add menu section/tab within the schedule page
- New CSS for sidebar groups, bottom-bar, bottom-sheet
- `pages/menu.php`: Deprecated (functionality moves to schedule.php)

## Migration Notes

- No database changes required
- URL parameters remain the same (backward-compatible)
- `page=menu` should redirect to `page=schedule` with menu section active
- No impact on public event pages (/e/)

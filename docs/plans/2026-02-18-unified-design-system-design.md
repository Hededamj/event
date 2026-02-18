# Unified Design System - Design Document

**Date:** 2026-02-18
**Status:** Approved

## Problem

The three platform areas (Event App, Partner Portal, Admin) use completely different design languages: different color palettes, different fonts (serif vs sans-serif), different sidebar styles (light vs dark), different border-radius values, and different component styling. It looks and feels like three separate products.

## Solution

Create a single shared design system with CSS custom properties. All three areas share the same layout, typography, components, and dark sidebar. Each area gets a unique accent color (green/gold/purple) as a subtle identity marker.

## Design Principles

1. **Nordic warmth** - Cream backgrounds, serif display font, natural tones
2. **High contrast** - Dark sidebar against light content, strong accent colors
3. **Consistent everywhere** - Same components, spacing, typography across all areas
4. **Subtle differentiation** - Accent color identifies which area you are in

---

## Color System

### Shared Base Palette

| Token | Value | Usage |
|-------|-------|-------|
| `--surface` | `#FAF9F7` | Page background (warm off-white) |
| `--surface-card` | `#FFFFFF` | Cards, modals, popovers |
| `--surface-sidebar` | `#1E2422` | Sidebar background (dark forest-green) |
| `--border` | `#E2DED8` | Borders, dividers |
| `--border-light` | `#EDEAE5` | Subtle separators |
| `--text` | `#1A1A1A` | Primary text |
| `--text-secondary` | `#6B6560` | Secondary/muted text |
| `--text-on-dark` | `#FFFFFF` | Text on dark backgrounds |
| `--text-sidebar` | `#A8A49E` | Inactive sidebar text |
| `--text-sidebar-hover` | `#D4D0CB` | Hovered sidebar text |
| `--success` | `#3D8B3D` | Success states |
| `--success-light` | `#E8F5E8` | Success background |
| `--warning` | `#C4922D` | Warning states, PRO badges |
| `--warning-light` | `#FDF6E8` | Warning background |
| `--error` | `#C14B4B` | Error states, danger actions |
| `--error-light` | `#FDE8E8` | Error background |

### Area-Specific Accent Colors

| Area | `--accent` | `--accent-dark` | `--accent-light` | Sidebar stripe |
|------|-----------|-----------------|-------------------|---------------|
| Event App | `#6B8F5E` | `#4D6E42` | `#E8F0E4` | 3px left, green |
| Partner Portal | `#B8923D` | `#96752D` | `#F5EDD8` | 3px left, gold |
| Admin Platform | `#7C6DAF` | `#5E5189` | `#EEEAF5` | 3px left, purple |

### Contrast Improvements vs Current

- Sidebar: white (#FFF) -> dark (#1E2422). Creates strong visual frame.
- Accent: muddy sage (#8FA583) -> forest green (#6B8F5E). More saturated, more pop.
- Background: dark cream (#E8E4DE) -> light off-white (#FAF9F7). More breathing room.
- Admin primary: generic blue (#3b82f6) -> muted purple (#7C6DAF). Feels unified with nordic palette.

---

## Typography

### Font Stack

| Role | Font | Fallback |
|------|------|----------|
| Display (h1, h2) | Cormorant Garamond | Georgia, serif |
| Body (everything else) | DM Sans | -apple-system, sans-serif |

**Inter is removed from Admin.** All three areas use the same two fonts.

### Type Scale

| Element | Font | Size | Weight | Letter-spacing |
|---------|------|------|--------|---------------|
| h1 (page titles) | Cormorant Garamond | 28px | 500 | 0 |
| h2 (sections) | Cormorant Garamond | 22px | 500 | 0 |
| h3 (card titles) | DM Sans | 16px | 600 | 0 |
| h4 (sub-headings) | DM Sans | 14px | 600 | 0 |
| Body | DM Sans | 14px | 400 | 0 |
| Small / captions | DM Sans | 13px | 400 | 0 |
| Sidebar links | DM Sans | 14px | 400 | 0 |
| Sidebar headers | DM Sans | 11px | 600 | 0.05em, uppercase |
| Badges/labels | DM Sans | 12px | 500 | 0 |
| Buttons | DM Sans | 14px | 500 | 0 |
| Table headers | DM Sans | 11px | 600 | 0.05em, uppercase |

---

## Spacing Scale

```css
--space-xs: 4px;
--space-sm: 8px;
--space-md: 16px;
--space-lg: 24px;
--space-xl: 32px;
--space-2xl: 48px;
```

All hardcoded pixel values across the platform should migrate to these tokens.

---

## Border Radius

| Element | Radius |
|---------|--------|
| Cards | 16px (`--radius-lg`) |
| Buttons | 10px (`--radius-md`) |
| Inputs | 10px (`--radius-md`) |
| Sidebar links | 8px (`--radius-sm`) |
| Badges | 6px (`--radius-xs`) |
| Bottom sheet | 20px 20px 0 0 (top only) |

---

## Sidebar Design

### Structure (shared across all areas)

```
Width: 260px
Background: var(--surface-sidebar) = #1E2422
Position: fixed, left, full-height
Z-index: 150
```

### Sidebar Layout

```
+--[3px accent stripe]--+
|                        |
|  Logo (white text)     |  Cormorant Garamond 20px
|                        |
|  --- divider ---       |  rgba(255,255,255,0.08)
|                        |
|  SECTION HEADER        |  11px uppercase, --text-sidebar
|  [icon] Link text      |  14px, --text-sidebar
|  [icon] Active link    |  14px, white, accent bg
|  [icon] Link text      |
|                        |
|  --- divider ---       |
|  ...more sections...   |
|                        |
+------------------------+
|  Plan: Basic           |  rgba(255,255,255,0.06) bg
|  [Upgrade button]      |  accent color bg
+------------------------+
```

### Sidebar States

| State | Background | Text Color |
|-------|-----------|-----------|
| Default | transparent | `--text-sidebar` (#A8A49E) |
| Hover | `rgba(255,255,255,0.06)` | `--text-sidebar-hover` (#D4D0CB) |
| Active | `var(--accent)` | white |
| Premium (locked) | transparent | `rgba(168,164,158,0.5)` + PRO badge |

### Icons

- All SVG, stroke-based, 18x18px in sidebar
- currentColor inheritance from text color
- Admin emoji icons replaced with matching SVGs

### Accent Stripe

- 3px wide, full sidebar height, left edge
- Color matches area accent (green/gold/purple)
- Provides instant visual identity for which area you are in

### Mobile (< 1024px)

Same as current implementation:
- Sidebar hidden, slides in from left with overlay
- Bottom bar with 5 group icons
- Bottom sheet slides up for sub-items
- Sidebar background stays dark

---

## Components

### Buttons

| Type | Background | Text | Border | Hover |
|------|-----------|------|--------|-------|
| Primary | `var(--accent)` | white | none | `var(--accent-dark)` |
| Secondary | white | `var(--text)` | `1.5px solid var(--border)` | `var(--accent-light)` bg |
| Danger | `var(--error)` | white | none | darker error |
| Ghost | transparent | `var(--accent)` | none | `var(--accent-light)` bg |

All buttons: `padding: 10px 20px`, `border-radius: 10px`, `font-size: 14px`, `font-weight: 500`, `transition: all 0.2s ease`.

### Cards

```css
background: var(--surface-card);
border: 1px solid var(--border-light);
border-radius: 16px;
padding: 24px;
box-shadow: 0 1px 3px rgba(0,0,0,0.04);
```

### Form Inputs

```css
border: 1.5px solid var(--border);
border-radius: 10px;
padding: 12px 14px;
font-size: 14px;
font-family: var(--font-body);
transition: border-color 0.2s, box-shadow 0.2s;
```

Focus state:
```css
border-color: var(--accent);
box-shadow: 0 0 0 3px var(--accent-light);
```

### Tables (primarily Admin)

```css
/* Header */
background: var(--surface);
font-size: 11px;
font-weight: 600;
text-transform: uppercase;
letter-spacing: 0.05em;
color: var(--text-secondary);
padding: 12px 16px;

/* Row */
background: var(--surface-card);
border-bottom: 1px solid var(--border-light);
padding: 14px 16px;

/* Row hover */
background: var(--accent-light);
```

### Badges

| Type | Background | Text |
|------|-----------|------|
| PRO | `var(--warning)` | white |
| Success | `var(--success-light)` | `var(--success)` |
| Warning | `var(--warning-light)` | `var(--warning)` |
| Error | `var(--error-light)` | `var(--error)` |
| Neutral | `var(--border-light)` | `var(--text-secondary)` |

All badges: `padding: 4px 10px`, `border-radius: 6px`, `font-size: 12px`, `font-weight: 500`.

### Modals

```css
.modal-overlay: background rgba(0,0,0,0.4), backdrop-filter blur(4px)
.modal: background white, border-radius 20px, max-width 520px, shadow
.modal-header: padding 24px, border-bottom
.modal-body: padding 24px
.modal-footer: padding 16px 24px, surface background
```

---

## Implementation Strategy

### Shared CSS File

Create `assets/css/design-system.css` containing all CSS custom properties, reset, typography, and component classes. All three areas include this single file.

### Area-Specific Variables

Each area sets its accent colors via a small override:

```css
/* Event App */
:root { --accent: #6B8F5E; --accent-dark: #4D6E42; --accent-light: #E8F0E4; }

/* Partner Portal */
:root { --accent: #B8923D; --accent-dark: #96752D; --accent-light: #F5EDD8; }

/* Admin */
:root { --accent: #7C6DAF; --accent-dark: #5E5189; --accent-light: #EEEAF5; }
```

### Files to Modify

| File | Change |
|------|--------|
| `assets/css/design-system.css` | **New** - Shared CSS variables, base styles, components |
| `includes/app-header.php` | Use shared design system, dark sidebar, new variables |
| `includes/admin-platform-header.php` | Replace blue/Inter with shared design system |
| `subcontractor/includes/vendor-header.php` | Align with shared design system |
| `subcontractor/assets/css/subcontractor.css` | Refactor to use shared variables |
| `app/events/manage.php` | Update to shared design system |
| All page-level CSS | Migrate hardcoded values to CSS variables |

### Migration Approach

1. Create the shared CSS file with all tokens
2. Update one area at a time (Event App first, then Partner, then Admin)
3. Each area migration: replace inline CSS variables with shared ones, update sidebar to dark theme
4. Remove area-specific duplicate CSS as shared file takes over

---

## What This Does NOT Change

- No database changes
- No PHP logic changes
- No functionality changes
- No layout restructuring (sidebar position, content flow)
- No JavaScript changes (interaction patterns stay the same)

This is purely a visual unification: colors, fonts, spacing, and component styling.

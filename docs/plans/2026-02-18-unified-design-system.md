# Unified Design System Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Unify the visual design across Event App, Partner Portal, and Admin Platform so all three areas share the same dark sidebar, typography, colors, spacing, and component styling—differentiated only by accent color.

**Architecture:** Create a single shared CSS file (`assets/css/design-system.css`) with all design tokens, reset, typography, layout shell, and component classes. Each area includes this file and sets its accent color via a `data-area` attribute on `<html>`. The three header files are updated to use the shared sidebar structure and dark theme. Page-level inline CSS in each area is then migrated to use the shared tokens.

**Tech Stack:** CSS custom properties, vanilla CSS (no preprocessor), PHP includes, Google Fonts (Cormorant Garamond + DM Sans)

**Reference:** `docs/plans/2026-02-18-unified-design-system-design.md` (approved design)

---

## Task 1: Create shared design-system.css

**Files:**
- Create: `assets/css/design-system.css`

**Step 1: Write the shared CSS file**

Create `assets/css/design-system.css` with the full design system. This file contains:

```css
/**
 * PartyParart Unified Design System
 * Shared across Event App, Partner Portal, Admin Platform.
 *
 * Each area sets its accent via data-area on <html>:
 *   <html data-area="event">   → green accent
 *   <html data-area="partner"> → gold accent
 *   <html data-area="admin">   → purple accent
 */

/* ============================================
   Reset
   ============================================ */
*, *::before, *::after {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

/* ============================================
   Design Tokens
   ============================================ */
:root {
    /* Surfaces */
    --surface: #FAF9F7;
    --surface-card: #FFFFFF;
    --surface-sidebar: #1E2422;

    /* Borders */
    --border: #E2DED8;
    --border-light: #EDEAE5;

    /* Text */
    --text: #1A1A1A;
    --text-secondary: #6B6560;
    --text-on-dark: #FFFFFF;
    --text-sidebar: #A8A49E;
    --text-sidebar-hover: #D4D0CB;

    /* Semantic */
    --success: #3D8B3D;
    --success-light: #E8F5E8;
    --warning: #C4922D;
    --warning-light: #FDF6E8;
    --error: #C14B4B;
    --error-light: #FDE8E8;

    /* Accent — default is event green, overridden by data-area */
    --accent: #6B8F5E;
    --accent-dark: #4D6E42;
    --accent-light: #E8F0E4;

    /* Typography */
    --font-display: 'Cormorant Garamond', Georgia, serif;
    --font-body: 'DM Sans', -apple-system, sans-serif;

    /* Spacing */
    --space-xs: 4px;
    --space-sm: 8px;
    --space-md: 16px;
    --space-lg: 24px;
    --space-xl: 32px;
    --space-2xl: 48px;

    /* Radius */
    --radius-xs: 6px;
    --radius-sm: 8px;
    --radius-md: 10px;
    --radius-lg: 16px;

    /* Layout */
    --sidebar-width: 260px;

    /* Motion */
    --ease-out: cubic-bezier(0.16, 1, 0.3, 1);
}

/* Area-specific accent overrides */
html[data-area="event"] {
    --accent: #6B8F5E;
    --accent-dark: #4D6E42;
    --accent-light: #E8F0E4;
}

html[data-area="partner"] {
    --accent: #B8923D;
    --accent-dark: #96752D;
    --accent-light: #F5EDD8;
}

html[data-area="admin"] {
    --accent: #7C6DAF;
    --accent-dark: #5E5189;
    --accent-light: #EEEAF5;
}

/* ============================================
   Base Typography
   ============================================ */
html {
    -webkit-text-size-adjust: 100%;
}

body {
    font-family: var(--font-body);
    background: var(--surface);
    color: var(--text);
    min-height: 100vh;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    line-height: 1.5;
    font-size: 14px;
}

a {
    color: inherit;
    text-decoration: none;
}

img {
    max-width: 100%;
    height: auto;
    display: block;
}

h1, h2 {
    font-family: var(--font-display);
    font-weight: 500;
}

h1 { font-size: 28px; }
h2 { font-size: 22px; }
h3 { font-size: 16px; font-weight: 600; }
h4 { font-size: 14px; font-weight: 600; }

/* ============================================
   Sidebar Shell (dark theme, shared)
   ============================================ */
.ds-sidebar {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    width: var(--sidebar-width);
    background: var(--surface-sidebar);
    color: var(--text-on-dark);
    display: flex;
    flex-direction: column;
    z-index: 150;
    overflow: hidden;
    border-right: none;
}

/* 3px accent stripe on left edge */
.ds-sidebar::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    bottom: 0;
    width: 3px;
    background: var(--accent);
    z-index: 1;
}

.ds-sidebar-header {
    padding: 24px 20px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.08);
}

.ds-sidebar-logo {
    font-family: var(--font-display);
    font-size: 20px;
    font-weight: 500;
    color: var(--text-on-dark);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 10px;
}

.ds-sidebar-logo svg {
    width: 22px;
    height: 22px;
    color: var(--accent);
}

.ds-sidebar-subtitle {
    font-size: 13px;
    color: var(--text-sidebar);
    margin-top: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.ds-sidebar-nav {
    flex: 1;
    padding: var(--space-md) 12px;
    overflow-y: auto;
}

.ds-sidebar-nav::-webkit-scrollbar { width: 4px; }
.ds-sidebar-nav::-webkit-scrollbar-track { background: transparent; }
.ds-sidebar-nav::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.15);
    border-radius: 2px;
}

/* Sidebar section headers */
.ds-nav-section-title {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-sidebar);
    padding: var(--space-sm) 14px var(--space-xs);
    margin-top: var(--space-sm);
}

.ds-nav-section-title:first-child {
    margin-top: 0;
}

/* Sidebar links */
.ds-nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    color: var(--text-sidebar);
    text-decoration: none;
    font-size: 14px;
    font-weight: 400;
    border-radius: var(--radius-sm);
    transition: all 0.2s ease;
    margin-bottom: 2px;
}

.ds-nav-link svg {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
}

.ds-nav-link:hover {
    background: rgba(255,255,255,0.06);
    color: var(--text-sidebar-hover);
}

.ds-nav-link.active {
    background: var(--accent);
    color: var(--text-on-dark);
    font-weight: 500;
}

.ds-nav-link.active:hover {
    background: var(--accent);
}

/* Nav badge (notification count) */
.ds-nav-badge {
    margin-left: auto;
    background: var(--error);
    color: var(--text-on-dark);
    font-size: 11px;
    font-weight: 600;
    padding: 2px 7px;
    border-radius: 10px;
    min-width: 20px;
    text-align: center;
    line-height: 1.4;
}

/* Sidebar divider */
.ds-sidebar-divider {
    height: 1px;
    background: rgba(255,255,255,0.08);
    margin: var(--space-sm) 0;
}

/* Sidebar footer */
.ds-sidebar-footer {
    padding: var(--space-md);
    border-top: 1px solid rgba(255,255,255,0.08);
}

.ds-sidebar-user {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
}

.ds-sidebar-avatar {
    width: 36px;
    height: 36px;
    background: var(--accent);
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-on-dark);
    font-family: var(--font-display);
    font-size: 15px;
    font-weight: 600;
    flex-shrink: 0;
}

.ds-sidebar-user-name {
    font-size: 14px;
    font-weight: 500;
    color: var(--text-on-dark);
}

.ds-sidebar-user-role {
    font-size: 12px;
    color: var(--text-sidebar);
}

/* Plan badge in footer (Event App) */
.ds-plan-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: var(--space-sm) var(--space-md);
    background: rgba(255,255,255,0.06);
    border-radius: var(--radius-sm);
    font-size: 13px;
    font-weight: 600;
    color: var(--text-sidebar-hover);
    margin-bottom: var(--space-sm);
}

.ds-upgrade-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: var(--space-sm) var(--space-md);
    background: var(--accent);
    color: var(--text-on-dark);
    text-decoration: none;
    border-radius: var(--radius-sm);
    font-size: 13px;
    font-weight: 500;
    transition: background 0.2s;
}

.ds-upgrade-btn:hover { background: var(--accent-dark); }
.ds-upgrade-btn svg { width: 14px; height: 14px; }

/* Logout link styling */
.ds-nav-link-logout {
    color: rgba(168,164,158,0.5);
}

.ds-nav-link-logout:hover {
    color: var(--error);
    background: rgba(193,75,75,0.15);
}

/* Premium/locked link */
.ds-nav-link.premium {
    color: rgba(168,164,158,0.5);
}

.ds-nav-link.premium::after {
    content: 'PRO';
    font-size: 9px;
    font-weight: 700;
    padding: 2px 5px;
    background: var(--warning);
    color: var(--text-on-dark);
    border-radius: 4px;
    margin-left: auto;
}

.ds-nav-link.premium.active {
    color: var(--text-on-dark);
}

.ds-nav-link.premium.active::after {
    background: rgba(255,255,255,0.3);
}

/* ============================================
   Main Content Layout
   ============================================ */
.ds-main {
    margin-left: var(--sidebar-width);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

.ds-content {
    padding: 40px;
    max-width: 1400px;
    width: 100%;
    flex: 1;
}

/* Top header bar (used by Event App) */
.ds-header {
    background: var(--surface-card);
    border-bottom: 1px solid var(--border);
    padding: 0 var(--space-xl);
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 64px;
    position: sticky;
    top: 0;
    z-index: 100;
}

/* Footer */
.ds-footer {
    padding: var(--space-lg) 40px;
    text-align: center;
    font-size: 13px;
    color: var(--text-secondary);
    border-top: 1px solid var(--border-light);
}

/* ============================================
   Components: Buttons
   ============================================ */
.btn {
    display: inline-flex;
    align-items: center;
    gap: var(--space-sm);
    padding: 10px 20px;
    font-family: var(--font-body);
    font-size: 14px;
    font-weight: 500;
    border: none;
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    white-space: nowrap;
    line-height: 1.4;
}

.btn svg {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
}

.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-primary {
    background: var(--accent);
    color: var(--text-on-dark);
}

.btn-primary:hover {
    background: var(--accent-dark);
}

.btn-secondary {
    background: var(--surface-card);
    color: var(--text);
    border: 1.5px solid var(--border);
}

.btn-secondary:hover {
    background: var(--accent-light);
}

.btn-danger {
    background: var(--error);
    color: var(--text-on-dark);
}

.btn-danger:hover {
    background: #A83E3E;
}

.btn-ghost {
    background: transparent;
    color: var(--accent);
    padding: 10px 16px;
}

.btn-ghost:hover {
    background: var(--accent-light);
}

.btn-sm {
    padding: 8px 16px;
    font-size: 13px;
}

.btn-sm svg {
    width: 16px;
    height: 16px;
}

/* ============================================
   Components: Cards
   ============================================ */
.card {
    background: var(--surface-card);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
    padding: var(--space-lg);
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: var(--space-md);
    gap: var(--space-md);
}

.card-title {
    font-family: var(--font-display);
    font-size: 22px;
    font-weight: 500;
    color: var(--text);
}

.card-body {
    padding: var(--space-lg);
}

.card-footer {
    padding: var(--space-md) var(--space-lg);
    border-top: 1px solid var(--border-light);
    background: var(--surface);
}

/* ============================================
   Components: Forms
   ============================================ */
.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: var(--text);
    margin-bottom: var(--space-sm);
}

.form-label .required {
    color: var(--error);
    margin-left: 2px;
}

.form-hint {
    display: block;
    font-size: 13px;
    color: var(--text-secondary);
    margin-top: 6px;
}

.form-input,
.form-select,
.form-textarea {
    width: 100%;
    padding: 12px 14px;
    font-family: var(--font-body);
    font-size: 14px;
    border: 1.5px solid var(--border);
    border-radius: var(--radius-md);
    background: var(--surface-card);
    color: var(--text);
    transition: border-color 0.2s, box-shadow 0.2s;
    -webkit-appearance: none;
    appearance: none;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-light);
}

.form-input::placeholder,
.form-textarea::placeholder {
    color: #B0ACA4;
}

.form-input.is-invalid,
.form-select.is-invalid,
.form-textarea.is-invalid {
    border-color: var(--error);
}

.form-input.is-invalid:focus,
.form-select.is-invalid:focus,
.form-textarea.is-invalid:focus {
    box-shadow: 0 0 0 3px var(--error-light);
}

.form-error {
    display: block;
    font-size: 13px;
    color: var(--error);
    margin-top: 6px;
}

.form-select {
    cursor: pointer;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236B6560'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 18px;
    padding-right: 40px;
}

.form-textarea {
    resize: vertical;
    min-height: 120px;
    line-height: 1.6;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 28px;
}

/* ============================================
   Components: Tables
   ============================================ */
.table-wrapper {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

table {
    width: 100%;
    border-collapse: collapse;
}

table th,
table td {
    padding: 12px var(--space-md);
    text-align: left;
}

table th {
    background: var(--surface);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-secondary);
}

table td {
    font-size: 14px;
    border-bottom: 1px solid var(--border-light);
    color: var(--text);
}

table tbody tr:hover td {
    background: var(--accent-light);
}

/* ============================================
   Components: Badges
   ============================================ */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    font-size: 12px;
    font-weight: 500;
    border-radius: var(--radius-xs);
    white-space: nowrap;
}

.badge-success {
    background: var(--success-light);
    color: var(--success);
}

.badge-warning {
    background: var(--warning-light);
    color: var(--warning);
}

.badge-error {
    background: var(--error-light);
    color: var(--error);
}

.badge-neutral {
    background: var(--border-light);
    color: var(--text-secondary);
}

.badge-pro {
    background: var(--warning);
    color: var(--text-on-dark);
}

/* ============================================
   Components: Modals
   ============================================ */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.4);
    backdrop-filter: blur(4px);
    z-index: 500;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal-content {
    background: var(--surface-card);
    border-radius: 20px;
    width: 100%;
    max-width: 520px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(0,0,0,0.15);
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--space-lg);
    border-bottom: 1px solid var(--border-light);
}

.modal-title {
    font-family: var(--font-display);
    font-size: 22px;
    font-weight: 500;
    color: var(--text);
}

.modal-close {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: none;
    cursor: pointer;
    border-radius: var(--radius-sm);
    color: var(--text-secondary);
    transition: all 0.2s;
}

.modal-close:hover {
    background: var(--surface);
    color: var(--text);
}

.modal-close svg {
    width: 20px;
    height: 20px;
}

.modal-body {
    padding: var(--space-lg);
    overflow-y: auto;
    flex: 1;
}

.modal-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
    padding: var(--space-md) var(--space-lg);
    border-top: 1px solid var(--border-light);
    background: var(--surface);
    border-radius: 0 0 20px 20px;
}

/* ============================================
   Components: Flash/Alert Messages
   ============================================ */
.flash-message {
    padding: var(--space-md) 20px;
    border-radius: var(--radius-md);
    margin-bottom: var(--space-lg);
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 14px;
    font-weight: 500;
}

.flash-message svg {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
}

.flash-message.success,
.flash-success {
    background: var(--success-light);
    border: 1px solid #C8E6C8;
    color: var(--success);
}

.flash-message.error,
.flash-error {
    background: var(--error-light);
    border: 1px solid #F5D5D5;
    color: var(--error);
}

.flash-message.warning,
.flash-warning {
    background: var(--warning-light);
    border: 1px solid #F5E6C8;
    color: var(--warning);
}

/* Alert aliases (used in admin) */
.alert { padding: var(--space-md); border-radius: var(--radius-md); margin-bottom: var(--space-md); }
.alert-success { background: var(--success-light); color: #166534; }
.alert-error { background: var(--error-light); color: #991b1b; }
.alert-warning { background: var(--warning-light); color: #92400e; }

/* ============================================
   Components: Stats
   ============================================ */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--space-md);
    margin-bottom: var(--space-xl);
}

.stat-card {
    background: var(--surface-card);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
    padding: var(--space-lg);
}

.stat-label {
    font-size: 13px;
    color: var(--text-secondary);
    margin-bottom: var(--space-xs);
    font-weight: 500;
}

.stat-value {
    font-family: var(--font-display);
    font-size: 28px;
    font-weight: 500;
    color: var(--text);
    line-height: 1;
}

.stat-change {
    font-size: 12px;
    font-weight: 600;
    margin-top: var(--space-xs);
}

.stat-change.positive { color: var(--success); }
.stat-change.negative { color: var(--error); }

/* ============================================
   Components: Pagination
   ============================================ */
.pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    margin-top: var(--space-lg);
}

.pagination a,
.pagination span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    padding: 0 10px;
    font-size: 14px;
    font-weight: 500;
    border-radius: var(--radius-sm);
    text-decoration: none;
    color: var(--text);
    border: 1px solid var(--border);
}

.pagination a:hover {
    background: var(--accent-light);
}

.pagination .active {
    background: var(--accent);
    color: var(--text-on-dark);
    border-color: var(--accent);
}

.pagination .disabled {
    color: var(--border);
    pointer-events: none;
}

/* ============================================
   Components: Empty State
   ============================================ */
.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state svg,
.empty-state-icon {
    width: 64px;
    height: 64px;
    color: var(--border);
    margin: 0 auto var(--space-md);
}

.empty-state h3 {
    font-family: var(--font-display);
    font-size: 22px;
    font-weight: 500;
    color: var(--text);
    margin-bottom: var(--space-sm);
}

.empty-state p {
    color: var(--text-secondary);
    font-size: 15px;
    margin-bottom: var(--space-lg);
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
}

/* ============================================
   Components: Page Header
   ============================================ */
.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: var(--space-xl);
    gap: var(--space-md);
    flex-wrap: wrap;
}

.page-title {
    font-family: var(--font-display);
    font-size: 28px;
    font-weight: 500;
    color: var(--text);
}

.page-subtitle {
    color: var(--text-secondary);
    margin-top: var(--space-xs);
    font-size: 15px;
}

/* ============================================
   Components: Tabs
   ============================================ */
.tabs {
    display: flex;
    gap: 0;
    border-bottom: 2px solid var(--border);
    margin-bottom: var(--space-lg);
}

.tab {
    padding: 12px 20px;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-secondary);
    border: none;
    background: none;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: all 0.2s ease;
}

.tab:hover { color: var(--text); }

.tab.active {
    color: var(--accent-dark);
    border-bottom-color: var(--accent-dark);
}

/* ============================================
   Mobile: Sidebar collapse
   ============================================ */
.ds-sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 140;
    opacity: 0;
    transition: opacity 0.3s var(--ease-out);
}

.ds-sidebar-overlay.open {
    display: block;
    opacity: 1;
}

.ds-mobile-toggle {
    display: none;
    position: fixed;
    top: var(--space-md);
    left: var(--space-md);
    z-index: 180;
    width: 44px;
    height: 44px;
    background: var(--surface-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.ds-mobile-toggle svg {
    width: 24px;
    height: 24px;
    color: var(--text);
}

@media (max-width: 1024px) {
    :root {
        --sidebar-width: 0px;
    }

    .ds-sidebar {
        width: 260px;
        transform: translateX(-100%);
        transition: transform 0.3s var(--ease-out);
    }

    .ds-sidebar.open {
        transform: translateX(0);
        box-shadow: 4px 0 24px rgba(0,0,0,0.15);
    }

    .ds-mobile-toggle {
        display: flex;
    }

    .ds-main {
        margin-left: 0;
    }

    .ds-content {
        padding-top: 72px;
    }
}

@media (max-width: 768px) {
    .ds-content {
        padding: 72px var(--space-lg) var(--space-lg);
    }

    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .page-title {
        font-size: 24px;
    }

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .form-row {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .ds-content {
        padding: 68px var(--space-md) var(--space-md);
    }

    .page-title {
        font-size: 22px;
    }

    .btn {
        padding: 10px 18px;
        font-size: 13px;
    }
}

/* ============================================
   Utility Classes
   ============================================ */
.text-center { text-align: center; }
.text-right { text-align: right; }
.text-muted { color: var(--text-secondary); }
.text-success { color: var(--success); }
.text-error { color: var(--error); }
.text-warning { color: var(--warning); }
.text-sm { font-size: 13px; }
.font-display { font-family: var(--font-display); }
.font-medium { font-weight: 500; }
.font-bold { font-weight: 700; }

.mt-0 { margin-top: 0; }
.mt-1 { margin-top: var(--space-sm); }
.mt-2 { margin-top: var(--space-md); }
.mt-3 { margin-top: var(--space-lg); }
.mt-4 { margin-top: var(--space-xl); }
.mb-0 { margin-bottom: 0; }
.mb-1 { margin-bottom: var(--space-sm); }
.mb-2 { margin-bottom: var(--space-md); }
.mb-3 { margin-bottom: var(--space-lg); }
.mb-4 { margin-bottom: var(--space-xl); }

.d-flex { display: flex; }
.align-center { align-items: center; }
.justify-between { justify-content: space-between; }
.gap-1 { gap: var(--space-sm); }
.gap-2 { gap: var(--space-md); }
.gap-3 { gap: var(--space-lg); }
.w-full { width: 100%; }

.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0,0,0,0);
    white-space: nowrap;
    border-width: 0;
}

/* ============================================
   Print Styles
   ============================================ */
@media print {
    .ds-sidebar,
    .ds-mobile-toggle,
    .ds-sidebar-overlay,
    .ds-footer {
        display: none !important;
    }

    .ds-main {
        margin-left: 0 !important;
    }

    .ds-content {
        padding: 0 !important;
    }
}
```

**Step 2: Verify the file was created**

Run: `ls -la assets/css/design-system.css`
Expected: File exists, ~600+ lines

**Step 3: Commit**

```bash
git add assets/css/design-system.css
git commit -m "feat: add shared design-system.css with unified tokens and components"
```

---

## Task 2: Migrate Partner Portal (vendor-header.php + subcontractor.css)

The Partner Portal is closest to the target design already (dark sidebar, Cormorant+DM Sans). Migrating it first is the smallest diff and validates the approach.

**Files:**
- Modify: `subcontractor/includes/vendor-header.php` (lines 49-69 HTML, line 57-58 font link, lines 72-147 sidebar HTML)
- Modify: `subcontractor/assets/css/subcontractor.css` (lines 11-28 variables, lines 70-190 sidebar, lines 240-265 main/footer)
- Modify: `subcontractor/includes/vendor-footer.php` (line 4 footer class)

**Step 1: Update vendor-header.php**

In `<head>`, add the design-system.css link before the subcontractor.css link, and add `data-area="partner"` to `<html>`:

- Line 50: change `<html lang="da">` → `<html lang="da" data-area="partner">`
- Line 57: keep existing font link (same fonts)
- After line 57, before line 58, add: `<link rel="stylesheet" href="/assets/css/design-system.css">`
- Line 58 stays: `<link rel="stylesheet" href="/subcontractor/assets/css/subcontractor.css">`

Update sidebar HTML (lines 72-147) to use `ds-` prefixed classes:

```php
<!-- Sidebar -->
<aside class="ds-sidebar" id="vendorSidebar">
    <div class="ds-sidebar-header">
        <a href="/subcontractor/dashboard/" class="ds-sidebar-logo">PartyParart</a>
        <div class="ds-sidebar-subtitle"><?= htmlspecialchars($currentVendor['company_name'] ?? 'Leverandor') ?></div>
    </div>

    <nav class="ds-sidebar-nav">
        <!-- nav links: replace class="nav-link" with class="ds-nav-link" -->
        <!-- keep all existing <a> tags but change classes -->
        <a href="/subcontractor/dashboard/" class="ds-nav-link <?= ... ? 'active' : '' ?>">
            <!-- same SVG icons -->
            Oversigt
        </a>
        <!-- ... all other links same pattern ... -->
        <!-- bookings link with badge: -->
        <a href="..." class="ds-nav-link ...">
            ...
            Bookinger
            <?php if ($unreadMessageCount > 0): ?>
                <span class="ds-nav-badge"><?= $unreadMessageCount > 99 ? '99+' : $unreadMessageCount ?></span>
            <?php endif; ?>
        </a>
        <!-- ... remaining links ... -->
    </nav>

    <div class="ds-sidebar-footer">
        <a href="/subcontractor/dashboard/logout.php" class="ds-nav-link ds-nav-link-logout">
            <!-- logout SVG -->
            Log ud
        </a>
    </div>
</aside>
```

Update main content wrapper:
- Line 61-62: change `sidebar-overlay` → `ds-sidebar-overlay`, keep id `sidebarOverlay`
- Line 65-69: change `mobile-menu-toggle` → `ds-mobile-toggle`, keep id `menuToggle`
- Line 150: change `vendor-main` → `ds-main`
- Line 151: change `vendor-content` → `ds-content`

**Step 2: Slim down subcontractor.css**

Remove from `subcontractor.css` everything that is now provided by `design-system.css`:
- Remove `:root` variables block (lines 11-28) — design system provides all tokens
- Remove reset (lines 33-37) — design system reset
- Remove base body/a/img (lines 42-65) — design system base
- Remove sidebar styles (lines 70-190) — replaced by `ds-sidebar` classes
- Remove `.vendor-main`, `.vendor-content` (lines 240-252) — replaced by `ds-main`, `ds-content`
- Remove `.vendor-footer` (lines 257-264) — replaced by `ds-footer`
- Remove flash messages (lines 269-303) — design system flash
- Remove `.page-header`, `.page-title`, `.page-subtitle` (lines 308-334) — design system provides
- Remove `.card`, `.card-header`, `.card-title`, `.card-body`, `.card-footer` (lines 346-377) — design system provides
- Remove base button `.btn` styles (lines 692-719) — design system provides
- Remove `.form-group`, `.form-label`, `.form-input`, `.form-textarea`, etc. (lines 570-681) — design system provides
- Remove table base styles (lines 482-525) — design system provides
- Remove pagination (lines 1320-1359) — design system provides
- Remove `.empty-state` (lines 1091-1123) — design system provides
- Remove `.badge` base (lines 785-795) — design system provides
- Remove modal base (lines 870-960) — design system provides
- Remove utility classes (lines 1128-1168) — design system provides
- Remove print styles (lines 1643-1662) — design system provides
- Remove mobile `@media (max-width: 1024px)` sidebar/menu rules (lines 1438-1468) — design system handles

**Keep** in `subcontractor.css` (partner-portal-specific components):
- `.stats-grid` / `.stat-card` with hover effect and `.stat-icon` variants (lines 382-477) — but update to use design system tokens
- `.table-cell-primary`, `.table-cell-secondary`, `.table-actions`, `.table-action-btn` (lines 527-565)
- Status badge variants: `.badge-requested`, `.badge-quoted`, `.badge-accepted`, etc. (lines 797-865) — keep but update colors to use `var(--success)`, `var(--error)`, `var(--warning)` tokens
- Message thread / chat bubbles (lines 965-1034) — partner-specific
- Star rating (lines 1039-1086) — partner-specific
- Gallery grid (lines 1188-1255) — partner-specific
- File upload zone (lines 1402-1433) — partner-specific
- Confirmation dialog (lines 1364-1397) — partner-specific
- Spinner/loading (lines 1260-1283) — partner-specific
- Responsive breakpoints for partner-specific components (768px, 640px, 480px)

Update kept styles to use design system tokens:
- Replace `var(--cream)` → `var(--surface)`
- Replace `var(--cream-dark)` → `var(--border)` or `var(--border-light)`
- Replace `var(--cream-light)` → `var(--surface)` (background context)
- Replace `var(--charcoal)` → `var(--text)`
- Replace `var(--charcoal-light)` → `var(--text-secondary)`
- Replace `var(--sage)` → `var(--accent)`
- Replace `var(--sage-dark)` → `var(--accent-dark)`
- Replace `var(--sage-light)` → `var(--accent-light)`
- Replace `var(--white)` → `var(--surface-card)` (card contexts) or `var(--text-on-dark)` (text on dark)

**Step 3: Update vendor-footer.php**

Change `class="vendor-footer"` to `class="ds-footer"`.

**Step 4: Update subcontractor.js**

Check `subcontractor/assets/js/subcontractor.js` for references to old class names (`.vendor-sidebar`, `.sidebar-overlay`, `.mobile-menu-toggle`). Update to use new `ds-` prefixed classes/IDs.

**Step 5: Verify visually by reviewing the HTML output structure**

**Step 6: Commit**

```bash
git add subcontractor/includes/vendor-header.php subcontractor/includes/vendor-footer.php subcontractor/assets/css/subcontractor.css subcontractor/assets/js/subcontractor.js
git commit -m "feat: migrate Partner Portal to unified design system"
```

---

## Task 3: Migrate Admin Platform (admin-platform-header.php)

The Admin Platform needs the biggest change: Inter → DM Sans + Cormorant Garamond, white sidebar → dark sidebar, blue accent → purple accent, emoji icons → SVG icons.

**Files:**
- Modify: `includes/admin-platform-header.php` (entire `<style>` block lines 46-552, sidebar HTML lines 557-655)
- Modify: `includes/admin-platform-footer.php` (closing HTML)
- Check and update all admin-platform pages for page-level CSS: `admin-platform/index.php`, `admin-platform/accounts.php`, `admin-platform/account-detail.php`, `admin-platform/subscriptions.php`, `admin-platform/revenue.php`, `admin-platform/vendors.php`, `admin-platform/vendor-detail.php`, `admin-platform/vendor-payouts.php`, `admin-platform/settings.php`

**Step 1: Rewrite admin-platform-header.php `<head>`**

Replace the Inter font link (line 44) with Cormorant Garamond + DM Sans:
```html
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
```

Add `data-area="admin"` to `<html>` tag.

Add design-system.css link:
```html
<link rel="stylesheet" href="/assets/css/design-system.css">
```

**Step 2: Replace the entire `<style>` block**

Remove all 500+ lines of inline CSS (`:root` through utility classes). Replace with a small admin-specific override block for any admin-only components not covered by the design system. The design system handles:
- All `:root` variables
- Reset, body, typography
- Sidebar, nav links
- Buttons, cards, tables, badges, forms, alerts, pagination, empty state
- Utility classes

The only admin-specific CSS to keep (rewritten with design system tokens):
```css
<style>
    /* Admin-specific: search box */
    .search-box { position: relative; }
    .search-box input { padding-left: 40px; }
    .search-box-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-secondary);
    }
</style>
```

**Step 3: Rewrite sidebar HTML**

Replace emoji icons with SVG icons. Replace class names:
- `platform-layout` wrapper → keep or use `ds-main` directly
- `platform-sidebar` → `ds-sidebar`
- `sidebar-brand` → `ds-sidebar-header`
- `sidebar-brand-name` → `ds-sidebar-logo`
- `sidebar-brand-label` → `ds-sidebar-subtitle`
- `sidebar-nav` → `ds-sidebar-nav`
- `nav-section-title` → `ds-nav-section-title`
- `nav-link` → `ds-nav-link`
- `nav-link-icon` → remove wrapper, use inline SVG
- `nav-badge` → `ds-nav-badge`
- `sidebar-footer` → `ds-sidebar-footer`
- `user-info` → `ds-sidebar-user`
- `user-avatar` → `ds-sidebar-avatar`
- `platform-main` → `ds-main`
- `platform-header` → `ds-header`
- `platform-content` → `ds-content`

Replace each emoji icon with an SVG:
- `&#128200;` (Dashboard) → bar-chart SVG
- `&#128100;` (Konti) → user SVG
- `&#128179;` (Abonnementer) → credit-card SVG
- `&#128176;` (Omsætning, Udbetalinger) → dollar-sign SVG
- `&#127970;` (Leverandører) → building SVG
- `&#9881;` (Indstillinger) → settings gear SVG
- `&#128682;` (Log ud) → log-out SVG

Add mobile toggle and overlay elements (not present in current admin header):
```php
<div class="ds-sidebar-overlay" id="sidebarOverlay"></div>
<button class="ds-mobile-toggle" id="menuToggle" aria-label="Toggle navigation">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
    </svg>
</button>
```

**Step 4: Update admin-platform-footer.php**

Add sidebar toggle JavaScript:
```html
<script>
    // Flash message auto-hide
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.alert').forEach(function(alert) {
            setTimeout(function() {
                alert.style.transition = 'opacity 0.3s ease';
                alert.style.opacity = '0';
                setTimeout(function() { alert.remove(); }, 300);
            }, 5000);
        });
    });

    // Mobile sidebar toggle
    document.getElementById('menuToggle')?.addEventListener('click', function() {
        document.getElementById('adminSidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('open');
    });

    document.getElementById('sidebarOverlay')?.addEventListener('click', function() {
        document.getElementById('adminSidebar').classList.remove('open');
        this.classList.remove('open');
    });
</script>
```

**Step 5: Check all admin pages for page-level CSS**

Scan each admin-platform page for `<style>` blocks or inline styles that reference old variables (`--color-*` prefix). Update any found to use design system tokens:
- `--color-bg` → `var(--surface)`
- `--color-surface` → `var(--surface-card)`
- `--color-primary` → `var(--accent)`
- `--color-primary-deep` → `var(--accent-dark)`
- `--color-primary-soft` → `var(--accent-light)`
- `--color-text` → `var(--text)`
- `--color-text-soft` → `var(--text-secondary)`
- `--color-text-muted` → `var(--text-secondary)`
- `--color-border` → `var(--border)`
- `--color-border-soft` → `var(--border-light)`
- `--color-success` → `var(--success)`
- `--color-warning` → `var(--warning)`
- `--color-error` → `var(--error)`
- `--color-success-soft` → `var(--success-light)`
- `--color-warning-soft` → `var(--warning-light)`
- `--color-error-soft` → `var(--error-light)`
- `--radius-*`, `--space-*`, `--text-*`, `--shadow-*` → corresponding design system tokens

**Step 6: Commit**

```bash
git add includes/admin-platform-header.php includes/admin-platform-footer.php admin-platform/
git commit -m "feat: migrate Admin Platform to unified design system (dark sidebar, purple accent, SVG icons)"
```

---

## Task 4: Migrate Event App - App-level pages (app-header.php)

The Event App has two layout shells: `app-header.php` (Dashboard, account pages) and `manage.php` (event management with grouped sidebar). This task handles the app-level shell.

**Files:**
- Modify: `includes/app-header.php` (lines 39-613 head/style, lines 617-669 sidebar HTML)
- Modify: `includes/app-footer.php` (closing JS)

**Step 1: Update app-header.php `<head>`**

- Add `data-area="event"` to `<html>` on line 40
- Add `<link rel="stylesheet" href="/assets/css/design-system.css">` after font links
- Remove the entire `<style>` block (lines 48-613) — **all** of these styles are now provided by design-system.css

Keep a minimal `<style>` block only for app-header-specific things not in the design system:
```css
<style>
    /* User dropdown menu - app-header specific */
    .user-menu { position: relative; }
    .user-menu-btn {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 8px 14px;
        background: none;
        border: none;
        cursor: pointer;
        border-radius: var(--radius-md);
        transition: all 0.2s;
    }
    .user-menu-btn:hover { background: var(--surface); }
    .user-avatar {
        width: 38px;
        height: 38px;
        background: var(--accent);
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-on-dark);
        font-family: var(--font-display);
        font-size: 16px;
        font-weight: 600;
    }
    .user-name {
        font-size: 14px;
        font-weight: 600;
        color: var(--text);
    }
    .user-dropdown {
        position: absolute;
        top: 100%;
        right: 0;
        margin-top: 8px;
        background: var(--surface-card);
        border-radius: var(--radius-lg);
        box-shadow: 0 10px 40px rgba(0,0,0,0.12);
        min-width: 220px;
        padding: 8px;
        display: none;
        z-index: 200;
        border: 1px solid var(--border);
    }
    .user-dropdown.show { display: block; }
    .dropdown-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 14px;
        color: var(--text);
        text-decoration: none;
        border-radius: var(--radius-md);
        font-size: 14px;
        font-weight: 500;
        transition: all 0.2s;
    }
    .dropdown-item:hover { background: var(--surface); }
    .dropdown-item svg { width: 18px; height: 18px; color: var(--text-secondary); }
    .dropdown-divider { height: 1px; background: var(--border); margin: 8px 0; }
    .dropdown-item.danger { color: var(--error); }
    .dropdown-item.danger:hover { background: var(--error-light); }
    .dropdown-item.danger svg { color: var(--error); }
</style>
```

**Step 2: Rewrite sidebar HTML to use dark theme**

Replace the existing sidebar (lines 617-669) with the design system sidebar:

```php
<aside class="ds-sidebar" id="sidebar">
    <div class="ds-sidebar-header">
        <a href="/app/dashboard.php" class="ds-sidebar-logo">PartyParart</a>
    </div>

    <nav class="ds-sidebar-nav">
        <div class="ds-nav-section-title">Oversigt</div>
        <a href="/app/dashboard.php" class="ds-nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
            <!-- house SVG icon -->
            Dashboard
        </a>

        <?php if (!empty($userEvents)): ?>
        <div class="ds-nav-section-title">Mine arrangementer</div>
        <?php foreach (array_slice($userEvents, 0, 5) as $event): ?>
        <a href="/app/events/manage.php?id=<?= $event['id'] ?>" class="ds-nav-link">
            <!-- calendar SVG icon -->
            <?= htmlspecialchars($event['name'] ?? $event['main_person_name'] ?? 'Arrangement') ?>
        </a>
        <?php endforeach; ?>
        <?php endif; ?>

        <div class="ds-nav-section-title">Handlinger</div>
        <a href="/app/events/create.php" class="ds-nav-link">
            <!-- plus SVG icon -->
            Nyt arrangement
        </a>
    </nav>

    <div class="ds-sidebar-footer">
        <div class="ds-plan-badge">
            <?= htmlspecialchars($subscription['plan_name'] ?? 'Gratis') ?>
        </div>
        <?php if (($subscription['plan_slug'] ?? 'free') === 'free'): ?>
        <a href="/app/account/subscription.php" class="ds-upgrade-btn">Opgrader nu</a>
        <?php endif; ?>
    </div>
</aside>
```

Update mobile toggle and overlay:
```php
<div class="ds-sidebar-overlay" id="sidebarOverlay"></div>
```

Update top header to use `ds-header` class and `ds-main`/`ds-content` for main area.

**Step 3: Update app-footer.php**

Update sidebar toggle JS to use new class names/IDs.

**Step 4: Check app-level pages for hardcoded colors**

Scan `app/dashboard.php`, `app/account/settings.php`, `app/account/subscription.php`, `app/account/billing.php`, `app/events/create.php` for inline `<style>` blocks with old variables (`--cream`, `--sage`, `--charcoal`). Update to design system tokens.

**Step 5: Commit**

```bash
git add includes/app-header.php includes/app-footer.php app/dashboard.php app/account/ app/events/create.php
git commit -m "feat: migrate Event App (app-level) to unified design system"
```

---

## Task 5: Migrate Event App - manage.php (event management)

The event management page has its own layout with the grouped sidebar. Update it to use the dark theme and design system tokens.

**Files:**
- Modify: `app/events/manage.php` (lines 92-1034 `<style>` block, line 1037 sidebar include, lines 1036-1038 overlay)
- Modify: `includes/event-sidebar.php` (lines 94-214 HTML classes)

**Step 1: Update manage.php `<head>`**

- Add `data-area="event"` to `<html>` on line 84
- Add `<link rel="stylesheet" href="/assets/css/design-system.css">` after font links (line 91)
- Drastically reduce the inline `<style>` block:

Remove from manage.php's `<style>`:
- `:root` variables (lines 95-113) — design system provides
- Reset (line 93) — design system provides
- Body styles (lines 115-121, 123-132 grain) — design system provides, keep grain texture as override
- `.btn` base, `.btn-primary`, `.btn-secondary` (lines 203-248) — design system provides
- `.card`, `.card-header`, `.card-title` (lines 586-607) — design system provides
- `.stats-grid`, `.stat-card`, `.stat-label`, `.stat-value` (lines 610-646) — design system provides
- `.empty-state` (lines 686-709) — design system provides
- `.flash-message` (lines 711-738) — design system provides
- `.form-group`, `.form-label`, `.form-input` (lines 741-811) — design system provides
- `.modal-overlay`, `.modal`, `.modal-header`, `.modal-body`, `.modal-footer` (lines 869-942) — design system provides

Keep (manage.php-specific):
- `.top-nav`, `.top-nav-inner`, `.nav-left`, `.nav-right`, `.back-link`, `.event-title`, `.event-badge` — top bar specific to event management
- `.event-sidebar` and all sidebar-group styles — BUT rewrite to dark theme
- `.mobile-bottom-bar`, `.bottom-bar-item`, `.bottom-sheet-*` — mobile nav specific to event management
- `.quick-actions`, `.quick-action` — dashboard specific
- `.filters-bar`, `.filter-tabs`, `.filter-tab` — event-specific
- `.section-title`, `.section-subtitle`, `.page-header-actions` — event-specific
- `.upgrade-notice` — event-specific
- `.btn-sage` — event-specific shorthand for accent
- Grain texture override

**Step 2: Rewrite event sidebar to dark theme**

Update the sidebar CSS in manage.php:
- `.event-sidebar` background: `var(--white)` → `var(--surface-sidebar)`
- `.event-sidebar` border-right: remove (dark sidebar has no border)
- Add `::before` accent stripe (3px green)
- `.sidebar-header` border-bottom: `1px solid var(--cream-dark)` → `1px solid rgba(255,255,255,0.08)`
- `.sidebar-logo` color: `var(--charcoal)` → `var(--text-on-dark)`
- `.sidebar-logo svg` color: `var(--sage)` → `var(--accent)`
- `.sidebar-divider` background: `var(--cream-dark)` → `rgba(255,255,255,0.08)`
- `.sidebar-link` color: `var(--charcoal-light)` → `var(--text-sidebar)`
- `.sidebar-link:hover` background: `var(--cream-light)` → `rgba(255,255,255,0.06)`, color → `var(--text-sidebar-hover)`
- `.sidebar-link.active` background: `var(--sage)` → `var(--accent)`, color stays white
- `.sidebar-link.premium` color: muted → `rgba(168,164,158,0.5)`, PRO badge stays gold
- `.sidebar-group-header` color: `var(--charcoal-light)` → `var(--text-sidebar)`
- `.sidebar-group-header:hover` color → `var(--text-sidebar-hover)`
- `.sidebar-footer` border-top: → `rgba(255,255,255,0.08)`
- `.sidebar-plan-badge` background: → `rgba(255,255,255,0.06)`, color: → `var(--text-sidebar-hover)`
- `.sidebar-upgrade-btn` background: `var(--sage)` → `var(--accent)`, hover → `var(--accent-dark)`

Update `.main-content` margin-left to use `var(--sidebar-width)` from design system (260px instead of 280px).

Update `.top-nav` margin-left similarly.

Update mobile bottom bar and bottom sheet to use design system tokens:
- Backgrounds: `var(--white)` → `var(--surface-card)`
- Borders: `var(--cream-dark)` → `var(--border)`
- Active colors: `var(--sage-dark)` → `var(--accent-dark)`
- `.bottom-sheet-link.active` background: `var(--sage)` → `var(--accent)`

**Step 3: Update event-sidebar.php HTML**

No class name changes needed (these are event-management-specific classes, not shared), but keep existing structure.

**Step 4: Replace old variable references in kept CSS**

For all CSS remaining in manage.php, replace:
- `var(--cream)` → `var(--surface)`
- `var(--cream-light)` → use contextually: `var(--surface)` or `var(--accent-light)`
- `var(--cream-dark)` → `var(--border)`
- `var(--sage)` → `var(--accent)`
- `var(--sage-light)` → `var(--accent-light)`
- `var(--sage-dark)` → `var(--accent-dark)`
- `var(--charcoal)` → `var(--text)`
- `var(--charcoal-light)` → `var(--text-secondary)`
- `var(--white)` → `var(--surface-card)` (for backgrounds)
- `var(--gold)` → `var(--warning)`

Remove `:root` variables block entirely (design system provides all).

**Step 5: Update event page-level CSS**

Check all files in `app/events/pages/` for `<style>` blocks using old variables. Key files:
- `pages/dashboard.php` — uses `var(--gray-500)`, `var(--gray-900)`, `var(--gray-100)`. Replace with `var(--text-secondary)`, `var(--text)`, `var(--border-light)`.
- `pages/schedule.php` — already updated in previous work but may still use `--cream-dark`, `--sage`, etc. Update to design system tokens.
- `pages/guests.php`, `pages/wishlist.php`, `pages/invitation.php`, etc. — scan and update.

**Step 6: Commit**

```bash
git add app/events/manage.php includes/event-sidebar.php app/events/pages/
git commit -m "feat: migrate Event Management to unified design system (dark sidebar, accent tokens)"
```

---

## Task 6: Final cleanup and consistency pass

**Files:**
- All modified files from Tasks 1-5
- Any remaining pages with hardcoded colors

**Step 1: Search for remaining old color variables**

Search the entire codebase for any remaining references to old variables:

```bash
grep -r "var(--cream" app/ includes/ subcontractor/ admin-platform/ --include="*.php" --include="*.css" -l
grep -r "var(--sage" app/ includes/ subcontractor/ admin-platform/ --include="*.php" --include="*.css" -l
grep -r "var(--charcoal" app/ includes/ subcontractor/ admin-platform/ --include="*.php" --include="*.css" -l
grep -r "var(--color-" admin-platform/ includes/ --include="*.php" --include="*.css" -l
grep -r "#8FA583\|#E8E4DE\|#3b82f6\|#D9D4CC" app/ includes/ subcontractor/ admin-platform/ --include="*.php" --include="*.css" -l
```

Fix any remaining references.

**Step 2: Verify sidebar width consistency**

All three areas should now use `--sidebar-width: 260px` from the design system. Verify no hardcoded `280px` or `250px` values remain in layout offsets.

**Step 3: Verify Google Fonts link is consistent**

All three areas should load Cormorant Garamond + DM Sans. Verify Inter is no longer loaded anywhere.

**Step 4: Commit final cleanup**

```bash
git add -A
git commit -m "fix: clean up remaining old color variables and ensure design system consistency"
```

---

## Task 7: Deploy to live

**Files:** All modified files from Tasks 1-6

**Step 1: List all modified files**

```bash
git diff main --name-only
```

**Step 2: Upload via FTP**

Upload all changed files to `partyparart.dk/` subdomain via FTP.

**CRITICAL:** Stay completely away from `/sofie/` area on the server.

Upload order:
1. `assets/css/design-system.css` (new file, safe)
2. `subcontractor/assets/css/subcontractor.css` (updated)
3. `subcontractor/includes/vendor-header.php` (updated)
4. `subcontractor/includes/vendor-footer.php` (updated)
5. `includes/admin-platform-header.php` (updated)
6. `includes/admin-platform-footer.php` (updated)
7. `includes/app-header.php` (updated)
8. `includes/app-footer.php` (updated)
9. `app/events/manage.php` (updated)
10. `includes/event-sidebar.php` (if changed)
11. All admin-platform pages
12. All event pages

**Step 3: Verify each area on live**

- Check `partyparart.dk/app/dashboard.php` — Event App
- Check `partyparart.dk/app/events/manage.php?id=X` — Event Management
- Check `partyparart.dk/subcontractor/dashboard/` — Partner Portal
- Check `partyparart.dk/admin-platform/` — Admin Platform

**Step 4: Commit deployment marker**

```bash
git commit --allow-empty -m "deploy: unified design system live on partyparart.dk"
```

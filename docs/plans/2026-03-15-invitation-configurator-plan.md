# Invitation Configurator Redesign - Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace the 6-step wizard with a layout showcase + sidebar/editor workspace with inline editing, drag-drop sections, and floating toolbar.

**Architecture:** The page has two modes: (1) Fullscreen layout showcase (step 1), (2) Sidebar+preview workspace (steps 2-6). Preview renders in DOM (not iframe) under `.inv-preview` namespace. Client-side updates for text/colors/fonts, server-side fetch for layout changes. Auto-save via debounced API calls.

**Tech Stack:** PHP 7.4 (procedural), vanilla JS, MySQL/PDO, CSS custom properties, Google Fonts

---

## File Overview

| File | Action | Purpose |
|------|--------|---------|
| `app/events/pages/invitation.php` | **Rewrite** | Main configurator page (1,392→~600 lines PHP + external JS/CSS) |
| `assets/css/invitation-editor.css` | **Create** | All editor styles (showcase, sidebar, preview, toolbar) |
| `assets/js/invitation-editor.js` | **Create** | Editor JS (showcase animations, inline editing, toolbar, drag-drop, sync, auto-save) |
| `api/invitation-autosave.php` | **Create** | Auto-save API endpoint (POST JSON, returns success) |
| `api/invitation-preview.php` | **Modify** | Add `?format=partial` to return layout HTML fragment (no `<html>` wrapper) for DOM injection |
| `includes/invitation-functions.php` | **Keep** | No changes needed — existing functions cover all DB operations |
| `e/layouts/invitation-*.php` | **Keep** | No changes — used by public pages, preview API generates from these |
| `e/partials/invitation-content.php` | **Keep** | No changes |

---

## Task 1: Create the CSS file for the editor

**Files:**
- Create: `assets/css/invitation-editor.css`

**Step 1: Create the layout showcase styles**

```css
/* === LAYOUT SHOWCASE (Step 1 - Fullscreen) === */

.showcase-fullscreen {
    position: fixed;
    inset: 0;
    z-index: 100;
    background: var(--bg-primary, #FAF9F7);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px;
    overflow-y: auto;
}

.showcase-header {
    text-align: center;
    margin-bottom: 40px;
}

.showcase-header h2 {
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: 32px;
    font-weight: 500;
    color: var(--text-primary, #1A1A1A);
    margin-bottom: 8px;
}

.showcase-header p {
    color: var(--text-secondary, #6B7280);
    font-size: 15px;
}

.showcase-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
    max-width: 1200px;
    width: 100%;
}

.showcase-card {
    position: relative;
    border-radius: 16px;
    overflow: hidden;
    cursor: pointer;
    background: #fff;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    aspect-ratio: 3/4;
}

.showcase-card:hover {
    transform: scale(1.02) translateY(-4px);
    box-shadow: 0 12px 40px rgba(0,0,0,0.12);
}

.showcase-card.selected {
    outline: 3px solid var(--sage, #8FA583);
    outline-offset: 2px;
}

.showcase-card.selected::after {
    content: '✓';
    position: absolute;
    top: 12px;
    right: 12px;
    width: 28px;
    height: 28px;
    background: var(--sage, #8FA583);
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: bold;
    z-index: 5;
}

.showcase-preview {
    position: relative;
    width: 100%;
    height: 100%;
    overflow: hidden;
}

.showcase-info {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 16px 20px;
    background: linear-gradient(to top, rgba(0,0,0,0.7), transparent);
    color: #fff;
    z-index: 3;
}

.showcase-info h3 {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 2px;
}

.showcase-info p {
    font-size: 13px;
    opacity: 0.85;
}

/* === SHOWCASE ANIMATIONS (per layout, play on hover) === */

/* Split: text column scrolls */
.showcase-card[data-layout="split"] .mock-content {
    transition: transform 2.5s ease-in-out;
}
.showcase-card[data-layout="split"]:hover .mock-content {
    transform: translateY(-30px);
}

/* Centered: text fades in from top */
.showcase-card[data-layout="centered"] .mock-text-line {
    opacity: 0;
    transform: translateY(-10px);
    transition: opacity 0.6s ease, transform 0.6s ease;
}
.showcase-card[data-layout="centered"]:hover .mock-text-line:nth-child(1) { opacity: 1; transform: translateY(0); transition-delay: 0.1s; }
.showcase-card[data-layout="centered"]:hover .mock-text-line:nth-child(2) { opacity: 1; transform: translateY(0); transition-delay: 0.3s; }
.showcase-card[data-layout="centered"]:hover .mock-text-line:nth-child(3) { opacity: 1; transform: translateY(0); transition-delay: 0.5s; }

/* Fullscreen: gradient overlay fades in, text slides up */
.showcase-card[data-layout="fullscreen"] .mock-overlay {
    opacity: 0;
    transition: opacity 0.8s ease;
}
.showcase-card[data-layout="fullscreen"]:hover .mock-overlay {
    opacity: 1;
}
.showcase-card[data-layout="fullscreen"] .mock-hero-text {
    opacity: 0;
    transform: translateY(20px);
    transition: opacity 0.6s ease 0.3s, transform 0.6s ease 0.3s;
}
.showcase-card[data-layout="fullscreen"]:hover .mock-hero-text {
    opacity: 1;
    transform: translateY(0);
}

/* Minimal: circle pulses, text fades in */
.showcase-card[data-layout="minimal"] .mock-circle {
    transition: transform 0.8s ease;
}
.showcase-card[data-layout="minimal"]:hover .mock-circle {
    transform: scale(1.05);
}
.showcase-card[data-layout="minimal"] .mock-text-line {
    opacity: 0;
    transition: opacity 0.5s ease;
}
.showcase-card[data-layout="minimal"]:hover .mock-text-line {
    opacity: 1;
    transition-delay: 0.4s;
}

/* Classic: card "opens" */
.showcase-card[data-layout="classic"] .mock-card-inner {
    transform: perspective(800px) rotateY(0deg);
    transition: transform 0.8s ease;
    transform-origin: left center;
}
.showcase-card[data-layout="classic"]:hover .mock-card-inner {
    transform: perspective(800px) rotateY(-5deg);
}

/* Slideshow: images crossfade */
.showcase-card[data-layout="slideshow"] .mock-slide {
    position: absolute;
    inset: 0;
    opacity: 0;
    transition: opacity 1s ease;
}
.showcase-card[data-layout="slideshow"] .mock-slide:first-child {
    opacity: 1;
}
.showcase-card[data-layout="slideshow"]:hover .mock-slide:first-child {
    opacity: 0;
}
.showcase-card[data-layout="slideshow"]:hover .mock-slide:nth-child(2) {
    opacity: 1;
}

/* Continue button */
.showcase-continue {
    margin-top: 32px;
    text-align: center;
}

.showcase-continue .btn {
    opacity: 0;
    transform: translateY(10px);
    transition: opacity 0.3s ease, transform 0.3s ease;
    pointer-events: none;
}

.showcase-continue.visible .btn {
    opacity: 1;
    transform: translateY(0);
    pointer-events: auto;
}

@media (max-width: 900px) {
    .showcase-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }
    .showcase-fullscreen {
        padding: 24px 16px;
    }
}

@media (max-width: 500px) {
    .showcase-grid {
        grid-template-columns: 1fr;
        max-width: 320px;
    }
}
```

**Step 2: Add the sidebar + preview workspace styles**

Append to the same file:

```css
/* === WORKSPACE (Sidebar + Preview) === */

.inv-workspace {
    display: grid;
    grid-template-columns: 360px 1fr;
    min-height: calc(100vh - 60px);
    position: relative;
}

.inv-workspace.sidebar-collapsed {
    grid-template-columns: 48px 1fr;
}

/* --- Sidebar --- */

.inv-sidebar {
    background: #fff;
    border-right: 1px solid var(--border, #E5E7EB);
    display: flex;
    flex-direction: column;
    height: calc(100vh - 60px);
    position: sticky;
    top: 60px;
    overflow: hidden;
}

.sidebar-tabs {
    display: flex;
    border-bottom: 1px solid var(--border, #E5E7EB);
    padding: 0;
    flex-shrink: 0;
}

.sidebar-tab {
    flex: 1;
    padding: 12px 8px;
    text-align: center;
    font-size: 12px;
    font-weight: 500;
    color: var(--text-secondary, #6B7280);
    cursor: pointer;
    border: none;
    background: none;
    border-bottom: 2px solid transparent;
    transition: color 0.2s, border-color 0.2s;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
}

.sidebar-tab:hover {
    color: var(--text-primary, #1A1A1A);
}

.sidebar-tab.active {
    color: var(--sage, #8FA583);
    border-bottom-color: var(--sage, #8FA583);
}

.sidebar-tab.completed::after {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--sage, #8FA583);
}

.sidebar-tab svg {
    width: 18px;
    height: 18px;
}

.sidebar-content {
    flex: 1;
    overflow-y: auto;
    padding: 24px 20px;
}

.sidebar-panel {
    display: none;
}

.sidebar-panel.active {
    display: block;
}

/* Collapse toggle */
.sidebar-collapse-btn {
    position: absolute;
    right: -14px;
    top: 50%;
    transform: translateY(-50%);
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #fff;
    border: 1px solid var(--border, #E5E7EB);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    transition: transform 0.2s;
}

.sidebar-collapsed .sidebar-collapse-btn {
    right: -14px;
}

.sidebar-collapsed .sidebar-tabs,
.sidebar-collapsed .sidebar-content {
    display: none;
}

/* --- Sidebar form elements --- */

.sidebar-section {
    margin-bottom: 24px;
}

.sidebar-section h4 {
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-secondary, #6B7280);
    margin-bottom: 12px;
}

/* Font style previews */
.font-option {
    padding: 12px 16px;
    border: 2px solid var(--border, #E5E7EB);
    border-radius: 10px;
    cursor: pointer;
    margin-bottom: 8px;
    transition: border-color 0.2s;
}

.font-option:hover {
    border-color: var(--sage, #8FA583);
}

.font-option.selected {
    border-color: var(--sage, #8FA583);
    background: rgba(143,165,131,0.05);
}

.font-option input[type="radio"] {
    display: none;
}

/* Color presets */
.color-presets {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
}

.color-preset {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    cursor: pointer;
    border: 2px solid transparent;
    transition: border-color 0.2s, transform 0.2s;
    display: flex;
    overflow: hidden;
}

.color-preset:hover {
    transform: scale(1.1);
}

.color-preset.selected {
    border-color: var(--sage, #8FA583);
}

.color-preset-swatch {
    flex: 1;
}

/* Color pickers */
.color-row {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
}

.color-row label {
    font-size: 13px;
    flex: 1;
}

.color-row input[type="color"] {
    width: 32px;
    height: 32px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    padding: 0;
}

/* Section toggles */
.section-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid var(--border, #E5E7EB);
    cursor: grab;
}

.section-item:active {
    cursor: grabbing;
}

.section-item.dragging {
    opacity: 0.5;
    background: rgba(143,165,131,0.1);
    border-radius: 8px;
}

.section-drag-handle {
    color: var(--text-secondary, #6B7280);
    cursor: grab;
    flex-shrink: 0;
}

.section-drag-handle svg {
    width: 16px;
    height: 16px;
}

.section-info {
    flex: 1;
}

.section-info h5 {
    font-size: 14px;
    font-weight: 500;
}

.section-info p {
    font-size: 12px;
    color: var(--text-secondary, #6B7280);
}

/* Publish panel */
.publish-panel {
    padding: 20px;
    border-top: 1px solid var(--border, #E5E7EB);
    flex-shrink: 0;
}

.publish-checklist {
    margin-bottom: 12px;
}

.checklist-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 0;
    font-size: 13px;
}

.checklist-item.ok { color: var(--sage, #8FA583); }
.checklist-item.warn { color: var(--warning, #F59E0B); }

.publish-btn-full {
    width: 100%;
    padding: 14px;
    border: none;
    border-radius: 12px;
    background: var(--sage, #8FA583);
    color: #fff;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
}

.publish-btn-full:hover {
    background: #7a9470;
}

.publish-btn-full:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Save status indicator */
.save-status {
    font-size: 12px;
    color: var(--text-secondary, #6B7280);
    padding: 8px 20px;
    text-align: center;
    flex-shrink: 0;
    border-top: 1px solid var(--border, #E5E7EB);
}

.save-status.saving { color: var(--warning, #F59E0B); }
.save-status.saved { color: var(--sage, #8FA583); }
.save-status.error { color: var(--error, #EF4444); }

/* --- Preview/Editor panel --- */

.inv-preview-panel {
    background: var(--bg-secondary, #F3F4F6);
    padding: 24px;
    overflow-y: auto;
    min-height: calc(100vh - 60px);
}

.inv-preview {
    max-width: 900px;
    margin: 0 auto;
    background: var(--inv-background, #FAF9F7);
    border-radius: 12px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.08);
    overflow: hidden;
    position: relative;
}

/* Editor hover states */
.inv-preview [data-editable]:hover {
    outline: 2px dashed rgba(143,165,131,0.5);
    outline-offset: 4px;
    cursor: text;
}

.inv-preview [data-editable]:focus {
    outline: 2px solid var(--sage, #8FA583);
    outline-offset: 4px;
}

.inv-preview [data-editable][contenteditable="true"] {
    outline: 2px solid var(--sage, #8FA583);
    outline-offset: 4px;
    min-height: 1em;
}

/* Drag handles on sections */
.inv-preview [data-section] {
    position: relative;
}

.inv-preview [data-section]:hover .section-handle {
    opacity: 1;
}

.section-handle {
    position: absolute;
    left: -32px;
    top: 50%;
    transform: translateY(-50%);
    width: 24px;
    height: 24px;
    background: var(--sage, #8FA583);
    color: #fff;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: grab;
    opacity: 0;
    transition: opacity 0.2s;
    z-index: 5;
}

/* Floating toolbar */
.floating-toolbar {
    position: absolute;
    z-index: 50;
    background: #1A1A1A;
    color: #fff;
    border-radius: 10px;
    padding: 6px 8px;
    display: none;
    align-items: center;
    gap: 4px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    transform: translateX(-50%);
}

.floating-toolbar.visible {
    display: flex;
}

.toolbar-btn {
    width: 32px;
    height: 32px;
    border: none;
    background: transparent;
    color: #fff;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s;
}

.toolbar-btn:hover {
    background: rgba(255,255,255,0.15);
}

.toolbar-btn.active {
    background: rgba(255,255,255,0.2);
}

.toolbar-divider {
    width: 1px;
    height: 20px;
    background: rgba(255,255,255,0.2);
    margin: 0 4px;
}

.toolbar-color-input {
    width: 24px;
    height: 24px;
    border: 2px solid rgba(255,255,255,0.3);
    border-radius: 6px;
    padding: 0;
    cursor: pointer;
}

/* Font size selector in toolbar */
.toolbar-font-size {
    background: rgba(255,255,255,0.1);
    border: none;
    color: #fff;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 12px;
    width: 48px;
    text-align: center;
}

/* Responsive */
@media (max-width: 900px) {
    .inv-workspace {
        grid-template-columns: 1fr;
    }

    .inv-sidebar {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        height: auto;
        max-height: 50vh;
        z-index: 50;
        border-right: none;
        border-top: 1px solid var(--border, #E5E7EB);
        border-radius: 16px 16px 0 0;
    }

    .sidebar-tabs {
        overflow-x: auto;
    }

    .sidebar-collapse-btn {
        display: none;
    }

    .inv-preview-panel {
        padding: 12px;
        padding-bottom: 200px;
    }
}
```

**Step 3: Commit**

```bash
git add assets/css/invitation-editor.css
git commit -m "feat: add CSS for invitation editor (showcase, sidebar, preview, toolbar)"
```

---

## Task 2: Create the auto-save API endpoint

**Files:**
- Create: `api/invitation-autosave.php`

**Step 1: Write the endpoint**

```php
<?php
/**
 * Invitation Auto-Save API
 * Accepts partial config updates via JSON POST
 * Returns JSON {success: true} or {success: false, error: "..."}
 */

require_once __DIR__ . '/../config/saas.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth-account.php';
require_once __DIR__ . '/../includes/invitation-functions.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isAccountLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Ikke logget ind']);
    exit;
}

$accountId = getCurrentAccountId();
$db = getDB();

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ugyldig JSON']);
    exit;
}

$eventId = (int)($input['event_id'] ?? 0);
if (!$eventId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Manglende event_id']);
    exit;
}

// Verify access
$stmt = $db->prepare("
    SELECT e.id FROM events e
    JOIN event_owners eo ON e.id = eo.event_id AND eo.account_id = ?
    WHERE e.id = ?
");
$stmt->execute([$accountId, $eventId]);
if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Ingen adgang']);
    exit;
}

// Allowed fields for update
$allowedFields = [
    'layout_style', 'font_style',
    'color_primary', 'color_secondary', 'color_accent', 'color_text', 'color_background',
    'greeting_template', 'headline_text', 'invitation_message', 'closing_text',
    'show_countdown', 'show_map', 'show_schedule', 'show_rsvp',
    'sections_order', 'template_id'
];

// Merge with existing config
$existing = getInvitationConfig($db, $eventId);
$data = [];
foreach ($allowedFields as $field) {
    $data[$field] = isset($input[$field]) ? $input[$field] : ($existing[$field] ?? null);
}

// Boolean fields
foreach (['show_countdown', 'show_map', 'show_schedule', 'show_rsvp'] as $boolField) {
    $data[$boolField] = $data[$boolField] ? 1 : 0;
}

try {
    $result = saveInvitationConfig($db, $eventId, $data);
    echo json_encode(['success' => $result]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Serverfejl']);
}
```

**Step 2: Commit**

```bash
git add api/invitation-autosave.php
git commit -m "feat: add auto-save API for invitation editor"
```

---

## Task 3: Add partial HTML output to preview API

**Files:**
- Modify: `api/invitation-preview.php:101-502`

**Step 1: Add format=partial support**

Before line 102 (`// Output HTML preview`), add:

```php
// Check if partial format requested (for DOM injection in editor)
$isPartial = ($_REQUEST['format'] ?? '') === 'partial';
```

Then wrap the HTML output. Replace lines 103-502 with a conditional:
- If `$isPartial`: output only the inner `<div class="preview-container ...">...</div>` plus a `<style>` tag with the CSS — no `<!DOCTYPE>`, no `<html>`, no `<head>`, no `<body>`.
- If not partial: keep existing full HTML output unchanged.

The key change at line 102:

```php
$isPartial = ($_REQUEST['format'] ?? '') === 'partial';

if ($isPartial) {
    // Return just the CSS + layout HTML for DOM injection
    header('Content-Type: text/html; charset=UTF-8');
    echo '<style>';
    echo $cssVars;
    echo ':root { --font-display: ' . $fonts['display'] . '; --font-body: ' . $fonts['body'] . '; }';
    echo '</style>';
    // Fall through to same layout rendering below, but without HTML wrapper
} else {
    // Full HTML page (existing behavior for standalone preview)
    header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="da">
<!-- ... existing full HTML ... -->
<?php
    exit;
}
// Partial output - just the container div
?>
<div class="preview-container inv-preview layout-<?= htmlspecialchars($layoutStyle) ?>">
    <?php if ($layoutStyle === 'split'): ?>
        <!-- ... same layout rendering as before ... -->
    <?php endif; ?>
</div>
```

**Important:** The partial output needs `data-editable` attributes on text elements and `data-section` attributes on sections for the editor to target them. Add these attributes:

- `data-editable="greeting"` on the greeting `<p>`
- `data-editable="headline"` on the headline `<h1>`
- `data-editable="message"` on the message `<div>`
- `data-editable="closing"` on the closing `<p>`
- `data-section="details"` on event details
- `data-section="countdown"` on countdown
- `data-section="gallery"` on gallery
- `data-section="rsvp"` on RSVP section

**Step 2: Commit**

```bash
git add api/invitation-preview.php
git commit -m "feat: add partial HTML format to preview API for DOM editor"
```

---

## Task 4: Create the JavaScript editor

**Files:**
- Create: `assets/js/invitation-editor.js`

This is the largest task. Break into sub-sections within one file:

**Step 1: Create the file with core structure and auto-save**

```javascript
/**
 * Invitation Editor
 * Handles: showcase animations, sidebar sync, inline editing,
 * floating toolbar, drag-drop sections, auto-save
 */

(function() {
    'use strict';

    // --- State ---
    let config = {};       // Current invitation config (synced with server)
    let saveTimeout = null; // Debounce timer
    let saveStatus = 'saved'; // 'saved' | 'saving' | 'error'
    let currentLayout = '';
    let activeEditable = null; // Currently editing element

    // --- Init ---
    function init() {
        const showcaseEl = document.getElementById('layout-showcase');
        if (showcaseEl) {
            initShowcase();
        }

        const workspaceEl = document.getElementById('inv-workspace');
        if (workspaceEl) {
            config = JSON.parse(workspaceEl.dataset.config || '{}');
            currentLayout = config.layout_style || 'split';
            initSidebar();
            initPreviewEditor();
            initFloatingToolbar();
            initSectionDragDrop();
            initAutoSave();
        }
    }

    // --- Auto-Save ---
    function scheduleAutoSave() {
        if (saveTimeout) clearTimeout(saveTimeout);
        updateSaveStatus('unsaved');
        saveTimeout = setTimeout(doAutoSave, 2000);
    }

    function doAutoSave() {
        updateSaveStatus('saving');
        const payload = Object.assign({}, config, {
            event_id: getEventId()
        });

        fetch('/api/invitation-autosave.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            updateSaveStatus(data.success ? 'saved' : 'error');
        })
        .catch(function() {
            updateSaveStatus('error');
        });
    }

    function updateSaveStatus(status) {
        saveStatus = status;
        var el = document.getElementById('save-status');
        if (!el) return;
        var messages = {
            unsaved: 'Ændringer ikke gemt...',
            saving: 'Gemmer...',
            saved: 'Alle ændringer gemt',
            error: 'Kunne ikke gemme — prøver igen...'
        };
        el.textContent = messages[status] || '';
        el.className = 'save-status ' + status;

        if (status === 'error') {
            // Retry after 5s
            setTimeout(doAutoSave, 5000);
        }
    }

    function getEventId() {
        var el = document.getElementById('inv-workspace');
        return el ? parseInt(el.dataset.eventId, 10) : 0;
    }

    // --- Showcase ---
    function initShowcase() {
        var cards = document.querySelectorAll('.showcase-card');
        var continueBtn = document.querySelector('.showcase-continue');

        cards.forEach(function(card) {
            card.addEventListener('click', function() {
                // Deselect all
                cards.forEach(function(c) { c.classList.remove('selected'); });
                // Select this one
                card.classList.add('selected');
                // Show continue button
                if (continueBtn) continueBtn.classList.add('visible');
            });
        });
    }

    // --- Sidebar ---
    function initSidebar() {
        // Tab switching
        var tabs = document.querySelectorAll('.sidebar-tab');
        var panels = document.querySelectorAll('.sidebar-panel');

        tabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                var target = tab.dataset.panel;
                tabs.forEach(function(t) { t.classList.remove('active'); });
                panels.forEach(function(p) { p.classList.remove('active'); });
                tab.classList.add('active');
                var targetPanel = document.getElementById('panel-' + target);
                if (targetPanel) targetPanel.classList.add('active');
            });
        });

        // Collapse toggle
        var collapseBtn = document.querySelector('.sidebar-collapse-btn');
        if (collapseBtn) {
            collapseBtn.addEventListener('click', function() {
                document.getElementById('inv-workspace').classList.toggle('sidebar-collapsed');
            });
        }

        // Font style options
        document.querySelectorAll('.font-option').forEach(function(opt) {
            opt.addEventListener('click', function() {
                document.querySelectorAll('.font-option').forEach(function(o) { o.classList.remove('selected'); });
                opt.classList.add('selected');
                var radio = opt.querySelector('input[type="radio"]');
                if (radio) {
                    radio.checked = true;
                    config.font_style = radio.value;
                    applyFontToPreview(radio.value);
                    scheduleAutoSave();
                }
            });
        });

        // Color inputs
        document.querySelectorAll('.color-row input[type="color"]').forEach(function(input) {
            input.addEventListener('input', function() {
                var field = input.dataset.field;
                config[field] = input.value;
                applyCssVariable(field, input.value);
                scheduleAutoSave();
            });
        });

        // Color presets
        document.querySelectorAll('.color-preset').forEach(function(preset) {
            preset.addEventListener('click', function() {
                document.querySelectorAll('.color-preset').forEach(function(p) { p.classList.remove('selected'); });
                preset.classList.add('selected');
                var colors = JSON.parse(preset.dataset.colors);
                Object.keys(colors).forEach(function(key) {
                    config['color_' + key] = colors[key];
                    applyCssVariable('color_' + key, colors[key]);
                    var input = document.querySelector('.color-row input[data-field="color_' + key + '"]');
                    if (input) input.value = colors[key];
                });
                scheduleAutoSave();
            });
        });

        // Text inputs in sidebar
        document.querySelectorAll('.sidebar-panel input[data-field], .sidebar-panel textarea[data-field]').forEach(function(input) {
            input.addEventListener('input', function() {
                var field = input.dataset.field;
                config[field] = input.value;
                // Sync to preview
                var previewEl = document.querySelector('.inv-preview [data-editable="' + field + '"]');
                if (previewEl) {
                    if (field === 'invitation_message') {
                        previewEl.innerHTML = input.value.replace(/\n/g, '<br>');
                    } else {
                        previewEl.textContent = input.value;
                    }
                }
                scheduleAutoSave();
            });
        });

        // Section toggles
        document.querySelectorAll('.section-item input[type="checkbox"]').forEach(function(cb) {
            cb.addEventListener('change', function() {
                var field = cb.dataset.field;
                config[field] = cb.checked ? 1 : 0;
                var section = document.querySelector('.inv-preview [data-section="' + cb.dataset.section + '"]');
                if (section) {
                    section.style.display = cb.checked ? '' : 'none';
                }
                scheduleAutoSave();
            });
        });

        // Layout change (requires server-side re-render)
        var layoutSelect = document.getElementById('layout-change');
        if (layoutSelect) {
            layoutSelect.addEventListener('change', function() {
                config.layout_style = layoutSelect.value;
                currentLayout = layoutSelect.value;
                loadPreviewFromServer();
                scheduleAutoSave();
            });
        }
    }

    // --- Client-side preview updates ---
    function applyCssVariable(field, value) {
        var varMap = {
            color_primary: '--inv-primary',
            color_secondary: '--inv-secondary',
            color_accent: '--inv-accent',
            color_text: '--inv-text',
            color_background: '--inv-background'
        };
        var varName = varMap[field];
        if (varName) {
            var preview = document.querySelector('.inv-preview');
            if (preview) preview.style.setProperty(varName, value);
        }
    }

    var fontMap = {
        elegant: { display: "'Cormorant Garamond', Georgia, serif", body: "'DM Sans', -apple-system, sans-serif" },
        modern: { display: "'Inter', -apple-system, sans-serif", body: "'Inter', -apple-system, sans-serif" },
        playful: { display: "'Quicksand', 'Comic Sans MS', sans-serif", body: "'Nunito', sans-serif" },
        traditional: { display: "'Playfair Display', Georgia, serif", body: "'Lora', Georgia, serif" },
        minimal: { display: "'DM Sans', -apple-system, sans-serif", body: "'DM Sans', -apple-system, sans-serif" }
    };

    function applyFontToPreview(fontStyle) {
        var fonts = fontMap[fontStyle] || fontMap.elegant;
        var preview = document.querySelector('.inv-preview');
        if (preview) {
            preview.style.setProperty('--font-display', fonts.display);
            preview.style.setProperty('--font-body', fonts.body);
        }
    }

    // --- Server-side preview reload (layout changes) ---
    function loadPreviewFromServer() {
        var previewContainer = document.querySelector('.inv-preview');
        if (!previewContainer) return;

        previewContainer.style.opacity = '0.5';

        fetch('/api/invitation-preview.php?event_id=' + getEventId() + '&format=partial', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(config)
        })
        .then(function(res) { return res.text(); })
        .then(function(html) {
            previewContainer.innerHTML = html;
            previewContainer.style.opacity = '1';
            // Re-init editor features on new DOM
            initPreviewEditor();
            initSectionDragDrop();
        })
        .catch(function() {
            previewContainer.style.opacity = '1';
        });
    }

    // --- Inline Editing ---
    function initPreviewEditor() {
        var editables = document.querySelectorAll('.inv-preview [data-editable]');

        editables.forEach(function(el) {
            el.addEventListener('click', function(e) {
                e.stopPropagation();
                startInlineEdit(el);
            });
        });

        // Click outside to stop editing
        document.addEventListener('click', function(e) {
            if (activeEditable && !activeEditable.contains(e.target) && !e.target.closest('.floating-toolbar')) {
                stopInlineEdit();
            }
        });

        // Escape to stop editing
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && activeEditable) {
                stopInlineEdit();
            }
        });
    }

    function startInlineEdit(el) {
        if (activeEditable === el) return;
        if (activeEditable) stopInlineEdit();

        activeEditable = el;
        el.setAttribute('contenteditable', 'true');
        el.focus();

        // Show toolbar
        showToolbar(el);

        // Highlight corresponding sidebar field
        var field = el.dataset.editable;
        var sidebarInput = document.querySelector('.sidebar-panel [data-field="' + field + '"]');
        if (sidebarInput) {
            sidebarInput.classList.add('editing-active');
        }

        // Listen for input
        el.addEventListener('input', onEditableInput);
    }

    function stopInlineEdit() {
        if (!activeEditable) return;

        var field = activeEditable.dataset.editable;
        activeEditable.removeAttribute('contenteditable');
        activeEditable.removeEventListener('input', onEditableInput);

        // Sync final value to config and sidebar
        syncEditableToConfig(activeEditable);

        // Remove sidebar highlight
        var sidebarInput = document.querySelector('.sidebar-panel [data-field="' + field + '"]');
        if (sidebarInput) {
            sidebarInput.classList.remove('editing-active');
        }

        activeEditable = null;
        hideToolbar();
    }

    function onEditableInput(e) {
        syncEditableToConfig(e.target);
        scheduleAutoSave();
    }

    function syncEditableToConfig(el) {
        var field = el.dataset.editable;
        var value;

        if (field === 'invitation_message') {
            // Convert <br> back to newlines
            value = el.innerHTML.replace(/<br\s*\/?>/gi, '\n').replace(/<[^>]+>/g, '');
        } else {
            value = el.textContent;
        }

        config[field] = value;

        // Sync to sidebar input
        var sidebarInput = document.querySelector('.sidebar-panel [data-field="' + field + '"]');
        if (sidebarInput) {
            sidebarInput.value = value;
        }
    }

    // --- Floating Toolbar ---
    var toolbar = null;

    function initFloatingToolbar() {
        toolbar = document.getElementById('floating-toolbar');
        if (!toolbar) return;

        // Bold button
        var boldBtn = toolbar.querySelector('[data-action="bold"]');
        if (boldBtn) {
            boldBtn.addEventListener('click', function(e) {
                e.preventDefault();
                document.execCommand('bold', false, null);
                boldBtn.classList.toggle('active');
            });
        }

        // Italic button
        var italicBtn = toolbar.querySelector('[data-action="italic"]');
        if (italicBtn) {
            italicBtn.addEventListener('click', function(e) {
                e.preventDefault();
                document.execCommand('italic', false, null);
                italicBtn.classList.toggle('active');
            });
        }

        // Color picker in toolbar
        var colorInput = toolbar.querySelector('.toolbar-color-input');
        if (colorInput) {
            colorInput.addEventListener('input', function() {
                document.execCommand('foreColor', false, colorInput.value);
            });
        }
    }

    function showToolbar(el) {
        if (!toolbar) return;
        var rect = el.getBoundingClientRect();
        var previewPanel = document.querySelector('.inv-preview-panel');
        var panelRect = previewPanel ? previewPanel.getBoundingClientRect() : { left: 0, top: 0 };

        toolbar.style.left = (rect.left + rect.width / 2 - panelRect.left + previewPanel.scrollLeft) + 'px';
        toolbar.style.top = (rect.top - panelRect.top + previewPanel.scrollTop - 48) + 'px';
        toolbar.classList.add('visible');
    }

    function hideToolbar() {
        if (toolbar) toolbar.classList.remove('visible');
    }

    // --- Section Drag & Drop ---
    function initSectionDragDrop() {
        // Sidebar section list
        var sectionList = document.getElementById('section-list');
        if (!sectionList) return;

        var items = sectionList.querySelectorAll('.section-item');
        var dragItem = null;

        items.forEach(function(item) {
            var handle = item.querySelector('.section-drag-handle');
            if (!handle) return;

            handle.addEventListener('mousedown', function(e) {
                dragItem = item;
                item.classList.add('dragging');
                e.preventDefault();
            });
        });

        document.addEventListener('mousemove', function(e) {
            if (!dragItem) return;
            var siblings = Array.from(sectionList.querySelectorAll('.section-item:not(.dragging)'));
            var nextSibling = siblings.find(function(sibling) {
                var rect = sibling.getBoundingClientRect();
                return e.clientY < rect.top + rect.height / 2;
            });
            sectionList.insertBefore(dragItem, nextSibling || null);
        });

        document.addEventListener('mouseup', function() {
            if (!dragItem) return;
            dragItem.classList.remove('dragging');
            dragItem = null;
            // Update sections order in config
            updateSectionsOrder();
            scheduleAutoSave();
        });
    }

    function updateSectionsOrder() {
        var items = document.querySelectorAll('#section-list .section-item');
        var order = {};
        var position = 1;
        items.forEach(function(item) {
            var key = item.dataset.section;
            var checkbox = item.querySelector('input[type="checkbox"]');
            order[key] = {
                enabled: checkbox ? checkbox.checked : true,
                position: position++
            };
        });
        config.sections_order = order;
    }

    // --- Start ---
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
```

**Step 2: Commit**

```bash
git add assets/js/invitation-editor.js
git commit -m "feat: add JavaScript for invitation editor (showcase, inline editing, toolbar, drag-drop, auto-save)"
```

---

## Task 5: Rewrite the invitation PHP page

**Files:**
- Rewrite: `app/events/pages/invitation.php`

This is the main page that ties everything together. It replaces the current 6-step wizard.

**Step 1: Write the new invitation.php**

The page has two modes:
1. **Showcase mode** (`step=1` or no layout chosen yet) — fullscreen layout selection
2. **Workspace mode** (after layout chosen) — sidebar + preview/editor

```php
<?php
/**
 * Invitation Editor
 * Layout showcase + sidebar/editor workspace
 */

require_once __DIR__ . '/../../../includes/invitation-functions.php';

// Get invitation config
$invitationConfig = getInvitationConfig($db, $eventId);
$images = getInvitationImages($db, $eventId);
$readiness = isInvitationReadyToPublish($db, $eventId);

// Organize images by role
$heroImage = null;
$galleryImages = [];
foreach ($images as $image) {
    if ($image['image_role'] === 'hero') {
        $heroImage = $image;
    } else {
        $galleryImages[] = $image;
    }
}

// Handle POST actions (publish, layout select from showcase)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyAccountCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Ugyldig anmodning. Prøv igen.');
        redirect("/app/events/manage.php?id=$eventId&page=invitation");
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'select-layout') {
        $layout = $_POST['layout_style'] ?? 'split';
        $allowed = ['split', 'centered', 'fullscreen', 'minimal', 'classic', 'slideshow'];
        if (in_array($layout, $allowed)) {
            saveInvitationConfig($db, $eventId, array_merge(
                (array)$invitationConfig,
                ['layout_style' => $layout]
            ));
        }
        redirect("/app/events/manage.php?id=$eventId&page=invitation&mode=editor");
    }

    if ($action === 'publish') {
        $publish = (int)($_POST['publish'] ?? 0);
        setInvitationPublished($db, $eventId, $publish === 1);
        setFlash('success', $publish ? 'Invitation offentliggjort!' : 'Invitation skjult.');
        redirect("/app/events/manage.php?id=$eventId&page=invitation&mode=editor");
    }
}

// Determine mode
$mode = $_GET['mode'] ?? '';
$hasLayout = !empty($invitationConfig['id']) && !empty($invitationConfig['layout_style']);

// If user has already configured a layout, go straight to editor
if ($mode !== 'showcase' && $hasLayout) {
    $mode = 'editor';
} else if (!$hasLayout) {
    $mode = 'showcase';
}

// Font families for Google Fonts link
$googleFontsUrl = 'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=DM+Sans:wght@400;500;600&family=Playfair+Display:wght@400;500;600&family=Inter:wght@400;500;600&family=Quicksand:wght@400;500;600&family=Nunito:wght@400;500;600&family=Lora:wght@400;500;600&display=swap';

// Prepare config JSON for JS
$configJson = htmlspecialchars(json_encode([
    'layout_style' => $invitationConfig['layout_style'] ?? 'split',
    'font_style' => $invitationConfig['font_style'] ?? 'elegant',
    'color_primary' => $invitationConfig['color_primary'] ?? '#1A1A1A',
    'color_secondary' => $invitationConfig['color_secondary'] ?? '#8FA583',
    'color_accent' => $invitationConfig['color_accent'] ?? '#B8923D',
    'color_text' => $invitationConfig['color_text'] ?? '#1A1A1A',
    'color_background' => $invitationConfig['color_background'] ?? '#FAF9F7',
    'greeting_template' => $invitationConfig['greeting_template'] ?? 'Kære {guest_name}',
    'headline_text' => $invitationConfig['headline_text'] ?? '',
    'invitation_message' => $invitationConfig['invitation_message'] ?? '',
    'closing_text' => $invitationConfig['closing_text'] ?? '',
    'show_countdown' => (int)($invitationConfig['show_countdown'] ?? 1),
    'show_map' => (int)($invitationConfig['show_map'] ?? 0),
    'show_schedule' => (int)($invitationConfig['show_schedule'] ?? 1),
    'show_rsvp' => (int)($invitationConfig['show_rsvp'] ?? 1),
    'sections_order' => $invitationConfig['sections_order'] ?? null,
    'template_id' => $invitationConfig['template_id'] ?? null
]), ENT_QUOTES, 'UTF-8');

// Color presets
$colorPresets = [
    ['name' => 'Nordisk', 'colors' => ['primary' => '#1A1A1A', 'secondary' => '#8FA583', 'accent' => '#B8923D', 'text' => '#1A1A1A', 'background' => '#FAF9F7']],
    ['name' => 'Romantisk', 'colors' => ['primary' => '#2D1B1B', 'secondary' => '#D4A5A5', 'accent' => '#B88A8A', 'text' => '#2D1B1B', 'background' => '#FFF8F6']],
    ['name' => 'Moderne', 'colors' => ['primary' => '#111827', 'secondary' => '#3B82F6', 'accent' => '#8B5CF6', 'text' => '#1F2937', 'background' => '#F9FAFB']],
    ['name' => 'Varm', 'colors' => ['primary' => '#292524', 'secondary' => '#D97706', 'accent' => '#B45309', 'text' => '#292524', 'background' => '#FFFBEB']],
    ['name' => 'Mørk', 'colors' => ['primary' => '#F9FAFB', 'secondary' => '#8FA583', 'accent' => '#D4AF37', 'text' => '#E5E7EB', 'background' => '#1A1A1A']]
];

// Layout descriptions for showcase
$layouts = [
    'split' => ['name' => 'Delt', 'desc' => 'Elegant to-kolonne med sticky billede'],
    'centered' => ['name' => 'Centreret', 'desc' => 'Billede over centreret indhold'],
    'fullscreen' => ['name' => 'Fullscreen', 'desc' => 'Dramatisk hero med overlay-tekst'],
    'minimal' => ['name' => 'Minimal', 'desc' => 'Ren og enkel med cirkulært billede'],
    'classic' => ['name' => 'Klassisk', 'desc' => 'Traditionelt kort med elegant ramme'],
    'slideshow' => ['name' => 'Slideshow', 'desc' => 'Filmisk galleri med billede-karrusel']
];

// Font style options
$fontStyles = [
    'elegant' => ['name' => 'Elegant', 'font' => "'Cormorant Garamond', serif", 'sample' => 'Kære Anna'],
    'modern' => ['name' => 'Moderne', 'font' => "'Inter', sans-serif", 'sample' => 'Kære Anna'],
    'playful' => ['name' => 'Legende', 'font' => "'Quicksand', sans-serif", 'sample' => 'Kære Anna'],
    'traditional' => ['name' => 'Traditionel', 'font' => "'Playfair Display', serif", 'sample' => 'Kære Anna'],
    'minimal' => ['name' => 'Minimalistisk', 'font' => "'DM Sans', sans-serif", 'sample' => 'Kære Anna']
];

// Sections config
$sections = [
    'countdown' => ['name' => 'Nedtælling', 'desc' => 'Vis nedtælling til arrangementet', 'field' => 'show_countdown'],
    'rsvp' => ['name' => 'RSVP', 'desc' => 'Vis svar-sektion', 'field' => 'show_rsvp'],
    'schedule' => ['name' => 'Program', 'desc' => 'Vis link til programmet', 'field' => 'show_schedule'],
    'map' => ['name' => 'Kort', 'desc' => 'Vis kort over lokationen', 'field' => 'show_map']
];
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="<?= $googleFontsUrl ?>" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/invitation-editor.css">

<?php if ($mode === 'showcase'): ?>
<!-- ========== LAYOUT SHOWCASE (Fullscreen) ========== -->
<div class="showcase-fullscreen" id="layout-showcase">
    <div class="showcase-header">
        <h2>Vælg dit layout</h2>
        <p>Hover over kortene for at se layoutet i aktion</p>
    </div>

    <form method="POST" id="layout-form">
        <?= accountCsrfField() ?>
        <input type="hidden" name="action" value="select-layout">
        <input type="hidden" name="layout_style" id="selected-layout" value="">

        <div class="showcase-grid">
            <?php foreach ($layouts as $key => $layout): ?>
            <div class="showcase-card" data-layout="<?= $key ?>" onclick="document.getElementById('selected-layout').value='<?= $key ?>';this.closest('.showcase-grid').querySelectorAll('.showcase-card').forEach(function(c){c.classList.remove('selected')});this.classList.add('selected');document.querySelector('.showcase-continue').classList.add('visible');">
                <div class="showcase-preview">
                    <?php include __DIR__ . '/invitation-showcase-mocks/' . $key . '.php'; ?>
                </div>
                <div class="showcase-info">
                    <h3><?= $layout['name'] ?></h3>
                    <p><?= $layout['desc'] ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="showcase-continue">
            <button type="submit" class="btn btn-primary btn-lg">
                Fortsæt med valgt layout
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                </svg>
            </button>
        </div>
    </form>
</div>

<?php else: ?>
<!-- ========== WORKSPACE (Sidebar + Preview/Editor) ========== -->
<div class="inv-workspace" id="inv-workspace" data-config="<?= $configJson ?>" data-event-id="<?= $eventId ?>">

    <!-- Sidebar -->
    <div class="inv-sidebar">
        <button class="sidebar-collapse-btn" title="Fold sidebar">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
        </button>

        <div class="sidebar-tabs">
            <button class="sidebar-tab active" data-panel="images" title="Billeder">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                <span>Billeder</span>
            </button>
            <button class="sidebar-tab" data-panel="text" title="Tekst">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                <span>Tekst</span>
            </button>
            <button class="sidebar-tab" data-panel="design" title="Design">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path></svg>
                <span>Design</span>
            </button>
            <button class="sidebar-tab" data-panel="sections" title="Sektioner">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                <span>Sektioner</span>
            </button>
            <button class="sidebar-tab" data-panel="publish" title="Publicer">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>Publicer</span>
            </button>
        </div>

        <div class="sidebar-content">
            <!-- Panel: Images -->
            <div class="sidebar-panel active" id="panel-images">
                <div class="sidebar-section">
                    <h4>Hovedbillede</h4>
                    <input type="file" id="hero-upload" accept="image/*" style="display:none">
                    <div class="upload-zone" onclick="document.getElementById('hero-upload').click()">
                        <?php if ($heroImage): ?>
                        <div style="width:100%;height:140px;background:url(/uploads/invitations/<?= htmlspecialchars($heroImage['filename']) ?>) center/cover;border-radius:8px;"></div>
                        <?php else: ?>
                        <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="opacity:0.4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        <p style="font-size:13px;color:var(--text-secondary);">Klik for at uploade</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="sidebar-section">
                    <h4>Galleri</h4>
                    <input type="file" id="gallery-upload" accept="image/*" multiple style="display:none">
                    <div class="image-grid" id="image-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;">
                        <?php foreach ($galleryImages as $image): ?>
                        <div style="aspect-ratio:1;background:url(/uploads/invitations/<?= htmlspecialchars($image['filename']) ?>) center/cover;border-radius:8px;position:relative;" data-id="<?= $image['id'] ?>">
                            <button onclick="deleteImage(<?= $image['id'] ?>)" style="position:absolute;top:4px;right:4px;width:20px;height:20px;border-radius:50%;background:rgba(0,0,0,0.6);color:#fff;border:none;cursor:pointer;font-size:12px;display:flex;align-items:center;justify-content:center;">&times;</button>
                        </div>
                        <?php endforeach; ?>
                        <div class="upload-zone" onclick="document.getElementById('gallery-upload').click()" style="aspect-ratio:1;display:flex;align-items:center;justify-content:center;border:2px dashed var(--border);border-radius:8px;cursor:pointer;">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="opacity:0.4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel: Text -->
            <div class="sidebar-panel" id="panel-text">
                <div class="sidebar-section">
                    <h4>Hilsen</h4>
                    <input type="text" class="form-input" data-field="greeting_template"
                           value="<?= htmlspecialchars($invitationConfig['greeting_template'] ?? 'Kære {guest_name}') ?>"
                           placeholder="Kære {guest_name}">
                    <p class="form-hint" style="font-size:12px;color:var(--text-secondary);margin-top:4px;">Brug {guest_name} for gæstens navn</p>
                </div>

                <div class="sidebar-section">
                    <h4>Overskrift</h4>
                    <input type="text" class="form-input" data-field="headline_text"
                           value="<?= htmlspecialchars($invitationConfig['headline_text'] ?? '') ?>"
                           placeholder="<?= htmlspecialchars($event['name']) ?>">
                </div>

                <div class="sidebar-section">
                    <h4>Besked</h4>
                    <textarea class="form-input" data-field="invitation_message" rows="5"
                              placeholder="Skriv din personlige invitation her..."><?= htmlspecialchars($invitationConfig['invitation_message'] ?? '') ?></textarea>
                </div>

                <div class="sidebar-section">
                    <h4>Afslutning</h4>
                    <input type="text" class="form-input" data-field="closing_text"
                           value="<?= htmlspecialchars($invitationConfig['closing_text'] ?? '') ?>"
                           placeholder="Vi glæder os til at se jer!">
                </div>
            </div>

            <!-- Panel: Design -->
            <div class="sidebar-panel" id="panel-design">
                <div class="sidebar-section">
                    <h4>Layout</h4>
                    <select id="layout-change" class="form-input" style="margin-bottom:16px;">
                        <?php foreach ($layouts as $key => $layout): ?>
                        <option value="<?= $key ?>" <?= ($invitationConfig['layout_style'] ?? 'split') === $key ? 'selected' : '' ?>>
                            <?= $layout['name'] ?> — <?= $layout['desc'] ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <a href="?id=<?= $eventId ?>&page=invitation&mode=showcase" class="btn btn-secondary btn-sm" style="width:100%;text-align:center;">Vælg nyt layout</a>
                </div>

                <div class="sidebar-section">
                    <h4>Skrifttype</h4>
                    <?php foreach ($fontStyles as $key => $font): ?>
                    <label class="font-option <?= ($invitationConfig['font_style'] ?? 'elegant') === $key ? 'selected' : '' ?>">
                        <input type="radio" name="font_style" value="<?= $key ?>" <?= ($invitationConfig['font_style'] ?? 'elegant') === $key ? 'checked' : '' ?>>
                        <span style="font-family:<?= $font['font'] ?>;font-size:18px;"><?= $font['sample'] ?></span>
                        <span style="font-size:12px;color:var(--text-secondary);margin-left:8px;"><?= $font['name'] ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>

                <div class="sidebar-section">
                    <h4>Farvepaletter</h4>
                    <div class="color-presets">
                        <?php foreach ($colorPresets as $preset): ?>
                        <div class="color-preset" title="<?= $preset['name'] ?>" data-colors='<?= json_encode($preset['colors']) ?>'>
                            <?php foreach (['secondary', 'accent', 'background'] as $c): ?>
                            <div class="color-preset-swatch" style="background:<?= $preset['colors'][$c] ?>;"></div>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="sidebar-section">
                    <h4>Farver</h4>
                    <?php
                    $colorFields = [
                        'color_primary' => 'Primær',
                        'color_secondary' => 'Sekundær',
                        'color_accent' => 'Accent',
                        'color_text' => 'Tekst',
                        'color_background' => 'Baggrund'
                    ];
                    foreach ($colorFields as $field => $label):
                    ?>
                    <div class="color-row">
                        <label><?= $label ?></label>
                        <input type="color" data-field="<?= $field ?>" value="<?= htmlspecialchars($invitationConfig[$field] ?? '#000000') ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Panel: Sections -->
            <div class="sidebar-panel" id="panel-sections">
                <div class="sidebar-section">
                    <h4>Sektioner</h4>
                    <p style="font-size:12px;color:var(--text-secondary);margin-bottom:12px;">Træk for at ændre rækkefølge</p>
                    <div id="section-list">
                        <?php foreach ($sections as $key => $section): ?>
                        <div class="section-item" data-section="<?= $key ?>">
                            <div class="section-drag-handle">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path></svg>
                            </div>
                            <div class="section-info">
                                <h5><?= $section['name'] ?></h5>
                                <p><?= $section['desc'] ?></p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" data-field="<?= $section['field'] ?>" data-section="<?= $key ?>"
                                       <?= ($invitationConfig[$section['field']] ?? 0) ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Panel: Publish -->
            <div class="sidebar-panel" id="panel-publish">
                <div class="sidebar-section">
                    <h4>Tjekliste</h4>
                    <div class="publish-checklist">
                        <div class="checklist-item <?= $heroImage ? 'ok' : 'warn' ?>">
                            <?= $heroImage ? '✓' : '!' ?> Hovedbillede
                        </div>
                        <div class="checklist-item <?= !empty($invitationConfig['invitation_message']) ? 'ok' : 'warn' ?>">
                            <?= !empty($invitationConfig['invitation_message']) ? '✓' : '!' ?> Invitationsbesked
                        </div>
                    </div>
                </div>

                <div class="sidebar-section">
                    <h4>Status</h4>
                    <?php if ($invitationConfig['is_published']): ?>
                    <p style="color:var(--sage);margin-bottom:12px;">Din invitation er offentliggjort</p>
                    <?php else: ?>
                    <p style="color:var(--text-secondary);margin-bottom:12px;">Invitationen er ikke offentliggjort endnu</p>
                    <?php endif; ?>

                    <form method="POST">
                        <?= accountCsrfField() ?>
                        <input type="hidden" name="action" value="publish">
                        <?php if ($invitationConfig['is_published']): ?>
                        <button type="submit" name="publish" value="0" class="btn btn-secondary" style="width:100%;">Skjul invitation</button>
                        <?php else: ?>
                        <button type="submit" name="publish" value="1" class="publish-btn-full" <?= !$readiness['ready'] ? 'disabled' : '' ?>>
                            Offentliggør invitation
                        </button>
                        <?php endif; ?>
                    </form>
                </div>

                <?php if ($invitationConfig['is_published']): ?>
                <div class="sidebar-section">
                    <h4>Del</h4>
                    <div style="display:flex;gap:8px;">
                        <input type="text" class="form-input" value="<?= htmlspecialchars('https://' . ($_SERVER['HTTP_HOST'] ?? 'partyparart.dk') . '/e/' . ($event['slug'] ?? '')) ?>" readonly style="flex:1;font-size:12px;">
                        <button class="btn btn-secondary btn-sm" onclick="navigator.clipboard.writeText(this.previousElementSibling.value);this.textContent='Kopieret!';setTimeout(()=>this.textContent='Kopier',2000)">Kopier</button>
                    </div>
                </div>
                <?php endif; ?>

                <div class="sidebar-section" style="margin-top:24px;">
                    <a href="?id=<?= $eventId ?>&page=invitation-send" class="btn btn-sage" style="width:100%;text-align:center;">
                        Send invitationer
                    </a>
                </div>
            </div>
        </div>

        <div class="save-status saved" id="save-status">Alle ændringer gemt</div>
    </div>

    <!-- Preview/Editor Panel -->
    <div class="inv-preview-panel">
        <!-- Floating Toolbar -->
        <div class="floating-toolbar" id="floating-toolbar">
            <button class="toolbar-btn" data-action="bold" title="Fed">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M6 4h8a4 4 0 014 4 4 4 0 01-4 4H6zm0 8h9a4 4 0 014 4 4 4 0 01-4 4H6z"/></svg>
            </button>
            <button class="toolbar-btn" data-action="italic" title="Kursiv">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M10 4h4l-2 16h-4z"/></svg>
            </button>
            <div class="toolbar-divider"></div>
            <input type="color" class="toolbar-color-input" value="#1A1A1A" title="Tekstfarve">
        </div>

        <!-- Preview Container (DOM-rendered, not iframe) -->
        <div class="inv-preview" id="inv-preview">
            Loading...
        </div>
    </div>
</div>

<script>
// Image upload handlers (reuse existing pattern)
var eventId = <?= $eventId ?>;

function setupImageUpload(inputId, role) {
    var input = document.getElementById(inputId);
    if (!input) return;
    input.addEventListener('change', function() {
        Array.from(input.files).forEach(function(file) {
            if (!file.type.startsWith('image/')) return;
            var formData = new FormData();
            formData.append('image', file);
            formData.append('event_id', eventId);
            formData.append('action', 'upload');
            formData.append('role', role);
            fetch('/api/invitation-images.php', { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(d) { if (d.success) location.reload(); else alert(d.error || 'Upload fejlede'); })
                .catch(function() { alert('Upload fejlede'); });
        });
    });
}

setupImageUpload('hero-upload', 'hero');
setupImageUpload('gallery-upload', 'gallery');

function deleteImage(imageId) {
    if (!confirm('Slet dette billede?')) return;
    fetch('/api/invitation-images.php?event_id=' + eventId + '&action=delete&image_id=' + imageId)
        .then(function(r) { return r.json(); })
        .then(function(d) { if (d.success) location.reload(); });
}

// Load initial preview from server
document.addEventListener('DOMContentLoaded', function() {
    var preview = document.getElementById('inv-preview');
    if (!preview) return;
    var config = JSON.parse(document.getElementById('inv-workspace').dataset.config);
    fetch('/api/invitation-preview.php?event_id=' + eventId + '&format=partial', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(config)
    })
    .then(function(r) { return r.text(); })
    .then(function(html) { preview.innerHTML = html; })
    .catch(function() { preview.innerHTML = '<p style="padding:40px;text-align:center;">Kunne ikke indlæse preview</p>'; });
});
</script>
<script src="/assets/js/invitation-editor.js"></script>
<?php endif; ?>
```

**Step 2: Create showcase mock directory and files**

Create `app/events/pages/invitation-showcase-mocks/` with one PHP file per layout containing static HTML mockups used inside the showcase cards. These are simplified visual representations, not the actual layouts.

Example for `split.php`:

```php
<div style="display:grid;grid-template-columns:1fr 1fr;height:100%;background:#FAF9F7;">
    <div style="background:linear-gradient(135deg,#8FA583,#B8923D);"></div>
    <div class="mock-content" style="padding:20px 16px;display:flex;flex-direction:column;justify-content:center;">
        <div class="mock-text-line" style="width:60%;height:8px;background:#1A1A1A;border-radius:4px;margin-bottom:8px;opacity:0.3;"></div>
        <div class="mock-text-line" style="width:80%;height:14px;background:#1A1A1A;border-radius:4px;margin-bottom:12px;opacity:0.7;"></div>
        <div class="mock-text-line" style="width:90%;height:6px;background:#1A1A1A;border-radius:4px;margin-bottom:4px;opacity:0.2;"></div>
        <div class="mock-text-line" style="width:70%;height:6px;background:#1A1A1A;border-radius:4px;margin-bottom:4px;opacity:0.2;"></div>
        <div class="mock-text-line" style="width:50%;height:6px;background:#B8923D;border-radius:4px;opacity:0.4;margin-top:12px;"></div>
    </div>
</div>
```

Create similar mocks for: `centered.php`, `fullscreen.php`, `minimal.php`, `classic.php`, `slideshow.php`.

**Step 3: Commit**

```bash
git add app/events/pages/invitation.php app/events/pages/invitation-showcase-mocks/
git commit -m "feat: rewrite invitation page with layout showcase and sidebar/editor workspace"
```

---

## Task 6: Update preview API for partial format with data-attributes

**Files:**
- Modify: `api/invitation-preview.php`
- Modify: `e/partials/invitation-content.php`

**Step 1: Add data-editable attributes to invitation-content.php**

Change the content partial to include editor hooks:

```php
<!-- In invitation-content.php, add data attributes -->
<p class="greeting" data-editable="greeting_template"><?= htmlspecialchars($greeting) ?></p>

<h1 class="headline" data-editable="headline_text"><?= htmlspecialchars($config['headline_text'] ?? '') ?></h1>

<div class="message" data-editable="invitation_message"><?= nl2br(htmlspecialchars($config['invitation_message'] ?? '')) ?></div>

<!-- Wrap sections with data-section -->
<div class="event-details" data-section="details">...</div>

<div class="countdown" data-section="countdown" id="countdown">...</div>

<div class="gallery" data-section="gallery">...</div>

<div class="rsvp-section" data-section="rsvp">...</div>

<p class="closing" data-editable="closing_text"><?= htmlspecialchars($config['closing_text'] ?? '') ?></p>
```

**Step 2: Add partial format support to preview API**

Insert before line 102 of `api/invitation-preview.php`:

```php
$isPartial = ($_REQUEST['format'] ?? '') === 'partial';
```

Then wrap existing HTML output in an `if (!$isPartial)` block, and add the partial output path that outputs just the container div with inline style tag.

**Step 3: Commit**

```bash
git add api/invitation-preview.php e/partials/invitation-content.php
git commit -m "feat: add data-editable/data-section attributes and partial format for editor"
```

---

## Task 7: Create the 6 showcase mock files

**Files:**
- Create: `app/events/pages/invitation-showcase-mocks/split.php`
- Create: `app/events/pages/invitation-showcase-mocks/centered.php`
- Create: `app/events/pages/invitation-showcase-mocks/fullscreen.php`
- Create: `app/events/pages/invitation-showcase-mocks/minimal.php`
- Create: `app/events/pages/invitation-showcase-mocks/classic.php`
- Create: `app/events/pages/invitation-showcase-mocks/slideshow.php`

Each file is a static HTML mockup (~20-40 lines) that represents the layout visually within a showcase card. Uses colored blocks and lines to simulate text and images, with CSS class hooks for the hover animations defined in Task 1.

**Step 1: Create directory and all 6 files**

See Task 5 for the split.php example. Each mock follows the same pattern:
- Uses the layout's distinctive visual structure (2 columns for split, card for classic, etc.)
- Uses `.mock-text-line`, `.mock-content`, `.mock-overlay`, `.mock-circle`, `.mock-slide` etc. classes
- These classes are targeted by the hover animations in `invitation-editor.css`

**Step 2: Commit**

```bash
git add app/events/pages/invitation-showcase-mocks/
git commit -m "feat: add showcase mock templates for 6 invitation layouts"
```

---

## Task 8: Integration test and polish

**Step 1: Manual test**

Navigate to `/app/events/manage.php?id={any_event_id}&page=invitation` and verify:

1. Showcase appears fullscreen with 6 animated cards
2. Hover animations play on each card
3. Clicking a card selects it, continue button appears
4. After selecting, workspace loads with sidebar + preview
5. Tab switching works in sidebar
6. Text editing in sidebar syncs to preview
7. Clicking text in preview enables inline editing
8. Color changes apply instantly
9. Font changes apply instantly
10. Layout change via dropdown triggers server-side reload
11. Auto-save fires after 2 seconds
12. Section toggles show/hide sections in preview
13. Publish panel works correctly

**Step 2: Fix any issues found during testing**

**Step 3: Final commit**

```bash
git add -A
git commit -m "fix: polish invitation editor after integration testing"
```

---

## Execution Order Summary

| Task | Description | Dependencies | Est. Complexity |
|------|------------|-------------|----------------|
| 1 | CSS file | None | Medium |
| 2 | Auto-save API | None | Low |
| 3 | Preview API partial format | None | Low |
| 7 | Showcase mock files | None | Low |
| 4 | JavaScript editor | Tasks 1, 2, 3 | High |
| 5 | Rewrite invitation.php | Tasks 1, 4, 7 | High |
| 6 | Data attributes on partials | None | Low |
| 8 | Integration test | All above | Medium |

Tasks 1, 2, 3, 6, 7 can be done in parallel. Tasks 4 and 5 depend on them. Task 8 is last.

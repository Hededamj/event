<?php
/**
 * Invitation Editor
 * Layout showcase + sidebar/editor workspace
 */
require_once __DIR__ . '/../../../includes/invitation-functions.php';

$invitationConfig = getInvitationConfig($db, $eventId);
$images = getInvitationImages($db, $eventId);
$readiness = isInvitationReadyToPublish($db, $eventId);

// Organize images
$heroImage = null;
$galleryImages = [];
foreach ($images as $image) {
    if ($image['image_role'] === 'hero') $heroImage = $image;
    else $galleryImages[] = $image;
}

// Handle POST
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
            saveInvitationConfig($db, $eventId, array_merge((array)$invitationConfig, ['layout_style' => $layout]));
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
if ($mode !== 'showcase' && $hasLayout) $mode = 'editor';
else if (!$hasLayout) $mode = 'showcase';

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

// Data arrays
$layouts = [
    'split' => ['name' => 'Delt', 'desc' => 'Elegant to-kolonne med sticky billede'],
    'centered' => ['name' => 'Centreret', 'desc' => 'Billede over centreret indhold'],
    'fullscreen' => ['name' => 'Fullscreen', 'desc' => 'Dramatisk hero med overlay-tekst'],
    'minimal' => ['name' => 'Minimal', 'desc' => 'Ren og enkel med cirkulært billede'],
    'classic' => ['name' => 'Klassisk', 'desc' => 'Traditionelt kort med elegant ramme'],
    'slideshow' => ['name' => 'Slideshow', 'desc' => 'Filmisk galleri med billede-karrusel']
];

$colorPresets = [
    ['name' => 'Nordisk', 'colors' => ['color_primary'=>'#1A1A1A','color_secondary'=>'#8FA583','color_accent'=>'#B8923D','color_text'=>'#1A1A1A','color_background'=>'#FAF9F7']],
    ['name' => 'Romantisk', 'colors' => ['color_primary'=>'#2D1B1B','color_secondary'=>'#D4A5A5','color_accent'=>'#B88A8A','color_text'=>'#2D1B1B','color_background'=>'#FFF8F6']],
    ['name' => 'Moderne', 'colors' => ['color_primary'=>'#111827','color_secondary'=>'#3B82F6','color_accent'=>'#8B5CF6','color_text'=>'#1F2937','color_background'=>'#F9FAFB']],
    ['name' => 'Varm', 'colors' => ['color_primary'=>'#292524','color_secondary'=>'#D97706','color_accent'=>'#B45309','color_text'=>'#292524','color_background'=>'#FFFBEB']],
    ['name' => 'Mørk', 'colors' => ['color_primary'=>'#F9FAFB','color_secondary'=>'#8FA583','color_accent'=>'#D4AF37','color_text'=>'#E5E7EB','color_background'=>'#1A1A1A']]
];

$fontStyles = [
    'elegant' => ['name'=>'Elegant','font'=>"'Cormorant Garamond', serif",'sample'=>'Kære Anna'],
    'modern' => ['name'=>'Moderne','font'=>"'Inter', sans-serif",'sample'=>'Kære Anna'],
    'playful' => ['name'=>'Legende','font'=>"'Quicksand', sans-serif",'sample'=>'Kære Anna'],
    'traditional' => ['name'=>'Traditionel','font'=>"'Playfair Display', serif",'sample'=>'Kære Anna'],
    'minimal' => ['name'=>'Minimalistisk','font'=>"'DM Sans', sans-serif",'sample'=>'Kære Anna']
];

$sections = [
    'countdown' => ['name'=>'Nedtælling','desc'=>'Vis nedtælling til arrangementet','field'=>'show_countdown'],
    'rsvp' => ['name'=>'RSVP','desc'=>'Vis svar-sektion','field'=>'show_rsvp'],
    'schedule' => ['name'=>'Program','desc'=>'Vis link til programmet','field'=>'show_schedule'],
    'map' => ['name'=>'Kort','desc'=>'Vis kort over lokationen','field'=>'show_map']
];

$currentLayout = $invitationConfig['layout_style'] ?? 'split';
$currentFont = $invitationConfig['font_style'] ?? 'elegant';
$isPublished = !empty($invitationConfig['is_published']);
$publicUrl = '/e/' . htmlspecialchars($event['public_slug'] ?? $eventId);
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=DM+Sans:wght@400;500;600&family=Playfair+Display:wght@400;500;600&family=Inter:wght@400;500;600&family=Quicksand:wght@400;500;600&family=Nunito:wght@400;500;600&family=Lora:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/invitation-editor.css">

<?php if ($mode === 'showcase'): ?>

<!-- ============ SHOWCASE MODE ============ -->
<div class="showcase-fullscreen" id="layout-showcase">
    <div class="showcase-header">
        <h2>Vælg dit layout</h2>
        <p>Hover over kortene for at se layoutet i aktion</p>
    </div>

    <form method="post" action="/app/events/manage.php?id=<?= $eventId ?>&page=invitation">
        <?= accountCsrfField() ?>
        <input type="hidden" name="action" value="select-layout">
        <input type="hidden" name="layout_style" id="selected-layout" value="">

        <div class="showcase-grid">
            <?php foreach ($layouts as $key => $layout): ?>
            <div class="showcase-card" data-layout="<?= $key ?>">
                <div class="showcase-preview">
                    <?php include __DIR__ . '/invitation-showcase-mocks/' . $key . '.php'; ?>
                </div>
                <div class="showcase-info">
                    <h3><?= htmlspecialchars($layout['name']) ?></h3>
                    <p><?= htmlspecialchars($layout['desc']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="showcase-continue">
            <button type="submit" class="btn btn-primary btn-lg">Fortsæt med valgt layout</button>
        </div>
    </form>
</div>

<?php else: ?>

<!-- ============ EDITOR MODE ============ -->
<div class="inv-workspace" id="inv-workspace" data-config="<?= $configJson ?>" data-event-id="<?= $eventId ?>">

    <!-- Sidebar -->
    <?php
    // Calculate step completion
    $stepsComplete = [
        'images' => !empty($heroImage),
        'text' => !empty($invitationConfig['invitation_message']),
        'design' => true, // always has defaults
        'sections' => true, // always has defaults
        'publish' => !empty($invitationConfig['is_published'])
    ];
    $completedCount = count(array_filter($stepsComplete));
    $totalSteps = count($stepsComplete);
    // Find first incomplete step to auto-open
    $activePanel = 'images';
    foreach ($stepsComplete as $panel => $done) {
        if (!$done) { $activePanel = $panel; break; }
    }
    ?>
    <div class="inv-sidebar" id="inv-sidebar">
        <button class="sidebar-collapse-btn" id="sidebar-collapse" title="Skjul sidebar">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
        </button>

        <div class="steps-progress">
            <div class="steps-progress-bar">
                <div class="steps-progress-fill" id="steps-progress-fill" style="width: <?= round(($completedCount / $totalSteps) * 100) ?>%"></div>
            </div>
            <span class="steps-progress-label" id="steps-progress-label"><?= $completedCount ?>/<?= $totalSteps ?> trin fuldført</span>
        </div>

        <div class="sidebar-tabs">
            <button class="sidebar-tab<?= $activePanel === 'images' ? ' active' : '' ?><?= $stepsComplete['images'] ? ' completed' : '' ?>" data-panel="images" data-step="1" title="Billeder">
                <span class="step-number">1</span>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                <span class="step-label">Billeder</span>
            </button>
            <button class="sidebar-tab<?= $activePanel === 'text' ? ' active' : '' ?><?= $stepsComplete['text'] ? ' completed' : '' ?>" data-panel="text" data-step="2" title="Tekst">
                <span class="step-number">2</span>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                <span class="step-label">Tekst</span>
            </button>
            <button class="sidebar-tab<?= $activePanel === 'design' ? ' active' : '' ?><?= $stepsComplete['design'] ? ' completed' : '' ?>" data-panel="design" data-step="3" title="Design">
                <span class="step-number">3</span>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="13.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="10.5" r="2.5"/><circle cx="8.5" cy="7.5" r="2.5"/><circle cx="6.5" cy="12.5" r="2.5"/><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12a10 10 0 0 0 5.012 8.662"/></svg>
                <span class="step-label">Design</span>
            </button>
            <button class="sidebar-tab<?= $activePanel === 'sections' ? ' active' : '' ?><?= $stepsComplete['sections'] ? ' completed' : '' ?>" data-panel="sections" data-step="4" title="Sektioner">
                <span class="step-number">4</span>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                <span class="step-label">Sektioner</span>
            </button>
            <button class="sidebar-tab<?= $activePanel === 'publish' ? ' active' : '' ?><?= $stepsComplete['publish'] ? ' completed' : '' ?>" data-panel="publish" data-step="5" title="Publicer">
                <span class="step-number">5</span>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>
                <span class="step-label">Publicer</span>
            </button>
        </div>

        <div class="sidebar-panels">

            <!-- Panel: Images -->
            <div class="sidebar-panel<?= $activePanel === 'images' ? ' active' : '' ?>" id="panel-images">
                <h3 class="panel-title">Billeder</h3>

                <div class="sidebar-section">
                    <label class="sidebar-label">Hero-billede</label>
                    <div class="hero-upload-zone" id="hero-dropzone">
                        <?php if ($heroImage): ?>
                        <div class="hero-preview">
                            <img src="/uploads/invitations/<?= htmlspecialchars($heroImage['filename']) ?>" alt="Hero">
                            <button type="button" class="hero-remove-btn" onclick="deleteImage(<?= (int)$heroImage['id'] ?>)" title="Fjern hero-billede">&times;</button>
                        </div>
                        <?php else: ?>
                        <div class="upload-placeholder" id="hero-placeholder">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            <span>Upload hero-billede</span>
                        </div>
                        <?php endif; ?>
                        <input type="file" id="hero-upload" accept="image/*" class="sr-only">
                    </div>
                </div>

                <div class="sidebar-section">
                    <label class="sidebar-label">Galleri</label>
                    <div class="gallery-grid">
                        <?php foreach ($galleryImages as $img): ?>
                        <div class="gallery-thumb" data-id="<?= (int)$img['id'] ?>">
                            <img src="/uploads/invitations/<?= htmlspecialchars($img['filename']) ?>" alt="Galleri">
                            <button type="button" class="gallery-remove-btn" onclick="deleteImage(<?= (int)$img['id'] ?>)" title="Fjern billede">&times;</button>
                        </div>
                        <?php endforeach; ?>
                        <div class="gallery-upload-slot">
                            <label class="gallery-add-btn" for="gallery-upload">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            </label>
                            <input type="file" id="gallery-upload" accept="image/*" multiple class="sr-only">
                        </div>
                    </div>
                </div>

                <button type="button" class="step-next-btn" data-next-panel="text">
                    Næste: Tekst
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
                </button>
            </div>

            <!-- Panel: Text -->
            <div class="sidebar-panel<?= $activePanel === 'text' ? ' active' : '' ?>" id="panel-text">
                <h3 class="panel-title">Tekst</h3>
                <p class="panel-desc">Skriv indholdet til din invitation. Klik på teksten i forhåndsvisningen for at redigere direkte.</p>

                <div class="field-card">
                    <label class="field-card-label" for="field-greeting">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        Hilsen
                    </label>
                    <input type="text" id="field-greeting" class="sidebar-input" data-field="greeting_template" placeholder="Kære {guest_name}" value="<?= htmlspecialchars($invitationConfig['greeting_template'] ?? 'Kære {guest_name}') ?>">
                    <span class="form-hint"><strong>{guest_name}</strong> erstattes med gæstens navn</span>
                </div>

                <div class="field-card">
                    <label class="field-card-label" for="field-headline">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7V4h16v3"/><path d="M9 20h6"/><path d="M12 4v16"/></svg>
                        Overskrift
                    </label>
                    <input type="text" id="field-headline" class="sidebar-input" data-field="headline_text" placeholder="F.eks. Du er inviteret til..." value="<?= htmlspecialchars($invitationConfig['headline_text'] ?? '') ?>">
                </div>

                <div class="field-card field-card--large">
                    <label class="field-card-label" for="field-message">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        Besked
                    </label>
                    <textarea id="field-message" class="sidebar-textarea" data-field="invitation_message" rows="6" placeholder="Skriv din invitationsbesked her..."><?= htmlspecialchars($invitationConfig['invitation_message'] ?? '') ?></textarea>
                </div>

                <div class="field-card">
                    <label class="field-card-label" for="field-closing">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20l9-5-9-5-9 5 9 5z"/><path d="M12 12l9-5-9-5-9 5 9 5z"/></svg>
                        Afslutning
                    </label>
                    <input type="text" id="field-closing" class="sidebar-input" data-field="closing_text" placeholder="F.eks. Vi glæder os til at se dig!" value="<?= htmlspecialchars($invitationConfig['closing_text'] ?? '') ?>">
                </div>

                <button type="button" class="step-next-btn" data-next-panel="design">
                    Næste: Design
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
                </button>
            </div>

            <!-- Panel: Design -->
            <div class="sidebar-panel<?= $activePanel === 'design' ? ' active' : '' ?>" id="panel-design">
                <h3 class="panel-title">Design</h3>

                <div class="sidebar-section">
                    <label class="sidebar-label">Layout</label>
                    <!-- Hidden select for JS compatibility -->
                    <select id="layout-change" class="sidebar-select sr-only" data-field="layout_style">
                        <?php foreach ($layouts as $key => $layout): ?>
                        <option value="<?= $key ?>" <?= $currentLayout === $key ? 'selected' : '' ?>><?= htmlspecialchars($layout['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="layout-picker-grid">
                        <?php foreach ($layouts as $key => $layout): ?>
                        <button type="button" class="layout-picker-card<?= $currentLayout === $key ? ' selected' : '' ?>" data-layout-value="<?= $key ?>" title="<?= htmlspecialchars($layout['name']) ?>">
                            <div class="layout-picker-preview">
                                <?php include __DIR__ . '/invitation-showcase-mocks/' . $key . '.php'; ?>
                            </div>
                            <span class="layout-picker-name"><?= htmlspecialchars($layout['name']) ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <a href="/app/events/manage.php?id=<?= $eventId ?>&page=invitation&mode=showcase" class="sidebar-link">Se alle layouts i fuld størrelse</a>
                </div>

                <div class="sidebar-section">
                    <label class="sidebar-label">Skrifttype</label>
                    <div class="font-options">
                        <?php foreach ($fontStyles as $key => $font): ?>
                        <label class="font-option <?= $currentFont === $key ? 'selected' : '' ?>">
                            <input type="radio" name="font_style" value="<?= $key ?>" data-field="font_style" <?= $currentFont === $key ? 'checked' : '' ?> class="sr-only">
                            <span class="font-sample" style="font-family: <?= htmlspecialchars($font['font']) ?>"><?= htmlspecialchars($font['sample']) ?></span>
                            <span class="font-name"><?= htmlspecialchars($font['name']) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="sidebar-section">
                    <label class="sidebar-label">Farvepaletter</label>
                    <div class="color-presets-row">
                        <?php foreach ($colorPresets as $preset): ?>
                        <button type="button" class="color-preset-btn" title="<?= htmlspecialchars($preset['name']) ?>" data-colors="<?= htmlspecialchars(json_encode($preset['colors'])) ?>">
                            <span class="preset-swatch" style="background: <?= htmlspecialchars($preset['colors']['color_secondary']) ?>"></span>
                            <span class="preset-swatch" style="background: <?= htmlspecialchars($preset['colors']['color_accent']) ?>"></span>
                            <span class="preset-name"><?= htmlspecialchars($preset['name']) ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="sidebar-section">
                    <label class="sidebar-label">Farver</label>
                    <div class="color-picker-row">
                        <label class="color-label">Primær</label>
                        <input type="color" class="color-input" data-field="color_primary" value="<?= htmlspecialchars($invitationConfig['color_primary'] ?? '#1A1A1A') ?>">
                    </div>
                    <div class="color-picker-row">
                        <label class="color-label">Sekundær</label>
                        <input type="color" class="color-input" data-field="color_secondary" value="<?= htmlspecialchars($invitationConfig['color_secondary'] ?? '#8FA583') ?>">
                    </div>
                    <div class="color-picker-row">
                        <label class="color-label">Accent</label>
                        <input type="color" class="color-input" data-field="color_accent" value="<?= htmlspecialchars($invitationConfig['color_accent'] ?? '#B8923D') ?>">
                    </div>
                    <div class="color-picker-row">
                        <label class="color-label">Tekst</label>
                        <input type="color" class="color-input" data-field="color_text" value="<?= htmlspecialchars($invitationConfig['color_text'] ?? '#1A1A1A') ?>">
                    </div>
                    <div class="color-picker-row">
                        <label class="color-label">Baggrund</label>
                        <input type="color" class="color-input" data-field="color_background" value="<?= htmlspecialchars($invitationConfig['color_background'] ?? '#FAF9F7') ?>">
                    </div>
                </div>

                <button type="button" class="step-next-btn" data-next-panel="sections">
                    Næste: Sektioner
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
                </button>
            </div>

            <!-- Panel: Sections -->
            <div class="sidebar-panel<?= $activePanel === 'sections' ? ' active' : '' ?>" id="panel-sections">
                <h3 class="panel-title">Sektioner</h3>

                <div class="sidebar-section">
                    <p class="sidebar-hint">Træk for at ændre rækkefølge. Slå sektioner til/fra.</p>
                    <div class="section-list" id="section-list">
                        <?php foreach ($sections as $key => $section): ?>
                        <div class="section-item" data-section="<?= $key ?>" draggable="true">
                            <span class="drag-handle">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="5" r="1"/><circle cx="15" cy="5" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="9" cy="19" r="1"/><circle cx="15" cy="19" r="1"/></svg>
                            </span>
                            <div class="section-info">
                                <span class="section-name"><?= htmlspecialchars($section['name']) ?></span>
                                <span class="section-desc"><?= htmlspecialchars($section['desc']) ?></span>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" data-field="<?= htmlspecialchars($section['field']) ?>" <?= !empty($invitationConfig[$section['field']]) ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="button" class="step-next-btn" data-next-panel="publish">
                    Næste: Publicer
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
                </button>
            </div>

            <!-- Panel: Publish -->
            <div class="sidebar-panel<?= $activePanel === 'publish' ? ' active' : '' ?>" id="panel-publish">
                <h3 class="panel-title">Publicer</h3>

                <div class="sidebar-section">
                    <label class="sidebar-label">Tjekliste</label>
                    <ul class="publish-checklist">
                        <li class="checklist-item <?= $heroImage ? 'checked' : '' ?>">
                            <span class="checklist-icon"><?= $heroImage ? '&#10003;' : '&#9675;' ?></span>
                            Hero-billede uploadet
                        </li>
                        <li class="checklist-item <?= !empty($invitationConfig['invitation_message']) ? 'checked' : '' ?>">
                            <span class="checklist-icon"><?= !empty($invitationConfig['invitation_message']) ? '&#10003;' : '&#9675;' ?></span>
                            Invitationsbesked skrevet
                        </li>
                    </ul>
                </div>

                <div class="sidebar-section">
                    <form method="post" action="/app/events/manage.php?id=<?= $eventId ?>&page=invitation">
                        <?= accountCsrfField() ?>
                        <input type="hidden" name="action" value="publish">
                        <?php if ($isPublished): ?>
                        <input type="hidden" name="publish" value="0">
                        <button type="submit" class="btn btn-secondary btn-block">Skjul invitation</button>
                        <?php else: ?>
                        <input type="hidden" name="publish" value="1">
                        <button type="submit" class="btn btn-primary btn-block">Offentliggør invitation</button>
                        <?php endif; ?>
                    </form>
                </div>

                <?php if ($isPublished): ?>
                <div class="sidebar-section">
                    <label class="sidebar-label">Del link</label>
                    <div class="share-link-row">
                        <input type="text" class="sidebar-input share-url" id="public-url" value="<?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'partyparart.dk') . $publicUrl) ?>" readonly>
                        <button type="button" class="btn btn-sm btn-outline" onclick="var btn=this;var url=document.getElementById('public-url');var t=document.createElement('textarea');t.value=url.value;document.body.appendChild(t);t.select();document.execCommand('copy');document.body.removeChild(t);btn.textContent='Kopieret!';setTimeout(function(){btn.textContent='Kopier';},2000);">Kopier</button>
                    </div>
                </div>

                <div class="sidebar-section">
                    <a href="/app/events/manage.php?id=<?= $eventId ?>&page=guests" class="btn btn-primary btn-block">Send invitationer</a>
                </div>
                <?php endif; ?>
            </div>

        </div><!-- /.sidebar-panels -->

        <div class="save-status" id="save-status">
            <span class="save-status-text">Gemt</span>
        </div>
    </div><!-- /.inv-sidebar -->

    <!-- Preview Panel -->
    <div class="inv-preview-panel" id="inv-preview-panel">
        <div class="floating-toolbar" id="floating-toolbar">
            <button type="button" class="toolbar-btn" data-action="bold" title="Fed"><strong>B</strong></button>
            <button type="button" class="toolbar-btn" data-action="italic" title="Kursiv"><em>I</em></button>
            <input type="color" class="toolbar-color-input" id="toolbar-color" title="Tekstfarve">
        </div>

        <div class="inv-preview" id="inv-preview">
            <p class="preview-loading">Indlæser forhåndsvisning...</p>
        </div>
    </div><!-- /.inv-preview-panel -->

</div><!-- /.inv-workspace -->

<script>
// Image upload: Hero
(function() {
    var heroDropzone = document.getElementById('hero-dropzone');
    var heroInput = document.getElementById('hero-upload');
    if (!heroDropzone || !heroInput) return;

    heroDropzone.addEventListener('click', function(e) {
        if (e.target.closest('.hero-remove-btn')) return;
        heroInput.click();
    });

    heroDropzone.addEventListener('dragover', function(e) {
        e.preventDefault();
        heroDropzone.classList.add('dragover');
    });
    heroDropzone.addEventListener('dragleave', function() {
        heroDropzone.classList.remove('dragover');
    });
    heroDropzone.addEventListener('drop', function(e) {
        e.preventDefault();
        heroDropzone.classList.remove('dragover');
        if (e.dataTransfer.files.length) {
            uploadImage(e.dataTransfer.files[0], 'hero');
        }
    });

    heroInput.addEventListener('change', function() {
        if (this.files.length) uploadImage(this.files[0], 'hero');
    });
})();

// Image upload: Gallery
(function() {
    var galleryInput = document.getElementById('gallery-upload');
    if (!galleryInput) return;

    galleryInput.addEventListener('change', function() {
        for (var i = 0; i < this.files.length; i++) {
            uploadImage(this.files[i], 'gallery');
        }
    });
})();

function uploadImage(file, role) {
    var wsEventId = document.getElementById('inv-workspace').getAttribute('data-event-id');
    var formData = new FormData();
    formData.append('image', file);
    formData.append('action', 'upload');
    formData.append('role', role);
    formData.append('event_id', wsEventId);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/api/invitation-images.php', true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState !== 4) return;
        try {
            var data = JSON.parse(xhr.responseText);
            if (data.success) {
                // Force save before reload to preserve unsaved changes
                if (window.forceAutoSave) {
                    window.forceAutoSave(function() { location.reload(); });
                } else {
                    location.reload();
                }
            } else {
                alert(data.error || 'Upload fejlede');
            }
        } catch (e) {
            alert('Upload fejlede');
        }
    };
    xhr.onerror = function() { alert('Upload fejlede'); };
    xhr.send(formData);
}

function deleteImage(imageId) {
    if (!confirm('Fjern dette billede?')) return;
    var wsEventId = document.getElementById('inv-workspace').getAttribute('data-event-id');

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/api/invitation-images.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState !== 4) return;
        try {
            var data = JSON.parse(xhr.responseText);
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || 'Kunne ikke fjerne billede');
            }
        } catch (e) {
            alert('Kunne ikke fjerne billede');
        }
    };
    xhr.onerror = function() { alert('Kunne ikke fjerne billede'); };
    xhr.send('event_id=' + encodeURIComponent(wsEventId) + '&action=delete&image_id=' + encodeURIComponent(imageId));
}

// Load preview on ready
document.addEventListener('DOMContentLoaded', function() {
    var workspace = document.getElementById('inv-workspace');
    if (!workspace) return;

    var wsConfig = JSON.parse(workspace.getAttribute('data-config'));
    var wsEventId = workspace.getAttribute('data-event-id');

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/api/invitation-preview.php?format=partial&event_id=' + encodeURIComponent(wsEventId), true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.onreadystatechange = function() {
        if (xhr.readyState !== 4) return;
        if (xhr.status >= 200 && xhr.status < 300) {
            document.getElementById('inv-preview').innerHTML = xhr.responseText;
            if (window.reinitPreviewEditor) window.reinitPreviewEditor();
        } else {
            document.getElementById('inv-preview').innerHTML = '<p class="preview-error">Kunne ikke indlæse forhåndsvisning</p>';
        }
    };
    xhr.onerror = function() {
        document.getElementById('inv-preview').innerHTML = '<p class="preview-error">Kunne ikke indlæse forhåndsvisning</p>';
    };
    xhr.send(JSON.stringify(wsConfig));
});
</script>
<?php endif; ?>

<script src="/assets/js/invitation-editor.js"></script>

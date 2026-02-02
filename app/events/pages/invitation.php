<?php
/**
 * Invitation Konfigurator
 * 6-step wizard for configuring event invitations
 */

require_once __DIR__ . '/../../../includes/invitation-functions.php';

// Get invitation config
$invitationConfig = getInvitationConfig($db, $eventId);
$templates = getInvitationTemplates($db, $event['event_type_id']);
$images = getInvitationImages($db, $eventId);

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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'apply-template') {
        $templateId = (int)($_POST['template_id'] ?? 0);
        if ($templateId && applyTemplateToConfig($db, $eventId, $templateId)) {
            setFlash('success', 'Skabelon anvendt!');
        } else {
            setFlash('error', 'Kunne ikke anvende skabelon.');
        }
        redirect("/app/events/manage.php?id=$eventId&page=invitation");
    }

    if ($action === 'save-config') {
        $data = [
            'layout_style' => $_POST['layout_style'] ?? 'split',
            'font_style' => $_POST['font_style'] ?? 'elegant',
            'color_primary' => $_POST['color_primary'] ?? '#1A1A1A',
            'color_secondary' => $_POST['color_secondary'] ?? '#8FA583',
            'color_accent' => $_POST['color_accent'] ?? '#B8923D',
            'color_text' => $_POST['color_text'] ?? '#1A1A1A',
            'color_background' => $_POST['color_background'] ?? '#FAF9F7',
            'greeting_template' => $_POST['greeting_template'] ?? 'Kære {guest_name}',
            'headline_text' => $_POST['headline_text'] ?? '',
            'invitation_message' => $_POST['invitation_message'] ?? '',
            'closing_text' => $_POST['closing_text'] ?? '',
            'show_countdown' => isset($_POST['show_countdown']) ? 1 : 0,
            'show_map' => isset($_POST['show_map']) ? 1 : 0,
            'show_schedule' => isset($_POST['show_schedule']) ? 1 : 0,
            'show_rsvp' => isset($_POST['show_rsvp']) ? 1 : 0,
        ];

        if (saveInvitationConfig($db, $eventId, $data)) {
            setFlash('success', 'Invitation gemt!');
        } else {
            setFlash('error', 'Kunne ikke gemme invitation.');
        }
        redirect("/app/events/manage.php?id=$eventId&page=invitation");
    }

    if ($action === 'publish') {
        $publish = isset($_POST['publish']);
        if (setInvitationPublished($db, $eventId, $publish)) {
            setFlash('success', $publish ? 'Invitation offentliggjort!' : 'Invitation skjult.');
        }
        redirect("/app/events/manage.php?id=$eventId&page=invitation");
    }
}

// Current step (from URL or default)
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$step = max(1, min(6, $step));

// Readiness check
$readiness = isInvitationReadyToPublish($db, $eventId);
?>

<style>
    /* Wizard Steps */
    .wizard-steps {
        display: flex;
        justify-content: space-between;
        margin-bottom: 32px;
        padding: 0 20px;
    }

    .wizard-step {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        flex: 1;
        position: relative;
    }

    .wizard-step:not(:last-child)::after {
        content: '';
        position: absolute;
        top: 18px;
        left: 50%;
        width: 100%;
        height: 2px;
        background: var(--cream-dark);
    }

    .wizard-step.completed:not(:last-child)::after {
        background: var(--sage);
    }

    .step-number {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--cream-dark);
        color: var(--charcoal-light);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        font-weight: 600;
        position: relative;
        z-index: 1;
        transition: all 0.25s var(--ease-out);
    }

    .wizard-step.active .step-number {
        background: var(--sage);
        color: var(--white);
        transform: scale(1.1);
    }

    .wizard-step.completed .step-number {
        background: var(--sage);
        color: var(--white);
    }

    .step-label {
        font-size: 12px;
        color: var(--charcoal-light);
        text-align: center;
    }

    .wizard-step.active .step-label {
        color: var(--charcoal);
        font-weight: 600;
    }

    /* Step Content */
    .step-content {
        display: none;
    }

    .step-content.active {
        display: block;
        animation: fadeIn 0.4s var(--ease-out);
    }

    /* Template Grid */
    .template-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 20px;
    }

    .template-card {
        background: var(--white);
        border: 2px solid var(--cream-dark);
        border-radius: 16px;
        overflow: hidden;
        cursor: pointer;
        transition: all 0.25s var(--ease-out);
    }

    .template-card:hover {
        border-color: var(--sage-light);
        transform: translateY(-2px);
    }

    .template-card.selected {
        border-color: var(--sage);
        box-shadow: 0 0 0 3px rgba(143, 165, 131, 0.2);
    }

    .template-card.recommended {
        position: relative;
    }

    .template-card.recommended::before {
        content: 'Anbefalet';
        position: absolute;
        top: 12px;
        right: 12px;
        background: var(--gold);
        color: var(--white);
        font-size: 10px;
        font-weight: 600;
        padding: 4px 8px;
        border-radius: 6px;
        z-index: 1;
    }

    .template-preview {
        aspect-ratio: 4/3;
        background: linear-gradient(135deg, var(--sage-light) 0%, var(--sage) 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--white);
        font-family: var(--font-display);
        font-size: 24px;
    }

    .template-info {
        padding: 16px;
    }

    .template-name {
        font-weight: 600;
        margin-bottom: 4px;
    }

    .template-meta {
        font-size: 12px;
        color: var(--charcoal-light);
    }

    /* Image Upload */
    .upload-zone {
        border: 2px dashed var(--cream-dark);
        border-radius: 16px;
        padding: 40px;
        text-align: center;
        cursor: pointer;
        transition: all 0.25s var(--ease-out);
        background: var(--cream);
    }

    .upload-zone:hover {
        border-color: var(--sage);
        background: var(--white);
    }

    .upload-zone.dragover {
        border-color: var(--sage);
        background: rgba(143, 165, 131, 0.1);
    }

    .upload-zone svg {
        width: 48px;
        height: 48px;
        color: var(--sage);
        margin-bottom: 16px;
    }

    .upload-zone h4 {
        font-size: 16px;
        margin-bottom: 8px;
    }

    .upload-zone p {
        font-size: 13px;
        color: var(--charcoal-light);
    }

    /* Image Grid */
    .image-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 16px;
        margin-top: 24px;
    }

    .image-item {
        position: relative;
        aspect-ratio: 1;
        border-radius: 12px;
        overflow: hidden;
        background-size: cover;
        background-position: center;
        cursor: grab;
    }

    .image-item:active {
        cursor: grabbing;
    }

    .image-item.hero {
        grid-column: span 2;
        grid-row: span 2;
    }

    .image-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(to top, rgba(0,0,0,0.6) 0%, transparent 50%);
        opacity: 0;
        transition: opacity 0.25s;
        display: flex;
        align-items: flex-end;
        padding: 12px;
    }

    .image-item:hover .image-overlay {
        opacity: 1;
    }

    .image-actions {
        display: flex;
        gap: 8px;
    }

    .image-action {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: var(--white);
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }

    .image-action:hover {
        background: var(--cream);
    }

    .image-action.danger:hover {
        background: #FDF2F2;
        color: var(--error);
    }

    .image-action svg {
        width: 16px;
        height: 16px;
    }

    .hero-badge {
        position: absolute;
        top: 12px;
        left: 12px;
        background: var(--gold);
        color: var(--white);
        font-size: 10px;
        font-weight: 600;
        padding: 4px 8px;
        border-radius: 6px;
    }

    /* Color Pickers */
    .color-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 20px;
    }

    .color-picker-group {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        background: var(--cream);
        border-radius: 12px;
    }

    .color-picker-group input[type="color"] {
        width: 48px;
        height: 48px;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        padding: 0;
    }

    .color-picker-group label {
        font-size: 14px;
        font-weight: 500;
    }

    /* Layout Options */
    .layout-options {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 16px;
    }

    .layout-option {
        padding: 20px;
        border: 2px solid var(--cream-dark);
        border-radius: 14px;
        text-align: center;
        cursor: pointer;
        transition: all 0.25s var(--ease-out);
    }

    .layout-option:hover {
        border-color: var(--sage-light);
    }

    .layout-option.selected {
        border-color: var(--sage);
        background: rgba(143, 165, 131, 0.08);
    }

    .layout-option input {
        display: none;
    }

    .layout-icon {
        width: 48px;
        height: 48px;
        margin: 0 auto 12px;
        background: var(--cream);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--sage-dark);
    }

    .layout-option.selected .layout-icon {
        background: var(--sage);
        color: var(--white);
    }

    .layout-option span {
        font-size: 13px;
        font-weight: 500;
    }

    /* Toggle Switches */
    .toggle-group {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .toggle-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px;
        background: var(--cream);
        border-radius: 12px;
    }

    .toggle-label h4 {
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 2px;
    }

    .toggle-label p {
        font-size: 13px;
        color: var(--charcoal-light);
    }

    .toggle-switch {
        position: relative;
        width: 48px;
        height: 28px;
    }

    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .toggle-slider {
        position: absolute;
        cursor: pointer;
        inset: 0;
        background: var(--cream-dark);
        border-radius: 28px;
        transition: 0.3s;
    }

    .toggle-slider::before {
        position: absolute;
        content: '';
        height: 22px;
        width: 22px;
        left: 3px;
        bottom: 3px;
        background: var(--white);
        border-radius: 50%;
        transition: 0.3s;
    }

    .toggle-switch input:checked + .toggle-slider {
        background: var(--sage);
    }

    .toggle-switch input:checked + .toggle-slider::before {
        transform: translateX(20px);
    }

    /* Preview Panel */
    .preview-panel {
        background: var(--charcoal);
        border-radius: 20px;
        overflow: hidden;
        height: 500px;
    }

    .preview-header {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 16px;
        background: rgba(255,255,255,0.05);
    }

    .preview-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: rgba(255,255,255,0.2);
    }

    .preview-dot.red { background: #FF5F57; }
    .preview-dot.yellow { background: #FEBC2E; }
    .preview-dot.green { background: #28C840; }

    .preview-frame {
        width: 100%;
        height: calc(100% - 40px);
        border: none;
        background: var(--cream);
    }

    /* Publish Section */
    .publish-card {
        background: linear-gradient(135deg, var(--sage) 0%, var(--sage-dark) 100%);
        color: var(--white);
        padding: 32px;
        border-radius: 20px;
        text-align: center;
    }

    .publish-card h3 {
        font-family: var(--font-display);
        font-size: 24px;
        margin-bottom: 12px;
    }

    .publish-card p {
        opacity: 0.9;
        margin-bottom: 24px;
    }

    .publish-btn {
        background: var(--white);
        color: var(--sage-dark);
        padding: 16px 40px;
        border: none;
        border-radius: 12px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.25s var(--ease-out);
    }

    .publish-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    }

    .publish-btn.unpublish {
        background: transparent;
        border: 2px solid var(--white);
        color: var(--white);
    }

    .issues-list {
        background: rgba(255,255,255,0.1);
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 24px;
        text-align: left;
    }

    .issues-list h4 {
        font-size: 14px;
        margin-bottom: 8px;
    }

    .issues-list ul {
        list-style: none;
        font-size: 13px;
    }

    .issues-list li {
        padding: 6px 0;
        padding-left: 20px;
        position: relative;
    }

    .issues-list li::before {
        content: '!';
        position: absolute;
        left: 0;
        width: 14px;
        height: 14px;
        background: var(--gold);
        border-radius: 50%;
        font-size: 10px;
        font-weight: bold;
        display: flex;
        align-items: center;
        justify-content: center;
        top: 8px;
    }

    /* Wizard Navigation */
    .wizard-nav {
        display: flex;
        justify-content: space-between;
        margin-top: 32px;
        padding-top: 24px;
        border-top: 1px solid var(--cream-dark);
    }

    @media (max-width: 768px) {
        .wizard-steps {
            display: none;
        }

        .template-grid {
            grid-template-columns: 1fr;
        }

        .color-grid {
            grid-template-columns: 1fr;
        }

        .image-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .image-item.hero {
            grid-column: span 2;
            grid-row: span 1;
            aspect-ratio: 16/9;
        }
    }
</style>

<div class="page-header-actions">
    <div>
        <h2 class="section-title">Invitation</h2>
        <p class="section-subtitle">Design og tilpas din invitation</p>
    </div>
    <?php if ($invitationConfig['is_published']): ?>
    <span class="event-badge" style="background: var(--success); color: var(--white);">Offentliggjort</span>
    <?php endif; ?>
</div>

<!-- Wizard Steps -->
<div class="wizard-steps">
    <?php
    $steps = [
        1 => 'Skabelon',
        2 => 'Billeder',
        3 => 'Tekst',
        4 => 'Stil',
        5 => 'Sektioner',
        6 => 'Publicer'
    ];
    foreach ($steps as $num => $label):
        $isActive = $step === $num;
        $isCompleted = $step > $num;
    ?>
    <a href="?id=<?= $eventId ?>&page=invitation&step=<?= $num ?>" class="wizard-step <?= $isActive ? 'active' : '' ?> <?= $isCompleted ? 'completed' : '' ?>">
        <div class="step-number"><?= $num ?></div>
        <span class="step-label"><?= $label ?></span>
    </a>
    <?php endforeach; ?>
</div>

<!-- Step 1: Choose Template -->
<div class="step-content <?= $step === 1 ? 'active' : '' ?>">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Vælg skabelon</h3>
        </div>

        <div class="template-grid">
            <?php foreach ($templates as $template): ?>
            <form method="POST" style="display: contents;">
                <input type="hidden" name="action" value="apply-template">
                <input type="hidden" name="template_id" value="<?= $template['id'] ?>">
                <div class="template-card <?= $invitationConfig['template_id'] == $template['id'] ? 'selected' : '' ?> <?= $template['is_recommended'] ? 'recommended' : '' ?>"
                     onclick="this.closest('form').submit()">
                    <div class="template-preview">
                        <?= substr($template['name'], 0, 1) ?>
                    </div>
                    <div class="template-info">
                        <div class="template-name"><?= htmlspecialchars($template['name']) ?></div>
                        <div class="template-meta"><?= ucfirst($template['layout_style']) ?> • <?= ucfirst($template['font_style']) ?></div>
                    </div>
                </div>
            </form>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="wizard-nav">
        <div></div>
        <a href="?id=<?= $eventId ?>&page=invitation&step=2" class="btn btn-primary">
            Næste: Billeder
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
            </svg>
        </a>
    </div>
</div>

<!-- Step 2: Upload Images -->
<div class="step-content <?= $step === 2 ? 'active' : '' ?>">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Billeder</h3>
        </div>

        <input type="file" id="image-upload" accept="image/*" multiple style="display: none;">

        <div class="upload-zone" id="upload-zone" onclick="document.getElementById('image-upload').click()">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            <h4>Træk billeder hertil</h4>
            <p>eller klik for at vælge filer • JPG, PNG, WebP • Max 10MB</p>
        </div>

        <div class="image-grid" id="image-grid">
            <?php if ($heroImage): ?>
            <div class="image-item hero" style="background-image: url(/uploads/invitations/<?= htmlspecialchars($heroImage['filename']) ?>)" data-id="<?= $heroImage['id'] ?>">
                <span class="hero-badge">Hovedbillede</span>
                <div class="image-overlay">
                    <div class="image-actions">
                        <button class="image-action danger" onclick="deleteImage(<?= $heroImage['id'] ?>)" title="Slet">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php foreach ($galleryImages as $image): ?>
            <div class="image-item" style="background-image: url(/uploads/invitations/<?= htmlspecialchars($image['filename']) ?>)" data-id="<?= $image['id'] ?>">
                <div class="image-overlay">
                    <div class="image-actions">
                        <button class="image-action" onclick="setAsHero(<?= $image['id'] ?>)" title="Sæt som hovedbillede">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                            </svg>
                        </button>
                        <button class="image-action danger" onclick="deleteImage(<?= $image['id'] ?>)" title="Slet">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="wizard-nav">
        <a href="?id=<?= $eventId ?>&page=invitation&step=1" class="btn btn-secondary">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Tilbage
        </a>
        <a href="?id=<?= $eventId ?>&page=invitation&step=3" class="btn btn-primary">
            Næste: Tekst
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
            </svg>
        </a>
    </div>
</div>

<!-- Step 3: Text Content -->
<div class="step-content <?= $step === 3 ? 'active' : '' ?>">
    <form method="POST" id="text-form">
        <input type="hidden" name="action" value="save-config">

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Tekst & indhold</h3>
            </div>

            <div class="form-group">
                <label class="form-label">Hilsen (brug {guest_name} for gæstens navn)</label>
                <input type="text" name="greeting_template" class="form-input"
                       value="<?= htmlspecialchars($invitationConfig['greeting_template'] ?? 'Kære {guest_name}') ?>"
                       placeholder="Kære {guest_name}">
                <p class="form-hint">Eksempel resultat: "Kære Mormor & Morfar"</p>
            </div>

            <div class="form-group">
                <label class="form-label">Overskrift</label>
                <input type="text" name="headline_text" class="form-input"
                       value="<?= htmlspecialchars($invitationConfig['headline_text'] ?? '') ?>"
                       placeholder="<?= htmlspecialchars($event['name']) ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Invitationsbesked</label>
                <textarea name="invitation_message" class="form-input" rows="6"
                          placeholder="Skriv din personlige invitation her..."><?= htmlspecialchars($invitationConfig['invitation_message'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Afslutning</label>
                <input type="text" name="closing_text" class="form-input"
                       value="<?= htmlspecialchars($invitationConfig['closing_text'] ?? '') ?>"
                       placeholder="Vi glæder os til at se jer!">
            </div>

            <!-- Hidden fields for other config values -->
            <input type="hidden" name="layout_style" value="<?= htmlspecialchars($invitationConfig['layout_style']) ?>">
            <input type="hidden" name="font_style" value="<?= htmlspecialchars($invitationConfig['font_style']) ?>">
            <input type="hidden" name="color_primary" value="<?= htmlspecialchars($invitationConfig['color_primary']) ?>">
            <input type="hidden" name="color_secondary" value="<?= htmlspecialchars($invitationConfig['color_secondary']) ?>">
            <input type="hidden" name="color_accent" value="<?= htmlspecialchars($invitationConfig['color_accent']) ?>">
            <input type="hidden" name="color_text" value="<?= htmlspecialchars($invitationConfig['color_text']) ?>">
            <input type="hidden" name="color_background" value="<?= htmlspecialchars($invitationConfig['color_background']) ?>">
            <?php if ($invitationConfig['show_countdown']): ?><input type="hidden" name="show_countdown" value="1"><?php endif; ?>
            <?php if ($invitationConfig['show_map']): ?><input type="hidden" name="show_map" value="1"><?php endif; ?>
            <?php if ($invitationConfig['show_schedule']): ?><input type="hidden" name="show_schedule" value="1"><?php endif; ?>
            <?php if ($invitationConfig['show_rsvp']): ?><input type="hidden" name="show_rsvp" value="1"><?php endif; ?>
        </div>

        <div class="wizard-nav">
            <a href="?id=<?= $eventId ?>&page=invitation&step=2" class="btn btn-secondary">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Tilbage
            </a>
            <button type="submit" class="btn btn-primary">
                Gem & Næste
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                </svg>
            </button>
        </div>
    </form>
</div>

<!-- Step 4: Style & Colors -->
<div class="step-content <?= $step === 4 ? 'active' : '' ?>">
    <form method="POST" id="style-form">
        <input type="hidden" name="action" value="save-config">

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Layout</h3>
            </div>

            <div class="layout-options">
                <?php
                $layouts = [
                    'split' => ['name' => 'Delt', 'icon' => 'M4 5a1 1 0 011-1h6v16H5a1 1 0 01-1-1V5zm10-1h5a1 1 0 011 1v14a1 1 0 01-1 1h-5V4z'],
                    'centered' => ['name' => 'Centreret', 'icon' => 'M4 6a2 2 0 012-2h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm3 2h10v3H7V8zm0 5h10v3H7v-3z'],
                    'fullscreen' => ['name' => 'Fullscreen', 'icon' => 'M4 3a1 1 0 00-1 1v16a1 1 0 001 1h16a1 1 0 001-1V4a1 1 0 00-1-1H4zm8 7a2 2 0 100-4 2 2 0 000 4zm0 2a4 4 0 110 8 4 4 0 010-8z'],
                    'minimal' => ['name' => 'Minimal', 'icon' => 'M4 6h16M4 10h16M4 14h10M4 18h6'],
                    'classic' => ['name' => 'Klassisk', 'icon' => 'M4 4h16v16H4V4zm2 2v12h12V6H6zm3 3h6v2H9V9zm0 4h6v2H9v-2z']
                ];
                foreach ($layouts as $key => $layout):
                ?>
                <label class="layout-option <?= $invitationConfig['layout_style'] === $key ? 'selected' : '' ?>">
                    <input type="radio" name="layout_style" value="<?= $key ?>" <?= $invitationConfig['layout_style'] === $key ? 'checked' : '' ?>>
                    <div class="layout-icon">
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $layout['icon'] ?>"></path>
                        </svg>
                    </div>
                    <span><?= $layout['name'] ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Skrifttype</h3>
            </div>

            <div class="layout-options">
                <?php
                $fonts = [
                    'elegant' => 'Elegant',
                    'modern' => 'Moderne',
                    'playful' => 'Legende',
                    'traditional' => 'Traditionel',
                    'minimal' => 'Minimalistisk'
                ];
                foreach ($fonts as $key => $name):
                ?>
                <label class="layout-option <?= $invitationConfig['font_style'] === $key ? 'selected' : '' ?>">
                    <input type="radio" name="font_style" value="<?= $key ?>" <?= $invitationConfig['font_style'] === $key ? 'checked' : '' ?>>
                    <span style="font-size: 16px;"><?= $name ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Farver</h3>
            </div>

            <div class="color-grid">
                <div class="color-picker-group">
                    <input type="color" name="color_primary" value="<?= htmlspecialchars($invitationConfig['color_primary']) ?>">
                    <label>Primær</label>
                </div>
                <div class="color-picker-group">
                    <input type="color" name="color_secondary" value="<?= htmlspecialchars($invitationConfig['color_secondary']) ?>">
                    <label>Sekundær</label>
                </div>
                <div class="color-picker-group">
                    <input type="color" name="color_accent" value="<?= htmlspecialchars($invitationConfig['color_accent']) ?>">
                    <label>Accent</label>
                </div>
                <div class="color-picker-group">
                    <input type="color" name="color_text" value="<?= htmlspecialchars($invitationConfig['color_text']) ?>">
                    <label>Tekst</label>
                </div>
                <div class="color-picker-group">
                    <input type="color" name="color_background" value="<?= htmlspecialchars($invitationConfig['color_background']) ?>">
                    <label>Baggrund</label>
                </div>
            </div>
        </div>

        <!-- Hidden fields for text content -->
        <input type="hidden" name="greeting_template" value="<?= htmlspecialchars($invitationConfig['greeting_template']) ?>">
        <input type="hidden" name="headline_text" value="<?= htmlspecialchars($invitationConfig['headline_text'] ?? '') ?>">
        <input type="hidden" name="invitation_message" value="<?= htmlspecialchars($invitationConfig['invitation_message'] ?? '') ?>">
        <input type="hidden" name="closing_text" value="<?= htmlspecialchars($invitationConfig['closing_text'] ?? '') ?>">
        <?php if ($invitationConfig['show_countdown']): ?><input type="hidden" name="show_countdown" value="1"><?php endif; ?>
        <?php if ($invitationConfig['show_map']): ?><input type="hidden" name="show_map" value="1"><?php endif; ?>
        <?php if ($invitationConfig['show_schedule']): ?><input type="hidden" name="show_schedule" value="1"><?php endif; ?>
        <?php if ($invitationConfig['show_rsvp']): ?><input type="hidden" name="show_rsvp" value="1"><?php endif; ?>

        <div class="wizard-nav">
            <a href="?id=<?= $eventId ?>&page=invitation&step=3" class="btn btn-secondary">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Tilbage
            </a>
            <button type="submit" class="btn btn-primary">
                Gem & Næste
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                </svg>
            </button>
        </div>
    </form>
</div>

<!-- Step 5: Sections -->
<div class="step-content <?= $step === 5 ? 'active' : '' ?>">
    <form method="POST" id="sections-form">
        <input type="hidden" name="action" value="save-config">

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Sektioner</h3>
            </div>

            <div class="toggle-group">
                <div class="toggle-item">
                    <div class="toggle-label">
                        <h4>Nedtælling</h4>
                        <p>Vis nedtælling til arrangementet</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="show_countdown" value="1" <?= $invitationConfig['show_countdown'] ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <div class="toggle-item">
                    <div class="toggle-label">
                        <h4>RSVP</h4>
                        <p>Vis svar-sektion på invitationen</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="show_rsvp" value="1" <?= $invitationConfig['show_rsvp'] ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <div class="toggle-item">
                    <div class="toggle-label">
                        <h4>Program</h4>
                        <p>Vis link til programmet</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="show_schedule" value="1" <?= $invitationConfig['show_schedule'] ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <div class="toggle-item">
                    <div class="toggle-label">
                        <h4>Kort</h4>
                        <p>Vis kort over lokationen</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="show_map" value="1" <?= $invitationConfig['show_map'] ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Hidden fields for other config values -->
        <input type="hidden" name="layout_style" value="<?= htmlspecialchars($invitationConfig['layout_style']) ?>">
        <input type="hidden" name="font_style" value="<?= htmlspecialchars($invitationConfig['font_style']) ?>">
        <input type="hidden" name="color_primary" value="<?= htmlspecialchars($invitationConfig['color_primary']) ?>">
        <input type="hidden" name="color_secondary" value="<?= htmlspecialchars($invitationConfig['color_secondary']) ?>">
        <input type="hidden" name="color_accent" value="<?= htmlspecialchars($invitationConfig['color_accent']) ?>">
        <input type="hidden" name="color_text" value="<?= htmlspecialchars($invitationConfig['color_text']) ?>">
        <input type="hidden" name="color_background" value="<?= htmlspecialchars($invitationConfig['color_background']) ?>">
        <input type="hidden" name="greeting_template" value="<?= htmlspecialchars($invitationConfig['greeting_template']) ?>">
        <input type="hidden" name="headline_text" value="<?= htmlspecialchars($invitationConfig['headline_text'] ?? '') ?>">
        <input type="hidden" name="invitation_message" value="<?= htmlspecialchars($invitationConfig['invitation_message'] ?? '') ?>">
        <input type="hidden" name="closing_text" value="<?= htmlspecialchars($invitationConfig['closing_text'] ?? '') ?>">

        <div class="wizard-nav">
            <a href="?id=<?= $eventId ?>&page=invitation&step=4" class="btn btn-secondary">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Tilbage
            </a>
            <button type="submit" class="btn btn-primary">
                Gem & Næste
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                </svg>
            </button>
        </div>
    </form>
</div>

<!-- Step 6: Preview & Publish -->
<div class="step-content <?= $step === 6 ? 'active' : '' ?>">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Preview</h3>
            <a href="/api/invitation-preview.php?event_id=<?= $eventId ?>" target="_blank" class="btn btn-secondary btn-sm">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                </svg>
                Åbn i nyt vindue
            </a>
        </div>

        <div class="preview-panel">
            <div class="preview-header">
                <span class="preview-dot red"></span>
                <span class="preview-dot yellow"></span>
                <span class="preview-dot green"></span>
            </div>
            <iframe src="/api/invitation-preview.php?event_id=<?= $eventId ?>" class="preview-frame"></iframe>
        </div>
    </div>

    <div class="publish-card">
        <?php if (!$readiness['ready']): ?>
        <div class="issues-list">
            <h4>Før du kan offentliggøre:</h4>
            <ul>
                <?php foreach ($readiness['issues'] as $issue): ?>
                <li><?= htmlspecialchars($issue) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <h3><?= $invitationConfig['is_published'] ? 'Invitation er offentliggjort' : 'Klar til at dele?' ?></h3>
        <p>
            <?php if ($invitationConfig['is_published']): ?>
            Din invitation er nu synlig for gæster. Du kan stadig redigere indholdet.
            <?php else: ?>
            Når du offentliggør, vil gæster se denne invitation når de logger ind.
            <?php endif; ?>
        </p>

        <form method="POST">
            <input type="hidden" name="action" value="publish">
            <?php if ($invitationConfig['is_published']): ?>
            <button type="submit" name="publish" value="0" class="publish-btn unpublish">Skjul invitation</button>
            <?php else: ?>
            <button type="submit" name="publish" value="1" class="publish-btn" <?= !$readiness['ready'] ? 'disabled style="opacity:0.5;cursor:not-allowed"' : '' ?>>
                Offentliggør invitation
            </button>
            <?php endif; ?>
        </form>
    </div>

    <div class="wizard-nav">
        <a href="?id=<?= $eventId ?>&page=invitation&step=5" class="btn btn-secondary">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Tilbage
        </a>
        <a href="?id=<?= $eventId ?>&page=invitation-send" class="btn btn-sage">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
            </svg>
            Send invitationer
        </a>
    </div>
</div>

<script>
const eventId = <?= $eventId ?>;

// Image upload handling
const uploadZone = document.getElementById('upload-zone');
const imageUpload = document.getElementById('image-upload');
const imageGrid = document.getElementById('image-grid');

if (uploadZone) {
    // Drag & drop
    ['dragenter', 'dragover'].forEach(eventName => {
        uploadZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });
    });

    ['dragleave', 'drop'].forEach(eventName => {
        uploadZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
        });
    });

    uploadZone.addEventListener('drop', (e) => {
        const files = e.dataTransfer.files;
        handleFiles(files);
    });

    // File input change
    imageUpload.addEventListener('change', () => {
        handleFiles(imageUpload.files);
    });
}

function handleFiles(files) {
    Array.from(files).forEach(file => {
        if (!file.type.startsWith('image/')) return;

        const formData = new FormData();
        formData.append('image', file);
        formData.append('event_id', eventId);
        formData.append('action', 'upload');
        formData.append('role', 'gallery');

        fetch('/api/invitation-images.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || 'Upload fejlede');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Upload fejlede');
        });
    });
}

function deleteImage(imageId) {
    if (!confirm('Er du sikker på at du vil slette dette billede?')) return;

    fetch('/api/invitation-images.php?event_id=' + eventId + '&action=delete&image_id=' + imageId)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Kunne ikke slette billede');
            }
        });
}

function setAsHero(imageId) {
    fetch('/api/invitation-images.php?event_id=' + eventId + '&action=set-role&image_id=' + imageId + '&role=hero')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Kunne ikke ændre billede');
            }
        });
}

// Radio button visual selection
document.querySelectorAll('.layout-option input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', () => {
        const group = radio.closest('.layout-options');
        group.querySelectorAll('.layout-option').forEach(opt => opt.classList.remove('selected'));
        radio.closest('.layout-option').classList.add('selected');
    });
});

// Step 3 redirect after save
document.getElementById('text-form')?.addEventListener('submit', (e) => {
    const form = e.target;
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'redirect_step';
    input.value = '4';
    form.appendChild(input);
});

// Step 4 redirect after save
document.getElementById('style-form')?.addEventListener('submit', (e) => {
    const form = e.target;
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'redirect_step';
    input.value = '5';
    form.appendChild(input);
});

// Step 5 redirect after save
document.getElementById('sections-form')?.addEventListener('submit', (e) => {
    const form = e.target;
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'redirect_step';
    input.value = '6';
    form.appendChild(input);
});
</script>

<?php
/**
 * QR Bordkort Generator Page
 * Generate printable A4 table cards with QR code matching invitation theme
 */

require_once __DIR__ . '/../../../includes/qr-functions.php';
require_once __DIR__ . '/../../../includes/invitation-functions.php';

// Get invitation config for theme colors
$invitationConfig = getInvitationConfig($db, $eventId);
$fonts = getBordkortFonts($invitationConfig['font_style'] ?? 'elegant');

// Get QR destinations
$qrDestinations = getQRDestinations();

// Default settings
$destination = $_GET['destination'] ?? 'photos';
$headline = $_GET['headline'] ?? '';
$subtitle = $_GET['subtitle'] ?? '';
$showEventName = isset($_GET['show_name']) ? $_GET['show_name'] === '1' : true;
$printMode = isset($_GET['print']) && $_GET['print'] === '1';

// Get default text if not customized
if (empty($headline) || empty($subtitle)) {
    $defaultText = getBordkortDefaultText($destination);
    if (empty($headline)) $headline = $defaultText['headline'];
    if (empty($subtitle)) $subtitle = $defaultText['subtitle'];
}

// Generate QR code URL
$destinationPage = $qrDestinations[$destination]['page'] ?? 'photos';
$eventUrl = getEventGuestUrl($event['slug'], $destinationPage);
$qrCodeUrl = generateQRCodeUrl($eventUrl, 400);

// Colors from invitation config
$colorPrimary = $invitationConfig['color_primary'] ?? '#1A1A1A';
$colorSecondary = $invitationConfig['color_secondary'] ?? '#8FA583';
$colorAccent = $invitationConfig['color_accent'] ?? '#B8923D';
$colorBackground = $invitationConfig['color_background'] ?? '#FAF9F7';
$colorText = $invitationConfig['color_text'] ?? '#1A1A1A';

// Format event date in Danish
$eventDateFormatted = null;
$eventDateParts = null;
if ($event['event_date']) {
    $months = ['', 'januar', 'februar', 'marts', 'april', 'maj', 'juni', 'juli', 'august', 'september', 'oktober', 'november', 'december'];
    $date = new DateTime($event['event_date']);
    $eventDateParts = [
        'day' => $date->format('j'),
        'month' => $months[(int)$date->format('n')],
        'year' => $date->format('Y')
    ];
    $eventDateFormatted = $eventDateParts['day'] . '. ' . $eventDateParts['month'] . ' ' . $eventDateParts['year'];
}

// If print mode, output minimal page
if ($printMode):
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <title>QR Bordkort - <?= htmlspecialchars($event['name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=<?= $fonts['google'] ?>&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        @page {
            size: A4;
            margin: 0;
        }

        html, body {
            width: 210mm;
            height: 297mm;
        }

        body {
            font-family: <?= $fonts['body'] ?>;
            background: <?= $colorBackground ?>;
            color: <?= $colorText ?>;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .bordkort-page {
            width: 210mm;
            height: 297mm;
            padding: 25mm 30mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        /* Decorative corners */
        .corner {
            position: absolute;
            width: 80px;
            height: 80px;
            border-color: <?= $colorSecondary ?>;
            border-style: solid;
            border-width: 0;
            opacity: 0.4;
        }
        .corner-tl { top: 20mm; left: 20mm; border-top-width: 2px; border-left-width: 2px; }
        .corner-tr { top: 20mm; right: 20mm; border-top-width: 2px; border-right-width: 2px; }
        .corner-bl { bottom: 20mm; left: 20mm; border-bottom-width: 2px; border-left-width: 2px; }
        .corner-br { bottom: 20mm; right: 20mm; border-bottom-width: 2px; border-right-width: 2px; }

        /* Header section */
        .header-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }

        .event-type {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: <?= $colorSecondary ?>;
        }

        .event-name {
            font-family: <?= $fonts['display'] ?>;
            font-size: 42px;
            font-weight: 400;
            color: <?= $colorPrimary ?>;
            letter-spacing: 0.01em;
            line-height: 1.2;
        }

        .event-date {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-top: 8px;
        }

        .event-date-line {
            width: 40px;
            height: 1px;
            background: <?= $colorAccent ?>;
        }

        .event-date-text {
            font-family: <?= $fonts['display'] ?>;
            font-size: 18px;
            font-weight: 400;
            color: <?= $colorPrimary ?>;
            letter-spacing: 0.05em;
        }

        /* Main content */
        .main-section {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .qr-container {
            background: white;
            padding: 20px;
            border-radius: 16px;
            box-shadow: 0 8px 40px rgba(0,0,0,0.08);
            margin-bottom: 32px;
            position: relative;
        }

        .qr-container::before {
            content: '';
            position: absolute;
            inset: -4px;
            border: 2px solid <?= $colorAccent ?>;
            border-radius: 20px;
            opacity: 0.3;
        }

        .qr-code {
            width: 160px;
            height: 160px;
            display: block;
        }

        .headline {
            font-family: <?= $fonts['display'] ?>;
            font-size: 32px;
            font-weight: 400;
            color: <?= $colorPrimary ?>;
            margin-bottom: 10px;
            letter-spacing: 0.01em;
        }

        .subtitle {
            font-size: 16px;
            color: <?= $colorText ?>;
            opacity: 0.6;
            font-weight: 400;
        }

        /* Footer section */
        .footer-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }

        .decorative-element {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .decorative-dot {
            width: 6px;
            height: 6px;
            background: <?= $colorAccent ?>;
            border-radius: 50%;
        }

        .decorative-line {
            width: 50px;
            height: 1px;
            background: <?= $colorSecondary ?>;
        }

        .footer {
            font-size: 12px;
            color: <?= $colorText ?>;
            opacity: 0.4;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        @media print {
            .bordkort-page {
                page-break-after: always;
            }
        }
    </style>
</head>
<body>
    <div class="bordkort-page">
        <!-- Decorative corners -->
        <div class="corner corner-tl"></div>
        <div class="corner corner-tr"></div>
        <div class="corner corner-bl"></div>
        <div class="corner corner-br"></div>

        <!-- Header -->
        <div class="header-section">
            <?php if ($event['event_type_name']): ?>
            <span class="event-type"><?= htmlspecialchars($event['event_type_name']) ?></span>
            <?php endif; ?>

            <?php if ($showEventName): ?>
            <h1 class="event-name"><?= htmlspecialchars($event['name']) ?></h1>
            <?php endif; ?>

            <?php if ($eventDateFormatted): ?>
            <div class="event-date">
                <span class="event-date-line"></span>
                <span class="event-date-text"><?= htmlspecialchars($eventDateFormatted) ?></span>
                <span class="event-date-line"></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Main QR Section -->
        <div class="main-section">
            <div class="qr-container">
                <img src="<?= htmlspecialchars($qrCodeUrl) ?>" alt="QR Code" class="qr-code">
            </div>

            <h2 class="headline"><?= htmlspecialchars($headline) ?></h2>
            <p class="subtitle"><?= htmlspecialchars($subtitle) ?></p>
        </div>

        <!-- Footer -->
        <div class="footer-section">
            <div class="decorative-element">
                <span class="decorative-line"></span>
                <span class="decorative-dot"></span>
                <span class="decorative-line"></span>
            </div>
            <p class="footer">partyparart.dk</p>
        </div>
    </div>

    <!-- Print Controls -->
    <div class="print-controls no-print">
        <button onclick="window.print()" class="print-btn">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
            </svg>
            Print / Gem som PDF
        </button>
        <a href="?id=<?= $eventId ?>&page=qr-bordkort" class="back-btn">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Tilbage
        </a>
        <p class="print-hint">Tip: Vælg "Gem som PDF" i print-dialogen for at downloade</p>
    </div>

    <style>
        .print-controls {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #1a1a1a;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            z-index: 1000;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.3);
        }
        .print-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 28px;
            background: <?= $colorSecondary ?>;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: <?= $fonts['body'] ?>;
        }
        .print-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 20px;
            background: transparent;
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 10px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        .back-btn:hover {
            background: rgba(255,255,255,0.1);
        }
        .print-hint {
            margin-left: auto;
            color: rgba(255,255,255,0.6);
            font-size: 13px;
        }
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .bordkort-page {
                background: <?= $colorBackground ?> !important;
            }
        }
    </style>
</body>
</html>
<?php
exit;
endif;
?>

<div class="page-header-actions">
    <div>
        <h1 class="section-title">QR-Bordkort</h1>
        <p class="section-subtitle">Generer et printbart bordkort med QR-kode</p>
    </div>
</div>

<div class="bordkort-layout">
    <!-- Settings Panel -->
    <div class="card settings-panel">
        <div class="card-header">
            <h2 class="card-title">Indstillinger</h2>
        </div>

        <form method="GET" id="bordkortForm">
            <input type="hidden" name="id" value="<?= $eventId ?>">
            <input type="hidden" name="page" value="qr-bordkort">

            <div class="form-group">
                <label class="form-label">QR-kode destination</label>
                <select name="destination" class="form-input" onchange="this.form.submit()">
                    <?php foreach ($qrDestinations as $key => $dest): ?>
                    <option value="<?= $key ?>" <?= $destination === $key ? 'selected' : '' ?>>
                        <?= htmlspecialchars($dest['label']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <p class="form-hint"><?= htmlspecialchars($qrDestinations[$destination]['description'] ?? '') ?></p>
            </div>

            <div class="form-group">
                <label class="form-label">Overskrift</label>
                <input type="text" name="headline" class="form-input" value="<?= htmlspecialchars($headline) ?>" placeholder="Del dine billeder!">
            </div>

            <div class="form-group">
                <label class="form-label">Undertekst</label>
                <input type="text" name="subtitle" class="form-input" value="<?= htmlspecialchars($subtitle) ?>" placeholder="Scan QR-koden med din mobil">
            </div>

            <div class="form-group">
                <label class="toggle-label">
                    <input type="hidden" name="show_name" value="0">
                    <input type="checkbox" name="show_name" value="1" <?= $showEventName ? 'checked' : '' ?>>
                    <span class="toggle-switch"></span>
                    <span>Vis arrangementsnavn</span>
                </label>
            </div>

            <div class="form-actions" style="border: none; padding-top: 0; margin-top: 16px;">
                <button type="submit" class="btn btn-secondary">Opdater preview</button>
            </div>
        </form>

        <div class="divider-line"></div>

        <div class="color-preview">
            <h4>Tema farver fra invitation</h4>
            <div class="color-swatches">
                <div class="color-swatch" style="background: <?= $colorPrimary ?>;" title="Primær"></div>
                <div class="color-swatch" style="background: <?= $colorSecondary ?>;" title="Sekundær"></div>
                <div class="color-swatch" style="background: <?= $colorAccent ?>;" title="Accent"></div>
                <div class="color-swatch" style="background: <?= $colorBackground ?>; border: 1px solid #ddd;" title="Baggrund"></div>
            </div>
            <p class="form-hint">Farverne hentes automatisk fra din invitation</p>
        </div>

        <div class="print-actions">
            <a href="?id=<?= $eventId ?>&page=qr-bordkort&destination=<?= $destination ?>&headline=<?= urlencode($headline) ?>&subtitle=<?= urlencode($subtitle) ?>&show_name=<?= $showEventName ? '1' : '0' ?>&print=1"
               class="btn btn-primary btn-full" target="_blank">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                </svg>
                Print bordkort
            </a>
        </div>
    </div>

    <!-- Preview Panel -->
    <div class="preview-panel">
        <div class="preview-header">
            <span class="preview-label">Preview (A4)</span>
        </div>
        <div class="preview-frame">
            <div class="bordkort-preview" style="background: <?= $colorBackground ?>; color: <?= $colorText ?>;">
                <?php if ($showEventName): ?>
                <h1 class="preview-event-name" style="color: <?= $colorPrimary ?>; font-family: <?= $fonts['display'] ?>;">
                    <?= htmlspecialchars($event['name']) ?>
                </h1>
                <?php endif; ?>

                <div class="preview-qr-container">
                    <img src="<?= htmlspecialchars($qrCodeUrl) ?>" alt="QR Code" class="preview-qr">
                </div>

                <h2 class="preview-headline" style="color: <?= $colorPrimary ?>; font-family: <?= $fonts['display'] ?>;">
                    <?= htmlspecialchars($headline) ?>
                </h2>
                <p class="preview-subtitle" style="color: <?= $colorText ?>;">
                    <?= htmlspecialchars($subtitle) ?>
                </p>

                <div class="preview-divider" style="background: <?= $colorSecondary ?>;"></div>
                <p class="preview-footer" style="color: <?= $colorText ?>;">partyparart.dk</p>
            </div>
        </div>

        <div class="qr-info">
            <p><strong>QR-koden linker til:</strong></p>
            <code><?= htmlspecialchars($eventUrl) ?></code>
        </div>
    </div>
</div>

<style>
.bordkort-layout {
    display: grid;
    grid-template-columns: 340px 1fr;
    gap: 24px;
    align-items: start;
}

.settings-panel {
    position: sticky;
    top: 100px;
}

.settings-panel .card-header {
    margin-bottom: 20px;
}

.divider-line {
    height: 1px;
    background: var(--cream-dark);
    margin: 24px 0;
}

.color-preview h4 {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 12px;
}

.color-swatches {
    display: flex;
    gap: 8px;
    margin-bottom: 8px;
}

.color-swatch {
    width: 32px;
    height: 32px;
    border-radius: 8px;
}

.print-actions {
    margin-top: 24px;
}

.toggle-label {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    font-size: 14px;
}

.toggle-label input[type="checkbox"] {
    display: none;
}

.toggle-switch {
    width: 44px;
    height: 24px;
    background: var(--cream-dark);
    border-radius: 12px;
    position: relative;
    transition: background 0.3s;
}

.toggle-switch::after {
    content: '';
    position: absolute;
    width: 18px;
    height: 18px;
    background: white;
    border-radius: 50%;
    top: 3px;
    left: 3px;
    transition: transform 0.3s;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}

.toggle-label input:checked + .toggle-switch {
    background: var(--sage);
}

.toggle-label input:checked + .toggle-switch::after {
    transform: translateX(20px);
}

/* Preview Panel */
.preview-panel {
    background: var(--white);
    border-radius: 20px;
    border: 1px solid var(--cream-dark);
    overflow: hidden;
}

.preview-header {
    padding: 16px 20px;
    background: var(--cream);
    border-bottom: 1px solid var(--cream-dark);
}

.preview-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--charcoal-light);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.preview-frame {
    padding: 24px;
    background: #E0E0E0;
}

.bordkort-preview {
    aspect-ratio: 210 / 297;
    max-width: 400px;
    margin: 0 auto;
    padding: 40px 30px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    border-radius: 4px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}

.preview-event-name {
    font-size: 18px;
    font-weight: 500;
    margin-bottom: 24px;
    letter-spacing: 0.02em;
}

.preview-qr-container {
    background: white;
    padding: 16px;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    margin-bottom: 24px;
}

.preview-qr {
    width: 100px;
    height: 100px;
    display: block;
}

.preview-headline {
    font-size: 22px;
    font-weight: 500;
    margin-bottom: 8px;
}

.preview-subtitle {
    font-size: 12px;
    opacity: 0.7;
    margin-bottom: 30px;
}

.preview-divider {
    width: 40px;
    height: 2px;
    margin-bottom: 12px;
}

.preview-footer {
    font-size: 10px;
    opacity: 0.5;
    letter-spacing: 0.05em;
}

.qr-info {
    padding: 16px 20px;
    border-top: 1px solid var(--cream-dark);
    background: var(--cream);
}

.qr-info p {
    font-size: 13px;
    color: var(--charcoal-light);
    margin-bottom: 6px;
}

.qr-info code {
    font-size: 12px;
    color: var(--charcoal);
    word-break: break-all;
}

@media (max-width: 900px) {
    .bordkort-layout {
        grid-template-columns: 1fr;
    }

    .settings-panel {
        position: static;
    }
}
</style>

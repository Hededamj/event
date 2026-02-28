<?php
/**
 * QR Code Helper Functions
 * Utility functions for generating QR codes and bordkort (table cards)
 */

/**
 * Generate a QR code URL using external API
 *
 * @param string $data The data to encode in the QR code
 * @param int $size The size in pixels (width and height)
 * @param string $format The output format (png, svg)
 * @return string The URL to the QR code image
 */
function generateQRCodeUrl(string $data, int $size = 300, string $format = 'png'): string {
    // Using qrserver.com API (free, no API key required)
    $params = [
        'size' => $size . 'x' . $size,
        'data' => $data,
        'format' => $format,
        'margin' => 2,
        'ecc' => 'M' // Error correction level: L, M, Q, H
    ];

    return 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query($params);
}

/**
 * Generate QR code as base64 data URI
 * Useful for embedding directly in HTML/PDF
 *
 * @param string $data The data to encode
 * @param int $size The size in pixels
 * @return string|null Base64 data URI or null on failure
 */
function generateQRCodeBase64(string $data, int $size = 300): ?string {
    $url = generateQRCodeUrl($data, $size, 'png');

    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'PartyParart/1.0'
        ]
    ]);

    $imageData = @file_get_contents($url, false, $context);

    if ($imageData === false) {
        return null;
    }

    return 'data:image/png;base64,' . base64_encode($imageData);
}

/**
 * Get the guest page URL for an event
 *
 * @param string $slug The event slug
 * @param string $page Optional specific page
 * @param string|null $qrToken Optional QR token for public access (photos/memories)
 * @return string Full URL to the guest page
 */
function getEventGuestUrl(string $slug, string $page = '', ?string $qrToken = null): string {
    $baseUrl = baseUrl();
    $path = "/e/" . urlencode($slug) . "/";
    if ($page) {
        $path .= urlencode($page);
    }
    // Append QR token for public pages (photos, memories)
    if ($qrToken && in_array($page, ['photos', 'memories'])) {
        $path .= '?t=' . urlencode($qrToken);
    }
    return $baseUrl . $path;
}

/**
 * Get available QR code destinations for bordkort
 *
 * @return array List of destination options
 */
function getQRDestinations(): array {
    return [
        'photos' => [
            'label' => 'Festbilleder',
            'description' => 'Gæster kan uploade billeder fra festen',
            'page' => 'photos'
        ],
        'memories' => [
            'label' => 'Minde-tidslinje',
            'description' => 'Gæster kan uploade minder med årstal',
            'page' => 'memories'
        ],
        'home' => [
            'label' => 'Gæsteside',
            'description' => 'Link til hovedsiden for gæster',
            'page' => 'home'
        ],
        'rsvp' => [
            'label' => 'Tilmelding',
            'description' => 'Link direkte til tilmeldingssiden',
            'page' => 'rsvp'
        ]
    ];
}

/**
 * Get default bordkort text based on destination
 *
 * @param string $destination The destination type
 * @return array Headline and subtitle
 */
function getBordkortDefaultText(string $destination): array {
    $texts = [
        'photos' => [
            'headline' => 'Del dine billeder!',
            'subtitle' => 'Scan QR-koden med din mobil'
        ],
        'memories' => [
            'headline' => 'Del et minde',
            'subtitle' => 'Upload et billede til tidslinjen'
        ],
        'home' => [
            'headline' => 'Velkommen!',
            'subtitle' => 'Scan for at se invitationen'
        ],
        'rsvp' => [
            'headline' => 'Giv os besked',
            'subtitle' => 'Scan for at svare på invitationen'
        ]
    ];

    return $texts[$destination] ?? $texts['photos'];
}

/**
 * Generate CSS for print-optimized bordkort
 * Uses invitation config colors if available
 *
 * @param array $invitationConfig The invitation configuration
 * @return string CSS styles
 */
function getBordkortPrintCSS(array $invitationConfig): string {
    $primary = $invitationConfig['color_primary'] ?? '#1A1A1A';
    $secondary = $invitationConfig['color_secondary'] ?? '#8FA583';
    $accent = $invitationConfig['color_accent'] ?? '#B8923D';
    $background = $invitationConfig['color_background'] ?? '#FAF9F7';
    $text = $invitationConfig['color_text'] ?? '#1A1A1A';

    return <<<CSS
:root {
    --bk-primary: {$primary};
    --bk-secondary: {$secondary};
    --bk-accent: {$accent};
    --bk-background: {$background};
    --bk-text: {$text};
}

@media print {
    body {
        margin: 0;
        padding: 0;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .no-print {
        display: none !important;
    }

    .bordkort-page {
        width: 210mm;
        height: 297mm;
        padding: 0;
        margin: 0;
        page-break-after: always;
        page-break-inside: avoid;
    }
}

@page {
    size: A4;
    margin: 0;
}
CSS;
}

/**
 * Get font family CSS based on invitation font style
 *
 * @param string $fontStyle The font style setting
 * @return array Font family for display and body
 */
function getBordkortFonts(string $fontStyle): array {
    $fonts = [
        'elegant' => [
            'display' => "'Cormorant Garamond', Georgia, serif",
            'body' => "'DM Sans', -apple-system, sans-serif",
            'google' => 'Cormorant+Garamond:wght@400;500;600&family=DM+Sans:wght@400;500;600'
        ],
        'modern' => [
            'display' => "'Inter', -apple-system, sans-serif",
            'body' => "'Inter', -apple-system, sans-serif",
            'google' => 'Inter:wght@400;500;600'
        ],
        'playful' => [
            'display' => "'Quicksand', sans-serif",
            'body' => "'Nunito', sans-serif",
            'google' => 'Quicksand:wght@400;500;600&family=Nunito:wght@400;500;600'
        ],
        'traditional' => [
            'display' => "'Playfair Display', Georgia, serif",
            'body' => "'Lora', Georgia, serif",
            'google' => 'Playfair+Display:wght@400;500;600&family=Lora:wght@400;500;600'
        ],
        'minimal' => [
            'display' => "'DM Sans', -apple-system, sans-serif",
            'body' => "'DM Sans', -apple-system, sans-serif",
            'google' => 'DM+Sans:wght@400;500;600'
        ]
    ];

    return $fonts[$fontStyle] ?? $fonts['elegant'];
}

/**
 * Calculate timeline years for an event
 *
 * @param string|null $startDate Timeline start date
 * @param string|null $endDate Event date (defaults to current date if null)
 * @return array Array of years from start to end
 */
function getTimelineYears(?string $startDate, ?string $endDate = null): array {
    if (!$startDate) {
        return [];
    }

    $start = (int)date('Y', strtotime($startDate));
    $end = $endDate ? (int)date('Y', strtotime($endDate)) : (int)date('Y');

    if ($start > $end) {
        return [];
    }

    return range($start, $end);
}

/**
 * Get suggested timeline start date based on event type
 *
 * @param string $eventTypeName The event type name
 * @param string|null $eventDate The event date
 * @param string|null $mainPersonBirthdate Optional birth date
 * @return array Suggested start date and label
 */
function getSuggestedTimelineStart(string $eventTypeName, ?string $eventDate, ?string $mainPersonBirthdate = null): array {
    $eventYear = $eventDate ? (int)date('Y', strtotime($eventDate)) : (int)date('Y');

    // Default values
    $result = [
        'date' => date('Y-01-01', strtotime('-50 years')),
        'label' => 'Minder gennem tiden'
    ];

    $eventTypeLower = mb_strtolower($eventTypeName);

    if (strpos($eventTypeLower, 'fødselsdag') !== false || strpos($eventTypeLower, 'birthday') !== false) {
        // Birthday: Birth date to event date
        if ($mainPersonBirthdate) {
            $result['date'] = $mainPersonBirthdate;
        } else {
            // Try to guess from event name (e.g., "50 års fødselsdag")
            if (preg_match('/(\d+)\s*års?\s*fødselsdag/i', $eventTypeName, $matches)) {
                $age = (int)$matches[1];
                $result['date'] = date('Y-01-01', strtotime("-{$age} years", strtotime($eventDate)));
            }
        }
        $result['label'] = 'Mit liv i billeder';

    } elseif (strpos($eventTypeLower, 'bryllup') !== false || strpos($eventTypeLower, 'wedding') !== false) {
        // Wedding: Meeting date to wedding date
        $result['date'] = date('Y-01-01', strtotime('-10 years', strtotime($eventDate)));
        $result['label'] = 'Vores historie';

    } elseif (strpos($eventTypeLower, 'sølvbryllup') !== false) {
        // Silver wedding: 25 years ago
        $result['date'] = date('Y-01-01', strtotime('-25 years', strtotime($eventDate)));
        $result['label'] = '25 års kærlighed';

    } elseif (strpos($eventTypeLower, 'guldbryllup') !== false) {
        // Golden wedding: 50 years ago
        $result['date'] = date('Y-01-01', strtotime('-50 years', strtotime($eventDate)));
        $result['label'] = '50 års kærlighed';

    } elseif (strpos($eventTypeLower, 'konfirmation') !== false) {
        // Confirmation: Birth to confirmation (13-14 years)
        $result['date'] = date('Y-01-01', strtotime('-14 years', strtotime($eventDate)));
        $result['label'] = 'Fra baby til voksen';

    } elseif (strpos($eventTypeLower, 'begravelse') !== false || strpos($eventTypeLower, 'mindehøjtid') !== false) {
        // Memorial: Full life span
        if ($mainPersonBirthdate) {
            $result['date'] = $mainPersonBirthdate;
        }
        $result['label'] = 'Et liv i billeder';

    } elseif (strpos($eventTypeLower, 'jubilæum') !== false) {
        // Anniversary: Depends on type
        $result['date'] = date('Y-01-01', strtotime('-25 years', strtotime($eventDate)));
        $result['label'] = 'Årene der er gået';
    }

    return $result;
}

<?php
/**
 * Invitation System Helper Functions
 * Utility functions for invitation configuration, templates, and images
 */

/**
 * Get all active invitation templates
 */
function getInvitationTemplates(PDO $db, ?int $eventTypeId = null): array {
    if ($eventTypeId) {
        $stmt = $db->prepare("
            SELECT t.*,
                   COALESCE(te.is_recommended, 0) as is_recommended
            FROM invitation_templates t
            LEFT JOIN template_event_types te ON t.id = te.template_id AND te.event_type_id = ?
            WHERE t.is_active = 1
            ORDER BY te.is_recommended DESC, t.sort_order ASC
        ");
        $stmt->execute([$eventTypeId]);
    } else {
        $stmt = $db->query("
            SELECT *, 0 as is_recommended
            FROM invitation_templates
            WHERE is_active = 1
            ORDER BY sort_order ASC
        ");
    }
    return $stmt->fetchAll();
}

/**
 * Get a single template by ID or slug
 */
function getInvitationTemplate(PDO $db, $idOrSlug): ?array {
    $field = is_numeric($idOrSlug) ? 'id' : 'slug';
    $stmt = $db->prepare("SELECT * FROM invitation_templates WHERE $field = ?");
    $stmt->execute([$idOrSlug]);
    $template = $stmt->fetch();

    if ($template) {
        $template['color_scheme'] = json_decode($template['color_scheme'], true);
        $template['sections_config'] = json_decode($template['sections_config'], true);
    }

    return $template ?: null;
}

/**
 * Get invitation config for an event (or create default)
 */
function getInvitationConfig(PDO $db, int $eventId): array {
    $stmt = $db->prepare("
        SELECT ic.*, it.name as template_name, it.slug as template_slug
        FROM invitation_configs ic
        LEFT JOIN invitation_templates it ON ic.template_id = it.id
        WHERE ic.event_id = ?
    ");
    $stmt->execute([$eventId]);
    $config = $stmt->fetch();

    if (!$config) {
        // Return default config
        return [
            'id' => null,
            'event_id' => $eventId,
            'template_id' => null,
            'layout_style' => 'split',
            'font_style' => 'elegant',
            'color_primary' => '#1A1A1A',
            'color_secondary' => '#8FA583',
            'color_accent' => '#B8923D',
            'color_text' => '#1A1A1A',
            'color_background' => '#FAF9F7',
            'greeting_template' => 'Kære {guest_name}',
            'headline_text' => null,
            'invitation_message' => null,
            'closing_text' => null,
            'show_countdown' => 1,
            'show_map' => 0,
            'show_schedule' => 1,
            'show_rsvp' => 1,
            'sections_order' => null,
            'custom_css' => null,
            'is_published' => 0
        ];
    }

    // Decode JSON fields
    if ($config['sections_order']) {
        $config['sections_order'] = json_decode($config['sections_order'], true);
    }

    return $config;
}

/**
 * Save invitation config
 */
function saveInvitationConfig(PDO $db, int $eventId, array $data): bool {
    $existingConfig = getInvitationConfig($db, $eventId);

    // Prepare sections_order as JSON
    $sectionsOrder = isset($data['sections_order']) && is_array($data['sections_order'])
        ? json_encode($data['sections_order'])
        : null;

    if ($existingConfig['id']) {
        // Update existing
        $stmt = $db->prepare("
            UPDATE invitation_configs SET
                template_id = ?,
                layout_style = ?,
                font_style = ?,
                color_primary = ?,
                color_secondary = ?,
                color_accent = ?,
                color_text = ?,
                color_background = ?,
                greeting_template = ?,
                headline_text = ?,
                invitation_message = ?,
                closing_text = ?,
                show_countdown = ?,
                show_map = ?,
                show_schedule = ?,
                show_rsvp = ?,
                sections_order = ?,
                custom_css = ?
            WHERE event_id = ?
        ");
        return $stmt->execute([
            $data['template_id'] ?? null,
            $data['layout_style'] ?? 'split',
            $data['font_style'] ?? 'elegant',
            $data['color_primary'] ?? '#1A1A1A',
            $data['color_secondary'] ?? '#8FA583',
            $data['color_accent'] ?? '#B8923D',
            $data['color_text'] ?? '#1A1A1A',
            $data['color_background'] ?? '#FAF9F7',
            $data['greeting_template'] ?? 'Kære {guest_name}',
            $data['headline_text'] ?? null,
            $data['invitation_message'] ?? null,
            $data['closing_text'] ?? null,
            $data['show_countdown'] ?? 1,
            $data['show_map'] ?? 0,
            $data['show_schedule'] ?? 1,
            $data['show_rsvp'] ?? 1,
            $sectionsOrder,
            $data['custom_css'] ?? null,
            $eventId
        ]);
    } else {
        // Insert new
        $stmt = $db->prepare("
            INSERT INTO invitation_configs (
                event_id, template_id, layout_style, font_style,
                color_primary, color_secondary, color_accent, color_text, color_background,
                greeting_template, headline_text, invitation_message, closing_text,
                show_countdown, show_map, show_schedule, show_rsvp, sections_order, custom_css
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $eventId,
            $data['template_id'] ?? null,
            $data['layout_style'] ?? 'split',
            $data['font_style'] ?? 'elegant',
            $data['color_primary'] ?? '#1A1A1A',
            $data['color_secondary'] ?? '#8FA583',
            $data['color_accent'] ?? '#B8923D',
            $data['color_text'] ?? '#1A1A1A',
            $data['color_background'] ?? '#FAF9F7',
            $data['greeting_template'] ?? 'Kære {guest_name}',
            $data['headline_text'] ?? null,
            $data['invitation_message'] ?? null,
            $data['closing_text'] ?? null,
            $data['show_countdown'] ?? 1,
            $data['show_map'] ?? 0,
            $data['show_schedule'] ?? 1,
            $data['show_rsvp'] ?? 1,
            $sectionsOrder,
            $data['custom_css'] ?? null
        ]);
    }
}

/**
 * Apply template to invitation config
 */
function applyTemplateToConfig(PDO $db, int $eventId, int $templateId): bool {
    $template = getInvitationTemplate($db, $templateId);
    if (!$template) {
        return false;
    }

    $colors = $template['color_scheme'];
    $sections = $template['sections_config'];

    return saveInvitationConfig($db, $eventId, [
        'template_id' => $templateId,
        'layout_style' => $template['layout_style'],
        'font_style' => $template['font_style'],
        'color_primary' => $colors['primary'] ?? '#1A1A1A',
        'color_secondary' => $colors['secondary'] ?? '#8FA583',
        'color_accent' => $colors['accent'] ?? '#B8923D',
        'color_text' => $colors['text'] ?? '#1A1A1A',
        'color_background' => $colors['background'] ?? '#FAF9F7',
        'sections_order' => $sections
    ]);
}

/**
 * Publish or unpublish invitation
 */
function setInvitationPublished(PDO $db, int $eventId, bool $published): bool {
    $config = getInvitationConfig($db, $eventId);

    if (!$config['id']) {
        // Create config first
        saveInvitationConfig($db, $eventId, []);
    }

    $stmt = $db->prepare("
        UPDATE invitation_configs
        SET is_published = ?, published_at = ?
        WHERE event_id = ?
    ");
    return $stmt->execute([
        $published ? 1 : 0,
        $published ? date('Y-m-d H:i:s') : null,
        $eventId
    ]);
}

/**
 * Get invitation images for an event
 */
function getInvitationImages(PDO $db, int $eventId, ?string $role = null): array {
    if ($role) {
        $stmt = $db->prepare("
            SELECT * FROM invitation_images
            WHERE event_id = ? AND image_role = ?
            ORDER BY position ASC
        ");
        $stmt->execute([$eventId, $role]);
    } else {
        $stmt = $db->prepare("
            SELECT * FROM invitation_images
            WHERE event_id = ?
            ORDER BY image_role ASC, position ASC
        ");
        $stmt->execute([$eventId]);
    }
    return $stmt->fetchAll();
}

/**
 * Get the hero image for an event
 */
function getHeroImage(PDO $db, int $eventId): ?array {
    $stmt = $db->prepare("
        SELECT * FROM invitation_images
        WHERE event_id = ? AND image_role = 'hero'
        ORDER BY position ASC
        LIMIT 1
    ");
    $stmt->execute([$eventId]);
    return $stmt->fetch() ?: null;
}

/**
 * Upload invitation image
 */
function uploadInvitationImage(PDO $db, int $eventId, array $file, string $role = 'gallery'): array {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 10 * 1024 * 1024; // 10MB

    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'error' => 'Ugyldig filtype. Kun JPG, PNG, GIF og WebP er tilladt.'];
    }

    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'Filen er for stor. Maksimum er 10MB.'];
    }

    // Generate unique filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'inv_' . $eventId . '_' . uniqid() . '.' . strtolower($ext);

    $uploadDir = __DIR__ . '/../uploads/invitations/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $targetPath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => false, 'error' => 'Kunne ikke uploade filen.'];
    }

    // Get next position
    $stmt = $db->prepare("
        SELECT COALESCE(MAX(position), 0) + 1 as next_pos
        FROM invitation_images
        WHERE event_id = ? AND image_role = ?
    ");
    $stmt->execute([$eventId, $role]);
    $nextPos = $stmt->fetchColumn();

    // If uploading hero, set previous heroes to gallery
    if ($role === 'hero') {
        $stmt = $db->prepare("
            UPDATE invitation_images
            SET image_role = 'gallery'
            WHERE event_id = ? AND image_role = 'hero'
        ");
        $stmt->execute([$eventId]);
        $nextPos = 1;
    }

    // Save to database
    $stmt = $db->prepare("
        INSERT INTO invitation_images (event_id, filename, original_name, file_size, mime_type, image_role, position)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $eventId,
        $filename,
        $file['name'],
        $file['size'],
        $file['type'],
        $role,
        $nextPos
    ]);

    return [
        'success' => true,
        'id' => $db->lastInsertId(),
        'filename' => $filename,
        'url' => '/uploads/invitations/' . $filename
    ];
}

/**
 * Delete invitation image
 */
function deleteInvitationImage(PDO $db, int $imageId, int $eventId): bool {
    // Get image info
    $stmt = $db->prepare("SELECT * FROM invitation_images WHERE id = ? AND event_id = ?");
    $stmt->execute([$imageId, $eventId]);
    $image = $stmt->fetch();

    if (!$image) {
        return false;
    }

    // Delete file
    $filePath = __DIR__ . '/../uploads/invitations/' . $image['filename'];
    if (file_exists($filePath)) {
        unlink($filePath);
    }

    // Delete from database
    $stmt = $db->prepare("DELETE FROM invitation_images WHERE id = ?");
    return $stmt->execute([$imageId]);
}

/**
 * Reorder invitation images
 */
function reorderInvitationImages(PDO $db, int $eventId, array $imageIds): bool {
    $db->beginTransaction();
    try {
        $position = 1;
        $stmt = $db->prepare("UPDATE invitation_images SET position = ? WHERE id = ? AND event_id = ?");
        foreach ($imageIds as $imageId) {
            $stmt->execute([$position++, $imageId, $eventId]);
        }
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        return false;
    }
}

/**
 * Set image role (hero, gallery, background)
 */
function setImageRole(PDO $db, int $imageId, int $eventId, string $role): bool {
    // If setting as hero, demote existing hero
    if ($role === 'hero') {
        $stmt = $db->prepare("
            UPDATE invitation_images
            SET image_role = 'gallery'
            WHERE event_id = ? AND image_role = 'hero'
        ");
        $stmt->execute([$eventId]);
    }

    $stmt = $db->prepare("UPDATE invitation_images SET image_role = ? WHERE id = ? AND event_id = ?");
    return $stmt->execute([$role, $imageId, $eventId]);
}

/**
 * Personalize greeting text for a guest
 */
function personalizeGreeting(string $template, array $guest): string {
    $guestName = $guest['name'] ?? 'Gæst';

    // If guest has multiple names (e.g., "Mormor & Morfar")
    if (!empty($guest['guest_names'])) {
        $names = json_decode($guest['guest_names'], true);
        if (is_array($names) && count($names) > 0) {
            $guestName = implode(' & ', $names);
        }
    }

    return str_replace('{guest_name}', $guestName, $template);
}

/**
 * Get CSS variables for invitation styling
 */
function getInvitationCssVariables(array $config): string {
    $vars = [
        '--inv-primary' => $config['color_primary'] ?? '#1A1A1A',
        '--inv-secondary' => $config['color_secondary'] ?? '#8FA583',
        '--inv-accent' => $config['color_accent'] ?? '#B8923D',
        '--inv-text' => $config['color_text'] ?? '#1A1A1A',
        '--inv-background' => $config['color_background'] ?? '#FAF9F7',
    ];

    $css = ':root {' . PHP_EOL;
    foreach ($vars as $name => $value) {
        $css .= "    $name: $value;" . PHP_EOL;
    }
    $css .= '}';

    return $css;
}

/**
 * Get font family based on font style
 */
function getInvitationFontFamily(string $fontStyle): array {
    $fonts = [
        'elegant' => [
            'display' => "'Cormorant Garamond', Georgia, serif",
            'body' => "'DM Sans', -apple-system, sans-serif"
        ],
        'modern' => [
            'display' => "'Inter', -apple-system, sans-serif",
            'body' => "'Inter', -apple-system, sans-serif"
        ],
        'playful' => [
            'display' => "'Quicksand', 'Comic Sans MS', sans-serif",
            'body' => "'Nunito', sans-serif"
        ],
        'traditional' => [
            'display' => "'Playfair Display', Georgia, serif",
            'body' => "'Lora', Georgia, serif"
        ],
        'minimal' => [
            'display' => "'DM Sans', -apple-system, sans-serif",
            'body' => "'DM Sans', -apple-system, sans-serif"
        ]
    ];

    return $fonts[$fontStyle] ?? $fonts['elegant'];
}

/**
 * Get ordered sections for rendering
 */
function getOrderedSections(array $config): array {
    $defaultSections = [
        'hero' => ['enabled' => true, 'position' => 1],
        'greeting' => ['enabled' => true, 'position' => 2],
        'message' => ['enabled' => true, 'position' => 3],
        'details' => ['enabled' => true, 'position' => 4],
        'countdown' => ['enabled' => true, 'position' => 5],
        'gallery' => ['enabled' => true, 'position' => 6],
        'rsvp' => ['enabled' => true, 'position' => 7]
    ];

    $sections = $config['sections_order'] ?? $defaultSections;

    // Filter enabled sections and sort by position
    $enabled = array_filter($sections, fn($s) => $s['enabled'] ?? true);
    uasort($enabled, fn($a, $b) => ($a['position'] ?? 99) - ($b['position'] ?? 99));

    return array_keys($enabled);
}

/**
 * Check if invitation is ready to be published
 */
function isInvitationReadyToPublish(PDO $db, int $eventId): array {
    $config = getInvitationConfig($db, $eventId);
    $images = getInvitationImages($db, $eventId);

    $issues = [];

    // Check for hero image
    $hasHero = array_filter($images, fn($i) => $i['image_role'] === 'hero');
    if (empty($hasHero)) {
        $issues[] = 'Ingen hovedbillede valgt';
    }

    // Check for invitation message
    if (empty($config['invitation_message'])) {
        $issues[] = 'Ingen invitationsbesked skrevet';
    }

    return [
        'ready' => empty($issues),
        'issues' => $issues
    ];
}

/**
 * Get email statistics for an event
 */
function getInvitationEmailStats(PDO $db, int $eventId): array {
    $stmt = $db->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
            SUM(CASE WHEN status = 'opened' THEN 1 ELSE 0 END) as opened,
            SUM(CASE WHEN status = 'clicked' THEN 1 ELSE 0 END) as clicked,
            SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) as bounced,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
        FROM invitation_emails
        WHERE event_id = ?
    ");
    $stmt->execute([$eventId]);
    return $stmt->fetch();
}

/**
 * Get guests who haven't received invitations
 */
function getGuestsWithoutInvitation(PDO $db, int $eventId): array {
    $stmt = $db->prepare("
        SELECT g.*
        FROM guests g
        LEFT JOIN invitation_emails ie ON g.id = ie.guest_id AND ie.email_type = 'invitation'
        WHERE g.event_id = ?
        AND g.email IS NOT NULL
        AND g.email != ''
        AND ie.id IS NULL
        ORDER BY g.name ASC
    ");
    $stmt->execute([$eventId]);
    return $stmt->fetchAll();
}

/**
 * Log email send attempt
 */
function logInvitationEmail(PDO $db, int $eventId, int $guestId, string $email, string $type = 'invitation'): int {
    $stmt = $db->prepare("
        INSERT INTO invitation_emails (event_id, guest_id, email_address, email_type, status)
        VALUES (?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([$eventId, $guestId, $email, $type]);
    return (int)$db->lastInsertId();
}

/**
 * Update email status
 */
function updateEmailStatus(PDO $db, int $emailId, string $status, ?string $externalId = null, ?string $error = null): bool {
    $timestampField = match($status) {
        'sent' => 'sent_at',
        'delivered' => 'delivered_at',
        'opened' => 'opened_at',
        'clicked' => 'clicked_at',
        default => null
    };

    $sql = "UPDATE invitation_emails SET status = ?";
    $params = [$status];

    if ($externalId) {
        $sql .= ", external_id = ?";
        $params[] = $externalId;
    }

    if ($error) {
        $sql .= ", error_message = ?";
        $params[] = $error;
    }

    if ($timestampField) {
        $sql .= ", $timestampField = NOW()";
    }

    $sql .= " WHERE id = ?";
    $params[] = $emailId;

    $stmt = $db->prepare($sql);
    return $stmt->execute($params);
}

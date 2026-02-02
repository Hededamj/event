-- Invitation Templates Seed Data
-- Seeds the invitation_templates table with 6 default templates

-- Clear existing templates (optional - comment out if you want to preserve existing)
-- TRUNCATE TABLE template_event_types;
-- TRUNCATE TABLE invitation_templates;

-- ============================================
-- TEMPLATE 1: Nordic Elegance
-- ============================================
INSERT INTO invitation_templates (name, slug, preview_image, layout_style, font_style, color_scheme, sections_config, category, is_blank, sort_order) VALUES
(
    'Nordic Elegance',
    'nordic-elegance',
    '/assets/images/templates/nordic-elegance.jpg',
    'split',
    'elegant',
    '{
        "primary": "#1A1A1A",
        "secondary": "#8FA583",
        "accent": "#B8923D",
        "text": "#1A1A1A",
        "background": "#FAF9F7",
        "card": "#FFFFFF"
    }',
    '{
        "hero": {"enabled": true, "position": 1},
        "greeting": {"enabled": true, "position": 2},
        "message": {"enabled": true, "position": 3},
        "details": {"enabled": true, "position": 4},
        "countdown": {"enabled": true, "position": 5},
        "gallery": {"enabled": true, "position": 6},
        "rsvp": {"enabled": true, "position": 7}
    }',
    'wedding',
    0,
    1
);

-- ============================================
-- TEMPLATE 2: Celebration Joy
-- ============================================
INSERT INTO invitation_templates (name, slug, preview_image, layout_style, font_style, color_scheme, sections_config, category, is_blank, sort_order) VALUES
(
    'Celebration Joy',
    'celebration-joy',
    '/assets/images/templates/celebration-joy.jpg',
    'centered',
    'playful',
    '{
        "primary": "#FF6B6B",
        "secondary": "#4ECDC4",
        "accent": "#FFE66D",
        "text": "#2C3E50",
        "background": "#FFFFFF",
        "card": "#F8F9FA"
    }',
    '{
        "hero": {"enabled": true, "position": 1},
        "greeting": {"enabled": true, "position": 2},
        "message": {"enabled": true, "position": 3},
        "countdown": {"enabled": true, "position": 4},
        "details": {"enabled": true, "position": 5},
        "gallery": {"enabled": true, "position": 6},
        "rsvp": {"enabled": true, "position": 7}
    }',
    'birthday',
    0,
    2
);

-- ============================================
-- TEMPLATE 3: Sacred Moments
-- ============================================
INSERT INTO invitation_templates (name, slug, preview_image, layout_style, font_style, color_scheme, sections_config, category, is_blank, sort_order) VALUES
(
    'Sacred Moments',
    'sacred-moments',
    '/assets/images/templates/sacred-moments.jpg',
    'minimal',
    'traditional',
    '{
        "primary": "#2C3E50",
        "secondary": "#8E9AAF",
        "accent": "#C9A227",
        "text": "#2C3E50",
        "background": "#FAFAFA",
        "card": "#FFFFFF"
    }',
    '{
        "greeting": {"enabled": true, "position": 1},
        "message": {"enabled": true, "position": 2},
        "hero": {"enabled": true, "position": 3},
        "details": {"enabled": true, "position": 4},
        "rsvp": {"enabled": true, "position": 5},
        "countdown": {"enabled": false, "position": 6},
        "gallery": {"enabled": false, "position": 7}
    }',
    'ceremony',
    0,
    3
);

-- ============================================
-- TEMPLATE 4: Modern Edge
-- ============================================
INSERT INTO invitation_templates (name, slug, preview_image, layout_style, font_style, color_scheme, sections_config, category, is_blank, sort_order) VALUES
(
    'Modern Edge',
    'modern-edge',
    '/assets/images/templates/modern-edge.jpg',
    'fullscreen',
    'modern',
    '{
        "primary": "#000000",
        "secondary": "#6C63FF",
        "accent": "#FF6584",
        "text": "#FFFFFF",
        "background": "#0A0A0A",
        "card": "#1A1A2E"
    }',
    '{
        "hero": {"enabled": true, "position": 1, "fullscreen": true},
        "greeting": {"enabled": true, "position": 2},
        "countdown": {"enabled": true, "position": 3},
        "details": {"enabled": true, "position": 4},
        "message": {"enabled": true, "position": 5},
        "gallery": {"enabled": true, "position": 6},
        "rsvp": {"enabled": true, "position": 7}
    }',
    'party',
    0,
    4
);

-- ============================================
-- TEMPLATE 5: Classic Grace
-- ============================================
INSERT INTO invitation_templates (name, slug, preview_image, layout_style, font_style, color_scheme, sections_config, category, is_blank, sort_order) VALUES
(
    'Classic Grace',
    'classic-grace',
    '/assets/images/templates/classic-grace.jpg',
    'classic',
    'elegant',
    '{
        "primary": "#2D3436",
        "secondary": "#D4A574",
        "accent": "#C9A227",
        "text": "#2D3436",
        "background": "#FDF6E3",
        "card": "#FFFFFF"
    }',
    '{
        "hero": {"enabled": true, "position": 1},
        "greeting": {"enabled": true, "position": 2},
        "message": {"enabled": true, "position": 3},
        "details": {"enabled": true, "position": 4},
        "gallery": {"enabled": true, "position": 5},
        "countdown": {"enabled": true, "position": 6},
        "rsvp": {"enabled": true, "position": 7}
    }',
    'anniversary',
    0,
    5
);

-- ============================================
-- TEMPLATE 6: Blank Canvas
-- ============================================
INSERT INTO invitation_templates (name, slug, preview_image, layout_style, font_style, color_scheme, sections_config, category, is_blank, sort_order) VALUES
(
    'Blank Canvas',
    'blank-canvas',
    '/assets/images/templates/blank-canvas.jpg',
    'split',
    'elegant',
    '{
        "primary": "#1A1A1A",
        "secondary": "#666666",
        "accent": "#1A1A1A",
        "text": "#1A1A1A",
        "background": "#FFFFFF",
        "card": "#F5F5F5"
    }',
    '{
        "hero": {"enabled": true, "position": 1},
        "greeting": {"enabled": true, "position": 2},
        "message": {"enabled": true, "position": 3},
        "details": {"enabled": true, "position": 4},
        "countdown": {"enabled": true, "position": 5},
        "gallery": {"enabled": true, "position": 6},
        "rsvp": {"enabled": true, "position": 7}
    }',
    'general',
    1,
    6
);

-- ============================================
-- TEMPLATE - EVENT TYPE RECOMMENDATIONS
-- ============================================

-- Nordic Elegance: Wedding, Anniversary
INSERT INTO template_event_types (template_id, event_type_id, is_recommended)
SELECT t.id, et.id, 1
FROM invitation_templates t, event_types et
WHERE t.slug = 'nordic-elegance' AND et.slug IN ('wedding', 'anniversary');

-- Celebration Joy: Birthday, Graduation
INSERT INTO template_event_types (template_id, event_type_id, is_recommended)
SELECT t.id, et.id, 1
FROM invitation_templates t, event_types et
WHERE t.slug = 'celebration-joy' AND et.slug IN ('birthday', 'graduation');

-- Sacred Moments: Confirmation, Baptism, Funeral
INSERT INTO template_event_types (template_id, event_type_id, is_recommended)
SELECT t.id, et.id, 1
FROM invitation_templates t, event_types et
WHERE t.slug = 'sacred-moments' AND et.slug IN ('confirmation', 'baptism', 'funeral');

-- Modern Edge: Bachelor-party, Company event
INSERT INTO template_event_types (template_id, event_type_id, is_recommended)
SELECT t.id, et.id, 1
FROM invitation_templates t, event_types et
WHERE t.slug = 'modern-edge' AND et.slug IN ('bachelor-party', 'company');

-- Classic Grace: Reception, Anniversary
INSERT INTO template_event_types (template_id, event_type_id, is_recommended)
SELECT t.id, et.id, 1
FROM invitation_templates t, event_types et
WHERE t.slug = 'classic-grace' AND et.slug IN ('reception', 'anniversary');

-- Blank Canvas: All event types (general purpose)
INSERT INTO template_event_types (template_id, event_type_id, is_recommended)
SELECT t.id, et.id, 0
FROM invitation_templates t, event_types et
WHERE t.slug = 'blank-canvas';

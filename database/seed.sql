-- ========================================
-- SEED DATA FOR TESTING
-- Sofies Konfirmation
-- ========================================

-- Insert the event
INSERT INTO events (name, event_date, event_time, location, theme, welcome_text, confirmand_name)
VALUES (
    'Konfirmation',
    '2026-05-10',
    '12:00:00',
    'Hjemme hos familien Hummel',
    'girl',
    'Kære familie og venner,

Vi glæder os enormt til at fejre Sofies konfirmation sammen med jer alle. Det bliver en dag fyldt med god mad, varme ord og masser af hygge.

Vi håber I alle kan komme og være med til at gøre dagen helt speciel.',
    'Sofie'
);

-- Insert admin user (password: password)
INSERT INTO users (event_id, email, password_hash, name, role)
VALUES (
    1,
    'admin@example.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'Administrator',
    'admin'
);

-- Insert some test guests
INSERT INTO guests (event_id, name, email, phone, unique_code, rsvp_status, adults_count, children_count) VALUES
(1, 'Mormor og Morfar', 'mormor@example.com', '12345678', '123456', 'yes', 2, 0),
(1, 'Farmor og Farfar', 'farmor@example.com', '23456789', '234567', 'yes', 2, 0),
(1, 'Onkel Peter og familie', 'peter@example.com', '34567890', '345678', 'yes', 2, 2),
(1, 'Tante Anna', 'anna@example.com', '45678901', '456789', 'pending', 1, 0),
(1, 'Familie Jensen', 'jensen@example.com', '56789012', '567890', 'pending', 2, 1),
(1, 'Sofies bedste veninde Emma', 'emma@example.com', '67890123', '678901', 'yes', 1, 0),
(1, 'Familie Nielsen', 'nielsen@example.com', '78901234', '789012', 'no', 0, 0),
(1, 'Nabofamilien Hansen', 'hansen@example.com', '89012345', '890123', 'pending', 2, 0);

-- Update RSVP date for those who responded
UPDATE guests SET rsvp_date = NOW() WHERE rsvp_status != 'pending' AND event_id = 1;

-- Insert dietary notes
UPDATE guests SET dietary_notes = 'Glutenfri' WHERE name = 'Farmor og Farfar' AND event_id = 1;
UPDATE guests SET dietary_notes = 'Vegetar' WHERE name = 'Sofies bedste veninde Emma' AND event_id = 1;

-- Insert checklist items
INSERT INTO checklist_items (event_id, category, task, due_date, sort_order, completed, assigned_to) VALUES
(1, 'praktisk', 'Send invitationer ud', '2026-04-01', 1, true, 'Mor'),
(1, 'praktisk', 'Book fotograf', '2026-04-15', 2, false, 'Far'),
(1, 'praktisk', 'Lån ekstra stole og borde', '2026-05-05', 3, false, 'Far'),
(1, 'praktisk', 'Lav bordplan', '2026-05-07', 4, false, 'Mor'),
(1, 'mad', 'Bestil konfirmationskage', '2026-04-20', 5, false, 'Mor'),
(1, 'mad', 'Planlæg menu', '2026-04-15', 6, true, 'Mor'),
(1, 'mad', 'Køb drikkevarer', '2026-05-08', 7, false, 'Far'),
(1, 'mad', 'Bestil smørrebrød', '2026-05-01', 8, false, 'Mor'),
(1, 'pynt', 'Køb blomster', '2026-05-09', 9, false, 'Mor'),
(1, 'pynt', 'Køb servietter og duge', '2026-05-01', 10, false, 'Mor'),
(1, 'pynt', 'Lav velkomstskilt', '2026-05-08', 11, false, 'Sofie'),
(1, 'underholdning', 'Forbered tale', '2026-05-08', 12, false, 'Far'),
(1, 'underholdning', 'Lav playlist', '2026-05-05', 13, false, 'Sofie');

-- Mark completed items
UPDATE checklist_items SET completed_at = NOW() WHERE completed = true AND event_id = 1;

-- Insert wishlist items
INSERT INTO wishlist_items (event_id, title, description, price, link, priority) VALUES
(1, 'AirPods Pro', 'Apple AirPods Pro 2. generation med støjreducering', 1999.00, 'https://www.apple.com/dk/shop/product/airpods-pro', 10),
(1, 'Gavekort til H&M', 'Til tøjshopping', 500.00, NULL, 8),
(1, 'Polaroid kamera', 'Instax Mini 12 i lyserød', 699.00, 'https://www.fotogear.dk', 9),
(1, 'Smykkeskrin', 'Fint smykkeskrin i læder', 299.00, NULL, 5),
(1, 'Pengegave', 'Til opsparingen', NULL, NULL, 7),
(1, 'Make-up sæt', 'Charlotte Tilbury eller lignende', 800.00, NULL, 6),
(1, 'Bog: Min første kogebog', 'For unge kokke', 199.00, NULL, 3),
(1, 'Bluetooth højtaler', 'JBL Go 3 i rosa', 349.00, NULL, 4);

-- Reserve some wishlist items
UPDATE wishlist_items
SET reserved_by_guest_id = (SELECT id FROM guests WHERE name = 'Mormor og Morfar' AND event_id = 1),
    reserved_at = NOW()
WHERE title = 'AirPods Pro' AND event_id = 1;

UPDATE wishlist_items
SET reserved_by_guest_id = (SELECT id FROM guests WHERE name = 'Onkel Peter og familie' AND event_id = 1),
    reserved_at = NOW()
WHERE title = 'Polaroid kamera' AND event_id = 1;

-- Insert menu items
INSERT INTO menu_items (event_id, course, title, description, sort_order) VALUES
(1, 'starter', 'Lakseterrin', 'Hjemmelavet lakseterrin med peberrodscreme og rugbrødschips', 1),
(1, 'starter', 'Hønsesalat', 'Klassisk dansk hønsesalat på salat med frisk brød', 2),
(1, 'main', 'Oksefilet', 'Mør oksefilet med bearnaisesauce, hasselback kartofler og sæsonens grøntsager', 3),
(1, 'main', 'Vegetarret', 'Grillet portobello med quinoa-salat og urte-dressing', 4),
(1, 'dessert', 'Konfirmationskage', 'Lagkage med jordbær og flødeskum', 5),
(1, 'dessert', 'Is med karamelsauce', 'Hjemmelavet vaniljeis med varm karamelsauce', 6),
(1, 'drink', 'Velkomstdrink', 'Hyldeblomst-lemonade eller champagne', 7),
(1, 'drink', 'Kaffe og te', 'Serveres med småkager', 8);

-- Insert schedule items
INSERT INTO schedule_items (event_id, time, title, description, sort_order) VALUES
(1, '11:30:00', 'Gæster ankommer', 'Velkomstdrink serveres i haven', 1),
(1, '12:00:00', 'Velkomst', 'Far byder velkommen og holder tale', 2),
(1, '12:30:00', 'Frokost', 'Smørrebrød og forret serveres', 3),
(1, '14:00:00', 'Kaffe og kage', 'Konfirmationskage skæres', 4),
(1, '14:30:00', 'Gaver', 'Sofie pakker gaver op', 5),
(1, '16:00:00', 'Fri leg', 'Hygge og snak i haven', 6),
(1, '18:00:00', 'Aftensmad', 'Varm ret serveres', 7),
(1, '20:00:00', 'Tak for i dag', 'Afrunding og farvel', 8);

-- Insert budget items
INSERT INTO budget_items (event_id, category, description, estimated, actual, paid, sort_order) VALUES
(1, 'mad', 'Smørrebrød og forret', 2500.00, 0, false, 1),
(1, 'mad', 'Hovedret (oksefilet)', 3500.00, 0, false, 2),
(1, 'mad', 'Konfirmationskage', 1200.00, 0, false, 3),
(1, 'mad', 'Drikkevarer', 1500.00, 0, false, 4),
(1, 'pynt', 'Blomster', 800.00, 0, false, 5),
(1, 'pynt', 'Servietter og duge', 300.00, 0, false, 6),
(1, 'pynt', 'Balloner og pynt', 400.00, 0, false, 7),
(1, 'praktisk', 'Fotograf', 2000.00, 0, false, 8),
(1, 'praktisk', 'Leje af stole/borde', 500.00, 0, false, 9),
(1, 'tøj', 'Sofies konfirmationskjole', 2500.00, 2399.00, true, 10),
(1, 'tøj', 'Sko og accessories', 800.00, 650.00, true, 11);

-- Update paid items
UPDATE budget_items SET paid_date = '2026-03-15' WHERE paid = true AND event_id = 1;

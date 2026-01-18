# Event Platform Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Bygge en elegant event-platform til konfirmationer og fester med invitationer, gæsteliste, huskeliste, ønskeliste, menu, tidsplan, fotoalbum og budget.

**Architecture:** PHP 8+ backend med MySQL database. Vanilla HTML/CSS/JavaScript frontend. Arrangører logger ind med email/password, gæster bruger unik 6-cifret kode. Responsive mobil-først design med valgfrit tema (pige: rosa/guld, dreng: blå/sølv).

**Tech Stack:** PHP 8+, MySQL, HTML5, CSS3 (custom properties for theming), Vanilla JavaScript (ES6+), Google Fonts (Playfair Display, Inter)

---

## Fase 1: Fundament

### Task 1: Opret projektstruktur

**Files:**
- Create: `index.php`
- Create: `config/database.php`
- Create: `includes/functions.php`
- Create: `includes/auth.php`
- Create: `assets/css/main.css`
- Create: `assets/css/theme-girl.css`
- Create: `assets/css/theme-boy.css`
- Create: `assets/js/main.js`
- Create: `admin/index.php`
- Create: `guest/index.php`
- Create: `api/index.php`

**Step 1: Opret mappestruktur**

```bash
mkdir -p config includes assets/css assets/js admin guest api
```

**Step 2: Opret database config**

Create `config/database.php`:
```php
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'events_platform');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
    }
    return $pdo;
}
```

**Step 3: Opret basis functions**

Create `includes/functions.php`:
```php
<?php
function escape(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never {
    header("Location: $url");
    exit;
}

function generateGuestCode(): string {
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function formatDate(string $date): string {
    return date('d. F Y', strtotime($date));
}

function jsonResponse(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
```

**Step 4: Opret auth helper**

Create `includes/auth.php`:
```php
<?php
session_start();

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function isGuest(): bool {
    return isset($_SESSION['guest_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        redirect('/index.php?error=login_required');
    }
}

function requireGuest(): void {
    if (!isGuest() && !isLoggedIn()) {
        redirect('/index.php?error=code_required');
    }
}

function getCurrentEventId(): ?int {
    return $_SESSION['event_id'] ?? null;
}

function login(int $userId, int $eventId): void {
    $_SESSION['user_id'] = $userId;
    $_SESSION['event_id'] = $eventId;
}

function loginGuest(int $guestId, int $eventId): void {
    $_SESSION['guest_id'] = $guestId;
    $_SESSION['event_id'] = $eventId;
}

function logout(): void {
    session_destroy();
}
```

**Step 5: Commit**

```bash
git init
git add .
git commit -m "feat: initial project structure with config, includes, and folders"
```

---

### Task 2: Database schema

**Files:**
- Create: `database/schema.sql`
- Create: `database/seed.sql`

**Step 1: Opret schema fil**

Create `database/schema.sql`:
```sql
-- Events Platform Database Schema

CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    event_date DATE NOT NULL,
    event_time TIME DEFAULT '12:00:00',
    location VARCHAR(255),
    theme ENUM('girl', 'boy') DEFAULT 'girl',
    welcome_text TEXT,
    confirmand_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'organizer', 'confirmand') DEFAULT 'organizer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    UNIQUE KEY unique_email_event (email, event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE guests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(20),
    unique_code CHAR(6) NOT NULL,
    rsvp_status ENUM('pending', 'yes', 'no') DEFAULT 'pending',
    rsvp_date TIMESTAMP NULL,
    adults_count INT DEFAULT 1,
    children_count INT DEFAULT 0,
    dietary_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    UNIQUE KEY unique_code_event (unique_code, event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE wishlist_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2),
    link VARCHAR(500),
    image_url VARCHAR(500),
    reserved_by_guest_id INT NULL,
    purchased BOOLEAN DEFAULT FALSE,
    priority INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (reserved_by_guest_id) REFERENCES guests(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE checklist_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    category VARCHAR(50) DEFAULT 'general',
    task VARCHAR(255) NOT NULL,
    due_date DATE,
    completed BOOLEAN DEFAULT FALSE,
    assigned_to VARCHAR(100),
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    course ENUM('starter', 'main', 'dessert', 'drink', 'snack') NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE schedule_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    time TIME NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    uploaded_by_guest_id INT,
    uploaded_by_user_id INT,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255),
    caption TEXT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by_guest_id) REFERENCES guests(id) ON DELETE SET NULL,
    FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE budget_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    category VARCHAR(50) NOT NULL,
    description VARCHAR(255) NOT NULL,
    estimated DECIMAL(10,2) DEFAULT 0,
    actual DECIMAL(10,2) DEFAULT 0,
    paid BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Indexes for performance
CREATE INDEX idx_guests_event ON guests(event_id);
CREATE INDEX idx_guests_code ON guests(unique_code);
CREATE INDEX idx_wishlist_event ON wishlist_items(event_id);
CREATE INDEX idx_checklist_event ON checklist_items(event_id);
```

**Step 2: Opret seed data**

Create `database/seed.sql`:
```sql
-- Seed data for testing

INSERT INTO events (name, event_date, event_time, location, theme, welcome_text, confirmand_name)
VALUES ('Sofies Konfirmation', '2026-05-10', '12:00:00', 'Hjemme hos os', 'girl',
        'Velkommen til Sofies store dag! Vi glæder os til at fejre sammen med jer.', 'Sofie');

INSERT INTO users (event_id, email, password_hash, name, role)
VALUES (1, 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin');
-- Password: password

INSERT INTO checklist_items (event_id, category, task, due_date, sort_order) VALUES
(1, 'mad', 'Bestil kage', '2026-05-01', 1),
(1, 'mad', 'Planlæg menu', '2026-04-15', 2),
(1, 'mad', 'Køb drikkevarer', '2026-05-08', 3),
(1, 'pynt', 'Køb bordpynt', '2026-05-01', 4),
(1, 'pynt', 'Køb balloner', '2026-05-09', 5),
(1, 'praktisk', 'Send invitationer', '2026-04-01', 6),
(1, 'praktisk', 'Lej ekstra stole', '2026-05-05', 7),
(1, 'praktisk', 'Lav bordplan', '2026-05-07', 8);
```

**Step 3: Commit**

```bash
mkdir -p database
git add database/
git commit -m "feat: add database schema and seed data"
```

---

### Task 3: CSS Theming System

**Files:**
- Create: `assets/css/main.css`
- Create: `assets/css/theme-girl.css`
- Create: `assets/css/theme-boy.css`

**Step 1: Opret main.css med CSS custom properties**

Create `assets/css/main.css`:
```css
/* Reset and Base */
*, *::before, *::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

:root {
    /* Default theme (overridden by theme files) */
    --color-primary: #E8B4B8;
    --color-accent: #D4AF37;
    --color-background: #FFF9F9;
    --color-surface: #FFFFFF;
    --color-text: #2D2D2D;
    --color-text-light: #666666;
    --color-border: #E0E0E0;
    --color-success: #4CAF50;
    --color-warning: #FF9800;
    --color-error: #F44336;

    /* Typography */
    --font-heading: 'Playfair Display', Georgia, serif;
    --font-body: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;

    /* Spacing */
    --space-xs: 0.25rem;
    --space-sm: 0.5rem;
    --space-md: 1rem;
    --space-lg: 1.5rem;
    --space-xl: 2rem;
    --space-xxl: 3rem;

    /* Border radius */
    --radius-sm: 4px;
    --radius-md: 8px;
    --radius-lg: 16px;
    --radius-full: 9999px;

    /* Shadows */
    --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
    --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
    --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
}

/* Typography */
html {
    font-size: 16px;
    line-height: 1.5;
}

body {
    font-family: var(--font-body);
    color: var(--color-text);
    background-color: var(--color-background);
    min-height: 100vh;
}

h1, h2, h3, h4, h5, h6 {
    font-family: var(--font-heading);
    font-weight: 600;
    line-height: 1.2;
    color: var(--color-text);
}

h1 { font-size: 2.5rem; }
h2 { font-size: 2rem; }
h3 { font-size: 1.5rem; }
h4 { font-size: 1.25rem; }

p { margin-bottom: var(--space-md); }

a {
    color: var(--color-primary);
    text-decoration: none;
    transition: color 0.2s;
}

a:hover {
    color: var(--color-accent);
}

/* Layout */
.container {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 var(--space-md);
}

.container--narrow {
    max-width: 600px;
}

/* Cards */
.card {
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-md);
    padding: var(--space-lg);
    margin-bottom: var(--space-lg);
}

.card__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--space-md);
    padding-bottom: var(--space-md);
    border-bottom: 1px solid var(--color-border);
}

.card__title {
    font-size: 1.25rem;
    margin: 0;
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-sm);
    padding: var(--space-sm) var(--space-lg);
    font-family: var(--font-body);
    font-size: 1rem;
    font-weight: 500;
    border: none;
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}

.btn--primary {
    background: var(--color-primary);
    color: white;
}

.btn--primary:hover {
    background: var(--color-accent);
    transform: translateY(-1px);
    box-shadow: var(--shadow-md);
}

.btn--secondary {
    background: transparent;
    color: var(--color-primary);
    border: 2px solid var(--color-primary);
}

.btn--secondary:hover {
    background: var(--color-primary);
    color: white;
}

.btn--large {
    padding: var(--space-md) var(--space-xl);
    font-size: 1.125rem;
}

.btn--block {
    width: 100%;
}

/* Forms */
.form-group {
    margin-bottom: var(--space-lg);
}

.form-label {
    display: block;
    margin-bottom: var(--space-sm);
    font-weight: 500;
    color: var(--color-text);
}

.form-input {
    width: 100%;
    padding: var(--space-sm) var(--space-md);
    font-family: var(--font-body);
    font-size: 1rem;
    border: 2px solid var(--color-border);
    border-radius: var(--radius-md);
    transition: border-color 0.2s, box-shadow 0.2s;
}

.form-input:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(232, 180, 184, 0.2);
}

.form-input--error {
    border-color: var(--color-error);
}

.form-error {
    color: var(--color-error);
    font-size: 0.875rem;
    margin-top: var(--space-xs);
}

/* Badges */
.badge {
    display: inline-flex;
    align-items: center;
    padding: var(--space-xs) var(--space-sm);
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: var(--radius-full);
    text-transform: uppercase;
}

.badge--success {
    background: #E8F5E9;
    color: var(--color-success);
}

.badge--warning {
    background: #FFF3E0;
    color: var(--color-warning);
}

.badge--error {
    background: #FFEBEE;
    color: var(--color-error);
}

.badge--neutral {
    background: #F5F5F5;
    color: var(--color-text-light);
}

/* Stats */
.stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: var(--space-md);
}

.stat {
    text-align: center;
    padding: var(--space-lg);
}

.stat__value {
    font-family: var(--font-heading);
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--color-primary);
}

.stat__label {
    font-size: 0.875rem;
    color: var(--color-text-light);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* Navigation */
.nav {
    background: var(--color-surface);
    box-shadow: var(--shadow-sm);
    padding: var(--space-md) 0;
}

.nav__container {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.nav__brand {
    font-family: var(--font-heading);
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--color-primary);
}

.nav__links {
    display: flex;
    gap: var(--space-lg);
    list-style: none;
}

.nav__link {
    color: var(--color-text);
    font-weight: 500;
    padding: var(--space-sm) 0;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
}

.nav__link:hover,
.nav__link--active {
    color: var(--color-primary);
    border-bottom-color: var(--color-primary);
}

/* Sidebar (Admin) */
.layout {
    display: flex;
    min-height: 100vh;
}

.sidebar {
    width: 260px;
    background: var(--color-surface);
    border-right: 1px solid var(--color-border);
    padding: var(--space-lg);
    position: fixed;
    height: 100vh;
    overflow-y: auto;
}

.sidebar__brand {
    font-family: var(--font-heading);
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--color-primary);
    margin-bottom: var(--space-xl);
    padding-bottom: var(--space-md);
    border-bottom: 1px solid var(--color-border);
}

.sidebar__nav {
    list-style: none;
}

.sidebar__item {
    margin-bottom: var(--space-xs);
}

.sidebar__link {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    padding: var(--space-sm) var(--space-md);
    color: var(--color-text);
    border-radius: var(--radius-md);
    transition: all 0.2s;
}

.sidebar__link:hover {
    background: var(--color-background);
    color: var(--color-primary);
}

.sidebar__link--active {
    background: var(--color-primary);
    color: white;
}

.sidebar__link--active:hover {
    background: var(--color-primary);
    color: white;
}

.main-content {
    flex: 1;
    margin-left: 260px;
    padding: var(--space-xl);
}

/* Tables */
.table-container {
    overflow-x: auto;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th,
.table td {
    padding: var(--space-md);
    text-align: left;
    border-bottom: 1px solid var(--color-border);
}

.table th {
    font-weight: 600;
    color: var(--color-text-light);
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.table tr:hover {
    background: var(--color-background);
}

/* Alerts */
.alert {
    padding: var(--space-md) var(--space-lg);
    border-radius: var(--radius-md);
    margin-bottom: var(--space-lg);
}

.alert--success {
    background: #E8F5E9;
    color: var(--color-success);
    border: 1px solid var(--color-success);
}

.alert--error {
    background: #FFEBEE;
    color: var(--color-error);
    border: 1px solid var(--color-error);
}

.alert--warning {
    background: #FFF3E0;
    color: var(--color-warning);
    border: 1px solid var(--color-warning);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: var(--space-xxl);
    color: var(--color-text-light);
}

.empty-state__icon {
    font-size: 3rem;
    margin-bottom: var(--space-md);
}

.empty-state__title {
    font-size: 1.25rem;
    margin-bottom: var(--space-sm);
    color: var(--color-text);
}

/* Loading */
.loading {
    display: flex;
    justify-content: center;
    padding: var(--space-xl);
}

.spinner {
    width: 40px;
    height: 40px;
    border: 3px solid var(--color-border);
    border-top-color: var(--color-primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Modal */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: var(--space-md);
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.2s;
}

.modal-overlay--active {
    opacity: 1;
    visibility: visible;
}

.modal {
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg);
    width: 100%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    transform: translateY(-20px);
    transition: transform 0.2s;
}

.modal-overlay--active .modal {
    transform: translateY(0);
}

.modal__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--space-lg);
    border-bottom: 1px solid var(--color-border);
}

.modal__title {
    font-size: 1.25rem;
    margin: 0;
}

.modal__close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--color-text-light);
    padding: var(--space-xs);
}

.modal__body {
    padding: var(--space-lg);
}

.modal__footer {
    display: flex;
    justify-content: flex-end;
    gap: var(--space-sm);
    padding: var(--space-lg);
    border-top: 1px solid var(--color-border);
}

/* Responsive */
@media (max-width: 768px) {
    h1 { font-size: 2rem; }
    h2 { font-size: 1.5rem; }

    .sidebar {
        transform: translateX(-100%);
        z-index: 100;
        transition: transform 0.3s;
    }

    .sidebar--open {
        transform: translateX(0);
    }

    .main-content {
        margin-left: 0;
    }

    .nav__links {
        display: none;
    }

    .stats {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Utility Classes */
.text-center { text-align: center; }
.text-right { text-align: right; }
.text-muted { color: var(--color-text-light); }
.text-success { color: var(--color-success); }
.text-error { color: var(--color-error); }
.mt-sm { margin-top: var(--space-sm); }
.mt-md { margin-top: var(--space-md); }
.mt-lg { margin-top: var(--space-lg); }
.mb-sm { margin-bottom: var(--space-sm); }
.mb-md { margin-bottom: var(--space-md); }
.mb-lg { margin-bottom: var(--space-lg); }
.hidden { display: none; }
.flex { display: flex; }
.flex-between { justify-content: space-between; }
.flex-center { justify-content: center; align-items: center; }
.gap-sm { gap: var(--space-sm); }
.gap-md { gap: var(--space-md); }
```

**Step 2: Opret theme-girl.css**

Create `assets/css/theme-girl.css`:
```css
/* Girl Theme - Rosa/Guld */
:root {
    --color-primary: #E8B4B8;
    --color-primary-dark: #D49CA1;
    --color-accent: #D4AF37;
    --color-accent-light: #F4E4A6;
    --color-background: #FFF9F9;
    --color-surface: #FFFFFF;
    --color-text: #2D2D2D;
    --color-text-light: #666666;
    --color-border: #F0E0E2;
}

/* Decorative elements */
.theme-decoration {
    background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-accent) 100%);
}

.hero {
    background: linear-gradient(180deg, var(--color-background) 0%, #FFF0F2 100%);
}

/* Button hover with gold accent */
.btn--primary:hover {
    background: var(--color-accent);
}

/* Form focus with theme color */
.form-input:focus {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(232, 180, 184, 0.3);
}
```

**Step 3: Opret theme-boy.css**

Create `assets/css/theme-boy.css`:
```css
/* Boy Theme - Blå/Sølv */
:root {
    --color-primary: #4A6FA5;
    --color-primary-dark: #3A5A8A;
    --color-accent: #8BA4C4;
    --color-accent-light: #C5D4E8;
    --color-background: #F5F8FC;
    --color-surface: #FFFFFF;
    --color-text: #2D2D2D;
    --color-text-light: #666666;
    --color-border: #DCE4EF;
}

/* Decorative elements */
.theme-decoration {
    background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-accent) 100%);
}

.hero {
    background: linear-gradient(180deg, var(--color-background) 0%, #E8F0F8 100%);
}

/* Button hover with silver accent */
.btn--primary:hover {
    background: var(--color-primary-dark);
}

/* Form focus with theme color */
.form-input:focus {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(74, 111, 165, 0.2);
}
```

**Step 4: Commit**

```bash
git add assets/css/
git commit -m "feat: add CSS theming system with girl/boy themes"
```

---

### Task 4: Landing Page

**Files:**
- Create: `index.php`
- Create: `assets/js/main.js`

**Step 1: Opret landing page**

Create `index.php`:
```php
<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Get event (for now, just get the first one)
$db = getDB();
$stmt = $db->query("SELECT * FROM events ORDER BY id LIMIT 1");
$event = $stmt->fetch();

if (!$event) {
    die('Ingen event fundet. Kør seed.sql først.');
}

$theme = $event['theme'] ?? 'girl';
$error = $_GET['error'] ?? null;
$success = $_GET['success'] ?? null;

// Handle guest code submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guest_code'])) {
    $code = trim($_POST['guest_code']);
    $stmt = $db->prepare("SELECT * FROM guests WHERE unique_code = ? AND event_id = ?");
    $stmt->execute([$code, $event['id']]);
    $guest = $stmt->fetch();

    if ($guest) {
        loginGuest($guest['id'], $event['id']);
        redirect('/guest/index.php');
    } else {
        $error = 'invalid_code';
    }
}

// Handle organizer login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND event_id = ?");
    $stmt->execute([$email, $event['id']]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        login($user['id'], $event['id']);
        redirect('/admin/index.php');
    } else {
        $error = 'invalid_credentials';
    }
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($event['name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/theme-<?= escape($theme) ?>.css">
    <style>
        .landing {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: var(--space-xl);
            text-align: center;
        }

        .landing__icon {
            font-size: 4rem;
            margin-bottom: var(--space-lg);
        }

        .landing__title {
            font-size: 3rem;
            margin-bottom: var(--space-sm);
            color: var(--color-primary);
        }

        .landing__subtitle {
            font-size: 1.25rem;
            color: var(--color-text-light);
            margin-bottom: var(--space-md);
        }

        .landing__date {
            font-family: var(--font-heading);
            font-size: 1.5rem;
            color: var(--color-accent);
            margin-bottom: var(--space-xxl);
        }

        .landing__welcome {
            max-width: 500px;
            margin-bottom: var(--space-xxl);
            line-height: 1.7;
        }

        .landing__cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: var(--space-lg);
            width: 100%;
            max-width: 700px;
        }

        .login-card {
            text-align: left;
        }

        .login-card__title {
            font-size: 1.125rem;
            margin-bottom: var(--space-md);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .code-input {
            font-size: 1.5rem;
            text-align: center;
            letter-spacing: 0.5em;
            padding: var(--space-md);
        }

        .divider {
            display: flex;
            align-items: center;
            gap: var(--space-md);
            margin: var(--space-xl) 0;
            color: var(--color-text-light);
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--color-border);
        }
    </style>
</head>
<body class="hero">
    <main class="landing">
        <div class="landing__icon">✨</div>

        <h1 class="landing__title"><?= escape($event['confirmand_name']) ?></h1>
        <p class="landing__subtitle"><?= escape($event['name']) ?></p>
        <p class="landing__date"><?= formatDate($event['event_date']) ?></p>

        <?php if ($event['welcome_text']): ?>
            <p class="landing__welcome"><?= escape($event['welcome_text']) ?></p>
        <?php endif; ?>

        <?php if ($error === 'invalid_code'): ?>
            <div class="alert alert--error">Ugyldig kode. Prøv igen.</div>
        <?php elseif ($error === 'invalid_credentials'): ?>
            <div class="alert alert--error">Forkert email eller adgangskode.</div>
        <?php endif; ?>

        <div class="landing__cards">
            <div class="card login-card">
                <h2 class="login-card__title">🎉 Gæst</h2>
                <p class="text-muted mb-md">Indtast din personlige kode fra invitationen</p>
                <form method="POST">
                    <div class="form-group">
                        <input type="text"
                               name="guest_code"
                               class="form-input code-input"
                               placeholder="000000"
                               maxlength="6"
                               pattern="[0-9]{6}"
                               required
                               autocomplete="off">
                    </div>
                    <button type="submit" class="btn btn--primary btn--block">Fortsæt</button>
                </form>
            </div>

            <div class="card login-card">
                <h2 class="login-card__title">⚙️ Arrangør</h2>
                <p class="text-muted mb-md">Log ind for at administrere eventet</p>
                <form method="POST">
                    <div class="form-group">
                        <input type="email"
                               name="email"
                               class="form-input"
                               placeholder="Email"
                               required>
                    </div>
                    <div class="form-group">
                        <input type="password"
                               name="password"
                               class="form-input"
                               placeholder="Adgangskode"
                               required>
                    </div>
                    <button type="submit" class="btn btn--secondary btn--block">Log ind</button>
                </form>
            </div>
        </div>
    </main>

    <script src="/assets/js/main.js"></script>
</body>
</html>
```

**Step 2: Opret main.js**

Create `assets/js/main.js`:
```javascript
// Main JavaScript file

document.addEventListener('DOMContentLoaded', function() {
    // Auto-format guest code input
    const codeInput = document.querySelector('.code-input');
    if (codeInput) {
        codeInput.addEventListener('input', function(e) {
            // Only allow numbers
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Auto-submit when 6 digits entered
        codeInput.addEventListener('keyup', function(e) {
            if (this.value.length === 6) {
                this.form.submit();
            }
        });
    }

    // Mobile sidebar toggle
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('sidebar--open');
        });
    }

    // Modal handling
    window.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('modal-overlay--active');
            document.body.style.overflow = 'hidden';
        }
    };

    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('modal-overlay--active');
            document.body.style.overflow = '';
        }
    };

    // Close modal on overlay click
    document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('modal-overlay--active');
                document.body.style.overflow = '';
            }
        });
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay--active').forEach(function(modal) {
                modal.classList.remove('modal-overlay--active');
            });
            document.body.style.overflow = '';
        }
    });

    // Confirm dialogs
    window.confirmAction = function(message, callback) {
        if (confirm(message)) {
            callback();
        }
    };

    // Toast notifications
    window.showToast = function(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `alert alert--${type}`;
        toast.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:1001;animation:slideIn 0.3s ease';
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(function() {
            toast.style.animation = 'slideOut 0.3s ease';
            setTimeout(function() {
                toast.remove();
            }, 300);
        }, 3000);
    };
});

// Utility functions
function formatNumber(num) {
    return new Intl.NumberFormat('da-DK').format(num);
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('da-DK', {
        style: 'currency',
        currency: 'DKK',
        minimumFractionDigits: 0
    }).format(amount);
}

// Form validation helper
function validateForm(form) {
    let isValid = true;
    form.querySelectorAll('[required]').forEach(function(input) {
        if (!input.value.trim()) {
            input.classList.add('form-input--error');
            isValid = false;
        } else {
            input.classList.remove('form-input--error');
        }
    });
    return isValid;
}

// AJAX helper
async function api(endpoint, options = {}) {
    const response = await fetch('/api/' + endpoint, {
        headers: {
            'Content-Type': 'application/json',
            ...options.headers
        },
        ...options
    });

    if (!response.ok) {
        throw new Error('API request failed');
    }

    return response.json();
}
```

**Step 3: Commit**

```bash
git add index.php assets/js/
git commit -m "feat: add landing page with guest code and organizer login"
```

---

## Fase 2: Admin Dashboard (Prioritet 1, 2, 5)

### Task 5: Admin Layout og Navigation

**Files:**
- Create: `admin/index.php`
- Create: `includes/admin-header.php`
- Create: `includes/admin-sidebar.php`

**Step 1: Opret admin header**

Create `includes/admin-header.php`:
```php
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

requireLogin();

$db = getDB();
$eventId = getCurrentEventId();

// Get event details
$stmt = $db->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) {
    redirect('/index.php');
}

$theme = $event['theme'] ?? 'girl';

// Get current user
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch();

// Get quick stats
$stmt = $db->prepare("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN rsvp_status = 'yes' THEN 1 ELSE 0 END) as confirmed,
    SUM(CASE WHEN rsvp_status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN rsvp_status = 'yes' THEN adults_count ELSE 0 END) as adults,
    SUM(CASE WHEN rsvp_status = 'yes' THEN children_count ELSE 0 END) as children
    FROM guests WHERE event_id = ?");
$stmt->execute([$eventId]);
$guestStats = $stmt->fetch();

// Current page for active nav
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($event['name']) ?> - Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/theme-<?= escape($theme) ?>.css">
</head>
<body>
    <div class="layout">
```

**Step 2: Opret admin sidebar**

Create `includes/admin-sidebar.php`:
```php
<aside class="sidebar">
    <div class="sidebar__brand">
        <?= escape($event['confirmand_name']) ?>
        <span class="text-muted" style="font-size: 0.75rem; display: block; font-weight: 400;">
            <?= formatDate($event['event_date']) ?>
        </span>
    </div>

    <nav>
        <ul class="sidebar__nav">
            <li class="sidebar__item">
                <a href="/admin/index.php" class="sidebar__link <?= $currentPage === 'index' ? 'sidebar__link--active' : '' ?>">
                    📊 Overblik
                </a>
            </li>
            <li class="sidebar__item">
                <a href="/admin/guests.php" class="sidebar__link <?= $currentPage === 'guests' ? 'sidebar__link--active' : '' ?>">
                    👥 Gæsteliste
                </a>
            </li>
            <li class="sidebar__item">
                <a href="/admin/checklist.php" class="sidebar__link <?= $currentPage === 'checklist' ? 'sidebar__link--active' : '' ?>">
                    ✅ Huskeliste
                </a>
            </li>
            <li class="sidebar__item">
                <a href="/admin/wishlist.php" class="sidebar__link <?= $currentPage === 'wishlist' ? 'sidebar__link--active' : '' ?>">
                    🎁 Ønskeliste
                </a>
            </li>
            <li class="sidebar__item">
                <a href="/admin/menu.php" class="sidebar__link <?= $currentPage === 'menu' ? 'sidebar__link--active' : '' ?>">
                    🍽️ Menu
                </a>
            </li>
            <li class="sidebar__item">
                <a href="/admin/schedule.php" class="sidebar__link <?= $currentPage === 'schedule' ? 'sidebar__link--active' : '' ?>">
                    🕐 Tidsplan
                </a>
            </li>
            <li class="sidebar__item">
                <a href="/admin/photos.php" class="sidebar__link <?= $currentPage === 'photos' ? 'sidebar__link--active' : '' ?>">
                    📷 Billeder
                </a>
            </li>
            <li class="sidebar__item">
                <a href="/admin/budget.php" class="sidebar__link <?= $currentPage === 'budget' ? 'sidebar__link--active' : '' ?>">
                    💰 Budget
                </a>
            </li>
            <li class="sidebar__item">
                <a href="/admin/settings.php" class="sidebar__link <?= $currentPage === 'settings' ? 'sidebar__link--active' : '' ?>">
                    ⚙️ Indstillinger
                </a>
            </li>
        </ul>
    </nav>

    <div style="margin-top: auto; padding-top: var(--space-xl); border-top: 1px solid var(--color-border);">
        <p class="text-muted" style="font-size: 0.875rem;">
            Logget ind som<br>
            <strong><?= escape($currentUser['name']) ?></strong>
        </p>
        <a href="/admin/logout.php" class="btn btn--secondary btn--block mt-md" style="font-size: 0.875rem;">
            Log ud
        </a>
    </div>
</aside>
```

**Step 3: Opret admin dashboard**

Create `admin/index.php`:
```php
<?php require_once __DIR__ . '/../includes/admin-header.php'; ?>
<?php require_once __DIR__ . '/../includes/admin-sidebar.php'; ?>

<main class="main-content">
    <h1 class="mb-lg">Overblik</h1>

    <!-- Quick Stats -->
    <div class="card">
        <div class="stats">
            <div class="stat">
                <div class="stat__value"><?= $guestStats['confirmed'] ?></div>
                <div class="stat__label">Bekræftet</div>
            </div>
            <div class="stat">
                <div class="stat__value"><?= $guestStats['pending'] ?></div>
                <div class="stat__label">Afventer svar</div>
            </div>
            <div class="stat">
                <div class="stat__value"><?= $guestStats['adults'] + $guestStats['children'] ?></div>
                <div class="stat__label">Gæster i alt</div>
            </div>
            <div class="stat">
                <div class="stat__value"><?= $guestStats['adults'] ?> + <?= $guestStats['children'] ?></div>
                <div class="stat__label">Voksne + Børn</div>
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: var(--space-lg);">
        <!-- Recent RSVPs -->
        <div class="card">
            <div class="card__header">
                <h2 class="card__title">Seneste svar</h2>
                <a href="/admin/guests.php" class="btn btn--secondary" style="font-size: 0.875rem;">Se alle</a>
            </div>
            <?php
            $stmt = $db->prepare("SELECT * FROM guests WHERE event_id = ? AND rsvp_status != 'pending' ORDER BY rsvp_date DESC LIMIT 5");
            $stmt->execute([$eventId]);
            $recentGuests = $stmt->fetchAll();
            ?>
            <?php if (empty($recentGuests)): ?>
                <div class="empty-state">
                    <p>Ingen svar endnu</p>
                </div>
            <?php else: ?>
                <ul style="list-style: none;">
                    <?php foreach ($recentGuests as $guest): ?>
                        <li style="display: flex; justify-content: space-between; align-items: center; padding: var(--space-sm) 0; border-bottom: 1px solid var(--color-border);">
                            <span><?= escape($guest['name']) ?></span>
                            <?php if ($guest['rsvp_status'] === 'yes'): ?>
                                <span class="badge badge--success">Kommer</span>
                            <?php else: ?>
                                <span class="badge badge--error">Kommer ikke</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- Upcoming Tasks -->
        <div class="card">
            <div class="card__header">
                <h2 class="card__title">Kommende opgaver</h2>
                <a href="/admin/checklist.php" class="btn btn--secondary" style="font-size: 0.875rem;">Se alle</a>
            </div>
            <?php
            $stmt = $db->prepare("SELECT * FROM checklist_items WHERE event_id = ? AND completed = 0 ORDER BY due_date ASC LIMIT 5");
            $stmt->execute([$eventId]);
            $upcomingTasks = $stmt->fetchAll();
            ?>
            <?php if (empty($upcomingTasks)): ?>
                <div class="empty-state">
                    <p>Ingen opgaver</p>
                </div>
            <?php else: ?>
                <ul style="list-style: none;">
                    <?php foreach ($upcomingTasks as $task): ?>
                        <li style="display: flex; justify-content: space-between; align-items: center; padding: var(--space-sm) 0; border-bottom: 1px solid var(--color-border);">
                            <span><?= escape($task['task']) ?></span>
                            <?php if ($task['due_date']): ?>
                                <span class="text-muted" style="font-size: 0.875rem;">
                                    <?= date('d/m', strtotime($task['due_date'])) ?>
                                </span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card mt-lg">
        <h2 class="card__title mb-md">Hurtige handlinger</h2>
        <div class="flex gap-md" style="flex-wrap: wrap;">
            <a href="/admin/guests.php?action=add" class="btn btn--primary">+ Tilføj gæst</a>
            <a href="/admin/checklist.php?action=add" class="btn btn--secondary">+ Ny opgave</a>
            <a href="/admin/wishlist.php?action=add" class="btn btn--secondary">+ Nyt ønske</a>
        </div>
    </div>
</main>

</div>
<script src="/assets/js/main.js"></script>
</body>
</html>
```

**Step 4: Opret logout**

Create `admin/logout.php`:
```php
<?php
require_once __DIR__ . '/../includes/auth.php';
logout();
redirect('/index.php');
```

**Step 5: Commit**

```bash
git add admin/ includes/admin-*.php
git commit -m "feat: add admin dashboard with sidebar and stats"
```

---

### Task 6: Gæsteliste (CRUD)

**Files:**
- Create: `admin/guests.php`
- Create: `api/guests.php`

**Step 1: Opret guests admin side**

Create `admin/guests.php`:
```php
<?php require_once __DIR__ . '/../includes/admin-header.php'; ?>
<?php require_once __DIR__ . '/../includes/admin-sidebar.php'; ?>

<?php
// Handle form submissions
$message = null;
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($name) {
            $code = generateGuestCode();
            // Ensure unique code
            $stmt = $db->prepare("SELECT id FROM guests WHERE unique_code = ? AND event_id = ?");
            $stmt->execute([$code, $eventId]);
            while ($stmt->fetch()) {
                $code = generateGuestCode();
                $stmt->execute([$code, $eventId]);
            }

            $stmt = $db->prepare("INSERT INTO guests (event_id, name, email, phone, unique_code) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$eventId, $name, $email ?: null, $phone ?: null, $code]);
            $message = "Gæst tilføjet! Kode: $code";
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM guests WHERE id = ? AND event_id = ?");
        $stmt->execute([$id, $eventId]);
        $message = "Gæst slettet";
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($name && $id) {
            $stmt = $db->prepare("UPDATE guests SET name = ?, email = ?, phone = ? WHERE id = ? AND event_id = ?");
            $stmt->execute([$name, $email ?: null, $phone ?: null, $id, $eventId]);
            $message = "Gæst opdateret";
        }
    }
}

// Get filter
$filter = $_GET['filter'] ?? 'all';
$whereClause = "event_id = ?";
$params = [$eventId];

if ($filter === 'yes') {
    $whereClause .= " AND rsvp_status = 'yes'";
} elseif ($filter === 'no') {
    $whereClause .= " AND rsvp_status = 'no'";
} elseif ($filter === 'pending') {
    $whereClause .= " AND rsvp_status = 'pending'";
}

$stmt = $db->prepare("SELECT * FROM guests WHERE $whereClause ORDER BY name ASC");
$stmt->execute($params);
$guests = $stmt->fetchAll();
?>

<main class="main-content">
    <div class="flex flex-between mb-lg">
        <h1>Gæsteliste</h1>
        <button onclick="openModal('add-guest-modal')" class="btn btn--primary">+ Tilføj gæst</button>
    </div>

    <?php if ($message): ?>
        <div class="alert alert--<?= $messageType ?>"><?= escape($message) ?></div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card">
        <div class="flex gap-sm" style="flex-wrap: wrap;">
            <a href="?filter=all" class="btn <?= $filter === 'all' ? 'btn--primary' : 'btn--secondary' ?>">
                Alle (<?= $guestStats['total'] ?>)
            </a>
            <a href="?filter=yes" class="btn <?= $filter === 'yes' ? 'btn--primary' : 'btn--secondary' ?>">
                Kommer (<?= $guestStats['confirmed'] ?>)
            </a>
            <a href="?filter=pending" class="btn <?= $filter === 'pending' ? 'btn--primary' : 'btn--secondary' ?>">
                Afventer (<?= $guestStats['pending'] ?>)
            </a>
            <a href="?filter=no" class="btn <?= $filter === 'no' ? 'btn--primary' : 'btn--secondary' ?>">
                Kommer ikke
            </a>
        </div>
    </div>

    <!-- Guest Table -->
    <div class="card">
        <?php if (empty($guests)): ?>
            <div class="empty-state">
                <div class="empty-state__icon">👥</div>
                <h3 class="empty-state__title">Ingen gæster endnu</h3>
                <p>Tilføj din første gæst for at komme i gang</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Navn</th>
                            <th>Kode</th>
                            <th>Status</th>
                            <th>Antal</th>
                            <th>Kostbehov</th>
                            <th>Handlinger</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($guests as $guest): ?>
                            <tr>
                                <td>
                                    <strong><?= escape($guest['name']) ?></strong>
                                    <?php if ($guest['email']): ?>
                                        <br><span class="text-muted" style="font-size: 0.875rem;"><?= escape($guest['email']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <code style="background: var(--color-background); padding: 2px 8px; border-radius: 4px;">
                                        <?= escape($guest['unique_code']) ?>
                                    </code>
                                </td>
                                <td>
                                    <?php if ($guest['rsvp_status'] === 'yes'): ?>
                                        <span class="badge badge--success">Kommer</span>
                                    <?php elseif ($guest['rsvp_status'] === 'no'): ?>
                                        <span class="badge badge--error">Kommer ikke</span>
                                    <?php else: ?>
                                        <span class="badge badge--neutral">Afventer</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($guest['rsvp_status'] === 'yes'): ?>
                                        <?= $guest['adults_count'] ?> voksne
                                        <?php if ($guest['children_count'] > 0): ?>
                                            + <?= $guest['children_count'] ?> børn
                                        <?php endif; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $guest['dietary_notes'] ? escape($guest['dietary_notes']) : '-' ?>
                                </td>
                                <td>
                                    <button onclick="editGuest(<?= htmlspecialchars(json_encode($guest)) ?>)"
                                            class="btn btn--secondary" style="padding: 4px 12px; font-size: 0.875rem;">
                                        Rediger
                                    </button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Er du sikker?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $guest['id'] ?>">
                                        <button type="submit" class="btn btn--secondary" style="padding: 4px 12px; font-size: 0.875rem; color: var(--color-error);">
                                            Slet
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Add Guest Modal -->
<div id="add-guest-modal" class="modal-overlay">
    <div class="modal">
        <div class="modal__header">
            <h2 class="modal__title">Tilføj gæst</h2>
            <button class="modal__close" onclick="closeModal('add-guest-modal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal__body">
                <div class="form-group">
                    <label class="form-label">Navn *</label>
                    <input type="text" name="name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Telefon</label>
                    <input type="tel" name="phone" class="form-input">
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--secondary" onclick="closeModal('add-guest-modal')">Annuller</button>
                <button type="submit" class="btn btn--primary">Tilføj</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Guest Modal -->
<div id="edit-guest-modal" class="modal-overlay">
    <div class="modal">
        <div class="modal__header">
            <h2 class="modal__title">Rediger gæst</h2>
            <button class="modal__close" onclick="closeModal('edit-guest-modal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit-guest-id">
            <div class="modal__body">
                <div class="form-group">
                    <label class="form-label">Navn *</label>
                    <input type="text" name="name" id="edit-guest-name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="edit-guest-email" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Telefon</label>
                    <input type="tel" name="phone" id="edit-guest-phone" class="form-input">
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--secondary" onclick="closeModal('edit-guest-modal')">Annuller</button>
                <button type="submit" class="btn btn--primary">Gem</button>
            </div>
        </form>
    </div>
</div>

<script>
function editGuest(guest) {
    document.getElementById('edit-guest-id').value = guest.id;
    document.getElementById('edit-guest-name').value = guest.name;
    document.getElementById('edit-guest-email').value = guest.email || '';
    document.getElementById('edit-guest-phone').value = guest.phone || '';
    openModal('edit-guest-modal');
}
</script>

</div>
<script src="/assets/js/main.js"></script>
</body>
</html>
```

**Step 2: Commit**

```bash
git add admin/guests.php
git commit -m "feat: add guest list with CRUD operations"
```

---

### Task 7: Huskeliste (CRUD)

**Files:**
- Create: `admin/checklist.php`

**Step 1: Opret checklist admin side**

Create `admin/checklist.php`:
```php
<?php require_once __DIR__ . '/../includes/admin-header.php'; ?>
<?php require_once __DIR__ . '/../includes/admin-sidebar.php'; ?>

<?php
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $task = trim($_POST['task'] ?? '');
        $category = trim($_POST['category'] ?? 'general');
        $dueDate = $_POST['due_date'] ?? null;
        $assignedTo = trim($_POST['assigned_to'] ?? '');

        if ($task) {
            $stmt = $db->prepare("INSERT INTO checklist_items (event_id, task, category, due_date, assigned_to) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$eventId, $task, $category, $dueDate ?: null, $assignedTo ?: null]);
            $message = "Opgave tilføjet";
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("UPDATE checklist_items SET completed = NOT completed WHERE id = ? AND event_id = ?");
        $stmt->execute([$id, $eventId]);
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM checklist_items WHERE id = ? AND event_id = ?");
        $stmt->execute([$id, $eventId]);
        $message = "Opgave slettet";
    }
}

// Get tasks grouped by category
$stmt = $db->prepare("SELECT * FROM checklist_items WHERE event_id = ? ORDER BY completed ASC, due_date ASC, sort_order ASC");
$stmt->execute([$eventId]);
$allTasks = $stmt->fetchAll();

$tasksByCategory = [];
foreach ($allTasks as $task) {
    $cat = $task['category'] ?: 'general';
    $tasksByCategory[$cat][] = $task;
}

$categories = [
    'mad' => '🍽️ Mad & Drikke',
    'pynt' => '🎈 Pynt & Dekoration',
    'praktisk' => '📋 Praktisk',
    'underholdning' => '🎉 Underholdning',
    'general' => '📌 Generelt'
];

// Stats
$totalTasks = count($allTasks);
$completedTasks = count(array_filter($allTasks, fn($t) => $t['completed']));
?>

<main class="main-content">
    <div class="flex flex-between mb-lg">
        <h1>Huskeliste</h1>
        <button onclick="openModal('add-task-modal')" class="btn btn--primary">+ Ny opgave</button>
    </div>

    <?php if ($message): ?>
        <div class="alert alert--success"><?= escape($message) ?></div>
    <?php endif; ?>

    <!-- Progress -->
    <div class="card">
        <div class="flex flex-between mb-md">
            <span><?= $completedTasks ?> af <?= $totalTasks ?> opgaver fuldført</span>
            <span><?= $totalTasks > 0 ? round($completedTasks / $totalTasks * 100) : 0 ?>%</span>
        </div>
        <div style="height: 8px; background: var(--color-border); border-radius: 4px; overflow: hidden;">
            <div style="height: 100%; width: <?= $totalTasks > 0 ? ($completedTasks / $totalTasks * 100) : 0 ?>%; background: var(--color-primary); transition: width 0.3s;"></div>
        </div>
    </div>

    <!-- Tasks by Category -->
    <?php foreach ($categories as $catKey => $catLabel): ?>
        <?php if (isset($tasksByCategory[$catKey])): ?>
            <div class="card">
                <h2 class="card__title mb-md"><?= $catLabel ?></h2>
                <ul style="list-style: none;">
                    <?php foreach ($tasksByCategory[$catKey] as $task): ?>
                        <li style="display: flex; align-items: center; gap: var(--space-md); padding: var(--space-sm) 0; border-bottom: 1px solid var(--color-border); <?= $task['completed'] ? 'opacity: 0.5;' : '' ?>">
                            <form method="POST" style="display: flex; align-items: center;">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= $task['id'] ?>">
                                <button type="submit" style="background: none; border: 2px solid var(--color-border); width: 24px; height: 24px; border-radius: 4px; cursor: pointer; display: flex; align-items: center; justify-content: center; <?= $task['completed'] ? 'background: var(--color-success); border-color: var(--color-success); color: white;' : '' ?>">
                                    <?= $task['completed'] ? '✓' : '' ?>
                                </button>
                            </form>
                            <span style="flex: 1; <?= $task['completed'] ? 'text-decoration: line-through;' : '' ?>">
                                <?= escape($task['task']) ?>
                            </span>
                            <?php if ($task['assigned_to']): ?>
                                <span class="badge badge--neutral"><?= escape($task['assigned_to']) ?></span>
                            <?php endif; ?>
                            <?php if ($task['due_date']): ?>
                                <?php
                                $dueDate = strtotime($task['due_date']);
                                $isOverdue = $dueDate < time() && !$task['completed'];
                                ?>
                                <span class="<?= $isOverdue ? 'text-error' : 'text-muted' ?>" style="font-size: 0.875rem;">
                                    <?= date('d/m', $dueDate) ?>
                                </span>
                            <?php endif; ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Slet denne opgave?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $task['id'] ?>">
                                <button type="submit" style="background: none; border: none; color: var(--color-text-light); cursor: pointer; padding: 4px;">
                                    🗑️
                                </button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php if (empty($allTasks)): ?>
        <div class="card">
            <div class="empty-state">
                <div class="empty-state__icon">✅</div>
                <h3 class="empty-state__title">Ingen opgaver endnu</h3>
                <p>Tilføj opgaver for at holde styr på alt det praktiske</p>
            </div>
        </div>
    <?php endif; ?>
</main>

<!-- Add Task Modal -->
<div id="add-task-modal" class="modal-overlay">
    <div class="modal">
        <div class="modal__header">
            <h2 class="modal__title">Ny opgave</h2>
            <button class="modal__close" onclick="closeModal('add-task-modal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal__body">
                <div class="form-group">
                    <label class="form-label">Opgave *</label>
                    <input type="text" name="task" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Kategori</label>
                    <select name="category" class="form-input">
                        <?php foreach ($categories as $key => $label): ?>
                            <option value="<?= $key ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Deadline</label>
                    <input type="date" name="due_date" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Ansvarlig</label>
                    <input type="text" name="assigned_to" class="form-input" placeholder="F.eks. Mor, Far, Sofie">
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--secondary" onclick="closeModal('add-task-modal')">Annuller</button>
                <button type="submit" class="btn btn--primary">Tilføj</button>
            </div>
        </form>
    </div>
</div>

</div>
<script src="/assets/js/main.js"></script>
</body>
</html>
```

**Step 2: Commit**

```bash
git add admin/checklist.php
git commit -m "feat: add checklist with categories and progress tracking"
```

---

## Fase 3: Gæste-visning

### Task 8: Gæste RSVP flow

**Files:**
- Create: `guest/index.php`
- Create: `guest/rsvp.php`
- Create: `includes/guest-header.php`

**Step 1: Opret guest header**

Create `includes/guest-header.php`:
```php
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

requireGuest();

$db = getDB();
$eventId = getCurrentEventId();
$guestId = $_SESSION['guest_id'] ?? null;

// Get event
$stmt = $db->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch();

// Get guest
$guest = null;
if ($guestId) {
    $stmt = $db->prepare("SELECT * FROM guests WHERE id = ?");
    $stmt->execute([$guestId]);
    $guest = $stmt->fetch();
}

$theme = $event['theme'] ?? 'girl';
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($event['name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/theme-<?= escape($theme) ?>.css">
    <style>
        .guest-nav {
            background: var(--color-surface);
            padding: var(--space-md);
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .guest-nav__inner {
            max-width: 600px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .guest-content {
            max-width: 600px;
            margin: 0 auto;
            padding: var(--space-xl) var(--space-md);
        }
    </style>
</head>
<body>
    <nav class="guest-nav">
        <div class="guest-nav__inner">
            <span style="font-family: var(--font-heading); color: var(--color-primary);">
                <?= escape($event['confirmand_name']) ?>
            </span>
            <span class="text-muted">
                Hej, <?= escape($guest['name'] ?? 'Gæst') ?>
            </span>
        </div>
    </nav>
```

**Step 2: Opret guest index**

Create `guest/index.php`:
```php
<?php require_once __DIR__ . '/../includes/guest-header.php'; ?>

<main class="guest-content">
    <div class="text-center mb-lg">
        <div style="font-size: 3rem; margin-bottom: var(--space-md);">✨</div>
        <h1 style="color: var(--color-primary);"><?= escape($event['confirmand_name']) ?></h1>
        <p class="text-muted"><?= escape($event['name']) ?></p>
        <p style="font-family: var(--font-heading); font-size: 1.25rem; color: var(--color-accent); margin-top: var(--space-sm);">
            <?= formatDate($event['event_date']) ?>
            <?php if ($event['event_time']): ?>
                kl. <?= date('H:i', strtotime($event['event_time'])) ?>
            <?php endif; ?>
        </p>
        <?php if ($event['location']): ?>
            <p class="text-muted mt-sm">📍 <?= escape($event['location']) ?></p>
        <?php endif; ?>
    </div>

    <?php if ($event['welcome_text']): ?>
        <div class="card text-center">
            <p style="line-height: 1.7;"><?= escape($event['welcome_text']) ?></p>
        </div>
    <?php endif; ?>

    <!-- RSVP Status -->
    <div class="card">
        <h2 class="card__title mb-md">Din tilmelding</h2>

        <?php if ($guest['rsvp_status'] === 'pending'): ?>
            <p class="mb-md">Vi har endnu ikke modtaget dit svar. Vil du komme til festen?</p>
            <div class="flex gap-md">
                <a href="/guest/rsvp.php" class="btn btn--primary btn--large" style="flex: 1;">
                    Ja, jeg kommer! 🎉
                </a>
                <a href="/guest/rsvp.php?decline=1" class="btn btn--secondary btn--large" style="flex: 1;">
                    Jeg kan desværre ikke
                </a>
            </div>
        <?php elseif ($guest['rsvp_status'] === 'yes'): ?>
            <div class="alert alert--success">
                ✅ Du har bekræftet din deltagelse!
            </div>
            <p>
                <strong>Antal:</strong> <?= $guest['adults_count'] ?> voksen(e)
                <?php if ($guest['children_count'] > 0): ?>
                    og <?= $guest['children_count'] ?> barn/børn
                <?php endif; ?>
            </p>
            <?php if ($guest['dietary_notes']): ?>
                <p><strong>Kostbehov:</strong> <?= escape($guest['dietary_notes']) ?></p>
            <?php endif; ?>
            <a href="/guest/rsvp.php" class="btn btn--secondary mt-md">Ret tilmelding</a>
        <?php else: ?>
            <div class="alert alert--warning">
                Du har meldt afbud til festen.
            </div>
            <p>Har du skiftet mening?</p>
            <a href="/guest/rsvp.php" class="btn btn--primary mt-md">Tilmeld dig alligevel</a>
        <?php endif; ?>
    </div>

    <!-- Quick Links -->
    <div class="card">
        <h2 class="card__title mb-md">Se mere</h2>
        <div style="display: grid; gap: var(--space-sm);">
            <a href="/guest/wishlist.php" class="btn btn--secondary btn--block" style="justify-content: flex-start;">
                🎁 Se ønskeliste
            </a>
            <a href="/guest/menu.php" class="btn btn--secondary btn--block" style="justify-content: flex-start;">
                🍽️ Se menu
            </a>
            <a href="/guest/schedule.php" class="btn btn--secondary btn--block" style="justify-content: flex-start;">
                🕐 Se tidsplan
            </a>
        </div>
    </div>
</main>

<script src="/assets/js/main.js"></script>
</body>
</html>
```

**Step 3: Opret RSVP side**

Create `guest/rsvp.php`:
```php
<?php require_once __DIR__ . '/../includes/guest-header.php'; ?>

<?php
$decline = isset($_GET['decline']);
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rsvpStatus = $_POST['rsvp_status'];
    $adultsCount = max(1, (int)($_POST['adults_count'] ?? 1));
    $childrenCount = max(0, (int)($_POST['children_count'] ?? 0));
    $dietaryNotes = trim($_POST['dietary_notes'] ?? '');

    $stmt = $db->prepare("UPDATE guests SET
        rsvp_status = ?,
        adults_count = ?,
        children_count = ?,
        dietary_notes = ?,
        rsvp_date = NOW()
        WHERE id = ?");
    $stmt->execute([
        $rsvpStatus,
        $rsvpStatus === 'yes' ? $adultsCount : 0,
        $rsvpStatus === 'yes' ? $childrenCount : 0,
        $dietaryNotes ?: null,
        $guestId
    ]);

    $success = true;

    // Refresh guest data
    $stmt = $db->prepare("SELECT * FROM guests WHERE id = ?");
    $stmt->execute([$guestId]);
    $guest = $stmt->fetch();
}
?>

<main class="guest-content">
    <?php if ($success): ?>
        <div class="card text-center">
            <?php if ($guest['rsvp_status'] === 'yes'): ?>
                <div style="font-size: 4rem; margin-bottom: var(--space-md);">🎉</div>
                <h1 style="color: var(--color-primary);">Tak for din tilmelding!</h1>
                <p class="mt-md">Vi glæder os til at se dig til <?= escape($event['confirmand_name']) ?>s konfirmation.</p>
            <?php else: ?>
                <div style="font-size: 4rem; margin-bottom: var(--space-md);">💌</div>
                <h1>Tak for din besked</h1>
                <p class="mt-md">Vi er kede af at du ikke kan komme, men tak fordi du gav os besked.</p>
            <?php endif; ?>
            <a href="/guest/index.php" class="btn btn--primary mt-lg">Tilbage til forsiden</a>
        </div>
    <?php else: ?>
        <div class="card">
            <h1 class="mb-lg"><?= $decline ? 'Meld afbud' : 'Tilmeld dig' ?></h1>

            <form method="POST">
                <input type="hidden" name="rsvp_status" value="<?= $decline ? 'no' : 'yes' ?>">

                <?php if (!$decline): ?>
                    <div class="form-group">
                        <label class="form-label">Antal voksne</label>
                        <select name="adults_count" class="form-input">
                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                <option value="<?= $i ?>" <?= $guest['adults_count'] == $i ? 'selected' : '' ?>>
                                    <?= $i ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Antal børn</label>
                        <select name="children_count" class="form-input">
                            <?php for ($i = 0; $i <= 10; $i++): ?>
                                <option value="<?= $i ?>" <?= $guest['children_count'] == $i ? 'selected' : '' ?>>
                                    <?= $i ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Allergier eller kostbehov?</label>
                        <textarea name="dietary_notes" class="form-input" rows="3" placeholder="F.eks. vegetar, glutenfri, nøddeallergi..."><?= escape($guest['dietary_notes'] ?? '') ?></textarea>
                    </div>
                <?php else: ?>
                    <p class="mb-lg">Vi er kede af at høre at du ikke kan komme. Tak fordi du giver os besked.</p>
                    <input type="hidden" name="adults_count" value="0">
                    <input type="hidden" name="children_count" value="0">
                <?php endif; ?>

                <button type="submit" class="btn btn--primary btn--large btn--block">
                    <?= $decline ? 'Send afbud' : 'Bekræft tilmelding' ?>
                </button>

                <a href="/guest/index.php" class="btn btn--secondary btn--block mt-md">Annuller</a>
            </form>
        </div>
    <?php endif; ?>
</main>

<script src="/assets/js/main.js"></script>
</body>
</html>
```

**Step 4: Commit**

```bash
git add guest/ includes/guest-header.php
git commit -m "feat: add guest RSVP flow with confirmation"
```

---

## Fase 4: Remaining Features (Tasks 9-14)

Remaining tasks follow the same pattern:

### Task 9: Ønskeliste (admin + guest view)
### Task 10: Menu (admin + guest view)
### Task 11: Tidsplan (admin + guest view)
### Task 12: Budget (admin only)
### Task 13: Fotoalbum (admin + guest upload)
### Task 14: Settings (admin - event details, theme)

Each task creates the relevant admin page and guest view following the established patterns.

---

## Implementation Checklist

- [ ] Task 1: Projektstruktur
- [ ] Task 2: Database schema
- [ ] Task 3: CSS Theming
- [ ] Task 4: Landing Page
- [ ] Task 5: Admin Layout
- [ ] Task 6: Gæsteliste
- [ ] Task 7: Huskeliste
- [ ] Task 8: Gæste RSVP
- [ ] Task 9: Ønskeliste
- [ ] Task 10: Menu
- [ ] Task 11: Tidsplan
- [ ] Task 12: Budget
- [ ] Task 13: Fotoalbum
- [ ] Task 14: Settings

---

*Plan created: 2026-01-18*

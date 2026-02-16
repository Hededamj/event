<?php
/**
 * Vendor Authentication Functions
 * Handles authentication for vendor/subcontractor users.
 * Separate from organizer auth (auth-account.php) and event-level auth (auth.php).
 *
 * Session keys are prefixed with vendor_ to avoid conflicts with account sessions.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Start session with extended lifetime (30 days)
if (session_status() === PHP_SESSION_NONE) {
    $sessionLifetime = 60 * 60 * 24 * 30; // 30 days

    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    ini_set('session.gc_maxlifetime', $sessionLifetime);
    session_start();

    // Refresh session cookie on each request
    if (isset($_COOKIE[session_name()])) {
        setcookie(
            session_name(),
            session_id(),
            time() + $sessionLifetime,
            '/',
            '',
            isset($_SERVER['HTTPS']),
            true
        );
    }
}

/**
 * Check if user is logged in as a vendor
 */
function isVendorLoggedIn(): bool {
    return isset($_SESSION['vendor_id']) && !empty($_SESSION['vendor_id']);
}

/**
 * Require vendor login - redirect if not logged in
 */
function requireVendorLogin(): void {
    if (!isVendorLoggedIn()) {
        $returnUrl = urlencode($_SERVER['REQUEST_URI'] ?? '/subcontractor/dashboard/');
        redirect('/subcontractor/dashboard/login.php?return=' . $returnUrl);
    }
}

/**
 * Get current vendor ID
 */
function getCurrentVendorId(): ?int {
    return $_SESSION['vendor_id'] ?? null;
}

/**
 * Get current vendor data from session
 */
function getCurrentVendor(): ?array {
    if (!isVendorLoggedIn()) {
        return null;
    }
    return [
        'id' => $_SESSION['vendor_id'],
        'email' => $_SESSION['vendor_email'] ?? null,
        'company_name' => $_SESSION['vendor_company_name'] ?? null
    ];
}

/**
 * Login as vendor
 */
function vendorLogin(int $vendorId, string $email, string $companyName): void {
    session_regenerate_id(true);

    $_SESSION['vendor_id'] = $vendorId;
    $_SESSION['vendor_email'] = $email;
    $_SESSION['vendor_company_name'] = $companyName;
    $_SESSION['vendor_login_time'] = time();

    // Update last login in database
    try {
        $db = getDB();
        $stmt = $db->prepare("UPDATE vendors SET last_login_at = NOW() WHERE id = ?");
        $stmt->execute([$vendorId]);
    } catch (Exception $e) {
        // Log error but don't fail login
        error_log("Failed to update vendor last login: " . $e->getMessage());
    }
}

/**
 * Logout vendor
 */
function vendorLogout(): void {
    // Clear vendor session data
    unset($_SESSION['vendor_id']);
    unset($_SESSION['vendor_email']);
    unset($_SESSION['vendor_company_name']);
    unset($_SESSION['vendor_login_time']);
    unset($_SESSION['vendor_csrf_token']);

    // Regenerate session ID
    session_regenerate_id(true);
}

/**
 * Verify vendor password
 *
 * Returns:
 *  - vendor row array on success
 *  - ['error' => 'vendor_pending']   if status is pending
 *  - ['error' => 'vendor_rejected']  if status is rejected
 *  - ['error' => 'vendor_suspended'] if status is suspended
 *  - ['error' => 'vendor_inactive']  if is_active = 0
 *  - null if email not found or wrong password
 */
function verifyVendorPassword(string $email, string $password): ?array {
    $db = getDB();

    $stmt = $db->prepare("
        SELECT id, email, company_name, contact_name, password_hash, status, is_active
        FROM vendors
        WHERE email = ?
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $vendor = $stmt->fetch();

    if (!$vendor) {
        return null;
    }

    if (!$vendor['is_active']) {
        return ['error' => 'vendor_inactive'];
    }

    if ($vendor['status'] === 'pending') {
        return ['error' => 'vendor_pending'];
    }

    if ($vendor['status'] === 'rejected') {
        return ['error' => 'vendor_rejected'];
    }

    if ($vendor['status'] === 'suspended') {
        return ['error' => 'vendor_suspended'];
    }

    if (!password_verify($password, $vendor['password_hash'])) {
        return null;
    }

    return $vendor;
}

/**
 * Register new vendor
 */
function registerVendor(string $email, string $password, string $companyName, string $contactName, ?string $phone = null): array {
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Ugyldig email-adresse'];
    }

    $db = getDB();

    // Check if email already exists
    $stmt = $db->prepare("SELECT id FROM vendors WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'Email er allerede registreret'];
    }

    // Validate password strength
    if (strlen($password) < 8) {
        return ['success' => false, 'error' => 'Adgangskode skal være mindst 8 tegn'];
    }

    // Create vendor
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $db->prepare("
            INSERT INTO vendors (email, password_hash, company_name, contact_name, phone, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$email, $passwordHash, $companyName, $contactName, $phone]);
        $vendorId = $db->lastInsertId();

        return [
            'success' => true,
            'vendor_id' => $vendorId
        ];
    } catch (Exception $e) {
        error_log("Vendor registration failed: " . $e->getMessage());
        return ['success' => false, 'error' => 'Der opstod en fejl. Prøv igen.'];
    }
}

/**
 * CSRF token functions
 */
if (!function_exists('generateVendorCsrfToken')) {
    function generateVendorCsrfToken(): string {
        if (empty($_SESSION['vendor_csrf_token'])) {
            $_SESSION['vendor_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['vendor_csrf_token'];
    }
}

if (!function_exists('verifyVendorCsrfToken')) {
    function verifyVendorCsrfToken(string $token): bool {
        return isset($_SESSION['vendor_csrf_token']) && hash_equals($_SESSION['vendor_csrf_token'], $token);
    }
}

if (!function_exists('vendorCsrfField')) {
    function vendorCsrfField(): string {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateVendorCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

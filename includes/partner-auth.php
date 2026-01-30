<?php
/**
 * Partner Authentication Helpers
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

// Start session
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
}

/**
 * Check if user is logged in as a partner
 */
function isPartner(): bool {
    return isset($_SESSION['partner_id']) && isset($_SESSION['partner_account_id']);
}

/**
 * Require partner login
 */
function requirePartner(): void {
    if (!isPartner()) {
        redirect(BASE_PATH . '/partners/login.php');
    }
}

/**
 * Get current partner ID
 */
function getCurrentPartnerId(): ?int {
    return $_SESSION['partner_id'] ?? null;
}

/**
 * Get current partner account ID
 */
function getCurrentPartnerAccountId(): ?int {
    return $_SESSION['partner_account_id'] ?? null;
}

/**
 * Get current partner data
 */
function getCurrentPartner(): ?array {
    if (!isPartner()) {
        return null;
    }

    $db = getDB();
    $stmt = $db->prepare("
        SELECT p.*, pc.name as category_name, pc.icon as category_icon
        FROM partners p
        LEFT JOIN partner_categories pc ON p.category_id = pc.id
        WHERE p.id = ?
    ");
    $stmt->execute([$_SESSION['partner_id']]);
    return $stmt->fetch() ?: null;
}

/**
 * Login as partner
 */
function loginPartner(int $accountId, int $partnerId): void {
    session_regenerate_id(true);

    $_SESSION['partner_account_id'] = $accountId;
    $_SESSION['partner_id'] = $partnerId;
    $_SESSION['partner_login_time'] = time();

    // Update last login on account
    $db = getDB();
    $stmt = $db->prepare("UPDATE accounts SET last_login_at = NOW(), login_count = login_count + 1 WHERE id = ?");
    $stmt->execute([$accountId]);
}

/**
 * Logout partner
 */
function logoutPartner(): void {
    unset($_SESSION['partner_account_id']);
    unset($_SESSION['partner_id']);
    unset($_SESSION['partner_login_time']);
}

/**
 * Get partner by account ID
 */
function getPartnerByAccountId(int $accountId): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM partners WHERE account_id = ?");
    $stmt->execute([$accountId]);
    return $stmt->fetch() ?: null;
}

/**
 * Generate CSRF token
 */
if (!function_exists('generateCsrfToken')) {
    function generateCsrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verifyCsrfToken')) {
    function verifyCsrfToken(string $token): bool {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('csrfField')) {
    function csrfField(): string {
        return '<input type="hidden" name="csrf_token" value="' . escape(generateCsrfToken()) . '">';
    }
}

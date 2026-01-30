<?php
/**
 * Platform Admin Authentication
 *
 * Handles authentication for the SaaS admin dashboard
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

// Start session with extended lifetime
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
 * Check if user is logged in as platform admin
 */
function isPlatformAdmin(): bool {
    return isset($_SESSION['platform_admin_id']) && isset($_SESSION['is_platform_admin']) && $_SESSION['is_platform_admin'] === true;
}

/**
 * Require platform admin login - redirect if not logged in
 */
function requirePlatformAdmin(): void {
    if (!isPlatformAdmin()) {
        redirect(BASE_PATH . '/admin-platform/login.php');
    }
}

/**
 * Get current platform admin ID
 */
function getCurrentPlatformAdminId(): ?int {
    return $_SESSION['platform_admin_id'] ?? null;
}

/**
 * Get current platform admin data
 */
function getCurrentPlatformAdmin(): ?array {
    if (!isPlatformAdmin()) {
        return null;
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM accounts WHERE id = ? AND is_platform_admin = 1");
    $stmt->execute([$_SESSION['platform_admin_id']]);
    return $stmt->fetch() ?: null;
}

/**
 * Login as platform admin
 */
function loginPlatformAdmin(int $accountId): bool {
    $db = getDB();

    // Verify admin status
    $stmt = $db->prepare("SELECT * FROM accounts WHERE id = ? AND is_platform_admin = 1 AND is_active = 1");
    $stmt->execute([$accountId]);
    $admin = $stmt->fetch();

    if (!$admin) {
        return false;
    }

    // Regenerate session ID for security
    session_regenerate_id(true);

    $_SESSION['platform_admin_id'] = $accountId;
    $_SESSION['is_platform_admin'] = true;
    $_SESSION['platform_admin_login_time'] = time();

    // Update last login
    $stmt = $db->prepare("UPDATE accounts SET last_login_at = NOW(), login_count = login_count + 1 WHERE id = ?");
    $stmt->execute([$accountId]);

    return true;
}

/**
 * Logout platform admin
 */
function logoutPlatformAdmin(): void {
    unset($_SESSION['platform_admin_id']);
    unset($_SESSION['is_platform_admin']);
    unset($_SESSION['platform_admin_login_time']);
}

/**
 * Get platform statistics
 */
function getPlatformStats(): array {
    $db = getDB();

    $stats = [];

    // Total accounts
    $stmt = $db->query("SELECT COUNT(*) FROM accounts WHERE is_platform_admin = 0");
    $stats['total_accounts'] = $stmt->fetchColumn();

    // Active accounts (logged in within 30 days)
    $stmt = $db->query("SELECT COUNT(*) FROM accounts WHERE is_platform_admin = 0 AND last_login_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stats['active_accounts'] = $stmt->fetchColumn();

    // New accounts this month
    $stmt = $db->query("SELECT COUNT(*) FROM accounts WHERE is_platform_admin = 0 AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')");
    $stats['new_accounts_month'] = $stmt->fetchColumn();

    // Total events
    $stmt = $db->query("SELECT COUNT(*) FROM events");
    $stats['total_events'] = $stmt->fetchColumn();

    // Active subscriptions
    $stmt = $db->query("SELECT COUNT(*) FROM subscriptions WHERE status = 'active'");
    $stats['active_subscriptions'] = $stmt->fetchColumn();

    // MRR (Monthly Recurring Revenue)
    $stmt = $db->query("
        SELECT COALESCE(SUM(p.price_monthly), 0) as mrr
        FROM subscriptions s
        JOIN plans p ON s.plan_id = p.id
        WHERE s.status = 'active'
    ");
    $stats['mrr'] = $stmt->fetchColumn();

    // Total revenue this month
    $stmt = $db->query("
        SELECT COALESCE(SUM(amount), 0)
        FROM payment_history
        WHERE status = 'succeeded'
        AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
    ");
    $stats['revenue_month'] = $stmt->fetchColumn();

    // Pending partners
    $stmt = $db->query("SELECT COUNT(*) FROM partners WHERE status = 'pending'");
    $stats['pending_partners'] = $stmt->fetchColumn();

    // Approved partners
    $stmt = $db->query("SELECT COUNT(*) FROM partners WHERE status = 'approved'");
    $stats['approved_partners'] = $stmt->fetchColumn();

    return $stats;
}

/**
 * Get platform setting
 */
function getPlatformSetting(string $key, $default = null) {
    $db = getDB();
    $stmt = $db->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value !== false ? $value : $default;
}

/**
 * Set platform setting
 */
function setPlatformSetting(string $key, string $value): void {
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO platform_settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    $stmt->execute([$key, $value]);
}

/**
 * Generate CSRF token
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCsrfToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get CSRF token input field
 */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . escape(generateCsrfToken()) . '">';
}

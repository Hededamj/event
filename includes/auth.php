<?php
/**
 * Authentication Functions
 */

// Start session with extended lifetime (30 days)
if (session_status() === PHP_SESSION_NONE) {
    // Set session cookie to last 30 days
    $sessionLifetime = 60 * 60 * 24 * 30; // 30 days in seconds

    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    // Also set the session garbage collection lifetime
    ini_set('session.gc_maxlifetime', $sessionLifetime);

    session_start();

    // Refresh the session cookie on each request to extend it
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
 * Check if user is logged in as organizer
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && isset($_SESSION['event_id']);
}

/**
 * Check if visitor is logged in as guest
 */
function isGuest(): bool {
    return isset($_SESSION['guest_id']) && isset($_SESSION['event_id']);
}

/**
 * Check if anyone is authenticated
 */
function isAuthenticated(): bool {
    return isLoggedIn() || isGuest();
}

/**
 * Require organizer login - redirect if not logged in
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        redirect(BASE_PATH . '/index.php?error=login_required');
    }
}

/**
 * Require guest or organizer access
 */
function requireGuest(): void {
    if (!isGuest() && !isLoggedIn()) {
        redirect(BASE_PATH . '/index.php?error=code_required');
    }
}

/**
 * Get current event ID
 */
function getCurrentEventId(): ?int {
    return $_SESSION['event_id'] ?? null;
}

/**
 * Get current user ID (organizer)
 */
function getCurrentUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role
 */
function getCurrentUserRole(): ?string {
    return $_SESSION['user_role'] ?? null;
}

/**
 * Check if current user is confirmand (limited view)
 */
function isConfirmand(): bool {
    return getCurrentUserRole() === 'confirmand';
}

/**
 * Get current guest ID
 */
function getCurrentGuestId(): ?int {
    return $_SESSION['guest_id'] ?? null;
}

/**
 * Login as organizer
 */
function login(int $userId, int $eventId, string $role = 'organizer'): void {
    // Regenerate session ID for security
    session_regenerate_id(true);

    $_SESSION['user_id'] = $userId;
    $_SESSION['event_id'] = $eventId;
    $_SESSION['user_role'] = $role;
    $_SESSION['login_time'] = time();

    // Clear any guest session
    unset($_SESSION['guest_id']);
}

/**
 * Login as guest
 */
function loginGuest(int $guestId, int $eventId): void {
    // Regenerate session ID for security
    session_regenerate_id(true);

    $_SESSION['guest_id'] = $guestId;
    $_SESSION['event_id'] = $eventId;
    $_SESSION['guest_login_time'] = time();

    // Clear any organizer session
    unset($_SESSION['user_id']);
}

/**
 * Logout and destroy session
 */
function logout(): void {
    // Unset all session variables
    $_SESSION = [];

    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    // Destroy session
    session_destroy();
}

/**
 * Check CSRF token
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get CSRF token input field
 */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . escape(generateCsrfToken()) . '">';
}

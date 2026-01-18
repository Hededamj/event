<?php
/**
 * Authentication Functions
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
        redirect('/index.php?error=login_required');
    }
}

/**
 * Require guest or organizer access
 */
function requireGuest(): void {
    if (!isGuest() && !isLoggedIn()) {
        redirect('/index.php?error=code_required');
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
 * Get current guest ID
 */
function getCurrentGuestId(): ?int {
    return $_SESSION['guest_id'] ?? null;
}

/**
 * Login as organizer
 */
function login(int $userId, int $eventId): void {
    // Regenerate session ID for security
    session_regenerate_id(true);

    $_SESSION['user_id'] = $userId;
    $_SESSION['event_id'] = $eventId;
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

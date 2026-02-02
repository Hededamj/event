<?php
/**
 * Rate Limiter
 * Prevents brute force attacks on guest codes and login forms
 */

/**
 * Get client IP address (only declare if not already defined in audit.php)
 */
if (!function_exists('getClientIP')) {
    function getClientIP(): string {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
}

/**
 * Check if IP is rate limited
 *
 * @param PDO $db Database connection
 * @param string $action Action being rate limited (e.g., 'guest_login', 'admin_login')
 * @param int $maxAttempts Maximum attempts allowed
 * @param int $windowSeconds Time window in seconds
 * @return array ['allowed' => bool, 'remaining' => int, 'retry_after' => int|null]
 */
function checkRateLimit(PDO $db, string $action, int $maxAttempts = 5, int $windowSeconds = 900): array {
    $ip = getClientIP();
    $now = time();
    $windowStart = $now - $windowSeconds;

    // Ensure table exists
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS rate_limits (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                action VARCHAR(50) NOT NULL,
                attempts INT DEFAULT 1,
                first_attempt_at INT NOT NULL,
                last_attempt_at INT NOT NULL,
                blocked_until INT DEFAULT NULL,
                INDEX idx_ip_action (ip_address, action),
                INDEX idx_blocked (blocked_until)
            )
        ");
    } catch (Exception $e) {
        // Table might already exist
    }

    // Clean up old entries (older than 24 hours)
    try {
        $db->prepare("DELETE FROM rate_limits WHERE last_attempt_at < ?")->execute([$now - 86400]);
    } catch (Exception $e) {}

    // Check for existing rate limit record
    $stmt = $db->prepare("
        SELECT * FROM rate_limits
        WHERE ip_address = ? AND action = ?
        AND first_attempt_at > ?
    ");
    $stmt->execute([$ip, $action, $windowStart]);
    $record = $stmt->fetch();

    // If blocked, check if block has expired
    if ($record && $record['blocked_until'] && $record['blocked_until'] > $now) {
        return [
            'allowed' => false,
            'remaining' => 0,
            'retry_after' => $record['blocked_until'] - $now
        ];
    }

    // If no record or window expired, user is allowed
    if (!$record || $record['first_attempt_at'] <= $windowStart) {
        return [
            'allowed' => true,
            'remaining' => $maxAttempts,
            'retry_after' => null
        ];
    }

    // Check attempts within window
    $remaining = max(0, $maxAttempts - $record['attempts']);

    return [
        'allowed' => $remaining > 0,
        'remaining' => $remaining,
        'retry_after' => $remaining > 0 ? null : ($record['first_attempt_at'] + $windowSeconds - $now)
    ];
}

/**
 * Record a rate limit attempt (call on failed attempts)
 *
 * @param PDO $db Database connection
 * @param string $action Action being rate limited
 * @param int $maxAttempts Maximum attempts before blocking
 * @param int $windowSeconds Time window in seconds
 * @param int $blockSeconds How long to block after max attempts (default: 30 minutes)
 */
function recordRateLimitAttempt(PDO $db, string $action, int $maxAttempts = 5, int $windowSeconds = 900, int $blockSeconds = 1800): void {
    $ip = getClientIP();
    $now = time();
    $windowStart = $now - $windowSeconds;

    // Check for existing record within window
    $stmt = $db->prepare("
        SELECT id, attempts FROM rate_limits
        WHERE ip_address = ? AND action = ?
        AND first_attempt_at > ?
    ");
    $stmt->execute([$ip, $action, $windowStart]);
    $record = $stmt->fetch();

    if ($record) {
        // Update existing record
        $newAttempts = $record['attempts'] + 1;
        $blockedUntil = null;

        // Block if max attempts exceeded
        if ($newAttempts >= $maxAttempts) {
            $blockedUntil = $now + $blockSeconds;
        }

        $stmt = $db->prepare("
            UPDATE rate_limits
            SET attempts = ?, last_attempt_at = ?, blocked_until = ?
            WHERE id = ?
        ");
        $stmt->execute([$newAttempts, $now, $blockedUntil, $record['id']]);
    } else {
        // Create new record
        $stmt = $db->prepare("
            INSERT INTO rate_limits (ip_address, action, attempts, first_attempt_at, last_attempt_at)
            VALUES (?, ?, 1, ?, ?)
        ");
        $stmt->execute([$ip, $action, $now, $now]);
    }
}

/**
 * Clear rate limit for an IP/action (call on successful login)
 */
function clearRateLimit(PDO $db, string $action): void {
    $ip = getClientIP();
    $stmt = $db->prepare("DELETE FROM rate_limits WHERE ip_address = ? AND action = ?");
    $stmt->execute([$ip, $action]);
}

/**
 * Format retry time for display
 */
function formatRetryTime(int $seconds): string {
    if ($seconds < 60) {
        return "$seconds sekunder";
    } elseif ($seconds < 3600) {
        $minutes = ceil($seconds / 60);
        return "$minutes minut" . ($minutes > 1 ? 'ter' : '');
    } else {
        $hours = ceil($seconds / 3600);
        return "$hours time" . ($hours > 1 ? 'r' : '');
    }
}

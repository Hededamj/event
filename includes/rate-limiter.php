<?php
/**
 * Rate Limiter
 * Prevents brute force attacks on guest codes and login forms
 */

require_once __DIR__ . '/functions.php';
// getClientIP() is now defined in includes/functions.php

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

    // Clean up old entries (older than 24 hours)
    try {
        $db->prepare("DELETE FROM rate_limits WHERE last_attempt_at < ?")->execute([$now - 86400]);
    } catch (Exception $e) {
        error_log('Failed to clean up old rate_limits entries: ' . $e->getMessage());
    }

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

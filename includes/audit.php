<?php
/**
 * Audit Logging System
 * Tracks authentication events, data changes, and admin actions
 */

require_once __DIR__ . '/functions.php';

/**
 * Audit event types
 */
const AUDIT_AUTH_LOGIN = 'auth.login';
const AUDIT_AUTH_LOGOUT = 'auth.logout';
const AUDIT_AUTH_FAILED = 'auth.failed';
const AUDIT_AUTH_GUEST = 'auth.guest_login';
const AUDIT_AUTH_GUEST_FAILED = 'auth.guest_failed';
const AUDIT_AUTH_TOASTMASTER = 'auth.toastmaster_login';

const AUDIT_DATA_CREATE = 'data.create';
const AUDIT_DATA_UPDATE = 'data.update';
const AUDIT_DATA_DELETE = 'data.delete';

const AUDIT_ADMIN_SETTINGS = 'admin.settings';
const AUDIT_ADMIN_GUEST_MANAGE = 'admin.guest_manage';
const AUDIT_ADMIN_EXPORT = 'admin.export';

const AUDIT_SECURITY_CSRF = 'security.csrf_failure';
const AUDIT_SECURITY_RATE_LIMIT = 'security.rate_limited';
const AUDIT_SECURITY_ACCESS_DENIED = 'security.access_denied';

/**
 * Log an audit event
 *
 * @param PDO $db Database connection
 * @param string $action The action type (use AUDIT_* constants)
 * @param array $details Additional details about the event
 * @param string|null $resourceType The type of resource affected
 * @param int|null $resourceId The ID of the resource affected
 * @return bool Whether logging succeeded
 */
function auditLog(
    PDO $db,
    string $action,
    array $details = [],
    ?string $resourceType = null,
    ?int $resourceId = null
): bool {
    try {
        // Ensure audit table exists
        ensureAuditTable($db);

        $stmt = $db->prepare("
            INSERT INTO audit_log (
                account_id, user_id, guest_id, event_id, action,
                resource_type, resource_id, ip_address, user_agent,
                details, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $_SESSION['account_id'] ?? null,
            getCurrentUserId(),
            getCurrentGuestId(),
            getCurrentEventId(),
            $action,
            $resourceType,
            $resourceId,
            getClientIP(),
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            json_encode($details, JSON_UNESCAPED_UNICODE)
        ]);

        return true;
    } catch (Exception $e) {
        // Log to error log but don't break the application
        error_log("Audit log failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Log a successful authentication
 */
function auditLoginSuccess(PDO $db, int $userId, int $eventId, string $role = 'organizer'): void {
    auditLog($db, AUDIT_AUTH_LOGIN, [
        'user_id' => $userId,
        'event_id' => $eventId,
        'role' => $role
    ]);
}

/**
 * Log a failed authentication attempt
 */
function auditLoginFailed(PDO $db, string $email, string $reason = 'invalid_credentials'): void {
    auditLog($db, AUDIT_AUTH_FAILED, [
        'email' => $email,
        'reason' => $reason
    ]);
}

/**
 * Log a logout
 */
function auditLogout(PDO $db): void {
    auditLog($db, AUDIT_AUTH_LOGOUT);
}

/**
 * Log guest login success
 */
function auditGuestLogin(PDO $db, int $guestId, int $eventId): void {
    auditLog($db, AUDIT_AUTH_GUEST, [
        'guest_id' => $guestId,
        'event_id' => $eventId
    ], 'guests', $guestId);
}

/**
 * Log failed guest code attempt
 */
function auditGuestFailed(PDO $db, string $code): void {
    auditLog($db, AUDIT_AUTH_GUEST_FAILED, [
        'code_prefix' => substr($code, 0, 3) . '***' // Don't log full code
    ]);
}

/**
 * Log data creation
 */
function auditCreate(PDO $db, string $resourceType, int $resourceId, array $data = []): void {
    // Remove sensitive fields
    $safeData = array_diff_key($data, array_flip(['password', 'password_hash']));
    auditLog($db, AUDIT_DATA_CREATE, $safeData, $resourceType, $resourceId);
}

/**
 * Log data update
 */
function auditUpdate(PDO $db, string $resourceType, int $resourceId, array $changes = []): void {
    // Remove sensitive fields
    $safeChanges = array_diff_key($changes, array_flip(['password', 'password_hash']));
    auditLog($db, AUDIT_DATA_UPDATE, ['changes' => $safeChanges], $resourceType, $resourceId);
}

/**
 * Log data deletion
 */
function auditDelete(PDO $db, string $resourceType, int $resourceId, array $metadata = []): void {
    auditLog($db, AUDIT_DATA_DELETE, $metadata, $resourceType, $resourceId);
}

/**
 * Log security event
 */
function auditSecurity(PDO $db, string $type, array $details = []): void {
    auditLog($db, $type, $details);
}

// getClientIP() is now defined in includes/functions.php

/**
 * Ensure audit_log table exists
 * Schema is guaranteed by migration 017_consolidate_schema.sql
 */
function ensureAuditTable(PDO $db): void {
    static $checked = false;
    $checked = true;
}

/**
 * Get audit log entries for an event
 */
function getAuditLog(PDO $db, int $eventId, int $limit = 100, int $offset = 0): array {
    $stmt = $db->prepare("
        SELECT al.*, u.email as user_email, g.name as guest_name
        FROM audit_log al
        LEFT JOIN users u ON al.user_id = u.id
        LEFT JOIN guests g ON al.guest_id = g.id
        WHERE al.event_id = ?
        ORDER BY al.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$eventId, $limit, $offset]);
    return $stmt->fetchAll();
}

/**
 * Clean up old audit entries (data retention)
 */
function cleanupAuditLog(PDO $db, int $daysToKeep = 365): int {
    $stmt = $db->prepare("
        DELETE FROM audit_log
        WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $stmt->execute([$daysToKeep]);
    return $stmt->rowCount();
}

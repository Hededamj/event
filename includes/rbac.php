<?php
/**
 * Role-Based Access Control (RBAC)
 * Ensures users can only access events they own or have permission for
 */

/**
 * Verify that the current user has access to the specified event
 * This should be called early in every admin endpoint
 *
 * @param PDO $db Database connection
 * @param int $eventId The event ID to check access for
 * @param bool $redirect Whether to redirect on failure (default) or return false
 * @return bool True if access granted
 */
function verifyEventAccess(PDO $db, int $eventId, bool $redirect = true): bool {
    // Must be logged in
    if (!isLoggedIn()) {
        if ($redirect) {
            redirect(BASE_PATH . '/index.php?error=login_required');
        }
        return false;
    }

    $userId = getCurrentUserId();
    $sessionEventId = getCurrentEventId();

    // Quick check: session event must match requested event
    if ($sessionEventId !== $eventId) {
        if ($redirect) {
            logSecurityEvent($db, 'event_access_denied', [
                'user_id' => $userId,
                'session_event_id' => $sessionEventId,
                'requested_event_id' => $eventId
            ]);
            setFlash('error', 'Adgang nægtet.');
            redirect(BASE_PATH . '/admin/index.php');
        }
        return false;
    }

    // Verify user actually belongs to this event
    $stmt = $db->prepare("
        SELECT id FROM users
        WHERE id = ? AND event_id = ?
    ");
    $stmt->execute([$userId, $eventId]);

    if (!$stmt->fetch()) {
        if ($redirect) {
            logSecurityEvent($db, 'invalid_user_event_access', [
                'user_id' => $userId,
                'event_id' => $eventId
            ]);
            logout();
            redirect(BASE_PATH . '/index.php?error=invalid_session');
        }
        return false;
    }

    return true;
}

/**
 * Verify guest has access to the specified event
 *
 * @param PDO $db Database connection
 * @param int $eventId The event ID to check access for
 * @param bool $redirect Whether to redirect on failure
 * @return bool True if access granted
 */
function verifyGuestEventAccess(PDO $db, int $eventId, bool $redirect = true): bool {
    if (!isGuest() && !isLoggedIn()) {
        if ($redirect) {
            redirect(BASE_PATH . '/index.php?error=code_required');
        }
        return false;
    }

    $sessionEventId = getCurrentEventId();

    // Session event must match requested event
    if ($sessionEventId !== $eventId) {
        if ($redirect) {
            logSecurityEvent($db, 'guest_event_access_denied', [
                'guest_id' => getCurrentGuestId(),
                'session_event_id' => $sessionEventId,
                'requested_event_id' => $eventId
            ]);
            setFlash('error', 'Adgang nægtet.');
            redirect(BASE_PATH . '/index.php');
        }
        return false;
    }

    return true;
}

/**
 * Check if user has a specific role
 *
 * @param string $role The role to check (organizer, confirmand)
 * @return bool True if user has the role
 */
function hasRole(string $role): bool {
    return getCurrentUserRole() === $role;
}

/**
 * Require a specific role, redirect if not met
 *
 * @param string|array $roles Single role or array of allowed roles
 * @param string|null $redirectUrl Where to redirect if role not met
 */
function requireRole(string|array $roles, ?string $redirectUrl = null): void {
    if (!isLoggedIn()) {
        redirect(BASE_PATH . '/index.php?error=login_required');
    }

    $allowedRoles = is_array($roles) ? $roles : [$roles];
    $userRole = getCurrentUserRole();

    if (!in_array($userRole, $allowedRoles)) {
        setFlash('error', 'Du har ikke adgang til denne side.');
        redirect($redirectUrl ?? BASE_PATH . '/admin/index.php');
    }
}

/**
 * Log security event for audit purposes
 */
function logSecurityEvent(PDO $db, string $event, array $details = []): void {
    try {
        // Check if audit_log table exists
        $tableExists = $db->query("SHOW TABLES LIKE 'audit_log'")->rowCount() > 0;
        if (!$tableExists) {
            return; // Skip if audit table doesn't exist yet
        }

        $stmt = $db->prepare("
            INSERT INTO audit_log (
                account_id, user_id, event_id, action, resource_type,
                ip_address, user_agent, details, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            null, // account_id
            $details['user_id'] ?? getCurrentUserId(),
            $details['event_id'] ?? getCurrentEventId(),
            $event,
            'security',
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            json_encode($details)
        ]);
    } catch (Exception $e) {
        // Silently fail - don't break the application for logging
        error_log("Failed to log security event: " . $e->getMessage());
    }
}

/**
 * Verify that an operation on a specific resource is allowed
 *
 * @param PDO $db Database connection
 * @param string $table The table/resource type
 * @param int $resourceId The resource ID
 * @param int $eventId The event ID it should belong to
 * @return bool True if the resource belongs to the event
 */
function verifyResourceOwnership(PDO $db, string $table, int $resourceId, int $eventId): bool {
    $allowedTables = [
        'guests', 'wishlist_items', 'checklist_items', 'menu_items',
        'schedule_items', 'photos', 'budget_items', 'toastmaster_items'
    ];

    if (!in_array($table, $allowedTables)) {
        return false;
    }

    $stmt = $db->prepare("SELECT id FROM $table WHERE id = ? AND event_id = ?");
    $stmt->execute([$resourceId, $eventId]);

    return $stmt->fetch() !== false;
}

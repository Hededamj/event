<?php
/**
 * GDPR Data Retention Cron Job
 *
 * This script should be run daily via cron:
 * 0 3 * * * /usr/bin/php /path/to/cron/data-retention.php >> /var/log/data-retention.log 2>&1
 *
 * It handles:
 * - Processing scheduled account deletions
 * - Cleaning up old rate limits
 * - Removing expired data export files
 * - Cleaning old audit logs
 * - Anonymizing old guest data
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from CLI');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/gdpr.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting data retention job\n";

$results = [
    'deleted_accounts' => 0,
    'rate_limits_cleaned' => 0,
    'export_files_deleted' => 0,
    'audit_logs_cleaned' => 0,
    'guests_anonymized' => 0,
    'errors' => []
];

try {
    // 1. Process scheduled account deletions
    echo "Processing scheduled account deletions...\n";
    $results['deleted_accounts'] = processScheduledDeletions($db);
    echo "  - Deleted {$results['deleted_accounts']} accounts\n";

    // 2. Run standard data retention cleanup
    echo "Running data retention cleanup...\n";
    $cleanupResults = runDataRetention($db);
    $results['rate_limits_cleaned'] = $cleanupResults['rate_limits'];
    $results['export_files_deleted'] = $cleanupResults['export_files'];
    $results['audit_logs_cleaned'] = $cleanupResults['audit_logs'];
    echo "  - Rate limits: {$results['rate_limits_cleaned']}\n";
    echo "  - Export files: {$results['export_files_deleted']}\n";
    echo "  - Audit logs: {$results['audit_logs_cleaned']}\n";

    // 3. Anonymize old guest data
    echo "Anonymizing old guest data...\n";
    $results['guests_anonymized'] = anonymizeOldGuestData($db);
    echo "  - Anonymized {$results['guests_anonymized']} guests\n";

} catch (Exception $e) {
    $results['errors'][] = $e->getMessage();
    echo "ERROR: " . $e->getMessage() . "\n";
}

// Log results to database
try {
    logRetentionRun($db, $results);
} catch (Exception $e) {
    echo "Failed to log results: " . $e->getMessage() . "\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Data retention job completed\n";
echo "Summary: " . json_encode($results) . "\n\n";

/**
 * Process accounts scheduled for deletion
 */
function processScheduledDeletions(PDO $db): int {
    $deleted = 0;

    // Find accounts past their deletion date
    $stmt = $db->query("
        SELECT adr.*, a.email, a.name
        FROM account_deletion_requests adr
        JOIN accounts a ON adr.account_id = a.id
        WHERE adr.status = 'pending'
        AND adr.scheduled_deletion_date <= CURDATE()
    ");
    $requests = $stmt->fetchAll();

    foreach ($requests as $request) {
        $db->beginTransaction();
        try {
            $accountId = $request['account_id'];

            // Get all events owned by this account
            $eventStmt = $db->prepare("
                SELECT event_id FROM event_owners WHERE account_id = ?
            ");
            $eventStmt->execute([$accountId]);
            $eventIds = $eventStmt->fetchAll(PDO::FETCH_COLUMN);

            // Delete guests for these events
            if (!empty($eventIds)) {
                $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
                $db->prepare("DELETE FROM guests WHERE event_id IN ($placeholders)")->execute($eventIds);

                // Delete events
                $db->prepare("DELETE FROM events WHERE id IN ($placeholders)")->execute($eventIds);
            }

            // Delete event owner records
            $db->prepare("DELETE FROM event_owners WHERE account_id = ?")->execute([$accountId]);

            // Delete consent records
            $db->prepare("DELETE FROM consent_records WHERE account_id = ?")->execute([$accountId]);

            // Mark deletion request as completed
            $db->prepare("
                UPDATE account_deletion_requests
                SET status = 'completed', deleted_at = NOW(), deleted_by = 'system'
                WHERE id = ?
            ")->execute([$request['id']]);

            // Finally delete the account
            $db->prepare("DELETE FROM accounts WHERE id = ?")->execute([$accountId]);

            $db->commit();
            $deleted++;

            echo "  - Deleted account: {$request['email']}\n";

        } catch (Exception $e) {
            $db->rollBack();
            echo "  - ERROR deleting account {$request['email']}: " . $e->getMessage() . "\n";
        }
    }

    return $deleted;
}

/**
 * Anonymize guest data from events older than retention period
 */
function anonymizeOldGuestData(PDO $db): int {
    $anonymized = 0;

    // Get retention setting
    try {
        $stmt = $db->query("
            SELECT retention_days FROM data_retention_settings
            WHERE data_type = 'guest_data' AND is_active = 1
        ");
        $retentionDays = $stmt->fetchColumn() ?: 365;
    } catch (Exception $e) {
        $retentionDays = 365; // Default 1 year
    }

    // Find guests from old events that haven't been anonymized
    $stmt = $db->prepare("
        SELECT g.id FROM guests g
        JOIN events e ON g.event_id = e.id
        WHERE e.event_date < DATE_SUB(CURDATE(), INTERVAL ? DAY)
        AND g.email IS NOT NULL
        AND g.name NOT LIKE 'Anonym gæst #%'
        LIMIT 1000
    ");
    $stmt->execute([$retentionDays]);
    $guestIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($guestIds as $guestId) {
        if (anonymizeGuest($db, $guestId)) {
            $anonymized++;
        }
    }

    // Update last cleanup timestamp
    try {
        $db->query("
            UPDATE data_retention_settings
            SET last_cleanup_at = NOW()
            WHERE data_type = 'guest_data'
        ");
    } catch (Exception $e) {}

    return $anonymized;
}

/**
 * Log retention run to database
 */
function logRetentionRun(PDO $db, array $results): void {
    try {
        // Ensure audit_log table exists
        $db->query("SHOW TABLES LIKE 'audit_log'")->rowCount();

        $stmt = $db->prepare("
            INSERT INTO audit_log (action, entity_type, details, ip_address, created_at)
            VALUES ('data_retention_run', 'system', ?, '127.0.0.1', NOW())
        ");
        $stmt->execute([json_encode($results)]);
    } catch (Exception $e) {
        // Ignore if table doesn't exist
    }
}

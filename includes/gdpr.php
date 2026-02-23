<?php
/**
 * GDPR Compliance Functions
 * Handles consent tracking, data export, and deletion requests
 */

/**
 * Ensure GDPR tables exist
 */
function ensureGdprTables(PDO $db): void {
    static $checked = false;
    if ($checked) return;

    try {
        $result = $db->query("SHOW TABLES LIKE 'consent_records'")->rowCount();
        if ($result === 0) {
            // Create consent_records table
            $db->exec("
                CREATE TABLE IF NOT EXISTS consent_records (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    account_id INT DEFAULT NULL,
                    guest_id INT DEFAULT NULL,
                    partner_id INT DEFAULT NULL,
                    consent_type ENUM('privacy_policy', 'terms', 'marketing', 'data_processing', 'cookies') NOT NULL,
                    consent_version VARCHAR(20) NOT NULL DEFAULT '1.0',
                    is_granted TINYINT(1) NOT NULL DEFAULT 1,
                    ip_address VARCHAR(45) DEFAULT NULL,
                    user_agent VARCHAR(500) DEFAULT NULL,
                    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    withdrawn_at TIMESTAMP NULL,
                    INDEX idx_consent_account (account_id),
                    INDEX idx_consent_guest (guest_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Add consent columns to guests
            try {
                $db->exec("ALTER TABLE guests ADD COLUMN IF NOT EXISTS privacy_consent TINYINT(1) DEFAULT 0");
                $db->exec("ALTER TABLE guests ADD COLUMN IF NOT EXISTS privacy_consent_at TIMESTAMP NULL");
                $db->exec("ALTER TABLE guests ADD COLUMN IF NOT EXISTS privacy_consent_version VARCHAR(20) DEFAULT NULL");
                $db->exec("ALTER TABLE guests ADD COLUMN IF NOT EXISTS marketing_consent TINYINT(1) DEFAULT 0");
                $db->exec("ALTER TABLE guests ADD COLUMN IF NOT EXISTS marketing_consent_at TIMESTAMP NULL");
            } catch (Exception $e) {
                error_log('Failed to add GDPR consent columns to guests table: ' . $e->getMessage());
            }
        }
        $checked = true;
    } catch (Exception $e) {
        error_log("Failed to ensure GDPR tables: " . $e->getMessage());
        $checked = true;
    }
}

/**
 * Get current privacy policy version
 */
function getPrivacyPolicyVersion(PDO $db): string {
    try {
        $stmt = $db->query("SELECT setting_value FROM platform_settings WHERE setting_key = 'privacy_policy_version'");
        return $stmt->fetchColumn() ?: '1.0';
    } catch (Exception $e) {
        return '1.0';
    }
}

/**
 * Record consent
 */
function recordConsent(
    PDO $db,
    string $consentType,
    ?int $accountId = null,
    ?int $guestId = null,
    ?int $partnerId = null,
    bool $isGranted = true
): bool {
    ensureGdprTables($db);

    $version = getPrivacyPolicyVersion($db);

    $stmt = $db->prepare("
        INSERT INTO consent_records
        (account_id, guest_id, partner_id, consent_type, consent_version, is_granted, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    try {
        return $stmt->execute([
            $accountId,
            $guestId,
            $partnerId,
            $consentType,
            $version,
            $isGranted ? 1 : 0,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)
        ]);
    } catch (Exception $e) {
        error_log("Failed to record consent: " . $e->getMessage());
        return false;
    }
}

/**
 * Record guest privacy consent during RSVP
 */
function recordGuestPrivacyConsent(PDO $db, int $guestId, bool $privacyConsent, bool $marketingConsent = false): bool {
    ensureGdprTables($db);

    $version = getPrivacyPolicyVersion($db);

    // Update guest record
    $stmt = $db->prepare("
        UPDATE guests SET
            privacy_consent = ?,
            privacy_consent_at = IF(? = 1, NOW(), privacy_consent_at),
            privacy_consent_version = IF(? = 1, ?, privacy_consent_version),
            marketing_consent = ?,
            marketing_consent_at = IF(? = 1, NOW(), marketing_consent_at)
        WHERE id = ?
    ");

    $result = $stmt->execute([
        $privacyConsent ? 1 : 0,
        $privacyConsent ? 1 : 0,
        $privacyConsent ? 1 : 0,
        $version,
        $marketingConsent ? 1 : 0,
        $marketingConsent ? 1 : 0,
        $guestId
    ]);

    // Also record in consent_records for audit trail
    if ($privacyConsent) {
        recordConsent($db, 'privacy_policy', null, $guestId);
        recordConsent($db, 'data_processing', null, $guestId);
    }

    if ($marketingConsent) {
        recordConsent($db, 'marketing', null, $guestId);
    }

    return $result;
}

/**
 * Check if user has given consent
 */
function hasConsent(PDO $db, string $consentType, ?int $accountId = null, ?int $guestId = null): bool {
    ensureGdprTables($db);

    $sql = "SELECT id FROM consent_records WHERE consent_type = ? AND is_granted = 1 AND withdrawn_at IS NULL";
    $params = [$consentType];

    if ($accountId) {
        $sql .= " AND account_id = ?";
        $params[] = $accountId;
    } elseif ($guestId) {
        $sql .= " AND guest_id = ?";
        $params[] = $guestId;
    } else {
        return false;
    }

    $sql .= " ORDER BY granted_at DESC LIMIT 1";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch() !== false;
}

/**
 * Withdraw consent
 */
function withdrawConsent(PDO $db, string $consentType, ?int $accountId = null, ?int $guestId = null): bool {
    ensureGdprTables($db);

    $sql = "UPDATE consent_records SET withdrawn_at = NOW() WHERE consent_type = ? AND withdrawn_at IS NULL";
    $params = [$consentType];

    if ($accountId) {
        $sql .= " AND account_id = ?";
        $params[] = $accountId;
    } elseif ($guestId) {
        $sql .= " AND guest_id = ?";
        $params[] = $guestId;
    } else {
        return false;
    }

    $stmt = $db->prepare($sql);
    return $stmt->execute($params);
}

/**
 * Get all consent records for a user
 */
function getConsentHistory(PDO $db, ?int $accountId = null, ?int $guestId = null): array {
    ensureGdprTables($db);

    $sql = "SELECT * FROM consent_records WHERE 1=1";
    $params = [];

    if ($accountId) {
        $sql .= " AND account_id = ?";
        $params[] = $accountId;
    } elseif ($guestId) {
        $sql .= " AND guest_id = ?";
        $params[] = $guestId;
    } else {
        return [];
    }

    $sql .= " ORDER BY granted_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Generate consent checkbox HTML
 */
function consentCheckbox(string $name, string $label, bool $required = true, bool $checked = false): string {
    $requiredAttr = $required ? 'required' : '';
    $checkedAttr = $checked ? 'checked' : '';
    $requiredMark = $required ? ' *' : '';

    return <<<HTML
<div class="consent-checkbox">
    <label class="checkbox-label">
        <input type="checkbox" name="{$name}" value="1" {$requiredAttr} {$checkedAttr}>
        <span class="checkbox-text">{$label}{$requiredMark}</span>
    </label>
</div>
HTML;
}

/**
 * Request account deletion
 */
function requestAccountDeletion(PDO $db, int $accountId, ?string $reason = null): ?string {
    // Check for existing pending request
    $stmt = $db->prepare("SELECT id FROM account_deletion_requests WHERE account_id = ? AND status = 'pending'");
    $stmt->execute([$accountId]);
    if ($stmt->fetch()) {
        return null; // Already has pending request
    }

    $token = bin2hex(random_bytes(32));
    $scheduledDate = date('Y-m-d', strtotime('+30 days'));

    $stmt = $db->prepare("
        INSERT INTO account_deletion_requests
        (account_id, reason, scheduled_deletion_date, confirmation_token)
        VALUES (?, ?, ?, ?)
    ");

    try {
        $stmt->execute([$accountId, $reason, $scheduledDate, $token]);

        // Update account
        $db->prepare("
            UPDATE accounts SET deletion_requested_at = NOW(), deletion_scheduled_for = ?
            WHERE id = ?
        ")->execute([$scheduledDate, $accountId]);

        return $token;
    } catch (Exception $e) {
        error_log("Failed to request deletion: " . $e->getMessage());
        return null;
    }
}

/**
 * Cancel account deletion request
 */
function cancelAccountDeletion(PDO $db, int $accountId): bool {
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("
            UPDATE account_deletion_requests
            SET status = 'cancelled', cancelled_at = NOW()
            WHERE account_id = ? AND status = 'pending'
        ");
        $stmt->execute([$accountId]);

        $db->prepare("
            UPDATE accounts SET deletion_requested_at = NULL, deletion_scheduled_for = NULL
            WHERE id = ?
        ")->execute([$accountId]);

        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        return false;
    }
}

/**
 * Export user data as JSON
 */
function exportUserData(PDO $db, int $accountId): array {
    $data = [
        'export_date' => date('c'),
        'export_type' => 'GDPR Data Export',
        'account' => [],
        'events' => [],
        'guests' => [],
        'consent_history' => []
    ];

    // Account info
    $stmt = $db->prepare("SELECT id, email, name, phone, created_at FROM accounts WHERE id = ?");
    $stmt->execute([$accountId]);
    $data['account'] = $stmt->fetch() ?: [];

    // Events owned by account
    $stmt = $db->prepare("
        SELECT e.* FROM events e
        JOIN event_owners eo ON e.id = eo.event_id
        WHERE eo.account_id = ?
    ");
    $stmt->execute([$accountId]);
    $data['events'] = $stmt->fetchAll();

    // Consent history
    $data['consent_history'] = getConsentHistory($db, $accountId);

    return $data;
}

/**
 * Export guest data
 */
function exportGuestData(PDO $db, int $guestId): array {
    $data = [
        'export_date' => date('c'),
        'export_type' => 'GDPR Guest Data Export'
    ];

    // Guest info
    $stmt = $db->prepare("
        SELECT g.*, e.name as event_name, e.event_date
        FROM guests g
        JOIN events e ON g.event_id = e.id
        WHERE g.id = ?
    ");
    $stmt->execute([$guestId]);
    $data['guest'] = $stmt->fetch() ?: [];

    // Consent history
    $data['consent_history'] = getConsentHistory($db, null, $guestId);

    return $data;
}

/**
 * Anonymize guest data (for retention)
 */
function anonymizeGuest(PDO $db, int $guestId): bool {
    $stmt = $db->prepare("
        UPDATE guests SET
            name = CONCAT('Anonym gæst #', id),
            email = NULL,
            phone = NULL,
            dietary_restrictions = NULL,
            notes = NULL,
            unique_code = CONCAT('DELETED_', id)
        WHERE id = ?
    ");
    return $stmt->execute([$guestId]);
}

/**
 * Delete old data based on retention settings
 */
function runDataRetention(PDO $db): array {
    $results = [
        'rate_limits' => 0,
        'export_files' => 0,
        'audit_logs' => 0
    ];

    // Clean old rate limits
    try {
        $stmt = $db->query("DELETE FROM rate_limits WHERE expires_at < NOW()");
        $results['rate_limits'] = $stmt->rowCount();
    } catch (Exception $e) {
        error_log('Failed to clean up expired rate_limits records during GDPR cleanup: ' . $e->getMessage());
    }

    // Clean old export files
    try {
        $stmt = $db->query("
            SELECT file_path FROM data_export_requests
            WHERE expires_at < NOW() AND file_path IS NOT NULL
        ");
        $files = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($files as $file) {
            $fullPath = __DIR__ . '/../' . $file;
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }

        $stmt = $db->query("
            UPDATE data_export_requests SET status = 'expired', file_path = NULL
            WHERE expires_at < NOW() AND status = 'completed'
        ");
        $results['export_files'] = count($files);
    } catch (Exception $e) {
        error_log('Failed to clean up expired data export files during GDPR cleanup: ' . $e->getMessage());
    }

    // Clean old audit logs (keep 2 years)
    try {
        $stmt = $db->query("
            DELETE FROM audit_log
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 730 DAY)
        ");
        $results['audit_logs'] = $stmt->rowCount();
    } catch (Exception $e) {
        error_log('Failed to clean up old audit_log records during GDPR cleanup: ' . $e->getMessage());
    }

    return $results;
}

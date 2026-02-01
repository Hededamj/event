<?php
/**
 * Partner Verification System
 * Handles document uploads and verification status
 */

/**
 * Ensure verification tables exist
 */
function ensureVerificationTables(PDO $db): void {
    static $checked = false;
    if ($checked) return;

    try {
        // Check if table exists
        $result = $db->query("SHOW TABLES LIKE 'partner_verification_documents'")->rowCount();
        if ($result === 0) {
            // Create verification documents table
            $db->exec("
                CREATE TABLE IF NOT EXISTS partner_verification_documents (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    partner_id INT NOT NULL,
                    document_type ENUM('cvr', 'insurance', 'license', 'portfolio', 'reference', 'other') NOT NULL,
                    document_name VARCHAR(255) NOT NULL,
                    file_path VARCHAR(500) NOT NULL,
                    file_size INT NOT NULL,
                    mime_type VARCHAR(100) NOT NULL,
                    description TEXT DEFAULT NULL,
                    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
                    reviewed_by INT DEFAULT NULL,
                    reviewed_at TIMESTAMP NULL,
                    review_note TEXT DEFAULT NULL,
                    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_doc_partner (partner_id),
                    INDEX idx_doc_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Add columns to partners table
            try {
                $db->exec("ALTER TABLE partners ADD COLUMN IF NOT EXISTS is_verified TINYINT(1) DEFAULT 0");
                $db->exec("ALTER TABLE partners ADD COLUMN IF NOT EXISTS verified_at TIMESTAMP NULL");
                $db->exec("ALTER TABLE partners ADD COLUMN IF NOT EXISTS verification_level ENUM('none', 'basic', 'full') DEFAULT 'none'");
                $db->exec("ALTER TABLE partners ADD COLUMN IF NOT EXISTS cvr_number VARCHAR(20) DEFAULT NULL");
            } catch (Exception $e) {}
        }
        $checked = true;
    } catch (Exception $e) {
        error_log("Failed to ensure verification tables: " . $e->getMessage());
        $checked = true;
    }
}

/**
 * Document type labels
 */
function getDocumentTypeLabels(): array {
    return [
        'cvr' => 'CVR-registreringsbevis',
        'insurance' => 'Erhvervsansvarsforsikring',
        'license' => 'Tilladelse/Certifikat',
        'portfolio' => 'Portfolio/Arbejdseksempler',
        'reference' => 'Kundereference',
        'other' => 'Andet'
    ];
}

/**
 * Allowed document MIME types
 */
function getAllowedDocumentTypes(): array {
    return [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];
}

/**
 * Upload a verification document
 */
function uploadVerificationDocument(PDO $db, int $partnerId, array $file, string $documentType, ?string $description = null): ?int {
    ensureVerificationTables($db);

    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    // Check file size (max 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        return null;
    }

    // Check MIME type
    $allowedTypes = getAllowedDocumentTypes();
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!isset($allowedTypes[$mimeType])) {
        return null;
    }

    // Create upload directory
    $uploadDir = __DIR__ . '/../uploads/verification/' . $partnerId;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $extension = $allowedTypes[$mimeType];
    $filename = $documentType . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $filePath = $uploadDir . '/' . $filename;
    $relativePath = 'uploads/verification/' . $partnerId . '/' . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        return null;
    }

    // Insert database record
    $stmt = $db->prepare("
        INSERT INTO partner_verification_documents
        (partner_id, document_type, document_name, file_path, file_size, mime_type, description)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    try {
        $stmt->execute([
            $partnerId,
            $documentType,
            $file['name'],
            $relativePath,
            $file['size'],
            $mimeType,
            $description
        ]);
        return (int)$db->lastInsertId();
    } catch (Exception $e) {
        // Clean up file on database error
        unlink($filePath);
        error_log("Failed to save document record: " . $e->getMessage());
        return null;
    }
}

/**
 * Get documents for a partner
 */
function getPartnerDocuments(PDO $db, int $partnerId): array {
    ensureVerificationTables($db);

    $stmt = $db->prepare("
        SELECT * FROM partner_verification_documents
        WHERE partner_id = ?
        ORDER BY uploaded_at DESC
    ");
    $stmt->execute([$partnerId]);
    return $stmt->fetchAll();
}

/**
 * Get verification status for a partner
 */
function getPartnerVerificationStatus(PDO $db, int $partnerId): array {
    ensureVerificationTables($db);

    // Get partner info
    $stmt = $db->prepare("SELECT is_verified, verified_at, verification_level, cvr_number FROM partners WHERE id = ?");
    $stmt->execute([$partnerId]);
    $partner = $stmt->fetch();

    // Get document counts
    $stmt = $db->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM partner_verification_documents
        WHERE partner_id = ?
    ");
    $stmt->execute([$partnerId]);
    $docs = $stmt->fetch();

    // Check if CVR document exists
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM partner_verification_documents
        WHERE partner_id = ? AND document_type = 'cvr' AND status = 'approved'
    ");
    $stmt->execute([$partnerId]);
    $hasCvr = $stmt->fetchColumn() > 0;

    // Check if insurance document exists
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM partner_verification_documents
        WHERE partner_id = ? AND document_type = 'insurance' AND status = 'approved'
    ");
    $stmt->execute([$partnerId]);
    $hasInsurance = $stmt->fetchColumn() > 0;

    return [
        'is_verified' => (bool)($partner['is_verified'] ?? false),
        'verified_at' => $partner['verified_at'] ?? null,
        'verification_level' => $partner['verification_level'] ?? 'none',
        'cvr_number' => $partner['cvr_number'] ?? null,
        'documents_total' => (int)($docs['total'] ?? 0),
        'documents_approved' => (int)($docs['approved'] ?? 0),
        'documents_pending' => (int)($docs['pending'] ?? 0),
        'documents_rejected' => (int)($docs['rejected'] ?? 0),
        'has_cvr' => $hasCvr,
        'has_insurance' => $hasInsurance,
        'can_verify_basic' => $hasCvr,
        'can_verify_full' => $hasCvr && $hasInsurance
    ];
}

/**
 * Update partner CVR number
 */
function updatePartnerCvr(PDO $db, int $partnerId, string $cvrNumber): bool {
    // Validate CVR format (Danish: 8 digits)
    $cvr = preg_replace('/[^0-9]/', '', $cvrNumber);
    if (strlen($cvr) !== 8) {
        return false;
    }

    $stmt = $db->prepare("UPDATE partners SET cvr_number = ? WHERE id = ?");
    return $stmt->execute([$cvr, $partnerId]);
}

/**
 * Review a verification document (admin)
 */
function reviewDocument(PDO $db, int $documentId, string $status, int $reviewerId, ?string $note = null): bool {
    if (!in_array($status, ['approved', 'rejected'])) {
        return false;
    }

    $stmt = $db->prepare("
        UPDATE partner_verification_documents
        SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_note = ?
        WHERE id = ?
    ");
    $result = $stmt->execute([$status, $reviewerId, $note, $documentId]);

    if ($result && $status === 'approved') {
        // Get partner ID and check if we should upgrade verification level
        $stmt = $db->prepare("SELECT partner_id FROM partner_verification_documents WHERE id = ?");
        $stmt->execute([$documentId]);
        $partnerId = $stmt->fetchColumn();

        if ($partnerId) {
            checkAndUpdateVerificationLevel($db, $partnerId);
        }
    }

    return $result;
}

/**
 * Check and update partner verification level
 */
function checkAndUpdateVerificationLevel(PDO $db, int $partnerId): void {
    $status = getPartnerVerificationStatus($db, $partnerId);

    $newLevel = 'none';
    $isVerified = false;

    if ($status['can_verify_full']) {
        $newLevel = 'full';
        $isVerified = true;
    } elseif ($status['can_verify_basic']) {
        $newLevel = 'basic';
        $isVerified = true;
    }

    $stmt = $db->prepare("
        UPDATE partners
        SET verification_level = ?, is_verified = ?, verified_at = IF(? = 1 AND verified_at IS NULL, NOW(), verified_at)
        WHERE id = ?
    ");
    $stmt->execute([$newLevel, $isVerified ? 1 : 0, $isVerified ? 1 : 0, $partnerId]);
}

/**
 * Delete a document
 */
function deleteVerificationDocument(PDO $db, int $documentId, int $partnerId): bool {
    // Get file path first
    $stmt = $db->prepare("SELECT file_path FROM partner_verification_documents WHERE id = ? AND partner_id = ?");
    $stmt->execute([$documentId, $partnerId]);
    $filePath = $stmt->fetchColumn();

    if (!$filePath) {
        return false;
    }

    // Delete database record
    $stmt = $db->prepare("DELETE FROM partner_verification_documents WHERE id = ? AND partner_id = ?");
    $result = $stmt->execute([$documentId, $partnerId]);

    if ($result) {
        // Delete file
        $fullPath = __DIR__ . '/../' . $filePath;
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }

        // Update verification level
        checkAndUpdateVerificationLevel($db, $partnerId);
    }

    return $result;
}

/**
 * Format file size
 */
function formatFileSize(int $bytes): string {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' bytes';
}

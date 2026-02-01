<?php
/**
 * Partner Reviews System
 * Handles review submission, display, and moderation
 */

/**
 * Ensure review tables exist
 */
function ensureReviewTables(PDO $db): void {
    static $checked = false;
    if ($checked) return;

    try {
        $result = $db->query("SHOW TABLES LIKE 'partner_reviews'")->rowCount();
        if ($result === 0) {
            // Add rating columns to partners if they don't exist
            try {
                $db->exec("ALTER TABLE partners ADD COLUMN IF NOT EXISTS average_rating DECIMAL(3,2) DEFAULT NULL");
                $db->exec("ALTER TABLE partners ADD COLUMN IF NOT EXISTS review_count INT DEFAULT 0");
            } catch (Exception $e) {}

            // Create reviews table
            $db->exec("
                CREATE TABLE IF NOT EXISTS partner_reviews (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    partner_id INT NOT NULL,
                    booking_id INT DEFAULT NULL,
                    account_id INT DEFAULT NULL,
                    reviewer_name VARCHAR(255) NOT NULL,
                    reviewer_email VARCHAR(255) NOT NULL,
                    rating TINYINT NOT NULL,
                    title VARCHAR(255) DEFAULT NULL,
                    review_text TEXT NOT NULL,
                    rating_quality TINYINT DEFAULT NULL,
                    rating_communication TINYINT DEFAULT NULL,
                    rating_value TINYINT DEFAULT NULL,
                    event_type VARCHAR(100) DEFAULT NULL,
                    event_date DATE DEFAULT NULL,
                    status ENUM('pending', 'approved', 'rejected', 'flagged') NOT NULL DEFAULT 'pending',
                    is_verified TINYINT(1) DEFAULT 0,
                    moderation_note TEXT DEFAULT NULL,
                    moderated_by INT DEFAULT NULL,
                    moderated_at TIMESTAMP NULL,
                    partner_response TEXT DEFAULT NULL,
                    partner_responded_at TIMESTAMP NULL,
                    helpful_count INT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_review_partner (partner_id),
                    INDEX idx_review_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
        $checked = true;
    } catch (Exception $e) {
        error_log("Failed to ensure review tables: " . $e->getMessage());
        $checked = true;
    }
}

/**
 * Submit a new review
 */
function submitReview(PDO $db, array $data): ?int {
    ensureReviewTables($db);

    // Validate rating
    $rating = (int)($data['rating'] ?? 0);
    if ($rating < 1 || $rating > 5) {
        return null;
    }

    // Check if booking exists and hasn't been reviewed
    $isVerified = false;
    if (!empty($data['booking_id'])) {
        $stmt = $db->prepare("
            SELECT pb.id FROM partner_bookings pb
            LEFT JOIN partner_reviews pr ON pb.id = pr.booking_id
            WHERE pb.id = ? AND pb.partner_id = ? AND pb.status = 'completed' AND pr.id IS NULL
        ");
        $stmt->execute([$data['booking_id'], $data['partner_id']]);
        if ($stmt->fetch()) {
            $isVerified = true;
        }
    }

    $stmt = $db->prepare("
        INSERT INTO partner_reviews (
            partner_id, booking_id, account_id,
            reviewer_name, reviewer_email, rating,
            title, review_text,
            rating_quality, rating_communication, rating_value,
            event_type, event_date, is_verified, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    try {
        $stmt->execute([
            $data['partner_id'],
            $data['booking_id'] ?? null,
            $data['account_id'] ?? null,
            $data['reviewer_name'],
            $data['reviewer_email'],
            $rating,
            $data['title'] ?? null,
            $data['review_text'],
            isset($data['rating_quality']) ? (int)$data['rating_quality'] : null,
            isset($data['rating_communication']) ? (int)$data['rating_communication'] : null,
            isset($data['rating_value']) ? (int)$data['rating_value'] : null,
            $data['event_type'] ?? null,
            $data['event_date'] ?? null,
            $isVerified ? 1 : 0,
            $isVerified ? 'approved' : 'pending' // Auto-approve verified reviews
        ]);

        $reviewId = (int)$db->lastInsertId();

        // Update partner rating cache if auto-approved
        if ($isVerified) {
            updatePartnerRatingCache($db, $data['partner_id']);
        }

        return $reviewId;
    } catch (Exception $e) {
        error_log("Failed to submit review: " . $e->getMessage());
        return null;
    }
}

/**
 * Get reviews for a partner
 */
function getPartnerReviews(PDO $db, int $partnerId, int $limit = 10, int $offset = 0, bool $approvedOnly = true): array {
    ensureReviewTables($db);

    $sql = "
        SELECT pr.*, pb.booking_reference
        FROM partner_reviews pr
        LEFT JOIN partner_bookings pb ON pr.booking_id = pb.id
        WHERE pr.partner_id = ?
    ";

    if ($approvedOnly) {
        $sql .= " AND pr.status = 'approved'";
    }

    $sql .= " ORDER BY pr.created_at DESC LIMIT ? OFFSET ?";

    $stmt = $db->prepare($sql);
    $stmt->execute([$partnerId, $limit, $offset]);
    return $stmt->fetchAll();
}

/**
 * Get rating summary for a partner
 */
function getPartnerRatingSummary(PDO $db, int $partnerId): array {
    ensureReviewTables($db);

    $stmt = $db->prepare("
        SELECT
            COUNT(*) as total_reviews,
            AVG(rating) as average_rating,
            SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
            SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
            SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
            SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
            SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star,
            AVG(rating_quality) as avg_quality,
            AVG(rating_communication) as avg_communication,
            AVG(rating_value) as avg_value
        FROM partner_reviews
        WHERE partner_id = ? AND status = 'approved'
    ");
    $stmt->execute([$partnerId]);
    $result = $stmt->fetch();

    return [
        'total_reviews' => (int)($result['total_reviews'] ?? 0),
        'average_rating' => $result['average_rating'] ? round((float)$result['average_rating'], 1) : null,
        'five_star' => (int)($result['five_star'] ?? 0),
        'four_star' => (int)($result['four_star'] ?? 0),
        'three_star' => (int)($result['three_star'] ?? 0),
        'two_star' => (int)($result['two_star'] ?? 0),
        'one_star' => (int)($result['one_star'] ?? 0),
        'avg_quality' => $result['avg_quality'] ? round((float)$result['avg_quality'], 1) : null,
        'avg_communication' => $result['avg_communication'] ? round((float)$result['avg_communication'], 1) : null,
        'avg_value' => $result['avg_value'] ? round((float)$result['avg_value'], 1) : null
    ];
}

/**
 * Update partner rating cache
 */
function updatePartnerRatingCache(PDO $db, int $partnerId): void {
    $summary = getPartnerRatingSummary($db, $partnerId);

    $stmt = $db->prepare("
        UPDATE partners
        SET average_rating = ?, review_count = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $summary['average_rating'],
        $summary['total_reviews'],
        $partnerId
    ]);
}

/**
 * Moderate a review
 */
function moderateReview(PDO $db, int $reviewId, string $status, ?int $moderatorId = null, ?string $note = null): bool {
    $validStatuses = ['pending', 'approved', 'rejected', 'flagged'];
    if (!in_array($status, $validStatuses)) {
        return false;
    }

    $stmt = $db->prepare("
        UPDATE partner_reviews
        SET status = ?, moderated_by = ?, moderation_note = ?, moderated_at = NOW()
        WHERE id = ?
    ");
    $result = $stmt->execute([$status, $moderatorId, $note, $reviewId]);

    if ($result) {
        // Get partner ID and update cache
        $stmt = $db->prepare("SELECT partner_id FROM partner_reviews WHERE id = ?");
        $stmt->execute([$reviewId]);
        $partnerId = $stmt->fetchColumn();
        if ($partnerId) {
            updatePartnerRatingCache($db, $partnerId);
        }
    }

    return $result;
}

/**
 * Add partner response to a review
 */
function addPartnerResponse(PDO $db, int $reviewId, int $partnerId, string $response): bool {
    $stmt = $db->prepare("
        UPDATE partner_reviews
        SET partner_response = ?, partner_responded_at = NOW()
        WHERE id = ? AND partner_id = ?
    ");
    return $stmt->execute([$response, $reviewId, $partnerId]);
}

/**
 * Render star rating HTML
 */
function renderStars(float $rating, bool $showNumber = true): string {
    $fullStars = floor($rating);
    $halfStar = ($rating - $fullStars) >= 0.5;
    $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);

    $html = '<span class="star-rating">';
    for ($i = 0; $i < $fullStars; $i++) {
        $html .= '<span class="star star--full">★</span>';
    }
    if ($halfStar) {
        $html .= '<span class="star star--half">★</span>';
    }
    for ($i = 0; $i < $emptyStars; $i++) {
        $html .= '<span class="star star--empty">☆</span>';
    }
    if ($showNumber) {
        $html .= '<span class="star-rating__number">' . number_format($rating, 1) . '</span>';
    }
    $html .= '</span>';

    return $html;
}

/**
 * Format review date
 */
function formatReviewDate(string $date): string {
    $timestamp = strtotime($date);
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'Lige nu';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' min siden';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' time' . ($hours > 1 ? 'r' : '') . ' siden';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' dag' . ($days > 1 ? 'e' : '') . ' siden';
    } else {
        return date('d. M Y', $timestamp);
    }
}

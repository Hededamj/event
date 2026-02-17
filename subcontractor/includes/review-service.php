<?php
/**
 * Review Service
 * Handles vendor review management: create, respond, retrieve, and rating stats.
 */

require_once __DIR__ . '/../../config/database.php';

// ============================================================
// 1. createReview
// ============================================================

/**
 * Create a review for a completed booking.
 *
 * @param int         $bookingId Booking to review
 * @param int         $vendorId  Vendor being reviewed
 * @param int         $accountId Reviewer's account ID
 * @param int         $rating    Rating 1-5
 * @param string|null $title     Optional review title
 * @param string|null $text      Optional review text
 * @return array Success/error result with review_id on success
 */
function createReview(
    int $bookingId,
    int $vendorId,
    int $accountId,
    int $rating,
    ?string $title,
    ?string $text
): array {
    try {
        // Validate rating range
        if ($rating < 1 || $rating > 5) {
            return ['success' => false, 'error' => 'Bedømmelse skal være mellem 1 og 5'];
        }

        $db = getDB();

        // Verify booking exists, belongs to the correct vendor and account, and is completed
        $stmt = $db->prepare("
            SELECT id, status, vendor_id, account_id
            FROM bookings
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();

        if (!$booking) {
            return ['success' => false, 'error' => 'Booking ikke fundet'];
        }

        if ((int)$booking['vendor_id'] !== $vendorId) {
            return ['success' => false, 'error' => 'Booking tilhører ikke denne leverandør'];
        }

        if ((int)$booking['account_id'] !== $accountId) {
            return ['success' => false, 'error' => 'Booking tilhører ikke denne konto'];
        }

        if ($booking['status'] !== 'completed') {
            return ['success' => false, 'error' => 'Booking skal være afsluttet før den kan anmeldes'];
        }

        // Check no existing review for this booking
        $stmt = $db->prepare("
            SELECT id FROM vendor_reviews
            WHERE booking_id = ?
            LIMIT 1
        ");
        $stmt->execute([$bookingId]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Der findes allerede en anmeldelse for denne booking'];
        }

        $db->beginTransaction();

        // Insert the review
        $stmt = $db->prepare("
            INSERT INTO vendor_reviews (booking_id, vendor_id, account_id, rating, title, review_text)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $bookingId,
            $vendorId,
            $accountId,
            $rating,
            $title,
            $text
        ]);
        $reviewId = (int)$db->lastInsertId();

        // Update booking status to 'reviewed'
        $stmt = $db->prepare("
            UPDATE bookings SET status = 'reviewed'
            WHERE id = ?
        ");
        $stmt->execute([$bookingId]);

        // Recalculate vendor rating stats
        updateVendorRatingStats($vendorId);

        $db->commit();
        return ['success' => true, 'review_id' => $reviewId];
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log("createReview failed: " . $e->getMessage());
        return ['success' => false, 'error' => 'Kunne ikke oprette anmeldelse'];
    }
}

// ============================================================
// 2. respondToReview
// ============================================================

/**
 * Vendor responds to a review.
 *
 * @param int    $reviewId Review to respond to
 * @param int    $vendorId Vendor responding (must own the review)
 * @param string $response Vendor's response text
 * @return bool True on success
 */
function respondToReview(int $reviewId, int $vendorId, string $response): bool {
    try {
        $db = getDB();

        // Verify vendor owns this review
        $stmt = $db->prepare("
            SELECT id FROM vendor_reviews
            WHERE id = ? AND vendor_id = ?
            LIMIT 1
        ");
        $stmt->execute([$reviewId, $vendorId]);
        if (!$stmt->fetch()) {
            return false;
        }

        // Update the vendor response
        $stmt = $db->prepare("
            UPDATE vendor_reviews
            SET vendor_response = ?, vendor_responded_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$response, $reviewId]);

        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("respondToReview failed: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// 3. getVendorReviews
// ============================================================

/**
 * Get reviews for a vendor, ordered by most recent first.
 *
 * @param int $vendorId Vendor to get reviews for
 * @param int $limit    Max number of reviews to return
 * @param int $offset   Offset for pagination
 * @return array List of reviews with reviewer name
 */
function getVendorReviews(int $vendorId, int $limit = 10, int $offset = 0): array {
    try {
        $db = getDB();

        $stmt = $db->prepare("
            SELECT
                vr.id,
                vr.booking_id,
                vr.rating,
                vr.title,
                vr.review_text,
                vr.vendor_response,
                vr.vendor_responded_at,
                vr.created_at,
                a.name AS reviewer_name
            FROM vendor_reviews vr
            JOIN accounts a ON a.id = vr.account_id
            WHERE vr.vendor_id = ?
            ORDER BY vr.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$vendorId, $limit, $offset]);

        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("getVendorReviews failed: " . $e->getMessage());
        return [];
    }
}

// ============================================================
// 4. getReviewForBooking
// ============================================================

/**
 * Get the review for a specific booking, if one exists.
 *
 * @param int $bookingId Booking to get review for
 * @return array|null Review data or null if no review exists
 */
function getReviewForBooking(int $bookingId): ?array {
    try {
        $db = getDB();

        $stmt = $db->prepare("
            SELECT *
            FROM vendor_reviews
            WHERE booking_id = ?
            LIMIT 1
        ");
        $stmt->execute([$bookingId]);
        $review = $stmt->fetch();

        return $review ?: null;
    } catch (Exception $e) {
        error_log("getReviewForBooking failed: " . $e->getMessage());
        return null;
    }
}

// ============================================================
// 5. updateVendorRatingStats
// ============================================================

/**
 * Recalculate and update a vendor's aggregate rating stats.
 * Only counts visible reviews (is_visible = 1).
 *
 * @param int $vendorId Vendor to update stats for
 * @return void
 */
function updateVendorRatingStats(int $vendorId): void {
    try {
        $db = getDB();

        // Calculate average rating and count from visible reviews
        $stmt = $db->prepare("
            SELECT AVG(rating) AS avg_rating, COUNT(*) AS review_count
            FROM vendor_reviews
            WHERE vendor_id = ? AND is_visible = 1
        ");
        $stmt->execute([$vendorId]);
        $stats = $stmt->fetch();

        $avgRating = $stats['avg_rating'] !== null ? round((float)$stats['avg_rating'], 1) : 0;
        $reviewCount = (int)$stats['review_count'];

        // Update the vendors table with recalculated stats
        $stmt = $db->prepare("
            UPDATE vendors
            SET avg_rating = ?, review_count = ?
            WHERE id = ?
        ");
        $stmt->execute([$avgRating, $reviewCount, $vendorId]);
    } catch (Exception $e) {
        error_log("updateVendorRatingStats failed: " . $e->getMessage());
    }
}

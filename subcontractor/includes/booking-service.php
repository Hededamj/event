<?php
/**
 * Booking Service
 * Handles the full booking lifecycle: request, quote, accept, pay, confirm, complete, cancel.
 * Also manages booking messages (in-booking messaging thread).
 */

require_once __DIR__ . '/../../config/database.php';

// Financial constants
if (!defined('DEPOSITUM_RATE')) define('DEPOSITUM_RATE', 0.25);
if (!defined('COMMISSION_RATE')) define('COMMISSION_RATE', 0.15);

// ============================================================
// 1. createBookingRequest
// ============================================================

/**
 * Create a new booking request from an organizer to a vendor.
 *
 * @param int      $eventId    Event to book for
 * @param int      $vendorId   Vendor being booked
 * @param int      $accountId  Organizer's account ID
 * @param int|null $serviceId  Optional vendor service ID
 * @param string   $message    Organizer's message to vendor
 * @param string   $eventDate  Date of the event (Y-m-d)
 * @param int|null $guestCount Optional guest count
 * @return array Success/error result with booking_id on success
 */
function createBookingRequest(
    int $eventId,
    int $vendorId,
    int $accountId,
    ?int $serviceId,
    string $message,
    string $eventDate,
    ?int $guestCount
): array {
    try {
        $db = getDB();

        // Validate event exists and belongs to account (organizer must be owner)
        $stmt = $db->prepare("
            SELECT eo.id
            FROM event_owners eo
            JOIN events e ON e.id = eo.event_id
            WHERE eo.event_id = ? AND eo.account_id = ? AND eo.role = 'owner'
            LIMIT 1
        ");
        $stmt->execute([$eventId, $accountId]);
        if (!$stmt->fetch()) {
            return ['success' => false, 'error' => 'Arrangement ikke fundet eller du er ikke ejeren'];
        }

        // Validate vendor exists and is approved
        $stmt = $db->prepare("
            SELECT id FROM vendors
            WHERE id = ? AND status = 'approved' AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$vendorId]);
        if (!$stmt->fetch()) {
            return ['success' => false, 'error' => 'Leverandør ikke fundet eller ikke godkendt'];
        }

        // Validate service belongs to vendor (if provided)
        if ($serviceId !== null) {
            $stmt = $db->prepare("
                SELECT id FROM vendor_services
                WHERE id = ? AND vendor_id = ? AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$serviceId, $vendorId]);
            if (!$stmt->fetch()) {
                return ['success' => false, 'error' => 'Ydelse ikke fundet eller tilhører ikke leverandøren'];
            }
        }

        $db->beginTransaction();

        // Insert booking
        $stmt = $db->prepare("
            INSERT INTO bookings (event_id, vendor_id, vendor_service_id, account_id, status, organizer_message, event_date, guest_count)
            VALUES (?, ?, ?, ?, 'requested', ?, ?, ?)
        ");
        $stmt->execute([
            $eventId,
            $vendorId,
            $serviceId,
            $accountId,
            $message,
            $eventDate,
            $guestCount
        ]);
        $bookingId = (int)$db->lastInsertId();

        // Also insert the organizer's message into the booking_messages thread
        if (!empty(trim($message))) {
            $msgStmt = $db->prepare("
                INSERT INTO booking_messages (booking_id, sender_type, message)
                VALUES (?, 'organizer', ?)
            ");
            $msgStmt->execute([$bookingId, $message]);
        }

        $db->commit();
        return ['success' => true, 'booking_id' => $bookingId];
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log("createBookingRequest failed: " . $e->getMessage());
        return ['success' => false, 'error' => 'Kunne ikke oprette bookingforespørgsel'];
    }
}

// ============================================================
// 2. submitQuote
// ============================================================

/**
 * Vendor submits a price quote for a booking request.
 *
 * @param int    $bookingId Booking to quote
 * @param int    $vendorId  Vendor submitting the quote
 * @param float  $price     Quoted price (total)
 * @param string $message   Vendor's message to organizer
 * @return array Success/error result
 */
function submitQuote(int $bookingId, int $vendorId, float $price, string $message): array {
    try {
        $db = getDB();

        // Verify vendor owns this booking and status is 'requested'
        $stmt = $db->prepare("
            SELECT id, status FROM bookings
            WHERE id = ? AND vendor_id = ?
            LIMIT 1
        ");
        $stmt->execute([$bookingId, $vendorId]);
        $booking = $stmt->fetch();

        if (!$booking) {
            return ['success' => false, 'error' => 'Booking ikke fundet eller tilhører ikke denne leverandør'];
        }

        if ($booking['status'] !== 'requested') {
            return ['success' => false, 'error' => 'Booking er ikke i forespurt status'];
        }

        if ($price <= 0) {
            return ['success' => false, 'error' => 'Prisen skal være større end nul'];
        }

        // Calculate financial breakdown
        $depositum = round($price * DEPOSITUM_RATE, 2);
        $commission = round($depositum * COMMISSION_RATE, 2);
        $vendorPayout = round($depositum - $commission, 2);

        $db->beginTransaction();

        // Update booking with quote
        $stmt = $db->prepare("
            UPDATE bookings SET
                quoted_price = ?,
                depositum_amount = ?,
                commission_amount = ?,
                vendor_payout = ?,
                vendor_message = ?,
                status = 'quoted',
                quoted_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $price,
            $depositum,
            $commission,
            $vendorPayout,
            $message,
            $bookingId
        ]);

        // Insert vendor message into booking_messages thread
        if (!empty(trim($message))) {
            $msgStmt = $db->prepare("
                INSERT INTO booking_messages (booking_id, sender_type, message)
                VALUES (?, 'vendor', ?)
            ");
            $msgStmt->execute([$bookingId, $message]);
        }

        $db->commit();
        return [
            'success' => true,
            'quoted_price' => $price,
            'depositum_amount' => $depositum,
            'commission_amount' => $commission,
            'vendor_payout' => $vendorPayout
        ];
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log("submitQuote failed: " . $e->getMessage());
        return ['success' => false, 'error' => 'Kunne ikke indsende tilbud'];
    }
}

// ============================================================
// 3. acceptQuote
// ============================================================

/**
 * Organizer accepts a vendor's quote. Returns booking data needed for payment redirect.
 *
 * @param int $bookingId Booking to accept
 * @param int $accountId Organizer's account ID
 * @return array Success/error result with booking data on success
 */
function acceptQuote(int $bookingId, int $accountId): array {
    try {
        $db = getDB();

        // Verify organizer owns the booking (via event_owners)
        $stmt = $db->prepare("
            SELECT b.id, b.status, b.quoted_price, b.depositum_amount, b.commission_amount,
                   b.vendor_payout, b.vendor_id, b.event_id, b.vendor_service_id,
                   b.event_date, b.guest_count,
                   v.company_name, v.stripe_account_id
            FROM bookings b
            JOIN event_owners eo ON eo.event_id = b.event_id AND eo.account_id = ? AND eo.role = 'owner'
            JOIN vendors v ON v.id = b.vendor_id
            WHERE b.id = ?
            LIMIT 1
        ");
        $stmt->execute([$accountId, $bookingId]);
        $booking = $stmt->fetch();

        if (!$booking) {
            return ['success' => false, 'error' => 'Booking ikke fundet eller du er ikke arrangøren'];
        }

        if ($booking['status'] !== 'quoted') {
            return ['success' => false, 'error' => 'Booking er ikke i tilbudt status'];
        }

        // Set status to accepted
        $stmt = $db->prepare("
            UPDATE bookings SET status = 'accepted', accepted_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$bookingId]);

        return [
            'success' => true,
            'booking' => $booking
        ];
    } catch (Exception $e) {
        error_log("acceptQuote failed: " . $e->getMessage());
        return ['success' => false, 'error' => 'Kunne ikke acceptere tilbud'];
    }
}

// ============================================================
// 4. confirmBooking (after Stripe payment — called from webhook)
// ============================================================

/**
 * Mark booking as deposited after successful Stripe payment.
 * Called from webhook — no auth check needed.
 *
 * @param int $bookingId Booking to confirm payment for
 * @return bool True on success
 */
function confirmBooking(int $bookingId): bool {
    try {
        $db = getDB();

        // Only transition from 'accepted' to prevent webhook replays moving booking backward
        $stmt = $db->prepare("
            UPDATE bookings SET status = 'deposited', paid_at = NOW()
            WHERE id = ? AND status = 'accepted'
        ");
        $stmt->execute([$bookingId]);

        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("confirmBooking failed: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// 5. vendorConfirmBooking
// ============================================================

/**
 * Vendor confirms a booking after deposit has been paid.
 *
 * @param int $bookingId Booking to confirm
 * @param int $vendorId  Vendor confirming
 * @return bool True on success
 */
function vendorConfirmBooking(int $bookingId, int $vendorId): bool {
    try {
        $db = getDB();

        // Verify vendor owns booking and status is 'deposited'
        $stmt = $db->prepare("
            SELECT id, status FROM bookings
            WHERE id = ? AND vendor_id = ?
            LIMIT 1
        ");
        $stmt->execute([$bookingId, $vendorId]);
        $booking = $stmt->fetch();

        if (!$booking || $booking['status'] !== 'deposited') {
            return false;
        }

        $stmt = $db->prepare("
            UPDATE bookings SET status = 'confirmed', confirmed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$bookingId]);

        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("vendorConfirmBooking failed: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// 6. completeBooking
// ============================================================

/**
 * Organizer marks a booking as completed after the event.
 *
 * @param int $bookingId Booking to complete
 * @param int $accountId Organizer's account ID
 * @return bool True on success
 */
function completeBooking(int $bookingId, int $accountId): bool {
    try {
        $db = getDB();

        // Verify organizer owns booking (via event_owners) and status is 'confirmed'
        $stmt = $db->prepare("
            SELECT b.id, b.status
            FROM bookings b
            JOIN event_owners eo ON eo.event_id = b.event_id AND eo.account_id = ? AND eo.role = 'owner'
            WHERE b.id = ?
            LIMIT 1
        ");
        $stmt->execute([$accountId, $bookingId]);
        $booking = $stmt->fetch();

        if (!$booking || $booking['status'] !== 'confirmed') {
            return false;
        }

        $stmt = $db->prepare("
            UPDATE bookings SET status = 'completed', completed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$bookingId]);

        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("completeBooking failed: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// 7. cancelBooking
// ============================================================

/**
 * Cancel a booking. Only allowed from certain statuses.
 *
 * @param int    $bookingId   Booking to cancel
 * @param string $cancelledBy Who is cancelling: 'organizer', 'vendor', or 'platform'
 * @param string $reason      Reason for cancellation
 * @return bool True on success
 */
function cancelBooking(int $bookingId, string $cancelledBy, string $reason): bool {
    try {
        // Validate cancelledBy value
        $allowedCancellers = ['organizer', 'vendor', 'platform'];
        if (!in_array($cancelledBy, $allowedCancellers, true)) {
            return false;
        }

        $db = getDB();

        // Only allow cancellation from certain statuses
        $allowedStatuses = ['requested', 'quoted', 'accepted'];

        $stmt = $db->prepare("
            SELECT id, status FROM bookings
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();

        if (!$booking || !in_array($booking['status'], $allowedStatuses, true)) {
            return false;
        }

        $stmt = $db->prepare("
            UPDATE bookings SET
                status = 'cancelled',
                cancelled_at = NOW(),
                cancelled_by = ?,
                cancel_reason = ?
            WHERE id = ?
        ");
        $stmt->execute([$cancelledBy, $reason, $bookingId]);

        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("cancelBooking failed: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// 8. getBooking
// ============================================================

/**
 * Fetch a single booking with related vendor, event, service, and account data.
 *
 * @param int $bookingId Booking to fetch
 * @return array|null Booking data or null if not found
 */
function getBooking(int $bookingId): ?array {
    try {
        $db = getDB();

        $stmt = $db->prepare("
            SELECT
                b.*,
                v.company_name AS vendor_company_name,
                v.email AS vendor_email,
                v.phone AS vendor_phone,
                v.city AS vendor_city,
                v.stripe_account_id AS vendor_stripe_account_id,
                e.name AS event_title,
                vs.title AS service_title,
                vs.price_from AS service_price_from,
                a.name AS account_name,
                a.email AS account_email
            FROM bookings b
            JOIN vendors v ON v.id = b.vendor_id
            JOIN events e ON e.id = b.event_id
            LEFT JOIN vendor_services vs ON vs.id = b.vendor_service_id
            JOIN accounts a ON a.id = b.account_id
            WHERE b.id = ?
            LIMIT 1
        ");
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();

        return $booking ?: null;
    } catch (Exception $e) {
        error_log("getBooking failed: " . $e->getMessage());
        return null;
    }
}

// ============================================================
// 9. getEventBookings
// ============================================================

/**
 * Get all bookings for an event, with vendor and service info.
 *
 * @param int $eventId Event ID
 * @return array List of bookings
 */
function getEventBookings(int $eventId): array {
    try {
        $db = getDB();

        $stmt = $db->prepare("
            SELECT
                b.*,
                v.company_name AS vendor_company_name,
                vs.title AS service_title
            FROM bookings b
            JOIN vendors v ON v.id = b.vendor_id
            LEFT JOIN vendor_services vs ON vs.id = b.vendor_service_id
            WHERE b.event_id = ?
            ORDER BY b.created_at DESC
        ");
        $stmt->execute([$eventId]);

        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("getEventBookings failed: " . $e->getMessage());
        return [];
    }
}

// ============================================================
// 10. getVendorBookings
// ============================================================

/**
 * Get all bookings for a vendor, with optional status filter.
 *
 * @param int         $vendorId Vendor ID
 * @param string|null $status   Optional status filter
 * @return array List of bookings
 */
function getVendorBookings(int $vendorId, ?string $status = null): array {
    try {
        $db = getDB();

        $sql = "
            SELECT
                b.*,
                e.name AS event_title,
                e.event_date AS event_event_date,
                a.name AS account_name
            FROM bookings b
            JOIN events e ON e.id = b.event_id
            JOIN accounts a ON a.id = b.account_id
            WHERE b.vendor_id = ?
        ";
        $params = [$vendorId];

        if ($status !== null) {
            $sql .= " AND b.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY b.created_at DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("getVendorBookings failed: " . $e->getMessage());
        return [];
    }
}

// ============================================================
// 11. sendBookingMessage
// ============================================================

/**
 * Send a message in a booking's message thread.
 *
 * @param int    $bookingId  Booking to send message for
 * @param string $senderType Who is sending: 'organizer', 'vendor', or 'platform'
 * @param string $message    The message text
 * @return bool True on success
 */
function sendBookingMessage(int $bookingId, string $senderType, string $message): bool {
    try {
        // Validate sender type
        $allowedSenderTypes = ['organizer', 'vendor', 'platform'];
        if (!in_array($senderType, $allowedSenderTypes, true)) {
            return false;
        }

        if (empty(trim($message))) {
            return false;
        }

        $db = getDB();

        $stmt = $db->prepare("
            INSERT INTO booking_messages (booking_id, sender_type, message)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$bookingId, $senderType, $message]);

        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("sendBookingMessage failed: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// 12. getBookingMessages
// ============================================================

/**
 * Get all messages for a booking, ordered chronologically.
 *
 * @param int $bookingId Booking to get messages for
 * @return array List of messages
 */
function getBookingMessages(int $bookingId): array {
    try {
        $db = getDB();

        $stmt = $db->prepare("
            SELECT * FROM booking_messages
            WHERE booking_id = ?
            ORDER BY created_at ASC
        ");
        $stmt->execute([$bookingId]);

        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("getBookingMessages failed: " . $e->getMessage());
        return [];
    }
}

// ============================================================
// 13. markMessagesRead
// ============================================================

/**
 * Mark messages as read for a given reader.
 * When the vendor reads, mark organizer's and platform's messages as read (and vice versa).
 *
 * @param int    $bookingId  Booking whose messages to mark
 * @param string $readerType Who is reading: 'organizer' or 'vendor'
 * @return void
 */
function markMessagesRead(int $bookingId, string $readerType): void {
    try {
        $db = getDB();

        $stmt = $db->prepare("
            UPDATE booking_messages
            SET is_read = 1
            WHERE booking_id = ? AND sender_type != ?
        ");
        $stmt->execute([$bookingId, $readerType]);
    } catch (Exception $e) {
        error_log("markMessagesRead failed: " . $e->getMessage());
    }
}

// ============================================================
// 14. syncBookingToBudget
// ============================================================

/**
 * Map a vendor category slug to a budget_items category key.
 * Budget categories: lokale, mad, pynt, toj, gaver, underholdning, foto, andet
 *
 * @param string $vendorCategorySlug Vendor category slug
 * @return string Budget category key
 */
function mapVendorCategoryToBudget(string $vendorCategorySlug): string {
    $map = [
        'teltudlejning' => 'lokale',
        'catering'      => 'mad',
        'festlokaler'   => 'lokale',
        'dj-musik'      => 'underholdning',
        'fotograf'      => 'foto',
        'blomster'      => 'pynt',
        'kage-dessert'  => 'mad',
        'underholdning' => 'underholdning',
        'transport'     => 'andet',
        'andet'         => 'andet',
    ];
    return $map[$vendorCategorySlug] ?? 'andet';
}

/**
 * Sync a marketplace booking to the budget_items table.
 * Called when a booking transitions to 'deposited' or 'confirmed'.
 *
 * Looks up (or creates) a budget_item whose title starts with
 * "Leverandor: {vendor_company_name}" for the same event.
 *
 * @param int $bookingId Booking to sync
 * @return void
 */
function syncBookingToBudget(int $bookingId): void {
    try {
        $db = getDB();

        // Fetch booking with vendor info and category
        $stmt = $db->prepare("
            SELECT
                b.event_id,
                b.quoted_price,
                b.depositum_amount,
                b.status,
                v.company_name AS vendor_company_name,
                vs.title AS service_title,
                (
                    SELECT vc.slug
                    FROM vendor_category_links vcl
                    JOIN vendor_categories vc ON vc.id = vcl.category_id
                    WHERE vcl.vendor_id = b.vendor_id
                    LIMIT 1
                ) AS vendor_category_slug
            FROM bookings b
            JOIN vendors v ON v.id = b.vendor_id
            LEFT JOIN vendor_services vs ON vs.id = b.vendor_service_id
            WHERE b.id = ?
            LIMIT 1
        ");
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();

        if (!$booking) {
            return;
        }

        $companyName = $booking['vendor_company_name'];
        $serviceTitle = $booking['service_title'] ?? '';
        $titlePrefix = "Leverandor: " . $companyName;
        $fullTitle = $serviceTitle
            ? "Leverandor: {$companyName} - {$serviceTitle}"
            : "Leverandor: {$companyName}";

        $budgetCategory = mapVendorCategoryToBudget($booking['vendor_category_slug'] ?? '');

        $estimatedAmount = (float)($booking['quoted_price'] ?? 0);
        $actualAmount = (float)($booking['depositum_amount'] ?? 0);
        $isPaid = in_array($booking['status'], ['deposited', 'confirmed', 'completed', 'reviewed'], true) ? 1 : 0;

        // Check if a budget_item already exists for this booking (match by title prefix + event)
        $stmt = $db->prepare("
            SELECT id FROM budget_items
            WHERE event_id = ? AND title LIKE CONCAT(?, '%')
            LIMIT 1
        ");
        $stmt->execute([$booking['event_id'], $titlePrefix]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Update existing budget item
            $stmt = $db->prepare("
                UPDATE budget_items
                SET title = ?,
                    category = ?,
                    estimated_cost = ?,
                    actual_cost = ?,
                    is_paid = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $fullTitle,
                $budgetCategory,
                $estimatedAmount,
                $actualAmount,
                $isPaid,
                $existing['id']
            ]);
        } else {
            // Insert new budget item
            $stmt = $db->prepare("
                INSERT INTO budget_items (event_id, title, category, estimated_cost, actual_cost, is_paid)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $booking['event_id'],
                $fullTitle,
                $budgetCategory,
                $estimatedAmount,
                $actualAmount,
                $isPaid
            ]);
        }
    } catch (Exception $e) {
        error_log("syncBookingToBudget failed: " . $e->getMessage());
    }
}

// ============================================================
// 15. syncManualVendorToBudget
// ============================================================

/**
 * Map a manual vendor's free-text category to a budget_items category key.
 *
 * @param string $manualCategory Free-text category from manual_vendor
 * @return string Budget category key
 */
function mapManualCategoryToBudget(string $manualCategory): string {
    $lower = mb_strtolower(trim($manualCategory));

    // Map common Danish terms to budget categories
    $keywords = [
        'lokale'          => 'lokale',
        'venue'           => 'lokale',
        'sal'             => 'lokale',
        'telt'            => 'lokale',
        'catering'        => 'mad',
        'mad'             => 'mad',
        'kage'            => 'mad',
        'dessert'         => 'mad',
        'drikke'          => 'mad',
        'bar'             => 'mad',
        'kok'             => 'mad',
        'pynt'            => 'pynt',
        'dekoration'      => 'pynt',
        'blomst'          => 'pynt',
        'toj'             => 'toj',
        'kjole'           => 'toj',
        'gave'            => 'gaver',
        'dj'              => 'underholdning',
        'musik'           => 'underholdning',
        'band'            => 'underholdning',
        'underholdning'   => 'underholdning',
        'show'            => 'underholdning',
        'fotograf'        => 'foto',
        'foto'            => 'foto',
        'video'           => 'foto',
    ];

    foreach ($keywords as $keyword => $budgetCat) {
        if (strpos($lower, $keyword) !== false) {
            return $budgetCat;
        }
    }

    return 'andet';
}

/**
 * Sync a manual vendor entry to the budget_items table.
 * Called when a manual vendor is added or updated.
 *
 * @param int $manualVendorId Manual vendor ID to sync
 * @return void
 */
function syncManualVendorToBudget(int $manualVendorId): void {
    try {
        $db = getDB();

        // Fetch manual vendor data
        $stmt = $db->prepare("
            SELECT * FROM manual_vendors
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$manualVendorId]);
        $vendor = $stmt->fetch();

        if (!$vendor) {
            return;
        }

        $companyName = $vendor['company_name'];
        $titlePrefix = "Leverandor: " . $companyName;
        $fullTitle = $titlePrefix;

        $budgetCategory = mapManualCategoryToBudget($vendor['category'] ?? '');

        $agreedPrice = (float)($vendor['agreed_price'] ?? 0);
        $isPaid = (int)($vendor['is_paid'] ?? 0);
        $actualAmount = $isPaid ? $agreedPrice : null;

        // Check if a budget_item already exists for this manual vendor
        $stmt = $db->prepare("
            SELECT id FROM budget_items
            WHERE event_id = ? AND title = ?
            LIMIT 1
        ");
        $stmt->execute([$vendor['event_id'], $fullTitle]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Update existing budget item
            $stmt = $db->prepare("
                UPDATE budget_items
                SET category = ?,
                    estimated_cost = ?,
                    actual_cost = ?,
                    is_paid = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $budgetCategory,
                $agreedPrice,
                $actualAmount,
                $isPaid,
                $existing['id']
            ]);
        } else {
            // Insert new budget item
            $stmt = $db->prepare("
                INSERT INTO budget_items (event_id, title, category, estimated_cost, actual_cost, is_paid)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $vendor['event_id'],
                $fullTitle,
                $budgetCategory,
                $agreedPrice,
                $actualAmount,
                $isPaid
            ]);
        }
    } catch (Exception $e) {
        error_log("syncManualVendorToBudget failed: " . $e->getMessage());
    }
}

// ============================================================
// 16. updateBudgetPaymentStatus
// ============================================================

/**
 * Update the payment status of a budget_item linked to a booking.
 * Called when a booking's payment status changes.
 *
 * @param int $bookingId Booking whose budget item should be updated
 * @return void
 */
function updateBudgetPaymentStatus(int $bookingId): void {
    try {
        $db = getDB();

        // Fetch booking with vendor name
        $stmt = $db->prepare("
            SELECT
                b.event_id,
                b.status,
                b.depositum_amount,
                v.company_name AS vendor_company_name
            FROM bookings b
            JOIN vendors v ON v.id = b.vendor_id
            WHERE b.id = ?
            LIMIT 1
        ");
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();

        if (!$booking) {
            return;
        }

        $titlePrefix = "Leverandor: " . $booking['vendor_company_name'];

        // Find the linked budget item
        $stmt = $db->prepare("
            SELECT id FROM budget_items
            WHERE event_id = ? AND title LIKE CONCAT(?, '%')
            LIMIT 1
        ");
        $stmt->execute([$booking['event_id'], $titlePrefix]);
        $budgetItem = $stmt->fetch();

        if (!$budgetItem) {
            return; // No budget item to update
        }

        // Determine payment status from booking status
        $isPaid = in_array($booking['status'], ['deposited', 'confirmed', 'completed', 'reviewed'], true) ? 1 : 0;
        $actualAmount = $isPaid ? (float)($booking['depositum_amount'] ?? 0) : null;

        $stmt = $db->prepare("
            UPDATE budget_items
            SET is_paid = ?,
                actual_cost = ?
            WHERE id = ?
        ");
        $stmt->execute([$isPaid, $actualAmount, $budgetItem['id']]);
    } catch (Exception $e) {
        error_log("updateBudgetPaymentStatus failed: " . $e->getMessage());
    }
}

<?php
/**
 * Partner Commission System
 * Handles booking registration, commission calculation, and payout management
 */

/**
 * Generate a unique booking reference
 */
function generateBookingReference(): string {
    $prefix = 'BK';
    $year = date('y');
    $random = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    return "{$prefix}{$year}-{$random}";
}

/**
 * Generate a unique payout reference
 */
function generatePayoutReference(): string {
    $prefix = 'PO';
    $year = date('y');
    $month = date('m');
    $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
    return "{$prefix}{$year}{$month}-{$random}";
}

/**
 * Get commission rate for a partner
 */
function getPartnerCommissionRate(PDO $db, int $partnerId): float {
    // Check partner-specific rate first
    $stmt = $db->prepare("
        SELECT commission_rate FROM partner_commission_settings
        WHERE partner_id = ?
    ");
    $stmt->execute([$partnerId]);
    $rate = $stmt->fetchColumn();

    if ($rate !== false) {
        return (float)$rate;
    }

    // Fall back to platform default
    $stmt = $db->prepare("
        SELECT setting_value FROM platform_settings
        WHERE setting_key = 'default_commission_rate'
    ");
    $stmt->execute();
    $default = $stmt->fetchColumn();

    return $default !== false ? (float)$default : 10.00;
}

/**
 * Calculate commission amounts for a booking
 */
function calculateCommission(float $totalAmount, float $commissionRate): array {
    $commissionAmount = round($totalAmount * ($commissionRate / 100), 2);
    $partnerAmount = round($totalAmount - $commissionAmount, 2);

    return [
        'total_amount' => $totalAmount,
        'commission_rate' => $commissionRate,
        'commission_amount' => $commissionAmount,
        'partner_amount' => $partnerAmount
    ];
}

/**
 * Create a new booking
 */
function createBooking(PDO $db, array $data): ?int {
    $partnerId = $data['partner_id'];
    $commissionRate = $data['commission_rate'] ?? getPartnerCommissionRate($db, $partnerId);
    $commission = calculateCommission($data['total_amount'], $commissionRate);

    $stmt = $db->prepare("
        INSERT INTO partner_bookings (
            partner_id, inquiry_id, event_id, account_id,
            booking_reference, service_description, event_date, guest_count,
            total_amount, commission_rate, commission_amount, partner_amount,
            status, customer_name, customer_email, customer_phone, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    try {
        $stmt->execute([
            $partnerId,
            $data['inquiry_id'] ?? null,
            $data['event_id'] ?? null,
            $data['account_id'] ?? null,
            generateBookingReference(),
            $data['service_description'],
            $data['event_date'],
            $data['guest_count'] ?? null,
            $commission['total_amount'],
            $commission['commission_rate'],
            $commission['commission_amount'],
            $commission['partner_amount'],
            $data['status'] ?? 'pending',
            $data['customer_name'],
            $data['customer_email'],
            $data['customer_phone'] ?? null,
            $data['notes'] ?? null
        ]);

        $bookingId = (int)$db->lastInsertId();

        // Update partner inquiry count
        $db->prepare("
            UPDATE partners SET inquiry_count = inquiry_count + 1 WHERE id = ?
        ")->execute([$partnerId]);

        return $bookingId;
    } catch (Exception $e) {
        error_log("Failed to create booking: " . $e->getMessage());
        return null;
    }
}

/**
 * Update booking status
 */
function updateBookingStatus(PDO $db, int $bookingId, string $status, ?string $reason = null): bool {
    $validStatuses = ['pending', 'confirmed', 'completed', 'cancelled', 'refunded'];
    if (!in_array($status, $validStatuses)) {
        return false;
    }

    $timestampFields = [
        'confirmed' => 'confirmed_at',
        'completed' => 'completed_at',
        'cancelled' => 'cancelled_at'
    ];
    $timestampField = $timestampFields[$status] ?? null;

    $sql = "UPDATE partner_bookings SET status = ?";
    $params = [$status];

    if ($timestampField) {
        $sql .= ", {$timestampField} = NOW()";
    }

    if ($status === 'cancelled' && $reason) {
        $sql .= ", cancellation_reason = ?";
        $params[] = $reason;
    }

    $sql .= " WHERE id = ?";
    $params[] = $bookingId;

    $stmt = $db->prepare($sql);
    return $stmt->execute($params);
}

/**
 * Get booking by ID
 */
function getBooking(PDO $db, int $bookingId): ?array {
    $stmt = $db->prepare("
        SELECT b.*, p.company_name as partner_name, pc.name as category_name
        FROM partner_bookings b
        JOIN partners p ON b.partner_id = p.id
        LEFT JOIN partner_categories pc ON p.category_id = pc.id
        WHERE b.id = ?
    ");
    $stmt->execute([$bookingId]);
    $result = $stmt->fetch();
    return $result ?: null;
}

/**
 * Get bookings for a partner
 */
function getPartnerBookings(PDO $db, int $partnerId, array $filters = []): array {
    $sql = "
        SELECT b.*
        FROM partner_bookings b
        WHERE b.partner_id = ?
    ";
    $params = [$partnerId];

    if (!empty($filters['status'])) {
        $sql .= " AND b.status = ?";
        $params[] = $filters['status'];
    }

    if (!empty($filters['commission_status'])) {
        $sql .= " AND b.commission_status = ?";
        $params[] = $filters['commission_status'];
    }

    if (!empty($filters['from_date'])) {
        $sql .= " AND b.event_date >= ?";
        $params[] = $filters['from_date'];
    }

    if (!empty($filters['to_date'])) {
        $sql .= " AND b.event_date <= ?";
        $params[] = $filters['to_date'];
    }

    $sql .= " ORDER BY b.created_at DESC";

    if (!empty($filters['limit'])) {
        $sql .= " LIMIT " . (int)$filters['limit'];
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get commission summary for a partner
 */
function getPartnerCommissionSummary(PDO $db, int $partnerId): array {
    $stmt = $db->prepare("
        SELECT
            COUNT(*) as total_bookings,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
            SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END) as total_revenue,
            SUM(CASE WHEN status = 'completed' AND commission_status = 'pending' THEN commission_amount ELSE 0 END) as pending_commission,
            SUM(CASE WHEN commission_status = 'paid' THEN commission_amount ELSE 0 END) as paid_commission
        FROM partner_bookings
        WHERE partner_id = ?
    ");
    $stmt->execute([$partnerId]);
    $result = $stmt->fetch();

    return [
        'total_bookings' => (int)($result['total_bookings'] ?? 0),
        'completed_bookings' => (int)($result['completed_bookings'] ?? 0),
        'total_revenue' => (float)($result['total_revenue'] ?? 0),
        'pending_commission' => (float)($result['pending_commission'] ?? 0),
        'paid_commission' => (float)($result['paid_commission'] ?? 0),
        'commission_rate' => getPartnerCommissionRate($db, $partnerId)
    ];
}

/**
 * Create a payout for a partner
 */
function createPayout(PDO $db, int $partnerId, string $periodStart, string $periodEnd, ?int $createdBy = null): ?int {
    // Get all completed bookings in period that haven't been paid
    $stmt = $db->prepare("
        SELECT id, commission_amount
        FROM partner_bookings
        WHERE partner_id = ?
          AND status = 'completed'
          AND commission_status = 'pending'
          AND completed_at >= ?
          AND completed_at < DATE_ADD(?, INTERVAL 1 DAY)
    ");
    $stmt->execute([$partnerId, $periodStart, $periodEnd]);
    $bookings = $stmt->fetchAll();

    if (empty($bookings)) {
        return null; // No bookings to include
    }

    $totalCommission = array_sum(array_column($bookings, 'commission_amount'));
    $totalBookingValue = 0;

    // Get total booking value
    $stmt = $db->prepare("
        SELECT SUM(total_amount) as total
        FROM partner_bookings
        WHERE partner_id = ?
          AND status = 'completed'
          AND commission_status = 'pending'
          AND completed_at >= ?
          AND completed_at < DATE_ADD(?, INTERVAL 1 DAY)
    ");
    $stmt->execute([$partnerId, $periodStart, $periodEnd]);
    $totalBookingValue = (float)$stmt->fetchColumn();

    // Get payment terms
    $stmt = $db->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = 'commission_payment_terms'");
    $stmt->execute();
    $paymentTerms = (int)($stmt->fetchColumn() ?: 14);

    $dueDate = date('Y-m-d', strtotime("+{$paymentTerms} days"));

    $db->beginTransaction();
    try {
        // Create payout record
        $stmt = $db->prepare("
            INSERT INTO partner_payouts (
                partner_id, payout_reference, period_start, period_end,
                total_bookings, total_booking_value, commission_amount,
                final_amount, due_date, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $partnerId,
            generatePayoutReference(),
            $periodStart,
            $periodEnd,
            count($bookings),
            $totalBookingValue,
            $totalCommission,
            $totalCommission,
            $dueDate,
            $createdBy
        ]);

        $payoutId = (int)$db->lastInsertId();

        // Link bookings to payout
        $stmt = $db->prepare("
            INSERT INTO partner_payout_items (payout_id, booking_id, commission_amount)
            VALUES (?, ?, ?)
        ");
        foreach ($bookings as $booking) {
            $stmt->execute([$payoutId, $booking['id'], $booking['commission_amount']]);
        }

        // Update booking commission status
        $bookingIds = array_column($bookings, 'id');
        $placeholders = implode(',', array_fill(0, count($bookingIds), '?'));
        $db->prepare("
            UPDATE partner_bookings
            SET commission_status = 'invoiced'
            WHERE id IN ($placeholders)
        ")->execute($bookingIds);

        $db->commit();
        return $payoutId;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Failed to create payout: " . $e->getMessage());
        return null;
    }
}

/**
 * Mark payout as paid
 */
function markPayoutPaid(PDO $db, int $payoutId, string $paymentMethod, ?string $paymentReference = null): bool {
    $db->beginTransaction();
    try {
        // Update payout
        $stmt = $db->prepare("
            UPDATE partner_payouts
            SET status = 'paid', paid_at = NOW(), payment_method = ?, payment_reference = ?
            WHERE id = ?
        ");
        $stmt->execute([$paymentMethod, $paymentReference, $payoutId]);

        // Update all linked bookings
        $db->prepare("
            UPDATE partner_bookings pb
            JOIN partner_payout_items ppi ON pb.id = ppi.booking_id
            SET pb.commission_status = 'paid'
            WHERE ppi.payout_id = ?
        ")->execute([$payoutId]);

        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Failed to mark payout as paid: " . $e->getMessage());
        return false;
    }
}

/**
 * Get payouts for a partner
 */
function getPartnerPayouts(PDO $db, int $partnerId, int $limit = 20): array {
    $stmt = $db->prepare("
        SELECT * FROM partner_payouts
        WHERE partner_id = ?
        ORDER BY created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$partnerId, $limit]);
    return $stmt->fetchAll();
}

/**
 * Format amount in DKK
 */
function formatDKK(float $amount): string {
    return number_format($amount, 2, ',', '.') . ' kr.';
}

/**
 * Ensure commission tables exist
 */
function ensureCommissionTables(PDO $db): void {
    static $checked = false;
    if ($checked) return;

    try {
        // Check if partner_bookings exists
        $result = $db->query("SHOW TABLES LIKE 'partner_bookings'")->rowCount();
        if ($result === 0) {
            // Run migration
            $migrationPath = __DIR__ . '/../database/migrations/004_partner_commission.sql';
            if (file_exists($migrationPath)) {
                $sql = file_get_contents($migrationPath);
                // Split by semicolon and execute each statement
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                foreach ($statements as $statement) {
                    if (!empty($statement) && !str_starts_with($statement, '--')) {
                        try {
                            $db->exec($statement);
                        } catch (Exception $e) {
                            // Ignore errors for existing objects
                        }
                    }
                }
            }
        }
        $checked = true;
    } catch (Exception $e) {
        error_log("Failed to ensure commission tables: " . $e->getMessage());
        $checked = true;
    }
}

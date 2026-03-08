<?php
/**
 * Commission Service
 * Handles commission calculation and financial aggregation for vendors and the platform.
 */

require_once __DIR__ . '/../../config/database.php';

// Financial constants (may already be defined by booking-service.php)
if (!defined('DEPOSITUM_RATE')) define('DEPOSITUM_RATE', 0.25);
if (!defined('COMMISSION_RATE')) define('COMMISSION_RATE', 0.15);

// ============================================================
// 1. calculateDepositum
// ============================================================

/**
 * Calculate the depositum (deposit) amount from a quoted price.
 *
 * @param float $quotedPrice The vendor's total quoted price
 * @return float The depositum amount (25% of quoted price)
 */
function calculateDepositum(float $quotedPrice): float {
    return round($quotedPrice * DEPOSITUM_RATE, 2);
}

// ============================================================
// 2. calculateCommission
// ============================================================

/**
 * Calculate the platform commission from a depositum amount.
 *
 * @param float $depositumAmount The depositum amount
 * @return float The commission amount (15% of depositum)
 */
function calculateCommission(float $depositumAmount): float {
    return round($depositumAmount * COMMISSION_RATE, 2);
}

// ============================================================
// 3. calculateVendorPayout
// ============================================================

/**
 * Calculate the vendor payout from a depositum amount.
 *
 * @param float $depositumAmount The depositum amount
 * @return float The vendor payout (85% of depositum)
 */
function calculateVendorPayout(float $depositumAmount): float {
    return round($depositumAmount - calculateCommission($depositumAmount), 2);
}

// ============================================================
// 4. getVendorEarnings
// ============================================================

/**
 * Get aggregated earnings for a specific vendor from completed/reviewed bookings.
 *
 * @param int         $vendorId  Vendor ID to aggregate for
 * @param string|null $fromDate  Optional start date filter (Y-m-d) on completed_at
 * @param string|null $toDate    Optional end date filter (Y-m-d) on completed_at
 * @return array Aggregated earnings: total_earned, total_commission, total_paid_out, booking_count
 */
function getVendorEarnings(int $vendorId, ?string $fromDate = null, ?string $toDate = null): array {
    $default = [
        'total_earned' => 0.0,
        'total_commission' => 0.0,
        'total_paid_out' => 0.0,
        'booking_count' => 0
    ];

    try {
        $db = getDB();

        $sql = "
            SELECT
                COALESCE(SUM(depositum_amount), 0) AS total_earned,
                COALESCE(SUM(commission_amount), 0) AS total_commission,
                COALESCE(SUM(vendor_payout), 0) AS total_paid_out,
                COUNT(*) AS booking_count
            FROM bookings
            WHERE vendor_id = ?
              AND status IN ('completed', 'reviewed')
        ";
        $params = [$vendorId];

        if ($fromDate !== null) {
            $sql .= " AND completed_at >= ?";
            $params[] = $fromDate;
        }

        if ($toDate !== null) {
            $sql .= " AND completed_at <= ?";
            $params[] = $toDate;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        if (!$row || $row['booking_count'] == 0) {
            return $default;
        }

        return [
            'total_earned' => round((float)$row['total_earned'], 2),
            'total_commission' => round((float)$row['total_commission'], 2),
            'total_paid_out' => round((float)$row['total_paid_out'], 2),
            'booking_count' => (int)$row['booking_count']
        ];
    } catch (Exception $e) {
        error_log("getVendorEarnings failed: " . $e->getMessage());
        return $default;
    }
}

// ============================================================
// 5. getPlatformRevenue
// ============================================================

/**
 * Get aggregated platform revenue across all qualifying bookings.
 *
 * @param string|null $fromDate Optional start date filter (Y-m-d) on paid_at
 * @param string|null $toDate   Optional end date filter (Y-m-d) on paid_at
 * @return array Aggregated revenue: total_bookings, total_depositum, total_commission, total_vendor_payouts
 */
function getPlatformRevenue(?string $fromDate = null, ?string $toDate = null): array {
    $default = [
        'total_bookings' => 0,
        'total_depositum' => 0.0,
        'total_commission' => 0.0,
        'total_vendor_payouts' => 0.0
    ];

    try {
        $db = getDB();

        $sql = "
            SELECT
                COUNT(*) AS total_bookings,
                COALESCE(SUM(depositum_amount), 0) AS total_depositum,
                COALESCE(SUM(commission_amount), 0) AS total_commission,
                COALESCE(SUM(vendor_payout), 0) AS total_vendor_payouts
            FROM bookings
            WHERE status IN ('completed', 'reviewed', 'deposited', 'confirmed')
        ";
        $params = [];

        if ($fromDate !== null) {
            $sql .= " AND paid_at >= ?";
            $params[] = $fromDate;
        }

        if ($toDate !== null) {
            $sql .= " AND paid_at <= ?";
            $params[] = $toDate;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        if (!$row || $row['total_bookings'] == 0) {
            return $default;
        }

        return [
            'total_bookings' => (int)$row['total_bookings'],
            'total_depositum' => round((float)$row['total_depositum'], 2),
            'total_commission' => round((float)$row['total_commission'], 2),
            'total_vendor_payouts' => round((float)$row['total_vendor_payouts'], 2)
        ];
    } catch (Exception $e) {
        error_log("getPlatformRevenue failed: " . $e->getMessage());
        return $default;
    }
}

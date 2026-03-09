<?php
/**
 * Referral/Agent Service
 * Handles agent registration, referral tracking, and provision calculations.
 */

require_once __DIR__ . '/../../config/database.php';

// Default rates (can be overridden in config/saas.php)
if (!defined('DEFAULT_COMMISSION_RATE')) define('DEFAULT_COMMISSION_RATE', 0.15);
if (!defined('DEFAULT_AGENT_PROVISION_RATE')) define('DEFAULT_AGENT_PROVISION_RATE', 0.01);
if (!defined('AGENT_PROVISION_MONTHS')) define('AGENT_PROVISION_MONTHS', 12);

// ============================================================
// 1. generateReferralCode
// ============================================================

/**
 * Generate a unique 8-character uppercase alphanumeric referral code.
 *
 * @return string 8-char code e.g. "A3K9X2BF"
 */
function generateReferralCode(): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Avoid ambiguous: 0/O, 1/I
    $code = '';
    for ($i = 0; $i < 8; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

// ============================================================
// 2. createAgent
// ============================================================

/**
 * Create a referral agent linked to an account.
 *
 * @param int        $accountId     Account to make an agent
 * @param float|null $provisionRate Override provision rate, or NULL for default
 * @param string|null $notes        Optional admin notes
 * @return array ['success' => true, 'agent' => [...]] or ['success' => false, 'error' => '...']
 */
function createAgent(int $accountId, ?float $provisionRate = null, ?string $notes = null): array {
    try {
        $db = getDB();

        // Check if account already is an agent
        $stmt = $db->prepare("SELECT id FROM referral_agents WHERE account_id = ? LIMIT 1");
        $stmt->execute([$accountId]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Kontoen er allerede registreret som agent'];
        }

        // Generate unique referral code (retry on collision)
        $maxAttempts = 10;
        $code = null;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $candidate = generateReferralCode();
            $stmt = $db->prepare("SELECT id FROM referral_agents WHERE referral_code = ? LIMIT 1");
            $stmt->execute([$candidate]);
            if (!$stmt->fetch()) {
                $code = $candidate;
                break;
            }
        }

        if ($code === null) {
            return ['success' => false, 'error' => 'Kunne ikke generere unik henvisningskode'];
        }

        $stmt = $db->prepare("
            INSERT INTO referral_agents (account_id, referral_code, provision_rate, notes)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$accountId, $code, $provisionRate, $notes]);
        $agentId = (int)$db->lastInsertId();

        // Fetch the created record
        $stmt = $db->prepare("SELECT * FROM referral_agents WHERE id = ? LIMIT 1");
        $stmt->execute([$agentId]);
        $agent = $stmt->fetch();

        return ['success' => true, 'agent' => $agent];
    } catch (Exception $e) {
        error_log("createAgent failed: " . $e->getMessage());
        return ['success' => false, 'error' => 'Kunne ikke oprette agent'];
    }
}

// ============================================================
// 3. deactivateAgent
// ============================================================

/**
 * Deactivate a referral agent.
 *
 * @param int $agentId Agent ID
 * @return bool True on success
 */
function deactivateAgent(int $agentId): bool {
    try {
        $db = getDB();
        $stmt = $db->prepare("UPDATE referral_agents SET is_active = 0 WHERE id = ?");
        $stmt->execute([$agentId]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("deactivateAgent failed: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// 4. activateAgent
// ============================================================

/**
 * Activate a referral agent.
 *
 * @param int $agentId Agent ID
 * @return bool True on success
 */
function activateAgent(int $agentId): bool {
    try {
        $db = getDB();
        $stmt = $db->prepare("UPDATE referral_agents SET is_active = 1 WHERE id = ?");
        $stmt->execute([$agentId]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("activateAgent failed: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// 5. getAgentByCode
// ============================================================

/**
 * Look up an agent by referral code, including account name and email.
 *
 * @param string $code Referral code
 * @return array|null Agent record with account info, or null
 */
function getAgentByCode(string $code): ?array {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT ra.*, a.name AS account_name, a.email AS account_email
            FROM referral_agents ra
            JOIN accounts a ON a.id = ra.account_id
            WHERE ra.referral_code = ?
            LIMIT 1
        ");
        $stmt->execute([$code]);
        $agent = $stmt->fetch();
        return $agent ?: null;
    } catch (Exception $e) {
        error_log("getAgentByCode failed: " . $e->getMessage());
        return null;
    }
}

// ============================================================
// 6. getAgentByAccountId
// ============================================================

/**
 * Look up an agent by account ID.
 *
 * @param int $accountId Account ID
 * @return array|null Agent record or null
 */
function getAgentByAccountId(int $accountId): ?array {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM referral_agents WHERE account_id = ? LIMIT 1");
        $stmt->execute([$accountId]);
        $agent = $stmt->fetch();
        return $agent ?: null;
    } catch (Exception $e) {
        error_log("getAgentByAccountId failed: " . $e->getMessage());
        return null;
    }
}

// ============================================================
// 7. recordReferral
// ============================================================

/**
 * Record a referral link between an agent and a newly registered vendor.
 * Locks the provision rate at the time of referral.
 *
 * @param int $agentId  Agent who referred
 * @param int $vendorId Vendor who was referred
 * @return array|null Created referral_link record, or null on error
 */
function recordReferral(int $agentId, int $vendorId): ?array {
    try {
        $db = getDB();

        // Get agent's provision rate (or default)
        $stmt = $db->prepare("SELECT provision_rate FROM referral_agents WHERE id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$agentId]);
        $agent = $stmt->fetch();

        if (!$agent) {
            error_log("recordReferral: agent $agentId not found or inactive");
            return null;
        }

        $provisionRate = $agent['provision_rate'] !== null
            ? (float)$agent['provision_rate']
            : DEFAULT_AGENT_PROVISION_RATE;

        $provisionUntil = date('Y-m-d', strtotime('+' . AGENT_PROVISION_MONTHS . ' months'));

        $stmt = $db->prepare("
            INSERT INTO referral_links (agent_id, vendor_id, provision_rate, provision_until)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$agentId, $vendorId, $provisionRate, $provisionUntil]);
        $linkId = (int)$db->lastInsertId();

        $stmt = $db->prepare("SELECT * FROM referral_links WHERE id = ? LIMIT 1");
        $stmt->execute([$linkId]);
        return $stmt->fetch() ?: null;
    } catch (Exception $e) {
        error_log("recordReferral failed: " . $e->getMessage());
        return null;
    }
}

// ============================================================
// 8. getReferralsByAgent
// ============================================================

/**
 * Get all referral links for an agent, with vendor info.
 *
 * @param int $agentId Agent ID
 * @return array List of referral links with vendor data
 */
function getReferralsByAgent(int $agentId): array {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT
                rl.*,
                v.company_name,
                v.status AS vendor_status,
                (SELECT COUNT(*) FROM bookings b WHERE b.vendor_id = rl.vendor_id AND b.status IN ('deposited','confirmed','completed','reviewed')) AS booking_count
            FROM referral_links rl
            JOIN vendors v ON v.id = rl.vendor_id
            WHERE rl.agent_id = ?
            ORDER BY rl.created_at DESC
        ");
        $stmt->execute([$agentId]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("getReferralsByAgent failed: " . $e->getMessage());
        return [];
    }
}

// ============================================================
// 9. getAgentDashboard
// ============================================================

/**
 * Get dashboard summary data for an agent.
 *
 * @param int $agentId Agent ID
 * @return array Dashboard data: total_referrals, active_vendors, total_earned, referrals
 */
function getAgentDashboard(int $agentId): array {
    try {
        $referrals = getReferralsByAgent($agentId);

        $totalReferrals = count($referrals);
        $activeVendors = 0;
        $totalEarned = 0.0;

        foreach ($referrals as $ref) {
            if ((int)$ref['booking_count'] > 0) {
                $activeVendors++;
            }
            $totalEarned += (float)$ref['total_earned'];
        }

        return [
            'total_referrals' => $totalReferrals,
            'active_vendors' => $activeVendors,
            'total_earned' => round($totalEarned, 2),
            'referrals' => $referrals
        ];
    } catch (Exception $e) {
        error_log("getAgentDashboard failed: " . $e->getMessage());
        return [
            'total_referrals' => 0,
            'active_vendors' => 0,
            'total_earned' => 0.0,
            'referrals' => []
        ];
    }
}

// ============================================================
// 10. calculateAgentProvision
// ============================================================

/**
 * Calculate agent provision for a vendor's booking depositum.
 * Returns null if vendor has no active referral link.
 *
 * @param int   $vendorId        Vendor ID
 * @param float $depositumAmount Depositum amount from booking
 * @return array|null ['amount' => float, 'referral_link_id' => int] or null
 */
function calculateAgentProvision(int $vendorId, float $depositumAmount): ?array {
    try {
        $db = getDB();

        $stmt = $db->prepare("
            SELECT id, provision_rate
            FROM referral_links
            WHERE vendor_id = ? AND provision_until >= CURDATE()
            LIMIT 1
        ");
        $stmt->execute([$vendorId]);
        $link = $stmt->fetch();

        if (!$link) {
            return null;
        }

        $amount = round($depositumAmount * (float)$link['provision_rate'], 2);

        return [
            'amount' => $amount,
            'referral_link_id' => (int)$link['id']
        ];
    } catch (Exception $e) {
        error_log("calculateAgentProvision failed: " . $e->getMessage());
        return null;
    }
}

// ============================================================
// 11. recordAgentProvision
// ============================================================

/**
 * Record a provision payment by incrementing the referral link's total_earned.
 *
 * @param int   $referralLinkId Referral link ID
 * @param float $amount         Provision amount to add
 * @return void
 */
function recordAgentProvision(int $referralLinkId, float $amount): void {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            UPDATE referral_links
            SET total_earned = total_earned + ?
            WHERE id = ?
        ");
        $stmt->execute([$amount, $referralLinkId]);
    } catch (Exception $e) {
        error_log("recordAgentProvision failed: " . $e->getMessage());
    }
}

// ============================================================
// 12. getEffectiveCommissionRate
// ============================================================

/**
 * Get the effective commission rate for a vendor.
 * Checks for per-vendor override, falls back to global default.
 *
 * @param int $vendorId Vendor ID
 * @return float Commission rate (e.g. 0.15)
 */
function getEffectiveCommissionRate(int $vendorId): float {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT commission_rate FROM vendors WHERE id = ? LIMIT 1");
        $stmt->execute([$vendorId]);
        $vendor = $stmt->fetch();

        if ($vendor && $vendor['commission_rate'] !== null) {
            return (float)$vendor['commission_rate'];
        }

        return DEFAULT_COMMISSION_RATE;
    } catch (Exception $e) {
        error_log("getEffectiveCommissionRate failed: " . $e->getMessage());
        return DEFAULT_COMMISSION_RATE;
    }
}

// ============================================================
// 13. getAllAgents
// ============================================================

/**
 * Get all referral agents with account info, referral count, and total earned.
 * For admin overview page.
 *
 * @return array List of agents
 */
function getAllAgents(): array {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT
                ra.*,
                a.name AS account_name,
                a.email AS account_email,
                COUNT(rl.id) AS referral_count,
                COALESCE(SUM(rl.total_earned), 0) AS total_earned
            FROM referral_agents ra
            JOIN accounts a ON a.id = ra.account_id
            LEFT JOIN referral_links rl ON rl.agent_id = ra.id
            GROUP BY ra.id
            ORDER BY ra.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("getAllAgents failed: " . $e->getMessage());
        return [];
    }
}

// ============================================================
// 14. getAgentDetail
// ============================================================

/**
 * Get full agent detail with account info.
 * For admin detail page.
 *
 * @param int $agentId Agent ID
 * @return array|null Agent with account info, or null
 */
function getAgentDetail(int $agentId): ?array {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT
                ra.*,
                a.name AS account_name,
                a.email AS account_email,
                a.phone AS account_phone
            FROM referral_agents ra
            JOIN accounts a ON a.id = ra.account_id
            WHERE ra.id = ?
            LIMIT 1
        ");
        $stmt->execute([$agentId]);
        $agent = $stmt->fetch();
        return $agent ?: null;
    } catch (Exception $e) {
        error_log("getAgentDetail failed: " . $e->getMessage());
        return null;
    }
}

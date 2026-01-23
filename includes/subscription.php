<?php
/**
 * Subscription & Feature Management
 */

/**
 * Get all available plans
 */
function getAllPlans(): array {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM plans WHERE is_active = TRUE ORDER BY sort_order ASC");
    $plans = $stmt->fetchAll();

    // Decode features JSON
    foreach ($plans as &$plan) {
        if ($plan['features']) {
            $plan['features'] = json_decode($plan['features'], true);
        }
    }

    return $plans;
}

/**
 * Get plan by slug
 */
function getPlanBySlug(string $slug): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM plans WHERE slug = ? AND is_active = TRUE LIMIT 1");
    $stmt->execute([$slug]);
    $plan = $stmt->fetch();

    if ($plan && $plan['features']) {
        $plan['features'] = json_decode($plan['features'], true);
    }

    return $plan ?: null;
}

/**
 * Get all event types
 */
function getAllEventTypes(): array {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM event_types WHERE is_active = TRUE ORDER BY sort_order ASC");
    return $stmt->fetchAll();
}

/**
 * Get event type by slug
 */
function getEventTypeBySlug(string $slug): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM event_types WHERE slug = ? AND is_active = TRUE LIMIT 1");
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

/**
 * Check if account can use a feature
 */
function accountCanUseFeature(int $accountId, string $feature): bool {
    $subscription = getAccountSubscription($accountId);

    if (!$subscription) {
        return false;
    }

    $features = $subscription['features'] ?? [];
    return !empty($features[$feature]);
}

/**
 * Get account's guest limit
 */
function getAccountGuestLimit(int $accountId): int {
    $subscription = getAccountSubscription($accountId);
    return $subscription['max_guests'] ?? 30;
}

/**
 * Get account's event limit
 */
function getAccountEventLimit(int $accountId): int {
    $subscription = getAccountSubscription($accountId);
    return $subscription['max_events'] ?? 1;
}

/**
 * Check if account needs upgrade for feature
 */
function needsUpgradeFor(int $accountId, string $feature): bool {
    return !accountCanUseFeature($accountId, $feature);
}

/**
 * Get recommended plan for feature
 */
function getRecommendedPlanFor(string $feature): ?array {
    $db = getDB();

    // Find cheapest plan with this feature
    $stmt = $db->query("SELECT * FROM plans WHERE is_active = TRUE ORDER BY price_monthly ASC");
    $plans = $stmt->fetchAll();

    foreach ($plans as $plan) {
        $features = json_decode($plan['features'] ?? '{}', true);
        if (!empty($features[$feature])) {
            return $plan;
        }
    }

    return null;
}

/**
 * Format plan features as human-readable list
 */
function formatPlanFeatures(array $features): array {
    $featureLabels = [
        'seating' => 'Bordplan',
        'toastmaster' => 'Toastmaster-koordinering',
        'budget' => 'Budget-styring',
        'checklist' => 'Tjekliste',
        'custom_domain' => 'Eget domæne',
        'priority_support' => 'Prioriteret support',
        'analytics' => 'Statistik & analyse'
    ];

    $formatted = [];
    foreach ($features as $key => $enabled) {
        if ($enabled && isset($featureLabels[$key])) {
            $formatted[] = $featureLabels[$key];
        }
    }

    return $formatted;
}

/**
 * Get upgrade message for feature
 */
function getUpgradeMessage(string $feature): string {
    $messages = [
        'seating' => 'Opgrader til Basis for at få adgang til bordplan-funktionen.',
        'toastmaster' => 'Opgrader til Premium for at få adgang til toastmaster-koordinering.',
        'budget' => 'Opgrader til Premium for at få adgang til budget-styring.',
        'checklist' => 'Opgrader til Basis for at få adgang til tjekliste-funktionen.',
        'custom_domain' => 'Opgrader til Pro for at bruge dit eget domæne.'
    ];

    return $messages[$feature] ?? 'Opgrader din plan for at få adgang til denne funktion.';
}

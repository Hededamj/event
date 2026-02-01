<?php
/**
 * GDPR Data Export Page
 * Allows users to download all their personal data
 */
$pageTitle = 'Download mine data';
require_once __DIR__ . '/../../includes/app-header.php';
require_once __DIR__ . '/../../includes/gdpr.php';

$error = '';
$success = '';

// Get account data
$stmt = $db->prepare("SELECT * FROM accounts WHERE id = ?");
$stmt->execute([$accountId]);
$account = $stmt->fetch();

// Handle export request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyAccountCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Ugyldig anmodning. Prøv igen.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'export_data') {
            $format = $_POST['format'] ?? 'json';

            // Generate export data
            $exportData = [
                'export_info' => [
                    'date' => date('c'),
                    'type' => 'GDPR Data Export',
                    'format' => $format,
                    'platform' => 'Partypart'
                ],
                'account' => [
                    'id' => $account['id'],
                    'name' => $account['name'],
                    'email' => $account['email'],
                    'phone' => $account['phone'],
                    'created_at' => $account['created_at'],
                    'email_verified_at' => $account['email_verified_at'],
                    'last_login_at' => $account['last_login_at']
                ],
                'events' => [],
                'guests' => [],
                'consent_history' => []
            ];

            // Get all events owned by this account
            $stmt = $db->prepare("
                SELECT e.*
                FROM events e
                JOIN event_owners eo ON e.id = eo.event_id
                WHERE eo.account_id = ?
                ORDER BY e.created_at DESC
            ");
            $stmt->execute([$accountId]);
            $events = $stmt->fetchAll();

            foreach ($events as $event) {
                $eventData = [
                    'id' => $event['id'],
                    'name' => $event['name'],
                    'slug' => $event['slug'],
                    'event_date' => $event['event_date'],
                    'location' => $event['location'],
                    'created_at' => $event['created_at'],
                    'guests' => []
                ];

                // Get guests for this event
                $guestStmt = $db->prepare("
                    SELECT id, name, email, phone, rsvp_status, adults_count, children_count,
                           dietary_notes, created_at, rsvp_date
                    FROM guests
                    WHERE event_id = ?
                    ORDER BY name
                ");
                $guestStmt->execute([$event['id']]);
                $eventData['guests'] = $guestStmt->fetchAll(PDO::FETCH_ASSOC);

                $exportData['events'][] = $eventData;
            }

            // Get consent history
            $exportData['consent_history'] = getConsentHistory($db, $accountId);

            // Generate file
            if ($format === 'json') {
                header('Content-Type: application/json; charset=utf-8');
                header('Content-Disposition: attachment; filename="partypart_data_export_' . date('Y-m-d') . '.json"');
                echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                exit;
            } else {
                // HTML format
                header('Content-Type: text/html; charset=utf-8');
                header('Content-Disposition: attachment; filename="partypart_data_export_' . date('Y-m-d') . '.html"');
                echo generateHtmlExport($exportData);
                exit;
            }
        }
    }
}

/**
 * Generate HTML export
 */
function generateHtmlExport(array $data): string {
    $html = '<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <title>Partypart Data Export</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 800px; margin: 0 auto; padding: 40px 20px; line-height: 1.6; }
        h1 { color: #667eea; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
        h2 { color: #374151; margin-top: 40px; }
        h3 { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { text-align: left; padding: 10px; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; }
        .info { background: #f3f4f6; padding: 15px; border-radius: 8px; margin: 20px 0; }
        .info p { margin: 5px 0; }
    </style>
</head>
<body>
    <h1>Partypart - Data Export</h1>
    <div class="info">
        <p><strong>Eksporteret:</strong> ' . htmlspecialchars($data['export_info']['date']) . '</p>
        <p><strong>Type:</strong> ' . htmlspecialchars($data['export_info']['type']) . '</p>
    </div>

    <h2>Kontooplysninger</h2>
    <table>
        <tr><th>Felt</th><th>Værdi</th></tr>
        <tr><td>Navn</td><td>' . htmlspecialchars($data['account']['name'] ?? '') . '</td></tr>
        <tr><td>Email</td><td>' . htmlspecialchars($data['account']['email'] ?? '') . '</td></tr>
        <tr><td>Telefon</td><td>' . htmlspecialchars($data['account']['phone'] ?? '-') . '</td></tr>
        <tr><td>Oprettet</td><td>' . htmlspecialchars($data['account']['created_at'] ?? '') . '</td></tr>
        <tr><td>Seneste login</td><td>' . htmlspecialchars($data['account']['last_login_at'] ?? '-') . '</td></tr>
    </table>';

    if (!empty($data['events'])) {
        $html .= '<h2>Arrangementer (' . count($data['events']) . ')</h2>';
        foreach ($data['events'] as $event) {
            $html .= '<h3>' . htmlspecialchars($event['name']) . '</h3>';
            $html .= '<p><strong>Dato:</strong> ' . htmlspecialchars($event['event_date'] ?? '-') . '</p>';
            $html .= '<p><strong>Sted:</strong> ' . htmlspecialchars($event['location'] ?? '-') . '</p>';

            if (!empty($event['guests'])) {
                $html .= '<h4>Gæster (' . count($event['guests']) . ')</h4>';
                $html .= '<table>
                    <tr><th>Navn</th><th>Email</th><th>Status</th><th>Personer</th></tr>';
                foreach ($event['guests'] as $guest) {
                    $html .= '<tr>
                        <td>' . htmlspecialchars($guest['name'] ?? '') . '</td>
                        <td>' . htmlspecialchars($guest['email'] ?? '-') . '</td>
                        <td>' . htmlspecialchars($guest['rsvp_status'] ?? 'pending') . '</td>
                        <td>' . ((int)($guest['adults_count'] ?? 0) + (int)($guest['children_count'] ?? 0)) . '</td>
                    </tr>';
                }
                $html .= '</table>';
            }
        }
    }

    if (!empty($data['consent_history'])) {
        $html .= '<h2>Samtykkehistorik</h2>
        <table>
            <tr><th>Type</th><th>Givet</th><th>Trukket tilbage</th></tr>';
        foreach ($data['consent_history'] as $consent) {
            $html .= '<tr>
                <td>' . htmlspecialchars($consent['consent_type']) . '</td>
                <td>' . htmlspecialchars($consent['granted_at'] ?? '') . '</td>
                <td>' . htmlspecialchars($consent['withdrawn_at'] ?? '-') . '</td>
            </tr>';
        }
        $html .= '</table>';
    }

    $html .= '</body></html>';
    return $html;
}

// Count data for display
$eventCount = 0;
$guestCount = 0;

$stmt = $db->prepare("
    SELECT COUNT(*) FROM events e
    JOIN event_owners eo ON e.id = eo.event_id
    WHERE eo.account_id = ?
");
$stmt->execute([$accountId]);
$eventCount = $stmt->fetchColumn();

$stmt = $db->prepare("
    SELECT COUNT(*) FROM guests g
    JOIN events e ON g.event_id = e.id
    JOIN event_owners eo ON e.id = eo.event_id
    WHERE eo.account_id = ?
");
$stmt->execute([$accountId]);
$guestCount = $stmt->fetchColumn();

$consentHistory = getConsentHistory($db, $accountId);
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Download mine data</h1>
        <p class="page-subtitle">GDPR - Ret til dataportabilitet</p>
    </div>
</div>

<?php if ($error): ?>
    <div class="flash-message error">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="gdpr-grid">
    <!-- Data Overview -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Dine data</h2>
        </div>
        <p class="text-muted mb-md">
            Ifølge GDPR har du ret til at modtage en kopi af alle de personoplysninger, vi har registreret om dig.
        </p>

        <div class="data-summary">
            <div class="data-item">
                <span class="data-icon">👤</span>
                <div>
                    <strong>Kontooplysninger</strong>
                    <p class="text-muted small">Navn, email, telefon</p>
                </div>
            </div>
            <div class="data-item">
                <span class="data-icon">🎉</span>
                <div>
                    <strong><?= $eventCount ?> arrangement<?= $eventCount !== 1 ? 'er' : '' ?></strong>
                    <p class="text-muted small">Dine oprettede events</p>
                </div>
            </div>
            <div class="data-item">
                <span class="data-icon">👥</span>
                <div>
                    <strong><?= $guestCount ?> gæst<?= $guestCount !== 1 ? 'er' : '' ?></strong>
                    <p class="text-muted small">Gæstelister fra dine events</p>
                </div>
            </div>
            <div class="data-item">
                <span class="data-icon">✅</span>
                <div>
                    <strong><?= count($consentHistory) ?> samtykke<?= count($consentHistory) !== 1 ? 'r' : '' ?></strong>
                    <p class="text-muted small">Registreret samtykkehistorik</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Export Form -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Download</h2>
        </div>
        <form method="POST">
            <?= accountCsrfField() ?>
            <input type="hidden" name="action" value="export_data">

            <div class="form-group">
                <label class="form-label">Vælg format</label>
                <div class="format-options">
                    <label class="format-option">
                        <input type="radio" name="format" value="json" checked>
                        <span class="format-card">
                            <span class="format-icon">📄</span>
                            <span class="format-name">JSON</span>
                            <span class="format-desc">Maskinlæsbart format</span>
                        </span>
                    </label>
                    <label class="format-option">
                        <input type="radio" name="format" value="html">
                        <span class="format-card">
                            <span class="format-icon">🌐</span>
                            <span class="format-name">HTML</span>
                            <span class="format-desc">Læsbart i browser</span>
                        </span>
                    </label>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-full">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 20px; height: 20px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                Download mine data
            </button>
        </form>
    </div>

    <!-- Consent History -->
    <?php if (!empty($consentHistory)): ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Samtykkehistorik</h2>
        </div>
        <div class="consent-list">
            <?php foreach ($consentHistory as $consent): ?>
            <div class="consent-item">
                <div class="consent-type">
                    <?php
                    $typeLabels = [
                        'privacy_policy' => 'Privatlivspolitik',
                        'terms' => 'Vilkår og betingelser',
                        'marketing' => 'Markedsføring',
                        'data_processing' => 'Databehandling',
                        'cookies' => 'Cookies'
                    ];
                    echo htmlspecialchars($typeLabels[$consent['consent_type']] ?? $consent['consent_type']);
                    ?>
                </div>
                <div class="consent-details">
                    <span class="consent-status <?= $consent['withdrawn_at'] ? 'withdrawn' : 'active' ?>">
                        <?= $consent['withdrawn_at'] ? 'Trukket tilbage' : 'Aktivt' ?>
                    </span>
                    <span class="consent-date">
                        <?= htmlspecialchars(date('d. M Y', strtotime($consent['granted_at']))) ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
    .gdpr-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 24px;
    }

    .data-summary {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .data-item {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 16px;
        background: var(--gray-50);
        border-radius: 8px;
    }

    .data-icon {
        font-size: 24px;
    }

    .data-item strong {
        display: block;
        color: var(--gray-900);
    }

    .format-options {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-bottom: 20px;
    }

    .format-option input {
        position: absolute;
        opacity: 0;
    }

    .format-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 20px;
        border: 2px solid var(--gray-200);
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .format-option input:checked + .format-card {
        border-color: var(--primary);
        background: rgba(102, 126, 234, 0.05);
    }

    .format-icon {
        font-size: 32px;
        margin-bottom: 8px;
    }

    .format-name {
        font-weight: 600;
        color: var(--gray-900);
    }

    .format-desc {
        font-size: 12px;
        color: var(--gray-500);
    }

    .consent-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .consent-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        background: var(--gray-50);
        border-radius: 8px;
    }

    .consent-type {
        font-weight: 500;
        color: var(--gray-900);
    }

    .consent-details {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .consent-status {
        padding: 4px 8px;
        font-size: 12px;
        font-weight: 500;
        border-radius: 4px;
    }

    .consent-status.active {
        background: #dcfce7;
        color: #15803d;
    }

    .consent-status.withdrawn {
        background: #fee2e2;
        color: #dc2626;
    }

    .consent-date {
        font-size: 13px;
        color: var(--gray-500);
    }

    .text-muted {
        color: var(--gray-500);
    }

    .small {
        font-size: 13px;
    }

    .mb-md {
        margin-bottom: 20px;
    }

    @media (max-width: 640px) {
        .gdpr-grid {
            grid-template-columns: 1fr;
        }

        .format-options {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php require_once __DIR__ . '/../../includes/app-footer.php'; ?>

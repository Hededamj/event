<?php
/**
 * GDPR Account Deletion Page
 * "Right to be Forgotten" - Request account deletion with 30-day grace period
 */
$pageTitle = 'Slet min konto';
require_once __DIR__ . '/../../includes/app-header.php';
require_once __DIR__ . '/../../includes/gdpr.php';

$error = '';
$success = '';

// Get account data
$stmt = $db->prepare("SELECT * FROM accounts WHERE id = ?");
$stmt->execute([$accountId]);
$account = $stmt->fetch();

// Check for existing deletion request
$stmt = $db->prepare("SELECT * FROM account_deletion_requests WHERE account_id = ? AND status = 'pending'");
$stmt->execute([$accountId]);
$pendingRequest = $stmt->fetch();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyAccountCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Ugyldig anmodning. Prøv igen.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'request_deletion') {
            $password = $_POST['password'] ?? '';
            $reason = trim($_POST['reason'] ?? '');
            $confirm = isset($_POST['confirm']);

            if (empty($password)) {
                $error = 'Indtast din adgangskode for at bekræfte.';
            } elseif (!password_verify($password, $account['password_hash'])) {
                $error = 'Forkert adgangskode.';
            } elseif (!$confirm) {
                $error = 'Du skal bekræfte at du forstår konsekvenserne.';
            } else {
                $token = requestAccountDeletion($db, $accountId, $reason);
                if ($token) {
                    // Record consent withdrawal for all types
                    withdrawConsent($db, 'privacy_policy', $accountId);
                    withdrawConsent($db, 'marketing', $accountId);

                    setFlash('success', 'Din anmodning om sletning er registreret. Du har 30 dage til at fortryde.');
                    redirect('/app/account/delete-account.php');
                } else {
                    $error = 'Der opstod en fejl. Prøv igen senere.';
                }
            }
        } elseif ($action === 'cancel_deletion') {
            if (cancelAccountDeletion($db, $accountId)) {
                $pendingRequest = null;
                setFlash('success', 'Din anmodning om sletning er annulleret.');
                redirect('/app/account/delete-account.php');
            } else {
                $error = 'Der opstod en fejl. Prøv igen.';
            }
        }
    }
}

// Count what will be deleted
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
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Slet min konto</h1>
        <p class="page-subtitle">GDPR - Ret til at blive glemt</p>
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

<?php if ($pendingRequest): ?>
    <!-- Pending Deletion Request -->
    <div class="deletion-pending">
        <div class="pending-icon">⏳</div>
        <h2>Sletning afventer</h2>
        <p class="pending-date">
            Din konto vil blive slettet den <strong><?= htmlspecialchars(date('d. F Y', strtotime($pendingRequest['scheduled_deletion_date']))) ?></strong>
        </p>
        <p class="pending-info">
            Du har indtil da til at fortryde og beholde din konto. Alle dine data vil blive permanent slettet efter denne dato.
        </p>

        <div class="pending-countdown">
            <?php
            $now = new DateTime();
            $deleteDate = new DateTime($pendingRequest['scheduled_deletion_date']);
            $daysLeft = $now->diff($deleteDate)->days;
            ?>
            <span class="countdown-number"><?= $daysLeft ?></span>
            <span class="countdown-label">dage tilbage</span>
        </div>

        <form method="POST" class="cancel-form">
            <?= accountCsrfField() ?>
            <input type="hidden" name="action" value="cancel_deletion">
            <button type="submit" class="btn btn-primary btn-full">
                Fortryd - Behold min konto
            </button>
        </form>
    </div>

<?php else: ?>
    <!-- Request Deletion Form -->
    <div class="deletion-grid">
        <div class="card warning-card">
            <div class="warning-icon">⚠️</div>
            <h2>Advarsel</h2>
            <p>
                Når du sletter din konto, vil <strong>alle</strong> dine data blive permanent fjernet.
                Dette kan ikke fortrydes efter 30-dages perioden.
            </p>

            <div class="deletion-summary">
                <h3>Følgende vil blive slettet:</h3>
                <ul>
                    <li>Din konto og alle personlige oplysninger</li>
                    <li><strong><?= $eventCount ?></strong> arrangement<?= $eventCount !== 1 ? 'er' : '' ?> du har oprettet</li>
                    <li><strong><?= $guestCount ?></strong> gæsteregistrering<?= $guestCount !== 1 ? 'er' : '' ?></li>
                    <li>Alle uploadede billeder og filer</li>
                    <li>Abonnement og betalingshistorik</li>
                </ul>
            </div>
        </div>

        <div class="card">
            <h2>Anmod om sletning</h2>
            <p class="text-muted mb-md">
                Efter du anmoder om sletning, har du 30 dage til at fortryde. Efter denne periode vil alle data blive permanent fjernet.
            </p>

            <form method="POST">
                <?= accountCsrfField() ?>
                <input type="hidden" name="action" value="request_deletion">

                <div class="form-group">
                    <label class="form-label">Hvorfor forlader du os? (valgfrit)</label>
                    <textarea
                        name="reason"
                        class="form-input"
                        rows="3"
                        placeholder="Din feedback hjælper os med at forbedre vores service..."
                    ></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Bekræft med din adgangskode</label>
                    <input
                        type="password"
                        name="password"
                        class="form-input"
                        placeholder="Indtast din adgangskode"
                        required
                    >
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="confirm" value="1" required>
                        <span>
                            Jeg forstår at alle mine data vil blive permanent slettet efter 30 dage,
                            og at denne handling ikke kan fortrydes.
                        </span>
                    </label>
                </div>

                <button type="submit" class="btn btn-danger btn-full">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 20px; height: 20px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                    </svg>
                    Anmod om sletning af min konto
                </button>
            </form>

            <p class="alternative-link">
                Vil du i stedet <a href="/app/account/export-data.php">downloade dine data</a>?
            </p>
        </div>
    </div>
<?php endif; ?>

<style>
    .deletion-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
        align-items: start;
    }

    .warning-card {
        background: #fef2f2;
        border: 2px solid #fecaca;
    }

    .warning-icon {
        font-size: 48px;
        text-align: center;
        margin-bottom: 16px;
    }

    .warning-card h2 {
        color: #dc2626;
        text-align: center;
        margin-bottom: 16px;
    }

    .deletion-summary {
        background: white;
        padding: 20px;
        border-radius: 8px;
        margin-top: 20px;
    }

    .deletion-summary h3 {
        font-size: 14px;
        color: var(--gray-700);
        margin-bottom: 12px;
    }

    .deletion-summary ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .deletion-summary li {
        padding: 8px 0;
        border-bottom: 1px solid var(--gray-100);
        color: var(--gray-600);
        font-size: 14px;
    }

    .deletion-summary li:last-child {
        border-bottom: none;
    }

    .deletion-summary li::before {
        content: "×";
        color: #dc2626;
        font-weight: bold;
        margin-right: 8px;
    }

    /* Pending deletion styles */
    .deletion-pending {
        max-width: 500px;
        margin: 0 auto;
        text-align: center;
        padding: 40px;
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    }

    .pending-icon {
        font-size: 64px;
        margin-bottom: 20px;
    }

    .deletion-pending h2 {
        color: #d97706;
        margin-bottom: 16px;
    }

    .pending-date {
        font-size: 16px;
        color: var(--gray-700);
        margin-bottom: 12px;
    }

    .pending-info {
        font-size: 14px;
        color: var(--gray-500);
        margin-bottom: 24px;
    }

    .pending-countdown {
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        background: #fef3c7;
        padding: 20px 40px;
        border-radius: 12px;
        margin-bottom: 24px;
    }

    .countdown-number {
        font-size: 48px;
        font-weight: 700;
        color: #d97706;
        line-height: 1;
    }

    .countdown-label {
        font-size: 14px;
        color: #92400e;
    }

    .cancel-form {
        margin-top: 20px;
    }

    /* Form styles */
    .form-group {
        margin-bottom: 20px;
    }

    .form-label {
        display: block;
        font-size: 14px;
        font-weight: 500;
        color: var(--gray-700);
        margin-bottom: 6px;
    }

    .form-input {
        width: 100%;
        padding: 10px 14px;
        font-size: 14px;
        border: 2px solid var(--gray-200);
        border-radius: 8px;
        font-family: inherit;
    }

    .form-input:focus {
        outline: none;
        border-color: var(--primary);
    }

    .checkbox-label {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        cursor: pointer;
        font-size: 13px;
        color: var(--gray-600);
    }

    .checkbox-label input {
        margin-top: 2px;
    }

    .btn-danger {
        background: #dc2626;
        color: white;
    }

    .btn-danger:hover {
        background: #b91c1c;
    }

    .text-muted {
        color: var(--gray-500);
    }

    .mb-md {
        margin-bottom: 20px;
    }

    .alternative-link {
        text-align: center;
        margin-top: 20px;
        font-size: 14px;
        color: var(--gray-500);
    }

    .alternative-link a {
        color: var(--primary);
    }

    @media (max-width: 768px) {
        .deletion-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php require_once __DIR__ . '/../../includes/app-footer.php'; ?>

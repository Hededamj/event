<?php
/**
 * Platform Admin - Partner Commission Management
 */

require_once __DIR__ . '/../includes/admin-platform-header.php';
require_once __DIR__ . '/../includes/partner-commission.php';

$db = getDB();

// Ensure commission tables exist
ensureCommissionTables($db);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'generate_payout') {
        $partnerId = (int)($_POST['partner_id'] ?? 0);
        $periodStart = $_POST['period_start'] ?? '';
        $periodEnd = $_POST['period_end'] ?? '';

        if ($partnerId && $periodStart && $periodEnd) {
            $payoutId = createPayout($db, $partnerId, $periodStart, $periodEnd, $adminId);
            if ($payoutId) {
                setFlash('success', 'Faktura oprettet med ID #' . $payoutId);
            } else {
                setFlash('error', 'Ingen bookinger at fakturere i denne periode.');
            }
        }
        redirect($_SERVER['REQUEST_URI']);
    }

    if ($action === 'send_payout') {
        $payoutId = (int)($_POST['payout_id'] ?? 0);
        if ($payoutId) {
            $stmt = $db->prepare("UPDATE partner_payouts SET status = 'sent', sent_at = NOW() WHERE id = ?");
            $stmt->execute([$payoutId]);
            setFlash('success', 'Faktura markeret som sendt.');
        }
        redirect($_SERVER['REQUEST_URI']);
    }

    if ($action === 'mark_paid') {
        $payoutId = (int)($_POST['payout_id'] ?? 0);
        $paymentMethod = $_POST['payment_method'] ?? 'bank_transfer';
        $paymentReference = $_POST['payment_reference'] ?? null;

        if ($payoutId) {
            if (markPayoutPaid($db, $payoutId, $paymentMethod, $paymentReference)) {
                setFlash('success', 'Faktura markeret som betalt.');
            } else {
                setFlash('error', 'Kunne ikke opdatere faktura.');
            }
        }
        redirect($_SERVER['REQUEST_URI']);
    }

    if ($action === 'update_commission_rate') {
        $partnerId = (int)($_POST['partner_id'] ?? 0);
        $newRate = (float)($_POST['commission_rate'] ?? 10);

        if ($partnerId && $newRate >= 0 && $newRate <= 50) {
            $stmt = $db->prepare("
                INSERT INTO partner_commission_settings (partner_id, commission_rate)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE commission_rate = ?
            ");
            $stmt->execute([$partnerId, $newRate, $newRate]);
            setFlash('success', 'Kommissionssats opdateret til ' . $newRate . '%');
        }
        redirect($_SERVER['REQUEST_URI']);
    }
}

// Filters
$statusFilter = $_GET['status'] ?? '';
$partnerFilter = (int)($_GET['partner'] ?? 0);

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get commission overview stats
$stmt = $db->query("
    SELECT
        COUNT(DISTINCT pb.partner_id) as active_partners,
        COUNT(pb.id) as total_bookings,
        SUM(CASE WHEN pb.status = 'completed' THEN pb.total_amount ELSE 0 END) as total_revenue,
        SUM(CASE WHEN pb.status = 'completed' AND pb.commission_status = 'pending' THEN pb.commission_amount ELSE 0 END) as pending_commission,
        SUM(CASE WHEN pb.commission_status = 'paid' THEN pb.commission_amount ELSE 0 END) as paid_commission
    FROM partner_bookings pb
");
$overviewStats = $stmt->fetch();

// Get partners with pending commission
$stmt = $db->query("
    SELECT
        p.id, p.company_name, p.email,
        COALESCE(pcs.commission_rate, 10) as commission_rate,
        COUNT(pb.id) as pending_bookings,
        SUM(pb.commission_amount) as pending_amount
    FROM partners p
    JOIN partner_bookings pb ON p.id = pb.partner_id
    LEFT JOIN partner_commission_settings pcs ON p.id = pcs.partner_id
    WHERE pb.status = 'completed' AND pb.commission_status = 'pending'
    GROUP BY p.id, p.company_name, p.email, pcs.commission_rate
    ORDER BY pending_amount DESC
");
$partnersWithPending = $stmt->fetchAll();

// Get payouts with filters
$sql = "
    SELECT pp.*, p.company_name, p.email as partner_email
    FROM partner_payouts pp
    JOIN partners p ON pp.partner_id = p.id
    WHERE 1=1
";
$params = [];

if ($statusFilter) {
    $sql .= " AND pp.status = ?";
    $params[] = $statusFilter;
}
if ($partnerFilter) {
    $sql .= " AND pp.partner_id = ?";
    $params[] = $partnerFilter;
}

$sql .= " ORDER BY pp.created_at DESC LIMIT $perPage OFFSET $offset";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$payouts = $stmt->fetchAll();

// Count total for pagination
$countSql = "SELECT COUNT(*) FROM partner_payouts pp WHERE 1=1";
$countParams = [];
if ($statusFilter) {
    $countSql .= " AND pp.status = ?";
    $countParams[] = $statusFilter;
}
if ($partnerFilter) {
    $countSql .= " AND pp.partner_id = ?";
    $countParams[] = $partnerFilter;
}
$stmt = $db->prepare($countSql);
$stmt->execute($countParams);
$totalPayouts = $stmt->fetchColumn();
$totalPages = ceil($totalPayouts / $perPage);

// Get all partners for filter dropdown
$stmt = $db->query("SELECT id, company_name FROM partners WHERE status = 'approved' ORDER BY company_name");
$allPartners = $stmt->fetchAll();

// Status labels
$statusLabels = [
    'draft' => ['text' => 'Kladde', 'class' => 'badge badge--neutral'],
    'sent' => ['text' => 'Sendt', 'class' => 'badge badge--warning'],
    'paid' => ['text' => 'Betalt', 'class' => 'badge badge--success'],
    'overdue' => ['text' => 'Forfalden', 'class' => 'badge badge--error'],
    'cancelled' => ['text' => 'Annulleret', 'class' => 'badge badge--neutral']
];
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-header__title">Kommission</h1>
        <p class="page-header__subtitle">Administrer partner bookinger og fakturaer</p>
    </div>
</div>

<?php if ($flash): ?>
    <div class="alert alert--<?= $flash['type'] ?>">
        <?= escape($flash['message']) ?>
    </div>
<?php endif; ?>

<!-- Overview Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card__label">Afventer fakturering</div>
        <div class="stat-card__value" style="color: var(--color-primary);">
            <?= formatDKK($overviewStats['pending_commission'] ?? 0) ?>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__label">Betalt kommission</div>
        <div class="stat-card__value"><?= formatDKK($overviewStats['paid_commission'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-card__label">Total omsætning</div>
        <div class="stat-card__value"><?= formatDKK($overviewStats['total_revenue'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-card__label">Aktive partnere</div>
        <div class="stat-card__value"><?= $overviewStats['active_partners'] ?? 0 ?></div>
    </div>
</div>

<!-- Partners with Pending Commission -->
<?php if (!empty($partnersWithPending)): ?>
<div class="card mb-lg">
    <h2 class="card__title">Partnere med kommission til fakturering</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Partner</th>
                <th>Sats</th>
                <th>Afventer</th>
                <th>Beløb</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($partnersWithPending as $p): ?>
                <tr>
                    <td>
                        <strong><?= escape($p['company_name']) ?></strong><br>
                        <span class="text-muted text-sm"><?= escape($p['email']) ?></span>
                    </td>
                    <td><?= $p['commission_rate'] ?>%</td>
                    <td><?= $p['pending_bookings'] ?> bookinger</td>
                    <td style="font-weight: 600;"><?= formatDKK($p['pending_amount']) ?></td>
                    <td>
                        <button onclick="openGenerateModal(<?= $p['id'] ?>, '<?= escape($p['company_name']) ?>')"
                                class="btn btn--sm btn--primary">
                            Opret faktura
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="card mb-lg">
    <form method="GET" class="filter-form">
        <div class="filter-form__row">
            <div class="filter-form__group">
                <label>Status</label>
                <select name="status" class="form-input">
                    <option value="">Alle</option>
                    <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Kladde</option>
                    <option value="sent" <?= $statusFilter === 'sent' ? 'selected' : '' ?>>Sendt</option>
                    <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Betalt</option>
                    <option value="overdue" <?= $statusFilter === 'overdue' ? 'selected' : '' ?>>Forfalden</option>
                </select>
            </div>
            <div class="filter-form__group">
                <label>Partner</label>
                <select name="partner" class="form-input">
                    <option value="">Alle partnere</option>
                    <?php foreach ($allPartners as $ap): ?>
                        <option value="<?= $ap['id'] ?>" <?= $partnerFilter === $ap['id'] ? 'selected' : '' ?>>
                            <?= escape($ap['company_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-form__actions">
                <button type="submit" class="btn btn--secondary">Filtrer</button>
                <a href="?" class="btn btn--ghost">Nulstil</a>
            </div>
        </div>
    </form>
</div>

<!-- Payouts Table -->
<div class="card">
    <h2 class="card__title">Fakturaer</h2>

    <?php if (empty($payouts)): ?>
        <div class="empty-state">
            <p>Ingen fakturaer endnu</p>
        </div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Partner</th>
                    <th>Periode</th>
                    <th>Bookinger</th>
                    <th>Beløb</th>
                    <th>Status</th>
                    <th>Forfald</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payouts as $payout): ?>
                    <tr>
                        <td><code><?= escape($payout['payout_reference']) ?></code></td>
                        <td><?= escape($payout['company_name']) ?></td>
                        <td>
                            <?= date('d/m', strtotime($payout['period_start'])) ?> -
                            <?= date('d/m/y', strtotime($payout['period_end'])) ?>
                        </td>
                        <td><?= $payout['total_bookings'] ?></td>
                        <td style="font-weight: 600;"><?= formatDKK($payout['final_amount']) ?></td>
                        <td>
                            <?php $status = $statusLabels[$payout['status']] ?? ['text' => $payout['status'], 'class' => 'badge']; ?>
                            <span class="<?= $status['class'] ?>"><?= $status['text'] ?></span>
                        </td>
                        <td>
                            <?php if ($payout['status'] === 'paid'): ?>
                                <span class="text-success"><?= date('d/m/y', strtotime($payout['paid_at'])) ?></span>
                            <?php else: ?>
                                <?= date('d/m/y', strtotime($payout['due_date'])) ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($payout['status'] === 'draft'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="send_payout">
                                    <input type="hidden" name="payout_id" value="<?= $payout['id'] ?>">
                                    <button type="submit" class="btn btn--sm btn--secondary">Send</button>
                                </form>
                            <?php elseif ($payout['status'] === 'sent'): ?>
                                <button onclick="openPaymentModal(<?= $payout['id'] ?>, '<?= escape($payout['payout_reference']) ?>')"
                                        class="btn btn--sm btn--success">
                                    Marker betalt
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i ?>&status=<?= $statusFilter ?>&partner=<?= $partnerFilter ?>"
                       class="pagination__link <?= $i === $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Generate Payout Modal -->
<div class="modal-overlay" id="generate-modal">
    <div class="modal">
        <div class="modal__header">
            <h3 class="modal__title">Opret faktura</h3>
            <button class="modal__close" onclick="closeModal('generate-modal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal__body">
                <input type="hidden" name="action" value="generate_payout">
                <input type="hidden" name="partner_id" id="generate-partner-id">

                <p class="mb-md">Opret faktura for <strong id="generate-partner-name"></strong></p>

                <div class="form-group">
                    <label class="form-label">Periode start</label>
                    <input type="date" name="period_start" class="form-input" required
                           value="<?= date('Y-m-01', strtotime('-1 month')) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Periode slut</label>
                    <input type="date" name="period_end" class="form-input" required
                           value="<?= date('Y-m-t', strtotime('-1 month')) ?>">
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--secondary" onclick="closeModal('generate-modal')">Annuller</button>
                <button type="submit" class="btn btn--primary">Opret faktura</button>
            </div>
        </form>
    </div>
</div>

<!-- Mark Paid Modal -->
<div class="modal-overlay" id="payment-modal">
    <div class="modal">
        <div class="modal__header">
            <h3 class="modal__title">Marker som betalt</h3>
            <button class="modal__close" onclick="closeModal('payment-modal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal__body">
                <input type="hidden" name="action" value="mark_paid">
                <input type="hidden" name="payout_id" id="payment-payout-id">

                <p class="mb-md">Marker faktura <strong id="payment-reference"></strong> som betalt</p>

                <div class="form-group">
                    <label class="form-label">Betalingsmetode</label>
                    <select name="payment_method" class="form-input">
                        <option value="bank_transfer">Bankoverførsel</option>
                        <option value="card">Kort</option>
                        <option value="mobilepay">MobilePay</option>
                        <option value="other">Andet</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Reference (valgfrit)</label>
                    <input type="text" name="payment_reference" class="form-input"
                           placeholder="Transaktions-ID eller note">
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--secondary" onclick="closeModal('payment-modal')">Annuller</button>
                <button type="submit" class="btn btn--success">Marker betalt</button>
            </div>
        </form>
    </div>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}
.stat-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
}
.stat-card__label {
    font-size: var(--text-sm);
    color: var(--color-text-muted);
    margin-bottom: 0.25rem;
}
.stat-card__value {
    font-size: 1.75rem;
    font-weight: 700;
}

.filter-form__row {
    display: flex;
    gap: 1rem;
    align-items: flex-end;
    flex-wrap: wrap;
}
.filter-form__group {
    flex: 1;
    min-width: 150px;
}
.filter-form__group label {
    display: block;
    font-size: var(--text-sm);
    font-weight: 500;
    margin-bottom: 0.375rem;
}
.filter-form__actions {
    display: flex;
    gap: 0.5rem;
}

.pagination {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
    margin-top: 1.5rem;
}
.pagination__link {
    padding: 0.5rem 0.75rem;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    text-decoration: none;
    color: var(--color-text);
    font-size: var(--text-sm);
}
.pagination__link:hover { background: var(--color-bg-subtle); }
.pagination__link.active {
    background: var(--color-primary);
    color: white;
    border-color: var(--color-primary);
}

.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}
.modal-overlay.active { display: flex; }
.modal {
    background: white;
    border-radius: var(--radius-lg);
    max-width: 450px;
    width: 90%;
}
.modal__header {
    padding: 1.25rem;
    border-bottom: 1px solid var(--color-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modal__title { font-size: 1.1rem; font-weight: 600; }
.modal__close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--color-text-muted);
}
.modal__body { padding: 1.25rem; }
.modal__footer {
    padding: 1.25rem;
    border-top: 1px solid var(--color-border);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

.text-success { color: var(--color-success); }
.text-muted { color: var(--color-text-muted); }
.text-sm { font-size: var(--text-sm); }
.mb-md { margin-bottom: 1rem; }
.mb-lg { margin-bottom: 1.5rem; }
</style>

<script>
function openModal(id) {
    document.getElementById(id).classList.add('active');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

function openGenerateModal(partnerId, partnerName) {
    document.getElementById('generate-partner-id').value = partnerId;
    document.getElementById('generate-partner-name').textContent = partnerName;
    openModal('generate-modal');
}

function openPaymentModal(payoutId, reference) {
    document.getElementById('payment-payout-id').value = payoutId;
    document.getElementById('payment-reference').textContent = reference;
    openModal('payment-modal');
}

// Close modal on outside click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) closeModal(overlay.id);
    });
});
</script>

<?php require_once __DIR__ . '/../includes/admin-platform-footer.php'; ?>

<?php
/**
 * Partner Dashboard - Bookings Management
 */

require_once __DIR__ . '/../../includes/partner-auth.php';
require_once __DIR__ . '/../../includes/partner-commission.php';
requirePartner();

$db = getDB();
$partner = getCurrentPartner();
$partnerId = getCurrentPartnerId();

// Ensure commission tables exist
ensureCommissionTables($db);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    $action = $_POST['action'] ?? '';

    if ($action === 'add_booking') {
        $bookingData = [
            'partner_id' => $partnerId,
            'inquiry_id' => !empty($_POST['inquiry_id']) ? (int)$_POST['inquiry_id'] : null,
            'service_description' => trim($_POST['service_description'] ?? ''),
            'event_date' => $_POST['event_date'] ?? '',
            'guest_count' => !empty($_POST['guest_count']) ? (int)$_POST['guest_count'] : null,
            'total_amount' => (float)($_POST['total_amount'] ?? 0),
            'customer_name' => trim($_POST['customer_name'] ?? ''),
            'customer_email' => trim($_POST['customer_email'] ?? ''),
            'customer_phone' => trim($_POST['customer_phone'] ?? ''),
            'notes' => trim($_POST['notes'] ?? ''),
            'status' => 'confirmed'
        ];

        if ($bookingData['service_description'] && $bookingData['event_date'] &&
            $bookingData['total_amount'] > 0 && $bookingData['customer_name'] && $bookingData['customer_email']) {

            $bookingId = createBooking($db, $bookingData);
            if ($bookingId) {
                setFlash('success', 'Booking oprettet!');
            } else {
                setFlash('error', 'Kunne ikke oprette booking.');
            }
        } else {
            setFlash('error', 'Udfyld venligst alle påkrævede felter.');
        }
        redirect($_SERVER['REQUEST_URI']);
    }

    if ($action === 'update_status') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';
        $reason = $_POST['reason'] ?? null;

        // Verify booking belongs to partner
        $stmt = $db->prepare("SELECT id FROM partner_bookings WHERE id = ? AND partner_id = ?");
        $stmt->execute([$bookingId, $partnerId]);
        if ($stmt->fetch()) {
            if (updateBookingStatus($db, $bookingId, $newStatus, $reason)) {
                setFlash('success', 'Status opdateret.');
            } else {
                setFlash('error', 'Kunne ikke opdatere status.');
            }
        }
        redirect($_SERVER['REQUEST_URI']);
    }
}

// Filters
$statusFilter = $_GET['status'] ?? '';
$filters = [];
if ($statusFilter) {
    $filters['status'] = $statusFilter;
}

// Get bookings
$bookings = getPartnerBookings($db, $partnerId, $filters);

// Get summary stats
$commissionStats = getPartnerCommissionSummary($db, $partnerId);

// Get inquiries for dropdown (unfulfilled ones)
$stmt = $db->prepare("
    SELECT pi.id, pi.name, pi.email, pi.event_date, pi.guest_count
    FROM partner_inquiries pi
    WHERE pi.partner_id = ?
      AND pi.status IN ('new', 'read', 'replied')
      AND NOT EXISTS (
          SELECT 1 FROM partner_bookings pb
          WHERE pb.inquiry_id = pi.id
      )
    ORDER BY pi.created_at DESC
");
$stmt->execute([$partnerId]);
$openInquiries = $stmt->fetchAll();

// Get flash
$flash = getFlash();

// Platform name
try {
    $stmt = $db->query("SELECT setting_value FROM platform_settings WHERE setting_key = 'platform_name'");
    $platformName = $stmt->fetchColumn() ?: 'EventPlatform';
} catch (Exception $e) {
    $platformName = 'EventPlatform';
}

// Status labels
$statusLabels = [
    'pending' => ['text' => 'Afventer', 'class' => 'badge-warning'],
    'confirmed' => ['text' => 'Bekræftet', 'class' => 'badge-info'],
    'completed' => ['text' => 'Gennemført', 'class' => 'badge-success'],
    'cancelled' => ['text' => 'Annulleret', 'class' => 'badge-error'],
    'refunded' => ['text' => 'Refunderet', 'class' => 'badge-error']
];
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookinger - Partner Dashboard</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --color-bg: #f8fafc;
            --color-bg-subtle: #f1f5f9;
            --color-surface: #ffffff;
            --color-primary: #7c3aed;
            --color-primary-deep: #6d28d9;
            --color-primary-soft: #ede9fe;
            --color-text: #1e293b;
            --color-text-soft: #475569;
            --color-text-muted: #94a3b8;
            --color-border: #e2e8f0;
            --color-success: #22c55e;
            --color-warning: #f59e0b;
            --color-error: #ef4444;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
            --radius-md: 8px;
            --radius-lg: 12px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: var(--color-bg); color: var(--color-text); line-height: 1.6; }

        .dashboard-layout { display: flex; min-height: 100vh; }

        .sidebar {
            width: 250px; background: var(--color-surface); border-right: 1px solid var(--color-border);
            position: fixed; top: 0; left: 0; bottom: 0; display: flex; flex-direction: column;
        }
        .sidebar-brand { padding: 1.25rem; border-bottom: 1px solid var(--color-border); }
        .sidebar-brand-name { font-size: 1rem; font-weight: 600; color: var(--color-primary); }
        .sidebar-brand-label { font-size: 0.75rem; color: var(--color-text-muted); }
        .sidebar-nav { flex: 1; padding: 1rem; }
        .nav-menu { list-style: none; }
        .nav-link {
            display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem;
            color: var(--color-text-soft); text-decoration: none; border-radius: var(--radius-md);
            font-size: 0.9rem; margin-bottom: 0.25rem;
        }
        .nav-link:hover { background: var(--color-bg-subtle); }
        .nav-link.active { background: var(--color-primary-soft); color: var(--color-primary-deep); font-weight: 500; }
        .sidebar-footer { padding: 1rem; border-top: 1px solid var(--color-border); }

        .main { flex: 1; margin-left: 250px; padding: 2rem; }

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
        .page-title { font-size: 1.5rem; font-weight: 600; }

        .card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem; margin-bottom: 1.5rem; }
        .card-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem; }

        .btn {
            display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem;
            border: none; border-radius: var(--radius-md); font-size: 0.875rem; font-weight: 500;
            cursor: pointer; text-decoration: none;
        }
        .btn-primary { background: var(--color-primary); color: white; }
        .btn-secondary { background: var(--color-bg-subtle); color: var(--color-text); border: 1px solid var(--color-border); }
        .btn-sm { padding: 0.375rem 0.75rem; font-size: 0.8rem; }
        .btn-block { width: 100%; justify-content: center; }

        .badge { display: inline-block; padding: 0.25rem 0.5rem; border-radius: 10px; font-size: 0.75rem; font-weight: 500; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-error { background: #fee2e2; color: #991b1b; }
        .badge-info { background: var(--color-primary-soft); color: var(--color-primary); }

        .alert { padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }

        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.375rem; }
        .form-input {
            width: 100%; padding: 0.625rem 0.875rem; border: 1px solid var(--color-border);
            border-radius: var(--radius-md); font-size: 0.875rem;
        }
        .form-input:focus { outline: none; border-color: var(--color-primary); box-shadow: 0 0 0 3px var(--color-primary-soft); }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid var(--color-border); }
        th { font-size: 0.75rem; font-weight: 600; color: var(--color-text-muted); text-transform: uppercase; background: var(--color-bg-subtle); }
        td { font-size: 0.875rem; }

        .empty-state { text-align: center; padding: 3rem; color: var(--color-text-muted); }

        .filter-bar { display: flex; gap: 0.5rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .filter-btn { padding: 0.5rem 1rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: white; cursor: pointer; font-size: 0.875rem; text-decoration: none; color: var(--color-text); }
        .filter-btn:hover { background: var(--color-bg-subtle); }
        .filter-btn.active { background: var(--color-primary); color: white; border-color: var(--color-primary); }

        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: white; border-radius: var(--radius-lg); max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto; }
        .modal-header { padding: 1.25rem; border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center; }
        .modal-title { font-size: 1.1rem; font-weight: 600; }
        .modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--color-text-muted); }
        .modal-body { padding: 1.25rem; }
        .modal-footer { padding: 1.25rem; border-top: 1px solid var(--color-border); display: flex; justify-content: flex-end; gap: 0.75rem; }

        .booking-card { border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 1rem; margin-bottom: 0.75rem; }
        .booking-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem; }
        .booking-ref { font-family: monospace; font-size: 0.8rem; color: var(--color-text-muted); }
        .booking-customer { font-weight: 600; }
        .booking-meta { font-size: 0.875rem; color: var(--color-text-soft); margin-bottom: 0.5rem; }
        .booking-amount { font-size: 1.25rem; font-weight: 700; color: var(--color-primary-deep); }
        .booking-commission { font-size: 0.8rem; color: var(--color-text-muted); }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main { margin-left: 0; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="sidebar-brand-name"><?= escape($partner['company_name']) ?></div>
                <div class="sidebar-brand-label">Partner Dashboard</div>
            </div>
            <nav class="sidebar-nav">
                <ul class="nav-menu">
                    <li><a href="<?= BASE_PATH ?>/partners/dashboard/" class="nav-link">&#128200; Dashboard</a></li>
                    <li><a href="<?= BASE_PATH ?>/partners/dashboard/profile.php" class="nav-link">&#128736; Profil</a></li>
                    <li><a href="<?= BASE_PATH ?>/partners/dashboard/inquiries.php" class="nav-link">&#128172; Forespørgsler</a></li>
                    <li><a href="<?= BASE_PATH ?>/partners/dashboard/bookings.php" class="nav-link active">&#128197; Bookinger</a></li>
                    <li><a href="<?= BASE_PATH ?>/partners/dashboard/commission.php" class="nav-link">&#128176; Økonomi</a></li>
                    <li><a href="<?= BASE_PATH ?>/partners/dashboard/verification.php" class="nav-link">&#9989; Verifikation</a></li>
                    <li><a href="<?= BASE_PATH ?>/partners/dashboard/gallery.php" class="nav-link">&#128247; Galleri</a></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <a href="<?= BASE_PATH ?>/partners/profile.php?id=<?= $partnerId ?>" class="nav-link" target="_blank">&#128065; Se offentlig profil</a>
                <a href="<?= BASE_PATH ?>/partners/dashboard/logout.php" class="nav-link">&#128682; Log ud</a>
            </div>
        </aside>

        <!-- Main -->
        <main class="main">
            <div class="page-header">
                <h1 class="page-title">Bookinger</h1>
                <button class="btn btn-primary" onclick="openModal('add-booking-modal')">+ Registrer booking</button>
            </div>

            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                    <?= escape($flash['message']) ?>
                </div>
            <?php endif; ?>

            <!-- Quick Stats -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                <div class="card" style="padding: 1rem; text-align: center;">
                    <div style="font-size: 1.75rem; font-weight: 700;"><?= $commissionStats['total_bookings'] ?></div>
                    <div style="font-size: 0.8rem; color: var(--color-text-muted);">Bookinger i alt</div>
                </div>
                <div class="card" style="padding: 1rem; text-align: center;">
                    <div style="font-size: 1.75rem; font-weight: 700;"><?= $commissionStats['completed_bookings'] ?></div>
                    <div style="font-size: 0.8rem; color: var(--color-text-muted);">Gennemført</div>
                </div>
                <div class="card" style="padding: 1rem; text-align: center;">
                    <div style="font-size: 1.75rem; font-weight: 700;"><?= formatDKK($commissionStats['total_revenue']) ?></div>
                    <div style="font-size: 0.8rem; color: var(--color-text-muted);">Total omsætning</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filter-bar">
                <a href="?" class="filter-btn <?= !$statusFilter ? 'active' : '' ?>">Alle</a>
                <a href="?status=pending" class="filter-btn <?= $statusFilter === 'pending' ? 'active' : '' ?>">Afventer</a>
                <a href="?status=confirmed" class="filter-btn <?= $statusFilter === 'confirmed' ? 'active' : '' ?>">Bekræftet</a>
                <a href="?status=completed" class="filter-btn <?= $statusFilter === 'completed' ? 'active' : '' ?>">Gennemført</a>
                <a href="?status=cancelled" class="filter-btn <?= $statusFilter === 'cancelled' ? 'active' : '' ?>">Annulleret</a>
            </div>

            <!-- Bookings List -->
            <?php if (empty($bookings)): ?>
                <div class="card">
                    <div class="empty-state">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">&#128197;</div>
                        <h3>Ingen bookinger endnu</h3>
                        <p style="margin-bottom: 1rem;">Registrer bookinger der er lavet via platformen for at tracke din omsætning og kommission.</p>
                        <button class="btn btn-primary" onclick="openModal('add-booking-modal')">+ Registrer første booking</button>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($bookings as $booking): ?>
                    <div class="booking-card">
                        <div class="booking-header">
                            <div>
                                <span class="booking-ref"><?= escape($booking['booking_reference']) ?></span>
                                <div class="booking-customer"><?= escape($booking['customer_name']) ?></div>
                            </div>
                            <span class="badge <?= $statusLabels[$booking['status']]['class'] ?? 'badge-info' ?>">
                                <?= $statusLabels[$booking['status']]['text'] ?? $booking['status'] ?>
                            </span>
                        </div>
                        <div class="booking-meta">
                            <?= escape($booking['service_description']) ?><br>
                            &#128197; <?= date('d. M Y', strtotime($booking['event_date'])) ?>
                            <?php if ($booking['guest_count']): ?>
                                &nbsp;|&nbsp; <?= $booking['guest_count'] ?> gæster
                            <?php endif; ?>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div class="booking-amount"><?= formatDKK($booking['total_amount']) ?></div>
                                <div class="booking-commission">
                                    Kommission: <?= formatDKK($booking['commission_amount']) ?> (<?= $booking['commission_rate'] ?>%)
                                </div>
                            </div>
                            <?php if ($booking['status'] === 'confirmed'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                    <input type="hidden" name="status" value="completed">
                                    <button type="submit" class="btn btn-secondary btn-sm">Marker som gennemført</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>
    </div>

    <!-- Add Booking Modal -->
    <div class="modal-overlay" id="add-booking-modal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Registrer booking</h3>
                <button class="modal-close" onclick="closeModal('add-booking-modal')">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="action" value="add_booking">

                    <?php if (!empty($openInquiries)): ?>
                    <div class="form-group">
                        <label class="form-label">Fra forespørgsel (valgfrit)</label>
                        <select name="inquiry_id" class="form-input" onchange="fillFromInquiry(this)">
                            <option value="">-- Vælg forespørgsel --</option>
                            <?php foreach ($openInquiries as $inq): ?>
                                <option value="<?= $inq['id'] ?>"
                                        data-name="<?= escape($inq['name']) ?>"
                                        data-email="<?= escape($inq['email']) ?>"
                                        data-date="<?= $inq['event_date'] ?>"
                                        data-guests="<?= $inq['guest_count'] ?>">
                                    <?= escape($inq['name']) ?> - <?= date('d/m/Y', strtotime($inq['event_date'] ?? 'now')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Kundenavn *</label>
                            <input type="text" name="customer_name" id="customer_name" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email *</label>
                            <input type="email" name="customer_email" id="customer_email" class="form-input" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Telefon</label>
                            <input type="tel" name="customer_phone" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Eventdato *</label>
                            <input type="date" name="event_date" id="event_date" class="form-input" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Antal gæster</label>
                            <input type="number" name="guest_count" id="guest_count" class="form-input" min="1">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Beløb (DKK) *</label>
                            <input type="number" name="total_amount" class="form-input" min="0" step="0.01" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Beskrivelse af ydelse *</label>
                        <input type="text" name="service_description" class="form-input" required
                               placeholder="F.eks. 'Teltudlejning til bryllup - 100 personer'">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Noter</label>
                        <textarea name="notes" class="form-input" rows="2"></textarea>
                    </div>

                    <div style="background: var(--color-bg-subtle); padding: 1rem; border-radius: var(--radius-md); font-size: 0.875rem;">
                        <strong>Kommission:</strong> <?= $commissionStats['commission_rate'] ?>% af bookingbeløbet vil blive beregnet som kommission til platformen.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('add-booking-modal')">Annuller</button>
                    <button type="submit" class="btn btn-primary">Registrer booking</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openModal(id) {
        document.getElementById(id).classList.add('active');
    }
    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
    }

    function fillFromInquiry(select) {
        const opt = select.options[select.selectedIndex];
        if (opt.value) {
            document.getElementById('customer_name').value = opt.dataset.name || '';
            document.getElementById('customer_email').value = opt.dataset.email || '';
            document.getElementById('event_date').value = opt.dataset.date || '';
            document.getElementById('guest_count').value = opt.dataset.guests || '';
        }
    }

    // Close modal on outside click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) closeModal(overlay.id);
        });
    });
    </script>
</body>
</html>

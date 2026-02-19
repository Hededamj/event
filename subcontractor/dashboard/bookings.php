<?php
/**
 * Vendor Bookings List
 * Displays all bookings for the current vendor with status filter tabs.
 */

$pageTitle = 'Bookinger';
require_once __DIR__ . '/../includes/vendor-header.php';
require_once __DIR__ . '/../includes/booking-service.php';

// ============================================================
// Tab filtering
// ============================================================
$currentTab = $_GET['tab'] ?? 'alle';
$allowedTabs = ['alle', 'forespoergsler', 'aktive', 'afsluttede'];
if (!in_array($currentTab, $allowedTabs, true)) {
    $currentTab = 'alle';
}

// Status groups for each tab
$tabStatusMap = [
    'forespoergsler' => ['requested'],
    'aktive'         => ['quoted', 'accepted', 'deposited', 'confirmed'],
    'afsluttede'     => ['completed', 'reviewed', 'cancelled', 'refunded'],
];

// Fetch all vendor bookings (unfiltered — we filter in PHP)
$allBookings = getVendorBookings($vendorId);

// Filter by tab
if ($currentTab === 'alle') {
    $bookings = $allBookings;
} else {
    $statuses = $tabStatusMap[$currentTab] ?? [];
    $bookings = array_filter($allBookings, function ($b) use ($statuses) {
        return in_array($b['status'], $statuses, true);
    });
}

// Tab counts for badges
$counts = [
    'alle'           => count($allBookings),
    'forespoergsler' => count(array_filter($allBookings, fn($b) => in_array($b['status'], $tabStatusMap['forespoergsler'], true))),
    'aktive'         => count(array_filter($allBookings, fn($b) => in_array($b['status'], $tabStatusMap['aktive'], true))),
    'afsluttede'     => count(array_filter($allBookings, fn($b) => in_array($b['status'], $tabStatusMap['afsluttede'], true))),
];

// ============================================================
// Helpers (re-declare if not defined by index.php)
// ============================================================
if (!function_exists('formatDanishDate')) {
    function formatDanishDate(string $dateStr): string {
        $months = [
            1 => 'januar', 2 => 'februar', 3 => 'marts', 4 => 'april',
            5 => 'maj', 6 => 'juni', 7 => 'juli', 8 => 'august',
            9 => 'september', 10 => 'oktober', 11 => 'november', 12 => 'december'
        ];
        $ts = strtotime($dateStr);
        if (!$ts) return $dateStr;
        $day = (int) date('j', $ts);
        $month = $months[(int) date('n', $ts)];
        $year = date('Y', $ts);
        return "{$day}. {$month} {$year}";
    }
}

if (!function_exists('statusLabel')) {
    function statusLabel(string $status): string {
        $labels = [
            'requested'  => 'Forespurgt',
            'quoted'     => 'Tilbudt',
            'accepted'   => 'Accepteret',
            'deposited'  => 'Depositum betalt',
            'confirmed'  => 'Bekræftet',
            'completed'  => 'Afsluttet',
            'reviewed'   => 'Anmeldt',
            'cancelled'  => 'Annulleret',
            'refunded'   => 'Refunderet',
            'disputed'   => 'Disputeret',
        ];
        return $labels[$status] ?? ucfirst($status);
    }
}

// Tab labels
$tabLabels = [
    'alle'           => 'Alle',
    'forespoergsler' => 'Forespørgsler',
    'aktive'         => 'Aktive',
    'afsluttede'     => 'Afsluttede',
];

// Empty state messages per tab
$emptyMessages = [
    'alle'           => ['title' => 'Ingen bookinger endnu', 'text' => 'Når arrangører sender forespørgsler, vises de her.'],
    'forespoergsler' => ['title' => 'Ingen forespørgsler', 'text' => 'Du har ingen afventende forespørgsler i øjeblikket.'],
    'aktive'         => ['title' => 'Ingen aktive bookinger', 'text' => 'Du har ingen aktive bookinger i øjeblikket.'],
    'afsluttede'     => ['title' => 'Ingen afsluttede bookinger', 'text' => 'Dine afsluttede og annullerede bookinger vises her.'],
];
?>

<!-- Page Header -->
<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Bookinger</h1>
        <p class="page-subtitle">Administrer dine bookinger og forespørgsler</p>
    </div>
</div>

<!-- Filter Tabs -->
<div class="tabs">
    <?php foreach ($tabLabels as $tabKey => $tabLabel): ?>
        <a href="/subcontractor/dashboard/bookings.php<?= $tabKey !== 'alle' ? '?tab=' . urlencode($tabKey) : '' ?>"
           class="tab <?= $currentTab === $tabKey ? 'active' : '' ?>">
            <?= htmlspecialchars($tabLabel) ?>
            <?php if ($counts[$tabKey] > 0): ?>
                <span style="margin-left: 6px; opacity: 0.7;">(<?= $counts[$tabKey] ?>)</span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if (empty($bookings)): ?>
    <!-- Empty state -->
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            </div>
            <h3><?= htmlspecialchars($emptyMessages[$currentTab]['title']) ?></h3>
            <p><?= htmlspecialchars($emptyMessages[$currentTab]['text']) ?></p>
        </div>
    </div>
<?php else: ?>
    <!-- Bookings table -->
    <div class="card">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Dato</th>
                        <th>Arrangør</th>
                        <th>Ydelse</th>
                        <th>Pris</th>
                        <th>Status</th>
                        <th>Handling</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                        <tr onclick="window.location.href='/subcontractor/dashboard/booking-detail.php?id=<?= (int) $booking['id'] ?>'" style="cursor: pointer;">
                            <td class="table-cell-primary">
                                <?= htmlspecialchars($booking['event_title'] ?? '—') ?>
                            </td>
                            <td>
                                <?= !empty($booking['event_date']) ? formatDanishDate($booking['event_date']) : '—' ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($booking['account_name'] ?? '—') ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($booking['service_title'] ?? '—') ?>
                            </td>
                            <td>
                                <?php if (!empty($booking['quoted_price']) && (float) $booking['quoted_price'] > 0): ?>
                                    <?= number_format((float) $booking['quoted_price'], 2, ',', '.') ?> kr
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= htmlspecialchars($booking['status']) ?>">
                                    <?= htmlspecialchars(statusLabel($booking['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <a href="/subcontractor/dashboard/booking-detail.php?id=<?= (int) $booking['id'] ?>"
                                   class="btn btn-sm btn-secondary"
                                   onclick="event.stopPropagation();">
                                    Se detaljer
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/vendor-footer.php'; ?>

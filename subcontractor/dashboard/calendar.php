<?php
/**
 * Vendor Calendar
 * Monthly calendar view showing confirmed/deposited bookings.
 */

$pageTitle = 'Kalender';
require_once __DIR__ . '/../includes/vendor-header.php';
require_once __DIR__ . '/../includes/booking-service.php';

// Danish month names
$danishMonths = [
    1 => 'Januar', 2 => 'Februar', 3 => 'Marts', 4 => 'April',
    5 => 'Maj', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'December'
];

// Danish day abbreviations (Monday-Sunday)
$danishDays = ['Man', 'Tir', 'Ons', 'Tor', 'Fre', 'Lor', 'Son'];

// Get month/year from URL, default to current
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');

// Clamp month to valid range
if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

// Clamp year to reasonable range
if ($year < 2020) $year = 2020;
if ($year > 2050) $year = 2050;

// Calculate first and last day of the month
$firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth     = (int)date('t', $firstDayOfMonth);
$firstDayWeekday = (int)date('N', $firstDayOfMonth); // 1=Mon, 7=Sun

// Previous and next month
$prevMonth = $month - 1;
$prevYear  = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }

$nextMonth = $month + 1;
$nextYear  = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

// Format date range for query
$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd   = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

// Query confirmed/deposited bookings for this vendor in the displayed month
$db = getDB();
$stmt = $db->prepare("
    SELECT
        b.id,
        b.event_date,
        b.status,
        b.quoted_price,
        e.name AS event_title
    FROM bookings b
    JOIN events e ON e.id = b.event_id
    WHERE b.vendor_id = ?
      AND b.status IN ('deposited', 'confirmed')
      AND b.event_date >= ?
      AND b.event_date <= ?
    ORDER BY b.event_date ASC
");
$stmt->execute([$vendorId, $monthStart, $monthEnd]);
$bookings = $stmt->fetchAll();

// Group bookings by day number
$bookingsByDay = [];
foreach ($bookings as $booking) {
    $eventDate = $booking['event_date'] ?? '';
    $day = $eventDate ? (int)date('j', strtotime($eventDate)) : 0;
    if (!isset($bookingsByDay[$day])) {
        $bookingsByDay[$day] = [];
    }
    $bookingsByDay[$day][] = $booking;
}

$today = (int)date('j');
$currentMonth = (int)date('n');
$currentYear  = (int)date('Y');
$isCurrentMonth = ($month === $currentMonth && $year === $currentYear);
?>

<style>
    .calendar-nav {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 24px;
    }
    .calendar-nav-title {
        font-family: var(--font-display);
        font-size: 24px;
        font-weight: 500;
        color: var(--text);
        text-align: center;
    }
    .calendar-nav a {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border-radius: 10px;
        border: 2px solid var(--border);
        background: var(--surface-card);
        color: var(--text);
        text-decoration: none;
        transition: all 0.2s var(--ease-out);
    }
    .calendar-nav a:hover {
        border-color: var(--accent);
        background: var(--surface);
    }
    .calendar-nav a svg {
        width: 20px;
        height: 20px;
    }
    .calendar-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 4px;
        table-layout: fixed;
    }
    .calendar-table th {
        padding: 10px 4px;
        text-align: center;
        font-size: 13px;
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .calendar-table td {
        vertical-align: top;
        padding: 8px;
        min-height: 80px;
        height: 80px;
        border-radius: 10px;
        background: var(--surface-card);
        border: 1px solid var(--border);
        transition: all 0.2s var(--ease-out);
    }
    .calendar-table td.empty {
        background: transparent;
        border-color: transparent;
    }
    .calendar-table td.today {
        border-color: var(--accent);
        background: rgba(168, 181, 160, 0.06);
    }
    .calendar-table td.has-bookings {
        cursor: default;
    }
    .calendar-day-number {
        font-size: 14px;
        font-weight: 600;
        color: var(--text);
        margin-bottom: 4px;
    }
    .calendar-table td.today .calendar-day-number {
        color: var(--accent-dark);
    }
    .calendar-booking-dot {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        text-decoration: none;
        margin-bottom: 2px;
        transition: all 0.15s var(--ease-out);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
    }
    .calendar-booking-dot.status-confirmed {
        background: rgba(168, 181, 160, 0.2);
        color: var(--accent-dark);
    }
    .calendar-booking-dot.status-deposited {
        background: rgba(212, 175, 55, 0.15);
        color: #8B7A2E;
    }
    .calendar-booking-dot:hover {
        opacity: 0.8;
        transform: scale(1.02);
    }
    .calendar-booking-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: var(--accent);
        color: var(--surface-card);
        font-size: 11px;
        font-weight: 700;
    }

    @media (max-width: 768px) {
        .calendar-table td {
            padding: 4px;
            height: 60px;
            min-height: 60px;
        }
        .calendar-day-number {
            font-size: 12px;
        }
        .calendar-booking-dot {
            font-size: 0;
            padding: 0;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: block;
            margin: 2px auto 0;
        }
        .calendar-nav-title {
            font-size: 18px;
        }
    }
</style>

<!-- Page Header -->
<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Kalender</h1>
        <p class="page-subtitle">Overblik over dine bookinger</p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <!-- Month Navigation -->
        <div class="calendar-nav">
            <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" title="<?= $danishMonths[$prevMonth] ?> <?= $prevYear ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <div class="calendar-nav-title"><?= $danishMonths[$month] ?> <?= $year ?></div>
            <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" title="<?= $danishMonths[$nextMonth] ?> <?= $nextYear ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </a>
        </div>

        <!-- Calendar Grid -->
        <table class="calendar-table">
            <thead>
                <tr>
                    <?php foreach ($danishDays as $dayName): ?>
                        <th><?= $dayName ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $dayCounter = 1;
                $cellCount  = 0;

                // Calculate total cells needed (fill in leading empty cells + days)
                $totalCells = $firstDayWeekday - 1 + $daysInMonth;
                // Round up to complete the last week
                $totalCells = (int)ceil($totalCells / 7) * 7;

                for ($cell = 0; $cell < $totalCells; $cell++):
                    if ($cell % 7 === 0) echo '<tr>';

                    $isEmptyBefore = $cell < ($firstDayWeekday - 1);
                    $isEmptyAfter  = $dayCounter > $daysInMonth;

                    if ($isEmptyBefore || $isEmptyAfter):
                ?>
                        <td class="empty"></td>
                <?php
                    else:
                        $isToday = $isCurrentMonth && $dayCounter === $today;
                        $hasBookings = isset($bookingsByDay[$dayCounter]);
                        $classes = [];
                        if ($isToday)       $classes[] = 'today';
                        if ($hasBookings)   $classes[] = 'has-bookings';
                ?>
                        <td class="<?= implode(' ', $classes) ?>">
                            <div class="calendar-day-number"><?= $dayCounter ?></div>
                            <?php if ($hasBookings): ?>
                                <?php
                                $dayBookings = $bookingsByDay[$dayCounter];
                                if (count($dayBookings) <= 2):
                                    foreach ($dayBookings as $b):
                                ?>
                                    <a href="/subcontractor/dashboard/booking-detail.php?id=<?= (int)$b['id'] ?>"
                                       class="calendar-booking-dot status-<?= htmlspecialchars($b['status']) ?>"
                                       title="<?= htmlspecialchars($b['event_title']) ?>">
                                        <?= htmlspecialchars(mb_strimwidth($b['event_title'], 0, 12, '...')) ?>
                                    </a>
                                <?php
                                    endforeach;
                                else:
                                    // Show first booking + count
                                    $first = $dayBookings[0];
                                ?>
                                    <a href="/subcontractor/dashboard/booking-detail.php?id=<?= (int)$first['id'] ?>"
                                       class="calendar-booking-dot status-<?= htmlspecialchars($first['status']) ?>"
                                       title="<?= htmlspecialchars($first['event_title']) ?>">
                                        <?= htmlspecialchars(mb_strimwidth($first['event_title'], 0, 10, '...')) ?>
                                    </a>
                                    <span class="calendar-booking-count" title="<?= count($dayBookings) ?> bookinger">
                                        +<?= count($dayBookings) - 1 ?>
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                <?php
                        $dayCounter++;
                    endif;

                    if ($cell % 7 === 6) echo '</tr>';
                endfor;
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (empty($bookings)): ?>
<div class="card" style="margin-top: 24px;">
    <div class="card-body">
        <div class="empty-state">
            <div class="empty-state-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            </div>
            <h3>Ingen bookinger i <?= $danishMonths[$month] ?></h3>
            <p>Du har ingen bekraeftede bookinger i denne maaned.</p>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/vendor-footer.php'; ?>

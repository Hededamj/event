<?php
/**
 * Vendor Dashboard Home
 * Overview page with stats, upcoming bookings, and recent reviews.
 */

$pageTitle = 'Oversigt';
require_once __DIR__ . '/../includes/vendor-header.php';

// vendor-header.php already handles: vendor-auth, functions, requireVendorLogin, getDB
// $currentVendor, $vendorId are available from vendor-header.php

$db = getDB();

// ============================================================
// 1. Stats: Active bookings (deposited + confirmed)
// ============================================================
$stmtActive = $db->prepare("
    SELECT COUNT(*) FROM bookings
    WHERE vendor_id = ? AND status IN ('deposited', 'confirmed')
");
$stmtActive->execute([$vendorId]);
$activeBookings = (int) $stmtActive->fetchColumn();

// ============================================================
// 2. Stats: Pending requests
// ============================================================
$stmtPending = $db->prepare("
    SELECT COUNT(*) FROM bookings
    WHERE vendor_id = ? AND status = 'requested'
");
$stmtPending->execute([$vendorId]);
$pendingRequests = (int) $stmtPending->fetchColumn();

// ============================================================
// 3. Stats: Total earnings
// ============================================================
$stmtEarnings = $db->prepare("
    SELECT COALESCE(SUM(vendor_payout), 0) FROM bookings
    WHERE vendor_id = ? AND status IN ('completed', 'reviewed')
");
$stmtEarnings->execute([$vendorId]);
$totalEarnings = (float) $stmtEarnings->fetchColumn();

// ============================================================
// 4. Stats: Average rating (from vendors table, already denormalized)
// ============================================================
$stmtRating = $db->prepare("
    SELECT avg_rating, review_count FROM vendors WHERE id = ?
");
$stmtRating->execute([$vendorId]);
$vendorStats = $stmtRating->fetch();
$avgRating = (float) ($vendorStats['avg_rating'] ?? 0);
$reviewCount = (int) ($vendorStats['review_count'] ?? 0);

// ============================================================
// 5. Upcoming bookings (next 5 confirmed/deposited by event_date ASC)
// ============================================================
$stmtUpcoming = $db->prepare("
    SELECT
        b.id,
        b.event_date,
        b.guest_count,
        b.quoted_price,
        b.status,
        e.name AS event_title
    FROM bookings b
    JOIN events e ON e.id = b.event_id
    WHERE b.vendor_id = ?
      AND b.status IN ('deposited', 'confirmed')
      AND b.event_date >= CURDATE()
    ORDER BY b.event_date ASC
    LIMIT 5
");
$stmtUpcoming->execute([$vendorId]);
$upcomingBookings = $stmtUpcoming->fetchAll();

// ============================================================
// 6. Recent reviews (last 3)
// ============================================================
$stmtReviews = $db->prepare("
    SELECT
        vr.id,
        vr.rating,
        vr.title,
        vr.review_text,
        vr.created_at,
        a.name AS reviewer_name
    FROM vendor_reviews vr
    JOIN accounts a ON a.id = vr.account_id
    WHERE vr.vendor_id = ?
    ORDER BY vr.created_at DESC
    LIMIT 3
");
$stmtReviews->execute([$vendorId]);
$recentReviews = $stmtReviews->fetchAll();

// ============================================================
// Helpers
// ============================================================

/** Format a date in Danish style: "15. januar 2026" */
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

/** Map booking status to Danish label */
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

/** Render star icons for a given rating (1-5) */
function renderStars(int $rating): string {
    $html = '<div class="stars">';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $html .= '<svg class="star-filled" viewBox="0 0 24 24" fill="currentColor"><path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path></svg>';
        } else {
            $html .= '<svg class="star-empty" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path></svg>';
        }
    }
    $html .= '</div>';
    return $html;
}
?>

<!-- Page Header -->
<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Velkommen, <?= htmlspecialchars($currentVendor['company_name'] ?? 'Leverandør') ?></h1>
        <p class="page-subtitle"><?= formatDanishDate(date('Y-m-d')) ?></p>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <!-- Active bookings -->
    <div class="stat-card">
        <div class="stat-info">
            <div class="stat-value"><?= $activeBookings ?></div>
            <div class="stat-label">Aktive bookinger</div>
        </div>
        <div class="stat-icon sage">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
        </div>
    </div>

    <!-- Pending requests -->
    <div class="stat-card">
        <div class="stat-info">
            <div class="stat-value"><?= $pendingRequests ?></div>
            <div class="stat-label">Afventende forespørgsler</div>
        </div>
        <div class="stat-icon gold">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
    </div>

    <!-- Total earnings -->
    <div class="stat-card">
        <div class="stat-info">
            <div class="stat-value"><?= number_format($totalEarnings, 2, ',', '.') ?> kr</div>
            <div class="stat-label">Total indtjening</div>
        </div>
        <div class="stat-icon success">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
    </div>

    <!-- Average rating -->
    <div class="stat-card">
        <div class="stat-info">
            <div class="stat-value"><?= $avgRating > 0 ? number_format($avgRating, 1, ',', '.') : '—' ?></div>
            <div class="stat-label">Gns. bedømmelse<?= $reviewCount > 0 ? " ({$reviewCount})" : '' ?></div>
        </div>
        <div class="stat-icon gold">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
            </svg>
        </div>
    </div>
</div>

<!-- Upcoming Bookings -->
<div class="card" style="margin-bottom: 32px;">
    <div class="card-header">
        <h2 class="card-title">Kommende bookinger</h2>
        <a href="/subcontractor/dashboard/bookings.php" class="text-sm" style="color: var(--accent-dark); text-decoration: none; font-weight: 500;">Se alle</a>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($upcomingBookings)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <h3>Ingen kommende bookinger</h3>
                <p>Du har ingen aktive bookinger i øjeblikket.</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Dato</th>
                            <th>Gæster</th>
                            <th>Pris</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcomingBookings as $booking): ?>
                            <tr onclick="window.location.href='/subcontractor/dashboard/booking-detail.php?id=<?= (int) $booking['id'] ?>'" style="cursor: pointer;">
                                <td class="table-cell-primary">
                                    <a href="/subcontractor/dashboard/booking-detail.php?id=<?= (int) $booking['id'] ?>" style="color: inherit; text-decoration: none;">
                                        <?= htmlspecialchars($booking['event_title']) ?>
                                    </a>
                                </td>
                                <td><?= formatDanishDate($booking['event_date']) ?></td>
                                <td><?= $booking['guest_count'] ? (int) $booking['guest_count'] : '—' ?></td>
                                <td><?= $booking['quoted_price'] ? number_format((float) $booking['quoted_price'], 2, ',', '.') . ' kr' : '—' ?></td>
                                <td><span class="badge badge-<?= htmlspecialchars($booking['status']) ?>"><?= htmlspecialchars(statusLabel($booking['status'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Reviews -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Seneste anmeldelser</h2>
        <a href="/subcontractor/dashboard/reviews.php" class="text-sm" style="color: var(--accent-dark); text-decoration: none; font-weight: 500;">Se alle</a>
    </div>
    <div class="card-body">
        <?php if (empty($recentReviews)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                    </svg>
                </div>
                <h3>Ingen anmeldelser endnu</h3>
                <p>Anmeldelser vises her, når dine kunder har bedømt dig.</p>
            </div>
        <?php else: ?>
            <?php foreach ($recentReviews as $review): ?>
                <div style="padding-bottom: 20px; margin-bottom: 20px; border-bottom: 1px solid var(--border);">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <?= renderStars((int) $review['rating']) ?>
                            <span style="font-weight: 600; font-size: 14px;"><?= htmlspecialchars($review['reviewer_name']) ?></span>
                        </div>
                        <span class="text-sm text-muted"><?= formatDanishDate($review['created_at']) ?></span>
                    </div>
                    <?php if (!empty($review['title'])): ?>
                        <div style="font-weight: 600; margin-bottom: 4px;"><?= htmlspecialchars($review['title']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($review['review_text'])): ?>
                        <p class="text-muted" style="margin: 0; font-size: 14px; line-height: 1.6;"><?= htmlspecialchars($review['review_text']) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <div style="text-align: center;">
                <a href="/subcontractor/dashboard/reviews.php" style="color: var(--accent-dark); text-decoration: none; font-weight: 500; font-size: 14px;">Se alle anmeldelser</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/vendor-footer.php'; ?>

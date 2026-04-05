<?php
/**
 * Platform Admin - Event Detail
 * Full read-only view of any event on the platform for support purposes
 */

require_once __DIR__ . '/../includes/admin-platform-header.php';

$db = getDB();
$eventId = (int)($_GET['id'] ?? 0);

if (!$eventId) {
    redirect(BASE_PATH . '/admin-platform/events.php');
}

// Get event with type info
$stmt = $db->prepare("
    SELECT e.*, et.name as event_type_name, et.icon as event_type_icon
    FROM events e
    LEFT JOIN event_types et ON e.event_type_id = et.id
    WHERE e.id = ?
");
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) {
    setFlash('error', 'Event ikke fundet');
    redirect(BASE_PATH . '/admin-platform/events.php');
}

// Get owners
$stmt = $db->prepare("
    SELECT eo.role, a.id as account_id, a.name, a.email
    FROM event_owners eo
    JOIN accounts a ON eo.account_id = a.id
    WHERE eo.event_id = ?
    ORDER BY eo.role
");
$stmt->execute([$eventId]);
$owners = $stmt->fetchAll();

// Get guest stats
$stmt = $db->prepare("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN rsvp_status = 'yes' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN rsvp_status = 'no' THEN 1 ELSE 0 END) as declined,
        SUM(CASE WHEN rsvp_status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN rsvp_status = 'yes' THEN adults_count ELSE 0 END) as total_adults,
        SUM(CASE WHEN rsvp_status = 'yes' THEN children_count ELSE 0 END) as total_children
    FROM guests WHERE event_id = ?
");
$stmt->execute([$eventId]);
$guestStats = $stmt->fetch();

// Get guests list
$stmt = $db->prepare("
    SELECT * FROM guests WHERE event_id = ? ORDER BY name ASC
");
$stmt->execute([$eventId]);
$guests = $stmt->fetchAll();

// Get wishlist items
$stmt = $db->prepare("
    SELECT wi.*, g.name as reserved_by_name
    FROM wishlist_items wi
    LEFT JOIN guests g ON wi.reserved_by_guest_id = g.id
    WHERE wi.event_id = ?
    ORDER BY wi.sort_order, wi.id
");
$stmt->execute([$eventId]);
$wishlistItems = $stmt->fetchAll();

// Get schedule items
$stmt = $db->prepare("
    SELECT * FROM schedule_items WHERE event_id = ? ORDER BY start_time, sort_order
");
$stmt->execute([$eventId]);
$scheduleItems = $stmt->fetchAll();

// Get menu items
$stmt = $db->prepare("
    SELECT * FROM menu_items WHERE event_id = ? ORDER BY category, sort_order
");
$stmt->execute([$eventId]);
$menuItems = $stmt->fetchAll();

// Public URL
$publicUrl = '/e/' . ($event['slug'] ?? $eventId);

$evStatus = $event['status'] ?? 'active';
$eventBadgeMap = ['active' => 'badge-success', 'draft' => 'badge-warning', 'completed' => 'badge-info', 'archived' => 'badge-error'];
$eventTextMap = ['active' => 'Aktiv', 'draft' => 'Kladde', 'completed' => 'Afsluttet', 'archived' => 'Arkiveret'];
?>

<header class="platform-header">
    <h1 class="page-title">
        <a href="<?= BASE_PATH ?>/admin-platform/events.php" style="color: var(--text-secondary); text-decoration: none;">Events</a>
        &rsaquo; <?= escape($event['name']) ?>
    </h1>
    <div class="header-actions">
        <span class="badge <?= $eventBadgeMap[$evStatus] ?? 'badge-info' ?>"><?= $eventTextMap[$evStatus] ?? ucfirst($evStatus) ?></span>
        <?php if ($event['slug']): ?>
            <a href="<?= $publicUrl ?>" target="_blank" class="btn btn-secondary btn-sm">Se gæsteside</a>
        <?php endif; ?>
    </div>
</header>

<div class="platform-content">
    <!-- Event Info -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-lg);">
        <div class="card">
            <h2 class="card-title">Event-detaljer</h2>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-md);">
                <div>
                    <div class="text-sm text-muted">Navn</div>
                    <div class="font-medium"><?= escape($event['name']) ?></div>
                </div>
                <div>
                    <div class="text-sm text-muted">Type</div>
                    <div class="font-medium"><?= escape($event['event_type_name'] ?? '-') ?></div>
                </div>
                <div>
                    <div class="text-sm text-muted">Dato</div>
                    <div class="font-medium"><?= $event['event_date'] ? date('d/m/Y', strtotime($event['event_date'])) : '-' ?></div>
                </div>
                <div>
                    <div class="text-sm text-muted">Tidspunkt</div>
                    <div class="font-medium"><?= $event['event_time'] ? date('H:i', strtotime($event['event_time'])) : '-' ?></div>
                </div>
                <div>
                    <div class="text-sm text-muted">Lokation</div>
                    <div class="font-medium"><?= escape($event['location'] ?? '-') ?></div>
                </div>
                <div>
                    <div class="text-sm text-muted">Slug</div>
                    <div class="font-medium">/<?= escape($event['slug'] ?? '-') ?></div>
                </div>
                <div>
                    <div class="text-sm text-muted">Oprettet</div>
                    <div class="font-medium"><?= date('d/m/Y H:i', strtotime($event['created_at'])) ?></div>
                </div>
                <div>
                    <div class="text-sm text-muted">Hovedperson</div>
                    <div class="font-medium"><?= escape($event['main_person_name'] ?? $event['confirmand_name'] ?? '-') ?></div>
                </div>
            </div>
            <?php if (!empty($event['welcome_text'])): ?>
                <div style="margin-top: var(--space-md);">
                    <div class="text-sm text-muted">Velkomsttekst</div>
                    <div style="margin-top: var(--space-xs); padding: var(--space-sm); background: var(--bg-secondary); border-radius: var(--radius-sm);">
                        <?= nl2br(escape($event['welcome_text'])) ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2 class="card-title">Ejere & Adgang</h2>
            <?php if (empty($owners)): ?>
                <div class="text-muted">Ingen ejere registreret</div>
            <?php else: ?>
                <?php foreach ($owners as $owner): ?>
                    <div style="display: flex; align-items: center; gap: var(--space-sm); margin-bottom: var(--space-sm);">
                        <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 600;">
                            <?= strtoupper(substr($owner['name'], 0, 1)) ?>
                        </div>
                        <div style="flex: 1;">
                            <a href="<?= BASE_PATH ?>/admin-platform/account-detail.php?id=<?= $owner['account_id'] ?>"
                               class="font-medium" style="text-decoration: none;"><?= escape($owner['name']) ?></a>
                            <div class="text-xs text-muted"><?= escape($owner['email']) ?></div>
                        </div>
                        <span class="badge badge-info"><?= ucfirst($owner['role']) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <hr style="margin: var(--space-lg) 0; border: none; border-top: 1px solid var(--border);">

            <h2 class="card-title">Gæste-statistik</h2>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-md);">
                <div style="text-align: center;">
                    <div style="font-size: 24px; font-weight: 600; color: var(--success);"><?= $guestStats['confirmed'] ?? 0 ?></div>
                    <div class="text-sm text-muted">Bekræftet</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 24px; font-weight: 600; color: var(--warning);"><?= $guestStats['pending'] ?? 0 ?></div>
                    <div class="text-sm text-muted">Afventer</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 24px; font-weight: 600; color: var(--error);"><?= $guestStats['declined'] ?? 0 ?></div>
                    <div class="text-sm text-muted">Afvist</div>
                </div>
            </div>
            <?php if (($guestStats['total_adults'] ?? 0) > 0): ?>
                <div style="margin-top: var(--space-md); text-align: center;" class="text-sm text-muted">
                    <?= $guestStats['total_adults'] ?> voksne, <?= $guestStats['total_children'] ?> børn
                </div>
            <?php endif; ?>

            <div style="display: flex; gap: var(--space-sm); margin-top: var(--space-md);">
                <div class="text-sm text-muted">
                    Synlighed:
                    <?php if ($event['show_wishlist']): ?><span class="badge badge-info" style="font-size:10px;">Ønskeliste</span><?php endif; ?>
                    <?php if ($event['show_menu']): ?><span class="badge badge-info" style="font-size:10px;">Menu</span><?php endif; ?>
                    <?php if ($event['show_schedule']): ?><span class="badge badge-info" style="font-size:10px;">Program</span><?php endif; ?>
                    <?php if ($event['show_photos']): ?><span class="badge badge-info" style="font-size:10px;">Fotos</span><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Guests -->
    <div class="card mt-md">
        <h2 class="card-title">Gæsteliste (<?= $guestStats['total'] ?? 0 ?>)</h2>
        <?php if (empty($guests)): ?>
            <div class="empty-state"><p>Ingen gæster tilføjet</p></div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Navn</th>
                            <th>Email</th>
                            <th>Telefon</th>
                            <th>RSVP</th>
                            <th>Voksne</th>
                            <th>Børn</th>
                            <th>Kost</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($guests as $guest): ?>
                            <tr>
                                <td class="font-medium"><?= escape($guest['name']) ?></td>
                                <td class="text-sm"><?= escape($guest['email'] ?? '-') ?></td>
                                <td class="text-sm"><?= escape($guest['phone'] ?? '-') ?></td>
                                <td>
                                    <?php
                                    $rsvpBadge = ['yes' => 'badge-success', 'no' => 'badge-error', 'pending' => 'badge-warning'];
                                    $rsvpText = ['yes' => 'Ja', 'no' => 'Nej', 'pending' => 'Afventer'];
                                    $rs = $guest['rsvp_status'];
                                    ?>
                                    <span class="badge <?= $rsvpBadge[$rs] ?? 'badge-info' ?>"><?= $rsvpText[$rs] ?? $rs ?></span>
                                </td>
                                <td class="text-sm"><?= $guest['adults_count'] ?></td>
                                <td class="text-sm"><?= $guest['children_count'] ?></td>
                                <td class="text-sm text-muted"><?= escape($guest['dietary_notes'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Wishlist & Schedule side by side -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-lg); margin-top: var(--space-md);">
        <!-- Wishlist -->
        <div class="card">
            <h2 class="card-title">Ønskeliste (<?= count($wishlistItems) ?>)</h2>
            <?php if (empty($wishlistItems)): ?>
                <div class="empty-state"><p>Ingen ønsker</p></div>
            <?php else: ?>
                <?php foreach ($wishlistItems as $item): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: var(--space-sm) 0; border-bottom: 1px solid var(--border);">
                        <div>
                            <div class="font-medium"><?= escape($item['name']) ?></div>
                            <?php if (!empty($item['description'])): ?>
                                <div class="text-xs text-muted"><?= escape($item['description']) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php if ($item['reserved_by_guest_id']): ?>
                            <span class="badge badge-success">Reserveret<?= $item['reserved_by_name'] ? ' af ' . escape($item['reserved_by_name']) : '' ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Schedule -->
        <div class="card">
            <h2 class="card-title">Program (<?= count($scheduleItems) ?>)</h2>
            <?php if (empty($scheduleItems)): ?>
                <div class="empty-state"><p>Intet program</p></div>
            <?php else: ?>
                <?php foreach ($scheduleItems as $item): ?>
                    <div style="display: flex; gap: var(--space-sm); padding: var(--space-sm) 0; border-bottom: 1px solid var(--border);">
                        <div class="font-medium text-sm" style="min-width: 50px;">
                            <?= $item['start_time'] ? date('H:i', strtotime($item['start_time'])) : '' ?>
                        </div>
                        <div>
                            <div class="font-medium"><?= escape($item['title']) ?></div>
                            <?php if (!empty($item['description'])): ?>
                                <div class="text-xs text-muted"><?= escape($item['description']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Menu -->
    <?php if (!empty($menuItems)): ?>
        <div class="card mt-md">
            <h2 class="card-title">Menu (<?= count($menuItems) ?>)</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Kategori</th>
                            <th>Ret</th>
                            <th>Beskrivelse</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($menuItems as $item): ?>
                            <tr>
                                <td class="text-sm"><?= escape($item['category'] ?? '-') ?></td>
                                <td class="font-medium"><?= escape($item['name']) ?></td>
                                <td class="text-sm text-muted"><?= escape($item['description'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/admin-platform-footer.php'; ?>

<?php
/**
 * Guest - Menu View
 */
require_once __DIR__ . '/../includes/guest-header.php';

// Get menu items grouped by course
$stmt = $db->prepare("
    SELECT * FROM menu_items
    WHERE event_id = ?
    ORDER BY sort_order ASC
");
$stmt->execute([$eventId]);
$menuItems = $stmt->fetchAll();

// Group by course
$courses = [
    'starter' => ['label' => 'Forret', 'icon' => '🥗'],
    'main' => ['label' => 'Hovedret', 'icon' => '🍽️'],
    'dessert' => ['label' => 'Dessert', 'icon' => '🍰'],
    'drink' => ['label' => 'Drikkevarer', 'icon' => '🥂'],
    'snack' => ['label' => 'Snacks', 'icon' => '🍿'],
    'other' => ['label' => 'Andet', 'icon' => '✨']
];

$menuByCourse = [];
foreach ($menuItems as $item) {
    $course = $item['course'] ?? 'other';
    $menuByCourse[$course][] = $item;
}
?>

<h1 class="h2 mb-sm text-center">Menu</h1>
<p class="text-center text-muted mb-lg">
    Her er hvad der serveres til <?= escape($event['confirmand_name']) ?>s <?= escape($event['name']) ?>
</p>

<?php if (empty($menuItems)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state__icon">🍽️</div>
            <h3 class="empty-state__title">Menuen kommer snart</h3>
            <p class="empty-state__text">Menuen er ikke offentliggjort endnu</p>
        </div>
    </div>

<?php else: ?>

    <?php foreach ($courses as $courseKey => $courseInfo): ?>
        <?php if (isset($menuByCourse[$courseKey]) && !empty($menuByCourse[$courseKey])): ?>
            <div class="card mb-md">
                <h2 class="card__title mb-sm">
                    <span><?= $courseInfo['icon'] ?></span>
                    <?= $courseInfo['label'] ?>
                </h2>

                <?php foreach ($menuByCourse[$courseKey] as $index => $item): ?>
                    <div style="<?= $index > 0 ? 'border-top: 1px solid var(--color-border-soft); padding-top: var(--space-sm); margin-top: var(--space-sm);' : '' ?>">
                        <h3 style="font-family: 'Cormorant Garamond', serif; font-size: var(--text-lg); font-weight: 500; margin-bottom: var(--space-3xs);">
                            <?= escape($item['title']) ?>
                        </h3>
                        <?php if ($item['description']): ?>
                            <p class="small text-muted" style="line-height: 1.6;">
                                <?= escape($item['description']) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php if ($guest['dietary_notes']): ?>
        <div class="card" style="background: var(--color-bg-subtle);">
            <p class="small">
                <strong>Dine kostbehov:</strong> <?= escape($guest['dietary_notes']) ?>
            </p>
            <p class="small text-muted mt-xs">
                Vi tager højde for dette i tilberedningen.
            </p>
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/guest-footer.php'; ?>
</body>
</html>

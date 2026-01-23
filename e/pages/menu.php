<?php
/**
 * Guest Menu Page
 */

$courses = ['starter' => 'Forret', 'main' => 'Hovedret', 'dessert' => 'Dessert', 'drinks' => 'Drikkevarer', 'snacks' => 'Snacks'];
$menuItems = [];
foreach ($courses as $key => $label) {
    $stmt = $db->prepare("SELECT * FROM menu_items WHERE event_id = ? AND course = ? ORDER BY sort_order ASC");
    $stmt->execute([$eventId, $key]);
    $items = $stmt->fetchAll();
    if (!empty($items)) {
        $menuItems[$key] = ['label' => $label, 'items' => $items];
    }
}
?>

<h1 class="serif" style="font-size: 24px; text-align: center; margin-bottom: 24px;">Menu</h1>

<?php if (empty($menuItems)): ?>
<div class="card">
    <div class="empty-state">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
        <p>Menuen er ikke offentliggjort endnu</p>
    </div>
</div>
<?php else: ?>
<?php foreach ($menuItems as $courseKey => $course): ?>
<div class="card">
    <h2 class="serif" style="font-size: 18px; color: var(--primary); margin-bottom: 16px;"><?= $course['label'] ?></h2>
    <div style="display: flex; flex-direction: column; gap: 16px;">
        <?php foreach ($course['items'] as $item): ?>
        <div style="padding-bottom: 16px; border-bottom: 1px solid var(--gray-100);">
            <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 4px;"><?= htmlspecialchars($item['title']) ?></h3>
            <?php if ($item['description']): ?>
            <p style="font-size: 14px; color: var(--gray-600);"><?= htmlspecialchars($item['description']) ?></p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

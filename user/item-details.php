<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_role('user');

$pdo = db();
$id = positive_int($_GET['id'] ?? null);

if ($id === null) {
    flash('error', 'Invalid item.');
    redirect('user/items.php');
}

$stmt = $pdo->prepare('SELECT * FROM items WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$item = $stmt->fetch();

if (!$item) {
    flash('error', 'Item not found.');
    redirect('user/items.php');
}

$historyStmt = $pdo->prepare(
    'SELECT sm.*, u.full_name AS created_by_name
     FROM stock_movements sm
     LEFT JOIN users u ON u.id = sm.created_by
     WHERE sm.item_id = :id
     ORDER BY sm.movement_date DESC, sm.id DESC
     LIMIT 25'
);
$historyStmt->execute(['id' => $id]);
$history = $historyStmt->fetchAll();

$pageTitle = 'Item Details';
require __DIR__ . '/../includes/header.php';
?>

<div class="toolbar">
    <a class="btn btn-secondary" href="<?= e(url('user/items.php')) ?>">Back to Items</a>
</div>

<div class="card">
    <h2><?= e($item['item_name']) ?></h2>
    <?php if ((int) $item['quantity'] <= (int) $item['reorder_level']): ?>
        <p><span class="badge badge-low">Low stock</span></p>
    <?php endif; ?>
    <dl class="detail-grid">
        <div class="detail-item"><dt>Item Code</dt><dd><?= e($item['item_code']) ?></dd></div>
        <div class="detail-item"><dt>Category</dt><dd><?= e($item['category']) ?></dd></div>
        <div class="detail-item"><dt>Location</dt><dd><?= e($item['location']) ?></dd></div>
        <div class="detail-item"><dt>Current Quantity</dt><dd><?= e((string) $item['quantity']) ?> <?= e($item['unit']) ?></dd></div>
        <div class="detail-item"><dt>Reorder Level</dt><dd><?= e((string) $item['reorder_level']) ?></dd></div>
        <div class="detail-item"><dt>Created</dt><dd><?= e(format_datetime($item['created_at'])) ?></dd></div>
        <div class="detail-item"><dt>Updated</dt><dd><?= e(format_datetime($item['updated_at'])) ?></dd></div>
    </dl>
</div>

<div class="card">
    <h3>Stock History</h3>
    <?php if (!$history): ?>
        <div class="empty-state">No stock movements yet.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Reference</th>
                        <th>User</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $row): ?>
                        <tr>
                            <td><?= e(format_date($row['movement_date'])) ?></td>
                            <td>
                                <span class="badge badge-<?= $row['movement_type'] === 'IN' ? 'in' : 'out' ?>">
                                    <?= e($row['movement_type']) ?>
                                </span>
                            </td>
                            <td><?= e((string) $row['quantity']) ?></td>
                            <td><?= e($row['reference_note'] ?? '') ?></td>
                            <td><?= e($row['created_by_name'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

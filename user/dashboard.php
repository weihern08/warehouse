<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_role('user');

$pdo = db();

$totalItems = (int) $pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();
$totalQty = (int) $pdo->query('SELECT COALESCE(SUM(quantity), 0) FROM items')->fetchColumn();
$lowStockCount = (int) $pdo->query(
    'SELECT COUNT(*) FROM items WHERE quantity <= reorder_level'
)->fetchColumn();

$recent = $pdo->query(
    "SELECT sm.movement_date, sm.movement_type, sm.quantity, sm.reference_note,
            i.item_code, i.item_name, u.full_name AS created_by_name
     FROM stock_movements sm
     INNER JOIN items i ON i.id = sm.item_id
     LEFT JOIN users u ON u.id = sm.created_by
     ORDER BY sm.movement_date DESC, sm.id DESC
     LIMIT 15"
)->fetchAll();

$pageTitle = 'Dashboard';
$user = current_user();
require __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <h2>Login Accounts</h2>
    <div class="alert alert-info" role="status">
        <p><strong>Current user:</strong> <?= e($user['username'] ?? '') ?></p>
        <p><strong>Admin</strong> — Username: <code>admin</code> / Password: <code>admin123</code></p>
        <p><strong>User</strong> — Username: <code>demo</code> / Password: <code>user123</code></p>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="label">Total Items</div>
        <div class="value"><?= e((string) $totalItems) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Total Stock Quantity</div>
        <div class="value"><?= e((string) $totalQty) ?></div>
    </div>
    <div class="stat-card warning">
        <div class="label">Low Stock Items</div>
        <div class="value"><?= e((string) $lowStockCount) ?></div>
    </div>
</div>

<div class="card">
    <h2>Recent Stock History</h2>
    <?php if (!$recent): ?>
        <div class="empty-state">No stock movements yet.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Item Code</th>
                        <th>Item Name</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Reference</th>
                        <th>User</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $row): ?>
                        <tr>
                            <td><?= e(format_date($row['movement_date'])) ?></td>
                            <td><?= e($row['item_code']) ?></td>
                            <td><?= e($row['item_name']) ?></td>
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

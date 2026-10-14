<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_role('admin');

$pdo = db();

$totalItems = (int) $pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();
$totalQty = (int) $pdo->query('SELECT COALESCE(SUM(quantity), 0) FROM items')->fetchColumn();
$lowStockCount = (int) $pdo->query(
    'SELECT COUNT(*) FROM items WHERE quantity <= reorder_level'
)->fetchColumn();

$todayInStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(quantity), 0)
     FROM stock_movements
     WHERE movement_type = 'IN' AND movement_date = CURDATE()"
);
$todayInStmt->execute();
$todayIn = (int) $todayInStmt->fetchColumn();

$todayOutStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(quantity), 0)
     FROM stock_movements
     WHERE movement_type = 'OUT' AND movement_date = CURDATE()"
);
$todayOutStmt->execute();
$todayOut = (int) $todayOutStmt->fetchColumn();

$lowStock = $pdo->query(
    'SELECT item_code, item_name, category, quantity, reorder_level, unit
     FROM items
     WHERE quantity <= reorder_level
     ORDER BY quantity ASC, item_name ASC
     LIMIT 50'
)->fetchAll();

$pageTitle = 'Admin Dashboard';
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
    <div class="stat-card">
        <div class="label">Today's Stock IN</div>
        <div class="value"><?= e((string) $todayIn) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Today's Stock OUT</div>
        <div class="value"><?= e((string) $todayOut) ?></div>
    </div>
</div>

<div class="card">
    <h2>Low Stock Items</h2>
    <?php if (!$lowStock): ?>
        <div class="empty-state">No low-stock items right now.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Item Code</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Current Qty</th>
                        <th>Reorder Level</th>
                        <th>Unit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lowStock as $item): ?>
                        <tr class="low-stock">
                            <td><?= e($item['item_code']) ?></td>
                            <td><?= e($item['item_name']) ?></td>
                            <td><?= e($item['category']) ?></td>
                            <td><span class="badge badge-low"><?= e((string) $item['quantity']) ?></span></td>
                            <td><?= e((string) $item['reorder_level']) ?></td>
                            <td><?= e($item['unit']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

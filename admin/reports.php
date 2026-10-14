<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_role('admin');

$pdo = db();

$items = $pdo->query(
    'SELECT id, item_code, item_name FROM items ORDER BY item_name ASC'
)->fetchAll();

$categories = $pdo->query(
    'SELECT DISTINCT category FROM items ORDER BY category ASC'
)->fetchAll(PDO::FETCH_COLUMN);

$filterItem = positive_int($_GET['item_id'] ?? null);
$filterCategory = trim_str($_GET['category'] ?? '');
$filterType = strtoupper(trim_str($_GET['movement_type'] ?? ''));
$startDate = trim_str($_GET['start_date'] ?? '');
$endDate = trim_str($_GET['end_date'] ?? '');
$run = isset($_GET['run']) || $startDate !== '' || $endDate !== '' || $filterItem !== null || $filterCategory !== '' || $filterType !== '';

if ($filterType !== '' && !validate_movement_type($filterType)) {
    $filterType = '';
}

$where = [];
$params = [];

if ($filterItem !== null) {
    $where[] = 'sm.item_id = :item_id';
    $params['item_id'] = $filterItem;
}
if ($filterCategory !== '') {
    $where[] = 'i.category = :category';
    $params['category'] = $filterCategory;
}
if ($filterType !== '') {
    $where[] = 'sm.movement_type = :movement_type';
    $params['movement_type'] = $filterType;
}
if ($startDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    $where[] = 'sm.movement_date >= :start_date';
    $params['start_date'] = $startDate;
}
if ($endDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $where[] = 'sm.movement_date <= :end_date';
    $params['end_date'] = $endDate;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$totalIn = 0;
$totalOut = 0;
$rows = [];

if ($run) {
    $summarySql = "SELECT
            COALESCE(SUM(CASE WHEN sm.movement_type = 'IN' THEN sm.quantity ELSE 0 END), 0) AS total_in,
            COALESCE(SUM(CASE WHEN sm.movement_type = 'OUT' THEN sm.quantity ELSE 0 END), 0) AS total_out
         FROM stock_movements sm
         INNER JOIN items i ON i.id = sm.item_id
         {$whereSql}";
    $summaryStmt = $pdo->prepare($summarySql);
    $summaryStmt->execute($params);
    $summary = $summaryStmt->fetch() ?: ['total_in' => 0, 'total_out' => 0];
    $totalIn = (int) $summary['total_in'];
    $totalOut = (int) $summary['total_out'];

    $detailSql = "SELECT sm.movement_date, sm.movement_type, sm.quantity, sm.reference_note,
                         i.item_code, i.item_name, i.category, u.full_name AS created_by_name
                  FROM stock_movements sm
                  INNER JOIN items i ON i.id = sm.item_id
                  LEFT JOIN users u ON u.id = sm.created_by
                  {$whereSql}
                  ORDER BY sm.movement_date DESC, sm.id DESC
                  LIMIT 1000";
    $detailStmt = $pdo->prepare($detailSql);
    $detailStmt->execute($params);
    $rows = $detailStmt->fetchAll();
}

$net = $totalIn - $totalOut;

$pageTitle = 'Reports';
require __DIR__ . '/../includes/header.php';
?>

<div class="card no-print">
    <h2>Report Filters</h2>
    <form method="get" action="<?= e(url('admin/reports.php')) ?>" class="toolbar">
        <input type="hidden" name="run" value="1">
        <div class="form-group">
            <label for="item_id">Item</label>
            <select id="item_id" name="item_id">
                <option value="">All items</option>
                <?php foreach ($items as $item): ?>
                    <option value="<?= e((string) $item['id']) ?>" <?= $filterItem === (int) $item['id'] ? 'selected' : '' ?>>
                        <?= e($item['item_code'] . ' — ' . $item['item_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="category">Category</label>
            <select id="category" name="category">
                <option value="">All categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= e((string) $cat) ?>" <?= $filterCategory === $cat ? 'selected' : '' ?>>
                        <?= e((string) $cat) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="movement_type">Movement Type</label>
            <select id="movement_type" name="movement_type">
                <option value="">All</option>
                <option value="IN" <?= $filterType === 'IN' ? 'selected' : '' ?>>IN</option>
                <option value="OUT" <?= $filterType === 'OUT' ? 'selected' : '' ?>>OUT</option>
            </select>
        </div>
        <div class="form-group">
            <label for="start_date">Start Date</label>
            <input type="date" id="start_date" name="start_date" value="<?= e($startDate) ?>">
        </div>
        <div class="form-group">
            <label for="end_date">End Date</label>
            <input type="date" id="end_date" name="end_date" value="<?= e($endDate) ?>">
        </div>
        <button type="submit" class="btn btn-primary">Generate</button>
        <a class="btn btn-secondary" href="<?= e(url('admin/reports.php')) ?>">Reset</a>
        <?php if ($run): ?>
            <button type="button" class="btn btn-secondary" onclick="window.print()">Print</button>
        <?php endif; ?>
    </form>
</div>

<?php if ($run): ?>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="label">Total Stock IN</div>
            <div class="value"><?= e((string) $totalIn) ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Total Stock OUT</div>
            <div class="value"><?= e((string) $totalOut) ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Net Movement</div>
            <div class="value"><?= e((string) $net) ?></div>
        </div>
    </div>

    <div class="card">
        <h2>Detailed Report</h2>
        <p class="muted">
            Generated: <?= e(date('Y-m-d H:i')) ?>
            <?php if ($startDate || $endDate): ?>
                | Period: <?= e($startDate !== '' ? $startDate : '…') ?> to <?= e($endDate !== '' ? $endDate : '…') ?>
            <?php endif; ?>
        </p>

        <?php if (!$rows): ?>
            <div class="empty-state">No movements match the selected filters.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Item Code</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>IN</th>
                            <th>OUT</th>
                            <th>Reference</th>
                            <th>User</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= e(format_date($row['movement_date'])) ?></td>
                                <td><?= e($row['item_code']) ?></td>
                                <td><?= e($row['item_name']) ?></td>
                                <td><?= e($row['category']) ?></td>
                                <td><?= $row['movement_type'] === 'IN' ? e((string) $row['quantity']) : '—' ?></td>
                                <td><?= $row['movement_type'] === 'OUT' ? e((string) $row['quantity']) : '—' ?></td>
                                <td><?= e($row['reference_note'] ?? '') ?></td>
                                <td><?= e($row['created_by_name'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="card">
        <div class="empty-state">Choose filters and click Generate to view the report.</div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>

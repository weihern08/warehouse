<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_role('admin');

$pdo = db();
$user = current_user();

$items = $pdo->query(
    'SELECT id, item_code, item_name, quantity, unit FROM items ORDER BY item_name ASC'
)->fetchAll();

if (is_post()) {
    require_csrf();

    $itemId = positive_int($_POST['item_id'] ?? null);
    $movementType = strtoupper(trim_str($_POST['movement_type'] ?? ''));
    $quantity = parse_positive_int($_POST['quantity'] ?? null);
    $movementDate = trim_str($_POST['movement_date'] ?? '');
    $reference = trim_str($_POST['reference_note'] ?? '');

    $errors = [];

    if ($itemId === null) {
        $errors[] = 'Please select an item.';
    }
    if (!validate_movement_type($movementType)) {
        $errors[] = 'Movement type must be IN or OUT.';
    }
    if ($quantity === null) {
        $errors[] = 'Quantity must be a whole number greater than 0.';
    }
    if ($movementDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $movementDate)) {
        $errors[] = 'Please provide a valid movement date.';
    } else {
        $dt = DateTime::createFromFormat('Y-m-d', $movementDate);
        if (!$dt || $dt->format('Y-m-d') !== $movementDate) {
            $errors[] = 'Please provide a valid movement date.';
        }
    }

    if ($errors) {
        flash('error', implode(' ', $errors));
        redirect('admin/movements.php');
    }

    try {
        $pdo->beginTransaction();

        $lock = $pdo->prepare(
            'SELECT id, quantity FROM items WHERE id = :id FOR UPDATE'
        );
        $lock->execute(['id' => $itemId]);
        $item = $lock->fetch();

        if (!$item) {
            $pdo->rollBack();
            flash('error', 'Item not found.');
            redirect('admin/movements.php');
        }

        $currentQty = (int) $item['quantity'];

        if ($movementType === 'OUT' && $quantity > $currentQty) {
            $pdo->rollBack();
            flash('error', 'Cannot remove more stock than available. Current stock: ' . $currentQty . '.');
            redirect('admin/movements.php?item_id=' . $itemId);
        }

        $newQty = $movementType === 'IN'
            ? $currentQty + $quantity
            : $currentQty - $quantity;

        $insert = $pdo->prepare(
            'INSERT INTO stock_movements (item_id, movement_type, quantity, movement_date, reference_note, created_by)
             VALUES (:item_id, :type, :qty, :mdate, :note, :user_id)'
        );
        $insert->execute([
            'item_id' => $itemId,
            'type'    => $movementType,
            'qty'     => $quantity,
            'mdate'   => $movementDate,
            'note'    => $reference !== '' ? $reference : null,
            'user_id' => $user['id'],
        ]);

        $update = $pdo->prepare(
            'UPDATE items SET quantity = :qty WHERE id = :id'
        );
        $update->execute([
            'qty' => $newQty,
            'id'  => $itemId,
        ]);

        $pdo->commit();
        flash('success', 'Stock movement recorded successfully. New quantity: ' . $newQty . '.');
        redirect('admin/movements.php?item_id=' . $itemId);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        log_error('Stock movement failed', $e);
        flash('error', friendly_error());
        redirect('admin/movements.php');
    }
}

$filterItem = positive_int($_GET['item_id'] ?? null);
$filterType = strtoupper(trim_str($_GET['movement_type'] ?? ''));
$startDate = trim_str($_GET['start_date'] ?? '');
$endDate = trim_str($_GET['end_date'] ?? '');
$page = positive_int($_GET['page'] ?? 1) ?? 1;

if ($filterType !== '' && !validate_movement_type($filterType)) {
    $filterType = '';
}

$where = [];
$params = [];

if ($filterItem !== null) {
    $where[] = 'sm.item_id = :item_id';
    $params['item_id'] = $filterItem;
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

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM stock_movements sm {$whereSql}");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pager = paginate($total, $page, 25);

$listSql = "SELECT sm.*, i.item_code, i.item_name, u.full_name AS created_by_name
            FROM stock_movements sm
            INNER JOIN items i ON i.id = sm.item_id
            LEFT JOIN users u ON u.id = sm.created_by
            {$whereSql}
            ORDER BY sm.movement_date DESC, sm.id DESC
            LIMIT :limit OFFSET :offset";
$listStmt = $pdo->prepare($listSql);
foreach ($params as $key => $value) {
    $listStmt->bindValue(':' . $key, $value);
}
$listStmt->bindValue(':limit', $pager['per_page'], PDO::PARAM_INT);
$listStmt->bindValue(':offset', $pager['offset'], PDO::PARAM_INT);
$listStmt->execute();
$movements = $listStmt->fetchAll();

$pageTitle = 'Stock Movements';
require __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <h2>Record Stock Movement</h2>
    <form method="post" action="<?= e(url('admin/movements.php')) ?>" class="form">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="form-group">
                <label for="item_id">Item</label>
                <select id="item_id" name="item_id" required>
                    <option value="">Select item</option>
                    <?php foreach ($items as $item): ?>
                        <option value="<?= e((string) $item['id']) ?>" <?= $filterItem === (int) $item['id'] ? 'selected' : '' ?>>
                            <?= e($item['item_code'] . ' — ' . $item['item_name'] . ' (Stock: ' . $item['quantity'] . ' ' . $item['unit'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="movement_type">Movement Type</label>
                <select id="movement_type" name="movement_type" required>
                    <option value="IN">IN</option>
                    <option value="OUT">OUT</option>
                </select>
            </div>
            <div class="form-group">
                <label for="quantity">Quantity</label>
                <input type="number" id="quantity" name="quantity" min="1" step="1" required>
            </div>
            <div class="form-group">
                <label for="movement_date">Date</label>
                <input type="date" id="movement_date" name="movement_date" required value="<?= e(date('Y-m-d')) ?>">
            </div>
            <div class="form-group" style="grid-column: 1 / -1;">
                <label for="reference_note">Reference / Note</label>
                <input type="text" id="reference_note" name="reference_note" maxlength="255" placeholder="Optional">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Movement</button>
        </div>
    </form>
</div>

<div class="card">
    <h2>Stock History</h2>
    <form method="get" action="<?= e(url('admin/movements.php')) ?>" class="toolbar">
        <div class="form-group">
            <label for="filter_item">Item</label>
            <select id="filter_item" name="item_id">
                <option value="">All items</option>
                <?php foreach ($items as $item): ?>
                    <option value="<?= e((string) $item['id']) ?>" <?= $filterItem === (int) $item['id'] ? 'selected' : '' ?>>
                        <?= e($item['item_code'] . ' — ' . $item['item_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="filter_type">Type</label>
            <select id="filter_type" name="movement_type">
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
        <button type="submit" class="btn btn-secondary">Filter</button>
        <a class="btn btn-secondary" href="<?= e(url('admin/movements.php')) ?>">Reset</a>
    </form>

    <?php if (!$movements): ?>
        <div class="empty-state">No stock movements found.</div>
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
                    <?php foreach ($movements as $row): ?>
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

        <?php if ($pager['total_pages'] > 1): ?>
            <div class="pagination">
                <?php for ($p = 1; $p <= $pager['total_pages']; $p++): ?>
                    <?php
                    $query = http_build_query([
                        'item_id'       => $filterItem,
                        'movement_type' => $filterType,
                        'start_date'    => $startDate,
                        'end_date'      => $endDate,
                        'page'          => $p,
                    ]);
                    ?>
                    <?php if ($p === $pager['page']): ?>
                        <span class="current"><?= e((string) $p) ?></span>
                    <?php else: ?>
                        <a href="<?= e(url('admin/movements.php?' . $query)) ?>"><?= e((string) $p) ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

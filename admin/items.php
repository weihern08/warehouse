<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_role('admin');

$pdo = db();
$action = trim_str($_GET['action'] ?? 'list');
$id = positive_int($_GET['id'] ?? null);

function fetch_categories(PDO $pdo): array
{
    return $pdo->query(
        'SELECT DISTINCT category FROM items ORDER BY category ASC'
    )->fetchAll(PDO::FETCH_COLUMN);
}

function fetch_item(PDO $pdo, int $itemId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM items WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $itemId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function item_code_exists(PDO $pdo, string $code, ?int $excludeId = null): bool
{
    if ($excludeId !== null) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM items WHERE item_code = :code AND id <> :id'
        );
        $stmt->execute(['code' => $code, 'id' => $excludeId]);
    } else {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM items WHERE item_code = :code');
        $stmt->execute(['code' => $code]);
    }

    return (int) $stmt->fetchColumn() > 0;
}

// Handle POST actions
if (is_post()) {
    require_csrf();
    $postAction = trim_str($_POST['action'] ?? '');

    try {
        if ($postAction === 'create' || $postAction === 'update') {
            $itemCode = trim_str($_POST['item_code'] ?? '');
            $itemName = trim_str($_POST['item_name'] ?? '');
            $category = trim_str($_POST['category'] ?? '');
            $location = trim_str($_POST['location'] ?? '');
            $unit = trim_str($_POST['unit'] ?? '');
            $quantity = parse_non_negative_int($_POST['quantity'] ?? null);
            $reorder = parse_non_negative_int($_POST['reorder_level'] ?? null);
            $editId = positive_int($_POST['id'] ?? null);

            $errors = [];
            if ($itemCode === '') {
                $errors[] = 'Item code is required.';
            }
            if ($itemName === '') {
                $errors[] = 'Item name is required.';
            }
            if ($category === '') {
                $errors[] = 'Category is required.';
            }
            if ($location === '') {
                $errors[] = 'Location is required.';
            }
            if ($unit === '') {
                $errors[] = 'Unit is required.';
            }
            if ($quantity === null) {
                $errors[] = 'Quantity must be a whole number of 0 or more.';
            }
            if ($reorder === null) {
                $errors[] = 'Reorder level must be a whole number of 0 or more.';
            }

            if ($postAction === 'create') {
                if ($itemCode !== '' && item_code_exists($pdo, $itemCode)) {
                    $errors[] = 'Item code already exists.';
                }

                if ($errors) {
                    flash('error', implode(' ', $errors));
                    redirect('admin/items.php?action=add');
                }

                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    'INSERT INTO items (item_code, item_name, category, location, quantity, unit, reorder_level)
                     VALUES (:item_code, :item_name, :category, :location, :quantity, :unit, :reorder_level)'
                );
                $stmt->execute([
                    'item_code'     => $itemCode,
                    'item_name'     => $itemName,
                    'category'      => $category,
                    'location'      => $location,
                    'quantity'      => $quantity,
                    'unit'          => $unit,
                    'reorder_level' => $reorder,
                ]);

                $newId = (int) $pdo->lastInsertId();

                // Opening balance as a stock movement when quantity > 0
                if ($quantity > 0) {
                    $user = current_user();
                    $mv = $pdo->prepare(
                        'INSERT INTO stock_movements (item_id, movement_type, quantity, movement_date, reference_note, created_by)
                         VALUES (:item_id, :type, :qty, CURDATE(), :note, :user_id)'
                    );
                    $mv->execute([
                        'item_id' => $newId,
                        'type'    => 'IN',
                        'qty'     => $quantity,
                        'note'    => 'Opening balance',
                        'user_id' => $user['id'],
                    ]);
                }

                $pdo->commit();
                flash('success', 'Item created successfully.');
                redirect('admin/items.php?action=view&id=' . $newId);
            }

            // Update — do not change quantity directly; keep inventory history intact
            if ($editId === null) {
                flash('error', 'Invalid item.');
                redirect('admin/items.php');
            }

            $existing = fetch_item($pdo, $editId);
            if ($existing === null) {
                flash('error', 'Item not found.');
                redirect('admin/items.php');
            }

            if (item_code_exists($pdo, $itemCode, $editId)) {
                $errors[] = 'Item code already exists.';
            }

            if ($errors) {
                flash('error', implode(' ', $errors));
                redirect('admin/items.php?action=edit&id=' . $editId);
            }

            $stmt = $pdo->prepare(
                'UPDATE items
                 SET item_code = :item_code,
                     item_name = :item_name,
                     category = :category,
                     location = :location,
                     unit = :unit,
                     reorder_level = :reorder_level
                 WHERE id = :id'
            );
            $stmt->execute([
                'item_code'     => $itemCode,
                'item_name'     => $itemName,
                'category'      => $category,
                'location'      => $location,
                'unit'          => $unit,
                'reorder_level' => $reorder,
                'id'            => $editId,
            ]);

            flash('success', 'Item updated successfully. Quantity changes must be recorded via Stock Movements.');
            redirect('admin/items.php?action=view&id=' . $editId);
        }

        if ($postAction === 'delete') {
            $deleteId = positive_int($_POST['id'] ?? null);
            if ($deleteId === null) {
                flash('error', 'Invalid item.');
                redirect('admin/items.php');
            }

            $countStmt = $pdo->prepare(
                'SELECT COUNT(*) FROM stock_movements WHERE item_id = :id'
            );
            $countStmt->execute(['id' => $deleteId]);
            $movementCount = (int) $countStmt->fetchColumn();

            if ($movementCount > 0) {
                flash('error', 'Cannot delete this item because it has stock movement history.');
                redirect('admin/items.php?action=view&id=' . $deleteId);
            }

            $del = $pdo->prepare('DELETE FROM items WHERE id = :id');
            $del->execute(['id' => $deleteId]);

            if ($del->rowCount() === 0) {
                flash('error', 'Item not found.');
            } else {
                flash('success', 'Item deleted successfully.');
            }
            redirect('admin/items.php');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        log_error('Admin items POST failed', $e);
        flash('error', friendly_error());
        redirect('admin/items.php');
    }
}

$categories = fetch_categories($pdo);

// View single item
if ($action === 'view' && $id !== null) {
    $item = fetch_item($pdo, $id);
    if ($item === null) {
        flash('error', 'Item not found.');
        redirect('admin/items.php');
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
    <div class="toolbar no-print">
        <a class="btn btn-secondary" href="<?= e(url('admin/items.php')) ?>">Back to Items</a>
        <a class="btn btn-primary" href="<?= e(url('admin/items.php?action=edit&id=' . $id)) ?>">Edit</a>
        <a class="btn btn-primary" href="<?= e(url('admin/movements.php?item_id=' . $id)) ?>">Record Movement</a>
        <form method="post" action="<?= e(url('admin/items.php')) ?>" style="display:inline;" onsubmit="return confirm('Delete this item?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= e((string) $id) ?>">
            <button type="submit" class="btn btn-danger">Delete</button>
        </form>
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
        <h3>Recent Stock History</h3>
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
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

// Add / Edit forms
if ($action === 'add' || ($action === 'edit' && $id !== null)) {
    $item = [
        'item_code'     => '',
        'item_name'     => '',
        'category'      => '',
        'location'      => '',
        'quantity'      => '0',
        'unit'          => '',
        'reorder_level' => '0',
    ];

    if ($action === 'edit') {
        $found = fetch_item($pdo, $id);
        if ($found === null) {
            flash('error', 'Item not found.');
            redirect('admin/items.php');
        }
        $item = $found;
    }

    $pageTitle = $action === 'add' ? 'Add Item' : 'Edit Item';
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="card">
        <h2><?= e($pageTitle) ?></h2>
        <form method="post" action="<?= e(url('admin/items.php')) ?>" class="form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $action === 'add' ? 'create' : 'update' ?>">
            <?php if ($action === 'edit'): ?>
                <input type="hidden" name="id" value="<?= e((string) $id) ?>">
            <?php endif; ?>

            <div class="form-grid">
                <div class="form-group">
                    <label for="item_code">Item Code</label>
                    <input type="text" id="item_code" name="item_code" required maxlength="50"
                           value="<?= e((string) $item['item_code']) ?>">
                </div>
                <div class="form-group">
                    <label for="item_name">Item Name</label>
                    <input type="text" id="item_name" name="item_name" required maxlength="150"
                           value="<?= e((string) $item['item_name']) ?>">
                </div>
                <div class="form-group">
                    <label for="category">Category</label>
                    <input type="text" id="category" name="category" required maxlength="100" list="category_list"
                           value="<?= e((string) $item['category']) ?>">
                    <datalist id="category_list">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= e((string) $cat) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="form-group">
                    <label for="location">Location</label>
                    <input type="text" id="location" name="location" required maxlength="100"
                           value="<?= e((string) $item['location']) ?>">
                </div>
                <div class="form-group">
                    <label for="unit">Unit</label>
                    <input type="text" id="unit" name="unit" required maxlength="30"
                           value="<?= e((string) $item['unit']) ?>">
                </div>
                <?php if ($action === 'add'): ?>
                    <div class="form-group">
                        <label for="quantity">Opening Quantity</label>
                        <input type="number" id="quantity" name="quantity" min="0" step="1" required
                               value="<?= e((string) $item['quantity']) ?>">
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <label>Current Quantity</label>
                        <input type="text" value="<?= e((string) $item['quantity']) ?>" disabled>
                        <input type="hidden" name="quantity" value="<?= e((string) $item['quantity']) ?>">
                        <small class="muted">Change stock via Stock Movements to keep history accurate.</small>
                    </div>
                <?php endif; ?>
                <div class="form-group">
                    <label for="reorder_level">Reorder Level</label>
                    <input type="number" id="reorder_level" name="reorder_level" min="0" step="1" required
                           value="<?= e((string) $item['reorder_level']) ?>">
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save</button>
                <a class="btn btn-secondary" href="<?= e(url('admin/items.php')) ?>">Cancel</a>
            </div>
        </form>
    </div>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

// List / search / filter
$q = trim_str($_GET['q'] ?? '');
$category = trim_str($_GET['category'] ?? '');
$page = positive_int($_GET['page'] ?? 1) ?? 1;
$perPage = 20;

$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(item_code LIKE :q1 OR item_name LIKE :q2 OR location LIKE :q3)';
    $like = '%' . $q . '%';
    $params['q1'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
}
if ($category !== '') {
    $where[] = 'category = :category';
    $params['category'] = $category;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM items {$whereSql}");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pager = paginate($total, $page, $perPage);

$listStmt = $pdo->prepare(
    "SELECT * FROM items {$whereSql}
     ORDER BY item_name ASC
     LIMIT :limit OFFSET :offset"
);
foreach ($params as $key => $value) {
    $listStmt->bindValue(':' . $key, $value);
}
$listStmt->bindValue(':limit', $pager['per_page'], PDO::PARAM_INT);
$listStmt->bindValue(':offset', $pager['offset'], PDO::PARAM_INT);
$listStmt->execute();
$items = $listStmt->fetchAll();

$pageTitle = 'Items';
require __DIR__ . '/../includes/header.php';
?>

<div class="toolbar">
    <a class="btn btn-primary" href="<?= e(url('admin/items.php?action=add')) ?>">Add Item</a>
    <form method="get" action="<?= e(url('admin/items.php')) ?>" class="toolbar" style="margin:0;flex:1;">
        <div class="form-group">
            <label for="q">Search</label>
            <input type="text" id="q" name="q" value="<?= e($q) ?>" placeholder="Code, name, location">
        </div>
        <div class="form-group">
            <label for="category">Category</label>
            <select id="category" name="category">
                <option value="">All categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= e((string) $cat) ?>" <?= $category === $cat ? 'selected' : '' ?>>
                        <?= e((string) $cat) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <a class="btn btn-secondary" href="<?= e(url('admin/items.php')) ?>">Reset</a>
    </form>
</div>

<div class="card">
    <?php if (!$items): ?>
        <div class="empty-state">No items found.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th>Qty</th>
                        <th>Reorder</th>
                        <th>Unit</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $row): ?>
                        <?php $isLow = (int) $row['quantity'] <= (int) $row['reorder_level']; ?>
                        <tr class="<?= $isLow ? 'low-stock' : '' ?>">
                            <td><?= e($row['item_code']) ?></td>
                            <td><?= e($row['item_name']) ?></td>
                            <td><?= e($row['category']) ?></td>
                            <td><?= e($row['location']) ?></td>
                            <td>
                                <?= e((string) $row['quantity']) ?>
                                <?php if ($isLow): ?><span class="badge badge-low">Low</span><?php endif; ?>
                            </td>
                            <td><?= e((string) $row['reorder_level']) ?></td>
                            <td><?= e($row['unit']) ?></td>
                            <td>
                                <a class="btn btn-sm btn-secondary" href="<?= e(url('admin/items.php?action=view&id=' . $row['id'])) ?>">View</a>
                                <a class="btn btn-sm btn-primary" href="<?= e(url('admin/items.php?action=edit&id=' . $row['id'])) ?>">Edit</a>
                            </td>
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
                        'q'        => $q,
                        'category' => $category,
                        'page'     => $p,
                    ]);
                    ?>
                    <?php if ($p === $pager['page']): ?>
                        <span class="current"><?= e((string) $p) ?></span>
                    <?php else: ?>
                        <a href="<?= e(url('admin/items.php?' . $query)) ?>"><?= e((string) $p) ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_role('user');

$pdo = db();

$q = trim_str($_GET['q'] ?? '');
$category = trim_str($_GET['category'] ?? '');
$page = positive_int($_GET['page'] ?? 1) ?? 1;

$categories = $pdo->query(
    'SELECT DISTINCT category FROM items ORDER BY category ASC'
)->fetchAll(PDO::FETCH_COLUMN);

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
$pager = paginate($total, $page, 20);

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
    <form method="get" action="<?= e(url('user/items.php')) ?>" class="toolbar" style="margin:0;flex:1;">
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
        <a class="btn btn-secondary" href="<?= e(url('user/items.php')) ?>">Reset</a>
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
                        <th></th>
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
                                <a class="btn btn-sm btn-secondary" href="<?= e(url('user/item-details.php?id=' . $row['id'])) ?>">Details</a>
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
                        <a href="<?= e(url('user/items.php?' . $query)) ?>"><?= e((string) $p) ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

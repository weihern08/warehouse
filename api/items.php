<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

header('Content-Type: application/json; charset=utf-8');

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'Authentication required.', 'data' => []], 401);
}

$q = trim_str($_GET['q'] ?? '');
$limit = parse_positive_int($_GET['limit'] ?? 20) ?? 20;
$limit = min($limit, 50);

try {
    $pdo = db();

    if ($q === '') {
        $stmt = $pdo->prepare(
            'SELECT id, item_code, item_name, category, location, quantity, unit, reorder_level
             FROM items
             ORDER BY item_name ASC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, item_code, item_name, category, location, quantity, unit, reorder_level
             FROM items
             WHERE item_code LIKE :q1 OR item_name LIKE :q2 OR location LIKE :q3
             ORDER BY item_name ASC
             LIMIT :limit'
        );
        $like = '%' . $q . '%';
        $stmt->bindValue(':q1', $like);
        $stmt->bindValue(':q2', $like);
        $stmt->bindValue(':q3', $like);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
    }

    $data = $stmt->fetchAll();

    json_response([
        'success' => true,
        'data'    => $data,
    ]);
} catch (Throwable $e) {
    log_error('API items failed', $e);
    json_response([
        'success' => false,
        'message' => friendly_error(),
        'data'    => [],
    ], 500);
}

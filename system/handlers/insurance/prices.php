<?php
// handlers/insurance/prices.php — paginated insurance price grid (procedures + items) for insurance.php?sub=insurance-prices
require_once __DIR__ . '/../../includes/database.php';

function jr($d, $s = 200) { http_response_code($s); echo json_encode($d); exit(); }
$method = $_SERVER['REQUEST_METHOD'];
$in = json_decode(file_get_contents('php://input'), true) ?? [];
$src = $method === 'GET' ? $_GET : $in;
$type = ($src['type'] ?? 'procedures') === 'items' ? 'items' : 'procedures';

try {
    if ($method === 'POST') {
        $rowId = (int)($in['row_id'] ?? 0); $insId = (int)($in['insurance_id'] ?? 0); $price = (float)($in['price'] ?? 0);
        if (!$rowId || !$insId || $price < 0) jr(['success' => false, 'message' => 'row_id, insurance_id and a price >= 0 are required'], 422);
        $chk = $pdo->prepare("SELECT COUNT(*) FROM insurance_companies WHERE id=? AND status='active'");
        $chk->execute([$insId]);
        if (!$chk->fetchColumn()) jr(['success' => false, 'message' => 'Unknown or inactive insurance'], 422);
        if ($type === 'items') {
            $pdo->prepare("INSERT INTO item_insurance_prices (item_id,insurance_id,price,insurance_pays) VALUES (?,?,?,?)
                           ON DUPLICATE KEY UPDATE price=VALUES(price), insurance_pays=VALUES(insurance_pays)")->execute([$rowId, $insId, $price, $price]);
        } else {
            $pdo->prepare("INSERT INTO insurance_procedure_prices (service_id,insurance_id,price) VALUES (?,?,?)
                           ON DUPLICATE KEY UPDATE price=VALUES(price)")->execute([$rowId, $insId, $price]);
        }
        jr(['success' => true, 'message' => 'Price saved']);
    }
    if ($method !== 'GET') jr(['success' => false, 'message' => 'Method not allowed'], 405);

    $group = in_array($_GET['group'] ?? '', ['private', 'public'], true) ? $_GET['group'] : '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per = min(200, max(10, (int)($_GET['per_page'] ?? 50)));
    $search = trim($_GET['search'] ?? '');
    $like = '%' . addcslashes($search, '%_\\') . '%';

    // insurer columns, filtered by Private/Public
    $cw = "status='active'"; $cp = [];
    if ($group) { $cw .= " AND tariff_group=?"; $cp[] = $group; }
    $st = $pdo->prepare("SELECT id,name,code,tariff_group FROM insurance_companies WHERE $cw ORDER BY tariff_group DESC, name");
    $st->execute($cp);
    $companies = $st->fetchAll();
    $insIds = array_map('intval', array_column($companies, 'id'));

    // rows
    $w = []; $p = [];
    if ($type === 'items') {
        $w[] = "i.is_active=1";
        if ($search !== '') { $w[] = "(i.name LIKE ? OR i.item_code LIKE ?)"; array_push($p, $like, $like); }
        $cat = $_GET['category'] ?? '';
        if ($cat === 'pharmacy') $w[] = "(i.is_medication=1 OR i.category_id=1)";
        elseif ((int)$cat > 0) { $w[] = "i.category_id=?"; $p[] = (int)$cat; }
        $from = "FROM items i WHERE " . implode(' AND ', $w);
        $sel = "SELECT i.id, i.item_code AS code, i.name, c.name AS group_name, i.selling_price AS std_price
                FROM (SELECT i.* $from ORDER BY i.name LIMIT ? OFFSET ?) i LEFT JOIN item_categories c ON c.id=i.category_id ORDER BY i.name";
        $table = 'item_insurance_prices'; $key = 'item_id';
    } else {
        $w[] = "s.is_active=1";
        if ($search !== '') { $w[] = "(s.procedure_name LIKE ? OR s.ichi_code LIKE ?)"; array_push($p, $like, $like); }
        if (($_GET['speciality'] ?? '') !== '') { $w[] = "s.speciality=?"; $p[] = $_GET['speciality']; }
        $from = "FROM service_catalog s WHERE " . implode(' AND ', $w);
        $sel = "SELECT s.id, s.ichi_code AS code, s.procedure_name AS name, s.speciality AS group_name, s.primary_health_facility AS std_price
                FROM (SELECT s.* $from ORDER BY s.procedure_name LIMIT ? OFFSET ?) s ORDER BY s.procedure_name";
        $table = 'insurance_procedure_prices'; $key = 'service_id';
    }
    $cnt = $pdo->prepare("SELECT COUNT(*) $from"); $cnt->execute($p);
    $total = (int)$cnt->fetchColumn();
    $pages = max(1, (int)ceil($total / $per));
    $page = min($page, $pages);
    $q = $pdo->prepare($sel);
    $q->execute(array_merge($p, [$per, ($page - 1) * $per]));
    $rows = $q->fetchAll();

    // prices for this page only
    $prices = [];
    if ($rows && $insIds) {
        $rid = array_map('intval', array_column($rows, 'id'));
        $ph = implode(',', array_fill(0, count($rid), '?')); $ih = implode(',', array_fill(0, count($insIds), '?'));
        $pq = $pdo->prepare("SELECT $key AS rid, insurance_id, price FROM $table WHERE $key IN ($ph) AND insurance_id IN ($ih)");
        $pq->execute(array_merge($rid, $insIds));
        foreach ($pq->fetchAll() as $r) $prices[$r['rid']][$r['insurance_id']] = (float)$r['price'];
    }

    $filters = $type === 'items'
        ? $pdo->query("SELECT c.id, c.name FROM item_categories c WHERE c.is_active=1 AND c.name<>'' AND EXISTS (SELECT 1 FROM items i WHERE i.category_id=c.id AND i.is_active=1) ORDER BY c.name")->fetchAll()
        : array_column($pdo->query("SELECT DISTINCT speciality FROM service_catalog WHERE is_active=1 AND speciality<>'' ORDER BY speciality")->fetchAll(), 'speciality');

    jr(['success' => true, 'data' => ['prices' => (object)$prices] + compact('companies', 'rows', 'total', 'page', 'pages', 'filters') + ['per_page' => $per]]);
} catch (Throwable $e) {
    jr(['success' => false, 'message' => 'Server error'], 500);
}

<?php
// Inventory workspace API (ws_* actions), included by inventory-management.php after login + inv_guard.
// Stores / departments, stock lists, void + edit with reason, department consumption, and the department / person / item report.
// Expects: $pdo, $method, $action, $rawInput, success(), error().

$wsUid = (int)($_SESSION['user_id'] ?? 0);
$wsRoles = $_SESSION['user_data']['role_slugs'] ?? [];
$wsStoreRole = (bool)array_intersect($wsRoles, ['admin', 'inventory_manager', 'storekeeper', 'storekeper', 'manager']);
// Inventory-side readers (accountant, procurement, pharmacist, MD...) see every store; everyone else only their own department.
require_once __DIR__ . '/../../includes/auth.php';   // canUsePage
$wsViewAll = $wsStoreRole || canUsePage($pdo, 'inventory.php');
$wsDept = 0;
if (!$wsViewAll) { $q = $pdo->prepare('SELECT department_id FROM users WHERE id = ?'); $q->execute([$wsUid]); $wsDept = (int)$q->fetchColumn(); }

// The department's own stock location (stores.type='department', code DEPT-<id>). Created on first use. 0 = no such department.
if (!function_exists('dept_store_id')) {
function dept_store_id(PDO $pdo, int $deptId): int {
    if ($deptId <= 0) return 0;
    $q = $pdo->prepare('SELECT id FROM stores WHERE code = ?');
    $q->execute(['DEPT-' . $deptId]);
    if ($id = (int)$q->fetchColumn()) return $id;
    $n = $pdo->prepare('SELECT name FROM departments WHERE id = ?');
    $n->execute([$deptId]);
    $name = $n->fetchColumn();
    if ($name === false) return 0;
    $pdo->prepare("INSERT INTO stores (name, code, type, location) VALUES (?,?, 'department', ?)")->execute([$name . ' Store', 'DEPT-' . $deptId, $name]);
    return (int)$pdo->lastInsertId();
}
}

// medical = flagged medication or one of the clinical categories; everything else (pens, paper, cleaning...) is non-medical
const WS_KIND = "CASE WHEN i.is_medication = 1 OR ic.name IN ('Medications','Medical','Laboratory','Nursing') THEN 'medical' ELSE 'non_medical' END";

$pdo->exec("CREATE TABLE IF NOT EXISTS dept_consumption (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
    patient_id INT NULL,
    used_by INT NOT NULL,
    note VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_dc_dept (department_id), KEY idx_dc_item (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function ws_date(string $k, string $def): string {
    $v = $_GET[$k] ?? $def;
    $d = DateTime::createFromFormat('Y-m-d', (string)$v);
    if (!$d || $d->format('Y-m-d') !== $v) error("Invalid $k, expected YYYY-MM-DD", 422);
    return $v;
}
function ws_need_store_role(bool $ok): void { if (!$ok) jsonResponse(['success' => false, 'message' => 'Only inventory / store staff can do this.'], 403); }

// ---------- GET ----------
if ($method === 'GET') {
    // Left-hand switcher: shared stores + every department (with its own store's totals, 0 when it has none yet)
    if ($action === 'ws_stores') {
        $stores = $wsViewAll ? $pdo->query("SELECT s.id, s.name, s.code, s.type, COALESCE(SUM(b.quantity),0) qty, COALESCE(SUM(b.quantity*b.unit_cost),0) value
            FROM stores s LEFT JOIN stock_batches b ON b.store_id = s.id AND b.is_active = 1 AND b.quantity > 0
            WHERE s.is_active = 1 AND s.type <> 'department' GROUP BY s.id ORDER BY s.name")->fetchAll() : [];
        $depts = $pdo->query("SELECT d.id, d.name, s.id store_id, COALESCE(SUM(b.quantity),0) qty, COALESCE(SUM(b.quantity*b.unit_cost),0) value
            FROM departments d LEFT JOIN stores s ON s.code = CONCAT('DEPT-', d.id)
            LEFT JOIN stock_batches b ON b.store_id = s.id AND b.is_active = 1 AND b.quantity > 0
            WHERE d.is_active = 1" . ($wsViewAll ? '' : ' AND d.id = ' . $wsDept) . " GROUP BY d.id ORDER BY (COALESCE(SUM(b.quantity),0) > 0) DESC, d.name")->fetchAll();
        success(['stores' => $stores, 'departments' => $depts, 'can_manage' => $wsStoreRole]);
    }

    // One list for every store: search + filters. A department with no store yet simply returns no rows.
    if ($action === 'ws_stock') {
        $w = ['b.is_active = 1', 'b.quantity > 0'];
        $p = [];
        if (!$wsViewAll) { $_GET['department_id'] = $wsDept ?: -1; unset($_GET['store_id']); }
        if (!empty($_GET['department_id'])) { $w[] = 's.code = ?'; $p[] = 'DEPT-' . (int)$_GET['department_id']; }
        elseif (!empty($_GET['store_id'])) { $w[] = 'b.store_id = ?'; $p[] = (int)$_GET['store_id']; }
        else $w[] = "s.type <> 'department'";
        $q = trim((string)($_GET['q'] ?? ''));
        if ($q !== '') { $w[] = '(i.name LIKE ? OR i.item_code LIKE ? OR i.generic_name LIKE ? OR b.batch_number LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%", "%$q%"); }
        if (($_GET['kind'] ?? '') === 'medical') $w[] = WS_KIND . " = 'medical'";
        if (($_GET['kind'] ?? '') === 'non_medical') $w[] = WS_KIND . " = 'non_medical'";
        if (!empty($_GET['category_id'])) { $w[] = 'i.category_id = ?'; $p[] = (int)$_GET['category_id']; }
        $flag = $_GET['flag'] ?? '';
        if ($flag === 'low') $w[] = 'b.reorder_level > 0 AND b.quantity <= b.reorder_level';
        if ($flag === 'expiring') $w[] = 'b.expiry_date IS NOT NULL AND b.expiry_date >= CURDATE() AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)';
        if ($flag === 'expired') $w[] = 'b.expiry_date IS NOT NULL AND b.expiry_date < CURDATE()';
        $st = $pdo->prepare("SELECT b.id batch_id, i.id item_id, i.item_code, i.name item_name, ic.name category, " . WS_KIND . " kind, s.name store_name, s.id store_id,
                b.batch_number, b.expiry_date, DATEDIFF(b.expiry_date, CURDATE()) days_left, b.quantity, b.unit_cost, b.quantity*b.unit_cost value, b.reorder_level, b.location_rack
            FROM stock_batches b JOIN items i ON i.id = b.item_id JOIN stores s ON s.id = b.store_id LEFT JOIN item_categories ic ON ic.id = i.category_id
            WHERE " . implode(' AND ', $w) . " ORDER BY i.name, (b.expiry_date IS NULL), b.expiry_date LIMIT 500");
        $st->execute($p);
        $rows = $st->fetchAll();
        success(['rows' => $rows, 'qty' => array_sum(array_column($rows, 'quantity')), 'value' => array_sum(array_column($rows, 'value')), 'truncated' => count($rows) >= 500]);
    }

    // Which store holds an item (global search box)
    if ($action === 'ws_where') {
        $q = trim((string)($_GET['q'] ?? ''));
        if (strlen($q) < 2 || (!$wsViewAll && !$wsDept)) success([]);
        $st = $pdo->prepare("SELECT i.id item_id, i.name item_name, s.name store_name, SUM(b.quantity) qty FROM stock_batches b JOIN items i ON i.id=b.item_id JOIN stores s ON s.id=b.store_id
            WHERE b.is_active=1 AND b.quantity>0 " . ($wsViewAll ? '' : "AND s.code = 'DEPT-$wsDept' ") . "AND (i.name LIKE ? OR i.item_code LIKE ? OR i.generic_name LIKE ?) GROUP BY i.id, s.id ORDER BY i.name, qty DESC LIMIT 60");
        $st->execute(["%$q%", "%$q%", "%$q%"]);
        success($st->fetchAll());
    }

    // Department / person / item report. Amount = cost (qty x batch unit cost). Paid vs unpaid only exists for patient prescriptions.
    if ($action === 'ws_report') {
        $from = ws_date('from', date('Y-m-01')); $to = ws_date('to', date('Y-m-d'));
        $w = ["sm.reference_type IN ('request_management','dept_use','prescription')", "sm.movement_type IN ('transfer_out','dispensing')", 'DATE(sm.created_at) BETWEEN ? AND ?'];
        $p = [$from, $to];
        $dept = "COALESCE(rm.department_id, dc.department_id, u.department_id)";   // prescriptions: the doctor's department
        $who = "COALESCE(rm.requested_by, dc.used_by, rx.doctor_id)";
        if (!$wsViewAll) $_GET['department_id'] = $wsDept ?: -1;
        if (!empty($_GET['department_id'])) { $w[] = "$dept = ?"; $p[] = (int)$_GET['department_id']; }
        if (!empty($_GET['person_id'])) { $w[] = "$who = ?"; $p[] = (int)$_GET['person_id']; }
        if (!empty($_GET['item_id'])) { $w[] = 'sm.item_id = ?'; $p[] = (int)$_GET['item_id']; }
        if (($_GET['kind'] ?? '') === 'medical') $w[] = WS_KIND . " = 'medical'";
        if (($_GET['kind'] ?? '') === 'non_medical') $w[] = WS_KIND . " = 'non_medical'";
        $paidExpr = "CASE WHEN sm.reference_type <> 'prescription' THEN 'internal'
            WHEN EXISTS (SELECT 1 FROM invoice_items ii JOIN invoices inv ON inv.id = ii.invoice_id WHERE ii.item_id = sm.item_id AND inv.visit_id = rx.visit_id AND inv.status = 'paid') THEN 'paid' ELSE 'unpaid' END";
        $st = $pdo->prepare("SELECT sm.id, sm.created_at, i.id item_id, i.name item_name, " . WS_KIND . " kind,
                CASE sm.reference_type WHEN 'request_management' THEN 'Requested' WHEN 'dept_use' THEN 'Used' ELSE 'Prescribed / dispensed' END event,
                $dept department_id, dp.name department_name, $who person_id, TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))) person_name,
                sm.quantity qty, COALESCE(sm.unit_cost,0) unit_cost, sm.quantity*COALESCE(sm.unit_cost,0) amount, $paidExpr paid_state
            FROM stock_movements sm JOIN items i ON i.id = sm.item_id LEFT JOIN item_categories ic ON ic.id = i.category_id
            LEFT JOIN request_management rm ON sm.reference_type = 'request_management' AND rm.id = sm.reference_id
            LEFT JOIN dept_consumption dc ON sm.reference_type = 'dept_use' AND dc.id = sm.reference_id
            LEFT JOIN pharmacy_prescriptions rx ON sm.reference_type = 'prescription' AND rx.id = sm.reference_id
            LEFT JOIN users u ON u.id = $who LEFT JOIN departments dp ON dp.id = $dept
            WHERE " . implode(' AND ', $w) . " ORDER BY sm.created_at DESC, sm.id DESC LIMIT 3000");
        $st->execute($p);
        $rows = $st->fetchAll();
        $tot = ['qty' => 0, 'amount' => 0, 'paid' => 0, 'unpaid' => 0, 'internal' => 0];
        $groups = ['item' => [], 'department' => [], 'person' => []];
        foreach ($rows as $r) {
            $a = (float)$r['amount']; $q = (float)$r['qty'];
            $tot['qty'] += $q; $tot['amount'] += $a; $tot[$r['paid_state']] += $a;
            foreach ([['item', $r['item_name']], ['department', $r['department_name'] ?: '—'], ['person', $r['person_name'] ?: '—']] as [$g, $label]) {
                $x = &$groups[$g][$label];
                $x = $x ?? ['label' => $label, 'qty' => 0, 'amount' => 0, 'paid' => 0, 'unpaid' => 0, 'internal' => 0];
                $x['qty'] += $q; $x['amount'] += $a; $x[$r['paid_state']] += $a;
                unset($x);
            }
        }
        foreach ($groups as $g => $list) { $list = array_values($list); usort($list, fn($a, $b) => $b['amount'] <=> $a['amount'] ?: $b['qty'] <=> $a['qty']); $groups[$g] = $list; }
        success(['rows' => $rows, 'totals' => $tot, 'groups' => $groups, 'truncated' => count($rows) >= 3000]);
    }

    // Filter lists for the report
    if ($action === 'ws_lookups') {
        success([
            'departments' => $pdo->query("SELECT id, name FROM departments WHERE is_active=1" . ($wsViewAll ? '' : ' AND id = ' . $wsDept) . " ORDER BY name")->fetchAll(),
            'people' => $pdo->query("SELECT id, TRIM(CONCAT(first_name,' ',last_name)) name, department_id FROM users WHERE is_active=1" . ($wsViewAll ? '' : ' AND department_id = ' . $wsDept) . " ORDER BY first_name")->fetchAll(),
            'items' => $pdo->query("SELECT id, name FROM items WHERE is_active=1 ORDER BY name")->fetchAll(),
            'categories' => $pdo->query("SELECT id, name FROM item_categories ORDER BY name")->fetchAll(),
        ]);
    }
    error('Unknown action', 404);
}

// ---------- POST ----------
$in = $rawInput;

// A department member records what the department used. Deducts from the department's own store (FEFO). Blocks when stock is short.
if ($action === 'ws_consume') {
    $deptId = (int)($in['department_id'] ?? 0); $itemId = (int)($in['item_id'] ?? 0); $qty = round((float)($in['quantity'] ?? 0), 2);
    if ($deptId <= 0 || $itemId <= 0 || $qty <= 0) error('Department, item and a quantity above 0 are required', 422);
    if (!$wsStoreRole) {
        $mine = $pdo->prepare('SELECT department_id FROM users WHERE id = ?'); $mine->execute([$wsUid]);
        if ((int)$mine->fetchColumn() !== $deptId) error('You can only record use for your own department', 403);
    }
    $store = dept_store_id($pdo, $deptId);
    if (!$store) error('Department not found', 404);
    try {
        $pdo->beginTransaction();
        $bs = $pdo->prepare("SELECT id, quantity, unit_cost FROM stock_batches WHERE store_id = ? AND item_id = ? AND is_active = 1 AND quantity > 0 AND (expiry_date IS NULL OR expiry_date >= CURDATE()) ORDER BY (expiry_date IS NULL), expiry_date, created_at FOR UPDATE");
        $bs->execute([$store, $itemId]);
        $batches = $bs->fetchAll();
        if (array_sum(array_column($batches, 'quantity')) < $qty) { $pdo->rollBack(); error('Not enough stock in this department store (' . array_sum(array_column($batches, 'quantity')) . ' available)', 422); }
        $cost = (float)$batches[0]['unit_cost'];
        $pdo->prepare('INSERT INTO dept_consumption (department_id, item_id, quantity, unit_cost, patient_id, used_by, note) VALUES (?,?,?,?,?,?,?)')
            ->execute([$deptId, $itemId, $qty, $cost, (int)($in['patient_id'] ?? 0) ?: null, $wsUid, mb_substr(trim((string)($in['note'] ?? '')), 0, 255)]);
        $cid = (int)$pdo->lastInsertId();
        $left = $qty;
        foreach ($batches as $b) {
            if ($left <= 0) break;
            $take = min($left, (float)$b['quantity']);
            $pdo->prepare('UPDATE stock_batches SET quantity = quantity - ? WHERE id = ?')->execute([$take, $b['id']]);
            $pdo->prepare("INSERT INTO stock_movements (item_id, batch_id, from_store_id, movement_type, reference_type, reference_id, quantity, unit_cost, notes, created_by) VALUES (?,?,?,'dispensing','dept_use',?,?,?,?,?)")
                ->execute([$itemId, $b['id'], $store, $cid, $take, $b['unit_cost'], 'Used by department', $wsUid ?: null]);
            $left -= $take;
        }
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error($e->getMessage(), 422); }
    success(['id' => $cid], 'Use recorded and deducted from the department store', 201);
}

ws_need_store_role($wsStoreRole);

// Edit one batch (details, or a corrected quantity). A quantity change needs a reason and is logged as an adjustment.
if ($action === 'ws_edit') {
    $bid = (int)($in['batch_id'] ?? 0);
    $b = $pdo->prepare('SELECT * FROM stock_batches WHERE id = ? AND is_active = 1'); $b->execute([$bid]); $b = $b->fetch();
    if (!$b) error('Stock record not found', 404);
    $set = []; $args = [];
    foreach (['batch_number' => 'string', 'location_rack' => 'string', 'expiry_date' => 'date', 'reorder_level' => 'num'] as $f => $t) {
        if (!array_key_exists($f, $in)) continue;
        $v = $in[$f];
        if ($t === 'date') { if ($v === '' || $v === null) $v = null; elseif (!DateTime::createFromFormat('Y-m-d', (string)$v)) error('Invalid expiry date', 422); }
        elseif ($t === 'num') { if (!is_numeric($v) || $v < 0) error('Reorder level must be 0 or more', 422); }
        else $v = mb_substr(trim((string)$v), 0, 50);
        $set[] = "$f = ?"; $args[] = $v;
    }
    $newQty = array_key_exists('quantity', $in) ? round((float)$in['quantity'], 2) : null;
    $reason = trim((string)($in['reason'] ?? ''));
    $qtyChanged = $newQty !== null && abs($newQty - (float)$b['quantity']) > 0.001;
    if ($qtyChanged) {
        if ($newQty < 0) error('Quantity cannot be negative', 422);
        if (strlen($reason) < 3) error('A reason is required to change a quantity', 422);
        $set[] = 'quantity = ?'; $args[] = $newQty;
    }
    if (!$set) error('Nothing to change', 422);
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE stock_batches SET ' . implode(', ', $set) . ' WHERE id = ?')->execute(array_merge($args, [$bid]));
    if ($qtyChanged) $pdo->prepare("INSERT INTO stock_movements (item_id, batch_id, to_store_id, movement_type, reference_type, quantity, unit_cost, notes, created_by) VALUES (?,?,?,'adjustment','ws_edit',?,?,?,?)")
        ->execute([$b['item_id'], $bid, $b['store_id'], abs($newQty - (float)$b['quantity']), $b['unit_cost'], 'Quantity ' . $b['quantity'] . ' -> ' . $newQty . ': ' . $reason, $wsUid ?: null]);
    $pdo->commit();
    success(null, 'Stock record updated');
}

// "Delete" = void: the record stays in the movement history, the stock goes to 0, a reason is mandatory.
if ($action === 'ws_void') {
    $bid = (int)($in['batch_id'] ?? 0); $reason = trim((string)($in['reason'] ?? ''));
    if (strlen($reason) < 3) error('A reason is required', 422);
    $b = $pdo->prepare('SELECT * FROM stock_batches WHERE id = ? AND is_active = 1'); $b->execute([$bid]); $b = $b->fetch();
    if (!$b) error('Stock record not found', 404);
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE stock_batches SET quantity = 0, is_active = 0 WHERE id = ?')->execute([$bid]);
    $pdo->prepare("INSERT INTO stock_movements (item_id, batch_id, from_store_id, movement_type, reference_type, quantity, unit_cost, notes, created_by) VALUES (?,?,?,'adjustment','ws_void',?,?,?,?)")
        ->execute([$b['item_id'], $bid, $b['store_id'], $b['quantity'], $b['unit_cost'], 'VOID: ' . $reason, $wsUid ?: null]);
    $pdo->commit();
    success(null, 'Stock record voided');
}

error('Unknown action', 404);

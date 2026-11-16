<?php
// handlers/assets/asset-management.php
// Asset transfers, custody (assignments) and approved disposals. Value-changing events post through includes/finance_posting.php;
// transfers and custody only move the asset, so they post nothing.
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/asset_schema.php';
am_boot($pdo);

function jsonResponse($d, $s = 200) { http_response_code($s); echo json_encode($d); exit(); }
function success($d = null, $m = 'Success', $s = 200) { jsonResponse(['success' => true, 'message' => $m, 'data' => $d], $s); }
function error($m, $s = 400) { jsonResponse(['success' => false, 'message' => $m], $s); }

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$uid = ctl_uid();

// Who may do what. Approvals are never done by the person who raised the request (ctl_conflict).
const AM_OPERATE = ['admin', 'accountant', 'maintenance_officer', 'inventory_manager'];
const AM_DECIDE_TRANSFER = ['admin', 'accountant', 'managing_director'];
const AM_DECIDE_DISPOSAL = ['admin', 'managing_director'];
const AM_VERIFY_WORK = ['admin', 'managing_director', 'maintenance_officer'];   // may release repaired equipment; never the person who did the work

function am_can(PDO $pdo, array $slugs): bool { return (bool)array_intersect(inv_role_slugs($pdo), $slugs); }
function am_need(PDO $pdo, array $slugs, string $msg): void { if (!am_can($pdo, $slugs)) error($msg, 403); }
function am_date($v, string $label, bool $allowFuture = false): string {
    $d = substr((string)($v ?: date('Y-m-d')), 0, 10);
    $t = DateTime::createFromFormat('Y-m-d', $d);
    if (!$t || $t->format('Y-m-d') !== $d) error("$label is not a valid date", 422);
    if (!$allowFuture && $d > date('Y-m-d')) error("$label cannot be in the future", 422);
    return $d;
}
function am_asset(PDO $pdo, int $id, bool $lock = true): array {
    $q = $pdo->prepare("SELECT a.*, COALESCE(c.account_id, (SELECT x.id FROM chart_of_accounts x WHERE x.account_code = CASE c.name WHEN 'Land' THEN '1211' WHEN 'Buildings' THEN '1212' WHEN 'Furniture & Fixtures' THEN '1213' WHEN 'Electronic Equipment' THEN '1217' WHEN 'Computers & IT' THEN '1217' WHEN 'Intangible Assets' THEN '1218' WHEN 'Plant & Machinery' THEN '1218' WHEN 'Motor Vehicles' THEN '1218' WHEN 'Other Equipment' THEN '1218' END)) AS cat_account FROM assets a LEFT JOIN asset_categories c ON c.id = a.category_id WHERE a.id = ?" . ($lock ? ' FOR UPDATE' : ''));
    $q->execute([$id]);
    $a = $q->fetch();
    if (!$a) { if ($pdo->inTransaction()) $pdo->rollBack(); error('Asset not found', 404); }
    return $a;
}
function am_live(array $a): bool { return $a['status'] !== 'disposed'; }
function am_fail(PDO $pdo, string $msg, int $code = 409): void { if ($pdo->inTransaction()) $pdo->rollBack(); error($msg, $code); }
function am_text($v, int $max): ?string { $v = trim((string)$v); return $v === '' ? null : mb_substr($v, 0, $max); }

// Equipment status follows the work: open job -> under maintenance, finished but unchecked -> awaiting inspection, open fault -> faulty.
// 'out_of_service' is set by hand and is only left by hand.
function am_sync_status(PDO $pdo, int $assetId): void {
    $cur = $pdo->prepare("SELECT equipment_status FROM assets WHERE id = ?"); $cur->execute([$assetId]);
    if ($cur->fetchColumn() === 'out_of_service') return;
    $n = function (string $sql) use ($pdo, $assetId): int { $q = $pdo->prepare($sql); $q->execute([$assetId]); return (int)$q->fetchColumn(); };
    if ($n("SELECT COUNT(*) FROM asset_work_orders WHERE asset_id = ? AND status IN ('open','in_progress')")) $st = 'under_maintenance';
    elseif ($n("SELECT COUNT(*) FROM asset_work_orders WHERE asset_id = ? AND status = 'awaiting_verification'")) $st = 'awaiting_inspection';
    elseif ($n("SELECT COUNT(*) FROM asset_fault_requests WHERE asset_id = ? AND status IN ('open','assigned')")) $st = 'faulty';
    else $st = 'operational';
    $pdo->prepare("UPDATE assets SET equipment_status = ? WHERE id = ?")->execute([$st, $assetId]);
}
function am_priority($v): string { return in_array($v, ['low', 'medium', 'high', 'critical'], true) ? $v : 'medium'; }
function am_cash_ok(PDO $pdo, int $accId): bool { foreach (fin_cash_accounts($pdo) as $c) if ((int)$c['id'] === $accId) return true; return false; }

// the inventory API is reachable by any logged-in role for reads; asset data is limited to the roles below
am_need($pdo, array_merge(AM_OPERATE, ['managing_director']), 'Your role cannot use asset management.');

try {

if ($method === 'GET') {

    if ($action === 'assets') {
        $rows = $pdo->query("SELECT a.id, a.asset_code, a.name, a.status, a.location, a.department_id, a.custodian_id, a.purchase_date,
                                    a.purchase_cost, a.accumulated_depreciation, (a.purchase_cost - a.accumulated_depreciation) AS book_value,
                                    a.serial_number, a.warranty_expiry, a.equipment_status, a.is_critical,
                                    (SELECT MAX(v.verified_at) FROM asset_verifications v WHERE v.asset_id = a.id) AS last_verified,
                                    d.name AS department_name, CONCAT(e.first_name, ' ', e.last_name) AS custodian_name
                             FROM assets a LEFT JOIN departments d ON d.id = a.department_id LEFT JOIN employees e ON e.id = a.custodian_id
                             WHERE a.status <> 'disposed' ORDER BY a.asset_code")->fetchAll();
        success($rows);
    }
    if ($action === 'cash_accounts') success(array_map(fn($c) => ['id' => $c['id'], 'account_name' => $c['account_name']], fin_cash_accounts($pdo)));
    if ($action === 'departments') success($pdo->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name")->fetchAll());
    if ($action === 'employees')   success($pdo->query("SELECT id, employee_no, CONCAT(first_name, ' ', last_name) AS name FROM employees WHERE status = 'active' ORDER BY first_name, last_name")->fetchAll());

    if ($action === 'transfers') {
        success($pdo->query("SELECT t.*, a.asset_code, a.name AS asset_name, fd.name AS from_department, td.name AS to_department,
                                    CONCAT(ru.first_name, ' ', ru.last_name) AS requested_by_name, CONCAT(re.first_name, ' ', re.last_name) AS received_by_name,
                                    CONCAT(au.first_name, ' ', au.last_name) AS decided_by_name
                             FROM asset_transfers t LEFT JOIN employees re ON re.id = t.received_by_employee_id LEFT JOIN users au ON au.id = t.decided_by JOIN assets a ON a.id = t.asset_id
                             LEFT JOIN departments fd ON fd.id = t.from_department_id LEFT JOIN departments td ON td.id = t.to_department_id
                             LEFT JOIN users ru ON ru.id = t.requested_by ORDER BY t.id DESC LIMIT 200")->fetchAll());
    }
    if ($action === 'assignments') {
        success($pdo->query("SELECT s.*, a.asset_code, a.name AS asset_name, a.serial_number, a.location AS asset_location, e.employee_no, dp.name AS employee_department,
                                    CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                                    (s.status = 'assigned' AND s.expected_return_date IS NOT NULL AND s.expected_return_date < CURDATE()) AS overdue
                             FROM asset_assignments s JOIN assets a ON a.id = s.asset_id JOIN employees e ON e.id = s.employee_id LEFT JOIN departments dp ON dp.id = e.department_id
                             ORDER BY s.id DESC LIMIT 200")->fetchAll());
    }
    if ($action === 'disposals') {
        success($pdo->query("SELECT d.*, a.asset_code, a.name AS asset_name, a.purchase_cost, a.accumulated_depreciation,
                                    (a.purchase_cost - a.accumulated_depreciation) AS book_value,
                                    CONCAT(ru.first_name, ' ', ru.last_name) AS requested_by_name, CONCAT(du.first_name, ' ', du.last_name) AS decided_by_name, a.serial_number
                             FROM asset_disposals d JOIN assets a ON a.id = d.asset_id LEFT JOIN users ru ON ru.id = d.requested_by LEFT JOIN users du ON du.id = d.decided_by
                             ORDER BY d.id DESC LIMIT 200")->fetchAll());
    }
    // ------------------------------------------------------------ movement, clearance, verification
    if ($action === 'movements') {
        success($pdo->query("SELECT t.transfer_no, t.transfer_date, t.completed_at, t.from_location, t.to_location, t.reason, a.asset_code, a.name AS asset_name,
                                    fd.name AS from_department, td.name AS to_department, CONCAT(ru.first_name, ' ', ru.last_name) AS requested_by_name,
                                    CONCAT(au.first_name, ' ', au.last_name) AS decided_by_name, CONCAT(re.first_name, ' ', re.last_name) AS received_by_name
                             FROM asset_transfers t JOIN assets a ON a.id = t.asset_id
                             LEFT JOIN departments fd ON fd.id = t.from_department_id LEFT JOIN departments td ON td.id = t.to_department_id
                             LEFT JOIN users ru ON ru.id = t.requested_by LEFT JOIN users au ON au.id = t.decided_by LEFT JOIN employees re ON re.id = t.received_by_employee_id
                             WHERE t.status = 'completed' ORDER BY t.completed_at DESC, t.id DESC LIMIT 300")->fetchAll());
    }
    // staff who still hold assets; people who have left come first
    if ($action === 'clearance') {
        success($pdo->query("SELECT e.id, e.employee_no, CONCAT(e.first_name, ' ', e.last_name) AS name, e.status AS employee_status, dp.name AS department,
                                    COUNT(*) AS held, GROUP_CONCAT(CONCAT(a.asset_code, ' ', a.name) ORDER BY a.asset_code SEPARATOR '; ') AS assets,
                                    SUM(s.expected_return_date IS NOT NULL AND s.expected_return_date < CURDATE()) AS overdue
                             FROM asset_assignments s JOIN employees e ON e.id = s.employee_id JOIN assets a ON a.id = s.asset_id LEFT JOIN departments dp ON dp.id = e.department_id
                             WHERE s.status = 'assigned' GROUP BY e.id ORDER BY (e.status = 'active'), name")->fetchAll());
    }
    if ($action === 'verifications') {
        success($pdo->query("SELECT v.*, a.asset_code, a.name AS asset_name, a.location AS register_location, CONCAT(u.first_name, ' ', u.last_name) AS verified_by_name
                             FROM asset_verifications v JOIN assets a ON a.id = v.asset_id LEFT JOIN users u ON u.id = v.verified_by ORDER BY v.id DESC LIMIT 200")->fetchAll());
    }

    // ------------------------------------------------------------ maintenance
    if ($action === 'maintenance_schedule') {
        success($pdo->query("SELECT m.id, m.asset_id, m.kind, m.basis, m.due_date, m.interval_days, m.notes, a.asset_code, a.name AS asset_name, a.is_critical,
                                    (m.due_date < CURDATE()) AS overdue, (SELECT w.wo_no FROM asset_work_orders w WHERE w.maintenance_id = m.id AND w.status NOT IN ('completed','cancelled') LIMIT 1) AS open_wo
                             FROM asset_maintenance m JOIN assets a ON a.id = m.asset_id WHERE m.status = 'scheduled' AND a.status <> 'disposed' ORDER BY m.due_date LIMIT 300")->fetchAll());
    }
    if ($action === 'faults') {
        success($pdo->query("SELECT f.*, a.asset_code, a.name AS asset_name, a.location, a.is_critical, CONCAT(u.first_name, ' ', u.last_name) AS reported_by_name,
                                    DATEDIFF(CURDATE(), DATE(f.reported_at)) AS age_days,
                                    (SELECT w.wo_no FROM asset_work_orders w WHERE w.fault_request_id = f.id AND w.status <> 'cancelled' ORDER BY w.id DESC LIMIT 1) AS wo_no
                             FROM asset_fault_requests f JOIN assets a ON a.id = f.asset_id LEFT JOIN users u ON u.id = f.reported_by
                             ORDER BY f.status IN ('open','assigned') DESC, FIELD(f.priority,'critical','high','medium','low'), f.id DESC LIMIT 300")->fetchAll());
    }
    if ($action === 'work_orders') {
        $view = $_GET['view'] ?? 'active';
        $where = ['active' => "w.status IN ('open','in_progress')", 'verify' => "w.status = 'awaiting_verification'", 'history' => "w.status IN ('completed','cancelled')"][$view] ?? null;
        if (!$where) error('Unknown view', 422);
        success($pdo->query("SELECT w.*, a.asset_code, a.name AS asset_name, a.is_critical, f.request_no AS fault_no, f.description AS fault_description,
                                    CONCAT(t.first_name, ' ', t.last_name) AS technician_name, CONCAT(wd.first_name, ' ', wd.last_name) AS work_done_by_name,
                                    CONCAT(vu.first_name, ' ', vu.last_name) AS verified_by_name
                             FROM asset_work_orders w JOIN assets a ON a.id = w.asset_id LEFT JOIN asset_fault_requests f ON f.id = w.fault_request_id
                             LEFT JOIN employees t ON t.id = w.assigned_employee_id LEFT JOIN users wd ON wd.id = w.work_done_by LEFT JOIN users vu ON vu.id = w.verified_by
                             WHERE $where ORDER BY FIELD(w.priority,'critical','high','medium','low'), w.id DESC LIMIT 300")->fetchAll());
    }

    // ------------------------------------------------------------ monitoring
    if ($action === 'equipment') {
        success($pdo->query("SELECT a.id, a.asset_code, a.name, a.location, a.equipment_status, a.is_critical, a.warranty_expiry, d.name AS department_name,
                                    (SELECT COUNT(*) FROM asset_fault_requests f WHERE f.asset_id = a.id AND f.status IN ('open','assigned')) AS open_faults,
                                    (SELECT MIN(m.due_date) FROM asset_maintenance m WHERE m.asset_id = a.id AND m.status = 'scheduled') AS next_due,
                                    (SELECT COALESCE(SUM(w.downtime_hours), 0) FROM asset_work_orders w WHERE w.asset_id = a.id AND w.status = 'completed') AS downtime_hours,
                                    (SELECT COUNT(*) FROM asset_work_orders w WHERE w.asset_id = a.id AND w.status = 'completed' AND w.kind = 'repair') AS repairs,
                                    (SELECT COALESCE(SUM(w.cost), 0) FROM asset_work_orders w WHERE w.asset_id = a.id AND w.status = 'completed') AS maintenance_cost
                             FROM assets a LEFT JOIN departments d ON d.id = a.department_id WHERE a.status <> 'disposed'
                             ORDER BY a.is_critical DESC, a.equipment_status = 'operational', a.asset_code")->fetchAll());
    }
    if ($action === 'alerts') {
        $out = [];
        $add = function (string $sev, string $kind, string $asset, string $msg, string $tab) use (&$out) { $out[] = ['severity' => $sev, 'kind' => $kind, 'asset' => $asset, 'message' => $msg, 'tab' => $tab]; };
        foreach ($pdo->query("SELECT m.kind, m.due_date, a.asset_code, a.name, a.is_critical, DATEDIFF(m.due_date, CURDATE()) AS days
                              FROM asset_maintenance m JOIN assets a ON a.id = m.asset_id WHERE m.status = 'scheduled' AND a.status <> 'disposed' AND m.due_date <= CURDATE() + INTERVAL 14 DAY ORDER BY m.due_date") as $r) {
            $lbl = $r['kind'] === 'inspection' ? 'Inspection' : 'Maintenance';
            if ($r['days'] < 0) $add($r['is_critical'] ? 'critical' : 'high', $r['kind'] === 'inspection' ? 'Overdue inspection' : 'Overdue maintenance', "{$r['asset_code']} {$r['name']}", "$lbl was due {$r['due_date']} (" . abs($r['days']) . ' days ago)', 'm_schedule');
            else $add('info', 'Upcoming maintenance', "{$r['asset_code']} {$r['name']}", "$lbl due {$r['due_date']}" . ($r['days'] == 0 ? ' (today)' : " (in {$r['days']} days)"), 'm_schedule');
        }
        foreach ($pdo->query("SELECT a.asset_code, a.name, a.warranty_expiry, DATEDIFF(a.warranty_expiry, CURDATE()) AS days FROM assets a
                              WHERE a.status <> 'disposed' AND a.warranty_expiry IS NOT NULL AND a.warranty_expiry BETWEEN CURDATE() AND CURDATE() + INTERVAL 60 DAY ORDER BY a.warranty_expiry") as $r)
            $add('warn', 'Warranty expiring', "{$r['asset_code']} {$r['name']}", "Warranty ends {$r['warranty_expiry']} (in {$r['days']} days)", 'mon_status');
        foreach ($pdo->query("SELECT f.request_no, f.priority, f.description, a.asset_code, a.name, a.is_critical, DATEDIFF(CURDATE(), DATE(f.reported_at)) AS age
                              FROM asset_fault_requests f JOIN assets a ON a.id = f.asset_id WHERE f.status IN ('open','assigned') ORDER BY f.id") as $r)
            $add($r['is_critical'] || $r['priority'] === 'critical' ? 'critical' : ($r['priority'] === 'high' ? 'high' : 'warn'), $r['is_critical'] ? 'Critical equipment fault' : 'Unresolved fault', "{$r['asset_code']} {$r['name']}",
                 "{$r['request_no']}: " . mb_substr($r['description'], 0, 90) . " ({$r['age']} days open)", 'm_faults');
        foreach ($pdo->query("SELECT w.wo_no, a.asset_code, a.name FROM asset_work_orders w JOIN assets a ON a.id = w.asset_id WHERE w.status = 'awaiting_verification'") as $r)
            $add('warn', 'Awaiting verification', "{$r['asset_code']} {$r['name']}", "{$r['wo_no']} is finished and needs sign-off before the equipment returns to use", 'm_verify');
        $rank = ['critical' => 0, 'high' => 1, 'warn' => 2, 'info' => 3];
        usort($out, fn($x, $y) => $rank[$x['severity']] <=> $rank[$y['severity']]);
        success($out);
    }
    if ($action === 'me') {
        success(['can_decide_transfer' => am_can($pdo, AM_DECIDE_TRANSFER), 'can_decide_disposal' => am_can($pdo, AM_DECIDE_DISPOSAL), 'can_verify_work' => am_can($pdo, AM_VERIFY_WORK), 'user_id' => $uid]);
    }
    // Asset categories page: every active category with how many assets use it, and the asset ledger accounts to link to
    if ($action === 'categories') {
        success($pdo->query("SELECT c.id, c.name, c.depreciation_rate, c.depreciation_method, c.useful_life_years, c.account_id,
                                    CONCAT(a.account_code, ' - ', a.account_name) AS account_label,
                                    (SELECT COUNT(*) FROM assets x WHERE x.category_id = c.id) AS assets
                             FROM asset_categories c LEFT JOIN chart_of_accounts a ON a.id = c.account_id
                             WHERE c.status = 'active' ORDER BY c.name")->fetchAll());
    }
    if ($action === 'asset_accounts') success($pdo->query("SELECT id, account_code, account_name FROM chart_of_accounts WHERE parent_id = 14 ORDER BY account_code")->fetchAll());
    error('Unknown action', 404);
}

if ($method !== 'POST') error('Method not allowed', 405);
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// ---------------------------------------------------------------- categories (name, depreciation policy, ledger account)
if ($action === 'category_save' || $action === 'category_delete') {
    am_need($pdo, ['admin', 'accountant'], 'Only an administrator or accountant can change asset categories.');
    $id = (int)($input['id'] ?? 0);
    if ($action === 'category_delete') {
        if (!$id) error('Missing category', 422);
        $n = $pdo->prepare("SELECT COUNT(*) FROM assets WHERE category_id = ?"); $n->execute([$id]);
        if ((int)$n->fetchColumn() > 0) error('Assets still use this category. Move them to another category first.', 409);
        $pdo->prepare("UPDATE asset_categories SET status = 'inactive' WHERE id = ?")->execute([$id]);   // hidden, not removed: old records may point to it
        success(null, 'Category deleted');
    }
    $name = am_text($input['name'] ?? '', 100); if (!$name) error('Category name is required', 422);
    $dm = $input['depreciation_method'] ?? 'straight_line';
    if (!in_array($dm, ['straight_line', 'reducing_balance', 'none'], true)) error('Choose a valid depreciation method', 422);
    $rate = $dm === 'none' ? 0 : (float)($input['depreciation_rate'] ?? 0);
    if ($rate < 0 || $rate > 100) error('Rate must be between 0 and 100', 422);
    $life = max(0, (int)($input['useful_life_years'] ?? 0));
    $acc = (int)($input['account_id'] ?? 0) ?: null;
    if ($acc) { $c = $pdo->prepare("SELECT 1 FROM chart_of_accounts WHERE id = ? AND parent_id = 14"); $c->execute([$acc]); if (!$c->fetchColumn()) error('Choose a valid asset account', 422); }
    $d = $pdo->prepare("SELECT 1 FROM asset_categories WHERE name = ? AND status = 'active' AND id <> ?"); $d->execute([$name, $id]);
    if ($d->fetchColumn()) error('A category with this name already exists', 422);
    if ($id) {
        $pdo->prepare("UPDATE asset_categories SET name=?, depreciation_rate=?, depreciation_method=?, useful_life_years=?, account_id=? WHERE id=?")->execute([$name, $rate, $dm, $life, $acc, $id]);
        success(null, 'Category updated');
    }
    $pdo->prepare("INSERT INTO asset_categories (name, depreciation_rate, depreciation_method, useful_life_years, account_id, status) VALUES (?,?,?,?,?, 'active')")->execute([$name, $rate, $dm, $life, $acc]);
    success(['id' => (int)$pdo->lastInsertId()], 'Category added', 201);
}

// ---------------------------------------------------------------- transfers
if ($action === 'transfer_request') {
    am_need($pdo, AM_OPERATE, 'Your role cannot request asset transfers.');
    $assetId = (int)($input['asset_id'] ?? 0);
    $reason = am_text($input['reason'] ?? '', 500); if (!$reason) error('Give the reason for the transfer', 422);
    $date = am_date($input['transfer_date'] ?? null, 'Transfer date', true);
    $toDept = !empty($input['to_department_id']) ? (int)$input['to_department_id'] : null;
    if ($toDept) { $c = $pdo->prepare("SELECT 1 FROM departments WHERE id = ? AND is_active = 1"); $c->execute([$toDept]); if (!$c->fetchColumn()) error('Choose a valid department', 422); }

    $pdo->beginTransaction();
    $a = am_asset($pdo, $assetId);
    if (!am_live($a)) am_fail($pdo, 'A disposed asset cannot be transferred');
    $toLoc = am_text($input['to_location'] ?? '', 100) ?? $a['location'];
    $toDept = $toDept ?? ($a['department_id'] ? (int)$a['department_id'] : null);
    if ((string)$toLoc === (string)$a['location'] && (int)$toDept === (int)$a['department_id']) am_fail($pdo, 'The asset is already in that location and department', 422);
    $open = $pdo->prepare("SELECT transfer_no FROM asset_transfers WHERE asset_id = ? AND status IN ('pending','approved')"); $open->execute([$assetId]);
    if ($no = $open->fetchColumn()) am_fail($pdo, "This asset already has an open transfer ($no)");
    $d = $pdo->prepare("SELECT 1 FROM asset_disposals WHERE asset_id = ? AND status = 'pending'"); $d->execute([$assetId]);
    if ($d->fetchColumn()) am_fail($pdo, 'This asset has a disposal waiting for approval');
    $no = next_doc_no($pdo, 'asset_transfers', 'transfer_no', 'TRF-' . date('Y') . '-');
    // the "from" side is always what the register says, never what the browser sends
    $pdo->prepare("INSERT INTO asset_transfers (transfer_no, asset_id, from_location, to_location, from_department_id, to_department_id, transfer_date, reason, requested_by) VALUES (?,?,?,?,?,?,?,?,?)")
        ->execute([$no, $assetId, $a['location'], $toLoc, $a['department_id'], $toDept, $date, $reason, $uid ?: null]);
    $id = (int)$pdo->lastInsertId();
    $pdo->commit();
    if (function_exists('log_activity')) log_activity($pdo, 'create', 'assets', "Transfer $no requested for {$a['asset_code']}", $id, $no);
    success(['id' => $id, 'transfer_no' => $no], 'Transfer requested', 201);
}

if ($action === 'transfer_decide') {
    am_need($pdo, AM_DECIDE_TRANSFER, 'Your role cannot approve asset transfers.');
    $id = (int)($input['id'] ?? 0); $decision = $input['decision'] ?? '';
    if (!in_array($decision, ['approve', 'reject'], true)) error('Decision must be approve or reject', 422);
    $note = am_text($input['note'] ?? '', 500);
    if ($decision === 'reject' && !$note) error('A reason is required to reject', 422);
    $pdo->beginTransaction();
    $q = $pdo->prepare("SELECT * FROM asset_transfers WHERE id = ? FOR UPDATE"); $q->execute([$id]); $t = $q->fetch();
    if (!$t) am_fail($pdo, 'Transfer not found', 404);
    if ($t['status'] !== 'pending') am_fail($pdo, 'Only a pending transfer can be decided (it is ' . $t['status'] . ')');
    if ($decision === 'approve' && ($m = ctl_conflict($pdo, 'approve', ['requested' => $t['requested_by']]))) am_fail($pdo, $m, 403);
    $pdo->prepare("UPDATE asset_transfers SET status = ?, decided_by = ?, decided_at = NOW(), decision_note = ? WHERE id = ?")
        ->execute([$decision === 'approve' ? 'approved' : 'rejected', $uid ?: null, $note, $id]);
    $pdo->commit();
    if (function_exists('log_activity')) log_activity($pdo, $decision, 'assets', "Transfer {$t['transfer_no']} " . ($decision === 'approve' ? 'approved' : 'rejected') . ($note ? ": $note" : ''), $id, $t['transfer_no']);
    success(null, $decision === 'approve' ? 'Transfer approved' : 'Transfer rejected');
}

if ($action === 'transfer_complete') {
    am_need($pdo, AM_OPERATE, 'Your role cannot complete asset transfers.');
    $id = (int)($input['id'] ?? 0);
    $recv = (int)($input['received_by_employee_id'] ?? 0);
    $e = $pdo->prepare("SELECT 1 FROM employees WHERE id = ? AND status = 'active'"); $e->execute([$recv]);
    if (!$e->fetchColumn()) error('Choose the receiving officer who is taking possession', 422);
    $pdo->beginTransaction();
    $q = $pdo->prepare("SELECT * FROM asset_transfers WHERE id = ? FOR UPDATE"); $q->execute([$id]); $t = $q->fetch();
    if (!$t) am_fail($pdo, 'Transfer not found', 404);
    if ($t['status'] !== 'approved') am_fail($pdo, 'Only an approved transfer can be completed (it is ' . $t['status'] . ')');
    $a = am_asset($pdo, (int)$t['asset_id']);
    if (!am_live($a)) am_fail($pdo, 'The asset has been disposed in the meantime');
    $pdo->prepare("UPDATE assets SET location = ?, department_id = ? WHERE id = ?")->execute([$t['to_location'], $t['to_department_id'], $a['id']]);
    $pdo->prepare("UPDATE asset_transfers SET status = 'completed', completed_by = ?, completed_at = NOW(), received_by_employee_id = ? WHERE id = ?")->execute([$uid ?: null, $recv, $id]);
    $pdo->commit();
    if (function_exists('log_activity')) log_activity($pdo, 'update', 'assets', "Transfer {$t['transfer_no']} completed: {$a['asset_code']} moved to {$t['to_location']}", $id, $t['transfer_no']);
    success(null, 'Asset moved');
}

// ---------------------------------------------------------------- custody
if ($action === 'assign') {
    am_need($pdo, AM_OPERATE, 'Your role cannot assign assets.');
    $assetId = (int)($input['asset_id'] ?? 0); $empId = (int)($input['employee_id'] ?? 0);
    $date = am_date($input['assigned_date'] ?? null, 'Assigned date');
    $due = !empty($input['expected_return_date']) ? am_date($input['expected_return_date'], 'Expected return date', true) : null;
    if ($due && $due < $date) error('The expected return date is before the assigned date', 422);
    $e = $pdo->prepare("SELECT 1 FROM employees WHERE id = ? AND status = 'active'"); $e->execute([$empId]);
    if (!$e->fetchColumn()) error('Choose an active employee', 422);

    $pdo->beginTransaction();
    $a = am_asset($pdo, $assetId);
    if (!am_live($a)) am_fail($pdo, 'A disposed asset cannot be assigned');
    $d = $pdo->prepare("SELECT 1 FROM asset_disposals WHERE asset_id = ? AND status = 'pending'"); $d->execute([$assetId]);
    if ($d->fetchColumn()) am_fail($pdo, 'This asset has a disposal waiting for approval');
    $open = $pdo->prepare("SELECT assignment_no FROM asset_assignments WHERE asset_id = ? AND status = 'assigned'"); $open->execute([$assetId]);
    if ($no = $open->fetchColumn()) am_fail($pdo, "This asset is already assigned ($no); return it first");
    $no = next_doc_no($pdo, 'asset_assignments', 'assignment_no', 'ASN-' . date('Y') . '-');
    $pdo->prepare("INSERT INTO asset_assignments (assignment_no, asset_id, employee_id, assigned_date, expected_return_date, condition_out, notes, created_by) VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$no, $assetId, $empId, $date, $due, am_text($input['condition_out'] ?? '', 255) ?? 'Good working condition', am_text($input['notes'] ?? '', 500), $uid ?: null]);
    $id = (int)$pdo->lastInsertId();
    $pdo->prepare("UPDATE assets SET custodian_id = ? WHERE id = ?")->execute([$empId, $assetId]);
    $pdo->commit();
    if (function_exists('log_activity')) log_activity($pdo, 'create', 'assets', "Asset {$a['asset_code']} assigned ($no)", $id, $no);
    success(['id' => $id, 'assignment_no' => $no], 'Asset assigned', 201);
}

if ($action === 'assignment_return' || $action === 'assignment_lost') {
    am_need($pdo, AM_OPERATE, 'Your role cannot close asset assignments.');
    $id = (int)($input['id'] ?? 0); $lost = $action === 'assignment_lost';
    $cond = am_text($input['condition_in'] ?? '', 255);
    if (!$cond) error($lost ? 'Say what happened to the asset' : 'Record the condition on return', 422);
    $pdo->beginTransaction();
    $q = $pdo->prepare("SELECT * FROM asset_assignments WHERE id = ? FOR UPDATE"); $q->execute([$id]); $s = $q->fetch();
    if (!$s) am_fail($pdo, 'Assignment not found', 404);
    if ($s['status'] !== 'assigned') am_fail($pdo, 'This assignment is already closed (' . $s['status'] . ')');
    $date = am_date($input['returned_date'] ?? null, 'Return date');
    if ($date < $s['assigned_date']) am_fail($pdo, 'The return date is before the assigned date', 422);
    $pdo->prepare("UPDATE asset_assignments SET status = ?, returned_date = ?, condition_in = ? WHERE id = ?")->execute([$lost ? 'lost_damaged' : 'returned', $date, $cond, $id]);
    $pdo->prepare("UPDATE assets SET custodian_id = NULL WHERE id = ? AND custodian_id = ?")->execute([$s['asset_id'], $s['employee_id']]);
    $pdo->commit();
    if (function_exists('log_activity')) log_activity($pdo, 'update', 'assets', "Assignment {$s['assignment_no']} " . ($lost ? 'reported lost or damaged' : 'returned') . ": $cond", $id, $s['assignment_no']);
    success(null, $lost ? 'Reported as lost or damaged' : 'Asset returned');
}

// ---------------------------------------------------------------- disposals (request, then approval by someone else)
if ($action === 'disposal_request') {
    am_need($pdo, AM_OPERATE, 'Your role cannot request asset disposals.');
    $assetId = (int)($input['asset_id'] ?? 0);
    $method_ = $input['method'] ?? '';
    if (!in_array($method_, ['sold', 'scrapped', 'donated', 'written_off'], true)) error('Choose how the asset is being disposed of', 422);
    $reason = am_text($input['reason'] ?? '', 500); if (!$reason) error('Give the reason for the disposal', 422);
    $cond = am_text($input['asset_condition'] ?? '', 255); if (!$cond) error('Record the current condition of the asset', 422);
    $assess = am_text($input['assessment'] ?? '', 500);
    $date = am_date($input['disposal_date'] ?? null, 'Disposal date');
    $proceeds = $method_ === 'sold' ? round((float)($input['proceeds'] ?? 0), 2) : 0.0;
    if ($proceeds < 0) error('Proceeds cannot be negative', 422);
    $procAcc = null;
    if ($proceeds > 0) {
        $procAcc = !empty($input['proceeds_account_id']) ? (int)$input['proceeds_account_id'] : fin_acct($pdo, '111001');
        $ok = false; foreach (fin_cash_accounts($pdo) as $c) if ((int)$c['id'] === $procAcc) $ok = true;
        if (!$ok) error('Proceeds must be received into a cash, bank or mobile-money account', 422);
    }
    $pdo->beginTransaction();
    $a = am_asset($pdo, $assetId);
    if (!am_live($a)) am_fail($pdo, 'This asset is already disposed');
    if ($a['purchase_date'] && $date < $a['purchase_date']) am_fail($pdo, 'The disposal date is before the purchase date', 422);
    $open = $pdo->prepare("SELECT disposal_no FROM asset_disposals WHERE asset_id = ? AND status = 'pending'"); $open->execute([$assetId]);
    if ($no = $open->fetchColumn()) am_fail($pdo, "This asset already has a disposal waiting for approval ($no)");
    $no = next_doc_no($pdo, 'asset_disposals', 'disposal_no', 'DSP-' . date('Y') . '-');
    $pdo->prepare("INSERT INTO asset_disposals (disposal_no, asset_id, method, disposal_date, proceeds, proceeds_account_id, reason, asset_condition, assessment, requested_by) VALUES (?,?,?,?,?,?,?,?,?,?)")
        ->execute([$no, $assetId, $method_, $date, $proceeds, $procAcc, $reason, $cond, $assess, $uid ?: null]);
    $id = (int)$pdo->lastInsertId();
    $pdo->commit();
    if (function_exists('log_activity')) log_activity($pdo, 'create', 'assets', "Disposal $no requested for {$a['asset_code']} ($method_)", $id, $no);
    success(['id' => $id, 'disposal_no' => $no], 'Disposal sent for approval', 201);
}

if ($action === 'disposal_decide') {
    am_need($pdo, AM_DECIDE_DISPOSAL, 'Only an administrator or the managing director can decide a disposal.');
    $id = (int)($input['id'] ?? 0); $decision = $input['decision'] ?? '';
    if (!in_array($decision, ['approve', 'reject'], true)) error('Decision must be approve or reject', 422);
    $note = am_text($input['note'] ?? '', 500);
    if ($decision === 'reject' && !$note) error('A reason is required to reject', 422);

    $pdo->beginTransaction();
    $q = $pdo->prepare("SELECT * FROM asset_disposals WHERE id = ? FOR UPDATE"); $q->execute([$id]); $d = $q->fetch();
    if (!$d) am_fail($pdo, 'Disposal not found', 404);
    if ($d['status'] !== 'pending') am_fail($pdo, 'Only a pending disposal can be decided (it is ' . $d['status'] . ')');
    // the requester can never decide it, even an administrator (unless an administrator switched separation of duties off)
    if (($m = ctl_conflict($pdo, 'approve', ['requested' => $d['requested_by']]))) am_fail($pdo, $m, 403);

    if ($decision === 'reject') {
        $pdo->prepare("UPDATE asset_disposals SET status = 'rejected', decided_by = ?, decided_at = NOW(), decision_note = ? WHERE id = ?")->execute([$uid ?: null, $note, $id]);
        $pdo->commit();
        if (function_exists('log_activity')) log_activity($pdo, 'reject', 'assets', "Disposal {$d['disposal_no']} rejected: $note", $id, $d['disposal_no']);
        success(null, 'Disposal rejected');
    }

    $a = am_asset($pdo, (int)$d['asset_id']);
    if (!am_live($a)) am_fail($pdo, 'This asset is already disposed');
    $assetAcc = (int)($a['account_id'] ?: $a['cat_account']);
    $cost = round((float)$a['purchase_cost'], 2); $accDep = round((float)$a['accumulated_depreciation'], 2);
    $proceeds = round((float)$d['proceeds'], 2);
    $surplus = round((float)$a['reval_surplus'], 2);
    if ($cost > 0 && !$assetAcc) am_fail($pdo, 'This asset has no asset account; set its category account first', 422);
    // gain/loss is computed now, on the figures at approval, not at request
    [$lines, $gain] = asset_disposal_lines($cost, $accDep, $proceeds, ['asset' => $assetAcc, 'acc_dep' => fin_acct($pdo, '1300'),
        'proceeds' => (int)$d['proceeds_account_id'] ?: fin_acct($pdo, '111001'), 'gain' => fin_acct($pdo, '4310'), 'loss' => fin_acct($pdo, '534008')], $a['asset_code']);
    $code = $a['asset_code'];
    // a revaluation surplus left on a disposed asset moves inside equity to retained earnings
    if ($surplus > 0) {
        $lines[] = ['account_id' => fin_acct($pdo, '3400'), 'debit' => $surplus, 'desc' => "Revaluation surplus released $code"];
        $lines[] = ['account_id' => fin_acct($pdo, '3300'), 'credit' => $surplus, 'desc' => "Revaluation surplus released $code"];
    }
    if (count($lines) >= 2) am_post($pdo, ['date' => $d['disposal_date'], 'type' => 'asset_disposal', 'ref_id' => (int)$a['id'], 'description' => 'Asset disposal - ' . $a['name'], 'lines' => $lines]);

    $pdo->prepare("UPDATE assets SET status = 'disposed', disposal_date = ?, disposal_value = ?, disposal_method = ?, current_value = 0, custodian_id = NULL, reval_surplus = 0, reval_loss = 0 WHERE id = ?")
        ->execute([$d['disposal_date'], $proceeds, $d['method'], $a['id']]);
    // nothing may stay open on an asset that no longer exists
    $pdo->prepare("UPDATE asset_assignments SET status = 'returned', returned_date = ?, condition_in = 'Closed: asset disposed' WHERE asset_id = ? AND status = 'assigned'")->execute([$d['disposal_date'], $a['id']]);
    $pdo->prepare("UPDATE asset_maintenance SET status = 'cancelled' WHERE asset_id = ? AND status = 'scheduled'")->execute([$a['id']]);
    $pdo->prepare("UPDATE asset_work_orders SET status = 'cancelled', verify_note = 'Asset disposed' WHERE asset_id = ? AND status IN ('open','in_progress','awaiting_verification')")->execute([$a['id']]);
    $pdo->prepare("UPDATE asset_fault_requests SET status = 'rejected', closed_at = NOW() WHERE asset_id = ? AND status IN ('open','assigned')")->execute([$a['id']]);
    $pdo->prepare("UPDATE asset_transfers SET status = 'rejected', decided_at = NOW(), decision_note = 'Asset disposed' WHERE asset_id = ? AND status IN ('pending','approved')")->execute([$a['id']]);
    $pdo->prepare("UPDATE asset_disposals SET status = 'approved', decided_by = ?, decided_at = NOW(), decision_note = ?, gain_loss = ? WHERE id = ?")->execute([$uid ?: null, $note, $gain, $id]);
    $pdo->commit();
    if (function_exists('log_activity')) log_activity($pdo, 'approve', 'assets', "Disposal {$d['disposal_no']} approved: $code, gain/(loss) $gain", $id, $d['disposal_no']);
    success(['gain_loss' => $gain], 'Disposal approved and posted');
}

// ---------------------------------------------------------------- verification by QR / asset code
if ($action === 'verify_asset') {
    am_need($pdo, AM_OPERATE, 'Your role cannot record asset verification.');
    $assetId = (int)($input['asset_id'] ?? 0);
    $a = am_asset($pdo, $assetId, false);
    if (!am_live($a)) error('A disposed asset cannot be verified', 409);
    $found = am_text($input['location_found'] ?? '', 100);
    $cond = am_text($input['condition_found'] ?? '', 255);
    $match = (!$found || mb_strtolower($found) === mb_strtolower((string)$a['location'])) ? 1 : 0;
    $pdo->prepare("INSERT INTO asset_verifications (asset_id, verified_by, location_found, condition_found, matches_register) VALUES (?,?,?,?,?)")->execute([$assetId, $uid ?: null, $found, $cond, $match]);
    if (function_exists('log_activity')) log_activity($pdo, 'update', 'assets', "Asset {$a['asset_code']} physically verified" . ($match ? '' : " (found at $found, register says {$a['location']})"), $assetId, $a['asset_code']);
    success(['matches_register' => (bool)$match, 'register_location' => $a['location']], $match ? 'Verified' : 'Verified, but the location differs from the register');
}

// ---------------------------------------------------------------- equipment status and criticality
if ($action === 'equipment_set') {
    am_need($pdo, AM_OPERATE, 'Your role cannot change equipment status.');
    $assetId = (int)($input['asset_id'] ?? 0);
    $a = am_asset($pdo, $assetId, false);
    if (!am_live($a)) error('A disposed asset has no equipment status', 409);
    if (isset($input['is_critical'])) {
        $pdo->prepare("UPDATE assets SET is_critical = ? WHERE id = ?")->execute([!empty($input['is_critical']) ? 1 : 0, $assetId]);
        if (function_exists('log_activity')) log_activity($pdo, 'update', 'assets', "Asset {$a['asset_code']} " . (!empty($input['is_critical']) ? 'marked' : 'unmarked') . ' as critical equipment', $assetId, $a['asset_code']);
    }
    if (!empty($input['equipment_status'])) {
        $st = $input['equipment_status'];
        if (!in_array($st, ['operational', 'out_of_service'], true)) error('Only Operational or Out of service can be set by hand; the other states follow faults and work orders', 422);
        $pdo->prepare("UPDATE assets SET equipment_status = ? WHERE id = ?")->execute([$st, $assetId]);
        if ($st === 'operational') am_sync_status($pdo, $assetId);   // back to what the open faults and jobs say
        if (function_exists('log_activity')) log_activity($pdo, 'update', 'assets', "Asset {$a['asset_code']} equipment status set to $st", $assetId, $a['asset_code']);
    }
    success(null, 'Saved');
}

// ---------------------------------------------------------------- preventive schedule
if ($action === 'schedule_add') {
    am_need($pdo, AM_OPERATE, 'Your role cannot plan maintenance.');
    $assetId = (int)($input['asset_id'] ?? 0);
    $a = am_asset($pdo, $assetId, false);
    if (!am_live($a)) error('A disposed asset cannot be scheduled', 409);
    $kind = in_array($input['kind'] ?? '', ['preventive', 'inspection', 'repair'], true) ? $input['kind'] : 'preventive';
    $basis = in_array($input['basis'] ?? '', ['time', 'usage', 'manufacturer'], true) ? $input['basis'] : 'time';
    $due = am_date($input['due_date'] ?? null, 'Due date', true);
    $int = !empty($input['interval_days']) ? (int)$input['interval_days'] : null;
    if ($int !== null && ($int < 1 || $int > 3650)) error('The repeat interval must be between 1 and 3650 days', 422);
    $pdo->prepare("INSERT INTO asset_maintenance (asset_id, kind, basis, due_date, interval_days, notes, created_by) VALUES (?,?,?,?,?,?,?)")
        ->execute([$assetId, $kind, $basis, $due, $int, am_text($input['notes'] ?? '', 255), $uid ?: null]);
    $id = (int)$pdo->lastInsertId();
    if (function_exists('log_activity')) log_activity($pdo, 'create', 'assets', "Maintenance ($kind, $basis) scheduled for {$a['asset_code']} due $due", $id, $a['asset_code']);
    success(['id' => $id], 'Maintenance scheduled', 201);
}
if ($action === 'schedule_cancel') {
    am_need($pdo, AM_OPERATE, 'Your role cannot change the maintenance schedule.');
    $id = (int)($input['id'] ?? 0);
    $open = $pdo->prepare("SELECT wo_no FROM asset_work_orders WHERE maintenance_id = ? AND status NOT IN ('completed','cancelled')"); $open->execute([$id]);
    if ($no = $open->fetchColumn()) error("Work order $no is open for this schedule; cancel that first", 409);
    $n = $pdo->prepare("UPDATE asset_maintenance SET status = 'cancelled' WHERE id = ? AND status = 'scheduled'"); $n->execute([$id]);
    if (!$n->rowCount()) error('Only scheduled maintenance can be cancelled', 409);
    success(null, 'Cancelled');
}

// ---------------------------------------------------------------- fault / maintenance requests
if ($action === 'fault_report') {
    am_need($pdo, AM_OPERATE, 'Your role cannot report faults.');
    $assetId = (int)($input['asset_id'] ?? 0);
    $desc = am_text($input['description'] ?? '', 1000); if (!$desc) error('Describe the fault', 422);
    $pdo->beginTransaction();
    $a = am_asset($pdo, $assetId);
    if (!am_live($a)) am_fail($pdo, 'A disposed asset cannot be reported');
    // a fault on critical equipment (ventilators, oxygen, imaging, laboratory) is always top priority
    $prio = $a['is_critical'] ? 'critical' : am_priority($input['priority'] ?? 'medium');
    $no = next_doc_no($pdo, 'asset_fault_requests', 'request_no', 'FLT-' . date('Y') . '-');
    $pdo->prepare("INSERT INTO asset_fault_requests (request_no, asset_id, description, priority, reported_by) VALUES (?,?,?,?,?)")->execute([$no, $assetId, $desc, $prio, $uid ?: null]);
    $id = (int)$pdo->lastInsertId();
    am_sync_status($pdo, $assetId);
    $pdo->commit();
    if (function_exists('log_activity')) log_activity($pdo, 'create', 'assets', "Fault $no reported on {$a['asset_code']} ($prio)", $id, $no);
    success(['id' => $id, 'request_no' => $no, 'priority' => $prio], 'Fault reported', 201);
}
if ($action === 'fault_close') {
    am_need($pdo, AM_OPERATE, 'Your role cannot close fault requests.');
    $id = (int)($input['id'] ?? 0); $note = am_text($input['note'] ?? '', 255); if (!$note) error('Give the reason for closing without repair', 422);
    $pdo->beginTransaction();
    $q = $pdo->prepare("SELECT * FROM asset_fault_requests WHERE id = ? FOR UPDATE"); $q->execute([$id]); $f = $q->fetch();
    if (!$f) am_fail($pdo, 'Fault request not found', 404);
    if ($f['status'] !== 'open') am_fail($pdo, 'Only a fault with no work order can be closed this way (it is ' . $f['status'] . ')');
    $pdo->prepare("UPDATE asset_fault_requests SET status = 'rejected', closed_at = NOW(), description = LEFT(CONCAT(description, ?), 1000) WHERE id = ?")->execute([" [Closed: $note]", $id]);
    am_sync_status($pdo, (int)$f['asset_id']);
    $pdo->commit();
    if (function_exists('log_activity')) log_activity($pdo, 'update', 'assets', "Fault {$f['request_no']} closed without repair: $note", $id, $f['request_no']);
    success(null, 'Fault closed');
}

// ---------------------------------------------------------------- work orders
if ($action === 'wo_create') {
    am_need($pdo, AM_OPERATE, 'Your role cannot create work orders.');
    $faultId = !empty($input['fault_request_id']) ? (int)$input['fault_request_id'] : null;
    $maintId = !empty($input['maintenance_id']) ? (int)$input['maintenance_id'] : null;
    $emp = !empty($input['assigned_employee_id']) ? (int)$input['assigned_employee_id'] : null;
    $ext = am_text($input['external_provider'] ?? '', 150);
    if (!$emp && !$ext) error('Assign the job to a technician or an external service provider', 422);
    if ($emp) { $e = $pdo->prepare("SELECT 1 FROM employees WHERE id = ? AND status = 'active'"); $e->execute([$emp]); if (!$e->fetchColumn()) error('Choose an active technician', 422); }
    $pdo->beginTransaction();
    $assetId = (int)($input['asset_id'] ?? 0); $kind = 'repair'; $prio = am_priority($input['priority'] ?? 'medium');
    if ($faultId) {
        $q = $pdo->prepare("SELECT * FROM asset_fault_requests WHERE id = ? FOR UPDATE"); $q->execute([$faultId]); $f = $q->fetch();
        if (!$f) am_fail($pdo, 'Fault request not found', 404);
        if ($f['status'] !== 'open') am_fail($pdo, 'This fault already has a work order (it is ' . $f['status'] . ')');
        $assetId = (int)$f['asset_id']; $prio = $f['priority'];
    } elseif ($maintId) {
        $q = $pdo->prepare("SELECT * FROM asset_maintenance WHERE id = ? FOR UPDATE"); $q->execute([$maintId]); $m = $q->fetch();
        if (!$m || $m['status'] !== 'scheduled') am_fail($pdo, 'That maintenance is not scheduled', 404);
        $o = $pdo->prepare("SELECT wo_no FROM asset_work_orders WHERE maintenance_id = ? AND status NOT IN ('completed','cancelled')"); $o->execute([$maintId]);
        if ($no = $o->fetchColumn()) am_fail($pdo, "Work order $no is already open for this schedule");
        $assetId = (int)$m['asset_id']; $kind = $m['kind'];
    } else {
        $kind = in_array($input['kind'] ?? '', ['preventive', 'inspection', 'repair'], true) ? $input['kind'] : 'repair';
    }
    $a = am_asset($pdo, $assetId);
    if (!am_live($a)) am_fail($pdo, 'A disposed asset cannot have a work order');
    $no = next_doc_no($pdo, 'asset_work_orders', 'wo_no', 'WO-' . date('Y') . '-');
    $pdo->prepare("INSERT INTO asset_work_orders (wo_no, asset_id, fault_request_id, maintenance_id, kind, priority, assigned_employee_id, external_provider, created_by) VALUES (?,?,?,?,?,?,?,?,?)")
        ->execute([$no, $assetId, $faultId, $maintId, $kind, $prio, $emp, $ext, $uid ?: null]);
    $id = (int)$pdo->lastInsertId();
    if ($faultId) $pdo->prepare("UPDATE asset_fault_requests SET status = 'assigned' WHERE id = ?")->execute([$faultId]);
    am_sync_status($pdo, $assetId);
    $pdo->commit();
    if (function_exists('log_activity')) log_activity($pdo, 'create', 'assets', "Work order $no created for {$a['asset_code']} ($kind, $prio)", $id, $no);
    success(['id' => $id, 'wo_no' => $no], 'Work order created', 201);
}

if ($action === 'wo_start') {
    am_need($pdo, AM_OPERATE, 'Your role cannot update work orders.');
    $n = $pdo->prepare("UPDATE asset_work_orders SET status = 'in_progress' WHERE id = ? AND status = 'open'"); $n->execute([(int)($input['id'] ?? 0)]);
    if (!$n->rowCount()) error('Only an open work order can be started', 409);
    success(null, 'Work started');
}

if ($action === 'wo_record') {
    am_need($pdo, AM_OPERATE, 'Your role cannot record work.');
    $id = (int)($input['id'] ?? 0);
    $findings = am_text($input['findings'] ?? '', 1000); $done = am_text($input['work_done'] ?? '', 1000);
    if (!$findings || !$done) error('Record the inspection findings and the work performed', 422);
    $cost = round((float)($input['cost'] ?? 0), 2); if ($cost < 0) error('Cost cannot be negative', 422);
    $down = round((float)($input['downtime_hours'] ?? 0), 2); if ($down < 0 || $down > 100000) error('Downtime must be zero or more hours', 422);
    $pay = !empty($input['pay_account_id']) ? (int)$input['pay_account_id'] : null;
    if ($cost > 0) { if (!$pay) error('Say which cash, bank or mobile-money account paid for the work', 422); if (!am_cash_ok($pdo, $pay)) error('Paid from must be a cash, bank or mobile-money account', 422); }
    $pdo->beginTransaction();
    $q = $pdo->prepare("SELECT * FROM asset_work_orders WHERE id = ? FOR UPDATE"); $q->execute([$id]); $w = $q->fetch();
    if (!$w) am_fail($pdo, 'Work order not found', 404);
    if (!in_array($w['status'], ['open', 'in_progress'], true)) am_fail($pdo, 'This work order is ' . $w['status']);
    $pdo->prepare("UPDATE asset_work_orders SET status = 'awaiting_verification', findings = ?, work_done = ?, parts_used = ?, cost = ?, pay_account_id = ?, downtime_hours = ?, work_done_by = ?, work_done_at = NOW() WHERE id = ?")
        ->execute([$findings, $done, am_text($input['parts_used'] ?? '', 500), $cost, $cost > 0 ? $pay : null, $down, $uid ?: null, $id]);
    am_sync_status($pdo, (int)$w['asset_id']);
    $pdo->commit();
    if (function_exists('log_activity')) log_activity($pdo, 'update', 'assets', "Work order {$w['wo_no']} finished, awaiting verification", $id, $w['wo_no']);
    success(null, 'Work recorded; waiting for verification');
}

if ($action === 'wo_verify') {
    am_need($pdo, AM_VERIFY_WORK, 'Your role cannot verify repaired equipment.');
    $id = (int)($input['id'] ?? 0); $decision = $input['decision'] ?? '';
    if (!in_array($decision, ['approve', 'reject'], true)) error('Decision must be approve or reject', 422);
    $note = am_text($input['note'] ?? '', 500);
    if ($decision === 'reject' && !$note) error('Say what is still wrong', 422);
    $pdo->beginTransaction();
    $q = $pdo->prepare("SELECT * FROM asset_work_orders WHERE id = ? FOR UPDATE"); $q->execute([$id]); $w = $q->fetch();
    if (!$w) am_fail($pdo, 'Work order not found', 404);
    if ($w['status'] !== 'awaiting_verification') am_fail($pdo, 'Only finished work can be verified (it is ' . $w['status'] . ')');
    if (($m = ctl_conflict($pdo, 'verify', ['did the work on' => $w['work_done_by']]))) am_fail($pdo, $m, 403);
    $assetId = (int)$w['asset_id'];
    if ($decision === 'reject') {
        $pdo->prepare("UPDATE asset_work_orders SET status = 'in_progress', verify_note = ? WHERE id = ?")->execute([$note, $id]);
        am_sync_status($pdo, $assetId);
        $pdo->commit();
        if (function_exists('log_activity')) log_activity($pdo, 'reject', 'assets', "Work order {$w['wo_no']} sent back: $note", $id, $w['wo_no']);
        success(null, 'Sent back to the technician');
    }
    $a = am_asset($pdo, $assetId);
    $cost = round((float)$w['cost'], 2);
    // the cost is booked only once the work is accepted: Dr repairs & maintenance, Cr the account that paid
    if ($cost > 0) am_post($pdo, ['date' => date('Y-m-d'), 'type' => 'maintenance', 'ref_id' => 'WO' . $id, 'description' => "Maintenance ({$w['kind']}) {$w['wo_no']} - {$a['name']}",
        'lines' => [['account_id' => fin_acct($pdo, '534010'), 'debit' => $cost, 'desc' => "{$w['wo_no']} {$a['name']}"], ['account_id' => (int)$w['pay_account_id'], 'credit' => $cost, 'desc' => 'Paid']]]);
    $pdo->prepare("UPDATE asset_work_orders SET status = 'completed', verified_by = ?, verified_at = NOW(), verify_note = ? WHERE id = ?")->execute([$uid ?: null, $note, $id]);
    if ($w['fault_request_id']) $pdo->prepare("UPDATE asset_fault_requests SET status = 'closed', closed_at = NOW() WHERE id = ?")->execute([$w['fault_request_id']]);
    if ($w['maintenance_id']) {
        $q = $pdo->prepare("SELECT * FROM asset_maintenance WHERE id = ?"); $q->execute([$w['maintenance_id']]); $m = $q->fetch();
        $pdo->prepare("UPDATE asset_maintenance SET status = 'done', done_date = CURDATE(), cost = ?, pay_account_id = ? WHERE id = ?")->execute([$cost, $w['pay_account_id'], $w['maintenance_id']]);
        if ($m && $m['interval_days']) $pdo->prepare("INSERT INTO asset_maintenance (asset_id, kind, basis, due_date, interval_days, created_by) VALUES (?,?,?,DATE_ADD(CURDATE(), INTERVAL ? DAY),?,?)")
            ->execute([$m['asset_id'], $m['kind'], $m['basis'], (int)$m['interval_days'], (int)$m['interval_days'], $uid ?: null]);
    }
    am_sync_status($pdo, $assetId);
    $pdo->commit();
    if (function_exists('log_activity')) log_activity($pdo, 'approve', 'assets', "Work order {$w['wo_no']} verified; {$a['asset_code']} back in use", $id, $w['wo_no']);
    success(null, 'Verified; the equipment is back in use');
}

if ($action === 'wo_cancel') {
    am_need($pdo, AM_OPERATE, 'Your role cannot cancel work orders.');
    $id = (int)($input['id'] ?? 0); $note = am_text($input['note'] ?? '', 255); if (!$note) error('Give the reason for cancelling', 422);
    $pdo->beginTransaction();
    $q = $pdo->prepare("SELECT * FROM asset_work_orders WHERE id = ? FOR UPDATE"); $q->execute([$id]); $w = $q->fetch();
    if (!$w) am_fail($pdo, 'Work order not found', 404);
    if (!in_array($w['status'], ['open', 'in_progress'], true)) am_fail($pdo, 'Only an open work order can be cancelled (it is ' . $w['status'] . ')');
    $pdo->prepare("UPDATE asset_work_orders SET status = 'cancelled', verify_note = ? WHERE id = ?")->execute([$note, $id]);
    if ($w['fault_request_id']) $pdo->prepare("UPDATE asset_fault_requests SET status = 'open' WHERE id = ? AND status = 'assigned'")->execute([$w['fault_request_id']]);
    am_sync_status($pdo, (int)$w['asset_id']);
    $pdo->commit();
    if (function_exists('log_activity')) log_activity($pdo, 'update', 'assets', "Work order {$w['wo_no']} cancelled: $note", $id, $w['wo_no']);
    success(null, 'Work order cancelled');
}

error('Unknown action', 404);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($e->getCode() === '23000') error('Another request changed this asset at the same time; reload and try again', 409);
    error(strpos($e->getMessage(), 'Accounting period is closed') !== false ? 'That accounting period is closed' : 'Database error', 500);
} catch (InvalidArgumentException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error($e->getMessage(), 422);
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error($e->getMessage(), 409);
}

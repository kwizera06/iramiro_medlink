<?php
// handlers/inventory/drug-reports.php — Drug Issues & Movements and Drug Performance.
// Included by inventory-management.php inside its GET branch (uses its success()/error() helpers, $pdo, $action).
// Issue = stock leaving a store for a department request, a requisition, a prescription or a POS sale.

function dr_clinic_store_id(PDO $pdo): int {
    static $id = null;
    if ($id !== null) return $id;
    $id = 0;
    try {
        $v = $pdo->query("SELECT setting_value FROM hospital_settings WHERE setting_key='clinical_store_id' LIMIT 1")->fetchColumn();
        if ($v && (int)$v > 0) return $id = (int)$v;
        $v = $pdo->query("SELECT id FROM stores WHERE is_active=1 AND (UPPER(code)='CLINIC' OR name LIKE '%clinic%') ORDER BY (UPPER(code)='CLINIC') DESC, id LIMIT 1")->fetchColumn();
        if ($v) $id = (int)$v;
    } catch (Throwable $e) {}
    return $id;
}
function dr_is_pharmacist(): bool {
    return in_array('pharmacist', $_SESSION['user_data']['role_slugs'] ?? [], true);
}

// Dropdown data for the two drug reports
if ($action === "report_filters") {
    success(['departments' => $pdo->query("SELECT id, name FROM departments WHERE is_active=1 ORDER BY name")->fetchAll(),
             'users' => $pdo->query("SELECT id, TRIM(CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,''))) AS name FROM users WHERE is_active=1 ORDER BY first_name, last_name LIMIT 1000")->fetchAll(),
             'clinic_store_id' => dr_clinic_store_id($pdo)]);
}

if ($action === "stock_issues" || $action === "drug_performance") {
    $isPh = dr_is_pharmacist();
    $hasRm = (bool)$pdo->query("SHOW TABLES LIKE 'request_management'")->fetchColumn(); // department requests table is optional in this install
        $storeId = isset($_GET['store_id']) && $_GET['store_id'] !== '' ? (int)$_GET['store_id'] : dr_clinic_store_id($pdo);
        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-d');
        foreach ([$from, $to] as $d) {
            $dt = DateTime::createFromFormat('Y-m-d', (string)$d);
            if (!$dt || $dt->format('Y-m-d') !== $d) error("Invalid date, expected YYYY-MM-DD", 422);
        }
        $where = ["sm.reference_type IN ('request_management','requisition','prescription','pharmacy_sale')", "sm.movement_type <> 'transfer_in'", "DATE(sm.created_at) BETWEEN ? AND ?"];
        $params = [$from, $to];
        if ($storeId) { $where[] = "sm.from_store_id = ?"; $params[] = $storeId; }
        if ($isPh) $where[] = "i.is_medication = 1";
        elseif (($_GET['medication'] ?? '') !== '') { $where[] = "i.is_medication = ?"; $params[] = (int)$_GET['medication']; }
        if (!empty($_GET['item_id'])) { $where[] = "sm.item_id = ?"; $params[] = (int)$_GET['item_id']; }
        if (!empty($_GET['category_id'])) { $where[] = "i.category_id = ?"; $params[] = (int)$_GET['category_id']; }
        $qty = "ABS(sm.quantity)";
        $deptExpr = $hasRm ? "COALESCE(rm.department_id, rq.department_id)" : "rq.department_id";
        $reqExpr = $hasRm ? "COALESCE(rm.requested_by, rq.requested_by, rx.doctor_id, ps.sold_by)" : "COALESCE(rq.requested_by, rx.doctor_id, ps.sold_by)";
        $apprExpr = $hasRm ? "COALESCE(rm.approved_by, rq.reviewed_by)" : "rq.reviewed_by";
        $from_sql = "FROM stock_movements sm
            JOIN items i ON i.id = sm.item_id
            LEFT JOIN item_categories ic ON ic.id = i.category_id
            LEFT JOIN stock_batches b ON b.id = sm.batch_id
            " . ($hasRm ? "LEFT JOIN request_management rm ON sm.reference_type = 'request_management' AND rm.id = sm.reference_id" : "") . "
            LEFT JOIN requisitions rq ON sm.reference_type = 'requisition' AND rq.id = sm.reference_id
            LEFT JOIN pharmacy_prescriptions rx ON sm.reference_type = 'prescription' AND rx.id = sm.reference_id
            LEFT JOIN pharmacy_sales ps ON sm.reference_type = 'pharmacy_sale' AND ps.id = sm.reference_id
            LEFT JOIN patients pt ON pt.id = COALESCE(rx.patient_id, NULLIF(ps.patient_id, 0))
            LEFT JOIN departments dp ON dp.id = $deptExpr";
        if (!empty($_GET['department_id'])) { $where[] = "$deptExpr = ?"; $params[] = (int)$_GET['department_id']; }
        if (!empty($_GET['requester_id'])) { $where[] = "$reqExpr = ?"; $params[] = (int)$_GET['requester_id']; }
        $ws = 'WHERE ' . implode(' AND ', $where);
        $uname = fn($a) => "TRIM(CONCAT(COALESCE($a.first_name,''),' ',COALESCE($a.last_name,'')))";
    
        if ($action === "stock_issues") {
            $st = $pdo->prepare("SELECT sm.id, sm.created_at, sm.reference_type, i.id AS item_id, i.name AS item_name, ic.name AS category_name, b.batch_number,
                    $qty AS qty, COALESCE(sm.unit_cost,0) AS unit_cost, $qty * COALESCE(sm.unit_cost,0) AS value,
                    $deptExpr AS department_id, dp.name AS department_name,
                    CASE WHEN pt.id IS NOT NULL THEN CONCAT(pt.first_name,' ',pt.last_name,' (',pt.patient_no,')') WHEN sm.reference_type = 'pharmacy_sale' THEN 'Walk-in sale' ELSE dp.name END AS target,
                    $reqExpr AS requester_id, " . $uname('ru') . " AS requested_by, " . $uname('au') . " AS approved_by, " . $uname('iu') . " AS issued_by
                $from_sql
                LEFT JOIN users ru ON ru.id = $reqExpr
                LEFT JOIN users au ON au.id = $apprExpr
                LEFT JOIN users iu ON iu.id = sm.created_by
                $ws ORDER BY sm.created_at DESC, sm.id DESC LIMIT 3000");
            $st->execute($params);
            $rows = $st->fetchAll();
            $tot = ['by_item' => [], 'by_department' => [], 'by_requester' => [], 'qty' => 0, 'value' => 0];
            foreach ($rows as $r) {
                foreach ([['by_item', $r['item_name']], ['by_department', $r['target'] ?: '—'], ['by_requester', $r['requested_by'] ?: '—']] as [$k, $label]) {
                    $tot[$k][$label] = $tot[$k][$label] ?? ['label' => $label, 'qty' => 0, 'value' => 0];
                    $tot[$k][$label]['qty'] += (float)$r['qty']; $tot[$k][$label]['value'] += (float)$r['value'];
                }
                $tot['qty'] += (float)$r['qty']; $tot['value'] += (float)$r['value'];
            }
            foreach (['by_item', 'by_department', 'by_requester'] as $k) { $tot[$k] = array_values($tot[$k]); usort($tot[$k], fn($a, $b) => $b['value'] <=> $a['value'] ?: $b['qty'] <=> $a['qty']); }
            success(['rows' => $rows, 'totals' => $tot, 'store_id' => $storeId, 'truncated' => count($rows) >= 3000]);
        }
    
        // drug_performance
        $agg = function (string $group, string $label, string $extraWhere = '') use ($pdo, $from_sql, $ws, $params, $qty) {
            $st = $pdo->prepare("SELECT $group AS gid, $label AS label, SUM($qty) AS qty, SUM($qty * COALESCE(sm.unit_cost,0)) AS value, COUNT(DISTINCT sm.reference_type, sm.reference_id) AS events
                $from_sql $ws $extraWhere GROUP BY $group ORDER BY value DESC, qty DESC LIMIT 15");
            $st->execute($params);
            return $st->fetchAll();
        };
        $top = $agg("sm.item_id", "MAX(i.name)");
        $byDept = $agg($deptExpr, "MAX(dp.name)", "AND $deptExpr IS NOT NULL");
        $stP = $pdo->prepare("SELECT rx.doctor_id AS gid, MAX(" . $uname('du') . ") AS label, SUM($qty) AS qty, SUM($qty * COALESCE(sm.unit_cost,0)) AS value, COUNT(DISTINCT rx.id) AS events
            $from_sql LEFT JOIN users du ON du.id = rx.doctor_id $ws AND sm.reference_type = 'prescription' AND rx.doctor_id IS NOT NULL GROUP BY rx.doctor_id ORDER BY value DESC, qty DESC LIMIT 15");
        $stP->execute($params);
        $byPrescriber = $stP->fetchAll();
    
        // Slow / non-moving: stock on hand in the store with no issue in the period (last issue date shown)
        $slowSt = $pdo->prepare("SELECT i.id AS item_id, i.name AS item_name, SUM(b.quantity) AS on_hand, SUM(b.quantity * COALESCE(b.unit_cost,0)) AS on_hand_value,
                (SELECT MAX(DATE(m.created_at)) FROM stock_movements m WHERE m.item_id = i.id AND m.reference_type IN ('request_management','requisition','prescription','pharmacy_sale') AND m.movement_type <> 'transfer_in') AS last_issue,
                (SELECT COALESCE(SUM(ABS(m.quantity)),0) FROM stock_movements m WHERE m.item_id = i.id AND m.reference_type IN ('request_management','requisition','prescription','pharmacy_sale') AND m.movement_type <> 'transfer_in' AND DATE(m.created_at) BETWEEN ? AND ?) AS issued_in_period
            FROM stock_batches b JOIN items i ON i.id = b.item_id
            WHERE b.is_active = 1 AND b.quantity > 0" . ($storeId ? " AND b.store_id = " . (int)$storeId : "") . ($isPh ? " AND i.is_medication = 1" : "") . "
            GROUP BY i.id HAVING issued_in_period = 0 ORDER BY on_hand_value DESC LIMIT 50");
        $slowSt->execute([$from, $to]);
        $slow = $slowSt->fetchAll();
    
        // Expiry risk: batches with stock that expire within 90 days (or already expired)
        $expSt = $pdo->prepare("SELECT i.name AS item_name, b.batch_number, b.quantity, b.expiry_date, DATEDIFF(b.expiry_date, CURDATE()) AS days_left, b.quantity * COALESCE(b.unit_cost,0) AS value
            FROM stock_batches b JOIN items i ON i.id = b.item_id
            WHERE b.is_active = 1 AND b.quantity > 0 AND b.expiry_date IS NOT NULL AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)" . ($storeId ? " AND b.store_id = " . (int)$storeId : "") . ($isPh ? " AND i.is_medication = 1" : "") . "
            ORDER BY b.expiry_date LIMIT 100");
        $expSt->execute();
        success(['top' => $top, 'by_department' => $byDept, 'by_prescriber' => $byPrescriber, 'slow' => $slow, 'expiry' => $expSt->fetchAll(), 'store_id' => $storeId]);
}

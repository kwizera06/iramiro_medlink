<?php
if (!defined('MEDLINK_API') && PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
// handlers/procedures/procedures.php
// Dedicated handler for procedure requests, completions, and activity logging
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/finance_posting.php';
require_once __DIR__ . '/../../includes/activity_log.php';
require_once __DIR__ . '/../../includes/record_lock.php';
require_once __DIR__ . '/../../includes/stock.php';
require_once __DIR__ . '/../../includes/billing-calc.php';
require_once __DIR__ . '/../../includes/request_schema.php';
if (!$pdo->inTransaction()) record_lock_schema($pdo);

function jsonResponse($d, $s = 200) { http_response_code($s); echo json_encode($d); exit(); }
function success($d = null, $m = 'Success', $s = 200) { jsonResponse(['success' => true, 'message' => $m, 'data' => $d], $s); }
function error($m, $s = 400) { jsonResponse(['success' => false, 'message' => $m], $s); }

// Synchronize invoice totals, balances, and GL journal entry lines after changes/deletions
function syncInvoiceAndJournal(PDO $pdo, int $invoiceId): void {
    if (!$invoiceId) return;
    
    $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM invoice_items WHERE invoice_id = ?");
    $sumStmt->execute([$invoiceId]);
    $subtotal = (float)$sumStmt->fetchColumn();

    $invStmt = $pdo->prepare("
        SELECT i.id, i.visit_id, i.patient_id, i.insurance_id, i.discount, i.tax, i.paid_amount,
               COALESCE(
                   (SELECT ip.copayment_percentage FROM patient_insurance pi JOIN insurance_packages ip ON ip.id = pi.package_id WHERE pi.patient_id = p.id AND (pi.id = i.insurance_id OR pi.id = v.insurance_id OR pi.company_id = i.insurance_id OR pi.company_id = v.insurance_id) ORDER BY pi.is_primary DESC LIMIT 1),
                   (SELECT ip.copayment_percentage FROM patient_insurance pi JOIN insurance_packages ip ON ip.id = pi.package_id WHERE pi.patient_id = p.id AND pi.is_primary = 1 LIMIT 1),
                   (SELECT ip.copayment_percentage FROM insurance_packages ip WHERE (ip.company_id = i.insurance_id OR ip.company_id = v.insurance_id) AND ip.status = 'active' LIMIT 1),
                   100
               ) AS copayment_percentage
        FROM invoices i
        JOIN patients p ON p.id = i.patient_id
        JOIN visits v ON v.id = i.visit_id
        WHERE i.id = ?
    ");
    $invStmt->execute([$invoiceId]);
    $inv = $invStmt->fetch();
    if (!$inv) return;

    $discount = (float)($inv['discount'] ?? 0);
    $tax = (float)($inv['tax'] ?? 0);
    $total = max(0, $subtotal - $discount + $tax);
    $paidAmount = (float)($inv['paid_amount'] ?? 0);
    $copayPct = (float)$inv['copayment_percentage'];

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM invoice_items WHERE invoice_id = ?");
    $countStmt->execute([$invoiceId]);
    $remainCount = (int)$countStmt->fetchColumn();

    if ($remainCount === 0 || $total <= 0) {
        fin_void_invoice($pdo, $invoiceId);
        $pdo->prepare("DELETE FROM payment_records WHERE invoice_id=?")->execute([$invoiceId]);
        $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id=?")->execute([$invoiceId]);
        $pdo->prepare("DELETE FROM invoices WHERE id=?")->execute([$invoiceId]);
        return;
    }

    $patientDue = fin_split($total, $copayPct)['patient'];
    $balance = max(0, $patientDue - $paidAmount);
    $status = $balance <= 0 && $total > 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'issued');

    $pdo->prepare("UPDATE invoices SET subtotal=?, total=?, balance=?, status=? WHERE id=?")
        ->execute([$subtotal, $total, $balance, $status, $invoiceId]);

    fin_post_invoice_safe($pdo, $invoiceId);
}

// What a nurse did (procedure / medication / consumables / sample), shown in the doctor's patient file with the nurse's name.
// The doctor is optional. Table from migrate.php.
function nurseRecord(PDO $pdo, int $visitId, int $patientId, ?int $doctorId, string $kind, string $summary): int {
    if ($doctorId) {
        $dq = $pdo->prepare("SELECT 1 FROM users u LEFT JOIN user_roles ur ON ur.user_id = u.id LEFT JOIN roles r ON r.id = ur.role_id WHERE u.id = ? AND u.is_active = 1 AND (r.slug = 'doctor' OR u.role = 'doctor') LIMIT 1");
        $dq->execute([$doctorId]);
        if (!$dq->fetchColumn()) throw new RuntimeException('Selected doctor not found');
    }
    $pdo->prepare("INSERT INTO nurse_doctor_links (visit_id, patient_id, doctor_id, nurse_id, kind, summary) VALUES (?,?,?,?,?,?)")
        ->execute([$visitId, $patientId, $doctorId, $_SESSION['user_id'] ?? null, $kind, mb_substr($summary, 0, 2000)]);
    return (int)$pdo->lastInsertId();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {

// ============================================================
// GET — list procedure orders / activity log
// ============================================================
if ($method === 'GET') {

    // GET ?action=doctors — active doctors for the nurse's doctor picker
    if ($action === 'doctors') {
        success($pdo->query("SELECT DISTINCT u.id, CONCAT('Dr. ',u.first_name,' ',u.last_name) AS name FROM users u
            LEFT JOIN user_roles ur ON ur.user_id = u.id LEFT JOIN roles r ON r.id = ur.role_id
            WHERE u.is_active = 1 AND (r.slug = 'doctor' OR u.role = 'doctor') ORDER BY name")->fetchAll());
    }

    // GET ?action=activity_log&module=procedures&limit=20&record_id=X
    if ($action === 'activity_log') {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $isProcAdmin = ($_SESSION['user_data']['role'] ?? '') === 'admin'
            || in_array('admin', $_SESSION['user_data']['role_slugs'] ?? [])
            || ($_SESSION['user_role'] ?? '') === 'admin';
        if (!$isProcAdmin) {
            error('Access denied: Administrator role required', 403);
        }
        $module    = $_GET['module'] ?? 'procedures';
        $recordId  = isset($_GET['record_id']) ? (int)$_GET['record_id'] : 0;
        $visitId   = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
        $limit     = min(100, max(5, (int)($_GET['limit'] ?? 20)));
        $dateFrom  = !empty($_GET['date_from']) ? trim($_GET['date_from']) : date('Y-m-d');
        $dateTo    = !empty($_GET['date_to'])   ? trim($_GET['date_to'])   : date('Y-m-d');

        $where  = ["a.module = ?", "a.created_at >= ? AND a.created_at < DATE_ADD(?, INTERVAL 1 DAY)"];
        $params = [$module, $dateFrom, $dateTo];

        if ($recordId) { $where[] = "a.record_id = ?"; $params[] = $recordId; }

        $ws = 'WHERE ' . implode(' AND ', $where);
        $stmt = $pdo->prepare(
            "SELECT a.id, a.user_id, a.user_name, a.action, a.module, a.description,
                    a.record_id, a.record_ref, a.ip_address, a.created_at
             FROM activity_log a
             $ws
             ORDER BY a.created_at DESC
             LIMIT $limit"
        );
        $stmt->execute($params);
        success($stmt->fetchAll());
    }

    // GET ?action=list — list procedure orders (medical_orders with order_type='procedure')
    if ($action === 'list' || !$action) {
        $visitId  = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
        $status   = $_GET['status'] ?? '';
        $dateFrom = $_GET['date_from'] ?? date('Y-m-d');
        $dateTo   = $_GET['date_to']   ?? date('Y-m-d');
        $search   = trim($_GET['search'] ?? '');
        $page     = max(1, (int)($_GET['page'] ?? 1));
        $limit    = min(100, max(10, (int)($_GET['limit'] ?? 20)));
        $offset   = ($page - 1) * $limit;

        $where  = ["mo.order_type = 'procedure'", "mo.ordered_at >= ? AND mo.ordered_at < DATE_ADD(?, INTERVAL 1 DAY)"];
        $params = [$dateFrom, $dateTo];

        if ($visitId) { $where[] = "mo.visit_id = ?"; $params[] = $visitId; }
        if ($status)  { $where[] = "mo.status = ?";  $params[] = $status; }
        if ($search)  {
            $like = "%$search%";
            $where[] = "(mo.description LIKE ? OR CONCAT(p.last_name,' ',p.first_name) LIKE ? OR CONCAT(p.first_name,' ',p.last_name) LIKE ? OR p.patient_no LIKE ?)";
            $params = array_merge($params, [$like, $like, $like, $like]);
        }

        $ws = 'WHERE ' . implode(' AND ', $where);

        $cnt = $pdo->prepare("SELECT COUNT(*) FROM medical_orders mo LEFT JOIN patients p ON p.id=mo.patient_id $ws");
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT mo.id, mo.visit_id, mo.patient_id, mo.order_type, mo.description,
                   mo.details, mo.status, mo.result, mo.ordered_at, mo.completed_at,
                   COALESCE(CONCAT(u.first_name,' ',u.last_name), u.username) AS ordered_by_name,
                   COALESCE(CONCAT(cb.first_name,' ',cb.last_name), cb.username) AS completed_by_name,
                   CONCAT(p.first_name,' ',p.last_name) AS patient_name,
                   p.patient_no, v.visit_no
            FROM medical_orders mo
            LEFT JOIN patients p ON p.id = mo.patient_id
            LEFT JOIN visits v ON v.id = mo.visit_id
            LEFT JOIN users u ON u.id = mo.ordered_by
            LEFT JOIN users cb ON cb.id = mo.completed_by
            $ws
            ORDER BY mo.ordered_at DESC
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute($params);
        $orders = $stmt->fetchAll();

        $stats = $pdo->prepare("
            SELECT
                SUM(status='ordered') AS pending,
                SUM(status='in_progress') AS in_progress,
                SUM(status='completed') AS completed,
                COUNT(*) AS total
            FROM medical_orders
            WHERE order_type='procedure' AND ordered_at >= ? AND ordered_at < DATE_ADD(?, INTERVAL 1 DAY)
        ");
        $stats->execute([$dateFrom, $dateTo]);

        success([
            'orders'     => $orders,
            'stats'      => $stats->fetch(),
            'pagination' => [
                'total'       => $total,
                'page'        => $page,
                'limit'       => $limit,
                'total_pages' => (int)ceil($total / max($limit, 1))
            ]
        ]);
    }

    error('Unknown action', 404);
}

// ============================================================
// POST — create / request a procedure
// ============================================================
if ($method === 'POST') {
    $input = $GLOBALS['_parsed_input'] ?? json_decode(file_get_contents('php://input'), true) ?? [];

    // POST ?action=request — request a new procedure
    if ($action === 'request' || !$action) {
        $visitId   = (int)($input['visit_id'] ?? 0);
        $patientId = (int)($input['patient_id'] ?? 0);
        $procedure = trim($input['description'] ?? $input['procedure_name'] ?? '');
        $notes     = trim($input['notes'] ?? '');
        $priority  = $input['priority'] ?? 'routine';

        if (!$visitId)   error('visit_id is required', 422);
        if (!$patientId) error('patient_id is required', 422);
        if (!$procedure) error('Procedure description is required', 422);

        // Get consultation_id if exists
        $cStmt = $pdo->prepare("SELECT id FROM consultations WHERE visit_id=? LIMIT 1");
        $cStmt->execute([$visitId]);
        $consultId = $cStmt->fetchColumn() ?: null;

        $stmt = $pdo->prepare("
            INSERT INTO medical_orders
                (consultation_id, visit_id, patient_id, order_type, description, details, status, ordered_at, ordered_by)
            VALUES (?, ?, ?, 'procedure', ?, ?, 'ordered', NOW(), ?)
        ");
        $stmt->execute([
            $consultId, $visitId, $patientId,
            $procedure,
            $notes . ($priority !== 'routine' ? ' [Priority: ' . $priority . ']' : ''),
            $_SESSION['user_id'] ?? null
        ]);
        $orderId = (int)$pdo->lastInsertId();

        // Fetch patient name for log
        $pStmt = $pdo->prepare("SELECT CONCAT(first_name,' ',last_name) AS name, patient_no FROM patients WHERE id=?");
        $pStmt->execute([$patientId]);
        $pat = $pStmt->fetch();

        log_activity($pdo, 'CREATE', 'procedures',
            'Procedure requested: "' . $procedure . '" for patient ' . ($pat['name'] ?? '#' . $patientId) .
            ' (Visit: ' . $visitId . ($priority !== 'routine' ? ', Priority: ' . $priority : '') . ')',
            $orderId, $pat['patient_no'] ?? null
        );

        success(['id' => $orderId], 'Procedure requested successfully', 201);
    }

    // POST ?action=link_doctor — nurse records a procedure / prescription / sample against a doctor (shown in that patient's file)
    if ($action === 'link_doctor') {
        $kind = $input['kind'] ?? '';
        $visitId = (int)($input['visit_id'] ?? 0);
        $patientId = (int)($input['patient_id'] ?? 0);
        $doctorId = (int)($input['doctor_id'] ?? 0);
        $summary = trim($input['summary'] ?? '');
        if (!in_array($kind, ['procedure', 'prescription', 'sample', 'consumable'], true)) error('Invalid kind', 422);
        if (!$visitId || !$patientId) error('visit_id and patient_id are required', 422);
        if ($summary === '') error('Nothing to record', 422);
        $vq = $pdo->prepare("SELECT 1 FROM visits WHERE id = ? AND patient_id = ?");
        $vq->execute([$visitId, $patientId]);
        if (!$vq->fetchColumn()) error('Visit not found for this patient', 404);
        try { $id = nurseRecord($pdo, $visitId, $patientId, $doctorId ?: null, $kind, $summary); }
        catch (RuntimeException $e) { error($e->getMessage(), 422); }
        success(['id' => $id], 'Recorded in the patient file', 201);
    }

    // POST ?action=nurse_use — nurse records medication/consumables given: deduct stock now (IPD store if admitted, else OPD) and bill, no pharmacy prescription
    if ($action === 'nurse_use') {
        $visitId = (int)($input['visit_id'] ?? 0);
        $patientId = (int)($input['patient_id'] ?? 0);
        $lines = array_filter((array)($input['items'] ?? []), fn($l) => (int)($l['item_id'] ?? 0) && (int)($l['quantity'] ?? 0) >= 1);
        if (!$visitId || !$patientId) error('visit_id and patient_id are required', 422);
        if (!$lines) error('Add at least one item (quantity must be a whole number of 1 or more)', 422);
        $vq = $pdo->prepare("SELECT v.status, v.payment_type, v.insurance_id, COALESCE(pi.company_id, v.insurance_id) AS company_id FROM visits v LEFT JOIN patient_insurance pi ON pi.id = v.insurance_id AND pi.patient_id = v.patient_id WHERE v.id = ? AND v.patient_id = ?");
        $vq->execute([$visitId, $patientId]);
        $visit = $vq->fetch();
        if (!$visit) error('Visit not found for this patient', 404);
        if (in_array($visit['status'], ['cancelled', 'completed'], true)) error('Visit is closed', 409);
        record_require_unlocked($pdo, 'visit', $visitId);
        $uid = $_SESSION['user_id'] ?? null;
        require_once __DIR__ . '/../../includes/finance_schema.php';
        $hasItemCol = (bool)$pdo->query("SHOW COLUMNS FROM invoice_items LIKE 'item_id'")->fetch();   // drug ids go in item_id when the column exists
        $pdo->beginTransaction();
        $adm = $pdo->prepare("SELECT 1 FROM bed_assignments WHERE visit_id = ? AND status = 'active' LIMIT 1");
        $adm->execute([$visitId]);
        $storeId = dept_store_id($pdo, $adm->fetchColumn() ? 4 : 3);   // departments: 3 = OPD, 4 = IPD
        if (!$storeId) { $pdo->rollBack(); error('Department store not found', 500); }

        $foreign = visitForeignTariff($pdo, $visitId);
        $company = (!$foreign && $visit['insurance_id'] && in_array($visit['payment_type'], ['insurance', 'mixed'], true)) ? (int)$visit['company_id'] : 0;
        $copay = $company ? getPatientCopayPercentage($pdo, $patientId, $company) : 100.0;
        if ($company && $copay >= 100 && $visit['payment_type'] !== 'insurance') $company = 0;

        $iq = $pdo->prepare("SELECT name, selling_price, is_medication FROM items WHERE id = ? AND is_active = 1");
        $ip = $pdo->prepare("SELECT price FROM item_insurance_prices WHERE item_id = ? AND insurance_id = ? LIMIT 1");
        $invoiceNo = null; $invoiceId = 0;   // created with the first billable line; a line with bill=false only deducts stock
        $ii = $pdo->prepare("INSERT INTO invoice_items (invoice_id," . ($hasItemCol ? 'item_id' : 'service_id') . ",description,category,quantity,unit_price,insurance_price,patient_topup,apply_insurance,discount,total) VALUES (?,?,?,?,?,?,0,0,0,0,?)");
        $names = [];
        foreach ($lines as $l) {
            $itemId = (int)$l['item_id']; $qty = (int)$l['quantity'];
            $iq->execute([$itemId]);
            $it = $iq->fetch();
            if (!$it) { $pdo->rollBack(); error("Item #$itemId not found", 404); }
            $price = (float)$it['selling_price'];
            if ($company) { $ip->execute([$itemId, $company]); if (($ins = $ip->fetchColumn()) !== false) $price = (float)$ins; }
            stock_consume($pdo, $itemId, $qty, $storeId, 'nurse_use', $visitId, 'Nurse use — visit #' . $visitId, $uid);
            if (array_key_exists('bill', $l) && !filter_var($l['bill'], FILTER_VALIDATE_BOOLEAN)) { $names[] = $it['name'] . ' x' . $qty . ' (not billed)'; continue; }
            if (!$invoiceId) {
                $invoiceNo = generateInvoiceNo($pdo);
                $pdo->prepare("INSERT INTO invoices (invoice_no,visit_id,patient_id,payment_type,insurance_id,subtotal,total,balance,status,notes,created_by,created_at) VALUES (?,?,?,?,?,0,0,0,'draft','Nurse medication / consumables',?,NOW())")
                    ->execute([$invoiceNo, $visitId, $patientId, $company ? 'insurance' : 'self_pay', $company ?: null, $uid]);
                $invoiceId = (int)$pdo->lastInsertId();
            }
            $isMed = (int)$it['is_medication'] === 1;
            $ii->execute([$invoiceId, $itemId, ($isMed ? 'Medication — ' : 'Consumable — ') . $it['name'], 'pharmacy', $qty, $price, $price * $qty]);
            $names[] = $it['name'] . ' x' . $qty;
        }
        if ($invoiceId) recalcInvoice($pdo, $invoiceId);
        // Same transaction: the stock, the bill and what the doctor sees in the patient file succeed or fail together
        $kind = ($input['kind'] ?? '') === 'consumable' ? 'consumable' : 'prescription';
        $summary = trim((string)($input['summary'] ?? '')) ?: implode(', ', $names);
        if (trim((string)($input['notes'] ?? '')) !== '') $summary .= ' - ' . trim($input['notes']);
        try { nurseRecord($pdo, $visitId, $patientId, (int)($input['doctor_id'] ?? 0) ?: null, $kind, $summary); }
        catch (RuntimeException $e) { $pdo->rollBack(); error($e->getMessage(), 422); }
        $pdo->commit();
        log_activity($pdo, 'CREATE', 'procedures', 'Nurse used: ' . implode(', ', $names) . " (Visit: $visitId, Invoice: " . ($invoiceNo ?: 'none') . ")", $invoiceId ?: null, $invoiceNo);
        success(['invoice_no' => $invoiceNo], 'Recorded', 201);
    }

    // POST ?action=start — mark procedure as in_progress
    if ($action === 'start') {
        $orderId = (int)($input['order_id'] ?? $id ?? 0);
        if (!$orderId) error('order_id required', 422);

        $chk = $pdo->prepare("SELECT mo.id, mo.status, mo.description, p.first_name, p.last_name, p.patient_no FROM medical_orders mo LEFT JOIN patients p ON p.id=mo.patient_id WHERE mo.id=? AND mo.order_type='procedure'");
        $chk->execute([$orderId]);
        $order = $chk->fetch();
        if (!$order) error('Procedure order not found', 404);
        if ($order['status'] !== 'ordered') error('Only an ordered procedure can be started (current status: ' . $order['status'] . ').', 409);

        $pdo->prepare("UPDATE medical_orders SET status='in_progress' WHERE id=?")->execute([$orderId]);

        log_activity($pdo, 'UPDATE', 'procedures',
            'Procedure started: "' . $order['description'] . '" for ' . $order['first_name'] . ' ' . $order['last_name'],
            $orderId, $order['patient_no']
        );

        success(['id' => $orderId], 'Procedure started');
    }

    // POST ?action=complete — mark procedure as completed + enter result
    if ($action === 'complete') {
        $orderId = (int)($input['order_id'] ?? $id ?? 0);
        $result  = trim($input['result'] ?? '');
        $notes   = trim($input['notes'] ?? '');
        if (!$orderId) error('order_id required', 422);

        $chk = $pdo->prepare("SELECT mo.id, mo.status, mo.description, p.first_name, p.last_name, p.patient_no FROM medical_orders mo LEFT JOIN patients p ON p.id=mo.patient_id WHERE mo.id=? AND mo.order_type='procedure'");
        $chk->execute([$orderId]);
        $order = $chk->fetch();
        if (!$order) error('Procedure order not found', 404);
        if ($order['status'] !== 'in_progress') error('Start the procedure before completing it (current status: ' . $order['status'] . ').', 409);
        if ($result === '') error('Record the findings / result before completing the procedure.', 422);

        $pdo->prepare("UPDATE medical_orders SET status='completed', result=?, details=CONCAT(COALESCE(details,''),' Notes: ',?), completed_at=NOW(), completed_by=? WHERE id=?")
            ->execute([$result, $notes, $_SESSION['user_id'] ?? null, $orderId]);

        log_activity($pdo, 'UPDATE', 'procedures',
            'Procedure completed: "' . $order['description'] . '" for ' . $order['first_name'] . ' ' . $order['last_name'] .
            ($result ? '. Result: ' . substr($result, 0, 80) : ''),
            $orderId, $order['patient_no']
        );

        success(['id' => $orderId], 'Procedure marked as completed');
    }

    // POST ?action=cancel — cancel a procedure order
    if ($action === 'cancel') {
        $orderId = (int)($input['order_id'] ?? $id ?? 0);
        $reason  = trim($input['reason'] ?? '');
        if (!$orderId) error('order_id required', 422);

        $chk = $pdo->prepare("SELECT mo.id, mo.status, mo.description, p.first_name, p.last_name, p.patient_no FROM medical_orders mo LEFT JOIN patients p ON p.id=mo.patient_id WHERE mo.id=? AND mo.order_type='procedure'");
        $chk->execute([$orderId]);
        $order = $chk->fetch();
        if (!$order) error('Procedure order not found', 404);
        if (in_array($order['status'], ['completed', 'cancelled'], true)) error('A ' . $order['status'] . ' procedure cannot be cancelled.', 409);

        $pdo->prepare("UPDATE medical_orders SET status='cancelled' WHERE id=?")->execute([$orderId]);

        log_activity($pdo, 'DELETE', 'procedures',
            'Procedure cancelled: "' . $order['description'] . '" for ' . $order['first_name'] . ' ' . $order['last_name'] .
            ($reason ? '. Reason: ' . $reason : ''),
            $orderId, $order['patient_no']
        );

        success(['id' => $orderId], 'Procedure cancelled');
    }

    // POST ?action=direct_delete — delete order/item immediately without permission, sync invoice & GL journal
    if ($action === 'direct_delete') {
        // Everyone except admins and cashiers/finance must go through Deletion Requests; admin approval executes the removal
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $rs = $pdo->prepare("SELECT GROUP_CONCAT(r.slug) FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ?");
        $rs->execute([$uid]);
        $slugs = explode(',', (string)$rs->fetchColumn());
        if (!array_intersect($slugs, ['admin','administrator','accountant','cashier','finance']) && empty($_SESSION['user_data']['is_admin'])) error('Removal must be requested; an administrator has to approve it', 403);
        $entityType = $input['entity_type'] ?? '';
        $entityId   = (int)($input['entity_id'] ?? 0);
        $visitId    = (int)($input['visit_id'] ?? 0);

        if (!$entityType || !$entityId) error('entity_type and entity_id required', 422);

        $pdo->beginTransaction();
        try {
            $deletedDesc = '';
            $patientNo = null;

            if ($entityType === 'procedure' || $entityType === 'item') {
                // Check if entityId is invoice_items.id
                $iiStmt = $pdo->prepare("
                    SELECT ii.*, i.id AS inv_id, i.visit_id, p.patient_no, p.first_name, p.last_name 
                    FROM invoice_items ii 
                    JOIN invoices i ON i.id = ii.invoice_id 
                    JOIN patients p ON p.id = i.patient_id 
                    WHERE ii.id = ?
                ");
                $iiStmt->execute([$entityId]);
                $invItem = $iiStmt->fetch();

                if ($invItem) {
                    $invId = (int)$invItem['inv_id'];
                    $vId = (int)$invItem['visit_id'];
                    $deletedDesc = $invItem['description'];
                    $patientNo = $invItem['patient_no'];

                    // Delete invoice item
                    $pdo->prepare("DELETE FROM invoice_items WHERE id = ?")->execute([$entityId]);

                    // If it was a procedure/lab/radiology order, remove from medical_orders too
                    if ($invItem['category'] === 'procedure') {
                        $pdo->prepare("DELETE FROM medical_orders WHERE visit_id = ? AND order_type = 'procedure' AND (description = ? OR description LIKE ?)")
                            ->execute([$vId, $deletedDesc, '%' . $deletedDesc . '%']);
                    } elseif ($invItem['category'] === 'lab') {
                        $pdo->prepare("DELETE FROM medical_orders WHERE visit_id = ? AND order_type = 'lab' AND (description = ? OR description LIKE ?)")
                            ->execute([$vId, $deletedDesc, '%' . $deletedDesc . '%']);
                    } elseif ($invItem['category'] === 'radiology') {
                        $pdo->prepare("DELETE FROM medical_orders WHERE visit_id = ? AND order_type = 'radiology' AND (description = ? OR description LIKE ?)")
                            ->execute([$vId, $deletedDesc, '%' . $deletedDesc . '%']);
                    }

                    // Recalculate invoice & sync journal entry
                    syncInvoiceAndJournal($pdo, $invId);
                } else {
                    // Check medical_orders table
                    $moStmt = $pdo->prepare("SELECT mo.*, p.patient_no, p.first_name, p.last_name FROM medical_orders mo JOIN patients p ON p.id = mo.patient_id WHERE mo.id = ?");
                    $moStmt->execute([$entityId]);
                    $mo = $moStmt->fetch();
                    if ($mo) {
                        $deletedDesc = $mo['description'];
                        $patientNo = $mo['patient_no'];
                        $vId = (int)$mo['visit_id'];

                        $pdo->prepare("DELETE FROM medical_orders WHERE id = ?")->execute([$entityId]);

                        // Clean up matching invoice item if any
                        $invItemStmt = $pdo->prepare("
                            SELECT ii.id, ii.invoice_id 
                            FROM invoice_items ii 
                            JOIN invoices i ON i.id = ii.invoice_id 
                            WHERE i.visit_id = ? AND (ii.description = ? OR ii.description LIKE ?)
                        ");
                        $invItemStmt->execute([$vId, $deletedDesc, '%' . $deletedDesc . '%']);
                        $matched = $invItemStmt->fetchAll();
                        foreach ($matched as $mi) {
                            $pdo->prepare("DELETE FROM invoice_items WHERE id = ?")->execute([(int)$mi['id']]);
                            syncInvoiceAndJournal($pdo, (int)$mi['invoice_id']);
                        }
                    } else {
                        error('Item not found', 404);
                    }
                }
            } elseif ($entityType === 'prescription') {
                // entityId is pharmacy_prescriptions.id
                $rxStmt = $pdo->prepare("SELECT rx.*, p.patient_no, p.first_name, p.last_name FROM pharmacy_prescriptions rx JOIN patients p ON p.id = rx.patient_id WHERE rx.id = ?");
                $rxStmt->execute([$entityId]);
                $rx = $rxStmt->fetch();
                if (!$rx) error('Prescription not found', 404);

                $deletedDesc = $rx['prescription_no'];
                $patientNo = $rx['patient_no'];
                $vId = (int)$rx['visit_id'];

                // Get prescription items to match drug names
                $piStmt = $pdo->prepare("SELECT pi.*, i.name AS drug_name FROM pharmacy_prescription_items pi LEFT JOIN items i ON i.id = pi.item_id WHERE pi.prescription_id = ?");
                $piStmt->execute([$entityId]);
                $rxItems = $piStmt->fetchAll();

                // Delete prescription items & prescription
                $pdo->prepare("DELETE FROM pharmacy_prescription_items WHERE prescription_id = ?")->execute([$entityId]);
                $pdo->prepare("DELETE FROM pharmacy_prescriptions WHERE id = ?")->execute([$entityId]);

                // Find matching invoice items for this visit
                foreach ($rxItems as $rxi) {
                    $medName = $rxi['drug_name'] ?: $rxi['instructions'];
                    $invItemStmt = $pdo->prepare("
                        SELECT ii.id, ii.invoice_id 
                        FROM invoice_items ii 
                        JOIN invoices i ON i.id = ii.invoice_id 
                        WHERE i.visit_id = ? AND ii.category IN ('medication', 'pharmacy', 'drug')
                          AND (ii.service_id = ? OR ii.description = ? OR ii.description LIKE ?)
                    ");
                    $invItemStmt->execute([$vId, (int)$rxi['item_id'], $medName, '%' . $medName . '%']);
                    $matched = $invItemStmt->fetchAll();
                    foreach ($matched as $mi) {
                        $pdo->prepare("DELETE FROM invoice_items WHERE id = ?")->execute([(int)$mi['id']]);
                        syncInvoiceAndJournal($pdo, (int)$mi['invoice_id']);
                    }
                }
            } elseif ($entityType === 'lab' || $entityType === 'lab_order') {
                // entityId is medical_orders.id
                $moStmt = $pdo->prepare("SELECT mo.*, p.patient_no, p.first_name, p.last_name FROM medical_orders mo JOIN patients p ON p.id = mo.patient_id WHERE mo.id = ?");
                $moStmt->execute([$entityId]);
                $mo = $moStmt->fetch();
                if (!$mo) error('Lab order not found', 404);

                $deletedDesc = $mo['description'];
                $patientNo = $mo['patient_no'];
                $vId = (int)$mo['visit_id'];

                $pdo->prepare("DELETE FROM medical_orders WHERE id = ?")->execute([$entityId]);

                // Find matching invoice item
                $invItemStmt = $pdo->prepare("
                    SELECT ii.id, ii.invoice_id 
                    FROM invoice_items ii 
                    JOIN invoices i ON i.id = ii.invoice_id 
                    WHERE i.visit_id = ? AND ii.category = 'lab'
                      AND (ii.description = ? OR ii.description LIKE ?)
                ");
                $invItemStmt->execute([$vId, $deletedDesc, '%' . $deletedDesc . '%']);
                $matched = $invItemStmt->fetchAll();
                foreach ($matched as $mi) {
                    $pdo->prepare("DELETE FROM invoice_items WHERE id = ?")->execute([(int)$mi['id']]);
                    syncInvoiceAndJournal($pdo, (int)$mi['invoice_id']);
                }
            }

            $pdo->commit();

            log_activity($pdo, 'DELETE', 'procedures',
                'Deleted ' . $entityType . ': "' . $deletedDesc . '" and synced invoice & journal entries',
                $entityId, $patientNo
            );

            success(['id' => $entityId], 'Item deleted and invoice/journal synced successfully');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error('Failed to delete item: ' . $e->getMessage(), 500);
        }
    }

    error('Unknown action', 404);
}

// ============================================================
// PUT — update a procedure order
// ============================================================
if ($method === 'PUT') {
    if (!$id) error('Procedure order ID required', 422);
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $chk = $pdo->prepare("SELECT mo.id, mo.status, mo.description, p.first_name, p.last_name, p.patient_no FROM medical_orders mo LEFT JOIN patients p ON p.id=mo.patient_id WHERE mo.id=? AND mo.order_type='procedure'");
    $chk->execute([$id]);
    $order = $chk->fetch();
    if (!$order) error('Procedure order not found', 404);
    if (!empty($order['visit_id'])) record_require_unlocked($pdo, 'visit', (int)$order['visit_id']);

    $fields = []; $params = [];
    $allowed = ['status', 'result', 'details', 'completed_at'];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $input)) { $fields[] = "$f = ?"; $params[] = $input[$f]; }
    }
    if (isset($input['status']) && $input['status'] === 'completed') {
        $fields[] = 'completed_at = NOW()';
        $fields[] = 'completed_by = ?';
        $params[] = $_SESSION['user_id'] ?? null;
    }
    if (!$fields) error('Nothing to update', 422);

    $params[] = $id;
    $pdo->prepare("UPDATE medical_orders SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);

    $newStatus = $input['status'] ?? 'updated';
    log_activity($pdo, 'UPDATE', 'procedures',
        'Procedure updated: "' . $order['description'] . '" → status: ' . $newStatus . ' for ' . $order['first_name'] . ' ' . $order['last_name'],
        $id, $order['patient_no']
    );

    success(['id' => $id], 'Procedure updated');
}

// ============================================================
// DELETE — delete a procedure order
// ============================================================
if ($method === 'DELETE') {
    if (!$id) error('Procedure order ID required', 422);

    $chk = $pdo->prepare("SELECT mo.id, mo.visit_id, mo.description, p.first_name, p.last_name, p.patient_no FROM medical_orders mo LEFT JOIN patients p ON p.id=mo.patient_id WHERE mo.id=? AND mo.order_type='procedure'");
    $chk->execute([$id]);
    $order = $chk->fetch();
    if (!$order) error('Procedure order not found', 404);

    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM medical_orders WHERE id = ?")->execute([$id]);

        // Clean up matching invoice items and sync
        if (!empty($order['visit_id'])) {
            $invItemStmt = $pdo->prepare("
                SELECT ii.id, ii.invoice_id 
                FROM invoice_items ii 
                JOIN invoices i ON i.id = ii.invoice_id 
                WHERE i.visit_id = ? AND ii.category = 'procedure' AND (ii.description = ? OR ii.description LIKE ?)
            ");
            $invItemStmt->execute([(int)$order['visit_id'], $order['description'], '%' . $order['description'] . '%']);
            $matched = $invItemStmt->fetchAll();
            foreach ($matched as $mi) {
                record_require_unlocked($pdo, 'invoice', (int)$mi['invoice_id']);
                $pdo->prepare("DELETE FROM invoice_items WHERE id = ?")->execute([(int)$mi['id']]);
                syncInvoiceAndJournal($pdo, (int)$mi['invoice_id']);
            }
        }

        $pdo->commit();

        log_activity($pdo, 'DELETE', 'procedures',
            'Procedure order deleted: "' . $order['description'] . '" for ' . $order['first_name'] . ' ' . $order['last_name'],
            $id, $order['patient_no']
        );

        success(null, 'Procedure order deleted and invoice/journal synced');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error('Failed to delete procedure: ' . $e->getMessage(), 500);
    }
}

error('Method not allowed', 405);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error('Server error: ' . $e->getMessage(), 500);
}
?>

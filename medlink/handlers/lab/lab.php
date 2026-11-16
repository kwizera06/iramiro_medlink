<?php
if (!defined('MEDLINK_API') && PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
// handlers/lab/lab.php
// Dedicated handler for lab test requests, sample collection, results entry, and activity logging
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/lab_rules.php';
lab_ensure_schema($pdo);
require_once __DIR__ . '/../../includes/activity_log.php';
require_once __DIR__ . '/../../includes/record_lock.php';
if (!$pdo->inTransaction()) record_lock_schema($pdo);

function jsonResponse($d, $s = 200) { http_response_code($s); echo json_encode($d); exit(); }
function success($d = null, $m = 'Success', $s = 200) { jsonResponse(['success' => true, 'message' => $m, 'data' => $d], $s); }
function error($m, $s = 400) { jsonResponse(['success' => false, 'message' => $m], $s); }

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {

// ============================================================
// GET
// ============================================================
if ($method === 'GET') {

    // GET ?action=activity_log — fetch activity log for lab module
    if ($action === 'activity_log') {
        $module   = $_GET['module'] ?? 'lab';
        $recordId = isset($_GET['record_id']) ? (int)$_GET['record_id'] : 0;
        $limit    = min(100, max(5, (int)($_GET['limit'] ?? 20)));
        $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
        $dateTo   = $_GET['date_to']   ?? date('Y-m-d');

        $where  = ["a.module = ?", "a.created_at >= ? AND a.created_at < DATE_ADD(?, INTERVAL 1 DAY)"];
        $params = [$module, $dateFrom, $dateTo];
        if ($recordId) { $where[] = "a.record_id = ?"; $params[] = $recordId; }

        $ws   = 'WHERE ' . implode(' AND ', $where);
        $stmt = $pdo->prepare(
            "SELECT a.id, a.user_id, a.user_name, a.action, a.module, a.description,
                    a.record_id, a.record_ref, a.ip_address, a.created_at
             FROM activity_log a $ws
             ORDER BY a.created_at DESC
             LIMIT $limit"
        );
        $stmt->execute($params);
        success($stmt->fetchAll());
    }

    // GET ?action=orders — list lab orders (medical_orders where order_type='lab')
    if ($action === 'orders' || !$action) {
        $visitId  = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
        $status   = $_GET['status'] ?? '';
        $dateFrom = $_GET['date_from'] ?? date('Y-m-d');
        $dateTo   = $_GET['date_to']   ?? date('Y-m-d');
        $search   = trim($_GET['search'] ?? '');
        $page     = max(1, (int)($_GET['page'] ?? 1));
        $limit    = min(100, max(10, (int)($_GET['limit'] ?? 20)));
        $offset   = ($page - 1) * $limit;

        $where  = [
            "mo.order_type = 'lab'",
            "mo.ordered_at >= ? AND mo.ordered_at < DATE_ADD(?, INTERVAL 1 DAY)"
        ];
        $params = [$dateFrom, $dateTo];

        if ($visitId) { $where[] = "mo.visit_id = ?"; $params[] = $visitId; }
        if ($status)  { $where[] = "mo.status = ?";   $params[] = $status; }
        if ($search)  {
            $like    = "%$search%";
            $where[] = "(mo.description LIKE ? OR CONCAT(p.last_name,' ',p.first_name) LIKE ? OR CONCAT(p.first_name,' ',p.last_name) LIKE ? OR p.patient_no LIKE ?)";
            $params  = array_merge($params, [$like, $like, $like, $like]);
        }

        $ws  = 'WHERE ' . implode(' AND ', $where);
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM medical_orders mo LEFT JOIN patients p ON p.id=mo.patient_id $ws");
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT mo.id, mo.visit_id, mo.patient_id, mo.order_type, mo.description,
                   mo.details, mo.status, mo.result, mo.ordered_at, mo.completed_at,
                   COALESCE(CONCAT(u.first_name,' ',u.last_name), u.username) AS ordered_by_name,
                   COALESCE(CONCAT(cb.first_name,' ',cb.last_name), cb.username) AS completed_by_name,
                   CONCAT(p.first_name,' ',p.last_name) AS patient_name,
                   p.patient_no, p.gender, p.date_of_birth, p.phone AS patient_phone, p.address AS patient_address,
                   v.visit_no,
                   ic.name AS insurance_name
            FROM medical_orders mo
            LEFT JOIN patients p ON p.id = mo.patient_id
            LEFT JOIN visits v ON v.id = mo.visit_id
            LEFT JOIN patient_insurance pi ON pi.id = (SELECT pi9.id FROM patient_insurance pi9 WHERE pi9.patient_id = p.id ORDER BY (pi9.id = v.insurance_id) DESC, (pi9.company_id = v.insurance_id) DESC, pi9.is_primary DESC, pi9.id DESC LIMIT 1)
            LEFT JOIN insurance_companies ic ON ic.id = pi.company_id
            LEFT JOIN users u ON u.id = mo.ordered_by
            LEFT JOIN users cb ON cb.id = mo.completed_by
            $ws
            ORDER BY mo.ordered_at DESC
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute($params);
        $orders = $stmt->fetchAll();

        $statsStmt = $pdo->prepare("
            SELECT
                SUM(status='ordered') AS pending,
                SUM(status='sample_collected') AS sample_collected,
                SUM(status='in_progress') AS in_progress,
                SUM(status='completed') AS completed,
                SUM(status='cancelled') AS cancelled,
                COUNT(*) AS total
            FROM medical_orders
            WHERE order_type='lab'
              AND ordered_at >= ? AND ordered_at < DATE_ADD(?, INTERVAL 1 DAY)
        ");
        $statsStmt->execute([$dateFrom, $dateTo]);

        success([
            'orders'     => $orders,
            'stats'      => $statsStmt->fetch(),
            'pagination' => [
                'total'       => $total,
                'page'        => $page,
                'limit'       => $limit,
                'total_pages' => (int)ceil($total / max($limit, 1))
            ]
        ]);
    }

    // GET ?action=single&id=X — get single order with details
    if ($action === 'single' && $id) {
        $stmt = $pdo->prepare("
            SELECT mo.*, CONCAT(p.first_name,' ',p.last_name) AS patient_name,
                   p.patient_no, p.gender, p.date_of_birth, p.phone AS patient_phone, p.address AS patient_address,
                   v.visit_no,
                   COALESCE(CONCAT(u.first_name,' ',u.last_name), u.username) AS ordered_by_name,
                   COALESCE(CONCAT(cb.first_name,' ',cb.last_name), cb.username) AS completed_by_name
            FROM medical_orders mo
            LEFT JOIN patients p ON p.id = mo.patient_id
            LEFT JOIN visits v ON v.id = mo.visit_id
            LEFT JOIN users u ON u.id = mo.ordered_by
            LEFT JOIN users cb ON cb.id = mo.completed_by
            WHERE mo.id = ?
        ");
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        if (!$order) error('Lab order not found', 404);
        success($order);
    }

    // GET ?action=statistics&date_from&date_to&group=test|day|month — lab orders counted per test / day / month by order date
    if ($action === 'statistics') {
        $from  = $_GET['date_from'] ?? date('Y-m-01');
        $to    = $_GET['date_to']   ?? date('Y-m-d');
        $group = $_GET['group'] ?? 'test';
        $expr  = ['test' => 'mo.description', 'day' => 'DATE(mo.ordered_at)', 'month' => "DATE_FORMAT(mo.ordered_at, '%Y-%m')"][$group] ?? null;
        if (!$expr) error('Invalid group', 422);
        $st = $pdo->prepare("SELECT $expr AS label, COUNT(*) AS total,
                SUM(mo.status = 'completed') AS completed,
                SUM(mo.status = 'in_progress') AS in_progress,
                SUM(mo.status = 'ordered') AS pending,
                SUM(mo.status IN ('cancelled','no_sample')) AS cancelled
            FROM medical_orders mo
            WHERE mo.order_type = 'lab' AND mo.ordered_at >= ? AND mo.ordered_at < DATE_ADD(?, INTERVAL 1 DAY)
            GROUP BY label ORDER BY " . ($group === 'test' ? 'total DESC, label' : 'label'));
        $st->execute([$from, $to]);
        success($st->fetchAll());
    }

    if ($action === 'special_reports') {
        $type = $_GET['report_type'] ?? '';
        $allowed = ['pathology', 'microbiology', 'blood-bank'];
        if (!in_array($type, $allowed, true)) error('Invalid laboratory report type', 422);
        $st = $pdo->prepare("SELECT r.*, CONCAT(p.first_name, ' ', p.last_name) AS patient_name, p.patient_no
            FROM lab_special_reports r LEFT JOIN patients p ON p.id = r.patient_id
            WHERE r.report_type = ? ORDER BY r.report_date DESC, r.id DESC");
        $st->execute([$type]);
        success($st->fetchAll());
    }

    error('Unknown action', 404);
}

// ============================================================
// POST — create / update lab orders
// ============================================================
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    if ($action === 'special_report') {
        $type = $input['report_type'] ?? '';
        if (!in_array($type, ['pathology', 'microbiology', 'blood-bank'], true)) error('Invalid laboratory report type', 422);
        $patientId = (int)($input['patient_id'] ?? 0);
        $reportDate = trim((string)($input['report_date'] ?? date('Y-m-d')));
        $payload = $input['payload'] ?? [];
        if (!$patientId || !is_array($payload)) error('Patient and report data are required', 422);
        $st = $pdo->prepare("INSERT INTO lab_special_reports (report_type, patient_id, report_date, payload, created_by) VALUES (?,?,?,?,?)");
        $st->execute([$type, $patientId, $reportDate, json_encode($payload, JSON_UNESCAPED_UNICODE), (int)($_SESSION['user_id'] ?? 0) ?: null]);
        success(['id' => (int)$pdo->lastInsertId()], 'Laboratory report saved', 201);
    }

    // POST ?action=request — request a new lab test
    if ($action === 'request' || !$action) {
        $visitId   = (int)($input['visit_id'] ?? 0);
        $patientId = (int)($input['patient_id'] ?? 0);
        $testName  = trim($input['test_name'] ?? $input['description'] ?? '');
        $notes     = trim($input['notes'] ?? '');
        $priority  = $input['priority'] ?? 'routine';

        if (!$visitId)   error('visit_id is required', 422);
        if (!$patientId) error('patient_id is required', 422);
        if (!$testName)  error('Test name is required', 422);

        $labSpecialities = ['Bacteriology','BIOCHEMISTRY','Hematology','Immuno-Serology','SEROLOGY','Parasitology','Molecular','Anatomical Pathology','Blood Transfusion'];
        $chkStmt = $pdo->prepare("SELECT speciality FROM service_catalog WHERE LOWER(TRIM(procedure_name)) = LOWER(TRIM(?)) LIMIT 1");
        $chkStmt->execute([$testName]);
        $spec = $chkStmt->fetchColumn();
        if ($spec && !in_array($spec, $labSpecialities)) {
            error('"' . $testName . '" is listed under ' . $spec . ' and cannot be ordered as a laboratory test.', 422);
        }

        $cStmt = $pdo->prepare("SELECT id FROM consultations WHERE visit_id=? LIMIT 1");
        $cStmt->execute([$visitId]);
        $consultId = $cStmt->fetchColumn() ?: null;

        $stmt = $pdo->prepare("
            INSERT INTO medical_orders
                (consultation_id, visit_id, patient_id, order_type, description, details, status, ordered_at, ordered_by)
            VALUES (?, ?, ?, 'lab', ?, ?, 'ordered', NOW(), ?)
        ");
        $stmt->execute([
            $consultId, $visitId, $patientId,
            $testName,
            $notes . ($priority !== 'routine' ? ' [Priority: ' . $priority . ']' : ''),
            $_SESSION['user_id'] ?? null
        ]);
        $orderId = (int)$pdo->lastInsertId();

        $pStmt = $pdo->prepare("SELECT CONCAT(first_name,' ',last_name) AS name, patient_no FROM patients WHERE id=?");
        $pStmt->execute([$patientId]);
        $pat = $pStmt->fetch();

        log_activity($pdo, 'CREATE', 'lab',
            'Lab test requested: "' . $testName . '" for ' . ($pat['name'] ?? '#' . $patientId) .
            ' (visit #' . $visitId . ($priority !== 'routine' ? ', Priority: ' . $priority : '') . ')',
            $orderId, $pat['patient_no'] ?? null
        );

        success(['id' => $orderId], 'Lab test requested', 201);
    }

    // POST ?action=collect_sample — mark sample as collected
    if ($action === 'collect_sample') {
        $orderId       = (int)($input['order_id'] ?? $id ?? 0);
        $collectedBy   = trim($input['collected_by'] ?? '');
        $collectionNote = trim($input['notes'] ?? '');
        if (!$orderId) error('order_id required', 422);

        $chk = $pdo->prepare("SELECT mo.id, mo.status, mo.description, p.first_name, p.last_name, p.patient_no FROM medical_orders mo LEFT JOIN patients p ON p.id=mo.patient_id WHERE mo.id=? AND mo.order_type='lab'");
        $chk->execute([$orderId]);
        $order = $chk->fetch();
        if (!$order) error('Lab order not found', 404);

        $detailsNote = 'Sample collected' . ($collectedBy ? ' by ' . $collectedBy : '') . ($collectionNote ? ': ' . $collectionNote : '');
        $pdo->prepare("UPDATE medical_orders SET status='in_progress', collected_by=:u, collected_at=NOW(), details=CONCAT(COALESCE(details,''),' | ',:note) WHERE id=:id AND status IN ('ordered','in_progress')")
            ->execute([':note' => $detailsNote, ':u' => $_SESSION['user_id'] ?? null, ':id' => $orderId]);

        log_activity($pdo, 'UPDATE', 'lab',
            'Sample collected for test: "' . $order['description'] . '" — ' . $order['first_name'] . ' ' . $order['last_name'] .
            ($collectedBy ? ' (by ' . $collectedBy . ')' : ''),
            $orderId, $order['patient_no']
        );

        success(['id' => $orderId], 'Sample marked as collected');
    }

    // POST ?action=submit_all — body {orders:[{order_id, result}]}: each order is validated like a single submit and saved in its own transaction;
    // one order failing never blocks the others. Returns [{order_id, success, message}].
    if ($action === 'submit_all') {
        $orders = is_array($input['orders'] ?? null) ? $input['orders'] : [];
        if (!$orders) error('No orders selected', 422);
        $GLOBALS['lab_bulk'] = true;
        $res = [];
        foreach ($orders as $o) {
            $orderId = (int)($o['order_id'] ?? 0);
            $result  = trim((string)($o['result'] ?? ''));
            try {
                if (!$orderId) throw new RuntimeException('order_id required');
                if ($result === '') throw new RuntimeException('Result text required');
                lab_result_guard($pdo, $orderId, 'completed', false);
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE medical_orders SET result = ?, status = 'completed', completed_at = NOW(), completed_by = ? WHERE id = ?")
                    ->execute([$result, $_SESSION['user_id'] ?? null, $orderId]);
                $info = $pdo->prepare("SELECT mo.description, mo.visit_id, mo.patient_id, CONCAT(p.first_name,' ',p.last_name) AS patient_name FROM medical_orders mo LEFT JOIN patients p ON p.id = mo.patient_id WHERE mo.id = ?");
                $info->execute([$orderId]);
                $i = $info->fetch();
                log_activity($pdo, 'UPDATE', 'lab', 'Lab result entered (Submit All): "' . ($i['description'] ?? 'Order #' . $orderId) . '" for ' . ($i['patient_name'] ?? 'patient') . ' | Result: ' . substr($result, 0, 80), $orderId, null);
                // Same "all lab tests of the visit are done" notice as a single submit
                if (!empty($i['visit_id'])) {
                    $tot = $pdo->prepare("SELECT COUNT(*), SUM(status = 'completed') FROM medical_orders WHERE visit_id = ? AND order_type IN ('lab','radiology')");
                    $tot->execute([$i['visit_id']]);
                    [$total, $done] = array_map('intval', $tot->fetch(PDO::FETCH_NUM));
                    if ($total > 0 && $done >= $total) {
                        $ex = $pdo->prepare("SELECT id FROM notifications WHERE visit_id = ? AND type = 'lab_ready' AND is_read = 0");
                        $ex->execute([$i['visit_id']]);
                        if (!$ex->fetch()) {
                            $pdo->prepare("INSERT INTO notifications (type, title, message, visit_id, patient_id, target_role) VALUES ('lab_ready', ?, ?, ?, ?, 'nurse')")
                                ->execute(['Lab Results Ready', "All $total lab test(s) for " . ($i['patient_name'] ?? 'Patient') . " are completed and ready for review.", $i['visit_id'], $i['patient_id']]);
                        }
                    }
                }
                $pdo->commit();
                $res[] = ['order_id' => $orderId, 'success' => true, 'message' => 'Saved'];
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $res[] = ['order_id' => $orderId, 'success' => false, 'message' => $e->getMessage()];
            }
        }
        $GLOBALS['lab_bulk'] = false;
        success($res, count(array_filter($res, fn($r) => $r['success'])) . ' of ' . count($res) . ' submitted');
    }

    // POST ?action=enter_result — enter lab result
    if ($action === 'enter_result') {
        $orderId = (int)($input['order_id'] ?? $id ?? 0);
        $result  = trim($input['result'] ?? '');
        $notes   = trim($input['notes'] ?? '');
        $status  = $input['status'] ?? 'completed';
        if (!$orderId) error('order_id required', 422);
        if (!$result)  error('Result is required', 422);
        lab_result_guard($pdo, $orderId, (string)$status, !empty($input['sample_collected']));

        $chk = $pdo->prepare("SELECT mo.id, mo.description, mo.visit_id, mo.patient_id, p.first_name, p.last_name, p.patient_no FROM medical_orders mo LEFT JOIN patients p ON p.id=mo.patient_id WHERE mo.id=? AND mo.order_type='lab'");
        $chk->execute([$orderId]);
        $order = $chk->fetch();
        if (!$order) error('Lab order not found', 404);

        $pdo->prepare("UPDATE medical_orders SET result=?, status=?, completed_at=NOW(), completed_by=? WHERE id=?")
            ->execute([$result, $status, $_SESSION['user_id'] ?? null, $orderId]);

        log_activity($pdo, 'UPDATE', 'lab',
            'Lab result entered: "' . $order['description'] . '" for ' . $order['first_name'] . ' ' . $order['last_name'] .
            '. Result: ' . substr($result, 0, 100),
            $orderId, $order['patient_no']
        );

        // Notify doctor if all lab orders for this visit are completed
        try {
            $visitId   = (int)$order['visit_id'];
            $patientId = (int)$order['patient_id'];
            if ($visitId && $status === 'completed') {
                $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM medical_orders WHERE visit_id=? AND order_type='lab'");
                $totalStmt->execute([$visitId]); $totalOrders = (int)$totalStmt->fetchColumn();
                $doneStmt  = $pdo->prepare("SELECT COUNT(*) FROM medical_orders WHERE visit_id=? AND order_type='lab' AND status='completed'");
                $doneStmt->execute([$visitId]);  $doneOrders  = (int)$doneStmt->fetchColumn();

                if ($totalOrders > 0 && $doneOrders >= $totalOrders) {
                    $existsStmt = $pdo->prepare("SELECT id FROM notifications WHERE visit_id=? AND type='lab_ready' AND is_read=0");
                    $existsStmt->execute([$visitId]);
                    if (!$existsStmt->fetch()) {
                        $ptName = $order['first_name'] . ' ' . $order['last_name'];
                        $pdo->prepare("INSERT INTO notifications (type, title, message, visit_id, patient_id, target_role) VALUES ('lab_ready',?,?,?,?,'doctor')")
                            ->execute(["Lab Results Ready", "All $totalOrders lab test(s) for $ptName are ready for review.", $visitId, $patientId]);
                    }
                }
            }
        } catch (Exception $e) { /* Non-critical */ }

        // ── Push Lab Result to RHIE SHR as Observation ───────────────────────
        try {
            require_once __DIR__ . '/../../includes/rhie/RHIELogger.php';

            $rhiePat = $pdo->prepare("SELECT rhie_patient_id, first_name, last_name FROM patients WHERE id = ?");
            $rhiePat->execute([$order['patient_id']]);
            $rhiePat = $rhiePat->fetch();
            $patientUpid = $rhiePat['rhie_patient_id'] ?? null;

            $rhieVis = $pdo->prepare("SELECT rhie_encounter_id FROM visits WHERE id = ?");
            $rhieVis->execute([$order['visit_id']]);
            $rhieVis = $rhieVis->fetch();
            $encounterFhirId = $rhieVis['rhie_encounter_id'] ?? null;

            if ($patientUpid && $encounterFhirId) {
                $facilityName = 'Medlink Health Facility';
                try {
                    $fn = $pdo->query("SELECT setting_value FROM hospital_settings WHERE setting_key='hospital_name' LIMIT 1")->fetchColumn();
                    if ($fn) $facilityName = $fn;
                } catch (Throwable $e) {}

                $labTechName = 'Lab Technician';
                if (!empty($_SESSION['user_id'])) {
                    $ru = $pdo->prepare("SELECT CONCAT(first_name,' ',last_name) FROM users WHERE id = ?");
                    $ru->execute([$_SESSION['user_id']]);
                    $labTechName = $ru->fetchColumn() ?: 'Lab Technician';
                }

                $now      = (new DateTime('now', new DateTimeZone('Africa/Kigali')))->format('Y-m-d\TH:i:sP');
                $authHdr  = 'Authorization: Basic ' . base64_encode('MEDLINK:InQpMD8a391zwdoH');
                $shrUrl   = 'http://197.243.24.138:5001/shr/Observation/lab-results';
                $testName = $order['description'] ?? 'Lab Test';

                $patientRef   = ['reference' => 'Patient/' . $patientUpid, 'display' => trim(($rhiePat['first_name'] ?? '') . ' ' . ($rhiePat['last_name'] ?? ''))];
                $encounterRef = ['reference' => 'Encounter/' . $encounterFhirId];
                $performer    = [['reference' => 'Practitioner/' . urlencode($labTechName), 'display' => $labTechName]];

                // Build one or more Observations depending on result format
                $resultParams = json_decode($result, true);
                $observations = [];

                if (is_array($resultParams) && !empty($resultParams)) {
                    // JSON array of parameters — one Observation per parameter
                    foreach ($resultParams as $param) {
                        $paramName  = $param['test_name'] ?? $param['name'] ?? $testName;
                        $paramValue = $param['value'] ?? '';
                        $paramUnit  = $param['unit']  ?? '';
                        if ($paramValue === '' || $paramValue === null) continue;

                        $obs = [
                            'resourceType'      => 'Observation',
                            'status'            => 'final',
                            'category'          => [['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/observation-category', 'code' => 'laboratory', 'display' => 'Laboratory']]]],
                            'code'              => ['coding' => [['system' => 'http://loinc.org', 'code' => 'unknown', 'display' => $paramName]], 'text' => $paramName],
                            'subject'           => $patientRef,
                            'encounter'         => $encounterRef,
                            'effectiveDateTime' => $now,
                            'performer'         => $performer,
                        ];
                        if (is_numeric($paramValue)) {
                            $obs['valueQuantity'] = ['value' => (float)$paramValue, 'unit' => $paramUnit, 'system' => 'http://unitsofmeasure.org', 'code' => $paramUnit];
                            if (!empty($param['normal_min']) && !empty($param['normal_max'])) {
                                $obs['referenceRange'] = [['low' => ['value' => (float)$param['normal_min'], 'unit' => $paramUnit], 'high' => ['value' => (float)$param['normal_max'], 'unit' => $paramUnit]]];
                            }
                        } else {
                            $obs['valueString'] = $paramValue;
                        }
                        $observations[] = $obs;
                    }
                } else {
                    // Plain string / single numeric result
                    $obs = [
                        'resourceType'      => 'Observation',
                        'status'            => 'final',
                        'category'          => [['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/observation-category', 'code' => 'laboratory', 'display' => 'Laboratory']]]],
                        'code'              => ['coding' => [['system' => 'http://loinc.org', 'code' => 'unknown', 'display' => $testName]], 'text' => $testName],
                        'subject'           => $patientRef,
                        'encounter'         => $encounterRef,
                        'effectiveDateTime' => $now,
                        'performer'         => $performer,
                    ];
                    if (is_numeric($result)) {
                        $obs['valueQuantity'] = ['value' => (float)$result, 'unit' => '', 'system' => 'http://unitsofmeasure.org', 'code' => ''];
                    } else {
                        $obs['valueString'] = $result;
                    }
                    $observations[] = $obs;
                }

                foreach ($observations as $obs) {
                    $paramLabel = $obs['code']['text'] ?? $testName;
                    $rhieLog = RHIELogger::start($pdo, 'push_lab_result', 'Observation', 'push',
                        (int)$order['patient_id'], (int)$order['visit_id'], $shrUrl, 'POST', $obs);

                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL            => $shrUrl,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT        => 15,
                        CURLOPT_CUSTOMREQUEST  => 'POST',
                        CURLOPT_POSTFIELDS     => json_encode($obs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        CURLOPT_HTTPHEADER     => [$authHdr, 'Content-Type: application/fhir+json', 'Accept: application/fhir+json'],
                    ]);
                    $resp     = curl_exec($ch);
                    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curlErr  = curl_error($ch);
                    $ch       = null;

                    $decoded = json_decode($resp, true);
                    $obsId   = $decoded['id'] ?? null;
                    $obsErr  = $curlErr ? 'cURL: ' . $curlErr : ($httpCode < 200 || $httpCode >= 300 ? "HTTP $httpCode: " . ($decoded['issue'][0]['diagnostics'] ?? substr($resp, 0, 150)) : null);
                    if ($obsErr) error_log('[RHIE] Lab result push failed order_id=' . $orderId . ' param=' . $paramLabel . ': ' . $obsErr);
                    RHIELogger::finish($pdo, $rhieLog, $httpCode ?: 0, (string)$resp, $obsId, $obsErr);
                }
            }
        } catch (Throwable $e) {
            error_log('[RHIE] Lab result push exception order_id=' . $orderId . ': ' . $e->getMessage());
        }
        // ─────────────────────────────────────────────────────────────────────

        success(['id' => $orderId], 'Result saved');
    }
    if ($action === 'verify') order_verify_result($pdo, (int)($input['order_id'] ?? $id ?? 0), ['lab']);
    if ($action === 'cancel') {
        $orderId = (int)($input['order_id'] ?? $id ?? 0);
        $reason  = trim($input['reason'] ?? '');
        if (!$orderId) error('order_id required', 422);

        $chk = $pdo->prepare("SELECT mo.id, mo.status, mo.description, p.first_name, p.last_name, p.patient_no FROM medical_orders mo LEFT JOIN patients p ON p.id=mo.patient_id WHERE mo.id=? AND mo.order_type='lab'");
        $chk->execute([$orderId]);
        $order = $chk->fetch();
        if (!$order) error('Lab order not found', 404);
        if (in_array($order['status'], ['completed', 'cancelled'], true)) error('A ' . $order['status'] . ' lab order cannot be cancelled.', 409);

        $pdo->prepare("UPDATE medical_orders SET status='cancelled' WHERE id=?")->execute([$orderId]);

        log_activity($pdo, 'DELETE', 'lab',
            'Lab order cancelled: "' . $order['description'] . '" for ' . $order['first_name'] . ' ' . $order['last_name'] .
            ($reason ? '. Reason: ' . $reason : ''),
            $orderId, $order['patient_no']
        );

        success(['id' => $orderId], 'Lab order cancelled');
    }

    error('Unknown action', 404);
}

// ============================================================
// PUT — update a lab order
// ============================================================
if ($method === 'PUT') {
    if (!$id) error('Lab order ID required', 422);
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $chk = $pdo->prepare("SELECT mo.id, mo.status, mo.description, p.first_name, p.last_name, p.patient_no FROM medical_orders mo LEFT JOIN patients p ON p.id=mo.patient_id WHERE mo.id=? AND mo.order_type='lab'");
    $chk->execute([$id]);
    $order = $chk->fetch();
    if (!$order) error('Lab order not found', 404);
    if (record_is_locked($pdo, 'lab_order', $id) && !empty($input['result'])) error('Completed laboratory results are locked; use an approved edit request', 409);

    $fields = []; $params = [];
    $allowed = ['status', 'result', 'details'];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $input)) { $fields[] = "$f = ?"; $params[] = $input[$f]; }
    }
    if (isset($input['status']) && $input['status'] === 'completed') {
        $fields[] = 'completed_at = NOW()';
        $fields[] = 'completed_by = ?';
        $params[] = $_SESSION['user_id'] ?? null;
        record_lock($pdo, 'lab_order', $id, 'Laboratory result completed; changes require an approved edit request', (int)($_SESSION['user_id'] ?? 0));
    }
    if (!$fields) error('Nothing to update', 422);

    $params[] = $id;
    $pdo->prepare("UPDATE medical_orders SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);

    log_activity($pdo, 'UPDATE', 'lab',
        'Lab order updated: "' . $order['description'] . '" → status: ' . ($input['status'] ?? 'updated') .
        ' for ' . $order['first_name'] . ' ' . $order['last_name'],
        $id, $order['patient_no']
    );

    success(['id' => $id], 'Lab order updated');
}

// ============================================================
// DELETE — delete a lab order
// ============================================================
if ($method === 'DELETE') {
    if (!$id) error('Lab order ID required', 422);

    $chk = $pdo->prepare("SELECT mo.id, mo.status, mo.description, p.first_name, p.last_name, p.patient_no FROM medical_orders mo LEFT JOIN patients p ON p.id=mo.patient_id WHERE mo.id=? AND mo.order_type='lab'");
    $chk->execute([$id]);
    $order = $chk->fetch();
    if (!$order) error('Lab order not found', 404);
    if (record_is_locked($pdo, 'lab_order', $id)) error('Completed laboratory orders are locked; use an approved deletion or edit request', 409);

    $pdo->prepare("DELETE FROM medical_orders WHERE id = ?")->execute([$id]);

    log_activity($pdo, 'DELETE', 'lab',
        'Lab order deleted: "' . $order['description'] . '" for ' . $order['first_name'] . ' ' . $order['last_name'],
        $id, $order['patient_no']
    );

    success(null, 'Lab order deleted');
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

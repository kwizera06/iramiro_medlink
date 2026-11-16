<?php
// handlers/patients/visits.php
require_once __DIR__ . '/../../includes/database.php';

function jsonResponse($data, $status = 200) { http_response_code($status); echo json_encode($data); exit(); }
function success($data = null, $msg = 'Success', $status = 200) { jsonResponse(['success'=>true,'message'=>$msg,'data'=>$data], $status); }
function error($msg, $status = 400) { jsonResponse(['success'=>false,'message'=>$msg], $status); }

// Generate unique visit number: VIS-YYYYMMDD-XXXX
function generateVisitNo(PDO $pdo): string {
    $date   = date('Ymd');
    $prefix = "VIS-{$date}-";
    $stmt   = $pdo->prepare("SELECT visit_no FROM visits WHERE visit_no LIKE ? ORDER BY visit_no DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    $seq  = $last ? ((int)substr($last, -4) + 1) : 1;
    return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

// Validate insurance for a patient: returns status + note
function checkInsurance(PDO $pdo, int $patientId): array {
    $stmt = $pdo->prepare("
        SELECT pi.id, pi.policy_number, pi.member_number,
               pi.policy_start_date, pi.policy_end_date, pi.status AS ins_status,
               ic.name AS company_name, ic.code AS company_code,
               ip.name AS package_name, ip.code AS package_code,
               ip.coverage_limit, ip.copayment_percentage
        FROM patient_insurance pi
        JOIN insurance_companies ic ON ic.id = pi.company_id
        LEFT JOIN insurance_packages ip ON ip.id = pi.package_id
        WHERE pi.patient_id = ?
        ORDER BY pi.is_primary DESC, pi.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$patientId]);
    $ins = $stmt->fetch();

    if (!$ins) {
        return ['has_insurance' => false, 'insurance_status' => null, 'insurance_id' => null, 'insurance_data' => null];
    }

    $today    = date('Y-m-d');
    $isActive = $ins['ins_status'] === 'active';
    $notExpired = $ins['policy_end_date'] >= $today;
    $started    = $ins['policy_start_date'] <= $today;

    if ($isActive && $notExpired && $started) {
        $status = 'valid';
        $note   = "Valid — {$ins['company_name']} (" . ($ins['package_name'] ?: 'No package') . ") expires " . date('d M Y', strtotime($ins['policy_end_date']));
    } elseif (!$notExpired) {
        $status = 'expired';
        $note   = "Expired — {$ins['company_name']} policy expired " . date('d M Y', strtotime($ins['policy_end_date']));
    } elseif (!$started) {
        $status = 'pending';
        $note   = "Not yet active — {$ins['company_name']} starts " . date('d M Y', strtotime($ins['policy_start_date']));
    } else {
        $status = 'not_covered';
        $note   = "{$ins['company_name']} policy status: {$ins['ins_status']}";
    }

    return [
        'has_insurance'    => true,
        'insurance_status' => $status,
        'insurance_id'     => (int)$ins['id'],
        'insurance_note'   => $note,
        'insurance_data'   => $ins
    ];
}

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {

// ===== GET =====
if ($method === 'GET') {

    // GET /visits?action=departments — list all departments
    if (isset($_GET['action']) && $_GET['action'] === 'departments') {
        $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC");
        success($stmt->fetchAll());
    }

    // GET /visits?action=users_by_dept&department_id=X — list users in a department
    if (isset($_GET['action']) && $_GET['action'] === 'users_by_dept') {
        $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
        if (!$deptId) error('department_id is required', 422);
        $stmt = $pdo->prepare("
            SELECT u.id, u.first_name, u.last_name, u.username, u.email, u.role,
                   r.name AS role_name
            FROM users u
            LEFT JOIN roles r ON r.id = u.role_id
            WHERE u.department_id = ? AND u.is_active = 1 AND u.is_blocked = 0
            ORDER BY u.first_name, u.last_name
        ");
        $stmt->execute([$deptId]);
        success($stmt->fetchAll());
    }

    // GET /visits?action=department_queue&department_id=X — queue of patients for a department
    if (isset($_GET['action']) && $_GET['action'] === 'department_queue') {
        $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
        if (!$deptId) error('department_id is required', 422);
        
        $dateFrom = isset($_GET['date_from']) && $_GET['date_from'] !== '' ? $_GET['date_from'] : '';
        $dateTo   = isset($_GET['date_to']) && $_GET['date_to'] !== '' ? $_GET['date_to'] : '';

        $whereWaiting = [
            "v.department_id = ?",
            "v.status IN ('open', 'in_progress')",
            "(v.consultation_status IS NULL OR v.consultation_status IN ('', 'waiting', 'in_consultation', 'now_serving'))"
        ];
        $paramsWaiting = [$deptId];
        if ($dateFrom !== '') {
            $whereWaiting[] = "DATE(v.visit_date) >= ?";
            $paramsWaiting[] = $dateFrom;
        }
        if ($dateTo !== '') {
            $whereWaiting[] = "DATE(v.visit_date) <= ?";
            $paramsWaiting[] = $dateTo;
        }
        
        // Waiting list — patients in queue assigned to this dept
        $waitingSql = "
            SELECT v.id, v.visit_no, v.queue_no, v.queued_at, v.consultation_status,
                   v.patient_id, v.department_id, v.visit_date, v.visit_type,
                   v.payment_type, v.payment_type AS visit_payment_type,
                   v.insurance_id,
                   v.chief_complaint,
                   CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                   p.patient_no, p.date_of_birth, p.gender,
                   COALESCE(ic.id, ic2.id, ic3.id) AS company_id,
                   COALESCE(ic.name, ic2.name, ic3.name) AS company_name,
                   COALESCE(ip.name, ip2.name, ip3.name) AS package_name,
                   COALESCE(ip.copayment_percentage, ip2.copayment_percentage, ip3.copayment_percentage, 100) AS copayment_percentage
            FROM visits v
            JOIN patients p ON p.id = v.patient_id
            LEFT JOIN patient_insurance pi ON pi.id = v.insurance_id AND pi.patient_id = v.patient_id
            LEFT JOIN insurance_companies ic ON ic.id = pi.company_id
            LEFT JOIN insurance_packages ip ON ip.id = pi.package_id
            LEFT JOIN patient_insurance pi2 ON pi2.company_id = v.insurance_id AND pi2.patient_id = v.patient_id AND pi.id IS NULL
            LEFT JOIN insurance_companies ic2 ON ic2.id = pi2.company_id AND pi.id IS NULL
            LEFT JOIN insurance_packages ip2 ON ip2.id = pi2.package_id AND pi.id IS NULL
            LEFT JOIN patient_insurance pi3 ON pi3.patient_id = v.patient_id AND pi3.is_primary = 1 AND pi.id IS NULL AND pi2.id IS NULL
            LEFT JOIN insurance_companies ic3 ON ic3.id = pi3.company_id AND pi.id IS NULL AND pi2.id IS NULL
            LEFT JOIN insurance_packages ip3 ON ip3.id = pi3.package_id AND pi.id IS NULL AND pi2.id IS NULL
            WHERE " . implode(' AND ', $whereWaiting) . "
            ORDER BY v.queue_no ASC, v.queued_at ASC, v.created_at ASC
        ";
        $waiting = $pdo->prepare($waitingSql);
        $waiting->execute($paramsWaiting);
        $waitingList = $waiting->fetchAll();

        $prevVisitStmt = $pdo->prepare("
            SELECT v_prev.id, v_prev.visit_no, v_prev.visit_date, v_prev.created_at,
                   d.name AS department_name,
                   DATEDIFF(COALESCE(?, CURDATE()), v_prev.visit_date) AS days_ago
            FROM visits v_prev
            LEFT JOIN departments d ON d.id = v_prev.department_id
            WHERE v_prev.patient_id = ?
              AND v_prev.id != ?
              AND (v_prev.visit_date < ? OR (v_prev.visit_date = ? AND v_prev.id < ?))
            ORDER BY v_prev.visit_date DESC, v_prev.id DESC
            LIMIT 1
        ");

        $processQueueRow = function(&$row) use ($prevVisitStmt) {
            $compName = $row['company_name'] ?? '';
            $compId   = (int)($row['company_id'] ?? 0);
            $isPrivate = ($compId === 14 || stripos($compName, 'prive') !== false || stripos($compName, 'private') !== false || empty($compName));
            $row['is_private'] = $isPrivate;
            if ($isPrivate) {
                $row['display_payment_type'] = 'self_pay';
                $row['display_insurance_name'] = 'Private';
                $row['copayment_percentage'] = 100.0;
            } else {
                $row['display_payment_type'] = 'insurance';
                $row['display_insurance_name'] = $compName;
                $row['copayment_percentage'] = (float)($row['copayment_percentage'] ?? 100.0);
            }

            // Attach previous visit info (days_ago, visit_date, visit_no, department_name)
            $vDate = $row['visit_date'] ?? date('Y-m-d');
            $prevVisitStmt->execute([
                $vDate,
                $row['patient_id'],
                $row['id'],
                $vDate,
                $vDate,
                $row['id']
            ]);
            $prev = $prevVisitStmt->fetch(PDO::FETCH_ASSOC);
            if ($prev) {
                $prev['days_ago'] = (int)$prev['days_ago'];
                $row['previous_visit'] = $prev;
            } else {
                $row['previous_visit'] = null;
            }
        };

        // Separate waiting vs in_consultation vs now_serving
        $waitingPatients = [];
        $inConsultationPatients = [];
        $currentServing = null;
        foreach ($waitingList as $v) {
            $processQueueRow($v);
            if ($v['consultation_status'] === 'now_serving') {
                $inConsultationPatients[] = $v;
                $currentServing = $v;
            } else if ($v['consultation_status'] === 'in_consultation') {
                $inConsultationPatients[] = $v;
            } else {
                $waitingPatients[] = $v;
            }
        }

        // Completed in date range
        $whereDone = [
            "v.department_id = ?",
            "v.consultation_status = 'done'"
        ];
        $paramsDone = [$deptId];
        if ($dateFrom !== '') {
            $whereDone[] = "DATE(v.visit_date) >= ?";
            $paramsDone[] = $dateFrom;
        } else {
            $whereDone[] = "DATE(v.visit_date) >= ?";
            $paramsDone[] = date('Y-m-d');
        }
        if ($dateTo !== '') {
            $whereDone[] = "DATE(v.visit_date) <= ?";
            $paramsDone[] = $dateTo;
        } else {
            $whereDone[] = "DATE(v.visit_date) <= ?";
            $paramsDone[] = date('Y-m-d');
        }

        $doneSql = "
            SELECT v.id, v.visit_no, v.queue_no, v.consultation_status, v.patient_id, v.visit_date,
                   v.chief_complaint, v.payment_type, v.payment_type AS visit_payment_type,
                   v.insurance_id,
                   CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                   p.patient_no, p.date_of_birth, p.gender,
                   COALESCE(ic.id, ic2.id, ic3.id) AS company_id,
                   COALESCE(ic.name, ic2.name, ic3.name) AS company_name,
                   COALESCE(ip.name, ip2.name, ip3.name) AS package_name,
                   COALESCE(ip.copayment_percentage, ip2.copayment_percentage, ip3.copayment_percentage, 100) AS copayment_percentage
            FROM visits v
            JOIN patients p ON p.id = v.patient_id
            LEFT JOIN patient_insurance pi ON pi.id = v.insurance_id AND pi.patient_id = v.patient_id
            LEFT JOIN insurance_companies ic ON ic.id = pi.company_id
            LEFT JOIN insurance_packages ip ON ip.id = pi.package_id
            LEFT JOIN patient_insurance pi2 ON pi2.company_id = v.insurance_id AND pi2.patient_id = v.patient_id AND pi.id IS NULL
            LEFT JOIN insurance_companies ic2 ON ic2.id = pi2.company_id AND pi.id IS NULL
            LEFT JOIN insurance_packages ip2 ON ip2.id = pi2.package_id AND pi.id IS NULL
            LEFT JOIN patient_insurance pi3 ON pi3.patient_id = v.patient_id AND pi3.is_primary = 1 AND pi.id IS NULL AND pi2.id IS NULL
            LEFT JOIN insurance_companies ic3 ON ic3.id = pi3.company_id AND pi.id IS NULL AND pi2.id IS NULL
            LEFT JOIN insurance_packages ip3 ON ip3.id = pi3.package_id AND pi.id IS NULL AND pi2.id IS NULL
            WHERE " . implode(' AND ', $whereDone) . "
            ORDER BY v.updated_at DESC
            LIMIT 50
        ";
        $done = $pdo->prepare($doneSql);
        $done->execute($paramsDone);
        $doneList = $done->fetchAll();
        foreach ($doneList as &$dv) {
            $processQueueRow($dv);
        }
        unset($dv);

        success([
            'waiting' => $waitingPatients,
            'in_consultation' => $inConsultationPatients,
            'done' => $doneList,
            'current_serving' => $currentServing,
            'stats' => [
                'waiting' => count($waitingPatients),
                'in_consultation' => count($inConsultationPatients),
                'done_today' => count($doneList)
            ]
        ]);
    }

    // GET /visits?check_insurance=1&patient_id=X  — insurance check before opening visit
    if (isset($_GET['check_insurance']) && isset($_GET['patient_id'])) {
        $pid = (int)$_GET['patient_id'];
        $result = checkInsurance($pdo, $pid);
        success($result, 'Insurance checked');
    }

    // GET /visits?id=X — single visit
    if ($id) {
        $stmt = $pdo->prepare("
            SELECT v.*,
                   p.first_name, p.last_name, p.patient_no, p.phone, p.gender, p.date_of_birth,
                   d.name AS department_name,
                   COALESCE(ic.id, ic2.id, ic3.id) AS company_id,
                   COALESCE(ic.name, ic2.name, ic3.name) AS company_name,
                   COALESCE(ip.name, ip2.name, ip3.name) AS package_name,
                   COALESCE(ip.copayment_percentage, ip2.copayment_percentage, ip3.copayment_percentage, 100) AS copayment_percentage
            FROM visits v
            JOIN patients p ON p.id = v.patient_id
            LEFT JOIN departments d ON d.id = v.department_id
            LEFT JOIN patient_insurance pi ON pi.id = v.insurance_id AND pi.patient_id = v.patient_id
            LEFT JOIN insurance_companies ic ON ic.id = pi.company_id
            LEFT JOIN insurance_packages ip ON ip.id = pi.package_id
            LEFT JOIN patient_insurance pi2 ON pi2.company_id = v.insurance_id AND pi2.patient_id = v.patient_id AND pi.id IS NULL
            LEFT JOIN insurance_companies ic2 ON ic2.id = pi2.company_id AND pi.id IS NULL
            LEFT JOIN insurance_packages ip2 ON ip2.id = pi2.package_id AND pi.id IS NULL
            LEFT JOIN patient_insurance pi3 ON pi3.patient_id = v.patient_id AND pi3.is_primary = 1 AND pi.id IS NULL AND pi2.id IS NULL
            LEFT JOIN insurance_companies ic3 ON ic3.id = pi3.company_id AND pi.id IS NULL AND pi2.id IS NULL
            LEFT JOIN insurance_packages ip3 ON ip3.id = pi3.package_id AND pi.id IS NULL AND pi2.id IS NULL
            WHERE v.id = ?
        ");
        $stmt->execute([$id]);
        $visit = $stmt->fetch();
        if (!$visit) error('Visit not found', 404);
        success($visit);
    }

    // GET list
    $search      = trim($_GET['search'] ?? '');
    $patientId   = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
    $status      = $_GET['status'] ?? '';
    $date        = $_GET['date'] ?? '';
    $dateFrom    = $_GET['date_from'] ?? '';
    $dateTo      = $_GET['date_to'] ?? '';
    $page        = max(1, (int)($_GET['page'] ?? 1));
    $limit       = min(500, max(1, (int)($_GET['limit'] ?? 20)));
    $offset      = ($page - 1) * $limit;
    $sortOrder   = strtolower($_GET['order'] ?? $_GET['sort_order'] ?? 'desc');
    $orderDir    = ($sortOrder === 'asc') ? 'ASC' : 'DESC';

    $where = []; $params = [];
    if ($search !== '') {
        $like = "%$search%";
        $where[] = "(v.visit_no LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_no LIKE ?)";
        $params  = array_merge($params, [$like,$like,$like,$like]);
    }
    if ($patientId > 0) { $where[] = "v.patient_id = ?"; $params[] = $patientId; }
    if ($status !== '')  { $where[] = "v.status = ?";     $params[] = $status; }
    if ($date !== '')    { $where[] = "v.visit_date = ?";  $params[] = $date; }
    if ($dateFrom !== '') { $where[] = "v.visit_date >= ?"; $params[] = $dateFrom; }
    if ($dateTo !== '')   { $where[] = "v.visit_date <= ?"; $params[] = $dateTo; }
    $paymentType = $_GET['payment_type'] ?? '';
    if ($paymentType === 'insurance') {
        $where[] = "(v.payment_type = 'insurance' AND COALESCE(ic.id, ic2.id, ic3.id) != 14 AND LOWER(COALESCE(ic.name, ic2.name, ic3.name, '')) NOT LIKE '%prive%' AND LOWER(COALESCE(ic.name, ic2.name, ic3.name, '')) NOT LIKE '%private%')";
    } elseif ($paymentType === 'self_pay' || $paymentType === 'private') {
        $where[] = "(v.payment_type = 'self_pay' OR v.payment_type IS NULL OR COALESCE(ic.id, ic2.id, ic3.id) = 14 OR LOWER(COALESCE(ic.name, ic2.name, ic3.name, '')) LIKE '%prive%' OR LOWER(COALESCE(ic.name, ic2.name, ic3.name, '')) LIKE '%private%')";
    }
    $ws = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $fromSql = "
        FROM visits v
        JOIN patients p ON p.id = v.patient_id
        LEFT JOIN departments d ON d.id = v.department_id
        LEFT JOIN patient_insurance pi ON pi.id = v.insurance_id AND pi.patient_id = v.patient_id
        LEFT JOIN insurance_companies ic ON ic.id = pi.company_id
        LEFT JOIN insurance_packages ip ON ip.id = pi.package_id
        LEFT JOIN patient_insurance pi2 ON pi2.company_id = v.insurance_id AND pi2.patient_id = v.patient_id AND pi.id IS NULL
        LEFT JOIN insurance_companies ic2 ON ic2.id = pi2.company_id AND pi.id IS NULL
        LEFT JOIN insurance_packages ip2 ON ip2.id = pi2.package_id AND pi.id IS NULL
        LEFT JOIN patient_insurance pi3 ON pi3.patient_id = v.patient_id AND pi3.is_primary = 1 AND pi.id IS NULL AND pi2.id IS NULL
        LEFT JOIN insurance_companies ic3 ON ic3.id = pi3.company_id AND pi.id IS NULL AND pi2.id IS NULL
        LEFT JOIN insurance_packages ip3 ON ip3.id = pi3.package_id AND pi.id IS NULL AND pi2.id IS NULL
    ";

    $cnt = $pdo->prepare("SELECT COUNT(*) $fromSql $ws");
    $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();

    // Patient-share balance calculation: only patient's copayment portion, not insurance
    // Formula: SUM(MAX(0, ROUND(total * copay% / 100, 2) - paid_amount))
    // Version: 2.0 (updated 2026-08-13 14:10)
    $stmt = $pdo->prepare("
        SELECT v.id, v.visit_no, v.visit_date, v.visit_time, v.payment_type,
               v.insurance_status, v.visit_type, v.status, v.chief_complaint,
               v.department_id, v.doctor_name, v.insurance_id,
               p.id AS patient_id, p.first_name, p.last_name, p.patient_no, p.phone,
               p.date_of_birth, p.gender, p.created_at AS patient_created_at,
               d.name AS department_name,
               COALESCE(ic.id, ic2.id, ic3.id) AS company_id,
               COALESCE(ic.name, ic2.name, ic3.name) AS company_name,
               COALESCE(ip.copayment_percentage, ip2.copayment_percentage, ip3.copayment_percentage, 100) AS copayment_percentage,
               COALESCE(ip.name, ip2.name, ip3.name) AS package_name,
               COALESCE((SELECT SUM(GREATEST(0, ROUND(inv.total * COALESCE(ip_inv.copayment_percentage, ip_inv2.copayment_percentage, 100) / 100, 2) - COALESCE((SELECT SUM(pr.amount_paid) FROM payment_records pr WHERE pr.invoice_id = inv.id AND pr.status IN ('completed', 'written_off')), (CASE WHEN inv.payment_method = 'unpaid' THEN inv.paid_amount ELSE inv.paid_amount END))))
                         FROM invoices inv
                         LEFT JOIN patient_insurance pi_inv ON pi_inv.id = inv.insurance_id AND pi_inv.patient_id = inv.patient_id
                         LEFT JOIN patient_insurance pi_inv2 ON pi_inv2.patient_id = inv.patient_id AND pi_inv2.is_primary = 1 AND pi_inv.id IS NULL
                         LEFT JOIN insurance_packages ip_inv ON ip_inv.id = pi_inv.package_id
                         LEFT JOIN insurance_packages ip_inv2 ON ip_inv2.id = pi_inv2.package_id AND pi_inv.id IS NULL
                         WHERE inv.visit_id = v.id AND inv.status IN ('issued','partial')), 0) AS pending_amount,
                COALESCE((
                    SELECT SUM(
                        CASE 
                            WHEN (SELECT COUNT(*) FROM payment_records pr WHERE pr.invoice_id = inv.id) > 0 
                            THEN (SELECT COALESCE(SUM(pr2.amount_paid), 0) FROM payment_records pr2 WHERE pr2.invoice_id = inv.id AND pr2.status = 'completed')
                            ELSE (CASE WHEN inv.payment_method = 'unpaid' THEN 0 ELSE inv.paid_amount END)
                        END
                    )
                    FROM invoices inv
                    WHERE inv.visit_id = v.id
                ), 0) AS paid_amount,
                COALESCE((
                    SELECT SUM(
                        CASE 
                            WHEN (SELECT COUNT(*) FROM payment_records pr WHERE pr.invoice_id = inv.id) > 0 
                            THEN (SELECT COALESCE(SUM(pr2.amount_paid), 0) FROM payment_records pr2 WHERE pr2.invoice_id = inv.id AND pr2.status IN ('written_off', 'pending'))
                            ELSE (CASE WHEN inv.payment_method = 'unpaid' THEN inv.paid_amount ELSE 0 END)
                        END
                    )
                    FROM invoices inv
                    WHERE inv.visit_id = v.id
                ), 0) AS unpaid_amount,
                COALESCE((SELECT COUNT(*) FROM invoices inv WHERE inv.visit_id = v.id), 0) AS invoice_count
        $fromSql
        $ws ORDER BY v.visit_date $orderDir, v.visit_time $orderDir, v.id $orderDir
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $visits = $stmt->fetchAll();

    foreach ($visits as &$v) {
        $v['patient_name'] = $v['first_name'] . ' ' . $v['last_name'];
        $compName = strtolower($v['company_name'] ?? '');
        $isPrivate = ($v['payment_type'] === 'self_pay' || empty($v['payment_type']) || (int)($v['company_id'] ?? 0) === 14 || strpos($compName, 'prive') !== false || strpos($compName, 'private') !== false);
        if ($isPrivate) {
            $v['is_private'] = true;
            $v['display_payment_type'] = 'self_pay';
            $v['display_insurance_name'] = 'Private';
            $v['display_copay'] = 100.0;
        } else {
            $v['is_private'] = false;
            $v['display_payment_type'] = 'insurance';
            $v['display_insurance_name'] = $v['company_name'];
            $v['display_copay'] = (float)($v['copayment_percentage'] ?? 100);
        }
    }

    $statsWhere = []; $statsParams = [];
    if ($dateFrom !== '') { $statsWhere[] = "v.visit_date >= ?"; $statsParams[] = $dateFrom; }
    if ($dateTo !== '')   { $statsWhere[] = "v.visit_date <= ?"; $statsParams[] = $dateTo; }
    $statsWs = $statsWhere ? 'WHERE ' . implode(' AND ', $statsWhere) : '';

    $statsStmt = $pdo->prepare("
        SELECT COUNT(*) AS total,
               SUM(CASE WHEN v.status='open' THEN 1 ELSE 0 END) AS open_visits,
               SUM(CASE WHEN v.status='in_progress' THEN 1 ELSE 0 END) AS in_progress,
               SUM(CASE WHEN v.status='completed' THEN 1 ELSE 0 END) AS completed_visits,
               SUM(CASE WHEN v.status='completed' THEN 1 ELSE 0 END) AS completed,
               SUM(CASE WHEN v.status='cancelled' THEN 1 ELSE 0 END) AS cancelled_visits,
               SUM(CASE WHEN v.status='cancelled' THEN 1 ELSE 0 END) AS cancelled,
               SUM(CASE WHEN (v.payment_type='insurance' AND COALESCE(ic.id, ic2.id, ic3.id) != 14 AND LOWER(COALESCE(ic.name, ic2.name, ic3.name, '')) NOT LIKE '%prive%' AND LOWER(COALESCE(ic.name, ic2.name, ic3.name, '')) NOT LIKE '%private%') THEN 1 ELSE 0 END) AS insurance_visits,
               SUM(CASE WHEN (v.payment_type='self_pay' OR v.payment_type IS NULL OR COALESCE(ic.id, ic2.id, ic3.id) = 14 OR LOWER(COALESCE(ic.name, ic2.name, ic3.name, '')) LIKE '%prive%' OR LOWER(COALESCE(ic.name, ic2.name, ic3.name, '')) LIKE '%private%') THEN 1 ELSE 0 END) AS self_pay_visits,
               SUM(CASE WHEN DATE(v.visit_date)=CURDATE() THEN 1 ELSE 0 END) AS today
        $fromSql
        $statsWs
    ");
    $statsStmt->execute($statsParams);
    $stats = $statsStmt->fetch() ?: [];

    success([
        'visits'     => $visits,
        'stats'      => $stats,
        'pagination' => ['total'=>$total,'page'=>$page,'limit'=>$limit,'total_pages'=>(int)ceil($total/max($limit,1))]
    ]);
}

// ===== POST — Open Visit / Call Next =====
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    // POST /visits?action=call_next&department_id=X — call next waiting patient
    if (isset($_GET['action']) && $_GET['action'] === 'call_next') {
        $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
        if (!$deptId) error('department_id is required', 422);

        // Find the next waiting patient (oldest queued first)
        $stmt = $pdo->prepare("
            SELECT v.id, v.visit_no, v.queue_no, v.patient_id,
                   CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                   p.patient_no
            FROM visits v
            JOIN patients p ON p.id = v.patient_id
            WHERE v.department_id = ?
              AND (v.consultation_status IS NULL OR v.consultation_status IN ('', 'waiting'))
              AND v.status IN ('open', 'in_progress')
              AND DATE(v.visit_date) = CURDATE()
            ORDER BY v.queue_no ASC, v.queued_at ASC, v.created_at ASC
            LIMIT 1
        ");
        $stmt->execute([$deptId]);
        $next = $stmt->fetch();

        if (!$next) error('No waiting patients', 404);

        // Mark as in_consultation and set status
        $pdo->prepare("UPDATE visits SET consultation_status = 'in_consultation', status = 'in_progress' WHERE id = ?")
            ->execute([$next['id']]);

        // Update department history if exists
        $pdo->prepare("UPDATE visit_department_history SET status = 'in_progress' WHERE visit_id = ? AND department_id = ?")
            ->execute([$next['id'], $deptId]);

        success($next, 'Patient called');
    }

    // POST /visits — Open Visit

    if (empty($input['patient_id'])) error('patient_id is required', 422);

    $pid = (int)$input['patient_id'];

    // Verify patient exists
    $pStmt = $pdo->prepare("SELECT id, first_name, last_name, patient_no FROM patients WHERE id = ?");
    $pStmt->execute([$pid]);
    $patient = $pStmt->fetch();
    if (!$patient) error('Patient not found', 404);

    // Determine insurance
    $insCheck    = checkInsurance($pdo, $pid);
    $paymentType = $input['payment_type'] ?? 'self_pay';
    $insId       = null;
    $insStatus   = null;
    $insNote     = null;

    if ($paymentType === 'insurance' || $paymentType === 'mixed') {
        // Use insurance_id from request if provided (selected at visit time)
        if (!empty($input['insurance_id'])) {
            $insId = (int)$input['insurance_id'];
            $insStatus = 'valid';
            $insNote = 'Insurance selected at visit';
        } elseif ($insCheck['has_insurance']) {
            $insId     = $insCheck['insurance_id'];
            $insStatus = $insCheck['insurance_status'];
            $insNote   = $insCheck['insurance_note'];
            if ($insStatus !== 'valid' && empty($input['force_insurance'])) {
                $paymentType = 'self_pay';
                $insNote     = ($insNote ?? '') . ' — defaulted to self-pay';
            }
        } else if (empty($input['insurance_id'])) {
            // No insurance selected and no insurance on file
            $paymentType = 'self_pay';
            $insNote     = 'No insurance selected — self-pay';
        }
    }

    $visitNo = generateVisitNo($pdo);
    $deptId  = !empty($input['department_id']) ? (int)$input['department_id'] : null;

    $pdo->beginTransaction();
    $stmt = $pdo->prepare("
        INSERT INTO visits
            (visit_no, patient_id, visit_date, visit_time, payment_type, insurance_id,
             insurance_status, insurance_note, department_id, visit_type,
             status, chief_complaint, notes, opened_by, created_at)
        VALUES
            (?, ?, CURDATE(), CURTIME(), ?, ?, ?, ?, ?, ?, 'open', ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $visitNo,
        $pid,
        $paymentType,
        $insId,
        $insStatus,
        $insNote,
        $deptId,
        $input['visit_type']       ?? 'outpatient',
        $input['chief_complaint']  ?? null,
        $input['notes']            ?? null,
        $input['opened_by']        ?? null,
    ]);
    $visitId = (int)$pdo->lastInsertId();
    $pdo->commit();

    success([
        'id'           => $visitId,
        'visit_no'     => $visitNo,
        'patient_no'   => $patient['patient_no'],
        'patient_name' => $patient['first_name'] . ' ' . $patient['last_name'],
        'payment_type' => $paymentType,
        'insurance'    => $insCheck['has_insurance'] ? $insCheck['insurance_data'] : null,
        'insurance_status' => $insStatus,
        'insurance_note'   => $insNote,
    ], 'Visit opened successfully', 201);
}

// ===== PUT — Update / Close visit =====
if ($method === 'PUT') {
    if (!$id) error('Visit ID required', 422);

    $chk = $pdo->prepare("SELECT id, status FROM visits WHERE id = ?");
    $chk->execute([$id]);
    $visit = $chk->fetch();
    if (!$visit) error('Visit not found', 404);

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? 'update';

    if ($action === 'close') {
        $pdo->prepare("UPDATE visits SET status='completed', closed_at=NOW() WHERE id = ?")->execute([$id]);
        success(null, 'Visit closed');
    }

    if ($action === 'assign_consultation') {
        $deptId     = isset($input['department_id']) ? (int)$input['department_id'] : null;
        $doctorName = isset($input['doctor_name']) ? trim($input['doctor_name']) : null;
        if (!$deptId) error('department_id is required', 422);

        // Get visit details
        $vStmt = $pdo->prepare("SELECT patient_id, status FROM visits WHERE id = ?");
        $vStmt->execute([$id]);
        $visit = $vStmt->fetch();
        if (!$visit) error('Visit not found', 404);

        // Create department history entry for tracking
        $doctorId = isset($input['doctor_id']) ? (int)$input['doctor_id'] : null;
        $chk = $pdo->prepare("SELECT id FROM visit_department_history WHERE visit_id = ? AND department_id = ? AND status = 'pending'");
        $chk->execute([$id, $deptId]);
        if (!$chk->fetch()) {
            $pdo->prepare("
                INSERT INTO visit_department_history (visit_id, patient_id, department_id, assigned_to, status, doctor_notes)
                VALUES (?, ?, ?, ?, 'pending', ?)
            ")->execute([$id, $visit['patient_id'], $deptId, $doctorId, $input['notes'] ?? null]);
        }

        $pdo->prepare("UPDATE visits SET department_id=?, doctor_name=?, consultation_status='waiting', status='in_progress' WHERE id=?")
            ->execute([$deptId, $doctorName ?: null, $id]);

        // Create a consultation record so the doctor sees the patient
        $existConsult = $pdo->prepare("SELECT id FROM consultations WHERE visit_id=? AND department_id=?");
        $existConsult->execute([$id, $deptId]);
        if (!$existConsult->fetch()) {
            $pdo->prepare("
                INSERT INTO consultations (visit_id, patient_id, department_id, doctor_id, doctor_name, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'draft', NOW())
            ")->execute([$id, $visit['patient_id'], $deptId, $doctorId, $doctorName ?: null]);
        }

        success(null, 'Consultation assigned');
    }

    if ($action === 'send_to_department') {
        $deptId  = isset($input['department_id']) ? (int)$input['department_id'] : null;
        $userId  = isset($input['user_id']) ? (int)$input['user_id'] : null;
        $notes   = $input['notes'] ?? null;
        if (!$deptId) error('department_id is required', 422);

        // Get visit's patient
        $vStmt = $pdo->prepare("SELECT patient_id FROM visits WHERE id = ?");
        $vStmt->execute([$id]);
        $visit = $vStmt->fetch();
        if (!$visit) error('Visit not found', 404);

        // Check if already assigned
        $chk = $pdo->prepare("SELECT id FROM visit_department_history WHERE visit_id = ? AND department_id = ?");
        $chk->execute([$id, $deptId]);
        if ($chk->fetch()) error('Visit already assigned to this department', 409);

        // Create department history entry
        $pdo->prepare("
            INSERT INTO visit_department_history (visit_id, patient_id, department_id, assigned_by, assigned_to, status, doctor_notes)
            VALUES (?, ?, ?, ?, ?, 'pending', ?)
        ")->execute([$id, $visit['patient_id'], $deptId, $userId, $input['assigned_to'] ?? null, $notes]);

        // Get next queue number for the department today
        $qStmt = $pdo->prepare("
            SELECT COALESCE(MAX(CAST(SUBSTRING(queue_no, 3) AS UNSIGNED)), 0) + 1
            FROM visits 
            WHERE department_id = ? AND DATE(visit_date) = CURDATE() AND queue_no IS NOT NULL
        ");
        $qStmt->execute([$deptId]);
        $nextQ = str_pad($qStmt->fetchColumn(), 2, '0', STR_PAD_LEFT);
        $queueNo = 'Q' . $nextQ;

        // Update visit with department, queue info, and consultation_status
        $pdo->prepare("UPDATE visits SET department_id = ?, status = 'in_progress', queue_no = ?, queued_at = NOW(), consultation_status = 'waiting' WHERE id = ?")
            ->execute([$deptId, $queueNo, $id]);

        success(['queue_no' => $queueNo], 'Patient sent to department successfully');
    }

    $fields = []; $params = [];
    $allowed = ['payment_type','insurance_id','insurance_status','insurance_note','department_id','visit_type','status','chief_complaint','notes','consultation_status'];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $input)) { $fields[] = "$f = ?"; $params[] = $input[$f]; }
    }
    if (!$fields) error('Nothing to update', 422);

    // Block cancellation if there are pending/issued invoices
    if (isset($input['status']) && $input['status'] === 'cancelled') {
        $invCheck = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE visit_id = ? AND status IN ('issued','partial')");
        $invCheck->execute([$id]);
        $pendingInvoices = (int)$invCheck->fetchColumn();
        if ($pendingInvoices > 0) {
            error('Cannot cancel this visit. There are ' . $pendingInvoices . ' pending invoice(s) that must be paid or cancelled first.', 422);
        }
    }

    $params[] = $id;
    $pdo->prepare("UPDATE visits SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
    success(null, 'Visit updated');
}

// ===== DELETE =====
if ($method === 'DELETE') {
    if (!$id) error('Visit ID required', 422);
    $chk = $pdo->prepare("SELECT id FROM visits WHERE id = ?");
    $chk->execute([$id]);
    if (!$chk->fetch()) error('Visit not found', 404);
    $pdo->prepare("DELETE FROM visits WHERE id = ?")->execute([$id]);
    success(null, 'Visit deleted');
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

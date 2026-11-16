<?php
if (!defined('MEDLINK_API') && PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
// handlers/patients/visits.php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/activity_log.php';
require_once __DIR__ . '/../../includes/referral_schema.php';
require_once __DIR__ . '/../../includes/insurance_limits.php';
require_once __DIR__ . '/../../includes/billing-calc.php';
require_once __DIR__ . '/../../includes/duty_status.php';
require_once __DIR__ . '/../../includes/record_lock.php';
if (!$pdo->inTransaction()) record_lock_schema($pdo);

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

// Saves company/plan/member no. on the patient's insurance rows (used when a visit is opened or its insurance edited).
function saveVisitInsurance(PDO $pdo, int $pid, int $insId, array $input, bool $primary = true): int {
    $planId = !empty($input['insurance_plan_id']) ? (int)$input['insurance_plan_id'] : null;
    $memNum = trim($input['member_number'] ?? '');
    // One row per (patient, company): switching company must not rewrite the old company's row,
    // because earlier visits resolve their company/plan/member no. through it.
    $exist  = $pdo->prepare("SELECT id FROM patient_insurance WHERE patient_id = ? AND company_id = ? ORDER BY is_primary DESC, id DESC LIMIT 1");
    $exist->execute([$pid, $insId]);
    $existId = $exist->fetchColumn();
    if ($existId) {
        $pdo->prepare("UPDATE patient_insurance SET package_id = COALESCE(?, package_id), member_number = COALESCE(NULLIF(?, ''), member_number), principal_member_name = COALESCE(?, principal_member_name), principal_phone = COALESCE(?, principal_phone), principal_member_number = COALESCE(?, principal_member_number), employer_name = COALESCE(?, employer_name), is_primary = IF(?, 1, is_primary), status = 'active' WHERE id = ?")
             ->execute([$planId, $memNum, isset($input['principal_member_name']) ? trim($input['principal_member_name']) : null, isset($input['principal_phone']) ? trim($input['principal_phone']) : null, isset($input['principal_member_number']) ? trim($input['principal_member_number']) : null, isset($input['employer_name']) ? trim($input['employer_name']) : null, (int)$primary, $existId]);
        if ($primary) $pdo->prepare("UPDATE patient_insurance SET is_primary = 0 WHERE patient_id = ? AND id <> ?")->execute([$pid, $existId]);
        return (int)$existId;
    } else {
        if ($primary) $pdo->prepare("UPDATE patient_insurance SET is_primary = 0 WHERE patient_id = ?")->execute([$pid]);
        $pdo->prepare("INSERT INTO patient_insurance (patient_id, company_id, package_id, policy_number, member_number, principal_member_name, principal_phone, principal_member_number, employer_name, policy_start_date, policy_end_date, is_primary, status)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), '9999-12-31', ?, 'active')")
             ->execute([$pid, $insId, $planId, 'POL-' . $pid . '-' . date('Ymd'), $memNum, trim($input['principal_member_name'] ?? ''), trim($input['principal_phone'] ?? ''), trim($input['principal_member_number'] ?? ''), trim($input['employer_name'] ?? ''), (int)$primary]);
        return (int)$pdo->lastInsertId();
    }
}

// Links the visit's extra insurers (several insurances per visit; the cashier assigns one to each invoice at invoicing). Replaces any earlier list.
function saveVisitExtras(PDO $pdo, int $visitId, int $pid, int $primaryCompany, int $primaryPi, array $input): void {
    $pdo->prepare("DELETE FROM visit_insurances WHERE visit_id = ?")->execute([$visitId]);
    $extras = array_filter((array)($input['extra_insurances'] ?? []), fn($x) => !empty($x['insurance_id']) && (int)$x['insurance_id'] !== $primaryCompany);
    if (!$extras) return;
    $link = $pdo->prepare("INSERT IGNORE INTO visit_insurances (visit_id, patient_insurance_id) VALUES (?, ?)");
    $link->execute([$visitId, $primaryPi]);
    foreach ($extras as $x) {
        $link->execute([$visitId, saveVisitInsurance($pdo, $pid, (int)$x['insurance_id'], ['insurance_plan_id' => $x['insurance_plan_id'] ?? null, 'member_number' => $x['member_number'] ?? ''], false)]);
    }
}

// Validate insurance for a patient: returns status + note
function checkInsurance(PDO $pdo, int $patientId): array {
    $stmt = $pdo->prepare("
        SELECT pi.id, pi.company_id, pi.package_id, pi.member_number,
               pi.status AS ins_status, pi.policy_end_date,
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

    $coverage = null;
    // Policy end date is deliberately ignored: an 'active' policy stays valid even past its end date (hospital rule)
    if ($ins['ins_status'] === 'active') {
        $status = 'valid';
        $note   = "Valid — {$ins['company_name']} (" . ($ins['package_name'] ?: 'No package') . ")";
        $coverage = ins_limit_status($pdo, $patientId, (int)$ins['company_id'], (int)$ins['id'], (float)($ins['coverage_limit'] ?? 0));
    } else {
        $status = 'not_covered';
        $note   = "{$ins['company_name']} policy status: {$ins['ins_status']}";
    }

    return [
        'has_insurance'    => true,
        'insurance_status' => $status,
        'insurance_id'     => (int)$ins['id'],
        'insurance_note'   => $note,
        'insurance_data'   => $ins,
        'coverage'         => $coverage
    ];
}

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {

// ===== GET =====
if ($method === 'GET') {

    // GET /visits?action=departments — list all departments
    if (isset($_GET['action']) && $_GET['action'] === 'departments') {
        // visit_kind 'hidden' = support departments (admin, finance, stores...) that never receive patients
        $stmt = $pdo->query("SELECT id, name, visit_kind FROM departments WHERE visit_kind <> 'hidden' ORDER BY name ASC");
        success($stmt->fetchAll());
    }

    // GET /visits?action=consult_rule&department_id=X&(patient_id=Y | visit_id=Z) — 3/14-day consultation fee rule + allowed fees
    if (isset($_GET['action']) && $_GET['action'] === 'consult_rule') {
        $deptId = (int)($_GET['department_id'] ?? 0);
        $vid = (int)($_GET['visit_id'] ?? 0);
        $pid = (int)($_GET['patient_id'] ?? 0);
        if ($vid) { $q = $pdo->prepare("SELECT patient_id FROM visits WHERE id = ?"); $q->execute([$vid]); $pid = (int)$q->fetchColumn(); }
        if (!$deptId || !$pid) error('department_id and patient_id (or visit_id) are required', 422);
        $r = consultRule($pdo, $pid, $deptId, $vid);
        $r['services'] = consultServicesFor($pdo, $deptId, $r['rule']);
        $r['current'] = null;
        if ($vid) {
            $c = $pdo->prepare("SELECT ii.service_id, i.invoice_no, (i.paid_amount > 0 OR i.status = 'paid') AS paid FROM invoices i JOIN invoice_items ii ON ii.invoice_id = i.id JOIN visits v ON v.id = i.visit_id
                                WHERE i.visit_id = ? AND i.status <> 'cancelled' AND ii.category = 'consultation' AND COALESCE(i.department_id, v.department_id) = ? ORDER BY i.id DESC LIMIT 1");
            $c->execute([$vid, $deptId]);
            $r['current'] = $c->fetch() ?: null;
        }
        success($r);
    }

    // GET /visits?action=users_by_dept&department_id=X — list users in a department
    if (isset($_GET['action']) && $_GET['action'] === 'users_by_dept') {
        $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
        if (!$deptId) error('department_id is required', 422);
        $clin = "'doctor','nurse','specialist','surgeon','midwife','clinical_officer','consultant','clinical_director'";
        $stmt = $pdo->prepare("
            SELECT u.id, u.first_name, u.last_name, u.username, u.email, u.role,
                   r.name AS role_name,
                   (SELECT COUNT(*) FROM consultations c
                      JOIN visits v ON v.id = c.visit_id AND v.department_id = c.department_id
                     WHERE c.doctor_id = u.id
                       AND v.status IN ('open', 'in_progress')
                       AND v.consultation_status IN ('waiting', 'in_consultation', 'now_serving')
                       AND v.visit_date >= CURDATE() AND v.visit_date < CURDATE() + INTERVAL 1 DAY) AS queue_count
            FROM users u
            LEFT JOIN roles r ON r.id = u.role_id
            WHERE u.department_id = ?
              AND u.is_active = 1
              AND u.is_blocked = 0
              AND (r.slug IN ($clin) OR EXISTS (SELECT 1 FROM user_roles ur JOIN roles r2 ON r2.id = ur.role_id WHERE ur.user_id = u.id AND r2.slug IN ($clin)))
            ORDER BY u.first_name, u.last_name
        ");
        $stmt->execute([$deptId]);
        $users = $stmt->fetchAll();
        $duty = duty_on_duty_map($pdo, array_column($users, 'id'));
        foreach ($users as &$u) $u['on_duty'] = !empty($duty[(int)$u['id']]);
        unset($u);
        usort($users, fn($x, $y) => $y['on_duty'] <=> $x['on_duty']); // on duty first (stable order otherwise)
        success($users);
    }

    // GET /visits?action=department_queue&department_id=X — queue of patients for a department
    if (isset($_GET['action']) && $_GET['action'] === 'department_queue') {
        // department_id=all (admin view) = every department; $deptId 0 disables the filter below
        $allDepts = ($_GET['department_id'] ?? '') === 'all';
        $deptId = $allDepts ? 0 : (isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0);
        if (!$deptId && !$allDepts) error('department_id is required', 422);
        
        // Date range support (defaults to today if not provided)
        $dateFrom = isset($_GET['date_from']) && $_GET['date_from'] ? $_GET['date_from'] : date('Y-m-d');
        $dateTo = isset($_GET['date_to']) && $_GET['date_to'] ? $_GET['date_to'] : date('Y-m-d');
        
        ensure_referral_schema($pdo);
        // Waiting list — patients in queue assigned to this dept (all active, no date limit)
        $waiting = $pdo->prepare("
            SELECT v.id, v.visit_no, v.queue_no, v.queued_at, v.created_at, v.consultation_status,
                   v.patient_id, v.department_id, v.visit_date, v.payment_type, v.insurance_id, v.tariff_category,
                   v.doctor_id, v.doctor_name,
                   (SELECT h.priority FROM visit_department_history h WHERE h.visit_id = v.id AND h.department_id = v.department_id ORDER BY h.id DESC LIMIT 1) AS referral_priority,
                   v.updated_at AS consultation_started_at,
                   d.name AS department_name,
                   CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                   p.patient_no, p.gender, p.date_of_birth, p.phone, p.allergies,
                   v.status AS visit_status,
                   (SELECT COUNT(*) FROM vital_signs_records vr WHERE vr.visit_id = v.id) AS vitals_count,
                   COALESCE(ic.id, v.insurance_id) AS company_id,
                   COALESCE(ic.name, '') AS company_name,
                   COALESCE(ip.name, '') AS package_name,
                   COALESCE(ip.copayment_percentage, 100) AS copayment_percentage
            FROM visits v
            JOIN patients p ON p.id = v.patient_id
            LEFT JOIN departments d ON d.id = v.department_id
            LEFT JOIN patient_insurance pi ON pi.patient_id = v.patient_id AND (pi.id = v.insurance_id OR pi.company_id = v.insurance_id)
            LEFT JOIN patient_insurance pi_fallback ON pi_fallback.patient_id = v.patient_id AND pi_fallback.is_primary = 1 AND pi.id IS NULL
            LEFT JOIN insurance_companies ic ON ic.id = COALESCE(pi.company_id, pi_fallback.company_id, v.insurance_id)
            LEFT JOIN insurance_packages ip ON ip.id = COALESCE(pi.package_id, pi_fallback.package_id)
            WHERE (? = 0 OR v.department_id = ? OR EXISTS (SELECT 1 FROM visit_department_history x WHERE x.visit_id = v.id AND x.department_id = ? AND x.status IN ('pending','in_progress')))
              AND (v.status IN ('open', 'in_progress')
                   OR (v.status = 'completed' AND v.consultation_status IN ('waiting', 'in_consultation', 'now_serving')))
              AND (v.consultation_status IS NULL OR v.consultation_status IN ('', 'waiting', 'in_consultation', 'now_serving'))
            ORDER BY v.queue_no ASC, v.queued_at ASC, v.created_at ASC
        ");
        $waiting->execute([$deptId, $deptId, $deptId]);
        $baseList = $waiting->fetchAll();

        // A patient referred to several departments waits in each one: add a row per extra pending department
        $waitingList = [];
        $pendDepts = [];
        if ($baseList) {
            $ids = implode(',', array_map(fn($r) => (int)$r['id'], $baseList));
            $hq = $pdo->query("SELECT h.visit_id, h.department_id, d.name FROM visit_department_history h JOIN departments d ON d.id = h.department_id WHERE h.status IN ('pending','in_progress') AND h.visit_id IN ($ids)");
            foreach ($hq->fetchAll() as $h) $pendDepts[$h['visit_id']][] = $h;
        }
        foreach ($baseList as $v) {
            if (!$deptId || (int)$v['department_id'] === $deptId) $waitingList[] = $v;
            foreach ($pendDepts[$v['id']] ?? [] as $h) {
                if ((int)$h['department_id'] === (int)$v['department_id'] || ($deptId && (int)$h['department_id'] !== $deptId)) continue;
                $waitingList[] = array_merge($v, ['department_id' => (int)$h['department_id'], 'department_name' => $h['name'], 'consultation_status' => 'waiting']);
            }
        }

        // Separate waiting vs in_consultation vs now_serving
        $waitingPatients = [];
        $inConsultationPatients = [];
        $currentServing = null;
        foreach ($waitingList as $v) {
            if ($v['consultation_status'] === 'now_serving') {
                $inConsultationPatients[] = $v;
                $currentServing = $v;
            } else if ($v['consultation_status'] === 'in_consultation') {
                $inConsultationPatients[] = $v;
            } else {
                $waitingPatients[] = $v;
            }
        }

        // Completed in date range — JOIN consultations for diagnosis and doctor_name
        $done = $pdo->prepare("
            SELECT v.id, v.visit_no, v.queue_no, v.consultation_status, v.patient_id, v.visit_date,
                   v.department_id, v.payment_type, v.insurance_id, v.tariff_category,
                   v.chief_complaint,
                   CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                   p.patient_no,
                   c.diagnosis,
                   c.doctor_name,
                   c.doctor_id,
                   c.id AS consultation_id,
                   dd.name AS department_name,
                   (SELECT COUNT(*) FROM invoices bi WHERE bi.visit_id = v.id AND bi.status NOT IN ('cancelled','void')) AS invoice_count,
                   (SELECT COUNT(*) FROM invoices bi WHERE bi.visit_id = v.id AND bi.status NOT IN ('paid','cancelled','void')) AS unpaid_count,
                   (SELECT GROUP_CONCAT(bi.invoice_no ORDER BY bi.id SEPARATOR ', ') FROM invoices bi WHERE bi.visit_id = v.id AND bi.status = 'paid') AS paid_invoice_nos,
                   pq.done_at,
                   COALESCE(ic.id, v.insurance_id) AS company_id,
                   COALESCE(ic.name, '') AS company_name,
                   COALESCE(ip.name, '') AS package_name,
                   COALESCE(ip.copayment_percentage, 100) AS copayment_percentage
            FROM visits v
            JOIN patients p ON p.id = v.patient_id
            LEFT JOIN departments dd ON dd.id = v.department_id
            LEFT JOIN consultations c ON c.visit_id = v.id AND c.department_id = v.department_id
            LEFT JOIN patient_queue pq ON pq.visit_id = v.id AND pq.department_id = v.department_id
            LEFT JOIN patient_insurance pi ON pi.patient_id = v.patient_id AND (pi.id = v.insurance_id OR pi.company_id = v.insurance_id)
            LEFT JOIN patient_insurance pi_fallback ON pi_fallback.patient_id = v.patient_id AND pi_fallback.is_primary = 1 AND pi.id IS NULL
            LEFT JOIN insurance_companies ic ON ic.id = COALESCE(pi.company_id, pi_fallback.company_id, v.insurance_id)
            LEFT JOIN insurance_packages ip ON ip.id = COALESCE(pi.package_id, pi_fallback.package_id)
            WHERE (? = 0 OR v.department_id = ?)
              AND v.consultation_status = 'done'
              AND v.visit_date >= ? AND v.visit_date < DATE_ADD(?, INTERVAL 1 DAY)
            ORDER BY COALESCE(pq.done_at, v.updated_at) DESC
        ");
        $done->execute([$deptId, $deptId, $dateFrom, $dateTo]);
        $doneList = $done->fetchAll();

        // One row per visit (the consultation/queue joins can repeat a visit); the tile counts the same list the tab shows
        $seen = [];
        $doneList = array_values(array_filter($doneList, function ($r) use (&$seen) { return !isset($seen[$r['id']]) && ($seen[$r['id']] = true); }));
        $todayDone = $pdo->prepare("SELECT COUNT(*) FROM visits WHERE (? = 0 OR department_id = ?) AND consultation_status = 'done' AND visit_date >= ? AND visit_date < DATE_ADD(?, INTERVAL 1 DAY)");
        $todayDone->execute([$deptId, $deptId, $dateFrom, $dateTo]);

        success([
            'waiting' => $waitingPatients,
            'in_consultation' => $inConsultationPatients,
            'done' => $doneList,
            'current_serving' => $currentServing,
            'stats' => [
                'waiting' => count($waitingPatients),
                'in_consultation' => count($inConsultationPatients),
                'done_today' => (int)$todayDone->fetchColumn()
            ]
        ]);
    }

    // GET /visits?action=lab_pending&department_id=X|all — visits with lab orders still ordered/in progress (any date)
    if (isset($_GET['action']) && $_GET['action'] === 'lab_pending') {
        $allDepts = ($_GET['department_id'] ?? '') === 'all';
        $deptId = $allDepts ? 0 : (int)($_GET['department_id'] ?? 0);
        // Non-admin users with a department are locked to it, whatever the client sends
        $ud = $_SESSION['user_data'] ?? [];
        $isAdm = !empty($ud['is_admin']) || in_array(strtolower($ud['role'] ?? ''), ['admin','administrator'], true)
            || in_array('admin', $ud['role_slugs'] ?? [], true) || in_array('administrator', $ud['role_slugs'] ?? [], true)
            || in_array(strtolower($_SESSION['user_role'] ?? ''), ['admin','administrator'], true);
        $ownDept = (int)($ud['department_id'] ?? 0);
        if (!$ownDept && !empty($_SESSION['user_id'])) {
            $o = $pdo->prepare('SELECT department_id FROM users WHERE id = ?');
            $o->execute([(int)$_SESSION['user_id']]);
            $ownDept = (int)$o->fetchColumn();
        }
        if (!$isAdm && $ownDept) { $allDepts = false; $deptId = $ownDept; }
        if (!$deptId && !$allDepts) error('department_id is required', 422);
        $q = $pdo->prepare("
            SELECT v.id, v.visit_no, v.visit_date, v.patient_id, v.doctor_name,
                   CONCAT(p.first_name, ' ', p.last_name) AS patient_name, p.patient_no,
                   d.name AS department_name,
                   COUNT(mo.id) AS pending_count,
                   GROUP_CONCAT(mo.description ORDER BY mo.id SEPARATOR ', ') AS tests,
                   MIN(mo.ordered_at) AS ordered_at
            FROM medical_orders mo
            JOIN visits v ON v.id = mo.visit_id
            JOIN patients p ON p.id = v.patient_id
            LEFT JOIN departments d ON d.id = v.department_id
            WHERE mo.order_type = 'lab' AND mo.status IN ('ordered','in_progress')
              AND v.status <> 'cancelled' AND (? = 0 OR v.department_id = ?)
            GROUP BY v.id
            ORDER BY ordered_at DESC
            LIMIT 200
        ");
        $q->execute([$deptId, $deptId]);
        success($q->fetchAll());
    }

    // GET /visits?action=my_referrals&date_from=&date_to= — patients the signed-in nurse sent, with progress
    if (isset($_GET['action']) && $_GET['action'] === 'my_referrals') {
        ensure_referral_schema($pdo);
        $from = !empty($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d');
        $to   = !empty($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
        $q = $pdo->prepare("
            SELECT h.id, h.visit_id, h.patient_id, h.department_id, h.priority, h.critical_info, h.referred_at, h.status AS hist_status, h.completed_at,
                   v.visit_no, v.consultation_status, v.status AS visit_status, v.doctor_name,
                   CONCAT(p.first_name, ' ', p.last_name) AS patient_name, p.patient_no, d.name AS department_name,
                   EXISTS (SELECT 1 FROM bed_assignments ba WHERE ba.visit_id = v.id AND ba.created_at >= h.referred_at) AS admitted
            FROM visit_department_history h
            JOIN visits v ON v.id = h.visit_id
            JOIN patients p ON p.id = h.patient_id
            LEFT JOIN departments d ON d.id = h.department_id
            WHERE h.referred_by = ? AND h.referred_at >= ? AND h.referred_at < DATE_ADD(?, INTERVAL 1 DAY)
            ORDER BY h.referred_at DESC LIMIT 200
        ");
        $q->execute([(int)($_SESSION['user_id'] ?? 0), $from, $to]);
        $rows = $q->fetchAll();
        foreach ($rows as &$r) {
            $r['progress'] = $r['admitted'] ? 'admitted'
                : (($r['hist_status'] === 'completed' || $r['consultation_status'] === 'done') ? 'attended'
                : (($r['hist_status'] === 'in_progress' || in_array($r['consultation_status'], ['in_consultation', 'now_serving'], true)) ? 'received' : 'waiting'));
            $r['critical_info'] = $r['critical_info'] ? json_decode($r['critical_info'], true) : null;
        }
        unset($r);
        success($rows);
    }

    // GET /visits?action=note_comments&visit_id=X — nurse/doctor comment thread on the consultation notes
    if (isset($_GET['action']) && $_GET['action'] === 'note_comments') {
        ensure_referral_schema($pdo);
        $vid = (int)($_GET['visit_id'] ?? 0);
        if (!$vid) error('visit_id required', 422);
        $q = $pdo->prepare("SELECT c.id, c.comment, c.created_at, COALESCE(CONCAT(u.first_name,' ',u.last_name), u.username) AS author
                            FROM clinical_note_comments c LEFT JOIN users u ON u.id = c.user_id WHERE c.visit_id = ? ORDER BY c.id ASC");
        $q->execute([$vid]);
        success($q->fetchAll());
    }

    // GET /visits?action=patient_visits&patient_id=X — list visits for a patient
    if (isset($_GET['action']) && $_GET['action'] === 'patient_visits') {
        $pid = (int)($_GET['patient_id'] ?? 0);
        if (!$pid) error('patient_id required', 422);
        $stmt = $pdo->prepare("
            SELECT id, visit_no, visit_date, visit_type, status
            FROM visits
            WHERE patient_id = ?
            ORDER BY visit_date DESC, id DESC
            LIMIT 200
        ");
        $stmt->execute([$pid]);
        success(['visits' => $stmt->fetchAll()]);
    }

    // GET /visits?action=medical_orders&visit_id=X&order_type=lab — list orders for a visit
    if (isset($_GET['action']) && $_GET['action'] === 'medical_orders') {
        $vid = (int)($_GET['visit_id'] ?? 0);
        if (!$vid) error('visit_id required', 422);
        $orderType = $_GET['order_type'] ?? '';
        $params = [$vid];
        if ($orderType) {
            $typeSQL = ' AND order_type = ?';
            $params[] = $orderType;
        }
        $stmt = $pdo->prepare("
            SELECT id, order_type, description, status, result, ordered_at, completed_at
            FROM medical_orders
            WHERE visit_id = ? AND status <> 'no_sample' $typeSQL
            ORDER BY ordered_at ASC
        ");
        $stmt->execute($params);
        success(['orders' => $stmt->fetchAll()]);
    }

    // GET /visits?action=external_prescription&visit_id=X — get prescription for a visit
    if (isset($_GET['action']) && $_GET['action'] === 'external_prescription') {
        $vid = (int)($_GET['visit_id'] ?? 0);
        if (!$vid) error('visit_id required', 422);

        // 1. Visit & Patient details
        $vStmt = $pdo->prepare("
            SELECT v.id, v.visit_no, v.visit_date, v.visit_time, v.payment_type, v.tariff_category,
                   p.id AS patient_id, CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                   p.patient_no, p.gender, p.date_of_birth, p.phone, p.address,
                   COALESCE(ic.name, '') AS insurance_name,
                   COALESCE(pi.member_number, '') AS member_number
            FROM visits v
            JOIN patients p ON p.id = v.patient_id
            LEFT JOIN patient_insurance pi ON pi.patient_id = v.patient_id AND (pi.id = v.insurance_id OR pi.company_id = v.insurance_id)
            LEFT JOIN insurance_companies ic ON ic.id = COALESCE(pi.company_id, v.insurance_id)
            WHERE v.id = ?
        ");
        $vStmt->execute([$vid]);
        $visit = $vStmt->fetch();
        if (!$visit) error('Visit not found', 404);

        $items = [];
        $doctorName = '';
        $deptName = '';
        $prescDate = $visit['visit_date'];
        $notes = '';

        // 2. Check consultations table (primary for OPD consultations)
        $cStmt = $pdo->prepare("
            SELECT c.id, c.prescription, c.doctor_name, c.diagnosis, c.created_at, c.notes,
                   d.name AS department_name
            FROM consultations c
            LEFT JOIN departments d ON d.id = c.department_id
            WHERE c.visit_id = ?
              AND c.prescription IS NOT NULL
              AND c.prescription != ''
              AND c.prescription != '[]'
              AND c.prescription != 'null'
            ORDER BY c.id DESC
        ");
        $cStmt->execute([$vid]);
        $consultRows = $cStmt->fetchAll();

        foreach ($consultRows as $c) {
            if (!empty($c['doctor_name']) && empty($doctorName)) $doctorName = $c['doctor_name'];
            if (!empty($c['department_name']) && empty($deptName)) $deptName = $c['department_name'];
            if (!empty($c['created_at'])) $prescDate = substr($c['created_at'], 0, 10);
            if (!empty($c['notes']) && empty($notes)) $notes = $c['notes'];

            $raw = json_decode($c['prescription'], true);
            if (is_array($raw)) {
                foreach ($raw as $rx) {
                    $items[] = [
                        'drug' => $rx['medication'] ?? $rx['drug'] ?? '',
                        'dosage' => $rx['dosage'] ?? '',
                        'frequency' => $rx['frequency'] ?? '',
                        'duration' => $rx['duration'] ?? '',
                        'quantity' => $rx['quantity'] ?? ''
                    ];
                }
            }
        }

        // 3. Also check external_prescriptions table (used in ward/inpatient)
        $epStmt = $pdo->prepare("
            SELECT ep.id, ep.prescription_no, ep.prescription_date, ep.notes,
                   COALESCE(CONCAT(u.first_name, ' ', u.last_name), u.username) AS doctor_name,
                   ep.created_at
            FROM external_prescriptions ep
            LEFT JOIN users u ON u.id = ep.created_by
            WHERE ep.visit_id = ? AND ep.status = 'active'
            ORDER BY ep.id DESC
        ");
        $epStmt->execute([$vid]);
        $epRows = $epStmt->fetchAll();

        foreach ($epRows as $ep) {
            if (!empty($ep['doctor_name']) && empty($doctorName)) $doctorName = $ep['doctor_name'];
            if (!empty($ep['prescription_date'])) $prescDate = $ep['prescription_date'];
            if (!empty($ep['notes']) && empty($notes)) $notes = $ep['notes'];

            $epiStmt = $pdo->prepare("SELECT medication_name AS drug, dosage, frequency, duration, quantity FROM external_prescription_items WHERE prescription_id = ?");
            $epiStmt->execute([$ep['id']]);
            $epiRows = $epiStmt->fetchAll();
            foreach ($epiRows as $epi) {
                $items[] = [
                    'drug' => $epi['drug'] ?? '',
                    'dosage' => $epi['dosage'] ?? '',
                    'frequency' => $epi['frequency'] ?? '',
                    'duration' => $epi['duration'] ?? '',
                    'quantity' => $epi['quantity'] ?? ''
                ];
            }
        }

        success([
            'has_prescription' => !empty($items),
            'visit' => $visit,
            'doctor_name' => $doctorName,
            'department_name' => $deptName,
            'prescription_date' => $prescDate,
            'notes' => $notes,
            'items' => $items
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
                   COALESCE(ic.id, v.insurance_id) AS company_id,
                   COALESCE(ic.name, '') AS company_name,
                   COALESCE(ip.name, '') AS package_name,
                   COALESCE(ip.copayment_percentage, 100) AS copayment_percentage,
                   COALESCE(pi.member_number, pi_fallback.member_number, (SELECT pi3.member_number FROM patient_insurance pi3 WHERE pi3.patient_id = v.patient_id ORDER BY pi3.is_primary DESC, pi3.id DESC LIMIT 1), '') AS member_number,
                   COALESCE((
                       SELECT 1 FROM consultations c 
                       WHERE c.visit_id = v.id 
                         AND c.prescription IS NOT NULL 
                         AND c.prescription != '' 
                         AND c.prescription != '[]' 
                         AND c.prescription != 'null' 
                       LIMIT 1
                   ), (
                       SELECT 1 FROM external_prescriptions ep 
                       WHERE ep.visit_id = v.id 
                         AND ep.status = 'active' 
                       LIMIT 1
                   ), 0) AS has_prescription
            FROM visits v
            JOIN patients p ON p.id = v.patient_id
            LEFT JOIN departments d ON d.id = v.department_id
            LEFT JOIN patient_insurance pi ON pi.patient_id = v.patient_id AND (pi.id = v.insurance_id OR pi.company_id = v.insurance_id)
            LEFT JOIN patient_insurance pi_fallback ON pi_fallback.patient_id = v.patient_id AND pi_fallback.is_primary = 1 AND pi.id IS NULL
            LEFT JOIN insurance_companies ic ON ic.id = COALESCE(pi.company_id, pi_fallback.company_id, v.insurance_id)
            LEFT JOIN insurance_packages ip ON ip.id = COALESCE(pi.package_id, pi_fallback.package_id)
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
    $limit       = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $offset      = ($page - 1) * $limit;

    $where = []; $params = [];
    if ($search !== '') {
        $like = "%$search%";
        $where[] = "(v.visit_no LIKE ? OR CONCAT(p.last_name,' ',p.first_name) LIKE ? OR CONCAT(p.first_name,' ',p.last_name) LIKE ? OR p.patient_no LIKE ? OR pi.member_number LIKE ? OR pi_fallback.member_number LIKE ? OR ic.name LIKE ?)";
        $params  = array_merge($params, [$like,$like,$like,$like,$like,$like,$like]);
    }
    if ($patientId > 0) { $where[] = "v.patient_id = ?"; $params[] = $patientId; }
    // status may be a comma list, e.g. "open,in_progress" (nurse screens: unpaid open visits count too)
    $statusList = array_values(array_filter(array_map('trim', explode(',', $status))));
    if ($statusList) { $where[] = "v.status IN (" . implode(',', array_fill(0, count($statusList), '?')) . ")"; $params = array_merge($params, $statusList); }
    // Skip date filter for active/in_progress visits — they should show regardless of start date
    $skipDateFilter = $statusList && !array_diff($statusList, ['open', 'in_progress']);
    if (!$skipDateFilter) {
        if ($date !== '')     { $where[] = "v.visit_date = ?";  $params[] = $date; }
        if ($dateFrom !== '')  { $where[] = "v.visit_date >= ?"; $params[] = $dateFrom; }
        if ($dateTo !== '')    { $where[] = "v.visit_date <= ?"; $params[] = $dateTo; }
    }
    $paymentType = $_GET['payment_type'] ?? '';
    if ($paymentType !== '') { $where[] = "v.payment_type = ?"; $params[] = $paymentType; }
    // Visit frequency: patient's total visits in the whole system (1, 2, >5, >20); date range only picks which visits are listed
    $freq = [ '1' => '= 1', '2' => '= 2', 'gt5' => '> 5', 'gt20' => '> 20' ][$_GET['visit_count'] ?? ''] ?? '';
    if ($freq !== '') $where[] = "v.patient_id IN (SELECT vc.patient_id FROM visits vc GROUP BY vc.patient_id HAVING COUNT(*) $freq)";
    $ws = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $cnt = $pdo->prepare("SELECT COUNT(DISTINCT v.id) FROM visits v JOIN patients p ON p.id = v.patient_id LEFT JOIN patient_insurance pi ON pi.patient_id = v.patient_id AND (pi.id = v.insurance_id OR pi.company_id = v.insurance_id) LEFT JOIN patient_insurance pi_fallback ON pi_fallback.patient_id = v.patient_id AND pi_fallback.is_primary = 1 AND pi.id IS NULL LEFT JOIN insurance_companies ic ON ic.id = COALESCE(pi.company_id, pi_fallback.company_id, v.insurance_id) $ws");
    $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT v.id, v.visit_no, v.visit_date, v.visit_time, v.payment_type, v.tariff_category,
               v.insurance_status, v.visit_type, v.status, v.chief_complaint,
               v.department_id, v.doctor_id, v.doctor_name, v.insurance_id, v.opened_by, v.cancelled_by, v.completed_by,
               v.created_at, v.cancelled_at, v.completed_at,
               COALESCE(CONCAT(ob.first_name,' ',ob.last_name), ob.username) AS opened_by_name,
               COALESCE(CONCAT(cb.first_name,' ',cb.last_name), cb.username) AS cancelled_by_name,
               COALESCE(CONCAT(cob.first_name,' ',cob.last_name), cob.username) AS completed_by_name,
               p.id AS patient_id, p.first_name, p.last_name, p.patient_no, p.phone,
               d.name AS department_name,
               COALESCE(ic.id, v.insurance_id) AS company_id,
               COALESCE(ic.name, '') AS company_name,
               COALESCE(ip.copayment_percentage, 100) AS copayment_percentage,
               COALESCE(ip.name, '') AS package_name,
               COALESCE(pi.member_number, pi_fallback.member_number, (SELECT pi3.member_number FROM patient_insurance pi3 WHERE pi3.patient_id = v.patient_id ORDER BY pi3.is_primary DESC, pi3.id DESC LIMIT 1), '') AS member_number,
               COALESCE((SELECT SUM(inv.balance) FROM invoices inv WHERE inv.visit_id = v.id AND inv.status IN ('issued','partial')), 0) AS pending_amount,
               COALESCE((SELECT SUM(inv.paid_amount) FROM invoices inv WHERE inv.visit_id = v.id), 0) AS paid_amount,
               COALESCE((SELECT COUNT(*) FROM invoices inv WHERE inv.visit_id = v.id), 0) AS invoice_count,
               COALESCE((
                   SELECT 1 FROM consultations c 
                   WHERE c.visit_id = v.id 
                     AND c.prescription IS NOT NULL 
                     AND c.prescription != '' 
                     AND c.prescription != '[]' 
                     AND c.prescription != 'null' 
                   LIMIT 1
               ), (
                   SELECT 1 FROM external_prescriptions ep 
                   WHERE ep.visit_id = v.id 
                     AND ep.status = 'active' 
                   LIMIT 1
               ), 0) AS has_prescription
        FROM visits v
        JOIN patients p ON p.id = v.patient_id
        LEFT JOIN departments d ON d.id = v.department_id
        LEFT JOIN users ob ON ob.id = v.opened_by
        LEFT JOIN users cb ON cb.id = v.cancelled_by
        LEFT JOIN users cob ON cob.id = v.completed_by
        LEFT JOIN patient_insurance pi ON pi.id = (
            SELECT pi2.id FROM patient_insurance pi2 
            WHERE pi2.patient_id = v.patient_id AND (pi2.id = v.insurance_id OR pi2.company_id = v.insurance_id)
            ORDER BY (pi2.id = v.insurance_id) DESC, pi2.is_primary DESC, pi2.id DESC 
            LIMIT 1
        )
        LEFT JOIN patient_insurance pi_fallback ON pi_fallback.id = (
            SELECT pi3.id FROM patient_insurance pi3 
            WHERE pi3.patient_id = v.patient_id AND pi3.is_primary = 1
            ORDER BY pi3.id DESC 
            LIMIT 1
        ) AND pi.id IS NULL
        LEFT JOIN insurance_companies ic ON ic.id = COALESCE(pi.company_id, pi_fallback.company_id, v.insurance_id)
        LEFT JOIN insurance_packages ip ON ip.id = COALESCE(pi.package_id, pi_fallback.package_id)
        $ws ORDER BY v.visit_date DESC, v.visit_time DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $visits = $stmt->fetchAll();

    foreach ($visits as &$v) {
        $v['patient_name'] = $v['first_name'] . ' ' . $v['last_name'];
    }

    $stats = $pdo->query("
        SELECT COUNT(*) AS total,
               SUM(CASE WHEN status='open' THEN 1 ELSE 0 END) AS open_visits,
               SUM(CASE WHEN status='in_progress' THEN 1 ELSE 0 END) AS in_progress,
               SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed,
               SUM(CASE WHEN visit_date >= CURDATE() AND visit_date < CURDATE() + INTERVAL 1 DAY THEN 1 ELSE 0 END) AS today
        FROM visits
    ")->fetch();

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
              AND v.visit_date >= CURDATE() AND v.visit_date < CURDATE() + INTERVAL 1 DAY
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

        log_activity($pdo, 'UPDATE', 'department', 'Called next patient: ' . $next['patient_name'] . ' (Visit: ' . $next['visit_no'] . ', Queue: ' . $next['queue_no'] . ')', $next['id'], $next['visit_no']);
        success($next, 'Patient called');
    }

    // POST /visits?action=add_note_comment — comment on a doctor's notes (comments only: notes are never edited here)
    if (isset($_GET['action']) && $_GET['action'] === 'add_note_comment') {
        ensure_referral_schema($pdo);
        $vid = (int)($input['visit_id'] ?? 0);
        $text = trim($input['comment'] ?? '');
        if (!$vid || $text === '') error('visit_id and comment are required', 422);
        $pdo->prepare("INSERT INTO clinical_note_comments (visit_id, consultation_id, user_id, comment) VALUES (?, ?, ?, ?)")
            ->execute([$vid, !empty($input['consultation_id']) ? (int)$input['consultation_id'] : null, (int)($_SESSION['user_id'] ?? 0) ?: null, $text]);
        success(['id' => (int)$pdo->lastInsertId()], 'Comment added', 201);
    }

    // POST /visits?action=add_authorization — insurer authorization letter; the authorized amount raises the coverage limit
    if (isset($_GET['action']) && $_GET['action'] === 'add_authorization') {
        ensure_insurance_authorizations($pdo);
        $piId = (int)($input['patient_insurance_id'] ?? 0);
        $amt  = (float)($input['authorized_amount'] ?? 0);
        if (!$piId || $amt <= 0) error('Insurance and a positive authorized amount are required', 422);
        $vf = trim((string)($input['valid_from'] ?? '')); $vt = trim((string)($input['valid_to'] ?? ''));
        if ($vf !== '' && !strtotime($vf)) error('valid_from is not a valid date', 422);
        if ($vt !== '' && !strtotime($vt)) error('valid_to is not a valid date', 422);
        if ($vf !== '' && $vt !== '' && $vt < $vf) error('The authorization cannot end before it starts', 422);
        if ($vt !== '' && $vt < date('Y-m-d')) error('This authorization has already expired (valid to ' . $vt . ').', 422);
        $an = trim((string)($input['auth_no'] ?? ''));
        if ($an !== '') {
            ensure_insurance_authorizations($pdo);
            $ad = $pdo->prepare("SELECT 1 FROM insurance_authorizations WHERE auth_no = ? LIMIT 1");
            $ad->execute([$an]);
            if ($ad->fetchColumn()) error("Authorization number $an has already been recorded.", 409);
        }
        $chk = $pdo->prepare("SELECT id FROM patient_insurance WHERE id = ?");
        $chk->execute([$piId]);
        if (!$chk->fetch()) error('Insurance record not found', 404);
        $d = fn($k) => !empty($input[$k]) ? $input[$k] : null;
        $pdo->prepare("INSERT INTO insurance_authorizations (patient_insurance_id, visit_id, auth_no, authorized_amount, valid_from, valid_to, document_id, created_by) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$piId, !empty($input['visit_id']) ? (int)$input['visit_id'] : null, trim($input['auth_no'] ?? '') ?: null, $amt, $d('valid_from'), $d('valid_to'), !empty($input['document_id']) ? (int)$input['document_id'] : null, (int)($_SESSION['user_id'] ?? 0) ?: null]);
        $authId = (int)$pdo->lastInsertId();
        log_activity($pdo, 'CREATE', 'visits', 'Insurance authorization added: ' . $amt . ' for patient_insurance #' . $piId, $authId);
        success(['id' => $authId], 'Authorization saved', 201);
    }

    // POST /visits — Open Visit

    if (empty($input['patient_id'])) error('patient_id is required', 422);
    if (empty($input['department_id'])) error('department_id is required', 422);
    if (in_array($input['payment_type'] ?? '', ['insurance', 'mixed'], true) && trim((string)($input['member_number'] ?? '')) === '')
        error('Member Number / Card No. is required for insurance visits', 422);
    if (isset($input['principal_phone'])) $input['principal_phone'] = preg_replace('/\D/', '', (string)$input['principal_phone']);
    $patientStatus = in_array($input['patient_status'] ?? '', ['active', 'admitted', 'critical'], true) ? $input['patient_status'] : null;
    // 3/14-day rule: the fee must match it (free within 3 days, follow-up within 14, else full)
    try {
        consultCheckService($pdo, consultRule($pdo, (int)$input['patient_id'], (int)$input['department_id'])['rule'],
            !empty($input['consultation_service_id']) ? (int)$input['consultation_service_id'] : null);
    } catch (RuntimeException $e) { error($e->getMessage(), 422); }

    $pid = (int)$input['patient_id'];

    // Verify patient exists
    $pStmt = $pdo->prepare("SELECT id, first_name, last_name, patient_no, tariff_category FROM patients WHERE id = ?");
    $pStmt->execute([$pid]);
    $patient = $pStmt->fetch();
    if (!$patient) error('Patient not found', 404);

    // One active visit per patient, enforced here as well as in the browser (DAT-05)
    $act = $pdo->prepare("SELECT visit_no FROM visits WHERE patient_id = ? AND status IN ('open','in_progress') LIMIT 1");
    $act->execute([$pid]);
    if ($activeNo = $act->fetchColumn()) error("This patient already has an active visit ($activeNo). Complete or cancel it first.", 409);

    // Get current user ID from session
    $userId = $_SESSION['user_id'] ?? null;

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
        } else if (empty($input['insurance_id'])) {
            // No insurance selected and no insurance on file
            $paymentType = 'self_pay';
            $insNote     = 'No insurance selected — self-pay';
        }
    }

    $visitNo = generateVisitNo($pdo);
    $deptId  = !empty($input['department_id']) ? (int)$input['department_id'] : null;

    require_once __DIR__ . '/../../includes/hmis_schema.php';
    hmis_ensure_schema($pdo); // outside the transaction: DDL commits
    ensureVisitInsurers($pdo);
    $refFrom = in_array($input['referred_from'] ?? '', ['facility','school_counselor'], true) ? $input['referred_from'] : null;
    $refName = $refFrom ? (mb_substr(trim((string)($input['referred_from_name'] ?? '')), 0, 150) ?: null) : null;

    $pdo->beginTransaction();
    $stmt = $pdo->prepare("
        INSERT INTO visits
            (visit_no, patient_id, visit_date, visit_time, payment_type, insurance_id,
             insurance_status, insurance_note, department_id, visit_type,
             status, chief_complaint, notes, opened_by, created_at, tariff_category, doctor_id, doctor_name, referred_from, referred_from_name)
        VALUES
            (?, ?, CURDATE(), CURTIME(), ?, ?, ?, ?, ?, ?, 'open', ?, ?, ?, NOW(), ?, ?, ?, ?, ?)
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
        $userId,
        $patient['tariff_category'],
        !empty($input['doctor_id']) ? (int)$input['doctor_id'] : null,
        !empty($input['doctor_id']) ? (trim($input['doctor_name'] ?? '') ?: null) : null,
        $refFrom,
        $refName,
    ]);
    $visitId = (int)$pdo->lastInsertId();
    if ($patientStatus) $pdo->prepare("UPDATE patients SET status = ? WHERE id = ?")->execute([$patientStatus, $pid]);
    log_activity($pdo, 'CREATE', 'visits',
        'Visit opened: ' . $visitNo . ' for ' . $patient['first_name'] . ' ' . $patient['last_name'] .
        ' (' . $patient['patient_no'] . ')' .
        ' | Type: ' . ($input['visit_type'] ?? 'outpatient') .
        ' | Payment: ' . $paymentType .
        ($insStatus ? ' | Insurance: ' . $insStatus : '') .
        ' | Opened by: ' . ($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''),
        $visitId, $visitNo
    );

    // Insurance details are captured here only: save company/plan/member no. on the patient's primary insurance row.
    // policy_number/start/end are NOT NULL legacy columns, filled with placeholders on insert.
    if ($insId && ($paymentType === 'insurance' || $paymentType === 'mixed') && !empty($input['insurance_id'])) {
        $primaryPi = saveVisitInsurance($pdo, $pid, $insId, $input);
        saveVisitExtras($pdo, $visitId, $pid, $insId, $primaryPi, $input);
    }

    // Consultation fee picked at the desk: goes straight to the cashier as an invoice (rolled back with the visit on error)
    if (!empty($input['consultation_service_id'])) {
        $cs = $pdo->prepare("SELECT 1 FROM service_catalog WHERE id = ? AND speciality = 'Consultation' AND is_active = 1");
        $cs->execute([(int)$input['consultation_service_id']]);
        if (!$cs->fetchColumn() || !createConsultationInvoice($pdo, $visitId, (int)$input['consultation_service_id'], $userId ? (int)$userId : null)) {
            $pdo->rollBack(); error('Invalid consultation fee', 422);
        }
    }

    // Follow-up appointment: opening the visit is the arrival confirmation (rolled back with the visit on error)
    if (!empty($input['appointment_id'])) {
        require_once __DIR__ . '/../../includes/appointments.php';
        try { ap_mark_arrived($pdo, (int)$input['appointment_id'], $visitId, $pid, (int)$userId); }
        catch (RuntimeException $e) { $pdo->rollBack(); error($e->getMessage(), (int)$e->getCode() ?: 400); }
    }

    $pdo->commit();

    success([
        'id'           => $visitId,
        'appointment_id' => !empty($input['appointment_id']) ? (int)$input['appointment_id'] : null,
        'visit_no'     => $visitNo,
        'patient_no'   => $patient['patient_no'],
        'patient_name' => $patient['first_name'] . ' ' . $patient['last_name'],
        'payment_type' => $paymentType,
        'tariff_category' => $patient['tariff_category'],
        'insurance'    => $insCheck['has_insurance'] ? $insCheck['insurance_data'] : null,
        'insurance_status' => $insStatus,
        'insurance_note'   => $insNote,
        'member_number'    => !empty($input['member_number']) ? trim($input['member_number']) : ($insCheck['insurance_data']['member_number'] ?? '')
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
        // Get visit details before updating
        $vChk = $pdo->prepare("SELECT v.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name, p.patient_no FROM visits v JOIN patients p ON p.id = v.patient_id WHERE v.id = ?");
        $vChk->execute([$id]);
        $visitDetails = $vChk->fetch();
        
        $userId = $_SESSION['user_id'] ?? null;
        $pdo->prepare("UPDATE visits SET status='completed', closed_at=NOW(), completed_by=?, completed_at=NOW() WHERE id = ?")->execute([$userId, $id]);
        record_lock($pdo, 'visit', $id, 'Visit closed; changes require an approved edit request', (int)$userId);
        
        // Get user name for detailed logging
        $userStmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as user_name FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $userData = $userStmt->fetch();
        $completedByName = $userData ? $userData['user_name'] : ('User #' . $userId);
        
        log_activity($pdo, 'UPDATE', 'visits', 
            'Visit completed: ' . ($visitDetails ? $visitDetails['visit_no'] . ' for ' . $visitDetails['patient_name'] . ' (' . $visitDetails['patient_no'] . ')' : 'Visit #' . $id) .
            ' | Completed by: ' . $completedByName,
            $id, $visitDetails ? $visitDetails['visit_no'] : null);
        success(null, 'Visit closed');
    }

    // Correct the payment/insurance of a visit that is still open or in progress
    if ($action === 'change_insurance') {
        if (!in_array($visit['status'], ['open', 'in_progress'], true)) error('Only open or in-progress visits can be edited', 409);
        $vRow = $pdo->prepare("SELECT patient_id FROM visits WHERE id = ?");
        $vRow->execute([$id]);
        $pid = (int)$vRow->fetchColumn();

        $paymentType = $input['payment_type'] ?? 'self_pay';
        if (!in_array($paymentType, ['self_pay', 'insurance', 'mixed'], true)) error('Invalid payment type', 422);
        $insId = null; $insStatus = null; $insNote = null;
        if ($paymentType !== 'self_pay') {
            $insId = (int)($input['insurance_id'] ?? 0);
            if (!$insId) error('Insurance company is required', 422);
            if (empty($input['insurance_plan_id'])) error('Plan / Package is required', 422);
            if (trim((string)($input['member_number'] ?? '')) === '') error('Member Number / Card No. is required for insurance visits', 422);
            $insStatus = 'valid';
            $insNote = 'Insurance corrected on open visit';
        }

        ensureVisitInsurers($pdo);
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE visits SET payment_type = ?, insurance_id = ?, insurance_status = ?, insurance_note = ? WHERE id = ?")
            ->execute([$paymentType, $insId, $insStatus, $insNote, $id]);
        if ($insId) saveVisitExtras($pdo, $id, $pid, $insId, saveVisitInsurance($pdo, $pid, $insId, $input), $input); else $pdo->prepare("DELETE FROM visit_insurances WHERE visit_id = ?")->execute([$id]);
        $pdo->commit();

        log_activity($pdo, 'UPDATE', 'visits', 'Visit #' . $id . ' insurance changed to ' . $paymentType . ($insId ? ' (company ' . $insId . ')' : ''), $id);
        success(null, 'Visit insurance updated');
    }

    // Triage sets / changes the consultation fee of a department (3/14-day rule checked again here)
    if ($action === 'set_consultation_fee') {
        $deptId = (int)($input['department_id'] ?? 0);
        $serviceId = !empty($input['service_id']) ? (int)$input['service_id'] : null;
        if (!$deptId) error('department_id is required', 422);
        if (!in_array($visit['status'], ['open', 'in_progress'], true)) error('Only open or in-progress visits can be billed here', 409);
        $pq = $pdo->prepare("SELECT patient_id FROM visits WHERE id = ?");
        $pq->execute([$id]);
        $pdo->beginTransaction();
        try {
            consultCheckService($pdo, consultRule($pdo, (int)$pq->fetchColumn(), $deptId, $id)['rule'], $serviceId);
            $res = setVisitConsultationFee($pdo, $id, $deptId, $serviceId, (int)($_SESSION['user_id'] ?? 0) ?: null);
            $pdo->commit();
        } catch (RuntimeException $e) { $pdo->rollBack(); error($e->getMessage(), 422); }
        log_activity($pdo, 'UPDATE', 'visits', 'Visit #' . $id . ' consultation fee (dept ' . $deptId . '): ' . $res['note'] . ($res['invoice_no'] ? ' ' . $res['invoice_no'] : ''), $id);
        success($res, $res['note']);
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
        $sessUser = (int)($_SESSION['user_id'] ?? 0) ?: null; // the sender is the signed-in user, never the request body
        ensure_referral_schema($pdo);
        $priority = in_array($input['priority'] ?? '', ['urgent', 'critical'], true) ? $input['priority'] : 'routine';
        $crit = ($priority !== 'routine' && !empty($input['critical_info']) && is_array($input['critical_info'])) ? json_encode($input['critical_info'], JSON_UNESCAPED_UNICODE) : null;
        $chk = $pdo->prepare("SELECT id FROM visit_department_history WHERE visit_id = ? AND department_id = ? AND status = 'pending'");
        $chk->execute([$id, $deptId]);
        $created = !$chk->fetch(); // false = patient already waiting for this department: no second row (callers must not bill again)
        if ($created) {
            $pdo->prepare("
                INSERT INTO visit_department_history (visit_id, patient_id, department_id, assigned_by, assigned_to, status, doctor_notes, referred_by, priority, critical_info, referred_at)
                VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, NOW())
            ")->execute([$id, $visit['patient_id'], $deptId, $sessUser, $doctorId, $input['notes'] ?? null, $sessUser, $priority, $crit]);
            if ($priority !== 'routine') {
                $pn = $pdo->prepare("SELECT CONCAT(first_name,' ',last_name) FROM patients WHERE id = ?");
                $pn->execute([$visit['patient_id']]);
                $pdo->prepare("INSERT INTO notifications (type, title, message, visit_id, patient_id, target_role) VALUES ('critical_referral', ?, ?, ?, ?, 'doctor')")
                    ->execute([strtoupper($priority) . ' referral', ($pn->fetchColumn() ?: 'Patient') . ' referred by nurse' . (!empty($input['critical_info']['condition']) ? ': ' . $input['critical_info']['condition'] : ''), $id, $visit['patient_id']]);
            }
        }

        $pdo->prepare("UPDATE visits SET department_id=?, doctor_id=COALESCE(?, doctor_id), doctor_name=?, consultation_status='waiting', status='in_progress' WHERE id=?")
            ->execute([$deptId, $doctorId ?: null, $doctorName ?: null, $id]);

        // Create a consultation record so the doctor sees the patient
        $existConsult = $pdo->prepare("SELECT id FROM consultations WHERE visit_id=? AND department_id=?");
        $existConsult->execute([$id, $deptId]);
        if (!$existConsult->fetch()) {
            $pdo->prepare("
                INSERT INTO consultations (visit_id, patient_id, department_id, doctor_id, doctor_name, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'draft', NOW())
            ")->execute([$id, $visit['patient_id'], $deptId, $doctorId, $doctorName ?: null]);
        }

        log_activity($pdo, 'UPDATE', 'visits', 'Visit #' . $id . ' assigned to consultation: dept_id=' . $deptId . ($doctorName ? ', doctor=' . $doctorName : ''), $id, null);
        success(['created' => $created], 'Consultation assigned');
    }

    if ($action === 'send_to_department') {
        $deptId  = isset($input['department_id']) ? (int)$input['department_id'] : null;
        $userId  = (int)($_SESSION['user_id'] ?? 0) ?: null; // the sender is the signed-in user, never the request body
        $notes   = $input['notes'] ?? null;
        ensure_referral_schema($pdo);
        if (!$deptId) error('department_id is required', 422);
        $dc = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE id = ? AND is_active = 1");
        $dc->execute([$deptId]);
        if (!$dc->fetchColumn()) error('That department does not exist or is not active.', 422);

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
            INSERT INTO visit_department_history (visit_id, patient_id, department_id, assigned_by, assigned_to, status, doctor_notes, referred_by, priority, referred_at)
            VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, 'routine', NOW())
        ")->execute([$id, $visit['patient_id'], $deptId, $userId, $input['assigned_to'] ?? null, $notes, $userId]);

        // Get next queue number for the department today
        $qStmt = $pdo->prepare("
            SELECT COALESCE(MAX(CAST(SUBSTRING(queue_no, 3) AS UNSIGNED)), 0) + 1
            FROM visits 
            WHERE department_id = ? AND visit_date >= CURDATE() AND visit_date < CURDATE() + INTERVAL 1 DAY AND queue_no IS NOT NULL
        ");
        $qStmt->execute([$deptId]);
        $nextQ = str_pad($qStmt->fetchColumn(), 2, '0', STR_PAD_LEFT);
        $queueNo = 'Q' . $nextQ;

        // Update visit with department, queue info, and consultation_status
        $pdo->prepare("UPDATE visits SET department_id = ?, status = 'in_progress', queue_no = ?, queued_at = NOW(), consultation_status = 'waiting' WHERE id = ?")
            ->execute([$deptId, $queueNo, $id]);

        log_activity($pdo, 'UPDATE', 'visits', 'Visit #' . $id . ' sent to department ID=' . $deptId . ', queue: ' . $queueNo, $id, $queueNo);
        success(['queue_no' => $queueNo], 'Patient sent to department successfully');
    }

    $fields = []; $params = [];
    $allowed = ['payment_type','insurance_id','insurance_status','insurance_note','department_id','doctor_id','doctor_name','visit_type','status','chief_complaint','notes','consultation_status'];
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
        // Add cancelled_by and cancelled_at when cancelling
        $userId = $_SESSION['user_id'] ?? null;
        $fields[] = "cancelled_by = ?";
        $fields[] = "cancelled_at = NOW()";
        $params[] = $userId;
    }

    // Handle completion - add completed_by and completed_at when marking as completed
    if (isset($input['status']) && $input['status'] === 'completed') {
        $userId = $_SESSION['user_id'] ?? null;
        $fields[] = "completed_by = ?";
        $fields[] = "completed_at = NOW()";
        $params[] = $userId;
    }

    $params[] = $id;
    $pdo->prepare("UPDATE visits SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
    
    // Create detailed logs based on what was updated
    $logDescription = 'Visit #' . $id . ' updated';
    $visitRef = null;
    
    if (isset($input['status'])) {
        // Get visit details for detailed logging
        $vChk = $pdo->prepare("SELECT v.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name, p.patient_no FROM visits v JOIN patients p ON p.id = v.patient_id WHERE v.id = ?");
        $vChk->execute([$id]);
        $visitDetails = $vChk->fetch();
        $visitRef = $visitDetails ? $visitDetails['visit_no'] : null;
        $patientInfo = $visitDetails ? $visitDetails['patient_name'] . ' (' . $visitDetails['patient_no'] . ')' : '';
        
        if ($input['status'] === 'cancelled') {
            $userId = $_SESSION['user_id'] ?? null;
            $userStmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as user_name FROM users WHERE id = ?");
            $userStmt->execute([$userId]);
            $userData = $userStmt->fetch();
            $cancelledByName = $userData ? $userData['user_name'] : ('User #' . $userId);
            
            $logDescription = 'Visit cancelled: ' . ($visitRef ?: 'Visit #' . $id) . ' for ' . $patientInfo . ' | Cancelled by: ' . $cancelledByName;
        } elseif ($input['status'] === 'completed') {
            $userId = $_SESSION['user_id'] ?? null;
            $userStmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as user_name FROM users WHERE id = ?");
            $userStmt->execute([$userId]);
            $userData = $userStmt->fetch();
            $completedByName = $userData ? $userData['user_name'] : ('User #' . $userId);
            
            $logDescription = 'Visit completed: ' . ($visitRef ?: 'Visit #' . $id) . ' for ' . $patientInfo . ' | Completed by: ' . $completedByName;
        }
    }
    
    log_activity($pdo, 'UPDATE', 'visits', $logDescription, $id, $visitRef);
    success(null, 'Visit updated');
}

// ===== DELETE =====
if ($method === 'DELETE') {
    if (!$id) error('Visit ID required', 422);
    $chk = $pdo->prepare("SELECT id, visit_no FROM visits WHERE id = ?");
    $chk->execute([$id]);
    $delVisit = $chk->fetch();
    if (!$delVisit) error('Visit not found', 404);
    $pdo->prepare("DELETE FROM visits WHERE id = ?")->execute([$id]);
    log_activity($pdo, 'DELETE', 'visits', 'Visit deleted: ' . $delVisit['visit_no'], $id, $delVisit['visit_no']);
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

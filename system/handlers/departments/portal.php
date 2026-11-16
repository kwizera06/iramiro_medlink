<?php
// handlers/departments/portal.php
// Comprehensive department portal endpoints: dashboard, queue, forms, reports, and RBAC
require_once __DIR__ . '/../../includes/database.php';

function jsonResponse($data, $status = 200) { http_response_code($status); echo json_encode($data); exit(); }
function success($data = null, $msg = 'Success', $status = 200) { jsonResponse(['success'=>true,'message'=>$msg,'data'=>$data], $status); }
function error($msg, $status = 400) { jsonResponse(['success'=>false,'message'=>$msg], $status); }

$method      = $_SERVER['REQUEST_METHOD'];
$action      = $_GET['action'] ?? '';
$id          = isset($_GET['id']) ? (int)$_GET['id'] : null;

// ============================================================
// ROLE-BASED ACCESS CONTROL
// ============================================================

$userId      = (int)($_SESSION['user_id'] ?? 0) ?: null;
$roleSlugs   = array_map('strtolower', (array)($_SESSION['user_data']['role_slugs'] ?? []));
$userRole    = $_SESSION['user_data']['role'] ?? ($roleSlugs[0] ?? ($_SESSION['user_role'] ?? 'user'));
if (in_array('admin', $roleSlugs, true)) $userRole = 'admin';
$userDeptId  = $_SESSION['user_data']['department_id'] ?? ($_SESSION['user_department_id'] ?? null);

if (!$userId) {
    error('Unauthorized: Please log in', 401);
}

// The session does not always carry the department; read it from the user row.
if (!$userDeptId) {
    $dq = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
    $dq->execute([$userId]);
    $userDeptId = $dq->fetchColumn() ?: null;
}

function checkDepartmentAccess($deptId, $userId, $userDeptId, $userRole) {
    if ($userRole === 'admin') return true;
    if (in_array($userRole, ['department_head', 'doctor', 'nurse'])) {
        return $deptId == $userDeptId;
    }
    return false;
}

// ============================================================
// HELPER FUNCTIONS
// ============================================================

/**
 * Get all visit IDs that belong to a department.
 * Returns an array of integer visit IDs, or empty array.
 */
function getVisitIdsByDepartment($pdo, $deptId) {
    $stmt = $pdo->prepare("SELECT id FROM visits WHERE department_id = ?");
    $stmt->execute([$deptId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

/**
 * Get all visit IDs that belong to a department on a specific date.
 */
function getVisitIdsByDepartmentAndDate($pdo, $deptId, $date) {
    $stmt = $pdo->prepare("SELECT id FROM visits WHERE department_id = ? AND DATE(created_at) = ?");
    $stmt->execute([$deptId, $date]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

/**
 * Get all visit IDs for a department within a date range.
 */
function getVisitIdsByDepartmentAndRange($pdo, $deptId, $dateFrom, $dateTo) {
    $stmt = $pdo->prepare("SELECT id FROM visits WHERE department_id = ? AND DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$deptId, $dateFrom, $dateTo]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

/**
 * Get invoice stats (total, paid, pending, count) for a set of visit IDs on a specific date.
 * Returns associative array with keys: total_revenue, pending, paid, invoice_count.
 */
function getInvoiceStatsByVisitIdsAndDate($pdo, $visitIds, $date) {
    if (empty($visitIds)) {
        return ['total_revenue' => 0, 'pending' => 0, 'paid' => 0, 'invoice_count' => 0];
    }
    $placeholders = implode(',', array_fill(0, count($visitIds), '?'));
    $params = array_merge($visitIds, [$date]);
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN status<>'cancelled' THEN total ELSE 0 END), 0) AS total_revenue,
            COALESCE(SUM(CASE WHEN status NOT IN ('paid','cancelled') THEN balance ELSE 0 END), 0) AS pending,
            COALESCE(SUM(CASE WHEN status<>'cancelled' THEN paid_amount ELSE 0 END), 0) AS paid,
            COUNT(*)                                                               AS invoice_count
        FROM invoices
        WHERE visit_id IN ($placeholders) AND DATE(created_at) = ?
    ");
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get invoice stats grouped by date for a set of visit IDs within a date range.
 * Returns array of rows: date, invoice_count, total_amount, paid_amount, pending_amount, cancelled_amount.
 */
function getInvoiceStatsByVisitIdsAndRange($pdo, $visitIds, $dateFrom, $dateTo) {
    if (empty($visitIds)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($visitIds), '?'));
    $params = array_merge($visitIds, [$dateFrom, $dateTo]);
    $stmt = $pdo->prepare("
        SELECT
            DATE(created_at)                                                              AS date,
            COUNT(DISTINCT id)                                                            AS invoice_count,
            COALESCE(SUM(CASE WHEN status<>'cancelled' THEN total ELSE 0 END), 0)         AS total_amount,
            COALESCE(SUM(CASE WHEN status<>'cancelled' THEN paid_amount ELSE 0 END), 0)   AS paid_amount,
            COALESCE(SUM(CASE WHEN status NOT IN ('paid','cancelled') THEN balance ELSE 0 END), 0) AS pending_amount,
            COALESCE(SUM(CASE WHEN status='cancelled' THEN total ELSE 0 END), 0)          AS cancelled_amount
        FROM invoices
        WHERE visit_id IN ($placeholders) AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get total invoice amount for a set of visit IDs — used in performance metrics.
 */
function getInvoiceTotalByVisitIds($pdo, $visitIds) {
    if (empty($visitIds)) return 0;
    $placeholders = implode(',', array_fill(0, count($visitIds), '?'));
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) AS total FROM invoices WHERE visit_id IN ($placeholders) AND status<>'cancelled'");
    $stmt->execute($visitIds);
    return (float) $stmt->fetchColumn();
}

/**
 * Get a single patient row by ID.
 */
function getPatientById($pdo, $patientId) {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, patient_no, phone, date_of_birth, gender FROM patients WHERE id = ?");
    $stmt->execute([$patientId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get a single visit row by ID.
 */
function getVisitById($pdo, $visitId) {
    $stmt = $pdo->prepare("SELECT id, visit_no, visit_type, chief_complaint, payment_type FROM visits WHERE id = ?");
    $stmt->execute([$visitId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get department name by ID.
 */
function getDepartmentName($pdo, $deptId) {
    $stmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
    $stmt->execute([$deptId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['name'] : null;
}

/**
 * Get latest consultation for a visit ID.
 */
function getConsultationByVisitId($pdo, $visitId) {
    $stmt = $pdo->prepare("SELECT id, status FROM consultations WHERE visit_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$visitId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get form template name and type by ID.
 */
function getFormTemplate($pdo, $templateId) {
    $stmt = $pdo->prepare("SELECT id, name, form_type FROM department_form_templates WHERE id = ?");
    $stmt->execute([$templateId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

try {

// ============================================================
// 1. DASHBOARD DATA
// ============================================================

if ($method === 'GET' && $action === 'dashboard') {
    $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : $userDeptId;

    if (!$deptId) error('department_id required', 422);
    if (!checkDepartmentAccess($deptId, $userId, $userDeptId, $userRole)) {
        error('Access denied to this department', 403);
    }

    $today = date('Y-m-d');

    // Queue statistics — no JOIN needed, department_id is on patient_queue
    $qStmt = $pdo->prepare("
        SELECT
            SUM(CASE WHEN status='waiting'        THEN 1 ELSE 0 END) AS waiting,
            SUM(CASE WHEN status='called'         THEN 1 ELSE 0 END) AS called,
            SUM(CASE WHEN status='in_consultation' THEN 1 ELSE 0 END) AS in_consultation,
            SUM(CASE WHEN status='done'           THEN 1 ELSE 0 END) AS done,
            COUNT(*) AS total
        FROM patient_queue
        WHERE department_id = ? AND queue_date = ?
    ");
    $qStmt->execute([$deptId, $today]);
    $queueStats = $qStmt->fetch(PDO::FETCH_ASSOC);

    // Revenue today — get visit IDs first, then query invoices separately
    $todayVisitIds  = getVisitIdsByDepartmentAndDate($pdo, $deptId, $today);
    $revenueStats   = getInvoiceStatsByVisitIdsAndDate($pdo, $todayVisitIds, $today);

    // Active consultations — get visit IDs for dept, then count consultations
    $allVisitIds    = getVisitIdsByDepartment($pdo, $deptId);
    $consultCount   = 0;
    if (!empty($allVisitIds)) {
        $placeholders = implode(',', array_fill(0, count($allVisitIds), '?'));
        $cStmt = $pdo->prepare("
            SELECT COUNT(*) AS count
            FROM consultations
            WHERE visit_id IN ($placeholders)
              AND status IN ('started', 'in_progress')
              AND DATE(created_at) = ?
        ");
        $cStmt->execute(array_merge($allVisitIds, [$today]));
        $consultCount = (int) $cStmt->fetch(PDO::FETCH_ASSOC)['count'];
    }

    // Department info
    $dStmt = $pdo->prepare("SELECT id, name, head_of_department AS head_id FROM departments WHERE id = ?");
    $dStmt->execute([$deptId]);
    $deptInfo = $dStmt->fetch(PDO::FETCH_ASSOC);

    // Performance metrics (this week)
    $startOfWeek = date('Y-m-d', strtotime('monday this week'));

    $pStmt = $pdo->prepare("
        SELECT
            COUNT(DISTINCT id)                                                  AS consultations_this_week,
            COALESCE(AVG(TIMESTAMPDIFF(MINUTE, called_at, done_at)), 0)        AS avg_consult_minutes
        FROM patient_queue
        WHERE department_id = ? AND DATE(queue_date) >= ? AND status = 'done'
    ");
    $pStmt->execute([$deptId, $startOfWeek]);
    $perfMetrics = $pStmt->fetch(PDO::FETCH_ASSOC);

    // Revenue this week — separate invoice query
    $weekVisitIds = getVisitIdsByDepartmentAndRange($pdo, $deptId, $startOfWeek, $today);
    $perfMetrics['revenue_this_week'] = getInvoiceTotalByVisitIds($pdo, $weekVisitIds);

    success([
        'department'          => $deptInfo,
        'queue_stats'         => $queueStats,
        'revenue_stats'       => $revenueStats,
        'active_consultations'=> $consultCount,
        'performance_metrics' => $perfMetrics,
        'current_date'        => $today
    ]);
}

// ============================================================
// 2. QUEUE MANAGEMENT
// ============================================================

if ($method === 'GET' && $action === 'queue_list') {
    $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : $userDeptId;
    $date   = $_GET['date']   ?? date('Y-m-d');
    $status = $_GET['status'] ?? '';
    $limit  = (int)($_GET['limit']  ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);

    if (!$deptId) error('department_id required', 422);
    if (!checkDepartmentAccess($deptId, $userId, $userDeptId, $userRole)) {
        error('Access denied', 403);
    }

    $where  = ['department_id = ?', 'queue_date = ?'];
    $params = [$deptId, $date];

    if ($status) {
        $where[]  = 'status = ?';
        $params[] = $status;
    }
    $ws = 'WHERE ' . implode(' AND ', $where);

    // Fetch queue rows — no JOINs
    $countStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM patient_queue $ws");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    $listStmt = $pdo->prepare("
        SELECT id, queue_no, status, called_at, started_at, done_at, created_at,
               patient_id, visit_id, department_id
        FROM patient_queue
        $ws
        ORDER BY queue_no ASC
        LIMIT ? OFFSET ?
    ");
    $listStmt->execute(array_merge($params, [$limit, $offset]));
    $queueRows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

    // Enrich each row with related data via single-record helper functions
    $deptName = getDepartmentName($pdo, $deptId);
    foreach ($queueRows as &$row) {
        // Patient
        $patient = getPatientById($pdo, $row['patient_id']);
        $row['patient_id']    = $patient['id']            ?? null;
        $row['first_name']    = $patient['first_name']    ?? null;
        $row['last_name']     = $patient['last_name']     ?? null;
        $row['patient_no']    = $patient['patient_no']    ?? null;
        $row['phone']         = $patient['phone']         ?? null;
        $row['date_of_birth'] = $patient['date_of_birth'] ?? null;
        $row['gender']        = $patient['gender']        ?? null;

        // Visit
        $visit = getVisitById($pdo, $row['visit_id']);
        $row['visit_id']       = $visit['id']             ?? null;
        $row['visit_no']       = $visit['visit_no']       ?? null;
        $row['visit_type']     = $visit['visit_type']     ?? null;
        $row['chief_complaint']= $visit['chief_complaint']?? null;
        $row['payment_type']   = $visit['payment_type']   ?? null;

        // Department name
        $row['department_name'] = $deptName;

        // Consultation
        $consult = getConsultationByVisitId($pdo, $row['visit_id']);
        $row['consultation_id']  = $consult['id']     ?? null;
        $row['consult_status']   = $consult['status'] ?? null;

        unset($row['department_id']); // already exposed as department_name
    }
    unset($row);

    success([
        'queue'      => $queueRows,
        'pagination' => [
            'limit'  => $limit,
            'offset' => $offset,
            'total'  => $total,
            'pages'  => ceil($total / $limit)
        ]
    ]);
}

// POST /portal?action=call_next&department_id=X
if ($method === 'POST' && $action === 'call_next') {
    $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : $userDeptId;
    $date   = $_GET['date'] ?? date('Y-m-d');

    if (!$deptId) error('department_id required', 422);
    if (!checkDepartmentAccess($deptId, $userId, $userDeptId, $userRole)) {
        error('Access denied', 403);
    }

    $stmt = $pdo->prepare("
        SELECT id, queue_no FROM patient_queue
        WHERE department_id = ? AND queue_date = ? AND status = 'waiting'
        ORDER BY queue_no ASC
        LIMIT 1
    ");
    $stmt->execute([$deptId, $date]);
    $nextPatient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$nextPatient) error('No waiting patients', 404);

    $pdo->prepare("UPDATE patient_queue SET status = 'called', called_at = NOW() WHERE id = ?")
        ->execute([$nextPatient['id']]);

    success($nextPatient, 'Patient called');
}

// POST /portal?action=start_consultation&queue_id=X
if ($method === 'POST' && $action === 'start_consultation') {
    $queueId = isset($_GET['queue_id']) ? (int)$_GET['queue_id'] : null;

    if (!$queueId) error('queue_id required', 422);

    $qStmt = $pdo->prepare("SELECT department_id FROM patient_queue WHERE id = ?");
    $qStmt->execute([$queueId]);
    $queue = $qStmt->fetch(PDO::FETCH_ASSOC);

    if (!$queue) error('Queue not found', 404);
    if (!checkDepartmentAccess($queue['department_id'], $userId, $userDeptId, $userRole)) {
        error('Access denied', 403);
    }

    $pdo->prepare("UPDATE patient_queue SET status = 'in_consultation', started_at = NOW() WHERE id = ?")
        ->execute([$queueId]);

    success(['queue_id' => $queueId], 'Consultation started');
}

// POST /portal?action=complete_consultation&queue_id=X
if ($method === 'POST' && $action === 'complete_consultation') {
    $queueId = isset($_GET['queue_id']) ? (int)$_GET['queue_id'] : null;

    if (!$queueId) error('queue_id required', 422);

    $qStmt = $pdo->prepare("SELECT department_id FROM patient_queue WHERE id = ?");
    $qStmt->execute([$queueId]);
    $queue = $qStmt->fetch(PDO::FETCH_ASSOC);

    if (!$queue) error('Queue not found', 404);
    if (!checkDepartmentAccess($queue['department_id'], $userId, $userDeptId, $userRole)) {
        error('Access denied', 403);
    }

    $pdo->prepare("UPDATE patient_queue SET status = 'done', done_at = NOW() WHERE id = ?")
        ->execute([$queueId]);

    success(['queue_id' => $queueId], 'Consultation completed');
}

// ============================================================
// 3. DEPARTMENT FORMS DISPLAY
// ============================================================

if ($method === 'GET' && $action === 'forms_display') {
    $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : $userDeptId;

    if (!$deptId) error('department_id required', 422);
    if (!checkDepartmentAccess($deptId, $userId, $userDeptId, $userRole)) {
        error('Access denied', 403);
    }

    $stmt = $pdo->prepare("
        SELECT id, name, description, form_type, status, sort_order, department_id
        FROM department_form_templates
        WHERE department_id = ? AND status = 'active'
        ORDER BY sort_order ASC
    ");
    $stmt->execute([$deptId]);
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($templates as &$template) {
        $fStmt = $pdo->prepare("
            SELECT id, field_name, field_type, required, options, sort_order
            FROM department_form_fields
            WHERE template_id = ?
            ORDER BY sort_order ASC
        ");
        $fStmt->execute([$template['id']]);
        $template['fields'] = $fStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($template);

    success($templates);
}

if ($method === 'GET' && $action === 'form_response') {
    $deptId  = isset($_GET['department_id']) ? (int)$_GET['department_id'] : $userDeptId;
    $visitId = isset($_GET['visit_id'])      ? (int)$_GET['visit_id']      : null;

    if (!$deptId || !$visitId) error('department_id and visit_id required', 422);
    if (!checkDepartmentAccess($deptId, $userId, $userDeptId, $userRole)) {
        error('Access denied', 403);
    }

    // Verify visit belongs to department — single query, no JOIN
    $vStmt = $pdo->prepare("SELECT id FROM visits WHERE id = ? AND department_id = ?");
    $vStmt->execute([$visitId, $deptId]);
    if (!$vStmt->fetch()) error('Visit not found in this department', 404);

    // Get submitted form responses — no JOIN, look up template separately
    $stmt = $pdo->prepare("
        SELECT id, template_id, visit_id, submitted_at
        FROM department_form_responses
        WHERE visit_id = ?
        ORDER BY submitted_at DESC
    ");
    $stmt->execute([$visitId]);
    $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($responses as &$response) {
        // Template info via helper — no JOIN
        $tpl = getFormTemplate($pdo, $response['template_id']);
        $response['template_name'] = $tpl['name']      ?? null;
        $response['form_type']     = $tpl['form_type'] ?? null;

        // Response field data
        $dStmt = $pdo->prepare("
            SELECT field_id, field_name, field_value
            FROM department_form_response_data
            WHERE response_id = ?
        ");
        $dStmt->execute([$response['id']]);
        $response['data'] = $dStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($response);

    success($responses);
}

if ($method === 'POST' && $action === 'submit_form_response') {
    $data       = json_decode(file_get_contents('php://input'), true) ?? [];
    $deptId     = $data['department_id'] ?? $userDeptId;
    $visitId    = $data['visit_id']      ?? null;
    $templateId = $data['template_id']   ?? null;
    $formData   = $data['form_data']     ?? [];

    if (!$deptId || !$visitId || !$templateId) {
        error('department_id, visit_id, and template_id required', 422);
    }
    if (!checkDepartmentAccess($deptId, $userId, $userDeptId, $userRole)) {
        error('Access denied', 403);
    }

    $vStmt = $pdo->prepare("SELECT id FROM visits WHERE id = ? AND department_id = ?");
    $vStmt->execute([$visitId, $deptId]);
    if (!$vStmt->fetch()) error('Visit not found', 404);

    $tStmt = $pdo->prepare("SELECT id FROM department_form_templates WHERE id = ? AND department_id = ?");
    $tStmt->execute([$templateId, $deptId]);
    if (!$tStmt->fetch()) error('Template not found', 404);

    $rStmt = $pdo->prepare("
        INSERT INTO department_form_responses (template_id, visit_id, submitted_by, submitted_at, created_at)
        VALUES (?, ?, ?, NOW(), NOW())
    ");
    $rStmt->execute([$templateId, $visitId, $userId]);
    $responseId = $pdo->lastInsertId();

    $dStmt = $pdo->prepare("
        INSERT INTO department_form_response_data (response_id, field_id, field_name, field_value, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    foreach ($formData as $fieldId => $value) {
        $dStmt->execute([$responseId, $fieldId, $fieldId, json_encode($value)]);
    }

    success(['response_id' => $responseId], 'Form submitted successfully');
}

// ============================================================
// 4. REPORTS & ANALYTICS
// ============================================================

if ($method === 'GET' && $action === 'reports') {
    $deptId     = isset($_GET['department_id']) ? (int)$_GET['department_id'] : $userDeptId;
    $reportType = $_GET['report_type'] ?? 'daily';
    $dateFrom   = $_GET['date_from']   ?? date('Y-m-d', strtotime('-30 days'));
    $dateTo     = $_GET['date_to']     ?? date('Y-m-d');

    if (!$deptId) error('department_id required', 422);
    if (!checkDepartmentAccess($deptId, $userId, $userDeptId, $userRole)) {
        error('Access denied', 403);
    }

    $result = [];

    // Consultation volume — no invoice/visit JOIN needed
    if ($reportType === 'daily' || $reportType === 'all') {
        $stmt = $pdo->prepare("
            SELECT
                DATE(queue_date)                                                         AS date,
                COUNT(*)                                                                 AS total_patients,
                SUM(CASE WHEN status='done'     THEN 1 ELSE 0 END)                      AS completed,
                SUM(CASE WHEN status='waiting'  THEN 1 ELSE 0 END)                      AS still_waiting,
                SUM(CASE WHEN status='no_show'  THEN 1 ELSE 0 END)                      AS no_shows,
                COALESCE(AVG(TIMESTAMPDIFF(MINUTE, called_at, done_at)), 0)             AS avg_consult_time_min
            FROM patient_queue
            WHERE department_id = ? AND DATE(queue_date) BETWEEN ? AND ?
            GROUP BY DATE(queue_date)
            ORDER BY date DESC
        ");
        $stmt->execute([$deptId, $dateFrom, $dateTo]);
        $result['consultation_volume'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Revenue report — get visit IDs first, then query invoices with no JOIN
    if ($reportType === 'daily' || $reportType === 'all') {
        $rangeVisitIds         = getVisitIdsByDepartmentAndRange($pdo, $deptId, $dateFrom, $dateTo);
        $result['revenue']     = getInvoiceStatsByVisitIdsAndRange($pdo, $rangeVisitIds, $dateFrom, $dateTo);
    }

    // Performance metrics — no JOIN needed
    if ($reportType === 'all') {
        $stmt = $pdo->prepare("
            SELECT
                COUNT(DISTINCT patient_id)                                               AS unique_patients,
                COUNT(*)                                                                 AS total_consultations,
                SUM(CASE WHEN status='done'    THEN 1 ELSE 0 END)                       AS completed_consultations,
                COALESCE(AVG(TIMESTAMPDIFF(MINUTE, called_at, done_at)), 0)             AS avg_time_per_patient,
                COALESCE(SUM(CASE WHEN status='no_show' THEN 1 ELSE 0 END), 0)          AS no_shows,
                ROUND(
                    COALESCE(SUM(CASE WHEN status='done' THEN 1 ELSE 0 END), 0)
                    / COUNT(*) * 100, 2
                )                                                                        AS completion_rate
            FROM patient_queue
            WHERE department_id = ? AND DATE(queue_date) BETWEEN ? AND ?
        ");
        $stmt->execute([$deptId, $dateFrom, $dateTo]);
        $result['performance'] = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Forms submitted — no invoice JOIN, template looked up via helper
    if ($reportType === 'all') {
        $stmt = $pdo->prepare("
            SELECT
                template_id,
                COUNT(id)         AS submissions,
                MAX(submitted_at) AS last_submitted
            FROM department_form_responses
            WHERE DATE(submitted_at) BETWEEN ? AND ?
            GROUP BY template_id
            ORDER BY submissions DESC
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $rawForms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Filter to this department and attach template name
        $deptTemplates = [];
        $stmt2 = $pdo->prepare("SELECT id FROM department_form_templates WHERE department_id = ?");
        $stmt2->execute([$deptId]);
        $deptTemplateIds = $stmt2->fetchAll(PDO::FETCH_COLUMN);
        $deptTemplateIds = array_flip($deptTemplateIds); // for O(1) lookup

        $formRows = [];
        foreach ($rawForms as $row) {
            if (!isset($deptTemplateIds[$row['template_id']])) continue;
            $tpl = getFormTemplate($pdo, $row['template_id']);
            $row['form_name'] = $tpl['name'] ?? null;
            unset($row['template_id']);
            $formRows[] = $row;
        }
        $result['forms_submitted'] = $formRows;
    }

    success([
        'department_id' => $deptId,
        'date_from'     => $dateFrom,
        'date_to'       => $dateTo,
        'report_type'   => $reportType,
        'data'          => $result
    ]);
}

// ============================================================
// FALLTHROUGH
// ============================================================

success(null, 'Action not recognized');

} catch (Exception $e) {
    error('Error: ' . $e->getMessage(), 500);
}
?>

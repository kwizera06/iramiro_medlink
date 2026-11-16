<?php
// handlers/departments/portal.php
// Comprehensive department portal endpoints: dashboard, queue, forms, reports, and RBAC
require_once __DIR__ . '/../../includes/database.php';

function jsonResponse($data, $status = 200) { http_response_code($status); echo json_encode($data); exit(); }
function success($data = null, $msg = 'Success', $status = 200) { jsonResponse(['success'=>true,'message'=>$msg,'data'=>$data], $status); }
function error($msg, $status = 400) { jsonResponse(['success'=>false,'message'=>$msg], $status); }

$method   = $_SERVER['REQUEST_METHOD'];
$action   = $_GET['action'] ?? '';
$id       = isset($_GET['id']) ? (int)$_GET['id'] : null;

// ============================================================
// ROLE-BASED ACCESS CONTROL
// ============================================================

// Get current user info from session/cookie
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? 'user';
$userDeptId = $_SESSION['user_department_id'] ?? null;

if (!$userId) {
    error('Unauthorized: Please log in', 401);
}

// Check if user has permission for this department
function checkDepartmentAccess($deptId, $userId, $userDeptId, $userRole) {
    // Admin can access all departments
    if ($userRole === 'admin') return true;
    // Department staff can only access their own department
    if ($userRole === 'department_head' || $userRole === 'doctor' || $userRole === 'nurse') {
        return $deptId == $userDeptId;
    }
    return false;
}

try {

// ============================================================
// 1. DASHBOARD DATA
// ============================================================

// GET /portal?action=dashboard&department_id=X
if ($method === 'GET' && $action === 'dashboard') {
    $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : $userDeptId;
    
    if (!$deptId) error('department_id required', 422);
    if (!checkDepartmentAccess($deptId, $userId, $userDeptId, $userRole)) {
        error('Access denied to this department', 403);
    }
    
    $today = date('Y-m-d');
    
    // Queue statistics
    $qStmt = $pdo->prepare("
        SELECT
            SUM(CASE WHEN status='waiting' THEN 1 ELSE 0 END) AS waiting,
            SUM(CASE WHEN status='called' THEN 1 ELSE 0 END) AS called,
            SUM(CASE WHEN status='in_consultation' THEN 1 ELSE 0 END) AS in_consultation,
            SUM(CASE WHEN status='done' THEN 1 ELSE 0 END) AS done,
            COUNT(*) AS total
        FROM patient_queue
        WHERE department_id = ? AND queue_date = ?
    ");
    $qStmt->execute([$deptId, $today]);
    $queueStats = $qStmt->fetch();
    
    // Revenue today
    $rStmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(amount), 0) AS total_revenue,
            COALESCE(SUM(CASE WHEN status='pending' THEN amount ELSE 0 END), 0) AS pending,
            COALESCE(SUM(CASE WHEN status='paid' THEN amount ELSE 0 END), 0) AS paid,
            COUNT(*) AS invoice_count
        FROM invoices i
        JOIN patient_visits v ON v.id = i.visit_id
        WHERE v.department_id = ? AND DATE(i.created_at) = ?
    ");
    $rStmt->execute([$deptId, $today]);
    $revenueStats = $rStmt->fetch();
    
    // Active consultations
    $cStmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM consultations c
        JOIN patient_visits v ON v.id = c.visit_id
        WHERE v.department_id = ? AND c.status IN ('started', 'in_progress') AND DATE(c.created_at) = ?
    ");
    $cStmt->execute([$deptId, $today]);
    $consultCount = $cStmt->fetch()['count'];
    
    // Department info
    $dStmt = $pdo->prepare("SELECT id, name, head_id FROM departments WHERE id = ?");
    $dStmt->execute([$deptId]);
    $deptInfo = $dStmt->fetch();
    
    // Performance metrics (this week)
    $startOfWeek = date('Y-m-d', strtotime('monday this week'));
    $pStmt = $pdo->prepare("
        SELECT
            COUNT(DISTINCT pq.id) AS consultations_this_week,
            COALESCE(SUM(i.amount), 0) AS revenue_this_week,
            COALESCE(AVG(TIMESTAMPDIFF(MINUTE, pq.called_at, pq.done_at)), 0) AS avg_consult_minutes
        FROM patient_queue pq
        LEFT JOIN patient_visits v ON v.id = pq.visit_id
        LEFT JOIN invoices i ON i.visit_id = v.id
        WHERE pq.department_id = ? AND DATE(pq.queue_date) >= ? AND pq.status = 'done'
    ");
    $pStmt->execute([$deptId, $startOfWeek]);
    $perfMetrics = $pStmt->fetch();
    
    success([
        'department' => $deptInfo,
        'queue_stats' => $queueStats,
        'revenue_stats' => $revenueStats,
        'active_consultations' => $consultCount,
        'performance_metrics' => $perfMetrics,
        'current_date' => $today
    ]);
}

// ============================================================
// 2. QUEUE MANAGEMENT
// ============================================================

// GET /portal?action=queue_list&department_id=X&date=Y&status=Z
if ($method === 'GET' && $action === 'queue_list') {
    $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : $userDeptId;
    $date = $_GET['date'] ?? date('Y-m-d');
    $status = $_GET['status'] ?? '';
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);
    
    if (!$deptId) error('department_id required', 422);
    if (!checkDepartmentAccess($deptId, $userId, $userDeptId, $userRole)) {
        error('Access denied', 403);
    }
    
    $where = ['pq.department_id = ?', 'pq.queue_date = ?'];
    $params = [$deptId, $date];
    
    if ($status) {
        $where[] = 'pq.status = ?';
        $params[] = $status;
    }
    $ws = 'WHERE ' . implode(' AND ', $where);
    
    $stmt = $pdo->prepare("
        SELECT 
            pq.id, pq.queue_no, pq.status, pq.called_at, pq.started_at, pq.done_at, pq.created_at,
            p.id AS patient_id, p.first_name, p.last_name, p.patient_no, p.phone, p.date_of_birth, p.gender,
            v.id AS visit_id, v.visit_no, v.visit_type, v.chief_complaint, v.payment_type,
            d.name AS department_name,
            c.id AS consultation_id, c.status AS consult_status
        FROM patient_queue pq
        LEFT JOIN patients p ON p.id = pq.patient_id
        LEFT JOIN patient_visits v ON v.id = pq.visit_id
        LEFT JOIN departments d ON d.id = pq.department_id
        LEFT JOIN consultations c ON c.visit_id = v.id
        $ws
        ORDER BY pq.queue_no ASC
        LIMIT ? OFFSET ?
    ");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $queueList = $stmt->fetchAll();
    
    // Total count
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total FROM patient_queue pq
        $ws
    ");
    $countStmt->execute(array_slice($params, 0, -2));
    $total = $countStmt->fetch()['total'];
    
    success([
        'queue' => $queueList,
        'pagination' => [
            'limit' => $limit,
            'offset' => $offset,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

// POST /portal?action=call_next&department_id=X
if ($method === 'POST' && $action === 'call_next') {
    $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : $userDeptId;
    $date = $_GET['date'] ?? date('Y-m-d');
    
    if (!$deptId) error('department_id required', 422);
    if (!checkDepartmentAccess($deptId, $userId, $userDeptId, $userRole)) {
        error('Access denied', 403);
    }
    
    // Get next waiting patient (if any already called, get next in line)
    $stmt = $pdo->prepare("
        SELECT pq.id, pq.queue_no FROM patient_queue pq
        WHERE pq.department_id = ? AND pq.queue_date = ? AND pq.status = 'waiting'
        ORDER BY pq.queue_no ASC
        LIMIT 1
    ");
    $stmt->execute([$deptId, $date]);
    $nextPatient = $stmt->fetch();
    
    if (!$nextPatient) {
        error('No waiting patients', 404);
    }
    
    // Update to 'called' status
    $updateStmt = $pdo->prepare("
        UPDATE patient_queue 
        SET status = 'called', called_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([$nextPatient['id']]);
    
    success($nextPatient, 'Patient called');
}

// POST /portal?action=start_consultation&queue_id=X
if ($method === 'POST' && $action === 'start_consultation') {
    $queueId = isset($_GET['queue_id']) ? (int)$_GET['queue_id'] : null;
    
    if (!$queueId) error('queue_id required', 422);
    
    // Verify the queue entry belongs to user's department
    $qStmt = $pdo->prepare("SELECT department_id FROM patient_queue WHERE id = ?");
    $qStmt->execute([$queueId]);
    $queue = $qStmt->fetch();
    
    if (!$queue) error('Queue not found', 404);
    if (!checkDepartmentAccess($queue['department_id'], $userId, $userDeptId, $userRole)) {
        error('Access denied', 403);
    }
    
    $upStmt = $pdo->prepare("
        UPDATE patient_queue 
        SET status = 'in_consultation', started_at = NOW()
        WHERE id = ?
    ");
    $upStmt->execute([$queueId]);
    
    success(['queue_id' => $queueId], 'Consultation started');
}

// POST /portal?action=complete_consultation&queue_id=X
if ($method === 'POST' && $action === 'complete_consultation') {
    $queueId = isset($_GET['queue_id']) ? (int)$_GET['queue_id'] : null;
    
    if (!$queueId) error('queue_id required', 422);
    
    $qStmt = $pdo->prepare("SELECT department_id FROM patient_queue WHERE id = ?");
    $qStmt->execute([$queueId]);
    $queue = $qStmt->fetch();
    
    if (!$queue) error('Queue not found', 404);
    if (!checkDepartmentAccess($queue['department_id'], $userId, $userDeptId, $userRole)) {
        error('Access denied', 403);
    }
    
    $upStmt = $pdo->prepare("
        UPDATE patient_queue 
        SET status = 'done', done_at = NOW()
        WHERE id = ?
    ");
    $upStmt->execute([$queueId]);
    
    success(['queue_id' => $queueId], 'Consultation completed');
}

// ============================================================
// 3. DEPARTMENT FORMS DISPLAY
// ============================================================

// GET /portal?action=forms_display&department_id=X
if ($method === 'GET' && $action === 'forms_display') {
    $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : $userDeptId;
    
    if (!$deptId) error('department_id required', 422);
    if (!checkDepartmentAccess($deptId, $userId, $userDeptId, $userRole)) {
        error('Access denied', 403);
    }
    
    // Get all active form templates for this department
    $stmt = $pdo->prepare("
        SELECT 
            t.id, t.name, t.description, t.form_type, t.status, t.sort_order, t.department_id
        FROM department_form_templates t
        WHERE t.department_id = ? AND t.status = 'active'
        ORDER BY t.sort_order ASC
    ");
    $stmt->execute([$deptId]);
    $templates = $stmt->fetchAll();
    
    // Enhance with field details
    foreach ($templates as &$template) {
        $fStmt = $pdo->prepare("
            SELECT id, field_name, field_type, required, options, sort_order
            FROM department_form_fields
            WHERE template_id = ?
            ORDER BY sort_order ASC
        ");
        $fStmt->execute([$template['id']]);
        $template['fields'] = $fStmt->fetchAll();
    }
    
    success($templates);
}

// GET /portal?action=form_response&department_id=X&visit_id=Y
if ($method === 'GET' && $action === 'form_response') {
    $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : $userDeptId;
    $visitId = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : null;
    
    if (!$deptId || !$visitId) error('department_id and visit_id required', 422);
    if (!checkDepartmentAccess($deptId, $userId, $userDeptId, $userRole)) {
        error('Access denied', 403);
    }
    
    // Verify visit belongs to department
    $vStmt = $pdo->prepare("SELECT id FROM patient_visits WHERE id = ? AND department_id = ?");
    $vStmt->execute([$visitId, $deptId]);
    if (!$vStmt->fetch()) error('Visit not found in this department', 404);
    
    // Get submitted form responses
    $stmt = $pdo->prepare("
        SELECT 
            r.id, r.template_id, r.visit_id, r.submitted_at,
            t.name as template_name, t.form_type
        FROM department_form_responses r
        JOIN department_form_templates t ON t.id = r.template_id
        WHERE r.visit_id = ?
        ORDER BY r.submitted_at DESC
    ");
    $stmt->execute([$visitId]);
    $responses = $stmt->fetchAll();
    
    // Get response data for each
    foreach ($responses as &$response) {
        $dStmt = $pdo->prepare("
            SELECT field_id, field_name, field_value
            FROM department_form_response_data
            WHERE response_id = ?
        ");
        $dStmt->execute([$response['id']]);
        $response['data'] = $dStmt->fetchAll();
    }
    
    success($responses);
}

// POST /portal?action=submit_form_response
if ($method === 'POST' && $action === 'submit_form_response') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    
    $deptId = $data['department_id'] ?? $userDeptId;
    $visitId = $data['visit_id'] ?? null;
    $templateId = $data['template_id'] ?? null;
    $formData = $data['form_data'] ?? [];
    
    if (!$deptId || !$visitId || !$templateId) {
        error('department_id, visit_id, and template_id required', 422);
    }
    if (!checkDepartmentAccess($deptId, $userId, $userDeptId, $userRole)) {
        error('Access denied', 403);
    }
    
    // Verify visit and template
    $vStmt = $pdo->prepare("SELECT id FROM patient_visits WHERE id = ? AND department_id = ?");
    $vStmt->execute([$visitId, $deptId]);
    if (!$vStmt->fetch()) error('Visit not found', 404);
    
    $tStmt = $pdo->prepare("SELECT id FROM department_form_templates WHERE id = ? AND department_id = ?");
    $tStmt->execute([$templateId, $deptId]);
    if (!$tStmt->fetch()) error('Template not found', 404);
    
    // Create response record
    $rStmt = $pdo->prepare("
        INSERT INTO department_form_responses (template_id, visit_id, submitted_by, submitted_at, created_at)
        VALUES (?, ?, ?, NOW(), NOW())
    ");
    $rStmt->execute([$templateId, $visitId, $userId]);
    $responseId = $pdo->lastInsertId();
    
    // Insert form field data
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

// GET /portal?action=reports&department_id=X&report_type=Y&date_from=Z&date_to=W
if ($method === 'GET' && $action === 'reports') {
    $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : $userDeptId;
    $reportType = $_GET['report_type'] ?? 'daily'; // daily, weekly, monthly, custom
    $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
    $dateTo = $_GET['date_to'] ?? date('Y-m-d');
    
    if (!$deptId) error('department_id required', 422);
    if (!checkDepartmentAccess($deptId, $userId, $userDeptId, $userRole)) {
        error('Access denied', 403);
    }
    
    $result = [];
    
    // Consultation volume report
    if ($reportType === 'daily' || $reportType === 'all') {
        $stmt = $pdo->prepare("
            SELECT 
                DATE(pq.queue_date) as date,
                COUNT(*) as total_patients,
                SUM(CASE WHEN pq.status='done' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN pq.status='waiting' THEN 1 ELSE 0 END) as still_waiting,
                SUM(CASE WHEN pq.status='no_show' THEN 1 ELSE 0 END) as no_shows,
                COALESCE(AVG(TIMESTAMPDIFF(MINUTE, pq.called_at, pq.done_at)), 0) as avg_consult_time_min
            FROM patient_queue pq
            WHERE pq.department_id = ? AND DATE(pq.queue_date) BETWEEN ? AND ?
            GROUP BY DATE(pq.queue_date)
            ORDER BY date DESC
        ");
        $stmt->execute([$deptId, $dateFrom, $dateTo]);
        $result['consultation_volume'] = $stmt->fetchAll();
    }
    
    // Revenue report
    if ($reportType === 'daily' || $reportType === 'all') {
        $stmt = $pdo->prepare("
            SELECT 
                DATE(i.created_at) as date,
                COUNT(DISTINCT i.id) as invoice_count,
                COALESCE(SUM(i.amount), 0) as total_amount,
                COALESCE(SUM(CASE WHEN i.status='paid' THEN i.amount ELSE 0 END), 0) as paid_amount,
                COALESCE(SUM(CASE WHEN i.status='pending' THEN i.amount ELSE 0 END), 0) as pending_amount,
                COALESCE(SUM(CASE WHEN i.status='cancelled' THEN i.amount ELSE 0 END), 0) as cancelled_amount
            FROM invoices i
            JOIN patient_visits v ON v.id = i.visit_id
            WHERE v.department_id = ? AND DATE(i.created_at) BETWEEN ? AND ?
            GROUP BY DATE(i.created_at)
            ORDER BY date DESC
        ");
        $stmt->execute([$deptId, $dateFrom, $dateTo]);
        $result['revenue'] = $stmt->fetchAll();
    }
    
    // Performance metrics
    if ($reportType === 'all') {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT pq.patient_id) as unique_patients,
                COUNT(*) as total_consultations,
                SUM(CASE WHEN pq.status='done' THEN 1 ELSE 0 END) as completed_consultations,
                COALESCE(AVG(TIMESTAMPDIFF(MINUTE, pq.called_at, pq.done_at)), 0) as avg_time_per_patient,
                COALESCE(SUM(CASE WHEN pq.status='no_show' THEN 1 ELSE 0 END), 0) as no_shows,
                ROUND(COALESCE(SUM(CASE WHEN pq.status='done' THEN 1 ELSE 0 END), 0) / COUNT(*) * 100, 2) as completion_rate
            FROM patient_queue pq
            WHERE pq.department_id = ? AND DATE(pq.queue_date) BETWEEN ? AND ?
        ");
        $stmt->execute([$deptId, $dateFrom, $dateTo]);
        $result['performance'] = $stmt->fetch();
    }
    
    // Forms submitted
    if ($reportType === 'all') {
        $stmt = $pdo->prepare("
            SELECT 
                t.name as form_name,
                COUNT(r.id) as submissions,
                MAX(r.submitted_at) as last_submitted
            FROM department_form_responses r
            JOIN department_form_templates t ON t.id = r.template_id
            WHERE t.department_id = ? AND DATE(r.submitted_at) BETWEEN ? AND ?
            GROUP BY t.id, t.name
            ORDER BY submissions DESC
        ");
        $stmt->execute([$deptId, $dateFrom, $dateTo]);
        $result['forms_submitted'] = $stmt->fetchAll();
    }
    
    success([
        'department_id' => $deptId,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'report_type' => $reportType,
        'data' => $result
    ]);
}

// ============================================================
// ERROR HANDLING
// ============================================================

success(null, 'Action not recognized');

} catch (Exception $e) {
    error('Error: ' . $e->getMessage(), 500);
}
?>

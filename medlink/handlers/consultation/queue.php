<?php
// handlers/consultation/queue.php
require_once __DIR__ . '/../../includes/database.php';
function jsonResponse($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
function success($d=null,$m='Success',$s=200){jsonResponse(['success'=>true,'message'=>$m,'data'=>$d],$s);}
function error($m,$s=400){jsonResponse(['success'=>false,'message'=>$m],$s);}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {

// GET — queue list or stats
if ($method === 'GET') {
    $deptId   = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
    $date     = $_GET['date'] ?? date('Y-m-d');
    $status   = $_GET['status'] ?? '';

    // Stats for a dept today
    if ($action === 'stats') {
        if (!$deptId) error('department_id required', 422);
        $stmt = $pdo->prepare("
            SELECT
                SUM(status='waiting')         AS waiting,
                SUM(status='called')          AS called,
                SUM(status='in_consultation') AS in_consultation,
                SUM(status='done')            AS done,
                COUNT(*)                      AS total
            FROM patient_queue
            WHERE department_id=? AND queue_date=?
        ");
        $stmt->execute([$deptId, $date]);
        success($stmt->fetch());
    }

    // Full queue list
    $where = ['pq.queue_date=?']; $params = [$date];
    if ($deptId)  { $where[] = 'pq.department_id=?'; $params[] = $deptId; }
    if ($status)  { $where[] = 'pq.status=?';        $params[] = $status; }
    $ws = 'WHERE '.implode(' AND ',$where);

    $stmt = $pdo->prepare("
        SELECT pq.id, pq.queue_no, pq.status, pq.called_at, pq.started_at, pq.done_at, pq.created_at,
               p.id AS patient_id, p.first_name, p.last_name, p.patient_no, p.phone, p.date_of_birth, p.gender,
               p.allergies, p.chronic_diseases,
               v.id AS visit_id, v.visit_no, v.visit_type, v.chief_complaint, v.payment_type, v.insurance_id,
               COALESCE(ic.name, ic2.name, ic3.name) AS company_name,
               COALESCE(ip.name, ip2.name, ip3.name) AS package_name,
               COALESCE(ip.copayment_percentage, ip2.copayment_percentage, ip3.copayment_percentage, 100) AS copayment_percentage,
               d.name AS department_name,
               c.id AS consultation_id, c.status AS consult_status
        FROM patient_queue pq
        JOIN patients p  ON p.id  = pq.patient_id
        JOIN visits v    ON v.id  = pq.visit_id
        LEFT JOIN patient_insurance pi ON pi.id = v.insurance_id AND pi.patient_id = v.patient_id
        LEFT JOIN insurance_companies ic ON ic.id = pi.company_id
        LEFT JOIN insurance_packages ip ON ip.id = pi.package_id
        LEFT JOIN patient_insurance pi2 ON pi2.company_id = v.insurance_id AND pi2.patient_id = v.patient_id AND pi.id IS NULL
        LEFT JOIN insurance_companies ic2 ON ic2.id = pi2.company_id AND pi.id IS NULL
        LEFT JOIN insurance_packages ip2 ON ip2.id = pi2.package_id AND pi.id IS NULL
        LEFT JOIN patient_insurance pi3 ON pi3.patient_id = v.patient_id AND pi3.is_primary = 1 AND pi.id IS NULL AND pi2.id IS NULL
        LEFT JOIN insurance_companies ic3 ON ic3.id = pi3.company_id AND pi.id IS NULL AND pi2.id IS NULL
        LEFT JOIN insurance_packages ip3 ON ip3.id = pi3.package_id AND pi.id IS NULL AND pi2.id IS NULL
        JOIN departments d ON d.id = pq.department_id
        LEFT JOIN consultations c ON c.visit_id = pq.visit_id AND c.department_id = pq.department_id
        $ws
        ORDER BY pq.id ASC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $dob = $r['date_of_birth'];
        if ($dob) {
            $d2 = new DateTime($dob); $now = new DateTime();
            $r['age'] = $d2->diff($now)->y.'y';
        } else { $r['age'] = '—'; }
    }

    success($rows);
}

// PUT — update queue status (call, start, done, skip)
if ($method === 'PUT') {
    if (!$id) error('Queue ID required', 422);
    $input  = json_decode(file_get_contents('php://input'), true) ?? [];
    $status = $input['status'] ?? '';
    $allowed = ['called','in_consultation','done','skipped','waiting'];
    if (!in_array($status, $allowed)) error('Invalid status', 422);

    $timeField = '';
    switch ($status) {
        case 'called':          $timeField = 'called_at=NOW(),'; break;
        case 'in_consultation': $timeField = 'started_at=NOW(),'; break;
        case 'done':            $timeField = 'done_at=NOW(),'; break;
        default:                $timeField = ''; break;
    }

    $pdo->prepare("UPDATE patient_queue SET {$timeField} status=? WHERE id=?")->execute([$status, $id]);

    // Sync visits.consultation_status
    $visitStatus = 'waiting';
    switch ($status) {
        case 'called':
        case 'in_consultation':
            $visitStatus = 'in_consultation';
            break;
        case 'done':
            $visitStatus = 'done';
            break;
        default:
            $visitStatus = 'waiting';
            break;
    }
    $vStmt = $pdo->prepare("UPDATE visits SET consultation_status=? WHERE id=(SELECT visit_id FROM patient_queue WHERE id=?)");
    $vStmt->execute([$visitStatus, $id]);

    success(null, 'Queue updated');
}

error('Method not allowed', 405);
} catch (PDOException $e) { error('DB error: '.$e->getMessage(), 500); }
?>

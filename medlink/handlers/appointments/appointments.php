<?php
// handlers/appointments/appointments.php — follow-up appointments (see includes/appointments.php)
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/appointments.php';
function jr($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
function ok($d=null,$m='Success',$s=200){jr(['success'=>true,'message'=>$m,'data'=>$d],$s);}
function err($m,$s=400){jr(['success'=>false,'message'=>$m],$s);}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$me     = ap_me($pdo);

const AP_SELECT = "SELECT a.*, CONCAT(p.first_name,' ',p.last_name) AS patient_name, p.patient_no, p.phone, p.gender, p.date_of_birth,
        CONCAT(u.first_name,' ',u.last_name) AS doctor_name, d.name AS department,
        sv.visit_no AS source_visit_no, sv.visit_date AS source_visit_date, nv.visit_no AS visit_no,
        CONCAT(cu.first_name,' ',cu.last_name) AS created_by_name, CONCAT(au.first_name,' ',au.last_name) AS arrived_by_name
    FROM appointments a JOIN patients p ON p.id = a.patient_id
    LEFT JOIN users u ON u.id = a.doctor_id LEFT JOIN departments d ON d.id = a.department_id
    LEFT JOIN visits sv ON sv.id = a.source_visit_id LEFT JOIN visits nv ON nv.id = a.visit_id
    LEFT JOIN users cu ON cu.id = a.created_by LEFT JOIN users au ON au.id = a.arrived_by";

function ap_get(PDO $pdo, int $id): ?array {
    $s = $pdo->prepare(AP_SELECT . " WHERE a.id = ?"); $s->execute([$id]); $r = $s->fetch(); return $r ?: null;
}

try {

if ($method === 'GET') {
    if ($action === 'doctors') {
        ok($pdo->query("SELECT DISTINCT u.id, CONCAT(u.first_name,' ',u.last_name) AS name, u.department_id FROM users u
            LEFT JOIN user_roles ur ON ur.user_id = u.id LEFT JOIN roles r ON r.id = ur.role_id
            WHERE u.is_active = 1 AND (r.slug = 'doctor' OR u.role = 'doctor') ORDER BY name")->fetchAll());
    }
    // A doctor's booked times on one day (shown while picking a slot)
    if ($action === 'day') {
        $s = $pdo->prepare("SELECT a.id, a.appointment_time, a.duration_min, CONCAT(p.first_name,' ',p.last_name) AS patient_name FROM appointments a
            JOIN patients p ON p.id = a.patient_id WHERE a.doctor_id = ? AND a.appointment_date = ? AND a.status IN " . AP_ACTIVE . " ORDER BY a.appointment_time");
        $s->execute([(int)($_GET['doctor_id'] ?? 0), $_GET['date'] ?? '']);
        ok($s->fetchAll());
    }
    // Doctor's dashboard: today, tomorrow, the next 7 days, overdue; also sends the bell reminders
    if ($action === 'reminders') {
        $doc = (int)($_GET['doctor_id'] ?? 0) ?: $me['uid'];
        ap_send_reminders($pdo, $doc);
        $s = $pdo->prepare(AP_SELECT . " WHERE a.doctor_id = ? AND ((a.status IN " . AP_ACTIVE . " AND a.appointment_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY)
            OR (a.status = 'scheduled' AND a.appointment_date < CURDATE() AND a.appointment_date >= CURDATE() - INTERVAL 30 DAY)) ORDER BY a.appointment_date, a.appointment_time");
        $s->execute([$doc]);
        ok($s->fetchAll());
    }
    if ($id) {
        $r = ap_get($pdo, $id); if (!$r) err('Not found', 404);
        $h = $pdo->prepare("SELECT h.*, CONCAT(u.first_name,' ',u.last_name) AS user_name FROM appointment_history h LEFT JOIN users u ON u.id = h.user_id WHERE h.appointment_id = ? ORDER BY h.id");
        $h->execute([$id]);
        $r['history'] = $h->fetchAll();
        ok($r);
    }
    // List
    $w = []; $p = [];
    foreach (['patient_id' => 'a.patient_id', 'doctor_id' => 'a.doctor_id', 'visit_id' => 'a.visit_id', 'source_visit_id' => 'a.source_visit_id'] as $k => $col)
        if (!empty($_GET[$k])) { $w[] = "$col = ?"; $p[] = (int)$_GET[$k]; }
    if (!empty($_GET['date_from'])) { $w[] = 'a.appointment_date >= ?'; $p[] = $_GET['date_from']; }
    if (!empty($_GET['date_to']))   { $w[] = 'a.appointment_date <= ?'; $p[] = $_GET['date_to']; }
    $status = $_GET['status'] ?? '';
    if ($status === 'overdue') $w[] = "a.status = 'scheduled' AND TIMESTAMP(a.appointment_date, a.appointment_time) < NOW() - INTERVAL 1 HOUR";
    elseif ($status !== '') { $w[] = 'a.status = ?'; $p[] = $status; }
    if (($q = trim($_GET['search'] ?? '')) !== '') {
        $w[] = "(CONCAT(p.first_name,' ',p.last_name) LIKE ? OR CONCAT(p.last_name,' ',p.first_name) LIKE ? OR p.patient_no LIKE ? OR p.phone LIKE ? OR a.reason LIKE ?)";
        array_push($p, ...array_fill(0, 5, "%$q%"));
    }
    $ws = $w ? 'WHERE ' . implode(' AND ', $w) : '';
    $s = $pdo->prepare(AP_SELECT . " $ws ORDER BY a.appointment_date, a.appointment_time LIMIT 1000");
    $s->execute($p);
    ok($s->fetchAll());
}

if ($method === 'POST') {
    if (!$me['can_schedule']) err('Only doctors can schedule follow-up appointments', 403);
    $patientId = (int)($input['patient_id'] ?? 0);
    $doctorId  = (int)($input['doctor_id'] ?? 0) ?: $me['uid'];
    $date = $input['appointment_date'] ?? ''; $time = substr($input['appointment_time'] ?? '', 0, 5);
    $min  = in_array((int)($input['duration_min'] ?? 15), [10, 15, 20, 30, 45, 60], true) ? (int)$input['duration_min'] : 15;
    $reason = trim($input['reason'] ?? '');
    if (!$patientId) err('patient_id required', 422);
    if ($reason === '') err('Write the reason for the follow-up', 422);
    $chk = $pdo->prepare("SELECT id FROM patients WHERE id = ?"); $chk->execute([$patientId]);
    if (!$chk->fetch()) err('Patient not found', 404);
    $deptId = !empty($input['department_id']) ? (int)$input['department_id'] : null;
    $srcVisit = !empty($input['source_visit_id']) ? (int)$input['source_visit_id'] : null;
    if ($srcVisit) {   // the follow-up must belong to that consultation's patient
        $sv = $pdo->prepare("SELECT patient_id, department_id FROM visits WHERE id = ?"); $sv->execute([$srcVisit]); $sv = $sv->fetch();
        if (!$sv || (int)$sv['patient_id'] !== $patientId) err('The consultation does not belong to this patient', 422);
        $deptId = $deptId ?: ($sv['department_id'] ? (int)$sv['department_id'] : null);
    }
    $pdo->beginTransaction();
    try {
        $pdo->query("SELECT id FROM users WHERE id = " . $doctorId . " FOR UPDATE");   // serialise bookings per doctor
        ap_check_slot($pdo, $doctorId, $patientId, $date, $time, $min);
        $pdo->prepare("INSERT INTO appointments (patient_id, doctor_id, department_id, source_visit_id, appointment_date, appointment_time, duration_min, reason, instructions, created_by)
                       VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([$patientId, $doctorId, $deptId, $srcVisit, $date, $time, $min, $reason, trim($input['instructions'] ?? '') ?: null, $me['uid']]);
        $newId = (int)$pdo->lastInsertId();
        ap_log($pdo, $newId, 'created', null, ['appointment_date' => $date, 'appointment_time' => $time], $reason, $me['uid']);
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    if (function_exists('log_activity')) log_activity($pdo, 'CREATE', 'appointments', "Follow-up appointment #$newId on $date $time for patient #$patientId", $newId, null);
    ok(ap_get($pdo, $newId), 'Follow-up appointment scheduled', 201);
}

if ($method === 'PUT') {
    if (!$id) err('ID required', 422);
    $a = ap_get($pdo, $id); if (!$a) err('Not found', 404);
    $owner = (int)$a['doctor_id'] === $me['uid'] || (int)$a['created_by'] === $me['uid'];
    if (!$owner && !$me['is_front'] && !$me['is_admin']) err('You cannot change this appointment', 403);
    if ($a['status'] !== 'scheduled') err("This appointment is already {$a['status']}", 409);
    $act = $input['action'] ?? ''; $notes = trim($input['notes'] ?? '');
    $pdo->beginTransaction();
    try {
        if ($act === 'reschedule') {
            $doctorId = (int)($input['doctor_id'] ?? 0) ?: (int)$a['doctor_id'];
            $date = $input['appointment_date'] ?? ''; $time = substr($input['appointment_time'] ?? '', 0, 5);
            $min = in_array((int)($input['duration_min'] ?? 0), [10, 15, 20, 30, 45, 60], true) ? (int)$input['duration_min'] : (int)$a['duration_min'];
            $pdo->query("SELECT id FROM users WHERE id = " . $doctorId . " FOR UPDATE");
            ap_check_slot($pdo, $doctorId, (int)$a['patient_id'], $date, $time, $min, $id);
            $pdo->prepare("UPDATE appointments SET doctor_id = ?, appointment_date = ?, appointment_time = ?, duration_min = ?, reminded_before = 0, reminded_day = 0 WHERE id = ?")
                ->execute([$doctorId, $date, $time, $min, $id]);
            ap_log($pdo, $id, 'rescheduled', $a, ['appointment_date' => $date, 'appointment_time' => $time], $notes, $me['uid']);
            $msg = 'Appointment rescheduled';
        } elseif ($act === 'cancel') {
            if ($notes === '') throw new RuntimeException('Give the reason for cancelling', 422);
            $pdo->prepare("UPDATE appointments SET status = 'cancelled', cancel_reason = ? WHERE id = ?")->execute([$notes, $id]);
            ap_log($pdo, $id, 'cancelled', null, null, $notes, $me['uid']);
            $msg = 'Appointment cancelled';
        } elseif ($act === 'missed') {
            if (strtotime($a['appointment_date'] . ' ' . $a['appointment_time']) > time()) throw new RuntimeException('The appointment time has not passed yet', 422);
            $pdo->prepare("UPDATE appointments SET status = 'missed' WHERE id = ?")->execute([$id]);
            ap_log($pdo, $id, 'missed', null, null, $notes ?: 'Patient did not come', $me['uid']);
            $msg = 'Marked as missed';
        } elseif ($act === 'update') {
            $reason = trim($input['reason'] ?? '');
            if ($reason === '') throw new RuntimeException('Write the reason for the follow-up', 422);
            $pdo->prepare("UPDATE appointments SET reason = ?, instructions = ? WHERE id = ?")->execute([$reason, trim($input['instructions'] ?? '') ?: null, $id]);
            ap_log($pdo, $id, 'updated', null, null, 'Reason / instructions edited', $me['uid']);
            $msg = 'Appointment updated';
        } else throw new RuntimeException('Unknown action', 422);
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    ok(ap_get($pdo, $id), $msg);
}

err('Method not allowed', 405);
} catch (PDOException $e) { err('DB: ' . $e->getMessage(), 500); }
  catch (RuntimeException $e) { err($e->getMessage(), (int)$e->getCode() ?: 400); }
  catch (Throwable $e) { err('Server: ' . $e->getMessage(), 500); }

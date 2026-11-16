<?php
// handlers/patients/patients.php
require_once __DIR__ . '/../../includes/database.php';

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}
function success($data = null, $message = 'Success', $status = 200) {
    jsonResponse(['success' => true, 'message' => $message, 'data' => $data], $status);
}
function error($message, $status = 400) {
    jsonResponse(['success' => false, 'message' => $message], $status);
}

// Generate unique patient number: PAT-YYYYMMDD-XXXX
function generatePatientNo(PDO $pdo): string {
    $date = date('Ymd');
    $prefix = "PAT-{$date}-";
    $stmt = $pdo->prepare(
        "SELECT patient_no FROM patients WHERE patient_no LIKE ? ORDER BY patient_no DESC LIMIT 1"
    );
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    $seq = $last ? ((int)substr($last, -4) + 1) : 1;
    return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

// Attach insurance records to a patient array
function attachInsurance(PDO $pdo, array &$patient): void {
    $stmt = $pdo->prepare("
        SELECT pi.*, ic.name AS company_name, ic.code AS company_code,
               ip.name AS package_name, ip.code AS package_code,
               ip.copayment_percentage
        FROM patient_insurance pi
        JOIN insurance_companies ic ON ic.id = pi.company_id
        LEFT JOIN insurance_packages ip ON ip.id = pi.package_id
        WHERE pi.patient_id = ?
        ORDER BY pi.is_primary DESC, pi.created_at DESC
    ");
    $stmt->execute([$patient['id']]);
    $patient['insurance'] = $stmt->fetchAll();
}

$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {

// ==================== GET ====================
if ($method === 'GET') {

    if ($id) {
        $stmt = $pdo->prepare("
            SELECT p.*,
                   prov.name AS province_name,
                   dist.name AS district_name,
                   sec.name AS sector_name,
                   cel.name AS cell_name,
                   vil.name AS village_name
            FROM patients p
            LEFT JOIN provinces prov ON prov.id = p.province_id
            LEFT JOIN districts dist ON dist.id = p.district_id
            LEFT JOIN sectors sec ON sec.id = p.sector_id
            LEFT JOIN cells cel ON cel.id = p.cell_id
            LEFT JOIN villages vil ON vil.id = p.village_id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        $patient = $stmt->fetch();
        if (!$patient) error('Patient not found', 404);
        attachInsurance($pdo, $patient);

        // Attach previous visit info (most recent visit prior to current/today)
        $prevVisitStmt = $pdo->prepare("
            SELECT v.id, v.visit_no, v.visit_date, v.created_at,
                   d.name AS department_name,
                   DATEDIFF(CURDATE(), v.visit_date) AS days_ago
            FROM visits v
            LEFT JOIN departments d ON d.id = v.department_id
            WHERE v.patient_id = ?
            ORDER BY v.visit_date DESC, v.id DESC
            LIMIT 2
        ");
        $prevVisitStmt->execute([$patient['id']]);
        $recentVisits = $prevVisitStmt->fetchAll();
        if (count($recentVisits) > 1) {
            $recentVisits[1]['days_ago'] = (int)$recentVisits[1]['days_ago'];
            $patient['previous_visit'] = $recentVisits[1];
        } elseif (count($recentVisits) === 1 && (int)$recentVisits[0]['days_ago'] > 0) {
            $recentVisits[0]['days_ago'] = (int)$recentVisits[0]['days_ago'];
            $patient['previous_visit'] = $recentVisits[0];
        } else {
            $patient['previous_visit'] = null;
        }

        success($patient);
    }

    $search  = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status  = isset($_GET['status']) ? $_GET['status'] : '';
    $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $dateTo   = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    $page    = max(1, (int)($_GET['page']  ?? 1));
    $limit   = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $offset  = ($page - 1) * $limit;

    $where   = [];
    $params  = [];

    if ($search !== '') {
        $like     = "%{$search}%";
        $where[]  = "(p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_no LIKE ? OR p.phone LIKE ? OR p.national_id LIKE ?)";
        $params   = array_merge($params, [$like, $like, $like, $like, $like]);
    }
    if ($status !== '') {
        $where[]  = "p.status = ?";
        $params[] = $status;
    }
    if ($dateFrom !== '') {
        $where[]  = "DATE(p.created_at) >= ?";
        $params[] = $dateFrom;
    }
    if ($dateTo !== '') {
        $where[]  = "DATE(p.created_at) <= ?";
        $params[] = $dateTo;
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM patients p $whereSQL");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT p.*, ic.name AS insurance_company
        FROM patients p
        LEFT JOIN patient_insurance pi ON pi.patient_id = p.id
        LEFT JOIN insurance_companies ic ON ic.id = pi.company_id
        $whereSQL
        ORDER BY p.created_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $patients = $stmt->fetchAll();

    // Stats
    $statsStmt = $pdo->query("
        SELECT
            COUNT(*) AS total,
            SUM(status='active')     AS active,
            SUM(status='admitted')   AS admitted,
            SUM(status='critical')   AS critical,
            SUM(status='discharged') AS discharged
        FROM patients
    ");
    $stats = $statsStmt->fetch();

    success([
        'patients'   => $patients,
        'stats'      => $stats,
        'pagination' => [
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => (int)ceil($total / max($limit, 1))
        ]
    ]);
}

// ==================== POST ====================
if ($method === 'POST') {
    $required = ['first_name', 'last_name', 'date_of_birth', 'gender', 'phone'];
    foreach ($required as $f) {
        if (empty($input[$f])) error("Field '{$f}' is required", 422);
    }

    // National ID is required
    if (empty($input['national_id'])) {
        error('National ID / Passport is required', 422);
    }

    // Duplicate check — prevent registering same National ID twice
    $dupStmt = $pdo->prepare("SELECT id, patient_no, CONCAT(first_name,' ',last_name) AS name FROM patients WHERE national_id = ? LIMIT 1");
    $dupStmt->execute([trim($input['national_id'])]);
    $dupPatient = $dupStmt->fetch();
    if ($dupPatient) {
        error('A patient with this National ID already exists: ' . $dupPatient['name'] . ' (' . $dupPatient['patient_no'] . ')', 409);
    }

    // Optional email validation
    if (!empty($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        error('Invalid email address', 422);
    }

    $pdo->beginTransaction();

    $patientNo = generatePatientNo($pdo);

    $stmt = $pdo->prepare("
        INSERT INTO patients
            (patient_no, first_name, last_name, date_of_birth, gender, national_id,
             blood_group, phone, email, address, province_id, district_id, sector_id, cell_id, village_id,
             emergency_contact, next_of_kin, kin_relationship, allergies, chronic_diseases,
             current_medications, medical_alerts, status, notes, created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([
        $patientNo,
        trim($input['first_name']),
        trim($input['last_name']),
        $input['date_of_birth'],
        $input['gender'],
        trim($input['national_id']          ?? ''),
        trim($input['blood_group']          ?? ''),
        trim($input['phone']),
        trim($input['email']                ?? ''),
        trim($input['address']              ?? ''),
        !empty($input['province_id'])       ? $input['province_id'] : null,
        !empty($input['district_id'])       ? $input['district_id'] : null,
        !empty($input['sector_id'])         ? $input['sector_id'] : null,
        !empty($input['cell_id'])           ? $input['cell_id'] : null,
        !empty($input['village_id'])        ? $input['village_id'] : null,
        trim($input['emergency_contact']    ?? ''),
        trim($input['next_of_kin']          ?? ''),
        trim($input['kin_relationship']     ?? ''),
        trim($input['allergies']            ?? ''),
        trim($input['chronic_diseases']     ?? ''),
        trim($input['current_medications']  ?? ''),
        trim($input['medical_alerts']       ?? ''),
        $input['status']                    ?? 'active',
        trim($input['notes']                ?? ''),
        !empty($input['created_by'])        ? (int)$input['created_by'] : null,
    ]);

    $newId = (int)$pdo->lastInsertId();

    // Save insurance if provided
    $ins = $input['insurance'] ?? [];
    if (!empty($ins['company_id'])) {
        // Default dates if not provided
        $policyStart = !empty($ins['policy_start_date']) ? $ins['policy_start_date'] : date('Y-m-d');
        $policyEnd = !empty($ins['policy_end_date']) ? $ins['policy_end_date'] : date('Y-m-d', strtotime('+1 year'));
        $policyNumber = !empty($ins['policy_number']) ? trim($ins['policy_number']) : 'POL-' . $newId . '-' . date('Ymd');

        $iStmt = $pdo->prepare("
            INSERT INTO patient_insurance
                (patient_id, company_id, package_id, policy_number, member_number,
                 principal_member_name, principal_phone, principal_member_number, employer_name,
                 policy_start_date, policy_end_date, is_primary, status, notes)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,1,?,?)
        ");
        $iStmt->execute([
            $newId,
            (int)$ins['company_id'],
            !empty($ins['package_id']) ? (int)$ins['package_id'] : null,
            $policyNumber,
            trim($ins['member_number']              ?? ''),
            trim($ins['principal_member_name']      ?? ''),
            trim($ins['principal_phone']            ?? ''),
            trim($ins['principal_member_number']    ?? ''),
            trim($ins['employer_name']              ?? ''),
            $policyStart,
            $policyEnd,
            $ins['status']                          ?? 'active',
            trim($ins['notes']                      ?? '')
        ]);
    }

    $pdo->commit();

    $fetchSt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $fetchSt->execute([$newId]);
    $patient = $fetchSt->fetch();
    attachInsurance($pdo, $patient);

    success($patient, 'Patient registered successfully', 201);
}

// ==================== PUT ====================
if ($method === 'PUT') {
    if (!$id) error('Patient ID is required', 422);

    // Get before-state for audit log
    $chk = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $chk->execute([$id]);
    $beforeData = $chk->fetch();
    if (!$beforeData) error('Patient not found', 404);

    // If edit_request_id provided, verify it's approved
    $editRequestId = !empty($input['edit_request_id']) ? (int)$input['edit_request_id'] : null;
    if ($editRequestId) {
        $erStmt = $pdo->prepare("SELECT id, status FROM edit_requests WHERE id = ? AND entity_type = 'patient' AND entity_id = ?");
        $erStmt->execute([$editRequestId, $id]);
        $er = $erStmt->fetch();
        if (!$er || $er['status'] !== 'approved') {
            error('Edit request is not approved', 403);
        }
    }

    $required = ['first_name', 'last_name', 'date_of_birth', 'gender', 'phone'];
    foreach ($required as $f) {
        if (empty($input[$f])) error("Field '{$f}' is required", 422);
    }
    if (!empty($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        error('Invalid email address', 422);
    }

    $stmt = $pdo->prepare("
        UPDATE patients SET
            first_name=?, last_name=?, date_of_birth=?, gender=?, national_id=?,
            blood_group=?, phone=?, email=?, address=?,
            province_id=?, district_id=?, sector_id=?, cell_id=?, village_id=?,
            emergency_contact=?, next_of_kin=?, kin_relationship=?, allergies=?, chronic_diseases=?,
            current_medications=?, medical_alerts=?, status=?, notes=?
        WHERE id=?
    ");
    $stmt->execute([
        trim($input['first_name']),
        trim($input['last_name']),
        $input['date_of_birth'],
        $input['gender'],
        trim($input['national_id']          ?? ''),
        trim($input['blood_group']          ?? ''),
        trim($input['phone']),
        trim($input['email']                ?? ''),
        trim($input['address']              ?? ''),
        !empty($input['province_id'])       ? $input['province_id'] : null,
        !empty($input['district_id'])       ? $input['district_id'] : null,
        !empty($input['sector_id'])         ? $input['sector_id'] : null,
        !empty($input['cell_id'])           ? $input['cell_id'] : null,
        !empty($input['village_id'])        ? $input['village_id'] : null,
        trim($input['emergency_contact']    ?? ''),
        trim($input['next_of_kin']          ?? ''),
        trim($input['kin_relationship']     ?? ''),
        trim($input['allergies']            ?? ''),
        trim($input['chronic_diseases']     ?? ''),
        trim($input['current_medications']  ?? ''),
        trim($input['medical_alerts']       ?? ''),
        $input['status']                    ?? 'active',
        trim($input['notes']                ?? ''),
        $id
    ]);

    // Update insurance if provided
    $ins = $input['insurance'] ?? [];
    if (!empty($ins['company_id'])) {
        $policyStart = !empty($ins['policy_start_date']) ? $ins['policy_start_date'] : date('Y-m-d');
        $policyEnd = !empty($ins['policy_end_date']) ? $ins['policy_end_date'] : date('Y-m-d', strtotime('+1 year'));
        $policyNumber = !empty($ins['policy_number']) ? trim($ins['policy_number']) : 'POL-' . $id . '-' . date('Ymd');

        // Check if patient already has insurance record
        $existIns = $pdo->prepare("SELECT id FROM patient_insurance WHERE patient_id = ? AND is_primary = 1 LIMIT 1");
        $existIns->execute([$id]);
        $existInsRow = $existIns->fetch();

        if ($existInsRow) {
            // Update existing insurance
            $iStmt = $pdo->prepare("
                UPDATE patient_insurance SET
                    company_id=?, package_id=?, policy_number=?, member_number=?,
                    principal_member_name=?, principal_phone=?, principal_member_number=?, employer_name=?,
                    policy_start_date=?, policy_end_date=?, status=?
                WHERE id=?
            ");
            $iStmt->execute([
                (int)$ins['company_id'],
                !empty($ins['package_id']) ? (int)$ins['package_id'] : null,
                $policyNumber,
                trim($ins['member_number']              ?? ''),
                trim($ins['principal_member_name']      ?? ''),
                trim($ins['principal_phone']            ?? ''),
                trim($ins['principal_member_number']    ?? ''),
                trim($ins['employer_name']              ?? ''),
                $policyStart,
                $policyEnd,
                $ins['status']                          ?? 'active',
                $existInsRow['id']
            ]);
        } else {
            // Insert new insurance
            $iStmt = $pdo->prepare("
                INSERT INTO patient_insurance
                    (patient_id, company_id, package_id, policy_number, member_number,
                     principal_member_name, principal_phone, principal_member_number, employer_name,
                     policy_start_date, policy_end_date, is_primary, status)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,1,?)
            ");
            $iStmt->execute([
                $id,
                (int)$ins['company_id'],
                !empty($ins['package_id']) ? (int)$ins['package_id'] : null,
                $policyNumber,
                trim($ins['member_number']              ?? ''),
                trim($ins['principal_member_name']      ?? ''),
                trim($ins['principal_phone']            ?? ''),
                trim($ins['principal_member_number']    ?? ''),
                trim($ins['employer_name']              ?? ''),
                $policyStart,
                $policyEnd,
                $ins['status']                          ?? 'active'
            ]);
        }
    } elseif (isset($input['insurance']) && empty($ins['company_id'])) {
        // If insurance object sent but company_id is empty, remove insurance
        $pdo->prepare("DELETE FROM patient_insurance WHERE patient_id = ?")->execute([$id]);
    }

    $fetchSt = $pdo->prepare("
        SELECT p.*,
               prov.name AS province_name,
               dist.name AS district_name,
               sec.name AS sector_name,
               cel.name AS cell_name,
               vil.name AS village_name
        FROM patients p
        LEFT JOIN provinces prov ON prov.id = p.province_id
        LEFT JOIN districts dist ON dist.id = p.district_id
        LEFT JOIN sectors sec ON sec.id = p.sector_id
        LEFT JOIN cells cel ON cel.id = p.cell_id
        LEFT JOIN villages vil ON vil.id = p.village_id
        WHERE p.id = ?
    ");
    $fetchSt->execute([$id]);
    $patient = $fetchSt->fetch();
    attachInsurance($pdo, $patient);

    // Log the edit in audit trail
    $afterData = $patient;
    $changedFields = [];
    $fieldsToTrack = ['first_name','last_name','date_of_birth','gender','national_id','blood_group','phone','email','status','province_id','district_id','sector_id','cell_id','village_id','emergency_contact','next_of_kin','kin_relationship','allergies','chronic_diseases','current_medications','medical_alerts','notes'];
    foreach ($fieldsToTrack as $f) {
        $before = trim($beforeData[$f] ?? '');
        $after = trim($afterData[$f] ?? '');
        if ($before !== $after) {
            $changedFields[] = $f . ': "' . $before . '" → "' . $after . '"';
        }
    }
    if (!empty($changedFields)) {
        $editedBy = !empty($input['edited_by']) ? (int)$input['edited_by'] : null;
        $logStmt = $pdo->prepare("INSERT INTO edit_audit_log (entity_type, entity_id, entity_name, edit_request_id, data_before, data_after, changed_fields, edited_by, reason) VALUES ('patient', ?, ?, ?, ?, ?, ?, ?, ?)");
        $logStmt->execute([
            $id,
            $beforeData['first_name'] . ' ' . $beforeData['last_name'],
            $editRequestId,
            json_encode($beforeData),
            json_encode($afterData),
            implode("\n", $changedFields),
            $editedBy,
            $editRequestId ? 'Approved edit request #' . $editRequestId : 'Direct edit'
        ]);

        // Mark edit request as used (change status back so it can't be reused)
        if ($editRequestId) {
            $pdo->prepare("UPDATE edit_requests SET status = 'pending' WHERE id = ? AND status = 'approved'")->execute([$editRequestId]);
            $pdo->prepare("DELETE FROM edit_requests WHERE id = ?")->execute([$editRequestId]);
        }
    }

    success($patient, 'Patient updated successfully');
}

// ==================== DELETE ====================
if ($method === 'DELETE') {
    if (!$id) error('Patient ID is required', 422);

    $chk = $pdo->prepare("SELECT id FROM patients WHERE id = ?");
    $chk->execute([$id]);
    if (!$chk->fetch()) error('Patient not found', 404);

    // CASCADE handles patient_insurance
    $pdo->prepare("DELETE FROM patients WHERE id = ?")->execute([$id]);
    success(null, 'Patient deleted successfully');
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

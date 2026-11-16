<?php
if (!defined('MEDLINK_API') && PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
// handlers/patients/patients.php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/activity_log.php';

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

// Tariff category from the registration form; null when absent so an update keeps the current value
function inputTariff(array $input): ?string {
    if (!isset($input['tariff_category']) || $input['tariff_category'] === '') return null;
    if (!in_array($input['tariff_category'], ['rwanda','east_africa','africa','world'], true)) error('Invalid tariff category', 422);
    return $input['tariff_category'];
}

// A patient cannot be born in the future; also reject malformed/non-ISO dates.
const PATIENT_EDIT_LIMIT = 3;
// Direct (non-admin-approved) edits already made to a patient
function patientEditsUsed(PDO $pdo, int $id): int {
    $q = $pdo->prepare("SELECT COUNT(*) FROM edit_audit_log WHERE entity_type='patient' AND entity_id=? AND reason='Direct edit'");
    $q->execute([$id]);
    return (int)$q->fetchColumn();
}
// An ID of only zeros (more than 3, e.g. 0000) = "no National ID": skip the uniqueness check.
function isMissingNationalId(?string $nationalId): bool {
    $v = trim((string)$nationalId);
    return preg_match('/^[0\s\-]+$/', $v) && strlen(preg_replace('/\D+/', '', $v)) > 3;
}
function validatePatientDob(?string $value): void {
    if ($value === null || $value === '') return;
    $dob = DateTime::createFromFormat('!Y-m-d', $value);
    if (!$dob || $dob->format('Y-m-d') !== $value) error('Invalid date_of_birth (expected YYYY-MM-DD)', 422);
    $today = new DateTime('today');
    if ($dob > $today) error('date_of_birth cannot be in the future', 422);
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
               ip.name AS package_name, ip.code AS package_code
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
$input  = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = [];   // malformed or non-object JSON is treated as an empty request (validation then answers 422)
// Phone numbers are stored as digits only
foreach (['phone', 'emergency_contact'] as $f) if (isset($input[$f]) && is_scalar($input[$f])) $input[$f] = preg_replace('/\D/', '', (string)$input[$f]);
foreach ((array)($input['insurance'] ?? []) as $k => $ins) if (is_array($ins) && isset($ins['principal_phone'])) $input['insurance'][$k]['principal_phone'] = preg_replace('/\D/', '', (string)$ins['principal_phone']);
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
        success($patient);
    }

    $search  = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status  = isset($_GET['status']) ? $_GET['status'] : '';
    $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $dateTo   = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    $page    = max(1, (int)($_GET['page']  ?? 1));
    $limit   = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $offset  = ($page - 1) * $limit;

    // --- Duplicate national ID check ---
    if (isset($_GET['action']) && $_GET['action'] === 'check_national_id') {
        $nid = trim($_GET['national_id'] ?? '');
        if ($nid === '') error('national_id is required');
        if (isMissingNationalId($nid)) success(['exists' => false]);
        $stmt = $pdo->prepare("SELECT id, patient_no FROM patients WHERE national_id = ? LIMIT 1");
        $stmt->execute([$nid]);
        $existing = $stmt->fetch();
        success([
            'exists'         => (bool)$existing,
            'patient_number' => $existing ? $existing['patient_no'] : null
        ]);
    }

    $provinceId = isset($_GET['province_id']) && $_GET['province_id'] !== '' ? (int)$_GET['province_id'] : null;
    $districtId = isset($_GET['district_id']) && $_GET['district_id'] !== '' ? (int)$_GET['district_id'] : null;
    $sectorId   = isset($_GET['sector_id']) && $_GET['sector_id'] !== '' ? (int)$_GET['sector_id'] : null;
    $zone       = isset($_GET['zone']) ? trim($_GET['zone']) : '';

    $where   = [];
    $params  = [];

    if ($search !== '') {
        $like     = "%{$search}%";
        $where[]  = "(CONCAT(p.last_name,' ',p.first_name) LIKE ? OR CONCAT(p.first_name,' ',p.last_name) LIKE ? OR p.patient_no LIKE ? OR p.phone LIKE ? OR p.national_id LIKE ? OR p.address LIKE ? OR prov.name LIKE ? OR dist.name LIKE ? OR sec.name LIKE ?)";
        $params   = array_merge($params, [$like, $like, $like, $like, $like, $like, $like, $like, $like]);
    }
    if ($status !== '') {
        $where[]  = "p.status = ?";
        $params[] = $status;
    }
    if ($dateFrom !== '') {
        $where[]  = "p.created_at >= ?";
        $params[] = $dateFrom;
    }
    if ($dateTo !== '') {
        $where[]  = "p.created_at < DATE_ADD(?, INTERVAL 1 DAY)";
        $params[] = $dateTo;
    }
    if ($provinceId) {
        $where[]  = "p.province_id = ?";
        $params[] = $provinceId;
    }
    if ($districtId) {
        $where[]  = "p.district_id = ?";
        $params[] = $districtId;
    }
    if ($sectorId) {
        $where[]  = "p.sector_id = ?";
        $params[] = $sectorId;
    }
    if ($zone === 'catchment') {
        $where[] = "(LOWER(sec.name) IN ('nyamata', 'ntarama') OR LOWER(p.address) LIKE '%nyamata%' OR LOWER(p.address) LIKE '%ntarama%')";
    } elseif ($zone === 'outside') {
        $where[] = "((p.province_id > 0 OR p.district_id > 0 OR p.sector_id > 0 OR (p.address IS NOT NULL AND p.address != '')) AND NOT (LOWER(COALESCE(sec.name, '')) IN ('nyamata', 'ntarama') OR LOWER(COALESCE(p.address, '')) LIKE '%nyamata%' OR LOWER(COALESCE(p.address, '')) LIKE '%ntarama%'))";
    } elseif ($zone === 'international') {
        $where[] = "((p.province_id IS NULL OR p.province_id = 0) AND (p.district_id IS NULL OR p.district_id = 0) AND (p.sector_id IS NULL OR p.sector_id = 0) AND (p.address IS NULL OR p.address = ''))";
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM patients p 
        LEFT JOIN provinces prov ON prov.id = p.province_id
        LEFT JOIN districts dist ON dist.id = p.district_id
        LEFT JOIN sectors sec ON sec.id = p.sector_id
        $whereSQL
    ");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    require_once __DIR__ . '/../../includes/edit_audit_schema.php';
    edit_audit_boot($pdo);
    $stmt = $pdo->prepare("
        SELECT p.*, ic.name AS insurance_company, ip.name AS insurance_package,
               pi.member_number,
               (SELECT COUNT(*) FROM edit_audit_log e WHERE e.entity_type='patient' AND e.entity_id=p.id AND e.reason='Direct edit') AS edits_used,
               prov.name AS province_name, dist.name AS district_name,
               sec.name AS sector_name, cel.name AS cell_name, vil.name AS village_name,
               COALESCE(CONCAT(u.first_name,' ',u.last_name), u.username) AS registered_by_name
        FROM patients p
        LEFT JOIN patient_insurance pi ON pi.patient_id = p.id AND pi.is_primary = 1
        LEFT JOIN insurance_companies ic ON ic.id = pi.company_id
        LEFT JOIN insurance_packages ip ON ip.id = pi.package_id
        LEFT JOIN provinces prov ON prov.id = p.province_id
        LEFT JOIN districts dist ON dist.id = p.district_id
        LEFT JOIN sectors sec ON sec.id = p.sector_id
        LEFT JOIN cells cel ON cel.id = p.cell_id
        LEFT JOIN villages vil ON vil.id = p.village_id
        LEFT JOIN users u ON u.id = p.registered_by
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


// Field lengths of the patients table: a longer value is refused (never silently cut)
function patient_check_lengths(array $in): void {
    foreach (['first_name' => 80, 'last_name' => 80, 'national_id' => 50, 'phone' => 30, 'email' => 150, 'emergency_contact' => 30, 'next_of_kin' => 100, 'blood_group' => 5] as $f => $max) {
        if (isset($in[$f]) && mb_strlen(trim((string)$in[$f])) > $max) error("'$f' is too long (maximum $max characters)", 422);
    }
}

// ==================== POST ====================
if ($method === 'POST') {
    patient_check_lengths($input);
    // Unidentified ER patient: nothing is required, placeholders fill the NOT NULL columns until the identity is completed
    $unidentified = !empty($input['unidentified']);
    if ($unidentified) {
        $input['first_name'] = trim($input['first_name'] ?? '') ?: 'Unknown';
        $input['last_name']  = trim($input['last_name'] ?? '') ?: 'Unidentified';
        $input['gender']     = in_array($input['gender'] ?? '', ['male','female','other'], true) ? $input['gender'] : 'other';
        $input['date_of_birth'] = trim($input['date_of_birth'] ?? '') ?: date('Y-m-d');
        $input['phone']      = trim($input['phone'] ?? '');
        $input['national_id'] = trim($input['national_id'] ?? '');
        $input['confirm_duplicate'] = true;
    }
    // Emergency arrivals who cannot give an address are registered with address_unknown and completed later
    $required = ['first_name', 'last_name', 'date_of_birth', 'gender', 'phone', 'national_id'];
    if (empty($input['address_unknown'])) array_push($required, 'province_id', 'district_id', 'sector_id');
    // Foreigners (any origin other than Rwandan) must give their country
    if (!empty($input['tariff_category']) && $input['tariff_category'] !== 'rwanda') $required[] = 'country';
    foreach ($unidentified ? [] : $required as $f) {
        if (empty($input[$f])) error("Field '{$f}' is required", 422);
    }
    validatePatientDob((string)$input['date_of_birth']);

    // Optional email validation
    if (!empty($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        error('Invalid email address', 422);
    }

    // Duplicate National ID / Passport check
    $nationalId = trim($input['national_id']);
    $dupStmt = $pdo->prepare("SELECT patient_no FROM patients WHERE national_id = ? LIMIT 1");
    $missingNationalId = isMissingNationalId($nationalId);
    $existing = false;
    if (!$missingNationalId) {
        $dupStmt->execute([$nationalId]);
        $existing = $dupStmt->fetch();
    }
    if ($existing) {
        error("A patient with National ID / Passport '{$nationalId}' already exists (Patient No: {$existing['patient_no']}).", 409);
    }
    // Same name + date of birth + phone is very likely the same person: ask staff to confirm before creating a second file
    if (empty($input['confirm_duplicate'])) {
        $sim = $pdo->prepare("SELECT patient_no FROM patients WHERE LOWER(TRIM(first_name)) = LOWER(TRIM(?)) AND LOWER(TRIM(last_name)) = LOWER(TRIM(?)) AND date_of_birth = ? AND TRIM(phone) = TRIM(?) LIMIT 1");
        $sim->execute([$input['first_name'], $input['last_name'], $input['date_of_birth'], $input['phone']]);
        if ($simNo = $sim->fetchColumn()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'code' => 'possible_duplicate', 'message' => "A patient with the same name, date of birth and phone already exists (Patient No: $simNo). Open that file, or confirm this is a different person."]);
            exit();
        }
    }
    // Insurance validation
    $ins = $input['insurance'] ?? [];
    if (!empty($ins['company_id'])) {
        $coStmt = $pdo->prepare("SELECT id, name, code FROM insurance_companies WHERE id = ?");
        $coStmt->execute([(int)$ins['company_id']]);
        $coData = $coStmt->fetch();
        $isPrivate = $coData && (strtoupper($coData['code'] ?? '') === 'PRIVATE' || stripos($coData['name'] ?? '', 'private') !== false);

        if (empty($ins['package_id'])) {
            error('Insurance Package is required', 422);
        }
        if (!$isPrivate && empty(trim($ins['member_number'] ?? ''))) {
            error('Member Number / Card No. is required for the selected insurance company', 422);
        }
    }

    $pdo->beginTransaction();

    $patientNo = generatePatientNo($pdo);

    $stmt = $pdo->prepare("
        INSERT INTO patients
            (patient_no, first_name, last_name, date_of_birth, gender, national_id,
             blood_group, phone, email, address, province_id, district_id, sector_id, cell_id, village_id,
             emergency_contact, next_of_kin, kin_relationship, allergies, chronic_diseases,
             current_medications, medical_alerts, status, notes, registered_by, tariff_category, country)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([
        $patientNo,
        trim($input['first_name']),
        trim($input['last_name']),
        $input['date_of_birth'],
        $input['gender'],
        trim($input['national_id']          ?? ''),
        isset($input['blood_group']) ? trim($input['blood_group']) : null,   // no longer on the forms: keep what lab / maternity recorded
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
         !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : (!empty($input['registered_by']) ? (int)$input['registered_by'] : null),
         inputTariff($input) ?? 'rwanda',
         trim($input['country'] ?? '') ?: null
    ]);

    $newId = (int)$pdo->lastInsertId();
    log_activity($pdo, 'CREATE', 'patients',
        'New patient registered: ' . trim($input['first_name']) . ' ' . trim($input['last_name']) .
        ' | No: ' . $patientNo . ' | Gender: ' . $input['gender'] . ' | Phone: ' . trim($input['phone']),
        $newId, $patientNo
    );

    // Save insurance if provided
    $ins = $input['insurance'] ?? [];
    if (!empty($ins['company_id'])) {
        // Policy number/dates are no longer collected; columns are NOT NULL so fill placeholders
        $policyStart = date('Y-m-d');
        $policyEnd = '9999-12-31';
        $policyNumber = 'POL-' . $newId . '-' . date('Ymd');

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

    $patient['no_national_id'] = $missingNationalId;
    success($patient, $missingNationalId ? 'Patient registered successfully (no National ID recorded)' : 'Patient registered successfully', 201);
}

// ==================== PUT ====================
if ($method === 'PUT') {
    require_once __DIR__ . '/../../includes/edit_audit_schema.php';
    edit_audit_boot($pdo);
    if (!$id) error('Patient ID is required', 422);

    // Get before-state for audit log
    $chk = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $chk->execute([$id]);
    $beforeData = $chk->fetch();
    if (!$beforeData) error('Patient not found', 404);

    // If edit_request_id provided, verify it's approved
    $editRequestId = !empty($input['edit_request_id']) ? (int)$input['edit_request_id'] : null;
    // Staff get PATIENT_EDIT_LIMIT direct edits per patient; an admin (or an approved edit request) overrides
    $isAdminUser = isUserAdmin($pdo);
    if (!$isAdminUser && !$editRequestId && patientEditsUsed($pdo, $id) >= PATIENT_EDIT_LIMIT) {
        http_response_code(403);
        echo json_encode(['success' => false, 'code' => 'edit_limit', 'message' => 'Edit limit reached: this patient has already been edited ' . PATIENT_EDIT_LIMIT . ' times. Ask an administrator to make further changes.']);
        exit();
    }
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
    validatePatientDob((string)$input['date_of_birth']);
    if (!empty($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        error('Invalid email address', 422);
    }

    patient_check_lengths($input);
    // Two people editing the same file: the form sends the updated_at it loaded; a different value in the database means someone saved in between
    if (!empty($input['loaded_updated_at']) && $beforeData['updated_at'] !== null && (string)$input['loaded_updated_at'] !== (string)$beforeData['updated_at']) {
        http_response_code(409);
        echo json_encode(['success' => false, 'code' => 'edit_conflict', 'message' => 'This patient record was changed by someone else after you opened it. Reload the record and make your change again.']);
        exit();
    }

    $stmt = $pdo->prepare("
        UPDATE patients SET
            first_name=?, last_name=?, date_of_birth=?, gender=?, national_id=?,
            blood_group=COALESCE(?, blood_group), phone=?, email=?, address=?,
            province_id=?, district_id=?, sector_id=?, cell_id=?, village_id=?,
            emergency_contact=?, next_of_kin=?, kin_relationship=?, allergies=COALESCE(?, allergies), chronic_diseases=COALESCE(?, chronic_diseases),
             current_medications=COALESCE(?, current_medications), medical_alerts=COALESCE(?, medical_alerts), status=?, notes=?,
             tariff_category=COALESCE(?, tariff_category), country=COALESCE(?, country)
        WHERE id=?
    ");
    $stmt->execute([
        trim($input['first_name']),
        trim($input['last_name']),
        $input['date_of_birth'],
        $input['gender'],
        trim($input['national_id']          ?? ''),
        isset($input['blood_group']) ? trim($input['blood_group']) : null,   // no longer on the forms: keep what lab / maternity recorded
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
        isset($input['allergies']) ? trim($input['allergies']) : null,
        isset($input['chronic_diseases']) ? trim($input['chronic_diseases']) : null,
        isset($input['current_medications']) ? trim($input['current_medications']) : null,
        isset($input['medical_alerts']) ? trim($input['medical_alerts']) : null,
        $input['status']                    ?? 'active',
         trim($input['notes']                ?? ''),
         inputTariff($input),
         trim($input['country']              ?? '') ?: null,
         $id
    ]);

    $chg = [];
    foreach (['first_name','last_name','date_of_birth','gender','national_id','phone','email','blood_group','status','allergies','chronic_diseases'] as $cf) {
        if (array_key_exists($cf, $input) && trim((string)($beforeData[$cf] ?? '')) !== trim((string)$input[$cf])) $chg[$cf] = [(string)($beforeData[$cf] ?? ''), trim((string)$input[$cf])];
    }
    log_activity($pdo, 'UPDATE', 'patients',
        'Patient record updated: ' . trim($input['first_name']) . ' ' . trim($input['last_name']) .
        ' (' . ($beforeData['patient_no'] ?? '') . ')' .
        (!empty($changedFields) ? ' | Changed: ' . implode(', ', array_slice($changedFields, 0, 3)) : ''),
        $id, $beforeData['patient_no'] ?? null, $chg ?: null
    );

    // Update insurance if provided
    $ins = $input['insurance'] ?? [];
    if (!empty($ins['company_id'])) {
        // Policy number/dates are no longer collected; placeholders only used for new rows (NOT NULL columns)
        $policyStart = date('Y-m-d');
        $policyEnd = '9999-12-31';
        $policyNumber = 'POL-' . $id . '-' . date('Ymd');

        // Check if patient already has insurance record
        $existIns = $pdo->prepare("SELECT id FROM patient_insurance WHERE patient_id = ? AND is_primary = 1 LIMIT 1");
        $existIns->execute([$id]);
        $existInsRow = $existIns->fetch();

        if ($existInsRow) {
            // Update existing insurance
            $iStmt = $pdo->prepare("
                UPDATE patient_insurance SET
                    company_id=?, package_id=?, member_number=?,
                    principal_member_name=?, principal_phone=?, principal_member_number=?, employer_name=?,
                    status=?
                WHERE id=?
            ");
            $iStmt->execute([
                (int)$ins['company_id'],
                !empty($ins['package_id']) ? (int)$ins['package_id'] : null,
                trim($ins['member_number']              ?? ''),
                trim($ins['principal_member_name']      ?? ''),
                trim($ins['principal_phone']            ?? ''),
                trim($ins['principal_member_number']    ?? ''),
                trim($ins['employer_name']              ?? ''),
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

    if (!empty($ins['company_id'])) { require_once __DIR__ . '/../../includes/finance_posting.php'; fin_resync_insured_invoices($pdo, (int)$id); } // package change moves the patient share of existing invoices

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
    $fieldsToTrack = ['first_name','last_name','date_of_birth','gender','national_id','blood_group','phone','email','status','province_id','district_id','sector_id','cell_id','village_id','emergency_contact','next_of_kin','kin_relationship','allergies','chronic_diseases','current_medications','medical_alerts','notes','tariff_category'];
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
            $editRequestId ? 'Approved edit request #' . $editRequestId : (isUserAdmin($pdo) ? 'Admin edit' : 'Direct edit')
        ]);

        // Tell the editor when this edit used up the last allowed one
        if (!$isAdminUser && !$editRequestId && patientEditsUsed($pdo, $id) >= PATIENT_EDIT_LIMIT && !empty($_SESSION['user_id'])) {
            $pdo->prepare("INSERT INTO notifications (type, title, message, patient_id, target_user_id) VALUES ('edit_limit', ?, ?, ?, ?)")
                ->execute(['Edit limit reached', 'You have used all ' . PATIENT_EDIT_LIMIT . ' edits for patient ' . $beforeData['first_name'] . ' ' . $beforeData['last_name'] . '. Only an administrator can edit this record now.', $id, (int)$_SESSION['user_id']]);
        }

        // Mark edit request as used (change status back so it can't be reused)
        if ($editRequestId) {
            $pdo->prepare("UPDATE edit_requests SET status = 'pending' WHERE id = ? AND status = 'approved'")->execute([$editRequestId]);
            $pdo->prepare("DELETE FROM edit_requests WHERE id = ?")->execute([$editRequestId]);
        }
    }

    success($patient, 'Patient updated successfully');
}

// ==================== PATCH (partial update — medical profile) ====================
if ($method === 'PATCH') {
    if (!$id) error('Patient ID is required', 422);

    $chk = $pdo->prepare("SELECT id FROM patients WHERE id = ?");
    $chk->execute([$id]);
    if (!$chk->fetch()) error('Patient not found', 404);

    $allowed = ['blood_group', 'allergies', 'chronic_diseases', 'current_medications', 'medical_alerts'];
    $sets = []; $vals = [];
    foreach ($allowed as $field) {
        if (array_key_exists($field, $input)) {
            $sets[] = "$field = ?";
            $vals[] = trim($input[$field] ?? '');
        }
    }
    if (!$sets) error('No valid fields to update', 422);
    $vals[] = $id;
    $pdo->prepare("UPDATE patients SET " . implode(', ', $sets) . " WHERE id = ?")->execute($vals);

    log_activity($pdo, 'UPDATE', 'patients',
        'Medical profile updated for patient ID: ' . $id . ' | Fields: ' . implode(', ', array_map(fn($s) => explode(' =', $s)[0], $sets)),
        $id, null
    );

    success(['id' => $id], 'Medical profile updated');
}

// ==================== DELETE ====================
if ($method === 'DELETE') {
    if (!$id) error('Patient ID is required', 422);

    $chk = $pdo->prepare("SELECT id, patient_no, first_name, last_name FROM patients WHERE id = ?");
    $chk->execute([$id]);
    $delPat = $chk->fetch();
    if (!$delPat) error('Patient not found', 404);

    // CASCADE handles patient_insurance
    $pdo->prepare("DELETE FROM patients WHERE id = ?")->execute([$id]);
    log_activity($pdo, 'DELETE', 'patients', 'Patient deleted: ' . $delPat['first_name'] . ' ' . $delPat['last_name'], $id, $delPat['patient_no']);
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

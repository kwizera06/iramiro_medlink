<?php
// handlers/hr/employees.php
require_once __DIR__ . '/../../includes/database.php';

function jsonResponse($data, $status = 200) { http_response_code($status); echo json_encode($data); exit(); }
function success($data = null, $msg = 'Success', $status = 200) { jsonResponse(['success'=>true,'message'=>$msg,'data'=>$data], $status); }
function error($msg, $status = 400) { jsonResponse(['success'=>false,'message'=>$msg], $status); }

function generateEmpNo(PDO $pdo): string {
    $stmt = $pdo->query("SELECT employee_no FROM employees ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetchColumn();
    $seq  = $last ? ((int)substr($last, 4) + 1) : 1;
    return 'EMP-' . str_pad($seq, 5, '0', STR_PAD_LEFT);
}

function fetchEmployee(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("
        SELECT e.*, d.name AS department_name
        FROM employees e
        LEFT JOIN departments d ON d.id = e.department_id
        WHERE e.id = ?
    ");
    $stmt->execute([$id]);
    $emp = $stmt->fetch();
    if (!$emp) return null;
    // Attach documents
    $docs = $pdo->prepare("SELECT id, document_type, document_name, file_size, mime_type, created_at FROM employee_documents WHERE employee_id = ? ORDER BY created_at DESC");
    $docs->execute([$id]);
    $emp['documents'] = $docs->fetchAll();
    return $emp;
}

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {

// ===== GET =====
if ($method === 'GET') {
    if ($id) {
        $emp = fetchEmployee($pdo, $id);
        if (!$emp) error('Employee not found', 404);
        success($emp);
    }
    $search = trim($_GET['search'] ?? '');
    $dept   = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
    $status = $_GET['status'] ?? '';
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $where = []; $params = [];
    if ($search !== '') {
        $like = "%$search%";
        $where[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_no LIKE ? OR e.email LIKE ? OR e.phone LIKE ?)";
        $params  = array_merge($params, [$like,$like,$like,$like,$like]);
    }
    if ($dept > 0)    { $where[] = "e.department_id = ?"; $params[] = $dept; }
    if ($status !== '') { $where[] = "e.status = ?"; $params[] = $status; }
    $ws = $where ? 'WHERE '.implode(' AND ',$where) : '';

    $cnt = $pdo->prepare("SELECT COUNT(*) FROM employees e $ws");
    $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT e.*, d.name AS department_name
        FROM employees e
        LEFT JOIN departments d ON d.id = e.department_id
        $ws ORDER BY e.created_at DESC LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $employees = $stmt->fetchAll();

    $stats = $pdo->query("SELECT COUNT(*) AS total, SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) AS active, SUM(CASE WHEN status='on-leave' THEN 1 ELSE 0 END) AS on_leave, SUM(CASE WHEN status='suspended' THEN 1 ELSE 0 END) AS suspended, SUM(CASE WHEN status='terminated' THEN 1 ELSE 0 END) AS emp_terminated FROM employees")->fetch();

    success(['employees'=>$employees,'stats'=>$stats,'pagination'=>['total'=>$total,'page'=>$page,'limit'=>$limit,'total_pages'=>(int)ceil($total/max($limit,1))]]);
}

// ===== POST (multipart form for file upload support) =====
if ($method === 'POST') {
    $d = $_POST;
    foreach (['first_name','last_name','email','phone','gender','position','date_joined','gross_salary'] as $f) {
        if (empty($d[$f])) error("Field '$f' is required", 422);
    }
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) error('Invalid email', 422);

    $chk = $pdo->prepare("SELECT id FROM employees WHERE email = ?");
    $chk->execute([$d['email']]);
    if ($chk->fetch()) error('Email already exists', 409);

    $pdo->beginTransaction();
    $empNo = generateEmpNo($pdo);

    $stmt = $pdo->prepare("
        INSERT INTO employees
            (employee_no,first_name,last_name,email,phone,gender,date_of_birth,national_id,
             department_id,position,employment_type,employee_category,date_joined,gross_salary,status,address,
             bank_name,bank_account_number,bank_account_name,
             mlbs_amount,medical_amount,mutuelle_amount,notes,
             intern_supervisor,intern_duration_days,intern_start_date,intern_end_date,
             casual_hourly_rate,casual_max_hours_per_week,casual_contract_end_date,payment_frequency)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([
        $empNo,
        trim($d['first_name']), trim($d['last_name']),
        trim($d['email']),      trim($d['phone']),
        $d['gender'],
        !empty($d['date_of_birth']) ? $d['date_of_birth'] : null,
        trim($d['national_id'] ?? ''),
        !empty($d['department_id']) ? (int)$d['department_id'] : null,
        trim($d['position']),
        $d['employment_type'] ?? 'full-time',
        $d['employee_category'] ?? null,
        $d['date_joined'],
        (float)$d['gross_salary'],
        $d['status'] ?? 'active',
        trim($d['address'] ?? ''),
        trim($d['bank_name'] ?? ''),
        trim($d['bank_account_number'] ?? ''),
        trim($d['bank_account_name'] ?? ''),
        (float)($d['mlbs_amount'] ?? 0),
        (float)($d['medical_amount'] ?? 0),
        (float)($d['mutuelle_amount'] ?? 0),
        trim($d['notes'] ?? ''),
        trim($d['intern_supervisor'] ?? '') !== '' ? (int)$d['intern_supervisor'] : null,
        !empty($d['intern_duration_days']) ? (int)$d['intern_duration_days'] : null,
        !empty($d['intern_start_date']) ? $d['intern_start_date'] : null,
        !empty($d['intern_end_date']) ? $d['intern_end_date'] : null,
        (float)($d['casual_hourly_rate'] ?? 0),
        !empty($d['casual_max_hours_per_week']) ? (int)$d['casual_max_hours_per_week'] : null,
        !empty($d['casual_contract_end_date']) ? $d['casual_contract_end_date'] : null,
        $d['payment_frequency'] ?? 'monthly'
    ]);
    $newId = (int)$pdo->lastInsertId();

    // Handle document uploads
    if (!empty($_FILES['documents']['name'][0])) {
        $uploadDir = __DIR__ . '/../../uploads/employee_documents/';
        $allowedTypes = ['application/pdf','image/jpeg','image/png','application/msword',
                         'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $docStmt = $pdo->prepare("INSERT INTO employee_documents (employee_id,document_type,document_name,file_path,file_size,mime_type) VALUES (?,?,?,?,?,?)");
        $docTypes = $_POST['document_types'] ?? [];
        foreach ($_FILES['documents']['name'] as $i => $fname) {
            if ($_FILES['documents']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $mime = mime_content_type($_FILES['documents']['tmp_name'][$i]);
            if (!in_array($mime, $allowedTypes)) continue;
            $ext      = pathinfo($fname, PATHINFO_EXTENSION);
            $safeName = $empNo . '_' . uniqid() . '.' . $ext;
            $dest     = $uploadDir . $safeName;
            if (move_uploaded_file($_FILES['documents']['tmp_name'][$i], $dest)) {
                $docStmt->execute([
                    $newId,
                    $docTypes[$i] ?? 'Other',
                    $fname,
                    'uploads/employee_documents/' . $safeName,
                    $_FILES['documents']['size'][$i],
                    $mime
                ]);
            }
        }
    }

    $pdo->commit();
    $emp = fetchEmployee($pdo, $newId);
    success($emp, 'Employee added successfully', 201);
}

// ===== PUT =====
if ($method === 'PUT') {
    if (!$id) error('Employee ID required', 422);
    $chk = $pdo->prepare("SELECT id FROM employees WHERE id = ?"); $chk->execute([$id]);
    if (!$chk->fetch()) error('Employee not found', 404);

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    foreach (['first_name','last_name','email','phone','gender','position','date_joined','gross_salary'] as $f) {
        if (empty($input[$f])) error("Field '$f' is required", 422);
    }
    $dupChk = $pdo->prepare("SELECT id FROM employees WHERE email = ? AND id != ?");
    $dupChk->execute([$input['email'], $id]);
    if ($dupChk->fetch()) error('Email used by another employee', 409);

    $stmt = $pdo->prepare("
        UPDATE employees SET
            first_name=?,last_name=?,email=?,phone=?,gender=?,date_of_birth=?,national_id=?,
            department_id=?,position=?,employment_type=?,employee_category=?,date_joined=?,gross_salary=?,status=?,address=?,
            bank_name=?,bank_account_number=?,bank_account_name=?,
            mlbs_amount=?,medical_amount=?,mutuelle_amount=?,notes=?,
            intern_supervisor=?,intern_duration_days=?,intern_start_date=?,intern_end_date=?,
            casual_hourly_rate=?,casual_max_hours_per_week=?,casual_contract_end_date=?,payment_frequency=?
        WHERE id=?
    ");
    $stmt->execute([
        trim($input['first_name']), trim($input['last_name']),
        trim($input['email']),      trim($input['phone']),
        $input['gender'],
        !empty($input['date_of_birth']) ? $input['date_of_birth'] : null,
        trim($input['national_id'] ?? ''),
        !empty($input['department_id']) ? (int)$input['department_id'] : null,
        trim($input['position']),
        $input['employment_type'] ?? 'full-time',
        $input['employee_category'] ?? null,
        $input['date_joined'],
        (float)$input['gross_salary'],
        $input['status'] ?? 'active',
        trim($input['address'] ?? ''),
        trim($input['bank_name'] ?? ''),
        trim($input['bank_account_number'] ?? ''),
        trim($input['bank_account_name'] ?? ''),
        (float)($input['mlbs_amount'] ?? 0),
        (float)($input['medical_amount'] ?? 0),
        (float)($input['mutuelle_amount'] ?? 0),
        trim($input['notes'] ?? ''),
        trim($input['intern_supervisor'] ?? '') !== '' ? (int)$input['intern_supervisor'] : null,
        !empty($input['intern_duration_days']) ? (int)$input['intern_duration_days'] : null,
        !empty($input['intern_start_date']) ? $input['intern_start_date'] : null,
        !empty($input['intern_end_date']) ? $input['intern_end_date'] : null,
        (float)($input['casual_hourly_rate'] ?? 0),
        !empty($input['casual_max_hours_per_week']) ? (int)$input['casual_max_hours_per_week'] : null,
        !empty($input['casual_contract_end_date']) ? $input['casual_contract_end_date'] : null,
        $input['payment_frequency'] ?? 'monthly',
        $id
    ]);
    success(fetchEmployee($pdo, $id), 'Employee updated');
}

// ===== DELETE =====
if ($method === 'DELETE') {
    if (!$id) error('Employee ID required', 422);
    $chk = $pdo->prepare("SELECT id FROM employees WHERE id = ?"); $chk->execute([$id]);
    if (!$chk->fetch()) error('Employee not found', 404);
    $pdo->prepare("DELETE FROM employees WHERE id = ?")->execute([$id]);
    success(null, 'Employee deleted');
}

error('Method not allowed', 405);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error('DB error: '.$e->getMessage(), 500);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error('Server error: '.$e->getMessage(), 500);
}
?>

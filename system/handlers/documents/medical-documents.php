<?php
// handlers/documents/medical-documents.php
require_once __DIR__ . '/../../includes/database.php';

function jsonResponse($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
function success($d=null,$m='Success',$s=200){jsonResponse(['success'=>true,'message'=>$m,'data'=>$d],$s);}
function error($m,$s=400){jsonResponse(['success'=>false,'message'=>$m],$s);}

$method = $_SERVER['REQUEST_METHOD'];

// Reference prefix per document type
$prefixes = [
    'care_sick_child' => 'CSC',
    'leave_certificate' => 'MLC',
    'test_certificate' => 'TST',
    'dietary_restriction' => 'DRC',
    'fitness_certificate' => 'MFC',
    'biological_certificate' => 'BIO',
    'hospitalization_certificate' => 'CHO',
    'referral_form' => 'RTF',
    'echography_report' => 'ECH',
    'followup_sheet' => 'FSH',
    'dental_lab' => 'DLA',
    'attestation_service' => 'ATS',
    'attestation_service_rendu' => 'ASR',
    'mission_order' => 'MSO',
    'external_transfer' => 'ETF'
];

// Atomically reserve the next reference for a prefix/day. A plain MAX+1 preview
// returned the same number to two concurrent callers, so callers now consume a
// counter row (the document table's UNIQUE key remains the final guard).
function documentNextReference(PDO $pdo, string $prefix, string $today): string {
    $pdo->exec("CREATE TABLE IF NOT EXISTS document_ref_counters (
        prefix VARCHAR(10) NOT NULL,
        day CHAR(8) NOT NULL,
        last_seq INT NOT NULL DEFAULT 0,
        PRIMARY KEY (prefix, day)
    ) ENGINE=InnoDB");

    $max = $pdo->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(reference_no, '-', -1) AS UNSIGNED)), 0)
                          FROM medical_documents WHERE reference_no LIKE ?");
    $max->execute([$prefix . '-' . $today . '-%']);
    $docMax = (int)$max->fetchColumn();

    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO document_ref_counters (prefix, day, last_seq) VALUES (?, ?, ?)
                       ON DUPLICATE KEY UPDATE last_seq = GREATEST(last_seq, ?) + 1")
            ->execute([$prefix, $today, $docMax + 1, $docMax]);
        $q = $pdo->prepare("SELECT last_seq FROM document_ref_counters WHERE prefix = ? AND day = ?");
        $q->execute([$prefix, $today]);
        $seq = (int)$q->fetchColumn();
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    return $prefix . '-' . $today . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
}

try {
    // Ensure table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS medical_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reference_no VARCHAR(50) NOT NULL UNIQUE,
        document_type VARCHAR(50) NOT NULL,
        patient_name VARCHAR(200) NOT NULL,
        physician_name VARCHAR(200),
        document_data JSON,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // GET - List/Search documents
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'list';
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        // Patient name, ID, age, sex and the latest recorded vitals, read from the system (leave certificate auto-fill)
        if ($action === 'patient_clinical') {
            $pid = (int)($_GET['patient_id'] ?? 0);
            $ps = $pdo->prepare("SELECT first_name, last_name, patient_no, national_id, gender, date_of_birth FROM patients WHERE id = ?");
            $ps->execute([$pid]);
            $p = $ps->fetch();
            if (!$p) error('Patient not found', 404);
            $vs = $pdo->prepare("SELECT vs.slug, vr.value FROM vital_signs_records vr JOIN vital_signs_settings vs ON vs.id = vr.vital_sign_id
                WHERE vr.patient_id = ? AND vr.id = (SELECT MAX(x.id) FROM vital_signs_records x WHERE x.patient_id = vr.patient_id AND x.vital_sign_id = vr.vital_sign_id)");
            $vs->execute([$pid]);
            $v = $vs->fetchAll(PDO::FETCH_KEY_PAIR);
            success([
                'name' => trim($p['first_name'] . ' ' . $p['last_name']), 'patient_no' => $p['patient_no'], 'national_id' => $p['national_id'],
                'sex' => $p['gender'], 'age' => $p['date_of_birth'] ? (int)(new DateTime($p['date_of_birth']))->diff(new DateTime())->y : null,
                'vitals' => ['bp' => $v['bp'] ?? '', 'hr' => $v['hr'] ?? '', 'rr' => $v['resp'] ?? '', 'temp' => $v['temp'] ?? '', 'spo2' => $v['spo2'] ?? '',
                             'weight' => $v['weight'] ?? '', 'height' => $v['size'] ?? '', 'bmi' => $v['bmi'] ?? ''],
            ]);
        }

        // Get single document by ID
        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM medical_documents WHERE id = ?");
            $stmt->execute([$id]);
            $doc = $stmt->fetch();
            if (!$doc) error('Document not found', 404);
            $doc['document_data'] = json_decode($doc['document_data'], true);
            success($doc);
        }
        
        // Verify document by reference_no
        if ($action === 'verify') {
            $ref = trim($_GET['ref'] ?? '');
            if (!$ref) error('Reference number required', 422);
            $stmt = $pdo->prepare("SELECT * FROM medical_documents WHERE reference_no = ?");
            $stmt->execute([$ref]);
            $doc = $stmt->fetch();
            if (!$doc) error('Document not found or invalid reference', 404);
            $doc['document_data'] = json_decode($doc['document_data'], true);
            success($doc, 'Document verified successfully');
        }

        // Get next reference number
        if ($action === 'next_ref') {
            $type = $_GET['type'] ?? 'DOC';
            $prefix = $prefixes[$type] ?? strtoupper(substr($type, 0, 3));
            $today = date('Ymd');
            $ref = documentNextReference($pdo, $prefix, $today);
            success(['reference_no' => $ref], 'Next reference reserved');
        }

        // List with search
        $search = trim($_GET['search'] ?? '');
        $type = $_GET['type'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $where = [];
        $params = [];
        if ($search !== '') {
            $like = "%{$search}%";
            $where[] = "(reference_no LIKE ? OR patient_name LIKE ? OR physician_name LIKE ?)";
            $params = array_merge($params, [$like, $like, $like]);
        }
        if ($type !== '') {
            $where[] = "document_type = ?";
            $params[] = $type;
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM medical_documents $whereSQL");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Rows
        $stmt = $pdo->prepare("SELECT id, reference_no, document_type, patient_name, physician_name, created_at FROM medical_documents $whereSQL ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        $documents = $stmt->fetchAll();

        success([
            'documents' => $documents,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ]
        ]);
    }

    // POST - Save document
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        
        $docType = $input['document_type'] ?? '';
        $patientName = trim($input['patient_name'] ?? '');
        $physicianName = trim($input['physician_name'] ?? '');
        $docData = $input['document_data'] ?? [];

        if (!$docType) error('document_type required', 422);
        if (!$patientName) error('patient_name required', 422);

        // Generate or validate reference
        $prefix = $prefixes[$docType] ?? 'DOC';
        $today = date('Ymd');

        $refNo = trim($input['reference_no'] ?? '');
        if ($refNo) {
            $chk = $pdo->prepare("SELECT id FROM medical_documents WHERE reference_no = ?");
            $chk->execute([$refNo]);
            if ($chk->fetch()) {
                $refNo = ''; // Taken, generate next in sequence
            }
        }

        if (!$refNo) {
            $refNo = documentNextReference($pdo, $prefix, $today);
        }

        $stmt = $pdo->prepare("INSERT INTO medical_documents (reference_no, document_type, patient_name, physician_name, document_data, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$refNo, $docType, $patientName, $physicianName, json_encode($docData)]);

        success([
            'id' => (int)$pdo->lastInsertId(),
            'reference_no' => $refNo
        ], 'Document saved. Reference: ' . $refNo);
    }

    error('Method not allowed', 405);
} catch (PDOException $e) {
    error('Database error: ' . $e->getMessage(), 500);
}
?>

<?php
// handlers/documents/medical-documents.php
require_once __DIR__ . '/../../includes/database.php';

function jsonResponse($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
function success($d=null,$m='Success',$s=200){jsonResponse(['success'=>true,'message'=>$m,'data'=>$d],$s);}
function error($m,$s=400){jsonResponse(['success'=>false,'message'=>$m],$s);}

$method = $_SERVER['REQUEST_METHOD'];

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
            $prefixes = [
                'care_sick_child' => 'CSC',
                'leave_certificate' => 'MLC',
                'test_certificate' => 'TST',
                'dietary_restriction' => 'DRC',
                'fitness_certificate' => 'MFC'
            ];
            $prefix = $prefixes[$type] ?? strtoupper(substr($type, 0, 3));
            $today = date('Ymd');
            $pattern = $prefix . '-' . $today . '-%';
            $stmt = $pdo->prepare("SELECT reference_no FROM medical_documents WHERE reference_no LIKE ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$pattern]);
            $last = $stmt->fetchColumn();
            $seq = 1;
            if ($last && preg_match('/(\d+)$/', $last, $m)) $seq = (int)$m[1] + 1;
            $ref = $prefix . '-' . $today . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
            success(['reference_no' => $ref]);
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
        $prefixes = [
            'care_sick_child' => 'CSC',
            'leave_certificate' => 'MLC',
            'test_certificate' => 'TST',
            'dietary_restriction' => 'DRC',
            'fitness_certificate' => 'MFC'
        ];
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
            $pattern = $prefix . '-' . $today . '-%';
            $stmt = $pdo->prepare("SELECT reference_no FROM medical_documents WHERE reference_no LIKE ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$pattern]);
            $last = $stmt->fetchColumn();
            $seq = 1;
            if ($last && preg_match('/(\d+)$/', $last, $m)) $seq = (int)$m[1] + 1;
            $refNo = $prefix . '-' . $today . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
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

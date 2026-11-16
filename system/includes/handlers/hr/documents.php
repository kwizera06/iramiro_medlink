<?php
// handlers/hr/documents.php
require_once __DIR__ . '/../../includes/database.php';

function jsonResponse($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
function success($d=null,$m='Success',$s=200){jsonResponse(['success'=>true,'message'=>$m,'data'=>$d],$s);}
function error($m,$s=400){jsonResponse(['success'=>false,'message'=>$m],$s);}

$method   = $_SERVER['REQUEST_METHOD'];
$id       = isset($_GET['id'])          ? (int)$_GET['id']          : null;
$empId    = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : null;
$download = isset($_GET['download'])    && $_GET['download'] === '1';

try {

// ===== GET — list or download =====
if ($method === 'GET') {
    if ($id && $download) {
        $stmt = $pdo->prepare("SELECT * FROM employee_documents WHERE id=?");
        $stmt->execute([$id]);
        $doc = $stmt->fetch();
        if (!$doc) { http_response_code(404); echo 'File not found'; exit(); }
        $path = __DIR__ . '/../../' . $doc['file_path'];
        if (!file_exists($path)) { http_response_code(404); echo 'File missing on disk'; exit(); }
        header('Content-Description: File Transfer');
        header('Content-Type: ' . ($doc['mime_type'] ?: 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . basename($doc['document_name']) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit();
    }
    if ($empId) {
        $stmt = $pdo->prepare("SELECT * FROM employee_documents WHERE employee_id=? ORDER BY created_at DESC");
        $stmt->execute([$empId]);
        success($stmt->fetchAll());
    }
    error('employee_id required', 422);
}

// ===== POST — upload additional documents =====
if ($method === 'POST') {
    $empId2 = (int)($_POST['employee_id'] ?? 0);
    if (!$empId2) error('employee_id required', 422);
    $chk = $pdo->prepare("SELECT id FROM employees WHERE id=?"); $chk->execute([$empId2]);
    if (!$chk->fetch()) error('Employee not found', 404);

    if (empty($_FILES['document']['name'])) error('No file uploaded', 422);
    if ($_FILES['document']['error'] !== UPLOAD_ERR_OK) error('Upload error', 400);

    $allowed   = ['application/pdf','image/jpeg','image/png','application/msword',
                  'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $mime      = mime_content_type($_FILES['document']['tmp_name']);
    if (!in_array($mime, $allowed)) error('File type not allowed. Use PDF, JPG, PNG, or DOCX.', 422);
    if ($_FILES['document']['size'] > 10 * 1024 * 1024) error('File too large (max 10MB)', 422);

    // Get employee_no for naming
    $empRow = $pdo->prepare("SELECT employee_no FROM employees WHERE id=?");
    $empRow->execute([$empId2]);
    $empNo = $empRow->fetchColumn();

    $uploadDir = __DIR__ . '/../../uploads/employee_documents/';
    $ext       = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
    $safeName  = $empNo . '_' . uniqid() . '.' . $ext;
    $dest      = $uploadDir . $safeName;

    if (!move_uploaded_file($_FILES['document']['tmp_name'], $dest)) error('Failed to save file', 500);

    $stmt = $pdo->prepare("INSERT INTO employee_documents (employee_id,document_type,document_name,file_path,file_size,mime_type) VALUES (?,?,?,?,?,?)");
    $stmt->execute([
        $empId2,
        trim($_POST['document_type'] ?? 'Other'),
        $_FILES['document']['name'],
        'uploads/employee_documents/' . $safeName,
        $_FILES['document']['size'],
        $mime
    ]);
    $newId = (int)$pdo->lastInsertId();
    $row   = $pdo->prepare("SELECT * FROM employee_documents WHERE id=?"); $row->execute([$newId]);
    success($row->fetch(), 'Document uploaded', 201);
}

// ===== DELETE =====
if ($method === 'DELETE') {
    if (!$id) error('Document ID required', 422);
    $stmt = $pdo->prepare("SELECT * FROM employee_documents WHERE id=?"); $stmt->execute([$id]);
    $doc  = $stmt->fetch();
    if (!$doc) error('Document not found', 404);
    // Remove file from disk
    $path = __DIR__ . '/../../' . $doc['file_path'];
    if (file_exists($path)) @unlink($path);
    $pdo->prepare("DELETE FROM employee_documents WHERE id=?")->execute([$id]);
    success(null, 'Document deleted');
}

error('Method not allowed', 405);
} catch (PDOException $e) { error('DB error: '.$e->getMessage(), 500); }
  catch (Exception $e)    { error('Server error: '.$e->getMessage(), 500); }
?>

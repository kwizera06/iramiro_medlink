<?php
// handlers/documents.php — Document Management API
require_once __DIR__ . '/../includes/database.php';

function jsonResp($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
function ok($d=null,$m='Success'){jsonResp(['success'=>true,'message'=>$m,'data'=>$d]);}
function err($m,$s=400){jsonResp(['success'=>false,'message'=>$m],$s);}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (session_status() === PHP_SESSION_NONE) session_start();
$userId = $_SESSION['user_id'] ?? 0;

$userId = (int)$userId;
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/records_schema.php';
rec_ensure_schema($pdo);
$isAdmin = isUserAdmin($pdo);

const DOC_CATEGORIES = ['general','lab_report','imaging','consent','referral','discharge','prescription','insurance','identification','other_clinical'];

function doc_log(PDO $pdo, int $docId, string $action, ?int $version = null): void {
    $pdo->prepare("INSERT INTO document_access_log (document_id, version_number, user_id, action, ip) VALUES (?,?,?,?,?)")
        ->execute([$docId, $version, (int)($_SESSION['user_id'] ?? 0) ?: null, $action, $_SERVER['REMOTE_ADDR'] ?? null]);
}

// Patient / visit sanity: ids must exist, and a visit must belong to the patient
function doc_patient_visit(PDO $pdo, $patientId, $visitId): array {
    $patientId = (int)$patientId ?: null;
    $visitId = (int)$visitId ?: null;
    if ($patientId) {
        $q = $pdo->prepare("SELECT 1 FROM patients WHERE id = ?");
        $q->execute([$patientId]);
        if (!$q->fetchColumn()) err('Patient not found', 404);
    }
    if ($visitId) {
        if (!$patientId) err('A visit needs a patient');
        $q = $pdo->prepare("SELECT 1 FROM visits WHERE id = ? AND patient_id = ?");
        $q->execute([$visitId, $patientId]);
        if (!$q->fetchColumn()) err('Visit does not belong to this patient');
    }
    return [$patientId, $visitId];
}

$uploadDir = __DIR__ . '/../uploads/documents/';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

// Fail with the real reason instead of a generic "upload failed"
function checkUpload($uploadDir) {
    if (empty($_FILES['file'])) {
        err('No file received. It may be larger than the server limit (' . ini_get('post_max_size') . ').');
    }
    $code = $_FILES['file']['error'];
    if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) err('File too large. Server limit is ' . ini_get('upload_max_filesize') . '.');
    if ($code === UPLOAD_ERR_PARTIAL) err('Upload was interrupted. Please try again.');
    if ($code === UPLOAD_ERR_NO_FILE) err('Please select a file.');
    if ($code !== UPLOAD_ERR_OK) err('Server upload error (code ' . $code . ').');
    if (!is_dir($uploadDir) || !is_writable($uploadDir)) err('Upload folder uploads/documents is missing or not writable on the server.');
}

// ===== GET =====
if ($method === 'GET') {

    // Download a file (current or a given version) with an access-log entry
    if ($action === 'download' && $id) {
        $stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ? AND status <> 'deleted'");
        $stmt->execute([$id]);
        $doc = $stmt->fetch();
        if (!$doc) err('Document not found', 404);
        $path = $doc['file_path']; $name = $doc['file_name']; $ver = (int)$doc['current_version'];
        if (!empty($_GET['v']) && (int)$_GET['v'] !== $ver) {
            $vs = $pdo->prepare("SELECT * FROM document_versions WHERE document_id = ? AND version_number = ?");
            $vs->execute([$id, (int)$_GET['v']]);
            $v = $vs->fetch();
            if (!$v) err('Version not found', 404);
            $path = $v['file_path']; $name = $v['file_name']; $ver = (int)$v['version_number'];
        }
        $real = realpath(__DIR__ . '/../' . $path);
        $root = realpath($uploadDir);
        if (!$real || !$root || strpos($real, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($real)) err('File missing on server', 404);
        doc_log($pdo, $id, 'download', $ver);
        header_remove('Content-Type');
        header('Content-Type: ' . ($doc['mime_type'] ?: 'application/octet-stream'));
        header('Content-Length: ' . filesize($real));
        header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', $name) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($real);
        exit();
    }

    // Who opened / changed what (admin only)
    if ($action === 'access_log') {
        if (!$isAdmin) err('Only administrators can read the access log', 403);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 25)));
        $offset = ($page - 1) * $limit;
        $total = (int)$pdo->query("SELECT COUNT(*) FROM document_access_log")->fetchColumn();
        $rows = $pdo->query("SELECT l.*, d.title, CONCAT(u.first_name,' ',u.last_name) AS user_name
            FROM document_access_log l
            LEFT JOIN documents d ON d.id = l.document_id
            LEFT JOIN users u ON u.id = l.user_id
            ORDER BY l.id DESC LIMIT $limit OFFSET $offset")->fetchAll();
        ok(['log' => $rows, 'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'total_pages' => (int)ceil($total / $limit)]]);
    }

    // Patient picker for clinical uploads
    if ($action === 'patients') {
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) ok([]);
        $like = "%$q%";
        $s = $pdo->prepare("SELECT id, patient_no, CONCAT(first_name,' ',last_name) AS name FROM patients
            WHERE patient_no LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name,' ',last_name) LIKE ? OR phone LIKE ?
            ORDER BY first_name LIMIT 15");
        $s->execute([$like, $like, $like, $like, $like]);
        ok($s->fetchAll());
    }

    // Visits of one patient (to attach a file to a visit)
    if ($action === 'patient_visits') {
        $s = $pdo->prepare("SELECT id, visit_no, visit_date FROM visits WHERE patient_id = ? ORDER BY id DESC LIMIT 30");
        $s->execute([(int)($_GET['patient_id'] ?? 0)]);
        ok($s->fetchAll());
    }

    // List folders
    if ($action === 'folders') {
        $parentId = isset($_GET['parent_id']) ? (int)$_GET['parent_id'] : null;
        if ($parentId) {
            $stmt = $pdo->prepare("SELECT f.*, CONCAT(u.first_name,' ',u.last_name) AS created_by_name,
                (SELECT COUNT(*) FROM document_folders WHERE parent_id=f.id) AS subfolder_count,
                (SELECT COUNT(*) FROM documents WHERE folder_id=f.id AND status='active') AS document_count
                FROM document_folders f LEFT JOIN users u ON u.id=f.created_by WHERE f.parent_id=? ORDER BY f.name");
            $stmt->execute([$parentId]);
        } else {
            $stmt = $pdo->prepare("SELECT f.*, CONCAT(u.first_name,' ',u.last_name) AS created_by_name,
                (SELECT COUNT(*) FROM document_folders WHERE parent_id=f.id) AS subfolder_count,
                (SELECT COUNT(*) FROM documents WHERE folder_id=f.id AND status='active') AS document_count
                FROM document_folders f LEFT JOIN users u ON u.id=f.created_by WHERE f.parent_id IS NULL ORDER BY f.name");
            $stmt->execute();
        }
        $folders = $stmt->fetchAll();
        // Get breadcrumb path
        $breadcrumb = [];
        if ($parentId) {
            $pid = $parentId;
            while ($pid) {
                $bStmt = $pdo->prepare("SELECT id, name, parent_id FROM document_folders WHERE id=?");
                $bStmt->execute([$pid]);
                $b = $bStmt->fetch();
                if ($b) { array_unshift($breadcrumb, $b); $pid = $b['parent_id']; }
                else break;
            }
        }
        ok(['folders' => $folders, 'breadcrumb' => $breadcrumb]);
    }

    // All folders flat (for select dropdown)
    if ($action === 'all_folders') {
        $stmt = $pdo->query("SELECT id, name, parent_id FROM document_folders ORDER BY name");
        $folders = $stmt->fetchAll();
        // Build hierarchy labels
        $map = [];
        foreach ($folders as $f) $map[$f['id']] = $f;
        foreach ($folders as &$f) {
            $path = $f['name'];
            $pid = $f['parent_id'];
            while ($pid && isset($map[$pid])) {
                $path = $map[$pid]['name'] . ' / ' . $path;
                $pid = $map[$pid]['parent_id'];
            }
            $f['full_path'] = $path;
        }
        unset($f);
        usort($folders, function($a,$b){ return strcmp($a['full_path'],$b['full_path']); });
        ok($folders);
    }

    // Single document with versions
    if ($id) {
        $stmt = $pdo->prepare("SELECT d.*, CONCAT(u.first_name,' ',u.last_name) AS uploaded_by_name,
            CONCAT(p.first_name,' ',p.last_name) AS patient_name, p.patient_no
            FROM documents d LEFT JOIN users u ON u.id = d.uploaded_by LEFT JOIN patients p ON p.id = d.patient_id WHERE d.id = ?");
        $stmt->execute([$id]);
        $doc = $stmt->fetch();
        if (!$doc) err('Document not found', 404);

        // Get versions
        $vStmt = $pdo->prepare("SELECT dv.*, CONCAT(u.first_name,' ',u.last_name) AS uploaded_by_name
            FROM document_versions dv LEFT JOIN users u ON u.id = dv.uploaded_by
            WHERE dv.document_id = ? ORDER BY dv.version_number DESC");
        $vStmt->execute([$id]);
        $doc['versions'] = $vStmt->fetchAll();
        ok($doc);
    }

    // List documents
    $type = $_GET['type'] ?? '';
    $status = $_GET['status'] ?? 'active';
    $search = trim($_GET['search'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 25)));
    $offset = ($page - 1) * $limit;

    $where = [];
    $params = [];
    if ($status) {
        if (!in_array($status, ['active', 'archived'], true)) $status = 'active'; // 'deleted' is never listed
        $where[] = "d.status = ?"; $params[] = $status;
    }
    if (($_GET['scope'] ?? '') === 'patient') $where[] = "d.patient_id IS NOT NULL";
    if (!empty($_GET['patient_id'])) { $where[] = "d.patient_id = ?"; $params[] = (int)$_GET['patient_id']; }
    if (!empty($_GET['category']) && in_array($_GET['category'], DOC_CATEGORIES, true)) { $where[] = "d.category = ?"; $params[] = $_GET['category']; }
    if ($type) { $where[] = "d.document_type = ?"; $params[] = $type; }
    if (isset($_GET['folder_id'])) {
        $folderId = $_GET['folder_id'];
        if ($folderId === '' || $folderId === 'null') {
            $where[] = "d.folder_id IS NULL";
        } else {
            $where[] = "d.folder_id = ?"; $params[] = (int)$folderId;
        }
    }
    if ($search) {
        $like = "%$search%";
        $where[] = "(d.title LIKE ? OR d.description LIKE ? OR d.tags LIKE ? OR d.file_name LIKE ? OR CONCAT(p.first_name,' ',p.last_name) LIKE ? OR p.patient_no LIKE ?)";
        $params = array_merge($params, [$like, $like, $like, $like, $like, $like]);
    }
    $ws = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $cnt = $pdo->prepare("SELECT COUNT(*) FROM documents d LEFT JOIN patients p ON p.id = d.patient_id $ws");
    $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();

    $stmt = $pdo->prepare("SELECT d.*, CONCAT(u.first_name,' ',u.last_name) AS uploaded_by_name,
        CONCAT(p.first_name,' ',p.last_name) AS patient_name, p.patient_no
        FROM documents d LEFT JOIN users u ON u.id = d.uploaded_by LEFT JOIN patients p ON p.id = d.patient_id
        $ws ORDER BY d.updated_at DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $docs = $stmt->fetchAll();

    // Stats
    $statsStmt = $pdo->query("SELECT document_type, COUNT(*) as cnt FROM documents WHERE status='active' GROUP BY document_type");
    $statsRaw = $statsStmt->fetchAll();
    $stats = ['total' => 0];
    foreach ($statsRaw as $s) { $stats[$s['document_type']] = (int)$s['cnt']; $stats['total'] += (int)$s['cnt']; }
    // Clinical archive counters (category keys never clash with the document_type keys above)
    foreach ($pdo->query("SELECT category, COUNT(*) AS cnt FROM documents WHERE status='active' GROUP BY category")->fetchAll() as $r) $stats[$r['category']] = (int)$r['cnt'];
    $stats['patient'] = (int)$pdo->query("SELECT COUNT(*) FROM documents WHERE status='active' AND patient_id IS NOT NULL")->fetchColumn();
    $stats['archived'] = (int)$pdo->query("SELECT COUNT(*) FROM documents WHERE status='archived'")->fetchColumn();
    $stats['retention_due'] = (int)$pdo->query("SELECT COUNT(*) FROM documents WHERE status<>'deleted' AND retention_until IS NOT NULL AND retention_until <= CURDATE()")->fetchColumn();

    ok([
        'documents' => $docs,
        'stats' => $stats,
        'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'total_pages' => ceil($total / $limit)]
    ]);
}

// ===== POST — Upload new document or new version =====
if ($method === 'POST') {

    // Create folder
    if ($action === 'create_folder') {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $name = trim($input['name'] ?? '');
        $parentId = !empty($input['parent_id']) ? (int)$input['parent_id'] : null;
        $description = trim($input['description'] ?? '');
        $color = trim($input['color'] ?? '#6b7280');
        $createdBy = (int)($input['created_by'] ?? $userId);
        if (!$name) err('Folder name is required');
        $stmt = $pdo->prepare("INSERT INTO document_folders (name, parent_id, description, color, created_by) VALUES (?,?,?,?,?)");
        $stmt->execute([$name, $parentId, $description, $color, $createdBy]);
        ok(['id' => (int)$pdo->lastInsertId()], 'Folder created');
    }

    // Rename folder
    if ($action === 'rename_folder') {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $folderId = (int)($input['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        if (!$folderId || !$name) err('Folder ID and name required');
        $pdo->prepare("UPDATE document_folders SET name=?, description=?, color=? WHERE id=?")
            ->execute([$name, trim($input['description'] ?? ''), trim($input['color'] ?? '#6b7280'), $folderId]);
        ok(null, 'Folder updated');
    }

    // Delete folder
    if ($action === 'delete_folder') {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $folderId = (int)($input['id'] ?? 0);
        if (!$folderId) err('Folder ID required');
        // Move documents in this folder to root
        $pdo->prepare("UPDATE documents SET folder_id = NULL WHERE folder_id = ?")->execute([$folderId]);
        // Delete folder (cascade deletes subfolders)
        $pdo->prepare("DELETE FROM document_folders WHERE id = ?")->execute([$folderId]);
        ok(null, 'Folder deleted. Documents moved to root.');
    }

    // Archive / restore: records are never destroyed, only moved out of the working list
    if ($action === 'archive' || $action === 'restore') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $docId = (int)($input['id'] ?? 0);
        $from = $action === 'archive' ? 'active' : 'archived';
        if (!$docId) err('Document ID required');
        if ($action === 'archive') {
            $st = $pdo->prepare("UPDATE documents SET status='archived', archived_at=NOW(), archived_by=? WHERE id=? AND status=?");
            $st->execute([$userId, $docId, $from]);
        } else {
            $st = $pdo->prepare("UPDATE documents SET status='active', archived_at=NULL, archived_by=NULL WHERE id=? AND status=?");
            $st->execute([$docId, $from]);
        }
        if (!$st->rowCount()) err('Document is not ' . $from, 409);
        doc_log($pdo, $docId, $action);
        ok(null, $action === 'archive' ? 'Document archived' : 'Document restored');
    }

    // Upload new version of existing document
    if ($action === 'new_version') {
        checkUpload($uploadDir);
        $docId = (int)($_POST['document_id'] ?? 0);
        $changeNotes = trim($_POST['change_notes'] ?? '');
        $uploadedBy = (int)($_POST['uploaded_by'] ?? $userId);

        if (!$docId) err('document_id required');
        $docStmt = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
        $docStmt->execute([$docId]);
        $doc = $docStmt->fetch();
        if (!$doc) err('Document not found', 404);

        $file = $_FILES['file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $newVersion = (int)$doc['current_version'] + 1;
        $filename = 'doc_' . $docId . '_v' . $newVersion . '_' . time() . '.' . $ext;
        $dest = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) err('Failed to save file');

        $pdo->beginTransaction();
        try {
            // Insert version record
            $pdo->prepare("INSERT INTO document_versions (document_id, version_number, file_path, file_name, file_size, change_notes, uploaded_by) VALUES (?,?,?,?,?,?,?)")
                ->execute([$docId, $newVersion, 'uploads/documents/' . $filename, $file['name'], $file['size'], $changeNotes, $uploadedBy]);

            // Update document
            $pdo->prepare("UPDATE documents SET current_version=?, file_path=?, file_name=?, file_size=?, file_extension=?, mime_type=? WHERE id=?")
                ->execute([$newVersion, 'uploads/documents/' . $filename, $file['name'], $file['size'], $ext, $file['type'], $docId]);

            doc_log($pdo, $docId, 'new_version', $newVersion);
            $pdo->commit();
            ok(['version' => $newVersion], 'New version uploaded (v' . $newVersion . ')');
        } catch (Exception $e) {
            $pdo->rollBack();
            @unlink($dest);
            err('Failed: ' . $e->getMessage());
        }
    }

    // Upload new document
    else {
        checkUpload($uploadDir);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $docType = $_POST['document_type'] ?? 'other';
        $tags = trim($_POST['tags'] ?? '');
        $uploadedBy = (int)($_POST['uploaded_by'] ?? $userId);

        if (!in_array($docType, ['financial','letter','invoice','contract','policy','report','other'], true)) $docType = 'other';
        $category = $_POST['category'] ?? 'general';
        if (!in_array($category, DOC_CATEGORIES, true)) $category = 'general';
        [$patientId, $visitId] = doc_patient_visit($pdo, $_POST['patient_id'] ?? 0, $_POST['visit_id'] ?? 0);
        if ($category !== 'general' && !$patientId) err('Clinical categories need a patient');
        $retentionYears = max(0, min(100, (int)($_POST['retention_years'] ?? 0)));
        $retentionUntil = $retentionYears ? date('Y-m-d', strtotime("+$retentionYears years")) : null;

        if (!$title) err('Title is required');

        $allowedTypes = ['pdf','doc','docx','xls','xlsx','csv','ppt','pptx','jpg','jpeg','png','gif','txt','rtf','odt','ods'];
        $file = $_FILES['file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedTypes)) err('File type not allowed. Allowed: ' . implode(', ', $allowedTypes));

        $maxSize = 20 * 1024 * 1024; // 20MB
        if ($file['size'] > $maxSize) err('File too large. Maximum 20MB.');

        $filename = 'doc_' . time() . '_' . uniqid() . '.' . $ext;
        $dest = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) err('Failed to save file');

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO documents (title, description, document_type, folder_id, file_path, file_name, file_size, file_extension, mime_type, tags, uploaded_by, patient_id, visit_id, category, retention_until) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$title, $description, $docType, !empty($_POST['folder_id']) ? (int)$_POST['folder_id'] : null, 'uploads/documents/' . $filename, $file['name'], $file['size'], $ext, $file['type'], $tags, $uploadedBy, $patientId, $visitId, $category, $retentionUntil]);
            $docId = (int)$pdo->lastInsertId();

            // Create initial version record
            $pdo->prepare("INSERT INTO document_versions (document_id, version_number, file_path, file_name, file_size, change_notes, uploaded_by) VALUES (?,1,?,?,?,?,?)")
                ->execute([$docId, 'uploads/documents/' . $filename, $file['name'], $file['size'], 'Initial upload', $uploadedBy]);

            doc_log($pdo, $docId, 'upload', 1);
            $pdo->commit();
            ok(['id' => $docId], 'Document uploaded successfully');
        } catch (Exception $e) {
            $pdo->rollBack();
            @unlink($dest);
            err('Failed: ' . $e->getMessage());
        }
    }
}

// ===== PUT — Update document metadata =====
if ($method === 'PUT') {
    if (!$id) err('Document ID required');
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $doc = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
    $doc->execute([$id]);
    if (!$doc->fetch()) err('Document not found', 404);

    $fields = [];
    $params = [];
    $allowed = ['title', 'description', 'document_type', 'tags', 'status'];
    foreach ($allowed as $f) {
        if (isset($input[$f])) { $fields[] = "$f = ?"; $params[] = $input[$f]; }
    }
    if (isset($input['category']) && in_array($input['category'], DOC_CATEGORIES, true)) { $fields[] = "category = ?"; $params[] = $input['category']; }
    if (empty($fields)) err('No fields to update');
    $params[] = $id;
    $pdo->prepare("UPDATE documents SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
    doc_log($pdo, $id, 'update');
    ok(null, 'Document updated');
}

// ===== DELETE =====
if ($method === 'DELETE') {
    if (!$id) err('Document ID required');
    $q = $pdo->prepare("SELECT retention_until FROM documents WHERE id = ? AND status <> 'deleted'");
    $q->execute([$id]);
    $d = $q->fetch();
    if (!$d) err('Document not found', 404);
    if ($d['retention_until'] && $d['retention_until'] > date('Y-m-d')) err('Under retention until ' . $d['retention_until'] . ': archive it instead.', 409);
    // Soft delete - set status to deleted
    $pdo->prepare("UPDATE documents SET status = 'deleted' WHERE id = ?")->execute([$id]);
    doc_log($pdo, $id, 'delete');
    ok(null, 'Document deleted');
}

err('Invalid request', 400);

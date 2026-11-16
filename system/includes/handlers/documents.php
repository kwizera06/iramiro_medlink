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

$uploadDir = __DIR__ . '/../uploads/documents/';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

// ===== GET =====
if ($method === 'GET') {

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
        $stmt = $pdo->prepare("SELECT d.*, CONCAT(u.first_name,' ',u.last_name) AS uploaded_by_name
            FROM documents d LEFT JOIN users u ON u.id = d.uploaded_by WHERE d.id = ?");
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
    if ($status) { $where[] = "d.status = ?"; $params[] = $status; }
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
        $where[] = "(d.title LIKE ? OR d.description LIKE ? OR d.tags LIKE ? OR d.file_name LIKE ?)";
        $params = array_merge($params, [$like, $like, $like, $like]);
    }
    $ws = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $cnt = $pdo->prepare("SELECT COUNT(*) FROM documents d $ws");
    $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();

    $stmt = $pdo->prepare("SELECT d.*, CONCAT(u.first_name,' ',u.last_name) AS uploaded_by_name
        FROM documents d LEFT JOIN users u ON u.id = d.uploaded_by
        $ws ORDER BY d.updated_at DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $docs = $stmt->fetchAll();

    // Stats
    $statsStmt = $pdo->query("SELECT document_type, COUNT(*) as cnt FROM documents WHERE status='active' GROUP BY document_type");
    $statsRaw = $statsStmt->fetchAll();
    $stats = ['total' => 0];
    foreach ($statsRaw as $s) { $stats[$s['document_type']] = (int)$s['cnt']; $stats['total'] += (int)$s['cnt']; }

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

    // Upload new version of existing document
    if ($action === 'new_version') {
        $docId = (int)($_POST['document_id'] ?? 0);
        $changeNotes = trim($_POST['change_notes'] ?? '');
        $uploadedBy = (int)($_POST['uploaded_by'] ?? $userId);

        if (!$docId) err('document_id required');
        $docStmt = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
        $docStmt->execute([$docId]);
        $doc = $docStmt->fetch();
        if (!$doc) err('Document not found', 404);

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            err('File upload failed');
        }

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
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $docType = $_POST['document_type'] ?? 'other';
        $tags = trim($_POST['tags'] ?? '');
        $uploadedBy = (int)($_POST['uploaded_by'] ?? $userId);

        if (!$title) err('Title is required');
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            err('File upload failed');
        }

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
            $stmt = $pdo->prepare("INSERT INTO documents (title, description, document_type, folder_id, file_path, file_name, file_size, file_extension, mime_type, tags, uploaded_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$title, $description, $docType, !empty($_POST['folder_id']) ? (int)$_POST['folder_id'] : null, 'uploads/documents/' . $filename, $file['name'], $file['size'], $ext, $file['type'], $tags, $uploadedBy]);
            $docId = (int)$pdo->lastInsertId();

            // Create initial version record
            $pdo->prepare("INSERT INTO document_versions (document_id, version_number, file_path, file_name, file_size, change_notes, uploaded_by) VALUES (?,1,?,?,?,?,?)")
                ->execute([$docId, 'uploads/documents/' . $filename, $file['name'], $file['size'], 'Initial upload', $uploadedBy]);

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
    if (empty($fields)) err('No fields to update');
    $params[] = $id;
    $pdo->prepare("UPDATE documents SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
    ok(null, 'Document updated');
}

// ===== DELETE =====
if ($method === 'DELETE') {
    if (!$id) err('Document ID required');
    // Soft delete - set status to deleted
    $pdo->prepare("UPDATE documents SET status = 'deleted' WHERE id = ?")->execute([$id]);
    ok(null, 'Document deleted');
}

err('Invalid request', 400);

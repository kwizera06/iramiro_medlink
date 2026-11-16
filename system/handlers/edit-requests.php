<?php
// handlers/edit-requests.php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

function jsonResp($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
function ok($d=null,$m='Success'){jsonResp(['success'=>true,'message'=>$m,'data'=>$d]);}
function err($m,$s=400){jsonResp(['success'=>false,'message'=>$m],$s);}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (session_status() === PHP_SESSION_NONE) session_start();
$userId = $_SESSION['user_id'] ?? 0;

// ===== GET =====
if ($method === 'GET') {

    // Check if entity has a pending/approved edit request
    if ($action === 'check') {
        $entityType = $_GET['entity_type'] ?? '';
        $entityId = (int)($_GET['entity_id'] ?? 0);
        if (!$entityType || !$entityId) err('entity_type and entity_id required');
        $stmt = $pdo->prepare("SELECT id, status FROM edit_requests WHERE entity_type=? AND entity_id=? AND status IN ('pending','approved') ORDER BY requested_at DESC LIMIT 1");
        $stmt->execute([$entityType, $entityId]);
        $existing = $stmt->fetch();
        ok($existing ?: null);
    }

    // List edit requests
    if (!$action || $action === 'list') {
        $status = $_GET['status'] ?? '';
        $entityType = $_GET['entity_type'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 25)));
        $offset = ($page - 1) * $limit;

        $where = [];
        $params = [];
        if ($status) { $where[] = "er.status = ?"; $params[] = $status; }
        if ($entityType) { $where[] = "er.entity_type = ?"; $params[] = $entityType; }
        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM edit_requests er $whereSQL");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $sql = "SELECT er.*, 
                    CONCAT(u1.first_name, ' ', u1.last_name) AS requested_by_name,
                    CONCAT(u2.first_name, ' ', u2.last_name) AS reviewed_by_name
                FROM edit_requests er
                LEFT JOIN users u1 ON u1.id = er.requested_by
                LEFT JOIN users u2 ON u2.id = er.reviewed_by
                $whereSQL
                ORDER BY er.requested_at DESC
                LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $requests = $stmt->fetchAll();

        $statsStmt = $pdo->query("SELECT status, COUNT(*) as cnt FROM edit_requests GROUP BY status");
        $statsRaw = $statsStmt->fetchAll();
        $stats = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
        foreach ($statsRaw as $s) { $stats[$s['status']] = (int)$s['cnt']; }

        ok([
            'requests' => $requests,
            'stats' => $stats,
            'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'total_pages' => ceil($total / $limit)]
        ]);
    }

    // Edit audit log
    if ($action === 'audit_log') {
        $entityType = $_GET['entity_type'] ?? '';
        $entityId = isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : 0;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 25)));
        $offset = ($page - 1) * $limit;

        $where = [];
        $params = [];
        if ($entityType) { $where[] = "el.entity_type = ?"; $params[] = $entityType; }
        if ($entityId) { $where[] = "el.entity_id = ?"; $params[] = $entityId; }
        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $pdo->prepare("SELECT el.*, CONCAT(u.first_name, ' ', u.last_name) AS edited_by_name
            FROM edit_audit_log el
            LEFT JOIN users u ON u.id = el.edited_by
            $whereSQL ORDER BY el.edited_at DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        ok($stmt->fetchAll());
    }
}

// ===== POST =====
if ($method === 'POST') {

    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    // Create edit request
    if (!$action || $action === 'create') {
        $entityType = $input['entity_type'] ?? '';
        $entityId = (int)($input['entity_id'] ?? 0);
        $entityName = $input['entity_name'] ?? '';
        $reason = trim($input['reason'] ?? '');
        $requestedBy = (int)$userId;

        if (!$entityType) err('entity_type is required');
        if (!$entityId) err('entity_id is required');
        if (!$reason || strlen($reason) < 10) err('Detailed reason required (at least 10 characters)');

        // Check for existing pending/approved request
        $existing = $pdo->prepare("SELECT id FROM edit_requests WHERE entity_type=? AND entity_id=? AND status IN ('pending','approved')");
        $existing->execute([$entityType, $entityId]);
        if ($existing->fetch()) err('An edit request already exists for this record');

        $stmt = $pdo->prepare("INSERT INTO edit_requests (entity_type, entity_id, entity_name, reason, requested_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$entityType, $entityId, $entityName, $reason, $requestedBy]);
        ok(['id' => (int)$pdo->lastInsertId()], 'Edit request submitted');
    }

    // Approve or reject
    if ($action === 'review') {
        $reqId = (int)($input['id'] ?? 0);
        $status = $input['status'] ?? '';
        $reviewNotes = $input['review_notes'] ?? '';
        $reviewedBy = (int)$userId;

        if (!isUserAdmin($pdo)) err('Only administrators can approve or reject edit requests.', 403);
        if (!$reqId) err('Request ID required');
        if (!in_array($status, ['approved', 'rejected'])) err('Status must be approved or rejected');

        $stmt = $pdo->prepare("SELECT * FROM edit_requests WHERE id = ?");
        $stmt->execute([$reqId]);
        $req = $stmt->fetch();
        if (!$req) err('Request not found', 404);
        if ($req['status'] !== 'pending') err('Can only review pending requests');
        if ((int)$req['requested_by'] === $reviewedBy) err('You cannot approve or reject your own request.', 403);

        $pdo->prepare("UPDATE edit_requests SET status=?, reviewed_by=?, reviewed_at=NOW(), review_notes=? WHERE id=?")
            ->execute([$status, $reviewedBy, $reviewNotes, $reqId]);
        ok(null, 'Request ' . $status);
    }
}

err('Invalid request', 400);

<?php
// handlers/settings/roles.php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

function jsonResponse($data, $status = 200) { http_response_code($status); echo json_encode($data); exit(); }
function success($data = null, $msg = 'Success', $status = 200) { jsonResponse(['success'=>true,'message'=>$msg,'data'=>$data], $status); }
function error($msg, $status = 400) { jsonResponse(['success'=>false,'message'=>$msg], $status); }

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Only administrators may change roles and their permissions.
if ($method !== 'GET' && !isUserAdmin($pdo)) {
    error('You do not have permission to manage roles.', 403);
}

try {

// ===== GET =====
if ($method === 'GET') {
    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
        $stmt->execute([$id]);
        $role = $stmt->fetch();
        if (!$role) error('Role not found', 404);
        success($role);
    }
    
    $search = trim($_GET['search'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $where = []; $params = [];
    if ($search !== '') {
        $like = "%$search%";
        $where[] = "(name LIKE ? OR slug LIKE ? OR description LIKE ?)";
        $params = array_merge($params, [$like,$like,$like]);
    }
    $ws = $where ? 'WHERE '.implode(' AND ',$where) : '';

    $cnt = $pdo->prepare("SELECT COUNT(*) FROM roles $ws");
    $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT r.*, 
            (SELECT COUNT(*) FROM user_roles ur WHERE ur.role_id = r.id) AS user_count
        FROM roles r
        $ws
        GROUP BY r.id
        ORDER BY r.created_at DESC 
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $roles = $stmt->fetchAll();

    success([
        'roles' => $roles,
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => (int)ceil($total/max($limit,1))
        ]
    ]);
}

// ===== POST =====
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    
    foreach (['name','slug'] as $f) {
        if (empty($input[$f])) error("Field '$f' is required", 422);
    }
    
    // Check for duplicate name or slug
    $chk = $pdo->prepare("SELECT id FROM roles WHERE name = ? OR slug = ?");
    $chk->execute([$input['name'], $input['slug']]);
    if ($chk->fetch()) error('Role name or slug already exists', 409);

    $stmt = $pdo->prepare("
        INSERT INTO roles (name, slug, description, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([
        trim($input['name']),
        trim($input['slug']),
        trim($input['description'] ?? '')
    ]);
    
    $newId = (int)$pdo->lastInsertId();
    success(['id' => $newId], 'Role created successfully', 201);
}

// ===== PUT =====
if ($method === 'PUT') {
    if (!$id) error('Role ID required', 422);
    
    $chk = $pdo->prepare("SELECT id, slug FROM roles WHERE id = ?");
    $chk->execute([$id]);
    $existingRole = $chk->fetch();
    if (!$existingRole) error('Role not found', 404);

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    
    foreach (['name','slug'] as $f) {
        if (empty($input[$f])) error("Field '$f' is required", 422);
    }
    
    // Check for duplicate name or slug (excluding current role)
    $dupChk = $pdo->prepare("SELECT id FROM roles WHERE (name = ? OR slug = ?) AND id != ?");
    $dupChk->execute([$input['name'], $input['slug'], $id]);
    if ($dupChk->fetch()) error('Role name or slug used by another role', 409);

    $oldSlug = $existingRole['slug'];
    $newSlug = trim($input['slug']);

    $stmt = $pdo->prepare("
        UPDATE roles SET name = ?, slug = ?, description = ? WHERE id = ?
    ");
    $stmt->execute([
        trim($input['name']),
        $newSlug,
        trim($input['description'] ?? ''),
        $id
    ]);
    
    // If slug changed, update the legacy users.role column for all users with this role
    if ($oldSlug !== $newSlug) {
        $syncStmt = $pdo->prepare("
            UPDATE users u
            JOIN user_roles ur ON ur.user_id = u.id
            SET u.role = ?
            WHERE ur.role_id = ? AND u.role = ?
        ");
        $syncStmt->execute([$newSlug, $id, $oldSlug]);
    }
    
    success(null, 'Role updated successfully');
}

// ===== DELETE =====
if ($method === 'DELETE') {
    if (!$id) error('Role ID required', 422);
    
    $chk = $pdo->prepare("SELECT id FROM roles WHERE id = ?");
    $chk->execute([$id]);
    if (!$chk->fetch()) error('Role not found', 404);
    
    // Check if role is in use
    $userChk = $pdo->prepare("SELECT COUNT(*) FROM user_roles WHERE role_id = ?");
    $userChk->execute([$id]);
    if ((int)$userChk->fetchColumn() > 0) {
        error('Cannot delete role: it is assigned to users', 409);
    }
    
    $pdo->prepare("DELETE FROM roles WHERE id = ?")->execute([$id]);
    success(null, 'Role deleted successfully');
}

error('Method not allowed', 405);

} catch (PDOException $e) {
    error('Database error: '.$e->getMessage(), 500);
} catch (Exception $e) {
    error('Server error: '.$e->getMessage(), 500);
}
?>

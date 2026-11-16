<?php
// handlers/settings/users.php
// User management with multiple roles support
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

function jsonResponse($data, $status = 200) { http_response_code($status); echo json_encode($data); exit(); }
function success($data = null, $msg = 'Success', $status = 200) { jsonResponse(['success'=>true,'message'=>$msg,'data'=>$data], $status); }
function error($msg, $status = 400) { jsonResponse(['success'=>false,'message'=>$msg], $status); }

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// User administration is limited to administrators (or someone explicitly
// granted the Settings/Users page in Roles & Access).
// Self-service (own profile read, change-password, upload-avatar) is always allowed.
if (session_status() === PHP_SESSION_NONE) @session_start();
$isSelfRead = $method === 'GET' && $id && $id === (int)($_SESSION['user_id'] ?? 0);
$isSelfAction = $method === 'POST' && in_array($_GET['action'] ?? '', ['change-password', 'upload-avatar'], true);
if (!$isSelfRead && !$isSelfAction && !isUserAdmin($pdo) && !canUsePage($pdo, 'settings.php', 'users.php')) {
    error('You do not have permission to manage users.', 403);
}

/**
 * Get role IDs and names for a user
 */
function getUserRoles($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT r.id, r.name, r.slug 
        FROM user_roles ur 
        JOIN roles r ON r.id = ur.role_id 
        WHERE ur.user_id = ?
        ORDER BY r.name
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Sync roles for a user (replace all with new set)
 */
function syncUserRoles($pdo, $userId, $roleIds, $assignedBy = null) {
    // Delete existing
    $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?")->execute([$userId]);
    
    // Insert new
    if (!empty($roleIds)) {
        $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id, assigned_by) VALUES (?, ?, ?)");
        foreach ($roleIds as $roleId) {
            $roleId = (int)$roleId;
            if ($roleId > 0) {
                $stmt->execute([$userId, $roleId, $assignedBy]);
            }
        }
    }
    
    // Also update the legacy role_id field (use first role for backward compatibility)
    $primaryRoleId = !empty($roleIds) ? (int)$roleIds[0] : null;
    $pdo->prepare("UPDATE users SET role_id = ? WHERE id = ?")->execute([$primaryRoleId, $userId]);
}

try {

// ===== GET =====
if ($method === 'GET') {
    if ($id) {
        $stmt = $pdo->prepare("
            SELECT u.id, u.employee_id, u.first_name, u.last_name, u.email, u.username,
                   u.role, u.role_id, u.department_id, u.status, u.is_active, u.last_login, u.created_at,
                   e.employee_no, d.name AS department_name
            FROM users u
            LEFT JOIN employees e ON e.employee_no = u.employee_id
            LEFT JOIN departments d ON d.id = u.department_id
            WHERE u.id = ?
        ");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) error('User not found', 404);
        
        // Get assigned roles
        $user['roles'] = getUserRoles($pdo, $id);
        $user['role_ids'] = array_map(function($r) { return $r['id']; }, $user['roles']);
        $user['role_name'] = implode(', ', array_map(function($r) { return $r['name']; }, $user['roles']));
        
        success($user);
    }

    $search   = trim($_GET['search'] ?? '');
    $roleId   = isset($_GET['role_id']) ? (int)$_GET['role_id'] : 0;
    $status   = $_GET['status'] ?? '';
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $limit    = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $offset   = ($page - 1) * $limit;

    $where = []; $params = [];
    if ($search !== '') {
        $like = "%$search%";
        $where[] = "(u.username LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.employee_id LIKE ?)";
        $params = array_merge($params, [$like, $like, $like, $like, $like]);
    }
    if ($roleId > 0) { 
        $where[] = "u.id IN (SELECT user_id FROM user_roles WHERE role_id = ?)"; 
        $params[] = $roleId; 
    }
    if ($status !== '') { $where[] = "u.status = ?"; $params[] = $status; }
    if (!empty($_GET['department_id'])) { $where[] = "u.department_id = ?"; $params[] = (int)$_GET['department_id']; }
    $ws = $where ?'WHERE ' . implode(' AND ', $where) : '';

    $cnt = $pdo->prepare("SELECT COUNT(*) FROM users u $ws");
    $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT u.id, u.employee_id, u.first_name, u.last_name, u.email, u.username,
               u.role, u.role_id, u.department_id, u.status, u.is_active, u.last_login, u.created_at,
               d.name AS department_name
        FROM users u
        LEFT JOIN departments d ON d.id = u.department_id
        $ws ORDER BY u.created_at DESC LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    // Attach roles for each user
    foreach ($users as &$user) {
        $user['employee_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: null;
        $roles = getUserRoles($pdo, $user['id']);
        $user['role_names'] = implode(', ', array_map(function($r) { return $r['name']; }, $roles));
        $user['role_name'] = $user['role_names'] ?: '—';
        $user['employee_no'] = $user['employee_id'];
    }

    $stats = $pdo->query("
        SELECT COUNT(*) AS total,
               SUM(CASE WHEN is_active = 1 AND (status IS NULL OR status != 'inactive') THEN 1 ELSE 0 END) AS active,
               SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive,
               SUM(CASE WHEN is_blocked = 1 THEN 1 ELSE 0 END) AS suspended
        FROM users
    ")->fetch();

    success([
        'users' => $users,
        'stats' => $stats,
        'pagination' => [
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => (int)ceil($total / max($limit, 1))
        ]
    ]);
}

// ===== CHANGE PASSWORD =====
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'change-password') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) error('Not authenticated', 401);

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($input['current_password'])) error('Current password is required', 422);
    if (empty($input['new_password'])) error('New password is required', 422);
    if (strlen($input['new_password']) < 6) error('New password must be at least 6 characters', 422);

    // Verify current password
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) error('User not found', 404);

    if (!password_verify($input['current_password'], $user['password_hash'])) {
        error('Current password is incorrect', 403);
    }

    // Update password
    $newHash = password_hash($input['new_password'], PASSWORD_DEFAULT);
    $update = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $update->execute([$newHash, $userId]);

    success(null, 'Password changed successfully');
}

// ===== UPLOAD OWN PROFILE PICTURE =====
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'upload-avatar') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) error('Not authenticated', 401);

    $f = $_FILES['avatar'] ?? null;
    if (!$f) error('No image received (server post_max_size is ' . ini_get('post_max_size') . ')', 422);
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) error('Image too large for server (upload_max_filesize is ' . ini_get('upload_max_filesize') . ')', 422);
    if ($f['error'] !== UPLOAD_ERR_OK) error('Upload error code ' . $f['error'] . ' (check server tmp dir)', 422);
    if ($f['size'] > 3 * 1024 * 1024) error('Image too large (max 3MB)', 422);
    // Type from getimagesize (core PHP, no fileinfo ext needed); extension never taken from client filename
    $exts = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    $info = @getimagesize($f['tmp_name']);
    if (!$info || !isset($exts[$info[2]])) error('Only JPG, PNG, WEBP or GIF images allowed', 422);

    $dir = __DIR__ . '/../../uploads/avatars/';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) error('Cannot create folder medlink/uploads/avatars — create it on the server and make it writable by the web server', 500);
    if (!is_writable($dir)) error('Folder medlink/uploads/avatars is not writable by the web server', 500);
    $name = 'user_' . (int)$userId . '_' . uniqid() . '.' . $exts[$info[2]];
    if (!move_uploaded_file($f['tmp_name'], $dir . $name)) error('Failed to save image', 500);
    $path = 'uploads/avatars/' . $name;

    $old = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $old->execute([$userId]);
    $oldPath = $old->fetchColumn();
    if ($oldPath && strpos($oldPath, 'uploads/avatars/') === 0) @unlink(__DIR__ . '/../../' . $oldPath);

    $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?")->execute([$path, $userId]);
    $_SESSION['user_data']['profile_picture'] = $path;
    success(['profile_picture' => $path], 'Profile picture updated');
}

// ===== POST (Create) =====
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    // Required fields
    foreach (['first_name', 'last_name', 'email', 'password'] as $f) {
        if (empty($input[$f])) error("Field '$f' is required", 422);
    }

    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) error('Invalid email', 422);

    // Validate role_ids (must have at least one)
    $roleIds = $input['role_ids'] ?? [];
    if (empty($roleIds)) {
        error('At least one role must be assigned', 422);
    }
    
    // Verify all role IDs exist
    $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
    $chkRoles = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE id IN ($placeholders)");
    $chkRoles->execute($roleIds);
    if ((int)$chkRoles->fetchColumn() !== count($roleIds)) {
        error('One or more invalid role IDs', 422);
    }

    // employee_id is the employee_no (varchar) — optional but unique if provided
    $employeeId = !empty($input['employee_id']) ? trim($input['employee_id']) : null;

    // If employee_id provided, check it exists in employees table and get department
    $departmentId = !empty($input['department_id']) ? (int)$input['department_id'] : null;
    if (!empty($employeeId)) {
        $empStmt = $pdo->prepare("SELECT id, department_id FROM employees WHERE employee_no = ?");
        $empStmt->execute([$employeeId]);
        $emp = $empStmt->fetch();
        if (!$emp) error('Employee with ID "' . $employeeId . '" not found', 422);
        if (!$departmentId && $emp['department_id']) {
            $departmentId = (int)$emp['department_id'];
        }
        // Check employee_id not already taken in users
        $dupEmp = $pdo->prepare("SELECT id FROM users WHERE employee_id = ?");
        $dupEmp->execute([$employeeId]);
        if ($dupEmp->fetch()) error('This employee already has a user account', 409);
    }

    // Check duplicate email
    $chk = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $chk->execute([trim($input['email'])]);
    if ($chk->fetch()) error('Email already in use', 409);

    // Check duplicate username if provided
    $username = !empty($input['username']) ? trim($input['username']) : null;
    if ($username) {
        $chkUser = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $chkUser->execute([$username]);
        if ($chkUser->fetch()) error('Username already taken', 409);
    }

    // Get primary role slug for legacy 'role' column
    $primaryRoleStmt = $pdo->prepare("SELECT slug FROM roles WHERE id = ?");
    $primaryRoleStmt->execute([(int)$roleIds[0]]);
    $primaryRoleSlug = $primaryRoleStmt->fetchColumn() ?: 'doctor';

    $pdo->beginTransaction();
    $stmt = $pdo->prepare("
        INSERT INTO users
            (employee_id, first_name, last_name, email, username, password_hash, role, role_id, department_id, status, is_active, is_verified, created_at)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, NOW())
    ");
    $stmt->execute([
        $employeeId,
        trim($input['first_name']),
        trim($input['last_name']),
        trim($input['email']),
        $username,
        password_hash($input['password'], PASSWORD_BCRYPT),
        $primaryRoleSlug,
        (int)$roleIds[0],
        $departmentId,
        $input['status'] ?? 'active'
    ]);

    $newId = (int)$pdo->lastInsertId();
    
    // Assign roles via pivot table
    $assignedBy = $_SESSION['user_id'] ?? null;
    $roleStmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id, assigned_by) VALUES (?, ?, ?)");
    foreach ($roleIds as $rId) {
        $roleStmt->execute([$newId, (int)$rId, $assignedBy]);
    }
    
    $pdo->commit();

    success(['id' => $newId], 'User created successfully', 201);
}

// ===== PUT (Update) =====
if ($method === 'PUT') {
    if (!$id) error('User ID required', 422);

    $chk = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $chk->execute([$id]);
    if (!$chk->fetch()) error('User not found', 404);

    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    foreach (['first_name', 'last_name', 'email'] as $f) {
        if (empty($input[$f])) error("Field '$f' is required", 422);
    }

    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) error('Invalid email', 422);

    // Validate role_ids (must have at least one)
    $roleIds = $input['role_ids'] ?? [];
    if (empty($roleIds)) {
        error('At least one role must be assigned', 422);
    }
    
    // Verify all role IDs exist
    $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
    $chkRoles = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE id IN ($placeholders)");
    $chkRoles->execute($roleIds);
    if ((int)$chkRoles->fetchColumn() !== count($roleIds)) {
        error('One or more invalid role IDs', 422);
    }

    // Check duplicate email (excluding current user)
    $dupEmail = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $dupEmail->execute([trim($input['email']), $id]);
    if ($dupEmail->fetch()) error('Email used by another user', 409);

    $username = !empty($input['username']) ? trim($input['username']) : null;
    if ($username) {
        $dupUser = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $dupUser->execute([$username, $id]);
        if ($dupUser->fetch()) error('Username used by another user', 409);
    }

    $employeeId   = !empty($input['employee_id']) ? trim($input['employee_id']) : null;
    $departmentId = !empty($input['department_id']) ? (int)$input['department_id'] : null;

    // Validate employee_id if changed
    if ($employeeId) {
        $dupEmp = $pdo->prepare("SELECT id FROM users WHERE employee_id = ? AND id != ?");
        $dupEmp->execute([$employeeId, $id]);
        if ($dupEmp->fetch()) error('This employee already has a user account', 409);
    }

    // Get primary role slug for legacy column
    $primaryRoleStmt = $pdo->prepare("SELECT slug FROM roles WHERE id = ?");
    $primaryRoleStmt->execute([(int)$roleIds[0]]);
    $primaryRoleSlug = $primaryRoleStmt->fetchColumn() ?: 'doctor';

    $pdo->beginTransaction();

    $updateFields = [
        'employee_id = ?',
        'first_name = ?',
        'last_name = ?',
        'email = ?',
        'username = ?',
        'role = ?',
        'role_id = ?',
        'department_id = ?',
        'status = ?'
    ];
    $params = [
        $employeeId,
        trim($input['first_name']),
        trim($input['last_name']),
        trim($input['email']),
        $username,
        $primaryRoleSlug,
        (int)$roleIds[0],
        $departmentId,
        $input['status'] ?? 'active'
    ];

    if (!empty($input['password'])) {
        $updateFields[] = 'password_hash = ?';
        $params[] = password_hash($input['password'], PASSWORD_BCRYPT);
    }

    $params[] = $id;

    $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?");
    $stmt->execute($params);

    // Sync roles
    $assignedBy = $_SESSION['user_id'] ?? null;
    $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?")->execute([$id]);
    $roleStmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id, assigned_by) VALUES (?, ?, ?)");
    foreach ($roleIds as $rId) {
        $roleStmt->execute([$id, (int)$rId, $assignedBy]);
    }
    
    $pdo->commit();

    success(null, 'User updated successfully');
}

// ===== DELETE =====
if ($method === 'DELETE') {
    if (!$id) error('User ID required', 422);

    $chk = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $chk->execute([$id]);
    if (!$chk->fetch()) error('User not found', 404);

    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
    success(null, 'User deleted successfully');
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

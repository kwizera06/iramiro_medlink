<?php
// handlers/settings/rbac.php
// Role-Based Access Control for Department Portal
require_once __DIR__ . '/../../includes/database.php';

function jsonResponse($data, $status = 200) { http_response_code($status); echo json_encode($data); exit(); }
function success($data = null, $msg = 'Success', $status = 200) { jsonResponse(['success'=>true,'message'=>$msg,'data'=>$data], $status); }
function error($msg, $status = 400) { jsonResponse(['success'=>false,'message'=>$msg], $status); }

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Current user from session
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    error('Unauthorized', 401);
}

try {

// ============================================================
// ROLE DEFINITIONS & PERMISSIONS
// ============================================================

$roleDefinitions = [
    'admin' => [
        'permissions' => [
            'portal.view_all_departments',
            'portal.view_dashboard',
            'portal.manage_queue',
            'portal.view_reports',
            'portal.manage_forms',
            'portal.export_data'
        ],
        'description' => 'Full access to all department portals'
    ],
    'department_head' => [
        'permissions' => [
            'portal.view_own_department',
            'portal.view_dashboard',
            'portal.manage_queue',
            'portal.view_reports',
            'portal.manage_forms'
        ],
        'description' => 'Manage own department portal'
    ],
    'doctor' => [
        'permissions' => [
            'portal.view_own_department',
            'portal.view_queue',
            'portal.manage_consultation',
            'portal.view_forms'
        ],
        'description' => 'Consultation and queue management'
    ],
    'nurse' => [
        'permissions' => [
            'portal.view_own_department',
            'portal.view_queue',
            'portal.manage_queue_status'
        ],
        'description' => 'Queue management only'
    ],
    'receptionist' => [
        'permissions' => [
            'portal.view_own_department',
            'portal.manage_queue'
        ],
        'description' => 'Patient queue management'
    ]
];

// GET /rbac?action=check_permission&permission=X&department_id=Y
if ($method === 'GET' && $action === 'check_permission') {
    $permission = $_GET['permission'] ?? '';
    $departmentId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : null;
    
    if (!$permission) error('permission parameter required', 422);
    
    // Get user's role
    $stmt = $pdo->prepare("SELECT role, department_id FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) error('User not found', 404);
    
    $userRole = $user['role'];
    $userDeptId = $user['department_id'];
    
    // Check if role has permission
    $hasPermission = false;
    if (isset($roleDefinitions[$userRole]['permissions'])) {
        $hasPermission = in_array($permission, $roleDefinitions[$userRole]['permissions']);
    }
    
    // For department-specific permissions, check department access
    if ($hasPermission && strpos($permission, 'own_department') !== false) {
        if ($departmentId && $userDeptId != $departmentId && $userRole !== 'admin') {
            $hasPermission = false;
        }
    }
    
    success([
        'has_permission' => $hasPermission,
        'user_role' => $userRole,
        'user_department_id' => $userDeptId,
        'requested_permission' => $permission,
        'requested_department_id' => $departmentId
    ]);
}

// GET /rbac?action=get_role&user_id=X
if ($method === 'GET' && $action === 'get_role') {
    $targetUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $userId;
    
    // Non-admins can only get their own role
    if ($targetUserId != $userId) {
        $userStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $userRole = $userStmt->fetch()['role'];
        
        if ($userRole !== 'admin') {
            error('Access denied', 403);
        }
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            u.id, u.role, u.department_id, d.name as department_name,
            u.first_name, u.last_name, u.email, u.status
        FROM users u
        LEFT JOIN departments d ON d.id = u.department_id
        WHERE u.id = ?
    ");
    $stmt->execute([$targetUserId]);
    $userInfo = $stmt->fetch();
    
    if (!$userInfo) error('User not found', 404);
    
    $roleInfo = $roleDefinitions[$userInfo['role']] ?? [];
    
    success([
        'user' => $userInfo,
        'role_info' => $roleInfo
    ]);
}

// GET /rbac?action=get_accessible_departments
if ($method === 'GET' && $action === 'get_accessible_departments') {
    $stmt = $pdo->prepare("SELECT role, department_id FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) error('User not found', 404);
    
    // Admins see all departments
    if ($user['role'] === 'admin') {
        $dStmt = $pdo->prepare("
            SELECT id, name, head_id, status FROM departments WHERE status = 'active'
        ");
        $dStmt->execute();
    } else {
        // Non-admins see only their assigned department
        $dStmt = $pdo->prepare("
            SELECT id, name, head_id, status FROM departments WHERE id = ? AND status = 'active'
        ");
        $dStmt->execute([$user['department_id']]);
    }
    
    $departments = $dStmt->fetchAll();
    
    success([
        'accessible_departments' => $departments,
        'user_role' => $user['role']
    ]);
}

// GET /rbac?action=role_definitions
if ($method === 'GET' && $action === 'role_definitions') {
    // Only admins can view all role definitions
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if ($user['role'] !== 'admin') {
        error('Access denied: Admin only', 403);
    }
    
    success($roleDefinitions);
}

// ============================================================
// ROLE MANAGEMENT (Admin only)
// ============================================================

// POST /rbac?action=assign_role
if ($method === 'POST' && $action === 'assign_role') {
    // Admin only
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $admin = $stmt->fetch();
    
    if ($admin['role'] !== 'admin') {
        error('Access denied: Admin only', 403);
    }
    
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $targetUserId = $data['user_id'] ?? null;
    $newRole = $data['role'] ?? null;
    $departmentId = $data['department_id'] ?? null;
    
    if (!$targetUserId || !$newRole) {
        error('user_id and role required', 422);
    }
    
    // Validate role
    if (!isset($roleDefinitions[$newRole])) {
        error('Invalid role', 422);
    }
    
    $updateStmt = $pdo->prepare("
        UPDATE users 
        SET role = ?, department_id = ?
        WHERE id = ?
    ");
    $updateStmt->execute([$newRole, $departmentId, $targetUserId]);
    
    success([
        'user_id' => $targetUserId,
        'role' => $newRole,
        'department_id' => $departmentId
    ], 'Role assigned successfully');
}

// ============================================================
// AUDIT LOGGING
// ============================================================

// GET /rbac?action=get_access_log&user_id=X&limit=Y
if ($method === 'GET' && $action === 'get_access_log') {
    $targetUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $userId;
    $limit = (int)($_GET['limit'] ?? 100);
    
    // Non-admins can only view their own log
    if ($targetUserId != $userId) {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        if ($stmt->fetch()['role'] !== 'admin') {
            error('Access denied', 403);
        }
    }
    
    $stmt = $pdo->prepare("
        SELECT id, user_id, action, resource, department_id, granted, timestamp
        FROM rbac_audit_log
        WHERE user_id = ?
        ORDER BY timestamp DESC
        LIMIT ?
    ");
    $stmt->execute([$targetUserId, $limit]);
    $logs = $stmt->fetchAll();
    
    success($logs);
}

// ============================================================
// LOG ACCESS ATTEMPT
// ============================================================

function logAccessAttempt($action, $resource, $departmentId, $granted) {
    global $pdo, $userId;
    
    $stmt = $pdo->prepare("
        INSERT INTO rbac_audit_log (user_id, action, resource, department_id, granted, timestamp)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$userId, $action, $resource, $departmentId, $granted ? 1 : 0]);
}

// ============================================================
// ERROR HANDLING
// ============================================================

success(null, 'Action not recognized');

} catch (Exception $e) {
    error('Error: ' . $e->getMessage(), 500);
}
?>

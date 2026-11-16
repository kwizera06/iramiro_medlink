<?php
// handlers/settings/user-permissions.php
// Role / per-user permission matrix used by Settings > Roles.
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

function jsonResponse($data, $status = 200) { http_response_code($status); echo json_encode($data); exit(); }
function success($data = null, $message = 'Success', $status = 200) {
    jsonResponse(['success' => true, 'message' => $message, 'data' => $data], $status);
}
function error($message, $status = 400) {
    jsonResponse(['success' => false, 'message' => $message], $status);
}

if (!isUserAdmin($pdo)) error('Only administrators can view user permissions.', 403);

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') error('Method not allowed', 405);

try {
    $stmt = $pdo->query("
        SELECT r.slug AS role, rp.permission
        FROM role_permissions rp
        JOIN roles r ON r.id = rp.role_id
        ORDER BY r.slug, rp.permission
    ");
    $grouped = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $grouped[$row['role']][] = $row['permission'];
    }

    $overrides = $pdo->query("
        SELECT up.user_id, up.permission, up.scope,
               CONCAT(u.first_name, ' ', u.last_name) AS user_name
        FROM user_permissions up
        JOIN users u ON u.id = up.user_id
        WHERE up.revoked_at IS NULL
        ORDER BY u.first_name, u.last_name, up.permission
    ")->fetchAll(PDO::FETCH_ASSOC);

    success([
        'permissions' => $grouped,
        'user_permissions' => $overrides,
        'available_roles' => array_keys($grouped),
    ], 'User permissions loaded');
} catch (PDOException $e) {
    error('Could not load permissions: ' . $e->getMessage(), 500);
}

<?php
// handlers/settings/users/user-permissions.php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/response.php';
require_once __DIR__ . '/../../../includes/auth.php';

// Only admin can access
$user = requireRole(['admin']);

$db = getDB();

// Get all roles with their permissions
$stmt = $db->prepare("SELECT * FROM roles_permissions ORDER BY role, permission");
$stmt->execute();
$permissions = $stmt->fetchAll();

// Group by role
$groupedPermissions = [];
foreach ($permissions as $perm) {
    if (!isset($groupedPermissions[$perm['role']])) {
        $groupedPermissions[$perm['role']] = [];
    }
    $groupedPermissions[$perm['role']][] = $perm['permission'];
}

successResponse([
    'permissions' => $groupedPermissions,
    'available_roles' => ['admin', 'doctor', 'nurse', 'receptionist', 'pharmacist', 'lab_technician', 'accountant', 'hr_manager', 'department_head']
]);
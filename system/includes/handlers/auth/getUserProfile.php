<?php
// handlers/auth/getUserProfile.php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';

$user = requireAuth();

// Get complete user profile with additional info
$db = getDB();
$stmt = $db->prepare("
    SELECT 
        u.*,
        d.name as department_name,
        d.code as department_code
    FROM users u
    LEFT JOIN departments d ON d.id = u.department_id
    WHERE u.id = ?
");
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

// Remove sensitive data
unset($profile['password_hash']);

successResponse($profile, 'Profile retrieved successfully');
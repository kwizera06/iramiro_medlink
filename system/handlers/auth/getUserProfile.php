<?php
// handlers/auth/getUserProfile.php
require_once __DIR__ . '/../../includes/database.php';

function jsonResponse($data, $status = 200) { http_response_code($status); echo json_encode($data); exit(); }
function successResponse($data = null, $message = 'Success', $status = 200) {
    jsonResponse(['success' => true, 'message' => $message, 'data' => $data], $status);
}
function errorResponse($message, $status = 400) {
    jsonResponse(['success' => false, 'message' => $message], $status);
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if (!$userId) errorResponse('Not authenticated', 401);

$stmt = $pdo->prepare("
    SELECT u.*, d.name AS department_name, d.code AS department_code
    FROM users u
    LEFT JOIN departments d ON d.id = u.department_id
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$profile) errorResponse('User not found', 404);

unset($profile['password_hash']);

successResponse($profile, 'Profile retrieved successfully');

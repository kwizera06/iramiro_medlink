<?php
// handlers/auth/checkAuth.php
// Returns the signed-in session user. Uses the application's session + PDO
// (the old token-based helpers this file referenced do not exist in MedLink).
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
    SELECT id, employee_id, first_name, last_name, email, role, department_id,
           is_active, is_verified, is_blocked, last_login
    FROM users WHERE id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) errorResponse('Not authenticated', 401);

unset($user['password_hash']);

successResponse([
    'authenticated' => true,
    'user' => $user
], 'Authenticated');

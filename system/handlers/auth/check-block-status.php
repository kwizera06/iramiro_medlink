<?php
// handlers/auth/check-block-status.php
// Public endpoint: tells the login screen whether the account (if any) is blocked.
require_once __DIR__ . '/../../includes/database.php';

function jsonResponse($data, $status = 200) { http_response_code($status); echo json_encode($data); exit(); }
function successResponse($data = null, $message = 'Success', $status = 200) {
    jsonResponse(['success' => true, 'message' => $message, 'data' => $data], $status);
}

$email = trim((string)($_GET['email'] ?? ($_SESSION['user_data']['email'] ?? '')));
$userId = (int)($_SESSION['user_id'] ?? 0);

if ($email === '' && $userId) {
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $email = (string)$stmt->fetchColumn();
}

$user = null;
if ($email !== '') {
    $stmt = $pdo->prepare("SELECT id, email, is_blocked, block_reason FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

successResponse([
    'is_blocked' => (bool)($user['is_blocked'] ?? false),
    'block_reason' => $user['block_reason'] ?? null,
    'user' => $user ? ['id' => (int)$user['id'], 'email' => $user['email']] : null,
], 'Block status');

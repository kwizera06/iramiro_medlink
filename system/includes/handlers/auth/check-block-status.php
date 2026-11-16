<?php
// handlers/auth/check-block-status.php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';

$user = getCurrentUser();

if (!$user) {
    successResponse([
        'is_blocked' => false,
        'message' => 'Not logged in'
    ]);
}

successResponse([
    'is_blocked' => (bool)$user['is_blocked'],
    'block_reason' => $user['block_reason'] ?? null,
    'user' => [
        'id' => $user['id'],
        'email' => $user['email']
    ]
]);
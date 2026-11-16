<?php
// handlers/auth/checkAuth.php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';

$user = requireAuth();

// Remove sensitive data
unset($user['password_hash']);

successResponse([
    'authenticated' => true,
    'user' => $user
], 'Authenticated');
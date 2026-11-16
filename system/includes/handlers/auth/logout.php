<?php
// handlers/auth/logout.php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';

// Get token from header
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (empty($authHeader) || strpos($authHeader, 'Bearer ') !== 0) {
    errorResponse('No valid token provided', 400);
}

$token = substr($authHeader, 7);

// Logout user
logoutUser($token);

successResponse(null, 'Logged out successfully');
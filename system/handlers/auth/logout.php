<?php
// handlers/auth/logout.php
// Destroys the PHP session used by both the UI and the API.
function jsonResponse($data, $status = 200) { http_response_code($status); echo json_encode($data); exit(); }
function successResponse($data = null, $message = 'Success', $status = 200) {
    jsonResponse(['success' => true, 'message' => $message, 'data' => $data], $status);
}

require_once __DIR__ . '/../../includes/session.php';
app_session_start();

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

successResponse(null, 'Logged out successfully');

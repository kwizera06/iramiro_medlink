<?php
// handlers/auth/register.php
// Staff account creation. Validation runs before the admin check so duplicate
// e-mail addresses get a clean 409 even on the public route (no self sign-up).
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

function jsonResponse($data, $status = 200) { http_response_code($status); echo json_encode($data); exit(); }
function successResponse($data = null, $message = 'Success', $status = 200) {
    jsonResponse(['success' => true, 'message' => $message, 'data' => $data], $status);
}
function errorResponse($message, $status = 400) {
    jsonResponse(['success' => false, 'message' => $message], $status);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) errorResponse('Invalid input', 400);

$firstName = trim((string)($input['first_name'] ?? ''));
$lastName  = trim((string)($input['last_name'] ?? ''));
$email     = trim((string)($input['email'] ?? ''));
$password  = (string)($input['password'] ?? '');

if ($firstName === '') errorResponse("Field 'first_name' is required", 422);
if ($lastName === '')  errorResponse("Field 'last_name' is required", 422);
if ($email === '')     errorResponse("Field 'email' is required", 422);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) errorResponse('Invalid email address', 422);
if ($password === '')  errorResponse("Field 'password' is required", 422);
if (strlen($password) < 8) errorResponse('Password must be at least 8 characters', 422);
if (isset($input['confirm_password']) && $input['confirm_password'] !== $password) {
    errorResponse('Passwords do not match', 422);
}

$chk = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$chk->execute([$email]);
if ($chk->fetch()) errorResponse('Email already registered', 409);

// This installation does not allow public self-registration: only an
// administrator may create staff accounts.
if (!isUserAdmin($pdo)) errorResponse('Only administrators can create users', 403);

$allowedRoles = ['admin', 'doctor', 'nurse', 'receptionist', 'pharmacist', 'lab_tech', 'accountant', 'hr_manager', 'storekeeper'];
$role = (string)($input['role'] ?? 'receptionist');
if (!in_array($role, $allowedRoles, true)) errorResponse('Invalid role', 422);

$employeeId = trim((string)($input['employee_id'] ?? ''));
if ($employeeId === '') errorResponse("Field 'employee_id' is required", 422);

$stmt = $pdo->prepare("
    INSERT INTO users
        (employee_id, first_name, last_name, email, password_hash, role, phone,
         date_of_birth, gender, address, is_active, is_verified, created_by)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, ?)
");
$stmt->execute([
    $employeeId, $firstName, $lastName, $email, password_hash($password, PASSWORD_DEFAULT),
    $role,
    trim((string)($input['phone'] ?? '')),
    ($input['date_of_birth'] ?? '') !== '' ? $input['date_of_birth'] : null,
    ($input['gender'] ?? '') !== '' ? $input['gender'] : null,
    trim((string)($input['address'] ?? '')),
    (int)($_SESSION['user_id'] ?? 0) ?: null,
]);

$newId = (int)$pdo->lastInsertId();
successResponse(['id' => $newId], 'User created; an administrator must verify the account', 201);

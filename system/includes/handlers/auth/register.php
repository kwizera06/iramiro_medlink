<?php
// handlers/auth/register.php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/validation.php';

// Get input data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    errorResponse('Invalid input', 400);
}

// Validate input
$errors = validate($input, [
    'employee_id' => [
        function($data, $field) { return validateRequired($data, $field); },
        function($data, $field) { return validateMax($data, $field, 50); },
        function($data, $field) { return validateUnique($data, $field, 'users', 'employee_id'); }
    ],
    'first_name' => [
        function($data, $field) { return validateRequired($data, $field); },
        function($data, $field) { return validateMax($data, $field, 100); }
    ],
    'last_name' => [
        function($data, $field) { return validateRequired($data, $field); },
        function($data, $field) { return validateMax($data, $field, 100); }
    ],
    'email' => [
        function($data, $field) { return validateRequired($data, $field); },
        function($data, $field) { return validateEmail($data, $field); },
        function($data, $field) { return validateMax($data, $field, 255); },
        function($data, $field) { return validateUnique($data, $field, 'users', 'email'); }
    ],
    'password' => [
        function($data, $field) { return validateRequired($data, $field); },
        function($data, $field) { return validateMin($data, $field, 8); },
        function($data, $field) { return validateMax($data, $field, 100); }
    ],
    'role' => [
        function($data, $field) { return validateRequired($data, $field); },
        function($data, $field) { return validateIn($data, $field, ['admin', 'doctor', 'nurse', 'receptionist', 'pharmacist', 'lab_technician', 'accountant', 'hr_manager', 'department_head']); }
    ],
    'phone' => [
        function($data, $field) { return validateMax($data, $field, 20); }
    ],
    'date_of_birth' => [
        function($data, $field) { return validateDate($data, $field, 'Y-m-d'); }
    ],
    'gender' => [
        function($data, $field) { return validateIn($data, $field, ['male', 'female', 'other']); }
    ]
]);

if (!empty($errors)) {
    validationErrorResponse($errors);
}

// Check if user is authorized (only admin can create users)
$currentUser = getCurrentUser();
if (!$currentUser || $currentUser['role'] !== 'admin') {
    forbiddenResponse('Only administrators can create users');
}

// Hash password
$passwordHash = hashPassword($input['password']);

// Insert user
$db = getDB();
$stmt = $db->prepare("
    INSERT INTO users (
        employee_id, first_name, last_name, email, password_hash,
        role, phone, date_of_birth, gender, address,
        is_active, is_verified, created_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$isActive = isset($input['is_active']) ? (int)$input['is_active'] : 1;
$isVerified = isset($input['is_verified']) ? (int)$input['is_verified'] : 0;

$stmt->execute([
    $input['employee_id'],
    $input['first_name'],
    $input['last_name'],
    $input['email'],
    $passwordHash,
    $input['role'],
    $input['phone'] ?? null,
    $input['date_of_birth'] ?? null,
    $input['gender'] ?? null,
    $input['address'] ?? null,
    $isActive,
    $isVerified,
    $currentUser['id']
]);

$userId = $db->lastInsertId();

successResponse([
    'id' => $userId,
    'employee_id' => $input['employee_id'],
    'email' => $input['email']
], 'User created successfully', 201);
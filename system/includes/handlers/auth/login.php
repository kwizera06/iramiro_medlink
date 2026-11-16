<?php
// handlers/auth/login.php
// This handler now supports both:
// 1. Direct PHP session login (from login.php form POST) - primary method
// 2. API JSON login (for any remaining AJAX calls) - sets session too

require_once __DIR__ . '/../../includes/database.php';

// ============================================
// RESPONSE FUNCTIONS
// ============================================
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

function successResponse($data = null, $message = 'Success', $statusCode = 200) {
    jsonResponse([
        'success' => true,
        'message' => $message,
        'data' => $data
    ], $statusCode);
}

function errorResponse($message, $statusCode = 400) {
    jsonResponse([
        'success' => false,
        'message' => $message
    ], $statusCode);
}

// ============================================
// AUTH FUNCTIONS
// ============================================
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function authenticateUser($email, $password) {
    global $pdo;
    
    // Get user
    $stmt = $pdo->prepare("
        SELECT id, employee_id, first_name, last_name, email, 
               password_hash, role, is_active, is_verified, is_blocked, block_reason
        FROM users 
        WHERE email = ?
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return ['success' => false, 'message' => 'Invalid credentials'];
    }
    
    // Check if blocked
    if ($user['is_blocked']) {
        return ['success' => false, 'message' => 'Account is blocked: ' . ($user['block_reason'] ?? 'Contact administrator')];
    }
    
    // Check if active
    if (!$user['is_active']) {
        return ['success' => false, 'message' => 'Account is deactivated'];
    }
    
    // Check if verified
    if (!$user['is_verified']) {
        return ['success' => false, 'message' => 'Account not verified'];
    }
    
    // Verify password
    if (!verifyPassword($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Invalid credentials'];
    }
    
    // Update last login
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    // Get user roles from user_roles pivot table
    $roleStmt = $pdo->prepare("
        SELECT r.id, r.name, r.slug 
        FROM user_roles ur 
        JOIN roles r ON r.id = ur.role_id 
        WHERE ur.user_id = ? 
        ORDER BY r.name
    ");
    $roleStmt->execute([$user['id']]);
    $roles = $roleStmt->fetchAll(PDO::FETCH_ASSOC);
    $roleSlugs = array_column($roles, 'slug');
    $roleNames = array_column($roles, 'name');
    
    // Set PHP session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['employee_id'] = $user['employee_id'];
    $_SESSION['last_activity'] = time();
    $_SESSION['login_time'] = time();
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $_SESSION['user_data'] = [
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'email' => $user['email'],
        'role' => $roleSlugs[0] ?? $user['role'],
        'roles' => $roles,
        'role_slugs' => $roleSlugs,
        'role_names' => $roleNames,
        'is_admin' => in_array('admin', $roleSlugs)
    ];
    
    return [
        'success' => true,
        'data' => [
            'user' => [
                'id' => $user['id'],
                'employee_id' => $user['employee_id'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'role' => $roleSlugs[0] ?? $user['role'],
                'roles' => $roles,
                'role_slugs' => $roleSlugs,
                'role_names' => $roleNames,
                'is_admin' => in_array('admin', $roleSlugs)
            ]
        ]
    ];
}

// ============================================
// VALIDATION FUNCTIONS
// ============================================
function validate($data, $rules) {
    $errors = [];
    foreach ($rules as $field => $validations) {
        foreach ($validations as $validation) {
            $result = $validation($data, $field);
            if (!empty($result)) {
                $errors = array_merge($errors, $result);
                break;
            }
        }
    }
    return $errors;
}

function validateRequired($data, $field) {
    $errors = [];
    if (empty($data[$field]) && $data[$field] !== '0') {
        $errors[$field] = "The {$field} field is required";
    }
    return $errors;
}

function validateEmail($data, $field) {
    $errors = [];
    if (!empty($data[$field]) && !filter_var($data[$field], FILTER_VALIDATE_EMAIL)) {
        $errors[$field] = "The {$field} must be a valid email address";
    }
    return $errors;
}

function validateMin($data, $field, $length) {
    $errors = [];
    if (!empty($data[$field]) && strlen($data[$field]) < $length) {
        $errors[$field] = "The {$field} must be at least {$length} characters";
    }
    return $errors;
}

function validateMax($data, $field, $length) {
    $errors = [];
    if (!empty($data[$field]) && strlen($data[$field]) > $length) {
        $errors[$field] = "The {$field} must not exceed {$length} characters";
    }
    return $errors;
}

// ============================================
// MAIN LOGIN HANDLER (API route)
// ============================================
try {
    // Get input data (JSON from API calls)
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        errorResponse('Invalid input', 400);
    }
    
    // Validate input
    $errors = validate($input, [
        'email' => [
            function($data, $field) { return validateRequired($data, $field); },
            function($data, $field) { return validateEmail($data, $field); },
            function($data, $field) { return validateMax($data, $field, 255); }
        ],
        'password' => [
            function($data, $field) { return validateRequired($data, $field); },
            function($data, $field) { return validateMin($data, $field, 6); },
            function($data, $field) { return validateMax($data, $field, 100); }
        ]
    ]);
    
    if (!empty($errors)) {
        jsonResponse([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $errors
        ], 422);
    }
    
    // Attempt login
    $result = authenticateUser($input['email'], $input['password']);
    
    if (!$result['success']) {
        errorResponse($result['message'], 401);
    }
    
    // Return success (session is already set by authenticateUser)
    successResponse($result['data'], 'Login successful');
    
} catch (PDOException $e) {
    errorResponse('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    errorResponse('Server error: ' . $e->getMessage(), 500);
}
?>

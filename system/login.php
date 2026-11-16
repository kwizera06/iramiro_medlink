<?php
// login.php - Session-based authentication with 3-hour inactivity timeout
require_once __DIR__ . '/includes/session.php';
app_session_start();

// Page a role lands on after login (admins and every other role keep the dashboard).
// ponytail: 'accountant' is the only finance role in the database; 'cashier' is listed for when that role is created.
function landing_page(array $roleSlugs): string {
    $landing = [
        'receptionist' => 'patients.php?module=receptionist&sub=registration',
        'cashier'      => 'invoicing.php?module=finance&sub=invoices',
        'accountant'   => 'invoicing.php?module=finance&sub=invoices',
    ];
    if (in_array('admin', $roleSlugs, true)) return 'dashboard.php';
    foreach ($roleSlugs as $slug) if (isset($landing[$slug])) return $landing[$slug];
    return 'dashboard.php';
}

$error_message = '';
$success_message = '';

// Handle logout FIRST — before checking if logged in
if (isset($_GET['logout'])) {
    app_session_reset();
    $success_message = 'You have been successfully logged out.';
} else {
    // If already logged in and session is valid, redirect to dashboard
    if (isset($_SESSION['user_id']) && isset($_SESSION['last_activity'])) {
        $inactive = time() - $_SESSION['last_activity'];
        if ($inactive < 10800) { // 10800 seconds = 3 hours
            $_SESSION['last_activity'] = time();
            header('Location: ' . landing_page($_SESSION['user_data']['role_slugs'] ?? []));
            exit();
        } else {
            // Session expired, destroy it
            app_session_reset();
            $error_message = 'Your session has expired due to inactivity. Please log in again.';
        }
    }
}

// Check if user was redirected due to session timeout
if (isset($_GET['timeout']) && $_GET['timeout'] == '1') {
    $error_message = 'Your session has expired due to 3 hours of inactivity. Please log in again.';
}

// Forgot password: a 6-digit code emailed through EmailJS, then an optional new password, then sign in.
const OTP_TTL = 600;            // seconds a code stays valid
const OTP_MAX_ATTEMPTS = 5;     // wrong codes before a new one is needed
const OTP_RESEND_COOLDOWN = 60; // seconds between resends
const LOGIN_USER_COLUMNS = 'id, employee_id, first_name, last_name, email, password_hash, role, is_active, is_verified, is_blocked, block_reason';

// $column is always a literal ('id' or 'email'), never user input
function login_user_by(PDO $pdo, string $column, $value) {
    $stmt = $pdo->prepare('SELECT ' . LOGIN_USER_COLUMNS . " FROM users WHERE $column = ?");
    $stmt->execute([$value]);
    return $stmt->fetch();
}

// Why this account may not log in, or '' if it may
function login_user_error(array $user): string {
    if ($user['is_blocked']) return 'Account is blocked: ' . ($user['block_reason'] ?? 'Contact administrator.');
    if (!$user['is_active']) return 'Account is deactivated. Contact administrator.';
    if (!$user['is_verified']) return 'Account not verified. Contact administrator.';
    return '';
}

// Only the code's hash is kept in the session
function set_otp_pending($userId, string $email, string $otp): void {
    $_SESSION['otp_pending'] = ['user_id' => $userId, 'email' => $email, 'hash' => password_hash($otp, PASSWORD_DEFAULT),
        'expires' => time() + OTP_TTL, 'sent_at' => time(), 'attempts' => 0];
}

// Unknown/blocked email: same code screen with a code nobody has, so the form can't reveal which emails have accounts
function set_fake_otp_pending(string $email): void {
    set_otp_pending(null, $email, bin2hex(random_bytes(8)));
}

function send_login_otp(array $user): bool {
    require_once __DIR__ . '/includes/emailjs.php';
    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $sent = emailjs_send([
        'to_email' => $user['email'], 'to_name' => trim($user['first_name'] . ' ' . $user['last_name']),
        'passcode' => $otp, 'time' => date('g:i A', time() + OTP_TTL), 'valid_minutes' => OTP_TTL / 60,
        'app_name' => 'MedLink', 'name' => 'MedLink',
    ]);
    if ($sent) set_otp_pending($user['id'], $user['email'], $otp);
    return $sent;
}

function complete_login(PDO $pdo, array $user, bool $passwordReset = false): void {
    unset($_SESSION['otp_pending'], $_SESSION['otp_verified']);
    $roleStmt = $pdo->prepare("SELECT r.id, r.name, r.slug FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? ORDER BY r.name");
    $roleStmt->execute([$user['id']]);
    $roles = $roleStmt->fetchAll(PDO::FETCH_ASSOC);
    $roleSlugs = array_column($roles, 'slug');
    $roleNames = array_column($roles, 'name');

    $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

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

    if ($passwordReset) {
        try { require_once __DIR__ . '/includes/activity_log.php';
              log_activity($pdo, 'PASSWORD_CHANGE', 'users', "Password reset via emailed code for user ID: {$user['id']}", $user['id'], null); } catch (Throwable $e) {}
    }

    header('Location: ' . landing_page($roleSlugs));
    exit();
}

// "Back to sign in": drop the pending code
if (isset($_GET['restart'])) unset($_SESSION['otp_pending'], $_SESSION['otp_verified']);

$show_forgot = isset($_GET['forgot']);
$otpFlow = isset($_POST['new_password']) || isset($_POST['skip_new_password']) || isset($_POST['forgot_email']) || isset($_POST['otp']) || isset($_POST['resend']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $otpFlow) {
    require_once __DIR__ . '/includes/database.php';
    try {
        if (isset($_POST['new_password']) || isset($_POST['skip_new_password'])) {
            // Step 3: optionally set a new password, then sign in
            $verified = $_SESSION['otp_verified'] ?? null;
            $user = ($verified && time() <= $verified['expires']) ? login_user_by($pdo, 'id', $verified['user_id']) : null;

            if (!$user || ($blocked = login_user_error($user))) {
                unset($_SESSION['otp_verified']);
                $error_message = $blocked ?? 'Your code has expired. Please request a new one.';
                $show_forgot = true;
            } elseif (isset($_POST['skip_new_password'])) {
                complete_login($pdo, $user);
            } elseif (strlen($_POST['new_password']) < 8) {
                $error_message = 'New password must be at least 8 characters.';
            } elseif ($_POST['new_password'] !== ($_POST['confirm_password'] ?? '')) {
                $error_message = 'Passwords do not match.';
            } else {
                $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($_POST['new_password'], PASSWORD_DEFAULT), $user['id']]);
                complete_login($pdo, $user, true);
            }
        } elseif (isset($_POST['forgot_email'])) {
            // Step 1: email a sign-in code
            // ponytail: resend limit lives in the session, a DB table per email if code emails get abused
            $email = trim($_POST['forgot_email']);
            $user = filter_var($email, FILTER_VALIDATE_EMAIL) ? login_user_by($pdo, 'email', $email) : null;

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error_message = 'Please enter a valid email address.';
                $show_forgot = true;
            } elseif (!$user || login_user_error($user)) {
                set_fake_otp_pending($email);
            } elseif (!send_login_otp($user)) {
                $error_message = 'Could not send the verification email. Please try again later.';
                $show_forgot = true;
            }
        } else {
            // Step 2: verify or resend the emailed code
            $pending = $_SESSION['otp_pending'] ?? null;

            if (!$pending) {
                $error_message = 'Your code has expired. Please request a new one.';
                $show_forgot = true;
            } elseif (isset($_POST['resend'])) {
                $wait = OTP_RESEND_COOLDOWN - (time() - $pending['sent_at']);
                $user = login_user_by($pdo, 'id', $pending['user_id']);
                if ($wait > 0) {
                    $error_message = "Please wait {$wait} seconds before requesting a new code.";
                } elseif ($user && !login_user_error($user) && !send_login_otp($user)) {
                    $error_message = 'Could not send the verification email. Please try again later.';
                } else {
                    if (!$user || login_user_error($user)) set_fake_otp_pending($pending['email']);
                    $success_message = 'A new code has been sent to your email.';
                }
            } elseif (time() > $pending['expires']) {
                $error_message = 'This code has expired. Click "Resend code" to get a new one.';
            } elseif (!password_verify(trim($_POST['otp']), $pending['hash'])) {
                $attempts = ++$_SESSION['otp_pending']['attempts'];
                if ($attempts >= OTP_MAX_ATTEMPTS) {
                    unset($_SESSION['otp_pending']);
                    $error_message = 'Too many incorrect codes. Please request a new one.';
                    $show_forgot = true;
                } else {
                    $error_message = 'Incorrect code. ' . (OTP_MAX_ATTEMPTS - $attempts) . ' attempt(s) left.';
                }
            } else {
                // Re-check the account in case it was blocked while the code was out
                $user = login_user_by($pdo, 'id', $pending['user_id']);
                unset($_SESSION['otp_pending']);
                if (!$user || ($blocked = login_user_error($user))) {
                    $error_message = $blocked ?? 'Your code has expired. Please request a new one.';
                } else {
                    // Code is right: offer a new password before signing in
                    $_SESSION['otp_verified'] = ['user_id' => $user['id'], 'expires' => time() + OTP_TTL];
                }
            }
        }
    } catch (PDOException $e) {
        $error_message = 'A system error occurred. Please try again later.';
        error_log('Login error: ' . $e->getMessage());
    }
}

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$otpFlow) {
    require_once __DIR__ . '/includes/database.php';
    
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validate inputs
    if (empty($email)) {
        $error_message = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } elseif (empty($password)) {
        $error_message = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters.';
    } else {
        try {
            // Get user from database
            $stmt = $pdo->prepare("
                SELECT id, employee_id, first_name, last_name, email, 
                       password_hash, role, is_active, is_verified, is_blocked, block_reason
                FROM users 
                WHERE email = ?
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $error_message = 'Invalid email or password.';
            } elseif ($user['is_blocked']) {
                $error_message = 'Account is blocked: ' . ($user['block_reason'] ?? 'Contact administrator.');
            } elseif (!$user['is_active']) {
                $error_message = 'Account is deactivated. Contact administrator.';
            } elseif (!$user['is_verified']) {
                $error_message = 'Account not verified. Contact administrator.';
            } elseif (!password_verify($password, $user['password_hash'])) {
                $error_message = 'Invalid email or password.';
            } else {
                complete_login($pdo, $user);
            }
        } catch (PDOException $e) {
            $error_message = 'A system error occurred. Please try again later.';
            error_log('Login error: ' . $e->getMessage());
        }
    }
}

$otp_verified = $_SESSION['otp_verified'] ?? null;
$otp_pending = $_SESSION['otp_pending'] ?? null;
$otp_email_masked = $otp_pending ? substr($otp_pending['email'], 0, 1) . '***' . strstr($otp_pending['email'], '@') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MedLink - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #60a5fa;
            --secondary-color: #0ea5e9;
            --success-color: #22c55e;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --radius: 12px;
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--gray-100);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 1200px;
            min-height: 600px;
            animation: slideUp 0.6s ease-out;
        }

        .login-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: white;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow-xl);
            min-height: 600px;
        }

        .login-left {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .login-left::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            transform: rotate(45deg);
        }

        .login-left::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -30%;
            width: 80%;
            height: 80%;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 50%;
        }

        .brand-content {
            position: relative;
            z-index: 1;
        }

        .brand-icon {
            width: 64px;
            height: 64px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
            font-size: 32px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .brand-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .brand-subtitle {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 32px;
            font-weight: 300;
        }

        .brand-features {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            opacity: 0.95;
        }

        .feature-item i {
            font-size: 18px;
            opacity: 0.8;
        }

        .brand-footer {
            position: relative;
            z-index: 1;
            font-size: 13px;
            opacity: 0.8;
            padding-top: 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .login-right {
            padding: 48px 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
        }

        .login-form-wrapper {
            width: 100%;
            max-width: 400px;
        }

        .login-header {
            margin-bottom: 32px;
        }

        .login-header h2 {
            font-size: 28px;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 8px;
        }

        .login-header p {
            color: var(--gray-500);
            font-size: 15px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            animation: slideDown 0.3s ease-out;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-warning {
            background: #fffbeb;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .alert i {
            font-size: 18px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 6px;
        }

        .form-group label i {
            margin-right: 8px;
            color: var(--gray-400);
        }

        .form-group input {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            font-size: 15px;
            color: var(--gray-900);
            transition: var(--transition);
            background: white;
            font-family: 'Inter', sans-serif;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-group input::placeholder {
            color: var(--gray-400);
        }

        .password-input-wrapper {
            position: relative;
        }

        .password-input-wrapper input {
            padding-right: 44px;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray-400);
            cursor: pointer;
            padding: 8px;
            font-size: 16px;
            transition: var(--transition);
        }

        .toggle-password:hover {
            color: var(--gray-600);
        }

        .login-button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            font-family: 'Inter', sans-serif;
            margin-top: 4px;
        }

        .login-button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.3);
        }

        .login-button:active:not(:disabled) {
            transform: translateY(0);
        }

        .login-button:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .form-group input.otp-input { font-size: 22px; letter-spacing: 8px; text-align: center; font-variant-numeric: tabular-nums; }
        .otp-actions { display: flex; justify-content: center; margin-top: 16px; font-size: 14px; }
        .otp-actions button { background: none; border: none; padding: 0; color: var(--primary-color); font: inherit; font-weight: 500; cursor: pointer; }
        .otp-actions button:hover { text-decoration: underline; }

        .login-footer {
            margin-top: 24px;
            text-align: center;
            font-size: 14px;
            color: var(--gray-500);
        }

        .login-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 968px) {
            .login-wrapper {
                grid-template-columns: 1fr;
                min-height: auto;
            }

            .login-left {
                padding: 32px 24px;
                min-height: 250px;
            }

            .login-right {
                padding: 32px 24px;
            }

            .brand-features {
                display: none;
            }

            .brand-footer {
                padding-top: 16px;
                margin-top: 16px;
            }

            .brand-title {
                font-size: 24px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 10px;
            }

            .login-right {
                padding: 24px 16px;
            }

            .login-header h2 {
                font-size: 24px;
            }

            .login-wrapper {
                border-radius: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-wrapper">
            <!-- Left Panel -->
            <div class="login-left">
                <div class="brand-content">
                    <div class="brand-icon">
                        <i class="fas fa-hospital"></i>
                    </div>
                    <h1 class="brand-title">MedLink</h1>
                    <p class="brand-subtitle">Enterprise Resource Planning System</p>
                    <div class="brand-features">
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Secure & Reliable</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>24/7 Support</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>HIPAA Compliant</span>
                        </div>
                    </div>
                </div>
                <div class="brand-footer">
                    <p>&copy; 2024 MedLink. All rights reserved.</p>
                </div>
            </div>

            <!-- Right Panel -->
            <div class="login-right">
                <div class="login-form-wrapper">
                    <div class="login-header">
                        <?php if ($otp_verified): ?>
                        <h2>Set a New Password</h2>
                        <p>Choose a new password so you won't need a code next time</p>
                        <?php elseif ($otp_pending): ?>
                        <h2>Check Your Email</h2>
                        <p>We sent a 6-digit code to <strong><?php echo htmlspecialchars($otp_email_masked); ?></strong></p>
                        <?php elseif ($show_forgot): ?>
                        <h2>Forgot Password</h2>
                        <p>Enter your email and we'll send you a code to sign in</p>
                        <?php else: ?>
                        <h2>Welcome Back</h2>
                        <p>Sign in to your account to continue</p>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($error_message)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo htmlspecialchars($success_message); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($otp_verified): ?>
                    <form id="newPasswordForm" method="POST" action="login.php" autocomplete="off">
                        <div class="form-group">
                            <label for="new_password"><i class="fas fa-lock"></i> New Password</label>
                            <input type="password" id="new_password" name="new_password" placeholder="At least 8 characters" autocomplete="new-password" minlength="8" required autofocus>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Type the new password again" autocomplete="new-password" minlength="8" required>
                        </div>
                        <button type="submit" class="login-button"><span class="button-text">Save &amp; Sign In</span></button>
                        <div class="otp-actions">
                            <button type="submit" name="skip_new_password" value="1" formnovalidate>Skip for now</button>
                        </div>
                    </form>
                    <?php elseif ($otp_pending): ?>
                    <form id="otpForm" method="POST" action="login.php" autocomplete="off">
                        <div class="form-group">
                            <label for="otp"><i class="fas fa-shield-alt"></i> Verification Code</label>
                            <input type="text" id="otp" name="otp" class="otp-input" inputmode="numeric" autocomplete="one-time-code" pattern="\d{6}" maxlength="6" placeholder="000000" required autofocus>
                        </div>
                        <button type="submit" class="login-button"><span class="button-text">Verify &amp; Sign In</span></button>
                    </form>
                    <form method="POST" action="login.php" class="otp-actions">
                        <button type="submit" name="resend" value="1">Resend code</button>
                    </form>
                    <?php elseif ($show_forgot): ?>
                    <form id="forgotForm" method="POST" action="login.php" autocomplete="off">
                        <div class="form-group">
                            <label for="forgot_email"><i class="fas fa-envelope"></i> Email Address</label>
                            <input type="email" id="forgot_email" name="forgot_email" placeholder="Enter your email address" value="<?php echo htmlspecialchars($_POST['forgot_email'] ?? ''); ?>" required autofocus>
                        </div>
                        <button type="submit" class="login-button"><span class="button-text">Send Code</span></button>
                    </form>
                    <?php else: ?>
                    <form id="loginForm" method="POST" action="login.php" autocomplete="off">
                        <div class="form-group">
                            <label for="email">
                                <i class="fas fa-envelope"></i>
                                Email Address
                            </label>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                placeholder="Enter your email address"
                                value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="password">
                                <i class="fas fa-lock"></i>
                                Password
                            </label>
                            <div class="password-input-wrapper">
                                <input 
                                    type="password" 
                                    id="password" 
                                    name="password" 
                                    placeholder="Enter your password"
                                    required
                                    minlength="6"
                                >
                                <button type="button" class="toggle-password" id="togglePassword" aria-label="Toggle password visibility">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="login-button" id="loginButton">
                            <span class="button-text">Sign In</span>
                        </button>
                    </form>
                    <?php endif; ?>

                    <div class="login-footer">
                        <?php if ($otp_verified || $otp_pending || $show_forgot): ?>
                        <p><a href="login.php?restart=1">Back to sign in</a></p>
                        <?php else: ?>
                        <p><a href="login.php?forgot=1">Forgot password?</a></p>
                        <?php endif; ?>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        document.getElementById('togglePassword')?.addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });

        // Clear any old localStorage auth data (migration from old system)
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user_data');
    </script>
</body>
</html>

<?php
/**
 * session_check.php - Session validation and 3-hour inactivity timeout enforcement
 * 
 * Include this file at the top of every protected page.
 * It will immediately redirect to login.php if:
 *   - No active session exists
 *   - Session has been inactive for more than 3 hours
 * 
 * Usage: require_once __DIR__ . '/session_check.php';
 *   (from includes/ directory)
 * 
 * Or from root pages: require_once __DIR__ . '/includes/session_check.php';
 */

// Start session if not already started
require_once __DIR__ . '/session.php';
app_session_start();

// Session timeout in seconds (3 hours)
define('SESSION_TIMEOUT', 10800);

/**
 * Check if user is authenticated and session is still valid
 */
function checkSessionValidity() {
    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['last_activity'])) {
        destroyAndRedirect();
        return;
    }
    
    // Check inactivity timeout (3 hours)
    $inactive_duration = time() - $_SESSION['last_activity'];
    if ($inactive_duration > SESSION_TIMEOUT) {
        destroyAndRedirect('timeout');
        return;
    }
    
    // Session is valid - update last activity timestamp
    $_SESSION['last_activity'] = time();
}

/**
 * Destroy session and redirect to login page
 */
function destroyAndRedirect($reason = '') {
    // Check if this is an AJAX/API request
    $isAjax = (
        !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    ) || (
        isset($_SERVER['HTTP_ACCEPT']) && 
        strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false
    ) || (
        isset($_SERVER['CONTENT_TYPE']) && 
        strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false
    );
    
    // Destroy the session
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
    
    if ($isAjax) {
        // Return JSON response for AJAX requests
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $reason === 'timeout' 
                ? 'Session expired due to inactivity' 
                : 'Authentication required',
            'redirect' => 'login.php' . ($reason === 'timeout' ? '?timeout=1' : '')
        ]);
        exit();
    }
    
    // Redirect to login page
    $redirect_url = 'login.php';
    if ($reason === 'timeout') {
        $redirect_url .= '?timeout=1';
    }
    
    // Determine correct path to login.php
    $loginPath = getLoginPath();
    header('Location: ' . $loginPath . ($reason === 'timeout' ? '?timeout=1' : ''));
    exit();
}

/**
 * Determine the relative path to login.php based on current script location
 */
function getLoginPath() {
    $scriptDir = realpath(dirname($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $appRoot = realpath(dirname(__DIR__));
    if ($scriptDir && $appRoot && strpos($scriptDir, $appRoot) === 0) {
        $rel = trim(substr($scriptDir, strlen($appRoot)), DIRECTORY_SEPARATOR);
        $depth = empty($rel) ? 0 : substr_count($rel, DIRECTORY_SEPARATOR) + 1;
        if ($depth > 0) {
            return str_repeat('../', $depth) . 'login.php';
        }
    }
    return 'login.php';
}

/**
 * Get remaining session time in seconds
 */
function getSessionTimeRemaining() {
    if (!isset($_SESSION['last_activity'])) {
        return 0;
    }
    $remaining = SESSION_TIMEOUT - (time() - $_SESSION['last_activity']);
    return max(0, $remaining);
}

/**
 * Check if session is about to expire (less than 2 minutes remaining)
 */
function isSessionExpiringSoon() {
    return getSessionTimeRemaining() < 120;
}

// Run the session check immediately when this file is included
checkSessionValidity();
?>

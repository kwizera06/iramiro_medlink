<?php
// includes/session.php — the one place sessions are started.
//
// Fixes "session_start(): open(...sess_xxx, O_RDWR) failed: Permission denied (13)" on Windows:
//  - The app's sessions live in their own folder. In the shared C:\xampp\tmp, any other PHP app
//    (phpMyAdmin…) runs garbage collection with its own 24-min lifetime and deletes our files mid-use.
//  - Sessions are kept longer than the app's 3-hour inactivity timeout, so PHP never deletes a live one.
//  - A destroyed session is never reopened under the same ID. On Windows the deleted file stays
//    "delete pending" while another request (the page's polling) still has it open, and reopening it
//    fails with errno 13.
//  - If the file still cannot be read, we retry once, then start a fresh session instead of erroring.

function app_session_start(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        $dir = rtrim(ini_get('session.save_path') ?: sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'medlink_sessions';
        if (is_dir($dir) || @mkdir($dir, 0700, true)) session_save_path($dir);
        ini_set('session.gc_maxlifetime', '14400');  // > the 3-hour inactivity timeout in session_check.php
        ini_set('session.cookie_httponly', '1');     // page scripts (XSS) cannot read the session id
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.use_strict_mode', '1');     // never adopt an unknown / destroyed session ID
    }
    if (@session_start()) return;
    usleep(150000);                                  // the file may be briefly locked by a parallel request
    if (@session_start()) return;
    // Still unreadable (being deleted by a logout / timeout in another request): continue with a new session
    session_id(session_create_id());
    session_start();
}

// Per-session CSRF token for authenticated API writes. It is stored server-side
// and is never derived from the session id or user id.
function app_csrf_token(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) app_session_start();
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Log out: empty and delete the session, then continue on a brand-new one (never the old ID)
function app_session_reset(): void {
    $_SESSION = [];
    @session_destroy();
    session_id(session_create_id());
    app_session_start();
}

// Role cache (#2): login stores the user's roles in the session; re-read them from user_roles at most every
// 20 s so an admin's role change reaches a signed-in user without a new login. 'role' stays the first role (legacy).
function app_refresh_roles(PDO $pdo): void {
    if (empty($_SESSION['user_id']) || empty($_SESSION['user_data'])) return;
    if (time() - ($_SESSION['roles_checked_at'] ?? 0) < 20) return;
    $_SESSION['roles_checked_at'] = time();
    $st = $pdo->prepare("SELECT r.id, r.name, r.slug FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? ORDER BY r.name");
    $st->execute([(int)$_SESSION['user_id']]);
    $roles = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$roles) return;   // keep the old cache rather than lock a user out on a transient empty read
    $slugs = array_column($roles, 'slug');
    $_SESSION['user_data']['roles'] = $roles;
    $_SESSION['user_data']['role_slugs'] = $slugs;
    $_SESSION['user_data']['role_names'] = array_column($roles, 'name');
    $_SESSION['user_data']['role'] = $slugs[0];
    $_SESSION['user_data']['is_admin'] = in_array('admin', $slugs, true);
}

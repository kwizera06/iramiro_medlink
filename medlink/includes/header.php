<?php
// includes/header.php
@ini_set('pcre.jit', 0);

// Enforce session-based authentication with 3-hour inactivity timeout
// This will immediately redirect to login.php if session is invalid or expired
require_once __DIR__ . '/session_check.php';
require_once __DIR__ . '/database.php';

// Define variables before using them
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentModule = isset($_GET['module']) ? $_GET['module'] : '';
$currentSubModule = isset($_GET['sub']) ? $_GET['sub'] : '';

// User is guaranteed to be logged in at this point (session_check enforces it)
$isLoggedIn = true;

// Get user data from session
// Sessions started before avatars existed don't carry profile_picture yet — fetch once
if (!empty($_SESSION['user_id']) && !array_key_exists('profile_picture', $_SESSION['user_data'] ?? [])) {
    $ppStmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $ppStmt->execute([$_SESSION['user_id']]);
    $_SESSION['user_data']['profile_picture'] = $ppStmt->fetchColumn() ?: null;
}
$userData = $_SESSION['user_data'];

// Helper: Check if logged-in user has a specific role
// Administrator / isAdmin always returns true (full access to all menus)
if (!function_exists('userHasRole')) {
function userHasRole($roleSlug) {
    $adminRoles = ['admin', 'administrator', 'isadmin'];
    $roleSlugLower = strtolower(trim($roleSlug));

    if (!isset($_SESSION['user_id'])) {
        global $userData;
        // Fallback to demo userData role - admin sees all
        $userRole = strtolower(trim($userData['role'] ?? ''));
        if (in_array($userRole, $adminRoles, true)) return true;
        return $userRole === $roleSlugLower;
    }

    // Check is_admin flag in session
    if (!empty($_SESSION['user_data']['is_admin']) || !empty($_SESSION['is_admin'])) {
        return true;
    }

    // Check from session cache
    if (isset($_SESSION['user_data']['role_slugs']) && is_array($_SESSION['user_data']['role_slugs'])) {
        $slugs = array_map(function($s){ return strtolower(trim($s)); }, $_SESSION['user_data']['role_slugs']);
        foreach ($adminRoles as $ar) {
            if (in_array($ar, $slugs, true)) return true;
        }
        if (in_array($roleSlugLower, $slugs, true)) return true;
    }

    if (isset($_SESSION['user_data']['role_names']) && is_array($_SESSION['user_data']['role_names'])) {
        $names = array_map(function($s){ return strtolower(trim($s)); }, $_SESSION['user_data']['role_names']);
        foreach ($adminRoles as $ar) {
            if (in_array($ar, $names, true)) return true;
        }
    }

    if (isset($_SESSION['user_data']['role'])) {
        $r = strtolower(trim((string)$_SESSION['user_data']['role']));
        if (in_array($r, $adminRoles, true)) return true;
    }

    // Fallback: query DB
    global $pdo;
    if (!isset($pdo) && isset($GLOBALS['pdo'])) {
        $pdo = $GLOBALS['pdo'];
    }
    if (!isset($pdo)) {
        require_once __DIR__ . '/database.php';
        if (!isset($pdo) && isset($GLOBALS['pdo'])) {
            $pdo = $GLOBALS['pdo'];
        }
    }
    // Check if user is admin / administrator / isadmin first
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM user_roles ur
        JOIN roles r ON r.id = ur.role_id
        WHERE ur.user_id = ? AND (
            LOWER(r.slug) IN ('admin', 'administrator', 'isadmin')
            OR LOWER(r.name) IN ('admin', 'administrator', 'isadmin')
        )
    ");
    $stmt->execute([$_SESSION['user_id']]);
    if ((int)$stmt->fetchColumn() > 0) return true;

    $stmtUsers = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmtUsers->execute([$_SESSION['user_id']]);
    $userColRole = strtolower(trim((string)$stmtUsers->fetchColumn()));
    if (in_array($userColRole, $adminRoles, true)) return true;
    
    // Check specific role
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM user_roles ur
        JOIN roles r ON r.id = ur.role_id
        WHERE ur.user_id = ? AND (LOWER(r.slug) = ? OR LOWER(r.name) = ?)
    ");
    $stmt->execute([$_SESSION['user_id'], $roleSlugLower, $roleSlugLower]);
    return (int)$stmt->fetchColumn() > 0;
}
}

// Helper: Check if logged-in user is an admin / administrator / isadmin
if (!function_exists('isUserAdmin')) {
function isUserAdmin() {
    $adminRoles = ['admin', 'administrator', 'isadmin'];

    // Check is_admin flag in session
    if (!empty($_SESSION['user_data']['is_admin']) || !empty($_SESSION['is_admin'])) {
        return true;
    }

    // Demo / unauthenticated fallback
    if (!isset($_SESSION['user_id'])) {
        global $userData;
        $userRole = strtolower(trim($userData['role'] ?? ''));
        return in_array($userRole, $adminRoles, true);
    }

    // Check role from session cache
    if (isset($_SESSION['user_data']['role'])) {
        $r = strtolower(trim((string)$_SESSION['user_data']['role']));
        if (in_array($r, $adminRoles, true)) return true;
    }
    if (isset($_SESSION['role_name']) && in_array(strtolower(trim((string)$_SESSION['role_name'])), $adminRoles, true)) {
        return true;
    }
    if (isset($_SESSION['role']) && in_array(strtolower(trim((string)$_SESSION['role'])), $adminRoles, true)) {
        return true;
    }

    // Check role_slugs from session cache
    if (isset($_SESSION['user_data']['role_slugs']) && is_array($_SESSION['user_data']['role_slugs'])) {
        foreach ($_SESSION['user_data']['role_slugs'] as $slug) {
            if (in_array(strtolower(trim((string)$slug)), $adminRoles, true)) return true;
        }
    }

    // Check role_names from session cache
    if (isset($_SESSION['user_data']['role_names']) && is_array($_SESSION['user_data']['role_names'])) {
        foreach ($_SESSION['user_data']['role_names'] as $name) {
            if (in_array(strtolower(trim((string)$name)), $adminRoles, true)) return true;
        }
    }

    // Fallback: query DB
    $userId = $_SESSION['user_id'] ?? ($_SESSION['user_data']['id'] ?? null);
    if ($userId) {
        global $pdo;
        if (!isset($pdo) && isset($GLOBALS['pdo'])) {
            $pdo = $GLOBALS['pdo'];
        }
        if (!isset($pdo)) {
            require_once __DIR__ . '/database.php';
            if (!isset($pdo) && isset($GLOBALS['pdo'])) {
                $pdo = $GLOBALS['pdo'];
            }
        }
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM user_roles ur
                JOIN roles r ON r.id = ur.role_id
                WHERE ur.user_id = ? AND (
                    LOWER(r.slug) IN ('admin', 'administrator', 'isadmin')
                    OR LOWER(r.name) IN ('admin', 'administrator', 'isadmin')
                )
            ");
            $stmt->execute([$userId]);
            if ((int)$stmt->fetchColumn() > 0) return true;

            $stmtUsers = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmtUsers->execute([$userId]);
            $userColRole = strtolower(trim((string)$stmtUsers->fetchColumn()));
            if (in_array($userColRole, $adminRoles, true)) return true;
        } catch (Exception $e) {
            // ignore error
        }
    }

    return false;
}
}

$isAdmin = isUserAdmin();

// Function to check if menu item is active
if (!function_exists('isActive')) {
function isActive($page, $module = '', $sub = '') {
    global $currentPage, $currentModule, $currentSubModule;
    if ($page == $currentPage && empty($module)) {
        return 'active';
    }
    if (!empty($module) && $module == $currentModule && empty($sub)) {
        return 'active';
    }
    if (!empty($module) && !empty($sub) && $module == $currentModule && $sub == $currentSubModule) {
        return 'active';
    }
    return '';
}
}

// Function to check if parent menu should be expanded
if (!function_exists('isExpanded')) {
function isExpanded($module) {
    global $currentModule;
    return $currentModule == $module ? 'open' : '';
}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital ERP - <?php echo ucfirst($currentPage); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
    // Default for From/To date filters: one month back through today (local date)
    window.dfRange = function(fromId, toId, months = 1){
      const iso = d => d.toLocaleDateString('en-CA'), d = new Date(), to = iso(d);
      d.setMonth(d.getMonth() - months);
      const f = document.getElementById(fromId), t = document.getElementById(toId);
      if (f) f.value = iso(d);
      if (t) t.value = to;
    };
    </script>
<style>
        /* ============================================
           RESET & BASE
           ============================================ */
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
            --sidebar-width: 280px;
            --header-height: 70px;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --radius: 12px;
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--gray-50);
            display: flex;
            min-height: 100vh;
        }

        /* ============================================
           MOBILE SIDEBAR OVERLAY
           ============================================ */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            z-index: 999; /* must stay lower than .sidebar's z-index */
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar-overlay.show {
            display: block;
            opacity: 1;
        }

        /* ============================================
           SIDEBAR
           ============================================ */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--gray-900);
            color: white;
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
            overflow-x: hidden;
            transition: var(--transition);
            z-index: 1001; /* must stay higher than .sidebar-overlay */
        }

        .sidebar::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: var(--gray-600);
            border-radius: 2px;
        }

        .sidebar-brand {
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            height: var(--header-height);
            position: sticky;
            top: 0;
            background: var(--gray-900);
            z-index: 10;
        }

        .sidebar-brand .brand-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .sidebar-brand .brand-text {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .sidebar-brand .brand-text span {
            color: var(--primary-light);
        }

        .sidebar-menu {
            padding: 8px 0 20px;
        }

        .sidebar-menu .menu-section {
            padding: 12px 20px 6px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--gray-400);
            letter-spacing: 0.5px;
        }

        /* Main Menu Items */
        .sidebar-menu .menu-item {
            padding: 10px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--gray-300);
            text-decoration: none;
            transition: var(--transition);
            border-left: 3px solid transparent;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            position: relative;
        }

        .sidebar-menu .menu-item:hover {
            background: rgba(255, 255, 255, 0.05);
            color: white;
            border-left-color: var(--primary-color);
        }

        .sidebar-menu .menu-item.active {
            background: rgba(37, 99, 235, 0.1);
            color: white;
            border-left-color: var(--primary-color);
        }

        .sidebar-menu .menu-item i {
            width: 18px;
            font-size: 15px;
            flex-shrink: 0;
        }

        .sidebar-menu .menu-item .badge {
            margin-left: auto;
            background: var(--danger-color);
            color: white;
            padding: 1px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }

        .sidebar-menu .menu-item .badge-success {
            background: var(--success-color);
        }

        .sidebar-menu .menu-item .badge-warning {
            background: var(--warning-color);
        }

        .sidebar-menu .menu-item .arrow {
            margin-left: auto;
            transition: var(--transition);
            font-size: 12px;
        }

        .sidebar-menu .menu-item .arrow.open {
            transform: rotate(90deg);
        }

        /* Sub Menu */
        .sidebar-menu .sub-menu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background: rgba(255, 255, 255, 0.02);
            /* One indent step under the parent row; nested groups indent again from here. */
            padding-left: 20px;
        }

        .sidebar-menu .sub-menu.open {
            max-height: 2000px;
        }

        .sidebar-menu .sub-menu .sub-item {
            padding: 8px 20px 8px 28px;
            display: flex;
            align-items: center;
            gap: 7px;
            color: var(--gray-400);
            text-decoration: none;
            transition: var(--transition);
            font-size: 12px;
            border-left: 3px solid transparent;
        }

        .sidebar-menu .sub-menu .sub-group {
            padding: 10px 20px 4px 52px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray-400);
            opacity: 0.6;
        }

        .sidebar-menu .sub-group-toggle {
            padding: 9px 20px 9px 28px;
            display: flex;
            align-items: center;
            gap: 7px;
            color: var(--gray-300);
            cursor: pointer;
            font-size: 12px;
        }
        .sidebar-menu .sub-group-toggle:hover { color: white; background: rgba(255,255,255,.03); }
        .sidebar-menu .sub-group-toggle > i:first-child { width: 18px; font-size: 15px; flex-shrink: 0; text-align: center; }
        .sidebar-menu .sub-group-toggle .arrow { margin-left: auto; font-size: 10px; transition: var(--transition); }
        .sidebar-menu .sub-group-toggle .arrow.open { transform: rotate(90deg); }
        .sidebar-menu .nested-menu {
            display: none;
            margin-left: 20px;
            position: relative;
        }
        .sidebar-menu .nested-menu.open { display: block; }
        .sidebar-menu .nested-menu .sub-item {
            position: relative;
            padding-left: 50px !important;
            list-style: none !important;
        }
        /* Curved tree branch: the vertical rail runs down the row, then bends into the
           icon with a rounded elbow (like a folder tree). */
        .sidebar-menu .nested-menu .sub-item::before {
            content: '';
            position: absolute;
            left: 14px;                 /* 14px + the item's 3px border = the 17px rail */
            top: 0;
            width: 28px;
            height: 50%;
            border-left: 1px solid rgba(148,163,184,.5);
            border-bottom: 1px solid rgba(148,163,184,.5);
            border-bottom-left-radius: 9px;
            pointer-events: none;
        }
        /* Continue the rail through the row so the elbows stay connected; the last row ends at its curve. */
        .sidebar-menu .nested-menu .sub-item::after {
            content: '';
            position: absolute;
            left: 14px;
            top: 50%;
            bottom: 0;
            border-left: 1px solid rgba(148,163,184,.5);
            pointer-events: none;
        }
        .sidebar-menu .nested-menu .sub-item:last-child::after { display: none; }
        /* Start the rail right under the group's icon instead of leaving a gap. */
        .sidebar-menu .nested-menu .sub-item:first-child::before {
            top: -9px;
            height: calc(50% + 9px);
        }

        .sidebar-menu .sub-menu .sub-item:hover {
            color: white;
            background: rgba(255, 255, 255, 0.03);
            border-left-color: var(--primary-color);
        }

        .sidebar-menu .sub-menu .sub-item.active {
            color: white;
            border-left-color: var(--primary-light);
            background: rgba(37, 99, 235, 0.05);
        }

        .sidebar-menu .sub-menu .sub-item i {
            width: 18px;
            font-size: 15px;
            flex-shrink: 0;
            text-align: center;
        }

        /* ============================================
           MAIN CONTENT
           ============================================ */
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            width: calc(100% - var(--sidebar-width));
        }

        /* ============================================
           HEADER
           ============================================ */
        .header {
            height: var(--header-height);
            background: white;
            border-bottom: 1px solid var(--gray-200);
            padding: 0 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: var(--shadow-sm);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .header-left .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            color: var(--gray-600);
            cursor: pointer;
            padding: 8px;
        }

        .header-left .page-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--gray-900);
        }

        .header-left .page-title .page-subtitle {
            font-size: 14px;
            font-weight: 400;
            color: var(--gray-500);
            margin-left: 8px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        /* ============================================
           GLOBAL SYSTEM SEARCH (HEADER & MODAL)
           ============================================ */
        <?php if (empty($isAdmin)): ?>
        .header-search-container,
        #headerSearchContainer,
        .mobile-search-btn,
        #mobileSearchBtn,
        .gs-modal-backdrop,
        #globalSearchModalBackdrop {
            display: none !important;
            pointer-events: none !important;
            visibility: hidden !important;
        }
        <?php endif; ?>
        .header-search-container {
            position: relative;
        }

        .header-right .search-box {
            display: flex;
            align-items: center;
            background: var(--gray-50);
            border-radius: 8px;
            padding: 7px 14px;
            border: 1px solid var(--gray-200);
            transition: var(--transition);
            width: 290px;
            cursor: pointer;
        }

        .header-right .search-box:hover,
        .header-right .search-box:focus-within {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            background: white;
        }

        .header-right .search-box input {
            border: none;
            background: none;
            outline: none;
            padding: 3px 8px;
            font-size: 13px;
            color: var(--gray-800);
            width: 100%;
            font-family: 'Inter', sans-serif;
        }

        .header-right .search-box input::placeholder {
            color: var(--gray-400);
            font-size: 12px;
        }

        .header-right .search-box .search-kbd-hint {
            display: flex;
            align-items: center;
            gap: 2px;
            margin-left: auto;
            flex-shrink: 0;
        }

        .header-right .search-box .search-kbd-hint kbd {
            background: var(--gray-200);
            color: var(--gray-600);
            border-radius: 4px;
            padding: 2px 5px;
            font-size: 10px;
            font-family: monospace;
            font-weight: 600;
        }

        .mobile-search-btn {
            display: none;
            background: none;
            border: none;
            font-size: 18px;
            color: var(--gray-600);
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
        }

        .mobile-search-btn:hover {
            color: var(--primary-color);
            background: var(--gray-100);
        }

        /* Live Header Search Dropdown */
        .header-search-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            width: 400px;
            max-height: 480px;
            background: white;
            border-radius: 12px;
            border: 1px solid var(--gray-200);
            box-shadow: 0 14px 32px rgba(0, 0, 0, 0.14);
            z-index: 1050;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            animation: gsSlideDown 0.18s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes gsSlideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .hsd-header {
            padding: 10px 14px;
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            font-weight: 600;
            color: var(--gray-700);
        }

        .hsd-header .hsd-tip kbd {
            background: var(--gray-200);
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 10px;
        }

        .hsd-results {
            max-height: 380px;
            overflow-y: auto;
            padding: 6px 0;
        }

        .hsd-footer {
            padding: 10px 14px;
            background: var(--gray-50);
            border-top: 1px solid var(--gray-200);
            font-size: 12px;
            color: var(--primary-color);
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: var(--transition);
        }

        .hsd-footer:hover {
            background: rgba(37, 99, 235, 0.08);
        }

        /* ============================================
           GLOBAL SEARCH MODAL (FULL HUB)
           ============================================ */
        .gs-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(4px);
            z-index: 99999;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 40px 16px 20px;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
        }

        .gs-modal-backdrop.active {
            opacity: 1;
            pointer-events: auto;
        }

        .gs-modal {
            background: white;
            width: 100%;
            max-width: 880px;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 80px);
            transform: scale(0.96) translateY(-10px);
            transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            border: 1px solid var(--gray-200);
        }

        .gs-modal-backdrop.active .gs-modal {
            transform: scale(1) translateY(0);
        }

        /* Modal Search Input Header */
        .gs-modal-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            gap: 14px;
            background: white;
        }

        .gs-modal-header .gs-search-icon {
            font-size: 20px;
            color: var(--primary-color);
        }

        .gs-modal-header input {
            flex: 1;
            border: none;
            outline: none;
            font-size: 16px;
            font-weight: 500;
            color: var(--gray-900);
            font-family: 'Inter', sans-serif;
            background: transparent;
        }

        .gs-modal-header input::placeholder {
            color: var(--gray-400);
            font-weight: 400;
        }

        .gs-modal-header .gs-clear-btn {
            background: var(--gray-100);
            border: none;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            color: var(--gray-500);
            display: none;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
            transition: var(--transition);
        }

        .gs-modal-header .gs-clear-btn:hover {
            background: var(--gray-200);
            color: var(--gray-800);
        }

        .gs-modal-header .gs-close-btn {
            background: var(--gray-100);
            border: none;
            padding: 6px 12px;
            border-radius: 8px;
            color: var(--gray-600);
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .gs-modal-header .gs-close-btn:hover {
            background: var(--danger-color);
            color: white;
        }

        /* Category Filter Pills (Specifically searching for) */
        .gs-category-bar {
            padding: 12px 20px;
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .gs-category-bar-label {
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray-500);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .gs-category-pills {
            display: flex;
            align-items: center;
            gap: 6px;
            overflow-x: auto;
            padding-bottom: 3px;
            scrollbar-width: thin;
        }

        .gs-category-pills::-webkit-scrollbar {
            height: 4px;
        }
        .gs-category-pills::-webkit-scrollbar-thumb {
            background: var(--gray-300);
            border-radius: 2px;
        }

        .gs-pill {
            padding: 6px 12px;
            border-radius: 20px;
            border: 1px solid var(--gray-200);
            background: white;
            color: var(--gray-700);
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease;
            user-select: none;
        }

        .gs-pill:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            background: rgba(37, 99, 235, 0.04);
        }

        .gs-pill.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            font-weight: 600;
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.3);
        }

        .gs-pill .pill-count {
            background: rgba(0, 0, 0, 0.08);
            padding: 1px 6px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 700;
        }

        .gs-pill.active .pill-count {
            background: rgba(255, 255, 255, 0.25);
            color: white;
        }

        /* Modal Body & Results */
        .gs-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 16px 20px;
            background: #ffffff;
            min-height: 280px;
            max-height: 520px;
        }

        .gs-section {
            margin-bottom: 20px;
        }

        .gs-section-title {
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--gray-500);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .gs-section-title .badge-count {
            background: var(--gray-100);
            color: var(--gray-600);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
        }

        .gs-item-card {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 10px 14px;
            border-radius: 10px;
            text-decoration: none;
            color: inherit;
            border: 1px solid transparent;
            transition: all 0.15s ease;
            cursor: pointer;
            margin-bottom: 4px;
        }

        .gs-item-card:hover,
        .gs-item-card.keyboard-active {
            background: var(--gray-50);
            border-color: var(--gray-200);
            transform: translateX(3px);
        }

        .gs-item-card.keyboard-active {
            border-color: var(--primary-color) !important;
            background: rgba(37, 99, 235, 0.05) !important;
        }

        .gs-item-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .icon-patient { background: #dcfce7; color: #15803d; }
        .icon-staff { background: #e0e7ff; color: #4338ca; }
        .icon-pharmacy { background: #fef3c7; color: #b45309; }
        .icon-invoice { background: #fee2e2; color: #b91c1c; }
        .icon-visit { background: #e0f2fe; color: #0369a1; }
        .icon-department { background: #f3e8ff; color: #7e22ce; }
        .icon-document { background: #f1f5f9; color: #475569; }
        .icon-service { background: #fce7f3; color: #be185d; }
        .icon-emergency { background: #ffe4e6; color: #e11d48; }

        .gs-item-content {
            flex: 1;
            min-width: 0;
        }

        .gs-item-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 2px;
        }

        .gs-item-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--gray-900);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .gs-item-badge {
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .gs-badge-green { background: #dcfce7; color: #166534; }
        .gs-badge-blue { background: #dbeafe; color: #1e40af; }
        .gs-badge-yellow { background: #fef3c7; color: #92400e; }
        .gs-badge-red { background: #fee2e2; color: #991b1b; }
        .gs-badge-purple { background: #f3e8ff; color: #6b21a8; }
        .gs-badge-gray { background: #f1f5f9; color: #475569; }

        .gs-item-sub {
            font-size: 12px;
            color: var(--gray-500);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .gs-item-action {
            font-size: 12px;
            font-weight: 600;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 4px;
            opacity: 0;
            transform: translateX(-4px);
            transition: all 0.15s ease;
            white-space: nowrap;
        }

        .gs-item-card:hover .gs-item-action,
        .gs-item-card.keyboard-active .gs-item-action {
            opacity: 1;
            transform: translateX(0);
        }

        /* Welcome Guide inside modal */
        .gs-welcome-box {
            text-align: center;
            padding: 24px 10px;
        }

        .gs-welcome-title {
            font-size: 17px;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 6px;
        }

        .gs-welcome-desc {
            font-size: 13px;
            color: var(--gray-500);
            max-width: 520px;
            margin: 0 auto 20px;
        }

        .gs-quick-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            max-width: 780px;
            margin: 0 auto;
        }

        .gs-quick-card {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            padding: 14px;
            text-align: left;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .gs-quick-card:hover {
            border-color: var(--primary-color);
            background: white;
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .gs-quick-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .gs-quick-info .gs-quick-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--gray-900);
        }

        .gs-quick-info .gs-quick-hint {
            font-size: 11px;
            color: var(--gray-500);
        }

        /* Highlight matching query in text */
        .gs-highlight {
            background: #fef08a;
            color: #854d0e;
            padding: 0 2px;
            border-radius: 2px;
            font-weight: 700;
        }

        /* Modal Footer */
        .gs-modal-footer {
            padding: 10px 20px;
            background: var(--gray-50);
            border-top: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 11.5px;
            color: var(--gray-500);
        }

        .gs-shortcuts {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .gs-shortcuts span {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .gs-shortcuts kbd {
            background: white;
            border: 1px solid var(--gray-300);
            border-radius: 4px;
            padding: 1px 5px;
            font-size: 10px;
            font-family: monospace;
            box-shadow: 0 1px 1px rgba(0,0,0,0.05);
        }

        .header-right .notification-btn {
            position: relative;
            background: none;
            border: none;
            font-size: 20px;
            color: var(--gray-600);
            cursor: pointer;
            padding: 8px;
            transition: var(--transition);
        }

        .header-right .notification-btn:hover {
            color: var(--primary-color);
        }

        .header-right .notification-btn .notification-dot {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 8px;
            height: 8px;
            background: var(--danger-color);
            border-radius: 50%;
            border: 2px solid white;
        }

        .header-right .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 4px 12px 4px 4px;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            border: 1px solid transparent;
        }

        .header-right .user-profile:hover {
            background: var(--gray-50);
            border-color: var(--gray-200);
        }

        .header-right .user-profile .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            overflow: hidden;
        }

        .header-right .user-profile .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .header-logout-btn { width:38px; height:38px; border:1px solid var(--gray-200); background:white; color:var(--gray-600); border-radius:8px; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:background-color 140ms ease-out,color 140ms ease-out; }
        .header-logout-btn:hover { background:#fef2f2; color:#dc2626; border-color:#fecaca; }
        .header-logout-btn:active { transform:scale(.97); }
        .header-right .user-profile .user-info {
            display: flex;
            flex-direction: column;
        }

        .header-right .user-profile .user-info .user-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--gray-900);
        }

        .header-right .user-profile .user-info .user-role {
            font-size: 12px;
            color: var(--gray-500);
        }

        .header-right .user-profile .user-info .user-role .role-badge {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .role-badge.admin { background: #dbeafe; color: #1e40af; }
        .role-badge.doctor { background: #d1fae5; color: #065f46; }
        .role-badge.nurse { background: #fce7f3; color: #9d174d; }
        .role-badge.receptionist { background: #fef3c7; color: #92400e; }
        .role-badge.pharmacist { background: #e0e7ff; color: #3730a3; }
        .role-badge.lab_technician { background: #f3e8ff; color: #6b21a8; }
        .role-badge.accountant { background: #d1fae5; color: #065f46; }
        .role-badge.hr_manager { background: #fce4ec; color: #b71c1c; }

        /* ============================================
           CONTENT AREA
           ============================================ */
        .content-area {
            flex: 1;
            padding: 24px 32px;
            overflow-y: auto;
        }

        /* ============================================
           BREADCRUMB
           ============================================ */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--gray-500);
            margin-bottom: 20px;
            padding: 12px 16px;
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
        }

        .breadcrumb a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* ============================================
           RESPONSIVE
           ============================================ */
        @media (max-width: 1200px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
                box-shadow: var(--shadow-lg);
            }

            .sidebar.open {
                transform: translateX(0) !important;
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }

            .header-left .menu-toggle {
                display: block;
            }
        }

        @media (max-width: 768px) {
            .header {
                padding: 0 16px;
            }

            .content-area {
                padding: 16px;
            }

            .header-right .search-box {
                display: none;
            }

            .header-right .user-profile .user-info {
                display: none;
            }
        }

        /* ============================================
           MOBILE (applies to every portal page)
           ============================================ */
        html {
            -webkit-tap-highlight-color: transparent;
            -webkit-text-size-adjust: 100%;
        }

        .main-content {
            min-width: 0; /* stop wide tables/grids from pushing the flex layout past the screen */
        }

        .sidebar {
            height: 100dvh;
        }

        button, a, [role="button"], .menu-item, .sub-item {
            touch-action: manipulation;
        }

        @media (pointer: coarse) {
            input, textarea, select {
                font-size: 16px !important; /* below 16px iOS zooms on focus */
            }
        }

        @media (max-width: 768px) {
            .header {
                gap: 8px;
            }

            .header-left {
                gap: 8px;
                min-width: 0;
            }

            .header-left .page-title {
                font-size: 16px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .header-left .page-title .page-subtitle {
                display: none;
            }

            .header-right {
                gap: 4px;
                flex-shrink: 0;
            }

            .header-search-container {
                display: none;
            }

            .mobile-search-btn {
                display: block;
            }

            .header-right .user-profile {
                padding: 4px;
            }

            .content-area {
                padding: 12px;
            }

            .breadcrumb {
                font-size: 12px;
                padding: 10px 12px;
                margin-bottom: 12px;
                flex-wrap: wrap;
            }

            /* Fixed-column inline grids stack; auto-fit/auto-fill grids already adapt */
            .content-area [style*="grid-template-columns"]:not([style*="auto-fit"]):not([style*="auto-fill"]),
            .content-area .grid {
                grid-template-columns: 1fr !important;
            }

            /* Toolbars, filter rows and tab bars wrap instead of overflowing */
            .content-area [style*="display:flex"],
            .content-area [style*="display: flex"] {
                flex-wrap: wrap;
            }

            /* Tables scroll sideways inside their own box */
            .content-area table {
                display: block;
                max-width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .content-area img,
            .content-area canvas {
                max-width: 100%;
                height: auto;
            }

            /* Inline-styled modals: use the full screen width */
            [style*="position:fixed"][style*="inset:0"] > div,
            [style*="position: fixed"][style*="inset: 0"] > div {
                max-width: calc(100vw - 24px) !important;
            }

            .main-content > footer {
                padding: 12px 16px !important;
                justify-content: center !important;
                text-align: center;
            }

            .gs-modal-backdrop {
                padding: 12px 8px;
            }

            .gs-shortcuts {
                display: none;
            }
        }
    </style>
</head>
<body>

    <!-- ============================================
    MOBILE SIDEBAR OVERLAY
    ============================================ -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ============================================
    SIDEBAR
    ============================================ -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">
                <i class="fas fa-hospital"></i>
            </div>
            <div class="brand-text">MED<span>LINK</span></div>
        </div>

        <nav class="sidebar-menu" id="sidebarMenu">
<?php
// ===== DYNAMIC MENU RENDERING FROM DATABASE =====
if (!isset($pdo)) {
    require_once __DIR__ . '/database.php';
}
// Get user's role IDs and user ID
$userRoleIds = [];
$currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($currentUserId > 0) {
    $roleStmt = $pdo->prepare("SELECT role_id FROM user_roles WHERE user_id = ?");
    $roleStmt->execute([$currentUserId]);
    $userRoleIds = $roleStmt->fetchAll(PDO::FETCH_COLUMN);
}
// Check if user is admin (admin sees everything)
// Also treat as admin if no session (dev mode / demo)
if (!isset($isAdmin)) {
    $isAdmin = isUserAdmin();
}
if (!$isAdmin && !empty($userRoleIds)) {
    $adminCheck = $pdo->prepare("SELECT id FROM roles WHERE id IN (".implode(',', array_fill(0, count($userRoleIds), '?')).") AND (slug IN ('admin', 'administrator', 'isadmin') OR LOWER(name) IN ('admin', 'administrator', 'isadmin'))");
    $adminCheck->execute($userRoleIds);
    $isAdmin = (bool)$adminCheck->fetch();
}
if (!$isAdmin && empty($userRoleIds) && !$currentUserId) {
    $isAdmin = true;
}

// Optional workflow-step label (nav_menu_items.group_label) -> level-2 groups; sidebar still works before migrate.php adds it
$groupCol = $pdo->query("SHOW COLUMNS FROM nav_menu_items LIKE 'group_label'")->fetch() ? 'i.group_label' : 'NULL';

// Get menu tree based on role access + user custom access overrides
if ($isAdmin) {
    // Admin sees all active menus
    $menuQuery = $pdo->query("
        SELECT m.id AS menu_id, m.title AS menu_title, m.icon AS menu_icon, m.section_label,
               i.id AS item_id, i.title AS item_title, i.url, i.icon AS item_icon, $groupCol AS group_label
        FROM nav_menus m
        JOIN nav_menu_items i ON i.menu_id = m.id AND i.is_active = 1
        WHERE m.is_active = 1
        ORDER BY m.menu_order, i.item_order
    ");
} else {
    // Non-admin: show items from assigned roles OR user custom overrides
    $rolePlaceholders = !empty($userRoleIds) ? implode(',', array_fill(0, count($userRoleIds), '?')) : '0';
    $params = !empty($userRoleIds) ? $userRoleIds : [0];
    $params[] = $currentUserId;

    $menuQuery = $pdo->prepare("
        SELECT DISTINCT m.id AS menu_id, m.title AS menu_title, m.icon AS menu_icon, m.section_label, m.menu_order,
               i.id AS item_id, i.title AS item_title, i.url, i.icon AS item_icon, $groupCol AS group_label, i.item_order
        FROM nav_menus m
        JOIN nav_menu_items i ON i.menu_id = m.id AND i.is_active = 1
        LEFT JOIN role_menu_access rma ON rma.menu_item_id = i.id AND rma.role_id IN ($rolePlaceholders)
        LEFT JOIN user_menu_access uma ON uma.menu_item_id = i.id AND uma.user_id = ?
        WHERE m.is_active = 1 
          AND (rma.id IS NOT NULL OR uma.id IS NOT NULL)
        ORDER BY m.menu_order, i.item_order
    ");
    $menuQuery->execute($params);
}

$menuRows = $menuQuery->fetchAll();

// Group by menu
$menuTree = [];
foreach ($menuRows as $row) {
    $mid = $row['menu_id'];
    if (!isset($menuTree[$mid])) {
        $menuTree[$mid] = [
            'title' => $row['menu_title'],
            'icon' => $row['menu_icon'],
            'section_label' => $row['section_label'],
            'items' => []
        ];
    }
    $menuTree[$mid]['items'][] = [
        'title' => $row['item_title'],
        'url' => $row['url'],
        'icon' => $row['item_icon'],
        'group' => $row['group_label']
    ];
}

// Render menus
$lastSection = '';
foreach ($menuTree as $menuId => $menu):
    // Section header
    if ($menu['section_label'] && $menu['section_label'] !== $lastSection):
        $lastSection = $menu['section_label'];
?>
            <div class="menu-section"><?php echo htmlspecialchars($menu['section_label']); ?></div>
<?php endif;
    $items = $menu['items'];
    if (count($items) === 1 && $menu['title'] === $items[0]['title']):
        // Single-item menu (like Dashboard) - render as direct link
        $item = $items[0];
        $urlParts = parse_url($item['url']);
        $pageName = basename($urlParts['path'] ?? '', '.php');
        parse_str($urlParts['query'] ?? '', $queryParams);
        $module = $queryParams['module'] ?? '';
        $sub = $queryParams['sub'] ?? '';
?>
            <a href="<?php echo htmlspecialchars($item['url']); ?>" class="menu-item <?php echo isActive($pageName, $module, $sub); ?>">
                <i class="<?php echo htmlspecialchars($item['icon']); ?>"></i> <?php echo htmlspecialchars($item['title']); ?>
            </a>
<?php else:
        // Multi-item menu - render with sub-menu toggle
        $menuSlug = strtolower(preg_replace('/[^a-z0-9]/i', '', $menu['title']));
        $menuDomId = $menuSlug . 'Menu';
        // Check if any sub-item is active (for expanded state)
        $isMenuExpanded = false;
        foreach ($items as $item) {
            $urlParts = parse_url($item['url']);
            $pageName = basename($urlParts['path'] ?? '', '.php');
            parse_str($urlParts['query'] ?? '', $qp);
            if (isActive($pageName, $qp['module'] ?? '', $qp['sub'] ?? '') === 'active') {
                $isMenuExpanded = true;
                break;
            }
        }
?>
            <div class="menu-item" onclick="toggleSubMenu('<?php echo $menuDomId; ?>')">
                <i class="<?php echo htmlspecialchars($menu['icon']); ?>"></i> <?php echo htmlspecialchars($menu['title']); ?>
                <i class="fas fa-chevron-right arrow <?php echo $isMenuExpanded ? 'open' : ''; ?>"></i>
            </div>
            <div class="sub-menu <?php echo $isMenuExpanded ? 'open' : ''; ?>" id="<?php echo $menuDomId; ?>">
<?php       $groups = [];
            foreach ($items as $item) {
                $group = $item['group'] ?: $item['title'];
                $groups[$group][] = $item;
            }
            foreach ($groups as $groupTitle => $groupItems):
                $groupSlug = strtolower(preg_replace('/[^a-z0-9]/i', '', $menuDomId . $groupTitle));
                $groupHasActive = false;
                foreach ($groupItems as $groupItem) {
                    $u = parse_url($groupItem['url']); parse_str($u['query'] ?? '', $gqp);
                    if (isActive(basename($u['path'] ?? '', '.php'), $gqp['module'] ?? '', $gqp['sub'] ?? '') === 'active') $groupHasActive = true;
                }
                $groupIcon = $groupItems[0]['icon'];
                if (strpos((string)$groupIcon, 'fa-circle') !== false) {
                    $groupIcon = match ($groupTitle) {
                        'Nursing & Triage' => 'fas fa-user-nurse',
                        default => 'fas fa-folder-open'
                    };
                }
                if (count($groupItems) > 1):
?>
                <div class="sub-group-toggle" onclick="toggleNestedSubMenu('<?php echo $groupSlug; ?>')">
                    <i class="<?php echo htmlspecialchars($groupIcon); ?>"></i>
                    <?php echo htmlspecialchars($groupTitle); ?><i class="fas fa-chevron-right arrow <?php echo $groupHasActive ? 'open' : ''; ?>"></i>
                </div>
                <div class="nested-menu <?php echo $groupHasActive ? 'open' : ''; ?>" id="<?php echo $groupSlug; ?>">
<?php           endif;
            foreach ($groupItems as $item):
                $urlParts = parse_url($item['url']);
                $pageName = basename($urlParts['path'] ?? '', '.php');
                parse_str($urlParts['query'] ?? '', $qp);
                $module = $qp['module'] ?? '';
                $sub = $qp['sub'] ?? '';
                // Replace legacy placeholder bullets with useful submenu icons.
                $displayIcon = $item['icon'];
                if (strpos((string)$displayIcon, 'fa-circle') !== false) {
                    $displayIcon = match ($item['title']) {
                        'Nurse Portal' => 'fas fa-user-nurse',
                        'Procedures and Billing' => 'fas fa-notes-medical',
                        default => 'fas fa-file-medical'
                    };
                }
?>
                <a href="<?php echo htmlspecialchars($item['url']); ?>" class="sub-item <?php echo isActive($pageName, $module, $sub); ?>">
                    <i class="<?php echo htmlspecialchars($displayIcon); ?>"></i> <?php echo htmlspecialchars($item['title']); ?>
                </a>
<?php       endforeach;
            if (count($groupItems) > 1): ?>
                </div>
<?php           endif;
            endforeach; ?>
            </div>
<?php endif;
endforeach; ?>

            <!-- Logout -->
            <div class="menu-section"></div>
            <a href="#" class="menu-item" onclick="logout()">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>
    </aside>

    <!-- ============================================
    MAIN CONTENT
    ============================================ -->
    <div class="main-content">
        <!-- ============================================
        HEADER
        ============================================ -->
        <header class="header">
            <div class="header-left">
                <button class="menu-toggle" id="menuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="page-title">
                    <?php 
                    $pageTitles = [
                        'dashboard' => 'Dashboard',
                        'patients' => 'Patient Management',
                        'appointments' => 'Appointments',
                        'department' => 'My Department',
                        'departments' => 'Department Management',
                        'hr' => 'Human Resources',
                        'procurement' => 'Procurement',
                        'inventory' => 'Inventory Management',
                        'pharmacy' => 'Pharmacy',
                        'insurance' => 'Insurance Management',
                        'finance' => 'Finance',
                        'lab' => 'Laboratory',
                        'radiology' => 'Radiology',
                        'ot' => 'Operation Theatre',
                        'emergency' => 'Emergency Department',
                        'ward' => 'Ward Management',
                        'nursing' => 'Nursing',
                        'maternity' => 'Maternity',
                        'icu' => 'ICU/CCU',
                        'dental' => 'Dental',
                        'dialysis' => 'Dialysis',
                        'physiotherapy' => 'Physiotherapy',
                        'patient-portal' => 'Patient Portal',
                        'reports' => 'Reports & BI',
                        'settings' => 'System Administration',
                        'integrations' => 'Integration Hub'
                    ];
                    $title = isset($pageTitles[$currentPage]) ? $pageTitles[$currentPage] : ucfirst($currentPage);
                    echo $title;
                    ?>
                    <span class="page-subtitle">- <?php echo date('l, F j, Y'); ?></span>
                </div>
            </div>

            <div class="header-right">

                <button class="notification-btn" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <span class="notification-dot"></span>
                </button>

                <div class="user-profile">
                    <div class="user-avatar" id="hdr-user-avatar">
                        <?php if (!empty($userData['profile_picture'])): ?>
                        <img src="<?php echo htmlspecialchars($userData['profile_picture']); ?>" alt="">
                        <?php else:
                        $name = isset($userData['first_name']) ? $userData['first_name'] : 'S';
                        $initial = substr($name, 0, 1);
                        echo strtoupper($initial);
                        endif; ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name">
                            <?php 
                            if (isset($userData['first_name']) && isset($userData['last_name'])) {
                                echo $userData['first_name'] . ' ' . $userData['last_name'];
                            } else {
                                echo 'System Administrator';
                            }
                            ?>
                        </div>
                        <div class="user-role">
                            <span class="role-badge <?php echo isset($userData['role']) ? $userData['role'] : 'admin'; ?>">
                                <?php echo isset($userData['role']) ? ucfirst(str_replace('_', ' ', $userData['role'])) : 'Admin'; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <button type="button" class="header-logout-btn" onclick="logout()" title="Logout" aria-label="Logout"><i class="fas fa-sign-out-alt"></i></button>
            </div>
        </header>

        <?php if (!empty($isAdmin)): ?>
        <!-- ============================================
        GLOBAL SEARCH CENTER MODAL
        ============================================ -->
        <div class="gs-modal-backdrop" id="globalSearchModalBackdrop">
            <div class="gs-modal" role="dialog" aria-modal="true" aria-labelledby="gsModalInput">
                <!-- Modal Search Input Bar -->
                <div class="gs-modal-header">
                    <i class="fas fa-search gs-search-icon"></i>
                    <input type="text" id="gsModalInput" placeholder="Search whole hospital system (Patients, Staff, Drugs, Invoices, Visits...)" autocomplete="off">
                    <i class="fas fa-spinner fa-spin" id="gsSpinner" style="display:none; color:var(--primary-color); font-size:16px;"></i>
                    <button class="gs-clear-btn" id="gsClearBtn" title="Clear Search" onclick="GlobalSearch.clearInput()">
                        <i class="fas fa-times"></i>
                    </button>
                    <button class="gs-close-btn" onclick="GlobalSearch.closeModal()" title="Close (ESC)">
                        <kbd>ESC</kbd> Close
                    </button>
                </div>

                <!-- Specifically Searching For - Category Selector Bar -->
                <div class="gs-category-bar">
                    <div class="gs-category-bar-label">
                        <i class="fas fa-sliders-h" style="color:var(--primary-color);"></i> What are you specifically searching for?
                    </div>
                    <div class="gs-category-pills" id="gsCategoryPills">
                        <button type="button" class="gs-pill active" data-category="all">
                            <i class="fas fa-globe"></i> Whole System <span class="pill-count" id="count-all" style="display:none;">0</span>
                        </button>
                        <button type="button" class="gs-pill" data-category="patients">
                            <i class="fas fa-user-injured"></i> Patients <span class="pill-count" id="count-patients" style="display:none;">0</span>
                        </button>
                        <button type="button" class="gs-pill" data-category="staff">
                            <i class="fas fa-user-md"></i> Staff & Doctors <span class="pill-count" id="count-staff" style="display:none;">0</span>
                        </button>
                        <button type="button" class="gs-pill" data-category="pharmacy">
                            <i class="fas fa-pills"></i> Pharmacy & Drugs <span class="pill-count" id="count-pharmacy" style="display:none;">0</span>
                        </button>
                        <button type="button" class="gs-pill" data-category="invoices">
                            <i class="fas fa-file-invoice-dollar"></i> Invoices & Billing <span class="pill-count" id="count-invoices" style="display:none;">0</span>
                        </button>
                        <button type="button" class="gs-pill" data-category="visits">
                            <i class="fas fa-stethoscope"></i> Visits & Consultations <span class="pill-count" id="count-visits" style="display:none;">0</span>
                        </button>
                        <button type="button" class="gs-pill" data-category="departments">
                            <i class="fas fa-hospital-alt"></i> Departments <span class="pill-count" id="count-departments" style="display:none;">0</span>
                        </button>
                        <button type="button" class="gs-pill" data-category="documents">
                            <i class="fas fa-file-medical"></i> Documents & Records <span class="pill-count" id="count-documents" style="display:none;">0</span>
                        </button>
                        <button type="button" class="gs-pill" data-category="services">
                            <i class="fas fa-procedures"></i> Services & Tariff <span class="pill-count" id="count-services" style="display:none;">0</span>
                        </button>
                        <button type="button" class="gs-pill" data-category="emergency">
                            <i class="fas fa-ambulance"></i> Emergency & Wards <span class="pill-count" id="count-emergency" style="display:none;">0</span>
                        </button>
                    </div>
                </div>

                <!-- Modal Body / Results -->
                <div class="gs-modal-body" id="gsModalResults">
                    <!-- Populated dynamically -->
                </div>

                <!-- Modal Footer -->
                <div class="gs-modal-footer">
                    <div class="gs-shortcuts">
                        <span><kbd>↑</kbd> <kbd>↓</kbd> Navigate</span>
                        <span><kbd>↵</kbd> Select</span>
                        <span><kbd>ESC</kbd> Close</span>
                    </div>
                    <div style="font-weight: 500;">
                        <span id="gsResultsSummary">Ready to search</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ============================================
        CONTENT AREA
        ============================================ -->
        <main class="content-area">
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
                <?php if ($currentPage != 'dashboard'): ?>
                    <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                    <span><?php echo isset($pageTitles[$currentPage]) ? $pageTitles[$currentPage] : ucfirst($currentPage); ?></span>
                    <?php if (!empty($currentSubModule)): ?>
                        <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                        <span><?php echo ucfirst(str_replace('-', ' ', $currentSubModule)); ?></span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <script>
                // Global API base path
                window._apiBase = window.location.origin + '<?php echo rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/"); ?>/api.php';

                // Top-right toast: stays ttl seconds (default 5 min), closable; a closed/expired key is never shown again
                window.mlToast = ({key, title, msg, icon = 'fa-bell', color = '#2563eb', ttl = 300}) => {
                    const gone = JSON.parse(localStorage.getItem('ml_toast_gone') || '[]');
                    if (gone.includes(key) || document.querySelector('[data-toast="' + key + '"]')) return;
                    let box = document.getElementById('ml-toasts');
                    if (!box) { box = document.createElement('div'); box.id = 'ml-toasts'; box.style.cssText = 'position:fixed;top:16px;right:16px;z-index:100000;display:flex;flex-direction:column;gap:10px;max-width:360px;'; document.body.appendChild(box); }
                    const t = document.createElement('div');
                    t.dataset.toast = key;
                    t.style.cssText = 'background:#fff;border:1px solid var(--gray-200);border-left:4px solid ' + color + ';border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.15);padding:12px 36px 12px 14px;position:relative;font-size:13px;';
                    const esc = x => String(x ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
                    t.innerHTML = '<div style="font-weight:600;color:var(--gray-900);"><i class="fas ' + icon + '" style="color:' + color + ';margin-right:6px;"></i>' + esc(title) + '</div><div style="color:var(--gray-600);margin-top:2px;">' + esc(msg) + '</div><button aria-label="Close" style="position:absolute;top:6px;right:8px;border:none;background:none;font-size:18px;cursor:pointer;color:var(--gray-400);">&times;</button>';
                    const close = () => { clearTimeout(timer); t.remove(); localStorage.setItem('ml_toast_gone', JSON.stringify([...JSON.parse(localStorage.getItem('ml_toast_gone') || '[]'), key].slice(-200))); };
                    t.querySelector('button').onclick = close;
                    const timer = setTimeout(close, ttl * 1000);
                    box.appendChild(t);
                };
                // Doctor: a lab test they ordered has a result
                <?php if (!empty($_SESSION['user_id'])): ?>
                (function labResultToasts() {
                    const poll = () => fetch(window._apiBase + '?endpoint=notifications&action=my_lab_results').then(r => r.json()).then(j => {
                        (j.data || []).forEach(o => mlToast({key: 'labres' + o.id, title: 'Lab result ready', msg: o.description + ' — ' + o.patient_name + (o.visit_no ? ' (' + o.visit_no + ')' : ''), icon: 'fa-flask', color: '#16a34a', ttl: o.seconds_left}));
                    }).catch(() => {});
                    document.addEventListener('DOMContentLoaded', poll);
                    setInterval(poll, 30000);
                })();
                <?php endif; ?>

                // Rows-per-page choice (10/25/50/100/All), remembered per table in localStorage; 0 = All
                window.pgSize = (key, def) => { const v = localStorage.getItem('pg:' + key); return v === null ? def : +v; };
                window.pgSizeSelect = (key, def, onchange, sizes = [10, 25, 50, 100, 0]) => {
                    const cur = pgSize(key, def);
                    return `<label style="font-size:12px;color:var(--gray-500);display:inline-flex;align-items:center;gap:6px;margin-right:auto;">Rows <select onchange="localStorage.setItem('pg:${key}',this.value);${onchange || ''}" style="padding:5px 8px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;background:white;color:var(--gray-700);cursor:pointer;">`
                        + sizes.map(n => `<option value="${n}"${n === cur ? ' selected' : ''}>${n || 'All'}</option>`).join('') + '</select></label>';
                };

                // Payment badge for a service (status from payStatusSql): shown to staff, never blocks the service
                function payBadge(status, balance) {
                    const s = {
                        paid:     ['#d1fae5', '#065f46', 'fa-check-circle', 'Paid'],
                        covered:  ['#dbeafe', '#1e40af', 'fa-shield-alt', 'Covered by insurance'],
                        approved: ['#dbeafe', '#1e40af', 'fa-user-check', 'Approved, pay later'],
                        partial:  ['#fef3c7', '#92400e', 'fa-adjust', 'Part paid, awaiting approval'],
                        unpaid:   ['#fee2e2', '#991b1b', 'fa-exclamation-circle', 'Awaiting approval']
                    }[status];
                    if (!s) return '<span style="padding:2px 8px;border-radius:10px;font-size:11px;background:#f3f4f6;color:#6b7280;">No bill</span>';
                    const owed = status !== 'paid' && status !== 'covered' && +balance > 0 ? ' · ' + Math.round(+balance).toLocaleString() + ' RWF' : '';
                    return `<span style="padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;background:${s[0]};color:${s[1]};white-space:nowrap;"><i class="fas ${s[2]}"></i> ${s[3]}${owed}</span>`;
                }

                // ============================================
                // SIDEBAR FUNCTIONS
                // ============================================

                // Toggle submenu
                function toggleSubMenu(menuId) {
                    const subMenu = document.getElementById(menuId);
                    const arrow = document.getElementById(menuId.replace('Menu', 'Arrow'));

                    if (subMenu) {
                        subMenu.classList.toggle('open');
                        if (arrow) {
                            arrow.classList.toggle('open');
                        }
                    }
                }

                function toggleNestedSubMenu(menuId) {
                    const subMenu = document.getElementById(menuId);
                    if (!subMenu) return;
                    const opening = !subMenu.classList.contains('open');
                    subMenu.classList.toggle('open', opening);
                    const arrow = subMenu.previousElementSibling && subMenu.previousElementSibling.querySelector('.arrow');
                    if (arrow) arrow.classList.toggle('open', opening);
                }

                // ============================================
                // MOBILE SIDEBAR (with overlay, scroll lock, icon swap, Esc to close)
                // ============================================
                (function () {
                    const sidebar = document.getElementById('sidebar');
                    const overlay = document.getElementById('sidebarOverlay');
                    const menuToggle = document.getElementById('menuToggle');
                    const toggleIcon = menuToggle ? menuToggle.querySelector('i') : null;

                    function openSidebar() {
                        sidebar.classList.add('open');
                        overlay.classList.add('show');
                        document.body.style.overflow = 'hidden';
                        if (toggleIcon) {
                            toggleIcon.classList.remove('fa-bars');
                            toggleIcon.classList.add('fa-times');
                        }
                    }

                    function closeSidebar() {
                        sidebar.classList.remove('open');
                        overlay.classList.remove('show');
                        document.body.style.overflow = '';
                        if (toggleIcon) {
                            toggleIcon.classList.remove('fa-times');
                            toggleIcon.classList.add('fa-bars');
                        }
                    }

                    if (menuToggle) {
                        menuToggle.addEventListener('click', function () {
                            if (sidebar.classList.contains('open')) {
                                closeSidebar();
                            } else {
                                openSidebar();
                            }
                        });
                    }

                    if (overlay) {
                        overlay.addEventListener('click', closeSidebar);
                    }

                    // Close when a sidebar link is tapped (mobile navigates to new page anyway,
                    // but this also covers same-page anchor links / SPA-style navigation)
                    sidebar.querySelectorAll('.sub-item, .menu-item[href]').forEach(function (link) {
                        link.addEventListener('click', function () {
                            if (window.innerWidth <= 1200) {
                                closeSidebar();
                            }
                        });
                    });

                    document.addEventListener('keydown', function (e) {
                        if (e.key === 'Escape') closeSidebar();
                    });

                    window.addEventListener('resize', function () {
                        if (window.innerWidth > 1200) closeSidebar();
                    });
                })();

                // ============================================
                // LOGOUT FUNCTION
                // ============================================
                function logout() {
                    if (confirm('Are you sure you want to logout?')) {
                        window.location.href = 'login.php?logout=1';
                    }
                }

                // ============================================
                // SESSION TIMEOUT AUTO-LOGOUT (3 hours)
                // ============================================
                (function() {
                    const SESSION_TIMEOUT = 10800; // 3 hours in seconds
                    let lastActivity = Date.now();
                    let timeoutWarningShown = false;
                    
                    // Track user activity
                    ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(function(event) {
                        document.addEventListener(event, function() {
                            lastActivity = Date.now();
                            timeoutWarningShown = false;
                        }, { passive: true });
                    });
                    
                    // Check session every 30 seconds
                    setInterval(function() {
                        const inactiveSeconds = (Date.now() - lastActivity) / 1000;
                        
                        if (inactiveSeconds >= SESSION_TIMEOUT) {
                            // Session expired - redirect immediately
                            window.location.href = 'login.php?timeout=1';
                        } else if (inactiveSeconds >= (SESSION_TIMEOUT - 120) && !timeoutWarningShown) {
                            // Warn 2 minutes before expiry
                            timeoutWarningShown = true;
                            showSessionWarning();
                        }
                    }, 30000);
                    
                    function showSessionWarning() {
                        const warning = document.createElement('div');
                        warning.id = 'session-timeout-warning';
                        warning.style.cssText = 'position:fixed;top:20px;right:20px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:16px 24px;border-radius:8px;z-index:99999;box-shadow:0 10px 15px rgba(0,0,0,0.1);font-family:Inter,sans-serif;font-size:14px;max-width:350px;';
                        warning.innerHTML = '<strong><i class="fas fa-exclamation-triangle" style="margin-right:8px;"></i>Session Expiring</strong><br><span style="font-size:13px;margin-top:4px;display:block;">Your session will expire in less than 2 minutes due to inactivity. Move your mouse or press any key to stay logged in.</span>';
                        
                        // Remove existing warning if present
                        const existing = document.getElementById('session-timeout-warning');
                        if (existing) existing.remove();
                        
                        document.body.appendChild(warning);
                        
                        // Remove warning after 10 seconds or on activity
                        setTimeout(function() {
                            if (warning.parentNode) warning.remove();
                        }, 10000);
                        
                        document.addEventListener('mousemove', function removeWarning() {
                            if (warning.parentNode) warning.remove();
                            document.removeEventListener('mousemove', removeWarning);
                        }, { once: true });
                    }
                })();

                // ============================================
                // GLOBAL SYSTEM SEARCH CLIENT
                // ============================================
                <?php if (!empty($isAdmin)): ?>
                const GlobalSearch = (function () {
                    let activeCategory = 'all';
                    let currentQuery = '';
                    let debounceTimer = null;
                    let headerDebounceTimer = null;
                    let activeIndex = -1;
                    let currentFlatResults = [];

                    const modalBackdrop = document.getElementById('globalSearchModalBackdrop');
                    const modalInput = document.getElementById('gsModalInput');
                    const modalResults = document.getElementById('gsModalResults');
                    const clearBtn = document.getElementById('gsClearBtn');
                    const spinner = document.getElementById('gsSpinner');
                    const resultsSummary = document.getElementById('gsResultsSummary');
                    const headerInput = document.getElementById('headerSearchInput');
                    const headerDropdown = document.getElementById('headerSearchDropdown');
                    const headerDropdownResults = document.getElementById('headerSearchDropdownResults');

                    const categoryMeta = {
                        patients: { name: 'Patients', icon: 'fa-user-injured', iconClass: 'icon-patient', badgeClass: 'gs-badge-green' },
                        staff: { name: 'Staff & Doctors', icon: 'fa-user-md', iconClass: 'icon-staff', badgeClass: 'gs-badge-blue' },
                        pharmacy: { name: 'Pharmacy & Drugs', icon: 'fa-pills', iconClass: 'icon-pharmacy', badgeClass: 'gs-badge-yellow' },
                        invoices: { name: 'Invoices & Billing', icon: 'fa-file-invoice-dollar', iconClass: 'icon-invoice', badgeClass: 'gs-badge-red' },
                        visits: { name: 'Visits & Consultations', icon: 'fa-stethoscope', iconClass: 'icon-visit', badgeClass: 'gs-badge-blue' },
                        departments: { name: 'Departments', icon: 'fa-hospital-alt', iconClass: 'icon-department', badgeClass: 'gs-badge-purple' },
                        documents: { name: 'Documents & Records', icon: 'fa-file-medical', iconClass: 'icon-document', badgeClass: 'gs-badge-gray' },
                        services: { name: 'Services & Tariff', icon: 'fa-procedures', iconClass: 'icon-service', badgeClass: 'gs-badge-purple' },
                        emergency: { name: 'Emergency & Wards', icon: 'fa-ambulance', iconClass: 'icon-emergency', badgeClass: 'gs-badge-red' }
                    };

                    function esc(s) {
                        if (!s) return '';
                        return String(s)
                            .replace(/&/g, '&amp;')
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;')
                            .replace(/"/g, '&quot;')
                            .replace(/'/g, '&#039;');
                    }

                    function highlightText(text, query) {
                        if (!text || !query) return esc(text || '');
                        const safeText = esc(text);
                        const safeQuery = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                        const regex = new RegExp('(' + safeQuery + ')', 'gi');
                        return safeText.replace(regex, '<span class="gs-highlight">$1</span>');
                    }

                    function init() {
                        if (!modalBackdrop || !modalInput) return;

                        // Shortcut listener: Ctrl+K / Cmd+K / Slash (/)
                        document.addEventListener('keydown', function (e) {
                            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                                e.preventDefault();
                                openModal(headerInput ? headerInput.value : '');
                            } else if (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
                                e.preventDefault();
                                openModal('');
                            } else if (e.key === 'Escape') {
                                closeModal();
                                closeHeaderDropdown();
                            }
                        });

                        // Backdrop click to close
                        modalBackdrop.addEventListener('click', function (e) {
                            if (e.target === modalBackdrop) {
                                closeModal();
                            }
                        });

                        // Modal Input typing listener
                        modalInput.addEventListener('input', function () {
                            currentQuery = this.value.trim();
                            if (clearBtn) clearBtn.style.display = currentQuery ? 'flex' : 'none';
                            clearTimeout(debounceTimer);
                            if (!currentQuery) {
                                renderWelcomeScreen();
                                resetCounts();
                                return;
                            }
                            debounceTimer = setTimeout(() => {
                                executeSearch(currentQuery, activeCategory);
                            }, 220);
                        });

                        modalInput.addEventListener('keydown', handleModalKeyNav);

                        // Header Input listener
                        if (headerInput) {
                            headerInput.addEventListener('input', function () {
                                const q = this.value.trim();
                                clearTimeout(headerDebounceTimer);
                                if (!q) {
                                    closeHeaderDropdown();
                                    return;
                                }
                                headerDebounceTimer = setTimeout(() => {
                                    executeHeaderDropdownSearch(q);
                                }, 220);
                            });

                            headerInput.addEventListener('keydown', function (e) {
                                if (e.key === 'Enter') {
                                    e.preventDefault();
                                    closeHeaderDropdown();
                                    openModal(this.value.trim());
                                } else if (e.key === 'Escape') {
                                    closeHeaderDropdown();
                                }
                            });

                            // Close dropdown when clicking outside
                            document.addEventListener('click', function (e) {
                                const cont = document.getElementById('headerSearchContainer');
                                if (cont && !cont.contains(e.target)) {
                                    closeHeaderDropdown();
                                }
                            });
                        }

                        // Category Pills click listener
                        document.querySelectorAll('.gs-pill').forEach(pill => {
                            pill.addEventListener('click', function () {
                                const cat = this.getAttribute('data-category');
                                setCategory(cat);
                            });
                        });
                    }

                    function openModal(query = '') {
                        closeHeaderDropdown();
                        if (!modalBackdrop) return;
                        modalBackdrop.classList.add('active');
                        document.body.style.overflow = 'hidden';
                        
                        if (query) {
                            modalInput.value = query;
                            currentQuery = query;
                            if (clearBtn) clearBtn.style.display = 'flex';
                            executeSearch(query, activeCategory);
                        } else if (!modalInput.value.trim()) {
                            renderWelcomeScreen();
                        } else {
                            executeSearch(modalInput.value.trim(), activeCategory);
                        }
                        setTimeout(() => modalInput.focus(), 60);
                    }

                    function openModalWithHeaderQuery() {
                        const q = headerInput ? headerInput.value.trim() : '';
                        openModal(q);
                    }

                    function closeModal() {
                        if (!modalBackdrop) return;
                        modalBackdrop.classList.remove('active');
                        document.body.style.overflow = '';
                        activeIndex = -1;
                    }

                    function clearInput() {
                        if (modalInput) {
                            modalInput.value = '';
                            currentQuery = '';
                            if (clearBtn) clearBtn.style.display = 'none';
                            modalInput.focus();
                        }
                        resetCounts();
                        renderWelcomeScreen();
                    }

                    function closeHeaderDropdown() {
                        if (headerDropdown) headerDropdown.style.display = 'none';
                    }

                    function setCategory(category) {
                        activeCategory = category;
                        document.querySelectorAll('.gs-pill').forEach(p => {
                            if (p.getAttribute('data-category') === category) {
                                p.classList.add('active');
                            } else {
                                p.classList.remove('active');
                            }
                        });
                        if (currentQuery) {
                            executeSearch(currentQuery, activeCategory);
                        } else {
                            renderWelcomeScreen();
                        }
                        modalInput.focus();
                    }

                    function resetCounts() {
                        document.querySelectorAll('.gs-pill .pill-count').forEach(cnt => {
                            cnt.style.display = 'none';
                            cnt.textContent = '0';
                        });
                        if (resultsSummary) resultsSummary.textContent = 'Ready to search';
                    }

                    function updateCountBadges(counts, total) {
                        const allCountEl = document.getElementById('count-all');
                        if (allCountEl) {
                            allCountEl.textContent = total || '0';
                            allCountEl.style.display = total > 0 ? 'inline-block' : 'none';
                        }
                        for (const [cat, cnt] of Object.entries(counts || {})) {
                            const el = document.getElementById('count-' + cat);
                            if (el) {
                                el.textContent = cnt || '0';
                                el.style.display = cnt > 0 ? 'inline-block' : 'none';
                            }
                        }
                        if (resultsSummary) {
                            resultsSummary.innerHTML = `Found <strong style="color:var(--gray-900);">${total}</strong> result${total === 1 ? '' : 's'}`;
                        }
                    }

                    function renderWelcomeScreen() {
                        activeIndex = -1;
                        currentFlatResults = [];
                        if (resultsSummary) resultsSummary.textContent = 'Select what you are searching for';
                        
                        const selectedName = activeCategory === 'all' ? 'Whole System' : (categoryMeta[activeCategory]?.name || 'Specific Category');

                        modalResults.innerHTML = `
                            <div class="gs-welcome-box">
                                <div style="width:52px;height:52px;background:rgba(37,99,235,0.08);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;color:var(--primary-color);font-size:22px;">
                                    <i class="fas fa-search"></i>
                                </div>
                                <div class="gs-welcome-title">Search ${esc(selectedName)}</div>
                                <div class="gs-welcome-desc">
                                    Type patient name, UHID, invoice number, drug name, or staff member. Or choose what you are specifically searching for:
                                </div>
                                <div class="gs-quick-grid">
                                    <div class="gs-quick-card" onclick="GlobalSearch.setCategory('patients')">
                                        <div class="gs-quick-icon icon-patient"><i class="fas fa-user-injured"></i></div>
                                        <div class="gs-quick-info">
                                            <div class="gs-quick-name">Patients</div>
                                            <div class="gs-quick-hint">UHID, Name, Phone, ID</div>
                                        </div>
                                    </div>
                                    <div class="gs-quick-card" onclick="GlobalSearch.setCategory('staff')">
                                        <div class="gs-quick-icon icon-staff"><i class="fas fa-user-md"></i></div>
                                        <div class="gs-quick-info">
                                            <div class="gs-quick-name">Staff & Doctors</div>
                                            <div class="gs-quick-hint">Clinicians, Employees</div>
                                        </div>
                                    </div>
                                    <div class="gs-quick-card" onclick="GlobalSearch.setCategory('pharmacy')">
                                        <div class="gs-quick-icon icon-pharmacy"><i class="fas fa-pills"></i></div>
                                        <div class="gs-quick-info">
                                            <div class="gs-quick-name">Pharmacy & Drugs</div>
                                            <div class="gs-quick-hint">Medications, Inventory</div>
                                        </div>
                                    </div>
                                    <div class="gs-quick-card" onclick="GlobalSearch.setCategory('invoices')">
                                        <div class="gs-quick-icon icon-invoice"><i class="fas fa-file-invoice-dollar"></i></div>
                                        <div class="gs-quick-info">
                                            <div class="gs-quick-name">Invoices & Billing</div>
                                            <div class="gs-quick-hint">Bills, Receipts, Amounts</div>
                                        </div>
                                    </div>
                                    <div class="gs-quick-card" onclick="GlobalSearch.setCategory('visits')">
                                        <div class="gs-quick-icon icon-visit"><i class="fas fa-stethoscope"></i></div>
                                        <div class="gs-quick-info">
                                            <div class="gs-quick-name">Visits & Consults</div>
                                            <div class="gs-quick-hint">Diagnoses, Queue, Visits</div>
                                        </div>
                                    </div>
                                    <div class="gs-quick-card" onclick="GlobalSearch.setCategory('departments')">
                                        <div class="gs-quick-icon icon-department"><i class="fas fa-hospital-alt"></i></div>
                                        <div class="gs-quick-info">
                                            <div class="gs-quick-name">Departments</div>
                                            <div class="gs-quick-hint">Units, Wards, Specialty</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    }

                    async function executeSearch(query, category) {
                        if (!query) return;
                        if (spinner) spinner.style.display = 'inline-block';
                        activeIndex = -1;
                        try {
                            const resp = await fetch('api.php?endpoint=global-search&q=' + encodeURIComponent(query) + '&category=' + encodeURIComponent(category));
                            const json = await resp.json();
                            if (spinner) spinner.style.display = 'none';

                            if (json.success) {
                                updateCountBadges(json.counts, json.total_count);
                                renderResults(json, query, category);
                            } else {
                                modalResults.innerHTML = '<div style="text-align:center;padding:40px;color:var(--danger-color);">' + esc(json.message || 'Error searching') + '</div>';
                            }
                        } catch (err) {
                            if (spinner) spinner.style.display = 'none';
                            modalResults.innerHTML = '<div style="text-align:center;padding:40px;color:var(--danger-color);"><i class="fas fa-exclamation-circle" style="font-size:24px;margin-bottom:8px;display:block;"></i>Failed to load search results.</div>';
                        }
                    }

                    function renderResults(data, query, category) {
                        currentFlatResults = [];
                        activeIndex = -1;

                        if (data.total_count === 0) {
                            const catLabel = category === 'all' ? 'the entire system' : (categoryMeta[category]?.name || category);
                            modalResults.innerHTML = `
                                <div style="text-align:center;padding:50px 20px;">
                                    <div style="width:50px;height:50px;border-radius:50%;background:var(--gray-100);color:var(--gray-400);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:20px;">
                                        <i class="fas fa-search"></i>
                                    </div>
                                    <h4 style="font-size:16px;font-weight:600;color:var(--gray-800);margin-bottom:6px;">No matches found</h4>
                                    <p style="font-size:13px;color:var(--gray-500);max-width:440px;margin:0 auto 16px;">
                                        We couldn't find any records matching "<strong>${esc(query)}</strong>" in ${esc(catLabel)}.
                                    </p>
                                    ${category !== 'all' ? `<button onclick="GlobalSearch.setCategory('all')" style="padding:8px 16px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-size:12.5px;font-weight:500;cursor:pointer;"><i class="fas fa-globe"></i> Search Whole System</button>` : ''}
                                </div>
                            `;
                            return;
                        }

                        let html = '';

                        // Render categories
                        const catsToRender = (category === 'all')
                            ? Object.keys(data.results || {})
                            : [category];

                        catsToRender.forEach(catKey => {
                            const items = (data.results && data.results[catKey]) ? data.results[catKey] : [];
                            if (!items.length) return;

                            const meta = categoryMeta[catKey] || { name: ucfirst(catKey), icon: 'fa-folder', iconClass: 'icon-document', badgeClass: 'gs-badge-gray' };

                            html += `
                                <div class="gs-section">
                                    <div class="gs-section-title">
                                        <span><i class="fas ${meta.icon}" style="margin-right:6px;color:var(--primary-color);"></i> ${meta.name}</span>
                                        <span class="badge-count">${items.length} item${items.length === 1 ? '' : 's'}</span>
                                    </div>
                                    <div class="gs-items-list">
                            `;

                            items.forEach(item => {
                                const flatIndex = currentFlatResults.length;
                                currentFlatResults.push(item);

                                const badgeColorClass = item.badge_class ? item.badge_class.replace('badge-', 'gs-badge-') : meta.badgeClass;

                                html += `
                                    <a href="${esc(item.url)}" class="gs-item-card" data-index="${flatIndex}">
                                        <div class="gs-item-icon ${meta.iconClass}">
                                            <i class="fas ${meta.icon}"></i>
                                        </div>
                                        <div class="gs-item-content">
                                            <div class="gs-item-header">
                                                <span class="gs-item-title">${highlightText(item.title, query)}</span>
                                                ${item.badge ? `<span class="gs-item-badge ${badgeColorClass}">${esc(item.badge)}</span>` : ''}
                                            </div>
                                            <div class="gs-item-sub">${highlightText(item.subtitle, query)}</div>
                                        </div>
                                        <div class="gs-item-action">
                                            ${esc(item.action_label || 'Open')} <i class="fas fa-arrow-right" style="font-size:11px;"></i>
                                        </div>
                                    </a>
                                `;
                            });

                            html += `
                                    </div>
                                </div>
                            `;
                        });

                        modalResults.innerHTML = html;
                    }

                    // Live Header quick dropdown search
                    async function executeHeaderDropdownSearch(query) {
                        if (!headerDropdown || !headerDropdownResults) return;
                        headerDropdown.style.display = 'flex';
                        headerDropdownResults.innerHTML = '<div style="padding:20px;text-align:center;color:var(--gray-400);font-size:12px;"><i class="fas fa-spinner fa-spin"></i> Searching...</div>';

                        try {
                            const resp = await fetch('api.php?endpoint=global-search&q=' + encodeURIComponent(query) + '&category=all&limit=3');
                            const json = await resp.json();

                            if (!json.success || json.total_count === 0) {
                                headerDropdownResults.innerHTML = `
                                    <div style="padding:24px 16px;text-align:center;color:var(--gray-500);font-size:12.5px;">
                                        <i class="fas fa-search" style="color:var(--gray-400);font-size:18px;margin-bottom:6px;display:block;"></i>
                                        No matches found for "<strong>${esc(query)}</strong>"
                                    </div>
                                `;
                                return;
                            }

                            let html = '';
                            let rendered = 0;
                            const maxItems = 7;

                            for (const [catKey, items] of Object.entries(json.results || {})) {
                                if (!items || !items.length) continue;
                                const meta = categoryMeta[catKey] || { name: ucfirst(catKey), icon: 'fa-folder', iconClass: 'icon-document' };

                                items.forEach(item => {
                                    if (rendered >= maxItems) return;
                                    rendered++;
                                    html += `
                                        <a href="${esc(item.url)}" style="display:flex;align-items:center;gap:10px;padding:8px 14px;border-bottom:1px solid var(--gray-100);text-decoration:none;color:inherit;transition:var(--transition);" onmouseover="this.style.background='var(--gray-50)'" onmouseout="this.style.background='white'">
                                            <div class="gs-item-icon ${meta.iconClass}" style="width:30px;height:30px;font-size:13px;border-radius:6px;">
                                                <i class="fas ${meta.icon}"></i>
                                            </div>
                                            <div style="flex:1;min-width:0;">
                                                <div style="font-size:13px;font-weight:600;color:var(--gray-900);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                                    ${highlightText(item.title, query)}
                                                </div>
                                                <div style="font-size:11px;color:var(--gray-500);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                                    ${highlightText(item.subtitle, query)}
                                                </div>
                                            </div>
                                            <span style="font-size:10px;font-weight:600;padding:1px 6px;border-radius:4px;background:var(--gray-100);color:var(--gray-600);white-space:nowrap;">
                                                ${esc(meta.name.split(' ')[0])}
                                            </span>
                                        </a>
                                    `;
                                });
                            }

                            headerDropdownResults.innerHTML = html;
                        } catch (e) {
                            headerDropdownResults.innerHTML = '<div style="padding:16px;text-align:center;color:var(--danger-color);font-size:12px;">Failed to load preview</div>';
                        }
                    }

                    // Modal keyboard navigation (ArrowUp, ArrowDown, Enter)
                    function handleModalKeyNav(e) {
                        const items = modalResults.querySelectorAll('.gs-item-card');
                        if (!items.length) return;

                        if (e.key === 'ArrowDown') {
                            e.preventDefault();
                            activeIndex = (activeIndex + 1) >= items.length ? 0 : (activeIndex + 1);
                            highlightActiveItem(items);
                        } else if (e.key === 'ArrowUp') {
                            e.preventDefault();
                            activeIndex = (activeIndex - 1) < 0 ? (items.length - 1) : (activeIndex - 1);
                            highlightActiveItem(items);
                        } else if (e.key === 'Enter') {
                            e.preventDefault();
                            if (activeIndex >= 0 && items[activeIndex]) {
                                items[activeIndex].click();
                            } else if (currentFlatResults.length > 0) {
                                window.location.href = currentFlatResults[0].url;
                            }
                        }
                    }

                    function highlightActiveItem(items) {
                        items.forEach((it, idx) => {
                            if (idx === activeIndex) {
                                it.classList.add('keyboard-active');
                                it.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                            } else {
                                it.classList.remove('keyboard-active');
                            }
                        });
                    }

                    function ucfirst(str) {
                        if (!str) return '';
                        return str.charAt(0).toUpperCase() + str.slice(1);
                    }

                    // Auto initialize when DOM is ready
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', init);
                    } else {
                        init();
                    }

                    return {
                        openModal,
                        openModalWithHeaderQuery,
                        closeModal,
                        clearInput,
                        setCategory
                    };
                })();
                <?php else: ?>
                const GlobalSearch = {
                    openModal: function() {},
                    openModalWithHeaderQuery: function() {},
                    closeModal: function() {},
                    clearInput: function() {},
                    setCategory: function() {}
                };
                <?php endif; ?>
            </script>
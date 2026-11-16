<?php
// dashboard.php — picks the dashboard body for the logged-in role. Each body is its own file, loaded only for that role.
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/dash_metrics.php';   // dx_metric() + dx_* helpers (dash_ui.php) for the dashboards below

$firstName = $_SESSION['user_data']['first_name'] ?? $userData['first_name'] ?? 'User';
$today = date('l, F j, Y');
$greeting = (date('H') < 12) ? 'Good Morning' : ((date('H') < 17) ? 'Good Afternoon' : 'Good Evening');

// Role flags (userHasRole() is true for admins on every role, so admin is tested first).
$isAdm   = !empty($isAdmin);
$isDoc   = !$isAdm && userHasRole('doctor');
$isNurse = !$isAdm && !$isDoc && userHasRole('nurse');

if ($isAdm || $isDoc || $isNurse) {
    require __DIR__ . '/includes/dashboard_clinical.php';            // admin also gets the extra money/visit graphs below it
    if ($isAdm) { require_once __DIR__ . '/includes/dash_ui.php'; require __DIR__ . '/includes/dash/admin_extra.php'; }
} else {
    // role slug => dashboard file in includes/dash/. 'cashier' has no role yet; receptionists collect money, so they use it too.
    $dashByRole = [
        'managing_director' => 'managing_director', 'clinical_director' => 'clinical_director', 'accountant' => 'accountant',
        'cashier' => 'cashier', 'receptionist' => 'cashier', 'storekeeper' => 'storekeeper', 'pharmacist' => 'pharmacist',
        'lab_tech' => 'lab', 'procurement' => 'procurement', 'hr_manager' => 'hr',
    ];
    $dashFile = null;
    foreach ($dashByRole as $slug => $file) if (userHasRole($slug)) { $dashFile = $file; break; }
    require_once __DIR__ . '/includes/dash_ui.php';
    if ($dashFile && is_file(__DIR__ . "/includes/dash/$dashFile.php")) {
        require __DIR__ . "/includes/dash/$dashFile.php";
    } else {   // a role without its own dashboard
        echo dx_head($greeting . ', ' . $firstName, $today) . '<div class="dx-panel"><div class="dx-empty">Use the menu on the left to open your work.</div></div>' . dx_end();
    }
}
require_once __DIR__ . '/includes/footer.php';

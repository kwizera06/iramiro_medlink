<?php
// api.php
@ini_set('pcre.jit', 0);
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

require_once __DIR__ . "/includes/session.php"; app_session_start();
header('Content-Type: application/json');

// Database connection
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/finance_schema.php'; // fin_private_ar_acct()

// Audit trail: every successful write gets a generic activity_log row unless the handler logged one itself.
// Only the user, verb, endpoint and ids are stored (never request bodies: passwords, patient data).
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    require_once __DIR__ . '/includes/activity_log.php';
    function al_generic_log(PDO $pdo, string $body): void {
        global $endpoint, $method;
        if (!empty($GLOBALS['__al_logged']) || empty($_SESSION['user_id'])) return;
        if ($endpoint === '' || strpos($endpoint, 'auth/') === 0 || in_array($endpoint, ['notifications', 'activity-log'], true)) return;
        if (http_response_code() === 403) {
            $msg403 = (json_decode($body, true)['message'] ?? 'Forbidden');
            log_activity($pdo, 'DENIED', explode('/', $endpoint)[0], substr("Blocked $method $endpoint" . (isset($_GET['action']) ? ' action=' . $_GET['action'] : '') . ' - ' . $msg403, 0, 500));
            return;
        }
        if (http_response_code() >= 400) return;
        $res = json_decode($body, true);
        if (!is_array($res) || empty($res['success'])) return;
        $in = json_decode((string)file_get_contents('php://input'), true);
        $act = (string)($_GET['action'] ?? (is_array($in) ? ($in['action'] ?? '') : ''));
        if (preg_match('/^(get|list|search|find|view|fetch|check|load|preview|lookup|calc|export|count)/i', $act)) return;
        $verb = $method === 'DELETE' || preg_match('/delete|remove|void|cancel/i', $act) ? 'DELETE'
              : ($method === 'PUT' || preg_match('/update|edit|change|set_|assign|approve|reject|revoke/i', $act) ? 'UPDATE' : 'CREATE');
        $ids = [];
        foreach (array_merge($_GET, is_array($in) ? $in : []) as $k => $v) {
            if (($k === 'id' || substr((string)$k, -3) === '_id') && is_scalar($v) && $v !== '' && count($ids) < 6) $ids[$k] = $v;
        }
        if (isset($res['data']['id']) && is_scalar($res['data']['id'])) $ids['new_id'] = $res['data']['id'];
        $first = explode('/', $endpoint)[0];
        $module = in_array($first, ['deletion-requests', 'edit-requests'], true) ? 'deletions' : $first;
        $desc = "$method $endpoint" . ($act !== '' ? " action=$act" : '')
              . ($ids ? ' (' . implode(', ', array_map(fn($k, $v) => "$k=$v", array_keys($ids), $ids)) . ')' : '');
        $rid = isset($ids['id']) ? (int)$ids['id'] : (isset($ids['new_id']) ? (int)$ids['new_id'] : null);
        log_activity($pdo, $verb, $module, substr($desc, 0, 500), $rid);
    }
    ob_start(); // keep the response so the shutdown hook can inspect it before it is flushed
    register_shutdown_function(function () use ($pdo) {
        $alBody = ob_get_level() ? (string)ob_get_contents() : '';
        if ($pdo->inTransaction()) return; // request died mid-transaction
        try { al_generic_log($pdo, $alBody); } catch (Throwable $e) { /* logging never breaks a request */ }
    });
}

$method = $_SERVER['REQUEST_METHOD'];
$endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : '';

// Every endpoint except login/session checks requires a logged-in user
$publicEndpoints = ['auth/login', 'auth/logout', 'auth/register', 'auth/check', 'auth/check-block-status'];
if (!in_array($endpoint, $publicEndpoints, true) && empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated. Please log in again.']);
    exit();
}

// Doctors cannot read or change visits assigned to another doctor
require_once __DIR__ . '/includes/auth.php';
$jsonBody = json_decode(file_get_contents('php://input'), true);
$reqVisitId = (int)($_GET['visit_id'] ?? $_POST['visit_id'] ?? (is_array($jsonBody) ? ($jsonBody['visit_id'] ?? 0) : 0));
if (!$reqVisitId && $endpoint === 'patients/visits') $reqVisitId = (int)($_GET['id'] ?? 0);
if ($reqVisitId && !canAccessVisit($pdo, $reqVisitId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'This patient is assigned to another doctor.']);
    exit();
}

if ($method !== 'GET') {
    // CSRF: browsers send Origin (or at least Referer) on POST/PUT/DELETE — refuse writes started by another site
    $from = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
    $fromHost = $from !== '' ? strtolower((string)parse_url($from, PHP_URL_HOST)) : '';
    $ownHost = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
    if ($from !== '' && $fromHost !== $ownHost) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Request refused: it did not come from this application.']);
        exit();
    }

    // Finance and stock writes need the matching page in Roles & Access
    $reqAction = (string)($_GET['action'] ?? (is_array($jsonBody) ? ($jsonBody['action'] ?? '') : ''));
    $writePages = apiWritePages($endpoint, $reqAction);
    if ($writePages && !canUsePage($pdo, ...$writePages)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission for this action. Ask an administrator to grant it in Roles & Access.']);
        exit();
    }

    // Per-person add/edit/delete limits on the current page (Settings → Roles → Individual Role Management)
    require_once __DIR__ . '/includes/page_perms.php';
    pp_enforce($pdo, $endpoint, $method);
}

$routeMap = [
    'POST' => [
        'auth/login'    => 'auth/login.php',
        'auth/register' => 'auth/register.php',
        'auth/logout'   => 'auth/logout.php',
        // Insurance
        'insurance/companies'      => 'insurance/companies.php',
        'insurance/prices'         => 'insurance/prices.php',
        'insurance/coverage-types' => 'insurance/coverage-types.php',
        'insurance/packages'       => 'insurance/packages.php',
        'insurance/settlement'     => 'insurance/settlement.php',
        // Patients
        'patients/patients'        => 'patients/patients.php',
        'patients/visits'          => 'patients/visits.php',
        'procedures/procedures'      => 'procedures/procedures.php',
        'patients/billing'         => 'patients/billing.php',
        // Finance
        'finance/chart-of-accounts' => 'finance/chart-of-accounts.php',
        'finance/invoices'          => 'finance/invoices.php',
        // HR
        'hr/employees'             => 'hr/employees.php',
        'hr/payroll'               => 'hr/payroll.php',
        'hr/documents'             => 'hr/documents.php',
        'hr/departments'           => 'hr/departments.php',
        'hr/attendance'            => 'hr/attendance.php',
        'hr/permissions'           => 'hr/permissions.php',
        'hr/leave'                 => 'hr/leave.php',
        'hr/performance'           => 'hr/performance.php',
        'hr/licenses'              => 'hr/licenses.php',
        'hr/contracts'             => 'hr/contracts.php',
        'hr/shifts'                => 'hr/shifts.php',
        'hr/hr-enhanced'           => 'hr/hr_enhanced.php',
        // Settings
        'settings/users'           => 'settings/users.php',
        'settings/roles'           => 'settings/roles.php',
        'settings/menus'           => 'settings/menus.php',
        'settings/vital-signs'     => 'settings/vital-signs.php',
        'settings/hospital'        => 'settings/hospital.php',
        'service-catalog'          => 'settings/service-catalog.php',
        // Departments
        'departments/department-forms' => 'departments/department-forms.php',
        'documents/medical-documents' => 'documents/medical-documents.php',
        'departments/portal' => 'departments/portal.php',
        // Consultation
        'consultation/consult'       => 'consultation/consult.php',
        'consultation/queue'         => 'consultation/queue.php',
        'consultation/diagnoses'     => 'consultation/diagnoses.php',
        'appointments'                => 'appointments/appointments.php',
        // Ward Management
        'ward/ward-management'       => 'ward/ward-management.php',
        'workflow/workflow'          => 'workflow/workflow.php',
        'inventory/inventory-management' => 'inventory/inventory-management.php',
        'payments/afrirem' => 'payments/afrirem.php',
        'reports/general' => 'reports/general.php',
        'reports/hmis'    => 'reports/hmis.php',
        'reports/hmis-dx-map' => 'reports/hmis-dx-map.php',
        'requisitions' => 'requisitions/requisitions.php',
        // Pharmacy
        'pharmacy/items'              => 'pharmacy/pharmacy.php',
        'pharmacy/stock-request'      => 'pharmacy/pharmacy.php',
        'pharmacy/stock-receive'      => 'pharmacy/pharmacy.php',
        'pharmacy/stock-movements'    => 'pharmacy/pharmacy.php',
        'pharmacy/prescriptions'      => 'pharmacy/pharmacy.php',
        'pharmacy/pos'                => 'pharmacy/pharmacy.php',
        'pharmacy/dashboard'          => 'pharmacy/pharmacy.php',
        // Notifications
        'notifications'               => 'notifications/notifications.php',
        // System activity log
        'activity-log'                => 'activity-log.php',
        // Deletion Requests
        'deletion-requests'           => 'deletion-requests.php',
        // Edit Requests
        'edit-requests'               => 'edit-requests.php',
        // Documents
        'documents'                   => 'documents.php',
        // Expense Management
        'expenses/management'         => 'expenses/expense-management.php',
        // Requests & Requisitions / Procurement and Supplier
        'requests/management'         => 'requests/request-management.php',
        'procurement/procurement-ops' => 'procurement/procurement-ops.php',
        'procurement/procurement-ext' => 'procurement/procurement-ext.php',
    ],
    'GET' => [
        'auth/check'              => 'auth/checkAuth.php',
        'auth/profile'            => 'auth/getUserProfile.php',
        'auth/check-block-status' => 'auth/check-block-status.php',
        'settings/users/user-permissions' => 'settings/user-permissions.php',
        // Location data (cascading)
        'patients/locations'       => 'patients/locations.php',
        // Insurance
        'insurance/companies'      => 'insurance/companies.php',
        'insurance/prices'         => 'insurance/prices.php',
        'insurance/coverage-types' => 'insurance/coverage-types.php',
        'insurance/packages'       => 'insurance/packages.php',
        'insurance/settlement'     => 'insurance/settlement.php',
        // Patients
        'patients/patients'        => 'patients/patients.php',
        'patients/visits'          => 'patients/visits.php',
        'procedures/procedures'      => 'procedures/procedures.php',
        'patients/billing'         => 'patients/billing.php',
        // Finance
        'finance/chart-of-accounts' => 'finance/chart-of-accounts.php',
        'finance/invoices'          => 'finance/invoices.php',
        // HR
        'hr/employees'             => 'hr/employees.php',
        'hr/payroll'               => 'hr/payroll.php',
        'hr/documents'             => 'hr/documents.php',
        'hr/departments'           => 'hr/departments.php',
        'hr/attendance'            => 'hr/attendance.php',
        'hr/permissions'           => 'hr/permissions.php',
        'hr/leave'                 => 'hr/leave.php',
        'hr/performance'           => 'hr/performance.php',
        'hr/licenses'              => 'hr/licenses.php',
        'hr/contracts'             => 'hr/contracts.php',
        'hr/shifts'                => 'hr/shifts.php',
        'hr/hr-enhanced'           => 'hr/hr_enhanced.php',
        // Settings  
        'settings/users'           => 'settings/users.php',
        'settings/roles'           => 'settings/roles.php',
        'settings/menus'           => 'settings/menus.php',
        'settings/vital-signs'     => 'settings/vital-signs.php',
        'settings/hospital'        => 'settings/hospital.php',
        'service-catalog'          => 'settings/service-catalog.php',
        // Departments
        'departments/department-forms' => 'departments/department-forms.php',
        'documents/medical-documents' => 'documents/medical-documents.php',
        'departments/portal' => 'departments/portal.php',
        'global-search'      => 'global-search.php',
        // Consultation
        'consultation/consult'       => 'consultation/consult.php',
        'consultation/queue'         => 'consultation/queue.php',
        'consultation/diagnoses'     => 'consultation/diagnoses.php',
        'appointments'                => 'appointments/appointments.php',
        // Ward Management
        'ward/ward-management'       => 'ward/ward-management.php',
        'workflow/workflow'          => 'workflow/workflow.php',
        'inventory/inventory-management' => 'inventory/inventory-management.php',
        'payments/afrirem' => 'payments/afrirem.php',
        'reports/general' => 'reports/general.php',
           'reports/hmis'    => 'reports/hmis.php',
        'reports/hmis-dx-map' => 'reports/hmis-dx-map.php',
        'requisitions' => 'requisitions/requisitions.php',
        // Pharmacy
        'pharmacy/items'              => 'pharmacy/pharmacy.php',
        'pharmacy/stock-request'      => 'pharmacy/pharmacy.php',
        'pharmacy/stock-receive'      => 'pharmacy/pharmacy.php',
        'pharmacy/stock-movements'    => 'pharmacy/pharmacy.php',
        'pharmacy/prescriptions'      => 'pharmacy/pharmacy.php',
        'pharmacy/pos'                => 'pharmacy/pharmacy.php',
        'pharmacy/dashboard'          => 'pharmacy/pharmacy.php',
        // Notifications
        'notifications'               => 'notifications/notifications.php',
        // System activity log
        'activity-log'                => 'activity-log.php',
        // Deletion Requests
        'deletion-requests'           => 'deletion-requests.php',
        // Edit Requests
        'edit-requests'               => 'edit-requests.php',
        // Documents
        'documents'                   => 'documents.php',
        // Expense Management
        'expenses/management'         => 'expenses/expense-management.php',
        // Requests & Requisitions / Procurement and Supplier
        'requests/management'         => 'requests/request-management.php',
        'procurement/procurement-ops' => 'procurement/procurement-ops.php',
        'procurement/procurement-ext' => 'procurement/procurement-ext.php',
    ],
    'PUT' => [
        // Deletion Requests
        'deletion-requests'        => 'deletion-requests.php',
        // Documents
        'documents'                => 'documents.php',
        // Insurance
        'insurance/companies'      => 'insurance/companies.php',
        'insurance/coverage-types' => 'insurance/coverage-types.php',
        'insurance/packages'       => 'insurance/packages.php',
        'insurance/settlement'     => 'insurance/settlement.php',
        // Patients
        'patients/patients'        => 'patients/patients.php',
        'patients/visits'          => 'patients/visits.php',
        'procedures/procedures'      => 'procedures/procedures.php',
        'patients/billing'         => 'patients/billing.php',
        // Finance
        'finance/chart-of-accounts' => 'finance/chart-of-accounts.php',
        // HR
        'hr/employees'             => 'hr/employees.php',
        'hr/departments'           => 'hr/departments.php',
        'hr/payroll'               => 'hr/payroll.php',
        'hr/attendance'            => 'hr/attendance.php',
        'hr/permissions'           => 'hr/permissions.php',
        'hr/leave'                 => 'hr/leave.php',
        'hr/performance'           => 'hr/performance.php',
        'hr/licenses'              => 'hr/licenses.php',
        'hr/contracts'             => 'hr/contracts.php',
        'hr/shifts'                => 'hr/shifts.php',
        'hr/hr-enhanced'           => 'hr/hr_enhanced.php',
        // Settings
        'settings/users'           => 'settings/users.php',
        'settings/roles'           => 'settings/roles.php',
        'settings/menus'           => 'settings/menus.php',
        'settings/vital-signs'     => 'settings/vital-signs.php',
        'settings/hospital'        => 'settings/hospital.php',
        'service-catalog'          => 'settings/service-catalog.php',
        // Departments
        'departments/department-forms' => 'departments/department-forms.php',
        'documents/medical-documents' => 'documents/medical-documents.php',
        // Consultation
        'consultation/consult'       => 'consultation/consult.php',
        'consultation/queue'         => 'consultation/queue.php',
        'consultation/diagnoses'     => 'consultation/diagnoses.php',
        'appointments'                => 'appointments/appointments.php',
        // Ward Management
        'ward/ward-management'       => 'ward/ward-management.php',
        'inventory/inventory-management' => 'inventory/inventory-management.php',
        'payments/afrirem' => 'payments/afrirem.php',
        'reports/general' => 'reports/general.php',
        'reports/hmis'    => 'reports/hmis.php',
        'reports/hmis-dx-map' => 'reports/hmis-dx-map.php',
        'requisitions' => 'requisitions/requisitions.php',
        // Pharmacy
        'pharmacy/items'              => 'pharmacy/pharmacy.php',
        'pharmacy/stock-request'      => 'pharmacy/pharmacy.php',
        'pharmacy/stock-receive'      => 'pharmacy/pharmacy.php',
        'pharmacy/stock-movements'    => 'pharmacy/pharmacy.php',
        'pharmacy/prescriptions'      => 'pharmacy/pharmacy.php',
        'pharmacy/pos'                => 'pharmacy/pharmacy.php',
        'pharmacy/dashboard'          => 'pharmacy/pharmacy.php',
    ],
    'DELETE' => [
        // Deletion Requests
        'deletion-requests'        => 'deletion-requests.php',
        // Documents
        'documents'                => 'documents.php',
        // Insurance
        'insurance/companies'      => 'insurance/companies.php',
        'insurance/coverage-types' => 'insurance/coverage-types.php',
        'insurance/packages'       => 'insurance/packages.php',
        'insurance/settlement'     => 'insurance/settlement.php',
        // Patients
        'patients/patients'        => 'patients/patients.php',
        'patients/visits'          => 'patients/visits.php',
        'procedures/procedures'      => 'procedures/procedures.php',
        'patients/billing'         => 'patients/billing.php',
        // Finance
        'finance/chart-of-accounts' => 'finance/chart-of-accounts.php',
        // HR
        'hr/employees'             => 'hr/employees.php',
        'hr/departments'           => 'hr/departments.php',
        'hr/payroll'               => 'hr/payroll.php',
        'hr/documents'             => 'hr/documents.php',
        'hr/attendance'            => 'hr/attendance.php',
        'hr/permissions'           => 'hr/permissions.php',
        'hr/leave'                 => 'hr/leave.php',
        'hr/performance'           => 'hr/performance.php',
        'hr/licenses'              => 'hr/licenses.php',
        'hr/contracts'             => 'hr/contracts.php',
        'hr/shifts'                => 'hr/shifts.php',
        'hr/hr-enhanced'           => 'hr/hr_enhanced.php',
        // Settings
        'settings/users'           => 'settings/users.php',
        'settings/roles'           => 'settings/roles.php',
        'settings/menus'           => 'settings/menus.php',
        'settings/vital-signs'     => 'settings/vital-signs.php',
        'settings/hospital'        => 'settings/hospital.php',
        'service-catalog'          => 'settings/service-catalog.php',
        // Departments
        'departments/department-forms' => 'departments/department-forms.php',
        'documents/medical-documents' => 'documents/medical-documents.php',
        // Ward Management
        'ward/ward-management'       => 'ward/ward-management.php',
        'inventory/inventory-management' => 'inventory/inventory-management.php',
        'payments/afrirem' => 'payments/afrirem.php',
        'reports/general' => 'reports/general.php',
        'reports/hmis'    => 'reports/hmis.php',
        'reports/hmis-dx-map' => 'reports/hmis-dx-map.php',
        'requisitions' => 'requisitions/requisitions.php',
        // Pharmacy
        'pharmacy/items'              => 'pharmacy/pharmacy.php',
        'pharmacy/stock-request'      => 'pharmacy/pharmacy.php',
        'pharmacy/stock-receive'      => 'pharmacy/pharmacy.php',
        'pharmacy/stock-movements'    => 'pharmacy/pharmacy.php',
        'pharmacy/prescriptions'      => 'pharmacy/pharmacy.php',
        'pharmacy/pos'                => 'pharmacy/pharmacy.php',
        'pharmacy/dashboard'          => 'pharmacy/pharmacy.php',
    ]
];

// Assets Management (transfers, assignments, maintenance, disposals)
foreach (['GET', 'POST'] as $m) $routeMap[$m]['assets/asset-management'] = 'assets/asset-management.php';
// Inventory v2 (items/stock/movements API used by the newer workspace screens)
foreach (['GET', 'POST', 'PUT', 'DELETE'] as $m) $routeMap[$m]['inventory/inventory-management-v2'] = 'inventory/inventory-management-v2.php';
// Ported from Michel: approvals inbox, dedicated lab API, staff shift sessions
foreach (['GET', 'POST'] as $m) $routeMap[$m]['approvals/approvals'] = 'approvals/approvals.php';
foreach (['GET', 'POST', 'PUT', 'DELETE'] as $m) $routeMap[$m]['lab/lab'] = 'lab/lab.php';
foreach (['GET', 'POST'] as $m) $routeMap[$m]['patients/attachments'] = 'patients/attachments.php';
$routeMap['GET']['settings/user-sessions'] = 'settings/user-sessions.php';
foreach (['GET', 'POST', 'PUT', 'DELETE'] as $m) $routeMap[$m]['finance/finance-ops'] = 'finance/finance-ops.php';
$routeMap['GET']['consultation/hosp-invoicing'] = 'consultation/hosp-invoicing.php';
foreach (['GET', 'POST'] as $m) { $routeMap[$m]['finance/finance-close'] = 'finance/finance-close.php'; $routeMap[$m]['finance/receivables'] = 'finance/receivables.php'; $routeMap[$m]['hr/my-attendance'] = 'hr/attendance.php'; }

// Check if endpoint exists
if (isset($routeMap[$method]) && isset($routeMap[$method][$endpoint])) {
    $handlerFile = __DIR__ . '/handlers/' . $routeMap[$method][$endpoint];
    
    if (file_exists($handlerFile)) {
        // Handlers ported from the reference installation start with this guard.
        if (!defined('MEDLINK_API')) define('MEDLINK_API', true);
        require_once $handlerFile;
        exit();
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Handler not found: ' . $handlerFile
        ]);
        exit();
    }
}

// If endpoint not found
http_response_code(404);
echo json_encode([
    'success' => false,
    'message' => 'Endpoint not found',
    'method' => $method,
    'endpoint' => $endpoint
]);
exit();
?>

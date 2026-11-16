<?php
// includes/auth.php — shared server-side role checks for API handlers

if (!function_exists('isUserAdmin')) {
function isUserAdmin(PDO $pdo, ?int $userId = null): bool {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    if (!$userId && isset($_SESSION['user_id'])) {
        $userId = (int)$_SESSION['user_id'];
    }

    $adminRoles = ['admin', 'administrator', 'isadmin'];

    // Check is_admin boolean / truthy flag
    if (!empty($_SESSION['user_data']['is_admin']) || !empty($_SESSION['is_admin'])) {
        return true;
    }

    // Check role slugs in session
    if (isset($_SESSION['user_data']['role_slugs']) && is_array($_SESSION['user_data']['role_slugs'])) {
        foreach ($_SESSION['user_data']['role_slugs'] as $slug) {
            if (in_array(strtolower(trim($slug)), $adminRoles, true)) {
                return true;
            }
        }
    }

    // Check role names in session
    if (isset($_SESSION['user_data']['role_names']) && is_array($_SESSION['user_data']['role_names'])) {
        foreach ($_SESSION['user_data']['role_names'] as $name) {
            if (in_array(strtolower(trim($name)), $adminRoles, true)) {
                return true;
            }
        }
    }

    // Check single role in session
    if (isset($_SESSION['user_data']['role']) && in_array(strtolower(trim($_SESSION['user_data']['role'])), $adminRoles, true)) {
        return true;
    }
    if (isset($_SESSION['role_name']) && in_array(strtolower(trim($_SESSION['role_name'])), $adminRoles, true)) {
        return true;
    }
    if (isset($_SESSION['role']) && in_array(strtolower(trim($_SESSION['role'])), $adminRoles, true)) {
        return true;
    }

    if (!$userId) return false;
    
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

        $stmt2 = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt2->execute([$userId]);
        $uRole = strtolower(trim((string)$stmt2->fetchColumn()));
        if (in_array($uRole, $adminRoles, true)) {
            return true;
        }
    } catch(Exception $e) {}

    return false;
}
}

// Finance personnel (accountant role) or administrators — the only users who may clear or write off patient debts
if (!function_exists('isFinanceUser')) {
function isFinanceUser(PDO $pdo, ?int $userId = null): bool {
    if (isUserAdmin($pdo, $userId)) return true;
    $userId = $userId ?: (int)($_SESSION['user_id'] ?? 0);
    if (!$userId) return false;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? AND LOWER(r.slug) IN ('accountant','finance')");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn() > 0;
}
}

// True when Roles & Access gives the user a sidebar page starting with one of these URLs (by role or personal grant).
// Admins always pass. Example: canUsePage($pdo, 'finance.php?module=finance&sub=journal-entry')
if (!function_exists('canUsePage')) {
function canUsePage(PDO $pdo, string ...$urlPrefixes): bool {
    if (isUserAdmin($pdo)) return true;
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if (!$userId || !$urlPrefixes) return false;
    $match = implode(' OR ', array_fill(0, count($urlPrefixes), "LEFT(TRIM(LEADING '/' FROM i.url), CHAR_LENGTH(?)) = ?"));
    $params = [];
    foreach ($urlPrefixes as $p) { $params[] = $p; $params[] = $p; }
    $params[] = $userId;
    $params[] = $userId;
    $stmt = $pdo->prepare("
        SELECT 1 FROM nav_menu_items i
        WHERE i.is_active = 1 AND ($match)
          AND (EXISTS (SELECT 1 FROM role_menu_access rma JOIN user_roles ur ON ur.role_id = rma.role_id WHERE rma.menu_item_id = i.id AND ur.user_id = ?)
               OR EXISTS (SELECT 1 FROM user_menu_access uma WHERE uma.menu_item_id = i.id AND uma.user_id = ?))
        LIMIT 1
    ");
    $stmt->execute($params);
    return (bool)$stmt->fetchColumn();
}
}

// Pages that allow a write (POST/PUT/DELETE) to a finance or stock endpoint — the user needs any one of them.
// [] = no page check for this endpoint/action.
if (!function_exists('apiWritePages')) {
function apiWritePages(string $endpoint, string $action): array {
    if ($endpoint === 'finance/chart-of-accounts') {
        if (strpos($action, 'assets') === 0) return ['assets.php'];
        if ($action === 'create_journal_entry') return ['finance.php?module=finance&sub=journal-entry', 'inventory-opening.php'];
        if (strpos($action, 'journal_entry') !== false) return ['finance.php?module=finance&sub=journal-entry'];
        return ['finance.php?module=finance&sub=chart-of-accounts'];
    }
    if ($endpoint === 'assets/asset-management') return ['assets.php'];
    if ($endpoint === 'insurance/settlement') return ['insurance.php?module=insurance&sub=receivables'];
    if ($endpoint === 'insurance/prices') return ['insurance.php?module=insurance&sub=insurance-prices'];
    if ($endpoint === 'payments/afrirem') return ['invoicing.php'];
    if ($endpoint === 'procedures/procedures' && $action === 'nurse_use') return ['nursing.php', 'ward.php', 'procedures.php'];
    if (strpos($endpoint, 'pharmacy/') === 0) {
        // Doctors write prescriptions from their own pages; everything else (dispense, POS, stock) is pharmacy work
        if ($endpoint === 'pharmacy/prescriptions' && $action !== 'dispense') return [];
        return ['pharmacy.php?module=pharmacy&sub=dispensing'];
    }
    if ($endpoint === 'inventory/inventory-management') {
        if (strpos($action, 'ws_') === 0) return ['inventory-workspace.php', 'inventory.php'];
        if ($action === 'create_item_opening') return ['inventory-opening.php'];
        if (in_array($action, ['create_purchase_order', 'update_po_status', 'create_supplier'], true)) return ['procurement.php', 'inventory.php'];
        if ($action === 'write_off_expired') return ['inventory.php?module=inventory&sub=expired'];
        return ['inventory.php'];
    }
    if (strpos($endpoint, 'requests/') === 0) return ['requests.php', 'procurement.php', 'dashboard.php'];
    if (strpos($endpoint, 'procurement/') === 0) return ['procurement.php', 'inventory.php'];
    if ($endpoint === 'requisitions') return ['requisitions.php', 'internal-requisition.php', 'requests.php', 'procurement.php'];
    return [];
}
}

// Nurses and administrators may change or remove the department/doctor a patient was sent to
if (!function_exists('canRerouteVisits')) {
function canRerouteVisits(PDO $pdo): bool {
    if (isUserAdmin($pdo)) return true;
    $slugs = array_map('strtolower', (array)($_SESSION['user_data']['role_slugs'] ?? []));
    $slugs[] = strtolower((string)($_SESSION['user_data']['role'] ?? ''));
    return in_array('nurse', $slugs, true);
}
}

// Doctors may only open visits they are involved in (assigned, referred by, or consulting) or that nobody is assigned to yet.
// Admins and non-doctor staff (nurses, lab, pharmacy, cashiers) keep full access.
if (!function_exists('canAccessVisit')) {
function canAccessVisit(PDO $pdo, int $visitId): bool {
    if (!$visitId || isUserAdmin($pdo)) return true;
    $slugs = array_map('strtolower', (array)($_SESSION['user_data']['role_slugs'] ?? []));
    if (!in_array('doctor', $slugs, true)) return true;
    $me = (int)($_SESSION['user_id'] ?? 0);

    // "mine" also covers any visit sent to the doctor's own department: every doctor there can take over
    // a colleague's patient (the assigned doctor may be called away)
    $stmt = $pdo->prepare("
        SELECT
          (SELECT COUNT(*) FROM visit_department_history WHERE visit_id = ? AND status <> 'cancelled'
             AND (assigned_to = ? OR assigned_by = ?
                  OR department_id = (SELECT department_id FROM users WHERE id = ?)))
        + (SELECT COUNT(*) FROM consultations WHERE visit_id = ? AND doctor_id = ?) AS mine,
          (SELECT COUNT(*) FROM visit_department_history WHERE visit_id = ? AND status <> 'cancelled' AND assigned_to IS NOT NULL)
        + (SELECT COUNT(*) FROM consultations WHERE visit_id = ? AND doctor_id IS NOT NULL AND doctor_id > 0) AS assigned
    ");
    $stmt->execute([$visitId, $me, $me, $me, $visitId, $me, $visitId, $visitId]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    return $r['mine'] > 0 || $r['assigned'] == 0;
}
}

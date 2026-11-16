<?php
// includes/page_guard.php — SEC-02
// Deny-by-default page authorization. A page is reachable only when the user's
// role (or per-user override) grants a matching nav_menu_item. Admins bypass.
// Pages with no menu item are denied unless they are explicitly allowed or are
// derived from a page the user can already open (e.g. consultation.php).

function pg_is_admin(PDO $pdo, int $uid): bool {
    static $cache = [];
    if ($uid <= 0) return false;
    if (array_key_exists($uid, $cache)) return $cache[$uid];
    $q = $pdo->prepare("SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id
                        WHERE ur.user_id = ? AND r.slug = 'admin' LIMIT 1");
    $q->execute([$uid]);
    return $cache[$uid] = (bool)$q->fetchColumn();
}

function pg_user_menu_ids(PDO $pdo, int $uid): array {
    static $cache = [];
    if ($uid <= 0) return [];
    if (isset($cache[$uid])) return $cache[$uid];
    $q = $pdo->prepare("SELECT DISTINCT menu_item_id FROM role_menu_access
                        WHERE role_id IN (SELECT role_id FROM user_roles WHERE user_id = ?)
                        UNION
                        SELECT menu_item_id FROM user_menu_access WHERE user_id = ?");
    $q->execute([$uid, $uid]);
    return $cache[$uid] = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
}

function pg_default_sub(string $script): string {
    static $map = [
        'assets.php' => 'register',
        'departments.php' => 'opd',
        'finance.php' => 'chart-of-accounts',
        'hr.php' => 'employees',
        'insurance.php' => 'companies',
        'inventory.php' => 'central-store',
        'invoicing.php' => 'invoices',
        'lab.php' => 'test-requests',
        'medical-documents.php' => 'medical-leave',
        'patients.php' => 'manage',
        'pharmacy.php' => 'prescription',
        'procurement.php' => 'approved-requests',
        'report.php' => 'dashboard',
        'reports.php' => 'dashboard',
        'requisitions.php' => 'my-requests',
        'service-catalog.php' => 'catalog',
        'settings.php' => 'users',
    ];
    return $map[$script] ?? '';
}

// Pages that are intentionally available to every authenticated user.
function pg_public_pages(): array {
    return ['dashboard.php', 'index.php', 'profile.php', 'login.php', 'logout.php', 'verify-document.php'];
}

// Pages that inherit access from a parent page (e.g. the consultation workflow
// is opened from the doctor's department queue).
function pg_derived_pages(): array {
    return [
        'consultation.php' => ['department.php'],
        'documents.php' => ['medical-documents.php', 'patients.php'],
        'departments.php' => ['settings.php'],
        'activity-log.php' => ['systemlogs.php', 'settings.php'],
        'afriremlogs.php' => ['settings.php'],
        'financelogs.php' => ['finance.php'],
        'hrlogs.php' => ['hr.php'],
        'inventorylogs.php' => ['inventory.php'],
        'invoicelogs.php' => ['invoicing.php', 'finance.php'],
        'lablogs.php' => ['lab.php'],
        'pharmacylogs.php' => ['pharmacy.php'],
        'procedurelogs.php' => ['procedures.php'],
        'userlogs.php' => ['settings.php'],
        'visitlogs.php' => ['patients.php'],
        'wardlogs.php' => ['ward.php'],
        'departmentlogs.php' => ['department.php'],
        'consultationlogs.php' => ['department.php'],
        'logdpatient.php' => ['patients.php'],
        'requisitions.php' => ['requests.php', 'procurement.php'],
        'reports.php' => ['report.php'],
    ];
}

function pg_has_any_page_access(PDO $pdo, int $uid, array $scripts): bool {
    if (pg_is_admin($pdo, $uid)) return true;
    $scripts = array_values(array_unique(array_filter(array_map('strval', $scripts))));
    if (!$scripts) return false;
    $ids = pg_user_menu_ids($pdo, $uid);
    if (!$ids) return false;
    $inIds = implode(',', array_fill(0, count($ids), '?'));
    $inScripts = implode(',', array_fill(0, count($scripts), '?'));
    $q = $pdo->prepare("SELECT 1 FROM nav_menu_items
                        WHERE is_active = 1 AND id IN ($inIds)
                          AND SUBSTRING_INDEX(SUBSTRING_INDEX(url, '?', 1), '/', -1) IN ($inScripts)
                        LIMIT 1");
    $q->execute(array_merge($ids, $scripts));
    return (bool)$q->fetchColumn();
}

require_once __DIR__ . '/asset_schema.php';
require_once __DIR__ . '/procurement_schema.php';

function pg_has_page_access(PDO $pdo, int $uid, string $script, string $sub = ''): bool {
    if (pg_is_admin($pdo, $uid)) return true;
    if (pg_has_any_page_access($pdo, $uid, [$script])) {
        // Page-level grant is enough when the page has no sub-modules.
        $ids = pg_user_menu_ids($pdo, $uid);
        if (!$ids) return false;
        $in = implode(',', array_fill(0, count($ids), '?'));
        $q = $pdo->prepare("SELECT url FROM nav_menu_items
                            WHERE is_active = 1 AND id IN ($in) AND url LIKE ?");
        $q->execute(array_merge($ids, [$script . '%']));
        $rows = $q->fetchAll(PDO::FETCH_COLUMN);
        $want = $sub !== '' ? $sub : pg_default_sub($script);
        $hasSubItems = false;
        foreach ($rows as $url) {
            $u = parse_url($url);
            if (basename($u['path'] ?? '') !== $script) continue;
            parse_str($u['query'] ?? '', $q2);
            $isub = (string)($q2['sub'] ?? '');
            if ($isub === '') return true;
            $hasSubItems = true;
            if ($want !== '' && ($isub === $want || ($script === 'assets.php' && am_same_page($isub, $want)) || ($script === 'procurement.php' && pc_same_page($isub, $want)))) return true;   // a page's tabs share its menu grant
        }
        // A sub-page with no explicit request sub: deny rather than default-open.
        return !$hasSubItems;
    }

    $derived = pg_derived_pages();
    if (!empty($derived[$script])) {
        return pg_has_any_page_access($pdo, $uid, $derived[$script]);
    }
    return false;
}

function pg_first_allowed_url(PDO $pdo, int $uid, string $script): ?string {
    if (pg_is_admin($pdo, $uid)) return null;
    $ids = pg_user_menu_ids($pdo, $uid);
    if (!$ids) return null;
    $in = implode(',', array_fill(0, count($ids), '?'));
    $q = $pdo->prepare("SELECT url FROM nav_menu_items
                        WHERE is_active = 1 AND id IN ($in) AND url LIKE ?
                        ORDER BY item_order, id");
    $q->execute(array_merge($ids, [$script . '%']));
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $url) {
        $u = parse_url($url);
        if (basename($u['path'] ?? '') === $script) return $url;
    }
    return null;
}

function pg_enforce_page(PDO $pdo): void {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid <= 0) return; // session_check.php handles the redirect
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($script === '' || in_array($script, pg_public_pages(), true)) return;
    if (pg_is_admin($pdo, $uid)) return;

    $sub = (string)($_GET['sub'] ?? '');
    if (pg_has_page_access($pdo, $uid, $script, $sub)) return;

    // No explicit sub requested: send the user to the first sub they can open
    // instead of showing the page's default (which may be a different module).
    if ($sub === '' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $allowedUrl = pg_first_allowed_url($pdo, $uid, $script);
        if ($allowedUrl) {
            header('Location: ' . $allowedUrl);
            exit;
        }
    }

    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>403 Forbidden</title></head>'
       . '<body style="font-family:system-ui,Segoe UI,sans-serif;padding:40px;max-width:640px;margin:auto">'
       . '<h1 style="font-size:24px">403 — Access denied</h1>'
       . '<p>Your role does not have access to this page.</p>'
       . '<p><a href="dashboard.php">Back to dashboard</a></p></body></html>';
    exit;
}

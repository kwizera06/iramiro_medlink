<?php
// includes/request_schema.php
// Department requests (requests.php), drug reports and the clinic-store lookup. Idempotent: every handler/page that needs it
// calls rm_schema() first, so a fresh database heals itself. DDL commits implicitly: call it before opening a transaction.

/** One role list for the final approval of a department request (handler, page and Approvals inbox). */
const RM_APPROVER_ROLES = ['admin', 'clinical_director', 'managing_director'];

function rm_approver_roles(): array { return RM_APPROVER_ROLES; }

/** Days after which a pending request gets the red badge (hospital_settings.request_reminder_days, default 3). */
function rm_reminder_days(PDO $pdo): int {
    try {
        $v = $pdo->query("SELECT setting_value FROM hospital_settings WHERE setting_key='request_reminder_days' LIMIT 1")->fetchColumn();
    } catch (Throwable $e) { $v = false; }
    return ($v !== false && (int)$v > 0) ? (int)$v : 3;
}

/** The clinic (dispensing) store: setting clinical_store_id, else stores.code='CLINIC', else a store named like "clinic". 0 = none found. */
function clinic_store_id(PDO $pdo): int {
    static $id = null;
    if ($id !== null) return $id;
    $id = 0;
    try {
        $v = $pdo->query("SELECT setting_value FROM hospital_settings WHERE setting_key='clinical_store_id' LIMIT 1")->fetchColumn();
        if ($v && (int)$v > 0) return $id = (int)$v;
        $v = $pdo->query("SELECT id FROM stores WHERE is_active=1 AND (UPPER(code)='CLINIC' OR name LIKE '%clinic%') ORDER BY (UPPER(code)='CLINIC') DESC, id LIMIT 1")->fetchColumn();
        if ($v) $id = (int)$v;
    } catch (Throwable $e) { error_log('clinic_store_id: ' . $e->getMessage()); }
    return $id;
}

/** The department's own stock location (stores.type='department', code DEPT-<id>). Created on first use. 0 = no such department. */
if (!function_exists('dept_store_id')) {
function dept_store_id(PDO $pdo, int $deptId): int {
    if ($deptId <= 0) return 0;
    $q = $pdo->prepare('SELECT id FROM stores WHERE code = ?');
    $q->execute(['DEPT-' . $deptId]);
    if ($id = (int)$q->fetchColumn()) return $id;
    $n = $pdo->prepare('SELECT name FROM departments WHERE id = ?');
    $n->execute([$deptId]);
    $name = $n->fetchColumn();
    if ($name === false) return 0;
    $pdo->prepare("INSERT INTO stores (name, code, type, location) VALUES (?,?, 'department', ?)")->execute([$name . ' Store', 'DEPT-' . $deptId, $name]);
    return (int)$pdo->lastInsertId();
}
}

/** The Laboratory department's own store: lab consumables (Collect Sample / Deduct Consumables) are drawn from it. */
if (!function_exists('lab_store_id')) {
function lab_store_id(PDO $pdo): int {
    $d = (int)$pdo->query("SELECT id FROM departments WHERE name = 'Laboratory' ORDER BY id LIMIT 1")->fetchColumn();
    return dept_store_id($pdo, $d ?: 36) ?: clinic_store_id($pdo);
}
}

function rm_schema(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        // Trimmed for medlink: request_management columns/enum, dispense_shortfalls are already in the DB (schema sync), so no ALTER here.
        // Cheap check first: when both roles exist this is one query per request
        if ((int)$pdo->query("SELECT COUNT(*) FROM roles WHERE slug IN ('clinical_director','managing_director')")->fetchColumn() === 2) return;
        // Roles: clinical director (new) and managing director (make sure it exists)
        $role = $pdo->prepare('INSERT INTO roles (name, slug, description) SELECT ?, ?, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM roles WHERE slug = ?)');
        $role->execute(['Clinical Director', 'clinical_director', 'Final approval of department requests for drugs and consumables', 'clinical_director']);
        $role->execute(['Managing Director', 'managing_director', 'Final approval for requisitions, POs and exceptions', 'managing_director']);
        // Menu access: approvers get the request screens they use; doctors can raise requests
        $grant = $pdo->prepare('INSERT INTO role_menu_access (role_id, menu_item_id) SELECT r.id, i.id FROM roles r JOIN nav_menu_items i ON i.url LIKE ? WHERE r.slug = ?
                                AND NOT EXISTS (SELECT 1 FROM role_menu_access x WHERE x.role_id = r.id AND x.menu_item_id = i.id)');
        foreach (['clinical_director', 'managing_director'] as $slug)
            foreach (['dashboard', 'my-requests', 'approvals', 'history'] as $tab) $grant->execute(['requests.php?module=requests&sub=' . $tab, $slug]);
        $grant->execute(['requests.php?module=requests&sub=create', 'doctor']);
    } catch (Throwable $e) { error_log('rm_schema: ' . $e->getMessage()); }
}

/** Sidebar entries for the two drug reports, under Inventory & Stock and Reports & HMIS. Each inherits the role grants of an existing sibling. */
function rm_report_menus(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $have = (int)$pdo->query("SELECT COUNT(DISTINCT menu_id, url) FROM nav_menu_items WHERE url IN ('inventory.php?module=inventory&sub=stock-issues','inventory.php?module=inventory&sub=drug-performance')")->fetchColumn();
        if ($have >= 4) return;   // both reports are already in both menus
        $reports = [['Drug Issues & Movements', 'stock-issues', 'fas fa-people-carry-box'], ['Drug Performance', 'drug-performance', 'fas fa-chart-line']];
        $targets = [
            [['Inventory & Stock', 'Assets & Inventory'], 'inventory.php?module=inventory&sub=stock-report'],
            [['Reports & HMIS', 'Reporting'], 'report.php?module=reports&sub=drug-sales'],
        ];
        $has = $pdo->prepare('SELECT id FROM nav_menu_items WHERE menu_id = ? AND url = ? LIMIT 1');
        $sib = $pdo->prepare('SELECT id FROM nav_menu_items WHERE url = ? LIMIT 1');
        foreach ($targets as [$titles, $sibling]) {
            $q = $pdo->prepare('SELECT id FROM nav_menus WHERE title IN (' . implode(',', array_fill(0, count($titles), '?')) . ') ORDER BY is_active DESC, id LIMIT 1');
            $q->execute($titles);
            $menuId = (int)$q->fetchColumn();
            if (!$menuId) continue;
            $sib->execute([$sibling]);
            $sibId = (int)$sib->fetchColumn();
            foreach ($reports as [$title, $sub, $icon]) {
                $url = 'inventory.php?module=inventory&sub=' . $sub;
                $has->execute([$menuId, $url]);
                if ($has->fetchColumn()) continue;
                $ord = (int)$pdo->query('SELECT COALESCE(MAX(item_order),0)+1 FROM nav_menu_items WHERE menu_id=' . $menuId)->fetchColumn();
                $pdo->prepare('INSERT INTO nav_menu_items (menu_id, title, url, icon, item_order, is_active) VALUES (?,?,?,?,?,1)')->execute([$menuId, $title, $url, $icon, $ord]);
                $newId = (int)$pdo->lastInsertId();
                if ($sibId) $pdo->prepare('INSERT INTO role_menu_access (role_id, menu_item_id) SELECT role_id, ? FROM role_menu_access WHERE menu_item_id = ?')->execute([$newId, $sibId]);
                $pdo->prepare("INSERT INTO role_menu_access (role_id, menu_item_id) SELECT r.id, ? FROM roles r WHERE r.slug IN ('inventory_manager','storekeeper','pharmacist') AND NOT EXISTS (SELECT 1 FROM role_menu_access x WHERE x.role_id=r.id AND x.menu_item_id=?)")->execute([$newId, $newId]);
            }
        }
    } catch (Throwable $e) { error_log('rm_report_menus: ' . $e->getMessage()); }
}

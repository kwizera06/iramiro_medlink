<?php
// includes/procurement_rules.php — helpers the ported Michel procurement / request pages and handlers need.
// Every function is guarded so this file can sit next to other includes that define the same names.

// Next number of a document series ($table / $col are code constants, never user input)
if (!function_exists('next_doc_no')) {
function next_doc_no(PDO $pdo, string $table, string $col, string $prefix, int $pad = 4): string {
    $pdo->query("SELECT GET_LOCK('docno_$table', 5)")->fetchColumn();
    $q = $pdo->prepare("SELECT MAX(CAST(SUBSTRING($col, ?) AS UNSIGNED)) FROM $table WHERE $col LIKE ?");
    $q->execute([strlen($prefix) + 1, $prefix . '%']);
    return $prefix . str_pad((string)((int)$q->fetchColumn() + 1), $pad, '0', STR_PAD_LEFT);
}
}

// Days a provisional award must stand before the final award (hospital_settings 'standstill_days', default 7)
if (!function_exists('rfq_standstill_days')) {
function rfq_standstill_days(PDO $pdo): int {
    $q = $pdo->query("SELECT setting_value FROM hospital_settings WHERE setting_key='standstill_days' LIMIT 1");
    $v = $q ? (int)$q->fetchColumn() : 0;
    return $v > 0 ? $v : 7;
}
}

// Price score = lowest open bid / this bid x 100, so it moves when a cheaper quotation arrives.
// Total = price 40% + technical 40% + delivery 20%.
if (!function_exists('rfq_total_score')) {
function rfq_total_score(float $price, float $technical, float $delivery): float {
    return round($price * 0.4 + $technical * 0.4 + $delivery * 0.2, 2);
}
}
if (!function_exists('rfq_rescore')) {
function rfq_rescore(PDO $pdo, int $rfqId): void {
    $min = $pdo->prepare("SELECT MIN(total_amount) FROM procurement_quotations WHERE rfq_id=? AND status<>'rejected' AND total_amount>0");
    $min->execute([$rfqId]); $low = (float)$min->fetchColumn();
    $ev = $pdo->prepare("SELECT e.id, e.technical_score, e.delivery_score, q.total_amount FROM procurement_evaluations e JOIN procurement_quotations q ON q.id=e.quotation_id WHERE e.rfq_id=? AND q.status<>'rejected'");
    $ev->execute([$rfqId]);
    $up = $pdo->prepare("UPDATE procurement_evaluations SET price_score=?, total_score=? WHERE id=?");
    foreach ($ev->fetchAll() as $r) {
        $price = ($low > 0 && (float)$r['total_amount'] > 0) ? round($low / (float)$r['total_amount'] * 100, 2) : 0;
        $up->execute([$price, rfq_total_score($price, (float)$r['technical_score'], (float)$r['delivery_score']), $r['id']]);
    }
}
}

// What is still owed on a GRN before its supplier bill: accepted value less returns made while it was uninvoiced
if (!function_exists('fin_grn_open_value')) {
function fin_grn_open_value(PDO $pdo, int $grnId): float {
    $q = $pdo->prepare("SELECT COALESCE((SELECT SUM(quantity_accepted * unit_cost) FROM goods_receiving_items WHERE grn_id = ?), 0)
                             - COALESCE((SELECT SUM(amount) FROM supplier_returns WHERE grn_id = ? AND against = 'grni'), 0)");
    $q->execute([$grnId, $grnId]);
    return round((float)$q->fetchColumn(), 2);
}
}

// Procurement tab access: admin sees every tab; others need a sidebar item (by role or personal grant) on procurement.php
// whose ?sub= is one of the page's tabs. ponytail: medlink has no page-guard layer, this reads role_menu_access/user_menu_access directly.
if (!function_exists('pc_has_access')) {
function pc_has_access(PDO $pdo, int $uid, array $subs): bool {
    static $urls = null;
    if (!$uid) return false;
    $roles = $_SESSION['user_data']['role_slugs'] ?? [];
    if (in_array('admin', $roles, true)) return true;
    if ($urls === null) {
        $q = $pdo->prepare("SELECT i.url FROM nav_menu_items i WHERE i.is_active = 1 AND TRIM(LEADING '/' FROM i.url) LIKE 'procurement.php%'
            AND (EXISTS (SELECT 1 FROM role_menu_access a JOIN user_roles ur ON ur.role_id = a.role_id WHERE a.menu_item_id = i.id AND ur.user_id = ?)
              OR EXISTS (SELECT 1 FROM user_menu_access u WHERE u.menu_item_id = i.id AND u.user_id = ?))");
        $q->execute([$uid, $uid]);
        $urls = $q->fetchAll(PDO::FETCH_COLUMN);
    }
    foreach ($urls as $u) { parse_str((string)parse_url($u, PHP_URL_QUERY), $p); if (in_array($p['sub'] ?? '', $subs, true)) return true; }
    return false;
}
}

// Minimal audit-trail writer (medlink has no activity_log include). Never breaks the request.
if (!function_exists('log_activity')) {
function log_activity($pdo, $action, $module, $description = '', $record_id = null, $record_ref = null, $changes = null) {
    try {
        $uid = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $name = trim(($_SESSION['user_data']['first_name'] ?? '') . ' ' . ($_SESSION['user_data']['last_name'] ?? ''));
        $pdo->prepare("INSERT INTO activity_log (user_id, user_name, action, module, description, record_id, record_ref, ip_address) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$uid, $name ?: null, (string)$action, (string)$module, (string)$description, $record_id ? (int)$record_id : null, $record_ref, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable $e) { error_log('log_activity: ' . $e->getMessage()); }
}
}

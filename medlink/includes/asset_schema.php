<?php
// includes/asset_schema.php — Assets Management support, ported from Michel's asset_schema.php plus the few helpers its handler needs
// (inventory_perms, controls, finance_posting), reduced for medlink: no ALTER/UPDATE/DELETE on existing tables, no sidebar sync.
// am_boot() only adds missing ledger accounts (INSERT when the account code is absent). All asset tables already exist.
require_once __DIR__ . '/expense_schema.php';   // fin_acct / fin_post / fin_unpost / fin_cash_accounts
require_once __DIR__ . '/transfer_schema.php';  // next_doc_no
require_once __DIR__ . '/asset_rules.php';

// Page grouping used for access control (a grant on one tab opens the other tabs of the same page)
const AM_PAGES = [
    ['Asset Register',                 'fas fa-clipboard-list', ['register', 'summary', 'categories', 'opening', 'qr', 'new_asset']],
    ['Capitalization & Depreciation',  'fas fa-chart-line',     ['capitalization', 'depreciation', 'revaluation']],
    ['Assignment & Handover',          'fas fa-user-tag',       ['assignments', 'clearance']],
    ['Transfers',                      'fas fa-exchange-alt',   ['transfers', 'movements']],
    ['Maintenance',                    'fas fa-tools',          ['m_schedule', 'm_faults', 'm_orders', 'm_verify', 'm_history']],
    ['Equipment Monitoring',           'fas fa-heartbeat',      ['mon_status', 'mon_alerts', 'mon_critical']],
    ['Disposal',                       'fas fa-trash-alt',      ['disposal']],
];

// Sidebar items: [title, icon, ?sub= value]
const AM_SIDEBAR = [
    ['New Asset',                           'fas fa-plus-circle',    'new_asset'],
    ['Asset Categories',                    'fas fa-tags',           'categories'],
    ['Assets Opening',                      'fas fa-book-open',      'opening'],
    ['Asset Depreciations',                 'fas fa-chart-line',     'depreciation'],
    ['Asset Appreciation / Capitalization', 'fas fa-arrow-up',       'capitalization'],
    ['Asset Transfers',                     'fas fa-exchange-alt',   'transfers'],
    ['Asset Assignment',                    'fas fa-user-tag',       'assignments'],
    ['Asset Maintenance Planning',          'fas fa-calendar-alt',   'm_schedule'],
    ['Maintenance Reports',                 'fas fa-history',        'm_history'],
    ['Asset Disposal',                      'fas fa-trash-alt',      'disposal'],
    ['Consolidated Asset Reports',          'fas fa-table',          'summary'],
];

// Tabs with no sidebar item: kept in the code, a direct request goes back to the Asset Register (see assets.php).
const AM_HIDDEN_SUBS = ['qr', 'clearance', 'movements', 'm_faults', 'm_orders', 'm_verify', 'mon_status', 'mon_alerts', 'mon_critical'];

function am_same_page(string $a, string $b): bool {
    foreach (AM_PAGES as [, , $subs]) if (in_array($a, $subs, true) && in_array($b, $subs, true)) return true;
    return false;
}

// One sidebar item that covers more than one screen: a tab strip inside the page (in order)
const AM_ITEM_TABS = ['capitalization' => ['capitalization', 'revaluation']];
function am_is_sidebar_tab(string $itemSub, string $currentSub): bool {
    return in_array($currentSub, AM_ITEM_TABS[$itemSub] ?? [$itemSub], true);
}
function am_tab_group(string $sub): array {
    foreach (AM_ITEM_TABS as $tabs) if (in_array($sub, $tabs, true)) return $tabs;
    return [];
}

// ---- helpers the ported handler expects -------------------------------------------------------------------------------
if (!function_exists('ctl_uid')) { function ctl_uid(): int { return (int)($_SESSION['user_id'] ?? 0); } }
if (!function_exists('ctl_enforced')) {
    function ctl_enforced(PDO $pdo): bool {
        $q = $pdo->query("SELECT setting_value FROM hospital_settings WHERE setting_key = 'sod_enforce' LIMIT 1");
        return ($q ? $q->fetchColumn() : false) !== '0';
    }
}
// Segregation of duties: $earlier = what the current user may already have done, e.g. ['requested' => 12]
if (!function_exists('ctl_conflict')) {
    function ctl_conflict(PDO $pdo, string $step, array $earlier): ?string {
        $me = ctl_uid();
        if (!$me || !ctl_enforced($pdo)) return null;
        foreach ($earlier as $did => $uid) {
            if ((int)$uid === $me) return "Segregation of duties: you $did this, so someone else must $step it";
        }
        return null;
    }
}
if (!function_exists('inv_role_slugs')) {
    function inv_role_slugs(PDO $pdo): array {
        static $cache = null;
        if ($cache !== null) return $cache;
        $cache = [];
        $uid = (int)($_SESSION['user_id'] ?? 0);
        if (!$uid) return $cache;
        $st = $pdo->prepare("SELECT r.slug FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ?");
        $st->execute([$uid]);
        return $cache = array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN));
    }
}

// Post a journal entry for an asset event. Unlike expense_schema's fin_post, 'replace' deletes the earlier entry of the same type/ref first
// and the reference is AST-yyyymmdd-id. Joins an open transaction.
function am_post(PDO $pdo, array $o): int {
    if (!empty($o['replace'])) fin_unpost($pdo, $o['type'], $o['ref_id']);
    $id = fin_post($pdo, $o);
    $date = substr((string)($o['date'] ?? date('Y-m-d')), 0, 10);
    $pdo->prepare("UPDATE journal_entries SET reference = ? WHERE id = ?")->execute([sprintf('AST-%s-%05d', date('Ymd', strtotime($date)), $id), $id]);
    return $id;
}
// Ledger account of the store a delivery sits in (Inventory sub-accounts are the store ids); Clinic store when it has none
function am_store_acct(PDO $pdo, int $storeId): int {
    $q = $pdo->prepare("SELECT id FROM chart_of_accounts WHERE id = ? AND parent_id = 9");
    $q->execute([$storeId]);
    return (int)($q->fetchColumn() ?: fin_acct($pdo, '1132'));
}
// Entries posted for a source record: [id, reference_type, description]
function am_entries(PDO $pdo, array $types, string $refId): array {
    $in = implode(',', array_fill(0, count($types), '?'));
    $q = $pdo->prepare("SELECT id, reference_type, description FROM journal_entries WHERE reference_type IN ($in) AND reference_id = ? ORDER BY id");
    $q->execute(array_merge($types, [$refId]));
    return $q->fetchAll();
}

// Ledger accounts the asset events post against (fin_acct accepts a raw account_code). Missing ones are added once.
const AM_ACCOUNTS = [
    ['111001', 'Cash on hand',           'asset',   '1110'],
    ['4310',   'Gain on asset disposal', 'income',  '4300'],
    ['534008', 'Loss on asset disposal', 'expense', '5340'],
    ['534010', 'Repairs & maintenance',  'expense', '5340'],
];
function am_boot(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $have = $pdo->prepare("SELECT 1 FROM chart_of_accounts WHERE account_code = ?");
    $par = $pdo->prepare("SELECT id FROM chart_of_accounts WHERE account_code = ?");
    $ins = $pdo->prepare("INSERT INTO chart_of_accounts (account_code, account_name, account_type, parent_id) VALUES (?,?,?,?)");
    foreach (AM_ACCOUNTS as [$code, $name, $type, $parent]) {
        $have->execute([$code]);
        if ($have->fetchColumn()) continue;
        $par->execute([$parent]);
        $pid = $par->fetchColumn();
        if ($pid) $ins->execute([$code, $name, $type, (int)$pid]);
    }
}

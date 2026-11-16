<?php
// includes/finance_menu.php
// Sidebar / role-access changes for the finance screens. Called from migrate.php (inside its transaction); idempotent.

function fin_migrate_menus(PDO $pdo, callable $log): void {
    $item = function (int $menuId, string $title, string $url, string $icon, int $order) use ($pdo, $log): int {
        $q = $pdo->prepare("SELECT id FROM nav_menu_items WHERE url = ? AND menu_id = ? LIMIT 1");
        $q->execute([$url, $menuId]);
        $id = (int)$q->fetchColumn();
        if (!$id) {
            $pdo->prepare("INSERT INTO nav_menu_items (menu_id, title, url, icon, item_order, is_active) VALUES (?,?,?,?,?,1)")->execute([$menuId, $title, $url, $icon, $order]);
            $id = (int)$pdo->lastInsertId();
            $log("+ menu item: $title");
        }
        $pdo->prepare("INSERT INTO role_menu_access (role_id, menu_item_id) SELECT 1, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM role_menu_access WHERE role_id = 1 AND menu_item_id = ?)")->execute([$id, $id]);
        return $id;
    };

    // Expense Management is its own top-level module, parallel to Finance.
    // It intentionally has its own subpages so operational users do not need access
    // to the accounting screens just to submit an expense.
    $expenseMenu = (int)$pdo->query("SELECT id FROM nav_menus WHERE title = 'Expense Management' LIMIT 1")->fetchColumn();
    if (!$expenseMenu) {
        $expenseMenu = (int)$pdo->query("SELECT COALESCE(MAX(menu_order), 0) + 1 FROM nav_menus")->fetchColumn();
        $pdo->prepare("INSERT INTO nav_menus (title, icon, section_label, menu_order, is_active) VALUES (?,?,?,?,1)")
            ->execute(['Expense Management', 'fas fa-file-invoice-dollar', 'Operations', $expenseMenu]);
        $expenseMenu = (int)$pdo->lastInsertId();
        $log('+ top-level menu: Expense Management');
    }
    $expensePages = [
        ['Dashboard', 'expenses.php?module=expenses&sub=dashboard', 'fas fa-chart-pie', 1],
        ['Add Expense', 'expenses.php?module=expenses&sub=add', 'fas fa-plus-circle', 2],
        ['All Expenses', 'expenses.php?module=expenses&sub=expenses', 'fas fa-receipt', 3],
        ['Pending Approvals', 'expenses.php?module=expenses&sub=approvals', 'fas fa-user-check', 4],
        ['Payments', 'expenses.php?module=expenses&sub=payments', 'fas fa-money-check-alt', 5],
        ['Categories', 'expenses.php?module=expenses&sub=categories', 'fas fa-tags', 6],
        ['Suppliers', 'expenses.php?module=expenses&sub=suppliers', 'fas fa-truck', 7],
        ['Reports', 'expenses.php?module=expenses&sub=reports', 'fas fa-chart-line', 8],
    ];
    foreach ($expensePages as [$title, $url, $icon, $order]) $item($expenseMenu, $title, $url, $icon, $order);
    // Remove the earlier single Finance submenu entry if this version was already installed.
    $oldExpense = $pdo->query("SELECT id FROM nav_menu_items WHERE menu_id=6 AND url='expenses.php?module=expenses'")->fetchAll(PDO::FETCH_COLUMN);
    if ($oldExpense) {
        $marks = implode(',', array_fill(0, count($oldExpense), '?'));
        $pdo->prepare("DELETE FROM role_menu_access WHERE menu_item_id IN ($marks)")->execute($oldExpense);
        $pdo->prepare("DELETE FROM user_menu_access WHERE menu_item_id IN ($marks)")->execute($oldExpense);
        $pdo->prepare("DELETE FROM nav_menu_items WHERE id IN ($marks)")->execute($oldExpense);
        $log('- moved Expense Management out of Finance submenu');
    }
    $oldExpensePages = $pdo->query("SELECT id FROM nav_menu_items WHERE menu_id=" . (int)$expenseMenu . " AND url IN ('expenses.php?module=expenses&sub=settings','expenses.php?module=expenses&sub=integration')")->fetchAll(PDO::FETCH_COLUMN);
    if ($oldExpensePages) {
        $marks = implode(',', array_fill(0, count($oldExpensePages), '?'));
        $pdo->prepare("DELETE FROM role_menu_access WHERE menu_item_id IN ($marks)")->execute($oldExpensePages);
        $pdo->prepare("DELETE FROM user_menu_access WHERE menu_item_id IN ($marks)")->execute($oldExpensePages);
        $pdo->prepare("DELETE FROM nav_menu_items WHERE id IN ($marks)")->execute($oldExpensePages);
        $log('- removed non-spec Expense Management pages');
    }
    // Expense pages are role-scoped. The old migration granted every tab to every
    // role, which made operational users see the same navigation as administrators.
    // Reset only this module once (schema v25), then apply least-privilege defaults.
    $pdo->exec("DELETE rma FROM role_menu_access rma
                JOIN nav_menu_items i ON i.id = rma.menu_item_id
                WHERE i.menu_id = " . (int)$expenseMenu);
    $expenseRoleTabs = [
        'admin'               => ['dashboard','add','expenses','approvals','payments','categories','suppliers','reports'],
        'accountant'          => ['dashboard','add','expenses','approvals','payments','categories','suppliers','reports'],
        'manager'             => ['dashboard','add','expenses','approvals','payments','reports'],
        'department_head'     => ['dashboard','add','expenses','approvals'],
        'housekeeping_officer'=> ['dashboard','add','expenses'],
        'maintenance_officer' => ['dashboard','add','expenses'],
    ];
    $grant = $pdo->prepare("INSERT IGNORE INTO role_menu_access (role_id, menu_item_id)
                            SELECT r.id, i.id FROM roles r JOIN nav_menu_items i
                              ON i.menu_id = ? AND i.url LIKE ?
                            WHERE r.slug = ?");
    foreach ($expenseRoleTabs as $slug => $tabs) {
        foreach ($tabs as $tab) $grant->execute([$expenseMenu, '%sub=' . $tab, $slug]);
    }

    // Requests & Requisitions is a separate operational module. Existing requisition,
    // inventory and procurement screens are exposed through one central navigation tree.
    $requestMenu = (int)$pdo->query("SELECT id FROM nav_menus WHERE title='Requests & Requisitions' LIMIT 1")->fetchColumn();
    if (!$requestMenu) {
        $order = (int)$pdo->query("SELECT COALESCE(MAX(menu_order),0)+1 FROM nav_menus")->fetchColumn();
        $pdo->prepare("INSERT INTO nav_menus (title,icon,section_label,menu_order,is_active) VALUES (?,?,?,?,1)")
            ->execute(['Requests & Requisitions','fas fa-clipboard-list','OPERATIONS',$order]);
        $requestMenu=(int)$pdo->lastInsertId(); $log('+ top-level menu: Requests & Requisitions');
    }
    $requestPages=[
        ['Add New Request','requests.php?module=requests&sub=create','fas fa-plus-circle',2],
        ['My Requests','requests.php?module=requests&sub=my-requests','fas fa-clipboard-list',3],
        ['Approval Queue','requests.php?module=requests&sub=approvals','fas fa-user-check',4],
        ['Stock Fulfillment','requests.php?module=requests&sub=fulfillment','fas fa-dolly',5],
        ['Requisitions','requests.php?module=requests&sub=requisitions','fas fa-file-alt',6],
        ['Procurement Handoff','requests.php?module=requests&sub=procurement','fas fa-share-square',7],
        ['History','requests.php?module=requests&sub=history','fas fa-history',8],
    ];
    foreach($requestPages as [$title,$url,$icon,$order]) $item($requestMenu,$title,$url,$icon,$order);
    $legacyRequestUrls=['requisitions.php?sub=my-requests','requisitions.php?sub=verification','requisitions.php?sub=approved'];
    $marks=implode(',',array_fill(0,count($legacyRequestUrls),'?'));$legacy=$pdo->prepare("SELECT id FROM nav_menu_items WHERE menu_id=? AND url IN ($marks)");$legacy->execute(array_merge([$requestMenu],$legacyRequestUrls));$legacyIds=$legacy->fetchAll(PDO::FETCH_COLUMN);if($legacyIds){$m=implode(',',array_fill(0,count($legacyIds),'?'));$pdo->prepare("DELETE FROM role_menu_access WHERE menu_item_id IN ($m)")->execute($legacyIds);$pdo->prepare("DELETE FROM user_menu_access WHERE menu_item_id IN ($m)")->execute($legacyIds);$pdo->prepare("DELETE FROM nav_menu_items WHERE id IN ($m)")->execute($legacyIds);$log('- removed legacy merged request/procurement links');}
    // Requests are role-scoped; do not expose fulfillment/procurement controls to
    // every employee just because they can submit a departmental request.
    $pdo->exec("DELETE rma FROM role_menu_access rma JOIN nav_menu_items i ON i.id=rma.menu_item_id WHERE i.menu_id=".(int)$requestMenu);
    $requestRoleTabs=[
        'admin'=>['dashboard','create','my-requests','approvals','fulfillment','requisitions','procurement','history'],
        'manager'=>['dashboard','create','my-requests','approvals','fulfillment','requisitions','procurement','history'],
        'department_head'=>['dashboard','create','my-requests','approvals','requisitions','history'],
        'housekeeping_officer'=>['dashboard','create','my-requests','history'],
        'maintenance_officer'=>['dashboard','create','my-requests','history'],
        'storekeeper'=>['dashboard','fulfillment','requisitions','history'],
        'inventory_manager'=>['dashboard','fulfillment','requisitions','procurement','history'],
        'procurement_officer'=>['dashboard','requisitions','procurement','history'],
        'clinical_director'=>['dashboard','create','my-requests','approvals','history'],
        'managing_director'=>['dashboard','create','my-requests','approvals','history'],
    ];
    $grantRequest=$pdo->prepare("INSERT IGNORE INTO role_menu_access(role_id,menu_item_id) SELECT r.id,i.id FROM roles r JOIN nav_menu_items i ON i.menu_id=? AND i.url LIKE ? WHERE r.slug=?");
    foreach($requestRoleTabs as $slug=>$tabs) foreach($tabs as $tab) $grantRequest->execute([$requestMenu,'%sub='.$tab,$slug]);
    // Every other employee can still raise a departmental request and follow it;
    // approval, fulfillment and procurement stay limited to the workflow roles.
    $requestDefaultTabs=['dashboard','create','my-requests','history'];
    $allRoleSlugs=$pdo->query('SELECT slug FROM roles')->fetchAll(PDO::FETCH_COLUMN);
    foreach($allRoleSlugs as $slug) if(!isset($requestRoleTabs[$slug])) foreach($requestDefaultTabs as $tab) $grantRequest->execute([$requestMenu,'%sub='.$tab,$slug]);
    // The standalone module replaces the old sidebar entry points; Procurement itself
    // remains available for approved handoffs and PO/GRN processing.
    $legacyStandalone=$pdo->query("SELECT id FROM nav_menu_items WHERE url LIKE 'requisitions.php%' OR url LIKE 'inventory.php%sub=purchase-request'")->fetchAll(PDO::FETCH_COLUMN);
    if($legacyStandalone){$m=implode(',',array_fill(0,count($legacyStandalone),'?'));$pdo->prepare("DELETE FROM role_menu_access WHERE menu_item_id IN ($m)")->execute($legacyStandalone);$pdo->prepare("DELETE FROM user_menu_access WHERE menu_item_id IN ($m)")->execute($legacyStandalone);$pdo->prepare("DELETE FROM nav_menu_items WHERE id IN ($m)")->execute($legacyStandalone);$log('- removed legacy requisition sidebar entry points');}

    // Procurement uses the existing Procurement menu and workflow. Remove the
    // duplicate Procurement Management menu and its grants if an earlier schema
    // version created it.
    $duplicatePm=(int)$pdo->query("SELECT id FROM nav_menus WHERE title='Procurement Management' LIMIT 1")->fetchColumn();
    if($duplicatePm){
        $ids=$pdo->query("SELECT id FROM nav_menu_items WHERE menu_id=".(int)$duplicatePm)->fetchAll(PDO::FETCH_COLUMN);
        if($ids){$marks=implode(',',array_fill(0,count($ids),'?'));$pdo->prepare("DELETE FROM role_menu_access WHERE menu_item_id IN ($marks)")->execute($ids);$pdo->prepare("DELETE FROM user_menu_access WHERE menu_item_id IN ($marks)")->execute($ids);$pdo->prepare("DELETE FROM nav_menu_items WHERE id IN ($marks)")->execute($ids);}
        $pdo->prepare('DELETE FROM nav_menus WHERE id=?')->execute([$duplicatePm]);
        $log('- removed duplicate Procurement Management menu');
    }

    // Procurement officers use the established Procurement workflow. These two
    // operational screens were previously removed as legacy links, but they are
    // part of the real purchase-request and receiving cycle.
    $procMenu=(int)$pdo->query("SELECT id FROM nav_menus WHERE title IN ('Procurements','Procurement and Supplier') ORDER BY id LIMIT 1")->fetchColumn();
    if($procMenu){
        $procOperationalPages=[
            ['Purchase Requests','inventory.php?module=inventory&sub=purchase-request','fas fa-file-alt',5],
        ];
        foreach($procOperationalPages as [$title,$url,$icon,$order]){
            $id=$item($procMenu,$title,$url,$icon,$order);
            // The accountant is the finance-stage approver on purchase requests, so the
            // request page must be reachable without going through the approvals inbox.
            $grantProc=$pdo->prepare("INSERT IGNORE INTO role_menu_access(role_id,menu_item_id) SELECT r.id,? FROM roles r WHERE r.slug IN ('admin','procurement_officer','procurement_manager','manager','accountant')");
            $grantProc->execute([$id]);
        }
    }

    // Finance menu (id 6)
    $new = [
        ['Expenses & Payables',  'expenses',          'fas fa-receipt',          30],
        ['Notes to Accounts',    'fs-notes',          'fas fa-sticky-note',      41],
        ['Changes in Equity',    'equity-statement',  'fas fa-layer-group',      42],
        ['Year-End Close',       'year-end',          'fas fa-calendar-check',   43],
        ['Bad Debts & Write-offs', 'receivables',     'fas fa-hand-holding-usd', 44],
        ['Insurance Claims',     'claims',            'fas fa-file-medical',     45],
    ];
    foreach ($new as [$title, $sub, $icon, $order]) $item(6, $title, "finance.php?module=finance&sub=$sub", $icon, $order);

    // Removed finance screens (deposits & refunds, ageing, cash close, fiscal periods, integrity, account mapping, alerts & controls, budgets): drop their sidebar items and grants
    $gone = ['deposits-refunds', 'ageing', 'cash-close', 'periods', 'integrity', 'account-map', 'alerts', 'budgets'];
    $goneUrls = array_map(fn($s) => "finance.php?module=finance&sub=$s", $gone);
    $in = implode(',', array_fill(0, count($goneUrls), '?'));
    $q = $pdo->prepare("SELECT id FROM nav_menu_items WHERE url IN ($in)");
    $q->execute($goneUrls);
    if ($ids = $q->fetchAll(PDO::FETCH_COLUMN)) {
        $m = implode(',', array_map('intval', $ids));
        $pdo->exec("DELETE FROM role_menu_access WHERE menu_item_id IN ($m)");
        $pdo->exec("DELETE FROM user_menu_access WHERE menu_item_id IN ($m)");
        $pdo->exec("DELETE FROM nav_menu_items WHERE id IN ($m)");
        $log('- removed ' . count($ids) . ' finance menu item(s)');
    }

    // Core statements first, in accounting-cycle order; the operational screens (30+) follow
    $core = ['chart-of-accounts' => 'Chart of Accounts', 'journal-entry' => 'Journal Entries', 'general-ledger' => 'General Ledger',
             'trial-balance' => 'Trial Balance', 'balance-sheet' => 'Balance Sheet', 'income-statement' => 'Income Statement',
             'cash-flow' => 'Cash Flow Statement', 'cash-equivalent' => 'Cash & Cash Equivalents',
             'bank-reconciliation' => 'Bank Reconciliation', 'fs-notes' => 'Notes to Accounts'];
    $upd = $pdo->prepare("UPDATE nav_menu_items SET item_order = ?, title = ? WHERE menu_id = 6 AND url = ? AND (item_order <> ? OR title <> ?)");
    $n = 0;
    foreach (array_keys($core) as $i => $sub) {
        $upd->execute([$i + 1, $core[$sub], "finance.php?module=finance&sub=$sub", $i + 1, $core[$sub]]);
        $n += $upd->rowCount();
    }
    if ($n) $log("~ reordered $n finance menu item(s)");

    // Settings > System logs gets a Finance tab (page only, no menu item needed)

    // Assets: broken revaluation URL, and a link to Categories
    $n = $pdo->exec("UPDATE nav_menu_items SET url = 'assets.php?module=assets&sub=revaluation' WHERE url = 'assets&sub=revaluation'");
    if ($n) $log("~ fixed $n broken Asset Revaluation link(s)");
    // (asset items live in the "Equipments and Assets" menu once the regrouping at the end has run, menu 9 before that)
    $assetMenuId = (int)($pdo->query("SELECT menu_id FROM nav_menu_items WHERE url LIKE 'assets.php%' ORDER BY id LIMIT 1")->fetchColumn() ?: 9);
    // (categories and opening balances are tabs of the Asset Register page; see includes/asset_schema.php)

    // Inactive menus whose items all duplicate an active menu's items (urls identical) are dead weight: drop them
    foreach ($pdo->query("SELECT id, title FROM nav_menus WHERE is_active = 0")->fetchAll() as $m) {
        $items = $pdo->prepare("SELECT i.id, i.url, (SELECT COUNT(*) FROM nav_menu_items d JOIN nav_menus dm ON dm.id = d.menu_id
                                  WHERE dm.is_active = 1 AND d.url = i.url) AS dup FROM nav_menu_items i WHERE i.menu_id = ?");
        $items->execute([$m['id']]);
        $rows = $items->fetchAll();
        if (!$rows) continue;
        foreach ($rows as $r) if (!(int)$r['dup']) continue 2;   // has a unique item: keep the whole menu
        $ids = array_column($rows, 'id');
        $in = implode(',', array_map('intval', $ids));
        $pdo->exec("DELETE FROM role_menu_access WHERE menu_item_id IN ($in)");
        $pdo->exec("DELETE FROM user_menu_access WHERE menu_item_id IN ($in)");
        $pdo->exec("DELETE FROM nav_menu_items WHERE id IN ($in)");
        $pdo->prepare("DELETE FROM nav_menus WHERE id = ?")->execute([$m['id']]);
        $log("- removed duplicate inactive menu: {$m['title']}");
    }

    // Accountant: finance screens, invoicing, insurance settlements, assets, reports
    $acc = $pdo->query("SELECT id FROM roles WHERE slug = 'accountant'")->fetchColumn();
    if ($acc) {
        $n = $pdo->prepare("INSERT INTO role_menu_access (role_id, menu_item_id)
            SELECT ?, i.id FROM nav_menu_items i
            WHERE i.is_active = 1 AND (i.menu_id = 6 OR i.url LIKE 'invoicing.php%' OR i.url LIKE 'assets.php%' OR i.url LIKE 'report.php%'
                                       OR i.url LIKE 'insurance.php%sub=receivables' OR i.url = 'dashboard.php' OR i.url = 'profile.php')
              AND NOT EXISTS (SELECT 1 FROM role_menu_access r WHERE r.role_id = ? AND r.menu_item_id = i.id)");
        $n->execute([$acc, $acc]);
        if ($n->rowCount()) $log("+ Accountant role: {$n->rowCount()} menu item(s)");
    }
    // Insurance officer: reviews and corrects insurer invoices and claims (#33b) — Finance > Insurance Claims
    $insOff = $pdo->query("SELECT id FROM roles WHERE slug = 'insurance_officer'")->fetchColumn();
    if ($insOff) {
        $n = $pdo->prepare("INSERT INTO role_menu_access (role_id, menu_item_id)
            SELECT ?, i.id FROM nav_menu_items i
            WHERE i.is_active = 1 AND (i.url = 'finance.php?module=finance&sub=claims' OR i.url LIKE 'insurance.php%sub=receivables'
                                       OR i.url = 'dashboard.php' OR i.url = 'profile.php')
              AND NOT EXISTS (SELECT 1 FROM role_menu_access r WHERE r.role_id = ? AND r.menu_item_id = i.id)");
        $n->execute([$insOff, $insOff]);
        if ($n->rowCount()) $log("+ Insurance officer role: {$n->rowCount()} menu item(s)");
    }
    // Receptionist takes payments: make sure invoicing is theirs
    $rec = $pdo->query("SELECT id FROM roles WHERE slug = 'receptionist'")->fetchColumn();
    if ($rec) {
        $n = $pdo->prepare("INSERT INTO role_menu_access (role_id, menu_item_id)
            SELECT ?, i.id FROM nav_menu_items i WHERE i.is_active = 1 AND i.url LIKE 'invoicing.php%'
              AND NOT EXISTS (SELECT 1 FROM role_menu_access r WHERE r.role_id = ? AND r.menu_item_id = i.id)");
        $n->execute([$rec, $rec]);
        if ($n->rowCount()) $log("+ Receptionist role: invoicing");
    }

    // Asset transfers and custody (v32). The disposal tab becomes request + approval, so the managing director needs it too.
    $assetMenu = (int)($pdo->query("SELECT menu_id FROM nav_menu_items WHERE url LIKE 'assets.php%' ORDER BY id LIMIT 1")->fetchColumn() ?: 9);
    $item($assetMenu, 'Asset transfers', 'assets.php?module=assets&sub=transfers', 'fas fa-exchange-alt', 17);
    $item($assetMenu, 'Asset assignments', 'assets.php?module=assets&sub=assignments', 'fas fa-user-tag', 18);
    $grantAsset = $pdo->prepare("INSERT IGNORE INTO role_menu_access (role_id, menu_item_id)
        SELECT r.id, i.id FROM roles r JOIN nav_menu_items i ON i.url LIKE ? WHERE r.slug = ?");
    foreach (['accountant' => ['transfers', 'assignments', 'disposal'],
              'maintenance_officer' => ['transfers', 'assignments', 'disposal'],
              'inventory_manager' => ['transfers', 'assignments', 'disposal'],
              'managing_director' => ['transfers', 'disposal']] as $slug => $tabs) {
        foreach ($tabs as $tab) $grantAsset->execute(['assets.php%sub=' . $tab, $slug]);
    }

    // Sidebar (v34): two top-level modules, each with its own submenu.
    // "Procurement and Supplier" is the old Procurements menu, renamed. "Assets Management" takes the asset-register tabs
    // out of Assets & Inventory (which keeps pharmacy dispensing, stores and stock). Role grants follow the items, so nothing is re-granted.
    $upMenu = $pdo->prepare("UPDATE nav_menus SET title = ?, icon = ? WHERE id = ?");
    $procId = (int)$pdo->query("SELECT id FROM nav_menus WHERE title IN ('Procurements','Procurement and Supplier') ORDER BY id LIMIT 1")->fetchColumn();
    if ($procId) { $upMenu->execute(['Procurement and Supplier', 'fas fa-shopping-cart', $procId]); }
    $equipId = (int)$pdo->query("SELECT id FROM nav_menus WHERE title IN ('Assets Management', 'Equipments and Assets') ORDER BY FIELD(title, 'Assets Management', 'Equipments and Assets') LIMIT 1")->fetchColumn();
    if (!$equipId) {
        $order = $procId ? (int)$pdo->query("SELECT menu_order FROM nav_menus WHERE id = $procId")->fetchColumn() : 9;
        $pdo->prepare("INSERT INTO nav_menus (title, icon, section_label, menu_order, is_active) VALUES ('Assets Management', 'fas fa-toolbox', 'Assets Management', ?, 1)")->execute([$order]);
        $equipId = (int)$pdo->lastInsertId();
        $log('+ top-level menu: Assets Management');
    }
    // (menu positions and section headings are set last by fin_regroup_sidebar)
    $moved = $pdo->prepare("UPDATE nav_menu_items SET menu_id = ? WHERE url LIKE 'assets.php%' AND menu_id <> ?");
    $moved->execute([$equipId, $equipId]);
        if ($moved->rowCount()) $log("~ moved {$moved->rowCount()} asset item(s) to Assets Management");
    $setItem = $pdo->prepare("UPDATE nav_menu_items SET title = ?, item_order = ?, icon = ? WHERE url = ?");
    foreach ([['New Asset', 'new_asset', 'fas fa-plus-circle'], ['Asset Register', 'register', 'fas fa-clipboard-list'],
              ['Asset Depreciations', 'depreciation', 'fas fa-chart-line'], ['Assets Opening', 'opening', 'fas fa-book-open'],
              ['Asset Appreciation / Capitalization', 'capitalization', 'fas fa-arrow-up'], ['Asset Transfers', 'transfers', 'fas fa-exchange-alt'],
              ['Asset Assignment', 'assignments', 'fas fa-user-tag'], ['Asset Maintenance Planning', 'm_schedule', 'fas fa-calendar-alt'],
              ['Maintenance Reports', 'm_history', 'fas fa-history'], ['Asset Disposal', 'disposal', 'fas fa-trash-alt'],
              ['Consolidated Asset Reports', 'summary', 'fas fa-table']] as $i => [$title, $sub, $icon]) {
        $setItem->execute([$title, $i + 1, $icon, "assets.php?module=assets&sub=$sub"]);
    }
    // Keep the Procurement and Supplier sidebar in its requested order. pc_menu() owns the labels;
    // this legacy migration only reinforces the item positions when migrations run again.
    if ($procId) {
        $setProc = $pdo->prepare("UPDATE nav_menu_items SET item_order = ? WHERE menu_id = ? AND url = ?");
        foreach (['procurement.php?module=procurement&sub=suppliers', 'procurement.php?module=procurement&sub=sourcing',
                  'procurement.php?module=procurement&sub=evaluation', 'procurement.php?module=procurement&sub=awards',
                  'procurement.php?module=procurement&sub=contracts', 'procurement.php?module=procurement&sub=purchase-orders',
                  'inventory.php?module=inventory&sub=goods-receiving', 'procurement.php?module=procurement&sub=goods-received',
                  'procurement.php?module=procurement&sub=proformas', 'procurement.php?module=procurement&sub=supplier-invoices'] as $i => $url) {
            $setProc->execute([$i + 1, $procId, $url]);
        }
    }
}

// Sidebar (v36): "Medical Operations" follows the patient through the hospital, with a step label (group_label) above each step.
// The separate Receptionist, Nurse, OPD, IPD, Laboratory and Insurance menus are folded into it; every other menu is re-titled and
// re-ordered. Moves rows by url, so role grants follow the items. Idempotent, and run last by migrate.php because earlier steps
// still add items to the old menu ids.
function fin_regroup_sidebar(PDO $pdo, callable $log): void {
    if (!$pdo->query("SHOW COLUMNS FROM nav_menu_items LIKE 'group_label'")->fetch()) {
        $pdo->exec("ALTER TABLE nav_menu_items ADD COLUMN group_label VARCHAR(60) NULL");
        $log('+ nav_menu_items.group_label');
    }
    $find = function (array $titles) use ($pdo): int {
        $q = $pdo->prepare("SELECT id FROM nav_menus WHERE title IN (" . implode(',', array_fill(0, count($titles), '?')) . ") ORDER BY id LIMIT 1");
        $q->execute($titles);
        return (int)$q->fetchColumn();
    };

    // The OPD appointments link was the same page as the Receptionist one: keep one, hand over its role grants
    $urlId = fn(string $url) => (int)$pdo->query("SELECT id FROM nav_menu_items WHERE url = " . $pdo->quote($url) . " ORDER BY id LIMIT 1")->fetchColumn();
    $keep = $urlId('appointments.php?module=receptionist');
    $drop = $urlId('appointments.php?module=opd');
    if ($keep && $drop) {
        $pdo->exec("INSERT INTO role_menu_access (role_id, menu_item_id) SELECT role_id, $keep FROM role_menu_access r WHERE menu_item_id = $drop
                    AND NOT EXISTS (SELECT 1 FROM role_menu_access x WHERE x.role_id = r.role_id AND x.menu_item_id = $keep)");
        $pdo->exec("INSERT INTO user_menu_access (user_id, menu_item_id) SELECT user_id, $keep FROM user_menu_access r WHERE menu_item_id = $drop
                    AND NOT EXISTS (SELECT 1 FROM user_menu_access x WHERE x.user_id = r.user_id AND x.menu_item_id = $keep)");
        $pdo->exec("DELETE FROM role_menu_access WHERE menu_item_id = $drop");
        $pdo->exec("DELETE FROM user_menu_access WHERE menu_item_id = $drop");
        $pdo->exec("DELETE FROM nav_menu_items WHERE id = $drop");
        $log('~ merged the OPD appointments link into Appointments');
    }

    $ops = $find(['Medical Operations']);
    if (!$ops) {
        $pdo->exec("INSERT INTO nav_menus (title, icon, section_label, menu_order, is_active) VALUES ('Medical Operations', 'fas fa-stethoscope', 'Clinical', 2, 1)");
        $ops = (int)$pdo->lastInsertId();
        $log('+ top-level menu: Medical Operations');
    }
    // Pages added after the first release of this sidebar: created once, granted to the roles that work at that step
    // (an admin can change the grants later; they are not re-applied)
    $grantNew = $pdo->prepare("INSERT IGNORE INTO role_menu_access (role_id, menu_item_id) SELECT r.id, ? FROM roles r WHERE r.slug = ? OR r.id = 1");
    foreach ([
        ['Imaging', 'imaging.php?module=imaging&sub=worklist', 'fas fa-x-ray', ['doctor']],
        ['Patient Transfer', 'workflow.php?module=workflow&sub=transfers', 'fas fa-ambulance', ['nurse']],
        ['Visit Closure', 'workflow.php?module=workflow&sub=closure', 'fas fa-clipboard-check', ['receptionist', 'nurse']],
        ['My Signature', 'signature.php?module=signature', 'fas fa-signature', ['doctor', 'dentist', 'radiographer', 'sonographer', 'radiologist', 'lab_tech', 'lab_technician', 'pharmacist', 'nurse', 'clinical_director']],
    ] as [$title, $url, $icon, $roles]) {
        if ($urlId($url)) continue;
        $pdo->prepare("INSERT INTO nav_menu_items (menu_id, title, url, icon, item_order, is_active) VALUES (?,?,?,?,99,1)")->execute([$ops, $title, $url, $icon]);
        $itemId = (int)$pdo->lastInsertId();
        foreach ($roles as $slug) $grantNew->execute([$itemId, $slug]);
        $log("+ menu item: $title");
    }
    // Signature settings live on the user's Profile, not in the clinical sidebar.
    if ($sigId = $urlId('signature.php?module=signature')) {
        $pdo->prepare("DELETE FROM role_menu_access WHERE menu_item_id = ?")->execute([$sigId]);
        $pdo->prepare("DELETE FROM user_menu_access WHERE menu_item_id = ?")->execute([$sigId]);
        $pdo->prepare("DELETE FROM nav_menu_items WHERE id = ?")->execute([$sigId]);
        $log('- sidebar: My Signature moved to Profile');
    }
    // Virtual consultations are a switch on the Appointments page now, not a second sidebar entry (same roles held both)
    if ($vid = $urlId('appointments.php?module=virtual')) {
        $pdo->prepare("DELETE FROM role_menu_access WHERE menu_item_id = ?")->execute([$vid]);
        $pdo->prepare("DELETE FROM user_menu_access WHERE menu_item_id = ?")->execute([$vid]);
        $pdo->prepare("DELETE FROM nav_menu_items WHERE id = ?")->execute([$vid]);
        $log('- sidebar: Virtual Consultations folded into Appointments');
    }
    // Approvals: one inbox, next to Dashboard (single-item menu: item and menu share the title)
    $apr = $find(['Approvals']);
    if (!$apr) {
        $pdo->exec("INSERT INTO nav_menus (title, icon, section_label, menu_order, is_active) VALUES ('Approvals', 'fas fa-check-double', 'Main', 2, 1)");
        $apr = (int)$pdo->lastInsertId();
        $log('+ top-level menu: Approvals');
    }
    if (!$urlId('approvals.php?module=approvals')) {
        $pdo->prepare("INSERT INTO nav_menu_items (menu_id, title, url, icon, item_order, is_active) VALUES (?, 'Approvals', 'approvals.php?module=approvals', 'fas fa-check-double', 1, 1)")->execute([$apr]);
        $aprItem = (int)$pdo->lastInsertId();
        $grantNew->execute([$aprItem, 'admin']);   // the admin approves everything
        $log('+ menu item: Approvals');
    }
    // Every role that decides something has the Approvals inbox; each sees only the kinds of its own role (handlers/approvals/approvals.php, ap_sources + ap_visible)
    if ($aprId = $urlId('approvals.php?module=approvals'))
        foreach (['accountant', 'managing_director', 'clinical_director', 'doctor', 'procurement_officer', 'department_head', 'inventory_manager', 'storekeeper', 'maintenance_officer', 'hr_manager'] as $slug) $grantNew->execute([$aprId, $slug]);
    // Imaging worklist: dentists, radiographers and sonographers write/attach the reports (grants are skipped when a role slug does not exist)
    if ($imgId = $urlId('imaging.php?module=imaging&sub=worklist'))
        foreach (['dentist', 'radiographer', 'sonographer', 'radiologist'] as $slug) $grantNew->execute([$imgId, $slug]);
    // Doctors may request transfers (the admin approves them)
    $pdo->exec("INSERT INTO role_menu_access (role_id, menu_item_id) SELECT r.id, i.id FROM roles r JOIN nav_menu_items i
                WHERE r.slug = 'doctor' AND i.url = 'workflow.php?module=workflow&sub=transfers'
                  AND NOT EXISTS (SELECT 1 FROM role_menu_access x WHERE x.role_id = r.id AND x.menu_item_id = i.id)");
    $flow = [
        ['Registration', 'patients.php?module=receptionist&sub=registration', 'Add Patient'],
        ['Registration', 'patients.php?module=receptionist&sub=manage', 'Patient List'],
        ['Open Visit', 'patients.php?module=receptionist&sub=open-visit', 'Open Visit'],
        ['Open Visit', 'workflow.php?module=workflow&sub=closure', 'Visit Closure'],
        ['Appointments', 'appointments.php?module=receptionist'],
        ['Nursing & Triage', 'patients.php?module=nurse&sub=patients-visit', 'Nurse Portal'],
        ['Nursing & Triage', 'procedures.php?module=nurse&sub=procedures'],
        ['Consultation & Diagnosis', 'department.php', 'Consultation & Diagnosis'],
        ['Laboratory', 'lab.php?module=lab&sub=test-requests'],
        ['Laboratory', 'lab.php?module=lab&sub=sample-collection'],
        ['Laboratory', 'lab.php?module=lab&sub=results'],
        ['Laboratory', 'lab.php?module=lab&sub=pathology'],
        ['Laboratory', 'lab.php?module=lab&sub=microbiology'],
        ['Laboratory', 'lab.php?module=lab&sub=blood-bank'],
        ['Laboratory', 'imaging.php?module=imaging&sub=worklist'],
        ['Laboratory', 'lab.php?module=lab&sub=test-settings'],
        ['Prescription & Drugs', 'pharmacy.php?module=pharmacy&sub=prescription'],
        ['Prescription & Drugs', 'pharmacy.php?module=pharmacy&sub=dispensing'],
        ['Admission & Beds', 'ward.php?module=ward&sub=admissions'],
        ['Admission & Beds', 'ward.php?module=ward&sub=admitted'],
        ['Admission & Beds', 'ward.php?module=ward&sub=rooms'],
        ['Admission & Beds', 'ward.php?module=ward&sub=discharged'],
        ['Patient Transfer', 'workflow.php?module=workflow&sub=transfers'],
        ['Billing & Payment', 'invoicing.php?module=finance&sub=invoices'],
        ['Billing & Payment', 'insurance.php?module=insurance&sub=companies'],
        ['Billing & Payment', 'insurance.php?module=insurance&sub=rates'],
        ['Billing & Payment', 'insurance.php?module=insurance&sub=receivables', 'Invoice Insurance'],
        ['Medical Documents', 'medical-documents.php?module=documents&sub=generated', 'General Document'],
        ['Medical Documents', 'medical-documents.php?module=documents&sub=medical-leave', 'Care of a Sick Child'],
        ['Medical Documents', 'medical-documents.php?module=documents&sub=leave-certificate', 'Medical Leave'],
        ['Medical Documents', 'medical-documents.php?module=documents&sub=test-certificate', 'Test Certificate'],
        ['Medical Documents', 'medical-documents.php?module=documents&sub=dietary-certificate', 'Dietary Restriction'],
        ['Medical Documents', 'medical-documents.php?module=documents&sub=fitness-certificate', 'Medical Fitness'],
    ];
    $place = $pdo->prepare("UPDATE nav_menu_items SET menu_id = ?, group_label = ?, item_order = ?, title = COALESCE(?, title) WHERE url = ? AND (menu_id <> ? OR group_label IS NULL OR group_label <> ? OR item_order <> ? OR title <> COALESCE(?, title))");
    $n = 0;
    foreach ($flow as $i => $row) {
        [$group, $url] = $row;
        $title = $row[2] ?? null;   // new name for the sidebar entry, when it has one
        $place->execute([$ops, $group, $i + 1, $title, $url, $ops, $group, $i + 1, $title]);
        $n += $place->rowCount();
    }
    if ($n) $log("~ placed $n item(s) in Medical Operations");
    // Admitted Patients is on the sidebar (it was hidden before): an admin can still hide it by hand, a re-run only shows it if it is inactive
    $h = $pdo->exec("UPDATE nav_menu_items SET is_active = 1 WHERE url = 'ward.php?module=ward&sub=admitted' AND is_active = 0");
    if ($h) $log('~ showed Admitted Patients in the sidebar');

    // Old per-role menus are now empty: hide them instead of deleting (earlier migrate steps still look them up by title)
    $hid = $pdo->exec("UPDATE nav_menus m SET is_active = 0 WHERE m.is_active = 1 AND m.id <> $ops
                        AND m.title IN ('Patient Registration','Nurse workspace','Out-Patient Department(OPD)','In-Patient Department(IPD)','Laboratory','Insurance')
                        AND NOT EXISTS (SELECT 1 FROM nav_menu_items i WHERE i.menu_id = m.id)");
    if ($hid) $log("~ hid $hid emptied menu(s)");

    // Documents are part of Medical Operations; only archive pages remain under Archives.
    $pdo->exec("UPDATE nav_menus SET title = 'Archives' WHERE title IN ('Medical Records & Archive', 'Medical Documents')");
    $pdo->exec("UPDATE nav_menus SET section_label = NULL WHERE section_label IN ('Operations', 'Management', 'Administration')");

    // Top-level order: the twelve modules, as [titles it may have today, final title, section heading, position]
    $upd = $pdo->prepare("UPDATE nav_menus SET title = ?, section_label = ?, menu_order = ?, is_active = 1 WHERE id = ? AND (title <> ? OR NOT (section_label <=> ?) OR menu_order <> ? OR is_active = 0)");
    foreach ([
        [['Dashboard'], 'Dashboard', 'Main', 1],
        [['Approvals'], 'Approvals', 'Main', 2],
        [['Medical Operations'], 'Medical Operations', 'Clinical', 3],
        [['Archives', 'Medical Records & Archive', 'Medical Documents'], 'Archives', 'Clinical', 4],
        [['Requests & Requisitions'], 'Requests & Requisitions', null, 5],
        [['Inventory & Stock', 'Assets & Inventory'], 'Inventory & Stock', null, 6],
        [['Procurement and Supplier', 'Procurements'], 'Procurement and Supplier', null, 7],
        [['Assets Management', 'Equipments and Assets'], 'Assets Management', null, 8],
        [['Expense Management'], 'Expense Management', null, 9],
        [['Finance Management', 'Finance'], 'Finance Management', null, 10],
        [['Human Resources', 'HR Management'], 'Human Resources', null, 11],
        [['Users, Roles & Permissions', 'Settings'], 'Users, Roles & Permissions', null, 100],
        [['Reports & HMIS', 'Reporting'], 'Reports & HMIS', null, 13],
    ] as [$from, $title, $section, $order]) {
        if ($id = $find($from)) {
            $upd->execute([$title, $section, $order, $id, $title, $section, $order]);
            if ($upd->rowCount()) $log("~ sidebar: $title (position $order)");
        }
    }

    // Human Resources: folders by HR area (group_label). A folder with one page shows as a plain link under that page's title,
    // so those pages carry the folder's name. Add New Employee is its own page (new_employee.php); Employees is view/edit only.
    $hrId = $find(['Human Resources', 'HR Management']);
    $empUrl = 'hr.php?module=hr&sub=employees';
    $newUrl = 'new_employee.php?module=hr&sub=new-employee';
    $deptUrl = 'department_file.php?module=hr&sub=add-department';
    // new HR pages: created once, granted to whoever has Employees
    foreach ([['Add New Employee', $newUrl, 'fas fa-user-plus'], ['Add Department', $deptUrl, 'fas fa-building']] as [$t, $u, $ic]) {
        if (!$hrId || $urlId($u) || !($empItem = $urlId($empUrl))) continue;
        $pdo->prepare("INSERT INTO nav_menu_items (menu_id, title, url, icon, item_order, is_active) VALUES (?, ?, ?, ?, 99, 1)")->execute([$hrId, $t, $u, $ic]);
        $newId = (int)$pdo->lastInsertId();
        $pdo->exec("INSERT IGNORE INTO role_menu_access (role_id, menu_item_id) SELECT role_id, $newId FROM role_menu_access WHERE menu_item_id = $empItem");
        $pdo->exec("INSERT IGNORE INTO user_menu_access (user_id, menu_item_id) SELECT user_id, $newId FROM user_menu_access WHERE menu_item_id = $empItem");
        $log("+ menu item: $t");
    }
    if ($hrId) {
        $hr = [   // [folder, url, new title or null]
            ['Employee Management', $newUrl, null],
            ['Employee Management', $deptUrl, null],
            ['Employee Management', $empUrl, 'View Employees'],
            ['Employee Management', 'hr.php?module=hr&sub=license-tracking', null],
            ['Recruitment and Workforce Planning', 'hr.php?module=hr&sub=recruitment', null],
            ['Recruitment and Workforce Planning', 'hr.php?module=hr&sub=interns', null],
            ['Recruitment and Workforce Planning', 'hr.php?module=hr&sub=casual-workers', null],
            ['Attendance, Duty Rosters and Shift', 'hr.php?module=hr&sub=attendance', null],
            ['Attendance, Duty Rosters and Shift', 'hr.php?module=hr&sub=shifts', null],
            ['Leave and Permissions', 'hr.php?module=hr&sub=leave', 'Leave and Permissions'],
            ['Payroll and Benefits', 'hr.php?module=hr&sub=payroll', null],
            ['Payroll and Benefits', 'hr.php?module=hr&sub=intern-casual-payroll', null],
            ['Performance and Staff Development', 'hr.php?module=hr&sub=performance', null],
            ['Performance and Staff Development', 'hr.php?module=hr&sub=training', null],
            ['Task management', 'hr.php?module=hr&sub=goals-tasks', 'Task management'],
            ['Disciplinary and Employee Relations', 'hr.php?module=hr&sub=disciplinary', null],
            ['Disciplinary and Employee Relations', 'hr.php?module=hr&sub=communications', null],
            ['Employee Documents and Exit Management', 'hr.php?module=hr&sub=contracts', 'Employee Documents and Exit Management'],
        ];
        $placeHr = $pdo->prepare("UPDATE nav_menu_items SET menu_id = ?, group_label = ?, item_order = ?, title = COALESCE(?, title) WHERE url = ? AND (menu_id <> ? OR group_label IS NULL OR group_label <> ? OR item_order <> ? OR title <> COALESCE(?, title))");
        $n = 0;
        foreach ($hr as $i => [$group, $url, $title]) {
            $placeHr->execute([$hrId, $group, $i + 1, $title, $url, $hrId, $group, $i + 1, $title]);
            $n += $placeHr->rowCount();
        }
        if ($n) $log("~ placed $n item(s) in Human Resources");
    }

    // Assets Management: folders along the life of an asset. The Asset Register entry is gone from the sidebar (hidden, not deleted:
    // the page still opens from New Asset); one-page folders show as plain links under the page's own title.
    $asId = $find(['Assets Management', 'Equipments and Assets']);
    if ($asId) {
        $a = 'assets.php?module=assets&sub=';
        $pdo->exec("UPDATE nav_menu_items SET is_active = 0 WHERE url = " . $pdo->quote($a . 'register') . " AND is_active = 1");
        // Asset Categories page: created once, granted to whoever has New Asset
        $catUrl = $a . 'categories'; $newAsset = $urlId($a . 'new_asset');
        if ($newAsset && !$urlId($catUrl)) {
            $pdo->prepare("INSERT INTO nav_menu_items (menu_id, title, url, icon, item_order, is_active) VALUES (?, 'Asset Categories', ?, 'fas fa-tags', 99, 1)")->execute([$asId, $catUrl]);
            $cid = (int)$pdo->lastInsertId();
            $pdo->exec("INSERT IGNORE INTO role_menu_access (role_id, menu_item_id) SELECT role_id, $cid FROM role_menu_access WHERE menu_item_id = $newAsset");
            $pdo->exec("INSERT IGNORE INTO user_menu_access (user_id, menu_item_id) SELECT user_id, $cid FROM user_menu_access WHERE menu_item_id = $newAsset");
            $log('+ menu item: Asset Categories');
        }
        $assets = [
            ['Asset Acquisition & Registry', $a . 'new_asset'],
            ['Asset Acquisition & Registry', $a . 'categories'],
            ['Asset Acquisition & Registry', $a . 'opening'],
            ['Asset Valuation', $a . 'depreciation'],
            ['Asset Valuation', $a . 'capitalization'],
            ['Asset Movement & Custody', $a . 'transfers'],
            ['Asset Movement & Custody', $a . 'assignments'],
            ['Asset Maintenance', $a . 'm_schedule'],
            ['Asset Maintenance', $a . 'm_history'],
            ['Asset Disposal', $a . 'disposal'],
            ['Consolidated Asset Reports', $a . 'summary'],
        ];
        $placeAs = $pdo->prepare("UPDATE nav_menu_items SET menu_id = ?, group_label = ?, item_order = ? WHERE url = ? AND (menu_id <> ? OR group_label IS NULL OR group_label <> ? OR item_order <> ?)");
        $n = 0;
        foreach ($assets as $i => [$group, $url]) {
            $placeAs->execute([$asId, $group, $i + 1, $url, $asId, $group, $i + 1]);
            $n += $placeAs->rowCount();
        }
        if ($n) $log("~ placed $n item(s) in Assets Management");
    }

    // Requests & Requisitions: folders along the workflow. The Dashboard is gone from the sidebar (hidden, not deleted):
    // its stat cards now sit on the History page. One-page folders show as plain links.
    $rqId = $find(['Requests & Requisitions']);
    if ($rqId) {
        $r = 'requests.php?module=requests&sub=';
        $pdo->exec("UPDATE nav_menu_items SET is_active = 0 WHERE url = " . $pdo->quote($r . 'dashboard') . " AND is_active = 1");
        $pdo->exec("UPDATE nav_menu_items SET is_active = 1 WHERE url = " . $pdo->quote($r . 'history') . " AND is_active = 0");
        $pdo->exec("UPDATE nav_menu_items SET title = 'Add New Request' WHERE url = " . $pdo->quote($r . 'create') . " AND title <> 'Add New Request'");
        $groupPlace = function (int $menuId, array $rows, string $label) use ($pdo, $log) {
            $st = $pdo->prepare("UPDATE nav_menu_items SET menu_id = ?, group_label = ?, item_order = ? WHERE url = ? AND (menu_id <> ? OR group_label IS NULL OR group_label <> ? OR item_order <> ?)");
            $n = 0;
            foreach ($rows as $i => [$group, $url]) { $st->execute([$menuId, $group, $i + 1, $url, $menuId, $group, $i + 1]); $n += $st->rowCount(); }
            if ($n) $log("~ placed $n item(s) in $label");
        };
        $groupPlace($rqId, [
            ['Requests', $r . 'create'],
            ['Requests', $r . 'my-requests'],
            ['Approval & Fulfillment', $r . 'approvals'],
            ['Approval & Fulfillment', $r . 'fulfillment'],
            ['Requisitions & Procurement', $r . 'requisitions'],
            ['Requisitions & Procurement', $r . 'procurement'],
            ['History', $r . 'history'],
        ], 'Requests & Requisitions');
    }

    // Inventory & Stock: folders by job (stores and items / stock movement / stock control)
    $ivId = $find(['Inventory & Stock', 'Assets & Inventory']);
    if ($ivId) {
        // the same report was added to this menu twice: keep the first row, give it the other row's grants, hide the copy
        foreach ($pdo->query("SELECT MIN(id) keep, GROUP_CONCAT(id) ids FROM nav_menu_items WHERE menu_id = $ivId AND is_active = 1 GROUP BY url HAVING COUNT(*) > 1")->fetchAll() as $dup) {
            $drop = implode(',', array_map('intval', array_diff(explode(',', $dup['ids']), [$dup['keep']])));
            $pdo->exec("INSERT IGNORE INTO role_menu_access (role_id, menu_item_id) SELECT role_id, {$dup['keep']} FROM role_menu_access WHERE menu_item_id IN ($drop)");
            $pdo->exec("INSERT IGNORE INTO user_menu_access (user_id, menu_item_id) SELECT user_id, {$dup['keep']} FROM user_menu_access WHERE menu_item_id IN ($drop)");
            $pdo->exec("UPDATE nav_menu_items SET is_active = 0 WHERE id IN ($drop)");
            $log("~ hid duplicate sidebar row(s) $drop");
        }
        $v = 'inventory.php?module=inventory&sub=';
        $st = $pdo->prepare("UPDATE nav_menu_items SET menu_id = ?, group_label = ?, item_order = ? WHERE url = ? AND menu_id NOT IN (SELECT id FROM nav_menus WHERE title IN ('Reports & HMIS', 'Reporting')) AND (menu_id <> ? OR group_label IS NULL OR group_label <> ? OR item_order <> ?)");
        $n = 0;
        foreach ([
            ['Stores & Items', $v . 'central-store'],
            ['Stores & Items', $v . 'items'],
            ['Stores & Items', $v . 'categories'],
            ['Stores & Items', 'inventory-opening.php?module=inventory&sub=opening'],
            ['Stock Movement', $v . 'transfers'],
            ['Stock Movement', $v . 'stock-report'],
            ['Stock Movement', $v . 'stock-issues'],
            ['Stock Control', $v . 'alerts'],
            ['Stock Control', $v . 'expired'],
            ['Inventory Report', 'inventory-workspace.php?module=inventory&sub=workspace'],
            ['Inventory Report', $v . 'drug-performance'],
        ] as $i => [$group, $url]) { $st->execute([$ivId, $group, $i + 1, $url, $ivId, $group, $i + 1]); $n += $st->rowCount(); }
        if ($n) $log("~ placed $n item(s) in Inventory & Stock");
    }
}

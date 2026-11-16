<?php
/**
 * Sidebar in the Michel layout: menu -> group -> page (nav_menu_items.group_label = level-2 group).
 * Existing items keep their id (role access is kept); Michel pages not present yet are added (admin-only until a role is given them).
 * Safe to run any number of times.   CLI: php migrations/sidebar_layout.php    Browser: /medlink/migrations/sidebar_layout.php
 */
if (PHP_SAPI !== 'cli') header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../includes/database.php';

$pdo->exec("ALTER TABLE nav_menu_items ADD COLUMN IF NOT EXISTS group_label VARCHAR(100) NULL");
$I = 'medical-documents.php?module=documents&sub=';
// [menu title, old titles to reuse, icon, section label, menu_order, items]; item = [group, title, url, icon|null (keep), old url, old title]
$sidebar = [
    ['Dashboard', ['Dashboard'], 'fas fa-th-large', 'Main', 1, [
        [null, 'Dashboard', 'dashboard.php', 'fas fa-th-large']]],
    ['Approvals', [], 'fas fa-user-check', 'Main', 2, [
        [null, 'Approvals', 'approvals.php', 'fas fa-user-check']]],
    ['Medical Operations', [], 'fas fa-stethoscope', 'Clinical', 3, [
        ['Registration', 'Add Patient', 'patients.php?module=receptionist&sub=registration', 'fas fa-user-plus'],
        ['Registration', 'Patient List', 'patients.php?module=receptionist&sub=manage', 'fas fa-users'],
        ['Open Visit', 'Open Visit', 'patients.php?module=receptionist&sub=open-visit', 'fas fa-door-open'],
        ['Open Visit', 'Visit Closure', 'workflow.php?module=workflow&sub=closure', 'fas fa-clipboard-check'],
        ['Nursing & Triage', 'Nurse Portal', 'patients.php?module=nurse&sub=patients-visit', 'fas fa-user-nurse'],
        ['Nursing & Triage', 'Procedures and Billing', 'procedures.php?module=nurse&sub=procedures', 'fas fa-notes-medical'],
        ['Nursing & Triage', 'Nursing Care', 'nursing.php?module=nursing&sub=patients', 'fas fa-user-nurse'],
        [null, 'Consultation & Diagnosis', 'department.php', 'fas fa-building'],
        ['Laboratory', 'Test Orders', 'lab.php?module=lab&sub=test-requests', null],
        ['Laboratory', 'Sample Collection', 'lab.php?module=lab&sub=sample-collection', null],
        ['Laboratory', 'Test results', 'lab.php?module=lab&sub=results', null],
        ['Laboratory', 'Test Settings', 'lab.php?module=lab&sub=test-settings', null],
        ['Prescription & Drugs', 'Dispensing Orders', 'pharmacy.php?module=pharmacy&sub=prescription', null],
        ['Prescription & Drugs', 'Dispensed Drugs', 'pharmacy.php?module=pharmacy&sub=dispensing', null],
        ['Admission & Beds', 'Admission Requests', 'ward.php?module=ward&sub=admissions', 'fas fa-bed'],
        ['Admission & Beds', 'Admitted Patients', 'ward.php?module=ward&sub=admitted', 'fas fa-bed'],
        [null, 'Patient Transfer', 'workflow.php?module=workflow&sub=transfers', 'fas fa-ambulance'],
        ['Billing & Payment', 'Invoicing', 'invoicing.php?module=finance&sub=invoices', null],
        ['Billing & Payment', 'Insurance', 'insurance.php?module=insurance&sub=companies', null],
        ['Billing & Payment', 'Rate Setting', 'insurance.php?module=insurance&sub=rates', null],
        ['Billing & Payment', 'Invoice Insurance', 'insurance.php?module=insurance&sub=receivables', null],
        ['Medical Documents', 'General Document', $I . 'generated', null],
        ['Medical Documents', 'Care of a Sick Child', $I . 'medical-leave', null],
        ['Medical Documents', 'Medical Leave', $I . 'leave-certificate', null, '/' . $I . 'leave-certificate'],
        ['Medical Documents', 'Test Certificate', $I . 'test-certificate', null],
        ['Medical Documents', 'Dietary Restriction', $I . 'dietary-certificate', null],
        ['Medical Documents', 'Medical Fitness', $I . 'fitness-certificate', null]]],
    ['Emergency', ['Emergency'], 'fas fa-ambulance', null, 4, [
        [null, 'Register Emergency', 'emergency.php?module=emergency&sub=register', null],
        [null, 'Active Cases', 'emergency.php?module=emergency&sub=triage', null],
        [null, 'Treatment', 'emergency.php?module=emergency&sub=treatment', null],
        [null, 'Admitted from ER', 'emergency.php?module=emergency&sub=admitted', null],
        [null, 'Closed Cases', 'emergency.php?module=emergency&sub=discharged', null]]],
    ['Archives', ['Document and Archive Management'], 'fas fa-folder-open', null, 5, [
        [null, 'Patient Files', 'documents.php?module=archive&sub=patient', 'fas fa-user-injured'],
        [null, 'Document Archive', 'documents.php?module=archive&sub=all', 'fas fa-archive', 'documents.php'],
        [null, 'Archived Records', 'documents.php?module=archive&sub=archived', 'fas fa-box-archive'],
        [null, 'Access Log', 'documents.php?module=archive&sub=access-log', 'fas fa-clipboard-list']]],
    ['Requests & Requisitions', ['Internal Requests'], 'fas fa-clipboard-list', null, 6, [
        ['Requests', 'Internal expense requisitions', 'requisitions.php?sub=my-requests', null],
        ['Approval & Fulfillment', 'Pending Requisitions', 'requisitions.php?sub=verification', null]]],
    ['Inventory & Stock', ['Inventory'], 'fas fa-boxes', null, 7, [
        ['Stores & Items', 'Stores', 'inventory.php?module=inventory&sub=central-store', null],
        ['Stores & Items', 'Items', 'inventory.php?module=inventory&sub=items', null],
        ['Stores & Items', 'Item Categories', 'inventory.php?module=inventory&sub=categories', null],
        ['Stores & Items', 'Record Opening', 'inventory-opening.php?module=inventory&sub=opening', null],
        ['Stock Movement', 'Transfers', 'inventory.php?module=inventory&sub=transfers', null],
        ['Stock Movement', 'Stock Movements', 'inventory.php?module=inventory&sub=stock-report', null],
        [null, 'Expired Items', 'inventory.php?module=inventory&sub=expired', null]]],
    ['Procurement and Supplier', ['Procurements'], 'fas fa-shopping-cart', null, 8, [
        [null, 'Suppliers', 'procurement.php?module=procurement&sub=suppliers', null],
        [null, 'Requisitions', 'procurement.php?module=procurement&sub=approved-requests', null],
        [null, 'Purchase Requests', 'inventory.php?module=inventory&sub=purchase-request', null],
        [null, 'Purchase Orders', 'procurement.php?module=procurement&sub=purchase-orders', null],
        [null, 'Good Received Note', 'inventory.php?module=inventory&sub=goods-receiving', null]]],
    ['Assets Management', ['Assets'], 'fas fa-toolbox', null, 9, [
        ['Asset Acquisition & Registry', 'Asset Register', 'assets.php?module=assets&sub=register', null],
        ['Asset Acquisition & Registry', 'Asset Categories', 'assets.php?module=assets&sub=categories', null, 'assets.php?module=assets&sub=opening', 'Assets Categoris'],
        ['Asset Acquisition & Registry', 'Assets Opening', 'assets.php?module=assets&sub=opening', null, null, 'Record Asset Opening'],
        ['Asset Valuation', 'Asset Depreciations', 'assets.php?module=assets&sub=depreciation', null],
        ['Asset Valuation', 'Asset Revaluation', 'assets.php?module=assets&sub=revaluation', null, 'assets&sub=revaluation'],
        ['Asset Valuation', 'Asset Capitalization', 'assets.php?module=assets&sub=capitalization', null],
        [null, 'Asset Disposal', 'assets.php?module=assets&sub=disposal', null],
        [null, 'Consolidated Asset Reports', 'assets.php?module=assets&sub=summary', null]]],
    ['Expense Management', [], 'fas fa-file-invoice-dollar', null, 10, [
        [null, 'Dashboard', 'expenses.php?module=expenses&sub=dashboard', 'fas fa-chart-pie'],
        [null, 'Add Expense', 'expenses.php?module=expenses&sub=add', 'fas fa-plus-circle'],
        [null, 'All Expenses', 'expenses.php?module=expenses&sub=expenses', 'fas fa-receipt'],
        [null, 'Pending Approvals', 'expenses.php?module=expenses&sub=approvals', 'fas fa-user-check'],
        [null, 'Payments', 'expenses.php?module=expenses&sub=payments', 'fas fa-money-check-alt'],
        [null, 'Categories', 'expenses.php?module=expenses&sub=categories', 'fas fa-tags'],
        [null, 'Suppliers', 'expenses.php?module=expenses&sub=suppliers', 'fas fa-truck'],
        [null, 'Reports', 'expenses.php?module=expenses&sub=reports', 'fas fa-chart-line']]],
    ['Finance Management', ['Finance'], 'fas fa-coins', null, 11, [
        [null, 'Chart of Accounts', 'finance.php?module=finance&sub=chart-of-accounts', null],
        [null, 'Journal Entries', 'finance.php?module=finance&sub=journal-entry', null],
        [null, 'General Ledger', 'finance.php?module=finance&sub=general-ledger', null],
        [null, 'Trial Balance', 'finance.php?module=finance&sub=trial-balance', null],
        [null, 'Balance Sheet', 'finance.php?module=finance&sub=balance-sheet', null],
        [null, 'Income Statement', 'finance.php?module=finance&sub=income-statement', null],
        [null, 'Cash Flow Statement', 'finance.php?module=finance&sub=cash-flow', null],
        [null, 'Cash & Cash Equivalents', 'finance.php?module=finance&sub=cash-equivalent', null],
        [null, 'Bank Reconciliation', 'finance.php?module=finance&sub=bank-reconciliation', null]]],
    ['Human Resources', ['HR Management'], 'fas fa-user-tie', null, 12, [
        ['Employee Management', 'Add New Employee', 'new_employee.php?module=hr&sub=new-employee', 'fas fa-user-plus'],
        ['Employee Management', 'Add Department', 'department_file.php?module=hr&sub=add-department', 'fas fa-building'],
        ['Employee Management', 'View Employees', 'hr.php?module=hr&sub=employees', null],
        ['Employee Management', 'License Tracking', 'hr.php?module=hr&sub=license-tracking', null],
        [null, 'Recruitment', 'hr.php?module=hr&sub=recruitment', null],
        ['Attendance, Duty Rosters and Shift', 'Attendance', 'hr.php?module=hr&sub=attendance', null],
        ['Attendance, Duty Rosters and Shift', 'Shift & Duty Roster', 'hr.php?module=hr&sub=shifts', null],
        [null, 'Leave and Permissions', 'hr.php?module=hr&sub=leave', null],
        [null, 'Payroll', 'hr.php?module=hr&sub=payroll', null],
        ['Performance and Staff Development', 'Performance', 'hr.php?module=hr&sub=performance', null],
        ['Performance and Staff Development', 'Training & CPD', 'hr.php?module=hr&sub=training', null],
        [null, 'Task management', 'hr.php?module=hr&sub=goals-tasks', null],
        ['Disciplinary and Employee Relations', 'Disciplinary', 'hr.php?module=hr&sub=disciplinary', null],
        ['Disciplinary and Employee Relations', 'Communications', 'hr.php?module=hr&sub=communications', null],
        [null, 'Employee Documents and Exit Management', 'hr.php?module=hr&sub=contracts', null]]],
    ['Users, Roles & Permissions', ['Settings'], 'fas fa-cogs', null, 13, [
        [null, 'Users', 'settings.php?module=admin&sub=users', null],
        [null, 'Deletion and edit Request', 'deletion-requests.php', null],
        [null, 'Roles & Access', 'settings.php?module=admin&sub=roles', null],
        [null, 'Hospital Settings', 'settings.php?module=admin&sub=hospital-settings', null],
        [null, 'Tariff settings', 'service-catalog.php?module=admin&sub=catalog', null]]],
    ['Reports & HMIS', ['Reports'], 'fas fa-chart-bar', null, 14, array_map(fn($r) => [null, $r[0], $r[1], $r[2] ?? 'fas fa-file-alt'], [
        ['General Report', 'report.php?module=reports&sub=general'], ['Visitors', 'report.php?module=reports&sub=visitors'],
        ['Doctor Sales', 'report.php?module=reports&sub=consultations'], ['Lab Sales', 'report.php?module=reports&sub=lab-sales'],
        ['Procedure Sales', 'report.php?module=reports&sub=procedure-sales'], ['Drug Sales', 'report.php?module=reports&sub=drug-sales'],
        ['Diagnosis', 'report.php?module=reports&sub=diagnosis'], ['Prescriptions', 'report.php?module=reports&sub=prescriptions'],
        ['HMIS Summary Details', 'report.php?module=reports&sub=hmis'], ['Repeat Patients', 'report.php?module=reports&sub=repeat-patients'],
        ['Doctor Performance', 'report.php?module=reports&sub=doctor-performance', 'fas fa-user-md'],
        ['Nurse Performance', 'report.php?module=reports&sub=nurse-performance', 'fas fa-user-nurse'],
        ['Repeat Diagnoses', 'report.php?module=reports&sub=repeat-diagnoses', 'fas fa-redo'],
        ['Drug Issues & Movements', 'inventory.php?module=inventory&sub=stock-issues', 'fas fa-people-carry-box'],
        ['Drug Performance', 'inventory.php?module=inventory&sub=drug-performance', 'fas fa-chart-line']])],
    ['Profile  & Security', [], 'fas fa-cogs', 'Profile  & Security', 99, [
        [null, 'Profile', 'profile.php', null],
        [null, 'Front End', 'frontend.php', null]]],
];

$usedItems = [0]; $usedMenus = [0]; $made = 0;
foreach ($sidebar as [$mTitle, $mFrom, $mIcon, $mSection, $mOrder, $mItems]) {
    $menuId = 0;
    foreach (array_merge([$mTitle], $mFrom) as $t) {
        $q = $pdo->prepare("SELECT id FROM nav_menus WHERE title = ? AND id NOT IN (" . implode(',', $usedMenus) . ") ORDER BY id LIMIT 1");
        $q->execute([$t]);
        if ($menuId = (int)$q->fetchColumn()) break;
    }
    if (!$menuId) {
        $pdo->prepare("INSERT INTO nav_menus (title, icon, section_label, menu_order) VALUES (?, ?, ?, ?)")->execute([$mTitle, $mIcon, $mSection, $mOrder]);
        $menuId = (int)$pdo->lastInsertId();
    }
    $usedMenus[] = $menuId;
    $pdo->prepare("UPDATE nav_menus SET title = ?, section_label = ?, menu_order = ?, is_active = 1 WHERE id = ?")->execute([$mTitle, $mSection, $mOrder, $menuId]);
    foreach ($mItems as $n => $it) {
        [$group, $title, $url, $icon] = $it;
        $fromUrl = $it[4] ?? $url; $fromTitle = $it[5] ?? null;
        $q = $pdo->prepare("SELECT id, icon FROM nav_menu_items WHERE url = ? AND id NOT IN (" . implode(',', $usedItems) . ")" . ($fromTitle ? " AND title = ?" : "") . " ORDER BY id LIMIT 1");
        $q->execute($fromTitle ? [$fromUrl, $fromTitle] : [$fromUrl]);
        if ($row = $q->fetch()) {
            $itemId = (int)$row['id']; $icon = $icon ?: $row['icon'];
        } else {
            $pdo->prepare("INSERT INTO nav_menu_items (menu_id, title, url, icon, item_order) VALUES (?, ?, ?, ?, 0)")->execute([$menuId, $title, $url, $icon ?: 'fas fa-file-medical']);
            $itemId = (int)$pdo->lastInsertId(); $made++;
        }
        $usedItems[] = $itemId;
        $pdo->prepare("UPDATE nav_menu_items SET menu_id = ?, title = ?, url = ?, icon = ?, item_order = ?, group_label = ?, is_active = 1 WHERE id = ?")
            ->execute([$menuId, $title, $url, $icon ?: 'fas fa-file-medical', $n + 1, $group, $itemId]);
    }
}
$ids = implode(',', $usedItems);
$gone = $pdo->query("SELECT title FROM nav_menu_items WHERE is_active = 1 AND id NOT IN ($ids)")->fetchAll(PDO::FETCH_COLUMN);
$pdo->exec("UPDATE nav_menu_items SET is_active = 0 WHERE id NOT IN ($ids)");
echo "Sidebar laid out in the Michel structure: $made new page link(s); hidden duplicates: " . ($gone ? implode(', ', $gone) : 'none') . "\n";

<?php
// includes/dash/procurement.php — Procurement Officer dashboard.
// Live system = purchase_requests / purchase_orders / supplier_invoices (what procurement.php and the inventory handlers write).
// The pm_* tables are not referenced by any page or handler, so they are not used here.
[$from, $to] = dx_range('month');
$po = 'procurement.php?module=procurement&sub=purchase-orders';
$deliv = 'procurement.php?module=procurement&sub=expected-deliveries';
$inv = 'procurement.php?module=procurement&sub=supplier-invoices';
$inbox = 'approval-inbox.php';

// Open PO = issued but not fully received: sent, confirmed, partially_received (draft is not yet with the supplier).
$open = "po.status IN ('sent','confirmed','partially_received')";

// Requests awaiting approval: purchase_requests.status = 'pending' (same rule as the approval inbox).
$prPending = (int)dx_val($pdo, "SELECT COUNT(*) FROM purchase_requests WHERE status = 'pending'");
$poOpen = (int)dx_val($pdo, "SELECT COUNT(*) FROM purchase_orders po WHERE $open");
// Overdue delivery: open PO whose delivery_date is before today.
$overdue = (int)dx_val($pdo, "SELECT COUNT(*) FROM purchase_orders po WHERE $open AND po.delivery_date < CURDATE()");
// Unpaid supplier invoices: posted or partially_paid; amount owed = amount - paid_amount.
$unpaid = dx_rows($pdo, "SELECT COUNT(*) n, COALESCE(SUM(amount - paid_amount), 0) owed FROM supplier_invoices WHERE status IN ('posted','partially_paid')")[0] ?? ['n' => 0, 'owed' => 0];
// Spend this period = total_amount of purchase orders raised (order_date in range) that are not draft or cancelled.
// There is no received-date column, so order_date is used.
$spendWhere = "po.status NOT IN ('draft','cancelled') AND po.order_date >= ? AND po.order_date <= ?";
$spend = (float)dx_val($pdo, "SELECT SUM(po.total_amount) FROM purchase_orders po WHERE $spendWhere", [$from, $to]);
// Items below reorder: items whose active stock (all batches/stores) is at or under their reorder level (level > 0).
$low = (int)dx_val($pdo, "SELECT COUNT(*) FROM (SELECT item_id FROM stock_batches WHERE is_active = 1 GROUP BY item_id HAVING MAX(reorder_level) > 0 AND SUM(quantity) <= MAX(reorder_level)) x");

echo dx_head('Procurement', "$greeting, $firstName. Purchase requests, orders and supplier invoices.", [$from, $to]);

echo dx_attention([
    ['deliveries overdue', $deliv, $overdue],
    ['requests awaiting approval', $inbox, $prPending],
    ['supplier invoices unpaid', $inv, $unpaid['n']],
]);

echo dx_cards([
    ['Requests awaiting approval', dx_num($prPending), 'fa-file-signature', '#d97706', 'status pending', $inbox],
    ['Open purchase orders', dx_num($poOpen), 'fa-file-invoice', '#2563eb', 'sent, confirmed or part-received', $po],
    ['Deliveries overdue', dx_num($overdue), 'fa-truck', '#dc2626', 'expected date passed, not received', $deliv],
    ['Supplier invoices unpaid', dx_num($unpaid['n']), 'fa-money-bill-wave', '#7c3aed', dx_money($unpaid['owed']) . ' owed', $inv],
    ['Spend this period', dx_money($spend), 'fa-coins', '#059669', 'purchase orders raised, excl. draft/cancelled', $po],
    ['Items below reorder level', dx_num($low), 'fa-boxes-stacked', '#ea580c', 'stock at or under minimum', 'inventory.php?module=inventory'],
]);

// Table: open POs, most overdue first (POs without an expected date last).
$rows = [];
foreach (dx_rows($pdo, "SELECT po.po_number, s.company_name, po.total_amount, po.delivery_date, DATEDIFF(CURDATE(), po.delivery_date) late
        FROM purchase_orders po LEFT JOIN suppliers s ON s.id = po.supplier_id WHERE $open
        ORDER BY po.delivery_date IS NULL, po.delivery_date LIMIT 10") as $r) {
    $late = $r['delivery_date'] === null ? null : (int)$r['late'];
    $rows[] = [$r['po_number'], $r['company_name'] ?? '-', dx_money($r['total_amount']), $r['delivery_date'] ?? '-',
        ['html' => $late !== null && $late > 0 ? dx_badge($late . ' days', '#dc2626') : dx_badge($late === null ? 'n/a' : 'On time', '#059669')]];
}
$t1 = dx_table('Open purchase orders', ['PO', 'Supplier', '>Total', 'Expected', 'Overdue'], $rows, 'No open purchase orders', $po);

// Table: requests awaiting action (status pending), oldest first.
$rows = [];
foreach (dx_rows($pdo, "SELECT pr.pr_number, d.name dept, CONCAT_WS(' ', u.first_name, u.last_name) who, pr.request_date, pr.total_estimated
        FROM purchase_requests pr LEFT JOIN departments d ON d.id = pr.department_id LEFT JOIN users u ON u.id = pr.requested_by
        WHERE pr.status = 'pending' ORDER BY pr.request_date, pr.id LIMIT 10") as $r)
    $rows[] = [$r['pr_number'], trim(($r['dept'] ?? '') . ' ' . ($r['who'] !== '' ? '(' . $r['who'] . ')' : '')) ?: '-', $r['request_date'], dx_money($r['total_estimated'])];
$t2 = dx_table('Requests awaiting action', ['Number', 'Department / requester', 'Date', '>Amount'], $rows, 'No requests waiting', $inbox);
echo dx_grid([$t1, $t2], 2);

// Graph: spend per supplier in the period, top 10 (same spend definition as the card).
$items = [];
foreach (dx_rows($pdo, "SELECT COALESCE(s.company_name, 'Unknown') n, SUM(po.total_amount) v FROM purchase_orders po LEFT JOIN suppliers s ON s.id = po.supplier_id
        WHERE $spendWhere GROUP BY po.supplier_id, n ORDER BY v DESC LIMIT 10", [$from, $to]) as $r) $items[$r['n']] = (float)$r['v'];
// Graph: spend per month, last 6 months (same definition, ignores the date filter).
[$labels, $keys] = dx_months(6);
$by = [];
foreach (dx_rows($pdo, "SELECT DATE_FORMAT(po.order_date, '%Y-%m') m, SUM(po.total_amount) v FROM purchase_orders po
        WHERE po.status NOT IN ('draft','cancelled') AND po.order_date >= ? GROUP BY m", [$keys[0] . '-01']) as $r) $by[$r['m']] = (float)$r['v'];
$vals = array_map(fn($k) => $by[$k] ?? 0, $keys);
echo dx_grid([dx_bars('Spend per supplier (top 10)', $items, 'dx_money', '#2563eb'), dx_cols('Monthly spend, last 6 months', $labels, ['Spend' => $vals], ['fmt' => 'dx_money'])], 2);

echo dx_end();

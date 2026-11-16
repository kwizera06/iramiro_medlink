<?php
// includes/dash/storekeeper.php — Storekeeper dashboard. Plain PHP, echoes html. Helpers: includes/dash_ui.php.
// Stock = stock_batches (is_active=1). Quantity <= 0 is ignored for value/expiry/low-stock; negative stock is reported on its own.
// Minimum stock lives per batch (reorder_level, or min_stock_level when reorder_level is 0); an item+store is "below minimum" when
// its positive on-hand total is <= that minimum and the minimum is set (> 0), same rule as the inventory page.
// Requisitions: the Storekeeper fulfils request_management (requests.php?sub=fulfillment). The old requisitions table auto-issues on approval.
[$from, $to] = dx_range('month');
$INV = 'inventory.php?module=inventory';

// Cards
$lowSql = "SELECT sb.item_id, sb.store_id, SUM(GREATEST(sb.quantity,0)) onhand, MAX(GREATEST(COALESCE(NULLIF(sb.reorder_level,0), 0), sb.min_stock_level)) minq
           FROM stock_batches sb WHERE sb.is_active = 1 GROUP BY sb.item_id, sb.store_id HAVING minq > 0 AND onhand <= minq";
$lowCount  = (int)dx_val($pdo, "SELECT COUNT(*) FROM ($lowSql) t");                                   // item+store rows at/below minimum
$expSoon   = (int)dx_val($pdo, "SELECT COUNT(*) FROM stock_batches WHERE is_active=1 AND quantity>0 AND expiry_date >= CURDATE() AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)");
$expired   = (int)dx_val($pdo, "SELECT COUNT(*) FROM stock_batches WHERE is_active=1 AND quantity>0 AND expiry_date < CURDATE()");
$negative  = (int)dx_val($pdo, "SELECT COUNT(*) FROM stock_batches WHERE is_active=1 AND quantity<0");
$reqWait   = (int)dx_val($pdo, "SELECT COUNT(*) FROM request_management WHERE status IN ('approved','partially_fulfilled')");  // approved, not fully issued yet
$trfOpen   = (int)dx_val($pdo, "SELECT COUNT(*) FROM stock_transfers WHERE status IN ('pending','approved','in_transit')");
$stockVal  = (float)dx_val($pdo, "SELECT SUM(quantity*unit_cost) FROM stock_batches WHERE is_active=1 AND quantity>0");

// Tables
$lowRows = [];
foreach (dx_rows($pdo, "SELECT i.name item, s.name store, t.onhand, t.minq FROM ($lowSql) t JOIN items i ON i.id=t.item_id JOIN stores s ON s.id=t.store_id
                        ORDER BY t.onhand/t.minq, i.name LIMIT 10") as $r)
    $lowRows[] = [$r['item'], $r['store'], dx_num($r['onhand']), dx_num($r['minq'])];

$expRows = [];
foreach (dx_rows($pdo, "SELECT i.name item, s.name store, sb.batch_number, sb.quantity, sb.expiry_date, DATEDIFF(sb.expiry_date, CURDATE()) days
                        FROM stock_batches sb JOIN items i ON i.id=sb.item_id JOIN stores s ON s.id=sb.store_id
                        WHERE sb.is_active=1 AND sb.quantity>0 AND sb.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
                        ORDER BY sb.expiry_date, i.name LIMIT 10") as $r) {
    $d = (int)$r['days'];
    $expRows[] = [$r['item'], $r['store'], (string)$r['batch_number'], dx_num($r['quantity']), date('d M Y', strtotime($r['expiry_date'])),
                  ['html' => $d < 0 ? dx_badge('Expired ' . (-$d) . 'd ago', '#dc2626') : dx_badge($d . ' days', $d <= 30 ? '#ea580c' : '#d97706')]];
}

// Graphs
$valByStore = [];   // stock value per store
foreach (dx_rows($pdo, "SELECT s.name, SUM(sb.quantity*sb.unit_cost) v FROM stock_batches sb JOIN stores s ON s.id=sb.store_id
                        WHERE sb.is_active=1 AND sb.quantity>0 GROUP BY s.id, s.name") as $r) $valByStore[$r['name']] = (float)$r['v'];

$issued = [];       // issues per department = value of request_management issues (transfer_out to a department store) in the period
foreach (dx_rows($pdo, "SELECT s.name, SUM(m.quantity*m.unit_cost) v FROM stock_movements m JOIN stores s ON s.id=m.to_store_id
                        WHERE m.movement_type='transfer_out' AND m.reference_type='request_management' AND s.type='department'
                          AND m.created_at >= ? AND m.created_at < DATE_ADD(?, INTERVAL 1 DAY) GROUP BY s.id, s.name", [$from, $to]) as $r) $issued[$r['name']] = (float)$r['v'];

echo dx_head('Stock overview', 'Stock levels, expiry and requests for stores', [$from, $to]);
echo dx_attention([
    ['expired batches still in stock', $INV . '&sub=stock', $expired],
    ['items below minimum stock', $INV . '&sub=stock', $lowCount],
    ['requests waiting to be issued', 'requests.php?sub=fulfillment', $reqWait],
    ['batches with negative stock', $INV . '&sub=stock', $negative],
]);
echo dx_cards([
    ['Below minimum', dx_num($lowCount), 'fa-arrow-trend-down', '#dc2626', 'item / store at or under minimum', $INV . '&sub=stock'],
    ['Expiring in 90 days', dx_num($expSoon), 'fa-hourglass-half', '#d97706', 'batches with stock', $INV . '&sub=stock'],
    ['Expired in stock', dx_num($expired), 'fa-triangle-exclamation', '#dc2626', 'batches still on hand', $INV . '&sub=stock'],
    ['Waiting to be issued', dx_num($reqWait), 'fa-dolly', '#2563eb', 'approved requests', 'requests.php?sub=fulfillment'],
    ['Transfers in progress', dx_num($trfOpen), 'fa-truck-ramp-box', '#7c3aed', 'pending, approved or in transit', $INV . '&sub=transfers'],
    ['Stock value', dx_money($stockVal), 'fa-coins', '#059669', 'at cost, positive stock only', $INV . '&sub=stock'],
]);
echo dx_grid([
    dx_table('Low-stock items', ['Item', 'Store', '>On hand', '>Minimum'], $lowRows, 'No items below minimum', $INV . '&sub=stock'),
    dx_table('Expiring batches (90 days)', ['Item', 'Store', 'Batch', '>Qty', 'Expiry', 'Days left'], $expRows, 'Nothing expiring soon', $INV . '&sub=stock'),
], 2);
echo dx_grid([
    dx_bars('Stock value per store', $valByStore, 'dx_money', '#059669'),
    dx_bars('Issues per department (value, this period)', $issued, 'dx_money', '#2563eb'),
], 2);
echo dx_end();

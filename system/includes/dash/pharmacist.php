<?php
// includes/dash/pharmacist.php — Pharmacist dashboard. Plain PHP, echoes html. Helpers: includes/dash_ui.php.
// Pharmacy dispenses and sells from store 1005 (Clinic Store), see handlers/pharmacy/pharmacy.php. Drugs = items.is_medication=1.
// Minimum per batch (reorder_level, or min_stock_level when reorder_level is 0), compared with positive on-hand of the drug in that store.
[$from, $to] = dx_range('today');
$PH = 'pharmacy.php?module=pharmacy';
$STORE = 1005;
$range = [$from, $to];
$inRange = fn($col) => "$col >= ? AND $col < DATE_ADD(?, INTERVAL 1 DAY)";

// Cards
$waiting   = (int)dx_val($pdo, "SELECT COUNT(*) FROM pharmacy_prescriptions WHERE destination='hospital' AND status IN ('active','dispensed_partial')");   // still to (fully) dispense
$dispensed = (int)dx_val($pdo, "SELECT COUNT(DISTINCT prescription_item_id) FROM pharmacy_dispensing_log WHERE " . $inRange('dispensed_at'), $range);      // prescription lines dispensed
$sales     = (float)dx_val($pdo, "SELECT SUM(total_amount) FROM pharmacy_sales WHERE status='completed' AND sale_date >= ? AND sale_date <= ?", $range);
$lowSql = "SELECT sb.item_id, SUM(GREATEST(sb.quantity,0)) onhand, MAX(GREATEST(COALESCE(NULLIF(sb.reorder_level,0), 0), sb.min_stock_level)) minq
           FROM stock_batches sb JOIN items i ON i.id=sb.item_id AND i.is_medication=1
           WHERE sb.is_active=1 AND sb.store_id = ? GROUP BY sb.item_id HAVING minq > 0 AND onhand <= minq";
$lowCount  = (int)dx_val($pdo, "SELECT COUNT(*) FROM ($lowSql) t", [$STORE]);
$expSoon   = (int)dx_val($pdo, "SELECT COUNT(*) FROM stock_batches sb JOIN items i ON i.id=sb.item_id AND i.is_medication=1
                                WHERE sb.is_active=1 AND sb.store_id=? AND sb.quantity>0 AND sb.expiry_date >= CURDATE() AND sb.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)", [$STORE]);
// dispense_shortfalls has no status column: every row counts. Counted for the period.
$shorts    = (int)dx_val($pdo, "SELECT COUNT(*) FROM dispense_shortfalls WHERE shortfall > 0 AND " . $inRange('created_at'), $range);

// Tables
$queue = [];   // oldest waiting prescriptions first
foreach (dx_rows($pdo, "SELECT p.id, p.created_at, TRIM(CONCAT(pt.first_name,' ',pt.last_name)) patient, v.visit_no,
                               COALESCE(NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), v.doctor_name, '') doctor,
                               (SELECT COUNT(*) FROM pharmacy_prescription_items x WHERE x.prescription_id = p.id) n
                        FROM pharmacy_prescriptions p LEFT JOIN patients pt ON pt.id=p.patient_id LEFT JOIN visits v ON v.id=p.visit_id LEFT JOIN users u ON u.id=p.doctor_id
                        WHERE p.destination='hospital' AND p.status IN ('active','dispensed_partial') ORDER BY p.created_at LIMIT 10") as $r)
    $queue[] = [$r['patient'], trim(($r['visit_no'] ?? '') . ' ' . $r['doctor']), dx_num($r['n']), date('d M H:i', strtotime($r['created_at']))];

$reorder = [];
foreach (dx_rows($pdo, "SELECT i.name, t.onhand, t.minq FROM ($lowSql) t JOIN items i ON i.id=t.item_id ORDER BY t.onhand/t.minq, i.name LIMIT 10", [$STORE]) as $r)
    $reorder[] = [$r['name'], dx_num($r['onhand']), dx_num($r['minq'])];

// Graphs
$top = [];   // top 10 drugs by quantity dispensed in the period
foreach (dx_rows($pdo, "SELECT i.name, SUM(l.quantity_dispensed) q FROM pharmacy_dispensing_log l JOIN items i ON i.id=l.item_id
                        WHERE " . $inRange('l.dispensed_at') . " GROUP BY i.id, i.name ORDER BY q DESC LIMIT 10", $range) as $r) $top[$r['name']] = (float)$r['q'];

$byDay = [];   // sales per day
foreach (dx_rows($pdo, "SELECT sale_date d, SUM(total_amount) v FROM pharmacy_sales WHERE status='completed' AND sale_date >= ? AND sale_date <= ? GROUP BY sale_date", $range) as $r) $byDay[$r['d']] = (float)$r['v'];
[$labels, $vals] = dx_fill_days($byDay, $from, $to);

echo dx_head('Pharmacy overview', 'Prescriptions, sales and drug stock', [$from, $to]);
echo dx_attention([
    ['prescriptions waiting', $PH . '&sub=prescription', $waiting],
    ['drugs below minimum', $PH . '&sub=stock', $lowCount],
    ['dispense shortfalls', $PH . '&sub=dispensing', $shorts],
]);
echo dx_cards([
    ['Waiting to dispense', dx_num($waiting), 'fa-prescription-bottle-medical', '#2563eb', 'active or part-dispensed', $PH . '&sub=prescription'],
    ['Dispensed', dx_num($dispensed), 'fa-pills', '#059669', 'prescription lines in period', $PH . '&sub=dispensing'],
    ['Sales', dx_money($sales), 'fa-cash-register', '#7c3aed', 'completed sales in period', $PH . '&sub=pos'],
    ['Drugs below minimum', dx_num($lowCount), 'fa-arrow-trend-down', '#dc2626', 'in the pharmacy store', $PH . '&sub=stock'],
    ['Drugs expiring in 90 days', dx_num($expSoon), 'fa-hourglass-half', '#d97706', 'batches with stock', $PH . '&sub=stock'],
    ['Dispense shortfalls', dx_num($shorts), 'fa-circle-exclamation', '#ea580c', 'recorded in period', $PH . '&sub=dispensing'],
]);
echo dx_grid([
    dx_table('Prescription queue', ['Patient', 'Visit / prescriber', '>Items', 'Waiting since'], $queue, 'No prescriptions waiting', $PH . '&sub=prescription'),
    dx_table('Drugs to re-order', ['Drug', '>On hand', '>Minimum'], $reorder, 'No drugs below minimum', $PH . '&sub=stock'),
], 2);
echo dx_grid([
    dx_bars('Top drugs dispensed (quantity)', $top, null, '#059669'),
    dx_cols('Sales per day', $labels, ['Sales' => $vals], ['type' => 'bar', 'fmt' => 'dx_money']),
], 2);
echo dx_end();

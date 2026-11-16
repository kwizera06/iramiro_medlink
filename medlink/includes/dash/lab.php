<?php
// includes/dash/lab.php — Lab Technician dashboard. Lab orders = medical_orders.order_type='lab'.
// Workflow: ordered (waiting for sample) -> in_progress (sample collected, result pending) -> completed (result entered) -> verified (verified_at set).
[$from, $to] = dx_range('today');
$lo = $from; $hi = $to;                       // date range, used as: col >= lo AND col < hi + 1 day
$rng = fn($col) => "$col >= ? AND $col < DATE_ADD(?, INTERVAL 1 DAY)";
$lab = "order_type = 'lab'";

// Current queue (not limited to the period: work waiting is work waiting).
$waiting   = (int)dx_val($pdo, "SELECT COUNT(*) FROM medical_orders WHERE $lab AND status = 'ordered'");        // no sample yet
$inProg    = (int)dx_val($pdo, "SELECT COUNT(*) FROM medical_orders WHERE $lab AND status = 'in_progress'");    // sample taken, no result yet
$toVerify  = (int)dx_val($pdo, "SELECT COUNT(*) FROM medical_orders WHERE $lab AND status = 'completed' AND verified_at IS NULL");
$old24     = (int)dx_val($pdo, "SELECT COUNT(*) FROM medical_orders WHERE $lab AND status IN ('ordered','in_progress') AND ordered_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");

// In the period: completed by completed_at; turnaround = ordered_at -> completed_at.
$done = dx_rows($pdo, "SELECT COUNT(*) n, AVG(TIMESTAMPDIFF(MINUTE, ordered_at, completed_at)) / 60 h FROM medical_orders WHERE $lab AND status = 'completed' AND " . $rng('completed_at'), [$lo, $hi])[0] ?? ['n' => 0, 'h' => null];
$avgH = $done['h'] === null ? null : (float)$done['h'];

// Supplies in the Laboratory store (code DEPT-36): items whose active stock <= their minimum level (items with no minimum set are ignored).
$low = (int)dx_val($pdo, "SELECT COUNT(*) FROM (SELECT sb.item_id FROM stock_batches sb JOIN stores s ON s.id = sb.store_id AND s.code = 'DEPT-36'
    WHERE sb.is_active = 1 GROUP BY sb.item_id HAVING MAX(sb.min_stock_level) > 0 AND SUM(sb.quantity) <= MAX(sb.min_stock_level)) x");

// Queue tables. Priority is only stored inside medical_orders.details as "[Priority: urgent]".
// age comes from SQL (TIMESTAMPDIFF vs NOW()) so PHP and MySQL timezones cannot disagree
$age = function ($m) { $m = max(0, (int)$m); return $m >= 1440 ? floor($m / 1440) . 'd ' . floor($m % 1440 / 60) . 'h' : ($m >= 60 ? floor($m / 60) . 'h ' . ($m % 60) . 'm' : $m . 'm'); };
$prio = function ($d) { return preg_match('/\[Priority:\s*([^\]]+)\]/i', (string)$d, $m) ? trim($m[1]) : 'routine'; };
$prioCell = fn($p) => strtolower($p) === 'routine' ? 'Routine' : ['html' => dx_badge(ucfirst($p), '#dc2626')];

$q = dx_rows($pdo, "SELECT mo.description, mo.status, mo.ordered_at, TIMESTAMPDIFF(MINUTE, mo.ordered_at, NOW()) mins, mo.details, CONCAT(p.first_name, ' ', p.last_name) pname
    FROM medical_orders mo LEFT JOIN patients p ON p.id = mo.patient_id
    WHERE mo.order_type = 'lab' AND mo.status IN ('ordered','in_progress') ORDER BY mo.ordered_at ASC LIMIT 15");
$queueRows = array_map(fn($r) => [$r['pname'], $r['description'], date('d/m H:i', strtotime($r['ordered_at'])), $age($r['mins']),
    ['html' => dx_badge($r['status'] === 'ordered' ? 'Needs sample' : 'Awaiting result', $r['status'] === 'ordered' ? '#d97706' : '#2563eb')], $prioCell($prio($r['details']))], $q);

$v = dx_rows($pdo, "SELECT mo.description, mo.completed_at, TIMESTAMPDIFF(MINUTE, mo.completed_at, NOW()) mins, CONCAT(p.first_name, ' ', p.last_name) pname, CONCAT(u.first_name, ' ', u.last_name) uname
    FROM medical_orders mo LEFT JOIN patients p ON p.id = mo.patient_id LEFT JOIN users u ON u.id = mo.completed_by
    WHERE mo.order_type = 'lab' AND mo.status = 'completed' AND mo.verified_at IS NULL ORDER BY mo.completed_at ASC LIMIT 10");
$verRows = array_map(fn($r) => [$r['pname'], $r['description'], $r['completed_at'] ? date('d/m H:i', strtotime($r['completed_at'])) : '', $r['completed_at'] ? $age($r['mins']) : '', $r['uname']], $v);

// Graphs (period, by ordered_at, cancelled excluded): tests per day, most requested tests.
$byDay = []; foreach (dx_rows($pdo, "SELECT DATE(ordered_at) d, COUNT(*) n FROM medical_orders WHERE $lab AND status <> 'cancelled' AND " . $rng('ordered_at') . " GROUP BY d", [$lo, $hi]) as $r) $byDay[$r['d']] = $r['n'];
[$dl, $dv] = dx_fill_days($byDay, $from, $to);
$top = []; foreach (dx_rows($pdo, "SELECT description, COUNT(*) n FROM medical_orders WHERE $lab AND status <> 'cancelled' AND " . $rng('ordered_at') . " GROUP BY description ORDER BY n DESC LIMIT 10", [$lo, $hi]) as $r) $top[$r['description']] = $r['n'];

echo dx_head('Laboratory', $greeting . ', ' . $firstName . '. Tests waiting, results to verify and supplies.', [$from, $to]);
echo dx_attention([
    ['results awaiting verification', 'lab.php', $toVerify],
    ['tests pending for more than 24 hours', 'lab.php', $old24],
    ['lab supplies below minimum', 'inventory.php', $low],
]);
echo dx_cards([
    ['Tests waiting for a sample', dx_num($waiting), 'fa-vial', '#d97706', 'Ordered, no sample yet (now)', 'lab.php'],
    ['Tests in progress', dx_num($inProg), 'fa-flask', '#2563eb', 'Sample taken, no result yet (now)', 'lab.php'],
    ['Results waiting for verification', dx_num($toVerify), 'fa-clipboard-check', '#7c3aed', 'Completed, not yet verified (now)', 'lab.php'],
    ['Completed in the period', dx_num($done['n']), 'fa-circle-check', '#059669', 'Results entered in the date range', 'lab.php'],
    ['Average turnaround', $avgH === null ? '-' : number_format($avgH, 1) . ' h', 'fa-stopwatch', '#0891b2', 'Ordered to completed, tests completed in the period'],
    ['Lab supplies below minimum', dx_num($low), 'fa-boxes-stacked', '#dc2626', 'Laboratory store items at or under minimum', 'inventory.php'],
]);
echo dx_grid([
    dx_table('Pending test queue (oldest first)', ['Patient', 'Test', 'Ordered', 'Waiting', 'Stage', 'Priority'], $queueRows, 'No tests waiting', 'lab.php'),
    dx_table('Results awaiting verification', ['Patient', 'Test', 'Completed', 'Waiting', 'Entered by'], $verRows, 'Nothing to verify', 'lab.php'),
], 2);
echo dx_grid([
    dx_cols('Tests per day (ordered)', $dl, ['Tests' => $dv]),
    dx_bars('Most requested tests', $top),
], 2);
echo dx_end();

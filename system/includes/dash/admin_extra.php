<?php
// includes/dash/admin_extra.php — two graphs under the admin's clinical dashboard; fixed last 30 days, not tied to the page date filter.
$to = date('Y-m-d'); $from = date('Y-m-d', strtotime('-29 days'));
[$labels, $rev] = dx_fill_days(dx_metric($pdo, 'billed', $from, $to), $from, $to);       // revenue = billed
[, $exp] = dx_fill_days(dx_metric($pdo, 'expenses', $from, $to), $from, $to);
// Visits per department (not cancelled) by visit_date.
$v = array_column(dx_rows($pdo, "SELECT d.name, COUNT(*) n FROM visits v JOIN departments d ON d.id = v.department_id
    WHERE v.status <> 'cancelled' AND v.visit_date BETWEEN ? AND ? GROUP BY d.id, d.name", [$from, $to]), 'n', 'name');
echo dx_styles() . '<div class="dx"><div class="dx-head"><h2 class="dx-title" style="font-size:17px">Last 30 days</h2></div>'
    . dx_grid([
        dx_cols('Revenue vs expenses', $labels, ['Revenue' => $rev, 'Expenses' => $exp], ['type' => 'line']),
        dx_bars('Visits per department', $v),
    ], 2) . dx_end();

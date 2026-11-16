<?php
// includes/dash/managing_director.php — Managing Director: whole-hospital view. Billed/collected/expenses come from dx_metric (dash_metrics.php).
[$from, $to] = dx_range('month');
$days = (int)((strtotime($to) - strtotime($from)) / 86400) + 1;          // length of the period in days
$pTo = date('Y-m-d', strtotime("$from -1 day"));                          // previous period of equal length, ends the day before
$pFrom = date('Y-m-d', strtotime("$pTo -" . ($days - 1) . ' days'));

$billed = array_sum(dx_metric($pdo, 'billed', $from, $to));
$prevBilled = array_sum(dx_metric($pdo, 'billed', $pFrom, $pTo));
$expenses = array_sum(dx_metric($pdo, 'expenses', $from, $to));
$net = $billed - $expenses;                                               // net result = billed - expenses
$chg = $prevBilled > 0 ? ($billed - $prevBilled) / $prevBilled * 100 : null;
$revFoot = $chg === null ? 'No previous-period revenue' : sprintf('%+.1f%% vs previous', $chg);

// Patients served = visits dated in the period, not cancelled.
$visits = (int)dx_val($pdo, "SELECT COUNT(*) FROM visits WHERE status <> 'cancelled' AND visit_date BETWEEN ? AND ?", [$from, $to]);

// Patient receivables (all-time) = SUM(invoices.balance) of open invoices.
$patOwed = (float)dx_val($pdo, "SELECT SUM(balance) FROM invoices WHERE status NOT IN ('cancelled','paid','draft')");
// Insurer receivables (all-time) = per insurance/mixed invoice: total - balance (patient share) - insurer_paid_amount, never below 0.
$insRows = dx_rows($pdo, "SELECT COALESCE(c.name,'Unknown insurer') name, COUNT(*) n, SUM(GREATEST(0, i.total - i.balance - i.insurer_paid_amount)) owed
    FROM invoices i LEFT JOIN insurance_companies c ON c.id = i.insurance_id
    WHERE i.payment_type IN ('insurance','mixed') AND i.status NOT IN ('cancelled','paid','draft')
    GROUP BY i.insurance_id, c.name HAVING owed > 0 ORDER BY owed DESC");
$insOwed = array_sum(array_column($insRows, 'owed'));

// Stock value = sum(quantity * unit_cost) of batches with stock; asset value = current book value of assets still held.
$stock = (float)dx_val($pdo, "SELECT SUM(quantity * unit_cost) FROM stock_batches WHERE quantity > 0");
$assets = (float)dx_val($pdo, "SELECT SUM(current_value) FROM assets WHERE status IN ('active','under_maintenance')");

// Departments: visits from visits.department_id (invoices.department_id is not filled); billed = non-cancelled invoices of those visits created in the period.
$deptRows = dx_rows($pdo, "SELECT d.name,
        (SELECT COUNT(*) FROM visits v WHERE v.department_id = d.id AND v.status <> 'cancelled' AND v.visit_date BETWEEN ? AND ?) visits,
        (SELECT COALESCE(SUM(i.total),0) FROM invoices i JOIN visits v ON v.id = i.visit_id WHERE v.department_id = d.id AND i.status <> 'cancelled'
            AND i.created_at >= ? AND i.created_at < DATE_ADD(?, INTERVAL 1 DAY)) billed
    FROM departments d HAVING visits > 0 OR billed > 0 ORDER BY billed DESC, visits DESC", [$from, $to, $from, $to]);

// Revenue vs expenses, last 6 months.
[$mLabels, $mKeys] = dx_months(6);
$mb = dx_metric($pdo, 'billed', $mKeys[0] . '-01', date('Y-m-d'), 'month');
$me = dx_metric($pdo, 'expenses', $mKeys[0] . '-01', date('Y-m-d'), 'month');
$sRev = $sExp = [];
foreach ($mKeys as $k) { $sRev[] = $mb[$k] ?? 0; $sExp[] = $me[$k] ?? 0; }

echo dx_head('Managing Director', "$greeting, $firstName. Hospital overview.", [$from, $to]);
echo dx_cards([
    ['Revenue (billed)', dx_money($billed), 'fa-coins', '#2563eb', $revFoot],
    ['Expenses', dx_money($expenses), 'fa-wallet', '#d97706'],
    ['Net result', dx_money($net), 'fa-scale-balanced', $net < 0 ? '#dc2626' : '#059669', 'Billed minus expenses'],
    ['Outstanding receivables', dx_money($patOwed + $insOwed), 'fa-file-invoice-dollar', '#7c3aed', 'Patients ' . dx_money($patOwed) . ' · Insurers ' . dx_money($insOwed)],
    ['Patients served', dx_num($visits), 'fa-users', '#0891b2', 'Visits in period'],
    ['Stock + asset value', dx_money($stock + $assets), 'fa-boxes-stacked', '#475569', 'Stock ' . dx_money($stock) . ' · Assets ' . dx_money($assets)],
]);
$dRows = array_map(fn($r) => [$r['name'], dx_num($r['visits']), dx_money($r['billed'])], $deptRows);
$iRows = array_map(fn($r) => [$r['name'], dx_num($r['n']), dx_money($r['owed'])], array_slice($insRows, 0, 5));
echo dx_grid([
    dx_table('Department performance', ['Department', '>Visits', '>Billed'], $dRows, 'No activity in this period'),
    dx_table('Top 5 insurers by amount owed', ['Insurer', '>Invoices', '>Owed'], $iRows, 'No insurer balances', 'insurance.php?module=insurance&sub=receivables'),
], 2);
echo dx_grid([
    dx_cols('Revenue vs expenses, last 6 months', $mLabels, ['Revenue' => $sRev, 'Expenses' => $sExp], ['fmt' => 'dx_num']),
    dx_donut('Revenue by department', array_column($deptRows, 'billed', 'name'), 'dx_money'),
], 2);
echo dx_end();

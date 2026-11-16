<?php
// includes/dash_metrics.php — THE definitions of billed / collected / expenses, shared by every dashboard so the numbers agree.
//   billed    = invoices.total, status <> 'cancelled', by DATE(created_at)
//   collected = payment_records.amount_paid, status = 'completed', by payment_date
//   expenses  = finance_expenses.amount, status <> 'voided', by expense_date
// All return [bucket => amount]: bucket is Y-m-d ($group 'day') or Y-m ($group 'month'). Use array_sum() for a total.

require_once __DIR__ . '/dash_ui.php';

if (!function_exists('dx_metric')) {
function dx_metric(PDO $pdo, string $name, string $from, string $to, string $group = 'day'): array {
    $fmt = $group === 'month' ? '%Y-%m' : '%Y-%m-%d';
    $src = [
        'billed'    => ['invoices',         'created_at',   'total',       "status <> 'cancelled'", true],
        'collected' => ['payment_records',  'payment_date', 'amount_paid', "status = 'completed'", false],
        'expenses'  => ['finance_expenses', 'expense_date', 'amount',      "status <> 'voided'", false],
    ][$name] ?? null;
    if (!$src) return [];
    [$tbl, $col, $amt, $where, $isTs] = $src;
    $lo = $isTs ? "$col >= ?" : "DATE($col) >= ?"; $hi = $isTs ? "$col < DATE_ADD(?, INTERVAL 1 DAY)" : "DATE($col) <= ?";
    $rows = dx_rows($pdo, "SELECT DATE_FORMAT($col, '$fmt') b, SUM($amt) v FROM $tbl WHERE $where AND $lo AND $hi GROUP BY b ORDER BY b", [$from, $to]);
    $out = []; foreach ($rows as $r) $out[$r['b']] = (float)$r['v'];
    return $out;
}
/** Last $n calendar months ending this month: [labels like 'Nov', keys like '2026-11']. */
function dx_months(int $n): array {
    $labels = $keys = [];
    for ($i = $n - 1; $i >= 0; $i--) { $t = strtotime("first day of -$i month"); $labels[] = date('M', $t); $keys[] = date('Y-m', $t); }
    return [$labels, $keys];
}
}

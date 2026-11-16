<?php
// Cashier / receptionist dashboard: front-desk money only (no insurance claims, no expenses).
[$from, $to] = dx_range('today');
echo dx_head('Cash desk', $today, [$from, $to]);
$uid = (int)($_SESSION['user_id'] ?? 0);
$invLink = 'invoicing.php?module=finance&sub=invoices';

// payment_records.created_by holds an EMPLOYEE id (handlers/patients/billing.php), not a user id. Map it the same way
// billing does: employee id == user id when such an employee exists, else users.employee_id = employees.employee_no.
$empId = 0;
if ($uid) {
    $empId = (int)dx_val($pdo, "SELECT id FROM employees WHERE id = ?", [$uid]);
    if (!$empId) $empId = (int)dx_val($pdo, "SELECT e.id FROM users u JOIN employees e ON e.employee_no = u.employee_id WHERE u.id = ?", [$uid]);
}

// "My" payments = recorded by my employee record (payment_records.created_by), or, when billing could not store an employee
// (created_by is NULL because the user has no employee row), the invoice's payment_received_by, which is the user id.
$mineSql = "(pr.created_by = ? OR (pr.created_by IS NULL AND i.payment_received_by = ?))";
$mineArgs = [$empId, $uid];
// Collected by me: completed payments I recorded, by payment_date.
$mine = dx_rows($pdo, "SELECT pr.payment_date d, pr.payment_method m, SUM(pr.amount_paid) s FROM payment_records pr JOIN invoices i ON i.id = pr.invoice_id
    WHERE $mineSql AND pr.status = 'completed' AND pr.payment_date BETWEEN ? AND ? GROUP BY pr.payment_date, pr.payment_method", [...$mineArgs, $from, $to]);
$mineTotal = 0; $byDay = []; $byMethod = [];
foreach ($mine as $r) {
    $mineTotal += $r['s']; $byDay[$r['d']] = ($byDay[$r['d']] ?? 0) + $r['s'];
    $k = ucfirst(str_replace('_', ' ', $r['m'])); $byMethod[$k] = ($byMethod[$k] ?? 0) + $r['s'];
}
$all = array_sum(dx_metric($pdo, 'collected', $from, $to, 'day'));

// Waiting for payment: issued/partial invoices with balance > 0; patients = distinct visits among them (the invoice itself when it has no visit).
$w = dx_rows($pdo, "SELECT COUNT(*) n, COUNT(DISTINCT COALESCE(visit_id, CONCAT('i', id))) v, COALESCE(SUM(balance),0) s FROM invoices WHERE status IN ('issued','partial') AND balance > 0")[0] ?? ['n' => 0, 'v' => 0, 's' => 0];
// Payments today: completed payments by anyone, dated today (not affected by the date filter).
$todayN = dx_val($pdo, "SELECT COUNT(*) FROM payment_records WHERE status = 'completed' AND payment_date = CURDATE()");

echo dx_cards([
    ['Collected by me', dx_money($mineTotal), 'fa-cash-register', '#059669', 'Payments I recorded in period', $invLink],
    ['Collected by everyone', dx_money($all), 'fa-hand-holding-usd', '#2563eb', 'All completed payments in period'],
    ['Invoices waiting', dx_num($w['n']), 'fa-file-invoice-dollar', '#d97706', dx_money($w['s']) . ' to collect', $invLink],
    ['Patients waiting to pay', dx_num($w['v']), 'fa-user-clock', '#7c3aed', 'Distinct visits with open invoices', $invLink],
    ['Payments today', dx_num($todayN), 'fa-receipt', '#0891b2', 'All cashiers'],
]);

$wait = dx_rows($pdo, "SELECT i.invoice_no, CONCAT(p.first_name,' ',p.last_name) pn, v.visit_no, d.name dep, i.balance
    FROM invoices i LEFT JOIN patients p ON p.id = i.patient_id LEFT JOIN visits v ON v.id = i.visit_id LEFT JOIN departments d ON d.id = COALESCE(i.department_id, v.department_id)
    WHERE i.status IN ('issued','partial') AND i.balance > 0 ORDER BY i.created_at LIMIT 15");
$waitRows = array_map(fn($r) => [$r['invoice_no'], $r['pn'] ?? '', trim(($r['visit_no'] ?? '') . ' ' . ($r['dep'] ?? '')), dx_money($r['balance'])], $wait);

$last = dx_rows($pdo, "SELECT pr.payment_date, pr.payment_time, pr.receipt_number, pr.amount_paid, pr.payment_method FROM payment_records pr JOIN invoices i ON i.id = pr.invoice_id
    WHERE $mineSql AND pr.status = 'completed' ORDER BY pr.payment_date DESC, pr.payment_time DESC, pr.id DESC LIMIT 10", $mineArgs);
$lastRows = array_map(fn($r) => [$r['payment_date'] . ' ' . substr((string)$r['payment_time'], 0, 5), $r['receipt_number'] ?? '', dx_money($r['amount_paid']), ucfirst(str_replace('_', ' ', $r['payment_method']))], $last);

echo dx_grid([
    dx_table('Invoices awaiting payment (oldest first)', ['Invoice', 'Patient', 'Visit / department', '>Balance'], $waitRows, 'Nobody is waiting to pay', $invLink),
    dx_table('My latest 10 payments', ['Time', 'Receipt', '>Amount', 'Method'], $lastRows, 'You have not recorded any payment yet'),
], 2);

[$lbl, $vals] = dx_fill_days($byDay, $from, $to);
echo dx_grid([
    dx_donut('My collections by payment method', $byMethod, 'dx_money'),
    dx_cols('My collections per day', $lbl, ['Collected' => $vals], ['fmt' => 'dx_money']),
], 2);
echo dx_end();

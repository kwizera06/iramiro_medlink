<?php
// includes/dash/hr.php — HR Manager dashboard. Shows today's staffing picture, so the date filter is not used.
$leave = 'hr.php?module=hr&sub=leave';
$cont = 'hr.php?module=hr&sub=contracts';
$lic = 'hr.php?module=hr&sub=license-tracking';
$disc = 'hr.php?module=hr&sub=disciplinary';

// Employees counted = everyone not terminated (active, on-leave, suspended).
$staff = (int)dx_val($pdo, "SELECT COUNT(*) FROM employees WHERE status <> 'terminated'");
// On leave today = distinct employees with an approved leave covering today.
$onLeave = (int)dx_val($pdo, "SELECT COUNT(DISTINCT employee_id) FROM leave_requests WHERE status = 'approved' AND CURDATE() BETWEEN start_date AND end_date");
// Pending leave = status 'pending' (waiting for HR review; 'hr_reviewed' is already past HR).
$pendLeave = (int)dx_val($pdo, "SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'");
// Contracts expiring: active contract of a current employee ending within 60 days (not yet ended).
$cWhere = "c.is_active = 1 AND c.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY) AND e.status <> 'terminated'";
$cExp = (int)dx_val($pdo, "SELECT COUNT(*) FROM employee_contracts c JOIN employees e ON e.id = c.employee_id WHERE $cWhere");
// Licences expiring: not revoked, expiry within 60 days (not yet expired), current employee.
$lWhere = "l.status <> 'revoked' AND l.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY) AND e.status <> 'terminated'";
$lExp = (int)dx_val($pdo, "SELECT COUNT(*) FROM employee_licenses l JOIN employees e ON e.id = l.employee_id WHERE $lWhere");
// Open vacancies = vacancy requests approved (recruitment running); closed/cancelled/rejected and in-approval ones are excluded.
$vac = (int)dx_val($pdo, "SELECT COALESCE(SUM(num_positions), 0) FROM vacancy_requests WHERE status = 'approved'");
// Open disciplinary cases = anything not resolved or closed.
$discOpen = (int)dx_val($pdo, "SELECT COUNT(*) FROM disciplinary_cases WHERE status NOT IN ('resolved','closed')");

echo dx_head('Human Resources', "$greeting, $firstName. Staff, leave and compliance.", null);

echo dx_attention([
    ['leave requests pending', $leave, $pendLeave],
    ['contracts expiring in 60 days', $cont, $cExp],
    ['licences expiring in 60 days', $lic, $lExp],
    ['open disciplinary cases', $disc, $discOpen],
]);

echo dx_cards([
    ['Total employees', dx_num($staff), 'fa-users', '#2563eb', 'not terminated', 'hr.php?module=hr&sub=employees'],
    ['On leave today', dx_num($onLeave), 'fa-umbrella-beach', '#0891b2', 'approved leave covering today', $leave],
    ['Pending leave requests', dx_num($pendLeave), 'fa-calendar-check', '#d97706', 'waiting for HR review', $leave],
    ['Contracts expiring', dx_num($cExp), 'fa-file-contract', '#dc2626', 'within 60 days', $cont],
    ['Licences expiring', dx_num($lExp), 'fa-id-card', '#7c3aed', 'within 60 days', $lic],
    ['Open vacancies', dx_num($vac), 'fa-user-plus', '#059669', 'positions in approved requests', 'hr.php?module=hr&sub=recruitment'],
]);

// Table: pending leave requests, oldest first.
$rows = [];
foreach (dx_rows($pdo, "SELECT CONCAT(e.first_name, ' ', e.last_name) n, lt.name t, lr.start_date, lr.end_date, lr.days_requested
        FROM leave_requests lr JOIN employees e ON e.id = lr.employee_id LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
        WHERE lr.status = 'pending' ORDER BY lr.created_at LIMIT 10") as $r)
    $rows[] = [$r['n'], $r['t'] ?? '-', $r['start_date'], $r['end_date'], dx_num($r['days_requested'])];
$t1 = dx_table('Pending leave requests', ['Employee', 'Type', 'From', 'To', '>Days'], $rows, 'No pending leave requests', $leave);

// Table: contracts and licences expiring in 60 days, soonest first; red when under 15 days left.
$rows = [];
foreach (dx_rows($pdo, "SELECT * FROM (
        SELECT CONCAT(e.first_name, ' ', e.last_name) n, CONCAT('Contract ', c.contract_number) w, c.end_date d FROM employee_contracts c JOIN employees e ON e.id = c.employee_id WHERE $cWhere
        UNION ALL
        SELECT CONCAT(e.first_name, ' ', e.last_name), CONCAT('Licence ', COALESCE(NULLIF(l.license_name, ''), l.license_type)), l.expiry_date FROM employee_licenses l JOIN employees e ON e.id = l.employee_id WHERE $lWhere
    ) x ORDER BY d LIMIT 15") as $r) {
    $left = (int)((strtotime($r['d']) - strtotime(date('Y-m-d'))) / 86400);
    $rows[] = [$r['n'], $r['w'], $r['d'], ['html' => dx_badge($left . ' days', $left < 15 ? '#dc2626' : '#d97706')]];
}
$t2 = dx_table('Contracts and licences expiring', ['Employee', 'What', 'Expires', 'Left'], $rows, 'Nothing expiring in 60 days', $cont);
echo dx_grid([$t1, $t2], 2);

// Graph: staff per department (not terminated).
$items = [];
foreach (dx_rows($pdo, "SELECT COALESCE(d.name, 'No department') n, COUNT(*) v FROM employees e LEFT JOIN departments d ON d.id = e.department_id WHERE e.status <> 'terminated' GROUP BY e.department_id, n") as $r) $items[$r['n']] = (int)$r['v'];
// Graph: attendance, people present (present or late) per day over the last 30 days.
$f30 = date('Y-m-d', strtotime('-29 days'));
$by = [];
foreach (dx_rows($pdo, "SELECT work_date d, COUNT(DISTINCT employee_id) v FROM attendance_records WHERE status IN ('present','late') AND work_date BETWEEN ? AND ? GROUP BY work_date", [$f30, date('Y-m-d')]) as $r) $by[$r['d']] = (int)$r['v'];
[$labels, $vals] = dx_fill_days($by, $f30, date('Y-m-d'));
echo dx_grid([dx_bars('Staff per department', $items, null, '#2563eb'), dx_cols('Attendance, last 30 days (present or late)', $labels, ['Present' => $vals])], 2);

echo dx_end();

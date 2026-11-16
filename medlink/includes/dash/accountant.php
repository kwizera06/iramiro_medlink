<?php
// Accountant dashboard. Variables from dashboard.php: $pdo, $firstName, $greeting, $today.
[$from, $to] = dx_range('month');
echo dx_head('Finance overview', $today, [$from, $to]);

$invLink = 'invoicing.php?module=finance&sub=invoices';
$recLink = 'insurance.php?module=insurance&sub=receivables';
$expLink = 'expenses.php?sub=payments';

// Billed / collected in the period: the shared definitions.
$billedDay = dx_metric($pdo, 'billed', $from, $to, 'day');
$collectedDay = dx_metric($pdo, 'collected', $from, $to, 'day');

// Open patient invoices (issued/partial with something left). invoices.balance = the patient's share only (insurer share is not in it).
$open = "status IN ('issued','partial') AND balance > 0";
$o = dx_rows($pdo, "SELECT COUNT(*) n, COALESCE(SUM(balance),0) s, COALESCE(SUM(created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)),0) old FROM invoices WHERE $open")[0] ?? ['n' => 0, 's' => 0, 'old' => 0];

// Claims: 'submitted' = sent to the insurer and not cancelled. Rejections: only those still 'open' (not resubmitted/billed to patient/written off/voided).
$cl = dx_rows($pdo, "SELECT COUNT(*) n, COALESCE(SUM(claimed_amount),0) s FROM insurance_claims WHERE status = 'submitted'")[0] ?? ['n' => 0, 's' => 0];
$rj = dx_rows($pdo, "SELECT COUNT(*) n, COALESCE(SUM(r.amount),0) s FROM insurance_claim_rejections r JOIN insurance_claims c ON c.id = r.claim_id WHERE r.status = 'open' AND c.status = 'submitted'")[0] ?? ['n' => 0, 's' => 0];

// Expenses awaiting payment = approved expense requests not fully paid (posted / partially_paid: total - amount_paid) + manual finance_expenses marked 'payable'.
$er = dx_rows($pdo, "SELECT COUNT(*) n, COALESCE(SUM(total - amount_paid),0) s FROM expense_requests WHERE status IN ('posted','partially_paid')")[0] ?? ['n' => 0, 's' => 0];
$fe = dx_rows($pdo, "SELECT COUNT(*) n, COALESCE(SUM(amount),0) s FROM finance_expenses WHERE status = 'payable'")[0] ?? ['n' => 0, 's' => 0];
$exN = (int)$er['n'] + (int)$fe['n']; $exS = (float)$er['s'] + (float)$fe['s'];

echo dx_attention([
    ['invoices unpaid for over 30 days', $invLink, $o['old']],
    ['insurance claim rejections open', $recLink, $rj['n']],
    ['expenses awaiting payment', $expLink, $exN],
]);

echo dx_cards([
    ['Billed', dx_money(array_sum($billedDay)), 'fa-file-invoice-dollar', '#2563eb', 'Invoices created in period', $invLink],
    ['Collected', dx_money(array_sum($collectedDay)), 'fa-hand-holding-usd', '#059669', 'Completed payments in period', $invLink],
    ['Patient balances owed', dx_money($o['s']), 'fa-wallet', '#d97706', dx_num($o['n']) . ' open invoices (all dates)', $invLink],
    ['Insurance claims pending', dx_num($cl['n']), 'fa-file-medical', '#7c3aed', dx_money($cl['s']) . ' claimed', $recLink],
    ['Claims rejected', dx_num($rj['n']), 'fa-ban', '#dc2626', dx_money($rj['s']) . ' open rejections', $recLink],
    ['Expenses awaiting payment', dx_money($exS), 'fa-receipt', '#0891b2', dx_num($exN) . ' approved, unpaid', $expLink],
]);

// Unpaid invoices by age (days since created): count and balance per bucket.
$labels = ['0-30 days', '31-60 days', '61-90 days', '90+ days'];
$b = dx_rows($pdo, "SELECT CASE WHEN d <= 30 THEN 0 WHEN d <= 60 THEN 1 WHEN d <= 90 THEN 2 ELSE 3 END k, COUNT(*) n, SUM(balance) s
    FROM (SELECT balance, DATEDIFF(CURDATE(), DATE(created_at)) d FROM invoices WHERE $open) x GROUP BY k");
$bk = array_fill(0, 4, ['n' => 0, 's' => 0]); foreach ($b as $r) $bk[(int)$r['k']] = $r;
$ageRows = []; foreach ($labels as $i => $l) $ageRows[] = [$l, dx_num($bk[$i]['n']), dx_money($bk[$i]['s'])];

$oldest = dx_rows($pdo, "SELECT i.invoice_no, CONCAT(p.first_name,' ',p.last_name) pn, i.balance, DATEDIFF(CURDATE(), DATE(i.created_at)) d
    FROM invoices i LEFT JOIN patients p ON p.id = i.patient_id WHERE i.status IN ('issued','partial') AND i.balance > 0 ORDER BY i.created_at LIMIT 10");
$oldRows = array_map(fn($r) => [$r['invoice_no'], $r['pn'] ?? '', $r['d'] . ' d', dx_money($r['balance'])], $oldest);

echo dx_grid([
    dx_table('Unpaid invoices by age', ['Age', '>Invoices', '>Balance'], $ageRows, 'No unpaid invoices', $invLink),
    dx_table('Oldest 10 unpaid invoices', ['Invoice', 'Patient', 'Age', '>Balance'], $oldRows, 'No unpaid invoices', $invLink),
], 2);

// Owed by insurer. Source of truth is the invoices (insurer share = total - patient balance - insurer already paid), because claims
// only hold a monthly claimed total and are mostly empty; claims and open rejections are shown per insurer alongside.
$ins = [];
foreach (dx_rows($pdo, "SELECT insurance_id k, SUM(total - balance - insurer_paid_amount) s FROM invoices
    WHERE payment_type IN ('insurance','mixed') AND status IN ('issued','partial','paid') AND insurance_id IS NOT NULL GROUP BY insurance_id") as $r) $ins[$r['k']]['owed'] = (float)$r['s'];
foreach (dx_rows($pdo, "SELECT insurance_id k, COUNT(*) n, SUM(claimed_amount) s FROM insurance_claims WHERE status = 'submitted' GROUP BY insurance_id") as $r) { $ins[$r['k']]['cn'] = $r['n']; $ins[$r['k']]['cs'] = (float)$r['s']; }
foreach (dx_rows($pdo, "SELECT c.insurance_id k, SUM(r.amount) s FROM insurance_claim_rejections r JOIN insurance_claims c ON c.id = r.claim_id WHERE r.status = 'open' GROUP BY c.insurance_id") as $r) $ins[$r['k']]['rej'] = (float)$r['s'];
$names = []; foreach (dx_rows($pdo, "SELECT id, name FROM insurance_companies") as $r) $names[$r['id']] = $r['name'];
uasort($ins, fn($a, $c) => ($c['owed'] ?? 0) <=> ($a['owed'] ?? 0));
$insRows = [];
foreach ($ins as $k => $v) {
    if (($v['owed'] ?? 0) <= 0 && empty($v['cn']) && empty($v['rej'])) continue;
    $insRows[] = [$names[$k] ?? ('Insurer #' . $k), dx_money($v['owed'] ?? 0), dx_num($v['cn'] ?? 0) . ' / ' . dx_money($v['cs'] ?? 0), dx_money($v['rej'] ?? 0)];
}
echo dx_table('Owed by insurer', ['Insurer', '>Insurer share owed', '>Claims submitted', '>Open rejections'], $insRows, 'No insurance receivables', $recLink);

// Revenue by payment method (completed payments in the period) and billed vs collected per day.
$pm = [];
foreach (dx_rows($pdo, "SELECT payment_method m, SUM(amount_paid) s FROM payment_records WHERE status = 'completed' AND payment_date BETWEEN ? AND ? GROUP BY payment_method", [$from, $to]) as $r) $pm[ucfirst(str_replace('_', ' ', $r['m']))] = (float)$r['s'];
[$lbl, $bv] = dx_fill_days($billedDay, $from, $to);
[, $cv] = dx_fill_days($collectedDay, $from, $to);
echo dx_grid([
    dx_donut('Revenue by payment method', $pm, 'dx_money'),
    dx_cols('Billed vs collected per day', $lbl, ['Billed' => $bv, 'Collected' => $cv], ['fmt' => 'dx_money']),
], 2);
echo dx_end();

<?php
// includes/payroll_rules.php
// Rwanda payroll rules, editable by an administrator / accountant (hospital_settings.payroll_rules, JSON).
// Defaults: PAYE bands of the 2023 income tax law, RSSB pension 6% + 6% (2025), maternity 0.3% + 0.3%,
// occupational hazards 2% (employer), CBHI 0.5% of net pay. Check them against the current RRA / RSSB notices.
// Workflow: draft -> processed (submitted) -> approved (posted to the ledger) -> paid.

function pr_default_rules(): array {
    return [
        'basic_pct' => 70, 'housing_pct' => 15, 'transport_pct' => 15, // split of gross shown on the payslip
        'paye_bands' => [[60000, 0], [100000, 10], [200000, 20], [null, 30]], // [monthly pay up to, rate %]; null = no upper limit
        'pension_employee_pct' => 6, 'pension_employer_pct' => 6,
        'maternity_employee_pct' => 0.3, 'maternity_employer_pct' => 0.3,
        'hazard_employer_pct' => 2,
        'cbhi_pct' => 0.5,                 // community health insurance (mutuelle), % of gross pay; 0 = use each employee's fixed amount
        'rssb_excludes_transport' => true, // RSSB contributions are worked out on gross minus the transport allowance
        'casual_paye_pct' => 15,           // flat PAYE withheld from casual workers; interns' stipends are not taxed
    ];
}

function pr_rules(PDO $pdo): array {
    $q = $pdo->query("SELECT setting_value FROM hospital_settings WHERE setting_key = 'payroll_rules' LIMIT 1");
    $saved = json_decode((string)($q ? $q->fetchColumn() : ''), true);
    return array_merge(pr_default_rules(), is_array($saved) ? $saved : []);
}

function pr_save_rules(PDO $pdo, array $in): array {
    $r = pr_default_rules();
    foreach ($r as $k => $v) {
        if (!array_key_exists($k, $in)) continue;
        if ($k === 'rssb_excludes_transport') { $r[$k] = (bool)$in[$k]; continue; }
        if ($k === 'paye_bands') {
            $bands = [];
            foreach ((array)$in[$k] as $b) {
                $upTo = ($b[0] ?? '') === '' || $b[0] === null ? null : (float)$b[0];
                $rate = (float)($b[1] ?? 0);
                if ($rate < 0 || $rate > 100) throw new RuntimeException('A PAYE rate must be between 0 and 100', 422);
                $bands[] = [$upTo, $rate];
            }
            $last = null;
            foreach ($bands as $i => [$upTo]) {
                if ($upTo === null && $i !== count($bands) - 1) throw new RuntimeException('Only the last PAYE band can have no upper limit', 422);
                if ($upTo !== null && $last !== null && $upTo <= $last) throw new RuntimeException('PAYE bands must go up in order', 422);
                $last = $upTo;
            }
            if (!$bands || end($bands)[0] !== null) throw new RuntimeException('The last PAYE band must have no upper limit', 422);
            $r[$k] = $bands;
            continue;
        }
        $n = (float)$in[$k];
        if ($n < 0 || $n > 100) throw new RuntimeException("$k must be a percentage between 0 and 100", 422);
        $r[$k] = $n;
    }
    if (abs($r['basic_pct'] + $r['housing_pct'] + $r['transport_pct'] - 100) > 0.001) throw new RuntimeException('Basic + housing + transport must add up to 100%', 422);
    $pdo->prepare("INSERT INTO hospital_settings (setting_key, setting_value) VALUES ('payroll_rules', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
        ->execute([json_encode($r)]);
    return $r;
}

// Progressive PAYE on monthly taxable pay
function pr_paye(array $bands, float $pay): float {
    $tax = 0.0; $from = 0.0;
    foreach ($bands as [$upTo, $rate]) {
        $top = $upTo === null ? $pay : min($pay, (float)$upTo);
        if ($top > $from) $tax += ($top - $from) * $rate / 100;
        if ($upTo === null || $pay <= $upTo) break;
        $from = (float)$upTo;
    }
    return round($tax, 2);
}

// One monthly payslip. $fixed: mlbs, medical, mutuelle (used only when cbhi_pct = 0), advance.
function pr_calc(array $r, float $gross, array $fixed = []): array {
    $pct = fn($base, $p) => round($base * $p / 100, 2);
    $transport = $pct($gross, $r['transport_pct']);
    $rssbBase = $r['rssb_excludes_transport'] ? $gross - $transport : $gross;
    $s = [
        'gross_salary' => round($gross, 2),
        'basic_pay' => $pct($gross, $r['basic_pct']),
        'housing_allowance' => $pct($gross, $r['housing_pct']),
        'transport_allowance' => $transport,
        'tpr_tax' => pr_paye($r['paye_bands'], $gross),
        'csr_deduction' => $pct($rssbBase, $r['pension_employee_pct']),
        'maternity_deduction' => $pct($rssbBase, $r['maternity_employee_pct']),
        'mlbs_deduction' => 0.0, // removed
        'medical_deduction' => 0.0, // removed
        'advance_deduction' => round((float)($fixed['advance'] ?? 0), 2),
        'employer_pension' => $pct($rssbBase, $r['pension_employer_pct']),
        'employer_maternity' => $pct($rssbBase, $r['maternity_employer_pct']),
        'employer_hazard' => $pct($rssbBase, $r['hazard_employer_pct']),
    ];
    $s['mutuelle_deduction'] = $r['cbhi_pct'] > 0 ? $pct($gross, $r['cbhi_pct']) : round((float)($fixed['mutuelle'] ?? 0), 2);
    $s['total_deductions'] = round($s['tpr_tax'] + $s['csr_deduction'] + $s['maternity_deduction'] + $s['mlbs_deduction']
        + $s['medical_deduction'] + $s['mutuelle_deduction'] + $s['advance_deduction'], 2);
    $s['net_pay'] = round($gross - $s['total_deductions'], 2);
    return $s;
}

// Interns: stipend with no statutory deductions. Casual workers: flat PAYE, no RSSB.
function pr_calc_flat(array $r, string $type, float $gross, float $advance = 0): array {
    $s = array_fill_keys(['housing_allowance', 'transport_allowance', 'csr_deduction', 'maternity_deduction', 'mlbs_deduction', 'medical_deduction',
                          'mutuelle_deduction', 'employer_pension', 'employer_maternity', 'employer_hazard'], 0.0);
    $s['gross_salary'] = $s['basic_pay'] = round($gross, 2);
    $s['tpr_tax'] = $type === 'casual-worker' ? round($gross * $r['casual_paye_pct'] / 100, 2) : 0.0;
    $s['advance_deduction'] = round($advance, 2);
    $s['total_deductions'] = round($s['tpr_tax'] + $s['advance_deduction'], 2);
    $s['net_pay'] = round($gross - $s['total_deductions'], 2);
    return $s;
}

// Days and hours an employee worked in a month, from approved attendance (half-day = 0.5)
function pr_worked(PDO $pdo, int $empId, string $period): array {
    $q = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN status IN ('present','late') THEN 1 WHEN status = 'half-day' THEN 0.5 ELSE 0 END), 0) AS days,
                               COALESCE(SUM(CASE WHEN check_out > check_in THEN TIME_TO_SEC(TIMEDIFF(check_out, check_in)) ELSE 0 END), 0) / 3600 AS hours
                        FROM attendance_records WHERE employee_id = ? AND DATE_FORMAT(work_date, '%Y-%m') = ? AND approval = 'approved'");
    $q->execute([$empId, $period]);
    $w = $q->fetch();
    return ['days' => (float)$w['days'], 'hours' => round((float)$w['hours'], 2)];
}

// Admins and accountants authorise payroll and change the rates
function pr_can_approve(PDO $pdo): bool {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $st = $pdo->prepare("SELECT 1 FROM users u LEFT JOIN user_roles ur ON ur.user_id = u.id LEFT JOIN roles r ON r.id = ur.role_id
                         WHERE u.id = ? AND (r.slug IN ('admin','accountant') OR u.role IN ('admin','accountant')) LIMIT 1");
    $st->execute([$uid]);
    return (bool)$st->fetchColumn();
}

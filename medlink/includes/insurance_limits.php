<?php
// includes/insurance_limits.php — insurance coverage limit (#52): used amount, authorization letters, effective limit.
// Idempotent schema; callers: handlers/patients/visits.php.

function ensure_insurance_authorizations(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $pdo->exec("CREATE TABLE IF NOT EXISTS insurance_authorizations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_insurance_id INT NOT NULL,
        visit_id INT NULL,
        auth_no VARCHAR(100) NULL,
        authorized_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
        valid_from DATE NULL,
        valid_to DATE NULL,
        document_id INT NULL,
        created_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_pi (patient_insurance_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** Policy year window [from, to] containing today, from the policy start date anniversary (calendar year when start is unusable). */
function ins_policy_year(?string $start): array {
    $y = (int)date('Y');
    $from = "$y-01-01"; $to = "$y-12-31";
    if ($start && $start > '1990-01-01' && $start <= date('Y-m-d')) {
        $a = date('Y') . substr($start, 4, 6);
        if ($a > date('Y-m-d')) $a = ($y - 1) . substr($start, 4, 6);
        $from = $a;
        $to = date('Y-m-d', strtotime("$a +1 year -1 day"));
    }
    return [$from, $to];
}

/** Insurer share of the patient's insured invoices with this company over the policy year (total x (100 - copay%)). */
function ins_coverage_used(PDO $pdo, int $patientId, int $companyId): float {
    $pi = $pdo->prepare("SELECT pi.policy_start_date, COALESCE(ip.copayment_percentage, 0) AS copay
                         FROM patient_insurance pi LEFT JOIN insurance_packages ip ON ip.id = pi.package_id
                         WHERE pi.patient_id=? AND pi.company_id=? ORDER BY pi.is_primary DESC, pi.id DESC LIMIT 1");
    $pi->execute([$patientId, $companyId]);
    $row = $pi->fetch();
    if (!$row) return 0.0;
    [$from, $to] = ins_policy_year($row['policy_start_date']);
    $q = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM invoices
                        WHERE patient_id=? AND insurance_id=? AND payment_type IN ('insurance','mixed')
                          AND status NOT IN ('draft','cancelled','void')
                          AND created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)");
    $q->execute([$patientId, $companyId, $from, $to]);
    return round((float)$q->fetchColumn() * (100 - (float)$row['copay']) / 100, 2);
}

/** Limit picture for a patient's insurance row: limit (0 = none), used, authorized extra, effective, reached. */
function ins_limit_status(PDO $pdo, int $patientId, int $companyId, int $patientInsuranceId, float $planLimit): array {
    $st = ['limit' => $planLimit, 'used' => 0.0, 'authorized' => 0.0, 'effective' => $planLimit, 'reached' => false];
    if ($planLimit <= 0) return $st;
    ensure_insurance_authorizations($pdo);
    $a = $pdo->prepare("SELECT COALESCE(SUM(authorized_amount),0) FROM insurance_authorizations
                        WHERE patient_insurance_id=? AND (valid_from IS NULL OR valid_from<=CURDATE()) AND (valid_to IS NULL OR valid_to>=CURDATE())");
    $a->execute([$patientInsuranceId]);
    $st['used'] = ins_coverage_used($pdo, $patientId, $companyId);
    $st['authorized'] = round((float)$a->fetchColumn(), 2);
    $st['effective'] = $planLimit + $st['authorized'];
    $st['reached'] = $st['used'] >= $st['effective'];
    return $st;
}

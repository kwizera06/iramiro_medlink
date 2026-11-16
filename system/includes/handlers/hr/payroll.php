<?php
/**
 * handlers/hr/payroll.php
 * Rwanda Payroll Handler – PAYE (TPR) + RSSB
 *
 * PAYE (TPR) – Monthly Taxable Income Brackets (RWF):
 *   0 – 60,000         → 0%
 *   60,001 – 100,000   → (income − 60,000) × 10%
 *   100,001 – 200,000  → 4,000 + (income − 100,000) × 20%
 *   Above 200,000      → 24,000 + (income − 200,000) × 30%
 *
 * RSSB:
 *   Employee pension   = Gross × 6%
 *   Employer pension   = Gross × 6%
 *   Employer OHI       = Gross × 2%
 *
 * Gross = Basic + Housing + Transport + Communication + Overtime + Bonus + Other Benefits
 * Total Deductions = Employee RSSB + PAYE + Loans + Salary Advance (mlbs/medical/mutuelle) + Other Deductions
 * Net Salary = Gross − Total Deductions
 * Employer Cost = Gross + Employer RSSB Pension + Employer OHI
 */
require_once __DIR__ . '/../../includes/database.php';

function jsonResponse(array $d, int $s = 200): void {
    http_response_code($s);
    header('Content-Type: application/json');
    echo json_encode($d);
    exit();
}
function success($data = null, string $msg = 'Success', int $s = 200): void {
    jsonResponse(['success' => true, 'message' => $msg, 'data' => $data], $s);
}
function fail(string $msg, int $s = 400): void {
    jsonResponse(['success' => false, 'message' => $msg], $s);
}

/**
 * Rwanda PAYE (TPR) – progressive tax on gross taxable income.
 * Brackets (monthly RWF):
 *   0 – 60,000         : 0
 *   60,001 – 100,000   : (income − 60,000) × 10%    → max 4,000
 *   100,001 – 200,000  : 4,000 + (income − 100,000) × 20%    → max 24,000
 *   Above 200,000      : 24,000 + (income − 200,000) × 30%
 */
function calcPAYE(float $grossIncome): float {
    if ($grossIncome <= 60000) return 0.0;
    $tax = 0.0;
    // Band 2: 60,001 – 100,000 @ 10%
    $band2 = min($grossIncome, 100000) - 60000;
    if ($band2 > 0) $tax += $band2 * 0.10;
    // Band 3: 100,001 – 200,000 @ 20%
    if ($grossIncome > 100000) {
        $band3 = min($grossIncome, 200000) - 100000;
        $tax += $band3 * 0.20;
    }
    // Band 4: Above 200,000 @ 30%
    if ($grossIncome > 200000) {
        $tax += ($grossIncome - 200000) * 0.30;
    }
    return round($tax, 2);
}

/**
 * Calculate full Rwanda payroll breakdown for one employee.
 * Returns associative array of all figures.
 */
function calcPayroll(array $e, array $override = []): array {
    // Helper: use override only if it is explicitly > 0, otherwise fall back to employee default
    $ov = function(string $key, float $empDefault) use ($override): float {
        return (isset($override[$key]) && (float)$override[$key] > 0)
            ? (float)$override[$key]
            : $empDefault;
    };

    // Salary components
    $basic         = $ov('basic_pay',               (float)($e['gross_salary']            ?? 0));
    $housing       = $ov('housing_allowance',        (float)($e['housing_allowance']       ?? 0));
    $transport     = $ov('transport_allowance',      (float)($e['transport_allowance']     ?? 0));
    $communication = $ov('communication_allowance',  (float)($e['communication_allowance'] ?? 0));
    // Overtime, bonus, other benefits: only from override (not on employee record by default)
    $overtime       = (float)($override['overtime_pay']    ?? 0);
    $bonus          = (float)($override['bonus']           ?? 0);
    $other_benefits = (float)($override['other_benefits']  ?? 0);

    // Gross
    $gross = $basic + $housing + $transport + $communication + $overtime + $bonus + $other_benefits;

    // PAYE (TPR) – on gross income
    $paye = calcPAYE($gross);

    // Employee RSSB = Gross × 6%
    $emp_rssb = round($gross * 0.06, 2);

    // Employer contributions
    $emp_rssb_pension = round($gross * 0.06, 2);
    $emp_ohi          = round($gross * 0.02, 2);

    // Other employee deductions (use override only if > 0, else employee defaults)
    $mlbs      = 0.0; // MLBS removed
    $medical   = 0.0; // Medical removed
    // Mutuelle de Santé = 0.5% of gross (auto-calculated; override allowed only if > 0)
    $mutuelle  = $ov('mutuelle_deduction', round($gross * 0.005, 2));
    $loans     = $ov('loan_deduction',    (float)($e['loan_deduction']    ?? 0));
    $other_ded = $ov('other_deductions',  (float)($e['other_deductions']  ?? 0));
    $advance   = (float)($override['advance_deduction'] ?? 0);

    // Total Deductions = Emp RSSB + PAYE + MLBS + Medical + Mutuelle + Loans + Advance + Other Deductions
    $total_deductions = round($emp_rssb + $paye + $mlbs + $medical + $mutuelle + $loans + $advance + $other_ded, 2);

    // Net Salary
    $net_pay = round($gross - $total_deductions, 2);

    // Employer Cost = Gross + Employer Pension + Employer OHI
    $employer_cost = round($gross + $emp_rssb_pension + $emp_ohi, 2);

    return [
        // Gross components
        'basic_pay'              => round($basic, 2),
        'housing_allowance'      => round($housing, 2),
        'transport_allowance'    => round($transport, 2),
        'communication_allowance'=> round($communication, 2),
        'overtime_pay'           => round($overtime, 2),
        'bonus'                  => round($bonus, 2),
        'other_benefits'         => round($other_benefits, 2),
        'gross_salary'           => round($gross, 2),
        // Employee deductions
        'employee_rssb'          => $emp_rssb,
        'tpr_tax'                => $paye,
        'mlbs_deduction'         => round($mlbs, 2),
        'medical_deduction'      => round($medical, 2),
        'mutuelle_deduction'     => round($mutuelle, 2),
        'loan_deduction'         => round($loans, 2),
        'advance_deduction'      => round($advance, 2),
        'other_deductions'       => round($other_ded, 2),
        'total_deductions'       => $total_deductions,
        'net_pay'                => $net_pay,
        // Employer contributions
        'employer_rssb_pension'  => $emp_rssb_pension,
        'employer_ohi'           => $emp_ohi,
        'employer_cost'          => $employer_cost,
    ];
}

// ─── Routing ────────────────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$action = $_GET['action'] ?? ($input['action'] ?? '');

try {

// ════════════════════════════════════════════════════
// GET
// ════════════════════════════════════════════════════
if ($method === 'GET') {

    // Single record
    if ($id && $action === '') {
        $stmt = $pdo->prepare("
            SELECT pr.*, e.first_name, e.last_name, e.employee_no, e.email, e.phone,
                   d.name AS department_name,
                   CONCAT(u.first_name,' ',u.last_name) AS approved_by_name
            FROM payroll_records pr
            JOIN employees e ON e.id = pr.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN employees u ON u.id = pr.approved_by
            WHERE pr.id = ?
        ");
        $stmt->execute([$id]);
        $rec = $stmt->fetch();
        if (!$rec) fail('Payroll record not found', 404);
        success($rec);
    }

    // Preview calculation (no save)
    if ($action === 'preview') {
        $empId = (int)($_GET['employee_id'] ?? 0);
        if (!$empId) fail('employee_id required', 422);
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE id=?");
        $stmt->execute([$empId]);
        $e = $stmt->fetch();
        if (!$e) fail('Employee not found', 404);
        // Only pass GET values that are actually > 0 so calcPayroll falls back to employee defaults
        $override = array_filter($_GET, function($v) { return is_numeric($v) && (float)$v > 0; });
        $calc = calcPayroll($e, $override);
        success($calc);
    }

    // List records
    $period  = $_GET['period']  ?? '';
    $empId   = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
    $status  = $_GET['status']  ?? '';
    $page    = max(1, (int)($_GET['page']  ?? 1));
    $limit   = min(100, max(1, (int)($_GET['limit'] ?? 50)));
    $offset  = ($page - 1) * $limit;

    $where = []; $params = [];
    if ($period !== '') { $where[] = "pr.pay_period=?"; $params[] = $period; }
    if ($empId > 0)     { $where[] = "pr.employee_id=?"; $params[] = $empId; }
    if ($status !== '') { $where[] = "pr.status=?"; $params[] = $status; }
    $ws = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $cnt = $pdo->prepare("SELECT COUNT(*) FROM payroll_records pr JOIN employees e ON e.id=pr.employee_id $ws");
    $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT pr.*,
               e.first_name, e.last_name, e.employee_no, e.email, e.phone,
               d.name AS department_name,
               CONCAT(ab.first_name,' ',ab.last_name) AS approved_by_name
        FROM payroll_records pr
        JOIN employees e ON e.id = pr.employee_id
        LEFT JOIN departments d ON d.id = e.department_id
        LEFT JOIN employees ab ON ab.id = pr.approved_by
        $ws
        ORDER BY pr.pay_period DESC, e.employee_no ASC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    // Summary
    $sumStmt = $pdo->prepare("
        SELECT
            COUNT(*)                 AS total_employees,
            SUM(pr.gross_salary)     AS total_gross,
            SUM(pr.tpr_tax)          AS total_tpr,
            SUM(pr.employee_rssb)    AS total_emp_rssb,
            SUM(pr.employer_cost)    AS total_employer_cost,
            SUM(pr.net_pay)          AS total_net,
            SUM(pr.status='approved') AS approved_count,
            SUM(pr.status='paid')    AS paid_count
        FROM payroll_records pr $ws
    ");
    $sumStmt->execute($params);
    $summary = $sumStmt->fetch();

    success([
        'records'    => $records,
        'summary'    => $summary,
        'pagination' => ['total' => $total, 'page' => $page, 'limit' => $limit, 'total_pages' => (int)ceil($total / max($limit, 1))]
    ]);
}

// ════════════════════════════════════════════════════
// POST — process payroll for one or all employees
// ════════════════════════════════════════════════════
if ($method === 'POST' && $action === '') {
    if (empty($input['pay_period'])) fail("Field 'pay_period' is required", 422);
    if (!preg_match('/^\d{4}-\d{2}$/', $input['pay_period'])) fail('pay_period must be YYYY-MM', 422);

    $period = $input['pay_period'];

    if (!empty($input['employee_id'])) {
        $empIds = [(int)$input['employee_id']];
    } else {
        $stmt = $pdo->query("SELECT id FROM employees WHERE status = 'active'");
        $empIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    if (!$empIds) fail('No active employees found', 404);

    $processed = [];
    $skipped   = [];
    $pdo->beginTransaction();

    foreach ($empIds as $empId) {
        // Duplicate check
        $dup = $pdo->prepare("SELECT id FROM payroll_records WHERE employee_id=? AND pay_period=?");
        $dup->execute([$empId, $period]);
        if ($dup->fetch()) { $skipped[] = $empId; continue; }

        $empStmt = $pdo->prepare("SELECT * FROM employees WHERE id=?");
        $empStmt->execute([$empId]);
        $e = $empStmt->fetch();
        if (!$e) continue;

        $calc = calcPayroll($e, $input);

        $ins = $pdo->prepare("
            INSERT INTO payroll_records (
                employee_id, pay_period, payment_frequency,
                gross_salary, basic_pay,
                housing_allowance, transport_allowance, communication_allowance,
                overtime_pay, bonus, other_benefits,
                tpr_tax, employee_rssb,
                mlbs_deduction, medical_deduction, mutuelle_deduction,
                loan_deduction, advance_deduction, other_deductions,
                total_deductions, net_pay,
                employer_rssb_pension, employer_ohi, employer_cost,
                status, notes, processed_by
            ) VALUES (
                ?,?,?,
                ?,?,
                ?,?,?,
                ?,?,?,
                ?,?,
                ?,?,?,
                ?,?,?,
                ?,?,
                ?,?,?,
                ?,?,?
            )
        ");
        $ins->execute([
            $empId, $period, 'monthly',
            $calc['gross_salary'],     $calc['basic_pay'],
            $calc['housing_allowance'],$calc['transport_allowance'],$calc['communication_allowance'],
            $calc['overtime_pay'],     $calc['bonus'], $calc['other_benefits'],
            $calc['tpr_tax'],          $calc['employee_rssb'],
            $calc['mlbs_deduction'],   $calc['medical_deduction'],  $calc['mutuelle_deduction'],
            $calc['loan_deduction'],   $calc['advance_deduction'],  $calc['other_deductions'],
            $calc['total_deductions'], $calc['net_pay'],
            $calc['employer_rssb_pension'], $calc['employer_ohi'], $calc['employer_cost'],
            $input['status'] ?? 'draft',
            trim($input['notes'] ?? ''),
            $_SESSION['user_id'] ?? null
        ]);
        $processed[] = (int)$pdo->lastInsertId();
    }

    $pdo->commit();
    success([
        'processed_count' => count($processed),
        'skipped_count'   => count($skipped),
        'record_ids'      => $processed
    ], 'Payroll processed for ' . count($processed) . ' employee(s)', 201);
}

// ════════════════════════════════════════════════════
// POST /approve — approve a single record or entire batch
// ════════════════════════════════════════════════════
if ($method === 'POST' && $action === 'approve') {
    $period   = $input['pay_period'] ?? '';
    $recordId = !empty($input['record_id']) ? (int)$input['record_id'] : null;
    $notes    = trim($input['notes'] ?? '');
    $userId   = $_SESSION['user_id'] ?? null;
    $userName = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
    $userRole = $_SESSION['role'] ?? 'staff';

    if (!$period && !$recordId) fail('pay_period or record_id required', 422);

    $pdo->beginTransaction();

    if ($recordId) {
        // Approve single record
        $pdo->prepare("
            UPDATE payroll_records
            SET status='approved', approved_by=?, approved_at=NOW(), approval_notes=?
            WHERE id=? AND status IN ('draft','processed')
        ")->execute([$userId, $notes, $recordId]);

        // Get period for audit
        $r = $pdo->prepare("SELECT pay_period FROM payroll_records WHERE id=?");
        $r->execute([$recordId]);
        $period = $r->fetchColumn() ?: $period;
    } else {
        // Approve all draft/processed records for the period
        $pdo->prepare("
            UPDATE payroll_records
            SET status='approved', approved_by=?, approved_at=NOW(), approval_notes=?
            WHERE pay_period=? AND status IN ('draft','processed')
        ")->execute([$userId, $notes, $period]);
    }

    // Audit log
    $pdo->prepare("
        INSERT INTO payroll_approvals (payroll_batch_id, payroll_record_id, action, actor_id, actor_name, actor_role, notes)
        VALUES (?,?,?,?,?,?,?)
    ")->execute([$period, $recordId, 'approved', $userId, $userName, $userRole, $notes]);

    $pdo->commit();
    success(null, 'Payroll approved successfully');
}

// ════════════════════════════════════════════════════
// POST /finance — record finance entry for the batch or single record
// ════════════════════════════════════════════════════
if ($method === 'POST' && $action === 'finance') {
    $period    = $input['pay_period'] ?? '';
    $recordId  = !empty($input['record_id']) ? (int)$input['record_id'] : null;
    $reference = trim($input['finance_reference'] ?? '');
    $userId    = $_SESSION['user_id'] ?? null;
    $userName  = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
    $userRole  = $_SESSION['role'] ?? 'staff';

    if (!$period && !$recordId) fail('pay_period or record_id required', 422);

    $pdo->beginTransaction();

    if ($recordId) {
        $pdo->prepare("
            UPDATE payroll_records
            SET finance_recorded=1, finance_recorded_by=?, finance_recorded_at=NOW(), finance_reference=?,
                status='paid'
            WHERE id=? AND status='approved'
        ")->execute([$userId, $reference, $recordId]);

        $r = $pdo->prepare("SELECT pay_period FROM payroll_records WHERE id=?");
        $r->execute([$recordId]);
        $period = $r->fetchColumn() ?: $period;
    } else {
        $pdo->prepare("
            UPDATE payroll_records
            SET finance_recorded=1, finance_recorded_by=?, finance_recorded_at=NOW(), finance_reference=?,
                status='paid'
            WHERE pay_period=? AND status='approved'
        ")->execute([$userId, $reference, $period]);
    }

    $pdo->prepare("
        INSERT INTO payroll_approvals (payroll_batch_id, payroll_record_id, action, actor_id, actor_name, actor_role, notes)
        VALUES (?,?,?,?,?,?,?)
    ")->execute([$period, $recordId, 'finance_recorded', $userId, $userName, $userRole, $reference]);

    $pdo->commit();
    success(null, 'Finance recorded successfully');
}

// ════════════════════════════════════════════════════
// PUT — update a record (recalculate on change)
// ════════════════════════════════════════════════════
if ($method === 'PUT') {
    if (!$id) fail('Record ID required', 422);

    $chk = $pdo->prepare("SELECT pr.*, e.* FROM payroll_records pr JOIN employees e ON e.id=pr.employee_id WHERE pr.id=?");
    $chk->execute([$id]);
    $existing = $chk->fetch();
    if (!$existing) fail('Record not found', 404);
    if ($existing['status'] === 'paid') fail('Cannot edit a paid payroll record', 409);

    // Recalculate with any overrides provided
    $override = array_merge([
        'basic_pay'               => $existing['basic_pay'],
        'housing_allowance'       => $existing['housing_allowance'],
        'transport_allowance'     => $existing['transport_allowance'],
        'communication_allowance' => $existing['communication_allowance'],
        'overtime_pay'            => $existing['overtime_pay'],
        'bonus'                   => $existing['bonus'],
        'other_benefits'          => $existing['other_benefits'],
        'mlbs_deduction'          => $existing['mlbs_deduction'],
        'medical_deduction'       => $existing['medical_deduction'],
        'mutuelle_deduction'      => $existing['mutuelle_deduction'],
        'loan_deduction'          => $existing['loan_deduction'],
        'advance_deduction'       => $existing['advance_deduction'],
        'other_deductions'        => $existing['other_deductions'],
    ], $input);

    $calc = calcPayroll($existing, $override);

    $pdo->prepare("
        UPDATE payroll_records SET
            gross_salary=?, basic_pay=?,
            housing_allowance=?, transport_allowance=?, communication_allowance=?,
            overtime_pay=?, bonus=?, other_benefits=?,
            tpr_tax=?, employee_rssb=?,
            mlbs_deduction=?, medical_deduction=?, mutuelle_deduction=?,
            loan_deduction=?, advance_deduction=?, other_deductions=?,
            total_deductions=?, net_pay=?,
            employer_rssb_pension=?, employer_ohi=?, employer_cost=?,
            notes=?
        WHERE id=?
    ")->execute([
        $calc['gross_salary'],     $calc['basic_pay'],
        $calc['housing_allowance'],$calc['transport_allowance'],$calc['communication_allowance'],
        $calc['overtime_pay'],     $calc['bonus'], $calc['other_benefits'],
        $calc['tpr_tax'],          $calc['employee_rssb'],
        $calc['mlbs_deduction'],   $calc['medical_deduction'], $calc['mutuelle_deduction'],
        $calc['loan_deduction'],   $calc['advance_deduction'], $calc['other_deductions'],
        $calc['total_deductions'], $calc['net_pay'],
        $calc['employer_rssb_pension'], $calc['employer_ohi'], $calc['employer_cost'],
        trim($input['notes'] ?? $existing['notes'] ?? ''),
        $id
    ]);

    success($calc, 'Payroll record updated');
}

// ════════════════════════════════════════════════════
// DELETE
// ════════════════════════════════════════════════════
if ($method === 'DELETE') {
    if (!$id) fail('Record ID required', 422);
    $chk = $pdo->prepare("SELECT id, status FROM payroll_records WHERE id=?");
    $chk->execute([$id]);
    $rec = $chk->fetch();
    if (!$rec) fail('Record not found', 404);
    if (in_array($rec['status'], ['approved', 'paid'])) fail('Cannot delete an approved or paid payroll record', 409);
    $pdo->prepare("DELETE FROM payroll_records WHERE id=?")->execute([$id]);
    success(null, 'Payroll record deleted');
}

// ════════════════════════════════════════════════════
// POST /record-journal — create journal entry for approved payroll batch
// ════════════════════════════════════════════════════
if ($method === 'POST' && $action === 'record-journal') {
    $period      = trim($input['pay_period']  ?? '');
    $entryDate   = trim($input['entry_date']  ?? date('Y-m-d'));
    $reference   = trim($input['reference']   ?? ('PAY-'.$period));
    $description = trim($input['description'] ?? ('Payroll Expense – '.$period));
    $lines       = $input['lines'] ?? [];

    if (!$period)        fail('pay_period is required', 422);
    if (empty($lines))   fail('Journal lines are required', 422);

    // Validate all lines have account_id, description, and numeric debit/credit
    foreach ($lines as $i => $line) {
        if (empty($line['account_id'])) fail("Line ".($i+1).": account_id is required", 422);
    }

    // Validate balance
    $totalDebit  = array_sum(array_column($lines, 'debit'));
    $totalCredit = array_sum(array_column($lines, 'credit'));
    if (abs($totalDebit - $totalCredit) >= 1) {
        fail(sprintf('Entry is not balanced: Debit %.2f ≠ Credit %.2f', $totalDebit, $totalCredit), 422);
    }

    // Ensure there are approved records for this period
    $chk = $pdo->prepare("SELECT COUNT(*) FROM payroll_records WHERE pay_period=? AND status='approved'");
    $chk->execute([$period]);
    if ((int)$chk->fetchColumn() === 0) fail('No approved payroll records found for period '.$period, 404);

    $userId   = $_SESSION['user_id'] ?? null;
    $userName = trim(($_SESSION['first_name'] ?? '').' '.($_SESSION['last_name'] ?? ''));

    $pdo->beginTransaction();

    // 1. Insert journal entry header
    $pdo->prepare("
        INSERT INTO journal_entries (entry_date, reference, description, reference_type, reference_id, posted, posted_by, posted_at, created_by)
        VALUES (?, ?, ?, 'payroll', ?, 1, ?, NOW(), ?)
    ")->execute([$entryDate, $reference, $description, $period, $userId, $userId]);
    $journalId = (int)$pdo->lastInsertId();

    // 2. Insert journal entry lines
    $lineStmt = $pdo->prepare("
        INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit)
        VALUES (?, ?, ?, ?, ?)
    ");
    foreach ($lines as $line) {
        $lineStmt->execute([
            $journalId,
            (int)$line['account_id'],
            trim($line['description'] ?? ''),
            round((float)($line['debit']  ?? 0), 2),
            round((float)($line['credit'] ?? 0), 2),
        ]);
    }

    // 3. Mark all approved payroll records for this period as paid
    $ref = $reference ?: ('PAY-'.$period);
    $pdo->prepare("
        UPDATE payroll_records
        SET finance_recorded=1,
            finance_recorded_by=?,
            finance_recorded_at=NOW(),
            finance_reference=?,
            status='paid'
        WHERE pay_period=? AND status='approved'
    ")->execute([$userId, $ref, $period]);

    // 4. Audit log
    $pdo->prepare("
        INSERT INTO payroll_approvals (payroll_batch_id, payroll_record_id, action, actor_id, actor_name, actor_role, notes)
        VALUES (?, NULL, 'finance_recorded', ?, ?, 'finance', ?)
    ")->execute([$period, $userId, $userName, 'Journal Entry #'.$journalId.' – '.$ref]);

    $pdo->commit();

    success([
        'journal_entry_id' => $journalId,
        'reference'        => $reference,
        'total_debit'      => $totalDebit,
        'total_credit'     => $totalCredit,
    ], 'Journal entry posted successfully');
}

fail('Method not allowed', 405);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    fail('DB error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    fail('Server error: ' . $e->getMessage(), 500);
}

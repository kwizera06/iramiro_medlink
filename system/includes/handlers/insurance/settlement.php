<?php
// handlers/insurance/settlement.php
// Processes insurance payment settlement and monthly invoice vouchers by visit date.
// 1. Write-off loss (Dr 1024, Cr insurance receivable)
// 2. Withholding tax 3% (Dr 1025, Cr insurance receivable)
// 3. Bank payment (Dr bank, Cr insurance receivable)

require_once __DIR__ . '/../../includes/database.php';

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}
function success($data = null, $message = 'Success', $status = 200) {
    jsonResponse(['success' => true, 'message' => $message, 'data' => $data], $status);
}
function error($message, $status = 400) {
    jsonResponse(['success' => false, 'message' => $message], $status);
}

/**
 * Computes monthly invoice vouchers and totals using VISIT DATE (COALESCE(v.visit_date, i.created_at)).
 * Guarantees that Invoice letter, Attachment, Excel export, and Months status have the exact same amounts.
 */
function getMonthlyInvoiceData($pdo, $insuranceId, $month, $year) {
    $months = ['','January','February','March','April','May','June','July','August','September','October','November','December'];

    // Get insurance company details
    $compStmt = $pdo->prepare("SELECT * FROM insurance_companies WHERE id = ?");
    $compStmt->execute([$insuranceId]);
    $company = $compStmt->fetch();
    if (!$company) return null;

    $isPrivate = ($insuranceId == 14 || $insuranceId == 13 || stripos($company['name'], 'prive') !== false || stripos($company['name'], 'private') !== false);

    // Fetch all invoices for this insurance in this month strictly by VISIT DATE
    $invStmt = $pdo->prepare("
        SELECT 
            i.id AS invoice_id,
            i.invoice_no,
            i.visit_id,
            i.patient_id,
            i.total,
            i.paid_amount,
            i.balance,
            i.status,
            i.created_at,
            DATE(COALESCE(v.visit_date, i.created_at)) AS visit_date,
            p.first_name,
            p.last_name,
            p.date_of_birth,
            p.gender,
            COALESCE(pi_ins.member_number, pi_ins.principal_member_number, '') AS member_number,
            'Self' AS relationship,
            COALESCE(
                (SELECT ip.copayment_percentage 
                 FROM patient_insurance pi 
                 JOIN insurance_packages ip ON ip.id = pi.package_id 
                 WHERE pi.id = v.insurance_id AND pi.patient_id = p.id LIMIT 1),
                (SELECT ip.copayment_percentage 
                 FROM patient_insurance pi 
                 JOIN insurance_packages ip ON ip.id = pi.package_id 
                 WHERE pi.patient_id = p.id 
                   AND (pi.company_id = i.insurance_id OR pi.id = i.insurance_id)
                 ORDER BY pi.is_primary DESC, pi.id DESC LIMIT 1),
                (SELECT ip.copayment_percentage 
                 FROM patient_insurance pi 
                 JOIN insurance_packages ip ON ip.id = pi.package_id 
                 WHERE pi.patient_id = p.id AND pi.is_primary = 1 LIMIT 1),
                (SELECT ip.copayment_percentage 
                 FROM insurance_packages ip 
                 WHERE ip.company_id = i.insurance_id AND ip.status = 'active'
                 ORDER BY ip.id ASC LIMIT 1),
                (SELECT ip.copayment_percentage 
                 FROM insurance_packages ip 
                 WHERE ip.company_id = i.insurance_id LIMIT 1),
                15
            ) AS copay_pct
        FROM invoices i
        LEFT JOIN visits v ON v.id = i.visit_id
        JOIN patients p ON p.id = i.patient_id
        LEFT JOIN patient_insurance pi_ins ON pi_ins.id = (
            SELECT pi2.id FROM patient_insurance pi2 
            WHERE pi2.patient_id = p.id AND pi2.company_id = ? 
            ORDER BY pi2.is_primary DESC, pi2.id DESC LIMIT 1
        )
        WHERE (
            i.insurance_id = ?
            OR EXISTS (SELECT 1 FROM patient_insurance pi_i WHERE pi_i.id = i.insurance_id AND pi_i.patient_id = i.patient_id AND pi_i.company_id = ?)
            OR (v.insurance_id = ? AND (i.payment_type = 'insurance' OR i.insurance_id IS NULL))
            OR EXISTS (SELECT 1 FROM patient_insurance pi_v WHERE pi_v.id = v.insurance_id AND pi_v.patient_id = v.patient_id AND pi_v.company_id = ? AND (i.payment_type = 'insurance' OR i.insurance_id IS NULL))
            OR (
                (i.payment_type = 'insurance' OR v.payment_type = 'insurance')
                AND (i.insurance_id IS NULL OR i.insurance_id = 0)
                AND (v.insurance_id IS NULL OR v.insurance_id = 0)
                AND EXISTS (SELECT 1 FROM patient_insurance pi_p WHERE pi_p.patient_id = p.id AND pi_p.company_id = ? AND pi_p.is_primary = 1)
            )
        )
        AND MONTH(COALESCE(v.visit_date, i.created_at)) = ?
        AND YEAR(COALESCE(v.visit_date, i.created_at)) = ?
        AND (i.status IS NULL OR i.status != 'cancelled')
        ORDER BY visit_date ASC, i.id ASC
    ");
    $invStmt->execute([$insuranceId, $insuranceId, $insuranceId, $insuranceId, $insuranceId, $insuranceId, $month, $year]);
    $invoices = $invStmt->fetchAll();

    if (empty($invoices)) {
        return [
            'insurance_id'      => $insuranceId,
            'insurance_name'    => $company['name'],
            'insurance_code'    => $company['code'] ?: $company['name'],
            'insurance_contact' => $company['contact_person'] ?? '',
            'insurance_phone'   => $company['phone'] ?? '',
            'insurance_email'   => $company['email'] ?? '',
            'insurance_address' => $company['address'] ?? '',
            'month'             => $month,
            'year'              => $year,
            'month_name'        => $months[$month] ?? '',
            'items'             => [],
            'total'             => 0,
            'gross_total'       => 0,
            'insurance_total'   => 0,
            'beneficiary_total' => 0,
            'totals'            => [
                'consult'     => 0,
                'hosp'        => 0,
                'lab'         => 0,
                'proc'        => 0,
                'med'         => 0,
                'total'       => 0,
                'beneficiary' => 0,
                'insurance'   => 0
            ],
            'item_count'        => 0,
            'invoice_count'     => 0,
            'all_paid'          => true
        ];
    }

    // Fetch invoice items for all these invoices
    $invIds = array_map(function($inv) { return (int)$inv['invoice_id']; }, $invoices);
    $itemsByInvoice = [];
    if (!empty($invIds)) {
        $placeholders = implode(',', array_fill(0, count($invIds), '?'));
        $itemsStmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id IN ($placeholders) ORDER BY id ASC");
        $itemsStmt->execute($invIds);
        $allItems = $itemsStmt->fetchAll();
        foreach ($allItems as $it) {
            $itemsByInvoice[$it['invoice_id']][] = $it;
        }
    }

    // Group invoices by visit (or unique invoice if visit_id is null)
    $visits = [];
    $isSettled = false;
    if ($month && $year) {
        $pattern = "%[{$month}/{$year}]%";
        $stlCheck = $pdo->prepare("SELECT COUNT(*) FROM journal_entries WHERE reference_type = 'insurance_settlement' AND reference_id = ? AND description LIKE ?");
        $stlCheck->execute([$insuranceId, $pattern]);
        $isSettled = ((int)$stlCheck->fetchColumn() > 0);
    }
    $allPaid = $isSettled;
    foreach ($invoices as $inv) {
        $vKey = $inv['visit_id'] ? 'v_' . $inv['visit_id'] : 'i_' . $inv['invoice_id'];
        if (!isset($visits[$vKey])) {
            $visits[$vKey] = [
                'visit_id'       => $inv['visit_id'],
                'patient_id'     => $inv['patient_id'],
                'patient_name'   => trim($inv['first_name'] . ' ' . $inv['last_name']),
                'date_of_birth'  => $inv['date_of_birth'] ?: '',
                'gender'         => $inv['gender'] ?: '',
                'member_number'  => $inv['member_number'] ?: '',
                'relationship'   => $inv['relationship'] ?: 'Self',
                'date'           => $inv['visit_date'],
                'copay_pct'      => (float)$inv['copay_pct'],
                'invoices'       => []
            ];
        }
        $visits[$vKey]['invoices'][] = $inv;
    }

    // Process categories and totals for each visit
    $processedItems = [];
    $totGross   = 0.0;
    $totConsult = 0.0; $totHosp = 0.0; $totLab = 0.0; $totProc = 0.0; $totMed = 0.0;
    $totBenef   = 0.0; $totIns = 0.0;

    foreach ($visits as $vKey => $v) {
        $vGross = 0.0;
        $vConsult = 0.0; $vHosp = 0.0; $vLab = 0.0; $vProc = 0.0; $vMed = 0.0;

        foreach ($v['invoices'] as $inv) {
            $invTotal = (float)$inv['total'];
            $vGross += $invTotal;

            $items = $itemsByInvoice[$inv['invoice_id']] ?? [];
            $itemsSum = 0.0;

            foreach ($items as $it) {
                $amt = (float)$it['total'];
                $itemsSum += $amt;
                $cat = strtolower(trim($it['category'] ?? ''));
                $desc = strtolower(trim($it['description'] ?? ''));

                if (in_array($cat, ['consultation', 'consult']) || strpos($desc, 'consultation') !== false || strpos($desc, 'consult') !== false) {
                    $vConsult += $amt;
                } elseif (in_array($cat, ['accommodation', 'ward', 'hospitalization', 'hospitalisation', 'inpatient', 'bed']) || strpos($desc, 'hospitali') !== false || strpos($desc, 'admission') !== false || strpos($desc, 'bed') !== false) {
                    $vHosp += $amt;
                } elseif (in_array($cat, ['lab', 'laboratory', 'radiology', 'imaging', 'xray', 'x-ray', 'scan', 'ultrasound', 'hematology', 'immuno-serology', 'parasitology', 'bacteriology', 'biochemistry', 'pathology', 'microbiology', 'serology', 'blood transfusion']) || strpos($desc, 'lab test') !== false || strpos($desc, 'blood') !== false || strpos($desc, 'stool') !== false || strpos($desc, 'urine') !== false || strpos($desc, 'smear') !== false || strpos($desc, 'culture') !== false || strpos($desc, 'crp') !== false || strpos($desc, 'aslo') !== false || strpos($desc, 'wbc') !== false || strpos($desc, 'rbc') !== false || strpos($desc, 'hemogram') !== false || strpos($desc, 'urinalysis') !== false) {
                    $vLab += $amt;
                } elseif (in_array($cat, ['medication', 'pharmacy', 'drug', 'medicine', 'drugs', 'medicines', 'pharmacy_item']) || strncmp($desc, 'medication', 10) === 0 || strpos($desc, 'tablet') !== false || strpos($desc, 'inject') !== false || strpos($desc, 'syrup') !== false || strpos($desc, 'capsule') !== false || strpos($desc, 'cream') !== false || strpos($desc, 'ointment') !== false) {
                    $vMed += $amt;
                } else {
                    $vProc += $amt;
                }
            }

            // Difference between invoice total and sum of items goes to procedures
            $diff = $invTotal - $itemsSum;
            if (abs($diff) > 0.001) {
                $vProc += $diff;
            }
        }

        // Reconcile rounding so categories equal vGross exactly
        $vCatSum = $vConsult + $vHosp + $vLab + $vProc + $vMed;
        if (abs($vCatSum - $vGross) > 0.001) {
            $vProc += ($vGross - $vCatSum);
        }

        $copayPct = $v['copay_pct'];
        if ($isPrivate) {
            $copayPct = 100.0;
        }

        $vBenef = round($vGross * ($copayPct / 100.0), 2);
        $vInsShare = round($vGross - $vBenef, 2);

        $itemRow = [
            'patient_name'   => $v['patient_name'],
            'date_of_birth'  => $v['date_of_birth'],
            'gender'         => $v['gender'],
            'member_number'  => $v['member_number'],
            'relationship'   => $v['relationship'],
            'date'           => $v['date'], // visit date
            'consult'        => round($vConsult, 2),
            'hosp'           => round($vHosp, 2),
            'lab'            => round($vLab, 2),
            'proc'           => round($vProc, 2),
            'med'            => round($vMed, 2),
            'total'          => round($vGross, 2),
            'beneficiary'    => round($vBenef, 2),
            'insurance'      => round($vInsShare, 2),
            'amount'         => round($vInsShare, 2)
        ];

        $processedItems[] = $itemRow;
        $totGross   += $vGross;
        $totConsult += $vConsult;
        $totHosp    += $vHosp;
        $totLab     += $vLab;
        $totProc    += $vProc;
        $totMed     += $vMed;
        $totBenef   += $vBenef;
        $totIns     += $vInsShare;
    }

    return [
        'insurance_id'      => $insuranceId,
        'insurance_name'    => $company['name'],
        'insurance_code'    => $company['code'] ?: $company['name'],
        'insurance_contact' => $company['contact_person'] ?? '',
        'insurance_phone'   => $company['phone'] ?? '',
        'insurance_email'   => $company['email'] ?? '',
        'insurance_address' => $company['address'] ?? '',
        'month'             => $month,
        'year'              => $year,
        'month_name'        => $months[$month] ?? '',
        'items'             => $processedItems,
        'total'             => round($totIns, 2), // Insurance portion payable
        'gross_total'       => round($totGross, 2), // 100% Total medical fees
        'insurance_total'   => round($totIns, 2),
        'beneficiary_total' => round($totBenef, 2),
        'totals'            => [
            'consult'     => round($totConsult, 2),
            'hosp'        => round($totHosp, 2),
            'lab'         => round($totLab, 2),
            'proc'        => round($totProc, 2),
            'med'         => round($totMed, 2),
            'total'       => round($totGross, 2),
            'beneficiary' => round($totBenef, 2),
            'insurance'   => round($totIns, 2)
        ],
        'item_count'        => count($processedItems),
        'invoice_count'     => count($invoices),
        'all_paid'          => $allPaid
    ];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'CLI') return;

try {

    // ===== GET - Monthly Invoice Data or Months List =====
    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';

        if ($action === 'monthly_invoice') {
            $insuranceId = (int)($_GET['insurance_id'] ?? 0);
            $month = (int)($_GET['month'] ?? 0);
            $year = (int)($_GET['year'] ?? 0);
            
            if (!$insuranceId) error('insurance_id required', 422);
            if (!$month || $month < 1 || $month > 12) error('Valid month (1-12) required', 422);
            if (!$year || $year < 2020) error('Valid year required', 422);
            
            $data = getMonthlyInvoiceData($pdo, $insuranceId, $month, $year);
            if (!$data) error('Insurance company not found', 404);
            
            success($data);
        }

        // ===== GET - Paid/Pending months per insurance using VISIT DATE =====
        if ($action === 'paid_pending_months') {
            $insuranceId = (int)($_GET['insurance_id'] ?? 0);
            if (!$insuranceId) error('insurance_id required', 422);

            $compStmt = $pdo->prepare("SELECT * FROM insurance_companies WHERE id = ?");
            $compStmt->execute([$insuranceId]);
            $company = $compStmt->fetch();
            if (!$company) error('Insurance company not found', 404);

            // Find every month/year that has insurance invoices grouped strictly by VISIT DATE
            $stmt = $pdo->prepare("
                SELECT
                    YEAR(COALESCE(v.visit_date, i.created_at))  AS yr,
                    MONTH(COALESCE(v.visit_date, i.created_at)) AS mo,
                    COUNT(DISTINCT i.id) AS invoice_count,
                    COUNT(DISTINCT COALESCE(i.visit_id, CONCAT('inv_', i.id))) AS visit_count
                FROM invoices i
                LEFT JOIN visits v ON v.id = i.visit_id
                JOIN patients p ON p.id = i.patient_id
                WHERE (
                    i.insurance_id = ?
                    OR EXISTS (SELECT 1 FROM patient_insurance pi_i WHERE pi_i.id = i.insurance_id AND pi_i.patient_id = i.patient_id AND pi_i.company_id = ?)
                    OR (v.insurance_id = ? AND (i.payment_type = 'insurance' OR i.insurance_id IS NULL))
                    OR EXISTS (SELECT 1 FROM patient_insurance pi_v WHERE pi_v.id = v.insurance_id AND pi_v.patient_id = v.patient_id AND pi_v.company_id = ? AND (i.payment_type = 'insurance' OR i.insurance_id IS NULL))
                    OR (
                        (i.payment_type = 'insurance' OR v.payment_type = 'insurance')
                        AND (i.insurance_id IS NULL OR i.insurance_id = 0)
                        AND (v.insurance_id IS NULL OR v.insurance_id = 0)
                        AND EXISTS (SELECT 1 FROM patient_insurance pi_p WHERE pi_p.patient_id = p.id AND pi_p.company_id = ? AND pi_p.is_primary = 1)
                    )
                )
                AND (i.status IS NULL OR i.status != 'cancelled')
                GROUP BY YEAR(COALESCE(v.visit_date, i.created_at)), MONTH(COALESCE(v.visit_date, i.created_at))
                ORDER BY yr DESC, mo DESC
            ");
            $stmt->execute([$insuranceId, $insuranceId, $insuranceId, $insuranceId, $insuranceId]);
            $rows = $stmt->fetchAll();

            $months = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
            $paid = [];
            $pending = [];

            foreach ($rows as $row) {
                $mo = (int)$row['mo'];
                $yr = (int)$row['yr'];

                // Calculate consistent figures based on visit date
                $mResult = getMonthlyInvoiceData($pdo, $insuranceId, $mo, $yr);
                $insTotal = $mResult ? (float)($mResult['total'] ?? 0) : 0;
                $grossTotal = $mResult ? (float)($mResult['gross_total'] ?? 0) : 0;
                $isPaid = $mResult ? (($mResult['all_paid'] ?? false) || ($insTotal <= 0)) : false;

                $entry = [
                    'month'          => $mo,
                    'year'           => $yr,
                    'month_name'     => $months[$mo] ?? '',
                    'total_amount'   => $insTotal,
                    'gross_amount'   => $grossTotal,
                    'pending_amount' => $isPaid ? 0.0 : $insTotal,
                    'invoice_count'  => (int)$row['invoice_count'],
                    'visit_count'    => (int)$row['visit_count'],
                    'status'         => $isPaid ? 'paid' : 'pending'
                ];
                if ($isPaid) $paid[] = $entry;
                else         $pending[] = $entry;
            }

            success([
                'insurance_id'   => $insuranceId,
                'insurance_name' => $company['name'],
                'pending'        => $pending,
                'paid'           => $paid
            ]);
        }
        
        error('Invalid action', 400);
    }

    if ($method !== 'POST') error('Method not allowed', 405);

    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    // Required fields
    $insuranceId      = (int)($input['insurance_id'] ?? 0);
    $insuranceAccId   = (int)($input['insurance_account_id'] ?? 0);
    $bankAccountId    = (int)($input['bank_account_id'] ?? 0);
    $writeoffAmount   = (float)($input['writeoff_amount'] ?? 0);
    $whtAmount        = (float)($input['wht_amount'] ?? 0);
    $netAmount        = (float)($input['net_amount'] ?? 0);
    $totalBalance     = (float)($input['total_balance'] ?? 0);
    $entryDate        = $input['entry_date'] ?? date('Y-m-d');
    $reference        = trim($input['reference'] ?? '');

    // Validation
    if (!$insuranceId) error('insurance_id required', 422);
    if (!$insuranceAccId) error('insurance_account_id required', 422);
    if (!$bankAccountId) error('bank_account_id required', 422);
    if ($netAmount <= 0) error('Net payment amount must be greater than 0', 422);

    // Verify insurance account exists
    $accCheck = $pdo->prepare("SELECT id, account_name FROM chart_of_accounts WHERE id = ? AND linked_insurance_id = ?");
    $accCheck->execute([$insuranceAccId, $insuranceId]);
    $insAccount = $accCheck->fetch();
    if (!$insAccount) error('Insurance receivable account not found', 404);

    // Verify bank account exists (under parent_id=3)
    $bankCheck = $pdo->prepare("SELECT id, account_name FROM chart_of_accounts WHERE id = ? AND parent_id = 3");
    $bankCheck->execute([$bankAccountId]);
    $bankAccount = $bankCheck->fetch();
    if (!$bankAccount) error('Bank account not found', 404);

    $month = (int)($input['month'] ?? 0);
    $year  = (int)($input['year'] ?? 0);

    // Verify current balance: check against real monthly balance or passed total balance
    if ($month && $year) {
        $mCheck = getMonthlyInvoiceData($pdo, $insuranceId, $month, $year);
        $currentBalance = $mCheck ? (float)($mCheck['total'] ?? 0) : $totalBalance;
    } else {
        $currentBalance = $totalBalance > 0 ? $totalBalance : 999999999;
    }

    $settlementTotal = $writeoffAmount + $whtAmount + $netAmount;
    if ($currentBalance > 0 && $settlementTotal > $currentBalance + 1) {
        error('Settlement total exceeds current balance ('.$currentBalance.')', 422);
    }
    // Adjust net to match exact balance if within rounding tolerance
    if ($currentBalance > 0 && $settlementTotal > $currentBalance && $settlementTotal <= $currentBalance + 1) {
        $netAmount = $currentBalance - $writeoffAmount - $whtAmount;
    }

    // Get insurance company name
    $compStmt = $pdo->prepare("SELECT name FROM insurance_companies WHERE id = ?");
    $compStmt->execute([$insuranceId]);
    $companyName = $compStmt->fetchColumn() ?: 'Insurance';

    $pdo->beginTransaction();
    $journalRefs = [];

    try {
        // Generate sequential reference
        $lastRef = $pdo->query("SELECT reference FROM journal_entries ORDER BY id DESC LIMIT 1")->fetchColumn();
        $seq = 1;
        if ($lastRef && preg_match('/(\d+)$/', $lastRef, $m)) $seq = (int)$m[1] + 1;

        $monthTag = ($month && $year) ? " [{$month}/{$year}]" : "";

        // =============================================
        // ENTRY 1: Write-off Loss (if writeoff > 0)
        // Dr: Loss Account (1024)
        // Cr: Insurance Receivable
        // =============================================
        if ($writeoffAmount > 0) {
            $ref1 = 'STL-' . date('Ymd', strtotime($entryDate)) . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
            $desc1 = 'Write-off loss - ' . $companyName . $monthTag . ($reference ? ' - ' . $reference : '');

            $pdo->prepare("INSERT INTO journal_entries (entry_date, reference, description, reference_type, reference_id, posted, posted_at, created_at) VALUES (?, ?, ?, 'insurance_settlement', ?, 1, NOW(), NOW())")
                ->execute([$entryDate, $ref1, $desc1, $insuranceId]);
            $jeId1 = (int)$pdo->lastInsertId();

            // Dr: Loss Account (1024)
            $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?, 1024, ?, ?, 0)")
                ->execute([$jeId1, 'Insurance write-off loss - ' . $companyName, $writeoffAmount]);

            // Cr: Insurance Receivable
            $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?, ?, ?, 0, ?)")
                ->execute([$jeId1, $insuranceAccId, 'Write-off settled - ' . $companyName, $writeoffAmount]);

            $journalRefs[] = $ref1;
            $seq++;
        }

        // =============================================
        // ENTRY 2: Withholding Tax (if wht > 0)
        // Dr: WHT Account (1025)
        // Cr: Insurance Receivable
        // =============================================
        if ($whtAmount > 0) {
            $ref2 = 'STL-' . date('Ymd', strtotime($entryDate)) . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
            $desc2 = 'Withholding tax 3% - ' . $companyName . $monthTag . ($reference ? ' - ' . $reference : '');

            $pdo->prepare("INSERT INTO journal_entries (entry_date, reference, description, reference_type, reference_id, posted, posted_at, created_at) VALUES (?, ?, ?, 'insurance_settlement', ?, 1, NOW(), NOW())")
                ->execute([$entryDate, $ref2, $desc2, $insuranceId]);
            $jeId2 = (int)$pdo->lastInsertId();

            // Dr: WHT Account (1025)
            $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?, 1025, ?, ?, 0)")
                ->execute([$jeId2, 'Withholding tax 3% - ' . $companyName, $whtAmount]);

            // Cr: Insurance Receivable
            $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?, ?, ?, 0, ?)")
                ->execute([$jeId2, $insuranceAccId, 'WHT deducted - ' . $companyName, $whtAmount]);

            $journalRefs[] = $ref2;
            $seq++;
        }

        // =============================================
        // ENTRY 3: Bank Payment
        // Dr: Bank Account (selected)
        // Cr: Insurance Receivable
        // =============================================
        $ref3 = 'STL-' . date('Ymd', strtotime($entryDate)) . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
        $desc3 = 'Insurance payment received - ' . $companyName . $monthTag . ($reference ? ' - ' . $reference : '');

        $pdo->prepare("INSERT INTO journal_entries (entry_date, reference, description, reference_type, reference_id, posted, posted_at, created_at) VALUES (?, ?, ?, 'insurance_settlement', ?, 1, NOW(), NOW())")
            ->execute([$entryDate, $ref3, $desc3, $insuranceId]);
        $jeId3 = (int)$pdo->lastInsertId();

        // Dr: Bank Account
        $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?, ?, ?, ?, 0)")
            ->execute([$jeId3, $bankAccountId, 'Insurance payment - ' . $companyName, $netAmount]);

        // Cr: Insurance Receivable
        $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?, ?, ?, 0, ?)")
            ->execute([$jeId3, $insuranceAccId, 'Payment received - ' . $companyName, $netAmount]);

        $journalRefs[] = $ref3;

        // =============================================
        // Mark invoices for this month as paid (by visit date)
        // =============================================
        if ($month && $year) {
            $pdo->prepare("
                UPDATE invoices i
                LEFT JOIN visits v ON v.id = i.visit_id
                JOIN patients p ON p.id = i.patient_id
                SET i.status = 'paid', i.paid_amount = i.total, i.balance = 0
                WHERE (
                    i.insurance_id = ?
                    OR EXISTS (SELECT 1 FROM patient_insurance pi_i WHERE pi_i.id = i.insurance_id AND pi_i.patient_id = i.patient_id AND pi_i.company_id = ?)
                    OR (v.insurance_id = ? AND (i.payment_type = 'insurance' OR i.insurance_id IS NULL))
                    OR EXISTS (SELECT 1 FROM patient_insurance pi_v WHERE pi_v.id = v.insurance_id AND pi_v.patient_id = v.patient_id AND pi_v.company_id = ? AND (i.payment_type = 'insurance' OR i.insurance_id IS NULL))
                    OR (
                        (i.payment_type = 'insurance' OR v.payment_type = 'insurance')
                        AND (i.insurance_id IS NULL OR i.insurance_id = 0)
                        AND (v.insurance_id IS NULL OR v.insurance_id = 0)
                        AND EXISTS (SELECT 1 FROM patient_insurance pi_p WHERE pi_p.patient_id = p.id AND pi_p.company_id = ? AND pi_p.is_primary = 1)
                    )
                )
                AND MONTH(COALESCE(v.visit_date, i.created_at)) = ? 
                AND YEAR(COALESCE(v.visit_date, i.created_at)) = ?
                AND i.status != 'cancelled'
            ")->execute([$insuranceId, $insuranceId, $insuranceId, $insuranceId, $insuranceId, $month, $year]);
        }

        $pdo->commit();

        success([
            'journal_refs' => $journalRefs,
            'writeoff' => $writeoffAmount,
            'wht' => $whtAmount,
            'net_payment' => $netAmount,
            'total_settled' => $writeoffAmount + $whtAmount + $netAmount
        ], 'Settlement posted successfully. Journals: ' . implode(', ', $journalRefs));

    } catch (Exception $e) {
        $pdo->rollBack();
        error('Failed to post settlement: ' . $e->getMessage());
    }

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error('Database error: ' . $e->getMessage(), 500);
}
?>

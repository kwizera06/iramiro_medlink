<?php
// Insurance billing matrix — CLI self-check / demo scenario.
//
//   php tests/insurance-billing-matrix-check.php            run, then roll everything back
//   php tests/insurance-billing-matrix-check.php --keep     run and keep the seeded rows for the UI
//   php tests/insurance-billing-matrix-check.php --no-diag  skip the edge probes
//
// Seeds four patients on the live schema — self-pay, RSSB (public, 15% copay), SANLAM
// (private, 10%), PRIME (full cover, 0%) — each with a consultation, a lab test, a
// procedure and a medication line. Every invoice is priced through the real resolvers,
// re-totalled by recalcInvoice(), posted by fin_post_invoice(), paid through the shared
// payment_records + allocatePayment() + updateInvoiceBalance() + fin_sync_payment()
// helpers, and then the ledger is checked account by account.
//
// The edge probes (DIAG) compare those helpers against the paths the UI actually uses
// (billing.php record_payment_full, finance/payment-records.php) and against the
// item-level coverage rules.
//
// Nothing here writes outside the transaction unless --keep is passed.

if (PHP_SAPI !== 'cli') { exit(1); }

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/billing-calc.php';
require_once __DIR__ . '/../includes/tariff.php';
require_once __DIR__ . '/../includes/payment_helpers.php'; // also loads finance_posting.php

$keep   = in_array('--keep', $argv, true);
$noDiag = in_array('--no-diag', $argv, true);
$run    = date('ymdHis') . random_int(10, 99);

/* ---------------------------------------------------------------- reporting */

$GLOBALS['C'] = ['pass' => 0, 'fail' => 0, 'diag' => 0, 'notes' => []];

function check(bool $ok, string $label, bool $diag = false): void
{
    if ($diag) {
        if (!$ok) $GLOBALS['C']['diag']++;
        echo ($ok ? "  ok   " : "  DIAG ") . $label . "\n";
        return;
    }
    if ($ok) { $GLOBALS['C']['pass']++; echo "  ok   $label\n"; }
    else     { $GLOBALS['C']['fail']++; echo "  FAIL $label\n"; }
}

function note(string $text): void { $GLOBALS['C']['notes'][] = $text; }

function money($v): string { return number_format((float)$v, 0, '.', ','); }
function money2($v): string { return number_format((float)$v, 2, '.', ','); }

function eq(float $a, float $b, float $tol = 0.01): bool { return abs($a - $b) <= $tol; }

/* ---------------------------------------------------------------- seeding */

function seed_patient(PDO $pdo, string $no, string $first, string $last): int
{
    $pdo->prepare("INSERT INTO patients (patient_no, first_name, last_name, date_of_birth, gender, phone, nationality, status)
                   VALUES (?, ?, ?, '1990-01-01', 'other', '0700000000', 'Rwandan', 'active')")
        ->execute([$no, $first, $last]);
    return (int)$pdo->lastInsertId();
}

function seed_policy(PDO $pdo, int $patientId, int $companyId, int $packageId): int
{
    $pdo->prepare("INSERT INTO patient_insurance (patient_id, company_id, package_id, policy_number, member_number,
                       policy_start_date, policy_end_date, is_primary, status)
                   VALUES (?, ?, ?, ?, ?, CURDATE(), '9999-12-31', 1, 'active')")
        ->execute([$patientId, $companyId, $packageId, 'POL-TST-' . uniqid(), 'MEM-' . uniqid()]);
    return (int)$pdo->lastInsertId();
}

function seed_visit(PDO $pdo, int $patientId, ?int $companyId, string $visitNo, int $deptId = 3): int
{
    $pdo->prepare("INSERT INTO visits (visit_no, patient_id, visit_date, visit_time, payment_type, tariff_category,
                       insurance_id, department_id, visit_type, status)
                   VALUES (?, ?, CURDATE(), CURTIME(), ?, 'rwanda', ?, ?, 'outpatient', 'open')")
        ->execute([$visitNo, $patientId, $companyId ? 'insurance' : 'self_pay', $companyId, $deptId]);
    return (int)$pdo->lastInsertId();
}

/** @param array<int,array{service_id?:?int,item_id?:?int,desc:string,cat:string,qty:int,unit:float,ins:float,apply:int}> $lines */
function seed_invoice(PDO $pdo, int $visitId, int $patientId, ?int $companyId, array $lines, string $notes): array
{
    $no = generateInvoiceNo($pdo);
    $pdo->prepare("INSERT INTO invoices (invoice_no, visit_id, patient_id, payment_type, insurance_id, subtotal, total, balance, status, notes, created_at)
                   VALUES (?, ?, ?, ?, ?, 0, 0, 0, 'draft', ?, NOW())")
        ->execute([$no, $visitId, $patientId, $companyId ? 'insurance' : 'self_pay', $companyId, $notes]);
    $id = (int)$pdo->lastInsertId();

    $ins = $pdo->prepare("INSERT INTO invoice_items (invoice_id, service_id, item_id, description, category, quantity,
                              unit_price, insurance_price, patient_topup, apply_insurance, discount, total)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 0, ?)");
    foreach ($lines as $l) {
        $ins->execute([
            $id,
            $l['service_id'] ?? null,
            $l['item_id'] ?? null,
            $l['desc'],
            $l['cat'],
            $l['qty'],
            $l['unit'],
            $l['ins'],
            $l['apply'],
            $l['qty'] * $l['unit'],
        ]);
    }
    recalcInvoice($pdo, $id);
    return ['id' => $id, 'no' => $no, 'lines' => $lines];
}

/* ---------------------------------------------------------------- pricing */

/** Catalog line priced by the canonical resolver (#41) and, for insured patients, marked covered. */
function catalog_line(PDO $pdo, int $serviceId, ?int $companyId, string $cat, int $qty = 1): array
{
    $unit = servicePrice($pdo, $serviceId, $companyId, 'rwanda');
    $name = $pdo->query("SELECT procedure_name FROM service_catalog WHERE id = " . (int)$serviceId)->fetchColumn();
    return [
        'service_id' => $serviceId,
        'desc'       => (string)$name,
        'cat'        => $cat,
        'qty'        => $qty,
        'unit'       => $unit,
        'ins'        => $companyId ? $unit : 0.0,
        'apply'      => $companyId ? 1 : 0,
    ];
}

/** Medication line priced on the insurer's item tariff, else the selling price. */
function medication_line(PDO $pdo, int $itemId, ?int $companyId, int $qty): array
{
    $unit = 0.0;
    if ($companyId) {
        $q = $pdo->prepare("SELECT price FROM item_insurance_prices WHERE item_id = ? AND insurance_id = ? LIMIT 1");
        $q->execute([$itemId, $companyId]);
        $unit = (float)$q->fetchColumn();
    }
    if ($unit <= 0) {
        $q = $pdo->prepare("SELECT selling_price FROM items WHERE id = ?");
        $q->execute([$itemId]);
        $unit = (float)$q->fetchColumn();
    }
    $name = $pdo->query("SELECT name FROM items WHERE id = " . (int)$itemId)->fetchColumn();
    return [
        'item_id' => $itemId,
        'desc'    => 'Medication — ' . $name,
        'cat'     => 'pharmacy',
        'qty'     => $qty,
        'unit'    => $unit,
        'ins'     => $companyId ? $unit : 0.0,
        'apply'   => $companyId ? 1 : 0,
    ];
}

/* ---------------------------------------------------------------- payment + ledger */

function add_payment(PDO $pdo, int $invoiceId, int $patientId, float $amount, string $method, string $run,
                     int $seq, bool $rebalance = true, bool $allocate = true, bool $sync = true): int
{
    $pdo->prepare("INSERT INTO payment_records (invoice_id, patient_id, amount_paid, payment_method, payment_date,
                       payment_time, receipt_number, status, notes)
                   VALUES (?, ?, ?, ?, CURDATE(), CURTIME(), ?, 'completed', 'insurance billing matrix')")
        ->execute([$invoiceId, $patientId, $amount, $method, 'RCP-' . $run . '-' . str_pad((string)$seq, 3, '0', STR_PAD_LEFT)]);
    $id = (int)$pdo->lastInsertId();

    if ($allocate) allocatePayment($pdo, $id, $invoiceId, $amount);
    if ($rebalance) updateInvoiceBalance($pdo, $invoiceId);
    if ($sync) fin_sync_payment($pdo, $id);
    return $id;
}

/** Net debit (debit - credit) per account for these invoice and payment records. */
function gl_nets(PDO $pdo, array $invoiceIds, array $paymentIds): array
{
    $out = [];
    $add = function (array $rows) use (&$out) {
        foreach ($rows as $r) {
            $a = (int)$r['account_id'];
            $out[$a] = round(($out[$a] ?? 0) + (float)$r['n'], 2);
        }
    };
    if ($invoiceIds) {
        $in = implode(',', array_fill(0, count($invoiceIds), '?'));
        $q = $pdo->prepare("SELECT jl.account_id, SUM(jl.debit - jl.credit) AS n
                            FROM journal_entries je JOIN journal_entry_lines jl ON jl.journal_entry_id = je.id
                            WHERE je.reference_type IN ('invoice','invoice_adj') AND je.reference_id IN ($in)
                            GROUP BY jl.account_id");
        $q->execute(array_map('strval', $invoiceIds));
        $add($q->fetchAll());
    }
    if ($paymentIds) {
        $in = implode(',', array_fill(0, count($paymentIds), '?'));
        $q = $pdo->prepare("SELECT jl.account_id, SUM(jl.debit - jl.credit) AS n
                            FROM journal_entries je JOIN journal_entry_lines jl ON jl.journal_entry_id = je.id
                            WHERE je.reference_type IN ('payment_record','payment_reversal') AND je.reference_id IN ($in)
                            GROUP BY jl.account_id");
        $q->execute(array_map('strval', $paymentIds));
        $add($q->fetchAll());
    }
    return array_filter($out, fn($v) => abs($v) >= 0.005);
}

function gl_totals(PDO $pdo, array $invoiceIds, array $paymentIds): array
{
    $out = ['debit' => 0.0, 'credit' => 0.0];
    $collect = function (string $types, array $ids) use ($pdo, &$out) {
        if (!$ids) return;
        $in = implode(',', array_fill(0, count($ids), '?'));
        $q = $pdo->prepare("SELECT COALESCE(SUM(jl.debit),0) d, COALESCE(SUM(jl.credit),0) c
                            FROM journal_entries je JOIN journal_entry_lines jl ON jl.journal_entry_id = je.id
                            WHERE je.reference_type IN ($types) AND je.reference_id IN ($in)");
        $q->execute(array_map('strval', $ids));
        $r = $q->fetch();
        $out['debit'] += (float)$r['d'];
        $out['credit'] += (float)$r['c'];
    };
    $collect("'invoice','invoice_adj'", $invoiceIds);
    $collect("'payment_record','payment_reversal'", $paymentIds);
    return $out;
}

function invoice_row(PDO $pdo, int $id): array
{
    $q = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
    $q->execute([$id]);
    return $q->fetch();
}

/** Expected patient share, line by line (the rule documented in INSURANCE_SYSTEM_DOCUMENTATION.md). */
function expected_patient_share(array $lines, float $copay): float
{
    $sum = 0.0;
    foreach ($lines as $l) {
        if ($l['apply'] && $l['unit'] > 0) $sum += round($l['unit'] * $copay / 100 * $l['qty']);
        else                               $sum += round($l['unit'] * $l['qty']);
    }
    return round($sum);
}

/* ---------------------------------------------------------------- scenario */

$matrix = [
    ['code' => 'SP', 'label' => 'Self-pay (no insurance)',        'company' => null, 'package' => null, 'copay' => 100.0],
    ['code' => 'RS', 'label' => 'RSSB (public), 15% copay',       'company' => 7,    'package' => 19,   'copay' => 15.0],
    ['code' => 'SL', 'label' => 'SANLAM (private), 10% copay',    'company' => 9,    'package' => 13,   'copay' => 10.0],
    ['code' => 'PR', 'label' => 'PRIME (private), 0% copay',      'company' => 11,   'package' => 6,    'copay' => 0.0],
];

$revKeys = ['consultation' => 'rev_consultation', 'lab' => 'rev_lab', 'procedure' => 'rev_procedure', 'pharmacy' => 'rev_pharmacy'];
$summary = [];
$paySeq  = 0;

$pdo->beginTransaction();
try {
    $patientAr  = fin_private_ar_acct($pdo);
    $cashAcct   = fin_acct($pdo, 'cash');
    $bankAcct   = fin_acct($pdo, 'bank');

    echo "Insurance billing matrix — run $run\n";
    echo str_repeat('=', 78) . "\n";

    foreach ($matrix as $m) {
        $code = $m['code'];
        $company = $m['company'];
        $copay = $m['copay'];

        echo "\n[{$code}] {$m['label']}\n";

        $patientId = seed_patient($pdo, 'T' . $run . $code, 'Matrix', $code . ' ' . $run);
        if ($company) seed_policy($pdo, $patientId, $company, $m['package']);
        $visitId = seed_visit($pdo, $patientId, $company, 'V' . $run . $code);

        $lines = [
            catalog_line($pdo, 513, $company, 'consultation'),          // GP consultation
            catalog_line($pdo, 3243, $company, 'lab'),                  // full blood count
            catalog_line($pdo, 1364, $company, 'procedure'),            // simple wound dressing
            medication_line($pdo, 20, $company, 2),                     // paracetamol inject x2
        ];
        $inv = seed_invoice($pdo, $visitId, $patientId, $company, $lines, 'Insurance matrix core');
        $invId = $inv['id'];

        $total  = round(array_sum(array_map(fn($l) => $l['qty'] * $l['unit'], $lines)), 2);
        $itemPatient = expected_patient_share($lines, $copay);
        $split  = fin_split($total, $copay);
        $flatPatient = (float)$split['patient'];
        $insShare    = (float)$split['insurer'];

        // Resolver price per line
        $itemRows = $pdo->query("SELECT unit_price, total FROM invoice_items WHERE invoice_id = $invId ORDER BY id")->fetchAll();
        foreach ($lines as $i => $l) {
            $lineRow = $itemRows[$i];
            check(eq((float)$lineRow['unit_price'], $l['unit']) && eq((float)$lineRow['total'], $l['qty'] * $l['unit']),
                sprintf('%s line "%s": %s x %d = %s', $code, substr($l['desc'], 0, 32), money($l['unit']), $l['qty'], money($l['qty'] * $l['unit'])));
        }

        $resolvedCopay = getPatientCopayPercentage($pdo, $patientId, $company ?: null);
        check(eq($resolvedCopay, $copay), "$code copay resolves to {$copay}% (got {$resolvedCopay}%)");

        $row = invoice_row($pdo, $invId);
        check(eq((float)$row['total'], $total), "$code invoice total = " . money($total));
        check($company
                ? ((int)$row['insurance_id'] === $company && $row['payment_type'] === 'insurance')
                : ($row['insurance_id'] === null && $row['payment_type'] === 'self_pay'),
            "$code invoice carries the right payer " . ($company ? "company #$company" : 'self-pay / no insurer'));

        check(eq((float)$row['balance'], $itemPatient),
            "$code recalcInvoice patient share = " . money($itemPatient) . ' (got ' . money($row['balance']) . ')');
        if ($company && $copay < 100) {
            check(eq($itemPatient, $flatPatient, 1.01),
                "$code item-aware share " . money($itemPatient) . ' matches flat copay share ' . money($flatPatient) . ' (rounding only)');
        }

        // Post through the canonical ledger path used by consultation/procedures.
        fin_post_invoice($pdo, $invId);
        $row = invoice_row($pdo, $invId);
        check(eq((float)$row['balance'], $flatPatient),
            "$code after fin_post_invoice balance = " . money($flatPatient) . ' (got ' . money($row['balance']) . ')');

        $insurerAr = $company ? fin_insurer_acct($pdo, $company) : 0;
        $gl = gl_nets($pdo, [$invId], []);
        check(eq((float)($gl[$patientAr] ?? 0), $flatPatient),
            "$code invoice Dr patient receivable = " . money($flatPatient));
        if ($company) {
            check(eq((float)($gl[$insurerAr] ?? 0), $insShare),
                "$code invoice Dr insurer #$company receivable = " . money($insShare));
        } else {
            $insurerAccts = array_map('intval', $pdo->query("SELECT id FROM chart_of_accounts
                WHERE parent_id = " . fin_acct($pdo, 'ar_general') . " AND linked_insurance_id IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN));
            $leak = array_intersect(array_map('intval', array_keys($gl)), $insurerAccts);
            check(!$leak, "$code no insurance receivable line on a self-pay invoice");
        }

        // Revenue credited by service category.
        $catTotals = [];
        foreach ($lines as $l) $catTotals[$l['cat']] = ($catTotals[$l['cat']] ?? 0) + $l['qty'] * $l['unit'];
        foreach ($revKeys as $cat => $key) {
            $acct = fin_acct($pdo, $key);
            check(eq((float)($gl[$acct] ?? 0), -1 * ($catTotals[$cat] ?? 0)),
                "$code revenue $cat credited to account $acct = " . money($catTotals[$cat] ?? 0));
        }
        $bal = gl_totals($pdo, [$invId], []);
        check(eq($bal['debit'], $bal['credit']), "$code invoice journal is balanced (" . money($bal['debit']) . ')');

        // Patient pays their share (cash).
        $payIds = [];
        if ($flatPatient > 0) {
            $payIds[] = add_payment($pdo, $invId, $patientId, $flatPatient, 'cash', $run, ++$paySeq);
            $row = invoice_row($pdo, $invId);
            check(eq((float)$row['paid_amount'], $flatPatient), "$code patient paid " . money($flatPatient));
            check(eq((float)$row['balance'], 0) && $row['status'] === 'paid',
                "$code invoice settled by the patient (balance " . money($row['balance']) . ', ' . $row['status'] . ')');

            $alloc = $pdo->prepare("SELECT COUNT(*) n, COALESCE(SUM(allocated_amount),0) s FROM payment_allocations WHERE payment_record_id = ?");
            $alloc->execute([$payIds[0]]);
            $a = $alloc->fetch();
            // Auto-allocation rounds each line to the cent, so the stored rows can add up to
            // one cent per line short of the payment (allocatePayment never absorbs the rest).
            $allocSum = (float)$a['s'];
            $allocGap = abs($allocSum - $flatPatient);
            check((int)$a['n'] === count($lines) && $allocGap <= 0.01 * count($lines),
                "$code payment allocated across " . $a['n'] . ' lines = ' . money2($allocSum) .
                ($allocGap > 0.004 ? ' (rounding gap ' . money2($allocGap) . ')' : ''));

            $glPay = gl_nets($pdo, [], $payIds);
            check(eq((float)($glPay[$cashAcct] ?? 0), $flatPatient), "$code patient money Dr cash " . money($flatPatient));
            check(eq((float)($glPay[$patientAr] ?? 0), -1 * $flatPatient), "$code patient money Cr patient receivable");
        }

        // Insurer settles its share (bank).
        if ($insShare > 0) {
            $insPay = add_payment($pdo, $invId, $patientId, $insShare, 'insurance', $run, ++$paySeq, false);
            $payIds[] = $insPay;
            $glPay = gl_nets($pdo, [], $payIds);
            check(eq((float)($glPay[$bankAcct] ?? 0), $insShare), "$code insurer money Dr bank " . money($insShare));
            check(eq((float)($glPay[$insurerAr] ?? 0), -1 * $insShare), "$code insurer money Cr insurer #$company receivable");
            check(eq((float)($glPay[$patientAr] ?? 0), -1 * $flatPatient), "$code insurer payment did not touch the patient receivable");
        }

        // End state: both receivables cleared, cash/bank equals the bill, revenue equals the bill.
        $final = gl_nets($pdo, [$invId], $payIds);
        check(eq((float)($final[$patientAr] ?? 0), 0), "$code patient receivable nets to 0");
        if ($company) check(eq((float)($final[$insurerAr] ?? 0), 0), "$code insurer #$company receivable nets to 0");
        check(eq((float)($final[$cashAcct] ?? 0) + (float)($final[$bankAcct] ?? 0), $total),
            "$code cash + bank collected = invoice total " . money($total));

        $summary[] = [
            'code' => $code, 'patient' => 'T' . $run . $code, 'patient_id' => $patientId, 'invoice' => $inv['no'],
            'insurer' => $company ? $pdo->query("SELECT name FROM insurance_companies WHERE id = $company")->fetchColumn() : '—',
            'copay' => $copay, 'total' => $total, 'patient_due' => $flatPatient, 'insurer_due' => $insShare,
            'patient_paid' => $flatPatient, 'insurer_paid' => $insShare,
        ];
    }

    /* ------------------------------------------------------------ edge probes */

    if (!$noDiag) {
        echo "\n" . str_repeat('-', 78) . "\nEdge probes (known divergences are reported as DIAG, they do not fail the run)\n";
        // Probes write throwaway invoices. Keep them out of the --keep snapshot: they are
        // meant to be read, not looked at in the UI next to the four clean scenarios.
        $pdo->exec('SAVEPOINT probes');

        // D1 — self-pay consultation: canonical resolver says price_non_insured, registration bills facility price.
        $spPatient = (int)$pdo->query("SELECT id FROM patients WHERE patient_no = 'T" . $run . "SP'")->fetchColumn();
        $spVisit = seed_visit($pdo, $spPatient, null, 'V' . $run . 'SP2');
        $ci = createConsultationInvoice($pdo, $spVisit, 513, null);
        $expectedSelfPay = servicePrice($pdo, 513, null, 'rwanda');
        check($ci && eq((float)$ci['total'], $expectedSelfPay), 'D1 self-pay consultation: registration bills ' .
            money($ci['total'] ?? 0) . ' but servicePrice() (price_non_insured) says ' . money($expectedSelfPay), true);

        // D2 — deliberately uncovered line: item-aware share is right, flat total×copay is wrong.
        $rsPatient = (int)$pdo->query("SELECT id FROM patients WHERE patient_no = 'T" . $run . "RS'")->fetchColumn();
        $rsVisit = seed_visit($pdo, $rsPatient, 7, 'V' . $run . 'RS2');
        $mixedLines = [
            catalog_line($pdo, 3243, 7, 'lab'),
            ['desc' => 'Non-covered procedure (probe)', 'cat' => 'procedure', 'qty' => 1, 'unit' => 10000.0, 'ins' => 0.0, 'apply' => 0],
        ];
        $mixed = seed_invoice($pdo, $rsVisit, $rsPatient, 7, $mixedLines, 'Mixed coverage probe');
        $mixedExpected = expected_patient_share($mixedLines, 15.0);
        $mixedTotal = 16278.0;
        $mixedFlat = fin_split($mixedTotal, 15.0)['patient'];
        $mixedRow = invoice_row($pdo, $mixed['id']);
        check(eq((float)$mixedRow['balance'], $mixedExpected) && eq((float)$mixedRow['balance'], $mixedFlat),
            'D2 mixed coverage: patient owes ' . money($mixedExpected) . ' (covered 15% + uncovered 100%); ' .
            'recalcInvoice=' . money($mixedRow['balance']) . ', payment/ledger path=' . money($mixedFlat), true);
        fin_post_invoice($pdo, $mixed['id']);
        updateInvoiceBalance($pdo, $mixed['id']);
        $mixedRow = invoice_row($pdo, $mixed['id']);
        check(eq((float)$mixedRow['balance'], $mixedExpected),
            'D2 after fin_post_invoice + updateInvoiceBalance, balance = ' . money($mixedRow['balance']) . ' (should be ' . money($mixedExpected) . ')', true);

        // D3 — pharmacy line written exactly as handlers/procedures/procedures.php does (apply_insurance = 0),
        //      inside an invoice that also has a covered line: the flat path is right, the item-aware path is wrong.
        $pharmLines = [
            catalog_line($pdo, 3243, 7, 'lab'),
            ['item_id' => 20, 'desc' => 'Medication — Paracetamol inject', 'cat' => 'pharmacy', 'qty' => 1, 'unit' => 1602.0, 'ins' => 0.0, 'apply' => 0],
        ];
        $pharm = seed_invoice($pdo, $rsVisit, $rsPatient, 7, $pharmLines, 'Pharmacy flag probe');
        $pharmExpected = round(6278 * 0.15) + round(1602 * 0.15); // lab + drug both covered at 15%
        $pharmTotal = 6278 + 1602;
        $pharmRow = invoice_row($pdo, $pharm['id']);
        check(eq((float)$pharmRow['balance'], $pharmExpected),
            'D3 real pharmacy line (apply_insurance=0) on a mixed invoice: recalcInvoice says patient owes ' .
            money($pharmRow['balance']) . ', insurance-based share is ' . money($pharmExpected), true);
        fin_post_invoice($pdo, $pharm['id']);
        $pharmRow = invoice_row($pdo, $pharm['id']);
        check(eq((float)$pharmRow['balance'], $pharmExpected),
            'D3 after fin_post_invoice balance = ' . money($pharmRow['balance']) . ' (ledger says ' . money($pharmExpected) . ')', true);

        // D4 — the screen the cashier actually uses (billing.php record_payment_full) never
        //      writes payment_allocations, so a payment is not attributed to invoice lines.
        $billingSrc = (string)file_get_contents(__DIR__ . '/../handlers/patients/billing.php');
        $bStart = strpos($billingSrc, "=== 'record_payment_full'");
        $bEnd = $bStart === false ? false : strpos($billingSrc, "=== 'record_payment'", $bStart);
        $bBlock = ($bStart !== false && $bEnd !== false) ? substr($billingSrc, $bStart, $bEnd - $bStart) : '';
        check($bBlock !== '' && strpos($bBlock, 'allocatePayment(') !== false,
            'D4 record_payment_full (the cashier screen) allocates the payment to invoice lines; it does not, so payment_allocations stays empty', true);

        // D5 — finance/payment-records.php keeps its own balance helper: total − paid instead of
        //      the patient share, so a copay payment on an insured invoice never settles it.
        $finSrc = (string)file_get_contents(__DIR__ . '/../handlers/finance/payment-records.php');
        $flatBalance = (bool)preg_match('/\$balance\s*=\s*max\(\s*0\s*,\s*\$total\s*-\s*\$totalPaid\s*\)/', $finSrc);
        check(!$flatBalance,
            'D5 finance/payment-records.php recomputes the balance as total − paid (ignoring the copay); reversing a 3,150 payment on a 21,002 insured invoice would re-open 21,002, not 3,150', true);

        $pdo->exec('ROLLBACK TO SAVEPOINT probes');
    }

    /* ------------------------------------------------------------ summary */

    echo "\n" . str_repeat('=', 78) . "\n";
    echo sprintf("%-3s %-15s %-11s %6s %10s %10s %10s %10s %10s\n",
        'code', 'patient_no', 'insurer', 'copay', 'total', 'pat.due', 'ins.due', 'pat.paid', 'ins.paid');
    foreach ($summary as $s) {
        echo sprintf("%-3s %-15s %-11s %5s%% %10s %10s %10s %10s %10s\n",
            $s['code'], $s['patient'], substr((string)$s['insurer'], 0, 11), money($s['copay']),
            money($s['total']), money($s['patient_due']), money($s['insurer_due']),
            money($s['patient_paid']), money($s['insurer_paid']));
    }

    $sumTotal = array_sum(array_column($summary, 'total'));
    $sumPat = array_sum(array_column($summary, 'patient_due'));
    $sumIns = array_sum(array_column($summary, 'insurer_due'));
    echo str_repeat('-', 78) . "\n";
    echo sprintf("%-3s %-15s %-11s %6s %10s %10s %10s %10s %10s\n", '', 'TOTAL', '',
        '', money($sumTotal), money($sumPat), money($sumIns), money($sumPat), money($sumIns));

    echo "\nChecks: {$GLOBALS['C']['pass']} passed, {$GLOBALS['C']['fail']} failed";
    if (!$noDiag) echo ", {$GLOBALS['C']['diag']} edge-probe divergences";
    echo "\n";

    foreach ($GLOBALS['C']['notes'] as $n) echo "note: $n\n";

    if ($keep) {
        $pdo->commit();
        echo "\nKEPT. Run tag: $run\n";
        echo "Filter patients with: patient_no LIKE 'T{$run}%' (or last_name LIKE '{$run}')\n";
        foreach ($summary as $s) echo "  {$s['code']}: patient #{$s['patient_id']} / invoice {$s['invoice']}\n";
    } else {
        $pdo->rollBack();
        echo "\nRolled back. Re-run with --keep to keep the rows for the UI.\n";
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "\nERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
}

exit($GLOBALS['C']['fail'] > 0 ? 1 : 0);

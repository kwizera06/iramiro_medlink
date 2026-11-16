<?php
// handlers/finance/finance-ops.php
// Finance operations that sit on top of the ledger: expenses, supplier payments, patient deposits, refunds,
// fiscal periods, daily cash close, insurer ageing, integrity check/repair and account mapping.
// Every posting goes through includes/finance_posting.php.

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/finance_posting.php';
require_once __DIR__ . '/../../includes/payment_helpers.php';
require_once __DIR__ . '/../../includes/controls.php';

function jsonResponse($d, $s = 200) { http_response_code($s); echo json_encode($d); exit(); }
function success($d = null, $m = 'Success', $s = 200) { jsonResponse(['success' => true, 'message' => $m, 'data' => $d], $s); }
function error($m, $s = 400) { jsonResponse(['success' => false, 'message' => $m], $s); }

// Period locks, account mapping, manual reversals and repairs: administrators and accountants only
function needFinanceRole(): void {
    $slugs = $_SESSION['user_data']['role_slugs'] ?? [];
    if (!array_intersect($slugs, ['admin', 'accountant'])) error('Only administrators and accountants can do this', 403);
}

// Contracts and supplier bills are recorded by procurement officers too (they moved to the Procurement pages); paying a supplier stays with Finance
function needProcurementRole(): void {
    $slugs = $_SESSION['user_data']['role_slugs'] ?? [];
    if (!array_intersect($slugs, ['admin', 'accountant', 'procurement_officer'])) error('Only administrators, accountants and procurement officers can do this', 403);
}

function amountOf($v, string $label = 'Amount'): float {
    $a = round((float)$v, 2);
    if ($a <= 0) error("$label must be greater than zero", 422);
    return $a;
}

function cashAccountOrFail(PDO $pdo, $id, string $label = 'Account'): int {
    $id = (int)$id;
    foreach (fin_cash_accounts($pdo) as $a) if ((int)$a['id'] === $id) return $id;
    error("$label must be a cash, bank or mobile-money account", 422);
}

function dateOf($v): string {
    $d = substr((string)($v ?: date('Y-m-d')), 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || !strtotime($d)) error('Invalid date', 422);
    return $d;
}

function accountBalance(PDO $pdo, int $accountId): float {
    $q = $pdo->prepare("SELECT COALESCE(SUM(debit - credit), 0) FROM journal_entry_lines WHERE account_id = ?");
    $q->execute([$accountId]);
    return round((float)$q->fetchColumn(), 2);
}

// Payroll deductions that are paid over to an authority (map key => label)
const STATUTORY = ['paye_payable' => 'PAYE / TPR (RRA)', 'pension_payable' => 'Pension (RSSB)', 'other_deductions_payable' => 'Medical / mutuelle / other deductions', 'wht_payable' => 'Withholding tax on supplier payments (RRA)'];

// ---- bank reconciliation ----
// Statement lines store money in as debit and money out as credit (the bank account seen from our books).
// A ledger line counts as cleared once it is linked to a line of a completed reconciliation (or of this one).
// Ledger lines dated before this account's first reconciled statement are treated as already cleared.
function brOpenLedger(PDO $pdo, array $rec): array {
    $q = $pdo->prepare("SELECT MIN(b.transaction_date) FROM bank_statement_lines b JOIN bank_reconciliations r ON r.id = b.reconciliation_id
                        WHERE r.account_id = ? AND (r.status = 'completed' OR r.id = ?)");
    $q->execute([$rec['account_id'], $rec['id']]);
    $from = $q->fetchColumn() ?: $rec['reconciliation_date'];
    $q = $pdo->prepare("SELECT jl.id, je.entry_date, je.reference, COALESCE(NULLIF(jl.line_description,''), je.description) AS description, jl.debit, jl.credit
                        FROM journal_entry_lines jl JOIN journal_entries je ON je.id = jl.journal_entry_id
                        WHERE jl.account_id = ? AND je.entry_date BETWEEN ? AND ?
                          AND NOT EXISTS (SELECT 1 FROM bank_statement_lines b JOIN bank_reconciliations r ON r.id = b.reconciliation_id
                                          WHERE b.journal_line_id = jl.id AND (r.status = 'completed' OR r.id = ?))
                        ORDER BY je.entry_date, jl.id");
    $q->execute([$rec['account_id'], $from, $rec['reconciliation_date'], $rec['id']]);
    return $q->fetchAll();
}

function brSummary(PDO $pdo, int $id): array {
    $q = $pdo->prepare("SELECT r.*, a.account_name FROM bank_reconciliations r LEFT JOIN chart_of_accounts a ON a.id = r.account_id WHERE r.id = ?");
    $q->execute([$id]);
    $rec = $q->fetch();
    if (!$rec) error('Reconciliation not found', 404);
    $l = $pdo->prepare("SELECT * FROM bank_statement_lines WHERE reconciliation_id = ? ORDER BY transaction_date, id");
    $l->execute([$id]);
    $lines = $l->fetchAll();
    $open = brOpenLedger($pdo, $rec);
    $b = $pdo->prepare("SELECT COALESCE(SUM(jl.debit - jl.credit), 0) FROM journal_entry_lines jl JOIN journal_entries je ON je.id = jl.journal_entry_id WHERE jl.account_id = ? AND je.entry_date <= ?");
    $b->execute([$rec['account_id'], $rec['reconciliation_date']]);
    $book = round((float)$b->fetchColumn(), 2);
    $sum = fn($rows, $col) => round(array_sum(array_map(fn($r) => (float)$r[$col], $rows)), 2);
    $bankOnly = array_values(array_filter($lines, fn($x) => !$x['journal_line_id']));
    $t = ['statement_balance' => round((float)$rec['bank_statement_balance'], 2), 'book_balance' => $book,
          'outstanding_payments' => $sum($open, 'credit'), 'deposits_in_transit' => $sum($open, 'debit'),
          'bank_only_in' => $sum($bankOnly, 'debit'), 'bank_only_out' => $sum($bankOnly, 'credit')];
    $t['adjusted_bank'] = round($t['statement_balance'] - $t['outstanding_payments'] + $t['deposits_in_transit'], 2);
    $t['adjusted_book'] = round($book + $t['bank_only_in'] - $t['bank_only_out'], 2);
    $t['difference'] = round($t['adjusted_bank'] - $t['adjusted_book'], 2);
    return ['rec' => $rec, 'lines' => $lines, 'open_ledger' => $open, 'totals' => $t];
}

function brDraft(PDO $pdo, int $lineId): array {
    $q = $pdo->prepare("SELECT b.*, r.status, r.account_id AS bank_id FROM bank_statement_lines b JOIN bank_reconciliations r ON r.id = b.reconciliation_id WHERE b.id = ?");
    $q->execute([$lineId]);
    $line = $q->fetch();
    if (!$line) error('Statement line not found', 404);
    if ($line['status'] !== 'draft') error('This reconciliation is completed', 409);
    return $line;
}

// Contracts with what has been billed against them (supplier invoices inside the contract dates) and the commitment left
function contractRows(PDO $pdo): array {
    $rows = $pdo->query("SELECT c.*, s.company_name,
                            COALESCE((SELECT SUM(si.amount) FROM supplier_invoices si WHERE si.supplier_id = c.supplier_id AND si.status <> 'voided'
                                      AND si.invoice_date >= c.start_date AND (c.end_date IS NULL OR si.invoice_date <= c.end_date)), 0) AS billed
                         FROM supplier_contracts c LEFT JOIN suppliers s ON s.id = c.supplier_id ORDER BY c.status = 'active' DESC, c.start_date DESC")->fetchAll();
    $today = date('Y-m-d');
    foreach ($rows as &$r) {
        $r['remaining'] = round(max(0, $r['value'] - $r['billed']), 2);
        $r['days_left'] = $r['end_date'] ? (int)floor((strtotime($r['end_date']) - strtotime($today)) / 86400) : null;
        $r['expiry_state'] = $r['status'] !== 'active' ? 'inactive'
            : ($r['end_date'] === null ? 'open'
            : ($r['end_date'] < $today ? 'expired'
            : ($r['days_left'] <= 30 ? 'expiring' : 'active')));
    }
    unset($r);
    return $rows;
}

// Allocate a supplier's live payments to its invoices oldest-first so installment
// payments are visible on each invoice (posted -> partially_paid -> paid).
function si_reallocate_invoices(PDO $pdo, int $supplierId): void {
    $paid = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM finance_supplier_payments WHERE supplier_id=" . (int)$supplierId . " AND status='paid'")->fetchColumn();
    $q = $pdo->prepare("SELECT id, amount FROM supplier_invoices WHERE supplier_id=? AND status<>'voided' ORDER BY invoice_date, id");
    $q->execute([$supplierId]);
    $upd = $pdo->prepare("UPDATE supplier_invoices SET paid_amount=?, status=? WHERE id=?");
    foreach ($q->fetchAll() as $inv) {
        $amt = (float)$inv['amount'];
        $alloc = min($amt, max(0, $paid));
        $paid -= $alloc;
        $st = $alloc <= 0.005 ? 'posted' : ($alloc + 0.005 < $amt ? 'partially_paid' : 'paid');
        $upd->execute([round($alloc, 2), $st, $inv['id']]);
    }
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$uid    = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

try {

// =====================================================================
// GET
// =====================================================================
if ($method === 'GET') {

    if ($action === 'departments') success($pdo->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name")->fetchAll());

    if ($action === 'cash_accounts') {
        $rows = fin_cash_accounts($pdo);
        foreach ($rows as &$r) $r['balance'] = accountBalance($pdo, (int)$r['id']);
        success($rows);
    }

    if ($action === 'expense_accounts') {
        $type = ($_GET['type'] ?? '') === 'income' ? 'income' : 'expense';
        success($pdo->query("SELECT id, account_code, account_name FROM chart_of_accounts WHERE account_type = '$type' AND status = 'active' AND has_sub = 0 ORDER BY account_code")->fetchAll());
    }

    if ($action === 'expenses') {
        $from = dateOf($_GET['from'] ?? date('Y-m-01')); $to = dateOf($_GET['to'] ?? date('Y-m-d'));
        $q = $pdo->prepare("SELECT e.*, c.account_name AS expense_account, p.account_name AS pay_account, s.company_name AS supplier
                            FROM finance_expenses e
                            LEFT JOIN chart_of_accounts c ON c.id = e.expense_account_id
                            LEFT JOIN chart_of_accounts p ON p.id = e.pay_account_id
                            LEFT JOIN suppliers s ON s.id = e.supplier_id
                            WHERE e.expense_date BETWEEN ? AND ? ORDER BY e.expense_date DESC, e.id DESC LIMIT 500");
        $q->execute([$from, $to]);
        $rows = $q->fetchAll();
        $total = 0; foreach ($rows as $r) if ($r['status'] !== 'voided') $total += (float)$r['amount'];
        success(['expenses' => $rows, 'total' => round($total, 2)]);
    }

    if ($action === 'suppliers_payable') {
        $rows = $pdo->query("SELECT s.id, s.company_name, s.status, a.id AS account_id FROM suppliers s LEFT JOIN chart_of_accounts a ON a.linked_supplier_id = s.id ORDER BY s.company_name")->fetchAll();
        foreach ($rows as &$r) $r['payable'] = $r['account_id'] ? -accountBalance($pdo, (int)$r['account_id']) : 0.0; // credit balance = we owe
        unset($r);
        $rows = array_values(array_filter($rows, fn($r) => $r['status'] === 'active' || abs($r['payable']) > 0.005)); // a suspended / blacklisted supplier we still owe stays payable
        $pay = $pdo->query("SELECT sp.*, s.company_name, a.account_name AS pay_account FROM finance_supplier_payments sp
                            LEFT JOIN suppliers s ON s.id = sp.supplier_id LEFT JOIN chart_of_accounts a ON a.id = sp.pay_account_id
                            ORDER BY sp.payment_date DESC, sp.id DESC LIMIT 100")->fetchAll();
        success(['suppliers' => $rows, 'payments' => $pay]);
    }

    if ($action === 'uninvoiced_grns') { // goods received from a supplier that no bill has been matched to yet
        $q = $pdo->prepare("SELECT g.id, g.grn_number, g.received_date, po.po_number FROM goods_receiving_notes g LEFT JOIN purchase_orders po ON po.id = g.po_id
                            WHERE g.supplier_id = ? AND g.supplier_invoice_id IS NULL ORDER BY g.received_date, g.id");
        $q->execute([(int)($_GET['supplier_id'] ?? 0)]);
        $rows = [];
        foreach ($q->fetchAll() as $g) { $g['value'] = fin_grn_open_value($pdo, (int)$g['id']); if ($g['value'] > 0) $rows[] = $g; }
        success($rows);
    }

    if ($action === 'supplier_invoices') {
        success($pdo->query("SELECT si.*, s.company_name, ROUND(si.amount - si.paid_amount, 2) AS balance,
                                    (SELECT GROUP_CONCAT(grn_number SEPARATOR ', ') FROM goods_receiving_notes WHERE supplier_invoice_id = si.id) AS grns
                             FROM supplier_invoices si LEFT JOIN suppliers s ON s.id = si.supplier_id ORDER BY si.invoice_date DESC, si.id DESC LIMIT 100")->fetchAll());
    }

    if ($action === 'statutory_payables') {
        $rows = [];
        foreach (STATUTORY as $key => $label) { $acc = fin_acct($pdo, $key); $rows[] = ['key' => $key, 'label' => $label, 'account_id' => $acc, 'owed' => -accountBalance($pdo, $acc)]; }
        $hist = $pdo->query("SELECT r.*, a.account_name AS pay_account FROM finance_remittances r LEFT JOIN chart_of_accounts a ON a.id = r.pay_account_id ORDER BY r.remit_date DESC, r.id DESC LIMIT 50")->fetchAll();
        foreach ($hist as &$h) $h['label'] = STATUTORY[$h['liability_key']] ?? $h['liability_key'];
        success(['payables' => $rows, 'remittances' => $hist]);
    }

    if ($action === 'bank_recs') {
        success($pdo->query("SELECT r.id, r.account_id, a.account_name, r.reconciliation_date, r.bank_statement_balance, r.cashbook_balance, r.status,
                                    (SELECT COUNT(*) FROM bank_statement_lines b WHERE b.reconciliation_id = r.id) AS line_count
                             FROM bank_reconciliations r LEFT JOIN chart_of_accounts a ON a.id = r.account_id ORDER BY r.reconciliation_date DESC, r.id DESC LIMIT 50")->fetchAll());
    }

    if ($action === 'bank_rec') success(brSummary($pdo, (int)($_GET['id'] ?? 0)));

    if ($action === 'contracts') success(contractRows($pdo));

    // ---- notes to the financial statements, built from the books and the registers ----
    if ($action === 'fs_notes') {
        $asOf = dateOf($_GET['as_of'] ?? null);
        $yearStart = substr($asOf, 0, 4) . '-01-01';
        $bal = function (int $acc) use ($pdo, $asOf) {
            $q = $pdo->prepare("SELECT COALESCE(SUM(l.debit - l.credit), 0) FROM journal_entry_lines l JOIN journal_entries e ON e.id = l.journal_entry_id WHERE l.account_id = ? AND e.entry_date <= ?");
            $q->execute([$acc, $asOf]); return round((float)$q->fetchColumn(), 2);
        };
        $flow = function (int $acc) use ($pdo, $asOf, $yearStart) {
            $q = $pdo->prepare("SELECT COALESCE(SUM(l.debit - l.credit), 0) FROM journal_entry_lines l JOIN journal_entries e ON e.id = l.journal_entry_id WHERE l.account_id = ? AND e.entry_date BETWEEN ? AND ?");
            $q->execute([$acc, $yearStart, $asOf]); return round((float)$q->fetchColumn(), 2);
        };
        $policies = $pdo->query("SELECT name, depreciation_method, depreciation_rate, useful_life_years FROM asset_categories WHERE status = 'active' ORDER BY name")->fetchAll();
        $stores = [];
        foreach ($pdo->query("SELECT id, account_name FROM chart_of_accounts WHERE parent_id = 9 AND status = 'active' ORDER BY account_code") as $s) $stores[] = ['store' => $s['account_name'], 'value' => $bal((int)$s['id'])];
        $q = $pdo->prepare("SELECT c.name AS category, COUNT(a.id) AS count, COALESCE(SUM(a.purchase_cost), 0) AS cost, COALESCE(SUM(a.accumulated_depreciation), 0) AS acc_dep
                            FROM assets a LEFT JOIN asset_categories c ON c.id = a.category_id WHERE a.status <> 'disposed' AND a.purchase_date <= ? GROUP BY c.name ORDER BY c.name");
        $q->execute([$asOf]);
        $ppe = $q->fetchAll();
        $q = $pdo->prepare("SELECT COUNT(*) FROM assets WHERE disposal_date BETWEEN ? AND ?"); $q->execute([$yearStart, $asOf]);
        $insurers = []; $patients = 0.0;
        foreach ($pdo->query("SELECT c.id, c.account_name, c.linked_insurance_id FROM chart_of_accounts c WHERE c.parent_id = " . fin_acct($pdo, 'ar_general')) as $r) {
            $b = $bal((int)$r['id']);
            if ($r['linked_insurance_id']) { if (abs($b) >= 0.01) $insurers[] = ['insurer' => $r['account_name'], 'balance' => $b]; } else $patients += $b;
        }
        $suppliers = 0.0;
        foreach ($pdo->query("SELECT id FROM chart_of_accounts WHERE linked_supplier_id IS NOT NULL") as $r) $suppliers -= $bal((int)$r['id']);
        $payroll = [];
        foreach (array_merge(['salary_payable' => 'Net salaries'], STATUTORY) as $k => $label) $payroll[] = ['item' => $label, 'owed' => -$bal(fin_acct($pdo, $k))];
        $banks = [];
        foreach (fin_cash_accounts($pdo) as $c) {
            $q2 = $pdo->prepare("SELECT MAX(reconciliation_date) FROM bank_reconciliations WHERE account_id = ? AND status = 'completed' AND reconciliation_date <= ?"); $q2->execute([$c['id'], $asOf]);
            $banks[] = ['account' => $c['account_name'], 'balance' => $bal((int)$c['id']), 'last_reconciled' => $q2->fetchColumn() ?: null];
        }
        success([
            'as_of' => $asOf,
            'policies' => $policies,
            'inventory' => ['stores' => $stores, 'expired_written_off' => $flow(fin_acct($pdo, 'stock_loss')), 'count_adjustments' => $flow(fin_acct($pdo, 'stock_adjust'))],
            'ppe' => ['categories' => $ppe, 'disposals_this_year' => (int)$q->fetchColumn(), 'depreciation_this_year' => $flow(fin_acct($pdo, 'dep_exp'))],
            'receivables' => ['insurers' => $insurers, 'patients' => round($patients, 2)],
            'payables' => ['suppliers' => round($suppliers, 2), 'goods_not_invoiced' => -$bal(fin_acct($pdo, 'grni')), 'payroll' => $payroll],
            'commitments' => array_values(array_filter(contractRows($pdo), fn($c) => $c['status'] === 'active' && $c['remaining'] > 0)),
            'banks' => $banks,
        ]);
    }

    if ($action === 'entry_source') { // where a journal entry came from, for the journal screen
        $id = (int)($_GET['id'] ?? 0);
        $q = $pdo->prepare("SELECT id, reference_type, reference_id FROM journal_entries WHERE id = ?"); $q->execute([$id]);
        $e = $q->fetch(); if (!$e) error('Entry not found', 404);
        $t = $e['reference_type']; $rid = $e['reference_id']; $label = null; $link = null;
        if (in_array($t, ['invoice', 'invoice_adj'], true)) {
            $r = $pdo->prepare("SELECT i.invoice_no, p.patient_no FROM invoices i LEFT JOIN patients p ON p.id = i.patient_id WHERE i.id = ?"); $r->execute([$rid]); $row = $r->fetch();
            if ($row) { $label = 'Invoice ' . $row['invoice_no']; $link = 'invoicing.php?module=finance&sub=invoices&q=' . urlencode($row['patient_no']); }
        } elseif (in_array($t, ['payment_record', 'payment_reversal'], true)) {
            $r = $pdo->prepare("SELECT pr.receipt_number, i.invoice_no, p.patient_no FROM payment_records pr LEFT JOIN invoices i ON i.id = pr.invoice_id LEFT JOIN patients p ON p.id = pr.patient_id WHERE pr.id = ?"); $r->execute([$rid]); $row = $r->fetch();
            if ($row) { $label = 'Payment ' . $row['receipt_number'] . ' on ' . $row['invoice_no']; $link = 'invoicing.php?module=finance&sub=invoices&q=' . urlencode($row['patient_no']); }
        } else {
            $map = ['expense' => ['Expense', 'finance.php?module=finance&sub=expenses'], 'supplier_payment' => ['Supplier payment', 'finance.php?module=finance&sub=expenses'],
                    'payroll' => ['Payroll', 'hr.php?module=hr&sub=payroll'], 'payroll_payment' => ['Salary payment', 'hr.php?module=hr&sub=payroll'],
                    'pos_sale' => ['Pharmacy sale', 'pharmacy.php?module=pharmacy&sub=dispensing'], 'dispense_cogs' => ['Pharmacy dispensing', 'pharmacy.php?module=pharmacy&sub=dispensing'],
                    'requisition_issue' => ['Requisition issue', 'requisitions.php?sub=approved'], 'stock_adjust' => ['Stock adjustment', 'inventory.php?module=inventory&sub=stock-report'],
                    'purchase_order' => ['Goods received', 'procurement.php?module=procurement&sub=purchase-orders'], 'insurance_settlement' => ['Insurance settlement', 'insurance.php?module=insurance&sub=receivables'],
                    'asset_open' => ['Asset opening', 'assets.php?module=assets&sub=register'], 'asset_capitalize' => ['Asset purchase / capitalization', 'assets.php?module=assets&sub=register'],
                    'asset_depreciation' => ['Depreciation', 'assets.php?module=assets&sub=depreciation'], 'asset_disposal' => ['Asset disposal', 'assets.php?module=assets&sub=disposal'],
                    'asset_revaluation' => ['Asset revaluation', 'assets.php?module=assets&sub=revaluation']];
            if (isset($map[(string)$t])) [$label, $link] = $map[(string)$t];
        }
        $reversedBy = $pdo->prepare("SELECT id FROM journal_entries WHERE reference_type = 'reversal' AND reference_id = ?"); $reversedBy->execute([(string)$id]);
        success(['type' => $t, 'label' => $label ?: ($t ? ucfirst(str_replace('_', ' ', $t)) : 'Manual entry'), 'link' => $link,
                 'reversal_id' => ((int)$reversedBy->fetchColumn()) ?: null, 'reversal_of' => $t === 'reversal' ? (int)$rid : null]);
    }

    if ($action === 'entry_sources') { // journal entries behind one invoice / payment (for "view journal" links)
        $type = $_GET['type'] ?? 'invoice'; $rid = (string)($_GET['id'] ?? '');
        $types = $type === 'invoice' ? ['invoice', 'invoice_adj'] : [$type];
        $rows = [];
        foreach (fin_entries($pdo, $types, $rid) as $e) {
            $l = $pdo->prepare("SELECT jl.*, c.account_code, c.account_name FROM journal_entry_lines jl JOIN chart_of_accounts c ON c.id = jl.account_id WHERE jl.journal_entry_id = ? ORDER BY jl.id");
            $l->execute([$e['id']]);
            $e['lines'] = $l->fetchAll();
            $rows[] = $e;
        }
        success($rows);
    }

    error('Unknown action', 404);
}

// =====================================================================
// POST
// =====================================================================
if ($method === 'POST') {

    if ($action === 'expense') {
        needFinanceRole();
        $date = dateOf($input['expense_date'] ?? null);
        $amount = amountOf($input['amount'] ?? 0);
        $accId = (int)($input['expense_account_id'] ?? 0);
        $acc = $pdo->prepare("SELECT account_type FROM chart_of_accounts WHERE id = ? AND status = 'active' AND has_sub = 0"); $acc->execute([$accId]);
        if ($acc->fetchColumn() !== 'expense') error('Choose an expense account', 422);
        $payAcc = !empty($input['pay_account_id']) ? cashAccountOrFail($pdo, $input['pay_account_id'], 'Paid from') : null;
        $supplierId = !empty($input['supplier_id']) ? (int)$input['supplier_id'] : null;
        $payee = trim($input['payee'] ?? ''); $desc = trim($input['description'] ?? '');
        if (!$payAcc && !$supplierId && $payee === '') error('Say who is owed (supplier or payee) when the expense is not paid yet', 422);

        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO finance_expenses (expense_date, expense_account_id, amount, pay_account_id, supplier_id, payee, reference, description, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([$date, $accId, $amount, $payAcc, $supplierId, $payee ?: null, trim($input['reference'] ?? '') ?: null, $desc ?: null, $payAcc ? 'paid' : 'payable', $uid]);
        $eid = (int)$pdo->lastInsertId();
        fin_post($pdo, ['date' => $date, 'type' => 'expense', 'ref_id' => $eid, 'dept' => (int)($input['department_id'] ?? 0) ?: null, 'description' => 'Expense' . ($desc ? ': ' . $desc : '') . ($payee ? ' - ' . $payee : ''),
            'lines' => [
                ['account_id' => $accId, 'debit' => $amount, 'desc' => $desc ?: 'Expense'],
                ['account_id' => $payAcc ?: fin_supplier_acct($pdo, (int)$supplierId), 'credit' => $amount, 'desc' => $payAcc ? 'Paid' : 'Payable' . ($payee ? " - $payee" : '')],
            ]]);
        $pdo->commit();
        fin_log($pdo, 'CREATE', 'finance', "Expense #$eid of " . number_format($amount) . ($desc ? " - $desc" : ''), $eid);
        success(['id' => $eid], 'Expense recorded', 201);
    }

    if ($action === 'expense_void') {
        needFinanceRole();
        $eid = (int)($input['id'] ?? 0);
        $q = $pdo->prepare("SELECT status FROM finance_expenses WHERE id = ?"); $q->execute([$eid]);
        $st = $q->fetchColumn();
        if (!$st) error('Expense not found', 404);
        if ($st === 'voided') error('Already voided', 409);
        $pdo->beginTransaction();
        fin_unpost($pdo, 'expense', $eid, 'Expense voided');
        $pdo->prepare("UPDATE finance_expenses SET status = 'voided' WHERE id = ?")->execute([$eid]);
        $pdo->commit();
        fin_log($pdo, 'VOID', 'finance', "Expense #$eid voided", $eid);
        success(null, 'Expense voided');
    }

    if ($action === 'supplier_payment') {
        needFinanceRole();
        $supplierId = (int)($input['supplier_id'] ?? 0);
        $s = $pdo->prepare("SELECT company_name FROM suppliers WHERE id = ?"); $s->execute([$supplierId]);
        $name = $s->fetchColumn(); if (!$name) error('Supplier not found', 404);
        $date = dateOf($input['payment_date'] ?? null);
        $amount = amountOf($input['amount'] ?? 0);
        $payAcc = cashAccountOrFail($pdo, $input['pay_account_id'] ?? 0, 'Paid from');
        fin_supplier_acct($pdo, $supplierId);   // a supplier without a payable account gets one now, outside the transaction, so a refused payment does not undo it
        $pdo->beginTransaction();
        // PROC-11: lock the cash account and reject an overdraft before creating
        // either the payment row or its journal. There is no implicit overdraft
        // policy; an authorised overdraft must be represented by a future
        // account-level approval rather than silently making the ledger negative.
        $lockCash = $pdo->prepare("SELECT id, account_name FROM chart_of_accounts WHERE id = ? AND parent_id = 3 AND status = 'active' FOR UPDATE");
        $lockCash->execute([$payAcc]);
        $cashRow = $lockCash->fetch(PDO::FETCH_ASSOC);
        if (!$cashRow) { $pdo->rollBack(); error('Payment account is no longer active', 409); }
        $wht = round(max(0, (float)($input['wht_amount'] ?? 0)), 2); // tax kept back for RRA: the supplier is settled in full, the bank pays the rest
        if ($wht >= $amount) { $pdo->rollBack(); error('Tax withheld must be less than the payment', 422); }
        $net = round($amount - $wht, 2);
        $available = accountBalance($pdo, $payAcc);
        if ($net > $available + 0.005) {
            $pdo->rollBack();
            error('Insufficient available balance in ' . $cashRow['account_name'] . ': available ' . number_format($available, 2) . ' RWF, requested ' . number_format($net, 2) . ' RWF', 422);
        }
        // never pay more than the supplier's account says we owe (a payment with no bill behind it would leave the supplier owing us)
        $owed = -accountBalance($pdo, fin_supplier_acct($pdo, $supplierId));
        $advance = !empty($input['advance']);   // an advance payment (before the bill) is a deliberate choice, never an accident
        if (!$advance && $amount > $owed + 0.005) {
            $pdo->rollBack();
            error('This payment (' . number_format($amount, 2) . ' RWF) is more than we owe ' . $name . ' (' . number_format(max(0, $owed), 2) . ' RWF). Enter the supplier bill first, or tick Advance payment.', 422);
        }
        $pdo->prepare("INSERT INTO finance_supplier_payments (supplier_id, payment_date, amount, wht_amount, pay_account_id, reference, notes, created_by) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$supplierId, $date, $amount, $wht, $payAcc, trim($input['reference'] ?? '') ?: null, trim($input['notes'] ?? '') ?: null, $uid]);
        $pid = (int)$pdo->lastInsertId();
        fin_post($pdo, ['date' => $date, 'type' => 'supplier_payment', 'ref_id' => $pid, 'description' => 'Payment to supplier ' . $name,
            'lines' => [
                ['account_id' => fin_supplier_acct($pdo, $supplierId), 'debit' => $amount, 'desc' => 'Payment to ' . $name],
                ['account_id' => $payAcc, 'credit' => $net, 'desc' => 'Paid ' . $name . (!empty($input['reference']) ? ' ref ' . $input['reference'] : '')],
                ['account_id' => fin_acct($pdo, 'wht_payable'), 'credit' => $wht, 'desc' => 'Tax withheld from ' . $name],
            ]]);
        si_reallocate_invoices($pdo, $supplierId);
        $pdo->commit();
        fin_log($pdo, 'CREATE', 'finance', "Supplier payment #$pid of " . number_format($amount) . " to $name", $pid);
        success(['id' => $pid], 'Supplier payment recorded', 201);
    }

    if ($action === 'supplier_payment_void') {
        needFinanceRole();
        $pid = (int)($input['id'] ?? 0);
        $q = $pdo->prepare("SELECT status FROM finance_supplier_payments WHERE id = ?"); $q->execute([$pid]);
        $st = $q->fetchColumn(); if (!$st) error('Payment not found', 404); if ($st === 'voided') error('Already voided', 409);
        $pdo->beginTransaction();
        fin_unpost($pdo, 'supplier_payment', $pid, 'Supplier payment voided');
        $q = $pdo->prepare("SELECT supplier_id FROM finance_supplier_payments WHERE id = ?"); $q->execute([$pid]);
        $supplierId = (int)$q->fetchColumn();
        $pdo->prepare("UPDATE finance_supplier_payments SET status = 'voided' WHERE id = ?")->execute([$pid]);
        si_reallocate_invoices($pdo, $supplierId);
        $pdo->commit();
        success(null, 'Supplier payment voided');
    }

    // Supplier bill: must match the goods received (3-way match) and cannot be entered twice
    if ($action === 'supplier_invoice') {
        needProcurementRole();
        $supplierId = (int)($input['supplier_id'] ?? 0);
        $s = $pdo->prepare("SELECT company_name FROM suppliers WHERE id = ?"); $s->execute([$supplierId]);
        $name = $s->fetchColumn(); if (!$name) error('Supplier not found', 404);
        $number = trim($input['invoice_number'] ?? ''); if ($number === '') error('Supplier invoice number required', 422);
        $date = dateOf($input['invoice_date'] ?? null);
        $due = !empty($input['due_date']) ? dateOf($input['due_date']) : null;
        $amount = amountOf($input['amount'] ?? 0);
        $grnIds = array_values(array_unique(array_map('intval', (array)($input['grn_ids'] ?? []))));
        if (!$grnIds) error('Select the goods received notes this bill covers', 422);
        $dup = $pdo->prepare("SELECT id FROM supplier_invoices WHERE supplier_id = ? AND invoice_number = ? AND status <> 'voided'");
        $dup->execute([$supplierId, $number]);
        if ($dup->fetchColumn()) error("Invoice $number from $name is already recorded", 409);

        $pdo->beginTransaction();
        $in = implode(',', array_fill(0, count($grnIds), '?'));
        $g = $pdo->prepare("SELECT id, grn_number FROM goods_receiving_notes WHERE id IN ($in) AND supplier_id = ? AND supplier_invoice_id IS NULL FOR UPDATE");
        $g->execute(array_merge($grnIds, [$supplierId]));
        $grns = $g->fetchAll();
        if (count($grns) !== count($grnIds)) { $pdo->rollBack(); error('A selected GRN is from another supplier or is already billed', 409); }
        $rcv = $pdo->prepare("SELECT created_by FROM goods_receiving_notes WHERE id IN ($in)"); $rcv->execute($grnIds);
        foreach ($rcv->fetchAll(PDO::FETCH_COLUMN) as $by) if ($m = ctl_conflict($pdo, 'enter the bill for', ['received the goods for' => $by])) { $pdo->rollBack(); error($m, 403); }
        $received = round(array_sum(array_map(fn($r) => fin_grn_open_value($pdo, (int)$r['id']), $grns)), 2);
        if (abs($amount - $received) > 1) { $pdo->rollBack(); error('Invoice does not match goods received: invoice ' . number_format($amount, 2) . ', received ' . number_format($received, 2) . ', difference ' . number_format($amount - $received, 2), 422); }

        $pdo->prepare("INSERT INTO supplier_invoices (supplier_id, invoice_number, invoice_date, due_date, amount, notes, created_by) VALUES (?,?,?,?,?,?,?)")
            ->execute([$supplierId, $number, $date, $due, $amount, trim($input['notes'] ?? '') ?: null, $uid]);
        $sid = (int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE goods_receiving_notes SET supplier_invoice_id = ? WHERE id IN ($in)")->execute(array_merge([$sid], $grnIds));
        fin_post($pdo, ['date' => $date, 'type' => 'supplier_invoice', 'ref_id' => $sid, 'description' => "Supplier invoice $number - $name",
            'lines' => [
                ['account_id' => fin_acct($pdo, 'grni'), 'debit' => $received, 'desc' => 'Billed: ' . implode(', ', array_column($grns, 'grn_number'))],
                ['account_id' => fin_acct($pdo, 'stock_adjust'), 'debit' => max(0, $amount - $received), 'credit' => max(0, $received - $amount), 'desc' => 'Rounding'],
                ['account_id' => fin_supplier_acct($pdo, $supplierId), 'credit' => $amount, 'desc' => "Invoice $number"],
            ]]);
        $pdo->commit();
        fin_log($pdo, 'CREATE', 'finance', "Supplier invoice $number from $name: " . number_format($amount), $sid);
        success(['id' => $sid], 'Invoice matched and posted', 201);
    }

    if ($action === 'supplier_invoice_void') {
        needProcurementRole();
        $sid = (int)($input['id'] ?? 0);
        $q = $pdo->prepare("SELECT status FROM supplier_invoices WHERE id = ?"); $q->execute([$sid]);
        $st = $q->fetchColumn(); if (!$st) error('Invoice not found', 404); if ($st === 'voided') error('Already voided', 409);
        $r = $pdo->prepare("SELECT COUNT(*) FROM supplier_returns r JOIN goods_receiving_notes g ON g.id = r.grn_id WHERE g.supplier_invoice_id = ? AND r.against = 'supplier'");
        $r->execute([$sid]);
        if ($r->fetchColumn()) error('Goods were already returned against this invoice, so it cannot be voided', 409);
        $pdo->beginTransaction();
        fin_unpost($pdo, 'supplier_invoice', $sid, 'Supplier invoice voided');
        $q = $pdo->prepare("SELECT supplier_id FROM supplier_invoices WHERE id = ?"); $q->execute([$sid]);
        $supplierId = (int)$q->fetchColumn();
        $pdo->prepare("UPDATE supplier_invoices SET status = 'voided', paid_amount = 0 WHERE id = ?")->execute([$sid]);
        $pdo->prepare("UPDATE goods_receiving_notes SET supplier_invoice_id = NULL WHERE supplier_invoice_id = ?")->execute([$sid]);
        si_reallocate_invoices($pdo, $supplierId);
        $pdo->commit();
        success(null, 'Supplier invoice voided; its GRNs can be billed again');
    }

    // Pay a payroll deduction over to the authority: Dr the payable, Cr cash / bank
    if ($action === 'remittance') {
        needFinanceRole();
        $key = (string)($input['liability_key'] ?? '');
        if (!isset(STATUTORY[$key])) error('Choose what is being paid (PAYE, pension or other deductions)', 422);
        $date = dateOf($input['remit_date'] ?? null);
        $amount = amountOf($input['amount'] ?? 0);
        $payAcc = cashAccountOrFail($pdo, $input['pay_account_id'] ?? 0, 'Paid from');
        $liab = fin_acct($pdo, $key);
        $owed = -accountBalance($pdo, $liab);
        if ($amount > $owed + 0.01) error('Amount is more than is owed (' . number_format($owed, 2) . ')', 422);
        $period = preg_match('/^\d{4}-\d{2}$/', (string)($input['period'] ?? '')) ? $input['period'] : null;
        $ref = trim($input['reference'] ?? '') ?: null;
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO finance_remittances (liability_key, remit_date, amount, pay_account_id, period, reference, created_by) VALUES (?,?,?,?,?,?,?)")
            ->execute([$key, $date, $amount, $payAcc, $period, $ref, $uid]);
        $rid = (int)$pdo->lastInsertId();
        $label = STATUTORY[$key] . ($period ? " $period" : '');
        fin_post($pdo, ['date' => $date, 'type' => 'remittance', 'ref_id' => $rid, 'description' => "Remittance - $label",
            'lines' => [
                ['account_id' => $liab, 'debit' => $amount, 'desc' => "Paid over: $label"],
                ['account_id' => $payAcc, 'credit' => $amount, 'desc' => "Remittance $label" . ($ref ? " ref $ref" : '')],
            ]]);
        $pdo->commit();
        fin_log($pdo, 'CREATE', 'finance', "Remittance #$rid $label: " . number_format($amount), $rid);
        success(['id' => $rid], 'Remittance recorded', 201);
    }

    if ($action === 'remittance_void') {
        needFinanceRole();
        $rid = (int)($input['id'] ?? 0);
        $q = $pdo->prepare("SELECT status FROM finance_remittances WHERE id = ?"); $q->execute([$rid]);
        $st = $q->fetchColumn(); if (!$st) error('Remittance not found', 404); if ($st === 'voided') error('Already voided', 409);
        $pdo->beginTransaction();
        fin_unpost($pdo, 'remittance', $rid, 'Remittance voided');
        $pdo->prepare("UPDATE finance_remittances SET status = 'voided' WHERE id = ?")->execute([$rid]);
        $pdo->commit();
        success(null, 'Remittance voided');
    }

    // ---- bank reconciliation ----
    // Save a bank statement and match each line to an uncleared ledger line (same amount; same reference first, then nearest date within 7 days)
    if ($action === 'bank_rec_save') {
        $acc = cashAccountOrFail($pdo, $input['account_id'] ?? 0, 'Bank account');
        $date = dateOf($input['statement_date'] ?? null);
        $lines = (array)($input['lines'] ?? []);
        if (!$lines) error('The statement has no lines', 422);
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO bank_reconciliations (account_id, reconciliation_date, bank_statement_balance, status, created_by) VALUES (?,?,?, 'draft', ?)")
            ->execute([$acc, $date, round((float)($input['statement_balance'] ?? 0), 2), $uid]);
        $id = (int)$pdo->lastInsertId();
        $rec = ['id' => $id, 'account_id' => $acc, 'reconciliation_date' => $date];
        $ins = $pdo->prepare("INSERT INTO bank_statement_lines (reconciliation_id, account_id, transaction_date, description, reference, debit, credit, balance) VALUES (?,?,?,?,?,?,?,?)");
        foreach ($lines as $ln) {
            $in = round((float)($ln['money_in'] ?? 0), 2); $out = round((float)($ln['money_out'] ?? 0), 2);
            if ($in == 0 && $out == 0) continue;
            $ins->execute([$id, $acc, dateOf($ln['date'] ?? $date), mb_substr(trim($ln['description'] ?? ''), 0, 255), mb_substr(trim($ln['reference'] ?? ''), 0, 100) ?: null, $in, $out, round((float)($ln['balance'] ?? 0), 2)]);
        }
        $open = brOpenLedger($pdo, $rec);
        // ledger lines already held by another statement line (including drafts) are not offered twice
        $held = array_flip($pdo->query("SELECT journal_line_id FROM bank_statement_lines WHERE journal_line_id IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN));
        $st = $pdo->prepare("SELECT * FROM bank_statement_lines WHERE reconciliation_id = ? ORDER BY transaction_date, id");
        $st->execute([$id]);
        $set = $pdo->prepare("UPDATE bank_statement_lines SET journal_line_id = ?, matched = 1 WHERE id = ?");
        $matched = 0;
        foreach ($st->fetchAll() as $b) {
            $best = null; $score = null;
            foreach ($open as $o) {
                if (isset($held[$o['id']]) || abs((float)$o['debit'] - (float)$b['debit']) > 0.009 || abs((float)$o['credit'] - (float)$b['credit']) > 0.009) continue;
                $days = abs((strtotime($o['entry_date']) - strtotime($b['transaction_date'])) / 86400);
                $sameRef = $b['reference'] && trim((string)$o['reference']) === trim($b['reference']);
                if (!$sameRef && $days > 7) continue;
                $sc = ($sameRef ? 0 : 1000) + $days;
                if ($score === null || $sc < $score) { $best = $o; $score = $sc; }
            }
            if ($best) { $set->execute([$best['id'], $b['id']]); $held[$best['id']] = true; $matched++; }
        }
        $pdo->commit();
        $sum = brSummary($pdo, $id);
        success($sum, "Statement saved: $matched of " . count($sum['lines']) . ' lines matched', 201);
    }

    // Link (or unlink with journal_line_id = null) a statement line and a ledger line by hand
    if ($action === 'bank_rec_match') {
        $line = brDraft($pdo, (int)($input['line_id'] ?? 0));
        $jl = !empty($input['journal_line_id']) ? (int)$input['journal_line_id'] : null;
        if ($jl) {
            $q = $pdo->prepare("SELECT account_id, debit, credit FROM journal_entry_lines WHERE id = ?"); $q->execute([$jl]);
            $j = $q->fetch();
            if (!$j || (int)$j['account_id'] !== (int)$line['bank_id']) error('That ledger line is not on this bank account', 422);
            if (abs($j['debit'] - $line['debit']) > 0.009 || abs($j['credit'] - $line['credit']) > 0.009) error('Amounts differ', 422);
            $u = $pdo->prepare("SELECT COUNT(*) FROM bank_statement_lines WHERE journal_line_id = ? AND id <> ?"); $u->execute([$jl, $line['id']]);
            if ($u->fetchColumn()) error('That ledger line is already matched to another statement line', 409);
        }
        $pdo->prepare("UPDATE bank_statement_lines SET journal_line_id = ?, matched = ? WHERE id = ?")->execute([$jl, $jl ? 1 : 0, $line['id']]);
        success(brSummary($pdo, (int)$line['reconciliation_id']), $jl ? 'Matched' : 'Unmatched');
    }

    // A statement line missing from the books (bank charges, interest): post it, then it is matched
    if ($action === 'bank_rec_post_line') {
        $line = brDraft($pdo, (int)($input['line_id'] ?? 0));
        if ($line['journal_line_id']) error('Line is already matched', 409);
        $other = (int)($input['account_id'] ?? 0);
        $c = $pdo->prepare("SELECT account_type FROM chart_of_accounts WHERE id = ? AND status = 'active' AND has_sub = 0"); $c->execute([$other]);
        if (!$c->fetchColumn() || $other === (int)$line['bank_id']) error('Choose the account to post against (e.g. bank charges)', 422);
        $in = (float)$line['debit'] > 0; $amt = $in ? (float)$line['debit'] : (float)$line['credit'];
        $desc = 'Bank statement: ' . ($line['description'] ?: ($line['reference'] ?: 'item'));
        $pdo->beginTransaction();
        $je = fin_post($pdo, ['date' => $line['transaction_date'], 'type' => 'bank_rec_line', 'ref_id' => $line['id'], 'description' => $desc, 'replace' => true,
            'lines' => [
                ['account_id' => $in ? (int)$line['bank_id'] : $other, 'debit' => $amt, 'desc' => $desc],
                ['account_id' => $in ? $other : (int)$line['bank_id'], 'credit' => $amt, 'desc' => $desc],
            ]]);
        $q = $pdo->prepare("SELECT id FROM journal_entry_lines WHERE journal_entry_id = ? AND account_id = ? LIMIT 1"); $q->execute([$je, $line['bank_id']]);
        $pdo->prepare("UPDATE bank_statement_lines SET journal_line_id = ?, matched = 1 WHERE id = ?")->execute([(int)$q->fetchColumn(), $line['id']]);
        $pdo->commit();
        success(brSummary($pdo, (int)$line['reconciliation_id']), 'Posted and matched');
    }

    if ($action === 'bank_rec_complete') {
        needFinanceRole();
        $sum = brSummary($pdo, (int)($input['id'] ?? 0));
        if ($sum['rec']['status'] !== 'draft') error('Already completed', 409);
        $missing = count(array_filter($sum['lines'], fn($x) => !$x['journal_line_id']));
        if ($missing) error("Post or match the $missing statement line(s) missing from the books first", 422);
        if (abs($sum['totals']['difference']) >= 0.01) error('Not reconciled: difference ' . number_format($sum['totals']['difference'], 2), 422);
        $pdo->prepare("UPDATE bank_reconciliations SET status = 'completed', cashbook_balance = ? WHERE id = ?")->execute([$sum['totals']['book_balance'], $sum['rec']['id']]);
        fin_log($pdo, 'UPDATE', 'finance', "Bank reconciliation #{$sum['rec']['id']} completed ({$sum['rec']['account_name']} {$sum['rec']['reconciliation_date']})", $sum['rec']['id']);
        success(null, 'Reconciliation completed');
    }

    if ($action === 'bank_rec_delete') {
        $id = (int)($input['id'] ?? 0);
        $q = $pdo->prepare("SELECT status FROM bank_reconciliations WHERE id = ?"); $q->execute([$id]);
        $st = $q->fetchColumn(); if (!$st) error('Reconciliation not found', 404); if ($st !== 'draft') error('A completed reconciliation cannot be deleted', 409);
        $pdo->prepare("DELETE FROM bank_statement_lines WHERE reconciliation_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM bank_reconciliations WHERE id = ?")->execute([$id]);
        success(null, 'Draft deleted');
    }

    if ($action === 'contract_save') {
        needProcurementRole();
        $sid = (int)($input['supplier_id'] ?? 0);
        $q = $pdo->prepare("SELECT id FROM suppliers WHERE id = ?"); $q->execute([$sid]); if (!$q->fetchColumn()) error('Supplier not found', 404);
        $title = trim($input['title'] ?? ''); if ($title === '') error('Title required', 422);
        $value = amountOf($input['value'] ?? 0, 'Contract value');
        $start = dateOf($input['start_date'] ?? null);
        $end = !empty($input['end_date']) ? dateOf($input['end_date']) : null;
        if ($end && $end < $start) error('End date is before the start date', 422);
        $status = in_array($input['status'] ?? 'active', ['active', 'closed', 'cancelled'], true) ? ($input['status'] ?? 'active') : 'active';
        $vals = [$sid, trim($input['contract_no'] ?? '') ?: null, $title, $value, $start, $end, $status, trim($input['notes'] ?? '') ?: null];
        if (!empty($input['id'])) {
            $pdo->prepare("UPDATE supplier_contracts SET supplier_id = ?, contract_no = ?, title = ?, value = ?, start_date = ?, end_date = ?, status = ?, notes = ? WHERE id = ?")->execute(array_merge($vals, [(int)$input['id']]));
            success(['id' => (int)$input['id']], 'Contract updated');
        }
        $pdo->prepare("INSERT INTO supplier_contracts (supplier_id, contract_no, title, value, start_date, end_date, status, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?)")->execute(array_merge($vals, [$uid]));
        success(['id' => (int)$pdo->lastInsertId()], 'Contract saved', 201);
    }

    if ($action === 'reverse_entry') {
        needFinanceRole();
        $id = (int)($input['id'] ?? 0);
        $reason = trim($input['reason'] ?? ''); if ($reason === '') error('A reason is required', 422);
        $newId = fin_reverse_entry($pdo, $id, !empty($input['date']) ? dateOf($input['date']) : null, $reason);
        fin_log($pdo, 'REVERSE', 'finance', "Journal entry #$id reversed: $reason", $id);
        success(['reversal_id' => $newId], 'Entry reversed');
    }

    error('Unknown action', 404);
}

error('Method not allowed', 405);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error(strpos($e->getMessage(), 'Accounting period is closed') !== false ? 'That accounting period is closed' : 'Database error: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error($e->getMessage(), $e instanceof InvalidArgumentException ? 422 : ($e instanceof RuntimeException ? (stripos($e->getMessage(), 'not found') !== false ? 404 : 409) : 500));   // rule violations are client errors, not server faults
}

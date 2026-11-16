<?php
// handlers/finance/chart-of-accounts.php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

function jsonResponse($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
function success($d=null,$m='Success',$s=200){jsonResponse(['success'=>true,'message'=>$m,'data'=>$d],$s);}
function error($m,$s=400){jsonResponse(['success'=>false,'message'=>$m],$s);}

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {

// Asset register actions (action=assets*) live in handlers/assets/asset-ledger.php (ported from Michel); it answers and exits.
// Load it BEFORE finance_posting.php: the asset ledger uses expense_schema.php's fin_acct/fin_post/fin_unpost/fin_cash_accounts,
// and loading finance_posting.php as well would redeclare those four functions (PHP fatal).
if (strpos($_GET['action'] ?? '', 'assets') === 0) {
    require __DIR__ . '/../assets/asset-ledger.php';
    error('Unknown asset action', 404); // every known asset action answered and exited above
}

require_once __DIR__ . '/../../includes/finance_posting.php';
require_once __DIR__ . '/../../includes/finance_close.php';

// ===== GET =====
if ($method === 'GET') {

    // Single account
    if ($id) {
        $stmt = $pdo->prepare("
            SELECT c.*, p.account_name AS parent_name, p.account_code AS parent_code
            FROM chart_of_accounts c
            LEFT JOIN chart_of_accounts p ON p.id = c.parent_id
            WHERE c.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) error('Account not found', 404);
        success($row);
    }

    $action = $_GET['action'] ?? '';

    // Journal Entries list
    if ($action === 'journal_entries') {
        $from = $_GET['from'] ?? '';
        $to = $_GET['to'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 25)));
        $offset = ($page - 1) * $limit;

        $where = [];
        $params = [];
        if ($from) { $where[] = "je.entry_date >= ?"; $params[] = $from; }
        if ($to) { $where[] = "je.entry_date <= ?"; $params[] = $to; }
        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count total
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM journal_entries je $whereSQL");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $totalPages = ceil($total / $limit);

        $sql = "SELECT je.*, 
                (SELECT SUM(debit) FROM journal_entry_lines WHERE journal_entry_id=je.id) AS total_debit, 
                (SELECT SUM(credit) FROM journal_entry_lines WHERE journal_entry_id=je.id) AS total_credit,
                (SELECT GROUP_CONCAT(CONCAT(c.account_name,' (',jl.debit,')') SEPARATOR ', ') FROM journal_entry_lines jl JOIN chart_of_accounts c ON c.id=jl.account_id WHERE jl.journal_entry_id=je.id AND jl.debit > 0) AS debited_accounts,
                (SELECT GROUP_CONCAT(CONCAT(c.account_name,' (',jl.credit,')') SEPARATOR ', ') FROM journal_entry_lines jl JOIN chart_of_accounts c ON c.id=jl.account_id WHERE jl.journal_entry_id=je.id AND jl.credit > 0) AS credited_accounts
            FROM journal_entries je $whereSQL ORDER BY je.entry_date DESC, je.id DESC LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        success(['entries' => $stmt->fetchAll(), 'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'total_pages' => $totalPages]]);
    }

    // Single journal entry with lines
    if ($action === 'journal_entry') {
        $jeId = (int)($_GET['id'] ?? 0);
        $je = $pdo->prepare("SELECT * FROM journal_entries WHERE id=?"); $je->execute([$jeId]);
        $entry = $je->fetch();
        if (!$entry) error('Entry not found', 404);
        $lines = $pdo->prepare("SELECT jl.*, c.account_name, c.account_code FROM journal_entry_lines jl LEFT JOIN chart_of_accounts c ON c.id=jl.account_id WHERE jl.journal_entry_id=? ORDER BY jl.id");
        $lines->execute([$jeId]);
        $entry['lines'] = $lines->fetchAll();
        success($entry);
    }

    // General Ledger for an account
    if ($action === 'ledger') {
        $accountId = (int)($_GET['account_id'] ?? 0);
        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-d');
        if (!$accountId) error('account_id required', 422);
        $stmt = $pdo->prepare("SELECT jl.*, je.entry_date, je.reference, je.description FROM journal_entry_lines jl JOIN journal_entries je ON je.id=jl.journal_entry_id WHERE jl.account_id=? AND je.entry_date BETWEEN ? AND ? ORDER BY je.entry_date ASC, jl.id ASC");
        $stmt->execute([$accountId, $from, $to]);
        success($stmt->fetchAll());
    }

    // Balance Sheet (as of ?date=)
    if ($action === 'balance_sheet') {
        $asOf = $_GET['date'] ?? date('Y-m-d');
        $bal = function (string $type, string $expr) use ($pdo, $asOf) {
            $st = $pdo->prepare("SELECT c.*, COALESCE((SELECT SUM($expr) FROM journal_entry_lines jl JOIN journal_entries je ON je.id = jl.journal_entry_id WHERE jl.account_id = c.id AND je.entry_date <= ?), 0) AS balance
                                 FROM chart_of_accounts c WHERE c.account_type = ? AND c.status = 'active' ORDER BY c.account_code");
            $st->execute([$asOf, $type]);
            return $st->fetchAll();
        };
        $assets = $bal('asset', 'jl.debit - jl.credit');
        $liabilities = $bal('liability', 'jl.credit - jl.debit');
        $equity = $bal('equity', 'jl.credit - jl.debit');
        $totalAssets = array_sum(array_column($assets, 'balance'));
        $totalLiabilities = array_sum(array_column($liabilities, 'balance'));
        $totalEquity = array_sum(array_column($equity, 'balance'));
        // Surplus not yet moved to Retained Earnings by a year-end close; the sheet balances with it counted as equity
        $earn = fin_earnings($pdo, $asOf);
        success(['assets'=>$assets,'liabilities'=>$liabilities,'equity'=>$equity,'total_assets'=>$totalAssets,'total_liabilities'=>$totalLiabilities,'total_equity'=>$totalEquity,'current_earnings'=>$earn,'total_equity_with_earnings'=>round($totalEquity+$earn,2),'as_of'=>$asOf]);
    }
    // Trial Balance
    if ($action === 'trial_balance') {
        $asOf = $_GET['date'] ?? date('Y-m-d');
        $stmt = $pdo->prepare("
            SELECT c.id, c.account_code, c.account_name, c.account_type, c.parent_id,
                COALESCE((SELECT SUM(jl.debit) FROM journal_entry_lines jl JOIN journal_entries je ON je.id=jl.journal_entry_id WHERE jl.account_id=c.id AND je.entry_date <= ?), 0) AS total_debit,
                COALESCE((SELECT SUM(jl.credit) FROM journal_entry_lines jl JOIN journal_entries je ON je.id=jl.journal_entry_id WHERE jl.account_id=c.id AND je.entry_date <= ?), 0) AS total_credit
            FROM chart_of_accounts c
            WHERE c.status = 'active'
            ORDER BY c.account_code
        ");
        $stmt->execute([$asOf, $asOf]);
        $accounts = $stmt->fetchAll();
        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($accounts as &$a) {
            $a['total_debit'] = (float)$a['total_debit'];
            $a['total_credit'] = (float)$a['total_credit'];
            // Normal balance: assets & expenses = debit; liabilities, equity, income = credit
            $net = $a['total_debit'] - $a['total_credit'];
            if (in_array($a['account_type'], ['asset', 'expense'])) {
                $a['debit_balance'] = $net > 0 ? $net : 0;
                $a['credit_balance'] = $net < 0 ? abs($net) : 0;
            } else {
                $a['credit_balance'] = (-$net) > 0 ? (-$net) : 0;
                $a['debit_balance'] = (-$net) < 0 ? abs(-$net) : 0;
            }
            $totalDebit += $a['debit_balance'];
            $totalCredit += $a['credit_balance'];
        }
        success(['accounts' => $accounts, 'total_debit' => $totalDebit, 'total_credit' => $totalCredit, 'as_of' => $asOf]);
    }

    // Income Statement
    if ($action === 'income_statement') {
        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-d');
        // optional department (cost centre): 0 / absent = whole hospital, 'none' = lines with no department
        $dept = $_GET['department_id'] ?? '';
        $dw = $dept === 'none' ? ' AND jl.department_id IS NULL' : ((int)$dept ? ' AND jl.department_id=' . (int)$dept : '');
        $income = $pdo->prepare("SELECT c.*, COALESCE((SELECT SUM(jl.credit)-SUM(jl.debit) FROM journal_entry_lines jl JOIN journal_entries je ON je.id=jl.journal_entry_id WHERE jl.account_id=c.id AND je.entry_date BETWEEN ? AND ?$dw AND (je.reference_type IS NULL OR je.reference_type<>'year_end_close')),0) AS balance FROM chart_of_accounts c WHERE c.account_type='income' AND c.status='active' ORDER BY c.account_code");
        $income->execute([$from, $to]); $incomeData = $income->fetchAll();
        $expenses = $pdo->prepare("SELECT c.*, COALESCE((SELECT SUM(jl.debit)-SUM(jl.credit) FROM journal_entry_lines jl JOIN journal_entries je ON je.id=jl.journal_entry_id WHERE jl.account_id=c.id AND je.entry_date BETWEEN ? AND ?$dw AND (je.reference_type IS NULL OR je.reference_type<>'year_end_close')),0) AS balance FROM chart_of_accounts c WHERE c.account_type='expense' AND c.status='active' ORDER BY c.account_code");
        $expenses->execute([$from, $to]); $expenseData = $expenses->fetchAll();
        $totalIncome = array_sum(array_column($incomeData, 'balance'));
        $totalExpenses = array_sum(array_column($expenseData, 'balance'));
        success(['income'=>$incomeData,'expenses'=>$expenseData,'total_income'=>$totalIncome,'total_expenses'=>$totalExpenses,'net_income'=>$totalIncome-$totalExpenses]);
    }

    // Cash & Cash Equivalent balances (parent_id = 3) + Other Operating Expenses (parent_id = 942)
    if ($action === 'cash_balances') {
        $stmt = $pdo->query("
            SELECT c.id, c.account_code, c.account_name, 
                COALESCE((SELECT SUM(jl.debit)-SUM(jl.credit) FROM journal_entry_lines jl WHERE jl.account_id=c.id), 0) AS balance,
                CASE WHEN c.parent_id = 3 THEN 'cash' ELSE 'expense' END AS account_group
            FROM chart_of_accounts c 
            WHERE c.parent_id IN (3, 942) AND c.status = 'active'
            ORDER BY c.parent_id, c.account_code
        ");
        $accounts = $stmt->fetchAll();
        $cashAccounts = array_filter($accounts, function($a) { return $a['account_group'] === 'cash'; });
        $total = array_sum(array_column(array_values($cashAccounts), 'balance'));
        success(['accounts' => $accounts, 'total' => $total]);
    }

    // Insurance Receivable balance for a specific insurance company
    if ($action === 'insurance_receivable') {
        $insuranceId = (int)($_GET['insurance_id'] ?? 0);
        if (!$insuranceId) error('insurance_id required', 422);
        $stmt = $pdo->prepare("
            SELECT c.id, c.account_code, c.account_name, c.linked_insurance_id,
                COALESCE((SELECT SUM(jl.debit)-SUM(jl.credit) FROM journal_entry_lines jl WHERE jl.account_id=c.id), 0) AS balance
            FROM chart_of_accounts c
            WHERE c.linked_insurance_id = ? AND c.parent_id = 7 AND c.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$insuranceId]);
        $account = $stmt->fetch();
        if (!$account) error('No receivable account for this insurance', 404);

        $ledgerBal = (float)$account['balance'];
        $account['ledger_balance'] = $ledgerBal;

        // Check company name to see if it's private/self-pay
        $compStmt = $pdo->prepare("SELECT name FROM insurance_companies WHERE id = ?");
        $compStmt->execute([$insuranceId]);
        $companyName = (string)$compStmt->fetchColumn();
        $isPrivate = ($insuranceId == 14 || stripos($companyName, 'prive') !== false || stripos($companyName, 'private') !== false);

        if ($isPrivate) {
            // Patient pending balance from unpaid private invoices (all-time)
            $balStmt = $pdo->prepare("
                SELECT COALESCE(SUM(i.balance), 0) AS patient_balance,
                       COALESCE((SELECT SUM(pr.amount_paid) FROM payment_records pr JOIN invoices inv2 ON inv2.id = pr.invoice_id LEFT JOIN visits v2 ON v2.id = inv2.visit_id WHERE (inv2.insurance_id = ? OR v2.insurance_id = ? OR inv2.payment_type = 'self_pay') AND pr.status = 'written_off'), 0) AS patient_deferred
                FROM invoices i
                LEFT JOIN visits v ON v.id = i.visit_id
                WHERE (i.insurance_id = ? OR v.insurance_id = ? OR i.payment_type = 'self_pay')
                  AND i.status != 'cancelled'
            ");
            $balStmt->execute([$insuranceId, $insuranceId, $insuranceId, $insuranceId]);
            $patBalRow = $balStmt->fetch();
            $patientBalance = (float)($patBalRow['patient_balance'] ?? 0);
            $patientDeferred = (float)($patBalRow['patient_deferred'] ?? 0);

            $account['insurance_owes']   = 0.0;
            $account['real_balance']     = 0.0;
            $account['patient_pending']  = $patientBalance;
            $account['patient_deferred'] = $patientDeferred;
            $account['settled_amount']   = 0.0;
            $account['balance']          = 0.0; // Insurance owes 0 RWF for private self-pay
            success($account);
        }

        // Calculate real insurance portion owed from all invoices strictly backed by visits / patient insurance
        $invStmt = $pdo->prepare("
            SELECT 
                i.id,
                i.total,
                COALESCE(
                    (SELECT ip.copayment_percentage 
                     FROM patient_insurance pi 
                     JOIN insurance_packages ip ON ip.id = pi.package_id 
                     WHERE pi.id = v.insurance_id AND pi.patient_id = p.id LIMIT 1),
                    (SELECT ip.copayment_percentage 
                     FROM patient_insurance pi 
                     JOIN insurance_packages ip ON ip.id = pi.package_id 
                     WHERE pi.patient_id = p.id 
                       AND (pi.company_id = ? OR pi.id = ?)
                     ORDER BY pi.is_primary DESC, pi.id DESC LIMIT 1),
                    (SELECT ip.copayment_percentage 
                     FROM patient_insurance pi 
                     JOIN insurance_packages ip ON ip.id = pi.package_id 
                     WHERE pi.patient_id = p.id AND pi.is_primary = 1 LIMIT 1),
                    (SELECT ip.copayment_percentage 
                     FROM insurance_packages ip 
                     WHERE ip.company_id = ? AND ip.status = 'active'
                     ORDER BY ip.id ASC LIMIT 1),
                    (SELECT ip.copayment_percentage 
                     FROM insurance_packages ip 
                     WHERE ip.company_id = ? LIMIT 1),
                    15
                ) AS copay_pct
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
        ");
        $invStmt->execute([
            $insuranceId, $insuranceId, $insuranceId, $insuranceId,
            $insuranceId, $insuranceId, $insuranceId, $insuranceId, $insuranceId
        ]);
        $invRows = $invStmt->fetchAll();

        $totInsOwes = 0.0;
        foreach ($invRows as $r) {
            $copay = (float)($r['copay_pct'] ?? 15);
            if ($copay < 100) {
                $totInsOwes += round((float)$r['total'] * (100 - $copay) / 100.0, 2);
            }
        }

        // Check any settled amounts posted in journal entries for this insurance
        $settledStmt = $pdo->prepare("
            SELECT COALESCE(SUM(jl.credit), 0)
            FROM journal_entry_lines jl
            JOIN journal_entries je ON je.id = jl.journal_entry_id
            WHERE jl.account_id = ? AND je.reference_type = 'insurance_settlement'
        ");
        $settledStmt->execute([$account['id']]);
        $settledAmount = (float)$settledStmt->fetchColumn();

        $realBalance = max(0.0, round($totInsOwes - $settledAmount, 2));

        $account['real_balance'] = $realBalance;
        $account['settled_amount'] = $settledAmount;
        $account['balance'] = $realBalance;

        success($account);
    }


    // Cash Flow
    if ($action === 'cash_flow') {
        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-d');
        // Cash accounts (1110, 1111, etc.)
        $cashStmt = $pdo->prepare("SELECT COALESCE(SUM(jl.debit),0) AS inflows, COALESCE(SUM(jl.credit),0) AS outflows FROM journal_entry_lines jl JOIN journal_entries je ON je.id=jl.journal_entry_id JOIN chart_of_accounts c ON c.id=jl.account_id WHERE c.account_code LIKE '111%' AND je.entry_date BETWEEN ? AND ?");
        $cashStmt->execute([$from, $to]);
        $cash = $cashStmt->fetch();
        $inflows = (float)($cash['inflows']??0);
        $outflows = (float)($cash['outflows']??0);
        
        // Details by reference_type
        $details = $pdo->prepare("SELECT COALESCE(je.reference_type,'other') AS category, SUM(jl.debit) AS inflow, SUM(jl.credit) AS outflow FROM journal_entry_lines jl JOIN journal_entries je ON je.id=jl.journal_entry_id JOIN chart_of_accounts c ON c.id=jl.account_id WHERE c.account_code LIKE '111%' AND je.entry_date BETWEEN ? AND ? GROUP BY je.reference_type");
        $details->execute([$from, $to]);
        $detailRows = $details->fetchAll();
        foreach($detailRows as &$dr){ $dr['net'] = (float)$dr['inflow'] - (float)$dr['outflow']; }
        
        success(['inflows'=>$inflows,'outflows'=>$outflows,'net_flow'=>$inflows-$outflows,'details'=>$detailRows]);
    }

    // --- ASSETS ---
    if ($action === 'assets') {
        $stmt = $pdo->query("SELECT * FROM assets ORDER BY asset_code, name");
        success($stmt->fetchAll());
    }
    if ($action === 'assets_categories') {
        $stmt = $pdo->query("SELECT * FROM asset_categories WHERE status='active' ORDER BY name");
        success($stmt->fetchAll());
    }
    if ($action === 'assets_revaluations') {
        $stmt = $pdo->query("SELECT r.*, a.name AS asset_name, a.asset_code FROM asset_revaluations r JOIN assets a ON a.id=r.asset_id ORDER BY r.revaluation_date DESC LIMIT 50");
        success($stmt->fetchAll());
    }
    if ($action === 'assets_capitalizations') {
        $stmt = $pdo->query("SELECT c.*, a.name AS asset_name, a.asset_code FROM asset_capitalizations c JOIN assets a ON a.id=c.asset_id ORDER BY c.capitalization_date DESC LIMIT 50");
        success($stmt->fetchAll());
    }

    // Full tree (flat list ordered for tree display)
    $search     = trim($_GET['search'] ?? '');
    $type       = $_GET['type']   ?? '';
    $status     = $_GET['status'] ?? '';
    $parentId   = isset($_GET['parent_id']) ? (int)$_GET['parent_id'] : null;

    $where = []; $params = [];
    if ($search !== '') {
        $like = "%$search%";
        $where[] = "(c.account_code LIKE ? OR c.account_name LIKE ?)";
        $params  = array_merge($params, [$like, $like]);
    }
    if ($type   !== '') { $where[] = "c.account_type = ?"; $params[] = $type; }
    if ($status !== '') { $where[] = "c.status = ?";       $params[] = $status; }
    if ($parentId !== null) { $where[] = "c.parent_id = ?"; $params[] = $parentId; }

    $ws = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare("
        SELECT c.id, c.account_code, c.account_name, c.account_type,
               c.description, c.parent_id, c.status, c.has_sub,
               c.linked_client_id, c.linked_supplier_id, c.is_default,
               p.account_name AS parent_name
        FROM chart_of_accounts c
        LEFT JOIN chart_of_accounts p ON p.id = c.parent_id
        $ws
        ORDER BY c.account_code ASC
    ");
    $stmt->execute($params);
    $accounts = $stmt->fetchAll();

    // Stats
    $stats = $pdo->query("
        SELECT
            COUNT(*) AS total,
            SUM(account_type='asset')     AS assets,
            SUM(account_type='liability') AS liabilities,
            SUM(account_type='equity')    AS equity,
            SUM(account_type='income')    AS income,
            SUM(account_type='expense')   AS expense,
            SUM(status='active')          AS active,
            SUM(status='inactive')        AS inactive
        FROM chart_of_accounts
    ")->fetch();

    success(['accounts' => $accounts, 'stats' => $stats]);
}

// ===== POST — create =====
// POST — Assets
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'assets') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($input['name'])) error('Name required', 422);
    $code = 'AST-'.str_pad(($pdo->query("SELECT COUNT(*)+1 FROM assets")->fetchColumn()), 4, '0', STR_PAD_LEFT);
    $cost = (float)($input['purchase_cost'] ?? 0);
    $stmt = $pdo->prepare("INSERT INTO assets (asset_code, name, category_id, purchase_date, purchase_cost, current_value, location, serial_number, supplier, warranty_expiry) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$code, $input['name'], (int)($input['category_id']??0), $input['purchase_date']??date('Y-m-d'), $cost, $cost, $input['location']??null, $input['serial_number']??null, $input['supplier']??null, $input['warranty_expiry']??null]);
    success(['id'=>(int)$pdo->lastInsertId(), 'asset_code'=>$code], 'Asset created');
}

if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'assets_depreciate') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $month = (int)($input['month'] ?? date('n'));
    $year = (int)($input['year'] ?? date('Y'));
    $periodDate = sprintf('%04d-%02d-%02d', $year, $month, cal_days_in_month(CAL_GREGORIAN, $month, $year));
    
    $assets = $pdo->query("SELECT a.*, c.depreciation_rate, c.depreciation_method FROM assets a JOIN asset_categories c ON c.id=a.category_id WHERE a.status='active' AND c.depreciation_rate > 0")->fetchAll();
    $count = 0;
    foreach ($assets as $a) {
        $cost = (float)$a['purchase_cost'];
        $rate = (float)$a['depreciation_rate'];
        $accDep = (float)$a['accumulated_depreciation'];
        $bv = $cost - $accDep;
        if ($bv <= 0) continue;
        // Monthly depreciation = annual rate / 12
        $annualDep = $a['depreciation_method'] === 'reducing_balance' ? $bv * $rate / 100 : $cost * $rate / 100;
        $monthlyDep = round($annualDep / 12, 2);
        $monthlyDep = min($monthlyDep, $bv); // Can't depreciate below 0
        if ($monthlyDep <= 0) continue;
        $newAcc = $accDep + $monthlyDep;
        $newBV = $cost - $newAcc;
        $pdo->prepare("UPDATE assets SET accumulated_depreciation=?, current_value=? WHERE id=?")->execute([$newAcc, $newBV, $a['id']]);
        $pdo->prepare("INSERT INTO asset_depreciation_log (asset_id, period_date, depreciation_amount, accumulated, book_value, method) VALUES (?,?,?,?,?,?)")->execute([$a['id'], $periodDate, $monthlyDep, $newAcc, $newBV, $a['depreciation_method']]);
        $count++;
    }
    success(null, "$count assets depreciated for month $month/$year");
}

if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'assets_dispose') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $assetId = (int)($input['asset_id'] ?? 0);
    if (!$assetId) error('asset_id required', 422);
    $pdo->prepare("UPDATE assets SET status='disposed', disposal_date=?, disposal_value=?, disposal_method=? WHERE id=?")
        ->execute([$input['disposal_date']??date('Y-m-d'), (float)($input['disposal_value']??0), $input['disposal_method']??'sold', $assetId]);
    success(null, 'Asset disposed');
}

if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'create_journal_entry') {
    require_once __DIR__ . '/../../includes/controls.php';
    if (!ctl_has_role(['admin', 'accountant'])) error('Only administrators and accountants can post manual journal entries', 403);
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $description = trim($input['description'] ?? '');
    if (!$description) error('Description required', 422);
    $lines = array_map(fn($l) => ['account_id' => (int)$l['account_id'], 'debit' => (float)($l['debit'] ?? 0), 'credit' => (float)($l['credit'] ?? 0),
                                  'desc' => $l['line_description'] ?? ''], $input['lines'] ?? []);
    if (count($lines) < 2) error('At least 2 lines required', 422);
    try {
        $entryId = fin_post($pdo, ['date' => $input['entry_date'] ?? date('Y-m-d'), 'description' => $description, 'lines' => $lines,
            'reference' => trim($input['reference'] ?? '') ?: null]);
    } catch (InvalidArgumentException $e) { error($e->getMessage(), 422); }
    catch (RuntimeException $e) { error($e->getMessage(), 409); }
    $ref = $pdo->prepare("SELECT reference FROM journal_entries WHERE id = ?"); $ref->execute([$entryId]);
    success(['id' => $entryId, 'reference' => $ref->fetchColumn()], 'Journal entry created');
}
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'assets_capitalize') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $assetId = (int)($input['asset_id'] ?? 0);
    $amount = (float)($input['amount'] ?? 0);
    if (!$assetId || !$amount) error('asset_id and amount required', 422);
    
    // Increase asset value
    $pdo->prepare("UPDATE assets SET current_value = current_value + ?, purchase_cost = purchase_cost + ? WHERE id = ?")->execute([$amount, $amount, $assetId]);
    
    // Record capitalization
    $pdo->prepare("INSERT INTO asset_capitalizations (asset_id, capitalization_date, amount, supplier, description) VALUES (?,?,?,?,?)")
        ->execute([$assetId, $input['date']??date('Y-m-d'), $amount, $input['supplier']??'', $input['description']??'']);
    
    success(null, 'Expense capitalized');
}

if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'assets_revalue') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $assetId = (int)($input['asset_id'] ?? 0);
    $newValue = (float)($input['new_value'] ?? 0);
    if (!$assetId || !$newValue) error('asset_id and new_value required', 422);
    
    $asset = $pdo->prepare("SELECT current_value FROM assets WHERE id=?"); $asset->execute([$assetId]);
    $old = $asset->fetch();
    if (!$old) error('Asset not found', 404);
    $oldValue = (float)$old['current_value'];
    
    $pdo->prepare("UPDATE assets SET current_value=?, purchase_cost=? WHERE id=?")->execute([$newValue, $newValue, $assetId]);
    $pdo->prepare("INSERT INTO asset_revaluations (asset_id, revaluation_date, old_value, new_value, reason) VALUES (?,?,?,?,?)")
        ->execute([$assetId, $input['date']??date('Y-m-d'), $oldValue, $newValue, $input['reason']??'']);
    success(null, 'Asset revalued');
}

if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'assets_revaluations') {
    // Already handled in main GET section above
}

// ===== PUT — update asset =====
if ($method === 'PUT' && isset($_GET['action']) && $_GET['action'] === 'assets') {
    $assetId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$assetId) error('Asset ID required', 422);
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    
    $allowed = ['name','category_id','purchase_date','purchase_cost','current_value','location','serial_number','supplier','status','notes'];
    $fields = []; $params = [];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $input)) {
            $fields[] = "$f=?";
            $params[] = $input[$f];
        }
    }
    if (!$fields) error('Nothing to update', 422);
    $params[] = $assetId;
    $pdo->prepare("UPDATE assets SET " . implode(',', $fields) . " WHERE id=?")->execute($params);
    success(null, 'Asset updated');
}

// ===== POST — create account =====
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    foreach (['account_code','account_name','account_type'] as $f) {
        if (empty($input[$f])) error("Field '$f' is required", 422);
    }
    $validTypes = ['asset','liability','equity','income','expense'];
    if (!in_array($input['account_type'], $validTypes)) error('Invalid account_type', 422);

    // Check duplicate code
    $dup = $pdo->prepare("SELECT id FROM chart_of_accounts WHERE account_code = ?");
    $dup->execute([trim($input['account_code'])]);
    if ($dup->fetch()) error('Account code already exists', 409);

    $parentId = !empty($input['parent_id']) ? (int)$input['parent_id'] : null;

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO chart_of_accounts
            (account_code, account_name, account_type, description, parent_id,
             status, linked_client_id, linked_supplier_id, is_default, has_sub)
        VALUES (?,?,?,?,?,?,?,?,0,0)
    ");
    $stmt->execute([
        trim($input['account_code']),
        trim($input['account_name']),
        $input['account_type'],
        trim($input['description'] ?? ''),
        $parentId,
        $input['status'] ?? 'active',
        !empty($input['linked_client_id'])   ? (int)$input['linked_client_id']   : null,
        !empty($input['linked_supplier_id']) ? (int)$input['linked_supplier_id'] : null,
    ]);
    $newId = (int)$pdo->lastInsertId();

    // Mark parent as has_sub
    if ($parentId) {
        $pdo->prepare("UPDATE chart_of_accounts SET has_sub=1 WHERE id=?")->execute([$parentId]);
    }

    $pdo->commit();

    $fetchSt = $pdo->prepare("SELECT c.*, p.account_name AS parent_name FROM chart_of_accounts c LEFT JOIN chart_of_accounts p ON p.id=c.parent_id WHERE c.id=?");
    $fetchSt->execute([$newId]);
    success($fetchSt->fetch(), 'Account created successfully', 201);
}

// ===== PUT — update journal entry date only (no approval required) =====
if ($method === 'PUT' && isset($_GET['action']) && $_GET['action'] === 'update_journal_entry_date') {
    $jeId = (int)($_GET['id'] ?? 0);
    if (!$jeId) error('Journal entry ID required', 422);
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $entryDate = trim($input['entry_date'] ?? '');
    if (!$entryDate) error('entry_date required', 422);
    $chk = $pdo->prepare("SELECT id FROM journal_entries WHERE id=?");
    $chk->execute([$jeId]);
    if (!$chk->fetch()) error('Journal entry not found', 404);
    if (fin_period_closed($pdo, substr($entryDate, 0, 10))) error('That accounting period is closed', 409);
    $pdo->prepare("UPDATE journal_entries SET entry_date=? WHERE id=?")->execute([$entryDate, $jeId]);
    success(null, 'Date updated');
}

// ===== PUT — update journal entry =====
if ($method === 'PUT' && isset($_GET['action']) && $_GET['action'] === 'update_journal_entry') {
    $jeId = (int)($_GET['id'] ?? 0);
    if (!$jeId) error('Journal entry ID required', 422);
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $chk = $pdo->prepare("SELECT id FROM journal_entries WHERE id=?");
    $chk->execute([$jeId]);
    if (!$chk->fetch()) error('Journal entry not found', 404);

    // Non-admins need an edit request for this entry that an administrator approved; it is used up by this edit
    $editRequestId = (int)($input['edit_request_id'] ?? 0);
    if (!isUserAdmin($pdo)) {
        $er = $pdo->prepare("SELECT id FROM edit_requests WHERE id=? AND entity_type='journal_entry' AND entity_id=? AND status='approved'");
        $er->execute([$editRequestId, $jeId]);
        if (!$er->fetch()) error('This journal entry needs an approved edit request before it can be changed.', 403);
    }

    $entryDate = $input['entry_date'] ?? null;
    $description = trim($input['description'] ?? '');
    $lines = $input['lines'] ?? [];

    if (!$entryDate || !$description) error('entry_date and description required', 422);
    if (count($lines) < 2) error('At least 2 lines required', 422);

    // Validate balance
    $totalDebit = array_sum(array_column($lines, 'debit'));
    $totalCredit = array_sum(array_column($lines, 'credit'));
    if (abs($totalDebit - $totalCredit) > 0.01) error('Entry must be balanced. Debit: '.$totalDebit.', Credit: '.$totalCredit, 422);

    if (fin_period_closed($pdo, substr($entryDate, 0, 10))) error('That accounting period is closed', 409);
    $pdo->beginTransaction();
    try {
        // Update header
        $pdo->prepare("UPDATE journal_entries SET entry_date=?, description=? WHERE id=?")->execute([$entryDate, $description, $jeId]);

        // Delete old lines and recreate
        $pdo->prepare("DELETE FROM journal_entry_lines WHERE journal_entry_id=?")->execute([$jeId]);

        $lineStmt = $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,?,?)");
        foreach ($lines as $line) {
            $lineStmt->execute([
                $jeId,
                (int)$line['account_id'],
                $line['line_description'] ?? '',
                (float)($line['debit'] ?? 0),
                (float)($line['credit'] ?? 0)
            ]);
        }

        // Mark edit request as used (if provided)
        if ($editRequestId) {
            $pdo->prepare("UPDATE edit_requests SET status='executed', executed_at=NOW() WHERE id=?")->execute([$editRequestId]);
        }

        $pdo->commit();
        success(null, 'Journal entry updated');
    } catch (Exception $e) {
        $pdo->rollBack();
        error('Update failed: '.$e->getMessage(), 500);
    }
}

if ($method === 'PUT') {
    if (!$id) error('Account ID required', 422);
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $chk = $pdo->prepare("SELECT id, parent_id FROM chart_of_accounts WHERE id=?");
    $chk->execute([$id]);
    $existing = $chk->fetch();
    if (!$existing) error('Account not found', 404);

    // Check duplicate code (excluding self)
    if (!empty($input['account_code'])) {
        $dup = $pdo->prepare("SELECT id FROM chart_of_accounts WHERE account_code=? AND id!=?");
        $dup->execute([trim($input['account_code']), $id]);
        if ($dup->fetch()) error('Account code already exists', 409);
    }

    $allowed = ['account_code','account_name','account_type','description','parent_id','status','linked_client_id','linked_supplier_id'];
    $fields = []; $params = [];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $input)) {
            $fields[] = "$f=?";
            $params[] = $input[$f] === '' ? null : $input[$f];
        }
    }
    if (!$fields) error('Nothing to update', 422);

    $params[] = $id;
    $pdo->prepare("UPDATE chart_of_accounts SET ".implode(',',$fields)." WHERE id=?")->execute($params);

    // Update has_sub on old and new parent
    $newParent = $input['parent_id'] ?? $existing['parent_id'];
    if ($newParent && $newParent != $existing['parent_id']) {
        // mark new parent
        $pdo->prepare("UPDATE chart_of_accounts SET has_sub=1 WHERE id=?")->execute([$newParent]);
        // re-check old parent
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM chart_of_accounts WHERE parent_id=?");
        $cnt->execute([$existing['parent_id']]);
        if ((int)$cnt->fetchColumn() === 0) {
            $pdo->prepare("UPDATE chart_of_accounts SET has_sub=0 WHERE id=?")->execute([$existing['parent_id']]);
        }
    }

    $fetchSt = $pdo->prepare("SELECT c.*, p.account_name AS parent_name FROM chart_of_accounts c LEFT JOIN chart_of_accounts p ON p.id=c.parent_id WHERE c.id=?");
    $fetchSt->execute([$id]);
    success($fetchSt->fetch(), 'Account updated');
}

// ===== DELETE =====
if ($method === 'DELETE') {
    if (!$id) error('Account ID required', 422);

    $chk = $pdo->prepare("SELECT id, parent_id, has_sub FROM chart_of_accounts WHERE id=?");
    $chk->execute([$id]);
    $acc = $chk->fetch();
    if (!$acc) error('Account not found', 404);
    if ($acc['has_sub']) error('Cannot delete — account has sub-accounts', 409);
    // Block delete when anything points at this account; tell the user what
    $uses = [];
    $q = $pdo->prepare("SELECT je.reference_type, COUNT(DISTINCT je.id) n FROM journal_entry_lines jl JOIN journal_entries je ON je.id=jl.journal_entry_id WHERE jl.account_id=? GROUP BY je.reference_type");
    $q->execute([$id]);
    foreach ($q->fetchAll() as $r) $uses[] = $r['n'] . ' journal entr' . ($r['n'] == 1 ? 'y' : 'ies') . ' from ' . ($r['reference_type'] ?: 'manual entries');
    $cols = $pdo->query("SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME LIKE '%account\_id' AND TABLE_NAME NOT IN ('journal_entry_lines','chart_of_accounts')")->fetchAll();
    foreach ($cols as $col) {
        $q = $pdo->prepare("SELECT COUNT(*) FROM `{$col['t']}` WHERE `{$col['c']}`=?");
        $q->execute([$id]);
        if ($n = (int)$q->fetchColumn()) $uses[] = "$n record(s) in " . str_replace('_', ' ', $col['t']) . ' (' . str_replace('_', ' ', $col['c']) . ')';
    }
    if ($uses && empty($_GET['force'])) jsonResponse(['success'=>false,'in_use'=>true,'uses'=>$uses,'message'=>'Account is in use'], 409);

    $pdo->prepare("DELETE FROM chart_of_accounts WHERE id=?")->execute([$id]);

    // Re-check parent
    if ($acc['parent_id']) {
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM chart_of_accounts WHERE parent_id=?");
        $cnt->execute([$acc['parent_id']]);
        if ((int)$cnt->fetchColumn() === 0) {
            $pdo->prepare("UPDATE chart_of_accounts SET has_sub=0 WHERE id=?")->execute([$acc['parent_id']]);
        }
    }
    success(null, 'Account deleted');
}

error('Method not allowed', 405);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error(strpos($e->getMessage(), 'Accounting period is closed') !== false ? 'That accounting period is closed' : 'Database error: ' . $e->getMessage(), 500);
} catch (InvalidArgumentException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error($e->getMessage(), 422);
} catch (RuntimeException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error($e->getMessage(), 409);
}
?>

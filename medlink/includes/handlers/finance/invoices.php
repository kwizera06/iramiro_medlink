<?php
// handlers/finance/invoices.php
require_once __DIR__ . '/../../includes/database.php';

function jsonResponse($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
function success($d=null,$m='Success',$s=200){jsonResponse(['success'=>true,'message'=>$m,'data'=>$d],$s);}
function error($m,$s=400){jsonResponse(['success'=>false,'message'=>$m],$s);}

function generateJournalRef(PDO $pdo): string {
    $prefix = 'JNL-'.date('Ymd').'-';
    $stmt = $pdo->prepare("SELECT reference FROM journal_entries WHERE reference LIKE ? ORDER BY reference DESC LIMIT 1");
    $stmt->execute([$prefix.'%']);
    $last = $stmt->fetchColumn();
    $seq  = $last ? ((int)substr($last,-4)+1) : 1;
    return $prefix.str_pad($seq,4,'0',STR_PAD_LEFT);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {

// ===== GET — list invoices or support data =====
if ($method === 'GET') {

    // Bank accounts (children of Cash & Cash Equivalents, parent_id=3)
    if ($action === 'bank_accounts') {
        $stmt = $pdo->query("SELECT id, account_code, account_name FROM chart_of_accounts WHERE parent_id=3 AND status='active' ORDER BY account_code");
        success($stmt->fetchAll());
    }

    // Income accounts for payment mapping
    if ($action === 'income_accounts') {
        $stmt = $pdo->query("SELECT id, account_code, account_name FROM chart_of_accounts WHERE account_type='income' AND status='active' ORDER BY account_code");
        success($stmt->fetchAll());
    }

    // Single invoice with items
    if ($id) {
        $stmt = $pdo->prepare("
            SELECT i.*, 
                   TRIM(CONCAT(COALESCE(uc.first_name,''),' ',COALESCE(uc.last_name,''))) AS created_by_name,
                   TRIM(CONCAT(COALESCE(ur.first_name,''),' ',COALESCE(ur.last_name,''))) AS payment_received_by_name,
                   p.first_name, p.last_name, p.patient_no,
                   v.visit_no, v.visit_date, v.visit_type,
                   coa.id AS patient_coa_id, coa.account_code AS patient_coa_code, coa.account_name AS patient_coa_name
            FROM invoices i
            JOIN patients p ON p.id = i.patient_id
            JOIN visits v ON v.id = i.visit_id
            LEFT JOIN users uc ON uc.id = i.created_by
            LEFT JOIN users ur ON ur.id = i.payment_received_by
            LEFT JOIN chart_of_accounts coa ON coa.linked_client_id = i.patient_id AND coa.parent_id = 7
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        $inv = $stmt->fetch();
        if (!$inv) error('Invoice not found', 404);
        $iStmt = $pdo->prepare("SELECT ii.*, s.code AS service_code, s.name AS service_name FROM invoice_items ii LEFT JOIN service_catalog s ON s.id=ii.service_id WHERE ii.invoice_id=? ORDER BY ii.id");
        $iStmt->execute([$id]);
        $inv['items'] = $iStmt->fetchAll();
        success($inv);
    }

    // List invoices
    $status  = $_GET['status'] ?? 'pending'; // pending = issued+partial
    $search  = trim($_GET['search'] ?? '');
    $page    = max(1,(int)($_GET['page'] ?? 1));
    $limit   = min(100,max(1,(int)($_GET['limit'] ?? 25)));
    $offset  = ($page-1)*$limit;

    $where = []; $params = [];
    if ($status === 'pending') {
        $where[] = "i.status IN ('issued','partial','draft')";
    } elseif ($status !== 'all') {
        $where[] = "i.status = ?"; $params[] = $status;
    }
    if ($search !== '') {
        $like = "%$search%";
        $where[] = "(i.invoice_no LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_no LIKE ? OR v.visit_no LIKE ?)";
        $params = array_merge($params, [$like,$like,$like,$like,$like]);
    }
    $ws = $where ? 'WHERE '.implode(' AND ',$where) : '';

    $cnt = $pdo->prepare("SELECT COUNT(*) FROM invoices i JOIN patients p ON p.id=i.patient_id JOIN visits v ON v.id=i.visit_id $ws");
    $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT i.id, i.invoice_no, i.payment_type, i.subtotal, i.total, i.paid_amount, i.balance,
               i.status, i.created_at, i.visit_id, i.created_by, i.payment_received_by,
               TRIM(CONCAT(COALESCE(uc.first_name,''),' ',COALESCE(uc.last_name,''))) AS created_by_name,
               TRIM(CONCAT(COALESCE(ur.first_name,''),' ',COALESCE(ur.last_name,''))) AS payment_received_by_name,
               p.id AS patient_id, p.first_name, p.last_name, p.patient_no,
               v.visit_no, v.visit_date, v.visit_type,
               coa.id AS patient_coa_id, coa.account_name AS patient_coa_name
        FROM invoices i
        JOIN patients p ON p.id=i.patient_id
        JOIN visits v ON v.id=i.visit_id
        LEFT JOIN users uc ON uc.id = i.created_by
        LEFT JOIN users ur ON ur.id = i.payment_received_by
        LEFT JOIN chart_of_accounts coa ON coa.linked_client_id=i.patient_id AND coa.parent_id=7
        $ws ORDER BY i.created_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $invoices = $stmt->fetchAll();

    // Stats
    $stats = $pdo->query("
        SELECT
            SUM(CASE WHEN status IN ('issued','partial','draft') THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN status IN ('issued','partial','draft') THEN balance ELSE 0 END) AS pending_amount,
            SUM(CASE WHEN status='paid' THEN 1 ELSE 0 END) AS paid_count,
            SUM(CASE WHEN status='paid' THEN total ELSE 0 END) AS paid_amount,
            SUM(CASE WHEN status='partial' THEN 1 ELSE 0 END) AS partial_count
        FROM invoices
    ")->fetch();

    success([
        'invoices'   => $invoices,
        'stats'      => $stats,
        'pagination' => ['total'=>$total,'page'=>$page,'limit'=>$limit,'total_pages'=>(int)ceil($total/max($limit,1))]
    ]);
}

// ===== POST — record manual payment =====
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    foreach (['invoice_id','amount','bank_account_id','income_account_id'] as $f) {
        if (empty($input[$f])) error("Field '$f' is required", 422);
    }

    $invoiceId      = (int)$input['invoice_id'];
    $amount         = (float)$input['amount'];
    $bankAccountId  = (int)$input['bank_account_id'];
    $incomeAccId    = (int)$input['income_account_id'];
    $paymentDate    = $input['payment_date'] ?? date('Y-m-d');
    $notes          = trim($input['notes'] ?? '');

    if ($amount <= 0) error('Amount must be greater than zero', 422);

    // Load invoice
    $invStmt = $pdo->prepare("
        SELECT i.*, p.first_name, p.last_name, p.patient_no,
               v.visit_no,
               coa.id AS patient_coa_id, coa.account_code AS patient_coa_code
        FROM invoices i
        JOIN patients p ON p.id=i.patient_id
        JOIN visits v ON v.id=i.visit_id
        LEFT JOIN chart_of_accounts coa ON coa.linked_client_id=i.patient_id AND coa.parent_id=7
        WHERE i.id=?
    ");
    $invStmt->execute([$invoiceId]);
    $inv = $invStmt->fetch();
    if (!$inv) error('Invoice not found', 404);
    if ($inv['status'] === 'paid') error('Invoice is already fully paid', 409);

    // Validate bank account
    $bankStmt = $pdo->prepare("SELECT id, account_code, account_name FROM chart_of_accounts WHERE id=? AND status='active'");
    $bankStmt->execute([$bankAccountId]);
    $bankAcc = $bankStmt->fetch();
    if (!$bankAcc) error('Bank account not found', 404);

    // Validate income account
    $incStmt = $pdo->prepare("SELECT id, account_code, account_name FROM chart_of_accounts WHERE id=? AND status='active'");
    $incStmt->execute([$incomeAccId]);
    $incAcc = $incStmt->fetch();
    if (!$incAcc) error('Income account not found', 404);

    // Cap amount at balance
    $payAmount = min($amount, (float)$inv['balance']);
    $newPaid   = (float)$inv['paid_amount'] + $payAmount;
    $newBal    = max(0, (float)$inv['total'] - $newPaid);
    $newStatus = $newBal <= 0 ? 'paid' : 'partial';

    $patientName = $inv['first_name'].' '.$inv['last_name'];
    $desc = "Payment — {$inv['invoice_no']} — {$patientName} ({$inv['patient_no']})".($notes?" — $notes":'');

    $pdo->beginTransaction();

    $ref = generateJournalRef($pdo);

    // === Journal Entry 1: Invoice creation (if not already posted) ===
    // DR Patient Receivable (patient COA under 1120)
    // CR Income account (e.g. consultation fee 974)
    // Only create if this is the first payment (status was issued/draft)
    if (in_array($inv['status'], ['issued','draft'])) {
        $jeStmt = $pdo->prepare("INSERT INTO journal_entries (entry_date,reference,description,reference_type,reference_id,posted,posted_at,created_at) VALUES (?,?,?,?,?,1,NOW(),NOW())");
        $jeStmt->execute([$paymentDate, $ref.'-A', "Invoice — {$inv['invoice_no']} — {$patientName}", 'invoice', $invoiceId]);
        $je1Id = (int)$pdo->lastInsertId();

        $lineStmt = $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id,account_id,line_description,debit,credit) VALUES (?,?,?,?,?)");

        // DR Patient receivable
        $patCoa = fin_private_ar_acct($pdo);
        $lineStmt->execute([$je1Id, $patCoa, "Receivable — {$inv['invoice_no']}", $inv['total'], 0]);
        // CR Income account
        $lineStmt->execute([$je1Id, $incomeAccId, "Income — {$inv['invoice_no']}", 0, $inv['total']]);

        $ref2 = generateJournalRef($pdo); // new ref for payment entry
    } else {
        $ref2 = $ref;
    }

    // === Journal Entry 2: Payment received ===
    // CR Patient Receivable (reduce what patient owes)
    // DR Bank Account (money comes in)
    $jeStmt2 = $pdo->prepare("INSERT INTO journal_entries (entry_date,reference,description,reference_type,reference_id,posted,posted_at,created_at) VALUES (?,?,?,?,?,1,NOW(),NOW())");
    $jeStmt2->execute([$paymentDate, $ref2, $desc, 'payment', $invoiceId]);
    $je2Id = (int)$pdo->lastInsertId();

    $lineStmt2 = $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id,account_id,line_description,debit,credit) VALUES (?,?,?,?,?)");
    $patCoa = fin_private_ar_acct($pdo);
    // DR Bank
    $lineStmt2->execute([$je2Id, $bankAccountId, "Payment received — {$inv['invoice_no']} via {$bankAcc['account_name']}", $payAmount, 0]);
    // CR Patient Receivable
    $lineStmt2->execute([$je2Id, $patCoa, "Receivable cleared — {$inv['invoice_no']}", 0, $payAmount]);

    // Update invoice paid_amount, balance, status
    $pdo->prepare("UPDATE invoices SET paid_amount=?, balance=?, status=? WHERE id=?")->execute([$newPaid, $newBal, $newStatus, $invoiceId]);

    // === Assign queue number if not already queued ===
    $visitRow = $pdo->prepare("SELECT department_id, queue_no FROM visits WHERE id=?");
    $visitRow->execute([$inv['visit_id']]);
    $visitData = $visitRow->fetch();
    $queueNo = null;

    if ($visitData && $visitData['department_id'] && !$visitData['queue_no']) {
        $deptId = (int)$visitData['department_id'];
        // Get dept prefix (first 3 letters of dept name)
        $deptStmt = $pdo->prepare("SELECT name FROM departments WHERE id=?");
        $deptStmt->execute([$deptId]);
        $deptName = $deptStmt->fetchColumn() ?: 'GEN';
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/','',$deptName), 0, 3));

        // Next queue number for today in this dept
        $qSeq = $pdo->prepare("SELECT COUNT(*) FROM patient_queue WHERE department_id=? AND queue_date=CURDATE()");
        $qSeq->execute([$deptId]);
        $seq = (int)$qSeq->fetchColumn() + 1;
        $queueNo = $prefix.'-'.str_pad($seq, 3, '0', STR_PAD_LEFT);

        // Insert into queue
        $pdo->prepare("INSERT INTO patient_queue (visit_id,patient_id,department_id,queue_no,queue_date,status) VALUES (?,?,?,?,CURDATE(),'waiting')")
            ->execute([$inv['visit_id'], $inv['patient_id'], $deptId, $queueNo]);

        // Update visit
        $pdo->prepare("UPDATE visits SET queue_no=?, queued_at=NOW(), consultation_status='waiting' WHERE id=?")
            ->execute([$queueNo, $inv['visit_id']]);
    }

    $pdo->commit();

    success([
        'invoice_no'    => $inv['invoice_no'],
        'amount_paid'   => $payAmount,
        'new_balance'   => $newBal,
        'new_status'    => $newStatus,
        'journal_ref'   => $ref2,
        'bank_account'  => $bankAcc['account_name'],
        'income_account'=> $incAcc['account_name'],
        'queue_no'      => $queueNo
    ], 'Payment recorded successfully', 201);
}

error('Method not allowed', 405);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error('Database error: '.$e->getMessage(), 500);
}
?>

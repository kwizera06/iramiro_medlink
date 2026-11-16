<?php
// handlers/procurement/procurement-ext.php
// Procurement-owned endpoint for what Michel keeps in inventory/inventory-management (RFQ -> quotation -> evaluation -> award, PO delivery tracking)
// and finance/finance-ops (contracts, supplier bills, supplier payments). Ported from Michel with medlink's ledger model:
// the GRN already credits "Payable - <supplier>", so supplier bills are documents only and payments post Dr payable / Cr cash.

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/expense_schema.php';      // fin_acct / fin_post / fin_unpost / fin_cash_accounts
require_once __DIR__ . '/../../includes/procurement_rules.php';
require_once __DIR__ . '/../../includes/procurement_schema.php';
require_once __DIR__ . '/../../includes/controls.php';
pc_migrate($pdo);

function jsonResponse($d, $s = 200) { http_response_code($s); echo json_encode($d); exit(); }
function success($d = null, $m = 'Success', $s = 200) { jsonResponse(['success' => true, 'message' => $m, 'data' => $d], $s); }
function error($m, $s = 400) { jsonResponse(['success' => false, 'message' => $m], $s); }

// Paying a supplier stays with Finance: administrators and accountants only
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


// Payable account of one supplier (chart_of_accounts.linked_supplier_id, under Accounts Payable); created on first use like inventory-management does
function fin_supplier_acct(PDO $pdo, int $supplierId): int {
    if ($supplierId) {
        $q = $pdo->prepare("SELECT id FROM chart_of_accounts WHERE linked_supplier_id = ? LIMIT 1");
        $q->execute([$supplierId]);
        if ($id = $q->fetchColumn()) return (int)$id;
        $s = $pdo->prepare("SELECT company_name FROM suppliers WHERE id = ?"); $s->execute([$supplierId]);
        if ($name = $s->fetchColumn()) {
            $ap = fin_acct($pdo, 'ap');
            $code = '2110' . str_pad((string)$supplierId, 2, '0', STR_PAD_LEFT);
            $taken = $pdo->prepare("SELECT COUNT(*) FROM chart_of_accounts WHERE account_code = ?");
            for ($n = 0; ; $n++) { $try = $n ? $code . '-' . $n : $code; $taken->execute([$try]); if (!$taken->fetchColumn()) { $code = $try; break; } }
            $pdo->prepare("INSERT INTO chart_of_accounts (account_code, account_name, account_type, parent_id, status, is_default, has_sub, linked_supplier_id) VALUES (?,?,'liability',?,'active',0,0,?)")
                ->execute([$code, 'Payable - ' . trim($name), $ap, $supplierId]);
            return (int)$pdo->lastInsertId();
        }
    }
    return fin_acct($pdo, 'ap');
}

function po_set_status(PDO $pdo, int $poId, string $status, string $note = ''): void {
    if (!in_array($status, ["draft","sent","confirmed","cancelled"], true)) error("Invalid status (goods are received through a GRN)", 422);
    $po = $pdo->prepare("SELECT po.created_by, pr.requested_by FROM purchase_orders po LEFT JOIN purchase_requests pr ON pr.id = po.pr_id WHERE po.id=?");
    $po->execute([$poId]);
    $po = $po->fetch(); if (!$po) error("Purchase order not found", 404);
    if ($status === "confirmed" && ($m = ctl_conflict($pdo, "approve", ["raised" => $po["created_by"], "requested" => $po["requested_by"]]))) error($m, 403);
    $pdo->prepare("UPDATE purchase_orders SET status=? WHERE id=?")->execute([$status, $poId]);
    if ($status === "confirmed") $pdo->prepare("UPDATE purchase_orders SET approved_by=?, approved_at=NOW() WHERE id=?")->execute([ctl_uid() ?: null, $poId]);
    if ($note !== '') $pdo->prepare("UPDATE purchase_orders SET notes=CONCAT(COALESCE(notes,''),' | ',?) WHERE id=?")->execute([$note, $poId]);
}

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
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$uid    = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$smUid  = (int)($_SESSION['user_id'] ?? 0) ?: 'NULL';   // stock_movements.created_by, interpolated into the INSERT below

try {

if ($method === 'GET') {
    if ($action === 'cash_accounts') {
        $rows = fin_cash_accounts($pdo);
        foreach ($rows as &$r) $r['balance'] = accountBalance($pdo, (int)$r['id']);
        success($rows);
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

    if ($action === 'contracts') success(contractRows($pdo));

    if ($action === "supplier_performance") {
        $rows = $pdo->query("SELECT s.id, s.company_name,
                (SELECT COUNT(*) FROM purchase_orders po WHERE po.supplier_id=s.id AND po.status NOT IN ('cancelled','draft')) AS orders,
                (SELECT COALESCE(SUM(po.total_amount),0) FROM purchase_orders po WHERE po.supplier_id=s.id AND po.status NOT IN ('cancelled','draft')) AS spend,
                (SELECT MAX(g.received_date) FROM goods_receiving_notes g WHERE g.supplier_id=s.id) AS last_delivery
            FROM suppliers s WHERE s.status='active' ORDER BY s.company_name")->fetchAll();
        $del = [];
        foreach ($pdo->query("SELECT po.supplier_id, COUNT(*) pos,
                    SUM(CASE WHEN po.delivery_date IS NULL OR g.first_receipt <= po.delivery_date THEN 1 ELSE 0 END) on_time,
                    AVG(DATEDIFF(g.first_receipt, po.delivery_date)) avg_delay
                FROM purchase_orders po
                JOIN (SELECT po_id, MIN(received_date) first_receipt FROM goods_receiving_notes GROUP BY po_id) g ON g.po_id=po.id
                WHERE po.status<>'cancelled' GROUP BY po.supplier_id") as $d) $del[(int)$d['supplier_id']] = $d;
        $qual = [];
        foreach ($pdo->query("SELECT po.supplier_id, SUM(gri.quantity_received) received, SUM(gri.quantity_accepted) accepted,
                    SUM(gri.quantity_rejected) rejected
                FROM goods_receiving_items gri
                JOIN goods_receiving_notes g ON g.id=gri.grn_id
                JOIN purchase_orders po ON po.id=g.po_id
                WHERE po.status<>'cancelled' GROUP BY po.supplier_id") as $q) $qual[(int)$q['supplier_id']] = $q;
        $ord = [];
        foreach ($pdo->query("SELECT po.supplier_id, SUM(poi.quantity_ordered) ordered
                FROM purchase_order_items poi JOIN purchase_orders po ON po.id=poi.po_id
                WHERE po.status NOT IN ('cancelled','draft') GROUP BY po.supplier_id") as $o) $ord[(int)$o['supplier_id']] = (float)$o['ordered'];
        foreach ($rows as &$r) {
            $d = $del[(int)$r['id']] ?? null; $q = $qual[(int)$r['id']] ?? null;
            $r['delivered_orders'] = $d ? (int)$d['pos'] : 0;
            $r['on_time_rate'] = ($d && (int)$d['pos'] > 0) ? round(100 * (int)$d['on_time'] / (int)$d['pos'], 1) : null;
            $r['avg_delay_days'] = ($d && $d['avg_delay'] !== null) ? round((float)$d['avg_delay'], 1) : null;
            $r['received_qty'] = $q ? (float)$q['received'] : 0;
            $r['accepted_qty'] = $q ? (float)$q['accepted'] : 0;
            $r['rejected_qty'] = $q ? (float)$q['rejected'] : 0;
            $r['ordered_qty'] = $ord[(int)$r['id']] ?? 0;
            $r['quality_rate'] = ($q && (float)$q['received'] > 0) ? round(100 * (float)$q['accepted'] / (float)$q['received'], 1) : null;
            $r['fulfilment_rate'] = ($r['ordered_qty'] > 0 && $r['received_qty'] > 0) ? round(100 * min($r['received_qty'], $r['ordered_qty']) / $r['ordered_qty'], 1) : null;
            $r['score'] = ($r['on_time_rate'] !== null && $r['quality_rate'] !== null && $r['fulfilment_rate'] !== null)
                ? round(0.5 * $r['on_time_rate'] + 0.3 * $r['quality_rate'] + 0.2 * $r['fulfilment_rate'], 1) : null;
            $r['grade'] = $r['score'] === null ? null : ($r['score'] >= 90 ? 'A' : ($r['score'] >= 75 ? 'B' : ($r['score'] >= 60 ? 'C' : 'D')));
        }
        unset($r);
        success($rows);
    }

    if ($action === 'rfqs') {
        $status = trim((string)($_GET['status'] ?? ''));
        $sql = "SELECT r.*, pr.pr_number, s.company_name AS awarded_supplier_name,
                       (SELECT COUNT(*) FROM procurement_quotations q WHERE q.rfq_id=r.id) AS quotation_count
                FROM procurement_rfqs r
                LEFT JOIN purchase_requests pr ON pr.id=r.pr_id
                LEFT JOIN suppliers s ON s.id=r.awarded_supplier_id";
        $params = [];
        if ($status !== '') { $sql .= ' WHERE r.status = ?'; $params[] = $status; }
        $sql .= ' ORDER BY r.created_at DESC, r.id DESC';
        $q = $pdo->prepare($sql); $q->execute($params);
        success($q->fetchAll());
    }
    if ($action === 'rfq_detail' && $id) {
        $q = $pdo->prepare("SELECT r.*, pr.pr_number FROM procurement_rfqs r LEFT JOIN purchase_requests pr ON pr.id=r.pr_id WHERE r.id=?");
        $q->execute([$id]); $rfq = $q->fetch();
        if (!$rfq) error('RFQ not found', 404);
        $q = $pdo->prepare('SELECT * FROM procurement_rfq_items WHERE rfq_id=? ORDER BY id'); $q->execute([$id]); $rfq['items'] = $q->fetchAll();
        $q = $pdo->prepare("SELECT q.*, s.company_name, e.technical_score, e.price_score, e.delivery_score, e.total_score, e.recommendation
                            FROM procurement_quotations q JOIN suppliers s ON s.id=q.supplier_id
                            LEFT JOIN procurement_evaluations e ON e.quotation_id=q.id WHERE q.rfq_id=? ORDER BY q.total_amount, q.id");
        $q->execute([$id]); $rfq['quotations'] = $q->fetchAll();
        $q = $pdo->prepare("SELECT a.*, s.company_name FROM procurement_awards a JOIN suppliers s ON s.id=a.supplier_id WHERE a.rfq_id=? ORDER BY a.id");
        $q->execute([$id]); $rfq['awards'] = $q->fetchAll();
        success($rfq);
    }

    error('Unknown action', 404);
}

if ($method === 'POST') {
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
                ['account_id' => fin_acct($pdo, '2124')  /* ponytail: medlink has no wht_payable map key; 2124 Withholding Tax 15% is the nearest liability account */, 'credit' => $wht, 'desc' => 'Tax withheld from ' . $name],
            ]]);
        si_reallocate_invoices($pdo, $supplierId);
        $pdo->commit();
        log_activity($pdo, 'CREATE', 'finance', "Supplier payment #$pid of " . number_format($amount) . " to $name", $pid);
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
        // ponytail: no journal here. Michel's GRN credits GRNI and this bill moves it to the supplier; medlink's GRN already credits
        // 'Payable - <supplier>' directly, so posting again would double the liability.
        $pdo->commit();
        log_activity($pdo, 'CREATE', 'procurement', "Supplier invoice $number from $name: " . number_format($amount), $sid);
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

    // === RETURN GOODS TO SUPPLIER (goods return note) ===
    if ($action === "return_goods") {
        $grnId = (int)($input["grn_id"] ?? 0);
        $items = $input["items"] ?? [];
        $grn = $pdo->prepare("SELECT g.*, po.po_number FROM goods_receiving_notes g LEFT JOIN purchase_orders po ON po.id = g.po_id WHERE g.id = ?");
        $grn->execute([$grnId]);
        $grn = $grn->fetch();
        if (!$grn) error("GRN not found", 404);
        if (!$items) error("items required", 422);
        $date = $input["return_date"] ?? date("Y-m-d");

        $pdo->beginTransaction();
        try {
            $returnNo = next_doc_no($pdo, 'supplier_returns', 'return_number', 'RTN-' . date('Ymd') . '-');
            // batches this GRN created, newest stock first
            $batches = $pdo->prepare("SELECT b.id, b.quantity, b.unit_cost, b.store_id FROM stock_movements m JOIN stock_batches b ON b.id = m.batch_id
                                      WHERE ((m.reference_type = 'grn' AND m.reference_id = ?) OR (m.reference_type = 'purchase_order' AND m.reference_id = ?)) AND m.item_id = ? FOR UPDATE");
            $move = $pdo->prepare("INSERT INTO stock_movements (item_id, batch_id, from_store_id, movement_type, reference_type, reference_id, quantity, unit_cost, notes, created_by) VALUES (?,?,?,'return','supplier_return',?,?,?,?, $smUid)");
            $value = 0; $moves = [];
            foreach ($items as $it) {
                $itemId = (int)($it["item_id"] ?? 0);
                $left = (float)($it["quantity"] ?? 0);
                if (!$itemId || $left <= 0) continue;
                $batches->execute([$grnId, (int)$grn["po_id"], $itemId]);
                foreach ($batches->fetchAll() as $b) {
                    if ($left <= 0) break;
                    $take = min($left, (float)$b["quantity"]);
                    if ($take <= 0) continue;
                    $pdo->prepare("UPDATE stock_batches SET quantity = quantity - ? WHERE id = ?")->execute([$take, $b["id"]]);
                    $moves[] = [$itemId, (int)$b["id"], (int)$b["store_id"], -$take, (float)$b["unit_cost"]];
                    $value += $take * (float)$b["unit_cost"];
                    $left -= $take;
                }
                if ($left > 0.0001) throw new RuntimeException("Cannot return more of item #$itemId than is left from this GRN in stock");
                // the supplier owes a replacement: the PO line is open again
                $pdo->prepare("UPDATE purchase_order_items SET quantity_received = GREATEST(0, quantity_received - ?) WHERE po_id = ? AND item_id = ?")
                    ->execute([(float)$it["quantity"], (int)$grn["po_id"], $itemId]);
            }
            if ($value <= 0) throw new RuntimeException("Nothing to return");
            $against = $grn["supplier_invoice_id"] ? "supplier" : "grni";
            $pdo->prepare("INSERT INTO supplier_returns (return_number, grn_id, supplier_id, return_date, amount, against, reason, created_by) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$returnNo, $grnId, $grn["supplier_id"], $date, round($value, 2), $against, $input["reason"] ?? null, $_SESSION["user_id"] ?? null]);
            $rid = (int)$pdo->lastInsertId();
            foreach ($moves as [$itemId, $batchId, $store, $qty, $cost]) $move->execute([$itemId, $batchId, $store, $rid, $qty, $cost, "Returned to supplier $returnNo"]);
            if ($grn["po_id"]) $pdo->prepare("UPDATE purchase_orders SET status = 'partially_received' WHERE id = ? AND status = 'received'")->execute([$grn["po_id"]]);

            // Ledger: Cr store, Dr the supplier's payable account (medlink's receipt credits "Payable - <supplier>" directly, never a GRNI account).
            // ponytail: a store id doubles as its inventory account id, as in medlink's receive_goods.
            $byStore = [];
            foreach ($moves as $m) $byStore[$m[2]] = ($byStore[$m[2]] ?? 0) + $m[3] * $m[4];
            $supAcct = fin_supplier_acct($pdo, (int)$grn["supplier_id"]);
            foreach ($byStore as $store => $v) {
                $v = round(-$v, 2);
                if ($v > 0) fin_post($pdo, ['date' => $date, 'type' => 'supplier_return', 'ref_id' => "$rid-$store", 'description' => "Returned to supplier $returnNo ({$grn['grn_number']})",
                    'lines' => [['account_id' => $supAcct, 'debit' => $v, 'desc' => "Return $returnNo"], ['account_id' => (int)$store, 'credit' => $v, 'desc' => "Stock out $returnNo"]]]);
            }

            $pdo->commit();
            success(["id" => $rid, "return_number" => $returnNo, "amount" => round($value, 2), "against" => $against], "$returnNo: " . number_format($value) . " RWF returned", 201);
        } catch (Exception $e) {
            $pdo->rollBack();
            error("Return failed: " . $e->getMessage(), 422);
        }
    }


    // === UPDATE PO STATUS (POST) ===
    if ($action === "update_po_status") {
        $poId = (int)($input['po_id'] ?? $id ?? 0);
        if (!$poId) error("PO ID required", 422);
        po_set_status($pdo, $poId, (string)($input["status"] ?? ''), (string)($input['note'] ?? ''));
        success(null, "PO status updated to: ".$input["status"]);
    }
    // === CREATE PURCHASE ORDER ===
    if ($action === "create_purchase_order") {
        if (empty($input["supplier_id"]) || empty($input["items"]) || !is_array($input["items"])) 
            error("Supplier and items required", 422);
        $stStmt = $pdo->prepare("SELECT status FROM suppliers WHERE id=?"); $stStmt->execute([(int)$input["supplier_id"]]); $supStatus = $stStmt->fetchColumn();
        if ($supStatus !== 'active') error($supStatus ? "This supplier is $supStatus; purchase orders can only go to active suppliers." : 'Supplier not found', 409);
        $ctStmt = $pdo->prepare("SELECT contract_no, title, end_date FROM supplier_contracts WHERE supplier_id=? AND status='active' AND end_date IS NOT NULL ORDER BY end_date DESC LIMIT 1");
        $ctStmt->execute([(int)$input["supplier_id"]]); $ct = $ctStmt->fetch(PDO::FETCH_ASSOC);
        if ($ct && $ct['end_date'] < date('Y-m-d')) error("Supplier contract " . ($ct['contract_no'] ?: $ct['title']) . " expired on " . $ct['end_date'] . ". Renew the contract, or run a Request for Quotation (RFQ) to choose a supplier, before creating a purchase order.", 409);
        $pdo->beginTransaction();
        try {
            $poNumber = next_doc_no($pdo, 'purchase_orders', 'po_number', 'PO-' . date('Ymd') . '-');
            $subtotal = 0;
            foreach ($input["items"] as $item) {
                $subtotal += (float)($item["quantity_ordered"] ?? 0) * (float)($item["unit_price"] ?? 0);
            }
            $taxRate = (float)($input["tax_rate"] ?? 0);
            $taxAmount = $subtotal * ($taxRate / 100);
            $totalAmount = $subtotal + $taxAmount;

            // PROC-07: preserve the RFQ and final award that supplied these
            // prices. Older callers may send only pr_id; derive provenance.
            $rfqId = !empty($input['rfq_id']) ? (int)$input['rfq_id'] : 0;
            $awardId = !empty($input['award_id']) ? (int)$input['award_id'] : 0;
            if (!$rfqId && !empty($input['pr_id'])) {
                $rq = $pdo->prepare("SELECT id FROM procurement_rfqs WHERE pr_id=? AND status='awarded' ORDER BY id DESC LIMIT 1");
                $rq->execute([(int)$input['pr_id']]);
                $rfqId = (int)$rq->fetchColumn();
            }
            if ($rfqId && !$awardId) {
                $aq = $pdo->prepare("SELECT id FROM procurement_awards WHERE rfq_id=? AND award_type='final' ORDER BY id DESC LIMIT 1");
                $aq->execute([$rfqId]);
                $awardId = (int)$aq->fetchColumn();
            }
            if ($awardId) {
                $aq = $pdo->prepare("SELECT rfq_id, supplier_id FROM procurement_awards WHERE id=? AND award_type='final'");
                $aq->execute([$awardId]);
                $award = $aq->fetch(PDO::FETCH_ASSOC);
                if (!$award) error('Final procurement award not found', 422);
                if ($rfqId && (int)$award['rfq_id'] !== $rfqId) error('RFQ and award do not match', 422);
                if ((int)$award['supplier_id'] !== (int)$input['supplier_id']) error('PO supplier does not match the final award', 422);
                $rfqId = (int)$award['rfq_id'];
            }

            $stmt = $pdo->prepare("INSERT INTO purchase_orders (po_number, pr_id, rfq_id, award_id, supplier_id, order_date, delivery_date, 
                shipping_method, tax_rate, subtotal, tax_amount, total_amount, status, delivery_address, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $poNumber,
                !empty($input["pr_id"]) ? (int)$input["pr_id"] : null,
                $rfqId ?: null,
                $awardId ?: null,
                (int)$input["supplier_id"],
                $input["order_date"] ?? date("Y-m-d"),
                $input["delivery_date"] ?? null,
                $input["shipping_method"] ?? null,
                $taxRate, $subtotal, $taxAmount, $totalAmount,
                "draft", // a PO is approved separately (po_set_status)
                $input["delivery_address"] ?? null,
                $input["notes"] ?? null,
                ctl_uid() ?: null
            ]);
            $poId = (int)$pdo->lastInsertId();

            foreach ($input["items"] as $item) {
                if (empty($item["item_id"]) && empty($item["item_description"])) continue;
                $qty = (float)($item["quantity_ordered"] ?? 0);
                $price = (float)($item["unit_price"] ?? 0);
                $pdo->prepare("INSERT INTO purchase_order_items (po_id, item_id, item_description, quantity_ordered, uom_id, unit_price, total_price, notes) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$poId,
                        !empty($item["item_id"]) ? (int)$item["item_id"] : null,
                        $item["item_description"] ?? null,
                        $qty,
                        !empty($item["uom_id"]) ? (int)$item["uom_id"] : null,
                        $price,
                        $qty * $price,
                        $item["notes"] ?? null
                    ]);
            }

            // Update PR status if linked
            if (!empty($input["pr_id"])) {
                $pdo->prepare("UPDATE purchase_requests SET status='ordered' WHERE id=?")->execute([(int)$input["pr_id"]]);
            }

            $pdo->commit();
            success(["id" => $poId, "po_number" => $poNumber], "Purchase order created", 201);
        } catch (Exception $e) {
            $pdo->rollBack();
            error("Failed to create purchase order: " . $e->getMessage(), 500);
        }
    }


    if ($action === 'create_rfq') {
        $prId = !empty($input['pr_id']) ? (int)$input['pr_id'] : null;
        if ($prId) {
            $q = $pdo->prepare("SELECT * FROM purchase_requests WHERE id=? AND status IN ('approved','ordered')"); $q->execute([$prId]);
            $pr = $q->fetch(); if (!$pr) error('Purchase request must be approved before sourcing', 422);
            $itemsQ = $pdo->prepare('SELECT item_id, item_description, quantity, uom_id, estimated_unit_price, notes FROM purchase_request_items WHERE pr_id=? ORDER BY id');
            $itemsQ->execute([$prId]); $items = $itemsQ->fetchAll();
        } else $items = (array)($input['items'] ?? []);
        if (!$items) error('RFQ items are required', 422);
        $title = trim((string)($input['title'] ?? '')); if ($title === '') error('RFQ title is required', 422);
        $pdo->beginTransaction();
        try {
            $no = next_doc_no($pdo, 'procurement_rfqs', 'rfq_number', 'RFQ-' . date('Ymd') . '-');
            $pdo->prepare('INSERT INTO procurement_rfqs (rfq_number,pr_id,title,issue_date,closing_date,status,notes,created_by) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$no, $prId, $title, $input['issue_date'] ?? date('Y-m-d'), $input['closing_date'] ?: null, 'published', trim((string)($input['notes'] ?? '')) ?: null, ctl_uid() ?: null]);
            $rid = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare('INSERT INTO procurement_rfq_items (rfq_id,item_id,item_description,quantity,uom_id,target_unit_price,notes) VALUES (?,?,?,?,?,?,?)');
            foreach ($items as $it) $ins->execute([$rid, !empty($it['item_id']) ? (int)$it['item_id'] : null, $it['item_description'] ?? null, (float)($it['quantity'] ?? 0), !empty($it['uom_id']) ? (int)$it['uom_id'] : null, (float)($it['estimated_unit_price'] ?? $it['target_unit_price'] ?? 0), $it['notes'] ?? null]);
            $pdo->commit(); success(['id'=>$rid,'rfq_number'=>$no], 'RFQ published', 201);
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error('Failed to create RFQ: '.$e->getMessage(), 500); }
    }

    // === REGISTER SUPPLIER QUOTATION ===
    if ($action === 'add_quotation') {
        $rfqId = (int)($input['rfq_id'] ?? 0); $supplierId = (int)($input['supplier_id'] ?? 0);
        $number = trim((string)($input['quotation_number'] ?? '')); $amount = round((float)($input['total_amount'] ?? 0), 2);
        if (!$rfqId || !$supplierId || $number === '') error('RFQ, supplier and quotation number are required', 422);
        $q = $pdo->prepare("SELECT status, closing_date FROM procurement_rfqs WHERE id=?"); $q->execute([$rfqId]); $rfq = $q->fetch();
        if (!$rfq) error('RFQ not found', 404);
        if ($rfq['status'] !== 'published') error('RFQ is not open for quotations (it is ' . $rfq['status'] . ')', 409);
        if ($rfq['closing_date'] && date('Y-m-d') > $rfq['closing_date']) error('This RFQ closed on ' . $rfq['closing_date'] . '; no more quotations can be recorded', 409);
        $q = $pdo->prepare("SELECT 1 FROM procurement_awards WHERE rfq_id=? LIMIT 1"); $q->execute([$rfqId]);
        if ($q->fetchColumn()) error('An award is already in progress for this RFQ; quotations are closed', 409);
        $q = $pdo->prepare("SELECT 1 FROM suppliers WHERE id=? AND is_active=1 AND status='active'"); $q->execute([$supplierId]);
        if (!$q->fetchColumn()) error('Choose an active supplier (inactive, suspended and blacklisted suppliers cannot bid)', 422);
        $q = $pdo->prepare("SELECT 1 FROM procurement_quotations WHERE rfq_id=? AND supplier_id=? AND status<>'rejected'"); $q->execute([$rfqId, $supplierId]);
        if ($q->fetchColumn()) error('This supplier already has a quotation on this RFQ', 409);
        if (!empty($input['valid_until']) && $input['valid_until'] < ($input['quotation_date'] ?? date('Y-m-d'))) error('Valid-until is before the quotation date', 422);
        $items = $pdo->prepare('SELECT id, quantity FROM procurement_rfq_items WHERE rfq_id=? ORDER BY id'); $items->execute([$rfqId]); $rfqItems = $items->fetchAll();
        $prices = (array)($input['items'] ?? []);
        // line prices, when given, decide the total (the browser's total is only used when there are no lines)
        $lineSum = 0; foreach ($rfqItems as $i => $row) { $p = $prices[$i] ?? []; $lineSum += (float)($p['quantity'] ?? $row['quantity']) * (float)($p['unit_price'] ?? 0); }
        if ($lineSum > 0) $amount = round($lineSum, 2);
        if ($amount <= 0) error('The quotation total must be greater than zero', 422);
        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO procurement_quotations (rfq_id,supplier_id,quotation_number,quotation_date,valid_until,delivery_days,total_amount,notes,submitted_by) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([$rfqId,$supplierId,$number,$input['quotation_date'] ?? date('Y-m-d'),$input['valid_until'] ?? null,(int)($input['delivery_days'] ?? 0),$amount,trim((string)($input['notes'] ?? '')) ?: null,ctl_uid() ?: null]);
            $qid = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare('INSERT INTO procurement_quotation_items (quotation_id,rfq_item_id,quantity,unit_price,total_price,notes) VALUES (?,?,?,?,?,?)');
            foreach ($rfqItems as $i => $row) { $p = $prices[$i] ?? []; $qty = (float)($p['quantity'] ?? $row['quantity']); $unit = (float)($p['unit_price'] ?? 0); $ins->execute([$qid,$row['id'],$qty,$unit,round($qty*$unit,2),$p['notes'] ?? null]); }
            rfq_rescore($pdo, $rfqId); // a cheaper bid changes everyone's price score
            $pdo->commit(); success(['id'=>$qid], 'Quotation recorded', 201);
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error('Failed to record quotation: '.$e->getMessage(), 500); }
    }

    // === SCORE QUOTATION === (the price score is worked out here from the bids, never typed in)
    if ($action === 'evaluate_quotation') {
        $qid = (int)($input['quotation_id'] ?? 0); $technical = (float)($input['technical_score'] ?? 0); $delivery = (float)($input['delivery_score'] ?? 0);
        if (!$qid || min($technical,$delivery) < 0 || max($technical,$delivery) > 100) error('Scores must be between 0 and 100', 422);
        $q = $pdo->prepare('SELECT rfq_id, status FROM procurement_quotations WHERE id=?'); $q->execute([$qid]); $quote = $q->fetch();
        if (!$quote) error('Quotation not found', 404);
        $rfqId = (int)$quote['rfq_id'];
        $q = $pdo->prepare('SELECT status FROM procurement_rfqs WHERE id=?'); $q->execute([$rfqId]);
        if (!in_array($q->fetchColumn(), ['published', 'closed'], true)) error('This RFQ is no longer open for evaluation', 409);
        $q = $pdo->prepare('SELECT 1 FROM procurement_awards WHERE rfq_id=? LIMIT 1'); $q->execute([$rfqId]);
        if ($q->fetchColumn()) error('Scores are locked: an award has already been recorded for this RFQ', 409);
        if (!in_array($quote['status'], ['submitted', 'shortlisted'], true)) error('This quotation is ' . $quote['status'] . ' and cannot be evaluated', 409);
        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO procurement_evaluations (rfq_id,quotation_id,technical_score,price_score,delivery_score,total_score,recommendation,evaluated_by) VALUES (?,?,?,0,?,0,?,?) ON DUPLICATE KEY UPDATE technical_score=VALUES(technical_score),delivery_score=VALUES(delivery_score),recommendation=VALUES(recommendation),evaluated_by=VALUES(evaluated_by),evaluated_at=NOW()')
                ->execute([$rfqId,$qid,$technical,$delivery,trim((string)($input['recommendation'] ?? '')) ?: null,ctl_uid() ?: null]);
            $pdo->prepare("UPDATE procurement_quotations SET status='shortlisted' WHERE id=? AND status='submitted'")->execute([$qid]);
            rfq_rescore($pdo, $rfqId);
            $s = $pdo->prepare('SELECT price_score,total_score FROM procurement_evaluations WHERE quotation_id=?'); $s->execute([$qid]); $score = $s->fetch();
            $pdo->commit();
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error('Failed to save the evaluation: '.$e->getMessage(), 500); }
        success(['price_score'=>(float)$score['price_score'], 'total_score'=>(float)$score['total_score']], 'Quotation evaluated');
    }

    // === PROVISIONAL / FINAL AWARD ===
    // provisional (after evaluation) starts the standstill period; final only follows it, for the same bidder. The server date is used, never the browser's.
    if ($action === 'award_rfq') {
        $rfqId = (int)($input['rfq_id'] ?? 0); $qid = (int)($input['quotation_id'] ?? 0); $type = ($input['award_type'] ?? 'provisional') === 'final' ? 'final' : 'provisional';
        $note = trim((string)($input['decision_note'] ?? ''));
        $today = date('Y-m-d');
        $pdo->beginTransaction();
        try {
            $q = $pdo->prepare('SELECT * FROM procurement_rfqs WHERE id=? FOR UPDATE'); $q->execute([$rfqId]); $rfq = $q->fetch();
            if (!$rfq) { $pdo->rollBack(); error('RFQ not found', 404); }
            if (!in_array($rfq['status'], ['published', 'closed'], true)) { $pdo->rollBack(); error('This RFQ is ' . $rfq['status'] . ' and cannot be awarded', 409); }
            $q = $pdo->prepare("SELECT q.supplier_id, q.status, e.total_score, e.evaluated_by FROM procurement_quotations q LEFT JOIN procurement_evaluations e ON e.quotation_id=q.id WHERE q.id=? AND q.rfq_id=?");
            $q->execute([$qid, $rfqId]); $quote = $q->fetch();
            if (!$quote) { $pdo->rollBack(); error('Quotation does not belong to this RFQ', 422); }
            if ($quote['total_score'] === null) { $pdo->rollBack(); error('Evaluate this quotation before awarding it', 409); }
            if (!in_array($quote['status'], ['submitted', 'shortlisted'], true)) { $pdo->rollBack(); error('This quotation is ' . $quote['status'] . ' and cannot be awarded', 409); }
            $supplierId = (int)$quote['supplier_id'];
            $q = $pdo->prepare('SELECT status FROM suppliers WHERE id=?'); $q->execute([$supplierId]); $supStatus = $q->fetchColumn();
            if ($supStatus !== 'active') { $pdo->rollBack(); error('This supplier is ' . ($supStatus ?: 'missing') . ' and cannot be awarded', 409); }
            if ($m = ctl_conflict($pdo, 'award', ['evaluated' => $quote['evaluated_by']])) { $pdo->rollBack(); error($m, 403); }
            $q = $pdo->prepare("SELECT MAX(e.total_score) FROM procurement_evaluations e JOIN procurement_quotations q ON q.id=e.quotation_id WHERE e.rfq_id=? AND q.status<>'rejected'");
            $q->execute([$rfqId]);
            if ((float)$quote['total_score'] < (float)$q->fetchColumn() && $note === '') { $pdo->rollBack(); error('This bidder did not score highest: a written justification is required', 422); }

            if ($type === 'final') {
                $q = $pdo->prepare("SELECT quotation_id FROM procurement_awards WHERE rfq_id=? AND award_type='provisional' ORDER BY id DESC LIMIT 1"); $q->execute([$rfqId]);
                $prov = $q->fetchColumn();
                if (!$prov) { $pdo->rollBack(); error('Issue a provisional award first; the final award can only follow the standstill period', 409); }
                if ((int)$prov !== $qid) { $pdo->rollBack(); error('The final award must go to the bidder named in the provisional award; issue a new provisional award to change it', 409); }
                if ($rfq['standstill_end'] && $today <= $rfq['standstill_end']) { $pdo->rollBack(); error('The standstill period runs until ' . $rfq['standstill_end'] . '; the final award can be made after that date', 409); }
                $ctStmt = $pdo->prepare("SELECT contract_no, title, end_date FROM supplier_contracts WHERE supplier_id=? AND status='active' AND end_date IS NOT NULL ORDER BY end_date DESC LIMIT 1");
                $ctStmt->execute([$supplierId]); $ct = $ctStmt->fetch(PDO::FETCH_ASSOC);
                if ($ct && $ct['end_date'] < $today) { $pdo->rollBack(); error("Cannot make a final award: supplier contract " . ($ct['contract_no'] ?: $ct['title']) . " expired on " . $ct['end_date'] . ".", 409); }
            }
            $pdo->prepare('INSERT INTO procurement_awards (rfq_id,quotation_id,supplier_id,award_type,award_date,decision_note,notified_at,created_by) VALUES (?,?,?,?,?,?,NOW(),?)')
                ->execute([$rfqId,$qid,$supplierId,$type,$today,$note ?: null,ctl_uid() ?: null]);
            if ($type === 'final') {
                $pdo->prepare("UPDATE procurement_rfqs SET status='awarded', awarded_supplier_id=?, awarded_quotation_id=?, final_notified_at=NOW() WHERE id=?")->execute([$supplierId,$qid,$rfqId]);
                $pdo->prepare("UPDATE procurement_quotations SET status=CASE WHEN id=? THEN 'awarded' ELSE 'rejected' END WHERE rfq_id=?")->execute([$qid,$rfqId]);
            } else {
                $days = rfq_standstill_days($pdo);
                $pdo->prepare('UPDATE procurement_rfqs SET provisional_notified_at=NOW(), standstill_end=DATE_ADD(?, INTERVAL ? DAY) WHERE id=?')->execute([$today,$days,$rfqId]);
            }
            $pdo->commit();
            $end = $pdo->prepare('SELECT standstill_end FROM procurement_rfqs WHERE id=?'); $end->execute([$rfqId]);
             $standstillEnd = $end->fetchColumn();
             $message = ucfirst($type) . ' award recorded';
             if ($type === 'provisional' && $standstillEnd) $message .= '; the final award can be made after ' . $standstillEnd;
             success(['rfq_id'=>$rfqId,'quotation_id'=>$qid,'supplier_id'=>$supplierId,'award_type'=>$type,'standstill_end'=>$standstillEnd], $message);
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error('Failed to record award: '.$e->getMessage(),500); }
    }

    // === PO DELIVERY TRACKING === (received / partially received is decided only by a GRN)
    if ($action === 'update_po_delivery') {
        $poId = (int)($input['po_id'] ?? 0); $st = $input['delivery_status'] ?? '';
        if (!in_array($st, ['pending', 'in_transit', 'delayed'], true)) error('Choose pending, in transit or delayed', 422);
        $note = trim((string)($input['note'] ?? ''));
        if ($st === 'delayed' && $note === '') error('Say why the delivery is delayed', 422);
        $q = $pdo->prepare('SELECT status, order_date FROM purchase_orders WHERE id=?'); $q->execute([$poId]); $po = $q->fetch();
        if (!$po) error('Purchase order not found', 404);
        if (!in_array($po['status'], ['confirmed', 'partially_received'], true)) error('Only an approved PO that is still open can be tracked (it is ' . $po['status'] . ')', 409);
        $exp = trim((string)($input['expected_date'] ?? ''));
        if ($exp !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $exp);
            if (!$d || $d->format('Y-m-d') !== $exp) error('Expected delivery date is not valid', 422);
            if ($exp < $po['order_date']) error('The expected date is before the PO date (' . $po['order_date'] . ')', 422);
        }
        $pdo->prepare('UPDATE purchase_orders SET delivery_status=?, carrier=?, tracking_ref=?, delivery_note=?, delivery_date=COALESCE(?, delivery_date) WHERE id=?')
            ->execute([$st, trim((string)($input['carrier'] ?? '')) ?: null, trim((string)($input['tracking_ref'] ?? '')) ?: null, $note ?: null, $exp ?: null, $poId]);
        log_activity($pdo, 'update', 'procurement', "Delivery of PO #$poId marked $st" . ($note ? ": $note" : ''), $poId);
        success(null, 'Delivery updated');
    }


    error('Unknown action', 404);
}

error('Method not allowed', 405);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('procurement-ext: ' . $e->getMessage());
    error($e->getMessage(), $e instanceof InvalidArgumentException ? 422 : 500);
}

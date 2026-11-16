<?php
// handlers/finance/receivables.php
// Money owed to the hospital that does not get paid in full:
//   - bad debts: provision (allowance for doubtful accounts) and write-off of a patient's balance
//   - insurance claims: one per insurer and month, with the rejections the insurer sends back and the decision on each
// Every ledger effect goes through fin_post(); the screens are in includes/finance_receivables_ui.php.
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/finance_posting.php';

function jsonResponse($d, $s = 200) { http_response_code($s); echo json_encode($d); exit(); }
function success($d = null, $m = 'Success', $s = 200) { jsonResponse(['success' => true, 'message' => $m, 'data' => $d], $s); }
function error($m, $s = 400) { jsonResponse(['success' => false, 'message' => $m], $s); }

function needFinanceRole(array $extra = []): void {
    if (!array_intersect($_SESSION['user_data']['role_slugs'] ?? [], array_merge(['admin', 'accountant'], $extra))) error('Only administrators and accountants can do this', 403);
}
function amountOf($v, string $label = 'Amount'): float {
    $a = round((float)$v, 2);
    if ($a <= 0) error("$label must be greater than zero", 422);
    return $a;
}
function dateOf($v, string $label = 'Date'): string {
    $d = substr((string)($v ?: date('Y-m-d')), 0, 10);
    $t = DateTime::createFromFormat('Y-m-d', $d);
    if (!$t || $t->format('Y-m-d') !== $d) error("$label is not a valid date", 422);
    if ($d > date('Y-m-d')) error("$label cannot be in the future", 422);
    return $d;
}
function balanceOf(PDO $pdo, int $accountId): float {
    $q = $pdo->prepare("SELECT COALESCE(SUM(debit - credit), 0) FROM journal_entry_lines WHERE account_id = ?");
    $q->execute([$accountId]);
    return round((float)$q->fetchColumn(), 2);
}

// Insured invoices of an insurer in a month with the share the ledger holds against the insurer
// Medlink invoices keep the patient_insurance id in invoices.insurance_id and post the insurer's share to the insurer's receivable account
// (or the general receivable account when the invoice was posted without one), so both are matched here.
function insuranceCompanyOf(PDO $pdo, $invoiceInsuranceId): int {
    $q = $pdo->prepare("SELECT company_id FROM patient_insurance WHERE id = ?"); $q->execute([(int)$invoiceInsuranceId]);
    return (int)($q->fetchColumn() ?: $invoiceInsuranceId);
}
function claimInvoices(PDO $pdo, int $insuranceId, string $ym): array {
    $q = $pdo->prepare("SELECT i.id, i.invoice_no, i.patient_id, DATE(i.created_at) AS invoice_date, ROUND(SUM(jl.debit - jl.credit), 2) AS insurer_share
                        FROM invoices i
                        JOIN journal_entries je ON je.reference_type IN ('invoice','invoice_adj') AND je.reference_id = CAST(i.id AS CHAR)
                        JOIN journal_entry_lines jl ON jl.journal_entry_id = je.id AND jl.account_id IN (?, ?)
                        WHERE (i.insurance_id IN (SELECT id FROM patient_insurance WHERE company_id = ?) OR (i.insurance_id = ? AND NOT EXISTS (SELECT 1 FROM patient_insurance px WHERE px.id = i.insurance_id)))
                          AND i.payment_type IN ('insurance','mixed') AND DATE_FORMAT(i.created_at, '%Y-%m') = ?
                        GROUP BY i.id HAVING insurer_share > 0.005 ORDER BY i.id");
    $q->execute([fin_insurer_acct($pdo, $insuranceId), fin_acct($pdo, 'ar_general'), $insuranceId, $insuranceId, $ym]);
    return $q->fetchAll();
}

// Totals for one claim: what was claimed, settled by the insurer, rejected and decided, and what is still outstanding
function claimSummary(PDO $pdo, array $c): array {
    $inv = claimInvoices($pdo, (int)$c['insurance_id'], $c['period_ym']);
    $ids = array_map('intval', array_column($inv, 'id'));
    $settled = 0.0;
    if ($ids) {
        $in = implode(',', $ids);
        $settled = round((float)$pdo->query("SELECT COALESCE(SUM(l.settled_amount), 0) FROM insurance_settlement_lines l
                                             JOIN insurance_settlements s ON s.id = l.settlement_id AND s.status = 'posted' WHERE l.invoice_id IN ($in)")->fetchColumn(), 2);
    }
    $r = $pdo->prepare("SELECT status, COALESCE(SUM(amount), 0) AS amt, COUNT(*) AS n FROM insurance_claim_rejections WHERE claim_id = ? AND status <> 'voided' GROUP BY status");
    $r->execute([$c['id']]);
    $by = [];
    foreach ($r->fetchAll() as $row) $by[$row['status']] = ['amount' => round((float)$row['amt'], 2), 'n' => (int)$row['n']];
    $gone = ($by['billed_patient']['amount'] ?? 0) + ($by['written_off']['amount'] ?? 0);
    $outstanding = round($c['claimed_amount'] - $settled - $gone, 2);
    $state = $c['status'] === 'cancelled' ? 'cancelled'
        : ($outstanding <= 0.01 ? 'settled' : (!empty($by['open']) ? 'rejections_to_decide' : (!empty($by['resubmitted']) ? 'resubmitted' : 'awaiting_payment')));
    return ['invoices' => $inv, 'settled' => $settled, 'rejected_open' => $by['open']['amount'] ?? 0, 'resubmitted' => $by['resubmitted']['amount'] ?? 0,
            'billed_patient' => $by['billed_patient']['amount'] ?? 0, 'written_off' => $by['written_off']['amount'] ?? 0, 'outstanding' => $outstanding, 'state' => $state];
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$uid    = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

try {
    // =================== GET ===================
    if ($method === 'GET') {
        if ($action === 'debtors') {
            // One 'Receivable - Private' ledger account: who owes what comes from open invoices less write-offs.
            $rows = $pdo->query("SELECT p.id AS patient_id, CONCAT_WS(' ', p.first_name, p.last_name) AS account_name,
                                        ROUND(SUM(i.balance) - COALESCE((SELECT SUM(w.amount) FROM finance_writeoffs w WHERE w.patient_id = p.id AND w.kind = 'writeoff' AND w.status <> 'voided'), 0), 2) AS balance,
                                        MIN(DATE(i.created_at)) AS oldest_open_invoice
                                 FROM invoices i JOIN patients p ON p.id = i.patient_id
                                 WHERE i.status NOT IN ('cancelled','paid') AND i.balance > 0
                                 GROUP BY p.id HAVING balance > 0.005 ORDER BY balance DESC LIMIT 300")->fetchAll();
            foreach ($rows as &$r) $r['days_old'] = $r['oldest_open_invoice'] ? (int)floor((time() - strtotime($r['oldest_open_invoice'])) / 86400) : null;
            unset($r);
            $patientAr = balanceOf($pdo, fin_private_ar_acct($pdo));
            $allowance = -balanceOf($pdo, fin_acct($pdo, 'allowance')); // credit balance
            success(['debtors' => $rows, 'patient_receivables' => $patientAr, 'allowance' => $allowance, 'net_receivables' => round($patientAr - $allowance, 2)]);
        }

        if ($action === 'writeoffs') {
            success($pdo->query("SELECT w.*, CONCAT_WS(' ', p.first_name, p.last_name) AS patient FROM finance_writeoffs w LEFT JOIN patients p ON p.id = w.patient_id
                                 ORDER BY w.entry_date DESC, w.id DESC LIMIT 100")->fetchAll());
        }

        if ($action === 'insurers') {
            success($pdo->query("SELECT id, name FROM insurance_companies WHERE id <> " . FIN_PRIVATE_INSURER . " ORDER BY name")->fetchAll());
        }

        if ($action === 'claim_preview') {
            $ins = (int)($_GET['insurance_id'] ?? 0); $ym = (string)($_GET['period'] ?? '');
            if (!$ins || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $ym)) error('Choose an insurer and a month', 422);
            $inv = claimInvoices($pdo, $ins, $ym);
            success(['invoice_count' => count($inv), 'claimed_amount' => round(array_sum(array_column($inv, 'insurer_share')), 2)]);
        }

        if ($action === 'claims') {
            $rows = $pdo->query("SELECT c.*, i.name AS insurer FROM insurance_claims c LEFT JOIN insurance_companies i ON i.id = c.insurance_id ORDER BY c.period_ym DESC, c.id DESC LIMIT 100")->fetchAll();
            foreach ($rows as &$r) { $r['claimed_amount'] = (float)$r['claimed_amount']; $s = claimSummary($pdo, $r); unset($s['invoices']); $r += $s; }
            success($rows);
        }

        // One invoice of a claim for the Review drawer: lines, header, member number, lock state, audit trail (before/after from activity_log)
        if ($action === 'claim_invoice') {
            needFinanceRole(['insurance_officer']);
            $iid = (int)($_GET['id'] ?? 0);
            $q = $pdo->prepare("SELECT i.id, i.invoice_no, i.patient_id, i.visit_id, i.payment_type, i.insurance_id, i.status, i.subtotal, i.discount, i.tax, i.total, i.paid_amount, i.balance, i.created_at,
                                       CONCAT(p.first_name, ' ', p.last_name) AS patient, ic.name AS insurer
                                FROM invoices i LEFT JOIN patients p ON p.id = i.patient_id LEFT JOIN insurance_companies ic ON ic.id = COALESCE((SELECT company_id FROM patient_insurance WHERE id = i.insurance_id), i.insurance_id) WHERE i.id = ?");
            $q->execute([$iid]);
            $inv = $q->fetch();
            if (!$inv) error('Invoice not found', 404);
            $it = $pdo->prepare("SELECT id, service_id, description, category, quantity, unit_price, discount, total FROM invoice_items WHERE invoice_id = ? ORDER BY id");
            $it->execute([$iid]);
            $inv['items'] = $it->fetchAll();
            $m = $pdo->prepare("SELECT id, member_number FROM patient_insurance WHERE patient_id = ? AND company_id = ? ORDER BY is_primary DESC, id DESC LIMIT 1");
            $m->execute([$inv['patient_id'], insuranceCompanyOf($pdo, $inv['insurance_id'])]);
            $inv['member'] = $m->fetch() ?: null;
            $c = $pdo->prepare("SELECT id, claim_no FROM insurance_claims WHERE insurance_id = ? AND period_ym = DATE_FORMAT(?, '%Y-%m') AND status = 'submitted'");
            $c->execute([insuranceCompanyOf($pdo, $inv['insurance_id']), $inv['created_at']]);
            $inv['claim'] = $c->fetch() ?: null;
            $inv['is_admin'] = in_array('admin', $_SESSION['user_data']['role_slugs'] ?? [], true);
            $e = $pdo->prepare("SELECT id, status FROM edit_requests WHERE entity_type = 'invoice' AND entity_id = ? AND status IN ('pending','approved') ORDER BY id DESC LIMIT 1");
            try { $e->execute([$iid]); $inv['edit_request'] = $e->fetch() ?: null; } catch (Throwable $x) { $inv['edit_request'] = null; }
            $a = $pdo->prepare("SELECT created_at, user_name, description, changes FROM activity_log WHERE module = 'invoices' AND record_id = ? AND action IN ('EDIT','UPDATE') ORDER BY id DESC LIMIT 50");
            try { $a->execute([$iid]); $inv['audit'] = $a->fetchAll(); } catch (Throwable $x) { $inv['audit'] = []; }
            success($inv);
        }

        if ($action === 'claim') {
            $q = $pdo->prepare("SELECT c.*, i.name AS insurer FROM insurance_claims c LEFT JOIN insurance_companies i ON i.id = c.insurance_id WHERE c.id = ?");
            $q->execute([(int)($_GET['id'] ?? 0)]);
            $c = $q->fetch();
            if (!$c) error('Claim not found', 404);
            $c['claimed_amount'] = (float)$c['claimed_amount'];
            $c += claimSummary($pdo, $c);
            $r = $pdo->prepare("SELECT r.*, v.invoice_no FROM insurance_claim_rejections r LEFT JOIN invoices v ON v.id = r.invoice_id WHERE r.claim_id = ? ORDER BY r.id DESC");
            $r->execute([$c['id']]);
            $c['rejections'] = $r->fetchAll();
            success($c);
        }

        error('Unknown action', 404);
    }

    // =================== POST ===================
    if ($method !== 'POST') error('Method not allowed', 405);
    needFinanceRole($action === 'claim_member_number' ? ['insurance_officer'] : []);
    $ar = fin_acct($pdo, 'ar_general');

    // Member number lives on the patient's insurance record, so the billing API cannot change it. Same lock as billing edits:
    // inside a submitted claim only an admin edits directly, everyone else raises an edit request.
    if ($action === 'claim_member_number') {
        $iid = (int)($input['invoice_id'] ?? 0); $no = trim((string)($input['member_number'] ?? ''));
        if ($no === '') error('Give the member number', 422);
        $q = $pdo->prepare("SELECT patient_id, insurance_id, created_at FROM invoices WHERE id = ?"); $q->execute([$iid]);
        $inv = $q->fetch(); if (!$inv) error('Invoice not found', 404);
        $c = $pdo->prepare("SELECT claim_no FROM insurance_claims WHERE insurance_id = ? AND period_ym = DATE_FORMAT(?, '%Y-%m') AND status = 'submitted'");
        $inv['insurance_id'] = insuranceCompanyOf($pdo, $inv['insurance_id']);
        $c->execute([$inv['insurance_id'], $inv['created_at']]);
        if ($c->fetchColumn() && !in_array('admin', $_SESSION['user_data']['role_slugs'] ?? [], true)) {
            http_response_code(409);
            echo json_encode(['success' => false, 'code' => 'needs_edit_request', 'message' => 'This invoice is in a submitted claim. Raise an edit request on the Approvals page.']);
            exit();
        }
        $m = $pdo->prepare("SELECT id, member_number FROM patient_insurance WHERE patient_id = ? AND company_id = ? ORDER BY is_primary DESC, id DESC LIMIT 1");
        $m->execute([$inv['patient_id'], $inv['insurance_id']]);
        $pi = $m->fetch(); if (!$pi) error('No insurance record found for this patient and insurer', 404);
        $pdo->prepare("UPDATE patient_insurance SET member_number = ? WHERE id = ?")->execute([$no, $pi['id']]);
        fin_log($pdo, 'EDIT', 'invoices', 'Member number changed: ' . ($pi['member_number'] ?: '-') . ' -> ' . $no, $iid, null, ['member_number' => [$pi['member_number'], $no]]);
        success(null, 'Member number updated');
    }

    // Estimate of debts that will not be collected: Dr Bad debts expense, Cr Allowance
    if ($action === 'provision') {
        $amount = amountOf($input['amount'] ?? 0); $date = dateOf($input['entry_date'] ?? null, 'Date');
        $reason = trim($input['reason'] ?? '') ?: null;
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO finance_writeoffs (kind, amount, entry_date, reason, created_by) VALUES ('provision',?,?,?,?)")->execute([$amount, $date, $reason, $uid]);
        $id = (int)$pdo->lastInsertId();
        fin_post($pdo, ['date' => $date, 'type' => 'bad_debt_provision', 'ref_id' => $id, 'description' => 'Provision for doubtful debts' . ($reason ? " - $reason" : ''), 'lines' => [
            ['account_id' => fin_acct($pdo, 'bad_debt_exp'), 'debit' => $amount, 'desc' => 'Bad debts provision'],
            ['account_id' => fin_acct($pdo, 'allowance'), 'credit' => $amount, 'desc' => 'Allowance for doubtful accounts'],
        ]]);
        $pdo->commit();
        fin_log($pdo, 'CREATE', 'finance', 'Bad debt provision ' . number_format($amount), $id);
        success(['id' => $id], 'Provision recorded', 201);
    }

    // Write off all or part of one patient's balance: the allowance absorbs it first, the rest is expensed
    if ($action === 'writeoff') {
        $pid = (int)($input['patient_id'] ?? 0);
        $amount = amountOf($input['amount'] ?? 0); $date = dateOf($input['entry_date'] ?? null, 'Date');
        $reason = trim($input['reason'] ?? ''); if ($reason === '') error('Give the reason for the write-off', 422);
        $pdo->beginTransaction();
        $acc = fin_private_ar_acct($pdo);
        $exists = $pdo->prepare("SELECT 1 FROM patients WHERE id = ?"); $exists->execute([$pid]);
        if (!$exists->fetchColumn()) { $pdo->rollBack(); error('Patient not found', 404); }
        $pdo->prepare("SELECT id FROM chart_of_accounts WHERE id = ? FOR UPDATE")->execute([$acc]); // two write-offs at once must not both pass the balance check
        $o = $pdo->prepare("SELECT COALESCE(SUM(balance), 0) - COALESCE((SELECT SUM(amount) FROM finance_writeoffs WHERE patient_id = ? AND kind = 'writeoff' AND status <> 'voided'), 0) FROM invoices WHERE patient_id = ? AND status NOT IN ('cancelled','paid') AND balance > 0");
        $o->execute([$pid, $pid]);
        $owed = round((float)$o->fetchColumn(), 2);
        if ($amount > $owed + 0.005) { $pdo->rollBack(); error('Amount is more than the patient owes (' . number_format($owed, 2) . ')', 422); }
        $pdo->prepare("INSERT INTO finance_writeoffs (kind, patient_id, amount, entry_date, reason, created_by) VALUES ('writeoff',?,?,?,?,?)")->execute([$pid, $amount, $date, $reason, $uid]);
        $id = (int)$pdo->lastInsertId();
        $allowAcc = fin_acct($pdo, 'allowance');
        $fromAllowance = round(min($amount, max(0, -balanceOf($pdo, $allowAcc))), 2);
        $lines = [['account_id' => $acc, 'credit' => $amount, 'desc' => "Written off: $reason"]];
        if ($fromAllowance > 0) $lines[] = ['account_id' => $allowAcc, 'debit' => $fromAllowance, 'desc' => 'Allowance used'];
        if ($amount - $fromAllowance > 0) $lines[] = ['account_id' => fin_acct($pdo, 'bad_debt_exp'), 'debit' => round($amount - $fromAllowance, 2), 'desc' => 'Bad debt expense'];
        fin_post($pdo, ['date' => $date, 'type' => 'bad_debt_writeoff', 'ref_id' => $id, 'description' => "Patient debt written off - $reason", 'lines' => $lines]);
        $pdo->commit();
        fin_log($pdo, 'CREATE', 'finance', 'Patient debt written off ' . number_format($amount), $id);
        success(['id' => $id, 'from_allowance' => $fromAllowance], 'Write-off recorded', 201);
    }

    if ($action === 'writeoff_void') {
        $id = (int)($input['id'] ?? 0);
        $q = $pdo->prepare("SELECT kind, status FROM finance_writeoffs WHERE id = ?"); $q->execute([$id]);
        $w = $q->fetch(); if (!$w) error('Record not found', 404); if ($w['status'] === 'voided') error('Already voided', 409);
        $pdo->beginTransaction();
        fin_unpost($pdo, $w['kind'] === 'provision' ? 'bad_debt_provision' : 'bad_debt_writeoff', $id, 'Voided');
        $pdo->prepare("UPDATE finance_writeoffs SET status = 'voided' WHERE id = ?")->execute([$id]);
        $pdo->commit();
        success(null, 'Voided');
    }

    // ---- insurance claims ----
    if ($action === 'claim_create') {
        $ins = (int)($input['insurance_id'] ?? 0); $ym = (string)($input['period'] ?? '');
        if (!$ins || $ins === FIN_PRIVATE_INSURER || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $ym)) error('Choose an insurer and a month', 422);
        if ($ym > date('Y-m')) error('The month has not started yet', 422);
        $inv = claimInvoices($pdo, $ins, $ym);
        $amount = round(array_sum(array_column($inv, 'insurer_share')), 2);
        if ($amount <= 0) error('This insurer has no insured invoices in that month', 422);
        $date = dateOf($input['submitted_date'] ?? null, 'Submitted date');
        $dup = $pdo->prepare("SELECT id FROM insurance_claims WHERE insurance_id = ? AND period_ym = ?"); $dup->execute([$ins, $ym]);
        if ($dup->fetchColumn()) error('A claim for this insurer and month already exists', 409);
        $no = 'CLM-' . str_replace('-', '', $ym) . '-' . str_pad((string)$ins, 3, '0', STR_PAD_LEFT);
        $pdo->prepare("INSERT INTO insurance_claims (claim_no, insurance_id, period_ym, claimed_amount, submitted_date, notes, created_by) VALUES (?,?,?,?,?,?,?)")
            ->execute([$no, $ins, $ym, $amount, $date, trim($input['notes'] ?? '') ?: null, $uid]);
        $id = (int)$pdo->lastInsertId();
        fin_log($pdo, 'CREATE', 'finance', "Insurance claim $no for " . number_format($amount), $id);
        success(['id' => $id, 'claim_no' => $no, 'claimed_amount' => $amount], 'Claim created', 201);
    }

    // The insurer sent part of a claim back. Recorded as open: nothing moves in the ledger until it is decided.
    if ($action === 'claim_reject') {
        $q = $pdo->prepare("SELECT * FROM insurance_claims WHERE id = ?"); $q->execute([(int)($input['claim_id'] ?? 0)]);
        $c = $q->fetch(); if (!$c) error('Claim not found', 404); if ($c['status'] === 'cancelled') error('Claim is cancelled', 409);
        $invId = (int)($input['invoice_id'] ?? 0);
        $share = null;
        foreach (claimInvoices($pdo, (int)$c['insurance_id'], $c['period_ym']) as $i) if ((int)$i['id'] === $invId) $share = (float)$i['insurer_share'];
        if ($share === null) error('That invoice is not part of this claim', 422);
        $amount = amountOf($input['amount'] ?? 0); $date = dateOf($input['rejected_date'] ?? null, 'Rejection date');
        $reason = trim($input['reason'] ?? ''); if ($reason === '') error('Give the insurer\'s reason', 422);
        $used = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM insurance_claim_rejections WHERE invoice_id = ? AND status <> 'voided'"); $used->execute([$invId]);
        if ($amount + (float)$used->fetchColumn() > $share + 0.005) error('Rejections on this invoice would be more than the insurer\'s share (' . number_format($share, 2) . ')', 422);
        $pdo->prepare("INSERT INTO insurance_claim_rejections (claim_id, invoice_id, amount, reason, rejected_date, created_by) VALUES (?,?,?,?,?,?)")->execute([$c['id'], $invId, $amount, $reason, $date, $uid]);
        $id = (int)$pdo->lastInsertId();
        fin_log($pdo, 'CREATE', 'finance', "Rejection on claim {$c['claim_no']}: " . number_format($amount), $id);
        success(['id' => $id], 'Rejection recorded', 201);
    }

    // Decide an open rejection: send it back to the insurer, bill the patient, or write it off
    if ($action === 'claim_resolve') {
        $id = (int)($input['id'] ?? 0); $decision = (string)($input['decision'] ?? '');
        if (!in_array($decision, ['resubmit', 'bill_patient', 'write_off'], true)) error('Choose resubmit, bill the patient or write off', 422);
        $date = dateOf($input['decided_date'] ?? null, 'Decision date');
        $pdo->beginTransaction();
        $q = $pdo->prepare("SELECT r.*, c.insurance_id, c.claim_no, v.patient_id, v.invoice_no FROM insurance_claim_rejections r JOIN insurance_claims c ON c.id = r.claim_id
                            LEFT JOIN invoices v ON v.id = r.invoice_id WHERE r.id = ? FOR UPDATE");
        $q->execute([$id]);
        $r = $q->fetch();
        if (!$r) { $pdo->rollBack(); error('Rejection not found', 404); }
        if ($r['status'] !== 'open') { $pdo->rollBack(); error('This rejection has already been decided', 409); }
        $amount = (float)$r['amount']; $insAcc = fin_insurer_acct($pdo, (int)$r['insurance_id']);
        if ($decision === 'resubmit') {
            $status = 'resubmitted';
        } else {
            $to = $decision === 'write_off' ? fin_acct($pdo, 'ins_writeoff') : fin_patient_acct($pdo, (int)$r['patient_id']);
            $label = $decision === 'write_off' ? 'written off' : 'billed to patient';
            fin_post($pdo, ['date' => $date, 'type' => 'claim_rejection', 'ref_id' => $id, 'description' => "Claim {$r['claim_no']} rejection $label - {$r['invoice_no']}", 'lines' => [
                ['account_id' => $to, 'debit' => $amount, 'desc' => "Rejected by insurer, $label: {$r['reason']}"],
                ['account_id' => $insAcc, 'credit' => $amount, 'desc' => "Claim {$r['claim_no']} rejection {$r['invoice_no']}"],
            ]]);
            $status = $decision === 'write_off' ? 'written_off' : 'billed_patient';
        }
        $pdo->prepare("UPDATE insurance_claim_rejections SET status = ?, decided_date = ? WHERE id = ?")->execute([$status, $date, $id]);
        $pdo->commit();
        fin_log($pdo, 'UPDATE', 'finance', "Claim {$r['claim_no']} rejection decided: $decision", $id);
        success(null, $decision === 'resubmit' ? 'Marked as resubmitted' : 'Decision posted');
    }

    if ($action === 'claim_reject_void') {
        $id = (int)($input['id'] ?? 0);
        $q = $pdo->prepare("SELECT status FROM insurance_claim_rejections WHERE id = ?"); $q->execute([$id]);
        $st = $q->fetchColumn(); if (!$st) error('Rejection not found', 404); if ($st === 'voided') error('Already voided', 409);
        $pdo->beginTransaction();
        fin_unpost($pdo, 'claim_rejection', $id, 'Rejection voided');
        $pdo->prepare("UPDATE insurance_claim_rejections SET status = 'voided' WHERE id = ?")->execute([$id]);
        $pdo->commit();
        success(null, 'Rejection voided');
    }

    if ($action === 'claim_cancel') {
        $id = (int)($input['id'] ?? 0);
        $n = $pdo->prepare("SELECT COUNT(*) FROM insurance_claim_rejections WHERE claim_id = ? AND status <> 'voided'"); $n->execute([$id]);
        if ((int)$n->fetchColumn()) error('Void the rejections first', 409);
        $pdo->prepare("UPDATE insurance_claims SET status = 'cancelled' WHERE id = ?")->execute([$id]);
        success(null, 'Claim cancelled');
    }

    error('Unknown action', 404);
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error($e->getMessage(), 409); // closed period, unmapped account
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('receivables: ' . $e->getMessage());
    error('Server error', 500);
}

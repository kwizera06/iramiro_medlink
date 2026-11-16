<?php
// includes/finance_posting.php
// The only place that writes to the general ledger (journal_entries / journal_entry_lines).
// Handlers call fin_post_invoice / fin_sync_payment / fin_post_* instead of inserting journal rows themselves.
// Derived entries are keyed by (reference_type, reference_id) so every function here is idempotent.
require_once __DIR__ . '/finance_schema.php';

const FIN_PRIVATE_INSURER = 14; // insurance_companies.id "PRIVE" in this database (13 in the St Michel one): never posts insurer share

// ---------- rounding (one rule for the whole system: whole RWF, half-up) ----------

function fin_round($amount): float {
    return (float)floor((float)$amount + 0.5 + 1e-9);
}

// Split an invoice total between patient and insurer. Patient share is rounded; the insurer gets the remainder.
function fin_split($total, $copayPct): array {
    $total = (float)$total;
    $pct = (float)$copayPct;
    $patient = $pct < 100 ? fin_round($total * $pct / 100) : $total;
    return ['patient' => $patient, 'insurer' => round($total - $patient, 2)];
}

// Self-check: php includes/finance_posting.php --selfcheck  (6,328 RWF at 15% copay must give 949 + 5,379)
if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__ && in_array('--selfcheck', $argv ?? [], true)) {
    $r = fin_split(6328, 15);
    assert($r['patient'] === 949.0 && $r['insurer'] === 5379.0, 'fin_split 6328 @15%');
    assert(fin_round(5378.8) === 5379.0 && fin_round(0.5) === 1.0 && fin_round(2.4) === 2.0, 'fin_round');
    echo "finance_posting selfcheck ok: patient {$r['patient']} + insurer {$r['insurer']}
";
}

// ---------- accounts ----------

function fin_map(PDO $pdo, bool $reload = false): array {
    static $map = null;
    if ($map === null || $reload) {
        fin_boot($pdo);
        $map = [];
        foreach ($pdo->query("SELECT map_key, account_id FROM finance_account_map") as $r) $map[$r['map_key']] = (int)$r['account_id'];
    }
    return $map;
}

function fin_acct(PDO $pdo, string $key): int {
    $m = fin_map($pdo);
    if (empty($m[$key])) throw new RuntimeException("No account mapped for '$key' (Finance > Account Mapping)");
    return $m[$key];
}

// No per-patient receivable accounts: every self-pay balance posts to the single 'Receivable - Private' account (patient detail comes from invoices).
function fin_patient_acct(PDO $pdo, int $patientId): int {
    return fin_private_ar_acct($pdo);
}

function fin_insurer_acct(PDO $pdo, int $insuranceId): int {
    $q = $pdo->prepare("SELECT id FROM chart_of_accounts WHERE linked_insurance_id = ? AND parent_id = ? LIMIT 1");
    $q->execute([$insuranceId, fin_acct($pdo, 'ar_general')]);
    if ($id = $q->fetchColumn()) return (int)$id;
    $c = $pdo->prepare("SELECT name FROM insurance_companies WHERE id = ?");
    $c->execute([$insuranceId]);
    if ($name = $c->fetchColumn()) {
        $pdo->prepare("INSERT INTO chart_of_accounts (account_code, account_name, account_type, parent_id, status, linked_insurance_id, is_default, has_sub) VALUES (?,?,?,?, 'active', ?, 0, 0)")
            ->execute(['1120-I' . $insuranceId, 'Receivable - ' . $name, 'asset', fin_acct($pdo, 'ar_general'), $insuranceId]);
        return (int)$pdo->lastInsertId();
    }
    return fin_acct($pdo, 'ar_general');
}

function fin_supplier_acct(PDO $pdo, int $supplierId): int {
    if ($supplierId) {
        $q = $pdo->prepare("SELECT id FROM chart_of_accounts WHERE linked_supplier_id = ? LIMIT 1");
        $q->execute([$supplierId]);
        if ($id = $q->fetchColumn()) return (int)$id;
        // A supplier created before payable accounts existed gets its own now, so what we owe it (and any overpayment check) is never mixed into the control account.
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

// Cash / bank / mobile-money accounts (children of Cash & Cash Equivalents)
function fin_cash_accounts(PDO $pdo): array {
    return $pdo->query("SELECT id, account_code, account_name FROM chart_of_accounts WHERE parent_id = 3 AND status = 'active' ORDER BY account_code")->fetchAll();
}

function fin_method_key(string $method): string {
    return ['cash' => 'cash', 'card' => 'bank', 'bank_transfer' => 'bank', 'check' => 'bank', 'insurance' => 'bank',
            'mobile_money' => 'momo', 'deposit' => 'deposits', 'mixed' => 'cash'][$method] ?? 'cash';
}

// ---------- periods ----------

function fin_period_closed(PDO $pdo, string $date): bool {
    $q = $pdo->prepare("SELECT status FROM finance_periods WHERE period_ym = ?");
    $q->execute([substr($date, 0, 7)]);
    return $q->fetchColumn() === 'closed';
}

// ---------- core posting ----------

function fin_ref_prefix(?string $type): string {
    return ['invoice' => 'INV', 'invoice_adj' => 'IAD', 'payment_record' => 'PAY', 'payment_reversal' => 'PRV', 'payroll' => 'PRL',
            'payroll_payment' => 'PRP', 'pos_sale' => 'POS', 'dispense_cogs' => 'COG', 'expense' => 'EXP', 'supplier_payment' => 'SUP',
            'deposit' => 'DEP', 'deposit_refund' => 'DRF', 'requisition_issue' => 'RQI', 'stock_adjust' => 'SAD', 'reversal' => 'REV',
            'stock_opening' => 'OPS', 'grn' => 'GRN', 'stock_receipt' => 'RCV', 'stock_issue' => 'ISS', 'stock_transfer' => 'TRF', 'stock_expiry' => 'SXP', 'expense_management' => 'EXP', 'expense_payment' => 'EPM',
            'pharmacy_receipt' => 'PGR', 'supplier_invoice' => 'SIN', 'remittance' => 'RMT', 'maintenance' => 'MNT', 'bank_rec_line' => 'BRL', 'supplier_return' => 'SRN', 'asset_open' => 'AST', 'asset_capitalize' => 'AST', 'asset_depreciation' => 'DEP', 'asset_disposal' => 'AST', 'asset_revaluation' => 'AST', 'insurance_settlement' => 'IST', 'year_end_close' => 'YEC', 'bad_debt_provision' => 'BDP', 'bad_debt_writeoff' => 'BDW', 'claim_rejection' => 'CLR',
           ][$type ?? ''] ?? 'JNL';
}

// $lines: [['account_id'=>, 'debit'=>, 'credit'=>, 'desc'=>]]; zero lines are dropped
function fin_clean_lines(array $lines): array {
    $out = [];
    foreach ($lines as $l) {
        $d = round((float)($l['debit'] ?? 0), 2);
        $c = round((float)($l['credit'] ?? 0), 2);
        if ($d < 0 || $c < 0) throw new InvalidArgumentException('Negative journal amount');
        if ($d == 0 && $c == 0) continue;
        $out[] = ['account_id' => (int)$l['account_id'], 'debit' => $d, 'credit' => $c, 'desc' => (string)($l['desc'] ?? $l['line_description'] ?? ''), 'dept' => !empty($l['dept']) ? (int)$l['dept'] : null];
    }
    return $out;
}

/**
 * Post a balanced journal entry.
 * $o: date, description, type, ref_id, lines, replace (rewrite the existing entry of the same type/ref_id), reference (optional),
 *     dept (department / cost centre for lines that do not carry their own 'dept')
 * Throws on unbalanced lines, unknown accounts or a closed period. Joins an open transaction or runs its own.
 */
function fin_post(PDO $pdo, array $o): int {
    fin_boot($pdo);
    $date  = substr((string)($o['date'] ?? date('Y-m-d')), 0, 10);
    $lines = fin_clean_lines($o['lines'] ?? []);
    if (count($lines) < 2) throw new InvalidArgumentException('A journal entry needs at least two lines');
    $dr = array_sum(array_column($lines, 'debit')); $cr = array_sum(array_column($lines, 'credit'));
    if (abs($dr - $cr) > 0.01) throw new InvalidArgumentException("Journal not balanced: Dr $dr / Cr $cr");
    if (fin_period_closed($pdo, $date)) throw new RuntimeException('Period ' . substr($date, 0, 7) . ' is closed');

    $ids = array_values(array_unique(array_column($lines, 'account_id')));
    $in = implode(',', array_fill(0, count($ids), '?'));
    $chk = $pdo->prepare("SELECT COUNT(*) FROM chart_of_accounts WHERE id IN ($in)");
    $chk->execute($ids);
    if ((int)$chk->fetchColumn() !== count($ids)) throw new InvalidArgumentException('Journal uses an account that is not in the chart of accounts');

    $type  = $o['type'] ?? null;
    $refId = isset($o['ref_id']) ? (string)$o['ref_id'] : null;
    $uid   = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $desc  = (string)($o['description'] ?? '');

    $own = !$pdo->inTransaction();
    if ($own) $pdo->beginTransaction();
    try {
        $jeId = 0;
        if (!empty($o['replace']) && $type && $refId !== null) {
            $q = $pdo->prepare("SELECT id, entry_date FROM journal_entries WHERE reference_type = ? AND reference_id = ? ORDER BY id");
            $q->execute([$type, $refId]);
            foreach ($q->fetchAll() as $i => $row) {
                if (fin_period_closed($pdo, $row['entry_date'])) throw new RuntimeException('Period ' . substr($row['entry_date'], 0, 7) . ' is closed');
                if ($i === 0) {
                    $jeId = (int)$row['id'];
                    $pdo->prepare("UPDATE journal_entries SET entry_date = ?, description = ? WHERE id = ?")->execute([$date, $desc, $jeId]);
                    $pdo->prepare("DELETE FROM journal_entry_lines WHERE journal_entry_id = ?")->execute([$jeId]);
                } else { // stray duplicate of a derived entry
                    $pdo->prepare("DELETE FROM journal_entry_lines WHERE journal_entry_id = ?")->execute([$row['id']]);
                    $pdo->prepare("DELETE FROM journal_entries WHERE id = ?")->execute([$row['id']]);
                }
            }
        }
        if (!$jeId) {
            $pdo->prepare("INSERT INTO journal_entries (entry_date, reference, description, reference_type, reference_id, posted, posted_at, created_by) VALUES (?,?,?,?,?,1,NOW(),?)")
                ->execute([$date, $o['reference'] ?? null, $desc, $type, $refId, $uid]);
            $jeId = (int)$pdo->lastInsertId();
            if (empty($o['reference'])) {
                $pdo->prepare("UPDATE journal_entries SET reference = ? WHERE id = ?")
                    ->execute([sprintf('%s-%s-%05d', fin_ref_prefix($type), date('Ymd', strtotime($date)), $jeId), $jeId]);
            }
        }
        $ins = $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit, department_id) VALUES (?,?,?,?,?,?)");
        $dept = !empty($o['dept']) ? (int)$o['dept'] : null;
        foreach ($lines as $l) $ins->execute([$jeId, $l['account_id'], $l['desc'], $l['debit'], $l['credit'], $l['dept'] ?? $dept]);
        if ($own) $pdo->commit();
        return $jeId;
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function fin_entries(PDO $pdo, $types, $refId): array {
    $types = (array)$types;
    $in = implode(',', array_fill(0, count($types), '?'));
    $q = $pdo->prepare("SELECT * FROM journal_entries WHERE reference_type IN ($in) AND reference_id = ? ORDER BY id");
    $q->execute(array_merge($types, [(string)$refId]));
    return $q->fetchAll();
}

// account_id => net debit, for all entries of these types/ref
function fin_net(PDO $pdo, $types, $refId): array {
    $types = (array)$types;
    $in = implode(',', array_fill(0, count($types), '?'));
    $q = $pdo->prepare("SELECT jl.account_id, SUM(jl.debit - jl.credit) AS n FROM journal_entries je JOIN journal_entry_lines jl ON jl.journal_entry_id = je.id
                        WHERE je.reference_type IN ($in) AND je.reference_id = ? GROUP BY jl.account_id");
    $q->execute(array_merge($types, [(string)$refId]));
    $out = [];
    foreach ($q->fetchAll() as $r) if (abs($r['n']) >= 0.005) $out[(int)$r['account_id']] = round((float)$r['n'], 2);
    return $out;
}

function fin_lines_net(array $lines): array {
    $out = [];
    foreach (fin_clean_lines($lines) as $l) {
        $out[$l['account_id']] = round(($out[$l['account_id']] ?? 0) + $l['debit'] - $l['credit'], 2);
    }
    return array_filter($out, fn($v) => abs($v) >= 0.005);
}

function fin_nets_equal(array $a, array $b): bool {
    foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $k) {
        if (abs(($a[$k] ?? 0) - ($b[$k] ?? 0)) >= 0.005) return false;
    }
    return true;
}

// Remove the derived entries of a source record that no longer exists / was voided.
// Open period: the entry is deleted. Closed period: a reversing entry is posted instead.
function fin_unpost(PDO $pdo, $types, $refId, string $reason = 'Source record removed'): void {
    foreach (fin_entries($pdo, $types, $refId) as $e) {
        if (fin_period_closed($pdo, $e['entry_date'])) {
            if (!fin_entries($pdo, 'reversal', $e['id'])) fin_reverse_entry($pdo, (int)$e['id'], null, $reason);
        } else {
            $pdo->prepare("DELETE FROM journal_entry_lines WHERE journal_entry_id = ?")->execute([$e['id']]);
            $pdo->prepare("DELETE FROM journal_entries WHERE id = ?")->execute([$e['id']]);
        }
    }
}

// Post the mirror image of an entry (used for manual reversals and for closed periods)
function fin_reverse_entry(PDO $pdo, int $jeId, ?string $date = null, string $reason = ''): int {
    $q = $pdo->prepare("SELECT * FROM journal_entries WHERE id = ?");
    $q->execute([$jeId]);
    $e = $q->fetch();
    if (!$e) throw new RuntimeException('Journal entry not found');
    if ($e['reference_type'] === 'reversal') throw new RuntimeException('A reversal cannot be reversed');
    if (fin_entries($pdo, 'reversal', $jeId)) throw new RuntimeException('Entry is already reversed');
    $l = $pdo->prepare("SELECT account_id, line_description, debit, credit FROM journal_entry_lines WHERE journal_entry_id = ?");
    $l->execute([$jeId]);
    $lines = [];
    foreach ($l->fetchAll() as $r) $lines[] = ['account_id' => $r['account_id'], 'debit' => $r['credit'], 'credit' => $r['debit'], 'desc' => 'Reversal: ' . $r['line_description']];
    return fin_post($pdo, ['date' => $date ?: date('Y-m-d'), 'type' => 'reversal', 'ref_id' => $jeId, 'lines' => $lines,
        'description' => 'Reversal of ' . $e['reference'] . ($reason ? ' - ' . $reason : '')]);
}

// ---------- invoices ----------

function fin_copay(PDO $pdo, int $patientId, int $insuranceId): float {
    $q = $pdo->prepare("SELECT ip.copayment_percentage FROM patient_insurance pi JOIN insurance_packages ip ON ip.id = pi.package_id
                        WHERE pi.patient_id = ? AND pi.company_id = ? ORDER BY pi.is_primary DESC, pi.id LIMIT 1");
    $q->execute([$patientId, $insuranceId]);
    $v = $q->fetchColumn();
    if ($v === false || $v === null) {
        $q = $pdo->prepare("SELECT copayment_percentage FROM insurance_packages WHERE company_id = ? AND status = 'active' ORDER BY id LIMIT 1");
        $q->execute([$insuranceId]);
        $v = $q->fetchColumn();
    }
    return ($v === false || $v === null) ? 100.0 : (float)$v;
}

// Revenue account key for an invoice line. Most billed services share invoice_items.category = 'procedure',
// so the service catalogue speciality decides (consultation, nursing, hospitalization, imaging, lab, ...).
function fin_revenue_key(string $category, string $speciality): string {
    $keys = fin_category_keys();
    if ($category !== 'procedure' && $category !== '' && isset($keys[$category])) return $keys[$category];
    if ($speciality === 'consultation') return 'rev_consultation';
    if ($speciality === 'nursing') return 'rev_nursing';
    if ($speciality === 'hospitalization') return 'rev_accommodation';
    if (strpos($speciality, 'emergency') !== false) return 'rev_nursing';
    if (strpos($speciality, 'imaging') === 0 || strpos($speciality, 'radiolog') !== false) return 'rev_radiology';
    if (preg_match('/hematolog|bacteriolog|parasitolog|serolog|biochem|microbiolog|patholog|cytolog|histolog|virolog|laborator|urinalys|immuno/', $speciality)) return 'rev_lab';
    if (isset($keys[$category])) return $keys[$category];
    return $category === '' && $speciality === '' ? 'rev_other' : 'rev_procedure';
}

// The ledger lines an invoice should have right now. null = invoice does not exist; ['lines'=>[]] = nothing to post.
function fin_invoice_plan(PDO $pdo, int $invoiceId): ?array {
    $q = $pdo->prepare("SELECT i.*, DATE(i.created_at) AS d FROM invoices i WHERE i.id = ?");
    $q->execute([$invoiceId]);
    $inv = $q->fetch();
    if (!$inv) return null;

    $it = $pdo->prepare("SELECT LOWER(TRIM(COALESCE(ii.category,''))) AS c, LOWER(TRIM(COALESCE(sc.speciality,''))) AS s, SUM(ii.total) AS t, COUNT(*) AS n
                         FROM invoice_items ii LEFT JOIN service_catalog sc ON sc.id = ii.service_id WHERE ii.invoice_id = ? GROUP BY c, s");
    $it->execute([$invoiceId]);
    $cats = $it->fetchAll();
    $hasItems = $cats && array_sum(array_column($cats, 'n')) > 0;
    $subtotal = $hasItems ? round(array_sum(array_column($cats, 't')), 2) : round((float)$inv['subtotal'], 2);
    $discount = max(0, round((float)($inv['discount'] ?? 0), 2));
    $tax      = round((float)($inv['tax'] ?? 0), 2);
    $total    = round($subtotal - $discount + $tax, 2);
    $plan = ['inv' => $inv, 'date' => $inv['d'], 'total' => $total, 'patient_due' => 0.0, 'lines' => [], 'dept' => fin_invoice_dept($pdo, $inv)];
    if ($inv['status'] === 'cancelled' || $total <= 0) return $plan;

    $no = $inv['invoice_no'];
    $lines = [];
    // receivable: patient share and insurer share
    $patId = (int)$inv['patient_id'];
    $insId = (int)($inv['insurance_id'] ?? 0);
    $insured = $insId && $insId !== FIN_PRIVATE_INSURER && in_array($inv['payment_type'], ['insurance', 'mixed'], true);
    $copay = $insured ? fin_copay($pdo, $patId, $insId) : 100.0;
    $split = fin_split($total, $copay);
    $patientPart = $split['patient'];
    $plan['patient_due'] = $patientPart;
    if ($patientPart > 0) $lines[] = ['account_id' => fin_patient_acct($pdo, $patId), 'debit' => $patientPart, 'desc' => "Patient receivable $no" . ($copay < 100 ? " ($copay%)" : '')];
    if ($split['insurer'] > 0) $lines[] = ['account_id' => fin_insurer_acct($pdo, $insId), 'debit' => $split['insurer'], 'desc' => "Insurance receivable $no (" . (100 - $copay) . '%)'];
    if ($discount > 0) $lines[] = ['account_id' => fin_acct($pdo, 'discount'), 'debit' => $discount, 'desc' => "Discount $no"];
    // revenue by service category
    $rev = [];
    foreach ($cats as $c) { $k = fin_revenue_key($c['c'], $c['s']); $rev[$k] = ($rev[$k] ?? 0) + (float)$c['t']; }
    $rest = round($subtotal - array_sum($rev), 2);
    if (abs($rest) >= 0.005) $rev['rev_other'] = ($rev['rev_other'] ?? 0) + $rest;
    foreach ($rev as $k => $amt) {
        $amt = round($amt, 2);
        if ($amt > 0) $lines[] = ['account_id' => fin_acct($pdo, $k), 'credit' => $amt, 'desc' => "Revenue $no"];
        elseif ($amt < 0) $lines[] = ['account_id' => fin_acct($pdo, $k), 'debit' => -$amt, 'desc' => "Revenue adjustment $no"];
    }
    if ($tax > 0) $lines[] = ['account_id' => fin_acct($pdo, 'vat_out'), 'credit' => $tax, 'desc' => "VAT $no"];
    $plan['lines'] = $lines;
    $plan['description'] = 'Invoice #' . $no;
    return $plan;
}

// Department behind an invoice: its own, else the department of its first payment
function fin_invoice_dept(PDO $pdo, array $inv): ?int {
    if ((int)($inv['department_id'] ?? 0)) return (int)$inv['department_id'];
    $q = $pdo->prepare("SELECT department_id FROM payment_records WHERE invoice_id = ? AND department_id > 0 ORDER BY id LIMIT 1");
    $q->execute([$inv['id']]);
    return (int)$q->fetchColumn() ?: null;
}

// Fill in the department on derived entries posted before it was known. Amounts are not touched;
// a closed month refuses line updates, and an untagged line there is only a reporting gap, so it is skipped.
function fin_tag_dept(PDO $pdo, $types, $refId, ?int $dept): void {
    if (!$dept) return;
    $types = (array)$types;
    $in = implode(',', array_fill(0, count($types), '?'));
    try {
        $pdo->prepare("UPDATE journal_entry_lines jl JOIN journal_entries je ON je.id = jl.journal_entry_id SET jl.department_id = ?
                       WHERE je.reference_type IN ($in) AND je.reference_id = ? AND jl.department_id IS NULL")->execute(array_merge([$dept], $types, [(string)$refId]));
    } catch (PDOException $e) { /* closed period */ }
}

// What an insured invoice's balance / status should be on the patient share the ledger uses (fin_invoice_plan),
// or null when it is already right, not insured, or the patient has no policy with the invoiced insurer
// (then the copay is only a guess and a person has to decide). Differences under 1 RWF are the old cents-based rounding.
// Money already collected is never touched: paid above the share = surplus, which fin_sync_payment books as a deposit.
function fin_invoice_balance_target(PDO $pdo, array $plan): ?array {
    $inv = $plan['inv'];
    $insId = (int)($inv['insurance_id'] ?? 0);
    if (!$insId || $insId === FIN_PRIVATE_INSURER || !in_array($inv['payment_type'], ['insurance', 'mixed'], true)
        || !in_array($inv['status'], ['issued', 'partial', 'paid'], true) || $plan['total'] <= 0) return null;
    $has = $pdo->prepare("SELECT 1 FROM patient_insurance pi JOIN insurance_packages ip ON ip.id = pi.package_id WHERE pi.patient_id = ? AND pi.company_id = ? LIMIT 1");
    $has->execute([$inv['patient_id'], $insId]);
    if (!$has->fetchColumn()) return null;
    $paid = (float)$inv['paid_amount'];
    $balance = max(0, round($plan['patient_due'] - $paid, 2));
    if (abs($balance - (float)$inv['balance']) < 1) return null;
    return ['old' => (float)$inv['balance'], 'new' => $balance, 'status' => $balance <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'issued'),
            'surplus' => max(0, round($paid - $plan['patient_due'], 2))];
}

// Keep invoices.balance / status on the same patient share as the ledger, so a copay change
// (patient moved to another package, package copay edited) can never leave the two disagreeing.
function fin_sync_invoice_balance(PDO $pdo, array $plan): ?array {
    $t = fin_invoice_balance_target($pdo, $plan);
    if (!$t) return null;
    $id = $plan['inv']['id'];
    $pdo->prepare("UPDATE invoices SET balance = ?, status = ? WHERE id = ?")->execute([$t['new'], $t['status'], $id]);
    // payments split receivable / deposit by the patient share, so re-derive them against the new share
    $p = $pdo->prepare("SELECT id FROM payment_records WHERE invoice_id = ? AND amount_paid <> 0 ORDER BY id");
    $p->execute([$id]);
    foreach ($p->fetchAll(PDO::FETCH_COLUMN) as $pid) fin_sync_payment($pdo, (int)$pid);
    return $t;
}

// After a patient's package or a package's copay changed: re-derive that patient's / package's insured invoices.
function fin_resync_insured_invoices(PDO $pdo, ?int $patientId = null, ?int $packageId = null): int {
    $q = $pdo->prepare("SELECT DISTINCT i.id FROM invoices i
                        WHERE i.payment_type IN ('insurance','mixed') AND i.status IN ('issued','partial','paid')
                          AND (? IS NOT NULL AND i.patient_id = ?
                               OR ? IS NOT NULL AND i.patient_id IN (SELECT patient_id FROM patient_insurance WHERE package_id = ?))");
    $q->execute([$patientId, $patientId, $packageId, $packageId]);
    $n = 0;
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $id) { fin_post_invoice_safe($pdo, (int)$id); $n++; }
    return $n;
}

// Make the ledger match the invoice. Safe to call any number of times.
function fin_post_invoice(PDO $pdo, int $invoiceId): ?int {
    $plan = fin_invoice_plan($pdo, $invoiceId);
    if (!$plan) return null;
    fin_sync_invoice_balance($pdo, $plan);
    $want = fin_lines_net($plan['lines']);
    $have = fin_net($pdo, ['invoice', 'invoice_adj'], $invoiceId);
    $orig = fin_entries($pdo, 'invoice', $invoiceId);
    if (fin_nets_equal($want, $have)) { fin_tag_dept($pdo, ['invoice', 'invoice_adj'], $invoiceId, $plan['dept']); return $orig ? (int)$orig[0]['id'] : null; }

    $hasAdj = (bool)fin_entries($pdo, 'invoice_adj', $invoiceId);
    $inPlace = !$hasAdj && (($orig && !fin_period_closed($pdo, $orig[0]['entry_date'])) || (!$orig && !fin_period_closed($pdo, $plan['date'])));
    if ($inPlace) {
        if (!$plan['lines']) { fin_unpost($pdo, 'invoice', $invoiceId); return null; }
        return fin_post($pdo, ['date' => $orig ? $orig[0]['entry_date'] : $plan['date'], 'type' => 'invoice', 'ref_id' => $invoiceId, 'dept' => $plan['dept'],
            'description' => $plan['description'], 'lines' => $plan['lines'], 'replace' => true]);
    }
    // closed period: post only the difference, dated today
    $delta = [];
    foreach (array_unique(array_merge(array_keys($want), array_keys($have))) as $acc) {
        $d = round(($want[$acc] ?? 0) - ($have[$acc] ?? 0), 2);
        if (abs($d) >= 0.005) $delta[] = ['account_id' => $acc, 'debit' => $d > 0 ? $d : 0, 'credit' => $d < 0 ? -$d : 0, 'desc' => 'Invoice adjustment'];
    }
    return $delta ? fin_post($pdo, ['type' => 'invoice_adj', 'ref_id' => $invoiceId, 'lines' => $delta, 'dept' => $plan['dept'],
        'description' => 'Adjustment to invoice #' . ($plan['inv']['invoice_no'] ?? $invoiceId)]) : null;
}

// Call BEFORE deleting an invoice (and its payment_records): takes its entries out of the ledger.
function fin_void_invoice(PDO $pdo, int $invoiceId): void {
    $p = $pdo->prepare("SELECT id FROM payment_records WHERE invoice_id = ?");
    $p->execute([$invoiceId]);
    foreach ($p->fetchAll(PDO::FETCH_COLUMN) as $pid) fin_unpost($pdo, ['payment_record', 'payment_reversal'], $pid, 'Invoice removed');
    fin_unpost($pdo, ['invoice', 'invoice_adj'], $invoiceId, 'Invoice removed');
}

// ---------- payments ----------

// Make the ledger match a payment_records row: completed => cash in / AR down, reversed or failed => mirrored out.
// A negative amount_paid is a refund (cash out / AR up).
function fin_sync_payment(PDO $pdo, int $paymentId): void {
    $q = $pdo->prepare("SELECT pr.*, i.invoice_no, i.insurance_id, i.payment_type FROM payment_records pr LEFT JOIN invoices i ON i.id = pr.invoice_id WHERE pr.id = ?");
    $q->execute([$paymentId]);
    $p = $q->fetch();
    if (!$p) { fin_unpost($pdo, ['payment_record', 'payment_reversal'], $paymentId, 'Payment removed'); return; }

    $amount = round((float)$p['amount_paid'], 2);
    if ($amount == 0) return;
    $cash = fin_acct($pdo, fin_method_key($p['payment_method']));
    $insured = $p['payment_method'] === 'insurance' && (int)$p['insurance_id'] && (int)$p['insurance_id'] !== FIN_PRIVATE_INSURER;
    $ar = $insured ? fin_insurer_acct($pdo, (int)$p['insurance_id']) : fin_patient_acct($pdo, (int)$p['patient_id']);
    $no = $p['invoice_no'] ?: ('#' . $p['invoice_id']);
    $abs = abs($amount);
    $in = $amount > 0; // money in
    // Cash beyond what the patient owes on the invoice is a patient deposit (liability), not a cleared receivable.
    // Split by running total of this invoice's completed payments, so refunds unwind the deposit first.
    $arPart = $amount;
    if (!$insured && (int)$p['invoice_id'] && $p['payment_method'] !== 'insurance') {
        $due = (float)(fin_invoice_plan($pdo, (int)$p['invoice_id'])['patient_due'] ?? 0);
        $pq = $pdo->prepare("SELECT COALESCE(SUM(amount_paid),0) FROM payment_records WHERE invoice_id = ? AND status = 'completed' AND id < ?");
        $pq->execute([$p['invoice_id'], $paymentId]);
        $before = (float)$pq->fetchColumn();
        $clamp = fn($x) => min(max($x, 0), $due);
        $arPart = round($clamp($before + $amount) - $clamp($before), 2);
    }
    $depPart = round($amount - $arPart, 2);
    $lines = [['account_id' => $cash, 'debit' => $in ? $abs : 0, 'credit' => $in ? 0 : $abs, 'desc' => ($in ? 'Payment received ' : 'Refund paid ') . $no . ' (' . $p['payment_method'] . ')']];
    if (abs($arPart) >= 0.005) $lines[] = ['account_id' => $ar, 'debit' => $arPart < 0 ? -$arPart : 0, 'credit' => $arPart > 0 ? $arPart : 0, 'desc' => ($in ? 'Receivable cleared ' : 'Receivable reinstated ') . $no];
    if (abs($depPart) >= 0.005) $lines[] = ['account_id' => fin_acct($pdo, 'deposits'), 'debit' => $depPart < 0 ? -$depPart : 0, 'credit' => $depPart > 0 ? $depPart : 0, 'desc' => ($depPart > 0 ? 'Overpayment held as deposit ' : 'Deposit released ') . $no];
    $dept = (int)$p['department_id'] ?: null;
    fin_tag_dept($pdo, ['invoice', 'invoice_adj'], $p['invoice_id'], $dept);
    $main = fin_entries($pdo, 'payment_record', $paymentId);
    $rev  = fin_entries($pdo, 'payment_reversal', $paymentId);

    if ($p['status'] === 'completed') {
        if ($rev) fin_unpost($pdo, 'payment_reversal', $paymentId, 'Payment completed again');
        $have = fin_net($pdo, 'payment_record', $paymentId);
        $wantNet = fin_lines_net($lines); // empty = deposit moved to another invoice, no net ledger effect
        if (($wantNet || $main) && (!$main || !fin_nets_equal($have, $wantNet))) {
            fin_post($pdo, ['date' => $p['payment_date'], 'type' => 'payment_record', 'ref_id' => $paymentId, 'lines' => $lines, 'replace' => true, 'dept' => $dept,
                'description' => ($in ? 'Payment ' : 'Refund ') . $no . ($p['receipt_number'] ? ' - ' . $p['receipt_number'] : '')]);
        }
    } elseif ($main && !$rev) {
        $mirror = [];
        foreach ($lines as $l) $mirror[] = ['account_id' => $l['account_id'], 'debit' => $l['credit'], 'credit' => $l['debit'], 'desc' => 'Reversal: ' . $l['desc']];
        fin_post($pdo, ['type' => 'payment_reversal', 'ref_id' => $paymentId, 'lines' => $mirror, 'dept' => $dept,
            'description' => 'Payment ' . $p['status'] . ' - ' . $no . ($p['receipt_number'] ? ' - ' . $p['receipt_number'] : '')]);
    }
}

// ---------- safety net ----------

// Runs after every API write (api.php). Posts whatever a handler forgot; $days = 0 means everything (Finance > Integrity > Repair).
function fin_reconcile(PDO $pdo, int $days = 7, int $limit = 200): array {
    $done = ['invoices' => 0, 'payments' => 0, 'errors' => 0];
    try {
        fin_boot($pdo);
        $since = $days > 0 ? "AND i.created_at >= NOW() - INTERVAL " . (int)$days . " DAY" : '';
        $ids = $pdo->query("
            SELECT i.id FROM invoices i
            LEFT JOIN (SELECT je.reference_id AS rid, SUM(jl.debit) AS d FROM journal_entries je JOIN journal_entry_lines jl ON jl.journal_entry_id = je.id
                       WHERE je.reference_type = 'invoice' GROUP BY je.reference_id) x ON x.rid = CAST(i.id AS CHAR)
            WHERE i.status <> 'cancelled' AND i.total > 0 $since
              AND (x.rid IS NULL OR ABS(x.d - (COALESCE((SELECT SUM(ii.total) FROM invoice_items ii WHERE ii.invoice_id = i.id), i.subtotal) + COALESCE(i.tax,0))) > 0.01)
            ORDER BY i.id LIMIT " . (int)$limit)->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            try { fin_post_invoice($pdo, (int)$id); $done['invoices']++; }
            catch (Throwable $e) { $done['errors']++; error_log("fin_reconcile invoice $id: " . $e->getMessage()); }
        }
        $sinceP = $days > 0 ? "AND pr.created_at >= NOW() - INTERVAL " . (int)$days . " DAY" : '';
        $pids = $pdo->query("
            SELECT pr.id FROM payment_records pr
            WHERE pr.amount_paid <> 0 $sinceP
              AND ( (pr.status = 'completed' AND NOT EXISTS (SELECT 1 FROM journal_entries je WHERE je.reference_type = 'payment_record' AND je.reference_id = CAST(pr.id AS CHAR)))
                 OR (pr.status IN ('reversed','failed') AND EXISTS (SELECT 1 FROM journal_entries je WHERE je.reference_type = 'payment_record' AND je.reference_id = CAST(pr.id AS CHAR))
                                                       AND NOT EXISTS (SELECT 1 FROM journal_entries je WHERE je.reference_type = 'payment_reversal' AND je.reference_id = CAST(pr.id AS CHAR))) )
            ORDER BY pr.id LIMIT " . (int)$limit)->fetchAll(PDO::FETCH_COLUMN);
        foreach ($pids as $id) {
            try { fin_sync_payment($pdo, (int)$id); $done['payments']++; }
            catch (Throwable $e) { $done['errors']++; error_log("fin_reconcile payment $id: " . $e->getMessage()); }
        }
    } catch (Throwable $e) { $done['errors']++; error_log('fin_reconcile: ' . $e->getMessage()); }
    return $done;
}

// Re-derive every invoice and payment entry from the source tables (Finance > Integrity > Repair, "full rebuild").
function fin_rebuild_all(PDO $pdo): array {
    fin_boot($pdo);
    $r = ['invoices' => 0, 'payments' => 0, 'errors' => 0, 'messages' => []];
    // entries written by the removed record_payment actions that point at accounts which do not exist
    $bad = $pdo->query("SELECT DISTINCT je.id FROM journal_entries je JOIN journal_entry_lines jl ON jl.journal_entry_id = je.id LEFT JOIN chart_of_accounts c ON c.id = jl.account_id
                         WHERE c.id IS NULL AND je.reference_type IN ('payment','afrirem_payment','insurance_payment')")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($bad as $jid) {
        $pdo->prepare("DELETE FROM journal_entry_lines WHERE journal_entry_id = ?")->execute([$jid]);
        $pdo->prepare("DELETE FROM journal_entries WHERE id = ?")->execute([$jid]);
    }
    $r['legacy_removed'] = count($bad);
    foreach ($pdo->query("SELECT id FROM invoices ORDER BY id")->fetchAll(PDO::FETCH_COLUMN) as $id) {
        try { fin_post_invoice($pdo, (int)$id); $r['invoices']++; }
        catch (Throwable $e) { $r['errors']++; if (count($r['messages']) < 10) $r['messages'][] = "invoice $id: " . $e->getMessage(); }
    }
    foreach ($pdo->query("SELECT id FROM payment_records WHERE amount_paid <> 0 ORDER BY id")->fetchAll(PDO::FETCH_COLUMN) as $id) {
        try { fin_sync_payment($pdo, (int)$id); $r['payments']++; }
        catch (Throwable $e) { $r['errors']++; if (count($r['messages']) < 10) $r['messages'][] = "payment $id: " . $e->getMessage(); }
    }
    return $r;
}

// ---------- payroll ----------

// draft / processed => nothing (not authorised yet); approved => salary + employer contributions accrued;
// paid => also the salary payment out of cash / bank.
function fin_sync_payroll(PDO $pdo, int $recordId, ?int $payAccountId = null): void {
    $q = $pdo->prepare("SELECT pr.*, e.first_name, e.last_name, e.department_id AS emp_dept FROM payroll_records pr LEFT JOIN employees e ON e.id = pr.employee_id WHERE pr.id = ?");
    $q->execute([$recordId]);
    $r = $q->fetch();
    if (!$r || !in_array($r['status'], ['approved', 'paid'], true)) { fin_unpost($pdo, ['payroll', 'payroll_payment'], $recordId, 'Payroll record changed'); return; }

    $who = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: ('employee #' . $r['employee_id']);
    $gross = round((float)$r['gross_salary'], 2);
    $paye = round((float)$r['tpr_tax'], 2);
    $pension = round((float)$r['csr_deduction'] + (float)($r['maternity_deduction'] ?? 0), 2); // employee share to RSSB
    $employer = round((float)($r['employer_pension'] ?? 0) + (float)($r['employer_maternity'] ?? 0) + (float)($r['employer_hazard'] ?? 0), 2);
    $other = round((float)$r['mlbs_deduction'] + (float)$r['medical_deduction'] + (float)$r['mutuelle_deduction'], 2);
    $advance = round((float)$r['advance_deduction'], 2);
    $net = round($gross - $paye - $pension - $other - $advance, 2);
    $date = date('Y-m-t', strtotime($r['pay_period'] . '-01'));
    $lines = [
        ['account_id' => fin_acct($pdo, 'salary_exp'),     'debit'  => $gross,   'desc' => "Salary $who {$r['pay_period']}"],
        ['account_id' => fin_acct($pdo, 'employer_contrib_exp'), 'debit' => $employer, 'desc' => "Employer RSSB contributions $who"],
        ['account_id' => fin_acct($pdo, 'paye_payable'),   'credit' => $paye,    'desc' => "PAYE $who"],
        ['account_id' => fin_acct($pdo, 'pension_payable'),'credit' => round($pension + $employer, 2), 'desc' => "RSSB (employee + employer) $who"],
        ['account_id' => fin_acct($pdo, 'other_deductions_payable'), 'credit' => $other, 'desc' => "Medical / mutuelle deductions $who"],
        ['account_id' => fin_acct($pdo, 'staff_advance'),  'credit' => $advance, 'desc' => "Advance recovered $who"],
        ['account_id' => fin_acct($pdo, 'salary_payable'), 'credit' => max(0, $net), 'debit' => max(0, -$net), 'desc' => "Net pay $who"],
    ];
    if (!fin_nets_equal(fin_net($pdo, 'payroll', $recordId), fin_lines_net($lines))) {
        fin_post($pdo, ['date' => $date, 'type' => 'payroll', 'ref_id' => $recordId, 'lines' => $lines, 'replace' => true, 'dept' => (int)$r['emp_dept'] ?: null,
            'description' => "Payroll {$r['pay_period']} - $who"]);
    }

    if ($r['status'] === 'paid' && $net > 0) {
        $have = fin_net($pdo, 'payroll_payment', $recordId);
        $payAcc = $payAccountId;
        if (!$payAcc) foreach ($have as $acc => $v) if ($v < 0) $payAcc = $acc; // keep the account chosen when it was paid
        if (!$payAcc) $payAcc = fin_acct($pdo, 'bank');
        $pay = [
            ['account_id' => fin_acct($pdo, 'salary_payable'), 'debit' => $net, 'desc' => "Net pay settled $who"],
            ['account_id' => $payAcc, 'credit' => $net, 'desc' => "Salary paid $who"],
        ];
        if (!fin_nets_equal($have, fin_lines_net($pay))) {
            fin_post($pdo, ['date' => $r['payment_date'] ?: date('Y-m-d'), 'type' => 'payroll_payment', 'ref_id' => $recordId, 'lines' => $pay, 'replace' => true, 'dept' => (int)$r['emp_dept'] ?: null,
                'description' => "Salary payment {$r['pay_period']} - $who"]);
        }
    } else {
        fin_unpost($pdo, 'payroll_payment', $recordId, 'Payroll no longer paid');
    }
}

// In this system a store's id IS its inventory account id (children of Inventory, parent 9). Anything else falls back to the clinic store.
function fin_store_acct(PDO $pdo, int $storeId): int {
    $q = $pdo->prepare("SELECT id FROM chart_of_accounts WHERE id = ? AND parent_id = 9");
    $q->execute([$storeId]);
    return (int)($q->fetchColumn() ?: fin_acct($pdo, 'store_clinic'));
}

// Stock value into (+$value) or out of (-$value) a store, against one other account. Idempotent per (type, ref_id).
function fin_post_stock(PDO $pdo, string $type, string $refId, int $storeId, float $value, int $otherAcct, string $desc, ?string $date = null, ?int $dept = null): ?int {
    $value = round($value, 2);
    $store = fin_store_acct($pdo, $storeId);
    if (abs($value) < 0.005 || $store === $otherAcct) return null;
    $in = $value > 0;
    return fin_post($pdo, ['date' => $date ?: date('Y-m-d'), 'type' => $type, 'ref_id' => $refId, 'replace' => true, 'description' => $desc, 'dept' => $dept, 'lines' => [
        ['account_id' => $in ? $store : $otherAcct, 'debit'  => abs($value), 'desc' => $desc],
        ['account_id' => $in ? $otherAcct : $store, 'credit' => abs($value), 'desc' => $desc],
    ]]);
}

// What is still owed on a GRN before its supplier bill: accepted value less returns made while it was uninvoiced
function fin_grn_open_value(PDO $pdo, int $grnId): float {
    $q = $pdo->prepare("SELECT COALESCE((SELECT SUM(quantity_accepted * unit_cost) FROM goods_receiving_items WHERE grn_id = ?), 0)
                             - COALESCE((SELECT SUM(amount) FROM supplier_returns WHERE grn_id = ? AND against = 'grni'), 0)");
    $q->execute([$grnId, $grnId]);
    return round((float)$q->fetchColumn(), 2);
}

// For clinical workflows (billing, consultation, ward): a ledger problem must not block care. It is logged,
// shows up in Finance > Integrity, and the safety net retries it after the next write.
function fin_post_invoice_safe(PDO $pdo, int $invoiceId): void {
    try { fin_post_invoice($pdo, $invoiceId); }
    catch (Throwable $e) { error_log("fin_post_invoice #$invoiceId: " . $e->getMessage()); }
}
